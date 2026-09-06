@php
    $daftar = $this->kategori;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>

        {{-- Judul panel berperan sebagai tautan menuju daftar barang persediaan --}}
        <x-slot name="heading">
            <a href="{{ $this->tautanSemua }}"
               class="transition hover:text-primary-600 dark:hover:text-primary-400">
                Kondisi Stok per Kategori
            </a>
        </x-slot>

        <x-slot name="description">
            Panjang batang menunjukkan stok fisik, terbagi atas bagian tersedia dan terkunci
        </x-slot>

        @forelse ($daftar as $k)
            <a href="{{ $k['tautan'] }}"
               @class([
                   'block rounded-lg px-2 py-3 -mx-2 transition hover:bg-gray-50 dark:hover:bg-white/5',
                   'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
               ])>

                {{-- Nama kategori dan stok fisik --}}
                <div class="flex items-baseline justify-between gap-3">
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ $k['nama'] }}
                    </span>
                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                        Stok fisik {{ $k['fisik'] }}
                    </span>
                </div>

                {{-- Batang bertumpuk --}}
                <div class="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-full bg-primary-500"
                         style="width: {{ $k['lebarTersedia'] }}%"></div>

                    @if ($k['terkunci'] > 0)
                        <div
                            x-data="{ tampil: false }"
                            x-on:mouseenter="tampil = true"
                            x-on:mouseleave="tampil = false"
                            class="relative h-full bg-warning-400"
                            style="width: {{ $k['lebarTerkunci'] }}%">

                            {{-- Rincian barang yang sedang dikunci --}}
                            <div
                                x-show="tampil"
                                x-cloak
                                x-transition.opacity.duration.150ms
                                class="absolute bottom-5 left-1/2 z-30 w-60 -translate-x-1/2 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">

                                <p class="mb-2 text-xs font-semibold text-gray-900 dark:text-white">
                                    Barang yang sedang dikunci
                                </p>

                                <div class="space-y-1">
                                    @foreach ($k['daftarHold'] as $h)
                                        <div class="flex items-baseline justify-between gap-3 text-xs">
                                            <span class="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-400">
                                                {{ $h['nama'] }}
                                            </span>
                                            <span class="shrink-0 font-medium text-gray-900 dark:text-white">
                                                {{ $h['jumlah'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Angka tersedia dan terkunci --}}
                <div class="mt-1.5 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-primary-500"></span>
                        Tersedia
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $k['tersedia'] }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-warning-400"></span>
                        Terkunci
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $k['terkunci'] }}</span>
                    </span>
                </div>

            </a>
        @empty

            <div class="py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-archive-box"
                    class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    Belum ada data persediaan
                </p>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Data stok akan muncul setelah barang tercatat pada sistem.
                </p>
            </div>

        @endforelse

    </x-filament::section>
</x-filament-widgets::widget>