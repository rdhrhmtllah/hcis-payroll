/* =====================================================================
   DDL-2026-118-rekap-skema-production
   ---------------------------------------------------------------------
   SATU BERKAS, SEKALI EKSEKUSI.

   Menutup SELURUH selisih skema antara development (Web_HRIS) dan
   production (emi_db_web_hr @ 34.143.236.80) yang ditemukan audit
   READ ONLY 16 September 2026:

       23 kolom  (10 SOP + 13 Visitor/RBAC/Pengumpulan Data)
        1 foreign key
        6 indeks (3 belum ada, 3 definisinya kedaluwarsa)
        8 constraint DEFAULT/CHECK

   Sumber temuan : docs/audit/daftar-temuan-sop-production-2026-09-16.md
                   docs/audit/adendum-pasca-merge-master-2026-09-16.md

   ---------------------------------------------------------------------
   BERKAS INI MENGGANTIKAN EKSEKUSI SEMBILAN DDL BERIKUT
   ---------------------------------------------------------------------
   Setelah berkas ini dijalankan, kesembilan DDL di bawah TIDAK PERLU
   dijalankan lagi. Isinya sudah tercakup seluruhnya di sini:

       DDL-2026-079-visitor-permit-api-b2b
       DDL-2026-100-visitor-daftar-anggota-tamu
       DDL-2026-101-visitor-tipe-tamu-permit-approver
       DDL-2026-102-visitor-tamu-api-b2b
       DDL-2026-103-visitor-permit-coklat-approval
       DDL-2026-104-visitor-kategori-permit-akses
       DDL-2026-110-hibah-role-per-halaman
       DDL-2026-110-cakupan-lokasi-pengumpulan-data
       (dan DDL-2026-114 yang sebelumnya dibuat untuk kolom SOP - artefaknya
        sudah dicabut, isinya pindah ke Bagian 1-2 di sini)

   Seluruh pernyataan tetap idempoten, jadi kalau salah satu DDL di atas
   terlanjur dijalankan lebih dulu, berkas ini akan melewatinya dengan
   selamat - dan sebaliknya.

   ---------------------------------------------------------------------
   YANG DI LUAR CAKUPAN
   ---------------------------------------------------------------------
   - DDL-2026-092-sinkronisasi-skema-production. Sampel Bagian 1-2 (10
     kolom) terbukti SUDAH ADA di production meski ledger menandainya
     PENDING, dan Bagian 5-nya sudah mati total karena ke-44 tabelnya sudah
     ada. Bagian 3 (indeks), 4 (default), 6 (trigger), dan 7 (foreign key)
     BELUM diperiksa dan tidak ikut di sini.
   - N_HRIS_KANDIDAT_Api_* dan kolom HRIS_KANDIDAT_Users - sistem rekrutmen
     terpisah, hanya dipakai middleware yatim. Dilewati atas keputusan user.
   - fn_GetShiftLengkap_Range - hanya dipanggil OvertimeController::
     userActive/userActiveAll yang tidak punya rute. Dilewati atas keputusan
     user.
   - View_Golongan_Sub_Golongan_Level_Jabatan (Flag_Extra_Meal, Flag_Harian).
     Dilewati atas keputusan user.
   - Seed data: DML-2026-115-seed-sop-prioritas berdiri sendiri sebagai DML.

   ---------------------------------------------------------------------
   PERHATIAN: BERKAS INI MEMUAT TIGA DROP INDEX
   ---------------------------------------------------------------------
   Bagian 11 membuang lalu membuat ulang tiga indeks yang definisinya di
   production sudah kedaluwarsa - dua di antaranya indeks UNIK:

     UX_VISITOR_Permit_Requester_Idempotency
        prod  : (Kode_Karyawan_Pemohon, Idempotency_Key), tanpa filter
        harus : (Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)
                WHERE Idempotency_Key IS NOT NULL
     UX_VISITOR_Tamu_Requester_Idempotency
        prod  : (Kode_Karyawan_PIC, Idempotency_Key), tanpa filter
        harus : (Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)
                WHERE Idempotency_Key IS NOT NULL
     IX_VISITOR_Permit_Api_Aplikasi
        prod  : tanpa filter
        harus : WHERE Id_Api_Aplikasi IS NOT NULL

   Tanpa Id_Api_Aplikasi di kunci, dua aplikasi mitra yang mengirim
   Idempotency_Key yang sama akan bertabrakan - persis masalah yang
   DDL-2026-079 dan 102 hendak selesaikan.

   Risikonya kecil tapi harus disadari: ketiga tabelnya BERISI 0 BARIS di
   production per 2026-09-16 (N_EMI_VISITOR_Transaksi_Permit 0,
   N_EMI_VISITOR_Transaksi_Tamu 0), jadi pembuatan ulang berlangsung
   seketika dan tidak mungkin gagal karena nilai kembar. Tiap DROP tetap
   dijaga: hanya dijalankan bila definisi lamanya memang terbukti
   kedaluwarsa, dan Bagian 12 melempar RAISERROR bila ada indeks yang tidak
   berhasil terbentuk kembali.

   ---------------------------------------------------------------------
   SIFAT PERUBAHAN
   ---------------------------------------------------------------------
   - 22 dari 23 kolom NULLABLE tanpa default -> operasi metadata saja.
     Satu kolom NOT NULL berdefault (N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role),
     lihat Bagian 9.
   - Tidak ada DROP TABLE, DROP COLUMN, TRUNCATE, UPDATE, DELETE, atau MERGE.
     Satu-satunya DROP adalah tiga DROP INDEX di Bagian 11, masing-masing
     langsung diikuti CREATE penggantinya.
   - Seluruh pernyataan idempoten: aman diulang, dan no-op penuh di
     development karena di sana semuanya sudah ada.
   - Nama DEFAULT, CHECK, dan INDEX disalin PERSIS dari DDL asal supaya
     tidak lahir objek kembar bernama beda.

   ---------------------------------------------------------------------
   KENAPA URUTANNYA SEPERTI INI
   ---------------------------------------------------------------------
   1. SELURUH KOLOM (Bagian 1-10) mendahului SELURUH INDEKS (Bagian 11).
      Empat dari enam indeks menyebut kolom yang baru lahir di berkas ini
      (Id_Api_Aplikasi, No_Transaksi_Tamu); membangunnya lebih dulu pasti
      gagal.
   2. Bagian 4 (DDL-100) WAJIB mendahului Bagian 5 (DDL-101). Dua tabel
      menerima kolom dari keduanya - N_EMI_VISITOR_Transaksi_Tamu_Detail dan
      N_VISITOR_Tamu_Efektif - dan SQL Server memberi column_id sesuai urutan
      penambahan. Membalik urutannya membuat ordinal production berbeda dari
      development.
   3. Foreign key dan CHECK selalu di BATCH TERPISAH setelah kolomnya. SQL
      Server mengompilasi satu batch utuh sebelum menjalankannya, jadi
      merujuk kolom yang baru dibuat di batch yang sama gagal dengan
      "Invalid column name".
   4. DEFAULT dipasang INLINE pada ALTER ... ADD. Untuk kolom NOT NULL itu
      wajib: tanpa default, ALTER ditolak pada tabel yang sudah berisi baris.
   5. Satu ALTER per kolom, masing-masing berpenjaga sendiri. Menggabungkan
      beberapa kolom dalam satu ALTER membuat penjaganya salah begitu
      sebagian kolom sudah ada.

   Target        : emi_db_web_hr (production)
   Dapat dijalankan juga di : Web_HRIS (development) -> hasilnya no-op
   Rollback      : drop.sql (MANUAL, jangan dijalankan otomatis)
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

PRINT '=== DDL-2026-118 dijalankan pada database: ' + DB_NAME() + ' ===';
PRINT '=== Mulai: ' + CONVERT(varchar(30), SYSDATETIME(), 121) + ' ===';
GO

/* =====================================================================
   BAGIAN 1 - KOLOM SOP
   Urutan dev: Master_Paket 22-23, Penjadwalan 15/16/18, Progress 23-24,
   Absensi 11-13. Semuanya kolom ekor, jadi urutan di bawah menghasilkan
   posisi yang identik dengan development.
   ===================================================================== */

IF OBJECT_ID('dbo.N_SOP_Master_Paket', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Master_Paket', 'Versi_Paket_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket ADD [Versi_Paket_Aktif] int NULL;
    PRINT '  + N_SOP_Master_Paket.Versi_Paket_Aktif';
END
GO
IF OBJECT_ID('dbo.N_SOP_Master_Paket', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket ADD [Id_SOP_Paket_Versi_Aktif] bigint NULL;
    PRINT '  + N_SOP_Master_Paket.Id_SOP_Paket_Versi_Aktif';
END
GO
IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Nama_Batch') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Nama_Batch] nvarchar(150) NULL;
    PRINT '  + N_SOP_Penjadwalan.Nama_Batch';
END
GO
IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Keterangan') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Keterangan] nvarchar(500) NULL;
    PRINT '  + N_SOP_Penjadwalan.Keterangan';
END
GO
IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan ADD [Id_SOP_Assignment_Rule] int NULL;
    PRINT '  + N_SOP_Penjadwalan.Id_SOP_Assignment_Rule';
END
GO
IF OBJECT_ID('dbo.N_SOP_Progress', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Progress', 'Id_Ujian_Token_Hasil') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Progress ADD [Id_Ujian_Token_Hasil] varchar(100) NULL;
    PRINT '  + N_SOP_Progress.Id_Ujian_Token_Hasil';
END
GO
IF OBJECT_ID('dbo.N_SOP_Progress', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Progress', 'Id_Versi_Asal') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Progress ADD [Id_Versi_Asal] int NULL;
    PRINT '  + N_SOP_Progress.Id_Versi_Asal';
END
GO
IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'Akurasi_Meter') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [Akurasi_Meter] decimal(9,2) NULL;
    PRINT '  + N_SOP_Absensi.Akurasi_Meter';
END
GO
IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'User_Agent') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [User_Agent] varchar(255) NULL;
    PRINT '  + N_SOP_Absensi.User_Agent';
END
GO
IF OBJECT_ID('dbo.N_SOP_Absensi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Absensi', 'IP_Address') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Absensi ADD [IP_Address] varchar(45) NULL;
    PRINT '  + N_SOP_Absensi.IP_Address';
END
GO

/* =====================================================================
   BAGIAN 2 - FOREIGN KEY SOP
   Aman divalidasi: kolomnya baru lahir di Bagian 1 sehingga seluruh baris
   lama NULL, dan NULL selalu lolos pemeriksaan foreign key.
   ===================================================================== */

IF OBJECT_ID('dbo.N_SOP_Penjadwalan', 'U') IS NOT NULL
   AND OBJECT_ID('dbo.N_SOP_Assignment_Rule', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule') IS NOT NULL
   AND OBJECT_ID('dbo.FK_N_SOP_Pjd_Rule', 'F') IS NULL
BEGIN
    ALTER TABLE dbo.N_SOP_Penjadwalan
        ADD CONSTRAINT [FK_N_SOP_Pjd_Rule]
        FOREIGN KEY ([Id_SOP_Assignment_Rule])
        REFERENCES dbo.N_SOP_Assignment_Rule ([Id_SOP_Assignment_Rule]);
    PRINT '  + FK_N_SOP_Pjd_Rule';
END
GO

/* =====================================================================
   BAGIAN 3 - dari DDL-2026-079-visitor-permit-api-b2b (kolom)
   Sebelas dari dua belas kolom ini sudah ada di production; hanya
   Kode_Karyawan_PIC yang benar-benar baru. Sisanya tetap ditulis lengkap
   agar berkas ini berdiri sendiri dan tetap benar bila dijalankan di
   lingkungan lain yang lebih tertinggal.
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Api_Aplikasi', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Api_Aplikasi ADD [Kode_Karyawan_PIC] varchar(20) NULL;
    PRINT '  + N_HRIS_Api_Aplikasi.Kode_Karyawan_PIC';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Sumber_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Sumber_Pengajuan] varchar(10) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Sumber_Pengajuan';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Id_Api_Aplikasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Id_Api_Aplikasi] int NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Id_Api_Aplikasi';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'Referensi_Mitra') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [Referensi_Mitra] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.Referensi_Mitra';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Mulai_Berlaku') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Waktu_Mulai_Berlaku] datetime2(0) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Waktu_Mulai_Berlaku';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Waktu_Berakhir') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Waktu_Berakhir] datetime2(0) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Waktu_Berakhir';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Nama_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Nama_Tamu] varchar(150) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Nama_Tamu';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Instansi_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Instansi_Tamu] varchar(150) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Instansi_Tamu';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'No_Identitas_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [No_Identitas_Tamu] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.No_Identitas_Tamu';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Identitas_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Jenis_Identitas_Tamu] varchar(20) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Jenis_Identitas_Tamu';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'Nama_Pemegang') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif ADD [Nama_Pemegang] varchar(150) NULL;
    PRINT '  + N_VISITOR_Permit_Efektif.Nama_Pemegang';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'Instansi_Pemegang') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif ADD [Instansi_Pemegang] varchar(150) NULL;
    PRINT '  + N_VISITOR_Permit_Efektif.Instansi_Pemegang';
END
GO

/* =====================================================================
   BAGIAN 4 - dari DDL-2026-100-visitor-daftar-anggota-tamu
   WAJIB mendahului Bagian 5 agar ordinal kolom sama dengan development.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail ADD [Daftar_Anggota_Json] nvarchar(max) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu_Detail.Daftar_Anggota_Json';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json')
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD CONSTRAINT [CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json]
            CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
    PRINT '  + CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif ADD [Daftar_Anggota_Json] nvarchar(max) NULL;
    PRINT '  + N_VISITOR_Tamu_Efektif.Daftar_Anggota_Json';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json')
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif
        ADD CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json]
            CHECK (Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1);
    PRINT '  + CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json';
END
GO

/* =====================================================================
   BAGIAN 5 - dari DDL-2026-101-visitor-tipe-tamu-permit-approver
   HARUS setelah Bagian 4.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD [Tipe_Tamu] varchar(20) NULL
            CONSTRAINT [DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu] DEFAULT 'REGULAR';
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu_Detail.Tipe_Tamu';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE name = 'CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu')
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu_Detail
        ADD CONSTRAINT [CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu]
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
    PRINT '  + CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif
        ADD [Tipe_Tamu] varchar(20) NULL
            CONSTRAINT [DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu] DEFAULT 'REGULAR';
    PRINT '  + N_VISITOR_Tamu_Efektif.Tipe_Tamu';
END
GO
IF COL_LENGTH('dbo.N_VISITOR_Tamu_Efektif', 'Tipe_Tamu') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE name = 'CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu')
BEGIN
    ALTER TABLE dbo.N_VISITOR_Tamu_Efektif
        ADD CONSTRAINT [CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu]
            CHECK (Tipe_Tamu IS NULL OR Tipe_Tamu IN ('REGULAR', 'B2B'));
    PRINT '  + CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu';
END
GO

/* =====================================================================
   BAGIAN 6 - dari DDL-2026-102-visitor-tamu-api-b2b (kolom)
   Urutan dev: 17, 18, 19.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Sumber_Pengajuan] varchar(10) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Sumber_Pengajuan';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Id_Api_Aplikasi] int NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Id_Api_Aplikasi';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Tamu ADD [Referensi_Mitra] varchar(100) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Tamu.Referensi_Mitra';
END
GO

/* =====================================================================
   BAGIAN 7 - dari DDL-2026-103-visitor-permit-coklat-approval (kolom)
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit ADD [No_Transaksi_Tamu] varchar(50) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit.No_Transaksi_Tamu';
END
GO
IF COL_LENGTH('dbo.N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Transaksi_Permit_Detail ADD [Jenis_Kendaraan_Tamu] varchar(50) NULL;
    PRINT '  + N_EMI_VISITOR_Transaksi_Permit_Detail.Jenis_Kendaraan_Tamu';
END
GO

/* =====================================================================
   BAGIAN 8 - dari DDL-2026-104-visitor-kategori-permit-akses
   Nullable TANPA default, persis seperti DDL asal: kodenya membaca NULL
   sebagai 'Y', sehingga empat baris kategori di production tetap muncul
   di layar pengajuan tanpa perlu di-backfill.
   ===================================================================== */

IF COL_LENGTH('dbo.N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan') IS NULL
BEGIN
    ALTER TABLE dbo.N_EMI_VISITOR_Master_Kategori_Permit ADD [Flag_Muncul_Pengajuan] char(1) NULL;
    PRINT '  + N_EMI_VISITOR_Master_Kategori_Permit.Flag_Muncul_Pengajuan';
END
GO

/* =====================================================================
   BAGIAN 9 - dari DDL-2026-110-hibah-role-per-halaman
   SATU-SATUNYA kolom NOT NULL di berkas ini. Default 'Y' WAJIB ada:
   tanpanya ALTER ditolak karena tabelnya berisi 75 baris di production.
   Seluruh baris lama otomatis 'Y' - perilaku hibah role yang berlaku
   sekarang dipertahankan apa adanya, dan pengecualian ditandai 'T'
   belakangan lewat perubahan data tersendiri.
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map
        ADD [Flag_Hibah_Role] char(1) NOT NULL
            CONSTRAINT [DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role] DEFAULT ('Y');
    PRINT '  + N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role';
END
GO
IF COL_LENGTH('dbo.N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.check_constraints
                   WHERE name = 'CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role')
BEGIN
    ALTER TABLE dbo.N_HRIS_Grup_Fitur_Map
        ADD CONSTRAINT [CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role]
            CHECK (Flag_Hibah_Role IN ('Y', 'T'));
    PRINT '  + CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role';
END
GO

/* =====================================================================
   BAGIAN 10 - dari DDL-2026-110-cakupan-lokasi-pengumpulan-data
   ===================================================================== */

IF OBJECT_ID('dbo.N_HRIS_Pengumpulan_Data_Config', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi') IS NULL
BEGIN
    ALTER TABLE dbo.N_HRIS_Pengumpulan_Data_Config ADD [Cakupan_Lokasi] varchar(2000) NULL;
    PRINT '  + N_HRIS_Pengumpulan_Data_Config.Cakupan_Lokasi';
END
GO

/* =====================================================================
   BAGIAN 11 - INDEKS
   Ditaruh paling akhir karena empat dari enam indeks menyebut kolom yang
   baru lahir di Bagian 6 dan 7.

   Tiga indeks pertama SUDAH ADA di production tetapi dengan definisi
   kedaluwarsa, jadi harus dibuang dan dibuat ulang. Penjaganya spesifik:
   DROP hanya dijalankan bila ciri versi barunya memang belum ada, sehingga
   menjalankan berkas ini berulang tidak akan membongkar indeks yang sudah
   benar.
   ===================================================================== */

/* 11a. UX_VISITOR_Permit_Requester_Idempotency - butuh Id_Api_Aplikasi di kunci. */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
   AND NOT EXISTS (
        SELECT 1
        FROM sys.indexes i
        JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
        JOIN sys.columns c ON c.object_id = i.object_id AND c.column_id = ic.column_id
        WHERE i.name = 'UX_VISITOR_Permit_Requester_Idempotency' AND c.name = 'Id_Api_Aplikasi')
BEGIN
    DROP INDEX UX_VISITOR_Permit_Requester_Idempotency ON dbo.N_EMI_VISITOR_Transaksi_Permit;
    PRINT '  - UX_VISITOR_Permit_Requester_Idempotency (versi lama dibuang)';
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Requester_Idempotency')
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)
        WHERE Idempotency_Key IS NOT NULL;
    PRINT '  + UX_VISITOR_Permit_Requester_Idempotency';
END
GO

/* 11b. IX_VISITOR_Permit_Api_Aplikasi - butuh filter Id_Api_Aplikasi IS NOT NULL. */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Api_Aplikasi' AND filter_definition IS NULL)
BEGIN
    DROP INDEX IX_VISITOR_Permit_Api_Aplikasi ON dbo.N_EMI_VISITOR_Transaksi_Permit;
    PRINT '  - IX_VISITOR_Permit_Api_Aplikasi (versi tanpa filter dibuang)';
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Api_Aplikasi')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Api_Aplikasi
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (Id_Api_Aplikasi, Flag_Approval)
        INCLUDE (No_Transaksi, Flag_Aktif)
        WHERE Id_Api_Aplikasi IS NOT NULL;
    PRINT '  + IX_VISITOR_Permit_Api_Aplikasi';
END
GO

/* 11c. UX_VISITOR_Tamu_Requester_Idempotency - butuh Id_Api_Aplikasi di kunci. */
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
   AND NOT EXISTS (
        SELECT 1
        FROM sys.indexes i
        JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
        JOIN sys.columns c ON c.object_id = i.object_id AND c.column_id = ic.column_id
        WHERE i.name = 'UX_VISITOR_Tamu_Requester_Idempotency' AND c.name = 'Id_Api_Aplikasi')
BEGIN
    DROP INDEX UX_VISITOR_Tamu_Requester_Idempotency ON dbo.N_EMI_VISITOR_Transaksi_Tamu;
    PRINT '  - UX_VISITOR_Tamu_Requester_Idempotency (versi lama dibuang)';
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Tamu_Requester_Idempotency')
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
        ON dbo.N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)
        WHERE Idempotency_Key IS NOT NULL;
    PRINT '  + UX_VISITOR_Tamu_Requester_Idempotency';
END
GO

/* 11d. IX_VISITOR_Tamu_Api_Aplikasi - belum ada. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Tamu_Api_Aplikasi')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Tamu_Api_Aplikasi
        ON dbo.N_EMI_VISITOR_Transaksi_Tamu (Id_Api_Aplikasi, Flag_Approval)
        INCLUDE (No_Transaksi, Flag_Aktif)
        WHERE Id_Api_Aplikasi IS NOT NULL;
    PRINT '  + IX_VISITOR_Tamu_Api_Aplikasi';
END
GO

/* 11e. UX_VISITOR_Permit_Transaksi_Tamu - belum ada. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Transaksi_Tamu')
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Permit_Transaksi_Tamu
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (No_Transaksi_Tamu)
        WHERE No_Transaksi_Tamu IS NOT NULL AND Flag_Aktif = 'Y';
    PRINT '  + UX_VISITOR_Permit_Transaksi_Tamu';
END
GO

/* 11f. IX_VISITOR_Permit_Pemohon_Sumber_Tamu - belum ada. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
BEGIN
    CREATE NONCLUSTERED INDEX IX_VISITOR_Permit_Pemohon_Sumber_Tamu
        ON dbo.N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Flag_Aktif)
        INCLUDE (No_Transaksi, No_Transaksi_Tamu, Flag_Approval);
    PRINT '  + IX_VISITOR_Permit_Pemohon_Sumber_Tamu';
END
GO

/* =====================================================================
   BAGIAN 12 - PENJAGA AKHIR
   Melempar galat bila ada satu pun objek yang gagal terbentuk. Paling
   penting: memastikan tidak ada indeks yang terbuang di Bagian 11 lalu
   gagal dibuat ulang.
   ===================================================================== */

DECLARE @kurang int = 0, @pesan nvarchar(2000) = N'';

;WITH wajib AS (
    SELECT * FROM (VALUES
        ('N_SOP_Master_Paket', 'Versi_Paket_Aktif'),
        ('N_SOP_Master_Paket', 'Id_SOP_Paket_Versi_Aktif'),
        ('N_SOP_Penjadwalan', 'Nama_Batch'),
        ('N_SOP_Penjadwalan', 'Keterangan'),
        ('N_SOP_Penjadwalan', 'Id_SOP_Assignment_Rule'),
        ('N_SOP_Progress', 'Id_Ujian_Token_Hasil'),
        ('N_SOP_Progress', 'Id_Versi_Asal'),
        ('N_SOP_Absensi', 'Akurasi_Meter'),
        ('N_SOP_Absensi', 'User_Agent'),
        ('N_SOP_Absensi', 'IP_Address'),
        ('N_HRIS_Api_Aplikasi', 'Kode_Karyawan_PIC'),
        ('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Daftar_Anggota_Json'),
        ('N_VISITOR_Tamu_Efektif', 'Daftar_Anggota_Json'),
        ('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Tipe_Tamu'),
        ('N_VISITOR_Tamu_Efektif', 'Tipe_Tamu'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Sumber_Pengajuan'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Id_Api_Aplikasi'),
        ('N_EMI_VISITOR_Transaksi_Tamu', 'Referensi_Mitra'),
        ('N_EMI_VISITOR_Transaksi_Permit', 'No_Transaksi_Tamu'),
        ('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu'),
        ('N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan'),
        ('N_HRIS_Grup_Fitur_Map', 'Flag_Hibah_Role'),
        ('N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi')
    ) AS v (Tabel, Kolom)
)
SELECT @kurang = COUNT(*),
       @pesan  = STRING_AGG(Tabel + '.' + Kolom, ', ')
FROM wajib
WHERE COL_LENGTH('dbo.' + Tabel, Kolom) IS NULL;

IF @kurang > 0
    RAISERROR('GAGAL: %d kolom belum terbentuk -> %s', 16, 1, @kurang, @pesan);
ELSE
    PRINT 'OK: 23 kolom lengkap.';

DECLARE @idxKurang int = 0, @idxPesan nvarchar(2000) = N'';
SELECT @idxKurang = COUNT(*), @idxPesan = STRING_AGG(Nama, ', ')
FROM (VALUES
        ('UX_VISITOR_Permit_Requester_Idempotency'),
        ('IX_VISITOR_Permit_Api_Aplikasi'),
        ('UX_VISITOR_Tamu_Requester_Idempotency'),
        ('IX_VISITOR_Tamu_Api_Aplikasi'),
        ('UX_VISITOR_Permit_Transaksi_Tamu'),
        ('IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
     ) AS v (Nama)
WHERE NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = v.Nama);

IF @idxKurang > 0
    RAISERROR('GAGAL: %d indeks belum terbentuk -> %s', 16, 1, @idxKurang, @idxPesan);
ELSE
    PRINT 'OK: 6 indeks lengkap.';

IF OBJECT_ID('dbo.FK_N_SOP_Pjd_Rule', 'F') IS NULL
    RAISERROR('GAGAL: FK_N_SOP_Pjd_Rule belum terbentuk.', 16, 1);
ELSE
    PRINT 'OK: FK_N_SOP_Pjd_Rule ada.';
GO

/* =====================================================================
   BAGIAN 13 - LAPORAN AKHIR
   ===================================================================== */

SELECT  d.Bagian, d.Tabel, d.Kolom, d.Tipe_Harapan,
        CASE WHEN COL_LENGTH('dbo.' + d.Tabel, d.Kolom) IS NULL THEN 'BELUM ADA' ELSE 'ADA' END AS Keadaan
FROM (VALUES
        (1, 'N_SOP_Master_Paket',                    'Versi_Paket_Aktif',        'int NULL'),
        (1, 'N_SOP_Master_Paket',                    'Id_SOP_Paket_Versi_Aktif', 'bigint NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Nama_Batch',               'nvarchar(150) NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Keterangan',               'nvarchar(500) NULL'),
        (1, 'N_SOP_Penjadwalan',                     'Id_SOP_Assignment_Rule',   'int NULL'),
        (1, 'N_SOP_Progress',                        'Id_Ujian_Token_Hasil',     'varchar(100) NULL'),
        (1, 'N_SOP_Progress',                        'Id_Versi_Asal',            'int NULL'),
        (1, 'N_SOP_Absensi',                         'Akurasi_Meter',            'decimal(9,2) NULL'),
        (1, 'N_SOP_Absensi',                         'User_Agent',               'varchar(255) NULL'),
        (1, 'N_SOP_Absensi',                         'IP_Address',               'varchar(45) NULL'),
        (3, 'N_HRIS_Api_Aplikasi',                   'Kode_Karyawan_PIC',        'varchar(20) NULL'),
        (4, 'N_EMI_VISITOR_Transaksi_Tamu_Detail',   'Daftar_Anggota_Json',      'nvarchar(max) NULL'),
        (4, 'N_VISITOR_Tamu_Efektif',                'Daftar_Anggota_Json',      'nvarchar(max) NULL'),
        (5, 'N_EMI_VISITOR_Transaksi_Tamu_Detail',   'Tipe_Tamu',                'varchar(20) NULL'),
        (5, 'N_VISITOR_Tamu_Efektif',                'Tipe_Tamu',                'varchar(20) NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Sumber_Pengajuan',         'varchar(10) NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Id_Api_Aplikasi',          'int NULL'),
        (6, 'N_EMI_VISITOR_Transaksi_Tamu',          'Referensi_Mitra',          'varchar(100) NULL'),
        (7, 'N_EMI_VISITOR_Transaksi_Permit',        'No_Transaksi_Tamu',        'varchar(50) NULL'),
        (7, 'N_EMI_VISITOR_Transaksi_Permit_Detail', 'Jenis_Kendaraan_Tamu',     'varchar(50) NULL'),
        (8, 'N_EMI_VISITOR_Master_Kategori_Permit',  'Flag_Muncul_Pengajuan',    'char(1) NULL'),
        (9, 'N_HRIS_Grup_Fitur_Map',                 'Flag_Hibah_Role',          'char(1) NOT NULL'),
        (10, 'N_HRIS_Pengumpulan_Data_Config',       'Cakupan_Lokasi',           'varchar(2000) NULL')
     ) AS d (Bagian, Tabel, Kolom, Tipe_Harapan)
ORDER BY d.Bagian, d.Tabel, d.Kolom;

SELECT  d.Nama, d.Jenis,
        CASE WHEN OBJECT_ID('dbo.' + d.Nama) IS NULL THEN 'BELUM ADA' ELSE 'ADA' END AS Keadaan
FROM (VALUES
        ('FK_N_SOP_Pjd_Rule',                                          'FOREIGN KEY'),
        ('CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json', 'CHECK'),
        ('CK_N_VISITOR_Tamu_Efektif_Daftar_Anggota_Json',              'CHECK'),
        ('DF_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu',           'DEFAULT'),
        ('CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Tipe_Tamu',           'CHECK'),
        ('DF_N_VISITOR_Tamu_Efektif_Tipe_Tamu',                        'DEFAULT'),
        ('CK_N_VISITOR_Tamu_Efektif_Tipe_Tamu',                        'CHECK'),
        ('DF_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role',                   'DEFAULT'),
        ('CK_N_HRIS_Grup_Fitur_Map_Flag_Hibah_Role',                   'CHECK')
     ) AS d (Nama, Jenis);

SELECT  i.name AS Indeks, OBJECT_NAME(i.object_id) AS Tabel, i.is_unique AS Unik,
        i.filter_definition AS Filter,
        STUFF((SELECT ', ' + c.name
               FROM sys.index_columns ic
               JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id AND ic.is_included_column = 0
               ORDER BY ic.key_ordinal
               FOR XML PATH('')), 1, 2, '') AS Kolom_Kunci
FROM sys.indexes i
WHERE i.name IN ('UX_VISITOR_Permit_Requester_Idempotency', 'IX_VISITOR_Permit_Api_Aplikasi',
                 'UX_VISITOR_Tamu_Requester_Idempotency', 'IX_VISITOR_Tamu_Api_Aplikasi',
                 'UX_VISITOR_Permit_Transaksi_Tamu', 'IX_VISITOR_Permit_Pemohon_Sumber_Tamu')
ORDER BY OBJECT_NAME(i.object_id), i.name;
GO

PRINT '=== Selesai: ' + CONVERT(varchar(30), SYSDATETIME(), 121) + ' ===';
GO
