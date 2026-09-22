<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\KartuKendali;
use App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource;
use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use Filament\Widgets\Widget;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

/**
 * Perbandingan aktivitas antar tim kerja (Instruksi §35 dan §43).
 *
 * Batang horizontal yang membandingkan tim kerja pada salah satu dari dua
 * besaran: banyaknya permintaan yang diajukan, atau banyaknya barang yang
 * benar-benar disalurkan kepadanya. Keterangan lengkap kedua besaran beserta
 * butir rancangan yang memintanya ada pada properti $metrik di bawah.
 * Seluruh tim kerja aktif ditampilkan, termasuk yang belum mengajukan
 * permintaan pada periode terpilih, sebab konteks panel ini adalah
 * perbandingan antar tim kerja — tim yang tidak mengajukan pun merupakan
 * informasi. Karena membandingkan antar tim, panjang batang di sini
 * diukur terhadap tim dengan permintaan terbanyak, berbeda dengan panel
 * Kondisi Stok yang mengukur terhadap stok kategorinya sendiri.
 */
class PermintaanPerTim extends Widget
{
    protected string $view = 'filament.widgets.permintaan-per-tim';

    protected static ?int $sort = 4;

    // Setengah lebar pada kisi dua belas kolom dasbor (App\Filament\Pages\Dashboard)
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 6,
    ];

    /** Periode pengamatan yang sedang dipilih (Instruksi §43). */
    public string $periode = '30';

    /**
     * Besaran yang sedang dibandingkan antar tim kerja.
     *
     * Dua besaran disediakan karena rancangan memang meminta keduanya, dan
     * keduanya menjawab pertanyaan yang berbeda:
     *
     *  - `permintaan` menjawab tim kerja yang paling aktif mengajukan
     *    permintaan, yaitu butir monitoring pola permintaan beserta keluaran
     *    "intensitas permintaan tertinggi".
     *  - `keluar` menjawab "jumlah yang disalurkan" pada butir monitoring
     *    distribusi, rekap pengeluaran barang per tim kerja pada butir
     *    pelaporan operasional, dan keluaran rekap distribusi barang per
     *    tim kerja.
     *
     * Rancangan menuliskan butir-butir itu dengan istilah lama bagi tim kerja;
     * di sini dipakai istilah yang berlaku sekarang, sejalan dengan
     * penyeragaman istilah pada temuan T-19.
     *
     * Keduanya ditaruh pada satu panel, bukan dua panel berdampingan, sebab
     * sumbunya sama persis — tim kerja — sehingga dua panel hanya akan
     * menghasilkan dua batang kembar yang beradu untuk ruang yang sama.
     */
    public string $metrik = 'permintaan';

    public static function canView(): bool
    {
        return auth()->user()?->role === 'kasubbag';
    }

    /** @return array<string, string> */
    public function getPilihanPeriodeProperty(): array
    {
        return [
            '30'  => '30 hari terakhir',
            '90'  => '90 hari terakhir',
            'semua' => 'Seluruh periode',
        ];
    }

    /** @return array<string, string> */
    public function getPilihanMetrikProperty(): array
    {
        return [
            'permintaan' => 'Jumlah permintaan',
            'keluar'     => 'Barang disalurkan',
        ];
    }

    /** Judul panel mengikuti besaran yang sedang ditampilkan. */
    public function getJudulProperty(): string
    {
        return $this->metrik === 'keluar'
            ? 'Pengeluaran per Tim Kerja'
            : 'Permintaan per Tim Kerja';
    }

    public function getKeteranganProperty(): string
    {
        return $this->metrik === 'keluar'
            ? 'Banyaknya barang yang disalurkan ke tiap tim kerja'
            : 'Jumlah permintaan per tim kerja';
    }

    /** Satuan yang ditulis di belakang angka pada tiap baris dan pada kaki panel. */
    public function getSatuanProperty(): string
    {
        return $this->metrik === 'keluar' ? 'unit barang' : 'permintaan';
    }

    public function getTimProperty(): Collection
    {
        $jumlah = $this->metrik === 'keluar'
            ? $this->cacahBarangKeluar()
            : $this->cacahPermintaan();

        $daftar = Tim::query()
            ->where('status_aktif', true)
            ->orderBy('nama_tim')
            ->get(['id', 'nama_tim'])
            ->map(fn (Tim $t) => [
                'id'     => $t->id,
                'nama'   => $t->nama_tim,
                'jumlah' => (int) ($jumlah[$t->id] ?? 0),
            ])
            ->sortByDesc('jumlah');

        // Pembanding adalah tim dengan permintaan terbanyak pada periode ini
        $tertinggi = max(1, (int) $daftar->max('jumlah'));

        return $daftar->map(fn (array $t) => $t + [
            'lebar'  => round($t['jumlah'] / $tertinggi * 100, 1),
            'tautan' => $this->tautanTim($t['id']),
        ])->values();
    }

    /**
     * Banyaknya permintaan per tim kerja, satu kueri agregat.
     *
     * @return Collection<int,int> [tim_id => jumlah]
     */
    protected function cacahPermintaan(): Collection
    {
        return PermintaanBarang::query()
            ->when(
                $this->batasWaktu(),
                fn ($q, $batas) => $q->where('created_at', '>=', $batas)
            )
            ->selectRaw('tim_pemohon_id, COUNT(*) as jumlah')
            ->groupBy('tim_pemohon_id')
            ->pluck('jumlah', 'tim_pemohon_id');
    }

    /**
     * Banyaknya barang yang benar-benar keluar untuk tiap tim kerja.
     *
     * Dibaca dari buku besar mutasi stok, bukan dari jumlah yang disetujui
     * pada rincian permintaan. Buku besarlah catatan barang meninggalkan
     * gudang, dan sumber yang sama dipakai Kartu Kendali — sehingga angka
     * pada panel ini dan pada kartu tidak mungkin berselisih. Permintaan yang
     * ditolak atau kedaluwarsa dengan sendirinya tidak terhitung, sebab tidak
     * pernah menghasilkan baris pengeluaran.
     *
     * Penautannya lewat `referensi_tabel` dan `referensi_id`, yaitu cara tabel
     * mutasi merujuk transaksi asalnya. Tanggal yang dipakai adalah tanggal
     * mutasi, bukan tanggal permintaan diajukan, sebab yang diukur di sini
     * adalah waktu distribusinya.
     *
     * Jumlah pada baris keluar bernilai negatif, sehingga tandanya dibalik.
     *
     * @return Collection<int,int> [tim_id => jumlah unit barang]
     */
    protected function cacahBarangKeluar(): Collection
    {
        return MutasiStok::query()
            ->join('permintaan_barang', function (JoinClause $join): void {
                $join->on('permintaan_barang.id', '=', 'mutasi_stok.referensi_id')
                    ->where('mutasi_stok.referensi_tabel', '=', 'permintaan_barang');
            })
            ->where('mutasi_stok.jenis', 'keluar')
            ->when(
                $this->batasWaktu(),
                fn ($q, $batas) => $q->where('mutasi_stok.tanggal', '>=', $batas->toDateString())
            )
            ->selectRaw('permintaan_barang.tim_pemohon_id as tim_pemohon_id, SUM(-mutasi_stok.jumlah) as jumlah')
            ->groupBy('permintaan_barang.tim_pemohon_id')
            ->pluck('jumlah', 'tim_pemohon_id');
    }

    public function getTotalProperty(): int
    {
        return (int) $this->tim->sum('jumlah');
    }

    /**
     * Batas awal periode, atau null apabila seluruh periode dipilih.
     *
     * Dipatok pada awal hari, bukan pada jam saat panel dibuka. Kedua besaran
     * panel ini menyaring kolom yang berbeda jenisnya — `created_at` bertanda
     * waktu, `mutasi_stok.tanggal` hanya bertanggal — sehingga tanpa patokan
     * awal hari keduanya mengamati rentang yang berselisih hampir sehari, dan
     * berganti besaran diam-diam menggeser jendelanya.
     */
    protected function batasWaktu(): ?\Illuminate\Support\Carbon
    {
        return match ($this->periode) {
            '30' => now()->subDays(30)->startOfDay(),
            '90' => now()->subDays(90)->startOfDay(),
            default => null,
        };
    }

    /**
     * Tautan baris, hanya ketika ada halaman yang benar-benar mewakili angkanya.
     *
     * Pada besaran "jumlah permintaan" angka tiap baris adalah banyaknya
     * dokumen, dan daftar Permintaan Barang tersaring tim itu menampilkan
     * persis dokumen-dokumen tersebut — tautannya menjelaskan angkanya.
     *
     * Pada besaran "barang disalurkan" tidak ada halaman semacam itu. Buku
     * besar mutasi stok tidak dapat disaring per tim kerja, dan daftar
     * permintaan akan menampilkan belasan dokumen di bawah angka yang berbunyi
     * ratusan unit — dua bilangan yang tidak berhubungan langsung, sehingga
     * tautannya justru membingungkan. Barisnya karena itu dibiarkan tanpa
     * tautan, dan judul panel yang mengarahkan ke Kartu Kendali, yaitu rekap
     * barang keluar yang memang tersedia.
     */
    protected function tautanTim(int $timId): ?string
    {
        if ($this->metrik === 'keluar') {
            return null;
        }

        return PermintaanBarangResource::getUrl('index', [
            'filters' => ['tim_pemohon_id' => ['value' => $timId]],
        ]);
    }

    /** Tujuan tautan judul panel, mengikuti besaran yang sedang ditampilkan. */
    public function getTautanSemuaProperty(): string
    {
        return $this->metrik === 'keluar'
            ? KartuKendali::getUrl()
            : PermintaanBarangResource::getUrl('index');
    }
}
