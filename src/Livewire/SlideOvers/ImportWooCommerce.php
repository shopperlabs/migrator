<?php

declare(strict_types=1);

namespace Shopper\Migrator\Livewire\SlideOvers;

final class ImportWooCommerce extends ImportStore
{
    protected function sourceCode(): string
    {
        return 'woocommerce';
    }
}
