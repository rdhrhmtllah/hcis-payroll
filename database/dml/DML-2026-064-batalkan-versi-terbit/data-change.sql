/*  DML-2026-064 — Versi terbit boleh dibatalkan selama BELUM BEREDAR
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Status : DIJALANKAN di dev 30 Agu 2026 (1 INSERT transisi, 1 INSERT syarat).

    MASALAHNYA
    Admin menerbitkan versi lalu sadar berkasnya salah. Satu-satunya jalan
    keluar hari ini adalah menerbitkan revisi pengganti — dan revisi itu
    MENGONSUMSI satu nomor untuk kekeliruan yang berumur beberapa menit.
    Versi yang keliru pun jadi OBSOLETE, yang berarti nomornya terkunci
    selamanya.

    KEPUTUSANNYA (30 Agu 2026)
    Versi terbit boleh dibatalkan — sehingga nomornya dilepas dan bisa dipakai
    penggantinya — TETAPI hanya selama belum ada yang menerimanya.

    Garisnya: nomor revisi dikonsumsi saat versinya SAMPAI KE ORANG, bukan saat
    diterbitkan. Sesudah beredar, membatalkan berarti menyatakan dokumen yang
    sudah diikuti orang "tak pernah terbit", dan untuk itulah OBSOLETE ada.

    Di dev saat change ini ditulis: 31 versi terbit, 28 di antaranya sudah
    beredar. Jalur ini memang untuk kasus sempit, bukan pintu umum.

    DUA BAGIAN
      1. Transisi FILING_REGISTERED -> CANCEL dibuka.
      2. Syarat BELUM_BEREDAR dipasang pada tahap CANCEL.

    Syarat itu dinilai SopTahapVersiService::nilaiSyarat() dari lima rujukan
    hilir (progres, paket item, penjadwalan integrasi, coverage rollup, paket
    versi item). Draft memenuhinya dengan sendirinya, jadi pembatalan draft
    tak berubah sama sekali — syaratnya hanya menggigit pada yang sudah terbit.

    BARIS TERDUGA
      N_SOP_Master_Tahap_Versi_Transisi  1 INSERT atau 1 UPDATE
      N_SOP_Master_Tahap_Versi_Syarat    1 INSERT
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

DECLARE @filing int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'FILING_REGISTERED');
DECLARE @cancel int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'CANCEL');

IF @filing IS NULL OR @cancel IS NULL
BEGIN
    ROLLBACK TRAN;
    THROW 50064, 'Tahap acuan tak ditemukan di master.', 1;
END

/* ── 1. Buka jalur terbit -> batal ──────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi
               WHERE Dari_Tahap_Id = @filing AND Ke_Tahap_Id = @cancel)
    INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif)
    VALUES (@filing, @cancel, 'Y');
ELSE
    UPDATE N_SOP_Master_Tahap_Versi_Transisi SET Flag_Aktif = 'Y'
     WHERE Dari_Tahap_Id = @filing AND Ke_Tahap_Id = @cancel;

/* ── 2. Syaratnya: belum beredar ────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat
               WHERE Tahap_Id = @cancel AND Kode_Syarat = 'BELUM_BEREDAR')
    INSERT INTO N_SOP_Master_Tahap_Versi_Syarat (Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif)
    VALUES (@cancel, 'BELUM_BEREDAR', N'Belum diterima siapa pun', 1, 'Y');

/* ── Verifikasi ─────────────────────────────────────────────────────────── */
SELECT d.Kode_Tahap AS Dari, k.Kode_Tahap AS Ke, t.Flag_Aktif
  FROM N_SOP_Master_Tahap_Versi_Transisi t
  JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = t.Dari_Tahap_Id
  JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = t.Ke_Tahap_Id
 WHERE k.Kode_Tahap = 'CANCEL' ORDER BY d.Urutan;

SELECT t.Kode_Tahap, s.Kode_Syarat, s.Nama_Syarat, s.Flag_Aktif
  FROM N_SOP_Master_Tahap_Versi_Syarat s
  JOIN N_SOP_Master_Tahap_Versi t ON t.Id_Tahap = s.Tahap_Id
 WHERE t.Kode_Tahap = 'CANCEL';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-064-batalkan-versi-terbit')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-064-batalkan-versi-terbit', 'DML', 'Versi terbit boleh dibatalkan selama belum beredar',
 'Transisi FILING_REGISTERED -> CANCEL dibuka, dengan syarat BELUM_BEREDAR pada tahap CANCEL. Nomor revisi dikonsumsi saat versinya sampai ke orang, bukan saat diterbitkan.',
 'database/dml/DML-2026-064-batalkan-versi-terbit/data-change.sql',
 'docs/changes/DML-2026-064-batalkan-versi-terbit/README.md',
 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'batal-terbit');

COMMIT TRAN;
