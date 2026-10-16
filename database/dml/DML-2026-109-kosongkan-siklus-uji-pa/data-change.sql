/* Change ID: DML-2026-109-kosongkan-siklus-uji-pa
   Target: Web_HRIS DEVELOPMENT SAJA.

   *** JANGAN DIREKAM KE SQL TRACKER, DAN JANGAN DIJALANKAN DI PRODUCTION. ***

   Skrip ini MENGHAPUS PERMANEN seluruh siklus Performance Appraisal beserta segala yang
   menggantung padanya. Di production ia akan menghapus penilaian orang sungguhan, termasuk
   hasil yang sudah mereka baca. Tidak ada jalan pulih selain pemulihan basis data.

   Scope: seluruh tabel N_PA_* yang berlingkup SIKLUS. Master TIDAK DISENTUH — kompetensi,
          jenis kompetensi, aturan penilai, bobot pilar, pita nilai, kuadran HAV, syarat
          kompetensi per level, dan ikatan paket soal baku tetap utuh, sehingga siklus
          berikutnya langsung menyalin cetak biru yang sudah benar.

   KENAPA ADA

   Diminta pemilik pada 7 September 2026: menguji ulang alur admin dari awal, dari layar
   pembuatan siklus. Tiga siklus percobaan dihapus:

       4900  PA-2026-01  Test1                     CLOSED  958 peserta, 3.574 penugasan
       5013  PA-2026-02  Uji Cetak Biru Otomatis   DRAFT   kosong, sudah nonaktif
       5053  PA-2026-03  Test2 - Skala & Esai      OPEN    958 peserta, 3.578 penugasan

   Termasuk dua hasil yang sudah berstatus TERBIT. Di lingkungan pengembangan itu memang
   hasil percobaan, bukan penilaian siapa pun.

   DIHAPUS, BUKAN DINONAKTIFKAN

   Beda dengan hampir semua perubahan lain di modul ini, di sini `Flag_Aktif = 'T'` justru
   TIDAK memadai. Yang diminta adalah lingkungan yang benar-benar bersih: nomor siklus
   berikutnya kembali ke `PA-2026-01`, dan daftar siklus di layar benar-benar kosong. Baris
   yang sekadar dinonaktifkan tetap memegang nomornya dan tetap muncul di beberapa hitungan.

   URUTAN PENGHAPUSAN ADALAH URUTAN KETERGANTUNGAN

   Dari daun ke akar. Membalik urutannya berarti foreign key menolak di tengah jalan, dan
   yang tersisa adalah setengah siklus yang tidak bisa dibuka maupun dihapus.

   SATU TRANSAKSI

   Seluruhnya dalam satu transaksi: penghapusan yang berhenti di tengah meninggalkan
   penugasan tanpa peserta dan hasil tanpa penugasan — keadaan yang tidak bisa dibersihkan
   lewat layar mana pun.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50560, 'DML-2026-109 menghapus data dan hanya untuk Web_HRIS pengembangan.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Sasarannya SELURUH siklus yang ada, dan itu memang yang diminta. Ditulis sebagai daftar
   supaya skrip ini tetap benar bila kelak dipakai untuk sebagian siklus saja: cukup ubah
   isi tabel di bawah, sisa skripnya tidak perlu disentuh. */
DECLARE @siklus TABLE (Id_PA_Cycle int PRIMARY KEY);

INSERT INTO @siklus (Id_PA_Cycle)
SELECT Id_PA_Cycle FROM N_PA_Cycle;

DECLARE @peserta TABLE (Id_PA_Participant int PRIMARY KEY);
DECLARE @penugasan TABLE (Id_PA_Assignment int PRIMARY KEY);
DECLARE @hasil TABLE (Id_PA_Result int PRIMARY KEY);
DECLARE @pintu TABLE (Id_PA_Assignment_Integration int PRIMARY KEY);
DECLARE @muatan TABLE (Id_PA_Assignment_Payload int PRIMARY KEY);
DECLARE @paket TABLE (Id_PA_Cycle_Question_Package int PRIMARY KEY);
DECLARE @aturan TABLE (Id_PA_Cycle_Rater_Policy_Snapshot int PRIMARY KEY);

INSERT INTO @peserta SELECT Id_PA_Participant FROM N_PA_Participant
    WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

INSERT INTO @penugasan SELECT Id_PA_Assignment FROM N_PA_Assignment
    WHERE PA_Participant_Id IN (SELECT Id_PA_Participant FROM @peserta);

INSERT INTO @hasil SELECT Id_PA_Result FROM N_PA_Result
    WHERE PA_Participant_Id IN (SELECT Id_PA_Participant FROM @peserta);

INSERT INTO @pintu SELECT Id_PA_Assignment_Integration FROM N_PA_Assignment_Integration
    WHERE PA_Assignment_Id IN (SELECT Id_PA_Assignment FROM @penugasan);

INSERT INTO @muatan SELECT Id_PA_Assignment_Payload FROM N_PA_Assignment_Payload
    WHERE PA_Assignment_Integration_Id IN (SELECT Id_PA_Assignment_Integration FROM @pintu);

INSERT INTO @paket SELECT Id_PA_Cycle_Question_Package FROM N_PA_Cycle_Question_Package
    WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

INSERT INTO @aturan SELECT Id_PA_Cycle_Rater_Policy_Snapshot FROM N_PA_Cycle_Rater_Policy_Snapshot
    WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

/* Dihitung ke variabel lebih dulu: SQL Server menolak sub-kueri di dalam CONCAT pada
   PRINT, dan penolakannya terjadi saat batch DIKOMPILASI - seluruh blok gagal sebelum
   satu baris pun terhapus. */
DECLARE @nSiklus int    = (SELECT COUNT(*) FROM @siklus);
DECLARE @nPeserta int   = (SELECT COUNT(*) FROM @peserta);
DECLARE @nPenugasan int = (SELECT COUNT(*) FROM @penugasan);
DECLARE @nHasil int     = (SELECT COUNT(*) FROM @hasil);

PRINT CONCAT('siklus: ', @nSiklus);
PRINT CONCAT('peserta: ', @nPeserta);
PRINT CONCAT('penugasan: ', @nPenugasan);
PRINT CONCAT('hasil: ', @nHasil);

BEGIN TRANSACTION;

/* --- 1. Jawaban penilai, dari daun ke pangkalnya --------------------------- */

DELETE FROM N_PA_Assignment_Score
WHERE PA_Assignment_Payload_Id IN (SELECT Id_PA_Assignment_Payload FROM @muatan);

DELETE FROM N_PA_Assignment_Payload
WHERE Id_PA_Assignment_Payload IN (SELECT Id_PA_Assignment_Payload FROM @muatan);

DELETE FROM N_PA_Assignment_Integration
WHERE Id_PA_Assignment_Integration IN (SELECT Id_PA_Assignment_Integration FROM @pintu);

/* --- 2. Hasil dan turunannya ----------------------------------------------- *
 * Catatan hasil dihapus lebih dulu karena ia menunjuk DUA arah sekaligus: ke hasil dan ke
 * penugasan. Menyisakannya berarti salah satu dari keduanya menolak dihapus.            */

DELETE FROM N_PA_Result_Note
WHERE PA_Result_Id IN (SELECT Id_PA_Result FROM @hasil);

DELETE FROM N_PA_Result_Competency
WHERE PA_Result_Id IN (SELECT Id_PA_Result FROM @hasil);

DELETE FROM N_PA_Result_Rater_Category
WHERE PA_Result_Id IN (SELECT Id_PA_Result FROM @hasil);

DELETE FROM N_PA_Calibration_History
WHERE PA_Result_Id IN (SELECT Id_PA_Result FROM @hasil);

DELETE FROM N_PA_Result
WHERE Id_PA_Result IN (SELECT Id_PA_Result FROM @hasil);

/* --- 3. Penugasan dan peserta ---------------------------------------------- */

DELETE FROM N_PA_Assignment
WHERE Id_PA_Assignment IN (SELECT Id_PA_Assignment FROM @penugasan);

DELETE FROM N_PA_Participant_Pillar_Override
WHERE PA_Participant_Id IN (SELECT Id_PA_Participant FROM @peserta);

DELETE FROM N_PA_Participant_Rater_Override
WHERE PA_Participant_Id IN (SELECT Id_PA_Participant FROM @peserta);

DELETE FROM N_PA_Participant_Requirement_Override
WHERE PA_Participant_Id IN (SELECT Id_PA_Participant FROM @peserta);

DELETE FROM N_PA_Participant
WHERE Id_PA_Participant IN (SELECT Id_PA_Participant FROM @peserta);

/* --- 4. Paket soal siklus --------------------------------------------------- */

DELETE FROM N_PA_Cycle_Question_Package_Item
WHERE PA_Cycle_Question_Package_Id IN (SELECT Id_PA_Cycle_Question_Package FROM @paket);

DELETE FROM N_PA_Cycle_Question_Package
WHERE Id_PA_Cycle_Question_Package IN (SELECT Id_PA_Cycle_Question_Package FROM @paket);

/* --- 5. Cetak biru siklus --------------------------------------------------- *
 * Inilah salinan master yang dibuat saat siklus lahir. Ia dihapus bersama siklusnya, dan
 * masternya sendiri tidak tersentuh sama sekali.                                        */

DELETE FROM N_PA_Cycle_Rater_Policy_Detail_Snapshot
WHERE PA_Cycle_Rater_Policy_Snapshot_Id IN (SELECT Id_PA_Cycle_Rater_Policy_Snapshot FROM @aturan);

DELETE FROM N_PA_Cycle_Rater_Policy_Snapshot
WHERE Id_PA_Cycle_Rater_Policy_Snapshot IN (SELECT Id_PA_Cycle_Rater_Policy_Snapshot FROM @aturan);

DELETE FROM N_PA_Cycle_Score_Range_Snapshot
WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

DELETE FROM N_PA_Cycle_Pillar_Weight_Snapshot
WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

DELETE FROM N_PA_Cycle_Competency_Requirement_Snapshot
WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

DELETE FROM N_PA_Cycle_Pre_Exclusion
WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

DELETE FROM N_PA_Cycle_Setup_Step
WHERE PA_Cycle_Id IN (SELECT Id_PA_Cycle FROM @siklus);

/* --- 6. Siklusnya sendiri --------------------------------------------------- */

DELETE FROM N_PA_Cycle
WHERE Id_PA_Cycle IN (SELECT Id_PA_Cycle FROM @siklus);

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ *
 * Dua arah, dan keduanya penting. Yang pertama: tidak ada sisa yatim — baris yang
 * siklusnya sudah tidak ada tetapi barisnya masih berdiri. Yang kedua: MASTER MASIH UTUH.
 * Penghapusan yang kebablasan ke master tidak akan berbunyi sebagai galat; ia baru
 * ketahuan saat siklus berikutnya lahir tanpa satu pun kompetensi.                      */

DECLARE @yatim int =
      (SELECT COUNT(*) FROM N_PA_Participant)
    + (SELECT COUNT(*) FROM N_PA_Assignment)
    + (SELECT COUNT(*) FROM N_PA_Result)
    + (SELECT COUNT(*) FROM N_PA_Assignment_Integration)
    + (SELECT COUNT(*) FROM N_PA_Assignment_Payload)
    + (SELECT COUNT(*) FROM N_PA_Assignment_Score)
    + (SELECT COUNT(*) FROM N_PA_Result_Note)
    + (SELECT COUNT(*) FROM N_PA_Result_Competency)
    + (SELECT COUNT(*) FROM N_PA_Result_Rater_Category)
    + (SELECT COUNT(*) FROM N_PA_Cycle_Question_Package)
    + (SELECT COUNT(*) FROM N_PA_Cycle_Question_Package_Item)
    + (SELECT COUNT(*) FROM N_PA_Cycle_Rater_Policy_Snapshot)
    + (SELECT COUNT(*) FROM N_PA_Cycle_Setup_Step)
    + (SELECT COUNT(*) FROM N_PA_Cycle);

IF @yatim > 0
    THROW 50561, 'Masih ada baris berlingkup siklus yang tersisa; lingkungan belum benar-benar bersih.', 1;

DECLARE @master int = (
    SELECT CASE WHEN (SELECT COUNT(*) FROM N_PA_Competency) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Competency_Type) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Rater_Policy) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Rater_Category) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Pillar_Weight) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Score_Range) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Hav_Quadrant) = 0 THEN 1 ELSE 0 END
         + CASE WHEN (SELECT COUNT(*) FROM N_PA_Question_Binding_Master) = 0 THEN 1 ELSE 0 END
);

IF @master > 0
    THROW 50562, 'Ada master PA yang ikut terhapus; siklus berikutnya akan lahir tanpa cetak biru.', 1;

/* VERIFIKASI — master yang harus tetap berdiri, beserta jumlah barisnya. */
SELECT 'N_PA_Competency_Type' AS master, COUNT(*) AS baris FROM N_PA_Competency_Type
UNION ALL SELECT 'N_PA_Competency', COUNT(*) FROM N_PA_Competency
UNION ALL SELECT 'N_PA_Competency_Requirement', COUNT(*) FROM N_PA_Competency_Requirement
UNION ALL SELECT 'N_PA_Rater_Category', COUNT(*) FROM N_PA_Rater_Category
UNION ALL SELECT 'N_PA_Rater_Policy', COUNT(*) FROM N_PA_Rater_Policy
UNION ALL SELECT 'N_PA_Rater_Policy_Detail', COUNT(*) FROM N_PA_Rater_Policy_Detail
UNION ALL SELECT 'N_PA_Pillar_Weight', COUNT(*) FROM N_PA_Pillar_Weight
UNION ALL SELECT 'N_PA_Score_Range', COUNT(*) FROM N_PA_Score_Range
UNION ALL SELECT 'N_PA_Hav_Quadrant', COUNT(*) FROM N_PA_Hav_Quadrant
UNION ALL SELECT 'N_PA_Question_Binding_Master', COUNT(*) FROM N_PA_Question_Binding_Master
UNION ALL SELECT 'N_PA_Cycle (harus 0)', COUNT(*) FROM N_PA_Cycle;
