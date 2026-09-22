<?php

namespace App\Services;

use App\Jobs\KirimPesanWhatsApp;
use App\Models\BarangPersediaan;
use App\Models\BastMutasiAset;
use App\Models\Notifikasi;
use App\Models\PermintaanBarang;
use App\Models\User;
use App\Support\NomorWhatsApp;
use App\Support\PengalihanWhatsApp;
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

        // Dibaca sekali di luar perulangan: keadaannya tidak mungkin berubah
        // di tengah penerbitan satu kejadian, dan membacanya per penerima
        // berarti satu kueri tambahan untuk tiap orang.
        $dialihkan = PengalihanWhatsApp::menyala();

        foreach (collect($penerima)->filter()->unique('id') as $pengguna) {
            // Pengguna tanpa nomor tidak dibuatkan baris sama sekali, supaya
            // riwayat pengiriman tidak dipenuhi kegagalan yang sudah pasti.
            //
            // Kecuali ketika pengalihan menyala. Tujuan pengiriman saat itu
            // bukan nomor penggunanya, sehingga akun yang memang belum
            // bernomor pun tetap menghasilkan pesan. Tanpa pengecualian ini
            // peragaan tampak separuh rusak: tahap Kasubbag dan Petugas Gudang
            // tidak memunculkan pesan apa pun, bukan karena alurnya salah,
            // melainkan karena akun peragaannya belum diisi nomor.
            if (! $dialihkan && ! NomorWhatsApp::normalkan($pengguna->no_hp)) {
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
        $tim  = $permintaan->tim?->nama_tim ?? 'Tim Kerja';
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
     * Notifikasi atas perpindahan status BAST mutasi aset.
     *
     * Bentuknya sengaja disamakan dengan permintaanBerubah(): penerima
     * ditentukan dari status yang baru, yaitu pihak yang harus bertindak
     * berikutnya, bukan ditentukan oleh pemanggil. Dengan begitu tidak ada
     * peran yang menerima pemberitahuan atas tahapan yang bukan urusannya —
     * Ketua Tim tujuan, misalnya, tidak perlu tahu bahwa BAST baru dibuat,
     * sebab ia baru berkepentingan setelah dokumennya disahkan.
     */
    public function bastBerubah(BastMutasiAset $bast): int
    {
        $bast->loadMissing(['aset', 'timAsal', 'timTujuan']);

        $nomor  = $bast->nomor_bast;
        $aset   = $bast->aset?->nama_aset ?? 'Aset';
        $nup    = $bast->aset?->nup;
        $asal   = $bast->timAsal?->nama_tim ?? 'tim kerja asal';
        $tujuan = $bast->timTujuan?->nama_tim ?? 'tim kerja tujuan';

        [$penerima, $judul, $pesan] = match ($bast->status) {
            'menunggu_pengesahan' => [
                $this->berperan('kasubbag'),
                'BAST mutasi aset menunggu pengesahan',
                "{$nomor} untuk mutasi {$aset}" . ($nup ? " ({$nup})" : '')
                    . " dari {$asal} ke {$tujuan} menunggu pengesahan Anda.",
            ],

            'menunggu_konfirmasi' => [
                $this->berperan('ketua_tim', $bast->tim_tujuan_id),
                'Aset menunggu konfirmasi penerimaan',
                "{$nomor} telah disahkan. {$aset}" . ($nup ? " ({$nup})" : '')
                    . " dipindahkan ke {$tujuan} dan menunggu konfirmasi penerimaan.",
            ],

            'selesai_administratif' => [
                // Petugas Gudang membuat BAST-nya dan Kasubbag mengesahkannya,
                // sehingga keduanya perlu tahu bahwa mutasinya sudah tuntas.
                $this->berperan(['kasubbag', 'petugas_gudang']),
                'Mutasi aset selesai',
                "{$nomor} telah dikonfirmasi diterima oleh {$tujuan}. "
                    . 'Penempatan aset sudah diperbarui.',
            ],

            default => [collect(), '', ''],
        };

        if ($judul === '') {
            return 0;
        }

        return $this->kirim($penerima, $judul, $pesan, 'mutasi', 'bast_mutasi_aset', $bast->id);
    }
}
