<div class="divide-y divide-gray-100 dark:divide-gray-800">
    @foreach ($keranjang as $id => $item)
        <div class="flex items-center justify-between gap-4 py-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-base font-medium text-gray-900 dark:text-white">
                    {{ $item['nama'] }}
                </p>
                <p class="text-sm text-gray-500">
                    {{ $item['jumlah'] }} {{ $item['satuan'] }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                {{ ($this->ubahJumlahAction)(['id' => $id]) }}

                <x-filament::button
                    size="sm"
                    color="danger"
                    outlined
                    wire:click="hapusDariKeranjang({{ $id }})">
                    Hapus
                </x-filament::button>
            </div>
        </div>
    @endforeach
</div>