{{--
    Ringkasan nota yang akan dicatat, dibaca ulang sebelum disimpan.

    Sengaja hanya angka pokoknya — nomor nota, sumber, tanggal, banyaknya barang,
    dan totalnya. Menyalin seluruh baris barang ke sini hanya menghasilkan dialog
    sepanjang formulirnya sendiri, dan yang panjang justru tidak dibaca.
--}}
<dl class="divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm dark:divide-white/10 dark:border-white/10">
    @foreach ($ringkasan as $label => $nilai)
        <div class="flex items-baseline justify-between gap-6 px-4 py-2.5">
            <dt class="shrink-0 text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="text-end font-medium text-gray-900 dark:text-white">{{ $nilai }}</dd>
        </div>
    @endforeach
</dl>

<p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
    Periksa kembali data sebelum disimpan ke kartu kendali.
</p>
