# DML-2026-021 - Koreksi durasi Visitor enam bulan

| Bidang | Nilai |
|---|---|
| Target | `Web_HRIS` development pada `team311.dyndns.info:1433` |
| Status | `TRACKER_PENDING` |
| Deskripsi | Mengoreksi master aktif `6 Bulan` dari angka 3 menjadi 6. |
| Objek/baris | `N_EMI_VISITOR_Master_Durasi_Permit`, tepat Id 3 dengan nilai lama yang terverifikasi |
| DML | `database/dml/DML-2026-021-fix-visitor-durasi-enam-bulan/data-change.sql` |
| Checksum SHA-256 | `d4a62bd7d270fdf00846d28f469e8a60258c9924d9d30c87e06542ac9e8c1134` |

Agent completed: SQL berpredikat ketat dijalankan pada development; tepat 1 baris berubah dan diverifikasi menjadi `6 MONTH` pada 2026-08-23. Remaining user work: pencatatan dan eksekusi production via SQL Tracker, lalu verifikasi.
