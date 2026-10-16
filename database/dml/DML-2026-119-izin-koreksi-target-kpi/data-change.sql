/* =====================================================================
   DML-2026-119-izin-koreksi-target-kpi
   ---------------------------------------------------------------------
   Memberikan izin halaman 'kpiKoreksiTarget' kepada lima pemegang
   'kpiEnforcementMonitoring' yang aktif di production (emi_db_web_hr).

   Temuan sumber : docs/changes/DML-2026-119-izin-koreksi-target-kpi/README.md

   ---------------------------------------------------------------------
   KENAPA PERLU
   ---------------------------------------------------------------------
   Fitur "Perbaiki Target" pada Monitoring > KPI Submission sudah lengkap
   di kode sejak 3-9 September 2026 (KpiTargetKoreksiService,
   KpiTargetKoreksiController, routes/KPI/Monitoring/Enforcement/
   KpiKoreksiTarget.php, modal di kpiEnforcementDashboard.vue, tiga berkas
   uji), dan tabel jejaknya N_KPI_Target_Koreksi sudah ada di production.

   Tetapi kunci izinnya belum pernah diberikan kepada siapa pun. Diukur
   READ ONLY pada 16 September 2026:

     HRIS_Page_Access     0 baris untuk 'kpiKoreksiTarget'
     N_HRIS_Menus         0 baris
     N_HRIS_Grup_Fitur_Map 0 baris   <- ini memang disengaja, lihat di bawah
     N_KPI_Target_Koreksi 0 baris    <- fiturnya belum pernah dipakai

   Akibatnya /api/v1/kpi-enforcement/target/kelayakan menjawab 403 untuk
   semua orang, dan layar GAGAL-TERTUTUP tanpa menulis apa pun ke console
   (kpiEnforcementDashboard.vue). Tombolnya karena itu tidak pernah muncul
   dan tidak meninggalkan jejak: fitur yang ada terlihat persis seperti
   fitur yang tidak ada.

   ---------------------------------------------------------------------
   KENAPA LEWAT HRIS_Page_Access, BUKAN N_HRIS_Grup_Fitur_Map
   ---------------------------------------------------------------------
   Disengaja, dan alasannya tertulis di berkas rutenya. Satu role memberi
   SELURUH halaman dalam grup fiturnya sekaligus. Menaruh 'kpiKoreksiTarget'
   di grup KPI berarti memberikan kuasa mengubah angka penilaian kepada
   setiap pemegang role KPI mana pun -- padahal seluruh titik desain fitur
   ini adalah bahwa memantau TIDAK sama dengan boleh mengubah angka.

   Karena itu pemberiannya per orang. Jangan memindahkannya ke
   N_HRIS_Grup_Fitur_Map "supaya rapi": itu membatalkan pemisahannya.

   ---------------------------------------------------------------------
   SIAPA YANG MENERIMA (keputusan user, 16 September 2026: "semuanya")
   ---------------------------------------------------------------------
     285  RIDHO RAHMAT    RIDHO RAHMATULLAH
     367  ABDUL RACHMAN   ABDUL RACHMAN
     899  OKKY            OKKY ADMIRAL NUGRATAMA
    1093  QORY AZZAHRA    QORY AZZAHRA
    1836  DESTU SYAH      DESTU SYAH INANDA

   Daftarnya sengaja DITULIS EKSPLISIT, bukan diturunkan dari "siapa pun
   pemegang kpiEnforcementMonitoring". Kalau diturunkan, orang keenam yang
   diberi izin memantau besok akan ikut mendapat kuasa mengoreksi tanpa
   seorang pun memutuskannya.

   Kode_Perusahaan / ID_Level / ID_Divisi ketiganya NOT NULL dan
   menggambarkan ORANGNYA, bukan halamannya. Nilainya disalin dari baris
   akses milik orang itu sendiri yang paling baru, sehingga tidak ada angka
   yang ditebak di berkas ini.

   Aman dijalankan ulang: baris yang sudah ada dilewati.
   ===================================================================== */

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT '=== DML-2026-119 pada database: ' + DB_NAME() + ' ===';

BEGIN TRANSACTION;

;WITH penerima AS (
    SELECT * FROM (VALUES
        (285),
        (367),
        (899),
        (1093),
        (1836)
    ) AS t(UserID_Web)
),
sumber AS (
    SELECT
        a.UserID_Web,
        a.Kode_Perusahaan,
        a.ID_Level,
        a.ID_Divisi,
        ROW_NUMBER() OVER (PARTITION BY a.UserID_Web ORDER BY a.Id_Page_Access DESC) AS urut
    FROM dbo.HRIS_Page_Access AS a
    INNER JOIN penerima AS p ON p.UserID_Web = a.UserID_Web
)
INSERT INTO dbo.HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Created_At, Updated_At, Flag_Aktif)
SELECT
    s.Kode_Perusahaan,
    s.ID_Level,
    s.ID_Divisi,
    'kpiKoreksiTarget',
    s.UserID_Web,
    NULL,
    GETDATE(),
    GETDATE(),
    'Y'
FROM sumber AS s
WHERE s.urut = 1
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.HRIS_Page_Access AS ada
      WHERE ada.UserID_Web = s.UserID_Web
        AND ada.Jenis_page = 'kpiKoreksiTarget'
  );

PRINT 'Baris izin kpiKoreksiTarget ditambahkan: ' + CAST(@@ROWCOUNT AS varchar(10));

/* Penjaga: kelima orang HARUS berakhir memegang izin yang aktif. Kalau ada
   yang meleset -- misalnya UserID_Web-nya tidak punya satu pun baris akses
   sehingga Kode_Perusahaan/ID_Level/ID_Divisi tak bisa disalin -- lebih baik
   seluruh perubahan dibatalkan daripada empat dari lima diberi izin dan
   selisihnya baru ketahuan berminggu-minggu kemudian. */
DECLARE @lulus int;

SELECT @lulus = COUNT(*)
FROM dbo.HRIS_Page_Access
WHERE Jenis_page = 'kpiKoreksiTarget'
  AND Flag_Aktif = 'Y'
  AND UserID_Web IN (285, 367, 899, 1093, 1836);

IF @lulus <> 5
BEGIN
    ROLLBACK TRANSACTION;
    RAISERROR('DML-2026-119 GAGAL: hanya %d dari 5 penerima yang memegang izin kpiKoreksiTarget. Tidak ada perubahan yang disimpan.', 16, 1, @lulus);
    RETURN;
END

COMMIT TRANSACTION;

PRINT 'DML-2026-119 selesai. Penerima aktif: ' + CAST(@lulus AS varchar(10)) + ' dari 5.';
PRINT 'Jalankan verify.sql untuk pemeriksaan lengkap.';
