<?php

namespace Tests\Feature\Concerns;

use App\Models\BarangPersediaan;
use App\Models\DetailPermintaanBarang;
use App\Models\Kategori;
use App\Models\PermintaanBarang;
use App\Models\Tim;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Penyiapan data secukupnya untuk uji alur inti.
 *
 * Data dibuat langsung lewat model, bukan lewat seeder, supaya setiap uji
 * hanya memuat baris yang benar-benar dibutuhkannya dan sebabnya terbaca dari
 * berkas ujinya sendiri. Seeder tetap dipakai untuk data sungguhan, bukan untuk
 * pengujian.
 */
trait MenyiapkanDataUji
{
    protected function buatTim(string $nama = 'Statistik Sosial'): Tim
    {
        return Tim::create(['nama_tim' => $nama, 'status_aktif' => true]);
    }

    protected function buatPengguna(string $peran, ?Tim $tim = null, array $tambahan = []): User
    {
        static $urutan = 0;
        $urutan++;

        return User::create([
            'username'     => $peran . $urutan,
            'password'     => Hash::make('password'),
            'name'         => ucfirst(str_replace('_', ' ', $peran)) . ' ' . $urutan,
            'role'         => $peran,
            'tim_id'       => $tim?->id,
            'status_aktif' => true,
            ...$tambahan,
        ]);
    }

    protected function buatBarang(int $stokFisik = 100, int $stokHold = 0, array $tambahan = []): BarangPersediaan
    {
        static $urutan = 0;
        $urutan++;

        $kategori = Kategori::firstOrCreate(
            ['kode_kategori' => '1010301001'],
            ['kode_akun' => '117111', 'nama_kategori' => 'Alat Tulis', 'tipe' => 'persediaan'],
        );

        return BarangPersediaan::create([
            'kategori_id'  => $kategori->id,
            'kode_barang'  => str_pad((string) $urutan, 6, '0', STR_PAD_LEFT),
            'nama_barang'  => 'Barang Uji ' . $urutan,
            'satuan'       => 'Buah',
            'stok_fisik'   => $stokFisik,
            'stok_hold'    => $stokHold,
            'stok_minimum' => 0,
            'status_aktif' => true,
            ...$tambahan,
        ]);
    }

    /**
     * Permintaan beserta rinciannya.
     *
     * @param  array<int, array{barang: BarangPersediaan, diminta: int, final?: int}>  $rincian
     */
    protected function buatPermintaan(
        Tim $tim,
        User $pengaju,
        array $rincian,
        string $status = 'menunggu_ketua',
        array $tambahan = [],
    ): PermintaanBarang {
        static $urutan = 0;
        $urutan++;

        $permintaan = PermintaanBarang::create([
            'kode_permintaan' => 'PB-UJI-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT),
            'tim_pemohon_id'  => $tim->id,
            'pengaju_id'      => $pengaju->id,
            'nama_pemohon'    => $pengaju->name,
            'status'          => $status,
            ...$tambahan,
        ]);

        foreach ($rincian as $baris) {
            DetailPermintaanBarang::create([
                'permintaan_id'  => $permintaan->id,
                'barang_id'      => $baris['barang']->id,
                'jumlah_diminta' => $baris['diminta'],
                'jumlah_final'   => $baris['final'] ?? null,
            ]);
        }

        return $permintaan->load('detail');
    }
}
