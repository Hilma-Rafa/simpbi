<?php

namespace App\Services\Impor;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\Tim;
use App\Models\User;
use App\Support\NomorWhatsApp;
use Illuminate\Support\Facades\DB;

/**
 * Impor daftar pengguna dari berkas sebar.
 *
 * Akun baru lahir dengan kata sandi awal yang seragam dan bertanda wajib
 * diganti, sehingga Administrator dapat membagikannya sekaligus tanpa kata
 * sandi seragam itu bertahan lama.
 *
 * Akun yang sudah ada hanya diperbarui data diri dan penempatannya; kata
 * sandinya tidak pernah disentuh. Berkas impor kerap diunggah ulang setelah
 * diperbaiki, dan impor ulang yang diam-diam menyetel ulang kata sandi akan
 * mengunci seluruh pengguna dari akunnya sendiri tanpa ada yang menduga
 * penyebabnya.
 *
 * Tim dirujuk dengan namanya, bukan dengan nomornya. Pengisi berkas mengenal
 * timnya sebagai nama, dan memaksa mereka mencari nomor di basis data hanya
 * memindahkan pekerjaan tanpa menambah ketelitian.
 */
class ImporPengguna
{
    public const JUDUL = 'Pengguna';

    /** @return list<Kolom> */
    public static function kolom(): array
    {
        $peran = implode(', ', array_values(UserForm::ROLE_OPTIONS));

        return [
            Kolom::buat(
                kunci: 'username',
                judul: 'Username',
                wajib: true,
                contoh: 'wanda.pribadi',
                catatan: 'Nama akun untuk masuk, harus unik. Inilah yang dipakai mencocokkan '
                    . 'baris dengan akun yang sudah ada, sehingga jangan diubah setelah dibagikan.',
            ),
            Kolom::buat(
                kunci: 'name',
                judul: 'Nama Lengkap',
                wajib: true,
                contoh: 'Wanda Pribadi',
                catatan: 'Nama sebagaimana dicantumkan pada dokumen bukti permintaan.',
            ),
            Kolom::buat(
                kunci: 'role',
                judul: 'Peran',
                wajib: true,
                contoh: 'Ketua Tim',
                catatan: 'Salah satu dari: ' . $peran . '.',
            ),
            Kolom::buat(
                kunci: 'tim',
                judul: 'Tim Kerja',
                contoh: 'Statistik Sosial',
                catatan: 'Nama tim kerja persis seperti terdaftar di sistem. Wajib untuk peran '
                    . 'Tim dan Ketua Tim; dikosongkan untuk peran yang melayani seluruh kantor. '
                    . 'Pengguna berperan Ketua Tim sekaligus ditetapkan sebagai ketua timnya.',
            ),
            Kolom::buat(
                kunci: 'email',
                judul: 'Email',
                contoh: 'wanda.pribadi@bps.go.id',
                catatan: 'Alamat surel kedinasan. Harus unik bila diisi.',
            ),
            Kolom::buat(
                kunci: 'nip',
                judul: 'NIP',
                contoh: '199001012015011001',
                catatan: 'Dicantumkan pada dokumen bukti permintaan.',
            ),
            Kolom::buat(
                kunci: 'no_hp',
                judul: 'Nomor WhatsApp',
                contoh: '081234567890',
                catatan: 'Boleh ditulis 08xx maupun 62xx; sistem menyeragamkannya sendiri. '
                    . 'Dipakai bila notifikasi WhatsApp dinyalakan.',
            ),
            Kolom::buat(
                kunci: 'status_aktif',
                judul: 'Aktif',
                contoh: 'Ya',
                catatan: 'Ya atau Tidak. Dikosongkan berarti Ya.',
            ),
        ];
    }

    public function jalankan(string $lintasanBerkas): HasilImpor
    {
        $hasil = new HasilImpor();
        $baris = app(PembacaBerkas::class)->baca($lintasanBerkas);

        $kolom = collect(static::kolom())
            ->mapWithKeys(fn (Kolom $k) => [$k->kunci => $k->tajukSeragam()])
            ->all();

        // Peran dibaca dari labelnya maupun dari nilai enumnya, sebab berkas
        // yang disalin dari sistem lama bisa memuat keduanya.
        $peranMenurutLabel = collect(UserForm::ROLE_OPTIONS)
            ->mapWithKeys(fn (string $label, string $nilai) => [mb_strtolower($label) => $nilai])
            ->all();

        foreach ($baris as $b) {
            $ambil = fn (string $kunci): string => trim($b['isi'][$kolom[$kunci]] ?? '');

            $username = $ambil('username');
            $nama     = $ambil('name');

            if ($username === '' || $nama === '') {
                $hasil->catatGalat($b['nomor'], 'Username dan Nama Lengkap wajib diisi.');

                continue;
            }

            $peranMentah = mb_strtolower($ambil('role'));
            $peran = $peranMenurutLabel[$peranMentah]
                ?? (isset(UserForm::ROLE_OPTIONS[$peranMentah]) ? $peranMentah : null);

            if ($peran === null) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Peran "' . $ambil('role') . '" tidak dikenali. Pilihannya: '
                    . implode(', ', array_values(UserForm::ROLE_OPTIONS)) . '.'
                );

                continue;
            }

            $namaTim = $ambil('tim');
            $tim = null;

            if ($namaTim !== '') {
                $tim = Tim::where('nama_tim', $namaTim)->first();

                if (! $tim) {
                    $hasil->catatGalat(
                        $b['nomor'],
                        'Tim Kerja "' . $namaTim . '" belum terdaftar. Impor tim kerjanya lebih dulu.'
                    );

                    continue;
                }
            }

            // Peran yang bekerja di dalam tim tidak bermakna tanpa timnya:
            // permintaan yang diajukannya tidak akan tahu harus menuju ketua
            // yang mana, dan penyaringan per tim ikut kehilangan pegangan.
            if (in_array($peran, ['tim', 'ketua_tim'], true) && ! $tim) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Peran ' . UserForm::ROLE_OPTIONS[$peran] . ' wajib disertai Tim Kerja.'
                );

                continue;
            }

            $aktif = $this->bacaYaTidak($ambil('status_aktif'));

            if ($aktif === null) {
                $hasil->catatGalat(
                    $b['nomor'],
                    'Kolom Aktif berisi "' . $ambil('status_aktif') . '", yang tidak dikenali. Isi Ya atau Tidak.'
                );

                continue;
            }

            $email = $ambil('email');

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $hasil->catatGalat($b['nomor'], 'Email "' . $email . '" tidak berbentuk alamat surel.');

                continue;
            }

            $bentrok = User::where('email', $email)
                ->where('username', '!=', $username)
                ->exists();

            if ($email !== '' && $bentrok) {
                $hasil->catatGalat($b['nomor'], 'Email "' . $email . '" sudah dipakai akun lain.');

                continue;
            }

            $atribut = [
                'name'         => $nama,
                'role'         => $peran,
                'tim_id'       => $tim?->id,
                'email'        => $email ?: null,
                'nip'          => $ambil('nip') ?: null,
                // Nomor diseragamkan di sini juga, sama seperti ketika diketik
                // lewat halaman Pengaturan, supaya gerbang WhatsApp tidak
                // pernah menerima bentuk yang berbeda-beda.
                'no_hp'        => NomorWhatsApp::normalkan($ambil('no_hp')),
                'status_aktif' => $aktif,
            ];

            DB::transaction(function () use ($username, $atribut, $peran, $tim, $hasil): void {
                $pengguna = User::where('username', $username)->first();

                if ($pengguna) {
                    // Kata sandi sengaja tidak termasuk: lihat keterangan kelas.
                    $pengguna->forceFill($atribut)->save();
                    $hasil->diperbarui++;
                } else {
                    $pengguna = User::create([
                        'username'          => $username,
                        ...$atribut,
                        'password'          => config('impor.sandi_awal'),
                        'harus_ganti_sandi' => true,
                    ]);
                    $hasil->ditambah++;
                }

                // Ketua tim ditetapkan dari sisi ini, bukan dari impor tim,
                // karena di sinilah kedua belah pihak sudah pasti ada.
                if ($peran === 'ketua_tim' && $tim) {
                    $tim->forceFill(['ketua_tim_id' => $pengguna->id])->save();
                }
            });
        }

        return $hasil;
    }

    /** Mengikuti aturan yang sama dengan impor tim kerja. */
    protected function bacaYaTidak(string $nilai): ?bool
    {
        $bersih = trim(mb_strtolower($nilai));

        if ($bersih === '') {
            return true;
        }

        if (in_array($bersih, ['ya', 'y', 'aktif', '1', 'true', 'benar'], true)) {
            return true;
        }

        if (in_array($bersih, ['tidak', 't', 'nonaktif', 'tidak aktif', '0', 'false', 'salah'], true)) {
            return false;
        }

        return null;
    }
}
