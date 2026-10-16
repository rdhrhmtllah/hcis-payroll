# DDL-2026-167 — Tabel cadangan backfill cap KPI per parameter

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-167-kpi-cadangan-backfill-cap` |
| Jenis | DDL |
| Target development | `Web_HRIS` |
| Forward | `database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/forward.sql` (sha256 `df089b3826eaa489f502b4f1aed9b0a297d14167a3b4243e69ae67c20a17d75a`) |
| Drop | `database/ddl/DDL-2026-167-kpi-cadangan-backfill-cap/drop.sql` (manual) |
| Rancangan | `docs/superpowers/specs/2026-10-12-kpi-backfill-cap-per-parameter-design.md` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Latar

Sejak 28 Agt 2026 skor KPI dijepit per parameter (50–125), tetapi data lama tidak
dihitung ulang. Keputusan user 12 Okt 2026: seluruh header aktif yang belum
ber-`Metode_Cap='PARAMETER'` dihitung ulang lewat endpoint
`POST /api/v1/kpi-enforcement/backfill-cap-parameter` (di-hit dari Postman,
diproses di background).

Total skor dan level performa lama **tidak bisa** dipulihkan dari kolom `_Raw`
saja. Tabel ini menyimpan nilai sebelum diubah supaya batch backfill bisa
di-rollback, dan menjadi penanda potongan yang sudah diproses.

## Isi

Tabel `dbo.N_KPI_Backfill_Cap_Cadangan`:

| Kolom | Isi |
|---|---|
| `Id_Cadangan` | bigint IDENTITY, PK |
| `Batch_Id` | batch backfill/rollback (`kpi-bf-…` / `kpi-bfr-…`) |
| `Jenis` | `HEADER`, `DETAIL`, atau `POTONG` (dijaga `CK_KPI_Backfill_Cap_Jenis`) |
| `Id_Update_Performance` | header; untuk `POTONG` = indeks potongan |
| `Id_Update_Performance_Detail` | detail (hanya `DETAIL`) |
| `Nilai_Lama_Json` | kolom yang akan diubah, sebelum diubah |
| `Dipulihkan_At` | terisi saat baris dipulihkan oleh rollback |
| `Created_At` | waktu dicadangkan |

Indeks `IX_KPI_Backfill_Cap_Batch (Batch_Id, Jenis, Id_Update_Performance)`.

Indeks unik terfilter `UX_KPI_Backfill_Cap_Potong (Batch_Id, Id_Update_Performance)
WHERE Jenis = 'POTONG'`: satu potongan hanya bisa diklaim sekali per batch. Cloud
Tasks yang mengirim ulang potongan yang sudah selesai gagal menyisipkan penanda
dan keluar tanpa mengubah atau menghitung apa pun.

## Urutan rilis

**Wajib dijalankan di produksi sebelum `mode=jalankan` dipakai.** Tanpa tabel ini
endpoint menolak menjalankan backfill (422); pratinjau tetap jalan.

Idempoten: menjalankan ulang `forward.sql` tidak mengubah apa pun.

## Pekerjaan

| Siapa | Apa | Status |
|---|---|---|
| Agent | Tulis DDL, dokumentasi, entri `status.json` | Selesai |
| User | Setujui SQL | Selesai (12 Okt 2026) |
| Agent | Jalankan di `Web_HRIS` (development) | Selesai (12 Okt 2026), idempoten |
| User | Rekam di SQL Tracker, jalankan di production sebelum backfill | Menunggu |
