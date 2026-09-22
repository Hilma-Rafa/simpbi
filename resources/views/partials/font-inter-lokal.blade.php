{{--
    Inter dari berkas lokal (A-019).

    Berkas font Inter sudah ada di proyek: aset Filament yang diterbitkan ke
    public/fonts/filament/filament/inter, berupa font variabel per subset
    aksara. Di sana keluarganya bernama "Inter Variable", sedangkan seluruh CSS
    SIMPBI memakai nama "Inter" (--font-sans, --font-family panel). Deklarasi di
    bawah memberi berkas yang sama itu nama "Inter" (rentang bobot penuh, tiap
    subset dengan unicode-range-nya), sehingga tampilan tidak berubah tetapi
    Inter tidak lagi diminta dari fonts.bunny.net.

    Plus Jakarta Sans dan Space Grotesk belum tersedia sebagai berkas lokal;
    keduanya masih dimuat dari pemanggilan lama pada halaman yang memuat
    partial ini.
--}}
@php
    $subsetInter = [
        'cyrillic-ext' => ['SP7Z6XGK', 'U+0460-052F,U+1C80-1C8A,U+20B4,U+2DE0-2DFF,U+A640-A69F,U+FE2E-FE2F'],
        'cyrillic'     => ['DR6K5BQD', 'U+0301,U+0400-045F,U+0490-0491,U+04B0-04B1,U+2116'],
        'greek-ext'    => ['A2J6H3EX', 'U+1F00-1FFF'],
        'greek'        => ['ZABHMKQG', 'U+0370-0377,U+037A-037F,U+0384-038A,U+038C,U+038E-03A1,U+03A3-03FF'],
        'vietnamese'   => ['VWEHJHBA', 'U+0102-0103,U+0110-0111,U+0128-0129,U+0168-0169,U+01A0-01A1,U+01AF-01B0,U+0300-0301,U+0303-0304,U+0308-0309,U+0323,U+0329,U+1EA0-1EF9,U+20AB'],
        'latin-ext'    => ['ZIT2UBIY', 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF'],
        'latin'        => ['DIHYUR35', 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD'],
    ];
@endphp
<style>
@foreach ($subsetInter as $subset => [$kode, $rentang])
@font-face{font-family:'Inter';font-style:normal;font-display:swap;font-weight:100 900;src:url('{{ asset("fonts/filament/filament/inter/inter-{$subset}-wght-normal-{$kode}.woff2") }}') format('woff2-variations');unicode-range:{{ $rentang }}}
@endforeach
</style>
