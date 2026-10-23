<?php

declare(strict_types=1);

namespace Shopper\Migrator\Tests;

use BladeUI\Icons\BladeIconsServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Shopper\Core\CoreServiceProvider;
use Shopper\Migrator\MigratorServiceProvider;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceClient;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WooCommerceClient::resolveHostsUsing(fn (): array => ['8.8.8.8']);
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            PermissionServiceProvider::class,
            CoreServiceProvider::class,
            MigratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('shopper.models', [
            'address' => \Shopper\Core\Models\Address::class,
            'brand' => \Shopper\Core\Models\Brand::class,
            'category' => \Shopper\Core\Models\Category::class,
            'collection' => \Shopper\Core\Models\Collection::class,
            'product' => \Shopper\Core\Models\Product::class,
            'variant' => \Shopper\Core\Models\ProductVariant::class,
            'attribute_product' => \Shopper\Core\Models\AttributeProduct::class,
            'channel' => \Shopper\Core\Models\Channel::class,
            'order' => \Shopper\Core\Models\Order::class,
            'inventory' => \Shopper\Core\Models\Inventory::class,
            'supplier' => \Shopper\Core\Models\Supplier::class,
            'tax_zone' => \Shopper\Core\Models\TaxZone::class,
            'tax_rate' => \Shopper\Core\Models\TaxRate::class,
        ]);
    }
}
