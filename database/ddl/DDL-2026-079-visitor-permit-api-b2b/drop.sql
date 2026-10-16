-- Change ID: DDL-2026-079-visitor-permit-api-b2b
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Menjatuhkan kolom di bawah menghapus jendela berlaku dan identitas pemegang
-- SELURUH permit mitra yang terlanjur terbit — termasuk yang masih aktif di
-- gerbang — dan mengembalikan indeks idempotensi ke bentuk yang bisa ditabrak
-- antar mitra.
--
-- Indeks idempotensi dikembalikan ke definisi aslinya persis: unik, terfilter
-- Idempotency_Key IS NOT NULL, kunci (Kode_Karyawan_Pemohon, Idempotency_Key).

DROP INDEX IF EXISTS IX_VISITOR_Permit_Api_Aplikasi ON N_EMI_VISITOR_Transaksi_Permit;
GO

DROP INDEX IF EXISTS UX_VISITOR_Permit_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Permit;
GO

CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
    ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Idempotency_Key)
    WHERE Idempotency_Key IS NOT NULL;
GO

ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN IF EXISTS Instansi_Pemegang, Nama_Pemegang;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Permit_Detail DROP COLUMN IF EXISTS Jenis_Identitas_Tamu, No_Identitas_Tamu, Instansi_Tamu, Nama_Tamu, Waktu_Berakhir, Waktu_Mulai_Berlaku;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Permit DROP COLUMN IF EXISTS Referensi_Mitra, Id_Api_Aplikasi, Sumber_Pengajuan;
GO

ALTER TABLE N_HRIS_Api_Aplikasi DROP COLUMN IF EXISTS Kode_Karyawan_PIC;
GO
