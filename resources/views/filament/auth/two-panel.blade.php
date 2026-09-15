{{--
    Tata letak halaman masuk dua sisi (Instruksi §28).

    Membungkus layout.base resmi Filament agar Livewire, notifikasi, skrip, dan
    seluruh mekanisme autentikasi bawaan tetap berjalan tanpa perubahan.

    Catatan penting: setiap warna di sini wajib memiliki padanan mode gelap.
    Sebelumnya panel kanan dipaku `bg-white` tanpa padanan tersebut, sehingga
    ketika skema warna perangkat pengguna gelap, Filament mewarnai label
    formulir menjadi putih di atas panel yang tetap putih dan label "Alamat
    Email" serta "Kata Sandi" tidak terbaca sama sekali.

    Sisi kiri sengaja dibiarkan lapang: hanya lambang, judul sistem, satu
    kalimat, dan baris satuan kerja. Sebelumnya panel ini memuat tiga kartu
    sorotan, tiga bentuk geometris yang hanyut, dan paralaks mengikuti
    penunjuk sekaligus; hasilnya ramai dan menarik perhatian dari satu-satunya
    tindakan di halaman ini, yaitu mengisi dua kolom di sebelah kanan. Yang
    tersisa kini hanya kisi tipis dan cahaya lembut, keduanya dari CSS tanpa
    gambar latar.
--}}
@php
    $livewire ??= null;
    $logoBps = asset('images/logo-bps.png');

    // Satu-satunya jalan keluar pengguna yang terkunci: SIMPBI tidak
    // menyediakan pemulihan kata sandi mandiri karena akun diberikan
    // Administrator dan sebagian dipakai bersama satu tim kerja.
    $tautanBantuan = \App\Support\KontakBantuan::tautanWhatsApp();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">

    {{-- ---------- Layar pemuatan berlambang SIMPBI ---------- --}}
    @include('filament.partials.pemuatan')

    <div class="fi-simpbi-login grid bg-white dark:bg-gray-900 md:grid-cols-[0.9fr_1fr] lg:grid-cols-[1.05fr_1fr]">

        {{-- ================= Sisi kiri: identitas ================= --}}
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-navy p-8 text-white md:flex lg:p-10 xl:p-14"
        >
            {{-- Cahaya lembut, satu-satunya kedalaman pada panel ini --}}
            <div
                class="pointer-events-none absolute inset-0"
                style="background:
                    radial-gradient(42rem 24rem at 88% -12%, rgba(245,158,11,.14), transparent 62%),
                    radial-gradient(38rem 26rem at -12% 108%, rgba(21,87,166,.5), transparent 58%);"
                aria-hidden="true"
            ></div>

            {{-- Kisi tipis --}}
            <div class="fi-simpbi-login-kisi pointer-events-none absolute inset-0" aria-hidden="true"></div>

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
            {{--
                Isi sisi kanan muncul berurutan dari atas ke bawah: sapaan,
                keterangan, kolom isian, lalu keterangan kaki. Urutannya
                menuntun mata ke tempat mengetik, bukan sekadar hiasan, dan
                jeda antarunsurnya ditulis pada --tunda masing-masing.
            --}}
            <div class="fi-simpbi-login-tampil w-full max-w-[22rem] sm:max-w-sm">

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

                <h1 style="--tunda: 60ms" class="text-[1.75rem] font-bold leading-tight tracking-tight text-navy dark:text-white text-center">
                    {{ $livewire->getHeading() }}
                </h1>

                @if ($subheading = $livewire->getSubheading())
                    <p style="--tunda: 120ms" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400 text-center">
                        {{ $subheading }}
                    </p>
                @endif

                <div style="--tunda: 180ms" class="mt-9">
                    {{ $slot }}
                </div>

                {{--
                    Kaki halaman. Kalimat pertama tetap abu-abu samar karena
                    sekadar keterangan; hanya frasa kontaknya yang diberi warna
                    dan garis bawah, supaya blok ini tidak terbaca seperti satu
                    bidang yang seluruhnya dapat diklik.

                    Warnanya biru primer, bukan hijau khas WhatsApp: Instruksi
                    §25 memberi arti pada tiap warna, dan hijau di SIMPBI sudah
                    berarti "selesai/aman" sehingga tautan bantuan berwarna hijau
                    menyampaikan pesan yang keliru. Biru justru definisinya,
                    yaitu tindakan utama dan informasi.

                    Garis bawahnya menyala terus, tidak hanya saat disorot,
                    sebab di ponsel tidak ada penunjuk yang dapat menyorot dan
                    tautannya harus tetap dikenali sebagai tautan.
                --}}
                <div style="--tunda: 360ms" class="mt-10 border-t border-gray-100 pt-5 text-center text-xs leading-relaxed dark:border-gray-800">
                    <p class="text-gray-400 dark:text-gray-500">
                        Akun diberikan oleh Administrator Sistem.
                    </p>

                    @if ($tautanBantuan)
                        <p class="mt-1 text-gray-400 dark:text-gray-500">
                            Mengalami kendala masuk?
                            <a
                                href="{{ $tautanBantuan }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-primary-600 underline decoration-primary-600/40 underline-offset-2 transition hover:text-navy hover:decoration-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 dark:text-primary-400 dark:decoration-primary-400/40 dark:hover:text-primary-300"
                            >Hubungi Sub-Bagian Umum</a>
                        </p>
                    @else
                        {{-- Nomor belum diisi Administrator; kalimatnya kembali
                             seperti semula, bukan tautan yang tidak menuju
                             ke mana-mana. --}}
                        <p class="mt-1 text-gray-400 dark:text-gray-500">
                            Hubungi Sub-Bagian Umum bila mengalami kendala masuk.
                        </p>
                    @endif
                </div>
            </div>
        </main>
    </div>

    @livewire(\Filament\Livewire\Notifications::class)
</x-filament-panels::layout.base>
