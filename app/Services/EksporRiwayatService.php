<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penerbitan berkas ekspor riwayat.
 *
 * Layanan ini sengaja tidak mengetahui asal datanya: pemanggil menyerahkan
 * satu atau beberapa bagian berisi judul kolom dan baris nilai yang sudah
 * tersaring, sehingga ekspor selalu mengikuti penyaring yang sedang aktif
 * pada tabel dan tidak perlu menulis ulang kueri.
 *
 * PDF dibentuk dengan dompdf yang sudah dipakai dokumen bukti permintaan dan
 * BAST, sedangkan berkas sebar dibentuk dengan OpenSpout dalam format XLSX
 * yang dapat dibuka Microsoft Excel maupun LibreOffice. Keduanya sudah
 * tersedia pada proyek, sehingga tidak ada pustaka baru yang ditambahkan.
 */
class EksporRiwayatService
{
    /**
     * Satu bagian ekspor.
     *
     * @param  array<int,string>  $kolom  judul kolom
     * @param  iterable<int,array<int,scalar|null>>  $baris
     */
    public static function bagian(string $judul, array $kolom, iterable $baris, ?string $keterangan = null): array
    {
        return compact('judul', 'kolom', 'baris', 'keterangan');
    }

    /**
     * @param  array<int,array{judul:string,kolom:array<int,string>,baris:iterable,keterangan:?string}>  $bagian
     */
    public function pdf(string $judulBerkas, array $bagian): Response
    {
        // Riwayat berisi banyak kolom, sehingga orientasi mendatar lebih terbaca
        return $this->pdfTampilan($judulBerkas, 'pdf.riwayat', [
            'judul'  => $judulBerkas,
            'bagian' => $bagian,
        ], 'landscape');
    }

    /**
     * Menerbitkan PDF dari tampilan mana pun.
     *
     * Dipisahkan dari pdf() karena tidak semua dokumen berbentuk tabel persegi
     * berisi bagian-bagian: kartu kendali persediaan, misalnya, memiliki blok
     * identitas barang serta baris stok awal dan stok akhir yang bukan baris
     * data. Penamaan berkas, penyematan waktu cetak, dan cara pengunduhannya
     * tetap satu jalur agar seluruh dokumen SIMPBI seragam.
     *
     * @param  array<string,mixed>  $data  data tampilan; menimpa nilai bawaan
     * @param  'portrait'|'landscape'  $orientasi
     */
    public function pdfTampilan(
        string $judulBerkas,
        string $tampilan,
        array $data,
        string $orientasi = 'portrait',
    ): Response {
        $pdf = Pdf::loadView($tampilan, [
            'dicetak'  => now(),
            'pencetak' => auth()->user(),
            ...$data,
        ]);

        $pdf->setPaper('a4', $orientasi);

        return response()->streamDownload(
            fn () => print($pdf->output()),
            $this->namaBerkas($judulBerkas, 'pdf'),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Berkas sebar XLSX. Setiap bagian menjadi satu lembar tersendiri, agar
     * ekspor keseluruhan tetap rapi ketika memuat beberapa jenis riwayat.
     *
     * @param  array<int,array{judul:string,kolom:array<int,string>,baris:iterable,keterangan:?string}>  $bagian
     */
    public function spreadsheet(string $judulBerkas, array $bagian): BinaryFileResponse
    {
        $berkas = tempnam(sys_get_temp_dir(), 'simpbi_');

        $tebal = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('1557A6'); // biru utama SIMPBI (Instruksi §25)

        $writer = new Writer();
        $writer->openToFile($berkas);

        foreach (array_values($bagian) as $i => $b) {
            // Lembar pertama sudah ada, lembar berikutnya dibuat baru
            $lembar = $i === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $lembar->setName($this->namaLembar($b['judul'], $i));

            $writer->addRow(Row::fromValues([$b['judul']]));
            if (filled($b['keterangan'])) {
                $writer->addRow(Row::fromValues([$b['keterangan']]));
            }
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues($b['kolom'], $tebal));

            foreach ($b['baris'] as $baris) {
                $writer->addRow(Row::fromValues(array_map(
                    fn ($nilai) => $nilai instanceof \DateTimeInterface
                        ? $nilai->format('d-m-Y H:i')
                        : $nilai,
                    $baris
                )));
            }
        }

        $writer->close();

        return response()
            ->download($berkas, $this->namaBerkas($judulBerkas, 'xlsx'))
            ->deleteFileAfterSend();
    }

    /** Nama berkas yang aman dan mudah diurutkan menurut waktu unduh. */
    protected function namaBerkas(string $judul, string $ekstensi): string
    {
        $bersih = preg_replace('/[^A-Za-z0-9]+/', '-', $judul);

        return trim($bersih, '-') . '-' . now()->format('Ymd-His') . '.' . $ekstensi;
    }

    /** Nama lembar XLSX dibatasi 31 karakter dan tidak boleh memuat : \ / ? * [ ] */
    protected function namaLembar(string $judul, int $urutan): string
    {
        // Memakai str_replace, bukan ekspresi reguler, agar daftar karakter
        // yang dilarang XLSX terbaca apa adanya tanpa pelolosan berlapis.
        $bersih = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $judul);
        $bersih = trim(mb_substr($bersih, 0, 31));

        return $bersih !== '' ? $bersih : 'Lembar ' . ($urutan + 1);
    }
}
