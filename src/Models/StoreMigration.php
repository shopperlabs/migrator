<?php

declare(strict_types=1);

namespace Shopper\Migrator\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Shopper\Core\Enum\ImportStatus;

/**
 * @property-read int $id
 * @property-read string $source
 * @property-read ?string $credentials
 * @property-read ImportStatus $status
 * @property-read ?int $user_id
 * @property-read ?CarbonInterface $started_at
 * @property-read ?CarbonInterface $finished_at
 * @property-read Collection<int, StoreMigrationEntity> $entities
 */
class StoreMigration extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('store_migrations');
    }

    /**
     * @return HasMany<StoreMigrationEntity, $this>
     */
    public function entities(): HasMany
    {
        return $this->hasMany(StoreMigrationEntity::class, 'store_migration_id')->orderBy('id');
    }

    /**
     * @return array{store_url: string, consumer_key: string, consumer_secret: string}
     */
    public function decryptedCredentials(): array
    {
        return decrypt($this->credentials);
    }

    public function refreshStatus(): void
    {
        $statuses = $this->entities()->pluck('status');

        if ($statuses->contains(fn (ImportStatus $status): bool => ! $status->isFinished())) {
            return;
        }

        $this->update([
            'status' => $statuses->contains(fn (ImportStatus $status): bool => $status !== ImportStatus::Completed)
                ? ImportStatus::CompletedWithErrors
                : ImportStatus::Completed,
            'finished_at' => now(),
            'credentials' => null,
        ]);
    }

    /**
     * Terminal state for a hard failure (worker crash, OOM, broken chain) so the
     * migration never stays stuck in Processing when the job chain aborts.
     */
    public function markFailed(): void
    {
        if ($this->status === ImportStatus::Failed) {
            return;
        }

        $this->update([
            'status' => ImportStatus::Failed,
            'finished_at' => now(),
            'credentials' => null,
        ]);
    }

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
