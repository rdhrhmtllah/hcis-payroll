<?php

namespace Tests\Unit\ESS\Attendance\Monitoring;

use App\Services\ESS\Attendance\Monitoring\PeriodeCutoff;
use Carbon\Carbon;
use Tests\TestCase;

class PeriodeCutoffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.hcis.cutoff_payroll_date' => ['start_day' => 16, 'end_day' => 15]]);
    }

    public function test_tanggal_akhir_periode_milik_periode_yang_berakhir_bulan_ini(): void
    {
        $p = PeriodeCutoff::untuk(Carbon::parse('2026-10-15'));

        $this->assertSame(['2026-09-16', '2026-10-15'], [$p['mulai'], $p['akhir']]);
        $this->assertSame('16 Sep – 15 Okt 2026', $p['label']);
    }

    public function test_tanggal_mulai_periode_membuka_periode_baru(): void
    {
        $p = PeriodeCutoff::untuk(Carbon::parse('2026-10-16'));

        $this->assertSame(['2026-10-16', '2026-11-15'], [$p['mulai'], $p['akhir']]);
    }

    public function test_periode_lintas_tahun(): void
    {
        $januari = PeriodeCutoff::untuk(Carbon::parse('2027-01-05'));
        $desember = PeriodeCutoff::untuk(Carbon::parse('2026-12-20'));

        $this->assertSame(['2026-12-16', '2027-01-15'], [$januari['mulai'], $januari['akhir']]);
        $this->assertSame($januari, $desember);
        $this->assertSame('16 Des 2026 – 15 Jan 2027', $januari['label']);
    }

    public function test_hari_akhir_dijepit_ke_akhir_bulan_pendek(): void
    {
        $p = PeriodeCutoff::untuk(Carbon::parse('2027-02-10'), 1, 31);

        $this->assertSame(['2027-02-01', '2027-02-28'], [$p['mulai'], $p['akhir']]);
    }

    public function test_daftar_menurun_tanpa_celah(): void
    {
        $daftar = PeriodeCutoff::daftar(Carbon::parse('2026-10-14'), 12);

        $this->assertCount(12, $daftar);
        $this->assertSame('2026-09-16', $daftar[0]['mulai']);
        for ($i = 1; $i < 12; $i++) {
            $this->assertSame(
                Carbon::parse($daftar[$i - 1]['mulai'])->subDay()->toDateString(),
                $daftar[$i]['akhir']
            );
        }
    }
}
