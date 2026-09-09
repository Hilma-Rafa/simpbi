<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiStok extends Model
{
    /**
     * Label kolom "Uraian M/K" pada kartu kendali untuk setiap nilai enum
     * `sumber`. Dikumpulkan di sini, bukan di masing-masing tampilan, agar
     * modal kartu kendali dan hasil cetaknya tidak pernah menyebut transaksi
     * yang sama dengan istilah berbeda — justru variasi penulisan itulah yang
     * hendak dihilangkan dari kartu kendali manual (Instruksi §22).
     */
    public const URAIAN = [
        'pembelian'          => 'Pembelian',
        'transfer_masuk'     => 'Transfer Masuk',
        'stok_awal'          => 'Stok Awal',
        'pemakaian'          => 'Pemakaian',
        'pengembalian'       => 'Pengembalian',
        'reklasifikasi_aset' => 'Reklasifikasi ke Aset',
        'stok_opname'        => 'Stok Opname',
    ];

    protected $table = 'mutasi_stok';
    protected $guarded = [];
    protected $casts = ['tanggal' => 'date'];

    /** Uraian transaksi sebagaimana dicetak pada kartu kendali. */
    public function getUraianAttribute(): string
    {
        return self::URAIAN[$this->sumber] ?? '—';
    }

    public function barang()
    {
        return $this->belongsTo(BarangPersediaan::class, 'barang_id');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}