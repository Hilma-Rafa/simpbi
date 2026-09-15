<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan tanda tangan pengguna.
 *
 * Kanvas pada peramban menyerahkan hasil goresan sebagai data URI PNG. Kelas
 * ini menjadi satu-satunya pintu masuknya ke penyimpanan, sehingga pemeriksaan
 * bentuk dan ukurannya tidak perlu diulang di halaman Pengaturan maupun di
 * pop-up tahapan alur.
 *
 * Berkasnya diletakkan pada cakram `local`. Cakram `public` sengaja dihindari:
 * begitu `storage:link` dijalankan, apa pun di sana dapat diunduh siapa saja
 * yang menebak alamatnya — dan tanda tangan pegawai bukan sesuatu yang boleh
 * beredar begitu. Berkas ini hanya dibaca peladen ketika membentuk PDF.
 */
class TandaTangan
{
    /** Direktori penyimpanan pada cakram `local`. */
    public const DIREKTORI = 'tanda-tangan';

    /**
     * Batas ukuran data URI yang diterima, dalam bita.
     *
     * Kanvas 600×200 piksel menghasilkan PNG jauh di bawah angka ini. Batasnya
     * ada bukan untuk menolak goresan yang rumit, melainkan supaya kiriman yang
     * dibuat-buat tidak dapat memenuhi cakram lewat satu kolom formulir.
     */
    public const BATAS_BITA = 512 * 1024;

    /**
     * Menyimpan tanda tangan dari data URI PNG dan mengembalikan lintasannya.
     *
     * Berkas lama pengguna dihapus, bukan ditumpuk, sebab hanya satu tanda
     * tangan yang berlaku pada satu waktu dan berkas usang tidak punya guna.
     *
     * @throws \InvalidArgumentException bila data URI tidak dapat diterima
     */
    public static function simpan(User $pengguna, string $dataUri): string
    {
        $biner = static::pangkas(static::bacaDataUri($dataUri));

        static::hapus($pengguna);

        // Nama berkas diacak, bukan memakai id pengguna, agar lintasannya tidak
        // dapat ditebak seandainya direktori ini suatu saat terlanjur terbuka.
        $lintasan = static::DIREKTORI . '/' . Str::random(40) . '.png';

        Storage::disk('local')->put($lintasan, $biner);

        $pengguna->forceFill([
            'tanda_tangan_path' => $lintasan,
            'tanda_tangan_at'   => now(),
        ])->save();

        return $lintasan;
    }

    /** Menghapus tanda tangan pengguna beserta berkasnya, bila ada. */
    public static function hapus(User $pengguna): void
    {
        if (filled($pengguna->tanda_tangan_path)) {
            Storage::disk('local')->delete($pengguna->tanda_tangan_path);
        }

        $pengguna->forceFill([
            'tanda_tangan_path' => null,
            'tanda_tangan_at'   => null,
        ])->save();
    }

    /** Apakah pengguna sudah menyimpan tanda tangan yang berkasnya masih ada. */
    public static function tersedia(?User $pengguna): bool
    {
        return static::dataUri($pengguna) !== null;
    }

    /**
     * Tanda tangan tersimpan sebagai data URI, siap disematkan ke tampilan
     * maupun ke PDF — DomPDF tidak dapat mengambil berkas lewat jaringan,
     * sehingga gambar harus ikut sebagai data URI.
     *
     * Mengembalikan null bila pengguna belum menyimpan apa pun, atau bila
     * kolomnya terisi tetapi berkasnya sudah tidak ada. Keadaan kedua
     * diperlakukan sama dengan "belum ada" supaya dokumen tetap terbentuk,
     * bukan gagal karena berkas yang hilang.
     */
    public static function dataUri(?User $pengguna): ?string
    {
        if (! $pengguna || blank($pengguna->tanda_tangan_path)) {
            return null;
        }

        $cakram = Storage::disk('local');

        if (! $cakram->exists($pengguna->tanda_tangan_path)) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($cakram->get($pengguna->tanda_tangan_path));
    }

    /**
     * Memangkas ruang kosong di sekeliling goresan.
     *
     * Kanvas selalu lebih besar daripada tanda tangan yang digambar di atasnya,
     * dan besar sisanya berbeda-beda menurut lebar dialog, ukuran layar, serta
     * seberapa besar orangnya menggores. Dokumen mencetak tanda tangan pada
     * tinggi tetap, sehingga dua tanda tangan yang digores sama besar pada
     * kanvas berbeda ukuran akan tercetak dengan besar yang berbeda pula —
     * persis yang terjadi pada surat ketika tanda tangan Ketua Tim tampak
     * separuh ukuran tanda tangan Petugas Gudang.
     *
     * Dengan hanya menyimpan tintanya, ukuran kanvas tidak lagi berpengaruh
     * dan setiap tanda tangan tercetak sebesar ruang yang disediakan surat.
     *
     * Gambar yang seluruhnya kosong ditolak: kanvas tanpa satu goresan pun
     * bukan tanda tangan, dan membiarkannya tersimpan berarti dokumen terbit
     * dengan ruang tanda tangan yang tampak sengaja dikosongkan.
     *
     * @throws \InvalidArgumentException bila gambarnya kosong atau tidak terbaca
     */
    protected static function pangkas(string $biner): string
    {
        $gambar = @imagecreatefromstring($biner);

        if ($gambar === false) {
            throw new \InvalidArgumentException('Tanda tangan tidak dapat dibaca.');
        }

        imagealphablending($gambar, false);
        imagesavealpha($gambar, true);

        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);

        $kiri = $lebar;
        $kanan = -1;
        $atas = $tinggi;
        $bawah = -1;

        for ($y = 0; $y < $tinggi; $y++) {
            for ($x = 0; $x < $lebar; $x++) {
                // Alfa 127 berarti sepenuhnya tembus pandang. Ambangnya diberi
                // kelonggaran supaya piksel tepi goresan yang setengah tembus
                // tetap dihitung sebagai tinta.
                if (((imagecolorat($gambar, $x, $y) >> 24) & 0x7F) >= 120) {
                    continue;
                }

                $kiri  = min($kiri, $x);
                $kanan = max($kanan, $x);
                $atas  = min($atas, $y);
                $bawah = max($bawah, $y);
            }
        }

        if ($kanan < 0) {
            imagedestroy($gambar);

            throw new \InvalidArgumentException('Tanda tangan kosong, tidak ada goresan yang tersimpan.');
        }

        // Sedikit ruang di sekeliling tinta supaya goresan tidak menempel rapat
        // pada tepi gambar ketika dicetak.
        $jarak = 6;
        $kiri   = max(0, $kiri - $jarak);
        $atas   = max(0, $atas - $jarak);
        $kanan  = min($lebar - 1, $kanan + $jarak);
        $bawah  = min($tinggi - 1, $bawah + $jarak);

        $hasil = imagecrop($gambar, [
            'x'      => $kiri,
            'y'      => $atas,
            'width'  => $kanan - $kiri + 1,
            'height' => $bawah - $atas + 1,
        ]);

        imagedestroy($gambar);

        if ($hasil === false) {
            throw new \InvalidArgumentException('Tanda tangan tidak dapat dipangkas.');
        }

        imagesavealpha($hasil, true);

        ob_start();
        imagepng($hasil);
        $dipangkas = (string) ob_get_clean();
        imagedestroy($hasil);

        return $dipangkas;
    }

    /**
     * Membaca data URI PNG menjadi bita mentah.
     *
     * Hanya PNG yang diterima, sebab itulah yang dihasilkan kanvas. Menerima
     * jenis lain berarti menerima berkas yang tidak pernah dibuat oleh
     * antarmuka kita sendiri.
     */
    protected static function bacaDataUri(string $dataUri): string
    {
        if (strlen($dataUri) > static::BATAS_BITA) {
            throw new \InvalidArgumentException('Tanda tangan terlalu besar.');
        }

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', trim($dataUri), $cocok)) {
            throw new \InvalidArgumentException('Tanda tangan bukan gambar PNG yang sah.');
        }

        $biner = base64_decode($cocok[1], true);

        if ($biner === false || $biner === '') {
            throw new \InvalidArgumentException('Tanda tangan tidak dapat dibaca.');
        }

        // Delapan bita pertama berkas PNG selalu tetap. Memeriksanya menutup
        // celah data base64 sah yang isinya ternyata bukan gambar sama sekali.
        if (! str_starts_with($biner, "\x89PNG\r\n\x1a\n")) {
            throw new \InvalidArgumentException('Tanda tangan bukan gambar PNG yang sah.');
        }

        return $biner;
    }
}
