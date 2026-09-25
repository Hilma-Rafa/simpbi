<?php

namespace App\Services\Impor;

use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\StokService;
use App\Support\KodeBarang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Impor stok awal untuk barang yang belum pernah memiliki stok.
 *
 * Berbeda dengan impor data induk, impor ini **memang mengubah stok** — tetapi
 * hanya lewat jalur yang sama dengan Catat Stok Masuk: setiap baris sah dicatat
 * oleh StokService::tambah() bersumber Stok Awal. Dengan begitu stok fisik,
 * buku besar mutasi, kolom Sisa, dan kartu kendali tetap satu cerita; stok
 * fisik tidak pernah ditulis langsung dari berkas.
 *
 * Kegunaannya satu: memindahkan saldo dari kartu kendali lama (kolom Sisa pada
 * baris Stok Akhir) ke SIMPBI tanpa mengetik satu nota per barang.
 *
 * Pengaman terpentingnya menolak barang yang sudah memiliki stok fisik atau
 * baris mutasi apa pun. Stok awal hanya bermakna sekali, sebelum barangnya
 * bergerak; mengunggah berkas yang sama untuk kedua kalinya tidak boleh
 * menggandakan stok, dan tambahan stok sesudahnya adalah Stok Masuk biasa yang
 * punya nomor dokumennya sendiri.
 *
 * Aturan tanggal dan nomor dasar mengikuti Catat Stok Masuk bersumber Stok
 * Awal, tanpa aturan baru: tanggal wajib dan tidak boleh melewati hari ini,
 * nomor dasar boleh kosong (maks. 60 aksara). Aturan "tidak mendahului
 * transaksi terakhir" tidak relevan di sini, sebab barang yang sudah punya
 * transaksi memang ditolak. Barang nonaktif ditolak karena Catat Stok Masuk
 * juga tidak menawarkannya.
 */
class ImporStokAwal
{
    public const JUDUL = 'Stok Awal';

    public const KETERANGAN = 'Stok awal dari impor berkas';

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        return [
            Kolom::buat(
                kunci: 'kode_kategori',
                judul: 'Kode Kategori',
                wajib: true,
                contoh: '1010301001',
                catatan: 'Kode kategori persediaan barangnya, sesuai menu Kategori Barang.',
            ),
            Kolom::buat(
                kunci: 'kode_barang',
                judul: 'Kode Barang',
                wajib: true,
                contoh: KodeBarang::CONTOH,
                catatan: 'Tepat 6 digit angka sesuai kartu kendali, termasuk nol di depan. Format selnya sebagai '
                    . 'Teks (atau awali dengan tanda petik \'). Barangnya harus sudah terdaftar di Barang '
                    . 'Persediaan; impor ini tidak membuat barang baru.',
            ),
            Kolom::buat(
                kunci: 'nama_barang',
                judul: 'Nama Barang',
                contoh: 'BINDER CLIPS NO. 105',
                catatan: 'Hanya pembantu membaca berkas. Tidak dipakai mencocokkan dan tidak mengubah nama barang.',
            ),
            Kolom::buat(
                kunci: 'jumlah',
                judul: 'Jumlah Stok Awal',
                wajib: true,
                contoh: '34',
                catatan: 'Bilangan bulat lebih dari 0. Dapat diambil dari kolom Sisa pada baris Stok Akhir kartu '
                    . 'kendali lama. Hanya untuk barang yang belum pernah memiliki stok; tambahan stok '
                    . 'sesudahnya dicatat lewat Catat Stok Masuk. Tanggal stok awal 1 Januari membuat angka '
                    . 'ini tampil sebagai Stok Awal kartu kendali tahun itu.',
            ),
        ];
    }

    public function jalankan(string $lintasanBerkas, string $tanggal, ?string $nomorDasar, int $petugasId): HasilImpor
    {
        $hasil = new HasilImpor();

        // Aturan Catat Stok Masuk (maxDate(now())) ditegakkan ulang di peladen,
        // sebab isian dialog dapat dilewati muatan yang dikirim langsung.
        if (Carbon::parse($tanggal)->startOfDay()->gt(now()->startOfDay())) {
            $hasil->galat[] = 'Tanggal stok awal tidak boleh melewati hari ini.';

            return $hasil;
        }

        $nomorDasar = filled($nomorDasar) ? trim($nomorDasar) : null;

        if ($nomorDasar !== null && mb_strlen($nomorDasar) > 60) {
            $hasil->galat[] = 'Nomor Dasar melebihi 60 aksara.';

            return $hasil;
        }

        $baris = app(PembacaBerkas::class)->baca($lintasanBerkas);

        $tajuk = [];
        foreach (static::kolom() as $kolom) {
            $tajuk[$kolom->kunci] = $kolom->tajukSeragam();
        }

        /** @var array<int,int> $barangTerbaca barang_id => nomor baris pertama */
        $barangTerbaca = [];

        foreach ($baris as $b) {
            $ambil = fn (string $kunci): string => trim($b['isi'][$tajuk[$kunci]] ?? '');

            $kodeKategori = $ambil('kode_kategori');
            $kodeBarang   = $ambil('kode_barang');

            if ($kodeKategori === '' || $kodeBarang === '') {
                $hasil->catatGalat($b['nomor'], 'Kode Kategori dan Kode Barang wajib diisi.');

                continue;
            }

            if ($pesan = KodeBarang::pesanGalatImpor($kodeBarang)) {
                $hasil->catatGalat($b['nomor'], $pesan);

                continue;
            }

            $kategori = Kategori::where('kode_kategori', $kodeKategori)->where('tipe', 'persediaan')->first();
            $barang = $kategori
                ? BarangPersediaan::where('kategori_id', $kategori->id)->where('kode_barang', $kodeBarang)->first()
                : null;

            if (! $barang) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Barang ' . $kodeKategori . ' / ' . $kodeBarang . ' belum terdaftar sebagai barang persediaan. '
                        . 'Daftarkan barangnya lebih dulu di menu Barang Persediaan.',
                );

                continue;
            }

            if (! $barang->status_aktif) {
                $hasil->catatGalat($b['nomor'], 'Barang ' . $kodeBarang . ' nonaktif, sehingga tidak dapat menerima stok.');

                continue;
            }

            $jumlah = $ambil('jumlah');
            if (! ctype_digit($jumlah) || (int) $jumlah < 1) {
                $hasil->catatGalat($b['nomor'], 'Jumlah Stok Awal berisi "' . $jumlah . '". Isi bilangan bulat lebih dari 0.');

                continue;
            }

            if (isset($barangTerbaca[$barang->id])) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Barang ' . $kodeKategori . ' / ' . $kodeBarang . ' sudah dipakai baris ' . $barangTerbaca[$barang->id] . ' pada berkas ini.',
                );

                continue;
            }

            $barangTerbaca[$barang->id] = $b['nomor'];

            DB::transaction(function () use ($barang, $jumlah, $tanggal, $nomorDasar, $petugasId, $hasil, $b, $kodeBarang): void {
                /*
                 * Pengaman stok ganda diperiksa di dalam transaksi, pada baris
                 * barang yang terkunci. Dua unggahan berkas yang sama secara
                 * bersamaan karena itu tidak dapat sama-sama lolos: yang kedua
                 * melihat baris mutasi yang baru saja dibuat yang pertama.
                 */
                $terkunci = BarangPersediaan::lockForUpdate()->find($barang->id);

                if ($terkunci->stok_fisik > 0 || MutasiStok::where('barang_id', $barang->id)->exists()) {
                    $hasil->catatGalat(
                        $b['nomor'],
                        'Barang ' . $kodeBarang . ' sudah memiliki stok atau transaksi. Stok awal hanya untuk barang '
                            . 'yang belum pernah memiliki stok; tambahan stok dicatat lewat Catat Stok Masuk.',
                    );

                    return;
                }

                try {
                    app(StokService::class)->tambah(
                        barangId: $barang->id,
                        jumlah: (int) $jumlah,
                        sumber: 'stok_awal',
                        nomorDasar: $nomorDasar,
                        keterangan: self::KETERANGAN,
                        petugasId: $petugasId,
                        tanggal: Carbon::parse($tanggal)->toDateString(),
                    );
                } catch (InvalidArgumentException $e) {
                    $hasil->catatGalat($b['nomor'], $e->getMessage());

                    return;
                }

                $hasil->ditambah++;
            });
        }

        return $hasil;
    }
}
