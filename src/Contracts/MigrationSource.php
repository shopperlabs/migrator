<?php

declare(strict_types=1);

namespace Shopper\Migrator\Contracts;

use Shopper\Core\Import\Contracts\ImportSource;
use Shopper\Migrator\Enum\MigrationEntityType;

interface MigrationSource
{
    /**
     * Unique code, shared with the core ImportManager source (e.g. 'woocommerce').
     */
    public function code(): string;

    public function name(): string;

    public function icon(): string;

    /**
     * Credential inputs shown in the import wizard, keyed by credential name.
     * The keys are the credentials passed back to {@see self::connect()}.
     *
     * @return array<string, \Filament\Forms\Components\Field>
     */
    public function credentialFields(): array;

    /**
     * Open an authenticated connection from the collected credentials.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function connect(array $credentials): MigrationConnection;

    /**
     * Entity types this source is able to migrate. Each source declares its own
     * subset of the shared Shopper-side vocabulary
     *
     * @return array<int, MigrationEntityType>
     */
    public function supportedEntities(): array;

    /**
     * The product ImportSource fed into the core ImportManager, so a platform is
     * declared once and its file-based product pipeline is derived from it.
     */
    public function productImportSource(): ImportSource;
}
