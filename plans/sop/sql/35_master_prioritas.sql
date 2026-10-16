/* =====================================================================
   35 — Prioritas Paket SOP jadi MASTER
   =====================================================================

   MASALAH YANG DITUTUP
   --------------------
   Tingkat prioritas paket terkunci pada tiga nilai di LIMA lapis sekaligus:
   CHECK constraint di sini, konstanta PHP, aturan validasi `in:`, konstanta
   label/warna di layar, dan kata "KRITIS" yang ditulis langsung pada chip
   filter, ikon api, serta preset ekspor. Admin tak punya cara menambah
   tingkat, dan setiap penambahan menuntut rilis kode.

   SATU KOLOM YANG MEMBUAT INI BUKAN SEKADAR DAFTAR NAMA
   ----------------------------------------------------
   Urutan — prioritas PUNYA TINGKATAN, berbeda dari Kategori. Papan risiko dan
   pengurutan daftar dulu memakai CASE bernama ('KRITIS'→0, 'TINGGI'→1,
   sisanya→2), sehingga tingkat baru apa pun mendarat di kelompok "sisanya",
   yaitu DI BAWAH Normal. Diberi jarak 10 supaya tingkat baru bisa disisipkan di
   antaranya tanpa menomori ulang semuanya. KECIL = PALING MENDESAK.

   Metrik "SOP kritis belum selesai" di monitoring tetap mencocokkan kode
   'KRITIS' apa adanya — tingkat buatan admin di atasnya tidak ikut terhitung.
   Itu keputusan sadar; lihat skrip 38.

   KODE PAKET TIDAK DIUBAH JADI ID
   -------------------------------
   N_SOP_Master_Paket.Prioritas tetap menyimpan KODE-nya, hanya ditambah kunci
   asing. Menggantinya jadi Id_Prioritas akan memaksa puluhan kueri, filter,
   dan ekspor yang membaca kolom itu ikut disentuh tanpa mendapat apa pun.

   AMAN DIJALANKAN BERULANG.
   ===================================================================== */

/* ── 1. Tabel master ────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'N_SOP_Prioritas')
BEGIN
    CREATE TABLE dbo.N_SOP_Prioritas (
        Id_SOP_Prioritas BIGINT IDENTITY(1,1) NOT NULL,
        Kode             VARCHAR(30)  NOT NULL,
        Nama             VARCHAR(60)  NOT NULL,
        /* Kecil = paling mendesak. Berjarak 10 agar bisa disisipi. */
        Urutan           INT          NOT NULL CONSTRAINT DF_N_SOP_Prioritas_Urutan DEFAULT (100),
        /* HEX 6 digit berawalan '#', mis. '#EF4444'. Bukan nama ragam: daftar
           ragam hanya memuat lima warna yang ditulis lebih dulu di kode, dan itu
           batas yang tak ada gunanya bagi master data. Latar & garis lencananya
           diturunkan dari hex ini lewat alfa, jadi admin memilih SATU warna. */
        Warna            VARCHAR(20)  NOT NULL CONSTRAINT DF_N_SOP_Prioritas_Warna DEFAULT ('#94A3B8'),
        Flag_Aktif       CHAR(1)      NOT NULL CONSTRAINT DF_N_SOP_Prioritas_Aktif DEFAULT ('Y'),
        Created_By       BIGINT       NULL,
        Created_At       DATETIME     NULL,
        Updated_By       BIGINT       NULL,
        Updated_At       DATETIME     NULL,
        CONSTRAINT PK_N_SOP_Prioritas PRIMARY KEY CLUSTERED (Id_SOP_Prioritas),
        /* WAJIB UNIQUE: kunci asing dari paket menunjuk ke kolom ini. */
        CONSTRAINT UQ_N_SOP_Prioritas_Kode UNIQUE (Kode),
        CONSTRAINT CK_N_SOP_Prioritas_Aktif  CHECK (Flag_Aktif  IN ('Y','T'))
    );

    PRINT 'Tabel N_SOP_Prioritas dibuat.';
END
ELSE
    PRINT 'Tabel N_SOP_Prioritas sudah ada — dilewati.';
GO

/* Pengurutan daftar master & pembentukan CASE urutan. */
IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.N_SOP_Prioritas') AND name = 'IX_N_SOP_Prioritas_Urutan'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_N_SOP_Prioritas_Urutan
        ON dbo.N_SOP_Prioritas (Flag_Aktif, Urutan) INCLUDE (Kode, Nama, Warna);

    PRINT 'IX_N_SOP_Prioritas_Urutan dibuat.';
END
GO

/* ── 2. Isi tiga tingkat yang sudah berlaku ─────────────────────────────
   Nilai Urutan-nya DIPILIH agar perilakunya persis sama dengan yang sekarang:
   KRITIS paling atas. Kalau angkanya digeser di sini, papan risiko berubah
   urutan pada hari yang sama tanpa ada satu baris kode pun yang berubah.

   TIGA INSERT TERPISAH, BUKAN SATU MERGE. Versi pertama skrip ini memakai
   MERGE dan batch-nya GAGAL DIAM-DIAM di dev: tabelnya terbentuk, CHECK-nya
   jatuh, kolomnya melebar — tapi isinya kosong, dan kedua kunci asing di bawah
   ikut ditolak karena 15 paket menunjuk kode yang tak ada di master. Bentuk di
   bawah ini tak punya bagian yang bisa gagal sendirian. */
INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'KRITIS', 'Sangat Penting', 10, '#EF4444', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'KRITIS');
GO

INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'TINGGI', 'Tinggi', 20, '#F59E0B', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'TINGGI');
GO

INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'NORMAL', 'Normal', 30, '#94A3B8', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'NORMAL');
GO

/* Berhenti di sini kalau isinya masih kosong. Melanjutkan ke kunci asing hanya
   menghasilkan galat kedua yang menutupi galat pertama. */
DECLARE @jml INT = (SELECT COUNT(*) FROM dbo.N_SOP_Prioritas);

/* Angkanya diambil ke variabel dulu. PRINT hanya menerima ekspresi skalar —
   subquery yang ditempel langsung ke dalamnya ditolak dengan "Subqueries are
   not allowed in this context", dan batch verifikasinya gagal justru pada
   skrip yang tugasnya memverifikasi. */
IF @jml = 0
    RAISERROR ('N_SOP_Prioritas masih kosong sesudah pengisian. Jangan lanjutkan — periksa galat batch INSERT di atas.', 16, 1);
ELSE
    PRINT 'Isi master: '+CAST(@jml AS VARCHAR(10))+' tingkat.';
GO

/* ── 2a. Betulkan baris lama yang warnanya masih token ─────────────────
   INSERT di atas dijaga WHERE NOT EXISTS, jadi baris yang SUDAH ada tak akan
   tersentuh — termasuk baris yang ditanam versi skrip sebelumnya, waktu kolom
   ini masih menyimpan nama ragam ('bad'/'warn'/'idle') alih-alih hex.

   Tanpa blok ini, menjalankan ulang skrip yang sudah diperbarui TIDAK mengubah
   apa pun dan lencana prioritas kehilangan warnanya di seluruh layar, tanpa
   satu pun galat yang menjelaskan kenapa. Hanya baris yang belum berbentuk hex
   yang disentuh, jadi warna pilihan admin tak pernah ditimpa. */
UPDATE dbo.N_SOP_Prioritas SET Warna = '#EF4444' WHERE Kode = 'KRITIS' AND Warna NOT LIKE '#%';
UPDATE dbo.N_SOP_Prioritas SET Warna = '#F59E0B' WHERE Kode = 'TINGGI' AND Warna NOT LIKE '#%';
UPDATE dbo.N_SOP_Prioritas SET Warna = '#94A3B8' WHERE Kode = 'NORMAL' AND Warna NOT LIKE '#%';

/* Sisa tingkat buatan admin yang warnanya masih token: diberi netral, bukan
   ditebak. Menebak warna orang lain lebih buruk daripada mengakui tak tahu. */
UPDATE dbo.N_SOP_Prioritas SET Warna = '#94A3B8' WHERE Warna NOT LIKE '#%';
GO

/* ── 2b. Betulkan nilai bawaan kolom Warna ─────────────────────────────
   Hanya berlaku untuk database yang tabelnya SUDAH ada dari versi skrip
   sebelumnya, waktu kolom ini masih menyimpan nama ragam ('idle'). CREATE TABLE
   di atas dilewati pada database seperti itu, sehingga default lamanya tetap
   tinggal — dan satu INSERT langsung tanpa menyebut Warna akan menanam nilai
   yang tak lagi bisa dibaca layar mana pun. */
IF EXISTS (
    SELECT 1 FROM sys.default_constraints dc
      JOIN sys.columns c ON c.object_id = dc.parent_object_id AND c.column_id = dc.parent_column_id
     WHERE dc.name = 'DF_N_SOP_Prioritas_Warna' AND c.name = 'Warna'
       AND dc.definition NOT LIKE '%#%'
)
BEGIN
    ALTER TABLE dbo.N_SOP_Prioritas DROP CONSTRAINT DF_N_SOP_Prioritas_Warna;
    ALTER TABLE dbo.N_SOP_Prioritas ADD CONSTRAINT DF_N_SOP_Prioritas_Warna DEFAULT ('#94A3B8') FOR Warna;

    PRINT 'Nilai bawaan kolom Warna diganti ke hex.';
END
GO

/* ── 3. Lepaskan kunci lama ─────────────────────────────────────────────
   CHECK-nya harus dijatuhkan LEBIH DULU: SQL Server menolak ALTER COLUMN pada
   kolom yang masih dirujuk sebuah CHECK constraint, dengan galat yang menyebut
   nama constraint-nya tanpa menyebut apa yang harus dilakukan. */
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CK_N_SOP_Paket_Prioritas')
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket DROP CONSTRAINT CK_N_SOP_Paket_Prioritas;
    PRINT 'CK_N_SOP_Paket_Prioritas dijatuhkan — daftar nilainya kini hidup di master.';
END
ELSE
    PRINT 'CK_N_SOP_Paket_Prioritas sudah tidak ada — dilewati.';
GO

/* ── 4. Samakan lebar kolom ─────────────────────────────────────────────
   varchar(10) hanya memuat kode sependek 'KRITIS'. 'SANGAT_TINGGI' saja sudah
   13 karakter, dan penyimpanannya akan terpotong senyap atau ditolak. Draft
   overlay sudah varchar(30); paket utama disamakan ke sana. Kunci asing juga
   menuntut tipe & lebar yang sama persis. */
IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.N_SOP_Master_Paket') AND name = 'Prioritas' AND max_length < 30
)
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket ALTER COLUMN Prioritas VARCHAR(30) NOT NULL;
    PRINT 'N_SOP_Master_Paket.Prioritas dilebarkan ke varchar(30).';
END
ELSE
    PRINT 'N_SOP_Master_Paket.Prioritas sudah cukup lebar — dilewati.';
GO

/* ── 5. Kunci asing ─────────────────────────────────────────────────────
   Inilah yang membuat tingkat yang sedang dipakai tak bisa dihapus dari
   master — penjaga terakhir, di bawah penjaga aplikasi yang pesannya lebih
   ramah. NOCHECK sengaja TIDAK dipakai: kalau ada nilai nyasar, lebih baik
   ketahuan sekarang daripada saat kueri pertama yang menjoinnya. */
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Master_Paket_Prioritas')
BEGIN
    ALTER TABLE dbo.N_SOP_Master_Paket
        ADD CONSTRAINT FK_N_SOP_Master_Paket_Prioritas
        FOREIGN KEY (Prioritas) REFERENCES dbo.N_SOP_Prioritas (Kode);

    PRINT 'FK_N_SOP_Master_Paket_Prioritas dipasang.';
END
ELSE
    PRINT 'FK_N_SOP_Master_Paket_Prioritas sudah ada — dilewati.';
GO

IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_N_SOP_Paket_Draft_Prioritas')
BEGIN
    ALTER TABLE dbo.N_SOP_Paket_Draft
        ADD CONSTRAINT FK_N_SOP_Paket_Draft_Prioritas
        FOREIGN KEY (Prioritas) REFERENCES dbo.N_SOP_Prioritas (Kode);

    PRINT 'FK_N_SOP_Paket_Draft_Prioritas dipasang.';
END
ELSE
    PRINT 'FK_N_SOP_Paket_Draft_Prioritas sudah ada — dilewati.';
GO

/* CATATAN UNTUK YANG MENJALANKAN
   Sebelum skrip ini dijalankan, kode tetap bekerja seperti sekarang: seluruh
   pembacaan master dijaga Schema::hasTable('N_SOP_Prioritas') dan jatuh ke
   tiga tingkat bawaan bila tabelnya belum ada. Tak ada urutan wajib antara
   menjalankan skrip ini dan menerbitkan kodenya. */
