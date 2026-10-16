# Performa dan atomicity modul LMS/SOP — batas transaksi, error handling, dan skala

Tanggal: 2026-09-01
Cabang: `feat/sop/harderning`
Status: rancangan disetujui, menunggu rencana implementasi

Basis verifikasi basis data (wajib dicatat per aturan agen):

- Lingkungan: **development**
- Target: `Web_HRIS` pada host `team311.dyndns.info:1433`, diverifikasi lewat `SELECT DB_NAME()`
- Tanggal verifikasi: 2026-09-01
- Sifat inspeksi: **read-only** (`sys.databases`, `sys.indexes`, `sys.partitions`)
- Modul pemilik: **LMS → SOP**, dipetakan ke grup `LMS` lewat `N_HRIS_Grup_Fitur_Map`

---

## 1. Masalah yang dijawab rancangan ini

Audit menyeluruh modul SOP/DMS pada 2026-09-01 menemukan delapan belas butir (tujuh
belas dari pembacaan kode, satu lagi dari inspeksi basis data saat merancang §6e).
Rancangan ini menjawab seluruhnya. Yang mengikat semuanya menjadi satu masalah, bukan
delapan belas masalah terpisah, adalah satu fakta yang diverifikasi langsung ke basis
data:

> `READ_COMMITTED_SNAPSHOT` pada `Web_HRIS` bernilai **OFF**.

Tanpa RCSI, pembaca antre di belakang penulis. Setiap transaksi yang berumur panjang
karena itu bukan sekadar lambat bagi pemiliknya — ia membekukan semua orang lain yang
menyentuh tabel yang sama. Itulah mekanisme di balik insiden "Login timeout expired"
yang sudah pernah terjadi di project ini, dan itulah sebabnya butir-butir di bawah
diperlakukan sebagai satu rancangan.

### 1.1 Konteks yang mengubah keputusan

Modul SOP/DMS **belum naik ke produksi**. Konsekuensinya dua arah:

- Tabel `N_SOP_*` bebas diubah strukturnya. Menambah indeks sekarang instan; menambahnya
  setelah tabel berisi jutaan baris menuntut jendela pemeliharaan. Ini keuntungan sekali
  pakai yang tidak datang dua kali.
- Tabel **bersama** tetap bukan wilayah bebas: `N_HRIS_Notification`, `N_HRIS_Async_Tasks`,
  `N_SYSTEM_Task_Outbox`, dan tabel cache sudah dipakai modul yang berjalan di produksi.
  Rancangan ini tidak mengubah strukturnya sama sekali.

### 1.2 Temuan yang dijawab

| Kode | Temuan | Dijawab di |
|---|---|---|
| P0-1 | Publish paket berjalan sebagai satu transaksi fisik berbiaya O(item × karyawan); pemotongan di dalamnya lumpuh jadi savepoint | §3 |
| P0-2 | Penulisan `N_HRIS_Notification` berada di dalam transaksi publish | §3 |
| P0-3 | `pendingChanges()` dihitung dua kali di dalam transaksi; yang kedua hanya untuk payload respons | §3 |
| P0-4 | 19 dari 29 `DB::beginTransaction()` hanya menangkap `\Exception`, sehingga `\Error` meninggalkan transaksi menggantung | §5a |
| P1-5 | `assignKeHeader()` melakukan satu `insertGetId` per karyawan | §6a |
| P1-6 | `simpanHalaman()` memakai `lockForUpdate()` di luar transaksi | §6b |
| P1-7 | Cache monitoring di-bypass total begitu ada saringan non-waktu | §6e |
| P1-8 | Cache memakai store `database`, menambah beban ke basis data | §6e |
| P1-9 | Ringkasan Master Dokumen memuat seluruh dokumen tanpa limit, lalu `whereIn` semua id | §6c |
| P1-10 | Pengurutan lewat subkueri berkorelasi per baris | §6c |
| P1-11 | `whereDate()` membungkus kolom terindeks | §6d |
| P1-12 | Celah indeks pada `N_SOP_Kepatuhan_Riwayat` dan `N_SOP_Penjadwalan_Detail` | §6f |
| P2-13 | Kerja setelah `commit()` masih berada di dalam `try` | §5b |
| P2-14 | Endpoint bulk menerima array id tanpa batas | §5d |
| P2-15 | Tiga `catch` menyembunyikan kegagalan tanpa jejak | §5e |
| P2-16 | Potongan reconcile yang gagal hanya mendarat di log | §5f |
| P2-17 | 178 titik `Log::error()` membuang stack trace | §5c |
| P1-18 | Tabel `cache` tidak pernah dipangkas; 79% barisnya sudah kedaluwarsa dan menetap | §6e |

### 1.3 Koreksi terhadap laporan audit awal

Temuan P2-13 dikoreksi **dua kali**, dan kedua kali arahnya sama: saya
melebih-lebihkannya. Dicatat lengkap di sini alih-alih diperbaiki diam-diam, karena
keputusan cakupan diambil sebagian berdasarkan angka ini.

| Klaim | Angka | Nasib |
|---|---|---|
| Laporan audit lisan | 19 titik | **Salah.** `SopLog::catat()` sudah membungkus insert-nya dengan `catch (\Throwable)` (`SopLog.php:56`), jadi `jejak()` tak dapat melempar. 18 dari 19 gugur. |
| Koreksi pertama (spec v1) | 4 titik | **Masih salah.** Contoh andalannya — `Storage::disk('gcs')->delete()` di `SopAdminBuilderController:437` — ternyata **sudah** berada di dalam `try/catch` sendiri sejak awal, lengkap dengan `Log::error`. Ia tak pernah bisa menghasilkan 500. |
| **Angka sebenarnya** | **3 titik** | Query basis data setelah commit di dalam `try`: `paketDariItem()` pada `updateItem` cabang draft (~1227), `paketDariSection()` pada `cloneSection` (~1446), dan `paketDariItem()` pada `cloneItem` (~1485). Hanya ketiganya yang benar-benar dapat mengubah operasi yang sudah commit menjadi respons 500. |

`SopDiff::antara()` yang juga berjalan setelah commit adalah PHP murni tanpa I/O;
risikonya ada tetapi tidak setara, dan ia ikut keluar dari `try` sebagai akibat
perapian, bukan sebagai perbaikan tersendiri.

**Pelajaran yang layak dicatat:** ketiga koreksi ini baru muncul ketika kodenya
benar-benar dibaca baris per baris saat implementasi. Audit yang membaca pola lewat
`grep` menemukan bentuknya dengan benar tetapi salah menilai akibatnya — `catch` yang
sudah ada di dalam fungsi yang dipanggil tidak terlihat dari pola pemanggilnya.

---

## 2. Keputusan yang sudah diambil

Empat keputusan berikut diambil pemilik project dan **tidak dibuka kembali** selama
implementasi:

1. **Publish paket balas seketika.** Rekonsiliasi selalu lewat antrean; ambang
   `penjadwalan_async_ambang` dicabut dari jalur ini.
2. **Durabilitas lewat outbox, visibilitas lewat panel Aktivitas.** Intent ditulis ke
   `N_SYSTEM_Task_Outbox` di dalam transaksi inti; progres tampil di
   `N_HRIS_Async_Tasks`.
3. **Cakupan penuh P0 + P1 + P2**, termasuk DDL indeks.
4. **Pendekatan A** — rekonsiliasi turun pangkat menjadi penulis intent, bukan
   pengumpul di controller.

---

## 3. Batas transaksi yang baru

### 3.1 Keadaan sekarang

```
Controller  BEGIN TRAN ────────────────────────────────────────────┐
  publish()   savepoint · lock paket + draft                       │
    applyItems() per item                                          │
      terbitkanVersi()  savepoint · lock dokumen                   │
        rekonsiliasi()                                             │
          reconcilePaket()  savepoint/potongan  ← lock TAK dilepas │
            usleep()                            ← tidur memegang lock
          notifDokumenDiperbarui()              ← tulis N_HRIS_Notification
  pendingChanges() ke-2 (hanya untuk respons)                      │
            COMMIT ◄──────────────────────────────────────────────┘
```

Laravel menerjemahkan `DB::transaction()` bersarang menjadi `SAVE TRANSACTION`, bukan
commit. Karena itu tiga lapis pemotongan di dalamnya tidak melepas satu lock pun.
`SopReconciliationService.php:84-100` sudah mengukur sendiri biayanya — 308 kueri dan
16,3 detik untuk 34 penugasan × 3 item — dan menyebut pola ini identik dengan insiden
"Login timeout expired". Pemotongannya benar bila dipanggil sendiri; lewat publish,
tidak.

### 3.2 Keadaan yang dituju

```
publish()  BEGIN TRAN ───────────────────────────────┐
  lock paket + draft · guard versi basis             │
  pendingChanges()                ← sekali, validasi │
  update paket · applySections · applyItems          │
    terbitkanVersi() → promosi versi, pointer, nomor │
      rekonsiliasi() → INSERT 1 baris outbox/dokumen │ ← hanya intent
  release() snapshot · update basis draft            │
  catat baris N_HRIS_Async_Tasks (QUEUED)            │
           COMMIT ◄─────────────────────────────────┘
   ↓ setelah commit
  pendingChanges() ke-2 → payload respons 202
   ↓ TaskOutboxDispatcher — di luar transaksi mana pun
  SopReconcileDokumenHandler
    paketPerujuk() → reconcilePaket() per paket
      transaksi pendek per 100 karyawan; lock DILEPAS tiap potongan
    satu notifikasi gabungan per karyawan
    bumpProgress() → selesai()
```

**Inti perbaikannya:** biaya transaksi berubah dari **O(item × karyawan)** menjadi
**O(item)**. Item satu paket berjumlah puluhan; karyawan berjumlah ribuan.

### 3.3 Yang tetap atomik, dan alasannya

Promosi versi DRAFT→PUBLISHED, pointer `Id_Item_Versi_Aktif`, `Id_Versi_Aktif` pada
dokumen, perakitan nomor resmi, dan snapshot versi paket tetap berada dalam satu
transaksi. Semuanya invarian yang **tidak** ditegakkan basis data; bila pecah di
tengah, tidak ada mekanisme apa pun yang akan menangkapnya.

### 3.4 Intent dipasang pada DOKUMEN, bukan paket

Bila intent dibuat per paket, keputusan produk 2026-08-10 — satu notifikasi per
karyawan per revisi dokumen — pecah, dan karyawan yang terdaftar di enam paket kembali
menerima enam notifikasi. Handler yang memekarkan dokumen menjadi paket-paketnya,
sehingga penggabungan notifikasi tetap utuh.

```
Task_Type       : SOP_RECONCILE_DOKUMEN
Idempotency_Key : SOP_RECONCILE:{Id_SOP_Dokumen}:{Id_SOP_Item_Versi}
Aggregate_Type  : SOP_DOKUMEN
Payload         : { id_dokumen, id_versi, alasan, user_id, kecuali_paket }
```

Dedup bawaan `TaskOutboxService::create()` membuat publish yang di-retry tidak
melahirkan pekerjaan ganda.

**Dua tabel, dua diskriminator — jangan tertukar.** Keduanya kebetulan memakai kolom
bernama `Task_Type`, tetapi tabel dan nilainya berbeda dan perannya berbeda:

| Tabel | Nilai | Peran |
|---|---|---|
| `N_SYSTEM_Task_Outbox` | `SOP_RECONCILE_DOKUMEN` | **Durabilitas.** Intent pekerjaan; dibaca dispatcher dan handler. |
| `N_HRIS_Async_Tasks` | `reconcile-dokumen` (`Module='sop-dokumen'`) | **Visibilitas.** Progres untuk panel Aktivitas; tidak pernah menjadi sumber kebenaran pekerjaan. |

Bila keduanya berselisih — outbox selesai tetapi baris progres tertinggal — yang benar
adalah outbox. Baris Async_Task adalah cermin, bukan catatan.

---

## 4. Komponen

Penempatan berkas mengikuti registry modul: **LMS → SOP → Rekonsiliasi Dokumen →
layer Service**. Handler mengimplementasikan antarmuka `TaskHandler` milik SYSTEM
(antarmuka boleh lintas modul), tetapi berkasnya tinggal di modul pemiliknya.

### 4.1 Berkas baru

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/LMS/SOP/SopReconcileDokumenHandler.php` | Implements `TaskHandler`. Memekarkan satu dokumen menjadi paket perujuk, memanggil `reconcilePaket()` per paket, lalu satu notifikasi gabungan. Melempar `RetryableTaskException` untuk galat sementara, `PermanentTaskException` untuk dokumen yang sudah tak ada. |
| `app/Services/LMS/SOP/SopReconcileTaskService.php` | Daur hidup baris `N_HRIS_Async_Tasks` (`Task_Type='reconcile-dokumen'`, `Module='sop-dokumen'`). Cermin `SopBulkAssignTaskService`. Nol DDL: tabelnya sudah generik. |
| `tests/Feature/LMS/SOP/SopPublishBatasTransaksiTest.php` | Penjaga regresi utama (§7a). |

Satu baris registrasi di `AppServiceProvider`, sejajar tiga handler yang sudah ada:

```php
$registry->register('SOP_RECONCILE_DOKUMEN', $app->make(SopReconcileDokumenHandler::class));
```

### 4.2 Berkas yang berubah

- `SopDokumenFanoutService::rekonsiliasi()` — dari mesin kerja menjadi penulis intent.
  `hitungDampak()` dan `rincianPerPaket()` tidak disentuh: keduanya milik layar
  pratinjau, bukan jalur eksekusi.
- `SopPaketDraftService::publish()` — `pendingChanges()` kedua keluar dari transaksi;
  menambah pembuatan baris Async_Task sebelum commit.
- `SopDokumenService::jalankanPenerbitan()` — tetap memanggil `rekonsiliasi()`, yang
  kini murah. Urutan langkah tidak berubah.
- `SopAdminBuilderController::updateStatus()` — melepas
  `DB::beginTransaction()`/`commit`/`rollBack` dan variabel `$committed`; membalas
  **202** beserta `task_id`.
- `SopReconciliationService` — logikanya **tidak diubah**. Hanya menerima satu callback
  progres opsional yang berbaku `null`, sehingga pemanggil lama berperilaku identik.

### 4.3 Frontend

- `SopBuilder.vue` — tombol Publish beralih ke keadaan "sedang diproses" dan
  menampilkan progres, memakai pola poll yang sudah terbukti di
  `SopPenjadwalan.vue:619`: interval 5 detik, digerbangi `document.visibilityState`,
  berhenti sendiri saat tak ada batch berjalan. Tidak ada mekanisme poll baru.
- Panel Aktivitas — **tidak generik.** Rancangan awal menyatakan tipe baru muncul
  sendiri; itu keliru dan dikoreksi setelah pembacaan kode pada 2026-09-01.
  `ActivityCenterService` memiliki adapter eksplisit per `Task_Type` (`export`,
  `upload`, `pdf-render`, `bulk-assign`), dan tipe yang tak punya adapter **tidak akan
  pernah tampil**. Menambahkannya menyentuh empat tempat:

  | # | Tempat | Bila terlewat |
  |---|---|---|
  | 1 | `ActivityCenterService::reconcileItems()` — adapter baru | Tak ada yang membaca barisnya |
  | 2 | `ActivityCenterService::list()` (baris 44, `array_merge`) | Tak muncul di daftar |
  | 3 | `ActivityCenterService::summary()` (baris 93, `array_merge`) | Muncul di daftar tetapi **tidak terhitung di lencana** |
  | 4 | `ActivityCenterController::dismiss()` — cabang `$type` | Tombol tutup mengembalikan galat |

  Nomor 2 dan 3 adalah pasangan setara yang mudah dikerjakan separuh — pola "aturan dua
  pintu" yang sudah berulang kali menjadi bug di modul ini. Penjaganya diuji lewat
  **efek** (daftar dan lencana sama-sama menghitungnya), bukan lewat pemanggilan.

### 4.4 Yang sengaja tidak dibuat

Tabel baru, endpoint poll baru, mekanisme progres baru, dan Job Laravel baru.
`ProsesReconcileSop` yang lama dipertahankan untuk pemanggil non-publish (reconcile
manual), sehingga tidak ada pintu yang kehilangan jalurnya.

---

## 5. Disiplin transaksi dan error handling

### 5a. Pola tunggal menggantikan begin/commit/rollback manual

Akar P0-4 bukan "lupa menulis `\Throwable`" di sembilan belas tempat, melainkan bahwa
penulisan `rollBack()` diserahkan pada ingatan manusia di dua puluh sembilan tempat.
Perbaikannya struktural:

```php
// SESUDAH — rollback dijamin closure, apa pun yang dilempar
try {
    $hasil = DB::transaction(fn () => $this->service->kerja(...));
} catch (\DomainException $e) {
    return ResponseHelper::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Log::error('SOP updateStatus gagal', ['exception' => $e]);
    return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
}
// kerja setelah commit hidup DI LUAR try
```

`DB::transaction()` melakukan rollback pada `Throwable` apa pun lalu melemparkannya
kembali. Sesudah ini tidak ada jalan bagi `\Error` meninggalkan transaksi menggantung,
dan tidak ada titik baru di masa depan yang bisa salah menuliskannya.

Cakupan seluruh 29 titik: `SopAdminBuilderController` (18),
`SopAdminDokumenController` (4), `SopApqcController` (3),
`SopJenisDokumenController` (4). Sembilan belas di antaranya adalah yang hanya
menangkap `\Exception`; sepuluh sisanya tetap diubah agar polanya seragam — pola yang
hanya berlaku sebagian adalah pola yang akan menyimpang lagi.

### 5b. Kerja setelah commit

Empat titik keluar dari `try`:

- `SopAdminBuilderController:433` — `Storage::disk('gcs')->delete()`. Panggilan
  eksternal setelah commit; GCS di project ini sudah pernah menolak seluruh operasi
  objek dengan 403. Dijadikan best-effort memakai pola yang **sudah ada** di
  `SopBerkasService:72-76`.
- `:549` — `reconcileSetelahPublish()`. Hilang sendiri: menjadi intent di dalam
  transaksi.
- `:1444`, `:1483` — `paketDariSection()` / `paketDariItem()` menjalankan query setelah
  commit di dalam `try`.

`jejak()` dibiarkan setelah commit dan **tidak** dipindah ke dalam transaksi: ia aman
secara konstruksi (§1.3).

### 5c. Log yang bisa dilacak

178 titik `Log::error($e->getMessage())` menjadi
`Log::error('<konteks>', ['exception' => $e])`. Mekanis, tanpa risiko perilaku.
Dikerjakan sebagai langkah tersendiri supaya diff-nya mudah ditinjau dan tidak
bercampur dengan perubahan logika.

### 5d. Batas masukan bulk

Validasi endpoint bulk menerima `'ids' => 'array|max:500'`. Bukan sekadar pagar: 500
parameter jauh di bawah plafon 2100 milik SQL Server, sehingga penolakannya jelas di
lapis validasi alih-alih muncul sebagai galat basis data yang membingungkan.

### 5e. Catch senyap

- `SopAdminDokumenController:1696` dan `:1708` — diberi `Log::warning` berkonteks.
  Perilaku tetap (field hilang, halaman tetap tampil), tetapi kegagalannya berhenti tak
  terlihat.
- `SopRepositoryReadService:579` — dipersempit dari `\Throwable` menjadi hanya
  pelanggaran unik (2601/2627), mengikuti pola yang sudah dipakai
  `SopCompletionService:114`. Galat lain dilempar: menjawab `favorit: true` atas insert
  yang gagal adalah berbohong kepada pengguna.

### 5f. Potongan reconcile yang gagal

Setelah §3, `reconcilePotongan()` tidak lagi berjalan di dalam transaksi luar, sehingga
risiko *doomed transaction* hilang dengan sendirinya. Yang tetap diperbaiki:
`$stat['gagal']` harus sampai ke layar lewat baris Async_Task ("selesai, 2 potongan
gagal"), bukan hanya mendarat di log. Pembatasan yang senyap adalah pembatasan yang
menipu.

---

## 6. Performa dan DDL

### 6a. `assignKeHeader()` menjadi set-based

Id detail tidak perlu dikembalikan: baris progress dapat diturunkan dengan menjoin
balik lewat `(Id_SOP_Penjadwalan, Kode_Karyawan)`.

```
1. bulk INSERT N_SOP_Penjadwalan_Detail   (diff "sudah ter-assign" dipertahankan)
2. INSERT N_SOP_Progress
     SELECT d.Id_SOP_Penjadwalan_Detail, i.Id_SOP_Paket_Item, i.Id_Item_Versi_Aktif,
            d.Kode_Karyawan, i.Tipe_Item, 'BELUM', 0, 0, GETDATE()
     FROM  N_SOP_Penjadwalan_Detail d
     JOIN  N_SOP_Paket_Item i ON i.Id_SOP_Paket = ?
                             AND i.Flag_Wajib='Y' AND i.Flag_Aktif='Y'
     WHERE d.Id_SOP_Penjadwalan = ? AND d.Kode_Karyawan IN (<potongan>)
       AND NOT EXISTS (progress untuk pasangan itu)
3. bumpRollup per item ber-versi          (sudah kecil, tak berubah)
```

Dari sekitar 200 pernyataan per 100 karyawan menjadi sekitar 4. Bentuknya sama dengan
refactor cabut massal yang sudah terbukti di modul ini (122 → 8 kueri). `NOT EXISTS`
menjaga idempotensi yang menjadi syarat retry Cloud Tasks.

### 6b. `simpanHalaman()` — kunci dibuang, ganti conditional update

Jalur ini dipanggil setiap pindah halaman dan setiap 60 detik per pembaca. Sekarang
menjalankan sekitar enam kueri, dengan `UPDLOCK/HOLDLOCK` yang tidak melindungi apa pun
karena dipasang di luar transaksi.

```sql
UPDATE N_SOP_Progress
   SET Halaman_Terakhir   = CASE WHEN ISNULL(Halaman_Terakhir,0) < @h
                                 THEN @h ELSE Halaman_Terakhir END,
       Durasi_Aktif_Detik = ISNULL(Durasi_Aktif_Detik,0) + @d,
       Waktu_Buka         = ISNULL(Waktu_Buka, SYSDATETIME()),
       Updated_At         = SYSDATETIME()
 WHERE Id_SOP_Penjadwalan_Detail = @det
   AND Id_SOP_Paket_Item        = @item
   AND <versi cocok>
```

Nol baris terpengaruh menempuh jalur pembuatan baris. Baca-ubah-tulis hilang, balapan
hilang, lock hilang. Ini pola yang diwajibkan standar transaction butir 8.
`bacaProgressTerkunci()` tetap ada dan tetap berkunci untuk `buka()` dan
`konfirmasi()`, yang memang berada di dalam transaksi dan memang butuh serialisasi.

### 6c. Ringkasan Master Dokumen dan pengurutan berkorelasi

- Ringkasan (`SopAdminDokumenController:495-520`): dari "muat semua dokumen → `whereIn`
  semua id tiga kali → hitung pil di PHP" menjadi agregat `GROUP BY` di basis data.
  Menghapus sekaligus beban memori, tiga kueri, dan bom parameter 2100 yang akan
  meledak di sekitar dokumen ke-2100.
- Pengurutan (`:236`): subkueri berkorelasi diganti `LEFT JOIN` ke `N_SOP_Item_Versi`
  lewat pointer `Id_Versi_Aktif` yang sudah ada, lalu `CROSS APPLY (VALUES ...)`
  dihitung atas kolom yang sudah ter-join. Satu seek untuk seluruh halaman, bukan satu
  seek per baris.

### 6d. Kueri tak sargable

`whereDate('p.Tanggal_Publish', …)` pada `SopPaketService:274,279,282` menjadi
perbandingan rentang setengah terbuka (`>=` awal hari, `<` hari berikutnya). Kolomnya
tidak lagi dibungkus fungsi sehingga indeksnya terpakai.

### 6e. Cache

**Fakta yang sudah dipastikan pemilik project (2026-09-01): tidak ada Redis. Cache di
produksi memakai basis data.** Karena itu `Cache::store('database')` di SOP bukan
kesalahan melainkan satu-satunya store lintas-instance yang tersedia, dan dipertahankan.
Konsekuensinya harus dihadapi langsung: **setiap kunci cache adalah baris di tabel
bersama pada basis data yang sama yang sedang kita coba ringankan.**

- **P1-8** — store `database` dipertahankan. Yang diperbaiki: memoisasi per-request
  untuk `SopSetting` dan opsi saringan, supaya nilai yang sama tidak ditanyakan berulang
  dalam satu permintaan.
- **P1-18 (temuan baru, prasyarat bagi P1-7)** — **tabel `cache` tidak pernah
  dipangkas.** Terverifikasi read-only di `Web_HRIS` pada 2026-09-01: 121 baris, **96 di
  antaranya (79%) sudah kedaluwarsa** dan tetap menetap. `Illuminate\Cache\DatabaseStore`
  hanya menghapus sebuah kunci ketika kunci itu ditulis ulang atau di-`forget`; baris
  yang kedaluwarsa dan tak pernah diminta lagi tinggal selamanya. Tabelnya karena itu
  tumbuh monoton mengikuti **kardinalitas kunci**, bukan mengikuti jumlah kunci yang
  hidup.

  Perbaikan: satu command pemangkas terjadwal
  (`DELETE FROM cache WHERE expiration < <now>`), memakai `idx_cache_expiration` yang
  sudah ada sehingga berupa seek, dijalankan dalam batch seperti pola
  `hris:api-nonce-prune` yang sudah dipakai di project ini. Ini **tabel bersama**, jadi
  penghapusannya dibatasi pada baris kedaluwarsa saja dan dipecah per batch.

- **P1-7** — berhenti mem-bypass cache saat ada saringan
  (`SaringanMonitoring.php:106`). Kunci diperluas dengan hash saringan yang sudah
  dinormalkan, TTL 300 detik.

  **Urutannya mengikat: P1-18 harus lebih dulu.** P1-7 bekerja dengan menaikkan
  kardinalitas kunci — persis dimensi yang membuat tabel cache tumbuh tanpa batas hari
  ini. Menambahkannya sebelum ada pemangkas berarti menukar satu masalah performa dengan
  masalah lain yang lebih sulit dilihat. Saringan juga dinormalkan lebih dulu supaya
  bentuk yang ekuivalen berbagi satu kunci.

**Diperiksa dan sengaja TIDAK diubah.** `SopPenjadwalanTargetService::daftarOrang()`
menyimpan satu entri cache berukuran 209 KB. Diukur pada 2026-09-01: 958 karyawan aktif,
219 B per orang. Proyeksinya 0,4 MB pada 2.000 karyawan dan 1,0 MB pada 5.000 — masih
wajar, dan keputusan yang sudah terdokumentasi di berkas itu (memuat sekali lalu
menyaring di layar, dan menolak plafon senyap) tetap sehat pada skala nyata. Angkanya
dicatat di sini supaya ambang peninjauan ulang menjadi eksplisit alih-alih perlu
diturunkan ulang: bila entri ini melewati sekitar 1 MB, yang benar adalah menambahkan
pencarian jarak jauh ke `HcisSelect` sebagai inisiatif tersendiri — bukan menambal di
sini.

### 6f. DDL indeks — satu change-id

Batasan yang dipatuhi: aturan agen melarang eksekusi `DROP INDEX`. Karena itu rancangan
ini **hanya membuat indeks baru**. Indeks lama yang tergantikan dituliskan di
`drop.sql` sebagai alat bantu manual dan tidak dijalankan agen.

| Tabel | Indeks | Untuk kueri |
|---|---|---|
| `N_SOP_Kepatuhan_Riwayat` | `(Waktu_Peristiwa) INCLUDE (Peristiwa, Id_SOP_Paket, Kode_Karyawan, Id_SOP_Penjadwalan_Detail)` | `tren()` — rentang tanggal. Tabel tercepat tumbuh dan sama sekali belum berindeks pada kolom ini |
| `N_SOP_Kepatuhan_Riwayat` | `(Id_SOP_Penjadwalan_Detail, Peristiwa)` | tanda "Diperbarui" di SOP Saya |
| `N_SOP_Penjadwalan_Detail` | `(Kode_Karyawan, Flag_Aktif, Flag_Cabut_Akses) INCLUDE (Id_SOP_Penjadwalan, Status_Paket, Tanggal_Selesai, Created_At, Updated_At)` | `tugasSaya` — jalur terpanas sisi karyawan |
| `N_SOP_Progress` | `(Id_SOP_Penjadwalan_Detail, Status) INCLUDE (Id_SOP_Paket_Item, Id_SOP_Item_Versi, Waktu_Buka, Waktu_Konfirmasi)` | agregat kartu SOP Saya |
| `N_SOP_Master_Paket` | `(Tanggal_Publish)` | setelah 6d membuatnya sargable |

Indeks yang sudah ada dan diverifikasi memadai, sehingga **tidak** ditambah:
`UQ_N_SOP_Progress(Id_SOP_Penjadwalan_Detail, Id_SOP_Paket_Item, Id_SOP_Item_Versi)`
melayani `NOT EXISTS` pada 6a dan predikat reconcile dengan tepat;
`UX_N_SOP_PjdD_Aktif` terfilter sudah cocok dengan predikat reconcile.

**Ledger DDL.** Sebelum change-id baru dibuat, ledger `database/ddl/status.json` memuat
6 item `TRACKER_PENDING` dan 3 `PENDING` yang belum tuntas. Itu dilaporkan di sini
sesuai aturan agen; penyelesaiannya adalah pekerjaan pemilik project, bukan prasyarat
teknis rancangan ini.

---

## 7. Pengujian

Prinsip yang mengikat seluruh bagian ini: di dev, data SOP terlalu kecil untuk
membuktikan apa pun lewat waktu (`N_SOP_Progress` 1.156 baris,
`N_SOP_Penjadwalan_Detail` 317, `N_SOP_Kepatuhan_Riwayat` 108 pada 2026-09-01). Angka
wall-clock tidak sah sebagai bukti. Yang diuji adalah **struktur** — apa yang berjalan
di dalam transaksi dan apa yang di luar.

Setiap penjaga regresi **wajib pernah terlihat MERAH** pada kode lama sebelum
dinyatakan hijau pada kode baru. Penjaga yang lahir langsung hijau tidak membuktikan
apa-apa.

### 7a. Penjaga batas transaksi

`tests/Feature/LMS/SOP/SopPublishBatasTransaksiTest.php`

- Terbitkan paket berisi dokumen dengan penugasan karyawan. Segera setelah respons,
  tegaskan baris `N_SOP_Progress` untuk versi baru **belum** tersemai dan satu baris
  `N_SYSTEM_Task_Outbox` ber-`Task_Type='SOP_RECONCILE_DOKUMEN'` sudah ada. Pada kode
  lama ini gagal, karena publish menyemai di tempat.
- Jalankan handler-nya, lalu tegaskan progres tersemai dan notifikasi terkirim. Tanpa
  langkah kedua, "belum tersemai" bisa lulus hanya karena tak ada yang pernah terjadi.
- Dokumen yang dirujuk dua item menghasilkan satu intent, bukan dua. Publish yang
  diulang tidak melahirkan intent kedua.
- Karyawan yang terdaftar di beberapa paket perujuk menerima satu notifikasi.

### 7b. Penjaga rollback pada `\Error`

Paksa `TypeError` di tengah mutasi, lalu tegaskan `DB::transactionLevel() === 0`
sesudahnya dan tidak ada baris separuh jadi. Pada kode lama, `catch (\Exception)`
melewatkannya sehingga level tetap 1. Menguji **efek**, bukan teks `catch`-nya.

### 7c. Penjaga jumlah kueri

Menghitung pernyataan lewat `DB::listen()`, dengan ambang dibandingkan pada populasi
nyata:

- Assign 50 karyawan × 3 item wajib: jumlah INSERT harus belasan, bukan ratusan;
  ditambah penegasan 150 baris progress benar-benar terbentuk, supaya angka sempurna
  tidak bisa dicapai operasi yang tak menyentuh apa pun.
- `simpanHalaman()`: paling banyak 2 kueri pada jalur baris-sudah-ada, dan akumulasi
  `Durasi_Aktif_Detik` benar setelah dua panggilan berurutan.
- Ringkasan Master Dokumen: jumlah kueri sama untuk 10 dokumen dan 50 dokumen. Itu cara
  menguji "tidak lagi tumbuh bersama data" tanpa menyemai 2100 baris.

### 7d. Urutan dan disiplin menjalankan

1. Ambil **baseline suite penuh sebelum satu baris pun diubah**.
   `SopReconciliationService` dan `SopDokumenFanoutService` dipakai banyak pemanggil,
   dan riwayat repo ini mencatat satu penjaga baru mematahkan 9 uji lama di 4 berkas
   tanpa ketahuan berhari-hari. Tanpa baseline, kegagalan lama dan baru tidak dapat
   dipisahkan.
2. Verifikasi peramban lewat **vhost**, bukan `artisan serve`. Skenarionya ikut
   berubah: Publish kini balas seketika, sehingga uji menunggu baris task selesai, bukan
   menunggu respons.
3. Kode dan uji ditulis sampai tuntas, lalu **berhenti dan minta persetujuan sebelum
   menjalankan apa pun** — sesuai preferensi pemilik project.

---

## 8. Yang rancangan ini tidak jangkau

- **Bahwa pembaca tidak lagi terblokir.** Hanya terlihat di bawah konkurensi nyata
  dengan RCSI mati. Uji beban sebelumnya menunjukkan p95 modul ini ditentukan antrean
  proses web, bukan basis data; pembuktiannya harus di staging.
- **Bahwa jalur antrean benar-benar berjalan.** Cloud Tasks tidak aktif di dev. Yang
  dapat diuji adalah handler-nya dipanggil langsung.
- **Efek indeks.** Pada tabel berisi puluhan baris, rencana eksekusi tidak berarti.
  Yang dibuktikan adalah indeksnya terbentuk dan kueri tidak lagi membungkus kolom
  dalam fungsi.
- **RCSI.** Menyalakan `READ_COMMITTED_SNAPSHOT` akan menghapus seluruh kelas
  "pembaca antre di belakang penulis" sekaligus — perbaikan tunggal terbesar yang
  tersedia. Sengaja **tidak** dimasukkan: standar transaction butir 12 menyatakan
  isolation level global tidak boleh diubah tanpa review dan pengukuran, dan efeknya
  mengenai seluruh modul yang sudah berjalan di produksi, bukan hanya SOP. Direkomendasikan
  sebagai pekerjaan tersendiri dengan pengukuran tempdb.
- **Performa render frontend.** `SopDetail.vue` (7.774 baris) dan `SopBuilder.vue`
  (6.250 baris) tidak diaudit. Sumbu terpisah.
- **`SopLog` yang menelan kegagalannya sendiri.** Audit yang boleh hilang diam-diam
  layak ditinjau untuk modul tata kelola dokumen, tetapi itu keputusan produk yang
  tidak pantas diselundupkan ke dalam pekerjaan performa.

---

## 9. Risiko

| Risiko | Penanganan |
|---|---|
| Jendela antara "versi terbit" dan "kewajiban tersemai" | Konsekuensi sadar dari keputusan asinkron. Panel Aktivitas membuatnya jujur alih-alih tersembunyi. |
| Intent tertulis tetapi dispatch gagal | Justru alasan outbox dipilih: baris sudah durable dan dapat dipulihkan dispatcher. |
| Penggabungan notifikasi pecah | Dikunci penjaga 7a; itulah sebabnya intent dipasang pada dokumen, bukan paket. |
| Perubahan menyentuh service yang dipakai banyak pemanggil | Baseline suite penuh diambil lebih dulu (7d). `SopReconciliationService` sengaja tidak diubah logikanya. |
| Kunci cache membengkak setelah 6e | Saringan dinormalkan sebelum di-hash; TTL dipersingkat. |
| Publish asinkron sulit dikembalikan | `ProsesReconcileSop` lama dipertahankan, sehingga jalur sinkron masih punya pintu. |

---

## 10. Kriteria selesai

1. Publish paket membalas 202 tanpa menyemai progress; satu baris outbox per dokumen
   tersentuh; progres terlihat di panel Aktivitas sampai selesai.
2. Seluruh 29 titik transaksi memakai `DB::transaction()`; tidak tersisa
   `DB::beginTransaction()` manual di modul SOP.
3. Penjaga 7a dan 7b pernah terlihat merah pada kode lama, lalu hijau pada kode baru.
4. Assign 100 karyawan menerbitkan pernyataan dalam orde satuan, bukan ratusan.
5. Lima indeks terpasang di dev, tercatat di SQL Tracker, dengan status yang benar pada
   ledger DDL.
6. Command pemangkas cache berjalan terjadwal, dan jumlah baris kedaluwarsa pada tabel
   `cache` turun mendekati nol — diverifikasi ulang dengan kueri yang sama seperti
   pengukuran 2026-09-01 (121 baris, 96 kedaluwarsa). P1-7 tidak dinyatakan selesai
   sebelum butir ini selesai.
7. Suite penuh dijalankan dan dibandingkan terhadap baseline; setiap selisih dijelaskan.
