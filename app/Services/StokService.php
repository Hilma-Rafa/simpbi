<?php

namespace App\Services;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use Illuminate\Support\Facades\DB;

/**
 * Layanan pengelolaan stok barang persediaan.
 *
 * Mekanisme pengendalian stok mengikuti Proses Bisnis Usulan Permintaan
 * Barang, yang terdiri atas tiga tindakan:
 *
 *   HOLD     : mengunci stok ketika permintaan diajukan (P.2.S3)
 *   RELEASE  : melepaskan kunci ketika permintaan ditolak, bermasalah,
 *              atau melewati batas waktu (P.2.S7a, P.2.S9a)
 *   KONVERSI : mengurangi stok fisik ketika pemohon mengonfirmasi
 *              penerimaan barang (P.2.S9)
 *
 * Seluruh tindakan dibungkus dalam transaksi basis data dan menggunakan
 * penguncian baris, agar dua permintaan atas barang yang sama pada waktu
 * bersamaan tidak menghasilkan stok yang tidak konsisten.
 */
class StokService
{
    /**
     * Mengunci stok sejumlah yang diminta.
     *
     * @param  array<int,int>  $items  [barang_id => jumlah]
     * @throws \RuntimeException bila stok tersedia tidak mencukupi
     */
    public function hold(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $barangId => $jumlah) {
                $barang = BarangPersediaan::lockForUpdate()->findOrFail($barangId);

                $tersedia = $barang->stok_fisik - $barang->stok_hold;

                if ($jumlah > $tersedia) {
                    throw new \RuntimeException(
                        "Stok {$barang->nama_barang} tidak mencukupi. Tersedia {$tersedia} {$barang->satuan}."
                    );
                }

                $barang->increment('stok_hold', $jumlah);
            }
        });
    }

    /**
     * Melepaskan kunci stok tanpa mengurangi stok fisik.
     */
    public function release(PermintaanBarang $permintaan): void
    {
        DB::transaction(function () use ($permintaan) {
            foreach ($permintaan->detail as $detail) {
                $jumlah = $detail->jumlah_final ?? $detail->jumlah_diminta;

                $barang = BarangPersediaan::lockForUpdate()->find($detail->barang_id);
                if (! $barang) {
                    continue;
                }

                // Tidak boleh kurang dari nol apabila terjadi pelepasan ganda
                $barang->update([
                    'stok_hold' => max(0, $barang->stok_hold - $jumlah),
                ]);
            }

            $permintaan->update(['hold_released_at' => now()]);
        });
    }

    /**
     * Menyesuaikan jumlah kunci ketika permintaan disetujui sebagian.
     */
    public function sesuaikanHold(PermintaanBarang $permintaan): void
    {
        DB::transaction(function () use ($permintaan) {
            foreach ($permintaan->detail as $detail) {
                if ($detail->jumlah_final === null) {
                    continue;
                }

                $selisih = $detail->jumlah_diminta - $detail->jumlah_final;
                if ($selisih <= 0) {
                    continue;
                }

                $barang = BarangPersediaan::lockForUpdate()->find($detail->barang_id);
                if ($barang) {
                    $barang->update([
                        'stok_hold' => max(0, $barang->stok_hold - $selisih),
                    ]);
                }
            }
        });
    }

    /**
     * Mengubah kunci menjadi pengeluaran: stok fisik dan stok terkunci
     * berkurang, lalu dicatat pada buku besar mutasi stok.
     */
    public function konversi(PermintaanBarang $permintaan, int $petugasId): void
    {
        DB::transaction(function () use ($permintaan, $petugasId) {
            foreach ($permintaan->detail as $detail) {
                $jumlah = $detail->jumlah_final ?? $detail->jumlah_diminta;
                if ($jumlah <= 0) {
                    continue;
                }

                $barang = BarangPersediaan::lockForUpdate()->find($detail->barang_id);
                if (! $barang) {
                    continue;
                }

                $saldoSesudah = $barang->stok_fisik - $jumlah;

                $barang->update([
                    'stok_fisik' => max(0, $saldoSesudah),
                    'stok_hold'  => max(0, $barang->stok_hold - $jumlah),
                ]);

                MutasiStok::create([
                    'barang_id'       => $barang->id,
                    'tanggal'         => now()->toDateString(),
                    'jenis'           => 'keluar',
                    'jumlah'          => -$jumlah,
                    'saldo_sesudah'   => max(0, $saldoSesudah),
                    'sumber'          => 'pemakaian',
                    'nomor_dasar'     => $permintaan->kode_permintaan,
                    'referensi_tabel' => 'permintaan_barang',
                    'referensi_id'    => $permintaan->id,
                    'keterangan'      => 'Pengeluaran atas permintaan ' . $permintaan->kode_permintaan,
                    'petugas_id'      => $petugasId,
                ]);
            }
        });
    }

    /**
     * Mencatat penambahan stok dari pengadaan atau pengembalian.
     */
    public function tambah(int $barangId, int $jumlah, string $sumber, ?string $nomorDasar, ?string $keterangan, int $petugasId): void
    {
        DB::transaction(function () use ($barangId, $jumlah, $sumber, $nomorDasar, $keterangan, $petugasId) {
            $barang = BarangPersediaan::lockForUpdate()->findOrFail($barangId);

            $saldoSesudah = $barang->stok_fisik + $jumlah;
            $barang->update(['stok_fisik' => $saldoSesudah]);

            MutasiStok::create([
                'barang_id'     => $barang->id,
                'tanggal'       => now()->toDateString(),
                'jenis'         => 'masuk',
                'jumlah'        => $jumlah,
                'saldo_sesudah' => $saldoSesudah,
                'sumber'        => $sumber,
                'nomor_dasar'   => $nomorDasar,
                'keterangan'    => $keterangan,
                'petugas_id'    => $petugasId,
            ]);
        });
    }
}