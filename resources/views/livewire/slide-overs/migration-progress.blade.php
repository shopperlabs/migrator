<x-shopper::slideover-card
    x-data
    x-on:keydown.escape.window="$wire.closeAll()"
>
    <header class="p-4">
        <div class="flex items-start justify-between">
            <h2 class="text-lg font-medium text-sh-fg">
                {{ __('shopper-migrator::messages.progress.title') }}
            </h2>
            <button
                type="button"
                class="rounded-md text-zinc-400 outline-none hover:text-zinc-500 dark:text-zinc-500 dark:hover:text-zinc-300"
                wire:click="closeAll"
            >
                <span class="sr-only">{{ __('Close panel') }}</span>
                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M17 7L7 17M7 7L17 17" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </header>

    <div
        class="h-0 flex-1 overflow-y-auto p-4"
        @if ($migration !== null && ! $migration->status->isFinished()) wire:poll.2s @endif
    >
        @if ($migration === null)
            <p class="text-sm text-sh-fg-muted">
                {{ __('shopper-migrator::messages.progress.empty') }}
            </p>
        @else
            <div class="space-y-4">
                @if ($migration->status->isFinished())
                    <div class="flex items-center gap-2 rounded-lg bg-success-50 p-3 text-sm text-success-700 dark:bg-success-400/10 dark:text-success-400">
                        <x-untitledui-check-circle class="size-4 shrink-0" aria-hidden="true" />
                        {{ __('shopper-migrator::messages.progress.finished') }}
                    </div>
                @else
                    <div class="flex items-start gap-2 rounded-lg bg-sh-muted/50 p-3 text-sm text-sh-fg-muted">
                        <x-shopper::loader class="size-4 shrink-0 text-primary-500" aria-hidden="true" />
                        {{ __('shopper-migrator::messages.progress.description') }}
                    </div>
                @endif

                <ul class="divide-y divide-sh-border overflow-hidden rounded-xl border border-sh-border">
                    @foreach ($migration->entities as $entity)
                        <li class="flex items-start gap-4 p-4" wire:key="entity-{{ $entity->id }}">
                            <x-dynamic-component
                                :component="$entity->type->getIcon()"
                                class="size-6 shrink-0 text-sh-fg-muted"
                                aria-hidden="true"
                            />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="text-sm font-medium text-sh-fg">
                                        {{ $entity->type->getLabel() }}
                                    </p>
                                    <p class="text-sm tabular-nums text-sh-fg-muted">
                                        {{ __('shopper-migrator::messages.progress.count', [
                                            'migrated' => $entity->displayedMigrated(),
                                            'total' => $entity->displayedTotal(),
                                        ]) }}
                                    </p>
                                </div>

                                <div class="mt-2 flex items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-sh-muted">
                                        <div
                                            class="h-full rounded-full bg-primary-500 transition-all duration-500"
                                            style="width: {{ $entity->progress() }}%"
                                        ></div>
                                    </div>
                                    <span class="w-10 text-right text-xs tabular-nums text-sh-fg-muted">
                                        {{ $entity->progress() }}%
                                    </span>
                                </div>

                                @if ($entity->displayedFailed() > 0)
                                    <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">
                                        {{ trans_choice('shopper-migrator::messages.progress.failed', $entity->displayedFailed(), ['count' => $entity->displayedFailed()]) }}
                                    </p>
                                @endif
                            </div>

                            <x-filament::badge
                                size="sm"
                                :color="$entity->status->getColor()"
                            >
                                {{ $entity->status->getLabel() }}
                            </x-filament::badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-shopper::slideover-card>
