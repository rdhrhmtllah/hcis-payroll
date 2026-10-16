# Export Studio — Fase 2: Layar Studio

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Halaman Export Studio yang bisa dibuka dari sidebar, merakit definisi
laporan, memratinjau hasilnya, dan menjalankan export.

**Architecture:** Halaman Inertia tipis di `Pages/LMS/SOP/Export/` menyerahkan
`baseUrl` ke kerangka builder generik di `components/SYSTEM/ReportStudio/`.
Seluruh isi perakit digerakkan endpoint katalog fase 1 — tak ada daftar dimensi
atau ukuran yang ditulis ulang di frontend.

**Tech Stack:** Vue 3 (Composition API), Inertia, Bootstrap grid + CSS vanilla,
komponen `@/components/ui` (HCIS), Vitest.

**Spec:** `docs/superpowers/specs/2026-08-18-export-studio-design.md` §6
**Fase sebelumnya:** `docs/superpowers/plans/2026-08-18-export-studio-fase1.md` (selesai)

## Global Constraints

- Tanpa Tailwind. Bootstrap untuk grid; styling memakai CSS vanilla dan mengikuti
  bahasa visual SOP yang sudah ada (`sop-scope`, `shero`, `stitch-card`,
  `sop-eyebrow`, ikon Bootstrap Icons).
- Impor lintas berkas memakai alias `@` (→ `resources/js`), tak pernah jalur
  relatif berjenjang.
- Komponen bersama **dilarang** tinggal di dalam `Pages/` — resolver Inertia
  memetakan seluruh isi `Pages/` menjadi nama halaman.
- `components/SYSTEM/ReportStudio/` bukan design system: ia memakai `Hcis*`,
  tidak menjadi `Hcis*`, dan **tidak** didaftarkan ke `registry.js`.
- Empat status layar wajib: memuat, kosong (dibedakan "belum pilih ukuran" vs
  "0 baris"), galat + coba lagi, dan definisi tak sah (inline per panel).
- Request pratinjau yang masih berjalan **wajib dibatalkan** saat definisi
  berubah lagi.
- DML memakai change ID, berkas di `database/dml/<id>/data-change.sql`, README di
  `docs/changes/<id>/`, entri di `database/ddl/status.json`, **dan** pendaftaran
  ke `N_SYSTEM_Database_Change` — mengisi ledger saja tidak membuatnya muncul di
  Database Change Dashboard.
- Jangan `git add -A`. Bandingkan snapshot route ke
  `plans/sop/baseline/export-studio-sebelum-route.json` sebelum commit terakhir.

---

### Task 1: Menu sidebar dan hak akses (DML-2026-012)

Halaman butuh `Jenis_Page` sendiri agar izinnya terpisah dari monitoring. Gate
route memakai pola OR yang sudah dipakai `SopAdminPrioritasController`:
`check.jabatan:menuSopExportStudioPage,menuSopMonitoringPage` — sehingga admin
monitoring yang ada tidak kehilangan akses pada hari rilis.

**Files:**
- Create: `database/dml/DML-2026-012-register-sop-export-studio-menu/data-change.sql`
- Create: `docs/changes/DML-2026-012-register-sop-export-studio-menu/README.md`
- Modify: `database/ddl/status.json`
- Modify: `routes/LMS/SOP/Export/SopExportStudio.php`

- [ ] **Step 1: Baca struktur menu dari database, jangan menebak**

Baca kolom `N_HRIS_Menus` dan satu baris menu SOP yang sudah ada sebagai contoh,
lalu `N_HRIS_Grup_Fitur_Map` untuk memastikan `Id_Grup_Fitur` LMS. Snapshot
dokumentasi bukan otoritas.

- [ ] **Step 2: Tulis DML** — baris menu (salin `Kode_Perusahaan`,
  `Header_Code`, `Header_Label`, `Header_Icon`, `Sort_Header` dari menu SOP
  lain), mapping `Jenis_Page` → `Id_Grup_Fitur` LMS, dan hak akses untuk setiap
  pemegang `menuSopMonitoringPage`. Semua dijaga `IF NOT EXISTS`.
  `Id_Menu` IDENTITY — jangan masuk daftar kolom INSERT.

- [ ] **Step 3: Nyatakan tabel, predikat, perkiraan baris, dan target** sebelum
  eksekusi. Lalu jalankan ke `Web_HRIS` dan verifikasi jumlahnya.

- [ ] **Step 4: Daftarkan ke dashboard** — tanpa langkah ini perubahannya tak
  terlihat di Database Change Dashboard.

- [ ] **Step 5: Gate route** ubah ke
  `check.jabatan:menuSopExportStudioPage,menuSopMonitoringPage`.

- [ ] **Step 6: Commit.**

---

### Task 2: Halaman Inertia dan rute halaman

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Export/SopExportStudioController.php` (tambah `halaman()`)
- Modify: `routes/LMS/SOP/Export/SopExportStudio.php`
- Create: `resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue`

- [ ] **Step 1:** `halaman()` mengembalikan
  `inertia('LMS/SOP/Export/SopExportStudioPage', ['baseUrl' => '/api/v1/sop/export-studio'])`.
  Jangan mengirim katalog dari server: layar mengambilnya per dataset, dan
  mengirimnya dua kali membuat dua sumber yang bisa berbeda.
- [ ] **Step 2:** Route `GET sop/admin/export-studio` bernama `sop.admin.export-studio`.
- [ ] **Step 3:** Halaman tipis — hero SOP + `<ReportStudio :base-url="baseUrl" />`.
- [ ] **Step 4:** `php scripts/verify-inertia-pages.php` harus tetap 0 menggantung.
- [ ] **Step 5: Commit.**

---

### Task 3: Kerangka builder

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.vue`
- Create: `resources/js/components/SYSTEM/ReportStudio/PanelDimensiUkuran.vue`
- Create: `resources/js/components/SYSTEM/ReportStudio/PanelSaringan.vue`
- Create: `resources/js/components/SYSTEM/ReportStudio/PanelPratinjau.vue`
- Create: `resources/js/components/SYSTEM/ReportStudio/api/reportStudioApi.js`
- Create: `resources/js/components/SYSTEM/ReportStudio/composables/useDefinisi.js`

**Interfaces:**
- `reportStudioApi(baseUrl)` → `{ katalog(dataset), galeri(), simpan(payload), duplikat(kode), hapus(kode), pratinjau(definisi, signal), jalankan(payload) }`
- `useDefinisi(katalogRef)` → `{ definisi, mode, pilihDimensi, pilihUkuran, pilihKolom, setSaringan, valid }`

- [ ] **Step 1:** Klien API menerima `baseUrl` sebagai argumen — generik, tanpa
  menyebut SOP. `pratinjau()` menerima `AbortSignal`.
- [ ] **Step 2:** `useDefinisi` menyimpan pilihan **kedua mode sekaligus**;
  berpindah Ringkasan↔Rinci tidak boleh membuang pilihan mode satunya.
- [ ] **Step 3:** `PanelDimensiUkuran` memakai `HcisTransfer`. Urutan dimensi
  bermakna (= urutan `GROUP BY`), jadi panel kanan harus bisa diurut ulang;
  bila `HcisTransfer` belum mendukungnya, tambahkan di pembungkus — **jangan**
  menambal komponen design system.
- [ ] **Step 4:** `PanelSaringan` dirender dari `saringan()` katalog, memakai
  `HcisFilterField`.
- [ ] **Step 5:** `PanelPratinjau` — debounce 400 ms, batalkan request berjalan
  saat definisi berubah, tampilkan `catatan` di kaki tabel, dan perlakukan
  `error_code = REPORT_PREVIEW_TOO_HEAVY` sebagai **turun anggun**: tombol
  Jalankan tetap hidup.
- [ ] **Step 6:** Keempat status layar wajib; galat validasi tampil inline di
  panel penyebabnya, bukan sebagai toast.
- [ ] **Step 7: Commit.**

---

### Task 4: Galeri template

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/GaleriTemplate.vue`

- [ ] **Step 1:** Tiga saringan: Resmi / Milik Saya / Dibagikan.
- [ ] **Step 2:** Template resmi berlencana dan **tidak bisa diubah** — hanya
  Jalankan dan "Duplikat & ubah". Itu jalur belajar terpenting fitur ini:
  halaman kosong dengan 29 dimensi membuat orang menyerah.
- [ ] **Step 3:** Simpan, duplikat, dan nonaktifkan tersambung ke API.
- [ ] **Step 4: Commit.**

---

### Task 5: Test dan gerbang

**Files:**
- Create: `resources/js/components/SYSTEM/ReportStudio/ReportStudio.test.js`

- [ ] **Step 1:** Test komponen: panel terbangun dari fixture katalog;
  berpindah mode tidak membuang pilihan; request pratinjau dibatalkan saat
  definisi berubah; keempat status layar dirender.
- [ ] **Step 2:** `npm run test:frontend`
- [ ] **Step 3:** `npm run build` — impor beralias harus resolve.
- [ ] **Step 4:** Lima skrip `scripts/`; bandingkan snapshot route ke baseline.
  Route baru yang diharapkan: **satu** (`GET sop/admin/export-studio`).
- [ ] **Step 5: Commit.**
