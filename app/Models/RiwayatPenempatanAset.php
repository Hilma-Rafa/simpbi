<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat penempatan/mutasi aset tetap. Setiap perpindahan aset menutup
 * baris penempatan sebelumnya (tanggal_selesai) dan membuka baris baru,
 * sehingga jejak penempatan aset dapat ditelusuri (Instruksi §18).
 */
class RiwayatPenempatanAset extends Model
{
    protected $table = 'riwayat_penempatan_aset';
    protected $guarded = [];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetTetap::class, 'aset_id');
    }

    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_id');
    }

    public function bast(): BelongsTo
    {
        return $this->belongsTo(BastMutasiAset::class, 'bast_id');
    }
}
