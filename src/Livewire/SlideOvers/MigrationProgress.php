<?php

declare(strict_types=1);

namespace Shopper\Migrator\Livewire\SlideOvers;

use Illuminate\Contracts\View\View;
use Laravelcm\LivewireSlideOvers\SlideOverComponent;
use Shopper\Migrator\Models\StoreMigration;
use Shopper\Traits\HandlesAuthorizationExceptions;

class MigrationProgress extends SlideOverComponent
{
    use HandlesAuthorizationExceptions;

    public ?StoreMigration $migration = null;

    public bool $wasRunning = false;

    public static function panelMaxWidth(): string
    {
        return '2xl';
    }

    public static function closePanelOnEscape(): bool
    {
        return false;
    }

    public function mount(?int $migrationId = null): void
    {
        $this->authorize('migrator.browse');

        $query = StoreMigration::query()->where('user_id', auth()->id());

        $this->migration = $migrationId !== null
            ? $query->find($migrationId)
            : $query->latest('id')->first();
    }

    public function closeAll(): void
    {
        $this->forceClose()->closePanel();
    }

    public function render(): View
    {
        $this->migration?->refresh()->load(['entities.productImport']);

        $isRunning = $this->migration !== null && ! $this->migration->status->isFinished();

        if ($this->wasRunning && ! $isRunning) {
            $this->dispatch('products.import.finished');
        }

        $this->wasRunning = $isRunning;

        return view('shopper-migrator::livewire.slide-overs.migration-progress');
    }
}
