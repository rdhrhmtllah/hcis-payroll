# Structure Alignment Final Status

Branch: `refactor/file-structure-alignment`
Pembaruan terakhir: 2026-08-18
Status: `ALIGNED_WITH_DESIGN_SYSTEM_PENDING_BEHAVIOR_REGRESSION`

## Ownership terverifikasi dari database

Dibaca read-only dari `N_HRIS_Master_Grup_Fitur` dan `N_HRIS_Grup_Fitur_Map`
pada `Web_HRIS` (host development team311) tanggal 2026-08-18.

```text
ESS      Employee Self Service        30 page
KPI      Key Performance Indicator    20 page
LMS      Learning Management System   16 page
VISITOR  Visitor Management System     6 page
SYSTEM   System                        1 page
```

Tidak ada grup `HRIS` dan tidak ada grup `SOP`. Modul kepegawaian terdaftar
sebagai `ESS`; kesembilan page SOP ter-resolve ke grup `LMS`. Dokumentasi yang
sebelumnya menyebut `HRIS` dan `SOP` sebagai modul telah dikoreksi.

## Struktur frontend saat ini

```text
resources/js/Pages        ESS KPI LMS SYSTEM VISITOR   (+ Error.vue framework)
resources/js/components   ui/ form/ Dashboard/ Shell/   (lintas module)
resources/js/composables  utils  Layouts                (lapisan bersama)
```

Design system bersama tidak lagi tinggal di dalam namespace halaman Inertia.
Rujukan lintas module memakai alias `@` yang menunjuk `resources/js`.

## Struktur backend saat ini

```text
app/Http/Controllers   ESS KPI LMS SYSTEM VISITOR   (+ Controller.php base)
app/Http/Requests      ESS SYSTEM
app/Models             ESS KPI SYSTEM
app/Services           ESS KPI LMS SYSTEM VISITOR
app/Jobs               ESS KPI LMS SYSTEM
app/Console/Commands   ESS KPI LMS SYSTEM VISITOR
routes                 ESS KPI LMS SYSTEM VISITOR   (+ loader Laravel)
```

Tidak ada lagi folder bernama developer, folder bernama controller, folder
domain-teknis lepas, maupun direktori kosong sisa pemindahan.

Seluruh pemuatan route modul kini hanya terjadi di `routes/web.php` dan
`routes/api.php`. Sebelumnya routing seluruh aplikasi dimuat berantai lewat
`routes/KPI/fransDevEvo.php` dan `routes/KPI/ridhoDevEvo.php` — dua file
bernama orang di dalam folder modul KPI — sehingga folder `routes/` tampak
modular padahal tulang punggungnya tidak. Kedua file itu dipecah menjadi 32
file modul dan dihapus.

## Cacat yang diperbaiki

| # | Cacat | Asal |
|---|---|---|
| 1 | Seluruh endpoint SOP HTTP 500; 18 referensi controller di 4 file route dan 9 di 8 file test menunjuk namespace yang sudah tidak ada | pass grouping sebelumnya |
| 2 | `app/Services/LMS/Stream/VideoStream.php` mendeklarasikan salinan basi `BunnyStreamService`, dilewati autoloader | pass grouping sebelumnya |
| 3 | 23 route kehilangan middleware `auth` akibat `require` ganda `fransDevEvo.php` | sudah ada di `master` |
| 4 | 11 referensi tak-terkualifikasi ke bekas tetangga sesama namespace putus saat namespace dipecah | pemindahan service pada pass ini |
| 5 | 23 `require` duplikat tersalin ke file hasil pemecahan route | pemecahan route pada pass ini |
| 6 | `app/Helpers/hitungCuti.php` melanggar PSR-4 | pre-existing |

Cacat 3 layak dicatat khusus. Registrasi kedua di luar grup `auth` menimpa yang
pertama, sehingga `api/v1/lms/penjadwalan/*` (15 route, termasuk `POST store`,
`update`, `toggle`, `bulk-toggle`, `reassign-third-party`) dan
`api/v1/training/kategori/*` (3 route) berjalan tanpa autentikasi. Sebagian
endpoint GET di dalamnya tidak memanggil `Auth::` sama sekali dan membaca tabel
`Karyawan`, `HRIS_Level`, serta `N_LMS_Penjadwalan`.

## Kenapa cacat 1 lolos dari verifikasi sebelumnya

`Foo::class` di PHP dievaluasi menjadi string pada waktu compile dan tidak
memicu autoloader. Route tetap terdaftar tanpa error; kegagalan baru muncul
saat dispatch. Pemeriksaan syntax, `git diff --check`, dan pemuatan kelas
per-file tidak dapat menangkapnya.

Tiga alat verifikasi permanen ditambahkan untuk menutup celah ini:

| Alat | Menangkap |
|---|---|
| `scripts/verify-references.php` | Simbol `App\` dari `use` dan `::class` yang tidak resolve |
| `scripts/route-snapshot.php` | Perubahan URI, nama, middleware, atau action pada 1163 route |
| `scripts/console-snapshot.php` | Dependency command yang gagal resolve dari container |

`scripts/route-snapshot.php` menggantikan `php artisan route:list`, yang tidak
dapat dijalankan di environment ini karena `RouteListCommand` me-resolve
controller dan memicu kegagalan Google ADC lewat `CloudTaskService`. Snapshot
sengaja memakai `Route::middleware()` alih-alih `gatherMiddleware()` karena
method kedua me-resolve controller dan memicu kegagalan yang sama.

`scripts/console-snapshot.php` ditambahkan setelah cacat 4 muncul: kelas
kesalahan itu tidak terlihat oleh pemindai referensi karena tidak ada satu pun
`use` atau `::class` yang berubah — yang putus adalah acuan polos yang dulu
resolve lewat namespace yang sama.

## Hasil verifikasi

```text
route terdaftar            : 1163 -> 1163, URI/nama/middleware dipertahankan
perintah artisan           : 170, Console Kernel boot bersih
referensi tidak resolve    : 23 -> 5
pelanggaran PSR-4          : 2 -> 0
phpunit --testsuite Unit   : 77/77
```

Satu-satunya perubahan middleware yang disengaja adalah 23 route yang
mendapatkan kembali `auth`. Tidak ada route yang kehilangan middleware.

## Cacat frontend yang diperbaiki

| # | Cacat | Asal |
|---|---|---|
| 7 | 16 halaman ter-resolve ke berkas yang tidak ada; gejalanya layar putih tanpa error di sisi server | pass grouping frontend sebelumnya |
| 8 | Design system bersama tinggal di `Pages/KPI/components/ui`, salah pemilik sekaligus salah lapisan | pre-existing |
| 9 | Alias `@` menunjuk `./src` yang tidak pernah ada di project ini | pre-existing |
| 10 | 95 berkas frontend tidak terjangkau dari akar mana pun | pre-existing |

Cacat 7 sejenis dengan cacat 1 tetapi lebih sunyi. Nama halaman Inertia hanyalah
string; tidak ada autoloader, linter, maupun compiler yang memeriksanya.

Cacat 8 diukur, bukan diperkirakan: `modalV2-noir.vue` dipakai 70 berkas dari 10
module, `header.vue` 52 berkas dari 7 module, `toggleSectionV2.vue` 44 berkas
dari 7 module. Halaman ESS mengimpornya lewat
`../../../../../KPI/components/ui/...`. Ini justru menyalahi
`frontend-architecture.md` yang sejak awal menetapkan component lintas module
berada di `resources/js/components/`.

Cacat 10 memerlukan penelusuran transitif. Pemeriksaan non-transitif menyatakan
`CardSkeleton.vue` dan `CardState.vue` hidup karena diimpor lima panel
LeaderDashboard, padahal kelima panel itu sendiri tidak terjangkau:
`LeaderDashboard.vue` mengimplementasikan panelnya inline dan tidak pernah
mengimpor berkas panel tersebut.

Dua alat verifikasi frontend ditambahkan:

| Alat | Menangkap |
|---|---|
| `scripts/verify-inertia-pages.php` | Nama halaman yang tidak punya berkas komponen |
| `scripts/verify-frontend-imports.php` | Jalur impor relatif dan beralias yang tidak menunjuk ke mana-mana |

## Catatan build

`npm run build` gagal dengan `JavaScript heap out of memory` pada heap Node
bawaan (2144 MB), baik sebelum maupun sesudah pekerjaan ini. Build berhasil
dengan `NODE_OPTIONS=--max-old-space-size=6144` dalam sekitar 2 menit 43 detik.
Selama heap bawaan belum dinaikkan di `package.json`, build bukan gerbang yang
dapat dijalankan begitu saja; `verify-frontend-imports.php` menutup kelas
kesalahan yang paling relevan untuk pemindahan berkas dengan biaya jauh lebih
kecil.

## Penggabungan design system

Branch `feat/system-design` digabungkan setelah alignment struktur masuk master.
Keduanya bercabang dari titik master yang sama.

Permukaan konfliknya kecil: dari 28 berkas beririsan, 23 adalah salinan usang
yang dihapus di satu sisi dan diarsipkan di sisi lain. Lima sisanya diubah di
satu sisi dan dipindah di sisi lain; git me-resolve rename+modify dengan benar
sehingga isi design system mendarat di path modul yang baru.

Dua branch memperbaiki alias `@` secara terpisah dengan nilai yang sama.

Penyesuaian struktur yang dilakukan pada design system:

| Sebelum | Sesudah | Alasan |
|---|---|---|
| `Pages/DesignSystem/` | `Pages/SYSTEM/DesignSystem/` | Master grup fitur hanya mengenal ESS, KPI, LMS, VISITOR, SYSTEM |
| closure inline di `routes/web.php` | `routes/SYSTEM/DesignSystem.php` | Pola file route per modul |
| 70 impor relatif di katalog | `@/components/ui` | Bentuk yang sudah diiklankan tombol "Salin Import" |

`components/ui` kini milik design system sepenuhnya; 11 komponen lintas modul
pra-design-system dipindahkan ke `components/legacy` dan dibekukan, dengan 249
penyentuhan berkas untuk mengarahkan impornya. Aturannya di
`docs/design-system.md`.

Dua cacat yang ditemukan gerbang saat penggabungan:

- `vitest.config.js` tidak mendefinisikan alias `@`, sehingga setiap impor
  beralias gagal saat test. Ini cacat yang diperkenalkan pass alignment
  frontend, yang memverifikasi lewat build Vite tetapi tidak pernah menjalankan
  vitest. 11 berkas test gagal collect sebelum diperbaiki.
- `verify-frontend-imports.php` melewatkan impor `.css` dan bentuk
  `import '...'` tanpa `from`; satu impor CSS yang putus lolos ke build.
  Skripnya diperluas.

## Hasil verifikasi setelah penggabungan

```text
jalur impor putus     : 0 dari 1076
halaman tidak tertaut : 0 dari 116 yang dirender
vitest                : 96 berkas, 688 tes lolos
phpunit Unit          : 77/77
referensi PHP         : 5 tidak resolve (pre-existing)
npm run build         : built in 2m 31s, exit 0
```

## Generasi lama fitur cuti dibuang

Lima kelas model yang dirujuk tetapi tidak pernah ada di riwayat git —
`App\Models\Cuti`, `PengajuanCuti`, `SaldoCuti`, `HCIS\CUTI\CutiBersama`,
`KpiUser` — semula dicatat sebagai fitur rusak yang perlu model baru. Penelusuran
lanjutan membuktikan sebaliknya: itu **generasi lama fitur cuti yang sudah
digantikan**, masih ter-route tetapi tidak pernah dipanggil.

Bukti sebelum dibuang, untuk ketujuh route yang terdampak
(`api/v1/cuti/leave-balance`, `leave-balance-detail`, `calendar-events`,
`admin/kuota`, `admin/kuota/adjust`, `admin/kuota/search`,
`admin/laporan/cuti/csv`, `admin/laporan/cuti/pdf`):

- nol pemanggilan dari `resources/js` maupun `resources/views`
- tidak satu pun test menyentuhnya
- penerusnya hidup dan lebih lengkap: `admin/kuota-management`
  (`KuotaManagementController` beserta widget dashboard), dan
  `CutiController@getSaldoCuti` di `/check-saldo` berdampingan dengan
  `SaldoCutiController@getSaldoCuti` yang mati

Yang dibuang: tiga controller yang seluruh method-nya hanya melayani route mati
(`SaldoCutiController`, `KuotaCutiController`, `LaporanCutiController`), method
`CutiController::getCalendarEvents`, lima method CRUD mati di
`cutiBersamaService` (`getDataCutiByKode_Karyawan` yang dipanggil tiga
controller hidup tidak memakai model itu sama sekali), `HitungCutiHelper` yang
tidak dirujuk siapa pun, serta dua seeder yang tidak terdaftar di
`DatabaseSeeder`. Semuanya dipindahkan ke `legacy/archive/`, bukan dihapus.

Konsekuensi terpenting: **`scripts/verify-references.php` kini berjalan pada
`--max=0`.** Sebelumnya lima referensi putus itu ditoleransi, sehingga gerbang
tidak dapat membedakan kerusakan lama dari kerusakan baru. Sejak sekarang
setiap referensi putus yang muncul langsung menggagalkan gerbang.

Route: 1166 -> 1158, tepat delapan yang mati, nol yang lain terpengaruh.

## Triase berkas arsip

Ke-38 berkas arsip yang bernama normal ditriase satu per satu. Tiap nama dicari
sebagai string di seluruh sumber hidup, bukan hanya sebagai pernyataan `import`,
untuk menutup kemungkinan pemakaian lewat komponen dinamis.

Enam nama muncul di sumber hidup. Empat ternyata cocok kebetulan:
`newShift` cocok dengan properti `quickModalData.newShiftId`, `modal-target`
dengan kelas CSS `modal-target-content`, `PagesAccessSystem` dengan properti
`name:` pada `index.vue` yang hidup, dan `Pagination` dengan kata umum —
tidak satu pun berkas mengimpor `components/ui/Pagination.vue`; yang dipakai
`HcisPagination` dan `SimplePagination`.

Dua sisanya bermakna:

- **`TabDokumen.vue` dikembalikan.** `SopMonitoring.vue` memuat komentar yang
  menyatakan berkas itu sengaja dibiarkan utuh supaya tab-nya gampang dipasang
  lagi. Mengarsipkannya bertentangan dengan maksud yang tertulis di kode.
  Memulihkannya menuntut `SopDokumenDetailDrawer.vue` ikut dipulihkan, sebab
  berkas itu mati hanya karena TabDokumen mati — gerbang impor yang
  menemukannya.
- **`TrainingDetailCheckIn.vue` tetap diarsipkan.** Docblock
  `TrainingDetailSectionRenderer` memetakan `ABS → TrainingDetailCheckIn`,
  tetapi `componentName` tidak punya entri `ABS` dan komponennya tidak
  terdaftar. Penelusuran menunjukkan `trainingDetail.vue` justru melewati item
  `ABS` sebelum menyusun daftar section, sehingga renderer tidak pernah
  menerimanya. Docblock itu dikoreksi agar tidak menyesatkan pembaca
  berikutnya.

Tiga puluh enam berkas lainnya tetap diarsipkan. Yang paling meyakinkan adalah
lima panel LeaderDashboard (halaman induknya mengimplementasikan panel secara
inline), lima komponen `Filter/child` (`modernFilter.vue` tidak mengimpornya),
dan empat komponen TrainingDetail yang digantikan trio
`TrainingDetailTest`/`Materials`/`Evaluation`.

## Sisa scaffolding make:fullcontroller dibuang

Lima berkas controller dan empat berkas route dibuang ke `legacy/archive/`.

Empat controller adalah scaffolding murni: 49–65 baris, **nol baris isi**,
seluruh method hanya berisi `//`. `LmsMasterRumusApi` di antaranya bahkan
terdaftar sebagai `apiResource` dengan lima route yang mengembalikan badan
kosong. Dua berkas route lainnya, `LmsPenjadwalanTrainingApi.php` dan
`LmsTrainingKategoriApi.php`, tidak pernah di-`require` dari mana pun.

`TrainingCatalogApi` diperlakukan berbeda karena berisi kode sungguhan (191
baris). Ia dibuang setelah dipastikan tergantikan, bukan karena tampak tidak
terpakai:

- Ia bukan duplikat `TrainingCatalogController@show`. Yang itu merender halaman
  Inertia (17 baris); yang ini mengembalikan JSON (105 baris).
- Halaman yang hidup, `trainingDetail.vue`, mengambil datanya dari
  `/api/v1/lms/materi/{id}` yang dilayani `LmsMateriController` — bukan dari
  endpoint ini.
- URL-nya `api/api/v1/lms/training/{id}`, berprefix ganda karena
  `routes/api.php` sudah menambahkan prefix `api`. Bentuk itu tidak muncul di
  satu pun berkas sumber.

Route: 1158 -> 1152. Enam yang hilang tepat yang dimaksud; nol yang lain
terpengaruh.

## Struktur tests/

Lapisan terakhir yang belum disejajarkan. 124 berkas dipindahkan ke
`tests/<suite>/<MODUL>/<Submodul>/`, mencerminkan struktur sumber.

```text
tests/Feature       ESS KPI LMS SYSTEM
tests/Integration   SYSTEM
tests/Unit          ESS LMS SYSTEM VISITOR   (+ ExampleTest bawaan framework)
tests/e2e           Playwright, di luar PHPUnit
tests/              TestCase.php, CreatesApplication.php
```

Kedalaman submodul dipilih karena LMS/SOP sendirian berisi 78 berkas; berhenti
di level modul akan menghasilkan satu folder datar berisi 80 berkas — persis
masalah yang dihindari di tempat lain.

Kepemilikan diturunkan dari kelas `App\` yang diuji tiap berkas, bukan dari nama
berkasnya. Metode itu punya satu kelemahan yang terbukti: ia memilih modul yang
paling banyak diimpor, bukan pemilik domainnya. Akibatnya empat test cuti dan
lembur sempat masuk SYSTEM karena banyak memakai layanan outbox dan transaksi.
Pemeriksaan silang terhadap nama berkas menemukan sembilan penempatan yang
bertentangan; tujuh dikoreksi, dua dipertahankan karena kelas yang diuji memang
tinggal di modul itu — `LmsReassignThirdPartyHandler` ada di
`SYSTEM/TaskProcessing`, dan `HomepageLmsService` ada di `ESS/Dashboard/Homepage`.

`tests/Feature/Uat/Support/UatAkun.php` dan `UatDunia.php` ikut pindah beserta
21 berkas yang mengimpornya. `TestCase.php` dan `CreatesApplication.php` tetap
di akar karena dipakai 111 berkas dari semua modul.

`phpunit.xml` tidak berubah: testsuite-nya berbasis direktori `tests/Unit`,
`tests/Feature`, `tests/Integration`, sehingga penataan subfolder tidak
memengaruhi keanggotaan suite.

Verifikasi inventaris test sebelum dan sesudah:

```text
Unit          77  -> 77    nama method berbeda: 0
Feature      601  -> 601   nama method berbeda: 0
Integration   25  -> 25    nama method berbeda: 0
pelanggaran PSR-4          : 0
```

## Temuan terbuka

- ~~Empat controller scaffolding tanpa isi~~ — sudah diarsipkan, lihat
  bagian "Sisa scaffolding make:fullcontroller dibuang".
- **Grup `api/v1` pada `TrainingScheduling.php` dan `TrainingCategory.php`
  masih tidak mendeklarasikan middleware sendiri** dan bergantung pada
  pewarisan dari lokasi `require`. Perbaikan cacat 3 menutup gejalanya, bukan
  kerapuhannya. Menambahkan `auth` eksplisit direkomendasikan.
- **`TabDokumen.vue` dan `SopDokumenDetailDrawer.vue` sengaja tidak diimpor.**
  Keduanya dikembalikan dari arsip setelah triase; analisis keterjangkauan
  otomatis akan menandainya mati lagi. Catatan maksud ada di kepala
  `TabDokumen.vue`. Periksa dulu sebelum mengarsipkannya kembali.
- **Grup route lain masih mewarisi proteksi dari posisi `require`.**
  `TrainingScheduling.php` dan `TrainingCategory.php` sudah mendeklarasikan
  `auth` sendiri, tetapi pola yang sama masih ada di `LmsModulAbsensiWeb/Api`
  (19 route), `PergantianHari`, dan `LmsMasterRumusApi` (5 route).
  `SYSTEM/TaskProcessing.php` tidak termasuk — ia memakai middleware khusus
  sendiri.
- **Endpoint `api/v1` LMS Training hanya menuntut `auth`, bukan
  `check.jabatan`**, sedangkan halaman web yang dilayaninya menuntut
  `LMSPenjadwalanPage` / `LMSTrainingKategoriPage`. Mengetatkannya mengubah
  perilaku dan perlu keputusan berdasarkan siapa pemanggilnya saat ini.
- **`components/ui/Pagination.vue` diarsipkan** meski branch design system
  memigrasikannya ke token. Berkas itu tidak terjangkau dari akar mana pun dan
  sudah digantikan `HcisPagination`; editan tokennya tetap utuh di
  `legacy/archive/`. Perlu keputusan apakah dipulihkan atau ditinggalkan.
- **59 dari 61 komponen `Hcis*` belum dipakai di luar katalog design system.**
  Wajar untuk pustaka yang baru lahir; hanya `HcisSwitch` dan `HcisDialog` yang
  sudah menembus halaman aplikasi. Konsekuensinya, verifikasi keterjangkauan
  tidak boleh dijalankan atas komponen design system tanpa memperlakukan
  `registry.js` dan katalog sebagai akar — kalau tidak, hampir seluruh pustaka
  akan salah dinyatakan mati.
- **Struktur `tests/` belum disejajarkan**: `tests/Feature/{HCAbsensi,Ops,Transaksi}`
  masih memakai penamaan lama.

## Batas klaim

Verifikasi di atas membuktikan setiap kelas yang dirujuk dapat dimuat, setiap
route terdaftar dengan kontrak yang sama, dan setiap command dapat di-resolve.
Verifikasi ini **tidak** membuktikan setiap endpoint mengembalikan hasil yang
benar. Suite Feature memerlukan database hidup, berjalan lebih dari sembilan
menit, dan sudah gagal sebagian sebelum pekerjaan ini dimulai; suite itu
dipakai sebagai pembanding baseline, bukan gerbang lulus/gagal.

Tidak ada perubahan database, DDL, DML, migrasi, push, PR, merge, atau
deployment yang dilakukan.
