<?php

namespace App\Support;

use App\Models\PermintaanBarang;
use App\Models\User;

/**
 * Sumber kebenaran tunggal atas "status apa yang dapat ditindak oleh peran apa"
 * pada alur Permintaan Barang.
 *
 * Widget "Perlu Tindakan" dan syarat tampil tombol aksi pada Daftar Permintaan
 * membaca peta yang sama dari sini, sehingga keduanya tidak mungkin berbeda
 * pendapat. Sebelumnya widget memetakan Ketua Tim hanya pada `menunggu_ketua`,
 * padahal Ketua Tim juga berhak "Konfirmasi Penerimaan" pada `siap_diambil` —
 * akibatnya permintaan yang benar-benar menunggu tindakannya tidak pernah
 * muncul di panel dasbor.
 */
class TindakanPermintaan
{
    /**
     * Status permintaan yang benar-benar memunculkan tombol aksi bagi peran.
     *
     * Harus selalu cocok dengan closure `->visible()` tiap aksi tahapan pada
     * PermintaanBarangResource. Bila sebuah aksi baru ditambahkan di sana,
     * statusnya wajib ditambahkan di sini pula.
     *
     * @return array<int,string>
     */
    public static function statusUntuk(?User $pengguna): array
    {
        return match ($pengguna?->role) {
            'ketua_tim'      => ['menunggu_ketua', 'siap_diambil'],
            'petugas_gudang' => ['menunggu_verifikasi', 'siap_diproses'],
            'kasubbag'       => ['menunggu_kasubbag', 'menunggu_pengesahan'],
            'tim'            => ['siap_diambil'],
            default          => [],
        };
    }

    /**
     * Apakah pengguna punya aksi yang dapat dijalankan atas permintaan ini
     * pada keadaannya saat ini. Ikut menegakkan batasan tim: Tim dan Ketua Tim
     * hanya atas permintaan timnya sendiri.
     */
    public static function ada(?User $pengguna, PermintaanBarang $permintaan): bool
    {
        if (! in_array($permintaan->status, static::statusUntuk($pengguna), true)) {
            return false;
        }

        if (in_array($pengguna->role, ['tim', 'ketua_tim'], true)) {
            return $pengguna->tim_id === $permintaan->tim_pemohon_id;
        }

        return true;
    }
}
