<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul ?? 'Kartu Kendali Barang Persediaan' }}</title>
    <style>
        @page { margin: 15mm 14mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
        }

        /* ---------- JUDUL ----------
           Dua baris judul mengikuti berkas Kartu Kendali Barang Persediaan
           yang berjalan di Sub-Bagian Umum, sehingga hasil cetak sistem dapat
           langsung disandingkan dengan arsip tahun-tahun sebelumnya. */
        .judul { text-align: center; margin-bottom: 14px; }
        .judul h1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .judul h2 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 3px 0 0 0;
        }

        /* ---------- IDENTITAS BARANG ---------- */
        table.identitas { border-collapse: collapse; margin-bottom: 10px; }
        table.identitas td {
            padding: 1px 0;
            vertical-align: top;
            font-size: 10pt;
        }
        table.identitas td.label { width: 90px; }
        table.identitas td.pemisah { width: 12px; }

        /* ---------- TABEL TRANSAKSI ---------- */
        table.kartu {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        table.kartu th,
        table.kartu td {
            border: 0.5pt solid #000;
            padding: 3px 5px;
            vertical-align: top;
        }
        table.kartu thead th {
            background: #E8EEF7;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }

        /* Lebar kolom ditetapkan agar kolom angka tetap sempit dan kolom
           uraian mendapat sisa ruang, sebab dompdf tidak menata lebar tabel
           sebaik peramban. */
        .k-no      { width: 30px;  text-align: center; }
        .k-nomor   { width: 130px; }
        .k-tanggal { width: 78px;  text-align: center; }
        .k-jumlah  { width: 62px;  text-align: right; }

        tr.saldo td {
            background: #F2F2F2;
            font-weight: bold;
        }

        .kosong {
            text-align: center;
            font-style: italic;
        }

        /* ---------- KAKI ---------- */
        .kaki {
            margin-top: 14px;
            font-size: 8pt;
            font-style: italic;
            border-top: 0.5pt solid #999;
            padding-top: 5px;
        }

        /* Setiap kartu memulai halamannya sendiri. Kartu kendali diarsipkan
           per barang, sehingga dua kartu pada satu lembar tidak dapat dipisah
           ketika diberkaskan. */
        .pemisah-halaman { page-break-before: always; }
    </style>
</head>
<body>

{{-- Tampilan ini melayani satu kartu maupun sehimpunan kartu sekaligus: aksi
     per barang mengirim satu, ekspor halaman Kartu Kendali mengirim seluruh
     barang pada kategori terpilih. Keduanya memakai satu tampilan supaya tata
     letaknya tidak pernah menyimpang satu sama lain. --}}
@foreach ($kartu as $isi)
    @php
        $barang  = $isi['barang'];
        $mutasi  = $isi['mutasi'];
        $tahun   = $isi['tahun'];
        $periode = $isi['periode'];
        $awal    = $isi['awal'];
        $akhir   = $isi['akhir'];
    @endphp

    @if (! $loop->first)
        <div class="pemisah-halaman"></div>
    @endif

<div class="judul">
    <h1>Kartu Kendali Barang Persediaan (ATK/ARK)</h1>
    <h2>Badan Pusat Statistik Kota Jakarta Barat Tahun {{ $tahun }}</h2>
</div>

<table class="identitas">
    <tr>
        <td class="label">Kode Barang</td>
        <td class="pemisah">:</td>
        <td>{{ $barang->kode_lengkap }}</td>
    </tr>
    <tr>
        <td class="label">Nama Barang</td>
        <td class="pemisah">:</td>
        <td>{{ $barang->nama_barang }}</td>
    </tr>
    <tr>
        <td class="label">Satuan</td>
        <td class="pemisah">:</td>
        <td>{{ $barang->satuan }}</td>
    </tr>
    <tr>
        <td class="label">Periode</td>
        <td class="pemisah">:</td>
        <td>{{ $periode }}</td>
    </tr>
</table>

<table class="kartu">
    <thead>
        <tr>
            <th class="k-no">No.</th>
            <th class="k-nomor">Nomor Dasar M/K</th>
            <th class="k-tanggal">Tanggal M/K</th>
            <th>Uraian M/K</th>
            <th class="k-jumlah">Masuk (M)</th>
            <th class="k-jumlah">Keluar (K)</th>
            <th class="k-jumlah">Sisa</th>
        </tr>
    </thead>
    <tbody>

        {{-- Saldo pembuka periode: hanya kolom Sisa yang berisi, seperti pada
             baris "Stok Awal" kartu kendali manual. --}}
        <tr class="saldo">
            {{-- Label dibentangkan sampai kolom Uraian, mengikuti berkas asli
                 yang menuliskannya mulai dari kolom paling kiri. --}}
            <td colspan="4">Stok Awal</td>
            <td class="k-jumlah"></td>
            <td class="k-jumlah"></td>
            <td class="k-jumlah">{{ $awal }}</td>
        </tr>

        @forelse ($mutasi as $m)
            <tr>
                <td class="k-no">{{ $loop->iteration }}</td>
                <td class="k-nomor">{{ $m->nomor_dasar ?: '' }}</td>
                <td class="k-tanggal">{{ $m->tanggal?->format('d-m-Y') }}</td>
                <td>{{ $m->uraian }}</td>
                <td class="k-jumlah">{{ $m->jumlah > 0 ? $m->jumlah : '' }}</td>
                <td class="k-jumlah">{{ $m->jumlah < 0 ? abs($m->jumlah) : '' }}</td>
                {{-- Saldo diambil apa adanya dari kolom saldo_sesudah, tidak
                     dihitung ulang, agar angka pada kartu sama persis dengan
                     yang tercatat saat transaksi terjadi. --}}
                <td class="k-jumlah">{{ $m->saldo_sesudah }}</td>
            </tr>
        @empty
            <tr>
                <td class="kosong" colspan="7">
                    Tidak ada transaksi tercatat pada periode ini.
                </td>
            </tr>
        @endforelse

        <tr class="saldo">
            {{-- Label dibentangkan sampai kolom Uraian, mengikuti berkas asli
                 yang menuliskannya mulai dari kolom paling kiri. --}}
            <td colspan="4">Stok Akhir</td>
            <td class="k-jumlah"></td>
            <td class="k-jumlah"></td>
            <td class="k-jumlah">{{ $akhir }}</td>
        </tr>

    </tbody>
</table>

<div class="kaki">
    Dicetak pada {{ $dicetak->translatedFormat('d F Y, H:i') }} WIB
    @if ($pencetak)
        oleh {{ $pencetak->name }}@if ($pencetak->nip) (NIP {{ $pencetak->nip }})@endif
    @endif
    &middot; Dihasilkan otomatis oleh SIMPBI dari buku besar mutasi stok.
</div>
@endforeach

</body>
</html>
