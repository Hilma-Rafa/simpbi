@php
    $terendah = $this->stokTerendah;
    $mendekati = $this->mendekatiMinimum;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Barang Perlu Perhatian</x-slot>

        <x-slot name="description">
            Stok tersedia terendah dan barang yang mendekati stok minimum
        </x-slot>

        <x-slot name="afterHeader">
            <a href="{{ $this->tautanSemua }}"
               class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                Lihat semua &rarr;
            </a>
        </x-slot>

        {{-- Stok tersedia terendah --}}
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Stok Tersedia Terendah</p>
        <div class="mt-2">
            @forelse ($terendah as $b)
                <div @class([
                    'flex items-center justify-between py-2.5 text-sm',
                    'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
                ])>
                    <span class="text-gray-700 dark:text-gray-300">{{ $b['nama'] }}</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ $b['tersedia'] }}</span>
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500 dark:text-gray-400">Belum ada data barang.</p>
            @endforelse
        </div>

        {{-- Mendekati stok minimum --}}
        <p class="mt-6 text-xs font-semibold uppercase tracking-wide text-gray-400">Mendekati Stok Minimum</p>
        <div class="mt-2">
            @forelse ($mendekati as $b)
                <div @class([
                    'flex items-center justify-between py-2.5 text-sm',
                    'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
                ])>
                    <span class="text-gray-700 dark:text-gray-300">{{ $b['nama'] }}</span>
                    <span class="inline-flex items-center gap-1.5 font-medium text-warning-600 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                        {{ $b['tersedia'] }} <span class="text-gray-400">/ min {{ $b['minimum'] }}</span>
                    </span>
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500 dark:text-gray-400">
                    Tidak ada barang yang mendekati stok minimum.
                </p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
