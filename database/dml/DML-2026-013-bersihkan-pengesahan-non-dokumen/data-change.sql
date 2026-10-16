/*
 * DML-2026-013 — Bersihkan data pengesahan pada item SOP non-dokumen.
 *
 * KENAPA.
 * Pengesahan hanya berlaku untuk tipe DOKUMEN (profil tipe, SopTipeItem::PROFIL).
 * Data pada FORM/VIDEO/KUIS terkumpul karena layar menyodorkan fieldnya, bukan
 * karena dibutuhkan. Terukur 20 Agustus 2026: 47% versi FORM punya
 * Nomor_Pengesahan, sementara 0 dari 17 item non-dokumen pernah punya
 * Nomor_SOP_Resmi. Bedanya bukan kebutuhan — yang satu diminta layar, yang lain
 * tidak.
 *
 * CAKUPAN (diukur sebelum eksekusi).
 *   10 versi non-dokumen dengan minimal satu kolom pengesahan terisi
 *   17 baris N_SOP_Item_Versi_Pengesah pada 7 versi
 *   1 di antara 7 versi itu berstatus PUBLISHED
 *
 * VERSI PUBLISHED IKUT DIBERSIHKAN. Itu keputusan sadar pemilik proyek, bukan
 * efek samping: pembersihan diminta menyeluruh agar tidak ambigu saat dibaca,
 * dan lingkungannya pengembangan.
 *
 * PRASYARAT — JANGAN JALANKAN SEBELUM INI TERPENUHI.
 * Penjaga terbit sudah sadar tipe (SopDokumenService::pastikanAdaPengesah,
 * commit 9a436ef1). Tanpa itu, tujuh versi yang kehilangan blok tanda tangan
 * tidak bisa diterbitkan lagi — penjaga menuntut sesuatu yang profil tipenya
 * sendiri nyatakan tidak berlaku.
 *
 * TIDAK DISENTUH.
 * Tanggal_Berlaku. Kapan sebuah versi mulai berlaku bermakna untuk form, video,
 * dan kuis juga; ia bukan bagian dari pengesahan formal.
 */

BEGIN TRANSACTION;

/* Blok tanda tangan (Disiapkan/Diperiksa/Disetujui/Disahkan). */
DELETE p
FROM N_SOP_Item_Versi_Pengesah p
INNER JOIN N_SOP_Item_Versi v ON v.Id_SOP_Item_Versi = p.Id_SOP_Item_Versi
INNER JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = v.Id_SOP_Dokumen
WHERE d.Tipe <> 'DOKUMEN';

/* Kolom cermin pengesahan di baris versi. */
UPDATE v
SET v.Nomor_Pengesahan = NULL,
    v.Disahkan_Oleh = NULL,
    v.Jabatan_Pengesah = NULL,
    v.Tanggal_Pengesahan = NULL,
    v.Kode_Karyawan_Pengesah = NULL
FROM N_SOP_Item_Versi v
INNER JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = v.Id_SOP_Dokumen
WHERE d.Tipe <> 'DOKUMEN';

COMMIT;
