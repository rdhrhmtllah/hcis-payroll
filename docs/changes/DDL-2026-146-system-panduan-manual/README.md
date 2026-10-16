# DDL-2026-146-system-panduan-manual

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-146-system-panduan-manual/forward.sql` (sha256 `3bf82a860024f67f90f1763b8c76f5487ae5931f386bc4b6e038f75dce729d6a`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_HRIS_Panduan`, `dbo.N_HRIS_Panduan_Halaman` + indeks `IX_HRIS_Panduan_Halaman_Jenis_Page`, `UX_HRIS_Panduan_Halaman_Unik`
- **Pemilik:** SYSTEM › Application › Panduan (`App\Services\SYSTEM\Application\Panduan\PanduanService`)

## Status

`DEV_EXECUTED` 2026-09-29 (Web_HRIS dev): 4 batch OK, idempoten saat diulang;
`tests/Feature/SYSTEM/Application/PanduanTest.php` 13 OK. SQL Tracker dan
production masih `PENDING`.

## Tujuan

Manajemen panduan (manual PDF) lintas modul. Admin sistem mengunggah PDF lalu
memetakannya ke halaman aplikasi (`Jenis_page`). Pengguna membuka panduan dari
Pusat Bantuan (ikon ? di topbar) pada halaman yang dipetakan.

Keputusan user 29 Sep 2026:

- Pemetaan ke **halaman** wajib; modul dan fitur **tidak disimpan**, ikut dari
  halamannya (`N_HRIS_Grup_Fitur_Map` / `N_HRIS_Menus`).
- **Role opsional**, satu pilihan untuk seluruh panduan (tabel
  `N_HRIS_Panduan_Role`, DDL-2026-148). Kolom `Id_Role` per halaman di tabel ini
  tidak diisi oleh form (tetap NULL), tetapi dihormati bila terisi.
- Satu PDF boleh dipetakan ke banyak halaman, masing-masing dengan **halaman
  mulai** di PDF.

## Siapa melihat apa

| Syarat | Sumber |
|---|---|
| Bisa membuka halamannya | `User::memilikiJabatan(Jenis_page)` — hak akses halaman yang sudah ada |
| Role cocok (bila panduan dibatasi role) | `N_HRIS_Panduan_Role` vs `N_HRIS_Role_User` aktif milik pengguna |
| Panduan & pemetaan aktif | `Flag_Aktif = 'Y'` di kedua tabel |

Berkas PDF disajikan lewat `GET /panduan/{id}/berkas` dengan syarat yang sama —
URL berkas penyimpanan tidak pernah dikirim ke peramban.

## Perilaku bila tabel belum ada

Topbar tetap berjalan; bagian "Panduan Halaman Ini" tidak tampil (daftar kosong,
dicatat di log). Halaman Manajemen Panduan menampilkan galat muat.

## Tugas user

1. Jalankan `forward.sql` lewat SQL Tracker di production.
2. Jalankan `DML-2026-147-system-register-panduan-menu` (menu).
3. Unggah panduan di SYSTEM › Aplikasi › Manajemen Panduan.

## Lanjutan

Pembatas role opsional untuk seluruh panduan ditambahkan di
`DDL-2026-148-system-panduan-sasaran-role`.
