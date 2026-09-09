<?php

namespace App\Jobs;

use App\Models\Notifikasi;
use App\Services\WhatsApp\PengirimanGagal;
use App\Services\WhatsApp\PengirimWhatsApp;
use App\Support\NomorWhatsApp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengiriman satu baris notifikasi berkanal WhatsApp.
 *
 * Satu job menangani satu baris `notifikasi`, bukan satu kejadian, sebab satu
 * kejadian dapat berpenerima banyak dan kegagalan pada satu nomor tidak boleh
 * menggagalkan pengiriman ke nomor lain. Status setiap baris dicatat pada
 * kolom `status_kirim`, `dikirim_at`, dan `error_message` yang memang sudah
 * disiapkan pada rancangan tabel.
 */
class KirimPesanWhatsApp implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $notifikasiId)
    {
        $this->onQueue('whatsapp');
    }

    /** Banyaknya percobaan mengikuti pengaturan pemasangan. */
    public function tries(): int
    {
        return max(1, (int) config('whatsapp.percobaan', 3));
    }

    /** Jeda bertingkat antar percobaan, dalam detik. */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(PengirimWhatsApp $pengirim): void
    {
        $notifikasi = Notifikasi::with('user')->find($this->notifikasiId);

        // Baris yang sudah tidak berstatus menunggu berarti sudah pernah
        // ditangani. Pemeriksaan ini membuat percobaan ulang tidak mengirim
        // pesan yang sama dua kali kepada orang yang sama.
        if (! $notifikasi || $notifikasi->status_kirim !== 'pending') {
            return;
        }

        if (! $pengirim->siap()) {
            $this->tandaiGagal(
                $notifikasi,
                'Gerbang WhatsApp "' . $pengirim->nama() . '" belum terkonfigurasi.'
            );

            return;
        }

        $tujuan = NomorWhatsApp::normalkan($notifikasi->user?->no_hp);

        // Nomor kosong atau tidak masuk akal bukan galat sistem, melainkan data
        // induk yang belum lengkap, sehingga tidak perlu dicoba ulang.
        if (! $tujuan) {
            $this->tandaiGagal($notifikasi, 'Nomor WhatsApp pengguna belum diisi atau tidak dikenali.');

            return;
        }

        try {
            $penanda = $pengirim->kirim($tujuan, $this->susunPesan($notifikasi));

            $notifikasi->update([
                'status_kirim'  => 'terkirim',
                'dikirim_at'    => now(),
                'error_message' => $penanda ? 'Penanda gerbang: ' . $penanda : null,
            ]);
        } catch (PengirimanGagal $e) {
            $this->tandaiGagal($notifikasi, $e->getMessage());

            /*
             * Galat sengaja dilempar ulang HANYA bila antreannya benar-benar
             * berjalan di latar belakang, supaya pekerja antrean mencoba lagi.
             * Pada sambungan `sync` — yang dipakai selama pengembangan — job
             * dijalankan di dalam permintaan HTTP yang sama, sehingga galat
             * yang dilempar akan menggagalkan aksi persetujuan yang sedang
             * dikerjakan pengguna. Notifikasi tidak boleh sampai merusak alur
             * transaksi; statusnya sudah tercatat gagal dan itu memadai.
             */
            if (config('queue.default') !== 'sync') {
                throw $e;
            }
        }
    }

    /** Dipanggil ketika seluruh percobaan habis. */
    public function failed(?Throwable $e): void
    {
        $notifikasi = Notifikasi::find($this->notifikasiId);

        if ($notifikasi && $notifikasi->status_kirim !== 'terkirim') {
            $notifikasi->update([
                'status_kirim'  => 'gagal',
                'error_message' => $e?->getMessage(),
            ]);
        }
    }

    /**
     * Menyusun isi pesan.
     *
     * Judul dan isi digabung karena WhatsApp tidak mengenal judul terpisah,
     * lalu ditutup nama sistem supaya penerima langsung tahu asal pesannya —
     * penting karena pesan datang dari nomor yang belum tentu mereka kenal.
     */
    protected function susunPesan(Notifikasi $notifikasi): string
    {
        return "*{$notifikasi->judul}*\n\n{$notifikasi->pesan}\n\n_SIMPBI — BPS Kota Jakarta Barat_";
    }

    protected function tandaiGagal(Notifikasi $notifikasi, string $alasan): void
    {
        $notifikasi->update([
            'status_kirim'  => 'gagal',
            'error_message' => $alasan,
        ]);

        Log::channel(config('whatsapp.log_channel'))->warning('Notifikasi WhatsApp gagal dikirim', [
            'notifikasi_id' => $notifikasi->id,
            'alasan'        => $alasan,
        ]);
    }
}
