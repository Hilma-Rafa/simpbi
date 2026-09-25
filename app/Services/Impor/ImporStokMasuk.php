<?php

namespace App\Services\Impor;

use App\Filament\Pages\StokMasuk;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Models\MutasiStok;
use App\Services\StokService;
use App\Support\KodeBarang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Impor Stok Masuk: satu berkas, satu jenis transaksi, banyak baris.
 *
 * Menggantikan Impor Stok Awal. Jenis transaksi dipilih sekali di pop-up dan
 * berlaku untuk seluruh berkas; nilainya diambil dari daftar sumber Catat
 * Stok Masuk manual (StokMasuk::SUMBER_MASUK) apa adanya, sebab kartu kendali
 * membaca sumber yang sama (uraian lewat MutasiStok::URAIAN, saldo pembawaan
 * lewat sumber `stok_awal`). Tanggal, nomor dasar, dan keterangan ikut per baris,
 * karena satu berkas memindahkan banyak dokumen sekaligus.
 *
 * Setiap baris sah dicatat lewat StokService::tambah() — jalur yang sama persis
 * dengan Catat Stok Masuk manual — sehingga stok fisik, buku besar mutasi,
 * kolom Sisa, dan kartu kendali tetap satu cerita. Stok fisik tidak pernah
 * ditulis langsung.
 *
 * Aturan tiap isian mengikuti Catat Stok Masuk manual, tanpa aturan baru:
 * tanggal tidak boleh melewati hari ini, Nomor Dasar wajib untuk sumber yang
 * lahir dari dokumen (StokMasuk::SUMBER_WAJIB_NOMOR_DASAR) dan paling banyak 60
 * aksara, keterangan paling banyak 255 aksara, jumlah minimal 1, dan hanya
 * barang aktif yang dapat menerima stok. Jumlah di sini dibatasi bilangan
 * bulat, sebab isian angka pada formulir manual pun hanya menyimpan bagian
 * bulatnya.
 *
 * Stok Awal punya dua pengaman tambahan. Barang yang sudah memiliki stok atau
 * riwayat transaksi ditolak (diperiksa di dalam transaksi pada baris yang
 * terkunci), karena stok awal hanya bermakna sekali; dan satu barang tidak
 * boleh muncul dua kali pada satu berkas. Jenis lain boleh memuat barang yang
 * sama berkali-kali, tetapi baris dengan barang, Nomor Dasar, dan Tanggal yang
 * sama persis ditolak sebagai kemungkinan input ganda.
 */
class ImporStokMasuk
{
    public const JUDUL = 'Stok Masuk';

    /** Catatan jenis Stok Awal; dipakai pop-up dan lembar Petunjuk agar kalimatnya satu. */
    public const CATATAN_STOK_AWAL = [
        'Kolom Tanggal boleh dikosongkan; sistem memakai 1 Januari tahun berjalan.',
        'Tanggal 1 Januari membuat angka tampil sebagai Stok Awal pada kartu kendali tahun itu. Untuk go-live di '
            . 'tengah tahun, isi tanggal go-live; angka akan tampil sebagai baris transaksi Stok Awal.',
        'Stok awal hanya untuk barang yang belum punya stok maupun riwayat transaksi; tambahan stok berikutnya '
            . 'dicatat dengan jenis lain.',
    ];

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        $wajibNomor = collect(StokMasuk::SUMBER_WAJIB_NOMOR_DASAR)->map(fn (string $s) => StokMasuk::SUMBER_MASUK[$s])->join(' dan ');

        return [
            Kolom::buat(
                kunci: 'kode_kategori',
                judul: 'Kode Kategori',
                wajib: true,
                contoh: '1010301001',
                catatan: 'Kode kategori persediaan barangnya, sesuai menu Kategori Barang.',
                format: Kolom::TEKS,
            ),
            Kolom::buat(
                kunci: 'kode_barang',
                judul: 'Kode Barang',
                wajib: true,
                contoh: KodeBarang::CONTOH,
                catatan: 'Tepat 6 digit angka sesuai kartu kendali, termasuk nol di depan. Barangnya harus sudah '
                    . 'terdaftar dan aktif di Barang Persediaan; impor ini tidak membuat barang baru.',
                format: Kolom::TEKS,
            ),
            Kolom::buat(
                kunci: 'nama_barang',
                judul: 'Nama Barang',
                contoh: 'BINDER CLIPS NO. 105',
                catatan: 'Hanya pembantu membaca berkas. Tidak dipakai mencocokkan dan tidak mengubah nama barang.',
            ),
            Kolom::buat(
                kunci: 'jumlah',
                judul: 'Jumlah',
                wajib: true,
                contoh: '34',
                catatan: 'Bilangan bulat lebih dari 0. Untuk Stok Awal dapat diambil dari kolom Sisa pada baris '
                    . 'Stok Akhir kartu kendali lama.',
            ),
            Kolom::buat(
                kunci: 'tanggal',
                judul: 'Tanggal',
                contoh: '01/01/' . now()->year,
                catatan: 'Tanggal dokumen, DD/MM/YYYY, tidak boleh melewati hari ini. Wajib untuk semua jenis kecuali '
                    . 'Stok Awal; pada Stok Awal kosong berarti 1 Januari tahun berjalan.',
                format: Kolom::TANGGAL,
            ),
            Kolom::buat(
                kunci: 'nomor_dasar',
                judul: 'Nomor Dasar',
                contoh: '',
                catatan: 'Nomor dokumen pengadaan atau berita acara, maks. 60 aksara. Wajib untuk ' . $wajibNomor
                    . '; boleh dikosongkan untuk jenis lain.',
                format: Kolom::TEKS,
            ),
            Kolom::buat(
                kunci: 'keterangan',
                judul: 'Keterangan',
                contoh: '',
                catatan: 'Opsional, maks. 255 aksara, sama dengan kolom Keterangan pada Catat Stok Masuk.',
            ),
        ];
    }

    /** Baris keterangan tambahan pada lembar Petunjuk template. @return list<string> */
    public static function petunjuk(): array
    {
        return [
            'Jenis transaksi dipilih di pop-up Impor Stok Masuk, bukan di berkas, dan berlaku untuk seluruh baris. '
                . 'Jenis yang tersedia: ' . implode(', ', StokMasuk::SUMBER_MASUK) . '.',
            'Jangan mengisi apa pun, termasuk tanggal, pada baris yang tidak dipakai; baris yang hanya berisi '
                . 'tanggal terbaca sebagai data dan ditolak.',
            'Selain Stok Awal, kolom Tanggal wajib diisi pada setiap baris.',
            ...array_map(fn (string $c) => 'Stok Awal: ' . $c, self::CATATAN_STOK_AWAL),
        ];
    }

    public function jalankan(string $lintasanBerkas, string $jenis, int $petugasId): HasilImpor
    {
        $hasil = new HasilImpor();

        // Pilihan pop-up sudah dibatasi Select, tetapi muatan dapat dimodifikasi;
        // sumber di luar daftar Catat Stok Masuk tidak pernah dicatat.
        if (! isset(StokMasuk::SUMBER_MASUK[$jenis])) {
            $hasil->galat[] = 'Jenis transaksi "' . $jenis . '" tidak dikenali.';

            return $hasil;
        }

        $stokAwal = $jenis === 'stok_awal';
        $tajuk = $this->tajuk();

        /** @var array<string,int> $terbaca kunci baris => nomor baris pertama */
        $terbaca = [];
        /** @var list<int> $bukanAwalTahun nomor baris Stok Awal tercatat yang tidak bertanggal 1 Januari */
        $bukanAwalTahun = [];

        foreach (app(PembacaBerkas::class)->baca($lintasanBerkas) as $b) {
            $baris = $this->periksaBaris($this->normalkan($b, $tajuk), $b['nomor'], $jenis, $stokAwal, $hasil);

            if ($baris === null || $this->ganda($baris, $terbaca, $stokAwal, $hasil)) {
                continue;
            }

            if ($this->catat($baris, $jenis, $stokAwal, $petugasId, $hasil) && $stokAwal && ! $this->awalTahun($baris['tanggal'])) {
                $bukanAwalTahun[] = $b['nomor'];
            }
        }

        if ($bukanAwalTahun !== []) {
            $hasil->catat(
                'Baris ' . implode(', ', $bukanAwalTahun) . ' bertanggal selain 1 Januari; stok awalnya tampil sebagai '
                . 'baris transaksi Stok Awal pada kartu kendali, bukan pada kolom Stok Awal.'
            );
        }

        return $hasil;
    }

    /**
     * Tajuk seragam tiap kolom template.
     *
     * @return array<string,string> kunci kolom => tajuk seragam
     */
    protected function tajuk(): array
    {
        $tajuk = [];
        foreach (static::kolom() as $kolom) {
            $tajuk[$kolom->kunci] = $kolom->tajukSeragam();
        }

        return $tajuk;
    }

    /**
     * Isi satu baris berkas menurut kunci kolom, sudah dipangkas; kolom yang
     * tidak ada pada berkas dibaca sebagai teks kosong.
     *
     * @param  array{nomor:int,isi:array<string,string>}  $b
     * @param  array<string,string>  $tajuk
     * @return array<string,string>
     */
    protected function normalkan(array $b, array $tajuk): array
    {
        return array_map(fn (string $t): string => trim($b['isi'][$t] ?? ''), $tajuk);
    }

    /**
     * Seluruh pemeriksaan satu baris, dalam urutan yang tetap: barang, jumlah
     * dan tanggal, lalu nomor dasar dan keterangan. Mengembalikan data siap
     * dicatat, atau null bila baris ditolak (galatnya sudah dicatat).
     *
     * @param  array<string,string>  $isi
     * @return array{nomor:int,barang:BarangPersediaan,kodeBarang:string,jumlah:int,tanggal:Carbon,nomorDasar:string,keterangan:string}|null
     */
    protected function periksaBaris(array $isi, int $nomor, string $jenis, bool $stokAwal, HasilImpor $hasil): ?array
    {
        $barang = $this->periksaBarang($isi, $nomor, $hasil);
        if (! $barang) {
            return null;
        }

        $tanggal = $this->periksaJumlahTanggal($isi, $nomor, $jenis, $stokAwal, $hasil);
        if (! $tanggal) {
            return null;
        }

        if (! $this->periksaNomorKeterangan($isi, $nomor, $jenis, $hasil)) {
            return null;
        }

        return [
            'nomor'      => $nomor,
            'barang'     => $barang,
            'kodeBarang' => $isi['kode_barang'],
            'jumlah'     => (int) $isi['jumlah'],
            'tanggal'    => $tanggal,
            'nomorDasar' => $isi['nomor_dasar'],
            'keterangan' => $isi['keterangan'],
        ];
    }

    /**
     * Kode wajib, kode barang 6 digit, barang terdaftar pada kategori
     * persediaan, dan barang aktif.
     *
     * @param  array<string,string>  $isi
     */
    protected function periksaBarang(array $isi, int $nomor, HasilImpor $hasil): ?BarangPersediaan
    {
        $kodeKategori = $isi['kode_kategori'];
        $kodeBarang   = $isi['kode_barang'];

        if ($kodeKategori === '' || $kodeBarang === '') {
            $hasil->catatGalat($nomor, 'Kode Kategori dan Kode Barang wajib diisi.');

            return null;
        }

        if ($pesan = KodeBarang::pesanGalatImpor($kodeBarang)) {
            $hasil->catatGalat($nomor, $pesan);

            return null;
        }

        $kategori = Kategori::where('kode_kategori', $kodeKategori)->where('tipe', 'persediaan')->first();
        $barang = $kategori
            ? BarangPersediaan::where('kategori_id', $kategori->id)->where('kode_barang', $kodeBarang)->first()
            : null;

        if (! $barang) {
            $hasil->catatGalat(
                $nomor,
                'Barang ' . $kodeKategori . ' / ' . $kodeBarang . ' belum terdaftar sebagai barang persediaan. '
                    . 'Daftarkan barangnya lebih dulu di menu Barang Persediaan.',
            );

            return null;
        }

        if (! $barang->status_aktif) {
            $hasil->catatGalat($nomor, 'Barang ' . $kodeBarang . ' nonaktif, sehingga tidak dapat menerima stok.');

            return null;
        }

        return $barang;
    }

    /**
     * Jumlah bilangan bulat > 0 dan tanggal (langkah 6). Mengembalikan tanggal
     * yang dipakai, atau null bila baris ditolak.
     *
     * @param  array<string,string>  $isi
     */
    protected function periksaJumlahTanggal(array $isi, int $nomor, string $jenis, bool $stokAwal, HasilImpor $hasil): ?Carbon
    {
        $jumlah = $isi['jumlah'];
        if (! ctype_digit($jumlah) || (int) $jumlah < 1) {
            $hasil->catatGalat($nomor, 'Jumlah berisi "' . $jumlah . '". Isi bilangan bulat lebih dari 0.');

            return null;
        }

        $tanggal = $this->bacaTanggal($isi['tanggal']);
        if ($tanggal === false) {
            $hasil->catatGalat($nomor, 'Tanggal "' . $isi['tanggal'] . '" tidak sah. Tulis tanggal sebagai DD/MM/YYYY.');

            return null;
        }

        if ($tanggal === null && ! $stokAwal) {
            $hasil->catatGalat($nomor, 'Tanggal wajib diisi untuk jenis ' . StokMasuk::SUMBER_MASUK[$jenis] . '.');

            return null;
        }

        // Stok Awal tanpa tanggal: 1 Januari tahun berjalan, sehingga angkanya
        // tampil sebagai Stok Awal kartu kendali tahun ini.
        $tanggal ??= Carbon::create(now()->year, 1, 1);

        // Aturan Catat Stok Masuk: transaksi tidak dicatat mendahului kejadiannya.
        if ($tanggal->gt(now()->startOfDay())) {
            $hasil->catatGalat($nomor, 'Tanggal ' . $tanggal->format('d/m/Y') . ' melewati hari ini.');

            return null;
        }

        return $tanggal;
    }

    /**
     * Nomor Dasar mengikuti aturan Catat Stok Masuk; panjang Nomor Dasar dan
     * Keterangan dibatasi.
     *
     * @param  array<string,string>  $isi
     */
    protected function periksaNomorKeterangan(array $isi, int $nomor, string $jenis, HasilImpor $hasil): bool
    {
        $nomorDasar = $isi['nomor_dasar'];
        if ($nomorDasar === '' && in_array($jenis, StokMasuk::SUMBER_WAJIB_NOMOR_DASAR, true)) {
            $hasil->catatGalat($nomor, 'Nomor Dasar wajib diisi untuk jenis ' . StokMasuk::SUMBER_MASUK[$jenis] . '.');

            return false;
        }

        if (mb_strlen($nomorDasar) > 60 || mb_strlen($isi['keterangan']) > 255) {
            $hasil->catatGalat($nomor, mb_strlen($nomorDasar) > 60 ? 'Nomor Dasar melebihi 60 aksara.' : 'Keterangan melebihi 255 aksara.');

            return false;
        }

        return true;
    }

    /**
     * Stok Awal: satu barang satu kali per berkas. Jenis lain: barang yang sama
     * boleh berulang, tetapi baris yang sama persis barang, nomor dasar, dan
     * tanggalnya hampir pasti tersalin dua kali. Mengembalikan true bila baris
     * ditolak sebagai ganda; bila tidak, barisnya dicatat sebagai terbaca.
     *
     * @param  array{nomor:int,barang:BarangPersediaan,kodeBarang:string,jumlah:int,tanggal:Carbon,nomorDasar:string,keterangan:string}  $baris
     * @param  array<string,int>  $terbaca
     */
    protected function ganda(array $baris, array &$terbaca, bool $stokAwal, HasilImpor $hasil): bool
    {
        $kunci = $stokAwal
            ? (string) $baris['barang']->id
            : $baris['barang']->id . '|' . $baris['nomorDasar'] . '|' . $baris['tanggal']->toDateString();

        if (isset($terbaca[$kunci])) {
            $hasil->catatGalat(
                $baris['nomor'],
                $stokAwal
                    ? 'Barang ' . $baris['kodeBarang'] . ' sudah dipakai baris ' . $terbaca[$kunci] . ' pada berkas ini.'
                    : 'Barang, Nomor Dasar, dan Tanggal sama persis dengan baris ' . $terbaca[$kunci] . '; kemungkinan input ganda.',
            );

            return true;
        }

        $terbaca[$kunci] = $baris['nomor'];

        return false;
    }

    /**
     * Mencatat satu baris lewat StokService::tambah(), di dalam transaksi.
     * Mengembalikan true bila tercatat.
     *
     * @param  array{nomor:int,barang:BarangPersediaan,kodeBarang:string,jumlah:int,tanggal:Carbon,nomorDasar:string,keterangan:string}  $baris
     */
    protected function catat(array $baris, string $jenis, bool $stokAwal, int $petugasId, HasilImpor $hasil): bool
    {
        return DB::transaction(function () use ($baris, $jenis, $stokAwal, $petugasId, $hasil): bool {
            /*
             * Pengaman stok ganda Stok Awal diperiksa pada baris barang yang
             * terkunci, sehingga dua unggahan bersamaan tidak dapat sama-sama
             * lolos: yang kedua melihat mutasi yang baru dibuat yang pertama.
             */
            if ($stokAwal) {
                $terkunci = BarangPersediaan::lockForUpdate()->find($baris['barang']->id);

                if ($terkunci->stok_fisik > 0 || MutasiStok::where('barang_id', $baris['barang']->id)->exists()) {
                    $hasil->catatGalat(
                        $baris['nomor'],
                        'Barang ' . $baris['kodeBarang'] . ' sudah memiliki stok atau transaksi. Stok awal hanya untuk barang '
                            . 'yang belum pernah memiliki stok; tambahan stok dicatat dengan jenis lain.',
                    );

                    return false;
                }
            }

            // Galat saldo dari buku besar (mis. saldo negatif karena tanggal
            // mundur) menjadi galat baris, bukan galat mentah.
            try {
                app(StokService::class)->tambah(
                    barangId: $baris['barang']->id,
                    jumlah: $baris['jumlah'],
                    sumber: $jenis,
                    nomorDasar: $baris['nomorDasar'] !== '' ? $baris['nomorDasar'] : null,
                    keterangan: $baris['keterangan'] !== '' ? $baris['keterangan'] : null,
                    petugasId: $petugasId,
                    tanggal: $baris['tanggal']->toDateString(),
                );
            } catch (InvalidArgumentException $e) {
                $hasil->catatGalat($baris['nomor'], $e->getMessage());

                return false;
            }

            $hasil->ditambah++;

            return true;
        });
    }

    /** Apakah tanggal jatuh pada 1 Januari (tampil sebagai kolom Stok Awal kartu kendali). */
    protected function awalTahun(Carbon $tanggal): bool
    {
        return $tanggal->month === 1 && $tanggal->day === 1;
    }

    /**
     * Tanggal dari berkas: Carbon bila sah, null bila kosong, false bila tidak sah.
     *
     * Sel tanggal Excel sampai dari PembacaBerkas sebagai teks Y-m-d; teks yang
     * diketik diterima dalam bentuk DD/MM/YYYY. Tanggal mustahil (31/02/2026)
     * ditolak, bukan digeser ke bulan berikutnya.
     */
    protected function bacaTanggal(string $nilai): Carbon|false|null
    {
        if ($nilai === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m) === 1) {
            [$tahun, $bulan, $hari] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $nilai, $m) === 1) {
            [$hari, $bulan, $tahun] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return false;
        }

        return checkdate($bulan, $hari, $tahun) ? Carbon::create($tahun, $bulan, $hari)->startOfDay() : false;
    }
}
