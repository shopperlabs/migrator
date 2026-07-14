@php
    $status = $schemaComponent->getLivewire()->connectionStatus;
@endphp

@if ($status !== null)
    <div
        wire:key="connection-status-{{ md5(json_encode($status)) }}"
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 6000)"
        x-show="show"
        x-transition.opacity.duration.300ms
        @class([
            'flex items-center gap-2 rounded-lg p-3 text-sm',
            'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $status['ok'],
            'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => ! $status['ok'],
        ])
    >
        @if ($status['ok'])
            <x-untitledui-check-circle class="size-4 shrink-0" aria-hidden="true" />
            <span class="font-medium">{{ __('shopper-migrator::messages.woocommerce.connection_ok') }}</span>
        @else
            <x-untitledui-alert-circle class="size-4 shrink-0" aria-hidden="true" />
            <span class="font-medium">{{ __('shopper-migrator::messages.woocommerce.connection_failed') }}</span>
        @endif
        <span>{{ $status['message'] }}</span>
    </div>
@endif
