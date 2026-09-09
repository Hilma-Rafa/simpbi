<?php

namespace Tests\Feature;

use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MenyiapkanDataUji;
use Tests\TestCase;

/**
 * Pengujian pelepasan kunci stok pada permintaan yang melewati batas waktu.
 *
 * Perintah ini berjalan terjadwal tanpa ditonton siapa pun, sehingga
 * kekeliruannya tidak akan dilaporkan pengguna: stok bisa terkunci selamanya
 * karena satu tahapan tidak ditindaklanjuti, atau sebaliknya permintaan yang
 * masih berjalan ikut dibatalkan. Keduanya diperiksa di sini.
 */
class PelepasanHoldKedaluwarsaTest extends TestCase
{
    use MenyiapkanDataUji;
    use RefreshDatabase;

    public function test_permintaan_yang_lewat_batas_waktu_dilepas_dan_ditandai_kedaluwarsa(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10],
        ], status: 'menunggu_kasubbag', tambahan: [
            'hold_expired_at' => now()->subHour(),
        ]);

        $this->artisan('permintaan:lepas-hold')->assertSuccessful();

        $permintaan->refresh();
        $this->assertSame('kedaluwarsa', $permintaan->status);
        $this->assertNull($permintaan->hold_expired_at);
        $this->assertNotNull($permintaan->hold_released_at);

        $barang->refresh();
        $this->assertSame(0, $barang->stok_hold, 'Kunci harus dilepas agar barang dapat diminta tim lain.');
        $this->assertSame(40, $barang->stok_fisik, 'Kedaluwarsa tidak mengeluarkan barang dari gudang.');
    }

    public function test_riwayat_mencatat_tahap_yang_benar_benar_kedaluwarsa(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10],
        ], status: 'menunggu_kasubbag', tambahan: [
            'hold_expired_at' => now()->subHour(),
        ]);

        $this->artisan('permintaan:lepas-hold')->assertSuccessful();

        $riwayat = RiwayatPersetujuan::where('permintaan_id', $permintaan->id)->sole();
        $this->assertSame('tolak', $riwayat->keputusan);
        $this->assertSame(
            'kasubbag',
            $riwayat->tahap,
            'Tahap yang dicatat harus tahap yang sedang menunggu ketika batas waktu terlampaui, '
            . 'sebab riwayat inilah yang dipakai menelusuri di titik mana permintaan berhenti.'
        );
    }

    public function test_permintaan_yang_belum_lewat_batas_waktu_tidak_disentuh(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 10);

        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10],
        ], status: 'menunggu_kasubbag', tambahan: [
            'hold_expired_at' => now()->addHours(3),
        ]);

        $this->artisan('permintaan:lepas-hold')->assertSuccessful();

        $this->assertSame('menunggu_kasubbag', $permintaan->refresh()->status);
        $this->assertSame(10, $barang->refresh()->stok_hold);
    }

    public function test_permintaan_berstatus_akhir_tidak_ikut_dilepas(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);
        $barang  = $this->buatBarang(stokFisik: 40, stokHold: 0);

        // Sudah selesai, stoknya sudah dikonversi; batas waktu lama tidak relevan
        $permintaan = $this->buatPermintaan($tim, $pengaju, [
            ['barang' => $barang, 'diminta' => 10],
        ], status: 'selesai', tambahan: [
            'hold_expired_at' => now()->subDay(),
        ]);

        $this->artisan('permintaan:lepas-hold')->assertSuccessful();

        $this->assertSame('selesai', $permintaan->refresh()->status);
        $this->assertSame(0, RiwayatPersetujuan::where('permintaan_id', $permintaan->id)->count());
    }

    public function test_seluruh_tahap_berjalan_tercakup(): void
    {
        $tim     = $this->buatTim();
        $pengaju = $this->buatPengguna('tim', $tim);

        $tahap = [
            'menunggu_ketua'      => 'ketua_tim',
            'menunggu_verifikasi' => 'verifikasi',
            'menunggu_kasubbag'   => 'kasubbag',
            'siap_diproses'       => 'penyiapan',
            'siap_diambil'        => 'konfirmasi',
        ];

        $permintaan = [];
        foreach ($tahap as $status => $_) {
            $barang = $this->buatBarang(stokFisik: 20, stokHold: 5);
            $permintaan[$status] = $this->buatPermintaan($tim, $pengaju, [
                ['barang' => $barang, 'diminta' => 5],
            ], status: $status, tambahan: ['hold_expired_at' => now()->subHour()]);
        }

        $this->artisan('permintaan:lepas-hold')->assertSuccessful();

        foreach ($tahap as $status => $tahapDiharapkan) {
            $ini = $permintaan[$status];
            $this->assertSame('kedaluwarsa', $ini->refresh()->status, "Status {$status} seharusnya kedaluwarsa.");
            $this->assertSame(
                $tahapDiharapkan,
                RiwayatPersetujuan::where('permintaan_id', $ini->id)->sole()->tahap,
                "Permintaan berstatus {$status} harus tercatat berhenti pada tahap {$tahapDiharapkan}."
            );
        }

        $this->assertSame(5, PermintaanBarang::where('status', 'kedaluwarsa')->count());
    }
}
