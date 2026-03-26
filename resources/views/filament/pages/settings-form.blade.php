<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end gap-3">
            @if(method_exists($this, 'testConnection'))
            <x-filament::button type="button" wire:click="testConnection" color="gray">
                {{ __('settings.test_connection') }}
            </x-filament::button>
            @endif
            <x-filament::button type="submit">
                {{ __('settings.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
