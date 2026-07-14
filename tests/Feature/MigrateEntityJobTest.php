<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Shopper\Core\Enum\ImportStatus;
use Shopper\Core\Models\ProductTag;
use Shopper\Migrator\Enum\MigrationEntityType;
use Shopper\Migrator\Jobs\MigrateEntityJob;
use Shopper\Migrator\Models\StoreMigration;
use Shopper\Migrator\Models\StoreMigrationEntity;

function makeMigrationEntity(MigrationEntityType $type): StoreMigrationEntity
{
    $migration = StoreMigration::query()->create([
        'source' => 'woocommerce',
        'credentials' => encrypt(['store_url' => 'https://store.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs']),
        'status' => ImportStatus::Pending,
    ]);

    return $migration->entities()->create([
        'type' => $type,
        'status' => ImportStatus::Pending,
        'total' => 2,
    ]);
}

describe(MigrateEntityJob::class, function (): void {
    it('migrates a paginated entity and completes it', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products/tags*' => Http::response([
                ['id' => 1, 'name' => 'Summer', 'slug' => 'summer'],
                ['id' => 2, 'name' => 'Basics', 'slug' => 'basics'],
            ]),
        ]);

        $entity = makeMigrationEntity(MigrationEntityType::Tags);

        (new MigrateEntityJob($entity->id))->handle();

        $entity->refresh();

        expect($entity->status)->toBe(ImportStatus::Completed)
            ->and($entity->migrated_count)->toBe(2)
            ->and(ProductTag::query()->where('name', 'Summer')->exists())->toBeTrue()
            ->and($entity->migration->status)->toBe(ImportStatus::Completed);
    });

    it('does not truncate when per_page is configured above the WooCommerce cap', function (): void {
        config(['shopper.migrator.per_page' => 250]);

        Http::fake([
            'store.test/wp-json/wc/v3/products/tags*' => Http::sequence()
                ->push(array_map(fn (int $i): array => ['id' => $i, 'name' => "Tag {$i}", 'slug' => "tag-{$i}"], range(1, 100)))
                ->push(array_map(fn (int $i): array => ['id' => $i, 'name' => "Tag {$i}", 'slug' => "tag-{$i}"], range(101, 130))),
        ]);

        $entity = makeMigrationEntity(MigrationEntityType::Tags);

        (new MigrateEntityJob($entity->id))->handle();

        expect($entity->refresh()->migrated_count)->toBe(130)
            ->and(ProductTag::query()->count())->toBe(130);
    });

    it('drives the whole migration to a terminal failed state when the job dies hard', function (): void {
        $entity = makeMigrationEntity(MigrationEntityType::Tags);
        // A sibling that the broken chain would otherwise leave Pending forever.
        $entity->migration->entities()->create([
            'type' => MigrationEntityType::Brands,
            'status' => ImportStatus::Pending,
            'total' => 0,
        ]);

        (new MigrateEntityJob($entity->id))->failed(new RuntimeException('worker killed'));

        $entity->refresh();

        expect($entity->status)->toBe(ImportStatus::Failed)
            ->and($entity->errors[0]['message'])->toBe('worker killed')
            ->and($entity->migration->refresh()->status)->toBe(ImportStatus::Failed)
            ->and($entity->migration->finished_at)->not->toBeNull();
    });

    it('marks the entity as failed without breaking the chain when the source errors', function (): void {
        Http::fake([
            'store.test/*' => Http::response(['message' => 'boom'], 500),
        ]);

        $entity = makeMigrationEntity(MigrationEntityType::Tags);

        (new MigrateEntityJob($entity->id))->handle();

        $entity->refresh();

        expect($entity->status)->toBe(ImportStatus::Failed)
            ->and($entity->errors[0]['handle'])->toBe('*')
            ->and($entity->migration->status)->toBe(ImportStatus::CompletedWithErrors);
    });
});
