<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\KatalogBarang;
use App\Filament\Resources\BarangPersediaans\BarangPersediaanResource;
use App\Models\BarangPersediaan;
use App\Models\Kategori;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Kondisi persediaan per kategori barang (Instruksi §44).
 *
 * Menyajikan hubungan antara stok fisik, stok yang sedang dikunci oleh
 * permintaan berjalan, dan stok yang dapat diminta. Setiap batang mewakili
 * stok fisik kategori itu sendiri, sehingga lebar penuh selalu bernilai
 * seratus persen dan terbagi atas bagian tersedia dan bagian terkunci.
 * Perbandingan antar kategori tidak dilakukan pada panel ini, sebab jumlah
 * satuan tiap kategori tidak sebanding satu sama lain.
 *
 * Seluruh kategori persediaan ditampilkan, termasuk yang belum memiliki
 * stok, agar panel dapat dibaca sebagai daftar kelengkapan persediaan.
 * Panel dibatasi tingginya dan digulir, mengikuti Instruksi §45 yang
 * mengutamakan tata letak sederhana dan stabil.
 */
class KondisiStok extends Widget
{
    protected string $view = 'filament.widgets.kondisi-stok';

    protected static ?int $sort = 3;

    // Setengah lebar pada kisi dua belas kolom dasbor (App\Filament\Pages\Dashboard)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 6,
    ];

    /** Jumlah barang terkunci yang dirinci pada keterangan hover. */
    protected const BATAS_RINCIAN_HOLD = 6;

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['kasubbag', 'petugas_gudang']);
    }

    public function getKategoriProperty(): Collection
    {
        $ringkasan = Kategori::query()
            ->where('tipe', 'persediaan')
            ->withSum(
                ['barang as total_fisik' => fn ($q) => $q->where('status_aktif', true)],
                'stok_fisik'
            )
            ->withSum(
                ['barang as total_hold' => fn ($q) => $q->where('status_aktif', true)],
                'stok_hold'
            )
            ->orderBy('nama_kategori')
            ->get()
            // Kategori yang paling terdampak penguncian berada di urutan atas,
            // disusul kategori dengan stok fisik terbesar. Kategori tanpa stok
            // tetap ditampilkan pada urutan terakhir.
            ->sortByDesc(fn ($k) => [(int) $k->total_hold, (int) $k->total_fisik]);

        $rincianHold = $this->rincianHold();

        return $ringkasan->map(function (Kategori $k) use ($rincianHold) {
            $fisik    = (int) $k->total_fisik;
            $terkunci = min((int) $k->total_hold, $fisik);
            $tersedia = max(0, $fisik - $terkunci);

            return [
                'id'            => $k->id,
                'nama'          => $k->nama_kategori,
                'fisik'         => $fisik,
                'terkunci'      => $terkunci,
                'tersedia'      => $tersedia,
                // Batang selalu mewakili stok fisik kategori yang bersangkutan
                'lebarTersedia' => $fisik > 0 ? round($tersedia / $fisik * 100, 1) : 0,
                'lebarTerkunci' => $fisik > 0 ? round($terkunci / $fisik * 100, 1) : 0,
                'daftarHold'    => $rincianHold->get($k->id, collect()),
                'tautan'        => $this->tautanKategori($k->id),
            ];
        })->values();
    }

    /**
     * Rincian barang yang sedang dikunci, dikelompokkan menurut kategori.
     *
     * Diambil sekali untuk seluruh kategori agar panel tidak menimbulkan
     * kueri berulang sebanyak jumlah kategori yang ditampilkan.
     */
    protected function rincianHold(): Collection
    {
        return BarangPersediaan::query()
            ->where('status_aktif', true)
            ->where('stok_hold', '>', 0)
            ->orderByDesc('stok_hold')
            ->get(['kategori_id', 'nama_barang', 'satuan', 'stok_hold'])
            ->groupBy('kategori_id')
            ->map(fn (Collection $barang) => $barang
                ->take(self::BATAS_RINCIAN_HOLD)
                ->map(fn ($b) => [
                    'nama'   => $b->nama_barang,
                    'jumlah' => $b->stok_hold . ' ' . $b->satuan,
                ])
                ->values());
    }

    /**
     * Tautan daftar barang dengan penyaring kategori telah diterapkan.
     *
     * Pengguna yang berhak membuka Katalog Barang diarahkan ke sana, sebab
     * di halaman itulah barang dapat langsung diminta. Peran pengelola
     * diarahkan ke daftar Barang Persediaan yang setara.
     */
    protected function tautanKategori(?int $kategoriId = null): string
    {
        $penyaring = $kategoriId === null
            ? []
            // Nama parameter mengikuti pengikatan URL bawaan Filament, yaitu
            // "filters", bukan nama properti Livewire-nya.
            : ['filters' => ['kategori_id' => ['value' => $kategoriId]]];

        return KatalogBarang::canAccess()
            ? KatalogBarang::getUrl($penyaring)
            : BarangPersediaanResource::getUrl('index', $penyaring);
    }

    public function getTautanSemuaProperty(): string
    {
        return $this->tautanKategori();
    }

    /**
     * Apakah judul panel dan nama kategori ditampilkan sebagai tautan (A-013).
     *
     * Tautannya menuju Katalog Barang atau daftar Barang Persediaan (lihat
     * tautanKategori()). Petugas Gudang tidak berhak membuka keduanya, sehingga
     * baginya semua tautan itu berujung 403; panel yang sama tampil tanpa
     * tautan, dengan angka dan isi yang tidak berubah.
     */
    public function getBolehMenautkanProperty(): bool
    {
        return KatalogBarang::canAccess() || BarangPersediaanResource::canAccess();
    }

    /** Ringkasan satu baris pada kaki panel. */
    public function getRingkasProperty(): array
    {
        return [
            'kategori'       => Kategori::where('tipe', 'persediaan')->count(),
            'barangTerkunci' => BarangPersediaan::where('status_aktif', true)
                ->where('stok_hold', '>', 0)
                ->count(),
        ];
    }
}
