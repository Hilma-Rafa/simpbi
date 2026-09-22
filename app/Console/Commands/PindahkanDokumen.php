<?php

namespace App\Console\Commands;

use App\Models\BastMutasiAset;
use App\Models\PermintaanBarang;
use App\Services\DokumenPermintaanService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Memindahkan dokumen bukti permintaan dan BAST dari disk public ke disk privat.
 *
 * Dokumen memuat tanda tangan, sehingga tidak boleh berada di storage/app/public
 * (terjangkau /storage bila `storage:link` dijalankan). Kode baru menulis dan
 * membaca disk privat; perintah ini memindahkan dokumen lama, sekali saja, saat
 * pemasangan. Aman dijalankan berulang kali.
 *
 * Bekerja dari catatan di basis data (kolom `file_bukti_path` dan
 * `file_bast_path`), bukan dengan memindai folder. Berkas bukti berfootnote
 * (yang dilayani rute /bukti/{token}) tidak tercatat di kolom mana pun; lintasannya
 * diturunkan dari kode permintaan, sama seperti yang dilakukan kode aplikasi.
 *
 * Tanpa opsi: hanya menampilkan rencana (tidak ada yang diubah).
 *
 *     php artisan simpbi:pindah-dokumen                       # rencana
 *     php artisan simpbi:pindah-dokumen --jalankan            # salin + verifikasi
 *     php artisan simpbi:pindah-dokumen --jalankan --hapus-asli
 */
class PindahkanDokumen extends Command
{
    protected $signature = 'simpbi:pindah-dokumen
        {--jalankan : Salin ke disk privat dan verifikasi (ukuran dan sha256)}
        {--hapus-asli : Bersama --jalankan: hapus berkas di disk public setelah verifikasinya berhasil}';

    protected $description = 'Memindahkan dokumen bukti dan BAST lama dari disk public ke disk privat';

    /** Disk asal dan tujuan. */
    protected const ASAL = 'public';

    protected const TUJUAN = 'local';

    public function handle(): int
    {
        $jalankan = (bool) $this->option('jalankan');
        $hapusAsli = (bool) $this->option('hapus-asli');

        if ($hapusAsli && ! $jalankan) {
            $this->components->error('--hapus-asli hanya dapat dipakai bersama --jalankan.');

            return self::FAILURE;
        }

        $asal = Storage::disk(self::ASAL);
        $tujuan = Storage::disk(self::TUJUAN);

        $this->line($jalankan
            ? 'Memindahkan dokumen dari disk "' . self::ASAL . '" ke "' . self::TUJUAN . '"' . ($hapusAsli ? ' (berkas asli dihapus setelah terverifikasi)' : '') . ' ...'
            : 'Rencana pemindahan (tidak ada yang diubah). Tambahkan --jalankan untuk melaksanakannya.');
        $this->newLine();

        $hitung = ['disalin' => 0, 'rencana' => 0, 'sudah' => 0, 'hilang' => 0, 'berbeda' => 0, 'gagal' => 0, 'dihapus' => 0];

        foreach ($this->daftarLintasan() as $lintasan) {
            $this->proses($lintasan, $asal, $tujuan, $jalankan, $hapusAsli, $hitung);
        }

        $this->newLine();
        $this->components->twoColumnDetail('Akan disalin (rencana)', (string) $hitung['rencana']);
        $this->components->twoColumnDetail('Disalin dan terverifikasi', (string) $hitung['disalin']);
        $this->components->twoColumnDetail('Sudah ada di tujuan (identik)', (string) $hitung['sudah']);
        $this->components->twoColumnDetail('Hilang di sumber dan di tujuan', (string) $hitung['hilang']);
        $this->components->twoColumnDetail('Ada di tujuan tetapi berbeda (tidak ditimpa)', (string) $hitung['berbeda']);
        $this->components->twoColumnDetail('Gagal disalin atau diverifikasi', (string) $hitung['gagal']);
        $this->components->twoColumnDetail('Berkas asli dihapus', (string) $hitung['dihapus']);

        if ($hitung['gagal'] > 0 || $hitung['berbeda'] > 0) {
            $this->components->error('Ada berkas yang perlu diperiksa; lihat baris bertanda di atas. Perintah dapat dijalankan ulang dengan aman.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Lintasan (relatif) seluruh dokumen yang tercatat di basis data.
     *
     * @return \Generator<int,string>
     */
    protected function daftarLintasan(): \Generator
    {
        foreach (PermintaanBarang::query()->whereNotNull('file_bukti_path')->orderBy('id')->cursor() as $permintaan) {
            yield $permintaan->file_bukti_path;

            yield DokumenPermintaanService::lintasanBerfootnote($permintaan);
        }

        foreach (BastMutasiAset::query()->whereNotNull('file_bast_path')->orderBy('id')->cursor() as $bast) {
            yield $bast->file_bast_path;
        }
    }

    /** @param  array<string,int>  $hitung */
    protected function proses(string $lintasan, Filesystem $asal, Filesystem $tujuan, bool $jalankan, bool $hapusAsli, array &$hitung): void
    {
        $adaAsal = $asal->exists($lintasan);
        $adaTujuan = $tujuan->exists($lintasan);

        if (! $adaAsal && ! $adaTujuan) {
            $hitung['hilang']++;
            $this->line("  <fg=yellow>HILANG</>      {$lintasan} (tidak ada di sumber maupun di tujuan)");

            return;
        }

        if (! $adaAsal) {
            $hitung['sudah']++;
            $this->line("  sudah ada    {$lintasan} (hanya di tujuan)");

            return;
        }

        $identik = false;

        if ($adaTujuan) {
            if ($this->sidik($asal, $lintasan) !== $this->sidik($tujuan, $lintasan)) {
                $hitung['berbeda']++;
                $this->line("  <fg=red>BERBEDA</>     {$lintasan} (ada di tujuan dengan isi lain; tidak ditimpa dan asli tidak dihapus)");

                return;
            }

            $identik = true;
            $hitung['sudah']++;
            $this->line("  sudah ada    {$lintasan} (identik di tujuan)");
        } elseif (! $jalankan) {
            $hitung['rencana']++;
            $this->line("  akan disalin {$lintasan}");

            return;
        } else {
            try {
                $tujuan->writeStream($lintasan, $asal->readStream($lintasan));

                if ($this->sidik($asal, $lintasan) !== $this->sidik($tujuan, $lintasan)) {
                    // Salinan yang tidak lolos verifikasi dibuang; sumbernya tidak disentuh.
                    $tujuan->delete($lintasan);

                    throw new \RuntimeException('ukuran atau sha256 tidak cocok');
                }
            } catch (Throwable $e) {
                $hitung['gagal']++;
                $this->line("  <fg=red>GAGAL</>       {$lintasan} ({$e->getMessage()})");

                return;
            }

            $identik = true;
            $hitung['disalin']++;
            $this->line("  <fg=green>disalin</>      {$lintasan} (terverifikasi)");
        }

        if ($identik && $hapusAsli && $jalankan) {
            $asal->delete($lintasan);
            $hitung['dihapus']++;
            $this->line("  dihapus asli {$lintasan}");
        }
    }

    /** Ukuran dan sha256 isi berkas, dibaca sebagai aliran. */
    protected function sidik(Filesystem $cakram, string $lintasan): array
    {
        $aliran = $cakram->readStream($lintasan);
        $konteks = hash_init('sha256');
        $ukuran = 0;

        while (! feof($aliran)) {
            $potongan = fread($aliran, 8192);

            if ($potongan === false) {
                break;
            }

            $ukuran += strlen($potongan);
            hash_update($konteks, $potongan);
        }

        fclose($aliran);

        return [$ukuran, hash_final($konteks)];
    }
}
