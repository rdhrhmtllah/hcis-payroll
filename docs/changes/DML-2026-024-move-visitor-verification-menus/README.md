# DML-2026-024 — Pindahkan menu verifikasi Visitor

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-024-move-visitor-verification-menus` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-024-move-visitor-verification-menus/data-change.sql` |
| Checksum SHA-256 | `cf06bbd2928b50f8ddf0a4732b397e02e9f663c5e5b679d1fee48f585ed68602` |
| Promotion | **DILARANG — digantikan oleh `DML-2026-025-correct-visitor-admin-menu-header`** |

## Basis registry yang diverifikasi

```text
Environment    : development (APP_ENV=local)
Database       : Web_HRIS
Module         : VISITOR
Id_Grup_Fitur  : 4
Flag_Aktif     : Y
Pages          : visitorVerifikatorPage, visitorLaporanPage
URLs           : /verifikator-visitor, /laporanvisitor
Diverifikasi   : 2026-08-24 melalui database aktif
```

## Scope dan dampak

| Tabel | Predicate/scope | Dampak |
|---|---|---:|
| `N_HRIS_Menus` | `Id_Menu=80/visitorVerifikatorPage` dan `Id_Menu=81/visitorLaporanPage` | tepat 2 update |
| `N_HRIS_Grup_Fitur_Map` | precondition mapping aktif ke VISITOR | 0 mutation |
| `HRIS_Page_Access` | akses existing user 4 dan 624 | 0 mutation |
| `N_SYSTEM_Database_Change` | `Kode_Change` change ini | maksimal 1 insert |

Header tujuan menggunakan `Header_Code=admin_verifikator`, label `Admin Verifikator`, ikon `bi bi-person-check-fill`, dan `Sort_Header=5`. Menu Verifikator memakai `Sort_Menu=10`; Laporan memakai `Sort_Menu=20`. Route, nama menu, module mapping, dan page access tidak berubah.

## Recovery

Tidak ada rollback otomatis dan tidak ada delete. Jika perlu dipulihkan, administrator database dapat meninjau lalu mengembalikan kedua row ke `Header_Code=visitor`, `Header_Label=Visitor`, `Header_Icon=bi bi-people-fill`, `Sort_Header=4`, dengan urutan lama 40 dan 50.

## Agent completed

- Memverifikasi target development, dua menu, mapping module, dan page access.
- Membuat DML idempotent dengan guard jumlah row dan transaction.
- Menjalankan exact DML ber-checksum di development `Web_HRIS`.
- Memverifikasi dua menu berada di header `Admin Verifikator`, dua mapping VISITOR tetap aktif, empat page-access tetap aktif, dan satu record change dashboard tercatat.
- Membersihkan application cache setelah perubahan menu.

## User tasks

- Jangan catat atau jalankan DML ini melalui SQL Tracker/production.
- Gunakan DML-2026-025 sebagai koreksi dan satu-satunya artifact yang dipromosikan.
