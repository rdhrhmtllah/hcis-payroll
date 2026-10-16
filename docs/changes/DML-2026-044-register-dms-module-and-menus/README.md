# DML-2026-044 — Registrasi Modul DMS dan Pemindahan Menu Dokumen SOP dari LMS

**Target development:** `Web_HRIS` @ team311 · **Status:** DEV_DONE · SQL Tracker PENDING · Production PENDING

## Latar Belakang & Tujuan

Sebelumnya, seluruh fungsionalitas dan administrasi dokumen SOP berada di bawah payung modul LMS (`Id_Grup_Fitur = 3`).
Perubahan ini memisahkan SOP menjadi modul mandiri berlabel **DMS (Document Management System)** (`Kode_Grup_Fitur = 'DMS'`), dengan restrukturisasi 2 grup header sidebar:
1. **DMS** — Fungsional untuk pengguna umum (`Functional Document` dan `Public Document`).
2. **Administrator DMS** — Manajemen dan konfigurasi dokumen untuk administrator (11 menu administrasi).

Selain itu, penamaan menu diselaraskan dari istilah spesifik "SOP" menjadi istilah yang lebih umum dan berstandar enterprise: "Document / Dokumen".

## Struktur Header & Menu DMS

### Header 1: DMS (`Header_Code = 'dms'`, `Sort_Header = 1`)
| `Jenis_page` | URL | Nama Menu Lama | Nama Menu Baru | `Sort_Menu` | `Is_Global_Menu` | Subtitle |
|---|---|---|---|---|---|---|
| `menuSopSayaPage` | `/sop/saya` | SOP Saya | **Functional Document** | 1 | Y | Dokumen fungsional & SOP penugasan saya |
| `menuSopRepositoryPage` | `/sop/repository` | Repository SOP | **Public Document** | 2 | Y | Repositori seluruh dokumen publik |

### Header 2: Administrator DMS (`Header_Code = 'administrator_dms'`, `Sort_Header = 2`)
| `Jenis_page` | URL | Nama Menu Lama | Nama Menu Baru | `Sort_Menu` | `Is_Global_Menu` | Subtitle |
|---|---|---|---|---|---|---|
| `menuSopKategoriPage` | `/sop/admin/kategori` | Kategori SOP | **Kategori Dokumen** | 1 | T | Struktur taksonomi & kelompok dokumen |
| `menuSopJenisDokumenPage` | `/sop/admin/jenis-dokumen` | Jenis Dokumen SOP | **Jenis Dokumen** | 2 | T | Hirarki mutu: Kebijakan sampai Formulir |
| `menuSopDokumenPage` | `/sop/admin/dokumen` | Master Dokumen SOP | **Master Dokumen** | 3 | T | Deklarasi & manajemen siklus dokumen |
| `menuSopApqcPage` | `/sop/admin/apqc` | Master APQC | **Master APQC** | 4 | T | Klasifikasi proses bisnis dokumen |
| `menuSopBuilderPage` | `/sop/admin/builder` | Builder SOP | **Document Builder** | 5 | T | Rancang & terbitkan dokumen |
| `menuSopPenjadwalanPage` | `/sop/admin/penjadwalan` | Penjadwalan SOP | **Penjadwalan Dokumen** | 6 | T | Jadwal tinjauan & penugasan |
| `menuSopMonitoringPage` | `/sop/admin/monitoring` | Monitoring SOP | **Monitoring Dokumen** | 7 | T | Pemantauan kepatuhan dokumen |
| `menuSopKebijakanPage` | `/sop/admin/kebijakan` | Otomasi SOP | **Otomasi Dokumen** | 8 | T | Aturan penugasan & riwayat eksekusi |
| `menuSopKontenPage` | `/sop/admin/konten` | Library Konten | **Library Konten** | 9 | T | Penyimpanan aset & lampiran dokumen |
| `menuSopExportStudioPage` | `/sop/admin/export-studio` | Export Studio | **Export Studio** | 10 | T | Rakit laporan dokumen kustom |
| `menuSopSetupSystemPage` | `/sop/admin/setup-system` | Kebijakan Global SOP | **Kebijakan Global Dokumen** | 11 | T | Guardrail kepatuhan & jadwal otomasi DMS |

## Perubahan Objek Basis Data

1. **`N_HRIS_Master_Grup_Fitur`**:
   - Menambahkan baris modul baru `Kode_Grup_Fitur = 'DMS'`, `Nama_Grup_Fitur = 'Document Management System'`, `Icon_Grup_Fitur = 'bi bi-folder2-open'`, `Sort_Order = 5`, `Landing_Jenis_Page = 'menuSopSayaPage'`.
2. **`N_HRIS_Menus`**:
   - Memperbarui 13 baris menu terkait (`Header_Code`, `Header_Label`, `Header_Icon`, `Nama_Menu`, `Menu_Subtitle`, `Sort_Header`, `Sort_Menu`).
3. **`N_HRIS_Grup_Fitur_Map`**:
   - Memindahkan 13 pemetaan `Jenis_page` dari `Id_Grup_Fitur = 3` (LMS) ke `Id_Grup_Fitur` milik DMS.
4. **`N_HRIS_Role_Functional`**:
   - Menambahkan fungsional role `DMS_MENU_ADMINISTRATOR` dan `DMS_APPROVAL_ADMINISTRATOR` yang dipetakan ke modul DMS.
5. **`N_SYSTEM_Database_Change`**:
   - Mendaftarkan entri pelacakan `DML-2026-044-register-dms-module-and-menus`.

## Eksekusi & Verifikasi Development

- Target: `Web_HRIS`
- Eksekusi DML via SQL Server Transaction (ACID compliant).
- Cache menu dibersihkan (`Cache::flush()`).
- Verifikasi pembacaan menu di `InertiaShellService`: Modul DMS aktif, memuat 2 grup header dan 13 item menu sesuai hak akses.

## Tugas User Selanjutnya

1. Review perubahan menu dan modul DMS.
2. Jalankan forward DML (`database/dml/DML-2026-044-register-dms-module-and-menus/data-change.sql`) ke staging/production via SQL Tracker.
3. Verifikasi hasil di production lalu perbarui status pelacakan menjadi `PRODUCTION_EXECUTED`.
