<x-filament-panels::page>
    {{--
        Pemilih periode berdiri di luar tabel, bukan sebagai penyaring di
        dalamnya, karena periode bukan penyaring baris melainkan penentu isi
        kolom: Stok Awal, Masuk, Keluar, dan Sisa seluruhnya berubah maknanya
        ketika tahunnya berganti.
    --}}
    <div class="fi-simpbi-periode">
        <label for="periodeKartuKendali" class="fi-simpbi-periode-label">
            Periode kartu kendali
        </label>

        <select
            id="periodeKartuKendali"
            wire:model.live="tahun"
            class="fi-simpbi-periode-pilih"
        >
            @foreach ($this->tahunTersedia() as $nilai => $label)
                <option value="{{ $nilai }}">{{ $label }}</option>
            @endforeach
        </select>

        <span class="fi-simpbi-periode-catatan">
            Januari s.d. Desember {{ $this->tahun }}
        </span>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
