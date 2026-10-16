# Audit Modul VISITOR — Lock, Atomicity, N+1, Error Handling, Async

Tanggal: 2026-09-01 · Cakupan: `app/Http/Controllers/VISITOR`, `app/Services/VISITOR`, `app/Jobs/VISITOR`, `app/Console/Commands/VISITOR`, `routes/VISITOR` (~9.000 baris)

Audit statis (baca kode + kontrak route). **Belum diverifikasi ke DB dev** — beberapa temuan performa perlu diukur dengan populasi nyata sebelum dieksekusi.

Konteks yang memperberat semua temuan lock di bawah: **RCSI mati di `Web_HRIS`** — pembaca antre di belakang penulis, jadi lock yang "cuma beberapa ratus milidetik" langsung terasa sebagai halaman lain menggantung.

---

## Ringkasan prioritas

| # | Temuan | Kategori | Dampak |
|---|--------|----------|--------|
| P0-1 | Lock baris `Karyawan` dipakai sebagai mutex kuota permit | Lock terlalu lama | Tinggi |
| P0-2 | `generateNoTransaksi('VSR')` dipanggil **di dalam** transaksi tamu | Lock terlalu lama | Tinggi |
| P0-3 | `visitor:expire-efektif` pakai `whereDate` → scan + lock eskalasi | Lock terlalu lama | Tinggi |
| P0-4 | Papan harian menarik **seluruh** permit disetujui tanpa jendela | Waktu respons | Tinggi |
| P1-1 | `limit`/`page` tidak divalidasi di 8 endpoint daftar | Waktu respons / DoS | Tinggi |
| P1-2 | Antrean approval: GROUP BY seluruh `HRIS_Approval_Request` + 6 subkueri korelasi | Waktu respons / CPU | Tinggi |
| P1-3 | `syncPermitEfektif`/`syncTamuEfektif` baca-lalu-tulis, dan update tersaring `AKTIF` | Tidak atomic | Sedang |
| P1-4 | Galat `\Exception` polos dari `ApprovalService` & checklist → 500 buta | Try/catch | Sedang |
| P2-1 | Notifikasi pengajuan **tamu** masih sinkron (permit sudah job) | Harusnya async | Sedang |
| P2-2 | `uploadPermitDokumen` baca-lalu-tulis tanpa transaksi | Tidak atomic | Sedang |
| P2-3 | `assertUnique` master data = TOCTOU, bukan unique index | Tidak atomic | Sedang |
| P2-4 | `bulkRevokePermit` tanpa transaksi & tanpa plafon | Tidak atomic | Sedang |
| P2-5 | `cancel()` tamu: `catch (\Exception)` + `rollBack()` tanpa penjaga | Try/catch | Sedang |
| P3-1 | `ApprovalService::approve` menulis notifikasi **di dalam** transaksi bersarang | Lock (laten) | Rendah kini |
| P3-2 | 4 round-trip per halaman laporan | Waktu respons | Rendah |
| P3-3 | N+1 profil approver di `pageProps` | N+1 | Rendah |
| P3-4 | Tidak ada `throttle` di satu pun route VISITOR | Potensi error | Rendah |
| P3-5 | Kode mati berperilaku tak aman (`update`/`destroy`/`checkIn`/`checkOut` tamu) | Potensi error | Rendah |

---

## 1. Lock terlalu lama → server down

### P0-1 · Baris `Karyawan` dipakai sebagai mutex kuota permit
`app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitQuotaService.php:103`

```php
$requesterExists = DB::table('Karyawan')
    ->where('Kode_Karyawan', $kodeKaryawan)
    ->lockForUpdate()   // ← baris di tabel master terpanas di HCIS
    ->exists();
```

Dipanggil dari `VehiclePermitController::store()` tepat setelah `DB::beginTransaction()` (`VehiclePermitController.php:214–224`). Lock eksklusif atas baris `Karyawan` itu **ditahan sampai commit** — melewati insert header, insert detail, `persistDocumentMetadata`, `createChecklistSnapshots`, dan `createFlow` (yang sendirinya menulis `HRIS_Approval_Request`).

Kenapa berbahaya: `Karyawan` di-join oleh hampir setiap kueri di aplikasi ini. Dengan RCSI mati, setiap pembaca yang menyentuh baris karyawan tersebut — homepage-nya sendiri, dashboard leader yang menampilkan dia, laporan absensi — antre di belakang submit permit. Niatnya benar (serialisasi per pemohon), tabelnya salah.

**Perbaikan:** pindahkan mutex ke sumber daya yang memang milik modul ini. Dua opsi:
- `sp_getapplock` dengan nama `visitor:permit:{kodeKaryawan}` dan `@LockOwner='Transaction'` — tidak menyentuh tabel bisnis sama sekali; atau
- baris kunci di tabel counter/lock milik VISITOR sendiri.

Alternatif yang lebih baik lagi: **filtered unique index** di `N_EMI_VISITOR_Transaksi_Permit` yang menegakkan kuota di level DB, sehingga penjaga aplikasi cukup jadi pesan ramah, bukan sumber kebenaran.

### P0-2 · `generateNoTransaksi` dipanggil di dalam transaksi (jalur tamu)
`app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php:180–182`

```php
DB::beginTransaction();
try {
    $noTransaksi = $this->generateNoTransaksi('VSR');   // ← counter di dalam transaksi
```

`TransactionNumberService` sudah memberi peringatan eksplisit di docblock-nya: *"PANGGIL SEBELUM transaksi bisnis; bila dipanggil di dalam transaksi caller, kunci baris counter ikut tertahan hingga commit caller."* Akibatnya baris `HRIS_Counter` untuk prefix `VSR<bulan><tahun>-` terkunci sepanjang transaksi pengajuan tamu — **setiap pengajuan tamu di bulan yang sama terserialisasi penuh**, bukan hanya pada saat pengambilan nomor.

`VehiclePermitController::store()` melakukannya dengan benar (`:137`, sebelum `beginTransaction()` di `:214`). Jalur tamu tinggal disamakan.

**Perbaikan:** pindahkan baris 182 ke atas `DB::beginTransaction()`, persis seperti pola permit.

### P0-3 · Command expire: `whereDate` non-sargable + tanpa batch
`app/Console/Commands/VISITOR/Operations/Expiration/ExpireVisitorEfektif.php:34,44`

```php
->whereDate('Tanggal_Kadaluarsa', '<', $today)   // → CAST(kolom AS date) < ?
```

`whereDate` membungkus kolom dengan `CAST` → indeks apa pun pada kolom itu tidak terpakai → **scan penuh sambil memegang U/X-lock**. Ditambah tidak ada batching: begitu jumlah baris yang di-update melewati ambang eskalasi SQL Server (~5.000), row lock naik jadi **table lock** pada `N_VISITOR_Permit_Efektif` / `N_VISITOR_Tamu_Efektif` — dua tabel yang di-join oleh `baseTamuQuery()`, `basePermitQuery()`, papan harian, dan seluruh laporan. Selama command jalan, semua halaman Visitor menggantung.

**Perbaikan:**
1. `->where('Tanggal_Kadaluarsa', '<', $today)` — kolomnya date/datetime, perbandingan langsung sudah sargable.
2. Batch: `UPDATE TOP (2000) ... ` dalam loop sampai `@@ROWCOUNT = 0`, tiap batch autocommit.
3. Pastikan ada indeks pada `(Status_Efektif, Tanggal_Kadaluarsa)` dan `(Status_Efektif, Tanggal_Kunjungan)`.

### P3-1 · Notifikasi di dalam transaksi bersarang (laten)
`app/Services/SYSTEM/Governance/Approval/Shared/ApprovalService.php:96, 152–156`

`VisitorApprovalDecisionService::approvePermit()` membungkus semuanya dengan `DB::transaction()` dan memegang `lockForUpdate()` atas header permit. Di dalamnya ia memanggil `ApprovalService::approve()`, yang membuka `DB::beginTransaction()` sendiri (jadi savepoint) dan — pada cabang `CONTINUE`/`AUDIT_PASS` — memanggil `sendNextApproverNotification()`, yang menulis ke `N_HRIS_Notification`.

Ini persis yang dihindari dengan susah payah di lapisan controller (lihat komentar di `VerifikatorVisitorController::kabariPemohon()`). Tabel `N_HRIS_Notification` di-poll top-bar setiap pengguna; menulisnya sambil memegang lock header permit adalah jalur yang sudah pernah menyebabkan "Login timeout expired" massal di modul lain.

**Kenapa belum meledak:** flow PERMIT/TAMU saat ini hanya punya 1 step approver, jadi cabang `CONTINUE` tidak pernah tereksekusi. Ini jebakan yang menunggu step kedua ditambahkan.

**Perbaikan:** jadikan pengiriman notifikasi approver berikutnya `dispatch()->afterCommit()`, atau kumpulkan event lalu terbitkan setelah `DB::transaction()` terluar selesai.

---

## 2. Kode tidak atomic

### P1-3 · `syncPermitEfektif` / `syncTamuEfektif`: baca-lalu-tulis + update tersaring
`app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php:34–83, 95–150`

Dua cacat dalam satu blok:

**(a) Race insert.** Pola `$existing = ...->first()` lalu `insert()` tanpa transaksi/lock. Pada jalur auto-approve, `syncPermitEfektif()` dipanggil **setelah commit** (`VehiclePermitController.php:275`) tanpa proteksi apa pun — dua permintaan yang menyerempet bisa sama-sama membaca "belum ada" lalu sama-sama insert. Hasilnya dua baris efektif untuk satu `No_Transaksi`, yang lalu menggandakan baris di setiap `leftJoin ... N_VISITOR_Permit_Efektif`.

**(b) Update yang gagal senyap.** Cabang `$existing` melakukan update dengan `->where('Status_Efektif', 'AKTIF')`. Kalau baris yang ada berstatus `DIBATALKAN` atau `EXPIRED`, `$existing` tetap truthy, tapi update-nya menyentuh **0 baris** dan method `return` tanpa galat. Permit yang dicabut lalu disetujui ulang **tidak pernah hidup kembali**, dan tidak ada satu pun log yang mengatakannya.

**Perbaikan:** ganti dengan satu `MERGE` (atau `updateOrInsert` dalam transaksi) yang di-anchor ke unique index pada `No_Transaksi`, dan hilangkan filter `AKTIF` dari `WHERE` update — status baru justru bagian dari nilai yang ditulis.

### P2-2 · `uploadPermitDokumen`: baca-lalu-tulis tanpa transaksi
`app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php:434–470`

`SELECT ... first()` → `if ($existing) update() else insert()`, tanpa transaksi dan tanpa unique constraint pada `(No_Transaksi, Jenis_Dokumen)`. Dua unggahan bersamaan untuk jenis dokumen yang sama menghasilkan dua baris; sesudah itu `first()` memilih salah satu secara non-deterministik, dan file yang satunya jadi yatim di GCS selamanya.

Urutan GCS-dulu-baru-DB sudah benar (ada cleanup di `catch`), itu bukan masalahnya.

**Perbaikan:** unique index pada `(No_Transaksi, Jenis_Dokumen)` + `updateOrInsert`.

### P2-3 · `assertUnique` master data adalah TOCTOU
`app/Services/VISITOR/Governance/MasterData/VisitorMasterDataService.php:287`

```php
if ($query->lockForUpdate()->exists()) {
    throw new DomainException('Data master ... sudah tersedia.');
}
```

Dua masalah:
1. Di bawah READ COMMITTED, `lockForUpdate()` atas baris yang **tidak ada** tidak mengambil key-range lock. Dua `create()` bersamaan sama-sama membaca "tidak ada", sama-sama insert → duplikat. Penjaganya tidak menjaga apa pun pada kasus yang justru dirancang untuk dicegah.
2. Predikatnya `LOWER(LTRIM(RTRIM(kolom))) = ...` — non-sargable, jadi pengecekan ini adalah **scan tabel dengan U-lock**. Tabel master kecil, jadi dampaknya rendah hari ini, tapi polanya salah.

**Perbaikan:** unique index (computed column `LOWER(LTRIM(RTRIM(...)))` bila perlu case-insensitive) dan tangkap pelanggarannya jadi `DomainException`. Penjaga aplikasi tetap boleh ada untuk pesan yang ramah, tapi DB yang jadi sumber kebenaran.

### P2-4 · `bulkRevokePermit` tanpa transaksi dan tanpa plafon
`app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php:297–385`

- `select_all_matching` mem-`pluck()` **seluruh** `No_Transaksi` yang cocok tanpa `LIMIT`. Filternya opsional semua, jadi "pilih semua" tanpa filter = seluruh permit aktif masuk memori PHP.
- `revokePermitEfektifMany()` memproses per-chunk 500 tanpa transaksi pembungkus. Gagal di chunk ke-3 meninggalkan pencabutan separuh jalan — tanpa jalan balik dan tanpa laporan chunk mana yang berhasil.
- Balasan 500-nya membocorkan `$e->getMessage()` mentah ke klien (`:383`).

**Perbaikan:** plafon eksplisit pada jalur `select_all` (dan **laporkan** plafonnya ke pengguna bila kena — jangan memotong diam-diam), bungkus per-chunk dengan transaksi masing-masing sehingga tiap chunk atomic, dan ganti pesan galat dengan kode galat.

---

## 3. N+1 dan waktu respons

### P0-4 · Papan harian menarik seluruh riwayat permit
`app/Services/VISITOR/Workflow/Reporting/Monitoring/VisitorPapanHarianService.php:210–231`

```php
private function permit(): Collection
{
    return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
        ->where('h.Flag_Aktif', 'Y')
        ->where('h.Flag_Approval', 'Y')
        // tidak ada penyaring tanggal sama sekali
        ->get();
}
```

Seluruh permit yang pernah disetujui ditarik ke memori PHP, lalu disaring **di PHP** jadi "kedaluwarsa hari ini" dan "kedaluwarsa ≤30 hari". Ini tumbuh selamanya. Kueri `tamu()` di atasnya sudah punya jendela 30 hari — inkonsistensi yang jelas tak disengaja.

`tamu()` sendiri (`:168–207`) juga bocor: klausa `orWhereNotNull('e.Waktu_Aktual_Masuk')` dimaksudkan untuk "yang belum keluar dari hari sebelumnya", tapi yang tertulis adalah "yang pernah masuk" — sehingga **seluruh riwayat check-in** ikut tertarik setiap papan dibuka. Yang dimaksud kemungkinan besar `masuk IS NOT NULL AND keluar IS NULL`.

**Perbaikan:** dorong kedua penyaring ke SQL — `d.Tanggal_Kadaluarsa BETWEEN CAST(GETDATE() AS date) AND DATEADD(day, 30, ...)` untuk permit, dan perbaiki klausa OR tamu jadi kondisi "masih di dalam".

### P1-1 · `limit` dan `page` tidak divalidasi di 8 endpoint
```
GuestRegistrationController.php:50-51
VehiclePermitController.php:51-52
VerifikatorVisitorController.php:112-113, 519-520
LaporanVisitorController.php:267-268, 323-324, 404-405
ApprovalVisitorController.php:43-44
```

Semuanya `(int) $request->input('limit', 15)` lalu langsung `->take($limit)`. Klien bisa mengirim `?limit=1000000` dan menarik seluruh tabel dalam satu permintaan; `limit=-1` menghasilkan `TOP (-1)` → galat SQL mentah. Tidak ada `throttle` di route mana pun (lihat P3-4), jadi tidak ada yang menahannya.

Kontras: `VisitorDashboardOperasionalService::antrianBerhalaman()` (`:138`) dan `RunVisitorReportRequest` sudah menjepit dengan benar — pola yang tinggal disalin.

**Perbaikan:** helper bersama `$limit = min(max((int) $request->input('limit', 15), 1), 100);` dipakai di kedelapan tempat.

### P1-2 · Antrean approval: kueri terberat di modul ini
`app/Services/VISITOR/Workflow/Reporting/Dashboard/VisitorDashboardOperasionalService.php:85–108, 190–225`

`queryAntrianApproval()` melakukan `GROUP BY ar.No_Transaksi` atas **seluruh** `HRIS_Approval_Request` — semua modul, semua tahun, tanpa penyaring tanggal — dengan dua `EXISTS` korelasi sebagai predikat. `dasarAntrian()` lalu membungkusnya dan menambahkan **enam subkueri skalar korelasi** yang dievaluasi per baris hasil grup, dan baru sesudah itu penyaring `LIKE` dan paginasi diterapkan di lapis terluar.

Artinya: agregasi + 6 subkueri dihitung untuk semua baris, **lalu** dibuang jadi 10 baris. Dan `antrianBerhalaman()` mengeksekusinya **dua kali** (sekali `count()`, sekali ambil halaman). `VisitorPapanHarianService::menungguReview()` (`:140`) memanggil kueri yang sama sekali lagi.

Ini bentuk yang sama dengan penyebab CPU 100% Cloud SQL yang sudah pernah didiagnosis di dashboard approval — filter di hilir, agregasi di hulu.

**Perbaikan (berurutan, ukur dulu):**
1. Batasi ranah lebih awal: `EXISTS` ke tabel transaksi VISITOR sebagai `JOIN` ke daftar `No_Transaksi` visitor, bukan predikat baris-demi-baris.
2. Tambahkan jendela waktu (`ar.created_at >= DATEADD(month, -N, ...)`) — antrean approval yang menunggu >6 bulan bukan antrean.
3. Ganti keenam subkueri skalar dengan `OUTER APPLY (SELECT TOP 1 ...)` **setelah** paginasi, bukan sebelum.
4. Indeks pendukung pada `HRIS_Approval_Request (No_Transaksi, Flag_Approval)`.

### P3-2 · 4 round-trip per halaman laporan
`LaporanVisitorController.php:359–390` (dan pola sama di `getLaporanPermit`)

Satu halaman laporan tamu = `count()` + ambil data + 3× `count()` untuk stats, masing-masing menjalankan ulang join `header × detail` dengan rentang tanggal yang sama.

**Perbaikan:** satu kueri dengan agregasi kondisional (`SUM(CASE WHEN ... THEN 1 ELSE 0 END)`) menggantikan ketiga count stats.

### P3-3 · N+1 profil approver
`app/Services/VISITOR/Workflow/Approval/VisitorApprovalPolicyService.php:96, 175–182`

`resolveApprovers()` memanggil `getKaryawanProfile()` di dalam `->map()` — satu kueri per approver, dijalankan pada **setiap render** halaman permit dan tamu. Dibatasi `approver_limit` = 2, jadi dampaknya kecil, tapi ini kueri yang seharusnya nol: satu `whereIn` sudah cukup.

---

## 4. Try/catch dan penanganan galat

### P1-4 · Galat bisnis yang sah muncul sebagai 500 buta

`VisitorPermitQueryService::validatePermitChecklist()` (`:272`) melempar `\Exception` polos:
```php
throw new \Exception('Checklist pemeriksaan wajib dilengkapi sebelum permit disetujui.');
```

`VerifikatorVisitorController::approvePermit()` (`:214–222`) menangkap `\InvalidArgumentException` → 422, `\DomainException` → 409, dan sisanya → **500 `'Permit gagal disetujui.'`**. Jadi verifikator yang lupa mengisi satu checklist mendapat *server error* generik, dan pesan yang sudah ditulis dengan jelas itu hanya mendarat di log.

Hal yang sama berlaku untuk seluruh galat `ApprovalService::approve()` (`ApprovalService.php:104, 110, 121, 125, 129`) yang semuanya `\Exception` polos:
- `"Transaksi ini baru saja diproses oleh orang lain."` → seharusnya **409**
- `"Approval sudah expired."` → seharusnya **409**
- `validateApproverAccess` gagal → seharusnya **403**

Ketiganya kini jadi 500 yang identik. Bagi pengguna: tombol Setujui "rusak", tanpa petunjuk.

Efek samping tambahan: karena `approvePermit()` memanggil `saveAnswers()` **sebelum** `validatePermitChecklist()` di dalam transaksi yang sama (`VisitorApprovalDecisionService.php:35–36`), rollback juga membuang jawaban checklist yang baru saja diisi verifikator.

**Perbaikan:**
1. Ubah lemparan checklist jadi `\DomainException` (409) atau `\InvalidArgumentException` (422).
2. Beri `ApprovalService` kelas galat bertipe (`ApprovalConflictException`, `ApprovalAccessDeniedException`) dan petakan di controller. Ini menyentuh pemanggil di luar VISITOR — perlu jalankan suite penuh, bukan hanya uji Visitor.

### P2-5 · `cancel()` tamu: penanganan galat lebih sempit dari yang dibutuhkan
`GuestRegistrationController.php:321–378`

```php
DB::beginTransaction();          // di LUAR try
try {
    ...
} catch (\Exception $e) {        // ← bukan \Throwable
    DB::rollBack();              // ← tanpa cek transactionLevel
    return ResponseHelper::error($e->getMessage(), 422);
}
```

Tiga hal:
1. `\Error` (mis. `TypeError` dari perubahan tipe) lolos dari `catch` → **transaksi tidak pernah di-rollback**, koneksi dikembalikan ke pool dengan transaksi terbuka.
2. `DB::rollBack()` tanpa penjaga `transactionLevel() > 0` bisa melempar sendiri di dalam catch. Versi `cancel()` di `VehiclePermitController:433` sudah punya penjaganya — inkonsistensi antar dua file yang sejajar.
3. Semua galat dibalas 422 dengan `$e->getMessage()` mentah, termasuk galat SQL. Nama kolom dan struktur tabel bocor ke klien, dan galat infrastruktur tampak sebagai kesalahan pengguna.

**Perbaikan:** samakan dengan pola `VehiclePermitController::cancel()` — `catch (\Throwable)`, penjaga `transactionLevel`, kode galat alih-alih pesan mentah.

### Pola berulang: `$e->getMessage()` mentah ke klien
Ada di ~14 tempat, semuanya di endpoint baca:
```
GuestRegistrationController.php:409, 428, 455, 498, 543, 578, 597
VehiclePermitController.php:375
LaporanVisitorController.php:311, 314, 393, 511
VerifikatorVisitorController.php:155, 383, 573, 596, 674
```
Endpoint tulis di `VerifikatorVisitorController` sudah dimigrasikan ke `errorWithCode` + log; endpoint baca belum. Konsistenkan.

---

## 5. Yang seharusnya async

### P2-1 · Notifikasi pengajuan tamu masih sinkron
`GuestRegistrationController.php:257–277` memanggil `$this->visitorNotification->notifikasiPengajuanBaru(...)` langsung di dalam permintaan.

`VehiclePermitController.php:281–296` untuk kasus yang sama sudah memakai `PublishVisitorNotificationsJob::dispatch(...)`, dan `VerifikatorVisitorController::kabariPemohon()` juga. Jalur tamu satu-satunya yang tertinggal.

Keduanya sudah benar dalam hal **penempatan** (setelah commit, dibungkus try/catch sendiri), jadi ini bukan risiko lock — hanya latensi yang ditanggung pemohon: satu kueri penerima + satu upsert batch per submit.

**Perbaikan:** ganti dengan `PublishVisitorNotificationsJob::dispatch([[ 'kind' => 'submission', ... ]])`, persis seperti jalur permit. Payload-nya identik.

Catatan positif: `VisitorNotificationService` memakai dedup `Notification_Key` (`upsertByKey` / `upsertManyByKey`), jadi retry job **aman** — tidak menghasilkan notifikasi kembar. Tidak perlu perubahan idempotensi di sisi job.

### Yang sudah benar (jangan diubah)
- Unggah 3 dokumen permit dilakukan **sebelum** `beginTransaction()`, dengan cleanup GCS di `catch` (`VehiclePermitController.php:213, 305`). Pola ini benar; jangan tergoda memindahkannya ke dalam transaksi.
- Export Studio (`jalankan()`) sudah `dispatchJob` + balas 202, dengan `per_page` dijepit `max:500` di FormRequest. Ini rujukan yang baik untuk endpoint lain.
- `VisitorPermitChecklistService::saveAnswers()` sudah satu statement `UPDATE ... FROM (VALUES ...)`, bukan loop — dan memeriksa `$affected !== count($answers)`.

---

## 6. Potensi error lain

### P3-4 · Tidak ada `throttle` di satu pun route VISITOR
Semua route hanya `['auth', 'check.jabatan:...']`. Yang paling perlu:
- `POST transaksipermit` — 3 unggahan × 5 MB per permintaan, langsung ke GCS.
- `POST transaksitamu`
- `POST verifikator-permit-bulk-revoke`
- `POST .../pratinjau` (Export Studio)

`Idempotency-Key` sudah melindungi dari **duplikasi**, tapi bukan dari **volume** — kunci berbeda tiap kali tetap lolos.

### P3-5 · Kode mati yang berperilaku tak aman
`GuestRegistrationController::update()` (`:409`), `destroy()` (`:430`), `checkIn()` (`:457`), `checkOut()` (`:500`) **tidak terdaftar di route mana pun** (dikonfirmasi di `routes/VISITOR/Guest/Visit/GuestRegistration.php`, yang `->only(['index','store','show'])`; check-in/out sengaja dicabut di commit d641d350 dan dijaga `VisitorRouteContractTest`).

Jadi ini bukan celah aktif. Tapi keempatnya **tidak memeriksa kepemilikan sama sekali** — `update($id)` menulis header tamu mana pun berdasarkan Id telanjang. Kalau suatu hari seseorang mendaftarkan ulang salah satunya (dan `update`/`destroy` tinggal menambah dua kata di `->only()`), itu langsung jadi IDOR.

**Perbaikan:** hapus keempat method, atau — kalau ingin dipertahankan sebagai rencana masa depan — tambahkan penyaring `Kode_Karyawan_PIC` sekarang juga, jangan nanti.

### Catatan: `revokePermit` tidak menyentuh header transaksi
`revokePermitEfektif()` hanya mengubah `N_VISITOR_Permit_Efektif.Status_Efektif`, sementara `N_EMI_VISITOR_Transaksi_Permit.Flag_Approval` tetap `'Y'`. Perhitungan kuota (`permitStatusSql()`) perlu diverifikasi benar-benar menurunkan status `REVOKED` dari tabel efektif — kalau tidak, permit yang dicabut tetap memakan jatah pemohon, dan komentar panjang di `VisitorPermitQuotaService` yang menjanjikan sebaliknya jadi tidak akurat. **Perlu dicek langsung ke DB dev.**

---

## Urutan eksekusi yang disarankan

**Gelombang 1 — perbaikan kecil, risiko rendah, dampak langsung**
1. P0-2 pindahkan `generateNoTransaksi` ke luar transaksi (1 baris)
2. P1-4 ubah lemparan checklist jadi `DomainException` (1 baris)
3. P2-1 tamu → `PublishVisitorNotificationsJob` (salin dari jalur permit)
4. P2-5 samakan `cancel()` tamu dengan pola permit
5. P1-1 helper penjepit `limit`/`page` di 8 endpoint

**Gelombang 2 — butuh DDL**
6. P2-2 unique index `(No_Transaksi, Jenis_Dokumen)` + `updateOrInsert`
7. P2-3 unique index master data, penjaga aplikasi jadi lapis kedua
8. P1-3 `MERGE` untuk `sync*Efektif`, buang filter `AKTIF` dari update
9. P0-1 pindahkan mutex kuota dari `Karyawan` ke applock/tabel sendiri

**Gelombang 3 — butuh pengukuran dulu**
10. P0-3 command expire: sargable + batching (+ indeks)
11. P0-4 dorong penyaring papan harian ke SQL
12. P1-2 bongkar kueri antrean approval — **ukur dengan `SET STATISTICS IO/TIME` di DB dev sebelum dan sesudah**, jangan tebak

**Gelombang 4 — lintas modul, hati-hati**
13. P3-1 + galat bertipe di `ApprovalService` — menyentuh KPI/cuti/SOP juga; wajib jalankan suite penuh, bukan hanya uji VISITOR

---

## Yang belum diverifikasi

Semua di atas dari pembacaan kode. Sebelum eksekusi, perlu dikonfirmasi ke DB dev:
- Populasi nyata `N_EMI_VISITOR_Transaksi_Permit` dengan `Flag_Approval='Y'` — menentukan apakah P0-4 sudah sakit sekarang atau baru nanti.
- Jumlah baris `HRIS_Approval_Request` dan rencana eksekusi `dasarAntrian()` — menentukan prioritas P1-2.
- Indeks yang sudah ada pada tabel `*_Efektif` — menentukan bentuk perbaikan P0-3.
- Apakah `permitStatusSql()` benar-benar menurunkan `REVOKED` (catatan di §6).
