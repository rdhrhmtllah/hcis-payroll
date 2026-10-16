# Cap KPI per parameter — Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkah memakai sintaks kotak centang (`- [ ]`).

**Goal:** Memindahkan penjepitan skor KPI dari total akhir ke achievement tiap parameter, sehingga parameter yang melampaui target tidak lagi menutupi parameter yang tidak tercapai.

**Architecture:** Penjepitan dipasang di satu titik — `KpiScoringService::scoreParameter()` — yang dilewati seluruh perhitungan per-parameter di backend. Yang dijepit adalah rasio hasil rumus; achievement dan skor sama-sama diturunkan dari rasio yang sudah dijepit. Angka sebelum penjepitan disimpan di kolom mentah baru pada dua tabel detail, dan kolom audit di header berpindah sumber dari penjepitan total ke penjumlahan kolom mentah itu.

**Tech Stack:** Laravel 11 (PHP 8.2), SQL Server (Web_HRIS), Vue 3 Options API + Inertia, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-28-kpi-cap-per-parameter-design.md`

## Global Constraints

- Batas cap: **maksimum 125%, minimum 50%**, dinyatakan dalam satuan achievement (persen), bukan skor.
- Kosakata wajib: **achievement** = rasio × 100, disimpan di kolom `Score`. **skor** = bobot × rasio, disimpan di kolom `Achievement`. Kolom DB tertukar dari namanya dan **tidak** diganti namanya dalam pekerjaan ini.
- Data lama **tidak dihitung ulang**. Aturan berlaku untuk perhitungan yang terjadi setelah rilis.
- Cap total di header **tidak dicabut**; tetap tinggal sebagai jaring pengaman yang tidak akan menggigit.
- Larangan `AGENTS.md`: agen tidak pernah menjalankan `DROP`/`TRUNCATE`, tidak memakai migration Laravel, dan hanya mengeksekusi forward DDL yang sudah disetujui ke database pengembangan.
- Jangan pernah `git add -A` atau `git add .`; tambahkan berkas per jalur eksplisit.
- Perintah artisan dijalankan dengan `env -u DATABASE_URL`.
- Tes backend menembak DB pengembangan. Tes unit di rencana ini dirancang **tanpa sentuhan DB** supaya cepat dan tidak mengunci UI pengguna.

**Target database:** `Web_HRIS` (pengembangan) pada host team311, terverifikasi 2026-08-28 lewat `SELECT DB_NAME()`.

**Fakta terverifikasi dari DB pengembangan (2026-08-28):**

| Objek | Fakta |
|---|---|
| `KPI_Update_Performance_Detail.Score` / `.Achievement` | `float` NULL |
| `KPI_Update_Performance_Detail_Sementara.Score` / `.Achievement` | `float` NULL |
| `KPI_Update_Performance.Flag_Capped` | `char(1)` NULL default `('T')` |
| `KPI_Update_Performance.Total_Score_Raw` / `Total_Score_Achievement_Raw` / `Cap_Min_Applied` / `Cap_Max_Applied` | `decimal(18,2)` NULL |
| Jumlah baris | Detail 305, Detail_Sementara 365, Header 116 |
| Edisi server | SQL Server Express |
| Cap efektif sekarang | max 125, min 50 |
| `KPI_Master_Grading.Nilai_Maksimum` (ambang PICA) | 100 |
| Band `KPI_Master_Performance` | 0–74 NOT PERFORM, 75–89 SATISFACTORY, 90–105 GOOD, 106–999 EXCELLENT |

---

## Struktur berkas

**Dibuat:**

| Berkas | Tanggung jawab |
|---|---|
| `tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php` | Seluruh perilaku penjepitan per parameter dan agregasinya. Tanpa DB. |
| `resources/js/Pages/KPI/utils/kpiCap.js` | Satu-satunya penjepit rasio di sisi klien. Dipakai lima halaman. |
| `resources/js/Pages/KPI/utils/kpiCap.test.js` | Tes vitest untuk penjepit klien. |
| `database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql` | DDL maju. |
| `database/ddl/DDL-2026-045-kpi-cap-per-parameter/drop.sql` | Alat bersih-bersih manual. **Tidak pernah dijalankan agen.** |
| `docs/changes/DDL-2026-045-kpi-cap-per-parameter/README.md` | Dokumentasi perubahan. |

Deklarasi penempatan berkas baru sesuai `AGENTS.md`: modul **KPI**, submodul **Performance**, fitur **Update Performance — penilaian**, lapisan **Service/Util/Test**. Folder `resources/js/Pages/KPI/utils/` baru dibuat di sini; ia milik modul KPI, bukan util global.

**Diubah:**

| Berkas | Perubahan |
|---|---|
| `config/services.php` | Dua kunci batas per-parameter |
| `app/Services/KPI/KpiScoringService.php` | Penjepitan, kembalian diperluas, agregasi sadar angka mentah |
| `app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php` | Tiga jalur tulis draf, dua jalur tulis detail final, endpoint cap-config, dua kueri detail |
| `app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php` | Tulis detail saat edit aktual, kueri + agregasi ulang header |
| `app/Services/KPI/KpiEnforcementService.php` | Buang penjepit duplikat, tulis kolom mentah, detail nol |
| `app/Http/Controllers/KPI/Monitoring/Enforcement/KpiEnforcementController.php` | Batas pratinjau dari service kanonik |
| `resources/js/Pages/KPI/Performance/Update/updatePerformance.vue` | Penjepitan + keterangan baru |
| `resources/js/Pages/KPI/Performance/Update/team/updatePerformance.vue` | Penjepitan + keterangan baru |
| `resources/js/Pages/KPI/Performance/Update/team/updatePerformanceByPass.vue` | Penjepitan + keterangan baru |
| `resources/js/Pages/KPI/Approval/Performance/performanceApproval.vue` | Penjepitan |
| `resources/js/Pages/KPI/Approval/Verification/VerifikasiPerformancePanel.vue` | Penjepitan |
| `resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue` | Label terbalik + tampilan angka mentah |
| `app/Console/Commands/KPI/Operations/VerifyKpiYtd.php` | Catatan bahwa ia sengaja memakai angka mentah |
| `database/ddl/status.json` | Catatan perubahan DDL-2026-045 |

---

## Task 1: Garis dasar — tes yang mengunci perilaku hari ini

Menulis tes untuk perilaku **yang berlaku sekarang**, sebelum apa pun diubah. Kalau salah satu tes ini nanti berubah warna tanpa disengaja, kita tahu ada yang bergeser di luar rencana.

**Files:**
- Create: `tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`

**Interfaces:**
- Consumes: `App\Services\KPI\KpiScoringService` sebagaimana adanya sekarang.
- Produces: berkas tes yang tugas-tugas berikutnya tambahi.

- [ ] **Step 1: Tulis tes garis dasar**

`KpiScoringService::scoreParameter()` murni (tidak menyentuh DB), jadi tes ini memakai `TestCase` biasa tanpa transaksi database.

```php
<?php

namespace Tests\Unit\KPI\Scoring;

use App\Services\KPI\KpiScoringService;
use Tests\TestCase;

class KpiScoringServiceCapTest extends TestCase
{
    private function svc(): KpiScoringService
    {
        return app(KpiScoringService::class);
    }

    /**
     * Garis dasar: tanpa batas per-parameter, rasio diteruskan apa adanya.
     * Bobot 50, target 10, aktual 15 -> rasio 1,5 -> achievement 150, skor 75.
     */
    public function test_garis_dasar_parameter_melampaui_target_tidak_dijepit(): void
    {
        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(150.0, $hasil['achievement']);
        $this->assertSame(75.0, $hasil['score']);
    }

    /**
     * Garis dasar: parameter yang jauh di bawah target juga diteruskan apa adanya.
     */
    public function test_garis_dasar_parameter_jauh_di_bawah_target_tidak_dijepit(): void
    {
        $hasil = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(20.0, $hasil['achievement']);
        $this->assertSame(10.0, $hasil['score']);
    }

    /**
     * Garis dasar agregasi: total = (Sigma skor / Sigma bobot) x 100.
     * A 300% (skor 150) + B 20% (skor 10) -> 160%.
     */
    public function test_garis_dasar_agregasi_menjumlahkan_skor_bukan_achievement(): void
    {
        $detail = collect([
            (object) ['Achievement' => 150.0, 'Bobot' => 50.0],
            (object) ['Achievement' => 10.0, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(160.0, $total['sum_of_scores']);
        $this->assertSame(100.0, $total['sum_of_active_bobot']);
        $this->assertSame(160.0, $total['final_score_percentage']);
    }

    /**
     * Garis dasar cap total: 160% dijepit ke 125 dan meninggalkan jejak audit.
     * Lapisan ini tetap ada sesudah perubahan, hanya tidak akan menggigit lagi.
     */
    public function test_garis_dasar_cap_total_masih_menjepit(): void
    {
        $total = $this->svc()->applyCap([
            'sum_of_scores' => 160.0,
            'sum_of_active_bobot' => 100.0,
            'final_score_percentage' => 160.0,
        ]);

        $this->assertSame(125.0, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertTrue($total['is_capped']);
        $this->assertSame(160.0, $total['sum_of_scores'], 'Sigma skor faktual tidak boleh ikut dijepit');
    }
}
```

- [ ] **Step 2: Jalankan, pastikan hijau**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`
Expected: 4 tes PASS. Kalau ada yang merah, **berhenti** — berarti pemahaman kita tentang perilaku sekarang keliru dan rencana ini harus ditinjau ulang sebelum dilanjutkan.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php
git commit -m "test(kpi): kunci perilaku cap total yang berlaku sekarang sebagai garis dasar"
```

---

## Task 2: DDL-2026-045 — kolom mentah pada dua tabel detail

**Files:**
- Create: `database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql`
- Create: `database/ddl/DDL-2026-045-kpi-cap-per-parameter/drop.sql`
- Create: `docs/changes/DDL-2026-045-kpi-cap-per-parameter/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Produces: kolom `Score_Raw` (float NULL), `Achievement_Raw` (float NULL), `Flag_Capped` (char(1) NOT NULL default 'T') pada `KPI_Update_Performance_Detail` dan `KPI_Update_Performance_Detail_Sementara`.

- [ ] **Step 1: Laporkan antrean yang tertunda**

Sebelum membuat perubahan baru, `AGENTS.md` mewajibkan melaporkan yang masih menggantung.

Run: `env -u DATABASE_URL php -r '$j=json_decode(file_get_contents("database/ddl/status.json"),true); $c=$j["changes"]??$j; $p=array_filter($c,fn($x)=>($x["sql_tracker"]??"")==="PENDING"); echo count($p)." perubahan masih TRACKER_PENDING\n";'`

Laporkan angkanya ke pengguna. Per 2026-08-28 jumlahnya 30, semuanya SOP dan Visitor — tidak ada yang KPI, jadi tidak bertabrakan.

- [ ] **Step 2: Tulis forward.sql**

Setiap `ALTER` dijaga `IF NOT EXISTS` agar aman dijalankan ulang. Batasan default diberi nama eksplisit supaya `drop.sql` bisa menyebutnya.

```sql
-- DDL-2026-045-kpi-cap-per-parameter
-- Kolom jejak nilai sebelum penjepitan per parameter.

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Score_Raw')
    ALTER TABLE KPI_Update_Performance_Detail ADD Score_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Achievement_Raw')
    ALTER TABLE KPI_Update_Performance_Detail ADD Achievement_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail') AND name = 'Flag_Capped')
    ALTER TABLE KPI_Update_Performance_Detail
        ADD Flag_Capped char(1) NOT NULL
        CONSTRAINT DF_KPI_Update_Performance_Detail_Flag_Capped DEFAULT ('T');
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Score_Raw')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara ADD Score_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Achievement_Raw')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara ADD Achievement_Raw float NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('KPI_Update_Performance_Detail_Sementara') AND name = 'Flag_Capped')
    ALTER TABLE KPI_Update_Performance_Detail_Sementara
        ADD Flag_Capped char(1) NOT NULL
        CONSTRAINT DF_KPI_Update_Performance_Detail_Sementara_Flag_Capped DEFAULT ('T');
GO
```

Catatan tipe: `Score_Raw`/`Achievement_Raw` bertipe `float` supaya sama persis dengan kolom pasangannya (`Score`/`Achievement`) — dipastikan dari `INFORMATION_SCHEMA.COLUMNS` pada 2026-08-28, bukan diasumsikan.

Catatan nullable: sengaja NULL. Baris lama tidak diisi, dan **kekosongan itulah pembeda antara record beraturan lama dan baru**. Mengisinya dengan nol menghapus pembeda itu dan menuliskan angka yang tidak pernah dihitung.

Catatan `Flag_Capped` NOT NULL: dengan `DEFAULT ('T')`, baris yang sudah ada terisi `'T'` — dan itu benar, karena tidak satu pun parameter lama pernah dijepit di tingkat parameter. Tabelnya kecil (305 dan 365 baris) sehingga pengisian ini sepele meski di edisi Express.

- [ ] **Step 3: Tulis drop.sql**

**Berkas ini alat bersih-bersih manual. Agen tidak boleh menjalankannya.**

```sql
-- DDL-2026-045-kpi-cap-per-parameter — pembersihan manual, JANGAN dijalankan agen.

ALTER TABLE KPI_Update_Performance_Detail DROP CONSTRAINT DF_KPI_Update_Performance_Detail_Flag_Capped;
ALTER TABLE KPI_Update_Performance_Detail DROP COLUMN Flag_Capped;
ALTER TABLE KPI_Update_Performance_Detail DROP COLUMN Achievement_Raw;
ALTER TABLE KPI_Update_Performance_Detail DROP COLUMN Score_Raw;

ALTER TABLE KPI_Update_Performance_Detail_Sementara DROP CONSTRAINT DF_KPI_Update_Performance_Detail_Sementara_Flag_Capped;
ALTER TABLE KPI_Update_Performance_Detail_Sementara DROP COLUMN Flag_Capped;
ALTER TABLE KPI_Update_Performance_Detail_Sementara DROP COLUMN Achievement_Raw;
ALTER TABLE KPI_Update_Performance_Detail_Sementara DROP COLUMN Score_Raw;
```

- [ ] **Step 4: Tulis dokumentasi perubahan**

`docs/changes/DDL-2026-045-kpi-cap-per-parameter/README.md` memuat: change ID, target database, deskripsi, objek terdampak, jalur DDL, checksum forward, status, pekerjaan yang selesai oleh agen, dan pekerjaan yang tersisa untuk pengguna. Sebutkan tegas bahwa kolom dibiarkan NULL untuk baris lama dan itu disengaja sebagai pembeda aturan.

- [ ] **Step 5: Minta persetujuan pengguna, lalu jalankan ke pengembangan**

Tampilkan SQL persisnya, sebutkan target (`Web_HRIS` pengembangan pada team311), objek terdampak, dan perkiraan baris tersentuh (305 + 365 pengisian default). **Tunggu persetujuan.**

Setelah disetujui:

```bash
env -u DATABASE_URL php artisan tinker --execute="
foreach (preg_split('/^\s*GO\s*$/mi', file_get_contents('database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql')) as \$b) {
    \$b = trim(\$b);
    if (\$b !== '') { DB::unprepared(\$b); }
}
echo 'OK ' . DB::selectOne('SELECT DB_NAME() n')->n . PHP_EOL;
"
```

- [ ] **Step 6: Verifikasi kolomnya benar-benar ada**

```bash
env -u DATABASE_URL php artisan tinker --execute="
foreach (DB::select(\"SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME LIKE 'KPI_Update_Performance_Detail%' AND COLUMN_NAME IN ('Score_Raw','Achievement_Raw','Flag_Capped') ORDER BY TABLE_NAME, COLUMN_NAME\") as \$c) {
    echo \$c->TABLE_NAME.' | '.\$c->COLUMN_NAME.' | '.\$c->DATA_TYPE.' | null='.\$c->IS_NULLABLE.PHP_EOL;
}
"
```
Expected: 6 baris.

- [ ] **Step 7: Catat di status.json dan commit**

Tambahkan entri `DDL-2026-045-kpi-cap-per-parameter` dengan `status: DEV_EXECUTED`, `development: DONE`, `sql_tracker: PENDING`, `production: PENDING`, checksum SHA-256 dari `forward.sql` yang persis dijalankan.

```bash
git add database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql database/ddl/DDL-2026-045-kpi-cap-per-parameter/drop.sql docs/changes/DDL-2026-045-kpi-cap-per-parameter/README.md database/ddl/status.json
git commit -m "feat(kpi): kolom jejak nilai sebelum penjepitan pada detail performance"
```

---

## Task 3: Penjepitan di KpiScoringService

Inti pekerjaan. Penjepitan ditulis lengkap dan diuji di sini, tetapi **mati secara baku** — batas dibaca dari config yang defaultnya `null`. Nilai siapa pun belum berubah sampai Task 8.

**Files:**
- Modify: `config/services.php`
- Modify: `app/Services/KPI/KpiScoringService.php`
- Test: `tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`

**Interfaces:**
- Produces:
  - `KpiScoringService::scoreParameter(float $actual, float $target, float $weight, ?string $formula): array` — kembalian bertambah `achievement_raw` (float), `score_raw` (float), `is_capped` (bool).
  - `KpiScoringService::paramCapBounds(): array` — `[?float $max, ?float $min]`, `null` berarti sisi itu tidak dijepit.
  - `KpiScoringService::aggregate($details, string $weightedKey = 'Achievement', string $bobotKey = 'Bobot', ?string $rawWeightedKey = 'Achievement_Raw'): array` — kembalian bertambah `raw_sum_of_scores`, `raw_final_score_percentage`, `capped_details_count` (int).

- [ ] **Step 1: Tulis tes yang gagal**

Tambahkan ke `tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`. Tes menyetel config secara eksplisit supaya tidak bergantung pada nilai baku yang masih mati.

```php
    private function nyalakanCapParameter(float $max = 125, float $min = 50): void
    {
        config()->set('services.kpi.param_max_achievement_score', $max);
        config()->set('services.kpi.param_min_achievement_score', $min);
    }

    public function test_parameter_di_atas_batas_dijepit_dan_skornya_ikut_turun(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $hasil['achievement']);
        $this->assertSame(62.5, $hasil['score'], 'Skor harus turun dari rasio yang sudah dijepit, bukan rasio mentah');
        $this->assertSame(150.0, $hasil['achievement_raw']);
        $this->assertSame(75.0, $hasil['score_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_parameter_di_bawah_batas_dinaikkan_ke_lantai(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(25.0, $hasil['score']);
        $this->assertSame(20.0, $hasil['achievement_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_parameter_tepat_di_batas_tidak_dianggap_dijepit(): void
    {
        $this->nyalakanCapParameter();

        $atas = $this->svc()->scoreParameter(12.5, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $bawah = $this->svc()->scoreParameter(5, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $atas['achievement']);
        $this->assertFalse($atas['is_capped']);
        $this->assertSame(50.0, $bawah['achievement']);
        $this->assertFalse($bawah['is_capped']);
    }

    public function test_parameter_di_dalam_rentang_lewat_tanpa_perubahan(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(9, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(90.0, $hasil['achievement']);
        $this->assertSame(45.0, $hasil['score']);
        $this->assertFalse($hasil['is_capped']);
    }

    public function test_rumus_bernilai_negatif_dijepit_ke_lantai_bukan_melempar(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(30, 10, 50, '1-[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(25.0, $hasil['score']);
        $this->assertSame(-200.0, $hasil['achievement_raw']);
    }

    public function test_rumus_kosong_menghasilkan_nol_lalu_dijepit_ke_lantai(): void
    {
        $this->nyalakanCapParameter();

        $hasil = $this->svc()->scoreParameter(9, 10, 50, null);

        $this->assertSame(50.0, $hasil['achievement']);
        $this->assertSame(0.0, $hasil['achievement_raw']);
        $this->assertTrue($hasil['is_capped']);
    }

    public function test_batas_mati_membuat_perilaku_identik_dengan_sebelumnya(): void
    {
        config()->set('services.kpi.param_max_achievement_score', null);
        config()->set('services.kpi.param_min_achievement_score', null);

        $hasil = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(150.0, $hasil['achievement']);
        $this->assertSame(75.0, $hasil['score']);
        $this->assertFalse($hasil['is_capped']);
    }

    public function test_hanya_satu_sisi_dinyalakan(): void
    {
        config()->set('services.kpi.param_max_achievement_score', 125);
        config()->set('services.kpi.param_min_achievement_score', null);

        $atas = $this->svc()->scoreParameter(15, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $bawah = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertSame(125.0, $atas['achievement']);
        $this->assertSame(20.0, $bawah['achievement'], 'Sisi bawah mati, jadi tidak diangkat');
    }

    public function test_agregasi_menghitung_jumlah_mentah_dan_cacah_yang_dijepit(): void
    {
        $detail = collect([
            (object) ['Achievement' => 62.5, 'Achievement_Raw' => 150.0, 'Bobot' => 50.0],
            (object) ['Achievement' => 25.0, 'Achievement_Raw' => 10.0, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(87.5, $total['sum_of_scores']);
        $this->assertSame(87.5, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_sum_of_scores']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertSame(2, $total['capped_details_count']);
    }

    public function test_agregasi_baris_lama_tanpa_kolom_mentah_tidak_dianggap_dijepit(): void
    {
        $detail = collect([
            (object) ['Achievement' => 150.0, 'Achievement_Raw' => null, 'Bobot' => 50.0],
            (object) ['Achievement' => 10.0, 'Achievement_Raw' => null, 'Bobot' => 50.0],
        ]);

        $total = $this->svc()->aggregate($detail);

        $this->assertSame(160.0, $total['sum_of_scores']);
        $this->assertSame(160.0, $total['raw_sum_of_scores'], 'Tanpa kolom mentah, mentah = tersimpan');
        $this->assertSame(0, $total['capped_details_count']);
    }

    /**
     * Kasus acuan dari spec: A 300%, B 20%, bobot 50 : 50.
     * Tanpa penjepitan parameter total 160 -> dijepit total jadi 125.
     * Dengan penjepitan parameter total menjadi 87,5 dan cap total tak menggigit.
     */
    public function test_kasus_acuan_rantai_penuh_menghasilkan_87_5(): void
    {
        $this->nyalakanCapParameter();
        $svc = $this->svc();

        $a = $svc->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $b = $svc->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $detail = collect([
            (object) ['Achievement' => $a['score'], 'Achievement_Raw' => $a['score_raw'], 'Bobot' => 50.0],
            (object) ['Achievement' => $b['score'], 'Achievement_Raw' => $b['score_raw'], 'Bobot' => 50.0],
        ]);

        $total = $svc->applyCap($svc->aggregate($detail));

        $this->assertSame(87.5, $total['final_score_percentage']);
        $this->assertSame(160.0, $total['raw_final_score_percentage']);
        $this->assertTrue($total['is_capped'], 'Ditandai karena ada parameter yang dijepit');
        $this->assertSame(2, $total['capped_details_count']);
    }
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`
Expected: tes garis dasar dari Task 1 tetap PASS; tes baru FAIL dengan "Undefined array key \"achievement_raw\"" dan sejenisnya.

Kalau ada tes baru yang langsung hijau, **berhenti** — berarti ia tidak menguji apa pun.

- [ ] **Step 3: Tambahkan kunci config**

Di `config/services.php`, di dalam blok `'kpi' => [`, tepat setelah `min_achievement_score`:

```php
        // Batas penjepitan PER PARAMETER, dinyatakan dalam satuan achievement (%).
        // Terpisah dari cap total di atas supaya dua lapisan itu bisa disetel
        // sendiri-sendiri; kalau menumpang satu kunci, satu penyetelan akan
        // menggeser keduanya diam-diam.
        //
        // null = sisi itu tidak dijepit. Baku masih mati; dinyalakan di Task 8
        // setelah seluruh jalur mampu menyimpan dan menampilkan angka mentah.
        'param_max_achievement_score' => env('KPI_PARAM_MAX_ACHIEVEMENT_SCORE') !== null
            ? (float) env('KPI_PARAM_MAX_ACHIEVEMENT_SCORE')
            : null,
        'param_min_achievement_score' => env('KPI_PARAM_MIN_ACHIEVEMENT_SCORE') !== null
            ? (float) env('KPI_PARAM_MIN_ACHIEVEMENT_SCORE')
            : null,
```

- [ ] **Step 4: Pasang penjepitan di service**

Ganti `scoreParameter()` di `app/Services/KPI/KpiScoringService.php`:

```php
    /**
     * Evaluasi satu parameter langsung dari string rumus, lalu jepit rasionya
     * ke batas per-parameter dari config.
     *
     * Yang dijepit adalah RASIO, bukan achievement sendirian: achievement dan
     * skor sama-sama diturunkan dari rasio yang sudah dijepit. Menjepit
     * achievement saja lalu menghitung skor dari rasio mentah membuat keduanya
     * tak sinkron, dan total berhenti sama dengan rata-rata baris yang tampil.
     *
     * Bila rumus kosong/null -> rasio nol (identik perilaku lama), lalu tetap
     * melewati penjepitan.
     *
     * @return array{achievement: float, score: float, achievement_raw: float, score_raw: float, is_capped: bool}
     */
    public function scoreParameter(float $actual, float $target, float $weight, ?string $formula): array
    {
        $mentah = (! is_string($formula) || trim($formula) === '')
            ? ['achievement' => 0.0, 'score' => 0.0]
            : KpiFormulaHelper::evaluate($actual, $target, $weight, $formula);

        $rasioMentah = ((float) $mentah['achievement']) / 100;
        $rasio = $this->jepitRasio($rasioMentah);
        $terjepit = $rasio !== $rasioMentah;

        return [
            'achievement' => $terjepit ? round($rasio * 100, 2) : (float) $mentah['achievement'],
            'score' => $terjepit ? round($weight * $rasio, 2) : (float) $mentah['score'],
            'achievement_raw' => (float) $mentah['achievement'],
            'score_raw' => (float) $mentah['score'],
            'is_capped' => $terjepit,
        ];
    }

    /**
     * Jepit rasio ke batas per-parameter. Sisi yang batasnya null dilewati.
     */
    private function jepitRasio(float $rasio): float
    {
        [$max, $min] = $this->paramCapBounds();

        if ($max !== null) {
            $rasio = min($rasio, $max / 100);
        }

        if ($min !== null) {
            $rasio = max($rasio, $min / 100);
        }

        return $rasio;
    }

    /**
     * Batas penjepitan per-parameter [max, min] dalam satuan achievement (%).
     * null pada satu sisi berarti sisi itu tidak dijepit.
     *
     * WAJIB lewat config(), bukan env() langsung: setelah `config:cache`
     * dijalankan, .env tidak lagi dimuat dan env() mengembalikan nilai baku —
     * tombol pengembaliannya mati tanpa memberi tanda apa pun.
     *
     * @return array{0: ?float, 1: ?float}
     */
    public function paramCapBounds(): array
    {
        $max = config('services.kpi.param_max_achievement_score');
        $min = config('services.kpi.param_min_achievement_score');

        return [
            $max === null ? null : (float) $max,
            $min === null ? null : (float) $min,
        ];
    }
```

Catatan penting soal `$rasioMentah`: ia diturunkan dari `achievement` (yang sudah dibulatkan dua desimal oleh `KpiFormulaHelper::evaluate`), bukan dari hasil `eval` mentah. Ini disengaja supaya perbandingan `!==` tidak terpicu oleh galat pembulatan float, dan supaya angka yang dijepit persis angka yang ditampilkan.

- [ ] **Step 5: Perluas `aggregate()`**

Ganti `aggregate()` di berkas yang sama:

```php
    /**
     * Agregasi total dari kumpulan detail.
     * final_score_percentage = (Sigma skor berbobot / Sigma bobot) x 100.
     *
     * $rawWeightedKey menunjuk kolom skor SEBELUM penjepitan per parameter.
     * Bila key itu tidak ada atau bernilai null (baris beraturan lama), nilai
     * tersimpan dipakai sebagai nilai mentah — baris lama memang tidak pernah
     * dijepit di tingkat parameter.
     *
     * @return array{sum_of_scores: float, sum_of_active_bobot: float, final_score_percentage: float, raw_sum_of_scores: float, raw_final_score_percentage: float, capped_details_count: int}
     */
    public function aggregate(
        $details,
        string $weightedKey = 'Achievement',
        string $bobotKey = 'Bobot',
        ?string $rawWeightedKey = 'Achievement_Raw',
    ): array {
        $sumOfScores = 0.0;
        $sumOfRawScores = 0.0;
        $sumOfActiveBobot = 0.0;
        $cappedCount = 0;

        foreach ($details as $item) {
            $weighted = (float) $this->pick($item, $weightedKey);
            $raw = $rawWeightedKey === null ? null : $this->pick($item, $rawWeightedKey);
            $raw = $raw === null ? $weighted : (float) $raw;

            $sumOfScores += $weighted;
            $sumOfRawScores += $raw;
            $sumOfActiveBobot += (float) $this->pick($item, $bobotKey);

            if (abs($raw - $weighted) > 0.0001) {
                $cappedCount++;
            }
        }

        $finalScorePercentage = $sumOfActiveBobot > 0 ? ($sumOfScores / $sumOfActiveBobot) * 100 : 0;
        $rawScorePercentage = $sumOfActiveBobot > 0 ? ($sumOfRawScores / $sumOfActiveBobot) * 100 : 0;

        return [
            'sum_of_scores' => $sumOfScores,
            'sum_of_active_bobot' => $sumOfActiveBobot,
            'final_score_percentage' => round($finalScorePercentage, 2),
            'raw_sum_of_scores' => round($sumOfRawScores, 2),
            'raw_final_score_percentage' => round($rawScorePercentage, 2),
            'capped_details_count' => $cappedCount,
        ];
    }
```

- [ ] **Step 6: Alihkan sumber jejak audit di `applyCap()`**

Ganti `applyCap()`:

```php
    /**
     * Terapkan cap TOTAL pada final_score_percentage.
     *
     * Sejak penjepitan pindah ke tingkat parameter, lapisan ini praktis tidak
     * pernah menggigit — rata-rata tertimbang dari nilai yang semuanya di dalam
     * rentang pasti juga di dalam rentang. Ia sengaja dipertahankan sebagai
     * jaring pengaman terakhir bila batas per-parameter dilonggarkan atau
     * dimatikan lewat config.
     *
     * Jejak audit (raw_* dan is_capped) TIDAK lagi lahir dari penjepitan total,
     * melainkan dari penjumlahan kolom mentah per detail yang dihitung
     * aggregate(). Untuk record beraturan lama yang tidak punya kolom mentah,
     * hasilnya identik dengan perilaku sebelumnya.
     *
     * HANYA final_score_percentage yang dijepit; sum_of_scores TETAP faktual.
     */
    public function applyCap(array $scores): array
    {
        [$maxCap, $minCap] = $this->capBounds();

        $rawScore = (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']);
        $nilai = (float) $scores['final_score_percentage'];
        $cappedScore = max($minCap, min($maxCap, $nilai));
        $totalTerjepit = $cappedScore !== $nilai;

        $scores['raw_final_score_percentage'] = $rawScore;
        $scores['raw_sum_of_scores'] = (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']);
        $scores['capped_details_count'] = (int) ($scores['capped_details_count'] ?? 0);
        $scores['is_capped'] = $totalTerjepit || $scores['capped_details_count'] > 0;

        [$paramMax, $paramMin] = $this->paramCapBounds();
        $scores['cap_min_applied'] = $paramMin ?? $minCap;
        $scores['cap_max_applied'] = $paramMax ?? $maxCap;

        if ($totalTerjepit) {
            $scores['final_score_percentage'] = round($cappedScore, 2);
        }

        return $scores;
    }
```

- [ ] **Step 7: Jalankan tes, pastikan HIJAU**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`
Expected: seluruh tes PASS, termasuk empat tes garis dasar dari Task 1 — perilaku baku belum berubah karena batas per-parameter masih `null`.

- [ ] **Step 8: Commit**

```bash
git add config/services.php app/Services/KPI/KpiScoringService.php tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php
git commit -m "feat(kpi): penjepitan per parameter di KpiScoringService, baku masih mati"
```

---

## Task 4: Seluruh penulis detail mengisi kolom mentah

Belum ada nilai yang berubah — batas masih mati, sehingga `achievement_raw` selalu sama dengan `achievement`. Tugas ini memastikan tempat penyimpanannya sudah siap sebelum penjepitan dinyalakan.

**Files:**
- Modify: `app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php` (jalur draf personal ~2501, team ~2726, by-pass ~2971; `createFinalPerformanceDetails` ~5294; `createFinalPerformanceDetailsByPass` ~5315; `calculateFormulaStream` ~3684)
- Modify: `app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php` (`calculateFormulaStream` ~35, `updateSingleKpiDetail` ~2249)
- Modify: `app/Services/KPI/KpiEnforcementService.php` (`buildZeroedDetailSet` ~552, `createFinalPerformanceDetails` ~750)

**Interfaces:**
- Consumes: kembalian `scoreParameter()` yang diperluas di Task 3.
- Produces: setiap baris `KPI_Update_Performance_Detail` dan `_Sementara` yang ditulis setelah ini memuat `Score_Raw`, `Achievement_Raw`, `Flag_Capped`.

- [ ] **Step 1: Teruskan kunci baru lewat `calculateFormulaStream`**

Keempat pembungkus `calculateFormulaStream` hanya meneruskan kembalian `scoreParameterById`, jadi kunci tambahan ikut dengan sendirinya. **Tidak ada perubahan kode yang dibutuhkan di sana** — cukup verifikasi bahwa keempatnya benar-benar meneruskan hasil apa adanya (tidak memetik key tertentu):

Run: `grep -n "function calculateFormulaStream" -A 6 app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php app/Http/Controllers/KPI/Performance/Dashboard/Executive/DashboardCeoController.php app/Http/Controllers/KPI/Performance/Dashboard/StaffPerformanceDashboardController.php`

Expected: keempatnya `return app(KpiScoringService::class)->scoreParameterById(...)` tanpa memetik key. Kalau ada yang memetik, perbaiki jadi meneruskan utuh.

- [ ] **Step 2: Isi kolom mentah pada tiga jalur draf**

Di `UpdatePerformanceController`, ketiga blok `$newData = [...]` (jalur personal ~2517, team ~2740, by-pass ~2983) punya bentuk identik. Pada masing-masing, setelah baris `'Achievement' => (float) $calc['score'],` sisipkan:

```php
                    'Score_Raw' => (float) ($calc['achievement_raw'] ?? $calc['achievement']),
                    'Achievement_Raw' => (float) ($calc['score_raw'] ?? $calc['score']),
                    'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
```

Lalu pada ketiga `upsert()` yang menyertainya, tambahkan ketiga kolom itu ke daftar kolom yang diperbarui:

```php
                    ['Nilai_Actual', 'Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped', 'File_Berkas', 'Flag_Pica', 'Flag_Aktif'],
```

Kalau daftar kolom pembaruan tidak ikut ditambah, baris yang di-upsert ulang akan mempertahankan nilai mentah lama sementara nilai terjepitnya berubah — dua angka dari dua perhitungan berbeda dalam satu baris.

- [ ] **Step 3: Teruskan kolom mentah dari draf ke detail final**

`createFinalPerformanceDetails()` dan `createFinalPerformanceDetailsByPass()` menyalin baris draf ke tabel final. Pada kedua `->map(fn ($item) => [...])`, setelah `'Achievement' => $item->Achievement,` sisipkan:

```php
                    'Score_Raw' => $item->Score_Raw ?? $item->Score,
                    'Achievement_Raw' => $item->Achievement_Raw ?? $item->Achievement,
                    'Flag_Capped' => $item->Flag_Capped ?? 'T',
```

- [ ] **Step 4: Isi kolom mentah saat approver mengedit nilai aktual**

Di `PerformanceApprovalController::updateSingleKpiDetail()` (~2249), ganti blok `update()`:

```php
        DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance_Detail', $kpiId)
            ->update([
                'Nilai_Actual' => $actualValue,
                'Score' => $calc['achievement'],
                'Achievement' => $calc['score'],
                'Score_Raw' => $calc['achievement_raw'] ?? $calc['achievement'],
                'Achievement_Raw' => $calc['score_raw'] ?? $calc['score'],
                'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
            ]);
```

- [ ] **Step 5: Enforcement menyimpan nol yang sebenarnya**

Di `KpiEnforcementService::buildZeroedDetailSet()` (~552), pada objek detail yang di-`push`, setelah `'Achievement' => 0,` sisipkan:

```php
                'Score_Raw' => 0,
                'Achievement_Raw' => 0,
                'Flag_Capped' => 'T',
```

Nilai `Flag_Capped` di sini masih `'T'`; ia menjadi `'Y'` sendirinya lewat Task 6 setelah detail nol melewati penjepitan.

Lalu di `KpiEnforcementService::createFinalPerformanceDetails()` (~750), setelah `'Achievement' => $item->Achievement,`:

```php
                'Score_Raw' => $item->Score_Raw ?? $item->Score,
                'Achievement_Raw' => $item->Achievement_Raw ?? $item->Achievement,
                'Flag_Capped' => $item->Flag_Capped ?? 'T',
```

- [ ] **Step 6: Ambil kolom mentah di kueri yang memberi makan agregasi**

Tiga kueri memilih kolom secara eksplisit dan harus ikut mengambil kolom mentah, kalau tidak `aggregate()` akan menganggap semua baris tidak terjepit.

`UpdatePerformanceController` ~2545 (dan blok serupa di jalur team/by-pass) — ganti:

```php
                ->select('upds.Achievement', 'p_detail.Bobot')
```
menjadi:
```php
                ->select('upds.Achievement', 'upds.Achievement_Raw', 'p_detail.Bobot')
```

`PerformanceApprovalController::recalcPerformanceTotals()` ~2287 — ganti:

```php
            ->get(['upd.Achievement', 'pd.Bobot']);
```
menjadi:
```php
            ->get(['upd.Achievement', 'upd.Achievement_Raw', 'pd.Bobot']);
```

Run: `grep -rn "'upds.Achievement'\|'upd.Achievement'" app/Http/Controllers/KPI/` untuk memastikan tidak ada kueri lain yang terlewat.

`UpdatePerformanceController::getPerformanceDetails()` memakai `upds.*` sehingga sudah ikut membawa kolom mentah tanpa diubah.

- [ ] **Step 7: Verifikasi tulis-baca lewat satu putaran nyata**

Simpan satu draf lewat halaman `/update-performance`, lalu periksa:

```bash
env -u DATABASE_URL php artisan tinker --execute="
\$r = DB::table('KPI_Update_Performance_Detail_Sementara')->orderByDesc('Id_Update_Performance_Detail_Sementara')->first();
echo 'Score='.\$r->Score.' Score_Raw='.(\$r->Score_Raw ?? 'NULL').' Achievement='.\$r->Achievement.' Achievement_Raw='.(\$r->Achievement_Raw ?? 'NULL').' Flag_Capped='.\$r->Flag_Capped.PHP_EOL;
"
```
Expected: `Score_Raw` sama dengan `Score`, `Achievement_Raw` sama dengan `Achievement`, `Flag_Capped=T`. Batas masih mati, jadi memang belum ada yang dijepit.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php app/Services/KPI/KpiEnforcementService.php
git commit -m "feat(kpi): seluruh jalur tulis detail menyimpan nilai sebelum penjepitan"
```

---

## Task 5: Arti baru kolom audit header

**Files:**
- Modify: `app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php` (`createFinalPerformanceHeader` ~5257, `createFinalPerformanceHeaderByTeam` ~5189, `createFinalPerformanceHeaderByPass` ~5222)
- Modify: `app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php` (`recalcPerformanceTotals` ~2282)
- Modify: `app/Services/KPI/KpiEnforcementService.php` (`createFinalPerformanceHeader` ~700)

**Interfaces:**
- Consumes: `aggregate()` + `applyCap()` yang sudah memuat `raw_sum_of_scores`, `raw_final_score_percentage`, `capped_details_count`.
- Produces: header dengan `Flag_Capped` bermakna "ada parameter yang dijepit".

- [ ] **Step 1: Seragamkan penulisan `Cap_*_Applied` di jalur approval**

Di `recalcPerformanceTotals()`, ganti dua baris yang menulis `null` saat tidak terjepit:

```php
                'Cap_Min_Applied' => $scores['cap_min_applied'],
                'Cap_Max_Applied' => $scores['cap_max_applied'],
```

Nilainya menjelaskan aturan yang berlaku saat record dihitung, bukan sekadar jejak kejadian, jadi selalu diisi. Jalur Update Performance sudah selalu mengisinya; ini menyamakan keduanya.

- [ ] **Step 2: Buang pembulatan yang menimpa jumlah mentah**

Masih di `recalcPerformanceTotals()`, baris berikut menulis Σ skor **terjepit** ke kolom mentah — salah sejak kolom mentah punya arti sendiri:

```php
                'Total_Score_Achievement_Raw' => round((float) $scores['sum_of_scores'], 2),
```
ganti menjadi:
```php
                'Total_Score_Achievement_Raw' => round((float) $scores['raw_sum_of_scores'], 2),
```

- [ ] **Step 3: Verifikasi ketiga penulis header lain sudah benar**

`createFinalPerformanceHeader`, `...ByTeam`, `...ByPass` di `UpdatePerformanceController` dan `createFinalPerformanceHeader` di `KpiEnforcementService` sudah membaca `$scores['raw_final_score_percentage']`, `$scores['raw_sum_of_scores']`, dan `$scores['is_capped']` — yang kini bersumber dari detail. **Tidak ada perubahan kode.**

Run: `grep -n "Total_Score_Achievement_Raw" app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php app/Services/KPI/KpiEnforcementService.php`
Expected: seluruhnya memakai `$scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']`.

- [ ] **Step 4: Hapus penjepit duplikat di KpiEnforcementService**

`KpiEnforcementService` punya salinan `calculateScores()` dan `applyAchievementCap()` dengan batas baku `150/0` yang menyimpang dari 125/50. Ganti keduanya menjadi delegasi, dengan mempertahankan penyaringan `Flag_Aktif` yang khas jalur ini:

```php
    /**
     * Hitung agregat score. Hanya parameter Flag_Aktif='Y' (exact-match periode)
     * yang dihitung ke total bobot/score — identik submit normal. Parameter Q/S/Y
     * yang belum waktunya dinilai tidak boleh memengaruhi total.
     */
    private function calculateScores($detailsData): array
    {
        $activeDetails = $detailsData->filter(fn ($item) => ($item->Flag_Aktif ?? 'Y') === 'Y');

        return app(\App\Services\KPI\KpiScoringService::class)->aggregate($activeDetails);
    }

    /**
     * Terpusat di KpiScoringService. Salinan lokal sebelumnya memakai batas baku
     * 150/0 yang menyimpang dari 125/50 yang sebenarnya berlaku — tak terlihat
     * selama config terisi, dan menampilkan angka salah begitu tidak.
     */
    private function applyAchievementCap(array $scores): array
    {
        return app(\App\Services\KPI\KpiScoringService::class)->applyCap($scores);
    }
```

Sekalian ganti `getPerformanceLevel()` di berkas yang sama menjadi delegasi, supaya skor di bawah seluruh band jatuh ke band terendah alih-alih melempar:

```php
    private function getPerformanceLevel(float $finalScore)
    {
        return app(\App\Services\KPI\KpiScoringService::class)->resolveLevel($finalScore);
    }
```

- [ ] **Step 5: Batas pratinjau enforcement dari sumber kanonik**

Di `KpiEnforcementController` ~1657, ganti dua baris yang memakai baku menyimpang `0/150`:

```php
                    'cap_min' => (float) config('services.kpi.min_achievement_score', 0),
                    'cap_max' => (float) config('services.kpi.max_achievement_score', 150),
```
menjadi:
```php
                    'cap_min' => app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds()[1]
                        ?? (float) config('services.kpi.min_achievement_score', 50),
                    'cap_max' => app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds()[0]
                        ?? (float) config('services.kpi.max_achievement_score', 125),
```

- [ ] **Step 6: Jalankan tes unit**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`
Expected: seluruhnya PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php app/Services/KPI/KpiEnforcementService.php app/Http/Controllers/KPI/Monitoring/Enforcement/KpiEnforcementController.php
git commit -m "feat(kpi): jejak audit header bersumber dari detail, penjepit duplikat dibuang"
```

---

## Task 6: Endpoint konfigurasi dan penjepit di sisi klien

**Files:**
- Create: `resources/js/Pages/KPI/utils/kpiCap.js`
- Create: `resources/js/Pages/KPI/utils/kpiCap.test.js`
- Modify: `app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php` (`getKpiCapConfig` ~4414)
- Modify: lima berkas Vue

**Interfaces:**
- Produces:
  - `jepitRasio(rasio: number, batas: {min: ?number, max: ?number}): number`
  - `hitungMetrikParameter(rasioMentah: number, bobot: number, batas): {achievement, score, achievementRaw, scoreRaw, terjepit}`
  - `batasDariConfig(payload): {min: ?number, max: ?number}`
  - Endpoint `GET /api/v1/update-performance/kpi-cap-config` mengembalikan `param_max_achievement_score` dan `param_min_achievement_score` di samping dua kunci lama.

- [ ] **Step 1: Tulis tes vitest yang gagal**

`resources/js/Pages/KPI/utils/kpiCap.test.js`:

```js
import { describe, expect, it } from 'vitest';
import { batasDariConfig, hitungMetrikParameter, jepitRasio } from './kpiCap';

const BATAS = { min: 50, max: 125 };

describe('jepitRasio', () => {
    it('menjepit rasio di atas batas atas', () => {
        expect(jepitRasio(3, BATAS)).toBe(1.25);
    });

    it('mengangkat rasio di bawah batas bawah', () => {
        expect(jepitRasio(0.2, BATAS)).toBe(0.5);
    });

    it('membiarkan rasio di dalam rentang', () => {
        expect(jepitRasio(0.9, BATAS)).toBe(0.9);
    });

    it('melewati sisi yang batasnya kosong', () => {
        expect(jepitRasio(3, { min: null, max: null })).toBe(3);
        expect(jepitRasio(0.2, { min: null, max: 125 })).toBe(0.2);
    });

    it('memperlakukan rasio bukan angka sebagai nol lalu menjepitnya', () => {
        expect(jepitRasio(Number.NaN, BATAS)).toBe(0.5);
        expect(jepitRasio(Number.POSITIVE_INFINITY, BATAS)).toBe(0.5);
    });
});

describe('hitungMetrikParameter', () => {
    it('menurunkan achievement dan skor dari rasio yang sudah dijepit', () => {
        const m = hitungMetrikParameter(3, 50, BATAS);

        expect(m.achievement).toBe(125);
        expect(m.score).toBe(62.5);
        expect(m.achievementRaw).toBe(300);
        expect(m.scoreRaw).toBe(150);
        expect(m.terjepit).toBe(true);
    });

    it('tidak menandai terjepit saat nilai tepat di batas', () => {
        expect(hitungMetrikParameter(1.25, 50, BATAS).terjepit).toBe(false);
    });

    it('meneruskan apa adanya saat batas kosong', () => {
        const m = hitungMetrikParameter(3, 50, { min: null, max: null });

        expect(m.achievement).toBe(300);
        expect(m.score).toBe(150);
        expect(m.terjepit).toBe(false);
    });
});

describe('batasDariConfig', () => {
    it('membaca batas per-parameter dari payload', () => {
        expect(batasDariConfig({ param_max_achievement_score: 125, param_min_achievement_score: 50 }))
            .toEqual({ min: 50, max: 125 });
    });

    it('menghasilkan batas kosong saat payload tidak memuatnya', () => {
        expect(batasDariConfig({ max_achievement_score: 125 })).toEqual({ min: null, max: null });
        expect(batasDariConfig(null)).toEqual({ min: null, max: null });
    });
});
```

- [ ] **Step 2: Jalankan, pastikan MERAH**

Run: `npm run test:unit -- resources/js/Pages/KPI/utils/kpiCap.test.js`
Expected: FAIL — modul `./kpiCap` belum ada.

- [ ] **Step 3: Tulis modulnya**

`resources/js/Pages/KPI/utils/kpiCap.js`:

```js
/**
 * Penjepitan achievement per parameter di sisi klien.
 *
 * Satu-satunya tempat aturan ini ditulis untuk frontend. Lima halaman KPI
 * menghitung ulang rumus parameter di browser; kalau masing-masing menjepit
 * dengan caranya sendiri, layar dan server akan menampilkan angka berbeda untuk
 * data yang sama — dan sejak penjepitan mengubah nilai akhir, selisih itu
 * terlihat oleh setiap pengisi.
 *
 * Batas dinyatakan dalam satuan achievement (persen), sama seperti di backend.
 * Batas bernilai null berarti sisi itu tidak dijepit.
 */

const BATAS_KOSONG = { min: null, max: null };

/**
 * Ambil batas per-parameter dari payload endpoint kpi-cap-config.
 */
export function batasDariConfig(payload) {
    if (!payload) {
        return { ...BATAS_KOSONG };
    }

    const max = payload.param_max_achievement_score;
    const min = payload.param_min_achievement_score;

    return {
        min: min === null || min === undefined ? null : Number(min),
        max: max === null || max === undefined ? null : Number(max),
    };
}

/**
 * Jepit rasio hasil rumus ke batas per-parameter.
 * Rasio yang bukan angka berhingga diperlakukan sebagai nol lebih dulu,
 * sama seperti perilaku lama, baru kemudian dijepit.
 */
export function jepitRasio(rasio, batas = BATAS_KOSONG) {
    let nilai = typeof rasio === 'number' && Number.isFinite(rasio) ? rasio : 0;

    if (batas.max !== null && batas.max !== undefined) {
        nilai = Math.min(nilai, batas.max / 100);
    }

    if (batas.min !== null && batas.min !== undefined) {
        nilai = Math.max(nilai, batas.min / 100);
    }

    return nilai;
}

/**
 * Turunkan achievement dan skor dari rasio yang sudah dijepit, beserta angka
 * mentahnya. Menjepit achievement sendirian lalu menghitung skor dari rasio
 * mentah membuat total berhenti sama dengan rata-rata baris yang tampil.
 */
export function hitungMetrikParameter(rasioMentah, bobot, batas = BATAS_KOSONG) {
    const mentah = typeof rasioMentah === 'number' && Number.isFinite(rasioMentah) ? rasioMentah : 0;
    const rasio = jepitRasio(mentah, batas);
    const berat = Number(bobot) || 0;

    return {
        achievement: rasio * 100,
        score: berat * rasio,
        achievementRaw: mentah * 100,
        scoreRaw: berat * mentah,
        terjepit: rasio !== mentah,
    };
}
```

- [ ] **Step 4: Jalankan, pastikan HIJAU**

Run: `npm run test:unit -- resources/js/Pages/KPI/utils/kpiCap.test.js`
Expected: seluruh tes PASS.

- [ ] **Step 5: Kirim batas per-parameter lewat endpoint**

Di `UpdatePerformanceController::getKpiCapConfig()` (~4414), ganti isi `$config`:

```php
            $bounds = app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds();

            $config = [
                // Batas cap TOTAL — dipertahankan untuk pemanggil lama.
                'max_achievement_score' => (int) config('services.kpi.max_achievement_score', 125),
                'min_achievement_score' => (int) config('services.kpi.min_achievement_score', 50),
                // Batas cap PER PARAMETER. null = sisi itu tidak dijepit.
                'param_max_achievement_score' => $bounds[0],
                'param_min_achievement_score' => $bounds[1],
            ];
```

- [ ] **Step 6: Pasang penjepit di tiga halaman pengisian**

Pola sama untuk `updatePerformance.vue` (~1525), `team/updatePerformance.vue` (~2111), `team/updatePerformanceByPass.vue` (~2008).

Tambahkan impor di bagian `<script>`:

```js
import { batasDariConfig, hitungMetrikParameter } from '@/Pages/KPI/utils/kpiCap';
```

Tambahkan state (dekat `kpiMaxScore`/`kpiMinScore` yang sudah ada):

```js
            batasParameter: { min: null, max: null },
```

Ganti dua baris penetapan metrik:

```js
            item.achievement = achievementRatio * 100;
            item.score = item.weight * achievementRatio;
```
menjadi:
```js
            const metrik = hitungMetrikParameter(achievementRatio, item.weight, this.batasParameter);
            item.achievement = metrik.achievement;
            item.score = metrik.score;
            item.achievementRaw = metrik.achievementRaw;
            item.isCapped = metrik.terjepit;
```

Di `fetchKpiCapConfig()`, setelah dua baris `kpiMaxScore`/`kpiMinScore` yang sudah ada:

```js
                    this.batasParameter = batasDariConfig(response.data.result);
```

- [ ] **Step 7: Pasang penjepit di dua halaman approval**

Untuk `Approval/Performance/performanceApproval.vue` (~4068) dan `Approval/Verification/VerifikasiPerformancePanel.vue` (~4214), impor dan state sama seperti Step 6, lalu ganti:

```js
                const achievement = Number(achievementRatio * 100);
                const score = Number(kpi.weight * achievementRatio);
```
menjadi:
```js
                const metrik = hitungMetrikParameter(achievementRatio, kpi.weight, this.batasParameter);
                const achievement = metrik.achievement;
                const score = metrik.score;
```

Di `fetchKpiCapConfig()` kedua berkas, tambahkan baris `batasDariConfig` yang sama.

**Penting:** `achievement` di kedua berkas ini diteruskan ke `checkPicaRemovalCondition()`. Karena batas (50/125) mengapit ambang PICA (100), keputusan PICA tidak berubah — 20% menjadi 50% tetap di bawah ambang, 300% menjadi 125% tetap di atas. Ini dikunci oleh tes di Task 8.

- [ ] **Step 8: Perbarui keterangan penjepitan di tiga halaman pengisian**

Keterangan sekarang berbicara tentang total. Ganti `capNote()` di `updatePerformance.vue` (~957) menjadi berbicara tentang parameter:

```js
        jumlahParameterTerjepit() {
            return this.activeKpiItems.filter((item) => item.isCapped).length;
        },
        isScoreCapped() {
            return this.jumlahParameterTerjepit > 0 || this.totalScoreRaw !== this.totalScore;
        },
        capNote() {
            const n = this.jumlahParameterTerjepit;
            if (n === 0) return '';

            const batas = `${this.batasParameter.min}%–${this.batasParameter.max}%`;
            return `${n} parameter disesuaikan ke batas ${batas}. Total tanpa penyesuaian ${this.formatScore(this.totalScoreRaw)}%.`;
        },
```

Dan `totalScoreRaw` harus dihitung dari skor mentah supaya kalimatnya benar:

```js
        totalScoreRaw() {
            const sumOfRaw = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.scoreRaw ?? item.score) || 0),
                0,
            );
            const sumOfActiveBobot = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.weight) || 0),
                0,
            );

            return sumOfActiveBobot === 0 ? 0 : (sumOfRaw / sumOfActiveBobot) * 100;
        },
        totalScore() {
            const sumOfScores = this.activeKpiItems.reduce((carry, item) => carry + (parseFloat(item.score) || 0), 0);
            const sumOfActiveBobot = this.activeKpiItems.reduce(
                (carry, item) => carry + (parseFloat(item.weight) || 0),
                0,
            );
            if (sumOfActiveBobot === 0) return 0;

            const raw = (sumOfScores / sumOfActiveBobot) * 100;
            return Math.max(this.kpiMinScore, Math.min(this.kpiMaxScore, raw));
        },
```

Simpan juga `item.scoreRaw = metrik.scoreRaw;` di Step 6 agar tersedia di sini.

Terapkan pola yang sama pada dua halaman team.

- [ ] **Step 9: Bangun frontend dan jalankan tes**

Run: `npm run test:unit -- resources/js/Pages/KPI/utils/kpiCap.test.js`
Expected: PASS.

Run: `npm run build`
Expected: selesai tanpa galat. Ini menangkap impor yang salah jalur.

- [ ] **Step 10: Commit**

```bash
git add resources/js/Pages/KPI/utils/kpiCap.js resources/js/Pages/KPI/utils/kpiCap.test.js app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php resources/js/Pages/KPI/Performance/Update/updatePerformance.vue resources/js/Pages/KPI/Performance/Update/team/updatePerformance.vue resources/js/Pages/KPI/Performance/Update/team/updatePerformanceByPass.vue resources/js/Pages/KPI/Approval/Performance/performanceApproval.vue resources/js/Pages/KPI/Approval/Verification/VerifikasiPerformancePanel.vue
git commit -m "feat(kpi): penjepit per parameter di sisi klien lewat satu modul bersama"
```

---

## Task 7: Halaman enforcement — label terbalik dan angka mentah

**Files:**
- Modify: `resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue`
- Modify: `app/Services/KPI/KpiEnforcementService.php` (payload detail ~305 dan ~371)

- [ ] **Step 1: Betulkan label yang terbalik**

Di kartu parameter modal detail (~1258), payload meneruskan kolom DB apa adanya sehingga `p.score` berisi achievement dan `p.achievement` berisi skor. Header modalnya sendiri (~1223) sudah benar. Tukar label supaya seluruh halaman memakai satu kosakata:

```html
                            <div class="pv">
                                <span>Achievement</span><strong>{{ p.score }}%</strong>
                            </div>
                            <div class="pv achv">
                                <span>Score</span><strong>{{ p.achievement }}</strong>
                            </div>
```

Yang ditukar hanya **label**; nama kunci payload dan nama kolom DB tidak disentuh, sesuai batasan global.

- [ ] **Step 2: Kirim angka mentah per parameter**

Di `KpiEnforcementService::getEnforcedResult()` (~305) dan `simulateForEmployee()` (~371), tambahkan ke tiap item detail:

```php
                    'score_raw' => isset($d->Score_Raw) ? (float) $d->Score_Raw : null,
                    'achievement_raw' => isset($d->Achievement_Raw) ? (float) $d->Achievement_Raw : null,
                    'is_capped' => ($d->Flag_Capped ?? 'T') === 'Y',
```

Pada `getEnforcedResult()`, tambahkan ketiga kolom itu ke daftar `get([...])`:

```php
                    'upd.Score_Raw',
                    'upd.Achievement_Raw',
                    'upd.Flag_Capped',
```

- [ ] **Step 3: Tampilkan angka mentah saat parameter dijepit**

Di kartu parameter, setelah blok Achievement:

```html
                            <div v-if="p.is_capped" class="pv pv-raw">
                                <span>Sebelum penyesuaian</span><strong>{{ p.score_raw }}%</strong>
                            </div>
```

- [ ] **Step 4: Bangun dan periksa di browser**

Run: `npm run build`

Buka `/kpi-enforcement`, buka satu record performance, pastikan label Achievement menampilkan persentase dan Score menampilkan angka berbobot.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/KPI/Monitoring/Enforcement/kpiEnforcementDashboard.vue app/Services/KPI/KpiEnforcementService.php
git commit -m "fix(kpi): label parameter halaman enforcement tak lagi terbalik, angka mentah ditampilkan"
```

---

## Task 8: Nyalakan penjepitan

Satu-satunya langkah yang mengubah nilai. Kerjakan setelah Task 1–7 selesai dan terverifikasi.

**Files:**
- Modify: `config/services.php`
- Modify: `app/Console/Commands/KPI/Operations/VerifyKpiYtd.php`
- Test: `tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`

- [ ] **Step 1: Tulis tes yang menuntut baku menyala**

```php
    public function test_baku_sistem_menjepit_pada_125_dan_50(): void
    {
        // Sengaja TIDAK menyetel config: menguji nilai baku aplikasi.
        [$max, $min] = $this->svc()->paramCapBounds();

        $this->assertSame(125.0, $max);
        $this->assertSame(50.0, $min);
    }

    public function test_keputusan_pica_tidak_berubah_oleh_penjepitan(): void
    {
        $this->nyalakanCapParameter();
        $ambangPica = 100;

        $jeblok = $this->svc()->scoreParameter(2, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');
        $tembus = $this->svc()->scoreParameter(30, 10, 50, '[actualKpiEvo]/[targetKpiEvo]');

        $this->assertLessThan($ambangPica, $jeblok['achievement'], 'Yang jeblok tetap wajib PICA');
        $this->assertGreaterThanOrEqual($ambangPica, $tembus['achievement'], 'Yang tembus tetap bebas PICA');
    }
```

- [ ] **Step 2: Jalankan, pastikan tes baku MERAH**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php`
Expected: `test_baku_sistem_menjepit_pada_125_dan_50` FAIL (dapat `null`); tes PICA sudah PASS.

- [ ] **Step 3: Nyalakan baku**

Di `config/services.php`, ganti kedua kunci per-parameter:

```php
        // Batas penjepitan PER PARAMETER, dalam satuan achievement (%).
        // Kosongkan env-nya untuk mematikan satu sisi tanpa rilis ulang.
        'param_max_achievement_score' => env('KPI_PARAM_MAX_ACHIEVEMENT_SCORE', 125) !== null
            ? (float) env('KPI_PARAM_MAX_ACHIEVEMENT_SCORE', 125)
            : null,
        'param_min_achievement_score' => env('KPI_PARAM_MIN_ACHIEVEMENT_SCORE', 50) !== null
            ? (float) env('KPI_PARAM_MIN_ACHIEVEMENT_SCORE', 50)
            : null,
```

- [ ] **Step 4: Beri catatan pada perintah verifikasi**

Di `VerifyKpiYtd.php` ~151, di atas pemanggilan `KpiFormulaHelper::evaluate`:

```php
            // SENGAJA memanggil helper langsung, bukan KpiScoringService: perintah
            // ini membandingkan, bukan menilai, sehingga ia harus melihat angka
            // SEBELUM penjepitan per parameter. Selisihnya terhadap skor tersimpan
            // adalah efek penjepitan, bukan cacat.
```

- [ ] **Step 5: Jalankan seluruh tes unit KPI**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI/`
Expected: seluruhnya PASS.

Perhatikan: empat tes garis dasar dari Task 1 tetap hijau karena mereka memanggil `applyCap()` langsung dengan angka yang sudah disiapkan, tidak lewat `scoreParameter()`.

- [ ] **Step 6: Bersihkan cache config**

Run: `env -u DATABASE_URL php artisan config:clear`

- [ ] **Step 7: Commit**

```bash
git add config/services.php app/Console/Commands/KPI/Operations/VerifyKpiYtd.php tests/Unit/KPI/Scoring/KpiScoringServiceCapTest.php
git commit -m "feat(kpi): nyalakan penjepitan achievement per parameter pada 125/50"
```

---

## Task 9: Verifikasi menyeluruh dan dokumentasi

- [ ] **Step 1: Gerbang verifikasi struktural**

```bash
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
php scripts/verify-frontend-imports.php
php scripts/verify-inertia-pages.php
```
Expected: seluruhnya lolos. `verify-frontend-imports.php` yang menangkap salah jalur impor `@/Pages/KPI/utils/kpiCap`.

- [ ] **Step 2: Tes backend terdampak**

Run: `env -u DATABASE_URL php artisan test tests/Unit/KPI tests/Feature/KPI`
Expected: PASS. Bila ada kegagalan di `tests/Feature/KPI`, periksa apakah ia berasumsi pada angka yang kini terjepit; perbaiki asumsinya, jangan longgarkan penjepitannya.

- [ ] **Step 3: Verifikasi manual tiga jalur pengisian**

Untuk masing-masing `/update-performance`, `/update-performance/team/{user}`, `/update-performance/by-pass/{user}`: isi satu parameter melampaui target dan satu jauh di bawah, lalu bandingkan angka layar dengan angka tersimpan.

```bash
env -u DATABASE_URL php artisan tinker --execute="
\$h = DB::table('KPI_Update_Performance')->orderByDesc('Id_Update_Performance')->first();
echo 'No='.\$h->No_Transaksi.' Total_Score='.\$h->Total_Score.' Raw='.\$h->Total_Score_Raw.' Capped='.\$h->Flag_Capped.' Cap='.\$h->Cap_Min_Applied.'-'.\$h->Cap_Max_Applied.PHP_EOL;
foreach (DB::table('KPI_Update_Performance_Detail')->where('Id_Update_Performance', \$h->Id_Update_Performance)->get() as \$d) {
    echo '  achievement='.\$d->Score.' (mentah '.(\$d->Score_Raw ?? '-').')  skor='.\$d->Achievement.' (mentah '.(\$d->Achievement_Raw ?? '-').')  capped='.\$d->Flag_Capped.PHP_EOL;
}
"
```
Expected: tidak ada `Score` melebihi 125 atau di bawah 50; `Total_Score` sama dengan rata-rata tertimbang baris-barisnya; `Flag_Capped` header `Y` bila ada baris `Y`.

- [ ] **Step 4: Verifikasi jalur approval**

Sebagai approver, ubah nilai aktual satu parameter pada record menunggu menjadi angka yang melampaui target. Pastikan totalnya dihitung ulang konsisten dengan detailnya, dan kolom mentah ikut terisi.

- [ ] **Step 5: Verifikasi record beraturan lama tidak tersentuh**

```bash
env -u DATABASE_URL php artisan tinker --execute="
echo 'detail tanpa kolom mentah (aturan lama): '.DB::table('KPI_Update_Performance_Detail')->whereNull('Score_Raw')->count().PHP_EOL;
echo 'detail beraturan baru               : '.DB::table('KPI_Update_Performance_Detail')->whereNotNull('Score_Raw')->count().PHP_EOL;
echo 'detail lama dengan achievement >125 : '.DB::table('KPI_Update_Performance_Detail')->whereNull('Score_Raw')->where('Score','>',125)->count().' (harus tetap ada, bukti tak dihitung ulang)'.PHP_EOL;
"
```

- [ ] **Step 6: Perbarui dokumentasi kolom**

Tulis `docs/database/tables/hris/kpi-update-performance-detail.md` (atau perbarui bila sudah ada) memuat arti ketiga kolom baru, alasan nullable sebagai pembeda aturan lama/baru, dan tanggal mulai berlakunya penjepitan.

- [ ] **Step 7: Commit terakhir**

```bash
git add docs/database/tables/hris/kpi-update-performance-detail.md
git commit -m "docs(kpi): dokumentasi kolom jejak penjepitan per parameter"
```

- [ ] **Step 8: Laporan penutup**

Laporkan dua bagian sesuai `AGENTS.md`:

**Selesai oleh agen** — berkas yang dibuat/diubah, DDL yang dijalankan di pengembangan beserta verifikasinya, hasil tes.

**Tugas pengguna** — tinjau dan setujui, catat `DDL-2026-045` ke SQL Tracker, jalankan ke produksi, verifikasi hasil produksi, dan putuskan kapan penjepitan diumumkan ke pemakai KPI.
