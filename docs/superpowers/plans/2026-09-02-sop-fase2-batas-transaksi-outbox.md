# SOP Fase 2 — Batas Transaksi Publish, Outbox, dan Panel Aktivitas: Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkah memakai sintaks checkbox (`- [ ]`) untuk penelusuran.

**Goal:** Mengeluarkan rekonsiliasi karyawan dari transaksi publish paket, sehingga biaya transaksinya turun dari O(item × karyawan) menjadi O(item).

**Architecture:** `rekonsiliasi()` berhenti mengerjakan apa pun dan hanya menulis satu baris intent ke `N_SYSTEM_Task_Outbox` — sebuah INSERT tunggal yang aman berada di dalam transaksi. Seluruh penyisiran karyawan pindah ke handler outbox, tempat pemotongan per-100 dan jeda antar-potongan milik `SopReconciliationService` akhirnya benar-benar bekerja karena tak ada lagi transaksi luar yang membungkusnya. Publish membalas 202; progres tampil di panel Aktivitas.

**Tech Stack:** Laravel 10, PHP 8, SQL Server (`sqlsrv`), Google Cloud Tasks via `stackkit/laravel-google-cloud-tasks-queue`, PHPUnit, Inertia + Vue 3.

**Spec:** `docs/superpowers/specs/2026-09-01-sop-performa-atomicity-design.md` (§3, §4, §5f)

**Prasyarat:** Fase 1 selesai (`docs/superpowers/plans/2026-09-01-sop-fase1-disiplin-transaksi.md`). Rencana ini mengasumsikan modul SOP sudah tidak menyisakan satu pun `DB::beginTransaction()` manual.

---

## Global Constraints

Berlaku untuk **setiap** tugas. Nilai disalin apa adanya dari kode yang sudah diverifikasi pada 2026-09-01.

- **Jangan jalankan uji atau build tanpa persetujuan eksplisit pemilik project.** `php artisan test` menembak `Web_HRIS` dev dan memperlambat UI yang sedang dipakai orang.
- **`READ_COMMITTED_SNAPSHOT = OFF`** pada `Web_HRIS`. Pembaca antre di belakang penulis; itulah kenapa fase ini ada.
- **Jangan pernah `git add -A` atau `git add .`** — path eksplisit saja.
- **Fase ini tidak menyentuh skema.** Tak ada DDL, tak ada tabel baru. `N_SYSTEM_Task_Outbox` dan `N_HRIS_Async_Tasks` sudah ada dan sudah dipakai produksi.
- **Pesan commit berakhir dengan** `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- **Uji:** namespace `Tests\Feature\LMS\SOP`, extend `Tests\TestCase`, trait `DatabaseTransactions`. Ingat `DB::transactionLevel()` bernilai **1**, bukan 0, di dalam uji.

### Kontrak yang sudah diverifikasi — pakai apa adanya, jangan dikarang ulang

`TaskOutboxService::create(array $data): SystemTaskOutbox`
— wajib: `Task_Type`, `Idempotency_Key`, `Queue_Name`. Sudah dedup sendiri: bila
`Idempotency_Key` sudah ada, baris lama dikembalikan tanpa menulis apa pun, dan
balapan unique-key ditangkap lalu dibaca ulang.

`Task_Url` **wajib** lewat `CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute')`.
`APP_URL` di lingkungan ini ditulis tanpa skema, dan URL tanpa skema membuat Cloud Tasks
menolak **diam-diam** — tasknya tak pernah terbuat dan tak ada galat di layar.
Rute terverifikasi via `php artisan route:list`: `POST api/v1/internal/task-outbox/execute`.

`TaskHandler` (antarmuka): `public function handle(SystemTaskOutbox $outbox, array $payload): ?array`
Lempar `RetryableTaskException` untuk galat sementara, `PermanentTaskException` untuk
yang tak akan pernah berhasil. `Throwable` lain diperlakukan sebagai retryable oleh
`TaskOutboxHandlerController`.

Registrasi handler: `AppServiceProvider.php:28-32`, di dalam singleton `TaskHandlerRegistry`.

`AbstractExportService::TABLE = 'N_HRIS_Async_Tasks'`; status: `STATUS_QUEUED='queued'`,
`STATUS_PROCESSING='processing'`, `STATUS_READY='ready'`, `STATUS_FAILED='failed'`.

Queue: `config('queue.names.penjadwalan_training')` → `penjadwalans-queue`.

### Pola nyata yang disalin (bukan dikarang)

Dari `TrainingSchedulingController.php:1439-1466` — satu-satunya pemakai outbox yang
sudah berjalan di produksi:

```php
$outbox = DB::transaction(function () { /* ...mutasi bisnis... */
    return $this->taskOutboxService->create([
        'Task_Type' => '...',
        'Idempotency_Key' => $key,
        'Queue_Name' => '...',
        'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
        'Payload' => $payload,
        'Max_Attempts' => 3,
        'Source_Module' => 'LMS',
        'Source_Feature' => '...',
    ]);
});

DB::afterCommit(function () use ($outbox): void {
    try {
        $this->taskOutboxDispatcher->dispatchBatch('...:'.Str::uuid(), 1);
    } catch (\Throwable $e) {
        Log::error('Gagal dispatch Outbox setelah commit.', ['outbox_id' => $outbox->Id_Task_Outbox, 'exception' => $e]);
    }
});
```

`dispatchBatch` setelah commit hanya **mempercepat**; kegagalannya tidak fatal karena
dispatcher terjadwal akan memungut baris `QUEUED` yang tertinggal. Karena itu ia
dibungkus try/catch dan tidak pernah menggagalkan respons.

---

## Peta berkas

| Berkas | Peran |
|---|---|
| `app/Services/LMS/SOP/SopReconcileTaskService.php` | **Baru.** Daur hidup baris `N_HRIS_Async_Tasks` untuk rekonsiliasi |
| `app/Services/LMS/SOP/SopReconcileHandler.php` | **Baru.** `TaskHandler` untuk kedua tipe intent |
| `app/Providers/AppServiceProvider.php:28-32` | Registrasi dua `Task_Type` |
| `app/Services/LMS/SOP/SopDokumenFanoutService.php` | `rekonsiliasi()` jadi penulis intent |
| `app/Services/LMS/SOP/SopPaketDraftService.php` | `pendingChanges()` ke-2 keluar dari transaksi |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` | `updateStatusPaket` balas 202; `reconcileSetelahPublish()` jadi intent |
| `app/Services/LMS/SOP/SopReconciliationService.php` | Kait progres opsional; statistik gagal dilaporkan |
| `app/Services/ESS/Activity/Center/ActivityCenterService.php` | Adapter baru + dua `array_merge` |
| `app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php` | Cabang `dismiss` |
| `resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue` | Keadaan "sedang diproses" + poll |
| `tests/Feature/LMS/SOP/SopPublishBatasTransaksiTest.php` | **Baru.** Penjaga utama |
| `tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php` | **Baru.** Penjaga dua pintu |

### Dua tipe intent, satu handler

Ada dua pemicu rekonsiliasi yang berbeda, dan menyamakannya akan salah:

| `Task_Type` | Pemicu | Payload | Perilaku handler |
|---|---|---|---|
| `SOP_RECONCILE_DOKUMEN` | Revisi dokumen diterbitkan | `{id_dokumen, id_versi, alasan, user_id, kecuali_paket}` | Memekarkan ke semua paket perujuk, lalu **satu** notifikasi gabungan per karyawan |
| `SOP_RECONCILE_PAKET` | Paket berubah status menjadi PUBLISHED | `{id_paket, alasan, user_id}` | Rekonsiliasi satu paket itu saja |

Keduanya ditangani satu kelas `SopReconcileHandler` yang bercabang pada
`$outbox->Task_Type`. Satu kelas, karena logika pemekaran dan penggabungan notifikasi
harus punya **satu** penulis — dua kelas akan menyimpang, dan penggabungan notifikasi
adalah hal pertama yang pecah ketika itu terjadi.

---

## Task 1: `SopReconcileTaskService` — daur hidup baris progres

**Files:**
- Create: `app/Services/LMS/SOP/SopReconcileTaskService.php`
- Test: `tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php`

**Interfaces:**
- Consumes: `AbstractExportService::TABLE`, `AbstractExportService::STATUS_*`.
- Produces:
  - `SopReconcileTaskService::TASK_TYPE = 'reconcile-dokumen'`
  - `SopReconcileTaskService::MODULE = 'sop-dokumen'`
  - `mulai(array $payload, int $total, ?string $requestedBy): string`
  - `bumpProgress(string $id, int $processed, int $total): void`
  - `selesai(string $id, array $hasil): void`
  - `gagal(string $id, string $pesan): void`
  - `baris(string $id): ?object`

- [ ] **Step 1: Tulis uji yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopReconcileTaskService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopPanelAktivitasReconcileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daur_hidup_baris_task_rekonsiliasi(): void
    {
        $svc = app(SopReconcileTaskService::class);

        $id = $svc->mulai(['id_dokumen' => 1, 'judul' => 'Dokumen Uji'], 40, 'UJI-KODE');

        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertNotNull($baris, 'Baris task tidak terbentuk.');
        $this->assertSame(SopReconcileTaskService::TASK_TYPE, $baris->Task_Type);
        $this->assertSame(SopReconcileTaskService::MODULE, $baris->Module);
        $this->assertSame(AbstractExportService::STATUS_QUEUED, $baris->Status);
        $this->assertSame(40, (int) $baris->Total_Records);

        $svc->bumpProgress($id, 20, 40);
        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(20, (int) $baris->Processed_Records);
        $this->assertSame(50, (int) $baris->Percent);

        $svc->selesai($id, ['paket' => 3, 'diberitahu' => 12, 'gagal' => []]);
        $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(AbstractExportService::STATUS_READY, $baris->Status);

        $hasil = json_decode($baris->Payload_Json, true)['hasil'] ?? null;
        $this->assertSame(3, $hasil['paket'] ?? null, 'Hasil tidak tersimpan di payload.');
    }
}
```

- [ ] **Step 2: Jalankan uji untuk memastikan ia GAGAL**

Jalankan: `php artisan test --filter=test_daur_hidup_baris_task_rekonsiliasi`
Diharapkan: FAIL — `Class "App\Services\LMS\SOP\SopReconcileTaskService" not found`.

- [ ] **Step 3: Tulis implementasinya**

Salin bentuk `app/Services/LMS/SOP/SopBulkAssignTaskService.php` apa adanya, ganti
konstanta dan awalan id. Jangan meng-`extend` `AbstractExportService`: rekonsiliasi tak
punya `buildDataset()`/`renderFile()`, dan subclassing hanya mewarisi kewajiban yang tak
relevan — alasan yang sama sudah ditulis di kepala `SopBulkAssignTaskService`.

```php
public const TASK_TYPE = 'reconcile-dokumen';
public const MODULE = 'sop-dokumen';

public function makeId(): string
{
    return 'sop-rec-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
}
```

`Format` diisi `'reconcile'`. `Percent` dihitung `(int) floor($processed / max(1,$total) * 100)`.
`selesai()` menulis `$hasil` ke dalam `Payload_Json` di bawah kunci `hasil`, dan menyetel
`Status` ke `STATUS_READY`.

- [ ] **Step 4: Jalankan uji untuk memastikan ia LULUS**

Jalankan: `php artisan test --filter=test_daur_hidup_baris_task_rekonsiliasi`
Diharapkan: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/LMS/SOP/SopReconcileTaskService.php tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php
git commit -m "feat(sop): daur hidup baris task rekonsiliasi di N_HRIS_Async_Tasks

Nol DDL: tabelnya sudah generik lewat Task_Type + Module.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `SopReconcileHandler` — pekerjaan berat pindah ke luar transaksi

**Files:**
- Create: `app/Services/LMS/SOP/SopReconcileHandler.php`
- Modify: `app/Providers/AppServiceProvider.php:28-32`
- Test: `tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php`

**Interfaces:**
- Consumes: `SopReconcileTaskService` (Task 1); `SopDokumenFanoutService::paketPerujuk(int $idDokumen): array`; `SopReconciliationService::reconcilePaket(int $idPaket, string $alasan, ?int $userId, bool $tanpaNotifikasi): array`; `SopNotificationService::notifDokumenDiperbarui(array $kodeList, int $idPaket, string $judulDokumen, array $namaPaket): void`.
- Produces: `SopReconcileHandler implements TaskHandler`, terdaftar untuk `SOP_RECONCILE_DOKUMEN` dan `SOP_RECONCILE_PAKET`.

- [ ] **Step 1: Tulis uji yang gagal**

Tambahkan ke `SopPanelAktivitasReconcileTest`:

```php
public function test_handler_menyemai_progres_untuk_paket_perujuk(): void
{
    $dok = DB::table('N_SOP_Paket_Item as i')
        ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
        ->whereNotNull('i.Id_SOP_Dokumen')
        ->where('i.Flag_Aktif', 'Y')
        ->where('i.Flag_Wajib', 'Y')
        ->first(['i.Id_SOP_Dokumen', 'i.Id_SOP_Paket']);
    if (! $dok) {
        $this->markTestSkipped('Tak ada item wajib bertaut dokumen di data dev.');
    }

    $adaPenugasan = DB::table('N_SOP_Penjadwalan_Detail as d')
        ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
        ->where('pj.Id_SOP_Paket', $dok->Id_SOP_Paket)
        ->where('d.Flag_Aktif', 'Y')->where('d.Flag_Cabut_Akses', 'T')
        ->exists();
    if (! $adaPenugasan) {
        $this->markTestSkipped('Paket perujuk belum punya penugasan aktif di data dev.');
    }

    $taskSvc = app(\App\Services\LMS\SOP\SopReconcileTaskService::class);
    $taskId = $taskSvc->mulai(['id_dokumen' => (int) $dok->Id_SOP_Dokumen], 1, 'UJI');

    $outbox = \App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox::query()->create([
        'Task_Type' => 'SOP_RECONCILE_DOKUMEN',
        'Idempotency_Key' => 'UJI:'.uniqid('', true),
        'Queue_Name' => config('queue.names.penjadwalan_training'),
        'Status' => \App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox::STATUS_QUEUED,
    ]);

    $hasil = app(\App\Services\LMS\SOP\SopReconcileHandler::class)->handle($outbox, [
        'id_dokumen' => (int) $dok->Id_SOP_Dokumen,
        'alasan' => 'VERSI_BARU',
        'user_id' => null,
        'task_id' => $taskId,
    ]);

    $this->assertIsArray($hasil);
    $this->assertArrayHasKey('paket', $hasil, 'Handler tak melaporkan paket yang disentuh.');
    $this->assertGreaterThan(0, $hasil['paket'], 'Paket perujuk tak satu pun direkonsiliasi.');

    $baris = DB::table(AbstractExportService::TABLE)->where('Export_Id', $taskId)->first();
    $this->assertSame(AbstractExportService::STATUS_READY, $baris->Status,
        'Baris task tidak ditutup handler — panel Aktivitas akan menggantung di "memproses".');
}
```

- [ ] **Step 2: Jalankan uji untuk memastikan ia GAGAL**

Jalankan: `php artisan test --filter=test_handler_menyemai_progres_untuk_paket_perujuk`
Diharapkan: FAIL — kelas handler belum ada.

- [ ] **Step 3: Tulis handler-nya**

```php
<?php

namespace App\Services\LMS\SOP;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\PermanentTaskException;
use App\Services\SYSTEM\TaskProcessing\RetryableTaskException;
use App\Services\SYSTEM\TaskProcessing\TaskHandler;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi SOP di luar transaksi mana pun — Fase 2.
 *
 * DI SINILAH pemotongan milik SopReconciliationService akhirnya bekerja.
 * Sebelum fase ini, jalur publish memanggilnya dari DALAM transaksi, sehingga
 * DB::transaction() per potongan menjadi SAVE TRANSACTION dan tak melepas satu
 * lock pun — dan usleep antar-potongan justru menahan lock lebih lama.
 *
 * SATU KELAS UNTUK DUA TIPE. Pemekaran dokumen ke paket dan penggabungan
 * notifikasi harus punya satu penulis; dua kelas akan menyimpang, dan
 * penggabungan notifikasi adalah hal pertama yang pecah ketika itu terjadi.
 */
class SopReconcileHandler implements TaskHandler
{
    public function __construct(
        private SopDokumenFanoutService $fanout,
        private SopReconciliationService $reconcile,
        private SopReconcileTaskService $task,
        private SopNotificationService $notif,
    ) {}

    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        $taskId = (string) ($payload['task_id'] ?? '');
        $alasan = (string) ($payload['alasan'] ?? 'MANUAL');
        $userId = $payload['user_id'] ?? null;

        try {
            $hasil = $outbox->Task_Type === 'SOP_RECONCILE_PAKET'
                ? $this->satuPaket((int) ($payload['id_paket'] ?? 0), $alasan, $userId, $taskId)
                : $this->satuDokumen($payload, $alasan, $userId, $taskId);
        } catch (PermanentTaskException $e) {
            if ($taskId !== '') {
                $this->task->gagal($taskId, $e->getMessage());
            }
            throw $e;
        } catch (\Throwable $e) {
            /* Sementara: jangan tutup baris task sebagai gagal — Cloud Tasks akan
               mencoba lagi, dan menutupnya sekarang membuat panel melaporkan
               kegagalan atas pekerjaan yang masih akan berhasil. */
            throw new RetryableTaskException($e->getMessage(), 0, $e);
        }

        if ($taskId !== '') {
            $this->task->selesai($taskId, $hasil);
        }

        return $hasil;
    }
}
```

Isi `satuDokumen()` memindahkan badan `rekonsiliasi()` yang lama apa adanya: iterasi
`paketPerujuk()` (melewati `kecuali_paket`), panggil
`reconcilePaket($idPaket, $alasan, $userId, tanpaNotifikasi: true)`, kumpulkan
`reopened_kodes`, panggil `bumpProgress()` setelah tiap paket, lalu **satu**
`notifDokumenDiperbarui()` atas kode gabungan yang sudah di-`array_unique`.

`satuPaket()` memanggil `reconcilePaket()` sekali dengan `tanpaNotifikasi: false`.

Dokumen yang sudah tidak ada melempar `PermanentTaskException` — mencobanya lagi tak
akan pernah berhasil.

- [ ] **Step 4: Daftarkan kedua tipe**

Di `app/Providers/AppServiceProvider.php`, di dalam singleton `TaskHandlerRegistry`,
sejajar tiga registrasi yang sudah ada:

```php
$registry->register('SOP_RECONCILE_DOKUMEN', $app->make(\App\Services\LMS\SOP\SopReconcileHandler::class));
$registry->register('SOP_RECONCILE_PAKET', $app->make(\App\Services\LMS\SOP\SopReconcileHandler::class));
```

- [ ] **Step 5: Jalankan uji untuk memastikan ia LULUS**

Jalankan: `php artisan test --filter=SopPanelAktivitasReconcileTest`
Diharapkan: PASS keduanya.

- [ ] **Step 6: Commit**

```bash
git add app/Services/LMS/SOP/SopReconcileHandler.php app/Providers/AppServiceProvider.php tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php
git commit -m "feat(sop): handler outbox rekonsiliasi di luar transaksi

Pemotongan per-100 dan jeda antar-potongan milik SopReconciliationService
akhirnya bekerja di sini: tak ada lagi transaksi luar yang mengubahnya
menjadi savepoint.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `rekonsiliasi()` turun pangkat jadi penulis intent

Inti fase ini. Penjaganya **wajib terlihat MERAH** pada kode lama.

**Files:**
- Modify: `app/Services/LMS/SOP/SopDokumenFanoutService.php` — metode `rekonsiliasi()`
- Test: `tests/Feature/LMS/SOP/SopPublishBatasTransaksiTest.php`

**Interfaces:**
- Consumes: `TaskOutboxService::create()`, `SopReconcileTaskService::mulai()`.
- Produces: `rekonsiliasi(int $idDokumen, string $alasan, ?int $userId, ?int $kecualiPaket = null): array` — **tanda tangan tidak berubah**, tetapi nilai baliknya kini `['outbox_id' => int, 'task_id' => string, 'paket' => int]` alih-alih `['sinkron' => [], 'antri' => [], 'diberitahu' => 0]`. Semua pemanggil wajib diperiksa.

- [ ] **Step 1: Daftar seluruh pemanggil sebelum mengubah apa pun**

```bash
grep -rn "rekonsiliasi(" app --include=*.php
```

Setiap pemanggil yang membaca `['sinkron']`, `['antri']`, atau `['diberitahu']` wajib
disesuaikan di langkah yang sama. Melewatkan satu berarti pesan ke admin menyebut angka
yang tak ada lagi.

- [ ] **Step 2: Tulis penjaga yang gagal**

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batas transaksi publish — penjaga inti Fase 2.
 *
 * Yang diuji adalah STRUKTUR: apa yang berjalan di dalam transaksi dan apa yang
 * di luar. Durasi tidak diuji — data dev terlalu kecil untuk membuktikan apa pun
 * lewat waktu, dan angka wall-clock di sini tidak sah sebagai bukti.
 */
class SopPublishBatasTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rekonsiliasi_hanya_menulis_intent_tidak_menyemai_progres(): void
    {
        $dok = DB::table('N_SOP_Paket_Item')
            ->whereNotNull('Id_SOP_Dokumen')->where('Flag_Aktif', 'Y')->where('Flag_Wajib', 'Y')
            ->first(['Id_SOP_Dokumen']);
        if (! $dok) {
            $this->markTestSkipped('Tak ada item wajib bertaut dokumen di data dev.');
        }

        $progresSebelum = DB::table('N_SOP_Progress')->count();
        $outboxSebelum = SystemTaskOutbox::query()->count();

        $hasil = app(\App\Services\LMS\SOP\SopDokumenFanoutService::class)
            ->rekonsiliasi((int) $dok->Id_SOP_Dokumen, 'VERSI_BARU', null);

        $this->assertSame(
            $progresSebelum,
            DB::table('N_SOP_Progress')->count(),
            'rekonsiliasi() masih menyemai progres di tempat. Seluruh penyisiran '
            .'karyawan harus pindah ke handler, di luar transaksi.'
        );

        $this->assertSame(
            $outboxSebelum + 1,
            SystemTaskOutbox::query()->count(),
            'Tak ada baris intent yang ditulis — pekerjaannya akan hilang.'
        );

        $this->assertArrayHasKey('outbox_id', $hasil);
        $this->assertArrayHasKey('task_id', $hasil);
    }

    public function test_intent_untuk_dokumen_yang_sama_tidak_digandakan(): void
    {
        $dok = DB::table('N_SOP_Paket_Item')
            ->whereNotNull('Id_SOP_Dokumen')->where('Flag_Aktif', 'Y')->where('Flag_Wajib', 'Y')
            ->first(['Id_SOP_Dokumen']);
        if (! $dok) {
            $this->markTestSkipped('Tak ada item wajib bertaut dokumen di data dev.');
        }

        $svc = app(\App\Services\LMS\SOP\SopDokumenFanoutService::class);
        $a = $svc->rekonsiliasi((int) $dok->Id_SOP_Dokumen, 'VERSI_BARU', null);
        $b = $svc->rekonsiliasi((int) $dok->Id_SOP_Dokumen, 'VERSI_BARU', null);

        $this->assertSame(
            $a['outbox_id'],
            $b['outbox_id'],
            'Publish yang diulang melahirkan intent kedua — karyawan akan disisir dua kali '
            .'dan diberi tahu dua kali untuk satu revisi.'
        );
    }
}
```

- [ ] **Step 3: Jalankan penjaga untuk memastikan ia GAGAL**

Jalankan: `php artisan test --filter=SopPublishBatasTransaksiTest`
Diharapkan: **FAIL** pada penegasan pertama — kode lama menyemai progres di tempat.
Bila justru LULUS, **berhenti dan laporkan**: berarti data dev tak punya penugasan aktif
pada paket perujuk sehingga penjaganya tidak mengukur apa pun, dan fiksturnya harus
diperbaiki lebih dulu.

- [ ] **Step 4: Commit penjaga dalam keadaan merah**

```bash
git add tests/Feature/LMS/SOP/SopPublishBatasTransaksiTest.php
git commit -m "test(sop): penjaga batas transaksi publish — merah pada kode lama

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 5: Tulis ulang `rekonsiliasi()` sebagai penulis intent**

```php
public function rekonsiliasi(int $idDokumen, string $alasan, ?int $userId, ?int $kecualiPaket = null): array
{
    $paket = $this->paketPerujuk($idDokumen);
    if ($kecualiPaket !== null) {
        $paket = array_values(array_filter($paket, fn ($p) => $p !== $kecualiPaket));
    }

    if ($paket === []) {
        return ['outbox_id' => null, 'task_id' => null, 'paket' => 0];
    }

    $judul = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Judul');
    $idVersi = (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Id_Versi_Aktif');

    $taskId = app(SopReconcileTaskService::class)->mulai(
        ['id_dokumen' => $idDokumen, 'judul' => $judul ?? 'Dokumen', 'paket' => count($paket)],
        count($paket),
        null,
    );

    $outbox = app(TaskOutboxService::class)->create([
        'Task_Type' => 'SOP_RECONCILE_DOKUMEN',
        'Aggregate_Type' => 'N_SOP_Dokumen',
        'Aggregate_Id' => $idDokumen,
        'Idempotency_Key' => "SOP_RECONCILE:{$idDokumen}:{$idVersi}",
        'Queue_Name' => (string) config('queue.names.penjadwalan_training'),
        'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
        'Payload' => [
            'id_dokumen' => $idDokumen,
            'id_versi' => $idVersi,
            'alasan' => $alasan,
            'user_id' => $userId,
            'kecuali_paket' => $kecualiPaket,
            'task_id' => $taskId,
        ],
        'Max_Attempts' => 3,
        'Created_By' => $userId,
        'Source_Module' => 'LMS',
        'Source_Feature' => 'SopRekonsiliasiDokumen',
    ]);

    return ['outbox_id' => $outbox->Id_Task_Outbox, 'task_id' => $taskId, 'paket' => count($paket)];
}
```

Ambang `penjadwalan_async_ambang` **dicabut dari metode ini**. Ia tetap dipakai jalur
penjadwalan lain; jangan menghapus settingnya.

- [ ] **Step 6: Jalankan penjaga untuk memastikan ia LULUS**

Jalankan: `php artisan test --filter=SopPublishBatasTransaksiTest`
Diharapkan: PASS keduanya.

- [ ] **Step 7: Commit**

```bash
git add app/Services/LMS/SOP/SopDokumenFanoutService.php
git commit -m "refactor(sop): rekonsiliasi jadi penulis intent, bukan mesin kerja

Transaksi publish turun dari O(item x karyawan) menjadi O(item).
Ambang penjadwalan_async_ambang dicabut dari jalur ini; semua lewat antrean.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: `publish()` — keluarkan `pendingChanges()` kedua dari transaksi

**Files:**
- Modify: `app/Services/LMS/SOP/SopPaketDraftService.php:1050-1095` (metode `publish()`)

**Interfaces:**
- Consumes: hasil Task 3.
- Produces: `publish()` mengembalikan `['version' => array, 'pending' => array, 'task_id' => ?string]`.

- [ ] **Step 1: Pindahkan perhitungan kedua ke luar closure**

`pendingChanges()` dipanggil dua kali: baris 1061 untuk **validasi** (wajib di dalam
transaksi) dan baris 1094 hanya untuk **payload respons**. Yang kedua adalah pekerjaan
diff yang berjalan sementara kunci `N_SOP_Master_Paket` dan header draft masih dipegang.

```php
public function publish(int $idPaket, int $userId, string $ringkasan, string $sifatRevisi = 'Y', array $sifatPerDokumen = []): array
{
    $hasil = DB::transaction(function () use (...) {
        // ...seluruh isi lama, TANPA baris terakhir...
        return ['version' => $release];
    });

    // DI LUAR transaksi: diff untuk layar, bukan untuk keputusan.
    $hasil['pending'] = $this->pendingChanges($idPaket, $userId);

    return $hasil;
}
```

- [ ] **Step 2: Pastikan tak ada pemanggil yang bergantung pada bentuk lama**

```bash
grep -rn "->publish(" app --include=*.php | grep -i paketDraft
```

- [ ] **Step 3: Jalankan uji publish yang sudah ada**

Jalankan: `php artisan test --filter=SopBuilderVersi`
Diharapkan: identik dengan baseline Fase 1.

- [ ] **Step 4: Commit**

```bash
git add app/Services/LMS/SOP/SopPaketDraftService.php
git commit -m "perf(sop): hitung pendingChanges kedua di luar transaksi publish

Diff itu hanya mengisi payload respons, tetapi berjalan sementara kunci
paket dan header draft masih dipegang.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Controller — balas 202 dan ubah `reconcileSetelahPublish()` jadi intent

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` — `updateStatusPaket` (sekitar baris 538) dan `reconcileSetelahPublish` (baris 623-641)

**Interfaces:**
- Consumes: `SopPaketDraftService::publish()` (Task 4), `TaskOutboxService::create()`.
- Produces: respons `202` berisi `['task_id' => string|null, 'version' => array, 'pending' => array]`.

- [ ] **Step 1: Ubah `reconcileSetelahPublish()` menjadi penulis intent**

Metode ini kini hanya menulis satu baris intent `SOP_RECONCILE_PAKET`. Hitungan
penugasan dan ambang dihapus seluruhnya — tak ada lagi keputusan sinkron-vs-antre.

```php
private function reconcileSetelahPublish(int $idPaket, ?int $userId): ?string
{
    $nama = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');
    $versi = (int) DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Versi_Paket_Aktif');

    $taskId = app(SopReconcileTaskService::class)->mulai(
        ['id_paket' => $idPaket, 'nama_paket' => $nama ?? 'Paket Dokumen'],
        1,
        Auth::user()?->karyawan?->Kode_Karyawan,
    );

    app(TaskOutboxService::class)->create([
        'Task_Type' => 'SOP_RECONCILE_PAKET',
        'Aggregate_Type' => 'N_SOP_Master_Paket',
        'Aggregate_Id' => $idPaket,
        'Idempotency_Key' => "SOP_RECONCILE_PAKET:{$idPaket}:{$versi}",
        'Queue_Name' => (string) config('queue.names.penjadwalan_training'),
        'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
        'Payload' => ['id_paket' => $idPaket, 'alasan' => 'PAKET_PUBLISH', 'user_id' => $userId, 'task_id' => $taskId],
        'Max_Attempts' => 3,
        'Created_By' => $userId,
        'Source_Module' => 'LMS',
        'Source_Feature' => 'SopPublishPaket',
    ]);

    return $taskId;
}
```

- [ ] **Step 2: Pindahkan panggilan itu KE DALAM transaksi, dan balas 202**

Sekarang `reconcileSetelahPublish()` dipanggil **setelah** commit (baris 549). Karena ia
kini hanya sebuah INSERT intent, ia harus pindah **ke dalam** transaksi — itulah yang
membuat outbox bermakna: bila transaksinya batal, intennya ikut batal, dan bila
transaksinya commit, pekerjaannya sudah durable.

```php
try {
    $hasil = DB::transaction(function () use (...) {
        $r = $publishedRevision
            ? $this->paketDraft->publish(...)
            : $this->paket->updateStatus(...);

        $taskId = $status === SopStatus::PAKET_PUBLISHED
            ? $this->reconcileSetelahPublish($decoded, Auth::user()->Id_Users)
            : null;

        return ['hasil' => $r, 'task_id' => $taskId];
    });
} catch (\DomainException $e) {
    return ResponseHelper::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Log::error('SOP builder updateStatusPaket gagal', ['exception' => $e]);
    return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
}

$this->jejak('PAKET_STATUS', "Status paket diubah menjadi {$status}.", $decoded, null, [
    'status' => $status,
    'revisi_terbit' => $publishedRevision,
    'task_id' => $hasil['task_id'],
]);

DB::afterCommit(fn () => null); // lihat Step 3

return ResponseHelper::success(
    ($hasil['hasil'] ?? []) + ['task_id' => $hasil['task_id']],
    $hasil['task_id']
        ? 'Paket diterbitkan. Kewajiban karyawan sedang disemai di latar — pantau di panel Aktivitas.'
        : "Status paket: {$status}",
    $hasil['task_id'] ? 202 : 200,
);
```

**Catatan penting:** `publish()` sudah memanggil `DB::transaction()` di dalamnya
(Task 4). Bersarang di sini menghasilkan savepoint — dan itu **tidak masalah** sekarang,
karena isi transaksinya sudah O(item). Yang dulu berbahaya bukan bersarangnya, melainkan
rekonsiliasi ribuan karyawan di dalamnya.

- [ ] **Step 3: Picu dispatcher setelah commit**

Ganti baris penanda di Step 2 dengan pola nyata dari `TrainingSchedulingController:1457`:

```php
DB::afterCommit(function () use ($hasil): void {
    try {
        app(TaskOutboxDispatcher::class)->dispatchBatch('sop-reconcile:'.Str::uuid(), 5);
    } catch (\Throwable $e) {
        /* Hanya mempercepat. Dispatcher terjadwal tetap akan memungut baris
           QUEUED yang tertinggal, jadi kegagalan di sini tidak boleh
           menggagalkan respons atas publish yang SUDAH commit. */
        Log::warning('SOP: dispatch outbox rekonsiliasi gagal dipicu', ['exception' => $e]);
    }
});
```

- [ ] **Step 4: Jalankan uji Builder**

Jalankan: `php artisan test --filter=SopBuilder`
Diharapkan: identik dengan baseline, **kecuali** uji yang menegaskan status 200 pada
publish — uji itu wajib diperbarui ke 202 sebagai bagian dari langkah ini, bukan
dilewati.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php
git commit -m "feat(sop): publish paket balas 202, rekonsiliasi jadi intent dalam transaksi

reconcileSetelahPublish() pindah ke DALAM transaksi karena kini hanya
INSERT intent: batal ikut batal, commit berarti pekerjaannya durable.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Panel Aktivitas — empat pintu

`ActivityCenterService` **tidak generik**. Tipe tanpa adapter tak akan pernah tampil.
Empat tempat harus disentuh, dan dua di antaranya pasangan setara yang mudah dikerjakan
separuh.

**Files:**
- Modify: `app/Services/ESS/Activity/Center/ActivityCenterService.php` — adapter baru, `list()` baris 44, `summary()` baris 93
- Modify: `app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php` — cabang `dismiss()` sekitar baris 128-143
- Test: `tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php`

**Interfaces:**
- Consumes: `SopReconcileTaskService::TASK_TYPE`, `AbstractExportService::mapRow()`.
- Produces: item panel bertipe `reconcile` dengan `dismiss_url` `/api/v1/activities/reconcile/{id}/dismiss`.

- [ ] **Step 1: Tulis penjaga dua pintu yang gagal**

Menguji **efek** — daftar dan lencana sama-sama menghitungnya — bukan pemanggilan.

```php
public function test_baris_rekonsiliasi_terhitung_di_daftar_dan_di_lencana(): void
{
    $svc = app(\App\Services\LMS\SOP\SopReconcileTaskService::class);
    $id = $svc->mulai(['id_dokumen' => 1, 'judul' => 'Dokumen Uji'], 5, 'UJI-PANEL');

    $panel = app(\App\Services\ESS\Activity\Center\ActivityCenterService::class)->forUser('UJI-PANEL');

    $daftar = collect($panel->list(50, false))->pluck('id')->all();
    $this->assertContains($id, $daftar, 'Baris rekonsiliasi tak muncul di daftar panel Aktivitas.');

    $ringkas = $panel->summary();
    $this->assertGreaterThan(
        0,
        (int) ($ringkas['running'] ?? $ringkas['total'] ?? 0),
        'Baris rekonsiliasi tak terhitung di lencana. list() dan summary() adalah '
        .'dua pintu setara — mengerjakan salah satunya saja membuat item muncul '
        .'tanpa pernah menghitung, atau sebaliknya.'
    );
}
```

Sesuaikan kunci `$ringkas` dengan bentuk yang benar-benar dikembalikan
`ActivityCenterService::summary()` — baca metodenya lebih dulu, jangan menebak.

- [ ] **Step 2: Jalankan penjaga untuk memastikan ia GAGAL**

Jalankan: `php artisan test --filter=test_baris_rekonsiliasi_terhitung_di_daftar_dan_di_lencana`
Diharapkan: FAIL pada penegasan pertama.

- [ ] **Step 3: Tulis adapter, salin bentuk `bulkAssignItems()`**

`bulkAssignItems()` (baris 290-338) adalah cetakannya. Seperti upload dan bulk assign,
rekonsiliasi **tak punya `File_Path`** — jadi statusnya memakai nilai asli baris; jangan
ikut logika "ready tanpa file = expired" milik `mapRow()`.

Label: `"Rekonsiliasi Dokumen · {judul} · {N} paket"`, ditambah ringkasan hasil bila
`Payload_Json.hasil` sudah terisi. `download_url` dan `retry_url` bernilai `null`:
rekonsiliasi tak menghasilkan berkas, dan percobaan ulang sudah ditangani Cloud Tasks
lewat `Max_Attempts`.

- [ ] **Step 4: Daftarkan di KEDUA `array_merge`**

```php
// baris 44, di dalam list()
$items = array_merge($this->exportItems(), $this->uploadItems(), $this->pdfRenderItems(), $this->bulkAssignItems(), $this->reconcileItems());

// baris 93, di dalam summary()
$items = array_merge($this->exportItems(), $this->uploadItems(), $this->pdfRenderItems(), $this->bulkAssignItems(), $this->reconcileItems());
```

- [ ] **Step 5: Tambahkan cabang `dismiss`**

Di `ActivityCenterController::dismiss()`, sejajar cabang `bulk-assign` yang sudah ada:

```php
if ($type === 'reconcile') {
    app(\App\Services\LMS\SOP\SopReconcileTaskService::class)->dismiss($id);

    return ResponseHelper::success(null, 'Aktivitas ditutup.');
}
```

Tambahkan `dismiss(string $id): void` ke `SopReconcileTaskService` bila belum ada di
Task 1 — salin dari `SopBulkAssignTaskService::dismiss()`.

- [ ] **Step 6: Jalankan penjaga untuk memastikan ia LULUS**

Jalankan: `php artisan test --filter=SopPanelAktivitasReconcileTest`
Diharapkan: PASS semuanya.

- [ ] **Step 7: Commit**

```bash
git add app/Services/ESS/Activity/Center/ActivityCenterService.php app/Http/Controllers/ESS/Activity/Center/ActivityCenterController.php app/Services/LMS/SOP/SopReconcileTaskService.php tests/Feature/LMS/SOP/SopPanelAktivitasReconcileTest.php
git commit -m "feat(sop): rekonsiliasi tampil di panel Aktivitas

ActivityCenterService tidak generik — tipe tanpa adapter tak pernah tampil.
Empat pintu: adapter, list(), summary(), dan cabang dismiss. Penjaganya
menguji efek pada daftar DAN lencana, bukan pemanggilan.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Potongan reconcile yang gagal harus sampai ke layar (§5f)

**Files:**
- Modify: `app/Services/LMS/SOP/SopReconciliationService.php:107-130` (kait progres) dan `tutupReconcile()`
- Modify: `app/Services/LMS/SOP/SopReconcileHandler.php`

**Interfaces:**
- Consumes: `SopReconcileTaskService::bumpProgress()`.
- Produces: `reconcilePaket(int $idPaket, string $alasan = 'MANUAL', ?int $userId = null, bool $tanpaNotifikasi = false, ?\Closure $onProgress = null): array` — parameter kelima **opsional dan berbaku `null`**, sehingga seluruh pemanggil lama berperilaku identik.

- [ ] **Step 1: Tambahkan kait progres opsional**

Di dalam `foreach ($details->chunk(...))`, setelah tiap potongan:

```php
if ($onProgress) {
    $onProgress(($i + 1) * $this->chunkDetail(), $details->count());
}
```

Logika reconcile **tidak diubah** selain ini.

- [ ] **Step 2: Laporkan `$stat['gagal']` ke baris task**

Di handler, sertakan `gagal` pada hasil yang dikirim ke `selesai()`. Bila `gagal > 0`,
label panel harus menyebutnya:

```
"Rekonsiliasi Dokumen · Prosedur Gudang · 3 paket — selesai, 2 potongan gagal"
```

Pembatasan yang senyap adalah pembatasan yang menipu: potongan yang gagal saat ini hanya
mendarat di log, sehingga admin melihat "selesai" atas pekerjaan yang separuh.

- [ ] **Step 3: Jalankan uji reconcile yang sudah ada**

Jalankan: `php artisan test --filter=Reconcile`
Diharapkan: identik dengan baseline — parameter kelima berbaku `null`, jadi pemanggil
lama tak berubah perilakunya.

- [ ] **Step 4: Commit**

```bash
git add app/Services/LMS/SOP/SopReconciliationService.php app/Services/LMS/SOP/SopReconcileHandler.php
git commit -m "feat(sop): potongan reconcile yang gagal dilaporkan ke panel

Sebelumnya hanya mendarat di log, sehingga admin melihat 'selesai' atas
pekerjaan yang separuh.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: `SopBuilder.vue` — keadaan "sedang diproses"

**Files:**
- Modify: `resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue`

**Interfaces:**
- Consumes: respons 202 dari Task 5 berisi `task_id`.
- Produces: —

- [ ] **Step 1: Salin pola poll yang sudah terbukti**

Jangan mengarang mekanisme baru. `SopPenjadwalan.vue:611-622` sudah memakai pola yang
benar dan sudah teruji di produksi: interval 5 detik, **digerbangi
`document.visibilityState`**, dan **berhenti sendiri** saat tak ada lagi yang berjalan.

```js
aturPollingPublish() {
    clearInterval(this.pollPublish);
    if (!this.publishTaskId) return;
    this.pollPublish = setInterval(() => {
        if (document.visibilityState === 'visible') this.periksaPublish();
    }, 5000);
},
```

- [ ] **Step 2: Bersihkan timer saat komponen dilepas**

```js
beforeUnmount() {
    clearInterval(this.pollPublish);
},
```

Timer yang tak dibersihkan terus memanggil server dari halaman yang sudah ditinggalkan —
dan di aplikasi Inertia, halaman ditinggalkan jauh lebih sering daripada tab ditutup.

- [ ] **Step 3: Tampilkan keadaannya**

Tombol Publish menjadi nonaktif berlabel "Menyemai kewajiban…" selama task berjalan,
dengan tautan ke panel Aktivitas. Saat selesai, tampilkan ringkasan hasil. Bila
`gagal > 0`, tampilkan peringatan yang menyebut angkanya — jangan menampilkan "selesai"
saja.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue
git commit -m "feat(sop): Builder tampilkan progres penyemaian setelah publish

Pola poll disalin dari SopPenjadwalan.vue: 5 detik, digerbangi
visibilityState, berhenti sendiri.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Verifikasi akhir terhadap baseline

**Files:**
- Create: `docs/superpowers/plans/baseline/2026-09-02-sesudah-fase2.txt`

**Interfaces:**
- Consumes: baseline Fase 1.
- Produces: —

- [ ] **Step 1: Minta izin menjalankan suite penuh**

- [ ] **Step 2: Jalankan dan bandingkan**

```bash
php artisan test --testsuite=Feature > docs/superpowers/plans/baseline/2026-09-02-sesudah-fase2.txt 2>&1; echo "exit=$?"
diff <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-01-sesudah-fase1.txt | sort) \
     <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-02-sesudah-fase2.txt | sort)
```

Diharapkan: nol kegagalan baru. Uji yang menegaskan publish membalas 200 **harus sudah
diperbarui ke 202 di Task 5** — bila ia baru gagal di sini, Task 5 belum tuntas.

- [ ] **Step 3: Verifikasi manual di peramban lewat vhost**

**Bukan** `php artisan serve`: di Windows ia melayani satu permintaan pada satu waktu,
dan pengujian yang seharusnya 3 menit pernah menjadi 55 menit lalu menggantung — dengan
gejala yang menyamar sebagai "elemen tidak ditemukan".

Terbitkan sebuah paket, lalu pastikan: respons datang seketika; panel Aktivitas
menampilkan barisnya; barisnya berpindah ke selesai; dan kewajiban karyawan benar-benar
tersemai sesudahnya.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/baseline/2026-09-02-sesudah-fase2.txt
git commit -m "test(sop): verifikasi Fase 2 terhadap baseline

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Kriteria selesai Fase 2

1. `rekonsiliasi()` tidak menulis satu baris pun ke `N_SOP_Progress`; penjaga Task 3 pernah terlihat MERAH lalu hijau.
2. Publish paket membalas 202 dengan `task_id`; satu baris outbox per dokumen tersentuh; dedup terbukti oleh penjaga intent-ganda.
3. Baris rekonsiliasi tampil di daftar **dan** terhitung di lencana panel Aktivitas.
4. Karyawan yang terdaftar di beberapa paket perujuk menerima **satu** notifikasi.
5. Potongan yang gagal terbaca di panel, bukan hanya di log.
6. Suite penuh dibandingkan terhadap baseline Fase 1; nol kegagalan baru.
7. Verifikasi peramban lewat vhost dilakukan dan hasilnya dicatat.

## Yang TIDAK dapat dibuktikan di fase ini

- **Bahwa pembaca tidak lagi terblokir.** Hanya terlihat di bawah konkurensi nyata dengan RCSI mati; pembuktiannya di staging.
- **Bahwa Cloud Tasks benar memanggil handler.** Cloud Tasks tidak aktif di dev; yang diuji adalah handler dipanggil langsung. Jalur dispatch sungguhan baru terbukti di staging.
