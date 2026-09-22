@php
    $daftar = $this->barang;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>

        {{-- Judul menjadi tautan ke Kartu Kendali, rekap seluruh barang. --}}
        <x-slot name="heading">
            <a href="{{ $this->tautanSemua }}"
               class="transition hover:text-primary-600 dark:hover:text-primary-400">
                Barang Paling Sering Diminta
            </a>
        </x-slot>

        <x-slot name="description">
            Banyaknya permintaan yang memuat barang tersebut
        </x-slot>


        {{-- Penyaring diletakkan sebagai satu baris di atas isi panel, bukan
             di sebelah judul. Pada panel selebar setengah kisi, judul dan
             keterangan kehilangan hampir seluruh ruangnya bila harus berbagi
             satu baris dengan kotak pilihan. --}}
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <x-filament::input.wrapper class="min-w-36 flex-1">
                <x-filament::input.select wire:model.live="periode">
                    @foreach ($this->pilihanPeriode as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>

        {{-- Dipendekkan dari tinggi panel daftar lain pada dasbor: baris di
             sini tiga baris tingginya — nama, batang, lalu jumlah diminta —
             sehingga tinggi yang sama menghasilkan kartu yang jauh lebih
             jangkung daripada tetangganya dan menyisakan ruang kosong. --}}
        <div class="fi-simpbi-panel-scroll -mx-2 h-48 overflow-y-auto px-2">

            @forelse ($daftar as $b)
                <a href="{{ $b['tautan'] }}"
                   @class([
                       '-mx-2 block rounded-lg px-2 py-2 transition hover:bg-gray-50 dark:hover:bg-white/5',
                       'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
                   ])>

                    <div class="flex items-baseline justify-between gap-3">
                        <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white"
                              title="{{ $b['nama'] }}">
                            {{ $b['nama'] }}
                        </span>
                        <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $b['frekuensi'] }}</span>
                            permintaan
                        </span>
                    </div>

                    <div class="mt-1.5 flex h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full min-w-[0.375rem] rounded-full bg-primary-500"
                             style="width: {{ $b['lebar'] }}%"></div>
                    </div>

                    {{-- Jumlah yang diminta dituliskan terpisah dari batangnya:
                         batang mengukur seberapa sering, angka ini mengukur
                         seberapa banyak, dan keduanya tidak boleh terbaca
                         sebagai ukuran yang sama. --}}
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Total diminta {{ $b['totalDiminta'] }}
                    </p>

                </a>
            @empty

                <div class="py-4 text-center">
                    <x-filament::icon
                        icon="heroicon-o-chart-bar"
                        class="mx-auto h-8 w-8 text-gray-400" />
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                        Belum ada permintaan pada periode ini
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Peringkat barang akan muncul setelah tim kerja mengajukan permintaan.
                    </p>
                </div>

            @endforelse

        </div>

        <x-slot name="footer">
            <div class="flex items-baseline justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $daftar->count() }} barang teratas</span>
                {{-- Angka ini menjumlahkan frekuensi barang pada peringkat,
                     bukan banyaknya dokumen permintaan. Satu permintaan berisi
                     tiga barang menyumbang tiga, sehingga penyebutannya tidak
                     boleh memakai kata "permintaan" begitu saja. --}}
                <span>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $this->totalFrekuensi }}</span>
                    kali diminta pada peringkat ini
                </span>
            </div>
        </x-slot>

    </x-filament::section>
</x-filament-widgets::widget>
