-- database/ddl/DDL-2026-007-create-pengumpulan-data-tables/drop.sql
--
-- Skrip reset MANUAL untuk developer. Agent tidak boleh menjalankan berkas ini.
-- Menghapus keempat tabel beserta seluruh isinya, termasuk NIK KTP karyawan yang
-- sudah masuk. Jalankan hanya di development, dan hanya bila memang ingin
-- membangun ulang dari nol.
--
-- Indeks ikut terhapus bersama tabelnya, jadi tidak perlu DROP INDEX terpisah.
-- Urutan penghapusan bebas: keempat tabel tidak punya FOREIGN KEY antar mereka,
-- mengikuti pola tabel N_HRIS_* lain di repo ini.

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Audit')
    DROP TABLE N_HRIS_Pengumpulan_Data_Audit;

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Config')
    DROP TABLE N_HRIS_Pengumpulan_Data_Config;

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Status')
    DROP TABLE N_HRIS_Pengumpulan_Data_Status;

IF EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Submission')
    DROP TABLE N_HRIS_Pengumpulan_Data_Submission;
