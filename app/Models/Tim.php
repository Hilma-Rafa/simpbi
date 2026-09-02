<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tim extends Model
{
    protected $table = 'tim';
    protected $guarded = [];

    public function ketuaTim()
    {
        return $this->belongsTo(User::class, 'ketua_tim_id');
    }

    public function anggota()
    {
        return $this->hasMany(User::class, 'tim_id');
    }
}