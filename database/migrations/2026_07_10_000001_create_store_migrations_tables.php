<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Shopper\Core\Helpers\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->getTableName('store_migrations'), static function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->text('credentials')->nullable();
            $table->string('status');
            $table->foreignId('user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $migrationsTable = $this->getTableName('store_migrations');

        Schema::create($this->getTableName('store_migration_entities'), static function (Blueprint $table) use ($migrationsTable): void {
            $table->id();
            $table->foreignId('store_migration_id')
                ->constrained($migrationsTable)
                ->cascadeOnDelete();
            $table->foreignId('product_import_id')->nullable();
            $table->string('type');
            $table->string('status');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('migrated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->jsonb('errors')->nullable();
            $table->timestamps();

            $table->index(['store_migration_id', 'type']);
            $table->index('product_import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->getTableName('store_migration_entities'));
        Schema::dropIfExists($this->getTableName('store_migrations'));
    }
};
