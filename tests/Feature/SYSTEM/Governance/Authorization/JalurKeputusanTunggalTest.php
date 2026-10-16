<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * RBAC Fase 4: semua keputusan "boleh buka halaman" lewat KeputusanAkses,
 * supaya mode BARU suatu modul berlaku di setiap layar — bukan hanya di
 * gerbang route dan sidebar.
 * Skenario: pemegang HCIS_LEADER (modul ESS); hak VIEW IzinPageAdmin dan
 * ShiftManagement dinonaktifkan di model baru (lama = boleh, baru = tidak),
 * dan satu halaman ESS tanpa hibah diberi hak (lama = tidak, baru = boleh).
 */
class JalurKeputusanTunggalTest extends TestCase
{
    use DatabaseTransactions;

    private User $u;

    private int $ess;

    private string $halamanHakSaja;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ess = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'ESS')->value('Id_Grup_Fitur');
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')->where('pa.Flag_Aktif', 'Y'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');
        $this->u = User::where('Id_Users', $id)->firstOrFail();
        $leader = (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $id, 'Id_Role' => $leader, 'Assigned_At' => now(), 'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        DB::table('N_HRIS_Role_Hak')->where('Id_Role', $leader)->whereIn('Jenis_page', ['IzinPageAdmin', 'ShiftManagement'])->update(['Flag_Aktif' => 'T']);

        // Halaman ESS yang tak dihibahkan ke role mana pun milik user dan bukan menu global.
        $this->lupakan();
        $mode = app(ModeAkses::class);
        $this->halamanHakSaja = (string) DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $this->ess)->where('Flag_Aktif', 'Y')
            ->whereNotIn('Jenis_page', DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')->select('Jenis_page'))
            ->pluck('Jenis_page')
            ->first(fn ($p) => $mode->modulHalaman((string) $p) === $this->ess && ! $this->u->memilikiJabatan((string) $p));
        $this->assertNotSame('', $this->halamanHakSaja, 'Tak ada halaman ESS tanpa hibah untuk skenario.');
        DB::table('N_HRIS_Role_Hak')->insert([
            'Id_Role' => $leader, 'Jenis_page' => $this->halamanHakSaja,
            'Id_Aksi' => DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions'),
            'Flag_Aktif' => 'Y', 'Created_At' => now(), 'Created_By' => 'UJI',
        ]);
        $this->lupakan();
    }

    private function lupakan(): void
    {
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
        KeputusanAkses::lupakanCache();
        ModeAkses::lupakan();
        AuthorizationScopeResolver::clearAllCache();
    }

    private function setMode(string $mode): void
    {
        DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(['Id_Grup_Fitur' => $this->ess], ['Mode' => $mode]);
        $this->lupakan();
    }

    public function test_lapis_halaman_resolver_ikut_mode_baru(): void
    {
        $this->assertTrue(AuthorizationScopeResolver::canAccessPage($this->u, 'IzinPageAdmin'), 'Prasyarat LAMA: hibah membuka halaman.');
        $this->assertFalse(AuthorizationScopeResolver::canAccessPage($this->u, $this->halamanHakSaja), 'Prasyarat LAMA: tanpa hibah tertutup.');

        $this->setMode('BARU');

        $this->assertFalse(AuthorizationScopeResolver::canAccessPage($this->u, 'IzinPageAdmin'), 'authorize.resource masih memakai hibah di modul BARU.');
        $this->assertTrue(AuthorizationScopeResolver::canAccessPage($this->u, $this->halamanHakSaja), 'Hak peran tak terbaca lapis halaman resolver.');
    }

    public function test_supervisor_ikut_mode_baru(): void
    {
        $this->assertTrue($this->u->isSupervisor(), 'Prasyarat LAMA: Leader adalah supervisor.');

        $this->setMode('BARU');

        $this->assertFalse($this->u->isSupervisor(), 'Gate akses-svp masih memakai aturan lama di modul BARU.');
    }

    /**
     * Review Fase 4 (I2): bandingkan dengan perhitungan LAMA resolver yang
     * sebenarnya (usablePageTypes), bukan dengan bolehLama — canAccessPage kini
     * memanggil bolehLama sendiri. Dibatasi ke halaman yang memang diperiksa
     * lewat canAccessPage (authorize.resource, ApprovalMatrixService); di
     * halaman lain resolver lama memang berbeda dari gerbang route (diterima).
     */
    public function test_lama_halaman_bergerbang_resolver_tak_berubah(): void
    {
        $normal = new \ReflectionMethod(AuthorizationScopeResolver::class, 'normalizePageKey');
        $normal->setAccessible(true);
        $halaman = ['superAdminManajemenPeranPage', 'systemAuthManagerPage', 'menuApprovalMatrixPage'];
        $ids = DB::table('N_HRIS_Role_User')->where('Flag_Aktif', 'Y')->pluck('UserId_Web')
            ->merge(DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')->whereIn('Jenis_page', $halaman)->pluck('UserID_Web'))
            ->unique();

        $beda = [];
        foreach (User::whereIn('Id_Users', $ids)->get() as $u) {
            $this->lupakan();
            $scope = AuthorizationScopeResolver::resolve($u);
            foreach ($halaman as $p) {
                $lama = $scope['isSystemAdmin'] || in_array($normal->invoke(null, $p), (array) $scope['usablePageTypes'], true);
                if (AuthorizationScopeResolver::canAccessPage($u, $p) !== $lama) {
                    $beda[] = $u->Id_Users.':'.$p;
                }
            }
        }

        $this->assertSame([], $beda, 'Keputusan lapis halaman resolver berubah di mode LAMA.');
    }

    public function test_tak_ada_pemanggil_memiliki_jabatan_di_luar_keputusan_akses(): void
    {
        $pelanggar = [];
        foreach ((new Finder)->files()->in(base_path('app'))->name('*.php') as $f) {
            $rel = 'app/'.str_replace('\\', '/', $f->getRelativePathname());
            if ($rel === 'app/Services/SYSTEM/Governance/Authorization/Akses/KeputusanAkses.php') {
                continue;
            }
            // Komentar dibuang dulu: kode mati di komentar bukan pemanggil.
            $kode = collect(token_get_all($f->getContents()))
                ->reject(fn ($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true))
                ->map(fn ($t) => is_array($t) ? $t[1] : $t)->implode('');
            $jabatan = preg_match_all("/->memilikiJabatan\\(|'memilikiJabatan'/", $kode);
            $baru = preg_match_all('/->memilikiAksesBaru\\(/', $kode);
            // User.php: definisinya sendiri memanggil memilikiAksesBaru tepat sekali (review Fase 4 — dulu
            // seluruh User.php dikecualikan, padahal isSupervisor pernah bocor di sana).
            $izinBaru = $rel === 'app/Models/ESS/Employee/Models/User.php' ? 1 : 0;
            if ($jabatan > 0 || $baru > $izinBaru) {
                $pelanggar[] = $rel;
            }
        }

        $this->assertSame([], $pelanggar, 'Keputusan halaman wajib lewat KeputusanAkses::boleh.');
    }

    public function test_telusuri_ikut_mode_modul(): void
    {
        $k = app(KeputusanAkses::class);
        $lama = $k->telusuri($this->u, 'IzinPageAdmin');
        $this->assertSame('LAMA', $lama['mode']);
        $this->assertContains('HIBAH_ROLE:HCIS_LEADER', $lama['jalur']);

        $this->setMode('BAYANGAN');
        $bayangan = $k->telusuri($this->u, 'IzinPageAdmin');
        $this->assertTrue($bayangan['boleh'], 'BAYANGAN tetap diputuskan aturan lama.');
        $this->assertContains('HIBAH_ROLE:HCIS_LEADER', $bayangan['jalur']);
        $this->assertSame([], $bayangan['jalur_baru'], 'Aturan baru menutup halaman ini; jalur_baru harus kosong.');

        $this->setMode('BARU');
        $tertutup = $k->telusuri($this->u, 'IzinPageAdmin');
        $this->assertFalse($tertutup['boleh'], 'Telusuri BARU masih menampilkan hibah.');
        $this->assertSame([], $tertutup['jalur']);
        $terbuka = $k->telusuri($this->u, $this->halamanHakSaja);
        $this->assertTrue($terbuka['boleh']);
        $this->assertSame(['HAK_PERAN:HCIS_LEADER'], $terbuka['jalur']);
    }

    public function test_telusuri_admin_modul_melihat_hak_peran_modulnya(): void
    {
        $this->setMode('BARU');
        $hasil = app(\App\Services\SYSTEM\Governance\Authorization\AksesPeran\AksesPeranQueryService::class)
            ->telusuri((int) $this->u->Id_Users, $this->halamanHakSaja, [$this->ess]);

        $this->assertSame(['HAK_PERAN:HCIS_LEADER'], $hasil['jalur'] ?? null, 'Jalur hak peran modul tersaring dari admin modul.');
    }
}
