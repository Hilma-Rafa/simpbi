{{--
    Penanda Caps Lock di bawah kolom kata sandi.

    Hanya tampilan: tidak membaca, mengubah, atau mengirim nilai kata sandi, dan
    tidak menyentuh autentikasi, pembatasan percobaan, maupun pesan galat. Alpine
    dibawa Filament sendiri, jadi tidak ada pustaka baru.

    Cara kerja: keadaan Caps Lock dibaca lewat getModifierState pada keydown dan
    keyup yang terjadi di dalam kolom sandi ini. Pesan hilang ketika Caps Lock
    mati atau ketika fokus meninggalkan kolom. Pendengar dipasang pada window
    dan disaring menurut kolomnya, sehingga tetap bekerja ketika Livewire
    merender ulang isi formulir.

    Tinggi baris ini selalu 20px, tampak maupun tidak, sehingga tata letak tidak
    bergeser ketika pesan muncul. Elemen role="status" sudah ada sejak halaman
    dimuat supaya perubahan isinya diumumkan pembaca layar dengan santun
    (aria-live="polite").
--}}
<div
    class="fi-simpbi-caps"
    x-data="{
        aktif: false,
        kolom: null,
        init() {
            this.kolom = this.$root.closest('.fi-fo-field')
        },
        periksa(e) {
            if (! this.kolom || e.target.tagName !== 'INPUT' || ! this.kolom.contains(e.target)) {
                return
            }

            this.aktif = typeof e.getModifierState === 'function' && e.getModifierState('CapsLock')
        },
        lepas(e) {
            if (this.kolom && e.target.tagName === 'INPUT' && this.kolom.contains(e.target)) {
                this.aktif = false
            }
        },
    }"
    x-on:keydown.window="periksa($event)"
    x-on:keyup.window="periksa($event)"
    x-on:focusout.window="lepas($event)"
>
    <p class="fi-simpbi-caps-pesan" role="status" aria-live="polite">
        <span class="fi-simpbi-caps-isi" x-cloak x-show="aktif">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="fi-simpbi-caps-ikon" aria-hidden="true" />
            Caps Lock aktif
        </span>
    </p>
</div>
