{{--
    Strip status sinkronisasi, dipakai ulang oleh Tim Kerja dan Aset Tetap.

    Sumber tanggalnya murni kolom synced_at yang sudah ada (diisi oleh
    ImporTimKerja/ImporAsetTetap saat data diselaraskan). Tidak ada aturan
    umur data, tidak ada countdown, dan tidak ada tanggal karangan ketika
    kosong — kekosongan dilaporkan apa adanya.
--}}
<div class="mb-4">
    @if ($syncedAt)
        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
            <span class="simpbi-tint-icon simpbi-tint-icon--green" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-check" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white">Data sudah diperbarui</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Sinkronisasi terakhir: {{ $syncedAt->translatedFormat('d F Y, H:i') }}
                </p>
            </div>
        </div>
    @else
        <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
            <span class="simpbi-tint-icon simpbi-tint-icon--slate" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-information-circle" />
            </span>
            <p class="text-sm text-gray-600 dark:text-gray-400">Belum ada data hasil sinkronisasi</p>
        </div>
    @endif
</div>
