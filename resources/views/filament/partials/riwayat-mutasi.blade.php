@php
    // Saldo sebelum baris pertama pada daftar ini
    $awal = $mutasi->isNotEmpty()
        ? $mutasi->first()->saldo_sesudah - $mutasi->first()->jumlah
        : $barang->stok_fisik;

    $totalMasuk  = $mutasi->where('jumlah', '>', 0)->sum('jumlah');
    $totalKeluar = abs($mutasi->where('jumlah', '<', 0)->sum('jumlah'));
    $akhir       = $mutasi->isNotEmpty() ? $mutasi->last()->saldo_sesudah : $barang->stok_fisik;

    $uraian = [
        'pembelian'          => 'Pembelian',
        'transfer_masuk'     => 'Transfer Masuk',
        'stok_awal'          => 'Stok Awal',
        'pemakaian'          => 'Pemakaian',
        'pengembalian'       => 'Pengembalian',
        'reklasifikasi_aset' => 'Reklasifikasi ke Aset',
        'stok_opname'        => 'Stok Opname',
    ];

    $ringkasan = [
        ['Saldo Awal',   $awal,        'text-gray-900 dark:text-white'],
        ['Total Masuk',  $totalMasuk,  'text-success-600'],
        ['Total Keluar', $totalKeluar, 'text-danger-600'],
        ['Saldo Akhir',  $akhir,       'text-gray-900 dark:text-white'],
    ];
@endphp

<div class="space-y-6">

    {{-- ---------- IDENTITAS BARANG ---------- --}}
    <dl class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-4">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Kode Barang</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $barang->kategori?->kode_kategori }}.{{ $barang->kode_barang }}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Barang</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $barang->nama_barang }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Satuan</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $barang->satuan }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Kategori</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $barang->kategori?->nama_kategori ?? '—' }}
            </dd>
        </div>
    </dl>

    {{-- ---------- RINGKASAN ---------- --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($ringkasan as [$label, $nilai, $warna])
            <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                <p @class(['text-2xl font-bold tabular-nums', $warna])>{{ $nilai }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    {{-- ---------- BUKU BESAR ---------- --}}
    <div class="-mx-1 overflow-x-auto px-1">
        <table class="w-full min-w-[46rem] text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <th class="py-2.5 pe-4 text-start font-medium">Nomor Dasar</th>
                    <th class="w-28 py-2.5 px-3 text-start font-medium">Tanggal</th>
                    <th class="py-2.5 px-3 text-start font-medium">Uraian</th>
                    <th class="w-24 py-2.5 px-3 text-end font-medium">Masuk</th>
                    <th class="w-24 py-2.5 px-3 text-end font-medium">Keluar</th>
                    <th class="w-24 py-2.5 ps-3 text-end font-medium">Sisa</th>
                </tr>
            </thead>
            <tbody>

                {{-- Saldo awal periode --}}
                <tr class="bg-gray-50 dark:bg-gray-800/60">
                    <td class="py-2.5 pe-4 text-gray-400">—</td>
                    <td class="py-2.5 px-3 text-gray-400">—</td>
                    <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-white">Saldo Awal</td>
                    <td class="py-2.5 px-3 text-end text-gray-400">—</td>
                    <td class="py-2.5 px-3 text-end text-gray-400">—</td>
                    <td class="py-2.5 ps-3 text-end font-semibold tabular-nums text-gray-900 dark:text-white">
                        {{ $awal }}
                    </td>
                </tr>

                @forelse ($mutasi as $m)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="py-2.5 pe-4 text-gray-900 dark:text-white">
                            {{ $m->nomor_dasar ?: '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">
                            {{ $m->tanggal?->format('d-m-Y') }}
                        </td>
                        <td class="py-2.5 px-3 text-gray-900 dark:text-white">
                            {{ $uraian[$m->sumber] ?? '—' }}
                            @if ($m->petugas)
                                <span class="block text-xs text-gray-500">{{ $m->petugas->name }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-end tabular-nums font-medium text-success-600">
                            {{ $m->jumlah > 0 ? $m->jumlah : '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-end tabular-nums font-medium text-danger-600">
                            {{ $m->jumlah < 0 ? abs($m->jumlah) : '—' }}
                        </td>
                        <td class="py-2.5 ps-3 text-end font-semibold tabular-nums text-gray-900 dark:text-white">
                            {{ $m->saldo_sesudah }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-500">
                            Belum ada mutasi tercatat untuk barang ini.
                        </td>
                    </tr>
                @endforelse

            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Kolom Nomor Dasar memuat kode permintaan untuk barang keluar, atau nomor dokumen
        pengadaan untuk barang masuk. Saldo pada kolom Sisa dicatat pada saat transaksi terjadi.
    </p>

</div>