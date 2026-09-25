<?php

namespace App\Services\Impor;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as PenulisXlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Pembentukan berkas template pengisian.
 *
 * Template dibangun dari daftar kolom yang sama dengan yang dibaca pengimpor,
 * sehingga tajuknya mustahil berbeda. Isinya dua lembar: lembar pertama
 * berisi tajuk beserta satu baris contoh, lembar kedua berisi petunjuk tiap
 * kolom.
 *
 * Baris contoh sengaja disertakan meski nanti harus dihapus pengisi. Berkas
 * yang hanya berisi tajuk kosong menyisakan pertanyaan yang tidak terjawab —
 * "Aktif" itu diisi ya/tidak, benar/salah, atau 1/0? Satu baris contoh
 * menjawab seluruh pertanyaan semacam itu tanpa perlu dibaca petunjuknya.
 *
 * Struktur yang dibaca PembacaBerkas tidak boleh bergeser, sebab seluruh
 * pengimpor bergantung padanya: lembar Data tetap lembar pertama, tajuk tetap
 * baris 1 dengan teks yang sama (bintang penanda wajib dibuang pembacanya),
 * dan baris contoh tetap baris 2 berisi teks yang sama. Yang ditambahkan hanya
 * rupa: tajuk bergaris dan berlatar, garis pada area isian, lebar kolom,
 * tajuk dibekukan, dan filter. Tidak dipakai objek Tabel Excel: tabel
 * mengunci rentang dan menambah baris otomatis, dan menguji keduanya terhadap
 * setiap cara orang mengisi lembar sebar tidak sebanding dengan manfaat
 * rupanya.
 *
 * Area isian (baris 2 sampai BARIS_AKHIR) diberi format sel menurut kolomnya
 * tanpa satu nilai pun: kolom kode berformat Teks agar nol di depan tidak
 * hilang saat diketik, dan kolom tanggal berformat DD/MM/YYYY. Sel yang hanya
 * berformat tidak berisi nilai, sehingga barisnya tetap dilewati pembaca
 * sebagai baris kosong.
 */
class PembuatTemplate
{
    /** Baris terakhir area isian yang diberi garis dan format sel. */
    public const BARIS_AKHIR = 1000;

    /** Format tanggal area isian; sama dengan bentuk teks yang diterima pengimpor. */
    public const FORMAT_TANGGAL = 'dd/mm/yyyy';

    protected const BIRU = '1557A6';   // biru utama SIMPBI (Instruksi §25)

    protected const GARIS = 'C9D1DE';

    /**
     * @param  list<Kolom>  $kolom
     * @param  list<string>  $petunjukTambahan  baris keterangan tambahan pada lembar Petunjuk
     */
    public function buat(string $judul, array $kolom, string $namaBerkas, array $petunjukTambahan = []): BinaryFileResponse
    {
        $buku = new Spreadsheet();
        $buku->getProperties()->setTitle('Template Impor ' . $judul)->setCreator('SIMPBI');

        $this->lembarData($buku->getActiveSheet(), $kolom);
        $this->lembarPetunjuk($buku->createSheet(), $judul, $kolom, $petunjukTambahan);

        $buku->setActiveSheetIndex(0);

        $berkas = tempnam(sys_get_temp_dir(), 'simpbi_tpl_');
        (new PenulisXlsx($buku))->save($berkas);
        $buku->disconnectWorksheets();

        return response()->download($berkas, $namaBerkas)->deleteFileAfterSend();
    }

    /** @param  list<Kolom>  $kolom */
    protected function lembarData(Worksheet $lembar, array $kolom): void
    {
        $lembar->setTitle('Data');
        $akhir = Coordinate::stringFromColumnIndex(count($kolom));

        foreach ($kolom as $i => $k) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);

            $lembar->setCellValueExplicit($huruf . '1', $k->judul . ($k->wajib ? ' *' : ''), DataType::TYPE_STRING);

            // Format area isian ditetapkan lebih dulu, lalu contohnya ditulis:
            // contoh tanggal ditulis sebagai tanggal sungguhan supaya pengisi
            // melihat bentuk yang diharapkan, contoh lain tetap teks seperti
            // template sebelumnya.
            $area = $huruf . '2:' . $huruf . static::BARIS_AKHIR;
            if ($k->format === Kolom::TEKS) {
                $lembar->getStyle($area)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            } elseif ($k->format === Kolom::TANGGAL) {
                $lembar->getStyle($area)->getNumberFormat()->setFormatCode(static::FORMAT_TANGGAL);
            }

            $tanggalContoh = $k->format === Kolom::TANGGAL ? $this->tanggalContoh($k->contoh) : null;
            if ($tanggalContoh !== null) {
                $lembar->setCellValue($huruf . '2', TanggalExcel::PHPToExcel($tanggalContoh));
            } elseif ($k->contoh !== '') {
                $lembar->setCellValueExplicit($huruf . '2', $k->contoh, DataType::TYPE_STRING);
            }

            $lembar->getColumnDimension($huruf)->setWidth($this->lebar($k));
        }

        $lembar->getStyle('A1:' . $akhir . '1')->applyFromArray($this->gayaTajuk());
        $lembar->getRowDimension(1)->setRowHeight(22);

        $lembar->getStyle('A2:' . $akhir . '2')->getFont()->setItalic(true)->getColor()->setRGB('8A94A6');
        $lembar->getStyle('A2:' . $akhir . static::BARIS_AKHIR)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => static::GARIS]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $lembar->freezePane('A2');
        $lembar->setAutoFilter('A1:' . $akhir . '1');
        $lembar->setSelectedCell('A2');
    }

    /**
     * @param  list<Kolom>  $kolom
     * @param  list<string>  $petunjukTambahan
     */
    protected function lembarPetunjuk(Worksheet $lembar, string $judul, array $kolom, array $petunjukTambahan): void
    {
        $lembar->setTitle('Petunjuk');
        $lembar->getColumnDimension('A')->setWidth(26);
        $lembar->getColumnDimension('B')->setWidth(9);
        $lembar->getColumnDimension('C')->setWidth(20);
        $lembar->getColumnDimension('D')->setWidth(90);

        $lembar->setCellValue('A1', 'Petunjuk Pengisian — ' . $judul);
        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(static::BIRU);

        $keterangan = [
            'Baris kedua pada lembar Data adalah contoh. Baris itu boleh ditimpa dengan data Anda atau dihapus; '
                . 'bila dibiarkan apa adanya, baris contoh dilewati saat impor dan tidak ikut tercatat.',
            'Kolom bertanda bintang wajib diisi. Urutan kolom boleh diubah; yang dicocokkan adalah judulnya.',
            ...$petunjukTambahan,
        ];

        $baris = 3;
        foreach ($keterangan as $teks) {
            $lembar->setCellValue('A' . $baris, '•  ' . $teks);
            $lembar->mergeCells('A' . $baris . ':D' . $baris);
            $lembar->getStyle('A' . $baris)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            // Tinggi baris ditaksir dari panjang teks, sebab sel gabungan tidak
            // disesuaikan tingginya sendiri oleh Excel.
            $lembar->getRowDimension($baris)->setRowHeight(15 * max(1, (int) ceil(mb_strlen($teks) / 130)));
            $baris++;
        }

        $baris++;
        $awalTabel = $baris;
        foreach (['Kolom', 'Wajib', 'Format isian', 'Keterangan'] as $i => $t) {
            $lembar->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $baris, $t);
        }
        $lembar->getStyle('A' . $baris . ':D' . $baris)->applyFromArray($this->gayaTajuk());

        foreach ($kolom as $k) {
            $baris++;
            $lembar->setCellValue('A' . $baris, $k->judul);
            $lembar->setCellValue('B' . $baris, $k->wajib ? 'Ya' : 'Tidak');
            $lembar->setCellValue('C' . $baris, match ($k->format) {
                Kolom::TEKS    => 'Teks',
                Kolom::TANGGAL => 'Tanggal DD/MM/YYYY',
                default        => 'Umum',
            });
            $lembar->setCellValue('D' . $baris, $k->catatan);
        }

        $lembar->getStyle('A' . ($awalTabel + 1) . ':D' . $baris)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => static::GARIS]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        ]);
        $lembar->getStyle('A' . ($awalTabel + 1) . ':A' . $baris)->getFont()->setBold(true);
    }

    /** @return array<string,mixed> */
    protected function gayaTajuk(): array
    {
        return [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => static::BIRU]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => static::BIRU]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
    }

    /** Lebar kolom menurut tajuk dan contohnya, dibatasi supaya tetap muat di layar. */
    protected function lebar(Kolom $k): float
    {
        return (float) min(45, max(14, mb_strlen($k->judul) + 6, mb_strlen($k->contoh) + 3));
    }

    /** Contoh kolom tanggal berbentuk DD/MM/YYYY menjadi tanggal; selain itu null. */
    protected function tanggalContoh(string $contoh): ?Carbon
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $contoh, $m) !== 1 || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }

        return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);
    }
}
