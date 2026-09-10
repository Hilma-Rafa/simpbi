@php
    $pekerjaan = $this->pekerjaan;
    $total     = $this->total;
@endphp

<x-filament-widgets::widget class="w-full">
    <x-filament::section class="w-full">

        {{-- HEADER PANEL --}}
        <x-slot name="heading">
            <a
                href="{{ $this->tautanSemua }}"
                class="transition hover:text-primary-600 dark:hover:text-primary-400"
            >
                Perlu Tindakan Anda
            </a>
        </x-slot>

        {{-- DAFTAR PEKERJAAN --}}
        @forelse ($pekerjaan as $p)
            @php
                $warnaWaktu = match ($p['urgensi']) {
                    'lewat'    => 'text-danger-600 dark:text-danger-400',
                    'mendesak' => 'text-danger-600 dark:text-danger-400',
                    'wajar'    => 'text-warning-600 dark:text-warning-400',
                    default    => 'text-gray-500 dark:text-gray-400',
                };
            @endphp

            <div
                @class([
                    'flex w-full flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between',
                    'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
                ])
            >

                {{-- INFORMASI PERMINTAAN --}}
                <div class="min-w-0 flex-1">

                    {{-- Kode + Tim + Jumlah Item --}}
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $p['kode'] }}
                        </span>

                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $p['tim'] }}
                        </span>

                        <span class="text-gray-300 dark:text-gray-600">
                            ·
                        </span>

                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $p['item'] }} item
                        </span>
                    </div>

                    {{-- Tahap + Waktu --}}
                    <div class="mt-2 flex flex-wrap items-center gap-x-10 gap-y-1.5">

                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <x-filament::icon
                                :icon="$p['ikonTahap']"
                                class="h-4 w-4 text-gray-400 dark:text-gray-500"
                            />

                            <span>
                                {{ $p['tahap'] }}
                            </span>
                        </span>

                        <span
                            @class([
                                'inline-flex items-center gap-1.5 text-sm font-medium',
                                $warnaWaktu,
                            ])
                        >
                            <x-filament::icon
                                icon="heroicon-m-clock"
                                class="h-4 w-4"
                            />

                            <span>
                                {{ $p['sisa'] }}
                            </span>
                        </span>
                    </div>
                </div>

                {{-- TOMBOL TINDAKAN --}}
                <div class="shrink-0 sm:pl-4">
                    <x-filament::button
                        tag="a"
                        :href="$p['tautan']"
                        size="sm"
                        icon="heroicon-m-arrow-right"
                        icon-position="after"
                    >
                        {{ $p['aksi'] }}
                    </x-filament::button>
                </div>

            </div>

        @empty

            {{-- EMPTY STATE --}}
            <div class="py-8 text-center">

                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="mx-auto h-9 w-9 text-success-500"
                />

                <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                    Tidak ada tindakan yang perlu dilakukan
                </p>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Seluruh pekerjaan Anda saat ini sudah tertangani.
                </p>

            </div>

        @endforelse

        {{-- KETERANGAN JUMLAH --}}
        @if ($total > $pekerjaan->count())
            <div class="border-t border-gray-100 pt-3 dark:border-gray-800">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan {{ $pekerjaan->count() }}
                    dari {{ $total }}
                    pekerjaan.
                </p>
            </div>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>