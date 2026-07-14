<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Support\WriteResult;
use Shopper\Core\Models\ProductTag;
use Throwable;

final class TagWriter implements EntityWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;

        foreach ($records as $tag) {
            try {
                $name = html_entity_decode((string) $tag['name'], ENT_QUOTES | ENT_HTML5);

                ProductTag::query()->firstOrCreate(['name' => $name], ['slug' => $name]);

                $result->success();
            } catch (Throwable $e) {
                $result->failure((string) $tag['slug'], $e->getMessage());
            }
        }

        return $result;
    }
}
