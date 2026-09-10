{{--
    Lambang SIMPBI pada bilah atas dan sisi kiri panel.

    Sebelumnya panel hanya memasang logo BPS, sehingga nama sistemnya tidak
    terbaca. Di sini logo resmi BPS dipasangkan dengan nama sistem dan nama
    satuan kerja, dipisah garis tipis agar identitas lembaga tetap menonjol dan
    nama sistem tidak terlihat seperti bagian dari logo.

    Susunannya sama dengan kepala halaman masuk, sehingga identitas yang dilihat
    pengguna sebelum dan sesudah masuk tetap sama.

    Nama sistem disembunyikan ketika sisi kiri dirapatkan, agar tidak terpotong.

    Warnanya terang karena bilah atas panel kini bernavy seperti bilah halaman
    muka; nama sistem memakai huruf wordmark dengan jarak huruf yang sama,
    sehingga lambang yang dilihat sebelum dan sesudah masuk benar-benar sama.
--}}
<div class="flex h-full items-center gap-2.5" x-data>
    <img
        src="{{ asset('images/logo-bps.png') }}"
        alt="Logo Badan Pusat Statistik"
        class="h-full w-auto shrink-0 object-contain"
    />

    <span
        class="h-6 w-px shrink-0 bg-white/25"
        x-show="$store.sidebar?.isOpen ?? true"
        aria-hidden="true"
    ></span>

    <span class="leading-tight" x-show="$store.sidebar?.isOpen ?? true">
        <span class="simpbi-wordmark block text-[0.9375rem] font-bold text-white">
            SIMPBI
        </span>
        <span class="block whitespace-nowrap text-[0.65rem] font-medium text-white/55">
            BPS Kota Jakarta Barat
        </span>
    </span>
</div>
