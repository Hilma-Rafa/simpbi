<x-filament-panels::page>

    {{--
        Wizard bertahap. Stepper, tombol Lanjut/Sebelumnya, dan tombol Selesai
        pada tahap terakhir seluruhnya dirender oleh komponen Wizard Filament,
        sehingga halaman ini cukup memanggil formulirnya. Tidak ada <form>
        pembungkus: penyimpanan berjalan lewat aksi "selesai" yang meminta
        konfirmasi lebih dulu, bukan lewat submit formulir biasa.
    --}}
    {{-- Lebar formulir dijaga agar nyaman dibaca; stepper empat tahap tanpa
         subjudul sudah muat utuh tanpa penggulung mendatar pada lebar ini. --}}
    <div class="mx-auto w-full max-w-3xl">
        {{ $this->form }}
    </div>

</x-filament-panels::page>
