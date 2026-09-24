@php
    $labelStatus = [
        'menunggu_konfirmasi'   => 'Menunggu Konfirmasi',
        'menunggu_pengesahan'   => 'Menunggu Pengesahan',
        'selesai_administratif' => 'Selesai Administratif',
    ];

    $warnaStatus = [
        'menunggu_konfirmasi'   => ['bg' => 'bg-info-50 dark:bg-info-950/40',       'br' => 'border-info-500',    'tx' => 'text-info-700 dark:text-info-400',    'ic' => 'text-info-600'],
        'menunggu_pengesahan'   => ['bg' => 'bg-warning-50 dark:bg-warning-950/40', 'br' => 'border-warning-500', 'tx' => 'text-warning-700 dark:text-warning-400', 'ic' => 'text-warning-600'],
        'selesai_administratif' => ['bg' => 'bg-success-50 dark:bg-success-950/40', 'br' => 'border-success-500', 'tx' => 'text-success-700 dark:text-success-400', 'ic' => 'text-success-600'],
    ];

    $ikonStatus = [
        'menunggu_konfirmasi'   => 'heroicon-o-clock',
        'menunggu_pengesahan'   => 'heroicon-o-clock',
        'selesai_administratif' => 'heroicon-o-check-circle',
    ];

    $status = $record->status;
    $warna  = $warnaStatus[$status] ?? $warnaStatus['menunggu_konfirmasi'];
@endphp

<div class="space-y-6">

    {{-- ================= STATUS ================= --}}
    <div class="rounded-xl border-s-4 {{ $warna['br'] }} {{ $warna['bg'] }} px-5 py-4">
        <div class="flex items-start gap-4">
            <x-filament::icon
                :icon="$ikonStatus[$status] ?? 'heroicon-o-clock'"
                @class(['mt-0.5 h-7 w-7 shrink-0', $warna['ic']]) />

            <div class="min-w-0 flex-1">
                <p @class(['text-lg font-bold uppercase tracking-wide', $warna['tx']])>
                    {{ $labelStatus[$status] ?? $status }}
                </p>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                    {{ $record->nomor_bast }}
                </p>
            </div>
        </div>
    </div>

    {{-- ================= RINCIAN MUTASI ================= --}}
    <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Rincian Mutasi
        </h3>

        <dl class="grid grid-cols-1 gap-x-10 gap-y-4 sm:grid-cols-2">
            <div class="flex flex-col gap-0.5 sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Aset</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $record->aset?->nama_aset ?? '—' }}
                    <span class="font-normal text-gray-500">(NUP {{ $record->aset?->nup ?? '-' }})</span>
                </dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tim Kerja Asal</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->timAsal?->nama_tim ?? '—' }}</dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tim Kerja Tujuan</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->timTujuan?->nama_tim ?? '—' }}</dd>
            </div>

            <div class="flex flex-col gap-0.5 sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Alasan Mutasi</dt>
                <dd class="text-sm text-gray-900 dark:text-white">{{ $record->alasan_mutasi }}</dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Dibuat Oleh</dt>
                <dd class="text-sm text-gray-900 dark:text-white">
                    {{ $record->dibuatOleh?->name ?? '—' }}
                    <span class="text-gray-500">&middot; {{ $record->created_at?->translatedFormat('d F Y, H:i') }}</span>
                </dd>
            </div>

            @if ($record->dikonfirmasi_at)
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Dikonfirmasi Oleh</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">
                        {{ $record->dikonfirmasiOleh?->name ?? '—' }}
                        <span class="text-gray-500">&middot; {{ $record->dikonfirmasi_at->translatedFormat('d F Y, H:i') }}</span>
                    </dd>
                </div>
            @endif

            @if ($record->disahkan_at)
                <div class="flex flex-col gap-0.5">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Disahkan Oleh</dt>
                    <dd class="text-sm text-gray-900 dark:text-white">
                        {{ $record->disahkanOleh?->name ?? '—' }}
                        <span class="text-gray-500">&middot; {{ $record->disahkan_at->translatedFormat('d F Y, H:i') }}</span>
                    </dd>
                </div>
            @endif
        </dl>
    </div>

</div>
