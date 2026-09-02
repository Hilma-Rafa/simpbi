<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KetidaksesuaianBarang extends Model
{
    protected $table = 'ketidaksesuaian_barang';
    protected $guarded = [];
    protected $casts = ['dapat_diatasi' => 'boolean'];

    public function permintaan()
    {
        return $this->belongsTo(PermintaanBarang::class, 'permintaan_id');
    }

    public function detail()
    {
        return $this->belongsTo(DetailPermintaanBarang::class, 'detail_id');
    }

    public function petugas()
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}