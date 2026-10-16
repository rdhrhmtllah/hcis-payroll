/* Change ID: DDL-2026-061-hris-organization-relationship
   BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan file ini.
   Eksekusi membuang seluruh histori relasi organisasi dan master tipenya. */

IF OBJECT_ID('N_HRIS_Organization_Relationship', 'U') IS NOT NULL
    DROP TABLE N_HRIS_Organization_Relationship;
GO

IF OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NOT NULL
    DROP TABLE N_HRIS_Organization_Relationship_Type;
GO
