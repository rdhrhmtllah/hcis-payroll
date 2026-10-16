/* Change ID: DML-2026-150-system-kontak-bantuan-pic-modul */

/* PIC modul yang dulu tertulis di kode (InertiaShellService::buildHelpItems)
   dipindah ke tabel kontak bantuan global (DDL-2026-144), supaya semua kontak
   di Pusat Bantuan dikelola di satu tempat. Idempoten: tiap baris dijaga
   IF NOT EXISTS pada lingkup + email yang sama. */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Kode_Grup_Fitur = 'ESS' AND Kode_Fitur = 'UMUM' AND Email = 'hc.ops@evomanufacturingindonesia.co.id')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('ESS', 'UMUM', 'PIC HCIS', 'Admin HCIS Absensi dan Perizinan', NULL, 'hc.ops@evomanufacturingindonesia.co.id', NULL, 1, 'Y', 'DML-2026-150');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Kode_Grup_Fitur = 'KPI' AND Kode_Fitur = 'UMUM' AND Email = 'learning.development@evomanufacturingindonesia.co.id')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('KPI', 'UMUM', 'PIC KPI', 'Admin KPI', NULL, 'learning.development@evomanufacturingindonesia.co.id', NULL, 1, 'Y', 'DML-2026-150');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Kode_Grup_Fitur = 'LMS' AND Kode_Fitur = 'UMUM' AND Email = 'learning.development@evomanufacturingindonesia.co.id')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('LMS', 'UMUM', 'PIC LMS', 'Admin LMS', NULL, 'learning.development@evomanufacturingindonesia.co.id', NULL, 1, 'Y', 'DML-2026-150');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Kode_Grup_Fitur = 'SYSTEM' AND Kode_Fitur = 'IT' AND Email = 'it.hcis@evomanufacturingindonesia.co.id')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('SYSTEM', 'IT', 'PIC IT HCIS', 'Admin IT HCIS', NULL, 'it.hcis@evomanufacturingindonesia.co.id', NULL, 1, 'Y', 'DML-2026-150');
