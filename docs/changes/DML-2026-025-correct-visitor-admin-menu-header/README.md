# DML-2026-025 — Koreksi header menu Visitor

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-025-correct-visitor-admin-menu-header` |
| Jenis | DML koreksi |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-025-correct-visitor-admin-menu-header/data-change.sql` |
| Checksum SHA-256 | `fe4337ae7c9aa7295007dd358358402c92d84c6dee5fc20f200bbb8b329fda79` |
| Menggantikan | `DML-2026-024-move-visitor-verification-menus` |

## Basis registry yang diverifikasi

```text
Environment    : development (APP_ENV=local)
Database       : Web_HRIS
Module         : VISITOR
Id_Grup_Fitur  : 4
Flag_Aktif     : Y
Header tujuan  : administrator_visitor / Administrator Visitor
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

Target memakai header existing `administrator_visitor` / `Administrator Visitor`, ikon `bi bi-gear-fill`, dan `Sort_Header=10`. Urutan menu menjadi Master Data 10, Verifikator 20, Laporan 30, dan Menu & Matrix 90. Route, nama menu, module mapping, dan page access tidak berubah.

## Koreksi terhadap DML-2026-024

`DML-2026-024` sempat dijalankan hanya di development dan salah membuat metadata header `Admin Verifikator`. DML tersebut tidak boleh dicatat ke SQL Tracker atau dijalankan ke production. Artefaknya dipertahankan sebagai bukti exact SQL yang pernah dieksekusi; koreksi dilakukan oleh change ID ini.

## Recovery

Tidak ada rollback otomatis dan tidak ada delete. Jika perlu dipulihkan, administrator database harus meninjau metadata menu yang diinginkan dan melakukan update terbatas pada `Id_Menu` 80 dan 81.

## Agent completed

- Memverifikasi target development, kedua menu, header existing Administrator Visitor, mapping module, dan page access.
- Membuat DML koreksi idempotent dengan guard jumlah row dan transaction.
- Menjalankan exact DML koreksi di development `Web_HRIS`.
- Memverifikasi susunan header: Master Data 10, Verifikator 20, Laporan 30, Menu & Matrix 90.
- Memverifikasi header salah `admin_verifikator` tidak lagi dipakai, dua mapping VISITOR dan empat page-access tetap aktif.
- Mencatat satu record change dashboard dan membersihkan application cache.

## User tasks

- Catat hanya DML-2026-025 dengan checksum identik ke SQL Tracker; jangan promosikan DML-2026-024.
- Jalankan DML-2026-025 secara manual ke production melalui SQL Tracker.
- Verifikasi sidebar dan akses kedua halaman di production.
