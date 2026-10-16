/* Change ID: DML-2026-031-seed-jenis-kode-dokumen */

/* Tujuh kode jenis dokumen jenis dokumen: PLC, SK, SOP, IK, STD, FRM, MAN.

   NAMA ADALAH PLACEHOLDER. Kepanjangan resminya belum ditetapkan saat seed ini
   ditulis; admin memperbaikinya lewat halaman Master Jenis Dokumen tanpa rilis.
   Deskripsi sengaja DIKOSONGKAN supaya jelas mana yang belum disentuh manusia —
   mengisinya dengan tebakan membuat yang belum diputuskan tampak sudah final.

   URUTAN mengikuti daftar yang diberikan. Level_Hirarki bebas diubah admin;
   ia tak dipakai logika mana pun, hanya untuk mengurutkan tampilan. */

/* 1. Empat baris yang sudah ada hanya DIISI kode jenis dokumennya. Kode_Jenis dan
      Id-nya tidak disentuh: Jenis_Dokumen_Id pada N_SOP_Dokumen menunjuk ke
      sana, dan seed serta test menemukannya lewat Kode_Jenis. */
UPDATE N_SOP_Master_Jenis_Dokumen SET Kode_Jenis_Dokumen = 'PLC', Level_Hirarki = 1, Urutan = 1, Updated_At = SYSDATETIME() WHERE Kode_Jenis = 'KEBIJAKAN'       AND Kode_Jenis_Dokumen IS NULL;
UPDATE N_SOP_Master_Jenis_Dokumen SET Kode_Jenis_Dokumen = 'SOP', Level_Hirarki = 3, Urutan = 3, Updated_At = SYSDATETIME() WHERE Kode_Jenis = 'PROSEDUR'        AND Kode_Jenis_Dokumen IS NULL;
UPDATE N_SOP_Master_Jenis_Dokumen SET Kode_Jenis_Dokumen = 'IK',  Level_Hirarki = 4, Urutan = 4, Updated_At = SYSDATETIME() WHERE Kode_Jenis = 'INSTRUKSI_KERJA' AND Kode_Jenis_Dokumen IS NULL;
UPDATE N_SOP_Master_Jenis_Dokumen SET Kode_Jenis_Dokumen = 'FRM', Level_Hirarki = 6, Urutan = 6, Updated_At = SYSDATETIME() WHERE Kode_Jenis = 'FORMULIR'        AND Kode_Jenis_Dokumen IS NULL;

/* 2. Tiga jenis yang belum punya baris sama sekali. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'SURAT_KEPUTUSAN')
    INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Kode_Jenis_Dokumen, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif, Created_At, Created_By)
    VALUES ('SURAT_KEPUTUSAN', 'SK', 'Surat Keputusan', 2, NULL, 2, 'Y', SYSDATETIME(), NULL);

IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'STANDAR')
    INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Kode_Jenis_Dokumen, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif, Created_At, Created_By)
    VALUES ('STANDAR', 'STD', 'Standar', 5, NULL, 5, 'Y', SYSDATETIME(), NULL);

IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'MANUAL')
    INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Kode_Jenis_Dokumen, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif, Created_At, Created_By)
    VALUES ('MANUAL', 'MAN', 'Manual', 7, NULL, 7, 'Y', SYSDATETIME(), NULL);

/* 3. Ketiganya berlaku untuk tipe item DOKUMEN.
      Hanya FRM yang berpasangan dengan tipe FORM, dan pasangan itu sudah ada
      sejak DML-2026-017. Admin mengubah pemetaan ini lewat halaman masternya. */
INSERT INTO N_SOP_Master_Jenis_Dokumen_Tipe_Item (Jenis_Dokumen_Id, Tipe_Item, Flag_Aktif, Created_At, Created_By)
SELECT j.Id_SOP_Jenis_Dokumen, 'DOKUMEN', 'Y', SYSDATETIME(), NULL
FROM N_SOP_Master_Jenis_Dokumen j
WHERE j.Kode_Jenis IN ('SURAT_KEPUTUSAN', 'STANDAR', 'MANUAL')
  AND NOT EXISTS (
      SELECT 1 FROM N_SOP_Master_Jenis_Dokumen_Tipe_Item m
      WHERE m.Jenis_Dokumen_Id = j.Id_SOP_Jenis_Dokumen AND m.Tipe_Item = 'DOKUMEN'
  );

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-031-seed-jenis-kode-dokumen')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-031-seed-jenis-kode-dokumen', 'DML', 'Seed tujuh kode jenis dokumen jenis dokumen', 'PLC, SK, SOP, IK, STD, FRM, MAN. Nama placeholder dan deskripsi kosong; admin melengkapinya lewat Master Jenis Dokumen.', 'database/dml/DML-2026-031-seed-jenis-kode-dokumen/data-change.sql', 'docs/changes/DML-2026-031-seed-jenis-kode-dokumen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'jenis-kode-dokumen');
