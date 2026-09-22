<?php

namespace App\Services;

use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menandai permintaan yang melewati batas waktu tahapan sebagai kedaluwarsa.
 *
 * Sebelumnya seluruh logika ini berada di dalam perintah artisan
 * permintaan:lepas-hold, sehingga hanya berjalan bila penjadwal Laravel aktif.
 * Pada pemasangan tanpa cron — termasuk saat pengembangan dan demonstrasi —
 * penjadwal itu tidak pernah dipicu, sehingga permintaan yang batas waktunya
 * sudah lewat tetap berstatus berjalan, tetap tampil pada daftar Permintaan
 * Barang, dan stoknya tetap terkunci. Logikanya dipindahkan ke layanan ini
 * agar dapat dipanggil dari dua arah: penjadwal, dan setiap kunjungan ke
 * panel melalui middleware SapuPermintaanKedaluwarsa.
 */
class KedaluwarsaService
{
    /** Status yang tahapannya masih berjalan dan stoknya masih terkunci. */
    public const STATUS_BERJALAN = [
        'menunggu_ketua',
        'menunggu_verifikasi',
        'menunggu_kasubbag',
        'siap_diproses',
        'siap_diambil',
    ];

    /**
     * Jeda minimum antar sapuan yang dipicu kunjungan halaman, dalam detik.
     *
     * Middleware ikut berjalan pada setiap permintaan Livewire, sehingga satu
     * halaman saja dapat memicu sapuan berkali-kali. Jeda ini menahannya agar
     * basis data tidak dibebani kueri berulang untuk hasil yang sama.
     */
    protected const JEDA_SAPUAN_DETIK = 60;

    public function __construct(protected StokService $stok) {}

    /**
     * Menyapu permintaan kedaluwarsa bila jeda sejak sapuan terakhir terlampaui.
     *
     * Dipakai oleh middleware. Cache::add bersifat atomik: hanya pemanggil
     * pertama yang berhasil menuliskan penanda, sehingga dua permintaan HTTP
     * yang datang bersamaan tidak menyapu secara berbarengan.
     */
    public function sapuBilaPerlu(): Collection
    {
        if (! Cache::add('sapuan-kedaluwarsa-terakhir', now()->toIso8601String(), self::JEDA_SAPUAN_DETIK)) {
            return collect();
        }

        return $this->sapu();
    }

    /**
     * Melepaskan kunci stok dan menandai kedaluwarsa seluruh permintaan
     * yang batas waktu tahapannya sudah terlewat.
     *
     * Mengembalikan daftar hasil per permintaan — kode, keberhasilan, dan
     * pesan galat bila ada — supaya perintah artisan dapat melaporkannya
     * kepada operator tanpa layanan ini perlu mengenal konsol.
     */
    public function sapu(): Collection
    {
        $kedaluwarsa = PermintaanBarang::query()
            ->whereIn('status', self::STATUS_BERJALAN)
            ->whereNotNull('hold_expired_at')
            ->where('hold_expired_at', '<', now())
            ->with('detail')
            ->get();

        return $kedaluwarsa->map(function (PermintaanBarang $permintaan): ?array {
            try {
                $diubah = false;

                DB::transaction(function () use (&$permintaan, &$diubah) {
                    // Status diperiksa ulang di dalam transaksi, dengan kunci baris:
                    // sapuan dapat berjalan berulang dan tumpang tindih, dan permintaan
                    // ini mungkin sudah ditangani sapuan lain atau oleh pengguna sejak
                    // daftar di atas dibaca.
                    $segar = PermintaanBarang::query()->whereKey($permintaan->id)->lockForUpdate()->first();

                    if (
                        ! $segar
                        || ! in_array($segar->status, self::STATUS_BERJALAN, true)
                        || $segar->hold_expired_at === null
                        || $segar->hold_expired_at->gte(now())
                    ) {
                        return;
                    }

                    $permintaan = $segar->load('detail');

                    // Tahap dibaca SEBELUM status diperbarui. Bila dibaca
                    // sesudahnya, statusnya sudah menjadi "kedaluwarsa"
                    // sehingga seluruh permintaan tercatat berhenti di tahap
                    // Ketua Tim — nilai bawaan pemetaan — dan riwayatnya tidak
                    // lagi dapat dipakai menelusuri di titik mana permintaan
                    // sebenarnya terhenti.
                    $tahap = static::tahapTerakhir($permintaan->status);

                    // Batas yang terlewat disalin sebelum dikosongkan, sebab
                    // riwayat mencatat kejadian pada saat batas itu jatuh,
                    // bukan pada saat sapuan kebetulan menemukannya. Keduanya
                    // dapat terpaut jauh — batas yang jatuh Jumat sore baru
                    // ditemukan Senin pagi bila tidak ada yang membuka aplikasi.
                    $batasTerlewat = $permintaan->hold_expired_at;

                    $this->stok->release($permintaan);

                    $permintaan->update([
                        'status'          => 'kedaluwarsa',
                        'hold_expired_at' => null,
                    ]);

                    RiwayatPersetujuan::create([
                        'permintaan_id' => $permintaan->id,
                        'tahap'         => $tahap,
                        'pelaksana_id'  => $permintaan->pengaju_id,
                        'keputusan'     => 'tolak',
                        // Batas waktunya tidak diulang di dalam catatan, sebab
                        // kolom waktu pada baris ini sudah berisi saat itu.
                        'catatan'       => 'Tahapan tidak ditindaklanjuti hingga batas waktu. '
                            . 'Anda dapat mengajukan kembali kapan saja.',
                        'waktu'         => $batasTerlewat,
                    ]);

                    $diubah = true;
                });

                // Bukan permintaan yang diubah sapuan ini: tidak dilaporkan dan tidak
                // diberi tahu, supaya tidak ada notifikasi ganda.
                if (! $diubah) {
                    return null;
                }

                // Sesudah transaksi selesai. Kegagalan pemberitahuan tidak boleh
                // membatalkan sapuan maupun membuat permintaan panel gagal.
                try {
                    app(NotifikasiService::class)->permintaanBerubah($permintaan->refresh());
                } catch (\Throwable $e) {
                    report($e);
                }

                return ['kode' => $permintaan->kode_permintaan, 'berhasil' => true, 'pesan' => null];
            } catch (\Throwable $e) {
                return ['kode' => $permintaan->kode_permintaan, 'berhasil' => false, 'pesan' => $e->getMessage()];
            }
        })->filter()->values();
    }

    /** Menentukan tahap yang dicatat pada riwayat berdasarkan status terakhir. */
    public static function tahapTerakhir(string $status): string
    {
        return match ($status) {
            'menunggu_ketua'      => 'ketua_tim',
            'menunggu_verifikasi' => 'verifikasi',
            'menunggu_kasubbag'   => 'kasubbag',
            'siap_diproses'       => 'penyiapan',
            'siap_diambil'        => 'konfirmasi',
            default               => 'ketua_tim',
        };
    }
}
