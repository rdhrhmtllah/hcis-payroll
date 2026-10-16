-- database/dml/DML-2026-015-bersihkan-riwayat-kepatuhan-yatim/data-change.sql
-- Target: Web_HRIS. Dokumentasi: docs/changes/DML-2026-015-bersihkan-riwayat-kepatuhan-yatim/README.md
--
-- Membuang baris N_SOP_Kepatuhan_Riwayat yang menunjuk paket SOP yang sudah
-- tidak ada di N_SOP_Master_Paket. Baris seperti ini tak bisa ditampilkan secara
-- bermakna — nama paketnya kosong — dan ikut terhitung sebagai peristiwa pada
-- grafik tren kepatuhan.
--
-- Pada development (20 Agu 2026) yang terjaring: 133 baris, seluruhnya
-- Peristiwa = 'DICABUT', lahir 14 Agu 2026 pukul 15:32-16:47, menyentuh 50
-- karyawan, menunjuk Id_SOP_Paket 838-923 yang tidak ada di master maupun di
-- tabel draft. Ciri puing uji beban: penjadwalannya sudah dibersihkan, ledger-nya
-- tertinggal.
--
-- JALANKAN LANGKAH 1 DAN 2 LEBIH DULU DAN BACA HASILNYA. Angka di production
-- boleh berbeda; kalau yang terjaring jauh lebih banyak dari perkiraan, BERHENTI.

/* ---------- 1. Hitung dulu: berapa yang akan terhapus ---------- */
SELECT r.Peristiwa,
       COUNT(*)                       AS Jumlah_Baris,
       COUNT(DISTINCT r.Kode_Karyawan) AS Jumlah_Karyawan,
       MIN(r.Waktu_Peristiwa)          AS Paling_Awal,
       MAX(r.Waktu_Peristiwa)          AS Paling_Akhir
FROM N_SOP_Kepatuhan_Riwayat r
LEFT JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = r.Id_SOP_Paket
WHERE p.Id_SOP_Paket IS NULL
GROUP BY r.Peristiwa;

/* ---------- 2. Paket mana saja yang dirujuk tapi sudah lenyap ---------- */
SELECT DISTINCT r.Id_SOP_Paket
FROM N_SOP_Kepatuhan_Riwayat r
LEFT JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = r.Id_SOP_Paket
WHERE p.Id_SOP_Paket IS NULL
ORDER BY r.Id_SOP_Paket;

/* ---------- 3. Cadangkan sebelum menghapus ----------
   Tabel cadangan dibuat sekali dan sengaja TIDAK ikut dihapus di akhir.
   Ini satu-satunya jalan pulang bila ternyata ada baris yang masih dibutuhkan. */
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'ZZ_Backup_SOP_Rwt_Yatim_20260820')
    SELECT r.*
    INTO   ZZ_Backup_SOP_Rwt_Yatim_20260820
    FROM   N_SOP_Kepatuhan_Riwayat r
    LEFT JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = r.Id_SOP_Paket
    WHERE  p.Id_SOP_Paket IS NULL;

SELECT COUNT(*) AS Baris_Tercadangkan FROM ZZ_Backup_SOP_Rwt_Yatim_20260820;

/* ---------- 4. Hapus ----------
   Predikat SAMA PERSIS dengan langkah 1 dan 3. Jangan diubah salah satunya saja. */
BEGIN TRAN;

DELETE r
FROM N_SOP_Kepatuhan_Riwayat r
LEFT JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = r.Id_SOP_Paket
WHERE p.Id_SOP_Paket IS NULL;

SELECT @@ROWCOUNT AS Baris_Terhapus;

-- Periksa angkanya. Cocok dengan langkah 1 → COMMIT. Tidak cocok → ROLLBACK.
-- COMMIT;
-- ROLLBACK;

/* ---------- 5. Pastikan bersih ---------- */
-- SELECT COUNT(*) AS Sisa_Yatim
-- FROM N_SOP_Kepatuhan_Riwayat r
-- LEFT JOIN N_SOP_Master_Paket p ON p.Id_SOP_Paket = r.Id_SOP_Paket
-- WHERE p.Id_SOP_Paket IS NULL;
