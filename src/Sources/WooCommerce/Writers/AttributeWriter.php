<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Shopper\Core\Enum\FieldType;
use Shopper\Core\Models\Attribute;
use Shopper\Core\Models\AttributeValue;
use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceClient;
use Shopper\Migrator\Support\WriteResult;
use Throwable;

final class AttributeWriter implements EntityWriter
{
    public function __construct(
        private readonly WooCommerceClient $client,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;

        foreach ($records as $wcAttribute) {
            try {
                $name = html_entity_decode((string) $wcAttribute['name'], ENT_QUOTES | ENT_HTML5);

                $attribute = Attribute::query()->where('name', $name)->first()
                    ?? Attribute::query()->create([
                        'name' => $name,
                        'slug' => $name,
                        'type' => FieldType::Select,
                        'is_enabled' => true,
                    ]);

                foreach ($this->client->attributeTerms((int) $wcAttribute['id']) as $term) {
                    $value = html_entity_decode((string) $term['name'], ENT_QUOTES | ENT_HTML5);

                    AttributeValue::query()->firstOrCreate(
                        ['attribute_id' => $attribute->id, 'value' => $value],
                        [
                            'key' => str($value)->slug()->toString(),
                            'position' => (int) $attribute->values()->max('position') + 1,
                        ]
                    );
                }

                $result->success();
            } catch (Throwable $e) {
                $result->failure((string) $wcAttribute['slug'], $e->getMessage());
            }
        }

        return $result;
    }
}
