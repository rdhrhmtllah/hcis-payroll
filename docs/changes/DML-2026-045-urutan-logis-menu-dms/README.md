# DML-2026-045 — Penataan Urutan Logis Menu Modul DMS Sesuai Alur Kerja Admin

**Target development:** `Web_HRIS` @ team311 · **Status:** DEV_DONE · SQL Tracker PENDING · Production PENDING

## Latar Belakang & Alur Logis

Perubahan ini menata ulang urutan menu (`Sort_Menu` pada `N_HRIS_Menus` dan `Sort_Order` pada `N_HRIS_Grup_Fitur_Map`) agar mengikuti tahapan kerja yang alami dan runut bagi admin/document controller dari hulu ke hilir.

### 5 Tahapan Siklus Pengelolaan Dokumen DMS:
1. **Master & Taksonomi (Setup Dasar):**
   - Mendefinisikan hierarki bentuk dokumen, kelompok bisnis, dan proses standar sebelum membuat dokumen.
2. **Registrasi & Penyusunan Dokumen (Authoring & Drafting):**
   - Mendaftarkan nomor/identitas dokumen, merancang konten/versi/approval flow di Builder, serta mengelola lampiran di Library Konten.
3. **Distribusi & Penugasan (Distribution & Automation):**
   - Menentukan jadwal penugasan pembacaan/sosialisasi dan mengatur otomasi berbasis aturan.
4. **Pengawasan & Pelaporan (Monitoring, Audit & Reporting):**
   - Memantau kepatuhan karyawan dan mengekspor laporan/matriks audit kustom.
5. **Tata Kelola Sistem (System Settings):**
   - Mengatur toleransi jatuh tempo, threshold peringatan, dan penjadwal background.

---

## Urutan Menu Hasil Penataan

### Header 1: DMS (`Header_Code = 'dms'`)
| Sort | `Jenis_page` | URL | Nama Menu | Subtitle |
|---|---|---|---|---|
| 1 | `menuSopSayaPage` | `/sop/saya` | **Functional Document** | Dokumen fungsional & SOP penugasan saya |
| 2 | `menuSopRepositoryPage` | `/sop/repository` | **Public Document** | Repositori seluruh dokumen publik |

### Header 2: Administrator DMS (`Header_Code = 'administrator_dms'`)
| Sort | `Jenis_page` | URL | Nama Menu | Fase / Alur Logis |
|---|---|---|---|---|
| 1 | `menuSopJenisDokumenPage` | `/sop/admin/jenis-dokumen` | **Jenis Dokumen** | *1. Setup Hierarki Mutu (Kebijakan - Formulir)* |
| 2 | `menuSopKategoriPage` | `/sop/admin/kategori` | **Kategori Dokumen** | *1. Setup Kategori & Taksonomi* |
| 3 | `menuSopApqcPage` | `/sop/admin/apqc` | **Master APQC** | *1. Setup Standar Proses Bisnis APQC* |
| 4 | `menuSopDokumenPage` | `/sop/admin/dokumen` | **Master Dokumen** | *2. Registrasi Master Dokumen & Kode* |
| 5 | `menuSopBuilderPage` | `/sop/admin/builder` | **Document Builder** | *2. Perancangan Konten, Versi, & Flow Approval* |
| 6 | `menuSopKontenPage` | `/sop/admin/konten` | **Library Konten** | *2. Pengelolaan Lampiran & Aset Media* |
| 7 | `menuSopPenjadwalanPage` | `/sop/admin/penjadwalan` | **Penjadwalan Dokumen** | *3. Penjadwalan Penugasan & Sosialisasi* |
| 8 | `menuSopKebijakanPage` | `/sop/admin/kebijakan` | **Otomasi Dokumen** | *3. Aturan Penugasan Otomatis Sistem* |
| 9 | `menuSopMonitoringPage` | `/sop/admin/monitoring` | **Monitoring Dokumen** | *4. Pemantauan Kepatuhan & Status Baca* |
| 10 | `menuSopExportStudioPage` | `/sop/admin/export-studio` | **Export Studio** | *4. Studio Pembuatan Laporan & Audit* |
| 11 | `menuSopSetupSystemPage` | `/sop/admin/setup-system` | **Kebijakan Global Dokumen** | *5. Konfigurasi Guardrail & Cron Scheduler* |

---

## Eksekusi & Verifikasi Development

- Target: `Web_HRIS`
- Eksekusi DML via SQL Server Transaction (ACID compliant).
- `InertiaShellService::flushMenuCache()` dijalankan.
- Verifikasi pembacaan menu di browser membuktikan urutan menu dari atas ke bawah 100% konsisten.

## Tugas User Selanjutnya

1. Review urutan menu di antarmuka (HMR aktif).
2. Daftarkan dan jalankan forward DML (`database/dml/DML-2026-045-urutan-logis-menu-dms/data-change.sql`) ke staging/production via SQL Tracker.
