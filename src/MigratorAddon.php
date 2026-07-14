<?php

declare(strict_types=1);

namespace Shopper\Migrator;

use Shopper\Addon\BaseAddon;
use Shopper\Migrator\Livewire\SlideOvers\ImportWooCommerce;
use Shopper\Migrator\Livewire\SlideOvers\MigrationProgress;
use Shopper\ShopperPanel;

final class MigratorAddon extends BaseAddon
{
    public function getId(): string
    {
        return 'migrator';
    }

    public function register(ShopperPanel $panel): void
    {
        $panel->addonLivewireComponents([
            'slide-overs.import-woocommerce' => ImportWooCommerce::class,
            'slide-overs.migration-progress' => MigrationProgress::class,
        ]);
    }
}
