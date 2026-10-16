<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tanggal masuk karyawan ikut tampil di bawah nama & kode.
 *
 * Permintaan user 6 Okt 2026: di KPI Submission, Performance Tracker, dan
 * export datanya, admin perlu melihat kapan karyawan itu join — sumbernya
 * Karyawan.Tgl_Masuk, bukan isian lain. Layar menerima ISO (Y-m-d) supaya
 * format tampilannya diputuskan satu rumah di frontend; berkas export
 * menerima teks siap baca karena Excel/PDF tak punya pemformat itu.
 */
class TglMasukKaryawanTest extends TestCase
{
    private function sebagaiAdmin(): User
    {
        $admin = User::first();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }
        Auth::setUser($admin);

        return $admin;
    }

    /** @return array<int,array<string,mixed>> */
    private function baris(string $metode, array $query): array
    {
        $admin = $this->sebagaiAdmin();
        $req = Request::create('/x', 'GET', $query + ['per_page' => 50, 'page' => 1]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $res = app(KpiEnforcementController::class)->{$metode}($req);
        if ($res->getStatusCode() !== 200) {
            $this->markTestSkipped('Tidak ada penjadwalan pada scope uji.');
        }
        $isi = json_decode($res->getContent(), true);
        $rows = $isi['result']['rows'] ?? $isi['result'] ?? [];
        if (empty($rows)) {
            $this->markTestSkipped('Scope uji tidak memuat satu karyawan pun.');
        }

        return $rows;
    }

    /** Tgl_Masuk asli per kode karyawan, sebagai Y-m-d (null bila kosong). */
    private function tglMasukAsli(array $rows): array
    {
        return DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', array_column($rows, 'kode_karyawan'))
            ->pluck('Tgl_Masuk', 'Kode_Karyawan')
            ->map(fn ($v) => $v ? Carbon::parse($v)->format('Y-m-d') : null)
            ->all();
    }

    private function tegaskanSamaDenganKaryawan(array $rows): void
    {
        $asli = $this->tglMasukAsli($rows);
        foreach ($rows as $r) {
            $this->assertArrayHasKey('tgl_masuk', $r, 'Baris monitoring tidak membawa tgl_masuk.');
            $this->assertSame($asli[$r['kode_karyawan']] ?? null, $r['tgl_masuk'], "Tgl masuk {$r['kode_karyawan']} tidak sama dengan Karyawan.Tgl_Masuk.");
        }
        $this->assertNotEmpty(array_filter(array_column($rows, 'tgl_masuk')), 'Tak satu baris pun berisi tgl_masuk.');
    }

    public function test_kpi_submission_membawa_tgl_masuk_karyawan(): void
    {
        $tahun = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Form_KPI', 'Y')->max('Tahun');

        $this->tegaskanSamaDenganKaryawan($this->baris('getSubmissionStatus', ['tahun' => $tahun]));
    }

    public function test_performance_tracker_membawa_tgl_masuk_karyawan(): void
    {
        $periode = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Penilaian_KPI', 'Y')->max('Periode');

        $this->tegaskanSamaDenganKaryawan($this->baris('getPerformanceTracking', ['periode' => $periode, 'mode' => 'bulan']));
    }

    public function test_performance_tracker_ytd_membawa_tgl_masuk_karyawan(): void
    {
        $periode = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Penilaian_KPI', 'Y')->max('Periode');

        $this->tegaskanSamaDenganKaryawan($this->baris('getPerformanceTracking', ['periode' => $periode, 'mode' => 'ytd']));
    }

    /** Kolom Excel "Tgl Masuk" berdiri tepat setelah Nama, di kedua jenis berkas. */
    public function test_kolom_excel_tgl_masuk_tepat_setelah_nama(): void
    {
        $m = new \ReflectionMethod(KpiExportService::class, 'excelColumns');
        $m->setAccessible(true);

        foreach ([false, true] as $isPerf) {
            $kunci = array_column($m->invoke(app(KpiExportService::class), $isPerf, 0), 'key');
            $i = array_search('nama', $kunci, true);
            $this->assertSame('tgl_masuk', $kunci[$i + 1] ?? null, 'Kolom Tgl Masuk tidak tepat setelah Nama.');
        }
    }

    /** Dataset export (semua jalur baris) membawa tgl_masuk yang sudah terformat. */
    public function test_dataset_export_membawa_tgl_masuk_terformat(): void
    {
        $this->sebagaiAdmin();

        foreach ([['parameter', 'Flag_Form_KPI', 'Tahun'], ['performance', 'Flag_Penilaian_KPI', 'Periode']] as [$jenis, $flag, $kolom]) {
            $scope = (string) DB::table('KPI_Master_Penjadwalan')->where($flag, 'Y')->max($kolom);
            $jadwal = DB::table('KPI_Master_Penjadwalan')->where($flag, 'Y')->where($kolom, $scope)
                ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan']);
            if ($jadwal->isEmpty()) {
                $this->markTestSkipped("Tidak ada penjadwalan {$jenis}.");
            }

            $dataset = $this->bangunDataset([
                'jenis' => $jenis,
                'scope_value' => $scope,
                'select_all' => true,
                'filters' => [],
                'schedule_meta' => [
                    'schedule_ids' => $jadwal->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
                    'has_global' => $jadwal->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y'),
                    'perorangan_ids' => $jadwal->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
                        ->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
                    'expired' => false,
                ],
            ]);

            $rows = $dataset['rows'] ?? [];
            if (empty($rows)) {
                $this->markTestSkipped("Dataset {$jenis} kosong.");
            }
            $asli = DB::table('Karyawan')->whereIn('Kode_Karyawan', array_column($rows, 'kode_karyawan'))
                ->pluck('Tgl_Masuk', 'Kode_Karyawan');
            foreach ($rows as $r) {
                $this->assertArrayHasKey('tgl_masuk', $r, "Baris export {$jenis} tidak membawa tgl_masuk.");
                $t = $asli[$r['kode_karyawan']] ?? null;
                $this->assertSame($t ? Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '-', $r['tgl_masuk']);
            }
        }
    }

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
}
