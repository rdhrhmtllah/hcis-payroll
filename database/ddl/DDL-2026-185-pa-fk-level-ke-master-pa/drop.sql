/* DDL-2026-185-pa-fk-level-ke-master-pa — bantuan reset MANUAL: kembalikan FK ke HRIS_Level.
   Agen tidak menjalankan berkas ini. Gagal bila ada baris yang memakai level khusus PA. */

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Karyawan_Level_PA_Level')
    ALTER TABLE N_PA_Karyawan_Level_PA DROP CONSTRAINT FK_PA_Karyawan_Level_PA_Level;
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Competency_Requirement_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Competency_Requirement DROP CONSTRAINT FK_PA_Competency_Requirement_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Competency_Requirement_Level')
    ALTER TABLE N_PA_Competency_Requirement WITH CHECK ADD CONSTRAINT FK_PA_Competency_Requirement_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Curve_Group_Level_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Curve_Group_Level DROP CONSTRAINT FK_PA_Curve_Group_Level_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Curve_Group_Level_Level')
    ALTER TABLE N_PA_Curve_Group_Level WITH CHECK ADD CONSTRAINT FK_PA_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot DROP CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Comp_Req_Snap_Level')
    ALTER TABLE N_PA_Cycle_Competency_Requirement_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Comp_Req_Snap_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Curve_Group_Level_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Cycle_Curve_Group_Level DROP CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Curve_Group_Level_Level')
    ALTER TABLE N_PA_Cycle_Curve_Group_Level WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Curve_Group_Level_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot DROP CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Pillar_Snap_Level')
    ALTER TABLE N_PA_Cycle_Pillar_Weight_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Pillar_Snap_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Cycle_Question_Package DROP CONSTRAINT FK_PA_Cycle_Question_Package_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Package_Level')
    ALTER TABLE N_PA_Cycle_Question_Package WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Question_Package_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot DROP CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Rater_Policy_Snap_Level')
    ALTER TABLE N_PA_Cycle_Rater_Policy_Snapshot WITH CHECK ADD CONSTRAINT FK_PA_Cycle_Rater_Policy_Snap_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Participant DROP CONSTRAINT FK_PA_Participant_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Participant_Level')
    ALTER TABLE N_PA_Participant WITH CHECK ADD CONSTRAINT FK_PA_Participant_Level FOREIGN KEY (ID_Level_Saat_Cycle) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Pillar_Weight_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Pillar_Weight DROP CONSTRAINT FK_PA_Pillar_Weight_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Pillar_Weight_Level')
    ALTER TABLE N_PA_Pillar_Weight WITH CHECK ADD CONSTRAINT FK_PA_Pillar_Weight_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

IF EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Rater_Policy_Level' AND referenced_object_id = OBJECT_ID('N_PA_Level'))
    ALTER TABLE N_PA_Rater_Policy DROP CONSTRAINT FK_PA_Rater_Policy_Level;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Rater_Policy_Level')
    ALTER TABLE N_PA_Rater_Policy WITH CHECK ADD CONSTRAINT FK_PA_Rater_Policy_Level FOREIGN KEY (ID_Level) REFERENCES HRIS_Level (ID_Level);
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-185';
GO
