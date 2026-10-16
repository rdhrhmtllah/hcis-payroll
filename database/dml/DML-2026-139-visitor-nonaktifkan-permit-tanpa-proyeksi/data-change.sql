/* Change ID: DML-2026-139-visitor-nonaktifkan-permit-tanpa-proyeksi */
/*
    Nonaktifkan satu permit COKLAT mitra (PMT0926-0012) yang disetujui
    14 Sep 2026 tanpa jendela berlaku, sebelum commit ec786d63 mewajibkan
    inspeksi & jendela sebelum approve. Ia tidak pernah punya baris
    N_VISITOR_Permit_Efektif, jadi tidak ada akses gerbang yang bisa dicabut
    (celah #7, keputusan user 26 Sep 2026: opsi a).

    Predikat dikunci ke nomornya DAN keadaannya: bila baris ini tidak ada atau
    keadaannya berbeda (mis. di production), perubahan menyentuh 0 baris.
    Idempoten: dijalankan ulang menyentuh 0 baris.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE p
   SET p.Flag_Aktif = 'T',
       p.Updated_At = SYSDATETIME(),
       p.Updated_By = 'DML-2026-139'
  FROM N_EMI_VISITOR_Transaksi_Permit p
 WHERE p.No_Transaksi = 'PMT0926-0012'
   AND p.Flag_Aktif = 'Y'
   AND p.Flag_Approval = 'Y'
   AND p.Sumber_Pengajuan = 'API'
   AND NOT EXISTS (SELECT 1 FROM N_VISITOR_Permit_Efektif pe WHERE pe.No_Transaksi = p.No_Transaksi);

COMMIT TRANSACTION;

/* Verifikasi. */
/*
SELECT No_Transaksi, Flag_Aktif, Flag_Approval, Updated_By, Updated_At
  FROM N_EMI_VISITOR_Transaksi_Permit WHERE No_Transaksi = 'PMT0926-0012';
*/
