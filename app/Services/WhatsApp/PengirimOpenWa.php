<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Pengiriman lewat gerbang OpenWA yang dipasang sendiri.
 *
 * OpenWA adalah gerbang WhatsApp sumber terbuka yang dijalankan di peladen
 * sendiri dan menyediakan REST API. Karena memakai klien WhatsApp tidak resmi,
 * nomor yang dipakai menanggung risiko pemblokiran; itulah sebabnya nomor
 * gerbang harus nomor khusus, bukan nomor pribadi maupun nomor dinas, dan
 * jeda antar pesan diatur pada config/whatsapp.php.
 *
 * Bentuk permintaan mengikuti berkas openapi.json OpenWA:
 *
 *   POST {alamat}/api/sessions/{sesi}/messages/send-text
 *   Header: X-API-Key
 *   Badan  : {"chatId": "628...@c.us", "text": "..."}
 *   Jawaban 201: {"messageId": "...", "timestamp": 1234567890}
 *
 * Percobaan ulang sengaja TIDAK dilakukan di sini, melainkan diserahkan kepada
 * App\Jobs\KirimPesanWhatsApp, agar setiap percobaan tercatat pada baris
 * notifikasi dan tidak ada pengulangan berlapis yang justru memperbesar
 * kecurigaan gerbang terhadap pola pengiriman.
 */
class PengirimOpenWa implements PengirimWhatsApp
{
    public function __construct(
        protected ?string $alamat = null,
        protected ?string $sesi = null,
        protected ?string $kunciApi = null,
        protected int $batasDetik = 15,
    ) {
        $this->alamat     ??= (string) config('whatsapp.openwa.alamat');
        $this->sesi       ??= (string) config('whatsapp.openwa.sesi');
        $this->kunciApi   ??= (string) config('whatsapp.openwa.kunci_api');
        $this->batasDetik   = (int) config('whatsapp.openwa.batas_detik', $this->batasDetik);
    }

    public function kirim(string $tujuan, string $pesan): ?string
    {
        $url = rtrim($this->alamat, '/') . '/api/sessions/' . rawurlencode($this->sesi) . '/messages/send-text';

        try {
            $jawaban = Http::withHeaders(['X-API-Key' => $this->kunciApi])
                ->timeout($this->batasDetik)
                ->connectTimeout(min(5, $this->batasDetik))
                ->asJson()
                ->post($url, [
                    // Nomor perorangan ditulis sebagai {nomor}@c.us; akhiran
                    // @g.us hanya untuk grup, yang tidak dipakai SIMPBI.
                    'chatId' => $tujuan . '@c.us',
                    'text'   => $pesan,
                ]);
        } catch (ConnectionException $e) {
            // Gerbang tidak terjangkau: peladen mati, alamat salah, atau
            // jaringan terputus. Layak dicoba ulang oleh antrean.
            throw new PengirimanGagal('Gerbang OpenWA tidak dapat dihubungi: ' . $e->getMessage(), 0, $e);
        }

        if ($jawaban->successful()) {
            return $jawaban->json('messageId');
        }

        throw new PengirimanGagal($this->jelaskanGalat($jawaban->status(), $jawaban->body()));
    }

    public function siap(): bool
    {
        return filled($this->alamat) && filled($this->sesi) && filled($this->kunciApi);
    }

    public function nama(): string
    {
        return 'openwa';
    }

    /**
     * Menerjemahkan kode jawaban menjadi keterangan yang dapat ditindaklanjuti.
     *
     * Keterangan ini tersimpan pada kolom error_message dan terbaca petugas,
     * sehingga harus menyebutkan tindakan yang perlu diambil, bukan sekadar
     * kode angka. Kode aslinya tetap disertakan untuk penelusuran.
     */
    protected function jelaskanGalat(int $kode, string $badan): string
    {
        $keterangan = match ($kode) {
            400     => 'Permintaan ditolak gerbang: sesi tidak aktif atau nomor tujuan tidak sah.',
            401,
            403     => 'Kunci API OpenWA ditolak. Periksa WHATSAPP_OPENWA_KUNCI pada berkas .env.',
            404     => 'Sesi "' . $this->sesi . '" tidak ditemukan pada gerbang.',
            409     => 'Sesi gerbang belum tersambung ke WhatsApp. Pindai ulang kode QR pada dasbor OpenWA.',
            429     => 'Gerbang membatasi laju pengiriman. Perbesar jeda antar pesan pada config/whatsapp.php.',
            default => $kode >= 500
                ? 'Gerbang OpenWA mengalami gangguan internal.'
                : 'Gerbang OpenWA menolak pesan.',
        };

        return $keterangan . ' (HTTP ' . $kode . ': ' . mb_substr(trim($badan), 0, 200) . ')';
    }
}
