<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermintaanBarang extends Model
{
    protected $table = 'permintaan_barang';
    protected $guarded = [];

    protected $casts = [
        'hold_expired_at'  => 'datetime',
        'hold_released_at' => 'datetime',
    ];

    // Status mengikuti alur pada BPMN Proses Bisnis Usulan Permintaan Barang
    public const STATUS = [
        'menunggu_ketua'       => 'Menunggu Persetujuan Ketua Tim',
        'menunggu_verifikasi'  => 'Menunggu Verifikasi Gudang',
        'menunggu_kasubbag'    => 'Menunggu Persetujuan Kasubbag',
        'siap_diproses'        => 'Siap Diproses',
        'siap_diambil'         => 'Siap Diambil',
        'selesai'              => 'Selesai',
        'ditolak_ketua'        => 'Ditolak Ketua Tim',
        'ditolak_kasubbag'     => 'Ditolak Kasubbag',
        'bermasalah'           => 'Bermasalah',
        'kedaluwarsa'          => 'Kedaluwarsa',
    ];

    public function tim()
    {
        return $this->belongsTo(Tim::class, 'tim_pemohon_id');
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'pengaju_id');
    }

    public function detail()
    {
        return $this->hasMany(DetailPermintaanBarang::class, 'permintaan_id');
    }

    public function persetujuan()
    {
        return $this->hasMany(RiwayatPersetujuan::class, 'permintaan_id');
    }

    public function ketidaksesuaian()
    {
        return $this->hasMany(KetidaksesuaianBarang::class, 'permintaan_id');
    }
}