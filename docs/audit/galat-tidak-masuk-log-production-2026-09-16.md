# Galat tidak masuk log di production — 16 September 2026

Keluhan: sebuah kolom belum ada di production, layar menerima **500**, tetapi log
tidak memuat apa pun — hanya baris `info` yang terlihat di log viewer.

Penelusuran ini **read-only**: `gcloud run services describe` dan pemindaian
kode. Tidak ada satu pun perubahan yang dijalankan ke production.

## Tiga sebab yang berdiri sendiri

### 1. Exception ditelan blok `catch` dan tidak pernah sampai ke Handler

Ini penyebab utama kasus yang Anda alami. **649 blok `catch` di `app/`**
menangkap exception lalu memulangkan 500 sendiri, sehingga `App\Exceptions\Handler`
tidak pernah melihatnya dan pencatatan otomatis Laravel tidak pernah berjalan.

| Modul | Nol log | Ke kanal bernama | Ke `laravel.log` |
| --- | --- | --- | --- |
| SYSTEM | **82** | 2 | 14 |
| ESS | 27 | 126 | 56 |
| LMS | 13 | 17 | 192 |
| VISITOR | 1 | 0 | 10 |
| KPI | 0 | **103** | 3 |
| **Total** | **123** | **248** | **278** |

Yang "ke kanal bernama" tetap tercatat, tetapi ke berkas modulnya sendiri
(mis. `kpiNotifUpdate.log`) — **bukan** `laravel.log`. KPI contoh paling tajam:
103 berbanding 3, jadi hampir setiap 500 di KPI tidak akan pernah muncul kalau
yang dibuka hanya `laravel.log`.

`Invalid column name` melahirkan `Illuminate\Database\QueryException`, turunan
`\Exception`, jadi tertangkap `catch (\Exception $e)` mana pun di jalurnya lalu
berubah jadi `ResponseHelper::error('Gagal mengambil data', 500)` tanpa jejak.

### 2. Berkas log di Cloud Run bersifat per-instance dan fana

Service production `hcisxkpi` (asia-southeast1): **`maxScale: 3`,
`minScale: 0`, `containerConcurrency: 80`**. Tiap instance menulis
`storage/logs/` di dalam containernya sendiri, dan `.dockerignore:20` membuang
`*.log` dari image sehingga tiap container mulai kosong.

Log viewer adalah halaman HTTP: ia menampilkan berkas milik instance yang
kebetulan melayani permintaan itu. Baris yang ditulis instance A tidak terlihat
dari B, dan musnah permanen saat A didaur ulang. Baris `info` selamat dari undian
ini karena ditulis hampir di setiap permintaan; sebuah galat adalah peristiwa
tunggal di satu instance pada satu momen.

### 3. Production berjalan dengan `APP_DEBUG=true`

Dari 40 env var pada service itu: `APP_ENV = staging`, `APP_DEBUG = true`, dan
**tidak ada `LOG_CHANNEL` maupun `LOG_LEVEL` sama sekali** (jadi level bukan
penyebabnya — bawaannya `debug`). Dengan debug menyala, `Handler::render()`
keluar lebih awal sehingga pengguna production melihat stacktrace Ignition, bukan
halaman error Vue.

## Yang diperiksa dan ternyata BERSIH

Supaya tidak ditelusuri ulang kelak:

- `reportable(function (Throwable $e) {})` yang kosong di `Handler.php` **bukan**
  penyebab. Di `ReportableHandler::__invoke`, hanya nilai balik `false` yang
  menghentikan pelaporan; closure kosong memulangkan `null`.
- `QueryException` **tidak** ada di `internalDontReport`. Exception yang dibiarkan
  lolos pasti tercatat.

## Perbaikan yang sudah dikerjakan (kode)

| Berkas | Perubahan |
| --- | --- |
| `app/Http/Middleware/CatatGalatServer.php` | **Baru.** Middleware **global** & *terminable*: setiap tanggapan 5xx yang belum dilaporkan dicatat, lengkap dengan path, rute, user, dan kalimat yang dilihat pengguna |
| `app/Http/Kernel.php` | Middleware didaftarkan di lapis paling luar `$middleware` |
| `app/Exceptions/Handler.php` | `reportable()` yang tadinya kosong jadi **penanda** `hcis.galat_dilaporkan`, supaya galat yang jujur tidak tercatat dua kali. Tetap memulangkan `null` agar pelaporan bawaan jalan |
| `app/Helpers/ResponseHelper.php` | **`galatServer($e, $pesan)`** — mencatat kelas, pesan, berkas:baris, dan `getSql()` untuk `QueryException`, lalu memulangkan 500 berpesan generik. Binding SQL sengaja tidak ikut (bisa memuat data pribadi) |
| `config/logging.php` | Daftar kanal `stack` dibaca dari `LOG_STACK` (bawaan tetap `single`) |
| 4 blok `catch` | Dimigrasikan ke `galatServer()` di `KuotaManagementController` dan `ContentManagementController` |

Penjaga regresi: `tests/Feature/SYSTEM/Observability/SetiapGalatServerBerjejakTest.php`.

Migrasi otomatis hanya menyentuh 4 blok yang bentuknya tidak ambigu. Sisanya
memulangkan bentuk JSON kustom, sehingga mengubahnya akan menggeser kontrak
frontend — itu pekerjaan bertahap. Untuk sementara **semuanya sudah tertutup
middleware**; yang belum mereka punya hanyalah detail exception-nya.

## Tugas pengguna — perubahan env production

Belum dijalankan. Keduanya membuat **revisi baru** dan me-restart service, jadi
pilih waktunya sendiri.

```bash
# 1. Alirkan log ke Cloud Logging, tetap menulis berkas untuk log viewer.
gcloud run services update hcisxkpi \
  --region=asia-southeast1 \
  --update-env-vars=LOG_STACK=single,stderr

# 2. Matikan debug di production (pengguna berhenti melihat stacktrace Ignition,
#    halaman error Vue mulai tampil). Pertimbangkan juga APP_ENV=production.
gcloud run services update hcisxkpi \
  --region=asia-southeast1 \
  --update-env-vars=APP_DEBUG=false
```

`--update-env-vars` hanya menyentuh kunci yang disebut dan **tidak** menghapus
env lain — berbeda dengan `--set-env-vars`, yang menggantikan seluruh daftar dan
akan membuang kredensial basis data. Peringatan yang sama tertulis di
`cloudbuild.yaml`.

Rollback: jalankan perintah yang sama dengan nilai lama
(`LOG_STACK` dihapus dengan `--remove-env-vars=LOG_STACK`, `APP_DEBUG=true`), atau
kembalikan trafik ke revisi sebelumnya lewat `gcloud run services update-traffic`.

Catatan: `Log::channel('nama')` (368 pemanggilan error) tetap hanya menulis ke
berkas. Kalau ingin ikut awet, kanal-kanal itu perlu perlakuan serupa — pekerjaan
terpisah.

## Batas penelusuran ini

Isi Cloud Logging **tidak bisa saya baca**: akun
`595840247695-compute@developer.gserviceaccount.com` ditolak
(`PERMISSION_DENIED for all log views`), dan `projects.getIamPolicy` juga ditolak.
Diagnosis di atas bersandar pada fakta konfigurasi dan pemindaian kode, bukan pada
baris galat yang dilihat langsung.

---

## Susulan: triase blok yang menelan galat (SYSTEM, ESS, KPI)

### Koreksi angka

Hitungan pertama saya salah dan perlu dicatat supaya tidak diulang. Dari 585
"blok bisu", **177 di antaranya melempar ulang** (`throw $e;`) — exception-nya
tetap sampai ke Handler dan pasti tercatat. Angka sebenarnya:

| | Kandidat mentah | Melempar ulang | **Benar-benar menelan** |
| --- | --- | --- | --- |
| Seluruh repo | 585 | 177 | **408** |
| SYSTEM | 104 | 63 | **41** |

Pelajarannya sama dengan yang sudah tercatat di tempat lain: keluaran pemindai
statis adalah **kandidat**, bukan temuan.

### Pembeda yang benar

Bukan isi blok `catch`-nya, melainkan **apakah blok `try`-nya menyentuh basis
data**. Mayoritas blok yang memulangkan `null`/`[]`/`false` ternyata penjaga
`Carbon::parse` — sah, dan menambahkan log di sana hanya jadi derau.

Yang menelan galat **dari kueri basis data**: **118 blok**.

| Modul | Jumlah |
| --- | --- |
| ESS | 35 |
| VISITOR | 28 |
| LMS | 23 |
| SYSTEM | 21 |
| KPI | 6 |
| lainnya | 5 |

Di dalam 118 itu pun mayoritas disengaja dan berkomentar: penanganan
unique-violation, `fallback` aturan lembur, "tabel belum dibuat → jangan jatuhkan
seluruh panel". Yang diperbaiki hanya yang benar-benar menyembunyikan kegagalan.

### Yang diperbaiki

| Berkas | Sebelum | Sesudah |
| --- | --- | --- |
| `SYSTEM/Audit/Activity/AdminActivityController.php` (daftar diagnostik) | `catch` → halaman **kosong berlabel `'OK'`, status 200** | Dicatat lengkap dengan `getSql()`; bentuk tanggapan tetap, kalimatnya berhenti berkata "OK" untuk sesuatu yang gagal |
| `SYSTEM/Audit/Activity/AdminActivityController.php` (jejak audit export) | `catch (\Throwable) { // ignore }` | `Log::warning` — export tetap jalan, tapi tidak ada lagi export yang tak tercatat |
| `KPI/Performance/Update/UpdatePerformanceController.php` (`Wa_Sent_Count`) | `catch { // Kolom mungkin belum ada }` | Dugaan itu kini bisa dibuktikan: `warning` + SQL. Pengiriman WA tetap tidak digagalkan |
| `KPI/Performance/Update/UpdatePerformanceController.php` (periode aktif) | `catch { return null; }` | Nilai balik tetap `null`, tetapi "tidak ada jadwal berjalan" kini bisa dibedakan dari "kuerinya gagal" |
| `ESS/Activity/Center/ActivityCenterService.php` | `catch { return []; }` | `warning` + SQL; panel tetap tidak dijatuhkan |

### Yang SUDAH diperiksa dan sengaja dibiarkan

Supaya tidak ditelusuri ulang: `DatabaseChangeDashboardController` memulangkan
422 **berisi pesan aslinya** (`normalizeApiError()` di frontend membuang pesan
pada 404); `TaskOutboxHandlerController` menulis galatnya ke tabel outbox;
`RuntimeRecoveryController` punya `logOpsEvent` sendiri; `DepartemenController`
dan `EntitasController` menangkap `\DomainException` — exception bisnis yang
memang membawa kalimat untuk admin, bukan galat teknis yang tertelan; blok di
command (`ApiNoncePrune`, `CachePrune`, `DbHealthSnapshot`) mencetak ke stderr
dan sengaja tidak menggagalkan proses.

### Belum dikerjakan

- VISITOR (28) dan LMS (23) belum ditriase.
- Kelima perbaikan di atas **tanpa uji regresi**: menguji jalur-jalur itu butuh
  fixture yang berat, dan menambahkan uji yang tidak pernah terlihat merah lebih
  buruk daripada tidak menambahkannya.
- Command dan queue job tidak punya tanggapan HTTP, jadi middleware tidak pernah
  berjalan di sana. Penjaga terpisah lewat `Queue::failing` dan `CommandFinished`
  masih terbuka.

### Triase VISITOR, DMS, dan LMS

**Tidak ada folder `DMS` di kode.** Isinya berada di `app/.../LMS/SOP/*` — di
basis data SOP milik grup DMS, tetapi kodenya masih bernaung di LMS.

| Modul | Kandidat | Cacat nyata |
| --- | --- | --- |
| VISITOR | 28 | **0** |
| DMS (`LMS/SOP`) | 20 | **1** |
| LMS (non-SOP) | 3 | **0** |

**VISITOR — nol, dan justru contoh terbaik di repo.** Dua puluh lima dari 28
kandidat memanggil `VisitorApiFailure::respond($e, $operation, $errorCode,
$safeMessage)`. Helper itu menulis
`Log::error('VISITOR_API_OPERATION_FAILED', [...])` lengkap dengan objek
exception, `request_id` (UUID), nama operasi, dan kode galat stabil, lalu
memulangkan 500 berkode. Pemindai saya menandainya "bisu" hanya karena tidak ada
tulisan `Log::` di dalam blok `catch`-nya — persis kekeliruan yang sama seperti
pada blok yang melempar ulang.

**DMS (`LMS/SOP`) — satu.** Sembilan belas `\DomainException` dan sebelas
`RuntimeException` adalah exception bisnis yang membawa kalimat untuk admin dan
dipulangkan sebagai 422; itu desain, bukan cacat. `SopAdminKebijakanController`
punya helper `serverError()` yang sudah mencatat. Yang nyata:
`SopPenjadwalanListService::taskAktif()` memulangkan `[]` diam-diam saat kueri
tabel task gagal — "tidak ada penugasan massal berjalan" dan "kueri gagal"
menghasilkan layar yang sama persis, sehingga admin menunggu proses yang tidak
pernah terlihat oleh sistem. Sudah diberi `Log::warning` + `getSql()`; daftar
tetap tidak dijatuhkan.

**LMS (non-SOP) — nol.** Satu sudah dimigrasikan ke `galatServer()`, satu
`continue` yang sengaja meneruskan batch dokumen, satu 400 berisi pesan aslinya.

### Ringkasan lima modul

Dari 118 blok yang menelan galat kueri basis data, yang benar-benar
menyembunyikan kegagalan dan sudah diperbaiki: **enam**. Sisanya disengaja,
berkomentar, atau mencatat lewat helper tersendiri.
