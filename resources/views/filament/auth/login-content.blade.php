{{--
    Isi formulir masuk, tanpa kartu bawaan Filament. Autentikasi tetap ditangani
    sepenuhnya oleh skema formulir bawaan melalui $this->content.

    Kelas "sedang memproses" dipasang Livewire selama aksi authenticate berjalan,
    sekadar untuk meredupkan kolom isian sebagai penanda proses. Pencegahan
    pengiriman ganda sudah ditangani Filament sendiri lewat wire:loading pada
    tombolnya, jadi tidak ada logika baru yang ditambahkan di sini.
--}}
<div
    class="fi-simpbi-login-form"
    wire:loading.class="fi-simpbi-login-memproses"
    wire:target="authenticate"
>
    {{ $this->content }}
</div>
