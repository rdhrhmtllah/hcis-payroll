-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-061-index-approval-role-lookup
--
-- Dua index ini sudah ada di development tetapi tidak pernah tercatat sebagai
-- change apa pun, sehingga tidak pernah ikut terbawa ke production. Berkas ini
-- mencatatnya secara retroaktif agar ada jalur resmi ke production.
--
-- Tidak ada perubahan data. Kedua pernyataan idempoten.
-- Production berjalan di Express Edition: WITH (ONLINE = ON) tidak didukung,
-- jadi kedua index sengaja dibangun offline.

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('HRIS_Approval_Request')
      AND name = 'IX_Approval_Request_Trans_Order'
)
CREATE NONCLUSTERED INDEX IX_Approval_Request_Trans_Order
ON HRIS_Approval_Request (No_Transaksi, Request_Order_Flow);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Role_User')
      AND name = 'IX_RoleUser_UserId'
)
CREATE NONCLUSTERED INDEX IX_RoleUser_UserId
ON N_HRIS_Role_User (UserId_Web);
GO
