<?php

namespace Tests\Feature\ESS\Attendance;

use App\Http\Controllers\ESS\Attendance\Leave\Personal\CutiController;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Bug: submission cuti membooking hari kerja dari SELURUH rentang (requested_date_from..to),
 * mengabaikan submitted_dates → hari yang di-deselect user ikut terbooking & terpotong.
 *
 * Test ini mengunci fix: server hanya memilih tanggal yang benar-benar disubmit
 * (selected ⊆ submitted). Method buildCalendarAuditForSubmission bersifat READ-ONLY
 * (hanya query), jadi tidak ada tulisan/cleanup. Env-gated karena bergantung DB dev
 * (N_HRIS_FN_GetStatusHariRange + tabel kalender).
 *   RUN_REAL_CUTI_ENGINE_TEST=true php artisan test --filter CutiSubmitSelectedDates
 */
class CutiSubmitSelectedDatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! filter_var(env('RUN_REAL_CUTI_ENGINE_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_CUTI_ENGINE_TEST=true untuk menjalankan (butuh DB dev).');
        }
    }

    /** @test */
    public function server_hanya_memilih_tanggal_yang_disubmit_bukan_seluruh_rentang(): void
    {
        // 5 hari berturut di masa depan; user men-deselect hari ke-3 (tengah rentang).
        $base = Carbon::today()->addDays(40);
        $d = fn (int $i) => $base->copy()->addDays($i)->toDateString();
        $submitted = [$d(0), $d(1), $d(3), $d(4)]; // 4 hari (tanpa $d(2))

        $karyawan = (object) ['Kode_Perusahaan' => '001', 'Kode_Karyawan' => 'ZZBUG_TEST'];
        $jenisCuti = (object) ['Batas_Hari_Pengajuan' => 0];

        $m = new ReflectionMethod(CutiController::class, 'buildCalendarAuditForSubmission');
        $m->setAccessible(true);
        $result = $m->invoke(app(CutiController::class), $karyawan, $submitted, $d(0), $d(4), $jenisCuti);

        // Jika rentang uji kebetulan jatuh pada libur / status tak bisa divalidasi → skip (anti-flaky).
        if (empty($result['selected_dates'] ?? null)) {
            $this->markTestSkipped('Rentang uji tidak menghasilkan tanggal terpilih: '.($result['error'] ?? 'unknown'));
        }

        $selected = $result['selected_dates'];

        // INVARIANT FIX: setiap tanggal terpilih HARUS ada di submitted (selected ⊆ submitted).
        foreach ($selected as $date) {
            $this->assertContains($date, $submitted, "Tanggal {$date} terpilih padahal tidak disubmit user.");
        }

        // Hari yang di-deselect user TIDAK boleh terbooking (inti bug).
        $this->assertNotContains($d(2), $selected, 'Hari yang di-deselect (tengah rentang) tidak boleh masuk selected.');

        // Audit tidak boleh mencatat selected melebihi submitted.
        $sw = (int) ($result['audit']['summary']['selected_work_days'] ?? 0);
        $sd = (int) ($result['audit']['summary']['submitted_days'] ?? 0);
        $this->assertLessThanOrEqual($sd, $sw, 'selected_work_days tidak boleh melebihi submitted_days.');
    }
}
