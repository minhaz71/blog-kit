<x-filament-panels::page>
    <form wire:submit="save" class="fi-form fi-page-form">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
