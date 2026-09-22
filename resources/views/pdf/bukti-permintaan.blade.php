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
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td {
            vertical-align: top;
            text-align: center;
            font-size: 11pt;
        }
        /*
         * Dua pihak pertama tidak lagi berbagi halaman rata dua. Kolom selebar
         * separuh memusatkan isinya pada seperempat dan tigaperempat halaman,
         * sehingga keduanya tampak tertarik ke tengah dan ruang di tepi kiri
         * dan kanan menganggur. Kolom penyekat di antaranya mendorong masing-
         * masing ke seperlima dan empatperlima halaman — letak yang lazim pada
         * naskah dinas.
         */
        .kol-pihak  { width: 40%; }
        .kol-sekat  { width: 20%; }

        .ruang-ttd { height: 78px; }
        /* Tinggi dikunci, lebar mengikuti — goresan kanvas bernisbah 3:1
           sehingga lebarnya tetap di dalam kolom selebar separuh halaman. */
        .ttd-gambar { height: 72px; margin-top: 3px; }

        .nama-ttd {
            font-weight: bold;
            text-decoration: underline;
            white-space: nowrap;
        }

        /* ---------- PENGESAHAN ---------- */
        /* Jarak ke baris di atasnya memberi blok ini napas sendiri, bukan
           sekadar menyambung ekor dua pihak pertama. */
        .pengesahan { padding-top: 30px; }
        .ettd-jabatan { line-height: 1.35; }

        /*
         * Kode QR diberi ruang bertinggi tetap, sebagaimana ruang tanda tangan
         * basah di atasnya. Sebelumnya kodenya dibungkus kotak berposisi
         * `relative` dengan tepi `auto`; DomPDF menyusun kotak semacam itu di
         * luar aliran barisnya, sehingga kode menindih tulisan "Kepala Sub
         * Bagian Umum" tepat di atasnya. Gambar biasa di dalam ruang bertinggi
         * tetap tidak punya cara untuk keluar dari barisnya.
         *
         * Ukuran gambarnya menghitung zona sunyi empat modul di tepi kode, jadi
         * modul gelapnya sendiri tercetak selebar enam puluh poin — sekitar
         * seperlima lebih kecil daripada sebelumnya, dan tidak lagi mendesak
         * tulisan di atas maupun di bawahnya.
         */
        .ruang-ettd { height: 94px; }
        .qr-ettd {
            width: 86px;
            height: 86px;
            margin-top: 4px;
        }

        /*
         * Catatan kaki dipaku ke dasar halaman, bukan dibiarkan mengalir
         * sesudah blok tanda tangan. Sebagai isi yang mengalir, letaknya
         * mengikuti panjang daftar barang — pada surat berisi satu barang ia
         * berhenti seratus empat puluh delapan poin di atas tepi bawah kertas
         * dan tampak mengambang di tengah halaman.
         *
         * `position: fixed` pada DomPDF mengacu pada kotak halaman, sehingga
         * catatan ini duduk pada tempat yang sama berapa pun panjang isinya.
         */
        .catatan-kaki {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            /* Tanpa tata letak tetap, DomPDF melebarkan kolom tulisan
               mengikuti isinya dan lebar yang ditetapkan di bawah diabaikan. */
            table-layout: fixed;
        }
        /* Rata bawah, meniru dokumen acuan: dasar tulisan sejajar dasar kode,
           bukan mengambang di tengah tingginya. */
        .catatan-kaki td { vertical-align: bottom; }
        /* Lebar ditulis sebagai persentase, bukan piksel: pada tata letak
           tetap DomPDF mengabaikan lebar berpiksel dan membagi sisa halaman
           rata, sehingga tulisan terlempar enam puluh poin dari kodenya. */
        .ck-qr { width: 7.5%; }
        /* Modul gelapnya setara 34pt, ukuran kode pada catatan kaki dokumen
           acuan; selebihnya zona sunyi. Lebih kecil dari ini modulnya turun di
           bawah seperempat milimeter ketika dicetak dan pemindai ponsel mulai
           kehilangan jejaknya. */
        .ck-qr img { width: 48px; height: 48px; }
        /*
         * Lebar kolom tulisan ditahan di bawah lebar halaman supaya kalimat
         * pertama patah menjadi dua baris. Dibiarkan selebar halaman, ia
         * menjadi satu baris sepanjang kertas yang terbaca sebagai kalimat
         * dokumen, bukan sebagai catatan kaki.
         */
        .ck-teks {
            padding-left: 3px;
            width: 64%;
            font-size: 6.5pt;
            font-style: italic;
            line-height: 1.5;
            color: #6E6D6D;
        }
        /* Baris sambungan menjorok sejajar tulisan, bukan sejajar tanda
           bintangnya, sehingga penandanya tetap menonjol di tepi kiri. */
        .ck-teks p {
            margin: 0;
            padding-left: 7px;
            text-indent: -7px;
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
        <td class="kol-pihak">
            Yang Menyerahkan,<br>
            Petugas Gudang
            {{-- Tanda tangan menempati ruang yang sebelumnya dibiarkan kosong
                 untuk tanda tangan basah, sehingga tinggi barisnya tidak
                 berubah baik dokumen ini bertanda tangan maupun tidak. --}}
            <div class="ruang-ttd">
                @if ($ttdPenyerah)
                    <img class="ttd-gambar" src="{{ $ttdPenyerah }}" alt="">
                @endif
            </div>
            <span class="nama-ttd">{{ $penyerah ?? '.....................................' }}</span>
        </td>
        <td class="kol-sekat"></td>
        <td class="kol-pihak">
            Yang Menerima,<br>
            Ketua Tim {{ $permintaan->tim?->nama_tim ?? 'Pemohon' }}
            <div class="ruang-ttd">
                @if ($ttdPenerima)
                    <img class="ttd-gambar" src="{{ $ttdPenerima }}" alt="">
                @endif
            </div>
            <span class="nama-ttd">{{ $penerima ?? '.....................................' }}</span>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="pengesahan">
            <div class="ettd-jabatan">Mengetahui,<br>Kepala Sub Bagian Umum</div>

            {{-- Kode QR inilah tanda tangan elektronik Kasubbag, berdiri
                 sendiri tanpa bingkai maupun keterangan — sebagaimana e-TTD
                 pada naskah dinas: kodenya sendiri yang menjadi tanda, bukan
                 kotak yang mengelilinginya. Lambang BPS sudah menyatu di dalam
                 kodenya, bukan ditumpangkan dari sini. --}}
            <div class="ruang-ettd">
                @if ($qr)
                    <img class="qr-ettd" src="{{ $qr }}" alt="Kode verifikasi">
                @endif
            </div>

            <span class="nama-ttd">{{ $pengesah ?? '.....................................' }}</span>
        </td>
    </tr>
</table>

{{-- ==================== CATATAN KAKI ====================
     Bentuknya mengikuti dokumen ber-TTE yang dijadikan acuan: kode QR kecil
     merapat ke sudut kiri bawah, keterangan di sampingnya. Penerbit sertifikat
     yang disebut adalah sistem ini sendiri, sebab dokumen ini memang tidak
     ditandatangani lewat penyelenggara sertifikasi mana pun. --}}
<table class="catatan-kaki">
    <tr>
        <td class="ck-qr">
            @if ($qrFootnote ?? null)
                <img src="{{ $qrFootnote }}" alt="Kode menuju berkas asli">
            @endif
        </td>
        <td class="ck-teks">
            <p>* Dokumen ini telah disahkan secara elektronik melalui Sistem Informasi Manajemen Permintaan Barang dan Inventaris.</p>
            <p>* Pindai kode QR di samping untuk menampilkan file asli</p>
        </td>
        {{-- Kolom penyisa: menampung sisa lebar halaman supaya tata letak
             tetap tidak membagikannya kembali ke kolom tulisan. --}}
        <td></td>
    </tr>
</table>

</body>
</html>