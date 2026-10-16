-- Change ID: DDL-2026-132-visitor-jenis-akses-efektif
--
-- Baris di N_VISITOR_Permit_Efektif tidak lagi berarti "sudah disetujui".
-- Sejak akses palang sementara untuk menemui SHE, pengajuan yang BELUM
-- diputus pun punya baris di sini. Pembedanya selama ini hanya kalimat di
-- Nama_Durasi, dan kalimat bukan kontrak: satu orang merapikan label itu
-- memutus aplikasi palang sekaligus penyapu kita sendiri, tanpa galat.
--
-- Aditif dan berdefault, jadi pembaca lama - termasuk aplikasi pihak ketiga
-- yang belum tahu kolom ini - tidak terganggu sama sekali.

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
     WHERE object_id = OBJECT_ID('dbo.N_VISITOR_Permit_Efektif') AND name = 'Jenis_Akses'
)
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif
        ADD Jenis_Akses varchar(20) NOT NULL
            CONSTRAINT DF_VISITOR_Permit_Efektif_Jenis_Akses DEFAULT ('PERMIT');
END;
GO

-- Penyaring "hanya permit sungguhan" dipakai laporan, dashboard, export, dan
-- pencabutan massal. Indeks tersaring menjaga mereka tidak memindai baris
-- percobaan yang jumlahnya justru paling banyak berubah.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Efektif_Jenis_Akses')
BEGIN
    CREATE INDEX IX_VISITOR_Permit_Efektif_Jenis_Akses
        ON dbo.N_VISITOR_Permit_Efektif (Jenis_Akses, Status_Efektif)
        INCLUDE (No_Transaksi);
END;
GO
