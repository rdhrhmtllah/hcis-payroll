# Database Documentation

Dokumentasi database dibaca bersama `AGENTS.md` dan workflow DDL/SQL Tracker. Folder `database/` berisi artefak eksekusi; dokumentasi manusia berada di `docs/`.

## Navigasi

- [[naming-conventions|Konvensi penamaan dan desain schema]]
- [[table-design-rules|Aturan pembuatan tabel dan kolom]]
- [[tables/README|Dokumentasi tabel per modul]]
- [[../workflows/ddl-sql-tracker|Workflow DDL dan SQL Tracker]]

## Struktur

```text
docs/database/
├── naming-conventions.md
├── table-design-rules.md
└── tables/<modul>/<nama-tabel>.md

 docs/changes/<change-id>/
 ├── README.md
 ├── data-flow.md
 ├── usage-map.md
 └── review-notes.md

 database/ddl/<change-id>/
 ├── forward.sql
 └── drop.sql

 database/dml/<change-id>/
 └── data-change.sql
```
