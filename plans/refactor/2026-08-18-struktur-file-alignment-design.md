# Desain: Penyelesaian Alignment Struktur File

Tanggal: 2026-08-18
Branch: `refactor/file-structure-alignment`
Status: `EXECUTED` — lihat `docs/architecture/structure-alignment-final-status.md` untuk hasil dan temuan terbuka

## 1. Tujuan

Menyelesaikan alignment struktur file yang sudah dimulai, dengan dua sasaran yang setara:

1. Memperbaiki regresi runtime yang ditinggalkan oleh pass grouping sebelumnya.
2. Menyelesaikan pengelompokan backend ke bentuk `Modul/Submodul/Fitur/Layer` sehingga tidak ada lagi folder sisa, folder bernama controller, atau folder domain-teknis lama di root layer.

Desain ini tidak mengubah URL, nama route, middleware, payload API, kontrak database, atau perilaku bisnis, kecuali pada perbaikan yang disebut eksplisit di Batch 0.

## 2. Keputusan yang sudah disepakati

| Pertanyaan | Keputusan |
|---|---|
| Modul domain kepegawaian | `ESS` tetap top-level. Registry dan `CLAUDE.md` yang diperbarui, bukan kodenya. |
| Posisi SOP | Di bawah `LMS`. `app/Services/SOP` dan `app/Jobs/Sop` pindah ke `LMS/SOP`. |
| Kedalaman scope | Backend penuh. `resources/js/Pages` tidak disentuh. |
| Pemilik `Activity` (pelacakan aktivitas) | `SYSTEM/Audit/Activity` |
| Pemilik `Homepage` | `ESS/Dashboard/Homepage` |
| Loader route (`fransDevEvo`/`ridhoDevEvo`) | Dibenahi, sebagai batch tersendiri setelah Batch 0. |
| 5 model yang hilang | Dilaporkan saja, tidak dibuat. |

### Catatan verifikasi database

> **Diperbarui 2026-08-18 (setelah Batch 6).** Verifikasi database sudah
> dijalankan dan **membenarkan seluruh keputusan penempatan di dokumen ini**:
> master grup fitur berisi tepat `ESS`, `KPI`, `LMS`, `VISITOR`, `SYSTEM`;
> tidak ada `HRIS`, tidak ada `SOP`; sembilan page SOP ter-resolve ke `LMS`.
> Paragraf di bawah adalah kondisi pada saat desain disusun.

`docs/architecture/module-registry.md` mewajibkan ownership diverifikasi dari
`N_HRIS_Master_Grup_Fitur` dan `N_HRIS_Grup_Fitur_Map`. Pada saat desain ini
disusun verifikasi itu **belum dilakukan**. Penempatan di dokumen ini diturunkan dari bukti di
dalam repository — file `routes/` yang sudah selaras, controller sibling yang
sudah dikelompokkan, dan string `Inertia::render` — bukan dari database.

Konsekuensinya: dokumen ini adalah keputusan struktural repository, bukan klaim
bahwa daftar modul/fitur ini resmi dan terbaru. Sebelum registry diperbarui
untuk mencatat `ESS` sebagai modul resmi (Batch 6), pembacaan read-only ke
master grup fitur development harus dijalankan lebih dulu. Bila hasilnya
berbeda dari asumsi di sini, Batch 6 berhenti dan keputusan dikembalikan ke user.

## 3. Baseline terukur sebelum perubahan

Diambil pada 2026-08-18 dari working tree bersih di commit `60d7820a`.

```text
Route terdaftar                       : 1163
Simbol App\ yang dirujuk              : 378
Referensi tidak resolve               : 23
Pelanggaran PSR-4 (--strict-psr)      : 2
phpunit --testsuite Unit              : 77/77 lolos (OK, 303 assertions)
phpunit --testsuite Feature           : gagal sebagian, butuh database hidup, >9 menit
php artisan route:list                : tidak dapat dijalankan (Google ADC)
```

Baseline `routes.txt` dan `refs.txt` disimpan di luar repository selama
pengerjaan dan dijadikan pembanding tiap batch.

## 4. Temuan yang mendasari rencana ini

### 4.1 Regresi yang disebabkan branch ini

**T1 — Seluruh endpoint SOP mengembalikan HTTP 500.**

Commit `519d6cb8` memindahkan 17 controller SOP ke
`App\Http\Controllers\LMS\SOP\{Employee,Governance,Integration}` tetapi tidak
memperbarui pernyataan `use` di file route yang memakainya. File route masih
menunjuk `App\Http\Controllers\LMS\SOP\SopXController` yang tidak pernah ada.

Bukti:

```text
$ ./vendor/bin/phpunit tests/Feature/LMS/SOP/SopBuilderRiwayatTest.php
BindingResolutionException: Target class
[App\Http\Controllers\LMS\SOP\SopAdminBuilderController] does not exist.
Tests: 5, Assertions: 3, Errors: 4.
```

Alasan kegagalan ini lolos dari verifikasi sebelumnya: `Foo::class` di PHP
dievaluasi menjadi *string* pada waktu compile dan tidak memicu autoloader.
Route tetap terdaftar tanpa error; kegagalan baru muncul saat dispatch.
Karena itu pemeriksaan berbasis syntax, `git diff --check`, dan pemuatan kelas
per-file tidak dapat menangkapnya. Gerbang verifikasi di bagian 7 dirancang
khusus untuk menutup celah ini.

File terdampak — 18 referensi ke 17 kelas berbeda
(`SopSecurePdfController` dirujuk dari dua file):

- `routes/LMS/SOP/Governance/SopGovernance.php` — 9 referensi
- `routes/LMS/SOP/Employee/SopEmployee.php` — 7 referensi
- `routes/LMS/SOP/Integration/SopThirdPartyApi.php` — 1 referensi
- `routes/api.php` — 1 referensi

**T2 — Kelas duplikat yang dilewati autoloader.**

`app/Services/LMS/Stream/VideoStream.php` mendeklarasikan
`App\Services\LMS\Bunny\BunnyStreamService`, nama kelas yang sama dengan
`app/Services/LMS/Bunny/BunnyStreamService.php` tetapi isinya versi lama.
Composer melewatinya dengan peringatan PSR-4. Kelas ini dipakai oleh
`LMS/Content/ContentManagementController` dan `SOP/SopKontenViewService`.

### 4.2 Kondisi yang sudah ada sebelum branch ini

Dicatat agar tidak salah dikaitkan dengan pekerjaan alignment.

**T3** — `app/Helpers/hitungCuti.php` mendeklarasikan `App\Helpers\HitungCutiHelper`.
Nama file tidak cocok dengan nama kelas sehingga dilewati classmap optimized.

**T4** — Lima kelas model dirujuk dari jalur kode yang hidup tetapi tidak pernah
ada di seluruh riwayat git:

| Kelas | Perujuk |
|---|---|
| `App\Models\Cuti` | `app/Helpers/hitungCuti.php` |
| `App\Models\PengajuanCuti` | `ESS/Attendance/Leave/Personal/CutiController.php`, `ESS/Attendance/Leave/Admin/LaporanCutiController.php` |
| `App\Models\SaldoCuti` | `ESS/Attendance/Leave/Personal/{CutiController,SaldoCutiController}.php`, `ESS/Attendance/Leave/Admin/KuotaCutiController.php` |
| `App\Models\HCIS\CUTI\CutiBersama` | `app/Services/HCIS/CUTI/cutiBersamaService.php` |
| `App\Models\KpiUser` | `database/seeders/{Users2,fiturHCIS}.php` |

Membuat model ini memerlukan verifikasi nama tabel dan kolom terhadap database
dan tunduk pada workflow DDL di `AGENTS.md`. Di luar scope desain ini.

**T5** — `routes/KPI/fransDevEvo.php` di-`require` dua kali dari `routes/web.php`:
baris 124 di dalam `Route::middleware(['auth'])->group(...)` dan baris 679 di
luar grup itu. Registrasi terakhir menang di `RouteCollection`, sehingga route
yang tidak mendeklarasikan `auth` sendiri kehilangan middleware tersebut.

**Dampak sebenarnya jauh lebih besar dari perkiraan awal pada dokumen ini.**
Penilaian pertama hanya memeriksa `api/lmsmasterrumus` dan menyimpulkan tidak
ada data yang terekspos karena controllernya stub kosong. Kesimpulan itu salah.
Snapshot route pada Batch 1a menunjukkan **23 route** kehilangan `auth`:

| Route | Jumlah | Sifat |
|---|---|---|
| `api/lmsmasterrumus{,/{id}}` | 5 | stub kosong, tidak ada data |
| `api/v1/lms/penjadwalan/*` | 15 | fungsional, termasuk `POST store`, `POST update`, `POST toggle`, `POST bulk-toggle`, `POST reassign-third-party` |
| `api/v1/training/kategori/*` | 3 | fungsional, termasuk `POST store`, `PUT update` |

Penyebabnya: `routes/LMS/Training/Scheduling/TrainingScheduling.php` dan
`routes/LMS/Training/MasterData/TrainingCategory/TrainingCategory.php`
mendeklarasikan `['auth', 'check.jabatan:...']` hanya pada grup halaman web,
sementara grup `Route::prefix('api/v1')` di bawahnya tidak mendeklarasikan
middleware apa pun dan sepenuhnya bergantung pada pewarisan dari lokasi
`require`. Registrasi kedua di luar grup `auth` menghapus pewarisan itu.

Beberapa endpoint GET tidak memanggil `Auth::` sama sekali dan mengembalikan
data langsung dari database — `getDataKaryawan`, `getLokasiKerja`,
`getMasterTraining`, `getDataMasterLevel` membaca tabel `Karyawan`,
`HRIS_Level`, dan `N_LMS_Penjadwalan`. Endpoint tersebut dapat dijangkau tanpa
login. Grup `web` tetap memasang CSRF, yang membatasi penyalahgunaan lintas
situs pada method tulis, tetapi tidak menghalangi pemanggilan langsung.

Kondisi ini **sudah ada di `master`**: `routes/LmsPenjadwalanTrainingWeb.php`
dan `routes/LmsTrainingKategoriWeb.php` di sana memiliki struktur grup yang
sama dan di-`require` dari `fransDevEvo.php` yang juga di-`require` dua kali.
Jadi ini bukan regresi branch ini, melainkan celah lama yang baru terlihat
karena snapshot route membandingkan middleware secara menyeluruh.

Batch 1a menutupnya dengan menghapus `require` ganda. Perlu dicatat bahwa
perbaikan itu bersifat struktural: grup `api/v1` pada kedua file route tetap
tidak mendeklarasikan middleware sendiri, sehingga masih rapuh terhadap
kesalahan penempatan `require` di masa depan. Menambahkan `auth` eksplisit pada
grup-grup tersebut adalah perbaikan lanjutan yang direkomendasikan, di luar
scope alignment struktur.

### 4.3 Masalah struktural pada loader route

Dari 33 file route modul, hanya 6 yang di-`require` dari `routes/web.php` atau
`routes/api.php`. Sisanya dimuat berantai melalui dua file bernama developer:

```text
routes/web.php
  └── routes/KPI/fransDevEvo.php
        └── LMS/Training/MasterData/TrainingCategory, LMS/Training/Scheduling,
            LMS/MasterData/Formula (Web + Api)
  └── routes/KPI/ridhoDevEvo.php
        └── LMS/Attendance, LMS/Training/Catalog, LMS/Content/Material,
            LMS/Training/Content, LMS/Training/Monitoring, LMS/Monitoring/Pdf,
            VISITOR/* (seluruhnya), Homepage/*, LMS/SOP/* (seluruhnya)
```

Folder `routes/` tampak modular, tetapi pemuatannya bergantung pada dua file
yang dinamai menurut orang dan diletakkan di dalam folder modul `KPI`.
`routes/LMS/MasterData/Formula/LmsMasterRumusWeb.php` berisi satu pernyataan
`use` dan nol route.

## 5. Struktur target

Konvensi mengikuti kode yang sudah selaras: `Modul/Submodul/Fitur/`, dengan
namespace PSR-4 mengikuti path secara persis. Modul aktif: `ESS`, `KPI`, `LMS`,
`VISITOR`, `SYSTEM`.

### 5.1 Controllers

| Sekarang | Target | Dasar |
|---|---|---|
| `Activity/{ActivityHandshake,AdminActivity,MyActivity}Controller` | `SYSTEM/Audit/Activity/` | keputusan user |
| `Homepage/{HomepageController,HomepageTourController,HomepageApi}` | `ESS/Dashboard/Homepage/` | keputusan user |
| `Konten/KontenVerifikasiWebhookController` | `LMS/Content/Verification/` | sibling `LMS/Content/ContentManagementController` |
| `LmsCloudTaskWebhookController/CloudTaskWebhookController` | `LMS/Integration/` | sibling `LMS/SOP/Integration/SopCloudTaskWebhookController` |
| `LmsMasterRumus/{LmsMasterRumusController,LmsMasterRumusApi}` | `LMS/MasterData/Formula/` | `routes/LMS/MasterData/Formula/` |
| `LmsMateri/LmsMateriController` | `LMS/Content/Material/` | `routes/LMS/Content/Material/` |
| `LmsModulAbsensi/{LmsModulAbsensiApi,LmsModulAbsensiController}` | `LMS/Attendance/` | `routes/LMS/Attendance/` |
| `LmsPdfMonitoring/LmsPdfMonitoringController` | `LMS/Monitoring/Pdf/` | `routes/LMS/Monitoring/Pdf/` |
| `LmsPenjadwalanTraining/LmsPenjadwalanTrainingApi` | `LMS/Training/Scheduling/` | `routes/LMS/Training/Scheduling/` |
| `LmsThirdParty/LmsThirdPartyCallbackController` | `LMS/Integration/` | `routes/LMS/Integration/` |
| `LmsTrainingKategori/LmsTrainingKategoriApi` | `LMS/Training/MasterData/Category/` | `routes/LMS/Training/MasterData/Category/` |
| `Ops/RuntimeRecoveryController` | `SYSTEM/Application/Maintenance/` | sibling command di `SYSTEM/Application/Maintenance` |
| `TransaksiPermit/TransaksiPermitApi` | `VISITOR/Permit/VehiclePermit/` | `routes/VISITOR/Permit/VehiclePermit/` |
| `TransaksiTamu/TransaksiTamuApi` | `VISITOR/Guest/Visit/` | `routes/VISITOR/Guest/Visit/` |

Folder kosong yang dihapus: `ApprovalVisitor`, `HCAbsensi` (beserta 3 subfolder
kosong di dalamnya), `LaporanVisitor`, `LmsMonitoring`, `Sop`,
`TrainingCatalog`, `VerifikatorVisitor` — 7 pohon, 9 direktori.
`Controller.php` tetap di root sebagai base class framework.

### 5.2 Services

| Sekarang | Target |
|---|---|
| `Absensi/IzinCancellationService` | `ESS/Attendance/Leave/Personal/` |
| `Activity/{ActivityCenterService,UploadTaskService}` | `ESS/Activity/Center/` |
| `ActivityTracker/*` (6) | `SYSTEM/Audit/Activity/Tracker/` |
| `Approval/KpiApprovalService` | `KPI/Approval/` |
| `Approval/*` sisanya (6) | `SYSTEM/Governance/Approval/Shared/` |
| `Cuti/Dashboard/CutiDashboardService` | `ESS/Attendance/Leave/Dashboard/` |
| `Dashboard/UDashReadModelService` | `ESS/Dashboard/Personal/` |
| `Export/{AbstractExportService,ExportAbortedException}` | `SYSTEM/Application/Exports/` |
| `GCP/CloudTaskService` | `SYSTEM/TaskProcessing/` |
| `HCIS/*` kecuali `CUTI/` (5) | `ESS/Attendance/Processing/` |
| `HCIS/CUTI/cutiBersamaService` | `ESS/Attendance/Leave/Admin/` |
| `Homepage/*` (8) | `ESS/Dashboard/Homepage/` |
| `InternalConfig/{AccessContext,FunctionalRole}` | `SYSTEM/Governance/Authorization/Shared/` |
| `Konten/{KontenPolicy,KontenVerifierService}` | `LMS/Content/Verification/` |
| `Lembur/LemburRequestService` | `ESS/Attendance/Overtime/Request/` |
| `Notification/CutiNotificationService` | `ESS/Attendance/Leave/Notification/` |
| `Notification/KpiNotificationService` | `KPI/Notification/` |
| `Notification/*` sisanya (5) | `SYSTEM/Notification/` |
| `Shell/InertiaShellService` | `SYSTEM/Application/Shell/` |
| `Transaksi/AllTransactionsReadService` | `SYSTEM/Application/Transactions/` |
| `Visitor/VisitorApprovalPolicyService` | `VISITOR/Workflow/Approval/` |
| `Visitor/VisitorEfektifService` | `VISITOR/Operations/Expiration/` |
| `Visitor/VisitorPermitQueryService` | `VISITOR/Permit/VehiclePermit/` |
| `SOP/*` (58, termasuk `Support/` dan `Uat/`) | `LMS/SOP/*` |

`app/Services/LMS/Stream/VideoStream.php` dihapus di Batch 0, bukan dipindah.

### 5.3 Jobs, Commands, Requests

| Sekarang | Target |
|---|---|
| `Jobs/Activity/*` (3) | `Jobs/SYSTEM/Audit/Activity/` |
| `Jobs/Sop/*` (4) | `Jobs/LMS/SOP/Automation/` |
| `Commands/Activity/*` (3) | `Commands/SYSTEM/Audit/Activity/` |
| `Commands/LMS/SOP/Operations/BackfillTrainingFinalScore` | `Commands/LMS/Training/Operations/` |
| `Commands/LMS/SOP/Operations/MigratePdfToImagesCommand` | `Commands/LMS/Training/Operations/` |
| `Commands/LMS/SOP/Operations/KontenVerifikasiUlang` | `Commands/LMS/Content/Verification/` |
| `Requests/Cuti/` | `Requests/ESS/Attendance/Leave/` |
| `Requests/System/` | `Requests/SYSTEM/` |

Tiga command terakhir saat ini berada di bawah `LMS/SOP` padahal cakupannya
Training dan Content, bukan SOP.

### 5.4 Routes

`routes/web.php` dan `routes/api.php` me-`require` file modul secara langsung.
`fransDevEvo.php` dan `ridhoDevEvo.php` dipecah menjadi file per fitur di bawah
`routes/KPI/` sesuai grup `check.jabatan` yang sudah ada, lalu dihapus.
`require` ganda pada baris 679 dihilangkan.
`routes/LMS/MasterData/Formula/LmsMasterRumusWeb.php` dihapus karena kosong.

Urutan `require` harus mempertahankan urutan registrasi saat ini, karena
`RouteCollection` menimpa entri dengan `method`+`uri` yang sama dan urutan
menentukan middleware mana yang menang. Snapshot route menjadi buktinya.

## 6. Rencana batch

Setiap batch adalah satu commit dan harus melewati seluruh gerbang di bagian 7
sebelum batch berikutnya dimulai.

**Batch 0 — Perbaikan regresi. Tidak ada file yang dipindah.**

- Perbaiki 18 referensi controller SOP (17 kelas) di 4 file route
- Hapus `app/Services/LMS/Stream/VideoStream.php`
- Rename `app/Helpers/hitungCuti.php` menjadi `HitungCutiHelper.php`
- Hapus 2 `use` mati `App\Jobs\LMS\SOP\Export\GenerateSopComplianceExport`
- Hapus `routes/LMS/MasterData/Formula/LmsMasterRumusWeb.php`

Target keluar: referensi tidak resolve turun dari 23 menjadi 5; pelanggaran
PSR-4 menjadi 0; test SOP yang tadinya error menjadi lolos atau gagal karena
sebab lain yang dilaporkan apa adanya.

**Batch 1 — Loader route.** Perubahan `routes/` saja. Snapshot route sebelum
dan sesudah harus identik kecuali hilangnya duplikasi `web,auth,auth` dan
perbaikan middleware pada `api/lmsmasterrumus`; setiap perbedaan lain
dijelaskan atau dibatalkan.

**Batch 2 — Folder kosong dan casing.** Hapus 9 direktori kosong di
`app/Http/Controllers` dan 9 di `resources/js/Pages`; `Requests/System` menjadi
`Requests/SYSTEM`.

Git tidak melacak direktori kosong, sehingga penghapusannya tidak menghasilkan
isi commit — itulah sebabnya `git status` tetap bersih meski folder-folder itu
ada di disk. Konsekuensinya: pembersihan ini hanya berlaku pada working tree
ini, tidak ikut terbawa saat orang lain melakukan clone, dan tidak dapat
diverifikasi lewat diff. Commit Batch 2 karenanya hanya berisi perubahan casing
`Requests/System`; penghapusan direktori dicatat di laporan, bukan di diff.

**Batch 3 — Controller liar.** 20 file sesuai tabel 5.1.

**Batch 4 — Services.** 110 file dipindah dari 17 folder lama (52 file) dan `SOP/` (58 file), sesuai tabel 5.2; 48 file yang sudah selaras di `ESS/KPI/LMS/SYSTEM` tidak disentuh. Dipecah menjadi beberapa
commit per modul tujuan bila diff terlalu besar untuk ditinjau.

**Batch 5 — Jobs, Commands, Requests.** Sesuai tabel 5.3.

**Batch 6 — Dokumentasi.** Perbarui `module-registry.md` dan `CLAUDE.md` untuk
mencatat `ESS` sebagai modul resmi dan `SOP` sebagai submodul `LMS`; perbarui
`module-structure-inventory.md`, `module-structure-live-map.md`,
`repository-wide-grouping-manifest.md`, dan
`structure-alignment-final-status.md` agar mencerminkan kondisi sebenarnya,
termasuk daftar temuan terbuka. Didahului verifikasi read-only ke master grup
fitur seperti dijelaskan di bagian 2.

## 7. Gerbang verifikasi

Dijalankan lengkap pada setiap batch. Batch tidak di-commit bila ada yang gagal.

1. `php -l` pada setiap file yang tersentuh.
2. `composer dump-autoload -o --strict-psr` — nol pelanggaran.
3. **Pemindai referensi.** Memuat autoloader, mengumpulkan setiap simbol `App\…`
   dari pernyataan `use` dan `::class` di `app/`, `routes/`, `tests/`,
   `database/`, lalu memastikan semuanya resolve. Ini gerbang yang menangkap
   kelas temuan T1. Ambang: tepat 5 sisa yang sudah dikenal (T4); nilai lain
   menggagalkan batch.
4. **Snapshot route.** Boot aplikasi, dump `method | uri | nama | middleware |
   action` terurut untuk seluruh 1163 route, bandingkan dengan baseline. Setiap
   perbedaan harus disengaja dan dijelaskan. Menggantikan `route:list` yang
   tidak dapat dijalankan. Snapshot ini sengaja tidak memakai
   `gatherMiddleware()` karena method itu me-resolve controller dari container
   dan memicu kegagalan Google ADC yang sama.
5. `phpunit --testsuite Unit` — harus tetap 77/77.
6. Test Feature terfokus untuk area yang disentuh, khususnya SOP pada Batch 0.
7. `git diff --check` dan tinjauan diff.

Skrip pemindai referensi dan snapshot route dijadikan file permanen di
`scripts/` agar dapat dijalankan ulang, bukan skrip sekali pakai.

### Batas kekuatan verifikasi

Gerbang ini membuktikan setiap kelas yang dirujuk dapat dimuat dan setiap route
terdaftar dengan URI, nama, middleware, dan action yang sama. Gerbang ini
**tidak** membuktikan tiap endpoint mengembalikan hasil yang benar. Suite
Feature memerlukan database hidup, berjalan lebih dari 9 menit, dan sudah gagal
sebagian sebelum perubahan apa pun; suite itu dipakai sebagai pembanding
baseline, bukan gerbang lulus/gagal. Regresi perilaku tetap fase terpisah.

## 8. Di luar scope

- `resources/js/Pages` (442 file) dan folder frontend non-modul
- Pembuatan 5 model yang hilang (T4)
- Perbaikan bug perilaku selain yang disebut di Batch 0
- Struktur `tests/` (`tests/Feature/{HCAbsensi,Ops,Transaksi}` masih memakai
  penamaan lama)
- Perubahan database, DDL, DML, atau apa pun terhadap production
- Push, PR, merge, dan deployment

## 9. Risiko

| Risiko | Mitigasi |
|---|---|
| Referensi kelas berbasis string tidak terdeteksi | Pemindai referensi menangkap `use` dan `::class`; string penuh di config/view dicari manual dengan grep pada nama lama tiap batch |
| Urutan `require` berubah di Batch 1 sehingga middleware ikut berubah | Snapshot route dibandingkan penuh; perbedaan tak terduga membatalkan batch |
| Diff Batch 4 terlalu besar untuk ditinjau | Dipecah per modul tujuan |
| Verifikasi database untuk registry tidak tersedia | Batch 6 berhenti dan keputusan dikembalikan ke user; batch lain tidak bergantung padanya |
| Suite Feature yang sudah merah menutupi regresi baru | Daftar kegagalan Feature dicatat sebagai baseline dan dibandingkan, bukan diabaikan |
