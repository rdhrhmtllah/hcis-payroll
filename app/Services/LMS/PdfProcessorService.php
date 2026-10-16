<?php

namespace App\Services\LMS;

use RuntimeException;

/**
 * PdfProcessorService — dipanggil oleh Cloud Task webhook untuk mem-pre-render
 * SELURUH halaman PDF menjadi gambar di storage (GCS/local) dalam satu jalur.
 *
 * Logika render sebenarnya ada di SecurePdfPageRenderService agar path & versi
 * render konsisten dengan yang dibaca oleh viewer (securePdfPageImage).
 */
class PdfProcessorService
{
    public function __construct(
        private readonly SecurePdfPageRenderService $renderService = new SecurePdfPageRenderService,
    ) {}

    /**
     * Render semua halaman dari sebuah berkas PDF ke storage.
     *
     * @param  object  $berkas  Row dari N_LMS_Master_Konten_Berkas
     *                          (butuh Id_Master_Konten_Berkas + File_Path).
     * @return int Jumlah halaman yang dirender.
     */
    public function processPdfToImages(object $berkas): int
    {
        $berkasId = (int) ($berkas->Id_Master_Konten_Berkas ?? 0);
        $filePath = (string) ($berkas->File_Path ?? '');

        if ($berkasId < 1 || $filePath === '') {
            throw new RuntimeException('Berkas tidak valid: Id atau File_Path kosong.');
        }

        return $this->renderService->renderAllPages($berkasId, $filePath);
    }
}
