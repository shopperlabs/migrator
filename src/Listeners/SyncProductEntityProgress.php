<?php

declare(strict_types=1);

namespace Shopper\Migrator\Listeners;

use Shopper\Core\Events\Products\ProductImportCompleted;
use Shopper\Migrator\Models\StoreMigrationEntity;

final class SyncProductEntityProgress
{
    public function handle(ProductImportCompleted $event): void
    {
        /** @var ?StoreMigrationEntity $entity */
        $entity = StoreMigrationEntity::query()
            ->where('product_import_id', $event->import->id)
            ->first();

        if ($entity === null) {
            return;
        }

        $entity->update([
            'status' => $event->import->status,
            'total' => $event->import->total_products,
            'migrated_count' => $event->import->imported_count,
            'failed_count' => $event->import->failed_count,
            'errors' => $event->import->errors,
        ]);

        $entity->migration->refreshStatus();
    }
}
