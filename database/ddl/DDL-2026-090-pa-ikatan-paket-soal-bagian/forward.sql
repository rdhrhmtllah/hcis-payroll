/*
| DDL-2026-090 — Ikatan paket soal berbasis bagian
|
| HC Learn mengubah kontraknya: yang dilempar kini ujian beserta paket, alat tes, dan
| bagian-bagiannya, dan bagian-bagian itu TIDAK berlabel kompetensi. Kompetensi karena
| itu berpindah menjadi milik kita, dipetakan admin satu bagian ke tepat satu kompetensi.
|
| Yang diikat adalah UJIAN, bukan paket: dokumentasi HC Learn menyebut id_master_ujian
| sebagai "satuan yang dijadwalkan kepada peserta", dan /api/v1/pa/hasil/{id_ujian_token}
| juga berpangkal di sana. Mengikat paket berarti mengikat sesuatu yang tidak bisa
| dijadwalkan — dan itu baru ketahuan saat penjadwalan, bukan saat pengikatan.
|
| Identitas satu bagian adalah PASANGAN (Id_Jenis_Indikator, Id_Jenis_Soal). HC Learn
| memastikan pada 9 September 2026 bahwa tidak ada id per-instance untuk bagian; yang ada
| hanya id_jenis_soal, yang dokumentasi mereka sendiri sebut pengenal BANK SOAL: "Bank yang
| sama bisa dipakai beberapa alat tes."
|
| Rujukan: docs/superpowers/specs/2026-09-09-pa-ikatan-paket-soal-section-kompetensi-design.md
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ---------------------------------------------------------------
 | 1. PRASYARAT: DML-2026-117 HARUS SUDAH DIJALANKAN.
 |
 | Berkas ini TIDAK menghapus data. Pengosongan siklus uji dipisahkan ke
 | `database/dml/DML-2026-117-bersihkan-siklus-uji-pa/data-change.sql` karena ia perubahan
 | DATA, bukan skema — dan jangkauannya jauh melampaui apa yang pantas disembunyikan di
 | dalam sebuah DDL: 18 tabel, sekitar 32.500 baris.
 |
 | Kenapa pengosongan itu diperlukan: bagian 5 di bawah membuat UX_PA_CycleQItem_Kompetensi,
 | dan data siklus pada kontrak lama TIDAK BISA memenuhinya — satu kompetensi memang diukur
 | beberapa soal di sana. Terukur 9 September 2026: 160 grup (360 baris) rangkap.
 |
 | Penjaga di bawah menghentikan seluruh transaksi bila prasyaratnya belum dijalankan,
 | daripada gagal belakangan di tengah pembuatan indeks dengan pesan yang tidak menyebut
 | sebab sesungguhnya.
 --------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM N_PA_Cycle_Question_Package_Item)
   OR EXISTS (SELECT 1 FROM N_PA_Question_Binding_Master)
BEGIN
    THROW 51090, 'DDL-2026-090 dihentikan: DML-2026-117 belum dijalankan. Kosongkan siklus uji PA lebih dulu — indeks unik di bagian 5 akan ditolak data kontrak lama.', 1;
END;

/* ---------------------------------------------------------------
 | 2. Ganti nama kolom yang artinya berubah.
 |
 | sp_rename dipakai supaya indeks dan constraint yang menempel ikut terbawa; membuat
 | kolom baru lalu menyalin akan menjatuhkan indeksnya diam-diam.
 |
 | 'Kode_Paket_Sumber' kini berisi ID UJIAN. Kode HC Learn terbukti tidak unik — 23 alat
 | di katalog semuanya kode = 'RDT', dan /detail/nama-tes/RDT menjawab HTTP 200 dengan
 | padanan pertama — sehingga memakai kode gagal tanpa terlihat gagal.
 --------------------------------------------------------------- */
EXEC sp_rename 'N_PA_Question_Binding_Master.Kode_Paket_Sumber', 'Id_Master_Ujian',    'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Nama_Paket_Sumber', 'Nama_Ujian_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Jumlah_Soal',       'Jumlah_Bagian',      'COLUMN';
EXEC sp_rename 'N_PA_Question_Binding_Master.Sidik_Susunan',     'Sidik_Bagian',       'COLUMN';

EXEC sp_rename 'N_PA_Cycle_Question_Package.Kode_Paket_Sumber',     'Id_Master_Ujian',    'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package.Nama_Paket_Sumber',     'Nama_Ujian_Sumber',  'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Kode_Soal',        'Id_Jenis_Soal',      'COLUMN';
EXEC sp_rename 'N_PA_Cycle_Question_Package_Item.Nama_Soal_Sumber', 'Nama_Bagian_Sumber', 'COLUMN';

/* ---------------------------------------------------------------
 | 3. Kolom baru pada header.
 |
 | Id_Jenis_Indikator SENGAJA TIDAK di sini. Satu paket boleh memuat dua alat tes — bentuk
 | yang dinyatakan HC Learn akan dipakai PA 360 — sehingga satu sel menyeberangi keduanya.
 | Menaruhnya di header memaksa satu sel hanya bisa memuat satu alat tes, dan ujian
 | dua-alat-tes menjadi mustahil diikat.
 |
 | Diverifikasi_At / Diverifikasi_By yang sudah ada dipakai ulang sebagai cap waktu
 | pemeriksaan. Menambah kolom kembar untuk makna yang sama membuat dua sumber yang kelak
 | berselisih.
 --------------------------------------------------------------- */
ALTER TABLE N_PA_Question_Binding_Master ADD
    Id_Master_Paket_Ujian   varchar(100) NULL,
    Nama_Paket_Ujian_Sumber nvarchar(255) NULL,
    Status_Kesehatan        varchar(20) NOT NULL
        CONSTRAINT DF_PA_QBindMaster_Kesehatan DEFAULT ('BELUM_DIPERIKSA'),
    Masalah_Json            nvarchar(max) NULL;

ALTER TABLE N_PA_Cycle_Question_Package ADD
    Id_Master_Paket_Ujian   varchar(100) NULL,
    Nama_Paket_Ujian_Sumber nvarchar(255) NULL;

/* ---------------------------------------------------------------
 | 4. Tabel anak: pemetaan bagian -> kompetensi.
 |
 | DUA indeks unik, bukan satu. Yang pertama melarang satu bagian punya dua label; yang
 | kedua melarang satu kompetensi dipegang dua bagian. Keduanya diperlukan — satu saja
 | meninggalkan separuh pemetaan bisa rangkap.
 |
 | Keduanya SENGAJA tidak menyaring Flag_Aktif. Pelabelan ulang menghapus baris lamanya,
 | bukan menonaktifkannya; unique tersaring justru akan meloloskan baris mati yang menumpuk
 | dan membuat "sel ini pakai apa sekarang" bercampur dengan "sel ini pernah pakai apa".
 --------------------------------------------------------------- */
CREATE TABLE N_PA_Question_Binding_Master_Detail (
    Id_PA_Question_Binding_Master_Detail int IDENTITY(1,1) NOT NULL,
    PA_Question_Binding_Master_Id        int NOT NULL,
    Id_Jenis_Indikator                   varchar(100) NOT NULL,
    Id_Jenis_Soal                        varchar(100) NOT NULL,
    Nama_Alat_Tes                        nvarchar(255) NULL,
    Nama_Bagian_Sumber                   nvarchar(255) NULL,
    Kode_Soal_Sumber                     varchar(50) NULL,
    Urutan_Alat_Tes                      smallint NULL,
    Urutan_Bagian                        smallint NULL,
    PA_Competency_Id                     int NOT NULL,
    Flag_Aktif                           char(1) NOT NULL
        CONSTRAINT DF_PA_QBindDetail_Flag DEFAULT ('Y'),
    Created_At                           datetime2(0) NOT NULL
        CONSTRAINT DF_PA_QBindDetail_Created DEFAULT (SYSDATETIME()),
    Created_By                           int NULL,
    Updated_At                           datetime2(0) NULL,
    Updated_By                           int NULL,
    CONSTRAINT PK_PA_QBindDetail PRIMARY KEY (Id_PA_Question_Binding_Master_Detail),
    CONSTRAINT FK_PA_QBindDetail_Header FOREIGN KEY (PA_Question_Binding_Master_Id)
        REFERENCES N_PA_Question_Binding_Master (Id_PA_Question_Binding_Master),
    CONSTRAINT FK_PA_QBindDetail_Competency FOREIGN KEY (PA_Competency_Id)
        REFERENCES N_PA_Competency (Id_PA_Competency)
);

CREATE UNIQUE INDEX UX_PA_QBindDetail_Bagian
    ON N_PA_Question_Binding_Master_Detail
       (PA_Question_Binding_Master_Id, Id_Jenis_Indikator, Id_Jenis_Soal);

CREATE UNIQUE INDEX UX_PA_QBindDetail_Kompetensi
    ON N_PA_Question_Binding_Master_Detail
       (PA_Question_Binding_Master_Id, PA_Competency_Id);

/* ---------------------------------------------------------------
 | 5. Aturan yang sama pada jalur siklus.
 |
 | Snapshot harus menegakkan invarian yang sama dengan masternya. Kalau tidak, penyalinan
 | yang cacat akan menghasilkan siklus yang bentuknya mustahil dicapai lewat layar mana pun.
 --------------------------------------------------------------- */
/* UX_PA_Cycle_Question_Item_Aktif DICABUT, bukan dibiarkan.
   |
   | Ia unik pada (paket, Kode_Soal) saja. Sesudah ganti nama, itu berarti melarang satu bank
   | soal muncul di DUA alat tes dalam paket yang sama — padahal justru itulah bentuk yang
   | dokumentasi HC Learn nyatakan mungkin, dan yang membuat identitas bagian harus majemuk.
   | Dibiarkan, ia akan menolak paket yang sah dengan pesan yang menuduh datanya kembar.
   |
   | IX_PA_Cycle_Question_Item_Cakupan ikut dicabut karena PA_Competency_Id ada di daftar
   | INCLUDE-nya, dan ALTER COLUMN ditolak selama ada indeks yang bergantung padanya. Ia
   | dibuat ulang di bawah dengan nama kolom yang baru. */
DROP INDEX UX_PA_Cycle_Question_Item_Aktif ON N_PA_Cycle_Question_Package_Item;
DROP INDEX IX_PA_Cycle_Question_Item_Cakupan ON N_PA_Cycle_Question_Package_Item;

ALTER TABLE N_PA_Cycle_Question_Package_Item ADD
    Id_Jenis_Indikator varchar(100) NOT NULL
        CONSTRAINT DF_PA_CycleQItem_Indikator DEFAULT (''),
    Nama_Alat_Tes      nvarchar(255) NULL;

ALTER TABLE N_PA_Cycle_Question_Package_Item ALTER COLUMN PA_Competency_Id int NOT NULL;

CREATE UNIQUE INDEX UX_PA_CycleQItem_Bagian
    ON N_PA_Cycle_Question_Package_Item
       (PA_Cycle_Question_Package_Id, Id_Jenis_Indikator, Id_Jenis_Soal);

CREATE UNIQUE INDEX UX_PA_CycleQItem_Kompetensi
    ON N_PA_Cycle_Question_Package_Item
       (PA_Cycle_Question_Package_Id, PA_Competency_Id);

/* Indeks pencakup dibangun ulang dengan nama kolom baru, plus Id_Jenis_Indikator — ia ikut
   dibaca setiap kali butir siklus dipetakan kembali ke bagiannya. */
CREATE INDEX IX_PA_Cycle_Question_Item_Cakupan
    ON N_PA_Cycle_Question_Package_Item (PA_Cycle_Question_Package_Id, Flag_Aktif)
    INCLUDE (Id_Jenis_Indikator, Id_Jenis_Soal, PA_Competency_Id, Bobot_Penilaian, Skor_Maksimal);

/* ---------------------------------------------------------------
 | 6. Buang jembatan yang tidak pernah dipakai.
 |
 | N_PA_Competency_Kode_Soal dirancang untuk kontrak kode-soal yang kini digantikan, dan
 | dicabut pada amandemen DDL-2026-067 r2. Nol baris, nol rujukan kode, nol FK masuk.
 | Sudah dihapus dari ERD "Main Diagram Project" versi 138.
 --------------------------------------------------------------- */
DROP TABLE N_PA_Competency_Kode_Soal;

COMMIT TRANSACTION;
