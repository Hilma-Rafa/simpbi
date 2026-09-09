<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangPersediaan extends Model
{
    protected $table = 'barang_persediaan';
    protected $guarded = [];

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function mutasi()
    {
        return $this->hasMany(MutasiStok::class, 'barang_id');
    }

    /**
     * Kode barang dalam penulisan resmi kartu kendali, contoh
     * 1.01.03.01.001.000122.
     *
     * Kode kategori disimpan tanpa titik (1010301001) karena itulah bentuk
     * yang dipakai laporan persediaan BPS, sedangkan kartu kendali menuliskan
     * dengan pemenggalan 1-2-2-2-3 lalu diikuti kode barang. Pemenggalan
     * dilakukan di sini, bukan disimpan sebagai kolom tersendiri, agar tidak
     * ada dua sumber kebenaran untuk satu kode yang sama.
     */
    public function getKodeLengkapAttribute(): string
    {
        $kodeKategori = $this->kategori?->kode_kategori;

        if (! $kodeKategori) {
            return $this->kode_barang;
        }

        // Kode kategori di luar sepuluh angka, misalnya kode aset tetap "PM",
        // dibiarkan apa adanya daripada dipenggal menjadi bentuk yang keliru.
        if (! preg_match('/^\d{10}$/', $kodeKategori)) {
            return $kodeKategori . '.' . $this->kode_barang;
        }

        $bagian = [
            substr($kodeKategori, 0, 1),
            substr($kodeKategori, 1, 2),
            substr($kodeKategori, 3, 2),
            substr($kodeKategori, 5, 2),
            substr($kodeKategori, 7, 3),
            $this->kode_barang,
        ];

        return implode('.', $bagian);
    }

    // Stok yang dapat diminta = stok fisik dikurangi stok yang sedang dikunci
    public function getStokTersediaAttribute(): int
    {
        return $this->stok_fisik - $this->stok_hold;
    }

    public function isMenipis(): bool
    {
        return $this->stok_minimum > 0 && $this->stok_fisik <= $this->stok_minimum;
    }
}