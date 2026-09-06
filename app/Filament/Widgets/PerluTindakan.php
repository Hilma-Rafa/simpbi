<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\PermintaanBarang;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Daftar pekerjaan yang membutuhkan tindakan pengguna yang sedang masuk.
 *
 * Panel ini berfungsi sebagai jalan pintas menuju pekerjaan prioritas,
 * bukan sebagai pengganti halaman Permintaan Barang. Oleh sebab itu
 * jumlah yang ditampilkan dibatasi dan informasinya diringkas.
 *
 * Isi panel menyesuaikan peran pengguna:
 *   Kasubbag Umum  : persetujuan akhir dan pengesahan
 *   Ketua Tim      : persetujuan permintaan timnya
 *   Petugas Gudang : verifikasi ketersediaan dan penyiapan barang
 *   Tim            : konfirmasi penerimaan barang
 */
class PerluTindakan extends Widget
{
    protected string $view = 'filament.widgets.perlu-tindakan';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
    'default' => 'full',
    'lg'      => 1,
    ];

    /** Jumlah pekerjaan yang ditampilkan pada panel. */
    protected const BATAS_TAMPIL = 5;

    public static function canView(): bool
    {
        return filled(auth()->user()?->role);
    }

    /** Status yang membutuhkan tindakan, menurut peran pengguna. */
    protected function statusMenurutPeran(): array
    {
        return match (auth()->user()->role) {
            'kasubbag'       => ['menunggu_kasubbag', 'menunggu_pengesahan'],
            'ketua_tim'      => ['menunggu_ketua'],
            'petugas_gudang' => ['menunggu_verifikasi', 'siap_diproses'],
            'tim'            => ['siap_diambil'],
            default          => [],
        };
    }

    protected function kueri()
    {
        $user   = auth()->user();
        $status = $this->statusMenurutPeran();

        if (empty($status)) {
            return PermintaanBarang::query()->whereRaw('1 = 0');
        }

        return PermintaanBarang::query()
            ->with('tim')
            ->withCount('detail')
            ->whereIn('status', $status)
            // Ketua Tim dan Tim hanya melihat permintaan dari timnya sendiri
            ->when(
                in_array($user->role, ['ketua_tim', 'tim']),
                fn ($q) => $q->where('tim_pemohon_id', $user->tim_id)
            )
            // Yang melewati batas waktu berada paling atas, disusul yang terdekat
            ->orderByRaw('hold_expired_at IS NULL')
            ->orderBy('hold_expired_at')
            ->orderBy('created_at');
    }

    public function getTotalProperty(): int
    {
        return $this->kueri()->count();
    }

    public function getPekerjaanProperty(): Collection
    {
        return $this->kueri()
            ->limit(self::BATAS_TAMPIL)
            ->get()
            ->map(fn (PermintaanBarang $p) => [
                'id'        => $p->id,
                'kode'      => $p->kode_permintaan,
                'tim'       => $p->tim?->nama_tim ?? '-',
                'pemohon'   => $p->nama_pemohon,
                'item'      => $p->detail_count,
                'tahap'     => $this->namaTahap($p->status),
                'ikonTahap' => $this->ikonTahap($p->status),
                'aksi'      => $this->labelAksi($p->status),
                'batas'     => $p->hold_expired_at,
                'urgensi'   => $this->urgensi($p->hold_expired_at),
                'sisa'      => $this->sisaWaktu($p->hold_expired_at),
                'tautan'    => $this->tautanKe($p),
            ]);
    }

    public function getTautanSemuaProperty(): string
    {
        return PermintaanBarangResource::getUrl('index');
    }

    /**
     * Tautan menuju halaman Permintaan Barang yang telah disaring pada
     * satu permintaan tertentu, sehingga pengguna tidak perlu mencarinya
     * kembali di dalam daftar.
     *
     * Penyaringan memanfaatkan parameter pencarian bawaan tabel Filament.
     */
    protected function tautanKe(PermintaanBarang $permintaan): string
    {
        return PermintaanBarangResource::getUrl('index', [
            'tableSearch' => $permintaan->kode_permintaan,
        ]);
    }

    /** Nama tahap yang membutuhkan tindakan. */
    protected function namaTahap(string $status): string
    {
        return match ($status) {
            'menunggu_ketua'       => 'Persetujuan Ketua Tim',
            'menunggu_verifikasi'  => 'Verifikasi Ketersediaan',
            'menunggu_kasubbag'    => 'Persetujuan Akhir',
            'siap_diproses'        => 'Penyiapan Barang',
            'siap_diambil'         => 'Konfirmasi Penerimaan',
            'menunggu_pengesahan'  => 'Pengesahan Akhir',
            default                => $status,
        };
    }

    protected function ikonTahap(string $status): string
    {
        return match ($status) {
            'menunggu_ketua', 'menunggu_kasubbag' => 'heroicon-m-check-circle',
            'menunggu_verifikasi'                 => 'heroicon-m-clipboard-document-check',
            'siap_diproses'                       => 'heroicon-m-archive-box',
            'siap_diambil'                        => 'heroicon-m-hand-thumb-up',
            'menunggu_pengesahan'                 => 'heroicon-m-check-badge',
            default                               => 'heroicon-m-clock',
        };
    }

    /** Label tombol disesuaikan dengan pekerjaan yang harus dilakukan. */
    protected function labelAksi(string $status): string
    {
        return match ($status) {
            'menunggu_ketua'      => 'Tinjau',
            'menunggu_verifikasi' => 'Verifikasi',
            'siap_diproses'       => 'Siapkan',
            'siap_diambil'        => 'Konfirmasi',
            default               => 'Proses',
        };
    }

    /** Tingkat urgensi berdasarkan sisa waktu tahapan. */
    protected function urgensi(?\Illuminate\Support\Carbon $batas): string
    {
        if (! $batas) {
            return 'netral';
        }

        if ($batas->isPast()) {
            return 'lewat';
        }

        return $batas->diffInHours() < 2 ? 'mendesak' : 'wajar';
    }

    protected function sisaWaktu(?\Illuminate\Support\Carbon $batas): string
    {
        if (! $batas) {
            return 'Tanpa batas waktu';
        }

        return $batas->isPast()
            ? 'Melewati batas ' . $batas->diffForHumans(null, true)
            : 'Tersisa ' . $batas->diffForHumans(null, true);
    }
}