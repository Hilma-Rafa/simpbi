<x-filament-panels::page>

    {{--
        Variabel animasi masuk di satu tempat (Instruksi bagian F4). Halaman
        ini saja — tidak menyentuh theme.css global — sebab urutan dan durasi
        di bawah khusus untuk susunan Panduan/Hubungi Kami pada halaman ini.
    --}}
    <style>
        .pb-masuk {
            --pb-durasi: 360ms;
            --pb-easing: cubic-bezier(0.22, 1, 0.36, 1);
            opacity: 0;
            transform: translateY(8px);
            animation: pb-masuk var(--pb-durasi) var(--pb-easing) both;
        }

        @keyframes pb-masuk {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pb-kartu {
            transition: transform 160ms cubic-bezier(0.22, 1, 0.36, 1),
                        border-color 160ms cubic-bezier(0.22, 1, 0.36, 1),
                        box-shadow 160ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        @media (hover: hover) {
            .pb-kartu:hover {
                transform: translateY(-2px);
                border-color: var(--color-brand, #1557A6);
                --tw-shadow: 0 4px 10px 0 rgb(11 42 91 / 0.06), 0 14px 32px -16px rgb(11 42 91 / 0.32);
                box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
            }
        }

        .pb-kartu:active {
            transform: translateY(0);
            opacity: 0.95;
        }

        /* Kotak tint ikon — ditulis sebagai kelas biasa, bukan utility
           arbitrary Tailwind bertumpuk, supaya color-mix() dengan token
           existing tetap mudah dibaca dan tidak salah escape. */
        .pb-tint-blue {
            background-color: color-mix(in oklab, var(--color-brand, #1557A6) 11%, white);
            color: var(--color-brand, #1557A6);
        }

        .dark .pb-tint-blue {
            background-color: color-mix(in oklab, var(--color-brand, #1557A6) 24%, black);
            color: var(--primary-300);
        }

        .pb-tint-orange {
            background-color: color-mix(in oklab, var(--color-bps-orange, #E18939) 11%, white);
            color: var(--color-bps-orange, #E18939);
        }

        .dark .pb-tint-orange {
            background-color: color-mix(in oklab, var(--color-bps-orange, #E18939) 22%, black);
            color: color-mix(in oklab, var(--color-bps-orange, #E18939) 55%, white);
        }

        .pb-envelope-icon {
            color: var(--color-bps-orange, #E18939);
        }

        .dark .pb-tint-green {
            background-color: color-mix(in oklab, var(--success-600, #16A34A) 22%, black);
        }

        .pb-info-box {
            background-color: color-mix(in oklab, var(--color-brand, #1557A6) 8%, white);
        }

        .dark .pb-info-box {
            background-color: color-mix(in oklab, var(--color-brand, #1557A6) 20%, black);
        }

        @media (prefers-reduced-motion: reduce) {
            .pb-masuk {
                animation: pb-fade 150ms ease both;
                transform: none;
            }

            @keyframes pb-fade {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            .pb-kartu,
            .pb-kartu:hover,
            .pb-kartu:active {
                transform: none;
                transition: border-color 160ms ease, box-shadow 160ms ease;
            }
        }
    </style>

    <div class="space-y-10 sm:space-y-10">

        {{-- ============================ PANDUAN PENGGUNAAN ============================ --}}
        <section>
            <div class="pb-masuk mb-5 flex items-center gap-4" style="animation-delay: 60ms">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-navy text-white" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-book-open" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-[22px] font-bold leading-tight text-gray-950 dark:text-white">Panduan Penggunaan</h2>
                    <p class="text-[15px] text-gray-500 dark:text-gray-400">Unduh dokumen panduan untuk membantu Anda memakai SIMPBI.</p>
                </div>
            </div>

            <div class="pb-masuk" style="animation-delay: 100ms">
                @if ($this->panduanTersedia())
                    <div class="pb-kartu rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
                            <span class="pb-tint-blue flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-7 w-7" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Panduan Penggunaan Aplikasi SIMPBI</h3>
                                <p class="mt-1 text-[15px] text-gray-500 dark:text-gray-400">Dokumen panduan lengkap penggunaan SIMPBI untuk seluruh peran.</p>
                                <div class="mt-3 flex items-center gap-2">
                                    <x-filament::badge color="gray">{{ $this->panduanFormat() }}</x-filament::badge>
                                    <x-filament::badge color="gray">{{ $this->panduanUkuran() }}</x-filament::badge>
                                </div>
                            </div>

                            <x-filament::button
                                tag="a"
                                href="{{ $this->panduanUrl() }}"
                                download
                                icon="heroicon-o-arrow-down-tray"
                                color="primary"
                                class="h-12 w-full shrink-0 justify-center sm:w-auto"
                            >
                                Unduh Panduan
                            </x-filament::button>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-center gap-5">
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-document-text" class="h-7 w-7" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">Panduan Penggunaan Aplikasi SIMPBI</h3>
                                <p class="mt-1 text-[15px] text-gray-500 dark:text-gray-400">Panduan belum tersedia. Silakan hubungi Sub-Bagian Umum.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        {{-- ============================ HUBUNGI KAMI ============================ --}}
        <section>
            <div class="pb-masuk mb-5 flex items-center gap-4" style="animation-delay: 160ms">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-navy text-white" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-[22px] font-bold leading-tight text-gray-950 dark:text-white">Hubungi Kami</h2>
                    <p class="text-[15px] text-gray-500 dark:text-gray-400">Belum menemukan jawaban? Sub-Bagian Umum siap membantu.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 lg:items-stretch">

                {{-- ---------- WhatsApp ---------- --}}
                <div class="pb-masuk sm:col-span-1" style="animation-delay: 200ms">
                    <div class="pb-kartu flex h-full flex-col justify-between rounded-xl border border-gray-200 bg-white p-7 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div>
                            <span class="pb-tint-green mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-success-50 text-success-600 dark:text-success-400" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-chat-bubble-oval-left-ellipsis" class="h-7 w-7" />
                            </span>
                            <h3 class="mt-4 text-xl font-bold text-gray-950 dark:text-white">WhatsApp</h3>
                            <p class="mt-1.5 text-[15px] text-gray-500 dark:text-gray-400">Respon cepat lewat pesan langsung</p>
                        </div>

                        <div class="mt-6">
                            @if ($this->nomorWhatsAppTampilan())
                                <div
                                    x-data="{
                                        tersalin: false,
                                        adaClipboard: (typeof navigator !== 'undefined' && !!navigator.clipboard),
                                        salin() {
                                            navigator.clipboard.writeText(@js($this->nomorWhatsAppTampilan())).then(() => {
                                                this.tersalin = true;
                                                $refs.pengumuman.textContent = 'Nomor WhatsApp disalin';
                                                setTimeout(() => { this.tersalin = false }, 1600);
                                            });
                                        },
                                    }"
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $this->nomorWhatsAppTampilan() }}</span>

                                    <span class="relative" x-show="adaClipboard">
                                        <button
                                            type="button"
                                            x-on:click="salin()"
                                            aria-label="Salin nomor WhatsApp"
                                            title="Salin nomor WhatsApp"
                                            class="flex h-11 w-11 items-center justify-center rounded-[10px] text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus-visible:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
                                        >
                                            <x-filament::icon icon="heroicon-o-check-circle" class="h-[18px] w-[18px] text-success-600 dark:text-success-400" x-cloak x-show="tersalin" />
                                            <x-filament::icon icon="heroicon-o-clipboard-document" class="h-[18px] w-[18px]" x-show="! tersalin" />
                                        </button>
                                        <span
                                            x-cloak
                                            x-show="tersalin"
                                            x-transition.opacity.duration.120ms
                                            class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white dark:bg-gray-700"
                                        >Tersalin</span>
                                    </span>

                                    <span class="sr-only" role="status" aria-live="polite" x-ref="pengumuman"></span>
                                </div>

                                <x-filament::button
                                    tag="a"
                                    href="{{ $this->tautanWhatsApp() }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    icon="heroicon-o-arrow-top-right-on-square"
                                    color="gray"
                                    outlined
                                    class="mt-4 h-11 min-w-[160px] justify-center"
                                >
                                    Chat Sekarang
                                    <span class="sr-only">(membuka tab baru)</span>
                                </x-filament::button>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Nomor WhatsApp belum diatur oleh Administrator.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ---------- Email ---------- --}}
                <div class="pb-masuk sm:col-span-1" style="animation-delay: 260ms">
                    <div class="pb-kartu flex h-full flex-col justify-between rounded-xl border border-gray-200 bg-white p-7 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div>
                            <span class="pb-envelope-icon mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-navy" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-envelope" class="h-7 w-7" />
                            </span>
                            <h3 class="mt-4 text-xl font-bold text-gray-950 dark:text-white">Email</h3>
                            <p class="mt-1.5 text-[15px] text-gray-500 dark:text-gray-400">Untuk pertanyaan atau laporan yang lebih rinci</p>
                        </div>

                        <div class="mt-6">
                            <div
                                x-data="{
                                    tersalin: false,
                                    adaClipboard: (typeof navigator !== 'undefined' && !!navigator.clipboard),
                                    salin() {
                                        navigator.clipboard.writeText(@js($this->email())).then(() => {
                                            this.tersalin = true;
                                            $refs.pengumuman.textContent = 'Alamat email disalin';
                                            setTimeout(() => { this.tersalin = false }, 1600);
                                        });
                                    },
                                }"
                                class="flex items-center justify-center gap-1.5"
                            >
                                <span
                                    class="min-w-0 truncate text-base font-semibold text-gray-950 dark:text-white"
                                    title="{{ $this->email() }}"
                                    style="word-break: break-word;"
                                >{{ $this->email() }}</span>

                                <span class="relative shrink-0" x-show="adaClipboard">
                                    <button
                                        type="button"
                                        x-on:click="salin()"
                                        aria-label="Salin alamat email"
                                        title="Salin alamat email"
                                        class="flex h-11 w-11 items-center justify-center rounded-[10px] text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 focus-visible:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300"
                                    >
                                        <x-filament::icon icon="heroicon-o-check-circle" class="h-[18px] w-[18px] text-success-600 dark:text-success-400" x-cloak x-show="tersalin" />
                                        <x-filament::icon icon="heroicon-o-clipboard-document" class="h-[18px] w-[18px]" x-show="! tersalin" />
                                    </button>
                                    <span
                                        x-cloak
                                        x-show="tersalin"
                                        x-transition.opacity.duration.120ms
                                        class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs font-medium text-white dark:bg-gray-700"
                                    >Tersalin</span>
                                </span>

                                <span class="sr-only" role="status" aria-live="polite" x-ref="pengumuman"></span>
                            </div>

                            <x-filament::button
                                tag="a"
                                href="mailto:{{ $this->email() }}"
                                icon="heroicon-o-paper-airplane"
                                color="gray"
                                outlined
                                class="mt-4 h-11 min-w-[160px] justify-center"
                            >
                                Kirim Email
                            </x-filament::button>
                        </div>
                    </div>
                </div>

                {{-- ---------- Jam Operasional ---------- --}}
                <div
                    class="pb-masuk sm:col-span-2 lg:col-span-1"
                    style="animation-delay: 320ms"
                    x-data="pusatBantuanStatusLayanan(@js($this->jamLayananJson()), @js($this->zonaWaktu()))"
                    x-init="mulai()"
                >
                    <div class="pb-kartu flex h-full flex-col rounded-xl border border-gray-200 bg-white p-7 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-center">
                            <span class="pb-tint-orange mx-auto flex h-14 w-14 items-center justify-center rounded-2xl" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-clock" class="h-7 w-7" />
                            </span>
                            <h3 class="mt-4 text-xl font-bold text-gray-950 dark:text-white">Jam Operasional</h3>
                            <p class="mt-1.5 text-[15px] text-gray-500 dark:text-gray-400">Waktu layanan Sub-Bagian Umum</p>
                        </div>

                        {{-- Ruang dicadangkan sejak render awal (24px) agar tidak ada flash/layout
                             shift ketika JS selesai menghitung; bila JS tidak berjalan, tidak
                             menyisakan ruang aneh sebab elemen di dalamnya memang kosong. --}}
                        <div class="mx-auto mt-3 flex h-6 items-center justify-center gap-1.5" x-cloak x-show="siap" x-transition.opacity.duration.120ms title="Berdasarkan jam layanan reguler">
                            <span class="h-2 w-2 rounded-full" x-bind:class="buka ? 'bg-success-500' : 'bg-gray-400'"></span>
                            <span class="text-[13px] text-gray-600 dark:text-gray-400" x-text="buka ? 'Sedang dalam jam layanan' : 'Di luar jam layanan'"></span>
                        </div>

                        <div class="mt-5 space-y-2.5">
                            @foreach ($this->barisJamLayanan() as $baris)
                                <div class="flex items-center justify-between gap-3 text-[15px]">
                                    <span class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                        <x-filament::icon
                                            :icon="$baris['libur'] ? 'heroicon-o-x-circle' : 'heroicon-o-calendar-days'"
                                            class="h-4 w-4 shrink-0"
                                        />
                                        {{ $baris['label'] }}
                                    </span>

                                    @if ($baris['libur'])
                                        <x-filament::badge color="danger">Libur</x-filament::badge>
                                    @else
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $baris['jam'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="pb-info-box mt-5 flex items-start gap-2.5 rounded-xl px-3.5 py-3">
                            <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-brand dark:text-primary-300" aria-hidden="true" />
                            <p class="text-[13px] leading-relaxed text-gray-700 dark:text-gray-300">
                                {!! str($this->catatanBalasan())->replace('1 hari kerja', '<strong>1 hari kerja</strong>') !!}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        function pusatBantuanStatusLayanan(jamLayanan, zonaWaktu) {
            return {
                siap: false,
                buka: false,
                _id: null,
                mulai() {
                    this.hitung();
                    this.siap = true;
                    this._id = setInterval(() => this.hitung(), 60000);
                    document.addEventListener('livewire:navigate', () => clearInterval(this._id), { once: true });
                },
                hitung() {
                    const bagian = new Intl.DateTimeFormat('en-GB', {
                        timeZone: zonaWaktu,
                        weekday: 'long',
                        hour: '2-digit',
                        minute: '2-digit',
                        hourCycle: 'h23',
                    }).formatToParts(new Date());

                    const namaHari = bagian.find(b => b.type === 'weekday').value.toLowerCase();
                    const jam = parseInt(bagian.find(b => b.type === 'hour').value, 10);
                    const menit = parseInt(bagian.find(b => b.type === 'minute').value, 10);

                    const petaHari = {
                        monday: 'senin', tuesday: 'selasa', wednesday: 'rabu', thursday: 'kamis',
                        friday: 'jumat', saturday: 'sabtu', sunday: 'minggu',
                    };
                    const kunci = petaHari[namaHari];
                    const jamIni = jamLayanan[kunci];

                    if (! jamIni) {
                        this.buka = false;
                        return;
                    }

                    const menitSekarang = (jam * 60) + menit;
                    const [jBuka, mBuka] = jamIni.buka.split(':').map(Number);
                    const [jTutup, mTutup] = jamIni.tutup.split(':').map(Number);
                    const menitBuka = (jBuka * 60) + mBuka;
                    const menitTutup = (jTutup * 60) + mTutup;

                    this.buka = menitSekarang >= menitBuka && menitSekarang < menitTutup;
                },
            };
        }
    </script>

</x-filament-panels::page>
