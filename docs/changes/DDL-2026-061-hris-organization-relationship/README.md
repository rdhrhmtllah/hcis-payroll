# DDL-2026-061 — Organization Relationship

| Field | Value |
|---|---|
| Change ID | `DDL-2026-061-hris-organization-relationship` |
| Target database | `Web_HRIS` development |
| Status | `TRACKER_PENDING` — development `DONE`, verification `DEV_DONE` |
| Description | Fondasi lintas modul: master jenis relasi dan relasi organisasi directional employee-to-employee |
| Affected objects | `N_HRIS_Organization_Relationship_Type`, `N_HRIS_Organization_Relationship`, `N_SYSTEM_Database_Change` |
| Forward DDL | `database/ddl/DDL-2026-061-hris-organization-relationship/forward.sql` |
| Manual drop | `database/ddl/DDL-2026-061-hris-organization-relationship/drop.sql` |
| DML | `database/dml/DDL-2026-061-hris-organization-relationship/data-change.sql` |
| Blueprint | `plans/hris/2026-08-29-organization-relationship-design.md` |
| Table docs | `docs/database/tables/hris/n-hris-organization-relationship-type.md`; `docs/database/tables/hris/n-hris-organization-relationship.md` |
| Forward checksum SHA-256 | `ceab546108f6667bd805da409659f751be8b3bc2eeec8984c380dfd4b5992ee2` |
| DML checksum SHA-256 | `0b864cac430a674abb7168c2d16b7dd74eca0fed75581cfb66a9cc0a99b0ffb4` |

## Scope

Perubahan ini membuat dua tabel organisasi lintas modul. Tidak menggandakan master Karyawan, Divisi, Level, atau Jabatan, tidak menyentuh tabel appraisal, dan tidak menyimpan bobot appraisal. Subject dan related employee boleh berbeda Divisi, Departemen, Level, Jabatan, maupun perusahaan; setiap modul menentukan akses dan scope sendiri.

PK `Karyawan` diverifikasi sebagai `(Kode_Perusahaan, Kode_Karyawan)`. Relationship memakai composite FK pada kedua ujung; FK satu kolom ke `Kode_Karyawan` ditolak saat parse-only dan tidak dipaksakan dengan perubahan constraint pada master legacy.

Audit actor manusia memakai `KPI_Users.Id_Users` secara semantik dalam kolom `int NULL`; tidak diberi FK. Source metadata disimpan dalam `Sumber_Data` dan `Referensi_Sumber`.

## DML scope

- Insert maksimal lima seed yang belum ada: `DIRECT_SUPERVISOR`, `FUNCTIONAL_SUPERVISOR`, `PEER`, `PROJECT_MANAGER`, `MATRIX_SUPERVISOR`.
- Insert satu record `N_SYSTEM_Database_Change` bila belum ada.
- Tidak ada UPDATE/DELETE terhadap data existing.

## Pending ledger yang diperiksa sebelum perubahan

Ledger project berisi perubahan lama yang belum melewati SQL Tracker/production. Tiga item masih development `PENDING`, termasuk `DML-2026-054-subtitle-functional-document`; banyak item lain sudah development tetapi `SQL Tracker = PENDING`. Kondisi tersebut tidak diubah oleh change ini.

## Execution and verification

Sudah dieksekusi pada `Web_HRIS` development tanggal 2026-08-29.

| Check | Result |
|---|---|
| Exact forward | 9/9 batch OK; checksum `ceab5461…` |
| Exact DML | 3/3 batch OK; checksum `0b864cac…` |
| Tables/columns | type 10 kolom; relationship 20 kolom |
| Foreign keys | type + subject + related = 3, seluruhnya enabled/trusted |
| Employee FK | composite `(Kode_Perusahaan, Kode_Karyawan)` pada kedua endpoint |
| Check constraints | date range dan different employee, enabled/trusted |
| Indexes | 2 PK + 6 additional indexes sesuai artefak |
| Seed | 5 kode aktif, 5 unik |
| Relationship data | 0 row; tidak ada data rekaan |
| Registry | 1 row; `DONE / PENDING / PENDING / DEV_DONE` |
| Idempotency | rerun DML mempertahankan type `5 → 5`, registry `1 → 1` |
| Appraisal objects | tidak ada `N_PA_*` yang dibuat/disentuh |

### Catatan validator

Percobaan memakai SQL Server `SET PARSEONLY` ternyata mengeksekusi batch DDL secara parsial sebelum melaporkan kegagalan FK satu kolom. Eksekusi dihentikan dan keadaan diaudit: tabel kosong dan seluruh objek berasal dari exact artifact. PK `Karyawan` kemudian dikonfirmasi komposit, DDL dikoreksi menjadi composite FK, dan exact forward final dijalankan ulang secara idempoten 9/9 batch.

Percobaan yang rollback mengonsumsi identity 1–3 pada master type; lima seed valid memiliki Id 4–8. Gap identity adalah perilaku normal SQL Server dan bukan kehilangan data.

## Rollback

`drop.sql` hanya bantuan reset manual. Agent tidak akan menjalankannya karena menghapus seluruh histori relationship dan master tipenya.

## Agent completed

- Blueprint, DDL/DML, table docs, change record, checksum, dan ledger selesai.
- Exact forward/DML dijalankan dan diverifikasi pada development `Web_HRIS`.
- Registry development direkam; SQL Tracker dan production tetap pending.

## User tasks

- Setelah development berhasil, review checksum dan record exact forward/DML ke SQL Tracker.
- Jalankan manual ke production melalui SQL Tracker.
- Konfirmasi hasil production sebelum status dapat dinaikkan.
