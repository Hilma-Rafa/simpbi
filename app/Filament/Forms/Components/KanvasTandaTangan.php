<?php

namespace App\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Kolom formulir berupa kanvas tanda tangan.
 *
 * Dibuat sebagai kelas tersendiri, bukan sekadar ViewField yang dipasang
 * berulang, karena komponen ini akan dipakai di tiga tempat dengan perilaku
 * yang harus persis sama: halaman Pengaturan, pop-up penyiapan barang, dan
 * pop-up konfirmasi penerimaan. Bila dipasang berulang sebagai ViewField,
 * perbedaan kecil antar pemasangan akan menyelinap tanpa ketahuan.
 *
 * Keadaan kolom ini berisi data URI PNG hasil goresan, atau null bila
 * pengguna belum menggambar apa pun pada kunjungan ini. Null tidak berarti
 * "tidak punya tanda tangan": pengguna yang sudah menyimpan sebelumnya tetap
 * bernilai null selama ia tidak menggambar ulang, dan itu memang benar —
 * tidak ada yang perlu disimpan ulang.
 */
class KanvasTandaTangan extends Field
{
    protected string $view = 'filament.forms.kanvas-tanda-tangan';

    /** Tanda tangan yang sudah tersimpan, sebagai data URI, bila ada. */
    protected Closure|string|null $tandaTanganTersimpan = null;

    /**
     * Tanda tangan tersimpan milik pengguna, untuk ditampilkan lebih dulu
     * sehingga ia tidak perlu menggambar ulang tanpa alasan.
     */
    public function tandaTanganTersimpan(Closure|string|null $dataUri): static
    {
        $this->tandaTanganTersimpan = $dataUri;

        return $this;
    }

    public function getTandaTanganTersimpan(): ?string
    {
        return $this->evaluate($this->tandaTanganTersimpan);
    }
}
