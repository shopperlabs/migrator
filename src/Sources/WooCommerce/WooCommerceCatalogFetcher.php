<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use Illuminate\Support\Facades\Storage;
use Shopper\Core\Models\ProductImport;

final class WooCommerceCatalogFetcher
{
    public function fetch(ProductImport $import, WooCommerceClient $client): void
    {
        $mapper = new MapWooCommerceProduct($client->categories());

        $disk = Storage::disk($import->disk);
        $disk->put($import->file_path, '');

        $handle = fopen($disk->path($import->file_path), 'w');
        $perPage = $client->perPage();
        $page = 1;

        do {
            $products = $client->products($page, $perPage);

            foreach ($products as $product) {
                $variations = ($product['type'] ?? 'simple') === 'variable'
                    ? $client->variations((int) $product['id'])
                    : [];

                fwrite($handle, json_encode($mapper->map($product, $variations)).PHP_EOL);
            }

            $page++;
        } while (count($products) === $perPage);

        fclose($handle);
    }
}
