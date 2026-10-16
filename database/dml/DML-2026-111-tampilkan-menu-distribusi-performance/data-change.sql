-- DML-2026-111-tampilkan-menu-distribusi-performance: data-change.sql
-- Tampilkan menu Distribusi Performance di sidebar Administrator PA

UPDATE [dbo].[N_HRIS_Menus]
SET
    [Is_Show_Sidebar] = 'Y',
    [Nama_Menu] = 'Distribusi Performance',
    [Menu_Subtitle] = 'Analisis kurva normal & sebaran hasil'
WHERE [Jenis_page] = 'PaDistribusiPage'
  AND [Kode_Perusahaan] = '001';
