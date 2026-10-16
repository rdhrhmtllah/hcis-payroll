# DML-2026-150 — PIC modul ke tabel kontak bantuan

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-150-system-kontak-bantuan-pic-modul` |
| Jenis | DML |
| Target | Web_HRIS development & production (SQL Tracker) |
| Artifact | `database/dml/DML-2026-150-system-kontak-bantuan-pic-modul/data-change.sql` (sha256 `5785b1ee5355622befafd6bf899444333db21b4c05da9a2e374da05a8af8c6c5`) |
| Bergantung pada | `DDL-2026-144-system-master-kontak-bantuan` |

## Status

`DEV_EXECUTED` 2026-09-29 (Web_HRIS dev): 4 baris, Id 34–37; dijalankan ulang tanpa
duplikat. SQL Tracker dan production masih `PENDING`.

## Tujuan

Permintaan user 29 Sep 2026: semua kontak di Pusat Bantuan dikelola di satu tabel
(`N_HRIS_Master_Kontak_Bantuan`). Tampilannya juga tidak lagi per baris kontak,
tetapi per kelompok modul · fitur ("Bantuan HCIS", "Bantuan VMS · Permit", …). Diklik,
kelompok itu membuka modal daftar kontaknya.

Empat PIC yang dulu tertulis di kode (`InertiaShellService::buildHelpItems`, kini
dihapus) dipindah ke tabel:

| Kode_Grup_Fitur | Kode_Fitur | Nama_Kontak | Email |
|---|---|---|---|
| ESS | UMUM | PIC HCIS | hc.ops@evomanufacturingindonesia.co.id |
| KPI | UMUM | PIC KPI | learning.development@evomanufacturingindonesia.co.id |
| LMS | UMUM | PIC LMS | learning.development@evomanufacturingindonesia.co.id |
| SYSTEM | IT | PIC IT HCIS | it.hcis@evomanufacturingindonesia.co.id |

`Kode_Fitur = UMUM` berarti bantuan modul secara umum (judul "Bantuan <modul>" tanpa
label fitur).

## Sebelum DML ini dijalankan

`KontakBantuanService::CADANGAN` berisi keempat PIC yang sama dan dipakai hanya untuk
kelompok yang **belum punya baris sama sekali** di tabel. Baris nonaktif ikut dihitung,
jadi kelompok yang sengaja dimatikan admin tidak hidup lagi lewat cadangan. Pusat Bantuan
tidak pernah kosong di antara deploy kode dan DML.

## Idempoten

Setiap INSERT dijaga `IF NOT EXISTS` pada lingkup + email; dijalankan ulang tidak
menambah baris.
