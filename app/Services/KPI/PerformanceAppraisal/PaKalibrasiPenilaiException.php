<?php

namespace App\Services\KPI\PerformanceAppraisal;

use RuntimeException;

/**
 * Penolakan Kalibrasi Penilai yang membawa kode stabil.
 *
 * Layar wizard bercabang pada `errorCode`, bukan pada kalimat: "lembar sudah terkirim" dan
 * "kategori ini tidak boleh dikalibrasi" menuntut tindakan berbeda, dan kalimatnya boleh
 * diperbaiki kapan saja tanpa mematahkan layar.
 */
class PaKalibrasiPenilaiException extends RuntimeException
{
    public const PENUGASAN_TIDAK_DITEMUKAN = 'PA_PENUGASAN_TIDAK_DITEMUKAN';

    public const SIKLUS_TIDAK_TERBUKA = 'PA_SIKLUS_TIDAK_TERBUKA';

    public const LEMBAR_BEKU = 'PA_LEMBAR_BEKU';

    public const KALIBRASI_DITUTUP = 'PA_KALIBRASI_DITUTUP';

    public const SKOR_DI_LUAR_RENTANG = 'PA_SKOR_DI_LUAR_RENTANG';

    public const KOMPETENSI_TIDAK_DI_LEMBAR = 'PA_KOMPETENSI_TIDAK_DI_LEMBAR';

    public const ALASAN_WAJIB = 'PA_ALASAN_WAJIB';

    public const TIDAK_ADA_PERUBAHAN = 'PA_TIDAK_ADA_PERUBAHAN';

    public const LANGKAH_TIDAK_DIKENAL = 'PA_LANGKAH_TIDAK_DIKENAL';

    public const SOAL_TIDAK_DI_LEMBAR = 'PA_SOAL_TIDAK_DI_LEMBAR';

    public const JAWABAN_KOSONG = 'PA_JAWABAN_KOSONG';

    public const JAWABAN_TERLALU_PANJANG = 'PA_JAWABAN_TERLALU_PANJANG';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
