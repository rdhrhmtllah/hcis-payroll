# N_PA_Rater_Category — target (`N_PA_Rater_Category` as-built)

> **Compatibility status:** target naming uses `N_PA_Rater_Category`.
> Development still contains physical table `N_PA_Rater_Category` from DDL
> 064. The physical rename requires a separate reviewed database change.

## Purpose

Controlled master of appraisal rater roles. It is distinct from Organization Relationship Type: this table describes how a score is reported, while Organization Relationship Type describes the employee relationship used to find candidates.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Policy
- Change: DDL-2026-064-performance-appraisal-policy-foundation
- DDL: database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/forward.sql
- Seed: database/dml/DDL-2026-064-performance-appraisal-policy-foundation/data-change.sql

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Rater_Category | int identity | No | Primary key. |
| Kode_Kategori | varchar(40) | No | Stable unique code. |
| Nama_Kategori | nvarchar(100) | No | Display name. |
| Deskripsi | nvarchar(500) | No | Appraisal-role definition. |
| Flag_Aktif | char(1) | No | Selectable when Y; default Y. |
| Created_At | datetime2(0) | No | Creation time; database default. |
| Created_By | int | Yes | Human actor KPI_Users.Id_Users; NULL for seed/system. |
| Updated_At | datetime2(0) | Yes | Last update time. |
| Updated_By | int | Yes | Human actor KPI_Users.Id_Users; no physical actor FK. |
| Urutan_Pengisian | smallint | Yes | Posisi kategori di wizard tugas penilaian, kecil lebih dulu. NULL = belum diurutkan (ditaruh paling akhir). DDL-2026-119. |
| Flag_Boleh_Kalibrasi | char(1) | No | Y = penilai kategori ini boleh mengubah skornya di pratinjau (Kalibrasi Penilai); T = hanya-baca. Default T. DDL-2026-119. |

## Urutan dan kalibrasi (DDL-2026-119)

`UX_PA_Rater_Category_Urutan` (unik, tersaring `Flag_Aktif = 'Y' AND Urutan_Pengisian IS NOT NULL`)
mencegah dua kategori aktif berebut langkah yang sama. Urutan ditata sekaligus lewat
`PaJenisPenilaiMatriksService::tataUrutanKategori()` dalam satu pernyataan `UPDATE ... CASE`,
karena indeks unik diperiksa per pernyataan. Penanda ditulis
`PaJenisPenilaiMatriksService::setelBolehKalibrasi()`.

Keduanya **kamus hidup**, tidak disalin per siklus: keputusan pemilik proyek 18 September 2026,
penyimpangan sadar dari K26. Nilai awal urutan dari DML-2026-124: SELF 1, ATASAN 2, PEER 3,
BAWAHAN 4.

## Seed and indexes (as-built 064)

Active system seed:

- ATASAN — supervisor appraisal role;
- BAWAHAN — subordinate appraisal role;
- PEER — peer appraisal role;
- SELF — participant self-assessment.

UX_PA_Rater_Category_Kode protects the business code. IX_PA_Rater_Category_Aktif supports active master reads. Codes are backend-governed rather than database-enum CHECK values.

## Application usage

No application writer exists yet. Historical referenced categories are
deactivated, not deleted. Category-to-Organization-Type mapping is stored per
Policy detail; SELF has no organization type. New code must not assume the target
name exists physically before the compatibility change is verified.
