{{--
    Isi formulir masuk, tanpa kartu bawaan Filament. Autentikasi tetap ditangani
    sepenuhnya oleh skema formulir bawaan melalui $this->content.

    Kelas "sedang memproses" dipasang Livewire selama aksi authenticate berjalan,
    sekadar untuk meredupkan kolom isian sebagai penanda proses. Pencegahan
    pengiriman ganda sudah ditangani Filament sendiri lewat wire:loading pada
    tombolnya, jadi tidak ada logika baru yang ditambahkan di sini.
--}}
{{--
    Penanda Caps Lock dipasang di bawah kolom kata sandi lewat view ini, bukan
    lewat kelas Login: kelas itu tidak disentuh sama sekali. Yang ditambahkan
    hanya konten tampilan di bawah kolom; definisi kolom, aturan validasi, dan
    penanganan autentikasinya tetap dari Filament.
--}}
@php
    $this->form->getComponent('password')
        ?->belowContent(view('filament.auth.indikator-caps-lock'));
@endphp

<div
    class="fi-simpbi-login-form"
    wire:loading.class="fi-simpbi-login-memproses"
    wire:target="authenticate"
>
    {{ $this->content }}
</div>
