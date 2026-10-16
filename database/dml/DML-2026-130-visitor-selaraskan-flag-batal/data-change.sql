/* Change ID: DML-2026-130-visitor-selaraskan-flag-batal */
/*
    Selaraskan Flag_Batal dengan Status_Efektif pada kedua tabel proyeksi yang
    dibaca aplikasi palang pihak ketiga.

    Kontrak yang berlaku:

      Status_Efektif = 'DIBATALKAN'  <=>  Flag_Batal = 'Y'
      Status_Efektif <> 'DIBATALKAN' <=>  Flag_Batal = 'T'
      setiap perubahan baris          =>  Flag_Sync  = 'T'  (tarik ulang)

    EXPIRED sengaja TIDAK ikut Flag_Batal='Y': kedaluwarsa bukan pencabutan,
    dan palang membedakan keduanya saat mencatat alasan penolakan di pos.

    Status_Efektif dipakai sebagai sumber kebenaran, bukan flagnya. Pada
    development seluruh baris yang bertentangan sudah diperiksa satu per satu
    dan Dibatalkan_At selalu sependapat dengan Status_Efektif: yang DIBATALKAN
    punya Dibatalkan_At terisi, yang AKTIF kosong dan headernya masih berjalan.
    Flag-nya yang tertinggal, bukan statusnya.

    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. Permit: dicabut tapi belum bertanda batal ───────────────────────── */
/* Akibat sebelum perbaikan: kendaraan yang sudah dicabut tetap dianggap sah
   oleh palang, dan Flag_Sync='Y' membuatnya tak pernah ditarik ulang. */
UPDATE N_VISITOR_Permit_Efektif
   SET Flag_Batal = 'Y',
       Flag_Sync  = 'T',
       Updated_At = GETDATE()
 WHERE Status_Efektif = 'DIBATALKAN'
   AND (Flag_Batal IS NULL OR Flag_Batal <> 'Y');

/* ── 2. Permit: bertanda batal padahal masih berlaku ────────────────────── */
UPDATE N_VISITOR_Permit_Efektif
   SET Flag_Batal = 'T',
       Flag_Sync  = 'T',
       Updated_At = GETDATE()
 WHERE Status_Efektif <> 'DIBATALKAN'
   AND Flag_Batal = 'Y'
   AND Dibatalkan_At IS NULL;

/* ── 3. Tamu: dicabut tapi belum bertanda batal ─────────────────────────── */
UPDATE N_VISITOR_Tamu_Efektif
   SET Flag_Batal = 'Y',
       Flag_Sync  = 'T',
       Updated_At = GETDATE()
 WHERE Status_Efektif = 'DIBATALKAN'
   AND (Flag_Batal IS NULL OR Flag_Batal <> 'Y');

/* ── 4. Tamu: bertanda batal padahal masih berlaku ──────────────────────── */
UPDATE N_VISITOR_Tamu_Efektif
   SET Flag_Batal = 'T',
       Flag_Sync  = 'T',
       Updated_At = GETDATE()
 WHERE Status_Efektif <> 'DIBATALKAN'
   AND Flag_Batal = 'Y'
   AND Dibatalkan_At IS NULL;

/* ── 5. Database Change Dashboard ───────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-130-visitor-selaraskan-flag-batal')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-130-visitor-selaraskan-flag-batal', 'DML', 'Selaraskan Flag_Batal dengan Status_Efektif pada proyeksi palang', 'Permit/tamu yang dicabut kini bertanda Flag_Batal=Y dan Flag_Sync=T agar aplikasi palang menariknya ulang; baris yang masih berlaku dibersihkan dari tanda batal yang keliru.', 'database/dml/DML-2026-130-visitor-selaraskan-flag-batal/data-change.sql', 'docs/changes/DML-2026-130-visitor-selaraskan-flag-batal/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-flag-batal');
END;

COMMIT TRANSACTION;

/* ── Verifikasi: kedua kueri harus mengembalikan 0 ──────────────────────── */
/*
SELECT COUNT(*) FROM N_VISITOR_Permit_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');

SELECT COUNT(*) FROM N_VISITOR_Tamu_Efektif
 WHERE (Status_Efektif = 'DIBATALKAN' AND Flag_Batal <> 'Y')
    OR (Status_Efektif <> 'DIBATALKAN' AND Flag_Batal = 'Y');
*/
