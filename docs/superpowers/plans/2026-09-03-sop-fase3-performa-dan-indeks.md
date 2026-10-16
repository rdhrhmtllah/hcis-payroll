# SOP Fase 3 — Performa, Indeks, dan Pemangkas Cache: Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkah memakai sintaks checkbox (`- [ ]`) untuk penelusuran.

**Goal:** Menghapus N+1 tulis pada penugasan, kunci yang tak berguna pada jalur baca terpanas, kueri yang tumbuh bersama data, dan celah indeks — selagi tabelnya masih kosong dan perubahan itu instan.

**Architecture:** Enam perbaikan kode yang berdiri sendiri, satu change-id DDL berisi lima indeks, dan satu command pemangkas cache yang menjadi prasyarat bagi perbaikan cache. Tidak ada perubahan perilaku yang terlihat pengguna; seluruhnya soal biaya.

**Tech Stack:** Laravel 10, PHP 8, SQL Server (`sqlsrv`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-01-sop-performa-atomicity-design.md` (§6)

**Prasyarat:** Fase 1 dan Fase 2 selesai.

---

## Global Constraints

- **Jangan jalankan uji atau build tanpa persetujuan eksplisit pemilik project.**
- **Jangan pernah menjalankan `DROP`, `TRUNCATE`, atau migration apa pun.** `drop.sql` boleh **dibuat** sebagai alat bantu manual tetapi **tidak boleh dijalankan** agen. Ini termasuk `DROP INDEX`.
- **Jangan pernah `git add -A` atau `git add .`** — path eksplisit saja.
- **Target basis data:** `Web_HRIS` di `team311.dyndns.info:1433`, lingkungan **development**, diverifikasi dengan `SELECT DB_NAME()` sebelum eksekusi DDL apa pun.
- **DDL hanya boleh dieksekusi ke development.** Eksekusi ke produksi adalah pekerjaan pemilik project lewat SQL Tracker.
- **Ledger DDL sudah memuat 6 item `TRACKER_PENDING` dan 3 `PENDING`.** Laporkan ini sebelum menambah change-id baru; itu kewajiban pelaporan, bukan penghalang teknis.
- **Pesan commit berakhir dengan** `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- **Uji:** namespace `Tests\Feature\LMS\SOP`, trait `DatabaseTransactions`.

### Cara mengukur di fase ini

Data dev terlalu kecil untuk membuktikan apa pun lewat waktu (`N_SOP_Progress` 1.156
baris, `N_SOP_Penjadwalan_Detail` 317, `N_SOP_Kepatuhan_Riwayat` 108, `N_SOP_Dokumen` 53
pada 2026-09-01). **Angka wall-clock tidak sah sebagai bukti di sini.** Yang diukur:

1. **Jumlah pernyataan** lewat `DB::listen()`.
2. **Apakah jumlah itu tumbuh bersama data** — ukur pada dua ukuran populasi dan
   bandingkan.

Setiap penjaga jumlah-kueri **wajib disertai penegasan kerja-nyata**: operasi yang tak
menyentuh apa pun menghasilkan angka yang sempurna. Tanpa penegasan bahwa barisnya
benar-benar terbentuk, penjaga itu justru menghadiahi kode yang rusak.

---

## Peta berkas

| Berkas | Peran | Butir |
|---|---|---|
| `database/ddl/DDL-2026-063-sop-indeks-performa/forward.sql` | **Baru.** Lima indeks | P1-12 |
| `database/ddl/DDL-2026-063-sop-indeks-performa/drop.sql` | **Baru.** Alat bantu manual, tak dijalankan | P1-12 |
| `docs/changes/DDL-2026-063/README.md` | **Baru.** Dokumentasi perubahan | P1-12 |
| `app/Services/LMS/SOP/SopPenjadwalanService.php:230-310` | `assignKeHeader()` set-based | P1-5 |
| `app/Services/LMS/SOP/SopCompletionService.php:251-283` | `simpanHalaman()` conditional update | P1-6 |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php:481` | `ringkasanTahap()` jadi agregat | P1-9 |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php:236` | Urutan lewat JOIN | P1-10 |
| `app/Services/LMS/SOP/SopPaketService.php:274,279,282` | `whereDate` jadi rentang | P1-11 |
| `app/Console/Commands/SYSTEM/Application/Maintenance/CachePrune.php` | **Baru.** Pemangkas cache | P1-18 |
| `app/Services/LMS/SOP/Support/SaringanMonitoring.php:106` | Cache untuk hasil tersaring | P1-7 |
| `app/Services/LMS/SOP/Support/SopSetting.php` | Memoisasi per-request | P1-8 |

---

## Task 1: DDL-2026-063 — lima indeks

**Files:**
- Create: `database/ddl/DDL-2026-063-sop-indeks-performa/forward.sql`
- Create: `database/ddl/DDL-2026-063-sop-indeks-performa/drop.sql`
- Create: `docs/changes/DDL-2026-063/README.md`
- Modify: `database/ddl/status.json`

**Interfaces:**
- Consumes: —
- Produces: lima indeks yang diandalkan Task 3, 4, dan 5.

- [ ] **Step 1: Laporkan item DDL yang belum tuntas**

Sebelum menambah change-id, laporkan isi ledger ke pemilik project: 6 `TRACKER_PENDING`,
3 `PENDING`. Aturan agen mewajibkan pelaporan ini di awal tugas DDL.

- [ ] **Step 2: Verifikasi target basis data**

```bash
php artisan tinker --execute="echo DB::selectOne('SELECT DB_NAME() n')->n;"
```

Harus mengeluarkan `Web_HRIS`. Bila bukan, **berhenti** — jangan mengeksekusi DDL apa pun
ke target yang belum diklasifikasikan.

- [ ] **Step 3: Tulis `forward.sql`**

Setiap pernyataan digerbangi keberadaannya sendiri, sehingga tiap tahap bisa gagal
sendirian tanpa menjatuhkan sisanya — MERGE yang gagal senyap sudah pernah menjadi
masalah di modul ini.

```sql
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Waktu')
CREATE NONCLUSTERED INDEX IX_N_SOP_Rwt_Waktu
    ON N_SOP_Kepatuhan_Riwayat (Waktu_Peristiwa)
    INCLUDE (Peristiwa, Id_SOP_Paket, Kode_Karyawan, Id_SOP_Penjadwalan_Detail);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Rwt_Detail_Peristiwa')
CREATE NONCLUSTERED INDEX IX_N_SOP_Rwt_Detail_Peristiwa
    ON N_SOP_Kepatuhan_Riwayat (Id_SOP_Penjadwalan_Detail, Peristiwa);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_PjdD_Karyawan_Aktif')
CREATE NONCLUSTERED INDEX IX_N_SOP_PjdD_Karyawan_Aktif
    ON N_SOP_Penjadwalan_Detail (Kode_Karyawan, Flag_Aktif, Flag_Cabut_Akses)
    INCLUDE (Id_SOP_Penjadwalan, Status_Paket, Tanggal_Selesai, Created_At, Updated_At);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Prg_Detail_Status')
CREATE NONCLUSTERED INDEX IX_N_SOP_Prg_Detail_Status
    ON N_SOP_Progress (Id_SOP_Penjadwalan_Detail, Status)
    INCLUDE (Id_SOP_Paket_Item, Id_SOP_Item_Versi, Waktu_Buka, Waktu_Konfirmasi);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Paket_Publish')
CREATE NONCLUSTERED INDEX IX_N_SOP_Paket_Publish
    ON N_SOP_Master_Paket (Tanggal_Publish);
GO
```

- [ ] **Step 4: Tulis `drop.sql` — dan JANGAN menjalankannya**

Berisi lima `DROP INDEX` pasangannya, ditambah catatan bahwa
`IX_N_SOP_PjdD_Karyawan (Kode_Karyawan, Status_Paket)` **tergantikan** oleh
`IX_N_SOP_PjdD_Karyawan_Aktif` dan boleh dihapus manual oleh pemilik project setelah
pengamatan. Agen tidak menghapus indeks apa pun.

- [ ] **Step 5: Tulis dokumentasi perubahan**

`docs/changes/DDL-2026-063/README.md` memuat: change ID, basis data target, alasan tiap
indeks beserta kueri yang dilayaninya, jalur SQL, checksum `forward.sql`, status, apa
yang sudah dikerjakan agen, dan apa yang tersisa untuk pemilik project.

- [ ] **Step 6: Eksekusi ke development, lalu verifikasi**

Tampilkan SQL persisnya lebih dulu. Setelah eksekusi:

```bash
php artisan tinker --execute="
foreach (['IX_N_SOP_Rwt_Waktu','IX_N_SOP_Rwt_Detail_Peristiwa','IX_N_SOP_PjdD_Karyawan_Aktif','IX_N_SOP_Prg_Detail_Status','IX_N_SOP_Paket_Publish'] as \$n) {
    echo \$n.' => '.(DB::selectOne('SELECT COUNT(*) c FROM sys.indexes WHERE name = ?', [\$n])->c ? 'ADA' : 'TIDAK ADA').PHP_EOL;
}"
```

Kelimanya harus `ADA`.

- [ ] **Step 7: Perbarui ledger dan commit**

Status `DEV_EXECUTED`, lalu `TRACKER_PENDING`. Jangan sekali-kali menyetel
`PRODUCTION_EXECUTED` — status itu hanya boleh disetel setelah pemilik project
mengonfirmasi eksekusi dari SQL Tracker.

```bash
git add database/ddl/DDL-2026-063-sop-indeks-performa docs/changes/DDL-2026-063 database/ddl/status.json
git commit -m "feat(sop): DDL-2026-063 lima indeks performa

Dibuat sekarang selagi tabelnya masih kosong: menambah indeks pada tabel
berisi jutaan baris menuntut jendela pemeliharaan.

N_SOP_Kepatuhan_Riwayat sama sekali belum berindeks pada Waktu_Peristiwa
padahal tren() menyaring rentang tanggal di sana, dan itu tabel yang
tercepat tumbuh.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Pemangkas cache (P1-18) — prasyarat Task 8

Terverifikasi 2026-09-01: tabel `cache` berisi 121 baris, **96 (79%) sudah kedaluwarsa**
dan menetap. `Illuminate\Cache\DatabaseStore` hanya menghapus kunci ketika ditulis ulang
atau di-`forget`. Tabelnya tumbuh mengikuti **kardinalitas kunci**, bukan jumlah kunci
yang hidup.

**Files:**
- Create: `app/Console/Commands/SYSTEM/Application/Maintenance/CachePrune.php`
- Test: `tests/Feature/SYSTEM/CachePruneTest.php`

**Interfaces:**
- Produces: command `hris:cache-prune {--batch=5000}`.

- [ ] **Step 1: Tulis uji yang gagal**

```php
public function test_hanya_baris_kedaluwarsa_yang_dihapus(): void
{
    $lampau = now()->subHour()->timestamp;
    $depan = now()->addHour()->timestamp;

    DB::table('cache')->insert([
        ['key' => 'uji_prune_mati', 'value' => 'x', 'expiration' => $lampau],
        ['key' => 'uji_prune_hidup', 'value' => 'x', 'expiration' => $depan],
    ]);

    $this->artisan('hris:cache-prune')->assertExitCode(0);

    $this->assertDatabaseMissing('cache', ['key' => 'uji_prune_mati']);
    $this->assertDatabaseHas('cache', ['key' => 'uji_prune_hidup']);
}
```

- [ ] **Step 2: Jalankan uji untuk memastikan ia GAGAL**

Jalankan: `php artisan test --filter=test_hanya_baris_kedaluwarsa_yang_dihapus`
Diharapkan: FAIL — command belum ada.

- [ ] **Step 3: Tulis command-nya**

Salin bentuk `app/Console/Commands/SYSTEM/Application/Maintenance/ApiNoncePrune.php` —
pola batch delete yang sudah dipakai di project ini.

```php
protected $signature = 'hris:cache-prune {--batch=5000 : Baris per batch delete}';

public function handle(): int
{
    $batch = max(100, (int) $this->option('batch'));
    $total = 0;

    do {
        // TOP (n) memakai idx_cache_expiration sehingga ini seek, bukan scan.
        // Dipecah per batch karena `cache` tabel BERSAMA: satu DELETE besar
        // menahan lock yang ikut memblokir modul lain, dan basis data ini
        // berjalan tanpa RCSI.
        $dihapus = DB::delete(
            'DELETE TOP ('.$batch.') FROM cache WHERE expiration < ?',
            [now()->timestamp]
        );
        $total += $dihapus;
    } while ($dihapus > 0);

    $this->info("Cache dipangkas: {$total} baris kedaluwarsa dihapus.");

    return self::SUCCESS;
}
```

- [ ] **Step 4: Jalankan uji untuk memastikan ia LULUS**

Jalankan: `php artisan test --filter=test_hanya_baris_kedaluwarsa_yang_dihapus`
Diharapkan: PASS.

- [ ] **Step 5: Catat kebutuhan penjadwalan**

Command ini perlu didaftarkan di `N_HRIS_Scheduler_Configs` (pola sama dengan
`hris:api-nonce-prune`, tiap jam). Pendaftarannya adalah pekerjaan pemilik project —
sampaikan sebagai butir tindak lanjut, jangan menganggapnya selesai.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/SYSTEM/Application/Maintenance/CachePrune.php tests/Feature/SYSTEM/CachePruneTest.php
git commit -m "feat(system): command pemangkas baris cache kedaluwarsa

DatabaseStore tak pernah menghapus baris kedaluwarsa yang tak diminta lagi;
79% isi tabel cache sudah mati saat diukur.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `assignKeHeader()` jadi set-based (P1-5)

**Files:**
- Modify: `app/Services/LMS/SOP/SopPenjadwalanService.php:230-310`
- Test: `tests/Feature/LMS/SOP/SopAssignSetBasedTest.php`

**Interfaces:**
- Consumes: —
- Produces: `assignKeHeader()` — tanda tangan dan nilai balik `['assigned' => int, 'skipped' => int, 'targets' => array]` **tidak berubah**. `ProsesBulkAssignSop` dan `SopAssignmentRuleService` bergantung padanya.

- [ ] **Step 1: Tulis penjaga yang gagal**

```php
public function test_assign_tidak_menerbitkan_satu_pernyataan_per_karyawan(): void
{
    // ...siapkan penjadwalan + 3 item wajib + 50 kode karyawan dari data dev...
    // (bila fikstur tak tersedia: markTestSkipped, jangan memaksakan angka)

    $pernyataan = 0;
    DB::listen(function () use (&$pernyataan) { $pernyataan++; });

    $hasil = app(SopPenjadwalanService::class)
        ->assignKeHeader($idPenjadwalan, $idPaket, $kodeList, null);

    // PENJAGA KERJA-NYATA. Tanpa ini, operasi yang tak menyentuh apa pun
    // menghasilkan jumlah pernyataan yang SEMPURNA dan penjaganya justru
    // menghadiahi kode yang rusak.
    $this->assertSame(50, $hasil['assigned'], 'Tak semua karyawan ter-assign.');
    $this->assertSame(
        150,
        DB::table('N_SOP_Progress')->whereIn('Kode_Karyawan', $kodeList)->count(),
        'Baris progress tidak terbentuk untuk 50 karyawan x 3 item wajib.'
    );

    $this->assertLessThan(
        20,
        $pernyataan,
        "Assign 50 karyawan menerbitkan {$pernyataan} pernyataan. Bentuk per-karyawan "
        .'(insertGetId + insert progress) belum diganti bentuk himpunan.'
    );
}
```

- [ ] **Step 2: Jalankan penjaga untuk memastikan ia GAGAL**

Diharapkan: FAIL pada penegasan terakhir dengan angka sekitar 100-150.

- [ ] **Step 3: Commit penjaga dalam keadaan merah**

- [ ] **Step 4: Tulis ulang jadi bentuk himpunan**

Id detail **tidak perlu dikembalikan**: baris progress diturunkan dengan menjoin balik
lewat `(Id_SOP_Penjadwalan, Kode_Karyawan)`.

```php
// 1. bulk insert detail — diff "sudah ter-assign" di atasnya dipertahankan apa adanya
foreach (array_chunk($barisDetail, 200) as $potong) {
    DB::table('N_SOP_Penjadwalan_Detail')->insert($potong);
}

// 2. progress diturunkan lewat INSERT ... SELECT, bukan per orang
foreach (array_chunk($targets, 400) as $potong) {
    $ph = implode(',', array_fill(0, count($potong), '?'));
    DB::insert(
        "INSERT INTO N_SOP_Progress
            (Id_SOP_Penjadwalan_Detail, Id_SOP_Paket_Item, Id_SOP_Item_Versi,
             Kode_Karyawan, Tipe_Item, Status, Durasi_Aktif_Detik, Jumlah_Percobaan, Created_At)
         SELECT d.Id_SOP_Penjadwalan_Detail, i.Id_SOP_Paket_Item, i.Id_Item_Versi_Aktif,
                d.Kode_Karyawan, i.Tipe_Item, ?, 0, 0, GETDATE()
           FROM N_SOP_Penjadwalan_Detail d
           JOIN N_SOP_Paket_Item i
             ON i.Id_SOP_Paket = ? AND i.Flag_Wajib = 'Y' AND i.Flag_Aktif = 'Y'
          WHERE d.Id_SOP_Penjadwalan = ?
            AND d.Kode_Karyawan IN ({$ph})
            AND NOT EXISTS (
                SELECT 1 FROM N_SOP_Progress p
                 WHERE p.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                   AND p.Id_SOP_Paket_Item = i.Id_SOP_Paket_Item
                   AND ISNULL(p.Id_SOP_Item_Versi, -1) = ISNULL(i.Id_Item_Versi_Aktif, -1))",
        array_merge([SopStatus::ITEM_BELUM, $idPaket, $penjadwalanId], $potong)
    );
}
```

`NOT EXISTS` menjaga idempotensi yang menjadi syarat retry Cloud Tasks — bagian ini
**tidak boleh** dihilangkan demi kesederhanaan. Potongan 400 kode menjaga jumlah
parameter jauh di bawah plafon 2100 SQL Server.

`bumpRollup` per item ber-versi tetap seperti sekarang: jumlahnya mengikuti item, bukan
karyawan.

- [ ] **Step 5: Jalankan penjaga dan uji penjadwalan yang sudah ada**

Jalankan: `php artisan test --filter=SopAssignSetBasedTest`, lalu
`php artisan test --filter=Penjadwalan`
Diharapkan: penjaga PASS; uji penjadwalan identik dengan baseline.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LMS/SOP/SopPenjadwalanService.php tests/Feature/LMS/SOP/SopAssignSetBasedTest.php
git commit -m "perf(sop): assignKeHeader jadi set-based

Dari ~200 pernyataan per 100 karyawan menjadi ~4. Bentuk yang sama dengan
refactor cabut massal (122 -> 8 kueri). NOT EXISTS menjaga idempotensi
yang jadi syarat retry Cloud Tasks.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `simpanHalaman()` — buang kunci, pakai conditional update (P1-6)

Jalur terpanas di seluruh modul: dipanggil tiap pindah halaman **dan** tiap 60 detik per
pembaca (`SopPdfReader.vue:861`). Sekarang menjalankan sekitar enam kueri, dengan
`UPDLOCK/HOLDLOCK` yang **tidak melindungi apa pun** karena dipasang di luar transaksi.

**Files:**
- Modify: `app/Services/LMS/SOP/SopCompletionService.php:251-283`
- Test: `tests/Feature/LMS/SOP/SopSimpanHalamanTest.php`

**Interfaces:**
- Consumes: —
- Produces: `simpanHalaman(string $kode, int $idItem, int $halaman, int $durasiDetik = 0): string` — tanda tangan dan nilai balik `'OK'`/kode galat **tidak berubah**.

- [ ] **Step 1: Tulis penjaga yang gagal**

```php
public function test_simpan_halaman_hemat_kueri_dan_mengakumulasi_durasi(): void
{
    // ...ambil progress yang sudah ada dari data dev; markTestSkipped bila nihil...

    $pernyataan = 0;
    DB::listen(function () use (&$pernyataan) { $pernyataan++; });

    $svc = app(SopCompletionService::class);
    $this->assertSame('OK', $svc->simpanHalaman($kode, $idItem, 5, 30));

    $this->assertLessThanOrEqual(
        3,
        $pernyataan,
        "simpanHalaman menerbitkan {$pernyataan} kueri pada jalur baris-sudah-ada. "
        .'Baca-ubah-tulis belum diganti conditional update.'
    );

    $svc->simpanHalaman($kode, $idItem, 3, 20);

    $baris = DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $idProgress)->first();
    $this->assertSame(5, (int) $baris->Halaman_Terakhir, 'Halaman mundur — harus MAX, bukan timpa.');
    $this->assertSame($durasiAwal + 50, (int) $baris->Durasi_Aktif_Detik, 'Durasi tidak terakumulasi.');
}
```

- [ ] **Step 2: Jalankan penjaga untuk memastikan ia GAGAL**

- [ ] **Step 3: Commit penjaga dalam keadaan merah**

- [ ] **Step 4: Ganti dengan conditional update**

```php
$tambah = max(0, min($durasiDetik, 600));

$terpengaruh = DB::update(
    'UPDATE N_SOP_Progress
        SET Halaman_Terakhir   = CASE WHEN ISNULL(Halaman_Terakhir,0) < ?
                                      THEN ? ELSE Halaman_Terakhir END,
            Durasi_Aktif_Detik = ISNULL(Durasi_Aktif_Detik,0) + ?,
            Waktu_Buka         = ISNULL(Waktu_Buka, SYSDATETIME()),
            Updated_At         = SYSDATETIME()
      WHERE Id_SOP_Penjadwalan_Detail = ?
        AND Id_SOP_Paket_Item        = ?
        AND '.($versiId === null ? 'Id_SOP_Item_Versi IS NULL' : 'Id_SOP_Item_Versi = ?'),
    $versiId === null
        ? [$halaman, $halaman, $tambah, $detailId, $idItem]
        : [$halaman, $halaman, $tambah, $detailId, $idItem, $versiId]
);

if ($terpengaruh === 0) {
    // Baris belum ada — baru di sinilah jalur pembuatan ditempuh.
    $this->ensureProgress($ctx, $kode);
    // ...ulangi UPDATE di atas sekali...
}

return 'OK';
```

`bacaProgressTerkunci()` **tetap ada dan tetap berkunci** untuk `buka()` dan
`konfirmasi()`: keduanya berada di dalam transaksi dan memang butuh serialisasi. Yang
dihapus hanya pemakaiannya di jalur `simpanHalaman()`, tempat kuncinya tak pernah bisa
bekerja.

- [ ] **Step 5: Jalankan penjaga dan uji progres yang sudah ada**

Jalankan: `php artisan test --filter=SopSimpanHalamanTest`, lalu
`php artisan test --filter=SopProgress`

- [ ] **Step 6: Commit**

```bash
git add app/Services/LMS/SOP/SopCompletionService.php tests/Feature/LMS/SOP/SopSimpanHalamanTest.php
git commit -m "perf(sop): simpanHalaman pakai conditional update, buang kunci sia-sia

lockForUpdate() di luar transaksi dilepas per pernyataan di autocommit,
jadi ia tak pernah melindungi apa pun — tetapi tetap memasang lock pada
tabel terpanas, tiap pindah halaman tiap pembaca.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: `ringkasanTahap()` jadi agregat, dan urutan lewat JOIN (P1-9, P1-10)

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php:481` (`ringkasanTahap`) dan `:236` (orderBy berkorelasi)
- Test: `tests/Feature/LMS/SOP/SopRingkasanDokumenTest.php`

**Interfaces:**
- Consumes: indeks dari Task 1.
- Produces: `ringkasanTahap(?string $tipe): array` — bentuk nilai balik **tidak berubah** (kunci `total`, `BARU`, `DRAFT`, `SIAP`, `DIPAKAI`, `PERHATIAN`, `BELUM_LENGKAP`, `NONAKTIF`).

- [ ] **Step 1: Tulis penjaga yang gagal — ukur pertumbuhan, bukan angka mutlak**

Menyemai 2100 dokumen mahal dan rapuh. Yang diuji: **jumlah kueri tidak tumbuh bersama
jumlah dokumen**.

`ringkasanTahap()` bersifat **private** dan dipanggil dari `getDataCurrent()`
(`SopAdminDokumenController.php:446`). Jadi ujinya menembak endpoint-nya, bukan
metodenya: `GET api/v1/sop/admin/dokumen/current`, digerbangi
`check.jabatan:menuSopDokumenPage` (terverifikasi via `php artisan route:list`).

```php
private function adminDokumen(): ?User
{
    return User::whereNotNull('Id_Users')->get()
        ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
}

public function test_ringkasan_tidak_tumbuh_bersama_jumlah_dokumen(): void
{
    $admin = $this->adminDokumen();
    if (! $admin) {
        $this->markTestSkipped('Tak ada user dgn akses menuSopDokumenPage di data dev.');
    }

    $hitung = function () use ($admin): int {
        $n = 0;
        DB::listen(function () use (&$n) { $n++; });
        $res = $this->actingAs($admin)->getJson('/api/v1/sop/admin/dokumen/current');
        // Penjaga kerja-nyata: permintaan yang ditolak 403 menghasilkan jumlah
        // kueri yang stabil dan penjaganya lulus tanpa menguji apa pun.
        $res->assertStatus(200);

        return $n;
    };

    $sebelum = $hitung();

    // 25 dokumen tambahan; di-rollback DatabaseTransactions di akhir uji.
    for ($i = 0; $i < 25; $i++) {
        DB::table('N_SOP_Dokumen')->insert([
            'Judul' => "Uji Ringkasan {$i}", 'Tipe' => 'DOKUMEN', 'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);
    }

    $sesudah = $hitung();

    $this->assertSame(
        $sebelum,
        $sesudah,
        "Jumlah kueri naik dari {$sebelum} ke {$sesudah} setelah 25 dokumen ditambahkan. "
        .'Ringkasan masih memuat seluruh dokumen lalu whereIn semua id — pada 2100 '
        .'dokumen itu menembus plafon parameter SQL Server dan gagal keras.'
    );
}
```

- [ ] **Step 2: Jalankan penjaga untuk memastikan ia GAGAL**

- [ ] **Step 3: Commit penjaga dalam keadaan merah**

- [ ] **Step 4: Ganti muat-semua dengan agregat**

Gantikan pola "muat semua dokumen → `whereIn` semua id tiga kali → hitung pil di PHP"
dengan `GROUP BY` di basis data. Satu kueri agregat per pil, atau satu kueri dengan
`SUM(CASE WHEN ...)` — pilih yang lebih terbaca, keduanya menghapus baik beban memori
maupun bom parameter.

- [ ] **Step 5: Ganti urutan berkorelasi dengan JOIN (baris 236)**

```sql
-- SEBELUM: subkueri berkorelasi, satu seek per baris
ORDER BY (SELECT MIN(t.x) FROM N_SOP_Item_Versi vp
          CROSS APPLY (VALUES (vp.Tanggal_Review_Berikutnya), (vp.Tanggal_Kadaluarsa)) AS t(x)
          WHERE vp.Id_SOP_Item_Versi = d.Id_Versi_Aktif)

-- SESUDAH: satu join untuk seluruh halaman, VALUES dihitung atas kolom yang sudah ter-join
LEFT JOIN N_SOP_Item_Versi va ON va.Id_SOP_Item_Versi = d.Id_Versi_Aktif
...
ORDER BY (SELECT MIN(t.x) FROM (VALUES (va.Tanggal_Review_Berikutnya), (va.Tanggal_Kadaluarsa)) AS t(x))
```

- [ ] **Step 6: Jalankan penjaga dan uji dokumen yang sudah ada**

Jalankan: `php artisan test --filter=SopRingkasanDokumenTest`, lalu
`php artisan test --filter=SopDokumen`

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php tests/Feature/LMS/SOP/SopRingkasanDokumenTest.php
git commit -m "perf(sop): ringkasan dokumen jadi agregat, urutan lewat JOIN

Muat-semua + whereIn semua id menembus plafon 2100 parameter SQL Server
di sekitar dokumen ke-2100 — gagal keras, bukan sekadar lambat.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `whereDate` jadi rentang setengah terbuka (P1-11)

**Files:**
- Modify: `app/Services/LMS/SOP/SopPaketService.php:274,279,282`

**Interfaces:**
- Consumes: `IX_N_SOP_Paket_Publish` dari Task 1.
- Produces: —

- [ ] **Step 1: Ganti ketiga pemanggilan**

`whereDate()` membungkus kolom dalam `CAST`, sehingga indeksnya tak terpakai.

```php
// SEBELUM
$query->whereDate('p.Tanggal_Publish', '>=', $batas);
$query->whereDate('p.Tanggal_Publish', '<=', $filters['tgl_sampai']);

// SESUDAH — kolom telanjang di satu sisi perbandingan
$query->where('p.Tanggal_Publish', '>=', Carbon::parse($batas)->startOfDay());
$query->where('p.Tanggal_Publish', '<', Carbon::parse($filters['tgl_sampai'])->addDay()->startOfDay());
```

Batas atas memakai `<` hari berikutnya, bukan `<=` hari yang sama: `<=` pada kolom
`datetime2` membuang seluruh baris yang terjadi **pada** hari itu setelah tengah malam.
Itu perubahan perilaku yang senyap, dan justru bentuk inilah yang menghindarinya.

- [ ] **Step 2: Jalankan uji paket**

Jalankan: `php artisan test --filter=SopPaket`
Diharapkan: identik dengan baseline. Bila ada uji yang menegaskan penyaringan tanggal,
periksa apakah ia sebelumnya bergantung pada perilaku `<=` yang membuang baris.

- [ ] **Step 3: Commit**

```bash
git add app/Services/LMS/SOP/SopPaketService.php
git commit -m "perf(sop): saringan Tanggal_Publish jadi sargable

whereDate membungkus kolom dalam CAST sehingga indeksnya tak terpakai.
Batas atas memakai < hari berikutnya, karena <= pada datetime2 membuang
baris yang terjadi pada hari itu setelah tengah malam.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Memoisasi per-request untuk `SopSetting` (P1-8)

Cache produksi memakai basis data (dipastikan pemilik project 2026-09-01; tak ada Redis).
Karena itu setiap pembacaan cache adalah kueri. `SopSetting::int()` dipanggil berkali-kali
dalam satu permintaan untuk nilai yang sama.

**Files:**
- Modify: `app/Services/LMS/SOP/Support/SopSetting.php`

**Interfaces:**
- Produces: tanda tangan publik **tidak berubah**.

- [ ] **Step 1: Tambahkan memo statis, dan pastikan `forget()` ikut membersihkannya**

```php
private static ?array $memo = null;

public static function semua(): array
{
    if (self::$memo !== null) {
        return self::$memo;
    }

    return self::$memo = Cache::store('database')->remember(self::CACHE_KEY, self::TTL, fn () => /* ...seperti sekarang... */);
}

public static function lupakan(): void
{
    self::$memo = null;                       // WAJIB: tanpa ini, permintaan yang
    Cache::store('database')->forget(self::CACHE_KEY);  // mengubah setting lalu membacanya
}                                             // kembali masih melihat nilai lama.
```

Memo bersifat per-proses dan hidup selama satu permintaan. Yang paling mudah terlewat
adalah membersihkannya di `lupakan()` — tanpa itu, permintaan yang menulis setting lalu
membacanya kembali menerima nilai basi, dan hanya di jalur itu.

- [ ] **Step 2: Jalankan uji setting**

Jalankan: `php artisan test --filter=SopSetting`

- [ ] **Step 3: Commit**

```bash
git add app/Services/LMS/SOP/Support/SopSetting.php
git commit -m "perf(sop): memoisasi SopSetting per-request

Cache produksi memakai basis data, jadi tiap pembacaan cache adalah kueri.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Cache untuk hasil tersaring (P1-7) — hanya setelah Task 2

**Urutan ini mengikat.** P1-7 bekerja dengan menaikkan kardinalitas kunci — persis
dimensi yang membuat tabel cache tumbuh tanpa batas hari ini. Tanpa pemangkas dari Task 2,
ini menukar satu masalah performa dengan masalah lain yang lebih sulit dilihat.

**Files:**
- Modify: `app/Services/LMS/SOP/Support/SaringanMonitoring.php:106`
- Create: `tests/Feature/LMS/SOP/SopSaringanCacheTest.php`

**Interfaces:**
- Consumes: command `hris:cache-prune` (Task 2).
- Produces: `mungkinCache(string $kunci, int $detik, array $f, \Closure $fn)` — tanda tangan **tidak berubah**.

- [ ] **Step 1: Pastikan Task 2 sudah selesai dan terdaftar**

Bila `hris:cache-prune` belum ada atau belum dijadwalkan, **berhenti** dan selesaikan
Task 2 lebih dulu.

- [ ] **Step 2: Ganti bypass dengan kunci ber-hash**

```php
protected function mungkinCache(string $kunci, int $detik, array $f, \Closure $fn)
{
    if ($this->tersaringSelainWaktu($f)) {
        /* Dulu di sini cache dilewati sama sekali, sehingga admin yang menyaring
           per divisi memicu agregat penuh pada SETIAP interaksi. Kini disimpan
           dengan kunci sendiri dan TTL lebih pendek.

           Saringan dinormalkan LEBIH DULU supaya bentuk yang ekuivalen
           (urutan berbeda, nilai kosong, duplikat) berbagi satu kunci —
           tanpa itu kardinalitas kunci meledak dan tabel cache ikut. */
        return Cache::store('database')->remember(
            $kunci.':f'.$this->sidikSaringan($f),
            300,
            $fn,
        );
    }

    return Cache::store('database')->remember($kunci.$this->imbuhanWaktu($f), $detik, $fn);
}

private function sidikSaringan(array $f): string
{
    $bersih = [];
    foreach ($f as $k => $v) {
        if ($v === null || $v === '' || $v === []) {
            continue;                        // kosong = tak menyaring
        }
        if (is_array($v)) {
            $v = array_values(array_unique($v));
            sort($v);                        // urutan pilihan tak mengubah hasil
        }
        $bersih[$k] = $v;
    }
    ksort($bersih);

    return substr(md5(json_encode($bersih)), 0, 12);
}
```

- [ ] **Step 3: Tulis uji normalisasi kunci**

```php
public function test_saringan_ekuivalen_berbagi_satu_kunci(): void
{
    // ['dept' => ['B','A']] dan ['dept' => ['A','B','A'], 'kosong' => '']
    // harus menghasilkan sidik yang SAMA — kalau tidak, kardinalitas kunci
    // meledak dan tabel cache ikut tumbuh tanpa batas.
}
```

Gunakan refleksi untuk memanggil `sidikSaringan()`, atau naikkan visibilitasnya menjadi
`protected` dan uji lewat subclass — jangan menjadikannya `public` hanya demi uji.

- [ ] **Step 4: Jalankan uji monitoring**

Jalankan: `php artisan test --filter=Monitoring`

- [ ] **Step 5: Commit**

```bash
git add app/Services/LMS/SOP/Support/SaringanMonitoring.php tests/Feature/LMS/SOP/SopSaringanCacheTest.php
git commit -m "perf(sop): hasil monitoring tersaring ikut di-cache

Sebelumnya cache dilewati total begitu ada saringan non-waktu, sehingga
admin yang menyaring per divisi memicu agregat penuh tiap interaksi.
Saringan dinormalkan lebih dulu agar kardinalitas kunci tetap terkendali.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Verifikasi akhir

**Files:**
- Create: `docs/superpowers/plans/baseline/2026-09-03-sesudah-fase3.txt`

- [ ] **Step 1: Minta izin menjalankan suite penuh**

- [ ] **Step 2: Jalankan dan bandingkan terhadap baseline Fase 2**

```bash
php artisan test --testsuite=Feature > docs/superpowers/plans/baseline/2026-09-03-sesudah-fase3.txt 2>&1; echo "exit=$?"
diff <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-02-sesudah-fase2.txt | sort) \
     <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-03-sesudah-fase3.txt | sort)
```

Diharapkan: nol kegagalan baru.

- [ ] **Step 3: Verifikasi tabel cache benar-benar susut**

```bash
php artisan hris:cache-prune
php artisan tinker --execute="
\$r = DB::selectOne(\"SELECT COUNT(*) c, SUM(CASE WHEN expiration < DATEDIFF(second,'1970-01-01',GETUTCDATE()) THEN 1 ELSE 0 END) mati FROM cache\");
echo 'baris='.\$r->c.' kedaluwarsa='.\$r->mati.PHP_EOL;"
```

Diharapkan: `kedaluwarsa` mendekati nol. Bandingkan dengan pengukuran 2026-09-01: 121
baris, 96 kedaluwarsa.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/baseline/2026-09-03-sesudah-fase3.txt
git commit -m "test(sop): verifikasi Fase 3 terhadap baseline

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Kriteria selesai Fase 3

1. Lima indeks terpasang di dev dan terverifikasi lewat `sys.indexes`; ledger DDL bernilai `TRACKER_PENDING`, **bukan** `PRODUCTION_EXECUTED`.
2. Assign 50 karyawan × 3 item menerbitkan kurang dari 20 pernyataan, **dan** 150 baris progress benar-benar terbentuk.
3. `simpanHalaman()` menerbitkan paling banyak 3 kueri pada jalur baris-sudah-ada; halaman tak pernah mundur; durasi terakumulasi.
4. Jumlah kueri ringkasan dokumen tidak berubah setelah 25 dokumen ditambahkan.
5. `hris:cache-prune` ada, teruji, dan kebutuhan penjadwalannya disampaikan ke pemilik project.
6. Setiap penjaga jumlah-kueri pernah terlihat MERAH pada kode lama.
7. Suite penuh dibandingkan terhadap baseline Fase 2; nol kegagalan baru.

## Pekerjaan yang tersisa untuk pemilik project

1. Daftarkan `hris:cache-prune` di `N_HRIS_Scheduler_Configs` (pola `hris:api-nonce-prune`, tiap jam).
2. Eksekusi DDL-2026-063 ke produksi lewat SQL Tracker, lalu konfirmasikan agar statusnya dapat dinaikkan.
3. Tuntaskan 6 item `TRACKER_PENDING` dan 3 `PENDING` yang sudah ada di ledger.
4. Pertimbangkan RCSI sebagai pekerjaan tersendiri dengan pengukuran tempdb — perbaikan tunggal terbesar yang tersedia, sengaja di luar ketiga fase ini.
