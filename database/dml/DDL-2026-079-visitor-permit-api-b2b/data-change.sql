-- Change ID: DDL-2026-079-visitor-permit-api-b2b (DML)
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
--
-- 1) Backfill penanda sumber pada pengajuan permit yang sudah ada. Penyaring di
--    kode memang sudah dilindungi ISNULL(Sumber_Pengajuan,'HCIS'), tetapi
--    laporan dan layar yang memisah pengajuan mitra dari pengajuan karyawan
--    akan salah tanpa backfill ini.
--
-- 2) Pendaftaran ketiga endpoint permit mitra. Url_Route WAJIB berisi POLA rute
--    termasuk "{no_transaksi}" — whitelist guard dicocokkan ke route()->uri(),
--    dan pola yang salah membuat rute berparameter ditolak 401 tanpa petunjuk
--    penyebabnya. Dua dari tiga endpoint di sini berparameter.
--    Id_Grup_Fitur 4 = VISITOR.
--
-- Pendaftaran APLIKASI mitra TIDAK ada di sini: nama, pasangan kunci
-- publik/rahasia, dan Kode_Karyawan_PIC-nya adalah keputusan pemilik repo, bukan
-- sesuatu yang boleh dikarang. Lihat bagian "Pekerjaan user" di
-- docs/changes/DDL-2026-079-visitor-permit-api-b2b/README.md.
--
-- Keduanya idempoten.

UPDATE N_EMI_VISITOR_Transaksi_Permit
   SET Sumber_Pengajuan = 'HCIS'
 WHERE Sumber_Pengajuan IS NULL;
GO

MERGE N_HRIS_Api_Endpoint AS t
USING (VALUES
    ('/api/v1/hcis/visitor/permit-tamu', 'POST', N'Ajukan permit COKLAT untuk tamu mitra B2B'),
    ('/api/v1/hcis/visitor/permit-tamu/{no_transaksi}', 'GET', N'Status permit COKLAT mitra B2B'),
    ('/api/v1/hcis/visitor/permit-tamu/{no_transaksi}/batal', 'POST', N'Pembatalan permit COKLAT oleh mitra B2B')
) AS s (Url_Route, Method, Deskripsi)
ON t.Url_Route = s.Url_Route AND t.Method = s.Method
WHEN NOT MATCHED THEN
    INSERT (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES (4, s.Url_Route, s.Method, 'IN', s.Deskripsi, SYSDATETIME(), 'Y');
GO
