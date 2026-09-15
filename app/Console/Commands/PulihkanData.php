<?php

namespace App\Console\Commands;

use App\Support\Cadangan;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pemulihan SIMPBI dari berkas cadangan.
 *
 * Perintah peladen, dan sengaja tidak pernah dijalankan dari antarmuka. Sebuah
 * aplikasi yang memulihkan basis data yang sedang dipakainya sendiri berarti
 * memotong dahan tempat ia duduk: sesi, antrean, dan berkas dokumen akan
 * mengacu pada data yang sudah tidak ada lagi.
 *
 * Urutannya dijaga supaya tidak ada yang dihapus sebelum penggantinya terbukti
 * sah — arsip diperiksa lebih dulu, keadaan sekarang dicadangkan, baru data
 * ditimpa.
 */
class PulihkanData extends Command
{
    protected $signature = 'simpbi:restore
        {berkas : Lintasan arsip cadangan, atau nama berkas di dalam storage/cadangan}
        {--paksa : Lewati pertanyaan konfirmasi, untuk pemulihan tanpa penjaga}
        {--tanpa-cadangan : Jangan mencadangkan keadaan sekarang lebih dulu}';

    protected $description = 'Memulihkan basis data dan berkas data dari arsip cadangan';

    public function handle(): int
    {
        $berkas = $this->lintasanArsip((string) $this->argument('berkas'));

        // Pemeriksaan didahulukan: arsip yang tidak sah tidak boleh sampai pada
        // tahap mana pun yang menyentuh data.
        try {
            $manifes = Cadangan::periksa($berkas);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());
            $this->line('  Tidak ada data yang diubah.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Arsip', $berkas);
        $this->components->twoColumnDetail('Dibuat pada', (string) ($manifes['dibuat_pada'] ?? '-'));
        $this->components->twoColumnDetail('Basis data', $manifes['penggerak'] . ' — ' . $manifes['basis_data']);
        $this->components->twoColumnDetail('Berkas data', ($manifes['berkas_data'] ?? 0) . ' berkas');
        $this->newLine();

        $this->components->warn(
            'Pemulihan MENIMPA seluruh basis data "' . Cadangan::namaBasisData()
            . '" beserta tanda tangan dan dokumen yang tersimpan sekarang.'
        );

        if (! $this->option('paksa') && ! $this->confirm('Lanjutkan pemulihan?', false)) {
            $this->line('  Dibatalkan. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        // Keadaan sekarang dicadangkan lebih dulu supaya pemulihan yang ternyata
        // salah pilih berkas masih dapat dibatalkan.
        if (! $this->option('tanpa-cadangan')) {
            try {
                $pengaman = Cadangan::buat('sebelum-restore-' . now()->format('Y-m-d-His') . '.zip');
                $this->components->info('Keadaan sekarang dicadangkan ke ' . $pengaman['lintasan']);
            } catch (Throwable $e) {
                $this->components->error('Pencadangan pengaman gagal: ' . $e->getMessage());
                $this->line('  Pemulihan dihentikan. Tidak ada data yang diubah.');
                $this->line('  Jalankan ulang dengan --tanpa-cadangan bila memang disengaja.');

                return self::FAILURE;
            }
        }

        try {
            Cadangan::pulihkan($berkas, $manifes);
        } catch (Throwable $e) {
            $this->components->error('Pemulihan gagal di tengah jalan: ' . $e->getMessage());
            $this->line('  Data mungkin dalam keadaan separuh jadi. Pulihkan dari cadangan pengaman di atas.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Pemulihan selesai.');
        $this->line('  Yang masih perlu dikerjakan sendiri:');
        $this->line('  1. Kosongkan antrean — <fg=cyan>php artisan queue:clear</> — sebab curahan basis data');
        $this->line('     ikut membawa pekerjaan yang belum terkirim, dan tanpa ini pesan WhatsApp lama');
        $this->line('     akan terkirim ulang.');
        $this->line('  2. Segarkan singgahan — <fg=cyan>php artisan config:clear</> lalu nyalakan lagi');
        $this->line('     pekerja antrean dan penjadwal.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Menerima lintasan lengkap maupun sekadar nama berkas di direktori
     * cadangan, sebab itulah bentuk yang paling sering diketik.
     */
    protected function lintasanArsip(string $diberikan): string
    {
        if (is_file($diberikan)) {
            return $diberikan;
        }

        return Cadangan::direktori() . DIRECTORY_SEPARATOR . basename($diberikan);
    }
}
