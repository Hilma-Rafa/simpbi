@php
    $pekerjaan = $this->pekerjaan;
    $total     = $this->total;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>

        {{-- ---------- KEPALA PANEL ---------- --}}
        <x-slot name="heading">Perlu Tindakan Anda</x-slot>

        <x-slot name="description">
            Pekerjaan yang membutuhkan tindakan Anda saat ini
        </x-slot>

        @if ($total > 0)
            <x-slot name="headerEnd">
                <a href="{{ $this->tautanSemua }}"
                   class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                    Lihat semua &rarr;
                </a>
            </x-slot>
        @endif

        {{-- ---------- DAFTAR PEKERJAAN ---------- --}}
        @forelse ($pekerjaan as $p)
            @php
                $warnaWaktu = match ($p['urgensi']) {
                    'lewat'    => 'text-danger-600 dark:text-danger-400',
                    'mendesak' => 'text-danger-600 dark:text-danger-400',
                    'wajar'    => 'text-warning-600 dark:text-warning-400',
                    default    => 'text-gray-500 dark:text-gray-400',
                };
            @endphp

            <div @class([
                'flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between',
                'border-t border-gray-100 dark:border-gray-800' => ! $loop->first,
            ])>

                {{-- Kiri: identitas permintaan --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $p['kode'] }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $p['tim'] }} &middot; {{ $p['item'] }} item
                        </span>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                            <x-filament::icon
                                :icon="$p['ikonTahap']"
                                class="h-4 w-4 text-gray-400" />
                            {{ $p['tahap'] }}
                        </span>

                        <span @class(['inline-flex items-center gap-1.5 text-sm font-medium', $warnaWaktu])>
                            <x-filament::icon icon="heroicon-m-clock" class="h-4 w-4" />
                            {{ $p['sisa'] }}
                        </span>
                    </div>
                </div>

                {{-- Kanan: tombol tindakan --}}
                <div class="shrink-0">
                    <x-filament::button
                        tag="a"
                        :href="$p['tautan']"
                        size="sm"
                        icon="heroicon-m-arrow-right"
                        icon-position="after">
                        {{ $p['aksi'] }}
                    </x-filament::button>
                </div>

            </div>
        @empty

            {{-- ---------- KEADAAN KOSONG ---------- --}}
            <div class="py-6 text-center">
                <x-filament::icon
                    icon="heroicon-o-check-circle"
                    class="mx-auto h-8 w-8 text-success-500" />
                <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">
                    Tidak ada tindakan yang perlu dilakukan
                </p>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Seluruh pekerjaan Anda saat ini sudah tertangani.
                </p>
            </div>

        @endforelse

        {{-- ---------- KETERANGAN JUMLAH ---------- --}}
        @if ($total > $pekerjaan->count())
            <p class="border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                Menampilkan {{ $pekerjaan->count() }} dari {{ $total }} pekerjaan.
            </p>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>