# DML-2026-166 — Konversi ke model RBAC Fase 2

Mengisi tabel DDL-2026-165 dari kondisi sekarang. Aturan lengkap: `docs/superpowers/plans/2026-10-09-rbac-fase2a-data-konversi.md` ("Aturan konversi").

## Yang diubah

| Objek | Isi |
|---|---|
| `N_HRIS_Role_Functional.Jenis_Role` | Role admin sah (config `tata_kelola.peran_admin`) → `ADMIN`; `ADMIN_PERMIT`, `ADMIN_TAMU(_B2B)`, `TAMU_DEPT_*`, `SHE_ADMIN`, `ROLE_KPI_VERIF` → `POOL_APPROVAL`; sisanya tetap `AKSES`. Label saja. |
| `N_HRIS_Role_Hak` | Per role aktif: halaman yang kini ia buka lewat hibah (tersaring Fase 0) × (VIEW + aksi capability default). |
| `N_HRIS_Role_User_Cakupan` | Per penugasan aktif: dari `N_HRIS_Role_Scope` (GLOBAL_ALL/SCOPE_HC_ALL_ACTIVE → SEMUA, SCOPE_GA_PHL → PHL, DIVISIONAL_FILTER → DIVISI, LEADER_DYNAMIC_TEAM → TIM). |
| `N_HRIS_Role_User_Tanggung_Jawab` | Per penugasan role ADMIN: MENU → AKSES, APPROVAL → APPROVAL, MEMBERSHIP → KEANGGOTAAN, SUPER_ADMIN → ketiganya. |

Tidak mengubah keputusan akses (model baru belum dipakai memutuskan). Idempoten; satu batch tanpa `GO`. Daftar halaman/role tersalin dari config dan dijaga `KonversiRbacTest`.

## Hasil di dev (9 Okt 2026)

1.501 hak (31 role, 97 halaman), 16 cakupan, 17 tanggung jawab. Jenis_Role: 18 ADMIN, 11 POOL_APPROVAL, 7 AKSES. Putaran kedua tanpa perubahan.

## Urutan rilis

Setelah DDL-2026-165. Boleh dijalankan ulang kapan pun (mis. setelah role/halaman baru ditambahkan sebelum Fase 3).

## Rollback

```sql
DELETE FROM dbo.N_HRIS_Role_User_Tanggung_Jawab;
DELETE FROM dbo.N_HRIS_Role_User_Cakupan;
DELETE FROM dbo.N_HRIS_Role_Hak WHERE Created_By = 'DML-2026-166';
UPDATE dbo.N_HRIS_Role_Functional SET Jenis_Role = 'AKSES';
```
