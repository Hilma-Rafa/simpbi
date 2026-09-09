<?php

namespace App\Services;

use App\Models\BastMutasiAset;
use App\Models\RiwayatPenempatanAset;
use Illuminate\Support\Facades\DB;

/**
 * Transisi status BAST mutasi aset (Instruksi §18). Tidak ada jalur approval
 * atau penolakan; hanya pengesahan lalu konfirmasi penerimaan.
 */
class MutasiAsetService
{
    public function __construct(protected DokumenBastService $dokumen)
    {
    }

    /** Nomor BAST otomatis, urut per tahun berjalan. */
    public function nomorBaru(): string
    {
        $tahun = now()->year;
        $urut = BastMutasiAset::query()
            ->whereYear('created_at', $tahun)
            ->count() + 1;

        return sprintf('BAST-%d-%04d', $tahun, $urut);
    }

    /**
     * Pengesahan BAST oleh Kasubbag (UC-17). Membubuhkan e-TTD (nama + QR +
     * waktu), memindahkan penempatan aset ke tim tujuan, dan mencatat riwayat.
     */
    public function sahkan(BastMutasiAset $bast, int $kasubbagId): void
    {
        DB::transaction(function () use ($bast, $kasubbagId) {
            $bast->update([
                'disahkan_oleh_id' => $kasubbagId,
                'disahkan_at'      => now(),
                'status'           => 'menunggu_konfirmasi',
            ]);

            // Pindahkan penempatan aset ke tim tujuan.
            $bast->aset->update(['tim_penempatan_id' => $bast->tim_tujuan_id]);

            // Tutup baris penempatan yang masih berjalan.
            RiwayatPenempatanAset::query()
                ->where('aset_id', $bast->aset_id)
                ->whereNull('tanggal_selesai')
                ->update(['tanggal_selesai' => now()->toDateString()]);

            // Buka baris penempatan baru pada tim tujuan.
            RiwayatPenempatanAset::create([
                'aset_id'       => $bast->aset_id,
                'tim_id'        => $bast->tim_tujuan_id,
                'tanggal_mulai' => now()->toDateString(),
                'jenis'         => 'mutasi',
                'bast_id'       => $bast->id,
            ]);

            // Bentuk ulang dokumen dengan e-TTD + QR verifikasi.
            $path = $this->dokumen->buat($bast->fresh(['aset', 'timAsal', 'timTujuan', 'disahkanOleh']));
            $bast->update(['file_bast_path' => $path]);
        });
    }

    /**
     * Konfirmasi penerimaan aset oleh Ketua Tim tujuan (UC-18).
     */
    public function konfirmasi(BastMutasiAset $bast, int $ketuaId): void
    {
        $bast->update([
            'dikonfirmasi_oleh_id' => $ketuaId,
            'dikonfirmasi_at'      => now(),
            'status'               => 'selesai_administratif',
        ]);
    }
}
