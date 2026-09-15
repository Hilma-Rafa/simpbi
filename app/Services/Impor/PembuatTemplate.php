<?php

namespace App\Services\Impor;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
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
 */
class PembuatTemplate
{
    /**
     * @param  list<Kolom>  $kolom
     */
    public function buat(string $judul, array $kolom, string $namaBerkas): BinaryFileResponse
    {
        $berkas = tempnam(sys_get_temp_dir(), 'simpbi_tpl_');

        $tajuk = (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('1557A6');   // biru utama SIMPBI (Instruksi §25)

        $contoh = (new Style())->setFontColor('8A94A6')->setFontItalic();
        $tebal  = (new Style())->setFontBold();

        $writer = new Writer();
        $writer->openToFile($berkas);

        // ---------- Lembar data ----------
        $writer->getCurrentSheet()->setName('Data');

        $writer->addRow(Row::fromValues(
            array_map(fn (Kolom $k) => $k->judul . ($k->wajib ? ' *' : ''), $kolom),
            $tajuk,
        ));

        $writer->addRow(Row::fromValues(
            array_map(fn (Kolom $k) => $k->contoh, $kolom),
            $contoh,
        ));

        // ---------- Lembar petunjuk ----------
        $writer->addNewSheetAndMakeItCurrent()->setName('Petunjuk');

        $writer->addRow(Row::fromValues(['Petunjuk Pengisian — ' . $judul], $tebal));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Baris kedua pada lembar Data adalah contoh. Hapus baris itu sebelum mengunggah.',
        ]));
        $writer->addRow(Row::fromValues([
            'Kolom bertanda bintang wajib diisi. Urutan kolom boleh diubah; yang dicocokkan adalah judulnya.',
        ]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Kolom', 'Wajib', 'Keterangan'], $tajuk));

        foreach ($kolom as $k) {
            $writer->addRow(Row::fromValues([
                $k->judul,
                $k->wajib ? 'Ya' : 'Tidak',
                $k->catatan,
            ]));
        }

        $writer->close();

        return response()->download($berkas, $namaBerkas)->deleteFileAfterSend();
    }
}
