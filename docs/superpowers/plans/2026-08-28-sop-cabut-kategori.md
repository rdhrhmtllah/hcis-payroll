# Pencabutan Kategori dari modul DMS/SOP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mencabut Kategori SOP dari modul — halaman master dibongkar, kewajibannya di Builder dihapus, saringan dan sumbunya dicabut dari Perpustakaan/Monitoring/Penjadwalan/Export Studio — tanpa menyentuh skema DB.

**Architecture:** Pencabutan berlapis dari pinggir ke tengah. Izin halaman Prioritas dimandirikan lebih dulu (kalau tidak, mencabut kunci Kategori mematikannya), lalu halaman master dibongkar, lalu kewajiban tulis di Builder, lalu saringan/sumbu baca di layar-layar hilir, terakhir laporan. Tabel `N_SOP_Kategori` dan kolom `N_SOP_Master_Paket.Id_Kategori_SOP` dibiarkan berdiri — kode berhenti membacanya, bukan datanya dihapus.

**Tech Stack:** Laravel 11 + Inertia + Vue 3 (Options API), SQL Server (`Web_HRIS` di team311), PHPUnit, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md`

## Global Constraints

- **Nol DDL.** Tak ada `CREATE`/`ALTER`/`DROP` sama sekali. `N_SOP_Kategori` dan `N_SOP_Master_Paket.Id_Kategori_SOP` tidak disentuh — bukan dihapus, bukan dinonaktifkan, bukan dikosongkan.
- **DML wajib lewat berkas** `database/dml/<change-id>/data-change.sql`, tiap tahap bisa gagal sendirian, tanpa `MERGE`.
- **Urutan tahap 1 mengikat.** Skrip menu Prioritas menyalin dari baris `menuSopKategoriPage`; begitu Kategori dimatikan skrip itu mandul. Jalankan lebih dulu, tak bisa dibalik.
- **Aturan dua pintu.** Setiap penjaga di modul SOP hidup di dua jalur setara (Master dan Builder). Mencabut di satu pintu saja meninggalkan yang lain. Uji lewat efek, bukan lewat pemanggilan.
- **Jangan jalankan test/build sampai diminta.** Tulis kode dan uji sampai selesai; `artisan test` menembak DB dev dan mengunci UI pengguna.
- **Suite penuh, bukan `--filter`.** Perubahan menyentuh service bersama. Baseline ~19 kegagalan non-SOP memang sudah ada sebelum pekerjaan ini.
- **`flushMenuCache` wajib** setiap kali baris menu berubah: `php artisan tinker --execute="App\Services\Shell\InertiaShellService::flushMenuCache();"`
- Kunci cache opsi saringan Export Studio **wajib dinaikkan** saat dimensi/saringan dicabut.

---

### Task 1: Mandirikan izin halaman Prioritas

Halaman Prioritas sudah berdiri sendiri di kode. Yang belum: tiga baris pendaftarannya di DB. Skrip `plans/sop/sql/37_menu_prioritas_sop.sql` sudah ditulis tapi tak pernah dijalankan, dan basi di tiga titik.

**Files:**
- Create: `database/dml/DML-2026-055-sop-cabut-kategori/data-change.sql` (tahap 1 saja untuk task ini)
- Modify: `plans/sop/sql/37_menu_prioritas_sop.sql` (koreksi komentar)
- Modify: `routes/LMS/SOP/Governance/SopGovernance.php:93-105`
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminPrioritasController.php:19`
- Test: `tests/Feature/LMS/SOP/SopIzinPrioritasMandiriTest.php`

**Interfaces:**
- Produces: kunci `menuSopPrioritasPage` berdiri sendiri sebagai satu-satunya gerbang `sop/admin/prioritas`.

- [ ] **Step 1: Tulis uji yang menuntut penolakan**

```php
/** Pemegang HANYA menuSopKategoriPage tak lagi bisa membuka Prioritas. */
public function test_kunci_kategori_tidak_lagi_membuka_prioritas(): void
{
    $user = $this->userDenganAkses(['menuSopKategoriPage']);
    $this->actingAs($user)->get('/sop/admin/prioritas')->assertForbidden();
}

/** Pemegang menuSopPrioritasPage bisa membukanya. */
public function test_kunci_prioritas_membuka_halamannya(): void
{
    $user = $this->userDenganAkses(['menuSopPrioritasPage']);
    $this->actingAs($user)->get('/sop/admin/prioritas')->assertOk();
}
```

- [ ] **Step 2: Jalankan uji, pastikan uji pertama MERAH**

Expected: `test_kunci_kategori_tidak_lagi_membuka_prioritas` FAIL (masih 200, karena gerbangnya masih menerima dua kunci).

- [ ] **Step 3: Segarkan skrip 37 dan angkat jadi tahap DML**

Tiga koreksi pada `plans/sop/sql/37_menu_prioritas_sop.sql`:
- Komentar `Sort_Menu`: slot 2 kini terpakai `menuSopKategoriPage`. Nilainya **tetap 2** — Kategori mengosongkannya di Task 10, sehingga urutan akhir 1–11 tanpa lubang.
- Komentar grup fitur: `grup 3 (Learning Management System)` → `grup 7 (DMS)`, sejak DML-2026-044. SQL-nya tak berubah (menyalin `Id_Grup_Fitur` dari baris Kategori).
- Tambahkan peringatan: sumber salinan adalah baris Kategori yang dimatikan di tahap akhir; skrip ini harus jalan lebih dulu.

Salin isinya sebagai **tahap 1** di `database/dml/DML-2026-055-sop-cabut-kategori/data-change.sql`.

- [ ] **Step 4: Jalankan tahap 1 ke dev, lalu `flushMenuCache`**

Verifikasi:
```
N_HRIS_Menus          menuSopPrioritasPage → 1 baris
N_HRIS_Grup_Fitur_Map menuSopPrioritasPage → 1 baris, Id_Grup_Fitur = 7
HRIS_Page_Access      menuSopPrioritasPage → 2 baris (user 4 & 622)
```

- [ ] **Step 5: Pindahkan gerbang ke kunci tunggal**

`SopGovernance.php:105` → `check.jabatan:menuSopPrioritasPage`. Komentar `:93-104` ditulis ulang: tongkat `menuSopKategoriPage` sudah dilepas, barisnya sudah ditanam. `SopAdminPrioritasController.php:19` — buang kalimat "ditumpangkan pada hak akses Kategori".

- [ ] **Step 6: Jalankan uji, keduanya HIJAU**

- [ ] **Step 7: Commit**

```bash
git add database/dml plans/sop/sql routes/LMS/SOP/Governance/SopGovernance.php \
        app/Http/Controllers/LMS/SOP/Governance/SopAdminPrioritasController.php \
        tests/Feature/LMS/SOP/SopIzinPrioritasMandiriTest.php
git commit -m "feat(dms): halaman Prioritas berdiri di kunci izinnya sendiri"
```

---

### Task 2: Bongkar halaman master Kategori

**Files:**
- Delete: `app/Http/Controllers/LMS/SOP/Governance/SopCategory/SopCategoryController.php`
- Delete: `app/Services/LMS/SOP/SopKategoriService.php`
- Delete: `resources/js/Pages/LMS/SOP/Governance/SopCategory/` (10 berkas)
- Modify: `routes/LMS/SOP/Governance/SopGovernance.php:30-41`
- Test: `tests/Feature/LMS/SOP/SopKategoriDicabutTest.php`

**Interfaces:**
- Consumes: Task 1 — gerbang Prioritas sudah mandiri, jadi menghapus blok Kategori aman.
- Produces: route `sop/admin/kategori` dan `api/v1/sop/admin/kategori/*` tak ada lagi.

- [ ] **Step 1: Tulis uji yang menuntut 404**

```php
public function test_halaman_master_kategori_sudah_tidak_ada(): void
{
    $user = $this->userAdminSop();
    $this->actingAs($user)->get('/sop/admin/kategori')->assertNotFound();
    $this->actingAs($user)->getJson('/api/v1/sop/admin/kategori/current')->assertNotFound();
    $this->actingAs($user)->postJson('/api/v1/sop/admin/kategori/store', [])->assertNotFound();
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH** (sekarang 200/302, bukan 404)

- [ ] **Step 3: Hapus berkas dan blok route**

Buang `use` statement `SopCategoryController` di `SopGovernance.php`. Pastikan `route:clear` — cache route per-worktree bisa menyimpan kelas yang sudah dihapus.

- [ ] **Step 4: Jalankan uji, HIJAU**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): bongkar halaman master Kategori SOP"
```

---

### Task 3: Cabut kewajiban Kategori di Builder

Aturan dua pintu: dua gerbang `"Kategori tidak valid"` (`:310` simpan, `:351` perbarui). Cabut keduanya.

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` — `:195-201`, `:229-230`, `:309-311`, `:350-352`, `:1999-2010`, `:2045`
- Modify: `routes/LMS/SOP/Governance/SopGovernance.php:223` (buang `/options/kategori`)
- Test: `tests/Feature/LMS/SOP/SopPaketTanpaKategoriTest.php`

**Interfaces:**
- Produces: `POST /api/v1/sop/admin/builder/paket` menerima payload tanpa `Id_Kategori_SOP`.

- [ ] **Step 1: Tulis uji yang menuntut PENERIMAAN**

```php
/** Paket baru tanpa kategori diterima — dulu ditolak "Kategori tidak valid". */
public function test_simpan_paket_tanpa_kategori_diterima(): void
{
    $this->actingAs($this->userAdminSop())
        ->postJson('/api/v1/sop/admin/builder/paket', [
            'Nama_Paket' => 'Paket Tanpa Kategori',
            'Kode_Paket' => 'PTK-001',
        ])
        ->assertSuccessful();
}

/** Pintu kedua: perbarui paket juga tak lagi menuntut kategori. */
public function test_perbarui_paket_tanpa_kategori_diterima(): void { /* ... paket yang sudah ada, PUT tanpa Id_Kategori_SOP */ }

public function test_endpoint_options_kategori_sudah_tidak_ada(): void
{
    $this->actingAs($this->userAdminSop())
        ->getJson('/api/v1/sop/admin/builder/options/kategori')
        ->assertNotFound();
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH** (400 "Kategori tidak valid")

- [ ] **Step 3: Cabut gerbang, validasi, endpoint, dan perakit saringan**

- [ ] **Step 4: Jalankan uji, HIJAU**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): Builder tak lagi mewajibkan Kategori"
```

---

### Task 4: Paket services berhenti menulis dan membaca Kategori

**Files:**
- Modify: `app/Services/LMS/SOP/SopPaketService.php` (10 rujukan: `:32`? tidak — `:39-40`, `:70`, `:75`, `:82`, `:113-114`, `:148-155`, `:184`, `:218`, `:266`)
- Modify: `app/Services/LMS/SOP/SopPaketDraftService.php` (7)
- Modify: `app/Services/LMS/SOP/SopPaketVersionService.php` (1)
- Modify: `app/Services/LMS/SOP/Support/SopDiff.php` (1)
- Test: `tests/Feature/LMS/SOP/SopPaketTanpaKategoriTest.php` (diperluas)

**Interfaces:**
- Consumes: Task 3.
- Produces: baris paket dari `SopPaketService::list()` tak lagi punya properti `Nama_Kategori` / `Id_Kategori_SOP`.

- [ ] **Step 1: Tulis uji bentuk baris**

```php
public function test_baris_paket_tak_lagi_membawa_kategori(): void
{
    [$rows, ] = app(SopPaketService::class)->list([], 1, 10);
    $this->assertNotEmpty($rows, 'fikstur kosong — uji ini tak menguji apa pun');
    $this->assertObjectNotHasProperty('Nama_Kategori', $rows->first());
    $this->assertObjectNotHasProperty('Id_Kategori_SOP', $rows->first());
}
```

Penjaga kerja-nyata (`assertNotEmpty`) wajib: daftar kosong membuat asersi properti lolos sempurna tanpa menguji apa pun.

- [ ] **Step 2: Jalankan, pastikan MERAH**

- [ ] **Step 3: Buang join, kolom select, cabang saringan, dan penulisan `Id_Kategori_SOP`** pada `buat()`, `klon()`, dan simpan versi. Kolom DB tetap ada dan jadi `NULL` untuk baris baru.

- [ ] **Step 4: Jalankan uji, HIJAU**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): paket service berhenti membaca dan menulis Kategori"
```

---

### Task 5: Cabut saringan Kategori dari Monitoring dan Penjadwalan

**Files:**
- Modify: `app/Services/LMS/SOP/Support/SaringanMonitoring.php:35`, `:54`
- Modify: `app/Services/LMS/SOP/SopMonitoringPeopleService.php:58`, `:291` (+1)
- Modify: `app/Services/LMS/SOP/SopMonitoringService.php` (2)
- Modify: `app/Services/LMS/SOP/SopMonitoringAnalyticsService.php:277`, `:695`
- Modify: `app/Services/LMS/SOP/SopPenjadwalanListService.php` (1), `SopPenjadwalanService.php` (1), `SopProgressReadService.php` (7)
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminPenjadwalanController.php:587`
- Modify Vue: `SopMonitoringKaryawan.vue`, `monitoring/TabPaket.vue`, `monitoring/TabKaryawan.vue`, `monitoring/ringkasan/ZonaTren.vue`, `ringkasan/panel/PanelHeatmap.vue`, `ringkasan/ZonaTindakan.vue`, `ringkasan/panel/ModalExportRingkasan.vue`, `penjadwalan/WizardPenjadwalan.vue`, `Governance/Admin/SopBuilder.vue`
- Modify Vue (lencana): `Shared/components/ui/SopPaketCard.vue`, `SopTugasCard.vue`, `SopLivePreview.vue`
- Test: `tests/Feature/LMS/SOP/SopRingkasanSaringanTest.php` (disesuaikan)

**PERINGATAN:** gerbang penugasan "publik hanya bila sudah dibuka" hidup di `SopMonitoringPeopleService::terapkanSaringanPaket` dan dipakai 7 pemanggil. Jangan tersenggol saat membuang cabang kategori dari service yang sama.

- [ ] **Step 1: Tulis uji bahwa katalog opsi saringan tak lagi menawarkan kategori**

```php
public function test_katalog_saringan_monitoring_tanpa_kategori(): void
{
    $opsi = app(SopMonitoringPeopleService::class)->opsiSaringan();
    $this->assertNotEmpty($opsi, 'katalog kosong — uji ini tak menguji apa pun');
    $this->assertArrayNotHasKey('kategori', $opsi);
    $this->assertArrayHasKey('paket', $opsi, 'saringan lain harus tetap ada');
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**
- [ ] **Step 3: Cabut backend, lalu Vue**
- [ ] **Step 4: Jalankan uji + `npx vitest run` berkas Vue terkait, HIJAU**
- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): cabut saringan Kategori dari Monitoring dan Penjadwalan"
```

---

### Task 6: Turunkan sumbu SOP Saya dari 4 jadi 3

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Employee/Saya/sumbuTugas.js:24`, `:45-51`, `:88`, `:156-159`, `:305`
- Modify: `Employee/Saya/RailSumbu.vue`, `SopSaya.vue`, `BarisTugasSaya.vue`, `BarisDokumenSaya.vue`, `sumberPalet.js`
- Test: `resources/js/Pages/LMS/SOP/Employee/Saya/sumbuTugas.test.js`

**Interfaces:**
- Produces: `SUMBU` = `[AKSIS.departemen, AKSIS.jenis, AKSIS.prioritas]`; sumbu bawaan `departemen`.

- [ ] **Step 1: Tulis uji**

```js
it('SUMBU tak lagi memuat kategori', () => {
    expect(SUMBU.map((s) => s.key)).toEqual(['departemen', 'jenis', 'prioritas']);
});
```

- [ ] **Step 2: Jalankan `npx vitest run sumbuTugas`, pastikan MERAH**
- [ ] **Step 3: Hapus `AKSIS.kategori`, `TANPA.kategori`, cabang `id_kategori` di `:159`, dan tautan balik ke Perpustakaan**
- [ ] **Step 4: Jalankan, HIJAU**
- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): sumbu SOP Saya turun jadi departemen, jenis, prioritas"
```

---

### Task 7: Perpustakaan jadi daftar paket datar

**Files:**
- Modify: `app/Services/LMS/SOP/SopRepositoryReadService.php` — hapus `folderKategori()` (`:151-225`) dan `kategoriDetail()` (`:286-340`); buang `Kategori`/`Id_Kategori` di `:584-585`, `:800-801`; buang `k.Nama_Kategori` dari pencarian `:364` dan select `:439`, `:557`, `:708`; buang join `:655`
- Modify: `app/Http/Controllers/LMS/SOP/Employee/SopRepositoryController.php` — hapus `apiKategori()` (`:184-243`), join `:98`
- Modify: `routes/LMS/SOP/Employee/SopEmployee.php:47` (hapus)
- Delete: `resources/js/Pages/LMS/SOP/Employee/Repository/FolderKategori.vue`, `HalamanKategori.vue`
- Modify: `Employee/Repository/SopRepository.vue` — lensa "Paket" jadi daftar kartu paket + pencarian + saringan prioritas; buang `?kat=` (`:564-570`, `:576`), impor & komponen `FolderKategori`/`HalamanKategori` (`:114`, `:338`, `:411-412`, `:433-434`)
- Modify: `Employee/Repository/KartuSop.vue`, `LensaDokumen.vue`, `IntipDrawer.vue` (lencana kategori)
- Test: `tests/Feature/LMS/SOP/SopRepositoryDatarTest.php`

**Lensa "Dokumen" (`mode === 'dokumen'`) TIDAK DISENTUH.**

- [ ] **Step 1: Tulis uji**

```php
public function test_endpoint_kategori_perpustakaan_sudah_tidak_ada(): void
{
    $this->actingAs($this->userKaryawan())
        ->getJson('/api/v1/sop/repository/kategori/semua')
        ->assertNotFound();
}

public function test_beranda_mengembalikan_daftar_paket_bukan_folder(): void
{
    $d = app(SopRepositoryReadService::class)->beranda($this->kodeKaryawanFikstur());
    $this->assertArrayNotHasKey('kategori', $d);
    $this->assertNotEmpty($d['paket'] ?? [], 'beranda kosong — uji ini tak menguji apa pun');
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**
- [ ] **Step 3: Cabut backend, route, lalu Vue**
- [ ] **Step 4: Jalankan uji + vitest Perpustakaan, HIJAU**
- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): Perpustakaan jadi daftar paket datar tanpa folder Kategori"
```

---

### Task 8: Cabut Kategori dari laporan dan Export Studio

**Files:**
- Modify: `app/Services/LMS/SOP/Export/SopDatasetKepatuhan.php:78`, `:252`, `:324`, `:454-461`, **naikkan kunci cache opsi saringan**
- Modify: `app/Services/LMS/SOP/Export/SopTemplateSistem.php:32`, `:231-233` — template jadi `"Kepatuhan per Prioritas"`, dimensi `['prioritas', 'distribusi']`
- Modify: `SopComplianceExportService.php:130`, `SopExportRinciService.php:181`, `SopDokumenKesehatanService.php:812`, `SopDocumentControlService.php:96`, `:283`
- Modify: `resources/js/Pages/LMS/SOP/Governance/Admin/monitoring/SopExportStudio.vue`
- Test: `tests/Unit/LMS/SOP/Export/SopDatasetKepatuhanTest.php` (disesuaikan)

- [ ] **Step 1: Tulis uji**

```php
public function test_dataset_kepatuhan_tak_lagi_menawarkan_kategori(): void
{
    $ds = app(SopDatasetKepatuhan::class);
    $dimensi = array_map(fn ($d) => $d->kunci, $ds->dimensi());
    $saringan = array_map(fn ($s) => $s->kunci, $ds->saringan());
    $this->assertNotEmpty($dimensi, 'dataset kosong — uji ini tak menguji apa pun');
    $this->assertNotContains('kategori', $dimensi);
    $this->assertNotContains('kategori', $saringan);
    $this->assertContains('prioritas', $dimensi, 'dimensi lain harus tetap ada');
}
```

- [ ] **Step 2: Jalankan, pastikan MERAH**
- [ ] **Step 3: Cabut dimensi, saringan, join, katalog opsi; naikkan kunci cache; perbaiki template sistem; buang kolom `Nama_Kategori` dari empat service**
- [ ] **Step 4: Jalankan uji, HIJAU**
- [ ] **Step 5: Commit**

```bash
git commit -am "feat(dms): cabut dimensi dan saringan Kategori dari laporan SOP"
```

---

### Task 9: Bersihkan fikstur dan uji

**Files:**
- Delete: `tests/Feature/LMS/SOP/Uat/Kontrol004KategoriTerpakaiTest.php`
- Modify: `app/Services/LMS/SOP/Uat/UatSkenario.php` (buang skenario 004), `UatTabelIzin.php` (buang baris izin `N_SOP_Kategori`), `UatPembangunDunia.php` (4)
- Modify: `tests/Feature/LMS/SOP/Uat/Support/UatDunia.php` (12)
- Modify: `app/Console/Commands/LMS/SOP/Operations/SopSeedDemo.php:824-825`
- Modify uji: `SopBuilderSaringanPaketTest` (28), `Alur024KebijakanOtomasiTest` (18), `SopRingkasanSaringanTest` (16), `SopMasterPrioritasTest` (9), `SopHeatmapSumbuTest` (9), `SopBuilderSaringanFiksturTest` (7), `SopAwarenessUpdateTest` (7), `SopKontenPickerTanpaBunnyTest` (6), `SopJangkauanPublikTest` (6), `SopTipeItemSakelarTest` (5), `SopSayaIdentitasDokumenTest` (4), `SopPenjadwalanServiceTest` (4), `SopDokumenKesehatanTest` (4), `SopDokumenKelompokTest` (4), `SopRepositoryDokumenTest` (3), `SopApqcTest` (3), `Alur005BerkasTerpusatTest`, `Alur015BuilderPaketTest`, `HapusKontenAmanTest`, `UatJurnalTest`, `UatPembersihRencanaTest`, `UatTabelIzinTest`
- Modify vitest: `sumbuTugas.test.js`, `RailSumbu.test.js`, `BarisTugasSaya.test.js`, `BarisDokumenSaya.test.js`, `SopBoot.test.js`, `AwarenessUpdate.test.js`, `SopPenjadwalan.test.js`, `WizardPenjadwalan.test.js`, `SopMonitoringKaryawan.test.js`, `SopExportStudio.test.js`, `KaryawanDrawer.test.js`, `PanelCoverageDokumenPaket.test.js`

- [ ] **Step 1: Hapus uji dan skenario UAT 004**
- [ ] **Step 2: Cabut penyemaian kategori dari fikstur**
- [ ] **Step 3: Sesuaikan uji lain — fikstur berhenti mengoper `Id_Kategori_SOP`, asersi berhenti menuntut `Nama_Kategori`**
- [ ] **Step 4: Commit**

```bash
git commit -am "test(dms): fikstur dan uji berhenti menyemai Kategori"
```

---

### Task 10: DML penutup — bersihkan template, matikan menu dan grant Kategori

**Files:**
- Modify: `database/dml/DML-2026-055-sop-cabut-kategori/data-change.sql` (tahap 2–4)

Tahap-tahap, masing-masing bisa gagal sendirian:

- [ ] **Step 1: Tahap 2 — bersihkan `N_HRIS_Report_Template`**

Buang kunci `kategori` dari `Definisi_Json` pada 9 baris yang menyebutnya (aktif: Id 95, 99, 100), baik sebagai dimensi maupun saringan. Verifikasi: nol baris `Module='sop'` yang `Definisi_Json` masih memuat `"kategori"`.

- [ ] **Step 2: Tahap 3 — matikan baris menu Kategori**

`N_HRIS_Grup_Fitur_Map` Id 133 → `Flag_Aktif='T'`; `N_HRIS_Menus` `menuSopKategoriPage` → `Is_Show_Sidebar='T'`.

- [ ] **Step 3: Tahap 4 — matikan grant**

`HRIS_Page_Access` Id 1162 dan 1199 → `Flag_Aktif='T'`.

- [ ] **Step 4: Jalankan ke dev, lalu `flushMenuCache`**

Verifikasi sidebar: Prioritas SOP muncul, Kategori Dokumen hilang, urutan 1–11 tanpa lubang.

- [ ] **Step 5: Commit**

```bash
git commit -am "chore(dms): DML penutup pencabutan Kategori"
```

---

### Task 11: Suite penuh dan laporan

- [ ] **Step 1: `php artisan test` — suite penuh, bukan `--filter`**

Baseline ~19 kegagalan non-SOP memang sudah ada sebelum pekerjaan ini. Bandingkan dengan baseline, bukan dengan nol.

- [ ] **Step 2: `npx vitest run`**

- [ ] **Step 3: `npm run build`** — pastikan tak ada impor yatim ke berkas Vue yang dihapus.

- [ ] **Step 4: Laporan dua bagian** sesuai AGENTS.md — yang diselesaikan agen, dan yang tersisa untuk pengguna (rekam DML ke SQL Tracker, eksekusi produksi, verifikasi produksi).
