/*  DML-2026-075 — Nomor HP pengumpulan data disatukan ke bentuk '62…'
    ------------------------------------------------------------------------
    Target : Web_HRIS (DEVELOPMENT)
    Status : DIJALANKAN di dev 4 Sep 2026 — 6 baris diperbaiki
             (Submission no_hp 3, darurat_no_hp 3; Draft no_hp 2, darurat_no_hp 1).
             Verifikasi: A1=0  A2=0  B1=0  C1=3.

    MASALAHNYA
    Fitur Pengumpulan Data menyimpan nomor HP sebagai '08…', sendirian.
    Seluruh sistem yang lain memakai '62…':

      Karyawan.HP                                    958 dari 958 baris '62…'
      WhatsappNotificationMirrorService::normalizePhone()  mengubah apa pun
                                                     menjadi '62…' sebelum kirim

    Akibat paling nyata sebelum ini disadari: prefill harus MEMBENGKOKKAN data
    master supaya lolos aturan fiturnya sendiri, dan satu nomor yang sama
    tersimpan dalam dua bentuk berbeda di dua tabel. Deteksi HP kembar di
    AdminPengumpulanDataService membandingkan string apa adanya, sehingga
    pasangan lintas-bentuk ('08…' vs '62…') tidak pernah terdeteksi kembar.

    Aturannya sudah dibalik di kode (config/pengumpulan_data.php: pola '^08'
    menjadi '^62', bentuk simpan dideklarasikan sebagai 'prefix_simpan').
    Change ini membereskan baris yang terlanjur lahir SEBELUM perbaikan itu.

    YANG TIDAK DISENTUH
    Snapshot_Lama_Json. Itu rekaman mentah sistem induk pada detik pengiriman;
    menormalkannya berarti membuat rekaman yang lebih rapi daripada
    kenyataannya. Ketiga baris yang ada memang sudah '62…' karena memang
    disalin apa adanya dari Karyawan.HP — justru itu buktinya.

    RUANG LINGKUP — dicari lewat POLA, bukan id yang dipatok
      nilai JSON no_hp / darurat_no_hp yang diawali '08'
      pada Data_Json (Submission) dan Draft_Json (Status)

    BARIS TERDUGA (dev, 4 Sep 2026)
      N_HRIS_Pengumpulan_Data_Submission  3 UPDATE  (Id 263 H45, 300 Y62, 339 E14)
      N_HRIS_Pengumpulan_Data_Status      3 UPDATE  (A4, C9, K32)

      F19 tidak ikut: draf-nya sudah '62…'.

      Catatan A4: nomornya '082839283' — hanya 8 angka yang diketik, di bawah
      minimum 9. Ia tidak sah sebelum maupun sesudah change ini. Bentuknya
      tetap diseragamkan; kekurangan angkanya memang urusan orangnya, dan
      wizard akan menahannya di langkah itu. Draf memang isian separuh jadi.
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

/* ── Sebelum ────────────────────────────────────────────────────────────── */
SELECT 'Submission' AS Tabel, Id, Kode_Karyawan,
       JSON_VALUE(Data_Json, '$.no_hp')         AS no_hp,
       JSON_VALUE(Data_Json, '$.darurat_no_hp') AS darurat_no_hp
  FROM N_HRIS_Pengumpulan_Data_Submission
 WHERE JSON_VALUE(Data_Json, '$.no_hp')         LIKE '08%'
    OR JSON_VALUE(Data_Json, '$.darurat_no_hp') LIKE '08%';

SELECT 'Draft' AS Tabel, Kode_Karyawan,
       JSON_VALUE(Draft_Json, '$.no_hp')         AS no_hp,
       JSON_VALUE(Draft_Json, '$.darurat_no_hp') AS darurat_no_hp
  FROM N_HRIS_Pengumpulan_Data_Status
 WHERE JSON_VALUE(Draft_Json, '$.no_hp')         LIKE '08%'
    OR JSON_VALUE(Draft_Json, '$.darurat_no_hp') LIKE '08%';

/* ── Perbaikan ──────────────────────────────────────────────────────────────
   '0' di depan diganti '62'. Angkanya sendiri tidak disentuh, jadi nomor yang
   tersimpan tetap nomor yang sama — hanya awalannya yang diseragamkan. Yang
   tampil di layar pun tidak berubah: angkaDiketikHp() membuang '62' maupun
   '0' di depan sebelum merender kotaknya.

   Predikat LIKE '08%' membuatnya idempoten: dijalankan dua kali, yang kedua
   tidak menemukan apa-apa.

   Draft_Updated_At sengaja TIDAK ikut digeser. Kolom itu merekam kapan
   ORANGNYA terakhir menyimpan draf, dan itu bukan yang terjadi di sini —
   menggesernya akan membuat draf lama tampak baru disentuh pemiliknya.
   ------------------------------------------------------------------------ */
UPDATE N_HRIS_Pengumpulan_Data_Submission
   SET Data_Json  = JSON_MODIFY(Data_Json, '$.no_hp',
                        '62' + SUBSTRING(JSON_VALUE(Data_Json, '$.no_hp'), 2, 20)),
       Updated_At = SYSDATETIME()
 WHERE JSON_VALUE(Data_Json, '$.no_hp') LIKE '08%';

PRINT CONCAT('Submission.no_hp diperbaiki: ', @@ROWCOUNT);

UPDATE N_HRIS_Pengumpulan_Data_Submission
   SET Data_Json  = JSON_MODIFY(Data_Json, '$.darurat_no_hp',
                        '62' + SUBSTRING(JSON_VALUE(Data_Json, '$.darurat_no_hp'), 2, 20)),
       Updated_At = SYSDATETIME()
 WHERE JSON_VALUE(Data_Json, '$.darurat_no_hp') LIKE '08%';

PRINT CONCAT('Submission.darurat_no_hp diperbaiki: ', @@ROWCOUNT);

UPDATE N_HRIS_Pengumpulan_Data_Status
   SET Draft_Json = JSON_MODIFY(Draft_Json, '$.no_hp',
                        '62' + SUBSTRING(JSON_VALUE(Draft_Json, '$.no_hp'), 2, 20)),
       Updated_At = SYSDATETIME()
 WHERE JSON_VALUE(Draft_Json, '$.no_hp') LIKE '08%';

PRINT CONCAT('Draft.no_hp diperbaiki: ', @@ROWCOUNT);

UPDATE N_HRIS_Pengumpulan_Data_Status
   SET Draft_Json = JSON_MODIFY(Draft_Json, '$.darurat_no_hp',
                        '62' + SUBSTRING(JSON_VALUE(Draft_Json, '$.darurat_no_hp'), 2, 20)),
       Updated_At = SYSDATETIME()
 WHERE JSON_VALUE(Draft_Json, '$.darurat_no_hp') LIKE '08%';

PRINT CONCAT('Draft.darurat_no_hp diperbaiki: ', @@ROWCOUNT);

/* ── Verifikasi ─────────────────────────────────────────────────────────────
   A1/A2 harus 0: tidak boleh ada bentuk lama yang tersisa.
   B1 harus 0: setiap nomor TERKIRIM harus lolos pola server '^628[0-9]{8,12}$'.
               Draf sengaja TIDAK dituntut lolos — ia memang isian separuh jadi.
   C1 harus 3: snapshot mentah tidak boleh ikut berubah.
   ------------------------------------------------------------------------ */
SELECT COUNT(*) AS A1_Submission_Masih_08
  FROM N_HRIS_Pengumpulan_Data_Submission
 WHERE JSON_VALUE(Data_Json, '$.no_hp')         LIKE '08%'
    OR JSON_VALUE(Data_Json, '$.darurat_no_hp') LIKE '08%';

SELECT COUNT(*) AS A2_Draft_Masih_08
  FROM N_HRIS_Pengumpulan_Data_Status
 WHERE JSON_VALUE(Draft_Json, '$.no_hp')         LIKE '08%'
    OR JSON_VALUE(Draft_Json, '$.darurat_no_hp') LIKE '08%';

SELECT COUNT(*) AS B1_Terkirim_Tak_Lolos_Pola
  FROM N_HRIS_Pengumpulan_Data_Submission
 WHERE Flag_Aktif = 'Y'
   AND JSON_VALUE(Data_Json, '$.no_hp') IS NOT NULL
   AND (JSON_VALUE(Data_Json, '$.no_hp') NOT LIKE '628%'
        OR JSON_VALUE(Data_Json, '$.no_hp') LIKE '%[^0-9]%'
        OR LEN(JSON_VALUE(Data_Json, '$.no_hp')) NOT BETWEEN 11 AND 15);

SELECT COUNT(*) AS C1_Snapshot_Masih_62
  FROM N_HRIS_Pengumpulan_Data_Submission
 WHERE JSON_VALUE(Snapshot_Lama_Json, '$.no_hp') LIKE '62%';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-075-hp-pengumpulan-data-bentuk-62')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-075-hp-pengumpulan-data-bentuk-62', 'DML', 'Nomor HP pengumpulan data disatukan ke bentuk 62',
 'Fitur Pengumpulan Data menyimpan HP sebagai 08... sendirian, sementara Karyawan.HP (958/958) dan pengirim WhatsApp memakai 62.... Akibatnya prefill harus membengkokkan data master, dan deteksi HP kembar buta terhadap pasangan lintas-bentuk. Pola sudah dibalik di config; change ini membereskan baris yang terlanjur lahir. Snapshot_Lama_Json sengaja tidak disentuh.',
 'database/dml/DML-2026-075-hp-pengumpulan-data-bentuk-62/data-change.sql',
 'docs/changes/DML-2026-075-hp-pengumpulan-data-bentuk-62/README.md',
 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'hp-bentuk-62');

COMMIT TRAN;
