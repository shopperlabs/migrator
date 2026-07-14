<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use Generator;
use Illuminate\Support\LazyCollection;
use Shopper\Core\Import\Contracts\ImportSource;
use Shopper\Core\Import\ImageRow;
use Shopper\Core\Import\ProductRow;
use Shopper\Core\Import\VariantRow;

final class WooCommerceSource implements ImportSource
{
    public function code(): string
    {
        return 'woocommerce';
    }

    public function name(): string
    {
        return 'WooCommerce';
    }

    public function description(): string
    {
        return __('shopper-migrator::messages.sources.woocommerce');
    }

    public function icon(): string
    {
        return 'migrator-woocommerce';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function read(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path): Generator {
            $handle = fopen($path, 'r');

            if ($handle === false) {
                return;
            }

            while (($line = fgets($handle)) !== false) {
                $line = mb_trim($line);

                if ($line === '') {
                    continue;
                }

                yield $this->makeProduct((array) json_decode($line, true));
            }

            fclose($handle);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeProduct(array $data): ProductRow
    {
        return new ProductRow(
            handle: (string) ($data['handle'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            description: $data['description'] ?: null,
            brand: $data['brand'] ?? null,
            categories: (array) ($data['categories'] ?? []),
            tags: (array) ($data['tags'] ?? []),
            published: (bool) ($data['published'] ?? false),
            seoTitle: $data['seo_title'] ?? null,
            seoDescription: $data['seo_description'] ?? null,
            optionNames: (array) ($data['option_names'] ?? []),
            variants: array_map(
                fn (array $variant): VariantRow => new VariantRow(
                    options: (array) ($variant['options'] ?? []),
                    sku: $variant['sku'] ?? null,
                    price: $variant['price'] ?? null,
                    compareAtPrice: $variant['compare_at_price'] ?? null,
                    quantity: (int) ($variant['quantity'] ?? 0),
                    weightValue: $variant['weight_value'] ?? null,
                    weightUnit: $variant['weight_unit'] ?? null,
                    requiresShipping: (bool) ($variant['requires_shipping'] ?? true),
                    allowBackorder: (bool) ($variant['allow_backorder'] ?? false),
                    imageUrl: $variant['image_url'] ?? null,
                ),
                (array) ($data['variants'] ?? [])
            ),
            images: array_map(
                fn (array $image): ImageRow => new ImageRow(
                    url: (string) $image['url'],
                    position: (int) ($image['position'] ?? 1),
                    alt: $image['alt'] ?? null,
                ),
                (array) ($data['images'] ?? [])
            ),
        );
    }
}
