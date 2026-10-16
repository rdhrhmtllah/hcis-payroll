# DDL-2026-045 — Jejak nilai sebelum penjepitan per parameter

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-045-kpi-cap-per-parameter` |
| Jenis | DDL |
| Target development | `Web_HRIS` (host team311) |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/ddl/DDL-2026-045-kpi-cap-per-parameter/forward.sql` |
| Pembersihan manual | `database/ddl/DDL-2026-045-kpi-cap-per-parameter/drop.sql` |
| Checksum forward (SHA-256) | `092882e4114cbb035b01413ecd4ff560685d603285214eef6ce8998dba8e9382` |
| Spec | `docs/superpowers/specs/2026-08-28-kpi-cap-per-parameter-design.md` |
| Rencana | `docs/superpowers/plans/2026-08-28-kpi-cap-per-parameter.md` |

## Latar

Penjepitan skor KPI berpindah dari total akhir ke achievement tiap parameter.
Begitu achievement per parameter dijepit, nilai aslinya lenyap kecuali ada
tempat menyimpannya. Header sudah punya jejak semacam itu (`Total_Score_Raw`,
`Flag_Capped`); tabel detail belum punya satu pun.

## Objek terdampak

| Tabel | Kolom baru |
|---|---|
| `KPI_Update_Performance_Detail` | `Score_Raw`, `Achievement_Raw`, `Flag_Capped` |
| `KPI_Update_Performance_Detail_Sementara` | `Score_Raw`, `Achievement_Raw`, `Flag_Capped` |

| Kolom | Tipe | Null | Default | Isi |
|---|---|---|---|---|
| `Score_Raw` | `float` | ya | — | achievement (%) sebelum dijepit |
| `Achievement_Raw` | `float` | ya | — | skor berbobot sebelum dijepit |
| `Flag_Capped` | `char(1)` | tidak | `'T'` | `Y` bila parameter ini dijepit |

Tipe `float` dipilih agar sama persis dengan kolom pasangannya (`Score` dan
`Achievement`), diverifikasi dari `INFORMATION_SCHEMA.COLUMNS` pada 2026-08-28,
bukan diasumsikan.

Penamaan mencerminkan `Total_Score_Raw`/`Total_Score_Achievement_Raw` di header,
termasuk mewarisi kekeliruan lama bahwa kolom `Score` menyimpan achievement dan
kolom `Achievement` menyimpan skor. Memperbaiki nama di sini akan membuat detail
dan header memakai konvensi berbeda — jebakan yang lebih mahal daripada
kekeliruan yang sudah dikenal.

## Kenapa nullable

Baris lama sengaja **tidak** diisi. Kekosongan itulah pembeda antara record
beraturan lama (dijepit di total) dan record beraturan baru (dijepit per
parameter). Mengisinya dengan nol menghapus pembeda tersebut dan menuliskan
angka yang tidak pernah dihitung.

`Flag_Capped` justru `NOT NULL DEFAULT ('T')` karena pernyataannya benar untuk
baris lama: tidak satu pun parameter lama pernah dijepit di tingkat parameter.

## Dampak baris

| Tabel | Baris saat DDL disusun |
|---|---|
| `KPI_Update_Performance_Detail` | 305 |
| `KPI_Update_Performance_Detail_Sementara` | 365 |

Pengisian default `Flag_Capped` menyentuh seluruh baris tersebut. Ukurannya
sepele meski pada SQL Server Express.

## Aman dijalankan ulang

Setiap `ALTER` dijaga `IF NOT EXISTS` terhadap `sys.columns`, sehingga eksekusi
kedua tidak melakukan apa-apa dan tidak melempar galat.

## Rollback

`drop.sql` disediakan sebagai alat pembersihan manual dan **tidak pernah
dijalankan agen**. Mencabut fitur tidak memerlukan pencabutan kolom: cukup
kosongkan `KPI_PARAM_MAX_ACHIEVEMENT_SCORE` dan `KPI_PARAM_MIN_ACHIEVEMENT_SCORE`
agar penjepitan mati, sementara kolom jejak tetap sah sebagai riwayat.

## Catatan penomoran

Nomor 044 dilewati karena sudah diklaim `DML-2026-044` pada cabang lain
(pemetaan halaman SOP ke grup DMS) yang belum masuk ke worktree ini.

## Pekerjaan agen

- Menyusun `forward.sql`, `drop.sql`, dan dokumentasi ini.
- Menjalankan `forward.sql` ke `Web_HRIS` pengembangan setelah persetujuan
  pengguna, lalu memverifikasi keenam kolom benar-benar ada.
- Mencatat perubahan ini di `database/ddl/status.json`.

## Pekerjaan pengguna

- Meninjau dan menyetujui DDL.
- Mencatat perubahan ke SQL Tracker dengan forward DDL yang identik.
- Menjalankan ke produksi dan memverifikasi hasilnya.
