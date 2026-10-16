<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Services\KPI\KpiAkumulasiYtdService;
use App\Services\KPI\KpiExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Berkas export harus berisi apa yang barusan dilihat admin di layar.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * Monitoring Performance Tracking sudah punya mode YTD, dan panel Export
 * memuat daftar karyawannya dari universe YTD yang sama. Tetapi permintaan
 * export tidak pernah membawa 'mode', sehingga berkasnya dibangun dari SATU
 * bulan. Admin memilih orang dari akumulasi Januari–Mei lalu menerima berkas
 * berisi Mei saja — dua himpunan berbeda, tanpa satu pun tanda di layar, tanpa
 * satu pun galat. Ketidakcocokan yang diam seperti ini justru yang paling mahal:
 * angkanya terlihat sah, dan baru ketahuan saat dipakai orang lain.
 *
 * ── Yang ditegaskan ─────────────────────────────────────────────────────────
 *
 * Bukan sekadar "ada mode YTD", melainkan bahwa angka di berkas SAMA PERSIS
 * dengan KpiAkumulasiYtdService — sumber yang juga dipakai layar monitoring,
 * dashboard, dan `kpi:verify-ytd`. Kalau keempatnya boleh berbeda satu digit,
 * tidak satu pun dari mereka bisa dipercaya.
 */
class ExportPerformanceYtdTest extends TestCase
{
    public function test_dataset_ytd_memakai_angka_yang_sama_dengan_layar(): void
    {
        [$idUser, $periodeAkhir, $skorLayar] = $this->karyawanBerakumulasi();

        $dataset = $this->bangunDataset([
            'jenis' => 'performance',
            'scope_value' => $periodeAkhir,
            'mode' => 'ytd',
            'periods' => app(KpiAkumulasiYtdService::class)->periodeSampai(
                (int) substr($periodeAkhir, 0, 4),
                (int) substr($periodeAkhir, 5, 2),
            ),
            'id_users' => [$idUser],
            'schedule_meta' => $this->metaSeluruhJadwal((int) substr($periodeAkhir, 0, 4)),
        ]);

        $baris = collect($dataset['rows'])->firstWhere('status', 'Akumulasi');

        $this->assertNotNull($baris, 'Dataset YTD tidak memuat satu pun baris akumulasi.');
        $this->assertEqualsWithDelta(
            $skorLayar,
            (float) $baris['total_achievement'],
            0.0001,
            'Angka di berkas berbeda dari angka di layar. Keduanya wajib datang dari '.
            'KpiAkumulasiYtdService yang sama.',
        );

        // Sebuah akumulasi tidak disetujui siapa pun — yang disetujui transaksi
        // bulanannya. Kolom approval karena itu harus lenyap, bukan diisi karangan.
        $this->assertSame([], $baris['approval_flow'], 'Baris akumulasi tidak boleh membawa alur persetujuan.');
        $this->assertNull($baris['no_transaksi'], 'Akumulasi tidak punya satu No. Transaksi.');

        // Rincian wajib ikut: satu angka akhir yang tak bisa ditelusuri ke
        // Σtarget/Σactual-nya akan selalu jadi bantahan yang tak terjawab.
        $this->assertNotEmpty($baris['parameters'], 'Rincian parameter tidak ikut dicetak.');
        foreach ($baris['parameters'] as $p) {
            $this->assertArrayHasKey('nilai_target', $p);
            $this->assertArrayHasKey('nilai_actual', $p);
        }
    }

    /**
     * Yang dicetak wajib angka SESUDAH dijepit, bukan angka mentahnya.
     *
     * Uji di atas memakai data nyata, dan di lingkungan ini kebetulan tidak ada
     * seorang pun yang kena jepitan — jadi skor dan raw_pct-nya sama, dan
     * menukar keduanya tidak akan ketahuan. Uji ini menutup celah itu dengan
     * akumulator palsu yang keduanya sengaja dibuat berbeda: satu-satunya cara
     * lolos adalah benar-benar mengambil yang terjepit.
     */
    public function test_yang_dicetak_adalah_skor_terjepit_bukan_angka_mentah(): void
    {
        [$idUser, $periodeAkhir] = $this->karyawanBerakumulasi();

        $this->swap(KpiAkumulasiYtdService::class, new class($idUser) extends KpiAkumulasiYtdService
        {
            public function __construct(private int $idUser) {}

            public function rincianPerUser(array $userIds, int $year, array $periods): array
            {
                return [(string) $this->idUser => [
                    'skor' => 125.0,     // sesudah dijepit
                    'raw_pct' => 190.0,  // sebelum dijepit
                    'sum_weighted' => 190.0,
                    'total_bobot' => 100.0,
                    'parameter' => [[
                        'id' => 1, 'nama_parameter' => 'Uji', 'bsc' => 'Financial', 'satuan' => '%',
                        'rumus' => null, 'bobot' => 100.0, 'sum_target' => 10.0, 'sum_actual' => 19.0,
                        'score' => 190.0, 'achievement' => 190.0,
                    ]],
                ]];
            }
        });

        $dataset = $this->bangunDataset([
            'jenis' => 'performance',
            'scope_value' => $periodeAkhir,
            'mode' => 'ytd',
            'periods' => ['2026-01'],
            'id_users' => [$idUser],
            'schedule_meta' => $this->metaSeluruhJadwal((int) substr($periodeAkhir, 0, 4)),
        ]);

        $baris = collect($dataset['rows'])->firstWhere('status', 'Akumulasi');
        $this->assertNotNull($baris, 'Baris akumulasi tidak terbentuk.');

        $this->assertSame(
            125.0,
            (float) $baris['total_achievement'],
            'Berkas mencetak angka SEBELUM jepitan. Layar mencetak yang sesudah — '.
            'dua angka berbeda untuk orang yang sama.',
        );
        $this->assertSame(190.0, (float) $baris['total_achievement_raw'], 'Kolom sebelum-cap tidak terisi.');
        $this->assertTrue((bool) $baris['is_capped'], 'Barisnya tidak ditandai terjepit padahal jelas terjepit.');
    }

    public function test_label_periode_menyebut_rentangnya_bukan_satu_bulan(): void
    {
        [$idUser, $periodeAkhir] = $this->karyawanBerakumulasi();

        $dataset = $this->bangunDataset([
            'jenis' => 'performance',
            'scope_value' => $periodeAkhir,
            'mode' => 'ytd',
            'periods' => app(KpiAkumulasiYtdService::class)->periodeSampai(
                (int) substr($periodeAkhir, 0, 4),
                (int) substr($periodeAkhir, 5, 2),
            ),
            'id_users' => [$idUser],
            'schedule_meta' => $this->metaSeluruhJadwal((int) substr($periodeAkhir, 0, 4)),
        ]);

        $this->assertStringContainsString(
            'Akumulasi',
            $dataset['scope_label'],
            'Judul berkas YTD tidak menyebut bahwa isinya akumulasi — pembacanya akan '.
            'menyangka itu angka satu bulan.',
        );
    }

    public function test_mode_bulanan_tidak_ikut_berubah(): void
    {
        [$idUser, $periodeAkhir] = $this->karyawanBerakumulasi();

        $dataset = $this->bangunDataset([
            'jenis' => 'performance',
            'scope_value' => $periodeAkhir,
            'id_users' => [$idUser],
            'schedule_meta' => $this->metaSeluruhJadwal((int) substr($periodeAkhir, 0, 4)),
        ]);

        $this->assertStringNotContainsString(
            'Akumulasi',
            $dataset['scope_label'],
            'Mode bulanan ikut berubah jadi akumulasi.',
        );
        $this->assertSame(
            [],
            collect($dataset['rows'])->where('status', 'Akumulasi')->values()->all(),
            'Mode bulanan memuat baris akumulasi.',
        );
    }

    /**
     * Rantai penuh: sakelar di layar -> permintaan -> isi job.
     *
     * Dataset YTD bisa saja sempurna dan tetap tidak pernah terpakai, karena
     * yang membangunnya membaca payload job — dan payload itu dibuat controller.
     * Kalau controller tidak meneruskan 'mode', seluruh pekerjaan di bawahnya
     * jadi kode mati yang hijau di uji tetapi tidak pernah jalan sekali pun.
     */
    public function test_permintaan_export_meneruskan_mode_ke_isi_job(): void
    {
        Bus::fake();

        [, $periodeAkhir] = $this->karyawanBerakumulasi();

        $req = Request::create('/x', 'POST', [
            'jenis' => 'performance',
            'format' => 'excel',
            'scope_value' => $periodeAkhir,
            'mode' => 'ytd',
            'id_users' => [1],
        ]);

        $respons = app(KpiEnforcementController::class)->requestExport($req);
        $isi = json_decode($respons->getContent(), true);
        $exportId = $isi['result']['export_id'] ?? $isi['data']['export_id'] ?? null;

        $this->assertNotNull($exportId, 'Permintaan export tidak menghasilkan export_id: '.$respons->getContent());

        $payload = json_decode(
            (string) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $exportId)->value('Payload_Json'),
            true,
        );

        $this->assertSame('ytd', $payload['mode'] ?? null, "Controller tidak meneruskan 'mode' ke payload job.");
        $this->assertNotEmpty($payload['periods'] ?? [], 'Rentang periode YTD tidak ikut disimpan.');
        $this->assertSame(
            (int) substr($periodeAkhir, 5, 2),
            count($payload['periods']),
            'Rentangnya bukan Januari s/d bulan terpilih.',
        );

        DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $exportId)->delete();
    }

    /** @param  array<string,mixed>  $payload */
    private function bangunDataset(array $payload): array
    {
        $job = (object) [
            'Export_Id' => 'UJI-'.bin2hex(random_bytes(4)),
            'Format' => 'excel',
            'Payload_Json' => json_encode($payload),
            'Total_Records' => 1,
        ];

        $m = new \ReflectionMethod(KpiExportService::class, 'buildDataset');
        $m->setAccessible(true);

        return $m->invoke(app(KpiExportService::class), $job);
    }

    /** @return array{0:int,1:string,2:float} */
    private function karyawanBerakumulasi(): array
    {
        $svc = app(KpiAkumulasiYtdService::class);

        $tahun = (int) (DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->max('Tahun') ?: now()->year);

        $kandidat = DB::table('KPI_Pengajuan')->where('Flag_Aktif', 'Y')->distinct()->pluck('Id_User')->all();
        $skor = $svc->skorPerUser($kandidat, $tahun, $svc->periodeSampai($tahun, 12));

        if (empty($skor)) {
            $this->markTestSkipped('Tidak ada karyawan dengan realisasi terverifikasi di lingkungan ini.');
        }

        $idUser = (int) array_key_first($skor);

        return [$idUser, sprintf('%04d-12', $tahun), (float) $skor[(string) $idUser]];
    }

    /** Seluruh penjadwalan penilaian pada satu tahun, dalam bentuk meta export. */
    private function metaSeluruhJadwal(int $tahun): array
    {
        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Tahun', (string) $tahun)
            ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan']);

        if ($jadwal->isEmpty()) {
            $this->markTestSkipped('Tidak ada penjadwalan penilaian pada tahun uji.');
        }

        return [
            'schedule_ids' => $jadwal->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            'has_global' => $jadwal->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y'),
            'perorangan_ids' => $jadwal->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
                ->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            'expired' => false,
        ];
    }
}
