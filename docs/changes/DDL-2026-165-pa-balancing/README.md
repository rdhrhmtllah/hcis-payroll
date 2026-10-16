# DDL-2026-165-pa-balancing

## Metadata

- **Change ID**: `DDL-2026-165-pa-balancing`
- **Target Database**: `Web_HRIS`
- **Type**: DDL
- **Revision**: `r2`
- **Forward Checksum (SHA-256)**: `905276381b6962681cb89940ad79997b90501fedecb6a604a43b508250cb4275`
- **Affected Objects**: `N_PA_Balancing_Sesi`, `N_PA_Balancing_Forum`, `N_PA_Balancing_Peserta`,
  `N_PA_Balancing_Kompetensi`, `N_PA_Balancing_Kualitatif`, `N_PA_Balancing_Jejak_Hitung`, beserta
  semua PK/FK/DF/CK/UX/IX di `forward.sql`, dan `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Rancangan**: `docs/superpowers/specs/2026-09-30-pa-balancing-design.md`
- **Dokumentasi tabel**: `docs/database/tables/kpi/n-pa-balancing-*.md` (enam berkas)
- **Artifacts**: `database/ddl/DDL-2026-165-pa-balancing/forward.sql`, `drop.sql` (bantuan manual, tidak dijalankan agen)

## Deskripsi

Balancing berulang sesudah Calculate. Admin Talent Analyst membuka sesi, mencatat forum,
memilih peserta, menggeser skor per kompetensi (Before/After) dan ringkasan kualitatif, lalu
memfinalkan sesi. Hitung ulang menerapkan After lewat lapisan penyesuaian di mesin hitung
(`N_PA_Result` tidak berubah bentuk), mengunci ulang kurva, dan mencatat jejaknya.

## Keputusan fisik

- Semua objek baru; tidak ada ALTER atas tabel lama.
- Status sesi, status tinjau, dan sebab divalidasi backend, tanpa CHECK (AGENTS.md).
- CHECK hanya untuk rentang angka (bobot 0–1, nilai 0–100, versi/urutan/nomor ≥ 1).
- FK tanpa cascade.

## Agent completed

- Disetujui pemilik proyek 30 Sep 2026, dieksekusi ke `Web_HRIS @ instance-20240809-031237` (target diverifikasi lewat `DB_NAME()` dan `@@SERVERNAME`).
- 15 batch OK: 6 tabel, 14 indeks (6 PK, 3 UX, 5 IX), baris `N_SYSTEM_Database_Change` DDL-2026-165. Dijalankan ulang: no-op.

## User tasks

1. Rekam di SQL Tracker dengan checksum di atas, lalu eksekusi ke production.
2. Verifikasi production.

## Amandemen r2 (2026-10-06)

Dinomori ulang dari `DDL-2026-158-pa-balancing` karena nomornya juga dipakai `DDL-2026-158-sop-versi-lampiran-form` di master. Isi perubahan tidak berubah selain kode change. **Checksum lama `19968405c046dc4d5cf291b0c2ea286c9fb732ea703f3831c32db8ca05058ce3` tidak boleh dicatat di SQL Tracker**; pakai checksum di atas.
