/* Change ID: DML-2026-060-seed-master-prioritas
   Target: Web_HRIS development only.
   Purpose: Mengisi N_SOP_Prioritas dengan tiga tingkat bawaan, supaya halaman
            master Prioritas Dokumen menampilkan baris yang selama ini sudah
            dipakai seluruh modul.

   KENAPA TABELNYA KOSONG
   ----------------------
   plans/sop/sql/35_master_prioritas.sql hanya jalan SEPARUH: CREATE TABLE-nya
   iya, tiga INSERT-nya tidak. Tak ada catatannya di database/ddl/status.json,
   dan kunci asing yang dijanjikan skrip itu juga tak pernah lahir.

   KENAPA TAK ADA YANG SADAR
   -------------------------
   Dua pembaca, hanya satu yang punya jalur mundur:

     SopPrioritasService::semua()       -> jatuh ke tiga tingkat BAWAAN di kode
                                           saat tabelnya kosong. Validasi,
                                           urutan papan risiko, dan warna
                                           lencana karena itu tetap bekerja.

     SopAdminPrioritasController::index() -> membaca tabelnya APA ADANYA, dan
                                           memang harus begitu: yang disunting
                                           admin adalah baris, bukan konstanta.

   Jadi seluruh modul tampak sehat sementara halaman masternya sendiri kosong.
   Penjaga tersedia() tak menolong — ia memeriksa KEBERADAAN tabel, bukan
   isinya, sehingga pesan "jalankan skrip 35 lebih dulu" tak pernah muncul.

   Halamannya sendiri baru terlihat orang sejak DML-2026-057 mendaftarkannya ke
   sidebar. Sebelum itu ia tak punya menu dan menumpang izin Kategori.

   PERUBAHAN INI NETRAL SECARA PERILAKU
   ------------------------------------
   Nilainya SENGAJA identik dengan SopPrioritasService::BAWAAN — kode, nama,
   urutan, dan warna. Sebelum diisi, semua() mengembalikan ketiganya dari kode;
   sesudah diisi, ia mengembalikan ketiganya dari tabel. Tak ada urutan yang
   bergeser dan tak ada warna yang berubah. Yang berubah hanya satu: ketiganya
   jadi bisa disunting admin, dan tingkat baru bisa ditambahkan.

   Angka Urutan 10/20/30 bukan pilihan bebas: ia menghasilkan urutan yang sama
   persis dengan CASE lama ('KRITIS'->0, 'TINGGI'->1, sisanya->2).

   TIGA INSERT TERPISAH, BUKAN SATU MERGE — meniru skrip 35 yang asli: MERGE
   yang gagal sebagian tak menyisakan jejak baris mana yang tak jadi.

   Lingkup: 3 baris pada N_SOP_Prioritas. AMAN DIJALANKAN BERULANG.
*/

SET XACT_ABORT ON;
BEGIN TRANSACTION;

INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'KRITIS', 'Sangat Penting', 10, '#EF4444', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'KRITIS');

INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'TINGGI', 'Tinggi', 20, '#F59E0B', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'TINGGI');

INSERT INTO dbo.N_SOP_Prioritas (Kode, Nama, Urutan, Warna, Flag_Aktif, Created_At)
SELECT 'NORMAL', 'Normal', 30, '#94A3B8', 'Y', GETDATE()
 WHERE NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas WHERE Kode = 'NORMAL');

COMMIT TRANSACTION;
GO

/* ── Verifikasi ────────────────────────────────────────────────────────
   Tiga tingkat itu WAJIB ada. Kalau tidak, halaman masternya tetap kosong
   dan cacat yang sedang diperbaiki masih berdiri. */
DECLARE @n INT = (
    SELECT COUNT(*) FROM dbo.N_SOP_Prioritas
     WHERE Flag_Aktif = 'Y' AND Kode IN ('KRITIS', 'TINGGI', 'NORMAL')
);

IF @n <> 3
    THROW 50060, 'N_SOP_Prioritas tidak berisi tepat tiga tingkat bawaan yang aktif. Periksa galat INSERT di atas.', 1;

/* Paket yang memakai kode di luar master akan tampil tanpa lencana dan
   mendarat di urutan "tak dikenal". Dilaporkan, bukan diperbaiki diam-diam. */
DECLARE @yatim INT = (
    SELECT COUNT(*) FROM dbo.N_SOP_Master_Paket p
     WHERE p.Flag_Aktif = 'Y'
       AND p.Prioritas IS NOT NULL
       AND NOT EXISTS (SELECT 1 FROM dbo.N_SOP_Prioritas m WHERE m.Kode = p.Prioritas)
);

IF @yatim > 0
    PRINT 'PERHATIAN: '+CAST(@yatim AS VARCHAR(10))+' paket memakai kode prioritas yang tak ada di master.';

PRINT 'Master prioritas terisi tiga tingkat bawaan.';
GO

/* ── Daftarkan perubahan ───────────────────────────────────────────────── */
IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
     WHERE Kode_Change = 'DML-2026-060-seed-master-prioritas'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path, Target_Development,
        Status_Development, Status_SQL_Tracker, Status_Production,
        Status_Verification, Created_By
    )
    VALUES
    (
        'DML-2026-060-seed-master-prioritas',
        'DML',
        'Isi master Prioritas Dokumen dengan tiga tingkat bawaan',
        'N_SOP_Prioritas lahir kosong karena plans/sop/sql/35_master_prioritas.sql hanya jalan separuh. Halaman masternya kosong sementara sisa modul memakai tiga tingkat bawaan dari kode lewat jalur mundur SopPrioritasService::semua(). Nilainya identik dengan BAWAAN sehingga pengisian ini netral secara perilaku; yang berubah hanya ketiganya jadi bisa disunting.',
        'database/dml/DML-2026-060-seed-master-prioritas/data-change.sql',
        'docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'sop-master-prioritas'
    );
END;
GO
