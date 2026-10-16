/*  DML-2026-061 — Bersihkan isi warisan pada versi yang tak pernah memilihnya
    ------------------------------------------------------------------------
    Target  : Web_HRIS (DEVELOPMENT)
    Status  : DIJALANKAN di development 30 Agu 2026 atas izin user.
              SQL Tracker & produksi masih PENDING.

    MASALAHNYA
    Sampai 30 Agu 2026 formulir versi baru di wizard mengisi dirinya sendiri
    dari versi sebelumnya: matriks pengesah, berkas, dan paket ujian. Maksudnya
    kenyamanan, tetapi isian itu ikut TERSIMPAN pada penyimpanan pertama — dan
    sejak saat itu tak ada lagi yang membedakannya dari pilihan sadar admin.

    Akibatnya dua, dan keduanya membuat sistem melaporkan hal yang tak terjadi:

      1. Riwayat versi menyebut "berkas terlampir" pada versi yang berkasnya
         belum pernah dipilih siapa pun.
      2. Gerbang REALISASI_PENGESAH (DML-2026-048) lolos sendiri — versi
         tercatat disahkan oleh orang-orang yang belum melihatnya.

    Pengisian otomatisnya sudah dicabut di kode (SopVersiWizard.vue dan
    SopAdminVersiWizardController.php). Change ini membereskan baris yang
    terlanjur lahir sebelum pencabutan itu.

    RUANG LINGKUPNYA SEMPIT DENGAN SENGAJA
    Hanya versi yang MEMENUHI SEMUANYA:
      - Versi_Ke > 0                    (bukan versi pertama; tak ada yang diwarisi)
      - Status = 'DRAFT'                (belum pernah terbit — tak ada karyawan
                                         yang sudah menerimanya)
      - Id_Master_Konten SAMA PERSIS dengan versi tepat sebelumnya

    Kesamaan konten dengan pendahulunya adalah sidik jari warisan itu. Versi
    yang admin-nya memang sengaja memilih ulang berkas yang sama tak bisa
    dibedakan dari sini — karena itu jaring ini dibatasi pada DRAFT saja, di
    mana biaya salah tebaknya cuma memilih ulang berkas, bukan mengubah arsip
    yang sudah mengikat orang.

    BARIS TERDUGA (dev, 30 Agu 2026):
      N_SOP_Item_Versi           2 UPDATE   (id 11183, 12140)
      N_SOP_Item_Versi_Pengesah  6 UPDATE   (3 baris per versi, dinonaktifkan)

    TIDAK MENGHAPUS APA PUN. Baris pengesah dinonaktifkan (Flag_Aktif = 'T'),
    bukan DELETE: jejaknya tetap bisa dibaca kalau ada yang mempertanyakan apa
    yang terjadi, dan pembacanya (nilaiSyarat, blokPengesah) sudah menyaring
    Flag_Aktif sejak awal.
    ------------------------------------------------------------------------
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRAN;

/* Sasaran dikunci SEKALI di awal, lalu dipakai kedua UPDATE di bawah.
   Menghitungnya dua kali membuka celah: UPDATE pertama mengubah kolom yang
   ikut menentukan sasaran UPDATE kedua, sehingga yang kedua menyasar himpunan
   yang berbeda dari yang dilaporkan. */
DECLARE @sasaran TABLE (Id_SOP_Item_Versi INT PRIMARY KEY);

INSERT INTO @sasaran (Id_SOP_Item_Versi)
SELECT v.Id_SOP_Item_Versi
FROM N_SOP_Item_Versi v
CROSS APPLY (
    SELECT TOP 1 p.Id_Master_Konten
    FROM N_SOP_Item_Versi p
    WHERE p.Id_SOP_Dokumen = v.Id_SOP_Dokumen
      AND p.Versi_Ke < v.Versi_Ke
    ORDER BY p.Versi_Ke DESC
) prev
WHERE v.Versi_Ke > 0
  AND v.Status = 'DRAFT'
  AND v.Id_Master_Konten IS NOT NULL
  AND prev.Id_Master_Konten IS NOT NULL
  AND v.Id_Master_Konten = prev.Id_Master_Konten;

/* Diperlihatkan SEBELUM apa pun berubah — inilah kesempatan terakhir
   membatalkan bila daftarnya tak seperti yang diharapkan. */
SELECT v.Id_SOP_Item_Versi, v.Id_SOP_Dokumen, d.Judul, v.Versi_Ke, v.Status,
       v.Id_Master_Konten, v.Disahkan_Oleh
FROM N_SOP_Item_Versi v
JOIN @sasaran s ON s.Id_SOP_Item_Versi = v.Id_SOP_Item_Versi
LEFT JOIN N_SOP_Dokumen d ON d.Id_SOP_Dokumen = v.Id_SOP_Dokumen
ORDER BY v.Id_SOP_Dokumen, v.Versi_Ke;

/* ── 1. Lepaskan berkas & cermin pengesah dari versinya ─────────────────── */
UPDATE v
   SET v.Id_Master_Konten        = NULL,
       v.Id_Master_Ujian         = NULL,
       v.Nama_Ujian              = NULL,
       v.Disahkan_Oleh           = NULL,
       v.Jabatan_Pengesah        = NULL,
       v.Kode_Karyawan_Pengesah  = NULL,
       v.Updated_At              = SYSDATETIME()
FROM N_SOP_Item_Versi v
JOIN @sasaran s ON s.Id_SOP_Item_Versi = v.Id_SOP_Item_Versi;

/* ── 2. Nonaktifkan baris blok pengesah yang ikut terbawa ───────────────── */
UPDATE p
   SET p.Flag_Aktif = 'T',
       p.Updated_At = SYSDATETIME()
FROM N_SOP_Item_Versi_Pengesah p
JOIN @sasaran s ON s.Id_SOP_Item_Versi = p.Id_SOP_Item_Versi
WHERE p.Flag_Aktif = 'Y';

/* ── Verifikasi ─────────────────────────────────────────────────────────── */
SELECT v.Id_SOP_Item_Versi, v.Versi_Ke, v.Status, v.Id_Master_Konten,
       v.Disahkan_Oleh,
       (SELECT COUNT(*) FROM N_SOP_Item_Versi_Pengesah x
         WHERE x.Id_SOP_Item_Versi = v.Id_SOP_Item_Versi AND x.Flag_Aktif = 'Y')
           AS Pengesah_Aktif
FROM N_SOP_Item_Versi v
JOIN @sasaran s ON s.Id_SOP_Item_Versi = v.Id_SOP_Item_Versi
ORDER BY v.Id_SOP_Dokumen, v.Versi_Ke;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-061-bersihkan-warisan-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-061-bersihkan-warisan-versi', 'DML', 'Bersihkan isi warisan pada versi draft',
 'Berkas, ujian, dan blok pengesah yang tersalin otomatis dari versi sebelumnya dilepas dari versi DRAFT yang tak pernah memilihnya. Pengisian otomatisnya sendiri sudah dicabut di kode.',
 'database/dml/DML-2026-061-bersihkan-warisan-versi/data-change.sql',
 'docs/changes/DML-2026-061-bersihkan-warisan-versi/README.md',
 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'warisan-versi');

COMMIT TRAN;
