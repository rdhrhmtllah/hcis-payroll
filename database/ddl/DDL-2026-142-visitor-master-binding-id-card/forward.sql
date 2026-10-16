-- Change ID: DDL-2026-142-visitor-master-binding-id-card
-- Master ID Card (kartu NFC) dan pairing-nya ke karyawan/mitra. Dibaca saat
-- pengajuan permit kendaraan: pemohon tanpa ID Card terpasang & tervalidasi
-- tidak bisa mengisi form (KartuNfcPemohon).
--
-- Definisi = SQL yang dijalankan user di development (tabel dibuat manual
-- sebelum berkas ini ada); dibungkus penjaga supaya aman dijalankan ulang.

IF OBJECT_ID('dbo.N_EMI_Master_NFC', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_EMI_Master_NFC (
        id bigint PRIMARY KEY IDENTITY(1,1),
        nfc_hex varchar(30),
        nfc_inbio bigint CHECK (nfc_inbio >= 0),
        flag_validasi_sistem char(1) DEFAULT 'T',
        flag_aktif char(1) DEFAULT 'Y',
        created_by varchar(50) NULL,
        created_at datetime NULL
    );
END;
GO

IF OBJECT_ID('dbo.N_EMI_Binding_NFC', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_EMI_Binding_NFC (
        id bigint PRIMARY KEY IDENTITY(1,1),
        id_nfc bigint NOT NULL,
        nik varchar(30),
        kode_karyawan varchar(50),
        nama varchar(100),
        no_mitra varchar(30),
        flag_aktif char(1) DEFAULT 'Y',
        created_by varchar(50) NULL,
        created_at datetime NULL,
        CONSTRAINT fk_binding_master_nfc FOREIGN KEY (id_nfc) REFERENCES dbo.N_EMI_Master_NFC(id)
    );
END;
GO
