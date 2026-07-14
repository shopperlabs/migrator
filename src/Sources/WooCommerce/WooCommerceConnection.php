<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use InvalidArgumentException;
use Shopper\Core\Models\ProductImport;
use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Contracts\MigrationConnection;
use Shopper\Migrator\Enum\MigrationEntityType;
use Shopper\Migrator\Sources\WooCommerce\Writers\AttributeWriter;
use Shopper\Migrator\Sources\WooCommerce\Writers\BrandWriter;
use Shopper\Migrator\Sources\WooCommerce\Writers\CategoryWriter;
use Shopper\Migrator\Sources\WooCommerce\Writers\CouponWriter;
use Shopper\Migrator\Sources\WooCommerce\Writers\CustomerWriter;
use Shopper\Migrator\Sources\WooCommerce\Writers\TagWriter;

final class WooCommerceConnection implements MigrationConnection
{
    public function __construct(
        private readonly WooCommerceClient $client,
    ) {}

    public function test(): void
    {
        $this->client->testConnection();
    }

    public function total(MigrationEntityType $type): int
    {
        return $this->client->total($this->endpoint($type));
    }

    public function writer(MigrationEntityType $type): ?EntityWriter
    {
        return match ($type) {
            MigrationEntityType::Categories => new CategoryWriter,
            MigrationEntityType::Tags => new TagWriter,
            MigrationEntityType::Brands => new BrandWriter,
            MigrationEntityType::Attributes => new AttributeWriter($this->client),
            MigrationEntityType::Customers => new CustomerWriter,
            MigrationEntityType::Discounts => new CouponWriter,
            MigrationEntityType::Products => null,
            default => throw new InvalidArgumentException("WooCommerce cannot migrate [{$type->value}]."),
        };
    }

    public function records(MigrationEntityType $type): iterable
    {
        return match ($type) {
            MigrationEntityType::Categories => [$this->client->categories()],
            MigrationEntityType::Attributes => [$this->client->attributes()],
            default => $this->paginate($this->endpoint($type)),
        };
    }

    public function fetchCatalog(ProductImport $import): void
    {
        (new WooCommerceCatalogFetcher)->fetch($import, $this->client);
    }

    /**
     * @return iterable<int, array<int, array<string, mixed>>>
     */
    private function paginate(string $endpoint): iterable
    {
        $perPage = $this->client->perPage();
        $page = 1;

        do {
            $records = $this->client->page($endpoint, $page, $perPage);

            yield $records;

            $page++;
        } while (count($records) === $perPage);
    }

    private function endpoint(MigrationEntityType $type): string
    {
        return match ($type) {
            MigrationEntityType::Categories => 'products/categories',
            MigrationEntityType::Tags => 'products/tags',
            MigrationEntityType::Brands => 'products/brands',
            MigrationEntityType::Attributes => 'products/attributes',
            MigrationEntityType::Products => 'products',
            MigrationEntityType::Customers => 'customers',
            MigrationEntityType::Discounts => 'coupons',
            default => throw new InvalidArgumentException("WooCommerce has no endpoint for [{$type->value}]."),
        };
    }
}
