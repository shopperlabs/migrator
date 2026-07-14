# Shopper Migrator

Migrate your store from other e-commerce platforms to Shopper. The first supported source is WooCommerce, through its REST API: products, variations, options, categories, tags, images, prices and stock are imported into Shopper using the product import pipeline.

## Installation

```bash
composer require shopper/migrator
```

Register the addon in your `AppServiceProvider::register()`:

```php
use Shopper\Facades\Shopper;
use Shopper\Migrator\MigratorAddon;

public function register(): void
{
    Shopper::addon(new MigratorAddon);
}
```

Then run the installer to apply the migrations and create the `migrator.*` permissions
(they are granted to the administrator role):

```bash
php artisan shopper:migrator:install
```

The WooCommerce source then appears in the import modal on the products page, for any
admin holding the `migrator.create` permission.

## WooCommerce credentials

Generate read-only API keys in your WooCommerce admin under **WooCommerce > Settings > Advanced > REST API**, then paste the store URL, consumer key and consumer secret in the import panel. The store must be served over HTTPS.

By default the migrator refuses non-HTTPS store URLs and hosts on private/loopback networks
(SSRF protection). For local development against a store on your own machine, allow it and
skip certificate verification:

```env
MIGRATOR_ALLOW_LOCAL_HOSTS=true
MIGRATOR_VERIFY_SSL=false
```

`MIGRATOR_VERIFY_SSL` is ignored in production — TLS verification is always enforced there.

## Idempotency

Migrations are idempotent: products are matched by slug and variants by SKU, so running the migration again updates existing records instead of duplicating them.
