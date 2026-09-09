@php
    $daftar = $this->tim;
    $total  = $this->total;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>

        <x-slot name="heading">Permintaan per Tim Kerja</x-slot>

        <x-slot name="description">
            Jumlah permintaan per tim kerja
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex h-9 w-44 items-center justify-end">
                <x-filament::input.wrapper class="w-full">
                    <x-filament::input.select wire:model.live="periode">
                        @foreach ($this->pilihanPeriode as $nilai => $label)
                            <option value="{{ $nilai }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </x-slot>

        {{-- Tinggi disamakan dengan panel Kondisi Stok di sebelahnya. --}}
        <div class="fi-simpbi-panel-scroll -mx-2 h-72 overflow-y-auto px-2">

            @forelse ($daftar as $t)
                <a href="{{ $t['tautan'] }}"
                   @class([
                       '-mx-2 block rounded-lg px-2 py-2.5 transition hover:bg-gray-50 dark:hover:bg-white/5',
                       'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
                   ])>

                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">
                            {{ $t['nama'] }}
                        </span>
                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $t['jumlah'] }}</span>
                            permintaan
                        </span>
                    </div>

                    <div class="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        @if ($t['jumlah'] > 0)
                            <div class="h-full min-w-[0.375rem] rounded-full bg-primary-500"
                                 style="width: {{ $t['lebar'] }}%"></div>
                        @endif
                    </div>

                </a>
            @empty

                <div class="py-6 text-center">
                    <x-filament::icon
                        icon="heroicon-o-user-group"
                        class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                        Belum ada tim kerja aktif
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Perbandingan akan muncul setelah tim kerja tercatat pada sistem.
                    </p>
                </div>

            @endforelse

        </div>

        <x-slot name="footer">
            <div class="flex items-baseline justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Total {{ $daftar->count() }} tim kerja</span>
                <span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $total }}</span>
                    permintaan pada periode ini
                </span>
            </div>
        </x-slot>

    </x-filament::section>
</x-filament-widgets::widget>
