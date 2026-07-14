<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Support\WriteResult;
use Shopper\Core\Models\Contracts\Category as CategoryContract;
use Throwable;

final class CategoryWriter implements EntityWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;
        $remaining = collect($records)->keyBy('id');
        $mapped = [0 => null];

        while ($remaining->isNotEmpty()) {
            $writable = $remaining->filter(
                fn (array $category): bool => array_key_exists((int) ($category['parent'] ?? 0), $mapped)
            );

            if ($writable->isEmpty()) {
                foreach ($remaining as $category) {
                    $result->failure((string) $category['slug'], 'Parent category could not be resolved.');
                }

                break;
            }

            foreach ($writable as $category) {
                try {
                    $mapped[(int) $category['id']] = $this->upsert($category, $mapped[(int) ($category['parent'] ?? 0)]);
                    $result->success();
                } catch (Throwable $e) {
                    $result->failure((string) $category['slug'], $e->getMessage());
                }

                $remaining->forget($category['id']);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $category
     */
    private function upsert(array $category, ?int $parentId): int
    {
        $name = html_entity_decode((string) $category['name'], ENT_QUOTES | ENT_HTML5);

        $existing = resolve(CategoryContract::class)::query()
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->first();

        if ($existing !== null) {
            $existing->update(['description' => $category['description'] ?: $existing->description]);

            return $existing->id;
        }

        return resolve(CategoryContract::class)::query()->create([
            'name' => $name,
            'slug' => $name,
            'parent_id' => $parentId,
            'description' => $category['description'] ?: null,
            'is_enabled' => true,
        ])->id;
    }
}
