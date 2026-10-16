# Backfill Cap KPI per Parameter — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Endpoint Postman ber-token yang menghitung ulang seluruh header KPI aktif non-`PARAMETER` dengan cap per parameter, di background, dengan cadangan dan rollback.

**Architecture:** Perhitungan murni di `KpiCapParameterKalkulator` (memanggil `KpiScoringService`). `KpiCapParameterBackfillService` mengurus sasaran, pratinjau, cadangan, penulisan set-based per potongan 300 header, dan rollback. Dua job Cloud Tasks (induk memecah, potongan memproses). Progres di `N_HRIS_Async_Tasks` (tampil di panel Tugas & Aktivitas). Tabel cadangan baru lewat DDL-2026-167.

**Tech Stack:** Laravel 10, SQL Server (`Web_HRIS`, tanpa RCSI), Cloud Tasks (`stackkit/laravel-google-cloud-tasks-queue`), PHPUnit dengan `DatabaseTransactions` terhadap DB dev.

**Spec:** `docs/superpowers/specs/2026-10-12-kpi-backfill-cap-per-parameter-design.md`

## Global Constraints

- Sasaran: `KPI_Update_Performance` `Flag_Aktif='Y'`, `Status IS NULL`, `Metode_Cap` NULL atau `<> 'PARAMETER'`.
- Rumus wajib lewat `KpiScoringService` (`scoreFromRatio`, `aggregate`, `applyCap`, level) — tidak ditulis ulang.
- Sumber nilai asli: `COALESCE(Score_Raw, Score)` dan `COALESCE(Achievement_Raw, Achievement)`.
- Potongan: **300 header** per transaksi; `whereIn` dipecah per **500** id (batas 2.100 parameter SQL Server).
- Penulisan set-based (tabel sementara + `UPDATE … FROM`), tidak ada UPDATE per baris.
- Token: header `X-Backfill-Token`, `hash_equals`, **gagal-tertutup bila kosong** (tanpa nilai cadangan di config).
- Hanya satu batch `kpi-backfill-cap` aktif pada satu waktu (409).
- Endpoint lama `api/v1/kpi-enforcement/backfill-cap` → **410 Gone**.
- Change ID: `DDL-2026-167-kpi-cadangan-backfill-cap`. Tidak ada migration Laravel baru.
- Jalankan uji hanya saat tugas memintanya (aturan proyek: jangan uji sebelum ACC sudah dipenuhi — user sudah ACC eksekusi).

## Review Focus

1. **Token default yang di-commit**: `config/services.php` memberi `KPI_BACKFILL_TOKEN` nilai cadangan rahasia, jadi endpoint tak pernah gagal-tertutup. Harapan: tanpa env, setiap permintaan 401. → uji di Task 5.
2. **Cloud Tasks mengirim ulang potongan yang sudah selesai**: progres tak boleh bertambah dua kali dan cadangan tak boleh tergandakan. → uji di Task 3 (penanda potongan unik).
3. **Header berubah jadi `PARAMETER` di antara pencacahan dan pemrosesan** (mis. approver mengedit): dilewati, tetapi batch tetap mencapai 100%. → uji di Task 3.
4. **Rollback dijalankan dua kali**: kali kedua tidak mengubah apa pun (`Dipulihkan_At`). → uji di Task 3.
5. **Pratinjau di data produksi besar** harus selesai dalam satu permintaan HTTP: jumlah kueri tak tumbuh per header. → uji batas kueri di Task 3.

---

## File Structure

| File | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/forward.sql`, `drop.sql` | Tabel cadangan + indeks + penanda potongan unik |
| `docs/changes/DDL-2026-167-kpi-cadangan-backfill-cap/README.md` | Dokumentasi perubahan |
| `docs/database/tables/hris/n-kpi-backfill-cap-cadangan.md` | Dokumentasi tabel |
| `database/ddl/status.json` | Entri ledger |
| `app/Services/KPI/KpiScoringService.php` | + `pilihLevel()` (level dari pita yang sudah dimuat) |
| `app/Services/KPI/KpiCapParameterKalkulator.php` | Hitung ulang satu header + detailnya (murni) |
| `app/Services/KPI/KpiCapParameterBackfillService.php` | Sasaran, pratinjau, batch, potongan, cadangan, rollback, status |
| `app/Jobs/KPI/Monitoring/Enforcement/BackfillCapParameterBatchJob.php` | Job induk: pecah sasaran jadi potongan |
| `app/Jobs/KPI/Monitoring/Enforcement/BackfillCapParameterChunkJob.php` | Job potongan |
| `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiBackfillCapController.php` | 4 endpoint + 410 endpoint lama |
| `routes/web.php`, `app/Http/Middleware/VerifyCsrfToken.php`, `config/services.php` | Rute, CSRF, token gagal-tertutup |
| `app/Services/ESS/Activity/Center/ActivityCenterService.php`, `app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php` | Batch tampil & bisa disembunyikan di panel |
| Hapus: `app/Services/KPI/KpiCapBackfillService.php`, `KpiEnforcementController::backfillCap` | Metode cap total yang sudah diganti |
| `tests/Feature/KPI/Backfill/*` | Uji |

---

### Task 1: DDL-2026-167 — tabel cadangan

**Files:**
- Create: `database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/forward.sql`
- Create: `database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/drop.sql`
- Create: `docs/changes/DDL-2026-167-kpi-cadangan-backfill-cap/README.md`
- Create: `docs/database/tables/hris/n-kpi-backfill-cap-cadangan.md`
- Modify: `database/ddl/status.json` (tambah entri di akhir `changes`)

**Interfaces:**
- Produces: tabel `dbo.N_KPI_Backfill_Cap_Cadangan(Id_Cadangan bigint PK, Batch_Id varchar(60), Jenis char(6) 'HEADER'|'DETAIL'|'POTONG', Id_Update_Performance int, Id_Update_Performance_Detail int NULL, Nilai_Lama_Json nvarchar(max) NULL, Dipulihkan_At datetime2 NULL, Created_At datetime2)`; indeks unik terfilter `UX_KPI_Backfill_Cap_Potong (Batch_Id, Id_Update_Performance) WHERE Jenis='POTONG'` (penanda potongan: `Id_Update_Performance` = indeks potongan).

- [ ] **Step 1: Tulis `forward.sql`**

```sql
-- Change ID: DDL-2026-167-kpi-cadangan-backfill-cap
-- Target: Web_HRIS. Cadangan nilai lama untuk backfill cap KPI per parameter
-- (endpoint backfill-cap-parameter) + penanda potongan agar kiriman ulang
-- Cloud Tasks tidak memproses/menghitung dua kali.
IF OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_KPI_Backfill_Cap_Cadangan (
        Id_Cadangan                   bigint IDENTITY(1,1) NOT NULL,
        Batch_Id                      varchar(60)   NOT NULL,
        Jenis                         char(6)       NOT NULL,
        Id_Update_Performance         int           NOT NULL,
        Id_Update_Performance_Detail  int           NULL,
        Nilai_Lama_Json               nvarchar(max) NULL,
        Dipulihkan_At                 datetime2(0)  NULL,
        Created_At                    datetime2(0)  NOT NULL CONSTRAINT DF_KPI_Backfill_Cap_Created DEFAULT (SYSDATETIME()),
        CONSTRAINT PK_KPI_Backfill_Cap_Cadangan PRIMARY KEY (Id_Cadangan),
        CONSTRAINT CK_KPI_Backfill_Cap_Jenis CHECK (Jenis IN ('HEADER', 'DETAIL', 'POTONG'))
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan') AND name = 'IX_KPI_Backfill_Cap_Batch')
    CREATE INDEX IX_KPI_Backfill_Cap_Batch
        ON dbo.N_KPI_Backfill_Cap_Cadangan (Batch_Id, Jenis, Id_Update_Performance);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan') AND name = 'UX_KPI_Backfill_Cap_Potong')
    CREATE UNIQUE INDEX UX_KPI_Backfill_Cap_Potong
        ON dbo.N_KPI_Backfill_Cap_Cadangan (Batch_Id, Id_Update_Performance)
        WHERE Jenis = 'POTONG';
GO
```

- [ ] **Step 2: Tulis `drop.sql`**

```sql
-- Change ID: DDL-2026-167-kpi-cadangan-backfill-cap
-- PEMBERSIHAN MANUAL — tidak dijalankan agent. Menghapus cadangan berarti
-- backfill yang sudah jalan TIDAK BISA lagi di-rollback.
IF OBJECT_ID('dbo.N_KPI_Backfill_Cap_Cadangan', 'U') IS NOT NULL
    DROP TABLE dbo.N_KPI_Backfill_Cap_Cadangan;
GO
```

- [ ] **Step 3: Tulis README perubahan** — tabel field (Change ID, Jenis DDL, Target `Web_HRIS`, artifact + checksum, Status `REVIEW_REQUIRED`), Latar (rujuk spec), Isi (kolom, indeks, arti `POTONG`), Urutan rilis ("wajib di produksi sebelum `mode=jalankan`; pratinjau tetap jalan tanpa tabel"), Pekerjaan (Agent/User).

- [ ] **Step 4: Tulis dokumentasi tabel** `docs/database/tables/hris/n-kpi-backfill-cap-cadangan.md` mengikuti format `kpi-update-performance-detail-cap.md`.

- [ ] **Step 5: Tambah entri `status.json`** (salin bentuk entri DDL-2026-158): `change_id`, `type: "DDL"`, `status: "REVIEW_REQUIRED"`, `artifact`, `documentation`, `objek_terdampak: ["dbo.N_KPI_Backfill_Cap_Cadangan (CREATE TABLE + PK, CK_KPI_Backfill_Cap_Jenis, IX_KPI_Backfill_Cap_Batch, UX_KPI_Backfill_Cap_Potong)"]`, `development.status: "PENDING"`, `sql_tracker/production/verification: PENDING`, `catatan` berisi sha256 `forward.sql`.

Run: `sha256sum database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/forward.sql` lalu `python -I -c "import json;json.load(open('database/ddl/status.json',encoding='utf-8'))"`
Expected: JSON valid.

- [ ] **Step 6: Minta persetujuan SQL ke user, lalu jalankan di dev** (hanya sesudah disetujui):

```bash
php artisan tinker --execute 'foreach (preg_split("/^GO\s*$/m", file_get_contents(base_path("database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/forward.sql"))) as $b) if (trim($b) !== "") DB::unprepared($b); echo Schema::hasTable("N_KPI_Backfill_Cap_Cadangan") ? "ok" : "gagal";'
```
Expected: `ok`; jalankan kedua kali tetap `ok` (idempoten). Perbarui `development` di `status.json` menjadi `DONE` + hasil; README status `DEV_EXECUTED`.

- [ ] **Step 7: Commit**

```bash
git add database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap docs/changes/DDL-2026-167-kpi-cadangan-backfill-cap docs/database/tables/hris/n-kpi-backfill-cap-cadangan.md database/ddl/status.json
git commit -m "feat(kpi): DDL-2026-167 tabel cadangan backfill cap per parameter"
```

---

### Task 2: Kalkulator per header + `pilihLevel`

**Files:**
- Modify: `app/Services/KPI/KpiScoringService.php` (`resolveLevel` memakai `pilihLevel`)
- Create: `app/Services/KPI/KpiCapParameterKalkulator.php`
- Test: `tests/Feature/KPI/Backfill/KpiCapParameterKalkulatorTest.php`

**Interfaces:**
- Produces: `KpiScoringService::pilihLevel(iterable $pita, float $skor): object` — pita = baris `KPI_Master_Performance`; level dengan `Range_Awal` tertinggi `<= $skor`, bila tak ada pita dengan `Range_Awal` terendah; lempar `\Exception` bila pita kosong.
- Produces: `KpiCapParameterKalkulator::hitung(iterable $detail, iterable $pita): array{detail: list<array{Id_Update_Performance_Detail:int, Score:float, Achievement:float, Score_Raw:float, Achievement_Raw:float, Flag_Capped:string}>, header: array{Total_Score:float, Total_Score_Achievement:float, Total_Score_Raw:float, Total_Score_Achievement_Raw:float, Flag_Capped:string, Cap_Min_Applied:?float, Cap_Max_Applied:?float, Metode_Cap:string, Id_Performance:int}}` — `$detail` berisi objek/array dengan kunci `Id_Update_Performance_Detail, Score, Achievement, Score_Raw, Achievement_Raw, Bobot`.
- Produces: `KpiCapParameterKalkulator::muatPita(): \Illuminate\Support\Collection` (satu kueri `KPI_Master_Performance`).

- [ ] **Step 1: Tulis uji gagal**

```php
<?php

namespace Tests\Feature\KPI\Backfill;

use App\Services\KPI\KpiCapParameterKalkulator;
use App\Services\KPI\KpiScoringService;
use Tests\TestCase;

class KpiCapParameterKalkulatorTest extends TestCase
{
    private function pita(): array
    {
        return [
            (object) ['Id_Master_Performance' => 12, 'Range_Awal' => 0.0],
            (object) ['Id_Master_Performance' => 13, 'Range_Awal' => 75.0],
            (object) ['Id_Master_Performance' => 14, 'Range_Awal' => 90.0],
            (object) ['Id_Master_Performance' => 15, 'Range_Awal' => 110.0],
        ];
    }

    private function d(int $id, ?float $score, ?float $ach, float $bobot, ?float $scoreRaw = null, ?float $achRaw = null): object
    {
        return (object) ['Id_Update_Performance_Detail' => $id, 'Score' => $score, 'Achievement' => $ach,
            'Score_Raw' => $scoreRaw, 'Achievement_Raw' => $achRaw, 'Bobot' => $bobot];
    }

    public function test_menjepit_tiap_parameter_lalu_menghitung_total_dan_level(): void
    {
        config(['services.kpi.param_max_achievement_score' => 125, 'services.kpi.param_min_achievement_score' => 50]);

        $hasil = app(KpiCapParameterKalkulator::class)->hitung([
            $this->d(1, 140.0, 42.0, 30.0),  // di atas plafon
            $this->d(2, 80.0, 56.0, 70.0),   // di dalam rentang
        ], $this->pita());

        $this->assertSame(['Id_Update_Performance_Detail' => 1, 'Score' => 125.0, 'Achievement' => 37.5,
            'Score_Raw' => 140.0, 'Achievement_Raw' => 42.0, 'Flag_Capped' => 'Y'], $hasil['detail'][0]);
        $this->assertSame(['Id_Update_Performance_Detail' => 2, 'Score' => 80.0, 'Achievement' => 56.0,
            'Score_Raw' => 80.0, 'Achievement_Raw' => 56.0, 'Flag_Capped' => 'T'], $hasil['detail'][1]);

        $h = $hasil['header'];
        $this->assertSame(93.5, $h['Total_Score']);           // (37,5 + 56) / 100 × 100
        $this->assertSame(98.0, $h['Total_Score_Raw']);       // (42 + 56) / 100 × 100
        $this->assertEqualsWithDelta(93.5, $h['Total_Score_Achievement'], 0.0001);
        $this->assertSame(98.0, $h['Total_Score_Achievement_Raw']);
        $this->assertSame('Y', $h['Flag_Capped']);
        $this->assertSame('PARAMETER', $h['Metode_Cap']);
        $this->assertSame(14, $h['Id_Performance']);          // floor(93,5)=93 → pita 90
        $this->assertSame(50.0, $h['Cap_Min_Applied']);
        $this->assertSame(125.0, $h['Cap_Max_Applied']);
    }

    public function test_menjepit_ke_lantai(): void
    {
        $hasil = app(KpiCapParameterKalkulator::class)->hitung([$this->d(1, 20.0, 10.0, 50.0)], $this->pita());

        $this->assertSame(50.0, $hasil['detail'][0]['Score']);
        $this->assertSame(25.0, $hasil['detail'][0]['Achievement']);
        $this->assertSame(50.0, $hasil['header']['Total_Score']);
        $this->assertSame(12, $hasil['header']['Id_Performance']);
    }

    public function test_idempoten_bila_dijalankan_atas_hasilnya_sendiri(): void
    {
        $k = app(KpiCapParameterKalkulator::class);
        $pertama = $k->hitung([$this->d(1, 140.0, 42.0, 30.0), $this->d(2, 20.0, 14.0, 70.0)], $this->pita());

        $masukan = array_map(fn ($r) => (object) ($r + ['Bobot' => $r['Id_Update_Performance_Detail'] === 1 ? 30.0 : 70.0]), $pertama['detail']);
        $kedua = $k->hitung($masukan, $this->pita());

        $this->assertSame($pertama, $kedua, 'Menghitung ulang record yang sudah dijepit mengubah nilainya.');
    }

    public function test_pilih_level_sama_dengan_resolve_level_untuk_pita_nyata(): void
    {
        $s = app(KpiScoringService::class);
        $pita = app(KpiCapParameterKalkulator::class)->muatPita();
        foreach ([-5, 0, 49.99, 50, 74, 75, 89.5, 100, 124.9, 125, 300] as $skor) {
            $this->assertSame(
                (int) $s->resolveLevel($skor)->Id_Master_Performance,
                (int) $s->pilihLevel($pita, $skor)->Id_Master_Performance,
                "Level berbeda untuk skor {$skor}.",
            );
        }
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/KPI/Backfill/KpiCapParameterKalkulatorTest.php`
Expected: FAIL — `Class "App\Services\KPI\KpiCapParameterKalkulator" not found`.

- [ ] **Step 3: Tambah `pilihLevel` dan alihkan `resolveLevel` ke sana** di `KpiScoringService`:

```php
    public function resolveLevel(float $score): object
    {
        return $this->pilihLevel(DB::table('KPI_Master_Performance')->get(), $score);
    }

    /**
     * Level untuk skor dari pita yang SUDAH dimuat — supaya pemanggil yang
     * menghitung ratusan header (backfill) tak membayar satu kueri per header.
     * Aturannya tetap satu: Range_Awal tertinggi yang <= skor; skor di bawah
     * seluruh pita = pita terendah.
     */
    public function pilihLevel(iterable $pita, float $skor): object
    {
        $pita = collect($pita);
        if ($pita->isEmpty()) {
            throw new \Exception('Konfigurasi Master Performance tidak ditemukan atau tidak lengkap.');
        }

        return $pita->filter(fn ($p) => (float) $p->Range_Awal <= $skor)->sortByDesc(fn ($p) => (float) $p->Range_Awal)->first()
            ?? $pita->sortBy(fn ($p) => (float) $p->Range_Awal)->first();
    }
```

(Hapus isi lama `resolveLevel` yang memakai dua kueri; komentar lama tentang "level TERENDAH, bukan error konfigurasi" dipindah ke docblock `pilihLevel`.)

- [ ] **Step 4: Tulis `KpiCapParameterKalkulator`**

```php
<?php

namespace App\Services\KPI;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hitung ulang satu header KPI + detailnya dengan cap PER PARAMETER.
 *
 * Murni: tidak membaca atau menulis basis data selain muatPita(). Rumusnya
 * milik KpiScoringService, tidak ditulis ulang di sini — hasilnya wajib sama
 * dengan finalisasi normal.
 *
 * Nilai asli diambil dari kolom _Raw bila sudah ada, sehingga record yang sudah
 * dijepit menghasilkan nilai yang sama (idempoten).
 */
class KpiCapParameterKalkulator
{
    public function __construct(private KpiScoringService $scoring) {}

    public function muatPita(): Collection
    {
        return DB::table('KPI_Master_Performance')->get(['Id_Master_Performance', 'Range_Awal']);
    }

    public function hitung(iterable $detail, iterable $pita): array
    {
        $baru = [];
        $bobot = [];
        foreach ($detail as $d) {
            $d = (object) $d;
            $asliScore = (float) ($d->Score_Raw ?? $d->Score);
            $asliAch = (float) ($d->Achievement_Raw ?? $d->Achievement);
            $h = $this->scoring->scoreFromRatio($asliScore / 100, (float) $d->Bobot, $asliAch);

            $baris = [
                'Id_Update_Performance_Detail' => (int) $d->Id_Update_Performance_Detail,
                'Score' => (float) $h['achievement'],
                'Achievement' => (float) $h['score'],
                'Score_Raw' => (float) $h['achievement_raw'],
                'Achievement_Raw' => (float) $h['score_raw'],
                'Flag_Capped' => $h['is_capped'] ? 'Y' : 'T',
            ];
            $baru[] = $baris;
            $bobot[] = $baris + ['Bobot' => (float) $d->Bobot];
        }

        $scores = $this->scoring->applyCap($this->scoring->aggregate($bobot, 'Achievement', 'Bobot', 'Achievement_Raw'));
        $level = $this->scoring->pilihLevel($pita, floor($scores['final_score_percentage']));

        return [
            'detail' => $baru,
            'header' => [
                'Total_Score' => (float) $scores['final_score_percentage'],
                'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
                'Total_Score_Raw' => (float) $scores['raw_final_score_percentage'],
                'Total_Score_Achievement_Raw' => (float) $scores['raw_sum_of_scores'],
                'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
                // Sama seperti finalisasi: dijepit per parameter. Penanda ini yang
                // membuat backfill tak menyentuh record ini lagi.
                'Metode_Cap' => \App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController::METODE_CAP_PARAMETER,
                'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
                'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
                'Id_Performance' => (int) $level->Id_Master_Performance,
            ],
        ];
    }
}
```

- [ ] **Step 5: Jalankan uji**

Run: `php artisan test tests/Feature/KPI/Backfill/KpiCapParameterKalkulatorTest.php tests/Feature/KPI/PenandaMetodeCapTest.php`
Expected: PASS (bila `assertSame` urutan kunci `header` berbeda, samakan urutan di kalkulator dengan uji — urutan kunci adalah bagian kontrak).

- [ ] **Step 6: Uji regresi pengguna `resolveLevel`**

Run: `php artisan test tests/Feature/KPI/Monitoring tests/Feature/KPI/PenandaMetodeCapTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/KPI/KpiScoringService.php app/Services/KPI/KpiCapParameterKalkulator.php tests/Feature/KPI/Backfill/KpiCapParameterKalkulatorTest.php
git commit -m "feat(kpi): kalkulator cap per parameter untuk backfill + pilihLevel"
```

---

### Task 3: Service backfill — sasaran, pratinjau, potongan, rollback

**Files:**
- Create: `app/Services/KPI/KpiCapParameterBackfillService.php`
- Test: `tests/Feature/KPI/Backfill/KpiCapParameterBackfillServiceTest.php`

**Interfaces:**
- Consumes: `KpiCapParameterKalkulator::hitung/muatPita` (Task 2); tabel Task 1.
- Produces (konstanta): `TASK_TYPE = 'kpi-backfill-cap'`, `MODULE = 'kpi-backfill'`, `TABEL_CADANGAN = 'N_KPI_Backfill_Cap_Cadangan'`, `POTONGAN = 300`.
- Produces (metode publik):
  - `tabelCadanganAda(): bool`
  - `idSasaran(): array` — `list<int>` `Id_Update_Performance` sasaran, urut naik.
  - `pratinjau(): array` — `{batas:{min,max}, header_sasaran:int, per_status:array<string,int>, detail_sasaran:int, detail_berubah:int, header_skor_berubah:int, perpindahan_level:list<{dari,ke,jumlah}>, contoh:list<{no_transaksi,periode,skor_lama,skor_baru,level_lama,level_baru}>}`
  - `batchAktif(): ?object`
  - `mulai(string $mode, ?string $requestedBy, ?string $sumberBatch = null): string` — `$mode` `'jalankan'|'rollback'`; buat baris `N_HRIS_Async_Tasks`, kembalikan `batch_id`.
  - `idUntukBatch(string $batchId): array` — `list<int>` sasaran (jalankan) atau header yang belum dipulihkan (rollback).
  - `prosesPotongan(string $batchId, int $indeks, array $ids): int` — jumlah header yang benar-benar diubah.
  - `pulihkanPotongan(string $batchId, string $sumberBatch, int $indeks, array $ids): int`
  - `tandaiGagal(string $batchId, string $pesan): void`
  - `status(string $batchId): ?array` — `{batch_id, mode, status, total, diproses, persen, mulai, selesai, galat}`

- [ ] **Step 1: Tulis uji gagal** (memakai data dev; dilewati bila tabel Task 1 belum ada)

```php
<?php

namespace Tests\Feature\KPI\Backfill;

use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use App\Services\KPI\KpiCapParameterKalkulator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KpiCapParameterBackfillServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Svc $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(Svc::class);
        if (! $this->svc->tabelCadanganAda()) {
            $this->markTestSkipped('DDL-2026-167 belum dijalankan di database ini.');
        }
    }

    /** Dua header sasaran yang punya detail di luar 50–125 bila ada. */
    private function duaSasaran(): array
    {
        $ids = $this->svc->idSasaran();
        if (count($ids) < 2) {
            $this->markTestSkipped('Butuh minimal dua header sasaran di DB dev.');
        }

        return array_slice($ids, 0, 2);
    }

    private function potret(array $ids): array
    {
        return [
            'h' => DB::table('KPI_Update_Performance')->whereIn('Id_Update_Performance', $ids)->orderBy('Id_Update_Performance')->get()->map(fn ($r) => (array) $r)->all(),
            'd' => DB::table('KPI_Update_Performance_Detail')->whereIn('Id_Update_Performance', $ids)->orderBy('Id_Update_Performance_Detail')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_pratinjau_tidak_menulis_dan_kuerinya_tak_tumbuh_per_header(): void
    {
        $sebelum = DB::table(Svc::TABEL_CADANGAN)->count();
        DB::enableQueryLog();
        $p = $this->svc->pratinjau();
        $jumlahKueri = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($sebelum, DB::table(Svc::TABEL_CADANGAN)->count(), 'Pratinjau menulis cadangan.');
        $this->assertSame(count($this->svc->idSasaran()), $p['header_sasaran']);
        // per 500 header: header + detail; + pita + label level. Tidak per header.
        $this->assertLessThan(10 + 4 * (int) ceil(max(1, $p['header_sasaran']) / 500), $jumlahKueri,
            "Pratinjau memakai {$jumlahKueri} kueri untuk {$p['header_sasaran']} header.");
        foreach (['batas', 'per_status', 'detail_berubah', 'header_skor_berubah', 'perpindahan_level', 'contoh'] as $k) {
            $this->assertArrayHasKey($k, $p);
        }
    }

    public function test_potongan_menulis_hasil_kalkulator_dan_mencadangkan_nilai_lama(): void
    {
        $ids = $this->duaSasaran();
        $lama = $this->potret($ids);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');

        $diubah = $this->svc->prosesPotongan($batch, 0, $ids);
        $this->assertSame(2, $diubah);

        $k = app(KpiCapParameterKalkulator::class);
        $pita = $k->muatPita();
        foreach ($ids as $id) {
            $detail = DB::table('KPI_Update_Performance_Detail as d')
                ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
                ->where('d.Id_Update_Performance', $id)
                ->get(['d.Id_Update_Performance_Detail', 'd.Score', 'd.Achievement', 'd.Score_Raw', 'd.Achievement_Raw', 'pd.Bobot']);
            $harap = $k->hitung($detail, $pita);   // idempoten: hitung ulang hasil = hasil
            $h = DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $id)->first();
            $this->assertSame('PARAMETER', $h->Metode_Cap);
            $this->assertEqualsWithDelta($harap['header']['Total_Score'], (float) $h->Total_Score, 0.01);
            $this->assertSame($harap['header']['Id_Performance'], (int) $h->Id_Performance);
            foreach ($detail as $d) {
                $this->assertGreaterThanOrEqual(50, (float) $d->Score);
                $this->assertLessThanOrEqual(125, (float) $d->Score);
            }
        }

        $this->assertSame(2, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'HEADER')->count());
        $this->assertSame(count($lama['d']), DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'DETAIL')->count());
    }

    public function test_potongan_terkirim_ulang_tidak_dihitung_dua_kali(): void
    {
        $ids = $this->duaSasaran();
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->svc->prosesPotongan($batch, 0, $ids);
        $this->svc->prosesPotongan($batch, 0, $ids);

        $this->assertSame(2, (int) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Processed_Records'));
        $this->assertSame(2, DB::table(Svc::TABEL_CADANGAN)->where('Batch_Id', $batch)->where('Jenis', 'HEADER')->count());
    }

    public function test_header_yang_sudah_parameter_dilewati_tetapi_batch_tetap_selesai(): void
    {
        $ids = $this->duaSasaran();
        DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $ids[1])->update(['Metode_Cap' => 'PARAMETER']);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->update(['Total_Records' => 2]);

        $this->assertSame(1, $this->svc->prosesPotongan($batch, 0, $ids));
        $this->assertSame('ready', DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Status'));
    }

    public function test_rollback_memulihkan_identik_dan_kali_kedua_tanpa_efek(): void
    {
        $ids = $this->duaSasaran();
        $lama = $this->potret($ids);
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->svc->prosesPotongan($batch, 0, $ids);

        $rb = $this->svc->mulai('rollback', 'UJI-BF', $batch);
        $this->assertSame(2, $this->svc->pulihkanPotongan($rb, $batch, 0, $ids));
        $this->assertEquals($lama, $this->potret($ids), 'Rollback tidak mengembalikan nilai identik.');

        $rb2 = $this->svc->mulai('rollback', 'UJI-BF', $batch);
        $this->assertSame([], $this->svc->idUntukBatch($rb2), 'Rollback kedua masih menemukan header untuk dipulihkan.');
    }

    public function test_hanya_satu_batch_aktif(): void
    {
        $batch = $this->svc->mulai('jalankan', 'UJI-BF');
        $this->assertSame($batch, $this->svc->batchAktif()->Export_Id);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal**

Run: `php artisan test tests/Feature/KPI/Backfill/KpiCapParameterBackfillServiceTest.php`
Expected: FAIL — kelas belum ada (atau SKIPPED semua bila Task 1 Step 6 belum disetujui; jangan lanjut sebelum tabel ada di dev).

- [ ] **Step 3: Tulis service**

```php
<?php

namespace App\Services\KPI;

use App\Services\SYSTEM\Application\Exports\AbstractExportService as Tugas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill cap KPI ke metode PER PARAMETER untuk seluruh header lama.
 *
 * Dijalankan dari Postman (KpiBackfillCapController), diproses di background per
 * potongan 300 header. Rumus milik KpiCapParameterKalkulator → KpiScoringService.
 *
 * Penanda potongan (Jenis='POTONG', Id_Update_Performance = indeks potongan,
 * unik per batch) ditulis DI DALAM transaksi yang sama dengan perubahannya:
 * Cloud Tasks yang mengirim ulang potongan yang sudah selesai gagal menyisipkan
 * penanda dan keluar tanpa mengubah atau menghitung apa pun.
 *
 * Penulisan set-based lewat tabel sementara: Web_HRIS berjalan tanpa RCSI,
 * transaksi panjang memblokir pembaca.
 */
class KpiCapParameterBackfillService
{
    public const TASK_TYPE = 'kpi-backfill-cap';

    public const MODULE = 'kpi-backfill';

    public const TABEL_CADANGAN = 'N_KPI_Backfill_Cap_Cadangan';

    public const POTONGAN = 300;

    private const ID_PER_KUERI = 500;

    private const KOLOM_HEADER = ['Total_Score', 'Total_Score_Achievement', 'Total_Score_Raw', 'Total_Score_Achievement_Raw',
        'Flag_Capped', 'Metode_Cap', 'Cap_Min_Applied', 'Cap_Max_Applied', 'Id_Performance'];

    private const KOLOM_DETAIL = ['Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped'];

    public function __construct(private KpiCapParameterKalkulator $kalkulator) {}

    public function tabelCadanganAda(): bool
    {
        return Schema::hasTable(self::TABEL_CADANGAN);
    }

    public function idSasaran(): array
    {
        return DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->where(fn ($q) => $q->whereNull('Metode_Cap')->orWhere('Metode_Cap', '<>', 'PARAMETER'))
            ->orderBy('Id_Update_Performance')
            ->pluck('Id_Update_Performance')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    // ── baca ────────────────────────────────────────────────────────────

    /** @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection} header keyed id, detail grouped by header */
    private function muat(array $ids): array
    {
        $header = collect();
        $detail = collect();
        foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
            $header = $header->merge(DB::table('KPI_Update_Performance')->whereIn('Id_Update_Performance', $bagian)
                ->get(array_merge(['Id_Update_Performance', 'No_Transaksi', 'Periode', 'Flag_Approval', 'Flag_Aktif', 'Status'], self::KOLOM_HEADER)));
            $detail = $detail->merge(DB::table('KPI_Update_Performance_Detail as d')
                ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
                ->whereIn('d.Id_Update_Performance', $bagian)
                ->get(['d.Id_Update_Performance', 'd.Id_Update_Performance_Detail', 'd.Score', 'd.Achievement',
                    'd.Score_Raw', 'd.Achievement_Raw', 'd.Flag_Capped', 'pd.Bobot']));
        }

        return [$header->keyBy(fn ($h) => (int) $h->Id_Update_Performance), $detail->groupBy(fn ($d) => (int) $d->Id_Update_Performance)];
    }

    private function masihSasaran(object $h): bool
    {
        return $h->Flag_Aktif === 'Y' && $h->Status === null && $h->Metode_Cap !== 'PARAMETER';
    }

    public function pratinjau(): array
    {
        $ids = $this->idSasaran();
        $pita = $this->kalkulator->muatPita();
        $kode = DB::table('KPI_Master_Performance')->pluck('Kode', 'Id_Master_Performance');
        $scoring = app(KpiScoringService::class);

        $perStatus = [];
        $detailSasaran = $detailBerubah = $skorBerubah = 0;
        $pindah = [];
        $contoh = [];

        foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
            [$header, $detail] = $this->muat($bagian);
            foreach ($header as $id => $h) {
                $baris = $detail->get($id, collect());
                $hasil = $this->kalkulator->hitung($baris, $pita);
                $status = match ($h->Flag_Approval) { 'Y' => 'disetujui', 'T' => 'ditolak', default => 'menunggu' };
                $perStatus[$status] = ($perStatus[$status] ?? 0) + 1;
                $detailSasaran += $baris->count();
                foreach ($baris->values() as $i => $d) {
                    $b = $hasil['detail'][$i];
                    if (abs((float) $d->Score - $b['Score']) > 0.005 || abs((float) $d->Achievement - $b['Achievement']) > 0.005) {
                        $detailBerubah++;
                    }
                }
                if (abs((float) $h->Total_Score - $hasil['header']['Total_Score']) > 0.005) {
                    $skorBerubah++;
                }
                $dari = (int) $h->Id_Performance;
                $ke = $hasil['header']['Id_Performance'];
                if ($dari !== $ke) {
                    $kunci = ($kode[$dari] ?? $dari).'→'.($kode[$ke] ?? $ke);
                    $pindah[$kunci] = ($pindah[$kunci] ?? 0) + 1;
                    if (count($contoh) < 20) {
                        $contoh[] = ['no_transaksi' => $h->No_Transaksi, 'periode' => $h->Periode,
                            'skor_lama' => round((float) $h->Total_Score, 2), 'skor_baru' => $hasil['header']['Total_Score'],
                            'level_lama' => $kode[$dari] ?? $dari, 'level_baru' => $kode[$ke] ?? $ke];
                    }
                }
            }
        }
        arsort($pindah);
        [$max, $min] = $scoring->paramCapBounds();

        return [
            'batas' => ['min' => $min, 'max' => $max],
            'header_sasaran' => count($ids),
            'per_status' => $perStatus,
            'detail_sasaran' => $detailSasaran,
            'detail_berubah' => $detailBerubah,
            'header_skor_berubah' => $skorBerubah,
            'perpindahan_level' => collect($pindah)->map(fn ($n, $k) => ['dari' => explode('→', $k)[0], 'ke' => explode('→', $k)[1], 'jumlah' => $n])->values()->all(),
            'contoh' => $contoh,
        ];
    }

    // ── batch ───────────────────────────────────────────────────────────

    public function batchAktif(): ?object
    {
        return DB::table(Tugas::TABLE)->where('Task_Type', self::TASK_TYPE)->whereNull('Deleted_At')
            ->whereIn('Status', [Tugas::STATUS_QUEUED, Tugas::STATUS_PROCESSING])->first();
    }

    public function mulai(string $mode, ?string $requestedBy, ?string $sumberBatch = null): string
    {
        $id = ($mode === 'rollback' ? 'kpi-bfr-' : 'kpi-bf-').now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
        $now = now();
        DB::table(Tugas::TABLE)->insert([
            'Export_Id' => $id, 'Task_Type' => self::TASK_TYPE, 'Module' => self::MODULE, 'Format' => $mode,
            'Payload_Json' => json_encode(['mode' => $mode, 'sumber' => $sumberBatch]),
            'Status' => Tugas::STATUS_QUEUED, 'Total_Records' => 0, 'Processed_Records' => 0, 'Percent' => 0,
            'Requested_By' => $requestedBy, 'Created_At' => $now, 'Updated_At' => $now,
        ]);

        return $id;
    }

    private function payload(string $batchId): array
    {
        return json_decode((string) DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->value('Payload_Json'), true) ?: [];
    }

    public function idUntukBatch(string $batchId): array
    {
        $p = $this->payload($batchId);
        if (($p['mode'] ?? null) !== 'rollback') {
            return $this->idSasaran();
        }

        return DB::table(self::TABEL_CADANGAN)->where('Batch_Id', $p['sumber'])->where('Jenis', 'HEADER')
            ->whereNull('Dipulihkan_At')->orderBy('Id_Update_Performance')
            ->pluck('Id_Update_Performance')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function setelTotal(string $batchId, int $total): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Total_Records' => $total, 'Status' => $total === 0 ? Tugas::STATUS_READY : Tugas::STATUS_PROCESSING,
            'Percent' => $total === 0 ? 100 : 0, 'Started_At' => now(), 'Completed_At' => $total === 0 ? now() : null, 'Updated_At' => now(),
        ]);
    }

    /** Penanda potongan; false = potongan ini sudah pernah diproses. */
    private function klaimPotongan(string $batchId, int $indeks): bool
    {
        try {
            DB::table(self::TABEL_CADANGAN)->insert(['Batch_Id' => $batchId, 'Jenis' => 'POTONG', 'Id_Update_Performance' => $indeks]);

            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'UX_KPI_Backfill_Cap_Potong')) {
                return false;
            }
            throw $e;
        }
    }

    private function majukan(string $batchId, int $n): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Processed_Records' => DB::raw('Processed_Records + '.(int) $n), 'Updated_At' => now(),
        ]);
        $t = DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->first(['Total_Records', 'Processed_Records']);
        $selesai = (int) $t->Processed_Records >= (int) $t->Total_Records;
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Percent' => (int) $t->Total_Records > 0 ? min(100, (int) round($t->Processed_Records / $t->Total_Records * 100)) : 100,
            'Status' => $selesai ? Tugas::STATUS_READY : Tugas::STATUS_PROCESSING,
            'Completed_At' => $selesai ? now() : null,
        ]);
    }

    public function tandaiGagal(string $batchId, string $pesan): void
    {
        DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->update([
            'Status' => Tugas::STATUS_FAILED, 'Error_Message' => mb_substr($pesan, 0, 1000), 'Completed_At' => now(), 'Updated_At' => now(),
        ]);
    }

    // ── tulis ───────────────────────────────────────────────────────────

    public function prosesPotongan(string $batchId, int $indeks, array $ids): int
    {
        return DB::transaction(function () use ($batchId, $indeks, $ids) {
            if (! $this->klaimPotongan($batchId, $indeks)) {
                return 0;
            }
            [$header, $detail] = $this->muat($ids);
            $pita = $this->kalkulator->muatPita();
            $cadangan = $hasilHeader = $hasilDetail = [];

            foreach ($header as $id => $h) {
                if (! $this->masihSasaran($h)) {
                    continue;
                }
                $baris = $detail->get($id, collect());
                $hasil = $this->kalkulator->hitung($baris, $pita);
                $cadangan[] = ['Batch_Id' => $batchId, 'Jenis' => 'HEADER', 'Id_Update_Performance' => $id, 'Id_Update_Performance_Detail' => null,
                    'Nilai_Lama_Json' => json_encode(collect((array) $h)->only(self::KOLOM_HEADER)->all())];
                foreach ($baris as $d) {
                    $cadangan[] = ['Batch_Id' => $batchId, 'Jenis' => 'DETAIL', 'Id_Update_Performance' => $id,
                        'Id_Update_Performance_Detail' => (int) $d->Id_Update_Performance_Detail,
                        'Nilai_Lama_Json' => json_encode(collect((array) $d)->only(self::KOLOM_DETAIL)->all())];
                }
                $hasilHeader[] = ['Id' => $id] + $hasil['header'];
                foreach ($hasil['detail'] as $b) {
                    $hasilDetail[] = $b;
                }
            }

            foreach (array_chunk($cadangan, 300) as $c) {
                DB::table(self::TABEL_CADANGAN)->insert($c);
            }
            $this->tulisDetail($hasilDetail);
            $this->tulisHeader($hasilHeader);
            $this->majukan($batchId, count($ids));

            return count($hasilHeader);
        });
    }

    public function pulihkanPotongan(string $batchId, string $sumberBatch, int $indeks, array $ids): int
    {
        return DB::transaction(function () use ($batchId, $sumberBatch, $indeks, $ids) {
            if (! $this->klaimPotongan($batchId, $indeks)) {
                return 0;
            }
            $baris = collect();
            foreach (array_chunk($ids, self::ID_PER_KUERI) as $bagian) {
                $baris = $baris->merge(DB::table(self::TABEL_CADANGAN)->where('Batch_Id', $sumberBatch)
                    ->whereIn('Jenis', ['HEADER', 'DETAIL'])->whereNull('Dipulihkan_At')
                    ->whereIn('Id_Update_Performance', $bagian)->get());
            }
            $hdr = $baris->where('Jenis', 'HEADER')->map(fn ($r) => ['Id' => (int) $r->Id_Update_Performance] + json_decode($r->Nilai_Lama_Json, true))->values()->all();
            $det = $baris->where('Jenis', 'DETAIL')->map(fn ($r) => ['Id_Update_Performance_Detail' => (int) $r->Id_Update_Performance_Detail] + json_decode($r->Nilai_Lama_Json, true))->values()->all();

            $this->tulisDetail($det);
            $this->tulisHeader($hdr);
            foreach ($baris->pluck('Id_Cadangan')->chunk(self::ID_PER_KUERI) as $c) {
                DB::table(self::TABEL_CADANGAN)->whereIn('Id_Cadangan', $c->all())->update(['Dipulihkan_At' => now()]);
            }
            $this->majukan($batchId, count($ids));

            return count($hdr);
        });
    }

    /** Tulis nilai lewat tabel sementara + satu UPDATE…FROM, bukan per baris. */
    private function tulisDetail(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        DB::statement('CREATE TABLE #bf_detail (Id int PRIMARY KEY, Score float NULL, Achievement float NULL, Score_Raw float NULL, Achievement_Raw float NULL, Flag_Capped char(1) NULL)');
        try {
            foreach (array_chunk($rows, 300) as $c) {
                DB::table('#bf_detail')->insert(array_map(fn ($r) => ['Id' => $r['Id_Update_Performance_Detail'], 'Score' => $r['Score'],
                    'Achievement' => $r['Achievement'], 'Score_Raw' => $r['Score_Raw'], 'Achievement_Raw' => $r['Achievement_Raw'],
                    'Flag_Capped' => $r['Flag_Capped']], $c));
            }
            DB::statement('UPDATE d SET d.Score = t.Score, d.Achievement = t.Achievement, d.Score_Raw = t.Score_Raw,
                d.Achievement_Raw = t.Achievement_Raw, d.Flag_Capped = t.Flag_Capped
                FROM KPI_Update_Performance_Detail d JOIN #bf_detail t ON t.Id = d.Id_Update_Performance_Detail');
        } finally {
            DB::statement('DROP TABLE #bf_detail');
        }
    }

    private function tulisHeader(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        DB::statement('CREATE TABLE #bf_header (Id int PRIMARY KEY, Total_Score float NULL, Total_Score_Achievement float NULL,
            Total_Score_Raw float NULL, Total_Score_Achievement_Raw float NULL, Flag_Capped char(1) NULL, Metode_Cap varchar(20) NULL,
            Cap_Min_Applied decimal(9,2) NULL, Cap_Max_Applied decimal(9,2) NULL, Id_Performance int NULL)');
        try {
            foreach (array_chunk($rows, 150) as $c) {
                DB::table('#bf_header')->insert(array_map(fn ($r) => collect($r)->only(array_merge(['Id'], self::KOLOM_HEADER))->all(), $c));
            }
            DB::statement('UPDATE h SET h.Total_Score = t.Total_Score, h.Total_Score_Achievement = t.Total_Score_Achievement,
                h.Total_Score_Raw = t.Total_Score_Raw, h.Total_Score_Achievement_Raw = t.Total_Score_Achievement_Raw,
                h.Flag_Capped = t.Flag_Capped, h.Metode_Cap = t.Metode_Cap, h.Cap_Min_Applied = t.Cap_Min_Applied,
                h.Cap_Max_Applied = t.Cap_Max_Applied, h.Id_Performance = t.Id_Performance
                FROM KPI_Update_Performance h JOIN #bf_header t ON t.Id = h.Id_Update_Performance');
        } finally {
            DB::statement('DROP TABLE #bf_header');
        }
    }

    public function status(string $batchId): ?array
    {
        $t = DB::table(Tugas::TABLE)->where('Export_Id', $batchId)->where('Task_Type', self::TASK_TYPE)->first();
        if (! $t) {
            return null;
        }
        $p = json_decode((string) $t->Payload_Json, true) ?: [];

        return ['batch_id' => $t->Export_Id, 'mode' => $p['mode'] ?? null, 'sumber' => $p['sumber'] ?? null,
            'status' => $t->Status, 'total' => (int) $t->Total_Records, 'diproses' => (int) $t->Processed_Records,
            'persen' => (int) $t->Percent, 'mulai' => $t->Started_At, 'selesai' => $t->Completed_At, 'galat' => $t->Error_Message];
    }
}
```

Catatan uji `test_header_yang_sudah_parameter_dilewati…`: `mulai()` membuat `Total_Records=0`; uji menyetel 2 secara langsung; di produksi `setelTotal()` dipanggil job induk (Task 4).

- [ ] **Step 4: Jalankan uji**

Run: `php artisan test tests/Feature/KPI/Backfill/KpiCapParameterBackfillServiceTest.php`
Expected: PASS. Bila `assertEquals($lama, …)` gagal karena presisi float (`Score` disimpan ulang dari JSON), bandingkan kolom float dengan `assertEqualsWithDelta(…, 1e-9)` per kolom — jangan longgarkan ke 0,01.

- [ ] **Step 5: Bukti penjaga** — ganti sementara `if (! $this->klaimPotongan(...)) return 0;` menjadi tanpa pengecekan; jalankan `--filter=terkirim_ulang`; Expected: FAIL. Kembalikan.

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/KpiCapParameterBackfillService.php tests/Feature/KPI/Backfill/KpiCapParameterBackfillServiceTest.php
git commit -m "feat(kpi): service backfill cap per parameter — pratinjau, potongan, cadangan, rollback"
```

---

### Task 4: Job induk & job potongan

**Files:**
- Create: `app/Jobs/KPI/Monitoring/Enforcement/BackfillCapParameterBatchJob.php`
- Create: `app/Jobs/KPI/Monitoring/Enforcement/BackfillCapParameterChunkJob.php`
- Test: `tests/Feature/KPI/Backfill/BackfillCapParameterJobTest.php`

**Interfaces:**
- Consumes: `idUntukBatch`, `setelTotal`, `prosesPotongan`, `pulihkanPotongan`, `tandaiGagal` (Task 3).
- Produces: `BackfillCapParameterBatchJob::__construct(string $batchId)`; `BackfillCapParameterChunkJob::__construct(string $batchId, int $indeks, array $ids)`. Antrean: `config('queue.names.kpi_enforcement')`.

- [ ] **Step 1: Tulis uji gagal**

```php
<?php

namespace Tests\Feature\KPI\Backfill;

use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterChunkJob;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillCapParameterJobTest extends TestCase
{
    use DatabaseTransactions;

    public function test_induk_memecah_sasaran_per_300_dan_menyetel_total(): void
    {
        $svc = app(Svc::class);
        if (! $svc->tabelCadanganAda()) {
            $this->markTestSkipped('DDL-2026-167 belum dijalankan.');
        }
        Bus::fake([BackfillCapParameterChunkJob::class]);
        $batch = $svc->mulai('jalankan', 'UJI-BF');
        $n = count($svc->idSasaran());

        (new BackfillCapParameterBatchJob($batch))->handle($svc);

        Bus::assertDispatchedTimes(BackfillCapParameterChunkJob::class, (int) ceil($n / Svc::POTONGAN));
        $this->assertSame($n, (int) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Total_Records'));
    }

    public function test_tanpa_sasaran_batch_langsung_selesai(): void
    {
        $svc = $this->partialMock(Svc::class, fn ($m) => $m->shouldReceive('idUntukBatch')->andReturn([]));
        Bus::fake([BackfillCapParameterChunkJob::class]);
        $batch = $svc->mulai('jalankan', 'UJI-BF');

        (new BackfillCapParameterBatchJob($batch))->handle($svc);

        Bus::assertNotDispatched(BackfillCapParameterChunkJob::class);
        $this->assertSame('ready', DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $batch)->value('Status'));
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test tests/Feature/KPI/Backfill/BackfillCapParameterJobTest.php` → FAIL (kelas tak ada).

- [ ] **Step 3: Tulis job induk**

```php
<?php

namespace App\Jobs\KPI\Monitoring\Enforcement;

use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Pecah sasaran backfill cap jadi potongan 300 header, satu job per potongan. */
class BackfillCapParameterBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    public $tries = 3;

    public function __construct(protected string $batchId)
    {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiCapParameterBackfillService $svc): void
    {
        $ids = $svc->idUntukBatch($this->batchId);
        $svc->setelTotal($this->batchId, count($ids));

        foreach (array_chunk($ids, KpiCapParameterBackfillService::POTONGAN) as $indeks => $potongan) {
            BackfillCapParameterChunkJob::dispatch($this->batchId, $indeks, $potongan);
        }
    }

    public function failed(\Throwable $e): void
    {
        app(KpiCapParameterBackfillService::class)->tandaiGagal($this->batchId, 'Job induk gagal: '.$e->getMessage());
    }
}
```

- [ ] **Step 4: Tulis job potongan**

```php
<?php

namespace App\Jobs\KPI\Monitoring\Enforcement;

use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/** Proses (atau pulihkan) satu potongan header backfill cap. */
class BackfillCapParameterChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public function __construct(protected string $batchId, protected int $indeks, protected array $ids)
    {
        $this->onQueue((string) config('queue.names.kpi_enforcement'));
    }

    public function handle(KpiCapParameterBackfillService $svc): void
    {
        $payload = json_decode((string) DB::table('N_HRIS_Async_Tasks')->where('Export_Id', $this->batchId)->value('Payload_Json'), true) ?: [];

        if (($payload['mode'] ?? null) === 'rollback') {
            $svc->pulihkanPotongan($this->batchId, (string) $payload['sumber'], $this->indeks, $this->ids);

            return;
        }

        $svc->prosesPotongan($this->batchId, $this->indeks, $this->ids);
    }

    public function failed(\Throwable $e): void
    {
        app(KpiCapParameterBackfillService::class)->tandaiGagal(
            $this->batchId, "Potongan {$this->indeks} gagal: ".$e->getMessage(),
        );
    }
}
```

- [ ] **Step 5: Jalankan uji** — `php artisan test tests/Feature/KPI/Backfill` → PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Jobs/KPI/Monitoring/Enforcement/BackfillCapParameter*.php tests/Feature/KPI/Backfill/BackfillCapParameterJobTest.php
git commit -m "feat(kpi): job induk & potongan backfill cap per parameter"
```

---

### Task 5: Endpoint Postman, token gagal-tertutup, endpoint lama 410

**Files:**
- Create: `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiBackfillCapController.php`
- Modify: `routes/web.php` (blok backfill sekitar baris 631–637)
- Modify: `app/Http/Middleware/VerifyCsrfToken.php` (`$except`)
- Modify: `config/services.php` (`backfill_token`: hapus nilai cadangan)
- Modify: `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiEnforcementController.php` (hapus `backfillCap()`, properti/injeksi `KpiCapBackfillService`, `use`)
- Delete: `app/Services/KPI/KpiCapBackfillService.php`
- Modify: `tests/Feature/KPI/PenandaMetodeCapTest.php` (ganti uji backfill lama)
- Modify: `app/Console/Commands/KPI/Operations/CekBandPerformance.php:15` (hapus rujukan `KpiCapBackfillService:123` dari komentar)
- Test: `tests/Feature/KPI/Backfill/KpiBackfillCapEndpointTest.php`

**Interfaces:**
- Consumes: service Task 3, job induk Task 4.
- Produces (HTTP, semua ber-header `X-Backfill-Token`):
  - `POST /api/v1/kpi-enforcement/backfill-cap-parameter` `{mode?: 'pratinjau'|'jalankan', requested_by?: string}` → 200 `result = pratinjau()`; `jalankan` → 202 `{batch_id}`; 409 bila batch aktif; 422 bila tabel cadangan tak ada.
  - `GET /api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}` → 200 `status()` | 404.
  - `POST /api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}/rollback` `{requested_by?}` → 202 `{batch_id}` | 404 | 409.
  - `POST /api/v1/kpi-enforcement/backfill-cap` → 410.

- [ ] **Step 1: Tulis uji gagal**

```php
<?php

namespace Tests\Feature\KPI\Backfill;

use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class KpiBackfillCapEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/api/v1/kpi-enforcement/backfill-cap-parameter';

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('http://localhost');
        config(['services.kpi.backfill_token' => 'token-uji']);
    }

    private function hdr(string $t = 'token-uji'): array
    {
        return ['X-Backfill-Token' => $t, 'Accept' => 'application/json'];
    }

    public function test_tanpa_atau_salah_token_ditolak(): void
    {
        $this->postJson(self::URL, [], ['Accept' => 'application/json'])->assertStatus(401);
        $this->postJson(self::URL, [], $this->hdr('salah'))->assertStatus(401);
    }

    public function test_token_kosong_di_config_menolak_segalanya(): void
    {
        config(['services.kpi.backfill_token' => null]);
        $this->postJson(self::URL, [], $this->hdr(''))->assertStatus(401);
        $this->postJson(self::URL, [], $this->hdr('apa-saja'))->assertStatus(401);

        // Tak boleh ada nilai cadangan di config: tanpa env, token = null (gagal-tertutup).
        $isi = file_get_contents(base_path('config/services.php'));
        $this->assertDoesNotMatchRegularExpression("/env\(\s*'KPI_BACKFILL_TOKEN'\s*,/", $isi,
            'KPI_BACKFILL_TOKEN punya nilai cadangan — endpoint tak pernah gagal-tertutup.');
    }

    public function test_jalankan_ditolak_bila_tabel_cadangan_belum_ada(): void
    {
        $this->partialMock(Svc::class, fn ($m) => $m->shouldReceive('tabelCadanganAda')->andReturn(false));

        $this->postJson(self::URL, ['mode' => 'jalankan'], $this->hdr())->assertStatus(422);
        $this->postJson(self::URL, [], $this->hdr())->assertOk(); // pratinjau tetap jalan
    }

    public function test_pratinjau_adalah_mode_baku(): void
    {
        $res = $this->postJson(self::URL, [], $this->hdr())->assertOk();
        $this->assertArrayHasKey('header_sasaran', $res->json('result'));
    }

    public function test_jalankan_menjadwalkan_job_induk_dan_menolak_batch_kedua(): void
    {
        if (! app(Svc::class)->tabelCadanganAda()) {
            $this->markTestSkipped('DDL-2026-167 belum dijalankan.');
        }
        Bus::fake([BackfillCapParameterBatchJob::class]);

        $res = $this->postJson(self::URL, ['mode' => 'jalankan', 'requested_by' => 'H45'], $this->hdr())->assertStatus(202);
        Bus::assertDispatched(BackfillCapParameterBatchJob::class);
        $this->getJson(self::URL.'/'.$res->json('result.batch_id'), $this->hdr())->assertOk()->assertJsonPath('result.mode', 'jalankan');

        $this->postJson(self::URL, ['mode' => 'jalankan'], $this->hdr())->assertStatus(409);
    }

    public function test_endpoint_lama_ditarik(): void
    {
        $this->postJson('/api/v1/kpi-enforcement/backfill-cap', [], $this->hdr())->assertStatus(410);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — `php artisan test tests/Feature/KPI/Backfill/KpiBackfillCapEndpointTest.php` → FAIL (404 rute).

- [ ] **Step 3: Tulis controller**

```php
<?php

namespace App\Http\Controllers\KPI\Monitoring\Enforcement;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\Monitoring\Enforcement\BackfillCapParameterBatchJob;
use App\Services\KPI\KpiCapParameterBackfillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Backfill cap KPI per parameter — operasi migrasi yang di-hit dari Postman.
 * Di luar grup auth, dijaga X-Backfill-Token (gagal-tertutup bila config kosong).
 */
class KpiBackfillCapController extends Controller
{
    public function __construct(private KpiCapParameterBackfillService $svc) {}

    private function tokenSah(Request $request): bool
    {
        $harap = config('services.kpi.backfill_token');
        $kirim = $request->header('X-Backfill-Token');

        return is_string($harap) && $harap !== '' && is_string($kirim) && hash_equals($harap, $kirim);
    }

    public function jalankan(Request $request)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $v = $request->validate([
            'mode' => ['nullable', 'in:pratinjau,jalankan'],
            'requested_by' => ['nullable', 'string', 'max:30'],
        ]);

        if (($v['mode'] ?? 'pratinjau') === 'pratinjau') {
            return ResponseHelper::success($this->svc->pratinjau(), 'Pratinjau backfill cap (tidak ada perubahan ditulis).');
        }

        return $this->mulai('jalankan', $v['requested_by'] ?? null);
    }

    public function rollback(Request $request, string $batchId)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $s = $this->svc->status($batchId);
        if (! $s || $s['mode'] !== 'jalankan') {
            return ResponseHelper::error('Batch backfill tidak ditemukan.', 404);
        }

        return $this->mulai('rollback', $request->input('requested_by'), $batchId);
    }

    public function status(Request $request, string $batchId)
    {
        if (! $this->tokenSah($request)) {
            return ResponseHelper::error('Token tidak valid.', 401);
        }
        $s = $this->svc->status($batchId);

        return $s ? ResponseHelper::success($s, 'Status batch backfill cap.') : ResponseHelper::error('Batch tidak ditemukan.', 404);
    }

    private function mulai(string $mode, ?string $requestedBy, ?string $sumber = null)
    {
        if (! $this->svc->tabelCadanganAda()) {
            return ResponseHelper::error('Tabel cadangan (DDL-2026-167) belum ada. Jalankan DDL-nya dulu.', 422);
        }
        if ($aktif = $this->svc->batchAktif()) {
            return ResponseHelper::error("Masih ada batch backfill berjalan: {$aktif->Export_Id}.", 409);
        }

        $batchId = $this->svc->mulai($mode, $requestedBy, $sumber);
        BackfillCapParameterBatchJob::dispatch($batchId);
        Log::channel('UpdatePerformanceController')->info('KPI_BACKFILL_CAP_PARAMETER', compact('batchId', 'mode', 'requestedBy', 'sumber'));

        return ResponseHelper::success(['batch_id' => $batchId], 'Backfill dijadwalkan.', 202);
    }

    /** Endpoint lama (cap TOTAL) sudah diganti metodenya. */
    public function ditarik()
    {
        return ResponseHelper::error(
            'Endpoint ini menjepit TOTAL (metode lama) dan sudah ditarik. Pakai POST /api/v1/kpi-enforcement/backfill-cap-parameter.',
            410,
        );
    }
}
```

- [ ] **Step 4: Rute, CSRF, token** — di `routes/web.php` ganti blok rute lama dengan:

```php
// Backfill cap KPI per parameter — operasi migrasi, di-hit dari Postman.
// Sengaja DI LUAR grup ['auth']: dijaga X-Backfill-Token di controller.
// Dikecualikan dari CSRF (VerifyCsrfToken).
Route::controller(\App\Http\Controllers\KPI\Monitoring\Enforcement\KpiBackfillCapController::class)->group(function () {
    Route::post('/api/v1/kpi-enforcement/backfill-cap-parameter', 'jalankan');
    Route::get('/api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}', 'status');
    Route::post('/api/v1/kpi-enforcement/backfill-cap-parameter/{batchId}/rollback', 'rollback');
    Route::post('/api/v1/kpi-enforcement/backfill-cap', 'ditarik');
});
```

Di `VerifyCsrfToken::$except` tambahkan `'api/v1/kpi-enforcement/backfill-cap-parameter'` dan `'api/v1/kpi-enforcement/backfill-cap-parameter/*'` (pertahankan `'api/v1/kpi-enforcement/backfill-cap'`).

Di `config/services.php` ganti blok token menjadi:

```php
        // Secret token endpoint backfill cap (Postman). TANPA nilai cadangan:
        // tanpa KPI_BACKFILL_TOKEN di env, endpoint menolak semua permintaan.
        'backfill_token' => env('KPI_BACKFILL_TOKEN'),
```

- [ ] **Step 5: Hapus metode lama** — di `KpiEnforcementController` hapus `use App\Services\KPI\KpiCapBackfillService;`, parameter konstruktor `protected KpiCapBackfillService $backfillService,` dan seluruh metode `backfillCap()`; `git rm app/Services/KPI/KpiCapBackfillService.php`; ubah komentar `CekBandPerformance.php:15` menjadi `KpiScoringService:130, KpiEnforcementService:663, KpiCapParameterKalkulator`.

- [ ] **Step 6: Sesuaikan `PenandaMetodeCapTest`** — hapus konstanta `BACKFILL` dan uji `backfill_menolak_record_bermetode_parameter`; tambahkan `'app/Services/KPI/KpiCapParameterKalkulator.php'` ke `PENULIS_HEADER`; tambahkan:

```php
    /** @test */
    public function backfill_hanya_menyasar_header_yang_belum_parameter(): void
    {
        $isi = file_get_contents(base_path('app/Services/KPI/KpiCapParameterBackfillService.php'));
        $this->assertStringContainsString("->orWhere('Metode_Cap', '<>', 'PARAMETER')", $isi,
            'Backfill tidak lagi menyaring Metode_Cap — record yang sudah benar ikut dihitung ulang.');
        $this->assertStringContainsString("Metode_Cap !== 'PARAMETER'", $isi,
            'Potongan tidak memeriksa ulang sasaran — header yang berubah sesudah pencacahan ikut ditimpa.');
    }
```

- [ ] **Step 7: Jalankan uji**

Run: `php artisan route:clear; php artisan test tests/Feature/KPI/Backfill tests/Feature/KPI/PenandaMetodeCapTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A app/Http app/Services/KPI app/Console/Commands/KPI/Operations/CekBandPerformance.php routes/web.php config/services.php tests/Feature/KPI
git commit -m "feat(kpi): endpoint backfill cap per parameter; token gagal-tertutup; endpoint cap total ditarik"
```

---

### Task 6: Tampil di panel Tugas & Aktivitas

**Files:**
- Modify: `app/Services/ESS/Activity/Center/ActivityCenterService.php` (`TIPE_TUGAS`, `list()`, `summary()`, adapter baru)
- Modify: `app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php` (`sembunyikanSatu`)
- Test: `tests/Feature/KPI/Backfill/KpiBackfillCapPanelTest.php`

**Interfaces:**
- Consumes: `KpiCapParameterBackfillService::TASK_TYPE`, baris yang dibuat `mulai()`.
- Produces: item panel `type='kpi-backfill-cap'`, `dismiss_url=/api/v1/activities/kpi-backfill-cap/{id}/dismiss`, label `Backfill Cap KPI · {N} header ({persen}%)` / `Rollback Backfill Cap KPI · …`.

- [ ] **Step 1: Tulis uji gagal**

```php
<?php

namespace Tests\Feature\KPI\Backfill;

use App\Services\ESS\Activity\Center\ActivityCenterService;
use App\Services\KPI\KpiCapParameterBackfillService as Svc;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KpiBackfillCapPanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_batch_tampil_di_panel_peminta(): void
    {
        $batch = app(Svc::class)->mulai('jalankan', 'UJI-PANEL');

        $item = collect(app(ActivityCenterService::class)->forUser('UJI-PANEL')->list(50))->firstWhere('id', $batch);

        $this->assertNotNull($item, 'Batch backfill tidak tampil di panel Tugas & Aktivitas.');
        $this->assertSame('kpi-backfill-cap', $item['type']);
        $this->assertStringContainsString('Backfill Cap KPI', $item['label']);
    }
}
```

- [ ] **Step 2: Jalankan, pastikan gagal** — FAIL (item null).

- [ ] **Step 3: Implementasi** — tambahkan `\App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE` ke `TIPE_TUGAS`; tambahkan `$this->kpiBackfillCapItems()` ke `array_merge` di `list()` **dan** `summary()`; tambahkan metode setelah `kpiAutoCapItems()`:

```php
    private function kpiBackfillCapItems(): array
    {
        return $this->tugasAsync(\App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE)->map(function ($job) {
            $mode = (json_decode($job->Payload_Json ?: '{}', true) ?: [])['mode'] ?? 'jalankan';
            $label = ($mode === 'rollback' ? 'Rollback Backfill Cap KPI' : 'Backfill Cap KPI').' · '.(int) $job->Total_Records.' header';
            if ($job->Status === AbstractExportService::STATUS_PROCESSING) {
                $label .= " ({$job->Percent}%)";
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= ' · Selesai';
            } elseif ($job->Status === AbstractExportService::STATUS_FAILED) {
                $label .= ' · Gagal';
            }

            return array_merge(AbstractExportService::mapRow($job, $label), [
                'id' => $job->Export_Id,
                'type' => 'kpi-backfill-cap',
                'status' => $job->Status,
                'percent' => (int) $job->Percent,
                'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
                'downloaded' => false,
                'download_url' => null,
                'retry_url' => null,
                'dismiss_url' => "/api/v1/activities/kpi-backfill-cap/{$job->Export_Id}/dismiss",
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }
```

Di `ActivityCenterController::sembunyikanSatu`, sebelum `if ($type !== 'export')`:

```php
        if ($type === 'kpi-backfill-cap') {
            DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)
                ->where('Task_Type', \App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE)
                ->update(['Deleted_At' => now()]);

            return true;
        }
```

- [ ] **Step 4: Jalankan uji** — `php artisan test tests/Feature/KPI/Backfill/KpiBackfillCapPanelTest.php tests/Feature/SYSTEM/ActivityCenterSatuKueriTest.php tests/Feature/KPI/Monitoring/KpiAutoCapActivityCenterTest.php` → PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ESS/Activity/Center/ActivityCenterService.php app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php tests/Feature/KPI/Backfill/KpiBackfillCapPanelTest.php
git commit -m "feat(kpi): batch backfill cap tampil di panel Tugas & Aktivitas"
```

---

### Task 7: Verifikasi end-to-end di dev, gaya, dan PR

**Files:** tidak ada berkas baru (kecuali pembaruan `status.json`/README DDL-167 bila ada hasil baru).

- [ ] **Step 1: Pint** — `vendor/bin/pint --test` untuk semua berkas PHP yang berubah → PASS.

- [ ] **Step 2: Suite terkait** — `php artisan test tests/Feature/KPI tests/Feature/SYSTEM/ActivityCenterSatuKueriTest.php`. Expected: semua uji baru PASS; daftar gagal lain **identik** dengan `master` (bandingkan seperti pada 12 Okt: 20 gagal bawaan di `Performance`/`Planning`).

- [ ] **Step 3: E2E di dev lewat HTTP nyata** (server `php artisan serve --port=5678` + tunnel hidup, `KPI_BACKFILL_TOKEN` di `.env` dev):
  1. `POST …/backfill-cap-parameter` → catat `header_sasaran`, `perpindahan_level`.
  2. `POST … {mode:jalankan, requested_by:H45}` → 202; pantau `GET …/{batch}` sampai `ready`; panel H45 menampilkan progres.
  3. Verifikasi: `idSasaran()` = []; sampel 20 header → hasil sama dengan `KpiCapParameterKalkulator::hitung`; jumlah baris `HEADER` di cadangan = `header_sasaran`.
  4. `POST …/{batch}/rollback` → `ready`; nilai kembali identik (bandingkan potret 20 header sebelum langkah 2).
  5. Jalankan langkah 2 lagi sehingga dev berakhir dalam keadaan ter-backfill (sesuai keputusan "semua periode").
  Catat angka-angka ini di README DDL-167 bagian "Hasil dev".

- [ ] **Step 4: Push & PR** — `git push -u origin feat/kpi/backfill-cap-per-parameter`; buka PR ke `master` berisi ringkasan, urutan rilis (DDL-167 di produksi **sebelum** dipakai; set `KPI_BACKFILL_TOKEN` di env Cloud Run — config kini tanpa nilai cadangan), cara pakai Postman, dan hasil uji. Merge hanya atas perintah user.
