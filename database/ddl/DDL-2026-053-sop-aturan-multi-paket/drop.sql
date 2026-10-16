/* Change ID: DDL-2026-053-sop-aturan-multi-paket -- pembersihan manual.
 * JANGAN dijalankan oleh agent. Membuang data.
 *
 * URUTAN adalah kebalikan forward.sql: penjaga lama dipulihkan lebih dulu,
 * penjaga baru dibongkar belakangan, supaya tidak ada jeda tanpa penjaga.
 *
 * PRASYARAT bagian 1: N_SOP_Assignment_Rule.Id_SOP_Paket harus terisi dan unik
 * di setiap baris. Setelah DDL-2026-053 kolom itu berhenti diisi, jadi bila
 * sudah ada aturan yang lahir sesudahnya, isi dulu kolom tersebut dari
 * N_SOP_Assignment_Rule_Paket -- atau hapus baris aturannya -- sebelum
 * menjalankan bagian ini. Kalau tidak, ALTER COLUMN dan penambahan constraint
 * akan gagal.
 */

/* ---- 1. Pulihkan penjaga lama ---- */
ALTER TABLE N_SOP_Assignment_Rule ALTER COLUMN Id_SOP_Paket int NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM sys.objects
               WHERE name = 'UQ_N_SOP_Assignment_Rule_Paket'
                 AND parent_object_id = OBJECT_ID('N_SOP_Assignment_Rule'))
    ALTER TABLE N_SOP_Assignment_Rule
        ADD CONSTRAINT UQ_N_SOP_Assignment_Rule_Paket UNIQUE (Id_SOP_Paket);
GO

/* ---- 2. Bongkar riwayat per paket ---- */
DROP INDEX IF EXISTS IX_SOP_Automation_Run_Induk ON N_SOP_Automation_Run;
ALTER TABLE N_SOP_Automation_Run DROP COLUMN Id_SOP_Paket;
ALTER TABLE N_SOP_Automation_Run DROP COLUMN Id_Run_Induk;
GO

/* ---- 3. Bongkar tabel jembatan ---- */
DROP INDEX IF EXISTS UX_SOP_Assignment_Rule_Paket_Paket ON N_SOP_Assignment_Rule_Paket;
DROP INDEX IF EXISTS IX_SOP_Assignment_Rule_Paket_Rule  ON N_SOP_Assignment_Rule_Paket;
DROP TABLE IF EXISTS N_SOP_Assignment_Rule_Paket;
GO

/* ---- 4. Opsional, TERPISAH dari pembalikan di atas ----
 * Membuang kolom paket lama yang sudah yatim setelah DDL-2026-053. Ini BUKAN
 * bagian dari pembalikan -- forward.sql sengaja hanya menjadikannya nullable,
 * karena membuang kolom membuang data dan wajib dijalankan manual. Jangan
 * dijalankan bersama bagian 1: keduanya bertentangan. */
-- ALTER TABLE N_SOP_Assignment_Rule DROP COLUMN Id_SOP_Paket;
-- GO

/* ---- 5. Cabut pencatatan ---- */
DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-053-sop-aturan-multi-paket';
GO
