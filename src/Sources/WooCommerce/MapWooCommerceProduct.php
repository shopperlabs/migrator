<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

final class MapWooCommerceProduct
{
    /**
     * Categories keyed by id, so climbing the ancestor chain is an O(1) lookup per
     * product instead of rebuilding the index on every mapped product.
     *
     * @var array<int, array{id: int, name: string, parent: int}>
     */
    private readonly array $categoriesById;

    /**
     * @param  array<int, array{id: int, name: string, parent: int}>  $categories
     */
    public function __construct(array $categories = [])
    {
        $this->categoriesById = collect($categories)->keyBy('id')->all();
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<int, array<string, mixed>>  $variations
     * @return array<string, mixed>
     */
    public function map(array $product, array $variations = []): array
    {
        $optionNames = collect($product['attributes'] ?? [])
            ->filter(fn (array $attribute): bool => (bool) ($attribute['variation'] ?? false))
            ->pluck('name')
            ->values()
            ->all();

        return [
            'handle' => (string) $product['slug'],
            'name' => $this->decode((string) $product['name']),
            'description' => $this->cleanText($product['description'] ?? '') ?: $this->cleanText($product['short_description'] ?? ''),
            'brand' => null,
            'categories' => $this->categoryPath($product['categories'] ?? []),
            'tags' => collect($product['tags'] ?? [])->pluck('name')->map($this->decode(...))->all(),
            'published' => ($product['status'] ?? 'publish') === 'publish',
            'seo_title' => null,
            'seo_description' => $this->cleanText($product['short_description'] ?? '') ?: null,
            'option_names' => $optionNames,
            'variants' => $variations === []
                ? [$this->mapVariant($product, [])]
                : array_map(
                    fn (array $variation): array => $this->mapVariant($variation, $optionNames, $this->parentStock($product)),
                    $variations,
                ),
            'images' => collect($product['images'] ?? [])
                ->values()
                ->map(fn (array $image, int $index): array => [
                    'url' => (string) $image['src'],
                    'position' => $index + 1,
                    'alt' => ($image['alt'] ?? '') !== '' ? $image['alt'] : null,
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $optionNames
     * @return array<string, mixed>
     */
    private function mapVariant(array $record, array $optionNames, ?int $parentStock = null): array
    {
        $options = collect($record['attributes'] ?? [])
            ->filter(fn (array $attribute): bool => in_array($attribute['name'] ?? '', $optionNames, true))
            ->mapWithKeys(fn (array $attribute): array => [(string) $attribute['name'] => (string) ($attribute['option'] ?? '')])
            ->all();

        $regular = $this->price($record['regular_price'] ?? null);
        $sale = $this->price($record['sale_price'] ?? null);

        $rawManage = $record['manage_stock'] ?? false;
        $selfManaged = $rawManage === true;
        $parentManaged = $rawManage === 'parent';
        $managed = $selfManaged || $parentManaged;

        $quantity = match (true) {
            $selfManaged => (int) ($record['stock_quantity'] ?? 0),
            $parentManaged => $parentStock ?? 0,
            default => 0,
        };

        return [
            'options' => $options,
            'sku' => ($record['sku'] ?? '') !== '' ? $record['sku'] : null,
            'price' => $sale ?? $regular ?? $this->price($record['price'] ?? null),
            'compare_at_price' => $sale !== null ? $regular : null,
            'quantity' => $quantity,
            'allow_backorder' => ($record['backorders'] ?? 'no') !== 'no'
                || (! $managed && ($record['stock_status'] ?? 'instock') === 'instock'),
            'weight_value' => ($record['weight'] ?? '') !== '' ? (float) $record['weight'] : null,
            'weight_unit' => config('shopper.migrator.weight_unit', 'kg'),
            'requires_shipping' => ! ($record['virtual'] ?? false),
            'image_url' => $record['image']['src'] ?? null,
        ];
    }

    /**
     * Stock quantity tracked on the parent product, used by variations whose
     * manage_stock is 'parent'. Null when the parent does not manage its own stock.
     *
     * @param  array<string, mixed>  $product
     */
    private function parentStock(array $product): ?int
    {
        return ($product['manage_stock'] ?? false) === true
            ? (int) ($product['stock_quantity'] ?? 0)
            : null;
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $productCategories
     * @return array<int, string>
     */
    private function categoryPath(array $productCategories): array
    {
        $first = $productCategories[0] ?? null;

        if ($first === null) {
            return [];
        }

        $path = [];
        $visited = [];
        $current = $this->categoriesById[$first['id']] ?? null;

        while ($current !== null && ! isset($visited[$current['id']])) {
            $visited[$current['id']] = true;
            array_unshift($path, $this->decode((string) $current['name']));
            $current = $this->categoriesById[$current['parent']] ?? null;
        }

        return $path === [] ? [$this->decode((string) $first['name'])] : $path;
    }

    private function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }

    private function price(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    private function cleanText(string $value): string
    {
        return mb_trim($value);
    }
}
