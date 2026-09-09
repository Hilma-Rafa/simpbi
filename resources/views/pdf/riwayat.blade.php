@php
    // Logo disisipkan sebagai data URI, sebab dompdf tidak memuat berkas
    // melalui HTTP ketika berkas dibentuk dari baris perintah.
    $berkasLogo = public_path('images/logo-bps.png');
    $logo = is_file($berkasLogo)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($berkasLogo))
        : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 14mm 12mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 9.5pt;
            color: #000;
            margin: 0;
        }

        /* ---------- KOP SURAT ---------- */
        .kop { width: 100%; }
        .kop td { vertical-align: middle; padding: 0; }
        .kop-logo { width: 70px; }
        .kop-logo img { width: 62px; height: auto; }

        .instansi {
            font-size: 13pt;
            font-weight: bold;
            font-style: italic;
            line-height: 1.15;
            margin: 0;
        }
        .alamat { font-size: 8pt; line-height: 1.3; margin: 3px 0 0 0; }

        .garis-tebal { border-top: 3px solid #000; margin-top: 6px; }
        .garis-tipis { border-top: 1px solid #000; margin-top: 2px; }

        /* ---------- JUDUL ---------- */
        .judul { text-align: center; margin-top: 16px; }
        .judul h1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin: 0;
        }
        .judul p { font-size: 9pt; margin: 4px 0 0 0; }

        /* ---------- BAGIAN ---------- */
        .bagian { margin-top: 18px; }
        .bagian h2 {
            font-size: 10.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }
        .bagian .keterangan { font-size: 8.5pt; font-style: italic; margin: 0 0 6px 0; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        table.data th,
        table.data td {
            border: 0.5pt solid #000;
            padding: 3px 5px;
            text-align: left;
            vertical-align: top;
        }
        table.data thead th {
            background: #E8EEF7;
            font-weight: bold;
            white-space: nowrap;
        }
        table.data td.nomor { text-align: right; width: 26px; }

        .kosong {
            border: 0.5pt solid #000;
            border-top: 0;
            padding: 10px;
            text-align: center;
            font-style: italic;
            font-size: 8.5pt;
        }

        /* ---------- KAKI ---------- */
        .kaki {
            margin-top: 20px;
            font-size: 8pt;
            font-style: italic;
            border-top: 0.5pt solid #999;
            padding-top: 5px;
        }
    </style>
</head>
<body>

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

<div class="judul">
    <h1>{{ $judul }}</h1>
    <p>Sistem Informasi Manajemen Permintaan Barang dan Inventaris (SIMPBI)</p>
</div>

@foreach ($bagian as $b)
    <div class="bagian">
        <h2>{{ $b['judul'] }}</h2>

        @if (filled($b['keterangan']))
            <p class="keterangan">{{ $b['keterangan'] }}</p>
        @endif

        <table class="data">
            <thead>
                <tr>
                    <th class="nomor">No</th>
                    @foreach ($b['kolom'] as $kolom)
                        <th>{{ $kolom }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($b['baris'] as $baris)
                    <tr>
                        <td class="nomor">{{ $loop->iteration }}</td>
                        @foreach ($baris as $nilai)
                            <td>{{ $nilai instanceof \DateTimeInterface ? $nilai->format('d-m-Y H:i') : $nilai }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($b['kolom']) + 1 }}" style="text-align: center; font-style: italic;">
                            Tidak ada data pada penyaring yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<div class="kaki">
    Dicetak pada {{ $dicetak->translatedFormat('d F Y, H:i') }} WIB
    @if ($pencetak)
        oleh {{ $pencetak->name }}@if ($pencetak->nip) (NIP {{ $pencetak->nip }})@endif
    @endif
    &middot; Dokumen ini dihasilkan otomatis oleh SIMPBI dan mengikuti penyaring data yang sedang digunakan.
</div>

</body>
</html>
