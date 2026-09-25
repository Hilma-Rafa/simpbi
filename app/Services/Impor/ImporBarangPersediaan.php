<?php

namespace App\Services\Impor;

use App\Models\BarangPersediaan;
use App\Models\Kategori;
use App\Support\KodeBarang;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Impor berbasis berkas untuk Barang Persediaan — data induknya saja.
 *
 * **Impor ini tidak pernah menyentuh stok** (keputusan pemilik G-5):
 * `stok_fisik`, `stok_hold`, dan tabel `mutasi_stok` tidak ditulis dalam
 * keadaan apa pun. Stok hanya berubah lewat Stok Masuk dan alur permintaan,
 * karena hanya jalur itulah yang menerbitkan baris kartu kendali. Stok yang
 * ditulis dari berkas akan terbaca oleh StokService::saldoAwalTersirat()
 * sebagai saldo pembawaan tanpa satu pun transaksi yang menjelaskannya,
 * sehingga kolom Sisa pada kartu kendali bergeser. Barang baru karena itu lahir
 * berstok nol, persis seperti barang dari dialog Barang Baru di Stok Masuk, dan
 * isinya dicatat Petugas Gudang sebagai Stok Masuk bersumber Stok Awal.
 *
 * Kuncinya kode kategori + kode barang (G-4), sama dengan indeks unik
 * (kategori_id, kode_barang). Kategori dirujuk dengan kodenya, bukan namanya,
 * karena itulah yang tercetak pada kartu kendali dan tidak ambigu.
 *
 * Status aktif tidak diatur dari sini (G-6): barang baru selalu aktif, dan
 * menonaktifkan barang tetap keputusan pengelola lewat form — seperti
 * status aktif aset tetap yang juga tidak ditimpa sinkronisasi.
 *
 * Tidak pernah menghapus; tanpa kolom provenans (G-7).
 */
class ImporBarangPersediaan
{
    public const JUDUL = 'Barang Persediaan';

    /**
     * Tajuk (bentuk seragam) yang menandakan berkas membawa angka stok.
     * Kolomnya tidak dibaca; keberadaannya hanya dilaporkan sebagai catatan.
     */
    protected const TAJUK_STOK = ['stok', 'stok fisik', 'stok_fisik', 'stok terkunci', 'stok_hold', 'stok hold', 'jumlah stok', 'stok awal'];

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        return [
            Kolom::buat(
                kunci: 'kode_kategori',
                judul: 'Kode Kategori',
                wajib: true,
                contoh: '1010301001',
                catatan: 'Harus sudah terdaftar sebagai kategori persediaan di menu Kategori Barang. '
                    . 'Kategori tidak dibuat otomatis, agar katalog kategori tidak tumbuh sendiri dari salah ketik.',
            ),
            Kolom::buat(
                kunci: 'kode_barang',
                judul: 'Kode Barang',
                wajib: true,
                contoh: KodeBarang::CONTOH,
                catatan: 'Tepat 6 digit angka sesuai kartu kendali persediaan, termasuk nol di depan. '
                    . 'Format selnya sebagai Teks (atau awali dengan tanda petik \'), sebab Excel membuang '
                    . 'nol di depan pada sel angka. Bersama Kode Kategori menjadi kunci pencocokan: '
                    . 'barang yang sudah ada diperbarui, barang baru ditambahkan.',
            ),
            Kolom::buat(
                kunci: 'nama_barang',
                judul: 'Nama Barang',
                wajib: true,
                contoh: 'BINDER CLIPS NO. 105',
                catatan: 'Maks. 150 aksara.',
            ),
            Kolom::buat(
                kunci: 'satuan',
                judul: 'Satuan',
                wajib: true,
                contoh: 'Dus',
                catatan: 'Maks. 20 aksara, mis. Pcs, Dus, Lusin, Rim.',
            ),
            Kolom::buat(
                kunci: 'stok_minimum',
                judul: 'Stok Minimum',
                contoh: '0',
                catatan: 'Bilangan bulat 0 atau lebih; 0 berarti tidak dipantau. Dikosongkan berarti 0 pada '
                    . 'barang baru dan tidak diubah pada barang lama. Stok fisik TIDAK diisi dari berkas ini: '
                    . 'barang baru lahir berstok 0 dan stoknya dicatat lewat Stok Masuk.',
            ),
        ];
    }

    public function jalankan(string $lintasanBerkas): HasilImpor
    {
        $hasil = new HasilImpor();
        $baris = app(PembacaBerkas::class)->baca($lintasanBerkas);

        $tajuk = [];
        foreach (static::kolom() as $kolom) {
            $tajuk[$kolom->kunci] = $kolom->tajukSeragam();
        }

        /** @var array<string,int> $barangTerbaca "kategori_id|kode_barang" => nomor baris pertama */
        $barangTerbaca = [];

        /** @var array<string,?Kategori> $kategoriDikenal tembolok kategori per kode dalam satu putaran */
        $kategoriDikenal = [];

        foreach ($baris as $b) {
            $ambil = fn (string $kunci): string => trim($b['isi'][$tajuk[$kunci]] ?? '');

            $kodeKategori = $ambil('kode_kategori');
            if ($kodeKategori === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom Kode Kategori kosong.');

                continue;
            }

            $kategori = $kategoriDikenal[$kodeKategori] ??= Kategori::where('kode_kategori', $kodeKategori)->first();

            // Kategori aset tetap ditolak seperti kategori yang tidak ada: barang
            // persediaan yang bernaung di sana hilang dari seluruh ringkasan stok.
            if (! $kategori || $kategori->tipe !== 'persediaan') {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kategori ' . $kodeKategori . ' belum terdaftar sebagai kategori persediaan. '
                        . 'Tambahkan lewat menu Kategori Barang.',
                );

                continue;
            }

            $kodeBarang = $ambil('kode_barang');
            if ($kodeBarang === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom Kode Barang kosong.');

                continue;
            }

            if ($pesan = KodeBarang::pesanGalatImpor($kodeBarang)) {
                $hasil->catatGalat($b['nomor'], $pesan);

                continue;
            }

            $nama = $ambil('nama_barang');
            if ($nama === '' || mb_strlen($nama) > 150) {
                $hasil->catatGalat($b['nomor'], $nama === '' ? 'Kolom Nama Barang kosong.' : 'Nama Barang melebihi 150 aksara.');

                continue;
            }

            $satuan = $ambil('satuan');
            if ($satuan === '' || mb_strlen($satuan) > 20) {
                $hasil->catatGalat($b['nomor'], $satuan === '' ? 'Kolom Satuan kosong.' : 'Satuan melebihi 20 aksara.');

                continue;
            }

            $stokMinimum = $this->bacaStokMinimum($ambil('stok_minimum'));
            if ($stokMinimum === false) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kolom Stok Minimum berisi "' . $ambil('stok_minimum') . '". Isi bilangan bulat 0 atau lebih.',
                );

                continue;
            }

            // Baris kedua untuk barang yang sama ditolak, alih-alih menimpa
            // baris pertama tanpa sepengetahuan pengisinya.
            $kunci = $kategori->id . '|' . $kodeBarang;
            if (isset($barangTerbaca[$kunci])) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Barang ' . $kodeKategori . ' / ' . $kodeBarang . ' sudah dipakai baris ' . $barangTerbaca[$kunci] . ' pada berkas ini.',
                );

                continue;
            }

            $barangTerbaca[$kunci] = $b['nomor'];

            DB::transaction(function () use ($kategori, $kodeBarang, $nama, $satuan, $stokMinimum, $hasil, $b): void {
                $barang = BarangPersediaan::where('kategori_id', $kategori->id)
                    ->where('kode_barang', $kodeBarang)
                    ->first();

                // Hanya kolom data induk. stok_fisik, stok_hold, dan status_aktif
                // sengaja tidak ada di sini; lihat keterangan kelas.
                $atribut = ['nama_barang' => $nama, 'satuan' => $satuan];

                if ($stokMinimum !== null) {
                    $atribut['stok_minimum'] = $stokMinimum;
                }

                if ($barang) {
                    $barang->forceFill($atribut)->save();
                    $hasil->diperbarui++;

                    return;
                }

                try {
                    BarangPersediaan::create([
                        'kategori_id'  => $kategori->id,
                        'kode_barang'  => $kodeBarang,
                        ...$atribut,
                        'stok_minimum' => $stokMinimum ?? 0,
                        // Barang baru selalu lahir berstok nol (G-5).
                        'stok_fisik'   => 0,
                        'stok_hold'    => 0,
                        'status_aktif' => true,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $hasil->catatGalat($b['nomor'], 'Kode barang sudah dipakai pada kategori ini.');

                    return;
                }

                $hasil->ditambah++;
            });
        }

        $kolomStok = array_values(array_intersect(self::TAJUK_STOK, array_keys($baris[0]['isi'] ?? [])));

        if ($kolomStok !== []) {
            $hasil->catat(
                'Kolom ' . implode(', ', $kolomStok) . ' diabaikan. Stok hanya berubah lewat Stok Masuk dan alur '
                . 'permintaan agar saldo kartu kendali tetap benar; barang baru lahir berstok 0.'
            );
        }

        return $hasil;
    }

    /**
     * Stok minimum dari berkas: null bila dikosongkan (tidak diubah), false
     * bila isinya bukan bilangan bulat 0 atau lebih.
     */
    protected function bacaStokMinimum(string $nilai): int|false|null
    {
        if ($nilai === '') {
            return null;
        }

        return ctype_digit($nilai) ? (int) $nilai : false;
    }
}
