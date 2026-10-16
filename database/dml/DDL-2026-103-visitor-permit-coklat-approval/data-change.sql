-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-103-visitor-permit-coklat-approval
--
-- Menutup permit COKLAT berformat LAMA.
--
-- Sebelum change ini, permit COKLAT ditulis langsung ke proyeksi gerbang dengan
-- No_Transaksi milik KUNJUNGAN-nya dan tanpa transaksi permit sama sekali.
-- Sesudahnya, setiap permit COKLAT adalah transaksi PMT tersendiri yang harus
-- disetujui lebih dulu, dan barisnya di proyeksi berkunci nomor permit itu.
--
-- Baris berformat lama tidak bisa ditelusuri oleh kode baru: sapuan kedaluwarsa
-- "tamu sudah keluar" dan penutupan permit saat check-out keduanya mencari
-- tautan ke transaksi permit yang tidak pernah ada pada baris ini. Dibiarkan
-- hidup, ia menjadi izin gerbang yang tidak bisa dimatikan siapa pun kecuali
-- lewat lewatnya Waktu_Berakhir.
--
-- Karena itu barisnya ditutup, bukan dikonversi: mengarangkan transaksi permit
-- berikut riwayat persetujuannya berarti memalsukan siapa yang menyetujui apa.
--
-- Tidak ada baris berformat lama di production — DDL-2026-077 dan DDL-2026-079
-- keduanya masih PENDING di sana, sehingga di production pernyataan ini
-- memutakhirkan nol baris. Ia tetap disertakan supaya urutan yang dijalankan di
-- production identik dengan yang diverifikasi di development.

UPDATE pe
   SET pe.Status_Efektif = 'EXPIRED',
       pe.Alasan_Status = 'Ditutup: permit COKLAT format lama (DDL-2026-103)',
       pe.Updated_At = GETDATE(),
       pe.Updated_Oleh_Nama = 'Sistem (DDL-2026-103)',
       pe.Flag_Sync = 'T'
  FROM N_VISITOR_Permit_Efektif pe
 WHERE pe.Sumber_Permit = 'TAMU'
   AND pe.Status_Efektif = 'AKTIF'
   AND EXISTS (SELECT 1
                 FROM N_EMI_VISITOR_Transaksi_Tamu t
                WHERE t.No_Transaksi = pe.No_Transaksi);
GO

-- Verifikasi: harus 0.
SELECT COUNT(*) AS sisa_permit_coklat_format_lama
  FROM N_VISITOR_Permit_Efektif pe
 WHERE pe.Sumber_Permit = 'TAMU'
   AND pe.Status_Efektif = 'AKTIF'
   AND EXISTS (SELECT 1
                 FROM N_EMI_VISITOR_Transaksi_Tamu t
                WHERE t.No_Transaksi = pe.No_Transaksi);
GO
