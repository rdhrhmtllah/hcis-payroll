# DML-2026-173-menu-pa-urutan-master

## Metadata

- **Change ID**: `DML-2026-173-menu-pa-urutan-master`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Revision**: `r1`
- **DML Checksum (SHA-256)**: `8f0d9d7c6f95237b02d5e58821cac95b9086d9d4ccec7213bc7241f5d908ce0c`
- **Affected Objects**: `N_HRIS_Menus` (8 baris menu master Administrator PA, kolom `Sort_Menu`)
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/dml/DML-2026-173-menu-pa-urutan-master/data-change.sql`

## Deskripsi

Urutan menu master PA di sidebar (permintaan user 8 Okt 2026): 1 Jenis Penilai, 2 Komposisi Penilai,
3 Bobot KPI & Kompetensi, 4 Jenis Kompetensi, 5 Kompetensi, 6 Matriks Kompetensi, 7 Matriks Soal,
8 Skala Nilai & Grade. Menu lain tidak disentuh.

## Eksekusi development

- 2026-10-08, `Web_HRIS @ instance-20240809-031237`: 8 baris diperbarui; dijalankan ulang no-op;
  `flushMenuCache()` dijalankan; urutan sidebar terverifikasi.

## User tasks

1. Catat `data-change.sql` persis di SQL Tracker (checksum di atas), jalankan ke production, lalu `flushMenuCache()`.
