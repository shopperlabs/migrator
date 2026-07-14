<?php

declare(strict_types=1);

use Shopper\Migrator\Sources\WooCommerce\MapWooCommerceProduct;

describe(MapWooCommerceProduct::class, function (): void {
    it('maps a simple product to a single-variant row', function (): void {
        $mapper = new MapWooCommerceProduct(wooFixture('categories'));

        $row = $mapper->map(wooFixture('product-simple'));

        expect($row['handle'])->toBe('premium-quality-t-shirt')
            ->and($row['name'])->toBe('Premium Quality T-Shirt')
            ->and($row['published'])->toBeTrue()
            ->and($row['option_names'])->toBe([])
            ->and($row['tags'])->toBe(['Cotton', 'Summer'])
            ->and($row['variants'])->toHaveCount(1)
            ->and($row['variants'][0]['sku'])->toBe('WOO-TSHIRT-1')
            ->and($row['variants'][0]['price'])->toBe(21.99)
            ->and($row['variants'][0]['compare_at_price'])->toBeNull()
            ->and($row['variants'][0]['quantity'])->toBe(3)
            ->and($row['variants'][0]['weight_value'])->toBe(0.5)
            ->and($row['variants'][0]['allow_backorder'])->toBeFalse()
            ->and($row['images'])->toHaveCount(2)
            ->and($row['images'][0]['url'])->toBe('https://example.com/wp-content/uploads/tshirt-front.jpg')
            ->and($row['images'][1]['alt'])->toBeNull();
    });

    it('rebuilds the category path from the category tree', function (): void {
        $mapper = new MapWooCommerceProduct(wooFixture('categories'));

        $row = $mapper->map(wooFixture('product-simple'));

        expect($row['categories'])->toBe(['Clothing', 'T-shirts']);
    });

    it('maps a variable product with its variations and options', function (): void {
        $mapper = new MapWooCommerceProduct(wooFixture('categories'));

        $row = $mapper->map(wooFixture('product-variable'), wooFixture('variations'));

        expect($row['option_names'])->toBe(['Color'])
            ->and($row['variants'])->toHaveCount(2)
            ->and($row['variants'][0]['options'])->toBe(['Color' => 'Black'])
            ->and($row['variants'][0]['price'])->toBe(20.00)
            ->and($row['variants'][0]['compare_at_price'])->toBeNull()
            ->and($row['variants'][0]['quantity'])->toBe(8)
            ->and($row['variants'][0]['image_url'])->toBe('https://example.com/wp-content/uploads/hoodie-black.jpg')
            ->and($row['variants'][1]['options'])->toBe(['Color' => 'Green'])
            ->and($row['variants'][1]['price'])->toBe(15.00)
            ->and($row['variants'][1]['compare_at_price'])->toBe(20.00)
            ->and($row['variants'][1]['quantity'])->toBe(0)
            ->and($row['variants'][1]['allow_backorder'])->toBeTrue();
    });

    it('takes the parent stock when a variation manages stock at parent level', function (): void {
        $mapper = new MapWooCommerceProduct([]);

        $row = $mapper->map(
            [
                'slug' => 'hoodie',
                'name' => 'Hoodie',
                'type' => 'variable',
                'manage_stock' => true,
                'stock_quantity' => 240,
                'attributes' => [
                    ['name' => 'Color', 'variation' => true],
                ],
            ],
            [
                [
                    'sku' => 'HOODIE-BLACK',
                    'manage_stock' => 'parent',
                    'stock_quantity' => null,
                    'attributes' => [['name' => 'Color', 'option' => 'Black']],
                ],
            ],
        );

        expect($row['variants'][0]['quantity'])->toBe(240);
    });

    it('does not hang when the category tree contains a cycle', function (): void {
        $mapper = new MapWooCommerceProduct([
            ['id' => 1, 'name' => 'A', 'parent' => 2],
            ['id' => 2, 'name' => 'B', 'parent' => 1],
        ]);

        $row = $mapper->map([
            'slug' => 'broken',
            'name' => 'Broken',
            'categories' => [['id' => 1, 'name' => 'A']],
        ]);

        expect($row['categories'])->toBe(['B', 'A']);
    });

    it('decodes html entities coming from the source api', function (): void {
        $mapper = new MapWooCommerceProduct([['id' => 40, 'name' => 'Food &amp; Beverages', 'parent' => 0]]);

        $row = $mapper->map([
            'slug' => 'bowl-pestle',
            'name' => 'Bowl &amp; Pestle',
            'categories' => [['id' => 40, 'name' => 'Food &amp; Beverages']],
            'tags' => [['id' => 1, 'name' => 'Kitchen &amp; Home']],
        ]);

        expect($row['name'])->toBe('Bowl & Pestle')
            ->and($row['categories'])->toBe(['Food & Beverages'])
            ->and($row['tags'])->toBe(['Kitchen & Home']);
    });
});
