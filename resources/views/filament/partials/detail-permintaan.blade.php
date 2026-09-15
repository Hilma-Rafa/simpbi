@php
    use App\Models\PermintaanBarang;

    $riwayat   = $record->persetujuan->sortBy('waktu');
    $terakhir  = $riwayat->last();
    $waktuAkhir = $terakhir?->waktu ?? $record->updated_at;

    // Alasan hanya ditampilkan bila tahap terakhir berupa penolakan
    $alasan = ($terakhir && $terakhir->keputusan === 'tolak') ? $terakhir->catatan : null;

    $namaTahap = [
        'pengajuan'  => 'Permintaan Diajukan',
        'ketua_tim'  => 'Persetujuan Ketua Tim',
        'verifikasi' => 'Verifikasi Ketersediaan Fisik',
        'kasubbag'   => 'Persetujuan Kasubbag Umum',
        'penyiapan'  => 'Penyiapan Barang',
        'konfirmasi' => 'Konfirmasi Penerimaan',
        'pengesahan' => 'Pengesahan Akhir',
    ];

    // Penyebutan pendek untuk dipakai di dalam kalimat label status, karena
    // nama tahap versi panjang membuat judulnya terbaca berbelit.
    $namaTahapSingkat = [
        'pengajuan'  => 'Pengajuan',
        'ketua_tim'  => 'Persetujuan Ketua Tim',
        'verifikasi' => 'Verifikasi Gudang',
        'kasubbag'   => 'Persetujuan Kasubbag',
        'penyiapan'  => 'Penyiapan Barang',
        'konfirmasi' => 'Pengambilan Barang',
        'pengesahan' => 'Pengesahan',
    ];

    $status = $record->status;

    // Pada permintaan kedaluwarsa, baris riwayat terakhir adalah baris yang
    // ditulis sapuan sistem, dan tahapnya menandai di titik mana permintaan
    // berhenti. Statusnya sendiri hanya berbunyi "kedaluwarsa", sehingga tanpa
    // tahap ini pengguna tidak tahu siapa yang seharusnya menindaklanjuti.
    $tahapTerhenti = $status === 'kedaluwarsa' ? $terakhir?->tahap : null;

    // Waktu pada baris tersebut sengaja dicatat sebagai saat batas jatuh,
    // bukan saat sapuan berjalan, sehingga dapat langsung ditampilkan.
    $batasTerlewat = $status === 'kedaluwarsa' ? $terakhir?->waktu : null;

    // Tahap sesudah titik berhenti ditampilkan redup pada linimasa. Tanpa itu
    // linimasa permintaan kedaluwarsa berhenti begitu saja dan tidak terlihat
    // bahwa masih ada tahapan yang seharusnya dilalui.
    $urutanTahap = ['pengajuan', 'ketua_tim', 'verifikasi', 'kasubbag', 'penyiapan', 'konfirmasi', 'pengesahan'];

    $posisiTerhenti     = $tahapTerhenti ? array_search($tahapTerhenti, $urutanTahap, true) : false;
    $tahapTidakTercapai = $posisiTerhenti === false ? [] : array_slice($urutanTahap, $posisiTerhenti + 1);

    $meta = match ($status) {
        'selesai' => [
            'label' => 'SELESAI',
            'nada'  => 'success',
            'ikon'  => 'heroicon-o-check-circle',
        ],
        'ditolak_ketua' => [
            'label' => 'DITOLAK KETUA TIM',
            'nada'  => 'danger',
            'ikon'  => 'heroicon-o-x-circle',
        ],
        'ditolak_kasubbag' => [
            'label' => 'DITOLAK KASUBBAG UMUM',
            'nada'  => 'danger',
            'ikon'  => 'heroicon-o-x-circle',
        ],
        'bermasalah' => [
            'label' => 'BERMASALAH',
            'nada'  => 'danger',
            'ikon'  => 'heroicon-o-exclamation-triangle',
        ],
        'kedaluwarsa' => [
            'label' => $tahapTerhenti
                ? 'KEDALUWARSA PADA ' . strtoupper($namaTahapSingkat[$tahapTerhenti] ?? $tahapTerhenti)
                : 'KEDALUWARSA',
            'nada'  => 'gray',
            'ikon'  => 'heroicon-o-clock',
        ],
        'siap_diambil' => [
            'label' => 'SIAP DIAMBIL',
            'nada'  => 'info',
            'ikon'  => 'heroicon-o-inbox-arrow-down',
        ],
        default => [
            'label' => strtoupper(PermintaanBarang::STATUS[$status] ?? $status),
            'nada'  => 'warning',
            'ikon'  => 'heroicon-o-clock',
        ],
    };

    $warna = [
        'success' => ['bg' => 'bg-success-50 dark:bg-success-950/40',  'br' => 'border-success-500',  'tx' => 'text-success-700 dark:text-success-400',  'ic' => 'text-success-600'],
        'danger'  => ['bg' => 'bg-danger-50 dark:bg-danger-950/40',    'br' => 'border-danger-500',   'tx' => 'text-danger-700 dark:text-danger-400',    'ic' => 'text-danger-600'],
        'warning' => ['bg' => 'bg-warning-50 dark:bg-warning-950/40',  'br' => 'border-warning-500',  'tx' => 'text-warning-700 dark:text-warning-400',  'ic' => 'text-warning-600'],
        'info'    => ['bg' => 'bg-info-50 dark:bg-info-950/40',        'br' => 'border-info-500',     'tx' => 'text-info-700 dark:text-info-400',        'ic' => 'text-info-600'],
        'gray'    => ['bg' => 'bg-gray-50 dark:bg-gray-800/60',        'br' => 'border-gray-400',     'tx' => 'text-gray-700 dark:text-gray-300',        'ic' => 'text-gray-500'],
    ][$meta['nada']];

@endphp

<div class="space-y-6">

    {{-- ================= STATUS ================= --}}
    <div class="rounded-xl border-s-4 {{ $warna['br'] }} {{ $warna['bg'] }} px-5 py-4">
        <div class="flex items-start gap-4">
            <x-filament::icon
                :icon="$meta['ikon']"
                @class(['mt-0.5 h-7 w-7 shrink-0', $warna['ic']]) />

            <div class="min-w-0 flex-1">
                <p @class(['text-lg font-bold uppercase tracking-wide', $warna['tx']])>
                    {{ $meta['label'] }}
                </p>

                @if ($status === 'kedaluwarsa')
                    {{-- Baris riwayat kedaluwarsa ditulis sistem, bukan penolakan
                         oleh seseorang, sehingga catatannya tidak ditampilkan
                         sebagai "alasan penolakan" seperti pada status ditolak. --}}
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                        Terhenti {{ $batasTerlewat?->translatedFormat('d F Y, H:i') ?? '—' }}
                    </p>
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                        Maaf pengajuan Anda terhenti karena telah melewati batas waktu. 
                    </p>
                @else
                    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                        Status terakhir: {{ $waktuAkhir?->translatedFormat('d F Y, H:i') ?? '—' }}
                    </p>

                    @if ($alasan)
                        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                            <span class="font-semibold">Alasan penolakan:</span> {{ $alasan }}
                        </p>
                    @endif
                @endif

                @if ($record->hold_expired_at && ! in_array($status, ['selesai', 'ditolak_ketua', 'ditolak_kasubbag', 'bermasalah', 'kedaluwarsa']))
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-semibold">Batas waktu tahapan:</span>
                        {{ $record->hold_expired_at->translatedFormat('d F Y, H:i') }}
                        <span class="text-gray-500">({{ $record->hold_expired_at->diffForHumans() }})</span>
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ================= INFORMASI PERMINTAAN ================= --}}
    <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Informasi Permintaan
        </h3>

        <dl class="grid grid-cols-1 gap-x-10 gap-y-4 sm:grid-cols-2">
            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tim Pemohon</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->tim?->nama_tim ?? '—' }}</dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Pemohon</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->nama_pemohon }}</dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">NIP</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $record->nip_pemohon ?: '—' }}</dd>
            </div>

            <div class="flex flex-col gap-0.5">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Tanggal Pengajuan</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ $record->created_at?->translatedFormat('d F Y, H:i') }}
                </dd>
            </div>

            <div class="flex flex-col gap-0.5 sm:col-span-2">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Keperluan</dt>
                <dd class="text-sm text-gray-900 dark:text-white">{{ $record->keterangan_keperluan ?: '—' }}</dd>
            </div>
        </dl>
    </div>

    <hr class="border-gray-200 dark:border-gray-700">

    {{-- ================= DAFTAR BARANG ================= --}}
    <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Daftar Barang yang Diminta
            <span class="ms-1 font-normal normal-case text-gray-400">({{ $record->detail->count() }} barang)</span>
        </h3>

        <div class="-mx-1 overflow-x-auto px-1">
            <table class="w-full min-w-[46rem] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-start text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <th class="w-10 py-2.5 pe-3 text-start font-medium">No</th>
                        <th class="py-2.5 pe-4 text-start font-medium">Nama Barang</th>
                        <th class="w-24 py-2.5 px-3 text-start font-medium">Satuan</th>
                        <th class="w-24 py-2.5 px-3 text-end font-medium">Diminta</th>
                        <th class="w-28 py-2.5 px-3 text-end font-medium">Hasil Cek</th>
                        <th class="w-32 py-2.5 px-3 text-start font-medium">Kondisi</th>
                        <th class="w-28 py-2.5 ps-3 text-end font-medium">Disetujui</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record->detail as $d)
                        @php
                            $nadaKondisi = match ($d->kondisi_verif) {
                                'tersedia' => 'success',
                                'rusak'    => 'danger',
                                'kurang'   => 'warning',
                                default    => 'gray',
                            };
                            $tekstKondisi = match ($d->kondisi_verif) {
                                'tersedia' => 'Tersedia',
                                'rusak'    => 'Rusak',
                                'kurang'   => 'Kurang',
                                default    => 'Belum dicek',
                            };
                        @endphp
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="py-3 pe-3 align-top text-gray-500">{{ $loop->iteration }}</td>

                            <td class="py-3 pe-4 align-top">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $d->barang?->nama_barang ?? '—' }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $d->barang?->kode_barang }}</p>
                            </td>

                            <td class="py-3 px-3 align-top text-gray-600 dark:text-gray-400">
                                {{ $d->barang?->satuan }}
                            </td>

                            <td class="py-3 px-3 align-top text-end tabular-nums text-gray-900 dark:text-white">
                                {{ $d->jumlah_diminta }}
                            </td>

                            <td class="py-3 px-3 align-top text-end tabular-nums text-gray-600 dark:text-gray-400">
                                {{ $d->jumlah_verif_fisik ?? '—' }}
                            </td>

                            <td class="py-3 px-3 align-top">
                                <x-filament::badge :color="$nadaKondisi" size="sm">
                                    {{ $tekstKondisi }}
                                </x-filament::badge>
                            </td>

                            <td class="py-3 ps-3 align-top text-end tabular-nums font-semibold text-gray-900 dark:text-white">
                                {{ $d->jumlah_final ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($record->ketidaksesuaian->isNotEmpty())
            <div class="mt-4 rounded-lg border-s-4 border-warning-500 bg-warning-50 px-4 py-3 dark:bg-warning-950/40">
                <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">
                    Ketidaksesuaian Barang
                </p>
                @foreach ($record->ketidaksesuaian as $k)
                    <p class="mt-1.5 text-sm text-gray-700 dark:text-gray-300">{{ $k->deskripsi }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $k->dapat_diatasi ? 'Dapat diatasi di tempat' : 'Tidak dapat diatasi' }}
                        @if ($k->tindak_lanjut) &middot; {{ $k->tindak_lanjut }} @endif
                    </p>
                @endforeach
            </div>
        @endif
    </div>

    <hr class="border-gray-200 dark:border-gray-700">

    {{-- ================= RIWAYAT PROSES ================= --}}
    <div>
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            Riwayat Proses
        </h3>

        <ol class="space-y-4">
            @forelse ($riwayat as $r)
                @php
                    // Baris terakhir permintaan kedaluwarsa disimpan dengan
                    // keputusan "tolak" agar konsisten dengan skema riwayat,
                    // tetapi menampilkannya sebagai "Ditolak" keliru: tidak ada
                    // yang menolak, tahapannya hanya lewat waktu.
                    $adalahBatasLewat = $status === 'kedaluwarsa' && $loop->last;

                    $nadaTahap = $adalahBatasLewat
                        ? ['bg' => 'bg-gray-400 dark:bg-gray-600', 'ikon' => 'heroicon-m-clock']
                        : match ($r->keputusan) {
                            'setuju', 'selesai' => ['bg' => 'bg-success-500', 'ikon' => 'heroicon-m-check'],
                            'tolak'             => ['bg' => 'bg-danger-500',  'ikon' => 'heroicon-m-x-mark'],
                            default             => ['bg' => 'bg-warning-500', 'ikon' => 'heroicon-m-clock'],
                        };

                    $tekstKeputusan = $adalahBatasLewat
                        ? 'Lewat batas waktu'
                        : match ($r->keputusan) {
                            'setuju'  => 'Disetujui',
                            'tolak'   => 'Ditolak',
                            'selesai' => 'Selesai',
                            default   => $r->keputusan,
                        };
                @endphp

                <li class="flex gap-3">
                    <div class="flex flex-col items-center">
                        <span @class(['flex h-6 w-6 shrink-0 items-center justify-center rounded-full', $nadaTahap['bg']])>
                            <x-filament::icon :icon="$nadaTahap['ikon']" class="h-3.5 w-3.5 text-white" />
                        </span>
                        @unless ($loop->last && empty($tahapTidakTercapai))
                            <span class="mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                        @endunless
                    </div>

                    <div class="min-w-0 flex-1 pb-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $namaTahap[$r->tahap] ?? $r->tahap }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{-- Pelaksana dilewati pada baris batas waktu: yang
                                 mencatatnya sistem, bukan orang. --}}
                            @unless ($adalahBatasLewat)
                                {{ $r->pelaksana?->name ?? '—' }} &middot;
                            @endunless
                            {{ $r->waktu?->translatedFormat('d F Y, H:i') }}
                            &middot; {{ $tekstKeputusan }}
                        </p>
                        @if ($r->catatan)
                            <p class="mt-1.5 text-sm text-gray-700 dark:text-gray-300">{{ $r->catatan }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="text-sm text-gray-500">Belum ada riwayat proses.</li>
            @endforelse

            @foreach ($tahapTidakTercapai as $i => $tahap)
                <li class="flex gap-3">
                    <div class="flex flex-col items-center">
                        {{-- Kotak penanda dibuat selebar lingkaran tahap yang sudah
                             dilalui agar seluruh judul tahap berbaris rata; hanya
                             titiknya yang dikecilkan sebagai tanda belum tercapai. --}}
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                        </span>
                        @unless ($loop->last)
                            <span class="mt-1 w-px flex-1 bg-gray-200 dark:bg-gray-700"></span>
                        @endunless
                    </div>

                    <div class="min-w-0 flex-1 pb-1">
                        <p class="text-sm text-gray-400 dark:text-gray-500">
                            {{ $namaTahap[$tahap] ?? $tahap }}
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-600">Tidak sampai tahap ini</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

</div>