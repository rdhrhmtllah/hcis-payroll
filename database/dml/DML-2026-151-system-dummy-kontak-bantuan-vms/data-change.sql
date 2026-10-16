/* Change ID: DML-2026-151-system-dummy-kontak-bantuan-vms */

/* DATA DUMMY — HANYA DEVELOPMENT. Jangan dijalankan di production.
   Melengkapi DML-2026-145 (3 kontak VISITOR/PERMIT) supaya modal
   "Bantuan VMS" di Pusat Bantuan menampilkan seksi Umum, Permit, dan Tamu.
   Semua baris ditandai Created_By = 'DUMMY-DML-2026-151' dan nama [DUMMY]. */

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Created_By = 'DUMMY-DML-2026-151' AND Nama_Kontak = '[DUMMY] Helpdesk VMS')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('VISITOR', 'UMUM', '[DUMMY] Helpdesk VMS', 'Pertanyaan umum aplikasi VMS', '0812-0000-0010', 'helpdesk.vms@contoh.id', 'Sen–Jum 08.00–17.00', 1, 'Y', 'DUMMY-DML-2026-151');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Created_By = 'DUMMY-DML-2026-151' AND Nama_Kontak = '[DUMMY] Resepsionis Lobi')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('VISITOR', 'TAMU', '[DUMMY] Resepsionis Lobi', 'Pendaftaran & jadwal kunjungan tamu', '0812-0000-0011', 'resepsionis@contoh.id', 'Sen–Sab 07.30–16.30', 1, 'Y', 'DUMMY-DML-2026-151');

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Created_By = 'DUMMY-DML-2026-151' AND Nama_Kontak = '[DUMMY] Security Pos Tamu')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('VISITOR', 'TAMU', '[DUMMY] Security Pos Tamu', 'Kedatangan tamu & kartu visitor', '0812-0000-0012', NULL, '24 jam', 2, 'Y', 'DUMMY-DML-2026-151');

/* Nonaktif: memastikan kontak nonaktif tidak tampil. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Kontak_Bantuan WHERE Created_By = 'DUMMY-DML-2026-151' AND Nama_Kontak = '[DUMMY] Resepsionis Lama (nonaktif)')
    INSERT INTO N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan, Urutan_Tampil, Flag_Aktif, Created_By)
    VALUES ('VISITOR', 'TAMU', '[DUMMY] Resepsionis Lama (nonaktif)', 'Tidak boleh tampil', NULL, 'lama@contoh.id', NULL, 9, 'T', 'DUMMY-DML-2026-151');
