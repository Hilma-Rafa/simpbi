<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pengiriman lewat layanan Fonnte.
 *
 * Berbeda dengan gerbang yang dipasang sendiri, Fonnte menjalankan gerbangnya
 * di peladen mereka, sehingga tidak ada apa pun yang perlu dipasang di komputer
 * atau peladen SIMPBI. Nomor tetap ditautkan lewat pemindaian kode QR pada
 * dasbor Fonnte, dan risiko pemblokiran nomor tetap ada karena layanan ini pun
 * memakai klien WhatsApp tidak resmi.
 *
 * Bentuk permintaan mengikuti dokumentasi Fonnte:
 *
 *   POST https://api.fonnte.com/send
 *   Header: Authorization: {token}      (tanpa awalan "Bearer")
 *   Badan  : target=62..., message=...
 *
 * PENTING — perbedaan mendasar dengan gerbang OpenWA: Fonnte menjawab dengan
 * kode HTTP 200 bahkan ketika pengiriman gagal, dan keberhasilan sebenarnya
 * ditandai oleh kolom `status` pada badan jawaban. Memeriksa kode HTTP saja
 * karena itu tidak cukup; token yang salah atau kuota yang habis akan tampak
 * seperti keberhasilan bila kolom itu diabaikan.
 *
 * Percobaan ulang sengaja TIDAK dilakukan di sini, melainkan diserahkan kepada
 * App\Jobs\KirimPesanWhatsApp, sama seperti pelaksana lainnya, agar setiap
 * percobaan tercatat pada baris notifikasi.
 */
class PengirimFonnte implements PengirimWhatsApp
{
    /** Alamat pengiriman pesan pada API Fonnte. */
    protected const ENDPOINT = 'https://api.fonnte.com/send';

    public function __construct(
        protected ?string $token = null,
        protected int $batasDetik = 15,
    ) {
        $this->token      ??= (string) config('whatsapp.fonnte.token');
        $this->batasDetik   = (int) config('whatsapp.fonnte.batas_detik', $this->batasDetik);
    }

    public function kirim(string $tujuan, string $pesan): ?string
    {
        try {
            $jawaban = Http::withHeaders(['Authorization' => $this->token])
                ->timeout($this->batasDetik)
                ->connectTimeout(min(5, $this->batasDetik))
                // Fonnte menerima parameter sebagai isian formulir, bukan JSON.
                ->asForm()
                ->post(self::ENDPOINT, [
                    'target'  => $tujuan,
                    'message' => $pesan,
                ]);
        } catch (ConnectionException $e) {
            // Layanan tidak terjangkau: jaringan peladen terputus atau Fonnte
            // sedang tidak dapat dihubungi. Layak dicoba ulang oleh antrean.
            throw new PengirimanGagal('Layanan Fonnte tidak dapat dihubungi: ' . $e->getMessage(), 0, $e);
        }

        if ($jawaban->failed()) {
            throw new PengirimanGagal(
                'Layanan Fonnte mengembalikan galat. (HTTP ' . $jawaban->status() . ': '
                . mb_substr(trim($jawaban->body()), 0, 200) . ')'
            );
        }

        $isi = $jawaban->json() ?? [];

        if (! $this->berhasil($isi)) {
            throw new PengirimanGagal($this->jelaskanGalat($isi, $jawaban->body()));
        }

        return $this->penanda($isi);
    }

    public function siap(): bool
    {
        return filled($this->token);
    }

    public function nama(): string
    {
        return 'fonnte';
    }

    /**
     * Membaca penanda keberhasilan dari badan jawaban.
     *
     * Dokumentasi Fonnte menuliskan kolomnya sebagai `status` pada satu contoh
     * dan `Status` pada contoh lain, sehingga keduanya diperiksa. Bila kolom
     * itu tidak ada sama sekali, jawaban dianggap gagal — lebih baik menahan
     * satu pesan yang sebenarnya berhasil daripada menandai terkirim sesuatu
     * yang tidak pernah sampai.
     *
     * @param  array<string,mixed>  $isi
     */
    protected function berhasil(array $isi): bool
    {
        $status = $isi['status'] ?? $isi['Status'] ?? null;

        return $status === true || $status === 'true' || $status === 1 || $status === '1';
    }

    /**
     * Penanda pesan, untuk penelusuran bila pesan dipersoalkan.
     *
     * Fonnte mengembalikan `id` berupa larik karena satu permintaan dapat
     * menyasar banyak nomor sekaligus; SIMPBI selalu mengirim ke satu nomor,
     * sehingga cukup diambil elemen pertamanya.
     *
     * @param  array<string,mixed>  $isi
     */
    protected function penanda(array $isi): ?string
    {
        $id = $isi['id'] ?? null;

        if (is_array($id)) {
            $id = $id[0] ?? null;
        }

        return $id === null ? null : (string) $id;
    }

    /**
     * Menerjemahkan alasan penolakan menjadi keterangan yang dapat
     * ditindaklanjuti.
     *
     * Keterangan ini tersimpan pada kolom error_message dan terbaca petugas,
     * sehingga harus menyebutkan tindakan yang perlu diambil. Alasan asli dari
     * Fonnte tetap disertakan untuk penelusuran.
     *
     * @param  array<string,mixed>  $isi
     */
    protected function jelaskanGalat(array $isi, string $badan): string
    {
        $alasan = (string) ($isi['reason'] ?? $isi['detail'] ?? '');
        $kunci  = mb_strtolower($alasan);

        $keterangan = match (true) {
            str_contains($kunci, 'token')      => 'Token Fonnte ditolak. Periksa WHATSAPP_FONNTE_TOKEN pada berkas .env.',
            str_contains($kunci, 'quota')      => 'Kuota pengiriman Fonnte habis. Isi ulang paket pada dasbor Fonnte.',
            str_contains($kunci, 'target')     => 'Nomor tujuan ditolak Fonnte. Periksa nomor WhatsApp pengguna pada menu Pengguna.',
            str_contains($kunci, 'disconnect') => 'Perangkat pada Fonnte belum tersambung. Pindai ulang kode QR pada dasbor Fonnte.',
            str_contains($kunci, 'input')      => 'Permintaan ditolak Fonnte karena isian tidak lengkap.',
            default                            => 'Fonnte menolak pengiriman pesan.',
        };

        $rincian = $alasan !== '' ? $alasan : mb_substr(trim($badan), 0, 200);

        return $keterangan . ' (Fonnte: ' . $rincian . ')';
    }
}
