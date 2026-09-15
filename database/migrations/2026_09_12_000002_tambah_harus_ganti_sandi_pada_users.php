<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda bahwa kata sandi wajib diganti pada kesempatan masuk berikutnya.
 *
 * Akun yang lahir dari impor massal berkata sandi awal yang sama untuk
 * semuanya, sebab Administrator harus dapat membagikannya sekaligus kepada
 * puluhan pegawai. Kata sandi seragam itu aman selama tidak bertahan: begitu
 * pemiliknya masuk, sistem menahannya sampai ia menggantinya sendiri.
 *
 * Penandanya menempel pada pengguna, bukan pada proses impornya, karena
 * keadaan yang ditandai memang keadaan pengguna — Administrator yang menyetel
 * ulang kata sandi seseorang lewat antarmuka kelak dapat memakai penanda yang
 * sama tanpa perlu apa pun yang baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('harus_ganti_sandi')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('harus_ganti_sandi');
        });
    }
};
