/* Change ID: DML-2026-133-visitor-pola-pos-nonaktif */
/*
    Pensiunkan baris pola jadwal yang menunjuk pos atau shift NONAKTIF.

    Baris seperti ini tidak menghasilkan slot apa pun - slotRentang() hanya
    membangun dari pos dan shift yang aktif - tetapi ia tetap muncul di layar
    pengaturan dan ikut divalidasi saat Simpan. Aturan "pos harus aktif"
    menolaknya, sehingga SELURUH pengaturan pola gagal disimpan dengan pesan
    yang menunjuk baris yang bahkan tidak sedang disunting. Buntu itu tidak
    bisa dibereskan dari layar: baris yang memblokirnya justru tak bisa
    diperbaiki tanpa mengaktifkan kembali posnya.

    Sebabnya sudah ditutup di ubahStatusPos()/ubahStatusShift() - sejak itu
    penonaktifan ikut memensiunkan polanya. Artefak ini hanya membereskan
    baris yang terlanjur ada.

    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE pl
   SET pl.Flag_Aktif = 'T',
       pl.Updated_At = GETDATE(),
       pl.Updated_By = 'DML-2026-133'
  FROM N_VISITOR_Inspeksi_Pola AS pl
  LEFT JOIN N_VISITOR_Master_Pos_Inspeksi   AS po ON po.Id_Pos_Inspeksi   = pl.Id_Pos_Inspeksi
  LEFT JOIN N_VISITOR_Master_Shift_Inspeksi AS sh ON sh.Id_Shift_Inspeksi = pl.Id_Shift_Inspeksi
 WHERE pl.Flag_Aktif = 'Y'
   AND (po.Id_Pos_Inspeksi IS NULL OR po.Flag_Aktif <> 'Y'
     OR sh.Id_Shift_Inspeksi IS NULL OR sh.Flag_Aktif <> 'Y');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-133-visitor-pola-pos-nonaktif')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-133-visitor-pola-pos-nonaktif', 'DML', 'Pensiunkan pola jadwal yang posnya sudah nonaktif', 'Pola yang menunjuk pos/shift nonaktif tidak menghasilkan slot tetapi memblokir seluruh penyimpanan pengaturan pola lewat aturan validasi pos aktif.', 'database/dml/DML-2026-133-visitor-pola-pos-nonaktif/data-change.sql', 'docs/changes/DML-2026-133-visitor-pola-pos-nonaktif/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-pola-pos-nonaktif');
END;

COMMIT TRANSACTION;

/* Verifikasi: harus 0. */
/*
SELECT COUNT(*)
  FROM N_VISITOR_Inspeksi_Pola pl
  LEFT JOIN N_VISITOR_Master_Pos_Inspeksi   po ON po.Id_Pos_Inspeksi   = pl.Id_Pos_Inspeksi
  LEFT JOIN N_VISITOR_Master_Shift_Inspeksi sh ON sh.Id_Shift_Inspeksi = pl.Id_Shift_Inspeksi
 WHERE pl.Flag_Aktif = 'Y'
   AND (po.Id_Pos_Inspeksi IS NULL OR po.Flag_Aktif <> 'Y'
     OR sh.Id_Shift_Inspeksi IS NULL OR sh.Flag_Aktif <> 'Y');
*/
