<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\Approval\KpiApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Modal detail anggota harus membaca engine approval yang benar.
 *
 * getDataCurrentTeam() sudah lama dual-engine (memilih HRIS_Approval_Request
 * atau tabel legacy berdasarkan No_Transaksi), tapi getMemberKpiDetail() —
 * yang melayani ikon mata di halaman yang sama — hanya membaca tabel legacy.
 * Pada engine CENTRAL tabel itu kosong, sehingga modal selalu tampil tanpa
 * approver padahal grid di halaman yang sama menampilkannya.
 *
 * Gejalanya tidak menimbulkan error apa pun: daftar approver hanya kosong.
 * Karena itu test ini membandingkan langsung dengan grid — dua sumber yang
 * seharusnya sepakat, dan diamnya salah satu tidak akan ketahuan tanpa
 * pembanding.
 */
class DetailAnggotaDualEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_detail_anggota_menampilkan_approver_yang_sama_dengan_grid(): void
    {
        // Anggota tim dengan pengajuan parameter yang masih hidup dan berjalan
        // di engine CENTRAL — di situlah bug lamanya muncul.
        $kandidat = DB::table('KPI_Pengajuan as kp')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->join('KPI_Karyawan_Team as kt', 'kt.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->join('Karyawan as kl', 'kl.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Leader')
            // Jadwalnya WAJIB ikut disaring dengan syarat yang sama seperti
            // getDataCurrentTeam(), bukan hanya pengajuannya. Pengajuan bisa
            // tetap hidup di atas jadwal yang sudah diarsipkan, dan grid dengan
            // benar memulangkan "Tidak ada jadwal yang cocok" untuk itu —
            // kandidatnya lalu hilang dari grid dan test gagal seolah-olah
            // kodenya rusak. Persis yang terjadi saat jadwal terbaru di data dev
            // bergeser ke Id_Master_Penjadwalan 54 (Flag_Aktif='T', Flag_Arsip='T').
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->where('mp.Flag_Aktif', 'Y')
            ->where('mp.Flag_Form_KPI', 'Y')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('kt.Flag_Aktif', 'Y')
            ->whereNotNull('kl.UserId_Web')
            ->orderByDesc('kp.Id_Pengajuan')
            ->select(
                'kp.No_Transaksi',
                'kp.Id_Penjadwalan',
                'kt.Id_Karyawan_Team',
                'k.Kode_Karyawan',
                'kl.UserId_Web as leader_user',
            )
            ->first();

        if (! $kandidat) {
            $this->markTestSkipped('Data dev tidak punya pengajuan tim hidup di atas jadwal Form KPI yang aktif.');
        }

        if (! app(KpiApprovalService::class)->isCentral($kandidat->No_Transaksi)) {
            $this->markTestSkipped('Pengajuan yang tersedia masih berjalan di engine legacy.');
        }

        $leader = User::where('Id_Users', $kandidat->leader_user)->first();

        if (! $leader) {
            $this->markTestSkipped('Leader tidak punya akun user.');
        }

        $this->actingAs($leader);

        $hashJadwal = Hashids::connection('custom')->encode($kandidat->Id_Penjadwalan);

        $detail = json_decode(
            (new FormKpiController)
                ->getMemberKpiDetail(new Request(['schedule_id' => $hashJadwal]), $kandidat->Id_Karyawan_Team)
                ->getContent(),
            true,
        )['result'] ?? [];

        $grid = collect(
            json_decode((new FormKpiController)->getDataCurrentTeam($hashJadwal)->getContent(), true)['result'] ?? [],
        )->firstWhere('employeeId', $kandidat->Kode_Karyawan);

        $this->assertNotNull($grid, 'Anggota tidak ditemukan di grid tim.');

        $jumlahGrid = count($grid['approvers'] ?? []);

        // Premis test: kalau grid pun kosong, membandingkan nol dengan nol tidak
        // membuktikan apa-apa soal engine yang dibaca detail.
        $this->assertGreaterThan(
            0,
            $jumlahGrid,
            'Grid tidak mengembalikan approver, jadi perbandingan ini tidak bermakna.',
        );

        $this->assertSame(
            $jumlahGrid,
            count($detail['approvers'] ?? []),
            'Modal detail dan grid tidak sepakat soal jumlah approver — '
                .'detail kemungkinan masih membaca tabel approval legacy.',
        );
    }
}
