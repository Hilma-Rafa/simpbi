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

        /* ---------- BLOK TANDA TANGAN ----------
           Susunan, ukuran, dan jaraknya mengikuti dokumen bukti permintaan:
           dua pihak berhadapan di atas, pengesah di tengah bawah. Sebelumnya
           ketiganya berjajar dalam tiga kolom sama lebar, sehingga kolom
           pengesah — satu-satunya yang benar-benar bertanda tangan — berdesak
           di tepi kanan alih-alih menjadi penutup dokumen. */
        .ttd { width: 100%; margin-top: 36px; }
        .ttd td { vertical-align: top; text-align: center; font-size: 11pt; }
        .kol-pihak { width: 40%; }
        .kol-sekat { width: 20%; }

        /* Ruang tanda tangan basah, tingginya dikunci seperti pada bukti
           permintaan sehingga tetap ada baik dokumen ini bertanda tangan
           maupun tidak. Baris peran dibiarkan mengalir apa adanya — tinggi
           tetap yang dulu dipasang padanya memampatkan blok ini sekitar empat
           setengah poin dibanding acuannya. */
        .ruang-ttd { height: 78px; }
        /* Tanpa `white-space: nowrap` seperti pada acuan: nama pihak di sini
           diketik bebas sampai seratus aksara, dan nama sepanjang itu akan
           menerobos tepi halaman bila dilarang patah. */
        .nama-ttd { font-weight: bold; text-decoration: underline; }

        /* ---------- PENGESAHAN ---------- */
        .pengesahan { padding-top: 30px; }
        .ettd-jabatan { line-height: 1.35; }

        /* e-TTD: satu gambar kode QR yang lambangnya sudah menyatu di dalamnya.
           Sebelumnya lambang ditumpangkan sebagai gambar kedua berlatar putih
           di atas kodenya — sebuah stiker, bukan bagian dari kode: modul di
           bawahnya masih dianggap ada, letaknya dipatri dalam piksel, dan
           tingginya dipaksa sama dengan lebarnya sehingga lambangnya gepeng.

           Ukurannya kini sama persis dengan bukti permintaan, sudah termasuk
           zona sunyi empat modul di tepinya. */
        .ruang-ettd { height: 94px; }
        .qr-ettd { width: 86px; height: 86px; margin-top: 4px; }

        .placeholder-ttd { font-size: 9pt; color: #777; font-style: italic; padding-top: 30px; }

        /* ---------- CATATAN KAKI ----------
           Sama dengan bukti permintaan: dipaku ke dasar halaman, kode kecil di
           kiri, keterangan di kanannya. Menggantikan paragraf tengah yang dulu
           mengalir sesudah blok tanda tangan dan karena itu letaknya berubah-
           ubah mengikuti panjang alasan mutasi. */
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
        .catatan-kaki td { vertical-align: bottom; }
        /* Lebar ditulis sebagai persentase, bukan piksel: pada tata letak tetap
           DomPDF mengabaikan lebar berpiksel dan membagi sisa halaman rata. */
        .ck-qr { width: 7.5%; }
        .ck-qr img { width: 48px; height: 48px; }
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
            <tr><th>Tim Kerja Asal</th><td>{{ $bast->timAsal?->nama_tim ?? '-' }}</td></tr>
            <tr><th>Tim Kerja Tujuan</th><td>{{ $bast->timTujuan?->nama_tim ?? '-' }}</td></tr>
            <tr><th>Alasan Mutasi</th><td>{{ $bast->alasan_mutasi }}</td></tr>
        </table>

        <p>
            Demikian berita acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.
        </p>
    </div>

    {{-- ================= TANDA TANGAN ================= --}}
    <table class="ttd">
        <tr>
            {{-- Jabatan kedua pihak dibaca dari relasi tim, bukan dikarang:
                 kolom pihak penyerah dan penerima hanyalah teks bebas yang
                 diketik operator, sehingga jabatannya tidak tersimpan di mana
                 pun. Yang pasti diketahui sistem adalah unit asal dan unit
                 tujuan asetnya. --}}
            <td class="kol-pihak">
                Yang Menyerahkan,<br>
                Tim Kerja {{ $bast->timAsal?->nama_tim ?? '-' }}
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $bast->pihak_penyerah }}</span>
            </td>
            <td class="kol-sekat"></td>
            <td class="kol-pihak">
                Yang Menerima,<br>
                Ketua Tim {{ $bast->timTujuan?->nama_tim ?? '-' }}
                <div class="ruang-ttd"></div>
                <span class="nama-ttd">{{ $bast->pihak_penerima }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="pengesahan">
                <div class="ettd-jabatan">Mengetahui,<br>Kepala Sub Bagian Umum</div>

                {{-- Kode QR inilah tanda tangan elektronik Kasubbag, berdiri
                     sendiri tanpa bingkai maupun keterangan — sebagaimana e-TTD
                     pada naskah dinas: kodenya sendiri yang menjadi tanda. --}}
                <div class="ruang-ettd">
                    @if ($bast->disahkan_at && $qr)
                        <img class="qr-ettd" src="{{ $qr }}" alt="Kode verifikasi">
                    @else
                        <div class="placeholder-ttd">(menunggu pengesahan)</div>
                    @endif
                </div>

                <span class="nama-ttd">{{ $bast->disahkanOleh?->name ?? '..............................' }}</span>
            </td>
        </tr>
    </table>

    {{-- ================= CATATAN KAKI =================
         Hanya muncul pada BAST yang sudah disahkan: pada yang belum, kalimat
         "telah ditandatangani secara elektronik" belum benar.

         Baris kedua berbunyi "memeriksa keaslian dokumen", bukan "menampilkan
         file asli" seperti pada bukti permintaan, sebab kode ini menuju halaman
         verifikasi — BAST tidak punya berkas yang terbuka tanpa masuk sistem,
         dan menjanjikan berkas yang tidak akan muncul lebih buruk daripada
         menyebut apa adanya. --}}
    @if ($bast->disahkan_at && ($qrFootnote ?? ''))
        <table class="catatan-kaki">
            <tr>
                <td class="ck-qr">
                    <img src="{{ $qrFootnote }}" alt="Kode verifikasi keaslian">
                </td>
                <td class="ck-teks">
                    <p>* Dokumen ini telah ditandatangani secara elektronik menggunakan sertifikat elektronik yang diterbitkan oleh Sistem Informasi Manajemen Permintaan Barang dan Inventaris.</p>
                    <p>* Pindai kode QR di samping untuk memeriksa keaslian dokumen</p>
                </td>
                {{-- Kolom penyisa: menampung sisa lebar halaman supaya tata
                     letak tetap tidak membagikannya kembali ke kolom tulisan. --}}
                <td></td>
            </tr>
        </table>
    @endif

</body>
</html>
