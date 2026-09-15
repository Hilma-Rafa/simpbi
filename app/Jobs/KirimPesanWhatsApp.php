<?php

namespace App\Jobs;

use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\BastMutasiAset;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Services\WhatsApp\PengirimanGagal;
use App\Services\WhatsApp\PengirimWhatsApp;
use App\Support\NomorWhatsApp;
use App\Support\PengalihanWhatsApp;
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

        // Selama pengalihan menyala, tujuan pengiriman bukan lagi nomor
        // penggunanya melainkan satu nomor yang ditetapkan Administrator.
        // Nomor pada akun pegawai tidak dibaca sama sekali, sehingga peragaan
        // tidak pernah menyentuh nomor Ketua Tim yang sebenarnya.
        $alihkanKe = PengalihanWhatsApp::nomor();
        $tujuan    = $alihkanKe ?? NomorWhatsApp::normalkan($notifikasi->user?->no_hp);

        // Nomor kosong atau tidak masuk akal bukan galat sistem, melainkan data
        // induk yang belum lengkap, sehingga tidak perlu dicoba ulang.
        if (! $tujuan) {
            $this->tandaiGagal($notifikasi, 'Nomor WhatsApp pengguna belum diisi atau tidak dikenali.');

            return;
        }

        try {
            $penanda = $pengirim->kirim(
                $tujuan,
                $this->susunPesan($notifikasi, dialihkan: $alihkanKe !== null),
            );

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
    protected function susunPesan(Notifikasi $notifikasi, bool $dialihkan = false): string
    {
        $bagian = [];

        // Pada pesan yang dialihkan, penerima sebenarnya disebutkan lebih dulu.
        // Tanpa itu seluruh pesan peragaan tiba di satu nomor tanpa dapat
        // dibedakan, sehingga justru tidak memperlihatkan bahwa alurnya menyasar
        // orang yang tepat — padahal itulah yang hendak ditunjukkan.
        if ($dialihkan) {
            $bagian[] = '[Demo — seharusnya untuk ' . $this->penerimaSebenarnya($notifikasi) . ']';
            $bagian[] = '';
        }

        $bagian[] = "*{$notifikasi->judul}*";
        $bagian[] = '';
        $bagian[] = $notifikasi->pesan;

        if ($tautan = $this->tautanTindakan($notifikasi)) {
            $bagian[] = '';
            $bagian[] = 'Buka: ' . $tautan;
        }

        $bagian[] = '';
        $bagian[] = '_SIMPBI — BPS Kota Jakarta Barat_';

        return implode("\n", $bagian);
    }

    /**
     * Sebutan penerima sebenarnya, untuk dicantumkan pada pesan yang dialihkan.
     *
     * Peran dan nama tim ikut disebut karena nama orang saja belum tentu cukup
     * bagi yang menyaksikan peragaan: yang hendak ditunjukkan bukan siapa
     * namanya, melainkan bahwa pesan menyasar jabatan yang benar pada tahap
     * yang sedang berjalan.
     */
    protected function penerimaSebenarnya(Notifikasi $notifikasi): string
    {
        $pengguna = $notifikasi->user;

        if (! $pengguna) {
            return 'penerima yang sudah tidak ada';
        }

        $peran = UserForm::ROLE_OPTIONS[$pengguna->role] ?? $pengguna->role;
        $tim   = $pengguna->tim?->nama_tim;

        // Peran yang melayani seluruh kantor tidak bertim, sehingga tidak perlu
        // ditempeli keterangan tim yang kosong.
        return $pengguna->name . ' (' . $peran . ($tim ? ' ' . $tim : '') . ')';
    }

    /**
     * Tautan ke halaman yang perlu dibuka penerima, bila memang ada yang harus
     * dikerjakan.
     *
     * Notifikasi yang sifatnya kabar — permintaan selesai, ditolak, atau
     * kedaluwarsa — sengaja tidak diberi tautan: tidak ada yang perlu dibuka,
     * dan makin sedikit tautan yang beredar makin kecil pula kemiripan pesan
     * dengan pola sebaran yang dicurigai WhatsApp.
     *
     * Kebutuhan tindakan tidak disimpan sebagai kolom baru pada baris
     * notifikasi, melainkan dibaca dari keadaan transaksinya pada saat pesan
     * dikirim. Dengan begitu tabel `notifikasi` tidak perlu berubah, dan
     * tautan tidak pernah mengajak membuka sesuatu yang ternyata sudah
     * ditangani orang lain lebih dulu.
     */
    protected function tautanTindakan(Notifikasi $notifikasi): ?string
    {
        if (! $notifikasi->referensi_id) {
            return null;
        }

        return match ($notifikasi->referensi_tabel) {
            'permintaan_barang' => $this->tautanPermintaan((int) $notifikasi->referensi_id),
            'bast_mutasi_aset'  => $this->tautanBast((int) $notifikasi->referensi_id),

            // Peringatan stok tidak diberi tautan karena tindak lanjutnya
            // berbeda menurut peran — Petugas Gudang mencatat stok masuk,
            // sedangkan Kasubbag menimbang pengadaan — sehingga tidak ada satu
            // halaman yang tepat untuk semua penerimanya.
            default => null,
        };
    }

    protected function tautanPermintaan(int $id): ?string
    {
        $permintaan = PermintaanBarang::find($id);

        if (! $permintaan || in_array($permintaan->status, PermintaanBarang::STATUS_RIWAYAT, true)) {
            return null;
        }

        // Panel disebut tegas karena job berjalan di luar permintaan HTTP,
        // sehingga Filament tidak mengetahui panel mana yang sedang aktif.
        return PermintaanBarangResource::getUrl('detail', ['record' => $id], panel: 'admin');
    }

    protected function tautanBast(int $id): ?string
    {
        $bast = BastMutasiAset::find($id);

        if (! $bast || $bast->status === 'selesai_administratif') {
            return null;
        }

        // Mutasi aset belum memiliki halaman rinci tersendiri, sehingga
        // penerima diarahkan ke daftarnya.
        return BastMutasiAsetResource::getUrl('index', panel: 'admin');
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
