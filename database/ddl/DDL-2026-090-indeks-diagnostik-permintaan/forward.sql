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
IF DB_NAME() NOT IN ('Web_HRIS', 'Web_HRIS_Cut_Off')
BEGIN
    RAISERROR('DDL-2026-090 dijalankan pada basis data yang salah.', 16, 1);
    SET NOEXEC ON;
END;
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
