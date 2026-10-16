# DML-2026-174-menu-pa-header-system-admin

## Metadata

- **Change ID**: `DML-2026-174-menu-pa-header-system-admin`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Revision**: `r1`
- **DML Checksum (SHA-256)**: `9de38fd2607a414dffb5f57fce549e0ccaed0e1fd54254afa7736e5a335869df`
- **Affected Objects**: `N_HRIS_Menus` (1 baris: `PaMasterAturanPenilaiPage`, kolom header & Sort_Menu)
- **Status**: `TRACKER_PENDING`
- **Paths**: `database/dml/DML-2026-174-menu-pa-header-system-admin/data-change.sql`

## Deskripsi

Menu "Master · Komposisi Penilai" (`/performance-appraisal/master/aturan-penilai`) dipindah ke header baru
**System Admin PA** (`system_admin_pa`, ikon `bi bi-shield-lock-fill`, Sort_Header 12). Hak akses halaman
tidak diubah. Pasangan kode: `PaJenisPenilaiMatriksService::setelSel` mengisi jenis relasi otomatis dari kode
kategori (ATASAN/PEER/BAWAHAN; SELF kosong), sehingga admin PA cukup memakai Jenis Penilai.

## Eksekusi development

- 2026-10-08, `Web_HRIS @ instance-20240809-031237`: 1 baris diperbarui; dijalankan ulang no-op;
  `flushMenuCache()`; sidebar terverifikasi (header System Admin PA berisi Komposisi Penilai).

## User tasks

1. Catat `data-change.sql` persis di SQL Tracker (checksum di atas), jalankan ke production, lalu `flushMenuCache()`.
