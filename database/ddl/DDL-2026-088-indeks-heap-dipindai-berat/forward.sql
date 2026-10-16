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

IF DB_NAME() NOT IN ('Web_HRIS')
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    SET NOEXEC ON;
END
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
