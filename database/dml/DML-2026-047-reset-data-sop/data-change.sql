/*  DML-2026-047 — Reset data paket & dokumen SOP
    ------------------------------------------------------------------------
    Target   : Web_HRIS (DEVELOPMENT)
    Maksud   : mengosongkan modul SOP supaya alur wizard versi yang baru diisi
               dari data bersih. Master TIDAK disentuh.
    Sebab    : pendefinisian versi dirombak (lihat
               plans/sop/2026-08-26-wizard-versi-dokumen-design.md). Data yang
               terlanjur dibuat lewat alur lama tak mewakili alur baru, dan
               membiarkannya membuat pengujian alur baru selalu bercampur
               dengan keadaan yang tak bisa dijelaskan.

    CARA     : DELETE berpredikat, BUKAN TRUNCATE. Urut anak -> induk supaya
               tak ada kunci asing yang mengeluh, dan supaya kegagalan di
               tengah bisa dibaca dari tabel mana yang tersisa.

    BARIS TERDUGA (dihitung 2026-08-26 terhadap Web_HRIS dev, total 4.135):
      Progress 2079 · Absensi 7 · Kepatuhan_Riwayat 303 · Coverage_Rollup 132
      Penjadwalan_Detail 368 · Penjadwalan_Integrasi 9 · Penjadwalan 22
      Favorit 1 · Paket_Versi_Item 88 · Paket_Versi 34 · Paket_Item 72
      Paket_Section 26 · Paket_Draft_Item_Versi 2 · Paket_Draft_Item 43
      Paket_Draft_Section 11 · Paket_Draft 11 · Master_Paket 17
      Item_Versi_Review_Lampiran 2 · Item_Versi_Review 1
      Item_Versi_Pengesah 34 · Item_Versi_Berkas 8 · Item_Versi 76
      Dokumen 44 · Log 745

    TIDAK DISENTUH (master & setelan):
      N_SOP_Kategori · N_SOP_Master_APQC (+Tipe_Item)
      N_SOP_Master_Jenis_Dokumen (+Tipe_Item) · N_SOP_Master_Tahap_Versi
      (+Syarat +Transisi) · N_SOP_Setting · N_SOP_Prioritas
      · N_SOP_Assignment_Rule · N_SOP_Automation_Run · N_SOP_Kuis_Integrasi
      · N_SOP_Aktivitas_Log
      Library konten juga tak disentuh — ia milik modul lain, bukan "paket
      dan dokumen".
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

DECLARE @sebelum TABLE (Tabel varchar(64), Baris int);
INSERT INTO @sebelum (Tabel, Baris) VALUES
 ('N_SOP_Progress',            (SELECT COUNT(*) FROM N_SOP_Progress)),
 ('N_SOP_Penjadwalan_Detail',  (SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail)),
 ('N_SOP_Master_Paket',        (SELECT COUNT(*) FROM N_SOP_Master_Paket)),
 ('N_SOP_Item_Versi',          (SELECT COUNT(*) FROM N_SOP_Item_Versi)),
 ('N_SOP_Dokumen',             (SELECT COUNT(*) FROM N_SOP_Dokumen)),
 ('N_SOP_Log',                 (SELECT COUNT(*) FROM N_SOP_Log));

/* ── 1..8  jejak turunan penugasan ────────────────────────────────────────
   Dihapus LEBIH DULU, bukan ditinggalkan. Menghapus paket & dokumen tanpa
   menyapu jejaknya meninggalkan baris yatim yang menunjuk id yang sudah tak
   ada — persis puing 133 baris sisa uji beban 14 Agu yang sampai sekarang
   masih menunggu dibersihkan. */
DELETE FROM N_SOP_Progress                   WHERE 1 = 1;
DELETE FROM N_SOP_Absensi                    WHERE 1 = 1;
DELETE FROM N_SOP_Kepatuhan_Riwayat          WHERE 1 = 1;
DELETE FROM N_SOP_Coverage_Rollup            WHERE 1 = 1;
DELETE FROM N_SOP_Penjadwalan_Detail         WHERE 1 = 1;
DELETE FROM N_SOP_Penjadwalan_Integrasi      WHERE 1 = 1;
DELETE FROM N_SOP_Penjadwalan                WHERE 1 = 1;
DELETE FROM N_SOP_Favorit                    WHERE 1 = 1;

/* ── 9..17  paket terbit dan salinan kerjanya ───────────────────────────── */
DELETE FROM N_SOP_Paket_Versi_Item           WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Versi                WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Item                 WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Section              WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Draft_Item_Versi     WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Draft_Item           WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Draft_Section        WHERE 1 = 1;
DELETE FROM N_SOP_Paket_Draft                WHERE 1 = 1;
DELETE FROM N_SOP_Master_Paket               WHERE 1 = 1;

/* ── 18..23  dokumen dan versinya ───────────────────────────────────────── */
DELETE FROM N_SOP_Item_Versi_Review_Lampiran WHERE 1 = 1;
DELETE FROM N_SOP_Item_Versi_Review          WHERE 1 = 1;
DELETE FROM N_SOP_Item_Versi_Pengesah        WHERE 1 = 1;
DELETE FROM N_SOP_Item_Versi_Berkas          WHERE 1 = 1;
DELETE FROM N_SOP_Item_Versi                 WHERE 1 = 1;
DELETE FROM N_SOP_Dokumen                    WHERE 1 = 1;

/* ── 24  jejak audit modul ────────────────────────────────────────────────
   Tak ada lagi yang dirujuknya, dan riwayat perubahan atas dokumen yang
   sudah tak ada hanya menyesatkan pembacanya. */
DELETE FROM N_SOP_Log                        WHERE 1 = 1;

/* ── Penjaga: master WAJIB utuh ───────────────────────────────────────────
   Nol baris di salah satu master berarti cakupan penghapusan meleset jauh
   dari maksudnya. Lebih baik seluruh transaksi dibatalkan daripada modul
   ditinggalkan tanpa tahap, jenis dokumen, atau kategori. */
IF (SELECT COUNT(*) FROM N_SOP_Master_Tahap_Versi) = 0
   OR (SELECT COUNT(*) FROM N_SOP_Master_Jenis_Dokumen) = 0
   OR (SELECT COUNT(*) FROM N_SOP_Kategori) = 0
   OR (SELECT COUNT(*) FROM N_SOP_Master_APQC) = 0
BEGIN
    ROLLBACK TRAN;
    THROW 50047, 'Master SOP ikut terhapus — seluruh perubahan dibatalkan.', 1;
END

SELECT s.Tabel,
       s.Baris AS Sebelum,
       CASE s.Tabel
         WHEN 'N_SOP_Progress'           THEN (SELECT COUNT(*) FROM N_SOP_Progress)
         WHEN 'N_SOP_Penjadwalan_Detail' THEN (SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail)
         WHEN 'N_SOP_Master_Paket'       THEN (SELECT COUNT(*) FROM N_SOP_Master_Paket)
         WHEN 'N_SOP_Item_Versi'         THEN (SELECT COUNT(*) FROM N_SOP_Item_Versi)
         WHEN 'N_SOP_Dokumen'            THEN (SELECT COUNT(*) FROM N_SOP_Dokumen)
         WHEN 'N_SOP_Log'                THEN (SELECT COUNT(*) FROM N_SOP_Log)
       END AS Sesudah
  FROM @sebelum s;

COMMIT TRAN;
