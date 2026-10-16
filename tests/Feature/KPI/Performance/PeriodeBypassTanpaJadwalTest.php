<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Acuan periode by-pass boleh diatur TANPA memilih penjadwalan.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Endpoint-nya dulu menuntut id_master_penjadwalan sebagai 'required', jadi
 * mengganti bulan saja mustahil tanpa lebih dulu memilih jadwal — padahal jadwal
 * yang tepat untuk bulan itu justru yang sedang dicari. Sekarang periodenya yang
 * menentukan, dan jadwalnya diturunkan dari periode itu bila tidak dipilih.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 *  1. Tanpa penjadwalan tetap diterima, dan Id_Master_Penjadwalan disimpan NULL.
 *     NULL itu bukan kelalaian: ia tanda "ikuti periode saja", dan seluruh
 *     pembacanya bergantung pada tanda itu.
 *
 *  2. Jadwal yang diturunkan mengikuti PERIODE, bukan tahun berjalan. Memakai
 *     tahun berjalan membuat periode yang sengaja dipilih membuka bulan lain —
 *     dan membuka bulan yang berbeda dari yang diminta adalah bug yang sudah
 *     berulang di jalur by-pass ini.
 *
 *  3. Penjadwalan yang DIPILIH tetap dihormati, dan kunci yang tak bisa didecode
 *     tetap ditolak 400 — opsional bukan berarti nilai ngawur ikut diterima.
 */
class PeriodeBypassTanpaJadwalTest extends TestCase
{
    use DatabaseTransactions;

    private function controller(): UpdatePerformanceController
    {
        return app(UpdatePerformanceController::class);
    }

    private function acuan(): ?object
    {
        return DB::table('KPI_Get_Date_Dummy2')->where('Flag_Aktif', 'Y')->first();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->acuan()) {
            $this->markTestSkipped('Tidak ada baris acuan KPI_Get_Date_Dummy2 yang aktif.');
        }
    }

    public function test_tanpa_penjadwalan_tetap_diterima_dan_disimpan_null(): void
    {
        $respons = $this->controller()->updateGetDateDummy2(
            Request::create('/x', 'POST', ['bulan' => 3, 'tahun' => 2026]),
        );

        $this->assertSame(200, $respons->getStatusCode(), 'Mengganti periode saja masih ditolak.');

        $baris = $this->acuan();
        $this->assertSame('2026-03', Carbon::parse($baris->Tanggal)->format('Y-m'));
        $this->assertNull(
            $baris->Id_Master_Penjadwalan,
            'Id_Master_Penjadwalan harus NULL — itulah tanda "ikuti periode saja".',
        );
    }

    public function test_jadwal_diturunkan_dari_periode_bukan_tahun_berjalan(): void
    {
        $m = new ReflectionMethod(UpdatePerformanceController::class, 'jadwalPenilaianUntukPeriode');
        $m->setAccessible(true);

        $periode = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->whereNotNull('Periode')
            ->distinct()
            ->pluck('Periode');

        if ($periode->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua periode jadwal penilaian yang berbeda.');
        }

        foreach ($periode->take(4) as $bulan) {
            $jadwal = $m->invoke($this->controller(), Carbon::parse($bulan.'-01'));

            $this->assertNotNull($jadwal, "Tidak ada jadwal yang bisa diturunkan untuk periode {$bulan}.");
            $this->assertSame(
                $bulan,
                $jadwal->Periode,
                "Periode {$bulan} menurunkan jadwal berperiode {$jadwal->Periode}. ".
                'Periode yang persis harus menang — kalau tidak, halaman membuka bulan '.
                'yang berbeda dari yang diminta.',
            );
        }
    }

    public function test_penjadwalan_yang_dipilih_tetap_dihormati(): void
    {
        $id = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->value('Id_Master_Penjadwalan');

        if (! $id) {
            $this->markTestSkipped('Tidak ada jadwal penilaian di data ini.');
        }

        $respons = $this->controller()->updateGetDateDummy2(
            Request::create('/x', 'POST', [
                'bulan' => 3,
                'tahun' => 2026,
                'id_master_penjadwalan' => Hashids::connection('custom')->encode((int) $id),
            ]),
        );

        $this->assertSame(200, $respons->getStatusCode());
        $this->assertSame((int) $id, (int) $this->acuan()->Id_Master_Penjadwalan);
    }

    public function test_kunci_yang_tidak_bisa_didecode_tetap_ditolak(): void
    {
        $respons = $this->controller()->updateGetDateDummy2(
            Request::create('/x', 'POST', [
                'bulan' => 3,
                'tahun' => 2026,
                'id_master_penjadwalan' => 'bukan-hash-yang-sah',
            ]),
        );

        $this->assertSame(
            400,
            $respons->getStatusCode(),
            'Opsional bukan berarti nilai ngawur ikut diterima — kunci rusak harus tetap ditolak, '.
            'bukan diam-diam dianggap "tidak memilih".',
        );
    }
}
