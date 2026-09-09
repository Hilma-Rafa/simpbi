@php
    $tersedia = $this->jenisTersedia;
    $aktif    = $this->jenisAktif();
@endphp

<x-filament-panels::page>

    {{--
        Pemilih jenis riwayat. Ditampilkan sebagai satu deret tombol agar
        seluruh jenis terlihat sekaligus dan pengguna tidak perlu berpindah
        menu. Jenis yang tampil menyesuaikan kewenangan peran.
    --}}
    @if (count($tersedia) > 1)
        <x-filament::section compact>
            <div class="flex flex-wrap gap-2">
                @foreach ($tersedia as $kunci => $jenis)
                    @php $terpilih = $kunci === $aktif; @endphp

                    <button
                        type="button"
                        wire:click="pilihJenis('{{ $kunci }}')"
                        wire:loading.attr="disabled"
                        @class([
                            'inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition',
                            'border-primary-600 bg-primary-600 text-white shadow-sm' => $terpilih,
                            'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-white/5' => ! $terpilih,
                        ])
                        @if ($terpilih) aria-current="page" @endif
                    >
                        <x-filament::icon :icon="$jenis['ikon']" class="h-4 w-4" />
                        {{ $jenis['ringkas'] }}
                    </button>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Judul jenis yang sedang dilihat, agar konteks tabel tetap jelas --}}
    <div class="-mb-2">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ $tersedia[$aktif]['label'] ?? 'Riwayat' }}
        </h2>
        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
            @switch($aktif)
                @case('mutasi_stok')
                    Seluruh pergerakan stok barang persediaan, masuk maupun keluar
                    @break
                @case('mutasi_aset')
                    Serah terima mutasi aset tetap yang telah disahkan
                    @break
                @default
                    Permintaan barang yang telah mencapai status akhir
            @endswitch
            &middot; tombol Export mengikuti penyaring yang sedang aktif
        </p>
    </div>

    {{ $this->table }}

</x-filament-panels::page>
