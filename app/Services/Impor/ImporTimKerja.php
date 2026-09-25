<?php

namespace App\Services\Impor;

use App\Models\Tim;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi berkala satu arah berbasis berkas untuk tim kerja.
 *
 * Arahnya satu: berkas yang diekspor dari sistem sumber diunggah ke sini,
 * tidak sebaliknya. Tidak ada yang berjalan otomatis dan tidak ada yang
 * seketika. Sinkronisasi juga tidak pernah menghapus — tim kerja yang tidak
 * muncul pada berkas dibiarkan apa adanya, sebab namanya melekat pada
 * permintaan dan BAST yang sudah terbit.
 *
 * Pencocokannya memakai nama tim, bukan `external_id`. Nama tim adalah
 * satu-satunya penanda yang benar-benar terisi pada data berjalan, sedangkan
 * `external_id` boleh kosong dan tidak unik — memakainya sebagai kunci berarti
 * tidak satu pun tim lama akan pernah cocok. `external_id` tetap disimpan
 * sebagai keterangan asal, dan `synced_at` mencatat kapan barisnya terakhir
 * diselaraskan.
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
                kunci: 'external_id',
                judul: 'ID Sumber',
                contoh: 'TIM-2024-03',
                catatan: 'Penanda tim kerja pada sistem sumber, disimpan sebagai keterangan asal. '
                    . 'Bukan kunci pencocokan — yang dicocokkan tetap Nama Tim. '
                    . 'Dikosongkan berarti tidak diubah.',
                format: Kolom::TEKS,
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

        $kolomNama     = Kolom::buat('nama_tim', 'Nama Tim')->tajukSeragam();
        $kolomAktif    = Kolom::buat('status_aktif', 'Aktif')->tajukSeragam();
        $kolomExternal = Kolom::buat('external_id', 'ID Sumber')->tajukSeragam();

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

            $externalId = trim($b['isi'][$kolomExternal] ?? '');

            if (mb_strlen($externalId) > 50) {
                $hasil->catatGalat($b['nomor'], 'ID Sumber melebihi 50 aksara.');

                continue;
            }

            /*
             * Provenans ditulis pada baris baru maupun baris yang diperbarui.
             * `synced_at` menyatakan kapan barisnya terakhir diselaraskan, dan
             * itulah yang dibaca kolom "Tersinkron" pada tabel Tim Kerja.
             * `external_id` hanya ditimpa ketika berkas benar-benar membawanya:
             * kolom yang dikosongkan berarti sistem sumber tidak menyertakan
             * penandanya, bukan berarti penanda yang tersimpan harus dihapus.
             */
            $provenans = ['synced_at' => now()];

            if ($externalId !== '') {
                $provenans['external_id'] = $externalId;
            }

            DB::transaction(function () use ($nama, $aktif, $provenans, $hasil): void {
                // Pencocokan tidak peka besar kecil huruf: berkas sumber kerap
                // menuliskan nama tim dengan kapitalisasi yang berbeda, dan
                // menganggapnya nama lain akan melahirkan tim kembar.
                $tim = PencocokanNama::samakan(Tim::query(), 'nama_tim', $nama)->first();

                if ($tim) {
                    // Ketua tim dan kolom operasional lain tidak disentuh;
                    // penetapan ketua tetap milik impor pengguna.
                    $tim->forceFill(['status_aktif' => $aktif, ...$provenans])->save();
                    $hasil->diperbarui++;

                    return;
                }

                Tim::create(['nama_tim' => $nama, 'status_aktif' => $aktif, ...$provenans]);
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
