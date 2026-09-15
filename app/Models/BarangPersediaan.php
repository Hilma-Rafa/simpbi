<?php

namespace App\Models;

use App\Models\Concerns\DilindungiRiwayat;
use Illuminate\Database\Eloquent\Model;

class BarangPersediaan extends Model
{
    use DilindungiRiwayat;

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
     * Barang yang sudah pernah bergerak tidak boleh dihapus.
     *
     * Kunci asing `mutasi_stok.barang_id` memakai penghapusan berantai,
     * sehingga menghapus barangnya ikut menghapus seluruh buku besar
     * mutasinya — padahal buku besar itulah satu-satunya sumber Kartu Kendali,
     * dokumen yang menjadi keluaran utama sistem ini dan bahan rekonsiliasi
     * Sub-Bagian Umum. Kehilangannya tidak dapat dipulihkan kecuali dari
     * cadangan.
     *
     * Barang yang tidak dipakai lagi dinonaktifkan lewat kolom `status_aktif`,
     * bukan dihapus, sehingga riwayatnya tetap utuh dan kartunya tetap dapat
     * diterbitkan untuk periode-periode sebelumnya.
     */
    public function punyaRiwayat(): bool
    {
        return $this->mutasi()->exists();
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