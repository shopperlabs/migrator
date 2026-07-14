<?php

declare(strict_types=1);

use Shopper\Core\Models\Category;
use Shopper\Migrator\Sources\WooCommerce\Writers\CategoryWriter;

describe(CategoryWriter::class, function (): void {
    it('writes the category tree with parents resolved first', function (): void {
        $result = (new CategoryWriter)->write(wooFixture('wc-categories'));

        expect($result->migrated)->toBe(3)
            ->and($result->failed)->toBe(1)
            ->and($result->errors[0]['handle'])->toBe('orphan');

        $child = Category::query()->where('name', 'T-shirts')->firstOrFail();

        expect($child->parent->name)->toBe('Clothing')
            ->and(Category::query()->where('name', 'Food & Beverages')->exists())->toBeTrue();
    });

    it('is idempotent on a second run', function (): void {
        (new CategoryWriter)->write(wooFixture('wc-categories'));
        (new CategoryWriter)->write(wooFixture('wc-categories'));

        expect(Category::query()->where('name', 'Clothing')->count())->toBe(1)
            ->and(Category::query()->where('name', 'T-shirts')->count())->toBe(1);
    });
});
