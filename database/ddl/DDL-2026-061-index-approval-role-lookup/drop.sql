-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Change ID: DDL-2026-061-index-approval-role-lookup

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('HRIS_Approval_Request')
      AND name = 'IX_Approval_Request_Trans_Order'
)
DROP INDEX IX_Approval_Request_Trans_Order ON HRIS_Approval_Request;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Role_User')
      AND name = 'IX_RoleUser_UserId'
)
DROP INDEX IX_RoleUser_UserId ON N_HRIS_Role_User;
GO
