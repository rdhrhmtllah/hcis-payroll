<?php

namespace Tests\Feature\LMS\Content;

use App\Http\Controllers\LMS\Content\ContentManagementController;
use App\Services\LMS\TrainingScheduleQueryService;
use Tests\TestCase;

/**
 * URL webhook Cloud Task untuk unggahan konten.
 *
 * KENAPA ADA. Aturan perangkaian URL dipindahkan ke CloudTaskService supaya
 * pemanggil lain tak menulis versinya sendiri — tapi pemanggilnya di sini
 * memakai nama pendek "CloudTaskService" tanpa import. PHP lalu mencarinya di
 * namespace berkas ini, App\Http\Controllers\, dan tak menemukannya.
 *
 * Cacatnya tak kelihatan dari luar: SEMUA pemanggilnya duduk di dalam try/catch
 * yang menelan Throwable. Unggahan tetap berbalas sukses, kontennya tetap
 * tersimpan — hanya berhenti selamanya di status MENUNGGU karena tugas
 * verifikasinya tak pernah lahir. Yang tampak cuma satu baris di log.
 *
 * Diuji lewat helper-nya langsung karena tiga jalur — verifikasi, verifikasi
 * ulang, dan render ulang PDF — semuanya melewatinya, dan tak satu pun bisa
 * dijalankan di uji tanpa memanggil Google Cloud Tasks sungguhan.
 */
class KontenCloudTaskUrlTest extends TestCase
{
    private function url(string $path): string
    {
        $c = new ContentManagementController(app(TrainingScheduleQueryService::class));

        $m = new \ReflectionMethod($c, 'resolveCloudTaskExecuteUrl');
        $m->setAccessible(true);

        return $m->invoke($c, $path);
    }

    public function test_webhook_verifikasi_konten_punya_url_absolut(): void
    {
        $url = $this->url('/api/v1/tasks/konten/verifikasi');

        $this->assertMatchesRegularExpression('~^https?://~i', $url);
        $this->assertStringEndsWith('/api/v1/tasks/konten/verifikasi', $url);
    }

    public function test_webhook_render_pdf_punya_url_absolut(): void
    {
        // Jalur bawaan helper — dipakai render ulang dari Content Dashboard.
        $url = $this->url('/api/v1/tasks/lms/process-pdf');

        $this->assertMatchesRegularExpression('~^https?://~i', $url);
        $this->assertStringEndsWith('/api/v1/tasks/lms/process-pdf', $url);
    }
}
