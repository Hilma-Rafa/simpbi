@php
    $pemeriksaan = $this->pemeriksaan;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kelengkapan Data Induk</x-slot>

        <x-slot name="description">
            Data induk yang masih perlu dilengkapi
        </x-slot>

        @forelse ($pemeriksaan as $item)
            <div @class([
                'flex items-center justify-between gap-4 py-3.5',
                'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
            ])>
                <div class="flex items-center gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $item['jumlah'] }}</span>
                        {{ $item['label'] }}
                    </p>
                </div>

                @if ($item['tautan'])
                    <a href="{{ $item['tautan'] }}"
                       class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        Lengkapi &rarr;
                    </a>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 text-success-500" />
                <p class="text-sm font-medium text-gray-900 dark:text-white">Data induk sudah lengkap</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Tidak ada data induk yang perlu dilengkapi saat ini.
                </p>
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
