-- database/ddl/DDL-2026-009-create-report-template-tables/drop.sql
-- ALAT BANTU MANUAL. Tidak dijalankan agent.

DROP INDEX IF EXISTS IX_Report_Template_Akses_Cari ON N_HRIS_Report_Template_Akses;
DROP INDEX IF EXISTS UX_Report_Template_Akses_Sasaran ON N_HRIS_Report_Template_Akses;
DROP TABLE IF EXISTS N_HRIS_Report_Template_Akses;
DROP INDEX IF EXISTS IX_Report_Template_Bagi ON N_HRIS_Report_Template;
DROP INDEX IF EXISTS IX_Report_Template_Pemilik ON N_HRIS_Report_Template;
DROP INDEX IF EXISTS UX_Report_Template_Kode ON N_HRIS_Report_Template;
DROP TABLE IF EXISTS N_HRIS_Report_Template;
