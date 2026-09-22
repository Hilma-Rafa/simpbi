<?php

namespace App\Support;

use App\Models\User;

/**
 * Aturan pelengkapan akun pada pemakaian pertama.
 *
 * Sebelum memakai sistem, pengguna wajib melengkapi data awal yang memang
 * dibutuhkan alur: nomor WhatsApp (agar notifikasi sampai) dan tanda tangan
 * (agar dokumen dapat terbit atas namanya). Nama lengkap, NIP, dan email sudah
 * berasal dari data akun dan tidak diisi ulang di sini. Penggantian kata sandi
 * awal ditangani gerbang tersendiri, {@see \App\Http\Middleware\PaksaGantiKataSandi},
 * dan tetap didahulukan supaya kata sandi seragam berumur sependek mungkin.
 *
 * Tidak semua peran menandatangani dokumen, sehingga tanda tangan hanya
 * diwajibkan bagi yang benar-benar membubuhkannya. Admin Sistem tidak
 * menjalankan alur operasional (Instruksi §39), sehingga tidak diwajibkan
 * melengkapi apa pun di sini.
 */
class Onboarding
{
    /**
     * Peran yang membubuhkan tanda tangan *tergambar* pada dokumen.
     *
     * Hanya Petugas Gudang (penyiapan) dan Ketua Tim (penerima bukti serta
     * penyerah/penerima BAST) yang gambar tanda tangannya benar-benar disematkan
     * ke PDF. Kasubbag Umum TIDAK termasuk: pengesahannya memakai e-TTD berupa
     * nama + kode QR verifikasi (Instruksi §18), bukan gambar tersimpan, sehingga
     * mewajibkannya menggambar tanda tangan hanya menahan Kasubbag di halaman
     * pelengkapan tanpa guna.
     */
    public const PERAN_BERTANDA_TANGAN = ['petugas_gudang', 'ketua_tim'];

    /** Peran yang notifikasi WhatsApp-nya memang ditujukan kepadanya. */
    public const PERAN_BERNOMOR_WA = ['tim', 'ketua_tim', 'petugas_gudang', 'kasubbag'];

    public static function butuhTandaTangan(?User $pengguna): bool
    {
        return in_array($pengguna?->role, self::PERAN_BERTANDA_TANGAN, true);
    }

    public static function butuhNomorWa(?User $pengguna): bool
    {
        return in_array($pengguna?->role, self::PERAN_BERNOMOR_WA, true);
    }

    public static function nomorWaKurang(?User $pengguna): bool
    {
        return static::butuhNomorWa($pengguna) && blank($pengguna?->no_hp);
    }

    public static function tandaTanganKurang(?User $pengguna): bool
    {
        return static::butuhTandaTangan($pengguna) && ! TandaTangan::terdaftar($pengguna);
    }

    /**
     * Apakah pelengkapan akun sudah tuntas.
     *
     * Penggantian kata sandi tidak diperiksa di sini: selama masih diwajibkan,
     * pengguna ditahan lebih dulu oleh gerbang kata sandi, sehingga pelengkapan
     * ini baru relevan sesudahnya.
     */
    public static function lengkap(?User $pengguna): bool
    {
        if (! $pengguna) {
            return true;
        }

        return ! static::nomorWaKurang($pengguna) && ! static::tandaTanganKurang($pengguna);
    }

    /**
     * Bagian formulir pelengkapan yang masih perlu diisi pengguna ini.
     *
     * @return array<int,'no_hp'|'tanda_tangan'>
     */
    public static function langkahKurang(?User $pengguna): array
    {
        $langkah = [];

        if (static::nomorWaKurang($pengguna)) {
            $langkah[] = 'no_hp';
        }

        if (static::tandaTanganKurang($pengguna)) {
            $langkah[] = 'tanda_tangan';
        }

        return $langkah;
    }
}
