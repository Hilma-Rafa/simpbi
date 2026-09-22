{{--
    Huruf panel disamakan dengan halaman muka.

    Halaman muka memakai tiga huruf: Inter untuk teks, Plus Jakarta Sans untuk
    judul, dan Space Grotesk untuk wordmark. Panel sebelumnya hanya memuat
    Inter, sehingga judul di dalam sistem terasa berbeda karakter dengan
    halaman sebelum masuk. Berkasnya diambil dari sumber yang sama persis,
    jadi peramban memakai ulang unduhan halaman muka tanpa permintaan baru.

    Inter kini dari berkas lokal (partial di bawah); hanya Plus Jakarta Sans
    dan Space Grotesk yang masih dimuat dari fonts.bunny.net karena berkasnya
    belum tersedia secara lokal (A-019).
--}}
@include('partials.font-inter-lokal')
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:600,700,800|space-grotesk:500,700" rel="stylesheet" />
