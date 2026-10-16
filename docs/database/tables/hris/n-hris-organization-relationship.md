# `N_HRIS_Organization_Relationship`

> **Status as-built:** development setelah `DDL-2026-063` mengaktifkan `ATASAN`/`BAWAHAN`/`PEER` dan master memakai `Flag_Relasi_Setara`. Tabel fakta masih 0 row saat verifikasi; pairing otomatis belum dijamin schema.

## Purpose

Effective-dated, directional employee-to-employee organization relationship and shared organization foundation. Each row states that a subject employee has one relationship type toward another employee for a time range.

This table is an organization fact and a candidate source. It is not a Performance Appraisal assignment table and stores no appraisal weight.

The employees may belong to different Divisi, Departemen, Level, Jabatan, or companies. There is deliberately no same-unit constraint. `Flag_Utama` distinguishes primary from secondary/contextual supervisor, while optional context columns explain where the relationship applies. Access scope is decided by each consuming module.

## Ownership dan perubahan

- Modul/submodul/fitur: `ESS / Organization / Relationship`
- Change ID: `DDL-2026-061-hris-organization-relationship`
- Context/type change: `DDL-2026-062-organization-relationship-simple-type`
- Explicit-type change: `DDL-2026-063-organization-relationship-explicit-direction-types`
- Forward DDL: `database/ddl/DDL-2026-061-hris-organization-relationship/forward.sql`
- Manual reset: `database/ddl/DDL-2026-061-hris-organization-relationship/drop.sql`
- DML/registry: `database/dml/DDL-2026-061-hris-organization-relationship/data-change.sql`
- Context/type DML: `database/dml/DDL-2026-062-organization-relationship-simple-type/data-change.sql`
- Explicit-type DML: `database/dml/DDL-2026-063-organization-relationship-explicit-direction-types/data-change.sql`

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| `Id_Organization_Relationship` | `int identity` | No | Primary key. |
| `Id_Organization_Relationship_Type` | `int` | No | FK to relationship type master. |
| `Kode_Perusahaan_Subjek` | `varchar(3)` | No | Subject side of the composite employee FK. |
| `Kode_Karyawan_Subjek` | `varchar(255)` | No | Employee whose relationship is described. |
| `Kode_Perusahaan_Terkait` | `varchar(3)` | No | Related side of the composite employee FK. |
| `Kode_Karyawan_Terkait` | `varchar(255)` | No | Employee at the directional endpoint. |
| `Berlaku_Dari` | `date` | No | Effective start. |
| `Berlaku_Sampai` | `date` | Yes | Effective end; NULL means open-ended. |
| `Flag_Utama` | `char(1)` | No | `Y` primary, `T` non-primary; default `T`. |
| `Jenis_Konteks` | `varchar(30)` | Yes | Context kind such as organization/unit/team/project/matrix; NULL together with its reference means general/no explicit context. |
| `Referensi_Konteks` | `varchar(100)` | Yes | Stable context identifier; must be filled together with `Jenis_Konteks`. No physical FK until the context authority is approved. |
| `Prioritas` | `int` | No | Resolver order within a type; default `1`, not an appraisal weight. |
| `Sumber_Data` | `varchar(50)` | No | Provenance such as MANUAL/IMPORT/KPI_APPROVAL_FLOW; backend-managed value. |
| `Referensi_Sumber` | `varchar(255)` | Yes | Source row/import reference; never treated as authority by itself. |
| `Catatan` | `nvarchar(500)` | Yes | Operational explanation without unnecessary sensitive data. |
| `Flag_Aktif` | `char(1)` | No | Administrative activity `Y/T`; effective reads also check dates. |
| `Created_At` | `datetime2(0)` | No | Creation time; database default `SYSDATETIME()`. |
| `Created_By` | `int` | Yes | Human actor `KPI_Users.Id_Users`; NULL for system/import. |
| `Updated_At`, `Cancelled_At` | `datetime2(0)` | Yes | Update and cancellation audit times. |
| `Updated_By`, `Cancelled_By` | `int` | Yes | Human actor `KPI_Users.Id_Users`; no physical FK. |

## Relationships

- Type FK: `N_HRIS_Organization_Relationship_Type.Id_Organization_Relationship_Type`.
- Subject and related employee composite FKs: `Karyawan.(Kode_Perusahaan, Kode_Karyawan)`. A single-column FK is invalid because the legacy PK is composite.
- Divisi/Sub Divisi are read through `Karyawan.ID_Divisi_Sub_Divisi` and `View_Divisi_Sub_Divisi` when candidate rules need them.
- Level/Jabatan are read through `Karyawan.ID_Level_Jabatan`, `HRIS_Level_Jabatan`, and the existing level/jabatan masters.
- Divisi, Level, and Jabatan are not duplicated and do not restrict valid cross-functional relationships.

## Constraints and indexes

- `PK_HRIS_Org_Rel`: primary key.
- `FK_HRIS_Org_Rel_Type`, `FK_HRIS_Org_Rel_Subject`, `FK_HRIS_Org_Rel_Related`.
- `CK_HRIS_Org_Rel_Date_Range`: end date is NULL or not before start.
- `CK_HRIS_Org_Rel_Different_Employee`: subject and related employee differ.
- `CK_HRIS_Org_Rel_Context_Pair`: context kind/reference are both NULL or both filled; enabled/trusted.
- `UX_HRIS_Org_Rel_Type_Subject_Related_Start`: exact business-row idempotency.
- `UX_HRIS_Org_Rel_Primary_Open`: one active primary open-ended row per type and subject.
- Subject and related-endpoint period indexes mendukung lookup kedua endpoint. Consumer memakai type eksplisit `BAWAHAN`, bukan interpretasi reverse `ATASAN`.
- `IX_HRIS_Org_Rel_Context_Period`: context/type/status/effective-period reads for chart and resolvers.

The existing filtered unique index prevents two active open-ended primary rows for the same subject/type. Bounded-period overlap, per-context primary rules, graph cycles, and PEER reverse duplicates require transactional application validation; no trigger is introduced.

Target paired mirror berikutnya menambahkan kontrak konseptual:

```text
A --ATASAN--> B
B --BAWAHAN--> A
```

Kedua row secara konsep membawa periode, context, `Flag_Utama`, status, dan provenance yang konsisten. `ATASAN` dan `BAWAHAN` memakai `Flag_Relasi_Setara=T` dan `Flag_Pembentuk_Struktur_Organisasi=Y`; `PEER` memakai `Y/T`. Chart harus memilih satu orientasi canonical atau menggabungkan pair agar hanya satu edge dirender. Identity pair, atomic write, dan constraint anti-divergence belum dijamin schema 063. Rollback test menunjukkan `Flag_Utama=Y` pada banyak mirror BAWAHAN untuk satu manager berbenturan dengan `UX_HRIS_Org_Rel_Primary_Open`; keputusan primary sisi BAWAHAN wajib dibuat sebelum fact writes.

## Data flow

```text
Karyawan + Organization Relationship
  -> Rater Policy candidate resolver
  -> transparent recommendations
  -> admin review/override
  -> Participant Rater Assignment snapshot per appraisal cycle
```

Relationship changes after a cycle lock must not modify the old cycle assignment.

## Related application usage

No controller, model, service, job, command, endpoint, or frontend page writes this table yet. The initial maintenance boundary is `ESS/Organization/Relationship`, while the `N_HRIS_` foundation is consumable across modules. KPI/Appraisal is a consumer, not the owner of the organization fact or access rules of other modules.
