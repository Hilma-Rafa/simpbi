<?php

namespace App\Console\Commands;

use App\Support\Cadangan;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pencadangan basis data beserta berkas data SIMPBI.
 *
 * Perintah peladen, bukan fitur pengguna: tidak ada tombol maupun alamat yang
 * menjalankannya. Pencadangan adalah pekerjaan pemeliharaan, dan tidak satu pun
 * dari lima peran pada rancangan sistem ini yang tugasnya mengurus basis data.
 *
 * Dijalankan berkala lewat penjadwal sistem operasi, misalnya setiap dini hari:
 *
 *     0 1 * * * cd /path/ke/simpbi && php artisan simpbi:backup >> /dev/null 2>&1
 */
class CadangkanData extends Command
{
    protected $signature = 'simpbi:backup
        {--nama= : Nama berkas arsip, bila tidak ingin memakai cap waktu}';

    protected $description = 'Mencadangkan basis data beserta tanda tangan dan dokumen yang sudah terbit';

    public function handle(): int
    {
        $mulai = microtime(true);

        $this->line('Mencadangkan ' . Cadangan::penggerak() . ' "' . Cadangan::namaBasisData() . '" ...');

        try {
            $hasil = Cadangan::buat($this->option('nama'));
        } catch (Throwable $e) {
            $this->components->error('Pencadangan gagal: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Berkas', $hasil['lintasan']);
        $this->components->twoColumnDetail('Waktu', now()->translatedFormat('d F Y, H:i:s'));
        $this->components->twoColumnDetail('Basis data', $hasil['penggerak'] . ' — ' . $hasil['basis_data'] . ' — <fg=green>berhasil</>');
        $this->components->twoColumnDetail(
            'Berkas data',
            $hasil['berkas_data'] . ' berkas — ' . ($hasil['berkas_data'] > 0
                ? '<fg=green>berhasil</>'
                : '<fg=yellow>tidak ada yang perlu dicadangkan</>'),
        );
        $this->components->twoColumnDetail('Ukuran', $this->ukuran($hasil['bita']));
        $this->components->twoColumnDetail('Lama', number_format(microtime(true) - $mulai, 1) . ' detik');
        $this->newLine();

        $this->components->info('Cadangan selesai. Berkas .env tidak ikut di dalamnya — simpan terpisah.');

        return self::SUCCESS;
    }

    /** Ukuran berkas dalam satuan yang enak dibaca. */
    protected function ukuran(int $bita): string
    {
        foreach (['bita', 'KB', 'MB', 'GB'] as $satuan) {
            if ($bita < 1024 || $satuan === 'GB') {
                return ($satuan === 'bita' ? $bita : number_format($bita, 1)) . ' ' . $satuan;
            }

            $bita /= 1024;
        }

        return (string) $bita;
    }
}
