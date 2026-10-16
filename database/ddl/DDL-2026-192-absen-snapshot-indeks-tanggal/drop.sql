/* DDL-2026-192-absen-snapshot-indeks-tanggal — bantuan reset MANUAL. Agen tidak menjalankan berkas ini. */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_HRIS_Absen_Snapshot') AND name = 'IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal')
    DROP INDEX IX_HRIS_Absen_Snapshot_Perusahaan_Tanggal ON dbo.N_HRIS_Absen_Snapshot;
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-192';
GO
