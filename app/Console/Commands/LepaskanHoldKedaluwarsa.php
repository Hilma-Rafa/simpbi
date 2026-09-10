<?php

namespace App\Console\Commands;

use App\Services\KedaluwarsaService;
use Illuminate\Console\Command;

/**
 * Melepaskan kunci stok pada permintaan yang melewati batas waktu tahapan.
 *
 * Batas waktu setiap tahapan disimpan pada tabel pengaturan dan dicatat
 * pada atribut hold_expired_at. Apabila suatu tahapan tidak ditindaklanjuti
 * sampai batas waktu tersebut, kunci stok dilepaskan agar barang dapat
 * kembali diminta oleh tim kerja lain, dan permintaan ditandai kedaluwarsa.
 *
 * Pekerjaannya sendiri dikerjakan oleh KedaluwarsaService, yang juga dipanggil
 * pada setiap kunjungan ke panel. Perintah ini tinggal menjadi jalur bagi
 * penjadwal dan bagi pemeriksaan manual dari konsol.
 */
class LepaskanHoldKedaluwarsa extends Command
{
    protected $signature = 'permintaan:lepas-hold';

    protected $description = 'Melepaskan kunci stok pada permintaan yang melewati batas waktu tahapan';

    public function handle(KedaluwarsaService $kedaluwarsa): int
    {
        $hasil = $kedaluwarsa->sapu();

        if ($hasil->isEmpty()) {
            $this->info('Tidak ada permintaan yang melewati batas waktu.');

            return self::SUCCESS;
        }

        foreach ($hasil as $baris) {
            $baris['berhasil']
                ? $this->line("  Dilepaskan: {$baris['kode']}")
                : $this->error("  Gagal: {$baris['kode']} — {$baris['pesan']}");
        }

        $berhasil = $hasil->where('berhasil', true)->count();

        $this->info("Selesai. {$berhasil} permintaan ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}
