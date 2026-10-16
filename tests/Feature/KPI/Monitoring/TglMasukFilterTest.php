<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiExportService;
use App\Services\KPI\KpiFilterTglMasuk;
use App\Services\KPI\KpiKepegawaianPeriode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filter rentang Tgl Masuk di KPI Submission, Performance Tracking, dan Export.
 *
 * Permintaan user 6 Okt 2026, setelah Tgl Masuk tampil di bawah nama: admin
 * ingin menyaring karyawan menurut tanggal join-nya. Rentangnya inklusif di
 * kedua ujung — rentang satu hari [d, d] wajib memuat karyawan yang masuk
 * tepat pada d. Export "pilih semua" harus menyaring dengan kriteria yang
 * sama, kalau tidak berkasnya berisi orang yang tak terlihat di layar.
 */
class TglMasukFilterTest extends TestCase
{
    private function admin(): User
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
        $admin = $this->admin();
        $req = Request::create('/x', 'GET', $query + ['per_page' => 100, 'page' => 1]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $res = app(KpiEnforcementController::class)->{$metode}($req);
        if ($res->getStatusCode() !== 200) {
            $this->markTestSkipped('Tidak ada penjadwalan pada scope uji.');
        }
        $isi = json_decode($res->getContent(), true);

        return $isi['result']['rows'] ?? $isi['result'] ?? [];
    }

    /**
     * Rentang satu hari pada tgl masuk karyawan pertama yang punya tgl masuk,
     * plus jumlah baris tanpa filter sebagai pembanding.
     *
     * @return array{0:string,1:int}
     */
    private function hariUji(string $metode, array $query): array
    {
        $semua = $this->baris($metode, $query);
        $d = collect($semua)->pluck('tgl_masuk')->filter()->first();
        if (! $d) {
            $this->markTestSkipped('Tak ada karyawan bertanggal masuk di scope uji.');
        }

        return [$d, count($semua)];
    }

    private function tegaskanTersaring(string $metode, array $query): void
    {
        [$d, $jumlahSemua] = $this->hariUji($metode, $query);

        $hasil = $this->baris($metode, $query + ['tgl_masuk' => [$d, $d]]);

        $this->assertNotEmpty($hasil, "Rentang [{$d}, {$d}] membuang karyawan yang masuk tepat pada {$d}.");
        $this->assertSame([$d], array_values(array_unique(array_column($hasil, 'tgl_masuk'))));
        $this->assertLessThanOrEqual($jumlahSemua, count($hasil));
    }

    public function test_rentang_diurai_dan_yang_rusak_diabaikan(): void
    {
        $this->assertSame(['2020-01-01', '2020-12-31'], KpiFilterTglMasuk::rentang(['2020-01-01', '2020-12-31']));
        $this->assertSame(['2020-01-01', '2020-12-31'], KpiFilterTglMasuk::rentang(['2020-12-31', '2020-01-01']), 'Urutan terbalik ditukar.');
        $this->assertNull(KpiFilterTglMasuk::rentang([]));
        $this->assertNull(KpiFilterTglMasuk::rentang(null));
        $this->assertNull(KpiFilterTglMasuk::rentang(['2020-01-01']));
        $this->assertNull(KpiFilterTglMasuk::rentang(['bukan', 'tanggal']));
        $this->assertNull(KpiFilterTglMasuk::rentang('2020-01-01'));
    }

    public function test_kpi_submission_tersaring_tgl_masuk(): void
    {
        $tahun = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Form_KPI', 'Y')->max('Tahun');

        $this->tegaskanTersaring('getSubmissionStatus', ['tahun' => $tahun]);
    }

    public function test_performance_tracking_bulanan_tersaring_tgl_masuk(): void
    {
        $periode = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Penilaian_KPI', 'Y')->max('Periode');

        $this->tegaskanTersaring('getPerformanceTracking', ['periode' => $periode, 'mode' => 'bulan']);
    }

    public function test_performance_tracking_ytd_tersaring_tgl_masuk(): void
    {
        $periode = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Penilaian_KPI', 'Y')->max('Periode');

        $this->tegaskanTersaring('getPerformanceTracking', ['periode' => $periode, 'mode' => 'ytd']);
    }

    public function test_daftar_panel_export_tersaring_tgl_masuk(): void
    {
        $tahun = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Form_KPI', 'Y')->max('Tahun');

        $this->tegaskanTersaring('getExportUniverse', ['jenis' => 'parameter', 'tahun' => $tahun]);
    }

    /** Export "pilih semua": filter bertahan di payload dan menyaring isi berkas. */
    public function test_export_pilih_semua_tersaring_tgl_masuk(): void
    {
        $tahun = (string) DB::table('KPI_Master_Penjadwalan')->where('Flag_Form_KPI', 'Y')->max('Tahun');
        [$d] = $this->hariUji('getSubmissionStatus', ['tahun' => $tahun]);

        $ctl = app(KpiEnforcementController::class);
        $s = new \ReflectionMethod($ctl, 'sanitizeExportFilters');
        $s->setAccessible(true);
        $filters = $s->invoke($ctl, ['tgl_masuk' => [$d, $d], 'lokasi' => []]);
        $this->assertSame([$d, $d], $filters['tgl_masuk'] ?? null, 'Filter tgl masuk hilang saat disanitasi.');

        $jadwal = DB::table('KPI_Master_Penjadwalan')->where('Flag_Form_KPI', 'Y')->where('Tahun', $tahun)
            ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan']);
        $meta = [
            'schedule_ids' => $jadwal->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            'has_global' => $jadwal->contains(fn ($x) => ($x->Flag_Perorangan ?? 'T') !== 'Y'),
            'perorangan_ids' => $jadwal->filter(fn ($x) => ($x->Flag_Perorangan ?? 'T') === 'Y')
                ->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            'expired' => false,
            'batas_kepegawaian' => KpiKepegawaianPeriode::batasDariScope($tahun)->toDateString(),
        ];

        $c = new \ReflectionMethod($ctl, 'countExportUniverse');
        $c->setAccessible(true);
        $jumlah = $c->invoke($ctl, $meta, $filters);

        $job = (object) [
            'Export_Id' => 'UJI-'.bin2hex(random_bytes(4)),
            'Format' => 'excel',
            'Payload_Json' => json_encode([
                'jenis' => 'parameter', 'scope_value' => $tahun, 'select_all' => true,
                'filters' => $filters, 'schedule_meta' => $meta,
            ]),
            'Total_Records' => 1,
        ];
        $b = new \ReflectionMethod(KpiExportService::class, 'buildDataset');
        $b->setAccessible(true);
        $rows = $b->invoke(app(KpiExportService::class), $job)['rows'] ?? [];

        $this->assertNotEmpty($rows, 'Berkas export kosong padahal ada karyawan yang masuk pada '.$d);
        $kode = array_values(array_unique(array_column($rows, 'kode_karyawan')));
        $tgl = DB::table('Karyawan')->whereIn('Kode_Karyawan', $kode)->pluck('Tgl_Masuk')
            ->map(fn ($v) => substr((string) $v, 0, 10))->unique()->values()->all();
        $this->assertSame([$d], $tgl, 'Berkas export memuat karyawan di luar rentang tgl masuk.');
        $this->assertSame(count($kode), $jumlah, 'Hitungan "pilih semua" tidak memakai filter tgl masuk.');
    }
}
