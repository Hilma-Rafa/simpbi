{{--
    Dialog konfirmasi keluar.

    Dipasang pada level layout (hook BODY_END), di luar DOM dropdown, supaya
    tidak ikut terpotong atau tertutup ketika dropdown ditutup. Memakai
    komponen modal bawaan Filament: backdrop, animasi, penguncian gulir, jebakan
    fokus, dan penutupan lewat Escape/klik latar sudah datang darinya.

    Alur keluarnya TIDAK diubah: formulir di bawah ini persis yang dirender
    bawaan Filament untuk menu "Keluar" sebelumnya — POST ke
    filament()->getLogoutUrl() dengan token CSRF. Yang bertambah hanya
    dialog konfirmasi di depannya.

    Fokus dikembalikan ke avatar oleh pendengar `modal-closed` pada
    aksi-bilah-atas.blade.php (fokus bawaan modal menunjuk ke tombol Keluar
    yang sudah tersembunyi bersama dropdown), karena itu restores-focus mati.
--}}
<x-filament::modal
    id="dialog-keluar"
    :alert="true"
    icon="heroicon-o-arrow-left-start-on-rectangle"
    icon-color="danger"
    heading="Keluar dari akun?"
    description="Sesi Anda akan diakhiri dan Anda perlu masuk kembali untuk memakai SIMPBI."
    width="sm"
    :close-button="false"
    :close-by-clicking-away="true"
    :close-by-escaping="true"
    :restores-focus="false"
    class="simpbi-dialog-keluar"
>
    <x-slot name="footer">
        <form
            method="post"
            action="{{ filament()->getLogoutUrl() }}"
            x-data="{ memproses: false }"
            x-on:submit="memproses = true"
            x-on:pageshow.window="if ($event.persisted) memproses = false"
            class="simpbi-dialog-aksi"
        >
            @csrf

            <x-filament::button
                color="gray"
                outlined
                autofocus
                x-on:click="$dispatch('close-modal', { id: 'dialog-keluar' })"
                x-bind:disabled="memproses"
                class="simpbi-dialog-tombol"
            >
                Batal
            </x-filament::button>

            <x-filament::button
                type="submit"
                color="danger"
                x-bind:disabled="memproses"
                x-bind:aria-busy="memproses ? 'true' : 'false'"
                class="simpbi-dialog-tombol"
            >
                <span x-show="memproses" x-cloak class="simpbi-dialog-spinner" aria-hidden="true"></span>
                <span x-text="memproses ? 'Memproses…' : 'Ya, Keluar'">Ya, Keluar</span>
            </x-filament::button>
        </form>
    </x-slot>
</x-filament::modal>
