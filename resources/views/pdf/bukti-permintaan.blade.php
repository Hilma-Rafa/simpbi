<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $permintaan->kode_permintaan }}</title>
    <style>
        @page { margin: 18mm 20mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
        }

        /* ---------- KOP SURAT ---------- */
        .kop { width: 100%; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop-logo { width: 80px; }
        .kop-logo img { width: 72px; height: auto; }

        .instansi {
            font-size: 15pt;
            font-weight: bold;
            font-style: italic;
            line-height: 1.15;
            margin: 0;
        }
        .alamat {
            font-size: 8.5pt;
            line-height: 1.35;
            margin: 3px 0 0 0;
        }

        .garis-tebal { border-top: 3px solid #000; margin-top: 6px; }
        .garis-tipis { border-top: 1px solid #000; margin-top: 2px; }

        /* ---------- JUDUL ---------- */
        .judul {
            text-align: center;
            margin-top: 22px;
        }
        .judul h1 {
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0;
        }
        .judul p {
            font-size: 11pt;
            margin: 4px 0 0 0;
        }

        /* ---------- IDENTITAS ---------- */
        .identitas {
            margin-top: 22px;
            font-size: 11pt;
        }
        .identitas td { padding: 2px 0; vertical-align: top; }
        .identitas .label { width: 92px; }
        .identitas .pemisah { width: 12px; }

        /* ---------- TABEL BARANG ---------- */
        table.barang {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            font-size: 10.5pt;
        }
        table.barang th,
        table.barang td {
            border: 1px solid #000;
            padding: 6px 8px;
        }
        table.barang thead th {
            background: #e8e8e8;
            font-weight: bold;
            text-align: center;
        }
        .kol-no      { width: 32px;  text-align: center; }
        .kol-jumlah  { width: 60px;  text-align: center; }
        .kol-satuan  { width: 72px;  text-align: center; }
        .kol-ket     { width: 150px; }

        /* ---------- TANDA TANGAN ---------- */
        .ttd { width: 100%; margin-top: 40px; }
        .ttd td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            font-size: 11pt;
        }
        .ruang-ttd { height: 78px; }
        .nama-ttd {
            font-weight: bold;
            text-decoration: underline;
            white-space: nowrap;
        }

        /* ---------- KODE QR ---------- */
        .qr-bungkus {
            width: 86px;
            height: 86px;
            margin: 4px auto 0 auto;
            position: relative;
        }
        .qr-bungkus img.qr {
            width: 86px;
            height: 86px;
        }
        .qr-logo {
            position: absolute;
            top: 30px;
            left: 30px;
            width: 26px;
            height: 26px;
            background: #fff;
            border-radius: 13px;
            padding: 3px;
        }
        .qr-logo img { width: 20px; height: auto; }

        .catatan-kaki {
            margin-top: 34px;
            font-size: 8pt;
            font-style: italic;
            line-height: 1.4;
            color: #333;
            border-top: 1px solid #999;
            padding-top: 6px;
        }
    </style>
</head>
<body>

{{-- ==================== KOP SURAT ==================== --}}
<table class="kop">
    <tr>
        <td class="kop-logo">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
        </td>
        <td>
            <p class="instansi">BADAN PUSAT STATISTIK<br>KOTA JAKARTA BARAT</p>
            <p class="alamat">
                Jalan Raya Kebayoran Lama No. 5a, Sukabumi Selatan, Kebon Jeruk, Jakarta Barat 11560<br>
                Telepon (021) 25673776 &nbsp;&middot;&nbsp; jakbarkota.bps.go.id &nbsp;&middot;&nbsp; bps3174@bps.go.id
            </p>
        </td>
    </tr>
</table>

<div class="garis-tebal"></div>
<div class="garis-tipis"></div>

{{-- ==================== JUDUL ==================== --}}
<div class="judul">
    <h1>Surat Permintaan ATK dan Komputer Supplies</h1>
    <p>Nomor: {{ $permintaan->kode_permintaan }}</p>
</div>

{{-- ==================== IDENTITAS ==================== --}}
<table class="identitas">
    <tr>
        <td class="label">Tanggal</td>
        <td class="pemisah">:</td>
        <td>{{ $permintaan->created_at?->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td class="label">Kepada</td>
        <td class="pemisah">:</td>
        <td>Sub Bagian Umum BPS Kota Jakarta Barat</td>
    </tr>
    <tr>
        <td class="label">Dari</td>
        <td class="pemisah">:</td>
        <td>{{ $permintaan->tim?->nama_tim ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Keperluan</td>
        <td class="pemisah">:</td>
        <td>{{ $permintaan->keterangan_keperluan ?: '-' }}</td>
    </tr>
</table>

{{-- ==================== TABEL BARANG ==================== --}}
<table class="barang">
    <thead>
        <tr>
            <th class="kol-no">No</th>
            <th>Jenis Barang</th>
            <th class="kol-jumlah">Jumlah</th>
            <th class="kol-satuan">Satuan</th>
            <th class="kol-ket">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($permintaan->detail as $d)
            <tr>
                <td class="kol-no">{{ $loop->iteration }}</td>
                <td>{{ $d->barang?->nama_barang ?? '-' }}</td>
                <td class="kol-jumlah">{{ $d->jumlah_final ?? $d->jumlah_diminta }}</td>
                <td class="kol-satuan">{{ $d->barang?->satuan }}</td>
                <td class="kol-ket">{{ $d->keterangan ?: '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ==================== TANDA TANGAN ==================== --}}
<table class="ttd">
    <tr>
        <td>
            Yang Menyerahkan,<br>
            Petugas Gudang
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $penyerah ?? '.....................................' }}</span>
        </td>
        <td>
            Yang Menerima,<br>
            {{ $permintaan->tim?->nama_tim ?? 'Unit Pemohon' }}
            <div class="ruang-ttd"></div>
            <span class="nama-ttd">{{ $permintaan->nama_pemohon }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 26px;">
            Mengetahui,<br>
            Kepala Sub Bagian Umum

            @if ($qr)
                <div class="qr-bungkus">
                    <img class="qr" src="{{ $qr }}" alt="Kode verifikasi">
                    @if ($logo)
                        <div class="qr-logo"><img src="{{ $logo }}" alt=""></div>
                    @endif
                </div>
            @else
                <div class="ruang-ttd"></div>
            @endif

            <span class="nama-ttd">{{ $pengesah ?? '.....................................' }}</span>
        </td>
    </tr>
</table>

{{-- ==================== CATATAN KAKI ==================== --}}
<p class="catatan-kaki">
    Dokumen ini diterbitkan oleh Sistem Informasi Manajemen Permintaan Barang dan Inventaris
    dan disahkan secara elektronik pada
    {{ $permintaan->pengesahan_at?->translatedFormat('d F Y, H:i') ?? '-' }}.
    Keaslian dokumen dapat diperiksa dengan memindai kode QR di atas.
</p>

</body>
</html>