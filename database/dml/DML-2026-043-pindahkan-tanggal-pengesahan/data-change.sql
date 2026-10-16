/* Change ID: DML-2026-043-pindahkan-tanggal-pengesahan */

/* TANGGAL PENGESAHAN DIGANTIKAN TANGGAL AKHIR APPROVAL.

   Keputusan 2026-08-25: Nomor Pengesahan dan Tanggal Pengesahan dibuang dari
   formulir versi. Tanggal Akhir Approval sudah menjawab pertanyaan yang sama —
   kapan pengesahannya tuntas — dan ia tercap sendiri saat tahap bergeser,
   sehingga tak perlu diketik ulang.

   TAPI DATANYA DIPINDAHKAN DULU, BUKAN DITINGGALKAN.
   Empat belas versi menyimpan Tanggal_Pengesahan sementara hanya satu versi
   punya Tanggal_Akhir_Approval. Membuang field-nya dari layar tanpa memindahkan
   isinya akan membuat empat belas tanggal pengesahan nyata berhenti terlihat di
   mana pun, padahal itulah tanggal yang tercetak di dokumen fisiknya.

   Nol baris punya KEDUANYA dengan nilai berbeda, jadi pemindahan ini tak
   menimpa apa pun. */

UPDATE N_SOP_Item_Versi
SET Tanggal_Akhir_Approval = Tanggal_Pengesahan,
    Updated_At = SYSDATETIME()
WHERE Tanggal_Pengesahan IS NOT NULL
  AND Tanggal_Akhir_Approval IS NULL;

/* KOLOMNYA SENGAJA TIDAK DI-DROP.

   Nomor_Pengesahan masih terisi pada sembilan belas versi dan dibaca ekspor
   kepatuhan, monitoring, serta register kontrol dokumen. Nomor SK yang tercetak
   di dokumen fisik tak punya pengganti di kolom mana pun — membuangnya berarti
   menghapus satu-satunya jejak nomor itu di sistem.

   Yang dilakukan pekerjaan ini hanyalah BERHENTI MEMINTANYA di formulir versi.
   Yang sudah tercatat tetap tersimpan dan tetap terbaca laporan; yang baru tak
   lagi diisi. Bila kelak terbukti benar-benar tak dipakai, penghapusan kolomnya
   adalah perubahan tersendiri dengan pertimbangannya sendiri. */

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-043-pindahkan-tanggal-pengesahan')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-043-pindahkan-tanggal-pengesahan', 'DML', 'Pindahkan tanggal pengesahan ke tanggal akhir approval', 'Empat belas tanggal pengesahan dipindahkan sebelum field-nya dibuang dari formulir. Kolomnya tidak di-drop.', 'database/dml/DML-2026-043-pindahkan-tanggal-pengesahan/data-change.sql', 'docs/changes/DML-2026-043-pindahkan-tanggal-pengesahan/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'sederhanakan-pengesahan');
