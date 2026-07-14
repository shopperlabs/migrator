<?php

declare(strict_types=1);

namespace Shopper\Migrator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Shopper\Core\Enum\ImportStatus;
use Shopper\Core\Models\ProductImport;
use Shopper\Migrator\Enum\MigrationEntityType;

/**
 * @property-read int $id
 * @property-read int $store_migration_id
 * @property-read MigrationEntityType $type
 * @property-read ImportStatus $status
 * @property-read int $total
 * @property-read int $migrated_count
 * @property-read int $failed_count
 * @property-read array<int, array<string, string>>|null $errors
 * @property-read ?int $product_import_id
 * @property-read StoreMigration $migration
 * @property-read ?ProductImport $productImport
 */
class StoreMigrationEntity extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return shopper_table('store_migration_entities');
    }

    /**
     * @return BelongsTo<StoreMigration, $this>
     */
    public function migration(): BelongsTo
    {
        return $this->belongsTo(StoreMigration::class, 'store_migration_id');
    }

    /**
     * @return BelongsTo<ProductImport, $this>
     */
    public function productImport(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'product_import_id');
    }

    public function progress(): int
    {
        $total = $this->displayedTotal();

        return $total > 0
            ? min(100, (int) round(($this->displayedMigrated() + $this->displayedFailed()) * 100 / $total))
            : 0;
    }

    public function displayedTotal(): int
    {
        if ($this->type === MigrationEntityType::Products && $this->productImport !== null && $this->productImport->total_products > 0) {
            return $this->productImport->total_products;
        }

        return $this->total;
    }

    public function displayedMigrated(): int
    {
        if ($this->type === MigrationEntityType::Products && $this->productImport !== null) {
            return $this->productImport->imported_count;
        }

        return $this->migrated_count;
    }

    public function displayedFailed(): int
    {
        if ($this->type === MigrationEntityType::Products && $this->productImport !== null) {
            return $this->productImport->failed_count;
        }

        return $this->failed_count;
    }

    protected function casts(): array
    {
        return [
            'type' => MigrationEntityType::class,
            'status' => ImportStatus::class,
            'errors' => 'array',
            'total' => 'integer',
            'migrated_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }
}
