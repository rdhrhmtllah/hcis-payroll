# DDL-2026-148-system-panduan-sasaran-role

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-148-system-panduan-sasaran-role/forward.sql` (sha256 `e0650d74471617cfb911cc82be391d027dbb125ff1d575ea0ccfc2aa2a907536`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_HRIS_Panduan_Role` + indeks `IX_HRIS_Panduan_Role_Role`, `UX_HRIS_Panduan_Role_Unik`
- **Bergantung pada:** `DDL-2026-146-system-panduan-manual` (FK ke `N_HRIS_Panduan`)
- **Pemilik:** SYSTEM › Application › Panduan (`PanduanService`)

## Status

`DEV_EXECUTED` 2026-09-29 (Web_HRIS dev): 3 batch OK, idempoten saat diulang;
`tests/Feature/SYSTEM/Application/PanduanTest.php` 20 OK. SQL Tracker dan production
masih `PENDING`.

Setelah dijalankan di dev, komentar `forward.sql` diperbarui mengikuti makna akhir
tabel (lihat di bawah). Pernyataan SQL-nya **identik**; checksum di atas adalah versi
berkomentar baru.

## Tujuan

Keputusan user 29 Sep 2026: panduan **wajib** dipasang di minimal satu halaman, dan
**opsional** dibatasi ke satu atau beberapa role untuk seluruh panduan.

| Isi tabel ini | Siapa melihat panduan di halaman yang dipilih |
|---|---|
| tanpa baris | semua yang bisa membuka halamannya |
| ada baris | hanya pemegang **salah satu** role tersebut (`N_HRIS_Role_User` aktif) |

Role saja tidak cukup: pengguna tetap harus bisa membuka halamannya. Berkas PDF
(`GET /panduan/{id}/berkas`) mengikuti aturan yang sama.

Kolom `Halaman_Mulai` tidak dipakai (halaman mulai diatur per halaman di
`N_HRIS_Panduan_Halaman`) dan tetap NULL. Kolom ini ada karena rancangan awal DDL ini
(sasaran role tersendiri) dibatalkan setelah tabel dibuat di dev.

## Perilaku bila tabel belum ada

Pemetaan halaman tetap berjalan penuh; isian "Batasi ke role" di form nonaktif, dan
panduan tampil tanpa pembatas role.

## Tugas user

1. Jalankan `forward.sql` lewat SQL Tracker di production **setelah** DDL-2026-146.
