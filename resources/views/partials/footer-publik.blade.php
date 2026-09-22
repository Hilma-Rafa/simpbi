@php
    /*
     * Kaki halaman publik, dipakai ulang oleh setiap halaman sebelum masuk
     * (saat ini: halaman muka). Tidak pernah disertakan pada kerangka
     * aplikasi sesudah masuk (Instruksi bagian G).
     *
     * Nomor bantuan dan tautannya memakai App\Support\KontakBantuan yang
     * sama dipakai kaki halaman masuk — bukan mekanisme baru — sehingga
     * bila nomornya kosong, tautan ini pun ikut tidak tampil.
     */
    $tautanBantuan = \App\Support\KontakBantuan::tautanWhatsApp();
    $alamatCari = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(
        __('muka.kaki.alamat_satker') . ', ' . __('muka.kaki.alamat_jalan')
    );
@endphp

<footer class="border-t border-hairline bg-white" role="contentinfo">
    {{-- Garis aksen identitas: tiga warna lambang BPS, bukan gradien. --}}
    <div class="flex h-1" aria-hidden="true">
        <span class="flex-1 bg-navy"></span>
        <span class="flex-1 bg-brand"></span>
        <span class="flex-1 bg-[var(--color-bps-orange)]"></span>
        <span class="flex-1 bg-[var(--color-bps-green)]"></span>
    </div>

    @if ($tautanBantuan)
        <div class="border-b border-hairline bg-[var(--color-surface)] px-4 py-4">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 sm:flex-row">
                <p class="text-sm font-medium text-ink">{{ __('muka.kaki.bantuan_judul') }}</p>
                <a
                    href="{{ $tautanBantuan }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center gap-2 rounded-full border border-hairline bg-white px-5 text-sm font-semibold text-navy transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:w-auto"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12.01 2C6.48 2 2 6.48 2 12c0 1.85.5 3.58 1.36 5.07L2 22l5.07-1.33A9.96 9.96 0 0 0 12.01 22C17.53 22 22 17.52 22 12S17.53 2 12.01 2Zm5.5 14.2c-.23.65-1.15 1.2-1.88 1.35-.5.1-1.15.18-3.35-.72-2.81-1.16-4.62-4.01-4.76-4.2-.14-.19-1.14-1.51-1.14-2.88 0-1.37.72-2.04.97-2.32.25-.28.55-.35.73-.35.18 0 .37 0 .53.01.17.01.4-.06.62.48.23.55.78 1.93.85 2.07.07.14.11.3.02.49-.09.19-.14.3-.28.46-.14.16-.29.36-.42.48-.14.14-.28.28-.12.55.16.28.71 1.18 1.53 1.91 1.05.94 1.94 1.24 2.21 1.38.28.14.44.12.6-.07.16-.19.68-.79.86-1.06.18-.28.36-.23.6-.14.25.09 1.58.75 1.85.88.28.14.46.2.53.32.07.11.07.65-.16 1.3Z"/>
                    </svg>
                    {{ __('muka.kaki.bantuan_tombol') }}
                </a>
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-6xl px-4 py-12">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Merek --}}
            <div>
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-navy p-1.5">
                        <img src="{{ asset('images/logo-bps.png') }}" alt="Logo Badan Pusat Statistik" class="h-full w-full object-contain">
                    </span>
                    <div class="text-sm leading-tight">
                        <p class="font-bold text-navy">SIMPBI</p>
                        <p class="text-muted">{{ __('muka.kaki.satker') }}</p>
                    </div>
                </div>
            </div>

            {{-- Hubungi Kami --}}
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">
                    {{ __('muka.kaki.hubungi_judul') }}
                </h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li>
                        <a
                            href="mailto:bps3174@bps.go.id"
                            class="inline-flex min-h-11 items-center gap-2 text-ink transition hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 12 13l9-5.5M4.5 5h15a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5v-11A1.5 1.5 0 0 1 4.5 5Z"/>
                            </svg>
                            bps3174@bps.go.id
                        </a>
                    </li>
                    <li>
                        <a
                            href="tel:+622125673776"
                            class="inline-flex min-h-11 items-center gap-2 text-ink transition hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4 w-4 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.5 5.5c0-1.1.9-2 2-2h1.63c.5 0 .93.34 1.05.82l.86 3.46c.1.4-.03.83-.34 1.1l-1.46 1.28a12.5 12.5 0 0 0 5.6 5.6l1.28-1.46a1.2 1.2 0 0 1 1.1-.34l3.46.86c.48.12.82.55.82 1.05V17.5c0 1.1-.9 2-2 2h-1c-8 0-14.5-6.5-14.5-14.5v-1Z"/>
                            </svg>
                            (021) 25673776
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Alamat Kantor --}}
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">
                    {{ __('muka.kaki.alamat_judul') }}
                </h2>
                <address class="mt-4 text-sm not-italic leading-relaxed text-ink">
                    {{ __('muka.kaki.alamat_satker') }}<br>
                    <span class="text-muted">{{ __('muka.kaki.alamat_jalan') }}</span>
                </address>
                <a
                    href="{{ $alamatCari }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-brand transition hover:text-navy focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                >
                    {{ __('muka.kaki.alamat_peta') }}
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <path d="M7 17 17 7M9 7h8v8"/>
                    </svg>
                </a>
            </div>

            {{-- Media Sosial --}}
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">
                    {{ __('muka.kaki.medsos_judul') }}
                </h2>
                <ul class="mt-4 flex items-center gap-2">
                    <li>
                        <a
                            href="https://www.youtube.com/channel/UCn4IaaxHaaP-mAjZzrAtixA"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="YouTube BPS"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-hairline text-muted transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.6 7.2s-.21-1.5-.87-2.16c-.83-.87-1.76-.87-2.19-.92C15.44 4 12 4 12 4h-.01s-3.44 0-6.54.12c-.43.05-1.36.05-2.19.92-.66.66-.87 2.16-.87 2.16S2.18 9 2.18 10.8v1.39C2.18 14 2.4 15.8 2.4 15.8s.21 1.5.87 2.16c.83.87 1.92.84 2.4.93C7.4 19.08 12 19.12 12 19.12s3.44-.01 6.54-.13c.43-.05 1.36-.05 2.19-.92.66-.66.87-2.16.87-2.16s.22-1.8.22-3.6v-1.39c0-1.8-.22-3.6-.22-3.6ZM9.96 14.5V8.9l5.4 2.81-5.4 2.8Z"/></svg>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://x.com/bps_statistics"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="X BPS Statistics"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-hairline text-muted transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.3 2.5h3.2l-7 8 8.2 11h-6.4l-5-6.6-5.7 6.6H1.4l7.5-8.6-7.9-10.4h6.6l4.5 6 5.2-6Zm-1.1 17.1h1.8L7 4.3H5l12.2 15.3Z"/></svg>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://www.instagram.com/bpsjakbar/"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Instagram BPS Jakarta Barat"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-hairline text-muted transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.2" cy="6.8" r="0.9" fill="currentColor" stroke="none"/>
                            </svg>
                        </a>
                    </li>
                    <li>
                        <a
                            href="https://www.facebook.com/bpsstatistics/#"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Facebook BPS Statistics"
                            class="flex h-11 w-11 items-center justify-center rounded-full border border-hairline text-muted transition hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 22v-8.5h2.85l.43-3.31H14V8.02c0-.96.27-1.61 1.64-1.61h1.75V3.46C17.08 3.32 16.03 3.24 14.96 3.24c-3.01 0-5.07 1.84-5.07 5.2v2.75H6.55v3.31H9.9V22h4.1Z"/></svg>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <p class="mt-10 border-t border-hairline pt-6 text-center text-xs text-muted">
            {{ __('muka.kaki.hak_cipta', ['tahun' => date('Y')]) }}
        </p>
    </div>
</footer>
