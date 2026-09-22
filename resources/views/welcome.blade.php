<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('muka.meta.judul') }}</title>
    <meta name="description" content="{{ __('muka.meta.deskripsi') }}">

    <link rel="icon" href="{{ asset('images/logo-bps.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Tiga keluarga huruf dengan tugas yang tegas dan tidak saling menimpa:

         Inter            — seluruh teks dan antarmuka, sama seperti panel aplikasi.
         Plus Jakarta Sans — judul bagian, sedikit lebih tegas pada ukuran besar.
         Space Grotesk    — khusus wordmark SIMPBI.

         Space Grotesk dipilih untuk wordmark karena bentuk hurufnya lurus dan
         terukur — terasa seperti penanda sistem, bukan judul tulisan — dan
         bertahan baik ketika dibesarkan sekaligus direnggangkan. Karakter
         teknisnya juga menyambung dengan animasi galat pada nama panjang di
         bawahnya. Pemakaiannya sengaja dibatasi hanya pada wordmark; panel
         aplikasi tetap sepenuhnya memakai Inter. --}}
    {{-- Inter dari berkas lokal; dua keluarga lainnya belum tersedia lokal dan
         masih dimuat dari fonts.bunny.net (A-019). --}}
    @include('partials.font-inter-lokal')
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:600,700,800|space-grotesk:500,700" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif

    {{-- Kelas .js dipasang lebih dulu agar penyembunyian elemen hanya berlaku
         ketika JavaScript benar-benar dapat menampilkannya kembali. Tanpa JS,
         atau bila skrip gagal, seluruh isi halaman tetap terlihat penuh. --}}
    <script>document.documentElement.classList.add('js')</script>
    <style>
        /* ---------- Tipografi ---------- */
        :root {
            --font-display: 'Plus Jakarta Sans', 'Inter', ui-sans-serif, system-ui, sans-serif;
            --font-wordmark: 'Space Grotesk', 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
        }

        .font-display {
            font-family: var(--font-display);
            font-feature-settings: 'ss01' 1;
        }

        /* Wordmark selalu huruf besar dan direnggangkan. */
        .font-wordmark {
            font-family: var(--font-wordmark);
            text-transform: uppercase;
            font-feature-settings: 'ss01' 1, 'ss02' 1;
        }

        /* Perenggangan menyisakan satu ruang kosong di kanan huruf terakhir.
           Ruang itu ikut terhitung saat baris dipusatkan, sehingga hurufnya
           tampak bergeser ke kiri sejauh setengah renggang. Padding kiri
           sebesar satu renggang menggeser kotak isi ke kanan sejauh setengah
           renggang pula, tepat menghapus selisih itu. Margin kanan negatif
           tidak dipakai karena pada elemen blok justru melebarkan kotaknya. */
        .wordmark-hero {
            letter-spacing: 0.12em;
            padding-left: 0.12em;
        }

        /* Enam huruf saja tidak akan pernah memenuhi lebar layar besar hanya
           dengan dibesarkan: pada 120px pun hurufnya hanya selebar 458px di
           dalam wadah 1024px. Yang melebarkan adalah renggang antar huruf,
           yang ditambah bertahap mengikuti lebar layar sehingga wordmark
           membentang, bukan menggumpal di tengah. */
        @media (min-width: 768px) {
            .wordmark-hero {
                letter-spacing: 0.26em;
                padding-left: 0.26em;
            }
        }

        @media (min-width: 1024px) {
            .wordmark-hero {
                letter-spacing: 0.38em;
                padding-left: 0.38em;
            }
        }

        /* ---------- Nama panjang: retakan warna ----------
           Tiga lapisan teks yang sama ditumpuk: satu lapisan asli, dua lapisan
           bayangan berwarna di belakangnya. Bayangan itu diam hampir sepanjang
           waktu dan hanya bergeser sesaat, sehingga terbaca sebagai gangguan
           sinyal yang lewat, bukan sebagai teks yang bergetar terus-menerus —
           yang akan melelahkan dibaca dan membuat halaman terasa rusak.

           Inilah satu-satunya gerak berulang yang tersisa di sini. Sebelumnya
           ada tiga sekaligus — retakan warna, berkas pindai yang melintas, dan
           pengacakan huruf oleh JavaScript — dan ketiganya berjalan pada satu
           baris teks yang sama sehingga saling berebut perhatian. Dua yang
           lain dibuang; yang bertahan adalah retakan warna, karena karakter
           teknisnya paling menyambung dengan wordmark SIMPBI di atasnya dan
           seluruhnya cukup ditangani CSS. */
        .glitch {
            position: relative;
            display: inline-block;
            /* Mengurung berkas pindai dan lapisan bayangan di dalam kotak
               teksnya sendiri. Tanpa ini berkas pindai melayang naik sampai
               menutupi wordmark, dan bayangan glitch menonjol keluar baris. */
            overflow: hidden;
        }

        .glitch::before,
        .glitch::after {
            content: attr(data-text);
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
        }

        .glitch::before {
            color: #7DD3FC;
            /* biru langit, sisi kiri */
            animation: simpbi-glitch-kiri 7s steps(1, end) infinite;
        }

        .glitch::after {
            color: #F59E0B;
            /* jingga aksen, sisi kanan */
            animation: simpbi-glitch-kanan 7s steps(1, end) infinite;
        }

        /* Dua ledakan pendek dalam tujuh detik: satu pada 82%, satu pada 90%.
           Sisa waktunya benar-benar diam. */
        @keyframes simpbi-glitch-kiri {
            0%, 81.9%   { opacity: 0; transform: none; clip-path: inset(0 0 0 0); }
            82%         { opacity: .85; transform: translate(-2px, -1px); clip-path: inset(12% 0 58% 0); }
            83.5%       { opacity: .85; transform: translate(3px, 1px);  clip-path: inset(64% 0 12% 0); }
            85%, 89.9%  { opacity: 0; transform: none; }
            90%         { opacity: .8; transform: translate(-3px, 0);   clip-path: inset(38% 0 40% 0); }
            91.5%, 100% { opacity: 0; transform: none; }
        }

        @keyframes simpbi-glitch-kanan {
            0%, 81.9%   { opacity: 0; transform: none; clip-path: inset(0 0 0 0); }
            82%         { opacity: .8; transform: translate(2px, 1px);  clip-path: inset(58% 0 14% 0); }
            83.5%       { opacity: .8; transform: translate(-3px, -1px); clip-path: inset(10% 0 66% 0); }
            85%, 89.9%  { opacity: 0; transform: none; }
            90%         { opacity: .75; transform: translate(3px, 0);   clip-path: inset(30% 0 48% 0); }
            91.5%, 100% { opacity: 0; transform: none; }
        }

        /* ---------- Penampakan saat digulir ---------- */
        .js [data-reveal] {
            opacity: 0;
            transform: translateY(1.75rem);
            transition:
                opacity .7s cubic-bezier(.22, .61, .36, 1),
                transform .7s cubic-bezier(.22, .61, .36, 1);
        }

        .js [data-reveal].is-visible {
            opacity: 1;
            transform: none;
        }

        /* ---------- Masuknya elemen hero ----------
           Tiap elemen memakai animasi CSS dengan jeda sendiri lewat --d,
           sehingga urutannya terbaca sebagai satu rangkaian, dan berhenti
           tepat pada posisi normal karena memakai fill-mode forwards. */
        .js .hero-in {
            opacity: 0;
            transform: translateY(0.9rem);
            animation: simpbi-hero-in .7s cubic-bezier(.22, .61, .36, 1) forwards;
            animation-delay: var(--d, 0ms);
        }

        @keyframes simpbi-hero-in {
            to { opacity: 1; transform: none; }
        }

        /* ---------- Judul hero: pengungkapan per kata ----------
           Kata muncul berurutan baris demi baris, memberi kesan proses yang
           sedang berjalan tanpa mengetik satu per satu karakter. */
        .js .word-in {
            display: inline-block;
            opacity: 0;
            transform: translateY(0.4em);
            filter: blur(5px);
            animation: simpbi-word-in .6s cubic-bezier(.22, .61, .36, 1) forwards;
            animation-delay: var(--d, 0ms);
        }

        @keyframes simpbi-word-in {
            to { opacity: 1; transform: none; filter: none; }
        }

        /* ---------- Penanda gulir ---------- */
        .cue-arrow {
            animation: simpbi-cue 2.1s cubic-bezier(.45, 0, .55, 1) infinite;
        }

        @keyframes simpbi-cue {
            0%, 100% { transform: translateY(0); opacity: .5; }
            50%      { transform: translateY(6px); opacity: 1; }
        }

        /* ---------- Keadaan aktif menu navigasi ----------
           Ditandai garis kecil di bawah label, cukup untuk menunjukkan posisi
           tanpa mengubah bentuk atau lebar tombolnya. */
        .nav-link { position: relative; }

        .nav-link::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0.28rem;
            height: 2px;
            width: 0;
            border-radius: 999px;
            background: var(--color-accent, #F59E0B);
            transform: translateX(-50%);
            transition: width .3s cubic-bezier(.22, .61, .36, 1);
        }

        .nav-link.is-active { color: #fff; }
        .nav-link.is-active::after { width: 0.9rem; }

        /* ---------- Penukar bahasa ----------
           Panel disembunyikan dengan visibility, bukan display, supaya
           membuka dan menutupnya dapat dianimasikan. Dengan display:none
           peralihannya akan terpotong dan panel terasa melompat. */
        .menu-bahasa {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-0.25rem) scale(.97);
            transition:
                opacity .18s cubic-bezier(.22, .61, .36, 1),
                transform .18s cubic-bezier(.22, .61, .36, 1),
                visibility .18s;
        }

        .menu-bahasa.is-open {
            opacity: 1;
            visibility: visible;
            transform: none;
        }

        /* Bendera diberi garis tipis agar bagian putihnya tidak lenyap di atas
           bilah navigasi yang gelap. */
        .bendera {
            border-radius: 2px;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .28);
        }

        /* ---------- Menghormati preferensi pengurangan gerak ---------- */
        @media (prefers-reduced-motion: reduce) {
            .js [data-reveal],
            .js .hero-in,
            .js .word-in {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
                animation: none;
            }

            .cue-arrow { animation: none; }

            .menu-bahasa { transition: none; }

            .glitch::before,
            .glitch::after {
                animation: none;
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-surface text-ink antialiased" style="font-family: var(--font-sans)">

    @include('komponen.lambang-bendera')

    {{-- ============================ NAVBAR ============================
         Bilah selebar layar yang menempel di tepi atas, bukan pil mengambang.
         Bentuk ini membuat kepala halaman terbaca sebagai bagian dari sistem,
         bukan sebagai elemen yang melayang di atasnya, dan memberi ruang bagi
         dua tombol aksi sekaligus di ujung kanan.

         Tautan "Beranda" ditiadakan: lambang di kiri sudah mengembalikan
         pengguna ke atas halaman, sehingga menyebutkannya dua kali hanya
         menambah panjang menu tanpa menambah kegunaan. --}}
    <header id="nav"
        class="fixed inset-x-0 top-0 z-40 border-b border-white/10 bg-navy/85 backdrop-blur-md transition-[background-color,border-color,box-shadow] duration-300 ease-[cubic-bezier(.22,.61,.36,1)]">
        <nav class="relative mx-auto flex h-16 max-w-7xl items-center px-4 sm:px-6 lg:px-8">

            {{-- Lambang, sekaligus jalan pulang ke atas halaman --}}
            <a href="#beranda" class="flex shrink-0 items-center gap-2.5">
                <span class="grid h-8 w-8 place-items-center rounded-md bg-white p-1.5">
                    <img src="{{ asset('images/logo-bps.png') }}" alt="Logo BPS" class="h-full w-full object-contain">
                </span>
                <span class="font-wordmark text-[15px] font-bold tracking-[0.14em] text-white">SIMPBI</span>
            </a>

            {{-- Menu, dipusatkan terhadap bilah dan bukan terhadap ruang sisa.
                 Sebelumnya menu ini memakai flex-1 di antara lambang dan tombol
                 aksi, sehingga titik tengahnya mengikuti ruang yang tersisa —
                 dan karena lambang di kiri tidak selebar tombol di kanan, menu
                 selalu tampak melenceng. Diposisikan mutlak pada 50% lebar
                 bilah, letaknya benar-benar di tengah berapa pun lebar kedua
                 sisinya.

                 Wadahnya dibuat tembus klik agar tidak menghalangi lambang atau
                 tombol seandainya sempat bertumpuk pada lebar layar tertentu;
                 tautannya sendiri dikembalikan menjadi dapat diklik. --}}
            <div class="pointer-events-none absolute left-1/2 hidden -translate-x-1/2 items-center gap-8 md:flex">
                @foreach (['tentang', 'fitur', 'alur', 'verifikasi'] as $anchor)
                    <a href="#{{ $anchor }}" data-nav="{{ $anchor }}"
                        class="nav-link pointer-events-auto py-2 text-[13.5px] font-medium text-white/65 transition-colors duration-200 hover:text-white">{{ __('muka.nav.' . $anchor) }}</a>
                @endforeach
            </div>

            {{-- Dua aksi di ujung kanan: penukar bahasa, lalu satu-satunya
                 tombol utama. Tombol "Verifikasi Dokumen" yang dulu di sini
                 ditiadakan — tautan Verifikasi pada menu tengah sudah menuju
                 bagian yang sama, jadi menyebutkannya dua kali hanya melebarkan
                 sisi kanan tanpa menambah kegunaan.

                 Penukar bahasa dibuat sesempit mungkin: hanya bendera yang
                 sedang dipakai, kode dua huruf, dan panah. Tingginya sama
                 dengan tombol di sebelahnya karena memakai ukuran huruf yang
                 persis sama, sehingga keduanya sebaris tanpa perlu tinggi yang
                 dipatok. Padding tegaknya 7px, bukan 8px seperti tetangganya,
                 justru supaya tingginya sama: tombol ini punya garis tepi yang
                 menambah 1px di atas dan di bawah. --}}
            <div class="ml-auto hidden shrink-0 items-center gap-2 sm:flex">
                <div class="relative" data-penukar-bahasa>
                    <button type="button" id="tombolBahasa" aria-haspopup="true" aria-expanded="false"
                        aria-label="{{ __('muka.nav.bahasa') }}"
                        class="flex items-center gap-1.5 rounded-lg border border-white/20 px-2.5 py-[7px] text-[13.5px] font-semibold text-white transition-[background-color,border-color] duration-200 hover:border-white/35 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60">
                        <svg class="bendera h-3 w-[1.125rem] shrink-0" aria-hidden="true">
                            <use href="#bendera-{{ app()->getLocale() }}" />
                        </svg>
                        <span class="uppercase">{{ app()->getLocale() }}</span>
                        <svg class="h-3.5 w-3.5 text-white/60 transition-transform duration-200" data-panah
                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                    </button>

                    <div id="menuBahasa" role="menu" aria-labelledby="tombolBahasa"
                        class="menu-bahasa absolute right-0 top-full z-50 mt-2 w-44 rounded-xl border border-white/15 bg-navy/95 p-1.5 shadow-[0_16px_40px_-16px_rgba(0,0,0,.8)] backdrop-blur-md">
                        @foreach (['id', 'en'] as $kode)
                            <a href="{{ route('bahasa.ganti', $kode) }}" role="menuitem"
                                @class([
                                    'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13.5px] transition-colors duration-150',
                                    'bg-white/10 font-semibold text-white' => app()->getLocale() === $kode,
                                    'font-medium text-white/70 hover:bg-white/5 hover:text-white' => app()->getLocale() !== $kode,
                                ])>
                                <svg class="bendera h-3 w-[1.125rem] shrink-0" aria-hidden="true">
                                    <use href="#bendera-{{ $kode }}" />
                                </svg>
                                {{ __('muka.bahasa.' . $kode) }}
                                @if (app()->getLocale() === $kode)
                                    <svg class="ml-auto h-3.5 w-3.5 text-accent" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.4" stroke-linecap="round"
                                        stroke-linejoin="round"><path d="M5 12l4 4L19 6" /></svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <a href="{{ url('/admin') }}"
                    class="rounded-lg bg-accent px-4 py-2 text-[13.5px] font-semibold text-navy transition-[background-color,box-shadow,transform] duration-200 hover:bg-accent/90 hover:shadow-[0_6px_18px_-6px_rgba(245,158,11,.7)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:scale-[.98]">
                    {{ __('muka.nav.masuk') }}
                </a>
            </div>

            <button id="menuBtn"
                class="ml-auto grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white transition-colors duration-200 hover:bg-white/10 sm:ml-2 md:hidden"
                aria-label="{{ __('muka.nav.buka_menu') }}" aria-expanded="false">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>
        </nav>
    </header>

    {{-- Menu untuk layar kecil --}}
    <div id="mobileMenu"
        class="fixed inset-0 z-50 hidden flex-col bg-navy/95 px-6 pt-24 backdrop-blur-xl md:hidden">
        <button id="menuClose" class="absolute right-6 top-7 grid h-10 w-10 place-items-center rounded-full text-white"
            aria-label="{{ __('muka.nav.tutup_menu') }}">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
        <nav class="flex flex-col gap-1">
            @foreach (['tentang', 'fitur', 'alur', 'verifikasi'] as $anchor)
                <a href="#{{ $anchor }}" data-close
                    class="font-display border-b border-white/10 py-4 text-2xl font-semibold text-white/85 transition-colors duration-200 hover:text-white">{{ __('muka.nav.' . $anchor) }}</a>
            @endforeach
            <a href="{{ url('/admin') }}"
                class="mt-6 flex items-center justify-center gap-2 rounded-lg bg-accent py-3.5 text-sm font-semibold text-navy">
                {{ __('muka.nav.masuk') }}
            </a>

            {{-- Pada layar kecil penukar bahasa dijajarkan begitu saja, bukan
                 disembunyikan di balik dropdown lagi: hanya ada dua pilihan,
                 dan menu ini sudah terbuka penuh selayar sehingga tidak ada
                 ruang yang perlu dihemat. --}}
            <div class="mt-6 flex items-center justify-center gap-2">
                @foreach (['id', 'en'] as $kode)
                    <a href="{{ route('bahasa.ganti', $kode) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm',
                            'border-white/30 bg-white/10 font-semibold text-white' => app()->getLocale() === $kode,
                            'border-white/15 font-medium text-white/65' => app()->getLocale() !== $kode,
                        ])>
                        <svg class="bendera h-3 w-[1.125rem] shrink-0" aria-hidden="true">
                            <use href="#bendera-{{ $kode }}" />
                        </svg>
                        {{ __('muka.bahasa.' . $kode) }}
                    </a>
                @endforeach
            </div>
        </nav>
    </div>

    {{-- ============================ HERO ============================ --}}
    @php
        // Judul diungkap per kata, baris demi baris. Jeda dihitung di sini agar
        // urutannya terbaca sekali pandang dan mudah disetel.
        $barisJudul = [
            0 => __('muka.hero.nama_panjang'),
        ];
        $jedaKata = 55;   // jarak antar kata
        $jedaBaris = 180; // jeda tambahan sebelum baris kedua
        $mulaiJudul = 340;
    @endphp

    <section id="beranda" class="relative overflow-hidden bg-navy px-4 pb-0 pt-32 text-white md:pt-20">
        {{-- Cahaya lembut, tanpa gradasi berat --}}
        <div class="pointer-events-none absolute inset-0"
            style="background:
                radial-gradient(60rem 30rem at 75% -10%, rgba(245,158,11,.14), transparent 60%),
                radial-gradient(50rem 30rem at 10% 10%, rgba(21,87,166,.55), transparent 55%);">
        </div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.05]"
            style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:44px 44px;
                   mask-image:radial-gradient(75% 65% at 50% 35%,#000 35%,transparent 85%);
                   -webkit-mask-image:radial-gradient(75% 65% at 50% 35%,#000 35%,transparent 85%);">
        </div>

        <div class="relative mx-auto max-w-5xl text-center">

            {{-- Badge --}}
            <span class="hero-in inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-[11px] font-medium uppercase tracking-[0.18em] text-white/75"
                style="--d: 60ms">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                {{ __('muka.hero.badge') }}<span class="hidden sm:inline">{{ __('muka.hero.badge_satker') }}</span>
            </span>

            {{-- Wordmark. Dibesarkan sampai hampir memenuhi lebar wadah supaya
                 tepi kiri dan kanan tidak menganga, dan direnggangkan agar
                 enam hurufnya membentang, bukan menggumpal di tengah. --}}
            <h1 class="hero-in font-wordmark wordmark-hero mt-7 text-[3.25rem] font-bold leading-[0.95] text-white sm:text-7xl md:text-8xl lg:text-[9rem]"
                style="--d: 150ms">
                SIMPBI
            </h1>

            {{-- Kepanjangan nama: bagian yang paling lebar sekaligus yang
                 mengalami gangguan sinyal berulang. Ditulis ulang pada atribut
                 data-text karena dua lapisan bayangan glitch membacanya dari
                 sana. --}}
            <p class="font-display mx-auto mt-5 max-w-4xl text-balance text-xl font-semibold leading-snug tracking-[-0.015em] text-white/90 sm:text-2xl md:text-[2rem]">
                <span class="glitch" data-text="{{ $barisJudul[0] }}">
                    @foreach ($barisJudul as $indeksBaris => $baris)
                        @foreach (explode(' ', $baris) as $indeksKata => $kata)
                            <span class="word-in" data-kata
                                style="--d: {{ $mulaiJudul + ($indeksBaris * $jedaBaris) + ($indeksKata * $jedaKata) }}ms">{{ $kata }}</span>{{ ' ' }}
                        @endforeach
                    @endforeach
                </span>
            </p>

            {{-- Penjelasan singkat --}}
            <p class="hero-in mx-auto mt-6 max-w-2xl text-[15px] leading-relaxed text-white/60 sm:text-base" style="--d: 900ms">
                {{ __('muka.hero.deskripsi') }}
            </p>

            {{-- Aksi --}}
            <div class="hero-in mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--d: 1000ms">
                <a href="{{ url('/admin') }}"
                    class="group flex w-full items-center justify-center gap-2 rounded-full bg-accent py-3 pl-6 pr-3 text-sm font-semibold text-navy transition-[background-color,transform,box-shadow] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] hover:bg-accent/90 hover:shadow-[0_10px_28px_-10px_rgba(245,158,11,.75)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:scale-[.98] sm:w-auto">
                    {{ __('muka.hero.tombol_masuk') }}
                    <span
                        class="grid h-8 w-8 place-items-center rounded-full bg-navy/10 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:translate-x-1">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </span>
                </a>
                <a href="#alur"
                    class="group flex w-full items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition-[background-color,border-color,transform] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] hover:border-white/25 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60 active:scale-[.98] sm:w-auto">
                    {{ __('muka.hero.tombol_alur') }}
                    <svg class="h-4 w-4 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:translate-y-0.5"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 5v14M6 13l6 6 6-6" />
                    </svg>
                </a>
            </div>

            {{-- Satuan kerja --}}
            <div class="hero-in mt-8 flex items-center justify-center gap-3 text-[13px] text-white/45" style="--d: 1080ms">
                <span class="hidden h-px w-8 bg-white/15 sm:block"></span>
                <span>{{ __('muka.hero.satker') }}</span>
                <span class="hidden h-px w-8 bg-white/15 sm:block"></span>
            </div>

            {{-- Penanda bahwa halaman masih dapat digulir. Tanpa teks perintah;
                 menghilang sendiri begitu pengguna mulai menggulir. --}}
            <div id="scrollCue"
                class="hero-in mt-12 flex justify-center transition-opacity duration-500 ease-[cubic-bezier(.22,.61,.36,1)]"
                style="--d: 1200ms" aria-hidden="true">
                <span class="cue-arrow grid h-9 w-9 place-items-center rounded-full border border-white/15 text-white/70">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
                </span>
            </div>
        </div>

        {{-- Pita angka selebar layar. Fungsinya menutup hero dengan garis yang
             benar-benar menyentuh kedua tepi, sehingga bagian bawah tidak lagi
             terasa mengambang di tengah ruang kosong. Angkanya diambil dari
             rancangan sistem, bukan dikarang: lima peran, delapan tim kerja,
             enam tahap persetujuan, dan dua kanal notifikasi. --}}
        <div class="relative -mx-4 mt-16 border-t border-white/10 md:mt-20">
            <dl class="mx-auto grid max-w-7xl grid-cols-2 divide-x divide-white/10 md:grid-cols-4">
                @foreach (__('muka.hero.angka') as $i => $angka)
                    <div class="hero-in px-4 py-6 text-center md:py-7" style="--d: {{ 1240 + $i * 70 }}ms">
                        <dt class="font-wordmark text-2xl font-bold tracking-[0.04em] text-white sm:text-3xl">{{ $angka[0] }}</dt>
                        <dd class="mt-1 text-[11.5px] font-medium uppercase tracking-[0.14em] text-white/50">{{ $angka[1] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ============================ TENTANG / MENGAPA ============================ --}}
    <section id="tentang" class="scroll-mt-24 px-4 py-24 md:py-32">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">{{ __('muka.tentang.label') }}</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    {{ __('muka.tentang.judul') }}
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-muted">
                    {{ __('muka.tentang.paragraf') }}
                </p>
            </div>

            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @php
                    // Jalur ikon tetap tinggal di tampilan: bentuk gambarnya
                    // tidak berubah menurut bahasa, jadi menyalinnya ke berkas
                    // terjemahan hanya menggandakan hal yang sama dua kali.
                    $ikonTentang = [
                        'M3 13h2l1.5 6h11L21 8H6M9 21a1 1 0 100-2 1 1 0 000 2m8 0a1 1 0 100-2 1 1 0 000 2',
                        'M4 6h16M4 12h16M4 18h10',
                        'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
                    ];
                @endphp
                @foreach (__('muka.tentang.kartu') as $i => $c)
                    <div data-reveal style="transition-delay: {{ $i * 90 }}ms"
                        class="rounded-card border border-hairline bg-card p-1.5 shadow-[0_1px_2px_rgba(15,23,42,.04)]">
                        <div class="rounded-[10px] bg-surface/60 p-6">
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-light text-brand">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $ikonTentang[$i] }}" /></svg>
                            </span>
                            <h3 class="mt-5 text-base font-semibold text-navy">{{ $c['judul'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted">{{ $c['isi'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ FITUR (bento) ============================ --}}
    <section id="fitur" class="scroll-mt-24 bg-white px-4 py-24 md:py-32">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">{{ __('muka.fitur.label') }}</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    {{ __('muka.fitur.judul') }}
                </h2>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $ikonFitur = [
                        'M4 6a2 2 0 012-2h9l5 5v9a2 2 0 01-2 2H6a2 2 0 01-2-2zM14 4v5h5',
                        'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                        'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                        'M4 19h16M7 16V8m5 8V5m5 11v-6',
                        'M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4',
                        'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z',
                    ];
                @endphp
                @foreach (__('muka.fitur.kartu') as $i => $f)
                    <div data-reveal style="transition-delay: {{ ($i % 3) * 90 }}ms"
                        class="group rounded-card border border-hairline bg-card p-6 transition-all duration-500 ease-[cubic-bezier(.32,.72,0,1)] hover:-translate-y-1 hover:border-brand/30 hover:shadow-[0_12px_30px_-12px_rgba(21,87,166,.25)]">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-navy text-white transition-colors duration-500 group-hover:bg-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $ikonFitur[$i] }}" /></svg>
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-navy">{{ $f['judul'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ $f['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ ALUR ============================ --}}
    <section id="alur" class="scroll-mt-24 px-4 py-24 md:py-32">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">{{ __('muka.alur.label') }}</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    {{ __('muka.alur.judul') }}
                </h2>
                {{-- Nama peran disisipkan sebagai potongan HTML supaya tetap
                     dicetak tebal, sedangkan letaknya di dalam kalimat diserahkan
                     pada berkas terjemahan — dalam bahasa Inggris urutan katanya
                     berbeda. Isinya berasal dari berkas terjemahan sendiri,
                     bukan dari masukan pengguna, sehingga aman dicetak mentah. --}}
                <p class="mt-4 text-[15px] leading-relaxed text-muted">
                    {!! __('muka.alur.paragraf', [
                        'peran' => '<span class="font-semibold text-navy">' . e(__('muka.alur.peran')) . '</span>',
                    ]) !!}
                </p>
            </div>

            <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (__('muka.alur.tahap') as $i => $tahap)
                    <li data-reveal style="transition-delay: {{ ($i % 4) * 70 }}ms"
                        class="relative flex items-start gap-3 rounded-card border border-hairline bg-card p-5">
                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-light text-sm font-bold text-brand">{{ $i + 1 }}</span>
                        <div>
                            <p class="text-sm font-semibold text-navy">{{ $tahap }}</p>
                            {{-- Ditandai lewat urutan, bukan lewat perbandingan
                                 teks: nama tahapnya berubah mengikuti bahasa,
                                 sedangkan posisinya dalam alur tidak. --}}
                            @if ($i === 1)
                                <p class="mt-1 text-[11px] font-medium text-accent">{{ __('muka.alur.catatan_ketua') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
                <li data-reveal
                    class="flex items-center justify-center rounded-card border border-dashed border-brand/30 bg-brand-light/50 p-5 text-center text-sm font-semibold text-brand">
                    {{ __('muka.alur.selesai') }}
                </li>
            </ol>
        </div>
    </section>

    {{-- ============================ VERIFIKASI QR ============================ --}}
    <section id="verifikasi" class="scroll-mt-24 bg-navy px-4 py-24 text-white md:py-32">
        <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2">
            <div data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">{{ __('muka.verifikasi.label') }}</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ __('muka.verifikasi.judul') }}
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-white/70">
                    {{ __('muka.verifikasi.paragraf') }}
                </p>
                <div class="mt-7 flex flex-wrap gap-3 text-sm text-white/75">
                    @foreach (__('muka.verifikasi.daftar') as $item)
                        <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-1.5">
                            <svg class="h-3.5 w-3.5 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.2" stroke-linecap="round"><path d="M5 12l4 4L19 6" /></svg>
                            {{ $item }}
                        </span>
                    @endforeach
                </div>
            </div>

            {{-- demo verifikasi card, double-bezel --}}
            <div data-reveal class="rounded-hero border border-white/10 bg-white/5 p-2">
                <div class="rounded-[18px] bg-white p-7 text-ink">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-9 w-9 place-items-center rounded-lg bg-navy p-1.5">
                                <img src="{{ asset('images/logo-bps.png') }}" alt="" class="h-full w-full object-contain">
                            </span>
                            <div class="text-[11px] font-semibold leading-tight text-navy">
                                {{ __('muka.verifikasi.demo.instansi') }}<br><span class="text-muted">{{ __('muka.verifikasi.demo.kota') }}</span>
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-accent-light px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-warning">{{ __('muka.verifikasi.demo.label') }}</span>
                    </div>

                    <div class="mt-6 grid grid-cols-[auto_1fr] items-center gap-5">
                        <div class="grid h-24 w-24 place-items-center rounded-xl border border-hairline bg-surface">
                            <svg class="h-16 w-16 text-navy" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 3h6v6H3zm2 2v2h2V5zM15 3h6v6h-6zm2 2v2h2V5zM3 15h6v6H3zm2 2v2h2v-2zM15 15h2v2h-2zm4 0h2v2h-2zm-4 4h2v2h-2zm4 0h2v2h-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted">{{ __('muka.verifikasi.demo.jenis') }}</p>
                            <p class="mt-1 font-semibold text-navy">PB-2026-0001</p>
                            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">
                                <span class="h-1.5 w-1.5 rounded-full bg-success"></span> {{ __('muka.verifikasi.demo.status') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg bg-surface px-3.5 py-2.5 font-mono text-[11px] text-muted">
                        {{ __('muka.verifikasi.demo.token') }} SIMPBI-DEMO-2026-0001
                    </div>
                    <p class="mt-2 text-[11px] text-muted">{{ __('muka.verifikasi.demo.catatan') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ CTA ============================ --}}
    <section class="px-4 py-24">
        <div data-reveal
            class="mx-auto max-w-5xl overflow-hidden rounded-hero border border-hairline bg-white p-10 text-center shadow-[0_20px_50px_-24px_rgba(11,42,91,.25)] md:p-16">
            <h2 class="text-3xl font-bold tracking-tight text-navy sm:text-4xl">{{ __('muka.ajakan.judul') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-[15px] leading-relaxed text-muted">
                {{ __('muka.ajakan.paragraf') }}
            </p>
            <a href="{{ url('/admin') }}"
                class="group mt-8 inline-flex items-center gap-2 rounded-full bg-navy py-3.5 pl-6 pr-3 text-sm font-semibold text-white transition-all duration-500 ease-[cubic-bezier(.32,.72,0,1)] hover:bg-brand active:scale-[.98]">
                {{ __('muka.ajakan.tombol') }}
                <span
                    class="grid h-8 w-8 place-items-center rounded-full bg-white/15 transition-transform duration-500 ease-[cubic-bezier(.32,.72,0,1)] group-hover:translate-x-1 group-hover:-translate-y-0.5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
            </a>
        </div>
    </section>

    {{-- ============================ FOOTER ============================
         Hanya tampil di halaman publik ini (dan halaman publik lain yang
         sengaja memuat partial yang sama) — tidak pernah dipasang pada
         kerangka aplikasi sesudah masuk. --}}
    @include('partials.footer-publik')

    {{-- Penampakan saat gulir, keadaan navbar, penanda gulir, dan menu layar
         kecil. Ditulis tanpa pustaka tambahan agar halaman tetap ringan. --}}
    <script>
        // Elemen muncul ketika masuk ke layar
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));

        const nav = document.getElementById('nav');
        const cue = document.getElementById('scrollCue');
        const tautanNav = [...document.querySelectorAll('[data-nav]')];
        const bagian = tautanNav
            .map(a => document.getElementById(a.dataset.nav))
            .filter(Boolean);

        // Navbar menebal sedikit setelah digulir, dan penanda gulir menghilang
        // begitu pengguna benar-benar mulai menggulir.
        const saatGulir = () => {
            const y = window.scrollY;
            nav.classList.toggle('shadow-[0_10px_30px_-12px_rgba(11,42,91,.55)]', y > 16);
            nav.classList.toggle('border-white/15', y > 16);

            if (cue) {
                cue.classList.toggle('opacity-0', y > 40);
                cue.classList.toggle('pointer-events-none', y > 40);
            }

            // Menu yang sedang dilihat ditandai garis kecil di bawah labelnya
            let aktif = bagian[0]?.id ?? null;
            for (const b of bagian) {
                if (b.getBoundingClientRect().top <= window.innerHeight * 0.35) { aktif = b.id; }
            }
            tautanNav.forEach(a => a.classList.toggle('is-active', a.dataset.nav === aktif));
        };

        window.addEventListener('scroll', saatGulir, { passive: true });
        saatGulir();

        // Menu layar kecil
        const menu = document.getElementById('mobileMenu');
        const tombolMenu = document.getElementById('menuBtn');
        const bukaMenu = () => {
            menu.classList.remove('hidden'); menu.classList.add('flex');
            tombolMenu.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        };
        const tutupMenu = () => {
            menu.classList.add('hidden'); menu.classList.remove('flex');
            tombolMenu.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        };
        tombolMenu.addEventListener('click', bukaMenu);
        document.getElementById('menuClose').addEventListener('click', tutupMenu);
        menu.querySelectorAll('[data-close]').forEach(a => a.addEventListener('click', tutupMenu));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') tutupMenu(); });

        /* ---------- Penukar bahasa ----------
           Panel dibuka dan ditutup dari sini, bukan lewat :focus-within, sebab
           tautan di dalamnya harus tetap dapat diklik dengan tetikus sekaligus
           dijangkau dengan papan ketik. Isi panel sudah tercetak lengkap di
           HTML, jadi tanpa JavaScript pun kedua bahasanya tetap terjangkau. */
        (() => {
            const wadah = document.querySelector('[data-penukar-bahasa]');
            if (!wadah) return;

            const tombol = wadah.querySelector('#tombolBahasa');
            const panel = wadah.querySelector('#menuBahasa');
            const panah = wadah.querySelector('[data-panah]');

            const setel = (terbuka) => {
                panel.classList.toggle('is-open', terbuka);
                tombol.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
                if (panah) panah.style.transform = terbuka ? 'rotate(180deg)' : '';
            };

            tombol.addEventListener('click', (e) => {
                e.stopPropagation();
                setel(!panel.classList.contains('is-open'));
            });

            // Klik di luar wadah menutup panel; klik di dalamnya tidak, supaya
            // tautan bahasa sempat dijalankan lebih dulu.
            document.addEventListener('click', (e) => {
                if (!wadah.contains(e.target)) setel(false);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                setel(false);
                tombol.focus();
            });
        })();
    </script>
</body>
</html>
