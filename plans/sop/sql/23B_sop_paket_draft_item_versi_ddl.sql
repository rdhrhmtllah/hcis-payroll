/* ============================================================================
   SOP 23B - DDL Versi Item Working Draft SOP

   Prasyarat: SOP 23A sudah dijalankan.
   Menyimpan konten/ujian calon rilis untuk item baru maupun item yang direvisi,
   tanpa membuat N_SOP_Item_Versi aktif berubah sebelum publish paket.
   ============================================================================ */

IF OBJECT_ID('dbo.N_SOP_Paket_Draft_Item_Versi', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_SOP_Paket_Draft_Item_Versi (
        Id_SOP_Draft_Item_Versi BIGINT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_N_SOP_Paket_Draft_Item_Versi PRIMARY KEY,
        Id_SOP_Draft_Item BIGINT NOT NULL,
        Id_SOP_Item_Versi_Asal BIGINT NULL, -- NULL = versi pertama item baru
        Versi_Ke INT NOT NULL,
        Status VARCHAR(30) NOT NULL DEFAULT ('DRAFT'), -- DRAFT | READY | DISCARDED
        Id_Master_Konten BIGINT NULL,
        Id_Master_Ujian VARCHAR(200) NULL,
        Ringkasan_Perubahan NVARCHAR(1000) NULL,
        Tanggal_Berlaku DATE NULL,
        Tanggal_Kadaluarsa DATE NULL,
        Tanggal_Review_Berikutnya DATE NULL,
        Disahkan_Oleh NVARCHAR(255) NULL,
        Jabatan_Pengesah NVARCHAR(255) NULL,
        Tanggal_Pengesahan DATE NULL,
        Nomor_Pengesahan NVARCHAR(255) NULL,
        Created_By INT NULL,
        Created_At DATETIME2(0) NOT NULL DEFAULT (SYSDATETIME()),
        Updated_By INT NULL,
        Updated_At DATETIME2(0) NULL,
        Row_Version ROWVERSION NOT NULL,

        CONSTRAINT FK_N_SOP_Paket_Draft_Item_Versi_Item
            FOREIGN KEY (Id_SOP_Draft_Item)
            REFERENCES dbo.N_SOP_Paket_Draft_Item (Id_SOP_Draft_Item)
    );

    CREATE UNIQUE INDEX UX_N_SOP_Paket_Draft_Item_Versi_Urut
        ON dbo.N_SOP_Paket_Draft_Item_Versi (Id_SOP_Draft_Item, Versi_Ke);

    CREATE INDEX IX_N_SOP_Paket_Draft_Item_Versi_Status
        ON dbo.N_SOP_Paket_Draft_Item_Versi (Id_SOP_Draft_Item, Status, Versi_Ke DESC);
END;
GO
