/* Change ID: DDL-2026-056-entitas-kode-pemilik (bagian DML)

   Mengisi kode cetak pemilik untuk EHG saja. Tiga entitas lain sengaja
   dibiarkan NULL — kode identitas mereka memang sama dengan kode penomorannya,
   dan mengisinya dengan nilai yang sama hanya menambah tempat yang bisa
   menyimpang.

   Skrip membatalkan transaksi bila baris terdampak bukan TEPAT SATU: nol
   berarti kodenya berubah atau barisnya hilang, lebih dari satu berarti master
   entitas memuat EHG kembar — dua keadaan yang harus dilihat orang, bukan
   dilewati diam-diam.

   SESUDAH INI WAJIB: php artisan sop:segarkan-nomor
   Nomor_SOP_Resmi adalah kolom TERSIMPAN yang dibaca 45 berkas. Tanpa
   penyegaran, dokumen milik holding menyimpan nomor ber-"EHG" sementara layar
   merakit nomor ber-"HOL" — dua nomor untuk satu dokumen. */

SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE N_HRIS_Master_Entitas
   SET Kode_Sebagai_Pemilik = 'HOL',
       Updated_At = SYSDATETIME()
 WHERE Kode_Entitas = 'EHG';

IF @@ROWCOUNT <> 1
BEGIN
    ROLLBACK TRANSACTION;
    THROW 50056, 'DDL-2026-056: baris EHG di N_HRIS_Master_Entitas bukan tepat satu. Dibatalkan.', 1;
END

COMMIT TRANSACTION;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-056-seed-kode-pemilik-holding')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-056-seed-kode-pemilik-holding', 'DML', 'Kode pemilik HOL untuk entitas holding', 'Mengisi Kode_Sebagai_Pemilik = HOL pada baris EHG. Tiga entitas lain dibiarkan NULL sehingga jatuh ke Kode_Entitas. Wajib diikuti sop:segarkan-nomor karena Nomor_SOP_Resmi adalah kolom tersimpan.', 'database/dml/DDL-2026-056-entitas-kode-pemilik/data-change.sql', 'docs/changes/DDL-2026-056-entitas-kode-pemilik/README.md', 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'entitas-kode-pemilik');
GO
