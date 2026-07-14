<?php

declare(strict_types=1);

namespace Shopper\Migrator;

use Illuminate\Support\Facades\Bus;
use Shopper\Core\Enum\ImportStatus;
use Shopper\Migrator\Jobs\MigrateEntityJob;
use Shopper\Migrator\Models\StoreMigration;
use Throwable;

final class StartStoreMigration
{
    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<int, string>  $types
     */
    public function execute(string $source, array $credentials, array $types, ?int $userId = null): StoreMigration
    {
        $migrationSource = resolve(MigrationSourceManager::class)->source($source);
        $connection = $migrationSource->connect($credentials);

        /** @var StoreMigration $migration */
        $migration = StoreMigration::query()->create([
            'source' => $source,
            'credentials' => encrypt($credentials),
            'status' => ImportStatus::Pending,
            'user_id' => $userId,
        ]);

        foreach ($migrationSource->supportedEntities() as $type) {
            if (! in_array($type->value, $types, true)) {
                continue;
            }

            $migration->entities()->create([
                'type' => $type,
                'status' => ImportStatus::Pending,
                'total' => $connection->total($type),
            ]);
        }

        $migrationId = $migration->id;

        Bus::chain(
            $migration->entities()->orderBy('id')->pluck('id')
                ->map(fn (int $id): MigrateEntityJob => new MigrateEntityJob($id))
                ->all()
        )
            ->catch(function (Throwable $e) use ($migrationId): void {
                StoreMigration::query()->find($migrationId)?->markFailed();
            })
            ->dispatch();

        return $migration;
    }
}
