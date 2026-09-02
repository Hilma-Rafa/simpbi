<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MutasiStok extends Model
{
    protected $table = 'mutasi_stok';
    protected $guarded = [];
    protected $casts = ['tanggal' => 'date'];

    public function barang()
    {
        return $this->belongsTo(BarangPersediaan::class, 'barang_id');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}