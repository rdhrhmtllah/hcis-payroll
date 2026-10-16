# Transaction, Atomicity, dan Cloud Tasks

Aturan ini berlaku untuk **kode baru**. Koreksi terhadap transaction legacy dilakukan dalam sesi/task terpisah.

## Arsitektur queue project

Project berjalan di Cloud Run dan tidak mengandalkan proses `queue:work` permanen. Queue Laravel menggunakan:

```text
Laravel Job
  -> Stackkit Cloud Tasks Queue Driver
  -> Google Cloud Tasks
  -> HTTP request ke Cloud Run
  -> task handler
  -> Job::handle()
```

Package queue yang digunakan adalah `stackkit/laravel-google-cloud-tasks-queue`. Direct Google Cloud Tasks API melalui `CloudTaskService` adalah mekanisme terpisah untuk kebutuhan custom/legacy.

## 15 standar transaction

1. Transaction hanya membungkus database mutation inti yang wajib atomic.
2. Transaction harus sesingkat mungkin.
3. HTTP call, upload, email, WhatsApp, PDF, AI, dan proses eksternal/lambat tidak boleh berada di dalam transaction inti.
4. Query persiapan dilakukan sebelum transaction jika aman.
5. Proses eksternal dilakukan setelah commit.
6. Gunakan `afterCommit` atau outbox untuk event/job setelah commit.
7. `lockForUpdate()` hanya digunakan jika race condition benar-benar membutuhkan.
8. Conditional update digunakan untuk status transition sederhana.
9. Mutation endpoint harus idempotent.
10. Proses panjang dipecah menjadi job dan batch kecil.
11. Deadlock boleh di-retry secara terbatas; retry bukan solusi untuk transaction yang terlalu panjang.
12. Isolation level global tidak boleh diubah tanpa review dan pengukuran.
13. Setiap transaction harus memiliki scope data yang jelas.
14. Audit inti yang wajib konsisten disimpan dalam transaction.
15. Audit tambahan/asinkron boleh dilakukan setelah commit.

## Fase A1 — Pemilihan mekanisme task

Untuk kode baru, default adalah:

```text
Laravel Job + Stackkit Cloud Tasks Queue Driver
```

Laravel Job dipakai untuk proses internal yang membutuhkan `handle`, timeout, retry, `failed`, queue khusus, dan integrasi Cloud Run melalui HTTP task handler. Tidak perlu menjalankan `queue:work`.

Direct `CloudTaskService` hanya boleh digunakan untuk endpoint custom/non-standard, payload khusus, service berbeda, delay/queue khusus, atau kebutuhan legacy yang tidak cocok menjadi Laravel Job. Alasan penggunaan direct service harus jelas dan terdokumentasi.

Direct `CloudTaskService::dispatch()` **dilarang dipanggil di dalam transaction** karena API Cloud Tasks dapat memperpanjang lock dan transaction.

Semua Job dan handler Cloud Tasks wajib idempotent karena Cloud Tasks dapat mengirim ulang task. Job baru wajib memiliki timeout, retry, dan failed handling yang sesuai jenis pekerjaannya.

## Fase A2 — `afterCommit`

Job yang di-dispatch dari dalam transaction dan bergantung pada hasil transaction wajib menggunakan `.afterCommit()` secara eksplisit:

```php
DB::transaction(function () use ($data) {
    $record = Model::create($data);

    ProcessJob::dispatch($record->Id_Record)
        ->onConnection('cloudtasks')
        ->afterCommit();
});
```

Konfigurasi connection `cloudtasks` dengan `after_commit = true` dipertahankan sebagai pagar pengaman global. `.afterCommit()` tetap ditulis eksplisit agar dependency terhadap commit terlihat dalam kode dan tetap aman jika connection/configuration berubah.

`afterCommit` mengikuti outermost transaction. Jika transaction luar rollback, Job tidak boleh dikirim. `afterCommit` tidak menggantikan idempotency.

Direct `CloudTaskService` tidak mendapat perlindungan `afterCommit` otomatis. Jika direct service diperlukan, transaction harus selesai lebih dahulu. Untuk proses penting, gunakan task intent/outbox agar task tidak hilang ketika database sudah commit tetapi dispatch gagal.

## Dampak Cloud Run

Desain harus mempertimbangkan perkalian `concurrency x max instances` terhadap kapasitas SQL Server. Transaction panjang, lock luas, query lambat, dan proses eksternal di dalam transaction dapat menumpuk koneksi dan memicu login timeout. `LOCK_TIMEOUT`/query timeout adalah circuit breaker, bukan pengganti perbaikan desain transaction.

`set_time_limit(0)` tidak menghapus timeout Cloud Run, Cloud Tasks, load balancer, atau batas memory/CPU. Proses PDF, export, dan bulk harus dipecah menjadi task/batch yang ukurannya terkendali.

## Fase A3 — Outbox / Task Intent

Untuk task kritis yang tidak boleh hilang setelah transaction bisnis berhasil commit, gunakan tabel durable outbox terpisah:

```text
N_SYSTEM_Task_Outbox
```

Tabel ini menyimpan intent pengiriman, bukan operational log. `N_HRIS_Cloud_Task_Log` tetap dipakai untuk log task yang sudah dikirim/diproses.

### Cukup `afterCommit`

`afterCommit` cukup untuk task turunan/non-kritis jika data utama tetap benar ketika task hilang dan task dapat dipicu ulang dengan aman. Contohnya cache refresh, notifikasi tambahan, dan export yang dapat dibuat ulang.

### Wajib outbox

Outbox wajib untuk task kritis yang tidak boleh hilang, memengaruhi status bisnis berikutnya, memicu integrasi eksternal wajib, memproses dokumen penting, menjalankan bulk mutation, atau membutuhkan recovery otomatis. Direct `CloudTaskService` untuk task kritis wajib memakai outbox.

### Lifecycle dan batas transaction

```text
Transaction:
- ubah data bisnis;
- insert outbox dengan status QUEUED;
- commit.

Dispatcher:
- claim QUEUED/RETRY_PENDING secara singkat;
- commit claim;
- panggil Cloud Tasks API di luar transaction;
- set DISPATCHED atau RETRY_PENDING/FAILED.
```

API Cloud Tasks tidak boleh dipanggil sambil memegang lock claim. `Idempotency_Key` wajib unik. Outbox tidak menjamin exactly-once execution; handler tetap wajib idempotent.

### Status awal

```text
QUEUED, DISPATCHING, DISPATCHED, PROCESSING, COMPLETED, RETRY_PENDING, FAILED, CANCELLED
```

Status divalidasi di application/service layer, bukan rigid database CHECK constraint.

### Provider delivery dan retry

Untuk integrasi provider eksternal seperti WhatsApp, status `DISPATCHED` hanya berarti Cloud Tasks berhasil menerima task. Status tersebut tidak berarti provider sudah menerima pesan.

```text
DISPATCHED
  -> PROCESSING
  -> COMPLETED       provider menerima/menyelesaikan sesuai kontrak bisnis
  -> RETRY_PENDING   kegagalan sementara yang boleh dicoba ulang
  -> FAILED          kegagalan permanen atau retry habis
```

Aturan wajib:

1. Handler harus membedakan kegagalan transport, kegagalan provider sementara, dan kegagalan provider permanen.
2. Kegagalan sementara seperti timeout, HTTP 429, HTTP 500, atau HTTP 503 harus menghasilkan retry yang terbatas.
3. Kegagalan permanen seperti nomor tidak valid, payload ditolak, template tidak diizinkan, atau credential yang tidak valid harus ditandai `FAILED` dan tidak diulang tanpa keputusan baru.
4. Handler tidak boleh mengembalikan HTTP 2xx ketika pekerjaan sebenarnya gagal dan masih perlu retry. HTTP 2xx berarti Cloud Tasks boleh menganggap delivery berhasil.
5. `COMPLETED` hanya boleh dicatat setelah hasil provider memenuhi definisi sukses bisnis, bukan hanya setelah Cloud Task dipanggil.
6. Pemilik retry harus ditentukan per integrasi. Retry transport Cloud Tasks dan retry bisnis Outbox tidak boleh dikalikan tanpa batas.
7. Jumlah maksimum percobaan, backoff, dan klasifikasi error harus didokumentasikan oleh setiap provider integration.
8. Outbox dan Cloud Tasks sama-sama at-least-once; keduanya tidak memberikan exactly-once delivery.
9. Setiap pengiriman provider wajib memakai idempotency key berbasis operasi bisnis, bukan UUID acak per percobaan.
10. Jika provider tidak mendukung idempotency key, aplikasi wajib memiliki guard/status pengiriman sendiri dan menangani kondisi timeout setelah provider mungkin sudah menerima pesan.

Contoh untuk WhatsApp:

```text
Outbox QUEUED
  -> Cloud Task dibuat; Outbox DISPATCHED
  -> handler mulai; Outbox PROCESSING
  -> WhatsApp timeout/429/503; RETRY_PENDING
  -> WhatsApp menerima pesan; COMPLETED + provider message id
  -> nomor invalid/template ditolak; FAILED
```

Cloud Scheduler, jika digunakan, hanya menjadi safety net untuk menemukan outbox `QUEUED`, `RETRY_PENDING`, atau lease `DISPATCHING` yang kadaluarsa. Scheduler bukan jalur wajib untuk setiap task dan bukan pengganti Cloud Tasks.

### Temuan implementasi WhatsApp existing

Inspeksi read-only terhadap jalur existing menemukan beberapa hal yang harus dianggap sebagai gap legacy, bukan langsung diperbaiki dalam fase desain ini:

1. `SendWhatsappJob` saat ini memiliki retry Cloud Tasks, tetapi jalur `performSend()` menangkap exception dan mengembalikan hasil normal. Implementasi baru harus memastikan kegagalan sementara diteruskan sebagai kegagalan handler/non-2xx agar Cloud Tasks benar-benar melakukan retry.
2. `Whatsapp::send_message()` juga memiliki retry HTTP internal. Jumlah retry provider dan retry Cloud Tasks harus dikalibrasi agar tidak menghasilkan retry berlipat dan pengiriman duplicate.
3. `Whatsapp_Log` berfungsi sebagai operational delivery log. Ia tidak boleh dianggap sebagai durable intent/outbox sebelum ada desain dan perubahan schema terpisah.
4. `Whatsapp_Log` existing belum menjadi sumber idempotency bisnis yang lengkap. Idempotency untuk implementasi baru harus berasal dari `N_SYSTEM_Task_Outbox.Idempotency_Key`, lalu dikaitkan ke log/provider result.
5. Rencana lama terkait penambahan kolom idempotency pada `Whatsapp_Log` belum dieksekusi dan tidak menjadi prasyarat pembuatan `N_SYSTEM_Task_Outbox`.

Karena aturan project memisahkan standard baru dari refactor legacy, temuan ini dicatat sebagai pekerjaan implementasi berikutnya. Jalur WhatsApp existing tidak diubah hanya karena dokumentasi ini.

### Implementasi generic tahap pertama

Komponen fondasi yang sudah tersedia:

```text
App\Models\SystemTaskOutbox
App\Services\SYSTEM\TaskProcessing\TaskOutboxService
```

`TaskOutboxService` menyediakan kontrak berikut:

- `create()` membuat intent dengan idempotency key unik dan mengembalikan intent existing jika key yang sama sudah ada;
- `claimBatch()` mengambil batch terbatas dengan lease dan status `DISPATCHING`;
- `markDispatched()` melakukan transition conditional dari `DISPATCHING`;
- `markDispatchRetry()` mengembalikan kegagalan dispatch ke `RETRY_PENDING`;
- `markProcessing()` melakukan transition dari `DISPATCHED`;
- `markCompleted()` hanya menerima transition dari `PROCESSING`;
- `markRetryPending()` dan `markFailed()` mencatat error serta menambah attempt count.

Implementasi ini belum mengirim ke Cloud Tasks dan belum terhubung ke WhatsApp. Dispatcher API, klasifikasi error provider, dan penyimpanan provider result akan dibangun pada tahap berikutnya.

### Dispatcher adapter tahap pertama

Dispatcher Outbox memakai adapter baru dan tidak memanggil `CloudTaskService` legacy secara langsung:

```text
TaskOutboxDispatcher
  -> CloudTaskGateway
      -> GoogleCloudTaskGateway (production)
      -> fake gateway (test)
```

`GoogleCloudTaskGateway` dibuat lazy sehingga client Google hanya dibuat ketika dispatch benar-benar dijalankan. Adapter mengembalikan provider-generated Cloud Task name dan tidak menulis `N_HRIS_Cloud_Task_Log`; status durable tetap dikelola oleh Outbox.

Urutan dispatch:

1. `claimBatch()` menandai row sebagai `DISPATCHING` dalam transaction pendek.
2. Gateway mengirim Cloud Task di luar transaction.
3. Jika berhasil, `markDispatched()` melakukan conditional update berdasarkan status dan worker.
4. Jika gagal sementara, `markDispatchRetry()` menghapus lease dan menjadwalkan `RETRY_PENDING`.
5. Jika attempt maksimum tercapai, `markDispatchFailed()` menandai `FAILED`.

Dispatcher belum diekspos sebagai endpoint HTTP atau Cloud Scheduler job. Endpoint internal dan authentication akan dibahas setelah kontrak dispatcher ini stabil.

### Endpoint internal dispatcher

Endpoint dispatcher Outbox tahap pertama:

```text
POST /api/v1/internal/task-outbox/dispatch
```

Endpoint memakai middleware `outbox.dispatcher.auth` dengan bearer token dari konfigurasi:

```text
OUTBOX_DISPATCHER_BEARER_TOKEN
```

Tidak ada fallback token hardcoded untuk endpoint baru. Jika token belum dikonfigurasi, request selalu ditolak. Parameter yang diterima:

- `limit`: integer `1..100`, default `20`;
- `lease_minutes`: integer `1..60`, default `5`.

Endpoint mengembalikan ringkasan `claimed`, `dispatched`, `retry_pending`, dan `failed`. Ia tidak diekspos ke browser/session dan belum dijadwalkan otomatis oleh Cloud Scheduler. Deployment configuration dan IAM/OIDC Cloud Scheduler harus ditetapkan terpisah sebelum endpoint diaktifkan di lingkungan bersama/production.

### Handler eksekusi Outbox

Endpoint handler generic tahap pertama:

```text
POST /api/v1/internal/task-outbox/execute
```

Handler memakai token terpisah:

```text
OUTBOX_HANDLER_BEARER_TOKEN
```

Bila env-nya kosong, token diturunkan dari `APP_KEY` (HMAC-SHA256) di `config/services.php` — tetap tanpa nilai harfiah di repo, dan server baru tidak perlu mengisinya. Mengganti `APP_KEY` ikut mengganti token; task yang sedang di Cloud Tasks saat itu akan ditolak lalu dicoba ulang.

Payload Cloud Task wajib memiliki metadata yang disisipkan dispatcher:

```json
{
  "_outbox_id": 123,
  "_idempotency_key": "WA:...",
  "_task_type": "EXAMPLE_TASK",
  "data": {}
}
```

`TaskHandlerRegistry` memetakan `Task_Type` ke implementasi `TaskHandler`. Handler bisnis harus didaftarkan secara eksplisit oleh implementasi feature; task type yang belum terdaftar diperlakukan sebagai permanent failure agar tidak menjadi poison task yang retry tanpa batas.

Kontrak hasil:

- sukses: Outbox `PROCESSING` menjadi `COMPLETED`, HTTP `200`;
- `RetryableTaskException`: Outbox menjadi `RETRY_PENDING`, HTTP `503`;
- `PermanentTaskException`: Outbox menjadi `FAILED`, HTTP `200`;
- exception tak dikenal: dianggap kegagalan sementara, Outbox `RETRY_PENDING`, HTTP `503`;
- task final (`COMPLETED`, `FAILED`, `CANCELLED`) yang dikirim ulang dijawab `200` secara idempotent dan tidak dieksekusi lagi.

`Task_Url` pada row Outbox yang memakai handler generic harus menunjuk endpoint execute tersebut. Handler ini belum dihubungkan ke WhatsApp atau task legacy; integrasi provider harus dibuat sebagai handler feature terpisah dan tetap memakai idempotency bisnis.

### Pilot handler non-bisnis

Handler pertama yang didaftarkan adalah:

```text
SYSTEM_OUTBOX_HEALTHCHECK
```

Handler ini hanya memvalidasi bahwa payload diterima dan mengembalikan metadata sederhana. Ia tidak mengirim WhatsApp, tidak memanggil provider eksternal, dan tidak memutasi data bisnis. Tujuannya adalah membuktikan kontrak generic dari row Outbox `DISPATCHED` sampai `COMPLETED` sebelum integrasi provider yang memiliki dampak nyata dibuat.

Pilot dapat ditemukan di:

```text
App\Services\SYSTEM\TaskProcessing\SystemOutboxHealthcheckHandler
tests/Integration/SYSTEM/TaskProcessing/SystemOutboxHealthcheckHandlerTest.php
```

### Pilot WhatsApp Outbox

Handler provider WhatsApp pertama tersedia sebagai task type terpisah:

```text
WHATSAPP_SEND
```

Komponen:

```text
App\Services\SYSTEM\TaskProcessing\WhatsappProviderClient
App\Services\SYSTEM\TaskProcessing\WhatsappOutboxHandler
```

Kontrak response provider:

- HTTP `200` dengan `messages[0].id`: sukses, simpan `Provider_Message_Id`;
- HTTP `408`, `425`, `429`, atau `5xx`: retryable, Outbox `RETRY_PENDING`, HTTP handler `503`;
- HTTP `4xx` lainnya: permanent failure, Outbox `FAILED`, HTTP handler `200`;
- response `2xx` tanpa message ID: permanent failure;
- nomor tujuan tidak valid: permanent failure sebelum HTTP provider dipanggil.

Payload WhatsApp berasal dari `Outbox.Payload`; nomor dinormalisasi melalui kontrak existing `WhatsappLogHelper::normalizeNumber()`. Hasil provider disimpan pada `Provider_Name`, `Provider_Status`, `Provider_Message_Id`, `Provider_Response`, dan `Processed_At`. Metadata agregasi dibawa oleh row Outbox: `Sender_Employee_Code`, `Source_Module`, `Source_Feature`, dan `Message_Template`.

Pilot ini belum mengganti `SendWhatsappJob` atau `WhatsappLogHelper::sendAndLog`. Produksi tidak akan mengirim pesan melalui handler baru sampai feature producer, idempotency key bisnis, token deployment, dan rollout terkontrol disetujui.


## Pilot integrasi LMS reassign

`LMS_REASSIGN_THIRD_PARTY` adalah contoh producer Outbox untuk operasi yang memerlukan response pihak ketiga sebelum update lokal. Producer hanya membaca data dan membuat intent dalam transaction singkat. Handler memanggil endpoint pihak ketiga setelah transaction selesai, lalu melakukan update lokal conditional berdasarkan `Id_Penjadwalan_Integrasi`, `Id_Penjadwalan_Det`, dan `Id_Ujian_Token` lama.

Endpoint user mengembalikan `202 Accepted` dengan `process_id`; frontend tidak boleh menyatakan token baru sudah tersedia sebelum handler selesai. HTTP timeout, 408, 425, 429, dan 5xx bersifat retryable. Response sukses tanpa token atau token lama yang sudah berubah bersifat permanent failure.


## Permit document uploads

Permit menggunakan GCS sebagai storage wajib tanpa fallback public. File permit di-upload sebelum transaction bisnis; transaction hanya menyimpan metadata/path dan data permit. Semua path baru dilacak selama request dan dihapus bila upload berikutnya gagal atau transaction/insert metadata gagal. Setelah commit berhasil, file tidak lagi dianggap cleanup candidate.


## Absensi submitIzin attachment

Lampiran pada `submitIzin()` di-upload ke GCS sebelum transaction bisnis. Nomor transaksi dibuat melalui counter atomik sebelum upload, satu path baru dilacak per request, dan cleanup dijalankan bila insert/approval/commit gagal. Callback WhatsApp memakai `DB::afterCommit()` sehingga provider call tidak menahan lock dan tidak berjalan sebelum data committed.
