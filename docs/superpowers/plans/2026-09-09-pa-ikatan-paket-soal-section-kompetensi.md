# Ikatan Paket Soal PA Berbasis Bagian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengubah ikatan paket soal PA dari "HC Learn melempar kode soal beserta kompetensinya" menjadi "HC Learn melempar ujian beserta paket, alat tes, dan bagian tanpa label, dan admin memetakan tiap bagian ke tepat satu kompetensi".

**Architecture:** Header `N_PA_Question_Binding_Master` (sudah ada) menyimpan id **ujian** dan id paket per sel level × kategori penilai; tabel anak baru `N_PA_Question_Binding_Master_Detail` menyimpan pemetaan satu-satu bagian↔kompetensi, dengan identitas bagian berupa **pasangan** `(Id_Jenis_Indikator, Id_Jenis_Soal)` karena HC Learn tidak menyediakan id per-instance. Pemeriksaan kesehatan dijalankan di dua gerbang penulisan saja, hasilnya disimpan di baris headernya, dan matriks membaca kolom itu tanpa memanggil HC Learn.

**Tech Stack:** Laravel 11, SQL Server (`sqlsrv`), Inertia + Vue 3, PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-09-09-pa-ikatan-paket-soal-section-kompetensi-design.md` (r2)

## Global Constraints

- **Tidak ada migrasi Laravel.** Perubahan skema lewat `database/ddl/<id>/forward.sql` + `drop.sql`, dengan entri di `database/ddl/status.json`. Nomor bebas berikutnya: **DDL-2026-090**.
- **JANGAN menjalankan uji sampai pemilik memberi ACC.** Tulis kode dan ujinya sampai selesai, lalu berhenti. Menjalankan `php artisan test` mengunci `Web_HRIS` (RCSI mati) dan membekukan UI pengguna lain. Pembuktian MERAH dilakukan sekaligus setelah ACC.
- **Tiap penjaga regresi harus pernah dilihat MERAH.** Uji yang belum pernah gagal saat penjaganya dilepas bukan penjaga. Tuliskan di komentar uji cara membuatnya merah.
- **Identitas satu bagian adalah PASANGAN `(Id_Jenis_Indikator, Id_Jenis_Soal)`.** HC Learn tidak menyediakan id per-instance — hanya `id_jenis_soal`, yang dokumentasi mereka sendiri sebut pengenal **bank soal** yang bisa dipakai beberapa alat tes. Dikonfirmasi 9 September 2026 bahwa hanya itu yang tersedia.
- **`Id_Jenis_Indikator` ada di DETAIL, bukan header.** Satu paket boleh memuat dua alat tes; menaruhnya di header membuat ujian dua-alat-tes mustahil diikat.
- **Yang diikat adalah UJIAN (`id_master_ujian`)**, bukan paket. Dokumentasi HC Learn menyebutnya "satuan yang dijadwalkan kepada peserta", dan endpoint hasil juga berpangkal di sana.
- **Pemetaan satu-satu penuh.** Cacah bagian == cacah kompetensi wajib level; tiap bagian tepat satu label; tiap kompetensi tepat satu bagian; tidak ada label di luar daftar wajib level itu.
- **Kompetensi satuan, bukan jenis.** Label menunjuk `N_PA_Competency` (CV01–CV06, K01–K11, QL01–QL04, BH01–BH03), bukan `N_PA_Competency_Type`.
- **`Urutan_Alat_Tes` dan `Urutan_Bagian` tidak pernah jadi kunci.** Hanya untuk urutan tampilan.
- **`Sidik_Bagian` hanya memotret sisi HC Learn** — himpunan pasangan terurut. Jangan memasukkan kompetensi ke dalamnya.
- **Kode masalah `BERGESER` dicabut.** Kode yang berlaku: `UJIAN_HILANG`, `BAGIAN_KEMBAR`, `BAGIAN_HILANG`, `JUMLAH_TAK_SEPADAN`, `KOMPETENSI_KURANG`, `KOMPETENSI_LEBIH`.
- **Siklus berjalan tidak disentuh.** Akibat pemeriksaan hanya satu: penyalinan ke siklus baru ditolak.
- **`salinKeSiklus()` memeriksa segar**, tidak memercayai `Status_Kesehatan` yang tersimpan.
- **Gaya kode:** Pint (`__DIR__.'/...'` tanpa spasi). Komentar dalam bahasa Indonesia, menjelaskan **kenapa**, bukan apa.
- **Tidak ada halaman baru**, jadi tidak ada baris RBAC baru dan tidak perlu `flushMenuCache()`.

## Bentuk kontrak HC Learn yang dipakai rencana ini

Satu panggilan, `GET /api/v1/pa/nama-tes?limit=25&page=1` (parameter **wajib terurut abjad** — server mereka mengurutkannya sebelum memverifikasi tanda tangan). Tidak ada endpoint detail terpisah.

```
result[]                                  ← UJIAN, yang kita ikat
  .id_master_ujian
  .nama, .kode
  .paket
     .id_master_paket_ujian
     .nama
     .alat_tes[]                          ← 1 atau 2 untuk PA 360
        .id_jenis_indikator
        .nama, .tes_khusus, .jumlah_bagian
        .bagian[]                         ← yang dilabeli; KOSONG pada tes khusus
           .id_jenis_soal                 ← pengenal BANK SOAL, bukan instance
           .kode_soal, .nama, .keterangan
```

## Struktur berkas

| Berkas | Tanggung jawab |
|---|---|
| `database/ddl/DDL-2026-090-.../forward.sql` + `drop.sql` | tabel anak, ganti nama kolom, drop tabel mati |
| `app/Services/KPI/PerformanceAppraisal/Katalog/PaBagianKatalog.php` (BARU) | satu-satunya penafsir bentuk ujian→paket→alat tes→bagian |
| `app/Services/KPI/PerformanceAppraisal/Katalog/PaBijeksiLabel.php` (BARU) | aturan pemetaan satu-satu, murni, tanpa DB |
| `app/Services/KPI/PerformanceAppraisal/PaHcLearnCatalogService.php` | mengembalikan ujian ber-`Bagian` rata |
| `app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php` | simpan berlabel, `periksaSel()`, `matriks()` baca status tersimpan, `salinKeSiklus()` menyalin detail |
| `app/Http/Controllers/.../PaMasterDataController.php` | endpoint bagian, simpan berlabel, salin antar sel, periksa sekarang |
| `routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php` | rute baru |
| `resources/js/Pages/.../MasterData/ikatan-paket-soal.vue` | layar pelabelan |
| `resources/js/Pages/.../api/paApi.js` | pemanggil endpoint baru |

---

### Task 1: Skema — tabel anak, ganti nama kolom, buang tabel mati

**Files:**
- Create: `database/ddl/DDL-2026-090-pa-ikatan-paket-soal-bagian/forward.sql`
- Create: `database/ddl/DDL-2026-090-pa-ikatan-paket-soal-bagian/drop.sql`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Produces: tabel `N_PA_Question_Binding_Master_Detail`; kolom `N_PA_Question_Binding_Master.Id_Master_Ujian`, `.Id_Master_Paket_Ujian`, `.Nama_Ujian_Sumber`, `.Nama_Paket_Ujian_Sumber`, `.Jumlah_Bagian`, `.Sidik_Bagian`, `.Status_Kesehatan`, `.Masalah_Json`; kolom `N_PA_Cycle_Question_Package.Id_Master_Ujian`, `.Id_Master_Paket_Ujian`; kolom `N_PA_Cycle_Question_Package_Item.Id_Jenis_Indikator`, `.Id_Jenis_Soal`, `.Nama_Bagian_Sumber`.

- [ ] **Step 1: Tulis `forward.sql`**

`sp_rename` dipakai untuk ganti nama supaya indeks dan constraint yang menempel ikut terbawa; membuat kolom baru lalu menyalin akan menjatuhkan indeksnya diam-diam.

```sql
/*
| DDL-2026-090 — Ikatan paket soal berbasis bagian
|
| HC Learn mengubah kontraknya: yang dilempar kini ujian beserta paket, alat tes, dan
| bagian-bagiannya, dan bagian-bagian itu TIDAK berlabel kompetensi. Kompetensi karena
| itu berpindah menjadi milik kita, dipetakan admin satu bagian ke tepat satu kompetensi.
|
| Yang diikat adalah UJIAN, bukan paket: dokumentasi HC Learn menyebut id_master_ujian
| sebagai satuan yang dijadwalkan kepada peserta, dan endpoint hasil juga berpangkal di
| token ujian. Mengikat paket berarti mengikat sesuatu yang tidak bisa dijadwalkan.
|
| Identitas satu bagian adalah PASANGAN (Id_Jenis_Indikator, Id_Jenis_Soal). HC Learn
| tidak menyediakan id per-instance — hanya id_jenis_soal, yang dokumentasi mereka sendiri
| sebut pengenal BANK SOAL yang bisa dipakai beberapa alat tes.
|
| Rujukan: docs/superpowers/specs/2026-09-09-pa-ikatan-paket-soal-section-kompetensi-design.md
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ---------------------------------------------------------------
 | 1. Ikatan lama dan snapshot siklus dikosongkan.
 |
 | 45 baris ikatan yang ada semuanya berkode SIM-* (artefak simulasi lokal) dan mengacu
 | pada kontrak kode-soal yang sudah tidak berlaku. Dibiarkan, mereka akan tampak seperti
 | ikatan sah yang kebetulan tak punya detail.
 |
 | 1.160 butir paket siklus HARUS ikut dikosongkan, dan HARUS sebelum langkah 5: sebagian
 | PA_Competency_Id-nya NULL sehingga ALTER COLUMN ... NOT NULL akan ditolak, dan sebagian
 | kompetensinya rangkap sehingga indeks uniknya akan ditolak. Seluruhnya milik siklus uji
 | di development.
 --------------------------------------------------------------- */
DELETE FROM N_PA_Question_Binding_Master;
DELETE FROM N_PA_Cycle_Question_Package_Item;
DELETE FROM N_PA_Cycle_Question_Package;

/* ---------------------------------------------------------------
 | 2. Ganti nama kolom yang artinya berubah.
 |
 | 'Kode_Paket_Sumber' kini berisi ID UJIAN. Kode HC Learn terbukti tidak unik — 23 alat
 | di katalog semuanya kode = 'RDT', dan /detail/nama-tes/RDT menjawab HTTP 200 dengan
 | padanan pertama — sehingga memakai kode gagal tanpa terlihat gagal.
 --------------------------------------------------------------- */
EXEC sp_rename 'N_PA_Question_Binding_Master.Kode_Paket_Sumber', 'Id_Master_Ujian',    'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Nama_Paket_Sumber', 'Nama_Ujian_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Jumlah_Soal',       'Jumlah_Bagian',      'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Sidik_Susunan',     'Sidik_Bagian',       'COLUMN';

EXEC sp_rename 'N_PA_Cycle_Question_Package.Kode_Paket_Sumber',     'Id_Master_Ujian',    'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Nama_Paket_Sumber',     'Nama_Ujian_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Kode_Soal',        'Id_Jenis_Soal',      'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Nama_Soal_Sumber', 'Nama_Bagian_Sumber', 'COLUMN';

/* ---------------------------------------------------------------
 | 3. Kolom baru pada header.
 |
 | Id_Jenis_Indikator SENGAJA TIDAK di sini. Satu paket boleh memuat dua alat tes,
 | sehingga satu sel menyeberangi keduanya; menaruhnya di header memaksa satu sel hanya
 | bisa memuat satu alat tes, dan ujian dua-alat-tes menjadi mustahil diikat.
 |
 | Diverifikasi_At / Diverifikasi_By yang sudah ada dipakai ulang sebagai cap waktu
 | pemeriksaan. Menambah kolom kembar untuk makna yang sama membuat dua sumber yang kelak
 | berselisih.
 --------------------------------------------------------------- */
ALTER TABLE N_PA_Question_Binding_Master ADD
    Id_Master_Paket_Ujian   varchar(100) NULL,
    Nama_Paket_Ujian_Sumber nvarchar(255) NULL,
    Status_Kesehatan        varchar(20) NOT NULL
        CONSTRAINT DF_PA_QBindMaster_Kesehatan DEFAULT ('BELUM_DIPERIKSA'),
    Masalah_Json            nvarchar(max) NULL;

ALTER TABLE N_PA_Cycle_Question_Package ADD
    Id_Master_Paket_Ujian   varchar(100) NULL,
    Nama_Paket_Ujian_Sumber nvarchar(255) NULL;

/* ---------------------------------------------------------------
 | 4. Tabel anak: pemetaan bagian -> kompetensi.
 |
 | DUA indeks unik, bukan satu. Yang pertama melarang satu bagian punya dua label; yang
 | kedua melarang satu kompetensi dipegang dua bagian. Keduanya diperlukan — satu saja
 | meninggalkan separuh pemetaan bisa rangkap.
 |
 | Kunci bagiannya MAJEMUK karena HC Learn tidak menyediakan id per-instance.
 --------------------------------------------------------------- */
CREATE TABLE N_PA_Question_Binding_Master_Detail (
    Id_PA_Question_Binding_Master_Detail int IDENTITY(1,1) NOT NULL,
    PA_Question_Binding_Master_Id        int NOT NULL,
    Id_Jenis_Indikator                   varchar(100) NOT NULL,
    Id_Jenis_Soal                        varchar(100) NOT NULL,
    Nama_Alat_Tes                        nvarchar(255) NULL,
    Nama_Bagian_Sumber                   nvarchar(255) NULL,
    Kode_Soal_Sumber                     varchar(50) NULL,
    Urutan_Alat_Tes                      smallint NULL,
    Urutan_Bagian                        smallint NULL,
    PA_Competency_Id                     int NOT NULL,
    Flag_Aktif                           char(1) NOT NULL
        CONSTRAINT DF_PA_QBindDetail_Flag DEFAULT ('Y'),
    Created_At                           datetime2(0) NOT NULL
        CONSTRAINT DF_PA_QBindDetail_Created DEFAULT (SYSDATETIME()),
    Created_By                           int NULL,
    Updated_At                           datetime2(0) NULL,
    Updated_By                           int NULL,
    CONSTRAINT PK_PA_QBindDetail PRIMARY KEY (Id_PA_Question_Binding_Master_Detail),
    CONSTRAINT FK_PA_QBindDetail_Header FOREIGN KEY (PA_Question_Binding_Master_Id)
        REFERENCES N_PA_Question_Binding_Master (Id_PA_Question_Binding_Master),
    CONSTRAINT FK_PA_QBindDetail_Competency FOREIGN KEY (PA_Competency_Id)
        REFERENCES N_PA_Competency (Id_PA_Competency)
);

CREATE UNIQUE INDEX UX_PA_QBindDetail_Bagian
    ON N_PA_Question_Binding_Master_Detail
       (PA_Question_Binding_Master_Id, Id_Jenis_Indikator, Id_Jenis_Soal);

CREATE UNIQUE INDEX UX_PA_QBindDetail_Kompetensi
    ON N_PA_Question_Binding_Master_Detail
       (PA_Question_Binding_Master_Id, PA_Competency_Id);

/* ---------------------------------------------------------------
 | 5. Aturan yang sama pada jalur siklus.
 |
 | Snapshot harus menegakkan invarian yang sama dengan masternya. Kalau tidak, penyalinan
 | yang cacat akan menghasilkan siklus yang bentuknya mustahil dicapai lewat layar mana pun.
 --------------------------------------------------------------- */
ALTER TABLE N_PA_Cycle_Question_Package_Item ADD
    Id_Jenis_Indikator varchar(100) NOT NULL
        CONSTRAINT DF_PA_CycleQItem_Indikator DEFAULT (''),
    Nama_Alat_Tes      nvarchar(255) NULL;

ALTER TABLE N_PA_Cycle_Question_Package_Item ALTER COLUMN PA_Competency_Id int NOT NULL;

CREATE UNIQUE INDEX UX_PA_CycleQItem_Bagian
    ON N_PA_Cycle_Question_Package_Item
       (PA_Cycle_Question_Package_Id, Id_Jenis_Indikator, Id_Jenis_Soal);

CREATE UNIQUE INDEX UX_PA_CycleQItem_Kompetensi
    ON N_PA_Cycle_Question_Package_Item
       (PA_Cycle_Question_Package_Id, PA_Competency_Id);

/* ---------------------------------------------------------------
 | 6. Buang jembatan yang tidak pernah dipakai.
 |
 | N_PA_Competency_Kode_Soal dirancang untuk kontrak kode-soal yang kini digantikan.
 | Nol baris, nol rujukan kode, nol FK masuk. Sudah dihapus dari ERD versi 138.
 --------------------------------------------------------------- */
DROP TABLE N_PA_Competency_Kode_Soal;

COMMIT TRANSACTION;
```

- [ ] **Step 2: Tulis `drop.sql`**

```sql
/* Pembalik DDL-2026-090. Data detail TIDAK dikembalikan — hanya strukturnya. */

SET XACT_ABORT ON;
BEGIN TRANSACTION;

CREATE TABLE N_PA_Competency_Kode_Soal (
    Id_PA_Competency_Kode_Soal int IDENTITY(1,1) NOT NULL,
    PA_Competency_Id           int NOT NULL,
    Kode_Soal                  varchar(100) NOT NULL,
    Nama_Soal_Sumber           nvarchar(200) NULL,
    Sumber_Pemetaan            varchar(20) NOT NULL,
    Flag_Aktif                 char(1) NOT NULL CONSTRAINT DF_PA_CompKodeSoal_Flag DEFAULT ('Y'),
    Created_At                 datetime2(0) NOT NULL CONSTRAINT DF_PA_CompKodeSoal_Created DEFAULT (SYSDATETIME()),
    Created_By                 int NULL,
    Updated_At                 datetime2(0) NULL,
    Updated_By                 int NULL,
    CONSTRAINT PK_PA_Competency_Kode_Soal PRIMARY KEY (Id_PA_Competency_Kode_Soal),
    CONSTRAINT FK_PA_Competency_Kode_Soal_Competency FOREIGN KEY (PA_Competency_Id)
        REFERENCES N_PA_Competency (Id_PA_Competency)
);

DROP INDEX UX_PA_CycleQItem_Kompetensi ON N_PA_Cycle_Question_Package_Item;
DROP INDEX UX_PA_CycleQItem_Bagian     ON N_PA_Cycle_Question_Package_Item;
ALTER TABLE N_PA_Cycle_Question_Package_Item ALTER COLUMN PA_Competency_Id int NULL;
ALTER TABLE N_PA_Cycle_Question_Package_Item DROP CONSTRAINT DF_PA_CycleQItem_Indikator;
ALTER TABLE N_PA_Cycle_Question_Package_Item DROP COLUMN Id_Jenis_Indikator, Nama_Alat_Tes;

DROP TABLE N_PA_Question_Binding_Master_Detail;

ALTER TABLE N_PA_Cycle_Question_Package DROP COLUMN Id_Master_Paket_Ujian, Nama_Paket_Ujian_Sumber;

ALTER TABLE N_PA_Question_Binding_Master DROP CONSTRAINT DF_PA_QBindMaster_Kesehatan;
ALTER TABLE N_PA_Question_Binding_Master
    DROP COLUMN Id_Master_Paket_Ujian, Nama_Paket_Ujian_Sumber, Status_Kesehatan, Masalah_Json;

EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Nama_Bagian_Sumber', 'Nama_Soal_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Id_Jenis_Soal',      'Kode_Soal',         'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Nama_Ujian_Sumber',       'Nama_Paket_Sumber', 'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Id_Master_Ujian',         'Kode_Paket_Sumber', 'COLUMN';

EXEC sp_rename 'N_PA_Question_Binding_Master.Sidik_Bagian',      'Sidik_Susunan',     'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Jumlah_Bagian',     'Jumlah_Soal',       'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Nama_Ujian_Sumber', 'Nama_Paket_Sumber', 'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Id_Master_Ujian',   'Kode_Paket_Sumber', 'COLUMN';

COMMIT TRANSACTION;
```

- [ ] **Step 3: Tambahkan entri ke `database/ddl/status.json`**

Baca entri `DDL-2026-089` sebagai contoh bentuk persisnya, lalu ikuti kuncinya satu per satu:

```json
{
  "id": "DDL-2026-090-pa-ikatan-paket-soal-bagian",
  "judul": "Ikatan paket soal berbasis bagian, bukan kode soal",
  "development": { "status": "PENDING" },
  "production": { "status": "PENDING" },
  "sql_tracker": { "reference": null }
}
```

- [ ] **Step 4: Verifikasi JSON tetap sah**

Run: `php -r "json_decode(file_get_contents('database/ddl/status.json'), true) === null ? exit(1) : print('OK');"`
Expected: `OK`

- [ ] **Step 5: Commit**

```bash
git add database/ddl/DDL-2026-090-pa-ikatan-paket-soal-bagian database/ddl/status.json
git commit -m "ddl(pa): tabel anak bagian-kompetensi, dan kolom yang artinya berubah"
```

---

### Task 2: `PaBagianKatalog` — satu-satunya penafsir bentuk HC Learn

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Katalog/PaBagianKatalog.php`
- Test: `tests/Unit/KPI/PerformanceAppraisal/Katalog/PaBagianKatalogTest.php`

**Interfaces:**
- Produces:
  - `PaBagianKatalog::kunci(array $bagian): string` — `"{Id_Jenis_Indikator}|{Id_Jenis_Soal}"`.
  - `PaBagianKatalog::ratakan(array $ujian): array` — daftar rata seluruh bagian dari seluruh alat tes, tiap elemen `['id_jenis_indikator','id_jenis_soal','nama_alat_tes','nama','kode_soal','urutan_alat_tes','urutan_bagian']`, terurut menurut `(urutan_alat_tes, urutan_bagian)`.
  - `PaBagianKatalog::kembar(array $daftar): array` — `list<string>` kunci yang muncul lebih dari sekali.
  - `PaBagianKatalog::sidik(array $daftar): string` — SHA-256 dari kunci terurut.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Katalog;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use RuntimeException;
use Tests\TestCase;

/**
 * Identitas bagian adalah titik paling rapuh di kontrak baru HC Learn, dan satu-satunya
 * yang gagalnya TIDAK terlihat: label yang terikat ke identitas yang salah tetap
 * tersimpan, tetap tampak lengkap, dan baru terasa saat nilainya pulang ke kompetensi
 * yang keliru.
 *
 * HC Learn tidak menyediakan id per-instance untuk bagian — hanya `id_jenis_soal`, yang
 * dokumentasi mereka sendiri sebut pengenal BANK SOAL: "Bank yang sama bisa dipakai
 * beberapa alat tes." Karena itu identitasnya PASANGAN dengan alat tesnya, dan seluruh
 * penafsiran bentuk dipusatkan di satu kelas.
 *
 * Cara membuatnya MERAH: kembalikan kunci() menjadi hanya id_jenis_soal.
 */
class PaBagianKatalogTest extends TestCase
{
    /** Ujian dua alat tes, keduanya memakai bank soal 77 — sah, dan HARUS terbedakan. */
    private function ujianDuaAlatTes(): array
    {
        return [
            'id_master_ujian' => 31,
            'nama' => 'Internal Assessment',
            'paket' => [
                'id_master_paket_ujian' => 9,
                'nama' => 'PAKET A',
                'alat_tes' => [
                    [
                        'id_jenis_indikator' => 100,
                        'nama' => 'TIU',
                        'bagian' => [
                            ['id_jenis_soal' => 77, 'nama' => 'Kemampuan Verbal', 'kode_soal' => 'TKV'],
                            ['id_jenis_soal' => 78, 'nama' => 'Kemampuan Numerik', 'kode_soal' => 'TKN'],
                        ],
                    ],
                    [
                        'id_jenis_indikator' => 200,
                        'nama' => 'TKD',
                        'bagian' => [
                            ['id_jenis_soal' => 77, 'nama' => 'Kemampuan Verbal', 'kode_soal' => 'TKV'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_kunci_memasangkan_alat_tes_dengan_jenis_soal(): void
    {
        $k = PaBagianKatalog::kunci(['id_jenis_indikator' => 100, 'id_jenis_soal' => 77]);

        $this->assertSame('100|77', $k);
    }

    public function test_bank_soal_yang_sama_di_dua_alat_tes_TIDAK_bertabrakan(): void
    {
        // Inti perbaikan ini. Kalau kuncinya hanya id_jenis_soal, kedua bagian ini
        // terbaca sebagai bagian yang sama, dan salah satunya kehilangan labelnya.
        $daftar = PaBagianKatalog::ratakan($this->ujianDuaAlatTes());

        $this->assertCount(3, $daftar);
        $this->assertSame(
            ['100|77', '100|78', '200|77'],
            array_map(fn ($b) => PaBagianKatalog::kunci($b), $daftar),
        );
        $this->assertSame([], PaBagianKatalog::kembar($daftar));
    }

    public function test_ratakan_membawa_nama_alat_tes_tiap_bagian(): void
    {
        // Satu paket bisa memuat dua alat tes yang sama-sama punya "Bagian 1". Tanpa nama
        // alat tesnya, admin tidak tahu yang mana yang sedang dilabeli.
        $daftar = PaBagianKatalog::ratakan($this->ujianDuaAlatTes());

        $this->assertSame(['TIU', 'TIU', 'TKD'], array_column($daftar, 'nama_alat_tes'));
        $this->assertSame([0, 0, 1], array_column($daftar, 'urutan_alat_tes'));
        $this->assertSame([0, 1, 0], array_column($daftar, 'urutan_bagian'));
    }

    public function test_alat_tes_khusus_tanpa_bagian_dilewati(): void
    {
        // Kraeplin, PAPI, DISC: jumlah_bagian = 0 karena soalnya memang tidak dipecah.
        // Bukan galat — hanya tidak menyumbang bagian yang bisa dilabeli.
        $daftar = PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 300, 'nama' => 'Kraeplin', 'tes_khusus' => true, 'bagian' => []],
        ]]]);

        $this->assertSame([], $daftar);
    }

    public function test_bagian_tanpa_id_jenis_soal_ditolak(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/identitas bagian/i');

        PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 100, 'nama' => 'TIU', 'bagian' => [['nama' => 'Tanpa Id']]],
        ]]]);
    }

    public function test_pasangan_kembar_terdeteksi(): void
    {
        // Satu alat tes memakai bank soal yang sama dua kali. Dokumentasi HC Learn tidak
        // melarangnya, dan kalau terjadi, pemetaannya ambigu.
        $daftar = PaBagianKatalog::ratakan(['paket' => ['alat_tes' => [
            ['id_jenis_indikator' => 100, 'nama' => 'TIU', 'bagian' => [
                ['id_jenis_soal' => 77, 'nama' => 'Verbal A'],
                ['id_jenis_soal' => 77, 'nama' => 'Verbal B'],
            ]],
        ]]]);

        $this->assertSame(['100|77'], PaBagianKatalog::kembar($daftar));
    }

    public function test_sidik_tidak_bergantung_pada_urutan_masukan(): void
    {
        // Sidik memotret HIMPUNAN pasangan. Urutan yang berubah tanpa mengubah himpunannya
        // tidak merugikan pemetaan kita, jadi tidak boleh terbaca berubah.
        $a = [['id_jenis_indikator' => 100, 'id_jenis_soal' => 77], ['id_jenis_indikator' => 200, 'id_jenis_soal' => 78]];

        $this->assertSame(
            PaBagianKatalog::sidik($a),
            PaBagianKatalog::sidik(array_reverse($a)),
        );
    }

    public function test_sidik_berubah_ketika_ada_bagian_hilang(): void
    {
        $a = [['id_jenis_indikator' => 100, 'id_jenis_soal' => 77], ['id_jenis_indikator' => 200, 'id_jenis_soal' => 78]];

        $this->assertNotSame(PaBagianKatalog::sidik($a), PaBagianKatalog::sidik([$a[0]]));
    }
}
```

- [ ] **Step 2: Tulis implementasinya**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Katalog;

use RuntimeException;

/**
 * Satu-satunya tempat yang menafsirkan bentuk ujian → paket → alat tes → bagian dari
 * HC Learn.
 *
 * KENAPA IDENTITASNYA MAJEMUK
 *
 * HC Learn tidak menyediakan id per-instance untuk bagian. Yang ada hanya
 * `id_jenis_soal`, dan dokumentasi mereka sendiri menyebutnya pengenal BANK SOAL:
 * "Bank yang sama bisa dipakai beberapa alat tes." Dikonfirmasi 9 September 2026 bahwa
 * hanya itu yang bisa mereka sediakan.
 *
 * Dua field lain yang tampak menggoda dan keduanya berbahaya:
 *
 *   - posisi dalam array bergeser ketika bagian disisipkan di tengah, sehingga label ikut
 *     pindah ke bagian yang salah — tanpa galat, tanpa gejala;
 *   - `kode_soal` adalah kode JENIS soal, boleh null, dan berulang seperti banknya.
 *
 * Jadi identitasnya pasangan (id_jenis_indikator, id_jenis_soal). Pasangan itu unik hanya
 * selama satu alat tes tidak memakai bank yang sama dua kali — yang tidak dijamin di mana
 * pun. `kembar()` mendeteksinya supaya pengikatannya bisa DITOLAK, bukan disimpan ambigu.
 */
class PaBagianKatalog
{
    /**
     * @param  array<string, mixed>  $bagian
     */
    public static function kunci(array $bagian): string
    {
        return ($bagian['id_jenis_indikator'] ?? '').'|'.($bagian['id_jenis_soal'] ?? '');
    }

    /**
     * Meratakan seluruh bagian dari seluruh alat tes dalam satu ujian.
     *
     * Diratakan karena admin melabeli kompetensi, dan kompetensi tidak mengenal batas alat
     * tes. Nama alat tesnya tetap dibawa supaya layar bisa mengelompokkannya kembali —
     * satu paket bisa memuat dua alat tes yang sama-sama punya "Bagian 1".
     *
     * @param  array<string, mixed>  $ujian
     * @return list<array{id_jenis_indikator: string, id_jenis_soal: string, nama_alat_tes: string, nama: string, kode_soal: ?string, urutan_alat_tes: int, urutan_bagian: int}>
     */
    public static function ratakan(array $ujian): array
    {
        $daftar = [];
        $alatTes = (array) (($ujian['paket']['alat_tes'] ?? $ujian['alat_tes'] ?? []));

        foreach (array_values($alatTes) as $iAlat => $alat) {
            $alat = (array) $alat;

            /* Alat tes khusus (Kraeplin, PAPI, DISC) memang tidak berbagian — jumlah_bagian
               bernilai 0 menurut kontrak mereka. Dilewati, bukan digagalkan. */
            foreach (array_values((array) ($alat['bagian'] ?? [])) as $iBagian => $b) {
                $b = (array) $b;
                $idJenisSoal = $b['id_jenis_soal'] ?? null;

                if ($idJenisSoal === null || $idJenisSoal === '') {
                    throw new RuntimeException(
                        'Bagian soal ini tidak membawa identitas bagian yang bisa dipercaya. '
                        .'Posisi dalam daftar tidak boleh dipakai karena bergeser saat '
                        .'penyisipan, dan kode_soal tidak unik. Minta HC Learn menyertakan '
                        .'id_jenis_soal pada setiap bagian.'
                    );
                }

                $daftar[] = [
                    'id_jenis_indikator' => (string) ($alat['id_jenis_indikator'] ?? ''),
                    'id_jenis_soal' => (string) $idJenisSoal,
                    'nama_alat_tes' => (string) ($alat['nama'] ?? ''),
                    'nama' => (string) ($b['nama'] ?? ''),
                    'kode_soal' => isset($b['kode_soal']) ? (string) $b['kode_soal'] : null,

                    /* Urutan tampilan saja. TIDAK pernah dipakai sebagai kunci — kontrak
                       HC Learn sendiri menyatakan urutan array adalah urutan pengerjaan
                       dan tidak ada field nomor tersendiri. */
                    'urutan_alat_tes' => $iAlat,
                    'urutan_bagian' => $iBagian,
                ];
            }
        }

        return $daftar;
    }

    /**
     * Pasangan yang muncul lebih dari sekali.
     *
     * Selama ada yang kembar, seluruh pemetaan ambigu: dua bagian berbeda menunjuk baris
     * yang sama. Pemanggilnya WAJIB menolak pengikatan, bukan menyimpan yang terakhir.
     *
     * @param  list<array<string, mixed>>  $daftar
     * @return list<string>
     */
    public static function kembar(array $daftar): array
    {
        $hitung = array_count_values(array_map(fn ($b) => self::kunci($b), $daftar));

        return array_values(array_keys(array_filter($hitung, fn ($n) => $n > 1)));
    }

    /**
     * Sidik HIMPUNAN pasangan — bukan susunannya, dan bukan label kita.
     *
     * Label kita sengaja tidak ikut: sidik yang mencampur keduanya membuat admin yang
     * mengganti labelnya sendiri terbaca sebagai "ujian berubah di HC Learn".
     *
     * @param  list<array<string, mixed>>  $daftar
     */
    public static function sidik(array $daftar): string
    {
        $kunci = array_map(fn ($b) => self::kunci($b), $daftar);
        sort($kunci);

        return hash('sha256', implode("\n", $kunci));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Katalog/PaBagianKatalog.php tests/Unit/KPI/PerformanceAppraisal/Katalog/PaBagianKatalogTest.php
git commit -m "feat(pa): pusatkan penafsiran bagian HC Learn, identitas majemuk alat tes + bank soal"
```

---

### Task 3: `PaBijeksiLabel` — aturan pemetaan satu-satu, murni

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Katalog/PaBijeksiLabel.php`
- Test: `tests/Unit/KPI/PerformanceAppraisal/Katalog/PaBijeksiLabelTest.php`

**Interfaces:**
- Consumes: `PaBagianKatalog::ratakan()`, `PaBagianKatalog::kunci()`, `PaBagianKatalog::kembar()`.
- Produces: `PaBijeksiLabel::periksa(array $daftarBagian, array $kompetensiWajibId, array $label): array` — mengembalikan `list<array{jenis: string, pesan: string}>`; kosong berarti sah. `$label` berbentuk `[kunciBagian => paCompetencyId]` dengan kunci dari `PaBagianKatalog::kunci()`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal\Katalog;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBijeksiLabel;
use Tests\TestCase;

/**
 * Aturan pemetaan satu-satu, diuji tanpa basis data sama sekali.
 *
 * Aturannya kelihatan sepele sampai dijalankan pada level ASSISTEN MANAGER ke atas:
 * 21 kompetensi wajib berarti ujiannya HARUS punya 21 bagian, dan satu bagian nyasar
 * membuat seluruh sel tak bisa disimpan.
 *
 * Cara membuat tiap uji MERAH: hapus pemeriksaan yang bersangkutan dari periksa().
 */
class PaBijeksiLabelTest extends TestCase
{
    /** @return list<array{id_jenis_indikator: string, id_jenis_soal: string}> */
    private function bagian(int $n): array
    {
        return array_map(
            fn ($i) => ['id_jenis_indikator' => '100', 'id_jenis_soal' => (string) $i],
            range(1, $n),
        );
    }

    public function test_pemetaan_lengkap_dinyatakan_sah(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 13],
        );

        $this->assertSame([], $masalah);
    }

    public function test_pasangan_kembar_ditolak_dan_dilaporkan_paling_dulu(): void
    {
        // Selama pasangannya ambigu, pemeriksaan lain tidak bermakna — keduanya menunjuk
        // baris yang sama. Karena itu ia harus muncul PERTAMA, bukan terkubur di bawah
        // keluhan kompetensi kurang yang menyesatkan.
        $bagian = [
            ['id_jenis_indikator' => '100', 'id_jenis_soal' => '77'],
            ['id_jenis_indikator' => '100', 'id_jenis_soal' => '77'],
        ];

        $masalah = PaBijeksiLabel::periksa($bagian, [11, 12], ['100|77' => 11]);

        $this->assertSame('BAGIAN_KEMBAR', $masalah[0]['jenis']);
        $this->assertStringContainsString('100|77', $masalah[0]['pesan']);
    }

    public function test_cacah_bagian_lebih_banyak_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(4),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 13],
        );

        $this->assertContains('JUMLAH_TAK_SEPADAN', array_column($masalah, 'jenis'));
        // Pesannya wajib menyebut KEDUA kemungkinan: bagian berubah di HC Learn, atau
        // matriks kompetensi berubah di sisi kita. Gejalanya persis sama.
        $pesan = collect($masalah)->firstWhere('jenis', 'JUMLAH_TAK_SEPADAN')['pesan'];
        $this->assertMatchesRegularExpression('/HC Learn.*matriks|matriks.*HC Learn/is', $pesan);
    }

    public function test_bagian_tanpa_label_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa($this->bagian(3), [11, 12, 13], ['100|1' => 11, '100|2' => 12]);

        $this->assertContains('KOMPETENSI_KURANG', array_column($masalah, 'jenis'));
    }

    public function test_label_di_luar_daftar_wajib_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|3' => 99],
        );

        $jenis = array_column($masalah, 'jenis');
        $this->assertContains('KOMPETENSI_LEBIH', $jenis);
        $this->assertContains('KOMPETENSI_KURANG', $jenis);
    }

    public function test_satu_kompetensi_dipakai_dua_bagian_ditolak(): void
    {
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 11, '100|3' => 12],
        );

        $this->assertContains('KOMPETENSI_KURANG', array_column($masalah, 'jenis'));
    }

    public function test_label_menunjuk_bagian_yang_tidak_ada_dilaporkan(): void
    {
        // Bagian dinonaktifkan di HC Learn setelah dilabeli. Ini skenario yang justru
        // menjadi alasan pemeriksaan kesehatan ada.
        $masalah = PaBijeksiLabel::periksa(
            $this->bagian(3),
            [11, 12, 13],
            ['100|1' => 11, '100|2' => 12, '100|99' => 13],
        );

        $this->assertContains('BAGIAN_HILANG', array_column($masalah, 'jenis'));
    }
}
```

- [ ] **Step 2: Tulis implementasinya**

```php
<?php

namespace App\Services\KPI\PerformanceAppraisal\Katalog;

/**
 * Aturan pemetaan satu-satu antara bagian ujian dan kompetensi wajib satu level.
 *
 * Murni: tidak menyentuh basis data, tidak menyentuh jaringan. Aturannya cukup halus untuk
 * pantas diuji sendiri, dan cukup sering dipanggil — layar, gerbang simpan, dan gerbang
 * salin — untuk pantas punya satu penulis saja.
 */
class PaBijeksiLabel
{
    /**
     * @param  list<array<string, mixed>>  $daftarBagian  bagian yang ADA di ujian sekarang
     * @param  list<int>  $kompetensiWajibId              kompetensi wajib level tersebut
     * @param  array<string, int>  $label                 [kunci bagian => PA_Competency_Id]
     * @return list<array{jenis: string, pesan: string}>   kosong berarti sah
     */
    public static function periksa(array $daftarBagian, array $kompetensiWajibId, array $label): array
    {
        $masalah = [];

        /* Kekembaran diperiksa PALING DULU dan mengembalikan lebih awal. Selama pasangan
           (alat tes, bank soal) ambigu, keempat pemeriksaan di bawahnya tidak bermakna —
           dua bagian berbeda menunjuk baris yang sama, sehingga "kompetensi kurang" yang
           dilaporkannya akan menyesatkan admin ke arah yang salah. */
        $kembar = PaBagianKatalog::kembar($daftarBagian);

        if ($kembar !== []) {
            return [[
                'jenis' => 'BAGIAN_KEMBAR',
                'pesan' => sprintf(
                    'Ujian ini memuat %d bagian yang identitasnya kembar: %s. HC Learn tidak '
                    .'memberi id tersendiri per bagian, jadi bagian yang memakai bank soal '
                    .'yang sama di dalam satu alat tes tidak bisa dibedakan. Minta HC Learn '
                    .'memisahkan bank soalnya sebelum ujian ini diikat.',
                    count($kembar),
                    implode(', ', array_slice($kembar, 0, 8)),
                ),
            ]];
        }

        $kunciBagian = array_map(fn ($b) => PaBagianKatalog::kunci($b), $daftarBagian);
        $wajib = array_values(array_unique(array_map('intval', $kompetensiWajibId)));

        /* Bagian yang dilabeli tetapi sudah tidak ada di ujian. Diperiksa sebelum cacah:
           tanpa ini, bagian yang hilang hanya muncul sebagai kompetensi yang kurang, dan
           admin akan mencari-cari label yang sebenarnya masih ada. */
        $labelYatim = array_values(array_diff(array_keys($label), $kunciBagian));

        if ($labelYatim !== []) {
            $masalah[] = [
                'jenis' => 'BAGIAN_HILANG',
                'pesan' => sprintf(
                    'Bagian yang sudah dilabeli tidak ada lagi di ujian: %s. Kemungkinan besar '
                    .'dinonaktifkan di HC Learn setelah ikatan ini dibuat.',
                    implode(', ', array_slice($labelYatim, 0, 8)),
                ),
            ];
        }

        if (count($kunciBagian) !== count($wajib)) {
            $masalah[] = [
                'jenis' => 'JUMLAH_TAK_SEPADAN',
                'pesan' => sprintf(
                    'Ujian ini punya %d bagian, sedangkan level ini menuntut %d kompetensi. '
                    .'Karena satu bagian mewakili tepat satu kompetensi, keduanya harus sama. '
                    .'Bisa karena bagian ditambah atau dikurangi di HC Learn, bisa juga karena '
                    .'matriks kompetensi level ini berubah di sisi kita.',
                    count($kunciBagian),
                    count($wajib),
                ),
            ];
        }

        /* Hanya label pada bagian yang MASIH ADA yang dihitung menutupi kompetensi.
           Menghitung label yatim membuat sel yang bagiannya hilang tetap tampak lengkap. */
        $terpakai = [];

        foreach ($label as $kunci => $kompetensiId) {
            if (in_array((string) $kunci, $kunciBagian, true)) {
                $terpakai[] = (int) $kompetensiId;
            }
        }

        $terpakai = array_values(array_unique($terpakai));
        $kurang = array_values(array_diff($wajib, $terpakai));
        $lebih = array_values(array_diff($terpakai, $wajib));

        if ($kurang !== []) {
            $masalah[] = [
                'jenis' => 'KOMPETENSI_KURANG',
                'pesan' => sprintf(
                    '%d kompetensi wajib level ini belum dipegang bagian mana pun.',
                    count($kurang),
                ),
            ];
        }

        if ($lebih !== []) {
            $masalah[] = [
                'jenis' => 'KOMPETENSI_LEBIH',
                'pesan' => sprintf(
                    '%d kompetensi yang dilabelkan tidak berlaku di level ini.',
                    count($lebih),
                ),
            ];
        }

        return $masalah;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/Katalog/PaBijeksiLabel.php tests/Unit/KPI/PerformanceAppraisal/Katalog/PaBijeksiLabelTest.php
git commit -m "feat(pa): aturan pemetaan satu-satu bagian-kompetensi sebagai kelas murni"
```

---

### Task 4: Katalog mengembalikan ujian dengan bagian yang sudah diratakan

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaHcLearnCatalogService.php` — `rapikanBalasanHcLearn()` (baris 665–697), `paketSimulasi()` (baris 468), `pastikanDimensiCocok()` (baris 384)
- Test: `tests/Unit/KPI/PerformanceAppraisal/PaHcLearnCatalogBentukTest.php`

**Interfaces:**
- Consumes: `PaBagianKatalog::ratakan()`.
- Produces: tiap entri dari `daftarPaket()['paket']` berbentuk
  `['Id_Master_Ujian' => string, 'Nama_Ujian' => ?string, 'Id_Master_Paket_Ujian' => ?string, 'Nama_Paket' => ?string, 'Bagian' => list<...>, 'jumlah_bagian' => int, 'bagian_tersedia' => bool, 'simulasi' => bool]`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Unit\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Bentuk katalog adalah kontrak antara HC Learn dan seluruh modul PA. Ketika bentuknya
 * berubah diam-diam, gejalanya muncul jauh dari sebabnya — sebagai sel yang tampak kosong,
 * bukan sebagai galat.
 *
 * Cara membuatnya MERAH: kembalikan kunci 'Detail' di rapikanBalasanHcLearn().
 */
class PaHcLearnCatalogBentukTest extends TestCase
{
    private function rapikan(array $baris): array
    {
        $m = new ReflectionMethod(PaHcLearnCatalogService::class, 'rapikanBalasanHcLearn');
        $m->setAccessible(true);

        return $m->invoke(app(PaHcLearnCatalogService::class), $baris);
    }

    public function test_ujian_dibentuk_dengan_bagian_yang_sudah_diratakan(): void
    {
        $hasil = $this->rapikan([[
            'id_master_ujian' => 31,
            'nama' => 'Internal Assessment',
            'paket' => [
                'id_master_paket_ujian' => 9,
                'nama' => 'PAKET A',
                'alat_tes' => [
                    ['id_jenis_indikator' => 100, 'nama' => 'TIU', 'bagian' => [
                        ['id_jenis_soal' => 77, 'nama' => 'Verbal'],
                        ['id_jenis_soal' => 78, 'nama' => 'Numerik'],
                    ]],
                    ['id_jenis_indikator' => 200, 'nama' => 'TKD', 'bagian' => [
                        ['id_jenis_soal' => 77, 'nama' => 'Verbal'],
                    ]],
                ],
            ],
        ]]);

        $this->assertCount(1, $hasil);
        $this->assertSame('31', $hasil[0]['Id_Master_Ujian']);
        $this->assertSame('9', $hasil[0]['Id_Master_Paket_Ujian']);
        $this->assertSame('Internal Assessment', $hasil[0]['Nama_Ujian']);
        $this->assertSame('PAKET A', $hasil[0]['Nama_Paket']);
        $this->assertSame(3, $hasil[0]['jumlah_bagian']);
        $this->assertTrue($hasil[0]['bagian_tersedia']);
        $this->assertArrayNotHasKey('Detail', $hasil[0]);
    }

    public function test_ujian_yang_seluruh_alat_tesnya_khusus_ditandai_belum_tersedia(): void
    {
        // Kraeplin/PAPI/DISC punya jumlah_bagian = 0 karena soalnya memang tidak dipecah.
        // Ujian seperti itu tetap ditampilkan supaya admin tahu ia ada, tetapi layar harus
        // bisa mencegah pengikatannya.
        $hasil = $this->rapikan([[
            'id_master_ujian' => 40,
            'nama' => 'Kraeplin Saja',
            'paket' => ['id_master_paket_ujian' => 12, 'nama' => 'PAKET K', 'alat_tes' => [
                ['id_jenis_indikator' => 300, 'nama' => 'Kraeplin', 'tes_khusus' => true, 'bagian' => []],
            ]],
        ]]);

        $this->assertFalse($hasil[0]['bagian_tersedia']);
        $this->assertSame(0, $hasil[0]['jumlah_bagian']);
    }

    public function test_ujian_tanpa_id_dilewati_bukan_dijadikan_null(): void
    {
        // Baris ber-Id null pernah lolos pada bentuk lama dan menjadi ikatan yang menunjuk
        // ke mana-mana.
        $this->assertSame([], $this->rapikan([['nama' => 'Tanpa Id', 'paket' => ['alat_tes' => []]]]));
    }
}
```

- [ ] **Step 2: Ganti `rapikanBalasanHcLearn()`**

```php
    /**
     * Menyeragamkan balasan HC Learn ke bentuk yang dipakai seluruh modul.
     *
     * `id_master_ujian` yang dipakai sebagai acuan, BUKAN paket dan bukan kode. Dokumentasi
     * HC Learn menyebut ujian sebagai "satuan yang dijadwalkan kepada peserta", dan endpoint
     * hasil juga berpangkal di token ujian — mengikat paket berarti mengikat sesuatu yang
     * tidak bisa dijadwalkan. Kode juga tidak unik: 23 alat di katalog semuanya kode "RDT".
     *
     * @param  array<int|string, mixed>  $baris
     * @return list<array<string, mixed>>
     */
    private function rapikanBalasanHcLearn(array $baris): array
    {
        $hasil = [];

        foreach ($baris as $u) {
            $u = (array) $u;
            $id = $u['id_master_ujian'] ?? null;

            /* Ujian tanpa id DILEWATI, bukan disimpan ber-Id null. Bentuk lama
               menyimpannya, dan hasilnya ikatan yang menunjuk ke mana-mana. */
            if ($id === null || $id === '') {
                continue;
            }

            $paket = (array) ($u['paket'] ?? []);
            $bagian = PaBagianKatalog::ratakan($u);

            $hasil[] = [
                'Id_Master_Ujian' => (string) $id,
                'Nama_Ujian' => $u['nama'] ?? null,
                'Id_Master_Paket_Ujian' => isset($paket['id_master_paket_ujian'])
                    ? (string) $paket['id_master_paket_ujian']
                    : null,
                'Nama_Paket' => $paket['nama'] ?? null,
                'Kode_Ujian' => $u['kode'] ?? null,
                'Bagian' => $bagian,
                'jumlah_bagian' => count($bagian),
                'bagian_tersedia' => $bagian !== [],
                'simulasi' => false,
            ];
        }

        return $hasil;
    }
```

Tambahkan `use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;` di kepala berkas.

- [ ] **Step 3: Sesuaikan `paketSimulasi()`**

Simulasi harus menghasilkan ujian yang **bagiannya sebanyak kompetensi wajib** tiap level, kalau tidak simulasi tidak akan pernah bisa disimpan dan seluruh alur tak bisa dicoba secara lokal. Satu di antaranya sengaja dibuat dua alat tes, supaya jalur itu ikut terlatih:

```php
    /**
     * Ujian simulasi lokal.
     *
     * Cacah bagiannya SENGAJA disamakan dengan cacah kompetensi wajib tiap level (3, 17,
     * 21). Simulasi yang bagiannya tidak sepadan akan selalu ditolak aturan pemetaan
     * satu-satu, dan seluruh alur menjadi tak bisa dicoba tanpa HC Learn.
     *
     * Yang 21 sengaja dipecah ke DUA alat tes: itu bentuk yang dinyatakan HC Learn akan
     * dipakai PA 360, dan jalur dua-alat-tes tidak boleh hanya terlatih di uji.
     *
     * @return list<array<string, mixed>>
     */
    private function paketSimulasi(): array
    {
        $buat = function (string $id, string $nama, array $pecahan): array {
            $bagian = [];
            $n = 0;

            foreach (array_values($pecahan) as $iAlat => $jumlah) {
                $indikator = "SIM-{$id}-A".($iAlat + 1);

                for ($i = 1; $i <= $jumlah; $i++) {
                    $n++;
                    $bagian[] = [
                        'id_jenis_indikator' => $indikator,
                        'id_jenis_soal' => "SIM-{$id}-S{$n}",
                        'nama_alat_tes' => 'Alat Tes '.($iAlat + 1),
                        'nama' => "Bagian {$i}",
                        'kode_soal' => null,
                        'urutan_alat_tes' => $iAlat,
                        'urutan_bagian' => $i - 1,
                    ];
                }
            }

            return [
                'Id_Master_Ujian' => "SIM-{$id}",
                'Nama_Ujian' => $nama,
                'Id_Master_Paket_Ujian' => "SIM-{$id}-PKT",
                'Nama_Paket' => 'Paket '.$nama,
                'Kode_Ujian' => null,
                'Bagian' => $bagian,
                'jumlah_bagian' => count($bagian),
                'bagian_tersedia' => $bagian !== [],
                'simulasi' => true,
            ];
        };

        return [
            $buat('PRAMUKARYA', 'Simulasi — Pramukarya (3 bagian)', [3]),
            $buat('STAFF', 'Simulasi — Staff s.d. Senior Officer (17 bagian)', [17]),
            $buat('MANAJERIAL', 'Simulasi — Assisten Manager ke atas (21 bagian, 2 alat tes)', [11, 10]),
        ];
    }
```

- [ ] **Step 4: Cabut `pastikanDimensiCocok()`**

Metode itu memverifikasi `Detail[].Kode_Kompetensi` — kolom yang tidak ada lagi karena kompetensi bukan lagi milik HC Learn. Hapus metodenya beserta seluruh pemanggilnya di berkas yang sama; penggantinya pemeriksaan Task 3 yang dipanggil pada Task 5.

Cari pemanggilnya dengan:

```bash
grep -rn "pastikanDimensiCocok\|Kode_Kompetensi\|Id_Master_Ujian'\]\|Nama_Ujian'\]\|\['Detail'\]" app/ resources/js/ tests/
```

Setiap kemunculan harus hilang atau berganti ke bentuk baru. Yang tersisa akan menjadi galat runtime yang menyamar jadi "paket kosong".

- [ ] **Step 5: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaHcLearnCatalogService.php tests/Unit/KPI/PerformanceAppraisal/PaHcLearnCatalogBentukTest.php
git commit -m "feat(pa): katalog HC Learn mengembalikan ujian dengan bagian yang diratakan"
```

---

### Task 5: `simpan()` menerima label dan menulis header + detail

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php` — `simpan()` (baris 224–306), `sidik()` (baris 58–75)
- Test: `tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalSimpanTest.php`

**Interfaces:**
- Consumes: `PaBijeksiLabel::periksa()`, `PaBagianKatalog::kunci()`, `PaBagianKatalog::sidik()`.
- Produces: `simpan(int $levelId, ?int $raterCategoryId, string $idMasterUjian, array $label, ?int $olehUserId = null): array` — `$label` berbentuk `[kunciBagian => PA_Competency_Id]`; mengembalikan `['id' => int, 'baru' => bool, 'jumlah_bagian' => int, 'status_kesehatan' => 'SEHAT']`.
- Produces: `kompetensiWajibLevel(int $levelId): array` — `list<int>` id `N_PA_Competency`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Gerbang simpan ikatan paket soal.
 *
 * Aturan pemetaan satu-satu ditegakkan DI DUA LAPIS: kode menolaknya dengan pesan yang
 * bisa dibaca admin, dan dua indeks unik menolaknya di basis data. Uji ini menjaga lapis
 * pertama; lapis kedua dijaga DDL-2026-090.
 *
 * Cara membuat tiap uji MERAH: buang pemanggilan PaBijeksiLabel::periksa() dari simpan().
 */
class PaIkatanPaketSoalSimpanTest extends TestCase
{
    private PaQuestionBindingMasterService $service;

    private int $levelId;

    /** @var list<int> */
    private array $kompetensiWajib;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pa_hclearn_simulasi.aktif' => true]);

        $this->service = app(PaQuestionBindingMasterService::class);

        /* Level yang BENAR-BENAR punya syarat kompetensi diambil dari data, bukan ditulis
           sebagai angka. Angka mutlak di fixture akan berbohong begitu matriks kompetensi
           berubah. */
        $baris = DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('ID_Level')
            ->first(['ID_Level']);

        $this->assertNotNull($baris, 'Data syarat kompetensi kosong; uji ini tidak bermakna.');

        $this->levelId = (int) $baris->ID_Level;
        $this->kompetensiWajib = $this->service->kompetensiWajibLevel($this->levelId);
    }

    /** @return array<string, mixed>|null */
    private function ujianSepadan(): ?array
    {
        foreach (app(PaHcLearnCatalogService::class)->daftarPaket()['paket'] as $u) {
            if ($u['jumlah_bagian'] === count($this->kompetensiWajib)) {
                return $u;
            }
        }

        return null;
    }

    /** @return array<string, int> */
    private function labelLengkap(array $ujian): array
    {
        $label = [];

        foreach ($this->kompetensiWajib as $i => $kompetensiId) {
            $label[PaBagianKatalog::kunci($ujian['Bagian'][$i])] = $kompetensiId;
        }

        return $label;
    }

    public function test_pemetaan_lengkap_tersimpan_beserta_detailnya(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian, 'Tidak ada ujian simulasi sepadan dengan kompetensi wajib level uji.');

        $hasil = $this->service->simpan(
            $this->levelId, null, $ujian['Id_Master_Ujian'], $this->labelLengkap($ujian),
        );

        $this->assertSame('SEHAT', $hasil['status_kesehatan']);
        $this->assertSame(count($this->kompetensiWajib), $hasil['jumlah_bagian']);

        $detail = DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $hasil['id'])
            ->where('Flag_Aktif', 'Y')
            ->get();

        $this->assertCount(count($this->kompetensiWajib), $detail);
        // Indikatornya WAJIB tersimpan — tanpa itu identitas bagiannya tidak lengkap dan
        // dua bagian dari alat tes berbeda akan bertabrakan.
        $this->assertTrue($detail->every(fn ($d) => $d->Id_Jenis_Indikator !== '' && $d->Id_Jenis_Indikator !== null));
    }

    public function test_label_kurang_satu_ditolak_dan_tidak_menyisakan_apa_pun(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian);

        $label = $this->labelLengkap($ujian);
        array_pop($label);

        $sebelum = DB::table('N_PA_Question_Binding_Master_Detail')->count();

        try {
            $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);
            $this->fail('Pemetaan yang tidak lengkap seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('kompetensi', strtolower($e->getMessage()));
        }

        // Penolakan HARUS bersih. Header tersimpan tanpa detail adalah keadaan yang paling
        // menyesatkan: layar melaporkan sel terisi, lalu penyalinannya kosong.
        $this->assertSame($sebelum, DB::table('N_PA_Question_Binding_Master_Detail')->count());
    }

    public function test_ujian_yang_cacah_bagiannya_tidak_sepadan_ditolak(): void
    {
        $tidakSepadan = null;

        foreach (app(PaHcLearnCatalogService::class)->daftarPaket()['paket'] as $u) {
            if ($u['jumlah_bagian'] !== count($this->kompetensiWajib) && $u['jumlah_bagian'] > 0) {
                $tidakSepadan = $u;
                break;
            }
        }

        $this->assertNotNull($tidakSepadan, 'Butuh ujian simulasi yang cacahnya berbeda.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/bagian/i');

        $this->service->simpan($this->levelId, null, $tidakSepadan['Id_Master_Ujian'], []);
    }

    public function test_menyimpan_ulang_mengganti_detail_lama_bukan_menumpuknya(): void
    {
        $ujian = $this->ujianSepadan();
        $this->assertNotNull($ujian);

        $label = $this->labelLengkap($ujian);
        $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);

        // Tukar dua label, lalu simpan ulang sel yang sama.
        $kunci = array_keys($label);
        [$label[$kunci[0]], $label[$kunci[1]]] = [$label[$kunci[1]], $label[$kunci[0]]];

        $hasil = $this->service->simpan($this->levelId, null, $ujian['Id_Master_Ujian'], $label);

        $this->assertFalse($hasil['baru']);
        $this->assertSame(
            count($this->kompetensiWajib),
            DB::table('N_PA_Question_Binding_Master_Detail')
                ->where('PA_Question_Binding_Master_Id', $hasil['id'])
                ->where('Flag_Aktif', 'Y')
                ->count(),
        );
    }
}
```

- [ ] **Step 2: Tambahkan `kompetensiWajibLevel()`**

`matriks()` sudah menghitung ini di dalam badannya (baris 122–128). Angkat menjadi metode publik supaya layar, gerbang simpan, dan uji membaca daftar yang sama — dua perhitungan terpisah untuk daftar yang sama adalah cara keduanya kelak berselisih:

```php
    /**
     * Kompetensi wajib satu level, sebagai daftar Id_PA_Competency.
     *
     * Satu penulis untuk daftar ini. Layar, gerbang simpan, dan pemeriksaan kesehatan
     * semuanya membacanya dari sini — perhitungan yang disalin akan berselisih diam-diam
     * begitu salah satunya diperbaiki.
     *
     * @return list<int>
     */
    public function kompetensiWajibLevel(int $levelId): array
    {
        return DB::table('N_PA_Competency_Requirement as r')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'r.PA_Competency_Type_Id')
            ->join('N_PA_Competency as c', 'c.PA_Competency_Type_Id', '=', 't.Id_PA_Competency_Type')
            ->where('r.ID_Level', $levelId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('t.Flag_Aktif', 'Y')
            ->where('c.Flag_Aktif', 'Y')
            ->orderBy('c.Id_PA_Competency')
            ->pluck('c.Id_PA_Competency')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }
```

- [ ] **Step 3: Tulis ulang `simpan()`**

```php
    /**
     * Mengikat satu sel ke ujian HC Learn, beserta pemetaan bagian -> kompetensi.
     *
     * SELURUHNYA dalam satu transaksi, dan divalidasi SEBELUM menulis apa pun. Header yang
     * tersimpan tanpa detail adalah keadaan paling menyesatkan di alur ini: matriks
     * melaporkan sel terisi, langkah penyiapan ditandai selesai, lalu penyalinannya ke
     * siklus menghasilkan paket kosong.
     *
     * @param  array<string, int>  $label  [kunci bagian => PA_Competency_Id]
     * @return array<string, mixed>
     */
    public function simpan(
        int $levelId,
        ?int $raterCategoryId,
        string $idMasterUjian,
        array $label,
        ?int $olehUserId = null,
    ): array {
        $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->firstWhere('Id_Master_Ujian', $idMasterUjian);

        if ($ujian === null) {
            throw new RuntimeException("Ujian '{$idMasterUjian}' tidak ada di katalog HC Learn.");
        }

        $bagian = $ujian['Bagian'] ?? [];

        if ($bagian === []) {
            throw new RuntimeException(
                "Ujian '{$idMasterUjian}' tidak memuat bagian yang bisa dilabeli. Alat tes "
                .'khusus seperti Kraeplin, PAPI, dan DISC memang tidak dipecah per bagian, '
                .'sehingga tidak bisa dipetakan ke kompetensi.'
            );
        }

        $this->pastikanKategoriMenilai($levelId, $raterCategoryId);

        $wajib = $this->kompetensiWajibLevel($levelId);
        $masalah = PaBijeksiLabel::periksa($bagian, $wajib, $label);

        if ($masalah !== []) {
            throw new RuntimeException(collect($masalah)->pluck('pesan')->implode(' '));
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $petaBagian = collect($bagian)->keyBy(fn ($b) => PaBagianKatalog::kunci($b));

        $nilai = [
            'Id_Master_Ujian' => $idMasterUjian,
            'Nama_Ujian_Sumber' => $ujian['Nama_Ujian'] ?? null,
            'Id_Master_Paket_Ujian' => $ujian['Id_Master_Paket_Ujian'] ?? null,
            'Nama_Paket_Ujian_Sumber' => $ujian['Nama_Paket'] ?? null,
            'Jumlah_Kompetensi' => count($wajib),
            'Jumlah_Bagian' => count($bagian),
            'Sidik_Bagian' => PaBagianKatalog::sidik($bagian),
            'Status_Kesehatan' => 'SEHAT',
            'Masalah_Json' => null,
            'Diverifikasi_At' => $sekarang,
            'Diverifikasi_By' => $olehUserId,
        ];

        return DB::transaction(function () use (
            $levelId, $raterCategoryId, $nilai, $label, $petaBagian, $sekarang, $olehUserId
        ) {
            $ada = DB::table('N_PA_Question_Binding_Master')
                ->where('ID_Level', $levelId)
                ->where('Flag_Aktif', 'Y')
                ->when(
                    $raterCategoryId === null,
                    fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                    fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
                )
                ->value('Id_PA_Question_Binding_Master');

            if ($ada !== null) {
                DB::table('N_PA_Question_Binding_Master')
                    ->where('Id_PA_Question_Binding_Master', $ada)
                    ->update($nilai + ['Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);

                /* Detail lama DIHAPUS, bukan dinonaktifkan. Indeks uniknya berlaku pada
                   seluruh baris tanpa memandang Flag_Aktif, jadi menyisakan baris mati akan
                   menolak pelabelan ulang yang sah. */
                DB::table('N_PA_Question_Binding_Master_Detail')
                    ->where('PA_Question_Binding_Master_Id', $ada)
                    ->delete();

                $id = (int) $ada;
                $baru = false;
            } else {
                $id = (int) DB::table('N_PA_Question_Binding_Master')->insertGetId($nilai + [
                    'ID_Level' => $levelId,
                    'PA_Rater_Category_Id' => $raterCategoryId,
                    'Created_By' => $olehUserId,
                ], 'Id_PA_Question_Binding_Master');
                $baru = true;
            }

            $baris = [];

            foreach ($label as $kunci => $kompetensiId) {
                $b = $petaBagian[(string) $kunci] ?? null;

                $baris[] = [
                    'PA_Question_Binding_Master_Id' => $id,
                    'Id_Jenis_Indikator' => (string) ($b['id_jenis_indikator'] ?? ''),
                    'Id_Jenis_Soal' => (string) ($b['id_jenis_soal'] ?? ''),
                    'Nama_Alat_Tes' => $b['nama_alat_tes'] ?? null,
                    'Nama_Bagian_Sumber' => $b['nama'] ?? null,
                    'Kode_Soal_Sumber' => $b['kode_soal'] ?? null,
                    'Urutan_Alat_Tes' => $b['urutan_alat_tes'] ?? null,
                    'Urutan_Bagian' => $b['urutan_bagian'] ?? null,
                    'PA_Competency_Id' => (int) $kompetensiId,
                    'Created_At' => $sekarang,
                    'Created_By' => $olehUserId,
                ];
            }

            DB::table('N_PA_Question_Binding_Master_Detail')->insert($baris);

            return [
                'id' => $id,
                'baru' => $baru,
                'jumlah_bagian' => count($baris),
                'status_kesehatan' => 'SEHAT',
            ];
        });
    }
```

- [ ] **Step 4: Angkat penjaga kategori penilai ke metodenya sendiri**

Blok pada baris 243–264 dipindahkan apa adanya (termasuk komentarnya) menjadi `private function pastikanKategoriMenilai(int $levelId, ?int $raterCategoryId): void`, supaya `simpan()` yang sudah panjang tidak bertambah panjang lagi. Perilakunya tidak berubah.

- [ ] **Step 5: Buang `sidik()` yang lama**

Metode `private static function sidik(array $detail)` pada baris 58–75 dihapus — ia meng-hash `Kode_Kompetensi` yang tidak ada lagi. Penggantinya `PaBagianKatalog::sidik()`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalSimpanTest.php
git commit -m "feat(pa): simpan ikatan menuntut pemetaan bagian-kompetensi yang lengkap"
```

---

### Task 6: `periksaSel()` baru, `matriks()` membaca status tersimpan

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php` — `matriks()` (baris 82–216), `periksa()` (baris 681–750), `verifikasiUlang()` (baris 335–406)
- Test: `tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalKesehatanTest.php`

**Interfaces:**
- Consumes: `PaBijeksiLabel::periksa()`, `PaBagianKatalog::sidik()`, `kompetensiWajibLevel()`.
- Produces: `periksaSel(int $bindingId, ?int $olehUserId = null): array` — `['status' => 'SEHAT'|'BERMASALAH', 'masalah' => list<array{jenis,pesan}>]`.
- Produces: `matriks()` yang **tidak memanggil HC Learn sama sekali**; tiap sel terisi membawa `status_kesehatan`, `masalah`, `diperiksa_pada`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pemeriksaan kesehatan ikatan paket soal.
 *
 * Dua sifat yang dijaga di sini, dan keduanya pernah salah di modul lain:
 *
 *   1. matriks() TIDAK memanggil HC Learn. Halaman yang memanggil layanan luar tiap dibuka
 *      ikut mati ketika layanan itu mati.
 *   2. Sel yang bermasalah TIDAK disegarkan sidiknya. Menyegarkannya menghapus bukti bahwa
 *      ia bermasalah, dan pemeriksaan berikutnya melaporkannya bersih padahal tak ada yang
 *      diperbaiki.
 *
 * Cara membuat uji pertama MERAH: kembalikan pemanggilan daftarPaket() ke dalam matriks().
 */
class PaIkatanPaketSoalKesehatanTest extends TestCase
{
    /** @return array{0: PaQuestionBindingMasterService, 1: int, 2: array<string, mixed>, 3: array<string, int>} */
    private function siapkan(): array
    {
        config(['services.pa_hclearn_simulasi.aktif' => true]);

        $service = app(PaQuestionBindingMasterService::class);
        $levelId = (int) DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')->orderBy('ID_Level')->value('ID_Level');
        $wajib = $service->kompetensiWajibLevel($levelId);

        $ujian = collect(app(PaHcLearnCatalogService::class)->daftarPaket()['paket'])
            ->firstWhere('jumlah_bagian', count($wajib));

        $this->assertNotNull($ujian, 'Tidak ada ujian simulasi sepadan.');

        $label = [];
        foreach ($wajib as $i => $kompetensiId) {
            $label[PaBagianKatalog::kunci($ujian['Bagian'][$i])] = $kompetensiId;
        }

        return [$service, $levelId, $ujian, $label];
    }

    public function test_matriks_tidak_memanggil_hc_learn(): void
    {
        config(['services.pa_hclearn_simulasi.aktif' => false]);
        Http::preventStrayRequests();
        Http::fake();

        app(PaQuestionBindingMasterService::class)->matriks();

        Http::assertNothingSent();
    }

    public function test_matriks_menampilkan_status_yang_tersimpan_beserta_cap_waktunya(): void
    {
        [$service, $levelId, $ujian, $label] = $this->siapkan();

        $service->simpan($levelId, null, $ujian['Id_Master_Ujian'], $label);

        $m = $service->matriks();
        $level = collect($m['level'])->firstWhere('id_level', $levelId);
        $sel = collect($level['sel'])->first(fn ($s) => ($s['terisi'] ?? false));

        $this->assertSame('SEHAT', $sel['status_kesehatan']);
        $this->assertNotNull($sel['diperiksa_pada']);
    }

    public function test_sel_bermasalah_tidak_disegarkan_sidiknya(): void
    {
        [$service, $levelId, $ujian, $label] = $this->siapkan();

        $hasil = $service->simpan($levelId, null, $ujian['Id_Master_Ujian'], $label);

        // Rusakkan dari sisi KITA: satu detail dihapus, seolah bagiannya hilang.
        DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $hasil['id'])
            ->limit(1)
            ->delete();

        $sidikSebelum = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Sidik_Bagian');

        $periksa = $service->periksaSel($hasil['id']);

        $this->assertSame('BERMASALAH', $periksa['status']);
        $this->assertContains('KOMPETENSI_KURANG', array_column($periksa['masalah'], 'jenis'));

        $this->assertSame($sidikSebelum, DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Sidik_Bagian'));
    }
}
```

- [ ] **Step 2: Tulis `periksaSel()`**

```php
    /**
     * Memeriksa SATU sel terhadap katalog HC Learn dan menyimpan hasilnya.
     *
     * Ini satu-satunya jalan yang memanggil HC Learn di kelas ini. Pemanggilnya cuma tiga:
     * gerbang simpan, gerbang salin ke siklus, dan tombol "Periksa Sekarang". Matriks TIDAK
     * memanggilnya — ia membaca kolom yang ditulis di sini.
     *
     * @return array{status: string, masalah: list<array{jenis: string, pesan: string}>}
     */
    public function periksaSel(int $bindingId, ?int $olehUserId = null): array
    {
        $ikatan = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $bindingId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($ikatan === null) {
            throw new RuntimeException("Ikatan {$bindingId} tidak ditemukan atau sudah dicabut.");
        }

        $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->firstWhere('Id_Master_Ujian', $ikatan->Id_Master_Ujian);

        $masalah = [];

        if ($ujian === null) {
            $masalah[] = [
                'jenis' => 'UJIAN_HILANG',
                'pesan' => sprintf(
                    'Ujian %s tidak ada lagi di katalog HC Learn. Sel ini tampak terisi tetapi '
                    .'tidak bisa disalin ke siklus mana pun.',
                    $ikatan->Id_Master_Ujian,
                ),
            ];
        } else {
            $bagian = $ujian['Bagian'] ?? [];

            $label = DB::table('N_PA_Question_Binding_Master_Detail')
                ->where('PA_Question_Binding_Master_Id', $bindingId)
                ->where('Flag_Aktif', 'Y')
                ->get(['Id_Jenis_Indikator', 'Id_Jenis_Soal', 'PA_Competency_Id'])
                ->mapWithKeys(fn ($d) => [
                    $d->Id_Jenis_Indikator.'|'.$d->Id_Jenis_Soal => (int) $d->PA_Competency_Id,
                ])
                ->all();

            $masalah = PaBijeksiLabel::periksa(
                $bagian,
                $this->kompetensiWajibLevel((int) $ikatan->ID_Level),
                $label,
            );

            /* Sidik hanya disegarkan bila selnya SAHIH. Menyegarkan sel bermasalah menghapus
               bukti bahwa ia bermasalah, dan pemeriksaan berikutnya akan melaporkannya
               bersih padahal tak ada yang diperbaiki. */
            if ($masalah === []) {
                DB::table('N_PA_Question_Binding_Master')
                    ->where('Id_PA_Question_Binding_Master', $bindingId)
                    ->update([
                        'Sidik_Bagian' => PaBagianKatalog::sidik($bagian),
                        'Jumlah_Bagian' => count($bagian),
                    ]);
            }
        }

        DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $bindingId)
            ->update([
                'Status_Kesehatan' => $masalah === [] ? 'SEHAT' : 'BERMASALAH',
                'Masalah_Json' => $masalah === [] ? null : json_encode($masalah, JSON_UNESCAPED_UNICODE),
                'Diverifikasi_At' => FormatTanggalHelper::getCurrentTime(),
                'Diverifikasi_By' => $olehUserId,
            ]);

        return ['status' => $masalah === [] ? 'SEHAT' : 'BERMASALAH', 'masalah' => $masalah];
    }
```

- [ ] **Step 3: Buang panggilan katalog dari `matriks()`**

Hapus baris `$katalogPaket = collect($this->katalog->daftarPaket()...)` beserta seluruh pemakaiannya, dan hapus pemanggilan `$this->periksa(...)` di dalam perakitan `$sel`. Gantikan bagian sel terisi dengan:

```php
                    $sel[$k['kode']] = $dipakai === null
                        ? ['terisi' => false, 'ikut_menilai' => $ikutMenilai]
                        : [
                            'terisi' => true,
                            'ikut_menilai' => $ikutMenilai,
                            'id' => (int) $dipakai->Id_PA_Question_Binding_Master,
                            'id_master_ujian' => $dipakai->Id_Master_Ujian,
                            'nama_ujian' => $dipakai->Nama_Ujian_Sumber,
                            'nama_paket' => $dipakai->Nama_Paket_Ujian_Sumber,
                            'khusus' => $khusus !== null,
                            'jumlah_bagian' => (int) $dipakai->Jumlah_Bagian,

                            /* Status TERSIMPAN, bukan hasil pemeriksaan sekarang. Cap
                               waktunya ikut dikirim supaya layar bisa menuliskan "diperiksa
                               3 hari lalu" — tanpa itu status lama terbaca seperti status
                               realtime. */
                            'status_kesehatan' => $dipakai->Status_Kesehatan,
                            'masalah' => json_decode((string) $dipakai->Masalah_Json, true) ?: [],
                            'diperiksa_pada' => $dipakai->Diverifikasi_At,
                            'sahih' => $dipakai->Status_Kesehatan === 'SEHAT',
                        ];
```

Kunci `'simulasi'` pada nilai kembalian `matriks()` juga tidak boleh lagi berasal dari `daftarPaket()`. Gantikan dengan `$this->katalog->sedangDisimulasikan()` — metode itu hanya membaca konfigurasi dan tidak menyentuh jaringan.

- [ ] **Step 4: Tulis ulang `verifikasiUlang()` menjadi pembungkus `periksaSel()`**

```php
    /**
     * Memeriksa ulang SELURUH sel dan menyimpan hasilnya masing-masing.
     *
     * Dipakai tombol "Periksa Semua". Sengaja tidak dijalankan otomatis: pemeriksaan
     * memanggil HC Learn, dan menjalankannya tanpa diminta membuat halaman ini bergantung
     * pada layanan luar yang bisa mati.
     *
     * @return array{diperiksa: int, sahih: int, bermasalah: list<array<string, mixed>>}
     */
    public function verifikasiUlang(?int $olehUserId = null): array
    {
        $id = DB::table('N_PA_Question_Binding_Master')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master');

        $sahih = 0;
        $bermasalah = [];

        foreach ($id as $satu) {
            $hasil = $this->periksaSel((int) $satu, $olehUserId);

            if ($hasil['status'] === 'SEHAT') {
                $sahih++;

                continue;
            }

            $bermasalah[] = ['id' => (int) $satu, 'masalah' => $hasil['masalah']];
        }

        return ['diperiksa' => count($id), 'sahih' => $sahih, 'bermasalah' => $bermasalah];
    }
```

Katalog di-memo (`daftarPaket()` menyimpan `memoKatalog`), jadi perulangan ini memanggil HC Learn sekali saja, bukan sekali per sel.

- [ ] **Step 5: Hapus `periksa()` yang lama**

Metode privat `periksa()` pada baris 681–750 dihapus seluruhnya. Perannya dibagi dua: aturan pemetaan ke `PaBijeksiLabel`, dan penulisan status ke `periksaSel()`.

- [ ] **Step 6: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalKesehatanTest.php
git commit -m "feat(pa): kesehatan ikatan disimpan di dua gerbang, matriks tidak lagi memanggil HC Learn"
```

---

### Task 7: `salinKeSiklus()` memeriksa segar dan menyalin detail

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php` — `salinKeSiklus()` (mulai baris 408)
- Test: `tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalSalinTest.php`

**Interfaces:**
- Consumes: `periksaSel()`, `matriks()`.
- Produces: `salinKeSiklus()` menolak seluruh penyalinan bila ada sel bermasalah, dan mengisi `N_PA_Cycle_Question_Package_Item` dari detail masternya.

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Gerbang penyalinan ke siklus.
 *
 * Sifat yang dijaga: gerbang ini memeriksa SEGAR, tidak memercayai Status_Kesehatan yang
 * tersimpan. Status tersimpan hanya untuk ditampilkan; kalau gerbang ikut memercayainya,
 * status yang sudah basi berminggu-minggu akan meloloskan ujian rusak ke siklus baru —
 * persis kerusakan yang mau dicegah.
 *
 * Cara membuatnya MERAH: ganti pemanggilan periksaSel() dengan pembacaan kolom
 * Status_Kesehatan di salinKeSiklus().
 */
class PaIkatanPaketSoalSalinTest extends TestCase
{
    public function test_status_tersimpan_yang_berbohong_tidak_meloloskan_penyalinan(): void
    {
        config(['services.pa_hclearn_simulasi.aktif' => true]);

        $service = app(PaQuestionBindingMasterService::class);
        $levelId = (int) DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')->orderBy('ID_Level')->value('ID_Level');
        $wajib = $service->kompetensiWajibLevel($levelId);

        $ujian = collect(app(PaHcLearnCatalogService::class)->daftarPaket()['paket'])
            ->firstWhere('jumlah_bagian', count($wajib));
        $this->assertNotNull($ujian);

        $label = [];
        foreach ($wajib as $i => $kompetensiId) {
            $label[PaBagianKatalog::kunci($ujian['Bagian'][$i])] = $kompetensiId;
        }

        $hasil = $service->simpan($levelId, null, $ujian['Id_Master_Ujian'], $label);

        // Rusakkan isinya, TETAPI biarkan Status_Kesehatan tetap SEHAT — inilah keadaan yang
        // persis terjadi ketika HC Learn berubah setelah pemeriksaan terakhir.
        DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $hasil['id'])
            ->limit(1)
            ->delete();

        $this->assertSame('SEHAT', DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $hasil['id'])
            ->value('Status_Kesehatan'));

        $cycleId = (int) DB::table('N_PA_Cycle')->orderByDesc('Id_PA_Cycle')->value('Id_PA_Cycle');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/tidak sehat|bermasalah/i');

        $service->salinKeSiklus($cycleId);
    }
}
```

- [ ] **Step 2: Tambahkan gerbang pemeriksaan segar di awal `salinKeSiklus()`**

Sisipkan tepat setelah `$this->gate->pastikanBoleh(...)`:

```php
        /* Diperiksa SEGAR, bukan dari Status_Kesehatan yang tersimpan.
           Status tersimpan ditulis saat sel terakhir disimpan atau diperiksa manual, dan
           bisa berumur berminggu-minggu. HC Learn boleh berubah kapan saja di antaranya;
           memercayai status lama di gerbang ini berarti meloloskan ujian rusak ke siklus
           baru — kerusakan yang justru mau dicegah pemeriksaan ini. */
        $bermasalah = [];

        foreach (DB::table('N_PA_Question_Binding_Master')->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master') as $bindingId) {
            if ($this->periksaSel((int) $bindingId, $olehUserId)['status'] !== 'SEHAT') {
                $bermasalah[] = (int) $bindingId;
            }
        }

        if ($bermasalah !== []) {
            throw new RuntimeException(sprintf(
                '%d sel ikatan paket soal tidak sehat dan penyalinan dibatalkan seluruhnya. '
                .'Perbaiki dulu di halaman ikatan paket soal, lalu salin ulang.',
                count($bermasalah),
            ));
        }
```

Seluruh penyalinan dibatalkan, bukan sebagian: siklus yang separuh terisi lebih sulit didiagnosis daripada siklus yang jelas-jelas belum disalin.

- [ ] **Step 3: Salin detailnya, bukan hanya headernya**

**Baca `salinKeSiklus()` seluruhnya lebih dulu.** Metode itu sudah berbasis himpunan dan punya variabel kerjanya sendiri — `$sekarang`, peta sel, dan sisipan borongan headernya. Potongan di bawah memakai dua nama yang harus Anda sesuaikan ke variabel yang benar-benar ada di sana. Bila peta header belum ada, bacalah kembali dalam **satu** kueri sesudah sisipan borongan headernya:

```php
        $petaHeaderBaru = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Cycle_Question_Package', 'ID_Level', 'PA_Rater_Category_Id'])
            ->mapWithKeys(fn ($h) => [
                $h->ID_Level.'|'.($h->PA_Rater_Category_Id ?? ''), (int) $h->Id_PA_Cycle_Question_Package,
            ])
            ->all();
```

`$detailMaster` dibaca SEKALI di luar perulangan, dan dikelompokkan memakai **kunci yang sama** dengan `$petaHeaderBaru` — bukan menurut id ikatannya, supaya keduanya bisa dipasangkan langsung:

```php
        $detailMaster = DB::table('N_PA_Question_Binding_Master_Detail as d')
            ->join('N_PA_Question_Binding_Master as m', 'm.Id_PA_Question_Binding_Master', '=', 'd.PA_Question_Binding_Master_Id')
            ->where('d.Flag_Aktif', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->get([
                'm.ID_Level', 'm.PA_Rater_Category_Id',
                'd.Id_Jenis_Indikator', 'd.Id_Jenis_Soal', 'd.Nama_Alat_Tes',
                'd.Nama_Bagian_Sumber', 'd.Urutan_Alat_Tes', 'd.Urutan_Bagian', 'd.PA_Competency_Id',
            ])
            ->groupBy(fn ($d) => $d->ID_Level.'|'.($d->PA_Rater_Category_Id ?? ''))
            ->all();
```

Sel baku (`PA_Rater_Category_Id` NULL) tidak otomatis mengisi sel per-kategori: `salinKeSiklus()` yang ada sudah menentukan sel mana memakai ikatan mana lewat `matriks()`. Ikuti keputusan yang sudah dibuatnya — jangan membuat aturan pemilihan kedua di sini, karena dua penentu untuk hal yang sama adalah cara keduanya kelak berselisih.

Lalu sisipan borongan detailnya:

```php
            /* Detail disalin BERBASIS HIMPUNAN, satu sisipan borongan untuk seluruh siklus.
               Versi pertama salinKeSiklus() memanggil impor() sekali per sel dan menjadi
               ~230 kueri; jangan kembali ke pola itu lewat pintu detail. */
            $barisItem = [];

            foreach ($petaHeaderBaru as $kunci => $headerId) {
                foreach ($detailMaster[$kunci] ?? [] as $d) {
                    $barisItem[] = [
                        'PA_Cycle_Question_Package_Id' => $headerId,
                        'Id_Jenis_Indikator' => $d->Id_Jenis_Indikator,
                        'Id_Jenis_Soal' => $d->Id_Jenis_Soal,
                        'Nama_Alat_Tes' => $d->Nama_Alat_Tes,
                        'Nama_Bagian_Sumber' => $d->Nama_Bagian_Sumber,
                        'PA_Competency_Id' => (int) $d->PA_Competency_Id,
                        'Urutan_Tampil' => (int) (($d->Urutan_Alat_Tes ?? 0) * 100 + ($d->Urutan_Bagian ?? 0)),
                        'Flag_Aktif' => 'Y',
                        'Snapshot_At' => $sekarang,
                        'Snapshot_By' => $olehUserId,
                    ];
                }
            }

            if ($barisItem !== []) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert($barisItem);
            }
```

`Bobot_Penilaian`, `Skor_Maksimal`, `Jenis_Soal`, dan `PA_Result_Note_Type_Id` **sengaja dibiarkan NULL**. Katalog HC Learn tidak membawanya di tingkat bagian — bobot dan skor maksimal baru muncul di `tabs[].domain[]` pada endpoint hasil, yaitu setelah peserta mengerjakan. Mengisinya dengan tebakan di sini berarti menyimpan angka yang tampak resmi padahal tidak pernah datang dari mana pun.

`Urutan_Tampil` bertipe `smallint NOT NULL`; rumus `alatTes × 100 + bagian` aman karena PA 360 memakai paling banyak dua alat tes dengan 21 bagian, jadi nilai tertingginya 121.

- [ ] **Step 4: Commit**

```bash
git add app/Services/KPI/PerformanceAppraisal/PaQuestionBindingMasterService.php tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalSalinTest.php
git commit -m "feat(pa): salin ke siklus memeriksa segar dan membawa detail bagiannya"
```

---

### Task 8: Controller dan rute

**Files:**
- Modify: `app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php` — baris 255–307
- Modify: `routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php` — baris 53–57
- Test: `tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalRuteTest.php`

**Interfaces:**
- Consumes: `simpan()`, `periksaSel()`, `verifikasiUlang()`, `matriks()`, `kompetensiWajibLevel()`.
- Produces: rute `GET /master/ikatan-paket-soal/bagian`, `POST /master/ikatan-paket-soal`, `POST /master/ikatan-paket-soal/{id}/periksa`, `GET /master/ikatan-paket-soal/{id}/label`.

- [ ] **Step 1: Tulis uji rute yang gagal**

Jalankan `php artisan route:list --path=ikatan-paket-soal` dan **salin URI-nya persis** ke dalam penyedia data di bawah. Prefiks grupnya ditentukan berkas rute, bukan ditebak.

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rute ikatan paket soal WAJIB berada di balik izin halamannya.
 *
 * Menegaskan 401 saja tidak cukup — middleware `auth` sendirian sudah menghasilkan 401,
 * jadi uji semacam itu tetap hijau meski penjaga izinnya hilang. Yang ditegaskan di sini
 * adalah keberadaan middleware izinnya.
 *
 * Cara membuatnya MERAH: hapus check.jabatan dari grup rute master PA.
 */
class PaIkatanPaketSoalRuteTest extends TestCase
{
    public static function ruteIkatan(): array
    {
        return [
            ['GET',  'api/performance-appraisal/master/ikatan-paket-soal/matriks'],
            ['GET',  'api/performance-appraisal/master/ikatan-paket-soal/bagian'],
            ['POST', 'api/performance-appraisal/master/ikatan-paket-soal'],
            ['POST', 'api/performance-appraisal/master/ikatan-paket-soal/verifikasi'],
        ];
    }

    /** @dataProvider ruteIkatan */
    public function test_rute_dijaga_izin_halaman(string $metode, string $uri): void
    {
        $cocok = null;

        foreach (Route::getRoutes()->getRoutes() as $r) {
            if (in_array($metode, $r->methods(), true) && $r->uri() === $uri) {
                $cocok = $r;
                break;
            }
        }

        $this->assertNotNull($cocok, "Rute {$metode} {$uri} tidak terdaftar.");

        $middleware = $cocok->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertTrue(
            (bool) count(array_filter($middleware, fn ($m) => str_starts_with((string) $m, 'check.jabatan:'))),
            "Rute {$metode} {$uri} tidak memakai check.jabatan.",
        );
    }
}
```

- [ ] **Step 2: Ganti `simpanIkatanBaku()`**

```php
    public function simpanIkatanBaku(Request $request)
    {
        $data = $request->validate([
            'id_level' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'label' => ['required', 'array', 'min:1'],
            'label.*' => ['required', 'integer'],
        ]);

        return $this->jalankanTulis(fn () => $this->ikatanBaku->simpan(
            (int) $data['id_level'],
            isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
            $data['id_master_ujian'],
            array_map('intval', $data['label']),
            $this->userId(),
        ));
    }
```

- [ ] **Step 3: Tambahkan `bagianUjian()`, `periksaSelIkatan()`, dan `labelIkatan()`**

```php
    /**
     * Bagian satu ujian beserta kompetensi wajib level yang dituju.
     *
     * Keduanya dikirim BERSAMA, dari satu permintaan. Layar butuh keduanya untuk bisa
     * menolak lebih awal ketika cacahnya tidak sepadan — dan dua permintaan terpisah membuka
     * jendela di mana layar sudah menggambar 21 baris pelabelan yang ternyata mustahil diisi.
     */
    public function bagianUjian(Request $request)
    {
        $data = $request->validate([
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'id_level' => ['required', 'integer'],
        ]);

        return $this->jalankan(function () use ($data) {
            $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
                ->firstWhere('Id_Master_Ujian', $data['id_master_ujian']);

            if ($ujian === null) {
                throw new RuntimeException("Ujian '{$data['id_master_ujian']}' tidak ada di katalog HC Learn.");
            }

            $wajibId = $this->ikatanBaku->kompetensiWajibLevel((int) $data['id_level']);
            $bagian = $ujian['Bagian'] ?? [];

            /* Kekembaran dilaporkan ke layar, bukan hanya ke gerbang simpan. Admin harus tahu
               sebelum melabeli, bukan sesudah mengisi 21 baris. */
            $kembar = PaBagianKatalog::kembar($bagian);

            return [
                'id_master_ujian' => $ujian['Id_Master_Ujian'],
                'nama_ujian' => $ujian['Nama_Ujian'],
                'nama_paket' => $ujian['Nama_Paket'],
                'bagian' => $bagian,
                'kompetensi_wajib' => DB::table('N_PA_Competency as c')
                    ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
                    ->whereIn('c.Id_PA_Competency', $wajibId)
                    ->orderBy('t.Kode_Type')
                    ->orderBy('c.Kode_Competency')
                    ->get([
                        'c.Id_PA_Competency as id',
                        'c.Kode_Competency as kode',
                        'c.Nama_Competency as nama',
                        't.Kode_Type as dimensi_kode',
                        't.Nama_Type as dimensi_nama',
                    ]),
                'kembar' => $kembar,
                'sepadan' => $kembar === [] && count($bagian) === count($wajibId),
            ];
        });
    }

    /** Pemeriksaan manual satu sel — tombol "Periksa Sekarang". */
    public function periksaSelIkatan(Request $request, int $id)
    {
        return $this->jalankanTulis(fn () => $this->ikatanBaku->periksaSel($id, $this->userId()));
    }

    /** Label satu sel, untuk disalin ke sel lain yang memakai ujian dan level yang sama. */
    public function labelIkatan(Request $request, int $id)
    {
        return $this->jalankan(fn () => DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', $id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Alat_Tes')
            ->orderBy('Urutan_Bagian')
            ->get([
                'Id_Jenis_Indikator as id_jenis_indikator',
                'Id_Jenis_Soal as id_jenis_soal',
                'Nama_Alat_Tes as nama_alat_tes',
                'Nama_Bagian_Sumber as nama_bagian',
                'PA_Competency_Id as kompetensi_id',
            ]));
    }
```

Tambahkan `use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;` di kepala berkas.

- [ ] **Step 4: Perbaiki komentar `matriksIkatanBaku()` yang kini berbohong**

Komentar pada baris 261–266 menyatakan pemeriksaan dijalankan setiap kali dibaca. Sejak Task 6 itu tidak benar lagi. Ganti seluruhnya:

```php
    /**
     * Matriks baku beserta status kesehatan TERSIMPAN tiap selnya.
     *
     * Tidak memanggil HC Learn. Status yang dikirim di sini ditulis saat sel terakhir
     * disimpan, disalin ke siklus, atau diperiksa manual — dan cap waktunya ikut dikirim
     * supaya layar bisa menuliskan kapan pemeriksaannya terjadi. Halaman yang memanggil
     * layanan luar tiap dibuka ikut mati ketika layanan itu mati.
     */
```

- [ ] **Step 5: Daftarkan rutenya**

Di `routes/.../PerformanceAppraisalMaster.php`, di dalam grup yang sama dengan rute ikatan yang sudah ada:

```php
    Route::get('/ikatan-paket-soal/bagian', [PaMasterDataController::class, 'bagianUjian']);
    Route::get('/ikatan-paket-soal/{id}/label', [PaMasterDataController::class, 'labelIkatan']);
    Route::post('/ikatan-paket-soal/{id}/periksa', [PaMasterDataController::class, 'periksaSelIkatan']);
```

`/ikatan-paket-soal/bagian` didaftarkan **sebelum** `/ikatan-paket-soal/{id}/...` supaya `bagian` tidak tertangkap sebagai `{id}`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/KPI/PerformanceAppraisal/MasterData/PaMasterDataController.php routes/KPI/PerformanceAppraisal/MasterData/PerformanceAppraisalMaster.php tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalRuteTest.php
git commit -m "feat(pa): endpoint bagian ujian, pelabelan, dan pemeriksaan satu sel"
```

---

### Task 9: Layar pelabelan

**Files:**
- Modify: `resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js` — sekitar baris 421–446
- Modify: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/ikatan-paket-soal.vue`
- Create: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/PaLabelBagianDialog.vue`
- Test: `resources/js/Pages/KPI/PerformanceAppraisal/MasterData/components/__tests__/PaLabelBagianDialog.test.js`

**Interfaces:**
- Consumes: endpoint dari Task 8.
- Produces: komponen `PaLabelBagianDialog` dengan props `{ idLevel, raterCategoryId, idMasterUjian, bagian, kompetensiWajib, labelAwal }` dan event `simpan(label)`; `label` berkunci `"{id_jenis_indikator}|{id_jenis_soal}"`.

- [ ] **Step 1: Tambahkan pemanggil API**

Bentuk argumen `panggil` untuk query param harus mengikuti yang sudah dipakai di berkas itu — baca satu contoh `get` berparameter yang sudah ada dan tiru persis, jangan menebak tanda tangannya.

```js
/**
 * Bagian satu ujian beserta kompetensi wajib level yang dituju — satu permintaan.
 *
 * Keduanya diminta bersama supaya layar bisa menolak ujian yang cacah bagiannya tidak
 * sepadan SEBELUM menggambar barisan pelabelan yang mustahil diisi.
 */
export function ambilBagianUjian(idMasterUjian, idLevel) {
    return panggil('get', '/master/ikatan-paket-soal/bagian', null, {
        params: { id_master_ujian: idMasterUjian, id_level: idLevel },
    })
}

export function ambilLabelIkatan(id) {
    return panggil('get', `/master/ikatan-paket-soal/${id}/label`)
}

export function periksaSelIkatan(id) {
    return panggil('post', `/master/ikatan-paket-soal/${id}/periksa`)
}

export function simpanIkatanBaku({ idLevel, raterCategoryId, idMasterUjian, label }) {
    return panggil('post', '/master/ikatan-paket-soal', {
        id_level: idLevel,
        rater_category_id: raterCategoryId ?? null,
        id_master_ujian: idMasterUjian,
        label,
    })
}
```

- [ ] **Step 2: Tulis uji komponen yang gagal**

```js
import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PaLabelBagianDialog from '../PaLabelBagianDialog.vue'

/**
 * Bijeksi dijaga SAMBIL diisi, bukan baru divonis saat menyimpan.
 *
 * Admin yang baru tahu labelnya rangkap setelah menekan simpan harus menelusuri 21 baris
 * untuk menemukan yang mana. Kompetensi yang sudah terpakai karena itu hilang dari pilihan
 * berikutnya.
 *
 * Cara membuatnya MERAH: kembalikan seluruh kompetensi ke setiap pilihan.
 */
const props = {
    idLevel: 5,
    raterCategoryId: null,
    idMasterUjian: 'SIM-X',
    bagian: [
        { id_jenis_indikator: '100', id_jenis_soal: '77', nama_alat_tes: 'TIU', nama: 'Verbal', urutan_alat_tes: 0, urutan_bagian: 0 },
        { id_jenis_indikator: '200', id_jenis_soal: '77', nama_alat_tes: 'TKD', nama: 'Verbal', urutan_alat_tes: 1, urutan_bagian: 0 },
    ],
    kompetensiWajib: [
        { id: 11, kode: 'K01', nama: 'Analytical Thinking', dimensi_kode: 'MANAJERIAL', dimensi_nama: 'Kompetensi Manajerial' },
        { id: 12, kode: 'K02', nama: 'Problem Solving', dimensi_kode: 'MANAJERIAL', dimensi_nama: 'Kompetensi Manajerial' },
    ],
    labelAwal: {},
}

describe('PaLabelBagianDialog', () => {
    it('membedakan dua bagian yang bank soalnya sama tetapi alat tesnya berbeda', async () => {
        // Inti kontrak HC Learn: id_jenis_soal adalah pengenal BANK soal, bukan instance.
        // Kalau kuncinya hanya itu, kedua baris ini saling menimpa.
        const w = mount(PaLabelBagianDialog, { props })

        await w.vm.setLabel('100|77', 11)
        await w.vm.setLabel('200|77', 12)

        expect(w.vm.label).toEqual({ '100|77': 11, '200|77': 12 })
    })

    it('mematikan tombol simpan selama masih ada bagian tanpa label', () => {
        const w = mount(PaLabelBagianDialog, { props })

        expect(w.get('[data-uji="simpan-label"]').attributes('disabled')).toBeDefined()
    })

    it('menyalakan tombol simpan ketika seluruh bagian berlabel', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        await w.vm.setLabel('100|77', 11)
        await w.vm.setLabel('200|77', 12)

        expect(w.get('[data-uji="simpan-label"]').attributes('disabled')).toBeUndefined()
    })

    it('membuang kompetensi yang sudah terpakai dari pilihan bagian lain', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        await w.vm.setLabel('100|77', 11)

        expect(w.vm.pilihanUntuk('200|77').map((k) => k.id)).toEqual([12])
        // Bagian yang MEMEGANG label itu tetap melihatnya, kalau tidak ia tak bisa
        // menampilkan pilihannya sendiri.
        expect(w.vm.pilihanUntuk('100|77').map((k) => k.id)).toEqual([11, 12])
    })

    it('memancarkan label berkunci pasangan alat tes dan bank soal', async () => {
        const w = mount(PaLabelBagianDialog, { props })

        await w.vm.setLabel('100|77', 11)
        await w.vm.setLabel('200|77', 12)
        await w.get('[data-uji="simpan-label"]').trigger('click')

        expect(w.emitted('simpan')[0][0]).toEqual({ '100|77': 11, '200|77': 12 })
    })
})
```

- [ ] **Step 3: Tulis komponennya**

Gunakan pola dan token yang sudah dipakai `ikatan-paket-soal.vue` — baca berkas itu lebih dulu dan ikuti kelas serta komponen kit yang sama. Yang wajib ada:

- kunci tiap baris adalah `` `${b.id_jenis_indikator}|${b.id_jenis_soal}` ``, **tidak pernah** posisi atau `id_jenis_soal` saja;
- bagian dikelompokkan menurut `nama_alat_tes`, dengan nama alat tes sebagai judul kelompok — satu paket bisa memuat dua alat tes yang sama-sama punya "Bagian 1", dan tanpa judul kelompok admin tidak tahu yang mana yang sedang dilabeli;
- di dalam kelompok, urut menurut `urutan_bagian`; antar kelompok menurut `urutan_alat_tes`;
- pemilih berbentuk pohon dua tingkat: `dimensi_nama` sebagai induk, kompetensi sebagai daunnya;
- `pilihanUntuk(kunci)` mengembalikan kompetensi yang belum terpakai **ditambah** yang sedang dipegang bagian itu sendiri;
- `setLabel(kunci, kompetensiId)`;
- tombol bertanda `data-uji="simpan-label"`, `:disabled` selama `Object.keys(label).length !== bagian.length`;
- penghitung "12 dari 21 bagian berlabel" supaya kemajuannya terlihat tanpa menggulir.

- [ ] **Step 4: Sambungkan ke halaman**

Di `ikatan-paket-soal.vue`:

1. setelah admin memilih ujian, panggil `ambilBagianUjian(idMasterUjian, idLevel)`;
2. bila `kembar` tidak kosong, tampilkan penolakan yang menyebut pasangan mana yang kembar dan minta HC Learn memisahkan bank soalnya — **jangan** membuka dialog pelabelan;
3. bila `sepadan === false` karena cacah, tampilkan penolakan yang menyebut kedua angkanya — juga tanpa membuka dialog;
4. bila lolos keduanya, buka `PaLabelBagianDialog`;
5. pada event `simpan`, panggil `simpanIkatanBaku({ ... })` lalu muat ulang matriks;
6. tiap sel terisi menampilkan `status_kesehatan` dan `diperiksa_pada` sebagai teks relatif ("diperiksa 3 hari lalu"), plus tombol "Periksa Sekarang" yang memanggil `periksaSelIkatan(id)`;
7. sel yang memakai `id_master_ujian` dan level yang sama dengan sel lain yang sudah terisi menawarkan "Salin label dari …", yang memanggil `ambilLabelIkatan(idSelSumber)` lalu mengisi dialognya sebagai `labelAwal` (bentuk `{ "100|77": 11, ... }`).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/KPI/PerformanceAppraisal/api/paApi.js resources/js/Pages/KPI/PerformanceAppraisal/MasterData/
git commit -m "feat(pa): layar pelabelan bagian ke kompetensi dengan penjagaan bijeksi"
```

---

### Task 10: Uji rantai penuh

**Files:**
- Test: `tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalRantaiPenuhTest.php`

**Interfaces:**
- Consumes: seluruh permukaan dari Task 1–9.

Uji per-lapis bisa hijau semua sementara rantainya putus di sambungan. Uji ini menempuh satu jalur utuh: ikat → periksa → salin ke siklus → butir siklus benar.

- [ ] **Step 1: Tulis ujinya**

```php
<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rantai penuh ikatan paket soal: ikat -> periksa -> salin ke siklus.
 *
 * Uji per-lapis bisa hijau seluruhnya sementara rantainya putus di sambungan — bentuk yang
 * dihasilkan satu lapis tidak sama dengan yang dibaca lapis berikutnya. Uji ini menempuh
 * jalurnya utuh dan memeriksa yang sampai di UJUNG, bukan yang keluar dari tiap lapis.
 *
 * Ujian yang dipakai SENGAJA yang dua alat tesnya, karena di sanalah identitas majemuk
 * benar-benar bekerja: bank soal yang sama muncul di dua alat tes berbeda.
 */
class PaIkatanPaketSoalRantaiPenuhTest extends TestCase
{
    public function test_ikat_periksa_lalu_salin_menghasilkan_butir_siklus_yang_benar(): void
    {
        config(['services.pa_hclearn_simulasi.aktif' => true]);

        $service = app(PaQuestionBindingMasterService::class);
        $levelId = (int) DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')->orderBy('ID_Level')->value('ID_Level');
        $wajib = $service->kompetensiWajibLevel($levelId);

        $ujian = collect(app(PaHcLearnCatalogService::class)->daftarPaket()['paket'])
            ->firstWhere('jumlah_bagian', count($wajib));
        $this->assertNotNull($ujian, 'Tidak ada ujian simulasi sepadan.');

        $label = [];
        foreach ($wajib as $i => $kompetensiId) {
            $label[PaBagianKatalog::kunci($ujian['Bagian'][$i])] = $kompetensiId;
        }

        $ikatan = $service->simpan($levelId, null, $ujian['Id_Master_Ujian'], $label);
        $this->assertSame('SEHAT', $service->periksaSel($ikatan['id'])['status']);

        $cycleId = (int) DB::table('N_PA_Cycle')->orderByDesc('Id_PA_Cycle')->value('Id_PA_Cycle');
        $service->salinKeSiklus($cycleId);

        $header = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        $this->assertNotNull($header, 'Paket siklus tidak terbentuk.');
        $this->assertSame($ujian['Id_Master_Ujian'], $header->Id_Master_Ujian);

        $butir = DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $header->Id_PA_Cycle_Question_Package)
            ->where('Flag_Aktif', 'Y')
            ->get();

        // Cacahnya benar, DAN pasangan identitas beserta kompetensinya sama persis dengan
        // yang dilabeli — bukan sekadar "ada isinya".
        $this->assertCount(count($wajib), $butir);
        $this->assertEqualsCanonicalizing(
            array_values($label),
            $butir->pluck('PA_Competency_Id')->map(fn ($x) => (int) $x)->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_keys($label),
            $butir->map(fn ($b) => $b->Id_Jenis_Indikator.'|'.$b->Id_Jenis_Soal)->all(),
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add tests/Feature/KPI/PerformanceAppraisal/PaIkatanPaketSoalRantaiPenuhTest.php
git commit -m "test(pa): rantai penuh ikatan paket soal dari pelabelan sampai butir siklus"
```

---

### Task 11: Sapu bersih rujukan bentuk lama

**Files:**
- Modify: berkas mana pun yang masih menyebut bentuk lama
- Modify: `app/Console/Commands/KPI/PerformanceAppraisal/PaSeedIkatanPaketCommand.php`
- Modify: `app/Console/Commands/KPI/PerformanceAppraisal/PaVerifyCommand.php`

Ganti nama kolom dan bentuk katalog menyentuh lebih banyak berkas daripada yang tersentuh Task 1–10. Yang tertinggal tidak gagal saat kompilasi — PHP dan SQL Server sama-sama baru mengeluh saat kuerinya jalan, dan gejalanya menyamar jadi "sel kosong".

- [ ] **Step 1: Cari seluruh yang tertinggal**

```bash
grep -rn "Kode_Paket_Sumber\|Kode_Soal\|Nama_Soal_Sumber\|Nama_Paket_Sumber\|Sidik_Susunan\|Jumlah_Soal\|Id_Master_Ujian'\|Nama_Ujian'\|N_PA_Competency_Kode_Soal\|pastikanDimensiCocok\|Kode_Kompetensi" app/ resources/js/ tests/ database/dml/ | grep -v "database/ddl/"
```

DDL lama sengaja dilewati — berkas DDL adalah catatan sejarah dan tidak boleh disunting mundur.

- [ ] **Step 2: Perbaiki tiap kemunculan**

Untuk masing-masing: kalau menunjuk kolom, ganti ke nama barunya; kalau menunjuk `Detail[]`/`Kode_Kompetensi`, ganti ke `Bagian[]` dan pemetaan dari `N_PA_Question_Binding_Master_Detail`; kalau menunjuk `N_PA_Competency_Kode_Soal`, hapus kodenya.

`PaSeedIkatanPaketCommand` harus menyemai label lengkap, bukan hanya header — perintah semai yang menghasilkan sel tanpa detail justru menciptakan keadaan yang seluruh rencana ini berusaha mustahilkan.

- [ ] **Step 3: Pastikan sapuannya bersih**

Jalankan ulang perintah Step 1. Keluarannya harus kosong (di luar `database/ddl/`).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "refactor(pa): sapu rujukan bentuk kode-soal yang tertinggal"
```

---

## Setelah seluruh tugas selesai

Berhenti dan laporkan. **Jangan menjalankan uji** sampai pemilik memberi ACC — lihat Global Constraints. Ketika ACC turun, jalankan dalam urutan ini, satu suite satu waktu:

```bash
php artisan test tests/Unit/KPI/PerformanceAppraisal/Katalog
php artisan test tests/Feature/KPI/PerformanceAppraisal
npx vitest run resources/js/Pages/KPI/PerformanceAppraisal/MasterData
```

Lalu buktikan MERAH-nya: untuk tiap uji, terapkan "cara membuatnya MERAH" yang tertulis di komentar kelasnya, lihat gagalnya, kembalikan. Uji yang belum pernah dilihat gagal bukan penjaga.

DDL-2026-090 dijalankan pemilik terhadap `Web_HRIS`, lalu entrinya di `status.json` diubah `development.status` menjadi `DONE`.
