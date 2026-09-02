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