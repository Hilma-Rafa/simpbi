<?php

namespace Database\Seeders;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Models\User;
use App\Services\StokService;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Saldo awal barang persediaan, diambil dari kartu kendali tahun sebelumnya.
 *
 * SIMPBI memulai hidupnya dengan katalog barang yang sudah punya stok, tetapi
 * tanpa satu pun baris pada buku besar stok yang menerangkan dari mana stok itu
 * berasal. Akibatnya kartu kendali yang diterbitkan sistem dimulai dari Sisa
 * nol, padahal stok sebenarnya ratusan — kartunya benar secara internal tetapi
 * tidak menggambarkan keadaan yang sesungguhnya.
 *
 * Seeder ini menutup jurang itu: saldo akhir kartu kendali 2025 milik
 * Sub-Bagian Umum dicatat sebagai transaksi "Stok Awal" bertanggal 1 Januari,
 * sehingga setiap angka stok di sistem punya asal usul yang dapat ditelusuri.
 *
 * Kolom "Sisa" pada berkas sumber berisi rumus lembar sebar, bukan angka, dan
 * karena itu tidak dapat dibaca langsung. Saldonya dihitung ulang dari tiga
 * kolom yang memang berisi angka: stok awal, jumlah Masuk, dan jumlah Keluar.
 *
 * Barang yang saldo akhirnya nol tetap dibiarkan ada dengan stok nol, bukan
 * dihapus — barangnya masih berlaku di katalog, hanya sedang kosong, dan
 * pembeliannya akan dicatat menyusul lewat halaman Stok Masuk.
 */
class SaldoAwalKartuKendaliSeeder extends Seeder
{
    /** Berkas kartu kendali tahun sebelumnya. */
    public const BERKAS = 'docs/Kartu-Kendali-Persediaan-2025.xlsx';

    /** Tanggal pencatatan saldo awal. */
    public const TANGGAL = '2026-01-01';

    public function run(): void
    {
        $berkas = base_path(static::BERKAS);

        if (! is_file($berkas)) {
            $this->command?->warn(
                'SaldoAwalKartuKendaliSeeder dilewati: berkas ' . static::BERKAS . ' tidak ditemukan.'
            );

            return;
        }

        $petugas = User::where('role', 'admin')->value('id')
            ?? User::where('role', 'kasubbag')->value('id');

        if (! $petugas) {
            $this->command?->warn(
                'SaldoAwalKartuKendaliSeeder dilewati: belum ada akun Admin maupun Kasubbag '
                . 'yang dapat dicatat sebagai petugas. Jalankan PenggunaSeeder lebih dulu.'
            );

            return;
        }

        $dicatat = 0;
        $nol = 0;
        $takKetemu = [];

        foreach ($this->bacaBerkas($berkas) as $baris) {
            $barang = $this->cariBarang($baris['kodeKategori'], $baris['kodeBarang']);

            if (! $barang) {
                $takKetemu[] = $baris['nama'] . ' (' . $baris['kode'] . ')';

                continue;
            }

            DB::transaction(function () use ($barang, $baris, $petugas, &$dicatat, &$nol) {
                // Baris saldo awal dicari lebih dulu, bukan langsung dibuat,
                // supaya seeder ini aman dijalankan berulang kali: menjalankan
                // dua kali tidak boleh melipatgandakan stok.
                $lama = MutasiStok::where('barang_id', $barang->id)
                    ->where('sumber', 'stok_awal')
                    ->whereDate('tanggal', static::TANGGAL)
                    ->first();

                if ($baris['saldo'] > 0) {
                    $isi = [
                        'barang_id'     => $barang->id,
                        'tanggal'       => static::TANGGAL,
                        'jenis'         => 'masuk',
                        'jumlah'        => $baris['saldo'],
                        'saldo_sesudah' => 0,   // ditetapkan hitungUlangSaldo()
                        'sumber'        => 'stok_awal',
                        'keterangan'    => 'Saldo akhir kartu kendali 2025 Sub-Bagian Umum.',
                        'petugas_id'    => $petugas,
                    ];

                    $lama ? $lama->update($isi) : MutasiStok::create($isi);
                    $dicatat++;
                } else {
                    // Saldo nol tidak perlu barisnya sendiri: kartu kendali
                    // menurunkan Stok Awal dari saldo sebelum periode, dan
                    // baris bernilai nol hanya menambah baris kosong di sana.
                    $lama?->delete();
                    $nol++;
                }

                /*
                 * Dihitung dari nol, bukan dari saldo tersirat, karena setelah
                 * langkah ini buku besar memang memuat seluruh riwayat barang:
                 * saldo awalnya sudah menjadi salah satu barisnya.
                 */
                StokService::hitungUlangSaldo($barang->id, 0);
            });
        }

        $this->command?->info(sprintf(
            'Saldo awal: %d barang dicatat, %d barang bersaldo nol, %d tidak ditemukan.',
            $dicatat,
            $nol,
            count($takKetemu),
        ));

        foreach (array_slice($takKetemu, 0, 10) as $t) {
            $this->command?->warn('  tidak ditemukan di katalog: ' . $t);
        }
    }

    /**
     * Membaca setiap lembar kartu kendali menjadi satu baris ringkasan.
     *
     * @return list<array{kode:string,kodeKategori:string,kodeBarang:string,nama:string,saldo:int}>
     */
    protected function bacaBerkas(string $berkas): array
    {
        $pembaca = new Reader();
        $pembaca->open($berkas);

        $hasil = [];

        foreach ($pembaca->getSheetIterator() as $lembar) {
            $nomorBaris = 0;
            $kode = $nama = '';
            $awal = $masuk = $keluar = 0;

            foreach ($lembar->getRowIterator() as $baris) {
                $nomorBaris++;
                $sel = $baris->getCells();
                $nilai = fn (int $k) => isset($sel[$k]) ? $sel[$k]->getValue() : null;

                // Baris 3 sampai 5 memuat identitas barang, baris 9 stok awal,
                // dan baris 10 ke bawah transaksinya.
                match (true) {
                    $nomorBaris === 3 => $kode = $this->teks($nilai(2)),
                    $nomorBaris === 4 => $nama = $this->teks($nilai(2)),
                    $nomorBaris === 9 => $awal = $this->angka($nilai(7)) ?? 0,
                    $nomorBaris >= 10 => [
                        $masuk  += $this->angka($nilai(5)) ?? 0,
                        $keluar += $this->angka($nilai(6)) ?? 0,
                    ],
                    default => null,
                };
            }

            if ($kode === '') {
                continue;
            }

            // Kode pada berkas menggabungkan kode kategori dan kode barang,
            // dipisah titik: 1.01.03.01.001.000122 = kategori 1010301001,
            // barang 000122.
            $polos = str_replace('.', '', $kode);

            $hasil[] = [
                'kode'         => $kode,
                'kodeKategori' => substr($polos, 0, 10),
                'kodeBarang'   => substr($polos, 10),
                'nama'         => $nama,
                'saldo'        => max(0, $awal + $masuk - $keluar),
            ];
        }

        $pembaca->close();

        return $hasil;
    }

    protected function cariBarang(string $kodeKategori, string $kodeBarang): ?BarangPersediaan
    {
        return BarangPersediaan::query()
            ->whereHas('kategori', fn ($q) => $q->where('kode_kategori', $kodeKategori))
            ->where('kode_barang', $kodeBarang)
            ->first();
    }

    protected function teks(mixed $nilai): string
    {
        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        return trim((string) $nilai);
    }

    /** Sel yang berisi rumus atau teks dianggap tidak bernilai. */
    protected function angka(mixed $nilai): ?int
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (int) $nilai;
        }

        $teks = trim((string) $nilai);

        return ($teks === '' || ! is_numeric($teks)) ? null : (int) $teks;
    }
}
