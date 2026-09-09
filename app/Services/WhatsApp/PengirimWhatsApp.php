<?php

namespace App\Services\WhatsApp;

/**
 * Kontrak pengirim pesan WhatsApp.
 *
 * SIMPBI sengaja tidak terikat pada satu gerbang WhatsApp tertentu. Jalur
 * resmi (WhatsApp Cloud API milik Meta) dan jalur tidak resmi yang dipasang
 * sendiri (misalnya gerbang berbasis Baileys) memiliki risiko, biaya, dan
 * syarat administratif yang berbeda, dan pilihan itu dapat berubah setelah
 * sistem berjalan. Dengan memisahkan kontrak dari pelaksananya, penggantian
 * gerbang cukup dilakukan lewat berkas konfigurasi, tanpa menyentuh alur
 * notifikasi maupun tabel `notifikasi`.
 *
 * Pelaksana tidak perlu tahu apa-apa tentang tabel notifikasi: tugasnya hanya
 * mengirim satu pesan ke satu nomor. Pencatatan status, percobaan ulang, dan
 * penanganan galat ditangani oleh App\Jobs\KirimPesanWhatsApp.
 */
interface PengirimWhatsApp
{
    /**
     * Mengirim satu pesan.
     *
     * @param  string  $tujuan  nomor E.164 tanpa tanda plus, contoh 6281234567890
     * @return string|null  penanda pesan dari gerbang, bila ada
     *
     * @throws PengirimanGagal bila gerbang menolak atau tidak dapat dihubungi
     */
    public function kirim(string $tujuan, string $pesan): ?string;

    /**
     * Apakah pelaksana ini sudah terkonfigurasi dan siap dipakai.
     *
     * Dipisahkan dari kirim() supaya kesalahan pemasangan — kredensial kosong,
     * alamat gerbang belum diisi — dapat dikenali sebelum pesan dicoba kirim,
     * dan tercatat sebagai kegagalan yang jelas alasannya.
     */
    public function siap(): bool;

    /** Nama pelaksana untuk keperluan pencatatan log. */
    public function nama(): string;
}
