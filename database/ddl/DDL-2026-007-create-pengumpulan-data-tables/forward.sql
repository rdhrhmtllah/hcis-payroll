-- database/ddl/DDL-2026-007-create-pengumpulan-data-tables/forward.sql
-- Jalankan manual di SQL Server (Web_HRIS - sesuaikan environment target).
-- Desain lengkap: docs/superpowers/specs/2026-08-14-pengumpulan-data-karyawan-design.md
--
-- Empat tabel baru. Tidak ada tabel lama yang diubah, ditambah kolom, atau disentuh.
-- Semua terikat ke Karyawan lewat Kode_Karyawan (tanpa FOREIGN KEY, mengikuti pola
-- tabel N_HRIS_* lain di repo ini).

-- ---------------------------------------------------------------------------
-- 1. Submission — jawaban karyawan, disimpan sebagai JSON.
--    Menambah field di kemudian hari TIDAK memerlukan ALTER TABLE:
--    cukup naikkan Versi_Skema di config/pengumpulan_data.php.
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Submission')
CREATE TABLE N_HRIS_Pengumpulan_Data_Submission (
    Id                     INT IDENTITY(1,1) PRIMARY KEY,
    Kode_Karyawan          VARCHAR(20)   NOT NULL,
    Versi_Skema            INT           NOT NULL DEFAULT 1,
    Data_Json              NVARCHAR(MAX) NOT NULL,
    Snapshot_Lama_Json     NVARCHAR(MAX) NULL,
    Flag_Aktif             CHAR(1)       NOT NULL DEFAULT 'Y',   -- 'Y' = versi berlaku, 'T' = riwayat
    Submitted_At           DATETIME      NOT NULL,
    Submitted_Ip           VARCHAR(45)   NULL,
    Submitted_User_Agent   VARCHAR(500)  NULL,
    Created_At             DATETIME      NOT NULL,
    Updated_At             DATETIME      NULL,
    CONSTRAINT CK_Pengumpulan_Submission_DataJson  CHECK (ISJSON(Data_Json) = 1),
    CONSTRAINT CK_Pengumpulan_Submission_SnapJson  CHECK (Snapshot_Lama_Json IS NULL OR ISJSON(Snapshot_Lama_Json) = 1),
    CONSTRAINT CK_Pengumpulan_Submission_FlagAktif CHECK (Flag_Aktif IN ('Y','T'))
);

-- Satu karyawan hanya boleh punya SATU baris aktif. Kiriman ulang setelah dibuka
-- kunci wajib menurunkan baris lama ke 'T' lebih dulu -- indeks ini yang memaksanya.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_Pengumpulan_Submission_Aktif')
CREATE UNIQUE INDEX UX_Pengumpulan_Submission_Aktif
    ON N_HRIS_Pengumpulan_Data_Submission (Kode_Karyawan)
    WHERE Flag_Aktif = 'Y';

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Pengumpulan_Submission_Karyawan')
CREATE INDEX IX_Pengumpulan_Submission_Karyawan
    ON N_HRIS_Pengumpulan_Data_Submission (Kode_Karyawan, Submitted_At DESC);

-- ---------------------------------------------------------------------------
-- 2. Status — satu baris per karyawan. INILAH yang dibaca di setiap kunjungan
--    halaman, sengaja dipisah dari tabel submission supaya yang dibaca berulang
--    adalah baris ringan, bukan JSON gemuk.
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Status')
CREATE TABLE N_HRIS_Pengumpulan_Data_Status (
    Kode_Karyawan          VARCHAR(20)   NOT NULL PRIMARY KEY,
    Status                 VARCHAR(20)   NOT NULL DEFAULT 'BELUM',  -- BELUM | TERKUNCI | SELESAI
    Jumlah_Tunda           INT           NOT NULL DEFAULT 0,
    Terakhir_Tunda_At      DATETIME      NULL,
    Is_Dikecualikan        CHAR(1)       NOT NULL DEFAULT 'T',      -- 'Y' = dibebaskan dari modal
    Dikecualikan_Oleh      VARCHAR(50)   NULL,
    Dikecualikan_At        DATETIME      NULL,
    Alasan_Pengecualian    VARCHAR(500)  NULL,
    Dibuka_Kunci_Oleh      VARCHAR(50)   NULL,
    Dibuka_Kunci_At        DATETIME      NULL,
    Alasan_Buka_Kunci      VARCHAR(500)  NULL,
    Draft_Json             NVARCHAR(MAX) NULL,                      -- jawaban sementara wizard
    Draft_Langkah          INT           NULL,                      -- langkah terakhir yang dibuka
    Draft_Updated_At       DATETIME      NULL,
    Selesai_At             DATETIME      NULL,
    Created_At             DATETIME      NOT NULL,
    Updated_At             DATETIME      NULL,
    CONSTRAINT CK_Pengumpulan_Status_Status     CHECK (Status IN ('BELUM','TERKUNCI','SELESAI')),
    CONSTRAINT CK_Pengumpulan_Status_Dikecuali  CHECK (Is_Dikecualikan IN ('Y','T')),
    CONSTRAINT CK_Pengumpulan_Status_DraftJson  CHECK (Draft_Json IS NULL OR ISJSON(Draft_Json) = 1)
);

-- Dipakai halaman admin: menyaring "siapa yang belum" dan "siapa yang dikecualikan".
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Pengumpulan_Status_Filter')
CREATE INDEX IX_Pengumpulan_Status_Filter
    ON N_HRIS_Pengumpulan_Data_Status (Status, Is_Dikecualikan)
    INCLUDE (Jumlah_Tunda, Selesai_At);

-- ---------------------------------------------------------------------------
-- 3. Config — satu baris. Hal-hal yang perlu bisa diubah saat sistem berjalan
--    TANPA deploy. Perilaku teknis (frekuensi modal, jatah penundaan, definisi
--    field) tetap di config/pengumpulan_data.php, bukan di sini.
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Config')
CREATE TABLE N_HRIS_Pengumpulan_Data_Config (
    Id                     INT IDENTITY(1,1) PRIMARY KEY,
    Is_Aktif               CHAR(1)       NOT NULL DEFAULT 'Y',   -- rem tangan seluruh kampanye
    Judul_Pengumuman       VARCHAR(200)  NOT NULL,
    Teks_Pengumuman        VARCHAR(1000) NOT NULL,
    Label_Tombol_Isi       VARCHAR(100)  NOT NULL DEFAULT 'Isi sekarang',
    Label_Tombol_Tunda     VARCHAR(100)  NOT NULL DEFAULT 'Nanti dulu',
    Updated_Oleh           VARCHAR(50)   NULL,
    Created_At             DATETIME      NOT NULL,
    Updated_At             DATETIME      NULL,
    CONSTRAINT CK_Pengumpulan_Config_Aktif CHECK (Is_Aktif IN ('Y','T'))
);

-- Baris awal. Sengaja dimatikan (Is_Aktif = 'T') supaya fase 1-2 bisa naik ke
-- produksi tanpa satu pun karyawan melihat modal. Nyalakan saat siap rilis.
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Pengumpulan_Data_Config)
INSERT INTO N_HRIS_Pengumpulan_Data_Config
    (Is_Aktif, Judul_Pengumuman, Teks_Pengumuman, Created_At)
VALUES
    ('T',
     'Data kamu, sekarang di tanganmu',
     'Kami sedang merapikan data karyawan. Dua menit, sekali saja - dan berkasmu di HR jadi benar.',
     GETDATE());

-- ---------------------------------------------------------------------------
-- 4. Audit — siapa mengaksi apa, ke siapa, kapan.
-- ---------------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_HRIS_Pengumpulan_Data_Audit')
CREATE TABLE N_HRIS_Pengumpulan_Data_Audit (
    Id                     INT IDENTITY(1,1) PRIMARY KEY,
    Aksi                   VARCHAR(40)   NOT NULL,   -- BUKA_KUNCI | TAMBAH_PENGECUALIAN | HAPUS_PENGECUALIAN | UBAH_SAKELAR | KIRIM_PENGINGAT
    Kode_Karyawan_Target   VARCHAR(20)   NULL,       -- NULL untuk aksi yang tidak menyasar satu orang
    Dilakukan_Oleh         VARCHAR(50)   NOT NULL,
    Nilai_Sebelum          VARCHAR(200)  NULL,
    Nilai_Sesudah          VARCHAR(200)  NULL,
    Keterangan             VARCHAR(500)  NULL,
    Ip_Address             VARCHAR(45)   NULL,
    Created_At             DATETIME      NOT NULL
);

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Pengumpulan_Audit_Target')
CREATE INDEX IX_Pengumpulan_Audit_Target
    ON N_HRIS_Pengumpulan_Data_Audit (Kode_Karyawan_Target, Created_At DESC);

-- ---------------------------------------------------------------------------
-- CATATAN: baris menu (N_HRIS_Menus) untuk ESS dan Administrator HCIS BELUM
-- termasuk di berkas ini. Skrip INSERT-nya diberikan terpisah pada fase 4,
-- setelah struktur N_HRIS_Menus dan N_HRIS_Grup_Fitur_Map dibaca langsung dari
-- database supaya kolom dan id induknya tepat -- menebaknya di sini justru
-- berisiko menyisipkan menu yatim.
-- ---------------------------------------------------------------------------
