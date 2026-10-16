# Audit performa per modul — dokumen hidup

Melacak penyisiran N+1, lock panjang, kueri tak optimal, kueri berulang yang
layak disentralkan, dan pola yang berpotensi merugikan — **per modul, per
fitur**, dengan cakupan yang terukur supaya tidak ada yang terlewat dan
serah-terima ke tahap berikutnya tidak kehilangan konteks.

- **Mulai:** 4 Sep 2026
- **Perkakas:** lihat `docs/architecture/diagnostik-kueri-dan-lock.md`
- **Status:** SELURUH modul tersapu di jalur GET (Fase 1–9); temuan #8–#10, #12–#16, #20–#25 beres; **#1 & #19 GUGUR**; **#2 & #18 menunggu keputusan produk**

---

## Aturan main

Tiga aturan yang lahir dari kesalahan nyata di jam-jam pertama audit ini.
Ketiganya wajib dipatuhi siapa pun yang melanjutkan.

### 1. Penyisiran statis TIDAK boleh dipakai memprioritaskan

Pemindai pola memberi daftar panjang tanpa bobot, dan sebagian besar isinya
tidak terbukti merugikan. Versi pertama pemindai bahkan membaca **komentar
sebagai kode**: kedelapan temuan non-sargable di `ShiftApprovalController`
ternyata blok yang sudah dimatikan, dan kelima "jaringan dalam transaksi"
ternyata panggilan sesudah `commit` atau di dalam `catch`.

Angka pemindai yang memuat positif palsu **lebih buruk daripada tidak ada
angka** — ia membuat orang mengejar hantu dengan yakin.

### 2. Waktu dinding pada dev TIDAK bisa dipercaya

DB dev ada di `team311.dyndns.info`, di luar mesin. `SELECT 1` polos memakan
**126–142 ms**. Artinya hampir semua "kueri lambat" di dev sebenarnya latensi
jaringan, bukan kerja basis data.

Yang jujur: **logical reads** dan **CPU**, keduanya tak terpengaruh jaringan.

### 3. Data dev sering TERLALU KECIL untuk membuktikan apa pun

Contoh nyata: `HomepageKpiService:2287` memakai
`LEFT(LTRIM(RTRIM(CAST(Periode…))))`, pola non-sargable klasik. Diukur:

```
non-sargable : 7 logical reads, 255 us CPU
sargable     : 7 logical reads, 446 us CPU
```

Identik — `KPI_Update_Performance` hanya **116 baris**; tak ada seek yang bisa
hilang. Temuan seperti ini ditandai **"berisiko, belum terbukti"**, bukan
dijadikan pekerjaan.

### 4. Baris `uji:` mengukur UJINYA, bukan produknya

Rekaman dari suite uji mengatribusi kueri ke nama uji. Uji yang sengaja
melooping 30 karyawan tampak seperti N+1 80× padahal bukan cacat produk. Dan
"kueri sama di 38 jalur" ternyata `delete from …` pembersihan fixture.

Untuk mencari cacat produk, **hanya baris ber-rute HTTP yang dipakai**.

### 5. Rute yang menjawab 5xx diperiksa SEBABNYA, bukan dihitung saja

Enam dari 53 rute Leave menjawab 500 saat disapu. Godaannya adalah mencatat
"6 galat" lalu lanjut. Ketika sebabnya ditelusuri, keenamnya ternyata satu
cacat yang sama, dan cacat itu ada di **38 tempat** lintas empat modul —
temuan terbesar fase ini justru datang dari angka yang paling mudah
diabaikan.

### 6. `dm_exec_query_stats` MENCAMPUR lalu lintas uji dengan lalu lintas produk

Aturan ini lahir dari kesalahan yang paling mahal di audit ini: **temuan #1,
yang selama berhari-hari tercatat sebagai temuan produk TERBESAR, ternyata
adalah ujinya sendiri.**

`N_HRIS_VW_All_Transactions2` tercatat 7.470 logical reads × 1.934 eksekusi.
Angkanya benar. Tafsirannya salah — tidak ada satu pun kode produksi yang
menyentuh view itu:

- enam rujukan di `app/` seluruhnya **komentar**;
- `sys.sql_expression_dependencies` → **nol** objek basis data merujuknya;
- `AllTransactionsReadService` sudah menggantikannya, dan
  `AllTransactionsReadParityTest` ada justru untuk membuktikan penggantinya
  setia — uji itulah yang melooping 966 karyawan, dua kali suite dijalankan.

Aturan 4 sudah menyatakan hal yang sama untuk baris `uji:` pada rekaman per
rute. DMV tidak punya atribusi rute **sama sekali**, jadi di sana bahayanya
lebih besar, bukan lebih kecil.

**Sebelum menyebut angka DMV sebagai temuan produk, buktikan dulu ada kode
produksi yang menjalankannya.** Dua pemeriksaan, keduanya murah:

```bash
grep -rn "<nama objek>" app/ --include=*.php     # rujukan kode
```
```sql
SELECT OBJECT_NAME(referencing_id)
FROM sys.sql_expression_dependencies
WHERE referenced_entity_name = '<nama objek>';   -- rujukan basis data
```

---

## Cakupan ESS

Diperbarui 5 Sep 2026, dihitung dari rute GET yang benar-benar dijalankan lewat
`scripts/audit/sapu-rute.php`.

| fitur | rute GET disapu | catatan |
|---|---:|---|
| Attendance · Leave | 53 dari 62 | 9 berparameter belum tersentuh |
| Attendance · Shift | 61 dari 63 | 2 berparameter belum tersentuh |
| Dashboard · Homepage | 52 | sehat, tak ada yang perlu digarap |
| Attendance · Legacy | 14 | tenang, 2 pola kecil belum digarap |
| Attendance · Overtime | 0 | **belum** |
| sisanya | 0 | **belum** |

Awalnya (4 Sep) cakupannya **1,4%** — 3 rute dari 211.

> **Dua batas yang tetap berlaku.** Angka di atas hanya rute **GET**; seluruh
> rute yang mengubah data belum tersentuh sama sekali dan memang tak boleh
> disapu otomatis di dev. Dan "tersapu" berarti dijalankan sekali dengan
> parameter baku — bukan berarti setiap cabang di dalamnya sudah dilewati.
> Kesimpulan "ESS sudah bersih" **tidak sah**.

### Peta fitur menurut besar kode

| submodul · fitur | ctrl | svc | job/cmd | baris |
|---|---:|---:|---:|---:|
| Attendance · Leave | 8 | 6 | 11 | 22.255 |
| Dashboard · Homepage | 3 | 8 | 0 | 19.863 |
| Attendance · Shift | 7 | 2 | 0 | 16.646 |
| Attendance · Overtime | 3 | 2 | 0 | 8.703 |
| Attendance · Legacy | 1 | 0 | 0 | 7.460 |
| Dashboard · Personal | 2 | 1 | 1 | 4.142 |
| Attendance · Processing | 1 | 6 | 8 | 2.176 |
| Organization · Team | 2 | 0 | 0 | 2.075 |
| Employee · DataCollection | 2 | 8 | 2 | 2.057 |
| Workflow · Ticket | 1 | 0 | 0 | 1.299 |
| Activity · Center | 1 | 2 | 0 | 1.067 |
| Attendance · Calendar | 2 | 2 | 1 | 658 |
| Workflow · CrossDivision | 1 | 0 | 0 | 654 |
| Workflow · Review | 1 | 0 | 0 | 577 |
| Employee · Profile | 3 | 0 | 0 | 428 |
| Attendance · Correction | 1 | 0 | 0 | 389 |
| Employee · Promotion | 0 | 0 | 1 | 365 |
| Workflow · Task / Evaluation / Project / Case | 4 | 0 | 0 | 513 |
| Dashboard · Leadership | 0 | 0 | 1 | 64 |
| Organization · Division / Level | 2 | 0 | 0 | 34 |

### Berkas raksasa — penanda risiko tersendiri

```
9.040  app/Services/ESS/Attendance/Shift/Request/ShiftRequestService.php
7.777  app/Services/ESS/Dashboard/Homepage/HomepageLeaderDashboardService.php
7.460  app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php   <- 1 controller, 0 service
3.777  app/Http/Controllers/ESS/Attendance/Leave/Admin/CutiBersamaAdvancedController.php
3.696  app/Services/ESS/Dashboard/Homepage/HomepagePersonalDashboardService.php
```

---

## Urutan penyisiran ESS

Diurut menurut **besar kode × belum tersentuh**, bukan tebakan.

| # | fitur | alasan | status |
|---|---|---|---|
| 1 | Attendance · Leave | terbesar (22k baris) | **tersapu** — 53/62 rute GET |
| 2 | Attendance · Shift | 16,6k baris | **tersapu** — 61/63 rute GET |
| 3 | Dashboard · Homepage | paling panas | **tersapu** — 52 rute GET, sehat |
| 4 | Attendance · Legacy | 7,4k baris satu controller | **tersapu** — 14 rute GET, tenang |
| 5 | Attendance · Overtime | 8,7k baris | belum |
| 6 | sisanya | < 4k baris | belum |

> **Yang SENGAJA belum tersentuh di semua fase:** rute berparameter (butuh id
> nyata) dan **seluruh rute yang mengubah data**. Basis data dev dipakai
> bersama; menyapu POST/PUT/DELETE di sana berarti mengubah data orang yang
> sedang bekerja. Lubang yang diakui lebih baik daripada cakupan yang
> dilebih-lebihkan.

---

## Register temuan

Status: **terbukti** (ada angka) · **berisiko** (pola nyata, dampak belum
terukur) · **gugur** (positif palsu) · **beres**.

| # | modul | tempat | temuan | bukti | status |
|---|---|---|---|---|---|
| 1 | ESS | `N_HRIS_VW_All_Transactions2` | ~~view mahal, jalan tiap pemuatan halaman~~ | angkanya benar (7.470 baca × 1.934 eksekusi) tapi itu **uji parity-nya sendiri**; nol kode produksi & nol objek DB merujuk view ini. Lihat aturan 6 | **GUGUR** |
| 2 | LMS | `TrainingSchedulingController.php:1162, 1232` | 2 panggilan `Http::timeout(8)` ke pihak ketiga **di dalam** transaksi (buka 921, commit 1326) | pola yang sama pernah menjatuhkan produksi lewat WhatsApp; RCSI mati sehingga pembaca ikut mengantre | **terbukti** |
| 3 | ESS | 24 titik non-sargable | `whereMonth`/`whereYear`/`CAST` membungkus kolom | pada dev tak terbukti (7 vs 7 reads, tabel 116 baris) | **berisiko** |
| 4 | ESS | 415 `->get()` tanpa plafon | ambil semua baris | belum diukur | **berisiko** |
| 5 | ESS | 20 `lockForUpdate` | cakupan & lama kunci belum diperiksa | belum diukur | **berisiko** |
| 6 | semua | 5 "jaringan dalam transaksi" | GCS di dalam transaksi | diperiksa satu per satu: semuanya **sesudah** commit atau di `catch` | **gugur** |
| 7 | ESS | 8 non-sargable `ShiftApprovalController` | — | kode yang sudah dikomentari | **gugur** |
| 8 | ESS·KPI·LMS·SYSTEM | 38 titik `->validate()` di dalam try bercatch generik | `ValidationException` tertelan → 422 dilaporkan 500, dan tiap salah ketik parameter jadi `Log::error` | 6 dari 53 rute Leave menjawab 500 karena ini; dibuktikan ulang lewat uji rute (422 vs 500) | **beres** |
| 9 | ESS | `GET /api/v1/cuti-bersama-adv/list` | N+1 enam lapis | **102 → 10 kueri**, 10.666 → 1.127 ms pada 16 acara; jawaban identik bita per bita | **beres** |
| 10 | ESS | `list()` cuti bersama, `ORDER BY Created_At` saja | Created_At tak unik → urutan baris kembar tak terdefinisi → paginasi bisa mengulang/menghilangkan baris | **6 dari 16 acara** berbagi detik yang sama (2026-09-04 10:54:19.513) | **beres** |
| 11 | ESS | `list()` cuti bersama, `->get()` tanpa plafon + paginasi di PHP | seluruh baris dimuat & dirakit di memori untuk menampilkan 10 | sesudah #9 biayanya **O(1) kueri**, tinggal memori; belum jadi masalah pada 16 baris | **diakui, tidak digarap** |
| 12 | ESS | `LemburRequestService`, "cari orang" lembur | 48 kueri **sia-sia**: cache hanya mengisi `next_shift_start` bila besok WORK, ketiadaan kunci dibaca sebagai "belum tahu" | **54 → 6 kueri**, 11,1 → 6,9 dtk pada 958 orang; hasil 958 baris identik | **beres** |
| 13 | ESS | 3 rute Shift (`isLembur`, `getExport`, `shift-rule/group-detail`) | parameter wajib dibaca **tanpa divalidasi sama sekali** → `whereIn(null)`, `strtotime(null)`→1970-01-01, `InvalidArgumentException` lepas | 3 dari 61 rute GET Shift menjawab 500; kini 422 | **beres** |
| 14 | ESS | `getLeaderShiftHistory()` | 3 hitungan pelengkap per baris halaman | `limit=50`: **59 → 9 kueri**; isi identik pada limit 10/50/100 | **beres** |
| 15 | ESS | `HomepageController::getCalendarData()` | `$this->attendanceService` **tak pernah disuntikkan** → 2 rute kalender selalu 500, tertelan `catch (\Throwable)` | `homepage/calendar-data` & `uDash/getCalendarData` kini 200 dengan data | **beres** |
| 16 | uji | `AllTransactionsReadParityTest::test_full_dataset_requester_parity` | melooping 966 karyawan × 2 sisi = ~1.932 kueri atas view 7.470 baca | **361,8 → 31,8 dtk**; hemat ±5,5 menit tiap suite dijalankan, jaminan tak dikurangi | **beres** |
| 17 | semua | 6 controller memakai properti dinamis (`$this->x = $y` tanpa deklarasi) | bekerja hari ini, **usang sejak PHP 8.2** | `perbaikiCheckinout`, `Overtime`, `AdminShift`, `LeaderShift`, `Shift`, `ShiftRequest` | **diakui, tidak digarap** |
| 18 | ESS | `CutiBersamaAdvancedController::execute()` (1438) & `::rollback()` (1957) | **Cloud Task dikirim ke jaringan DI DALAM transaksi yang memegang `lockForUpdate`** pada `N_HRIS_Cuti_Bersama` | rantai dibaca langsung: `DB::transaction` → `dispatchExecutionTaskById` → `cloudTaskService->dispatch` → `client()->createTask()`. Pola yang sama dengan #2 | **terbukti — menunggu keputusan** |
| 19 | ESS·KPI·VISITOR | 70 titik `lockForUpdate`/`sharedLock` | ~~38 punya panggilan jaringan~~ | dengan batas metode yang benar tinggal **3 kandidat**, dan ketiganya **positif palsu** — rinciannya di bawah | **GUGUR** |
| 20 | SYSTEM | `PeringatanJaringanDalamTransaksi` di `CloudTaskService::dispatch()` | penjaga **runtime**: panggilan jaringan di dalam transaksi dicatat sebagai peringatan, sedalam apa pun bersarangnya | pemindai statis terbukti tak bisa melihat #18; runtime bisa | **beres** |
| 21 | KPI | `akses-user/current` & `akses-user/level/current` | `ceil()` (float) dikirim ke parameter ke-7 `successWithPagination()` yang bertipe `array` → **TypeError, selalu 500** | dua rute mati; nilainya pun mubazir karena helper sudah menghitung `total_page` dengan rumus yang sama | **beres** |
| 22 | LMS | `TrainingCatalogController::index()` & daftar training | `computeProgress()` + `computeStatus()` di dalam `map()`, masing-masing 4–6 kueri | **61 → 20 kueri** pada 9 baris; jawaban identik di luar tanda tangan GCS | **beres** |
| 23 | LMS | `ringkasanMassal()` versi pertama | mengirim seluruh id dalam satu `IN` → **melewati batas 2.100 parameter SQL Server** | ketahuan dari skrip diagnostik pada 2.207 penjadwalan, bukan dari layar; kini dipotong per 500 | **beres** |
| 24 | SYSTEM | `isRegistered()` dipanggil di dalam `map()` pada 2 tempat | satu kueri `exists` per baris → **20× untuk satu halaman** | `api/v1/system-auth/resource-access` **26 → 7 kueri**, jawaban identik | **beres** |
| 25 | VISITOR | seluruh modul | — | 26 rute GET disapu: terberat 6 kueri, **nol** pola berulang, nol galat | **bersih** |
| 26 | LMS | `getExportEmployees()` | `exportEmployeeTrainingSummary()` di dalam `map()` — satu kueri **non-sargable** per karyawan | `lms/admin/monitoring/export/employees` **20 → 6 kueri**; jawaban identik sesudah id terenkripsi didekripsi | **beres** |
| 27 | LMS | `getList()` daftar training & `getKategori()` katalog konten | `count()` di dalam `map()`; daftar training memanggilnya **dua kali per baris**, dan ukuran halamannya bisa diminta lewat `per_page` | `lms/admin/training/list` **21 → 7**, `lms/konten-kategori/kategori` **8 → 2**; jawaban identik di luar tanda tangan GCS | **beres** |
| 28 | ESS | `getHistory()` & `getHistoryStats()` riwayat self-shift | 3 kueri per transaksi + 6 kueri statistik; `limit` datang dari permintaan | `self-shift/history` **33 → 11**, halaman 2 **25 → 7**; jawaban identik utuh | **beres** (#302) |
| 29 | ESS | `getWeekSchedule()` | 2 kueri per hari: fungsi skalar `N_HRIS_FN_GetStatusHari` + jadwal shift | `self-shift/schedule` **20 → 7**, halaman `self-shift` **34 → 22**; bentuk jamaknya sudah ada di berkas yang sama | **beres** (#302) |
| 30 | ESS | `getUserShift()` | `getStatusHariInfo()` di dalam `map()`: fungsi skalar + pencarian hari libur per hari | `absensi/getUserShift` **16 → 10**; tujuh `exec sp_getShiftAbsen` SENGAJA tetap — prosedurnya berkontrak satu tanggal, mengubahnya berarti DDL | **beres** (#303) |
| 31 | LMS | `ProcessBulkPenjadwalan` jalur ujian pihak ketiga | `insertGetId` + `update` untuk **tiap aktivitas tiap peserta** | 40 peserta x 6 aktivitas **496 → 32 kueri** (121,5 → 10,5 dtk); 560 baris hasil diadu, seluruhnya identik | **beres** (#304) |
| 32 | KPI | pembuatan nomor `TAKPI-` (3 tempat) & `AKPI-` (2 tempat) | `MAX(CAST(RIGHT(...)))` + `lockForUpdate` = `UPDLOCK, HOLDLOCK`; tanpa indeks ia mengunci **rentang** sampai transaksi pemanggil commit | tabelnya **124 baris tanpa indeks**, jadi 13,7 dtk itu ANTREAN bukan pemindaian; kini counter atomik, diambil di luar transaksi | **beres** (#305) |
| 33 | KPI | dua penulis seri `AKPI-` | memindahkan **hanya satu** ke counter memutus koordinasi keduanya → **nomor dokumen kembar** | ketahuan lewat sabotase: `9002, 9002, 9003, 9002`. Bukan temuan lapangan — cacat yang HAMPIR saya kirim sendiri | **beres** (#305) |
| 34 | SYSTEM | `db:health-snapshot` | perintahnya ada, bergerbang, dirancang terjadwal — **tak pernah didaftarkan**; tabelnya hanya berisi 8 baris percobaan manual | selama itu, tiap kesimpulan lock hanya turunan dari gejala | **changeset menunggu keputusan** (#306) |
| 35 | LMS | `api/v1/tasks/lms/process-pdf` | 179 permintaan lambat, puncak **80 dtk**, hanya 8 kueri — biayanya render PDF, dan koneksi DB dipegang selama itu | perbaikan yang jelas (lepas koneksi saat render) TIDAK bekerja: rendernya memakai `Cache::store('database')` | **dicatat, tidak digarap** |
| 36 | LMS | `TrainingSchedulingController::update()` | `Http::timeout(8)->post()` **di dalam perulangan bersarang, di dalam transaksi** — kunci tertahan 8 dtk x jumlah aktivitas, lamanya ditentukan pihak lain | dititipkan ke outbox transaksional; panggilannya di handler sesudah commit, dengan percobaan ulang. Ini penyelesaian **temuan #2** | **beres** (#312) |
| 37 | SYSTEM | 31 rute menunjuk metode tak ada | 500 yang menunggu disentuh; dua di antaranya salah tunjuk terang-terangan (`dashboardUser` ke metode yang dikomentari, `crossFeedbackDetail` memakai POTONGAN JALUR sebagai nama metode) | seluruhnya diperiksa, nol dirujuk frontend, semua dihapus; ratchet **31 → 0** | **beres** (#313) |
| 38 | ESS | `CutiBersamaAdvancedController` execute & rollback | `createTask()` ke Google **di dalam transaksi ber-lockForUpdate**. Kuncinya bukan kebetulan: baris log adalah KLAIM yang mencegah dispatch kembar | `dispatch()` dipecah `catat()` (klaim, boleh di dalam) + `kirim()` (jaringan, wajib sesudah commit); pembungkusnya tetap ada sehingga pemanggil lain tak berubah. Ini penyelesaian **temuan #18** | **beres** (#314) |
| 39 | KPI | `FormKpiController::storeTeam()` | dua kueri per anggota yang **tidak bergantung anggota** — jadwal (sama untuk semua) dan pencarian pengajuan lama (tahun sama, beda Id_User) | diangkat keluar perulangan: tim 20 orang **40 → 2 kueri** | **beres** (#315) |
| 40 | ESS | `KonversiLemburService::getHistoryForUser()` | `Storage::disk('gcs')->exists()` per baris — **100 ms per panggilan** dan TAK TERLIHAT penghitung kueri, karena ia bukan SQL | diperiksa per direktori: 24 baris/7 lampiran **1.965 → 1.409 ms**, jawaban identik | **beres** (#316) |
| 41 | SYSTEM | CI `Backend quality` | merah terus-menerus, jadi **tak memberi sinyal apa pun** — regresi nyata tak bisa dibedakan dari kebisingan | dua sebab: suite Unit menembak DB (6 kelas dipindah) dan `APP_KEY` tak pernah disiapkan. Kini **hijau** | **beres** (#308, #311) |
| 42 | LMS | `SopVersiWizard::pakaiPengesahan()` | daftar LARANGAN `!isKuis && Tipe !== 'FORM'` memberi pengesahan ke tipe yang kebetulan tak disebut — dan **VIDEO tidak disebut**, padahal profil resminya melarang | diganti daftar IZIN lewat peta profil yang sudah ada; berkas ujinya sendiri ternyata tak pernah berjalan (mock kit tak lengkap) | **beres** (#309) |

### Temuan #19 — kenapa GUGUR, dan apa yang menggantikannya

Sapuan awal melaporkan **38 dari 70** titik kunci punya "panggilan jaringan di
antara kunci dan commit". Angka itu **salah**, karena dua hal:

1. `->exists()` juga metode Builder dan Collection, bukan hanya `Storage`.
   Sebagian besar barisnya kueri biasa.
2. "Jarak" dihitung sampai `DB::commit` **berikutnya di berkas**, dan itu bisa
   melewati batas metode. `UpdatePerformanceController::handleHeader()`
   terjaring begitu — ia memang punya `lockForUpdate`, tapi **nol**
   `Storage::disk`/`temporaryUrl` di dalam dirinya sendiri.

Dengan cakupan dipotong tepat pada metode yang memuat kuncinya: **3 kandidat**,
dan ketiganya positif palsu setelah dibaca —

| tempat | kenyataannya |
|---|---|
| `handleCloudTaskExecute()`, `handleCloudTaskRollback()` | token "CloudTask" hanyalah `CloudTaskService::markX()`, yang menulis log ke basis data |
| `VerifikatorVisitorController::uploadPermitDokumen()` | unggahan GCS justru **di luar** transaksi, dengan komentar yang menyatakannya |

**Dan satu-satunya cacat yang nyata — temuan #18 — tidak muncul sama sekali**,
karena panggilannya bersarang dua lapis:
`DB::transaction` → `dispatchExecutionTaskById()` → `cloudTaskService->dispatch()`
→ `client()->createTask()`.

Kesimpulannya jelas: **pemindai statis adalah alat yang salah untuk cacat ini.**
Penggantinya temuan #20 — penjaga **runtime** yang mencatat peringatan ketika
`DB::transactionLevel() > 0` pada saat panggilan jaringan terjadi. Runtime tahu
apa yang pemindai tak bisa tahu: pada saat itu, tingkat transaksi bicara apa
adanya, sedalam apa pun bersarangnya.

Sengaja hanya **memperingatkan**, bukan melempar: perbaikan #18 dan #2 masih
menunggu keputusan produk, dan sampai itu dijawab yang dibutuhkan adalah membuat
kejadiannya terlihat — bukan menjatuhkan permintaannya.

### Temuan #8 — rinciannya

Sebarannya: **ESS 29 · KPI 5 · LMS 3 · SYSTEM 1**, terpusat di
`KuotaManagementController` (9), `AdminCutiController` (4), dan
`IzinAtasanApprovalController` (3).

Diperbaiki dengan dua bentuk, dipilih menurut apakah validasinya boleh
berpindah tempat:

| bentuk | jumlah | dipakai bila |
|---|---:|---|
| validasi dinaikkan ke luar `try` | 32 | validasi memang pekerjaan pertama metode itu |
| `catch (ValidationException)` tersendiri | 6 | validasi **tak boleh** pindah |

Enam yang tak boleh pindah, beserta alasannya — ini yang paling mudah salah
kalau ada yang "merapikan" nanti:

- `KpiApprovalController::reject`, `::rejectWithDetail` — `DB::beginTransaction()`
  dijalankan **sebelum** `try`, dan validasinya berada setelah pemeriksaan
  kepemilikan approval. Klausanya wajib `DB::rollBack()` dulu, baru melempar
  ulang. (Sebelum ini, `rollBack()` di catch generik justru dipanggil saat tak
  ada transaksi terbuka.)
- `LmsModulAbsensiController::store`, `::recordActivity`, `::completeActivity` —
  aturannya berlaku pada `$request` **sesudah** `replace()` menaruh hasil decode
  Hashids.
- `SystemAuthController::getResourceAccess` — ada gerbang 403 yang harus
  dijawab lebih dulu; penanya yang tak berhak tak perlu tahu bentuk
  parameternya.

Dijaga oleh `tests/Feature/SYSTEM/DisiplinValidasiTertelanTest.php` (bentuk
kode, seluruh repo) dan `tests/Feature/SYSTEM/ValidasiMenjawab422Test.php`
(perilaku di jaringan). Keempat penjaganya sudah pernah dilihat **merah** lewat
sabotase yang disengaja — termasuk sabotase yang membutakan pemindainya
sendiri, yang membuat penjaga struktural melaporkan repo bersih dan hanya
tertangkap oleh penjaga-bagi-penjaga.

---

## Yang masih menghalangi

1. **Jumlah baris produksi tidak diketahui.** Tanpa itu, temuan #3–#5 tak bisa
   naik dari "berisiko" ke "terbukti". Data dev terlalu kecil.
2. **Rute yang mengubah data belum tersentuh sama sekali**, dan tak bisa disapu
   otomatis di dev. Menyisirnya butuh menjalankan fiturnya sungguhan sebagai
   pengguna, dengan rekaman menyala — atau lingkungan staging tersendiri.
3. **Temuan #2 menunggu KEPUTUSAN, bukan pekerjaan.** `TrainingSchedulingController`
   memanggil pihak ketiga `Http::timeout(8)` **di dalam** transaksi (buka 921,
   commit 1326) — pola persis yang pernah menjatuhkan produksi lewat WhatsApp,
   dan pengungkit terbesar yang masih tersisa untuk "Login timeout expired".
   Memindahkan panggilan itu ke luar transaksi mengubah **semantik kegagalan**:
   kalau HTTP-nya gagal setelah commit, apa yang harus terjadi? Itu pertanyaan
   produk. Selama belum dijawab, jangan digarap sebagai refactor biasa.

---

## Cara melanjutkan (serah-terima)

```bash
# 1. Nyalakan gerbang dev (.env) — baku MATI
DB_REALTIME_WATCH_ENABLED=true
REQUEST_DIAGNOSTICS_TAG_SESSION=true

# 2. Mulai rekaman
php artisan diagnostik:rekam mulai

# 3a. Sisir lewat suite uji (luas, tapi baris 'uji:' bukan data produk)
DIAGNOSTIK_REKAM_UJI=true php artisan test

# 3b. ATAU sisir fiturnya sungguhan lewat browser sebagai pengguna
#     (satu-satunya cara mendapat baris ber-rute HTTP)

# 4. Pantau lock selama itu
php artisan db:health-watch --detik=300 --interval=2

# 5. Hentikan & baca temuannya
php artisan diagnostik:rekam berhenti
php artisan diagnostik:laporan --simpan
```

### Di mana datanya

| | |
|---|---|
| rekaman kueri per rute/uji | `storage/app/diagnostik/rekaman-*.jsonl` |
| laporan tersimpan | `storage/app/diagnostik/laporan-*.md` |
| sampel lock | tabel `N_HRIS_DB_Health_Snapshot` |
| diagnostik per permintaan | tabel `N_HRIS_Request_Diagnostics` |

### Skrip analisa

Sudah dipermanenkan di `scripts/audit/` (lihat READMEnya):

| skrip | gunanya |
|---|---|
| `peta-modul.py` | peta submodul/fitur + cakupan rute |
| `sisir-statis.py` | penyisiran pola (versi yang membuang komentar & melacak cakupan transaksi dengan hitungan kurung — **jangan pakai versi pertama**) |
| `rute-saja.py`, `rute-fitur.py` | daftar rute, dipisah GET vs yang mengubah data |
| `sapu-rute.php` | menyapu rute GET satu fitur lewat kernel, sebagai pengguna login |
| `telan-validasi.py` | mencari validasi yang tertelan catch generik (temuan #8) |

`sapu-rute.php` **hanya menyentuh GET**. Basis data dev dipakai bersama;
menyapu POST/PUT/DELETE di sana berarti mengubah data orang yang sedang
bekerja.

---

## Riwayat fase

### Fase 0 — perkakas (4 Sep 2026) · SELESAI

Pemantau lock realtime, peringkat kueri terberat, perekam per rute, penilai
temuan, laporan Markdown. Semuanya digerbangi tiga kunci dan baku mati.
PR #280.

### Fase 1 — pemetaan ESS (4 Sep 2026) · SELESAI

Inventaris 24 fitur ESS, cakupan rute terukur (1,4%), 5 berkas raksasa
teridentifikasi, urutan penyisiran ditetapkan.

### Fase 2 — Attendance · Leave (4 Sep 2026) · SEBAGIAN

**Yang disapu.** 53 rute GET tanpa parameter, lewat
`php scripts/audit/sapu-rute.php ESS Leave 4`:

```
43 ok · 4 ditolak (4xx) · 6 galat (5xx)
```

**Yang SENGAJA belum tersentuh** — lubang yang diakui lebih baik daripada
cakupan yang dilebih-lebihkan:

- **9 rute GET berparameter.** Butuh id nyata; belum dijalankan.
- **Seluruh rute yang mengubah data** (POST/PUT/DELETE). Tidak boleh disapu
  otomatis di basis data dev yang dipakai bersama.

**Yang ditemukan.** Temuan #8 (38 titik validasi tertelan — **beres**),
temuan #9 (`cuti-bersama-adv/list`, N+1 enam lapis — **beres**), temuan #10
(urutan tanpa pemutus seri — **beres**), temuan #11 (`->get()` tanpa plafon —
**diakui, tidak digarap**).

#### Temuan #9 — rinciannya

`CutiBersamaAdvancedController::list()` menembak satu kueri induk lalu **enam
kueri per baris**:

| x | kueri |
|---:|---|
| 32 | `N_HRIS_Cloud_Task_Log` terbaru (EXECUTE & ROLLBACK, 2× per baris) |
| 16 | agregat hitungan dari `N_HRIS_Cuti_Bersama_Detail` |
| 16 | hitungan dari `N_HRIS_Cuti_Bersama_Exclusion` |
| 16 | `MAX(Rolled_Back_At)` dari `…_Detail` |
| 16 | katalog kolom, dari `Schema::hasColumn()` yang dipanggil per baris |

Diperbaiki dengan mengingat jawaban `Schema::hasColumn()` sekali per permintaan
dan mengambil empat sisanya massal sebelum `map()`, di `ambilPelengkapDaftar()`
— satu agregat ber-`GROUP BY`, satu hitungan exclusion, dan satu
`ROW_NUMBER() OVER (PARTITION BY Reference_Id, Task_Type)` untuk memilih log
terbaru di basis data, bukan di PHP.

```
sebelum : 102 kueri, 10.666 ms
sesudah :  10 kueri,  1.127 ms
```

Seluruh badan JSON disimpan sebelum dan sesudah lalu dibandingkan — **identik
bita per bita**. Satu beda yang sempat muncul: `total_detail` berubah dari
string `"0"` jadi angka `0` pada acara tanpa baris detail. Driver PDO
mengembalikan `COUNT(*)` sebagai string, dan itulah yang selama ini sampai ke
layar; `detailKosong()` kini menirunya apa adanya. Merapikan tipenya adalah
keputusan tersendiri, bukan sesuatu yang boleh diam-diam ikut terbawa perbaikan
performa.

#### Temuan #11 — kenapa TIDAK digarap

`->get()` tanpa plafon lalu dipaginasi di PHP memang bentuk yang buruk, tapi di
sini ia **tak bisa dipindahkan ke SQL tanpa memindahkan aturan bisnisnya juga**.
Saringan status layar bekerja pada `execution_snapshot`, yang diturunkan di PHP
lewat `buildExecutionSnapshot()` — sekitar sepuluh cabang yang membaca hitungan
detail, log tugas, dan medan barisnya sendiri. Menuliskan ulang cabang-cabang
itu sebagai `WHERE` berarti aturan yang sama hidup di dua tempat, dan itu pola
yang sudah berkali-kali menggigit repo ini (lihat "aturan dua pintu").

Sesudah #9, biayanya **O(1) kueri** berapa pun jumlah barisnya; yang tersisa
hanya memori dan CPU PHP, dan pada 16 acara itu belum jadi masalah. Kalau suatu
hari acaranya menembus ratusan, jalan keluarnya bukan memaksa paginasi SQL
melainkan memindahkan turunan status ke kolom tersimpan yang dirawat saat
eksekusi — dan itu pekerjaan tersendiri, bukan tempelan.

**Berikutnya di fitur ini:** 9 rute GET berparameter yang belum tersentuh.

### Fase 3 — Attendance · Shift (4–5 Sep 2026) · SEBAGIAN

**Sapuan.** 61 rute GET tanpa parameter: **45 ok · 11 ditolak (4xx) · 3 galat
(5xx)**. Sebelas penolakan 4xx itu kabar baik — sebagian dulunya 500, dan kini
jujur berkat temuan #8. Dua rute berparameter (`self-shift/detail/{no}`,
`leader-shift/detail/{no}`) belum tersentuh: butuh nomor transaksi nyata.

**Temuan.** #13 (tiga rute 500 → 422, **beres**) dan #14 (riwayat berbiaya per
baris, **beres**).

**Shift TIDAK punya N+1 katastrofal.** Rute terberat 38 kueri — bandingkan
`cuti-bersama-adv/list` yang 102. Empat pola satu-kueri-per-baris yang tersisa,
semuanya modest dan **belum digarap**:

| rute | pola |
|---|---|
| `self-shift/history` | 10× EXISTS approval per transaksi |
| `shift-approval/history` | 7× SELECT detail per transaksi |
| `self-shift`, `self-shift/schedule` | 7× `N_HRIS_FN_GetStatusHari` (satu per hari) |
| `absensi/getUserShift` | 7× `sp_getShiftAbsen` |

### Fase 4 — Dashboard · Homepage (5 Sep 2026) · SELESAI

**Homepage sehat.** 64 rute disapu; terberat 31 kueri (`api/homepage`), dan
satu-satunya bentuk berulang ≥5× adalah `N_HRIS_FN_GetStatusHari` 7× (satu per
hari dalam sepekan). Kerja konsolidasi bootstrap sebelumnya membuahkan hasil —
tidak ada yang perlu digarap di sisi performa.

**Temuan.** #15 — dua rute kalender **selalu 500** karena properti yang tak
pernah disuntikkan (**beres**), plus penjaga baru yang menutup seluruh kelas
cacat itu untuk 133 controller.

### Fase 5 — Attendance · Legacy (5 Sep 2026) · SEBAGIAN

14 rute GET disapu, semuanya 200. Terberat 23 kueri
(`izin/getDataIzinAdmin`). Dua pola berulang, **belum digarap**:
`absensi/getUserShift` 7× `sp_getShiftAbsen`, dan `izin/getDataIzinAdmin` 5×
lookup `Tanggal_Masuk_Pulang` per transaksi.

Meski berkasnya raksasa (`AbsensiController` 7.460 baris, nol service), jalur
GET-nya ternyata tenang. Risiko di sini kemungkinan ada di jalur yang mengubah
data — dan itu **tidak** boleh disapu otomatis.


### Fase 6 — Attendance · Overtime (5 Sep 2026) · SEBAGIAN

28 rute GET disapu. **Overtime tenang**: rute terberat 9 kueri.

- `konversi-lembur/history` **14 → 7 kueri**, `stats` **10 → 3** — langkah
  approval diambil massal lewat bentuk jamak yang sudah lama ada di kelas yang
  sama, hanya belum dipakai di sini (#22 versi Overtime).
- `GET /getOffset` **dihapus**: menunjuk metode yang tidak ada, jadi selamanya
  500, dan tak ada kode layar yang memanggilnya.
- Menelusurinya membuka temuan yang lebih besar: **32 rute** di seluruh aplikasi
  menunjuk metode tak ada. Dijaga ratchet `RuteMenunjukMetodeAdaTest` pada
  angka 31; utang lama diakui, yang dilarang menambah utang baru.

**Dicatat, belum digarap:** `Storage::disk('gcs')->exists()` dipanggil per baris
berlampiran di dalam `map()` yang sama — perjalanan jaringan yang tak terlihat
penghitung kueri. Di dev baru 1 dari 8 baris berlampiran, jadi berisiko tapi
belum terbukti.

### Fase 7 — KPI (5 Sep 2026) · SEBAGIAN

57 rute GET disapu. **KPI tenang**: rute terberat 27 kueri, satu pola berulang
≥5× (`penjadwalan-kpi/audit/target-sebelum-mulai`, 7× lookup
`KPI_Master_Penjadwalan`) yang **belum digarap**.

Yang ditemukan: temuan **#21** — dua rute yang selalu 500. Kelasnya ikut
disisir: 5 pemanggil `successWithPagination*` dengan argumen ke-7 bukan array,
tiga di antaranya positif palsu.

### Fase 8 — LMS (5 Sep 2026) · SEMUA YANG TERCATAT BERES

Temuan terberat sejak cuti bersama: `lms/training/catalog` **61 kueri** untuk 9
baris — 58 di antaranya per-baris. **61 → 20** (#22).

**Tiga pola lain yang dicatat di sapuan ini, ketiganya kini beres:**

| rute | pola |
|---|---|
| ~~`lms/admin/monitoring/export/employees`~~ | ~~15× kueri non-sargable~~ — **beres**, lihat #26 |
| ~~`lms/admin/training/list`~~ | ~~8× `count(*)` per baris~~ — **beres**, lihat #27 |
| ~~`lms/konten-kategori/kategori`~~ | ~~7× `count(*)` per baris~~ — **beres**, lihat #27 |

`lmsmodulabsensi` dan `lmsmodulabsensi/create` menjawab 500 — keduanya termasuk
31 rute yang menunjuk metode tak ada, sudah tercakup ratchet Fase 6.

### Fase 9 — VISITOR & SYSTEM (5 Sep 2026) · SEBAGIAN

**VISITOR bersih** (#25). 26 rute GET: terberat 6 kueri, nol pola berulang, dan
satu-satunya jawaban non-200 adalah 403 pada `admin/visitor/master-data` —
gerbang izin yang memang bekerja. Dicatat sebagai temuan justru supaya "sudah
diperiksa" bisa dibedakan dari "belum diperiksa".

**SYSTEM** — semua jawaban non-200 adalah 401/403/422 yang sah. Dua pola
berulang: `system-auth/resource-access` (**beres**, #24) dan
`admin/role-manager/dashboard-stats` (7× lookup per grup, **belum digarap**).

### Fase 11 — daftar dihabiskan (6 Sep 2026)

Fase ini bukan sapuan baru: ia menutup **seluruh sisa daftar** yang terkumpul
sejak Fase 10, dan berhenti pada satu item yang memang bukan pekerjaan teknis.

**Pola yang berulang untuk kali kesekian:** mekanisme yang benar sudah ada dan
tidak dipakai. Outbox transaksional lengkap dengan dispatcher, registry, dan
pasangan Retryable/Permanent — sudah dipakai `reassignThirdParty` di controller
yang SAMA dengan yang memanggil HC Learn di dalam transaksi (#36). Peta profil
tipe — sudah dipakai benar di dua komponen, hanya wizard-nya yang menulis
aturannya sendiri (#42). `blokBerlaku()`, `sisipkanDenganOutput()`,
`byKodeKaryawansQuery()`, `N_HRIS_FN_GetStatusHariRange`,
`TransactionNumberService`: lima kali pola yang sama sebelum ini.

**Yang paling mahal dari fase ini bukan temuannya, melainkan yang nyaris
terkirim.** Memindahkan SATU dari dua penulis seri `AKPI-` ke counter atomik
memutus koordinasi keduanya dan menghasilkan nomor dokumen kembar
(`9002, 9002, 9003, 9002`) — cacat yang dibuat oleh perbaikan, bukan ditemukan
di lapangan, dan hanya ketahuan karena sabotase dijalankan pada penjaga yang
baru ditulis.

**Empat kekeliruan proses yang dicatat karena semuanya nyaris lolos:**

| kekeliruan | bagaimana ketahuan |
|---|---|
| pemindai sekali-pakai melapor **0** rute rusak, padahal 31 | diadu dengan logika ujinya, bukan dipercaya |
| pemetaan baris menuduh rute yang **masih hidup** (`LevelController` substring dari `UserAksesLevelController`) | dibaca ulang sebelum menghapus |
| metode dihapus sebagai "kode mati" padahal dipanggil **uji lewat refleksi** | suite KPI merah; saya menyisir `app/` dan lupa `tests/` |
| penjaga bentuk **menuduh komentar** yang saya tulis sendiri | penyaringnya dibuat sadar komentar blok |

**Batas yang diakui, bukan disamarkan:** sabotase urutan pada #39 TIDAK
menggigit — data dev tak punya pengguna dengan lebih dari satu pengajuan aktif
per tahun, jadi membalik `ORDER BY` tetap hijau. Isinya teruji, urutannya
belum, dan itu dinyatakan di docblock ujinya.

**Yang tersisa dan sengaja tidak digarap:** `process-pdf` (#35). Ia memegang
koneksi DB sampai 80 detik selama merender, dan perbaikan yang tampak jelas —
melepas koneksinya — tidak akan bekerja karena rendernya memakai
`Cache::store('database')`. Menyelesaikannya berarti memindahkan cache render
keluar dari basis data atau mengubah cara worker dijalankan; keduanya keputusan,
bukan tambalan.

---

### Fase 10 — sumber pemeringkatan diganti (6 Sep 2026)

**Fase ini dimulai dari satu pertanyaan pengguna, bukan dari sapuan:** apakah
sasaran selama ini dipilih dari aktivitas kunci? Tidak. Sasaran dipilih dari
JUMLAH KUERI PER RUTE, hasil sapuan `n1-rute.php` atas **rute GET saja** —
batasan yang saya tetapkan sendiri supaya tidak mengubah data orang lain di dev
bersama.

Akibatnya bisa diukur. Begitu `N_HRIS_Request_Diagnostics` dibuka — tabel yang
sudah dua bulan merekam SETIAP permintaan >=3 detik atau 5xx, dan yang tak
pernah saya sentuh — sepuluh perbaikan sebelumnya ternyata semuanya benar dan
semuanya kecil, sementara yang terberat tak pernah masuk daftar:

| rute | n | kueri | total | keterangan |
|---|---|---|---|---|
| `api/v1/lms/penjadwalan/store` | 37 | 9 – **3.683** | 40 ms – **251 dtk** | POST — tak pernah disapu |
| `api/v1/update-performance/store/temporary` | 80 | 17 | rata **9,6 dtk** | POST — tak pernah disapu |
| `homepage/notifications/unread` | 49 | **1 – 7** | 3,1 – **56,5 dtk** | GET — 5 dtk rata-rata hanya MENUNGGU KONEKSI |

**Pelajarannya, dan ini yang paling mahal dari seluruh audit:** jumlah kueri
adalah proksi biaya, bukan proksi penderitaan. Baris ketiga membuktikannya —
rute yang hampir tak mengerjakan apa pun bisa memakan 56 detik karena antre.
Batasan "GET saja" itu benar sebagai perlindungan data, tapi salah sebagai
strategi pemilihan sasaran, dan tak ada yang memberi tahu selain data.

**Empat sasaran diperiksa lalu DIBUANG,** dan itu bagian dari hasilnya:

- `form-kpi` dan `homepage` — catatan Agustus menyebut 23 dan 17 kueri; nyatanya
  kini **5 dan 6**. Sudah dibereskan sejak itu. Mengoptimalkan sejarah adalah
  bahaya nyata ketika sumbernya data lama.
- `api/v1/activities` — 17,4 dtk pada `TOP 20` atas join **82 x 109 baris**.
  Mustahil sebagai perhitungan, jadi ia korban antrean; menambalnya tak menolong.
- `exam/complete` — 37 kueri, tetapi **tanpa satu pun perulangan berkueri**, dan
  2,2 dari 3,6 dtk waktu DB-nya habis hanya untuk mendapatkan koneksi.

**Batas yang tidak bisa ditembus dari sini.** Semua kesimpulan lock di fase ini
diturunkan dari kemustahilan aritmetik — 13,7 dtk pada tabel 124 baris, 17,4 dtk
pada 82 x 109 baris — bukan dari rekaman siapa memblokir siapa. Yang bisa
merekamnya sudah ada (`db:health-snapshot`, Layer 3, aman produksi) dan
**tidak pernah dijadwalkan** (#34). Sampai itu berubah, pemeringkatan lock tetap
penalaran, bukan bukti.

### Waktu uji — pertanyaan yang ditutup di fase ini

Paralelisasi **tidak bisa dipakai**, dua alasan yang berdiri sendiri:
`--parallel` memberi tiap pekerja basis data sendiri, sedangkan **239 dari 291
berkas uji membaca data dev nyata**; dan mengarahkan pekerja paralel ke satu DB
bersama mengulang persis insiden deadlock yang sudah tercatat.

Biayanya bukan pada ujinya: `SELECT 1` ke `team311.dyndns.info` bermedian
**81 ms**, dan tiap kueri di tiap uji membayarnya. Tak ada perapian uji yang
menembus lantai itu. Satu-satunya tuas besar adalah basis data lokal — mesin
pengembangan ini tidak punya SQL Server sama sekali, tidak ada `.env.testing`,
dan tidak ada override DB di `phpunit.xml`. Itu pekerjaan infrastruktur.

Efek samping yang baru terlihat: karena uji menembak DB yang sedang dipakai
orang lain, menjalankan suite MENAMBAH beban ke sana — yang menjelaskan dua
kegagalan berubah-ubah yang sempat dicurigai regresi lalu terbukti bukan.

---

---

## Cakupan modul — keadaan 5 Sep 2026

Seluruh modul kini sudah tersapu setidaknya sekali di jalur GET.

| modul | keadaan |
|---|---|
| ESS · Leave | tersapu; #8, #9, #10 beres, #11 diakui |
| ESS · Shift | tersapu; #13, #14 beres, 4 pola kecil dicatat |
| ESS · Homepage | tersapu, **sehat**; #15 beres |
| ESS · Legacy | tersapu, tenang; 2 pola kecil dicatat |
| ESS · Overtime | tersapu, tenang; beres, 1 risiko GCS dicatat |
| KPI | tersapu, tenang; #21 beres, 1 pola dicatat |
| LMS | tersapu; #22, #23 beres, 3 pola dicatat |
| VISITOR | tersapu, **bersih** (#25) |
| SYSTEM | tersapu; #24 beres, 1 pola dicatat |

> **Dua batas yang tetap berlaku.** Semuanya hanya rute **GET**; seluruh rute
> yang mengubah data belum tersentuh dan memang tak boleh disapu otomatis di
> dev. Dan "tersapu" berarti dijalankan sekali dengan parameter baku — bukan
> berarti setiap cabang di dalamnya sudah dilewati.

---

## Perkakas pernah berbohong dua kali — bacalah ini sebelum memercayai angka

Keduanya menghasilkan angka yang **terlihat masuk akal**, dan itulah yang
membuatnya berbahaya. Keduanya sudah diperbaiki, tapi polanya akan terulang
pada perkakas berikutnya.

**1. `DB::listen` menumpuk.** Pendengar didaftarkan di dalam perulangan rute
tanpa pernah dilepas, jadi rute ke-50 punya 50 pendengar dan setiap kueri
tercatat 50 kali. Laporannya: `absensi/getUserShift` 799 kueri dengan
pengulangan 329×, dan hampir semua rute "mengulang ~50×". Yang sebenarnya: 17
kueri, pengulangan 7×.
→ Perkakasnya kini punya penjaga sendiri: satu kueri yang pasti jalan sekali
harus tercatat sekali. Terbukti menyala (60×).

**2. Angka sapuan berurutan mengukur URUTAN CACHE, bukan biaya rute.** Cache di
sini berbasis berkas dan bertahan lintas permintaan, jadi rute yang kebetulan
disapu duluan membayar pengisian cache untuk semua yang mengikutinya.
`homepage/notifications/paginate` terbaca **116 kueri** dan sempat disebut
temuan besar — rute itu di-poll tiap 60 detik oleh setiap pengguna. Sesungguhnya
**7 kueri** dalam keadaan mantap.
→ Perkakasnya kini memanaskan tiap rute dulu, lalu mengukur pada jalan kedua.

Pelajaran yang sama juga muncul pada dua pemindai statis: pemindai
"validasi tertelan" versi pertama membaca komentar sebagai kode, dan pemindai
"properti hantu" versi pertama melaporkan **601 temuan** karena lookahead
negatif memotong satu huruf nama metode (601 → 6 → 0).

**Setiap pemindai wajib punya penjaga bagi dirinya sendiri** — disuruh membaca
contoh yang cacatnya sudah diketahui. Tanpa itu, pemindai yang buta melaporkan
repo BERSIH, dan hijau seperti itu lebih berbahaya daripada tidak ada penjaga.

---

## Waktu uji

Misi tersendiri: mempersingkat waktu uji supaya waktu pengembangan lebih
optimal.

| temuan | hasil |
|---|---|
| #16 — parity all-transactions | **361,8 → 31,8 dtk**, hemat ±5,5 menit tiap suite dijalankan |

Pengungkit terbesar berikutnya masih sama seperti yang tercatat di Fase 0:
**basis data dev ada di luar mesin** (`SELECT 1` = 126–142 ms). Memindahkannya
ke lokal bernilai jauh lebih besar daripada optimasi uji mana pun — tapi itu
keputusan infrastruktur, bukan kode.

---

### Fase 12 — kelas cacat selain N+1, dengan kunci akhirnya TERUKUR (6 Sep 2026)

Fase ini menjawab dua permintaan sekaligus: **ukur kunci dalam ms/detik dengan
perkakas aktivitas milik proyek sendiri**, dan **sisir kelas cacat di luar
N+1** — tidak atomik, tidak sargable, tidak terpusat, kembar.

#### Kunci: dari inferensi menjadi angka

Sepanjang audit, "kunci tertahan" selalu berupa penalaran. Kini ada angkanya,
diambil Layer 1 (`PembacaKunciLangsung`) pada percobaan terkendali: satu sesi
penahan memegang `UPDLOCK` atas `N_HRIS_Pengumpulan_Data_Config` selama 7 detik,
satu sesi korban menabraknya pada detik ke-2.

| t | yang dilihat `sampel()` | tunggu |
|---|---|---|
| +1 dtk | rantai blokir hidup, `LCK_M_U` | **1.682 ms** |
| +2 dtk | rantai yang sama, masih diblokir | **3.761 ms** |
| akhir | korban lolos setelah penahan rollback | **5.103 ms** |

Artinya sederhana dan keras: **dengan RCSI mati, satu transaksi yang menahan
N detik membuat pembacanya menunggu ~N detik.** Setiap temuan "HTTP di dalam
transaksi" sepanjang audit ini sekarang punya satuan — bukan lagi "berpotensi
lambat", melainkan detik yang bisa dihitung.

Catatan perkakas: `sampel()` mengembalikan `rantai_blokir`; nama kunci di
dalamnya harus dibaca dari sumbernya, bukan ditebak (percobaan pertama mencetak
`sesi ?` dan `ctx=` kosong karena menebak `session_id`/`context_info`).

#### Tidak atomik: 31 → 13 → 1

Pemindai pertama melaporkan 31. **Dua bug pemindai** memangkasnya jadi 13:

1. **Regex peka kapital.** `JenisCutiController::store()` memakai
   `DB::BeginTransaction()` dengan B besar. PHP tak peduli kapital; regex saya
   peduli. Tertuduh padahal atomik.
2. **Cabang `switch` dihitung berurutan.**
   `IzinAtasanApprovalController::updateMainTransactionStatus()` menulis SATU
   tabel per panggilan lewat `switch`; pemindai membacanya sebagai empat.

Sisanya 13 dibaca satu per satu, dan hanya **satu** yang nyata:

| berkas | putusan |
|---|---|
| `AdminRoleManagerController::deleteRole()` | **NYATA** — 3 tulisan (2 soft-delete + 1 hard delete) tanpa transaksi, titik masuk |
| `TrainingExecutionService::syncCompletionState()` | aman — kedua pemanggil membungkus |
| `SopPaketVersionService::activatePendingItemVersions()` | aman — dibungkus **dua tingkat** di atas, di controller |
| `SopAbsensiService::checkin()` | aman **secara sengaja** — tulisan kedua adalah jejak audit; membungkusnya justru akan membatalkan presensi sah saat log gagal |
| `LeaderShiftController::batchApplyStatusHariDirect()` | **kode mati** — `private`, tak dipanggil dari mana pun termasuk tes |
| `createApprovalRequestLegacy()` | jalur LEGACY (rollback), tak aktif secara baku |

Pelajaran perkakas: pemeriksaan atomisitas **satu tingkat tidak cukup**.
Percobaan membuat penelusur pemanggil lintas berkas juga gagal — ia mencocokkan
**nama** saja, sehingga `$this->release($this->backoff)` milik Laravel Job
tercampur dengan `SopPaketVersionService::release()`, dan rantai yang
dihasilkannya fiksi. Tanpa resolusi tipe, kelas ini hanya bisa dituntaskan
dengan membaca.

#### WhatsApp di dalam transaksi: tuduhan yang DIBATALKAN

Empat situs di `karyawanTeamController` terbaca "WA sinkron di dalam
transaksi" — pola yang dulu menyebabkan *Login timeout expired*. Tuduhan itu
**salah**. `WhatsappLogHelper::sendAndLog()` sudah tidak mengirim sinkron; ia
melempar `SendWhatsappJob` ke antrean, dan koneksi `cloudtasks` memakai
`after_commit => true` (terverifikasi di `config/queue.php:45`, dan
`QUEUE_CONNECTION=cloudtasks`).

Yang tersisa adalah **jebakan laten, bukan cacat**: baku `config/queue.php`
adalah `env('QUEUE_CONNECTION', 'sync')`. Bila env itu hilang di suatu
lingkungan, `sync` menjalankan job **inline di dalam transaksi** — persis
defek yang sudah diperbaiki, hidup kembali tanpa satu baris kode pun berubah.

#### Non-sargable: 278 kandidat, dan kenapa angka itu tak berguna sendirian

278 kandidat statis tidak memberi tahu mana yang berarti — `whereDate` atas
tabel 200 baris tak merugikan siapa pun. Layer 2 (`PembacaKueriTerberat`,
`dm_exec_query_stats`) tahu bedanya. Sepuluh kueri termahal di server dev:

- **Tiga dari empat teratas bukan milik repo ini** (`N_HRIS_KANDIDAT_Sessions`,
  `HRIS_KANDIDAT_Ujian_Log_Soal` — aplikasi rekrutmen yang berbagi server).
  CPU server dev adalah sumber daya bersama; ini wajib disadari sebelum
  menuduh kode HCIS.
- Yang termahal **per eksekusi** milik kita: `LemburRequestService:648`,
  `CROSS APPLY N_HRIS_FN_GetStatusHariRange` — 1.970 ms CPU, 311.592 halaman
  sekali jalan.

**Hipotesis saya atas kueri itu terbantah oleh pengukurannya sendiri.** Dugaan:
`WHERE Kode_Karyawan IN (...)` tidak membatasi TVF, sehingga TVF jalan untuk
seluruh 965 karyawan. Uji langsung:

| karyawan | bentuk sekarang | bentuk "saring dulu" |
|---|---|---|
| 1 | 307 ms | 317 ms |
| 3 | 284 ms | 296 ms |
| 10 | 284 ms | 272 ms |
| 50 | 484 ms | 425 ms |

(bolak-balik kosong `SELECT 1` = 109 ms, sudah termasuk di atas)

Skalanya sub-linear dan tulis-ulang tak memberi apa pun. Biayanya sebanding
dengan **ukuran roster yang dikirim**, bukan bentuk kuerinya — komentar di
kode itu benar, dan micro-cache 60 detik yang sudah ada di sana adalah
peredam yang tepat. Tidak ada yang perlu diperbaiki.

#### Kembar: 34 blok, dua yang menakutkan ternyata disengaja

34 badan metode identik (≥6 baris berisi) lintas berkas. Dua di antaranya
generator nomor transaksi — jenis kode yang pernah nyaris mengirim nomor
dokumen kembar di fase sebelumnya. Keduanya **sudah terkoordinasi**:

- `AuditAdminTransaction::generateNoTransaksiWithLock()` ≡
  `AdminLogNumberService::generate()` — docblock menyatakan kesamaannya
  **sengaja**, agar keduanya saling-eksklusi pada `sp_getapplock` yang sama.
- `ApprovalFlowShiftController` ≡ `ApprovalFlowShiftService` — keduanya lewat
  `TransactionNumberService` dengan prefix yang sama, jadi counter pusat yang
  menjamin keunikan.

Yang tersisa sebagai duplikasi merugikan: `ensurePenjadwalanStructure()`,
**62 baris identik** di `LmsMateriController:2348` dan
`TrainingCatalogController:664`.

#### Injeksi SQL: 25 kandidat, nol celah

Semua interpolasi yang tersisa adalah komposisi sub-kueri (`{$q->toSql()}`
dengan `mergeBindings`), integer dari Carbon (`{$currentYear}`), atau nama
kolom literal dari kode (`bumpRollup($kolom)` — tujuh pemanggil, semua string
harfiah). Tidak ada masukan pengguna yang mencapai SQL mentah.

#### Yang tersisa untuk dikerjakan

| # | temuan | dasar |
|---|---|---|
| A | `deleteRole()` tanpa transaksi | dibaca & dipastikan |
| B | fallback `QUEUE_CONNECTION` = `sync` | jebakan laten, terverifikasi di config |
| C | `ensurePenjadwalanStructure()` kembar 62 baris | pemindai + dibaca |
| D | `LeaderShiftController` dua helper mati | terverifikasi nol pemanggil |

#### Kesehatan uji frontend — utang yang tak terlihat dari satu PR

Ditemukan saat memeriksa hasil merge, dan pantas dicatat karena mudah salah
dibaca sebagai "PR terakhir merusak CI".

**Frontend quality di master terakhir hijau 18 Agustus 2026.** Sejak itu setiap
run yang selesai berstatus `failure` (57 run selesai antara 16 Agu–6 Sep, 15
sukses, semuanya sebelum 19 Agu). Jadi merahnya berumur ±19 hari dan tidak
disebabkan PR mana pun yang baru di-merge.

Keadaan terukur: **20 berkas uji, 84 uji merah**, dalam tiga kelompok:

| kelompok | jumlah | bentuknya |
|---|---|---|
| ratchet design-system | 18 | warna hard-coded, `transition-property`, blur, hitungan komponen |
| selektor basi pasca-adopsi kit | ±29 | `Cannot call trigger on an empty DOMWrapper`, terpusat di komponen SOP |
| teks yang berubah | ±14 | `expected '…' to contain '…'` |

**Satu jebakan waktu yang sudah ditutup** (PR #318, tiga uji `SopVersiWizard`):
uji yang mount dengan `idVersi` lalu menyuntik keadaan dan hanya menunggu
`$nextTick()` akan gagal dua kali lipat — skeleton `memuatInisial` masih
terpasang sehingga badan komponen belum ada di DOM, DAN promise yang sudah
resolve menimpa suntikannya. Obatnya `await flushPromises()` setelah mount.
Polanya kemungkinan berulang di kelompok kedua; itu tempat pertama yang layak
diperiksa sebelum menyalahkan selektornya.

**Atribusi itu wajib diperiksa, bukan ditebak.** Ketiga uji tadi sempat terlihat
seperti akibat #309 semata-mata karena #309 menyentuh berkas `.vue` yang sama.
Diff-nya membantah: #309 hanya mengganti satu baris `pakaiPengesahan()`.
Biangnya `19b8fc39` (31 Agu), enam hari lebih dulu — ditemukan lewat
`git log -S "memuatInisial"`, bukan lewat menebak dari kedekatan berkas.

---

### Fase 13 — konversi implisit: temuan terbesar audit ini (6 Sep 2026)

Ditemukan dengan membalik arah pertanyaan: alih-alih memindai kode untuk
menebak apa yang mahal, **server ditanya apa yang ia rasakan kurang**.

#### Angkanya

`sys.dm_db_missing_index_*` menempatkan `N_HRIS_Notification` di puncak dengan
selisih enam kali lipat dari kandidat berikutnya — dampak 185.912, **21.144
seek**. Tapi saran indeksnya ganjil: **tanpa kolom kesamaan**, hanya rentang
pada `Expired_At`/`Sent_At`, dan `Kode_Karyawan` cuma "disertakan". Keganjilan
itulah petunjuknya.

Tabelnya sendiri kecil — 18.150 baris, 20 MB — dan sudah punya **tiga** indeks
non-clustered. Pemakaiannya:

| indeks | seek | scan | TULIS |
|---|---|---|---|
| `IDX_Notification_Unread` | **0** | 0 | 14.540 |
| `IX_Notification_Key_Kode` | **0** | 19.202 | 14.540 |
| `IX_Notification_Unread_Shell` | **1** | 32.706 | 14.541 |

Tiga indeks, ~43.600 operasi pemeliharaan tulis, dan **satu seek** sebagai
imbalannya. `IX_Notification_Unread_Shell` bahkan punya kunci yang persis cocok
dengan kuerinya — `(Kode_Karyawan, Is_Read, Is_Deleted, Channel, source,
Created_At)` — namun tetap di-SCAN. Indeks yang tepat sasaran tapi tak pernah
dipakai adalah gejala, bukan kebetulan.

#### Sebabnya

Seluruh kolom teks tabel itu **`varchar`/`char`** (non-Unicode). Driver PHP
mengikat parameter string sebagai **`nvarchar(4000)`**. Karena `nvarchar`
berpresedensi lebih tinggi, SQL Server meng-konversi **KOLOMNYA**, bukan
parameternya — dan kolom yang dikonversi tak bisa di-seek.

Diukur dengan logical reads, bukan waktu dinding (DB dev di luar mesin, ~150 ms
bolak-balik, akan menyembunyikan bedanya):

| bentuk kueri | baca/eksekusi | CPU/eksekusi |
|---|---|---|
| apa adanya (driver mengikat nvarchar) | **815** | **20,57 ms** |
| parameternya di-`CAST(? AS varchar(50))` | **37** | **2,87 ms** |

22× lebih sedikit halaman dibaca, 7× lebih sedikit CPU — hasil identik.

> **KOREKSI (6 Sep 2026, sesudah dicoba diterapkan).** Tabel di atas benar
> sebagai demonstrasi mekanismenya, tetapi ia diukur pada kueri yang **saya
> tulis sendiri** untuk menguji hipotesis — `SELECT TOP 15 Id_Notification,
> Title, Created_At …` — BUKAN pada kueri yang benar-benar dijalankan
> `NotificationService`. Menyebutnya "kueri polling notifikasi" adalah
> pelabelan yang menyesatkan, dan itu kekeliruan saya.
>
> Saat `CAST` benar-benar dipasang di `activeQuery()` lalu diukur lewat jalur
> service yang sesungguhnya, hasilnya: **2.863 halaman/eksekusi sebelum dan
> sesudah — tak ada perbaikan sama sekali.** Perubahannya dibatalkan; tidak
> ada kode yang dikirim atas dasar angka yang tak terbukti di jalur nyata.
>
> Kueri aslinya berbeda dari rekonstruksi saya dalam tiga hal: ia `SELECT *`,
> ia membawa penyaring `Channel`, dan ia TIDAK menyaring `Is_Read` — padahal
> `Is_Read` adalah kunci ke-2 `IX_Notification_Unread_Shell`, sehingga ada
> celah kunci yang membatalkan seek sekaligus pemakaian `Created_At` untuk
> pengurutan. Hipotesis kedua (`SELECT *` yang memicu lookup) **juga gugur**:
> membatasi kolom memberi 303 halaman, sama persis dengan `SELECT *`.
>
> Yang masih berdiri dari Fase 13: mekanisme konversi implisitnya nyata, dan
> luasnya (69% rencana, 2.005.833 pemakaian) diukur langsung dari DMV — tidak
> terpengaruh koreksi ini. Yang GUGUR: klaim bahwa jalur polling notifikasi
> akan membaik 22× bila parameternya di-CAST. Jalur itu mahal karena sebab
> lain yang **belum** saya temukan.

#### Luasnya

Dibatasi ke basis data ini saja (server dev berbagi dengan aplikasi rekrutmen,
jadi angka se-server akan menyesatkan):

> **7.066 dari 10.172 rencana ter-cache — 69% — memuat `CONVERT_IMPLICIT`,
> dan sudah dipakai 2.005.833 kali.**

Sebaran tipe kolomnya menjelaskan kenapa: **varchar 3.243 + char 869 = 4.112
kolom non-Unicode** di ratusan tabel, berbanding **nvarchar 749**. Penyimpanan
mayoritas non-Unicode, pengikatan seluruhnya Unicode.

Ini menjelaskan gejala yang selama ini dianggap terpisah: indeks yang "sudah
ada tapi tak membantu", kueri sederhana yang mahal, dan CPU dev yang tinggi
tanpa satu kueri pun terlihat salah.

#### Kenapa BELUM diperbaiki — dan kenapa itu keputusan, bukan kelalaian

Perbaikan satu baris memang ada: menyetel pengikatan koneksi ke ANSI
(`SQLSRV_ENCODING_SYSTEM`) membuat string diikat sebagai `varchar`. Ia menyentuh
**setiap kueri di seluruh aplikasi sekaligus** — dan di situlah bahayanya:

1. **749 kolom `nvarchar` akan menerima data yang di-transcode.** Isi non-Latin1
   (emoji, tanda kutip melengkung hasil salin-tempel dari Word, aksara non-Latin)
   rusak **diam-diam** — tanpa galat, tanpa jejak. Termasuk tabel `cache`
   milik Laravel sendiri, yang menyimpan nilai terserialisasi.
2. **Kerusakannya tak bisa dibedakan dari data yang memang begitu.** Berbeda
   dari kueri lambat, ia tak muncul di DMV mana pun.

Alternatif yang lebih sempit, sesuai urutan risikonya:

| pilihan | untung | risiko |
|---|---|---|
| `CAST(? AS varchar(n))` pada jalur terpanas saja | terukur, terbatas | manual, mudah terlewat di kode baru |
| Ubah kolom kunci pencarian jadi `nvarchar` | permanen & benar | DDL besar, perlu jendela rilis |
| Pengikatan ANSI di tingkat koneksi | menyapu 69% rencana sekaligus | 749 kolom nvarchar; kerusakan senyap |

**Rekomendasi**: mulai dari baris pertama tabel di atas pada jalur yang sudah
terukur (polling notifikasi paling dulu — ia berjalan tiap 60 detik untuk
SETIAP pengguna), lalu ukur ulang. Baris ketiga jangan diambil tanpa audit
kolom `nvarchar` satu per satu.

#### Yang juga terlihat, dan berdiri sendiri

`IDX_Notification_Unread` — **nol seek, nol scan, nol lookup, 14.540 tulis**.
Indeks yang tak pernah dibaca sekali pun sejak statistiknya dimulai, tetapi
dipelihara pada setiap penulisan. Ini murni biaya, dan menghapusnya tak
menuntut keputusan sebesar di atas.

---

### Fase 14 — indeks yang dibayar tapi tak pernah dipakai (6 Sep 2026)

Lanjutan langsung Fase 13, arah pertanyaan yang sama: server ditanya, bukan
kode ditebak. Kalau Fase 13 menemukan indeks yang **tepat sasaran tapi tak bisa
dipakai**, fase ini menemukan indeks yang **tak dibutuhkan siapa pun tapi tetap
dibayar**.

Setiap indeks non-clustered mempercepat baca dan memperlambat SETIAP
insert/update/delete pada tabelnya — menambah waktu tulis, memperpanjang kunci
yang ditahan, memakan ruang.

#### Jendela pengamatannya

`sys.dm_db_index_usage_stats` terkumpul sejak **18 Agu 2026, 471 jam (±20
hari)**. Ini penting: angkanya hilang saat SQL Server restart, jadi "nol" hanya
bermakna bila jendelanya cukup panjang. Dua puluh hari cukup.

#### Angkanya

> **84 indeks berstatus nol-baca namun ditulis** (di luar 66 milik aplikasi lain
> yang berbagi server). Total **225.370 pemeliharaan tulis sia-sia**.
> **43 di antaranya UNIK** — menegakkan aturan, bukan kecepatan, jadi TETAP
> DIPERLUKAN. Sisa **41** adalah kandidat hapus.

#### Tiga yang paling mahal, dibaca utuh

**`N_HRIS_Activity_Event`** — 32.421 baris, dan inilah tabel yang ditulis pada
hampir setiap tindakan pengguna, sehingga beban tulisnya terasa di setiap
permintaan.

| indeks | seek | scan | tulis |
|---|---|---|---|
| `CIX_NHRIS_AE_Created` (clustered) | 236 | 0 | 18.245 |
| `IX_NHRIS_AE_GrupFitur` | 4 | 19 | 18.245 |
| `IX_NHRIS_AE_Kode_Created` | 0 | 2 | 18.245 |
| `IX_NHRIS_AE_UserID_Created` | **0** | **0** | 18.245 |
| `IX_NHRIS_AE_Session` | **0** | **0** | 18.245 |
| `IX_NHRIS_AE_Menu` | **0** | **0** | 18.245 |
| `IX_NHRIS_AE_Security` | **0** | **0** | 18.245 |

Empat indeks non-unik tanpa satu pun pembacaan dalam 20 hari, masing-masing
dipelihara 18.245 kali: **72.980 operasi tulis** yang tak pernah menolong
siapa pun.

**`N_SOP_Penjadwalan_Detail`** — **317 baris**, tetapi lima indeks non-clustered
dan 34.548 penulisan pada clustered-nya. `IX_N_SOP_PjdD_Rule`: 24.980 tulis,
nol baca. Pada tabel sekecil ini sebagian indeks memang tak masuk akal secara
prinsip — memindai 317 baris lebih murah daripada menelusuri indeks.

**`sessions`** — 73 baris. `idx_sessions_user_id`: **nol baca, 21.176 tulis**.
Laravel menulis baris sesi pada hampir setiap permintaan, jadi indeks ini
dibayar setiap permintaan dan tak pernah dibaca sekali pun.

#### Yang HARUS dipertimbangkan sebelum menghapus

1. **Jendela ini dev, bukan produksi.** Indeks yang mati di sini bisa hidup di
   sana — `IX_NHRIS_AE_*` melayani halaman Aktivitas, yang mungkin jarang
   dibuka di dev tetapi rutin dipakai admin di produksi. Statistik produksi
   wajib diperiksa dengan kueri yang sama sebelum DDL apa pun dijalankan.
2. **Indeks UNIK menegakkan aturan.** Ke-43 itu tak boleh disentuh walau nol
   pemakaian; menghapusnya membuka pintu data ganda.
3. **Laporan berkala.** Indeks yang hanya dipakai laporan bulanan bisa terlihat
   nol pada jendela 20 hari.

#### Tidak ada DDL yang dibuat di sini, dan itu disengaja

Proyek ini punya alur `database/ddl/<change-id>/` dengan status
`DRAFT → REVIEW_REQUIRED → APPROVED → …`. Penghapusan indeks adalah DDL, jadi
ia masuk lewat pintu itu — bukan lewat dokumen audit. Yang disediakan fase ini
adalah **bukti dan daftar kandidatnya**, supaya perubahan DDL-nya kelak
diajukan dengan angka, bukan dengan dugaan.

Urutan yang disarankan bila hendak dilanjutkan: ambil dulu **tiga tabel di
atas** — bukan keempat puluh satu kandidat sekaligus — sebab ketiganya
ditulis paling sering sehingga untungnya paling terasa dan cakupannya paling
mudah diverifikasi ulang sesudahnya.

---

### Fase 15 — server ini menunggu apa? (6 Sep 2026)

Pertanyaan yang seharusnya diajukan paling awal, dan baru diajukan sekarang.
Ia menentukan urutan segalanya: kalau waktu habis menunggu **jaringan**,
memperbaiki kueri hampir tak terasa; kalau habis menunggu **kunci**, transaksi
panjang yang jadi prioritas; kalau habis menunggu **CPU**, bentuk kueri yang
menentukan.

`sys.dm_os_wait_stats`, jendela **471 jam** (sejak 18 Agu), server **2 CPU**.

#### Perkakasnya berbohong dulu — jalan pertama harus dibuang

Jalan pertama menempatkan `DISPATCHER_QUEUE_SEMAPHORE` (61%),
`CHECKPOINT_QUEUE` (18%) dan `PWAIT_EXTENSIBILITY_CLEANUP_TASK` (18%) di
puncak. Ketiganya wait **latar/menganggur** — thread yang menunggu pekerjaan,
bukan kueri yang menunggu sumber daya. Daftar abaikan yang saya pakai (dan
sebut "baku") ternyata tak memuatnya, dan akibatnya sinyal sebenarnya terkubur
di bawah 2%. Angka pertama itu dibuang seluruhnya; yang di bawah ini hasil
jalan kedua.

Pola yang sama dengan dua pemindai statis sebelumnya: **perkakas yang belum
pernah dilihat salah belum bisa dipercaya.**

#### Hasilnya

| jenis tunggu | bagian | jumlah | rata/tunggu | artinya |
|---|---|---|---|---|
| `ASYNC_NETWORK_IO` | **33,5%** | 3.031.321 | 12,3 ms | server SUDAH selesai, menunggu klien menyerap hasil |
| `SOS_SCHEDULER_YIELD` | **28,3%** | 6.241.435 | 5,0 ms | antre CPU — dan server ini hanya punya **2 CPU** |
| `LCK_M_S` | **11,2%** | 8.096 | **1.527 ms** | pembaca antre di belakang penulis (RCSI mati) |
| `PAGELATCH_EX` | 8,8% | 870.102 | 11,2 ms | rebutan halaman di memori — khas hotspot insert |
| `WRITELOG` | 2,6% | 1.403.064 | 2,1 ms | banyak transaksi kecil |
| `LCK_M_U` | 0,9% | 2.233 | 447 ms | kunci update |

Total tunggu tercatat: **110.721 detik** dalam 471 jam.

#### Apa yang angka ini ubah

**1. Jaringan adalah biaya terbesar, dan itu membenarkan arah audit selama
ini.** `ASYNC_NETWORK_IO` bukan tanda server lambat — ia tanda server selesai
lebih dulu lalu menunggu klien. Dengan DB dev di luar mesin, yang menolong
bukan "kueri lebih cepat" melainkan **lebih sedikit perjalanan dan lebih
sedikit baris**. Seluruh kerja N+1 sepanjang audit ini menyerang tepat di situ.

**2. CPU adalah biaya kedua, di server 2 inti.** Di sinilah temuan Fase 13
membayar langsung: `CONVERT_IMPLICIT` menaikkan CPU satu kueri polling dari
2,87 ms menjadi 20,57 ms. Menghilangkannya mengurangi antrean CPU, bukan
sekadar mempercepat satu halaman.

**3. Kunci: jarang, tetapi paling menyakitkan per kejadian.** `LCK_M_S` hanya
8.096 kali — 0,3% dari jumlah `ASYNC_NETWORK_IO` — tetapi rata-ratanya
**1.527 ms**. Totalnya ±3,4 jam pembaca terblokir dalam 20 hari. Angka ini
sejalan dengan percobaan terkendali Fase 12 (penahan 7 detik → korban menunggu
5.103 ms), dan ia menjelaskan kenapa keluhan "kadang halaman diam lama" tak
pernah cocok dengan rata-rata: yang terjadi bukan lambat merata, melainkan
sebagian kecil permintaan menunggu sangat lama.

**4. `PAGELATCH_EX` 870.102 kali** menunjuk rebutan halaman terakhir pada tabel
ber-kunci-urut (identity). Belum ditelusuri; dicatat sebagai kandidat fase
berikutnya.

#### Urutan pengungkit, berdasarkan bukti dan bukan selera

1. **Kurangi perjalanan & ukuran hasil** — menyerang 33,5%. Sudah berjalan
   sepanjang audit (N+1, set-based, paginasi).
2. **Kurangi CPU per kueri** — menyerang 28,3%. Temuan Fase 13 langsung di
   sini, dan ia yang untungnya paling terukur (22× halaman dibaca).
3. **Perpendek transaksi** — menyerang 11,2% yang paling menyakitkan per
   kejadian. Pola outbox yang sudah dipasang menyerang tepat ini.
4. **Kurangi beban tulis sia-sia** — Fase 14 (84 indeks nol-baca) menyentuh
   `WRITELOG` dan `PAGELATCH_EX` sekaligus.

Keempatnya sudah punya pekerjaan yang sedang berjalan atau kandidat konkret.
Yang belum: **memindahkan basis data dev ke mesin yang sama** — itu menyerang
pengungkit nomor satu lebih besar daripada optimasi kode mana pun, tetapi ia
keputusan infrastruktur, bukan kode.

---

### Fase 16 — cache rencana lebih besar daripada cache data (6 Sep 2026)

Lanjutan langsung dari pengungkit nomor satu. Setelah kueri yang mengirim
paling banyak baris **total** ditangani (PR akses halaman), giliran yang sekali
jalan mengirim paling banyak.

#### Yang terlihat pertama

| kueri | baris/eksekusi | eksekusi |
|---|---|---|
| `KPI_Pengajuan_Det` ⨝ `KPI_Pengajuan_Detail` | **43.327** | 2–4 |
| `SELECT * FROM KPI_Pengajuan_Det WHERE Id IN (@P1…@Pn)` | **23.983** | 9 |
| `N_PA_Assignment_Score WHERE … IN (@P1…@Pn)` | 26.752 | 3 |

Dua hal sekaligus: `SELECT *` atas puluhan ribu baris, dan daftar `IN` yang
panjangnya berubah-ubah. Yang kedua ternyata jauh lebih mahal daripada
kelihatannya.

#### Sebab yang tak terlihat dari kode

Setiap panjang daftar `IN` yang berbeda menghasilkan teks kueri berbeda,
sehingga **rencana baru dikompilasi dan disimpan** untuk masing-masing:

| tabel | rencana ter-cache | ukuran |
|---|---|---|
| `KPI_Pengajuan_Det` | **122** | 49 MB |
| `KPI_Pengajuan_Detail` | 89 | 36 MB |
| `N_PA_Assignment_Score` | 31 | 11 MB |

Satu kueri, 122 rencana. Kompilasi itu sendiri memakan CPU — dan CPU adalah
biaya kedua terbesar di server ini (28,3%, Fase 15), pada mesin **2 inti**.

#### Angka yang paling mengejutkan

| pemakai memori | ukuran |
|---|---|
| `CACHESTORE_SQLCP` (cache rencana) | **1.878 MB** |
| `MEMORYCLERK_SQLBUFFERPOOL` (cache DATA) | **1.408 MB** |

**Cache rencananya lebih besar daripada cache datanya.** Server menyimpan lebih
banyak "cara mengambil data" daripada datanya sendiri.

#### Tetapi — dan ini menahan saya menyebutnya mendesak

**Page Life Expectancy: 370.325 detik (±4,3 hari).** Artinya halaman data
praktis tak pernah tergusur dari memori: 1.408 MB itu MEMANG CUKUP untuk basis
data sedang ini. Jadi cache rencana yang membengkak **belum merugikan siapa pun
hari ini** — ia boros, bukan berbahaya.

Yang berubah di produksi: dengan data jauh lebih besar, 1.878 MB yang dipakai
menyimpan 9.694 rencana akan langsung merampas buffer pool, dan PLE akan jatuh.
Di sanalah ia berubah dari boros menjadi merugikan.

Menyebutnya "kritis" berdasarkan angka dev akan keliru; mengabaikannya karena
dev baik-baik saja juga keliru.

#### Temuan sampingan: batas memori SQL Server tak disetel

`max server memory` bernilai **2.147.483.647 MB** — nilai baku yang berarti
"tanpa batas", pada mesin ber-RAM **10.236 MB**. SQL Server akan mengambil
sebanyak yang ia bisa dan menekan sistem operasi beserta proses lain di mesin
yang sama. Ini konfigurasi infrastruktur, bukan kode, dan berlaku terlepas dari
temuan mana pun di atas.

#### Arah perbaikan, dan kenapa belum dikerjakan

Situs `whereIn` berukuran bebas tersebar di banyak controller KPI — ini pola
modul, bukan satu-dua tempat. Memperbaikinya satu per satu akan menyebarkan
bentuk baru tanpa jaminan konsisten. Yang benar adalah satu pembantu bersama,
lalu memigrasikan pemanggilnya:

| pilihan | untung | risiko |
|---|---|---|
| Potong `whereIn` ke ukuran TETAP (mis. kelipatan 500) | sederhana, 122 rencana → 2–3 | perlu penyeragaman di semua pemanggil |
| `STRING_SPLIT(?, ',')` — satu parameter string | 122 rencana → **1** | mengubah bentuk kueri; perlu SQL Server 2016+ |
| Tabel sementara / TVP | paling bersih untuk daftar besar | paling banyak berubah |

Belum dikerjakan karena menyentuh jalur dasbor KPI yang tak punya penjaga
setara, dan karena batas 2.100 parameter SQL Server sudah pernah menggigit
proyek ini — perubahan di sini wajib dibarengi penjaga, bukan disisipkan
diam-diam.

---

### Fase 17 — kenapa polling notifikasi mahal, dan tiga tebakan yang salah (6 Sep 2026)

Fase ini menutup pertanyaan yang ditinggalkan menggantung oleh koreksi Fase 13.
Ia juga catatan tentang **cara gagal**: tiga hipotesis berturut-turut meleset,
dan dua di antaranya meleset karena kesalahan pengukuran saya sendiri.

#### Jawabannya, dibaca dari rencana kueri — bukan ditebak

| operator | perkiraan baris | biaya |
|---|---|---|
| `Sort` (TopN) | 15 | 2,2193 |
| **`Clustered Index Scan`** | 890 | 2,1361 |

Indeks yang disebut rencana: **hanya primary key**. Keempat indeks non-clustered
pada tabel itu **tidak dipakai sama sekali**.

Sebabnya sederhana begitu terlihat: kueri daftar memakai `SELECT *`, dan tak
satu pun indeks mencakup seluruh kolomnya. Dengan ±890 baris memenuhi syarat
dari 18.150, optimizer menilai memindai seluruh clustered index lebih murah
daripada seek lalu 890 key lookup. Pilihan itu rasional — indeksnyalah yang
tidak cocok.

**2.863 halaman dibaca per polling, tiap 60 detik, untuk SETIAP pengguna.**

#### Tiga hipotesis yang gugur, dan kenapa

**1. Konversi implisit (Fase 13).** Dipasang `CAST(? AS varchar(50))` di
`activeQuery()`, diukur lewat jalur service: **2.863 sebelum, 2.863 sesudah**.
Nol perbaikan. Perubahannya dibatalkan dan Fase 13 sudah dikoreksi.

**2. `SELECT *` memicu key lookup.** Diuji, tak ada bedanya — lalu ternyata uji
itu sendiri cacat: ia dijalankan pada `Channel = 'MAIN_NOTIF'` yang **nol baris**
untuk karyawan uji, sehingga tak ada yang perlu di-lookup dan perbedaan apa pun
mustahil muncul. Diulang pada `IN_APP` (774 baris):

| bentuk | halaman |
|---|---|
| `SELECT *` | 2.863 |
| 9 kolom yang dipakai layar | **2.863** |
| `SELECT Id_Notification` saja | 303 |

Jadi bukan JUMLAH kolomnya, melainkan apakah kolomnya **tercakup indeks**.
Hanya kunci yang tercakup.

**3. Celah kunci pada `Is_Read` memaksa Sort.** `IX_Notification_Unread_Shell`
berkunci `(Kode_Karyawan, Is_Read, Is_Deleted, Channel, source, Created_At)` dan
kueri tak menyaring `Is_Read`. Terdengar meyakinkan; salah. Menutup celahnya
memberi **2.863 juga**, dan tak ada Sort di kedua rencana. "Sort" yang sempat
saya lihat berasal dari varian channel-kosong, ditangkap oleh pencocokan kata
yang terlalu kasar.

> Pelajarannya sama seperti yang sudah tercatat dua kali di dokumen ini:
> **pengukuran pada premis yang kosong akan selalu hijau.** Sebelum
> membandingkan dua bentuk, pastikan bentuk yang lama memang MENGERJAKAN
> sesuatu.

#### Obat tanpa DDL: diukur, lalu DITOLAK

Ambil kuncinya dulu (tercakup indeks), baru 15 baris penuh lewat primary key:

| | halaman | CPU |
|---|---|---|
| sekarang (1 perjalanan, pindai penuh) | 2.863 | 25,25 ms |
| dua langkah (2 perjalanan, seek) | **364** | 11,15 ms |

**7,9× lebih sedikit halaman.** Tetapi waktu dindingnya:

| | rata-rata 8 ulangan |
|---|---|
| sekarang | **143 ms** |
| dua langkah | **239 ms** |

**97 ms LEBIH LAMBAT.** Satu perjalanan tambahan lebih mahal daripada 2.500
halaman yang dihemat — persis yang diramalkan Fase 15 saat menempatkan
`ASYNC_NETWORK_IO` (33,5%) di atas segalanya. Perubahannya tidak dikirim.

Catatan: angka ini milik DB dev yang ada **di luar mesin**. Di produksi, dengan
aplikasi dan basis data berdampingan, perhitungan yang sama bisa berbalik —
tetapi itu harus diukur di sana, bukan diasumsikan.

#### Obat yang benar, dan kenapa belum dikerjakan

Satu **indeks yang mencakup** untuk bentuk kueri ini:
`(Kode_Karyawan, Is_Deleted, Channel, Created_At)` `INCLUDE` kolom yang dipakai
layar. Ia menghapus pemindaian penuh tanpa menambah perjalanan.

Yang membuatnya menarik: tabel ini sudah punya **empat** indeks yang bersama-sama
menghasilkan **satu** seek ditukar ±43.600 operasi tulis (Fase 14). Indeks baru
itu bisa **menggantikan** sebagian di antaranya — jadi bebannya berkurang, bukan
bertambah.

Ia DDL, dan `DROP INDEX` tak boleh dieksekusi agen. Ia juga akan mengantre di
belakang **80 perubahan DDL yang belum COMPLETED**.

---

### Fase 18 — peta rute tulis: 472 rute tanpa cakupan, diperingkat (6 Sep 2026)

Lubang terbesar yang tersisa dari seluruh audit ini, dan satu-satunya yang tak
bisa ditutup perkakas: **rute yang MENGUBAH data**.

| | |
|---|---|
| total rute | 1.235 |
| rute GET (sudah tersapu) | 763 |
| **rute TULIS (nol cakupan)** | **472** |

Sebarannya: ESS 148, LMS 134, KPI 75, SYSTEM 74, VISITOR 23, lain 18.

Mereka tak boleh disapu otomatis di dev bersama — satu sapuan akan membuat
pengajuan, persetujuan, dan penghapusan sungguhan. Jadi cakupannya hanya bisa
datang dari pemeriksaan manual, dan 472 terlalu banyak untuk diperiksa semua.
Yang menentukan **urutannya**.

#### Sinyal yang dipakai, dan alasan bobotnya

| sinyal | bobot | alasan |
|---|---|---|
| panggilan luar DI DALAM transaksi | 40 | terukur di audit ini: RCSI mati, penahan 7 dtk → korban menunggu 5.103 ms — **nol rute kena** |
| DELETE permanen | 25 | gagal separuh jalan tak bisa dikembalikan |
| ≥2 tabel tanpa transaksi | 20 | separuh pekerjaan tersimpan diam-diam (Fase 12) |
| menyentuh tabel berdampak tinggi | 15 | absensi, cuti, lembur, KPI, approval |
| kueri dalam perulangan | 10 | perjalanan = biaya terbesar (33,5%, Fase 15) |

#### Hasilnya

**204 dari 472** rute membawa setidaknya satu sinyal. Yang teratas:

| skor | rute | sinyal |
|---|---|---|
| 70 | `POST lms/konten-kategori/delete-konten` | DELETE · ≥2 tabel tanpa transaksi · tabel penting · N+1 |
| 70 | `POST sop/admin/konten/delete-konten` | idem |
| 50 | `PUT api/v1/admin/role-manager/roles/{id}` | DELETE · tabel penting · N+1 |
| 50 | `POST api/v1/approval-kpi-admin/save` | idem |
| 50 | `POST api/v1/lms/penjadwalan/update` | idem |
| 50 | `POST lms/admin/training/store` | idem |

**Tak satu pun rute membawa sinyal "panggilan luar di dalam transaksi".** Itu
kabar baik dan layak disebut: pola yang paling mahal di lingkungan ini — dan
yang jadi biang beberapa temuan awal audit — sudah bersih dari seluruh 472 rute
tulis.

#### Pemeringkatnya berbohong TIGA kali sebelum benar

Dicatat lengkap karena ketiganya terjadi TEPAT DI PERINGKAT TERATAS — tempat
kekeliruan paling mahal — dan karena yang ketiga sempat SAYA TERBITKAN sebagai
"sudah diperiksa tangan":

1. **`sendAndLog` dan `::dispatch(` ikut dihitung sebagai panggilan luar.**
   Keduanya sudah aman sejak ketiga job WhatsApp memasang `afterCommit()` di
   konstruktor. Dua rute teratas saat itu — `simpanUser` dan approve cuti —
   tuduhan palsu.

2. **Urutan tidak diperiksa.** Versi berikutnya hanya menanyakan "ada transaksi
   DAN ada panggilan luar di metode yang sama".
   `ContentManagementController::deleteKategori` terbaca 90 padahal commit-nya
   di offset 1686 sementara `Storage` baru di 2655 — sesudah selesai.

3. **Batas transaksi bentuk closure dihitung sebagai "sampai akhir metode".**
   `DB::transaction(fn ...)` tak punya `DB::commit`, jadi cadangan itu membuat
   SELURUH blok `catch` — tempat berkas dibersihkan SETELAH rollback — terhitung
   di dalam transaksi.

   Inilah yang membuat `AdminTrainingController::store` dan `update` terbaca
   **90**, dan saya sempat menuliskannya di sini sebagai temuan yang "diperiksa
   tangan". **Itu salah.** Dibaca utuh, keduanya justru terstruktur BENAR:
   thumbnail diunggah lewat `prepareThumbnailForStorage()` SEBELUM transaksi
   dibuka, dan `Storage::disk` hanya muncul di blok `catch` (store) atau
   sesudah closure ditutup di baris 743 (update). Sesudah batas closure dihitung
   dari kurungnya, skor keduanya turun ke 50 — dan turun karena sinyal lain,
   bukan karena ini.

#### Batasnya

Pembacaan TEKS atas badan metode, bukan penelusuran tipe. Metode yang memanggil
pembantu bertransaksi tetap akan terbaca "tanpa transaksi" — tuduhan palsu yang
sudah tiga kali muncul di audit ini. Keluarannya **urutan pemeriksaan**, bukan
daftar temuan.

---

### Fase 19 — dua penyempitan, dan sebuah uji yang lulus tanpa menguji (7 Sep 2026)

Fase ini kecil dan sengaja begitu: keduanya bukan temuan baru, melainkan
**pembatalan dua klaim saya sendiri** dari fase-fase sebelumnya. Dicatat karena
pola kekeliruannya berulang, bukan karena perbaikannya besar.

#### 1. `KartuTugasExport.vue` — tuduhan "korban sampingan" yang keliru

Saya sempat menyimpulkan berkas ini hilang tak sengaja lewat sebuah commit
checkpoint dan perlu dikembalikan. Pemeriksaan sampai tuntas menunjukkan
sebaliknya: alur export Report Studio **dirancang ulang** menjadi modal
tanda-terima (`rincianReceiptExport` / `faseExport` di `ReportStudio.vue`), dan
modal itu menampilkan lebih banyak daripada kartu lama — Task ID, status
antrean, aksi lanjutan. Rujukan ke komponennya: **nol**.

Mengembalikannya bukan memulihkan fungsi, melainkan menghidupkan permukaan
kedua untuk hal yang sama. Berkasnya dihapus.

Cara kekeliruan ini terjadi patut dicatat: saya membaca **riwayat git** dan
menyimpulkan maksud dari bentuk commit-nya, bukan membaca **kode penggantinya**.
Riwayat memberi tahu apa yang berubah; ia tidak memberi tahu apakah ada yang
hilang.

#### 2. `tautanMaster()` — penjaga yang tak pernah bisa salah

```js
// sebelum — cadangan truthy membuat v-if di bawahnya jadi kode mati
return this.idDokumen ? `/sop/admin/dokumen?id=${...}` : '/sop/admin/dokumen';

// sesudah
return this.idDokumen ? `/sop/admin/dokumen?id=${...}` : null;
```

`v-if="tautanMaster"` **tidak pernah bisa bernilai salah**, jadi item yang belum
bertaut dokumen tetap menawarkan tautan — yang mendarat di daftar kosong.

#### Yang paling perlu dibaca: ujinya lulus bertahun-tahun tanpa memeriksa apa pun

Penjaga lamanya bernama *"tautan Master tetap ditawarkan walau item belum
bertaut dokumen"*. Ia **hijau** sepanjang usianya — bukan karena perilakunya
benar, melainkan karena **selektornya menunjuk tempat yang keliru**. Uji itu
menegaskan sesuatu yang tak pernah ia periksa.

Penggantinya diperlakukan sesuai aturan yang berlaku di audit ini: **setiap
penjaga harus pernah dilihat MERAH**, dan **sabotasenya harus diverifikasi
mendarat** — bukan diasumsikan mendarat. Pada percobaan pertama, pola `perl`
untuk memasang kembali cadangan lama **tidak cocok sama sekali**; diff
memperlihatkan berkasnya tak berubah. Kalau langkah verifikasi itu dilewati,
uji akan tampak "tetap hijau setelah disabotase" dan saya akan menyimpulkan
penjaganya lemah — padahal yang gagal adalah sabotasenya. Ini kali ketiga pola
yang sama muncul di audit ini.

Setelah sabotasenya benar-benar mendarat: `expected true to be false`. Dipulihkan
→ 17/17 hijau.

#### Keadaan suite frontend

`1.550 lulus / 17 gagal`. Ketujuh belas kegagalan seluruhnya **utang
design-system yang sudah ada sebelumnya** — `designMdTokens.test.js`,
`designSystemGuards.test.js`, `HcisKomponenAnak.test.js` — dan menunggu satu
keputusan: dilunasi, atau ambang ratchet-nya di-baseline ulang ke keadaan
sekarang. Keduanya sah; yang tidak sah adalah membiarkannya merah, karena suite
yang merah secara rutin membuat kegagalan **baru** tak terlihat.

---

### Fase 20 — perjalanan, bukan pekerjaan (8 Sep 2026)

Fase ini menemukan satu hal yang mengubah cara membaca seluruh audit
sebelumnya, dan lima perbaikan yang mengikuti darinya.

#### Temuan yang mengubah kerangkanya

| | |
|---|---|
| satu bolak-balik kosong (`SELECT 1`, p50 dari 30 percobaan) | **54 ms** |
| eksekusi kueri cuti di sisi server | **0,4 – 37 ms** |

**99% biaya adalah perjalanan, bukan pekerjaan.** Konsekuensinya untuk arah
optimasi tegas: selama jumlah perjalanan tidak dipangkas, mengoptimalkan kueri
satu per satu tidak mengubah apa pun. Lima perbaikan di bawah semuanya
memangkas perjalanan, bukan mempercepat kueri.

#### Yang diperbaiki, dengan angkanya

| PR | apa | terukur |
|---|---|---|
| #341 | `cuti/store` menulis tanggal satu per satu **di dalam transaksi** | 2,3 perjalanan → 1; jendela kunci diperpendek |
| #342 | Activity Center memukul satu tabel 4× | **7 kueri → 4**, 440 ms → 270 ms, keluaran identik byte-per-byte |
| #344 | `Schema::hasTable()` tak pernah dimemo | **36 kueri → 4**, 20.600 ms → 2.693 ms; 49 titik dikonversi, 41 → 12 |
| #345 | kunci anti double-dispatch bukan kunci | `has()+put()` → `add()` yang atomik |
| #346 | hak akses halaman massal: perulangan **bersarang** | **240 kueri → 2**, 47.458 ms → 715 ms |

#### #344 layak dibaca ulang: yang mahal justru yang tampak sepele

`Schema::hasTable()` di SQL Server BUKAN lookup ringan. Laravel
menerjemahkannya menjadi kueri yang mengagregasi **ukuran seluruh tabel** di
basis data. `dm_exec_query_stats` mencatat **46.285 halaman dibaca dan 99 ms
CPU untuk satu panggilan, 4.457 eksekusi, 442 detik CPU** — pemakan CPU
terbesar dari sisi aplikasi ini, untuk pertanyaan yang jawabannya tak pernah
berubah.

Masalahnya sudah dikenali dan ditambal **tiga kali secara lokal**, sementara
belasan pemanggil lain dibiarkan — bentuk "aturan hanya ada di sebagian jalur"
yang berulang di repo ini.

#### #346: yang berbahaya bukan lamanya, melainkan apa yang dikunci

`N × (1 + 2M)` kueri dalam **satu transaksi** — pada 958 karyawan aktif itu
6.706 kueri untuk tiga halaman, 20.118 untuk sepuluh. `HRIS_Page_Access`
dibaca hampir setiap permintaan (11.642 eksekusi tercatat), dan RCSI mati:
selama transaksi berjalan, pengguna lain menunggu hanya untuk tahu halaman apa
yang boleh ia buka.

#### Hasil NEGATIF, yang sama pentingnya

Empat kelas dugaan ditutup dengan pengukuran, bukan dengan perasaan:

- **Non-sargable: bersih.** 368 kecocokan teks runtuh jadi **satu** kandidat
  setelah disilangkan ukuran tabel nyata, dan yang satu itu terukur sehat
  (111 ms, 1.746 halaman) karena penyaring `Kode_Karyawan` sudah menyempitkan
  lebih dulu.
- **Indeks yang hilang: tak ada yang layak.** Usulan teratas (skor 223.242)
  berdiri di atas kueri yang membaca 4–22 halaman; sisanya menyasar `Karyawan`
  yang hanya **965 baris**.
- **`/homepage` dan `/api/v1/sop/progress/halaman` sudah ramping** — 4 dan 3
  kueri. Tidak ada yang pantas "diperbaiki" di sana.
- **`leader-shift/history` bukan N+1**: 9 kueri untuk 10 item, 9 juga untuk 49.
  Tidak tumbuh mengikuti data.

Lalu satu koreksi atas angka sendiri: badge pending approval **bukan 688 ms
melainkan 173 ms**. Angka 688 adalah eksekusi PERTAMA — kompilasi rencana dan
cache dingin — yang keliru dipakai sebagai representatif.

#### Perkakas berbohong, dan kali ini termasuk perkakas ukur

Ini bagian yang paling perlu diingat, karena tiga di antaranya nyaris
menghasilkan kesimpulan yang salah **tentang orang lain**, bukan tentang kode:

1. **Log aktivitas tercemar sapuan rute sendiri.** 4.558 dari 32.432 peristiwa
   (14%) tanpa `Duration_Ms`/`Http_Status` — semuanya dari satu user dalam
   tujuh menit. Tanpa menyaringnya, daftar "rute terpakai" adalah cermin dari
   perkakas audit.
2. **Hari uji beban ikut tercampur.** 19 Agu 2026: 5.781 peristiwa, **829
   pengguna semu**, 2.248 login — sementara hari lain 2–11 orang. Angka p95 222
   detik pada `lms/penjadwalan/store` seluruhnya berasal dari sana; di hari
   biasa endpoint itu 371 ms–3,4 detik.
3. **`Conn_Acquire_Ms` bukan akuisisi koneksi**, melainkan `firstQueryAt − t0`.
4. **Pemindai teks mengarang kaitan**: jendela 220 baris membuat unggah GCS
   tampak berada di dalam transaksi. Diperiksa: kedua pemanggilnya **di luar**
   transaksi. Tuduhan dicabut.

Ditambah dua kekeliruan yang murni milik penulis audit ini: sebuah skrip
perapian akhir-baris **mengosongkan berkas penjaga** (membuka untuk ditulis
sebelum membaca), dan kekosongan itu tak ketahuan karena verifikasi sabotase
saat itu menguji **salinan logikanya**, bukan berkasnya. `php -l` lulus pada
berkas kosong. Sejak itu, verifikasi dijalankan lewat **refleksi atas metode
uji yang sebenarnya** — dan langkah itu langsung menemukan bug kedua: penjaga
yang menormalkan pemisah path lebih dulu sehingga `base_path()` tak lagi cocok.
Salinan logikanya tidak punya cacat itu.

> Aturan yang lahir dari fase ini: **memverifikasi salinan bukan verifikasi.**
> Jalankan berkas yang akan dikirim, bukan tiruannya.

#### Divergensi checkout — pembatas yang wajib diketahui pembaca berikutnya

Perbaikan #326 (tiga kueri hak akses digabung UNION) terbukti **tidak berjalan**
di aplikasi yang dilayani: bentuk lama masih dieksekusi hari ini, berjam-jam
sesudah perbaikan masuk master. Sebabnya bukan kode:

```
hcis-kpi-grouped   cabang feat/performance-apprisal, tertinggal 125 commit
HCIS-W-3           tertinggal 164 commit
HCIS-Worktree-2    tertinggal 0 commit   <- tempat audit ini bekerja
```

Divergensinya **dua arah**: checkout yang dilayani punya fitur yang master tak
punya (`N_PA_Cycle` — nol rujukan di seluruh `app/`), dan master punya
perbaikan yang ia tak punya.

**Akibatnya untuk membaca angka DMV mana pun di dokumen ini:** sinyal "endpoint
mana yang panas" tetap sahih karena itu soal pemakaian. Tetapi setiap atribusi
"bentuk kode inilah penyebabnya" wajib dicek ulang terhadap master.

#### Yang tersisa, dan miliknya siapa

- **DDL-2026-067 (#343)** — usulan RCSI. `LCK_M_S` 12.785 dtk pada 8.194
  penantian (rata-rata 1.560 ms) versus `LCK_M_X` 428 dtk: pembaca menunggu 30×
  lebih lama daripada penulis. Prasyaratnya tempdb (masih SATU berkas data di
  server 2 inti) dan `max server memory` yang belum disetel.
- **`SopPaketDraftService::applyItems`** — boros, sengaja tidak disentuh:
  metodenya penuh invarian yang dijaga komentarnya sendiri, `$activeId` bisa
  baru di-insert pada iterasi yang sama, dan hasilnya tak bisa dibuktikan tanpa
  menjalankan publish.
- **Menyelaraskan checkout yang dilayani** — tanpa itu, perbaikan berikutnya
  tetap tak berjalan di tempat orang mengujinya.

---

## Fase 21 — Tiga mesin nilai akhir training, dan kenapa satu pun tidak digabung

Ini catatan tentang sesuatu yang **sengaja tidak disentuh**, dan tentang bukti
yang ternyata terlalu tipis untuk menyanggah maupun membenarkan.

### Keadaannya

Nilai akhir training dihitung di tiga tempat:

| tempat | baris | perannya |
|---|---|---|
| `LmsMateriController::buildFinalScoreSummary()` | 188 | menampilkan |
| `AdminTrainingMonitoringController::buildFinalScoreSummary()` | 170 | menampilkan |
| `TrainingExecutionService::computeFinalScoreUpdates()` | 176 | **menyimpan** |

Dua yang pertama berbagi **136 dari ~160** baris. Perbedaannya hanya perakitan
muatan: kunci tambahan, teks tooltip, dan `final_score_display` yang bernilai
`'-'` di satu sisi dan `null` di sisi lain.

Perbedaan terakhir itu **benar untuk masing-masing layar**, dan itu diperiksa
bukan diduga: `MonitoringPage.vue:547` merender lencananya di balik `v-if`, jadi
`null` berarti "sembunyikan" sementara `'-'` yang truthy justru memunculkan
lencana bertuliskan "Skor -".

Yang ketiga hanya berbagi **68** baris. Ia menerapkan `$requireCompleted`,
menuntut seluruh skor terisi sebelum sebuah komponen dianggap siap, lalu
meneruskan hasilnya ke `Total_Score`, `Score_Learning_Gain`, dan penentuan
kelulusan.

### Perbedaan yang terbaca di kode

Pada komponen bertipe rata-rata skor:

- **penampil** — skor yang kosong dihitung sebagai `0.0`, lalu dibagi jumlah
  peserta; nilainya tetap dihitung meski ditandai belum siap.
- **penyimpan** — mengembalikan `null` dan tidak siap kecuali SEMUA skor
  terisi.

Ketika semuanya terisi, keduanya sama-sama menghitung `jumlah / total`. Ketika
belum, penampil memperlihatkan angka sementara yang tidak pernah sampai ke nilai
akhir, karena nilai akhir hanya dihitung bila seluruh komponen siap.

### Bukti yang dicoba dikumpulkan, dan kenapa ia tidak cukup

Ketiga mesin diadu langsung pada data nyata lewat refleksi, read-only: untuk
setiap pasangan `(penjadwalan, materi)` yang punya rumus, nilai akhirnya dihitung
tiga kali dan dibandingkan.

> 60 pasangan berumus diperiksa — **1** menghasilkan angka, dan di situ
> ketiganya sepakat (100). 59 sisanya `null` di semua mesin.

**Satu sampel bukan bukti kesetaraan.** Data pengembangan tidak punya cukup
training yang seluruh komponennya terisi untuk menjalankan cabang-cabang yang
berbeda itu.

### Keputusannya

Ketiganya **dibiarkan**. Menyatukan mesin penilaian yang divergensinya tidak
bisa dijalankan berarti menukar duplikasi yang terlihat dengan perubahan
perilaku yang tidak terlihat — pada angka yang menentukan kelulusan orang.

Yang dikerjakan sebagai gantinya: mematok **kontrak yang pasti harus mereka
bagi**, di `tests/Feature/LMS/TigaMesinNilaiTrainingTest.php`.

- sumber rumus yang sama (`N_LMS_Master_Rumus` lewat `Id_Master_Rumus`);
- penguraian token yang sama persis (`preg_match_all('/\[(.*?)\]/', …)`);
- penilai ekspresi yang sama (`PenilaiEkspresiNumerik`, dipusatkan di #354);
- `$requireCompleted` tetap **hanya** milik jalur penyimpan;
- kedua nilai `final_score_display` tetap berbeda seperti adanya.

Selama ketiganya membaca rumus yang sama dan menghitungnya dengan penilai yang
sama, yang boleh berbeda tinggal cara komponen dinilai. Begitu salah satunya
menyimpang di tiga hal pertama, mereka **dijamin** berbeda — dan penjaganya
merah.

Penjaga jumlah mesinnya menyapu **seluruh** `app/`, bukan daftar berkas. Itu
pelajaran dari #357 dan #359: keduanya melewatkan satu salinan justru karena
bekerja dari daftar pasangan.

### Syarat untuk menyatukannya kelak

Data yang bisa menjalankan cabang-cabangnya: training dengan seluruh komponen
terisi, dan training dengan sebagian komponen kosong, pada rumus yang memakai
komponen bertipe rata-rata skor. Tanpa itu, penyatuannya tidak bisa dibuktikan
dan sebaiknya tidak dikerjakan.

---

## Fase 22 — Rute tulis: apa yang dicari, apa yang ditemukan, dan apa yang tidak

Fase ini berpindah kelas: dari duplikasi ke **472 rute tulis** yang sejak awal
disebut belum tersentuh. Batasannya dipegang sepanjang jalan — **tidak satu pun
rute dipanggil**, karena basis data pengembangan dipakai bersama. Semuanya
statis, dibaca dari router Laravel yang sesungguhnya.

### Hasil, termasuk yang negatif

| kelas yang diperiksa | hasil |
|---|---|
| penanganan menulis >1 kali tanpa transaksi | **11 dari 472**, semuanya dinilai satu-satu |
| `commit`/`rollBack` tanpa membuka transaksi | **1**, dan ia nol penulisan |
| rute tulis tanpa gerbang apa pun | **1** — `POST /api/trigger-task`, dicabut (#364) |
| rahasia cadangan di pemanggilan `config()` | **4**, semuanya dialihkan (#364) |
| tabel otorisasi berbentuk heap tanpa PK | **2**, usulan DDL-2026-087 (#362) |
| penanganan tanpa validasi kerangka | 195 — **BUKAN temuan**, lihat di bawah |

**Disiplin transaksi repo ini ternyata baik.** `beginTransaction` muncul 152
kali, dan keenam helper `safeRollback` yang sempat tertangkap pemindai ternyata
bergerbang `transactionLevel() > 0` dengan benar. Dari sebelas sisa yang menulis
tanpa transaksi: dua state-machine webhook yang komit bertahapnya disengaja,
satu tabel telemetri, satu yang mengandung `dispatch` (membungkusnya justru
berbahaya), sisanya positif palsu.

**Rute tulis tanpa autentikasi ternyata hampir seluruhnya terjaga.** Dari 29
yang tak lewat middleware `auth`, semuanya kecuali satu punya middleware kustom
atau memeriksa bearer token di penanganannya.

### Lima kali pemindai berbohong, dan bagaimana ketahuannya

Ini bagian terpenting fase ini, dan berlaku surut untuk **setiap angka pemindai
di dokumen ini**.

1. **Regex peka huruf besar-kecil.** Kode menulis `DB::Begintransaction()`,
   `DB::BeginTransaction()`, `DB::rollback()`. PHP tidak peduli kapitalisasi,
   grep peduli. Empat "temuan" pertama — termasuk satu handler yang saya sempat
   sebut "5 penulisan tanpa transaksi" — seluruhnya positif palsu.
   Ejaan yang benar-benar dipakai: `rollBack` 307×, `commit` 171×,
   `beginTransaction` 152×, `rollback` 22×, `BeginTransaction` 4×,
   `Begintransaction` 2×.
2. **`DB::commit()` dibaca sebagai bukti transaksi.** Ia bukan — yang menentukan
   pasangan pembukanya.
3. **`->delete(` menangkap yang bukan basis data.** `Http::...->delete()` ke
   BunnyCDN dan `Storage::disk('gcs')->delete()` ikut terhitung sebagai
   penulisan.
4. **Penulisan di cabang `if/elseif` yang saling eksklusif dihitung ganda.**
   Pemindai menghitung titik penulisan di SUMBER, bukan penulisan per jalur
   eksekusi.
5. **Closure dilewatkan sama sekali.** Dan justru di situ satu-satunya endpoint
   tanpa gerbang bersembunyi. Penjaganya sekarang membaca closure lewat
   refleksi.

Yang keempat dari empat rahasia cadangan pun ketahuan bukan lewat grep, melainkan
karena **penjaga yang ditulis untuknya langsung merah**.

Pelajarannya satu baris: **angka pemindai tidak boleh dilaporkan sebelum
diverifikasi satu per satu.**

### Kenapa "195 tanpa validasi" tidak dicatat sebagai temuan

Pemindai menyebut 195 dari 454 penanganan tidak memanggil `validate()`,
`Validator::make`, maupun memakai FormRequest. Tiga teratas diperiksa
tangan-demi-tangan:

- `SopAdminBuilderController::updateItem` — 7 pembacaan `$request`, tetapi
  **11 pemeriksaan manual** (`filter_var`, `is_numeric`, `(int)`, `ctype_`).
- `TicketController::simpanTicket` — 9 pembacaan, 1 pemeriksaan manual.
- `AuthController::prosesLogin` — 7 pembacaan, nol pemeriksaan.

Jadi angkanya mencampur "tidak memvalidasi" dengan "memvalidasi tanpa
kerangka". Itu spektrum gaya kode, bukan himpunan cacat, dan melaporkannya
sebagai temuan akan menjadi kesalahan kelima dengan pola yang sama.

### Yang menunggu keputusan, bukan pekerjaan

- **DDL-2026-087 (#362)** — PK dan unique index untuk dua tabel otorisasi
  berbentuk heap. `user_seeks = 0` pada 2.005 baris. Jalankan BAGIAN 4 (`SELECT`
  saja) di production lebih dulu.
- **DML-2026-098 (#358)** — jadwal cutoff absensi memakai rentang tanggal yang
  dipaku pada Mei 2026, sementara perintah yang menghitung periodenya sendiri
  dimatikan. Konfigurasi production belum diperiksa.
- **DDL-2026-067** — usulan RCSI, masih menunggu prasyarat tempdb dan
  `max server memory`.
- **Rotasi token operasional.** Nilainya sudah ada di riwayat git. Literal
  default di `config/services.php` sengaja tidak disentuh: mengubahnya menuntut
  env terisi di setiap lingkungan, dan kalau ada yang belum, pemicu penjadwal
  berhenti bekerja.
- **Menjalankan suite PHPUnit penuh.** Penjaga yang ditambahkan sepanjang audit
  ini seluruhnya pernah dilihat MERAH lewat sabotase terhadap berkas asli, dan
  seluruhnya bebas basis data — tetapi belum pernah dijalankan bersama seluruh
  suite.
- **Menyelaraskan checkout yang dilayani.** Tanpa itu, perbaikan berikutnya
  tetap tak berjalan di tempat orang mengujinya.
