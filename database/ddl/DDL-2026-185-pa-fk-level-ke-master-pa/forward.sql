/* Change ID: DDL-2026-185-pa-fk-level-ke-master-pa
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-183 (N_PA_Level) dan DML-2026-184 (isi level HRIS) — WAJIB lebih dulu.
   Scope: sepuluh FK level PA yang menunjuk HRIS_Level dipindah ke N_PA_Level (nama constraint
          tetap). Datanya tidak berubah: level HRIS punya ID yang sama di N_PA_Level. FK baru
          N_PA_Karyawan_Level_PA.ID_Level_PA -> N_PA_Level (koreksi boleh ke level khusus PA).
   Catatan eksekusi: memuat ALTER TABLE ... DROP CONSTRAINT. Pemilik proyek mengizinkan agen
          menjalankannya di DEVELOPMENT (13 Okt 2026); produksi hanya lewat SQL Tracker.
   Dokumentasi: docs/changes/DDL-2026-185-pa-fk-level-ke-master-pa/README.md
*/

/* Penjaga: seluruh ID level yang sedang dipakai sudah ada di N_PA_Level. */
IF OBJECT_ID('N_PA_Level', 'U') IS NULL
    THROW 50685, 'DDL-2026-185 butuh N_PA_Level (DDL-2026-183) lebih dulu.', 1;
IF EXISTS (SELECT 1 FROM HRIS_Level h WHERE NOT EXISTS (SELECT 1 FROM N_PA_Level p WHERE p.ID_Level = h.ID_Level))
    THROW 50686, 'N_PA_Level belum memuat seluruh HRIS_Level - jalankan DML-2026-184 lebih dulu.', 1;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Competency_Requirement_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Competency_Requirement DROP CONSTRAINT FK_PA_Competency_Requirement_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Competency_Requirement_Level')
    ALTER TABLE N_PA_Competency_Requirement WITH CHECK ADD CONSTRAINT FK_PA_Competency_Requirement_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Curve_Group_Level_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Curve_Group_Level DROP CONSTRAINT FK_PA_Curve_Group_Level_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Curve_Group_Level_Level')
    ALTER TABLE N_PA_Curve_Group_Level WITH CHECK ADD CONSTRAINT FK_PA_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot DROP CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Level')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Curve_Group_Level_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Cycle_Curve_Group_Level DROP CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Curve_Group_Level_Level')
    ALTER TABLE N_PA_Cycle_Curve_Group_Level WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot DROP CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Level')
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Cycle_Question_Package DROP CONSTRAINT FK_PA_Cycle_Question_Package_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Level')
    ALTER TABLE N_PA_Cycle_Question_Package WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Question_Package_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot DROP CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Level')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Participant DROP CONSTRAINT FK_PA_Participant_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Level')
    ALTER TABLE N_PA_Participant WITH CHECK ADD CONSTRAINT FK_PA_Participant_Level FOREIGN KEY (ID_Level_Saat_Cycle) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Pillar_Weight_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Pillar_Weight DROP CONSTRAINT FK_PA_Pillar_Weight_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Pillar_Weight_Level')
    ALTER TABLE N_PA_Pillar_Weight WITH CHECK ADD CONSTRAINT FK_PA_Pillar_Weight_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Rater_Policy_Level' AND referenced_object_id = OBJECT_ID('HRIS_Level'))
    ALTER TABLE N_PA_Rater_Policy DROP CONSTRAINT FK_PA_Rater_Policy_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Rater_Policy_Level')
    ALTER TABLE N_PA_Rater_Policy WITH CHECK ADD CONSTRAINT FK_PA_Rater_Policy_Level FOREIGN KEY (ID_Level) REFERENCES N_PA_Level (ID_Level);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Karyawan_Level_PA_Level')
    ALTER TABLE N_PA_Karyawan_Level_PA WITH CHECK ADD CONSTRAINT FK_PA_Karyawan_Level_PA_Level FOREIGN KEY (ID_Level_PA) REFERENCES N_PA_Level (ID_Level);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-185')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-185',
        'DDL',
        'FK level Performance Appraisal ke Master Level PA',
        'Sepuluh FK ID_Level PA dipindah dari HRIS_Level ke N_PA_Level (nama constraint tetap) dan FK baru N_PA_Karyawan_Level_PA.ID_Level_PA -> N_PA_Level.',
        'database/ddl/DDL-2026-185-pa-fk-level-ke-master-pa/forward.sql',
        'docs/changes/DDL-2026-185-pa-fk-level-ke-master-pa/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Master Data. Setelah DDL-2026-183 dan DML-2026-184. Memuat DROP CONSTRAINT.',
        NULL
    );
END;
GO
