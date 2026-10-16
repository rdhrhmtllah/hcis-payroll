-- Change ID: DDL-2026-102-visitor-tamu-api-b2b (DML)
-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
--
-- 1) Backfill penanda sumber pada registrasi tamu yang sudah ada. Penyaring di
--    kode sudah dilindungi ISNULL(Sumber_Pengajuan,'HCIS'), tetapi laporan dan
--    layar yang memisah tamu mitra dari tamu karyawan akan salah tanpa backfill.
--
-- 2) Pendaftaran dua endpoint tamu B2B. Url_Route WAJIB berisi POLA rute
--    termasuk "{no_transaksi}" — whitelist guard dicocokkan ke route()->uri(),
--    dan pola yang salah membuat rute berparameter ditolak 401 tanpa petunjuk
--    penyebabnya. Id_Grup_Fitur 4 = VISITOR.
--
-- Pendaftaran APLIKASI mitra TIDAK ada di sini: nama, pasangan kunci, dan
-- Kode_Karyawan_PIC-nya keputusan pemilik repo. Lihat "Pekerjaan user" di
-- docs/changes/DDL-2026-102-visitor-tamu-api-b2b/README.md.
--
-- Keduanya idempoten.

UPDATE N_EMI_VISITOR_Transaksi_Tamu
   SET Sumber_Pengajuan = 'HCIS'
 WHERE Sumber_Pengajuan IS NULL;
GO

MERGE N_HRIS_Api_Endpoint AS t
USING (VALUES
    ('/api/v1/hcis/visitor/tamu-b2b', 'POST', N'Registrasi tamu B2B oleh aplikasi mitra'),
    ('/api/v1/hcis/visitor/tamu-b2b/{no_transaksi}', 'GET', N'Status registrasi tamu B2B mitra')
) AS s (Url_Route, Method, Deskripsi)
ON t.Url_Route = s.Url_Route AND t.Method = s.Method
WHEN NOT MATCHED THEN
    INSERT (Id_Grup_Fitur, Url_Route, Method, Arah, Deskripsi, Created_At, Flag_Aktif)
    VALUES (4, s.Url_Route, s.Method, 'IN', s.Deskripsi, SYSDATETIME(), 'Y');
GO
