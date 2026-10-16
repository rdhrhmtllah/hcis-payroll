# Mencabut Kategori dari modul DMS/SOP

Tanggal: 2026-08-28
Status: DESIGN — menunggu tinjauan pengguna
Modul: DMS (grup fitur 7) / submodul SOP

## Ringkasan

Kategori SOP dicabut dari modul: halaman masternya dibongkar, kewajiban mengisinya
di Builder dihapus, saringan dan sumbu pengelompokannya dicabut dari Perpustakaan,
Monitoring, Penjadwalan, dan Export Studio.

Tabel `N_SOP_Kategori` dan kolom `N_SOP_Master_Paket.Id_Kategori_SOP` **tidak
dihapus**. Keduanya tetap berdiri dengan isinya yang sekarang; kode berhenti
membacanya dan berhenti menulisinya.

> **KOREKSI 28 Agu 2026 — janji "nol DDL" di rancangan ini KELIRU.**
>
> Rancangan awal beralasan kolomnya cukup dibiarkan berdiri dan berhenti diisi.
> Kolomnya ternyata `NOT NULL`, tanpa `DEFAULT` dan tanpa FK, pada **dua** tabel:
> `N_SOP_Master_Paket` dan `N_SOP_Paket_Draft`. "Berhenti mengisi" karena itu
> bukan pilihan yang tersedia — setiap INSERT paket baru gagal dengan
> `Cannot insert the value NULL into column 'Id_Kategori_SOP'`.
>
> Ini hanya terlihat saat uji dijalankan, bukan saat kode dibaca. Akibatnya
> **DDL-2026-059** dibuat dan dijalankan: `ALTER COLUMN ... INT NULL` pada kedua
> tabel. Melonggarkan batasan, bukan mengetatkan — nol baris berubah, nol data
> hilang, kolomnya tetap ada.
>
> Melonggarkan dipilih daripada mengisi nilai sentinel: paket baru yang menunjuk
> baris master yang tak lagi dikelola siapa pun akan terhitung sebagai kategori
> sungguhan di laporan lama.

## Keputusan yang mendasari

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Kedalaman | Halaman **dan** peranannya di alur | Mencabut halaman saja meninggalkan Builder yang tetap mewajibkan kategori dari master yang tak bisa dibuka |
| Sifat | Cabut kode, permanen | Sakelar `N_SOP_Setting` membuat setiap layar punya dua bentuk yang dua-duanya harus benar dan diuji; cabang mati cenderung membusuk. Membalikkan = revert commit |
| Perpustakaan | Daftar paket datar, tanpa folder | Dari empat sumbu klasifikasi SOP, hanya Kategori dan Prioritas yang melekat di **paket**; APQC dan Jenis Dokumen melekat di dokumen/item. Prioritas bukan topik, jadi tak layak jadi folder |
| Data lama | Dibiarkan di DB | Riwayat tetap terbaca kalau suatu saat kategori dibutuhkan lagi; DROP juga dilarang aturan proyek |
| Template Export tersimpan | Dibersihkan lewat DML | Pemilik template tak perlu turun tangan; laporan tetap jalan, hanya kehilangan satu kolom |

## Keadaan sekarang (terverifikasi di `Web_HRIS`, dev, 2026-08-28)

```
N_SOP_Kategori aktif ................ 8 baris
N_SOP_Master_Paket berkategori ...... 7 dari 7  (kolom terisi penuh)
N_HRIS_Report_Template menyebut kategori .. 9 dari 14  (aktif='Y': Id 95, 99, 100)
```

Menu di `N_HRIS_Grup_Fitur_Map`, grup 7 (DMS), 13 halaman SOP:

```
133 | menuSopKategoriPage  | sidebar=Y | aktif=Y
      menuSopPrioritasPage → TIDAK ADA BARISNYA
```

Grant di `HRIS_Page_Access` untuk `menuSopKategoriPage`: user 4 dan user 622.

## Risiko utama: halaman Prioritas menumpang izin Kategori

`routes/LMS/SOP/Governance/SopGovernance.php:105` menjaga halaman Prioritas dengan
`check.jabatan:menuSopPrioritasPage,menuSopKategoriPage`. Karena
`menuSopPrioritasPage` tidak pernah didaftarkan sebagai baris menu maupun grant,
satu-satunya kunci yang benar-benar membuka halaman Prioritas adalah kunci
Kategori.

**Akibat kalau urutannya terbalik:** mematikan baris menu 133 membuat halaman
Prioritas 403 untuk semua orang, termasuk dua pengguna yang sekarang memegang
grant.

**Yang sudah beres:** halaman Prioritas **sudah berdiri sendiri di kode** —
`SopAdminPrioritasController`, `SopPrioritasService`, `SopPrioritas.vue`, route
`sop/admin/prioritas` + 4 API, dan kunci `menuSopPrioritasPage` sudah dipakai di
gerbangnya. Tak ada halaman yang perlu dibangun.

**Yang belum:** tiga baris pendaftarannya di DB. Terverifikasi di dev, 2026-08-28:

```
N_HRIS_Menus          menuSopPrioritasPage → 0 baris
N_HRIS_Grup_Fitur_Map menuSopPrioritasPage → 0 baris
HRIS_Page_Access      menuSopPrioritasPage → 0 baris
```

Skripnya sudah ditulis tapi tak pernah dijalankan: `plans/sop/sql/37_menu_prioritas_sop.sql`
— idempoten, dan menyalin seluruh kolom header dari baris Kategori.

**Skrip 37 basi di tiga titik, wajib disegarkan sebelum dipakai:**

1. `Sort_Menu` di-hardcode `2` dengan alasan "slot itu kosong (1, 3, 4, 5, 6, 7, 8)".
   Sudah tidak: menu admin DMS kini 1–11 tanpa lubang, dan slot 2 justru milik
   `menuSopKategoriPage`. **Keputusan: biarkan `Sort_Menu = 2`.** Kategori kosong
   dari slot itu di tahap 4–5, sehingga urutan akhirnya tetap 1–11 tanpa lubang.
   Tumpang-tindihnya hanya berlangsung selama langkah antara, dan pada rentang itu
   dua menu bersebelahan dengan urutan sesama-2 yang tak menentu — tidak merusak
   apa pun.
2. Komentar headernya menyebut "seluruh menu SOP dipetakan ke grup 3 (Learning
   Management System)". Sejak DML-2026-044 SOP ada di **grup 7 (DMS)**. SQL-nya
   sendiri aman karena menyalin `Id_Grup_Fitur` dari baris Kategori; hanya
   komentarnya yang menyesatkan dan harus dikoreksi.
3. Ketiga bloknya membaca `WHERE Jenis_page = 'menuSopKategoriPage'` sebagai sumber
   salinan. Begitu Kategori dimatikan, skrip ini mandul.

**Akibat (3) urutannya mengikat dan tak bisa dibalik:** skrip 37 dijalankan lebih
dulu, `flushMenuCache`, verifikasi Prioritas terbuka — baru Kategori dicabut.
Setiap tahap DML harus bisa gagal sendirian; jangan digabung jadi satu MERGE.

## Perubahan per lapis

### Lapis 1 — Halaman master dibongkar

Dihapus:
- `app/Http/Controllers/LMS/SOP/Governance/SopCategory/SopCategoryController.php`
- `app/Services/LMS/SOP/SopKategoriService.php`
- `resources/js/Pages/LMS/SOP/Governance/SopCategory/` seluruhnya — 10 berkas: `SopCategoryPage.vue`, `Components/SopCategoryCard.vue`, `SopCategoryDeleteDialog.vue`, `SopCategoryDetailDialog.vue`, `SopCategoryModal.vue`, `SopCategoryStats.vue`, `api/sopCategoryApi.js`, dan 3 berkas uji (`SopCategoryPage.test.js`, `SopCategoryCard.test.js`, `SopCategoryStats.test.js`)

Diubah:
- `routes/LMS/SOP/Governance/SopGovernance.php:30-41` — blok `/* KATEGORI */` dan 5 route-nya dihapus
- `routes/LMS/SOP/Governance/SopGovernance.php:105` — gerbang Prioritas jadi `check.jabatan:menuSopPrioritasPage` saja, komentar `:93-104` disesuaikan
- `app/Http/Controllers/LMS/SOP/Governance/SopAdminPrioritasController.php:19` — komentar "ditumpangkan pada hak akses Kategori" sudah tidak berlaku

### Lapis 2 — Kewajiban di Builder dicabut

`SopAdminBuilderController`:
- `:310` dan `:351` — dua gerbang `"Kategori tidak valid"` dihapus (ingat aturan dua pintu: keduanya, bukan salah satu)
- `:2045` — `'Id_Kategori_SOP' => 'required|string'` dihapus dari validasi
- `:1999-2010` — endpoint `optionsKategori()` dan route `/options/kategori` (`SopGovernance.php:223`) dihapus
- `:229-230` — penyusun `Id_Kategori_SOP` / `Nama_Kategori` pada pratinjau draf dihapus
- `:195-201` — kunci `kategori` dibuang dari perakit saringan list paket

`SopPaketService` (10 rujukan), `SopPaketDraftService` (7), `SopPaketVersionService` (1):
- berhenti menulis `Id_Kategori_SOP` pada `buat()`, `klon()`, dan `simpan versi`
- buang `leftJoin N_SOP_Kategori` dan kolom `k.Nama_Kategori` dari kueri baca
- buang cabang saringan `$filters['kategori']`

`SopDiff` — kategori dikeluarkan dari perbandingan versi.

Kolom `Id_Kategori_SOP` tetap ada dan tetap `NULL`-able; paket baru cukup tidak
mengisinya. Tak ada DDL.

### Lapis 3 — Saringan dan sumbu dicabut

Backend:
- `Support/SaringanMonitoring.php` — `kategori` dibuang dari deteksi "ada saringan aktif" dan dari kombinasi baris
- `SopMonitoringPeopleService` (3 rujukan, termasuk katalog opsi di `:58`), `SopMonitoringService` (2), `SopMonitoringAnalyticsService` (2)
- `SopPenjadwalanListService` (1), `SopPenjadwalanService` (1), `SopProgressReadService` (7)
- `SopAdminPenjadwalanController:587` — join kategori dibuang

Catatan: gerbang penugasan "publik hanya bila sudah dibuka" hidup di
`SopMonitoringPeopleService::terapkanSaringanPaket` dan dipakai 7 pemanggil.
Jangan ikut tersenggol saat membuang cabang kategori dari service yang sama.

Frontend:
- `Governance/Admin/SopMonitoringKaryawan.vue` (23), `monitoring/TabPaket.vue` (19), `monitoring/TabKaryawan.vue` (7)
- `monitoring/ringkasan/ZonaTren.vue` (16), `ringkasan/panel/PanelHeatmap.vue` (13), `ringkasan/ZonaTindakan.vue` (8), `ringkasan/panel/ModalExportRingkasan.vue` (8)
- `penjadwalan/WizardPenjadwalan.vue` (11)
- `Governance/Admin/SopBuilder.vue` (49) — pemilih kategori dan kolom daftar paket
- `Shared/components/ui/SopPaketCard.vue`, `SopTugasCard.vue`, `SopLivePreview.vue` — lencana kategori

Sumbu SOP Saya:
- `Employee/Saya/sumbuTugas.js` — `AKSIS.kategori` dan `TANPA.kategori` dihapus; `SUMBU` turun dari 4 jadi 3 (`departemen`, `jenis`, `prioritas`)
- `:159` — cabang `id_kategori: key === 'kategori' ? ...` dan tautan balik ke Perpustakaan dihapus
- `Employee/Saya/RailSumbu.vue`, `SopSaya.vue`, `BarisTugasSaya.vue` menyesuaikan; sumbu bawaan dipindah ke `departemen`

### Lapis 4 — Perpustakaan jadi daftar paket datar

`SopRepositoryReadService` (12 rujukan):
- `folderKategori()` (`:151-225`) dihapus; `apiBeranda` mengembalikan daftar paket, bukan daftar folder
- `kategoriDetail()` (`:286-340`) dihapus
- kolom `Kategori` / `Id_Kategori` pada bentuk baris (`:584-585`, `:800-801`) dan pencarian lintas kolom (`:364`) dibuang

`SopRepositoryController`:
- `apiKategori()` (`:184-243`) dihapus
- `:98` join kategori dibuang

Route: `routes/LMS/SOP/Employee/SopEmployee.php:47` (`/repository/kategori/{id}`) dihapus.

Vue:
- `Employee/Repository/FolderKategori.vue` dan `HalamanKategori.vue` dihapus
- `SopRepository.vue` — lensa "Paket" jadi daftar kartu paket dengan pencarian dan
  saringan prioritas; parameter URL `?kat=` dan cabang `:564-570` dihapus. Lensa
  "Dokumen" (`mode === 'dokumen'`) **tidak disentuh**
- `KartuSop.vue`, `LensaDokumen.vue`, `IntipDrawer.vue` — lencana kategori dibuang

### Lapis 5 — Laporan dan Export Studio

`Export/SopDatasetKepatuhan.php` (5 rujukan):
- `:78` — `new Dimensi('kategori', 'Kategori', 'kt.Nama_Kategori', 'teks', 'paket')` dihapus
- `:252` — `new Saringan('kategori', 'Kategori Dokumen', 'mp.Id_Kategori_SOP', ...)` dihapus
- `:324` — join dihapus
- `:454-461` — katalog opsi saringan `kategori` dihapus
- **Kunci cache opsi saringan wajib dinaikkan**, kalau tidak klien masih menerima daftar kategori dari cache lama

`Export/SopTemplateSistem.php`:
- `:32` — `'kategori'` dibuang dari daftar kolom yang dikenal
- `:231-233` — template sistem `"Kepatuhan per Prioritas & Kategori"` diganti nama
  jadi `"Kepatuhan per Prioritas"`, dimensinya jadi `['prioritas', 'distribusi']`

Buang kolom `Nama_Kategori` dari:
- `SopComplianceExportService:130`
- `SopExportRinciService:181`
- `SopDokumenKesehatanService:812`
- `SopDocumentControlService:96, :283`

### Lapis 6 — Data dan izin (DML, tanpa DDL)

Satu berkas `database/dml/<change-id>/data-change.sql`, tiap tahap berdiri sendiri:

1. Angkat `plans/sop/sql/37_menu_prioritas_sop.sql` menjadi tahap DML resmi, dengan
   tiga koreksi: `Sort_Menu` disesuaikan (slot 2 kini terpakai Kategori), komentar
   grup fitur diperbaiki dari grup 3 → grup 7 (DMS), dan catatan bahwa sumber
   salinannya adalah baris Kategori yang akan dimatikan di tahap 4–5.
   Skrip ini menanam ketiganya sekaligus: `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`
   (grup fitur disalin dari baris Kategori → otomatis 7), dan grant
   `HRIS_Page_Access` bagi setiap pemegang `menuSopKategoriPage` yang aktif (user 4
   dan 622)
2. `flushMenuCache`, lalu verifikasi halaman Prioritas terbuka bagi user 4 dan 622
   **dengan kunci barunya** — bukan lewat kunci Kategori yang masih hidup
3. Bersihkan kunci `kategori` dari `N_HRIS_Report_Template.Definisi_Json` pada 9 baris yang menyebutnya (3 di antaranya masih aktif: Id 95, 99, 100) — dimensi maupun saringan
4. Matikan `N_HRIS_Grup_Fitur_Map` Id 133 (`Flag_Aktif='T'`)
5. Matikan grant `HRIS_Page_Access` Id 1162 dan 1199 (`Flag_Aktif='T'`)

Setelah DML dijalankan, panggil `flushMenuCache`.

`N_SOP_Kategori` dan `N_SOP_Master_Paket.Id_Kategori_SOP` **tidak disentuh sama
sekali** — bukan dihapus, bukan dinonaktifkan, bukan dikosongkan.

### Lapis 7 — Uji

Dihapus:
- `tests/Feature/LMS/SOP/Uat/Kontrol004KategoriTerpakaiTest.php` (30 rujukan) beserta skenario UAT-nya di `UatSkenario`
- `resources/js/Pages/LMS/SOP/Governance/SopCategory/*.test.js`

Fikstur berhenti menyemai kategori:
- `tests/Feature/LMS/SOP/Uat/Support/UatDunia.php` (12)
- `app/Services/LMS/SOP/Uat/UatPembangunDunia.php` (4)
- `app/Services/LMS/SOP/Uat/UatTabelIzin.php` — baris izin `N_SOP_Kategori` dicabut
- `app/Console/Commands/LMS/SOP/Operations/SopSeedDemo.php:824-825`

Menyesuaikan (terbesar dulu):
- `SopBuilderSaringanPaketTest` (28), `Alur024KebijakanOtomasiTest` (18),
  `SopRingkasanSaringanTest` (16), `SopMasterPrioritasTest` (9),
  `SopHeatmapSumbuTest` (9), `SopBuilderSaringanFiksturTest` (7),
  `SopAwarenessUpdateTest` (7), `SopKontenPickerTanpaBunnyTest` (6),
  `SopJangkauanPublikTest` (6), `SopTipeItemSakelarTest` (5),
  `SopDatasetKepatuhanTest` (4), `SopSayaIdentitasDokumenTest` (4),
  `SopPenjadwalanServiceTest` (4), `SopDokumenKesehatanTest` (4),
  `SopDokumenKelompokTest` (4), `SopRepositoryDokumenTest` (3), `SopApqcTest` (3)
- `sumbuTugas.test.js`, `RailSumbu.test.js`, `BarisTugasSaya.test.js`,
  `SopPenjadwalan.test.js`, `WizardPenjadwalan.test.js`,
  `SopMonitoringKaryawan.test.js`, `SopExportStudio.test.js`

Uji baru yang wajib ada:
- Halaman Prioritas tetap terbuka bagi pemegang `menuSopPrioritasPage` dan tertutup
  bagi yang tidak — dijalankan setelah kunci Kategori dicabut
- Route `sop/admin/kategori` dan `/api/v1/sop/admin/kategori/*` mengembalikan 404
- Builder menerima simpan paket **tanpa** `Id_Kategori_SOP` (dulu ditolak) — di
  kedua pintu, Master dan Builder
- Perpustakaan lensa "Paket" mengembalikan daftar paket, dan `/repository/kategori/{id}` 404
- Dataset kepatuhan tidak lagi menawarkan dimensi maupun saringan `kategori`, dan
  opsi saringan yang dikembalikan bukan dari cache lama

Setiap uji regresi baru harus pernah dilihat **MERAH** sebelum kodenya diubah.
Karena perubahan ini menyentuh service bersama, jalankan **suite penuh**, bukan
`--filter` — ada baseline sekitar 19 kegagalan non-SOP yang memang sudah ada
sebelumnya dan bukan akibat pekerjaan ini.

## Urutan pengerjaan

Urutannya mengikat, bukan saran:

1. **DML tahap 1–2** — segarkan lalu jalankan skrip 37, `flushMenuCache`, dan
   verifikasi halaman Prioritas terbuka lewat `menuSopPrioritasPage` sendiri.
   Tak bisa dibalik: skrip 37 menyalin dari baris Kategori yang akan dimatikan.
2. **Lapis 1** — gerbang Prioritas dipindah ke kunci barunya, halaman master dibongkar.
3. **Lapis 2** — kewajiban Builder dicabut.
4. **Lapis 3 dan 4** — saringan, sumbu, Perpustakaan.
5. **Lapis 5** — laporan, plus naikkan kunci cache opsi saringan.
6. **DML tahap 3** — bersihkan template tersimpan.
7. **DML tahap 4–5** — matikan baris menu dan grant Kategori, lalu `flushMenuCache`.
8. **Lapis 7** — suite penuh.

## Ukuran

| | Jumlah |
|---|---|
| Berkas PHP diubah | ~21 |
| Berkas PHP dihapus | 2 |
| Berkas Vue diubah | ~13 |
| Berkas Vue/JS dihapus | 12 (10 di folder `SopCategory/` — 6 `.vue`, 3 `.test.js`, 1 `api/sopCategoryApi.js` — plus `FolderKategori.vue` dan `HalamanKategori.vue`) |
| Berkas uji terdampak | ~20 diubah, 2 dihapus |
| Route dihapus | 7 |
| DDL | **1** (DDL-2026-059, koreksi — lihat catatan di Ringkasan) |
| DML | 2 berkas (DML-2026-057 dan DML-2026-058) |

Perkiraan 3–4 hari kerja.

## Yang sengaja tidak dikerjakan

- Halaman Prioritas tidak dibangun ulang — sudah berdiri sendiri di kode sejak
  awal. Yang dikerjakan hanya menyelesaikan pendaftarannya di DB.
- Kolom `Id_Kategori_SOP` dan tabel `N_SOP_Kategori` tidak dihapus dan tidak
  dinonaktifkan. Kolomnya dilonggarkan jadi boleh kosong (DDL-2026-059) karena
  tanpa itu paket baru tak bisa lahir sama sekali, tapi ia tetap ada. Ini bukan kelupaan — DROP dilarang aturan proyek, dan
  membiarkannya berdiri adalah satu-satunya jalan pulang kalau kategori ternyata
  dibutuhkan lagi.
- `Id_Kategori` di modul VISITOR dan Training memakai nama kolom yang sama tapi
  tabel yang sama sekali lain. Di luar lingkup.
- Lensa "Dokumen" di Perpustakaan tidak disentuh.
- Sumbu APQC dan Jenis Dokumen tidak diusulkan sebagai pengganti folder, karena
  keduanya melekat di dokumen/item sehingga satu paket akan muncul di beberapa
  folder sekaligus. Kalau folder topikal ternyata dibutuhkan lagi, itu pekerjaan
  tersendiri dengan keputusannya sendiri.
