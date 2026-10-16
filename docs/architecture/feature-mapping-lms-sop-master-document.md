# Feature Mapping: LMS-Registered SOP Master Document

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopDokumenPage` ke fitur Master Dokumen SOP existing. Registry menempatkan page ini di module `LMS`, sedangkan source, route, frontend, service, dan business language menggunakan domain `SOP`. Tidak ada source file yang dipindahkan atau diubah pada fase inventory/mapping ini.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopDokumenPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/dokumen
Middleware    : auth, check.jabatan:menuSopDokumenPage
```

Belum ada module SOP formal pada registry. Mapping live ke LMS harus dipertahankan sebagai fakta registry dan mismatch terhadap source domain harus diputuskan melalui review bisnis/registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Master Data    # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Master Document         # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Master Dokumen berbeda dari:

```text
SOP Content Library       -> menyediakan berkas/video/dokumen rujukan
SOP Builder               -> menyusun paket, section, dan item
SOP Monitoring            -> mengukur kepatuhan dan coverage
SOP Repository            -> menyediakan akses baca karyawan
```

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` | index, master listing, detail, CRUD, version lifecycle, impact, publish/rollback | `LEGACY_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/dokumen*` and shared admin gates | `SOURCE_DOMAIN_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/Admin/SopMasterDokumen.vue` | master list, detail, package usage, version, preview flows | `SOURCE_DOMAIN_KEEP` |
| Document service | `app/Services/LMS/SOP/SopDokumenService.php` | create/find, identity, versions, publish, rollback, validation | `SHARED_SOP_KEEP` |
| Control/read service | `app/Services/LMS/SOP/SopDocumentControlService.php` | register, summary, package users, version history | `SHARED_SOP_KEEP` |
| Fan-out service | `app/Services/LMS/SOP/SopDokumenFanoutService.php` | impact calculation and reconciliation across referring packages | `SHARED_SOP_KEEP` |
| Reconciliation | `app/Services/LMS/SOP/SopReconciliationService.php` | package progress/status reconciliation and notifications | `CROSS_FEATURE_DEPENDENCY` |
| File access | `app/Services/LMS/SOP/Support/SopAksesBerkas.php` | controlled content/file access | `SHARED_AUTH_KEEP` |
| Secure PDF | `app/Http/Controllers/LMS/SOP/Employee/SopSecurePdfController.php` | admin preview of draft/obsolete files | `SHARED_SECURITY_KEEP` |
| Version UI | `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenVersiModal.vue` | create/edit/publish version and approver flow | `SHARED_SOP_KEEP` |
| Flow UI | `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenAlur.vue` | version/package flow and impact presentation | `SHARED_SOP_KEEP` |
| Picker UI | `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenPickerModal.vue` | builder document selection and cross-link | `CROSS_FEATURE_DEPENDENCY` |
| Detail UI | `resources/js/Pages/LMS/SOP/Shared/components/ui/SopDokumenDetailDrawer.vue` | monitoring document detail and history | `CROSS_FEATURE_DEPENDENCY` |
| Jenis dokumen | `app/Services/LMS/SOP/SopJenisDokumenService.php` | master hirarki mutu; opsi tersaring tipe item; kode cetak segmen nomor | `CROSS_FEATURE_DEPENDENCY` |
| Perakit nomor | `app/Services/LMS/SOP/Support/SopNomorDokumen.php` | merakit tujuh segmen nomor dokumen gabungan; satu-satunya penulis format | `SHARED_SOP_KEEP` |
| Tahap versi | `app/Services/LMS/SOP/SopTahapVersiService.php` | delapan tahap proses versi, transisi, syarat masuk, dan pencapan tanggal — semuanya dari master | `SHARED_SOP_KEEP` |
| Review versi | `app/Services/LMS/SOP/SopVersiReviewService.php` | catatan review berulang; sanitasi HTML server, lampiran gambar | `SHARED_SOP_KEEP` |
| Departemen pemilik | `app/Services/SYSTEM/MasterData/Departemen/DepartemenService.php` | master kode departemen lintas modul; opsi, binding sub-divisi, segmen nomor | `CROSS_MODULE_DEPENDENCY` |
| Unit turunan | `app/Services/LMS/SOP/Support/SopUnitKerja.php` | penurunan divisi dan validasi dari `View_Divisi_Sub_Divisi`; tak lagi jadi sumber opsi kepemilikan | `SHARED_SOP_KEEP` |
| Entitas | `app/Services/SYSTEM/MasterData/Entitas/EntitasService.php` | master badan hukum lintas modul; opsi, cakupan payung, warna badge | `CROSS_MODULE_DEPENDENCY` |
| APQC | `app/Services/LMS/SOP/SopApqcService.php` | master klasifikasi proses bisnis; opsi tersaring tipe item | `CROSS_FEATURE_DEPENDENCY` |

## Dimensi tata kelola (DDL-2026-016, 2026-08-22)

Identitas dokumen bertambah tiga kolom: `Jenis_Dokumen_Id` (tingkat hirarki
mutu), `Divisi_Id`, dan `Sub_Divisi_Id` (unit pemilik). Ketiganya nullable —
41 dokumen lahir sebelum kolomnya ada.

Kewajiban mengisi hidup di pintu manusia (`validasi()` di controller ini dan
aturan `Dokumen_Baru.*` di Builder), bukan di `SopDokumenService`. Service hanya
menjaga KESAHIHAN nilai (`pastikanJenisSesuaiTipe`, `pastikanUnitSah`), karena
ia juga melayani backfill dan penyalinan paket yang secara definisi tak punya
jawaban untuk kedua field itu.

Pemetaannya sendiri ada di
`docs/architecture/feature-mapping-lms-sop-jenis-dokumen.md`.

## Entitas dan koreksi kepemilikan (DDL-2026-019, 2026-08-22)

Identitas dokumen bertambah dua kolom entitas: `Entitas_Pemilik_Id` (badan hukum
yang bertanggung jawab) dan `Entitas_Lingkup_Id` (berlaku di badan hukum mana).
Keduanya bernilai tunggal dan nullable, dan menunjuk `N_HRIS_Master_Entitas` —
master lintas modul milik `SYSTEM`, bukan milik SOP.

Bersamaan dengan itu, kewajiban **Divisi dicabut**. Yang dipakai operasional
hanya sub-divisi, dengan label **"Departemen"**; `Divisi_Id` menjadi kolom
TURUNAN yang dihitung server dari Departemen bila induknya tunggal (30 dari 32
departemen) dan dibiarkan NULL bila bercabang. Koreksi ini gratis: nol dokumen
telah mengisi kedua kolom itu saat aturannya diubah.

Entitas dan Departemen **tidak saling mengikat**: memilih ENB tidak mempersempit
daftar Departemen, dan tidak ada departemen yang "milik" sebuah entitas. Lihat
`plans/hris/2026-08-22-master-entitas-design.md`.

## Proses APQC (DDL-2026-023, 2026-08-22)

Identitas dokumen bertambah satu kolom `APQC_Id` yang menunjuk
`N_SOP_Master_APQC` — sumbu KEEMPAT, menjawab "memetakan proses bisnis yang
mana". Ia berdiri sendiri dari Tipe (berkasnya apa), Jenis Dokumen (tingkat
mutu), dan Kategori SOP (topik, melekat di paket).

Kewajiban mengisinya **diturunkan dari master**, bukan ditulis di kode: sebuah
tipe item yang tak punya satu pun APQC sah tidak pernah diminta mengisinya.
Karena itu `KUIS` tak menawarkan APQC, dan admin bisa mengubah kapan APQC wajib
lewat halaman Master APQC tanpa rilis.

## Nomor urut dokumen (DDL-2026-026, 2026-08-22)

`N_SOP_Dokumen.No_Dokumen` — nomor urut yang melekat di master, **bukan** nomor
versi. Ia ditetapkan sekali dan tidak ikut naik saat versi baru terbit.

Deretnya berjalan per KOMBINASI lima field identitas: jenis dokumen, entitas
pemilik, departemen pemilik, ruang lingkup, dan proses APQC. Sarannya `MAX + 1`
di dalam kombinasi itu, menghitung dokumen nonaktif juga supaya nomor tak pernah
didaur ulang; admin boleh menimpanya untuk penomoran lama yang sudah beredar.

Keunikan dijaga backend, bukan unique index — empat kolom pembentuk grup nullable
dan SQL Server memperlakukan NULL sebagai nilai yang sama, sehingga 40 dokumen
lama akan saling bertabrakan sejak hari pertama.

## Route surface

```text
GET    /sop/admin/dokumen
GET    /api/v1/sop/admin/dokumen/current
GET    /api/v1/sop/admin/dokumen/{id}/detail
GET    /api/v1/sop/admin/dokumen/{id}/riwayat
GET    /api/v1/sop/admin/dokumen/{id}/sebaran-nonaktif
POST   /api/v1/sop/admin/dokumen/store
PUT    /api/v1/sop/admin/dokumen/update/{id}
DELETE /api/v1/sop/admin/dokumen/delete/{id}

POST   /api/v1/sop/admin/dokumen/{id}/versi
PUT    /api/v1/sop/admin/dokumen/versi/{idVersi}
DELETE /api/v1/sop/admin/dokumen/versi/{idVersi}
GET    /api/v1/sop/admin/dokumen/versi/{idVersi}/konten
GET    /api/v1/sop/admin/dokumen/versi/{idVersi}/dampak
POST   /api/v1/sop/admin/dokumen/versi/{idVersi}/terbitkan
POST   /api/v1/sop/admin/dokumen/versi/{idVersi}/kembalikan

GET    /api/v1/sop/admin/dokumen/options/ujian
GET    /api/v1/sop/admin/dokumen/konten/{id}/pemakai
GET    /api/v1/sop/admin/dokumen/options/pengesah
POST   /api/v1/sop/admin/secure-pdf/token/{berkasId}
```

Secure-PDF token memakai satu route dengan gate:

```text
auth + check.jabatan:menuSopDokumenPage,menuSopBuilderPage
```

Ini mendukung Master Dokumen dan Builder tanpa menduplikasi implementation keamanan.

## Data and lifecycle boundary

Primary document domain tables include:

```text
N_SOP_Dokumen
N_SOP_Item_Versi
N_SOP_Paket_Item
N_SOP_Paket
```

Lifecycle:

```text
master document declaration
→ draft version
→ content/file and approver validation
→ impact preview
→ publish active version
→ package fan-out/reconciliation
→ obsolete/history or explicit rollback
```

Master document baru boleh ada tanpa package item. Ini invariant yang sah pada alur deklarasi Master Dokumen dan tidak boleh otomatis dianggap sebagai orphan defect.

## Important findings

1. `SopAdminDokumenController` memiliki boundary master listing, detail, CRUD, version operations, impact preview, publish, rollback, content usage, dan approver options. Pemindahan wholesale berisiko tanpa dekomposisi behavior terpisah.
2. `SopDokumenService` adalah canonical service yang dipanggil Builder, item services, version services, backfill, dan UAT helpers. Service ini harus tetap shared sampai seluruh caller dipetakan.
3. Publish atau rollback dapat berdampak pada banyak package yang mereferensikan dokumen yang sama. `SopDokumenFanoutService` dan `SopReconciliationService` adalah cross-feature dependencies.
4. Content/file access memiliki security contract terpisah. Admin dapat melihat draft dan obsolete, sedangkan employee reader sengaja ditolak untuk state tersebut.
5. Approver lookup dan HC Learn exam options digunakan ulang oleh Builder. Route gate berbeda, tetapi implementation tidak boleh diduplikasi.
6. Frontend terhubung ke Builder untuk package usage dan dipakai oleh shared UI dari Builder dan Monitoring.
7. Delete document/version adalah operasi destruktif; publish/rollback adalah operasi state-changing dengan audit dan dampak downstream.

## Related tests

```text
tests/Feature/LMS/SOP/SopMasterDokumenPageTest.php
tests/Feature/LMS/SOP/SopDokumenSkemaTest.php
tests/Feature/LMS/SOP/SopDokumenBackfillTest.php
tests/Feature/LMS/SOP/SopDokumenBaselineTest.php
tests/Feature/LMS/SOP/SopDokumenVerifyTest.php
tests/Feature/LMS/SOP/SopDokumenPenulisVersiTest.php
tests/Feature/LMS/SOP/SopDokumenFase2Test.php
tests/Feature/LMS/SOP/SopDokumenFanoutTest.php
tests/Feature/LMS/SOP/SopDokumenJoinVersiTest.php
tests/Feature/LMS/SOP/SopDokumenBerbagiTest.php
tests/Feature/LMS/SOP/SopRiwayatDokumenTest.php
tests/Feature/LMS/SOP/SopSuntingDraftTest.php
tests/Feature/LMS/SOP/SopPublishPaketVsRevisiDokumenTest.php
tests/Feature/LMS/SOP/SopPratinjauAdminPdfTest.php
tests/Feature/LMS/SOP/Uat/Alur007SiklusVersiDokumenTest.php
tests/Feature/LMS/SOP/Uat/Alur044SiklusHidupLanjutTest.php
tests/Feature/LMS/SOP/SopDokumenJenisPemilikTest.php
tests/Feature/LMS/SOP/SopDokumenEntitasTest.php
tests/Feature/SYSTEM/MasterData/EntitasTest.php
tests/Feature/LMS/SOP/SopApqcTest.php
tests/Feature/LMS/SOP/SopNoDokumenTest.php
```

Frontend component tests juga mencakup document picker, version modal, metadata, version panel, dan document flow components.

Status coverage:

```text
RELATED_TEST
```

Coverage cukup luas, tetapi browser-level matrix lengkap untuk authorization, seluruh tab, provider failure, publish/rollback UI, secure-PDF preview, dan cross-package fan-out masih `TEST_GAP_REVIEW_REQUIRED`. Mapping ini tidak mengklaim full regression.

## Proposed normalized paths for a future move

Proposal only; jangan membuat atau memakai path ini sekarang:

```text
app/Http/Controllers/SOP/Governance/MasterDocument/SopMasterDokumenController.php
app/Services/SOP/Governance/MasterDocument/
routes/SOP/Governance/MasterDocument/
resources/js/Pages/SOP/Governance/MasterDocument/
tests/<type>/SOP/Governance/MasterDocument/
```

Canonical shared services tidak boleh dipindahkan sebelum caller Builder, Monitoring, Repository, item/version services, commands, dan UAT helpers memiliki mapping yang disetujui.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Sebelum physical migration, selesaikan ownership LMS-versus-SOP pada registry, setujui dekomposisi Master Dokumen, pertahankan dual-route authorization secure-PDF, inventarisasi seluruh shared service callers, dan tetapkan focused/impacted tests serta rollback path. Source path saat ini tetap menjadi state yang aman.

## Kode departemen sebagai acuan kepemilikan (DDL-2026-027, 2026-08-22)

`N_SOP_Dokumen.Departemen_Id` menunjuk `N_HRIS_Master_Departemen` — master
lintas modul milik `SYSTEM`, sejajar dengan Entitas. Sejak perubahan ini
**dialah acuan kepemilikan**; `Sub_Divisi_Id` dan `Divisi_Id` menjadi kolom
TURUNAN yang dihitung server dari binding departemen dan ditolak bila dikirim
klien.

Alasannya penomoran: nomor dokumen gabungan berbentuk
`Jenis/Entitas/Departemen/RuangLingkup/NoAPQC/NoDokumen/Revisi`, dan segmen
ketiga memakai **kode**, bukan nama. Daftar kode perusahaan lebih rinci daripada
struktur HRIS yang tersimpan (52 versus 32), sehingga 35 kode tak punya
sub-divisi untuk ditumpangi — memaksakan kodenya ke `HRIS_Sub_Divisi` akan
membuat mayoritas kode mustahil dipakai.

**Lingkup deret nomor ikut berpindah** dari `Sub_Divisi_Id` ke `Departemen_Id`
(`SopDokumenService::KOLOM_GRUP_NOMOR`). Tanpa itu, arti "kombinasi yang sama"
berubah diam-diam: dua departemen berbeda yang sama-sama tak punya binding akan
berbagi satu deret hanya karena turunannya sama-sama `NULL`.

Binding ke sub-divisi tetap ada dan tetap opsional — ia satu-satunya jembatan ke
data karyawan lewat `ID_Divisi_Sub_Divisi`, yang dibutuhkan
`SopMonitoringPeopleService` dan `SopPenjadwalanTargetService`. Ia tak pernah
ditebak; lihat `docs/database/tables/hris/n-hris-master-departemen.md`.

## Nomor dokumen gabungan (DDL-2026-030, 2026-08-22)

Tujuh segmen, urutan tetap:

```text
Jenis / Entitas / Departemen / RuangLingkup / NoAPQC / NoDokumen / Revisi
IK    / ENB     / PROD       / EHG          / 09     / 03        / 01
```

| Segmen | Sumber |
|---|---|
| Jenis | `N_SOP_Master_Jenis_Dokumen.Kode_Jenis_Dokumen` |
| Entitas | `N_HRIS_Master_Entitas.Kode_Entitas` (pemilik) |
| Departemen | `N_HRIS_Master_Departemen.Kode_Departemen` |
| Ruang lingkup | `N_HRIS_Master_Entitas.Kode_Entitas` (lingkup) |
| No. APQC | `N_SOP_Master_APQC.Kode_Proses` |
| No. dokumen | `N_SOP_Dokumen.No_Dokumen`, dua digit |
| Revisi | `Versi_Ke` dari versi yang **berlaku**, dua digit |

**DIHITUNG, TIDAK DISIMPAN.** Setiap segmen sudah punya kolomnya sendiri, dan
segmen terakhir bergerak setiap kali versi baru terbit. Menyimpannya sebagai
satu kolom teks berarti menyimpan salinan yang basi sejak revisi berikutnya.

**SATU PENULIS.** Layar juga menampilkannya, tetapi rumusnya TIDAK disalin ke
JavaScript — pratinjau di form pun diambil dari `saran-nomor`. Dua implementasi
untuk satu format pasti menyimpang, dan yang menyimpang di sini adalah nomor
yang tercetak di dokumen resmi.

**Satu segmen kosong membatalkan seluruh nomor.** `rakit()` mengembalikan
`null`; yang ditampilkan sebagai gantinya adalah `pratinjau()` dengan `??` di
posisi yang kosong, plus `segmenKosong()` yang menyebut apa persisnya yang
kurang. Nomor separuh jadi lebih berbahaya daripada tak ada nomor: ia terlihat
sah, tercetak, lalu dipakai merujuk dokumen yang identitasnya belum lengkap.

**Nol itu sah** di kedua segmen angka — penomoran dokumen maupun revisi
sama-sama mulai dari nol, sehingga cek falsy akan menghapus segmen `00`.

### `Nomor_SOP_Resmi` ADALAH nomor rakitan

Bukan nomor kedua di sebelahnya. Tidak ada dua kotak nomor untuk satu dokumen —
form tak lagi punya isian bebas untuk `Nomor_SOP_Resmi`, baik di Master Dokumen
maupun di Builder, dan kedua controller berhenti menerimanya dari klien.

Karena itu nomor rakitan **disimpan**, bukan sekadar dihitung saat ditampilkan:
kolom ini dibaca 45 berkas — monitoring, ekspor, repository, detail karyawan,
dataset kepatuhan — dan merakitnya hanya di layar admin akan membuat seluruh
sisanya kosong. `SopDokumenService::segarkanNomorResmi()` menuliskannya lalu
memanggil `sinkronIdentitasKeItem()`, karena kolom item adalah cerminan dengan
satu penulis.

Dipanggil di setiap titik yang menggeser salah satu segmen:

| Titik | Segmen yang bergeser |
|---|---|
| `buat()` | seluruhnya |
| `ubah()` | identitas |
| publish versi | Revisi |
| rollback versi | Revisi |

**Tidak pernah mengosongkan.** Selama nomornya belum bisa dirakit utuh, nilai
yang sudah ada dibiarkan apa adanya: 16 dokumen lama menyimpan penomoran yang
diketik tangan dan masih beredar di luar sistem. Menimpanya dengan `null`
berarti menghapus satu-satunya jejak nomor itu.

**Balapan permintaan dijaga di klien.** Memilih entitas pemilik ikut mengubah
ruang lingkup, sehingga dua permintaan `saran-nomor` terbang hampir bersamaan.
Tanpa penanda urutan, respons yang berangkat lebih dulu bisa tiba belakangan dan
menimpa hasil yang lebih baru — bug yang benar-benar terjadi dan tertangkap
probe browser, dan sejak kotak ini menampilkan nomor resmi, tertimpa berarti
memperlihatkan nomor yang salah.

## Tahap proses versi (DDL-2026-032, 2026-08-22)

Versi kini bisa **disiapkan lebih dulu tanpa berkas**, lalu digeser melewati
delapan tahap: Drafting → Review → Approval → Approved → Filing and Registered →
Obsolete, dengan Hold dan Cancel sebagai cabang.

**Melapisi `Status`, tidak menggantinya.** `N_SOP_Item_Versi.Status` dibaca
rilis paket, progres karyawan, monitoring, dan ekspor; ia tetap empat nilai
teknis. Pemetaan tahap ke Status disimpan sebagai data di
`N_SOP_Master_Tahap_Versi.Status_Teknis`, dan nilainya wajib salah satu dari
`DRAFT`/`PUBLISHED`/`OBSOLETE` — `CK_N_SOP_Versi_Status` tak mengenal yang lain.

**Syarat masuk tahap dan transisinya adalah data**, bukan cabang di kode: alur
persetujuan berubah lebih sering daripada perangkat lunaknya. Yang menegakkan
"Filing and Registered hanya setelah berkasnya diunggah" adalah kolom
`Butuh_Berkas` pada masternya.

**Filing and Registered ditolak lewat pemindahan tahap** dan diarahkan ke jalur
terbit — di sanalah pointer dokumen, versi lama, nomor resmi, dan paket perujuk
ikut disesuaikan. Sebaliknya, penerbitan dan rollback menggeser `Tahap_Id`
sendiri, sehingga tahap dan Status tak pernah menyimpang.

Layar menawarkan tujuan yang dibaca dari **server**, bukan yang dihafal klien:
alurnya bisa berubah tanpa rilis, dan layar yang menghafalnya akan menawarkan
langkah yang nanti ditolak.

## Tanggal proses versi (DDL-2026-034, 2026-08-23)

Identitas versi bertambah empat tanggal:

| Tanggal | Dicap saat | Kolom |
|---|---|---|
| Permintaan | masuk tahap **Review** | `Tanggal_Permintaan` |
| Respon | masuk tahap **Approval** | `Tanggal_Respon` |
| Akhir Approval | masuk tahap **Approved** | `Tanggal_Akhir_Approval` |
| Kadaluarsa | tak dicap — murni diketik | `Tanggal_Kadaluarsa` |

`Tanggal_Kadaluarsa` **sudah ada sejak lama** tetapi tak pernah punya form
maupun penulis; enam dari 76 versi terisi tanpa satu pun jalur yang menyentuhnya.
Ia dimunculkan, bukan diduplikasi — kolom kedua dengan arti yang sama akan
membuat enam baris itu tak pernah ikut terbaca.

**Aturan pencapan adalah data**, sama seperti `Status_Teknis` dan `Butuh_Berkas`:
`N_SOP_Master_Tahap_Versi.Cap_Tanggal` menyimpan nama kolom yang dicap. Nilainya
disaring daftar putih di `SopTahapVersiService::KOLOM_CAP` — nama kolom yang
datang dari data tak pernah dipercaya begitu saja, dan salah ketik di master
berakhir sebagai pencapan yang dilewati, bukan kueri yang menyebut kolom karangan.

**Dicap hanya bila kolomnya masih kosong.** Itu melindungi tanggal asli saat
versi bolak-balik antar tahap, sekaligus koreksi manual admin. Jejak setiap
perpindahan tetap utuh di `N_SOP_Log`.

## Tanggal obsolete (DDL-2026-036, 2026-08-23)

Melengkapi tiga tanggal proses. Berbeda dari ketiganya, tahap Obsolete dicapai
lewat **dua jalur**, dan yang dominan bukan pemindahan tahap melainkan
penerbitan versi lain — `SopItemVersionService` meng-obsolete-kan versi berlaku
sebelumnya. Pencapan karena itu dipasang di **ketiga titik**, masing-masing
dengan `whereNull`.

Backfill 16 versi lama **diturunkan**, bukan ditebak: tanggalnya adalah
`Tanggal_Publish` penerus terawal. Empat belas terisi; dua yang tak punya
penerus sengaja dibiarkan kosong.

## Catatan review per versi (DDL-2026-038, 2026-08-23)

Satu versi bisa direview berkali-kali — Review 1, 2, 3 — masing-masing dengan
tanggal, hasil ber-HTML, dan lampiran gambar opsional. Disimpan di tabel anak
`N_SOP_Item_Versi_Review` dan `..._Lampiran`.

**Sanitasi hidup di server.** `SopHtml::sanitize()` memakai daftar putih 15 tag
yang tak memuat `<img>`; editor di layar juga menyaring, tetapi itu kenyamanan —
panggilan API langsung tak pernah melewatinya, dan ada tes yang menembak endpoint
dengan payload XSS untuk membuktikannya.

**Disimpan lewat tombolnya sendiri**, terpisah dari penyimpanan versi: isinya
bisa panjang dan lampirannya berat, dan satu gambar bermasalah tak boleh
menggagalkan penyuntingan judul.

**Daftar ditulis ulang, baris lama dinonaktifkan** — lampirannya menunjuk berkas
di GCS, dan baris yang lenyap membuat berkas itu yatim.

Batas yang diketahui: jalur unggah gambar menulis ke disk `gcs`, yang
kredensialnya rusak di environment development ini, sehingga **belum
terverifikasi end-to-end**.

## Alur berlangkah versi (DDL-2026-039, 2026-08-23)

Layar versi kini menampilkan perjalanan, bukan satu form panjang. Empat langkah —
Pengajuan, Review, Pengesahan, Terbit & Sebar — dengan urutan, kelompok field,
dan syaratnya seluruhnya **diturunkan dari master tahap**, bukan ditulis kode.

**Pengesahan satu blok, di langkah Pengesahan.** Rancangan awal memisahkan
RENCANA dan REALISASI; konsep itu dibatalkan 2026-08-24. Tanggal Permintaan dan
Tanggal Respon berada di Pengajuan. Pengesah diisi satu kali pada langkah
Pengesahan.

**Syarat generik per tahap.** `N_SOP_Master_Tahap_Versi_Syarat` menampung
syarat sebagai baris data: Approval menuntut catatan review, Approved menuntut
pengesah, Filing menuntut berkas final + pengesah. Menambah jenis syarat baru
tak lagi butuh kolom `Butuh_X`, `if` di service, maupun `if` di layar.

**Berkas final pindah ke Terbit & Sebar.** Approved hanya menuntut pengesah;
Filing and Registered menuntut berkas + pengesah.

**Tidak ada pintu lompat dari Builder.** Builder tidak lagi membuat lalu
menerbitkan dokumen dalam satu permintaan — pagar server menolak `Dokumen_Baru`
dan mengarahkan ke Master. Alur berlangkah ditegakkan pada transisi tahap;
gerbang publish lama tetap kompatibel untuk jalur paket/Builder.

**Verifikasi browser selesai.** Setelah batas inotify dinaikkan, empat langkah
tergambar tanpa galat konsol/HTTP: Pengajuan (nomor, ringkasan, Tanggal
Permintaan/Respon), Review, Pengesahan, Terbit & Sebar (berkas final).

## Wizard versi di halaman sendiri (2026-08-26)

Pendefinisian versi **keluar dari modal** dan menempati halamannya sendiri.
Desain: `plans/sop/2026-08-26-wizard-versi-dokumen-design.md`.

### Gerbang: memakai ulang, bukan menambah

```text
Route   : GET sop/admin/dokumen/{dokumen}/versi/{versi?}
Nama    : sop.admin.dokumen.versi
Gerbang : check.jabatan:menuSopDokumenPage      # KUNCI YANG SUDAH ADA
Sidebar : tidak — halaman rincian, bukan menu
```

Kunci akses BARU menuntut baris `HRIS_Page_Access` yang ditanam manual, dan
sampai itu terjadi halamannya menolak semua orang. Halaman ini karena itu
memakai ulang kunci Master Dokumen: siapa pun yang boleh membuka daftar dokumen
boleh mendefinisikan versinya. **Nol baris registry baru.**

### Peta implementasi tambahan

```text
app/Http/Controllers/LMS/SOP/Governance/SopAdminVersiWizardController.php
app/Services/LMS/SOP/SopTahapVersiService.php          # alurVersi(), langkahBerjalan()
resources/js/Pages/LMS/SOP/Governance/Admin/SopVersiWizard.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/useVersiAlur.js
resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiStepper.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/VersiRingkasan.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengajuan.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahReview.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPengesahan.vue
resources/js/Pages/LMS/SOP/Governance/Admin/versi/LangkahPemberkasan.vue
```

`SopDokumenVersiModal.vue` menyusut **2.326 → 1.051 baris** dan kembali menjadi
apa yang namanya janjikan: daftar riwayat versi beserta pintu masuknya.

### Server memutuskan langkah, layar menggambar

`GET /api/v1/sop/admin/dokumen/versi/{id}/langkah` kini juga mengirim
`Langkah_Aktif`, `Terkunci`, `Kurang`, `Aksi_Utama`, dan `Aksi_Lain`. Kontrak
lama (`Tujuan`, `Syarat_Kini`) dipertahankan — Builder masih membacanya.

Aturannya **posisional**, bukan berbasis isi:

```text
Langkah_Aktif = langkah tempat tahap berjalan berada
Terkunci      = Nomor > langkah berjalan
```

Sebelumnya layar memilih "langkah pertama yang `Lengkap`-nya false". Karena
langkah Pengajuan selalu dilaporkan lengkap, layar tak pernah berhenti di sana:
tiap penyimpanan melemparkan admin ke Review atau Pengesahan, dan pekerjaannya
tampak tak tersimpan padahal tersimpan. `Lengkap` tetap dilaporkan, tetapi kini
hanya sebagai keterangan di kolom ringkasan.

### Langkah 4 bernama Pemberkasan; `APPROVED` hidup lagi di sana

`DML-2026-048` menghidupkan `APPROVED` dan memetakannya ke **langkah 4**
(`Kelompok = TERBIT`), bukan langkah 3 seperti sebelum `DML-2026-042`. Dengan
begitu tetap berlaku satu tombol lanjut per langkah — keluhan klik-ganda yang
membuat 042 mematikannya justru terjawab. Terbit kini hanya sah dari
`APPROVED`, dan `terbitkanVersi()` menolak penerbitan dari tahap yang tak punya
transisi aktif ke `FILING_REGISTERED`.

### `Tanggal_Kadaluarsa` berhenti diminta

Masa berlaku SOP dicabut 2026-08-15 dan tak ada satu pun aturan SOP yang
bertindak atasnya; satu-satunya pembaca yang tersisa adalah register kontrol
dokumen yang mencetaknya ulang. Inputnya dibuang dari layar dan validasi;
**kolom DB dan kolom ekspornya dibiarkan**, sehingga enam versi lama yang sudah
terisi tetap tercetak seperti semula.

### Masa berlaku diminta lagi (2026-08-27)

Keputusan di atas **dibalik sebagian**. Yang berubah bukan pendapat melainkan
fakta: alasan pencabutan 2026-08-15 adalah "tak ada satu pun aturan SOP yang
bertindak atasnya", dan `DDL-2026-049` / `DML-2026-050` memberi kolom itu aturan
yang bertindak.

| | |
|---|---|
| `Tanggal_Kadaluarsa` | diminta lagi; **tidak** diduplikasi, kolom lama dipakai |
| `Tanggal_Review_Berikutnya` | kolom baru pada `N_SOP_Item_Versi` |
| `Masa_Berlaku_Bulan`, `Siklus_Review_Bulan` | baku per jenis dokumen, di-seed 24/12 |

Ketiganya menjadi **syarat transisi** `FILING_REGISTERED` — dijaga saat versi
meninggalkan Pemberkasan menuju terbit, bukan saat draft disimpan. Admin cukup
mengisi `Tanggal_Berlaku`; dua sisanya dilengkapi baku oleh
`SopMasaBerlakuService` pada `tambahVersi()` dan `ubahVersi()`, sehingga gerbang
yang bertambah tidak berubah menjadi tiga pekerjaan tambahan.

**Kadaluarsa tidak pernah meng-obsolete versi.** `OBSOLETE` tetap milik satu
sebab saja — terbitnya versi pengganti. Lensa karyawan menolak versi `OBSOLETE`
(`SopSecurePdfController` menyebutnya "sudah dicabut"), jadi meng-obsolete versi
kadaluarsa berarti memberi pembacanya 403, bukan peringatan. Keadaan "lewat
tanggal, belum ada pengganti, masih wajib dibaca" adalah status **keberlakuan**
`KEDALUWARSA`, dan itu dihidupkan pada slice B.

`SopDokumenKeberlakuanTest::test_tanggal_lama_tak_lagi_menggugurkan_keberlakuan`
sengaja **belum** disentuh: pada slice A tanggal kadaluarsa memang belum
menggugurkan apa pun. Tes itulah yang harus merah lebih dulu di slice B — persis
seperti komentar di dalamnya berharap.

Rancangan lengkap beserta slice B/C/D:
`plans/sop/2026-08-27-masa-berlaku-versi-design.md`.

### Langkah Review yang kosong terhitung selesai (2026-08-27)

`Lengkap` untuk kelompok `REVIEW` tak lagi berarti "ada catatan", melainkan
"ada catatan **atau** langkahnya sudah dilewati". Ringkasannya ikut: `0 putaran
review` diganti `tanpa catatan review` begitu langkahnya terlewati.

Gerbangnya sendiri sudah dicabut `DML-2026-042`; yang tersisa cuma kalimatnya,
dan kalimat itulah yang dibaca admin sebagai pekerjaan tertinggal pada versi
yang hanya diperpanjang.

### Data percobaan dihapus

`DML-2026-047` mengosongkan seluruh rantai dokumen & paket beserta jejaknya
(4.135 baris) di development. Master — kategori, APQC, jenis dokumen, tahap
versi, setelan — tidak disentuh.

### Penonaktifan dokumen (DDL-2026-055, 2026-08-27)

Menonaktifkan dokumen yang sedang dipakai **tak lagi ditolak**. Larangannya
dicabut, dan bersamanya alasan larangan itu juga hilang.

**Apa yang berubah.** Penonaktifan menulis `Nonaktif_At/By/Catatan/Paket/Orang`
dan **tidak** membalik `Flag_Aktif`. `Flag_Aktif` dokumen ditanyakan 28 kueri,
tiga di antaranya jalur baca karyawan (`SopProgressReadService:1639`,
`SopRepositoryReadService:550`, `SopJangkauanPublikService:71`); membaliknya
akan mencabut dokumen dari paket yang sedang berjalan. Statusnya hidup di
`Nonaktif_At`: NULL berarti aktif.

**Yang benar-benar tertutup hanya satu tempat:** pemilih dokumen di Document
Builder (`SopAdminBuilderController::dokumenOptions`). Pintu keduanya
`pastikanDokumenSiap()`, yang menolak permintaan yang membawa id dokumen
langsung — pemilih hanya menyembunyikan, tidak menolak.

**Yang TIDAK terjadi:** item paket tak disentuh, penjadwalan tak dicabut,
progres karyawan tak ditutup. Kewajiban yang sudah terbit berjalan terus sampai
adminnya melepas sendiri.

**Dua angka, bukan satu.** `SopDokumenNonaktifService::sebaran()` melaporkan
`orang` (kepala yang berbeda) dan `penugasan` (penempatan) secara terpisah;
seseorang bisa memegang dokumen lewat dua paket. Keduanya menyaring
`Flag_Cabut_Akses = 'T'` karena pencabutan akses menyetel kolom itu ke `'Y'` dan
**membiarkan** `Flag_Aktif = 'Y'` — menyaring `Flag_Aktif` saja ikut menghitung
orang yang dokumennya sudah ditarik. `total_karyawan` di `detail()` diperbaiki
dengan saringan yang sama.

**Cuplikan, bukan turunan.** `Nonaktif_Paket` dan `Nonaktif_Orang` dibekukan
pada detik penonaktifan justru karena paket tetap berjalan sesudahnya.

**Batas yang diketahui, dan sengaja dibiarkan:**

| Jalur | Perilaku |
|---|---|
| `SopItemService::tautkanDokumen()` lewat penyalinan item | Tak menolak dokumen nonaktif. Menyalin paket adalah menyalin susunan yang sudah ada, bukan memilih dokumen baru; menolaknya akan membuat kloning paket gagal seluruhnya gara-gara satu dokumen yang dipensiunkan. |
| `SopDatasetDokumen` (Export Studio) | Tetap memuat dokumen nonaktif. Ekspor ini dibaca untuk kepatuhan, dan paket pemakainya memang masih berjalan. |
| Baris warisan `Flag_Aktif='T'` | Tidak disentuh dan tidak muncul di pil mana pun. Semuanya dimatikan sewaktu belum dipakai, jadi tak ada jejak sebaran yang bisa dipulihkan secara jujur. `jejak()` melaporkannya dengan `Tanpa_Jejak = true`. |

**Tidak ada jalur menghidupkan kembali.** Keputusan produk 2026-08-27: dokumen
yang dinonaktifkan tidak bisa diaktifkan lagi lewat layar, dan paket maupun
penugasan tidak pernah dipulihkan.

Pil **Dibatalkan** di Master Dokumen (`tahap=NONAKTIF`) mengurutkan dari yang
paling terakhir dinonaktifkan. Daftar baku dan hitungan tahap lainnya menyaring
`Nonaktif_At IS NULL` supaya dokumen pensiun tak menuduh dirinya "Belum Dipakai"
atau "Perhatian".
