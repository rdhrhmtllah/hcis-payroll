-- Change ID: DDL-2026-077-visitor-permit-coklat
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Menjatuhkan kolom di bawah menghapus jendela jam seluruh registrasi tamu dan
-- masa berlaku seluruh permit coklat. Baris proyeksi permit coklat yang
-- terlanjur ada akan kehilangan akhir masa berlakunya dan berhenti bisa
-- dikedaluwarsakan sapuan.

DROP INDEX IF EXISTS IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir ON N_VISITOR_Permit_Efektif;
GO

ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN IF EXISTS Sumber_Permit, Waktu_Berakhir, Waktu_Mulai_Berlaku;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN IF EXISTS Waktu_Selesai_Kunjungan, Waktu_Mulai_Kunjungan;
GO
