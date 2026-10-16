# DML-2026-189-pa-menu-production

| | |
|---|---|
| Change ID | `DML-2026-189-pa-menu-production` |
| Jenis | DML |
| Target | **Production** `emi_db_web_hr`, hanya melalui SQL Tracker |
| Development | Tidak dijalankan (data sumbernya justru development) |
| Berkas | `database/dml/DML-2026-189-pa-menu-production/data-change.sql` |
| Checksum (sha256) | `6003a9d96c31b67b7697f8d3e85b7e77813e2b84c6bd11d7b95af9a2c3b7dffe` |
| Bergantung pada | `DDL-2026-187-pa-skema-lengkap-production`, `DML-2026-188-pa-master-production` |
| Status | REVIEW_REQUIRED |

## Untuk apa

Production belum punya satu pun menu, peta grup fitur, jadwal, ataupun registri endpoint
Performance Appraisal. Change ini memasang keadaan akhir development dalam satu berkas,
menggantikan rangkaian DML menu PA lama yang saling menimpa.

## Isinya

| Bagian | Tabel | Baris |
|---|---|---|
| Butir menu PA (24) + Relasi Organisasi milik System Admin (1) | `N_HRIS_Menus` | 25 |
| Peta grup fitur: 24 ke grup `KPI`, 1 ke grup `SYSTEM` | `N_HRIS_Grup_Fitur_Map` | 25 |
| Jadwal `pa:ingatkan-penilai` (01.45) dan `pa:sapu-tertahan` (tiap jam 07.20–19.20) | `N_HRIS_Scheduler_Configs` | 2 |
| Endpoint callback HC Learn `POST /api/v1/pa/third-party/penilaian/complete` (isi `DML-2026-102`) | `N_LMS_Endpoint_Api`, `N_LMS_Akses_Endpoint`, `N_HRIS_Api_Endpoint`, `N_HRIS_Api_Akses_Endpoint` | 4 |
| Ledger | `N_SYSTEM_Database_Change` | 1 |

Tidak dibawa:

- **Hak akses per pengguna** (`HRIS_Page_Access`): di development berupa hibah ke UserID_Web
  development. Di production hak diberikan admin lewat layar.
- `PaAssignmentPage` (*Assignment Penilai*): tidak punya rute lagi (dicabut `DML-2026-100`).

## Perbedaan production yang ditangani

- **ID identity tidak dibawa.** `Id_Menu` development (97–166) bertabrakan dengan menu production
  (sampai 164); ID diberikan identity production. Tidak ada FK yang merujuk `Id_Menu`
  (`N_HRIS_Menu_Capabilities` merujuk `Jenis_page`, dan tidak punya baris PA).
- **Grup fitur dicari lewat `Kode_Grup_Fitur`**, bukan ID: SYSTEM = 5 di development, 11 di production.
- **Kolom `Section_*` (`DDL-2026-181`) belum ada di production.** Insert hanya memakai kolom yang
  ada di keduanya; bila `Section_Code` sudah ada saat dijalankan, bagian menu diisi lewat SQL
  dinamis. Bila belum, `DML-2026-182` kelak mengisinya dengan aturan yang sama. `Nama_Header`
  (hanya ada di production, nullable) dibiarkan NULL. Aplikasi sudah memeriksa keberadaan kolom
  `Section_Code` sebelum membacanya.
- Default kolom tidak diandalkan: semua kolom NOT NULL diisi eksplisit.
- Endpoint callback memakai grup `KPI` lewat `Kode_Grup_Fitur`, sehingga tetap jalan walaupun
  `DML-2026-182` sudah mengganti nama grupnya (`DML-2026-102` mencarinya lewat nama lama).

## Pengaman

1. Berhenti (`THROW 50189`) bila skema PA belum ada, aplikasi HC Learn tidak ada/aktif di kedua
   registri, atau grup `KPI`/`SYSTEM` tidak ada.
2. **Idempoten**: setiap baris dilewati bila sudah ada (menu per `Jenis_page`, peta per halaman +
   grup, jadwal per `Command`, endpoint per alamat + metode, akses per aplikasi + endpoint).
3. Satu batch, satu transaksi (`XACT_ABORT ON`).

## Verifikasi (agent, 14 Okt 2026)

- Berkas dijalankan di development dalam transaksi yang di-ROLLBACK terhadap salinan kosong tabel
  tujuan, dua kali: dengan kolom `Section_*` dan tanpa (bentuk production). Hasil: 25 menu
  (isinya identik dengan development pada seluruh kolom bersama), peta KPI 24 + SYSTEM 1, tidak
  ada peta tanpa menu, 2 jadwal, endpoint 1/1 di kedua registri, ledger 1. Dijalankan ulang:
  tidak ada baris tambahan. Tidak ada objek tersisa.
- Kompilasi tanpa eksekusi (`SET NOEXEC ON`) di development: OK.
- Production dibaca saja (akun READ ONLY): 0 dari 25 `Jenis_page` sudah ada, tidak ada URL PA,
  0 jadwal `pa:`, 0 endpoint `/api/v1/pa/`, aplikasi HC Learn aktif di kedua registri, grup KPI
  dan SYSTEM ada.

## DML lama yang DIGANTIKAN untuk production

Jangan jalankan di production:

- `DML-2026-084-menu-performance-appraisal`
- `DML-2026-085-menu-admin-performance-appraisal`
- `DML-2026-086-menu-performance-appraisal-saya`
- `DML-2026-087-jadwal-pengingat-penilai-pa`
- `DML-2026-088-label-header-administrator-pa`
- `DML-2026-089-menu-performance-appraisal-tim`
- `DML-2026-090-cabut-menu-master-struktural-pa`
- `DML-2026-091-halaman-fase-siklus-pa`
- `DML-2026-092-tata-ulang-header-menu-pa`
- `DML-2026-093-tata-menu-pa-ikut-mockup`
- `DML-2026-094-pa-master-global-keluar-dari-konfigurasi-cycle`
- `DML-2026-095-pa-halaman-syarat-kompetensi`
- `DML-2026-096-pa-halaman-ikatan-paket-soal`
- `DML-2026-097-menu-relasi-organisasi`
- `DML-2026-098-akses-relasi-organisasi-admin-pa`
- `DML-2026-099-menu-relasi-organisasi-di-modul-kpi`
- `DML-2026-100-cabut-menu-assignment-penilai`
- `DML-2026-101-menu-pa-satu-header-administrator`
- `DML-2026-102-endpoint-callback-pa-terpisah`
- `DML-2026-103-jadwalkan-pa-sapu-tertahan`
- `DML-2026-105-menu-penilaian-tim-leader`
- `DML-2026-107-urutan-menu-pa-mengikuti-alur`
- `DML-2026-110-menu-hasil-kalibrasi-lebur-ke-pemantauan`
- `DML-2026-111-tampilkan-menu-distribusi-performance`
- `DML-2026-112-selaraskan-label-menu-pa`
- `DML-2026-114-hide-menu-kalibrasi-pa`
- `DML-2026-115-label-menu-bobot-kpi-kompetensi`
- `DML-2026-116-menu-pa-export-studio`
- `DML-2026-119-menu-jenis-penilai-pa`
- `DML-2026-159-menu-pa-balancing`
- `DML-2026-160-menu-pa-penutupan-siklus`
- `DML-2026-172-menu-pa-sembunyikan-dash-distribusi`
- `DML-2026-173-menu-pa-urutan-master`
- `DML-2026-174-menu-pa-header-system-admin`
- `DML-2026-178-menu-pa-koreksi-level`
- `DML-2026-186-menu-pa-master-level`

## Sesudah dijalankan

1. Bersihkan cache menu aplikasi (`flushMenuCache`) bila menu belum tampil.
2. Admin memberi hak akses halaman PA ke pengguna/role production.
3. Admin membentuk relasi organisasi lewat layar dari HRIS dan approval KPI.
4. Setelah ujian HC Learn production tersedia, petakan *Master · Matriks Soal*.
