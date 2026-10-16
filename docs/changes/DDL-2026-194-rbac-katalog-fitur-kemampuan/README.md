# DDL-2026-194 — RBAC Standarisasi: Master Fitur Bisnis, Kemampuan Fitur, dan Pemetaan Role Kemampuan

Fondasi deklarasi modular berbasis objek fitur (setara konsep UDO/UDT/UDF) untuk standarisasi otorisasi di seluruh modul HCIS.

## Ringkasan Perubahan

1. **`N_HRIS_Fitur`**: Tabel master fitur bisnis modular. Mengikat kode fitur (misal: `ESS.CUTI`), nama fitur ramah bisnis, halaman antarmuka (`Jenis_Page`), dan relasi alur approval (`Id_Approval_Config`).
2. **`N_HRIS_Fitur_Kemampuan`**: Tabel aksi wewenang nyata dalam fitur (misal: `ESS.CUTI.LIHAT`, `ESS.CUTI.AJUKAN`, `ESS.CUTI.VERIFIKASI`), jenis aksi sistem, serta batas cakupan data yang sah.
3. **`N_HRIS_Role_Kemampuan`**: Tabel pemetaan hak peran ke kemampuan fitur, menggantikan centang matriks teknis manual bagi pengguna awam/Dept Head.

## Objek Terdampak

- `dbo.N_HRIS_Fitur` (CREATE TABLE, PK_HRIS_Fitur, IX_HRIS_Fitur_Grup, IX_HRIS_Fitur_Page)
- `dbo.N_HRIS_Fitur_Kemampuan` (CREATE TABLE, PK_HRIS_Fitur_Kemampuan, FK_HRIS_Fitur_Kemampuan_Fitur, IX_HRIS_Fitur_Kemampuan_Fitur)
- `dbo.N_HRIS_Role_Kemampuan` (CREATE TABLE, PK_HRIS_Role_Kemampuan, UQ_HRIS_Role_Kemampuan, FK_HRIS_Role_Kemampuan_Role, FK_HRIS_Role_Kemampuan_Kemampuan, IX_HRIS_Role_Kemampuan_Role)

## Eksekusi & Idempotensi

Skrip idempoten menggunakan `IF OBJECT_ID ... IS NULL` dan `IF NOT EXISTS (SELECT 1 FROM sys.indexes ...)` sehingga aman dijalankan ulang.

## Rencana Rollback

Gunakan `drop.sql` untuk pembersihan manual jika diperlukan.
