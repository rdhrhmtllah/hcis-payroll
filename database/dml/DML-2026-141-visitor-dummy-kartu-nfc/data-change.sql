/* Change ID: DML-2026-141-visitor-dummy-kartu-nfc */
/*
    Data DUMMY kartu NFC dan pairing-nya untuk menguji pengecekan kartu saat
    pengajuan permit (branch feat/vms/cek-kartu-nfc-permit).

    Target: Web_HRIS DEVELOPMENT saja. JANGAN dijalankan di production: seluruh
    kartu di sini fiktif. Semua baris bertanda created_by = 'DUMMY-DML-2026-141'.

    Kartu memakai UID 4 byte (Mifare Classic): nfc_hex 8 digit heksa huruf
    besar tanpa pemisah, nfc_inbio = nilai desimal UID yang sama.

    Nama & NIK binding karyawan disalin dari tabel Karyawan saat dijalankan.
    Catatan dev: seluruh karyawan aktif di dev ber-NIK sama (EMI2025120001),
    jadi pencocokan pemohon wajib memakai kode_karyawan, bukan NIK.

    Idempoten: kartu dilewati bila nfc_hex-nya sudah ada; binding dilewati bila
    pasangan (kartu, pemegang) yang sama sudah ada.
*/
SET XACT_ABORT ON;
BEGIN TRANSACTION;

DECLARE @oleh varchar(50) = 'DUMMY-DML-2026-141';

/* ---- 1. Master kartu -------------------------------------------------- */
DECLARE @kartu TABLE (nfc_hex varchar(30), nfc_inbio bigint, validasi char(1), aktif char(1), dibuat datetime);
INSERT INTO @kartu VALUES
    ('04A1B2C3', 77705923, 'Y', 'Y', '2026-09-01T08:10:00'),  -- K01 H45 lolos
    ('04A1B2C4', 77705924, 'T', 'Y', '2026-09-26T09:00:00'),  -- K02 Y62 belum divalidasi sistem
    ('04A1B2C5', 77705925, 'Y', 'T', '2026-08-04T10:00:00'),  -- K03 K32 kartu lama (hilang, dinonaktifkan)
    ('04A1B2C6', 77705926, 'Y', 'Y', '2026-09-15T13:30:00'),  -- K04 K32 kartu pengganti
    ('04A1B2C7', 77705927, 'Y', 'Y', '2026-08-20T08:00:00'),  -- K05 E14 pairing dicabut
    ('04A1B2C8', 77705928, 'Y', 'T', '2026-08-21T08:00:00'),  -- K06 C9 kartu diblokir
    ('04A1B2C9', 77705929, 'Y', 'Y', '2026-09-10T11:00:00'),  -- K07 dipakai J31 & F19 (konflik)
    ('04A1B2CA', 77705930, 'Y', 'Y', '2025-06-02T08:00:00'),  -- K08 W30 sudah resign, kartu belum ditarik
    ('04A1B2CB', 77705931, 'Y', 'Y', '2026-09-18T07:45:00'),  -- K09 mitra aktif
    ('04A1B2CC', 77705932, 'Y', 'Y', '2026-07-01T07:45:00'),  -- K10 mitra kontrak selesai
    ('04A1B2CD', 77705933, 'Y', 'Y', '2026-09-27T15:00:00'),  -- K11 stok siap pairing
    ('04A1B2CE', 77705934, 'T', 'Y', '2026-09-27T15:05:00'),  -- K12 stok belum divalidasi
    ('04A1B2CF', 77705935, 'Y', 'Y', '2026-09-05T09:20:00');  -- K13 B6 lolos

INSERT INTO N_EMI_Master_NFC (nfc_hex, nfc_inbio, flag_validasi_sistem, flag_aktif, created_by, created_at)
SELECT k.nfc_hex, k.nfc_inbio, k.validasi, k.aktif, @oleh, k.dibuat
  FROM @kartu k
 WHERE NOT EXISTS (SELECT 1 FROM N_EMI_Master_NFC m WHERE m.nfc_hex = k.nfc_hex);

/* ---- 2. Pairing karyawan (nama & NIK dari Karyawan) --------------------- */
DECLARE @pairing TABLE (nfc_hex varchar(30), kode_karyawan varchar(50), aktif char(1), dibuat datetime);
INSERT INTO @pairing VALUES
    ('04A1B2C3', 'H45', 'Y', '2026-09-01T08:15:00'),
    ('04A1B2C4', 'Y62', 'Y', '2026-09-26T09:05:00'),
    ('04A1B2C5', 'K32', 'T', '2026-08-04T10:05:00'),
    ('04A1B2C6', 'K32', 'Y', '2026-09-15T13:35:00'),
    ('04A1B2C7', 'E14', 'T', '2026-08-20T08:05:00'),
    ('04A1B2C8', 'C9',  'Y', '2026-08-21T08:05:00'),
    ('04A1B2C9', 'J31', 'Y', '2026-09-10T11:05:00'),
    ('04A1B2C9', 'F19', 'Y', '2026-09-12T14:00:00'),
    ('04A1B2CA', 'W30', 'Y', '2025-06-02T08:05:00'),
    ('04A1B2CF', 'B6',  'Y', '2026-09-05T09:25:00');

INSERT INTO N_EMI_Binding_NFC (id_nfc, nik, kode_karyawan, nama, no_mitra, flag_aktif, created_by, created_at)
SELECT m.id, k.No_Induk_Karyawan, k.Kode_Karyawan, k.Nama, NULL, p.aktif, @oleh, p.dibuat
  FROM @pairing p
  JOIN N_EMI_Master_NFC m ON m.nfc_hex = p.nfc_hex
  JOIN Karyawan k ON k.Kode_Karyawan = p.kode_karyawan
 WHERE NOT EXISTS (SELECT 1 FROM N_EMI_Binding_NFC b WHERE b.id_nfc = m.id AND b.kode_karyawan = p.kode_karyawan);

/* ---- 3. Pairing mitra (tanpa kode karyawan) ----------------------------- */
DECLARE @mitra TABLE (nfc_hex varchar(30), no_mitra varchar(30), nik varchar(30), nama varchar(100), aktif char(1), dibuat datetime);
INSERT INTO @mitra VALUES
    ('04A1B2CB', 'MTR-2026-0001', '1671010101900001', '[DUMMY] Budi Santoso (PT Contoh Logistik)', 'Y', '2026-09-18T07:50:00'),
    ('04A1B2CC', 'MTR-2026-0002', '1671010202900002', '[DUMMY] Rina Wati (CV Contoh Teknik)',     'T', '2026-07-01T07:50:00');

INSERT INTO N_EMI_Binding_NFC (id_nfc, nik, kode_karyawan, nama, no_mitra, flag_aktif, created_by, created_at)
SELECT m.id, x.nik, NULL, x.nama, x.no_mitra, x.aktif, @oleh, x.dibuat
  FROM @mitra x
  JOIN N_EMI_Master_NFC m ON m.nfc_hex = x.nfc_hex
 WHERE NOT EXISTS (SELECT 1 FROM N_EMI_Binding_NFC b WHERE b.id_nfc = m.id AND b.no_mitra = x.no_mitra);

COMMIT TRANSACTION;

/* Verifikasi: 13 kartu, 12 binding bertanda DUMMY-DML-2026-141. */
/*
SELECT COUNT(*) FROM N_EMI_Master_NFC  WHERE created_by = 'DUMMY-DML-2026-141';  -- 13
SELECT COUNT(*) FROM N_EMI_Binding_NFC WHERE created_by = 'DUMMY-DML-2026-141';  -- 12
*/

/* Pembersihan (hanya bila data dummy ingin diulang dari nol; jalankan manual). */
/*
DELETE b FROM N_EMI_Binding_NFC b WHERE b.created_by = 'DUMMY-DML-2026-141';
DELETE m FROM N_EMI_Master_NFC  m WHERE m.created_by = 'DUMMY-DML-2026-141'
   AND NOT EXISTS (SELECT 1 FROM N_EMI_Binding_NFC b WHERE b.id_nfc = m.id);
*/
