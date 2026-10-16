/* Change ID: DDL-2026-093-pa-pintu-hclearn-nyata
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: 9 kolom ditambahkan, 2 indeks dibuat, register the change <= 1.
   Dependencies: DDL-2026-075 (tabel integrasi penugasan sudah ada).

   PINTU PENILAI DATANG DARI HC LEARN SUNGGUHAN, BUKAN LAGI DIKARANG SENDIRI.

   HC Learn menyerahkan kontrak penjadwalan PA pada 9 September 2026 — lima endpoint di grup
   Penjadwalan: store (massal, menjawab 202), status (dibaca berkala sampai SELESAI), update,
   delete, dan extend-time yang sekaligus menerbitkan ulang kodenya.

   Yang dipulangkan HC Learn per penilai BUKAN satu nilai, melainkan lima, dan sampai hari
   ini tabel kita cuma punya tempat untuk satu (`Id_Ujian_Token`, yang selama ini diisi token
   simulasi berawalan SIM-PA-):

     api_ref      identitas yang diterima SELURUH endpoint token milik mereka. Inilah yang
                  wajib disimpan. `Id_Ujian_Token` numerik SENGAJA tidak pernah dikirim HC
                  Learn - bilangan berurutan bisa ditebak, dan memegang satu berarti bisa
                  mencoba tetangganya.
     short_token  kode pendek yang diketik peserta.
     akses_otp    OTP pendamping short_token. Bersama link_ujian, KETIGANYA yang dibagikan
                  ke penilai; `akses_token` terenkripsi milik mereka tidak pernah dikirim
                  karena pemanggil tidak pernah memakainya.
     link_ujian   tautan siap bagi.
     waktu_mulai / waktu_akhir  jendela yang BENAR-BENAR berlaku menurut mereka - bukan yang
                  kita minta. Keduanya bisa berbeda, dan yang mengikat peserta adalah milik
                  mereka.

   KENAPA PROCESS_ID DAN STATUS IKUT DISIMPAN

   `store` menjawab 202 dan menitipkan pekerjaannya ke antrean `hcl-penjadwalanpa`. Tokennya
   belum ada saat jawaban itu diterima. Tanpa menyimpan `process_id` beserta status per
   baris, satu-satunya cara mengetahui nasib kiriman yang terputus di tengah adalah
   mengirimnya ulang - dan mengirim ulang penjadwalan berarti menerbitkan token kedua untuk
   orang yang sudah punya.

   Statusnya memakai kosakata MEREKA apa adanya (ANTRE, BERHASIL, SUDAH_ADA, GAGAL).
   Menerjemahkannya ke kosakata kita akan memaksa dua pihak menebak arti yang sama.

   `Id_Ujian_Token` DIPERTAHANKAN, tidak dibuang: ia dipakai `PaPesertaService`,
   `PaSimulasiPengisianService`, dan penjaga di `pa:verify`. Pengalihan pembacaannya ke
   `Api_Ref` dikerjakan di kode, dan barisnya yang lama dibersihkan DML-2026-120.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50423, 'DDL-2026-093 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* ---------- 1. Kolom baru ---------- */

IF COL_LENGTH('N_PA_Assignment_Integration', 'Api_Ref') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Api_Ref varchar(64) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Short_Token') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Short_Token varchar(32) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Akses_Otp') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Akses_Otp varchar(32) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Link_Ujian') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Link_Ujian nvarchar(500) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Process_Id') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Process_Id varchar(64) NULL;
GO

/* Kosakata MEREKA, apa adanya. Bawaannya ANTRE karena baris integrasi memang lahir pada saat
   kiriman diterima 202, sebelum tokennya terbit. */
IF COL_LENGTH('N_PA_Assignment_Integration', 'Status_Integrasi') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Status_Integrasi varchar(20) NOT NULL
        CONSTRAINT DF_PA_AsgIntg_Status DEFAULT ('ANTRE');
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Pesan_Gagal') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Pesan_Gagal nvarchar(500) NULL;
GO

/* Jendela yang berlaku menurut HC Learn. Started_At/Finished_At yang sudah ada berarti KAPAN
   PESERTA MENGERJAKAN - dua hal yang berbeda, dan menumpangkannya pada kolom yang sama akan
   membuat "belum mulai" tak bisa dibedakan dari "jendelanya belum dibuka". */
IF COL_LENGTH('N_PA_Assignment_Integration', 'Waktu_Mulai') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Waktu_Mulai datetime2(0) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Integration', 'Waktu_Akhir') IS NULL
    ALTER TABLE N_PA_Assignment_Integration ADD Waktu_Akhir datetime2(0) NULL;
GO

/* ---------- 2. Penjaga ---------- */

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgIntg_Status')
    ALTER TABLE N_PA_Assignment_Integration ADD CONSTRAINT CK_PA_AsgIntg_Status
        CHECK (Status_Integrasi IN ('ANTRE', 'BERHASIL', 'SUDAH_ADA', 'GAGAL'));
GO

/* Satu api_ref milik satu penugasan, dan tidak boleh dipakai dua baris aktif. Tersaring pada
   yang bukan NULL: baris yang masih ANTRE memang belum punya api_ref, dan itu sah. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'UX_PA_AsgIntg_ApiRef'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_AsgIntg_ApiRef
        ON N_PA_Assignment_Integration (Api_Ref)
        WHERE Api_Ref IS NOT NULL AND Flag_Aktif = 'Y';
END;
GO

/* Jalur baca "kiriman mana yang masih ANTRE" — dipanggil tiap kali status dibaca berkala,
   dan tanpa indeks ini ia memindai seluruh tabel integrasi satu siklus. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'IX_PA_AsgIntg_Proses'
)
BEGIN
    CREATE INDEX IX_PA_AsgIntg_Proses
        ON N_PA_Assignment_Integration (Process_Id, Status_Integrasi)
        INCLUDE (PA_Assignment_Id, Api_Ref, Flag_Aktif);
END;
GO

/* ---------- 3. Pendaftaran perubahan ---------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-093-pa-pintu-hclearn-nyata'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DDL-2026-093-pa-pintu-hclearn-nyata',
         'DDL',
         'Tempat untuk pintu penilai yang sebenarnya dari HC Learn',
         'HC Learn menyerahkan kontrak penjadwalan PA (5 endpoint). Yang dipulangkan per penilai ada lima nilai - api_ref, short_token, akses_otp, link_ujian, dan jendela yang benar-benar berlaku - sementara tabel integrasi cuma punya Id_Ujian_Token. Ditambah process_id dan status per baris karena store menjawab 202 dan tokennya baru ada sesudah antreannya digarap.',
         'database/ddl/DDL-2026-093-pa-pintu-hclearn-nyata/forward.sql',
         'database/ddl/DDL-2026-093-pa-pintu-hclearn-nyata/forward.sql',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'Status memakai kosakata HC Learn apa adanya (ANTRE/BERHASIL/SUDAH_ADA/GAGAL) supaya kedua pihak tidak menebak arti yang sama. Waktu_Mulai/Waktu_Akhir DIPISAH dari Started_At/Finished_At: yang pertama jendela yang berlaku, yang kedua kapan peserta mengerjakan. Id_Ujian_Token dipertahankan karena masih dibaca PaPesertaService dan pa:verify; pembersihan token simulasi lama lewat DML-2026-120.',
         'performance-appraisal');
END;
GO
