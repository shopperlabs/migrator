<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Support\WriteResult;
use Shopper\Core\Models\Contracts\Brand as BrandContract;
use Throwable;

final class BrandWriter implements EntityWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;

        foreach ($records as $brand) {
            try {
                $name = html_entity_decode((string) $brand['name'], ENT_QUOTES | ENT_HTML5);

                $existing = resolve(BrandContract::class)::query()->where('name', $name)->first();

                if ($existing === null) {
                    resolve(BrandContract::class)::query()->create([
                        'name' => $name,
                        'slug' => $name,
                        'description' => $brand['description'] ?: null,
                        'is_enabled' => true,
                    ]);
                }

                $result->success();
            } catch (Throwable $e) {
                $result->failure((string) $brand['slug'], $e->getMessage());
            }
        }

        return $result;
    }
}
