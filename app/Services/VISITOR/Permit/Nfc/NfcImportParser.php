<?php

namespace App\Services\VISITOR\Permit\Nfc;

use DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parser berkas Excel pemetaan/pairing NFC ID Card (Karyawan & PHL).
 *
 * Mengonversi lembar Excel menjadi data terstruktur tanpa mengakses basis data.
 * Kolom dicocokkan berdasarkan nama header (case-insensitive & toleran variasi).
 */
class NfcImportParser
{
    /**
     * Membaca berkas Excel dari path disk.
     *
     * @return array{valid: array, skipped_tanpa_nfc: array, total_baris: int}
     */
    public function parseFile(string $filePath, string $tipe = 'karyawan'): array
    {
        if (! file_exists($filePath)) {
            throw new DomainException("Berkas Excel tidak ditemukan: {$filePath}");
        }

        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        return $this->parseRows($rows, $tipe);
    }

    /**
     * Mem-parsing baris mentah array hasil toArray() spreadsheet.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{valid: array, skipped_tanpa_nfc: array, total_baris: int}
     */
    public function parseRows(array $rows, string $tipe = 'karyawan'): array
    {
        if (empty($rows)) {
            throw new DomainException('Berkas Excel kosong.');
        }

        $rawHeader = $rows[0] ?? [];
        $headerMap = $this->petakanHeader($rawHeader);

        $valid = [];
        $skipped = [];
        $totalDataRows = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if ($this->barisKosong($row)) {
                continue;
            }

            $totalDataRows++;
            $data = $this->ekstrakBaris($row, $headerMap);

            if ($data['nfc_hex'] !== null) {
                $valid[] = $data;
            } else {
                $skipped[] = $data;
            }
        }

        return [
            'valid' => $valid,
            'skipped_tanpa_nfc' => $skipped,
            'total_baris' => $totalDataRows,
        ];
    }

    /**
     * Memetakan indeks kolom header ke kunci internal.
     *
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private function petakanHeader(array $header): array
    {
        $map = [];

        foreach ($header as $idx => $val) {
            $clean = strtolower(trim((string) $val));
            $clean = str_replace(['_', '-'], ' ', $clean);

            if ($clean === 'nik') {
                $map['nik'] = $idx;
            } elseif (in_array($clean, ['kode karyawan', 'kode', 'id karyawan', 'nik sap'], true)) {
                $map['kode_karyawan'] = $idx;
            } elseif (in_array($clean, ['nama', 'nama karyawan', 'nama lengkap'], true)) {
                $map['nama'] = $idx;
            } elseif (in_array($clean, ['no mitra', 'nomor mitra', 'id mitra', 'mitra'], true)) {
                $map['no_mitra'] = $idx;
            } elseif (in_array($clean, ['nfc hex', 'hex', 'uid hex', 'nfc uid'], true)) {
                $map['nfc_hex'] = $idx;
            } elseif (in_array($clean, ['nfc parse', 'nfc inbio', 'inbio', 'parse', 'nfc dec'], true)) {
                $map['nfc_inbio'] = $idx;
            }
        }

        if (! isset($map['nfc_hex']) || (! isset($map['kode_karyawan']) && ! isset($map['nama']))) {
            throw new DomainException('Kolom wajib tidak ditemukan. Pastikan lembar memiliki kolom Kode Karyawan/Nama dan NFC HEX.');
        }

        return $map;
    }

    /**
     * Mengekstrak dan menormalkan data baris.
     *
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $map
     * @return array{nik: ?string, kode_karyawan: ?string, nama: ?string, no_mitra: ?string, nfc_hex: ?string, nfc_inbio: ?int}
     */
    private function ekstrakBaris(array $row, array $map): array
    {
        $ambil = fn (string $kunci): ?string => isset($map[$kunci]) && isset($row[$map[$kunci]]) && trim((string) $row[$map[$kunci]]) !== ''
            ? trim((string) $row[$map[$kunci]])
            : null;

        $rawHex = $ambil('nfc_hex');
        $nfcHex = $rawHex !== null ? strtoupper(preg_replace('/\s+/', '', $rawHex)) : null;

        $rawInbio = $ambil('nfc_inbio');
        $nfcInbio = null;
        if ($rawInbio !== null && is_numeric($rawInbio)) {
            $nfcInbio = (int) $rawInbio;
        }

        return [
            'nik' => $ambil('nik'),
            'kode_karyawan' => $ambil('kode_karyawan'),
            'nama' => $ambil('nama'),
            'no_mitra' => $ambil('no_mitra'),
            'nfc_hex' => $nfcHex !== '' ? $nfcHex : null,
            'nfc_inbio' => $nfcInbio,
        ];
    }

    private function barisKosong(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
