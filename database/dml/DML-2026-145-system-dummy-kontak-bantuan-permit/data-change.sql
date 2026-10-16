/* Change ID: DML-2026-145-system-dummy-kontak-bantuan-permit */
/*
    Data DUMMY kontak bantuan VISITOR/PERMIT untuk melihat kartu "Butuh bantuan
    soal permit?" di halaman pengajuan & detail permit.

    Target: Web_HRIS DEVELOPMENT saja. Nomor dan email fiktif; JANGAN dijalankan
    di production — kontak sungguhan diisi admin lewat Master Data Permit.
    Semua baris bertanda Created_By = 'DUMMY-DML-2026-145'. Idempoten per nama.

    Studi kasus:
      1. Kontak utama lengkap (HP + email + jam layanan), urutan 1
      2. Kontak hanya email (tanpa tombol WhatsApp), urutan 2
      3. Kontak hanya HP, urutan 3
      4. Kontak NONAKTIF — tidak boleh tampil
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @oleh varchar(100) = 'DUMMY-DML-2026-145';
DECLARE @k TABLE (Nama nvarchar(100), Ket nvarchar(150), HP varchar(20), Email varchar(150), Jam nvarchar(100), Urut int, Aktif char(1));
INSERT INTO @k VALUES
    (N'[DUMMY] Admin Permit SHE',  N'Verifikasi & inspeksi kendaraan', '0812-0000-0001', 'permit.she@contoh.id', N'Senin–Jumat 08.00–16.00', 1, 'Y'),
    (N'[DUMMY] Helpdesk GA',        N'Kartu permit & akses gerbang',     NULL,             'helpdesk.ga@contoh.id', N'Senin–Sabtu 08.00–17.00', 2, 'Y'),
    (N'[DUMMY] Pos Keamanan Utama', N'Darurat di gerbang (24 jam)',      '0812-0000-0003', NULL,                    N'Setiap hari 24 jam',      3, 'Y'),
    (N'[DUMMY] Kontak Lama',        N'Sudah tidak melayani',             '0812-0000-0004', 'lama@contoh.id',        NULL,                       9, 'T');

INSERT INTO N_HRIS_Master_Kontak_Bantuan
    (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
SELECT 'VISITOR', 'PERMIT', k.Nama, k.Ket, k.HP, k.Email, k.Jam, k.Urut, k.Aktif, @oleh
  FROM @k k
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan x
                    WHERE x.Kode_Grup_Fitur = 'VISITOR' AND x.Kode_Fitur = 'PERMIT' AND x.Nama_Kontak = k.Nama);

COMMIT TRANSACTION;

/* Verifikasi: 4 baris, 3 aktif. */
/*
SELECT Nama_Kontak, No_HP, Email, Urutan_Tampil, Flag_Aktif FROM N_HRIS_Master_Kontak_Bantuan
 WHERE Created_By = 'DUMMY-DML-2026-145' ORDER BY Urutan_Tampil;
*/

/* Pembersihan (manual, bila perlu). */
/*
DELETE FROM N_HRIS_Master_Kontak_Bantuan WHERE Created_By = 'DUMMY-DML-2026-145';
*/
