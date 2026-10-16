/* Change ID: DDL-2026-095-pa-kode-soal-butir-siklus
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-083 (butir paket siklus), DDL-2026-094 (kolom callback).
   Scope: menyimpan `Kode_Soal_Sumber` pada butir paket soal siklus, yaitu kunci yang
          DIPAKAI HC LEARN saat mengembalikan nilai — supaya kiriman mereka bisa
          dipetakan ke kompetensi.

   ── KENAPA PERUBAHAN INI ADA ──────────────────────────────────────────────────
   Pada 10 September 2026 penilaian pertama yang benar-benar diisi orang masuk lewat
   callback: 13 butir, tanda tangan sah, tersimpan utuh, dijawab 200. NOL di antaranya
   terpetakan, `kontrak_lengkap` false, dan penugasannya tidak pernah ditandai selesai —
   penilai sudah mengerjakan, layar tetap berkata "belum mulai", dan tidak ada satu pun
   pesan galat di mana pun.

   Sebabnya satu kolom yang tidak ada. HC Learn mengembalikan `Detail[].Kode_Soal` berisi
   `K01`..`K13`, persis kode yang MEREKA berikan saat paketnya diikat — dan kode itu
   memang kita simpan, tetapi hanya di master (`N_PA_Question_Binding_Master_Detail.
   Kode_Soal_Sumber`). Salinan master -> siklus membuangnya, sehingga pemeta terpaksa
   mencocokkan `Kode_Soal` dengan `Id_Jenis_Soal` (100158..). "K01" tidak akan pernah
   sama dengan "100158".

   ── KENAPA KOLOM DI SIKLUS, BUKAN MEMBACA MASTER SAAT MEMETAKAN ───────────────
   Butir paket siklus adalah SALINAN, dan itu memang tujuannya: siklus yang sudah dikunci
   dinilai dengan susunan soal yang berlaku SAAT ITU. Membaca kodenya dari master ketika
   nilai datang berarti kiriman bulan depan dipetakan memakai master yang mungkin sudah
   diubah — pemetaan yang berubah sendiri sesudah orangnya mengisi. Kunci yang dipakai
   kontrak harus ikut dibekukan bersama paketnya.

   ── NULL-ABLE, DAN ITU DISENGAJA ──────────────────────────────────────────────
   Siklus yang sudah ada tidak punya kolom ini; DML-2026-016 mengisinya dari master
   berdasarkan `Id_Jenis_Soal` + ujiannya. Baris yang tetap kosong sesudah pengisian itu
   berarti masternya sendiri tidak punya kodenya — keadaan yang harus terlihat apa adanya
   di layar peninjauan kiriman, bukan ditutup dengan tebakan.
*/

/* ---------------------------------------------------------------
 | 1. Kolom kode soal pada butir paket siklus.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND name = 'Kode_Soal_Sumber'
)
    ALTER TABLE N_PA_Cycle_Question_Package_Item
        ADD Kode_Soal_Sumber varchar(64) NULL;
GO

/* Pencarian pemeta selalu berbentuk "butir paket ini, kode ini". Tanpa indeks, tiap
   kiriman memindai seluruh butir paketnya — kecil per kiriman, tetapi kirimannya
   sebanyak penilai. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Question_Package_Item')
      AND name = 'IX_PA_CycleQPkgItem_KodeSoal'
)
    CREATE INDEX IX_PA_CycleQPkgItem_KodeSoal
        ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Kode_Soal_Sumber)
        WHERE Kode_Soal_Sumber IS NOT NULL;
GO

/* ---------------------------------------------------------------
 | 2. Pendaftaran perubahan.
 --------------------------------------------------------------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-095'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-095',
        'DDL',
        'Kode soal HC Learn ikut dibekukan pada butir paket siklus',
        'HC Learn mengembalikan Detail[].Kode_Soal berisi kode yang mereka berikan saat paket diikat (K01..K13). Kode itu hanya tersimpan di master binding; salinan master ke siklus membuangnya, sehingga pemeta mencocokkan Kode_Soal dengan Id_Jenis_Soal dan tidak satu butir pun terpetakan. Akibatnya penilaian yang sudah diisi orang tidak pernah ditandai selesai, tanpa satu pun pesan galat.',
        'database/ddl/DDL-2026-095-pa-kode-soal-butir-siklus/forward.sql',
        'database/ddl/DDL-2026-095-pa-kode-soal-butir-siklus/forward.sql',
        'Web_HRIS',
        'DONE',
        'PENDING',
        'PENDING',
        'DEV_DONE',
        'Kolomnya NULL-able supaya siklus lama tetap sah; DML-2026-016 mengisinya dari master berdasarkan Id_Jenis_Soal. Kode dibekukan bersama paketnya, bukan dibaca dari master saat nilai datang - siklus yang sudah dikunci harus dipetakan memakai susunan soal yang berlaku saat itu, bukan master yang mungkin sudah berubah.',
        NULL
    );
END;
GO
