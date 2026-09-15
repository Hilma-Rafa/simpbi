{{--
    Kanvas tanda tangan.

    Digambar dengan pointer events, bukan mouse events, supaya satu berkas ini
    melayani tetikus, layar sentuh, dan pena tanpa cabang kode terpisah —
    Petugas Gudang lazimnya memakai tetikus, sedangkan Ketua Tim membuka
    SIMPBI dari ponsel.

    Tidak memakai pustaka tanda tangan pihak ketiga: seluruh yang dibutuhkan
    hanyalah menyambung titik-titik pointer, sedangkan memuat pustaka dari CDN
    akan menambah satu ketergantungan jaringan pada halaman yang harus tetap
    bekerja di jaringan kantor yang tertutup.
--}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @php
        $lintasanState = $getStatePath();
        $tersimpan     = $getTandaTanganTersimpan();
    @endphp

    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle('{{ $lintasanState }}'),

            /* 'tersimpan' menampilkan tanda tangan yang sudah ada, 'gambar'
               membuka kanvas kosong. Pengguna yang belum punya langsung
               mendapat kanvas, tanpa perlu menekan apa pun lebih dulu. */
            mode: @js($tersimpan ? 'tersimpan' : 'gambar'),

            menggambar: false,
            adaGoresan: false,
            konteks: null,
            pantau: null,

            init() {
                /*
                 * AKAR MASALAH KANVAS YANG TIDAK BISA DIGAMBARI
                 *
                 * Isi dialog Filament dirender ketika dialognya masih
                 * tersembunyi, sehingga mengukur kanvas pada saat itu
                 * menghasilkan nol. Kanvas berukuran nol tidak menampilkan
                 * goresan apa pun, dan toDataURL() padanya mengembalikan
                 * data:, — bukan PNG — sehingga penyimpanannya ditolak
                 * peladen dengan galat yang membingungkan.
                 *
                 * Karena itu ukurannya tidak diambil sekali di awal, melainkan
                 * diamati: begitu kanvas benar-benar memperoleh ukuran, ia
                 * disiapkan saat itu juga. Cara ini sekaligus melayani
                 * perubahan lebar jendela dan perpindahan dari tanda tangan
                 * tersimpan ke kanvas kosong.
                 */
                this.pantau = new ResizeObserver(() => this.siapkanKanvas())

                this.$nextTick(() => {
                    if (this.$refs.kanvas) this.pantau.observe(this.$refs.kanvas)
                })
            },

            destroy() {
                this.pantau?.disconnect()
            },

            siapkanKanvas() {
                const kanvas = this.$refs.kanvas
                if (! kanvas) return

                const kotak = kanvas.getBoundingClientRect()

                // Masih tersembunyi; tunggu sampai benar-benar terlihat.
                if (kotak.width < 1 || kotak.height < 1) return

                /* Kanvas digambar pada resolusi perangkat, lalu dikecilkan
                   lewat CSS. Tanpa ini goresan tampak berbayang di layar
                   beresolusi tinggi, dan hasil PNG-nya ikut kabur. */
                const rasio = window.devicePixelRatio || 1
                const lebar = Math.round(kotak.width * rasio)
                const tinggi = Math.round(kotak.height * rasio)

                // Ukurannya tidak berubah, jadi tidak ada yang perlu disiapkan
                // ulang — menyiapkan ulang akan menghapus goresan tanpa sebab.
                if (kanvas.width === lebar && kanvas.height === tinggi) return

                // Mengubah width/height mengosongkan kanvas, sehingga goresan
                // yang sudah ada disalin dulu lalu digambar kembali.
                const sebelumnya = this.adaGoresan ? kanvas.toDataURL('image/png') : null

                kanvas.width = lebar
                kanvas.height = tinggi

                const k = kanvas.getContext('2d')
                k.scale(rasio, rasio)
                k.lineWidth = 2.2
                k.lineCap = 'round'
                k.lineJoin = 'round'
                k.strokeStyle = '#0B2A5B'
                this.konteks = k

                if (sebelumnya) {
                    const gambar = new Image()
                    gambar.onload = () => k.drawImage(gambar, 0, 0, kotak.width, kotak.height)
                    gambar.src = sebelumnya
                }
            },

            /** Kanvas siap dipakai hanya bila ia benar-benar punya permukaan. */
            siap() {
                const kanvas = this.$refs.kanvas

                return !! this.konteks && !! kanvas && kanvas.width > 0 && kanvas.height > 0
            },

            titik(peristiwa) {
                const kotak = this.$refs.kanvas.getBoundingClientRect()
                return { x: peristiwa.clientX - kotak.left, y: peristiwa.clientY - kotak.top }
            },

            mulai(peristiwa) {
                if (! this.siap()) this.siapkanKanvas()
                if (! this.siap()) return

                /* Pointer ditangkap supaya goresan tidak terputus ketika
                   kursor sempat keluar dari kanvas di tengah tarikan. */
                this.$refs.kanvas.setPointerCapture(peristiwa.pointerId)
                this.menggambar = true
                const t = this.titik(peristiwa)
                this.konteks.beginPath()
                this.konteks.moveTo(t.x, t.y)
            },

            gerak(peristiwa) {
                if (! this.menggambar) return
                const t = this.titik(peristiwa)
                this.konteks.lineTo(t.x, t.y)
                this.konteks.stroke()
                this.adaGoresan = true
            },

            selesai(peristiwa) {
                if (! this.menggambar) return
                this.menggambar = false
                if (this.$refs.kanvas.hasPointerCapture(peristiwa.pointerId)) {
                    this.$refs.kanvas.releasePointerCapture(peristiwa.pointerId)
                }
                this.rekam()
            },

            rekam() {
                /*
                 * Hanya kanvas yang benar-benar punya permukaan yang boleh
                 * dikirim. Tanpa penjagaan ini, kanvas berukuran nol mengirim
                 * data:, ke peladen, yang ditolak sebagai bukan PNG — galat
                 * yang tidak menyebut sebab sebenarnya sama sekali.
                 */
                this.state = (this.adaGoresan && this.siap())
                    ? this.$refs.kanvas.toDataURL('image/png')
                    : null
            },

            bersihkan() {
                if (! this.siap()) return
                const kanvas = this.$refs.kanvas
                this.konteks.clearRect(0, 0, kanvas.width, kanvas.height)
                this.adaGoresan = false
                this.state = null
            },

            gambarUlang() {
                this.mode = 'gambar'
                this.adaGoresan = false
                this.state = null
                // Kanvas baru saja berpindah dari tersembunyi ke terlihat,
                // sehingga ukurannya baru ada setelah render berikutnya.
                this.$nextTick(() => this.siapkanKanvas())
            },
        }"
        class="fi-simpbi-ttd"
    >
        {{-- ---------- Tanda tangan yang sudah tersimpan ----------
             Hanya dirender bila memang ada. Merendernya dalam keadaan kosong
             berarti menaruh <img> tanpa sumber di dalam halaman, yang membuat
             peramban meminta ulang alamat halaman itu sendiri. --}}
        @if ($tersimpan)
        <div x-show="mode === 'tersimpan'" x-cloak>
            <div class="fi-simpbi-ttd-kotak fi-simpbi-ttd-tersimpan">
                <img src="{{ $tersimpan }}" alt="Tanda tangan tersimpan">
            </div>

            <div class="fi-simpbi-ttd-aksi">
                <button type="button" x-on:click="gambarUlang()" class="fi-simpbi-ttd-tombol">
                    Gambar Ulang
                </button>
            </div>
        </div>
        @endif

        {{-- ---------- Kanvas ---------- --}}
        <div x-show="mode === 'gambar'">
            <div class="fi-simpbi-ttd-kotak">
                <canvas
                    x-ref="kanvas"
                    x-on:pointerdown.prevent="mulai($event)"
                    x-on:pointermove.prevent="gerak($event)"
                    x-on:pointerup.prevent="selesai($event)"
                    x-on:pointercancel.prevent="selesai($event)"
                ></canvas>

                <span class="fi-simpbi-ttd-petunjuk" x-show="! adaGoresan">
                    Bubuhkan tanda tangan di sini
                </span>
            </div>

            <div class="fi-simpbi-ttd-aksi">
                <button
                    type="button"
                    x-on:click="bersihkan()"
                    x-bind:disabled="! adaGoresan"
                    class="fi-simpbi-ttd-tombol"
                >
                    Bersihkan
                </button>

                @if ($tersimpan)
                    <button
                        type="button"
                        x-on:click="mode = 'tersimpan'; state = null"
                        class="fi-simpbi-ttd-tombol"
                    >
                        Batal, pakai yang tersimpan
                    </button>
                @endif
            </div>
        </div>
    </div>
</x-dynamic-component>
