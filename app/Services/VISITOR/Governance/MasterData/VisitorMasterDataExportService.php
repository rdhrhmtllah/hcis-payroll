<?php

namespace App\Services\VISITOR\Governance\MasterData;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export master data Visitor ke satu berkas Excel multi-lembar.
 *
 * Berkas ini juga TEMPLAT import: bentuknya diambil dari
 * `VisitorMasterDataFileFormat`, sumber yang sama dengan pembacanya, sehingga
 * berkas yang baru diunduh selalu bisa di-import balik. Kolom teknis (id,
 * hitungan pemakaian) dan kolom nomor urut sengaja tidak ditulis — keduanya
 * tidak berarti apa-apa ketika berkasnya dibaca kembali.
 */
final class VisitorMasterDataExportService
{
    public function __construct(private VisitorMasterDataService $data) {}

    /**
     * Ringkasan kering: lembar apa saja, berapa barisnya, mana yang tersaring.
     *
     * Dipakai sebagai dry run — tidak ada berkas yang ditulis dan tidak ada
     * unduhan yang dimulai. Lembarnya dibangun lewat jalur yang sama dengan
     * berkas sungguhan, jadi angka di pratinjau adalah angka yang akan terunduh.
     */
    public function pratinjau(string $domain, ?string $masterTersaring, array $filters): array
    {
        $lembar = $this->lembar($domain, $masterTersaring, $filters);

        return [
            'nama_berkas' => $this->namaBerkas($domain),
            'master_tersaring' => $this->masterTersaringSah($domain, $masterTersaring),
            'total_baris' => array_sum(array_column($lembar, 'baris')),
            'sheets' => array_map(static fn (array $item) => [
                'master' => $item['master'],
                'sheet' => $item['sheet'],
                'kolom' => $item['kolom'],
                'baris' => $item['baris'],
                'total' => $item['total'],
                'tersaring' => $item['tersaring'],
            ], $lembar),
        ];
    }

    public function spreadsheet(string $domain, ?string $masterTersaring, array $filters): Spreadsheet
    {
        $lembar = $this->lembar($domain, $masterTersaring, $filters);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach ($lembar as $index => $item) {
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($item['sheet']);

            $sheet->fromArray($item['kolom'], null, 'A1');
            if ($item['rows'] !== []) {
                $sheet->fromArray($item['rows'], null, 'A2');
            }

            $kolomTerakhir = Coordinate::stringFromColumnIndex(count($item['kolom']));
            $sheet->getStyle("A1:{$kolomTerakhir}1")->getFont()->setBold(true);
            $sheet->getStyle("A1:{$kolomTerakhir}1")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFEDE9FE');
            $sheet->getStyle("A1:{$kolomTerakhir}1")->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->freezePane('A2');

            foreach (range(1, count($item['kolom'])) as $nomorKolom) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($nomorKolom))->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function namaBerkas(string $domain): string
    {
        $judul = VisitorMasterDataFileFormat::JUDUL_DOMAIN[$domain] ?? 'Master_Data_Visitor';

        return $judul.'_'.Carbon::now()->format('Y-m-d').'.xlsx';
    }

    /**
     * Satu jalur pembangun lembar untuk pratinjau DAN berkas.
     *
     * @return list<array<string, mixed>>
     */
    private function lembar(string $domain, ?string $masterTersaring, array $filters): array
    {
        $tersaring = $this->masterTersaringSah($domain, $masterTersaring);

        return array_map(function (string $master) use ($tersaring, $filters): array {
            $ikutSaringan = $master === $tersaring && $this->adaSaringan($filters);
            $baris = $this->data->rowsForExport($master, $ikutSaringan ? $filters : []);
            $kolom = VisitorMasterDataFileFormat::kolom($master);

            return [
                'master' => $master,
                'sheet' => VisitorMasterDataFileFormat::sheet($master),
                'kolom' => array_column($kolom, 1),
                'rows' => array_map(fn (array $row) => $this->baris($kolom, $row), $baris),
                'baris' => count($baris),
                'total' => $ikutSaringan ? $this->data->countAll($master) : count($baris),
                'tersaring' => $ikutSaringan,
            ];
        }, VisitorMasterDataFileFormat::masterDomain($domain));
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: string}>  $kolom
     * @return list<string|int|float|null>
     */
    private function baris(array $kolom, array $row): array
    {
        return array_map(function (array $definisiKolom) use ($row) {
            $format = $definisiKolom[2] ?? null;

            // Kolom relasi ditulis sebagai NAMA master tujuannya. Label itu
            // sudah disiapkan pembaca datanya sebagai `<kunci>_label`, termasuk
            // kata "Semua" untuk relasi yang memang tidak dibatasi.
            $kunci = $format === 'relasi' ? $definisiKolom[0].'_label' : $definisiKolom[0];
            $nilai = $row[$kunci] ?? null;

            return VisitorMasterDataFileFormat::keTeks($format, $nilai);
        }, $kolom);
    }

    /**
     * Saringan layar hanya berlaku untuk master yang tabnya sedang dibuka, dan
     * hanya kalau master itu memang milik domain yang diekspor.
     */
    private function masterTersaringSah(string $domain, ?string $masterTersaring): ?string
    {
        if ($masterTersaring === null || $masterTersaring === '') {
            return null;
        }

        return in_array($masterTersaring, VisitorMasterDataFileFormat::masterDomain($domain), true)
            ? $masterTersaring
            : null;
    }

    /**
     * Halaman selalu mengirim `sort_by`/`sort_direction`; keduanya mengubah
     * urutan, bukan isi, jadi keduanya TIDAK dihitung sebagai saringan.
     * Menghitungnya akan menandai setiap lembar sebagai "tersaring" dan
     * membuat penanda itu kehilangan artinya.
     */
    private function adaSaringan(array $filters): bool
    {
        foreach ($filters as $kunci => $nilai) {
            if (in_array($kunci, ['sort_by', 'sort_direction', 'page', 'limit'], true)) {
                continue;
            }

            if ($nilai !== null && $nilai !== '') {
                return true;
            }
        }

        return false;
    }
}
