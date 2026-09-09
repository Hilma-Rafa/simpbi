<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIMPBI — Sistem Informasi Manajemen Permintaan Barang dan Inventaris</title>
    <meta name="description"
        content="SIMPBI — Sistem Informasi Manajemen Permintaan Barang dan Inventaris. Sub-Bagian Umum, Badan Pusat Statistik Kota Jakarta Barat.">

    <link rel="icon" href="{{ asset('images/logo-bps.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Dua keluarga huruf saja: Plus Jakarta Sans untuk judul, Inter untuk teks
         dan antarmuka. Keduanya grotesk modern yang bersih dan tidak dekoratif;
         Plus Jakarta Sans dipilih karena karakternya sedikit lebih tegas pada
         ukuran besar, sekaligus selaras dengan identitas kota tempat satuan
         kerja ini berada. Panel aplikasi tetap sepenuhnya memakai Inter. --}}
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|plus-jakarta-sans:600,700,800" rel="stylesheet" />

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
        }

        .font-display {
            font-family: var(--font-display);
            font-feature-settings: 'ss01' 1;
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
        }
    </style>
</head>
<body class="bg-surface text-ink antialiased" style="font-family: var(--font-sans)">

    {{-- ============================ NAVBAR (pill mengambang) ============================
         Susunannya mengalir dari kiri ke kanan: lambang, lalu menu tepat di
         sebelahnya, ruang lentur, dan tombol aksi di ujung kanan. Menu sengaja
         tidak dipusatkan secara matematis agar jarak antar item tetap rapat dan
         konsisten, bukan direnggangkan hanya untuk memenuhi lebar navbar. --}}
    <header class="fixed inset-x-0 top-0 z-40 px-4">
        <nav id="nav"
            class="mx-auto mt-3.5 flex h-14 max-w-5xl items-center gap-1.5 rounded-full border border-white/10 bg-navy/95 py-2 pl-2.5 pr-2 backdrop-blur-md transition-[box-shadow,background-color] duration-300 ease-[cubic-bezier(.22,.61,.36,1)]">

            {{-- Lambang --}}
            <a href="#beranda" class="flex shrink-0 items-center gap-2.5 rounded-full pr-1.5">
                <span class="grid h-9 w-9 place-items-center rounded-full bg-white p-1.5">
                    <img src="{{ asset('images/logo-bps.png') }}" alt="Logo BPS" class="h-full w-full object-contain">
                </span>
                <span class="font-display text-[15px] font-bold tracking-tight text-white">SIMPBI</span>
            </a>

            <span class="hidden h-5 w-px shrink-0 bg-white/12 md:block" aria-hidden="true"></span>

            {{-- Menu, langsung menempel setelah lambang --}}
            <div class="hidden items-center gap-0.5 md:flex">
                <a href="#beranda" data-nav="beranda"
                    class="nav-link inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-[13.5px] font-medium text-white/65 transition-colors duration-200 hover:text-white">
                    <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 10.5 12 3l9 7.5M5.5 9.5V20a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V9.5" />
                    </svg>
                    Beranda
                </a>

                @foreach (['Tentang' => 'tentang', 'Fitur' => 'fitur', 'Alur' => 'alur', 'Verifikasi' => 'verifikasi'] as $label => $anchor)
                    <a href="#{{ $anchor }}" data-nav="{{ $anchor }}"
                        class="nav-link rounded-full px-3 py-2 text-[13.5px] font-medium text-white/65 transition-colors duration-200 hover:text-white">{{ $label }}</a>
                @endforeach
            </div>

            {{-- Ruang lentur: satu-satunya tempat sisa lebar diserap --}}
            <span class="flex-1" aria-hidden="true"></span>

            {{-- Aksi utama --}}
            <a href="{{ url('/admin') }}"
                class="group hidden shrink-0 items-center gap-2 rounded-full bg-accent py-1.5 pl-4 pr-1.5 text-[13.5px] font-semibold text-navy transition-[background-color,transform,box-shadow] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] hover:bg-accent/90 hover:shadow-[0_6px_18px_-6px_rgba(245,158,11,.7)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:scale-[.98] sm:flex">
                Masuk ke Sistem
                <span
                    class="grid h-7 w-7 place-items-center rounded-full bg-navy/10 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:translate-x-0.5">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
            </a>

            <button id="menuBtn" class="grid h-10 w-10 shrink-0 place-items-center rounded-full text-white transition-colors duration-200 hover:bg-white/10 md:hidden"
                aria-label="Buka menu" aria-expanded="false">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                    stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>
        </nav>
    </header>

    {{-- Menu untuk layar kecil --}}
    <div id="mobileMenu"
        class="fixed inset-0 z-50 hidden flex-col bg-navy/95 px-6 pt-24 backdrop-blur-xl md:hidden">
        <button id="menuClose" class="absolute right-6 top-7 grid h-10 w-10 place-items-center rounded-full text-white"
            aria-label="Tutup menu">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
        <nav class="flex flex-col gap-1">
            @foreach (['Beranda' => 'beranda', 'Tentang' => 'tentang', 'Fitur' => 'fitur', 'Alur' => 'alur', 'Verifikasi' => 'verifikasi'] as $label => $anchor)
                <a href="#{{ $anchor }}" data-close
                    class="font-display border-b border-white/10 py-4 text-2xl font-semibold text-white/85 transition-colors duration-200 hover:text-white">{{ $label }}</a>
            @endforeach
            <a href="{{ url('/admin') }}"
                class="mt-6 flex items-center justify-center gap-2 rounded-full bg-accent py-3.5 text-sm font-semibold text-navy">
                Masuk ke Sistem
            </a>
        </nav>
    </div>

    {{-- ============================ HERO ============================ --}}
    @php
        // Judul diungkap per kata, baris demi baris. Jeda dihitung di sini agar
        // urutannya terbaca sekali pandang dan mudah disetel.
        $barisJudul = [
            0 => 'Sistem Informasi Manajemen Permintaan Barang dan Inventaris',
        ];
        $jedaKata = 55;   // jarak antar kata
        $jedaBaris = 180; // jeda tambahan sebelum baris kedua
        $mulaiJudul = 340;
    @endphp

    <section id="beranda" class="relative overflow-hidden bg-navy px-4 pb-20 pt-32 text-white md:pb-24 md:pt-40">
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

        <div class="relative mx-auto max-w-3xl text-center">

            {{-- Badge --}}
            <span class="hero-in inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-[11px] font-medium uppercase tracking-[0.18em] text-white/75"
                style="--d: 60ms">
                <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                Sistem Internal<span class="hidden sm:inline"> · BPS Kota Jakarta Barat</span>
            </span>

            {{-- Nama sistem --}}
            <h1 class="hero-in font-display mt-6 text-5xl font-extrabold leading-[1.02] tracking-[-0.03em] sm:text-6xl md:text-7xl"
                style="--d: 150ms">
                SIMPBI
            </h1>

            {{-- Kepanjangan nama, diungkap per kata dalam dua baris --}}
            <p class="font-display mx-auto mt-4 max-w-2xl text-[1.0625rem] font-semibold leading-snug tracking-[-0.01em] text-white/90 sm:text-xl md:text-[1.375rem]">
                @foreach ($barisJudul as $indeksBaris => $baris)
                    <span class="block">
                        @foreach (explode(' ', $baris) as $indeksKata => $kata)
                            <span class="word-in"
                                style="--d: {{ $mulaiJudul + ($indeksBaris * $jedaBaris) + ($indeksKata * $jedaKata) }}ms">{{ $kata }}</span>{{ ' ' }}
                        @endforeach
                    </span>
                @endforeach
            </p>

            {{-- Penjelasan singkat --}}
            <p class="hero-in mx-auto mt-5 max-w-xl text-[15px] leading-relaxed text-white/60" style="--d: 900ms">
                Kelola permintaan, ketersediaan persediaan, dan distribusi barang dalam satu proses
                yang terintegrasi dan dapat ditelusuri.
            </p>

            {{-- Aksi --}}
            <div class="hero-in mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row" style="--d: 1000ms">
                <a href="{{ url('/admin') }}"
                    class="group flex w-full items-center justify-center gap-2 rounded-full bg-accent py-3 pl-6 pr-3 text-sm font-semibold text-navy transition-[background-color,transform,box-shadow] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] hover:bg-accent/90 hover:shadow-[0_10px_28px_-10px_rgba(245,158,11,.75)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:scale-[.98] sm:w-auto">
                    Masuk ke Sistem
                    <span
                        class="grid h-8 w-8 place-items-center rounded-full bg-navy/10 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:translate-x-1">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                    </span>
                </a>
                <a href="#alur"
                    class="group flex w-full items-center justify-center gap-2 rounded-full border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white transition-[background-color,border-color,transform] duration-300 ease-[cubic-bezier(.22,.61,.36,1)] hover:border-white/25 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/60 active:scale-[.98] sm:w-auto">
                    Lihat Alur
                    <svg class="h-4 w-4 transition-transform duration-300 ease-[cubic-bezier(.22,.61,.36,1)] group-hover:translate-y-0.5"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M12 5v14M6 13l6 6 6-6" />
                    </svg>
                </a>
            </div>

            {{-- Satuan kerja --}}
            <div class="hero-in mt-8 flex items-center justify-center gap-3 text-[13px] text-white/45" style="--d: 1080ms">
                <span class="hidden h-px w-8 bg-white/15 sm:block"></span>
                <span>Sub-Bagian Umum · Badan Pusat Statistik Kota Jakarta Barat</span>
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
    </section>

    {{-- ============================ TENTANG / MENGAPA ============================ --}}
    <section id="tentang" class="scroll-mt-24 px-4 py-24 md:py-32">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">Mengapa SIMPBI</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    Satu proses yang jelas, dari permintaan hingga pengesahan.
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-muted">
                    Menggantikan pencatatan manual yang tersebar dengan alur kerja yang terstandar,
                    dapat dipantau, dan dapat ditelusuri kembali.
                </p>
            </div>

            <div class="mt-12 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['Stok lebih mudah dipantau', 'Informasi stok fisik, HOLD, dan tersedia dapat dilihat dengan lebih terstruktur.', 'M3 13h2l1.5 6h11L21 8H6M9 21a1 1 0 100-2 1 1 0 000 2m8 0a1 1 0 100-2 1 1 0 000 2'],
                    ['Permintaan lebih terstandar', 'Katalog menjadi acuan barang yang digunakan dalam setiap pengajuan.', 'M4 6h16M4 12h16M4 18h10'],
                    ['Proses lebih mudah ditelusuri', 'Setiap permintaan memiliki status dan riwayat proses yang tercatat.', 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                ] as $i => $c)
                    <div data-reveal style="transition-delay: {{ $i * 90 }}ms"
                        class="rounded-card border border-hairline bg-card p-1.5 shadow-[0_1px_2px_rgba(15,23,42,.04)]">
                        <div class="rounded-[10px] bg-surface/60 p-6">
                            <span class="grid h-11 w-11 place-items-center rounded-xl bg-brand-light text-brand">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="{{ $c[2] }}" /></svg>
                            </span>
                            <h3 class="mt-5 text-base font-semibold text-navy">{{ $c[0] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-muted">{{ $c[1] }}</p>
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
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">Fitur</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    Dirancang untuk pekerjaan harian unit kerja.
                </h2>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Katalog Barang', 'Daftar barang persediaan terstandar sebagai acuan pengajuan.', 'M4 6a2 2 0 012-2h9l5 5v9a2 2 0 01-2 2H6a2 2 0 01-2-2zM14 4v5h5'],
                    ['Permintaan & Persetujuan', 'Alur pengajuan berjenjang dengan persetujuan sesuai kewenangan.', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['Pengendalian Stok', 'Mekanisme HOLD, RELEASE, dan konversi menjaga stok tetap akurat.', 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
                    ['Monitoring & Laporan', 'Dashboard per peran dan laporan yang dapat diekspor.', 'M4 19h16M7 16V8m5 8V5m5 11v-6'],
                    ['Pencatatan Mutasi Aset', 'Penempatan, redistribusi, dan mutasi aset tetap peralatan & mesin.', 'M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4'],
                    ['BAST + Verifikasi QR', 'Berita acara serah terima dengan tanda tangan digital dan QR.', 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z'],
                ] as $i => $f)
                    <div data-reveal style="transition-delay: {{ ($i % 3) * 90 }}ms"
                        class="group rounded-card border border-hairline bg-card p-6 transition-all duration-500 ease-[cubic-bezier(.32,.72,0,1)] hover:-translate-y-1 hover:border-brand/30 hover:shadow-[0_12px_30px_-12px_rgba(21,87,166,.25)]">
                        <span
                            class="grid h-11 w-11 place-items-center rounded-xl bg-navy text-white transition-colors duration-500 group-hover:bg-brand">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $f[2] }}" /></svg>
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-navy">{{ $f[0] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-muted">{{ $f[1] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================ ALUR ============================ --}}
    <section id="alur" class="scroll-mt-24 px-4 py-24 md:py-32">
        <div class="mx-auto max-w-6xl">
            <div class="max-w-2xl" data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand">Alur Permintaan</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-navy sm:text-4xl">
                    Tujuh tahap, satu jejak yang jelas.
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-muted">
                    Untuk pengaju berperan <span class="font-semibold text-navy">Ketua Tim</span>, tahap persetujuan
                    Ketua dilewati dan permintaan langsung menuju verifikasi gudang.
                </p>
            </div>

            <ol class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    'Pengajuan', 'Persetujuan Ketua', 'Verifikasi Gudang', 'Persetujuan Kasubbag',
                    'Penyiapan', 'Penerimaan', 'Pengesahan',
                ] as $i => $step)
                    <li data-reveal style="transition-delay: {{ ($i % 4) * 70 }}ms"
                        class="relative flex items-start gap-3 rounded-card border border-hairline bg-card p-5">
                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-light text-sm font-bold text-brand">{{ $i + 1 }}</span>
                        <div>
                            <p class="text-sm font-semibold text-navy">{{ $step }}</p>
                            @if ($step === 'Persetujuan Ketua')
                                <p class="mt-1 text-[11px] font-medium text-accent">Dilewati untuk pengaju Ketua Tim</p>
                            @endif
                        </div>
                    </li>
                @endforeach
                <li data-reveal
                    class="flex items-center justify-center rounded-card border border-dashed border-brand/30 bg-brand-light/50 p-5 text-center text-sm font-semibold text-brand">
                    Selesai & terarsip
                </li>
            </ol>
        </div>
    </section>

    {{-- ============================ VERIFIKASI QR ============================ --}}
    <section id="verifikasi" class="scroll-mt-24 bg-navy px-4 py-24 text-white md:py-32">
        <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2">
            <div data-reveal>
                <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-accent">Verifikasi Dokumen</span>
                <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    Setiap dokumen resmi dapat dipindai dan diperiksa keasliannya.
                </h2>
                <p class="mt-4 text-[15px] leading-relaxed text-white/70">
                    Bukti permintaan dan BAST mutasi aset dilengkapi kode QR. Pemindaian mengarah ke halaman
                    verifikasi yang menampilkan status keaslian dokumen tanpa membuka data sensitif.
                </p>
                <div class="mt-7 flex flex-wrap gap-3 text-sm text-white/75">
                    @foreach (['Nomor & jenis dokumen', 'Tanggal pengesahan', 'Status keaslian'] as $item)
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
                                BADAN PUSAT STATISTIK<br><span class="text-muted">Kota Jakarta Barat</span>
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-accent-light px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-warning">Demo</span>
                    </div>

                    <div class="mt-6 grid grid-cols-[auto_1fr] items-center gap-5">
                        <div class="grid h-24 w-24 place-items-center rounded-xl border border-hairline bg-surface">
                            <svg class="h-16 w-16 text-navy" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 3h6v6H3zm2 2v2h2V5zM15 3h6v6h-6zm2 2v2h2V5zM3 15h6v6H3zm2 2v2h2v-2zM15 15h2v2h-2zm4 0h2v2h-2zm-4 4h2v2h-2zm4 0h2v2h-2z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-muted">Bukti Permintaan Barang</p>
                            <p class="mt-1 font-semibold text-navy">PB-2026-0001</p>
                            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-success/10 px-2.5 py-1 text-xs font-semibold text-success">
                                <span class="h-1.5 w-1.5 rounded-full bg-success"></span> Dokumen sah
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-lg bg-surface px-3.5 py-2.5 font-mono text-[11px] text-muted">
                        Token: SIMPBI-DEMO-2026-0001
                    </div>
                    <p class="mt-2 text-[11px] text-muted">Contoh tampilan. Bukan dokumen resmi.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ CTA ============================ --}}
    <section class="px-4 py-24">
        <div data-reveal
            class="mx-auto max-w-5xl overflow-hidden rounded-hero border border-hairline bg-white p-10 text-center shadow-[0_20px_50px_-24px_rgba(11,42,91,.25)] md:p-16">
            <h2 class="text-3xl font-bold tracking-tight text-navy sm:text-4xl">Masuk untuk mulai bekerja.</h2>
            <p class="mx-auto mt-4 max-w-xl text-[15px] leading-relaxed text-muted">
                Akses SIMPBI menggunakan akun yang telah diberikan oleh administrator sistem.
            </p>
            <a href="{{ url('/admin') }}"
                class="group mt-8 inline-flex items-center gap-2 rounded-full bg-navy py-3.5 pl-6 pr-3 text-sm font-semibold text-white transition-all duration-500 ease-[cubic-bezier(.32,.72,0,1)] hover:bg-brand active:scale-[.98]">
                Masuk ke Sistem
                <span
                    class="grid h-8 w-8 place-items-center rounded-full bg-white/15 transition-transform duration-500 ease-[cubic-bezier(.32,.72,0,1)] group-hover:translate-x-1 group-hover:-translate-y-0.5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
            </a>
        </div>
    </section>

    {{-- ============================ FOOTER ============================ --}}
    <footer class="border-t border-hairline bg-white px-4 py-12">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-6 sm:flex-row">
            <div class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded-lg bg-navy p-1.5">
                    <img src="{{ asset('images/logo-bps.png') }}" alt="Logo BPS" class="h-full w-full object-contain">
                </span>
                <div class="text-sm leading-tight">
                    <p class="font-bold text-navy">SIMPBI</p>
                    <p class="text-muted">Sub-Bagian Umum · BPS Kota Jakarta Barat</p>
                </div>
            </div>
            <p class="text-xs text-muted">© {{ date('Y') }} Badan Pusat Statistik Kota Jakarta Barat. Sistem internal.</p>
        </div>
    </footer>

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
    </script>
</body>
</html>
