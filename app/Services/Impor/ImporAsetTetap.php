<?php

namespace App\Services\Impor;

use App\Models\AsetTetap;
use App\Models\Kategori;
use App\Models\Tim;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi berkala satu arah berbasis berkas untuk aset tetap.
 *
 * Akses antarmuka pemrograman ke sistem sumber tidak tersedia, sehingga
 * pemutakhiran ditempuh lewat berkas: data diekspor dari sistem sumber, lalu
 * berkas itu diunggah ke sini. Arahnya satu: sistem sumber menulis ke SIMPBI,
 * tidak sebaliknya. Tidak ada yang berjalan otomatis dan tidak ada yang
 * seketika — berkasnya diunggah ketika pengelola memang hendak memutakhirkan.
 *
 * Yang membedakan ini dari impor biasa ada tiga, dan ketiganya disengaja:
 *
 *  - **Pencocokan pada NUP.** Nomor Urut Pendaftaran adalah penanda aset pada
 *    pencatatan barang milik negara, bersifat unik di basis data, dan sudah
 *    terisi pada seluruh aset yang ada. Mengunggah berkas yang sama dua kali
 *    karena itu memperbarui baris yang sama, bukan melahirkan aset kembar.
 *    `external_id` tidak dipakai mencocokkan: kolomnya boleh kosong, tidak
 *    unik, dan pada data berjalan memang belum terisi sama sekali — memakainya
 *    sebagai kunci berarti seluruh aset lama tidak akan pernah cocok.
 *  - **Provenans tercatat.** `sumber_data`, `external_id`, dan `synced_at`
 *    menyatakan dari mana sebuah baris berasal dan kapan terakhir diselaraskan.
 *  - **Batas kewenangan.** Hanya atribut yang memang dimiliki sistem sumber
 *    yang diperbarui. Yang lahir dari alur kerja SIMPBI tidak disentuh, dan
 *    daftarnya ada pada {@see static::atributSumber()}.
 *
 * Sinkronisasi tidak pernah menghapus. Aset yang tidak muncul pada berkas
 * dibiarkan apa adanya, sebab berkas ekspor bisa saja tersaring sebagian, dan
 * menghapus aset berdasarkan ketidakhadiran akan memusnahkan riwayat
 * penempatan beserta BAST yang menyertainya.
 */
class ImporAsetTetap
{
    public const JUDUL = 'Aset Tetap';

    /** Kondisi aset sebagaimana dikenali berkas sumber. */
    protected const KONDISI = [
        'baik'         => 'baik',
        'rusak ringan' => 'rusak_ringan',
        'rusak_ringan' => 'rusak_ringan',
        'rusak berat'  => 'rusak_berat',
        'rusak_berat'  => 'rusak_berat',
    ];

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        return [
            Kolom::buat(
                kunci: 'nup',
                judul: 'NUP',
                wajib: true,
                contoh: '3.10.01.00001',
                catatan: 'Nomor Urut Pendaftaran aset pada pencatatan barang milik negara. '
                    . 'Inilah kunci pencocokannya: NUP yang sudah ada akan diperbarui, '
                    . 'NUP yang belum ada akan ditambahkan. NUP sendiri tidak pernah diubah.',
            ),
            Kolom::buat(
                kunci: 'nama_aset',
                judul: 'Nama Aset',
                wajib: true,
                contoh: 'Laptop Lenovo ThinkPad E14',
                catatan: 'Nama aset sebagaimana tercatat pada sistem sumber.',
            ),
            Kolom::buat(
                kunci: 'kategori',
                judul: 'Kategori',
                wajib: true,
                contoh: 'Peralatan dan Mesin',
                catatan: 'Harus sudah terdaftar sebagai kategori aset tetap di SIMPBI. '
                    . 'Kategori yang belum dikenali tidak dibuat otomatis — barisnya ditolak '
                    . 'beserta keterangannya, agar katalog kategori tidak tumbuh sendiri '
                    . 'dari salah ketik pada berkas.',
            ),
            Kolom::buat(
                kunci: 'kondisi',
                judul: 'Kondisi',
                contoh: 'Baik',
                catatan: 'Baik, Rusak Ringan, atau Rusak Berat. Dikosongkan berarti kondisi '
                    . 'yang tersimpan tidak diubah; pada aset baru berarti Baik.',
            ),
            Kolom::buat(
                kunci: 'tim_penempatan',
                judul: 'Tim Kerja Penempatan',
                contoh: 'Statistik Sosial',
                catatan: 'Hanya dipakai ketika asetnya belum ada dan sedang ditambahkan. '
                    . 'Pada aset yang sudah tercatat, penempatan tidak pernah diubah dari sini '
                    . 'sebab perpindahan aset antar tim kerja hanya sah melalui BAST mutasi.',
            ),
            Kolom::buat(
                kunci: 'external_id',
                judul: 'ID Sumber',
                contoh: 'BMN-2024-00041',
                catatan: 'Penanda aset pada sistem sumber, disimpan sebagai keterangan asal. '
                    . 'Bukan kunci pencocokan. Dikosongkan berarti tidak diubah.',
            ),
        ];
    }

    /**
     * Menjalankan satu putaran sinkronisasi.
     *
     * Tiap baris dibungkus transaksinya sendiri, mengikuti pola impor tim kerja
     * dan pengguna: baris yang sah tetap masuk meski baris lain bermasalah.
     * Berkas ekspor kerap memuat ratusan aset, dan menahan seluruhnya karena
     * satu kategori salah ketik berarti menunda pemutakhiran yang sebenarnya
     * sudah benar. Baris yang ditolak dilaporkan beserta nomornya.
     */
    public function jalankan(string $lintasanBerkas): HasilImpor
    {
        $hasil = new HasilImpor();
        $baris = app(PembacaBerkas::class)->baca($lintasanBerkas);

        $tajuk = [];
        foreach (static::kolom() as $kolom) {
            $tajuk[$kolom->kunci] = $kolom->tajukSeragam();
        }

        $penempatanDiabaikan = 0;

        foreach ($baris as $b) {
            $ambil = fn (string $kunci): string => trim($b['isi'][$tajuk[$kunci]] ?? '');

            $nup = $ambil('nup');
            if ($nup === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom NUP kosong. NUP wajib diisi karena menjadi kunci pencocokan.');

                continue;
            }

            if (mb_strlen($nup) > 30) {
                $hasil->catatGalat($b['nomor'], 'NUP "' . $nup . '" melebihi 30 aksara.');

                continue;
            }

            $nama = $ambil('nama_aset');
            if ($nama === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom Nama Aset kosong.');

                continue;
            }

            if (mb_strlen($nama) > 150) {
                $hasil->catatGalat($b['nomor'], 'Nama Aset melebihi 150 aksara.');

                continue;
            }

            $namaKategori = $ambil('kategori');
            if ($namaKategori === '') {
                $hasil->catatGalat($b['nomor'], 'Kolom Kategori kosong.');

                continue;
            }

            $kategori = $this->cariKategori($namaKategori);
            if (! $kategori) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kategori "' . $namaKategori . '" belum terdaftar sebagai kategori aset tetap. '
                        . 'Tambahkan kategorinya lebih dulu lewat menu Kategori Barang.',
                );

                continue;
            }

            $kondisi = $this->bacaKondisi($ambil('kondisi'));
            if ($kondisi === false) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kolom Kondisi berisi "' . $ambil('kondisi') . '", yang tidak dikenali. '
                        . 'Isi Baik, Rusak Ringan, atau Rusak Berat.',
                );

                continue;
            }

            $aset = AsetTetap::where('nup', $nup)->first();

            // Tim kerja penempatan hanya berlaku bagi aset yang benar-benar baru.
            $timPenempatan = null;
            $namaTim = $ambil('tim_penempatan');

            if ($aset && $namaTim !== '') {
                // Barisnya tetap diperbarui; yang diabaikan hanya kolom ini.
                // Dicatat agar pengunggah tidak menyangka penempatannya pindah.
                $penempatanDiabaikan++;
            }

            if (! $aset && $namaTim !== '') {
                // Aturan pencocokannya sama dengan kategori di bawah,
                // sehingga satu berkas tidak diperlakukan dengan dua ukuran.
                $timPenempatan = PencocokanNama::samakan(Tim::query(), 'nama_tim', $namaTim)->first();

                if (! $timPenempatan) {
                    $hasil->catatGalat(
                        $b['nomor'],
                        'Tim Kerja "' . $namaTim . '" belum terdaftar. Tambahkan tim kerjanya lebih dulu.',
                    );

                    continue;
                }
            }

            $externalId = $ambil('external_id');
            if (mb_strlen($externalId) > 50) {
                $hasil->catatGalat($b['nomor'], 'ID Sumber melebihi 50 aksara.');

                continue;
            }

            DB::transaction(function () use (
                $aset, $nup, $nama, $kategori, $kondisi, $timPenempatan, $externalId, $hasil, $b
            ): void {
                $atribut = $this->atributSumber($nama, $kategori->id, $kondisi, $externalId);

                if ($aset) {
                    // tim_penempatan_id dan status_aktif sengaja tidak termasuk;
                    // lihat keterangan pada atributSumber().
                    $aset->forceFill($atribut)->save();
                    $hasil->diperbarui++;

                    return;
                }

                // Pencarian $aset di atas dan pembuatan ini bukan satu kesatuan atomik:
                // dua unggahan yang bersamaan persis dapat sama-sama tidak menemukan
                // barisnya lalu sama-sama mencoba membuat. Jaring pengaman ini menolak
                // baris itu dengan pesan yang jelas, bukan menggagalkan seisi berkas.
                try {
                    $baru = AsetTetap::create([
                        'nup'               => $nup,
                        'tim_penempatan_id' => $timPenempatan?->id,
                        'kondisi'           => $kondisi ?: 'baik',
                        'status_aktif'      => true,
                        ...$atribut,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    $hasil->catatGalat($b['nomor'], 'NUP sudah dipakai oleh aset lain.');

                    return;
                }

                /*
                 * Penempatan awal dicatat lewat mekanisme yang sama dengan
                 * penambahan aset dari antarmuka (temuan T-10), bukan dengan
                 * menulis barisnya sendiri di sini. Metodenya sudah menjaga
                 * dirinya: tanpa tim kerja ia tidak membuat apa pun, dan pada
                 * aset yang sudah berriwayat ia menolak menambah baris kedua.
                 */
                $baru->catatPenempatanAwal();

                $hasil->ditambah++;
            });
        }

        if ($penempatanDiabaikan > 0) {
            $hasil->catat(
                'Kolom Tim Kerja Penempatan pada ' . $penempatanDiabaikan . ' baris tidak dipakai '
                . 'karena asetnya sudah tercatat. Perpindahan aset antar tim kerja hanya sah '
                . 'melalui BAST mutasi.'
            );
        }

        return $hasil;
    }

    /**
     * Atribut yang memang dimiliki sistem sumber, dan hanya itu.
     *
     * Yang sengaja tidak ada di sini, beserta alasannya:
     *
     *  - `tim_penempatan_id` pada aset yang sudah tercatat. Penempatan terkini
     *    ditulis oleh pengesahan BAST mutasi bersama satu baris pada riwayat
     *    penempatan. Menimpanya dari berkas akan membuat penempatan dan
     *    riwayatnya saling bertentangan, dan membatalkan BAST yang sudah
     *    disahkan tanpa satu pun jejak.
     *  - `status_aktif`. Di SIMPBI kolom ini adalah kendali pengelola, bukan
     *    keterangan dari sistem sumber: penolakan penghapusan aset justru
     *    mengarahkan pengguna ke sana sebagai jalan keluarnya. Berkas sumber
     *    tidak boleh mengaktifkan kembali aset yang sengaja dinonaktifkan.
     *  - `nup`. Ia kunci pencocokannya; mengubahnya berarti menunjuk aset lain.
     *
     * Nilai yang dikosongkan pada berkas tidak menimpa apa pun. Berkas ekspor
     * tidak selalu memuat seluruh kolom, dan kolom yang tidak dibawa sistem
     * sumber berarti sistem sumber tidak berwenang atasnya — bukan berarti
     * nilainya kosong.
     *
     * @return array<string, mixed>
     */
    protected function atributSumber(
        string $nama,
        int $kategoriId,
        ?string $kondisi,
        string $externalId,
    ): array {
        $atribut = [
            'nama_aset'   => $nama,
            'kategori_id' => $kategoriId,
            // Menyatakan baris ini berasal dari berkas sinkronisasi, bukan
            // diketik seseorang lewat formulir.
            'sumber_data' => 'impor',
            'synced_at'   => now(),
        ];

        if ($kondisi) {
            $atribut['kondisi'] = $kondisi;
        }

        if ($externalId !== '') {
            $atribut['external_id'] = $externalId;
        }

        return $atribut;
    }

    /**
     * Kategori aset tetap yang namanya cocok, tanpa memperhatikan besar kecil
     * huruf.
     *
     * Dibatasi pada kategori bertipe aset tetap, sehingga berkas yang keliru
     * memuat kategori persediaan tertolak alih-alih melahirkan aset pada
     * kategori yang salah.
     */
    protected function cariKategori(string $nama): ?Kategori
    {
        return PencocokanNama::samakan(
            Kategori::query()->where('tipe', 'aset_tetap'),
            'nama_kategori',
            $nama,
        )->first();
    }

    /**
     * Membaca kolom kondisi dengan longgar.
     *
     * Mengembalikan null bila dikosongkan — artinya kondisi yang tersimpan
     * tidak diubah — dan false bila isinya tidak dapat ditafsirkan. Keduanya
     * dibedakan sebab yang satu keadaan wajar dan yang lain kesalahan yang
     * perlu diperbaiki pengisinya.
     */
    protected function bacaKondisi(string $nilai): string|false|null
    {
        $bersih = trim(mb_strtolower($nilai));

        if ($bersih === '') {
            return null;
        }

        return self::KONDISI[$bersih] ?? false;
    }
}
