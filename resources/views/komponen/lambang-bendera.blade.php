{{-- Bendera untuk penukar bahasa, dipakai bersama oleh halaman muka dan kedua
     halaman verifikasi.

     Digambar sebagai SVG, bukan emoji, karena Windows tidak menggambar emoji
     bendera sama sekali — yang muncul justru huruf "ID" dan "GB", sehingga di
     komputer pengguna hasilnya tidak akan seperti yang dimaksud.

     Didefinisikan sekali per halaman lalu dipakai ulang lewat <use>, supaya id
     clipPath di dalam Union Jack tidak pernah kembar walau benderanya muncul
     di beberapa tempat sekaligus. Disembunyikan lewat gaya sebaris, bukan
     kelas utilitas, sebab halaman verifikasi tidak memuat Tailwind. --}}
<svg style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">
    <symbol id="bendera-id" viewBox="0 0 60 30">
        <rect width="60" height="15" fill="#CE1126" />
        <rect y="15" width="60" height="15" fill="#F5F5F5" />
    </symbol>
    <symbol id="bendera-en" viewBox="0 0 60 30">
        <clipPath id="bendera-en-kotak">
            <path d="M0,0 v30 h60 v-30 z" />
        </clipPath>
        <clipPath id="bendera-en-diagonal">
            <path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z" />
        </clipPath>
        <g clip-path="url(#bendera-en-kotak)">
            <path d="M0,0 v30 h60 v-30 z" fill="#012169" />
            <path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6" />
            <path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#bendera-en-diagonal)" stroke="#C8102E"
                stroke-width="4" />
            <path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10" />
            <path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6" />
        </g>
    </symbol>
</svg>
