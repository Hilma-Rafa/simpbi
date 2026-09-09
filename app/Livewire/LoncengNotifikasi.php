<?php

namespace App\Livewire;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\Notifikasi;
use Filament\Notifications\Notification as PemberitahuanLayar;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Lonceng notifikasi pada bilah atas.
 *
 * Membaca tabel `notifikasi` milik proyek, bukan tabel notifikasi bawaan
 * Laravel, sehingga memakai mekanisme yang memang sudah dirancang pada ERD.
 * Isinya sudah role-aware dengan sendirinya: setiap baris ditujukan kepada
 * satu user_id, dan penentuan penerimanya dilakukan NotifikasiService menurut
 * peran serta tim yang berkepentingan.
 *
 * Pembaruan berjalan tanpa memuat ulang halaman melalui penjadwalan berkala
 * Livewire. Cara ini dipilih daripada websocket karena proyek belum memakai
 * server siaran (Reverb/Pusher); bila kelak dipasang, hanya metode periksa()
 * yang perlu dipicu oleh peristiwa siaran.
 */
class LoncengNotifikasi extends Component
{
    /** Jumlah notifikasi yang ditampilkan pada panel lonceng. */
    protected const BATAS_TAMPIL = 8;

    /** Selang pemeriksaan notifikasi baru. */
    protected const SELANG = '15s';

    /** Id notifikasi terbaru yang sudah pernah ditampilkan sebagai toast. */
    public ?int $terakhirDilihat = null;

    public function mount(): void
    {
        // Notifikasi yang sudah ada sebelum halaman dibuka tidak dimunculkan
        // sebagai toast, agar pengguna tidak dibanjiri pesan lama.
        $this->terakhirDilihat = $this->kueri()->max('id');
    }

    protected function kueri()
    {
        return Notifikasi::query()
            ->dalamAplikasi()
            ->where('user_id', auth()->id());
    }

    /** @return Collection<int,Notifikasi> */
    public function getDaftarProperty(): Collection
    {
        return $this->kueri()
            ->latest('id')
            ->limit(self::BATAS_TAMPIL)
            ->get();
    }

    public function getJumlahBelumDibacaProperty(): int
    {
        return $this->kueri()->belumDibaca()->count();
    }

    /**
     * Dipanggil berkala oleh Livewire. Notifikasi yang baru tiba sejak
     * pemeriksaan terakhir dimunculkan sebagai toast di sudut layar.
     */
    public function periksa(): void
    {
        $baru = $this->kueri()
            ->belumDibaca()
            ->when($this->terakhirDilihat, fn ($q) => $q->where('id', '>', $this->terakhirDilihat))
            ->orderBy('id')
            ->get();

        if ($baru->isEmpty()) {
            return;
        }

        foreach ($baru as $notifikasi) {
            $tampilan = $notifikasi->tampilan();

            PemberitahuanLayar::make()
                ->title($notifikasi->judul)
                ->body($notifikasi->pesan)
                ->icon($tampilan['ikon'])
                ->iconColor($tampilan['warna'])
                ->color($tampilan['warna'])
                ->duration(8000)
                ->send();
        }

        $this->terakhirDilihat = $baru->last()->id;
    }

    public function tandaiDibaca(int $id): void
    {
        $this->kueri()->where('id', $id)->belumDibaca()->update(['dibaca_at' => now()]);
    }

    public function tandaiSemuaDibaca(): void
    {
        $this->kueri()->belumDibaca()->update(['dibaca_at' => now()]);

        PemberitahuanLayar::make()
            ->title('Semua notifikasi ditandai telah dibaca')
            ->success()
            ->send();
    }

    /**
     * Membuka data yang dirujuk notifikasi, bila pengguna memang berwenang
     * membukanya. Kewenangan diambil dari resource terkait, bukan aturan baru.
     */
    public function tautan(Notifikasi $notifikasi): ?string
    {
        return match ($notifikasi->referensi_tabel) {
            'permintaan_barang' => PermintaanBarangResource::canAccess()
                ? PermintaanBarangResource::getUrl('detail', ['record' => $notifikasi->referensi_id])
                : null,

            'barang_persediaan' => BarangPersediaanResource::canAccess()
                ? BarangPersediaanResource::getUrl('index')
                : (Riwayat::canAccess() ? Riwayat::getUrl(['jenis' => 'mutasi_stok']) : null),

            'bast_mutasi_aset' => BastMutasiAsetResource::canAccess()
                ? BastMutasiAsetResource::getUrl('index')
                : null,

            default => null,
        };
    }

    public function selang(): string
    {
        return self::SELANG;
    }

    public function render()
    {
        return view('livewire.lonceng-notifikasi');
    }
}
