<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tab "Belum Parameter" pada Update Performance Team.
 *
 * ── Kenapa tab ini ada ──────────────────────────────────────────────────────
 *
 * Daftar kerja "To Update" berbasis KPI_Pengajuan: tanpa parameter yang sudah
 * disetujui memang tidak ada achievement yang bisa di-update. Akibatnya anggota
 * yang belum berparameter tidak muncul di mana pun — leader tidak punya cara
 * melihat lubang di timnya sendiri. Tab ini menutup lubang itu dari roster
 * (KPI_Karyawan_Team), tanpa mengubah daftar kerja.
 *
 * ── Yang dijaga, dan kenapa persisnya ini ───────────────────────────────────
 *
 *  1. ANGKA BADGE HARUS SETARA ISI TAB. getKpiTeamSummaryCounts() punya gerbang
 *     "tidak ada tim aktif" yang memulangkan semua angka nol ketika leader belum
 *     punya satu pun pengajuan disetujui. Justru itulah kasus terparah tab ini:
 *     SELURUH roster belum berparameter. Sekali angka ini ikut dinolkan gerbang
 *     tersebut, badge menunjukkan 0 sementara tabnya berisi puluhan orang — dan
 *     tidak ada yang akan menyadarinya karena keduanya "terlihat masuk akal"
 *     bila dilihat terpisah.
 *
 *  2. "BELUM MENGISI" TIDAK BOLEH MENELAN "MENUNGGU APPROVAL". Anggota yang
 *     parameternya sudah dikirim tetapi approval-nya menggantung juga tidak
 *     muncul di To Update. Kalau ia dilabeli "belum mengisi", leader akan
 *     mengisi ulang sesuatu yang sudah ada; yang benar adalah menagih approver.
 *     Dua keadaan, dua tindakan berbeda.
 *
 *  3. SELURUH HALAMAN HARUS MENGIRIM SEBANYAK YANG DIJANJIKAN. Kueri roster
 *     memakai JOIN ke Karyawan; sekali baris kembar lolos, `total` dan jumlah
 *     kartu berselisih dan halaman terakhir bisa kosong.
 *
 * Fixture dibangun sendiri di dalam transaksi (roster + satu pengajuan
 * menggantung) supaya ketiga penjaga tidak bergantung pada kebetulan data dev.
 */
class BelumParameterTimTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $kodeLeader = null;

    private ?int $userLeader = null;

    /** @var array<int,object> */
    private array $anggota = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanTim();
    }

    public function test_badge_setara_isi_tab_meski_leader_belum_punya_pengajuan_disetujui(): void
    {
        $this->masukSebagaiLeader();
        $c = app(UpdatePerformanceController::class);

        // Prasyarat penjaga #1: leader ini memang tidak punya pengajuan disetujui,
        // jadi gerbang "tidak ada tim aktif" pasti dilewati.
        $this->assertFalse(
            DB::table('KPI_Pengajuan')
                ->where('Id_User_Sender', $this->userLeader)
                ->where('Flag_Team', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Approval', 'Y')
                ->whereNull('Status')
                ->exists(),
            'Fixture tidak valid: leader uji tidak boleh punya pengajuan tim disetujui.',
        );

        $daftar = $this->ambilDaftar($c, 1, 50);
        $widget = json_decode($c->getKpiTeamSummaryCounts()->getContent(), true);

        $this->assertSame(
            (int) $daftar['total_data'],
            (int) ($widget['result']['belum_parameter'] ?? -1),
            'Badge "Belum Parameter" berbeda dari jumlah baris yang benar-benar ada di tab.',
        );
        $this->assertSame(count($this->anggota), (int) $daftar['total_data']);
    }

    public function test_anggota_yang_menunggu_approval_tidak_dilabeli_belum_mengisi(): void
    {
        $menggantung = $this->anggota[0];
        $this->buatPengajuanMenggantung((int) $menggantung->UserID_Web);

        $this->masukSebagaiLeader();
        $daftar = $this->ambilDaftar(app(UpdatePerformanceController::class), 1, 50);

        $perKode = collect($daftar['result'])->keyBy('kode_karyawan');

        $this->assertArrayHasKey(
            $menggantung->Kode_Karyawan,
            $perKode->all(),
            'Anggota dengan pengajuan menggantung hilang dari tab — ia tidak muncul di To Update juga.',
        );
        $this->assertSame(
            'MENUNGGU_APPROVAL',
            $perKode[$menggantung->Kode_Karyawan]['status_parameter'],
            'Pengajuan yang sudah dikirim tetapi belum disetujui dilabeli seolah belum pernah diisi.',
        );

        $lain = $this->anggota[1];
        $this->assertSame(
            'BELUM_ISI',
            $perKode[$lain->Kode_Karyawan]['status_parameter'],
            'Anggota tanpa pengajuan apa pun harus tetap berstatus BELUM_ISI.',
        );
    }

    public function test_seluruh_halaman_mengirim_sebanyak_yang_dijanjikan(): void
    {
        $this->masukSebagaiLeader();
        $c = app(UpdatePerformanceController::class);

        $terkumpul = [];
        $dijanjikan = null;
        $totalHalaman = 1;

        for ($halaman = 1; $halaman <= $totalHalaman && $halaman <= 20; $halaman++) {
            $isi = $this->ambilDaftar($c, $halaman, 1);
            $dijanjikan ??= (int) $isi['total_data'];
            $totalHalaman = (int) $isi['total_page'];

            foreach ($isi['result'] as $baris) {
                $terkumpul[] = $baris['kode_karyawan'];
            }
        }

        $this->assertSame($dijanjikan, count($terkumpul), 'Jumlah baris seluruh halaman ≠ total paginasi.');
        $this->assertSame(
            count($terkumpul),
            count(array_unique($terkumpul)),
            'Ada anggota yang muncul lebih dari sekali antar halaman.',
        );
    }

    /**
     * Parameter yang diajukan LEADER LAIN tetap dihitung sebagai "sudah punya
     * parameter".
     *
     * Keanggotaan tim kini ditentukan roster KPI_Karyawan_Team, bukan lagi siapa
     * yang mengirim parameter. Konsekuensinya anggota itu KELUAR dari tab ini dan
     * masuk ke daftar kerja To Update — sebelumnya ia terjebak di antara keduanya:
     * tidak muncul di To Update karena bukan pengirimnya, dan dilabeli "belum
     * mengisi parameter" di sini padahal parameternya ada dan sudah disetujui.
     *
     * Nyata terjadi di data dev: leader A4 (Aji) punya anggota E14 (Dhea) yang
     * pengajuan parameternya dikirim leader lain.
     */
    public function test_parameter_yang_diajukan_leader_lain_dihitung_sudah_punya_parameter(): void
    {
        $anggota = $this->anggota[0];

        $this->masukSebagaiLeader();
        $c = app(UpdatePerformanceController::class);

        $sebelum = collect($this->ambilDaftar($c, 1, 50)['result'])->pluck('kode_karyawan');
        $this->assertContains(
            $anggota->Kode_Karyawan,
            $sebelum->all(),
            'Prasyarat: anggota ini semestinya masih berstatus belum punya parameter.',
        );

        DB::table('KPI_Pengajuan')->insert([
            'No_Transaksi' => 'UJI-BP-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'Id_Penjadwalan' => DB::table('KPI_Master_Penjadwalan')->value('Id_Master_Penjadwalan'),
            'Id_User' => (int) $anggota->UserID_Web,
            'Id_User_Sender' => $this->userLeaderLain(),
            'Flag_Team' => 'Y',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Status' => null,
            'Tanggal' => now(),
        ]);

        $sesudah = collect($this->ambilDaftar($c, 1, 50)['result'])->pluck('kode_karyawan');

        $this->assertNotContains(
            $anggota->Kode_Karyawan,
            $sesudah->all(),
            'Anggota masih dilabeli belum punya parameter padahal parameternya ada — '.
            'saringan pengirim kemungkinan hidup lagi.',
        );

        // Anggota lain tidak boleh ikut terseret keluar.
        $this->assertContains($this->anggota[1]->Kode_Karyawan, $sesudah->all());
    }

    /**
     * "Belum mengisi parameter" hanya benar bila jendela pengisiannya memang
     * sedang dibuka.
     *
     * Kalau tidak ada jadwal Form KPI yang berjalan untuk seorang anggota, tidak
     * ada yang bisa dikerjakan siapa pun — bukan leader-nya yang lalai, bukan
     * anggotanya. Menyebutnya "belum mengisi" mengirim leader mengejar sesuatu
     * yang memang belum bisa dikerjakan.
     */
    public function test_status_berubah_jadi_belum_terjadwal_saat_jendela_parameter_tutup(): void
    {
        $this->masukSebagaiLeader();
        $c = app(UpdatePerformanceController::class);

        $sebelum = collect($this->ambilDaftar($c, 1, 50)['result']);
        $this->assertNotEmpty($sebelum, 'Fixture tidak menghasilkan satu pun baris.');

        if ($sebelum->pluck('status_parameter')->doesntContain('BELUM_ISI')) {
            $this->markTestSkipped('Fixture tidak menghasilkan anggota berstatus BELUM_ISI.');
        }

        $this->tutupJendelaParameter();

        $sesudah = collect($this->ambilDaftar($c, 1, 50)['result'])->keyBy('kode_karyawan');

        foreach ($sebelum->where('status_parameter', 'BELUM_ISI') as $baris) {
            $kode = $baris['kode_karyawan'];
            $this->assertSame(
                'BELUM_TERJADWAL',
                $sesudah[$kode]['status_parameter'],
                "Anggota {$kode} tetap dilabeli 'belum mengisi' padahal jendela pengisiannya tutup.",
            );
            $this->assertFalse($sesudah[$kode]['jadwal_parameter']);
        }
    }

    /**
     * Parameter beres TIDAK sama dengan muncul di To Update: daftar itu menuntut
     * jadwal PENILAIAN yang sedang berjalan. Nilainya harus dilaporkan apa adanya,
     * bukan diasumsikan true.
     */
    public function test_jadwal_penilaian_dilaporkan_apa_adanya(): void
    {
        $this->masukSebagaiLeader();
        $daftar = $this->ambilDaftar(app(UpdatePerformanceController::class), 1, 50);

        foreach ($daftar['result'] as $baris) {
            $this->assertArrayHasKey('jadwal_penilaian', $baris);

            $harapan = DB::table('KPI_Master_Penjadwalan')
                ->where('Flag_Penilaian_KPI', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->where(function ($q) {
                    $q->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
                })
                ->where('Jadwal_Mulai', '<=', now())
                ->where('Jadwal_Akhir', '>=', now())
                ->exists()
                || DB::table('KPI_Master_Penjadwalan as m')
                    ->join('N_KPI_Master_Penjadwalan_Perorangan as p',
                        'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
                    ->where('p.Kode_Karyawan', $baris['kode_karyawan'])
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('m.Flag_Aktif', 'Y')
                    ->where('m.Flag_Perorangan', 'Y')
                    ->where('m.Flag_Penilaian_KPI', 'Y')
                    ->where('m.Jadwal_Mulai', '<=', now())
                    ->where('m.Jadwal_Akhir', '>=', now())
                    ->exists();

            $this->assertSame(
                $harapan,
                $baris['jadwal_penilaian'],
                "Status jadwal penilaian anggota {$baris['kode_karyawan']} tidak sesuai keadaan sebenarnya.",
            );
        }
    }

    /** Akun leader mana pun selain leader uji. */
    private function userLeaderLain(): int
    {
        $lain = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->where('UserID_Web', '!=', $this->userLeader)
            ->value('UserID_Web');

        if (! $lain) {
            $this->markTestSkipped('Tidak ada karyawan berakun lain untuk berperan sebagai leader kedua.');
        }

        return (int) $lain;
    }

    /** Menutup seluruh jendela pengisian parameter yang menyentuh tim uji. */
    private function tutupJendelaParameter(): void
    {
        $kode = collect($this->anggota)->pluck('Kode_Karyawan')->all();

        DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Form_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Jadwal_Mulai', '<=', now())
            ->where('Jadwal_Akhir', '>=', now())
            ->update(['Flag_Aktif' => 'T']);

        $idPerorangan = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->whereIn('Kode_Karyawan', $kode)
            ->pluck('Id_Master_Penjadwalan_Perorangan');

        if ($idPerorangan->isNotEmpty()) {
            DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->whereIn('Id_Master_Penjadwalan_Perorangan', $idPerorangan)
                ->update(['Flag_Aktif' => 'T']);
        }
    }

    /** @return array<string,mixed> */
    private function ambilDaftar(UpdatePerformanceController $c, int $page, int $limit): array
    {
        $req = Request::create(
            '/api/v1/update-performance/list-Kpi-team/belum-parameter',
            'GET',
            ['page' => $page, 'limit' => $limit],
        );

        return json_decode($c->getBelumParameterTeam($req)->getContent(), true);
    }

    private function masukSebagaiLeader(): void
    {
        $user = User::find($this->userLeader);
        $this->assertNotNull($user, 'Akun leader uji tidak ditemukan.');
        Auth::setUser($user);
    }

    private function buatPengajuanMenggantung(int $idUser): void
    {
        $idPenjadwalan = DB::table('KPI_Master_Penjadwalan')->value('Id_Master_Penjadwalan');
        $this->assertNotNull($idPenjadwalan, 'Tidak ada jadwal KPI di data ini.');

        DB::table('KPI_Pengajuan')->insert([
            'No_Transaksi' => 'UJI-BP-'.substr(bin2hex(random_bytes(6)), 0, 10),
            'Id_Penjadwalan' => $idPenjadwalan,
            'Id_User' => $idUser,
            'Id_User_Sender' => $this->userLeader,
            'Flag_Team' => 'Y',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Status' => null,
            'Tanggal' => now(),
        ]);
    }

    /**
     * Leader yang benar-benar bersih: belum punya roster dan belum pernah
     * mengirim pengajuan tim. Tanpa itu, angka yang diuji bercampur data dev.
     */
    private function siapkanTim(): void
    {
        $kandidat = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(function ($q) {
                $q->from('KPI_Karyawan_Team as kt')
                    ->whereColumn('kt.Kode_Karyawan_Leader', 'k.Kode_Karyawan')
                    ->select(DB::raw('1'));
            })
            ->whereNotExists(function ($q) {
                $q->from('KPI_Pengajuan as p')
                    ->whereColumn('p.Id_User_Sender', 'k.UserID_Web')
                    ->select(DB::raw('1'));
            })
            ->select('k.Kode_Karyawan', 'k.UserID_Web')
            ->first();

        if (! $kandidat) {
            $this->markTestSkipped('Tidak ada karyawan berakun yang belum jadi leader di data ini.');
        }

        $this->kodeLeader = $kandidat->Kode_Karyawan;
        $this->userLeader = (int) $kandidat->UserID_Web;

        $anggota = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->where('Kode_Karyawan', '!=', $this->kodeLeader)
            ->select('Kode_Karyawan', 'UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->limit(3)
            ->get();

        if ($anggota->count() < 3) {
            $this->markTestSkipped('Butuh minimal tiga karyawan berakun untuk membentuk tim uji.');
        }

        $this->anggota = $anggota->all();

        foreach ($this->anggota as $a) {
            DB::table('KPI_Karyawan_Team')->insert([
                'Kode_Karyawan_Leader' => $this->kodeLeader,
                'Kode_Karyawan_Team' => $a->Kode_Karyawan,
                'Flag_Aktif' => 'Y',
                'create_at' => now(),
            ]);
        }
    }
}
