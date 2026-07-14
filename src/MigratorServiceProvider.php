<?php

declare(strict_types=1);

namespace Shopper\Migrator;

use BladeUI\Icons\Factory as IconFactory;
use Illuminate\Support\Facades\Event;
use Shopper\Core\Events\Products\ProductImportCompleted;
use Shopper\Core\Import\Contracts\ImportSource;
use Shopper\Core\Import\ImportManager;
use Shopper\Core\Traits\HasRegisterConfigAndMigrationFiles;
use Shopper\Migrator\Console\InstallCommand;
use Shopper\Migrator\Listeners\SyncProductEntityProgress;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceMigrationSource;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class MigratorServiceProvider extends PackageServiceProvider
{
    use HasRegisterConfigAndMigrationFiles;

    protected string $root = __DIR__.'/..';

    /** @var list<string> */
    protected array $configFiles = [
        'migrator',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('shopper-migrator')
            ->hasTranslations()
            ->hasViews()
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->registerConfigFiles();
        $this->registerDatabase();

        $this->app->singleton(MigrationSourceManager::class);

        $this->callAfterResolving(IconFactory::class, function (IconFactory $factory): void {
            $factory->add('shopper-migrator', [
                'path' => __DIR__.'/../resources/svg',
                'prefix' => 'migrator',
            ]);
        });
    }

    public function packageBooted(): void
    {
        $migrationManager = resolve(MigrationSourceManager::class);
        $importManager = resolve(ImportManager::class);

        $migrationManager->extend(
            'woocommerce',
            fn (): WooCommerceMigrationSource => new WooCommerceMigrationSource,
        );

        // A platform is declared once as a migration source; its product ImportSource
        // is derived from it, so the two registries can never drift apart.
        foreach ($migrationManager->availableSources() as $code) {
            $importManager->extend(
                $code,
                fn (): ImportSource => $migrationManager->source($code)->productImportSource(),
            );
        }

        Event::listen(ProductImportCompleted::class, SyncProductEntityProgress::class);
    }
}
