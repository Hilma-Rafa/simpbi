<?php

namespace App\Services;

use App\Models\AsetTetap;
use App\Models\BastMutasiAset;
use App\Models\RiwayatPenempatanAset;
use App\Models\Tim;
use App\Support\TandaTangan;
use Illuminate\Support\Facades\DB;

/**
 * Transisi status BAST mutasi aset (Instruksi §18). Tidak ada jalur approval
 * atau penolakan; hanya konfirmasi penerimaan oleh Ketua Tim tujuan (aset
 * berpindah di sini) lalu pengesahan Kasubbag (finalisasi dokumen, langkah
 * terakhir).
 */
class MutasiAsetService
{
    public function __construct(protected DokumenBastService $dokumen)
    {
    }

    /** Nomor BAST otomatis, urut per tahun berjalan. */
    public function nomorBaru(): string
    {
        $tahun = now()->year;
        $awalan = "BAST-{$tahun}-";

        // Dari nomor terbesar yang ada pada tahun itu, bukan dari jumlah baris:
        // celah akibat baris yang hilang tidak boleh membuat nomor bentrok.
        $terbesar = BastMutasiAset::query()
            ->where('nomor_bast', 'like', $awalan . '%')
            ->pluck('nomor_bast')
            ->map(fn (string $nomor): int => (int) substr($nomor, strlen($awalan)))
            ->max() ?? 0;

        return sprintf('BAST-%d-%04d', $tahun, $terbesar + 1);
    }

    /**
     * Alasan sebuah aset belum dapat dimutasi, atau null bila boleh (A-011):
     * aset tanpa penempatan tidak dapat dimutasi, dan aset yang masih punya BAST
     * menunggu_konfirmasi diblokir — itulah status pertama/aktif pada alur yang
     * dibalik, tercapai sejak BAST dibuat dan sebelum Ketua Tim tujuan
     * mengkonfirmasi penerimaannya (aset belum pindah). BAST menunggu_pengesahan
     * dan selesai_administratif tidak memblokir (aset sudah pindah ke tim tujuan
     * sejak konfirmasi), begitu pula BAST usang yang tim_asal_id-nya tak lagi
     * sama dengan penempatan aset sekarang, sebab alur ini tidak punya jalur
     * pembatalan atau penolakan.
     */
    public function pesanAsetTidakDapatDimutasi(AsetTetap $aset): ?string
    {
        if (blank($aset->tim_penempatan_id)) {
            return 'Aset ini belum memiliki penempatan. Tetapkan penempatan terlebih dahulu lewat menu Aset Tetap.';
        }

        $menunggu = BastMutasiAset::query()
            ->where('aset_id', $aset->id)
            ->where('status', 'menunggu_konfirmasi')
            ->where('tim_asal_id', $aset->tim_penempatan_id)
            ->orderBy('id')
            ->pluck('nomor_bast');

        return $menunggu->isEmpty()
            ? null
            : "Aset ini masih memiliki BAST yang menunggu konfirmasi penerimaan ({$menunggu->implode(', ')}). Konfirmasikan penerimaan BAST tersebut terlebih dahulu.";
    }

    /**
     * Pesan penjagaan tanda tangan (E): Ketua Tim Tim Asal dan Ketua Tim Tim
     * Tujuan sama-sama wajib sudah punya tanda tangan tersimpan sebelum BAST
     * dapat dibuat, sebab dokumen BAST menyematkan tanda tangan keduanya
     * (lihat DokumenBastService::buat()). Sumber "siapa Ketua Tim suatu tim"
     * memakai relasi yang sama dengan yang sudah diselaraskan A-012
     * (Tim::ketuaTim(), bukan User::where('role','ketua_tim')), supaya
     * konsisten dengan sumber yang dipakai merender tanda tangan pada dokumen.
     */
    public function pesanTandaTanganBelumLengkap(?Tim $timAsal, ?Tim $timTujuan): ?string
    {
        $belum = collect([$timAsal, $timTujuan])
            ->filter()
            ->unique('id')
            ->reject(fn (Tim $tim): bool => TandaTangan::terdaftar($tim->ketuaTim))
            ->pluck('nama_tim');

        return $belum->isEmpty()
            ? null
            : 'Ketua Tim pada ' . $belum->implode(', ') . ' belum membubuhkan tanda tangan. Lengkapi tanda tangan lewat Pengaturan akun terlebih dahulu.';
    }

    /**
     * Pemeriksaan di server sebelum BAST dibuat (A-011, E); muatan form tidak
     * dipercaya. Mengunci baris aset, sehingga harus dipanggil di dalam transaksi
     * yang sama dengan pembuatan BAST-nya. (SQLite tidak mengenal kunci baris;
     * penulisnya sudah diserialkan oleh basis data itu sendiri.)
     *
     * @throws \RuntimeException dengan pesan yang layak ditampilkan
     */
    public function periksaPembuatan(int $asetId, ?int $timAsalId, ?int $timTujuanId): void
    {
        $aset = AsetTetap::query()->lockForUpdate()->find($asetId);

        if (! $aset) {
            throw new \RuntimeException('Aset tidak ditemukan.');
        }

        // Pilihan pada form sudah menyaring aset nonaktif; ini penjaga di server
        // bagi muatan yang dimodifikasi (F-006).
        if (! $aset->status_aktif) {
            throw new \RuntimeException('Aset ini tidak aktif dan tidak dapat dimutasi.');
        }

        if (blank($aset->tim_penempatan_id)) {
            throw new \RuntimeException($this->pesanAsetTidakDapatDimutasi($aset));
        }

        // Tim asal selalu penempatan aset saat ini; yang lain berarti muatan
        // dimodifikasi atau formulir usang karena aset sudah pindah.
        if ((int) $timAsalId !== (int) $aset->tim_penempatan_id) {
            throw new \RuntimeException('Penempatan aset telah berubah. Muat ulang formulir dan coba lagi.');
        }

        if ($pesan = $this->pesanAsetTidakDapatDimutasi($aset)) {
            throw new \RuntimeException($pesan);
        }

        // Jaring pengaman (E): form sudah membekukan tombol Buat pada keadaan
        // ini (UX); pemeriksaan di sini adalah jaring pengaman bagi muatan yang
        // dimodifikasi di luar form.
        $timTujuan = $timTujuanId ? Tim::query()->find($timTujuanId) : null;

        if ($pesan = $this->pesanTandaTanganBelumLengkap($aset->timPenempatan, $timTujuan)) {
            throw new \RuntimeException($pesan);
        }
    }

    /**
     * Mengunci baris BAST lalu memastikan statusnya masih yang diharapkan
     * tahapan ini (F-006). Dipanggil di dalam transaksi tahapan, sebelum ada
     * yang ditulis. Model yang dipegang pemanggil bisa usang (panggilan ganda,
     * atau halaman yang dibuka sebelum orang lain memprosesnya), sehingga
     * statusnya dibaca dari baris terkunci, bukan dari model itu. Kunci baris
     * hanya berlaku pada MySQL; SQLite tidak mengenalnya, tetapi penulisnya
     * sudah diserialkan basis datanya.
     *
     * @throws \RuntimeException bila status sudah berubah
     */
    protected function kunciDanPastikanStatus(BastMutasiAset $bast, string $statusDiharapkan): void
    {
        $terkini = BastMutasiAset::query()->lockForUpdate()->find($bast->getKey());

        if (! $terkini || $terkini->status !== $statusDiharapkan) {
            throw new \RuntimeException('BAST ini sudah diproses atau statusnya telah berubah. Muat ulang halaman.');
        }
    }

    /**
     * Pengesahan BAST oleh Kasubbag (UC-17) — langkah TERAKHIR pada alur yang
     * dibalik: murni finalisasi dokumen, membubuhkan e-TTD Kasubbag (nama + QR
     * + waktu). Aset sudah berpindah lebih dulu pada langkah Konfirmasi
     * Penerimaan (di bawah); method ini tidak lagi menyentuh penempatan aset
     * atau riwayat penempatan.
     */
    public function sahkan(BastMutasiAset $bast, int $kasubbagId): void
    {
        DB::transaction(function () use ($bast, $kasubbagId) {
            $this->kunciDanPastikanStatus($bast, 'menunggu_pengesahan');

            $bast->update([
                'disahkan_oleh_id' => $kasubbagId,
                'disahkan_at'      => now(),
                'status'           => 'selesai_administratif',
            ]);

            // Bentuk ulang dokumen: di sinilah e-TTD + QR verifikasi Kasubbag
            // akhirnya terbubuh, sebagai finalisasi dokumen.
            $path = $this->dokumen->buat($bast->fresh(['aset', 'timAsal', 'timTujuan', 'disahkanOleh', 'dikonfirmasiOleh']));
            $bast->update(['file_bast_path' => $path]);
        });
    }

    /**
     * Konfirmasi penerimaan aset oleh Ketua Tim tujuan (UC-18) — langkah
     * PERTAMA pada alur yang dibalik, dijalankan segera setelah BAST dibuat.
     * Di sinilah aset benar-benar berpindah: penempatan aset diperiksa ulang
     * lebih dulu, dengan baris aset terkunci, sebelum ada yang ditulis — bila
     * aset sudah berpindah sejak BAST dibuat, BAST ini ditolak tanpa
     * perubahan apa pun (A-011, sebelumnya diperiksa di sahkan() sebelum
     * urutan dibalik).
     *
     * Dokumen dibentuk ulang sesudahnya agar tanda tangan tersimpan pihak
     * penerima — Ketua Tim tujuan yang baru saja mengkonfirmasi — ikut
     * terbubuh pada ruang tanda tangan yang memang disediakan BAST. Pihak
     * penerima tidak menggambar apa pun; ia hanya mengkonfirmasi, dan tanda
     * tangan yang dipakai adalah yang sudah terdaftar di akunnya.
     *
     * @throws \RuntimeException bila penempatan aset sudah berubah
     */
    public function konfirmasi(BastMutasiAset $bast, int $ketuaId): void
    {
        DB::transaction(function () use ($bast, $ketuaId) {
            $this->kunciDanPastikanStatus($bast, 'menunggu_konfirmasi');

            $aset = AsetTetap::query()->lockForUpdate()->findOrFail($bast->aset_id);

            if ((int) $aset->tim_penempatan_id !== (int) $bast->tim_asal_id) {
                throw new \RuntimeException('Penempatan aset sudah berubah sejak BAST dibuat, sehingga penerimaan ini tidak dapat dikonfirmasi. Buat BAST baru bila mutasi masih diperlukan.');
            }

            $bast->update([
                'dikonfirmasi_oleh_id' => $ketuaId,
                'dikonfirmasi_at'      => now(),
                'status'               => 'menunggu_pengesahan',
            ]);

            // Pindahkan penempatan aset ke tim tujuan.
            $aset->update(['tim_penempatan_id' => $bast->tim_tujuan_id]);

            // Tutup baris penempatan yang masih berjalan.
            RiwayatPenempatanAset::query()
                ->where('aset_id', $bast->aset_id)
                ->whereNull('tanggal_selesai')
                ->update(['tanggal_selesai' => now()->toDateString()]);

            // Buka baris penempatan baru pada tim tujuan.
            RiwayatPenempatanAset::create([
                'aset_id'       => $bast->aset_id,
                'tim_id'        => $bast->tim_tujuan_id,
                'tanggal_mulai' => now()->toDateString(),
                'jenis'         => 'mutasi',
                'bast_id'       => $bast->id,
            ]);

            $path = $this->dokumen->buat($bast->fresh([
                'aset', 'timAsal', 'timTujuan', 'disahkanOleh', 'dikonfirmasiOleh',
            ]));

            $bast->update(['file_bast_path' => $path]);
        });
    }
}
