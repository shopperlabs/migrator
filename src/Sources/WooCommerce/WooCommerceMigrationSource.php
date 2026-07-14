<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use Filament\Forms\Components\TextInput;
use Shopper\Core\Import\Contracts\ImportSource;
use Shopper\Migrator\Contracts\MigrationConnection;
use Shopper\Migrator\Contracts\MigrationSource;
use Shopper\Migrator\Enum\MigrationEntityType;

final class WooCommerceMigrationSource implements MigrationSource
{
    public function code(): string
    {
        return 'woocommerce';
    }

    public function name(): string
    {
        return 'WooCommerce';
    }

    public function icon(): string
    {
        return 'migrator-woocommerce';
    }

    public function credentialFields(): array
    {
        return [
            'store_url' => TextInput::make('store_url')
                ->label(__('shopper-migrator::messages.woocommerce.store_url'))
                ->placeholder('https://my-store.com')
                ->url()
                ->maxLength(2048)
                ->required(),
            'consumer_key' => TextInput::make('consumer_key')
                ->label(__('shopper-migrator::messages.woocommerce.consumer_key'))
                ->placeholder('ck_xxxxxxxxxxxxxxxx')
                ->maxLength(255)
                ->required(),
            'consumer_secret' => TextInput::make('consumer_secret')
                ->label(__('shopper-migrator::messages.woocommerce.consumer_secret'))
                ->placeholder('cs_xxxxxxxxxxxxxxxx')
                ->password()
                ->revealable()
                ->maxLength(255)
                ->required()
                ->helperText(__('shopper-migrator::messages.woocommerce.credentials_helper')),
        ];
    }

    public function connect(array $credentials): MigrationConnection
    {
        return new WooCommerceConnection(new WooCommerceClient(
            storeUrl: (string) $credentials['store_url'],
            consumerKey: (string) $credentials['consumer_key'],
            consumerSecret: (string) $credentials['consumer_secret'],
        ));
    }

    public function supportedEntities(): array
    {
        return [
            MigrationEntityType::Categories,
            MigrationEntityType::Tags,
            MigrationEntityType::Brands,
            MigrationEntityType::Attributes,
            MigrationEntityType::Products,
            MigrationEntityType::Customers,
            MigrationEntityType::Discounts,
        ];
    }

    public function productImportSource(): ImportSource
    {
        return new WooCommerceSource;
    }
}
