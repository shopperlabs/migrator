# Changelog

All notable changes to `shopper/migrator` will be documented in this file.

## v1.0.0 - 2026-10-23

> [!IMPORTANT]
First release of Migrator, a free add-on for Shopper 3. Move a WooCommerce store to Shopper from the admin: the catalog, customers and coupons are read through the WooCommerce REST API and written into Shopper by queued jobs.

### Installation

Shopper 3 is still a release candidate, so your application needs:

```json
"minimum-stability": "RC",
"prefer-stable": true

```
```bash
composer require shopper/migrator

```
Register the add-on in the `register` method of your `AppServiceProvider`:

```php
use Shopper\Facades\Shopper;
use Shopper\Migrator\MigratorAddon;

Shopper::addons([
    new MigratorAddon,
]);

```
Then run the installer:

```bash
php artisan shopper:migrator:install

```
It runs the migrations and creates the `migrator.browse` and `migrator.create` permissions, granted to the administrator role. Migrations run on the queue, so a worker must be running.

### New Features

- feat(woocommerce): migrate categories with their hierarchy, tags, brands, attributes with their values, products, customers and coupons from the WooCommerce REST API (`/wp-json/wc/v3`)
- feat(admin): "Import from WooCommerce" panel in the product import modal: store URL and read-only API keys, a connection test, then the data types to migrate with the number of records found for each. Requires `migrator.create`.
- feat(admin): progress panel with the migrated and failed counts and the errors of each data type, refreshed every 2 seconds while the migration runs. Requires `migrator.browse`.
- feat(products): products, variations, options, images, prices and stock go through the core product import pipeline. Products are matched by slug and variants by SKU, so running a migration again updates them instead of duplicating them.
- feat(customers): customers are matched by email and imported with their billing and shipping addresses. WooCommerce does not expose passwords, so customers are created without one.
- feat(discounts): coupons become order-level discounts (percentage or fixed amount, minimum amount, usage limits, expiry date). A coupon with restrictions Shopper cannot represent (products, categories, emails, sale items, maximum amount, `fixed_product` type) is imported disabled, with the dropped restrictions listed in its `metadata`. A code that already exists is skipped.
- feat(security): API keys are stored encrypted. Store URLs must use HTTPS. The store host is resolved once and refused when any of its addresses is not public (private, loopback, link-local or reserved), then every request is pinned to the checked address, so a DNS change cannot aim the migration at an internal service. A redirect to another host is refused and names the address to enter instead. `MIGRATOR_ALLOW_LOCAL_HOSTS` and `MIGRATOR_VERIFY_SSL` lift this for local development, and certificate verification stays on in production. ([#3](https://github.com/shopperlabs/migrator/pull/3))
- feat(sources): `MigrationSource` contract and `MigrationSourceManager`, built on Laravel's `Manager`, to add other platforms ([#1](https://github.com/shopperlabs/migrator/pull/1))
- feat(lang): English, French and Spanish translations

### Requirements

- PHP `8.3+`
- Laravel `12.68+` or `13.27+`
- Shopper `^3.0.0-rc.4`, which also matches `3.0.0` stable
- A queue worker
- A WooCommerce store served over HTTPS, with REST API keys

### Contributors

@mckenziearts

**Full Changelog**: https://github.com/shopperlabs/migrator/commits/v1.0.0
