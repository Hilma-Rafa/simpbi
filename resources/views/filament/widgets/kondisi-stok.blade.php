@php
    $daftar  = $this->kategori;
    $ringkas = $this->ringkas;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>

        <x-slot name="heading">Kondisi Stok per Kategori</x-slot>

        <x-slot name="description">
            Tersedia dan terkunci per kategori
        </x-slot>

        {{--
            Tinggi kendali kepala disamakan dengan panel di sebelahnya agar
            kedua panel berakhir pada garis yang sama.
        --}}
        <x-slot name="afterHeader">
            <div class="flex h-9 w-44 items-center justify-end">
                <a href="{{ $this->tautanSemua }}"
                   class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Lihat semua &rarr;
                </a>
            </div>
        </x-slot>

        {{--
            Tinggi panel dikunci dan isinya digulir agar seluruh kategori dapat
            ditelusuri tanpa membuat dashboard memanjang (Instruksi §45).
            Tinggi yang sama dipakai panel di sebelahnya.
        --}}
        <div class="fi-simpbi-panel-scroll -mx-2 h-72 overflow-y-auto px-2">

            @forelse ($daftar as $k)
                <a href="{{ $k['tautan'] }}"
                   @class([
                       '-mx-2 block rounded-lg px-2 py-2.5 transition hover:bg-gray-50 dark:hover:bg-white/5',
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

                    {{-- Batang bertumpuk: lebar penuh selalu mewakili stok fisik kategori --}}
                    <div class="mt-2 flex h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        @if ($k['tersedia'] > 0)
                            <div class="h-full min-w-[0.375rem] bg-primary-500"
                                 style="width: {{ $k['lebarTersedia'] }}%"></div>
                        @endif

                        @if ($k['terkunci'] > 0)
                            <div
                                x-data="{
                                    tampil: false,
                                    gaya: '',
                                    buka(batang) {
                                        const kotak = batang.getBoundingClientRect();
                                        // Ditahan di dalam layar agar rincian tidak terpotong tepi jendela
                                        const tengah = Math.min(
                                            Math.max(kotak.left + kotak.width / 2, 140),
                                            window.innerWidth - 140
                                        );
                                        // Muncul di atas batang, kecuali bila ruang di atas tidak cukup
                                        const tegak = kotak.top > 180
                                            ? `bottom: ${window.innerHeight - kotak.top + 8}px`
                                            : `top: ${kotak.bottom + 8}px`;
                                        this.gaya = `left: ${tengah}px; ${tegak}`;
                                        this.tampil = true;
                                    },
                                }"
                                x-on:mouseenter="buka($el)"
                                x-on:mouseleave="tampil = false"
                                class="h-full min-w-[0.375rem] bg-gray-400 dark:bg-gray-500"
                                style="width: {{ $k['lebarTerkunci'] }}%">

                                {{--
                                    Rincian dipindahkan ke body agar tidak terpotong
                                    oleh area gulir panel.
                                --}}
                                <template x-teleport="body">
                                    <div
                                        x-show="tampil"
                                        x-cloak
                                        x-transition.opacity.duration.150ms
                                        :style="gaya"
                                        class="fixed z-50 w-64 -translate-x-1/2 rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-900">

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
                                </template>
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
                            <span class="h-2 w-2 rounded-full bg-gray-400 dark:bg-gray-500"></span>
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
                        Belum ada kategori persediaan
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Data stok akan muncul setelah kategori dan barang tercatat pada sistem.
                    </p>
                </div>

            @endforelse

        </div>

        <x-slot name="footer">
            <div class="flex items-baseline justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Total {{ $ringkas['kategori'] }} kategori persediaan</span>
                <span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $ringkas['barangTerkunci'] }}</span>
                    barang sedang terkunci
                </span>
            </div>
        </x-slot>

    </x-filament::section>
</x-filament-widgets::widget>
