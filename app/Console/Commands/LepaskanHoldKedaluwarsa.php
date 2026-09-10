<?php

namespace App\Console\Commands;

use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use App\Services\StokService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Melepaskan kunci stok pada permintaan yang melewati batas waktu tahapan.
 *
 * Batas waktu setiap tahapan disimpan pada tabel pengaturan dan dicatat
 * pada atribut hold_expired_at. Apabila suatu tahapan tidak ditindaklanjuti
 * sampai batas waktu tersebut, kunci stok dilepaskan agar barang dapat
 * kembali diminta oleh tim kerja lain, dan permintaan ditandai kedaluwarsa.
 */
class LepaskanHoldKedaluwarsa extends Command
{
    protected $signature = 'permintaan:lepas-hold';

    protected $description = 'Melepaskan kunci stok pada permintaan yang melewati batas waktu tahapan';

    /** Status yang tahapannya masih berjalan dan stoknya masih terkunci. */
    protected const STATUS_BERJALAN = [
        'menunggu_ketua',
        'menunggu_verifikasi',
        'menunggu_kasubbag',
        'siap_diproses',
        'siap_diambil',
    ];

    public function handle(StokService $stok): int
    {
        $kedaluwarsa = PermintaanBarang::query()
            ->whereIn('status', self::STATUS_BERJALAN)
            ->whereNotNull('hold_expired_at')
            ->where('hold_expired_at', '<', now())
            ->with('detail')
            ->get();

        if ($kedaluwarsa->isEmpty()) {
            $this->info('Tidak ada permintaan yang melewati batas waktu.');

            return self::SUCCESS;
        }

        $berhasil = 0;

        foreach ($kedaluwarsa as $permintaan) {
            try {
                DB::transaction(function () use ($permintaan, $stok) {
                    // Tahap dibaca SEBELUM status diperbarui. Bila dibaca
                    // sesudahnya, statusnya sudah menjadi "kedaluwarsa"
                    // sehingga seluruh permintaan tercatat berhenti di tahap
                    // Ketua Tim — nilai bawaan pemetaan — dan riwayatnya tidak
                    // lagi dapat dipakai menelusuri di titik mana permintaan
                    // sebenarnya terhenti.
                    $tahap = static::tahapTerakhir($permintaan->status);

                    $stok->release($permintaan);

                    $permintaan->update([
                        'status'          => 'kedaluwarsa',
                        'hold_expired_at' => null,
                    ]);

                    RiwayatPersetujuan::create([
                        'permintaan_id' => $permintaan->id,
                        'tahap'         => $tahap,
                        'pelaksana_id'  => $permintaan->pengaju_id,
                        'keputusan'     => 'tolak',
                        'catatan'       => 'Tahapan tidak ditindaklanjuti sampai batas waktu. '
                            . 'Kunci stok dilepaskan secara otomatis oleh sistem.',
                        'waktu'         => now(),
                    ]);
                });

                $berhasil++;
                $this->line("  Dilepaskan: {$permintaan->kode_permintaan}");
            } catch (\Throwable $e) {
                $this->error("  Gagal: {$permintaan->kode_permintaan} — {$e->getMessage()}");
            }
        }

        $this->info("Selesai. {$berhasil} permintaan ditandai kedaluwarsa.");

        return self::SUCCESS;
    }

    /** Menentukan tahap yang dicatat pada riwayat berdasarkan status terakhir. */
    protected static function tahapTerakhir(string $status): string
    {
        return match ($status) {
            'menunggu_ketua'      => 'ketua_tim',
            'menunggu_verifikasi' => 'verifikasi',
            'menunggu_kasubbag'   => 'kasubbag',
            'siap_diproses'       => 'penyiapan',
            'siap_diambil'        => 'konfirmasi',
            default               => 'ketua_tim',
        };
    }
}