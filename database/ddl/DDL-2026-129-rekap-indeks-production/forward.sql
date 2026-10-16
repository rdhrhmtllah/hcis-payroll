-- database/ddl/DDL-2026-129-rekap-indeks-production/forward.sql
-- Target eksekusi: production (emi_db_web_hr) lewat SQL Tracker.
--
-- INDEKS DAN CONSTRAINT SAJA: 36 objek. Tanpa satu pun CREATE TABLE atau
-- ALTER TABLE ADD <kolom> — itu semua di DDL-2026-128.
--
-- URUTAN: SESUDAH DDL-2026-128. Tujuh dari tabel sasarannya baru lahir di sana;
-- tanpa itu bagian Visitor berhenti pada penjaga OBJECT_ID.
--
-- Seluruh pernyataan berpenjaga (IF NOT EXISTS / COL_LENGTH / OBJECT_ID),
-- jadi berkas ini aman diulang. Isinya disalin apa adanya dari berkas DDL
-- yang sudah ditinjau dan dijalankan di development; yang dicabut hanya
-- penjaga "hanya Web_HRIS" pada DDL-087/088/090, sebab sasaran berkas ini
-- memang production.

SET NOCOUNT ON;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Jenis_Dokumen_Kode')
CREATE UNIQUE INDEX UX_SOP_Jenis_Dokumen_Kode
    ON N_SOP_Master_Jenis_Dokumen (Kode_Jenis);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Jenis_Dokumen_Urut')
CREATE INDEX IX_SOP_Jenis_Dokumen_Urut
    ON N_SOP_Master_Jenis_Dokumen (Flag_Aktif, Level_Hirarki, Urutan)
    INCLUDE (Kode_Jenis, Nama_Jenis);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Jenis_Tipe_Pasangan')
CREATE UNIQUE INDEX UX_SOP_Jenis_Tipe_Pasangan
    ON N_SOP_Master_Jenis_Dokumen_Tipe_Item (Jenis_Dokumen_Id, Tipe_Item);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Jenis_Tipe_Cari')
CREATE INDEX IX_SOP_Jenis_Tipe_Cari
    ON N_SOP_Master_Jenis_Dokumen_Tipe_Item (Tipe_Item, Flag_Aktif)
    INCLUDE (Jenis_Dokumen_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Jenis')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Jenis
    FOREIGN KEY (Jenis_Dokumen_Id) REFERENCES N_SOP_Master_Jenis_Dokumen (Id_SOP_Jenis_Dokumen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Jenis' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Jenis
    ON N_SOP_Dokumen (Flag_Aktif, Jenis_Dokumen_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Pemilik' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Pemilik
    ON N_SOP_Dokumen (Flag_Aktif, Divisi_Id, Sub_Divisi_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Entitas_Induk')
ALTER TABLE N_HRIS_Master_Entitas ADD CONSTRAINT FK_HRIS_Entitas_Induk
    FOREIGN KEY (Induk_Entitas_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Entitas_Kode')
CREATE UNIQUE INDEX UX_HRIS_Entitas_Kode
    ON N_HRIS_Master_Entitas (Kode_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Entitas_Urut')
CREATE INDEX IX_HRIS_Entitas_Urut
    ON N_HRIS_Master_Entitas (Flag_Aktif, Urutan)
    INCLUDE (Kode_Entitas, Nama_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Pemilik')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Entitas_Pemilik
    FOREIGN KEY (Entitas_Pemilik_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Entitas_Lingkup')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Entitas_Lingkup
    FOREIGN KEY (Entitas_Lingkup_Id) REFERENCES N_HRIS_Master_Entitas (Id_Entitas);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Entitas' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Entitas
    ON N_SOP_Dokumen (Flag_Aktif, Entitas_Pemilik_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Lingkup' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Lingkup
    ON N_SOP_Dokumen (Flag_Aktif, Entitas_Lingkup_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_APQC_Kode')
CREATE UNIQUE INDEX UX_SOP_APQC_Kode ON N_SOP_Master_APQC (Kode_Proses);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_APQC_Urut')
CREATE INDEX IX_SOP_APQC_Urut ON N_SOP_Master_APQC (Flag_Aktif, Urutan)
    INCLUDE (Kode_Proses, Nama_Proses);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_APQC_Tipe_Pasangan')
CREATE UNIQUE INDEX UX_SOP_APQC_Tipe_Pasangan
    ON N_SOP_Master_APQC_Tipe_Item (APQC_Id, Tipe_Item);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_APQC_Tipe_Cari')
CREATE INDEX IX_SOP_APQC_Tipe_Cari
    ON N_SOP_Master_APQC_Tipe_Item (Tipe_Item, Flag_Aktif) INCLUDE (APQC_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_APQC')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_APQC
    FOREIGN KEY (APQC_Id) REFERENCES N_SOP_Master_APQC (Id_SOP_APQC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_APQC' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_APQC ON N_SOP_Dokumen (Flag_Aktif, APQC_Id);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_HRIS_Departemen_Kode')
CREATE UNIQUE INDEX UX_HRIS_Departemen_Kode ON N_HRIS_Master_Departemen (Kode_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Departemen_Urut')
CREATE INDEX IX_HRIS_Departemen_Urut ON N_HRIS_Master_Departemen (Flag_Aktif, Urutan)
    INCLUDE (Kode_Departemen, Nama_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_SOP_Dokumen_Departemen')
ALTER TABLE N_SOP_Dokumen ADD CONSTRAINT FK_SOP_Dokumen_Departemen
    FOREIGN KEY (Departemen_Id) REFERENCES N_HRIS_Master_Departemen (Id_Departemen);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Dokumen_Departemen' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
CREATE INDEX IX_SOP_Dokumen_Departemen ON N_SOP_Dokumen (Flag_Aktif, Departemen_Id);
GO

/* Penjaga tumpang tindih yang menggantikan UQ_N_SOP_Assignment_Rule_Paket.
 *
 * Terfilter WHERE Flag_Aktif='Y' -- dua sebab. Pertama, paket yang dilepas dari
 * sebuah aturan harus langsung bebas diklaim aturan lain tanpa barisnya
 * dihapus. Kedua, filtered index TIDAK menyamakan baris mati satu sama lain,
 * berbeda dengan UNIQUE CONSTRAINT biasa yang menganggap semua NULL bentrok --
 * jebakan yang persis mematikan penjaga lama. */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_SOP_Assignment_Rule_Paket_Paket')
CREATE UNIQUE INDEX UX_SOP_Assignment_Rule_Paket_Paket
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Paket)
    WHERE Flag_Aktif = 'Y';
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Assignment_Rule_Paket_Rule')
CREATE INDEX IX_SOP_Assignment_Rule_Paket_Rule
    ON N_SOP_Assignment_Rule_Paket (Id_SOP_Assignment_Rule, Urutan);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_SOP_Automation_Run_Induk')
CREATE INDEX IX_SOP_Automation_Run_Induk
    ON N_SOP_Automation_Run (Id_Run_Induk) WHERE Id_Run_Induk IS NOT NULL;
GO

/* Indeks TERFILTER. Pil "Dibatalkan" hanya pernah meminta baris yang
   Nonaktif_At-nya terisi, dan baris itu segelintir; indeks penuh akan
   menyimpan seluruh dokumen aktif hanya untuk tak pernah dibaca. Urutannya
   menurun karena satu-satunya urutan yang diminta layar adalah "paling
   terakhir dinonaktifkan lebih dulu". */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Dokumen_Nonaktif_At' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
    CREATE INDEX IX_N_SOP_Dokumen_Nonaktif_At
        ON N_SOP_Dokumen (Nonaktif_At DESC)
        INCLUDE (Nonaktif_By)
        WHERE Nonaktif_At IS NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-055-sop-dokumen-nonaktif')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-055-sop-dokumen-nonaktif', 'DDL', 'Jejak penonaktifan dokumen SOP', 'Lima kolom jejak penonaktifan pada N_SOP_Dokumen (Nonaktif_At/By/Catatan/Paket/Orang) plus indeks terfilter. Nonaktif_At merangkap penanda status sehingga Flag_Aktif tidak perlu dibalik dan tidak satu pun jalur baca karyawan berubah; Nonaktif_Paket & Nonaktif_Orang membekukan kondisi sebaran pada detik penonaktifan.', 'database/ddl/DDL-2026-055-sop-dokumen-nonaktif/forward.sql', 'docs/changes/DDL-2026-055-sop-dokumen-nonaktif/README.md', 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'sop-nonaktif-dokumen');
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-062-versi-unik-hanya-yang-hidup/forward.sql
-- ------------------------------------------------------------------------
/*  DDL-2026-062 — Penegak invarian versi SOP di basis data
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Status : BELUM DIJALANKAN — dijalankan user. Skrip menjatuhkan indeks, dan
             aturan proyek melarang agen mengeksekusi DROP.

    LATAR
    Audit 30 Agu 2026 (docs/superpowers/specs/2026-08-30-audit-kekokohan-
    versioning-sop.md) memeriksa delapan invarian siklus versi. Datanya bersih
    — dan tak satu pun dari kedelapan invarian itu ditegakkan basis data.
    Semuanya bergantung pada disiplin kode, di modul yang pintunya lebih dari
    satu.

    Change ini memasang penjaga yang tak bisa dilewati pintu kedua.

    EMPAT BAGIAN, MASING-MASING BISA GAGAL SENDIRIAN
      1. Nomor versi unik hanya di antara versi yang HIDUP
      2. Satu dokumen, satu versi PUBLISHED
      3. Id_Versi_Aktif diselaraskan tipenya, lalu diberi FK
      4. FK pada dua rujukan versi yang menentukan apa yang dibaca karyawan

    Tiap bagian berdiri di batch GO-nya sendiri dan punya penjaga prasyaratnya
    sendiri. Satu bagian gagal tidak menyeret yang lain — itu disengaja: MERGE
    dan skrip panjang yang gagal separuh jalan pernah membuat perubahan
    sebelumnya berhenti tanpa ada yang tahu bagian mana yang sudah jalan.

    TIDAK ADA CASCADE pada satu pun FK. Menghapus versi yang masih ditunjuk
    HARUS gagal — itulah gunanya. hapusVersi() sudah memeriksanya di aplikasi;
    ini penjaga bagi pintu yang belum ada.
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

/* ══ 1. NOMOR VERSI UNIK HANYA DI ANTARA VERSI YANG HIDUP ═══════════════════

   Draft yang DIBATALKAN ikut mengunci nomornya selamanya. Bila v01 dibatalkan,
   versi berikutnya terpaksa bernomor v02 — dan nomor resmi dokumen melompat
   dari …/00 ke …/02, menyebut "Revisi 01" yang tak pernah diterbitkan kepada
   siapa pun. Dalam tata kelola dokumen, nomor revisi dikonsumsi saat revisi
   DITERBITKAN, bukan saat draftnya dibuat.

   KENAPA Flag_Aktif, BUKAN "tahapnya CANCEL". Indeks terfilter tak boleh
   menengok tabel lain, jadi predikatnya wajib satu kolom pada tabel yang sama.
   Flag_Aktif tepat karena HANYA tahap CANCEL yang punya Nonaktifkan_Versi='Y'.
   OBSOLETE tidak: versi lama yang tergantikan tetap Flag_Aktif='Y', sehingga
   nomor yang PERNAH beredar terkunci selamanya. Ketepatan itu dikunci uji
   invarian di suite, bukan diandalkan begitu saja.

   Indeks baru dibuat LEBIH DULU, yang lama dijatuhkan sesudahnya — urutan
   sebaliknya membuka jendela ketika tabel tak punya penjaga kembar sama sekali.
*/
IF EXISTS (
    SELECT 1 FROM N_SOP_Item_Versi
     WHERE Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y'
     GROUP BY Id_SOP_Dokumen, Versi_Ke
    HAVING COUNT(*) > 1
)
BEGIN
    SELECT Id_SOP_Dokumen, Versi_Ke, COUNT(*) AS Jumlah
      FROM N_SOP_Item_Versi
     WHERE Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y'
     GROUP BY Id_SOP_Dokumen, Versi_Ke HAVING COUNT(*) > 1;
    THROW 50062, 'Bagian 1 dilewati: ada nomor versi kembar di antara versi hidup. Bereskan baris di atas.', 1;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
                 AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup')
CREATE UNIQUE NONCLUSTERED INDEX UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup
    ON N_SOP_Item_Versi (Id_SOP_Dokumen, Versi_Ke)
 WHERE Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y';
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
             AND name = 'UX_N_SOP_Item_Versi_Dokumen_Urut')
DROP INDEX UX_N_SOP_Item_Versi_Dokumen_Urut ON N_SOP_Item_Versi;
GO

/* ══ 2. SATU DOKUMEN, SATU VERSI PUBLISHED ══════════════════════════════════

   Aturannya sudah nyata dan tertulis sebagai komentar di
   SopItemVersionService::publish(), tetapi ditegakkan murni oleh urutan
   operasi di dalam satu transaksi, dengan SELECT biasa tanpa kunci. Yang
   menyelamatkannya adalah kebetulan isolasi: RCSI mati di Web_HRIS, jadi
   pembaca antre di belakang penulis. Menyalakan RCSI — masih terbuka, belum
   ditolak — akan menghapus penjaganya tanpa satu baris kode pun berubah.

   Sisi aplikasi juga sudah diperkuat (lockForUpdate pada baris dokumen di
   terbitkanVersi), tapi kunci aplikasi hanya mengikat pemanggil yang memakai
   jalur itu. Ini mengikat semuanya.
*/
IF EXISTS (
    SELECT 1 FROM N_SOP_Item_Versi
     WHERE Id_SOP_Dokumen IS NOT NULL AND Status = 'PUBLISHED'
     GROUP BY Id_SOP_Dokumen HAVING COUNT(*) > 1
)
BEGIN
    SELECT Id_SOP_Dokumen, COUNT(*) AS Jumlah_Published
      FROM N_SOP_Item_Versi
     WHERE Id_SOP_Dokumen IS NOT NULL AND Status = 'PUBLISHED'
     GROUP BY Id_SOP_Dokumen HAVING COUNT(*) > 1;
    THROW 50062, 'Bagian 2 dilewati: ada dokumen dengan lebih dari satu versi PUBLISHED. Bereskan baris di atas.', 1;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Item_Versi')
                 AND name = 'UX_N_SOP_Item_Versi_Satu_Published')
CREATE UNIQUE NONCLUSTERED INDEX UX_N_SOP_Item_Versi_Satu_Published
    ON N_SOP_Item_Versi (Id_SOP_Dokumen)
 WHERE Id_SOP_Dokumen IS NOT NULL AND Status = 'PUBLISHED';
GO

/* ══ 3. POINTER VERSI BERLAKU: SELARASKAN TIPE, LALU PAGARI ═════════════════

   N_SOP_Dokumen.Id_Versi_Aktif bertipe BIGINT sementara kunci yang ditunjuknya
   INT. SQL Server menolak FK antartipe yang tak sama, jadi kolomnya diselaraskan
   lebih dulu. Penyempitan ini aman diperiksa, bukan diandaikan: penjaga di
   bawah menolak jalan bila ada nilai yang tak muat.

   Kolom ini ikut dalam IX_N_SOP_Dokumen_Tipe sebagai kolom SERTAAN, sehingga
   indeks itu dijatuhkan dan dibangun ulang mengapit ALTER-nya. Bentuk yang
   dibangun ulang di bawah disalin dari definisi yang BERJALAN pada 30 Agu 2026
   —

       kunci   : (Flag_Aktif, Tipe)
       sertaan : (Judul, Nomor_SOP_Resmi, Id_Versi_Aktif)

   — bukan dari ingatan. Membangun ulang indeks dengan bentuk yang lebih sempit
   menurunkan kueri yang memakainya tanpa satu pun galat; kerusakannya baru
   terlihat sebagai halaman yang melambat, berminggu-minggu kemudian.

   PERIKSA DULU sebelum menjalankan bagian ini: bila bentuknya sudah bergeser
   sejak tanggal itu, salin bentuk yang sekarang, jangan yang tertulis di sini.

   FK-nya melengkapi lingkaran dengan FK_N_SOP_Item_Versi_Dokumen yang sudah
   ada. SQL Server mengizinkannya; akibatnya menghapus dokumen menuntut pointer
   dikosongkan lebih dulu, dan menghapus versi yang sedang berlaku menjadi
   MUSTAHIL. Keduanya memang yang diinginkan.
*/
IF EXISTS (SELECT 1 FROM N_SOP_Dokumen WHERE Id_Versi_Aktif > 2147483647)
    THROW 50062, 'Bagian 3 dilewati: ada Id_Versi_Aktif di luar jangkauan INT.', 1;

IF EXISTS (SELECT 1 FROM N_SOP_Dokumen d WHERE d.Id_Versi_Aktif IS NOT NULL
             AND NOT EXISTS (SELECT 1 FROM N_SOP_Item_Versi v WHERE v.Id_SOP_Item_Versi = d.Id_Versi_Aktif))
BEGIN
    SELECT Id_SOP_Dokumen, Id_Versi_Aktif FROM N_SOP_Dokumen d
     WHERE d.Id_Versi_Aktif IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM N_SOP_Item_Versi v WHERE v.Id_SOP_Item_Versi = d.Id_Versi_Aktif);
    THROW 50062, 'Bagian 3 dilewati: ada pointer versi berlaku yang menunjuk baris tak ada.', 1;
END
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_SOP_Dokumen') AND name = 'IX_N_SOP_Dokumen_Tipe')
DROP INDEX IX_N_SOP_Dokumen_Tipe ON N_SOP_Dokumen;
GO

CREATE NONCLUSTERED INDEX IX_N_SOP_Dokumen_Tipe
    ON N_SOP_Dokumen (Flag_Aktif, Tipe)
    INCLUDE (Judul, Nomor_SOP_Resmi, Id_Versi_Aktif);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Dokumen_Versi_Aktif')
ALTER TABLE N_SOP_Dokumen WITH CHECK
    ADD CONSTRAINT FK_N_SOP_Dokumen_Versi_Aktif
    FOREIGN KEY (Id_Versi_Aktif) REFERENCES N_SOP_Item_Versi (Id_SOP_Item_Versi);
GO

/* ══ 4. DUA RUJUKAN VERSI YANG MENENTUKAN APA YANG DIBACA KARYAWAN ══════════

   N_SOP_Paket_Item.Id_Item_Versi_Aktif menentukan revisi mana yang disajikan
   sebuah paket. N_SOP_Progress.Id_SOP_Item_Versi adalah catatan kepatuhan —
   bukti siapa membaca revisi mana. Keduanya INT, cocok dengan kuncinya, jadi
   tak butuh penyelarasan.

   Tabel log (N_SOP_Log, N_SOP_Aktivitas_Log) SENGAJA dibiarkan tanpa FK: jejak
   audit harus bertahan melampaui baris yang dicatatnya.
*/
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Paket_Item_Versi_Aktif')
ALTER TABLE N_SOP_Paket_Item WITH CHECK
    ADD CONSTRAINT FK_N_SOP_Paket_Item_Versi_Aktif
    FOREIGN KEY (Id_Item_Versi_Aktif) REFERENCES N_SOP_Item_Versi (Id_SOP_Item_Versi);
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Progress_Versi')
ALTER TABLE N_SOP_Progress WITH CHECK
    ADD CONSTRAINT FK_N_SOP_Progress_Versi
    FOREIGN KEY (Id_SOP_Item_Versi) REFERENCES N_SOP_Item_Versi (Id_SOP_Item_Versi);
GO

/* ══ VERIFIKASI ═════════════════════════════════════════════════════════════ */
SELECT i.name, i.is_unique, i.filter_definition
  FROM sys.indexes i
 WHERE i.object_id = OBJECT_ID('N_SOP_Item_Versi') AND i.is_unique = 1;

SELECT fk.name, OBJECT_NAME(fk.parent_object_id) AS tabel, OBJECT_NAME(fk.referenced_object_id) AS rujukan
  FROM sys.foreign_keys fk
 WHERE OBJECT_NAME(fk.referenced_object_id) = 'N_SOP_Item_Versi';

SELECT ty.name AS tipe_Id_Versi_Aktif
  FROM sys.columns c JOIN sys.types ty ON ty.user_type_id = c.user_type_id
 WHERE c.object_id = OBJECT_ID('N_SOP_Dokumen') AND c.name = 'Id_Versi_Aktif';
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-062-versi-unik-hanya-yang-hidup')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-062-versi-unik-hanya-yang-hidup', 'DDL', 'Penegak invarian versi SOP di basis data',
 'Nomor versi unik hanya antar versi hidup; satu dokumen satu PUBLISHED; Id_Versi_Aktif diselaraskan ke INT lalu diberi FK; FK pada Paket_Item.Id_Item_Versi_Aktif dan Progress.Id_SOP_Item_Versi.',
 'database/ddl/DDL-2026-062-versi-unik-hanya-yang-hidup/forward.sql',
 'docs/changes/DDL-2026-062-versi-unik-hanya-yang-hidup/README.md',
 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'penegak-versi');
GO

/* ==========================================================================
   BAGIAN B — Otorisasi, absensi, diagnostik, dan anti-replay LMS
   ========================================================================== */


-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-087-otorisasi-heap-tanpa-pk/forward.sql
-- ------------------------------------------------------------------------
-- DDL-2026-087-otorisasi-heap-tanpa-pk
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : N_HRIS_Role_Menu_Access — primary key
-- BAGIAN 2 : N_HRIS_Role_Menu_Access — unique index tersaring
-- BAGIAN 3 : HRIS_Page_Access — primary key
-- BAGIAN 4 : verifikasi
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Dua tabel OTORISASI berbentuk HEAP: tanpa primary key, tanpa satu pun indeks.
--
--   N_HRIS_Role_Menu_Access   2.005 baris, 200 KB
--   HRIS_Page_Access          3.026 baris
--
-- Diukur di sys.dm_db_index_usage_stats untuk N_HRIS_Role_Menu_Access:
--
--   user_seeks = 0,  user_scans = 18,  user_lookups = 0
--
-- Nol seek. Bukan kebetulan — tanpa indeks, SQL Server memang tidak punya cara
-- lain selain memindai seluruh tabel, termasuk untuk mencari SATU baris.
--
-- ── KENAPA UNIQUE INDEX-nya PENTING, BUKAN SEKADAR RAPI ──────────────────────
--
-- SystemAuthController::togglePermission melakukan cek-lalu-tulis:
--
--   $existing = ...->where('Id_Page_Access', ?)->where('Id_Aksi', ?)
--                  ->where('Flag_Aktif', 'Y')->first();
--   if ($existing) { ...->update(...); } else { ...->insert(...); }
--
-- Tanpa unique index, dua permintaan bersamaan untuk pasangan yang sama
-- sama-sama melihat "belum ada" dan sama-sama INSERT. Hasilnya dua baris izin
-- aktif untuk (halaman, aksi) yang sama — dan mana yang menang saat dibaca
-- bergantung pada urutan pemindaian.
--
-- Bandingkan dengan tabel sekerabatnya, N_HRIS_Role_Resource_Access, yang SUDAH
-- punya UQ_NHRIS_RRA_RoleContextTypeCode di keempat kolom kuncinya. Pola
-- kodenya sama persis; yang satu dijaga basis data, yang satu tidak.
--
-- ── KENAPA TERSARING (Flag_Aktif = 'Y') ──────────────────────────────────────
--
-- Kode hanya menganggap baris ber-Flag_Aktif='Y' sebagai "sudah ada". Unique
-- index penuh akan menolak INSERT ketika ada baris NONAKTIF berpasangan sama —
-- padahal menurut kodenya itu sah. Indeks tersaring menegakkan persis invarian
-- yang dipakai kodenya, tidak lebih.
--
-- Diperiksa pada data sekarang: nol pasangan kembar, baik di seluruh tabel
-- maupun di baris aktif saja, dan nol nilai NULL di kedua kolomnya. Jadi
-- pembuatannya tidak akan gagal.
--
-- ── YANG BELUM DIKETAHUI ─────────────────────────────────────────────────────
--
-- Semua angka di atas dari basis data PENGEMBANGAN. Struktur biasanya sama
-- lintas lingkungan, tetapi jumlah baris dan ada-tidaknya duplikat TIDAK.
-- BAGIAN 4 memuat pemeriksaan yang harus dijalankan di production LEBIH DULU:
-- bila di sana ada pasangan kembar, BAGIAN 2 akan gagal dan datanya harus
-- dibereskan dahulu lewat DML tersendiri.

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* =====================================================================
   BAGIAN 0 — gerbang: jangan sampai salah basis data
   ===================================================================== */
-- [rekap] penjaga "hanya Web_HRIS" dicabut: sasaran berkas ini production.

/* =====================================================================
   BAGIAN 1 — N_HRIS_Role_Menu_Access: primary key

   Id_Role_Menu_Access sudah int NOT NULL dan terukur 2.005 nilai unik dari
   2.005 baris. CLUSTERED: tabelnya kecil (200 KB) dan seluruh aksesnya lewat
   kunci ini atau lewat pasangan di BAGIAN 2, jadi heap tidak memberi apa pun.
   ===================================================================== */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Role_Menu_Access') AND is_primary_key = 1
)
BEGIN
    ALTER TABLE dbo.N_HRIS_Role_Menu_Access
        ADD CONSTRAINT PK_N_HRIS_Role_Menu_Access
        PRIMARY KEY CLUSTERED (Id_Role_Menu_Access);

    PRINT 'PK_N_HRIS_Role_Menu_Access dibuat.';
END
ELSE
    PRINT 'PK_N_HRIS_Role_Menu_Access sudah ada, dilewati.';

/* =====================================================================
   BAGIAN 2 — N_HRIS_Role_Menu_Access: unique index tersaring

   Inilah yang mengubah cek-lalu-tulis di togglePermission dari "berharap
   tidak ada yang bersamaan" menjadi dijaga basis data. Balapan tidak lagi
   menghasilkan baris kembar; ia menghasilkan galat 2601 yang bisa ditangkap
   pemanggilnya.
   ===================================================================== */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Role_Menu_Access')
      AND name = N'UQ_NHRIS_RMA_PageAksi_Aktif'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UQ_NHRIS_RMA_PageAksi_Aktif
        ON dbo.N_HRIS_Role_Menu_Access (Id_Page_Access, Id_Aksi)
        WHERE Flag_Aktif = 'Y';

    PRINT 'UQ_NHRIS_RMA_PageAksi_Aktif dibuat.';
END
ELSE
    PRINT 'UQ_NHRIS_RMA_PageAksi_Aktif sudah ada, dilewati.';

/* =====================================================================
   BAGIAN 3 — HRIS_Page_Access: primary key

   3.026 baris, Id_Page_Access terukur 3.026 nilai unik dan nol NULL.

   TIDAK disertai unique index tambahan: berbeda dengan BAGIAN 2, belum ada
   pola cek-lalu-tulis yang terbukti pada tabel ini, dan menambahkan batasan
   yang tidak dituntut kode adalah cara membuat kegagalan yang tak seorang pun
   siap menanganinya.
   ===================================================================== */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.HRIS_Page_Access') AND is_primary_key = 1
)
BEGIN
    ALTER TABLE dbo.HRIS_Page_Access
        ADD CONSTRAINT PK_HRIS_Page_Access
        PRIMARY KEY CLUSTERED (Id_Page_Access);

    PRINT 'PK_HRIS_Page_Access dibuat.';
END
ELSE
    PRINT 'PK_HRIS_Page_Access sudah ada, dilewati.';

/* =====================================================================
   BAGIAN 4 — verifikasi (aman dijalankan sendiri, hanya membaca)

   JALANKAN INI DI PRODUCTION LEBIH DULU. Bila kembar_aktif > 0 di sana,
   BAGIAN 2 akan gagal: bereskan datanya dengan DML tersendiri sebelum
   menjalankan skrip ini.
   ===================================================================== */
SELECT
    (SELECT COUNT(*) FROM dbo.N_HRIS_Role_Menu_Access)                        AS rma_baris,
    (SELECT COUNT(DISTINCT Id_Role_Menu_Access) FROM dbo.N_HRIS_Role_Menu_Access) AS rma_id_unik,
    (SELECT COUNT(*) FROM (
        SELECT Id_Page_Access, Id_Aksi
        FROM dbo.N_HRIS_Role_Menu_Access
        WHERE Flag_Aktif = 'Y'
        GROUP BY Id_Page_Access, Id_Aksi
        HAVING COUNT(*) > 1) x)                                               AS rma_kembar_aktif,
    (SELECT COUNT(*) FROM dbo.HRIS_Page_Access)                               AS hpa_baris,
    (SELECT COUNT(DISTINCT Id_Page_Access) FROM dbo.HRIS_Page_Access)         AS hpa_id_unik;

SELECT t.name AS tabel, i.name AS indeks, i.type_desc, i.is_primary_key, i.is_unique, i.filter_definition
FROM sys.indexes i
JOIN sys.tables t ON t.object_id = i.object_id
WHERE t.name IN (N'N_HRIS_Role_Menu_Access', N'HRIS_Page_Access')
ORDER BY t.name, i.index_id;

-- Yang diharapkan sesudah BAGIAN 1-3:
--   N_HRIS_Role_Menu_Access : PK_N_HRIS_Role_Menu_Access (CLUSTERED, pk)
--                             UQ_NHRIS_RMA_PageAksi_Aktif (NONCLUSTERED, unik,
--                             filter [Flag_Aktif]='Y')
--   HRIS_Page_Access        : PK_HRIS_Page_Access (CLUSTERED, pk)
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-088-indeks-heap-dipindai-berat/forward.sql
-- ------------------------------------------------------------------------
-- DDL-2026-088-indeks-heap-dipindai-berat
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : Transaksi_Cuti_Det
-- BAGIAN 2 : N_HRIS_Transaksi_Shift_Det
-- BAGIAN 3 : HRIS_Page_Access
-- BAGIAN 4 : N_HRIS_Grup_Fitur_Map
-- BAGIAN 5 : HRIS_Karyawan_Team
-- BAGIAN 6 : verifikasi
-- BAGIAN 7 : yang HARUS dijalankan di production sebelum menerapkan ini
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Lima tabel berbentuk HEAP dipindai puluhan ribu kali tanpa satu pun seek.
-- Diukur dari sys.dm_db_index_usage_stats pada Web_HRIS (development), jendela
-- 2026-08-18 03:26 (sqlserver_start_time) sampai 2026-09-08 16:27:
--
--   tabel                            baris   user_scans   user_seeks   indeks
--   ------------------------------------------------------------------------
--   Transaksi_Cuti_Det                 184      127.654            0        0
--   N_HRIS_Transaksi_Shift_Det         268       72.582            0        0
--   HRIS_Page_Access                 3.026       50.330            0        1*
--   N_HRIS_Grup_Fitur_Map              103       43.601            0        0
--   HRIS_Karyawan_Team                 108        5.382            0        0
--
--   * satu unique index pada kolom surrogate Id_Page_Access, yang tidak dipakai
--     oleh satu pun jalur penyaringan nyata (1 seek sepanjang jendela di atas).
--
-- Nol seek bukan kebetulan: tanpa indeks yang cocok, SQL Server memang tidak
-- punya pilihan selain memindai seluruh tabel — termasuk untuk mencari SATU
-- baris.
--
-- ── KENAPA INI TIDAK BOLEH DINILAI DARI JUMLAH BARIS DEVELOPMENT ─────────────
--
-- 184 baris memang murah dipindai. Tapi tiga hal membuat angka itu menyesatkan:
--
--   1. Development bukan production. Transaksi_Cuti_Det di sini 184 baris
--      karena datanya sebagian; di production ia menampung rincian cuti
--      bertahun-tahun. BAGIAN 7 mewajibkan angka production diambil dulu.
--   2. RCSI mati di basis data ini. Pemindaian penuh mengambil shared lock atas
--      lebih banyak baris daripada seek, jadi biayanya bukan hanya CPU
--      melainkan juga antrean penulis.
--   3. Frekuensinya yang jadi soal. 127.654 pemindaian dalam tiga minggu berarti
--      tabel ini disapu habis rata-rata sekali tiap 14 detik.
--
-- ── KOLOM PENYARINGNYA DARI MANA ─────────────────────────────────────────────
--
-- Bukan tebakan: setiap klausa where/join di app/ dikumpulkan, lalu kolomnya
-- dicocokkan ke sys.columns supaya salah-baca alias tidak ikut terbawa.
-- Jumlah pemakaian di kode:
--
--   Transaksi_Cuti_Det          No_Transaksi 37, Kode_Karyawan 16, Tanggal_Cuti 6
--   N_HRIS_Transaksi_Shift_Det  Id_Detail 5, Status 5, Tanggal_Shift 4
--   HRIS_Page_Access            Jenis_page 43, UserID_Web 41, Flag_Aktif 35
--   N_HRIS_Grup_Fitur_Map       Id_Grup_Fitur 22, Jenis_page 20, Flag_Aktif 12
--   HRIS_Karyawan_Team          Kode_Karyawan 23, Kode_Karyawan_Team 21
--
-- ── HUBUNGANNYA DENGAN DDL-2026-087 ──────────────────────────────────────────
--
-- DDL-2026-087 menambahkan PK_HRIS_Page_Access (CLUSTERED, Id_Page_Access).
-- Itu menghapus bentuk heap-nya, tetapi TIDAK menolong satu pun kueri di atas:
-- tidak ada jalur yang menyaring lewat Id_Page_Access. Kedua perubahan saling
-- melengkapi dan tidak bertabrakan; keduanya boleh diterapkan dalam urutan apa
-- pun. Indeks di bawah bekerja baik pada heap maupun sesudah tabelnya berklaster.
--
-- ── APA YANG TIDAK DILAKUKAN DI SINI ─────────────────────────────────────────
--
-- Tidak ada DROP. Tidak ada perubahan kolom. Tidak ada perubahan data. Yang
-- dibuat hanya indeks non-klaster, dan setiap satunya bisa dibatalkan dengan
-- drop.sql tanpa menyentuh baris mana pun.

/* ═══ BAGIAN 0 : gerbang basis data ═══════════════════════════════════════ */

-- [rekap] penjaga "hanya Web_HRIS" dicabut: sasaran berkas ini production.
GO

/* ═══ BAGIAN 1 : Transaksi_Cuti_Det ═══════════════════════════════════════
 *
 * Jalur dominan: ->where('No_Transaksi', $noTransaksi) lalu mengambil seluruh
 * rincian transaksi itu. INCLUDE-nya membuat kueri tersebut tidak perlu kembali
 * ke tabel sama sekali.
 *
 * Kode_Perusahaan sengaja TIDAK dijadikan kolom pertama walaupun ia bagian dari
 * identitas baris: tidak satu pun jalur di app/ menyaring dengannya, dan indeks
 * (Kode_Perusahaan, No_Transaksi) tidak bisa di-seek oleh kueri yang hanya tahu
 * No_Transaksi.
 */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.Transaksi_Cuti_Det')
                 AND name = 'IX_Transaksi_Cuti_Det_NoTransaksi')
BEGIN
    CREATE NONCLUSTERED INDEX IX_Transaksi_Cuti_Det_NoTransaksi
        ON dbo.Transaksi_Cuti_Det (No_Transaksi)
        INCLUDE (Kode_Karyawan, Urut_Detail, Tanggal_Cuti, Urut_Oto);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.Transaksi_Cuti_Det')
                 AND name = 'IX_Transaksi_Cuti_Det_Karyawan_Tanggal')
BEGIN
    CREATE NONCLUSTERED INDEX IX_Transaksi_Cuti_Det_Karyawan_Tanggal
        ON dbo.Transaksi_Cuti_Det (Kode_Karyawan, Tanggal_Cuti)
        INCLUDE (No_Transaksi);
END
GO

/* ═══ BAGIAN 2 : N_HRIS_Transaksi_Shift_Det ═══════════════════════════════
 *
 * Id_Detail adalah kunci induk: hampir setiap pembacaan rincian shift berangkat
 * dari header-nya.
 */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.N_HRIS_Transaksi_Shift_Det')
                 AND name = 'IX_N_HRIS_Transaksi_Shift_Det_Induk')
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Transaksi_Shift_Det_Induk
        ON dbo.N_HRIS_Transaksi_Shift_Det (Id_Detail)
        INCLUDE (Tanggal_Shift, Status, Flag_Applied, Needs_Approval, Flag_Approval);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.N_HRIS_Transaksi_Shift_Det')
                 AND name = 'IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi')
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi
        ON dbo.N_HRIS_Transaksi_Shift_Det (No_Transaksi, Tanggal_Shift);
END
GO

/* ═══ BAGIAN 3 : HRIS_Page_Access ═════════════════════════════════════════
 *
 * Dua arah pembacaan yang sama-sama nyata:
 *   a. "halaman apa saja yang boleh dibuka user ini"  -> UserID_Web lebih dulu
 *   b. "siapa saja yang boleh membuka halaman ini"    -> Jenis_page lebih dulu
 *
 * Flag_Aktif ikut sebagai kolom kunci KEDUA, bukan saringan indeks: nilainya
 * NULL-able dan sebagian jalur membaca baris non-aktif dengan sengaja, jadi
 * indeks tersaring WHERE Flag_Aktif='Y' akan membuat jalur itu kembali memindai.
 */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.HRIS_Page_Access')
                 AND name = 'IX_HRIS_Page_Access_User_Aktif')
BEGIN
    CREATE NONCLUSTERED INDEX IX_HRIS_Page_Access_User_Aktif
        ON dbo.HRIS_Page_Access (UserID_Web, Flag_Aktif)
        INCLUDE (Jenis_page, Id_Page_Access, Urutan_Menu);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.HRIS_Page_Access')
                 AND name = 'IX_HRIS_Page_Access_Halaman_Aktif')
BEGIN
    CREATE NONCLUSTERED INDEX IX_HRIS_Page_Access_Halaman_Aktif
        ON dbo.HRIS_Page_Access (Jenis_page, Flag_Aktif)
        INCLUDE (UserID_Web, Id_Page_Access);
END
GO

/* ═══ BAGIAN 4 : N_HRIS_Grup_Fitur_Map ════════════════════════════════════
 *
 * Peta grup fitur ke halaman: dibaca saat menyusun menu, yaitu pada hampir
 * setiap kunjungan halaman. 43.601 pemindaian atas 103 baris.
 */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map')
                 AND name = 'IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif')
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif
        ON dbo.N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Flag_Aktif)
        INCLUDE (Jenis_page, Sort_Order, Is_Show_Sidebar, Is_Default_Landing);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.N_HRIS_Grup_Fitur_Map')
                 AND name = 'IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif')
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif
        ON dbo.N_HRIS_Grup_Fitur_Map (Jenis_page, Flag_Aktif)
        INCLUDE (Id_Grup_Fitur);
END
GO

/* ═══ BAGIAN 5 : HRIS_Karyawan_Team ═══════════════════════════════════════
 *
 * Tabel dua kolom, dibaca dari kedua arah (anggota -> tim, tim -> anggota).
 * Karena hanya dua kolom, keduanya indeks penutup penuh tanpa INCLUDE.
 */

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.HRIS_Karyawan_Team')
                 AND name = 'IX_HRIS_Karyawan_Team_Karyawan')
BEGIN
    CREATE NONCLUSTERED INDEX IX_HRIS_Karyawan_Team_Karyawan
        ON dbo.HRIS_Karyawan_Team (Kode_Karyawan, Kode_Karyawan_Team);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('dbo.HRIS_Karyawan_Team')
                 AND name = 'IX_HRIS_Karyawan_Team_Tim')
BEGIN
    CREATE NONCLUSTERED INDEX IX_HRIS_Karyawan_Team_Tim
        ON dbo.HRIS_Karyawan_Team (Kode_Karyawan_Team, Kode_Karyawan);
END
GO

/* ═══ BAGIAN 6 : verifikasi ═══════════════════════════════════════════════ */

SELECT  t.name  AS tabel,
        i.name  AS indeks,
        i.type_desc AS jenis,
        STUFF((SELECT ', ' + c.name
               FROM sys.index_columns ic
               JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                 AND ic.is_included_column = 0
               ORDER BY ic.key_ordinal
               FOR XML PATH('')), 1, 2, '') AS kolom_kunci
FROM sys.indexes i
JOIN sys.tables  t ON t.object_id = i.object_id
WHERE t.name IN ('Transaksi_Cuti_Det', 'N_HRIS_Transaksi_Shift_Det',
                 'HRIS_Page_Access', 'N_HRIS_Grup_Fitur_Map', 'HRIS_Karyawan_Team')
  AND i.type_desc <> 'HEAP'
ORDER BY t.name, i.name;
GO

SET NOEXEC OFF;
GO

/* ═══ BAGIAN 7 : yang HARUS dijalankan di production LEBIH DULU ═══════════
 *
 * Seluruh angka di atas berasal dari development. Sebelum menerapkan skrip ini
 * ke production, jalankan blok HANYA-BACA berikut di sana dan bandingkan.
 * Kalau di production kelima tabel itu sudah punya indeks yang setara, skrip
 * ini tidak perlu dijalankan sama sekali.
 *
 *   SELECT  t.name AS tabel,
 *           (SELECT SUM(p.rows) FROM sys.partitions p
 *             WHERE p.object_id = t.object_id AND p.index_id IN (0, 1)) AS baris,
 *           ISNULL(i.name, '(heap)') AS indeks,
 *           i.type_desc,
 *           ISNULL(us.user_seeks, 0)   AS seeks,
 *           ISNULL(us.user_scans, 0)   AS scans,
 *           ISNULL(us.user_updates, 0) AS updates_,
 *           (SELECT sqlserver_start_time FROM sys.dm_os_sys_info) AS sejak
 *   FROM sys.tables t
 *   JOIN sys.indexes i ON i.object_id = t.object_id
 *   LEFT JOIN sys.dm_db_index_usage_stats us
 *          ON us.object_id = i.object_id AND us.index_id = i.index_id
 *         AND us.database_id = DB_ID()
 *   WHERE t.name IN ('Transaksi_Cuti_Det', 'N_HRIS_Transaksi_Shift_Det',
 *                    'HRIS_Page_Access', 'N_HRIS_Grup_Fitur_Map', 'HRIS_Karyawan_Team')
 *   ORDER BY t.name, i.index_id;
 *
 * Cara yang lebih lengkap dan bisa diulang: jalankan
 *
 *   php artisan db:inventaris-skema --pemakaian --keluaran=prod.json
 *
 * di production (perintah itu hanya membaca katalog sistem), lalu
 *
 *   php artisan db:inventaris-skema --banding=dev.json,prod.json
 *
 * yang melaporkan tabel, kolom, dan indeks yang hanya ada di salah satu sisi —
 * termasuk indeks yang NAMANYA sama tetapi kuncinya berbeda.
 */
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-090-indeks-diagnostik-permintaan/forward.sql
-- ------------------------------------------------------------------------
-- DDL-2026-090-indeks-diagnostik-permintaan
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : indeks penutup untuk jendela waktu /admin/activity
-- BAGIAN 2 : indeks pada Id, HANYA bila production ternyata heap
-- BAGIAN 3 : verifikasi
-- BAGIAN 4 : yang HARUS dijalankan di production sebelum menerapkan ini
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- N_HRIS_Request_Diagnostics di production sudah 71.000 baris (dilaporkan
-- pengguna, 9 September 2026) dan tidak pernah dipangkas: sampai
-- hris:diagnostik-prune ada, tak satu pun baris pernah dibuang sejak middleware
-- RequestDiagnostics dipasang.
--
-- Diukur dari sys.dm_db_index_usage_stats pada Web_HRIS (development):
--
--   indeks                                   seeks   scans   lookups   updates
--   -------------------------------------------------------------------------
--   PK__N_HRIS_R__3214EC07 (clustered, Id)       0      65        68     2.225
--   IX_N_HRIS_Request_Diagnostics_Created       92      14         0     2.225
--
-- Dua hal terbaca dari sana:
--
--   1. Indeks clustered TIDAK PERNAH di-seek. Tak ada satu pun jalur aplikasi
--      yang mencari baris lewat Id; ia hanya jadi sasaran pemindaian penuh dan
--      key lookup. `ORDER BY Id DESC` ad-hoc karena itu = pindai seluruh tabel
--      lalu sortir.
--
--   2. 65 pemindaian penuh itu datang dari halaman /admin/activity sendiri.
--      diagnosticsSummary() meng-agregat SELURUH jendela waktunya (COUNT,
--      SUM CASE atas Http_Status/Db_Query_Count/Db_Query_Ms, AVG/MAX Total_Ms)
--      dan diagnosticsRequests() menghitung total baris untuk paginasi.
--      IX_..._Created hanya berkunci Created_At TANPA include, jadi tak satu pun
--      kolom yang diagregat ada di dalamnya — optimizer menyerah pada seek dan
--      memindai indeks clustered, yang membawa serta kolom Slowest_Query_Sql
--      nvarchar(4000).
--
-- Artinya: tiap kali orang membuka /admin/activity untuk mencari tahu kenapa
-- sistem lambat, ia memindai tabel yang justru paling cepat membesar ketika
-- sistem sedang lambat. Pemantau ikut jadi beban yang dipantau.
--
-- ── KENAPA INDEKS PENUTUP, BUKAN SEKADAR INDEKS BARU ─────────────────────────
--
-- Kolom yang diagregat semuanya sempit (int). Menyalinnya ke dalam INCLUDE
-- membuat agregat dilayani sepenuhnya dari indeks, tanpa menyentuh baris
-- clustered. Lebar rata-rata baris di development: Slowest_Query_Sql 339 byte
-- (maks 4.000), Path 26 byte — jadi indeks penutup ini berkali lipat lebih
-- ramping daripada barisnya sendiri.
--
-- Slowest_Query_Sql SENGAJA TIDAK di-include: ia satu-satunya kolom lebar, dan
-- hanya dibutuhkan untuk baris yang benar-benar ditampilkan (maks 200 per
-- halaman) — 200 key lookup jauh lebih murah daripada menggemukkan indeks yang
-- dipindai penuh pada tiap agregat.
--
-- Kunci indeks dibuat DESC mengikuti ORDER BY Created_At DESC pada kedua jalur
-- baca, supaya tak ada operator sort yang tersisa.
--
-- ── YANG TERUKUR PADA SKALA PRODUCTION ───────────────────────────────────────
--
-- Development cuma 1.190 baris — terlalu kecil untuk membuktikan apa pun. Karena
-- itu agregat diagnosticsSummary() yang sama dijalankan atas tabel CORETAN
-- berisi 71.000 baris (37 MB), sebesar production, di container SQL Server lokal.
-- Tabel dev maupun production tidak disentuh.
--
--   tanpa indeks penutup : 4.757 logical reads, 12,4 ms
--   dengan indeks penutup:    16 logical reads,  2,1 ms
--   ongkos indeks        : 5 MB
--
-- 4.757 -> 16 adalah selisih antara memindai seluruh tabel dan menyentuh hanya
-- jendela waktunya. Angka ms-nya sendiri kecil karena mesin lokal tidak sedang
-- menanggung beban apa-apa; yang bertahan lintas mesin adalah jumlah bacanya.
--
-- ── DUA SEBAB YANG DIPISAHKAN ────────────────────────────────────────────────
--
-- "Lambatnya karena kolom Slowest_Query_Sql yang lebar, atau murni karena tak
-- ada indeks?" Dipisahkan dengan dua tabel berisi 71.000 baris dan indeks yang
-- sama, beda HANYA pada terisi/tidaknya kolom lebar itu (40,4 MB lawan 11,6 MB):
--
--   kueri                                       kolom terisi    kolom NULL
--   ------------------------------------------------------------------------
--   TOP 50 * ORDER BY Id DESC, indeks Id ADA         10 baca        7 baca
--   TOP 50 * ORDER BY <tanpa indeks> DESC         4.825 baca    1.119 baca
--   agregat diagnosticsSummary()                  4.825 baca    1.119 baca
--
-- Jawabannya: MURNI KARENA INDEKS. Dengan indeks di Id, kueri yang sama hanya
-- 10 logical reads — kolom lebar itu tidak menghalangi apa pun. Begitu indeksnya
-- tidak ada, kueri yang sama jadi 4.825: naik 480 kali lipat.
--
-- Kolom lebar itu PENGGANDA, bukan sebab. Ia membuat pemindaian yang sudah
-- terlanjur terjadi jadi 4,3 kali lebih mahal (4.825 lawan 1.119), karena tiap
-- halaman 8 KB memuat lebih sedikit baris. Menyempitkan kolomnya tanpa memberi
-- indeks hanya memperbaiki 4,3x dari masalah 480x.
--
-- Dua akibat langsung untuk berkas ini: BAGIAN 2 (indeks pada Id) bukan
-- kelengkapan, ia justru yang menjawab keluhan aslinya; dan Slowest_Query_Sql
-- tetap TIDAK di-include di BAGIAN 1, karena memasukkannya akan memindahkan
-- ongkos 4,3x itu ke dalam indeks yang justru dibuat untuk dipindai murah.
--

-- ── HUBUNGAN DENGAN PERUBAHAN LAIN ───────────────────────────────────────────
--
-- Indeks ini mengurangi biaya PER PEMINDAIAN. Yang mengurangi JUMLAH BARIS yang
-- dipindai adalah command hris:diagnostik-prune (masa simpan baku 90 hari, lihat
-- config request-diagnostics.retention_days). Keduanya saling melengkapi: tanpa
-- pemangkasan, indeks apa pun akhirnya ikut membesar tanpa batas.
--
-- ── RISIKO ───────────────────────────────────────────────────────────────────
--
-- Tabel ini menerima INSERT dari middleware pada tiap permintaan lambat/5xx.
-- Menambah indeks menambah biaya tulis itu. Dinilai sepadan: tulisnya satu baris
-- per INSIDEN (bukan per permintaan), sementara bacanya memindai seluruh tabel.
--
-- ONLINE=OFF dipakai karena edisi SQL Server di sini belum dipastikan Enterprise;
-- pembuatan indeks karena itu memegang lock skema. Jalankan di luar jam sibuk.

-- ── BAGIAN 0 : gerbang basis data ────────────────────────────────────────────
-- [rekap] penjaga "hanya Web_HRIS" dicabut: sasaran berkas ini production.
GO

IF OBJECT_ID('dbo.N_HRIS_Request_Diagnostics', 'U') IS NULL
BEGIN
    RAISERROR('N_HRIS_Request_Diagnostics tidak ada di basis data ini.', 16, 1);
    SET NOEXEC ON;
END;
GO

-- ── BAGIAN 1 : indeks penutup untuk jendela waktu ────────────────────────────
-- Melayani diagnosticsSummary() (agregat atas seluruh jendela) dan
-- diagnosticsRequests() (COUNT untuk paginasi + daftar terurut) tanpa menyentuh
-- baris clustered, kecuali untuk Slowest_Query_Sql pada baris yang ditampilkan.
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
     WHERE object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics')
       AND name = 'IX_N_HRIS_Request_Diagnostics_Created_Penutup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Request_Diagnostics_Created_Penutup
        ON dbo.N_HRIS_Request_Diagnostics (Created_At DESC)
        INCLUDE (Http_Status, Total_Ms, Conn_Acquire_Ms, Db_Query_Count,
                 Db_Query_Ms, Slowest_Query_Ms, Method, Kode_Karyawan,
                 Path, Trace_Id)
        WITH (ONLINE = OFF, DATA_COMPRESSION = PAGE);
END;
GO

-- ── BAGIAN 2 : indeks pada Id, HANYA bila tabelnya heap ──────────────────────
-- Di development tabel ini punya PK clustered pada Id, jadi blok ini no-op.
-- Ia ada untuk kemungkinan production dibuat lewat skrip lain tanpa PK: di sana
-- `ORDER BY Id DESC` (pola inspeksi manual yang lazim) memindai seluruh tabel.
-- Indeks NONCLUSTERED sengaja dipilih, bukan membangun ulang jadi clustered:
-- yang terakhir menulis ulang seluruh tabel dan menahan lock jauh lebih lama.
IF NOT EXISTS (
    SELECT 1
      FROM sys.index_columns ic
      JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
     WHERE ic.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics')
       AND ic.key_ordinal = 1
       AND ic.is_included_column = 0
       AND c.name = 'Id'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_HRIS_Request_Diagnostics_Id
        ON dbo.N_HRIS_Request_Diagnostics (Id DESC)
        WITH (ONLINE = OFF, DATA_COMPRESSION = PAGE);
END;
GO

-- ── BAGIAN 3 : verifikasi ────────────────────────────────────────────────────
-- Harus muncul IX_..._Created_Penutup; IX_..._Id hanya bila BAGIAN 2 berjalan.
SELECT ISNULL(i.name, '(HEAP)') AS indeks,
       i.type_desc,
       STUFF((SELECT ',' + c.name
                FROM sys.index_columns ic
                JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
               WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                 AND ic.is_included_column = 0
               ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 1, '') AS kunci
  FROM sys.indexes i
 WHERE i.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics');
GO

SET NOEXEC OFF;
GO

-- ── BAGIAN 4 : yang HARUS dijalankan di production sebelum menerapkan ini ────
--
-- Semua angka di atas dari development (1.190 baris). Production 71.000 baris,
-- dan bentuk indeksnya BELUM diperiksa. Ambil dulu:
--
--   -- a. bentuk indeks sekarang (menentukan apakah BAGIAN 2 berjalan)
--   SELECT ISNULL(i.name,'(HEAP)') AS indeks, i.type_desc
--     FROM sys.indexes i
--    WHERE i.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics');
--
--   -- b. ukuran sebenarnya (menentukan lama pembuatan indeks)
--   --    baris dan MB DIPISAH: menggabung sys.partitions ke sys.allocation_units
--   --    menggandakan p.rows begitu tabelnya punya allocation unit LOB/
--   --    ROW_OVERFLOW, dan Slowest_Query_Sql nvarchar(4000) bisa memicunya.
--   SELECT SUM(p.rows) AS baris
--     FROM sys.partitions p
--    WHERE p.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics')
--      AND p.index_id IN (0, 1);
--
--   SELECT SUM(a.total_pages) * 8 / 1024 AS mb_total
--     FROM sys.partitions p
--     JOIN sys.allocation_units a ON a.container_id = p.partition_id
--    WHERE p.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics');
--
--   -- c. seek/scan production (membuktikan pola yang sama, bukan mengandaikan)
--   SELECT ISNULL(i.name,'(HEAP)') AS indeks, u.user_seeks, u.user_scans, u.user_lookups
--     FROM sys.indexes i
--     LEFT JOIN sys.dm_db_index_usage_stats u
--            ON u.object_id = i.object_id AND u.index_id = i.index_id
--           AND u.database_id = DB_ID()
--    WHERE i.object_id = OBJECT_ID('dbo.N_HRIS_Request_Diagnostics');
--
-- Jalankan hris:diagnostik-prune LEBIH DULU bila (b) menunjukkan ratusan MB:
-- membangun indeks atas baris yang sebentar lagi dibuang itu kerja dua kali.
GO

-- ------------------------------------------------------------------------
-- SUMBER: database/ddl/DDL-2026-095-anti-replay-lms-setara-hris/forward.sql
-- ------------------------------------------------------------------------
-- database/ddl/DDL-2026-095-anti-replay-lms-setara-hris/forward.sql
--
-- Menyetarakan jaminan anti-replay registry LMS dengan kembarannya di HRIS.
--
-- MASALAH YANG DISELESAIKAN
--   Dua registry API pihak ketiga memakai pola gerbang yang sama: cek nonce
--   sudah pernah dipakai, lalu simpan nonce baru. Tetapi tabel penyimpannya
--   TIDAK setara. Dibaca dari katalog Web_HRIS (development) 10 September 2026:
--
--     N_HRIS_Api_Nonce   PK + UQ_N_HRIS_Api_Nonce (Nonce)  UNIK
--                        + IX_N_HRIS_Api_Nonce_Expired (Expired_At)
--                        + IX_N_HRIS_Api_Nonce_Aplikasi (Id_Api_Aplikasi, Expired_At)
--
--     N_LMS_Api_Nonces   PK saja. Tidak ada satu pun indeks pada Nonce.
--
--   Padahal justru N_LMS_Api_Nonces-lah yang dipakai lalu lintas nyata: seluruh
--   callback HCLEARN masuk lewat LmsApiGuard. Tiga akibatnya:
--
--   1. ANTI-REPLAY-NYA BISA DIBALAP. Gerbang memeriksa dengan SELECT lalu
--      menyisipkan dengan INSERT terpisah. Tanpa UNIQUE, dua permintaan
--      bernonce sama yang datang bersamaan sama-sama melihat "belum ada" lalu
--      sama-sama tersimpan — keduanya diproses. Di N_HRIS hal itu mustahil
--      karena database menolak INSERT keduanya.
--
--   2. PEMERIKSAANNYA MEMINDAI SELURUH TABEL, dan makin lama makin lambat
--      karena tabel ini hanya bertambah di antara dua kali prune.
--
--   3. PEMBERSIHNYA JUGA MEMINDAI SELURUH TABEL. hris:api-nonce-prune
--      menghapus WHERE Expired_At < now setiap jam; tanpa indeks pada
--      Expired_At ia harus membaca semuanya untuk menemukan yang kedaluwarsa.
--
--   Sekaligus: N_LMS_Api_Logs tidak punya CHECK pada Signature_Valid, sedangkan
--   N_HRIS_Api_Log punya CK_N_HRIS_Api_Log_Sig. Nilai salah ketik akan tersimpan
--   diam-diam di satu registry dan ditolak di registry lain — untuk kolom yang
--   dipakai sebagai bukti keabsahan, itu perbedaan yang tidak boleh dibiarkan.
--
-- SIFAT
--   Idempoten. Aman dijalankan berulang.
--   Tidak ada DROP, tidak ada DELETE, tidak ada perubahan data.
--
-- CATATAN PENTING TENTANG UNIQUE
--   Indeks UNIQUE hanya dibuat bila data yang ada memang sudah memenuhi syarat
--   (tidak ada Nonce kembar dan tidak ada Nonce NULL). Bila belum, skrip TIDAK
--   gagal: ia membuat indeks biasa supaya manfaat kecepatan tetap didapat, lalu
--   mencetak peringatan berisi jumlah pelanggarnya. Nonce kedaluwarsa boleh
--   dihapus lebih dulu dengan `php artisan hris:api-nonce-prune`, setelah itu
--   skrip ini dijalankan ulang untuk menaikkannya menjadi UNIQUE.
--
--   Alasan tidak menghapus sendiri: pembersihan data bukan pekerjaan berkas DDL,
--   dan sudah ada perintah terjadwal yang memang bertugas untuk itu.
/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 1 — Indeks Expired_At: pembersih berhenti memindai seluruh tabel
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Nonces', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
        WHERE name = N'IX_N_LMS_Api_Nonces_Expired'
          AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   )
BEGIN
    CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Nonces_Expired]
        ON dbo.N_LMS_Api_Nonces (Expired_At);

    PRINT 'IX_N_LMS_Api_Nonces_Expired dibuat.';
END
ELSE
BEGIN
    PRINT 'IX_N_LMS_Api_Nonces_Expired sudah ada / tabel tidak ada — dilewati.';
END
GO

/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 2 — Nonce: UNIQUE bila memungkinkan, indeks biasa bila belum
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Nonces', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.indexes
        WHERE name IN (N'UQ_N_LMS_Api_Nonces_Nonce', N'IX_N_LMS_Api_Nonces_Nonce')
          AND object_id = OBJECT_ID('dbo.N_LMS_Api_Nonces')
   )
BEGIN
    DECLARE @kembar int = (
        SELECT COUNT(*) FROM (
            SELECT Nonce FROM dbo.N_LMS_Api_Nonces
             WHERE Nonce IS NOT NULL
             GROUP BY Nonce HAVING COUNT(*) > 1
        ) d
    );
    DECLARE @kosong int = (SELECT COUNT(*) FROM dbo.N_LMS_Api_Nonces WHERE Nonce IS NULL);

    IF @kembar = 0 AND @kosong = 0
    BEGIN
        CREATE UNIQUE NONCLUSTERED INDEX [UQ_N_LMS_Api_Nonces_Nonce]
            ON dbo.N_LMS_Api_Nonces (Nonce);

        PRINT 'UQ_N_LMS_Api_Nonces_Nonce dibuat — anti-replay kini dijamin database.';
    END
    ELSE
    BEGIN
        CREATE NONCLUSTERED INDEX [IX_N_LMS_Api_Nonces_Nonce]
            ON dbo.N_LMS_Api_Nonces (Nonce);

        PRINT 'PERINGATAN: UNIQUE dilewati karena data belum memenuhi syarat.';
        PRINT '  nonce kembar : ' + CAST(@kembar AS varchar(20));
        PRINT '  nonce NULL   : ' + CAST(@kosong AS varchar(20));
        PRINT '  Dibuat indeks biasa IX_N_LMS_Api_Nonces_Nonce sebagai gantinya.';
        PRINT '  Jalankan `php artisan hris:api-nonce-prune` lalu jalankan ulang skrip ini.';
    END
END
ELSE
BEGIN
    PRINT 'Indeks Nonce sudah ada / tabel tidak ada — dilewati.';
END
GO

/* ─────────────────────────────────────────────────────────────────────────────
   BAGIAN 3 — CHECK Signature_Valid pada N_LMS_Api_Logs, setara CK_N_HRIS_Api_Log_Sig
   ───────────────────────────────────────────────────────────────────────────*/
IF OBJECT_ID('dbo.N_LMS_Api_Logs', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1 FROM sys.check_constraints
        WHERE name = N'CK_N_LMS_Api_Logs_Sig'
          AND parent_object_id = OBJECT_ID('dbo.N_LMS_Api_Logs')
   )
BEGIN
    DECLARE @langgar int = (
        SELECT COUNT(*) FROM dbo.N_LMS_Api_Logs
         WHERE Signature_Valid IS NOT NULL
           AND Signature_Valid NOT IN ('Y', 'T')
    );

    IF @langgar = 0
    BEGIN
        ALTER TABLE dbo.N_LMS_Api_Logs
            ADD CONSTRAINT [CK_N_LMS_Api_Logs_Sig]
            CHECK (Signature_Valid IS NULL OR Signature_Valid IN ('Y', 'T'));

        PRINT 'CK_N_LMS_Api_Logs_Sig dibuat.';
    END
    ELSE
    BEGIN
        -- Sengaja TIDAK memakai WITH NOCHECK: baris lama yang melanggar berarti
        -- ada penulis yang menyimpan nilai di luar kontrak, dan itu harus
        -- ditemukan lebih dulu, bukan disembunyikan di belakang constraint.
        PRINT 'PERINGATAN: CK_N_LMS_Api_Logs_Sig dilewati, ada '
              + CAST(@langgar AS varchar(20))
              + ' baris dengan Signature_Valid di luar {Y,T,NULL}.';
    END
END
ELSE
BEGIN
    PRINT 'CK_N_LMS_Api_Logs_Sig sudah ada / tabel tidak ada — dilewati.';
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

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Matriks_Tamu_Role_Kategori' AND object_id = OBJECT_ID('N_VISITOR_Matriks_Tamu'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Matriks_Tamu_Role_Kategori
        ON N_VISITOR_Matriks_Tamu (Role_Id, Kategori_Tamu);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Master_Shift_Inspeksi_Nama' AND object_id = OBJECT_ID('N_VISITOR_Master_Shift_Inspeksi'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Master_Shift_Inspeksi_Nama
        ON N_VISITOR_Master_Shift_Inspeksi (Nama_Shift);
END
GO

-- Dua pos bernama sama membuat pemohon tidak tahu harus ke mana.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Master_Pos_Inspeksi_Nama' AND object_id = OBJECT_ID('N_VISITOR_Master_Pos_Inspeksi'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Master_Pos_Inspeksi_Nama
        ON N_VISITOR_Master_Pos_Inspeksi (Nama_Pos);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Pengecualian_Tanggal' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Pengecualian'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Pengecualian_Tanggal
        ON N_VISITOR_Inspeksi_Pengecualian (Tanggal) INCLUDE (Id_Pos_Inspeksi, Jenis, Flag_Aktif);
END
GO

-- Satu booking aktif per permit.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Inspeksi_Booking_Aktif' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Inspeksi_Booking_Aktif
        ON N_VISITOR_Inspeksi_Booking (No_Transaksi_Permit)
        WHERE Status = 'TERJADWAL';
END
GO

-- Hitungan kuota per slot (pos + jam) dan agenda per rentang waktu.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Booking_Slot' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Booking_Slot
        ON N_VISITOR_Inspeksi_Booking (Waktu_Mulai, Id_Pos_Inspeksi, Status)
        INCLUDE (No_Transaksi_Permit, Waktu_Selesai);
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_VISITOR_Inspeksi_Booking_Permit' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Booking'))
BEGIN
    CREATE INDEX IX_VISITOR_Inspeksi_Booking_Permit
        ON N_VISITOR_Inspeksi_Booking (No_Transaksi_Permit, Status);
END
GO

-- Rujukan ke master pos. Blok terpisah dari CREATE TABLE supaya tetap
-- terpasang pada tabel yang sudah ada.
IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pola_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pola ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pola_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pengecualian_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pengecualian ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pengecualian_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Booking_Pos', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Booking ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Booking_Pos
        FOREIGN KEY (Id_Pos_Inspeksi) REFERENCES N_VISITOR_Master_Pos_Inspeksi (Id_Pos_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pola_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pola ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pola_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Pengecualian_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Pengecualian ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Pengecualian_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Booking_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Booking ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Booking_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

-- Satu sakelar per (hari, shift); NULL dianggap sama oleh indeks unik SQL
-- Server, jadi sakelar "seluruh hari" pun hanya satu per hari.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Inspeksi_Hari_Shift' AND object_id = OBJECT_ID('N_VISITOR_Inspeksi_Hari_Shift'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Inspeksi_Hari_Shift
        ON N_VISITOR_Inspeksi_Hari_Shift (Hari_Minggu, Id_Shift_Inspeksi);
END
GO

IF OBJECT_ID('FK_N_VISITOR_Inspeksi_Hari_Shift_Shift', 'F') IS NULL
BEGIN
    ALTER TABLE N_VISITOR_Inspeksi_Hari_Shift ADD CONSTRAINT FK_N_VISITOR_Inspeksi_Hari_Shift_Shift
        FOREIGN KEY (Id_Shift_Inspeksi) REFERENCES N_VISITOR_Master_Shift_Inspeksi (Id_Shift_Inspeksi);
END
GO

-- Unik, tersaring NULL: dua kartu tidak boleh berbagi kode, sementara permit
-- tanpa kode tetap banyak. Indeks unik biasa akan menolak NULL kedua.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_VISITOR_Permit_Kode_Kartu' AND object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit'))
BEGIN
    CREATE UNIQUE INDEX UX_VISITOR_Permit_Kode_Kartu
        ON N_EMI_VISITOR_Transaksi_Permit (Kode_Kartu)
        WHERE Kode_Kartu IS NOT NULL;
END
GO


/* ==========================================================================
   VERIFIKASI INDEKS — jalankan SESUDAH berkas ini selesai.
   Harus mengembalikan NOL baris. Tabel yang belum ada ikut dilaporkan:
   indeksnya tak mungkin terpasang bila strukturnya belum naik.
   ========================================================================== */
SELECT CASE WHEN OBJECT_ID('dbo.'+tabel, 'U') IS NULL
            THEN 'TABELNYA BELUM ADA' ELSE 'INDEKS BELUM ADA' END AS keadaan,
       tabel+'.'+indeks AS objek
FROM (VALUES
    ('N_SOP_Dokumen','IX_SOP_Dokumen_Jenis'),
    ('N_SOP_Dokumen','IX_SOP_Dokumen_Pemilik'),
    ('N_SOP_Dokumen','IX_SOP_Dokumen_Entitas'),
    ('N_SOP_Dokumen','IX_SOP_Dokumen_Lingkup'),
    ('N_SOP_Dokumen','IX_SOP_Dokumen_APQC'),
    ('N_SOP_Dokumen','IX_SOP_Dokumen_Departemen'),
    ('N_SOP_Dokumen','IX_N_SOP_Dokumen_Nonaktif_At'),
    ('N_SOP_Dokumen','IX_N_SOP_Dokumen_Tipe'),
    ('N_SOP_Assignment_Rule','UQ_N_SOP_Assignment_Rule_Paket'),
    ('N_HRIS_Role_Menu_Access','UQ_NHRIS_RMA_PageAksi_Aktif'),
    ('Transaksi_Cuti_Det','IX_Transaksi_Cuti_Det_NoTransaksi'),
    ('Transaksi_Cuti_Det','IX_Transaksi_Cuti_Det_Karyawan_Tanggal'),
    ('N_HRIS_Transaksi_Shift_Det','IX_N_HRIS_Transaksi_Shift_Det_Induk'),
    ('N_HRIS_Transaksi_Shift_Det','IX_N_HRIS_Transaksi_Shift_Det_NoTransaksi'),
    ('HRIS_Page_Access','IX_HRIS_Page_Access_User_Aktif'),
    ('HRIS_Page_Access','IX_HRIS_Page_Access_Halaman_Aktif'),
    ('N_HRIS_Grup_Fitur_Map','IX_N_HRIS_Grup_Fitur_Map_Grup_Aktif'),
    ('N_HRIS_Grup_Fitur_Map','IX_N_HRIS_Grup_Fitur_Map_Halaman_Aktif'),
    ('HRIS_Karyawan_Team','IX_HRIS_Karyawan_Team_Karyawan'),
    ('HRIS_Karyawan_Team','IX_HRIS_Karyawan_Team_Tim'),
    ('N_HRIS_Request_Diagnostics','IX_N_HRIS_Request_Diagnostics_Created_Penutup'),
    ('N_HRIS_Request_Diagnostics','IX_N_HRIS_Request_Diagnostics_Id'),
    ('N_LMS_Api_Nonces','IX_N_LMS_Api_Nonces_Nonce'),
    ('N_EMI_VISITOR_Transaksi_Tamu','IX_VISITOR_Tamu_Api_Aplikasi'),
    ('N_EMI_VISITOR_Transaksi_Permit','UX_VISITOR_Permit_Transaksi_Tamu'),
    ('N_EMI_VISITOR_Transaksi_Permit','IX_VISITOR_Permit_Pemohon_Sumber_Tamu'),
    ('N_EMI_VISITOR_Transaksi_Permit','UX_VISITOR_Permit_Kode_Kartu'),
    ('N_VISITOR_Matriks_Tamu','UX_VISITOR_Matriks_Tamu_Role_Kategori'),
    ('N_VISITOR_Master_Shift_Inspeksi','UX_VISITOR_Master_Shift_Inspeksi_Nama'),
    ('N_VISITOR_Master_Pos_Inspeksi','UX_VISITOR_Master_Pos_Inspeksi_Nama'),
    ('N_VISITOR_Inspeksi_Pengecualian','IX_VISITOR_Inspeksi_Pengecualian_Tanggal'),
    ('N_VISITOR_Inspeksi_Booking','UX_VISITOR_Inspeksi_Booking_Aktif'),
    ('N_VISITOR_Inspeksi_Booking','IX_VISITOR_Inspeksi_Booking_Slot'),
    ('N_VISITOR_Inspeksi_Booking','IX_VISITOR_Inspeksi_Booking_Permit'),
    ('N_VISITOR_Inspeksi_Hari_Shift','UX_VISITOR_Inspeksi_Hari_Shift')
) AS i(tabel, indeks)
WHERE OBJECT_ID('dbo.'+tabel, 'U') IS NULL
   OR NOT EXISTS (SELECT 1 FROM sys.indexes
                   WHERE name = indeks AND object_id = OBJECT_ID('dbo.'+tabel));
GO