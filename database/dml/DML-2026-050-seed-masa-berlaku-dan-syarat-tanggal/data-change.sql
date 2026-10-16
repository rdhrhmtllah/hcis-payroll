/* Change ID: DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal */

UPDATE N_SOP_Master_Jenis_Dokumen
SET Masa_Berlaku_Bulan = 24
WHERE Masa_Berlaku_Bulan IS NULL;
GO

UPDATE N_SOP_Master_Jenis_Dokumen
SET Siklus_Review_Bulan = 12
WHERE Siklus_Review_Bulan IS NULL;
GO

INSERT INTO N_SOP_Master_Tahap_Versi_Syarat
(Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif, Created_By)
SELECT t.Id_Tahap, s.kode, s.nama, s.urut, 'Y', NULL
FROM (VALUES
    ('FILING_REGISTERED', 'TANGGAL_BERLAKU',           N'Tanggal mulai berlaku',    3),
    ('FILING_REGISTERED', 'TANGGAL_KADALUARSA',        N'Tanggal kadaluarsa',       4),
    ('FILING_REGISTERED', 'TANGGAL_REVIEW_BERIKUTNYA', N'Jadwal review berikutnya', 5)
) s(tahap, kode, nama, urut)
JOIN N_SOP_Master_Tahap_Versi t ON t.Kode_Tahap = s.tahap
WHERE NOT EXISTS (
    SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat x
    WHERE x.Tahap_Id = t.Id_Tahap AND x.Kode_Syarat = s.kode
);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal', 'DML', 'Baku masa berlaku dan syarat tanggal terbit', 'Baku 24/12 bulan per jenis dokumen; tiga syarat tanggal untuk meninggalkan Pemberkasan menuju terbit.', 'database/dml/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/data-change.sql', 'docs/changes/DML-2026-050-seed-masa-berlaku-dan-syarat-tanggal/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'masa-berlaku');
GO
