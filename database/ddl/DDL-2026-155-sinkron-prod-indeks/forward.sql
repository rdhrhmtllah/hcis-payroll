/* Change ID: DDL-2026-155-sinkron-prod-indeks

   Index yang ADA di development tetapi TIDAK ADA di production, dibandingkan
   berdasarkan DEFINISI (kolom kunci, INCLUDE, UNIQUE, filter), bukan nama saja,
   diukur 2026-09-29 (baca saja). PK/UNIQUE constraint tabel baru ada di
   DDL-2026-154. Index yang NAMANYA SAMA tetapi definisinya berbeda TIDAK
   diubah di sini (butuh DROP + CREATE) — daftarnya di README.

   Jalankan SETELAH DDL-2026-154. IDEMPOTEN: dijaga nama index per tabel.
   Dibuat satu per satu tanpa ONLINE=ON agar sah di semua edisi SQL Server;
   jalankan di luar jam sibuk. */

/* Wajib untuk index berfilter (WHERE ...). */
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;

/* 1. dbo.N_EMI_VISITOR_Transaksi_Permit.UX_VISITOR_Permit_Kode_Kartu — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit') AND name = N'UX_VISITOR_Permit_Kode_Kartu')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Permit_Kode_Kartu] ON dbo.[N_EMI_VISITOR_Transaksi_Permit] ([Kode_Kartu]) WHERE ([Kode_Kartu] IS NOT NULL);

/* 2. dbo.N_EMI_VISITOR_Transaksi_Permit.UX_VISITOR_Permit_Transaksi_Tamu — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit') AND name = N'UX_VISITOR_Permit_Transaksi_Tamu')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Permit_Transaksi_Tamu] ON dbo.[N_EMI_VISITOR_Transaksi_Permit] ([No_Transaksi_Tamu]) WHERE ([No_Transaksi_Tamu] IS NOT NULL AND [Flag_Aktif]='Y');

/* 3. dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi.IX_VISITOR_Permit_Koreksi_No_Transaksi — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi') AND name = N'IX_VISITOR_Permit_Koreksi_No_Transaksi')
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Permit_Koreksi_No_Transaksi] ON dbo.[N_EMI_VISITOR_Transaksi_Permit_Koreksi] ([No_Transaksi]) INCLUDE ([Created_At]);

/* 4. dbo.N_HRIS_Master_Kontak_Bantuan.IX_HRIS_Kontak_Bantuan_Lingkup — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Master_Kontak_Bantuan') AND name = N'IX_HRIS_Kontak_Bantuan_Lingkup')
    CREATE NONCLUSTERED INDEX [IX_HRIS_Kontak_Bantuan_Lingkup] ON dbo.[N_HRIS_Master_Kontak_Bantuan] ([Kode_Grup_Fitur], [Kode_Fitur], [Flag_Aktif], [Urutan_Tampil]) INCLUDE ([Nama_Kontak], [Keterangan], [No_HP], [Email], [Jam_Layanan]);

/* 5. dbo.N_HRIS_Panduan_Halaman.IX_HRIS_Panduan_Halaman_Jenis_Page — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Panduan_Halaman') AND name = N'IX_HRIS_Panduan_Halaman_Jenis_Page')
    CREATE NONCLUSTERED INDEX [IX_HRIS_Panduan_Halaman_Jenis_Page] ON dbo.[N_HRIS_Panduan_Halaman] ([Jenis_page], [Flag_Aktif], [Urutan_Tampil]) INCLUDE ([Id_Panduan], [Halaman_Mulai], [Id_Role]);

/* 6. dbo.N_HRIS_Panduan_Halaman.UX_HRIS_Panduan_Halaman_Unik — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Panduan_Halaman') AND name = N'UX_HRIS_Panduan_Halaman_Unik')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_HRIS_Panduan_Halaman_Unik] ON dbo.[N_HRIS_Panduan_Halaman] ([Id_Panduan], [Jenis_page], [Id_Role]);

/* 7. dbo.N_HRIS_Panduan_Role.IX_HRIS_Panduan_Role_Role — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Panduan_Role') AND name = N'IX_HRIS_Panduan_Role_Role')
    CREATE NONCLUSTERED INDEX [IX_HRIS_Panduan_Role_Role] ON dbo.[N_HRIS_Panduan_Role] ([Id_Role], [Flag_Aktif], [Urutan_Tampil]) INCLUDE ([Id_Panduan], [Halaman_Mulai]);

/* 8. dbo.N_HRIS_Panduan_Role.UX_HRIS_Panduan_Role_Unik — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Panduan_Role') AND name = N'UX_HRIS_Panduan_Role_Unik')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_HRIS_Panduan_Role_Unik] ON dbo.[N_HRIS_Panduan_Role] ([Id_Panduan], [Id_Role]);

/* 9. dbo.N_HRIS_Panduan_Versi.UX_HRIS_Panduan_Versi_No — tabel baru dari DDL-2026-154 */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_HRIS_Panduan_Versi') AND name = N'UX_HRIS_Panduan_Versi_No')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_HRIS_Panduan_Versi_No] ON dbo.[N_HRIS_Panduan_Versi] ([Id_Panduan], [No_Versi]);

/* 10. dbo.N_SOP_Dokumen.IX_N_SOP_Dokumen_Tipe — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_SOP_Dokumen') AND name = N'IX_N_SOP_Dokumen_Tipe')
    CREATE NONCLUSTERED INDEX [IX_N_SOP_Dokumen_Tipe] ON dbo.[N_SOP_Dokumen] ([Flag_Aktif], [Tipe]) INCLUDE ([Judul], [Nomor_SOP_Resmi], [Id_Versi_Aktif]);

/* 11. dbo.N_VISITOR_Inspeksi_Booking.IX_VISITOR_Inspeksi_Booking_Permit — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Inspeksi_Booking') AND name = N'IX_VISITOR_Inspeksi_Booking_Permit')
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Inspeksi_Booking_Permit] ON dbo.[N_VISITOR_Inspeksi_Booking] ([No_Transaksi_Permit], [Status]);

/* 12. dbo.N_VISITOR_Inspeksi_Booking.IX_VISITOR_Inspeksi_Booking_Slot — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Inspeksi_Booking') AND name = N'IX_VISITOR_Inspeksi_Booking_Slot')
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Inspeksi_Booking_Slot] ON dbo.[N_VISITOR_Inspeksi_Booking] ([Waktu_Mulai], [Id_Pos_Inspeksi], [Status]) INCLUDE ([No_Transaksi_Permit], [Waktu_Selesai]);

/* 13. dbo.N_VISITOR_Inspeksi_Booking.UX_VISITOR_Inspeksi_Booking_Aktif — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Inspeksi_Booking') AND name = N'UX_VISITOR_Inspeksi_Booking_Aktif')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Inspeksi_Booking_Aktif] ON dbo.[N_VISITOR_Inspeksi_Booking] ([No_Transaksi_Permit]) WHERE ([Status]='TERJADWAL');

/* 14. dbo.N_VISITOR_Inspeksi_Hari_Shift.UX_VISITOR_Inspeksi_Hari_Shift — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Inspeksi_Hari_Shift') AND name = N'UX_VISITOR_Inspeksi_Hari_Shift')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Inspeksi_Hari_Shift] ON dbo.[N_VISITOR_Inspeksi_Hari_Shift] ([Hari_Minggu], [Id_Shift_Inspeksi]);

/* 15. dbo.N_VISITOR_Inspeksi_Pengecualian.IX_VISITOR_Inspeksi_Pengecualian_Tanggal — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Inspeksi_Pengecualian') AND name = N'IX_VISITOR_Inspeksi_Pengecualian_Tanggal')
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Inspeksi_Pengecualian_Tanggal] ON dbo.[N_VISITOR_Inspeksi_Pengecualian] ([Tanggal]) INCLUDE ([Id_Pos_Inspeksi], [Jenis], [Flag_Aktif]);

/* 16. dbo.N_VISITOR_Master_Pos_Inspeksi.UX_VISITOR_Master_Pos_Inspeksi_Nama — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Master_Pos_Inspeksi') AND name = N'UX_VISITOR_Master_Pos_Inspeksi_Nama')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Master_Pos_Inspeksi_Nama] ON dbo.[N_VISITOR_Master_Pos_Inspeksi] ([Nama_Pos]);

/* 17. dbo.N_VISITOR_Master_Shift_Inspeksi.UX_VISITOR_Master_Shift_Inspeksi_Nama — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Master_Shift_Inspeksi') AND name = N'UX_VISITOR_Master_Shift_Inspeksi_Nama')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Master_Shift_Inspeksi_Nama] ON dbo.[N_VISITOR_Master_Shift_Inspeksi] ([Nama_Shift]);

/* 18. dbo.N_VISITOR_Matriks_Tamu.UX_VISITOR_Matriks_Tamu_Role_Kategori — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Matriks_Tamu') AND name = N'UX_VISITOR_Matriks_Tamu_Role_Kategori')
    CREATE UNIQUE NONCLUSTERED INDEX [UX_VISITOR_Matriks_Tamu_Role_Kategori] ON dbo.[N_VISITOR_Matriks_Tamu] ([Role_Id], [Kategori_Tamu]);

/* 19. dbo.N_VISITOR_Permit_Efektif.IX_VISITOR_Permit_Efektif_Jenis_Akses — tabel sudah ada di production */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.N_VISITOR_Permit_Efektif') AND name = N'IX_VISITOR_Permit_Efektif_Jenis_Akses')
    CREATE NONCLUSTERED INDEX [IX_VISITOR_Permit_Efektif_Jenis_Akses] ON dbo.[N_VISITOR_Permit_Efektif] ([Jenis_Akses], [Status_Efektif]) INCLUDE ([No_Transaksi]);
