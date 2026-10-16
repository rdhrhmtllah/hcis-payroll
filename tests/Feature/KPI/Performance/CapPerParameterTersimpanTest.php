<?php

namespace Tests\Feature\KPI\Performance;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiScoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Membuktikan KABEL-nya tersambung, bukan cuma mesinnya benar.
 *
 * Tes unit KpiScoringServiceCapTest sudah menguji penjepitan sebagai fungsi
 * murni. Yang belum terbukti sampai berkas ini ada: bahwa nilai yang sudah
 * dijepit benar-benar MENDARAT di kolom yang tepat lewat endpoint sungguhan,
 * dan bahwa nilai mentahnya ikut tersimpan.
 *
 * Memakai rute HTTP asli dan data dev. Seluruh penulisan dibungkus
 * DatabaseTransactions sehingga di-rollback — tidak ada baris yang tertinggal.
 */
class CapPerParameterTersimpanTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Leader yang punya anggota tim sekaligus akses halaman tim. */
    private function cariLeader(int $maksAnggota = 40): ?User
    {
        $kandidat = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->select('Id_User_Sender', DB::raw('COUNT(DISTINCT Id_User) as anggota'))
            ->groupBy('Id_User_Sender')
            ->havingRaw('COUNT(DISTINCT Id_User) BETWEEN 1 AND ?', [$maksAnggota])
            ->orderByDesc(DB::raw('COUNT(DISTINCT Id_User)'))
            ->get();

        foreach ($kandidat as $k) {
            $user = User::find($k->Id_User_Sender);
            if ($user && $user->memilikiJabatan('UpdatePerformanceTeam')) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Buka jendela satu jadwal penilaian supaya jalur simpan bisa ditempuh.
     *
     * Tanpa ini uji ini TIDAK PERNAH BERJALAN. Jalur simpan tim menuntut
     * Jadwal_Mulai <= sekarang <= Jadwal_Akhir, dan jendela di data dev sudah
     * lewat semua (yang terakhir tutup 30 Juli 2026). Ujinya lalu melewati
     * dirinya sendiri dengan alasan "tidak ada baris To Update" — hijau di
     * laporan, nol yang dibuktikan.
     *
     * Perubahannya ikut di-rollback DatabaseTransactions.
     */
    private function bukaJendelaPenilaian(): bool
    {
        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        if (! $jadwal) {
            return false;
        }

        DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $jadwal->Id_Master_Penjadwalan)
            ->update([
                'Jadwal_Mulai' => now()->subDay()->format('Y-m-d H:i:s'),
                'Jadwal_Akhir' => now()->addDay()->format('Y-m-d H:i:s'),
            ]);

        return true;
    }

    public function test_nilai_terjepit_dan_nilai_mentah_mendarat_di_kolom_yang_tepat(): void
    {
        if (! $this->bukaJendelaPenilaian()) {
            $this->markTestSkipped('Tidak ada jadwal penilaian aktif yang bisa dibuka jendelanya.');
        }

        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $rows = collect(
            $this->actingAs($leader)
                ->getJson('/api/v1/update-performance/list-Kpi-team?limit=500&page=1')
                ->json('result') ?? [],
        )->filter(fn ($r) => is_array($r) && ! empty($r['schedule_hash']));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris To Update di data dev.');
        }

        $row = $rows->first();

        $items = $this->actingAs($leader)->postJson('/api/v1/update-performance/list-Kpi/by-user', [
            'Id_User' => $row['id_user'],
            'Id_Master_Penjadwalan' => $row['schedule_hash'],
        ]);
        $items->assertStatus(200);

        $details = collect($items->json('result.details') ?? [])
            ->filter(fn ($d) => ! empty($d['is_input_period']))
            ->values();

        if ($details->isEmpty()) {
            $this->markTestSkipped('Anggota ini tidak punya parameter yg bisa diisi pada periode tsb.');
        }

        // Nilai ekstrem supaya rasionya pasti keluar dari rentang 50-125 apa pun
        // bentuk rumusnya (naik atau turun), sehingga penjepitan benar-benar
        // menyala. Tanpa ini tesnya bisa hijau tanpa pernah menjepit apa pun.
        $kpiData = $details->map(function ($d) {
            // idPengajuanDet dari API adalah hashid, bukan integer.
            $idDet = Hashids::connection('custom')->decode($d['idPengajuanDet'])[0] ?? null;
            $target = (float) DB::table('KPI_Pengajuan_Det')
                ->where('Id_Pengajuan_Det', $idDet)
                ->value('Nilai_Target');

            return [
                'Id_Pengajuan' => $d['idPengajuan'],
                'Id_Pengajuan_Detail' => $d['idPengajuanDetail'],
                'Id_Pengajuan_Det' => $d['idPengajuanDet'],
                'is_input_period' => 'true',
                'Actual' => $target > 0 ? $target * 50 : 999999,
            ];
        })->all();

        $simpan = $this->actingAs($leader)->postJson(
            "/api/v1/update-performance/store/temporary/{$row['id_user']}",
            [
                'kpi_data' => $kpiData,
                'current_step' => 1,
                'Id_Master_Penjadwalan' => $row['schedule_hash'],
            ],
        );

        if ($simpan->status() < 200 || $simpan->status() >= 300) {
            $this->assertStringContainsString(
                'INSERT permission was denied',
                $simpan->getContent(),
                'Simpan draft gagal bukan karena izin: '.$simpan->getContent(),
            );
            $this->markTestSkipped('Kredensial DB dev read-only untuk tabel draft.');
        }

        $idUser = Hashids::connection('custom')->decode($row['id_user'])[0];
        $idJadwal = Hashids::connection('custom')->decode($row['schedule_hash'])[0];

        $header = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_User', $idUser)
            ->where('Id_Penjadwalan', $idJadwal)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Update_Performance_Sementara')
            ->first();

        $this->assertNotNull($header, 'Draft tidak ditemukan setelah disimpan.');

        $tersimpan = DB::table('KPI_Update_Performance_Detail_Sementara as upds')
            ->join('KPI_Pengajuan_Det as pdet', 'upds.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->leftJoin('KPI_Master_Formula as f', 'pd.Id_Master_Formula', '=', 'f.Id_Master_Formula')
            ->where('upds.Id_Update_Performance_Sementara', $header->Id_Update_Performance_Sementara)
            ->get([
                'upds.Id_Pengajuan_Det',
                'upds.Nilai_Actual',
                'upds.Score',
                'upds.Achievement',
                'upds.Score_Raw',
                'upds.Achievement_Raw',
                'upds.Flag_Capped',
                'upds.Flag_Aktif',
                'pd.Bobot',
                'pdet.Nilai_Target',
                'f.Rumus',
            ]);

        $this->assertNotEmpty($tersimpan, 'Draft tersimpan tanpa satu pun detail.');

        $svc = app(KpiScoringService::class);
        [$batasMax, $batasMin] = $svc->paramCapBounds();
        $this->assertNotNull($batasMax, 'Batas atas per-parameter mati; tes ini tidak menguji apa pun.');
        $this->assertNotNull($batasMin, 'Batas bawah per-parameter mati; tes ini tidak menguji apa pun.');

        $adaYangTerjepit = false;

        foreach ($tersimpan as $d) {
            $ket = 'Parameter Id_Pengajuan_Det='.$d->Id_Pengajuan_Det;

            // Kolom mentah HARUS terisi. Kekosongan berarti baris beraturan lama,
            // dan baris yang baru saja ditulis tidak boleh terlihat seperti itu.
            $this->assertNotNull($d->Score_Raw, $ket.': Score_Raw kosong pada baris yang baru ditulis.');
            $this->assertNotNull($d->Achievement_Raw, $ket.': Achievement_Raw kosong.');

            // Hitung ulang dari input yang sama; nilai tersimpan harus persis sama.
            $harap = $svc->scoreParameter(
                (float) $d->Nilai_Actual,
                (float) $d->Nilai_Target,
                (float) $d->Bobot,
                $d->Rumus,
            );

            $this->assertEqualsWithDelta($harap['achievement'], (float) $d->Score, 0.01, $ket.': achievement tersimpan meleset.');
            $this->assertEqualsWithDelta($harap['score'], (float) $d->Achievement, 0.01, $ket.': skor tersimpan meleset.');
            $this->assertEqualsWithDelta($harap['achievement_raw'], (float) $d->Score_Raw, 0.01, $ket.': achievement mentah meleset.');
            $this->assertEqualsWithDelta($harap['score_raw'], (float) $d->Achievement_Raw, 0.01, $ket.': skor mentah meleset.');
            $this->assertSame($harap['is_capped'] ? 'Y' : 'T', (string) $d->Flag_Capped, $ket.': penanda terjepit tidak sesuai.');

            // Achievement tersimpan tak boleh keluar dari batas.
            $this->assertGreaterThanOrEqual($batasMin, (float) $d->Score, $ket.': achievement di bawah lantai.');
            $this->assertLessThanOrEqual($batasMax, (float) $d->Score, $ket.': achievement di atas plafon.');

            if ((string) $d->Flag_Capped === 'Y') {
                $adaYangTerjepit = true;
            }
        }

        $this->assertTrue(
            $adaYangTerjepit,
            'Tidak satu pun parameter terjepit meski nilai aktualnya 50x target — '.
            'penjepitan tidak pernah menyala, jadi tes ini tidak membuktikan apa-apa.',
        );

        // Total header harus sama dengan rata-rata tertimbang baris yang dihitung
        // (Flag_Aktif='Y'). Inilah janji yang membuat halaman bisa dipercaya:
        // angka total dapat direkonstruksi dari baris-baris yang ditampilkan.
        $aktif = $tersimpan->filter(fn ($d) => (string) $d->Flag_Aktif === 'Y');
        $sumSkor = $aktif->sum(fn ($d) => (float) $d->Achievement);
        $sumBobot = $aktif->sum(fn ($d) => (float) $d->Bobot);

        if ($sumBobot > 0) {
            $this->assertEqualsWithDelta(
                round(($sumSkor / $sumBobot) * 100, 2),
                (float) $header->Total_Score,
                0.02,
                'Total header bukan rata-rata tertimbang dari baris yang tersimpan.',
            );
        }
    }

    /**
     * PINTU KETIGA: jalur by-pass.
     *
     * Ia punya endpoint, halaman, dan method simpan sendiri
     * (storeTemporaryPerformanceStep1ByTeamByPass), dan sengaja TIDAK memeriksa
     * jendela jadwal — itulah gunanya. Karena jalurnya terpisah, penjepitan di
     * sini harus dibuktikan sendiri: cacat yang berulang di proyek ini justru
     * berbentuk aturan yang dipasang di sebagian pintu saja.
     */
    public function test_jalur_by_pass_juga_menyimpan_nilai_terjepit_dan_mentah(): void
    {
        $leader = $this->cariLeader();

        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Jadwal_Akhir')
            ->first();

        if (! $jadwal) {
            $this->markTestSkipped('Tidak ada jadwal penilaian aktif.');
        }

        $hashJadwal = Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan);

        $anggota = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->where('Id_User_Sender', $leader->Id_Users)
            ->value('Id_User');

        if (! $anggota) {
            $this->markTestSkipped('Leader ini tidak punya anggota dgn parameter disetujui.');
        }

        $hashAnggota = Hashids::connection('custom')->encode($anggota);

        $items = $this->actingAs($leader)->postJson('/api/v1/update-performance/list-Kpi-by-pass/by-user', [
            'Id_User' => $hashAnggota,
            'Id_Master_Penjadwalan' => $hashJadwal,
        ]);

        if ($items->status() !== 200) {
            $this->markTestSkipped('Daftar by-pass tidak tersedia: '.substr($items->getContent(), 0, 160));
        }

        $details = collect($items->json('result.details') ?? [])
            ->filter(fn ($d) => ! empty($d['is_input_period']))
            ->values();

        if ($details->isEmpty()) {
            $this->markTestSkipped('Anggota ini tidak punya parameter yang bisa diisi lewat by-pass.');
        }

        $kpiData = $details->map(function ($d) {
            $idDet = Hashids::connection('custom')->decode($d['idPengajuanDet'])[0] ?? null;
            $target = (float) DB::table('KPI_Pengajuan_Det')->where('Id_Pengajuan_Det', $idDet)->value('Nilai_Target');

            return [
                'Id_Pengajuan' => $d['idPengajuan'],
                'Id_Pengajuan_Detail' => $d['idPengajuanDetail'],
                'Id_Pengajuan_Det' => $d['idPengajuanDet'],
                'is_input_period' => 'true',
                // Ekstrem supaya rasionya pasti keluar rentang 50-125 apa pun rumusnya.
                'Actual' => $target > 0 ? $target * 50 : 999999,
            ];
        })->all();

        $simpan = $this->actingAs($leader)->postJson(
            "/api/v1/update-performance/store/temporary-bypass/{$hashAnggota}",
            [
                'kpi_data' => $kpiData,
                'current_step' => 1,
                'Id_Master_Penjadwalan' => $hashJadwal,
            ],
        );

        // Sampai di sini jalurnya SUDAH terbukti bisa ditempuh: daftar parameter
        // tersedia dan muatannya sah. Karena itu kegagalan simpan harus GAGAL,
        // bukan dilewati — satu-satunya pengecualian adalah kredensial dev yang
        // read-only, dan itu pun harus dibuktikan dari pesannya sendiri.
        if ($simpan->status() < 200 || $simpan->status() >= 300) {
            $this->assertStringContainsString(
                'INSERT permission was denied',
                $simpan->getContent(),
                'Simpan by-pass gagal bukan karena izin: '.$simpan->getContent(),
            );
            $this->markTestSkipped('Kredensial DB dev read-only untuk tabel draft.');
        }

        $header = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_User', $anggota)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_Update_Performance_Sementara')
            ->first();

        $this->assertNotNull($header, 'Draft by-pass tidak ditemukan setelah disimpan.');

        $baris = DB::table('KPI_Update_Performance_Detail_Sementara')
            ->where('Id_Update_Performance_Sementara', $header->Id_Update_Performance_Sementara)
            ->get(['Score', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped']);

        $this->assertNotEmpty($baris, 'Draft by-pass tersimpan tanpa satu pun detail.');

        foreach ($baris as $b) {
            $this->assertNotNull($b->Score_Raw, 'Score_Raw kosong pada baris by-pass yang baru ditulis.');
            $this->assertNotNull($b->Achievement_Raw, 'Achievement_Raw kosong pada baris by-pass.');
        }

        // Nilai ekstrem tadi WAJIB memicu penjepitan; kalau tidak, uji ini hijau
        // tanpa pernah menyentuh aturan yang seharusnya dijaganya.
        $this->assertTrue(
            $baris->contains(fn ($b) => $b->Flag_Capped === 'Y'),
            'Tidak ada satu pun parameter yang terjepit padahal aktualnya ekstrem.',
        );
    }
}
