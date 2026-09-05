<?php

namespace App\Filament\Pages;

use App\Models\BarangPersediaan;
use App\Models\PermintaanBarang;
use App\Models\RiwayatPersetujuan;
use App\Services\StokService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class KatalogBarang extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.katalog-barang';

    protected static ?string $navigationLabel = 'Katalog Barang';
    protected static ?string $title = 'Katalog Barang';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['tim', 'ketua_tim']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BarangPersediaan::query()->with('kategori')->where('status_aktif', true))
            ->defaultSort('nama_barang')
            ->columns([
                TextColumn::make('nama_barang')
                    ->label('Nama Barang')
                    ->description(fn ($record) => $record->kode_barang)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kategori.nama_kategori')
                    ->label('Kategori')
                    ->sortable(),

                TextColumn::make('satuan')->label('Satuan'),

                TextColumn::make('stok_tersedia')
                    ->label('Stok Tersedia')
                    ->state(fn ($record) => $record->stok_fisik - $record->stok_hold)
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : 'Habis'),
            ])
            ->filters([
                SelectFilter::make('kategori_id')
                    ->label('Kategori')
                    ->relationship('kategori', 'nama_kategori'),
            ])
            ->recordActions([
                Action::make('tambah')
                    ->label('Tambah')
                    ->icon('heroicon-m-plus')
                    ->button()
                    ->color('primary')
                    ->visible(fn ($record) => ($record->stok_fisik - $record->stok_hold) > 0)
                    ->schema([
                        TextInput::make('jumlah')
                            ->label('Jumlah Diminta')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->helperText(fn ($record) => 'Stok tersedia: '
                                . ($record->stok_fisik - $record->stok_hold) . ' ' . $record->satuan),
                    ])
                    ->action(fn (array $data, $record) => $this->tambahKeKeranjang($record, (int) $data['jumlah'])),
            ])
            ->paginated([10, 25, 50]);
    }

    /** Tombol pengajuan pada bagian keranjang */
    public function ajukanAction(): Action
    {
        return Action::make('ajukan')
            ->label('Detail Keranjang')
            ->icon('heroicon-m-shopping-cart')
            ->tooltip('Isi detail permintaan')
            ->modalHeading('Lihat Keranjang dan Ajukan Permintaan')
            ->modalDescription('Periksa kembali jumlah barang sebelum diajukan. Setelah diajukan, jumlah barang akan dikunci sementara hingga permintaan disetujui atau ditolak.')
            ->modalSubmitActionLabel('Ajukan Permintaan')
            ->modalCancelActionLabel('Batal')
            ->modalWidth('3xl')
            ->fillForm(fn () => [
                'nama_pemohon' => auth()->user()->name,
                'nip_pemohon'  => auth()->user()->nip,
                'items'        => collect($this->keranjang)
                    ->map(fn ($item, $id) => [
                        'barang_id' => $id,
                        'nama'      => $item['nama'] . ' (' . $item['satuan'] . ')',
                        'jumlah'    => $item['jumlah'],
                    ])
                    ->values()
                    ->toArray(),
            ])
            ->schema([
                \Filament\Forms\Components\Repeater::make('items')
                    ->label('Barang yang Diminta')
                    ->schema([
                        \Filament\Forms\Components\Hidden::make('barang_id'),

                        \Filament\Forms\Components\Placeholder::make('nama')
                            ->label('Nama Barang')
                            ->columnSpan(2),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(3)
                    ->addable(false)
                    ->reorderable(false)
                    ->deleteAction(fn ($action) => $action->label('Hapus')),

                TextInput::make('nama_pemohon')
                    ->label('Nama Pemohon')
                    ->required(),

                TextInput::make('nip_pemohon')
                    ->label('NIP Pemohon'),

                Textarea::make('keperluan')
                    ->label('Keperluan')
                    ->rows(2),
            ])
            ->action(fn (array $data) => $this->ajukan($data));
    }

    public function getKeranjangProperty(): array
    {
        return Session::get('keranjang', []);
    }

    protected function tambahKeKeranjang(BarangPersediaan $barang, int $jumlah): void
    {
        $tersedia  = $barang->stok_fisik - $barang->stok_hold;
        $keranjang = Session::get('keranjang', []);
        $sudahAda  = $keranjang[$barang->id]['jumlah'] ?? 0;

        if (($sudahAda + $jumlah) > $tersedia) {
            Notification::make()
                ->title('Jumlah melebihi stok tersedia')
                ->body("Stok tersedia {$tersedia} {$barang->satuan}, sudah ada {$sudahAda} di keranjang.")
                ->danger()
                ->send();

            return;
        }

        $keranjang[$barang->id] = [
            'nama'   => $barang->nama_barang,
            'satuan' => $barang->satuan,
            'jumlah' => $sudahAda + $jumlah,
        ];

        Session::put('keranjang', $keranjang);

        Notification::make()
            ->title($barang->nama_barang . ' ditambahkan ke keranjang')
            ->success()
            ->send();
    }

    public function hapusDariKeranjang(int $barangId): void
    {
        $keranjang = Session::get('keranjang', []);
        unset($keranjang[$barangId]);
        Session::put('keranjang', $keranjang);

        Notification::make()->title('Barang dihapus dari keranjang')->success()->send();
    }

    /**
     * Menyimpan permintaan dan mengunci stok.
     *
     * Mengikuti BPMN P.2.S3. Apabila pengaju berperan sebagai Ketua Tim
     * (percabangan P.2.6), tahap persetujuan Ketua Tim dilewati.
     */
    protected function ajukan(array $data): void
    {
        // Jumlah diambil dari formulir, bukan dari session, agar perubahan
        // yang dilakukan pengguna pada dialog ikut tersimpan.
        $keranjang = collect($data['items'] ?? [])
            ->filter(fn ($i) => (int) ($i['jumlah'] ?? 0) > 0)
            ->mapWithKeys(fn ($i) => [
                (int) $i['barang_id'] => ['jumlah' => (int) $i['jumlah']],
            ])
            ->toArray();

        if (empty($keranjang)) {
            Notification::make()->title('Keranjang masih kosong')->warning()->send();
            return;
        }

        $user = auth()->user();

        if (! $user->tim_id) {
            Notification::make()
                ->title('Akun Anda belum terhubung dengan tim kerja')
                ->body('Hubungi Administrator untuk melengkapi data akun.')
                ->danger()
                ->send();
            return;
        }

        $adalahKetua = $user->role === 'ketua_tim';

        try {
            DB::transaction(function () use ($keranjang, $user, $adalahKetua, $data) {
                app(StokService::class)->hold(
                    array_map(fn ($i) => $i['jumlah'], $keranjang)
                );

                $batasJam = (int) (DB::table('pengaturan')
                    ->where('kunci', $adalahKetua ? 'batas_verifikasi_jam' : 'batas_ketua_jam')
                    ->value('nilai') ?? 24);

                $permintaan = PermintaanBarang::create([
                    'kode_permintaan'      => $this->buatKode(),
                    'tim_pemohon_id'       => $user->tim_id,
                    'pengaju_id'           => $user->id,
                    'nama_pemohon'         => $data['nama_pemohon'],
                    'nip_pemohon'          => $data['nip_pemohon'] ?: null,
                    'keterangan_keperluan' => $data['keperluan'] ?: null,
                    'status'               => $adalahKetua ? 'menunggu_verifikasi' : 'menunggu_ketua',
                    'hold_expired_at'      => now()->addHours($batasJam),
                ]);

                foreach ($keranjang as $barangId => $item) {
                    $permintaan->detail()->create([
                        'barang_id'      => $barangId,
                        'jumlah_diminta' => $item['jumlah'],
                    ]);
                }

                // Baris pertama riwayat menandakan pengajuan oleh unit pemohon
                RiwayatPersetujuan::create([
                    'permintaan_id' => $permintaan->id,
                    'tahap'         => 'pengajuan',
                    'pelaksana_id'  => $user->id,
                    'keputusan'     => 'selesai',
                    'catatan'       => 'Permintaan diajukan.',
                    'waktu'         => now(),
                ]);

                // Apabila pengaju berperan sebagai Ketua Tim, tahap persetujuan dilewati
                if ($adalahKetua) {
                    RiwayatPersetujuan::create([
                        'permintaan_id' => $permintaan->id,
                        'tahap'         => 'ketua_tim',
                        'pelaksana_id'  => $user->id,
                        'keputusan'     => 'setuju',
                        'catatan'       => 'Pengaju berperan sebagai Ketua Tim, tahap persetujuan dilewati.',
                        'waktu'         => now(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Permintaan gagal diajukan')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        Session::forget('keranjang');

        Notification::make()
            ->title('Permintaan berhasil diajukan')
            ->body('Stok telah dikunci sementara menunggu proses persetujuan.')
            ->success()
            ->send();
    }

    protected function buatKode(): string
    {
        $prefix   = 'PB-' . now()->format('Y') . '-';
        $terakhir = PermintaanBarang::where('kode_permintaan', 'like', $prefix . '%')
            ->orderByDesc('kode_permintaan')
            ->value('kode_permintaan');

        $urut = $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;

        return $prefix . str_pad($urut, 4, '0', STR_PAD_LEFT);
    }
}