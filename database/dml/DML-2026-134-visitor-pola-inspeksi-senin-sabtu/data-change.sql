/* Change ID: DML-2026-134-visitor-pola-inspeksi-senin-sabtu */
/*
    Pola jadwal inspeksi kendaraan untuk Senin sampai Sabtu, sesuai keputusan
    SHE 2026-09-22. Empat baris per hari, sama persis di keenam harinya:

      Shift 1 (07:00-15:00)  Area Parkir Motor/Mobil   10:00-11:00
      Shift 1 (07:00-15:00)  Klinik SHE                14:00-15:00
      Shift 2 (15:00-23:00)  Klinik SHE                19:00-20:00
      Shift 3 (23:00-07:00)  Klinik SHE                01:00-02:00

    Semuanya berdurasi 15 menit per sesi dan berkuota 1 kendaraan per sesi,
    sehingga tiap baris menghasilkan 4 sesi = 4 kendaraan. Total 16 kendaraan
    per hari, 96 per minggu. MINGGU sengaja kosong.

    Baris Shift 3 menyeberang tengah malam: 01:00-02:00 berada DI DALAM shift
    23:00-07:00 yang dimulai hari sebelumnya, dan slotnya dimiliki hari yang
    memulainya - bukan tanggal jamnya.

    Pos dan shift dicari lewat NAMANYA, bukan Id yang ditempel: Id identity
    berbeda antar lingkungan, dan menempelkannya membuat artefak ini menulis
    jadwal ke pos yang salah di production tanpa satu pun galat.

    Idempoten: baris yang sudah ada dengan kombinasi (pos, shift, hari, jam
    mulai) yang sama tidak digandakan.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @PosParkir int = (SELECT Id_Pos_Inspeksi FROM N_VISITOR_Master_Pos_Inspeksi WHERE Nama_Pos = 'Area Parkir Motor/Mobil' AND Flag_Aktif = 'Y');
DECLARE @PosKlinik int = (SELECT Id_Pos_Inspeksi FROM N_VISITOR_Master_Pos_Inspeksi WHERE Nama_Pos = 'Klinik SHE'              AND Flag_Aktif = 'Y');
DECLARE @Shift1    int = (SELECT Id_Shift_Inspeksi FROM N_VISITOR_Master_Shift_Inspeksi WHERE Nama_Shift = 'Shift 1' AND Flag_Aktif = 'Y');
DECLARE @Shift2    int = (SELECT Id_Shift_Inspeksi FROM N_VISITOR_Master_Shift_Inspeksi WHERE Nama_Shift = 'Shift 2' AND Flag_Aktif = 'Y');
DECLARE @Shift3    int = (SELECT Id_Shift_Inspeksi FROM N_VISITOR_Master_Shift_Inspeksi WHERE Nama_Shift = 'Shift 3' AND Flag_Aktif = 'Y');

IF @PosParkir IS NULL OR @PosKlinik IS NULL OR @Shift1 IS NULL OR @Shift2 IS NULL OR @Shift3 IS NULL
    THROW 50000, 'Pos atau shift yang dibutuhkan tidak ditemukan / tidak aktif; jadwal tidak ditulis.', 1;

/* Rencana: satu baris per (pos, shift, jam) x hari 1..6 */
DECLARE @Rencana TABLE (Id_Pos int, Id_Shift int, Jam_Mulai time(0), Jam_Selesai time(0), Durasi int, Kuota int);

INSERT INTO @Rencana (Id_Pos, Id_Shift, Jam_Mulai, Jam_Selesai, Durasi, Kuota)
VALUES (@PosParkir, @Shift1, '10:00', '11:00', 15, 1),
       (@PosKlinik, @Shift1, '14:00', '15:00', 15, 1),
       (@PosKlinik, @Shift2, '19:00', '20:00', 15, 1),
       (@PosKlinik, @Shift3, '01:00', '02:00', 15, 1);

DECLARE @Hari TABLE (H int);
INSERT INTO @Hari (H) VALUES (1), (2), (3), (4), (5), (6);   /* Senin..Sabtu; Minggu kosong */

INSERT INTO N_VISITOR_Inspeksi_Pola
    (Id_Pos_Inspeksi, Id_Shift_Inspeksi, Hari_Minggu, Jam_Mulai, Jam_Selesai, Durasi_Menit, Kuota_Per_Slot, Flag_Aktif, Created_By)
SELECT r.Id_Pos, r.Id_Shift, h.H, r.Jam_Mulai, r.Jam_Selesai, r.Durasi, r.Kuota, 'Y', 'DML-2026-134'
  FROM @Rencana r
 CROSS JOIN @Hari h
 WHERE NOT EXISTS (
        SELECT 1 FROM N_VISITOR_Inspeksi_Pola p
         WHERE p.Flag_Aktif = 'Y'
           AND p.Id_Pos_Inspeksi   = r.Id_Pos
           AND p.Id_Shift_Inspeksi = r.Id_Shift
           AND p.Hari_Minggu       = h.H
           AND p.Jam_Mulai         = r.Jam_Mulai);

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-134-visitor-pola-inspeksi-senin-sabtu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-134-visitor-pola-inspeksi-senin-sabtu', 'DML', 'Pola jadwal inspeksi Senin-Sabtu', 'Empat baris pola per hari (Area Parkir 10-11, Klinik SHE 14-15, 19-20, 01-02) untuk Senin sampai Sabtu; 16 kendaraan per hari. Minggu sengaja kosong.', 'database/dml/DML-2026-134-visitor-pola-inspeksi-senin-sabtu/data-change.sql', 'docs/changes/DML-2026-134-visitor-pola-inspeksi-senin-sabtu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'visitor-pola-senin-sabtu');
END;

COMMIT TRANSACTION;

/* Verifikasi: harus 24 baris (4 x 6 hari). */
/*
SELECT Hari_Minggu, COUNT(*) AS baris
  FROM N_VISITOR_Inspeksi_Pola
 WHERE Flag_Aktif = 'Y'
 GROUP BY Hari_Minggu
 ORDER BY Hari_Minggu;
*/
