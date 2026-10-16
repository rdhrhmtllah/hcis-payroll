# DML-2026-196 — Registrasi Fitur Bisnis & Kemampuan Seluruh Modul (RBAC)

Memperluas pendaftaran master fitur bisnis, kemampuan wewenang, dan pemetaan peran untuk seluruh modul HCIS (`VISITOR`, `DMS`, `KPI`, dan kelengkapan `ESS`).

## Ringkasan Perubahan

1. **Registrasi 7 Fitur Bisnis Baru (`N_HRIS_Fitur`):**
   - `VISITOR.PERMIT`: Izin Masuk Kendaraan (Permit)
   - `VISITOR.TAMU`: Kunjungan Tamu & Vendor
   - `DMS.DOKUMEN`: Manajemen SOP & Dokumen
   - `KPI.PERFORMANCE`: Penilaian Kinerja & Evaluasi KPI
   - `ESS.ABSENSI`: Kehadiran & Absensi Karyawan
   - `ESS.LEMBUR`: Penugasan & Konversi Lembur
   - `ESS.SHIFT`: Pengaturan & Pergantian Shift

2. **Registrasi 25 Kemampuan Fitur Baru (`N_HRIS_Fitur_Kemampuan`):**
   - Mendefinisikan aksi wewenang nyata, jenis aksi sistem, serta batas cakupan data yang sah.

3. **Pemetaan ke 25 Peran Aktif (`N_HRIS_Role_Kemampuan`):**
   - Memetakan peran Super Admin, Admin Modul, Verifikator Alur Approval, dan Pelaksana Operasional ke kemampuan masing-masing.

## Eksekusi & Idempotensi

Skrip idempoten menggunakan `MERGE` dan `NOT EXISTS`, aman dijalankan berulang.
