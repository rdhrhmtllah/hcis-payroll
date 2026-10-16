/* ============================================================================
   SOP 22B - DML Backfill Versi Paket SOP
   Jalankan manual SETELAH 22A.
   Isi v1 untuk paket PUBLISHED yang belum punya riwayat versi paket.
   ============================================================================ */

DECLARE @IdPaket INT;
DECLARE @IdPaketVersi BIGINT;
DECLARE @SnapshotJson NVARCHAR(MAX);
DECLARE @SnapshotHash CHAR(40);
DECLARE @ItemTotal INT;
DECLARE @ItemWajib INT;

DECLARE paket_cursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT p.Id_SOP_Paket
    FROM dbo.N_SOP_Master_Paket p
    WHERE p.Status = 'PUBLISHED'
      AND p.Flag_Aktif = 'Y'
      AND NOT EXISTS (
          SELECT 1
          FROM dbo.N_SOP_Paket_Versi pv
          WHERE pv.Id_SOP_Paket = p.Id_SOP_Paket
      );

OPEN paket_cursor;
FETCH NEXT FROM paket_cursor INTO @IdPaket;

WHILE @@FETCH_STATUS = 0
BEGIN
    SELECT @SnapshotJson = (
        SELECT
            i.Id_SOP_Paket_Item,
            i.Id_SOP_Section,
            i.Urutan,
            i.Tipe_Item,
            i.Judul_Item,
            CASE WHEN i.Flag_Wajib = 'Y' THEN CAST(1 AS BIT) ELSE CAST(0 AS BIT) END AS Wajib,
            i.Id_Item_Versi_Aktif AS Id_SOP_Item_Versi,
            v.Versi_Ke AS Versi_Item,
            v.Status AS Status_Item_Versi,
            v.Ringkasan_Perubahan
        FROM dbo.N_SOP_Paket_Item i
        LEFT JOIN dbo.N_SOP_Item_Versi v
            ON v.Id_SOP_Item_Versi = i.Id_Item_Versi_Aktif
        WHERE i.Id_SOP_Paket = @IdPaket
          AND i.Flag_Aktif = 'Y'
        ORDER BY i.Urutan
        FOR JSON PATH
    );

    SET @SnapshotJson = ISNULL(@SnapshotJson, N'[]');
    SET @SnapshotHash = CONVERT(CHAR(40), HASHBYTES('SHA1', CONVERT(VARBINARY(MAX), @SnapshotJson)), 2);

    SELECT
        @ItemTotal = COUNT(1),
        @ItemWajib = SUM(CASE WHEN i.Flag_Wajib = 'Y' THEN 1 ELSE 0 END)
    FROM dbo.N_SOP_Paket_Item i
    WHERE i.Id_SOP_Paket = @IdPaket
      AND i.Flag_Aktif = 'Y';

    INSERT INTO dbo.N_SOP_Paket_Versi (
        Id_SOP_Paket,
        Versi_Ke,
        Status,
        Trigger_Perubahan,
        Ringkasan_Perubahan,
        Snapshot_Hash,
        Snapshot_JSON,
        Item_Total,
        Item_Wajib,
        Tanggal_Publish,
        Created_At
    )
    VALUES (
        @IdPaket,
        1,
        'PUBLISHED',
        'BACKFILL',
        N'Baseline versi paket dari struktur published saat ini.',
        @SnapshotHash,
        @SnapshotJson,
        ISNULL(@ItemTotal, 0),
        ISNULL(@ItemWajib, 0),
        SYSDATETIME(),
        SYSDATETIME()
    );

    SET @IdPaketVersi = SCOPE_IDENTITY();

    INSERT INTO dbo.N_SOP_Paket_Versi_Item (
        Id_SOP_Paket_Versi,
        Id_SOP_Paket_Item,
        Id_SOP_Item_Versi,
        Id_SOP_Section,
        Urutan,
        Tipe_Item,
        Judul_Item,
        Wajib,
        Versi_Item,
        Status_Item_Versi,
        Ringkasan_Perubahan,
        Created_At
    )
    SELECT
        @IdPaketVersi,
        i.Id_SOP_Paket_Item,
        i.Id_Item_Versi_Aktif,
        i.Id_SOP_Section,
        i.Urutan,
        i.Tipe_Item,
        i.Judul_Item,
        i.Flag_Wajib,
        v.Versi_Ke,
        v.Status,
        v.Ringkasan_Perubahan,
        SYSDATETIME()
    FROM dbo.N_SOP_Paket_Item i
    LEFT JOIN dbo.N_SOP_Item_Versi v
        ON v.Id_SOP_Item_Versi = i.Id_Item_Versi_Aktif
    WHERE i.Id_SOP_Paket = @IdPaket
      AND i.Flag_Aktif = 'Y'
    ORDER BY i.Urutan;

    UPDATE dbo.N_SOP_Master_Paket
    SET Versi_Paket_Aktif = 1,
        Id_SOP_Paket_Versi_Aktif = @IdPaketVersi,
        Updated_At = ISNULL(Updated_At, SYSDATETIME())
    WHERE Id_SOP_Paket = @IdPaket;

    FETCH NEXT FROM paket_cursor INTO @IdPaket;
END;

CLOSE paket_cursor;
DEALLOCATE paket_cursor;
GO
