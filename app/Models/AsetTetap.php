<?php

namespace App\Models;

use App\Models\Concerns\DilindungiRiwayat;
use Illuminate\Database\Eloquent\Model;

class AsetTetap extends Model
{
    use DilindungiRiwayat;

    protected $table = 'aset_tetap';
    protected $guarded = [];

    /**
     * Aset yang sudah punya jejak tidak boleh dihapus.
     *
     * Dua kunci asing merujuk aset ini, dan keduanya bermasalah dengan cara yang
     * berbeda. `riwayat_penempatan_aset.aset_id` memakai penghapusan berantai,
     * sehingga menghapus asetnya ikut memusnahkan seluruh jejak penempatannya —
     * ke tim mana ia pernah ditempatkan dan sejak kapan — tanpa satu pun galat
     * yang memberitahu. `bast_mutasi_aset.aset_id` sebaliknya menolak, tetapi
     * penolakannya berupa galat basis data mentah di layar.
     *
     * Keduanya dicegah di sini, sehingga tidak ada jejak yang hilang diam-diam
     * dan tidak ada galat mentah yang sampai ke pengguna. Aset yang sudah tidak
     * dipakai lagi dinonaktifkan lewat kolom `status_aktif`, bukan dihapus.
     */
    public function punyaRiwayat(): bool
    {
        return RiwayatPenempatanAset::where('aset_id', $this->id)->exists()
            || BastMutasiAset::where('aset_id', $this->id)->exists();
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function timPenempatan()
    {
        return $this->belongsTo(Tim::class, 'tim_penempatan_id');
    }

    /**
     * Seluruh baris penempatan aset ini, yang terbaru lebih dulu.
     *
     * Urutannya menurun supaya penempatan yang sedang berlaku — satu-satunya
     * baris yang `tanggal_selesai`-nya kosong — selalu duduk di paling atas
     * ketika riwayatnya dibaca. Id dipakai sebagai pemutus ketika dua baris
     * jatuh pada tanggal yang sama, sebab id mewakili urutan pencatatan dan
     * itulah satu-satunya keterangan urutan yang tersisa saat tanggalnya kembar.
     */
    public function riwayatPenempatan()
    {
        return $this->hasMany(RiwayatPenempatanAset::class, 'aset_id')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id');
    }

    /**
     * Membuka baris penempatan pertama sebuah aset.
     *
     * Tanpa baris ini, jejak penempatan sebuah aset baru dimulai ketika ia
     * dimutasikan untuk pertama kali. Akibatnya rentang penempatan pertamanya —
     * yang justru biasanya paling panjang — tidak pernah terhitung, dan aset
     * yang belum pernah berpindah tampak tidak punya riwayat sama sekali.
     * Seeder sudah menuliskan baris ini untuk data contoh; yang belum adalah
     * aset yang ditambahkan lewat antarmuka.
     *
     * Dipanggil dari dua titik, dan keduanya menandai peristiwa yang sama yaitu
     * saat aset pertama kali punya tim kerja: penambahan aset yang formulirnya
     * sudah memuat tim, dan penyuntingan yang mengisi tim yang semula kosong.
     * Perpindahan aset yang sudah ditempatkan bukan urusan metode ini — itu
     * mutasi, dan mutasi dicatat oleh alur BAST.
     *
     * Mengembalikan null pada dua keadaan, dan keduanya disengaja:
     *
     *  - Aset yang belum punya tim penempatan. Kolom `tim_id` pada riwayat
     *    tidak menerima null, dan menebak timnya berarti mengarang jejak yang
     *    tidak pernah terjadi. Asetnya tetap tersimpan, hanya belum berriwayat.
     *  - Aset yang sudah memiliki baris riwayat. Penjaga inilah yang membuat
     *    pemanggilan berulang tidak pernah menghasilkan baris kembar.
     *
     * `tanggal_mulai` adalah **tanggal penempatan itu dicatat**, yaitu hari
     * ini — bukan tanggal asetnya dibuat. Keduanya memang sama ketika aset
     * ditambahkan lengkap dengan tim kerjanya, sebab keduanya terjadi pada
     * saat yang sama. Keduanya berbeda jauh ketika aset dicatat lebih dulu
     * tanpa tim, lalu ditempatkan berbulan-bulan kemudian: memakai tanggal
     * pembuatan di situ menyatakan tim tersebut memegang aset sejak sebelum
     * penempatannya diputuskan, dan membuat kolom Lama pada riwayat melaporkan
     * ratusan hari yang tidak pernah terjadi.
     */
    public function catatPenempatanAwal(): ?RiwayatPenempatanAset
    {
        if (! $this->tim_penempatan_id) {
            return null;
        }

        if ($this->riwayatPenempatan()->exists()) {
            return null;
        }

        return $this->riwayatPenempatan()->create([
            'tim_id'          => $this->tim_penempatan_id,
            'tanggal_mulai'   => now()->toDateString(),
            'tanggal_selesai' => null,
            'jenis'           => 'penempatan_awal',
            'bast_id'         => null,
        ]);
    }
}