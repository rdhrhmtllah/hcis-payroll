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

ALTER TABLE N_SOP_Dokumen ALTER COLUMN Id_Versi_Aktif INT NULL;
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
