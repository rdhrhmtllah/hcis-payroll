# DDL-2026-082 — Jejak pembatalan penugasan PA

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-082-performance-appraisal-assignment-cancellation |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Assignment |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependency | DDL-2026-075-performance-appraisal-assignment |
| Forward DDL | database/ddl/DDL-2026-082-performance-appraisal-assignment-cancellation/forward.sql |
| Manual reset | database/ddl/DDL-2026-082-performance-appraisal-assignment-cancellation/drop.sql |
| DML | database/dml/DDL-2026-082-performance-appraisal-assignment-cancellation/data-change.sql |
| Table documentation | docs/database/tables/kpi/n-pa-assignment.md |
| Forward checksum SHA-256 | b759b15b4c6aadbac595f2779d3becd619ef214065fee85fdab14d46c2f43e2d |
| DML checksum SHA-256 | b4faa48befbbd8a37ace07d448b0b4b7cb84e90bb274d1bbadac9234423ecb33 |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

Menambah `Cancel_Reason`, `Cancelled_At`, dan `Cancelled_By` pada
`N_PA_Assignment`. Ketiga kolom menyimpan alasan, waktu, dan pelaku pembatalan tanpa
menghapus riwayat penugasan. `Alasan_Manual` tidak dipakai ulang karena menjelaskan
alasan penugasan dibuat, bukan alasan penugasan dicabut.

`Cancelled_By` berisi `KPI_Users.Id_Users` yang diterima service dari pengguna aktif.
Relasi ini tidak dijadikan foreign key agar jejak audit tetap terbaca jika akun berubah
atau dinonaktifkan.

## Agent completed

- Menjalankan forward DDL dan DML pada development `Web_HRIS`.
- Memverifikasi 22 kolom serta dua CHECK pembatalan aktif dan trusted.
- Implementasi `PaAssignmentBuilderService::batalkan()` mengunci penugasan, menolak
  penugasan selesai/nonaktif, mengisi tiga jejak pembatalan, lalu menghitung ulang bobot.
- SQL Tracker belum direkam dan production belum dijalankan.

## User tasks

- Review SQL dan checksum di atas.
- Rekam artefak yang sama persis di SQL Tracker.
- Jalankan manual ke production setelah DDL-2026-075, lalu verifikasi hasilnya.
