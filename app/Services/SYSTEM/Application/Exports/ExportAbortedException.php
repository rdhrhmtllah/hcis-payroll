<?php

namespace App\Services\SYSTEM\Application\Exports;

/**
 * Dilempar saat job export terdeteksi DIBATALKAN di tengah proses (cek berkala di
 * bumpProgress). process() menangkapnya sebagai pembatalan bersih — TIDAK menimpa
 * status job (yang sudah 'failed' + payload aborted oleh abort()).
 */
class ExportAbortedException extends \RuntimeException
{
    public function __construct(public string $exportId)
    {
        parent::__construct("Export {$exportId} dibatalkan oleh pengguna.");
    }
}
