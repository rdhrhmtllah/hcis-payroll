/* Change ID: DML-2026-176-pa-matriks-kompetensi-awal
   Target: Web_HRIS development; production only through SQL Tracker. Bergantung pada DDL-2026-175.
   Isi awal N_PA_Competency_Level_Requirement dari matriks per jenis (N_PA_Competency_Requirement aktif)
   x kompetensi aktif jenis itu. Tingkat dituntut NULL (belum ditentukan). Idempoten.
*/
INSERT INTO N_PA_Competency_Level_Requirement (ID_Level, PA_Competency_Id, PA_Competency_Proficiency_Id, Flag_Aktif)
SELECT DISTINCT r.ID_Level, c.Id_PA_Competency, NULL, 'Y'
  FROM N_PA_Competency_Requirement r
  JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = r.PA_Competency_Type_Id AND t.Flag_Aktif = 'Y'
  JOIN N_PA_Competency c ON c.PA_Competency_Type_Id = r.PA_Competency_Type_Id AND c.Flag_Aktif = 'Y'
 WHERE r.Flag_Aktif = 'Y'
   AND NOT EXISTS (
        SELECT 1 FROM N_PA_Competency_Level_Requirement x
         WHERE x.ID_Level = r.ID_Level AND x.PA_Competency_Id = c.Id_PA_Competency AND x.Flag_Aktif = 'Y'
   );
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-176')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DML-2026-176',
        'DML',
        'Isi awal matriks kompetensi per kompetensi Performance Appraisal',
        'N_PA_Competency_Level_Requirement diturunkan dari matriks per jenis x kompetensi aktif, supaya kompetensi wajib per level tidak berubah saat sumber kebenaran berpindah ke matriks per kompetensi.',
        'database/dml/DML-2026-176-pa-matriks-kompetensi-awal/data-change.sql',
        'docs/changes/DML-2026-176-pa-matriks-kompetensi-awal/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Master Data. Setelah DDL-2026-175.',
        NULL
    );
END;
GO
