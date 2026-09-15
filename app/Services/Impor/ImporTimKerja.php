<?php

namespace App\Services\Impor;

use App\Models\Tim;
use Illuminate\Support\Facades\DB;

/**
 * Impor daftar tim kerja dari berkas sebar.
 *
 * Tim kerja adalah data induk yang paling jarang berubah tetapi paling banyak
 * dirujuk — pengguna, permintaan, dan mutasi aset semuanya menunjuk ke sini.
 * Karena itu impornya mencocokkan berdasarkan nama, bukan membuat baris baru
 * setiap kali: mengimpor ulang berkas yang sama tidak boleh melahirkan tim
 * kembar yang kemudian memecah anggotanya ke dua tempat.
 *
 * Ketua tim tidak diatur di sini. Ketua adalah seorang pengguna, sedangkan
 * pengguna menunjuk ke tim — mengaturnya dari kedua arah sekaligus membuat
 * urutan impor jadi penting dan mudah salah. Penetapan ketua karena itu
 * diserahkan kepada impor pengguna, yang memang sudah mengenal keduanya.
 */
class ImporTimKerja
{
    public const JUDUL = 'Tim Kerja';

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        return [
            Kolom::buat(
                kunci: 'nama_tim',
                judul: 'Nama Tim',
                wajib: true,
                contoh: 'Statistik Pertambangan, Energi dan Konstruksi (PEK)',
                catatan: 'Nama resmi tim kerja. Tulis lengkap beserta akronim resminya dalam kurung '
                    . 'bila ada — akronim itulah yang dipakai sistem pada tabel yang sempit.',
            ),
            Kolom::buat(
                kunci: 'status_aktif',
                judul: 'Aktif',
                contoh: 'Ya',
                catatan: 'Ya atau Tidak. Dikosongkan berarti Ya. Tim yang tidak aktif tetap '
                    . 'tersimpan beserta riwayatnya, hanya tidak dapat dipilih pada permintaan baru.',
            ),
        ];
    }

    public function jalankan(string $lintasanBerkas): HasilImpor
    {
        $hasil = new HasilImpor();
        $baris = app(PembacaBerkas::class)->baca($lintasanBerkas);

        $kolomNama   = Kolom::buat('nama_tim', 'Nama Tim')->tajukSeragam();
        $kolomAktif  = Kolom::buat('status_aktif', 'Aktif')->tajukSeragam();

        /*
         * Baris yang sah tetap masuk meski ada baris lain yang bermasalah.
         * Data induk diisi bertahap, dan memaksa seluruh berkas sempurna
         * sebelum satu pun baris diterima berarti kesalahan ketik pada satu
         * tim menahan tujuh tim lainnya. Baris yang ditolak dilaporkan
         * beserta nomornya, sehingga perbaikannya terarah.
         */
        foreach ($baris as $b) {
            $nama = trim($b['isi'][$kolomNama] ?? '');

            if ($nama === '') {
                $hasil->catatGalat($b['nomor'], 'Nama Tim kosong.');

                continue;
            }

            if (mb_strlen($nama) > 100) {
                $hasil->catatGalat($b['nomor'], 'Nama Tim melebihi 100 aksara.');

                continue;
            }

            $aktif = $this->bacaYaTidak($b['isi'][$kolomAktif] ?? '');

            if ($aktif === null) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kolom Aktif berisi "' . $b['isi'][$kolomAktif] . '", yang tidak dikenali. Isi Ya atau Tidak.'
                );

                continue;
            }

            DB::transaction(function () use ($nama, $aktif, $hasil): void {
                $tim = Tim::where('nama_tim', $nama)->first();

                if ($tim) {
                    $tim->update(['status_aktif' => $aktif]);
                    $hasil->diperbarui++;

                    return;
                }

                Tim::create(['nama_tim' => $nama, 'status_aktif' => $aktif]);
                $hasil->ditambah++;
            });
        }

        return $hasil;
    }

    /**
     * Membaca kolom ya/tidak dengan longgar.
     *
     * Berkas yang diisi banyak orang akan memuat "Ya", "ya", "Y", "Aktif",
     * "1", dan "TRUE" untuk maksud yang sama. Menolak semuanya kecuali satu
     * bentuk hanya memindahkan pekerjaan ke pengisinya tanpa menambah
     * ketelitian apa pun. Kosong berarti aktif, sebab itulah keadaan lazimnya.
     *
     * Mengembalikan null bila isinya benar-benar tidak dapat ditafsirkan —
     * menebak di titik itu justru berbahaya, sebab menonaktifkan tim tanpa
     * disadari akan menyembunyikannya dari seluruh formulir.
     */
    protected function bacaYaTidak(string $nilai): ?bool
    {
        $bersih = trim(mb_strtolower($nilai));

        if ($bersih === '') {
            return true;
        }

        if (in_array($bersih, ['ya', 'y', 'aktif', '1', 'true', 'benar'], true)) {
            return true;
        }

        if (in_array($bersih, ['tidak', 't', 'nonaktif', 'tidak aktif', '0', 'false', 'salah'], true)) {
            return false;
        }

        return null;
    }
}
