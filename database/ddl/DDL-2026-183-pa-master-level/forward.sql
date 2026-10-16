/* Change ID: DDL-2026-183-pa-master-level
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: HRIS_Level.
   Scope: master level milik Performance Appraisal. Berisi salinan level HRIS (ID sama) dan level
          KHUSUS PA (mis. OFFICER SPECIALIST) yang tidak boleh ada di HRIS. Diisi oleh
          DML-2026-184; FK level PA dipindah ke sini oleh DDL-2026-185.
   Dokumentasi: docs/changes/DDL-2026-183-pa-master-level/README.md
*/

IF OBJECT_ID('N_PA_Level', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Level (
        ID_Level bigint NOT NULL
            CONSTRAINT PK_PA_Level PRIMARY KEY,
        Nama_Level nvarchar(100) NOT NULL,
        Sumber varchar(10) NOT NULL,
        ID_Level_HRIS_Dasar bigint NULL
            CONSTRAINT FK_PA_Level_HRIS_Dasar REFERENCES HRIS_Level (ID_Level),
        Urutan decimal(6,2) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Level_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Level_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Level_Sumber CHECK (Sumber IN ('HRIS', 'PA')),
        CONSTRAINT CK_PA_Level_Flag_Aktif CHECK (Flag_Aktif IN ('Y', 'T')),
        /* Rentang terpisah: identity HRIS_Level tidak akan pernah menabrak level khusus PA. */
        CONSTRAINT CK_PA_Level_Rentang_Id CHECK (
            (Sumber = 'HRIS' AND ID_Level < 1000000)
            OR (Sumber = 'PA' AND ID_Level >= 1000000)
        ),
        /* Level khusus PA wajib punya level dasar HRIS — asal salinan matriksnya. */
        CONSTRAINT CK_PA_Level_Dasar CHECK (Sumber = 'HRIS' OR ID_Level_HRIS_Dasar IS NOT NULL)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Level') AND name = 'UX_PA_Level_Nama_Aktif')
    CREATE UNIQUE INDEX UX_PA_Level_Nama_Aktif ON N_PA_Level (Nama_Level) WHERE Flag_Aktif = 'Y';
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-183')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-183',
        'DDL',
        'Master Level PA Performance Appraisal',
        'Tabel N_PA_Level: salinan level HRIS (ID sama, Sumber HRIS) dan level khusus PA (ID >= 1000000, Sumber PA, wajib level dasar HRIS). Urutan menggantikan Level_Hierarchy di PA.',
        'database/ddl/DDL-2026-183-pa-master-level/forward.sql',
        'docs/changes/DDL-2026-183-pa-master-level/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Master Data. Diisi DML-2026-184; FK dipindah DDL-2026-185.',
        NULL
    );
END;
GO
