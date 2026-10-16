<?php

namespace App\Services\VISITOR\Governance\MasterData;

use DomainException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use Throwable;

/**
 * Pembaca berkas Excel master data Visitor menjadi baris bertipe teks.
 *
 * Tugasnya berhenti di sini: mengubah lembar menjadi baris berlabel kolom.
 * Tidak ada aturan bisnis, tidak ada kueri, tidak ada tulisan ke basis data —
 * itu milik `VisitorMasterDataImportService`. Pemisahan ini yang membuat
 * seluruh bentuk berkas aneh (kolom tertukar, lembar asing, baris kosong di
 * tengah) bisa diuji tanpa satu pun sambungan basis data.
 *
 * Kolom dikenali dari NAMA HEADER, bukan posisinya. Berkas acuan punya kolom
 * "No" di depan dan pemakainya bebas menambah kolom catatan sendiri; keduanya
 * cukup diabaikan, bukan menggeser arti kolom di sebelahnya.
 */
final class VisitorMasterDataImportParser
{
    /**
     * Batas baris per lembar.
     *
     * Import berjalan sinkron di dalam satu permintaan HTTP. Berkas yang jauh
     * lebih besar dari ini bukan lagi "master data yang dikoreksi", dan
     * membiarkannya lewat hanya memindahkan kegagalannya ke batas waktu
     * permintaan — tanpa laporan, dan tanpa kepastian apa yang sudah masuk.
     */
    public const MAKS_BARIS = 2000;

    /**
     * @return array{
     *     lembar: array<string, array{sheet: string, kolom: list<string>, kolom_hilang: list<string>, baris: list<array{nomor: int, nilai: array<string, string>}>, galat: ?string}>,
     *     diabaikan: list<string>
     * }
     */
    public function baca(string $path, string $domain): array
    {
        $spreadsheet = $this->muat($path);
        $lembar = [];
        $diabaikan = [];

        foreach ($spreadsheet->getSheetNames() as $judul) {
            $master = VisitorMasterDataFileFormat::masterDariSheet($judul, $domain);

            if ($master === null || isset($lembar[$master])) {
                // Lembar asing dibiarkan lewat: berkas kerja pemakai sering
                // membawa lembar catatan sendiri, dan menolak seluruh berkas
                // karenanya hanya memaksa mereka membersihkan berkas yang
                // sebetulnya sah.
                $diabaikan[] = $judul;

                continue;
            }

            $lembar[$master] = $this->bacaLembar($spreadsheet->getSheetByName($judul)->toArray(null, true, false, false), $master);
        }

        $spreadsheet->disconnectWorksheets();

        return ['lembar' => $lembar, 'diabaikan' => $diabaikan];
    }

    private function muat(string $path): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
            $reader->setReadDataOnly(true);

            if (! $reader->canRead($path)) {
                throw new DomainException('Berkas tidak dapat dibaca sebagai Excel. Pastikan berkasnya .xlsx dan tidak rusak.');
            }

            return $reader->load($path);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DomainException('Berkas tidak dapat dibaca sebagai Excel. Pastikan berkasnya .xlsx dan tidak rusak.');
        }
    }

    /**
     * @param  list<list<mixed>>  $isi
     */
    private function bacaLembar(array $isi, string $master): array
    {
        $bentuk = VisitorMasterDataFileFormat::kolom($master);
        $sheet = VisitorMasterDataFileFormat::sheet($master);
        $headerBaris = $isi[0] ?? [];
        $posisi = $this->posisiKolom($headerBaris, $bentuk);

        // Kolom Status boleh tidak ada: berkas yang disusun sendiri oleh
        // pemakai biasanya hanya memuat data barunya. Kolom wajib yang hilang
        // adalah cerita lain — isinya tidak bisa ditebak.
        $wajib = array_values(array_filter(
            $bentuk,
            static fn (array $kolom): bool => ($kolom[2] ?? null) !== 'status',
        ));
        $hilang = array_values(array_diff(
            array_column($wajib, 1),
            array_keys($posisi),
        ));

        if ($hilang !== []) {
            return [
                'sheet' => $sheet,
                'kolom' => array_keys($posisi),
                'kolom_hilang' => $hilang,
                'baris' => [],
                'galat' => 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang).'.',
            ];
        }

        [$baris, $galat] = $this->bacaBaris($isi, $bentuk, $posisi);

        return [
            'sheet' => $sheet,
            'kolom' => array_keys($posisi),
            'kolom_hilang' => [],
            'baris' => $baris,
            'galat' => $galat,
        ];
    }

    /**
     * Header -> indeks kolom. Header kembar dimenangkan yang PERTAMA supaya
     * kolom yang tak sengaja tersalin di ujung kanan tidak menimpa kolom yang
     * sudah terisi benar.
     *
     * @param  list<mixed>  $headerBaris
     * @param  list<array{0: string, 1: string, 2?: string}>  $bentuk
     * @return array<string, int>
     */
    private function posisiKolom(array $headerBaris, array $bentuk): array
    {
        $dikenal = [];
        foreach ($bentuk as $kolom) {
            $dikenal[mb_strtolower($kolom[1])] = $kolom[1];
        }

        $posisi = [];
        foreach ($headerBaris as $indeks => $header) {
            $nama = $dikenal[mb_strtolower(trim((string) $header))] ?? null;

            if ($nama !== null && ! isset($posisi[$nama])) {
                $posisi[$nama] = $indeks;
            }
        }

        return $posisi;
    }

    /**
     * @param  list<list<mixed>>  $isi
     * @param  list<array{0: string, 1: string, 2?: string}>  $bentuk
     * @param  array<string, int>  $posisi
     * @return array{0: list<array{nomor: int, nilai: array<string, string>}>, 1: ?string}
     */
    private function bacaBaris(array $isi, array $bentuk, array $posisi): array
    {
        $baris = [];

        foreach ($isi as $indeks => $isiBaris) {
            if ($indeks === 0) {
                continue;
            }

            $nilai = [];
            foreach ($bentuk as $kolom) {
                $kolomPosisi = $posisi[$kolom[1]] ?? null;
                $nilai[$kolom[0]] = $kolomPosisi === null
                    ? ''
                    : trim((string) ($isiBaris[$kolomPosisi] ?? ''));
            }

            // Baris kosong di tengah lembar adalah jejak sunting, bukan data.
            if (implode('', $nilai) === '') {
                continue;
            }

            if (count($baris) >= self::MAKS_BARIS) {
                return [$baris, 'Lembar ini melebihi '.self::MAKS_BARIS.' baris. Pecah berkasnya lalu import bertahap.'];
            }

            // Nomor baris versi Excel — satu-satunya petunjuk yang bisa
            // dipakai pemakainya untuk menemukan barisnya kembali.
            $baris[] = ['nomor' => $indeks + 1, 'nilai' => $nilai];
        }

        return [$baris, null];
    }
}
