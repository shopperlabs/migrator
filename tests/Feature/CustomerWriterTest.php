<?php

declare(strict_types=1);

use Shopper\Core\Models\Address;
use Shopper\Core\Models\Country;
use Shopper\Migrator\Sources\WooCommerce\Writers\CustomerWriter;
use Shopper\Migrator\Tests\Stubs\User;

describe(CustomerWriter::class, function (): void {
    it('creates customers with their billing and shipping addresses', function (): void {
        Country::factory()->create(['cca2' => 'CM']);

        $result = (new CustomerWriter)->write(wooFixture('wc-customers'));

        expect($result->migrated)->toBe(2)->and($result->failed)->toBe(0);

        $jane = User::query()->where('email', 'jane@example.com')->firstOrFail();

        expect($jane->first_name)->toBe('Jane')
            ->and($jane->last_name)->toBe('Doe')
            ->and(Address::query()->where('user_id', $jane->id)->count())->toBe(2)
            ->and(Address::query()->where('user_id', $jane->id)->where('type', 'billing')->value('company_name'))->toBe('Acme Inc');
    });

    it('skips addresses whose country is unknown but still creates the customer', function (): void {
        Country::factory()->create(['cca2' => 'CM']);

        (new CustomerWriter)->write(wooFixture('wc-customers'));

        $john = User::query()->where('email', 'john@example.com')->firstOrFail();

        expect(Address::query()->where('user_id', $john->id)->count())->toBe(0);
    });

    it('is idempotent on a second run', function (): void {
        Country::factory()->create(['cca2' => 'CM']);

        (new CustomerWriter)->write(wooFixture('wc-customers'));
        (new CustomerWriter)->write(wooFixture('wc-customers'));

        expect(User::query()->count())->toBe(2)
            ->and(Address::query()->count())->toBe(2);
    });
});
