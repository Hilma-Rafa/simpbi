<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kategori extends Model
{
    protected $table = 'kategori';
    protected $guarded = [];

    public function barang()
    {
        return $this->hasMany(BarangPersediaan::class, 'kategori_id');
    }
}