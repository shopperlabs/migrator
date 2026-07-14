<?php

declare(strict_types=1);

namespace Shopper\Migrator\Enum;

use Shopper\Core\Contracts\HasIcon;
use Shopper\Core\Contracts\HasLabel;
use Shopper\Core\Traits\ArrayableEnum;
use Shopper\Core\Traits\HasEnumStaticMethods;

enum MigrationEntityType: string implements HasIcon, HasLabel
{
    use ArrayableEnum;
    use HasEnumStaticMethods;

    case Categories = 'categories';

    case Collections = 'collections';

    case Tags = 'tags';

    case Brands = 'brands';

    case Attributes = 'attributes';

    case Products = 'products';

    case Customers = 'customers';

    case Discounts = 'discounts';

    case Orders = 'orders';

    public function getLabel(): string
    {
        return __("shopper-migrator::messages.entities.{$this->value}");
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Categories => 'phosphor-folders-duotone',
            self::Collections => 'phosphor-squares-four-duotone',
            self::Tags => 'phosphor-tag-duotone',
            self::Brands => 'phosphor-medal-duotone',
            self::Attributes => 'phosphor-sliders-duotone',
            self::Products => 'phosphor-package-duotone',
            self::Customers => 'phosphor-users-duotone',
            self::Discounts => 'phosphor-ticket-duotone',
            self::Orders => 'phosphor-shopping-bag-duotone',
        };
    }
}
