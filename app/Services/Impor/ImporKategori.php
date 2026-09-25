<?php

namespace App\Services\Impor;

use App\Filament\Resources\Kategoris\Schemas\KategoriForm;
use App\Models\Kategori;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Impor berbasis berkas untuk Kategori Barang.
 *
 * Mengikuti pola impor Tim Kerja, Pengguna, dan Aset Tetap: upsert satu arah,
 * tiap baris dalam transaksinya sendiri, baris yang sah tetap masuk walau baris
 * lain bermasalah, dan tidak pernah menghapus kategori yang tidak muncul pada
 * berkas — kategori menaungi barang dan aset yang sudah tercatat.
 *
 * Kuncinya **kode kategori** (keputusan pemilik G-4): kode itu sudah unik di
 * basis data, menjadi bagian kode lengkap pada kartu kendali, dan tidak
 * berubah ketika ejaan namanya dibetulkan. Nama justru termasuk yang boleh
 * diperbarui lewat impor ulang.
 *
 * Tidak ada kolom provenans (synced_at) pada tabel kategori, dan tidak
 * ditambahkan (G-7); impor ini karena itu mengikuti pola Pengguna, bukan pola
 * Tim Kerja yang mencatat waktu sinkronisasi.
 */
class ImporKategori
{
    public const JUDUL = 'Kategori Barang';

    /** Tipe kategori sebagaimana dikenali berkas, tanpa memperhatikan besar kecil huruf. */
    protected const TIPE = [
        'persediaan' => 'persediaan',
        'aset tetap' => 'aset_tetap',
        'aset_tetap' => 'aset_tetap',
    ];

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        return [
            Kolom::buat(
                kunci: 'kode_kategori',
                judul: 'Kode Kategori',
                wajib: true,
                contoh: '1010301001',
                catatan: 'Kunci pencocokan: kode yang sudah ada diperbarui, kode baru ditambahkan. '
                    . 'Kode kategori sendiri tidak pernah diubah dari impor. Maks. 20 aksara.',
            ),
            Kolom::buat(
                kunci: 'nama_kategori',
                judul: 'Nama Kategori',
                wajib: true,
                contoh: 'Alat Tulis',
                catatan: 'Nama kategori. Maks. 100 aksara. Boleh dibetulkan lewat impor ulang.',
            ),
            Kolom::buat(
                kunci: 'kode_akun',
                judul: 'Kode Akun',
                wajib: true,
                contoh: '117111',
                catatan: 'Kode akun neraca (Bagan Akun Standar), mis. 117111 untuk Barang Konsumsi. '
                    . 'Kategori persediaan: tepat 6 digit angka. Kategori aset tetap: bebas, maks. 10 aksara. '
                    . 'Hanya keterangan; tidak memengaruhi stok, kartu kendali, maupun dokumen.',
            ),
            Kolom::buat(
                kunci: 'tipe',
                judul: 'Tipe',
                wajib: true,
                contoh: 'Persediaan',
                catatan: 'Persediaan atau Aset Tetap. Kategori yang sudah dipakai barang atau aset tidak '
                    . 'dapat berganti tipe dari sini, sebab isinya akan ikut berpindah golongan.',
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

        /** @var array<string,int> $kodeTerbaca kode kategori => nomor baris pertama yang memuatnya */
        $kodeTerbaca = [];

        foreach ($baris as $b) {
            $ambil = fn (string $kunci): string => trim($b['isi'][$tajuk[$kunci]] ?? '');

            $kode = $ambil('kode_kategori');
            if ($kode === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom Kode Kategori kosong. Kode kategori wajib diisi karena menjadi kunci pencocokan.');

                continue;
            }

            if (mb_strlen($kode) > 20) {
                $hasil->catatGalat($b['nomor'], 'Kode Kategori "' . $kode . '" melebihi 20 aksara.');

                continue;
            }

            /*
             * Dua baris berkode sama pada satu berkas ditolak yang kedua. Tanpa
             * ini baris pertama diam-diam tertimpa baris kedua, dan ringkasan
             * impor melaporkan "diperbarui" untuk perubahan yang tidak pernah
             * dimaksudkan pengisinya.
             */
            if (isset($kodeTerbaca[$kode])) {
                $hasil->catatGalat($b['nomor'], 'Kode Kategori ' . $kode . ' sudah dipakai baris ' . $kodeTerbaca[$kode] . ' pada berkas ini.');

                continue;
            }

            $nama = $ambil('nama_kategori');
            if ($nama === '' || mb_strlen($nama) > 100) {
                $hasil->catatGalat($b['nomor'], $nama === '' ? 'Kolom Nama Kategori kosong.' : 'Nama Kategori melebihi 100 aksara.');

                continue;
            }

            $tipe = $this->bacaTipe($ambil('tipe'));
            if ($tipe === null) {
                $hasil->catatGalat($b['nomor'], 'Kolom Tipe berisi "' . $ambil('tipe') . '", yang tidak dikenali. Isi Persediaan atau Aset Tetap.');

                continue;
            }

            $kodeAkun = $ambil('kode_akun');
            if ($kodeAkun === '' || mb_strlen($kodeAkun) > 10) {
                $hasil->catatGalat($b['nomor'], $kodeAkun === '' ? 'Kolom Kode Akun kosong.' : 'Kode Akun melebihi 10 aksara.');

                continue;
            }

            // Ukurannya sama dengan form (KategoriForm), keputusan pemilik G-2.
            if ($tipe === 'persediaan' && preg_match(KategoriForm::POLA_KODE_AKUN_PERSEDIAAN, $kodeAkun) !== 1) {
                $hasil->catatGalat($b['nomor'], 'Kode Akun "' . $kodeAkun . '" tidak sah. ' . KategoriForm::PESAN_KODE_AKUN_PERSEDIAAN);

                continue;
            }

            $kodeTerbaca[$kode] = $b['nomor'];

            DB::transaction(function () use ($kode, $nama, $kodeAkun, $tipe, $hasil, $b): void {
                $kategori = Kategori::where('kode_kategori', $kode)->first();

                if ($kategori) {
                    /*
                     * Berganti tipe hanya boleh selama kategorinya belum dipakai.
                     * Kategori persediaan yang sudah menaungi barang, bila menjadi
                     * aset tetap, akan menghilangkan barangnya dari seluruh
                     * ringkasan stok tanpa satu pun barang yang dipindahkan.
                     */
                    if ($kategori->tipe !== $tipe && $kategori->punyaRiwayat()) {
                        $hasil->catatGalat(
                            $b['nomor'],
                            'Kategori ' . $kode . ' sudah dipakai barang persediaan atau aset tetap, sehingga tipenya '
                                . 'tidak dapat diubah dari impor.',
                        );

                        return;
                    }

                    $kategori->forceFill(['nama_kategori' => $nama, 'kode_akun' => $kodeAkun, 'tipe' => $tipe])->save();
                    $hasil->diperbarui++;

                    return;
                }

                // Pencarian di atas dan pembuatan ini bukan satu kesatuan atomik;
                // dua unggahan bersamaan ditolak dengan pesan, bukan galat mentah.
                try {
                    Kategori::create([
                        'kode_kategori' => $kode,
                        'nama_kategori' => $nama,
                        'kode_akun'     => $kodeAkun,
                        'tipe'          => $tipe,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $hasil->catatGalat($b['nomor'], KategoriForm::PESAN_KODE_DIPAKAI);

                    return;
                }

                $hasil->ditambah++;
            });
        }

        return $hasil;
    }

    /** Tipe yang dikenali, atau null bila kosong maupun tidak dapat ditafsirkan. */
    protected function bacaTipe(string $nilai): ?string
    {
        return self::TIPE[mb_strtolower(trim($nilai))] ?? null;
    }
}
