# Master Dokumen SOP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menjadikan dokumen SOP entitas terkendali tersendiri sehingga pengesahan, masa berlaku, dan rantai versinya dikelola satu kali dan berlaku di semua paket yang memakainya.

**Architecture:** Memindahkan satu foreign key. `N_SOP_Item_Versi` yang hari ini dimiliki satu `N_SOP_Paket_Item` dipindahkan kepemilikannya ke entitas baru `N_SOP_Dokumen`; `N_SOP_Paket_Item` berubah jadi *rujukan* + setelan per-paket. Kolom `N_SOP_Paket_Item.Id_Item_Versi_Aktif` sengaja dipertahankan sebagai cerminan yang disinkronkan sistem, supaya ~9 tempat baca besar berjalan tanpa disentuh.

**Tech Stack:** Laravel 10, SQL Server (sqlsrv, **tanpa RCSI**), query builder `DB::table()` (modul SOP tidak memakai Eloquent), Hashids untuk id publik, PHPUnit (`php artisan test`), Vue 3 Options API + Inertia.

**Rancangan lengkap:** `plans/sop/2026-08-07-master-dokumen-sop-design.md` — baca dulu sebelum mengeksekusi.

## Global Constraints

- Modul SOP **tidak memakai Laravel migration**. Skema ditulis sebagai berkas DDL bernomor di `plans/sop/sql/` dan dijalankan manual. Nomor berikutnya: **26A** (DDL), **26B** bila perlu DML.
- DDL **wajib idempoten** (`IF OBJECT_ID(...) IS NULL`, `IF COL_LENGTH(...) IS NULL`) — aman dijalankan berulang, mengikuti gaya `23A_sop_paket_draft_ddl.sql`.
- Semua penamaan mengikuti konvensi repo: tabel `N_SOP_PascalCase`, kolom `PascalCase`, flag `CHAR(1)` `'Y'`/`'T'`, waktu `DATETIME2(0)`, PK `BIGINT IDENTITY(1,1)`, `Row_Version ROWVERSION`.
- Setiap command data **wajib punya `--dry-run`** dan menampilkan dampak sebelum menulis (pola `SopRollupRecalc`).
- Nilai status dibaca dari `App\Services\SOP\Support\SopStatus` — **jangan hardcode** string status. `VERSI_DRAFT='DRAFT'`, `VERSI_READY='READY'`, `VERSI_PUBLISHED='PUBLISHED'`, `VERSI_OBSOLETE='OBSOLETE'`, `AKTIF='Y'`, `INAKTIF='T'`.
- Tipe ber-versi dibaca dari `App\Services\SOP\Support\SopTipeItem::VERSIONED` = `[DOKUMEN, FORM, VIDEO, KUIS]`. Tipe lain (`PARAGRAF`, `TAUTAN`, `ABSENSI`) **tidak** punya dokumen — kolomnya nullable.
- Tes mengikuti gaya `tests/Feature/LMS/SOP/SopBuilderCopyItemToPaketTest.php`: `DatabaseTransactions`, query data nyata via `DB::table()`, `markTestSkipped()` bila fixture tak ada. **Tidak ada factory** untuk modul SOP.
- Query tanggal **wajib sargable** — jangan `whereDate()`/`CAST(kolom AS DATE)` di sisi kolom (lihat catatan di `SopDocumentControlService`).
- **Jangan menulis `Id_Item_Versi_Aktif`** di luar penulis sahnya (`publish()`, `rollback()`, `activatePendingItemVersions()`) — lihat memori `sop-versi-aktif-pointer-invariant`.

---

## Peta Fase

Tiap fase menghasilkan perangkat lunak yang berjalan & teruji sendiri. **Fase 1 adalah satu-satunya yang dirinci penuh di dokumen ini**; tiap fase berikutnya mendapat dokumen rencananya sendiri saat gilirannya tiba, supaya rinciannya ditulis dengan pengetahuan hasil fase sebelumnya, bukan tebakan di muka.

| Fase | Isi | Perubahan perilaku | Bisa dirilis sendiri? |
|---|---|---|---|
| **1** | Fondasi skema + backfill 1:1 + alat verifikasi & baseline | **Nol** — kolom baru belum dibaca siapa pun | Ya |
| **2** | Baca lewat dokumen (service layer), re-key unique index | Nol (1 dokumen = 1 item, jadi hasilnya identik) — dibuktikan lawan baseline Fase 1 | Ya |
| **3** | Fan-out saat publish + pagar pengaman (ambang rekonsiliasi total, dedup notifikasi, semantik rollback) | Ada: revisi menyebar ke semua paket perujuk | Ya |
| **4** | Tulis ulang join versi→item (monitoring, `versiVisibleInPaket`, akses PDF aman) | Perbaikan kebenaran angka & otorisasi | Ya |
| **5** | Register kontrol dokumen ditumbuhkan jadi halaman Master Dokumen (kelola versi & pengesahan) | Permukaan admin baru | Ya |
| **6** | Builder memilih **Dokumen**, bukan berkas; field pengesahan pindah keluar dari form item | Alur admin berubah | Ya |
| **7** | (Tahap 2) Alat "Gabungkan Dokumen" untuk duplikat lama | Penyatuan data, diputuskan admin | Ya |

**Kenapa urutannya begini:** Fase 1–2 tidak mengubah apa pun yang dilihat pengguna, jadi kalau ada yang salah ketahuan sebelum satu pun perilaku bergeser. Fan-out (Fase 3) baru dinyalakan setelah bacaannya terbukti benar. Penggabungan data (Fase 7) paling akhir karena ia satu-satunya yang tak bisa dibalik otomatis.

**Keputusan yang masih terbuka — wajib dijawab sebelum Fase 3:** semantik `rollback()`. Hari ini rollback sengaja senyap (tak merekonsiliasi, `SopItemVersionService.php:174`). Untuk dokumen bersama, rekomendasi saya: pointer **wajib** ikut fan-out (kalau tidak, paket-paket menyimpang tanpa jejak), dan rekonsiliasi memakai jalur terantri yang sama dengan publish. Tapi ini pilihan kebijakan kepatuhan, bukan teknis — konfirmasikan dulu.

---

# FASE 1 — Fondasi Skema + Backfill 1:1

**Deliverable:** Tabel `N_SOP_Dokumen` berdiri, tiap item ber-versi punya tepat satu dokumen, tiap baris versi tertaut ke dokumen yang benar, dan tersedia alat untuk membuktikan integritasnya + baseline perilaku untuk dibandingkan di Fase 2. **Tidak ada satu baris kode pun yang membaca kolom baru** — sistem berperilaku persis sama.

---

### Task 1: DDL `N_SOP_Dokumen` + kolom rujukan

**Files:**
- Create: `plans/sop/sql/26A_sop_dokumen_ddl.sql`
- Test: `tests/Feature/LMS/SOP/SopDokumenSkemaTest.php`

**Interfaces:**
- Produces: tabel `N_SOP_Dokumen` (PK `Id_SOP_Dokumen` BIGINT); kolom `N_SOP_Item_Versi.Id_SOP_Dokumen` BIGINT NULL; kolom `N_SOP_Paket_Item.Id_SOP_Dokumen` BIGINT NULL. Task 2 & 3 bergantung pada ketiganya.

- [ ] **Step 1: Tulis tes yang gagal**

Tes ini adalah gerbangnya: DDL dijalankan manual, jadi tes inilah yang membuktikan seseorang benar-benar menjalankannya.

```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang skema Fase 1. DDL modul SOP dijalankan manual (tak ada Laravel
 * migration), jadi tes inilah satu-satunya yang membuktikan 26A sudah benar
 * dijalankan di basis data yang sedang dipakai.
 */
class SopDokumenSkemaTest extends TestCase
{
    private function kolom(string $tabel): array
    {
        return array_map(
            fn ($r) => $r->COLUMN_NAME,
            DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel])
        );
    }

    public function test_tabel_dokumen_ada_dengan_kolom_yang_dibutuhkan(): void
    {
        $kolom = $this->kolom('N_SOP_Dokumen');
        $this->assertNotEmpty($kolom, 'Tabel N_SOP_Dokumen belum ada — jalankan plans/sop/sql/26A_sop_dokumen_ddl.sql');

        foreach ([
            'Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'Judul', 'Tipe', 'Deskripsi',
            'Id_Versi_Aktif', 'Flag_Aktif', 'Created_By', 'Created_At', 'Updated_By', 'Updated_At',
        ] as $wajib) {
            $this->assertContains($wajib, $kolom, "Kolom {$wajib} hilang di N_SOP_Dokumen.");
        }
    }

    public function test_kolom_rujukan_dokumen_ditambahkan_ke_dua_tabel(): void
    {
        $this->assertContains('Id_SOP_Dokumen', $this->kolom('N_SOP_Item_Versi'),
            'N_SOP_Item_Versi.Id_SOP_Dokumen belum ada — jalankan 26A.');
        $this->assertContains('Id_SOP_Dokumen', $this->kolom('N_SOP_Paket_Item'),
            'N_SOP_Paket_Item.Id_SOP_Dokumen belum ada — jalankan 26A.');
    }

    public function test_kolom_rujukan_masih_nullable_di_fase_1(): void
    {
        // Wajib nullable: tipe PARAGRAF/TAUTAN/ABSENSI tak punya dokumen, dan
        // backfill (Task 2) harus bisa berjalan bertahap tanpa melanggar constraint.
        foreach ([['N_SOP_Item_Versi'], ['N_SOP_Paket_Item']] as [$tabel]) {
            $nullable = DB::selectOne(
                'SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$tabel, 'Id_SOP_Dokumen']
            );
            $this->assertSame('YES', $nullable->IS_NULLABLE, "{$tabel}.Id_SOP_Dokumen harus nullable di Fase 1.");
        }
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenSkemaTest.php`
Expected: FAIL — "Tabel N_SOP_Dokumen belum ada — jalankan plans/sop/sql/26A_sop_dokumen_ddl.sql"

- [ ] **Step 3: Tulis DDL**

```sql
/* ============================================================================
   SOP 26A - DDL Master Dokumen SOP

   Tujuan:
   - N_SOP_Dokumen menjadi entitas dokumen terkendali (nomor resmi, judul,
     tipe, pointer versi aktif).
   - N_SOP_Item_Versi mendapat Id_SOP_Dokumen: kepemilikan versi berpindah dari
     item ke dokumen (pemindahan datanya di command sop:dokumen-backfill).
   - N_SOP_Paket_Item mendapat Id_SOP_Dokumen: item jadi RUJUKAN ke dokumen.

   FASE 1: kedua kolom rujukan NULLABLE dan belum dibaca kode mana pun.
   Kolom lama (Id_SOP_Paket_Item di versi) SENGAJA dibiarkan utuh — ia masih
   jadi sumber kebenaran sampai Fase 2 selesai. Tak ada data yang dihapus.

   Prasyarat: SOP 25A sudah dijalankan.
   Aman dijalankan berulang kali.
   ============================================================================ */

IF OBJECT_ID('dbo.N_SOP_Dokumen', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Dokumen (
        Id_SOP_Dokumen BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Dokumen PRIMARY KEY,

        Nomor_SOP_Resmi VARCHAR(100) NULL,
        Judul NVARCHAR(255) NOT NULL,
        Tipe VARCHAR(30) NOT NULL,          -- DOKUMEN | FORM | VIDEO | KUIS
        Deskripsi NVARCHAR(MAX) NULL,

        /* Versi yang sedang berlaku. SENGAJA TANPA FOREIGN KEY: relasinya
           melingkar (dokumen -> versi -> dokumen) dan akan mengunci urutan
           insert. Konsistensinya dijaga kode + command sop:dokumen-verify,
           sama seperti N_SOP_Paket_Item.Id_Item_Versi_Aktif hari ini. */
        Id_Versi_Aktif BIGINT NULL,

        Flag_Aktif CHAR(1) NOT NULL DEFAULT ('Y'),
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL
    );
END
GO

/* Register dokumen disaring per tipe & status aktif. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Dokumen_Tipe' AND object_id = OBJECT_ID('dbo.N_SOP_Dokumen'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Dokumen_Tipe
        ON dbo.N_SOP_Dokumen (Flag_Aktif, Tipe) INCLUDE (Judul, Nomor_SOP_Resmi, Id_Versi_Aktif);
END
GO

/* ---- Kolom rujukan: versi milik dokumen ---- */
IF COL_LENGTH('dbo.N_SOP_Item_Versi', 'Id_SOP_Dokumen') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Item_Versi ADD Id_SOP_Dokumen BIGINT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Item_Versi_Dokumen')
BEGIN
    ALTER TABLE dbo.N_SOP_Item_Versi WITH CHECK
        ADD CONSTRAINT FK_N_SOP_Item_Versi_Dokumen
        FOREIGN KEY (Id_SOP_Dokumen) REFERENCES dbo.N_SOP_Dokumen (Id_SOP_Dokumen);
END
GO

/* Non-unique di Fase 1. Unique (Id_SOP_Dokumen, Versi_Ke) di-pasang pada Fase 2
   bersamaan dengan pencabutan UX_N_SOP_Item_Versi_Urut (Id_SOP_Paket_Item,
   Versi_Ke) — memasangnya sekarang akan menggagalkan 26A bila ada data tak
   terduga, padahal Fase 1 harus bisa dijalankan tanpa risiko. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Item_Versi_Dokumen' AND object_id = OBJECT_ID('dbo.N_SOP_Item_Versi'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Item_Versi_Dokumen
        ON dbo.N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke);
END
GO

/* ---- Kolom rujukan: item menunjuk dokumen ---- */
IF COL_LENGTH('dbo.N_SOP_Paket_Item', 'Id_SOP_Dokumen') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item ADD Id_SOP_Dokumen BIGINT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Paket_Item_Dokumen')
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Item WITH CHECK
        ADD CONSTRAINT FK_N_SOP_Paket_Item_Dokumen
        FOREIGN KEY (Id_SOP_Dokumen) REFERENCES dbo.N_SOP_Dokumen (Id_SOP_Dokumen);
END
GO

/* "Paket mana saja yang memakai dokumen ini" — pertanyaan inti halaman Master
   Dokumen (Fase 5) dan penentu fan-out saat publish (Fase 3). */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Paket_Item_Dokumen' AND object_id = OBJECT_ID('dbo.N_SOP_Paket_Item'))
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Paket_Item_Dokumen
        ON dbo.N_SOP_Paket_Item (Id_SOP_Dokumen, Flag_Aktif) INCLUDE (Id_SOP_Paket, Id_Item_Versi_Aktif);
END
GO
```

- [ ] **Step 4: Jalankan DDL di basis data dev, lalu tes harus LULUS**

Jalankan `plans/sop/sql/26A_sop_dokumen_ddl.sql` lewat klien SQL Server (SSMS/Azure Data Studio) pada basis data dev.
Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenSkemaTest.php`
Expected: PASS (3 tes)

- [ ] **Step 5: Commit**

```bash
git add plans/sop/sql/26A_sop_dokumen_ddl.sql tests/Feature/LMS/SOP/SopDokumenSkemaTest.php
git commit -m "feat(sop): skema Master Dokumen SOP (26A) + gerbang tes skema"
```

---

### Task 2: Command backfill 1:1

**Files:**
- Create: `app/Console/Commands/SopDokumenBackfill.php`
- Test: `tests/Feature/LMS/SOP/SopDokumenBackfillTest.php`

**Interfaces:**
- Consumes: tabel & kolom dari Task 1.
- Produces: command `sop:dokumen-backfill {--dry-run} {--paket=}`. Setelah dijalankan: tiap `N_SOP_Paket_Item` bertipe VERSIONED yang punya baris versi memiliki `Id_SOP_Dokumen`; tiap `N_SOP_Item_Versi` punya `Id_SOP_Dokumen` yang sama dengan itemnya; `N_SOP_Dokumen.Id_Versi_Aktif` = `N_SOP_Paket_Item.Id_Item_Versi_Aktif` item asalnya. **Idempoten** — menjalankan dua kali tidak membuat dokumen ganda. Task 3 memverifikasi hasilnya.

**KONVERGEN, bukan cuma idempoten — dua lintasan, dan yang kedua tak boleh dilewatkan.** Lintasan 1 hanya menyentuh item yang `Id_SOP_Dokumen`-nya masih NULL. `SopItemVersionService::create()` (dipanggil `SopBerkasController::storeVersi` & `SopAdminBuilderController`) menulis baris versi **tanpa** `Id_SOP_Dokumen`, jadi begitu admin menambah v2 pada item yang sudah di-backfill — alur revisi paling biasa — lahir baris versi lepas yang lintasan 1 tak bisa sentuh (itemnya sudah bertaut). Invarian 2 Task 3 langsung GAGAL, gerbang Fase 2 merah permanen, dan satu-satunya obat adalah SQL manual. Karena itu `handle()` punya lintasan penyembuhan **tanpa syarat**: baris versi yang itemnya sudah bertaut tapi versinya belum, ditarik ke dokumen itemnya. Ia ikut dihitung di pratinjau `--dry-run`, dan early-return baru boleh terjadi bila **kedua** backlog kosong.

**Tes wajib memanufaktur backlog-nya sendiri.** Setelah Task 5 menjalankan backfill sungguhan di dev, backlog-nya habis dan `handle()` mengambil jalur early-return tanpa menulis apa pun — tes yang cuma memanggil command lalu memeriksa keadaan tabel jadi memeriksa data warisan, bukan perilaku. Helper `buatBacklog()` mengembalikan sebagian data ke keadaan pra-backfill di dalam transaksi tes (lepaskan rujukan dulu, baru hapus dokumennya — kedua kolom punya FK), sehingga tiap tes benar-benar menjalankan lintasan tulis. Tanpa ini `test_idempoten_…` tak bisa lagi gagal untuk sebab apa pun.

**`--paket` wajib divalidasi.** `(int) 'abc'` = 0 dan `when(0)` falsy, jadi salah ketik menghapus filternya diam-diam dan command menyapu SELURUH paket — kebalikan persis dari yang diminta. Salah ketik pada command yang menulis data harus berhenti, bukan melebar.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Backfill 1:1 Fase 1 — tiap item ber-versi jadi SATU dokumen sendiri.
 * Sengaja tidak menggabungkan duplikat: nilai pengesahan untuk konten yang sama
 * sudah menyimpang di data nyata dan hanya manusia yang bisa memutuskan mana
 * yang benar (penggabungan = Fase 7).
 */
class SopDokumenBackfillTest extends TestCase
{
    use DatabaseTransactions;

    private function itemBerVersi()
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'));
    }

    /**
     * Kembalikan sebagian data ke keadaan PRA-backfill supaya command punya
     * pekerjaan sungguhan.
     *
     * KENAPA WAJIB ADA. Setelah backfill sungguhan dijalankan di dev (Task 5),
     * backlog-nya habis; handle() lalu mengambil jalur early-return dan tak
     * menulis apa pun. Tes yang cuma memanggil command lalu memeriksa keadaan
     * tabel sesudahnya jadi memeriksa DATA WARISAN, bukan perilaku command —
     * test_idempoten_… bahkan tak bisa lagi gagal untuk sebab apa pun. Dengan
     * backlog buatan ini, tiap tes kembali benar-benar menjalankan lintasan
     * tulis. Semuanya di-rollback DatabaseTransactions.
     *
     * @return array<int,int> Id_SOP_Paket_Item yang dilepas tautannya
     */
    private function buatBacklog(int $berapa = 2): array
    {
        $sasaran = $this->itemBerVersi()
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->orderBy('i.Id_SOP_Paket_Item')
            ->limit($berapa)
            ->get(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen']);

        if ($sasaran->isEmpty()) return [];

        $idItem = $sasaran->pluck('Id_SOP_Paket_Item')->map(fn ($v) => (int) $v)->all();
        $idDokumen = $sasaran->pluck('Id_SOP_Dokumen')->map(fn ($v) => (int) $v)->all();

        // Urutan penting: lepaskan dulu semua rujukan, baru hapus dokumennya —
        // kedua kolom rujukan punya FOREIGN KEY ke N_SOP_Dokumen (26A).
        DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Paket_Item', $idItem)->update(['Id_SOP_Dokumen' => null]);
        DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Paket_Item', $idItem)->update(['Id_SOP_Dokumen' => null]);
        DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $idDokumen)->delete();

        return $idItem;
    }

    /** Baris versi yang itemnya bertaut dokumen tapi versinya tidak — keadaan yang lahir dari alur revisi normal. */
    private function versiLepasCount(): int
    {
        return (int) DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNull('v.Id_SOP_Dokumen')
            ->count();
    }

    /**
     * Tiru apa yang SopItemVersionService::create() lakukan: baris versi baru
     * pada item yang SUDAH bertaut dokumen, tapi tanpa Id_SOP_Dokumen sendiri.
     *
     * @return int|null Id_SOP_Item_Versi yang dilepas, atau null bila data tak cukup
     */
    private function lepasSatuBarisVersi(): ?int
    {
        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('v.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->orderBy('v.Id_SOP_Item_Versi')
            ->first(['v.Id_SOP_Item_Versi']);

        if (!$versi) return null;

        DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Id_SOP_Dokumen' => null]);

        return (int) $versi->Id_SOP_Item_Versi;
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        // Membandingkan SEBELUM vs SESUDAH, bukan menuntut nol: data dev sudah
        // ter-backfill, jadi assertion "nol dokumen" akan selalu gagal. Yang
        // ingin dibuktikan cuma satu — dry-run tak mengubah apa pun.
        if (!$this->buatBacklog()) $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');

        // Sengaja juga melepas satu baris versi pada item yang TETAP bertaut,
        // supaya lintasan penyembuhan ikut punya pekerjaan — dry-run harus
        // menahan diri pada KEDUA lintasan, bukan cuma yang pertama.
        $versi = $this->lepasSatuBarisVersi();

        $dokumenSebelum = DB::table('N_SOP_Dokumen')->count();
        $tertautSebelum = (int) $this->itemBerVersi()->whereNotNull('i.Id_SOP_Dokumen')->count();
        $lepasSebelum = $this->versiLepasCount();

        $this->artisan('sop:dokumen-backfill --dry-run')->assertSuccessful();

        $this->assertSame($dokumenSebelum, DB::table('N_SOP_Dokumen')->count(),
            'dry-run tidak boleh membuat dokumen.');
        $this->assertSame($tertautSebelum, (int) $this->itemBerVersi()->whereNotNull('i.Id_SOP_Dokumen')->count(),
            'dry-run tidak boleh menautkan item ke dokumen.');
        $this->assertSame($lepasSebelum, $this->versiLepasCount(),
            'dry-run tidak boleh menyembuhkan baris versi lepas.');
        if ($versi !== null) {
            $this->assertNull(DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi)->value('Id_SOP_Dokumen'),
                'dry-run menulis Id_SOP_Dokumen pada baris versi.');
        }
    }

    public function test_setiap_item_berversi_dapat_tepat_satu_dokumen(): void
    {
        $harusnya = (int) $this->itemBerVersi()->count();
        if ($harusnya === 0) $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $this->assertSame(0, (int) $this->itemBerVersi()->whereNull('i.Id_SOP_Dokumen')->count(),
            'Masih ada item ber-versi tanpa dokumen.');

        // 1:1 — tak ada dokumen yang dipakai dua item pada tahap ini.
        $ganda = DB::table('N_SOP_Paket_Item')->whereNotNull('Id_SOP_Dokumen')
            ->select('Id_SOP_Dokumen')->groupBy('Id_SOP_Dokumen')
            ->havingRaw('COUNT(*) > 1')->get();
        $this->assertCount(0, $ganda, 'Fase 1 wajib 1:1 — penggabungan adalah pekerjaan Fase 7.');
    }

    public function test_setiap_baris_versi_ikut_dokumen_itemnya(): void
    {
        if ((int) $this->itemBerVersi()->count() === 0) $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $nyasar = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Dokumen')->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'i.Id_SOP_Dokumen'))
            ->count();

        $this->assertSame(0, $nyasar, 'Ada baris versi yang tak ikut dokumen itemnya.');
    }

    /**
     * KONVERGEN, bukan cuma idempoten.
     *
     * Alur revisi normal — SopItemVersionService::create(), dipanggil
     * SopBerkasController::storeVersi & SopAdminBuilderController — menulis
     * baris versi TANPA Id_SOP_Dokumen. Begitu admin menambah v2 pada item yang
     * sudah di-backfill, lahirlah keadaan ini: item bertaut, versinya tidak.
     * Lintasan 1 tak bisa menyentuhnya (ia menyaring item yang BELUM bertaut),
     * jadi tanpa lintasan penyembuhan gerbang sop:dokumen-verify merah permanen
     * dan cuma bisa dibersihkan lewat SQL manual.
     */
    public function test_menyembuhkan_baris_versi_yang_lahir_tanpa_dokumen(): void
    {
        $versi = $this->lepasSatuBarisVersi();
        if ($versi === null) $this->markTestSkipped('Tak ada baris versi ber-dokumen di data dev.');

        $this->assertSame(1, $this->versiLepasCount(), 'Prasyarat: tepat satu baris versi lepas.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $sesudah = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->where('v.Id_SOP_Item_Versi', $versi)
            ->first(['v.Id_SOP_Dokumen as versi_dok', 'i.Id_SOP_Dokumen as item_dok']);

        $this->assertNotNull($sesudah->versi_dok, 'Baris versi lepas tidak disembuhkan backfill.');
        $this->assertEquals($sesudah->item_dok, $sesudah->versi_dok,
            'Baris versi disembuhkan ke dokumen yang salah — harus mengikuti dokumen itemnya.');
        $this->assertSame(0, $this->versiLepasCount(), 'Masih ada baris versi lepas setelah backfill.');
    }

    public function test_pointer_versi_aktif_dokumen_menyalin_pointer_item(): void
    {
        if ((int) $this->itemBerVersi()->whereNotNull('i.Id_Item_Versi_Aktif')->count() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi aktif di data dev.');
        }
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $beda = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->whereColumn('d.Id_Versi_Aktif', '<>', 'i.Id_Item_Versi_Aktif')
            ->count();

        $this->assertSame(0, $beda, 'Pointer versi aktif dokumen tak sama dengan pointer item asalnya.');
    }

    public function test_identitas_dokumen_diambil_dari_itemnya(): void
    {
        // Item dipilih dari backlog buatan, dan HARUS yang berjudul: baris
        // berjudul kosong adalah satu-satunya yang menempuh fallback
        // 'Dokumen tanpa judul' (kolom Judul NOT NULL di 26A), sehingga
        // memasukkannya ke assertion identitas berarti menuntut command
        // melanggar constraint-nya sendiri. Sisi fallback diuji terpisah oleh
        // test_dokumen_selalu_punya_judul.
        $idItem = $this->buatBacklog(5);
        if (!$idItem) $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');

        $item = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Id_SOP_Paket_Item', $idItem)
            ->whereNotNull('i.Judul_Item')->where('i.Judul_Item', '<>', '')
            ->orderBy('i.Id_SOP_Paket_Item')
            ->first(['i.Id_SOP_Paket_Item', 'i.Judul_Item', 'i.Nomor_SOP_Resmi', 'i.Tipe_Item']);
        if (!$item) $this->markTestSkipped('Tak ada item ber-versi berjudul di data dev.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $dok = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->first(['d.Judul', 'd.Nomor_SOP_Resmi', 'd.Tipe', 'd.Flag_Aktif']);

        $this->assertSame($item->Judul_Item, $dok->Judul);
        $this->assertSame($item->Nomor_SOP_Resmi, $dok->Nomor_SOP_Resmi);
        $this->assertSame($item->Tipe_Item, $dok->Tipe);
        $this->assertSame(SopStatus::AKTIF, $dok->Flag_Aktif);
    }

    /** N_SOP_Dokumen.Judul NOT NULL — item tak berjudul pun harus menghasilkan dokumen sah. */
    public function test_dokumen_selalu_punya_judul(): void
    {
        if (!$this->buatBacklog(5)) $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $kosong = DB::table('N_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('Judul')->orWhere('Judul', ''))
            ->count();

        $this->assertSame(0, $kosong, 'Ada dokumen tanpa judul — kolomnya NOT NULL.');
    }

    public function test_tipe_tanpa_versi_tidak_diberi_dokumen(): void
    {
        if ((int) $this->itemBerVersi()->count() === 0) $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        $this->buatBacklog();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $salah = DB::table('N_SOP_Paket_Item')
            ->whereNotIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNotNull('Id_SOP_Dokumen')->count();

        $this->assertSame(0, $salah, 'PARAGRAF/TAUTAN/ABSENSI tak boleh punya dokumen.');
    }

    public function test_idempoten_dijalankan_dua_kali_tidak_menggandakan(): void
    {
        if (!$this->buatBacklog()) $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();
        $setelahSekali = DB::table('N_SOP_Dokumen')->count();

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $this->assertSame($setelahSekali, DB::table('N_SOP_Dokumen')->count(),
            'Backfill kedua membuat dokumen baru — tidak idempoten.');
    }

    /**
     * `--paket=abc` dulu di-cast jadi 0, `when(0)` falsy, filternya lenyap, dan
     * command menyapu SELURUH paket — kebalikan dari yang diminta operator.
     */
    public function test_paket_bukan_angka_ditolak_dan_tidak_menulis(): void
    {
        if (!$this->buatBacklog()) $this->markTestSkipped('Tak ada item ber-versi ber-dokumen untuk dijadikan backlog.');

        $sebelum = DB::table('N_SOP_Dokumen')->count();

        $this->artisan('sop:dokumen-backfill --paket=abc')->assertExitCode(1);
        $this->artisan('sop:dokumen-backfill --paket=0')->assertExitCode(1);

        $this->assertSame($sebelum, DB::table('N_SOP_Dokumen')->count(),
            '--paket tak valid harus berhenti, bukan menyapu semua paket.');
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenBackfillTest.php`
Expected: FAIL — command `sop:dokumen-backfill` belum ada ("The command ... does not exist").

- [ ] **Step 3: Tulis command**

```php
<?php

namespace App\Console\Commands;

use App\Services\SOP\Support\SopStatus;
use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill Fase 1 Master Dokumen SOP — tiap item ber-versi menjadi SATU dokumen.
 *
 * SENGAJA 1:1, TIDAK menggabungkan duplikat. Satu berkas yang dipakai banyak
 * paket hari ini menyimpan pengesahan yang sudah MENYIMPANG (konten#80: dua
 * Tanggal_Kadaluarsa berbeda; konten#173: Nomor_Pengesahan, Tanggal_Berlaku, dan
 * Disahkan_Oleh semuanya berbeda). Mesin tak bisa memilih mana yang benar.
 * Penggabungan adalah keputusan admin yang tercatat — Fase 7.
 *
 * Item nonaktif (Flag_Aktif='T') IKUT di-backfill selama ia punya baris versi,
 * supaya tak ada versi yang jadi yatim dan riwayat progres lama tetap tertelusur.
 *
 * DUA LINTASAN, dan lintasan kedua itu yang membuatnya KONVERGEN — bukan sekadar
 * idempoten. Lintasan 1 hanya menyentuh item yang belum punya dokumen; lintasan 2
 * menyembuhkan baris versi yang itemnya SUDAH punya dokumen tapi versinya belum
 * ikut. Tanpa lintasan 2, alur revisi normal jadi ranjau: SopItemVersionService::create()
 * (dipanggil SopBerkasController::storeVersi & SopAdminBuilderController) menulis
 * baris versi TANPA Id_SOP_Dokumen, jadi begitu admin menambah v2 pada item mana
 * pun yang sudah di-backfill, invarian 2 sop:dokumen-verify langsung GAGAL — dan
 * lintasan 1 tak bisa memperbaikinya karena itemnya sudah tertaut. Gerbang Fase 2
 * akan merah permanen tanpa obat selain SQL manual.
 *
 * SELALU jalankan --dry-run lebih dulu.
 */
class SopDokumenBackfill extends Command
{
    protected $signature = 'sop:dokumen-backfill
        {--dry-run : Tampilkan rencana tanpa menulis apa pun}
        {--paket= : Batasi ke satu Id_SOP_Paket}';

    protected $description = 'Backfill 1:1 item ber-versi SOP menjadi entitas N_SOP_Dokumen';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        /* --paket=abc di-cast jadi 0 dan `when(0)` bernilai falsy, sehingga
           filternya diam-diam hilang dan command menyapu SELURUH paket —
           kebalikan persis dari yang diminta operator. Salah ketik pada
           command yang menulis data harus berhenti, bukan melebar. */
        $paketMentah = $this->option('paket');
        if ($paketMentah !== null && $paketMentah !== '') {
            if (!ctype_digit((string) $paketMentah) || (int) $paketMentah < 1) {
                $this->error("--paket harus bilangan bulat positif (Id_SOP_Paket), diberi: {$paketMentah}");
                return self::FAILURE;
            }
            $paket = (int) $paketMentah;
        } else {
            $paket = null;
        }

        $items = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('i.Id_SOP_Dokumen')
            ->when($paket, fn ($q) => $q->where('i.Id_SOP_Paket', $paket))
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->orderBy('i.Id_SOP_Paket_Item')
            ->get([
                'i.Id_SOP_Paket_Item', 'i.Id_SOP_Paket', 'i.Judul_Item', 'i.Nomor_SOP_Resmi',
                'i.Tipe_Item', 'i.Deskripsi_Item', 'i.Id_Item_Versi_Aktif', 'i.Created_By',
            ]);

        // Lintasan 2 (penyembuhan): baris versi yang itemnya SUDAH bertaut tapi
        // versinya belum. Dihitung di sini supaya dry-run melaporkannya juga.
        $versiLepas = $this->versiLepas($paket)->get(['v.Id_SOP_Item_Versi', 'i.Id_SOP_Dokumen']);

        if ($items->isEmpty() && $versiLepas->isEmpty()) {
            $this->info('Tidak ada item ber-versi yang perlu di-backfill dan tak ada baris versi lepas — semua sudah tertaut.');
            return self::SUCCESS;
        }

        $awalan = $dryRun ? '[DRY-RUN] ' : '';
        $this->info($awalan . "Akan membuat {$items->count()} dokumen (1 per item ber-versi).");
        $this->info($awalan . "Akan menyembuhkan {$versiLepas->count()} baris versi yang itemnya sudah bertaut dokumen.");

        if ($items->isNotEmpty()) {
            $this->table(
                ['Item', 'Paket', 'Tipe', 'Judul'],
                $items->take(15)->map(fn ($i) => [
                    $i->Id_SOP_Paket_Item, $i->Id_SOP_Paket, $i->Tipe_Item,
                    mb_strimwidth((string) $i->Judul_Item, 0, 50, '…'),
                ])->all()
            );
            if ($items->count() > 15) $this->line('… dan ' . ($items->count() - 15) . ' lainnya.');
        }

        if ($dryRun) {
            $this->warn('Dry-run: tak ada yang ditulis. Jalankan tanpa --dry-run untuk menerapkan.');
            return self::SUCCESS;
        }

        $dibuat = 0;
        foreach ($items as $it) {
            DB::transaction(function () use ($it, &$dibuat) {
                $idDokumen = DB::table('N_SOP_Dokumen')->insertGetId([
                    'Nomor_SOP_Resmi' => $it->Nomor_SOP_Resmi,
                    // N_SOP_Dokumen.Judul NOT NULL (26A), sementara Judul_Item
                    // nullable. Fallback ini HANYA untuk judul kosong/NULL;
                    // untuk judul terisi, dokumen menyalinnya apa adanya — itu
                    // yang diuji test_identitas_dokumen_diambil_dari_itemnya,
                    // dan test_dokumen_selalu_punya_judul menjaga sisi NOT NULL-nya.
                    'Judul'           => self::judulDokumen($it->Judul_Item),
                    'Tipe'            => $it->Tipe_Item,
                    'Deskripsi'       => $it->Deskripsi_Item,
                    'Id_Versi_Aktif'  => $it->Id_Item_Versi_Aktif,
                    'Flag_Aktif'      => SopStatus::AKTIF,
                    'Created_By'      => $it->Created_By,
                    'Created_At'      => now(),
                ], 'Id_SOP_Dokumen');

                DB::table('N_SOP_Paket_Item')
                    ->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)
                    ->update(['Id_SOP_Dokumen' => $idDokumen]);

                DB::table('N_SOP_Item_Versi')
                    ->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)
                    ->update(['Id_SOP_Dokumen' => $idDokumen]);

                $dibuat++;
            });
        }

        // Lintasan 2 — penyembuhan. Dikelompokkan per dokumen supaya jumlah
        // UPDATE-nya sebanyak dokumen terdampak, bukan sebanyak baris versi.
        $disembuhkan = 0;
        foreach ($versiLepas->groupBy('Id_SOP_Dokumen') as $idDokumen => $baris) {
            $ids = $baris->pluck('Id_SOP_Item_Versi')->all();
            $disembuhkan += DB::table('N_SOP_Item_Versi')
                ->whereIn('Id_SOP_Item_Versi', $ids)
                ->update(['Id_SOP_Dokumen' => (int) $idDokumen]);
        }

        $this->info("Selesai: {$dibuat} dokumen dibuat & ditautkan, {$disembuhkan} baris versi lepas disembuhkan.");
        $this->line('Jalankan `php artisan sop:dokumen-verify` untuk memastikan integritasnya.');

        return self::SUCCESS;
    }

    /**
     * Baris versi yang itemnya sudah punya dokumen tapi versinya belum ikut.
     * Sengaja TANPA saringan Tipe_Item: bila tipe item pernah berubah menjauh
     * dari tipe ber-versi, baris versinya tetap harus ikut dokumen item itu —
     * dan invarian 2 sop:dokumen-verify memang menagihnya tanpa saringan tipe.
     */
    private function versiLepas(?int $paket)
    {
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNull('v.Id_SOP_Dokumen')
            ->when($paket, fn ($q) => $q->where('i.Id_SOP_Paket', $paket))
            ->orderBy('v.Id_SOP_Item_Versi');
    }

    /** Judul dokumen = judul item; fallback hanya bila item tak berjudul (kolom NOT NULL). */
    public static function judulDokumen(?string $judulItem): string
    {
        return trim((string) $judulItem) !== '' ? $judulItem : 'Dokumen tanpa judul';
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan LULUS**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenBackfillTest.php`
Expected: PASS (10 tes)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SopDokumenBackfill.php tests/Feature/LMS/SOP/SopDokumenBackfillTest.php
git commit -m "feat(sop): command backfill 1:1 item ber-versi menjadi dokumen"
```

---

### Task 3: Command verifikasi integritas

**Files:**
- Create: `app/Console/Commands/SopDokumenVerify.php`
- Test: `tests/Feature/LMS/SOP/SopDokumenVerifyTest.php`

**Interfaces:**
- Consumes: hasil Task 2.
- Produces: command `sop:dokumen-verify` — **baca-saja**, exit code `0` bila semua invarian terpenuhi, `1` bila ada pelanggaran. Dipakai sebagai gerbang sebelum Fase 2 dimulai dan sebagai alat audit berkelanjutan.

Invarian yang diperiksa:
1. Tiap item bertipe VERSIONED yang punya baris versi wajib punya `Id_SOP_Dokumen`.
2. Tiap baris `N_SOP_Item_Versi` yang itemnya sudah punya dokumen wajib punya `Id_SOP_Dokumen` yang sama.
3. Tipe tanpa versi tak boleh punya `Id_SOP_Dokumen`.
4. `N_SOP_Dokumen.Id_Versi_Aktif` (bila terisi) wajib menunjuk baris versi milik dokumen itu sendiri.
5. Tak ada dokumen yatim (tak dirujuk item mana pun).

Ditambah **satu baris INFORMASI, bukan invarian** — ia tak menaikkan hitungan pelanggaran dan tak mengubah exit code:

6. Jumlah baris `N_SOP_Item_Versi` yang `Id_SOP_Dokumen`-nya NULL.

Kelima invarian di atas semuanya bertumpu pada item: yang diperiksa adalah baris versi yang **itemnya** punya dokumen. Baris versi yang itemnya juga tak punya dokumen — item yang `Tipe_Item`-nya kemudian diubah menjauh dari tipe ber-versi, atau versi yatim sungguhan — tak terlihat oleh satu pun di antaranya, dan itu **sah** di Fase 1 (di dev: 18 dari 54 baris). Angkanya tetap wajib dilaporkan karena Fase 2 me-rekey `UX_N_SOP_Item_Versi_Urut` ke `(Id_SOP_Dokumen, Versi_Ke)`, dan **SQL Server menganggap NULL SETARA di unique index**: baris `(NULL, 1)` yang kedua ditolak dengan error 2601. Selama angka ini > 0, Fase 2 harus memilih index **TERFILTER** (`WHERE Id_SOP_Dokumen IS NOT NULL`) secara sadar — bukan menemukannya saat DDL gagal.

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature;

use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenVerifyTest extends TestCase
{
    use DatabaseTransactions;

    /** Predikat "item ber-versi yang punya baris versi" — sengaja DIULANG, tak dibagi pakai dengan command yang diperiksa. */
    private function itemBerVersiCount(): int
    {
        return (int) DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->count();
    }

    public function test_lulus_setelah_backfill_bersih(): void
    {
        if ($this->itemBerVersiCount() === 0) $this->markTestSkipped('Tak ada item ber-versi di data dev.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();
        $this->artisan('sop:dokumen-verify')->assertExitCode(0);
    }

    /**
     * Baris versi tanpa dokumen (mis. item yang Tipe_Item-nya diubah menjauh
     * dari tipe ber-versi) TAK terlihat oleh kelima invarian dan memang sah di
     * Fase 1 — tapi angkanya menentukan apakah Fase 2 wajib memakai unique
     * index TERFILTER, karena SQL Server menganggap NULL setara. Karena itu ia
     * dilaporkan sebagai INFO: tak menaikkan pelanggaran, tak mengubah exit code.
     */
    public function test_melaporkan_baris_versi_tanpa_dokumen_tanpa_menggagalkan(): void
    {
        if ($this->itemBerVersiCount() === 0) $this->markTestSkipped('Tak ada item ber-versi di data dev.');

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $jumlah = DB::table('N_SOP_Item_Versi')->whereNull('Id_SOP_Dokumen')->count();

        $this->artisan('sop:dokumen-verify')
            ->expectsOutputToContain("Baris versi tanpa dokumen (di luar kelima invarian): {$jumlah}")
            ->assertExitCode(0);
    }

    public function test_menangkap_baris_versi_yang_tak_ikut_dokumen_itemnya(): void
    {
        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $versi = DB::table('N_SOP_Item_Versi')->whereNotNull('Id_SOP_Dokumen')->first();
        if (!$versi) $this->markTestSkipped('Tak ada baris versi ber-dokumen di data dev.');

        // Rusak dengan sengaja — di-rollback otomatis oleh DatabaseTransactions.
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Id_SOP_Dokumen' => null]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }

    public function test_menangkap_pointer_versi_aktif_milik_dokumen_lain(): void
    {
        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $dok = DB::table('N_SOP_Dokumen')->whereNotNull('Id_Versi_Aktif')->first();
        $versiAsing = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', '<>', $dok->Id_SOP_Dokumen ?? 0)->first();
        if (!$dok || !$versiAsing) $this->markTestSkipped('Data dev tak cukup untuk kasus ini.');

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->update(['Id_Versi_Aktif' => $versiAsing->Id_SOP_Item_Versi]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }

    public function test_menangkap_pointer_versi_aktif_menggantung(): void
    {
        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $dok = DB::table('N_SOP_Dokumen')->whereNotNull('Id_Versi_Aktif')->first();
        if (!$dok) $this->markTestSkipped('Tak ada dokumen ber-Id_Versi_Aktif di data dev.');

        $idTakAda = (int) DB::table('N_SOP_Item_Versi')->max('Id_SOP_Item_Versi') + 1000;

        // Rusak dengan sengaja — di-rollback otomatis oleh DatabaseTransactions.
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->update(['Id_Versi_Aktif' => $idTakAda]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenVerifyTest.php`
Expected: FAIL — command `sop:dokumen-verify` belum ada.

- [ ] **Step 3: Tulis command**

```php
<?php

namespace App\Console\Commands;

use App\Services\SOP\Support\SopTipeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa integritas Master Dokumen SOP — BACA-SAJA, tak pernah menulis.
 *
 * Dipakai sebagai gerbang: Fase 2 (memindahkan pembacaan ke dokumen) tak boleh
 * dimulai sebelum command ini keluar dengan kode 0.
 */
class SopDokumenVerify extends Command
{
    protected $signature = 'sop:dokumen-verify';
    protected $description = 'Periksa integritas tautan dokumen SOP (baca-saja)';

    public function handle(): int
    {
        $pelanggaran = 0;

        $itemTanpaDokumen = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('i.Id_SOP_Dokumen')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->count();
        $pelanggaran += $this->lapor('Item ber-versi tanpa dokumen', $itemTanpaDokumen);

        $versiNyasar = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Dokumen')
                ->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'i.Id_SOP_Dokumen'))
            ->count();
        $pelanggaran += $this->lapor('Baris versi tak ikut dokumen itemnya', $versiNyasar);

        $tipeSalah = DB::table('N_SOP_Paket_Item')
            ->whereNotIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNotNull('Id_SOP_Dokumen')->count();
        $pelanggaran += $this->lapor('Tipe tanpa versi tapi punya dokumen', $tipeSalah);

        $pointerAsing = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->whereNotNull('d.Id_Versi_Aktif')
            ->where(fn ($q) => $q->whereNull('v.Id_SOP_Item_Versi')   // pointer menggantung (id tak ada)
                ->orWhereNull('v.Id_SOP_Dokumen')                      // versi belum ikut dokumen mana pun
                ->orWhereColumn('v.Id_SOP_Dokumen', '<>', 'd.Id_SOP_Dokumen')) // versi milik dokumen lain
            ->count();
        $pelanggaran += $this->lapor('Pointer versi aktif menggantung/tanpa dokumen/milik dokumen lain', $pointerAsing);

        $yatim = DB::table('N_SOP_Dokumen as d')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen'))
            ->count();
        $pelanggaran += $this->lapor('Dokumen yatim (tak dirujuk item mana pun)', $yatim);

        /*
         * BARIS INFORMASI — BUKAN invarian. Sengaja tak menambah $pelanggaran
         * dan tak mengubah exit code.
         *
         * Kelima invarian di atas semuanya bertumpu pada item: yang diperiksa
         * adalah baris versi yang ITEMNYA punya dokumen. Baris versi yang
         * itemnya juga tak punya dokumen — mis. item yang Tipe_Item-nya kemudian
         * diubah menjauh dari tipe ber-versi, atau versi yatim sungguhan — tak
         * terlihat oleh satu pun di antaranya, dan itu SAH di Fase 1.
         *
         * Angkanya tetap wajib dilaporkan karena Fase 2 me-rekey
         * UX_N_SOP_Item_Versi_Urut ke (Id_SOP_Dokumen, Versi_Ke), dan SQL Server
         * menganggap NULL SETARA di unique index: baris (NULL, 1) yang kedua
         * akan ditolak dengan error 2601. Selama angka ini > 0, Fase 2 HARUS
         * memilih index TERFILTER (WHERE Id_SOP_Dokumen IS NOT NULL) secara
         * sadar — bukan menemukannya saat DDL gagal.
         */
        $versiTanpaDokumen = DB::table('N_SOP_Item_Versi')->whereNull('Id_SOP_Dokumen')->count();
        $this->line("  <fg=cyan>INFO</> Baris versi tanpa dokumen (di luar kelima invarian): {$versiTanpaDokumen}");
        if ($versiTanpaDokumen > 0) {
            $this->line('       Fase 2 wajib memakai unique index TERFILTER (WHERE Id_SOP_Dokumen IS NOT NULL) —'
                . ' SQL Server menganggap NULL setara, jadi index polos akan gagal (2601).');
        }

        if ($pelanggaran === 0) {
            $this->info('Integritas dokumen SOP: BERSIH.');
            return self::SUCCESS;
        }

        $this->error("Ditemukan {$pelanggaran} jenis pelanggaran. JANGAN lanjut ke Fase 2 sebelum bersih.");
        return self::FAILURE;
    }

    private function lapor(string $label, int $jumlah): int
    {
        if ($jumlah === 0) {
            $this->line("  <fg=green>OK</>   {$label}: 0");
            return 0;
        }
        $this->line("  <fg=red>GAGAL</> {$label}: {$jumlah}");
        return 1;
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan LULUS**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenVerifyTest.php`
Expected: PASS (5 tes)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SopDokumenVerify.php tests/Feature/LMS/SOP/SopDokumenVerifyTest.php
git commit -m "feat(sop): command verifikasi integritas tautan dokumen"
```

---

### Task 4: Baseline perilaku untuk membuktikan nol regresi di Fase 2

**Files:**
- Create: `app/Console/Commands/SopDokumenBaseline.php`
- Test: `tests/Feature/LMS/SOP/SopDokumenBaselineTest.php`

**Interfaces:**
- Consumes: layanan baca yang sudah ada — `SopProgressReadService::detailBundle()`, `SopDocumentControlService::ringkasan()`, `SopPaketVersionService::releasedSnapshot()`.
- Produces: command `sop:dokumen-baseline {--simpan=} {--bandingkan=}`. Menulis berkas JSON berisi sidik jari keluaran baca untuk **seluruh** paket. Fase 2 menjalankannya lagi dengan `--bandingkan=` untuk membuktikan keluarannya identik.

**Kenapa task ini ada:** rancangan mensyaratkan "Tahap 1 wajib membuktikan nol regresi". Tanpa alat ini, "nol regresi" hanya klaim. Dengan alat ini, ia terbukti.

**Cakupan & yang sengaja ditunda.** Baseline meliputi `detailBundle` (jalur baca karyawan), `releasedSnapshot` (rilis resmi), dan register kontrol dokumen. **Export rinci sengaja belum masuk**: ia menjoin lewat `i.Id_Item_Versi_Aktif` (pointer), dan Fase 1 tak menyentuh pointer sama sekali — jadi ia mustahil bergeser di fase ini. Export rinci **wajib** masuk baseline Fase 2, saat pembacaan versi benar-benar berpindah. Ditulis di sini supaya kelalaian tak menyamar jadi keputusan.

**Jebakan cache — wajib ditangani.** `SopDocumentControlService::ringkasan()` menyimpan hasilnya di `Cache::store('database')` selama 600 detik. Bila cache tak dibuang, perbandingan sebelum/sesudah akan membandingkan dua nilai cache yang sama dan melaporkan "identik" **tanpa pernah benar-benar memeriksa** — positif palsu yang lebih berbahaya daripada tak ada pemeriksaan sama sekali. Command di bawah karena itu membuang kuncinya lebih dulu, lewat `SopDocumentControlService::kunciRingkasan()` — bukan string yang disusun ulang, supaya kuncinya tak bisa menyimpang dari yang dipakai service.

**Empat jebakan lain, semuanya sudah pernah menggigit — jangan diulang.**

1. **`bypassGate` tak boleh tanpa syarat, dan syaratnya BUKAN `$kode === null`.** `detailBundle()` memakai `bypassGate` untuk memutuskan apakah menempuh jalur SNAPSHOT RILIS RESMI (`SopProgressReadService.php:149`) atau jalur tabel aktif; mem-bypass semua paket mematikan cabang snapshot untuk seluruh baseline. Tapi menyaring dengan `$kode === null` juga salah: `$kode` hanya menyaring `d.Flag_Aktif`, sedangkan `paketBolehDiakses()` menuntut Status `PUBLISHED`, paket aktif, dan — untuk paket `TERBATAS` — `Flag_Cabut_Akses='T'`. Paket ARCHIVED yang masih memegang penugasan aktif, atau paket TERBATAS yang penerima pertamanya dicabut aksesnya, menghasilkan `$kode` **bukan** null tapi gerbangnya menolak → `detailBundle()` mengembalikan null → sidik jarinya diam-diam menciut jadi snapshot saja, dan ia tetap unik sehingga pemeriksaan "hash berbeda-beda" buta terhadapnya. Yang benar: tanyakan langsung ke gerbangnya — `$boleh = $pemanggil !== null && $progres->paketBolehDiakses($pemanggil, $idPaket);` lalu `bypassGate: !$boleh`. `paketBolehDiakses()` sudah public; pakai apa adanya, jangan salin aturannya.
2. **Cakupan kosong tak boleh menyamar jadi cakupan.** Daftar `bundle_null` dicetak saat `--simpan`. Pelajaran round 1: "12 sidik jari unik" tak membuktikan apa pun — jalur fallback tetap menghasilkan isi berbeda per paket, jadi hash terlihat sehat sementara satu cabang kode mati. **Sidik jari bervariasi ≠ lengkap.**
3. **Register kontrol dokumen, bukan cuma 8 angka ringkasannya.** `register()` adalah jalur baca paling erat terikat pada join yang ditulis ulang Fase 2 (`baseVersiAktif()` men-join item↔versi DUA KALI). Regresi yang menukar baris antar paket, menghilangkan satu sambil menggandakan satu lagi di ember yang sama, atau salah meng-encode Hashid, mempertahankan kedelapan agregat utuh dan akan dilaporkan "Identik. Nol regresi." Isinya karena itu ikut di-hash. **Urutannya dinormalisasi** (disortir stabil sebelum di-hash): `ORDER BY`-nya `(CASE WHEN Tanggal_Kadaluarsa IS NULL, Tanggal_Kadaluarsa)` tanpa pemecah seri, dan di dev 27 dari 28 baris berbagi nilai yang sama — mengunci urutan ke dalam hash memproduksi "BERUBAH" palsu tiap kali rencana kueri bergeser. Urutan aslinya disimpan di luar hash dan dilaporkan sebagai **peringatan**. Semangatnya sama persis dengan pembuangan query signed pada `Cover_Url`: yang non-deterministik dibuang, yang bermakna dipertahankan.
4. **Nilai yang bukan keluaran baca disimpan DI LUAR hash, lalu dijadikan peringatan.** `diambil_pada` (nilai turunan tanggal bergeser lintas hari), `probe_kode`/`via_probe` (roster bergeser), `kode_per_paket` (penugasan ditambah/dicabut mengganti pemanggil). Ketiganya bisa menggeser sidik jari tanpa ada regresi; operator harus bisa membedakannya. Bidangnya **tidak** dibuang dari hash — itu justru membutakan deteksi — yang ditambahkan adalah peringatannya.

**Tes wajib membuktikan sidik jarinya terikat pada DATA.** Mengedit string JSON dengan tangan hanya membuktikan pembandingnya bekerja — ia lulus persis sama seandainya `ambil()` mengembalikan null untuk setiap paket. Karena itu ada dua tes yang memutasi data nyata di dalam transaksi: `Judul_Item` pada paket yang bundle-nya **bukan** null (pilih lewat daftar `bundle_null`), dan `Tanggal_Kadaluarsa` pada versi aktif (menutupi jalur register).

- [ ] **Step 1: Tulis tes yang gagal**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private string $berkas;

    protected function setUp(): void
    {
        parent::setUp();
        // Symfony Console's StringInput (used by $this->artisan() when given a
        // single command string) treats backslash as a shell-style escape
        // character, mangling native Windows paths from storage_path(). Forward
        // slashes are accepted by the Windows filesystem APIs and survive
        // StringInput's tokenizer intact, so normalize before interpolating.
        $this->berkas = str_replace('\\', '/', storage_path('app/test-baseline-dokumen.json'));
        @unlink($this->berkas);
    }

    protected function tearDown(): void
    {
        @unlink($this->berkas);
        parent::tearDown();
    }

    public function test_menyimpan_sidik_jari_untuk_setiap_paket(): void
    {
        $jumlahPaket = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count();
        if ($jumlahPaket === 0) $this->markTestSkipped('Tak ada paket aktif di data dev.');

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();

        $this->assertFileExists($this->berkas);
        $isi = json_decode(file_get_contents($this->berkas), true);
        $this->assertCount($jumlahPaket, $isi['paket'] ?? [], 'Baseline harus mencakup semua paket aktif.');
    }

    public function test_membandingkan_dengan_dirinya_sendiri_selalu_identik(): void
    {
        if (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count() === 0) {
            $this->markTestSkipped('Tak ada paket aktif di data dev.');
        }

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(0);
    }

    public function test_mendeteksi_perbedaan(): void
    {
        if (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count() === 0) {
            $this->markTestSkipped('Tak ada paket aktif di data dev.');
        }

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();

        // Rusak sidik jarinya — pembanding wajib menyadarinya.
        $isi = json_decode(file_get_contents($this->berkas), true);
        $kunciPertama = array_key_first($isi['paket']);
        $isi['paket'][$kunciPertama]['sidik'] = 'sengaja-diubah';
        file_put_contents($this->berkas, json_encode($isi));

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }

    /**
     * Dua tes di bawah menguji hal yang tak diuji test_mendeteksi_perbedaan:
     * bahwa sidik jarinya benar-benar TERIKAT PADA DATA. Mengedit string JSON
     * dengan tangan hanya membuktikan pembandingnya bekerja — ia akan lulus
     * persis sama seandainya ambil() mengembalikan null untuk setiap paket.
     */
    public function test_sidik_jari_bereaksi_pada_perubahan_data_paket(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $isi = json_decode(file_get_contents($this->berkas), true);

        // Paket yang bundle-nya BUKAN null — kalau tidak, perubahan judul item
        // takkan pernah sampai ke sidik jarinya dan tesnya hijau tanpa arti.
        $berisi = array_values(array_diff(array_keys($isi['paket']), $isi['bundle_null'] ?? []));
        if (!$berisi) $this->markTestSkipped('Semua paket ber-bundle null — tak ada cakupan untuk diuji.');

        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', $berisi)
            ->orderBy('Id_SOP_Paket_Item')
            ->first(['Id_SOP_Paket_Item', 'Judul_Item']);
        if (!$item) $this->markTestSkipped('Paket ber-bundle tak punya item.');

        // Di-rollback otomatis oleh DatabaseTransactions.
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->update(['Judul_Item' => 'JUDUL UJI SIDIK JARI ' . uniqid()]);

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }

    public function test_sidik_jari_bereaksi_pada_perubahan_register_kontrol_dokumen(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $isi = json_decode(file_get_contents($this->berkas), true);

        if (($isi['kontrol_register']['jumlah'] ?? 0) === 0) {
            $this->markTestSkipped('Register kontrol dokumen kosong di data dev.');
        }

        // Versi AKTIF (i.Id_Item_Versi_Aktif) — hanya itu yang masuk register.
        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
                    ->on('i.Id_Item_Versi_Aktif', '=', 'v.Id_SOP_Item_Versi');
            })
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Flag_Aktif', 'Y')->where('p.Flag_Aktif', 'Y')->where('p.Status', 'PUBLISHED')
            ->orderBy('v.Id_SOP_Item_Versi')
            ->first(['v.Id_SOP_Item_Versi']);
        if (!$versi) $this->markTestSkipped('Tak ada versi aktif terbit di data dev.');

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Tanggal_Kadaluarsa' => '2019-01-31']);

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Jalankan tes, pastikan GAGAL**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenBaselineTest.php`
Expected: FAIL — command `sop:dokumen-baseline` belum ada.

- [ ] **Step 3: Tulis command**

```php
<?php

namespace App\Console\Commands;

use App\Services\SOP\SopDocumentControlService;
use App\Services\SOP\SopPaketVersionService;
use App\Services\SOP\SopProgressReadService;
use App\Services\SOP\Support\SopStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sidik jari keluaran baca SOP — bukti "nol regresi" untuk Fase 2.
 *
 * Fase 1 tak mengubah perilaku apa pun; Fase 2 memindahkan pembacaan versi ke
 * dokumen dan HARUS menghasilkan keluaran yang sama persis. Tanpa baseline yang
 * diambil SEBELUM perubahan, klaim itu tak bisa dibuktikan — hanya diyakini.
 *
 * Command ini sendiri tak menerbitkan satu pun INSERT/UPDATE/DELETE. Tapi
 * detailBundle() — layanan baca yang dipanggilnya — bisa menulis sebagai efek
 * samping: reconcileFinalQuizAttempts() menuntaskan progres kuis yang sudah
 * mentok percobaan tapi belum difinalkan (self-healing di jalur baca). Itu
 * sebabnya tesnya berjalan di dalam transaksi — supaya efek samping ini tak
 * pernah tersimpan permanen ke data dev.
 */
class SopDokumenBaseline extends Command
{
    protected $signature = 'sop:dokumen-baseline
        {--simpan= : Tulis baseline ke berkas JSON}
        {--bandingkan= : Bandingkan keadaan sekarang dengan berkas baseline}';

    protected $description = 'Ambil/bandingkan sidik jari keluaran baca SOP (baca-saja)';

    public function handle(
        SopProgressReadService $progres,
        SopDocumentControlService $kontrol,
        SopPaketVersionService $versiPaket,
    ): int {
        $simpan = $this->option('simpan');
        $bandingkan = $this->option('bandingkan');

        if (!$simpan && !$bandingkan) {
            $this->error('Wajib salah satu: --simpan= atau --bandingkan=');
            return self::FAILURE;
        }

        $sekarang = $this->ambil($progres, $kontrol, $versiPaket);

        if ($simpan) {
            // Baseline harus hidup lebih lama dari Fase 1 (Fase 2 membandingkannya),
            // jadi ia ditulis ke lokasi ter-track — bukan storage/, yang di-gitignore.
            if (!is_dir(dirname($simpan))) mkdir(dirname($simpan), 0775, true);
            file_put_contents($simpan, json_encode($sekarang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Baseline disimpan: ' . $simpan);
            $this->line('Paket tercakup: ' . count($sekarang['paket']));
            $this->line('Baris register kontrol dokumen tercakup: ' . $sekarang['kontrol_register']['jumlah']);

            /* Cakupan kosong tak boleh menyamar jadi cakupan. Paket yang
               detailBundle()-nya null tetap punya sidik jari unik (dari
               'snapshot'), jadi pemeriksaan "hash-nya berbeda-beda" TAK akan
               melihatnya — persis pelajaran round 1 Task 4. Karena itu
               daftarnya dicetak, bukan disimpulkan. */
            $kosong = $sekarang['bundle_null'];
            if ($kosong) {
                $this->warn('Paket tanpa isi bundle (detailBundle mengembalikan null) — sidik jarinya '
                    . 'hanya menutupi releasedSnapshot, BUKAN jalur baca karyawan: ' . implode(', ', $kosong));
            } else {
                $this->line('Semua paket mengembalikan bundle (bundle_null kosong).');
            }
            return self::SUCCESS;
        }

        if (!is_file($bandingkan)) {
            $this->error('Berkas baseline tak ditemukan: ' . $bandingkan);
            return self::FAILURE;
        }

        $lama = json_decode(file_get_contents($bandingkan), true);

        /*
         * Sejumlah nilai dalam sidik jari dihitung relatif terhadap "hari ini"
         * (Hari_Tersisa/Is_Terlambat, Keberlakuan per item, ember kadaluarsa di
         * ringkasan()). Baseline diambil hari X; pembandingan bisa berjalan
         * berminggu-minggu kemudian di hari Y — item yang melewati batas hari
         * akan berubah TANPA regresi nyata. Field ini SENGAJA tidak dibuang dari
         * hash (itu justru membutakan deteksi), tapi operator wajib diberi tahu
         * sumber selisih yang mungkin cuma pergeseran tanggal, bukan bug.
         */
        if (!empty($lama['diambil_pada'])) {
            $tanggalLama = Carbon::parse($lama['diambil_pada'])->toDateString();
            $tanggalSekarang = Carbon::parse($sekarang['diambil_pada'])->toDateString();
            if ($tanggalLama !== $tanggalSekarang) {
                $this->warn("Baseline diambil pada {$tanggalLama}; sebagian nilai dihitung relatif "
                    . 'terhadap hari ini (sisa hari, masa berlaku, ember kadaluarsa), jadi perbedaan '
                    . 'bisa berasal dari pergantian tanggal, bukan regresi.');
            }
        }

        /*
         * Paket tanpa penugasan (lazimnya DRAFT) dibaca lewat $kodeProbe —
         * karyawan pertama secara leksikografis, dipakai HANYA sebagai
         * identitas pemanggil (lihat ambil()). Bila roster berubah sehingga
         * karyawan pertama bergeser, probe-nya ikut bergeser, dan SEMUA paket
         * yang lewat jalur probe bisa ber-BERUBAH serentak — bukan regresi
         * Fase 2, cuma pergeseran identitas pemanggil. Tanpa sinyal ini,
         * operator tak bisa membedakan keduanya.
         */
        if (!empty($lama['probe_kode']) && !empty($sekarang['probe_kode']) && $lama['probe_kode'] !== $sekarang['probe_kode']) {
            $paketTerdampak = array_values(array_unique(array_merge(
                $lama['via_probe'] ?? [],
                $sekarang['via_probe'] ?? [],
            )));
            $this->warn("Identitas probe berubah dari {$lama['probe_kode']} menjadi {$sekarang['probe_kode']} "
                . '(karyawan pertama secara leksikografis bergeser, mis. ada penambahan/penghapusan roster). '
                . 'Paket tanpa penugasan dibaca lewat identitas ini, jadi perbedaan pada paket berikut bisa '
                . 'berasal dari pergeseran probe, bukan regresi: ' . (implode(', ', $paketTerdampak) ?: '(tak ada)'));
        }

        /*
         * Sumbu ketiga yang bisa menggeser sidik jari tanpa ada regresi:
         * identitas pemanggil per paket. detailBundle() dibaca atas nama
         * MIN(Kode_Karyawan) yang ditugaskan; menugaskan/mencabut seseorang
         * yang kebetulan berada di urutan paling awal mengganti pemanggilnya,
         * dan isi bundle memang seharusnya ikut berubah. Sama seperti
         * probe_kode, ini diperingatkan — tidak dibuang dari hash.
         */
        $geser = [];
        foreach ($sekarang['kode_per_paket'] ?? [] as $id => $kode) {
            $kodeLama = $lama['kode_per_paket'][$id] ?? null;
            if ($kodeLama !== null && $kodeLama !== $kode) $geser[] = "{$id}: {$kodeLama} → {$kode}";
        }
        if ($geser) {
            $this->warn('Identitas pemanggil bergeser pada paket berikut (penugasan ditambah/dicabut), '
                . 'jadi perbedaan di sana bisa bukan regresi: ' . implode('; ', $geser));
        }

        /*
         * Urutan baris register memang tak deterministik — ORDER BY-nya
         * (CASE NULL, Tanggal_Kadaluarsa) tak punya pemecah seri, dan di data
         * dev 27 dari 28 baris berbagi nilai yang sama. Karena itu urutannya
         * DIKELUARKAN dari hash (lihat ambil()) dan diperlakukan sebagai
         * peringatan saja: menjadikannya kegagalan hanya akan melahirkan
         * "BERUBAH" palsu setiap kali rencana kuerinya bergeser.
         */
        if (($lama['kontrol_register']['urutan'] ?? null) !== ($sekarang['kontrol_register']['urutan'] ?? null)) {
            $this->warn('Urutan baris register kontrol dokumen bergeser. ORDER BY-nya tak punya pemecah seri, '
                . 'jadi ini BUKAN kegagalan — tapi bila Fase 2 memang menjanjikan urutan stabil, periksa manual.');
        }

        $beda = [];

        foreach ($sekarang['paket'] as $id => $baris) {
            $sebelum = $lama['paket'][$id]['sidik'] ?? null;
            if ($sebelum !== $baris['sidik']) {
                $beda[] = [$id, $baris['nama'], $sebelum ? 'BERUBAH' : 'BARU'];
            }
        }
        foreach (array_keys($lama['paket'] ?? []) as $id) {
            if (!isset($sekarang['paket'][$id])) $beda[] = [$id, '(hilang)', 'HILANG'];
        }

        if ($lama['kontrol_dokumen'] !== $sekarang['kontrol_dokumen']) {
            $beda[] = ['-', 'Ringkasan kontrol dokumen (8 angka)', 'BERUBAH'];
        }

        // Isi register, bukan cuma agregatnya. Baris yang bertukar paket, satu
        // hilang & satu ganda di ember yang sama, atau Hashid salah-encode —
        // semuanya mempertahankan kedelapan angka ringkasan dan akan lolos
        // sebagai "nol regresi" bila hanya ringkasan yang dibandingkan.
        $registerLama = $lama['kontrol_register']['sidik'] ?? null;
        if ($registerLama === null) {
            $this->warn('Berkas baseline belum memuat sidik jari isi register (dibuat sebelum cakupan itu ada) — '
                . 'ambil ulang baseline sebelum Fase 2, kalau tidak jalur baca paling terdampak tak terlindungi.');
            $beda[] = ['-', 'Isi register kontrol dokumen', 'BELUM ADA DI BASELINE'];
        } elseif ($registerLama !== $sekarang['kontrol_register']['sidik']) {
            $beda[] = ['-', 'Isi register kontrol dokumen', 'BERUBAH'];
        }

        if (!$beda) {
            $this->info('Identik dengan baseline — nol regresi.');
            return self::SUCCESS;
        }

        $this->error('Ada ' . count($beda) . ' perbedaan dari baseline:');
        $this->table(['Paket', 'Nama', 'Status'], $beda);
        return self::FAILURE;
    }

    /** @return array{diambil_pada: string, probe_kode: ?string, via_probe: array<int,string>, kode_per_paket: array<string,?string>, bundle_null: array<int,string>, paket: array<string,array{nama:string,sidik:string}>, kontrol_dokumen: array, kontrol_register: array{jumlah:int,total:int,sidik:string,urutan:array<int,string>}} */
    private function ambil(
        SopProgressReadService $progres,
        SopDocumentControlService $kontrol,
        SopPaketVersionService $versiPaket,
    ): array {
        /* Buang cache dulu. ringkasan() menyimpan hasilnya 600 detik; tanpa ini
           perbandingan sebelum/sesudah membandingkan dua nilai cache yang sama
           dan melaporkan "identik" tanpa pernah memeriksa apa pun. */
        Cache::store('database')->forget(SopDocumentControlService::kunciRingkasan());

        $hasil = [
            'diambil_pada'     => now()->toIso8601String(),
            'probe_kode'       => null,
            'via_probe'        => [],
            'kode_per_paket'   => [],
            'bundle_null'      => [],
            'paket'            => [],
            'kontrol_dokumen'  => $kontrol->ringkasan(),
            'kontrol_register' => $this->sidikRegister($kontrol),
        ];

        $paketList = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_SOP_Paket')
            ->get(['Id_SOP_Paket', 'Nama_Paket']);

        // Probe identity utk paket yang tak punya penugasan sama sekali (mis.
        // DRAFT — belum ada yang ditugaskan konten yang belum terbit). Dipakai
        // HANYA sebagai identitas pemanggil detailBundle(); progres pribadinya
        // akan kosong secara sah (bypassGate:true membaca isi paket, bukan
        // menyamar sebagai penugasan yang tak ada). Direkam di $hasil supaya
        // --bandingkan bisa memperingatkan bila probe ini bergeser nanti
        // (lihat peringatan di handle()) — pergeseran roster tak boleh
        // menyamar sebagai regresi Fase 2.
        $kodeProbe = DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');
        $hasil['probe_kode'] = $kodeProbe;

        foreach ($paketList as $p) {
            $idPaket = (int) $p->Id_SOP_Paket;

            // Karyawan yang benar-benar ditugaskan (bila ada) — supaya
            // detailBundle menempuh jalur nyata penugasan, bukan jalur kosong.
            $kode = DB::table('N_SOP_Penjadwalan as pj')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                ->where('pj.Id_SOP_Paket', $idPaket)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->min('d.Kode_Karyawan');

            /*
             * bypassGate HANYA saat pemanggilnya memang TAK lolos gerbang.
             * detailBundle() sendiri
             * memakai bypassGate utk memutuskan apakah menempuh jalur
             * SNAPSHOT RILIS RESMI (SopProgressReadService.php:149 —
             * `$releaseSnapshot = !$bypassGate ? releasedSnapshot(...) : null`)
             * atau jalur tabel aktif. Karyawan yang BENAR ditugaskan pada
             * paket PUBLISHED selalu lolos paketBolehDiakses() tanpa bypass —
             * itulah jalur yang sungguh ditempuh pembaca sungguhan, dan itu
             * pula jalur yang membaca releasedSnapshot(). Mem-bypass secara
             * tanpa syarat (seperti sebelumnya) memaksa $releaseSnapshot null
             * utk SEMUA paket — termasuk yang PUBLISHED dan punya rilis resmi
             * — sehingga cabang snapshot (152-172 & 224-255) tak pernah
             * tereksekusi lewat command ini. Fingerprint tetap kelihatan sehat
             * (isinya tetap nyata, dari tabel aktif) TAPI jalur kode yang
             * dites keliru — lubang cakupan yang sama muncul lagi lewat sumbu
             * lain.
             *
             * SYARATNYA BUKAN `$kode === null`. `$kode` hanya menyaring
             * d.Flag_Aktif, sedangkan paketBolehDiakses() menuntut lebih:
             * Status PUBLISHED, Flag_Aktif paket, dan — untuk paket TERBATAS —
             * Flag_Cabut_Akses='T' pada penugasan orang itu. Paket ARCHIVED
             * yang masih memegang penugasan aktif, atau paket TERBATAS yang
             * penerima pertamanya dicabut aksesnya, menghasilkan $kode BUKAN
             * null tapi gerbangnya MENOLAK → detailBundle() mengembalikan null
             * → sidik jarinya diam-diam menciut jadi snapshot saja. Ia tetap
             * unik, jadi pemeriksaan "hash berbeda-beda" buta terhadapnya.
             * Menanyakan langsung ke gerbangnya menutup sumbu ketiga itu.
             * (paketBolehDiakses() memang public — dipakai apa adanya, bukan
             * disalin, supaya tak ada versi kedua dari aturan akses.)
             */
            $pemanggil = $kode ?: $kodeProbe;
            if ($pemanggil && $kode === null) $hasil['via_probe'][] = (string) $idPaket;

            $boleh = $pemanggil !== null && $progres->paketBolehDiakses($pemanggil, $idPaket);
            $bundle = $pemanggil ? $progres->detailBundle($pemanggil, $idPaket, bypassGate: !$boleh) : null;

            // Identitas pemanggil & cakupan kosong dicatat DI LUAR hash: keduanya
            // bukan keluaran baca yang dijaga Fase 2, melainkan konteks yang
            // memberi tahu operator kenapa sebuah sidik jari bergeser (atau
            // kenapa ia tak menutupi apa-apa).
            $hasil['kode_per_paket'][(string) $idPaket] = $pemanggil;
            if ($bundle === null) $hasil['bundle_null'][] = (string) $idPaket;

            $bahan = [
                'bundle'   => $bundle,
                'snapshot' => $versiPaket->releasedSnapshot($idPaket),
            ];

            /* detailBundle() menyertakan Cover_Url — signed URL GCS berumur 20
               menit (Storage::disk('gcs')->temporaryUrl()) yang ditanda-tangani
               ULANG setiap panggilan. Expires & Signature-nya SELALU beda meski
               path gambarnya sama persis, jadi tanpa normalisasi ini sidik jari
               tak akan pernah identik untuk paket manapun yang punya cover —
               positif palsu "berubah" padahal isinya tak berubah sama sekali.
               Hanya path yang berarti utk deteksi regresi; query signed dibuang. */
            if (!empty($bahan['bundle']['paket']['Cover_Url'])) {
                $bahan['bundle']['paket']['Cover_Url'] = strtok($bahan['bundle']['paket']['Cover_Url'], '?');
            }

            $hasil['paket'][(string) $idPaket] = [
                'nama'  => $p->Nama_Paket,
                'sidik' => hash('sha256', json_encode($bahan, JSON_UNESCAPED_UNICODE)),
            ];
        }

        return $hasil;
    }

    /**
     * Sidik jari ISI register kontrol dokumen — bukan cuma kedelapan angka
     * ringkasannya.
     *
     * KENAPA WAJIB. register() adalah jalur baca yang paling erat terikat pada
     * join yang ditulis ulang Fase 2: baseVersiAktif() men-join item↔versi DUA
     * KALI (Id_SOP_Paket_Item DAN Id_Item_Versi_Aktif). Regresi yang menukar
     * baris antar paket, menghilangkan satu sambil menggandakan satu lagi di
     * ember yang sama, atau salah meng-encode Hashid, mempertahankan kedelapan
     * agregat itu utuh — dan akan dilaporkan "Identik. Nol regresi."
     *
     * NORMALISASI URUTAN, dan hanya itu. ORDER BY register() adalah
     * (CASE WHEN Tanggal_Kadaluarsa IS NULL, Tanggal_Kadaluarsa) — tanpa
     * pemecah seri. Di data dev 27 dari 28 baris berbagi nilai yang sama, jadi
     * urutan di antara mereka ditentukan rencana kueri, dan Fase 2 hampir pasti
     * mengubah rencana itu karena ia mengubah join-nya. Mengunci urutan ke
     * dalam hash akan memproduksi "BERUBAH" palsu; urutannya karena itu
     * disortir stabil sebelum di-hash (sama persis semangatnya dengan
     * pembuangan query signed pada Cover_Url: yang non-deterministik dibuang,
     * yang bermakna dipertahankan). Urutan aslinya TIDAK hilang — ia disimpan
     * di 'urutan' dan dilaporkan sebagai PERINGATAN oleh --bandingkan.
     *
     * Sisanya sudah deterministik: tanggal sudah diformat Y-m-d oleh
     * lekatkanDampakDanEmber(), id sudah Hashid (fungsi murni), ember berupa
     * array dengan urutan tetap. Ambang 30 = default halaman monitoring; limit
     * 500 > 28 baris hari ini, dan 'jumlah' vs 'total' di bawah membuat
     * pemotongan halaman ketahuan alih-alih senyap.
     *
     * @return array{jumlah:int,total:int,sidik:string,urutan:array<int,string>}
     */
    private function sidikRegister(SopDocumentControlService $kontrol): array
    {
        [$baris, $total] = $kontrol->register(
            ['ambang' => SopDocumentControlService::AMBANG_HARI_DEFAULT], 1, 500
        );

        // Pembatasan apa pun wajib dilaporkan, tak boleh senyap: kalau register
        // sudah melewati satu halaman, sidik jari ini cuma menutupi 500 pertama.
        if ($baris->count() < (int) $total) {
            $this->warn("Register kontrol dokumen punya {$total} baris tapi sidik jari hanya menutupi "
                . $baris->count() . ' (satu halaman). Naikkan limit di sidikRegister() sebelum Fase 2.');
        }

        $urutan = $baris->pluck('Id_SOP_Item_Versi')->map(fn ($v) => (string) $v)->values()->all();
        $stabil = $baris->sortBy(fn ($r) => (string) $r->Id_SOP_Item_Versi)->values()->all();

        return [
            'jumlah' => count($stabil),
            'total'  => (int) $total,
            'sidik'  => hash('sha256', json_encode($stabil, JSON_UNESCAPED_UNICODE)),
            'urutan' => $urutan,
        ];
    }
}
```

- [ ] **Step 4: Jalankan tes, pastikan LULUS**

Run: `php artisan test tests/Feature/LMS/SOP/SopDokumenBaselineTest.php`
Expected: PASS (5 tes)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SopDokumenBaseline.php tests/Feature/LMS/SOP/SopDokumenBaselineTest.php
git commit -m "feat(sop): baseline sidik jari keluaran baca untuk bukti nol regresi"
```

---

### Task 5: Jalankan Fase 1 di dev + bukti tak ada regresi

**Files:** tidak ada perubahan kode — ini tugas operasional bergerbang.

- [ ] **Step 1: Ambil baseline SEBELUM backfill**

```bash
php artisan sop:dokumen-baseline --simpan=plans/sop/baseline/fase1-sebelum.json
```
Expected: "Baseline disimpan", 14 paket tercakup.

Lokasinya **bukan** `storage/` — `/storage` ada di `.gitignore`, sehingga baseline
di sana tak bisa di-commit dan akan lenyap sebelum Fase 2 sempat memakainya.

- [ ] **Step 2: Dry-run backfill & baca dampaknya**

```bash
php artisan sop:dokumen-backfill --dry-run
```
Expected: daftar item yang akan dibuatkan dokumen; **tak ada** yang ditulis.

- [ ] **Step 3: Jalankan backfill**

```bash
php artisan sop:dokumen-backfill
```
Expected: "Selesai: N dokumen dibuat & ditautkan."

- [ ] **Step 4: Verifikasi integritas**

```bash
php artisan sop:dokumen-verify
```
Expected: "Integritas dokumen SOP: BERSIH." (exit 0). **Bila gagal, JANGAN lanjut** — perbaiki dulu.

- [ ] **Step 5: Buktikan nol regresi**

```bash
php artisan sop:dokumen-baseline --bandingkan=plans/sop/baseline/fase1-sebelum.json
```
Expected: "Identik dengan baseline — nol regresi." (exit 0).
Ini gerbang utama Fase 1: kolom baru terisi, tapi tak satu pun keluaran baca bergeser.

- [ ] **Step 6: Jalankan seluruh rangkaian tes**

```bash
php artisan test --filter=Sop
npx vitest run
```
Expected: semua lulus, tak ada regresi pada 106 tes SOP & 126 tes JS yang sudah ada.

- [ ] **Step 7: Commit baseline sebagai bukti**

```bash
git add plans/sop/baseline/fase1-sebelum.json
git commit -m "chore(sop): baseline pra-Fase-1 sebagai bukti nol regresi"
```

---

## Gerbang Menuju Fase 2

Fase 2 **tidak boleh dimulai** sebelum semuanya hijau:

- [ ] `php artisan sop:dokumen-verify` → exit 0
- [ ] `php artisan sop:dokumen-baseline --bandingkan=...` → "Identik dengan baseline"
- [ ] `php artisan test --filter=Sop` → nol gagal
- [ ] Semantik `rollback()` untuk dokumen bersama sudah diputuskan (lihat "Keputusan yang masih terbuka" di Peta Fase)

**Dua hal yang wajib dikerjakan DI AWAL Fase 2, sebelum satu baris kode pun bergerak:**

- [ ] **Export rinci masuk baseline dulu, baru kode disentuh.** Ia sengaja ditunda dari Fase 1 karena menjoin lewat `i.Id_Item_Versi_Aktif` yang tak tersentuh (lihat "Cakupan & yang sengaja ditunda" di Task 4), tapi Fase 2 memindahkan pembacaan versi — persis yang membuatnya bisa bergeser. **Baseline pra-perubahan hanya bisa diambil sebelum perubahan.** Menambahkannya setelah kode bergerak berarti membakukan hasil yang sudah berubah sebagai "sebelum", dan bukti nol-regresinya jadi kosong. Sama berlakunya untuk penambahan cakupan apa pun yang menyusul: ubah `ambil()` **lalu ambil ulang baseline**, dua-duanya sebelum kode Fase 2 pertama.
- [ ] **Unique index baru wajib TERFILTER.** `sop:dokumen-verify` baris INFO ke-6 melaporkan jumlah `N_SOP_Item_Versi` ber-`Id_SOP_Dokumen` NULL (18 dari 54 di dev). SQL Server menganggap NULL **setara** di unique index, jadi `UNIQUE (Id_SOP_Dokumen, Versi_Ke)` polos akan gagal dengan error 2601 pada baris `(NULL, 1)` yang kedua. Selama angka itu > 0, DDL Fase 2 harus memakai `WHERE Id_SOP_Dokumen IS NOT NULL` — keputusan yang diambil sadar di muka, bukan ditemukan saat DDL meledak.

Setelah gerbang ini lolos, tulis `plans/sop/<tanggal>-master-dokumen-fase2-plan.md` dengan pengetahuan hasil Fase 1 di tangan — bukan tebakan yang dibuat hari ini.
