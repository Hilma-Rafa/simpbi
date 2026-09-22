@php
    /**
     * Riwayat penempatan satu aset tetap.
     *
     * Seluruh angka dibaca apa adanya dari tabel riwayat_penempatan_aset;
     * tidak ada nilai yang disimpulkan di sini kecuali lama penempatan, yang
     * memang hanya selisih dua tanggal pada baris yang sama.
     */

    $jenisLabel = [
        'penempatan_awal' => 'Penempatan Awal',
        'mutasi'          => 'Mutasi',
    ];

    /**
     * Lama penempatan dihitung dalam hari, bukan diringkas menjadi bulan atau
     * tahun. Peringkasan menuntut pembulatan, dan angka bulat yang dibulatkan
     * pada dokumen inventaris lebih menyesatkan daripada angka panjang yang
     * benar. Penempatan yang masih berjalan dihitung sampai hari ini.
     */
    $lamaHari = fn ($baris) => (int) $baris->tanggal_mulai->diffInDays($baris->tanggal_selesai ?? now());
@endphp

<div class="space-y-6">

    {{-- ---------- IDENTITAS ASET ---------- --}}
    <dl class="grid grid-cols-2 gap-x-8 gap-y-3 sm:grid-cols-4">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">NUP</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $aset->nup }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Nama Aset</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ $aset->nama_aset }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Kategori</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $aset->kategori?->nama_kategori ?? '—' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Penempatan Sekarang</dt>
            <dd class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $aset->timPenempatan?->nama_tim ?? 'Belum ditempatkan' }}
            </dd>
        </div>
    </dl>

    {{-- ---------- DAFTAR PENEMPATAN ---------- --}}
    <div class="-mx-1 overflow-x-auto px-1">
        <table class="w-full min-w-[42rem] text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <th class="py-2.5 pe-4 text-start font-medium">Tim Kerja</th>
                    <th class="w-28 py-2.5 px-3 text-start font-medium">Mulai</th>
                    <th class="w-28 py-2.5 px-3 text-start font-medium">Selesai</th>
                    <th class="w-24 py-2.5 px-3 text-end font-medium">Lama</th>
                    <th class="w-36 py-2.5 px-3 text-start font-medium">Kejadian</th>
                    <th class="w-40 py-2.5 ps-3 text-start font-medium">Dasar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($riwayat as $baris)
                    <tr @class([
                        'border-b border-gray-100 last:border-0 dark:border-gray-800',
                        // Baris yang sedang berlaku diberi latar agar terbaca
                        // sekilas tanpa perlu menelusuri kolom Selesai.
                        'bg-primary-50/60 dark:bg-primary-500/10' => $baris->tanggal_selesai === null,
                    ])>
                        <td class="py-2.5 pe-4 font-medium text-gray-900 dark:text-white">
                            {{ $baris->tim?->nama_tim ?? '—' }}
                        </td>
                        <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">
                            {{ $baris->tanggal_mulai?->format('d-m-Y') }}
                        </td>
                        <td class="py-2.5 px-3 text-gray-600 dark:text-gray-400">
                            @if ($baris->tanggal_selesai)
                                {{ $baris->tanggal_selesai->format('d-m-Y') }}
                            @else
                                <span class="text-primary-600 dark:text-primary-400">Sedang berlaku</span>
                            @endif
                        </td>
                        <td class="py-2.5 px-3 text-end tabular-nums text-gray-900 dark:text-white">
                            {{ $lamaHari($baris) }} hari
                        </td>
                        <td class="py-2.5 px-3 text-gray-900 dark:text-white">
                            {{ $jenisLabel[$baris->jenis] ?? $baris->jenis }}
                        </td>
                        <td class="py-2.5 ps-3 text-gray-600 dark:text-gray-400">
                            {{ $baris->bast?->nomor_bast ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-500 dark:text-gray-400">
                            @if ($aset->tim_penempatan_id)
                                Aset ini belum memiliki riwayat penempatan. Riwayat mulai terbentuk
                                ketika aset dimutasikan melalui BAST.
                            @else
                                Aset ini belum ditempatkan pada tim kerja mana pun, sehingga belum ada
                                penempatan yang dapat dicatat.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Penempatan awal dicatat saat aset ditambahkan, sedangkan baris bertanda Mutasi terbentuk
        ketika BAST mutasi disahkan dan membawa nomor BAST-nya sebagai dasar. Baris tanpa tanggal
        selesai adalah penempatan yang sedang berlaku.
    </p>

</div>
