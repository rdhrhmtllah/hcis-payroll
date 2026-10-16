<?php

namespace App\Console\Commands\VISITOR\Permit;

use App\Services\VISITOR\Permit\Nfc\NfcImportParser;
use App\Services\VISITOR\Permit\Nfc\NfcImportService;
use Illuminate\Console\Command;

class ImportNfcExcelCommand extends Command
{
    protected $signature = 'visitor:import-nfc
        {--karyawan= : Path berkas Excel pairing NFC karyawan}
        {--phl= : Path berkas Excel pairing NFC PHL/mitra}';

    protected $description = 'Mengimpor data pairing ID Card / NFC dari berkas Excel ke N_EMI_Master_NFC dan N_EMI_Binding_NFC';

    public function handle(NfcImportParser $parser, NfcImportService $importer): int
    {
        $berkas = array_filter([
            'karyawan' => $this->option('karyawan'),
            'phl' => $this->option('phl'),
        ]);

        if ($berkas === []) {
            $this->error('Berikan --karyawan=<path> dan/atau --phl=<path>.');

            return self::FAILURE;
        }

        foreach ($berkas as $tipe => $path) {
            if (! file_exists($path)) {
                $this->error("Berkas tidak ditemukan: {$path}");

                return self::FAILURE;
            }

            $this->info("Memproses impor {$tipe} dari: {$path}");
            $parsed = $parser->parseFile($path, $tipe);
            $hasil = $importer->import($parsed['valid'], $tipe, 'CLI_IMPORT');
            $this->info(sprintf(
                '%s: master baru %d, pairing baru %d, diaktifkan ulang %d, sudah terdaftar %d, konflik %d, tanpa NFC %d',
                $tipe,
                $hasil['imported_master'],
                $hasil['imported_binding'],
                $hasil['updated_binding'],
                $hasil['skipped_sudah_terdaftar'],
                $hasil['skipped_konflik'],
                count($parsed['skipped_tanpa_nfc']),
            ));
        }

        return self::SUCCESS;
    }
}
