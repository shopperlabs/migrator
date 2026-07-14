<?php

declare(strict_types=1);

namespace Shopper\Migrator\Livewire\SlideOvers;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\View;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Laravelcm\LivewireSlideOvers\SlideOverComponent;
use Shopper\Components\SlideOverWizard;
use Shopper\Components\Wizard\StepColumn;
use Shopper\Migrator\Contracts\MigrationConnection;
use Shopper\Migrator\Contracts\MigrationSource;
use Shopper\Migrator\Enum\MigrationEntityType;
use Shopper\Migrator\Exceptions\MigrationException;
use Shopper\Migrator\MigrationSourceManager;
use Shopper\Migrator\StartStoreMigration;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * Base import wizard shared by every migration source. A concrete source only
 * declares its {@see self::sourceCode()}; everything else is driven by the
 * {@see MigrationSource} contract resolved from the manager.
 *
 * @property-read Schema $form
 */
abstract class ImportStore extends SlideOverComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, int> */
    public array $entityCounts = [];

    /** @var array{ok: bool, message: string}|null */
    public ?array $connectionStatus = null;

    /**
     * Code of the migration source this wizard drives (e.g. 'woocommerce').
     */
    abstract protected function sourceCode(): string;

    public static function panelMaxWidth(): string
    {
        return '2xl';
    }

    public function mount(): void
    {
        $this->authorize('migrator.create');

        $this->form->fill([
            'entities' => array_map(
                fn (MigrationEntityType $type): string => $type->value,
                $this->source()->supportedEntities(),
            ),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SlideOverWizard::make([
                    StepColumn::make(__('shopper-migrator::messages.wizard.steps.connection'))
                        ->icon('phosphor-plugs-connected-duotone')
                        ->schema([
                            ...array_values($this->source()->credentialFields()),
                            Actions::make([
                                Action::make('testConnection')
                                    ->label(__('shopper-migrator::messages.wizard.test_connection'))
                                    ->color('gray')
                                    ->action(function (): void {
                                        $this->testConnection();
                                    }),
                            ]),
                            View::make('shopper-migrator::livewire.partials.connection-status'),
                        ])
                        ->afterValidation(function (): void {
                            $this->loadEntityCounts();
                        }),
                    StepColumn::make(__('shopper-migrator::messages.wizard.steps.selection'))
                        ->icon('phosphor-list-checks-duotone')
                        ->description(__('shopper-migrator::messages.wizard.selection_description'))
                        ->schema([
                            CheckboxList::make('entities')
                                ->hiddenLabel()
                                ->required()
                                ->bulkToggleable()
                                ->allowHtml()
                                ->options(fn (): array => $this->entityOptions())
                                ->columns(3),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" wire:loading.attr="disabled">
                            <x-shopper::loader wire:loading wire:target="store" class="text-white" />
                            {{ __('shopper-migrator::messages.wizard.submit') }}
                        </x-filament::button>
                     BLADE))),
            ])
            ->statePath('data');
    }

    public function store(): void
    {
        $this->validate();

        if ($this->rateLimited('store', 5)) {
            return;
        }

        try {
            $this->connect();
        } catch (MigrationException $e) {
            $this->connectionStatus = ['ok' => false, 'message' => $e->getMessage()];

            Notification::make()
                ->title(__('shopper-migrator::messages.wizard.connection_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $migration = resolve(StartStoreMigration::class)->execute(
            source: $this->sourceCode(),
            credentials: $this->credentials(),
            types: (array) ($this->data['entities'] ?? []),
            userId: auth()->id(),
        );

        $this->dispatch('products.import.started');

        $this->dispatch('openPanel', 'shopper-slide-overs.migration-progress', ['migrationId' => $migration->id]);
    }

    public function testConnection(): void
    {
        $this->validate($this->credentialRules());

        if ($this->rateLimited('test-connection', 10)) {
            return;
        }

        try {
            $connection = $this->connect();
        } catch (MigrationException $e) {
            $this->connectionStatus = ['ok' => false, 'message' => $e->getMessage()];

            return;
        }

        $this->connectionStatus = [
            'ok' => true,
            'message' => __('shopper-migrator::messages.wizard.products_found', [
                'count' => $connection->total(MigrationEntityType::Products),
            ]),
        ];
    }

    public function panelTitle(): string
    {
        return __('shopper-migrator::messages.wizard.title', ['source' => $this->source()->name()]);
    }

    public function render(): ViewContract
    {
        return view('shopper-migrator::livewire.slide-overs.import-store');
    }

    protected function source(): MigrationSource
    {
        return resolve(MigrationSourceManager::class)->source($this->sourceCode());
    }

    /**
     * Throttle the outbound-HTTP actions (SSRF-capable, and store() also enqueues job chains)
     * so a single admin cannot spam them.
     */
    protected function rateLimited(string $action, int $perMinute): bool
    {
        $key = "migrator:{$action}:{$this->sourceCode()}:".(auth()->id() ?? 'guest');

        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            $this->connectionStatus = [
                'ok' => false,
                'message' => __('shopper-migrator::messages.errors.too_many_attempts'),
            ];

            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }

    protected function loadEntityCounts(): void
    {
        try {
            $connection = $this->connect();
        } catch (MigrationException $e) {
            $this->connectionStatus = ['ok' => false, 'message' => $e->getMessage()];

            return;
        }

        foreach ($this->source()->supportedEntities() as $type) {
            $this->entityCounts[$type->value] = $connection->total($type);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function entityOptions(): array
    {
        $options = [];

        foreach ($this->source()->supportedEntities() as $type) {
            $count = $this->entityCounts[$type->value] ?? null;
            $icon = svg($type->getIcon(), 'size-5 shrink-0 text-sh-fg-muted')->toHtml();
            $label = e($type->getLabel()).($count !== null ? ' <span class="text-sh-fg-muted">('.$count.')</span>' : '');

            $options[$type->value] = "<span class=\"flex items-center gap-2\">{$icon}<span>{$label}</span></span>";
        }

        return $options;
    }

    protected function connect(): MigrationConnection
    {
        $connection = $this->source()->connect($this->credentials());

        $connection->test();

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        $credentials = [];

        foreach (array_keys($this->source()->credentialFields()) as $key) {
            $credentials[$key] = (string) ($this->data[$key] ?? '');
        }

        return $credentials;
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function credentialRules(): array
    {
        $rules = [];

        foreach (array_keys($this->source()->credentialFields()) as $key) {
            $rules["data.{$key}"] = ['required'];
        }

        return $rules;
    }
}
