# KPI-Unreal Module Registry

Dokumen ini adalah registry resmi payung modul bisnis dan modul teknis KPI-Unreal. Semua agent AI—Hermes, Claude Code, Codex, OpenCode, atau agent lain—wajib membaca dan mengikutinya sebelum membuat file baru atau memindahkan file.

## Sumber kebenaran resmi (database-driven)

Dokumen ini **bukan sumber kebenaran daftar modul, submodul, atau fitur aktif**. Daftar resmi harus dibaca dari database pada environment yang sedang digunakan. Agent tidak boleh menganggap daftar statis di dokumentasi sebagai daftar terbaru.

Sumber kebenaran utama:

```text
N_HRIS_Master_Grup_Fitur
  → master grup fitur/module: Id_Grup_Fitur, Kode_Grup_Fitur, Nama_Grup_Fitur, Flag_Aktif, dan metadata yang tersedia

N_HRIS_Grup_Fitur_Map
  → mapping page/feature aktif ke Id_Grup_Fitur melalui Jenis_page dan metadata menu yang tersedia

N_HRIS_Role_Functional
N_HRIS_Role_User
  → ownership/access scope grup fitur berdasarkan role dan user
```

Kolom aktual wajib diperiksa dari schema/database sebelum query baru dibuat. Nama kolom di atas adalah kolom yang telah dipakai oleh implementasi existing; jangan mengarang kolom status, hierarchy, atau parent-child yang belum diverifikasi.

Aturan precedence:

1. Database master dan mapping aktif adalah authority untuk kondisi terbaru.
2. Authorization/role tables menentukan siapa yang dapat melihat atau mengelola feature.
3. Source code menentukan implementasi teknis aktual.
4. Dokumentasi menjadi aturan proses, definisi boundary, dan snapshot/mapping yang harus diberi tanggal—bukan pengganti database.

Prosedur wajib sebelum membuat atau memindahkan file baru:

1. Baca `N_HRIS_Master_Grup_Fitur` untuk memperoleh grup fitur aktif terbaru.
2. Cari page/feature terkait pada `N_HRIS_Grup_Fitur_Map` berdasarkan `Jenis_page` dan/atau URL yang diverifikasi.
3. Periksa role/ownership bila task menyentuh authorization atau placement yang dikelola role.
4. Cocokkan hasil database dengan source code dan dokumentasi.
5. Jika tidak ada mapping atau hasilnya ambigu, berhenti dan minta keputusan; jangan menebak dari nama role atau folder.

Jika database tidak tersedia, agent boleh melanjutkan pekerjaan dokumentasi umum, tetapi tidak boleh menyatakan module/feature tersebut resmi dan terbaru. Untuk pekerjaan implementasi yang membutuhkan ownership, database verification adalah prerequisite.

## Struktur kepemilikan

```text
Modul utama
└── Submodul
    └── Fitur
        └── Layer teknis
```

Role pengguna (`User`, `Admin`, `Super Admin`) tidak menentukan modul. Pemilik proses bisnis yang menentukan modul.

Contoh:

```text
Super admin mengatur workflow SOP
→ SOP/Settings/Workflow

Admin mengatur approval KPI
→ KPI/Settings/Approval

Super admin mengatur role dan permission
→ SYSTEM/Governance/Access
```

## Snapshot terverifikasi — 2026-08-26

Dibaca read-only dari `N_HRIS_Master_Grup_Fitur` pada database `Web_HRIS`
(host development team311, `APP_ENV=local`). Snapshot bertanggal, bukan
pengganti database.

```text
Id  Kode      Nama                          Flag_Aktif
 1  ESS       Employee Self Service         Y
 2  KPI       Key Performance Indicator     Y
 3  LMS       Learning Management System    Y
 4  VISITOR   Visitor Management System     Y
 5  SYSTEM    System                        Y
 6  BUILDER   Module Builder                Y
 7  DMS       Document Management System    Y
```

Jumlah page per grup dari `N_HRIS_Grup_Fitur_Map` (dibaca ulang 2026-08-26):
ESS 30, KPI 20, LMS 7, VISITOR 6, SYSTEM 3, DMS 13.

Catatan penting dari evolusi struktur modul:

- **Tidak ada grup `HRIS`.** Modul kepegawaian terdaftar sebagai `ESS`.
- **Modul `DMS` (Document Management System).** Sejak `DML-2026-044` (2026-08-26),
  seluruh fungsionalitas dan administrasi dokumen/SOP resmi dipisahkan dari `LMS`
  menjadi modul mandiri `DMS` (`Id_Grup_Fitur = 7`).
  Tiga belas page dokumen yang kini berada di bawah payung `DMS`:
  `menuSopSayaPage`, `menuSopRepositoryPage`, `menuSopKategoriPage`,
  `menuSopJenisDokumenPage`, `menuSopDokumenPage`, `menuSopApqcPage`,
  `menuSopBuilderPage`, `menuSopPenjadwalanPage`, `menuSopMonitoringPage`,
  `menuSopKebijakanPage`, `menuSopKontenPage`, `menuSopExportStudioPage`,
  `menuSopSetupSystemPage`.
  Modul DMS memiliki 2 header menu:
  1. `DMS` (`dms`): `Functional Document` dan `Public Document`.
  2. `Administrator DMS` (`administrator_dms`): 11 menu administrasi dokumen.
- Modul `LMS` kini murni menaungi manajemen training dan pembelajaran (7 page).

- **Halaman `SYSTEM` tidak memakai `check.jabatan`.** Diverifikasi 2026-08-22:
  grup fitur SYSTEM (`Id_Grup_Fitur = 5`) tidak dipegang satu pun role di
  `N_HRIS_Role_Functional`, dan halaman SYSTEM yang sudah ada nol baris di
  `HRIS_Page_Access` — ketiganya: `systemDatabaseChangeDashboardPage`,
  `systemMasterEntitasPage`, `systemMasterDepartemenPage`.
  Gerbangnya middleware `system.admin`
  (`KPI_Users.Is_System_Admin = 'Y'`, 1 dari 945 user). Jadi "System Admin" di
  repo ini adalah flag, bukan role — jangan menambahkan grant `HRIS_Page_Access`
  untuk halaman SYSTEM, karena itu justru membuka master lintas modul kepada
  pemegang menu modul tertentu.

## Contoh mapping historis/non-authoritative

Bagian berikut adalah contoh konseptual dan snapshot historis, bukan daftar resmi terbaru. Setiap task wajib memverifikasi database terlebih dahulu.

### Contoh submodul ESS

```text
ESS/
├── Attendance
│   ├── Leave
│   ├── Overtime
│   ├── Shift
│   ├── Calendar
│   ├── Correction
│   ├── Processing
│   └── Legacy
├── Employee
├── Organization
├── Dashboard
├── Activity
└── Workflow
```

Submodul untuk module lain mengikuti data master dan mapping database. Contoh ini tidak boleh dipakai untuk menyimpulkan bahwa submodul tersebut masih aktif, lengkap, atau authoritative. Fitur berada di bawah payung module hasil verifikasi, bukan menjadi folder fitur baru di root.

### Contoh kapabilitas teknis lintas modul

Gunakan grup fitur yang di-resolve dari database untuk menentukan ownership teknis lintas modul. `SYSTEM` hanya digunakan bila master/mapping dan proses bisnisnya memang menunjukkan kapabilitas governance/technical, misalnya:

```text
SYSTEM/
├── Authentication
├── Authorization
├── Governance
├── Application
├── UserPreferences
├── Notification
├── FileStorage
└── Audit
```

Menu, role, permission, access matrix, delegation, dan approval matrix lintas modul berada di `SYSTEM/Governance`. Setting yang hanya mengatur KPI, LMS, SOP, HRIS, atau VISITOR tetap berada di modul bisnis masing-masing.

## Aturan agent dan verifikasi database

Sebelum membuat file baru, agent wajib:

1. Membaca master grup fitur terbaru dari database.
2. Menentukan module dari `Kode_Grup_Fitur`/`Id_Grup_Fitur` hasil query, bukan dari daftar dokumen.
3. Menentukan submodul dan fitur dari mapping page/feature yang terverifikasi.
4. Menentukan layer file.
5. Memeriksa file existing yang terkait.
6. Mengikuti mapping legacy tanpa memindahkan file lain secara otomatis.

### Jika kepemilikan modul tidak jelas

Jika agent atau developer tidak dapat menentukan fitur tersebut masuk ke modul/submodul yang mana, pekerjaan harus berhenti pada tahap klasifikasi dan meminta keputusan user/maintainer. Agent tidak boleh menebak, membuat folder sementara, atau langsung menciptakan modul baru.

Pertanyaan klasifikasinya harus menjelaskan:

- proses bisnis apa yang diselesaikan fitur;
- siapa pemilik proses bisnisnya;
- apakah fitur memperluas modul existing atau benar-benar domain bisnis baru;
- dampaknya terhadap backend dan frontend.

### Penambahan modul baru

Modul baru hanya dibuat setelah user/maintainer menyetujuinya sebagai domain bisnis atau domain teknis baru. Persetujuan tersebut harus memperbarui registry ini terlebih dahulu, termasuk nama modul, tujuan, batas kepemilikan, dan submodul awalnya.

Setelah disetujui, struktur modul baru wajib konsisten di seluruh layer yang digunakan:

```text
Backend:
app/Http/Controllers/<MODUL>/
app/Http/Requests/<MODUL>/
app/Models/<MODUL>/
app/Services/<MODUL>/
app/Jobs/<MODUL>/
app/Console/Commands/<MODUL>/

Frontend:
resources/js/Pages/<MODUL>/
resources/js/Components/<MODUL>/
resources/js/Composables/<MODUL>/
resources/js/Services/<MODUL>/
resources/js/Stores/<MODUL>/

Routes dan tests:
routes/modules/<MODUL>/
tests/<jenis>/<MODUL>/
```

Tidak semua folder wajib dibuat. Hanya layer yang benar-benar diperlukan oleh fitur yang boleh dibuat. Penambahan modul harus diperlakukan sebagai perubahan arsitektur kecil: registry, inventory/mapping, aturan agent, dan dokumentasi terkait harus diperbarui sebelum implementasi berjalan.

Jika kepemilikan modul ambigu, agent harus berhenti dan meminta keputusan; jangan membuat folder `Misc`, `General`, atau `Admin` sebagai tempat sementara.

## Ketentuan frontend

Frontend mengikuti struktur Vue/Inertia yang familiar tanpa `app/Modules` dan tanpa Tailwind. Semua entry point halaman baru berada di `resources/js/Pages/<MODUL>/<Submodul>/<Fitur>/`. Page baru memakai nama deskriptif seperti `SopExportListPage.vue`, bukan `index.vue`, `Create.vue`, atau nama generik lainnya.

Komponen khusus fitur berada dekat dengan page. Komponen reusable lintas fitur berada di `resources/js/Components/`; layout berada di `resources/js/Layouts/`; composable reusable berada di `resources/js/Composables/`; store, service, utility, dan type hanya dibuat bila diperlukan. Bootstrap dipakai terutama untuk grid, sementara styling dan design system menggunakan CSS Vanilla. Detail lengkap ada di `docs/architecture/frontend-architecture.md`.

## Ketentuan generator

`make:fullcontroller` adalah alat bantu generator, bukan pengecualian dari registry. Pada saat command diperbarui, input harus mampu menyatakan modul, submodul, fitur, dan developer; output harus tetap berada pada base folder Laravel seperti `app/Http/Controllers/<MODUL>/...` dan `routes/<MODUL>/...`. Command tidak boleh membuat modul baru yang belum disetujui dan tidak boleh membuat `app/Modules`. Spesifikasi generator berada di `docs/architecture/make-fullcontroller-definition.md`.

## Batas fase definisi

Dokumen ini menetapkan aturan target saja. File legacy yang salah tidak dipindahkan, di-rename, atau diperbaiki dalam fase definisi. Implementasi generator, migrasi backend, migrasi frontend, perubahan route loader, dan refactor namespace dilakukan dalam sesi terpisah dengan scope dan verifikasi masing-masing.

