<?php

declare(strict_types=1);

use Shopper\Core\Import\ImportManager;
use Shopper\Core\Import\ProductRow;
use Shopper\Migrator\Sources\WooCommerce\MapWooCommerceProduct;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceSource;

describe(WooCommerceSource::class, function (): void {
    it('is registered on the import manager by the addon', function (): void {
        $source = resolve(ImportManager::class)->source('woocommerce');

        expect($source)->toBeInstanceOf(WooCommerceSource::class)
            ->and($source->code())->toBe('woocommerce')
            ->and(resolve(ImportManager::class)->availableSources())->toContain('woocommerce');
    });

    it('reads a staging file back into product rows', function (): void {
        $mapper = new MapWooCommerceProduct(wooFixture('categories'));

        $path = tempnam(sys_get_temp_dir(), 'woo');
        file_put_contents($path, implode(PHP_EOL, [
            json_encode($mapper->map(wooFixture('product-simple'))),
            json_encode($mapper->map(wooFixture('product-variable'), wooFixture('variations'))),
        ]));

        $products = (new WooCommerceSource)->read($path)->all();

        expect($products)->toHaveCount(2)
            ->and($products[0])->toBeInstanceOf(ProductRow::class)
            ->and($products[0]->handle)->toBe('premium-quality-t-shirt')
            ->and($products[0]->isStandard())->toBeTrue()
            ->and($products[0]->categories)->toBe(['Clothing', 'T-shirts'])
            ->and($products[1]->handle)->toBe('ship-your-idea')
            ->and($products[1]->isStandard())->toBeFalse()
            ->and($products[1]->optionNames)->toBe(['Color'])
            ->and($products[1]->variants)->toHaveCount(2)
            ->and($products[1]->variants[1]->compareAtPrice)->toBe(20.00)
            ->and($products[1]->images[0]->url)->toBe('https://example.com/wp-content/uploads/hoodie.jpg');
    });

    it('registers the woocommerce brand icon', function (): void {
        $svg = svg('migrator-woocommerce')->toHtml();

        expect($svg)->toContain('<svg')
            ->and($svg)->toContain('#7F54B3');
    });
});
