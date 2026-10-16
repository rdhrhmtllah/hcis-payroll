<?php

namespace App\Services\KPI\PerformanceAppraisal;

use RuntimeException;

/**
 * Penerbitan ditahan karena ada kategori penilai yang gugur (tidak memenuhi minimal pengisi)
 * pada hasil peserta. Dipisah dari RuntimeException biasa supaya layar bisa menawarkan
 * "terbitkan dengan sadar" lewat kode galat yang stabil, bukan mencocokkan kalimat.
 */
class PaKategoriGugurException extends RuntimeException
{
    public const KODE = 'PA_KATEGORI_GUGUR';
}
