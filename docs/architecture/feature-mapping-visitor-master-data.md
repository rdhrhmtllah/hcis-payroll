# Mapping Visitor Governance Master Data

Snapshot ini mencatat basis placement fitur baru dan bukan pengganti registry
database.

| Bidang | Hasil verifikasi |
|---|---|
| Tanggal | 2026-08-24 |
| Environment | development (`APP_ENV=local`) |
| Database | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Module | `VISITOR` (`Id_Grup_Fitur=4`) |
| Submodule | `Governance` |
| Feature | `MasterData` |
| Page | `visitorMasterDataPage` |
| URL | `/admin/visitor/master-data` |
| Header | `administrator_visitor` / Administrator Visitor |
| Role akses awal | `SHE_ADMIN` aktif pada grup VISITOR |

Layer fitur ditempatkan pada controller, request, service, route, frontend page,
komponen, API module, dan test di bawah struktur
`VISITOR/Governance/MasterData`. Delapan master yang dikelola adalah approval
question, durasi permit, kategori permit, plat kendaraan, purpose, vehicle
color, vehicle model, dan vehicle type.

`DDL-2026-023-add-visitor-master-soft-status` menambahkan `Flag_Aktif` pada
master purpose dan vehicle color di development. Seluruh delapan master kini
mendukung status aktif/nonaktif. Form Permit dan Tamu hanya memuat master aktif,
dan backend menolak referensi master nonaktif untuk transaksi baru. Query
histori serta Verifikator tetap membaca snapshot/referensi lama tanpa filter
status agar audit transaksi tidak kehilangan konteks.
