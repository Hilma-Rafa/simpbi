<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi dalam aplikasi.
 *
 * Memakai tabel `notifikasi` yang sudah dirancang pada ERD (Instruksi §52),
 * bukan tabel notifikasi bawaan Laravel, sehingga satu kejadian dapat
 * menghasilkan baris terpisah per kanal (in_app dan whatsapp) dan riwayat
 * pengirimannya tetap terlacak.
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';
    protected $guarded = [];

    protected $casts = [
        'dibaca_at'        => 'datetime',
        'dikirim_at'       => 'datetime',
        'disembunyikan_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Notifikasi yang belum dibaca pengguna. */
    public function scopeBelumDibaca($query)
    {
        return $query->whereNull('dibaca_at');
    }

    /** Hanya notifikasi yang tampil di dalam aplikasi. */
    public function scopeDalamAplikasi($query)
    {
        return $query->where('channel', 'in_app');
    }

    /**
     * Notifikasi yang belum disingkirkan pengguna dari panel lonceng.
     *
     * Terpisah dari scopeBelumDibaca(): sebuah notifikasi dapat disingkirkan
     * tanpa pernah dibaca, dan sebaliknya notifikasi yang sudah dibaca tetap
     * berdiri di panel sampai pengguna sendiri yang menyingkirkannya.
     *
     * Dipakai hanya oleh panel lonceng. Halaman Riwayat sengaja tidak
     * memakainya, sebab yang disingkirkan dari pandangan sehari-hari tetap
     * harus dapat ditelusuri di sana.
     */
    public function scopeBelumDisembunyikan($query)
    {
        return $query->whereNull('disembunyikan_at');
    }

    /** Warna dan ikon menurut jenis kejadian, mengikuti palet Instruksi §25. */
    public function tampilan(): array
    {
        return match ($this->tipe) {
            'stok'   => ['ikon' => 'heroicon-m-exclamation-triangle', 'warna' => 'warning'],
            'mutasi' => ['ikon' => 'heroicon-m-truck',                'warna' => 'info'],
            default  => ['ikon' => 'heroicon-m-clipboard-document-list', 'warna' => 'primary'],
        };
    }
}
