<?php

namespace App\Services;

use App\Models\BarangPersediaan;
use App\Models\MutasiStok;
use App\Models\PermintaanBarang;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
            $nomorBon = static::terbitkanNomorBon($permintaan);

            foreach ($permintaan->detail as $detail) {
                $jumlah = $detail->jumlah_final ?? $detail->jumlah_diminta;
                if ($jumlah <= 0) {
                    continue;
                }

                $barang = BarangPersediaan::lockForUpdate()->find($detail->barang_id);
                if (! $barang) {
                    continue;
                }

                $saldoAwal = static::saldoAwalTersirat($barang);

                // Kunci stok dilepas di sini; stok fisiknya sendiri ditetapkan
                // oleh hitungUlangSaldo() bersama seluruh kolom Sisa barang ini.
                $barang->update([
                    'stok_hold' => max(0, $barang->stok_hold - $jumlah),
                ]);

                MutasiStok::create([
                    'barang_id'       => $barang->id,
                    'tanggal'         => now()->toDateString(),
                    'jenis'           => 'keluar',
                    'jumlah'          => -$jumlah,
                    'saldo_sesudah'   => 0,
                    'sumber'          => 'pemakaian',
                    // Kolom "Nomor Dasar M/K" pada kartu kendali memakai nomor
                    // bon, mengikuti penomoran Sub-Bagian Umum. Kode
                    // permintaannya tetap tercatat pada keterangan sehingga
                    // penelusuran balik ke sistem tidak hilang.
                    'nomor_dasar'     => $nomorBon,
                    'referensi_tabel' => 'permintaan_barang',
                    'referensi_id'    => $permintaan->id,
                    'keterangan'      => 'Pengeluaran atas permintaan ' . $permintaan->kode_permintaan,
                    'petugas_id'      => $petugasId,
                ]);

                static::hitungUlangSaldo($barang->id, $saldoAwal);
            }
        });
    }

    /**
     * Mencatat penambahan stok dari pengadaan atau pengembalian.
     *
     * $tanggal adalah tanggal dokumennya, bukan tanggal pencatatan: kolom
     * "Tanggal M/K" pada kartu kendali menunjuk tanggal faktur atau berita
     * acara, dan faktur kerap baru sampai ke gudang beberapa hari kemudian.
     * Bila tidak diisi, tanggal hari ini yang dipakai.
     *
     * Tanggalnya boleh mundur. Nota pembelian kerap baru sampai ke gudang
     * berhari-hari setelah barangnya diterima, dan riwayat tahun berjalan
     * kadang baru dicatatkan susulan — memaksa urutan pemasukan mengikuti
     * urutan tanggal akan membuat keduanya mustahil dicatat pada tanggal yang
     * sebenarnya.
     *
     * Konsekuensinya kolom "Sisa" tidak lagi dapat dipercaya apa adanya dari
     * saldo pada saat transaksi disimpan, sebab penyisipan di tengah menggeser
     * seluruh saldo sesudahnya. Karena itu saldo barang itu dihitung ulang
     * dari awal setiap kali buku besarnya berubah.
     */
    public function tambah(int $barangId, int $jumlah, string $sumber, ?string $nomorDasar, ?string $keterangan, int $petugasId, ?string $tanggal = null): void
    {
        $tanggal = $tanggal ?: now()->toDateString();

        DB::transaction(function () use ($barangId, $jumlah, $sumber, $nomorDasar, $keterangan, $petugasId, $tanggal) {
            $barang = BarangPersediaan::lockForUpdate()->findOrFail($barangId);

            $saldoAwal = static::saldoAwalTersirat($barang);

            MutasiStok::create([
                'barang_id'     => $barang->id,
                'tanggal'       => $tanggal,
                'jenis'         => 'masuk',
                'jumlah'        => $jumlah,
                // Diisi sementara; nilai sebenarnya ditetapkan oleh
                // hitungUlangSaldo() setelah barisnya duduk pada urutannya.
                'saldo_sesudah' => 0,
                'sumber'        => $sumber,
                'nomor_dasar'   => $nomorDasar,
                'keterangan'    => $keterangan,
                'petugas_id'    => $petugasId,
            ]);

            static::hitungUlangSaldo($barang->id, $saldoAwal);
        });
    }

    /**
     * Memberi nomor bon kepada sebuah permintaan, bila belum punya.
     *
     * Penomoran dimulai ulang setiap tahun dan mengikuti tahun saat barang
     * keluar, bukan tahun permintaan diajukan — permintaan akhir Desember yang
     * baru diambil pada Januari masuk ke kartu kendali tahun berikutnya, dan
     * nomornya harus sejalan dengan kartu tempat ia tercatat.
     *
     * Nomor terakhir dicari dengan mengurutkan sebagai angka, bukan sebagai
     * teks. Selama nomornya tiga digit keduanya kebetulan sama hasilnya, tetapi
     * begitu melewati 999 urutan teks akan menempatkan "99" di atas "1000" dan
     * penomoran berikutnya mengulang nomor yang sudah terpakai.
     *
     * Dipanggil di dalam transaksi konversi, sehingga permintaan yang berakhir
     * bermasalah tidak pernah menghabiskan satu nomor pun.
     */
    protected static function terbitkanNomorBon(PermintaanBarang $permintaan): string
    {
        if (filled($permintaan->nomor_bon)) {
            return $permintaan->nomor_bon;
        }

        $tahun = (int) now()->year;

        $terakhir = (int) PermintaanBarang::query()
            ->where('tahun_bon', $tahun)
            ->orderByRaw('CAST(nomor_bon AS INTEGER) DESC')
            ->value('nomor_bon');

        $nomor = str_pad((string) ($terakhir + 1), 3, '0', STR_PAD_LEFT);

        $permintaan->forceFill([
            'nomor_bon' => $nomor,
            'tahun_bon' => $tahun,
        ])->save();

        return $nomor;
    }

    /**
     * Saldo yang sudah ada pada sebuah barang sebelum baris pertama buku
     * besarnya.
     *
     * Buku besar mutasi tidak selalu memuat seluruh riwayat barang. Katalog
     * awal dimasukkan dengan stok fisik apa adanya, tanpa baris "stok awal"
     * yang mendampinginya, sehingga menghitung saldo mulai dari nol akan
     * menghapus stok yang sebenarnya ada. Selisih antara stok fisik sekarang
     * dan jumlah seluruh mutasi itulah saldo yang tersirat, dan angka itu yang
     * dipakai sebagai titik mulai perhitungan ulang.
     *
     * Dibaca sebelum buku besarnya diubah, sebab sesudahnya selisih itu sudah
     * bergeser oleh baris yang baru disisipkan.
     */
    protected static function saldoAwalTersirat(BarangPersediaan $barang): int
    {
        return $barang->stok_fisik - (int) MutasiStok::where('barang_id', $barang->id)->sum('jumlah');
    }

    /**
     * Menghitung ulang kolom saldo_sesudah seluruh mutasi sebuah barang, lalu
     * menyelaraskan stok fisiknya dengan saldo terakhir.
     *
     * Urutannya menurut tanggal, lalu menurut id bagi transaksi yang jatuh
     * pada tanggal yang sama — id mewakili urutan pencatatan, yang merupakan
     * satu-satunya keterangan urutan yang tersedia ketika tanggalnya kembar.
     * Urutan ini harus sama persis dengan urutan yang dipakai kartu kendali
     * saat dicetak, sebab kolom Sisa dibaca berpasangan dengan barisnya.
     *
     * Saldo yang jatuh di bawah nol ditolak: kartu kendali dengan sisa negatif
     * menggambarkan keadaan yang tidak mungkin, dan lebih baik penyisipannya
     * gagal terang-terangan daripada menerbitkan kartu yang mustahil.
     */
    public static function hitungUlangSaldo(int $barangId, int $saldoAwal = 0): void
    {
        $saldo = $saldoAwal;

        $mutasi = MutasiStok::query()
            ->where('barang_id', $barangId)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get(['id', 'tanggal', 'jumlah', 'saldo_sesudah']);

        foreach ($mutasi as $baris) {
            $saldo += $baris->jumlah;

            if ($saldo < 0) {
                throw new InvalidArgumentException(
                    'Transaksi ini membuat sisa stok menjadi negatif pada '
                    . $baris->tanggal->format('d-m-Y')
                    . '. Periksa kembali tanggal atau jumlahnya.'
                );
            }

            // Hanya baris yang saldonya benar-benar berubah yang ditulis ulang,
            // supaya penyisipan di ujung tidak menyentuh ratusan baris lama.
            if ($baris->saldo_sesudah !== $saldo) {
                MutasiStok::whereKey($baris->id)->update(['saldo_sesudah' => $saldo]);
            }
        }

        BarangPersediaan::whereKey($barangId)->update(['stok_fisik' => $saldo]);
    }

    /**
     * Tanggal transaksi terakhir suatu barang pada buku besar mutasi.
     *
     * Dipakai bersama oleh layanan ini dan formulir Stok Masuk, supaya batas
     * tanggal yang ditawarkan kepada pengguna sama persis dengan batas yang
     * ditegakkan ketika transaksinya disimpan.
     *
     * @return string|null Tanggal Y-m-d, atau null bila barang belum pernah bermutasi.
     */
    public static function tanggalMutasiTerakhir(int $barangId): ?string
    {
        $tanggal = MutasiStok::query()
            ->where('barang_id', $barangId)
            ->max('tanggal');

        return $tanggal ? Carbon::parse($tanggal)->toDateString() : null;
    }
}
