# `N_HRIS_Organization_Relationship_Type`

> **Status lingkungan:** schema/data awal berasal dari `DDL-2026-061`, penyederhanaan dari 062, dan type eksplisit/nama final dari `DDL-2026-063`. Ketiganya `DEV_DONE`/`TRACKER_PENDING`; production wajib menjalankan 061 lalu 062 lalu 063 melalui SQL Tracker.

> **As-built 063:** development sudah memakai `Flag_Relasi_Setara` dan mengaktifkan `ATASAN`, `BAWAHAN`, serta `PEER`. Pairing fact row belum dijamin oleh schema.

## Purpose

Master jenis relasi directional antar-karyawan. Tabel ini menyediakan business key stabil bagi Organization Relationship sebagai fondasi lintas modul, tanpa mencampur jenis relasi dengan assignment atau policy modul konsumen.

## Ownership dan perubahan

- Modul/submodul/fitur: `ESS / Organization / Relationship`
- Change ID: `DDL-2026-061-hris-organization-relationship`
- Semantic change: `DDL-2026-062-organization-relationship-simple-type`
- Explicit-type change: `DDL-2026-063-organization-relationship-explicit-direction-types`
- Forward DDL: `database/ddl/DDL-2026-061-hris-organization-relationship/forward.sql`
- Manual reset: `database/ddl/DDL-2026-061-hris-organization-relationship/drop.sql`
- Seed DML: `database/dml/DDL-2026-061-hris-organization-relationship/data-change.sql`
- Simplification DML: `database/dml/DDL-2026-062-organization-relationship-simple-type/data-change.sql`
- Explicit-type DML: `database/dml/DDL-2026-063-organization-relationship-explicit-direction-types/data-change.sql`

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| `Id_Organization_Relationship_Type` | `int identity` | No | Primary key. |
| `Kode_Relasi` | `varchar(50)` | No | Immutable-style business key; unique. |
| `Nama_Relasi` | `nvarchar(100)` | No | Display name. |
| `Deskripsi` | `nvarchar(500)` | Yes | Human-readable semantics and resolver use. |
| `Flag_Relasi_Setara` | `char(1)` | No | `Y` berarti kedua endpoint tidak memiliki pihak lebih tinggi/rendah dan dibaca setara. `PEER=Y`; `ATASAN/BAWAHAN=T`. Default `T`. |
| `Flag_Pembentuk_Struktur_Organisasi` | `char(1)` | No | `Y` bila type mempunyai semantik pembentuk/penelusuran struktur. As-built: `ATASAN=Y`, `PEER=T`; target `BAWAHAN=Y`. Default `T`. Chart wajib menggabungkan pasangan cermin menjadi satu edge. |
| `Urutan` | `int` | No | Display order; default `0`. |
| `Flag_Aktif` | `char(1)` | No | `Y` active, `T` inactive; default `Y`. |
| `Created_At` | `datetime2(0)` | No | Creation time; database default `SYSDATETIME()`. |
| `Created_By` | `int` | Yes | Human actor `KPI_Users.Id_Users`; NULL for seed/system actor. |
| `Updated_At` | `datetime2(0)` | Yes | Last update time. |
| `Updated_By` | `int` | Yes | Human actor `KPI_Users.Id_Users`; no physical FK. |

## Constraints and indexes

- `PK_HRIS_Org_Rel_Type`: primary key.
- `UX_HRIS_Org_Rel_Type_Kode`: unique business key.
- `IX_HRIS_Org_Rel_Type_Aktif_Urutan`: active ordered master reads; includes code and name.
- `DF_HRIS_Org_Rel_Type_Dua_Arah` dan `DF_HRIS_Org_Rel_Type_Pembentuk_Struktur`: default aman `T`.
- No CHECK enum for relationship codes or flags; values are validated by the future backend.

## Seed rows

Type aktif setelah change 063:

- `ATASAN`: related employee adalah atasan subject; relasi setara `T`, pembentuk struktur `Y`;
- `BAWAHAN`: related employee adalah bawahan subject; relasi setara `T`, pembentuk struktur `Y`;
- `PEER`: kedua endpoint mempunyai kedudukan setara; relasi setara `Y`, pembentuk struktur `T`.

Legacy type `DIRECT_SUPERVISOR`, `FUNCTIONAL_SUPERVISOR`, `PROJECT_MANAGER`, dan `MATRIX_SUPERVISOR` tetap tersimpan tetapi `Flag_Aktif=T`. Tidak ada row yang dihapus. Keputusan lama untuk memperoleh bawahan melalui reverse lookup tidak lagi menjadi target konsep.

Target konsep berikutnya adalah:

- `ATASAN`: Related adalah atasan Subject; relasi setara `T`, pembentuk struktur `Y`;
- `BAWAHAN`: Related adalah bawahan Subject; relasi setara `T`, pembentuk struktur `Y`;
- `PEER`: kedua endpoint berkedudukan setara; relasi setara `Y`, pembentuk struktur `T`;
- tidak ada kolom `Sifat_Arah`, direction, atau metadata arah pengganti;
- `SELF` tetap bukan Organization Relationship Type.

Development sudah memuat seed `BAWAHAN`. Consumer production belum boleh menganggapnya tersedia sebelum SQL Tracker 061/062/063 selesai dan hasil production diverifikasi.

## Related application usage

No controller, service, endpoint, or model exists yet. KPI/Appraisal is the first planned consumer, not the owner of every use case. Each module must define its own access and scope policy. Application implementation is a separate reviewed task.
