# RBAC Fase 1 — Titik Keputusan & Audit Akses Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Satu layanan `KeputusanAkses` menjadi sumber keputusan halaman untuk gerbang route (`check.jabatan`) dan sidebar, sehingga keduanya tidak berselisih lagi, dan setiap perubahan role/akses tercatat sebelum→sesudah di tabel `N_HRIS_Akses_Audit`.

**Architecture:** Mode LAMA saja (belum ada tabel mode). `KeputusanAkses::boleh()` = System Admin **atau** `User::memilikiJabatan()` (yang sudah memuat penyaring Fase 0). `CheckJabatan` dan `InertiaShellService::menuIsVisible` memanggilnya; `memilikiJabatan` sendiri tidak diubah supaya pemanggil lain (Gate `akses-spesial`, `isSupervisor`, controller Visitor) tidak berubah perilaku. Audit lewat `AuditAkses` ke tabel baru (DDL-2026-164), toleran bila tabel belum ada; `AuthorizationAuditLogger::logMutation`, Page Access Setup, dan simpan role user menulis ke sana.

**Tech Stack:** Laravel 10, SQL Server (`Web_HRIS` dev), PHPUnit 10 (`php vendor/bin/phpunit <file>`).

**Spec:** `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (bagian 3, 4.1, 5, 8 — Fase 1)

## Global Constraints

- Branch `feat/system/rbac-fase1` (dari `feat/system/rbac-fase0`); **commit lokal saja, tanpa push/PR** sampai user minta.
- DDL hanya aditif; dijalankan di dev **setelah user menyetujui**; prod lewat SQL Tracker oleh user.
- Kode toleran DDL belum jalan: tanpa tabel audit, tidak ada galat (jatuh ke log file seperti sekarang).
- Tidak ada user yang kehilangan akses. Perubahan yang disengaja (disetujui user 9 Okt 2026): sidebar mengikuti gerbang; System Admin boleh semua halaman.
- `User::memilikiJabatan` tidak diubah.
- Mutasi DB di test hanya di dev `Web_HRIS`, di dalam `DatabaseTransactions`.
- Commit diakhiri `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Rulings terhadap spec

- Kolom tambahan `Target_Id_User` di `N_HRIS_Akses_Audit` (tidak ada di spec 4.1) — supaya riwayat per user bisa dicari dengan indeks. Spec diperbarui di Task 6.
- `KeputusanAkses::cakupan()` dari spec bagian 5 **tidak** dibuat di Fase 1 — cakupan baru ada di Fase 2 (YAGNI).
- Simpan role user (`saveUserRoles`) dibersihkan cache aksesnya (`AuthorizationScopeResolver::clearCache`) — tanpa ini sidebar target basi sampai cache lintas-request kedaluwarsa (60 detik). Biaya bila salah: tidak ada.

## Review Focus

1. Halaman dengan banyak alternatif di `check.jabatan:a,b,c` → lolos bila salah satu boleh (Task 3, `test_gerbang_lolos_bila_salah_satu_alternatif_boleh`).
2. System Admin pada halaman tata kelola & `systemDatabaseChangeDashboardPage` → tetap boleh (Task 3 & 4).
3. User tanpa role & tanpa grant → sidebar dan gerbang sama-sama hanya menu global (Task 4, `test_sidebar_sama_dengan_gerbang_untuk_semua_pemegang_role`).
4. Tabel audit belum ada (prod sebelum DDL) → simpan akses tetap berhasil (Task 2, `test_tanpa_tabel_tidak_gagal`).
5. Nilai sebelum/sesudah besar (ratusan role/halaman) → tersimpan utuh (nvarchar(max)) (Task 2, `test_nilai_besar_tersimpan_utuh`).

---

### Task 1: DDL-2026-164 `N_HRIS_Akses_Audit`

**Files:**
- Create: `database/ddl/DDL-2026-164-hris-akses-audit/forward.sql`
- Create: `database/ddl/DDL-2026-164-hris-akses-audit/drop.sql`
- Create: `docs/changes/DDL-2026-164-hris-akses-audit/README.md`
- Modify: `database/ddl/status.json` (tambah satu entri; indent 4, gabungkan bila ada konflik — lihat memori konflik ledger)

- [ ] **Step 1: Pastikan nomor belum dipakai**

Run: `ls database/ddl | grep -c 'DDL-2026-164'`
Expected: `0` (bila sudah ada, pakai nomor bebas berikutnya dan catat Ruling).

- [ ] **Step 2: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-164-hris-akses-audit */
/* RBAC Fase 1: jejak perubahan role/akses (sebelum -> sesudah). Aditif. */
IF OBJECT_ID('dbo.N_HRIS_Akses_Audit', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Akses_Audit
    (
        Id_Akses_Audit bigint IDENTITY(1,1) NOT NULL,
        Waktu datetime2 NOT NULL CONSTRAINT DF_HRIS_Akses_Audit_Waktu DEFAULT (sysdatetime()),
        Oleh_Id_User int NULL,
        Target_Id_User int NULL,
        Subjek_Jenis varchar(20) NOT NULL,
        Subjek_Id varchar(100) NULL,
        Aksi varchar(50) NOT NULL,
        Nilai_Sebelum nvarchar(max) NULL,
        Nilai_Sesudah nvarchar(max) NULL,
        Alasan nvarchar(500) NULL,
        Sumber varchar(30) NOT NULL CONSTRAINT DF_HRIS_Akses_Audit_Sumber DEFAULT ('UI'),
        CONSTRAINT PK_HRIS_Akses_Audit PRIMARY KEY (Id_Akses_Audit)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Akses_Audit_Target' AND object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit'))
    CREATE INDEX IX_HRIS_Akses_Audit_Target ON dbo.N_HRIS_Akses_Audit (Target_Id_User, Waktu);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Akses_Audit_Waktu' AND object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit'))
    CREATE INDEX IX_HRIS_Akses_Audit_Waktu ON dbo.N_HRIS_Akses_Audit (Waktu);
GO
```

- [ ] **Step 3: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-164-hris-akses-audit
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus seluruh jejak audit akses.
IF OBJECT_ID('dbo.N_HRIS_Akses_Audit', 'U') IS NOT NULL
    DROP TABLE dbo.N_HRIS_Akses_Audit;
GO
```

- [ ] **Step 4: README + status.json** — README berisi tujuan, objek terdampak, cara verifikasi (`SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit')` → 11), urutan rilis ("boleh sebelum atau sesudah kode Fase 1; kode toleran"). Entri status.json meniru bentuk DDL-2026-158 dengan `"status": "REVIEW_REQUIRED"`, `development.status` `"PENDING"`, checksum sha256 `forward.sql` di `catatan`. Gunakan Python `json.dump(..., indent=4, ensure_ascii=False)` setelah membaca berkas asli.

- [ ] **Step 5: GERBANG USER — minta persetujuan menjalankan DDL di dev.** Berhenti dan tanyakan. Setelah disetujui: jalankan per batch (pisah `GO`) lewat `DB::unprepared` dengan pengaman `DB_NAME() = 'Web_HRIS'`, jalankan dua kali (idempoten), verifikasi 11 kolom + PK + 2 indeks, ubah status.json → `DEV_EXECUTED`, `development.status` `DONE` + hasil.

- [ ] **Step 6: Commit**

```bash
git add database/ddl/DDL-2026-164-hris-akses-audit docs/changes/DDL-2026-164-hris-akses-audit database/ddl/status.json
git commit -m "feat(system): DDL-2026-164 tabel N_HRIS_Akses_Audit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2: `AuditAkses` + `logMutation` menulis ke DB

**Files:**
- Create: `app/Services/SYSTEM/Governance/Authorization/Akses/AuditAkses.php`
- Modify: `app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationAuditLogger.php` (`logMutation`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTest.php`

**Interfaces:**
- Produces: `AuditAkses::catat(string $subjekJenis, ?string $subjekId, string $aksi, mixed $sebelum, mixed $sesudah, ?int $targetIdUser = null, ?string $alasan = null, string $sumber = 'UI'): bool` — `true` bila tertulis ke tabel, `false` bila tabel belum ada (tidak melempar). `$sebelum/$sesudah` di-JSON-kan (`JSON_UNESCAPED_UNICODE`) kecuali sudah string/null. `Oleh_Id_User` dari `Auth::id()`.
- Konstanta: `AuditAkses::TABEL = 'N_HRIS_Akses_Audit'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationAuditLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** RBAC Fase 1: jejak perubahan akses ke N_HRIS_Akses_Audit. */
class AuditAksesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! SkemaTersedia::tabel(AuditAkses::TABEL)) {
            $this->markTestSkipped('DDL-2026-164 belum dijalankan di database ini.');
        }
    }

    private function terakhir(): ?object
    {
        return DB::table(AuditAkses::TABEL)->orderByDesc('Id_Akses_Audit')->first();
    }

    public function test_mencatat_sebelum_dan_sesudah(): void
    {
        $ok = app(AuditAkses::class)->catat('PENUGASAN', '12', 'UBAH_ROLE', ['KPI_ADMIN'], ['KPI_ADMIN', 'ESS_MENU_ADMINISTRATOR'], 630, 'uji', 'UI');

        $this->assertTrue($ok);
        $b = $this->terakhir();
        $this->assertSame('PENUGASAN', $b->Subjek_Jenis);
        $this->assertSame(630, (int) $b->Target_Id_User);
        $this->assertSame(['KPI_ADMIN'], json_decode($b->Nilai_Sebelum, true));
        $this->assertSame(['KPI_ADMIN', 'ESS_MENU_ADMINISTRATOR'], json_decode($b->Nilai_Sesudah, true));
    }

    public function test_nilai_besar_tersimpan_utuh(): void
    {
        $besar = array_map(fn ($i) => 'HALAMAN_'.$i, range(1, 400));

        app(AuditAkses::class)->catat('GRANT', null, 'BERI', [], $besar, 630);

        $this->assertCount(400, json_decode($this->terakhir()->Nilai_Sesudah, true));
    }

    public function test_log_mutation_ikut_tercatat_di_tabel(): void
    {
        AuthorizationAuditLogger::logMutation([
            'event' => 'menu-binding.save', 'actor_user_id' => 4, 'target_user_id' => 630,
            'jenis_page' => 'IzinPageAdmin', 'old_value' => 'T', 'new_value' => 'Y', 'source' => 'uji',
        ]);

        $b = $this->terakhir();
        $this->assertSame('menu-binding.save', $b->Aksi);
        $this->assertSame(630, (int) $b->Target_Id_User);
        $this->assertSame('uji', $b->Sumber);
    }
}
```

Dan uji toleransi tanpa tabel, di berkas yang sama **di luar** `setUp` skip — buat kelas kedua `tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTanpaTabelTest.php`:

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use Tests\TestCase;

class AuditAksesTanpaTabelTest extends TestCase
{
    public function test_tanpa_tabel_tidak_gagal(): void
    {
        $audit = new class extends AuditAkses
        {
            protected function tabelAda(): bool
            {
                return false;
            }
        };

        $this->assertFalse($audit->catat('GRANT', null, 'BERI', [], ['x'], 1));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTest.php tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTanpaTabelTest.php`
Expected: `Class "App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses" not found`.

- [ ] **Step 3: Implementasi `AuditAkses`**

```php
<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Jejak perubahan role/akses (RBAC Fase 1, DDL-2026-164). Toleran tabel belum
 * ada: mengembalikan false tanpa melempar, supaya kode bisa rilis sebelum DDL.
 */
class AuditAkses
{
    public const TABEL = 'N_HRIS_Akses_Audit';

    public function catat(
        string $subjekJenis,
        ?string $subjekId,
        string $aksi,
        mixed $sebelum,
        mixed $sesudah,
        ?int $targetIdUser = null,
        ?string $alasan = null,
        string $sumber = 'UI',
    ): bool {
        if (! $this->tabelAda()) {
            return false;
        }

        DB::table(self::TABEL)->insert([
            'Waktu' => now(),
            'Oleh_Id_User' => Auth::id(),
            'Target_Id_User' => $targetIdUser,
            'Subjek_Jenis' => mb_substr($subjekJenis, 0, 20),
            'Subjek_Id' => $subjekId !== null ? mb_substr($subjekId, 0, 100) : null,
            'Aksi' => mb_substr($aksi, 0, 50),
            'Nilai_Sebelum' => $this->teks($sebelum),
            'Nilai_Sesudah' => $this->teks($sesudah),
            'Alasan' => $alasan !== null ? mb_substr($alasan, 0, 500) : null,
            'Sumber' => mb_substr($sumber, 0, 30),
        ]);

        return true;
    }

    protected function tabelAda(): bool
    {
        return SkemaTersedia::tabel(self::TABEL);
    }

    private function teks(mixed $nilai): ?string
    {
        if ($nilai === null || is_string($nilai)) {
            return $nilai;
        }

        return json_encode($nilai, JSON_UNESCAPED_UNICODE);
    }
}
```

- [ ] **Step 4: `logMutation` ikut menulis** — di `AuthorizationAuditLogger::logMutation`, setelah blok `Log::channel(...)->info(...)`, tambahkan:

```php
        // RBAC Fase 1: mutasi juga masuk tabel audit bila sudah ada (DDL-2026-164).
        try {
            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'KATALOG',
                isset($payload['jenis_page']) ? (string) $payload['jenis_page'] : null,
                (string) ($payload['event'] ?? 'authorization.mutation'),
                $payload['old_value'] ?? null,
                $payload['new_value'] ?? null,
                isset($payload['target_user_id']) ? (int) $payload['target_user_id'] : null,
                null,
                (string) ($payload['source'] ?? 'authorization-governance'),
            );
        } catch (\Throwable $e) {
            report($e);
        }
```

- [ ] **Step 5: Run test to verify it passes**

Run: perintah Step 2.
Expected: `OK` (4 tests). Bila DDL belum dijalankan di dev, 3 uji `AuditAksesTest` SKIP — Task 1 Step 5 harus selesai lebih dulu.

- [ ] **Step 6: Commit**

```bash
git add app/Services/SYSTEM/Governance/Authorization/Akses/AuditAkses.php app/Services/SYSTEM/Governance/Authorization/Shared/AuthorizationAuditLogger.php tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTest.php tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTanpaTabelTest.php
git commit -m "feat(system): AuditAkses — jejak perubahan akses ke N_HRIS_Akses_Audit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3: `KeputusanAkses` + gerbang route

**Files:**
- Create: `app/Services/SYSTEM/Governance/Authorization/Akses/KeputusanAkses.php`
- Modify: `app/Http/Middleware/CheckJabatan.php:25` (`memilikiJabatan` → `KeputusanAkses::boleh`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/KeputusanAksesTest.php`

**Interfaces:**
- Produces:
  - `KeputusanAkses::boleh(User $u, string $halaman, string $aksi = 'VIEW'): bool` — mode LAMA: `apakahSystemAdmin($u) || $u->memilikiJabatan($halaman)`; `$aksi` belum dievaluasi (dicatat di docblock).
  - `KeputusanAkses::apakahSystemAdmin(User $u): bool` — `($u->Is_System_Admin ?? null) === 'Y'`.
  - `KeputusanAkses::telusuri(User $u, string $halaman): array{boleh: bool, jalur: list<string>}` — jalur dari: `SYSTEM_ADMIN`, `GRANT_LANGSUNG`, `MENU_GLOBAL`, `HIBAH_ROLE:<Role_Code>` (hanya role yang lolos `HibahTataKelola::saringHibah`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Http\Middleware\CheckJabatan;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** RBAC Fase 1: satu titik keputusan halaman (mode LAMA). */
class KeputusanAksesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $c = new \ReflectionProperty(User::class, 'menuAccessCache');
        $c->setAccessible(true);
        $c->setValue(null, []);
    }

    private function userBiasa(): User
    {
        $id = DB::table('Karyawan as k')->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->orderBy('u.Id_Users')->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function lewatGerbang(User $u, string ...$halaman): bool
    {
        Auth::setUser($u);
        $req = Request::create('/x');
        $req->setUserResolver(fn () => $u);
        try {
            (new CheckJabatan)->handle($req, fn () => response('ok'), ...$halaman);

            return true;
        } catch (HttpException $e) {
            return false;
        }
    }

    public function test_sama_dengan_memiliki_jabatan_untuk_user_biasa(): void
    {
        $u = $this->userBiasa();
        $k = app(KeputusanAkses::class);

        foreach (DB::table('N_HRIS_Menus')->pluck('Jenis_page') as $p) {
            $this->assertSame($u->memilikiJabatan($p), $k->boleh($u, $p), "Beda untuk {$p}.");
        }
    }

    public function test_system_admin_boleh_semua_halaman(): void
    {
        $u = $this->userBiasa();
        DB::table('KPI_Users')->where('Id_Users', $u->Id_Users)->update(['Is_System_Admin' => 'Y']);
        $u = User::where('Id_Users', $u->Id_Users)->firstOrFail();

        $this->assertTrue(app(KeputusanAkses::class)->boleh($u, 'systemAuthManagerPage'));
        $this->assertTrue($this->lewatGerbang($u, 'kpiEnforcementMonitoring'));
    }

    public function test_gerbang_menolak_user_tanpa_akses(): void
    {
        $this->assertFalse($this->lewatGerbang($this->userBiasa(), 'systemAuthManagerPage'));
    }

    public function test_gerbang_lolos_bila_salah_satu_alternatif_boleh(): void
    {
        $u = $this->userBiasa();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);

        $this->assertTrue($this->lewatGerbang($u, 'systemAuthManagerPage', ' IzinPageAdmin'));
    }

    public function test_telusuri_menyebut_jalurnya(): void
    {
        $u = $this->userBiasa();
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001', 'Jenis_page' => 'IzinPageAdmin', 'UserID_Web' => $u->Id_Users,
            'ID_Divisi' => 0, 'ID_Level' => 0, 'Flag_Aktif' => 'Y',
        ]);

        $t = app(KeputusanAkses::class)->telusuri($u, 'IzinPageAdmin');

        $this->assertTrue($t['boleh']);
        $this->assertContains('GRANT_LANGSUNG', $t['jalur']);
        $this->assertSame(['boleh' => false, 'jalur' => []], app(KeputusanAkses::class)->telusuri($u, 'systemAuthManagerPage'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/KeputusanAksesTest.php`
Expected: `Class "...\Akses\KeputusanAkses" not found`.

- [ ] **Step 3: Implementasi**

```php
<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\DB;

/**
 * Satu titik keputusan akses halaman (RBAC Fase 1, mode LAMA).
 *
 * Dipakai gerbang route (CheckJabatan) DAN sidebar (InertiaShellService) —
 * keduanya wajib bertanya ke sini supaya menu yang tampil = halaman yang bisa
 * dibuka. Aturan: System Admin, atau User::memilikiJabatan (grant langsung,
 * hibah role tersaring Fase 0, menu global). Argumen $aksi belum dievaluasi di
 * mode LAMA; izin per aksi datang di Fase 2.
 */
class KeputusanAkses
{
    public function apakahSystemAdmin(User $u): bool
    {
        return ($u->Is_System_Admin ?? null) === 'Y';
    }

    public function boleh(User $u, string $halaman, string $aksi = 'VIEW'): bool
    {
        return $this->apakahSystemAdmin($u) || $u->memilikiJabatan($halaman);
    }

    /** @return array{boleh: bool, jalur: list<string>} */
    public function telusuri(User $u, string $halaman): array
    {
        $kunci = HibahTataKelola::normalisasi($halaman);
        $normal = "LOWER(REPLACE(LTRIM(RTRIM(%s)), ' ', ''))";
        $jalur = [];

        if ($this->apakahSystemAdmin($u)) {
            $jalur[] = 'SYSTEM_ADMIN';
        }
        if (DB::table('HRIS_Page_Access')->where('UserID_Web', $u->Id_Users)->where('Flag_Aktif', 'Y')
            ->whereRaw(sprintf($normal, 'Jenis_page').' = ?', [$kunci])->exists()) {
            $jalur[] = 'GRANT_LANGSUNG';
        }
        if (DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')
            ->whereRaw(sprintf($normal, 'Jenis_page').' = ?', [$kunci])->exists()) {
            $jalur[] = 'MENU_GLOBAL';
        }
        $peran = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Grup_Fitur_Map as gfm', function ($j) {
                $j->on('gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')->where('gfm.Flag_Hibah_Role', '=', 'Y');
            })
            ->where('ru.UserId_Web', $u->Id_Users)
            ->where('ru.Flag_Aktif', 'Y')
            ->whereRaw(sprintf($normal, 'gfm.Jenis_page').' = ?', [$kunci])
            ->tap(fn ($q) => HibahTataKelola::saringHibah($q, 'gfm.Jenis_page', 'rf.Role_Code'))
            ->distinct()
            ->pluck('rf.Role_Code');
        foreach ($peran as $kode) {
            $jalur[] = 'HIBAH_ROLE:'.$kode;
        }

        return ['boleh' => $jalur !== [], 'jalur' => $jalur];
    }
}
```

Di `CheckJabatan::handle`, ganti `if ($request->user()->memilikiJabatan($jabatan)) {` menjadi:

```php
            if (app(\App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses::class)->boleh($request->user(), $jabatan)) {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/KeputusanAksesTest.php tests/Feature/SYSTEM/Governance/Authorization/HibahTataKelolaTest.php tests/Feature/SYSTEM/Governance/Authorization/RuteLamaSystemAdminTest.php`
Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SYSTEM/Governance/Authorization/Akses/KeputusanAkses.php app/Http/Middleware/CheckJabatan.php tests/Feature/SYSTEM/Governance/Authorization/KeputusanAksesTest.php
git commit -m "feat(system): KeputusanAkses sebagai gerbang check.jabatan (System Admin boleh semua)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4: Sidebar memakai `KeputusanAkses`

**Files:**
- Modify: `app/Services/SYSTEM/Application/Shell/InertiaShellService.php:539-557` (`menuIsVisible`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/SidebarSamaGerbangTest.php`

**Interfaces:**
- Consumes: `KeputusanAkses::boleh`, `KeputusanAkses::apakahSystemAdmin` (Task 3)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Shell\InertiaShellService;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC Fase 1: menu yang tampil = halaman yang lolos gerbang. Diukur 9 Okt
 * 2026 di dev: 4 user bisa membuka 37 halaman yang tak tampil, System Admin
 * melihat 12 menu yang ditolak gerbang.
 */
class SidebarSamaGerbangTest extends TestCase
{
    public function test_sidebar_sama_dengan_gerbang_untuk_semua_pemegang_role(): void
    {
        $shell = app(InertiaShellService::class);
        $tampil = new \ReflectionMethod($shell, 'menuIsVisible');
        $tampil->setAccessible(true);
        $k = app(KeputusanAkses::class);

        $menus = DB::table('N_HRIS_Menus')->get(['Jenis_page', 'Is_Global_Menu']);
        $ids = DB::table('N_HRIS_Role_User')->where('Flag_Aktif', 'Y')->pluck('UserId_Web')
            ->merge(DB::table('KPI_Users')->where('Is_System_Admin', 'Y')->pluck('Id_Users'))->unique();

        $selisih = [];
        foreach ($ids as $id) {
            $u = User::where('Id_Users', $id)->first();
            if (! $u) {
                continue;
            }
            foreach ($menus as $m) {
                if ($m->Jenis_page === 'systemDatabaseChangeDashboardPage') {
                    continue;
                }
                $boleh = $k->boleh($u, (string) $m->Jenis_page);
                if ($tampil->invoke($shell, $m, $u) !== $boleh) {
                    $selisih[] = "#{$id} {$m->Jenis_page} (gerbang=".($boleh ? 'ya' : 'tidak').')';
                }
            }
        }

        $this->assertSame([], $selisih, "Sidebar berselisih dengan gerbang:\n".implode("\n", array_slice($selisih, 0, 30)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/SidebarSamaGerbangTest.php`
Expected: FAIL dengan daftar selisih (±49 baris: 37 gerbang-saja + 12 sidebar-saja System Admin).

- [ ] **Step 3: Implementasi** — di `menuIsVisible`, ganti baris terakhir `return AuthorizationScopeResolver::canAccessPage($user, (string) $row->Jenis_page);` menjadi:

```php
        // RBAC Fase 1: sumber yang sama dengan gerbang route (CheckJabatan).
        return app(KeputusanAkses::class)->boleh($user, (string) $row->Jenis_page);
```

Tambah `use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;`. Cabang `systemDatabaseChangeDashboardPage` dan menu global tidak diubah.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/SidebarSamaGerbangTest.php tests/Feature/SYSTEM/Governance`
Expected: OK. Bila `HibahTataKelolaTest::test_sidebar_*` (Fase 0, memakai `canAccessPage`) masih lulus, biarkan; bila gagal karena resolver tak lagi dipakai sidebar, ubah uji itu agar memanggil `menuIsVisible` dan catat Ruling.

- [ ] **Step 5: Commit**

```bash
git add app/Services/SYSTEM/Application/Shell/InertiaShellService.php tests/Feature/SYSTEM/Governance/Authorization/SidebarSamaGerbangTest.php
git commit -m "feat(system): sidebar memakai KeputusanAkses — menu tampil = halaman yang bisa dibuka

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5: Audit simpan role user & Page Access Setup

**Files:**
- Modify: `app/Http/Controllers/SYSTEM/Governance/Authorization/AdminRoleManagerController.php:576-631` (`saveUserRoles`)
- Modify: `app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php` (setelah `PageAccessAudit::catat`)
- Test: `tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTest.php` (tambah uji)

**Interfaces:**
- Consumes: `AuditAkses::catat` (Task 2)

- [ ] **Step 1: Write the failing test** — tambahkan ke `AuditAksesTest`:

```php
    public function test_simpan_role_user_tercatat(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::where('Id_Users', 4)->firstOrFail();
        $target = (int) DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
            ->where('UserID_Web', '<>', 4)->orderBy('UserID_Web')->value('UserID_Web');
        $role = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'HCIS_LEADER')->value('Id_Role');
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/role-manager/user-roles/{$target}", ['roleIds' => [$role]])
            ->assertOk();

        $b = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Subjek_Jenis', 'PENUGASAN')->first();
        $this->assertNotNull($b, 'Simpan role user tidak tercatat.');
        $this->assertSame($target, (int) $b->Target_Id_User);
        $this->assertContains('HCIS_LEADER', json_decode($b->Nilai_Sesudah, true));
    }
```

Untuk uji kedua, **pindahkan** helper `adminDanHalaman()` dan `karyawanTanpaBaris()` dari `PageAccessUpdateTest` ke trait baru `tests/Feature/SYSTEM/Governance/Authorization/FixturePageAccess.php` (`trait FixturePageAccess`, metode `protected`), pakai trait itu di kedua kelas, dan tulis uji kedua sebagai:

```php
    public function test_page_access_tercatat_ringkas(): void
    {
        [$admin, $hal] = $this->adminDanHalaman();
        $target = $this->karyawanTanpaBaris($hal->Jenis_page, (int) $admin->Id_Users);
        $awal = (int) DB::table(AuditAkses::TABEL)->max('Id_Akses_Audit');

        $this->actingAs($admin)->postJson('/api/v1/page-access/update-access',
            ['all' => false, 'employeeIds' => [$target], 'pageIds' => [$hal->id], 'action' => 'grant'])->assertOk();

        $b = DB::table(AuditAkses::TABEL)->where('Id_Akses_Audit', '>', $awal)->where('Subjek_Jenis', 'GRANT')->first();
        $this->assertNotNull($b, 'Page Access Setup tidak tercatat di N_HRIS_Akses_Audit.');
        $this->assertSame('grant', $b->Aksi);
    }
```

(`AuditAksesTest` juga perlu `config(['app.url' => 'http://localhost']); URL::forceRootUrl('http://localhost');` di `setUp`, seperti `PageAccessUpdateTest`.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance/Authorization/AuditAksesTest.php`
Expected: dua uji baru FAIL ("tidak tercatat").

- [ ] **Step 3: Implementasi**

(a) `saveUserRoles`: sebelum `DB::table('N_HRIS_Role_User')->where('UserId_Web', $userId)->whereIn('Id_Role', $oldRoleIds)...update(...)`, ambil:

```php
            $kodeSebelum = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.UserId_Web', $userId)->where('ru.Flag_Aktif', 'Y')
                ->orderBy('rf.Role_Code')->pluck('rf.Role_Code')->all();
```

sebelum `DB::commit();`:

```php
            $kodeSesudah = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.UserId_Web', $userId)->where('ru.Flag_Aktif', 'Y')
                ->orderBy('rf.Role_Code')->pluck('rf.Role_Code')->all();
            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'PENUGASAN', (string) $userId, 'UBAH_ROLE', $kodeSebelum, $kodeSesudah, (int) $userId,
            );
```

dan setelah `DB::commit();`:

```php
            $target = \App\Models\ESS\Employee\Models\User::where('Id_Users', $userId)->first();
            if ($target) {
                \App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver::clearCache($target);
            }
```

(b) `PageAccessController::updateAccess`, tepat setelah pemanggilan `PageAccessAudit::catat(...)`:

```php
            app(AuditAkses::class)->catat(
                'GRANT',
                implode(',', $jenisHalaman),
                $validated['action'],
                ['jumlah_karyawan' => count($validated['employeeIds'])],
                ['halaman' => $jenisHalaman, 'karyawan' => array_values($validated['employeeIds'])],
            );
```

dengan `use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit tests/Feature/SYSTEM/Governance`
Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SYSTEM/Governance/Authorization/AdminRoleManagerController.php app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php tests/Feature/SYSTEM/Governance/Authorization
git commit -m "feat(system): audit simpan role user & Page Access ke N_HRIS_Akses_Audit

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6: Selaraskan spec & verifikasi menyeluruh

**Files:**
- Modify: `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` (4.1: kolom `Target_Id_User`; 5: `cakupan()` di Fase 2; 8: Fase 1 selesai dengan keputusan "sidebar ikut gerbang + System Admin boleh semua")

- [ ] **Step 1: Perbarui spec** sesuai daftar di atas.

- [ ] **Step 2: Verifikasi**

```bash
php vendor/bin/phpunit tests/Feature/SYSTEM/Governance
php vendor/bin/phpunit tests/Feature/SYSTEM/RuteTulisSelaluBergerbangTest.php tests/Feature/SYSTEM/HakAksesHalamanMassalTest.php tests/Feature/ESS/Employee/AdminPengumpulanDataHalamanTest.php
php vendor/bin/phpunit tests/Unit/SYSTEM
php scripts/verify-references.php
vendor/bin/pint --test app/Services/SYSTEM app/Http/Middleware app/Http/Controllers/SYSTEM tests/Feature/SYSTEM/Governance
```

Expected: OK (kegagalan yang identik di master dicatat, bukan dikejar).

- [ ] **Step 3: Browser dev** — sesi via tinker untuk #642 (Tari), #4 (System Admin), #630 (Leader): ukur ulang selisih sidebar-vs-gerbang dengan skrip `/tmp/selisih.php` (harus 0/0); Tari melihat menu tambahan yang memang bisa ia buka; System Admin bisa membuka 12 menu yang dulu 403; Leader tetap tanpa menu tata kelola. Hapus berkas sesi sesudahnya.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md
git commit -m "docs(system): spec RBAC — catatan Fase 1

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
