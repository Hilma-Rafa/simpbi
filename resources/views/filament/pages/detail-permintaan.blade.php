{{--
    Halaman rincian permintaan barang.

    Isi rincian dipakai kembali dari partial yang sebelumnya ditampilkan di
    dalam dialog, sehingga informasi dan tata letaknya tidak berubah ketika
    tombol "Detail" digantikan oleh klik pada baris tabel.
--}}
<x-filament-panels::page>
    @include('filament.partials.detail-permintaan', ['record' => $this->getRecord()])
</x-filament-panels::page>
