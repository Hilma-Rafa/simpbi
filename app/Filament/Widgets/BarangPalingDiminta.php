<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\KartuKendali;
use App\Filament\Pages\Riwayat;
use App\Models\DetailPermintaanBarang;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Barang yang paling sering diminta tim kerja.
 *
 * Menjawab butir monitoring pola permintaan pada revisi sempro — "barang yang
 * paling sering diminta" — dan keluaran sistem "Informasi barang yang paling
 * sering diminta", serta visualisasi "barang dengan permintaan tertinggi" pada
 * UC-22. Ketiganya berbicara tentang sisi **permintaan**, bukan sisi gudang,
 * sehingga panel ini membaca rincian permintaan dan bukan buku besar mutasi.
 *
 * Peringkatnya memakai **frekuensi**, yaitu banyaknya permintaan yang memuat
 * barang tersebut, sesuai kata "paling sering". Jumlah yang diminta ikut
 * ditampilkan sebagai angka pendamping, sebab satu barang yang diminta sepuluh
 * kali masing-masing satu rim berbeda artinya bagi perencanaan pengadaan
 * daripada barang yang diminta sekali sebanyak sepuluh rim.
 *
 * Yang sengaja tidak dihitung di sini adalah "barang yang paling banyak
 * keluar" pada butir pelaporan operasional. Angka itu sudah disajikan kolom
 * Keluar pada halaman Kartu Kendali, yang memang merupakan rekap operasionalnya,
 * dan mengulanginya di dasbor hanya akan menghasilkan dua angka yang beradu
 * untuk pertanyaan yang sama.
 *
 * Bentuknya mengikuti panel Permintaan per Tim Kerja: daftar berperingkat
 * dengan batang pembanding, bukan bagan. Peringkat dibaca dari urutan dan
 * panjang batang, sedangkan nama barang terlalu panjang untuk menjadi label
 * sumbu bagan batang.
 */
class BarangPalingDiminta extends Widget
{
    protected string $view = 'filament.widgets.barang-paling-diminta';

    /**
     * Ditempatkan sesudah bagan Pola Permintaan, sehingga ketiga panel analisis
     * duduk berurutan pada bagian bawah dasbor, di bawah panel operasional.
     */
    protected static ?int $sort = 7;

    /**
     * Lima dari dua belas kolom, berpasangan dengan bagan Tren Konsumsi yang
     * mengambil tujuh sisanya. Pembagiannya sengaja tidak sama rata: daftar
     * ini hanya memerlukan lebar secukupnya untuk nama barang, sedangkan
     * kepala bagan di sebelahnya harus memuat judul, keterangan, dan kotak
     * pilihan kategori yang isinya panjang pada satu baris yang sama.
     */
    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg'      => 5,
    ];

    /**
     * Sepuluh teratas. Katalog memuat lebih dari seratus barang, sehingga
     * menampilkan seluruhnya mengubah peringkat menjadi daftar biasa; sepuluh
     * cukup untuk melihat pola tanpa perlu menggulir jauh.
     */
    protected const BATAS_TAMPIL = 10;

    /** Periode pengamatan, pilihannya disamakan dengan panel analisis lain. */
    public string $periode = '30';

    /**
     * Kasubbag Umum saja, mengikuti UC-22 yang menetapkannya sebagai aktor
     * utama analisis pola permintaan. Petugas Gudang sudah memiliki panel
     * Barang Perlu Perhatian untuk kebutuhan penyiapan dan pengadaannya,
     * sedangkan Tim dan Ketua Tim tidak berkepentingan atas peringkat lintas
     * tim kerja.
     */
    public static function canView(): bool
    {
        return auth()->user()?->role === 'kasubbag';
    }

    /** @return array<string, string> */
    public function getPilihanPeriodeProperty(): array
    {
        return [
            '30'    => '30 hari terakhir',
            '90'    => '90 hari terakhir',
            'semua' => 'Seluruh periode',
        ];
    }

    /**
     * Peringkat barang dalam satu kueri agregat.
     *
     * Digabungkan lewat join dan dikelompokkan di basis data, bukan dimuat
     * sebagai relasi lalu dihitung di PHP, supaya panel ini tetap satu kueri
     * berapa pun banyaknya permintaan yang sudah tercatat.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getBarangProperty(): Collection
    {
        $daftar = DetailPermintaanBarang::query()
            ->join('permintaan_barang', 'permintaan_barang.id', '=', 'detail_permintaan_barang.permintaan_id')
            ->join('barang_persediaan', 'barang_persediaan.id', '=', 'detail_permintaan_barang.barang_id')
            ->when(
                $this->batasWaktu(),
                fn ($q, $batas) => $q->where('permintaan_barang.created_at', '>=', $batas)
            )
            ->selectRaw(
                'barang_persediaan.id as barang_id,'
                . ' barang_persediaan.nama_barang as nama,'
                . ' barang_persediaan.satuan as satuan,'
                . ' COUNT(*) as frekuensi,'
                . ' SUM(detail_permintaan_barang.jumlah_diminta) as total_diminta'
            )
            ->groupBy('barang_persediaan.id', 'barang_persediaan.nama_barang', 'barang_persediaan.satuan')
            ->orderByDesc('frekuensi')
            ->orderByDesc('total_diminta')
            ->orderBy('barang_persediaan.nama_barang')
            ->limit(self::BATAS_TAMPIL)
            ->get();

        // Pembanding adalah barang dengan frekuensi tertinggi pada periode ini,
        // sama dengan cara panel Permintaan per Tim Kerja mengukur batangnya.
        $tertinggi = max(1, (int) $daftar->max('frekuensi'));

        return $daftar->map(fn ($b) => [
            'nama'         => $b->nama,
            'frekuensi'    => (int) $b->frekuensi,
            'totalDiminta' => (int) $b->total_diminta . ' ' . $b->satuan,
            'lebar'        => round((int) $b->frekuensi / $tertinggi * 100, 1),
            'tautan'       => $this->tautanBarang((int) $b->barang_id),
        ]);
    }

    /**
     * Berapa kali kesepuluh barang teratas itu diminta, dijumlahkan.
     *
     * Bukan banyaknya dokumen permintaan, dan sengaja tidak dinamai begitu:
     * satu permintaan yang memuat tiga barang menyumbang tiga pada angka ini.
     * Angkanya juga hanya menjumlahkan barang yang tampil pada peringkat,
     * bukan seluruh katalog — keduanya membuat "jumlah permintaan pada
     * periode ini" menjadi pembacaan yang keliru.
     */
    public function getTotalFrekuensiProperty(): int
    {
        return (int) $this->barang->sum('frekuensi');
    }

    /**
     * Batas awal periode, atau null apabila seluruh periode dipilih.
     *
     * Dipatok pada awal hari, sama dengan panel Permintaan per Tim Kerja,
     * supaya kedua panel analisis yang menawarkan pilihan periode yang sama
     * benar-benar mengamati rentang yang sama pula.
     */
    protected function batasWaktu(): ?Carbon
    {
        return match ($this->periode) {
            '30'    => now()->subDays(30)->startOfDay(),
            '90'    => now()->subDays(90)->startOfDay(),
            default => null,
        };
    }

    /**
     * Tautan menuju riwayat pergerakan barang yang bersangkutan.
     *
     * Halaman Riwayat mengikat penyaringnya ke alamat halaman, sehingga barang
     * yang diklik langsung terpilih di sana. Tujuannya buku besar mutasi, sebab
     * di situlah terlihat apa yang benar-benar terjadi pada barang itu —
     * pemasukan, pengeluaran, dan sisanya — sesudah pembacanya melihat bahwa
     * barang tersebut sering diminta.
     */
    protected function tautanBarang(int $barangId): string
    {
        return Riwayat::getUrl([
            'jenis'   => 'mutasi_stok',
            'filters' => ['barang_id' => ['value' => $barangId]],
        ]);
    }

    /**
     * Tujuan tautan judul panel.
     *
     * Kartu Kendali, bukan daftar Barang Persediaan: halaman itulah rekap
     * seluruh barang beserta pemasukan, pengeluaran, dan sisanya, yang menjadi
     * kelanjutan wajar dari peringkat ini.
     */
    public function getTautanSemuaProperty(): string
    {
        return KartuKendali::getUrl();
    }
}
