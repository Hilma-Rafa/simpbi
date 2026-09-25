<?php

namespace App\Filament\Pages\Concerns;

use App\Filament\Support\GayaUnduh;
use App\Models\PermintaanBarang;
use App\Services\EksporRiwayatService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tombol ekspor pada halaman Riwayat.
 *
 * Dipisahkan dari kelas halaman agar definisi tabel tetap terbaca. Baris yang
 * diekspor diambil dari kueri tabel yang sudah tersaring, sehingga hasil unduhan
 * selalu sama dengan yang sedang dilihat pengguna. Ekspor keseluruhan menyusun
 * ulang seluruh jenis riwayat yang boleh dilihat peran tersebut, tanpa penyaring
 * jenis lain, karena penyaring hanya berlaku pada jenis yang sedang aktif.
 */
trait MengeksporRiwayat
{
    protected function getHeaderActions(): array
    {
        $banyakJenis = count($this->jenisTersedia) > 1;

        return [
            // Rupa tombol grup dari GayaUnduh, sama dengan tombol unduhan lain
            // (keputusan pemilik G-8). Isi berkasnya tetap dibentuk
            // EksporRiwayatService lewat ekspor() di bawah, tidak berubah.
            GayaUnduh::terapkan(ActionGroup::make(array_filter([
                Action::make('eksporPdf')
                    ->label('Ekspor PDF')
                    ->icon('heroicon-m-document-arrow-down')
                    ->action(fn () => $this->ekspor('pdf', false)),

                Action::make('eksporSpreadsheet')
                    ->label('Ekspor Excel')
                    ->icon('heroicon-m-table-cells')
                    ->action(fn () => $this->ekspor('xlsx', false)),

                $banyakJenis
                    ? Action::make('eksporSemuaPdf')
                        ->label('Ekspor Semua (PDF)')
                        ->icon('heroicon-m-document-duplicate')
                        ->requiresConfirmation()
                        ->modalHeading('Ekspor seluruh jenis riwayat')
                        ->modalDescription('Seluruh jenis riwayat yang boleh Anda lihat akan direkap tanpa penyaring. Lanjutkan?')
                        ->modalSubmitActionLabel('Ekspor PDF')
                        ->action(fn () => $this->ekspor('pdf', true))
                    : null,

                $banyakJenis
                    ? Action::make('eksporSemuaSpreadsheet')
                        ->label('Ekspor Semua (Excel)')
                        ->icon('heroicon-m-rectangle-stack')
                        ->requiresConfirmation()
                        ->modalHeading('Ekspor seluruh jenis riwayat')
                        ->modalDescription('Setiap jenis riwayat menjadi satu lembar tersendiri di dalam satu berkas. Lanjutkan?')
                        ->modalSubmitActionLabel('Ekspor Excel')
                        ->action(fn () => $this->ekspor('xlsx', true))
                    : null,
            ])))
                ->label('Ekspor'),
        ];
    }

    /**
     * Menerbitkan berkas unduhan.
     *
     * @param  'pdf'|'xlsx'  $format
     * @param  bool  $semua  true untuk merekap seluruh jenis riwayat
     */
    protected function ekspor(string $format, bool $semua)
    {
        $layanan = app(EksporRiwayatService::class);

        if ($semua) {
            $bagian = [];
            foreach ($this->jenisTersedia as $kunci => $jenis) {
                $bagian[] = $this->bagianEkspor($kunci, $this->kueriTanpaPenyaring($kunci), null);
            }

            $judul = 'Rekap Riwayat SIMPBI';
        } else {
            $aktif  = $this->jenisAktif();
            $bagian = [$this->bagianEkspor(
                $aktif,
                $this->getFilteredSortedTableQuery(),
                $this->keteranganPenyaring(),
            )];

            $judul = $this->jenisTersedia[$aktif]['label'];
        }

        return $format === 'pdf'
            ? $layanan->pdf($judul, $bagian)
            : $layanan->spreadsheet($judul, $bagian);
    }

    /** Kueri dasar suatu jenis tanpa penyaring, untuk ekspor keseluruhan. */
    protected function kueriTanpaPenyaring(string $jenis): Builder
    {
        return match ($jenis) {
            'mutasi_stok' => \App\Models\MutasiStok::query()
                ->with(['barang.kategori', 'petugas'])
                ->orderByDesc('tanggal'),

            'mutasi_aset' => \App\Filament\Resources\BastMutasiAsets\BastMutasiAsetResource::getEloquentQuery()
                ->whereNotNull('disahkan_at')
                ->orderByDesc('disahkan_at'),

            'notifikasi' => \App\Models\Notifikasi::query()
                ->with('user')
                ->orderByDesc('created_at'),

            default => \App\Filament\Resources\PermintaanBarangs\PermintaanBarangResource::getEloquentQuery()
                ->whereIn('status', PermintaanBarang::STATUS_RIWAYAT)
                ->withCount('detail')
                ->orderByDesc('created_at'),
        };
    }

    /**
     * Menyusun satu bagian ekspor: judul, judul kolom, dan barisnya.
     *
     * Baris dibaca dengan chunk agar rekap yang panjang tidak memuat seluruh
     * record ke memori sekaligus, dan relasi dimuat serentak (eager loading)
     * sehingga tidak menimbulkan kueri berulang per baris.
     */
    protected function bagianEkspor(string $jenis, Builder $kueri, ?string $keterangan): array
    {
        [$kolom, $petaBaris] = $this->petaKolom($jenis);

        $baris = [];
        $kueri->chunk(500, function ($kumpulan) use (&$baris, $petaBaris) {
            foreach ($kumpulan as $record) {
                $baris[] = $petaBaris($record);
            }
        });

        return EksporRiwayatService::bagian(
            $this->jenisTersedia[$jenis]['label'] ?? 'Riwayat',
            $kolom,
            $baris,
            $keterangan,
        );
    }

    /**
     * Judul kolom dan pemetaan satu record menjadi satu baris nilai.
     *
     * Kolomnya sengaja disamakan dengan kolom yang tampil pada tabel, agar
     * berkas unduhan mudah dicocokkan dengan tampilan di layar.
     *
     * @return array{0: array<int,string>, 1: callable(Model):array<int,mixed>}
     */
    protected function petaKolom(string $jenis): array
    {
        return match ($jenis) {
            'mutasi_stok' => [
                ['Tanggal', 'Barang', 'Kategori', 'Satuan', 'Jenis', 'Uraian', 'Jumlah', 'Saldo Sesudah', 'Nomor Dasar', 'Petugas', 'Keterangan'],
                fn (Model $r) => [
                    $r->tanggal?->format('d-m-Y'),
                    $r->barang?->nama_barang,
                    $r->barang?->kategori?->nama_kategori,
                    $r->barang?->satuan,
                    ucfirst($r->jenis),
                    $r->sumber ? ucwords(str_replace('_', ' ', $r->sumber)) : null,
                    (int) $r->jumlah,
                    (int) $r->saldo_sesudah,
                    $r->nomor_dasar,
                    $r->petugas?->name,
                    $r->keterangan,
                ],
            ],

            'notifikasi' => [
                ['Waktu', 'Penerima', 'Nomor', 'Judul', 'Pesan', 'Jenis', 'Kanal', 'Status Kirim', 'Dikirim', 'Keterangan Gagal'],
                fn (Model $r) => [
                    $r->created_at?->format('d-m-Y H:i'),
                    $r->user?->name,
                    $r->user?->no_hp,
                    $r->judul,
                    $r->pesan,
                    ucfirst($r->tipe),
                    $r->channel === 'whatsapp' ? 'WhatsApp' : 'Dalam Aplikasi',
                    match ($r->status_kirim) {
                        'terkirim' => 'Terkirim',
                        'gagal'    => 'Gagal',
                        'pending'  => 'Menunggu',
                        default    => null,
                    },
                    $r->dikirim_at?->format('d-m-Y H:i'),
                    $r->error_message,
                ],
            ],

            'mutasi_aset' => [
                ['Nomor BAST', 'Aset', 'NUP', 'Tim Asal', 'Tim Tujuan', 'Tanggal Mutasi', 'Disahkan'],
                fn (Model $r) => [
                    $r->nomor_bast,
                    $r->aset?->nama_aset,
                    $r->aset?->nup,
                    $r->timAsal?->nama_tim,
                    $r->timTujuan?->nama_tim,
                    $r->tanggal_mutasi?->format('d-m-Y'),
                    $r->disahkan_at?->format('d-m-Y H:i'),
                ],
            ],

            default => [
                ['Kode Permintaan', 'Tim Pemohon', 'Pemohon', 'NIP', 'Jumlah Item', 'Status', 'Keperluan', 'Diajukan', 'Disahkan'],
                fn (Model $r) => [
                    $r->kode_permintaan,
                    $r->tim?->nama_tim,
                    $r->nama_pemohon,
                    $r->nip_pemohon,
                    (int) ($r->detail_count ?? $r->detail()->count()),
                    PermintaanBarang::STATUS[$r->status] ?? $r->status,
                    $r->keterangan_keperluan,
                    $r->created_at?->format('d-m-Y H:i'),
                    $r->pengesahan_at?->format('d-m-Y H:i'),
                ],
            ],
        };
    }

    /** Rangkuman penyaring aktif, dicantumkan pada berkas unduhan. */
    protected function keteranganPenyaring(): ?string
    {
        $penanda = collect($this->getTable()->getFilters())
            ->flatMap(fn ($filter) => $filter->getIndicators())
            ->map(fn ($indicator) => is_string($indicator) ? $indicator : $indicator->getLabel())
            ->filter()
            ->values();

        $pencarian = filled($this->getTableSearch()) ? 'Pencarian: ' . $this->getTableSearch() : null;

        $bagian = $penanda->push($pencarian)->filter()->all();

        return $bagian === []
            ? 'Tanpa penyaring, seluruh data ditampilkan.'
            : 'Penyaring aktif — ' . implode(' · ', $bagian);
    }
}
