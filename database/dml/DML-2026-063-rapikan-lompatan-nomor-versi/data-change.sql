/*  DML-2026-063 — Rapikan lompatan nomor versi peninggalan bug penomoran
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Status : DIJALANKAN di development 30 Agu 2026 atas permintaan user.

    MASALAHNYA
    Sampai 30 Agu 2026 nomor versi berikutnya dihitung di LIMA tempat, dan empat
    di antaranya tak menyaring versi yang sudah dibatalkan. Yang paling menggigit
    adalah muatan wizard: ia mengusulkan nomor tanpa saringan, layar
    mengirimkannya balik sebagai nomor MANUAL, dan penomoran otomatis yang sudah
    benar tak pernah terpakai.

    Akibatnya versi baru pada dokumen yang v01-nya dibatalkan lahir sebagai v02 —
    dan nomor resmi dokumen melompat dari …/00 ke …/02, menyebut "Revisi 01" yang
    tak pernah diterbitkan kepada siapa pun.

    Kelima pintu sudah disatukan ke SopNomorVersi. Change ini membereskan baris
    yang terlanjur lahir SEBELUM penyatuan itu — supaya sisa bug tak terbaca
    sebagai perilaku yang disengaja oleh siapa pun yang membacanya nanti.

    RUANG LINGKUP — DICARI LEWAT POLA, BUKAN ID YANG DIPATOK
    Versi yang memenuhi SEMUANYA:
      - Flag_Aktif='Y' dan Status='PUBLISHED'
      - ada nomor LEBIH RENDAH yang bebas karena versi pemiliknya dibatalkan,
        dan nomor itu tak dipakai versi hidup mana pun
      - NOL rujukan hilir: tak ada progres karyawan, tak ditunjuk paket item,
        tak ada penjadwalan integrasi, tak ada coverage rollup, tak ada
        paket versi item

    Syarat terakhir itulah pengamannya, dan ia mutlak. Versi yang sudah dibaca
    seseorang TIDAK BOLEH dinomori ulang: nomor revisi yang sudah beredar adalah
    bagian dari catatan kepatuhan, dan mengubahnya membuat bukti bacaan menunjuk
    revisi yang namanya sudah lain.

    BARIS TERDUGA (dev, 30 Agu 2026):
      N_SOP_Item_Versi   1 UPDATE   (versi 12417, dok 17054, v02 -> v01)

    LANGKAH LANJUTAN YANG TAK BISA DILAKUKAN SQL
    Nomor_SOP_Resmi WAJIB dirakit ulang sesudahnya lewat
    SopDokumenService::segarkanNomorResmi(). Rumus tujuh segmennya tinggal di
    SopNomorDokumen, dan menuliskannya sebagai SQL di sini akan menjadi
    implementasi kedua — persis yang rancangannya ada untuk dihindari. Lihat
    README.
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

DECLARE @sasaran TABLE (
    Id_SOP_Item_Versi INT PRIMARY KEY,
    Id_SOP_Dokumen    INT,
    Dari              INT,
    Ke                INT
);

INSERT INTO @sasaran (Id_SOP_Item_Versi, Id_SOP_Dokumen, Dari, Ke)
SELECT v.Id_SOP_Item_Versi, v.Id_SOP_Dokumen, v.Versi_Ke, bebas.Nomor_Bebas
FROM N_SOP_Item_Versi v
CROSS APPLY (
    /* Nomor bebas TERENDAH di bawah nomornya sekarang: yang dilepas sebuah
       pembatalan dan tak diambil versi hidup mana pun. */
    SELECT TOP 1 mati.Versi_Ke AS Nomor_Bebas
    FROM N_SOP_Item_Versi mati
    WHERE mati.Id_SOP_Dokumen = v.Id_SOP_Dokumen
      AND mati.Flag_Aktif = 'T'
      AND mati.Versi_Ke < v.Versi_Ke
      AND NOT EXISTS (
          SELECT 1 FROM N_SOP_Item_Versi hidup
           WHERE hidup.Id_SOP_Dokumen = v.Id_SOP_Dokumen
             AND hidup.Flag_Aktif = 'Y'
             AND hidup.Versi_Ke = mati.Versi_Ke)
    ORDER BY mati.Versi_Ke
) bebas
WHERE v.Id_SOP_Dokumen IS NOT NULL
  AND v.Flag_Aktif = 'Y'
  AND v.Status = 'PUBLISHED'
  /* NOL RUJUKAN HILIR — pengaman yang mutlak. */
  AND NOT EXISTS (SELECT 1 FROM N_SOP_Progress g              WHERE g.Id_SOP_Item_Versi   = v.Id_SOP_Item_Versi)
  AND NOT EXISTS (SELECT 1 FROM N_SOP_Paket_Item p            WHERE p.Id_Item_Versi_Aktif = v.Id_SOP_Item_Versi)
  AND NOT EXISTS (SELECT 1 FROM N_SOP_Penjadwalan_Integrasi i  WHERE i.Id_SOP_Item_Versi   = v.Id_SOP_Item_Versi)
  AND NOT EXISTS (SELECT 1 FROM N_SOP_Coverage_Rollup c        WHERE c.Id_SOP_Item_Versi   = v.Id_SOP_Item_Versi)
  AND NOT EXISTS (SELECT 1 FROM N_SOP_Paket_Versi_Item x       WHERE x.Id_SOP_Item_Versi   = v.Id_SOP_Item_Versi);

/* Diperlihatkan SEBELUM apa pun berubah — kesempatan terakhir membatalkan. */
SELECT s.*, d.Judul, d.Nomor_SOP_Resmi AS Nomor_Sebelum
  FROM @sasaran s
  LEFT JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = s.Id_SOP_Dokumen;

/* Penjaga: nomor tujuan tak boleh bentrok dengan versi hidup mana pun.
   Indeks unik terfilter juga akan menolaknya, tetapi galat 2601 tak menyebut
   baris mana — dan gagal yang tak menjelaskan diri sendiri adalah gagal yang
   dikerjakan ulang dengan menebak. */
IF EXISTS (
    SELECT 1 FROM @sasaran s
    JOIN N_SOP_Item_Versi bentrok
      ON bentrok.Id_SOP_Dokumen = s.Id_SOP_Dokumen
     AND bentrok.Flag_Aktif = 'Y'
     AND bentrok.Versi_Ke = s.Ke
     AND bentrok.Id_SOP_Item_Versi <> s.Id_SOP_Item_Versi
)
BEGIN
    ROLLBACK TRAN;
    THROW 50063, 'Nomor tujuan sudah dipakai versi hidup lain. Tak ada yang diubah.', 1;
END

UPDATE v
   SET v.Versi_Ke   = s.Ke,
       v.Updated_At = SYSDATETIME()
FROM N_SOP_Item_Versi v
JOIN @sasaran s ON s.Id_SOP_Item_Versi = v.Id_SOP_Item_Versi;

/* ── Verifikasi ─────────────────────────────────────────────────────────── */
SELECT v.Id_SOP_Item_Versi, v.Id_SOP_Dokumen, v.Versi_Ke, v.Status, v.Flag_Aktif
  FROM N_SOP_Item_Versi v
 WHERE v.Id_SOP_Dokumen IN (SELECT Id_SOP_Dokumen FROM @sasaran)
 ORDER BY v.Id_SOP_Dokumen, v.Versi_Ke, v.Id_SOP_Item_Versi;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-063-rapikan-lompatan-nomor-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-063-rapikan-lompatan-nomor-versi', 'DML', 'Rapikan lompatan nomor versi peninggalan bug penomoran',
 'Versi PUBLISHED tanpa rujukan hilir dinomori ulang ke nomor lebih rendah yang dibebaskan pembatalan, supaya nomor resmi tak melompati revisi yang tak pernah terbit. Nomor_SOP_Resmi dirakit ulang lewat segarkanNomorResmi().',
 'database/dml/DML-2026-063-rapikan-lompatan-nomor-versi/data-change.sql',
 'docs/changes/DML-2026-063-rapikan-lompatan-nomor-versi/README.md',
 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'rapikan-lompatan');

COMMIT TRAN;
