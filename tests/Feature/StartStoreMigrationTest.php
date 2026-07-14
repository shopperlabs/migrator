<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Shopper\Core\Enum\ImportStatus;
use Shopper\Migrator\Jobs\MigrateEntityJob;
use Shopper\Migrator\StartStoreMigration;

describe(StartStoreMigration::class, function (): void {
    it('creates the migration with its entities in dependency order and chains the jobs', function (): void {
        Bus::fake();
        Http::fake([
            'store.test/wp-json/wc/v3/*' => Http::response([], 200, ['X-WP-Total' => '7']),
        ]);

        $migration = (new StartStoreMigration)->execute(
            source: 'woocommerce',
            credentials: ['store_url' => 'https://store.test', 'consumer_key' => 'ck', 'consumer_secret' => 'cs'],
            types: ['products', 'categories', 'discounts'],
        );

        expect($migration->status)->toBe(ImportStatus::Pending)
            ->and($migration->entities()->pluck('type')->map->value->all())->toBe(['categories', 'products', 'discounts'])
            ->and($migration->entities()->first()->total)->toBe(7)
            ->and($migration->decryptedCredentials()['store_url'])->toBe('https://store.test');

        Bus::assertChained([
            MigrateEntityJob::class,
            MigrateEntityJob::class,
            MigrateEntityJob::class,
        ]);
    });

});
