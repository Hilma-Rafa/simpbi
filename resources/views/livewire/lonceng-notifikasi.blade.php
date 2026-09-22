@php
    $daftar = $this->daftar;
    $belum  = $this->jumlahBelumDibaca;
@endphp

{{--
    Lonceng notifikasi pada bilah atas. Pemeriksaan berkala dijalankan Livewire
    sehingga notifikasi baru muncul tanpa memuat ulang halaman, sekaligus
    memunculkan toast di sudut layar melalui Filament.
--}}
<div
    wire:poll.{{ $this->selang() }}="periksa"
    x-data="{ terbuka: false }"
    x-on:keydown.escape.window="terbuka = false"
    class="relative"
>
    <button
        type="button"
        x-on:click="terbuka = ! terbuka"
        class="fi-icon-btn relative flex h-10 w-10 items-center justify-center rounded-lg text-gray-500 outline-none transition hover:bg-gray-100 hover:text-gray-700 focus-visible:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
        :aria-expanded="terbuka.toString()"
        aria-haspopup="true"
        aria-label="Notifikasi{{ $belum > 0 ? ' — ' . $belum . ' belum dibaca' : '' }}"
    >
        <x-filament::icon icon="heroicon-o-bell" class="h-5 w-5" />

        @if ($belum > 0)
            <span
                class="absolute -right-0.5 -top-0.5 inline-flex min-w-[1.15rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[0.65rem] font-semibold leading-[1.15rem] text-white ring-2 ring-white dark:ring-gray-900"
            >
                {{ $belum > 9 ? '9+' : $belum }}
            </span>
        @endif
    </button>

    {{-- Panel daftar notifikasi --}}
    <div
        x-show="terbuka"
        x-cloak
        x-on:click.outside="terbuka = false"
        x-transition.opacity.duration.150ms
        class="absolute right-0 top-11 z-40 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900 sm:w-96"
    >
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Notifikasi</p>

            @if ($belum > 0)
                <button
                    type="button"
                    wire:click="tandaiSemuaDibaca"
                    class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    Tandai semua dibaca
                </button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($daftar as $n)
                @php
                    $tampilan = $n->tampilan();
                    $tautan   = $this->tautan($n);
                    $dibaca   = filled($n->dibaca_at);
                @endphp

                {{--
                    Baris dibungkus supaya tombol singkirkan dapat berdiri di
                    luar tautannya: tombol yang bersarang di dalam <a> bukan
                    markah yang sah, dan kliknya akan ikut membuka tautan itu.
                    Garis pemisah dan latar "belum dibaca" ikut pindah ke
                    pembungkus agar tampilan barisnya tidak berubah sama sekali.

                    wire:key wajib ada di sini. Tanpanya Livewire mencocokkan
                    baris menurut urutan, sehingga menyingkirkan baris di tengah
                    membuat isi baris-baris di bawahnya bergeser naik sendiri
                    tanpa simpulnya ikut dibuang — yang terlihat sebagai baris
                    terakhir yang kembar.
                --}}
                <div
                    wire:key="notifikasi-{{ $n->id }}"
                    @class([
                        'relative border-b border-gray-100 transition last:border-b-0 dark:border-gray-800',
                        'hover:bg-gray-50 dark:hover:bg-white/5' => true,
                        'bg-primary-50/60 dark:bg-primary-500/5' => ! $dibaca,
                    ])
                >
                    <a
                        @if ($tautan) href="{{ $tautan }}" @endif
                        wire:click="tandaiDibaca({{ $n->id }})"
                        @class([
                            // Lapang di kanan disediakan untuk tombol singkirkan,
                            // supaya teks terpanjang pun tidak berjalan ke kolongnya.
                            'flex gap-3 py-3 pl-4 pr-9',
                            'cursor-pointer' => (bool) $tautan,
                        ])
                    >
                        <x-filament::icon
                            :icon="$tampilan['ikon']"
                            @class([
                                'mt-0.5 h-5 w-5 shrink-0',
                                'text-warning-500' => $tampilan['warna'] === 'warning',
                                'text-info-500' => $tampilan['warna'] === 'info',
                                'text-primary-500' => $tampilan['warna'] === 'primary',
                            ])
                        />

                        <div class="min-w-0 flex-1">
                            <p @class([
                                'text-sm text-gray-900 dark:text-white',
                                'font-semibold' => ! $dibaca,
                                'font-medium' => $dibaca,
                            ])>
                                {{ $n->judul }}
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                {{ $n->pesan }}
                            </p>
                            <p class="mt-1 text-xs text-gray-400">
                                {{ $n->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        @unless ($dibaca)
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                        @endunless
                    </a>

                    {{--
                        Selalu tampak, tetapi paling redup di antara isi baris —
                        delapan silang yang berwarna penuh akan meramaikan panel
                        dan bersaing dengan judul notifikasinya. Tetap dirender
                        (bukan hanya muncul saat disorot) sebab pada layar sentuh
                        tidak ada keadaan disorot sama sekali.
                    --}}
                    <button
                        type="button"
                        wire:click="sembunyikan({{ $n->id }})"
                        class="absolute right-1.5 top-2.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-gray-300 outline-none transition hover:bg-gray-200/70 hover:text-gray-600 focus-visible:bg-gray-200/70 focus-visible:text-gray-600 dark:text-gray-600 dark:hover:bg-white/10 dark:hover:text-gray-300 dark:focus-visible:bg-white/10 dark:focus-visible:text-gray-300"
                        aria-label="Singkirkan notifikasi {{ $n->judul }}"
                        title="Singkirkan dari daftar"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                    </button>
                </div>
            @empty
                <div class="px-4 py-10 text-center">
                    <x-filament::icon icon="heroicon-o-bell-slash" class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Belum ada notifikasi</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Pemberitahuan tentang permintaan dan persediaan akan muncul di sini.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</div>
