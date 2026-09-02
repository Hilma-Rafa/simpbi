<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('name', 100);
            $table->string('nip', 30)->nullable();
            $table->string('email', 100)->nullable();
            // Format E.164 tanpa tanda plus, contoh: 6281296875227
            $table->string('no_hp', 20)->nullable();
            $table->enum('role', ['admin', 'tim', 'ketua_tim', 'petugas_gudang', 'kasubbag']);
            $table->foreignId('tim_id')->nullable()->constrained('tim')->nullOnDelete();
            $table->boolean('status_aktif')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        // FK ketua_tim ditambahkan di sini karena users dibuat setelah tim
        Schema::table('tim', function (Blueprint $table) {
            $table->foreign('ketua_tim_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tim', fn (Blueprint $t) => $t->dropForeign(['ketua_tim_id']));
        Schema::dropIfExists('users');
    }
};
