/*  DML-2026-074 — Versi yang dikembalikan: tahap & tanggal digantikan tertinggal
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Status : DIJALANKAN di dev 31 Agu 2026 — 1 baris diperbaiki (versi 12139).

    MASALAHNYA
    SopItemVersionService::rollback() mengembalikan versi lama menjadi berlaku
    dengan menulis Status='PUBLISHED' saja. Tahap_Id dan Tanggal_Obsolete-nya
    dibiarkan apa adanya — padahal publish() pada berkas yang sama menggeser
    keduanya. Satu aturan di dua pintu, dan hanya satu yang dirawat.

    Akibatnya versi yang SEDANG BERLAKU:
      - bertahap OBSOLETE, sehingga tampil sebagai arsip di stepper wizard
      - membawa Tanggal_Obsolete, sehingga riwayat menyebutnya "digantikan
        pada" tanggal yang sudah lewat
      - Status tidak lagi sejalan dengan Status_Teknis tahapnya — dua sumber
        untuk satu keadaan

    Kodenya sudah diperbaiki (Tahap_Id + Tanggal_Obsolete ikut mundur).
    Change ini membereskan baris yang terlanjur lahir SEBELUM perbaikan itu,
    supaya sisanya tak terbaca sebagai perilaku yang disengaja.

    RUANG LINGKUP — dicari lewat POLA, bukan id yang dipatok
      versi HIDUP (Flag_Aktif='Y') ber-Status='PUBLISHED'
      yang tahapnya BUKAN FILING_REGISTERED

    BARIS TERDUGA
      N_SOP_Item_Versi  1 UPDATE  (dev, 31 Agu 2026 — versi 12139, dokumen 17054)
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

DECLARE @filing int = (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'FILING_REGISTERED');

IF @filing IS NULL
BEGIN
    ROLLBACK TRAN;
    THROW 50074, 'Tahap FILING_REGISTERED tak ditemukan di master.', 1;
END

/* ── Sebelum ────────────────────────────────────────────────────────────── */
SELECT v.Id_SOP_Item_Versi, v.Id_SOP_Dokumen, v.Versi_Ke, v.Status,
       t.Kode_Tahap AS Tahap_Sekarang, v.Tanggal_Obsolete
  FROM N_SOP_Item_Versi v
  JOIN N_SOP_Master_Tahap_Versi t ON t.Id_Tahap = v.Tahap_Id
 WHERE v.Flag_Aktif = 'Y' AND v.Status = 'PUBLISHED' AND t.Kode_Tahap <> 'FILING_REGISTERED';

/* ── Perbaikan ──────────────────────────────────────────────────────────── */
UPDATE v
   SET v.Tahap_Id        = @filing,
       v.Tanggal_Obsolete = NULL,
       v.Updated_At      = SYSDATETIME()
  FROM N_SOP_Item_Versi v
  JOIN N_SOP_Master_Tahap_Versi t ON t.Id_Tahap = v.Tahap_Id
 WHERE v.Flag_Aktif = 'Y' AND v.Status = 'PUBLISHED' AND t.Kode_Tahap <> 'FILING_REGISTERED';

PRINT CONCAT('Baris diperbaiki: ', @@ROWCOUNT);

/* ── Verifikasi ─────────────────────────────────────────────────────────── */
SELECT COUNT(*) AS A1_Status_Vs_Status_Teknis
  FROM N_SOP_Item_Versi v
  JOIN N_SOP_Master_Tahap_Versi t ON t.Id_Tahap = v.Tahap_Id
 WHERE v.Status <> t.Status_Teknis;

SELECT COUNT(*) AS D1_Publikasi_Ber_Tanggal_Obsolete
  FROM N_SOP_Item_Versi
 WHERE Flag_Aktif = 'Y' AND Status = 'PUBLISHED' AND Tanggal_Obsolete IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-074-versi-dikembalikan-tahapnya-tertinggal')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-074-versi-dikembalikan-tahapnya-tertinggal', 'DML', 'Versi yang dikembalikan: tahap dan tanggal digantikan tertinggal',
 'rollback() hanya menulis Status sehingga versi yang kembali berlaku tetap bertahap OBSOLETE dan tetap membawa Tanggal_Obsolete. Kode sudah diperbaiki; change ini membereskan baris yang terlanjur lahir.',
 'database/dml/DML-2026-074-versi-dikembalikan-tahapnya-tertinggal/data-change.sql',
 'docs/changes/DML-2026-074-versi-dikembalikan-tahapnya-tertinggal/README.md',
 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'rollback-tahap');

COMMIT TRAN;
