<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berita Acara Serah Terima (BAST) mutasi aset tetap.
 *
 * Subsistem aset tidak menggunakan alur approval permintaan barang
 * (Instruksi §18). Statusnya hanya: menunggu_pengesahan → menunggu_konfirmasi
 * → selesai_administratif, tanpa jalur penolakan.
 */
class BastMutasiAset extends Model
{
    protected $table = 'bast_mutasi_aset';
    protected $guarded = [];

    protected $casts = [
        'disahkan_at'     => 'datetime',
        'dikonfirmasi_at' => 'datetime',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(AsetTetap::class, 'aset_id');
    }

    public function timAsal(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_asal_id');
    }

    public function timTujuan(): BelongsTo
    {
        return $this->belongsTo(Tim::class, 'tim_tujuan_id');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_id');
    }

    public function disahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disahkan_oleh_id');
    }

    public function dikonfirmasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikonfirmasi_oleh_id');
    }
}
