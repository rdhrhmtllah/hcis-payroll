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
IF DB_NAME() <> N'Web_HRIS'
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    RETURN;
END;

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
