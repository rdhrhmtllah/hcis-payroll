# DML-2026-022 — Registrasi Master Data Visitor

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-022-register-visitor-master-data-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-022-register-visitor-master-data-menu/data-change.sql` |
| Dokumentasi mapping | `docs/architecture/feature-mapping-visitor-master-data.md` |

## Basis registry yang diverifikasi

```text
Environment    : development (APP_ENV=local)
Database       : Web_HRIS
Module         : VISITOR
Id_Grup_Fitur  : 4
Flag_Aktif     : Y
Role pemilik   : SHE_ADMIN (Id_Role=4, VISITOR)
Jenis_page     : visitorMasterDataPage
URL            : /admin/visitor/master-data
Header         : administrator_visitor / Administrator Visitor
Diverifikasi   : 2026-08-24 melalui database aktif
```

## Scope dan perkiraan dampak

| Tabel | Predicate/scope | Perkiraan awal |
|---|---|---:|
| `N_HRIS_Menus` | `Jenis_page = visitorMasterDataPage` | 1 insert |
| `N_HRIS_Grup_Fitur_Map` | page baru ke `Id_Grup_Fitur = 4` | 1 insert |
| `HRIS_Page_Access` | user aktif role `SHE_ADMIN` pada grup VISITOR | 1 insert |
| `N_SYSTEM_Database_Change` | `Kode_Change` change ini | 1 insert |

Script bersifat idempoten dan dibungkus satu transaction. Tidak ada `UPDATE`,
`DELETE`, `MERGE`, perubahan schema, atau hard delete. Atribut perusahaan,
level, dan divisi akses diambil dari page VISITOR aktif milik user `SHE_ADMIN`
yang sama. Hasil preflight development menemukan tepat satu user dan sumbernya
lengkap.

## Rollback/recovery

Agent tidak menjalankan rollback data. Jika perubahan harus dibatalkan,
administrator database menonaktifkan atau menghapus empat baris hasil change
ini secara manual setelah review predicate dan dependensinya. File yang diuji
tetap menjadi sumber untuk SQL Tracker; jangan menulis ulang SQL saat dicatat.

## Hasil development

File dengan SHA-256
`57e262d33c63a4519c8e203fbbe6a72215b5917537b9f8f5fb4f5c3e465a4345`
dieksekusi pada 2026-08-24. Hasil verifikasi:

| Tabel | Hasil |
|---|---:|
| `N_HRIS_Menus` | 1 baris (`Id_Menu=94`, header Administrator Visitor) |
| `N_HRIS_Grup_Fitur_Map` | 1 baris (`Id_Grup_Fitur=4`, aktif) |
| `HRIS_Page_Access` | 1 baris aktif untuk user role `SHE_ADMIN` |
| `N_SYSTEM_Database_Change` | 1 baris |

URL terverifikasi `/admin/visitor/master-data`, `Sort_Menu=10`, dan jumlah user
aktif yang memperoleh page access tepat satu. SQL Tracker dan production belum
dijalankan.

## Agent completed

1. Membuat dan membekukan artifact DML idempoten.
2. Memverifikasi target `Web_HRIS` development dan scope role `SHE_ADMIN`.
3. Menjalankan serta memverifikasi empat insert development.
4. Mendaftarkan change pada dashboard database dan ledger project.

## Sisa pekerjaan pengguna

1. Record file DML yang checksum-nya tercatat di ledger ke SQL Tracker.
2. Jalankan dari SQL Tracker ke production setelah approval.
3. Verifikasi menu, mapping, dan akses `SHE_ADMIN` pada production.
