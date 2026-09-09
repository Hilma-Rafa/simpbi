<?php

namespace Database\Seeders;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan saldo pembuka barang persediaan ke buku besar mutasi stok.
 *
 * KatalogBarangSeeder mengisi kolom `stok_fisik` secara langsung dari Kartu
 * Kendali Barang Persediaan yang berjalan, sehingga stok tersebut ada pada
 * data induk tetapi tidak memiliki satu pun baris di `mutasi_stok`. Akibatnya
 * kartu kendali yang dicetak sistem berbunyi nol, padahal barangnya nyata ada
 * di gudang. Seeder ini menutup celah itu dengan menerbitkan satu transaksi
 * masuk bersumber `stok_awal` untuk setiap barang tersebut, sehingga buku
 * besar dan data induk menyatakan angka yang sama.
 *
 * Transaksi sengaja TIDAK dibuat melalui StokService::tambah(), sebab metode
 * itu menambahkan jumlahnya ke `stok_fisik` — perilaku yang benar untuk
 * penerimaan barang baru (UC-07), tetapi keliru di sini karena stoknya sudah
 * tercatat pada data induk. Yang dikerjakan seeder ini hanya melengkapi
 * riwayat, bukan menambah barang.
 */
class StokAwalSeeder extends Seeder
{
    public function run(): void
    {
        // Hanya barang yang benar-benar berstok dan belum punya riwayat apa pun.
        // Barang yang sudah memiliki mutasi dilewati, agar seeder ini aman
        // dijalankan berulang dan tidak pernah menggandakan saldo pembuka.
        $barang = BarangPersediaan::query()
            ->where('stok_fisik', '>', 0)
            ->whereDoesntHave('mutasi')
            ->orderBy('nama_barang')
            ->get();

        if ($barang->isEmpty()) {
            $this->command?->info('StokAwalSeeder: tidak ada barang yang perlu dicatat saldo pembukanya.');

            return;
        }

        $petugas = $this->petugas();

        if (! $petugas) {
            $this->command?->warn(
                'StokAwalSeeder dilewati: belum ada akun Petugas Gudang maupun Admin '
                . 'yang dapat dicatat sebagai pelaksana transaksi. Jalankan PenggunaSeeder lebih dulu.'
            );

            return;
        }

        // Saldo pembuka bertanggal 1 Januari tahun berjalan, mengikuti kebiasaan
        // kartu kendali yang dibuka setiap awal tahun anggaran.
        $tanggal = Carbon::create(now()->year, 1, 1)->toDateString();
        $waktu   = now();

        $baris = $barang->map(fn (BarangPersediaan $b) => [
            'barang_id'       => $b->id,
            'tanggal'         => $tanggal,
            'jenis'           => 'masuk',
            'jumlah'          => (int) $b->stok_fisik,
            'saldo_sesudah'   => (int) $b->stok_fisik,
            'sumber'          => 'stok_awal',
            // Saldo pembuka tidak lahir dari dokumen pengadaan, sehingga kolom
            // Nomor Dasar M/K pada kartu kendali memang dibiarkan kosong,
            // sebagaimana kartu manualnya.
            'nomor_dasar'     => null,
            'referensi_tabel' => null,
            'referensi_id'    => null,
            'keterangan'      => 'Saldo pembuka hasil pendataan awal katalog barang persediaan.',
            'petugas_id'      => $petugas->id,
            'created_at'      => $waktu,
            'updated_at'      => $waktu,
        ])->all();

        DB::transaction(fn () => MutasiStok::insert($baris));

        $this->command?->info(sprintf(
            'StokAwalSeeder: %d barang dicatat saldo pembukanya per %s, total %s unit, pelaksana %s.',
            count($baris),
            Carbon::parse($tanggal)->format('d-m-Y'),
            number_format($barang->sum('stok_fisik'), 0, ',', '.'),
            $petugas->name,
        ));
    }

    /**
     * Pelaksana yang dicatat pada transaksi.
     *
     * Petugas Gudang didahulukan karena dialah yang berwenang atas pencatatan
     * stok (Instruksi §4); Admin Sistem hanya menjadi cadangan supaya seeder
     * tetap dapat berjalan pada pemasangan yang belum memiliki akun gudang.
     */
    protected function petugas(): ?User
    {
        return User::query()->where('role', 'petugas_gudang')->first()
            ?? User::query()->where('role', 'admin')->first();
    }
}
