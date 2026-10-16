-- Change ID: DDL-2026-100-visitor-daftar-anggota-tamu
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Menjatuhkan kolom ini menghapus daftar nama seluruh rombongan yang pernah
-- didaftarkan. Jumlah_Tamu hanya menyimpan angkanya dan tidak dapat memulihkan
-- nama yang hilang.

ALTER TABLE N_VISITOR_Tamu_Efektif
    DROP CONSTRAINT IF EXISTS CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json;
GO
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN IF EXISTS Daftar_Anggota_Json;
GO

ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail
    DROP CONSTRAINT IF EXISTS CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json;
GO
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu_Detail DROP COLUMN IF EXISTS Daftar_Anggota_Json;
GO
