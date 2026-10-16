/* Change ID: DML-2026-157-visitor-bersih-transaksi-uat-permit

   KHUSUS DEVELOPMENT (Web_HRIS). JANGAN dijalankan di production.

   Menyiapkan database development untuk UAT VMS Permit 1 Oktober 2026
   (docs/uat/IT_BA_UAT VMS-PERMIT.docx):
   1. Mengosongkan SELURUH data transaksi Visitor (permit + tamu) beserta
      jejaknya: approval request, notifikasi, booking inspeksi, proyeksi
      Efektif. Diukur 2026-09-30: 91 permit, 48 tamu, 93 approval request,
      168 notifikasi — semuanya data uji/dummy development.
   2. Menonaktifkan sisa uji otomatis di master inspeksi (pos "Pos Uji
      Rebutan Slot", shift "Shift Uji Rebutan" dan pola-nya) yang dibuat
      suite uji dengan Created_By = 'UJI'.
   3. Menonaktifkan pertanyaan checklist #15 (khusus MERAH) yang berisi teks
      uji; MERAH tetap punya pertanyaan khusus #17.

   Master data (kategori, durasi, pertanyaan, kendaraan, plat), kartu NFC
   & binding, pos/shift/pola asli, role, dan akses halaman TIDAK disentuh.
   Cadangan JSON seluruh baris yang dihapus dibuat sebelum berkas ini
   dijalankan (lihat README). */

IF DB_NAME() <> 'Web_HRIS'
BEGIN
    RAISERROR('BERHENTI: berkas ini hanya untuk database development Web_HRIS.', 16, 1);
    SET NOEXEC ON;
END;
GO

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ---- 1. Jejak di luar tabel Visitor (harus sebelum header dihapus) ---- */
DELETE FROM HRIS_Approval_Request
WHERE No_Transaksi IN (SELECT No_Transaksi FROM N_EMI_VISITOR_Transaksi_Permit
                       UNION SELECT No_Transaksi FROM N_EMI_VISITOR_Transaksi_Tamu);

DELETE FROM N_HRIS_Notification WHERE Module = 'VISITOR';

/* ---- 2. Permit: anak dulu, header terakhir (urutan FK) ---- */
DELETE FROM N_EMI_VISITOR_Transaksi_Permit_Checklist;
DELETE FROM N_EMI_VISITOR_Transaksi_Permit_Dokumen;
DELETE FROM N_EMI_VISITOR_Transaksi_Permit_Koreksi;
DELETE FROM N_EMI_VISITOR_Transaksi_Permit_Detail;
DELETE FROM N_VISITOR_Inspeksi_Booking;
DELETE FROM N_VISITOR_Permit_Efektif;
DELETE FROM N_EMI_VISITOR_Transaksi_Permit;

/* ---- 3. Tamu ---- */
DELETE FROM N_EMI_VISITOR_Transaksi_Tamu_Detail;
DELETE FROM N_VISITOR_Tamu_Efektif;
DELETE FROM N_EMI_VISITOR_Transaksi_Tamu;

/* ---- 4. Sisa suite uji di master inspeksi: nonaktifkan, bukan hapus ---- */
UPDATE N_VISITOR_Inspeksi_Pola
SET Flag_Aktif = 'T', Updated_At = GETDATE(), Updated_By = 'DML-2026-157'
WHERE Flag_Aktif = 'Y'
  AND (Id_Pos_Inspeksi IN (SELECT Id_Pos_Inspeksi FROM N_VISITOR_Master_Pos_Inspeksi WHERE Created_By = 'UJI')
       OR Id_Shift_Inspeksi IN (SELECT Id_Shift_Inspeksi FROM N_VISITOR_Master_Shift_Inspeksi WHERE Created_By = 'UJI'));

UPDATE N_VISITOR_Master_Pos_Inspeksi
SET Flag_Aktif = 'T', Updated_At = GETDATE(), Updated_By = 'DML-2026-157'
WHERE Created_By = 'UJI' AND Flag_Aktif = 'Y';

UPDATE N_VISITOR_Master_Shift_Inspeksi
SET Flag_Aktif = 'T', Updated_At = GETDATE(), Updated_By = 'DML-2026-157'
WHERE Created_By = 'UJI' AND Flag_Aktif = 'Y';

/* ---- 5. Pertanyaan checklist MERAH berisi teks uji ---- */
UPDATE N_EMI_VISITOR_Master_Approval_Question
SET Flag_Aktif = 'T'
WHERE Id_Approval_Question = 15 AND Teks_Pertanyaan LIKE 'Siapa suruh tanya%' AND Flag_Aktif = 'Y';

COMMIT TRANSACTION;
GO

SET NOEXEC OFF;
GO
