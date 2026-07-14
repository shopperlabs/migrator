<?php

declare(strict_types=1);

namespace Shopper\Migrator\Contracts;

use Shopper\Core\Models\ProductImport;
use Shopper\Migrator\Enum\MigrationEntityType;

interface MigrationConnection
{
    /**
     * Verify the credentials against the source. Throws on failure.
     */
    public function test(): void;

    /**
     * Number of records the source holds for the given entity type.
     */
    public function total(MigrationEntityType $type): int;

    /**
     * Writer that persists a non-product entity into Shopper.
     * Returns null for Products, which are handled by the core import pipeline.
     */
    public function writer(MigrationEntityType $type): ?EntityWriter;

    /**
     * Stream the source records for an entity, one page at a time, so the caller
     * can persist progress incrementally.
     *
     * @return iterable<int, array<int, array<string, mixed>>>
     */
    public function records(MigrationEntityType $type): iterable;

    /**
     * Write the product catalog (as the JSONL file the core import pipeline reads).
     */
    public function fetchCatalog(ProductImport $import): void;
}
