/* Change ID: DML-2026-037-cap-tanggal-obsolete */

/* 1. Tahap Obsolete mencap Tanggal_Obsolete. */
UPDATE N_SOP_Master_Tahap_Versi SET Cap_Tanggal = 'Tanggal_Obsolete', Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'OBSOLETE' AND Cap_Tanggal IS NULL;

/* 2. Backfill 16 versi yang sudah OBSOLETE.

      DITURUNKAN, BUKAN DITEBAK: sebuah versi digantikan tepat ketika penerusnya
      terbit, jadi tanggalnya adalah Tanggal_Publish versi berikutnya pada
      dokumen yang sama — yang paling awal di antara para penerusnya.

      Dua dari 16 tak punya penerus ber-Tanggal_Publish dan sengaja DIBIARKAN
      KOSONG. Mengisinya dengan tanggal hari ini akan menuliskan peristiwa yang
      tak pernah terjadi pada tanggal itu. */
UPDATE v SET v.Tanggal_Obsolete = CAST(x.terbit AS date), v.Updated_At = SYSDATETIME()
FROM N_SOP_Item_Versi v
CROSS APPLY (
    SELECT MIN(w.Tanggal_Publish) AS terbit
    FROM N_SOP_Item_Versi w
    WHERE w.Id_SOP_Dokumen = v.Id_SOP_Dokumen
      AND w.Versi_Ke > v.Versi_Ke
      AND w.Tanggal_Publish IS NOT NULL
) x
WHERE v.Status = 'OBSOLETE'
  AND v.Tanggal_Obsolete IS NULL
  AND x.terbit IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-037-cap-tanggal-obsolete')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-037-cap-tanggal-obsolete', 'DML', 'Cap dan backfill tanggal obsolete', 'Tahap Obsolete mencap Tanggal_Obsolete; 14 dari 16 versi lama diturunkan dari tanggal terbit penerusnya.', 'database/dml/DML-2026-037-cap-tanggal-obsolete/data-change.sql', 'docs/changes/DML-2026-037-cap-tanggal-obsolete/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tanggal-obsolete');
