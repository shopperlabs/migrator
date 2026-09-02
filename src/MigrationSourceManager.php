<?php

declare(strict_types=1);

namespace Shopper\Migrator;

use Illuminate\Support\Collection;
use Illuminate\Support\Manager;
use Shopper\Migrator\Contracts\MigrationSource;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceMigrationSource;
use UnitEnum;

final class MigrationSourceManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return null;
    }

    /**
     * @param  UnitEnum|string|null  $driver
     */
    public function driver($driver = null): MigrationSource
    {
        return parent::driver($driver);
    }

    public function source(string $name): MigrationSource
    {
        return $this->driver($name);
    }

    /**
     * @return array<int, string>
     */
    public function availableSources(): array
    {
        return array_unique(['woocommerce', ...array_keys($this->customCreators)]);
    }

    /**
     * @return Collection<string, MigrationSource>
     */
    public function sources(): Collection
    {
        return collect($this->availableSources())
            ->mapWithKeys(fn (string $name): array => [$name => $this->source($name)]);
    }

    protected function createWoocommerceDriver(): WooCommerceMigrationSource
    {
        return new WooCommerceMigrationSource;
    }
}
