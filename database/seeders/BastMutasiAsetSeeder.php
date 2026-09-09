<?php

namespace Database\Seeders;

use App\Models\AsetTetap;
use App\Models\BastMutasiAset;
use App\Models\Tim;
use App\Models\User;
use App\Services\MutasiAsetService;
use Illuminate\Database\Seeder;

/**
 * Satu BAST mutasi aset yang sudah disahkan, agar alur aset (UC-16/17/18)
 * punya contoh lengkap sejak awal dan halaman verifikasi keaslian BAST dapat
 * dibuka tanpa lebih dulu membuat dokumen sendiri lewat panel.
 *
 * BAST-nya tidak ditulis langsung ke tabel, melainkan dibentuk lalu disahkan
 * lewat MutasiAsetService::sahkan() — persis jalan yang ditempuh Kasubbag di
 * panel. Dengan begitu seluruh akibat ikut terjadi apa adanya: penempatan aset
 * berpindah ke tim tujuan, baris riwayat penempatan yang berjalan ditutup dan
 * yang baru dibuka, dokumen PDF dibentuk, dan token QR dihasilkan. Kalau
 * datanya ditulis manual, tabel akan tampak benar sementara penempatan asetnya
 * tertinggal di tim lama — justru keadaan yang tidak pernah terjadi di sistem.
 *
 * Yang sengaja tidak ditiru adalah pengiriman notifikasi. Di panel notifikasi
 * dipanggil oleh lapisan Filament, bukan oleh service, dan seeder tidak boleh
 * mengirim pesan WhatsApp ke nomor siapa pun hanya karena basis data diisi.
 */
class BastMutasiAsetSeeder extends Seeder
{
    public function run(): void
    {
        // Aset dipilih lewat NUP, bukan lewat nama atau id, karena NUP-lah yang
        // dijamin tetap oleh AsetTetapSeeder meski urutan barisnya berubah.
        $aset = AsetTetap::where('nup', '3.10.01.00007')->first();

        // Seeder ini bergantung pada AsetTetapSeeder dan PenggunaSeeder. Bila
        // salah satunya belum berjalan, lebih baik dilewati diam-diam daripada
        // menggagalkan seluruh rangkaian db:seed.
        $petugas = User::where('role', 'petugas_gudang')->first();
        $kasubbag = User::where('role', 'kasubbag')->first();

        if (! $aset || ! $petugas || ! $kasubbag) {
            return;
        }

        // Aman dijalankan ulang: satu aset cukup diwakili satu BAST contoh.
        if (BastMutasiAset::where('aset_id', $aset->id)->exists()) {
            return;
        }

        // Tim asal dibaca dari penempatan aset yang berlaku sekarang, bukan
        // ditulis tetap, supaya BAST ini tetap benar walau AsetTetapSeeder
        // kelak menempatkan proyektornya di tim lain.
        $timAsalId = $aset->tim_penempatan_id;

        // Tujuannya tim mana pun selain tim asal, diutamakan yang sudah punya
        // Ketua Tim — sebab nama Ketua itulah yang tercetak sebagai pihak
        // penerima pada dokumen. Diambil dari basis data agar tidak bergantung
        // pada id yang kebetulan berlaku hari ini.
        $timTujuan = Tim::whereKeyNot($timAsalId)
            ->orderByRaw('ketua_tim_id is null')
            ->with('ketuaTim')
            ->first();

        if (! $timAsalId || ! $timTujuan) {
            return;
        }

        $layanan = app(MutasiAsetService::class);

        $bast = BastMutasiAset::create([
            'nomor_bast'     => $layanan->nomorBaru(),
            'aset_id'        => $aset->id,
            'tim_asal_id'    => $timAsalId,
            'tim_tujuan_id'  => $timTujuan->id,
            'alasan_mutasi'  => 'Proyektor dipindahkan untuk mendukung kegiatan pengumpulan data lapangan yang frekuensinya meningkat pada tim tujuan.',
            'pihak_penyerah' => $petugas->name,
            'pihak_penerima' => $timTujuan->ketuaTim?->name ?? $timTujuan->nama_tim,
            'dibuat_oleh_id' => $petugas->id,
        ]);

        $layanan->sahkan($bast, $kasubbag->id);
    }
}
