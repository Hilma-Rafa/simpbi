{{--
    Layar pemuatan SIMPBI.

    Tampil sejak halaman mulai dimuat dan menghilang begitu berkas halaman siap,
    sehingga pengguna tidak melihat halaman setengah jadi. Urutannya: tanda BPS
    muncul halus, wordmark SIMPBI memudar masuk, garis tipis menyapu di
    bawahnya, lalu satu baris status statis — hanya memakai opacity dan
    transform, tanpa perspektif 3D atau cahaya berpendar (Instruksi bagian H).
    Aturan geraknya ada pada berkas tema (`.fi-simpbi-pemuatan`), termasuk
    penghormatan terhadap preferensi pengguna yang membatasi animasi.

    Disembunyikan dari pembaca layar karena tidak membawa informasi yang perlu
    dibacakan, dan dihapus dari alur begitu selesai agar tidak menghalangi klik.
--}}
<div
    class="fi-simpbi-pemuatan fixed inset-0 z-[60] grid place-items-center bg-navy"
    aria-hidden="true"
    x-data="{ selesai: false }"
    x-init="
        const tutup = () => setTimeout(() => { selesai = true }, 420);
        document.readyState === 'complete' ? tutup() : window.addEventListener('load', tutup, { once: true });
        setTimeout(() => { selesai = true }, 6000);
    "
    x-bind:class="selesai && 'fi-simpbi-pemuatan-selesai'"
    x-on:transitionend="if (selesai) $el.remove()"
>
    <div class="flex flex-col items-center gap-6">

        {{-- Tanda yang dibalik: logo BPS di atas kepingan putih --}}
        <div class="fi-simpbi-pemuatan-tanda grid h-[4.5rem] w-[4.5rem] place-items-center rounded-2xl bg-white p-3.5 shadow-2xl shadow-black/30">
            <img
                src="{{ asset('images/logo-bps.png') }}"
                alt=""
                class="h-full w-full object-contain"
            >
        </div>

        <div class="text-center leading-tight">
            <p class="fi-simpbi-pemuatan-teks text-xl font-bold tracking-tight text-white">SIMPBI</p>
            <p class="fi-simpbi-pemuatan-teks fi-simpbi-pemuatan-teks-kedua mt-1 text-xs text-white/60">
                BPS Kota Jakarta Barat
            </p>
        </div>

        {{-- Garis tipis yang menyapu dari kiri, menggantikan corong 3D/berpendar. --}}
        <span class="fi-simpbi-pemuatan-garis h-px w-16 bg-white/30"></span>

        <p class="fi-simpbi-pemuatan-teks fi-simpbi-pemuatan-status text-[11px] uppercase tracking-[0.14em] text-white/45">
            Menyiapkan sistem
        </p>
    </div>
</div>
