# Otomasi SOP Multi-Paket — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB — gunakan `superpowers:subagent-driven-development` (disarankan) atau `superpowers:executing-plans` untuk mengerjakan rencana ini task demi task. Langkah memakai sintaks kotak centang (`- [ ]`).

**Tujuan:** Membuat satu aturan penugasan otomatis SOP dapat menampung banyak paket sekaligus, dengan tenggat induk yang boleh di-override per paket.

**Arsitektur:** Relasi aturan↔paket dipindah dari kolom `N_SOP_Assignment_Rule.Id_SOP_Paket` ke tabel jembatan `N_SOP_Assignment_Rule_Paket`, sehingga penjaga "satu paket satu aturan" ditegakkan filtered unique index alih-alih `if` di PHP. Mesin apply berubah menjadi loop per paket dengan tiap paket sebagai satuan kegagalan yang berdiri sendiri; riwayat eksekusi menjadi satu baris induk plus satu baris anak per paket.

**Tumpukan teknologi:** Laravel 11 (Query Builder, tanpa Eloquent di modul SOP), SQL Server (Web_HRIS), Vue 3 Options API + kit Hcis, PHPUnit (`DatabaseTransactions`), Vitest.

**Spec:** `plans/sop/2026-08-27-otomasi-multi-paket-design.md`

## Batasan Global

Berlaku untuk **setiap** task di bawah ini.

- **Jangan jalankan uji sampai ACC.** Kesepakatan sesi yang berdiri: tulis kode dan berkas uji sampai selesai, tetapi `php artisan test`, `npx vitest`, dan `npm run build` **tidak dijalankan** sampai pengguna memberi ACC. Alasannya `artisan test` menembak basis data dev dan mengunci UI pengguna. Tiap task tetap menuliskan perintah ujinya; eksekusinya dikumpulkan di **Task 9**. Yang boleh dijalankan selama penulisan hanya `php -l` dan `node --check`.
- **Basis data dev:** `Web_HRIS`. Jangan pernah menjalankan DDL ke production.
- **DROP dilarang dieksekusi agent.** Statement drop boleh ditulis di `drop.sql`, tidak boleh dijalankan. ALTER yang membuang data wajib berhenti dan bertanya.
- **Sebelum menjalankan DDL apa pun:** periksa objek sasarannya dan tampilkan SQL persisnya lebih dulu.
- **Status konstanta** diambil dari `App\Services\LMS\SOP\Support\SopStatus` — jangan hardcode string status.
- **Tanpa Eloquent.** Modul SOP memakai `DB::table(...)` sepenuhnya. Ikuti.
- **Bahasa komentar & pesan pengguna: Indonesia.** Komentar menjelaskan *mengapa*, bukan *apa*.
- **Nilai boolean di basis data** memakai `char(1)` `'Y'`/`'T'`, bukan bit.
- **Setiap pembatasan wajib dilaporkan ke pengguna.** Plafon, potongan, dan sampling tidak boleh senyap.
- **Penjaga baru wajib punya uji yang menuntut PENOLAKAN**, dan penjaga itu harus pernah terlihat MERAH sebelum implementasinya ditulis.

---

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql` | Tabel jembatan, dua index, dua kolom riwayat, satu ALTER nullable |
| `database/ddl/DDL-2026-053-sop-aturan-multi-paket/drop.sql` | Pembersihan manual — tidak dijalankan agent |
| `app/Services/LMS/SOP/SopAturanPaketService.php` | **Baru.** Baca/tulis/sinkron daftar paket sebuah aturan, penjaga klaim, jadwal efektif per paket |
| `app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php` | Ekstraksi `normalizeSchedule()`, hash menyerap snapshot paket |
| `app/Services/LMS/SOP/SopAssignmentRuleService.php` | `saveDraft` bertanda tangan baru, `applyQueued` jadi loop per paket, `impact()` O(1) |
| `app/Services/LMS/SOP/SopAutomationRunService.php` | Baris induk/anak, join paket langsung, plafon dilaporkan |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php` | Validasi `paket[]`, plafon selaras |
| `app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php` | `failed()` menandai baris paket yang menggantung |
| `resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.vue` | **Baru.** Daftar paket + switch override + setelan mini |
| `resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPratinjau.vue` | **Baru.** Tabel dampak per paket + baris TOTAL |
| `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.vue` | Merakit dua komponen di atas, riwayat induk/anak, remah dibersihkan |

Dua komponen Vue baru sengaja dipisah: `SopKebijakan.vue` sudah 1.455 baris, dan bagian yang paling banyak bertambah justru dua bagian itu.

---

## Task 1: DDL-2026-053 — bentuk data

**Berkas:**
- Buat: `database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql`
- Buat: `database/ddl/DDL-2026-053-sop-aturan-multi-paket/drop.sql`
- Buat: `docs/changes/DDL-2026-053-sop-aturan-multi-paket/README.md`
- Ubah: `database/ddl/status.json`

**Antarmuka:**
- Menghasilkan: tabel `N_SOP_Assignment_Rule_Paket`; kolom `N_SOP_Automation_Run.Id_SOP_Paket` dan `.Id_Run_Induk`; `N_SOP_Assignment_Rule.Id_SOP_Paket` menjadi nullable. Seluruh task berikutnya bergantung pada ini.

- [ ] **Langkah 1: Periksa objek sasaran dan laporkan antrean ledger**

Jalankan dan tampilkan hasilnya ke pengguna sebelum melangkah:

```bash
php artisan tinker --execute="
echo 'baris aturan = '.DB::table('N_SOP_Assignment_Rule')->count().PHP_EOL;
echo 'baris run    = '.DB::table('N_SOP_Automation_Run')->count().PHP_EOL;
foreach(DB::select(\"SELECT COUNT(*) c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='N_SOP_Assignment_Rule_Paket'\") as \$r) echo 'tabel jembatan sudah ada? '.\$r->c.PHP_EOL;
"
```

Diharapkan: `aturan = 0`, `run = 0`, `tabel jembatan sudah ada? 0`. Kalau baris aturan **bukan** nol, BERHENTI dan laporkan — rencana ini mengasumsikan tak ada data yang perlu dimigrasi.

Laporkan juga: `database/ddl/status.json` berisi 59 catatan, semuanya `sql_tracker=PENDING` dan `production=PENDING`.

- [ ] **Langkah 2: Tulis `forward.sql`**

```sql
/* Change ID: DDL-2026-053-sop-aturan-multi-paket */

/* ---- 1. Tabel jembatan aturan <-> paket ---- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'N_SOP_Assignment_Rule_Paket')
CREATE TABLE N_SOP_Assignment_Rule_Paket (
    Id_SOP_Assignment_Rule_Paket int IDENTITY(1,1) NOT NULL,
    Id_SOP_Assignment_Rule       int NOT NULL,
    Id_SOP_Paket                 int NOT NULL,
    Urutan                       int NOT NULL CONSTRAINT DF_SOP_ARP_Urutan DEFAULT (1),
    Flag_Override_Jadwal         char(1) NOT NULL CONSTRAINT DF_SOP_ARP_Override DEFAULT ('T'),
    Deadline_Mode                varchar(10) NULL,
    Deadline_Hari                int NULL,
    Tanggal_Deadline             date NULL,
    Flag_Reminder                char(1) NULL,
    Irama_Reminder_Hari          int NULL,
    Last_Apply_Status            varchar(20) NOT NULL CONSTRAINT DF_SOP_ARP_ApplyStatus DEFAULT ('IDLE'),
    Last_Apply_Error             nvarchar(1000) NULL,
    Last_Applied_At              datetime2 NULL,
    Created_By                   int NULL,
    Created_At                   datetime2 NOT NULL CONSTRAINT DF_SOP_ARP_CreatedAt DEFAULT (SYSDATETIME()),
    Updated_By                   int NULL,
    Updated_At                   datetime2 NULL,
    Flag_Aktif                   char(1) NOT NULL CONSTRAINT DF_SOP_ARP_FlagAktif DEFAULT ('Y'),
    CONSTRAINT PK_SOP_Assignment_Rule_Paket PRIMARY KEY CLUSTERED (Id_SOP_Assignment_Rule_Paket),
    CONSTRAINT FK_SOP_ARP_Rule  FOREIGN KEY (Id_SOP_Assignment_Rule) REFERENCES N_SOP_Assignment_Rule (Id_SOP_Assignment_Rule),
    CONSTRAINT FK_SOP_ARP_Paket FOREIGN KEY (Id_SOP_Paket) REFERENCES N_SOP_Master_Paket (Id_SOP_Paket),
    CONSTRAINT CK_SOP_ARP_Override    CHECK (Flag_Override_Jadwal IN ('Y','T')),
    CONSTRAINT CK_SOP_ARP_FlagAktif   CHECK (Flag_Aktif IN ('Y','T')),
    CONSTRAINT CK_SOP_ARP_DeadlineMod CHECK (Deadline_Mode IS NULL OR Deadline_Mode IN ('NONE','RELATIVE','ABSOLUTE'))
);
GO

/* Penjaga tumpang tindih: satu paket hanya boleh diklaim satu aturan hidup.
   Ditegakkan mesin basis data, bukan hanya if di PHP — dua admin yang
   menyimpan bersamaan tak bisa sama-sama lolos. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Assignment_Rule_Paket_Paket')
CREATE UNIQUE INDEX UX_SOP_Assignment_Rule_Paket_Paket
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Paket)
    WHERE Flag_Aktif = 'Y';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Assignment_Rule_Paket_Rule')
CREATE INDEX IX_SOP_Assignment_Rule_Paket_Rule
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Assignment_Rule, Urutan);
GO

/* ---- 2. Riwayat eksekusi: baris induk + baris anak per paket ---- */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_SOP_Paket')
    ALTER TABLE N_SOP_Automation_Run ADD Id_SOP_Paket int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Automation_Run' AND COLUMN_NAME = 'Id_Run_Induk')
    ALTER TABLE N_SOP_Automation_Run ADD Id_Run_Induk int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Automation_Run_Induk')
CREATE INDEX IX_SOP_Automation_Run_Induk
    ON N_SOP_Automation_Run (Id_Run_Induk) WHERE Id_Run_Induk IS NOT NULL;
GO

/* ---- 3. Kolom paket lama pada tabel aturan berhenti dipakai ----
   TIDAK di-drop di sini: pembuangan kolom membuang data dan wajib dijalankan
   manual. Dijadikan nullable supaya INSERT baru tak lagi menuntutnya.
   Statement drop-nya tersedia di drop.sql. */
IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
           WHERE TABLE_NAME = 'N_SOP_Assignment_Rule' AND COLUMN_NAME = 'Id_SOP_Paket' AND IS_NULLABLE = 'NO')
    ALTER TABLE N_SOP_Assignment_Rule ALTER COLUMN Id_SOP_Paket int NULL;
GO

/* ---- 4. Catat di dashboard perubahan ---- */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-053-sop-aturan-multi-paket')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-053-sop-aturan-multi-paket', 'DDL', 'Satu aturan otomasi menampung banyak paket', 'Tabel jembatan N_SOP_Assignment_Rule_Paket dengan filtered unique index sebagai penjaga klaim paket; kolom Id_SOP_Paket dan Id_Run_Induk pada N_SOP_Automation_Run untuk riwayat per paket; N_SOP_Assignment_Rule.Id_SOP_Paket dijadikan nullable.', 'database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql', 'docs/changes/DDL-2026-053-sop-aturan-multi-paket/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'otomasi-multi-paket');
GO
```

- [ ] **Langkah 3: Tulis `drop.sql`** (bantuan manual — JANGAN dijalankan)

```sql
/* Change ID: DDL-2026-053-sop-aturan-multi-paket — pembersihan manual.
   JANGAN dijalankan oleh agent. Membuang data. */

DROP INDEX IF EXISTS UX_SOP_Assignment_Rule_Paket_Paket ON N_SOP_Assignment_Rule_Paket;
DROP INDEX IF EXISTS IX_SOP_Assignment_Rule_Paket_Rule  ON N_SOP_Assignment_Rule_Paket;
DROP TABLE IF EXISTS N_SOP_Assignment_Rule_Paket;
GO

DROP INDEX IF EXISTS IX_SOP_Automation_Run_Induk ON N_SOP_Automation_Run;
ALTER TABLE N_SOP_Automation_Run DROP COLUMN Id_SOP_Paket;
ALTER TABLE N_SOP_Automation_Run DROP COLUMN Id_Run_Induk;
GO

/* Opsional: membuang kolom paket lama yang sudah yatim setelah DDL-2026-053. */
ALTER TABLE N_SOP_Assignment_Rule DROP COLUMN Id_SOP_Paket;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-053-sop-aturan-multi-paket';
GO
```

- [ ] **Langkah 4: Tulis dokumentasi perubahan**

`docs/changes/DDL-2026-053-sop-aturan-multi-paket/README.md` memuat: change ID, basis data sasaran (`Web_HRIS`), deskripsi, objek terdampak (`N_SOP_Assignment_Rule_Paket`, `N_SOP_Automation_Run`, `N_SOP_Assignment_Rule`, `N_SYSTEM_Database_Change`), jalur DDL/dokumentasi, checksum sha256 `forward.sql`, status, pekerjaan yang diselesaikan agent, dan pekerjaan yang tersisa untuk pengguna (eksekusi ke production lewat SQL Tracker).

Ambil checksum-nya:

```bash
sha256sum database/ddl/DDL-2026-053-sop-aturan-multi-paket/forward.sql
```

- [ ] **Langkah 5: Tampilkan SQL ke pengguna, lalu jalankan ke dev**

Tampilkan isi `forward.sql` persis, sebutkan bahwa sasarannya `Web_HRIS` dan tak ada baris data yang tersentuh (tabel aturan 0 baris). Setelah itu jalankan.

- [ ] **Langkah 6: Verifikasi hasilnya di dev**

```bash
php artisan tinker --execute="
foreach(DB::select(\"SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='N_SOP_Assignment_Rule_Paket' ORDER BY ORDINAL_POSITION\") as \$c) echo \$c->COLUMN_NAME.' | '.\$c->DATA_TYPE.' | null='.\$c->IS_NULLABLE.PHP_EOL;
foreach(DB::select(\"SELECT name, is_unique, has_filter FROM sys.indexes WHERE object_id=OBJECT_ID('N_SOP_Assignment_Rule_Paket')\") as \$i) echo 'index '.\$i->name.' unique='.\$i->is_unique.' filtered='.\$i->has_filter.PHP_EOL;
foreach(DB::select(\"SELECT COLUMN_NAME, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='N_SOP_Automation_Run' AND COLUMN_NAME IN ('Id_SOP_Paket','Id_Run_Induk')\") as \$c) echo 'run.'.\$c->COLUMN_NAME.' null='.\$c->IS_NULLABLE.PHP_EOL;
foreach(DB::select(\"SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='N_SOP_Assignment_Rule' AND COLUMN_NAME='Id_SOP_Paket'\") as \$c) echo 'aturan.Id_SOP_Paket null='.\$c->IS_NULLABLE.PHP_EOL;
"
```

Diharapkan: 19 kolom, `UX_SOP_Assignment_Rule_Paket_Paket unique=1 filtered=1`, dua kolom run `null=YES`, `aturan.Id_SOP_Paket null=YES`.

- [ ] **Langkah 7: Buktikan filtered unique index benar-benar menggigit**

Penjaga yang tak pernah terlihat menyala bukan penjaga. Di dalam transaksi yang dibatalkan:

```bash
php artisan tinker --execute="
DB::beginTransaction();
try {
  \$p = DB::table('N_SOP_Master_Paket')->value('Id_SOP_Paket');
  \$r = DB::table('N_SOP_Assignment_Rule')->insertGetId(['Nama_Aturan'=>'uji-a','Status_Aturan'=>'DRAFT','Draft_Config_JSON'=>'{}','Draft_Hash'=>str_repeat('0',64),'Last_Apply_Status'=>'IDLE','Created_At'=>now(),'Flag_Aktif'=>'Y'],'Id_SOP_Assignment_Rule');
  \$r2= DB::table('N_SOP_Assignment_Rule')->insertGetId(['Nama_Aturan'=>'uji-b','Status_Aturan'=>'DRAFT','Draft_Config_JSON'=>'{}','Draft_Hash'=>str_repeat('1',64),'Last_Apply_Status'=>'IDLE','Created_At'=>now(),'Flag_Aktif'=>'Y'],'Id_SOP_Assignment_Rule');
  DB::table('N_SOP_Assignment_Rule_Paket')->insert(['Id_SOP_Assignment_Rule'=>\$r,'Id_SOP_Paket'=>\$p,'Created_At'=>now()]);
  DB::table('N_SOP_Assignment_Rule_Paket')->insert(['Id_SOP_Assignment_Rule'=>\$r2,'Id_SOP_Paket'=>\$p,'Created_At'=>now()]);
  echo 'GAGAL: klaim kedua LOLOS, index tidak menjaga apa pun'.PHP_EOL;
} catch (\Throwable \$e) { echo 'BENAR: klaim kedua ditolak -> '.substr(\$e->getMessage(),0,120).PHP_EOL; }
DB::rollBack();
"
```

Diharapkan: baris `BENAR: klaim kedua ditolak`. Kalau yang muncul `GAGAL`, index-nya salah — perbaiki sebelum melangkah.

- [ ] **Langkah 8: Catat di `status.json`**

Tambahkan entri baru mengikuti bentuk entri terakhir (`DML-2026-051`): `change_id`, `type: "DDL"`, `title`, `status: "DEV_DONE"`, `development: "DONE"`, `sql_tracker: "PENDING"`, `production: "PENDING"`, `verification: "DEV_DONE"`, `artifact_path`, `drop_path`, `documentation_path`, `forward_checksum_sha256`, `affected_objects`, `catatan`, `execution`.

- [ ] **Langkah 9: Commit**

```bash
git add database/ddl/DDL-2026-053-sop-aturan-multi-paket database/ddl/status.json docs/changes/DDL-2026-053-sop-aturan-multi-paket
git commit -m "feat(dms): DDL-2026-053 tabel jembatan aturan otomasi <-> paket"
```

---

## Task 2: Support — jadwal efektif & hash yang jujur

**Berkas:**
- Ubah: `app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php`
- Uji: `tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php` (buat)

**Antarmuka:**
- Memakai: —
- Menghasilkan:
  - `SopAssignmentRuleConfig::normalizeSchedule(array $schedule): array` — normalisasi satu blok jadwal; dipakai config induk **dan** override per paket
  - `SopAssignmentRuleConfig::hash(array $config, array $snapshotPaket = []): string`
  - `SopAssignmentRuleConfig::snapshotPaket(array $rows): array` — bentuk deterministik `[['id'=>int,'urutan'=>int,'override'=>bool,'schedule'=>array|null], …]` terurut `id`

- [ ] **Langkah 1: Tulis uji yang gagal**

Buat `tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php`:

```php
<?php

namespace Tests\Unit\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAssignmentRuleConfig as Config;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class SopAssignmentRuleConfigTest extends TestCase
{
    public function test_normalize_schedule_bisa_dipakai_sendiri_untuk_override(): void
    {
        $hasil = Config::normalizeSchedule([
            'deadline_mode' => 'relative',
            'deadline_days' => 30,
            'reminder_enabled' => true,
            'reminder_interval_days' => 5,
        ]);

        $this->assertSame('RELATIVE', $hasil['deadline_mode']);
        $this->assertSame(30, $hasil['deadline_days']);
        $this->assertTrue($hasil['reminder_enabled']);
        $this->assertSame(5, $hasil['reminder_interval_days']);
    }

    public function test_override_bermode_none_sah_walau_induk_bertenggat(): void
    {
        $hasil = Config::normalizeSchedule(['deadline_mode' => 'NONE']);

        $this->assertSame('NONE', $hasil['deadline_mode']);
        $this->assertNull($hasil['deadline_days']);
        $this->assertNull($hasil['deadline_date']);
    }

    public function test_schedule_tak_valid_tetap_ditolak(): void
    {
        $this->expectException(ValidationException::class);
        Config::normalizeSchedule(['deadline_mode' => 'RELATIVE', 'deadline_days' => 0]);
    }

    /**
     * Tanpa ini, menambah atau membuang paket tak mengubah Draft_Hash dan
     * lencana "draft berubah" di layar berbohong.
     */
    public function test_hash_berubah_ketika_daftar_paket_berubah(): void
    {
        $config = Config::normalize(['groups' => [['tipe' => 'Karyawan']]]);

        $satu = Config::hash($config, Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
        ]));
        $dua = Config::hash($config, Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
        ]));

        $this->assertNotSame($satu, $dua, 'Hash tak berubah saat paket ditambah.');
    }

    public function test_snapshot_paket_deterministik_terhadap_urutan_baris(): void
    {
        $a = Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
        ]);
        $b = Config::snapshotPaket([
            (object) ['Id_SOP_Paket' => 7, 'Urutan' => 1, 'Flag_Override_Jadwal' => 'T'],
            (object) ['Id_SOP_Paket' => 9, 'Urutan' => 2, 'Flag_Override_Jadwal' => 'T'],
        ]);

        $this->assertSame($a, $b, 'Urutan baris dari basis data mengubah snapshot.');
    }
}
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `php artisan test tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php`
Diharapkan pada Task 9: LULUS. Sebelum implementasi Langkah 3 ditulis, uji ini harus GAGAL dengan `Call to undefined method … normalizeSchedule()`.

- [ ] **Langkah 3: Ekstraksi `normalizeSchedule()`**

Di `SopAssignmentRuleConfig.php`, pindahkan seluruh blok validasi jadwal (baris 50-78 sekarang) ke metode publik, lalu panggil dari `normalize()`:

```php
    /**
     * Normalisasi satu blok jadwal.
     *
     * Publik karena dipakai DUA pemanggil: setelan induk aturan lewat
     * normalize(), dan setelan override per paket yang tinggal di tabel
     * jembatan. Satu aturan validasi untuk keduanya — kalau dipisah, override
     * bisa menerima tenggat yang ditolak induknya.
     */
    public static function normalizeSchedule(array $schedule): array
    {
        $mode = strtoupper((string) ($schedule['deadline_mode'] ?? 'NONE'));
        if (! in_array($mode, ['NONE', 'RELATIVE', 'ABSOLUTE'], true)) {
            throw ValidationException::withMessages(['schedule.deadline_mode' => 'Mode tenggat tidak valid.']);
        }

        $days = $mode === 'RELATIVE' ? (int) ($schedule['deadline_days'] ?? 0) : null;
        if ($mode === 'RELATIVE' && ($days < 1 || $days > 3650)) {
            throw ValidationException::withMessages(['schedule.deadline_days' => 'Tenggat relatif harus 1-3650 hari.']);
        }

        $date = $mode === 'ABSOLUTE' ? ($schedule['deadline_date'] ?? null) : null;
        if ($mode === 'ABSOLUTE') {
            if (! $date) {
                throw ValidationException::withMessages(['schedule.deadline_date' => 'Tanggal tenggat absolut wajib diisi.']);
            }
            try {
                $date = Carbon::parse($date)->format('Y-m-d');
            } catch (\Throwable) {
                throw ValidationException::withMessages(['schedule.deadline_date' => 'Tanggal tenggat absolut tidak valid.']);
            }
        }

        $reminder = (bool) ($schedule['reminder_enabled'] ?? false);
        $interval = $reminder ? (int) ($schedule['reminder_interval_days'] ?? 0) : null;
        if ($reminder && ($interval < 1 || $interval > 365)) {
            throw ValidationException::withMessages(['schedule.reminder_interval_days' => 'Interval reminder harus 1-365 hari.']);
        }

        return [
            'deadline_mode' => $mode,
            'deadline_days' => $days,
            'deadline_date' => $date,
            'reminder_enabled' => $reminder,
            'reminder_interval_days' => $interval,
        ];
    }
```

Lalu di dalam `normalize()`, ganti seluruh blok itu dengan:

```php
        $schedule = self::normalizeSchedule((array) ($input['schedule'] ?? []));
```

dan pada nilai balik ganti blok `'schedule' => [ … ]` menjadi `'schedule' => $schedule,`.

- [ ] **Langkah 4: Tambah `snapshotPaket()` dan hash bersnapshot**

```php
    /**
     * Bentuk deterministik daftar paket sebuah aturan, untuk ikut dihitung ke
     * dalam hash.
     *
     * Diurutkan menurut Id_SOP_Paket, BUKAN urutan baris yang dikembalikan
     * basis data: tanpa ORDER BY yang eksplisit, dua pembacaan atas isi yang
     * sama bisa menghasilkan hash berbeda dan lencana "draft berubah" menyala
     * tanpa ada yang berubah.
     *
     * @param  array<int,object>  $rows  baris N_SOP_Assignment_Rule_Paket
     */
    public static function snapshotPaket(array $rows): array
    {
        $snapshot = array_map(fn ($row) => [
            'id' => (int) $row->Id_SOP_Paket,
            'urutan' => (int) ($row->Urutan ?? 1),
            'override' => ($row->Flag_Override_Jadwal ?? 'T') === 'Y',
            'schedule' => ($row->Flag_Override_Jadwal ?? 'T') === 'Y' ? [
                'deadline_mode' => $row->Deadline_Mode ?? 'NONE',
                'deadline_days' => isset($row->Deadline_Hari) ? (int) $row->Deadline_Hari : null,
                'deadline_date' => $row->Tanggal_Deadline ?? null,
                'reminder_enabled' => ($row->Flag_Reminder ?? 'T') === 'Y',
                'reminder_interval_days' => isset($row->Irama_Reminder_Hari) ? (int) $row->Irama_Reminder_Hari : null,
            ] : null,
        ], $rows);

        usort($snapshot, fn ($a, $b) => $a['id'] <=> $b['id']);

        return $snapshot;
    }
```

Ubah tanda tangan `hash()`:

```php
    public static function hash(array $config, array $snapshotPaket = []): string
    {
        return hash('sha256', self::encode($config).'|'.self::encode($snapshotPaket));
    }
```

- [ ] **Langkah 5: Periksa sintaksis**

```bash
php -l app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php
php -l tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php
```

- [ ] **Langkah 6: Commit**

```bash
git add app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php
git commit -m "feat(dms): jadwal bisa dinormalkan sendiri dan hash menyerap daftar paket"
```

---

## Task 3: `SopAturanPaketService` — daftar paket & penjaga klaim

**Berkas:**
- Buat: `app/Services/LMS/SOP/SopAturanPaketService.php`
- Uji: `tests/Feature/LMS/SOP/Otomasi/SopAturanPaketServiceTest.php` (buat)

**Antarmuka:**
- Memakai: `SopAssignmentRuleConfig::normalizeSchedule()`, `SopAssignmentRuleConfig::snapshotPaket()` dari Task 2
- Menghasilkan:
  - `daftar(int $ruleId): array` — baris paket + `Nama_Paket` + `Mode_Distribusi`, urut `Urutan`
  - `barisMentah(int $ruleId): array` — baris tabel jembatan apa adanya (untuk snapshot hash)
  - `pemilikLain(array $idPaket, ?int $kecualiRuleId): array` — `[Id_SOP_Paket => Nama_Aturan]`
  - `sinkron(int $ruleId, array $paketInput, ?int $userId): void`
  - `jadwalEfektif(object $barisPaket, array $scheduleInduk): array`
  - `tandaiStatus(int $rulePaketId, string $status, ?string $error = null): void`
  - `tandaiGantung(int $ruleId, string $pesan): int`

  Bentuk `$paketInput`: `[['id_paket' => int, 'urutan' => int, 'override' => bool, 'schedule' => array|null], …]` dengan `id_paket` sudah ter-decode dari hashid oleh controller.

- [ ] **Langkah 1: Tulis uji yang gagal**

Buat `tests/Feature/LMS/SOP/Otomasi/SopAturanPaketServiceTest.php`:

```php
<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAturanPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

class SopAturanPaketServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan ketujuh hak akses halaman SOP.');
        }
        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatKategori()->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Aturan')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    private function aturanKosong(string $nama): int
    {
        return (int) DB::table('N_SOP_Assignment_Rule')->insertGetId([
            'Nama_Aturan' => $nama,
            'Status_Aturan' => 'DRAFT',
            'Draft_Config_JSON' => '{}',
            'Draft_Hash' => hash('sha256', $nama),
            'Last_Apply_Status' => 'IDLE',
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ], 'Id_SOP_Assignment_Rule');
    }

    public function test_daftar_paket_tersimpan_dan_terbaca_urut(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Kedua');
        $d->terbitkanPaketLain($lain['paket']);
        $svc = app(SopAturanPaketService::class);
        $ruleId = $this->aturanKosong($d->nama('Aturan Multi'));

        $svc->sinkron($ruleId, [
            ['id_paket' => $lain['paket'], 'urutan' => 2, 'override' => false, 'schedule' => null],
            ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
        ], $d->userIdUji());

        $daftar = $svc->daftar($ruleId);

        $this->assertCount(2, $daftar, 'Daftar paket tidak tersimpan lengkap.');
        $this->assertSame($d->idPaket, (int) $daftar[0]->Id_SOP_Paket, 'Daftar tidak urut menurut Urutan.');
        $this->assertNotEmpty($daftar[0]->Nama_Paket, 'Nama paket tidak ikut terbaca.');
    }

    /**
     * PENJAGA. Keputusan 2.2: satu paket hanya boleh diklaim satu aturan.
     * Uji ini WAJIB pernah terlihat MERAH.
     */
    public function test_paket_milik_aturan_lain_ditolak_dan_pesannya_menyebut_pemiliknya(): void
    {
        $d = $this->dunia();
        $svc = app(SopAturanPaketService::class);

        $namaA = $d->nama('Aturan Pemilik');
        $ruleA = $this->aturanKosong($namaA);
        $svc->sinkron($ruleA, [
            ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
        ], $d->userIdUji());

        $ruleB = $this->aturanKosong($d->nama('Aturan Penyerobot'));

        try {
            $svc->sinkron($ruleB, [
                ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
            ], $d->userIdUji());
            $this->fail('Klaim atas paket milik aturan lain LOLOS. Penjaga tumpang tindih tidak bekerja.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', array_merge(...array_values($e->errors())));
            $this->assertStringContainsString($namaA, $pesan,
                'Pesan penolakan tidak menyebut aturan pemiliknya, sehingga admin tak tahu harus ke mana.');
        }
    }

    /**
     * Keputusan 2.4: membuang paket melepas kepemilikan, penugasan lama utuh,
     * dan paket itu langsung bebas diklaim aturan lain.
     */
    public function test_paket_yang_dibuang_bebas_diklaim_aturan_lain(): void
    {
        $d = $this->dunia();
        $svc = app(SopAturanPaketService::class);

        $ruleA = $this->aturanKosong($d->nama('Aturan A'));
        $svc->sinkron($ruleA, [
            ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
        ], $d->userIdUji());

        $svc->sinkron($ruleA, [], $d->userIdUji());

        $ruleB = $this->aturanKosong($d->nama('Aturan B'));
        $svc->sinkron($ruleB, [
            ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
        ], $d->userIdUji());

        $this->assertCount(1, $svc->daftar($ruleB), 'Paket yang dibuang tidak bisa diklaim aturan lain.');
        $this->assertCount(0, $svc->daftar($ruleA), 'Paket yang dibuang masih menempel pada aturan lamanya.');
    }

    public function test_jadwal_efektif_memakai_override_ketika_dinyalakan(): void
    {
        $svc = app(SopAturanPaketService::class);
        $induk = [
            'deadline_mode' => 'RELATIVE', 'deadline_days' => 7, 'deadline_date' => null,
            'reminder_enabled' => false, 'reminder_interval_days' => null,
        ];

        $ikut = (object) ['Flag_Override_Jadwal' => 'T'];
        $this->assertSame(7, $svc->jadwalEfektif($ikut, $induk)['deadline_days']);

        $sendiri = (object) [
            'Flag_Override_Jadwal' => 'Y', 'Deadline_Mode' => 'RELATIVE', 'Deadline_Hari' => 30,
            'Tanggal_Deadline' => null, 'Flag_Reminder' => 'Y', 'Irama_Reminder_Hari' => 5,
        ];
        $hasil = $svc->jadwalEfektif($sendiri, $induk);

        $this->assertSame(30, $hasil['deadline_days'], 'Override tenggat tidak dipakai.');
        $this->assertTrue($hasil['reminder_enabled'], 'Override reminder tidak dipakai.');
    }

    /**
     * Override adalah penggantian UTUH, bukan tambahan di atas induk:
     * paket boleh bermode NONE walau induknya bertenggat.
     */
    public function test_override_none_membatalkan_tenggat_induk(): void
    {
        $svc = app(SopAturanPaketService::class);
        $induk = [
            'deadline_mode' => 'RELATIVE', 'deadline_days' => 7, 'deadline_date' => null,
            'reminder_enabled' => true, 'reminder_interval_days' => 3,
        ];
        $sendiri = (object) [
            'Flag_Override_Jadwal' => 'Y', 'Deadline_Mode' => 'NONE', 'Deadline_Hari' => null,
            'Tanggal_Deadline' => null, 'Flag_Reminder' => 'T', 'Irama_Reminder_Hari' => null,
        ];

        $hasil = $svc->jadwalEfektif($sendiri, $induk);

        $this->assertSame('NONE', $hasil['deadline_mode']);
        $this->assertNull($hasil['deadline_days'], 'Tenggat induk bocor ke paket yang di-override NONE.');
    }
}
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `php artisan test tests/Feature/LMS/SOP/Otomasi/SopAturanPaketServiceTest.php`
Diharapkan sebelum implementasi: GAGAL, `Target class [App\Services\LMS\SOP\SopAturanPaketService] does not exist.`

- [ ] **Langkah 3: Tulis service**

```php
<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAssignmentRuleConfig;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daftar paket yang dimiliki satu aturan penugasan otomatis.
 *
 * Sebelum DDL-2026-053 relasi ini adalah satu kolom pada N_SOP_Assignment_Rule,
 * sehingga satu cohort yang wajib membaca lima paket harus disusun sebagai lima
 * aturan berkriteria kembar. Sekarang relasinya tinggal di tabel jembatan, dan
 * penjaga "satu paket satu aturan" ditegakkan filtered unique index
 * UX_SOP_Assignment_Rule_Paket_Paket — bukan hanya oleh pemeriksaan di sini.
 * Pemeriksaan di kelas ini ada untuk PESANNYA: index memberi galat basis data
 * yang tak menyebut siapa pemilik paketnya.
 */
class SopAturanPaketService
{
    /** @return array<int,object> baris + Nama_Paket + Mode_Distribusi, urut Urutan */
    public function daftar(int $ruleId): array
    {
        return DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'rp.Id_SOP_Paket')
            ->where('rp.Id_SOP_Assignment_Rule', $ruleId)
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('rp.Urutan')
            ->orderBy('rp.Id_SOP_Assignment_Rule_Paket')
            ->get(['rp.*', 'p.Nama_Paket', 'p.Mode_Distribusi'])
            ->all();
    }

    /** Baris apa adanya, untuk perhitungan snapshot hash. */
    public function barisMentah(int $ruleId): array
    {
        return DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get()
            ->all();
    }

    /**
     * Paket mana saja yang sudah diklaim aturan LAIN.
     *
     * @return array<int,string> [Id_SOP_Paket => Nama_Aturan pemiliknya]
     */
    public function pemilikLain(array $idPaket, ?int $kecualiRuleId): array
    {
        if (! $idPaket) {
            return [];
        }

        return DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Assignment_Rule as r', 'r.Id_SOP_Assignment_Rule', '=', 'rp.Id_SOP_Assignment_Rule')
            ->whereIn('rp.Id_SOP_Paket', $idPaket)
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            ->where('r.Flag_Aktif', SopStatus::AKTIF)
            ->when($kecualiRuleId, fn ($q) => $q->where('rp.Id_SOP_Assignment_Rule', '<>', $kecualiRuleId))
            ->pluck('r.Nama_Aturan', 'rp.Id_SOP_Paket')
            ->all();
    }

    /**
     * Samakan isi tabel jembatan dengan daftar yang dikirim layar.
     *
     * Paket yang hilang dari daftar di-soft-delete (Flag_Aktif='T'), TIDAK
     * menyentuh penugasan yang sudah tercatat: keputusan 2.4 — yang berhenti
     * hanya penugasan baru. Soft-delete juga yang melepaskan paket itu dari
     * filtered unique index sehingga aturan lain langsung bisa mengklaimnya.
     */
    public function sinkron(int $ruleId, array $paketInput, ?int $userId): void
    {
        $normal = $this->normalkanInput($paketInput);
        $idBaru = array_column($normal, 'id_paket');

        $bentrok = $this->pemilikLain($idBaru, $ruleId);
        if ($bentrok) {
            $nama = DB::table('N_SOP_Master_Paket')->whereIn('Id_SOP_Paket', array_keys($bentrok))
                ->pluck('Nama_Paket', 'Id_SOP_Paket')->all();
            $rincian = [];
            foreach ($bentrok as $idPaket => $namaAturan) {
                $rincian[] = ($nama[$idPaket] ?? "Paket #{$idPaket}")." sudah dimiliki aturan \"{$namaAturan}\"";
            }
            throw ValidationException::withMessages([
                'paket' => 'Paket berikut tidak bisa dipakai: '.implode('; ', $rincian).'.',
            ]);
        }

        DB::transaction(function () use ($ruleId, $normal, $idBaru, $userId) {
            DB::table('N_SOP_Assignment_Rule_Paket')
                ->where('Id_SOP_Assignment_Rule', $ruleId)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->when($idBaru, fn ($q) => $q->whereNotIn('Id_SOP_Paket', $idBaru))
                ->update(['Flag_Aktif' => 'T', 'Updated_By' => $userId, 'Updated_At' => now()]);

            foreach ($normal as $baris) {
                $kolom = [
                    'Urutan' => $baris['urutan'],
                    'Flag_Override_Jadwal' => $baris['override'] ? 'Y' : 'T',
                    'Deadline_Mode' => $baris['override'] ? $baris['schedule']['deadline_mode'] : null,
                    'Deadline_Hari' => $baris['override'] ? $baris['schedule']['deadline_days'] : null,
                    'Tanggal_Deadline' => $baris['override'] ? $baris['schedule']['deadline_date'] : null,
                    'Flag_Reminder' => $baris['override'] ? ($baris['schedule']['reminder_enabled'] ? 'Y' : 'T') : null,
                    'Irama_Reminder_Hari' => $baris['override'] ? $baris['schedule']['reminder_interval_days'] : null,
                    'Updated_By' => $userId,
                    'Updated_At' => now(),
                ];

                $adaId = DB::table('N_SOP_Assignment_Rule_Paket')
                    ->where('Id_SOP_Assignment_Rule', $ruleId)
                    ->where('Id_SOP_Paket', $baris['id_paket'])
                    ->where('Flag_Aktif', SopStatus::AKTIF)
                    ->value('Id_SOP_Assignment_Rule_Paket');

                if ($adaId) {
                    DB::table('N_SOP_Assignment_Rule_Paket')
                        ->where('Id_SOP_Assignment_Rule_Paket', $adaId)->update($kolom);

                    continue;
                }

                DB::table('N_SOP_Assignment_Rule_Paket')->insert($kolom + [
                    'Id_SOP_Assignment_Rule' => $ruleId,
                    'Id_SOP_Paket' => $baris['id_paket'],
                    'Last_Apply_Status' => 'IDLE',
                    'Created_By' => $userId,
                    'Created_At' => now(),
                    'Flag_Aktif' => SopStatus::AKTIF,
                ]);
            }
        });
    }

    /**
     * Jadwal yang benar-benar dipakai paket ini.
     *
     * Override adalah penggantian UTUH, bukan tambahan di atas induk: paket
     * bermode NONE sah walau induknya bertenggat.
     */
    public function jadwalEfektif(object $barisPaket, array $scheduleInduk): array
    {
        if (($barisPaket->Flag_Override_Jadwal ?? 'T') !== 'Y') {
            return $scheduleInduk;
        }

        return SopAssignmentRuleConfig::normalizeSchedule([
            'deadline_mode' => $barisPaket->Deadline_Mode ?? 'NONE',
            'deadline_days' => $barisPaket->Deadline_Hari ?? null,
            'deadline_date' => $barisPaket->Tanggal_Deadline ?? null,
            'reminder_enabled' => ($barisPaket->Flag_Reminder ?? 'T') === 'Y',
            'reminder_interval_days' => $barisPaket->Irama_Reminder_Hari ?? null,
        ]);
    }

    public function tandaiStatus(int $rulePaketId, string $status, ?string $error = null): void
    {
        DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule_Paket', $rulePaketId)
            ->update([
                'Last_Apply_Status' => $status,
                'Last_Apply_Error' => $error ? mb_substr($error, 0, 1000) : null,
                'Last_Applied_At' => $status === 'SUCCESS' ? now() : null,
                'Updated_At' => now(),
            ]);
    }

    /**
     * Tandai paket yang masih menggantung sebagai FAILED.
     *
     * Dipanggil hook failed() job: tanpa ini aturan tampak NEEDS_ATTENTION
     * tetapi tak satu paket pun menjelaskan sebabnya.
     *
     * @return int jumlah baris yang disapu
     */
    public function tandaiGantung(int $ruleId, string $pesan): int
    {
        return DB::table('N_SOP_Assignment_Rule_Paket')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Last_Apply_Status', ['IDLE', 'RUNNING'])
            ->update([
                'Last_Apply_Status' => 'FAILED',
                'Last_Apply_Error' => mb_substr($pesan, 0, 1000),
                'Updated_At' => now(),
            ]);
    }

    /** @return array<int,array{id_paket:int,urutan:int,override:bool,schedule:?array}> */
    private function normalkanInput(array $paketInput): array
    {
        $hasil = [];
        $terlihat = [];
        $urutan = 0;

        foreach ($paketInput as $baris) {
            $idPaket = (int) ($baris['id_paket'] ?? 0);
            if ($idPaket <= 0 || isset($terlihat[$idPaket])) {
                continue;
            }
            $terlihat[$idPaket] = true;
            $override = (bool) ($baris['override'] ?? false);

            $hasil[] = [
                'id_paket' => $idPaket,
                'urutan' => (int) ($baris['urutan'] ?? ++$urutan),
                'override' => $override,
                'schedule' => $override
                    ? SopAssignmentRuleConfig::normalizeSchedule((array) ($baris['schedule'] ?? []))
                    : null,
            ];
        }

        $ada = DB::table('N_SOP_Master_Paket')
            ->whereIn('Id_SOP_Paket', array_column($hasil, 'id_paket'))
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Id_SOP_Paket')->all();

        $hilang = array_diff(array_column($hasil, 'id_paket'), $ada);
        if ($hilang) {
            throw ValidationException::withMessages([
                'paket' => 'Ada paket yang bukan paket published aktif: '.implode(', ', $hilang).'.',
            ]);
        }

        return $hasil;
    }
}
```

- [ ] **Langkah 4: Periksa sintaksis**

```bash
php -l app/Services/LMS/SOP/SopAturanPaketService.php
php -l tests/Feature/LMS/SOP/Otomasi/SopAturanPaketServiceTest.php
```

- [ ] **Langkah 5: Commit**

```bash
git add app/Services/LMS/SOP/SopAturanPaketService.php tests/Feature/LMS/SOP/Otomasi/SopAturanPaketServiceTest.php
git commit -m "feat(dms): daftar paket per aturan otomasi berikut penjaga klaimnya"
```

---

## Task 4: `SopAssignmentRuleService` — simpan, pratinjau, apply per paket

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopAssignmentRuleService.php`
- Uji: `tests/Feature/LMS/SOP/Otomasi/SopAturanApplyTest.php` (buat)
- Uji: `tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php:86-140` (menyesuaikan tanda tangan baru)

**Antarmuka:**
- Memakai: `SopAturanPaketService` (Task 3), `SopAssignmentRuleConfig::hash/snapshotPaket` (Task 2)
- Menghasilkan:
  - `saveDraft(string $name, array $input, array $paket, ?int $userId, ?int $ruleId = null): int` — **parameter `$idPaket` yang lama dibuang**, `$paket` menggantikannya
  - `preview(int $ruleId, ?array $override = null): array` — nilai balik berubah menjadi `['paket' => [ … per paket … ], 'total' => [ … ]]`
  - `list()` dan `find()` mengembalikan `Jumlah_Paket` (int) dan `Paket` (array) menggantikan `Nama_Paket`/`Id_SOP_Paket`

- [ ] **Langkah 1: Tulis uji yang gagal**

Buat `tests/Feature/LMS/SOP/Otomasi/SopAturanApplyTest.php`:

```php
<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAturanPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

class SopAturanApplyTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan ketujuh hak akses halaman SOP.');
        }
        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatKategori()->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Aturan')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    public function test_satu_aturan_menyimpan_tiga_paket_sekaligus(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket B'); $d->terbitkanPaketLain($b['paket']);
        $c = $d->paketTambahan('Paket C'); $d->terbitkanPaketLain($c['paket']);

        $ruleId = app(SopAssignmentRuleService::class)->saveDraft(
            $d->nama('Aturan Finance'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
                ['id_paket' => $b['paket'], 'urutan' => 2, 'override' => false, 'schedule' => null],
                ['id_paket' => $c['paket'], 'urutan' => 3, 'override' => false, 'schedule' => null],
            ],
            $d->userIdUji(),
        );

        $this->assertCount(3, app(SopAturanPaketService::class)->daftar($ruleId),
            'Aturan tidak menyimpan ketiga paket.');
    }

    public function test_pratinjau_memberi_angka_per_paket_dan_total(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket B'); $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Pratinjau'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
                ['id_paket' => $b['paket'], 'urutan' => 2, 'override' => false, 'schedule' => null],
            ],
            $d->userIdUji(),
        );

        $pratinjau = $svc->preview($ruleId);

        $this->assertArrayHasKey('paket', $pratinjau, 'Pratinjau tidak merinci per paket.');
        $this->assertArrayHasKey('total', $pratinjau, 'Pratinjau tidak memberi baris TOTAL.');
        $this->assertCount(2, $pratinjau['paket']);
        $this->assertSame(
            array_sum(array_column($pratinjau['paket'], 'matched')),
            $pratinjau['total']['matched'],
            'Baris TOTAL tidak sama dengan jumlah barisan paketnya.',
        );
    }

    /**
     * PENJAGA. Paket yang sudah tersemai tidak boleh dianulir karena paket
     * berikutnya gagal. Kegagalan disimulasikan dengan menghapus paket kedua
     * dari master setelah aturan tersimpan.
     */
    public function test_paket_gagal_tidak_menyeret_paket_yang_sudah_tersemai(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Rusak'); $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);
        $paketSvc = app(SopAturanPaketService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Sebagian'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
                ['id_paket' => $b['paket'], 'urutan' => 2, 'override' => false, 'schedule' => null],
            ],
            $d->userIdUji(),
        );

        // Rusakkan paket kedua: itemnya dilepas sehingga assign atasnya gagal.
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $b['paket'])
            ->update(['Id_Item_Versi_Aktif' => null]);

        $runId = $svc->queueActivation($ruleId, $d->userIdUji());
        $svc->applyQueued($runId, $ruleId, true, $d->userIdUji());

        $daftar = $paketSvc->daftar($ruleId);
        $status = array_column(array_map(fn ($r) => (array) $r, $daftar), 'Last_Apply_Status', 'Id_SOP_Paket');

        $this->assertSame('SUCCESS', $status[$d->idPaket] ?? null,
            'Paket pertama ikut dianulir padahal sudah tersemai.');

        $aturan = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
        $this->assertContains($aturan->Last_Apply_Status, ['NEEDS_ATTENTION', 'SUCCESS'],
            'Status aturan tidak turun dari status paket-paketnya.');
    }

    /**
     * Override tenggat harus benar-benar sampai ke header penjadwalan —
     * setelan yang tersimpan tapi tak dipakai adalah setelan yang berbohong.
     */
    public function test_override_tenggat_melahirkan_header_bertanggal_berbeda(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Override'); $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Override'),
            [
                'groups' => [['tipe' => 'Karyawan']],
                'schedule' => ['deadline_mode' => 'RELATIVE', 'deadline_days' => 7],
            ],
            [
                ['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null],
                ['id_paket' => $b['paket'], 'urutan' => 2, 'override' => true, 'schedule' => [
                    'deadline_mode' => 'RELATIVE', 'deadline_days' => 30,
                ]],
            ],
            $d->userIdUji(),
        );

        $runId = $svc->queueActivation($ruleId, $d->userIdUji());
        $svc->applyQueued($runId, $ruleId, true, $d->userIdUji());

        $tenggat = DB::table('N_SOP_Penjadwalan')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->pluck('Tanggal_Deadline', 'Id_SOP_Paket')->all();

        $this->assertNotEquals(
            (string) ($tenggat[$d->idPaket] ?? ''),
            (string) ($tenggat[$b['paket']] ?? ''),
            'Kedua paket berakhir dengan tenggat sama; override tidak dipakai.',
        );
    }
}
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `php artisan test tests/Feature/LMS/SOP/Otomasi/SopAturanApplyTest.php`
Diharapkan sebelum implementasi: GAGAL pada `saveDraft()` karena tanda tangannya belum berubah.

- [ ] **Langkah 3: Ubah `list()` dan `find()`**

`mapRow()` tak lagi punya `Nama_Paket`/`Mode_Distribusi` dari join. Ganti kedua metode:

```php
    public function list(): array
    {
        $rows = DB::table('N_SOP_Assignment_Rule as r')
            ->where('r.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('r.Nama_Aturan')
            ->get([
                'r.Id_SOP_Assignment_Rule', 'r.Nama_Aturan', 'r.Status_Aturan',
                'r.Draft_Hash', 'r.Active_Hash', 'r.Last_Apply_Status', 'r.Last_Apply_Error',
                'r.Last_Applied_At', 'r.Last_Scanned_At', 'r.Updated_At',
            ]);

        // Satu kueri untuk semua aturan, bukan satu per aturan: daftar ini
        // dimuat setiap kali halaman dibuka.
        $paket = DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'rp.Id_SOP_Paket')
            ->whereIn('rp.Id_SOP_Assignment_Rule', $rows->pluck('Id_SOP_Assignment_Rule'))
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('rp.Urutan')
            ->get(['rp.Id_SOP_Assignment_Rule', 'rp.Id_SOP_Paket', 'p.Nama_Paket', 'p.Mode_Distribusi'])
            ->groupBy('Id_SOP_Assignment_Rule');

        return $rows->map(function ($row) use ($paket) {
            $milik = $paket->get($row->Id_SOP_Assignment_Rule, collect());
            $row->Jumlah_Paket = $milik->count();
            $row->Paket = $milik->map(fn ($p) => [
                'Id_SOP_Paket' => Hashids::connection('custom')->encode($p->Id_SOP_Paket),
                'Nama_Paket' => $p->Nama_Paket,
                'Mode_Distribusi' => $p->Mode_Distribusi ?: 'PUBLIK',
            ])->values()->all();

            return $this->mapRow($row);
        })->all();
    }

    public function find(int $ruleId): ?array
    {
        $row = DB::table('N_SOP_Assignment_Rule')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();

        if (! $row) {
            return null;
        }

        $paket = $this->paket->daftar($ruleId);
        $row->Jumlah_Paket = count($paket);
        $row->Paket = array_map(fn ($p) => [
            'Id_SOP_Paket' => Hashids::connection('custom')->encode($p->Id_SOP_Paket),
            'Nama_Paket' => $p->Nama_Paket,
            'Mode_Distribusi' => $p->Mode_Distribusi ?: 'PUBLIK',
            'Urutan' => (int) $p->Urutan,
            'override' => $p->Flag_Override_Jadwal === 'Y',
            'schedule' => $p->Flag_Override_Jadwal === 'Y' ? [
                'deadline_mode' => $p->Deadline_Mode,
                'deadline_days' => $p->Deadline_Hari !== null ? (int) $p->Deadline_Hari : null,
                'deadline_date' => $p->Tanggal_Deadline,
                'reminder_enabled' => $p->Flag_Reminder === 'Y',
                'reminder_interval_days' => $p->Irama_Reminder_Hari !== null ? (int) $p->Irama_Reminder_Hari : null,
            ] : null,
            'Last_Apply_Status' => $p->Last_Apply_Status,
            'Last_Apply_Error' => $p->Last_Apply_Error,
        ], $paket);

        $mapped = (array) $this->mapRow($row);
        $mapped['config'] = json_decode($row->Draft_Config_JSON, true) ?: [];

        return $mapped;
    }
```

Di `mapRow()`, buang baris `$row->Id_SOP_Paket = …` dan `$row->Mode_Distribusi = …`, dan ubah baris hash:

```php
        $row->Draft_Berubah = ! empty($row->Active_Hash) && $row->Draft_Hash !== $row->Active_Hash;
```
(tetap — hash-nya yang sudah menyerap paket, bukan pembandingannya.)

Tambahkan `SopAturanPaketService $paket` ke konstruktor.

- [ ] **Langkah 4: Ubah `saveDraft()`**

```php
    /**
     * @param  array  $paket  daftar paket: [['id_paket'=>int,'urutan'=>int,'override'=>bool,'schedule'=>?array], …]
     */
    public function saveDraft(string $name, array $input, array $paket, ?int $userId, ?int $ruleId = null): int
    {
        $config = SopAssignmentRuleConfig::normalize($input);
        $json = SopAssignmentRuleConfig::encode($config);
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['nama_aturan' => 'Nama aturan wajib diisi.']);
        }
        if (! $paket) {
            throw ValidationException::withMessages(['paket' => 'Aturan harus memuat minimal satu paket SOP.']);
        }

        return DB::transaction(function () use ($ruleId, $name, $json, $config, $paket, $userId) {
            if ($ruleId) {
                $ada = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->exists();
                if (! $ada) {
                    throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
                }
            } else {
                $ruleId = (int) DB::table('N_SOP_Assignment_Rule')->insertGetId([
                    'Nama_Aturan' => mb_substr($name, 0, 150),
                    'Status_Aturan' => 'DRAFT',
                    'Draft_Config_JSON' => $json,
                    'Draft_Hash' => SopAssignmentRuleConfig::hash($config),
                    'Last_Apply_Status' => 'IDLE',
                    'Created_By' => $userId,
                    'Created_At' => now(),
                    'Flag_Aktif' => SopStatus::AKTIF,
                ], 'Id_SOP_Assignment_Rule');
            }

            // Daftar paket disinkronkan LEBIH DULU: hash draft harus dihitung
            // dari daftar yang sudah tersimpan, kalau tidak lencana "draft
            // berubah" mengukur keadaan yang sudah basi.
            $this->paket->sinkron($ruleId, $paket, $userId);

            $hash = SopAssignmentRuleConfig::hash(
                $config,
                SopAssignmentRuleConfig::snapshotPaket($this->paket->barisMentah($ruleId)),
            );

            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Nama_Aturan' => mb_substr($name, 0, 150),
                'Draft_Config_JSON' => $json,
                'Draft_Hash' => $hash,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            SopLog::catat('KEBIJAKAN_DRAFT', 'Draft aturan kebijakan SOP disimpan.', null, null, null, null, [
                'id_assignment_rule' => $ruleId,
                'nama_aturan' => mb_substr($name, 0, 150),
                'jumlah_paket' => count($paket),
                'draft_hash' => $hash,
                'oleh_id_users' => $userId,
            ]);

            return $ruleId;
        });
    }
```

- [ ] **Langkah 5: Ubah `preview()` menjadi per paket**

```php
    public function preview(int $ruleId, ?array $override = null): array
    {
        $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
        }
        $config = $override
            ? SopAssignmentRuleConfig::normalize($override)
            : (json_decode($rule->Draft_Config_JSON, true) ?: []);

        $baris = [];
        $total = ['matched' => 0, 'new' => 0, 'kept' => 0, 'covered' => 0, 'release' => 0, 'withheld' => 0, 'excluded' => 0];

        foreach ($this->paket->daftar($ruleId) as $paket) {
            $jadwal = $this->paket->jadwalEfektif($paket, $config['schedule']);
            $dampak = $this->impact($ruleId, (int) $paket->Id_SOP_Paket, $config, false);
            $dampak['Nama_Paket'] = $paket->Nama_Paket;
            $dampak['Id_SOP_Paket'] = Hashids::connection('custom')->encode($paket->Id_SOP_Paket);
            // Paket yang tertahan gerbang tenggat absolut harus terlihat SEBELUM
            // admin menekan Aktifkan, bukan sesudahnya di riwayat.
            $dampak['tertahan_tenggat'] = $jadwal['deadline_mode'] === 'ABSOLUTE'
                && Carbon::parse($jadwal['deadline_date'])->lt(Carbon::today());

            foreach (array_keys($total) as $k) {
                $total[$k] += (int) ($dampak[$k] ?? 0);
            }
            $baris[] = $dampak;
        }

        return ['paket' => $baris, 'total' => $total, 'auto_release' => SopSetting::bool('mover_auto_release', false)];
    }
```

- [ ] **Langkah 6: Ubah `impact()` — per paket dan O(1)**

Tanda tangannya menerima paket, dan `in_array` diganti lookup ber-flip:

```php
    private function impact(int $ruleId, int $idPaket, array $config, bool $internal): array
    {
        $groupCodes = [];
        foreach ($config['groups'] as $group) {
            $groupCodes = array_merge($groupCodes, $this->targets->resolveTargets($group));
        }
        $groupCodes = array_values(array_unique($groupCodes));
        $included = $this->resolvePeople($config['include_karyawan']);
        $excluded = $this->resolvePeople($config['exclude_karyawan']);
        $beforeExclude = array_values(array_unique(array_merge($groupCodes, $included)));
        $matched = array_values(array_diff($beforeExclude, $excluded));

        // Lookup O(1). Sebelumnya in_array() dipanggil di dalam filter atas
        // SELURUH penugasan aktif paket — biayanya perkalian, dan dengan lima
        // paket per aturan biaya itu dikali lima.
        $matchedIndex = array_flip($matched);

        $current = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->get(['d.Kode_Karyawan', 'd.Id_SOP_Assignment_Rule', 'pj.Sumber'])
            ->keyBy('Kode_Karyawan');

        $new = [];
        $kept = 0;
        $covered = 0;
        foreach ($matched as $code) {
            $assignment = $current->get($code);
            if (! $assignment) {
                $new[] = $code;
            } elseif ((int) ($assignment->Id_SOP_Assignment_Rule ?? 0) === $ruleId
                && $assignment->Sumber === 'AUTO_RULE') {
                $kept++;
            } else {
                $covered++;
            }
        }

        $release = $current->filter(fn ($assignment, $code) => (int) ($assignment->Id_SOP_Assignment_Rule ?? 0) === $ruleId
            && $assignment->Sumber === 'AUTO_RULE'
            && ! isset($matchedIndex[$code])
        )->keys()->values()->all();
        $autoRelease = SopSetting::bool('mover_auto_release', false);

        $result = [
            'matched' => count($matched),
            'new' => count($new),
            'kept' => $kept,
            'covered' => $covered,
            'release' => $autoRelease ? count($release) : 0,
            'withheld' => $autoRelease ? 0 : count($release),
            'excluded' => count(array_intersect($beforeExclude, $excluded)),
            'auto_release' => $autoRelease,
        ];
        if ($internal) {
            $result['_new_codes'] = $new;
            $result['_release_codes'] = $release;
        }

        return $result;
    }
```

- [ ] **Langkah 7: Ubah `applyQueued()` menjadi loop per paket**

Ganti seluruh badan `try` (setelah `claim()`) dengan:

```php
        try {
            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Last_Apply_Status' => 'RUNNING', 'Last_Apply_Error' => null,
            ]);
            $payload = $this->runs->payload($runId);
            $config = SopAssignmentRuleConfig::normalize((array) ($payload['config'] ?? []));
            $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
            if (! $rule) {
                throw new \RuntimeException('Aturan tidak ditemukan.');
            }

            $ringkas = [];
            $adaMasalah = false;

            foreach ($this->paket->daftar($ruleId) as $paket) {
                $idPaket = (int) $paket->Id_SOP_Paket;
                $rpId = (int) $paket->Id_SOP_Assignment_Rule_Paket;
                $runAnak = $this->runs->queueAnak($runId, $idPaket, 'RULE_APPLY', 'ACTIVATION', $ruleId, $rule->Draft_Hash, $userId);

                try {
                    $hasil = $this->terapkanSatuPaket($rule, $ruleId, $paket, $config, $activate, $userId);

                    if (! empty($hasil['withheld_gate'])) {
                        $adaMasalah = true;
                        $this->paket->tandaiStatus($rpId, 'NEEDS_ATTENTION', $hasil['message']);
                        $this->runs->skip($runAnak, $hasil['message'], $hasil);
                    } else {
                        $this->paket->tandaiStatus($rpId, 'SUCCESS');
                        $this->runs->success($runAnak, $hasil);
                    }
                    $ringkas[] = ['paket' => $paket->Nama_Paket] + $hasil;
                } catch (\Throwable $e) {
                    // Paket yang sudah tersemai TIDAK dianulir karena paket
                    // berikutnya gagal — janji yang sama sudah dipegang jalur
                    // chunk di dalam terapkanSatuPaket().
                    $adaMasalah = true;
                    Log::error("SOP apply aturan {$ruleId} paket {$idPaket}: ".$e->getMessage());
                    $this->paket->tandaiStatus($rpId, 'FAILED', $e->getMessage());
                    $this->runs->fail($runAnak, $e->getMessage());
                    $ringkas[] = ['paket' => $paket->Nama_Paket, 'gagal' => mb_substr($e->getMessage(), 0, 200)];
                }
            }

            $total = $this->totalkan($ringkas);
            $total['rincian'] = $ringkas;

            DB::transaction(function () use ($ruleId, $config, $userId, $activate, $adaMasalah) {
                $updates = [
                    'Last_Apply_Status' => $adaMasalah ? 'NEEDS_ATTENTION' : 'SUCCESS',
                    'Last_Apply_Error' => $adaMasalah ? 'Sebagian paket tidak selesai. Periksa rincian per paket.' : null,
                    'Last_Applied_At' => now(),
                    'Last_Scanned_At' => now(),
                    'Updated_By' => $userId,
                    'Updated_At' => now(),
                ];
                if ($activate) {
                    $snapshot = SopAssignmentRuleConfig::snapshotPaket($this->paket->barisMentah($ruleId));
                    $updates += [
                        'Active_Config_JSON' => SopAssignmentRuleConfig::encode($config),
                        'Active_Hash' => SopAssignmentRuleConfig::hash($config, $snapshot),
                        'Status_Aturan' => 'ACTIVE',
                        'Activated_At' => now(),
                        'Deactivated_At' => null,
                    ];
                }
                DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update($updates);
            });

            $this->runs->success($runId, $total);
            SopLog::catat('KEBIJAKAN_APPLY_OK', 'Apply aturan kebijakan SOP selesai.', null, null, null, null, [
                'id_assignment_rule' => $ruleId,
                'id_automation_run' => $runId,
                'activate' => $activate,
                'result' => $total,
                'oleh_id_users' => $userId,
            ]);

            return $total;
        } catch (\Throwable $e) {
            $this->runs->fail($runId, $e->getMessage());
            $this->paket->tandaiGantung($ruleId, $e->getMessage());
            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Last_Apply_Status' => 'FAILED',
                'Last_Apply_Error' => mb_substr($e->getMessage(), 0, 1000),
                'Updated_At' => now(),
            ]);
            SopLog::catat('KEBIJAKAN_APPLY_FAIL', 'Apply aturan kebijakan SOP gagal.', null, null, null, null, [
                'id_assignment_rule' => $ruleId,
                'id_automation_run' => $runId,
                'activate' => $activate,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'oleh_id_users' => $userId,
            ]);
            throw $e;
        }
```

- [ ] **Langkah 8: Tambah `terapkanSatuPaket()` dan `totalkan()`**

```php
    /**
     * Badan apply untuk SATU paket. Isinya persis jalur lama yang dulu berlaku
     * untuk satu-satunya paket sebuah aturan — termasuk pemecahan chunk 100 per
     * transaksi, yang menjaga penugasan yang sudah commit tidak dianulir oleh
     * chunk berikutnya.
     */
    private function terapkanSatuPaket(object $rule, int $ruleId, object $paket, array $config, bool $activate, ?int $userId): array
    {
        $idPaket = (int) $paket->Id_SOP_Paket;
        $schedule = $this->paket->jadwalEfektif($paket, $config['schedule']);

        if ($schedule['deadline_mode'] === 'ABSOLUTE'
            && Carbon::parse($schedule['deadline_date'])->lt(Carbon::today())) {
            return [
                'withheld_gate' => 1,
                'withheld' => 1,
                'message' => 'Tenggat absolut sudah lewat. Penugasan baru ditahan sampai draft diperbaiki.',
                'reason' => 'ABSOLUTE_DEADLINE_PASSED',
            ];
        }

        $impact = $this->impact($ruleId, $idPaket, $config, true);
        $assignedTargets = [];
        $released = 0;
        $chunkGagal = [];

        if ($impact['_new_codes']) {
            $opts = $this->scheduleOptions($schedule, $ruleId, $rule->Nama_Aturan, $paket->Nama_Paket);
            $headerId = DB::transaction(fn () => $this->schedules->buatHeader($idPaket, $opts, $userId));

            foreach (array_chunk($impact['_new_codes'], self::CHUNK_ASSIGN) as $i => $chunk) {
                try {
                    $hasil = DB::transaction(fn () => $this->schedules->assignKeHeader(
                        $headerId, $idPaket, $chunk, $userId, $ruleId,
                    ));
                    $assignedTargets = array_merge($assignedTargets, $hasil['targets']);
                } catch (\Throwable $e) {
                    Log::error("SOP apply aturan paket {$idPaket} chunk {$i}: ".$e->getMessage());
                    $chunkGagal[] = [
                        'chunk' => $i + 1,
                        'jumlah' => count($chunk),
                        'pesan' => mb_substr($e->getMessage(), 0, 200),
                    ];
                }
            }
        }

        DB::transaction(function () use ($ruleId, $idPaket, $impact, $userId, &$released) {
            if (SopSetting::bool('mover_auto_release', false) && $impact['_release_codes']) {
                $released = $this->schedules->cabutMilikRule($ruleId, $idPaket, $impact['_release_codes'], $userId);
            }
        });

        if ($assignedTargets) {
            app(SopNotificationService::class)->notifPenugasan($assignedTargets, $idPaket, $paket->Nama_Paket ?: 'SOP');
        }

        unset($impact['_new_codes'], $impact['_release_codes']);
        $impact['assigned'] = count($assignedTargets);
        $impact['released'] = $released;
        if ($chunkGagal) {
            $impact['gagal'] = $chunkGagal;
        }

        return $impact;
    }

    /** @param array<int,array> $ringkas */
    private function totalkan(array $ringkas): array
    {
        $total = ['matched' => 0, 'new' => 0, 'assigned' => 0, 'kept' => 0, 'covered' => 0, 'released' => 0, 'withheld' => 0];
        foreach ($ringkas as $baris) {
            foreach (array_keys($total) as $k) {
                $total[$k] += (int) ($baris[$k] ?? 0);
            }
        }
        $total['paket_diproses'] = count($ringkas);

        return $total;
    }
```

Ubah `scheduleOptions()` agar menerima nama paket, supaya nama batch menyebut paketnya:

```php
    private function scheduleOptions(array $schedule, int $ruleId, string $ruleName, string $namaPaket = ''): array
    {
        $mode = $schedule['deadline_mode'];
        $deadline = null;
        if ($mode === 'RELATIVE') {
            $deadline = now()->addDays($schedule['deadline_days'])->endOfDay();
        }
        if ($mode === 'ABSOLUTE') {
            $deadline = Carbon::parse($schedule['deadline_date'])->endOfDay();
        }

        return [
            'id_assignment_rule' => $ruleId,
            'nama_batch' => 'Otomasi - '.$ruleName.($namaPaket ? ' - '.$namaPaket : '').' - '.now()->format('d M Y'),
            'keterangan' => 'Cohort otomatis dari Otomasi SOP.',
            'sumber' => 'AUTO_RULE',
            'tipe_tenggat' => $mode === 'NONE' ? 'SAMPAI_DICABUT' : 'DEADLINE',
            'tanggal_deadline' => $deadline,
            'flag_reminder' => $schedule['reminder_enabled'],
            'irama_reminder_hari' => $schedule['reminder_interval_days'],
        ];
    }
```

- [ ] **Langkah 9: Sesuaikan uji UAT lama**

Di `tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php`, ketiga panggilan `saveDraft()` (baris 91, 122, dan di uji `test_aturan_tanpa_kriteria_apa_pun_ditolak`) memakai tanda tangan lama. Ubah polanya menjadi:

```php
        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Otomatis'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null]],
            $d->userIdUji()
        );
```

Dan pada `test_aturan_penugasan_disimpan_sebagai_draft_dan_bisa_dipratinjau`, ganti asersi pratinjau menjadi:

```php
        $this->assertArrayHasKey('paket', $pratinjau,
            'Pratinjau aturan tidak merinci dampak per paket; dampaknya baru diketahui '
            . 'setelah orang-orang menerima tugasnya.');
```

- [ ] **Langkah 10: Periksa sintaksis**

```bash
php -l app/Services/LMS/SOP/SopAssignmentRuleService.php
php -l tests/Feature/LMS/SOP/Otomasi/SopAturanApplyTest.php
php -l tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php
```

- [ ] **Langkah 11: Commit**

```bash
git add app/Services/LMS/SOP/SopAssignmentRuleService.php tests/Feature/LMS/SOP/Otomasi/SopAturanApplyTest.php tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php
git commit -m "feat(dms): apply aturan otomasi berjalan per paket dengan kegagalan yang berdiri sendiri"
```

---

## Task 5: Riwayat eksekusi induk & anak

**Berkas:**
- Ubah: `app/Services/LMS/SOP/SopAutomationRunService.php`
- Ubah: `app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php:42-58`
- Uji: `tests/Feature/LMS/SOP/Otomasi/SopRiwayatOtomasiTest.php` (buat)

**Antarmuka:**
- Memakai: `SopAturanPaketService::tandaiGantung()` (Task 3)
- Menghasilkan:
  - `queueAnak(int $indukId, int $idPaket, string $type, string $trigger, ?int $ruleId, ?string $hash, ?int $userId): int`
  - `history(int $limit = 50): array` — `['rows' => array, 'ditampilkan' => int, 'total_induk' => int]`

- [ ] **Langkah 1: Tulis uji yang gagal**

Buat `tests/Feature/LMS/SOP/Otomasi/SopRiwayatOtomasiTest.php`:

```php
<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAutomationRunService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopRiwayatOtomasiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_baris_anak_menempel_pada_induknya_dan_membawa_nama_paket(): void
    {
        $svc = app(SopAutomationRunService::class);
        $idPaket = (int) DB::table('N_SOP_Master_Paket')->value('Id_SOP_Paket');
        $this->assertGreaterThan(0, $idPaket, 'Tidak ada paket di basis data uji.');

        $induk = $svc->queue('RULE_APPLY', 'ACTIVATION');
        $anak = $svc->queueAnak($induk, $idPaket, 'RULE_APPLY', 'ACTIVATION', null, null, null);

        $baris = DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $anak)->first();

        $this->assertSame($induk, (int) $baris->Id_Run_Induk, 'Baris anak tidak menunjuk induknya.');
        $this->assertSame($idPaket, (int) $baris->Id_SOP_Paket, 'Baris anak tidak menyimpan paketnya.');

        $riwayat = $svc->history(50);
        $cocok = array_filter($riwayat['rows'], fn ($r) => $r->Id_SOP_Automation_Run === $svc->encodeUji($anak));
        $this->assertNotEmpty($cocok, 'Baris anak tidak muncul di riwayat.');
        $this->assertNotEmpty(reset($cocok)->Nama_Paket, 'Nama paket tidak ikut di baris anak.');
    }

    /**
     * PENJAGA. Plafon tidak boleh memotong diam-diam: konvensi repo sejak audit
     * paging target penjadwalan.
     */
    public function test_riwayat_melaporkan_plafon_dan_total_sebenarnya(): void
    {
        $svc = app(SopAutomationRunService::class);
        for ($i = 0; $i < 3; $i++) {
            $svc->queue('RULE_SCAN', 'MANUAL');
        }

        $riwayat = $svc->history(2);

        $this->assertArrayHasKey('ditampilkan', $riwayat);
        $this->assertArrayHasKey('total_induk', $riwayat);
        $this->assertLessThanOrEqual(2, $riwayat['ditampilkan'], 'Plafon tidak ditaati.');
        $this->assertGreaterThanOrEqual(3, $riwayat['total_induk'],
            'Total sebenarnya tidak dilaporkan, sehingga pemotongan jadi senyap.');
    }
}
```

Tambahkan helper kecil pada service supaya uji bisa membandingkan hashid (metode publik, dipakai uji dan tak berbahaya):

```php
    /** Hashid untuk satu id run — dipakai uji untuk mencocokkan baris. */
    public function encodeUji(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `php artisan test tests/Feature/LMS/SOP/Otomasi/SopRiwayatOtomasiTest.php`
Diharapkan sebelum implementasi: GAGAL, `Call to undefined method … queueAnak()`.

- [ ] **Langkah 3: Tambah `queueAnak()`**

```php
    /**
     * Baris anak: hasil satu paket di dalam satu eksekusi.
     *
     * Penjaga konkurensi TIDAK dipindahkan ke sini. claim(), hasActive(),
     * hasActiveForRule(), reapStale(), dan hook failed() tetap bekerja pada
     * baris INDUK — satu baris, persis seperti sebelum multi-paket. Baris anak
     * murni catatan hasil, sehingga tak ada pengaman lama yang perlu diubah.
     */
    public function queueAnak(
        int $indukId,
        int $idPaket,
        string $type,
        string $trigger,
        ?int $ruleId = null,
        ?string $configHash = null,
        ?int $requestedBy = null,
    ): int {
        return DB::table('N_SOP_Automation_Run')->insertGetId([
            'Id_Run_Induk' => $indukId,
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Assignment_Rule' => $ruleId,
            'Tipe_Run' => $type,
            'Trigger_Run' => $trigger,
            'Status_Run' => 'RUNNING',
            'Config_Hash' => $configHash,
            'Requested_By' => $requestedBy,
            'Queued_At' => now(),
            'Started_At' => now(),
            'Created_At' => now(),
        ], 'Id_SOP_Automation_Run');
    }
```

Pastikan `hasActive()`, `hasActiveForRule()`, dan `reapStale()` hanya melihat baris induk — tambahkan `->whereNull('Id_Run_Induk')` pada ketiganya, dengan komentar:

```php
            // Hanya baris induk. Baris anak berumur pendek dan tak pernah jadi
            // dasar penjaga konkurensi; ikut menghitungnya membuat aturan
            // tampak "masih berjalan" setelah induknya selesai.
            ->whereNull('Id_Run_Induk')
```

- [ ] **Langkah 4: Tulis ulang `history()`**

```php
    /**
     * Riwayat: baris induk terbaru berikut seluruh baris anaknya.
     *
     * Plafon dihitung atas baris INDUK; anak ikut serta tanpa memakan jatah,
     * karena satu aktivasi lima paket kalau tidak akan memakan sepersepuluh
     * jendela riwayat sendirian.
     *
     * @return array{rows:array,ditampilkan:int,total_induk:int}
     */
    public function history(int $limit = 50): array
    {
        $limit = min(100, max(1, $limit));

        $indukIds = DB::table('N_SOP_Automation_Run')
            ->whereNull('Id_Run_Induk')
            ->orderByDesc('Id_SOP_Automation_Run')
            ->limit($limit)
            ->pluck('Id_SOP_Automation_Run');

        $totalInduk = (int) DB::table('N_SOP_Automation_Run')->whereNull('Id_Run_Induk')->count();

        if ($indukIds->isEmpty()) {
            return ['rows' => [], 'ditampilkan' => 0, 'total_induk' => $totalInduk];
        }

        $rows = DB::table('N_SOP_Automation_Run as r')
            ->leftJoin('N_SOP_Assignment_Rule as ar', 'ar.Id_SOP_Assignment_Rule', '=', 'r.Id_SOP_Assignment_Rule')
            // Nama paket diambil dari kolom run LANGSUNG. Join lewat aturan
            // putus begitu satu aturan tak lagi menunjuk satu paket.
            ->leftJoin('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'r.Id_SOP_Paket')
            ->where(fn ($q) => $q->whereIn('r.Id_SOP_Automation_Run', $indukIds)
                ->orWhereIn('r.Id_Run_Induk', $indukIds))
            ->orderByDesc(DB::raw('COALESCE(r.Id_Run_Induk, r.Id_SOP_Automation_Run)'))
            ->orderBy('r.Id_SOP_Automation_Run')
            ->get([
                'r.Id_SOP_Automation_Run', 'r.Id_Run_Induk', 'r.Id_SOP_Assignment_Rule', 'r.Id_SOP_Paket',
                'r.Tipe_Run', 'r.Trigger_Run', 'r.Status_Run', 'r.Total_Ditemukan', 'r.Total_Ditambahkan',
                'r.Total_Dipertahankan', 'r.Total_Dicabut', 'r.Total_Dilewati', 'r.Error_Message',
                'r.Queued_At', 'r.Started_At', 'r.Completed_At', 'p.Nama_Paket', 'ar.Nama_Aturan',
            ])->map(function ($row) {
                $row->Id_SOP_Automation_Run = Hashids::connection('custom')->encode($row->Id_SOP_Automation_Run);
                $row->Id_Run_Induk = $row->Id_Run_Induk
                    ? Hashids::connection('custom')->encode($row->Id_Run_Induk) : null;
                $row->Id_SOP_Assignment_Rule = $row->Id_SOP_Assignment_Rule
                    ? Hashids::connection('custom')->encode($row->Id_SOP_Assignment_Rule) : null;
                $row->Is_Anak = $row->Id_Run_Induk !== null;

                return $row;
            })->all();

        return ['rows' => $rows, 'ditampilkan' => $indukIds->count(), 'total_induk' => $totalInduk];
    }
```

- [ ] **Langkah 5: Perbaiki hook `failed()` job**

Di `app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php`, tambahkan penandaan baris paket:

```php
        app(SopAutomationRunService::class)->fail($this->runId, $pesan);

        // Tanpa ini aturan tampak NEEDS_ATTENTION tetapi tak satu paket pun
        // menjelaskan sebabnya, sehingga admin tak tahu paket mana yang
        // sebenarnya berhenti.
        app(SopAturanPaketService::class)->tandaiGantung($this->ruleId, $pesan);
```

Tambahkan `use App\Services\LMS\SOP\SopAturanPaketService;` di kepala berkas.

- [ ] **Langkah 6: Periksa sintaksis**

```bash
php -l app/Services/LMS/SOP/SopAutomationRunService.php
php -l app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php
php -l tests/Feature/LMS/SOP/Otomasi/SopRiwayatOtomasiTest.php
```

- [ ] **Langkah 7: Commit**

```bash
git add app/Services/LMS/SOP/SopAutomationRunService.php app/Jobs/LMS/SOP/Automation/ApplySopAssignmentRule.php tests/Feature/LMS/SOP/Otomasi/SopRiwayatOtomasiTest.php
git commit -m "feat(dms): riwayat otomasi jadi baris induk plus satu baris per paket"
```

---

## Task 6: Controller — kontrak API baru

**Berkas:**
- Ubah: `app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php`
- Uji: `tests/Feature/LMS/SOP/Otomasi/SopKebijakanEndpointTest.php` (buat)

**Antarmuka:**
- Memakai: `SopAssignmentRuleService::saveDraft/preview` (Task 4), `SopAutomationRunService::history` (Task 5)
- Menghasilkan kontrak HTTP:
  - `POST/PUT /api/v1/sop/admin/kebijakan/rules` menerima `nama_aturan`, `config`, dan `paket[]` — **`id_paket` tunggal dibuang**
  - `GET  /runs` mengembalikan `{rows, ditampilkan, total_induk}`

- [ ] **Langkah 1: Tulis uji yang gagal**

Buat `tests/Feature/LMS/SOP/Otomasi/SopKebijakanEndpointTest.php`:

```php
<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\TestCase;

class SopKebijakanEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private function admin()
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan ketujuh hak akses halaman SOP.');
        }

        return $admin;
    }

    public function test_menyimpan_aturan_tanpa_paket_ditolak(): void
    {
        $admin = $this->admin();

        $r = $this->actingAs(\App\Models\User::find($admin->Id_Users))
            ->postJson('/api/v1/sop/admin/kebijakan/rules', [
                'nama_aturan' => 'Aturan Tanpa Paket',
                'config' => ['groups' => [['tipe' => 'Karyawan']]],
                'paket' => [],
            ]);

        $r->assertStatus(422);
    }

    public function test_riwayat_mengembalikan_plafon_dan_total(): void
    {
        $admin = $this->admin();

        $r = $this->actingAs(\App\Models\User::find($admin->Id_Users))
            ->getJson('/api/v1/sop/admin/kebijakan/runs');

        $r->assertOk()
            ->assertJsonStructure(['result' => ['rows', 'ditampilkan', 'total_induk']]);
    }
}
```

> Catatan pelaksana: model user aplikasi ini adalah `App\Models\User` dengan primary key `Id_Users`. Kalau `actingAs` gagal, pakai pola yang sama dengan berkas uji Feature SOP lain yang sudah ada di `tests/Feature/LMS/SOP/` dan sesuaikan — jangan mengarang helper baru.

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `php artisan test tests/Feature/LMS/SOP/Otomasi/SopKebijakanEndpointTest.php`

- [ ] **Langkah 3: Ubah `storeRule()` dan `updateRule()`**

```php
    public function storeRule(Request $request)
    {
        try {
            $request->validate([
                'nama_aturan' => 'required|string|max:150',
                'config' => 'required|array',
                'paket' => 'required|array|min:1',
                'paket.*.id_paket' => 'required|string',
                'paket.*.urutan' => 'nullable|integer|min:1',
                'paket.*.override' => 'nullable|boolean',
                'paket.*.schedule' => 'nullable|array',
            ]);

            $id = $this->rules->saveDraft(
                $request->input('nama_aturan'),
                $request->input('config'),
                $this->paketMasukan($request->input('paket')),
                Auth::id(),
            );

            return ResponseHelper::success(['id' => $this->encode($id)], 'Draft aturan tersimpan', 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('store rule', $e);
        }
    }

    public function updateRule(string $id, Request $request)
    {
        try {
            $request->validate([
                'nama_aturan' => 'required|string|max:150',
                'config' => 'required|array',
                'paket' => 'required|array|min:1',
                'paket.*.id_paket' => 'required|string',
                'paket.*.urutan' => 'nullable|integer|min:1',
                'paket.*.override' => 'nullable|boolean',
                'paket.*.schedule' => 'nullable|array',
            ]);
            $ruleId = $this->decode($id);

            $this->rules->saveDraft(
                $request->input('nama_aturan'),
                $request->input('config'),
                $this->paketMasukan($request->input('paket')),
                Auth::id(),
                $ruleId,
            );

            return ResponseHelper::success(['id' => $this->encode($ruleId)], 'Draft aturan diperbarui');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('update rule', $e);
        }
    }

    /**
     * Terjemahkan daftar paket dari layar: hashid → id nyata.
     *
     * Dilakukan di controller, bukan di service, supaya service tak perlu tahu
     * apa pun tentang hashid — sama seperti seluruh endpoint lain di berkas ini.
     */
    private function paketMasukan(array $masukan): array
    {
        return array_map(fn ($baris, $i) => [
            'id_paket' => $this->decode((string) $baris['id_paket']),
            'urutan' => (int) ($baris['urutan'] ?? $i + 1),
            'override' => (bool) ($baris['override'] ?? false),
            'schedule' => $baris['schedule'] ?? null,
        ], $masukan, array_keys($masukan));
    }
```

- [ ] **Langkah 4: Selaraskan plafon riwayat**

```php
    public function history(Request $request)
    {
        try {
            // Plafon diselaraskan dengan service (100). Sebelumnya controller
            // mengizinkan 200 sementara service diam-diam menjepitnya ke 100.
            return ResponseHelper::success(
                $this->runs->history(min(100, max(5, (int) $request->query('limit', 50)))),
            );
        } catch (\Throwable $e) {
            return $this->serverError('run history', $e);
        }
    }
```

- [ ] **Langkah 5: Periksa sintaksis**

```bash
php -l app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php
php -l tests/Feature/LMS/SOP/Otomasi/SopKebijakanEndpointTest.php
```

- [ ] **Langkah 6: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php tests/Feature/LMS/SOP/Otomasi/SopKebijakanEndpointTest.php
git commit -m "feat(dms): endpoint aturan otomasi menerima daftar paket"
```

---

## Task 7: Layar — daftar paket & override

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.vue`
- Uji: `resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.test.js` (buat)

**Antarmuka:**
- Props: `modelValue: Array` (daftar paket), `options: Array` (paket tersedia), `disabled: Boolean`
- Emit: `update:modelValue`
- Bentuk satu baris: `{ Id_SOP_Paket, Nama_Paket, Mode_Distribusi, Urutan, override, schedule }`

- [ ] **Langkah 1: Tulis uji yang gagal**

```js
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SopAturanPaketList from './SopAturanPaketList.vue';

const paket = (id, nama) => ({
    Id_SOP_Paket: id,
    Nama_Paket: nama,
    Mode_Distribusi: 'PUBLIK',
    Urutan: 1,
    override: false,
    schedule: null,
});

describe('SopAturanPaketList', () => {
    it('menampilkan setiap paket yang dipilih', () => {
        const w = mount(SopAturanPaketList, {
            props: { modelValue: [paket('a1', 'Paket A'), paket('b2', 'Paket B')], options: [] },
            global: { stubs: { HcisSelect: true, HcisSelectOption: true, HcisSwitch: true, HcisBadge: true, HcisButton: true, HcisField: true, HcisInputNumber: true, HcisDatePicker: true, HcisIcon: true } },
        });

        expect(w.text()).toContain('Paket A');
        expect(w.text()).toContain('Paket B');
    });

    it('menyembunyikan setelan tenggat selama override mati', () => {
        const w = mount(SopAturanPaketList, {
            props: { modelValue: [paket('a1', 'Paket A')], options: [] },
            global: { stubs: { HcisSelect: true, HcisSelectOption: true, HcisSwitch: true, HcisBadge: true, HcisButton: true, HcisField: true, HcisInputNumber: true, HcisDatePicker: true, HcisIcon: true } },
        });

        expect(w.find('[data-uji="setelan-override"]').exists()).toBe(false);
    });

    it('memunculkan setelan tenggat ketika override dinyalakan', () => {
        const baris = { ...paket('a1', 'Paket A'), override: true, schedule: { deadline_mode: 'RELATIVE', deadline_days: 30, deadline_date: null, reminder_enabled: false, reminder_interval_days: null } };
        const w = mount(SopAturanPaketList, {
            props: { modelValue: [baris], options: [] },
            global: { stubs: { HcisSelect: true, HcisSelectOption: true, HcisSwitch: true, HcisBadge: true, HcisButton: true, HcisField: true, HcisInputNumber: true, HcisDatePicker: true, HcisIcon: true } },
        });

        expect(w.find('[data-uji="setelan-override"]').exists()).toBe(true);
    });

    it('membuang satu paket memancarkan daftar tanpa paket itu', async () => {
        const w = mount(SopAturanPaketList, {
            props: { modelValue: [paket('a1', 'Paket A'), paket('b2', 'Paket B')], options: [] },
            global: { stubs: { HcisSelect: true, HcisSelectOption: true, HcisSwitch: true, HcisBadge: true, HcisButton: true, HcisField: true, HcisInputNumber: true, HcisDatePicker: true, HcisIcon: true } },
        });

        await w.find('[data-uji="buang-a1"]').trigger('click');

        const emitted = w.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(emitted.at(-1)[0].map((p) => p.Id_SOP_Paket)).toEqual(['b2']);
    });
});
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.test.js`

- [ ] **Langkah 3: Tulis komponennya**

```vue
<template>
    <div class="sop-aturan-paket-list">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3 flex-wrap">
            <div>
                <h6 class="text-xs uppercase font-extrabold text-brand-700 tracking-wider mb-1">
                    1. Paket SOP yang Diwajibkan
                </h6>
                <small class="text-slate-500">
                    Satu aturan boleh memuat beberapa paket. Paket yang sudah dimiliki aturan lain
                    tidak muncul di pilihan.
                </small>
            </div>
            <HcisBadge :label="`${modelValue.length} paket`" variant="brand" size="xs" />
        </div>

        <HcisField id="sop-tambah-paket" label="Tambah Paket">
            <HcisSelect
                :model-value="''"
                placeholder="Pilih paket SOP published…"
                filterable
                :disabled="disabled || !options.length"
                @update:model-value="tambah"
            >
                <HcisSelectOption
                    v-for="pkg in options"
                    :key="pkg.Id_SOP_Paket"
                    :value="pkg.Id_SOP_Paket"
                    :label="pkg.Nama_Paket"
                    :badge="pkg.Mode_Distribusi === 'PUBLIK' ? 'Publik' : 'Terbatas'"
                    :badge-variant="pkg.Mode_Distribusi === 'PUBLIK' ? 'success' : 'warning'"
                />
            </HcisSelect>
        </HcisField>

        <HcisEmptyState
            v-if="!modelValue.length"
            title="Belum Ada Paket"
            description="Aturan belum memuat paket SOP mana pun. Tambahkan minimal satu."
            icon="box-seam"
            size="sm"
            class="mt-3"
        />

        <div v-else class="d-flex flex-column gap-2 mt-3">
            <HcisCard
                v-for="(baris, index) in modelValue"
                :key="baris.Id_SOP_Paket"
                variant="flat"
                sentiment="neutral"
                size="sm"
                divider
            >
                <template #header>
                    <div class="d-flex justify-content-between align-items-center w-100 gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <HcisBadge :label="`#${index + 1}`" variant="brand" size="xs" />
                            <strong class="text-truncate text-slate-900" style="font-size: 0.875rem;">
                                {{ baris.Nama_Paket }}
                            </strong>
                            <HcisBadge
                                :label="baris.Mode_Distribusi === 'PUBLIK' ? 'Publik' : 'Terbatas'"
                                :variant="baris.Mode_Distribusi === 'PUBLIK' ? 'success' : 'warning'"
                                size="xs"
                                appearance="subtle"
                            />
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-up"
                                :disabled="disabled || index === 0"
                                :title="`Naikkan ${baris.Nama_Paket}`"
                                :aria-label="`Naikkan urutan ${baris.Nama_Paket}`"
                                @click="geser(index, -1)"
                            />
                            <HcisButton
                                variant="ghost"
                                size="xs"
                                leading-icon="arrow-down"
                                :disabled="disabled || index === modelValue.length - 1"
                                :title="`Turunkan ${baris.Nama_Paket}`"
                                :aria-label="`Turunkan urutan ${baris.Nama_Paket}`"
                                @click="geser(index, 1)"
                            />
                            <HcisButton
                                variant="danger"
                                size="xs"
                                leading-icon="trash3"
                                :disabled="disabled"
                                :data-uji="`buang-${baris.Id_SOP_Paket}`"
                                :title="`Buang ${baris.Nama_Paket}`"
                                :aria-label="`Buang paket ${baris.Nama_Paket}`"
                                @click="buang(index)"
                            />
                        </div>
                    </div>
                </template>

                <!-- Override adalah PENGGANTIAN utuh setelan induk, bukan tambahan di
                     atasnya: mode NONE di sini berarti "paket ini tanpa tenggat"
                     walaupun setelan induknya bertenggat. -->
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="text-xs text-slate-600">
                        {{ baris.override ? 'Memakai tenggat sendiri' : 'Mengikuti tenggat induk' }}
                    </span>
                    <HcisSwitch
                        :model-value="!!baris.override"
                        :disabled="disabled"
                        label="Pakai tenggat sendiri"
                        size="sm"
                        @update:model-value="setOverride(index, $event)"
                    />
                </div>

                <div v-if="baris.override" data-uji="setelan-override" class="row g-2 mt-2">
                    <div class="col-12 col-md-4">
                        <HcisField :id="`ov-mode-${index}`" label="Mode Tenggat">
                            <HcisSelect
                                :model-value="baris.schedule.deadline_mode"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'deadline_mode', $event)"
                            >
                                <HcisSelectOption value="NONE" label="Tanpa tenggat" />
                                <HcisSelectOption value="RELATIVE" label="Sekian hari sejak ditugaskan" />
                                <HcisSelectOption value="ABSOLUTE" label="Tanggal tertentu" />
                            </HcisSelect>
                        </HcisField>
                    </div>
                    <div v-if="baris.schedule.deadline_mode === 'RELATIVE'" class="col-12 col-md-3">
                        <HcisField :id="`ov-hari-${index}`" label="Jumlah Hari">
                            <HcisInputNumber
                                :model-value="baris.schedule.deadline_days"
                                :min="1"
                                :max="3650"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'deadline_days', $event)"
                            />
                        </HcisField>
                    </div>
                    <div v-if="baris.schedule.deadline_mode === 'ABSOLUTE'" class="col-12 col-md-3">
                        <HcisField :id="`ov-tgl-${index}`" label="Tanggal Tenggat">
                            <HcisDatePicker
                                :model-value="baris.schedule.deadline_date"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'deadline_date', $event)"
                            />
                        </HcisField>
                    </div>
                    <div class="col-12 col-md-3">
                        <HcisField :id="`ov-rem-${index}`" label="Pengingat">
                            <HcisSwitch
                                :model-value="!!baris.schedule.reminder_enabled"
                                :disabled="disabled"
                                label="Kirim pengingat"
                                size="sm"
                                @update:model-value="ubahJadwal(index, 'reminder_enabled', $event)"
                            />
                        </HcisField>
                    </div>
                    <div v-if="baris.schedule.reminder_enabled" class="col-12 col-md-2">
                        <HcisField :id="`ov-irama-${index}`" label="Tiap (hari)">
                            <HcisInputNumber
                                :model-value="baris.schedule.reminder_interval_days"
                                :min="1"
                                :max="365"
                                :disabled="disabled"
                                @update:model-value="ubahJadwal(index, 'reminder_interval_days', $event)"
                            />
                        </HcisField>
                    </div>
                </div>
            </HcisCard>
        </div>
    </div>
</template>

<script>
import {
    HcisBadge,
    HcisButton,
    HcisCard,
    HcisDatePicker,
    HcisEmptyState,
    HcisField,
    HcisInputNumber,
    HcisSelect,
    HcisSelectOption,
    HcisSwitch,
} from '@/components/ui';

const jadwalBaku = () => ({
    deadline_mode: 'NONE',
    deadline_days: 7,
    deadline_date: null,
    reminder_enabled: false,
    reminder_interval_days: 3,
});

export default {
    name: 'SopAturanPaketList',
    components: {
        HcisBadge, HcisButton, HcisCard, HcisDatePicker, HcisEmptyState,
        HcisField, HcisInputNumber, HcisSelect, HcisSelectOption, HcisSwitch,
    },
    props: {
        modelValue: { type: Array, default: () => [] },
        options: { type: Array, default: () => [] },
        disabled: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    methods: {
        pancarkan(daftar) {
            this.$emit('update:modelValue', daftar.map((p, i) => ({ ...p, Urutan: i + 1 })));
        },
        tambah(idPaket) {
            if (!idPaket) return;
            const pkg = this.options.find((p) => p.Id_SOP_Paket === idPaket);
            if (!pkg) return;
            this.pancarkan([
                ...this.modelValue,
                {
                    Id_SOP_Paket: pkg.Id_SOP_Paket,
                    Nama_Paket: pkg.Nama_Paket,
                    Mode_Distribusi: pkg.Mode_Distribusi,
                    Urutan: this.modelValue.length + 1,
                    override: false,
                    schedule: null,
                },
            ]);
        },
        buang(index) {
            const daftar = [...this.modelValue];
            daftar.splice(index, 1);
            this.pancarkan(daftar);
        },
        geser(index, arah) {
            const tujuan = index + arah;
            if (tujuan < 0 || tujuan >= this.modelValue.length) return;
            const daftar = [...this.modelValue];
            [daftar[index], daftar[tujuan]] = [daftar[tujuan], daftar[index]];
            this.pancarkan(daftar);
        },
        setOverride(index, nyala) {
            const daftar = [...this.modelValue];
            daftar[index] = {
                ...daftar[index],
                override: !!nyala,
                // Salinan baru tiap kali dinyalakan: satu objek jadwal yang
                // dipakai bersama membuat dua paket saling mengubah tenggat.
                schedule: nyala ? { ...(daftar[index].schedule || jadwalBaku()) } : null,
            };
            this.pancarkan(daftar);
        },
        ubahJadwal(index, kunci, nilai) {
            const daftar = [...this.modelValue];
            daftar[index] = {
                ...daftar[index],
                schedule: { ...(daftar[index].schedule || jadwalBaku()), [kunci]: nilai },
            };
            this.pancarkan(daftar);
        },
    },
};
</script>
```

- [ ] **Langkah 4: Periksa sintaksis**

```bash
node --check resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.test.js
```

(SFC `.vue` tidak bisa dicek `node --check`; ketepatannya dijamin uji di Task 9.)

- [ ] **Langkah 5: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.vue resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPaketList.test.js
git commit -m "feat(dms): komponen daftar paket aturan otomasi dengan override tenggat"
```

---

## Task 8: Layar — pratinjau per paket, riwayat induk/anak, perakitan

**Berkas:**
- Buat: `resources/js/Pages/LMS/SOP/Governance/Admin/Components/SopAturanPratinjau.vue`
- Ubah: `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.vue`
- Uji: `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.test.js` (ubah/buat)

**Antarmuka:**
- Memakai: `SopAturanPaketList` (Task 7); kontrak API dari Task 6
- `SopAturanPratinjau` props: `preview: Object` berbentuk `{ paket: [], total: {}, auto_release: Boolean }`

- [ ] **Langkah 1: Tulis uji yang gagal**

Tambahkan ke `SopKebijakan.test.js`:

```js
import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopAturanPratinjau from './Components/SopAturanPratinjau.vue';

const stubs = { HcisCard: true, HcisBadge: true, HcisTable: true, HcisAlert: true, HcisIcon: true };

describe('SopAturanPratinjau', () => {
    it('menampilkan satu baris per paket dan baris TOTAL', () => {
        const w = mount(SopAturanPratinjau, {
            props: {
                preview: {
                    paket: [
                        { Nama_Paket: 'Paket A', matched: 10, new: 4, kept: 6, covered: 0, release: 0, withheld: 0, tertahan_tenggat: false },
                        { Nama_Paket: 'Paket B', matched: 10, new: 10, kept: 0, covered: 0, release: 0, withheld: 0, tertahan_tenggat: false },
                    ],
                    total: { matched: 20, new: 14, kept: 6, covered: 0, release: 0, withheld: 0 },
                    auto_release: false,
                },
            },
            global: { stubs },
        });

        expect(w.text()).toContain('Paket A');
        expect(w.text()).toContain('Paket B');
        expect(w.find('[data-uji="pratinjau-total"]').text()).toContain('20');
    });

    it('menandai paket yang tertahan gerbang tenggat absolut', () => {
        const w = mount(SopAturanPratinjau, {
            props: {
                preview: {
                    paket: [{ Nama_Paket: 'Paket Lewat', matched: 5, new: 5, kept: 0, covered: 0, release: 0, withheld: 5, tertahan_tenggat: true }],
                    total: { matched: 5, new: 5, kept: 0, covered: 0, release: 0, withheld: 5 },
                    auto_release: false,
                },
            },
            global: { stubs },
        });

        expect(w.find('[data-uji="tertahan-tenggat"]').exists()).toBe(true);
    });
});
```

- [ ] **Langkah 2: Catat perintah ujinya (JANGAN dijalankan sekarang)**

Perintah: `npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/`

- [ ] **Langkah 3: Tulis `SopAturanPratinjau.vue`**

```vue
<template>
    <HcisCard
        variant="glass"
        sentiment="brand"
        size="md"
        title="Pratinjau Dampak"
        description="Dihitung dari data karyawan terkini, sebelum satu penugasan pun dibuat."
        icon="eye"
        class="mb-4"
    >
        <template #badge>
            <HcisBadge label="Data Real-Time" variant="brand" size="xs" />
        </template>

        <!-- Menutup kebingungan yang selama ini tak pernah dijelaskan layar:
             kolom Dicabut selalu nol selama saklar auto-release mati, dan tak
             ada satu pun kalimat yang memberi tahu di mana saklarnya. -->
        <HcisAlert
            v-if="!preview.auto_release"
            variant="info"
            title="Pencabutan otomatis sedang mati"
            message="Kolom Dicabut karena itu selalu nol dan angkanya masuk ke kolom Ditahan. Saklarnya ada di halaman Set Up System."
            icon="info-circle-fill"
            class="mb-3"
        />

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr class="text-xs text-slate-500 uppercase">
                        <th scope="col">Paket SOP</th>
                        <th scope="col" class="text-end">Cocok</th>
                        <th scope="col" class="text-end">Baru</th>
                        <th scope="col" class="text-end">Dipertahankan</th>
                        <th scope="col" class="text-end">Tercakup</th>
                        <th scope="col" class="text-end">Dicabut</th>
                        <th scope="col" class="text-end">Ditahan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="baris in preview.paket" :key="baris.Id_SOP_Paket || baris.Nama_Paket">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-truncate">{{ baris.Nama_Paket }}</span>
                                <HcisBadge
                                    v-if="baris.tertahan_tenggat"
                                    data-uji="tertahan-tenggat"
                                    label="Tertahan"
                                    variant="danger"
                                    size="xs"
                                    title="Tenggat absolut sudah lewat — paket ini tidak akan menugaskan siapa pun sampai tanggalnya diperbaiki."
                                />
                            </div>
                        </td>
                        <td class="text-end font-semibold">{{ baris.matched }}</td>
                        <td class="text-end font-semibold text-success-700">+{{ baris.new }}</td>
                        <td class="text-end">{{ baris.kept }}</td>
                        <td class="text-end">{{ baris.covered }}</td>
                        <td class="text-end text-warning-700">-{{ baris.release }}</td>
                        <td class="text-end text-danger-700">{{ baris.withheld }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr data-uji="pratinjau-total" class="border-top font-extrabold">
                        <td>TOTAL</td>
                        <td class="text-end">{{ preview.total.matched }}</td>
                        <td class="text-end text-success-700">+{{ preview.total.new }}</td>
                        <td class="text-end">{{ preview.total.kept }}</td>
                        <td class="text-end">{{ preview.total.covered }}</td>
                        <td class="text-end text-warning-700">-{{ preview.total.release }}</td>
                        <td class="text-end text-danger-700">{{ preview.total.withheld }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </HcisCard>
</template>

<script>
import { HcisAlert, HcisBadge, HcisCard } from '@/components/ui';

export default {
    name: 'SopAturanPratinjau',
    components: { HcisAlert, HcisBadge, HcisCard },
    props: {
        preview: {
            type: Object,
            required: true,
            validator: (v) => Array.isArray(v.paket) && typeof v.total === 'object',
        },
    },
};
</script>
```

- [ ] **Langkah 4: Rakit di `SopKebijakan.vue`**

1. Impor dan daftarkan `SopAturanPaketList` dan `SopAturanPratinjau`.
2. `blankForm()` — ganti `id_paket: ''` menjadi `paket: []`.
3. Seksi 1: ganti `<HcisField id="sop-id-paket">` beserta `<HcisSelect>`-nya dengan:

```html
<SopAturanPaketList v-model="form.paket" :options="availablePackages" />
```

   Kunci `:disabled="!!selectedId"` **dicabut** — keputusan 2.4 menuntut paket bisa dibuang tanpa menonaktifkan aturannya lebih dulu.

4. Seksi 4: ubah judulnya menjadi **"4. Tenggat & Pengingat Induk"** dengan keterangan *"Berlaku untuk semua paket, kecuali paket yang menyalakan tenggat sendiri."*
5. Ganti blok enam kartu angka pratinjau (baris 555-598) dengan `<SopAturanPratinjau :preview="preview" />`.
6. `configPayload()` tetap seperti sekarang; tambahkan pengiriman paket di `saveDraft()`:

```js
                const payload = {
                    nama_aturan: this.form.nama_aturan,
                    config: this.configPayload(),
                    paket: this.form.paket.map((p, i) => ({
                        id_paket: p.Id_SOP_Paket,
                        urutan: i + 1,
                        override: !!p.override,
                        schedule: p.override ? p.schedule : null,
                    })),
                };
```

   dan penjaga awalnya:

```js
            if (!this.form.paket.length || !this.form.nama_aturan) {
                hcisMessage.warning('Nama aturan dan minimal satu paket SOP wajib diisi.');
                return false;
            }
```

7. `openRule()` — isi `paket` dari `rule.Paket`:

```js
                    paket: (rule.Paket || []).map((p) => ({ ...p, schedule: p.schedule || null })),
```

8. `availablePackages` — buang paket yang sudah dipakai aturan **lain**, dan yang sudah ada di form:

```js
        availablePackages() {
            const dipakaiLain = new Set(
                this.rules
                    .filter((r) => r.Id_SOP_Assignment_Rule !== this.selectedId)
                    .flatMap((r) => (r.Paket || []).map((p) => p.Id_SOP_Paket))
            );
            const sudahDiForm = new Set(this.form.paket.map((p) => p.Id_SOP_Paket));

            return (this.options.packages || []).filter(
                (pkg) => !dipakaiLain.has(pkg.Id_SOP_Paket) && !sudahDiForm.has(pkg.Id_SOP_Paket)
            );
        },
```

9. `loadRuns()` — kontraknya kini berbentuk objek:

```js
                if (r.data.success) {
                    this.runs = r.data.result.rows || [];
                    this.runsDitampilkan = r.data.result.ditampilkan || 0;
                    this.runsTotal = r.data.result.total_induk || 0;
                }
```

   Tambahkan `runsDitampilkan: 0` dan `runsTotal: 0` ke `data()`, dan di kaki tabel riwayat:

```html
<!-- Plafon tidak boleh memotong diam-diam. -->
<small v-if="runsTotal > runsDitampilkan" class="text-slate-500">
    Menampilkan {{ runsDitampilkan }} eksekusi terakhir dari {{ runsTotal }}.
</small>
```

10. Baris riwayat anak ditampilkan menjorok: pada kolom pertama, `<span v-if="row.Is_Anak" class="ps-4">`.
11. Daftar aturan kiri: ganti `{{ rule.Nama_Paket }}` menjadi `{{ rule.Jumlah_Paket }} paket`.
12. **Buang** definisi kedua `resetRuleFilters` (baris 1280-1283) — dua definisi di satu objek `methods`, yang kedua diam-diam menang.

- [ ] **Langkah 5: Periksa sintaksis**

```bash
node --check resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.test.js
```

- [ ] **Langkah 6: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/
git commit -m "feat(dms): layar otomasi SOP menampung banyak paket dan merinci dampaknya per paket"
```

---

## Task 9: Gerbang verifikasi — jalankan seluruh uji

**Berkas:** tidak ada yang diubah kecuali perbaikan yang muncul dari hasil uji.

**Antarmuka:**
- Memakai: seluruh task sebelumnya

- [ ] **Langkah 1: Minta ACC pengguna**

Laporkan bahwa seluruh kode dan berkas uji sudah ditulis, sebutkan berapa berkas dan berapa uji, lalu minta izin menjalankan. **Jangan menjalankan apa pun sebelum jawabannya datang** — `artisan test` menembak basis data dev dan mengunci UI pengguna selama beberapa menit.

- [ ] **Langkah 2: Buktikan tiap penjaga pernah MERAH**

Untuk masing-masing penjaga di bawah, balik kondisinya sementara (komentari satu baris penjaganya), jalankan ujinya, pastikan GAGAL, lalu kembalikan:

| Penjaga | Berkas |
|---|---|
| Tumpang tindih ditolak | `SopAturanPaketServiceTest::test_paket_milik_aturan_lain_ditolak…` |
| Kegagalan sebagian | `SopAturanApplyTest::test_paket_gagal_tidak_menyeret…` |
| Override tenggat dipakai | `SopAturanApplyTest::test_override_tenggat_melahirkan_header…` |
| Hash jujur | `SopAssignmentRuleConfigTest::test_hash_berubah_ketika_daftar_paket_berubah` |
| Plafon dilaporkan | `SopRiwayatOtomasiTest::test_riwayat_melaporkan_plafon…` |

Penjaga yang tak pernah terlihat menyala bukan penjaga.

- [ ] **Langkah 3: Jalankan suite SOP penuh**

```bash
php artisan test tests/Feature/LMS/SOP tests/Unit/LMS/SOP
```

Lingkupnya penuh, bukan berkas baru saja: `impact()`, `buatHeader()`, dan `assignKeHeader()` punya pemakai lain. Repo ini pernah kena — satu penjaga baru di service bersama mematahkan 9 uji lama di 4 berkas.

Ada baseline ±19 kegagalan non-SOP yang memang sudah ada sebelumnya; bandingkan terhadap baseline, jangan terhadap nol.

- [ ] **Langkah 4: Jalankan uji frontend**

```bash
npx vitest run resources/js/Pages/LMS/SOP/Governance/Admin/
```

- [ ] **Langkah 5: Bangun**

```bash
npm run build
```

- [ ] **Langkah 6: Laporkan apa adanya**

Sebutkan jumlah lulus/gagal persis dengan keluarannya. Kalau ada yang gagal, katakan gagal — jangan bulatkan menjadi "selesai". Kalau ada bagian yang dilewati, sebutkan mana dan mengapa.

- [ ] **Langkah 7: Commit perbaikan yang muncul**

```bash
git add -A
git commit -m "fix(dms): perbaikan hasil jalan-uji otomasi multi-paket"
```

---

## Cakupan yang tidak dikerjakan rencana ini

Temuan audit halaman `/sop/admin/kebijakan` yang **tidak** disentuh, agar tidak dikira sudah beres:

| Temuan | Alasan ditunda |
|---|---|
| Aturan nonaktif mengunci paketnya (`deactivate()` tak pernah menulis `Flag_Aktif='T'`) | Sebagian terpecahkan sendiri — paket kini dilepas lewat `sinkron()`. Tetapi `deactivate()` sendiri masih tak melepas klaim paketnya; putuskan terpisah apakah menonaktifkan aturan harus membebaskan paket-paketnya. |
| Tak ada umpan balik async (202 lalu diam) | Perbaikan UX yang berdiri sendiri; tak bergantung multi-paket. |
| Tak ada endpoint hapus aturan | Belum diminta. |
| Header baru tiap scan untuk tenggat RELATIVE | Berdiri sendiri, dan bertambah relevan setelah multi-paket — layak jadi tugas berikutnya. |
