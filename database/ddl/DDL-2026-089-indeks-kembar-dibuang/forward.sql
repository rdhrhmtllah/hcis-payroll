-- DDL-2026-089-indeks-kembar-dibuang
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI. Skrip ini berisi DROP INDEX; ia harus dijalankan MANUSIA,
-- setelah BAGIAN 7 dipenuhi. Tidak boleh dijalankan oleh agen.
--
-- BAGIAN 0 : gerbang basis data
-- BAGIAN 1 : HRIS_KANDIDAT_Jawaban_Soal
-- BAGIAN 2 : HRIS_KANDIDAT_Master_Soal
-- BAGIAN 3 : N_WEB_CAREERS_Lamaran_Tahap
-- BAGIAN 4 : HRIS_Rekrutmen_Karyawan
-- BAGIAN 5 : N_WEB_CAREERS_* (dua tabel informasi) dan N_ESIGN_Revoked_Certificates
-- BAGIAN 6 : verifikasi
-- BAGIAN 7 : yang HARUS dijalankan di production lebih dulu
--
-- ── TEMUAN ───────────────────────────────────────────────────────────────────
--
-- Tujuh indeks kembar SEJATI: kolom kunci, urutannya, kolom INCLUDE, dan
-- definisi saringannya sama persis dengan indeks lain pada tabel yang sama.
-- Indeks kembar tidak memberi manfaat baca sedikit pun — pengoptimal hanya
-- memakai salah satu — tetapi setiap INSERT, UPDATE, dan DELETE tetap harus
-- memelihara keduanya.
--
--   tabel                                   baris  disimpan / dibuang        seeks
--   ---------------------------------------------------------------------------
--   HRIS_KANDIDAT_Jawaban_Soal             11.236  PK__HRIS_KAN__5E8D9D0C     4.512
--                                                  idx_jawaban_soal_pk            7
--   HRIS_KANDIDAT_Master_Soal               2.788  PK__HRIS_KAN__9F8D659A   194.029
--                                                  idx_master_soal_pk             8
--   N_WEB_CAREERS_Lamaran_Tahap             2.179  UQ_..._Tahap_Urutan        6.496
--                                                  IX_..._Tahap_Lamaran       1.294
--   HRIS_Rekrutmen_Karyawan                    71  idx_rk_kode_calon            676
--                                                  idx_rekrutmen_kode_calon       0
--   N_WEB_CAREERS_Sub_Divisi_Informations      32  UQ__N_WEB_CA__03FA2B1A        42
--                                                  Idx_DeptInfo_Sub_Divisi      139
--   N_WEB_CAREERS_Division_Informations        22  UQ__N_WEB_CA__B2357D05        33
--                                                  Idx_DivInfo_Divisi           584
--   N_ESIGN_Revoked_Certificates                0  UQ_Revoked_SerialNumber        0
--                                                  IX_Revoked_SerialNumber        0
--
-- Diukur pada Web_HRIS (development), jendela 2026-08-18 03:26 sampai
-- 2026-09-08 16:27. Beberapa yang dibuang memang punya seek — itu wajar: kalau
-- dua indeks identik, pengoptimal memilih salah satu sembarang. Sesudah yang
-- kembar dibuang, seek itu pindah ke saudaranya, bukan hilang.
--
-- ── KENAPA YANG DISIMPAN ADALAH YANG UNIK ────────────────────────────────────
--
-- Pada enam dari tujuh pasang, salah satunya adalah PRIMARY KEY atau UNIQUE
-- CONSTRAINT. Yang unik WAJIB tinggal: ia bukan sekadar indeks melainkan
-- penjamin keutuhan data, dan membuangnya membuka pintu baris kembar. Yang
-- non-unik tidak menjamin apa pun dan tidak dipakai untuk apa pun yang tidak
-- bisa dilayani saudaranya.
--
-- Pasangan HRIS_Rekrutmen_Karyawan adalah kekecualian: keduanya non-unik dan
-- identik. Yang disimpan adalah yang punya seek (idx_rk_kode_calon, 676).
--
-- ── TUJUH TUDUHAN YANG SENGAJA DITARIK ───────────────────────────────────────
--
-- Pemeriksaan pertama menemukan empat belas pasang berkunci sama. Tujuh di
-- antaranya TERNYATA BUKAN kembar, dan tidak masuk skrip ini:
--
--   IX_JawabanAkhir_Token vs IX_JawAkhir_Token        INCLUDE berbeda
--   IX_KPI_Pengajuan_Det_Periode vs ..._IdDetail_...  INCLUDE berbeda
--   UX_HRIS_Absen_Snapshot_... vs IX_..._Read         INCLUDE berbeda
--   UQ__KPI_Peng__3A5E7948 vs IX_KPI_Pengajuan_No...  INCLUDE berbeda
--   IX_N_SOP_Item_Versi_Dokumen vs UX_..._Urut_Hidup  SARINGAN berbeda
--                                     (WHERE Flag_Aktif = 'Y')
--   UX_NWC_SkrVersi_Terbit vs UX_NWC_SkrVersi_Draf    SARINGAN berbeda
--                                     (Status='PUBLISHED' vs Status='DRAFT')
--   PK__Cutoff_C__3214EC07 vs IDX_Active_Config       SARINGAN berbeda
--                                     (WHERE Effective_To IS NULL)
--
-- Indeks tersaring adalah pola yang dipakai sadar di repo ini. Membuang salah
-- satunya karena kuncinya "kelihatan sama" akan menghapus penjamin keunikan
-- yang hanya berlaku pada baris aktif.
--
-- ── APA YANG TIDAK DILAKUKAN DI SINI ─────────────────────────────────────────
--
-- Tidak ada DROP TABLE, DROP COLUMN, maupun TRUNCATE. Tidak ada satu baris data
-- pun yang berubah. Setiap indeks yang dibuang bisa dibangun kembali persis
-- lewat drop.sql, yang berisi definisi aslinya kata per kata.

/* ═══ BAGIAN 0 : gerbang basis data ═══════════════════════════════════════ */

IF DB_NAME() NOT IN ('Web_HRIS')
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    SET NOEXEC ON;
END
GO

/* ═══ BAGIAN 1 : HRIS_KANDIDAT_Jawaban_Soal ═══════════════════════════════
 * Disimpan : PK__HRIS_KAN__5E8D9D0C776009B3 (PK, unik, Id_Jawaban_Soal)
 * Dibuang  : idx_jawaban_soal_pk (non-unik, kunci identik, 7 seek, 9.298 tulis)
 */
DROP INDEX IF EXISTS idx_jawaban_soal_pk ON dbo.HRIS_KANDIDAT_Jawaban_Soal;
GO

/* ═══ BAGIAN 2 : HRIS_KANDIDAT_Master_Soal ════════════════════════════════
 * Disimpan : PK__HRIS_KAN__9F8D659AC461D905 (PK, unik, 194.029 seek)
 * Dibuang  : idx_master_soal_pk (non-unik, kunci identik, 8 seek)
 */
DROP INDEX IF EXISTS idx_master_soal_pk ON dbo.HRIS_KANDIDAT_Master_Soal;
GO

/* ═══ BAGIAN 3 : N_WEB_CAREERS_Lamaran_Tahap ══════════════════════════════
 * Disimpan : UQ_N_WEB_CAREERS_Lamaran_Tahap_Urutan (unik, Lamaran_Id + Urutan)
 * Dibuang  : IX_N_WEB_CAREERS_Lamaran_Tahap_Lamaran (kunci identik)
 */
DROP INDEX IF EXISTS IX_N_WEB_CAREERS_Lamaran_Tahap_Lamaran ON dbo.N_WEB_CAREERS_Lamaran_Tahap;
GO

/* ═══ BAGIAN 4 : HRIS_Rekrutmen_Karyawan ══════════════════════════════════
 * Keduanya non-unik dan identik; yang dipakai pengoptimal yang disimpan.
 * Disimpan : idx_rk_kode_calon (676 seek)
 * Dibuang  : idx_rekrutmen_kode_calon (0 seek)
 */
DROP INDEX IF EXISTS idx_rekrutmen_kode_calon ON dbo.HRIS_Rekrutmen_Karyawan;
GO

/* ═══ BAGIAN 5 : tabel kecil ══════════════════════════════════════════════ */
DROP INDEX IF EXISTS Idx_DeptInfo_Sub_Divisi ON dbo.N_WEB_CAREERS_Sub_Divisi_Informations;
GO
DROP INDEX IF EXISTS Idx_DivInfo_Divisi ON dbo.N_WEB_CAREERS_Division_Informations;
GO
DROP INDEX IF EXISTS IX_Revoked_SerialNumber ON dbo.N_ESIGN_Revoked_Certificates;
GO

/* ═══ BAGIAN 6 : verifikasi ═══════════════════════════════════════════════
 * Harus mengembalikan NOL baris: tidak boleh ada lagi pasangan indeks dengan
 * kunci, INCLUDE, dan saringan yang sama persis.
 */

WITH k AS (
    SELECT i.object_id, i.index_id, t.name AS tabel, i.name AS indeks,
           STUFF((SELECT ',' + c.name
                  FROM sys.index_columns ic
                  JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                  WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                    AND ic.is_included_column = 0
                  ORDER BY ic.key_ordinal FOR XML PATH('')), 1, 1, '') AS kunci,
           ISNULL(STUFF((SELECT ',' + c.name
                  FROM sys.index_columns ic
                  JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                  WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                    AND ic.is_included_column = 1
                  ORDER BY c.name FOR XML PATH('')), 1, 1, ''), '') AS ikut,
           ISNULL(i.filter_definition, '') AS saringan
    FROM sys.indexes i
    JOIN sys.tables t ON t.object_id = i.object_id
    WHERE i.type_desc <> 'HEAP'
)
SELECT a.tabel, a.indeks AS indeks_a, b.indeks AS indeks_b, a.kunci
FROM k a
JOIN k b ON a.object_id = b.object_id AND a.index_id < b.index_id
        AND a.kunci = b.kunci AND a.ikut = b.ikut AND a.saringan = b.saringan
ORDER BY a.tabel;
GO

SET NOEXEC OFF;
GO

/* ═══ BAGIAN 7 : yang HARUS dijalankan di production LEBIH DULU ═══════════
 *
 * Nama indeks di production BELUM TENTU sama dengan di development, dan indeks
 * bernama sama pun bisa berkunci berbeda. Menjalankan DROP berdasarkan daftar
 * dari development bisa membuang indeks yang di sana justru satu-satunya.
 *
 * Jalankan blok HANYA-BACA berikut di production dan pastikan tiap pasangan
 * yang hendak dibuang memang kembar DI SANA juga:
 *
 *   -- salin kueri BAGIAN 6 di atas apa adanya; ia hanya membaca katalog.
 *
 * Atau, cara yang bisa diulang:
 *
 *   php artisan db:inventaris-skema --pemakaian --keluaran=prod.json   (di production)
 *   php artisan db:inventaris-skema --banding=dev.json,prod.json
 *
 * Perhatikan khususnya bagian "indeks bernama sama tetapi BERBEDA isinya".
 */
