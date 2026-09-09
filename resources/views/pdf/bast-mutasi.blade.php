<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $bast->nomor_bast }}</title>
    <style>
        @page { margin: 18mm 20mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11pt; color: #000; margin: 0; }

        /* ---------- KOP SURAT ---------- */
        .kop { width: 100%; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop-logo { width: 80px; }
        .kop-logo img { width: 72px; height: auto; }
        .instansi { font-size: 15pt; font-weight: bold; font-style: italic; line-height: 1.15; margin: 0; }
        .alamat { font-size: 8.5pt; line-height: 1.35; margin: 3px 0 0 0; }
        .garis-tebal { border-top: 3px solid #000; margin-top: 6px; }
        .garis-tipis { border-top: 1px solid #000; margin-top: 2px; }

        /* ---------- JUDUL ---------- */
        .judul { text-align: center; margin-top: 22px; }
        .judul h1 { font-size: 12.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .judul p { font-size: 11pt; margin: 4px 0 0 0; }

        .isi { margin-top: 20px; text-align: justify; line-height: 1.5; }
        .isi p { margin: 0 0 10px 0; }

        table.rincian { width: 100%; border-collapse: collapse; margin: 6px 0 12px 0; }
        table.rincian td { padding: 4px 6px; vertical-align: top; }
        table.rincian td.label { width: 34%; }
        table.rincian td.pemisah { width: 3%; }

        .mutasi { width: 100%; border-collapse: collapse; margin: 8px 0 4px; }
        .mutasi th, .mutasi td { border: 1px solid #000; padding: 6px 8px; font-size: 10.5pt; }
        .mutasi th { background: #f0f0f0; text-align: left; }

        /* ---------- BLOK TANDA TANGAN ---------- */
        .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .ttd td { width: 33.33%; vertical-align: top; text-align: center; font-size: 10.5pt; padding: 0 6px; }
        /* Tinggi tetap agar kolom 1-baris dan 2-baris sejajar. */
        .ttd .peran { margin: 0; height: 44px; line-height: 1.3; }
        .ttd .sign-area { height: 92px; padding-top: 8px; }
        .ttd .nama { margin: 0; font-weight: bold; text-decoration: underline; }

        /* e-TTD: QR dengan logo di tengah (gaya tanda tangan elektronik) */
        .ettd-wrap { position: relative; width: 72px; height: 72px; margin: 0 auto; }
        .ettd-wrap img.qr { width: 72px; height: 72px; display: block; }
        .ettd-wrap img.logo {
            position: absolute; top: 26px; left: 26px; width: 20px; height: 20px;
            background: #fff; padding: 1px;
        }
        .ettd-note { font-size: 7.5pt; font-style: italic; color: #333; margin: 3px 0 0; }

        .kaki { margin-top: 30px; font-size: 8pt; color: #333; text-align: center; border-top: 1px solid #ccc; padding-top: 6px; }
        .placeholder-ttd { font-size: 9pt; color: #777; font-style: italic; padding-top: 30px; }
    </style>
</head>
<body>

    {{-- ================= KOP ================= --}}
    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if ($logo)<img src="{{ $logo }}" alt="Logo BPS">@endif
            </td>
            <td>
                <p class="instansi">BADAN PUSAT STATISTIK<br>KOTA JAKARTA BARAT</p>
                <p class="alamat">
                    Sub-Bagian Umum &middot; Jl. Raya Kembangan No. 2, Jakarta Barat<br>
                    Telepon (021) 5820128 &middot; Surel: bps3174@bps.go.id
                </p>
            </td>
        </tr>
    </table>
    <div class="garis-tebal"></div>
    <div class="garis-tipis"></div>

    {{-- ================= JUDUL ================= --}}
    <div class="judul">
        <h1>Berita Acara Serah Terima Mutasi Aset</h1>
        <p>Nomor: {{ $bast->nomor_bast }}</p>
    </div>

    {{-- ================= ISI ================= --}}
    <div class="isi">
        <p>
            Pada hari ini,
            {{ ($bast->disahkan_at ?? $bast->created_at)?->translatedFormat('l, d F Y') }},
            bertempat di Sub-Bagian Umum Badan Pusat Statistik Kota Jakarta Barat, telah dilakukan
            serah terima mutasi aset tetap kategori peralatan dan mesin dengan rincian sebagai berikut:
        </p>

        <table class="mutasi">
            <tr><th style="width:34%">Nomor Urut Pendaftaran (NUP)</th><td>{{ $bast->aset?->nup }}</td></tr>
            <tr><th>Nama Aset</th><td>{{ $bast->aset?->nama_aset }}</td></tr>
            <tr><th>Kategori</th><td>{{ $bast->aset?->kategori?->nama_kategori ?? '-' }}</td></tr>
            <tr><th>Unit Kerja Asal</th><td>{{ $bast->timAsal?->nama_tim ?? '-' }}</td></tr>
            <tr><th>Unit Kerja Tujuan</th><td>{{ $bast->timTujuan?->nama_tim ?? '-' }}</td></tr>
            <tr><th>Alasan Mutasi</th><td>{{ $bast->alasan_mutasi }}</td></tr>
        </table>

        <p>
            Demikian berita acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
        </p>
    </div>

    {{-- ================= TANDA TANGAN ================= --}}
    <table class="ttd">
        <tr>
            <td>
                <p class="peran">Yang Menyerahkan,</p>
                <div class="sign-area">
                    <div class="placeholder-ttd">&nbsp;</div>
                </div>
                <p class="nama">{{ $bast->pihak_penyerah }}</p>
            </td>
            <td>
                <p class="peran">Yang Menerima,</p>
                <div class="sign-area">
                    <div class="placeholder-ttd">&nbsp;</div>
                </div>
                <p class="nama">{{ $bast->pihak_penerima }}</p>
            </td>
            <td>
                <p class="peran">Mengetahui,<br>Kepala Sub-Bagian Umum</p>
                <div class="sign-area">
                    @if ($bast->disahkan_at && $qr)
                        <div class="ettd-wrap">
                            <img class="qr" src="{{ $qr }}" alt="e-TTD">
                            @if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif
                        </div>
                        <p class="ettd-note">Ditandatangani secara elektronik</p>
                    @else
                        <div class="placeholder-ttd">(menunggu pengesahan)</div>
                    @endif
                </div>
                <p class="nama">{{ $bast->disahkanOleh?->name ?? '..............................' }}</p>
            </td>
        </tr>
    </table>

    <div class="kaki">
        Dokumen ini ditandatangani secara elektronik dan sah tanpa memerlukan tanda tangan basah maupun stempel.
        Keaslian dokumen dapat diperiksa dengan memindai kode QR pada kolom pengesahan.
    </div>

</body>
</html>
