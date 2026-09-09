{{-- Penukar bahasa untuk halaman verifikasi.

     Bentuknya berbeda dari yang di navbar halaman muka: di sini kedua pilihan
     dijajarkan begitu saja, tanpa dropdown. Halaman ini dibuka orang yang baru
     saja memindai kode QR pada selembar dokumen — sering sekali baru pertama
     kali melihatnya — sehingga pilihan yang langsung terlihat lebih menolong
     daripada satu tombol yang harus ditekan dulu untuk tahu isinya. --}}
<div class="bahasa">
    @foreach (['id', 'en'] as $kode)
        <a href="{{ route('bahasa.ganti', $kode) }}" @class(['aktif' => app()->getLocale() === $kode])
            @if (app()->getLocale() === $kode) aria-current="true" @endif>
            <svg class="bendera" aria-hidden="true">
                <use href="#bendera-{{ $kode }}" />
            </svg>{{ strtoupper($kode) }}
        </a>
    @endforeach
</div>
