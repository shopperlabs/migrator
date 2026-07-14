<?php

declare(strict_types=1);

namespace Shopper\Migrator\Console;

use Illuminate\Console\Command;
use Shopper\Migrator\Contracts\MigrationSource;
use Shopper\Migrator\MigrationSourceManager;
use Shopper\Models\Permission;
use Shopper\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

#[AsCommand(name: 'shopper:migrator:install')]
final class InstallCommand extends Command
{
    protected $signature = 'shopper:migrator:install';

    protected $description = 'Install Shopper Migrator: run migrations and create the migrator browse and create permissions';

    /**
     * @var list<string>
     */
    private const array PERMISSIONS = ['browse', 'create'];

    public function handle(): int
    {
        $this->callSilently('migrate', ['--force' => true]);
        $this->createPermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->grantToAdministrator();

        info('Shopper Migrator installed.');

        $sources = app(MigrationSourceManager::class)->sources()
            ->map(fn (MigrationSource $source): string => $source->name())
            ->values();

        if ($sources->isNotEmpty()) {
            note("Available sources in the product import modal: {$sources->join(', ', ' and ')}.");
        }

        return self::SUCCESS;
    }

    private function createPermissions(): void
    {
        foreach (self::PERMISSIONS as $verb) {
            Permission::query()->firstOrCreate([
                'name' => "migrator.{$verb}",
                'group_name' => 'migrator',
                'display_name' => __("shopper::permissions.generate.{$verb}.display_name", ['item' => 'Migrator']),
                'description' => __("shopper::permissions.generate.{$verb}.description", ['item' => 'migrator']),
                'can_be_removed' => false,
            ]);
        }
    }

    private function grantToAdministrator(): void
    {
        $administrator = Role::query()
            ->where('name', config('shopper.admin.roles.admin', 'administrator'))
            ->first();

        $administrator?->permissions()->syncWithoutDetaching(
            Permission::query()
                ->where('name', 'like', 'migrator.%')
                ->pluck('id')
                ->all(),
        );
    }
}
