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
        'pengesahan_at'    => 'datetime',
    ];

    // Status mengikuti alur pada BPMN Proses Bisnis Usulan Permintaan Barang
    public const STATUS = [
        'menunggu_ketua'       => 'Menunggu Persetujuan Ketua Tim',
        'menunggu_verifikasi'  => 'Menunggu Verifikasi Gudang',
        'menunggu_kasubbag'    => 'Menunggu Persetujuan Kasubbag',
        'siap_diproses'        => 'Siap Diproses',
        'siap_diambil'         => 'Siap Diambil',
        'menunggu_pengesahan'  => 'Menunggu Pengesahan',
        'selesai'              => 'Selesai',
        'ditolak_ketua'        => 'Ditolak Ketua Tim',
        'ditolak_kasubbag'     => 'Ditolak Kasubbag',
        'bermasalah'           => 'Bermasalah',
        'kedaluwarsa'          => 'Kedaluwarsa',
    ];

    /**
     * Status akhir yang menjadikan permintaan sebagai histori.
     *
     * Permintaan pada status ini sudah berhenti berjalan pada alur enam tahap,
     * sehingga dipisahkan dari daftar permintaan aktif dan ditampilkan pada
     * halaman Riwayat. Recordnya tetap tersimpan utuh di basis data; yang
     * berbeda hanya tempat penampilannya.
     *
     * Status "bermasalah" ikut termasuk: kunci stoknya sudah dilepaskan dan
     * permintaannya tidak dapat dilanjutkan, sehingga tempatnya adalah histori.
     * Jumlahnya tetap dipantau melalui KPI "Permintaan Bermasalah" pada dasbor
     * Petugas Gudang, yang menghitung langsung dari status, bukan dari daftar.
     */
    public const STATUS_RIWAYAT = [
        'selesai',
        'ditolak_ketua',
        'ditolak_kasubbag',
        'kedaluwarsa',
        'bermasalah',
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