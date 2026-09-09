{{--
    Tata letak halaman masuk dua sisi (Instruksi §28).

    Membungkus layout.base resmi Filament agar Livewire, notifikasi, skrip, dan
    seluruh mekanisme autentikasi bawaan tetap berjalan tanpa perubahan.

    Catatan penting: setiap warna di sini wajib memiliki padanan mode gelap.
    Sebelumnya panel kanan dipaku `bg-white` tanpa padanan tersebut, sehingga
    ketika skema warna perangkat pengguna gelap, Filament mewarnai label
    formulir menjadi putih di atas panel yang tetap putih dan label "Alamat
    Email" serta "Kata Sandi" tidak terbaca sama sekali.

    Seluruh dekorasi pada sisi kiri dibentuk dari CSS — kisi, cahaya lembut,
    dan bentuk geometris — tanpa gambar latar, dan hanya bergerak lewat
    transform/opacity supaya tidak memicu perhitungan ulang tata letak.
--}}
@php
    $livewire ??= null;
    $logoBps = asset('images/logo-bps.png');

    // Tiga hal yang paling sering ditanyakan pengguna baru, ditampilkan
    // ringkas sebagai isi panel kiri agar areanya tidak terasa kosong.
    $sorotan = [
        ['ikon' => 'heroicon-o-clipboard-document-check', 'judul' => 'Permintaan Terlacak',  'teks' => 'Setiap tahap persetujuan tercatat dan dapat ditelusuri.'],
        ['ikon' => 'heroicon-o-archive-box',              'judul' => 'Stok Selalu Mutakhir', 'teks' => 'Ketersediaan barang terkunci otomatis saat diminta.'],
        ['ikon' => 'heroicon-o-document-check',           'judul' => 'Dokumen Sah',          'teks' => 'Bukti permintaan terbit lengkap dengan kode verifikasi.'],
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">

    {{-- ---------- Layar pemuatan berlambang SIMPBI ---------- --}}
    @include('filament.partials.pemuatan')

    <div class="fi-simpbi-login grid bg-white dark:bg-gray-900 md:grid-cols-[0.9fr_1fr] lg:grid-cols-[1.05fr_1fr]">

        {{-- ================= Sisi kiri: identitas ================= --}}
        <aside
            id="panelIdentitas"
            class="relative hidden flex-col justify-between overflow-hidden bg-navy p-8 text-white md:flex lg:p-10 xl:p-14"
        >
            {{-- Cahaya lembut --}}
            <div
                class="fi-simpbi-login-lapisan pointer-events-none absolute inset-0"
                style="--kedalaman: .6; background:
                    radial-gradient(42rem 24rem at 88% -12%, rgba(245,158,11,.18), transparent 62%),
                    radial-gradient(38rem 26rem at -12% 108%, rgba(21,87,166,.6), transparent 58%);"
                aria-hidden="true"
            ></div>

            {{-- Kisi tipis --}}
            <div class="fi-simpbi-login-kisi fi-simpbi-login-lapisan pointer-events-none absolute inset-0"
                 style="--kedalaman: .35" aria-hidden="true"></div>

            {{-- Bentuk geometris yang hanyut sangat perlahan --}}
            <div class="fi-simpbi-login-lapisan pointer-events-none absolute inset-0" style="--kedalaman: 1" aria-hidden="true">
                <span class="fi-simpbi-login-bentuk fi-simpbi-login-bentuk-1"></span>
                <span class="fi-simpbi-login-bentuk fi-simpbi-login-bentuk-2"></span>
                <span class="fi-simpbi-login-bentuk fi-simpbi-login-bentuk-3"></span>
            </div>

            {{-- Lambang: BPS + SIMPBI + satuan kerja --}}
            <a href="{{ url('/') }}" class="fi-simpbi-login-merek relative flex w-fit items-center gap-3.5 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-white/40">
                <span class="fi-simpbi-login-merek-tanda grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-white p-2 shadow-lg shadow-black/20">
                    <img src="{{ $logoBps }}" alt="Logo Badan Pusat Statistik" class="h-full w-full object-contain">
                </span>

                <span class="h-9 w-px bg-white/20" aria-hidden="true"></span>

                <span class="leading-tight">
                    <span class="block text-lg font-bold tracking-tight">SIMPBI</span>
                    <span class="block text-xs text-white/65">BPS Kota Jakarta Barat</span>
                </span>
            </a>

            {{-- Judul dan penjelasan sistem --}}
            <div class="relative max-w-xl">
                <span class="fi-simpbi-login-lencana mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[0.7rem] font-medium uppercase tracking-wider text-white/75">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    Sistem Internal
                </span>

                <h2 class="text-[1.4rem] font-bold leading-[1.22] tracking-tight lg:text-[1.75rem] xl:text-[2.05rem]">
                    Sistem Informasi Manajemen<br>
                    <span class="text-white/90">Permintaan Barang dan Inventaris</span>
                </h2>

                <p class="mt-4 max-w-md text-sm leading-relaxed text-white/60">
                    Kelola permintaan, ketersediaan persediaan, dan distribusi barang dalam satu
                    proses yang terintegrasi dan dapat ditelusuri.
                </p>

                {{-- Sorotan singkat, memberi isi pada panel tanpa membuatnya ramai --}}
                <ul class="mt-8 hidden space-y-2.5 lg:block">
                    @foreach ($sorotan as $s)
                        <li class="fi-simpbi-login-sorotan flex items-start gap-3.5 p-3">
                            <span class="fi-simpbi-login-sorotan-ikon mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/10 ring-1 ring-inset ring-white/15">
                                <x-filament::icon :icon="$s['ikon']" class="h-[1.15rem] w-[1.15rem] text-white/85" />
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-white/95">{{ $s['judul'] }}</span>
                                <span class="block text-xs leading-relaxed text-white/55">{{ $s['teks'] }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative text-xs text-white/45">
                Sub-Bagian Umum &middot; Badan Pusat Statistik Kota Jakarta Barat
            </p>
        </aside>

        {{-- ================= Sisi kanan: formulir ================= --}}
        <main
            id="fi-main-content"
            tabindex="-1"
            class="flex min-h-[100dvh] flex-col items-center justify-center bg-white px-6 py-12 dark:bg-gray-900 sm:px-10"
        >
            <div class="w-full max-w-[22rem] sm:max-w-sm">

                {{-- Lambang untuk layar kecil, ketika sisi kiri disembunyikan --}}
                <a href="{{ url('/') }}" class="fi-simpbi-login-merek mb-9 flex w-fit items-center gap-3 rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-brand/40 md:hidden">
                    <span class="fi-simpbi-login-merek-tanda grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-navy p-2">
                        <img src="{{ $logoBps }}" alt="Logo Badan Pusat Statistik" class="h-full w-full object-contain">
                    </span>
                    <span class="h-8 w-px bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                    <span class="leading-tight">
                        <span class="block text-base font-bold tracking-tight text-navy dark:text-white">SIMPBI</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">BPS Kota Jakarta Barat</span>
                    </span>
                </a>

                <h1 class="text-[1.75rem] font-bold leading-tight tracking-tight text-navy dark:text-white">
                    {{ $livewire->getHeading() }}
                </h1>

                @if ($subheading = $livewire->getSubheading())
                    <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ $subheading }}
                    </p>
                @endif

                <div class="mt-9">
                    {{ $slot }}
                </div>

                <p class="mt-10 border-t border-gray-100 pt-5 text-center text-xs leading-relaxed text-gray-400 dark:border-gray-800 dark:text-gray-500">
                    Akun diberikan oleh Administrator Sistem.<br class="sm:hidden">
                    Hubungi Sub-Bagian Umum bila mengalami kendala masuk.
                </p>
            </div>
        </main>
    </div>

    {{-- Paralaks halus mengikuti penunjuk.
         Hanya berjalan pada perangkat berpenunjuk presisi dan ketika pengguna
         tidak meminta pengurangan gerak. Nilainya sangat kecil dan hanya
         mengenai lapisan dekorasi, bukan teks. --}}
    <script>
        (() => {
            const panel = document.getElementById('panelIdentitas');
            if (! panel) return;

            const bolehBergerak = window.matchMedia('(pointer: fine)').matches
                && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (! bolehBergerak) return;

            let menunggu = false;

            panel.addEventListener('pointermove', (e) => {
                if (menunggu) return;
                menunggu = true;

                requestAnimationFrame(() => {
                    const kotak = panel.getBoundingClientRect();
                    const x = (e.clientX - kotak.left) / kotak.width - 0.5;
                    const y = (e.clientY - kotak.top) / kotak.height - 0.5;

                    // Pergeseran maksimal hanya beberapa piksel
                    panel.style.setProperty('--px', (x * 14).toFixed(2) + 'px');
                    panel.style.setProperty('--py', (y * 14).toFixed(2) + 'px');
                    menunggu = false;
                });
            });

            panel.addEventListener('pointerleave', () => {
                panel.style.setProperty('--px', '0px');
                panel.style.setProperty('--py', '0px');
            });
        })();
    </script>

    @livewire(\Filament\Livewire\Notifications::class)
</x-filament-panels::layout.base>
