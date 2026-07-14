<?php

declare(strict_types=1);

namespace Shopper\Migrator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Shopper\Core\Enum\ImportStatus;
use Shopper\Core\Import\StartProductImport;
use Shopper\Core\Models\ProductImport;
use Shopper\Migrator\Contracts\MigrationConnection;
use Shopper\Migrator\Enum\MigrationEntityType;
use Shopper\Migrator\MigrationSourceManager;
use Shopper\Migrator\Models\StoreMigration;
use Shopper\Migrator\Models\StoreMigrationEntity;
use Shopper\Migrator\Support\WriteResult;
use Throwable;

final class MigrateEntityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $entityId,
    ) {}

    public function handle(): void
    {
        /** @var ?StoreMigrationEntity $entity */
        $entity = StoreMigrationEntity::query()->find($this->entityId);

        if ($entity === null || $entity->status->isFinished()) {
            return;
        }

        $migration = $entity->migration;

        if ($migration->status === ImportStatus::Pending) {
            $migration->update(['status' => ImportStatus::Processing, 'started_at' => now()]);
        }

        $source = resolve(MigrationSourceManager::class)->source($migration->source);
        $connection = $source->connect($migration->decryptedCredentials());

        $entity->update(['status' => ImportStatus::Processing]);

        try {
            $this->migrate($entity, $migration, $connection);
        } catch (Throwable $e) {
            $entity->update([
                'status' => ImportStatus::Failed,
                'errors' => [...($entity->errors ?? []), ['handle' => '*', 'message' => $e->getMessage()]],
            ]);
        }

        if ($entity->type !== MigrationEntityType::Products || $entity->refresh()->status->isFinished()) {
            $migration->refreshStatus();
        }
    }

    /**
     * A hard failure (timeout, OOM, DB loss) escapes handle() and breaks the chain, leaving the
     * following entities Pending. Mark this entity and the whole migration failed so it reaches
     * a terminal state instead of hanging in Processing forever.
     */
    public function failed(Throwable $e): void
    {
        /** @var ?StoreMigrationEntity $entity */
        $entity = StoreMigrationEntity::query()->find($this->entityId);

        if ($entity === null) {
            return;
        }

        $entity->update([
            'status' => ImportStatus::Failed,
            'errors' => [...($entity->errors ?? []), ['handle' => '*', 'message' => $e->getMessage()]],
        ]);

        $entity->migration->markFailed();
    }

    private function migrate(StoreMigrationEntity $entity, StoreMigration $migration, MigrationConnection $connection): void
    {
        if ($entity->type === MigrationEntityType::Products) {
            $this->products($entity, $migration, $connection);

            return;
        }

        $writer = $connection->writer($entity->type);

        if ($writer === null) {
            return;
        }

        $result = new WriteResult;

        foreach ($connection->records($entity->type) as $records) {
            $result->merge($writer->write($records));

            $entity->update([
                'migrated_count' => $result->migrated,
                'failed_count' => $result->failed,
                'errors' => $result->errors,
            ]);
        }

        $this->finish($entity, $result);
    }

    private function products(StoreMigrationEntity $entity, StoreMigration $migration, MigrationConnection $connection): void
    {
        $disk = config('shopper.migrator.import_disk', 'local');

        $import = ProductImport::query()->create([
            'source' => $migration->source,
            'disk' => $disk,
            'file_path' => 'shopper/imports/'.$migration->source.'-'.str()->ulid().'.jsonl',
            'status' => ImportStatus::Pending,
            'user_id' => $migration->user_id,
        ]);

        $entity->update(['product_import_id' => $import->id]);

        $connection->fetchCatalog($import);

        resolve(StartProductImport::class)->execute($import->refresh());
    }

    private function finish(StoreMigrationEntity $entity, WriteResult $result): void
    {
        $entity->update([
            'status' => $result->failed > 0 ? ImportStatus::CompletedWithErrors : ImportStatus::Completed,
            'migrated_count' => $result->migrated,
            'failed_count' => $result->failed,
            'errors' => $result->errors,
            'total' => max($entity->total, $result->migrated + $result->failed),
        ]);
    }
}
