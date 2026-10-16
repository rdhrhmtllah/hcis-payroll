# DML-2026-085 — Header dan delapan halaman master Admin PA

## Change record

| Field | Value |
|---|---|
| Change ID | DML-2026-085-menu-admin-performance-appraisal |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Akses |
| Status | TRACKER_PENDING |
| Artifact revision | r1 |
| Dependencies | DML-2026-084 |
| DML | database/dml/DML-2026-085-menu-admin-performance-appraisal/data-change.sql |
| DML checksum SHA-256 | e6f1b43ae98a71a9cfd8f713f612b33c06e67b336996111209e5d09fc95d7393 |

## Scope

- Memindahkan `PerformanceAppraisalPage` dari header `manajemen_kpi` ke header
  `performance_appraisal` (sort 6). **Dipindahkan, bukan digandakan.**
- Menambah delapan halaman master di header `administrator_pa`
  ("Administrator Performance Appraisal", sort 11).
- Menyambungkan kedelapan ke grup fitur KPI, dan memberi akses eksplisit ke dua user
  pengembang (16 baris).

## Kenapa delapan kunci halaman, bukan satu

`Jenis_page` memang unik sehingga satu kunci hanya bisa menjadi satu baris menu —
tetapi itu bukan alasan utamanya.

**Master PA tidak setara bahayanya.** Menyunting nama kompetensi menyentuh satu layar.
Menyunting **pita grade mengubah arti nilai semua orang** pada siklus berikutnya. Satu
kunci untuk semuanya berarti siapa pun yang butuh yang pertama otomatis mendapat yang
kedua.

Penjagaannya dua lapis, dan keduanya menjawab pertanyaan yang berbeda:

| Lapis | Di mana | Menjawab |
|---|---|---|
| Rute | `check.jabatan` dengan kedelapan kunci (OR) | "boleh masuk ruangannya" |
| Controller | `pastikanBolehMaster()` per master | "boleh membuka lemari yang mana" |

Tanpa lapis kedua, siapa pun yang boleh menyunting kompetensi bisa mengubah pita grade
lewat alamat yang tinggal diketik.

## Wajib sesudah menjalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

## Agent completed

- Menjalankan DML pada development `Web_HRIS`: 8 menu, 8 peta grup fitur, 16 akses,
  1 pemindahan header.
- Menjalankan `flushMenuCache()`.
- Verifikasi di dalam skrip menuntut jumlah 8/8 dan **melempar bila nol akses eksplisit
  terbentuk** — nol akses berarti kedelapan halaman 403 untuk semua orang.
- Rerun exact no-op.

## User must do

- Mencatatkan change ke SQL Tracker dan menjalankannya ke production.
- **Memutuskan siapa yang berhak atas tiap master di production.** Dua user pengembang di
  sini tidak dimaksudkan ikut ke production. Pemisahan delapan kunci baru berguna kalau
  pemberiannya memang dibedakan — kalau semuanya diberikan bersamaan, pemisahannya hanya
  menambah pekerjaan tanpa menambah perlindungan.
- Menjalankan `flushMenuCache()` di production setelah barisnya masuk.
