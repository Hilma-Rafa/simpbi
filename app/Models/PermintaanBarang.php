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
        'menunggu_kasubbag'    => 'Menunggu Persetujuan akhir Kasubbag',
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
     * Label pendek untuk badge status pada tabel.
     *
     * Label panjang pada STATUS tetap dipakai di tempat yang memang menuntut
     * penyebutan resmi: berkas ekspor, pilihan penyaring, dan kepala dialog
     * rincian. Di dalam tabel label itu justru merugikan — "Menunggu
     * Persetujuan Ketua Tim" sepanjang tiga puluh karakter memaksa seluruh
     * tabel melebar sampai muncul penggulung mendatar, sehingga kolom Batas
     * Waktu terpotong di layar biasa.
     *
     * Yang dibuang hanya kata "Persetujuan" dan "Verifikasi", karena tahap
     * siapa yang sedang menahan permintaan sudah cukup ditunjukkan oleh nama
     * jabatannya. Status yang memang sudah pendek tidak diubah, supaya kedua
     * daftar menyebut hal yang sama dengan kata yang sama.
     */
    public const STATUS_RINGKAS = [
        'menunggu_ketua'      => 'Menunggu Ketua Tim',
        'menunggu_verifikasi' => 'Menunggu Gudang',
        'menunggu_kasubbag'   => 'Menunggu Kasubbag Umum',
    ];

    /**
     * Label status sebagaimana ditampilkan pada badge tabel.
     */
    public static function labelRingkas(?string $status): string
    {
        return static::STATUS_RINGKAS[$status]
            ?? static::STATUS[$status]
            ?? (string) $status;
    }

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