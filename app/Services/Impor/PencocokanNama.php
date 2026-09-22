<?php

namespace App\Services\Impor;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Pencocokan data induk berdasarkan namanya, tanpa memperhatikan besar kecil
 * huruf maupun spasi berlebih di ujung.
 *
 * Berkas ekspor diisi dan diolah banyak tangan sebelum sampai ke sini, dan
 * nama yang sama kerap tiba dalam bentuk yang berbeda: "Statistik Sosial",
 * "STATISTIK SOSIAL", atau "statistik sosial ". Pada SQLite pembandingan
 * `=` bersifat biner, sehingga ketiganya dianggap tiga nama yang berlainan —
 * dan akibatnya bukan sekadar baris yang tertolak, melainkan **tim kerja
 * kembar** yang lahir dari berkas yang sebenarnya menunjuk tim yang sudah ada.
 * Sinkronisasi tidak pernah menghapus, sehingga kembaran itu menetap dan
 * permintaan berikutnya dapat menunjuk tim yang keliru.
 *
 * Aturannya dikumpulkan di satu tempat karena dipakai tiga kali — nama tim
 * kerja pada dua pengimpor, dan nama kategori pada pengimpor aset. Ketika
 * ketiganya menormalkan dengan caranya sendiri, satu di antaranya cepat atau
 * lambat tertinggal, dan gejalanya hanya muncul pada berkas tertentu di
 * basis data tertentu.
 *
 * Yang diseragamkan hanya **pembandingnya**. Nama yang tersimpan tidak pernah
 * diubah menjadi huruf kecil: yang tampil pada tabel, dokumen, dan kartu tetap
 * ejaan resminya.
 */
class PencocokanNama
{
    /**
     * Menambahkan syarat "namanya sama" pada sebuah kueri.
     *
     * Urutan berdasarkan id ditambahkan supaya hasilnya tetap dapat diramalkan
     * seandainya basis data terlanjur memuat dua baris yang hanya berbeda
     * kapitalisasi: yang tercatat lebih dulu selalu yang dipilih, bukan baris
     * yang kebetulan lebih dahulu dikembalikan.
     *
     * @param  string  $kolom  nama kolom, selalu tetapan dari kode sendiri
     */
    public static function samakan(Builder $kueri, string $kolom, string $nilai): Builder
    {
        // Nama kolom dirangkai ke dalam SQL mentah, sehingga bentuknya dibatasi
        // pada pengenal biasa. Nilainya sendiri tetap lewat parameter terikat.
        if (preg_match('/^[a-z_]+$/', $kolom) !== 1) {
            throw new InvalidArgumentException('Nama kolom tidak sah: ' . $kolom);
        }

        return $kueri
            ->whereRaw('LOWER(' . $kolom . ') = ?', [static::seragamkan($nilai)])
            ->orderBy('id');
    }

    /** Bentuk seragam sebuah nama untuk keperluan pembandingan. */
    public static function seragamkan(string $nilai): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nilai) ?? $nilai));
    }
}
