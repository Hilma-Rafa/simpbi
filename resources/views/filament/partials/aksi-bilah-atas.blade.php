{{--
    Kelompok kanan bilah atas: [toggle tema] [lonceng] [avatar → dropdown profil].

    Dipasang lewat hook GLOBAL_SEARCH_AFTER, di dalam `.fi-topbar-end`, sehingga
    ketiga elemen ini menjadi saudara langsung dan jaraknya tetap jarak bawaan
    (gap 16px) antara lonceng dan avatar. Kolom pencarian sudah dimatikan, dan
    menu pengguna bawaan Filament dimatikan supaya markup dropdown ini satu-
    satunya (Panel::userMenu(false)) — bukan ditambal di atas templat lamanya.

    SEBAB BARIS IDENTITAS DULU TAMPIL SEBAGAI ITEM MENU
    Templat bawaan hanya menganggap satu MenuItem sebagai "kepala" (bukan
    tombol) bila ia berada SEBELUM pemilih tema. MenuItem 'profile' pada panel
    ini bernilai sort ≥ 0, sehingga jatuh ke daftar sesudah pemilih tema dan
    dirender oleh perulangan yang sama dengan Pengaturan dan Keluar: sebagai
    <button wire:click="mountAction('profile')"> berkelas item menu. Baris itu
    tidak punya tautan; satu-satunya yang melekat adalah wire:click tadi, yang
    tidak menuju ke mana pun (tidak ada aksi 'profile' yang terdaftar). Di sini
    identitas ditulis sebagai <div> tersendiri tanpa aksi apa pun.
--}}
@php
    use App\Filament\Pages\Pengaturan;
    use App\Filament\Pages\PusatBantuan;

    $pengguna = auth()->user();
    $nama     = (string) $pengguna->name;
    $email    = (string) $pengguna->email;

    // "Administrator Sistem" → "AS". Tanpa permintaan jaringan (A-019).
    $inisial = Pengaturan::inisial($nama);

    // KEPUTUSAN 1: halaman Pengaturan milik admin juga memuat Pengaturan Sistem.
    $adalahAdmin        = $pengguna->role === 'admin';
    $judulPengaturan    = $adalahAdmin ? 'Pengaturan' : 'Pengaturan Profil';
    $subjudulPengaturan = $adalahAdmin ? 'PROFIL, KEAMANAN & SISTEM' : 'PROFIL & KEAMANAN';
@endphp

{{-- ============================ TOGGLE TEMA ============================ --}}
{{--
    Hanya dua keadaan: terang dan gelap. Mekanismenya tetap milik Filament:
    tombol ini mengirim `theme-changed` (terang/gelap), lalu pendengar bawaan
    menyimpannya ke localStorage 'theme' dan menyetel kelas `dark` pada <html>.
    Posisi thumb dibaca dari kelas `dark` (bukan dari aria-checked) agar sama
    dengan skrip anti-kedip pada <head> dan tidak bergeser saat halaman dimuat.
--}}
<button
    type="button"
    role="switch"
    aria-checked="false"
    aria-label="Mode gelap"
    x-data
    x-bind:aria-checked="($store.theme === 'dark') ? 'true' : 'false'"
    x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')"
    x-tooltip="{
        content: () => $store.theme === 'dark' ? 'Ganti ke mode terang' : 'Ganti ke mode gelap',
        theme: $store.theme,
        placement: 'bottom',
        delay: [300, 0],
        duration: [120, 120],
    }"
    class="simpbi-tema"
>
    <span class="simpbi-tema-trek" aria-hidden="true">
        <x-filament::icon icon="heroicon-o-sun" class="simpbi-tema-ikon simpbi-tema-ikon-kiri" />
        <x-filament::icon icon="heroicon-o-moon" class="simpbi-tema-ikon simpbi-tema-ikon-kanan" />

        <span class="simpbi-tema-thumb">
            <x-filament::icon icon="heroicon-o-sun" class="simpbi-tema-thumb-ikon simpbi-tema-thumb-matahari" />
            <x-filament::icon icon="heroicon-o-moon" class="simpbi-tema-thumb-ikon simpbi-tema-thumb-bulan" />
        </span>
    </span>
</button>

{{-- ============================== LONCENG ============================== --}}
@livewire('lonceng-notifikasi')

{{-- ========================== DROPDOWN PROFIL ========================== --}}
<div
    class="simpbi-profil-akar"
    x-data="{
        abaikanKeyup: false,
        terbuka: false,
        pulihkanFokus: false,

        tombol() { return this.$root.querySelector('.fi-user-menu-trigger') },

        panel() {
            const id = this.tombol()?.getAttribute('aria-controls')

            return id ? document.getElementById(id) : null
        },

        init() {
            // Escape dicatat pada fase tangkap, sebelum dropdown bawaan
            // menutup dirinya sendiri, agar penutupan karena Escape (bukan
            // klik di luar) dapat dikenali dan fokusnya dikembalikan ke avatar.
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.terbuka) this.pulihkanFokus = true
            }, true)

            // Keadaan buka/tutup dibaca dari aria-expanded milik dropdown
            // bawaan. Saat terbuka, fokus awal jatuh pada item interaktif
            // pertama, bukan pada blok identitas; ditunda satu putaran agar
            // tidak tertimpa fokus bawaan peramban pada mousedown.
            new MutationObserver(() => {
                const buka = this.tombol().getAttribute('aria-expanded') === 'true'

                if (buka === this.terbuka) return

                this.terbuka = buka

                if (buka) {
                    this.pulihkanFokus = false
                    setTimeout(() => this.panel()?.querySelector('[data-fokus-awal]')?.focus(), 0)
                } else if (this.pulihkanFokus) {
                    this.pulihkanFokus = false
                    this.tombol().focus()
                }
            }).observe(this.tombol(), { attributes: true, attributeFilter: ['aria-expanded'] })
        },

        // Sesudah dialog keluar ditutup tanpa keluar, fokus kembali ke avatar.
        // Dropdown bawaan membuka/menutup pada keyup Enter, jadi keyup yang
        // menyusul penekanan Enter pada tombol dialog dibuang sekali agar
        // dropdown tidak terbuka lagi dengan sendirinya.
        kembaliKeAvatar() {
            this.abaikanKeyup = true
            this.$nextTick(() => this.tombol()?.focus())
        },
    }"
    x-on:keydown.window="abaikanKeyup = false"
    x-on:modal-closed.window="if ($event.detail.id === 'dialog-keluar') kembaliKeAvatar()"
>
    {{--
        Jarak 8px di bawah bilah atas. Dropdown bawaan mengukur jarak dari
        tombol avatar (32px, di tengah bilah setinggi 64px), bukan dari tepi
        bilah, sehingga offset-nya 8px + 16px sisa bilah di bawah avatar.
    --}}
    <x-filament::dropdown
        placement="bottom-end"
        teleport
        width="fi-width-none simpbi-profil-panel"
        :offset="24"
        class="fi-user-menu"
    >
        <x-slot name="trigger">
            <button
                type="button"
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                aria-haspopup="true"
                aria-expanded="false"
                x-on:keyup="if (abaikanKeyup) { abaikanKeyup = false; $event.stopPropagation() }"
                class="fi-user-menu-trigger"
            >
                <span class="simpbi-avatar-inisial" aria-hidden="true">{{ $inisial }}</span>
            </button>
        </x-slot>

        {{-- Blok 1 — identitas akun. BUKAN tombol: tanpa tautan, aksi, tabindex, atau peran. --}}
        <div class="simpbi-profil-identitas">
            <span class="simpbi-profil-avatar" aria-hidden="true">{{ $inisial }}</span>

            <div class="simpbi-profil-teks">
                <p class="simpbi-profil-nama" title="{{ $nama }}">{{ $nama }}</p>
                <p class="simpbi-profil-email" title="{{ $email }}">{{ $email }}</p>
            </div>
        </div>

        <hr class="simpbi-profil-garis" aria-hidden="true">

        {{-- Blok 2 — menu: dua tautan. --}}
        <div class="simpbi-profil-blok">
            <a
                href="{{ Pengaturan::getUrl() }}"
                data-fokus-awal
                class="simpbi-profil-item"
            >
                <span class="simpbi-profil-ikon simpbi-profil-ikon-amber" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-cog-6-tooth" />
                </span>

                <span class="simpbi-profil-item-teks">
                    <span class="simpbi-profil-judul">{{ $judulPengaturan }}</span>
                    <span class="simpbi-profil-subjudul" title="{{ $subjudulPengaturan }}">{{ $subjudulPengaturan }}</span>
                </span>
            </a>

            <a href="{{ PusatBantuan::getUrl() }}" class="simpbi-profil-item">
                <span class="simpbi-profil-ikon simpbi-profil-ikon-biru" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-question-mark-circle" />
                </span>

                <span class="simpbi-profil-item-teks">
                    <span class="simpbi-profil-judul">Pusat Bantuan</span>
                    <span class="simpbi-profil-subjudul" title="PANDUAN &amp; KONTAK">PANDUAN &amp; KONTAK</span>
                </span>
            </a>
        </div>

        <hr class="simpbi-profil-garis" aria-hidden="true">

        {{-- Blok 3 — keluar. Membuka dialog konfirmasi; tidak mengirim logout langsung. --}}
        <div class="simpbi-profil-blok">
            <button
                type="button"
                aria-haspopup="dialog"
                x-on:click="close(); $dispatch('open-modal', { id: 'dialog-keluar' })"
                class="simpbi-profil-item simpbi-profil-item-keluar"
            >
                <span class="simpbi-profil-ikon simpbi-profil-ikon-merah" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-arrow-left-start-on-rectangle" />
                </span>

                <span class="simpbi-profil-item-teks">
                    <span class="simpbi-profil-judul">Keluar</span>
                    <span class="simpbi-profil-subjudul" title="AKHIRI SESI ANDA">AKHIRI SESI ANDA</span>
                </span>
            </button>
        </div>
    </x-filament::dropdown>
</div>
