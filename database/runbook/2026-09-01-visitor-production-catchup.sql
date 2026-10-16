/*
================================================================================
  RUNBOOK EKSEKUSI SEKALI — Modul VISITOR, database production
================================================================================
  Disusun          : 2026-09-01
  Target           : emi_db_web_hr  (SQL Server 16.0.4252.3, Express Edition)
  Dasar            : audit skema VISITOR development vs production (read-only)
  Dijalankan oleh  : user, melalui SQL Tracker. BUKAN oleh agent.

  Skrip ini menggabungkan tiga change agar cukup satu kali eksekusi:

    Blok A  DDL-2026-020-visitor-lifecycle-idempotency    (TRACKER_PENDING)
    Blok B  DDL-2026-023-add-visitor-master-soft-status   (TRACKER_PENDING)
    Blok C  DDL-2026-061-index-approval-role-lookup       (BARU)

  Pernyataan di Blok A dan B disalin VERBATIM dari forward.sql masing-masing
  change; tidak ada yang diubah, hanya digabung. Catatan SQL Tracker tetap
  harus merujuk ke tiga change ID di atas secara terpisah.

--------------------------------------------------------------------------------
  SIFAT SKRIP
--------------------------------------------------------------------------------
  - Idempoten seluruhnya. Aman dijalankan ulang.
  - Tidak ada DROP, tidak ada TRUNCATE, tidak ada UPDATE/DELETE.
  - Satu-satunya penulisan data adalah pengisian otomatis kolom Flag_Aktif
    NOT NULL DEFAULT ('Y') pada baris yang sudah ada (WITH VALUES).

--------------------------------------------------------------------------------
  DAMPAK TERUKUR DI PRODUCTION (dihitung 2026-09-01)
--------------------------------------------------------------------------------
  N_EMI_VISITOR_Master_Purpose            0 baris  -> tidak ada baris diisi
  N_EMI_VISITOR_Master_Vehicle_Color      0 baris  -> tidak ada baris diisi
  N_EMI_VISITOR_Transaksi_Permit          0 baris  -> index unik pasti bersih
  N_EMI_VISITOR_Transaksi_Tamu            0 baris  -> index unik pasti bersih
  HRIS_Approval_Request              35.968 baris  -> satu build index offline
  N_HRIS_Role_User                       25 baris  -> seketika

  Tabel VISITOR yang tersentuh semuanya kosong, jadi tidak ada risiko
  pelanggaran unik maupun pengisian massal. Beban nyata hanya pada
  HRIS_Approval_Request, dan 35.968 baris itu hitungan detik.

--------------------------------------------------------------------------------
  EDISI SERVER
--------------------------------------------------------------------------------
  Production berjalan di Express Edition. WITH (ONLINE = ON) TIDAK didukung di
  edisi ini, jadi semua CREATE INDEX di sini sengaja offline. Tabel akan
  terkunci singkat selama build. Jalankan di luar jam sibuk bila memungkinkan.

--------------------------------------------------------------------------------
  URUTAN YANG TIDAK BOLEH DIBALIK
--------------------------------------------------------------------------------
  Blok B WAJIB jalan sebelum DML-2026-045-bootstrap-visitor-master-data.
  INSERT di DML tersebut menyebut kolom Flag_Aktif secara eksplisit
  (baris 235 dan 289), sehingga akan gagal bila kolomnya belum ada.

--------------------------------------------------------------------------------
  DI LUAR CAKUPAN SKRIP INI
--------------------------------------------------------------------------------
  Skrip ini hanya memperbaiki SKEMA. Master data Visitor di production masih
  kosong (Vehicle_Type, Vehicle_Model, Vehicle_Color, Purpose, Durasi_Permit,
  Plat_Kendaraan, Approval_Question semuanya 0 baris). Modul belum akan
  berfungsi sampai DML-2026-045 juga dijalankan. Lihat catatan di akhir berkas.
================================================================================
*/


/* ============================================================================
   PRA-PERIKSA — pastikan target benar, lalu rekam keadaan awal
   ============================================================================ */

SELECT
    DB_NAME()                                        AS database_aktif,
    @@SERVERNAME                                     AS server,
    CONVERT(varchar(40), SERVERPROPERTY('Edition'))  AS edisi,
    GETDATE()                                        AS waktu_mulai;
GO

SELECT jenis, objek, status FROM (
    SELECT 1 AS urut, 'kolom' AS jenis, 'N_EMI_VISITOR_Master_Purpose.Flag_Aktif' AS objek,
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NULL THEN 'BELUM' ELSE 'ADA' END AS status
    UNION ALL SELECT 2, 'kolom', 'N_EMI_VISITOR_Master_Vehicle_Color.Flag_Aktif',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NULL THEN 'BELUM' ELSE 'ADA' END
    UNION ALL SELECT 3, 'kolom', 'N_EMI_VISITOR_Transaksi_Permit.Idempotency_Key',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Idempotency_Key') IS NULL THEN 'BELUM' ELSE 'ADA' END
    UNION ALL SELECT 4, 'kolom', 'N_EMI_VISITOR_Transaksi_Tamu.Idempotency_Key',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Idempotency_Key') IS NULL THEN 'BELUM' ELSE 'ADA' END
    UNION ALL SELECT 5, 'index', 'UX_VISITOR_Permit_Requester_Idempotency',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit') AND name = 'UX_VISITOR_Permit_Requester_Idempotency') THEN 'ADA' ELSE 'BELUM' END
    UNION ALL SELECT 6, 'index', 'UX_VISITOR_Tamu_Requester_Idempotency',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu') AND name = 'UX_VISITOR_Tamu_Requester_Idempotency') THEN 'ADA' ELSE 'BELUM' END
    UNION ALL SELECT 7, 'index', 'IX_Approval_Request_Trans_Order',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('HRIS_Approval_Request') AND name = 'IX_Approval_Request_Trans_Order') THEN 'ADA' ELSE 'BELUM' END
    UNION ALL SELECT 8, 'index', 'IX_RoleUser_UserId',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Role_User') AND name = 'IX_RoleUser_UserId') THEN 'ADA' ELSE 'BELUM' END
) AS keadaan_awal
ORDER BY urut;
GO


/* ============================================================================
   BLOK A — DDL-2026-020-visitor-lifecycle-idempotency
   Kunci idempotency pengajuan permit dan tamu.

   Catatan: dua pernyataan Waktu_Aktual_* di bawah sudah terpenuhi di
   production dan akan dilewati sendiri oleh penjaganya. Sengaja disertakan
   agar isi blok ini identik dengan forward.sql aslinya.
   ============================================================================ */

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Aktual_Masuk') IS NULL
ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Aktual_Masuk datetime2 NULL;
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Waktu_Aktual_Keluar') IS NULL
ALTER TABLE N_VISITOR_Tamu_Efektif ADD Waktu_Aktual_Keluar datetime2 NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Idempotency_Key') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Permit ADD Idempotency_Key varchar(100) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit') AND name = 'UX_VISITOR_Permit_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Permit_Requester_Idempotency
ON N_EMI_VISITOR_Transaksi_Permit (Kode_Karyawan_Pemohon, Idempotency_Key)
WHERE Idempotency_Key IS NOT NULL;
GO

IF COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Idempotency_Key') IS NULL
ALTER TABLE N_EMI_VISITOR_Transaksi_Tamu ADD Idempotency_Key varchar(100) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu') AND name = 'UX_VISITOR_Tamu_Requester_Idempotency')
CREATE UNIQUE INDEX UX_VISITOR_Tamu_Requester_Idempotency
ON N_EMI_VISITOR_Transaksi_Tamu (Kode_Karyawan_PIC, Idempotency_Key)
WHERE Idempotency_Key IS NOT NULL;
GO


/* ============================================================================
   BLOK B — DDL-2026-023-add-visitor-master-soft-status
   Status aktif/nonaktif untuk master Warna Kendaraan dan Keperluan.
   WAJIB jalan sebelum DML-2026-045.
   ============================================================================ */

IF OBJECT_ID('N_EMI_VISITOR_Master_Vehicle_Color', 'U') IS NULL
    THROW 50001, 'Table N_EMI_VISITOR_Master_Vehicle_Color was not found.', 1;
GO

IF COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Master_Vehicle_Color
        ADD Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_VISITOR_Master_Vehicle_Color_Flag_Aktif DEFAULT ('Y') WITH VALUES;
END;
GO

IF OBJECT_ID('N_EMI_VISITOR_Master_Purpose', 'U') IS NULL
    THROW 50002, 'Table N_EMI_VISITOR_Master_Purpose was not found.', 1;
GO

IF COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Master_Purpose
        ADD Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_VISITOR_Master_Purpose_Flag_Aktif DEFAULT ('Y') WITH VALUES;
END;
GO


/* ============================================================================
   BLOK C — DDL-2026-061-index-approval-role-lookup
   Dua index pencarian yang sudah lama ada di development tetapi tidak pernah
   tercatat sebagai change, sehingga tidak pernah terbawa ke production.
   Bukan penyebab error; ini soal kecepatan kueri.
   ============================================================================ */

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('HRIS_Approval_Request')
      AND name = 'IX_Approval_Request_Trans_Order'
)
CREATE NONCLUSTERED INDEX IX_Approval_Request_Trans_Order
ON HRIS_Approval_Request (No_Transaksi, Request_Order_Flow);
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_HRIS_Role_User')
      AND name = 'IX_RoleUser_UserId'
)
CREATE NONCLUSTERED INDEX IX_RoleUser_UserId
ON N_HRIS_Role_User (UserId_Web);
GO


/* ============================================================================
   PASCA-PERIKSA — kedelapan baris harus berstatus ADA
   ============================================================================ */

SELECT jenis, objek, status FROM (
    SELECT 1 AS urut, 'kolom' AS jenis, 'N_EMI_VISITOR_Master_Purpose.Flag_Aktif' AS objek,
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Master_Purpose', 'Flag_Aktif') IS NULL THEN 'GAGAL' ELSE 'ADA' END AS status
    UNION ALL SELECT 2, 'kolom', 'N_EMI_VISITOR_Master_Vehicle_Color.Flag_Aktif',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Master_Vehicle_Color', 'Flag_Aktif') IS NULL THEN 'GAGAL' ELSE 'ADA' END
    UNION ALL SELECT 3, 'kolom', 'N_EMI_VISITOR_Transaksi_Permit.Idempotency_Key',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Transaksi_Permit', 'Idempotency_Key') IS NULL THEN 'GAGAL' ELSE 'ADA' END
    UNION ALL SELECT 4, 'kolom', 'N_EMI_VISITOR_Transaksi_Tamu.Idempotency_Key',
           CASE WHEN COL_LENGTH('N_EMI_VISITOR_Transaksi_Tamu', 'Idempotency_Key') IS NULL THEN 'GAGAL' ELSE 'ADA' END
    UNION ALL SELECT 5, 'index', 'UX_VISITOR_Permit_Requester_Idempotency',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Permit') AND name = 'UX_VISITOR_Permit_Requester_Idempotency') THEN 'ADA' ELSE 'GAGAL' END
    UNION ALL SELECT 6, 'index', 'UX_VISITOR_Tamu_Requester_Idempotency',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_EMI_VISITOR_Transaksi_Tamu') AND name = 'UX_VISITOR_Tamu_Requester_Idempotency') THEN 'ADA' ELSE 'GAGAL' END
    UNION ALL SELECT 7, 'index', 'IX_Approval_Request_Trans_Order',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('HRIS_Approval_Request') AND name = 'IX_Approval_Request_Trans_Order') THEN 'ADA' ELSE 'GAGAL' END
    UNION ALL SELECT 8, 'index', 'IX_RoleUser_UserId',
           CASE WHEN EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_HRIS_Role_User') AND name = 'IX_RoleUser_UserId') THEN 'ADA' ELSE 'GAGAL' END
) AS keadaan_akhir
ORDER BY urut;
GO

-- Nilai default terpasang benar? Kedua baris harus mengembalikan ('Y').
SELECT t.name AS tabel, c.name AS kolom, dc.name AS nama_default, dc.definition AS nilai
FROM sys.default_constraints dc
JOIN sys.columns c ON c.default_object_id = dc.object_id
JOIN sys.tables  t ON t.object_id = c.object_id
WHERE t.name IN ('N_EMI_VISITOR_Master_Purpose', 'N_EMI_VISITOR_Master_Vehicle_Color')
  AND c.name = 'Flag_Aktif';
GO


/* ============================================================================
   LANGKAH BERIKUTNYA — belum termasuk skrip ini
   ============================================================================

   Skema sudah setara setelah skrip ini, tetapi modul Visitor masih belum bisa
   dipakai di production karena master datanya kosong:

       N_EMI_VISITOR_Master_Vehicle_Type          0 baris   (dev: 5)
       N_EMI_VISITOR_Master_Vehicle_Model         0 baris   (dev: 111)
       N_EMI_VISITOR_Master_Vehicle_Color         0 baris   (dev: 10)
       N_EMI_VISITOR_Master_Purpose               0 baris   (dev: 6)
       N_EMI_VISITOR_Master_Durasi_Permit         0 baris   (dev: 3)
       N_EMI_VISITOR_Master_Plat_Kendaraan        0 baris   (dev: 15)
       N_EMI_VISITOR_Master_Approval_Question     0 baris   (dev: 15)
       N_EMI_VISITOR_Master_Kategori_Permit       2 baris   (dev: 4)

   DML-2026-045-bootstrap-visitor-master-data mengisi enam dari delapan tabel
   itu. Approval_Question dan Kategori_Permit belum tercakup di mana pun.
   Jalankan DML tersebut SETELAH skrip ini, bukan sebelumnya.
============================================================================ */
