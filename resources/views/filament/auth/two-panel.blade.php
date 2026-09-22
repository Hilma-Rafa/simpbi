{{--
    Tata letak halaman masuk dua sisi (Instruksi §28).

    Membungkus layout.base resmi Filament agar Livewire, notifikasi, skrip, dan
    seluruh mekanisme autentikasi bawaan tetap berjalan tanpa perubahan.

    Catatan penting: setiap warna di sini wajib memiliki padanan mode gelap.
    Sebelumnya panel kanan dipaku `bg-white` tanpa padanan tersebut, sehingga
    ketika skema warna perangkat pengguna gelap, Filament mewarnai label
    formulir menjadi putih di atas panel yang tetap putih dan label "Alamat
    Email" serta "Kata Sandi" tidak terbaca sama sekali.

    Bingkai halaman (lebar ≥1024px): sisi kiri menjadi kartu navy yang menjorok
    16px dari tepi layar, sedangkan sisi kanan bukan kartu terpisah, melainkan
    latar halaman itu sendiri dengan formulir di tengahnya. Di bawah 1024px
    bingkai dilepas dan sisi kiri menyusut menjadi pita navy di atas formulir
    yang hanya memuat lambang dan nama sistem; semua yang bersifat hiasan
    disembunyikan. Aturannya ada pada berkas tema (`.fi-simpbi-login-*`).

    Sisi kiri sengaja dibiarkan lapang: lambang, judul sistem, satu kalimat,
    ilustrasi alur permintaan, dan baris satuan kerja. Ilustrasi alur digerakkan
    sepenuhnya oleh CSS (tanpa JavaScript) dan tidak memuat data nyata.
--}}
@php
    $livewire ??= null;
    $logoBps = asset('images/logo-bps.png');

    // Satu-satunya jalan keluar pengguna yang terkunci: SIMPBI tidak
    // menyediakan pemulihan kata sandi mandiri karena akun diberikan
    // Administrator dan sebagian dipakai bersama satu tim kerja.
    $tautanBantuan = \App\Support\KontakBantuan::tautanWhatsApp();

    // Lima tahap alur permintaan, mengikuti fase pada Instruksi §10–§13.
    // Hanya ilustrasi: tidak ada angka, nama, atau nomor permintaan nyata.
    $tahapAlur = [
        'Persetujuan Ketua Tim',
        'Verifikasi stok fisik',
        'Persetujuan akhir Kasubbag',
        'Penyiapan barang',
        'Pengambilan oleh pemohon',
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">

    {{-- ---------- Layar pemuatan berlambang SIMPBI ---------- --}}
    @include('filament.partials.pemuatan')

    <div class="fi-simpbi-login bg-white dark:bg-gray-900">

        {{-- ================= Sisi kiri: identitas ================= --}}
        <aside class="fi-simpbi-login-panel relative overflow-hidden bg-navy text-white">

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

            {{-- Hiasan: kelompok berlian 3×3, datar dan sangat samar --}}
            <div class="fi-simpbi-login-berlian" aria-hidden="true">
                @for ($i = 0; $i < 9; $i++)
                    <span></span>
                @endfor
            </div>

            {{-- Lambang: BPS + SIMPBI + satuan kerja. Di bawah 1024px inilah
                 satu-satunya isi pita navy di atas formulir. --}}
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
            <div class="fi-simpbi-login-narasi relative max-w-xl">
                <span class="fi-simpbi-login-lencana mb-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[0.7rem] font-medium uppercase tracking-wider text-white/75">
                    <span class="h-1.5 w-1.5 rounded-full bg-accent"></span>
                    Sistem Internal
                </span>

                <h2 class="text-[1.4rem] font-bold leading-[1.22] tracking-tight lg:text-[1.75rem] xl:text-[2.05rem]">
                    Sistem Informasi Manajemen<br>
                    <span class="text-white/90">Permintaan Barang dan Inventaris</span>
                </h2>

                <p class="fi-simpbi-login-uraian mt-4 max-w-md text-sm leading-relaxed text-white/60">
                    Kelola permintaan, ketersediaan persediaan, dan distribusi barang dalam satu
                    proses yang terintegrasi dan dapat ditelusuri.
                </p>
            </div>

            {{-- ---------- Ilustrasi alur permintaan ----------
                 Murni hiasan (bukan data nyata) sehingga disembunyikan dari
                 pembaca layar; kalimat pengganti untuknya ada pada sr-only.
                 Lima baris berbagi satu lini masa 14 detik yang seluruhnya
                 didefinisikan di CSS, dan tiap tahap dilapisi tiga keadaan
                 (Menunggu, Diproses, Selesai) yang bergantian lewat opacity
                 pada posisi yang sama, sehingga tidak ada tinggi atau lebar
                 yang berubah. --}}
            <p class="sr-only">
                Ilustrasi alur permintaan barang: {{ implode(', ', $tahapAlur) }}.
            </p>

            <div class="fi-alur relative" aria-hidden="true">
                <p class="fi-alur-keterangan">Ilustrasi alur permintaan</p>

                <div class="fi-alur-siklus">
                    @foreach ($tahapAlur as $nomor => $tahap)
                        <div class="fi-alur-baris">
                            <span class="fi-alur-simpul">
                                <span class="fi-alur-simpul-menunggu">{{ $nomor + 1 }}</span>
                                <span class="fi-alur-simpul-proses">
                                    <i class="fi-alur-cincin"></i>
                                    {{ $nomor + 1 }}
                                </span>
                                <span class="fi-alur-simpul-selesai">
                                    <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                                </span>
                            </span>

                            <span class="fi-alur-label">{{ $tahap }}</span>

                            <span class="fi-alur-status">
                                <span class="fi-alur-status-menunggu"><i></i>Menunggu</span>
                                <span class="fi-alur-status-proses"><i></i>Diproses</span>
                                <span class="fi-alur-status-selesai"><i></i>Selesai</span>
                            </span>

                            <span class="fi-alur-garis"></span>
                        </div>
                    @endforeach
                </div>
            </div>

            <p class="fi-simpbi-login-kaki relative text-xs text-white/45">
                Sub-Bagian Umum &middot; Badan Pusat Statistik Kota Jakarta Barat
            </p>
        </aside>

        {{-- ================= Sisi kanan: formulir ================= --}}
        <main
            id="fi-main-content"
            tabindex="-1"
            class="fi-simpbi-login-utama flex flex-col items-center justify-center bg-white px-6 py-10 dark:bg-gray-900 lg:px-10"
        >
            {{--
                Isi sisi kanan muncul berurutan dari atas ke bawah: ikon,
                sapaan, keterangan, kolom isian, lalu keterangan kaki. Urutannya
                menuntun mata ke tempat mengetik, bukan sekadar hiasan, dan
                jeda antarunsurnya ditulis pada --tunda masing-masing.
            --}}
            <div class="fi-simpbi-login-tampil w-full max-w-[440px]">

                {{-- Kotak ikon di atas judul --}}
                <span class="fi-simpbi-login-ikon mx-auto mb-4 grid h-[52px] w-[52px] place-items-center rounded-2xl border border-gray-200 bg-gray-50 text-navy dark:border-gray-700 dark:bg-white/5 dark:text-white" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-[22px] w-[22px]" />
                </span>

                <h1 style="--tunda: 60ms" class="text-[1.75rem] font-bold leading-tight tracking-tight text-navy dark:text-white text-center">
                    {{ $livewire->getHeading() }}
                </h1>

                @if ($subheading = $livewire->getSubheading())
                    <p style="--tunda: 120ms" class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400 text-center">
                        {{ $subheading }}
                    </p>
                @endif

                <div style="--tunda: 180ms" class="mt-8">
                    {{ $slot }}
                </div>

                {{--
                    Kaki halaman. Kalimat pertama tetap abu-abu samar karena
                    sekadar keterangan. Bila nomor bantuan terisi, tautannya
                    tampil sebagai tombol sekunder (garis tepi, bukan isian)
                    supaya tidak bersaing dengan tombol Masuk.

                    Warnanya biru primer, bukan hijau khas WhatsApp: Instruksi
                    §25 memberi arti pada tiap warna, dan hijau di SIMPBI sudah
                    berarti "selesai/aman" sehingga tombol bantuan berwarna
                    hijau menyampaikan pesan yang keliru.
                --}}
                <div style="--tunda: 360ms" class="mt-8 border-t border-gray-100 pt-5 text-center text-xs leading-relaxed dark:border-gray-800">
                    <p class="text-gray-400 dark:text-gray-500">
                        Akun diberikan oleh Administrator Sistem.
                    </p>

                    @if ($tautanBantuan)
                        <p class="mt-1 text-gray-400 dark:text-gray-500">
                            Mengalami kendala masuk?
                        </p>

                        <a
                            href="{{ $tautanBantuan }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="fi-simpbi-login-bantuan"
                        >Hubungi Sub-Bagian Umum</a>
                    @else
                        {{-- Nomor belum diisi Administrator; kalimatnya kembali
                             seperti semula, bukan tombol yang tidak menuju
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
