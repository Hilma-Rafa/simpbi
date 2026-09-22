<?php

namespace App\Models;

use App\Models\Concerns\DilindungiRiwayat;
use Illuminate\Database\Eloquent\Model;

class Kategori extends Model
{
    use DilindungiRiwayat;

    protected $table = 'kategori';
    protected $guarded = [];

    /**
     * Kategori yang masih dipakai tidak boleh dihapus.
     *
     * Dua kunci asing merujuk kategori — `barang_persediaan.kategori_id` dan
     * `aset_tetap.kategori_id` — dan keduanya menolak penghapusan. Penolakannya
     * benar, tetapi bentuknya galat basis data mentah di layar, bukan
     * keterangan yang dapat dibaca penggunanya.
     *
     * Tidak ada data yang terancam hilang di sini: batasan basis data justru
     * bekerja sebagaimana mestinya. Yang diperbaiki adalah cara sistem
     * mengatakannya.
     *
     * Nama metodenya mengikuti trait yang sama dengan tabel induk lain; pada
     * kategori maknanya "masih dipakai", bukan "punya riwayat" — kategori
     * memang bukan sesuatu yang berjalan sendiri.
     */
    public function punyaRiwayat(): bool
    {
        return BarangPersediaan::where('kategori_id', $this->id)->exists()
            || AsetTetap::where('kategori_id', $this->id)->exists();
    }

    public function barang()
    {
        return $this->hasMany(BarangPersediaan::class, 'kategori_id');
    }
}