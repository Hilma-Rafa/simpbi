<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Cadangan;
use Database\Seeders\SaldoAwalKartuKendaliSeeder;
use Illuminate\Console\Command;

/**
 * Pengembalian data demo ke keadaan bersih, untuk pengujian berulang.
 *
 * Diperlukan karena pengujian fungsional dan penilaian usability dijalankan
 * berkali-kali dengan pengguna yang berbeda, dan setiap sesi harus berangkat
 * dari keadaan yang sama. Menyeed ulang saja tidak cukup: seluruh seeder di
 * proyek ini memakai `updateOrInsert` sehingga bersifat idempoten — dijalankan
 * di atas basis data yang sudah terpakai, ia hanya menimpa data induk dan
 * meninggalkan seluruh transaksi.
 *
 * Perintah ini TIDAK akan pernah berjalan pada lingkungan produksi, dan
 * penolakan itu tidak dapat dilewati opsi apa pun.
 */
class ResetDemo extends Command
{
    protected $signature = 'simpbi:reset-demo
        {--paksa : Lewati pertanyaan konfirmasi; tidak melewati penjaga produksi}';

    protected $description = 'Mengembalikan basis data dan berkas demo ke keadaan bersih (khusus pengembangan)';

    public function handle(): int
    {
        /*
         * Penjaga produksi berdiri paling depan, sebelum konfirmasi maupun opsi
         * apa pun dibaca. Perintah ini menghapus seluruh riwayat permintaan,
         * pengesahan, dan dokumen; pada peladen sungguhan tidak ada keadaan yang
         * membenarkannya, jadi tidak disediakan jalan untuk memaksanya.
         */
        if (app()->environment('production')) {
            $this->components->error('Ditolak: APP_ENV=production.');
            $this->line('  Reset demo hanya untuk lingkungan pengembangan dan pengujian.');
            $this->line('  Tidak ada data yang dihapus.');

            return self::FAILURE;
        }

        $this->components->warn(
            'Seluruh isi basis data "' . Cadangan::namaBasisData() . '" akan dibuang dan dibentuk ulang, '
            . 'beserta tanda tangan dan dokumen yang tersimpan.'
        );
        $this->line('  Lingkungan sekarang: <fg=cyan>' . app()->environment() . '</>');

        if (! $this->option('paksa') && ! $this->confirm('Lanjutkan reset demo?', false)) {
            $this->line('  Dibatalkan. Tidak ada data yang dihapus.');

            return self::SUCCESS;
        }

        $this->newLine();

        // migrate:fresh membuang seluruh tabel lalu membangunnya kembali,
        // sehingga urutan kunci asing tidak perlu diurus sendiri dan tidak
        // mungkin ada sisa yang terlewat.
        $this->components->task('Membangun ulang seluruh tabel', function (): bool {
            $this->callSilent('migrate:fresh', ['--force' => true]);

            return true;
        });

        $this->components->task('Mengisi data demo bawaan', function (): bool {
            $this->callSilent('db:seed', ['--force' => true]);

            return true;
        });

        $saldo = $this->isiSaldoAwal();

        $disapu = 0;

        foreach (Cadangan::DATA as $relatif) {
            $disapu += Cadangan::kosongkanDirektori(storage_path($relatif));
        }

        $this->components->task("Menyapu {$disapu} berkas dokumen dan tanda tangan", fn (): bool => true);

        $this->newLine();
        $this->components->twoColumnDetail('Saldo awal kartu kendali', $saldo);
        $this->newLine();

        $this->daftarAkun();

        $this->components->info('Reset demo selesai. Basis data siap dipakai untuk pengujian.');

        return self::SUCCESS;
    }

    /**
     * Menampilkan akun yang benar-benar ada sesudah seeder berjalan.
     *
     * Dibaca dari basis data, bukan ditulis sebagai daftar tetap, karena daftar
     * tetap akan basi tanpa ada yang menyadarinya: `KetuaTimSeeder` menimpa
     * surel akun Ketua Tim dengan surel pegawai yang sebenarnya bila berkas
     * sumbernya tersedia, sehingga alamat masuknya berubah mengikuti data.
     *
     * Yang ditampilkan adalah **surel**, sebab itulah kredensial pada halaman
     * masuk — bukan username, yang hanya menjadi kunci alami saat impor.
     */
    protected function daftarAkun(): void
    {
        $peran = ['admin' => 'Admin Sistem', 'kasubbag' => 'Kasubbag Umum',
            'petugas_gudang' => 'Petugas Gudang', 'ketua_tim' => 'Ketua Tim', 'tim' => 'Tim'];

        $this->components->twoColumnDetail('<fg=gray>Masuk memakai SUREL</>', '<fg=gray>kata sandi: password</>');

        foreach ($peran as $kunci => $label) {
            $akun = User::where('role', $kunci)->orderBy('id')->first();

            $this->components->twoColumnDetail(
                $label,
                $akun?->email ?: '<fg=yellow>tidak ada akun untuk peran ini</>',
            );
        }

        $this->newLine();
    }

    /**
     * Mengisi saldo awal kartu kendali dari berkas Sub-Bagian Umum.
     *
     * Seeder ini berdiri di luar DatabaseSeeder karena bergantung pada berkas
     * yang tidak ikut repositori. Tanpa saldo awal, kartu kendali hasil
     * pengujian dimulai dari angka nol padahal stoknya ratusan — kartunya benar
     * secara internal tetapi tidak menggambarkan keadaan sebenarnya.
     *
     * Ketiadaan berkasnya dilaporkan, bukan dilewati diam-diam, supaya penguji
     * tahu mengapa kartunya berbeda dari sesi sebelumnya.
     */
    protected function isiSaldoAwal(): string
    {
        $berkas = base_path(SaldoAwalKartuKendaliSeeder::BERKAS);

        if (! is_file($berkas)) {
            $this->components->warn('Saldo awal dilewati: ' . SaldoAwalKartuKendaliSeeder::BERKAS . ' tidak ada.');

            return '<fg=yellow>dilewati, berkas sumber tidak ada</>';
        }

        $this->components->task('Mencatat saldo awal kartu kendali', function (): bool {
            $this->callSilent('db:seed', [
                '--class' => SaldoAwalKartuKendaliSeeder::class,
                '--force' => true,
            ]);

            return true;
        });

        return '<fg=green>terisi dari ' . SaldoAwalKartuKendaliSeeder::BERKAS . '</>';
    }
}
