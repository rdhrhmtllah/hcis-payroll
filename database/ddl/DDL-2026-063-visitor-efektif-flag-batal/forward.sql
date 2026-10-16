-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-063-visitor-efektif-flag-batal
--
-- Kolom Flag_Batal pada N_VISITOR_Permit_Efektif dan N_VISITOR_Tamu_Efektif
-- sudah ada di development tetapi tidak pernah tercatat sebagai change apa
-- pun, sama seperti kolom NIK di DDL-2026-062. Berkas pembuat tabelnya,
-- database/visitor/2026_07_30_visitor_efektif_projection.sql, tidak
-- menyebutnya sama sekali.
--
-- Sampai sekarang tidak ada satu baris kode pun yang menulisnya. Mulai change
-- ini kolomnya dipakai: pencabutan akses menulis 'Y', penyetujuan/sync
-- menulis 'T'. Karena itu kolomnya WAJIB ada di production sebelum kodenya
-- rilis, kalau tidak setiap pencabutan gagal dengan
-- "Invalid column name 'Flag_Batal'".
--
-- Arti nilai:
--   'T' = akses tidak dibatalkan  (Status_Efektif 'AKTIF' atau 'EXPIRED')
--   'Y' = akses dibatalkan        (Status_Efektif 'DIBATALKAN')
--
-- Nilai ini pasangan Status_Efektif, bukan sumber kebenaran yang berdiri
-- sendiri. Bila keduanya berselisih, Status_Efektif yang benar.
--
-- WITH VALUES dipakai supaya baris yang sudah ada ikut terisi 'T', bukan
-- NULL. Di production kedua tabel ini masih kosong per audit 2026-09-01,
-- jadi tidak ada pengisian massal. Pelurusan baris lama di development ada di
-- database/dml/DDL-2026-063-visitor-efektif-flag-batal/data-change.sql.
--
-- Kedua pernyataan idempoten.

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Flag_Batal') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif
    ADD Flag_Batal char(1) NULL
        CONSTRAINT DF_VISITOR_Permit_Efektif_Flag_Batal DEFAULT ('T') WITH VALUES;
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Flag_Batal') IS NULL
ALTER TABLE N_VISITOR_Tamu_Efektif
    ADD Flag_Batal char(1) NULL
        CONSTRAINT DF_VISITOR_Tamu_Efektif_Flag_Batal DEFAULT ('T') WITH VALUES;
GO
