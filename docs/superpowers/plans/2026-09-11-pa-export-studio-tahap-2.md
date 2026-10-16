# Export Studio PA Tahap 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menuntaskan Export Studio Performance Appraisal dengan dataset `kompetensi` dan `peserta`, dua template sistem, serta penjaga kontrak dan eksekusi SQL Server.

**Architecture:** Tahap 2 hanya menambah dua penghuni ke mesin laporan generik yang sudah dipakai Tahap 1. `PaKompetensiDataset` membaca satu baris agregat peserta × kompetensi (`PA_Rater_Category_Id IS NULL`); `PaPesertaDataset` membaca satu snapshot peserta per siklus tanpa bergantung pada hasil. Registrasi dan template memperluas provider/galeri yang sudah ada; tidak ada halaman, tabel, rute, controller, atau mesin export baru.

**Tech Stack:** Laravel 11 · SQL Server (`sqlsrv`) · PHPUnit · mesin `SYSTEM/Application/Reports`

**Spec:** `docs/superpowers/specs/2026-09-09-pa-export-studio-design.md`

## Global Constraints

- Modul: `KPI` → submodul `PerformanceAppraisal` → fitur `Export`.
- Seluruh sumber menyaring `Flag_Aktif = 'Y'` pada baris utama dan snapshot terkait.
- Identitas organisasi selalu memakai kolom `..._Saat_Cycle` dari `N_PA_Participant`; jangan join master karyawan hidup atau `View_Divisi_Sub_Divisi`.
- Dataset `kompetensi` wajib `N_PA_Result_Competency.PA_Rater_Category_Id IS NULL`; baris kategori penilai tidak boleh ikut.
- Dataset `peserta` berbasis `N_PA_Participant`, bukan `N_PA_Result`; peserta dikecualikan dan tertunda harus tetap bisa dilaporkan.
- Non-admin menerima kueri `1 = 0`; tidak ada penyempitan `Lingkup` lain.
- Hanya `COUNT(*)`/`SUM()` yang memiliki grand total; rata-rata memakai `Ukuran::TOTAL_ABAIKAN`.
- Mode ringkasan harus dieksekusi pada SQL Server sungguhan untuk setiap dimensi; `toSql()` tidak cukup.
- Tidak ada migration, DDL, DML, menu, atau hak akses baru pada Tahap 2.
- Jangan sentuh, stage, atau commit perubahan PA lain yang sudah ada di working tree.
- Jangan commit sebelum pengguna meminta.

---

### Task 1: Dataset Kompetensi

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaKompetensiDataset.php`
- Create: `tests/Feature/KPI/PerformanceAppraisal/Export/PaKompetensiDatasetTest.php`

**Interfaces:**
- Consumes: `DatasetLaporan`, `PenyediaOpsiSaringan`, `Dimensi`, `Ukuran`, `Saringan`, `Lingkup` dari mesin laporan generik.
- Produces: dataset module `pa`, key `kompetensi`, grain peserta × kompetensi.

- [ ] **Step 1: Tulis uji kontrak merah**

Uji harus menegaskan:
- `kunci() === 'kompetensi'` dan grain menyebut peserta muncul berulang per kompetensi.
- `baseQuery()` hanya menghasilkan baris agregat `PA_Rater_Category_Id IS NULL`.
- filter aktif berlaku pada hasil, peserta, kompetensi, dan result competency.
- dimensi minimum: siklus, identitas snapshot, jenis/kode/nama kompetensi.
- ukuran: jumlah pasangan peserta-kompetensi, jumlah peserta dinilai, rata-rata nilai, jumlah penilai.
- rata-rata memakai `TOTAL_ABAIKAN`; count/sum tetap ditotal.
- non-admin mendapat nol baris.

- [ ] **Step 2: Jalankan uji dan lihat merah**

Run:
```bash
php artisan test tests/Feature/KPI/PerformanceAppraisal/Export/PaKompetensiDatasetTest.php
```
Expected: FAIL karena `PaKompetensiDataset` belum ada.

- [ ] **Step 3: Implementasikan dataset**

`baseQuery()`:
```php
DB::table('N_PA_Result_Competency as rc')
    ->join('N_PA_Result as r', ... r.Flag_Aktif = Y)
    ->join('N_PA_Participant as p', ... p.Flag_Aktif = Y)
    ->leftJoin('N_PA_Cycle as c', ...)
    ->join('N_PA_Competency as k', ... k.Flag_Aktif = Y)
    ->leftJoin('N_PA_Competency_Type as kt', ... kt.Flag_Aktif = Y)
    ->where('rc.Flag_Aktif', 'Y')
    ->whereNull('rc.PA_Rater_Category_Id');
```

Dimensi memakai alias sederhana tanpa subkueri agar aman di `GROUP BY`: `kode_cycle`, `nama_cycle`, `tahun`, `kode_karyawan`, `nama_karyawan`, `level`, `divisi`, `sub_divisi`, `jabatan`, `lokasi`, `jenis_kompetensi`, `kode_kompetensi`, `nama_kompetensi`, `nilai_baris`, `jumlah_penilai_baris`. Ukuran: `jumlah_pasang`, `jumlah_peserta` (`COUNT(DISTINCT p.Id_PA_Participant)`), `rata_rata_nilai` (`AVG(CAST(rc.Nilai AS float))`, total abaikan), `jumlah_penilai` (`SUM(COALESCE(rc.Jumlah_Penilai, 0))`).

Saringan: siklus, tahun, divisi, jenis, kode kompetensi. Opsi berasal dari kueri DISTINCT yang tetap memakai filter aktif dan agregat global.

- [ ] **Step 4: Jalankan uji dataset**

Expected: PASS.

---

### Task 2: Dataset Peserta

**Files:**
- Create: `app/Services/KPI/PerformanceAppraisal/Export/PaPesertaDataset.php`
- Create: `tests/Feature/KPI/PerformanceAppraisal/Export/PaPesertaDatasetTest.php`

**Interfaces:**
- Produces: dataset module `pa`, key `peserta`, grain satu peserta terdaftar pada satu siklus.

- [ ] **Step 1: Tulis uji kontrak merah**

Uji harus menegaskan:
- basis hanya `N_PA_Participant` + siklus; tidak INNER JOIN hasil.
- `DIIKUTKAN`, `DIKECUALIKAN`, dan `TERTUNDA` tersedia sebagai opsi status.
- `Alasan_Dikecualikan` dan `Snapshot_At` menjadi dimensi rinci.
- ukuran `jumlah_orang = COUNT(*)` dan `jumlah_penugasan` berasal dari LEFT JOIN turunan yang sudah diagregasi per participant, sehingga peserta tanpa tugas tetap muncul dan tidak tergandakan.
- non-admin mendapat nol baris.

- [ ] **Step 2: Jalankan uji dan lihat merah**

Run:
```bash
php artisan test tests/Feature/KPI/PerformanceAppraisal/Export/PaPesertaDatasetTest.php
```
Expected: FAIL karena kelas belum ada.

- [ ] **Step 3: Implementasikan dataset**

Pakai LEFT JOIN ke derived table:
```sql
SELECT PA_Participant_Id, COUNT(*) AS jumlah_penugasan
FROM N_PA_Assignment
WHERE Flag_Aktif = 'Y'
GROUP BY PA_Participant_Id
```

Dimensi: siklus + periode, identitas snapshot, `status_peserta`, `alasan_dikecualikan`, `snapshot_at`, dan `jumlah_penugasan_baris`. Ukuran: `jumlah_orang`, `jumlah_penugasan = SUM(COALESCE(at.jumlah_penugasan,0))`. Saringan: siklus, tahun, divisi, status peserta; opsi status memakai satu konstanta daftar agar label/filter tidak menyimpang.

- [ ] **Step 4: Jalankan uji dataset**

Expected: PASS.

---

### Task 3: Registrasi dan Template Sistem

**Files:**
- Modify: `app/Services/KPI/PerformanceAppraisal/Export/PaReportServiceProvider.php`
- Modify: `app/Services/KPI/PerformanceAppraisal/Export/PaSystemTemplates.php`
- Modify: `tests/Feature/KPI/PerformanceAppraisal/Export/PaExportPendaftaranTest.php`
- Modify: `tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioAksesTest.php`

**Interfaces:**
- Registers: `pa/kompetensi`, `pa/peserta`.
- Produces templates: `pa-gap-kompetensi` and `pa-audit-cakupan`.

- [ ] **Step 1: Perluas uji registrasi/template dan lihat merah**

Expected checks:
```php
$this->assertSame('kompetensi', $registry->ambil('pa', 'kompetensi')->kunci());
$this->assertSame('peserta', $registry->ambil('pa', 'peserta')->kunci());
```

Template:
- `Gap Kompetensi per Departemen`: dataset `kompetensi`, mode ringkasan, dimensi `divisi`, `jenis_kompetensi`, `nama_kompetensi`; ukuran `jumlah_peserta`, `rata_rata_nilai`.
- `Audit Cakupan Siklus`: dataset `peserta`, mode rinci; kolom siklus, kode/nama, organisasi snapshot, status, alasan, snapshot, jumlah penugasan baris.

- [ ] **Step 2: Daftarkan kelas dan tambah dua preset**

Provider menambah dua `daftarkan('pa', ...)`. `PaSystemTemplates::semua()` menjadi empat template tanpa mengubah dua template lama.

- [ ] **Step 3: Jalankan uji registrasi dan akses**

Run:
```bash
php artisan test tests/Feature/KPI/PerformanceAppraisal/Export/PaExportPendaftaranTest.php tests/Feature/KPI/PerformanceAppraisal/Export/PaExportStudioAksesTest.php
```
Expected: PASS.

---

### Task 4: Mode Ringkasan SQL Server dan Rantai Export

**Files:**
- Modify: `tests/Integration/KPI/PerformanceAppraisal/Export/PaExportStudioModeRingkasanTest.php`
- Create: `tests/Integration/KPI/PerformanceAppraisal/Export/PaExportStudioTahapDuaTest.php`

**Interfaces:**
- Consumes: empat dataset terdaftar, `ReportCompiler`, `DefinisiLaporan`, `ReportRunner`/export service yang sudah ada.

- [ ] **Step 1: Perluas matriks ringkasan**

Tambahkan ukuran utama:
```php
'kompetensi' => 'jumlah_pasang',
'peserta' => 'jumlah_orang',
```

Jalankan setiap dimensi kedua dataset baru pada SQL Server dan tegaskan baris ringkasan tidak melebihi grain rinci.

- [ ] **Step 2: Tambah uji rantai Tahap 2**

Semai fixture kecil lewat jalur PA yang sudah ada atau manfaatkan data development hanya untuk test integration baca-saja. Uji:
- katalog mengembalikan dimensi/ukuran/saringan kedua dataset;
- preset sistem bisa dikompilasi;
- pratinjau mode rinci dan ringkasan menghasilkan metadata dan baris;
- export CSV/XLSX/PDF memakai registrasi module `pa-studio` dan definisi yang sama;
- peserta dikecualikan tetap hadir pada dataset `peserta`;
- baris kategori penilai tidak hadir pada dataset `kompetensi`.

- [ ] **Step 3: Jalankan suite PA Export Studio**

Run:
```bash
php artisan test tests/Feature/KPI/PerformanceAppraisal/Export tests/Integration/KPI/PerformanceAppraisal/Export
```
Expected: PASS.

- [ ] **Step 4: Jalankan style dan structural gates yang relevan**

Run:
```bash
./vendor/bin/pint --test app/Services/KPI/PerformanceAppraisal/Export tests/Feature/KPI/PerformanceAppraisal/Export tests/Integration/KPI/PerformanceAppraisal/Export
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
```
Expected: PASS dan tidak ada rute/UI baru.

---

## Verification Matrix

- Dataset keys: `hasil`, `penugasan`, `kompetensi`, `peserta`.
- Template system: 4 total, satu per dataset.
- Grain rinci: tidak menggandakan peserta karena kategori penilai atau join penugasan.
- Ringkasan: seluruh dimensi keempat dataset dieksekusi pada SQL Server.
- Access: non-admin tetap nol data; middleware halaman/API Tahap 1 tidak berubah.
- Database changes: none.
- Unrelated working-tree files: untouched.
