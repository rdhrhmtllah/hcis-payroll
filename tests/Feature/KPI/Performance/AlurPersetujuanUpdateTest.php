<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\Approval\KpiApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur persetujuan di halaman Update Performance tidak boleh dinyatakan "belum
 * tersedia" padahal konfigurasinya ada.
 *
 * ── Bug aslinya ─────────────────────────────────────────────────────────────
 *
 * Pengajuan yang berjalan di engine LEGACY memulangkan `steps: []`, dan layar
 * menuliskan "Alur persetujuan belum tersedia". Kalimat itu keliru dua kali:
 *
 *   • approval config-nya ADA — KPI_PERF_STD memulangkan approver untuk setiap
 *     karyawan yang diperiksa (A4 tiga approver, E14 dua, C9 satu);
 *   • halaman Parameter menampilkan alurnya tanpa masalah, karena ia memang
 *     selalu memakai preview dari config yang sama.
 *
 * Jadi pengguna melihat "belum tersedia" untuk sesuatu yang jelas ada, di layar
 * sebelah layar yang menampilkannya dengan baik. Di Web_HRIS 10 September 2026,
 * 18 dari 64 transaksi update performance aktif berjalan di engine legacy.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 *  1. Pengajuan legacy tetap memulangkan langkah, tidak pernah daftar kosong.
 *  2. Ketika jejak per-approver memang tidak ada, langkahnya ditandai 'unknown'
 *     dan payload membawa jejak_tersedia=false. BUKAN 'pending': pengajuan yang
 *     sudah berstatus 'disetujui' tak boleh digambar seolah masih menunggu
 *     orang — itu mengganti satu kebohongan dengan kebohongan lain.
 *  3. Ketika jejak legacy ADA, statusnya dibaca dari jejak itu, bukan dikarang.
 */
class AlurPersetujuanUpdateTest extends TestCase
{
    use DatabaseTransactions;

    private function alur(int $userIdWeb): array
    {
        $user = User::find($userIdWeb);

        if (! $user) {
            $this->markTestSkipped("User {$userIdWeb} tidak punya akun KPI_Users.");
        }

        Auth::setUser($user);

        $req = Request::create('/x', 'GET');
        $req->setUserResolver(fn () => $user);
        app()->instance('request', $req);

        $isi = json_decode(
            app(UpdatePerformanceController::class)->getApprovalFlow($req)->getContent(),
            true,
        );

        return $isi['result'] ?? [];
    }

    /** Pemilik pengajuan final yang engine-nya LEGACY. */
    private function pemilikPengajuanLegacy(): ?int
    {
        $svc = app(KpiApprovalService::class);

        $baris = DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('No_Transaksi')
            ->where('Status_Pengajuan', '!=', 'ditolak')
            ->orderByDesc('Id_Update_Performance')
            ->get(['Id_User', 'No_Transaksi', 'Periode']);

        foreach ($baris as $b) {
            if ($svc->engineFor($b->No_Transaksi) !== 'CENTRAL') {
                // Harus jadi pengajuan TERAKHIR pada periodenya, karena itulah
                // yang dibaca endpoint.
                $terakhir = DB::table('KPI_Update_Performance')
                    ->where('Id_User', $b->Id_User)
                    ->where('Flag_Aktif', 'Y')
                    ->whereNotNull('No_Transaksi')
                    ->where('Periode', $b->Periode)
                    ->orderByDesc('Tanggal')
                    ->orderByDesc('Id_Update_Performance')
                    ->value('No_Transaksi');

                if ($terakhir === $b->No_Transaksi) {
                    return (int) $b->Id_User;
                }
            }
        }

        return null;
    }

    public function test_pengajuan_legacy_tidak_memulangkan_daftar_kosong(): void
    {
        $user = $this->pemilikPengajuanLegacy();

        if (! $user) {
            $this->markTestSkipped('Tidak ada pengajuan engine legacy di data ini.');
        }

        $hasil = $this->alur($user);

        $this->assertTrue($hasil['submitted'] ?? false);
        $this->assertNotEmpty(
            $hasil['steps'] ?? [],
            'Pengajuan engine legacy memulangkan daftar kosong, dan layar menuliskan '.
            '"Alur persetujuan belum tersedia" padahal approval config-nya ada.',
        );
    }

    public function test_tanpa_jejak_ditandai_tidak_terekam_bukan_menunggu(): void
    {
        $user = $this->pemilikPengajuanLegacy();

        if (! $user) {
            $this->markTestSkipped('Tidak ada pengajuan engine legacy di data ini.');
        }

        $hasil = $this->alur($user);

        if (($hasil['jejak_tersedia'] ?? true) !== false) {
            $this->markTestSkipped('Pengajuan legacy yang terpilih justru punya jejak.');
        }

        $status = collect($hasil['steps'])->pluck('status')->unique();

        $this->assertSame(
            ['unknown'],
            $status->values()->all(),
            'Langkah tanpa jejak digambar memakai status biasa. Pengajuan berstatus '.
            '"'.($hasil['status'] ?? '?').'" tidak boleh tampak seolah masih menunggu approver.',
        );
    }

    public function test_langkahnya_membawa_nama_approver_yang_nyata(): void
    {
        $user = $this->pemilikPengajuanLegacy();

        if (! $user) {
            $this->markTestSkipped('Tidak ada pengajuan engine legacy di data ini.');
        }

        foreach ($this->alur($user)['steps'] ?? [] as $langkah) {
            $this->assertArrayHasKey('name', $langkah);
            $this->assertArrayHasKey('order', $langkah);
            $this->assertNotSame(
                '',
                trim((string) $langkah['name']),
                'Ada langkah tanpa nama — daftar approver yang kosong isinya sama tak bergunanya '.
                'dengan tidak menampilkan apa pun.',
            );
        }
    }

    public function test_jejak_legacy_yang_ada_dibaca_apa_adanya(): void
    {
        // Sengaja TANPA join ke KPI_Approval_Flow_Performance: Flow_Id pada jejak
        // lama bisa menunjuk baris yang sudah tidak ada, dan uji yang ikut
        // menjoin akan melewatkan dirinya sendiri (skip) persis pada data yang
        // paling perlu diuji.
        $jejak = DB::table('KPI_Approval_Request_Performance')
            ->orderBy('Id_Approval_Request')
            ->get(['No_Transaksi', 'Flag_Approval']);

        if ($jejak->isEmpty()) {
            $this->markTestSkipped('Tidak ada jejak approval legacy di data ini.');
        }

        $noTransaksi = $jejak->first()->No_Transaksi;

        $m = new \ReflectionMethod(UpdatePerformanceController::class, 'buildLegacyApprovalSteps');
        $m->setAccessible(true);
        $langkah = collect($m->invoke(app(UpdatePerformanceController::class), $noTransaksi));

        $this->assertCount(
            $jejak->where('No_Transaksi', $noTransaksi)->count(),
            $langkah,
            'Jumlah langkah tidak sama dengan jumlah baris jejaknya.',
        );

        $disetujui = $jejak->where('No_Transaksi', $noTransaksi)->where('Flag_Approval', 'Y')->count();

        $this->assertSame(
            $disetujui,
            $langkah->where('status', 'approved')->count(),
            'Status langkah tidak mengikuti Flag_Approval pada jejaknya.',
        );
    }
}
