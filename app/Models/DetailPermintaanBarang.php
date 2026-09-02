<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPermintaanBarang extends Model
{
    protected $table = 'detail_permintaan_barang';
    protected $guarded = [];

    public function permintaan()
    {
        return $this->belongsTo(PermintaanBarang::class, 'permintaan_id');
    }

    public function barang()
    {
        return $this->belongsTo(BarangPersediaan::class, 'barang_id');
    }
}