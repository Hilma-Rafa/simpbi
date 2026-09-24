<?php

namespace App\Livewire;

use App\Filament\Pages\Riwayat;
use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
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

    /**
     * Dasar bersama seluruh isi lonceng: daftar, angka pada badge, dan toast.
     *
     * Penyaringan notifikasi yang sudah disingkirkan diletakkan di sini, bukan
     * di masing-masing pemakainya, supaya ketiganya tidak mungkin berbeda
     * pendapat — badge yang menghitung notifikasi yang tidak tampak di panel
     * adalah angka yang tidak dapat ditindaklanjuti pengguna.
     */
    protected function kueri()
    {
        return Notifikasi::query()
            ->dalamAplikasi()
            ->belumDisembunyikan()
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

    /**
     * Menandai satu notifikasi sebagai dibaca, lalu membuka tujuannya.
     *
     * Keduanya sengaja disatukan dalam satu permintaan Livewire, bukan
     * dibiarkan sebagai tautan <a href> polos yang bernavigasi sendiri:
     * navigasi lewat href berjalan seketika di peramban, sedangkan
     * permintaan Livewire baru selesai belakangan — perlombaan yang
     * membuat penandaan dibaca kerap tidak sempat tersimpan sebelum
     * halaman berpindah. Menunggu update ini selesai baru mengalihkan
     * memastikan urutannya selalu benar.
     */
    public function tandaiDibaca(int $id): void
    {
        $notifikasi = $this->kueri()->find($id);

        if (! $notifikasi) {
            return;
        }

        $this->kueri()->whereKey($id)->belumDibaca()->update(['dibaca_at' => now()]);

        if ($tautan = $this->tautan($notifikasi)) {
            $this->redirect($tautan);
        }
    }

    /**
     * Menyingkirkan satu notifikasi dari panel, tanpa menghapus barisnya.
     *
     * Panel lonceng adalah daftar hal yang masih perlu diperhatikan, sehingga
     * pengguna perlu dapat membersihkannya; sedangkan tabel `notifikasi`
     * merangkap rekam jejak pengiriman yang dibaca halaman Riwayat, sehingga
     * barisnya harus tetap ada. Kolom `disembunyikan_at` yang memisahkan kedua
     * kepentingan itu.
     *
     * `dibaca_at` sengaja tidak ikut diisi: menyingkirkan tanpa membuka bukan
     * berarti sudah membacanya, dan Riwayat sebaiknya mencatat keadaan itu apa
     * adanya. Angka pada badge tetap turun dengan sendirinya karena kueri()
     * yang sama sudah membuang notifikasi yang disingkirkan.
     *
     * Penulisan lewat kueri() membuatnya tersaring kepemilikan — notifikasi
     * milik pengguna lain tidak dapat disingkirkan meski idnya ditebak — dan
     * sekaligus membuat pemanggilan berulang tidak berakibat apa-apa.
     */
    public function sembunyikan(int $id): void
    {
        $this->kueri()->whereKey($id)->update(['disembunyikan_at' => now()]);
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
            // Rincian permintaan dibuka sebagai pop-up di atas Daftar Permintaan
            // Barang, bukan halaman detail /{id}. Susunan tautannya dipusatkan di
            // resource agar sama dengan pesan WhatsApp dan panel dasbor.
            'permintaan_barang' => PermintaanBarangResource::canAccess()
                ? (($permintaan = PermintaanBarang::find($notifikasi->referensi_id))
                    ? PermintaanBarangResource::urlRincian($permintaan)
                    : null)
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
