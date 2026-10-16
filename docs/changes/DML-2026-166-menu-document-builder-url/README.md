# DML-2026-166 — Menu Document Builders menunjuk ke rute yang benar

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-166-menu-document-builder-url` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Artifact | `database/dml/DML-2026-166-menu-document-builder-url/data-change.sql` (sha256 `d9460a7751db78c2bebda5f1a32ed8a222b96cd0cd0882936c32993c940f20f9`) |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Latar

Menu sidebar **Document Builders** di `N_HRIS_Menus` menyimpan
`Url_Menu = '/sop/admin/builders'` (jamak). Rutenya `sop/admin/builder`
(`sop.admin.builder`, `routes/LMS/SOP/Governance/SopGovernance.php`), jadi setiap
klik dari sidebar berbalas 404. Semua tautan lain di aplikasi (Master Dokumen,
palet monitoring, dashboard konten) sudah memakai bentuk tunggal.

Branch `feat/dms/responsive-ui` (commit `8c9a324d`, 9 Okt 2026) menambah redirect
301 `builders` → `builder`. Itu pengaman yang baik, tetapi hanya menutupi gejala
dan belum masuk `master`. DML ini memperbaiki sumbernya.

## Cakupan

```text
N_HRIS_Menus  Url_Menu '/sop/admin/builders' → '/sop/admin/builder'
```

Terukur di development (baca saja, 10 Okt 2026): 1 baris, `Id_Menu` 71,
`Kode_Perusahaan` 001. Tidak ada tabel menu/halaman lain yang menyimpan URL ini;
hak akses terikat ke `Id_Menu`, bukan ke URL. Dicocokkan lewat URL karena
`Id_Menu` bisa berbeda antara development dan production.

## Sesudah eksekusi

Sidebar dibangun dari cache `shell.menu-rows.v1:{Kode_Perusahaan}` (TTL 600 detik).
Perubahan terlihat setelah cache kedaluwarsa, atau segera setelah
`App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache()`
dipanggil.

## Idempotensi dan verifikasi

Menjalankan ulang mengubah 0 baris. Kueri verifikasi di akhir `data-change.sql`
harus mengembalikan 0.

## Rollback

Tidak disediakan: nilai lama adalah URL yang tidak pernah berfungsi.

## Pekerjaan

| Siapa | Apa | Status |
|---|---|---|
| Agent | Tulis SQL, dokumentasi, entri `status.json` | Selesai |
| User | Setujui SQL | Selesai (10 Okt 2026) |
| Agent | Jalankan di `Web_HRIS` (development) dan bersihkan cache menu | Selesai (10 Okt 2026): 1 baris, sisa 0 |
| User | Rekam di SQL Tracker, jalankan di production | Menunggu |
