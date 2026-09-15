<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Pencadangan dan pemulihan data SIMPBI.
 *
 * Kelas ini menjadi satu-satunya tempat yang mengetahui bentuk arsip cadangan,
 * sehingga perintah mencadangkan dan memulihkan tidak pernah berbeda pendapat
 * tentang apa isinya. Perintahnya sendiri hanya mengurus percakapan dengan
 * penggunanya — konfirmasi, keluaran, dan kode keluar.
 *
 * Yang dicadangkan bukan hanya basis data. Tanda tangan pegawai dan dokumen
 * yang sudah disahkan tersimpan sebagai berkas, dan basis data hanya menyimpan
 * lintasannya. Dokumen itu pun tidak dapat dibentuk ulang secara setara:
 * dokumen sengaja dibekukan saat terbit, sebab tanda tangan dibaca dari akun
 * penggunanya dan akan ikut berubah bila orangnya menggambar ulang tanda
 * tangannya. Cadangan yang hanya mengambil basis data karena itu kehilangan
 * justru bagian yang paling tidak tergantikan.
 *
 * Yang sengaja TIDAK ikut: berkas `.env` beserta seluruh kredensial di
 * dalamnya. Cadangan berpindah tempat dan berganti tangan; kunci aplikasi dan
 * token gerbang WhatsApp tidak boleh ikut berpindah bersamanya.
 */
class Cadangan
{
    /**
     * Direktori penyimpanan arsip, relatif terhadap `storage/`.
     *
     * Sengaja di luar `storage/app/`, karena dua sebab. Pertama, `public/storage`
     * menunjuk ke `storage/app/public`, sehingga apa pun di sana dapat diunduh
     * siapa saja yang menebak alamatnya. Kedua, direktori inilah yang justru
     * tidak boleh ikut terarsip — arsip yang memuat arsip sebelumnya akan
     * berlipat ganda setiap kali dijalankan.
     */
    public const DIREKTORI = 'cadangan';

    /** Versi bentuk arsip, diperiksa saat pemulihan. */
    public const VERSI_MANIFES = 1;

    /** Nama berkas keterangan di dalam arsip. */
    public const MANIFES = 'manifes.json';

    /**
     * Direktori data yang ikut dicadangkan.
     *
     * Kuncinya adalah lintasan di dalam arsip, nilainya lintasan sebenarnya
     * relatif terhadap `storage/`. Didaftar satu per satu, bukan menyapu
     * seluruh `storage/app`, supaya singgahan kerangka kerja dan berkas
     * sementara tidak ikut membengkakkan arsip.
     */
    public const DATA = [
        'storage/private/tanda-tangan'   => 'app/private/tanda-tangan',
        'storage/public/bukti-permintaan' => 'app/public/bukti-permintaan',
        'storage/public/bast-mutasi'      => 'app/public/bast-mutasi',
    ];

    /** Lintasan direktori arsip. */
    public static function direktori(): string
    {
        return storage_path(static::DIREKTORI);
    }

    /**
     * Membentuk arsip cadangan dan mengembalikan keterangannya.
     *
     * @return array{lintasan: string, bita: int, berkas_data: int, penggerak: string, basis_data: string}
     */
    public static function buat(?string $nama = null): array
    {
        $direktori = static::direktori();

        if (! is_dir($direktori) && ! @mkdir($direktori, 0700, true) && ! is_dir($direktori)) {
            throw new RuntimeException("Direktori cadangan tidak dapat dibuat: {$direktori}");
        }

        $penggerak = static::penggerak();
        $lintasan  = $direktori . DIRECTORY_SEPARATOR . ($nama ?: static::namaBerkas());

        // Curahan basis data dibentuk lebih dulu sebagai berkas tersendiri,
        // bukan langsung dialirkan ke dalam arsip: bila proses curahannya gagal
        // separuh jalan, yang tertinggal hanya berkas sementara, bukan arsip
        // cacat yang tampak seperti cadangan yang sah.
        $sementara = $direktori . DIRECTORY_SEPARATOR . '.curahan-' . getmypid();
        @unlink($sementara);

        try {
            $namaCurahan = $penggerak === 'sqlite' ? 'basis-data.sqlite' : 'basis-data.sql';

            $penggerak === 'sqlite'
                ? static::curahSqlite($sementara)
                : static::curahMysql($sementara);

            $zip = new ZipArchive;

            if ($zip->open($lintasan, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Arsip tidak dapat dibuat: {$lintasan}");
            }

            $zip->addFile($sementara, $namaCurahan);

            $berkasData = 0;

            foreach (static::DATA as $didalamArsip => $relatif) {
                foreach (static::berkasDi(storage_path($relatif)) as $berkas => $nisbi) {
                    $zip->addFile($berkas, $didalamArsip . '/' . $nisbi);
                    $berkasData++;
                }
            }

            $zip->addFromString(static::MANIFES, json_encode([
                'aplikasi'         => 'SIMPBI',
                'versi_manifes'    => static::VERSI_MANIFES,
                'dibuat_pada'      => now()->toIso8601String(),
                'penggerak'        => $penggerak,
                'basis_data'       => static::namaBasisData(),
                'berkas_basis_data' => $namaCurahan,
                'berkas_data'      => $berkasData,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip->close();
        } finally {
            @unlink($sementara);
        }

        // Arsip memuat seluruh isi basis data, jadi haknya dipersempit sejauh
        // yang dapat dilakukan sistem berkas — pada Windows ini tidak berlaku,
        // karena itu letaknya di luar jangkauan web tetap menjadi penjaganya.
        @chmod($lintasan, 0600);

        return [
            'lintasan'    => $lintasan,
            'bita'        => (int) filesize($lintasan),
            'berkas_data' => $berkasData,
            'penggerak'   => $penggerak,
            'basis_data'  => static::namaBasisData(),
        ];
    }

    /**
     * Memeriksa arsip dan mengembalikan manifesnya.
     *
     * Dipanggil sebelum apa pun dihapus. Arsip yang tidak lolos pemeriksaan di
     * sini tidak akan pernah sampai pada tahap menimpa data.
     *
     * @return array<string,mixed>
     */
    public static function periksa(string $lintasan): array
    {
        if (! is_file($lintasan)) {
            throw new RuntimeException("Berkas cadangan tidak ditemukan: {$lintasan}");
        }

        $zip = new ZipArchive;

        if ($zip->open($lintasan) !== true) {
            throw new RuntimeException('Berkas cadangan bukan arsip yang dapat dibaca.');
        }

        try {
            $mentah = $zip->getFromName(static::MANIFES);

            if ($mentah === false) {
                throw new RuntimeException('Arsip tidak memuat ' . static::MANIFES . ', jadi bukan cadangan SIMPBI.');
            }

            $manifes = json_decode($mentah, true);

            if (! is_array($manifes) || ($manifes['aplikasi'] ?? null) !== 'SIMPBI') {
                throw new RuntimeException('Manifes arsip rusak atau bukan milik SIMPBI.');
            }

            if (($manifes['versi_manifes'] ?? null) !== static::VERSI_MANIFES) {
                throw new RuntimeException(
                    'Versi manifes ' . var_export($manifes['versi_manifes'] ?? null, true)
                    . ' tidak dikenali oleh pemasangan ini (mengenal versi ' . static::VERSI_MANIFES . ').'
                );
            }

            $penggerak = static::penggerak();

            if (($manifes['penggerak'] ?? null) !== $penggerak) {
                throw new RuntimeException(
                    'Cadangan dibuat untuk basis data "' . ($manifes['penggerak'] ?? '?')
                    . '", sedangkan pemasangan ini memakai "' . $penggerak
                    . '". Curahan keduanya tidak saling dapat dibaca.'
                );
            }

            $curahan = $manifes['berkas_basis_data'] ?? '';

            if ($curahan === '' || $zip->locateName($curahan) === false) {
                throw new RuntimeException("Arsip tidak memuat curahan basis data ({$curahan}).");
            }

            $keterangan = $zip->statName($curahan);

            if (! $keterangan || ($keterangan['size'] ?? 0) <= 0) {
                throw new RuntimeException('Curahan basis data di dalam arsip berukuran nol.');
            }

            $manifes['bita_curahan'] = (int) $keterangan['size'];

            return $manifes;
        } finally {
            $zip->close();
        }
    }

    /**
     * Memulihkan basis data dan berkas data dari arsip.
     *
     * Arsip harus sudah lolos {@see static::periksa()} lebih dulu; metode ini
     * tidak memeriksanya kembali dan langsung menimpa.
     */
    public static function pulihkan(string $lintasan, array $manifes): void
    {
        $zip = new ZipArchive;

        if ($zip->open($lintasan) !== true) {
            throw new RuntimeException('Berkas cadangan tidak dapat dibuka.');
        }

        $sementara = static::direktori() . DIRECTORY_SEPARATOR . '.pulih-' . getmypid();
        @unlink($sementara);

        try {
            $isi = $zip->getFromName($manifes['berkas_basis_data']);

            if ($isi === false) {
                throw new RuntimeException('Curahan basis data gagal dibaca dari arsip.');
            }

            file_put_contents($sementara, $isi);
            unset($isi);

            static::penggerak() === 'sqlite'
                ? static::pulihkanSqlite($sementara)
                : static::pulihkanMysql($sementara);

            foreach (static::DATA as $didalamArsip => $relatif) {
                static::kosongkanDirektori(storage_path($relatif));
            }

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = $zip->getNameIndex($i);

                if ($nama === false || str_ends_with($nama, '/')) {
                    continue;
                }

                $tujuan = static::tujuanData($nama);

                if ($tujuan === null) {
                    continue;
                }

                $direktori = dirname($tujuan);

                if (! is_dir($direktori)) {
                    @mkdir($direktori, 0755, true);
                }

                file_put_contents($tujuan, (string) $zip->getFromIndex($i));
            }
        } finally {
            @unlink($sementara);
            $zip->close();
        }
    }

    /**
     * Menerjemahkan lintasan di dalam arsip menjadi lintasan sebenarnya.
     *
     * Mengembalikan null untuk anggota arsip yang bukan berkas data, termasuk
     * manifes dan curahan basis data. Lintasan yang mengandung ".." ditolak,
     * sebab arsip dapat berasal dari luar dan tidak boleh menulis ke mana pun
     * di luar direktori yang memang disediakan.
     */
    protected static function tujuanData(string $didalamArsip): ?string
    {
        if (str_contains($didalamArsip, '..')) {
            return null;
        }

        foreach (static::DATA as $awalan => $relatif) {
            if (str_starts_with($didalamArsip, $awalan . '/')) {
                $sisa = substr($didalamArsip, strlen($awalan) + 1);

                return $sisa === '' ? null : storage_path($relatif) . DIRECTORY_SEPARATOR . $sisa;
            }
        }

        return null;
    }

    /**
     * Membuat cuplikan basis data SQLite.
     *
     * `VACUUM INTO` adalah cara yang disediakan SQLite sendiri untuk menyalin
     * basis data yang sedang dipakai, dan menghasilkan berkas yang utuh
     * meskipun ada transaksi berjalan. Menyalin berkasnya dengan copy() tidak
     * menjamin itu: berkas -wal yang menyertainya bisa memuat perubahan yang
     * belum menyatu, sehingga salinannya tertinggal.
     */
    protected static function curahSqlite(string $tujuan): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("VACUUM INTO '" . str_replace("'", "''", str_replace('\\', '/', $tujuan)) . "'");
    }

    /** Membuat curahan SQL basis data MySQL dengan mysqldump. */
    protected static function curahMysql(string $tujuan): void
    {
        $tetapan = config('database.connections.' . config('database.default'));

        $berkas = fopen($tujuan, 'wb');

        if ($berkas === false) {
            throw new RuntimeException('Berkas curahan sementara tidak dapat dibuat.');
        }

        try {
            $proses = new Process([
                static::perkakas('mysqldump'),
                '--host=' . $tetapan['host'],
                '--port=' . $tetapan['port'],
                '--user=' . $tetapan['username'],
                // Curahan diambil di dalam satu transaksi supaya isinya
                // konsisten tanpa mengunci tabel — peladen tetap dapat melayani
                // pengguna selama pencadangan berjalan.
                '--single-transaction',
                '--quick',
                // Tanpa ini mysqldump meminta hak PROCESS, yang lazimnya tidak
                // diberikan kepada akun aplikasi.
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                $tetapan['database'],
            ], base_path(), static::lingkungan($tetapan), null, 3600);

            // Keluaran dialirkan langsung ke berkas, bukan ditampung di memori,
            // sebab curahan basis data yang sudah terpakai setahun dapat jauh
            // melampaui batas memori PHP.
            $proses->run(function (string $jenis, string $petak) use ($berkas): void {
                if ($jenis === Process::OUT) {
                    fwrite($berkas, $petak);
                }
            });

            if (! $proses->isSuccessful()) {
                throw new RuntimeException('mysqldump gagal: ' . trim($proses->getErrorOutput()));
            }
        } finally {
            fclose($berkas);
        }
    }

    /** Menimpa berkas basis data SQLite dengan salinan dari arsip. */
    protected static function pulihkanSqlite(string $sumber): void
    {
        $tujuan = config('database.connections.sqlite.database');

        // Sambungan ditutup lebih dulu; pada Windows berkas yang sedang terbuka
        // tidak dapat ditimpa.
        DB::disconnect();

        foreach (['-wal', '-shm'] as $imbuhan) {
            @unlink($tujuan . $imbuhan);
        }

        if (! @copy($sumber, $tujuan)) {
            throw new RuntimeException("Berkas basis data tidak dapat ditimpa: {$tujuan}");
        }
    }

    /** Menjalankan curahan SQL ke dalam basis data MySQL. */
    protected static function pulihkanMysql(string $sumber): void
    {
        $tetapan = config('database.connections.' . config('database.default'));

        $berkas = fopen($sumber, 'rb');

        if ($berkas === false) {
            throw new RuntimeException('Curahan tidak dapat dibaca.');
        }

        try {
            $proses = new Process([
                static::perkakas('mysql'),
                '--host=' . $tetapan['host'],
                '--port=' . $tetapan['port'],
                '--user=' . $tetapan['username'],
                '--default-character-set=utf8mb4',
                $tetapan['database'],
            ], base_path(), static::lingkungan($tetapan), $berkas, 3600);

            $proses->run();

            if (! $proses->isSuccessful()) {
                throw new RuntimeException('mysql gagal: ' . trim($proses->getErrorOutput()));
            }
        } finally {
            fclose($berkas);
        }

        DB::disconnect();
    }

    /**
     * Sandi diserahkan lewat peubah lingkungan, bukan sebagai argumen.
     *
     * Argumen baris perintah terbaca oleh siapa pun yang dapat melihat daftar
     * proses pada peladen, sedangkan lingkungan proses hanya terbaca oleh
     * pemiliknya. `MYSQL_PWD` memang dikenali kedua perkakas ini.
     *
     * @return array<string,string>
     */
    protected static function lingkungan(array $tetapan): array
    {
        return ['MYSQL_PWD' => (string) ($tetapan['password'] ?? '')];
    }

    /** Nama perkakas luar, dapat ditunjuk lewat lingkungan bila tidak ada di PATH. */
    protected static function perkakas(string $nama): string
    {
        return (string) env('SIMPBI_' . strtoupper($nama), $nama);
    }

    /** Penggerak basis data yang sedang dipakai. */
    public static function penggerak(): string
    {
        return (string) config('database.connections.' . config('database.default') . '.driver');
    }

    /** Nama basis data, atau nama berkasnya pada SQLite. */
    public static function namaBasisData(): string
    {
        $nama = (string) config('database.connections.' . config('database.default') . '.database');

        return static::penggerak() === 'sqlite' ? basename($nama) : $nama;
    }

    /** Nama berkas arsip, bercap waktu agar tidak pernah menimpa yang lama. */
    public static function namaBerkas(): string
    {
        return 'simpbi-backup-' . now()->format('Y-m-d-His') . '.zip';
    }

    /**
     * Seluruh berkas di bawah sebuah direktori.
     *
     * @return array<string,string> lintasan mutlak => lintasan relatif
     */
    protected static function berkasDi(string $direktori): array
    {
        if (! is_dir($direktori)) {
            return [];
        }

        $hasil = [];

        $penjelajah = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($direktori, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($penjelajah as $berkas) {
            /** @var \SplFileInfo $berkas */
            if (! $berkas->isFile() || $berkas->getFilename() === '.gitignore') {
                continue;
            }

            $nisbi = str_replace('\\', '/', substr($berkas->getPathname(), strlen($direktori) + 1));
            $hasil[$berkas->getPathname()] = $nisbi;
        }

        return $hasil;
    }

    /**
     * Mengosongkan isi sebuah direktori tanpa menghapus direktorinya.
     *
     * Berkas `.gitignore` dipertahankan, sebab itulah yang menjaga direktori
     * tetap ada di dalam repositori setelah isinya habis.
     */
    public static function kosongkanDirektori(string $direktori): int
    {
        if (! is_dir($direktori)) {
            return 0;
        }

        $terhapus = 0;

        foreach (static::berkasDi($direktori) as $berkas => $nisbi) {
            if (@unlink($berkas)) {
                $terhapus++;
            }
        }

        return $terhapus;
    }
}
