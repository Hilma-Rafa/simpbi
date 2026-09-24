<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Berkas panduan penggunaan SIMPBI (lihat config/pusat_bantuan.php).
         * Root-nya resources/Panduan-Pengguna, bukan storage/app — pemilik
         * sistem menaruh berkas final di sana. Tetap disk 'local' biasa
         * (bukan 'public'), sehingga hanya bisa diakses lewat rute unduh
         * bergerbang login (pusat-bantuan.unduh-panduan di routes/web.php),
         * tidak lewat tautan langsung.
         *
         * 'serve' => false (bukan true): opsi ini mendaftarkan rute publik
         * bawaan Laravel GET/PUT /storage/{path} (FilesystemServiceProvider,
         * hanya dilindungi signed URL, bukan gerbang login) — tidak pernah
         * dipakai kode aplikasi untuk disk ini, dan karena tidak menyetel
         * 'url' sendiri ia diam-diam menggantikan rute /storage/{path} milik
         * disk 'local' yang sudah ada (dua disk local berebut URI default
         * yang sama). Dimatikan supaya rute 'local' tidak tertimpa dan tidak
         * ada permukaan rute tambahan yang tidak perlu.
         */
        'panduan' => [
            'driver' => 'local',
            'root' => resource_path('Panduan-Pengguna'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
