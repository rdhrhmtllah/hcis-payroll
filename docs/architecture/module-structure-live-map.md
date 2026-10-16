# Live Module Structure Map

Status: `INVENTORY_BASELINE`
Tanggal snapshot: 2026-08-18
Environment read-only: `Web_HRIS` pada `team311.dyndns.info:1433`

## Tujuan

Dokumen ini mencatat hasil verifikasi registry database dan mencocokkannya dengan struktur source existing. Dokumen ini bukan pengganti registry database, tidak menetapkan ownership baru, dan tidak memindahkan atau mengubah file kode.

## Registry yang diverifikasi

```text
N_HRIS_Master_Grup_Fitur
N_HRIS_Grup_Fitur_Map
N_HRIS_Role_Functional
N_HRIS_Role_User
```

Schema aktual yang diverifikasi:

```text
N_HRIS_Master_Grup_Fitur:
Id_Grup_Fitur, Kode_Grup_Fitur, Nama_Grup_Fitur, Icon_Grup_Fitur,
Subtitle_Grup_Fitur, Sort_Order, Landing_Jenis_Page, Is_Show_Sidebar, Flag_Aktif

N_HRIS_Grup_Fitur_Map:
Id_Grup_Map, Id_Grup_Fitur, Jenis_page, Sort_Order,
Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif

N_HRIS_Role_Functional:
Id_Role, Role_Code, Role_Name, Description, Flag_Aktif,
Created_At, Created_By, Id_Grup_Fitur

N_HRIS_Role_User:
Id, UserId_Web, Id_Role, Assigned_At, Assigned_By, Flag_Aktif
```

## Grup fitur aktif

| Id | Kode | Nama | Mapping aktif |
|---:|---|---|---:|
| 1 | ESS | Employee Self Service | 30 |
| 2 | KPI | Key Performance Indicator | 20 |
| 3 | LMS | Learning Management System | 16 |
| 4 | VISITOR | Visitor Management System | 6 |
| 5 | SYSTEM | System | 1 |

Jumlah mapping dihitung dari `N_HRIS_Grup_Fitur_Map` dengan `Flag_Aktif = 'Y'`. Ini snapshot dan wajib diverifikasi ulang pada task berikutnya yang menyentuh ownership.

## Temuan penting

1. Master aktif hanya memiliki `ESS`, `KPI`, `LMS`, `VISITOR`, dan `SYSTEM`.
2. Tidak ada master aktif berkode `SOP` pada snapshot ini.
3. `menuSopBuilderPage`, `menuSopDokumenPage`, `menuSopKategoriPage`, dan page SOP lain tercatat pada `Id_Grup_Fitur = 3`, yaitu `LMS`.
4. Registry aktif belum memiliki kolom formal `Submodule` atau `Feature`. Hierarchy `Module → Submodule → Feature` harus dicocokkan dengan source dan keputusan maintainer, bukan ditebak dari nama page.
5. Duplicate `superAdminManajemenPeranPage` terdapat pada mapping aktif dan harus diperlakukan sebagai isu data/registry terpisah, bukan dibersihkan dalam fase struktur.
6. `adminPengumpulanDataPage`, `pengumpulanDataPage`, `menuSopRepositoryPage`, dan `menuSopSayaPage` tidak ditemukan pada source dengan identifier tersebut. Ini belum membuktikan page tidak digunakan karena bisa memakai alias, nama route lain, atau implementasi legacy.
7. Role tidak digunakan sebagai module. `Admin`, `User`, dan `System Admin` tetap merupakan authorization concern.

## Struktur target yang berlaku

Struktur target tetap menggunakan base folder Laravel/Vue existing. Tidak ada `app/Modules`.

```text
Backend:
app/Http/Controllers/<MODUL>/<Submodul>/<Fitur>/
app/Http/Requests/<MODUL>/<Submodul>/<Fitur>/
app/Models/<MODUL>/<Submodul>/<Fitur>/
app/Services/<MODUL>/<Submodul>/<Fitur>/
app/Jobs/<MODUL>/<Submodul>/<Fitur>/

Frontend:
resources/js/Pages/<MODUL>/<Submodul>/<Fitur>/
resources/js/components/<MODUL>/       # hanya reusable module-level
resources/js/composables/              # hanya reusable lintas feature
resources/js/services/                 # hanya bila diperlukan
resources/js/stores/                   # hanya bila diperlukan

Routes dan tests:
routes/<MODUL>/
tests/<jenis>/<MODUL>/<Submodul>/<Fitur>/
```

Folder hanya dibuat ketika ada file feature yang ownership-nya sudah terverifikasi. Tidak boleh membuat folder kosong, folder `Misc`, `General`, atau folder berbasis role sebagai tempat sementara.

## Status mapping source

- `VERIFIED_MODULE`: module cocok dengan master dan page map aktif.
- `SOURCE_MATCH`: identifier registry ditemukan pada route/source, tetapi hierarchy belum lengkap.
- `REVIEW_REQUIRED`: source ada, tetapi submodule/feature atau ownership teknis belum dapat dipastikan.
- `REGISTRY_SOURCE_MISMATCH`: source domain tampak berbeda dari module registry aktif; jangan dipindahkan sebelum keputusan.
- `NOT_FOUND_BY_IDENTIFIER`: identifier tidak ditemukan dengan pencarian awal; perlu pemeriksaan route alias/dynamic mapping.
- `LEGACY_KEEP`: file existing tetap di lokasi saat ini.
- `MIGRATION_LATER`: kandidat pemindahan hanya boleh dibahas dalam task terpisah.

## Mapping awal berdasarkan module registry

| Module registry | Area source yang terlihat | Status fase ini |
|---|---|---|
| ESS | `app/Http/Controllers/HCAbsensi/`, controller absensi/cuti/lembur/shift root, `resources/js/Pages/izinPage*.vue`, `SelfShift.vue`, `ShiftApproval.vue`, `KonversiLembur/`, route legacy | `VERIFIED_MODULE`; submodule/feature `REVIEW_REQUIRED`; `LEGACY_KEEP` |
| KPI | controller KPI/approval, `app/Services/KPI/`, `resources/js/Pages/KPI/`, `routes/KPI/`, `tests/Feature/KPI/` | `VERIFIED_MODULE`; banyak feature source match; `LEGACY_KEEP` |
| LMS | controller `Lms*`/`TrainingCatalog`, `app/Services/LMS/`, `resources/js/Pages/LMS/`, route `Lms*`/`TrainingCatalog/` | `VERIFIED_MODULE`; submodule/feature `REVIEW_REQUIRED`; `LEGACY_KEEP` |
| VISITOR | controller/route `ApprovalVisitor`, `LaporanVisitor`, `TransaksiPermit`, `TransaksiTamu`, `VerifikatorVisitor`; service Visitor; page VISITOR | `VERIFIED_MODULE`; source boundary relatif jelas; `LEGACY_KEEP` |
| SYSTEM | activity, authorization, database-change dashboard, internal config, shell/shared infrastructure | `VERIFIED_MODULE` hanya untuk page registry yang ada; shared ownership per file `REVIEW_REQUIRED`; `LEGACY_KEEP` |
| SOP-looking source | `app/Http/Controllers/Sop/`, `app/Services/SOP/`, `resources/js/Pages/SOP/`, `routes/Sop/`, `tests/Feature/Sop*` | `REGISTRY_SOURCE_MISMATCH`: registry snapshot menempatkan menu SOP di LMS; jangan menganggap SOP sebagai module resmi baru |

## Aturan mapping file existing

Sebelum satu file dipindahkan, mapping wajib memuat:

```text
old_path
new_path
namespace/import impact
route/import references
Module dari registry
Submodule
Feature
Layer
owner/maintainer
focused test
impacted test
rollback path
```

Jika hanya module yang terverifikasi tetapi submodule/feature belum jelas, file tetap `LEGACY_KEEP`. Tidak boleh mengisi hierarchy dengan tebakan dari nama folder atau role pengguna.

## Rencana fase berikutnya

1. Pilih satu feature konkret yang memang sedang dikerjakan.
2. Baca ulang mapping page/URL aktif untuk feature tersebut.
3. Cocokkan controller, service, page, route, dan test yang benar-benar dipakai.
4. Tetapkan `Module → Submodule → Feature → Layer` untuk feature itu saja.
5. Buat mapping old/new path sebagai proposal terpisah.
6. Minta persetujuan sebelum `git mv` atau perubahan import/namespace.
7. Lakukan satu batch kecil dan jalankan focused/impacted verification.

Tidak ada pemindahan atau perubahan source dalam fase inventory ini.
