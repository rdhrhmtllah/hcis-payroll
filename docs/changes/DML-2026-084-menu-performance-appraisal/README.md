# DML-2026-084 — Pendaftaran halaman Performance Appraisal

## Change record

| Field | Value |
|---|---|
| Change ID | DML-2026-084-menu-performance-appraisal |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Akses |
| Status | TRACKER_PENDING |
| Artifact revision | r1 |
| Dependencies | rute `/performance-appraisal` terdaftar di `routes/web.php` |
| DML | database/dml/DML-2026-084-menu-performance-appraisal/data-change.sql |
| DML checksum SHA-256 | 5c38199596eea5d346a58301d2a43c45fa90295585b78673ccf5e85f64eca8b9 |

## Scope

Mendaftarkan `PerformanceAppraisalPage` ke tiga tabel yang bersama-sama menentukan
akses halaman: `N_HRIS_Menus` (halamannya ada), `N_HRIS_Grup_Fitur_Map` (jalur role,
disambungkan ke grup fitur KPI), dan `HRIS_Page_Access` (jalur warisan, akses eksplisit
per user).

## Kenapa ketiganya sekaligus

Halaman baru yang hanya punya rute menjawab **403 untuk semua orang, termasuk
pembuatnya**. Yang menentukan akses bukan rutenya melainkan `Jenis_page`, dan
`CheckJabatan` membacanya melalui dua jalur — role dan akses eksplisit. Melewatkan satu
tabel menghasilkan halaman yang ada tetapi tidak bisa dibuka siapa pun, dan gejalanya
menyamar sebagai kesalahan rute.

## Kenapa akses eksplisit masih diperlukan

Tidak ada satu pun role aktif yang memuat halaman PA, jadi jalur role belum menghasilkan
akses bagi siapa pun. Dua user pengembang (4 dan 622) diberi akses eksplisit supaya
halamannya bisa diuji. **Pemberian akses kepada pengguna sebenarnya adalah keputusan
pemilik modul, bukan keputusan skrip ini.**

`Kode_Perusahaan`, `ID_Level`, dan `ID_Divisi` disalin dari baris akses milik user itu
sendiri, bukan ditulis tetap. Menuliskannya tetap akan membuat barisnya menunjuk divisi
yang bukan divisinya begitu ia pindah.

## Wajib sesudah menjalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

Tanpa itu barisnya sudah ada di database tetapi menu yang dilihat pengguna masih yang
lama, dan halamannya tetap 403 sampai cache-nya kedaluwarsa sendiri.

## Agent completed

- Menjalankan DML pada development `Web_HRIS`: 1 baris menu, 1 baris peta grup fitur, 2 baris akses.
- Menjalankan `flushMenuCache()`.
- Memverifikasi ketiga baris ada, tidak ada duplikat, dan **melempar bila nol akses
  eksplisit terbentuk** — nol akses berarti halaman tetap 403 untuk semua orang, dan itu
  tidak boleh lolos diam-diam.
- Rerun exact no-op.

## User must do

- Mencatatkan change ini ke SQL Tracker dan menjalankannya ke production.
- Memutuskan siapa yang berhak membuka halaman PA di production, lalu memberikan akses
  lewat role atau `HRIS_Page_Access`. Dua user pengembang di sini **tidak** dimaksudkan
  ikut ke production.
- Menjalankan `flushMenuCache()` di production setelah barisnya masuk.
