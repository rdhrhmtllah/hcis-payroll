/* Change ID: DML-2026-118-bersihkan-notifikasi-pa-yatim
   Target: Web_HRIS development; production only through SQL Tracker.
   First-run maximum scope: N_HRIS_Notification <= 30.000 dihapus, register the change <= 1.
   Dependencies: DML-2026-117 (siklus uji PA sudah dibersihkan).

   NOTIFIKASI PA YANG MENUNJUK PENUGASAN YANG SUDAH TIDAK ADA.

   DML-2026-117 menghapus ~32.500 baris di 18 tabel siklus PA, dan `N_HRIS_Notification`
   TIDAK termasuk di antaranya. Akibatnya seluruh pemberitahuan PA yang pernah dikirim
   tetap tinggal sambil menunjuk `Reference_Id` yang penugasannya sudah lenyap.

   KENAPA INI BUKAN SEKADAR SAMPAH YANG DIAM

   `Notification_Key` berbentuk `PA_DITUGASKAN:<Id_PA_Assignment>`, dan kunci itulah yang
   dipakai `NotificationService::upsertManyByKey()` untuk menolak kiriman ganda. Ketika
   penugasan baru memperoleh id yang PERNAH dipakai penugasan lama, kuncinya bertabrakan:
   satu id membawa dua baris dengan `Kode_Karyawan` berbeda — penilai lama dan penilai
   baru. Dedupe-nya berpasangan (kunci, karyawan) sehingga tidak melebur, dan yang lahir
   adalah dua pemberitahuan atas satu penugasan yang sama, salah satunya milik orang yang
   sudah tidak ada urusannya.

   Terbukti, bukan diduga: `NotificationTest::test_penugasan_baru_memberi_tahu_penilainya`
   memerah dengan 2 baris untuk `PA_DITUGASKAN:854` — `UJI0022` (peninggalan) dan `A2`
   (penilai yang sebenarnya).

   TERUKUR DI DEVELOPMENT 9 September 2026:
     N_HRIS_Notification bertipe PA_*            26.518
       PA_DITUGASKAN                             26.483
       PA_HASIL_TERBIT                                34
       PA_DIBATALKAN                                   1
     di antaranya YATIM (rujukannya sudah tidak ada) 26.518  <- seluruhnya

   Angka terakhir itu yang menentukan bentuk perubahan ini: tidak ada satu pun
   pemberitahuan PA di development yang masih menunjuk baris hidup, jadi tidak ada yang
   perlu dipertahankan. Sekalipun begitu, penghapusannya tetap ditulis BERSYARAT pada
   ketiadaan rujukan — bukan `WHERE Type LIKE 'PA[_]%'` polos — supaya skrip yang sama
   aman dibaca dan dinilai ulang di lingkungan yang datanya tidak sekosong ini.

   HANYA UNTUK DEVELOPMENT. Bila production sudah memuat pemberitahuan sungguhan yang
   dibaca karyawan, perubahan ini TIDAK BOLEH dijalankan apa adanya: pemberitahuan yang
   rujukannya hilang di sana adalah gejala kehilangan data lain, dan yang perlu diperiksa
   sebabnya — bukan jejaknya yang dihapus. Jalankan BAGIAN 1 (hanya SELECT) lebih dulu.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50418, 'DML-2026-118 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

/* ---------- BAGIAN 1: potret sebelum, hanya SELECT ---------- */

SELECT
    n.Type,
    COUNT(*) AS jumlah,
    SUM(CASE WHEN a.Id_PA_Assignment IS NULL AND r.Id_PA_Result IS NULL THEN 1 ELSE 0 END) AS yatim
FROM N_HRIS_Notification AS n
LEFT JOIN N_PA_Assignment AS a
       ON CAST(a.Id_PA_Assignment AS varchar(50)) = n.Reference_Id
      AND n.Type IN ('PA_DITUGASKAN', 'PA_DIBATALKAN', 'PA_TERLAMBAT')
LEFT JOIN N_PA_Result AS r
       ON CAST(r.Id_PA_Result AS varchar(50)) = n.Reference_Id
      AND n.Type = 'PA_HASIL_TERBIT'
WHERE n.Type LIKE 'PA[_]%'
GROUP BY n.Type
ORDER BY n.Type;
GO

/* ---------- BAGIAN 2: penghapusan ---------- */

BEGIN TRANSACTION;

/* Pemberitahuan yang berpangkal PENUGASAN. */
DELETE n
FROM N_HRIS_Notification AS n
WHERE n.Type IN ('PA_DITUGASKAN', 'PA_DIBATALKAN', 'PA_TERLAMBAT')
  AND NOT EXISTS (
        SELECT 1
        FROM N_PA_Assignment AS a
        WHERE CAST(a.Id_PA_Assignment AS varchar(50)) = n.Reference_Id
  );

/* Pemberitahuan yang berpangkal HASIL. */
DELETE n
FROM N_HRIS_Notification AS n
WHERE n.Type = 'PA_HASIL_TERBIT'
  AND NOT EXISTS (
        SELECT 1
        FROM N_PA_Result AS r
        WHERE CAST(r.Id_PA_Result AS varchar(50)) = n.Reference_Id
  );

/* ---------- BAGIAN 3: penjaga cakupan ---------- */

/* Tidak boleh ada satu pun yatim yang tersisa; kalau ada, penyaringnya tidak mengenai
   apa yang dimaksud dan lebih baik dibatalkan daripada setengah jalan. */
DECLARE @yatimSisa int = (
    SELECT COUNT(*)
    FROM N_HRIS_Notification AS n
    WHERE n.Type LIKE 'PA[_]%'
      AND NOT EXISTS (SELECT 1 FROM N_PA_Assignment AS a WHERE CAST(a.Id_PA_Assignment AS varchar(50)) = n.Reference_Id)
      AND NOT EXISTS (SELECT 1 FROM N_PA_Result AS r WHERE CAST(r.Id_PA_Result AS varchar(50)) = n.Reference_Id)
);

IF @yatimSisa > 0
BEGIN
    ROLLBACK TRANSACTION;
    THROW 51418, 'DML-2026-118 dibatalkan: masih ada notifikasi PA yatim sesudah penghapusan.', 1;
END;

COMMIT TRANSACTION;
GO

/* ---------- BAGIAN 4: pendaftaran perubahan ---------- */

IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
    WHERE Kode_Change = 'DML-2026-118-bersihkan-notifikasi-pa-yatim'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
        (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
         Artifact_Path, Documentation_Path, Target_Development,
         Status_Development, Status_SQL_Tracker, Status_Production,
         Status_Verification, Catatan, Created_By)
    VALUES
        ('DML-2026-118-bersihkan-notifikasi-pa-yatim',
         'DML',
         'Bersihkan notifikasi PA yang menunjuk penugasan/hasil yang sudah tidak ada',
         'DML-2026-117 menghapus siklus uji PA tetapi tidak menyentuh N_HRIS_Notification, sehingga 26.518 pemberitahuan PA tertinggal sambil menunjuk Reference_Id yang barisnya sudah lenyap. Kunci dedupe berbentuk PA_DITUGASKAN:<Id_PA_Assignment>, jadi id penugasan yang terpakai ulang membuat satu penugasan membawa dua pemberitahuan dengan penilai berbeda.',
         'database/dml/DML-2026-118-bersihkan-notifikasi-pa-yatim/data-change.sql',
         'database/dml/DML-2026-118-bersihkan-notifikasi-pa-yatim/data-change.sql',
         'Web_HRIS',
         'DONE', 'PENDING', 'PENDING', 'DEV_DONE',
         'HANYA UNTUK DEVELOPMENT. Di production, pemberitahuan yang rujukannya hilang adalah GEJALA kehilangan data lain; yang diperiksa sebabnya, bukan jejaknya yang dihapus. Jalankan BAGIAN 1 (hanya SELECT) lebih dulu.',
         'performance-appraisal');
END;
GO
