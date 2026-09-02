<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatPersetujuan extends Model
{
    protected $table = 'riwayat_persetujuan';
    protected $guarded = [];
    protected $casts = ['waktu' => 'datetime'];

    public function permintaan()
    {
        return $this->belongsTo(PermintaanBarang::class, 'permintaan_id');
    }

    public function pelaksana()
    {
        return $this->belongsTo(User::class, 'pelaksana_id');
    }
}