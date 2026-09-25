<?php

namespace App\Support;

/**
 * Aturan bentuk kode barang persediaan: tepat enam digit angka.
 *
 * Kode barang diambil dari kartu kendali persediaan Sub-Bagian Umum, tempat ia
 * menjadi segmen terakhir kode lengkap `1.01.03.01.001.000122`. Kartu itu
 * selalu menuliskannya enam digit, termasuk nol di depan, sehingga kode yang
 * lebih pendek atau bercampur huruf tidak akan pernah dapat dicocokkan kembali
 * dengan kartunya.
 *
 * Aturannya dikumpulkan di sini, bukan ditulis ulang di tiap titik masuk,
 * karena kode barang lahir dari empat tempat — form Buat/Ubah Barang
 * Persediaan, dialog Barang Baru di Stok Masuk, impor Barang Persediaan, dan
 * impor Stok Awal. Bila salah satunya menyimpan pola sendiri, cepat atau
 * lambat satu di antaranya tertinggal dan menerima kode yang ditolak yang lain.
 *
 * Diletakkan di luar model BarangPersediaan dengan sengaja: model itu memuat
 * getKodeLengkapAttribute() yang dipakai PDF dan Excel kartu kendali, dan
 * berkas pembentuk dokumen dibekukan.
 */
class KodeBarang
{
    /** Tepat enam digit angka; disimpan sebagai teks agar nol di depan terjaga. */
    public const POLA = '/^\d{6}$/';

    public const PANJANG = 6;

    public const PESAN = 'Kode barang harus tepat 6 digit angka, sesuai kartu kendali persediaan (mis. 000122).';

    public const BANTUAN = '6 digit angka sesuai kartu kendali persediaan, termasuk nol di depan (mis. 000122). '
        . 'Kode cukup unik di dalam kategorinya.';

    public const CONTOH = '000122';

    public static function sah(string $kode): bool
    {
        return preg_match(self::POLA, $kode) === 1;
    }

    /**
     * Pesan galat untuk kode yang dibaca dari berkas impor, atau null bila sah.
     *
     * Berkas sebar menyimpan `000122` pada sel berformat angka sebagai 122, dan
     * pembaca berkas menerimanya dalam bentuk itu. Kode angka yang kurang dari
     * enam digit karena itu hampir pasti kehilangan nol di depannya, dan
     * pesannya dibedakan supaya pengisi tahu yang perlu diperbaiki adalah format
     * selnya, bukan kodenya.
     *
     * Nol tidak ditambahkan otomatis. Kode 12 bisa berarti 000012, tetapi bisa
     * juga salah ketik dari 120012; menebak di titik ini berarti diam-diam
     * menunjuk barang lain.
     */
    public static function pesanGalatImpor(string $kode): ?string
    {
        if (static::sah($kode)) {
            return null;
        }

        if (ctype_digit($kode) && strlen($kode) < self::PANJANG) {
            return 'Kode Barang ' . $kode . ' tampaknya kehilangan nol di depan. Format selnya sebagai Teks '
                . 'lalu tulis ' . str_pad($kode, self::PANJANG, '0', STR_PAD_LEFT) . '.';
        }

        return 'Kode Barang "' . $kode . '" tidak sah. ' . self::PESAN;
    }
}
