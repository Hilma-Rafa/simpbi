<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Pelaksana bawaan yang hanya mencatat pesan ke berkas log.
 *
 * Dipakai selama gerbang WhatsApp yang sebenarnya belum dipasang, sehingga
 * seluruh alur notifikasi — pembuatan baris berkanal whatsapp, antrean,
 * sampai penandaan status kirim — dapat dijalankan dan diuji tanpa nomor
 * WhatsApp, tanpa biaya, dan tanpa risiko pemblokiran nomor.
 *
 * Isi pesan ditulis ke storage/logs, jadi jelas terlihat bahwa pesan TIDAK
 * benar-benar terkirim ke ponsel siapa pun. Jangan dipakai di lingkungan
 * sebenarnya.
 */
class PengirimCatat implements PengirimWhatsApp
{
    public function kirim(string $tujuan, string $pesan): ?string
    {
        Log::channel(config('whatsapp.log_channel'))->info('Pesan WhatsApp (tidak dikirim, hanya dicatat)', [
            'tujuan' => $tujuan,
            'pesan'  => $pesan,
        ]);

        return null;
    }

    public function siap(): bool
    {
        return true;
    }

    public function nama(): string
    {
        return 'catat';
    }
}
