<?php

namespace App\Filament\Support;

use App\Services\Impor\HasilImpor;
use App\Services\Impor\PembuatTemplate;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;

/**
 * Tombol impor berkas, seragam untuk seluruh data induk.
 *
 * Satu tombol membuka satu dialog yang memuat dua-duanya: tempat mengunggah
 * berkas, dan tombol mengunduh templatnya di kaki dialog. Menyajikannya
 * sebagai dua pilihan yang harus dipilih lebih dulu justru menyulitkan —
 * keduanya bukan alternatif melainkan dua langkah dari satu pekerjaan, dan
 * orang yang baru pertama kali mengimpor selalu memerlukan templatnya dulu
 * lalu kembali lagi. Dengan susunan ini dialognya tidak perlu ditutup.
 */
class AksiImpor
{
    /**
     * Isian tambahan (`$isian`) tampil di bawah unggahan berkas dan ikut
     * diteruskan ke `$impor`. Dipakai impor Stok Awal, yang memerlukan tanggal
     * dan nomor dokumen yang berlaku untuk seluruh berkas — sama seperti satu
     * nota pada Catat Stok Masuk. Impor data induk tidak memakainya, dan
     * closure satu parameter mereka tetap berjalan apa adanya.
     *
     * @param  list<\App\Services\Impor\Kolom>  $kolom
     * @param  Closure(string, array<string,mixed>): HasilImpor  $impor  menerima lintasan berkas dan isian dialog
     * @param  array<int,\Filament\Schemas\Components\Component>  $isian
     */
    public static function buat(
        string $judul,
        array $kolom,
        string $namaTemplate,
        Closure $impor,
        string $label = 'Impor',
        array $isian = [],
    ): Action {
        return Action::make('impor')
            ->label($label)
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->outlined()
            ->modalHeading('Impor ' . $judul)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Impor')
            ->modalCancelActionLabel('Batal')
            ->schema([
                FileUpload::make('berkas')
                    ->label('Berkas Excel')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                    ])
                    ->required()
                    /*
                     * Berkas tidak disimpan ke cakram mana pun: ia hanya dibaca
                     * sekali lalu dibuang. Berkas impor kerap memuat data
                     * kepegawaian, dan menyimpan salinannya di peladen berarti
                     * menambah satu tempat lagi yang harus dijaga tanpa ada
                     * yang akan membacanya kembali.
                     */
                    ->storeFiles(false)
                    ->helperText(new HtmlString(
                        'Belum punya berkasnya? Unduh templatnya lebih dulu lewat tombol di bawah, '
                        . 'isi datanya, lalu unggah kembali di sini.'
                    )),
                ...$isian,
            ])
            ->extraModalFooterActions([
                // Bergaris warna utama seperti tombol unduhan lain (GayaUnduh),
                // bukan lagi tautan abu-abu yang tampak berbeda sendiri.
                GayaUnduh::terapkan(Action::make('unduhTemplate'))
                    ->label('Unduh Template')
                    ->action(fn () => app(PembuatTemplate::class)->buat($judul, $kolom, $namaTemplate)),
            ])
            ->action(function (array $data) use ($impor): void {
                $berkas = $data['berkas'];

                // FileUpload mengembalikan berkas unggahan sementara, kadang di
                // dalam larik ketika komponennya pernah berganti keadaan.
                $unggahan = is_array($berkas) ? reset($berkas) : $berkas;

                $hasil = $impor($unggahan->getRealPath(), $data);

                static::beritahukan($hasil);
            });
    }

    /**
     * Menyampaikan hasil impor.
     *
     * Galat ditampilkan beserta nomor barisnya dan dibuat menetap, sebab
     * pemberitahuan yang hilang sendiri setelah beberapa detik tidak mungkin
     * dipakai sebagai daftar perbaikan. Yang ditampilkan dibatasi lima baris;
     * berkas yang bermasalah pada puluhan baris biasanya salah kolom atau
     * salah berkas, dan menampilkan seluruhnya tidak menambah keterangan apa
     * pun yang berguna.
     */
    protected static function beritahukan(HasilImpor $hasil): void
    {
        $badan = $hasil->uraian();

        if ($hasil->adaGalat()) {
            $ditampilkan = array_slice($hasil->galat, 0, 5);
            $sisa = count($hasil->galat) - count($ditampilkan);

            $badan .= "\n\n" . implode("\n", $ditampilkan);

            if ($sisa > 0) {
                $badan .= "\n… dan " . $sisa . ' baris lain.';
            }
        }

        /*
         * Catatan bukan kegagalan, melainkan keterangan bahwa sebagian isi
         * berkas sengaja tidak dipakai. Ia ikut ditampilkan dan membuat
         * pemberitahuannya menetap, sebab keterangan semacam ini justru tidak
         * berguna bila hilang sendiri sebelum sempat dibaca.
         */
        if ($hasil->adaCatatan()) {
            $badan .= "

" . implode("
", $hasil->catatan);
        }

        $pemberitahuan = Notification::make()
            ->title($hasil->judul())
            ->body($badan);

        $hasil->adaGalat() || $hasil->adaCatatan()
            ? $pemberitahuan->warning()->persistent()
            : $pemberitahuan->success();

        $pemberitahuan->send();
    }
}
