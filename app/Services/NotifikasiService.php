<?php

namespace App\Services;

use App\Jobs\KirimPesanWhatsApp;
use App\Models\BarangPersediaan;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\NomorWhatsApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penerbitan notifikasi dalam aplikasi.
 *
 * Menulis ke tabel `notifikasi` yang sudah ada. Penerima ditentukan dari peran
 * dan tim yang benar-benar berkepentingan atas kejadian, sehingga tidak ada
 * pengguna yang menerima pemberitahuan di luar kewenangannya — misalnya Tim
 * tidak menerima permintaan tim lain, dan Admin Sistem tidak menerima antrian
 * persetujuan karena tidak menjalankan alur operasional (Instruksi §39).
 *
 * Seluruh baris ditulis dengan satu perintah insert agar tidak menimbulkan
 * kueri berulang sebanyak jumlah penerima.
 */
class NotifikasiService
{
    /**
     * Menerbitkan notifikasi untuk sekumpulan pengguna.
     *
     * @param  Collection<int,User>|array<int,User>  $penerima
     */
    public function kirim(
        iterable $penerima,
        string $judul,
        string $pesan,
        string $tipe = 'permintaan',
        ?string $referensiTabel = null,
        ?int $referensiId = null,
    ): int {
        $waktu = now();

        $baris = collect($penerima)
            ->filter()
            ->unique('id')
            ->map(fn (User $u) => [
                'user_id'         => $u->id,
                'judul'           => $judul,
                'pesan'           => $pesan,
                'tipe'            => $tipe,
                'channel'         => 'in_app',
                'status_kirim'    => null,
                'dikirim_at'      => $waktu,
                'referensi_tabel' => $referensiTabel,
                'referensi_id'    => $referensiId,
                'dibaca_at'       => null,
                'created_at'      => $waktu,
                'updated_at'      => $waktu,
            ])
            ->values()
            ->all();

        if ($baris === []) {
            return 0;
        }

        Notifikasi::insert($baris);

        $this->terbitkanWhatsApp($penerima, $judul, $pesan, $tipe, $referensiTabel, $referensiId);

        // Yang dikembalikan tetap banyaknya penerima, bukan banyaknya baris,
        // sebab pemanggil memakai angka ini untuk melaporkan jumlah orang yang
        // diberi tahu — bukan jumlah pesan yang diterbitkan per kanal.
        return count($baris);
    }

    /**
     * Menerbitkan baris berkanal WhatsApp beserta antreannya.
     *
     * Kanal ini bersifat pelengkap: notifikasi dalam aplikasi tetap terbit
     * lebih dulu dan tidak bergantung pada keberhasilan WhatsApp, sehingga
     * gangguan atau pemblokiran pada gerbang WhatsApp tidak pernah menghentikan
     * alur kerja. Baris diterbitkan berstatus `pending` lalu dikirim lewat
     * antrean, agar permintaan HTTP yang sedang berjalan tidak perlu menunggu
     * gerbang menjawab.
     *
     * @param  Collection<int,User>|array<int,User>  $penerima
     */
    protected function terbitkanWhatsApp(
        iterable $penerima,
        string $judul,
        string $pesan,
        string $tipe,
        ?string $referensiTabel,
        ?int $referensiId,
    ): void {
        if (! $this->whatsappAktif()) {
            return;
        }

        $urutan = 0;

        foreach (collect($penerima)->filter()->unique('id') as $pengguna) {
            // Pengguna tanpa nomor tidak dibuatkan baris sama sekali, supaya
            // riwayat pengiriman tidak dipenuhi kegagalan yang sudah pasti.
            if (! NomorWhatsApp::normalkan($pengguna->no_hp)) {
                continue;
            }

            $notifikasi = Notifikasi::create([
                'user_id'         => $pengguna->id,
                'judul'           => $judul,
                'pesan'           => $pesan,
                'tipe'            => $tipe,
                'channel'         => 'whatsapp',
                'status_kirim'    => 'pending',
                'referensi_tabel' => $referensiTabel,
                'referensi_id'    => $referensiId,
            ]);

            // Pesan diberi jarak agar gerbang tidak melihat kiriman serentak
            // ke banyak nomor, yang merupakan pola khas pengiriman massal.
            KirimPesanWhatsApp::dispatch($notifikasi->id)
                ->delay(now()->addSeconds($urutan++ * (int) config('whatsapp.jeda_detik', 5)))
                ->afterCommit();
        }
    }

    /**
     * Apakah kanal WhatsApp sedang dinyalakan.
     *
     * Dibaca dari tabel `pengaturan`, bukan dari berkas konfigurasi, karena
     * penyalaannya merupakan kebijakan Admin lewat halaman Pengaturan dan harus
     * dapat diubah tanpa menyentuh kode maupun memasang ulang sistem.
     */
    protected function whatsappAktif(): bool
    {
        return DB::table('pengaturan')->where('kunci', 'wa_aktif')->value('nilai') === '1';
    }

    // =====================================================================
    // PENERIMA MENURUT PERAN
    // =====================================================================

    /** @return Collection<int,User> */
    protected function berperan(string|array $role, ?int $timId = null): Collection
    {
        return User::query()
            ->where('status_aktif', true)
            ->whereIn('role', (array) $role)
            ->when($timId, fn ($q) => $q->where('tim_id', $timId))
            ->get();
    }

    // =====================================================================
    // KEJADIAN ALUR PERMINTAAN BARANG
    // =====================================================================

    /**
     * Notifikasi atas perpindahan status permintaan.
     *
     * Penerima ditentukan dari status yang baru: pihak yang harus bertindak
     * berikutnya, ditambah pemohon ketika permintaannya berubah nasib.
     */
    public function permintaanBerubah(PermintaanBarang $permintaan, ?string $catatan = null): int
    {
        $tim  = $permintaan->tim?->nama_tim ?? 'Tim';
        $kode = $permintaan->kode_permintaan;

        [$penerima, $judul, $pesan] = match ($permintaan->status) {
            'menunggu_ketua' => [
                $this->berperan('ketua_tim', $permintaan->tim_pemohon_id),
                'Permintaan menunggu persetujuan Anda',
                "{$kode} dari {$permintaan->nama_pemohon} menunggu persetujuan Ketua Tim.",
            ],

            'menunggu_verifikasi' => [
                $this->berperan('petugas_gudang'),
                'Permintaan perlu verifikasi ketersediaan',
                "{$kode} dari {$tim} perlu diperiksa ketersediaan fisiknya.",
            ],

            'menunggu_kasubbag' => [
                $this->berperan('kasubbag'),
                'Permintaan menunggu persetujuan akhir',
                "{$kode} dari {$tim} telah diverifikasi gudang dan menunggu persetujuan Anda.",
            ],

            'siap_diproses' => [
                $this->berperan('petugas_gudang'),
                'Permintaan siap disiapkan',
                "{$kode} dari {$tim} telah disetujui dan barangnya dapat disiapkan.",
            ],

            'siap_diambil' => [
                $this->anggotaTim($permintaan),
                'Barang siap diambil',
                "Barang untuk {$kode} sudah dapat diambil di gudang.",
            ],

            'menunggu_pengesahan' => [
                $this->berperan('kasubbag'),
                'Permintaan menunggu pengesahan',
                "{$kode} dari {$tim} telah diterima pemohon dan menunggu pengesahan akhir.",
            ],

            'selesai' => [
                $this->anggotaTim($permintaan),
                'Permintaan selesai',
                "{$kode} telah disahkan. Dokumen bukti permintaan dapat diunduh.",
            ],

            'ditolak_ketua', 'ditolak_kasubbag' => [
                $this->anggotaTim($permintaan),
                'Permintaan ditolak',
                "{$kode} ditolak" . ($catatan ? ". Alasan: {$catatan}" : '.') . ' Stok yang dikunci telah dilepaskan.',
            ],

            'bermasalah' => [
                $this->berperan(['kasubbag', 'petugas_gudang'])
                    ->merge($this->anggotaTim($permintaan)),
                'Permintaan bermasalah',
                "{$kode} dari {$tim} ditandai bermasalah dan memerlukan tindak lanjut.",
            ],

            'kedaluwarsa' => [
                $this->anggotaTim($permintaan),
                'Permintaan kedaluwarsa',
                "{$kode} melewati batas waktu dan stok yang dikunci telah dilepaskan.",
            ],

            default => [collect(), '', ''],
        };

        if ($judul === '') {
            return 0;
        }

        return $this->kirim(
            $penerima,
            $judul,
            $pesan,
            'permintaan',
            'permintaan_barang',
            $permintaan->id,
        );
    }

    /** Anggota tim pemohon, yaitu akun Tim dan Ketua Tim pada tim tersebut. */
    protected function anggotaTim(PermintaanBarang $permintaan): Collection
    {
        return $this->berperan(['tim', 'ketua_tim'], $permintaan->tim_pemohon_id);
    }

    // =====================================================================
    // KEJADIAN PERSEDIAAN
    // =====================================================================

    /**
     * Peringatan stok menipis atau habis setelah suatu barang berkurang.
     *
     * Ditujukan kepada Petugas Gudang dan Kasubbag Umum, yaitu pihak yang
     * berwenang atas pengadaan dan pengendalian persediaan. Notifikasi hanya
     * diterbitkan ketika barang benar-benar melewati ambangnya, bukan pada
     * setiap perubahan stok.
     */
    public function stokMenipis(BarangPersediaan $barang): int
    {
        $tersedia = $barang->stok_fisik - $barang->stok_hold;

        if ($tersedia > 0 && ! ($barang->stok_minimum > 0 && $tersedia <= $barang->stok_minimum)) {
            return 0;
        }

        [$judul, $pesan] = $tersedia <= 0
            ? [
                'Stok habis',
                "{$barang->nama_barang} tidak lagi tersedia untuk diminta.",
            ]
            : [
                'Stok menipis',
                "{$barang->nama_barang} tersisa {$tersedia} {$barang->satuan}, "
                    . "sudah mencapai batas minimum {$barang->stok_minimum} {$barang->satuan}.",
            ];

        return $this->kirim(
            $this->berperan(['petugas_gudang', 'kasubbag']),
            $judul,
            $pesan,
            'stok',
            'barang_persediaan',
            $barang->id,
        );
    }

    // =====================================================================
    // KEJADIAN MUTASI ASET
    // =====================================================================

    /**
     * Notifikasi mutasi aset kepada Ketua Tim tujuan dan Kasubbag Umum,
     * mengikuti cakupan BastMutasiAsetResource yang sudah berlaku.
     */
    public function mutasiAset(string $judul, string $pesan, ?int $timTujuanId, ?int $bastId = null): int
    {
        $penerima = $this->berperan('kasubbag')
            ->merge($timTujuanId ? $this->berperan('ketua_tim', $timTujuanId) : collect());

        return $this->kirim($penerima, $judul, $pesan, 'mutasi', 'bast_mutasi_aset', $bastId);
    }
}
