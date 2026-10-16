<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pemilih TAHUN monitoring submission tidak boleh bergantung pada jadwal aktif.
 *
 * ── Gejalanya ────────────────────────────────────────────────────────────────
 *
 * Saat tak ada penjadwalan Form KPI yang aktif, layar monitoring submission
 * tidak bisa memilih tahun sama sekali — sehingga seluruh riwayat pengajuan
 * tahun itu tak bisa dilihat, padahal datanya utuh di basis data.
 *
 * ── Kenapa ini bukan sekadar preferensi ──────────────────────────────────────
 *
 * Monitoring memandang ke BELAKANG. Pertanyaannya "tahun itu ada pengajuan atau
 * tidak", bukan "tahun itu masih dibuka atau tidak" — dan penutupan jadwal
 * adalah peristiwa yang PASTI terjadi pada setiap tahun yang sudah lewat. Jadi
 * menyaring Flag_Aktif menjamin setiap tahun lampau akhirnya hilang dari layar.
 *
 * Terukur 10 September 2026 di Web_HRIS: dari 11 penjadwalan Form KPI 2026,
 * DELAPAN sudah nonaktif. Tahun itu masih muncul semata karena tiga sisanya
 * kebetulan belum ditutup, sementara 913 pengajuan menggantung di belakangnya.
 *
 * Tiga saudara terdekatnya — schedulesInScope(), fetchMonthlyPeriods(),
 * getSchedulesInScope() — sudah lebih dulu melepas syarat itu. fetchYears()
 * satu-satunya yang tertinggal.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Sebuah tahun yang SELURUH penjadwalannya nonaktif tetap muncul di daftar.
 * Diuji lewat efek pada data yang disemai, bukan dengan membaca ulang kodenya.
 */
class TahunMonitoringLepasDariJadwalAktifTest extends TestCase
{
    use DatabaseTransactions;

    public function test_tahun_dengan_jadwal_nonaktif_tetap_bisa_dipilih(): void
    {
        // Tahun yang jauh di depan supaya tak bertabrakan dengan data nyata, dan
        // penjadwalannya SEMUA nonaktif — persis keadaan sebuah tahun yang sudah
        // selesai berjalan.
        $tahun = (string) (now()->year + 40);

        DB::table('KPI_Master_Penjadwalan')->insert([
            'Nama_Kegiatan' => 'Uji tahun nonaktif',
            'Tahun' => $tahun,
            'Periode' => $tahun.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'T',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'T',
        ]);

        $metode = new ReflectionMethod(KpiEnforcementController::class, 'fetchYears');
        $metode->setAccessible(true);

        $tahunTersedia = array_column(
            $metode->invoke(app(KpiEnforcementController::class), 'submission'),
            'value',
        );

        $this->assertContains(
            $tahun,
            $tahunTersedia,
            "Tahun {$tahun} tidak muncul di pemilih walaupun punya penjadwalan Form KPI. ".
            'Menyaring Flag_Aktif membuat setiap tahun lampau akhirnya lenyap dari layar '.
            'monitoring — beserta seluruh riwayat pengajuannya.',
        );
    }

    /**
     * Sisi kembarannya: pemilih PERIODE pada update performance tracking.
     *
     * fetchMonthlyPeriods() sudah lebih dulu melepas syarat Flag_Aktif, jadi uji
     * ini tidak memperbaiki apa pun — ia MENGUNCI keadaan yang sudah benar.
     * Alasannya: dua pemilih ini bersaudara dan sekali sudah terbukti bisa
     * berbeda nasib (fetchYears tertinggal sendirian). Menjaga hanya satu di
     * antaranya berarti mengundang pasangan yang sama terulang dari sisi lain.
     */
    public function test_periode_dengan_jadwal_nonaktif_tetap_bisa_dipilih(): void
    {
        $tahun = (string) (now()->year + 41);
        $periode = $tahun.'-05';

        DB::table('KPI_Master_Penjadwalan')->insert([
            'Nama_Kegiatan' => 'Uji periode nonaktif',
            'Tahun' => $tahun,
            'Periode' => $periode,
            'Flag_Form_KPI' => 'T',
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'T',
        ]);

        $metode = new ReflectionMethod(KpiEnforcementController::class, 'fetchMonthlyPeriods');
        $metode->setAccessible(true);

        $tersedia = array_column(
            $metode->invoke(app(KpiEnforcementController::class), 'penilaian'),
            'value',
        );

        $this->assertContains(
            $periode,
            $tersedia,
            "Periode {$periode} tidak muncul di pemilih update performance tracking walaupun ".
            'punya penjadwalan penilaian. Sama seperti pemilih tahun: monitoring memandang ke '.
            'belakang, jadi periode yang jadwalnya sudah ditutup justru yang paling sering dilihat.',
        );
    }
}
