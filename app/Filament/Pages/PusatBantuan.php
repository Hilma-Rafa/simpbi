<?php

namespace App\Filament\Pages;

use App\Support\KontakBantuan;
use App\Support\NomorWhatsApp;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pusat Bantuan: panduan penggunaan (berkas unduhan) dan kontak Sub-Bagian
 * Umum (WhatsApp, email, jam operasional).
 *
 * Seperti Pengaturan, halaman ini tidak didaftarkan pada menu samping —
 * jalan masuknya hanya lewat menu profil — dan terbuka bagi seluruh peran
 * yang sudah masuk tanpa pembatasan tambahan, mengikuti perilaku bawaan
 * Filament: pengguna yang belum masuk otomatis diarahkan ke halaman masuk
 * oleh middleware panel yang sama dipakai setiap halaman lain, tanpa kode
 * baru di sini.
 */
class PusatBantuan extends Page
{
    protected string $view = 'filament.pages.pusat-bantuan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $title = 'Pusat Bantuan';

    protected static ?string $slug = 'pusat-bantuan';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getSubheading(): ?string
    {
        return 'Panduan penggunaan dan kontak Sub-Bagian Umum.';
    }

    // =====================================================================
    // PANDUAN PENGGUNAAN
    // =====================================================================

    /** @return array{disk:string,path:string,nama_tampilan:string} */
    protected function konfigPanduan(): array
    {
        return config('pusat_bantuan.panduan');
    }

    public function panduanTersedia(): bool
    {
        $p = $this->konfigPanduan();

        return Storage::disk($p['disk'])->exists($p['path']);
    }

    public function panduanNamaTampilan(): string
    {
        return $this->konfigPanduan()['nama_tampilan'];
    }

    /** Format berkas dari ekstensi sungguhan, bukan ditulis mati. */
    public function panduanFormat(): string
    {
        return strtoupper(pathinfo($this->konfigPanduan()['path'], PATHINFO_EXTENSION));
    }

    /**
     * Ukuran berkas dari filesystem sungguhan, format angka Indonesia
     * (koma sebagai pemisah desimal).
     */
    public function panduanUkuran(): string
    {
        $p     = $this->konfigPanduan();
        $bytes = Storage::disk($p['disk'])->size($p['path']);

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
        }

        return number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.') . ' KB';
    }

    public function panduanUrl(): string
    {
        return route('pusat-bantuan.unduh-panduan');
    }

    // =====================================================================
    // KONTAK — WHATSAPP
    // =====================================================================

    /**
     * "+62 812-3809-6104" dari nomor tersimpan "6281238096104", murni
     * presentasi — nilai yang tersimpan di tabel pengaturan tidak disentuh.
     * Panjang yang tidak cocok dengan pola 3-4-4 ditampilkan apa adanya
     * dengan awalan "+".
     */
    public function nomorWhatsAppTampilan(): ?string
    {
        $nomor = KontakBantuan::nomor();

        if ($nomor === null) {
            return null;
        }

        if (Str::startsWith($nomor, '62')) {
            $sisa = substr($nomor, 2);

            if (strlen($sisa) === 11) {
                return '+62 ' . substr($sisa, 0, 3) . '-' . substr($sisa, 3, 4) . '-' . substr($sisa, 7, 4);
            }
        }

        return '+' . $nomor;
    }

    /** Tautan wa.me tanpa pesan otomatis (berbeda dari kaki halaman masuk). */
    public function tautanWhatsApp(): ?string
    {
        return KontakBantuan::tautanWhatsApp(null);
    }

    // =====================================================================
    // KONTAK — EMAIL
    // =====================================================================

    public function email(): string
    {
        return config('pusat_bantuan.email');
    }

    // =====================================================================
    // JAM OPERASIONAL
    // =====================================================================

    protected const URUTAN_HARI = [
        'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis',
        'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu' => 'Minggu',
    ];

    /**
     * Mengelompokkan hari-hari berurutan dengan jam yang sama persis menjadi
     * satu baris tampilan ("Senin – Kamis", dst.), dibangun dari struktur
     * config/pusat_bantuan.php — bukan ditulis mati di templat.
     *
     * @return list<array{label:string, jam:?string, libur:bool}>
     */
    public function barisJamLayanan(): array
    {
        $jam    = config('pusat_bantuan.jam_layanan');
        $hasil  = [];
        $urutan = array_keys(self::URUTAN_HARI);
        $i      = 0;

        while ($i < count($urutan)) {
            $kunci = $urutan[$i];
            $nilai = $jam[$kunci] ?? null;
            $akhir = $i;

            while (
                $akhir + 1 < count($urutan)
                && ($jam[$urutan[$akhir + 1]] ?? null) === $nilai
            ) {
                $akhir++;
            }

            $label = $akhir === $i
                ? self::URUTAN_HARI[$kunci]
                : self::URUTAN_HARI[$kunci] . ' – ' . self::URUTAN_HARI[$urutan[$akhir]];

            $hasil[] = [
                'label' => $label,
                'jam'   => $nilai ? $this->formatJam($nilai['buka']) . ' – ' . $this->formatJam($nilai['tutup']) : null,
                'libur' => $nilai === null,
            ];

            $i = $akhir + 1;
        }

        return $hasil;
    }

    /** "08:00" -> "08.00", mengikuti format waktu existing pada Batas Waktu Alur. */
    protected function formatJam(string $hm): string
    {
        return str_replace(':', '.', $hm);
    }

    public function catatanBalasan(): string
    {
        return config('pusat_bantuan.catatan_balasan');
    }

    /**
     * Struktur jam layanan yang sama, dibaca ulang oleh JS sisi klien untuk
     * indikator status layanan (Intl.DateTimeFormat, zona Asia/Jakarta) —
     * satu sumber, tidak dihitung ulang di server maupun ditulis dua kali.
     *
     * @return array<string, array{buka:string,tutup:string}|null>
     */
    public function jamLayananJson(): array
    {
        return config('pusat_bantuan.jam_layanan');
    }

    public function zonaWaktu(): string
    {
        return config('pusat_bantuan.zona_waktu');
    }
}
