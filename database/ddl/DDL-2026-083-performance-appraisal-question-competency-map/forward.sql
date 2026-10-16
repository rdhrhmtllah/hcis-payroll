/* Change ID: DDL-2026-083-performance-appraisal-question-competency-map
   Artifact revision: r2
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-073 (paket soal siklus dan butirnya), DDL-2026-080 (jenis catatan).
   Scope: DUA KOLOM pada N_PA_Cycle_Question_Package_Item.

   ── KENAPA REVISI ─────────────────────────────────────────────────────────────
   r1 membuat tabel BARU bernama N_PA_Question_Competency_Map untuk memetakan
   Kode_Soal HC Learn ke kompetensi. Itu keliru: N_PA_Cycle_Question_Package_Item
   dari DDL-2026-073 SUDAH melakukan hal itu, per paket siklus, dengan unique index
   tersaring yang sama persis. r1 menduplikasinya tanpa memeriksa.

   Kembarnya bukan sekadar mubazir - ia memutus rantai. Pemeta payload membaca peta
   dari tabel baru sedangkan pemeriksa kelengkapan butir membaca tabel lama yang
   kosong, sehingga penugasan tidak pernah ditandai SELESAI, seluruh penilai
   dilewati saat perhitungan, dan pilar kompetensi menjadi kosong tanpa satu pun
   pesan galat.

   r2 karena itu tidak membuat tabel apa pun. Ia menambahkan dua kolom yang memang
   belum ada di tabel lama, yaitu satu-satunya hal yang benar-benar baru di r1.

   ── KENAPA PA_Result_Note_Type_Id ────────────────────────────────────────────
   Butir uraian tidak menyumbang nilai kompetensi. Memaksanya menunjuk kompetensi
   membuat jawaban teks ikut dihitung sebagai angka nol. Yang kosong kompetensinya
   boleh menunjuk jenis catatan sebagai gantinya - itulah jalan jawaban kualitatif
   menjadi catatan hasil. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50340, 'DDL-2026-083 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle_Question_Package_Item', 'U') IS NULL
   OR OBJECT_ID('N_PA_Result_Note_Type', 'U') IS NULL
    THROW 50341, 'DDL-2026-083 membutuhkan butir paket soal siklus dan master jenis catatan.', 1;

/* Penjaga terhadap sisa r1. Kalau tabel kembarnya masih ada, realignment
   development belum dijalankan dan menjalankan r2 hanya akan menyembunyikan
   keadaan setengah jalan. */
IF OBJECT_ID('N_PA_Question_Competency_Map', 'U') IS NOT NULL
    THROW 50342, 'DDL-2026-083 r2: N_PA_Question_Competency_Map peninggalan r1 masih ada; jalankan drop.sql r1 lebih dulu.', 1;
GO

/* Jenis soal dari HC Learn: "skala", "teks", dan seterusnya. Disimpan apa adanya,
   TANPA CHECK: daftarnya milik pihak lain dan bisa tumbuh tanpa memberi tahu kita. */
IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'Jenis_Soal') IS NULL
    ALTER TABLE N_PA_Cycle_Question_Package_Item ADD Jenis_Soal varchar(30) NULL;
GO

IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'PA_Result_Note_Type_Id') IS NULL
    ALTER TABLE N_PA_Cycle_Question_Package_Item ADD PA_Result_Note_Type_Id int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_PA_Cycle_Question_Item_Note_Type')
    ALTER TABLE N_PA_Cycle_Question_Package_Item WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Question_Item_Note_Type
        FOREIGN KEY (PA_Result_Note_Type_Id)
        REFERENCES N_PA_Result_Note_Type (Id_PA_Result_Note_Type);
GO

/* Satu butir tidak boleh sekaligus menjadi nilai kompetensi dan catatan kualitatif:
   yang pertama dirata-ratakan, yang kedua dibaca apa adanya, dan butir yang menjadi
   keduanya akan dihitung dua kali dengan arti yang berbeda. */
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_PA_Cycle_Question_Item_Peran')
    ALTER TABLE N_PA_Cycle_Question_Package_Item WITH CHECK
        ADD CONSTRAINT CK_PA_Cycle_Question_Item_Peran
        CHECK (PA_Competency_Id IS NULL OR PA_Result_Note_Type_Id IS NULL);
GO

/* -------- verifikasi -------- */
IF COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'Jenis_Soal') IS NULL
   OR COL_LENGTH('N_PA_Cycle_Question_Package_Item', 'PA_Result_Note_Type_Id') IS NULL
    THROW 50343, 'DDL-2026-083 r2: kolom baru tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')) <> 15
    THROW 50344, 'DDL-2026-083 r2: jumlah kolom N_PA_Cycle_Question_Package_Item bukan 15.', 1;

IF (SELECT COUNT(*) FROM sys.foreign_keys WHERE parent_object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')) <> 3
    THROW 50345, 'DDL-2026-083 r2: jumlah foreign key bukan 3.', 1;

IF EXISTS (SELECT 1 FROM sys.foreign_keys
           WHERE parent_object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
             AND (is_disabled = 1 OR is_not_trusted = 1))
    THROW 50346, 'DDL-2026-083 r2: terdapat FK disabled atau tidak trusted.', 1;

IF EXISTS (SELECT 1 FROM sys.check_constraints
           WHERE parent_object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
             AND (is_disabled = 1 OR is_not_trusted = 1))
    THROW 50347, 'DDL-2026-083 r2: terdapat CHECK disabled atau tidak trusted.', 1;

/* Unique index tersaring milik DDL-2026-073 harus tetap utuh - itulah yang membuat
   satu kode soal hanya punya satu terjemahan aktif per paket. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
                 AND name = 'UX_PA_Cycle_Question_Item_Aktif' AND is_unique = 1 AND has_filter = 1)
    THROW 50348, 'DDL-2026-083 r2: unique index tersaring butir hilang.', 1;

IF OBJECT_ID('N_PA_Question_Competency_Map', 'U') IS NOT NULL
    THROW 50349, 'DDL-2026-083 r2: tabel kembar peninggalan r1 masih ada.', 1;
GO
