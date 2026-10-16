# Feature Mapping: LMS-Registered SOP Jenis Dokumen (Hirarki Mutu)

Status: `NEW_FEATURE_DEV_COMPLETE`
Tanggal verifikasi registry: 2026-08-22
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem)

## Scope

Master tingkat hirarki mutu untuk dokumen SOP: Kebijakan → Prosedur → Instruksi
Kerja → Formulir. Fitur baru, bukan pemetaan implementation lama. Registry
menempatkan page ini di module `LMS`, sedangkan source, route, dan business
language memakai domain `SOP` — mismatch yang sama dengan seluruh page SOP lain
dan sengaja tidak diperbaiki di sini.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module        : LMS
Id            : 3
Nama          : Learning Management System
Flag_Aktif    : Y
Diverifikasi  : 2026-08-22 (kueri langsung, bukan snapshot dokumentasi)

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopJenisDokumenPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/jenis-dokumen
Middleware    : auth, check.jabatan:menuSopJenisDokumenPage
Change ID     : DML-2026-018-register-sop-jenis-dokumen-menu
```

## Ownership decision

```text
Registry module : LMS                          # VERIFIED_MODULE
Source domain   : SOP                          # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Master Data     # MENGIKUTI SopCategory
Feature         : SOP Document Class Master
Registry status : REGISTRY_SOURCE_MISMATCH     # sama seperti page SOP lain
File movement   : NOT_APPLICABLE (fitur baru, langsung di struktur target)
```

Page ini berbeda dari tiga page yang namanya berdekatan dan **tidak boleh**
digabung dengan salah satunya:

```text
Kategori SOP            -> topik (HSE, HR, QA); melekat di PAKET
Kebijakan & Otomasi SOP -> aturan penugasan otomatis, bukan dokumen kebijakan
Master Dokumen SOP      -> satu dokumen pada satu waktu; halaman ini mengurus
                           daftar tingkat yang berlaku bagi SELURUH dokumen
```

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php` | page, list, CRUD, opsi jenis, opsi unit kerja | `NEW` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/jenis-dokumen*` + blok opsi master-data bergerbang ganda | `NEW_IN_SHARED_FILE` |
| Service | `app/Services/LMS/SOP/SopJenisDokumenService.php` | paginate, opsi tersaring tipe, CRUD, setTipeItem, softDelete aman-pakai | `NEW` |
| Support unit kerja | `app/Services/LMS/SOP/Support/SopUnitKerja.php` | opsi divisi/sub-divisi, validasi pasangan, label, lookup massal | `NEW` |
| Penjaga pemakaian | `app/Services/LMS/SOP/SopDokumenService.php` | `pastikanJenisSesuaiTipe`, `pastikanUnitSah`, `tampilanTataKelola` | `MODIFIED` |
| Label jejak audit | `app/Services/LMS/SOP/Support/SopDiff.php` | label `Jenis dokumen` dan `Unit pemilik` | `MODIFIED` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenPage.vue` | daftar, piramid, CRUD | `NEW` |
| Modal | `resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/Components/SopJenisDokumenModal.vue` | form jenis + pemilih tipe item sah | `NEW` |
| API module | `resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/api/sopJenisDokumenApi.js` | CRUD + opsi master-data bersama | `NEW` |
| Konsumen | `resources/js/Pages/LMS/SOP/Governance/Admin/SopMasterDokumen.vue` | field jenis + unit pemilik, badge detail, dua filter | `MODIFIED` |
| Konsumen | `resources/js/Pages/LMS/SOP/Shared/components/ui/ItemFields.vue` | field jenis + unit pada blok "dokumen baru" Builder | `MODIFIED` |
| Konsumen | `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` | aturan wajib untuk `Dokumen_Baru`, penerusan ke service | `MODIFIED` |
| Konsumen | `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` | aturan wajib, filter daftar, pengayaan detail | `MODIFIED` |
| DB table | `N_SOP_Master_Jenis_Dokumen` | master tingkat | `NEW` |
| DB table | `N_SOP_Master_Jenis_Dokumen_Tipe_Item` | pemetaan jenis↔tipe item | `NEW` |
| DB column | `N_SOP_Dokumen.Jenis_Dokumen_Id`, `.Divisi_Id`, `.Sub_Divisi_Id` | klasifikasi + kepemilikan | `NEW` |
| Sumber unit | `View_Divisi_Sub_Divisi` | sumber yang sama dengan monitoring dan penjadwalan | `SHARED_LEGACY_READ` |

## Route surface

```text
GET    /sop/admin/jenis-dokumen
GET    /api/v1/sop/admin/jenis-dokumen/current
POST   /api/v1/sop/admin/jenis-dokumen/store
PUT    /api/v1/sop/admin/jenis-dokumen/update/{id}
DELETE /api/v1/sop/admin/jenis-dokumen/delete/{id}
```

Dua endpoint opsi memakai satu implementation dengan gerbang ganda:

```text
GET /api/v1/sop/admin/master-data/jenis-dokumen?tipe=DOKUMEN
GET /api/v1/sop/admin/master-data/unit-kerja

auth + check.jabatan:menuSopDokumenPage,menuSopBuilderPage
```

Pola gerbang ganda ini mengikuti preseden route token secure-PDF: dua layar
memakai daftar yang sama, dan menduplikasinya per halaman berarti dua daftar
yang cepat atau lambat tidak lagi cocok.

## Pembagian penjaga

```text
Controller (pintu manusia)  -> KEWAJIBAN mengisi jenis dan divisi
Service (semantik)          -> KESAHIHAN nilai, bagi siapa pun pemanggilnya
```

Kewajiban sengaja TIDAK di service: `SopDokumenService::buat()` juga melayani
backfill dan penyalinan paket, yang secara definisi tak punya jawaban untuk
kedua field ini. Konsekuensi yang disengaja: menyunting dokumen lama lewat form
identitas akan memaksa klasifikasinya diisi — jalur backfill terpandu untuk 41
dokumen yang lahir sebelum kolomnya ada.

Jenis wajib hanya bila tipe item tersebut PUNYA jenis yang sah. `KUIS` tak
dipetakan ke tingkat mana pun, jadi dokumen kuis tidak wajib berjenis.

## Related tests

```text
tests/Feature/LMS/SOP/SopJenisDokumenSkemaTest.php
tests/Feature/LMS/SOP/SopJenisDokumenMasterTest.php
tests/Feature/LMS/SOP/SopDokumenJenisPemilikTest.php
resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/api/sopJenisDokumenApi.test.js
```

Status coverage:

```text
RELATED_TEST
```

Belum tercakup dan jujur dinyatakan sebagai `TEST_GAP_REVIEW_REQUIRED`: test
komponen untuk `SopJenisDokumenPage.vue` dan `SopJenisDokumenModal.vue`, serta
verifikasi browser untuk alur Builder.

Catatan pengukuran: saat modul API ini ditulis, seluruh test yang mengimpor
`@/components/ui` gagal *collect* karena cache optimizer Vite masih dingin —
bukan karena dependensi hilang (`echarts` terpasang normal). Setelah dev server
berjalan, baseline `vitest` untuk folder SOP adalah 13 berkas / 23 tes gagal,
dan daftarnya identik dengan `HEAD`. Test komponen karena itu tetap mungkin
ditulis; ia hanya belum dikerjakan.

## Decision gate

Fitur selesai di development dan terverifikasi di sana. Yang menunggu pengguna:
review DDL/DML, pencatatan ke SQL Tracker, eksekusi ke production, dan
verifikasi production. Lihat `docs/changes/DDL-2026-016-*`,
`docs/changes/DML-2026-017-*`, `docs/changes/DML-2026-018-*`.

## Kode cetak (DDL-2026-030, 2026-08-22)

Master ini kini memegang DUA kode dengan peran berbeda:

| Kolom | Peran | Disunting admin |
|---|---|---|
| `Kode_Jenis` | kunci mesin — rujukan seed, test, integrasi | tidak |
| `Kode_Jenis_Dokumen` | segmen PERTAMA nomor dokumen gabungan | ya |

Tujuh kode terisi: `PLC`, `SK`, `SOP`, `IK`, `STD`, `FRM`, `MAN`. Nama panjang
dan deskripsinya masih placeholder — sengaja, karena kepanjangan resminya belum
ditetapkan dan halaman inilah tempat melengkapinya tanpa rilis.

Keunikan kode cetak dijaga filtered unique index (`WHERE ... IS NOT NULL`):
dua jenis yang memakai kode sama membuat nomor dokumen tak bisa dibaca kembali
menjadi satu jenis tertentu, sementara baris yang kodenya belum diisi tak boleh
saling menolak.
