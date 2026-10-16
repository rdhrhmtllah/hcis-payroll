# DML-2026-168: Pendaftaran Menu Monitoring ID Card / NFC

## Ringkasan
Mendaftarkan menu baru **Monitoring ID Card / NFC** di bawah header sidebar `Administrator Visitor` (`administrator_visitor`), menghubungkannya ke pemetaan grup fitur VISITOR (`Id_Grup_Fitur = 4`), dan menghibahkan akses awal bagi pengguna pemegang hak akses `visitorVerifikatorPermitPage` dan `visitorMasterDataPermitPage`.

## Metadata
- **Change ID**: `DML-2026-168-menu-nfc-monitoring`
- **Tipe**: `DML`
- **Target Database**: `Web_HRIS` (Development) / `emi_db_web_hr` (Production)
- **Status Development**: `PENDING`
- **Status Production**: `PENDING`
- **Status SQL Tracker**: `PENDING`

## Objek Terdampak
- `N_HRIS_Menus`: Penambahan 1 baris menu (`Jenis_page = 'visitorNfcMonitoringPage'`, `Url_Menu = '/admin/visitor/nfc'`).
- `N_HRIS_Grup_Fitur_Map`: Penambahan 1 baris pemetaan ke grup VISITOR (`Id_Grup_Fitur = 4`).
- `HRIS_Page_Access`: Penambahan hak akses menu untuk verifikator / admin permit.

## Rollback
Jalankan `database/dml/DML-2026-168-menu-nfc-monitoring/rollback.sql`.
