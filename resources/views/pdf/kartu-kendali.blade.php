<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul ?? 'Kartu Kendali Barang Persediaan' }}</title>
    <style>
        /* ============================================================
         * Cerminan template Kartu Kendali Barang Persediaan Sub-Bagian
         * Umum (berkas Excel master). PDF ini sengaja BUKAN rancangan
         * tersendiri — ia meniru berkas Excel sepenuhnya dan hanya
         * berbeda format berkas. Setiap ukuran di sini diturunkan dari
         * berkas master: halaman lanskap A4, huruf Times New Roman,
         * tujuh kolom terlihat (kolom Harga Satuan dan Nilai pada master
         * disembunyikan, sehingga tidak ikut dicetak), baris nomor kolom
         * (1)…(8), dua puluh slot transaksi bergaris, baris "dst", serta
         * baris Stok Awal dan Stok Akhir. Tidak ada catatan kaki, kode
         * QR, tanda tangan, maupun metadata cetak — tak satu pun ada pada
         * berkas Excel.
         * ============================================================ */

        @page { size: A4 landscape; margin: 0.75in 0.7in; }

        * { box-sizing: border-box; }

        /* Berkas master memakai Arial 11pt pada seluruh selnya (huruf bawaan
           Times New Roman workbook tidak dipakai satu sel pun). DomPDF
           memetakan Arial ke Helvetica bawaannya, jadi tak ada huruf yang
           perlu disematkan. */
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
        }

        /* ---------- JUDUL (baris 1-2 master, di-merge A:I) ---------- */
        .judul { text-align: center; }
        .judul h1,
        .judul h2 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            line-height: 13pt;
        }

        /* ---------- IDENTITAS BARANG (baris 3-6 master) ----------
           Label di kolom kiri, nilai di kolom ketiga, tanpa tanda titik
           dua — sama seperti berkas master. */
        table.identitas {
            border-collapse: collapse;
            margin-top: 4pt;
            margin-bottom: 4pt;
            font-size: 11pt;
        }
        table.identitas td { padding: 0 0; vertical-align: top; line-height: 13pt; }
        table.identitas td.label { width: 120px; }
        table.identitas td.antara { width: 14px; }

        /* ---------- TABEL KARTU ---------- */
        table.kartu {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11pt;
            line-height: 12pt;
        }
        /* Seluruh sel master rata tengah, baik kepala maupun data — itulah
           yang ditiru di sini. Satu-satunya pengecualian adalah kolom "No."
           pada kepala dan baris nomor kolom, yang pada master rata kanan. */
        table.kartu th,
        table.kartu td {
            border: 0.75pt solid #000;
            padding: 0 4pt;
            vertical-align: middle;
            text-align: center;
        }

        /* Tinggi baris disamakan dan dibuat ringkas supaya seluruh kartu —
           kepala, dua puluh slot, "dst", Stok Awal dan Stok Akhir — muat pada
           satu halaman lanskap, sama seperti berkas Excel yang mencetak satu
           kartu per halaman (Excel sendiri memuatnya lewat penyekalaan 96%). */
        table.kartu tbody td { height: 13pt; }

        /* Kepala tabel: tebal — mengikuti baris 8 master yang tingginya
           menampung teks terbungkus. */
        table.kartu thead th { font-weight: bold; }

        /* Baris nomor kolom (1)…(8) — baris 9 master, berhuruf 9pt. */
        tr.nomor-kolom td { font-size: 9pt; }

        /* Kolom "No." pada kepala dan baris nomor kolom rata kanan (sel A8
           dan A9 master), meski datanya sendiri rata tengah. */
        th.k-no, tr.nomor-kolom td.k-no { text-align: right; }

        /* Lebar kolom diturunkan dari lebar kolom master (satuan aksara
           Excel), dinormalkan menjadi persen agar perbandingannya sama. */
        .k-no      { width: 5.5%;  }
        .k-nomor   { width: 15.5%; }
        .k-tanggal { width: 13.8%; }
        .k-uraian  { width: 28.2%; }
        .k-masuk   { width: 14.4%; }
        .k-keluar  { width: 11.4%; }
        .k-sisa    { width: 11.2%; }

        /* Sel transaksi kosong tetap setinggi baris terisi, sehingga
           kedua puluh slot tergaris rata seperti kartu fisiknya. */
        td.slot { height: 13pt; }

        /* Baris Stok Awal / Stok Akhir (baris 10 & 32 master, label
           di-merge A:G) — tebal, berlatar kelabu tipis. Labelnya rata
           tengah seperti sel gabungan pada master. */
        tr.saldo td {
            font-weight: bold;
            background: #F2F2F2;
        }

        /* Baris "dst" (baris 31 master): teks pada kolom No., sel lain
           dibiarkan bergaris kosong. Master menulisnya tegak, bukan miring. */

        .pemisah-halaman { page-break-before: always; }
    </style>
</head>
<body>

{{-- Satu tampilan melayani satu kartu maupun sehimpunan kartu: aksi per
     barang pada halaman Kartu Kendali mengirim satu, ekspor mengirim seluruh
     barang pada pilihan yang diberikan. Keduanya memakai tampilan yang sama
     supaya tata letaknya tidak pernah menyimpang. --}}
@foreach ($kartu as $isi)
    @php
        $barang  = $isi['barang'];
        $mutasi  = $isi['mutasi'];
        $tahun   = $isi['tahun'];
        $periode = $isi['periode'];
        $awal    = $isi['awal'];
        $akhir   = $isi['akhir'];

        // Kartu master menyediakan dua puluh slot bergaris. Bila transaksi
        // kurang dari itu, sisanya tetap digambar sebagai slot kosong; bila
        // lebih, seluruhnya digambar dan kartunya memanjang — sama dengan
        // perilaku berkas Excel yang menyisipkan baris sebelum "dst".
        $slotBawaan = 20;
        $kosong     = max(0, $slotBawaan - $mutasi->count());
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
            <td class="antara"></td>
            <td>{{ $barang->kode_lengkap }}</td>
        </tr>
        <tr>
            <td class="label">Nama Barang</td>
            <td class="antara"></td>
            {{-- Berkas master menuliskan nama barang dengan huruf besar
                 seluruhnya; katalog menyimpannya apa adanya. --}}
            <td>{{ \Illuminate\Support\Str::upper($barang->nama_barang) }}</td>
        </tr>
        <tr>
            <td class="label">Satuan</td>
            <td class="antara"></td>
            <td>{{ $barang->satuan }}</td>
        </tr>
        <tr>
            <td class="label">Periode</td>
            <td class="antara"></td>
            <td>{{ $periode }}</td>
        </tr>
    </table>

    <table class="kartu">
        <thead>
            <tr>
                <th class="k-no">No.</th>
                <th class="k-nomor">Nomor Dasar M/K</th>
                <th class="k-tanggal">Tanggal M/K</th>
                <th class="k-uraian">Uraian M/K</th>
                <th class="k-masuk">Masuk (M)</th>
                <th class="k-keluar">Keluar (K)</th>
                <th class="k-sisa">Sisa</th>
            </tr>
            {{-- Baris nomor kolom (1)…(8); nomor (5) dan (9) tidak muncul
                 karena kolom Harga Satuan dan Nilai disembunyikan pada
                 master. --}}
            <tr class="nomor-kolom">
                <td class="k-no">(1)</td>
                <td>(2)</td>
                <td>(3)</td>
                <td>(4)</td>
                <td>(6)</td>
                <td>(7)</td>
                <td>(8)</td>
            </tr>
        </thead>
        <tbody>

            {{-- Stok Awal: label membentang enam kolom pertama (No. sampai
                 Keluar), nilai pada kolom Sisa — mengikuti merge A:G master. --}}
            <tr class="saldo">
                <td class="label" colspan="6">Stok Awal</td>
                <td class="k-sisa">{{ $awal }}</td>
            </tr>

            @foreach ($mutasi as $m)
                <tr>
                    <td class="k-no">{{ $loop->iteration }}</td>
                    <td class="k-nomor">{{ $m->nomor_dasar ?: '' }}</td>
                    <td class="k-tanggal">{{ $m->tanggal?->format('d/m/Y') }}</td>
                    <td class="k-uraian">{{ $m->uraian }}</td>
                    <td class="k-masuk">{{ $m->jumlah > 0 ? $m->jumlah : '' }}</td>
                    <td class="k-keluar">{{ $m->jumlah < 0 ? abs($m->jumlah) : '' }}</td>
                    {{-- Sisa dibaca apa adanya dari saldo_sesudah, tidak
                         dihitung ulang, agar sama persis dengan angka Excel
                         yang bersumber dari data yang sama. --}}
                    <td class="k-sisa">{{ $m->saldo_sesudah }}</td>
                </tr>
            @endforeach

            {{-- Slot kosong hingga genap dua puluh baris. --}}
            @for ($i = 0; $i < $kosong; $i++)
                <tr>
                    <td class="k-no slot">{{ $mutasi->count() + $i + 1 }}</td>
                    <td class="k-nomor"></td>
                    <td class="k-tanggal"></td>
                    <td class="k-uraian"></td>
                    <td class="k-masuk"></td>
                    <td class="k-keluar"></td>
                    <td class="k-sisa"></td>
                </tr>
            @endfor

            {{-- Baris "dst" (baris 31 master). --}}
            <tr>
                <td class="dst">dst</td>
                <td class="k-nomor"></td>
                <td class="k-tanggal"></td>
                <td class="k-uraian"></td>
                <td class="k-masuk"></td>
                <td class="k-keluar"></td>
                <td class="k-sisa"></td>
            </tr>

            {{-- Stok Akhir: sama bentuknya dengan Stok Awal. --}}
            <tr class="saldo">
                <td class="label" colspan="6">Stok Akhir</td>
                <td class="k-sisa">{{ $akhir }}</td>
            </tr>

        </tbody>
    </table>
@endforeach

</body>
</html>
