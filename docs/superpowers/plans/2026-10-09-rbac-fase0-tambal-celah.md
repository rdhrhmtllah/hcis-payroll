# RBAC Fase 0 — Tambal Celah Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tutup celah eskalasi hak akses tanpa DDL dan tanpa mengubah akses user lain: halaman tata kelola akses tidak lagi bisa didapat lewat hibah role operasional, Page Access Setup dibatasi dan diaudit, route lama admin dikunci System Admin.

**Architecture:** Satu kelas aturan `HibahTataKelola` (daftar halaman + daftar role admin di `config/authorization.php`) dipakai bersama oleh gerbang route (`User::memilikiAksesBaru`) dan sidebar (`AuthorizationScopeResolver::getUsablePageTypes`). `PageAccessController::updateAccess` memakai himpunan halaman kelolaan yang sama dengan `getInitialData`, menolak eskalasi, memperbaiki pemilihan target, dan menulis audit sebelum/sesudah lewat `PageAccessAudit` di transaksi yang sama. Route lama di `routes/web.php` dibungkus middleware `system.admin` yang sudah ada.

**Tech Stack:** Laravel 10, SQL Server (`Web_HRIS` dev), PHPUnit 10 (`php vendor/bin/phpunit <file>`), DatabaseTransactions.

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (bagian 3 dan 8 — Fase 0)

## Global Constraints

- Tanpa DDL dan tanpa DML ke prod; semua perubahan di kode + config.
- Grant langsung (`HRIS_Page_Access`) dan menu global tetap dihormati persis seperti sekarang.
- Hanya jalur **hibah role** ke **halaman tata kelola** yang dipersempit; halaman lain tidak berubah.
- Tanda tangan middleware `check.jabatan` dan route lama tidak berubah (hanya ditambah `system.admin`).
- Mutasi DB di test hanya di dev `Web_HRIS` dan selalu di dalam `DatabaseTransactions`.
- Teks pesan ke user dalam Bahasa Indonesia.
- Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Rulings terhadap spec (dicatat sebelum eksekusi)

- **Daftar halaman Fase 0 dipersempit** ke halaman yang *memberi atau mengatur akses*: `adminAllPagesAccess`, `superAdminManajemenPeranPage`, `systemAuthManagerPage`, `menuApprovalMatrixEssPage`, `menuApprovalMatrixKpiPage`, `menuApprovalMatrixLmsPage`, `menuApprovalMatrixVisitorPage`, `ManajemenAksesDivisi`, `ManajemenAksesLevel`, `ActivityMonitor`. Halaman setup operasional (`addKaryawanTeam`, `adminKPIKaryawanTeamPage`, `adminHCApprovalKPIPage`, `approvalFlowShift`) **ditunda** karena HC Ops kemungkinan memakainya sah; daftarnya ada di config sehingga bisa ditambah tanpa kode. Biaya bila salah: celah setup operasional tetap terbuka sampai config diubah. Spec diperbarui di Task 7.
- **Role admin sah** = role yang dirancang memegang tab admin (punya baris `N_HRIS_Role_Resource_Access` di dev) + `KPI_SUPER_ADMIN`. Biaya bila salah: admin sah lain terkunci — dimitigasi laporan dampak prod sebelum rilis.

## Review Focus

1. User memegang role operasional **dan** role admin sekaligus → tetap membuka halaman tata kelola (Task 1, `test_role_admin_tetap_menang_walau_ada_role_operasional`).
2. `Jenis_page` beda huruf/spasi di tabel map vs config (mis. `superadminmanajemenperanpage`) → tetap dikenali sebagai halaman tata kelola (Task 1, `test_normalisasi_kunci_halaman`).
3. Grant langsung ke halaman tata kelola milik admin lama tetap berlaku setelah tambalan (Task 1, `test_grant_langsung_tetap_berlaku`).
4. Halaman yang pernah dicabut (baris `Flag_Aktif='T'`) diberikan lagi → berhasil (Task 3, `test_beri_ulang_setelah_dicabut`).
5. `all=true` ke ratusan karyawan → tidak menembus batas 2.100 parameter SQL Server dan audit tetap tertulis (Task 3 dan 5, `test_beri_semua_karyawan_berpotongan`).

---

### Task 1: Aturan hibah tata kelola di gerbang route

**Files:**
- Modify: `config/authorization.php` (tambah kunci `tata_kelola`)
- Create: `app/Services/SYSTEM/Governance/Authorization/Shared/HibahTataKelola.php`
- Modify: `app/Models/ESS/Employee/Models/User.php:197-218` (query `$roleMappedPages` di `memilikiAksesBaru`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php`

**Interfaces:**
- Produces:
  - `HibahTataKelola::normalisasi(?string $page): string` — `strtolower(str_replace(' ', '', trim($page)))`, sama dengan `User::normalizePageKey`
  - `HibahTataKelola::halaman(): array<string>` — kunci ternormalisasi dari `config('authorization.tata_kelola.halaman')`
  - `HibahTataKelola::peranAdmin(): array<string>` — kode role UPPERCASE dari `config('authorization.tata_kelola.peran_admin')`
  - `HibahTataKelola::apakahHalamanTataKelola(string $page): bool`
  - `HibahTataKelola::saringHibah(\Illuminate\Database\Query\Builder $q, string $kolomHalaman, string $kolomKodeRole): \Illuminate\Database\Query\Builder`
  - `HibahTataKelola::halamanLewatPeranAdmin(int $idUser): array<string>` — halaman tata kelola (ternormalisasi) yang dicapai user lewat hibah role admin sah

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 0: halaman yang memberi/mengatur akses tidak boleh didapat lewat
 * hibah role operasional. Temuan 9 Okt 2026: HCIS_LEADER, GA_PHL_ADMIN,
 * ADMIN_HC_CUTI_VERIFIKATOR membuka Page Access Setup lalu bisa memberi diri
 * sendiri halaman apa pun.
 */
class HibahTataKelolaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lupakanCacheAkses();
    }

    private function lupakanCacheAkses(): void
    {
        $cache = new \ReflectionProperty(User::class, 'menuAccessCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    /** User aktif tanpa role apa pun dan tanpa grant ke halaman tata kelola. */
    private function userBersih(): User
    {
        $id = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'u.Id_Users')
                ->whereIn('pa.Jenis_page', ['adminAllPagesAccess', 'systemAuthManagerPage', 'superAdminManajemenPeranPage']))
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');
        $this->assertNotNull($id, 'Tak ada user bersih untuk uji.');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function beriRole(User $u, string $kode): void
    {
        $idRole = DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->where('Flag_Aktif', 'Y')->value('Id_Role');
        $this->assertNotNull($idRole, "Role {$kode} tidak ada/aktif di dev.");
        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => $u->Id_Users, 'Id_Role' => $idRole, 'Assigned_At' => now(),
            'Assigned_By' => 'UJI', 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakanCacheAkses();
    }

    public function test_role_operasional_tidak_membuka_halaman_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        $this->assertFalse($u->memilikiJabatan('adminAllPagesAccess'));
        $this->assertFalse($u->memilikiJabatan('systemAuthManagerPage'));
        $this->assertFalse($u->memilikiJabatan('superAdminManajemenPeranPage'));
    }

    public function test_role_operasional_tetap_membuka_halaman_non_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        // Halaman admin ESS biasa — bukan tata kelola — tidak boleh ikut hilang.
        $this->assertTrue($u->memilikiJabatan('IzinPageAdmin'));
    }

    public function test_role_admin_sah_membuka_halaman_tata_kelola(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'ESS_MENU_ADMINISTRATOR');

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_role_admin_tetap_menang_walau_ada_role_operasional(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $this->beriRole($u, 'ESS_MENU_ADMINISTRATOR');

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_grant_langsung_tetap_berlaku(): void
    {
        $u = $this->userBersih();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'adminAllPagesAccess', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        $this->lupakanCacheAkses();

        $this->assertTrue($u->memilikiJabatan('adminAllPagesAccess'));
    }

    public function test_normalisasi_kunci_halaman(): void
    {
        $this->assertTrue(HibahTataKelola::apakahHalamanTataKelola('superadminmanajemenperanpage'));
        $this->assertTrue(HibahTataKelola::apakahHalamanTataKelola(' Super AdminManajemenPeranPage '));
        $this->assertFalse(HibahTataKelola::apakahHalamanTataKelola('IzinPageAdmin'));
    }

    public function test_halaman_lewat_peran_admin(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');
        $this->assertSame([], HibahTataKelola::halamanLewatPeranAdmin((int) $u->Id_Users));

        $this->beriRole($u, 'ESS_MENU_ADMINISTRATOR');
        $this->assertContains('adminallpagesaccess', HibahTataKelola::halamanLewatPeranAdmin((int) $u->Id_Users));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php`
Expected: `Error: Class "App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola" not found` pada uji normalisasi/lewat-peran, dan `test_role_operasional_tidak_membuka_halaman_tata_kelola` FAIL (Leader masih membuka). `test_role_operasional_tetap_membuka_halaman_non_tata_kelola`, `test_role_admin_sah_*`, `test_grant_langsung_tetap_berlaku` sudah PASS (perilaku lama yang harus dipertahankan).

- [ ] **Step 3: Tambah config**

Di `config/authorization.php`, sebelum kunci `'cache' => [`:

```php
    /*
     * RBAC Fase 0 (spec 2026-10-09-rbac-akses-peran-design.md): halaman yang
     * memberi/mengatur akses orang lain. Untuk halaman ini, hibah role
     * (N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role) hanya berlaku bagi role admin
     * sah di bawah. Grant langsung (HRIS_Page_Access) dan System Admin tidak
     * terpengaruh. Tambah halaman/role di sini tanpa mengubah kode.
     */
    'tata_kelola' => [
        'halaman' => [
            'adminAllPagesAccess',
            'superAdminManajemenPeranPage',
            'systemAuthManagerPage',
            'menuApprovalMatrixEssPage',
            'menuApprovalMatrixKpiPage',
            'menuApprovalMatrixLmsPage',
            'menuApprovalMatrixVisitorPage',
            'ManajemenAksesDivisi',
            'ManajemenAksesLevel',
            'ActivityMonitor',
        ],
        'peran_admin' => [
            'ESS_MENU_ADMINISTRATOR',
            'ESS_APPROVAL_ADMINISTRATOR',
            'ESS_MEMBERSHIP_ADMINISTRATOR',
            'ESS_MENU_APPROVAL_ADMINISTRATOR',
            'ESS_MENU_ADMINISTRATOR_PHL',
            'ESS_APPROVAL_ADMINISTRATOR_PHL',
            'KPI_SUPER_ADMIN',
            'KPI_ADMIN_PHL',
            'KPI_MENU_ADMINISTRATOR',
            'KPI_APPROVAL_ADMINISTRATOR',
            'KPI_MEMBERSHIP_ADMINISTRATOR',
            'KPI_MENU_APPROVAL_ADMINISTRATOR',
            'LMS_MENU_ADMINISTRATOR',
            'LMS_APPROVAL_ADMINISTRATOR',
            'LMS_MENU_APPROVAL_ADMINISTRATOR',
            'VISITOR_MENU_ADMINISTRATOR',
            'VISITOR_APPROVAL_ADMINISTRATOR',
            'VISITOR_MENU_APPROVAL_ADMINISTRATOR',
        ],
    ],
```

- [ ] **Step 4: Buat `HibahTataKelola`**

```php
<?php

namespace App\Services\SYSTEM\Governance\Authorization\Shared;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RBAC Fase 0: hibah role ke halaman tata kelola hanya untuk role admin sah.
 *
 * Satu rumah untuk gerbang route (User::memilikiAksesBaru) dan sidebar
 * (AuthorizationScopeResolver::getUsablePageTypes) — keduanya WAJIB memakai
 * kelas ini supaya menu dan gerbang tidak berselisih lagi.
 */
class HibahTataKelola
{
    public static function normalisasi(?string $page): string
    {
        return strtolower(str_replace(' ', '', trim((string) $page)));
    }

    /** @return array<string> */
    public static function halaman(): array
    {
        return array_values(array_unique(array_filter(array_map(
            [self::class, 'normalisasi'],
            (array) config('authorization.tata_kelola.halaman', []),
        ))));
    }

    /** @return array<string> */
    public static function peranAdmin(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($k) => strtoupper(trim((string) $k)),
            (array) config('authorization.tata_kelola.peran_admin', []),
        ))));
    }

    public static function apakahHalamanTataKelola(string $page): bool
    {
        return in_array(self::normalisasi($page), self::halaman(), true);
    }

    /**
     * Saring kueri hibah: baris halaman tata kelola hanya lolos bila role-nya
     * admin sah. Halaman lain tidak tersentuh.
     */
    public static function saringHibah(Builder $q, string $kolomHalaman, string $kolomKodeRole): Builder
    {
        $halaman = self::halaman();
        if ($halaman === []) {
            return $q;
        }
        $peran = self::peranAdmin();
        $tandaHalaman = implode(',', array_fill(0, count($halaman), '?'));
        $normal = "LOWER(REPLACE(LTRIM(RTRIM({$kolomHalaman})), ' ', ''))";

        if ($peran === []) {
            return $q->whereRaw("{$normal} NOT IN ({$tandaHalaman})", $halaman);
        }
        $tandaPeran = implode(',', array_fill(0, count($peran), '?'));

        return $q->whereRaw(
            "({$normal} NOT IN ({$tandaHalaman}) OR UPPER({$kolomKodeRole}) IN ({$tandaPeran}))",
            array_merge($halaman, $peran),
        );
    }

    /** @return array<string> halaman tata kelola (ternormalisasi) lewat role admin sah */
    public static function halamanLewatPeranAdmin(int $idUser): array
    {
        $peran = self::peranAdmin();
        if ($peran === [] || self::halaman() === []) {
            return [];
        }

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Grup_Fitur_Map as gfm', function ($j) {
                $j->on('gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')->where('gfm.Flag_Hibah_Role', '=', 'Y');
            })
            ->where('ru.UserId_Web', $idUser)
            ->where('ru.Flag_Aktif', 'Y')
            ->whereIn(DB::raw('UPPER(rf.Role_Code)'), $peran)
            ->pluck('gfm.Jenis_page')
            ->map(fn ($p) => self::normalisasi((string) $p))
            ->filter(fn ($p) => in_array($p, self::halaman(), true))
            ->unique()
            ->values()
            ->all();
    }
}
```

- [ ] **Step 5: Pasang di `User::memilikiAksesBaru`**

Tambah `use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;` di atas kelas. Pada `$roleMappedPages`, setelah `->where('ru.Flag_Aktif', 'Y')` dan sebelum `->select('gfm.Jenis_page as Jenis_page');`, sisipkan:

```php
                // RBAC Fase 0: halaman tata kelola hanya lewat role admin sah.
                // Kembarannya di AuthorizationScopeResolver::getUsablePageTypes.
                ->tap(fn ($q) => HibahTataKelola::saringHibah($q, 'gfm.Jenis_page', 'rf.Role_Code'))
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php`
Expected: `OK (7 tests, ...)`

Run juga: `php vendor/bin/phpunit tests/Feature/ESS/Employee/AdminPengumpulanDataHalamanTest.php` dan `php vendor/bin/phpunit --filter UserPageAccessSatuPerjalanan tests`
Expected: OK (perilaku gabungan union tidak berubah).

- [ ] **Step 7: Commit**

```bash
git add config/authorization.php app/Services/SYSTEM/Governance/Authorization/Shared/HibahTataKelola.php app/Models/ESS/Employee/Models/User.php tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php
git commit -m "fix(system): halaman tata kelola tak lagi lewat hibah role operasional

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: Sidebar memakai aturan yang sama

**Files:**
- Modify: `app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationScopeResolver.php:403-436` (`getUsablePageTypes`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php` (tambah dua uji)

**Interfaces:**
- Consumes: `HibahTataKelola::apakahHalamanTataKelola`, `HibahTataKelola::halamanLewatPeranAdmin`, `HibahTataKelola::normalisasi` (Task 1)

- [ ] **Step 1: Write the failing test** — tambahkan ke `HibahTataKelolaTest`:

```php
    private function sidebarBoleh(User $u, string $page): bool
    {
        \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::clearCache($u);

        return \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::canAccessPage($u, $page);
    }

    public function test_sidebar_sejalan_dengan_gerbang_untuk_role_operasional(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'HCIS_LEADER');

        foreach (['adminAllPagesAccess', 'systemAuthManagerPage', 'superAdminManajemenPeranPage'] as $p) {
            $this->assertSame($u->memilikiJabatan($p), $this->sidebarBoleh($u, $p), "Sidebar & gerbang berselisih untuk {$p}.");
            $this->assertFalse($this->sidebarBoleh($u, $p));
        }
    }

    public function test_sidebar_role_admin_sah_tetap_melihat(): void
    {
        $u = $this->userBersih();
        $this->beriRole($u, 'ESS_MENU_ADMINISTRATOR');

        $this->assertTrue($this->sidebarBoleh($u, 'adminAllPagesAccess'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter sidebar tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php`
Expected: `test_sidebar_sejalan_dengan_gerbang_untuk_role_operasional` FAIL (sidebar masih true untuk Leader). Bila Leader tidak masuk `manageableGroupIds` sehingga sidebar sudah false, uji ini PASS — catat sebagai Ruling di ledger dan lanjut (penyaring tetap dipasang demi konsistensi).

- [ ] **Step 3: Implementasi** — di `getUsablePageTypes`, ganti blok `if (! empty($manageableGroupIds)) { ... }` menjadi:

```php
        if (! empty($manageableGroupIds)) {
            $pageTypes = DB::table('N_HRIS_Grup_Fitur_Map')
                ->whereIn('Id_Grup_Fitur', $manageableGroupIds)
                ->where('Flag_Hibah_Role', '=', 'Y')
                ->distinct()
                ->pluck('Jenis_page')
                ->toArray();

            // RBAC Fase 0: kembaran penyaring di User::memilikiAksesBaru.
            $lewatAdmin = HibahTataKelola::halamanLewatPeranAdmin(self::getUserId($user));
            $pageTypes = array_values(array_filter(
                $pageTypes,
                fn ($p) => ! HibahTataKelola::apakahHalamanTataKelola((string) $p)
                    || in_array(HibahTataKelola::normalisasi((string) $p), $lewatAdmin, true),
            ));
        }
```

Tambah `use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;` bila kelasnya beda namespace (keduanya di `...\Authorization\Shared`, jadi tidak perlu `use`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php tests/Feature/SYSTEM/Governance`
Expected: OK

- [ ] **Step 5: Commit**

```bash
git add app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationScopeResolver.php tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php
git commit -m "fix(system): sidebar menyaring halaman tata kelola seperti gerbang route

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: Page Access Setup — pemilihan target yang benar

Cacat yang ditemukan saat menyusun rencana: `updateAccess` memilih target lewat INNER JOIN ke `HRIS_Page_Access` tanpa melihat `Flag_Aktif`, sehingga (a) karyawan yang belum pernah punya baris untuk halaman itu tidak pernah terpilih — "beri akses" gagal dengan "Seluruh karyawan sudah memiliki akses", (b) halaman yang pernah dicabut tidak bisa diberikan lagi, (c) mencabut dari karyawan yang memiliki semua halaman terpilih tidak terjadi.

**Files:**
- Modify: `app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php:147-180`
- Test: `tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`

**Interfaces:**
- Produces (private, dipakai Task 4 & 5):
  - `PageAccessController::aksesAktif(array $idUser, array $jenisHalaman): array<int, list<string>>` — halaman aktif per user (Jenis_page asli)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Page Access Setup (`/api/v1/page-access/update-access`). */
class PageAccessUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
        $cache = new \ReflectionProperty(User::class, 'menuAccessCache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }

    /**
     * Admin Page Access yang punya halaman kelolaan (lewat N_HRIS_Role_Konten_Access).
     *
     * @return array{0: User, 1: object} [admin, satu halaman kelolaan non-tata-kelola {id, Jenis_page}]
     */
    protected function adminDanHalaman(): array
    {
        $baris = DB::table('HRIS_Page_Access as pa')
            ->join('N_HRIS_Role_Konten_Access as rka', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Id_Grup_Fitur', '=', 'rka.Id_Grup_Fitur')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('pa.Flag_Aktif', 'Y')->where('rka.Flag_Aktif', 'Y')
            ->whereNotIn('m.Jenis_page', (array) config('authorization.tata_kelola.halaman'))
            ->orderBy('pa.UserID_Web')
            ->first(['pa.UserID_Web', 'm.id_menu as id', 'm.Jenis_page']);
        $this->assertNotNull($baris, 'Tak ada admin Page Access berhalaman kelolaan di dev.');
        $admin = User::where('Id_Users', $baris->UserID_Web)->firstOrFail();
        if (! $admin->memilikiJabatan('adminAllPagesAccess')) {
            DB::table('HRIS_Page_Access')->insert([
                'Kode_Perusahaan' => '001', 'Jenis_page' => 'adminAllPagesAccess', 'UserID_Web' => $admin->Id_Users,
                'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
            ]);
        }

        return [$admin, $baris];
    }

    /** Karyawan aktif (bukan admin) tanpa baris apa pun untuk halaman ini. */
    protected function karyawanTanpaBaris(string $jenisPage, int $kecuali): int
    {
        $id = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')->where('k.UserID_Web', '<>', $kecuali)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')
                ->whereColumn('pa.UserID_Web', 'k.UserID_Web')->where('pa.Jenis_page', $jenisPage))
            ->orderBy('k.UserID_Web')
            ->value('k.UserID_Web');
        $this->assertNotNull($id);

        return (int) $id;
    }

    protected function kirim(User $admin, array $isi)
    {
        return $this->actingAs($admin)->postJson('/api/v1/page-access/update-access', $isi);
    }

    protected function aktif(int $idUser, string $jenisPage): bool
    {
        return DB::table('HRIS_Page_Access')->where('UserID_Web', $idUser)
            ->where('Jenis_page', $jenisPage)->where('Flag_Aktif', 'Y')->exists();
    }

    public function test_beri_akses_ke_karyawan_yang_belum_punya_baris(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])
            ->assertOk();

        $this->assertTrue($this->aktif($target, $hal->Jenis_page));
    }

    public function test_beri_ulang_setelah_dicabut(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $isi = ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id]];

        $this->kirim($admin, $isi + ['action' => 'grant'])->assertOk();
        $this->kirim($admin, $isi + ['action' => 'revoke'])->assertOk();
        $this->assertFalse($this->aktif($target, $hal->Jenis_page));

        $this->kirim($admin, $isi + ['action' => 'grant'])->assertOk();
        $this->assertTrue($this->aktif($target, $hal->Jenis_page));
    }

    public function test_tanpa_perubahan_dijawab_422(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'revoke'])
            ->assertStatus(422);
    }

    public function test_beri_semua_karyawan_berpotongan(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $tanpa = DB::table('Karyawan as k')->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')
            ->where('k.UserID_Web', '<>', $admin->Id_Users)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                ->where('pa.Jenis_page', $hal->Jenis_page)->where('pa.Flag_Aktif', 'Y'))
            ->count();
        $this->assertSame(0, $tanpa, 'Masih ada karyawan aktif tanpa akses setelah "beri semua".');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: `test_beri_akses_ke_karyawan_yang_belum_punya_baris` FAIL (500, "Seluruh karyawan sudah memiliki akses"), `test_beri_ulang_setelah_dicabut` FAIL, `test_tanpa_perubahan_dijawab_422` FAIL (500 ≠ 422).

- [ ] **Step 3: Implementasi** — ganti isi `try {` di `updateAccess` dari `$pageJenis = ...` sampai `$validated['employeeIds'] = $allEmployeeIds->toArray();` beserta blok `if ($allEmployeeIds->count() == 0) { ... }` menjadi:

```php
            $jenisHalaman = DB::table('N_HRIS_Menus')->whereIn('id_menu', $validated['pageIds'])
                ->pluck('Jenis_page')->unique()->values()->all();

            // Kandidat: karyawan aktif ber-user, dipilih eksplisit atau semua.
            $kandidat = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
                ->when(! ($validated['all'] ?? false), fn ($q) => $q->whereIn('UserID_Web', $validated['employeeIds'] ?? []))
                ->pluck('UserID_Web')->map(fn ($v) => (int) $v)->unique()->values()->all();

            // Target = kandidat yang benar-benar berubah, dilihat dari baris AKTIF.
            $sebelum = $this->aksesAktif($kandidat, $jenisHalaman);
            $target = array_values(array_filter($kandidat, fn ($id) => $validated['action'] === 'grant'
                ? count($sebelum[$id] ?? []) < count($jenisHalaman)
                : ! empty($sebelum[$id])));

            if ($target === []) {
                DB::rollBack();

                return ResponseHelper::error($validated['action'] === 'grant'
                    ? 'Seluruh karyawan terpilih sudah memiliki akses halaman ini.'
                    : 'Seluruh karyawan terpilih memang tidak memiliki akses halaman ini.', 422);
            }
            $validated['employeeIds'] = $target;
            $pageJenis = collect($jenisHalaman);
```

Tambahkan metode privat di kelas:

```php
    /**
     * Halaman AKTIF per user di antara $jenisHalaman, berpotongan 500 user
     * (batas 2.100 parameter SQL Server).
     *
     * @return array<int, list<string>>
     */
    private function aksesAktif(array $idUser, array $jenisHalaman): array
    {
        $hasil = [];
        foreach (array_chunk($idUser, 500) as $potongan) {
            DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $potongan)
                ->whereIn('Jenis_page', $jenisHalaman)
                ->where('Flag_Aktif', 'Y')
                ->get(['UserID_Web', 'Jenis_page'])
                ->each(function ($b) use (&$hasil) {
                    $hasil[(int) $b->UserID_Web][] = (string) $b->Jenis_page;
                });
        }

        return array_map(fn ($l) => array_values(array_unique($l)), $hasil);
    }
```

Sisa metode (cabang revoke/grant, `PenyisipanBerbatasParameter`) tidak berubah.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: `OK (4 tests, ...)`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php
git commit -m "fix(system): Page Access Setup memilih target dari akses aktif

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Page Access Setup — tolak eskalasi

**Files:**
- Modify: `app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php` (`getInitialData` langkah 1–2 diekstrak; `updateAccess` awal)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php` (tambah uji)

**Interfaces:**
- Consumes: `HibahTataKelola::apakahHalamanTataKelola` (Task 1)
- Produces (private): `PageAccessController::halamanKelolaan(int $adminId): \Illuminate\Support\Collection` — baris `{id, nama, module, Jenis_page}` halaman yang boleh dikelola admin (logika yang sekarang ada di `getInitialData` langkah 1–2)

- [ ] **Step 1: Write the failing test** — tambahkan ke `PageAccessUpdateTest`:

```php
    public function test_halaman_di_luar_kelolaan_ditolak(): void
    {
        [$admin] = $this->adminDanHalaman();
        $kelolaan = DB::table('HRIS_Page_Access as pa')
            ->join('N_HRIS_Role_Konten_Access as rka', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->where('pa.UserID_Web', $admin->Id_Users)->where('pa.Flag_Aktif', 'Y')->where('rka.Flag_Aktif', 'Y')
            ->pluck('rka.Id_Grup_Fitur');
        $luar = DB::table('N_HRIS_Menus as m')
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Grup_Fitur_Map as g')->whereColumn('g.Jenis_page', 'm.Jenis_page')
                ->whereIn('g.Id_Grup_Fitur', $kelolaan))
            ->value('m.id_menu');
        $this->assertNotNull($luar);
        $target = $this->karyawanTanpaBaris('__tidak_ada__', (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$luar], 'action' => 'grant'])
            ->assertStatus(403);
    }

    public function test_halaman_tata_kelola_hanya_oleh_system_admin(): void
    {
        [$admin] = $this->adminDanHalaman();
        DB::table('KPI_Users')->where('Id_Users', $admin->Id_Users)->update(['Is_System_Admin' => null]);
        $admin->refresh();
        $idTataKelola = DB::table('N_HRIS_Menus')->where('Jenis_page', 'systemAuthManagerPage')->value('id_menu');
        $target = $this->karyawanTanpaBaris('systemAuthManagerPage', (int) $admin->Id_Users);

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$idTataKelola], 'action' => 'grant'])
            ->assertStatus(403);
        $this->assertFalse($this->aktif($target, 'systemAuthManagerPage'));
    }

    public function test_tidak_bisa_mengubah_akses_diri_sendiri(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();

        $this->kirim($admin, ['all' => false, 'employeeIds' => [(int) $admin->Id_Users], 'pageIds' => [$hal->id], 'action' => 'revoke'])
            ->assertStatus(422);
    }

    public function test_beri_semua_tidak_menyentuh_diri_sendiri(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        // Admin sendiri memegang halaman itu secara aktif.
        DB::table('HRIS_Page_Access')->where('UserID_Web', $admin->Id_Users)->where('Jenis_page', $hal->Jenis_page)->delete();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => $hal->Jenis_page, 'UserID_Web' => $admin->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);
        // Pastikan ada karyawan lain yang bisa dicabut.
        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'revoke'])->assertOk();

        $this->assertTrue(
            $this->aktif((int) $admin->Id_Users, $hal->Jenis_page),
            'Cabut-semua ikut mencabut akses admin sendiri.',
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter 'kelolaan|tata_kelola|diri_sendiri' tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: keempatnya FAIL (200 alih-alih 403/422, atau akses admin ikut dicabut).

- [ ] **Step 3: Implementasi**

(a) Ekstrak langkah 1–2 `getInitialData` ke metode privat dan pakai di `getInitialData` (`$manageablePages = $this->halamanKelolaan($adminId);`):

```php
    /** Halaman yang boleh dikelola admin ini (lewat N_HRIS_Role_Konten_Access). */
    private function halamanKelolaan(int $adminId): \Illuminate\Support\Collection
    {
        $grup = DB::table('HRIS_Page_Access as pa')
            ->join('N_HRIS_Role_Konten_Access as rka', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->where('pa.Flag_Aktif', 'Y')
            ->where('rka.Flag_Aktif', 'Y')
            ->where('pa.UserID_Web', $adminId)
            ->whereNotNull('rka.Id_Grup_Fitur')
            ->pluck('rka.Id_Grup_Fitur')
            ->unique();

        return DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'gfm.Jenis_page', '=', 'm.Jenis_page')
            ->whereIn('gfm.Id_Grup_Fitur', $grup)
            ->select('m.id_menu as id', 'm.Nama_Menu as nama', 'm.Header_Label as module', 'm.Jenis_page')
            ->distinct()
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get();
    }
```

(b) Di `updateAccess`, tepat setelah `$validated = $request->validate([...]);` dan sebelum `DB::beginTransaction();`:

```php
        $admin = Auth::user();
        $adminId = (int) $admin->Id_Users;
        $systemAdmin = ($admin->Is_System_Admin ?? null) === 'Y';

        // Hanya halaman kelolaan admin ini (sama dengan yang tampil di layar).
        $kelolaan = $this->halamanKelolaan($adminId)->pluck('Jenis_page', 'id');
        $diminta = array_values(array_unique(array_map('intval', $validated['pageIds'])));
        $luar = array_diff($diminta, array_map('intval', $kelolaan->keys()->all()));
        if ($luar !== []) {
            return ResponseHelper::error('Anda tidak berwenang mengelola sebagian halaman yang dipilih.', 403);
        }

        // Halaman tata kelola akses hanya boleh diberikan/dicabut System Admin.
        if (! $systemAdmin && $kelolaan->only($diminta)->contains(fn ($p) => HibahTataKelola::apakahHalamanTataKelola((string) $p))) {
            return ResponseHelper::error('Halaman pengatur akses hanya dapat dikelola System Admin.', 403);
        }

        // Tidak boleh mengubah akses diri sendiri.
        if (! ($validated['all'] ?? false) && in_array($adminId, array_map('intval', $validated['employeeIds'] ?? []), true)) {
            return ResponseHelper::error('Anda tidak dapat mengubah akses diri sendiri.', 422);
        }
```

(c) Di Task 3 Step 3, query `$kandidat` tambah `->where('UserID_Web', '<>', $adminId)` (mode "semua" melewati admin sendiri).

(d) `use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;` di atas kelas.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: `OK (8 tests, ...)`

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php
git commit -m "fix(system): Page Access Setup menolak eskalasi dan ubah-diri-sendiri

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Page Access Setup — audit sebelum/sesudah

**Files:**
- Create: `app/Services/SYSTEM/Governance/Authorization/PageAccess/PageAccessAudit.php`
- Modify: `app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php` (panggil audit sebelum `DB::commit()`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php` (tambah uji)

**Interfaces:**
- Consumes: `PageAccessController::aksesAktif` (Task 3), `AdminLogNumberService::generate(): string`, `PenyisipanBerbatasParameter::sisipkan(string $tabel, array $baris)`
- Produces: `PageAccessAudit::catat(\App\Models\ESS\Employee\Models\User $admin, string $aksi, array $jenisHalaman, array $sebelum, array $sesudah): string` — `$sebelum`/`$sesudah` = `array<int idUser, list<string>>`; mengembalikan `No_Transaksi`

- [ ] **Step 1: Write the failing test** — tambahkan ke `PageAccessUpdateTest`:

```php
    public function test_perubahan_tercatat_sebelum_dan_sesudah(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $kode = DB::table('Karyawan')->where('UserID_Web', $target)->value('Kode_Karyawan');
        $awal = (int) DB::table('HRIS_Transaksi_Log_Admin_Detail')->max('Urut_Oto');

        $this->kirim($admin, ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])
            ->assertOk();

        $detail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->where('Urut_Oto', '>', $awal)
            ->where('Jenis', 'PAGE_ACCESS')->where('Kode_Karyawan', $kode)->first();
        $this->assertNotNull($detail, 'Perubahan Page Access tidak tercatat.');
        $det = DB::table('HRIS_Transaksi_Log_Admin_Det')->where('No_Transaksi', $detail->No_Transaksi)
            ->where('Urut_Detail', $detail->Urut_Oto)->first();
        $this->assertNotNull($det);
        $this->assertSame('', (string) $det->Nilai_Awal);
        $this->assertStringContainsString($hal->Jenis_page, (string) $det->Nilai_Setelah);
    }

    public function test_beri_semua_tercatat_per_karyawan(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $awal = (int) DB::table('HRIS_Transaksi_Log_Admin_Detail')->max('Urut_Oto');
        $belum = DB::table('Karyawan as k')->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web')
            ->where('k.UserID_Web', '<>', $admin->Id_Users)
            ->whereNotExists(fn ($q) => $q->from('HRIS_Page_Access as pa')->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                ->where('pa.Jenis_page', $hal->Jenis_page)->where('pa.Flag_Aktif', 'Y'))
            ->count();

        $this->kirim($admin, ['all' => true, 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $this->assertSame($belum, DB::table('HRIS_Transaksi_Log_Admin_Detail')
            ->where('Urut_Oto', '>', $awal)->where('Jenis', 'PAGE_ACCESS')->count());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter tercatat tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: FAIL "Perubahan Page Access tidak tercatat." dan 0 ≠ jumlah.

- [ ] **Step 3: Buat `PageAccessAudit`**

```php
<?php

namespace App\Services\SYSTEM\Governance\Authorization\PageAccess;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Audit\Numbering\AdminLogNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Jejak Page Access Setup ke HRIS_Transaksi_Log_Admin(_Detail/_Det): satu
 * header, satu detail + satu det per karyawan yang berubah, nilai SEBELUM dan
 * SESUDAH. Dipanggil di dalam transaksi updateAccess — gagal mencatat berarti
 * perubahan aksesnya ikut batal.
 */
class PageAccessAudit
{
    public const JENIS = 'PAGE_ACCESS';

    /**
     * @param  array<int, list<string>>  $sebelum
     * @param  array<int, list<string>>  $sesudah
     */
    public function catat(User $admin, string $aksi, array $jenisHalaman, array $sebelum, array $sesudah): string
    {
        $no = app(AdminLogNumberService::class)->generate();
        $now = now();
        $ids = array_values(array_unique(array_map('intval', array_merge(array_keys($sebelum), array_keys($sesudah)))));

        $kodeAdmin = DB::table('Karyawan')->where('UserID_Web', $admin->Id_Users)->value('Kode_Karyawan')
            ?? (string) ($admin->Username ?? $admin->Id_Users);
        DB::table('HRIS_Transaksi_Log_Admin')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no, 'Tanggal' => $now,
            'Jam' => $now->format('H:i'), 'UserID' => $kodeAdmin,
        ]);

        $kode = [];
        foreach (array_chunk($ids, 500) as $p) {
            $kode += DB::table('Karyawan')->whereIn('UserID_Web', $p)->pluck('Kode_Karyawan', 'UserID_Web')->all();
        }

        $deskripsi = mb_substr(strtoupper($aksi).' '.implode(',', $jenisHalaman), 0, 255);
        PenyisipanBerbatasParameter::sisipkan('HRIS_Transaksi_Log_Admin_Detail', array_map(fn ($id) => [
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no,
            'Kode_Karyawan' => mb_substr((string) ($kode[$id] ?? $id), 0, 20),
            'Jenis' => self::JENIS, 'Deskripsi' => $deskripsi,
        ], $ids));

        $urut = DB::table('HRIS_Transaksi_Log_Admin_Detail')->where('No_Transaksi', $no)
            ->pluck('Urut_Oto', 'Kode_Karyawan')->all();

        PenyisipanBerbatasParameter::sisipkan('HRIS_Transaksi_Log_Admin_Det', array_map(fn ($id) => [
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no,
            'Field_Diubah' => 'HRIS_Page_Access.Flag_Aktif', 'Deskripsi' => $deskripsi,
            'Nilai_Awal' => mb_substr(implode(',', $sebelum[$id] ?? []), 0, 255),
            'Nilai_Setelah' => mb_substr(implode(',', $sesudah[$id] ?? []), 0, 255),
            'Urut_Detail' => $urut[mb_substr((string) ($kode[$id] ?? $id), 0, 20)] ?? null,
        ], $ids));

        return $no;
    }
}
```

- [ ] **Step 4: Panggil dari `updateAccess`** — tepat sebelum `DB::commit();`:

```php
            $sesudah = $this->aksesAktif($validated['employeeIds'], $jenisHalaman);
            app(PageAccessAudit::class)->catat(
                Auth::user(),
                $validated['action'],
                $jenisHalaman,
                array_intersect_key($sebelum, array_flip($validated['employeeIds'])) + array_fill_keys($validated['employeeIds'], []),
                $sesudah + array_fill_keys($validated['employeeIds'], []),
            );
```

Tambah `use App\Services\SYSTEM\Governance\Authorization\PageAccess\PageAccessAudit;`.

- [ ] **Step 5: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php`
Expected: `OK (10 tests, ...)`

- [ ] **Step 6: Commit**

```bash
git add app/Services/SYSTEM/Governance/Authorization/PageAccess/PageAccessAudit.php app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php tests/Feature/SYSTEM/Governance/Authorization/PageAccessUpdateTest.php
git commit -m "feat(system): audit sebelum/sesudah untuk Page Access Setup

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Route lama admin dikunci System Admin

**Files:**
- Modify: `routes/web.php:202-271`
- Test: `tests/Feature/SYSTEM/Governance/Authorization/RuteLamaSystemAdminTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route admin warisan (Blade lama, tak ada di menu) dulu cukup login.
 * RBAC Fase 0 menguncinya ke System Admin.
 */
class RuteLamaSystemAdminTest extends TestCase
{
    private const RUTE = [
        'GET dashboardSuperUser',
        'GET master-periode',
        'GET master-periode/display_tambah',
        'POST master-periode/display_tambah',
        'GET crossReviewPeriode',
        'GET addCrossReviewPeriode',
        'POST storeCrossReviewPeriode',
        'POST destroyCrossPeriode',
        'GET divison',
        'GET level',
        'GET employee',
    ];

    public function test_rute_lama_admin_bergerbang_system_admin(): void
    {
        foreach (self::RUTE as $r) {
            [$metode, $uri] = explode(' ', $r, 2);
            $rute = collect(Route::getRoutes())->first(fn ($x) => $x->uri() === $uri && in_array($metode, $x->methods(), true));
            $this->assertNotNull($rute, "Route {$r} tidak ditemukan.");
            $this->assertContains('system.admin', $rute->gatherMiddleware(), "Route {$r} belum dikunci system.admin.");
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/RuteLamaSystemAdminTest.php`
Expected: FAIL "Route GET dashboardSuperUser belum dikunci system.admin."

- [ ] **Step 3: Implementasi** — di `routes/web.php`, tambahkan `->middleware('system.admin')` pada setiap definisi berikut (tanpa mengubah URI, controller, atau nama):
  - `Route::get('/dashboardSuperUser', ...)->name('superUser')` → `Route::get('/dashboardSuperUser', ...)->middleware('system.admin')->name('superUser')`
  - tiga route `master-periode` (GET index, GET display_tambah, POST display_tambah)
  - `Route::get('/crossReviewPeriode', ...)`, `Route::get('/addCrossReviewPeriode', ...)`, `route::post('/storeCrossReviewPeriode', ...)`, `route::post('/destroyCrossPeriode', ...)`
  - `Route::get('/divison', ...)`, `Route::get('/level', ...)`, `Route::get('/employee', ...)`

  Di atas blok `// Division` tambahkan komentar: `// RBAC Fase 0: halaman admin warisan (Blade lama, tak ada di menu) — khusus System Admin.`

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/RuteLamaSystemAdminTest.php tests/Feature/SYSTEM/RuteTulisSelaluBergerbangTest.php && php scripts/route-snapshot.php > /tmp/route-snapshot-fase0.txt; echo exit $?`
Expected: OK; route-snapshot berjalan (hanya kolom middleware 11 route ini yang berbeda dari master).

- [ ] **Step 5: Commit**

```bash
git add routes/web.php tests/Feature/SYSTEM/Governance/Authorization/RuteLamaSystemAdminTest.php
git commit -m "fix(system): route admin warisan khusus System Admin

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7: Selaraskan spec & laporan dampak, verifikasi menyeluruh

**Files:**
- Modify: `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (bagian 8 "Fase 0 rinci")
- Modify: `database/runbook/2026-10-09-rbac-fase0-laporan-dampak.sql` (daftar `TATA_KELOLA` = config; tandai role admin sah)

- [ ] **Step 1: Perbarui spec** — ganti daftar halaman di "Fase 0 rinci" dengan 10 halaman di config Task 1 dan tambahkan kalimat: "Halaman setup operasional (`addKaryawanTeam`, `adminKPIKaryawanTeamPage`, `adminHCApprovalKPIPage`, `approvalFlowShift`) ditunda; cukup menambah ke `authorization.tata_kelola.halaman` bila diputuskan."

- [ ] **Step 2: Perbarui query laporan** — di CTE `halaman`, daftar `IN (...)` untuk `TATA_KELOLA` diganti persis 10 halaman config; di result set pertama tambahkan kolom:

```sql
       CASE WHEN EXISTS (
            SELECT 1 FROM N_HRIS_Role_User ru JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role AND rf.Flag_Aktif = 'Y'
            WHERE ru.UserId_Web = p.Id_User AND ru.Flag_Aktif = 'Y'
              AND UPPER(rf.Role_Code) IN ('ESS_MENU_ADMINISTRATOR','ESS_APPROVAL_ADMINISTRATOR','ESS_MEMBERSHIP_ADMINISTRATOR',
                'ESS_MENU_APPROVAL_ADMINISTRATOR','ESS_MENU_ADMINISTRATOR_PHL','ESS_APPROVAL_ADMINISTRATOR_PHL','KPI_SUPER_ADMIN',
                'KPI_ADMIN_PHL','KPI_MENU_ADMINISTRATOR','KPI_APPROVAL_ADMINISTRATOR','KPI_MEMBERSHIP_ADMINISTRATOR',
                'KPI_MENU_APPROVAL_ADMINISTRATOR','LMS_MENU_ADMINISTRATOR','LMS_APPROVAL_ADMINISTRATOR',
                'LMS_MENU_APPROVAL_ADMINISTRATOR','VISITOR_MENU_ADMINISTRATOR','VISITOR_APPROVAL_ADMINISTRATOR',
                'VISITOR_MENU_APPROVAL_ADMINISTRATOR')
       ) THEN 'Y' ELSE 'T' END AS Punya_Role_Admin_Sah,
```

Baris dengan `Kategori='TATA_KELOLA'`, `Hanya_Lewat_Hibah='Y'`, `Punya_Role_Admin_Sah='T'` = user yang akan kehilangan halaman itu setelah rilis.

- [ ] **Step 3: Jalankan laporan di dev** dan pastikan daftar "akan kehilangan" = delapan user temuan minus yang punya role admin sah (Aji #622, Haris #644 tetap).

Run: `php /tmp/laporan.php` (atau ulangi pemanggilan DB::select atas berkas SQL)
Expected: Andre (#662), Cahya (#630), Dinda (#635), Iqbal (#648), Tari (#642) muncul sebagai "akan kehilangan". Aji (#622), Haris (#644), dan Dhea (#600, memegang `ESS_Menu_Administrator_PHL` — dicocokkan UPPER) tetap.

- [ ] **Step 4: Verifikasi menyeluruh**

Run:
```bash
php vendor/bin/phpunit tests/Feature/SYSTEM/Governance
php vendor/bin/phpunit tests/Feature/SYSTEM/RuteTulisSelaluBergerbangTest.php
php vendor/bin/phpunit tests/Feature/ESS/Employee/AdminPengumpulanDataHalamanTest.php
php scripts/verify-references.php
vendor/bin/pint --test app config routes tests/Feature/SYSTEM
```
Expected: semua OK (kegagalan yang sudah ada di master dicatat, bukan dikejar).

Browser dev (headless, sesi via tinker seperti pola sebelumnya): login sebagai Cahya (#630, Leader) → `/page-access` 403 dan menu "Page Access Setup" hilang; sebagai Aji (#622) → tetap bisa; sebagai Ridho (#4) → tetap bisa. Hapus berkas sesi sesudahnya.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md database/runbook/2026-10-09-rbac-fase0-laporan-dampak.sql
git commit -m "docs(system): selaraskan spec & laporan dampak Fase 0 dengan config

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

## Sebelum rilis ke prod (tugas user, bukan agen)

1. Jalankan `database/runbook/2026-10-09-rbac-fase0-laporan-dampak.sql` di prod.
2. Untuk setiap baris "akan kehilangan" yang ternyata admin sah: beri grant langsung (Page Access Setup oleh System Admin) atau tambahkan role-nya ke `authorization.tata_kelola.peran_admin`.
3. Baru deploy.
