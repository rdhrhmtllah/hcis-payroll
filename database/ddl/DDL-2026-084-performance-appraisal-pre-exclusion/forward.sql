/* Change ID: DDL-2026-084-performance-appraisal-pre-exclusion
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-066 (N_PA_Cycle). Jalankan sesudahnya.
   Scope: satu tabel — daftar karyawan yang dikecualikan SEBELUM peserta dibentuk.

   MASALAH YANG DISELESAIKAN

   `N_PA_Participant.Alasan_Dikecualikan` sudah bisa menampung pengecualian, tetapi baris
   peserta baru ada SESUDAH penyiapan berjalan. Admin yang sudah tahu sejak awal bahwa
   sekelompok orang tidak ikut dinilai — pegawai magang, orang yang sedang cuti panjang,
   direksi — tidak punya tempat menuliskannya sampai 958 baris terlanjur terbentuk.

   Akibatnya ia harus menyiapkan dulu, lalu mengecualikan satu per satu dari daftar
   panjang. Tabel ini membalik urutannya: daftar dibuat lebih dulu, penyiapan
   menghormatinya.

   KENAPA TABEL SENDIRI, BUKAN KOLOM DI KARYAWAN

   Pengecualian ini milik SATU SIKLUS, bukan sifat orangnya. Orang yang tidak dinilai
   tahun ini karena sedang tugas belajar tetap dinilai tahun depan. Menaruhnya sebagai
   kolom di `Karyawan` akan membuatnya terbawa selamanya dan tak seorang pun ingat
   mencabutnya.

   KENAPA TIDAK MENUNJUK N_PA_Participant

   Justru karena barisnya belum ada — itu seluruh maksudnya. Yang ditunjuk adalah siklus
   dan pasangan (perusahaan, karyawan), sama seperti yang dipakai `N_PA_Participant`
   sendiri untuk menyebut orang.

   ALASAN WAJIB, DAN ITU BUKAN FORMALITAS. Pengecualian tanpa alasan menghasilkan orang
   yang hilang dari penilaian tanpa siapa pun bisa menjawab kenapa — dan pertanyaan itu
   selalu datang setahun kemudian, ketika yang memutuskannya sudah lupa.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50420, 'DDL-2026-084 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
    THROW 50421, 'DDL-2026-084 membutuhkan tabel N_PA_Cycle.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Pre_Exclusion', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Pre_Exclusion (
        Id_PA_Cycle_Pre_Exclusion int IDENTITY(1,1) NOT NULL,
        PA_Cycle_Id int NOT NULL,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,

        /* Wajib. Lihat catatan di kepala berkas: pengecualian tanpa alasan menghasilkan
           orang yang hilang tanpa siapa pun bisa menjawab kenapa. */
        Alasan nvarchar(500) NOT NULL,

        /* Dicabut dengan menonaktifkan, bukan menghapus — sama seperti seluruh modul ini.
           Baris yang dihapus membuat "kenapa dia dinilai padahal dulu dikecualikan"
           mustahil dijawab. */
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_PA_PreExcl_Flag DEFAULT ('Y'),

        Created_At datetime2(0) NOT NULL CONSTRAINT DF_PA_PreExcl_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,

        CONSTRAINT PK_PA_Cycle_Pre_Exclusion PRIMARY KEY CLUSTERED (Id_PA_Cycle_Pre_Exclusion),
        CONSTRAINT FK_PA_PreExcl_Cycle FOREIGN KEY (PA_Cycle_Id)
            REFERENCES N_PA_Cycle (Id_PA_Cycle),
        CONSTRAINT CK_PA_PreExcl_Flag CHECK (Flag_Aktif IN ('Y', 'T'))
    );
END;
GO

/* Satu orang tidak boleh punya dua baris AKTIF pada satu siklus. Indeks tersaring, bukan
   unique constraint biasa: baris yang sudah dicabut (`Flag_Aktif = 'T'`) memang boleh
   berulang — orang bisa dikecualikan, dicabut, lalu dikecualikan lagi dengan alasan lain,
   dan riwayat keduanya harus tetap ada. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Pre_Exclusion')
      AND name = 'UX_PA_PreExcl_Cycle_Karyawan'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_PreExcl_Cycle_Karyawan
        ON N_PA_Cycle_Pre_Exclusion (PA_Cycle_Id, Kode_Perusahaan, Kode_Karyawan)
        WHERE Flag_Aktif = 'Y';
END;
GO

/* Jalur baca utamanya: "siapa saja yang dikecualikan pada siklus ini". Dipanggil sekali
   per penyiapan dan sekali per pratinjau, atas siklus yang sama. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Pre_Exclusion')
      AND name = 'IX_PA_PreExcl_Cycle_Aktif'
)
BEGIN
    CREATE INDEX IX_PA_PreExcl_Cycle_Aktif
        ON N_PA_Cycle_Pre_Exclusion (PA_Cycle_Id, Flag_Aktif)
        INCLUDE (Kode_Perusahaan, Kode_Karyawan, Alasan);
END;
GO
