<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use RuntimeException;

/**
 * Penolakan Balancing yang dikenali layar lewat `error_code`. Turunan RuntimeException,
 * jadi pintu lama yang menangkap RuntimeException (mis. penerbitan) tetap menjawab 422.
 */
class PaBalancingException extends RuntimeException
{
    public const SIKLUS_BELUM_DITUTUP = 'PA_BAL_SIKLUS_BELUM_DITUTUP';

    public const KURVA_BELUM_DIKUNCI = 'PA_BAL_KURVA_BELUM_DIKUNCI';

    public const PESERTA_TIDAK_LAYAK = 'PA_BAL_PESERTA_TIDAK_LAYAK';

    public const SESI_TIDAK_DITEMUKAN = 'PA_BAL_SESI_TIDAK_DITEMUKAN';

    public const SESI_TERKUNCI = 'PA_BAL_SESI_TERKUNCI';

    public const VERSI_BERUBAH = 'PA_BAL_VERSI_BERUBAH';

    public const NILAI_DI_LUAR_RENTANG = 'PA_BAL_NILAI_DI_LUAR_RENTANG';

    public const ALASAN_WAJIB = 'PA_BAL_ALASAN_WAJIB';

    public const PESERTA_BELUM_DITINJAU = 'PA_BAL_PESERTA_BELUM_DITINJAU';

    public const SESI_MASIH_TERBUKA = 'PA_BAL_SESI_MASIH_TERBUKA';

    public const BELUM_DIHITUNG_ULANG = 'PA_BAL_BELUM_DIHITUNG_ULANG';

    public const TIDAK_ADA_SESI_FINAL = 'PA_BAL_TIDAK_ADA_SESI_FINAL';

    public const SUDAH_ADA_YANG_TERBIT = 'PA_BAL_SUDAH_ADA_YANG_TERBIT';

    public const HITUNG_ULANG_TERTAHAN = 'PA_BAL_HITUNG_ULANG_TERTAHAN';

    public const DATA_TIDAK_SAH = 'PA_BAL_DATA_TIDAK_SAH';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
