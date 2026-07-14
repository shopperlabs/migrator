<?php

declare(strict_types=1);

use Shopper\Core\Enum\DiscountRequirement;
use Shopper\Core\Enum\DiscountType;
use Shopper\Core\Models\Discount;
use Shopper\Migrator\Sources\WooCommerce\Writers\CouponWriter;

describe(CouponWriter::class, function (): void {
    it('maps woocommerce coupons to discounts', function (): void {
        $result = (new CouponWriter)->write(wooFixture('wc-coupons'));

        expect($result->migrated)->toBe(3)->and($result->failed)->toBe(0);

        $percent = Discount::query()->where('code', 'WELCOME10')->firstOrFail();

        expect($percent->type)->toBe(DiscountType::Percentage)
            ->and($percent->value)->toBe(10)
            ->and($percent->usage_limit)->toBe(100)
            ->and($percent->usage_limit_per_user)->toBeTrue()
            ->and($percent->combinable)->toBeFalse()
            ->and($percent->is_active)->toBeTrue();

        $fixed = Discount::query()->where('code', 'SAVE5')->firstOrFail();

        expect($fixed->type)->toBe(DiscountType::FixedAmount)
            ->and($fixed->value)->toBe(500)
            ->and($fixed->min_required)->toBe(DiscountRequirement::Price->value)
            ->and($fixed->min_required_value)->toBe('5000')
            ->and($fixed->end_at)->toBeNull();

        $expired = Discount::query()->where('code', 'EXPIRED')->firstOrFail();

        expect($expired->is_active)->toBeFalse();
    });

    it('carries the usage count so a nearly-exhausted coupon is not reset to fresh', function (): void {
        (new CouponWriter)->write(wooFixture('wc-coupons'));

        expect(Discount::query()->where('code', 'WELCOME10')->value('total_use'))->toBe(80)
            ->and(Discount::query()->where('code', 'SAVE5')->value('total_use'))->toBe(5);
    });

    it('reads the gmt expiry so the site timezone cannot flip is_active', function (): void {
        config(['app.timezone' => 'Pacific/Kiritimati']); // UTC+14

        (new CouponWriter)->write([[
            'id' => 9,
            'code' => 'EDGE',
            'discount_type' => 'percent',
            'amount' => '10.00',
            // Local time reads as the next day, GMT is still in 2020 (expired).
            'date_expires' => '2021-01-01T13:00:00',
            'date_expires_gmt' => '2020-06-30T23:59:59',
        ]]);

        expect(Discount::query()->where('code', 'EDGE')->value('is_active'))->toBeFalse();
    });

    it('imports a product-restricted coupon as disabled and records why', function (): void {
        $result = (new CouponWriter)->write([[
            'id' => 10,
            'code' => 'SHOES20',
            'discount_type' => 'percent',
            'amount' => '20.00',
            'usage_limit' => 500,
            'usage_count' => 480,
            'product_ids' => [15, 16],
            'email_restrictions' => ['vip@example.com'],
        ]]);

        $discount = Discount::query()->where('code', 'SHOES20')->firstOrFail();

        expect($result->migrated)->toBe(1)
            ->and($discount->is_active)->toBeFalse()
            ->and($discount->total_use)->toBe(480)
            ->and($discount->metadata['migration']['reason'])->toBe('restrictions_not_migrated')
            ->and($discount->metadata['migration']['dropped'])->toContain('product_ids')
            ->and($discount->metadata['migration']['dropped'])->toContain('email_restrictions');
    });

    it('imports a fixed_product coupon as disabled because the scope cannot be represented', function (): void {
        (new CouponWriter)->write([[
            'id' => 11,
            'code' => 'PERITEM5',
            'discount_type' => 'fixed_product',
            'amount' => '5.00',
        ]]);

        $discount = Discount::query()->where('code', 'PERITEM5')->firstOrFail();

        expect($discount->is_active)->toBeFalse()
            ->and($discount->metadata['migration']['dropped'])->toContain('fixed_product');
    });

    it('bounds an out-of-range percentage value', function (): void {
        (new CouponWriter)->write([[
            'id' => 12,
            'code' => 'BOGUS',
            'discount_type' => 'percent',
            'amount' => '150.00',
        ]]);

        expect(Discount::query()->where('code', 'BOGUS')->value('value'))->toBe(100);
    });

    it('skips coupons whose code already exists', function (): void {
        (new CouponWriter)->write(wooFixture('wc-coupons'));
        $result = (new CouponWriter)->write(wooFixture('wc-coupons'));

        expect($result->migrated)->toBe(3)
            ->and(Discount::query()->count())->toBe(3);
    });
});
