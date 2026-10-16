-- Change ID: DDL-2026-045-bootstrap-visitor-master-data
-- MANUAL RECOVERY AID ONLY. THE AGENT MUST NEVER EXECUTE THIS FILE.
-- This would destroy Visitor master data and is intentionally blocked.

THROW 51000, 'Blocked manual DROP script. Review dependencies and remove this guard only during an approved recovery.', 1;
GO

DROP TABLE N_EMI_VISITOR_Master_Vehicle_Model;
DROP TABLE N_EMI_VISITOR_Master_Durasi_Permit;
DROP TABLE N_EMI_VISITOR_Master_Purpose;
DROP TABLE N_EMI_VISITOR_Master_Plat_Kendaraan;
DROP TABLE N_EMI_VISITOR_Master_Vehicle_Color;
DROP TABLE N_EMI_VISITOR_Master_Vehicle_Type;
GO
