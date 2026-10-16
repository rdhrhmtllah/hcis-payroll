-- Change ID: DDL-2026-104-visitor-kategori-permit-akses
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Peringatan: tanpa kolom ini kodenya membaca seluruh kategori aktif sebagai
-- boleh tampil, sehingga COKLAT kembali ditawarkan kepada karyawan di form
-- pengajuan permit.

ALTER TABLE N_EMI_VISITOR_Master_Kategori_Permit DROP COLUMN IF EXISTS Flag_Muncul_Pengajuan;
GO
