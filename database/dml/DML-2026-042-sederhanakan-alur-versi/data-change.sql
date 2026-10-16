/* Change ID: DML-2026-042-sederhanakan-alur-versi */

/* ALUR EMPAT LANGKAH, SATU TAHAP PER LANGKAH.

   Keputusan 2026-08-25 setelah alurnya dipakai: yang terpasang terlalu berbelit
   dibanding kenyataan kerjanya.

   1 Pengajuan    admin mengisi identitas versi, lalu lanjut
   2 Review       catatan review bila ada; kalau mulus, langsung lanjut
   3 Pengesahan   isi pengesah dan legalitasnya, lalu lanjut
   4 Pemberkasan  di sinilah ditentukan: TERBIT atau BATAL jadi arsip

   DUA HAL YANG DIBUANG.

   (a) APPROVED sebagai tahap kedua di langkah 3. Langkah Pengesahan diwakili
       dua tahap — Approval lalu Approved — sehingga admin menekan "Simpan &
       Lanjut" dua kali untuk satu langkah. Pemisahan itu tak menjawab
       pertanyaan siapa pun: yang dicatat sistem adalah siapa menandatangani
       dan kapan, bukan "sedang disetujui" versus "sudah disetujui".

   (b) HOLD sepenuhnya. Menahan pekerjaan tak butuh status: admin yang belum
       siap melanjutkan cukup TIDAK menekan lanjut, dan versinya memang tinggal
       di tahap itu. Status yang tak mengubah apa pun selain namanya sendiri
       hanya menambah satu keputusan yang harus dipikirkan.

   DINONAKTIFKAN, BUKAN DIHAPUS. Flag_Aktif = 'T' menyimpan artinya bagi baris
   lama yang mungkin pernah menyinggungnya, dan mengembalikannya cukup satu
   UPDATE bila ternyata dibutuhkan. Menghapus barisnya akan memutus rujukan
   yang tak bisa dipulihkan. */

/* ── (1) Nonaktifkan tahap yang tak dipakai lagi ─────────────────────── */
UPDATE N_SOP_Master_Tahap_Versi
SET Flag_Aktif = 'T', Updated_At = SYSDATETIME()
WHERE Kode_Tahap IN ('HOLD', 'APPROVED');

/* ── (2) Nonaktifkan seluruh transisi yang menyentuh keduanya ────────── */
UPDATE x
SET x.Flag_Aktif = 'T'
FROM N_SOP_Master_Tahap_Versi_Transisi x
JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = x.Dari_Tahap_Id
JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = x.Ke_Tahap_Id
WHERE d.Kode_Tahap IN ('HOLD', 'APPROVED')
   OR k.Kode_Tahap IN ('HOLD', 'APPROVED');

/* ── (3) APPROVAL kini mewakili seluruh langkah Pengesahan ───────────── */
/* Butuh_Pengesahan sengaja 'T': pengesah diisi DI DALAM langkah ini, bukan
   sebelum memasukinya. Menuntutnya sebagai syarat masuk membuat langkah itu
   mustahil dicapai — admin diminta melengkapi sesuatu yang formulirnya baru
   terbuka setelah ia masuk. Yang menuntut pengesah adalah gerbang TERBIT. */
UPDATE N_SOP_Master_Tahap_Versi
SET Nama_Tahap = 'Pengesahan',
    Deskripsi = 'Pengesah dan legalitasnya dicatat di sini.',
    Butuh_Berkas = 'T',
    Butuh_Pengesahan = 'T',
    Cap_Tanggal = 'Tanggal_Akhir_Approval',
    Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'APPROVAL';

/* Syarat pindah KE Pengesahan tetap catatan review; syarat KE Pemberkasan
   yang menuntut pengesah. Baris syarat APPROVED dipindahkan ke APPROVAL
   supaya "tak bisa maju tanpa pengesah" tetap ditegakkan. */
DELETE FROM N_SOP_Master_Tahap_Versi_Syarat
WHERE Tahap_Id IN (SELECT Id_Tahap FROM N_SOP_Master_Tahap_Versi WHERE Kode_Tahap = 'APPROVED');

/* ── (4) Sambungkan Pengesahan langsung ke Pemberkasan ───────────────── */
INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif)
SELECT d.Id_Tahap, k.Id_Tahap, 'Y'
FROM N_SOP_Master_Tahap_Versi d
CROSS JOIN N_SOP_Master_Tahap_Versi k
WHERE d.Kode_Tahap = 'APPROVAL' AND k.Kode_Tahap = 'FILING_REGISTERED'
  AND NOT EXISTS (
      SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi x
      WHERE x.Dari_Tahap_Id = d.Id_Tahap AND x.Ke_Tahap_Id = k.Id_Tahap
  );

/* Bila transisinya sudah ada tapi nonaktif, hidupkan lagi. */
UPDATE x SET x.Flag_Aktif = 'Y'
FROM N_SOP_Master_Tahap_Versi_Transisi x
JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = x.Dari_Tahap_Id
JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = x.Ke_Tahap_Id
WHERE d.Kode_Tahap = 'APPROVAL' AND k.Kode_Tahap = 'FILING_REGISTERED';

/* ── (5) Pembatalan hanya dari Pemberkasan ───────────────────────────── */
/* "Di tahap pemberkasan user menentukan versi ini diterbitkan atau dibatalkan,
   sehingga dokumen yang gagal jadi arsip." Membatalkan dari langkah lain
   menawarkan keputusan besar sebelum ada yang bisa ditimbang. */
UPDATE x SET x.Flag_Aktif = 'T'
FROM N_SOP_Master_Tahap_Versi_Transisi x
JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = x.Dari_Tahap_Id
JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = x.Ke_Tahap_Id
WHERE k.Kode_Tahap = 'CANCEL' AND d.Kode_Tahap <> 'APPROVAL';

INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif)
SELECT d.Id_Tahap, k.Id_Tahap, 'Y'
FROM N_SOP_Master_Tahap_Versi d
CROSS JOIN N_SOP_Master_Tahap_Versi k
WHERE d.Kode_Tahap = 'APPROVAL' AND k.Kode_Tahap = 'CANCEL'
  AND NOT EXISTS (
      SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi x
      WHERE x.Dari_Tahap_Id = d.Id_Tahap AND x.Ke_Tahap_Id = k.Id_Tahap
  );

UPDATE x SET x.Flag_Aktif = 'Y'
FROM N_SOP_Master_Tahap_Versi_Transisi x
JOIN N_SOP_Master_Tahap_Versi d ON d.Id_Tahap = x.Dari_Tahap_Id
JOIN N_SOP_Master_Tahap_Versi k ON k.Id_Tahap = x.Ke_Tahap_Id
WHERE d.Kode_Tahap = 'APPROVAL' AND k.Kode_Tahap = 'CANCEL';

/* ── (6) Catatan review TIDAK lagi menjadi syarat maju ───────────────── */
/* "Kalau ada catatan user boleh isi, kalau tidak ada dan mulus user bisa
   langsung simpan dan lanjut ke pengesahan." Review yang berjalan mulus memang
   tak meninggalkan catatan, dan memaksa mengarang satu putaran kosong hanya
   demi melewati gerbang membuat catatannya berhenti berarti.

   Yang tetap dijaga adalah gerbang TERBIT: berkas final dan pengesah nyata.
   Di sanalah dokumen mulai mengikat orang. */
DELETE FROM N_SOP_Master_Tahap_Versi_Syarat WHERE Kode_Syarat = 'CATATAN_REVIEW';

/* ── (7) Nama langkah 4 mengikuti kata yang dipakai sehari-hari ──────── */
UPDATE N_SOP_Master_Tahap_Versi
SET Kelompok = 'TERBIT', Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'FILING_REGISTERED';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-042-sederhanakan-alur-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-042-sederhanakan-alur-versi', 'DML', 'Alur versi empat langkah, satu tahap per langkah', 'HOLD dan APPROVED dinonaktifkan; Pengesahan jadi satu tahap; pembatalan hanya dari Pemberkasan.', 'database/dml/DML-2026-042-sederhanakan-alur-versi/data-change.sql', 'docs/changes/DML-2026-042-sederhanakan-alur-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'alur-sederhana');
