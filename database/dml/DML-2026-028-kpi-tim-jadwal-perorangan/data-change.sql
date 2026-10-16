/*
 * DML-2026-028 — Pengajuan KPI tim yang tersimpan di bawah jadwal PERORANGAN
 *
 * BELUM DIJALANKAN. Baca "Keputusan yang dibutuhkan" di README sebelum eksekusi.
 *
 * Latar:
 *   Halaman form KPI tim dulu memilih jadwal secara otomatis tanpa
 *   mengecualikan Flag_Perorangan='Y'. Jadwal 57 (perorangan, Periode NULL)
 *   memenangi ORDER BY Id DESC, sehingga seluruh tim mengisi di bawahnya.
 *   Anggota yang TIDAK ada di roster jadwal itu tidak punya bulan mulai di
 *   sana, sehingga KpiStartPeriodeResolver jatuh ke Periode jadwal tersebut —
 *   NULL — dan normalkan(null) mengubahnya menjadi Januari.
 *
 *   Akibatnya PKPI08-26-0054 (Dhea) dan PKPI08-26-0055 (Anita) tersimpan mulai
 *   2026-01, padahal jadwal tim yang benar (54) mulai 2026-05. Amelia yang ada
 *   di roster jadwal 57 tersimpan benar mulai 2026-02.
 *
 *   Alur sudah diperbaiki tiga lapis di FormKpiController; berkas ini hanya
 *   menangani data yang telanjur tersimpan.
 */

-- ────────────────────────────────────────────────────────────────────────────
-- 1. LINGKUP — jalankan dulu, pastikan angkanya sesuai sebelum langkah 2/3.
--    Hasil pada Web_HRIS (development), 26 Agu 2026: 36 baris, 2 pengajuan.
-- ────────────────────────────────────────────────────────────────────────────
SELECT  p.No_Transaksi,
        u.Kode_Users            AS Kode_Karyawan,
        p.Id_Penjadwalan,
        t.Periode,
        COUNT(*)                AS Jumlah_Baris
FROM        KPI_Pengajuan            AS p
JOIN        KPI_Master_Penjadwalan   AS j ON j.Id_Master_Penjadwalan   = p.Id_Penjadwalan
JOIN        KPI_Users                AS u ON u.Id_Users                = p.Id_User
JOIN        KPI_Pengajuan_Detail     AS d ON d.Id_Pengajuan            = p.Id_Pengajuan
JOIN        KPI_Pengajuan_Det        AS t ON t.Id_Pengajuan_Detail     = d.Id_Pengajuan_Detail
WHERE   j.Flag_Perorangan = 'Y'
  AND   p.Flag_Aktif      = 'Y'
  AND   NOT EXISTS (
            SELECT 1
            FROM   N_KPI_Master_Penjadwalan_Perorangan AS r
            WHERE  r.Id_Master_Penjadwalan = p.Id_Penjadwalan
              AND  r.Kode_Karyawan         = u.Kode_Users
        )
GROUP BY p.No_Transaksi, u.Kode_Users, p.Id_Penjadwalan, t.Periode
ORDER BY p.No_Transaksi, t.Periode;

-- ────────────────────────────────────────────────────────────────────────────
-- 2. OPSI A (disarankan) — batalkan pengajuannya, biarkan leader mengirim ulang
--
--    Alasannya: nilai target yang diisi leader untuk Januari–April bukan
--    sekadar baris berlebih. Leader mengisinya dengan asumsi setahun penuh,
--    jadi memotong empat bulan di depan mengubah arti angka yang tersisa.
--    Mengirim ulang lewat alur yang sudah diperbaiki menghasilkan data yang
--    memang dimaksudkan, bukan tebakan kita.
-- ────────────────────────────────────────────────────────────────────────────
-- BEGIN TRAN;
--
-- UPDATE  KPI_Pengajuan
-- SET     Flag_Aktif = 'T',
--         Status     = 'Y',
--         Cancel_Reason = 'Dibatalkan sistem: tersimpan di bawah jadwal perorangan (DML-2026-028)'
-- WHERE   No_Transaksi IN ('PKPI08-26-0054', 'PKPI08-26-0055');
--
-- -- Langkah approval WAJIB ikut ditutup, kalau tidak barisnya menggantung di
-- -- inbox approver dan versi lama masih bisa disetujui.
-- UPDATE  HRIS_Approval_Request
-- SET     Status = 'Y'
-- WHERE   No_Transaksi IN ('PKPI08-26-0054', 'PKPI08-26-0055')
--   AND   Flag_Approval IS NULL;
--
-- -- Harap: 2 baris KPI_Pengajuan, 2 baris HRIS_Approval_Request.
-- COMMIT;   -- ROLLBACK bila angkanya tidak sesuai

-- ────────────────────────────────────────────────────────────────────────────
-- 3. OPSI B — pertahankan pengajuannya, buang baris di bawah bulan mulai
--
--    Hanya bila Anda yakin nilai bulan Mei–Desember tetap sahih berdiri
--    sendiri. Bulan mulai yang benar diambil dari jadwal tim yang sah (54,
--    Periode 2026-05).
-- ────────────────────────────────────────────────────────────────────────────
-- BEGIN TRAN;
--
-- DELETE  t
-- FROM        KPI_Pengajuan_Det        AS t
-- JOIN        KPI_Pengajuan_Detail     AS d ON d.Id_Pengajuan_Detail = t.Id_Pengajuan_Detail
-- JOIN        KPI_Pengajuan            AS p ON p.Id_Pengajuan        = d.Id_Pengajuan
-- WHERE   p.No_Transaksi IN ('PKPI08-26-0054', 'PKPI08-26-0055')
--   AND   t.Periode < '2026-05';
--
-- -- Id_Penjadwalan ikut dibetulkan supaya laporan membaca jadwal yang benar.
-- UPDATE  KPI_Pengajuan
-- SET     Id_Penjadwalan = 54
-- WHERE   No_Transaksi IN ('PKPI08-26-0054', 'PKPI08-26-0055');
--
-- -- Harap: 10 baris terhapus, 2 baris KPI_Pengajuan ter-update.
-- -- (Terhitung pada Web_HRIS 26 Agu 2026. BUKAN 12: tidak setiap bulan punya
-- --  baris target, jadi jangan menebak dari jumlah bulan.)
-- COMMIT;   -- ROLLBACK bila angkanya tidak sesuai

-- ────────────────────────────────────────────────────────────────────────────
-- 4. VERIFIKASI — sesudah opsi mana pun, langkah 1 harus mengembalikan 0 baris.
-- ────────────────────────────────────────────────────────────────────────────
