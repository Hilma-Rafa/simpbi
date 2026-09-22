<x-filament-panels::page>

    <div
        x-data="{
            get dirty() {
                return window.jsMd5(JSON.stringify($wire.data).replace(/\\/g, '')) !== $wire.savedDataHash
            },
        }"
    >
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Panel samping: turun secara natural di atas form pada layar kecil.
                 Pada breakpoint dua kolom (lg+) dibuat sticky, mengikuti tinggi
                 topbar bawaan Filament (--topbar-height, dipakai apa adanya —
                 bukan angka baru) plus sedikit jarak. Bila isinya lebih tinggi
                 daripada sisa viewport, wadah ini sendiri yang gulir, bukan
                 seluruh halaman, sehingga tetap terlihat saat form panjang
                 (kartu Batas Waktu Alur + Notifikasi WhatsApp) digulir. --}}
            <div
                class="order-1 space-y-6 lg:order-2 lg:col-span-1 lg:sticky lg:self-start lg:overflow-y-auto"
                style="top: calc(var(--topbar-height, 4rem) + 1.5rem); max-height: calc(100dvh - var(--topbar-height, 4rem) - 3rem);"
            >

                <x-filament::section>
                    <x-slot name="heading">Ringkasan Akun</x-slot>

                    <div class="flex items-center gap-3">
                        <span class="simpbi-avatar-inisial simpbi-avatar-inisial--besar" aria-hidden="true">{{ $this::inisial(auth()->user()->name) }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ auth()->user()->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->labelPeran() }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                            <dd class="truncate text-gray-950 dark:text-white">{{ auth()->user()->email }}</dd>
                        </div>

                        @if (in_array(auth()->user()->role, ['ketua_tim', 'tim']) && auth()->user()->tim)
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-500 dark:text-gray-400">Tim Kerja</dt>
                                <dd class="truncate text-gray-950 dark:text-white">{{ auth()->user()->tim->nama_tim }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-filament::section>

                @if (static::bolehMengaturSistem())
                    <x-filament::section>
                        <x-slot name="heading">Status Sistem</x-slot>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-600 dark:text-gray-300">Notifikasi WhatsApp</dt>
                                <dd>
                                    <x-filament::badge :color="($this->data['wa_aktif'] ?? false) ? 'success' : 'gray'">
                                        {{ ($this->data['wa_aktif'] ?? false) ? 'Aktif' : 'Nonaktif' }}
                                    </x-filament::badge>
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-600 dark:text-gray-300">Mode Peragaan</dt>
                                <dd>
                                    <x-filament::badge :color="filled($this->data['wa_alihkan_ke'] ?? null) ? 'warning' : 'gray'">
                                        {{ filled($this->data['wa_alihkan_ke'] ?? null) ? 'Aktif' : 'Nonaktif' }}
                                    </x-filament::badge>
                                </dd>
                            </div>
                        </dl>
                    </x-filament::section>

                    <x-filament::section>
                        <x-slot name="heading">Ringkasan Alur</x-slot>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-600 dark:text-gray-300">Total maksimal</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">
                                    {{ (int) $this->totalJamAlur() }} jam kerja
                                </dd>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <dt class="text-gray-600 dark:text-gray-300">Setara</dt>
                                <dd class="font-medium text-gray-950 dark:text-white">
                                    {{ $this->totalHariAlur() }} hari kerja
                                </dd>
                            </div>
                        </dl>
                    </x-filament::section>
                @endif
            </div>

            {{-- Kolom utama --}}
            <div class="order-2 lg:order-1 lg:col-span-2">
                {{ $this->form }}
            </div>
        </div>

        {{-- Bar "Ada perubahan yang belum disimpan", pola sticky-bawah yang sama
             dipakai keranjang Katalog Barang: fixed di bawah, dengan pengimbang
             tinggi agar isi terakhir halaman tidak tertutup. --}}
        <div
            x-show="dirty"
            x-transition
            x-cloak
            class="pointer-events-none fixed inset-x-0 bottom-0 z-30 px-4 pb-4 sm:px-6 lg:pl-72"
        >
            <div class="pointer-events-auto mx-auto max-w-5xl rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col items-start justify-between gap-3 px-4 py-3 sm:flex-row sm:items-center">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        Ada perubahan yang belum disimpan
                    </p>

                    <div class="flex items-center gap-2">
                        <x-filament::button color="gray" outlined wire:click="batal">
                            Batal
                        </x-filament::button>

                        {{ $this->simpanAction }}
                    </div>
                </div>
            </div>
        </div>

        <div x-show="dirty" x-cloak class="h-20"></div>
    </div>

</x-filament-panels::page>
