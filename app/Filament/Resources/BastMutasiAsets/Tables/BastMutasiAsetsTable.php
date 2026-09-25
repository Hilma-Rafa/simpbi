<?php

namespace App\Filament\Resources\BastMutasiAsets\Tables;

use App\Filament\Support\GayaUnduh;
use App\Filament\Support\KeadaanKosong;
use App\Models\BastMutasiAset;
use App\Services\MutasiAsetService;
use App\Services\NotifikasiService;
use App\Services\StokService;
use App\Support\TandaTangan;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BastMutasiAsetsTable
{
    protected const STATUS_LABEL = [
        'menunggu_pengesahan'   => 'Menunggu Pengesahan',
        'menunggu_konfirmasi'   => 'Menunggu Konfirmasi',
        'selesai_administratif' => 'Selesai Administratif',
    ];

    protected const STATUS_COLOR = [
        'menunggu_pengesahan'   => 'warning',
        'menunggu_konfirmasi'   => 'info',
        'selesai_administratif' => 'success',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            /**
             * Kalimat keadaan kosong dibedakan: daftar yang memang belum berisi
             * memerlukan ajakan mengisi, sedangkan pencarian yang tidak
             * menemukan apa pun memerlukan jalan keluar dari penyaringnya.
             */
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Tidak ada BAST yang cocok'
                : 'Belum ada mutasi aset')
            ->emptyStateDescription(fn ($livewire): string => KeadaanKosong::sedangDisaring($livewire)
                ? 'Coba longgarkan penyaringnya, atau periksa kembali nomor BAST yang dicari.'
                : 'BAST terbit ketika aset tetap dipindahkan antar tim kerja. Buat form BAST untuk mencatat perpindahan pertama.')
            ->columns([
                TextColumn::make('nomor_bast')
                    ->label('Nomor BAST')
                    ->searchable()
                    ->weight('semibold'),
                TextColumn::make('aset.nama_aset')
                    ->label('Aset')
                    ->description(fn (BastMutasiAset $r): string => 'NUP ' . ($r->aset?->nup ?? '-'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('timAsal.nama_tim')
                    ->label('Asal'),
                TextColumn::make('timTujuan.nama_tim')
                    ->label('Tujuan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABEL[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLOR[$state] ?? 'gray'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d-m-Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS_LABEL),
            ])
            ->recordActions([
                // UC-17 — Pengesahan oleh Kasubbag Umum.
                //
                // Rincian BAST wajib terbaca dulu (modal ini sendiri, lewat
                // schema()) sebelum tombol "Sahkan" di kakinya dapat ditekan —
                // dialog itu sendiri sudah menjadi bentuk konfirmasi, sehingga
                // tidak ada requiresConfirmation() kedua dan tidak ada kolom
                // isian apa pun. Gaya tombol disamakan persis dengan tombol
                // tahapan Permintaan Barang (PermintaanBarangResource::aksiSahkan()):
                // ikon, warna, dan ->button() yang sama.
                Action::make('sahkan')
                    ->label('Sahkan')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->modalHeading('Sahkan BAST Mutasi Aset')
                    ->modalDescription('Dokumen akan difinalisasi dengan e-TTD Anda sebagai Kasubbag Umum.')
                    ->modalSubmitActionLabel('Sahkan')
                    ->modalCancelActionLabel('Batal')
                    ->modalWidth(Width::Medium)
                    ->visible(fn (BastMutasiAset $r): bool => $r->status === 'menunggu_pengesahan' && auth()->user()?->role === 'kasubbag')
                    ->schema(fn (BastMutasiAset $r) => [
                        View::make('filament.partials.detail-bast')
                            ->viewData(['record' => $r->load(['aset', 'timAsal', 'timTujuan', 'dibuatOleh', 'disahkanOleh', 'dikonfirmasiOleh'])]),
                    ])
                    ->action(function (BastMutasiAset $r): void {
                        try {
                            app(MutasiAsetService::class)->sahkan($r, auth()->id());
                        } catch (\RuntimeException $e) {
                            // Penempatan aset berubah sejak BAST dibuat: ditolak tanpa
                            // perubahan apa pun. Galat teknis dilempar ulang.
                            $pesan = StokService::pesanAturan($e);

                            if ($pesan === null) {
                                throw $e;
                            }

                            Notification::make()
                                ->title('BAST tidak dapat disahkan')
                                ->body($pesan)
                                ->danger()
                                ->send();

                            return;
                        }

                        // Ketua Tim tujuan kini berkepentingan: aset sudah
                        // berpindah dan menunggu konfirmasi penerimaannya.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('BAST disahkan')->success()->send();
                    }),

                // UC-18 — Konfirmasi penerimaan oleh Ketua Tim tujuan.
                //
                // Penerima cukup mengkonfirmasi; ia tidak menggambar tanda
                // tangan. Tanda tangan yang dibubuhkan pada BAST adalah yang
                // sudah terdaftar di akunnya, dipasang saat dokumen dibentuk
                // ulang oleh MutasiAsetService::konfirmasi() — di sinilah aset
                // benar-benar berpindah pada alur yang dibalik.
                //
                // Sama seperti Sahkan: rincian BAST wajib terbaca dulu di dalam
                // modal ini, tombol "Konfirmasi Penerimaan" di kakinya langsung
                // menuntaskan aksi, tanpa kolom isian apa pun. Gaya tombol
                // disamakan persis dengan Konfirmasi Penerimaan pada Permintaan
                // Barang (PermintaanBarangResource::aksiKonfirmasi()): ikon,
                // warna 'success', dan ->button() yang sama.
                Action::make('konfirmasi')
                    ->label('Konfirmasi Penerimaan')
                    ->icon('heroicon-m-hand-thumb-up')
                    ->color('success')
                    ->button()
                    ->modalHeading('Konfirmasi Penerimaan BAST')
                    ->modalDescription('Penempatan aset akan dipindahkan ke tim kerja Anda dan tanda tangan Anda yang tersimpan akan dibubuhkan pada BAST sebagai pihak penerima.')
                    ->modalSubmitActionLabel('Konfirmasi Penerimaan')
                    ->modalCancelActionLabel('Batal')
                    ->modalWidth(Width::Medium)
                    ->visible(fn (BastMutasiAset $r): bool => $r->status === 'menunggu_konfirmasi'
                        && auth()->user()?->role === 'ketua_tim'
                        && auth()->user()?->tim_id === $r->tim_tujuan_id)
                    ->schema(fn (BastMutasiAset $r) => [
                        View::make('filament.partials.detail-bast')
                            ->viewData(['record' => $r->load(['aset', 'timAsal', 'timTujuan', 'dibuatOleh', 'disahkanOleh', 'dikonfirmasiOleh'])]),
                    ])
                    ->action(function (BastMutasiAset $r): void {
                        // Lapis pertahanan terakhir: onboarding sudah menuntut
                        // Ketua Tim mendaftarkan tanda tangan, tetapi bila entah
                        // bagaimana belum ada, konfirmasi dihentikan daripada
                        // menerbitkan BAST dengan ruang tanda tangan penerima
                        // yang kosong.
                        if (! TandaTangan::terdaftar(auth()->user())) {
                            Notification::make()
                                ->title('Tanda tangan belum tersedia')
                                ->body('Lengkapi tanda tangan Anda melalui pelengkapan akun sebelum mengkonfirmasi penerimaan aset.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        try {
                            app(MutasiAsetService::class)->konfirmasi($r, auth()->id());
                        } catch (\RuntimeException $e) {
                            // Status berubah sejak halaman dibuka: ditolak tanpa perubahan
                            // apa pun. Galat teknis dilempar ulang.
                            $pesan = StokService::pesanAturan($e);

                            if ($pesan === null) {
                                throw $e;
                            }

                            Notification::make()
                                ->title('Penerimaan tidak dapat dikonfirmasi')
                                ->body($pesan)
                                ->danger()
                                ->send();

                            return;
                        }

                        // Pembuat BAST dan pengesahnya diberi tahu bahwa
                        // mutasinya sudah tuntas secara administratif.
                        app(NotifikasiService::class)->bastBerubah($r->refresh());

                        Notification::make()->title('Penerimaan aset dikonfirmasi')->success()->send();
                    }),

                // Unduh dokumen BAST (tersedia setelah disahkan).
                //
                // Gaya diambil dari GayaUnduh, definisi yang sama dengan Unduh
                // Bukti pada permintaan barang, sebab keduanya sama-sama tombol
                // pengunduhan dokumen yang sudah disahkan. Sebelumnya nilainya
                // disalin di sini satu per satu.
                GayaUnduh::terapkan(Action::make('unduh'))
                    ->label('Unduh BAST')
                    ->tooltip('Unduh dokumen BAST yang telah disahkan')
                    ->url(fn (BastMutasiAset $r): ?string => $r->file_bast_path ? route('bast.unduh', $r) : null)
                    ->openUrlInNewTab()
                    ->visible(fn (BastMutasiAset $r): bool => filled($r->file_bast_path) && filled($r->disahkan_at)),
            ]);
    }
}
