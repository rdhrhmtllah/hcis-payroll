/* Change ID: DDL-2026-089-kalibrasi-boleh-dilepas
   Target: Web_HRIS development; production hanya lewat SQL Tracker.
   Dependencies: tidak ada.
   Scope: N_PA_Calibration_History — dua pelonggaran NOT NULL dan satu CHECK penggantinya.

   ------------------------------------------------------------------------------------
   YANG DISELESAIKAN
   ------------------------------------------------------------------------------------

   Riwayat kalibrasi dirancang untuk SATU jenis peristiwa: forum menggeser grade seseorang.
   Peristiwa itu selalu punya grade sesudah dan selalu punya pelaku manusia, jadi kedua
   kolomnya NOT NULL dan tidak ada yang salah dengan itu.

   Sejak 7 September 2026 ada peristiwa KEDUA di tabel yang sama: KALIBRASI DILEPAS, ketika
   perhitungan ulang membuat hasil yang sudah dikalibrasi menjadi TERTAHAN. Peristiwa itu
   tidak punya dua hal yang dituntut kolomnya:

     Kode_Grade_Sesudah   Hasil tertahan tidak punya grade sama sekali. Grade final hasil
                          kalibrasi tidak bisa berdiri di atas hasil yang tak punya angka,
                          jadi "sesudah"-nya memang tidak ada.

     Performed_By         Pemicunya kiriman terakhir seorang penilai dari HC Learn, yang
                          masuk lewat callback dan diproses di antrean. Tidak ada sesi HCIS
                          di sana, dan karenanya tidak ada user yang jujur bisa dituliskan.

   Tanpa pelonggaran ini tersisa tiga jalan dan ketiganya buruk. Menuliskan grade karangan
   ('-', 'TERTAHAN') menaruh nilai yang bukan grade ke dalam kolom grade, dan layar mana pun
   yang membacanya akan menampilkannya sebagai grade. Menuliskan user karangan menaruh nama
   orang pada tindakan yang tidak ia lakukan. Atau tidak mencatat peristiwanya sama sekali —
   dan itu berarti grade yang pernah disepakati forum lenyap tanpa satu pun keterangan,
   padahal riwayat inilah SATU-SATUNYA tempat yang menyimpan nilai sebelumnya.

   ------------------------------------------------------------------------------------
   KENAPA ADA CHECK, BUKAN SEKADAR MELONGGARKAN
   ------------------------------------------------------------------------------------

   `Performed_By` yang polos NULL-able melemahkan kolom audit yang justru paling penting:
   kalibrasi manual yang kehilangan pelakunya karena bug tidak akan berbunyi sebagai galat,
   ia hanya menjadi baris tanpa nama yang baru terasa saat ada yang bertanya siapa yang
   menggeser grade seseorang.

   Karena itu kelonggarannya DIIKAT: baris tanpa pelaku hanya sah bila ia memang pelepasan
   otomatis — yang dikenali dari tidak adanya grade sesudah. Kalibrasi manual yang kehilangan
   pelakunya tetap ditolak basis data, persis seperti sebelum perubahan ini.

   ------------------------------------------------------------------------------------
   KENAPA HANYA MELONGGARKAN
   ------------------------------------------------------------------------------------

   Tidak ada baris yang diubah dan tidak ada kolom yang dibuang. Yang berubah hanya bahwa
   baris BARU boleh tidak punya keduanya. Panjang dan tipe kolom dipertahankan persis
   (varchar(40)) supaya ALTER ini tidak diam-diam menjadi perubahan lain.

   PENYERTA DI KODE: PaResultCalculationService::lepaskanKalibrasi() menulis baris pelepasan
   itu, dan mengisi Performed_By_Nama dengan penanda sistem supaya audit tetap terbaca
   meski pelakunya kosong. Pembacanya (PaAuditService::kalibrasi) sudah memakai
   COALESCE(Kode_Grade_Sesudah, '-'), jadi barisnya terbaca "Grade B+ menjadi -".
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50580, 'DDL-2026-089 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Tanpa transaksi eksplisit: tiga pernyataan yang masing-masing atomik dan dijaga IF
   EXISTS, sehingga aman dijalankan ulang dan aman berhenti di tengah. */

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'Kode_Grade_Sesudah' AND is_nullable = 0
)
    ALTER TABLE N_PA_Calibration_History ALTER COLUMN Kode_Grade_Sesudah VARCHAR(40) NULL;
GO

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'Performed_By' AND is_nullable = 0
)
    ALTER TABLE N_PA_Calibration_History ALTER COLUMN Performed_By INT NULL;
GO

/* Pengikat kelonggaran di atas: tanpa pelaku hanya sah pada pelepasan otomatis. */
IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'CK_PA_Calibration_History_Pelaku'
)
    ALTER TABLE N_PA_Calibration_History
        ADD CONSTRAINT CK_PA_Calibration_History_Pelaku
        CHECK (Performed_By IS NOT NULL OR Kode_Grade_Sesudah IS NULL);
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ */

IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name IN ('Kode_Grade_Sesudah', 'Performed_By')
      AND is_nullable = 0
)
    THROW 50581, 'Kolom pelepasan kalibrasi masih NOT NULL; pelepasan tetap tidak bisa dicatat.', 1;

IF NOT EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name = 'CK_PA_Calibration_History_Pelaku'
)
    THROW 50583, 'CK_PA_Calibration_History_Pelaku tidak terpasang; kalibrasi manual tanpa pelaku tidak lagi tertolak.', 1;

/* Panjang kolom TIDAK BOLEH ikut berubah — ALTER COLUMN yang salah ketik panjangnya
   memotong data tanpa berbunyi. */
IF EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Calibration_History')
      AND name IN ('Kode_Grade_Sebelum', 'Kode_Grade_Sesudah')
      AND max_length <> 40
)
    THROW 50584, 'Panjang kolom grade berubah dari 40; ALTER COLUMN memotong nilainya.', 1;

/* VERIFIKASI — kedua kolom grade kini sama perlakuannya, pelaku boleh kosong dengan syarat. */
SELECT c.name, t.name AS tipe, c.max_length, c.is_nullable
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('N_PA_Calibration_History')
  AND c.name IN ('Kode_Grade_Sebelum', 'Kode_Grade_Sesudah', 'Performed_By');

SELECT name, definition FROM sys.check_constraints
WHERE parent_object_id = OBJECT_ID('N_PA_Calibration_History');
