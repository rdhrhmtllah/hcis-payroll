/* Change ID: DDL-2026-094-pa-callback-hclearn-lengkap
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-076 (payload & score), DDL-2026-093 (Api_Ref pada integrasi).
   Scope: menyimpan APA ADANYA seluruh kiriman callback HC Learn — header penilaian dan
          rincian per butir — supaya perhitungan dan tampilan per kompetensi tidak perlu
          mengurai ulang JSON mentahnya.

   ── KENAPA KOLOM, PADAHAL JSON-NYA SUDAH DISIMPAN ─────────────────────────────
   `N_PA_Assignment_Payload.Payload_Json` memang menyimpan kiriman utuh, dan itu tetap
   bukti mentahnya. Tetapi JSON tidak bisa disaring, diurutkan, atau dijumlahkan tanpa
   diurai baris per baris di PHP — dan layar hasil membaca per kompetensi untuk ratusan
   peserta sekaligus. Yang dipromosikan jadi kolom hanyalah yang DIBACA mesin atau layar;
   sisanya tetap tinggal di JSON.

   ── YANG DIPROMOSIKAN, DAN ALASANNYA SATU PER SATU ────────────────────────────
   Header (N_PA_Assignment_Payload):
     Api_Reference       identitas penilaian versi HC Learn. Sejak kontrak final merekalah
                         yang dikirim di callback — `Id_Ujian_Token` numerik SENGAJA tidak
                         pernah dikirim karena bilangan berurutan bisa ditebak.
     Status_Skor         FINAL | MENUNGGU_VERIFIKASI | DITOLAK. Tanpa ini, payload yang
                         tersimpan tidak bisa dibedakan antara "nilai sah" dan "angka yang
                         belum boleh dipakai".
     Kode_Akses          alat tes utama; pemisah paket dua alat tes.
     Flag_Multi          token memuat lebih dari satu alat tes (mayoritas token mereka).
     Total_Nilai         ringkasan MEREKA. Disimpan sebagai PEMBANDING, bukan sumber —
                         HCIS tetap menghitung dari butirnya. Nullable karena sengaja tidak
                         dikirim saat MENUNGGU_VERIFIKASI.
     Nama_Tes, Waktu_Mulai_Akses, Waktu_Selesai_Akses, Ambang_Batas_Nilai,
     Status_Kelulusan, Warna
                         blok `penilaian{}` mereka. Inilah yang menjawab "kapan orang ini
                         benar-benar mengerjakan" dan "apa yang dilihatnya di sana" —
                         pertanyaan yang selalu muncul saat seseorang menyanggah nilainya.

   Rincian (N_PA_Assignment_Score):
     Kode_Alat_Tes, Nama_Alat_Tes
                         satu token bisa memuat dua alat tes, dan butir dari keduanya datar
                         di satu larik. Tanpa kolom ini keduanya tidak bisa dipisahkan lagi.
     Status_Interpretation, Warna, Keterangan
                         interpretasi HC Learn per butir. Inilah yang ditampilkan per
                         kompetensi; tanpa disimpan, layar hanya punya angka mentah dan
                         setiap tampilan harus mengarang ambangnya sendiri — dua sistem
                         yang menilai hal yang sama dengan ambang berbeda.
     Jumlah_Soal, Jumlah_Terjawab
                         pembeda "nilai rendah" dari "tidak selesai dikerjakan". Keduanya
                         menghasilkan skor kecil dan menuntut tindakan yang sama sekali
                         berbeda.

   TIDAK ADA KOLOM YANG DIHAPUS ATAU DISEMPITKAN. Seluruhnya NULL-able: payload lama yang
   sudah tersimpan tetap sah dan tidak perlu diisi ulang. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50194, 'DDL-2026-094 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Assignment_Payload', 'U') IS NULL
   OR OBJECT_ID('N_PA_Assignment_Score', 'U') IS NULL
    THROW 50195, 'DDL-2026-094 membutuhkan DDL-2026-076 (payload dan score).', 1;
GO

/* ---------------------------------------------------------------
 | 1. Header kiriman.
 --------------------------------------------------------------- */

IF COL_LENGTH('N_PA_Assignment_Payload', 'Api_Reference') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Api_Reference varchar(64) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Status_Skor') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Status_Skor varchar(30) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Kode_Akses') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Kode_Akses varchar(50) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Flag_Multi') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Flag_Multi char(1) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Total_Nilai') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Total_Nilai decimal(9,2) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Nama_Tes') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Nama_Tes nvarchar(255) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Waktu_Mulai_Akses') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Waktu_Mulai_Akses datetime2(0) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Waktu_Selesai_Akses') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Waktu_Selesai_Akses datetime2(0) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Ambang_Batas_Nilai') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Ambang_Batas_Nilai decimal(9,2) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Status_Kelulusan') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Status_Kelulusan nvarchar(50) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Payload', 'Warna') IS NULL
    ALTER TABLE N_PA_Assignment_Payload ADD Warna varchar(20) NULL;
GO

/* Kosakata mereka apa adanya. CHECK, bukan gerbang aplikasi: nilai di luar tiga ini
   membuat "boleh dihitung atau belum" jadi pertanyaan yang tak terjawab, dan baris yang
   sudah telanjur masuk tidak bisa lagi ditafsirkan siapa pun. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgPayload_Status_Skor')
    ALTER TABLE N_PA_Assignment_Payload WITH NOCHECK
        ADD CONSTRAINT CK_PA_AsgPayload_Status_Skor
        CHECK (Status_Skor IS NULL OR Status_Skor IN ('FINAL', 'MENUNGGU_VERIFIKASI', 'DITOLAK'));
GO

IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgPayload_Flag_Multi')
    ALTER TABLE N_PA_Assignment_Payload WITH NOCHECK
        ADD CONSTRAINT CK_PA_AsgPayload_Flag_Multi
        CHECK (Flag_Multi IS NULL OR Flag_Multi IN ('Y', 'T'));
GO

/* Jalur baca "kiriman mana milik penilaian ini". Tersaring pada yang terisi: payload lama
   ber-Api_Reference NULL tidak perlu ikut memberati indeksnya. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Payload') AND name = 'IX_PA_AsgPayload_ApiRef'
)
    CREATE INDEX IX_PA_AsgPayload_ApiRef
        ON N_PA_Assignment_Payload (Api_Reference)
        INCLUDE (PA_Assignment_Integration_Id, Status_Skor, Diterima_At)
        WHERE Api_Reference IS NOT NULL;
GO

/* ---------------------------------------------------------------
 | 2. Rincian per butir.
 --------------------------------------------------------------- */

IF COL_LENGTH('N_PA_Assignment_Score', 'Kode_Alat_Tes') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Kode_Alat_Tes varchar(50) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Nama_Alat_Tes') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Nama_Alat_Tes nvarchar(255) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Status_Interpretation') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Status_Interpretation nvarchar(50) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Warna') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Warna varchar(20) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Keterangan') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Keterangan nvarchar(500) NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Jumlah_Soal') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Jumlah_Soal smallint NULL;
GO

IF COL_LENGTH('N_PA_Assignment_Score', 'Jumlah_Terjawab') IS NULL
    ALTER TABLE N_PA_Assignment_Score ADD Jumlah_Terjawab smallint NULL;
GO

/* Cacah terjawab tidak boleh melebihi cacah soalnya. Nilai yang melanggar bukan sekadar
   janggal — ia membuat "belum selesai dikerjakan" mustahil dihitung dengan benar. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_AsgScore_Terjawab')
    ALTER TABLE N_PA_Assignment_Score WITH NOCHECK
        ADD CONSTRAINT CK_PA_AsgScore_Terjawab
        CHECK (
            Jumlah_Soal IS NULL
            OR Jumlah_Terjawab IS NULL
            OR (Jumlah_Soal >= 0 AND Jumlah_Terjawab >= 0 AND Jumlah_Terjawab <= Jumlah_Soal)
        );
GO

/* ---------------------------------------------------------------
 | 3. Pendaftaran perubahan.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-094'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-094',
        'DDL',
        'Kiriman callback HC Learn disimpan lengkap, bukan hanya sebagai JSON',
        'Kontrak callback final HC Learn (10 September 2026) mengirim Api_Reference sebagai identitas penilaian - Id_Ujian_Token numerik SENGAJA tidak pernah dikirim. Kirimannya juga membawa blok penilaian (nama tes, waktu akses sebenarnya, ambang batas, status kelulusan) dan rincian per butir yang jauh lebih kaya daripada yang kolomnya tersedia: kode dan nama alat tes, interpretasi, warna, keterangan, serta cacah soal dan terjawab.',
        'database/ddl/DDL-2026-094-pa-callback-hclearn-lengkap/forward.sql',
        'database/ddl/DDL-2026-094-pa-callback-hclearn-lengkap/forward.sql',
        'Web_HRIS',
        'DONE',
        'PENDING',
        'PENDING',
        'DEV_DONE',
        'Payload_Json tetap menyimpan kiriman utuh sebagai bukti mentah; yang dipromosikan jadi kolom hanya yang dibaca mesin atau layar. Interpretasi dan warna per butir dipromosikan karena layar hasil menampilkannya PER KOMPETENSI - tanpa disimpan, setiap tampilan harus mengarang ambangnya sendiri dan dua sistem menilai hal yang sama dengan ambang berbeda. Jumlah_Soal/Jumlah_Terjawab membedakan nilai rendah dari tidak selesai dikerjakan, dua keadaan yang menuntut tindakan berbeda. Seluruh kolom NULL-able supaya payload lama tetap sah tanpa diisi ulang.',
        NULL
    );
END;
GO
