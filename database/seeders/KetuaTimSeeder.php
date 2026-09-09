<?php

namespace Database\Seeders;

use App\Models\Tim;
use App\Models\User;
use App\Support\NomorWhatsApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Pengisian data Ketua Tim delapan tim kerja beserta nomor WhatsApp-nya.
 *
 * Sumber data adalah berkas `database/data/nomor-wa-ketua-tim.xlsx`, yang
 * memuat data pribadi pegawai sehingga tidak ikut disimpan pada repositori.
 * Apabila berkas tersebut tidak ditemukan, seeder ini berhenti dengan pesan
 * peringatan dan tidak menggagalkan rangkaian seeder lainnya, agar salinan
 * repositori tanpa berkas sumber tetap dapat disiapkan.
 *
 * Nomor WhatsApp yang terisi menjadi tujuan pengiriman notifikasi kanal
 * WhatsApp (UC-23), sedangkan penetapan `tim.ketua_tim_id` melengkapi data
 * induk yang dipantau panel Kelengkapan Data Induk pada dasbor Admin.
 *
 * Kata sandi awal setiap akun yang baru dibuat adalah `password`, mengikuti
 * kebiasaan PenggunaSeeder untuk keperluan pengembangan. Pada lingkungan
 * sebenarnya kata sandi wajib diganti setelah masuk pertama kali.
 */
class KetuaTimSeeder extends Seeder
{
    /** Kolom yang wajib ada pada baris judul berkas sumber. */
    private const KOLOM = ['Tim', 'Ketua Tim', 'Nomor WhatsApp', 'E-Mail', 'NIP'];

    public function run(): void
    {
        $berkas = database_path('data/nomor-wa-ketua-tim.xlsx');

        if (! is_file($berkas)) {
            $this->command?->warn(
                'KetuaTimSeeder dilewati: berkas database/data/nomor-wa-ketua-tim.xlsx '
                . 'tidak ditemukan. Salin dari nomor-wa-ketua-tim.contoh.xlsx lalu isi datanya.'
            );

            return;
        }

        $baris = $this->bacaBerkas($berkas);

        if ($baris === []) {
            $this->command?->warn('KetuaTimSeeder dilewati: berkas sumber tidak berisi data.');

            return;
        }

        DB::transaction(fn () => $this->impor($baris));
    }

    // =====================================================================
    // PEMBACAAN BERKAS
    // =====================================================================

    /**
     * Membaca berkas sumber menjadi larik asosiatif menurut nama kolom.
     *
     * Kolom dipetakan dari baris judul, bukan dari urutan tetap, sehingga
     * penambahan atau pemindahan kolom pada berkas sumber tidak merusak
     * pembacaan selama nama kolomnya tidak berubah.
     *
     * @return array<int, array<string, string>>
     */
    private function bacaBerkas(string $berkas): array
    {
        $pembaca = new Reader();
        $pembaca->open($berkas);

        $judul = null;
        $hasil = [];

        foreach ($pembaca->getSheetIterator() as $lembar) {
            foreach ($lembar->getRowIterator() as $baris) {
                $sel = array_map(
                    fn ($s) => is_scalar($s->getValue()) ? trim((string) $s->getValue()) : '',
                    $baris->getCells()
                );

                if ($judul === null) {
                    $judul = $sel;

                    $hilang = array_diff(self::KOLOM, $judul);
                    if ($hilang !== []) {
                        $pembaca->close();

                        throw new \RuntimeException(
                            'Kolom berikut tidak ditemukan pada berkas sumber: '
                            . implode(', ', $hilang)
                        );
                    }

                    continue;
                }

                if (implode('', $sel) === '') {
                    continue;
                }

                $hasil[] = array_combine(
                    $judul,
                    array_pad(array_slice($sel, 0, count($judul)), count($judul), '')
                );
            }

            break; // hanya lembar pertama yang dibaca
        }

        $pembaca->close();

        return $hasil;
    }

    // =====================================================================
    // IMPOR
    // =====================================================================

    /**
     * @param  array<int, array<string, string>>  $baris
     */
    private function impor(array $baris): void
    {
        $timTersedia = Tim::query()->get();
        $jumlahBaru  = 0;
        $jumlahUbah  = 0;

        foreach ($baris as $data) {
            $namaTim = $data['Tim'];
            $nama    = $data['Ketua Tim'];
            $nip     = $this->angkaSaja($data['NIP']);
            $noHp    = NomorWhatsApp::normalkan($data['Nomor WhatsApp']);
            $email   = mb_strtolower($data['E-Mail']) ?: null;

            if ($nama === '' || $nip === '') {
                $this->command?->warn("Baris dilewati, nama atau NIP kosong: {$namaTim}");

                continue;
            }

            $tim = $this->cariTim($timTersedia, $namaTim);

            if (! $tim) {
                $this->command?->warn("Tim \"{$namaTim}\" tidak ada pada tabel tim, baris dilewati.");

                continue;
            }

            if ($noHp === null) {
                $this->command?->warn(
                    "Nomor WhatsApp {$nama} tidak dikenali (\"{$data['Nomor WhatsApp']}\"), "
                    . 'akun tetap dibuat tanpa nomor.'
                );
            }

            $pengguna = $this->cariPengguna($nip, $tim->id);
            $baru     = $pengguna === null;

            if ($baru) {
                $pengguna = new User([
                    'username' => $this->buatUsername($email, $nama),
                ]);

                $pengguna->password = Hash::make('password');
            }

            $pengguna->fill([
                'name'         => $nama,
                'nip'          => $nip,
                'email'        => $email,
                'no_hp'        => $noHp,
                'role'         => 'ketua_tim',
                'tim_id'       => $tim->id,
                'status_aktif' => true,
            ])->save();

            // Melengkapi penunjuk ketua pada data induk tim
            if ($tim->ketua_tim_id !== $pengguna->id) {
                $tim->update(['ketua_tim_id' => $pengguna->id]);
            }

            $baru ? $jumlahBaru++ : $jumlahUbah++;

            $this->command?->line(sprintf(
                '  %-10s %-20s %s (%s)',
                $baru ? 'dibuat' : 'diperbarui',
                $pengguna->username,
                $nama,
                $noHp ?? 'tanpa nomor'
            ));
        }

        $this->command?->info(
            "Ketua Tim: {$jumlahBaru} akun dibuat, {$jumlahUbah} akun diperbarui."
        );
    }

    // =====================================================================
    // PENCOCOKAN
    // =====================================================================

    /**
     * Mencari pengguna yang sudah ada untuk baris berjalan.
     *
     * Pencocokan utama memakai NIP, karena nilainya unik dan tidak berubah.
     * Apabila belum ada yang cocok, akun Ketua Tim bawaan PenggunaSeeder pada
     * tim yang sama — yang dikenali dari NIP yang masih kosong — diangkat
     * menjadi akun pegawai sebenarnya, sehingga satu tim tidak berakhir
     * memiliki dua Ketua Tim.
     */
    private function cariPengguna(string $nip, int $timId): ?User
    {
        return User::query()->where('nip', $nip)->first()
            ?? User::query()
                ->where('role', 'ketua_tim')
                ->where('tim_id', $timId)
                ->whereNull('nip')
                ->first();
    }

    /**
     * Mencocokkan nama tim pada berkas sumber dengan tabel tim.
     *
     * Pencocokan dilakukan bertingkat: sama persis, lalu setelah huruf
     * disamakan dan tanda baca dibuang, lalu terakhir dengan toleransi
     * selisih tiga huruf. Tingkat terakhir diperlukan karena penulisan nama
     * tim pada berkas sumber kerap berbeda tipis, misalnya kehilangan satu
     * huruf, sementara tim yang dimaksud tetap dapat dikenali dengan pasti.
     *
     * @param  Collection<int, Tim>  $timTersedia
     */
    private function cariTim(Collection $timTersedia, string $namaTim): ?Tim
    {
        if ($namaTim === '') {
            return null;
        }

        $tepat = $timTersedia->firstWhere('nama_tim', $namaTim);
        if ($tepat) {
            return $tepat;
        }

        $dicari = $this->sederhanakan($namaTim);

        $serupa = $timTersedia->first(
            fn (Tim $t) => $this->sederhanakan($t->nama_tim) === $dicari
        );
        if ($serupa) {
            return $serupa;
        }

        $terdekat = $timTersedia
            ->map(fn (Tim $t) => [
                'tim'     => $t,
                'selisih' => levenshtein($dicari, $this->sederhanakan($t->nama_tim)),
            ])
            ->sortBy('selisih')
            ->first();

        if ($terdekat && $terdekat['selisih'] <= 3) {
            $this->command?->warn(sprintf(
                'Nama tim "%s" dicocokkan dengan "%s" (selisih %d huruf).',
                $namaTim,
                $terdekat['tim']->nama_tim,
                $terdekat['selisih']
            ));

            return $terdekat['tim'];
        }

        return null;
    }

    /** Menyederhanakan teks menjadi huruf kecil tanpa tanda baca dan spasi. */
    private function sederhanakan(string $teks): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($teks)) ?? '';
    }

    // =====================================================================
    // PEMBERSIHAN NILAI
    // =====================================================================

    /** Menyisakan angka saja, misalnya untuk NIP yang tersimpan bergaya teks. */
    private function angkaSaja(string $nilai): string
    {
        return preg_replace('/\D+/', '', $nilai) ?? '';
    }

    /**
     * Menyusun nama pengguna dari bagian awal alamat surel, atau dari nama
     * pegawai apabila surel kosong, lalu memastikan nilainya belum terpakai.
     */
    private function buatUsername(?string $email, string $nama): string
    {
        $dasar = $email ? Str::before($email, '@') : Str::slug($nama, '.');
        $dasar = preg_replace('/[^a-z0-9._-]/', '', mb_strtolower($dasar)) ?: 'ketua';
        $dasar = mb_substr($dasar, 0, 45);

        $username = $dasar;
        $urutan   = 1;

        while (User::query()->where('username', $username)->exists()) {
            $username = $dasar . ++$urutan;
        }

        return $username;
    }
}
