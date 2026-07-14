<?php

declare(strict_types=1);

use Shopper\Core\Enum\ImportStatus;
use Shopper\Migrator\Enum\MigrationEntityType;
use Shopper\Migrator\Models\StoreMigration;

function makeMigration(ImportStatus $status = ImportStatus::Processing): StoreMigration
{
    return StoreMigration::query()->create([
        'source' => 'woocommerce',
        'credentials' => encrypt(['store_url' => 'https://store.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs']),
        'status' => $status,
    ]);
}

describe(StoreMigration::class, function (): void {
    it('marks a processing migration as failed with a finished timestamp and purges credentials', function (): void {
        $migration = makeMigration();

        $migration->markFailed();

        expect($migration->refresh()->status)->toBe(ImportStatus::Failed)
            ->and($migration->finished_at)->not->toBeNull()
            ->and($migration->credentials)->toBeNull();
    });

    it('does not touch an already failed migration', function (): void {
        $migration = makeMigration(ImportStatus::Failed);
        $migration->forceFill(['finished_at' => now()->subDay()])->save();
        $before = $migration->refresh()->finished_at?->toDateTimeString();

        $migration->markFailed();

        expect($migration->refresh()->finished_at?->toDateTimeString())->toBe($before);
    });

    it('stays processing while an entity is unfinished', function (): void {
        $migration = makeMigration();
        $migration->entities()->create(['type' => MigrationEntityType::Tags, 'status' => ImportStatus::Completed, 'total' => 1]);
        $migration->entities()->create(['type' => MigrationEntityType::Brands, 'status' => ImportStatus::Pending, 'total' => 1]);

        $migration->refreshStatus();

        expect($migration->refresh()->status)->toBe(ImportStatus::Processing);
    });

    it('completes with errors when an entity finished with errors', function (): void {
        $migration = makeMigration();
        $migration->entities()->create(['type' => MigrationEntityType::Tags, 'status' => ImportStatus::Completed, 'total' => 1]);
        $migration->entities()->create(['type' => MigrationEntityType::Brands, 'status' => ImportStatus::CompletedWithErrors, 'total' => 1]);

        $migration->refreshStatus();

        expect($migration->refresh()->status)->toBe(ImportStatus::CompletedWithErrors);
    });

    it('completes cleanly when every entity completed', function (): void {
        $migration = makeMigration();
        $migration->entities()->create(['type' => MigrationEntityType::Tags, 'status' => ImportStatus::Completed, 'total' => 1]);
        $migration->entities()->create(['type' => MigrationEntityType::Brands, 'status' => ImportStatus::Completed, 'total' => 1]);

        $migration->refreshStatus();

        expect($migration->refresh()->status)->toBe(ImportStatus::Completed)
            ->and($migration->credentials)->toBeNull();
    });
});
