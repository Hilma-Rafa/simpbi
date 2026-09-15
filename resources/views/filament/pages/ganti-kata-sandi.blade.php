<x-filament-panels::page>

    <form wire:submit="simpan" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="simpan">Simpan dan Lanjutkan</span>
                <span wire:loading wire:target="simpan">Menyimpan…</span>
            </x-filament::button>
        </div>
    </form>

</x-filament-panels::page>
