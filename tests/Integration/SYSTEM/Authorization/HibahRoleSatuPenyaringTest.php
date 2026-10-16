<?php

namespace Tests\Integration\SYSTEM\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hibah role dan tampilan menu HARUS memakai penyaring yang sama.
 *
 * `N_HRIS_Grup_Fitur_Map` mengerjakan dua tugas: menyusun sidebar dan memberi
 * hibah akses per role. `Flag_Hibah_Role` (DDL-2026-110) memisahkan keduanya —
 * tetapi penyaringnya harus dipasang di SETIAP tempat yang menjawab pertanyaan
 * yang sama:
 *
 *   - User::memilikiAksesBaru()                         -> boleh tidaknya membuka halaman
 *   - AuthorizationScopeResolver::getUsablePageTypes()  -> tampil tidaknya menu di sidebar
 *   - InertiaShellService::getAccessiblePages()         -> tidak dipanggil lagi, tetapi dijaga
 *                                                          supaya tidak kembali tanpa penyaring
 *
 * Bila hanya satu yang disaring, gejalanya tidak berupa galat: orangnya melihat
 * menu yang dibalas 403 saat ditekan, atau sebaliknya punya hak atas halaman
 * yang tidak pernah muncul di mana pun. Keduanya baru ketahuan dari keluhan.
 *
 * Kejadian 2026-09-18 (production): penyaring hanya dipasang di User dan
 * InertiaShellService, padahal sidebar membaca resolver. Datanya sudah benar,
 * gerbang rutenya benar, tetapi pemegang ADMIN_PERMIT tetap melihat seluruh
 * menu Visitor — dan yang dicurigai lebih dulu justru cache.
 */
class HibahRoleSatuPenyaringTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Berkas yang menurunkan halaman dari role lewat peta grup fitur, beserta
     * penanda jalur hibahnya masing-masing. Penanda itu membedakan jalur hibah
     * dari JOIN lain pada tabel yang sama — pencarian grup sebuah halaman,
     * misalnya — yang memang harus membaca semua baris.
     */
    private const PEMBERI_HIBAH = [
        'app/Models/ESS/Employee/Models/User.php' => [
            'jalur' => "'gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur'",
            'penyaring' => "'gfm.Flag_Hibah_Role', '=', 'Y'",
        ],
        'app/Services/SYSTEM/Application/Shell/InertiaShellService.php' => [
            'jalur' => "'gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur'",
            'penyaring' => "'gfm.Flag_Hibah_Role', '=', 'Y'",
        ],
        'app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationScopeResolver.php' => [
            'jalur' => "->whereIn('Id_Grup_Fitur', \$manageableGroupIds)",
            'penyaring' => "'Flag_Hibah_Role', '=', 'Y'",
        ],
    ];

    public function test_setiap_kueri_hibah_menyaring_flag_hibah_role(): void
    {
        $tanpaPenyaring = [];

        foreach (self::PEMBERI_HIBAH as $berkas => $penanda) {
            $isi = (string) file_get_contents(base_path($berkas));

            if (! str_contains($isi, $penanda['jalur'])) {
                $tanpaPenyaring[] = $berkas.' (jalur hibah tidak ditemukan — berkasnya berubah bentuk?)';

                continue;
            }

            if (! str_contains($isi, $penanda['penyaring'])) {
                $tanpaPenyaring[] = $berkas;
            }
        }

        $this->assertSame(
            [],
            $tanpaPenyaring,
            'Kueri hibah tanpa penyaring Flag_Hibah_Role: '.implode(', ', $tanpaPenyaring),
        );
    }

    /**
     * Peta yang hanya struktur TIDAK boleh membuka halaman.
     *
     * Inti seluruh perubahan: memegang role Visitor tidak lagi membuka seluruh
     * modul. Diuji lewat efek — apa yang benar-benar dijawab memilikiJabatan() —
     * bukan lewat isi tabelnya.
     */
    public function test_role_tidak_lagi_membuka_halaman_yang_dicabut_hibahnya(): void
    {
        $dicabut = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
            ->where('g.Kode_Grup_Fitur', 'VISITOR')
            ->where('gfm.Flag_Hibah_Role', 'T')
            ->pluck('gfm.Jenis_page');

        $this->assertNotEmpty($dicabut, 'DML-nya belum dijalankan di basis data ini.');

        // Orang dengan SATU role domain dan tanpa kunci halaman domain seberang.
        $pemegang = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ru.Id_Role')
            ->where('r.Role_Code', 'ADMIN_TAMU')
            ->where('ru.Flag_Aktif', 'Y')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_HRIS_Role_User as ru2')
                    ->join('N_HRIS_Role_Functional as r2', 'r2.Id_Role', '=', 'ru2.Id_Role')
                    ->whereColumn('ru2.UserId_Web', 'ru.UserId_Web')
                    ->where('r2.Role_Code', 'ADMIN_PERMIT')
                    ->where('ru2.Flag_Aktif', 'Y');
            })
            ->value('ru.UserId_Web');

        if (! $pemegang) {
            $this->markTestSkipped('Tidak ada pemegang ADMIN_TAMU murni untuk diuji.');
        }

        $user = User::find($pemegang);
        $this->assertNotNull($user);

        $this->assertTrue(
            $user->memilikiJabatan('visitorVerifikatorTamuPage'),
            'Pemegang ADMIN_TAMU kehilangan halaman domainnya sendiri.',
        );
        $this->assertFalse(
            $user->memilikiJabatan('visitorVerifikatorPermitPage'),
            'Pemegang ADMIN_TAMU masih membuka meja permit; hibah grup belum benar-benar tercabut.',
        );
    }

    /**
     * Sidebar dan gerbang rute HARUS menjawab sama untuk setiap halaman Visitor.
     *
     * Sidebar tidak bertanya ke User::memilikiJabatan(), melainkan ke
     * AuthorizationScopeResolver::canAccessPage(). Kasusnya persis keluhan
     * production: pemegang ADMIN_PERMIT tanpa kunci halaman Tamu tetap melihat
     * Verifikator/Monitoring/Export/Master Data Tamu di sidebar.
     */
    public function test_sidebar_dan_gerbang_rute_sepakat_untuk_pemegang_satu_domain(): void
    {
        $halamanVisitor = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
            ->where('g.Kode_Grup_Fitur', 'VISITOR')
            ->pluck('gfm.Jenis_page')
            ->all();

        $this->assertNotEmpty($halamanVisitor);

        // Pemegang ADMIN_PERMIT murni: bukan System Admin (yang memang melihat
        // segalanya), tanpa ADMIN_TAMU, dan tanpa kunci eksplisit meja Tamu.
        $kandidat = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ru.Id_Role')
            ->where('r.Role_Code', 'ADMIN_PERMIT')
            ->where('ru.Flag_Aktif', 'Y')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_HRIS_Role_User as ru2')
                    ->join('N_HRIS_Role_Functional as r2', 'r2.Id_Role', '=', 'ru2.Id_Role')
                    ->whereColumn('ru2.UserId_Web', 'ru.UserId_Web')
                    ->where('r2.Role_Code', 'ADMIN_TAMU')
                    ->where('ru2.Flag_Aktif', 'Y');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('HRIS_Page_Access as pa')
                    ->whereColumn('pa.UserID_Web', 'ru.UserId_Web')
                    ->where('pa.Jenis_page', 'visitorVerifikatorTamuPage')
                    ->where('pa.Flag_Aktif', 'Y');
            })
            ->distinct()
            ->pluck('ru.UserId_Web');

        AuthorizationScopeResolver::clearAllCache();

        $user = $kandidat
            ->map(fn ($id) => User::find($id))
            ->first(fn (?User $u) => $u !== null && AuthorizationScopeResolver::getScopeTier($u) !== 'SYSTEM');

        if (! $user) {
            $this->markTestSkipped('Tidak ada pemegang ADMIN_PERMIT murni untuk diuji.');
        }

        $this->assertFalse(
            AuthorizationScopeResolver::canAccessPage($user, 'visitorVerifikatorTamuPage'),
            'Sidebar masih menampilkan meja Tamu bagi pemegang ADMIN_PERMIT; resolver belum menyaring Flag_Hibah_Role.',
        );

        $berbeda = [];
        foreach ($halamanVisitor as $halaman) {
            $sidebar = AuthorizationScopeResolver::canAccessPage($user, (string) $halaman);
            $rute = $user->memilikiJabatan((string) $halaman);

            if ($sidebar !== $rute) {
                $berbeda[] = sprintf('%s (sidebar=%s, rute=%s)', $halaman, $sidebar ? 'ya' : 'tidak', $rute ? 'ya' : 'tidak');
            }
        }

        $this->assertSame(
            [],
            $berbeda,
            'Menu yang tampil tidak sama dengan hak membukanya: '.implode(', ', $berbeda),
        );
    }

    /**
     * Pengajuan permit dan tamu TETAP terbuka untuk semua karyawan.
     *
     * Keduanya menu global. Penjaga ini ada karena kesalahan paling merugikan
     * dari perubahan ini bukan admin yang kelebihan menu, melainkan seluruh
     * karyawan yang kehilangan cara mengajukan tamu.
     */
    public function test_pengajuan_mandiri_tidak_ikut_tercabut(): void
    {
        foreach (['visitorPermitPage', 'visitorTamuPage'] as $halaman) {
            $this->assertSame(
                'Y',
                DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->value('Is_Global_Menu'),
                "{$halaman} berhenti menjadi menu global; seluruh karyawan kehilangan aksesnya.",
            );
        }
    }

    /**
     * Modul lain tidak boleh ikut berubah.
     *
     * Bawaan kolomnya 'Y' justru supaya perubahan ini tidak terasa di ESS, KPI,
     * LMS, DMS, SYSTEM, dan BUILDER. Baris yang tercabut di luar Visitor berarti
     * ada modul yang diam-diam kehilangan menunya.
     */
    public function test_modul_lain_tetap_menerima_hibah_penuh(): void
    {
        $tercabutDiLuarVisitor = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'gfm.Id_Grup_Fitur')
            ->where('gfm.Flag_Hibah_Role', 'T')
            ->where('g.Kode_Grup_Fitur', '<>', 'VISITOR')
            ->count();

        $this->assertSame(0, $tercabutDiLuarVisitor);
    }
}
