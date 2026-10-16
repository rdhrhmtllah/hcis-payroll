<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaHitungTugasService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hitung siklus sebagai TUGAS LATAR (14 Okt 2026).
 *
 * Mesin hitung dan gerbangnya diganti tiruan: yang diuji di sini RANTAINYA — potongan demi
 * potongan lewat kursor, kemajuan terakumulasi, berhenti saat habis, dan Coba Lagi melanjutkan
 * dari kursor. Kebenaran angkanya dijaga uji mesin hitung sendiri. Antrean di phpunit `sync`,
 * jadi seluruh rantai berjalan di dalam permintaan uji.
 */
class HitungLatarTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    /** @var list<array{batas:?int, mulai:?int, lingkup:string}> */
    public array $panggilan = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->cycleId = (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-HTG-'.bin2hex(random_bytes(3)), 'Nama_Cycle' => 'Siklus Uji Hitung Latar', 'Tahun' => 2026,
            'Status_Cycle' => 'CLOSED', 'Periode_Dinilai_Dari' => '2026-01-01', 'Periode_Dinilai_Sampai' => '2026-06-30',
        ], 'Id_PA_Cycle');

        $this->mock(PaCycleGate::class, function ($m) {
            $m->shouldReceive('pastikanBoleh')->andReturnNull();
            $m->shouldReceive('status')->andReturn('CLOSED');
        });

        $uji = $this;
        $this->app->instance(PaResultCalculationService::class, new class($uji) extends PaResultCalculationService
        {
            /** 120 peserta ber-id 1..120; peserta 7 selalu tertahan, peserta 9 selalu gagal. */
            public function __construct(private HitungLatarTest $uji) {}

            public function jumlahAkanDihitung(int $cycleId, string $lingkup = self::LINGKUP_SEMUA): int
            {
                return 120;
            }

            public function hitungSiklus(int $cycleId, ?int $olehUserId = null, ?int $batas = null, ?int $mulaiDariId = null, string $lingkup = self::LINGKUP_SEMUA): array
            {
                $this->uji->panggilan[] = ['batas' => $batas, 'mulai' => $mulaiDariId, 'lingkup' => $lingkup];
                $ids = array_slice(array_values(array_filter(range(1, 120), fn ($i) => $i > (int) $mulaiDariId)), 0, $batas);

                return [
                    'dihitung' => count(array_diff($ids, [7, 9])),
                    'tertahan' => in_array(7, $ids, true) ? 1 : 0,
                    'gagal' => in_array(9, $ids, true) ? [['participant_id' => 9, 'pesan' => 'sudah terbit']] : [],
                    'diproses' => count($ids),
                    'terakhir_id' => $ids === [] ? null : end($ids),
                    'selesai' => count($ids) < $batas,
                ];
            }
        });
    }

    private function admin(): User
    {
        $userId = DB::table('HRIS_Page_Access')->where('Jenis_page', 'PaHasilPage')->where('Flag_Aktif', 'Y')
            ->whereNotNull('UserID_Web')->value('UserID_Web');
        $this->assertNotNull($userId, 'Tidak ada user berakses PaHasilPage.');

        return User::find($userId);
    }

    public function test_rantai_menghitung_seluruh_siklus_lewat_kursor_lalu_selesai(): void
    {
        $r = $this->actingAs($this->admin())
            ->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung/latar", ['lingkup' => 'BELUM'])
            ->assertOk();

        $this->assertTrue($r->json('result.baru'));

        // 120 peserta / 50 per job = 3 potongan, kursor diteruskan, lingkup dibawa.
        $this->assertSame([[50, null], [50, 50], [50, 100]], array_map(fn ($p) => [$p['batas'], $p['mulai']], $this->panggilan));
        $this->assertSame(['BELUM'], array_values(array_unique(array_column($this->panggilan, 'lingkup'))));

        $tugas = $this->getJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung/latar")->assertOk()->json('result.tugas');
        $this->assertSame('ready', $tugas['status']);
        $this->assertSame(100, $tugas['persen']);
        $this->assertSame(['total' => 120, 'sudah' => 120], $tugas['ringkasan']);
        $this->assertSame(118, $tugas['dihitung']);
        $this->assertSame(1, $tugas['tertahan']);
        $this->assertSame(1, $tugas['jumlah_galat']);
        $this->assertSame('BELUM', $tugas['lingkup']);
    }

    public function test_tugas_yang_masih_berjalan_dikembalikan_bukan_dimulai_dua_kali(): void
    {
        $tugas = app(PaHitungTugasService::class);
        $ada = $tugas->mulai($this->cycleId, ['total' => 120, 'sudah' => 0], null, null);

        $r = $this->actingAs($this->admin())
            ->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung/latar")
            ->assertOk();

        $this->assertFalse($r->json('result.baru'));
        $this->assertSame($ada['id'], $r->json('result.tugas.id'));
        $this->assertSame([], $this->panggilan, 'Klik kedua tidak boleh memulai rantai kedua.');
    }

    public function test_coba_lagi_melanjutkan_dari_kursor(): void
    {
        $tugas = app(PaHitungTugasService::class);
        $id = $tugas->mulai($this->cycleId, ['total' => 120, 'sudah' => 0], null, null)['id'];
        $tugas->catat($id, ['mulai_dari' => 100, 'ringkasan' => ['total' => 120, 'sudah' => 100], 'dihitung' => 98, 'tertahan' => 1, 'jumlah_galat' => 1]);
        $tugas->gagal($id, 'Pekerja antrean mati.');

        $this->actingAs($this->admin())
            ->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung/latar/ulangi")
            ->assertOk();

        $this->assertSame([[50, 100]], array_map(fn ($p) => [$p['batas'], $p['mulai']], $this->panggilan));
        $akhir = $tugas->bentukLayar($tugas->baris($id));
        $this->assertSame('ready', $akhir['status']);
        $this->assertSame(['total' => 120, 'sudah' => 120], $akhir['ringkasan']);
        $this->assertSame(118, $akhir['dihitung']);
    }

    public function test_siklus_yang_belum_ditutup_ditolak_di_muka(): void
    {
        $this->mock(PaCycleGate::class, function ($m) {
            $m->shouldReceive('pastikanBoleh')->andReturnNull();
            $m->shouldReceive('status')->andReturn('OPEN');
        });

        $this->actingAs($this->admin())
            ->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/hitung/latar")
            ->assertStatus(422);

        $this->assertNull(app(PaHitungTugasService::class)->terakhirUntukSiklus($this->cycleId));
    }
}
