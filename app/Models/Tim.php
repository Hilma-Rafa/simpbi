<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tim extends Model
{
    protected $table = 'tim';
    protected $guarded = [];

    /**
     * Nama tim sebagaimana ditampilkan pada kolom tabel yang sempit.
     *
     * Empat dari delapan tim kerja sudah membawa akronim resminya sendiri di
     * dalam namanya, ditulis dalam kurung di bagian akhir — "(PEK)",
     * "(Nerwilis)", "(IPDS)", dan "(PSS)". Akronim itu dipakai apa adanya,
     * sebab yang dipakai sehari-hari di kantor memang kependekannya.
     *
     * Tim yang tidak membawa akronim resmi tetap ditulis penuh. Menyingkat
     * sendiri nama yang tidak punya kependekan resmi berarti mengarang
     * istilah yang tidak dikenali siapa pun di BPS, dan itu lebih merugikan
     * daripada tabel yang sedikit melebar.
     *
     * Yang diringkas hanya tampilannya. Nilai pada basis data tidak diubah,
     * sehingga pencarian, penyaringan, dan berkas ekspor tetap memakai nama
     * resmi yang lengkap.
     */
    public static function ringkas(?string $namaTim): string
    {
        if (blank($namaTim)) {
            return '';
        }

        // Hanya kurung di ujung nama yang dianggap akronim; kurung di tengah
        // kalimat biasanya keterangan, bukan kependekan.
        return preg_match('/\(([^()]+)\)\s*$/u', $namaTim, $cocok) === 1
            ? trim($cocok[1])
            : $namaTim;
    }

    public function ketuaTim()
    {
        return $this->belongsTo(User::class, 'ketua_tim_id');
    }

    public function anggota()
    {
        return $this->hasMany(User::class, 'tim_id');
    }
}