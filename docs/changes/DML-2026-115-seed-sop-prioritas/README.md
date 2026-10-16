# DML-2026-115 — Seed master `N_SOP_Prioritas` di production

| | |
|---|---|
| Change ID | `DML-2026-115-seed-sop-prioritas` |
| Tipe | DML (INSERT saja) |
| Target | `emi_db_web_hr` (production) |
| Development | `Web_HRIS` — no-op, ketiga baris sudah ada |
| Status | `REVIEW_REQUIRED` |
| Artifact | `database/dml/DML-2026-115-seed-sop-prioritas/data-change.sql` |
| Verifikasi | `database/dml/DML-2026-115-seed-sop-prioritas/verify.sql` |
| Checksum data-change (sha256) | `2e7f1003b7656b627c1c76964f291f6cbc0f915fd2abf7566356440e6c26467b` |
| Checksum verify (sha256) | `1df8c29dcf0f51b25daef10bb6066ddc326a4e3dbcb0994acfbfa4587e5b7a7c` |
| Sumber temuan | `docs/audit/daftar-temuan-sop-production-2026-09-16.md` (Daftar 5) |

## Masalah

`dbo.N_SOP_Prioritas` di production berisi **0 baris**, sementara development
punya **3 baris**. Ini satu-satunya master SOP yang kosong; master lain
(`N_SOP_Master_APQC`, `N_SOP_Master_Jenis_Dokumen`, `N_SOP_Master_Tahap_Versi`
beserta syarat dan transisinya) jumlahnya sudah sama persis di kedua sisi.

Kekosongan ini **tidak** menyebabkan HTTP 500. `SopPrioritasService::semua()`
jatuh ke konstanta PHP `self::BAWAAN`
(`app/Services/LMS/SOP/SopPrioritasService.php:37-41`) ketika tabelnya kosong,
jadi layar tetap menampilkan tiga tingkat. Yang hilang adalah kemampuannya sebagai
master: tingkat prioritas buatan admin tidak bisa ada, `Id_SOP_Prioritas` tidak
pernah terbentuk, dan production berbeda dari development secara diam-diam sampai
ada yang mencoba menambah tingkat baru.

## Baris yang dimasukkan

| Kode | Nama | Urutan | Warna | Flag_Aktif |
|---|---|---:|---|---|
| `KRITIS` | Sangat Penting | 10 | `#EF4444` | `Y` |
| `TINGGI` | Tinggi | 20 | `#F59E0B` | `Y` |
| `NORMAL` | Normal | 30 | `#94A3B8` | `Y` |

Ketiganya **sama persis** dengan isi development dan dengan konstanta `BAWAAN` di
`SopPrioritasService`. Karena itu tampilan layar tidak berubah sedikit pun setelah
seed ini dijalankan — yang berubah hanya sumbernya, dari konstanta menjadi master.

## Keputusan dan alasannya

- **`Id_SOP_Prioritas` tidak disalin.** Kolom itu `IDENTITY`; nilai identity
  berbeda antar server, dan `SET IDENTITY_INSERT` akan menabrak counter
  production. Seluruh penjangkaran memakai kunci bisnis `Kode`, yang dijamin unik
  oleh `UQ_N_SOP_Prioritas_Kode` (sudah diverifikasi ada di production).
- **Idempoten lewat `NOT EXISTS`, bukan `MERGE`.** `MERGE` dilarang aturan proyek,
  dan `NOT EXISTS` sudah cukup: menjalankan berkas ini berulang tidak menggandakan
  baris.
- **`INSERT` saja, tanpa `UPDATE`.** Kalau kelak ada baris dengan `Kode` sama tapi
  isi berbeda, berkas ini **tidak** menimpanya — `verify.sql` yang akan menandainya
  `GAGAL` supaya perbedaannya diputuskan manusia, bukan ditimpa diam-diam.
- **`Created_At = GETDATE()`, bukan menyalin cap waktu development.** Baris ini
  memang baru lahir di production; menyalin `2026-08-28` akan memalsukan jejaknya.
- **`Created_By` dibiarkan `NULL`**, sama seperti development: baris ini lahir dari
  seed sistem, bukan dari tindakan seorang pengguna.

## Keamanan

- Tidak ada `UPDATE`, `DELETE`, `MERGE`, `TRUNCATE`, atau `DROP`.
- Seluruhnya dalam satu transaksi dengan `SET XACT_ABORT ON`, ditutup penjaga
  hasil: kalau jumlah baris menyusut atau ketiga `Kode` tidak berjumlah tepat 3
  setelah insert, seluruh transaksi di-`ROLLBACK` dan `RAISERROR` dilempar.
- Ada penjaga `OBJECT_ID('dbo.N_SOP_Prioritas','U') IS NULL` di depan.
- Rollback ada di kaki `data-change.sql` dalam bentuk **terkomentari**, lengkap
  dengan pemeriksaan pemakaian yang harus dijalankan lebih dulu. Jangan dijalankan
  otomatis.

## Verifikasi yang sudah dilakukan (read-only)

- Struktur `N_SOP_Prioritas` dibandingkan dev vs prod: 10 kolom identik; `IDENTITY`
  pada `Id_SOP_Prioritas` di kedua sisi; `PK_N_SOP_Prioritas`,
  `UQ_N_SOP_Prioritas_Kode`, dan `IX_N_SOP_Prioritas_Urutan` ada di kedua sisi.
- Default constraint `DF_N_SOP_Prioritas_Urutan`, `DF_N_SOP_Prioritas_Warna`,
  `DF_N_SOP_Prioritas_Aktif` ada di production. (Teks definisinya terbaca kosong
  oleh login audit karena `VIEW DEFINITION` tidak dipegang — bukan berarti hilang.
  DML ini mengisi ketiga kolom itu secara eksplisit, jadi tidak bergantung pada
  default.)
- **Dry-run predikat**, dijalankan sebagai `SELECT` murni di kedua database:
  `Web_HRIS` → **0 baris akan masuk** (no-op); `emi_db_web_hr` → **3 baris akan
  masuk**, isinya persis tabel di atas.
- Validasi sintaks `SET PARSEONLY ON` terhadap `Web_HRIS`: `data-change.sql` 6
  batch lolos, `verify.sql` 1 batch lolos, 0 gagal.
- Agent **tidak** menjalankan pernyataan tulis apa pun, baik ke production maupun
  development.

## Catatan pelaksanaan

Login audit `srv_team_web` punya `SELECT` tetapi **tidak punya `INSERT`** pada
`N_SOP_Prioritas` (dan pada seluruh tabel `N_SOP_*`). Jalankan DML ini dengan akun
yang berwenang, bukan dengan login itu.

## Pekerjaan user

1. Tinjau dan setujui `data-change.sql`.
2. Opsional: jalankan di `Web_HRIS` untuk membuktikan no-op-nya (`Baris masuk : 0`).
3. Rekam di SQL Tracker — isi harus identik dan checksum harus cocok.
4. Jalankan ke production; baca keluaran `PRINT` (`Baris sebelum : 0`,
   `Baris masuk : 3`, `Baris sesudah : 3`).
5. Jalankan `verify.sql` — harapan **6 periksa, 6 LULUS, 0 GAGAL**.
6. Bersihkan cache aplikasi bila perlu (`SopPrioritasService` memoisasi per proses,
   jadi instance lama masih memakai konstanta sampai proses berganti).
7. Naikkan status mengikuti alur wajib sampai `COMPLETED`.
