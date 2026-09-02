<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsetTetap extends Model
{
    protected $table = 'aset_tetap';
    protected $guarded = [];

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function timPenempatan()
    {
        return $this->belongsTo(Tim::class, 'tim_penempatan_id');
    }
}