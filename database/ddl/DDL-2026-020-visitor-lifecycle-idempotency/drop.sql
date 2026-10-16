-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
DROP INDEX UX_VISITOR_Permit_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Permit;
DROP INDEX UX_VISITOR_Tamu_Requester_Idempotency ON N_EMI_VISITOR_Transaksi_Tamu;
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit DROP COLUMN Idempotency_Key;
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu DROP COLUMN Idempotency_Key;
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Waktu_Aktual_Masuk;
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Waktu_Aktual_Keluar;
