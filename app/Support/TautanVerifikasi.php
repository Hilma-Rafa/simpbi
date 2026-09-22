<?php

namespace App\Support;

/**
 * Alamat verifikasi yang disandikan ke dalam kode QR dokumen.
 *
 * Dibentuk dari `config('app.url')`, bukan dari `route()` apa adanya. `route()`
 * mengambil akar alamat dari permintaan HTTP yang sedang berjalan, sehingga
 * dokumen yang disahkan lewat panel di `127.0.0.1:8000` membawa alamat itu ke
 * dalam kodenya — alamat yang pada ponsel pemindainya menunjuk balik ke ponsel
 * itu sendiri. Dokumen beredar ke luar sistem dan membeku begitu terbit, jadi
 * alamatnya harus berasal dari tetapan pemasangan, bukan dari mesin yang
 * kebetulan menekan tombolnya.
 *
 * Dikumpulkan di sini karena dua jenis dokumen membutuhkannya — bukti
 * permintaan dan BAST mutasi aset — dan alamat keduanya harus dibentuk dengan
 * cara yang persis sama. Dua salinan yang terpisah cepat atau lambat akan
 * berbeda, dan bedanya baru ketahuan ketika kode QR di tangan orang tidak
 * terbuka.
 */
class TautanVerifikasi
{
    /**
     * Alamat lengkap menuju sebuah rute verifikasi.
     *
     * Ketika `APP_URL` belum diisi, alamat permintaan dipakai sebagai jalan
     * terakhir agar kode tetap terbentuk — lebih baik alamat yang perlu
     * diperbaiki daripada kode yang tidak menuju ke mana pun.
     */
    public static function untuk(string $rute, string $token): string
    {
        $akar = rtrim((string) config('app.url'), '/');

        if ($akar === '') {
            return route($rute, ['token' => $token]);
        }

        return $akar . route($rute, ['token' => $token], absolute: false);
    }
}
