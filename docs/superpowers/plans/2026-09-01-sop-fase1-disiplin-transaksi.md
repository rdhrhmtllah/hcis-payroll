# SOP Fase 1 — Disiplin Transaksi dan Error Handling: Rencana Implementasi

> **Untuk pekerja agentik:** SUB-SKILL WAJIB: pakai superpowers:subagent-driven-development (disarankan) atau superpowers:executing-plans untuk mengerjakan rencana ini tugas demi tugas. Langkah memakai sintaks checkbox (`- [ ]`) untuk penelusuran.

**Goal:** Menghapus seluruh `DB::beginTransaction()` manual di modul SOP sehingga rollback dijamin oleh closure, dan membuat kegagalan di modul ini dapat dilacak.

**Architecture:** Dua puluh sembilan titik transaksi manual diganti `DB::transaction(closure)`, yang melakukan rollback pada `Throwable` apa pun lalu melemparkannya kembali. Kerja setelah commit dipindahkan ke luar `try` sehingga kegagalannya tidak lagi mengubah operasi yang sudah berhasil menjadi galat 500. Tidak ada perubahan perilaku yang terlihat pengguna kecuali pada kasus galat.

**Tech Stack:** Laravel 10, PHP 8, SQL Server (`sqlsrv`), PHPUnit, Inertia + Vue 3.

**Spec:** `docs/superpowers/specs/2026-09-01-sop-performa-atomicity-design.md` (§5)

**Fase ini bagian dari tiga:**
1. **Fase 1 (dokumen ini)** — disiplin transaksi & error handling (§5 spec). Mekanis, risiko rendah, membersihkan tanah sebelum Fase 2.
2. Fase 2 — batas transaksi publish + outbox + panel Aktivitas (§3, §4, §5f spec) → `2026-09-02-sop-fase2-batas-transaksi-outbox.md`
3. Fase 3 — performa, DDL indeks, pemangkas cache (§6 spec) → `2026-09-03-sop-fase3-performa-dan-indeks.md`

Urutan ini disengaja: mengubah 29 titik menjadi satu pola lebih dulu membuat Fase 2 — yang **menghapus** transaksi pada `updateStatusPaket` — menjadi perubahan kecil di atas pola yang seragam, bukan perubahan di atas dua puluh sembilan bentuk yang berbeda-beda.

---

## Global Constraints

Berlaku untuk **setiap** tugas di bawah. Nilai disalin apa adanya dari spec dan aturan repo.

- **Jangan jalankan uji atau build tanpa persetujuan eksplisit pemilik project.** Tulis kode dan uji sampai tuntas, lalu berhenti dan minta izin. `php artisan test` menembak basis data dev (`Web_HRIS` di `team311.dyndns.info:1433`) dan mengunci UI yang sedang dipakai orang.
- **Basis data dev tanpa RCSI.** `READ_COMMITTED_SNAPSHOT = OFF`. Uji yang menahan transaksi lama akan memblokir pengguna lain.
- **Jangan pernah `git add -A` atau `git add .`** — tambahkan berkas dengan path eksplisit. Repo ini pernah dua kali menyapu service account key Google Cloud ke dalam commit, sekali sampai ke `master`.
- **Jangan jalankan DROP, TRUNCATE, atau migration apa pun.** Fase ini tidak menyentuh skema sama sekali.
- **Pesan commit berakhir dengan** `Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>`.
- **Namespace uji:** `Tests\Feature\LMS\SOP`, meng-extend `Tests\TestCase`, memakai trait `Illuminate\Foundation\Testing\DatabaseTransactions`.
- **`DatabaseTransactions` membuka satu transaksi sebelum tiap uji.** Karena itu `DB::transactionLevel()` **bernilai 1, bukan 0**, di dalam uji. Setiap penegasan tentang level transaksi wajib dibandingkan terhadap baseline yang ditangkap di awal uji — tidak pernah terhadap angka 0 harfiah.
- **Modul pemilik:** LMS → SOP → Tata Kelola → layer Controller/Service. Tidak ada berkas baru di luar pohon itu.

### Pola sasaran — satu bentuk untuk seluruh 29 titik

```php
// SEBELUM
DB::beginTransaction();
try {
    $hasil = $this->service->kerja(...);
    DB::commit();
    $this->jejak('AKSI', '...');            // kerja setelah commit, DI DALAM try
    return ResponseHelper::success($hasil);
} catch (\DomainException $e) {
    DB::rollBack();
    return ResponseHelper::error($e->getMessage(), 422);
} catch (\Exception $e) {                    // \Error LOLOS dari sini
    DB::rollBack();
    Log::error($e->getMessage());            // stack trace dibuang
    return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
}

// SESUDAH
try {
    $hasil = DB::transaction(fn () => $this->service->kerja(...));
} catch (\DomainException $e) {
    return ResponseHelper::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Log::error('SOP <namaMetode> gagal', ['exception' => $e]);
    return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
}

$this->jejak('AKSI', '...');                 // kerja setelah commit, DI LUAR try
return ResponseHelper::success($hasil);
```

Tiga hal yang berubah dan **tidak boleh** dilewatkan pada satu titik pun:

1. `DB::beginTransaction()`/`commit()`/`rollBack()` hilang seluruhnya; closure yang mengurus.
2. `catch (\Exception)` menjadi `catch (\Throwable)`.
3. `Log::error($e->getMessage())` menjadi `Log::error('<konteks>', ['exception' => $e])`.

Yang **tidak** berubah: kode status HTTP, isi pesan yang dilihat pengguna, cabang `\DomainException` yang mengembalikan 422, dan urutan validasi sebelum transaksi.

---

## Peta berkas

| Berkas | Peran di fase ini | Titik |
|---|---|---|
| `app/Http/Controllers/LMS/SOP/Governance/SopApqc/SopApqcController.php` | Konversi pola; berkas terkecil, dipakai membuktikan polanya | 3 |
| `app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php` | Konversi pola | 4 |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` | Konversi pola + dua `catch` senyap | 4 |
| `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` | Konversi pola + empat titik kerja-setelah-commit | 18 |
| `app/Services/LMS/SOP/SopRepositoryReadService.php` | Persempit `catch (\Throwable)` menjadi pelanggaran unik | 1 |
| `tests/Feature/LMS/SOP/SopDisiplinTransaksiTest.php` | **Baru.** Penjaga rollback pada `\Error` | — |

### Bagian spec yang sengaja TIDAK dikerjakan di fase ini

- **§5f — potongan reconcile yang gagal harus sampai ke layar.** Bergantung pada
  baris Async_Task yang baru lahir di Fase 2; mengerjakannya sekarang berarti membangun
  pelaporan ke tempat yang belum ada.
- **§5b titik keempat — `reconcileSetelahPublish()` (baris 549).** Hilang dengan
  sendirinya di Fase 2 ketika berubah menjadi intent di dalam transaksi.
- **Seluruh §3, §4, §6.** Fase 2 dan Fase 3.

---

---

## CATATAN PELAKSANAAN (2026-09-01) — baca sebelum mengeksekusi ulang

Fase ini **sudah dikerjakan**. Tiga hal berjalan tidak sesuai rencana, dan dicatat di
sini supaya siapa pun yang membaca ulang tidak mengulangi jalannya.

**1. Suite tidak bisa dijalankan sekaligus — hang-nya KUMULATIF.**
`php artisan test --testsuite=Feature` dan bahkan `php artisan test tests/Feature/LMS/SOP`
mati dengan `Maximum execution time of 300 seconds exceeded` di `Connection.php`.
Bukan satu berkas rusak: `SopApqcTest` menggantung sebagai berkas ke-9 berturut-turut,
tetapi **lulus penuh dalam 20 detik bila dijalankan sendiri**. Cocok dengan RCSI mati —
transaksi uji menumpuk sampai pembaca berikutnya menabrak batas waktu PHP.
**Cara menjalankan yang berhasil: satu berkas satu proses**, dalam perulangan. Jauh lebih
lambat, dan berarti CI tidak dapat menjalankan suite ini apa adanya.

**2. `SopAturanApplyTest` menggantung bahkan sendirian.** Ia membangun dunia lalu
menerbitkan paket — jalur `publish` yang menjadi temuan P0-1 — di dalam
`DatabaseTransactions`, sehingga pemotongan di dalamnya lumpuh jadi savepoint. Dikecualikan
dari verifikasi Fase 1.

> **TERJAWAB, dan ini bukti terkuat P0-1 yang ada.**
> Diuji ulang sesudah Fase 2, tanpa satu baris pun mengubah uji itu sendiri:
>
> | | Sebelum Fase 2 | Sesudah Fase 2 |
> |---|---|---|
> | `SopAturanApplyTest` | **HANG** — mati di batas 300 dtk, nol nama uji tercetak | **10 lulus, 231 dtk** |
>
> Uji ini tidak diperbaiki; ia **sembuh sendiri** ketika rekonsiliasi keluar dari
> transaksi publish. Itu memenuhi syarat pembuktian yang paling sulit dipenuhi di
> pekerjaan ini: sebuah kegagalan yang ada SEBELUM perbaikan, hilang SESUDAHNYA,
> tanpa menyentuh alat ukurnya. Semua angka lain di ketiga fase mengukur jumlah
> kueri; yang ini mengukur akibatnya — pekerjaan yang tak pernah selesai, kini
> selesai.

**3. Urutan Task 1 dilanggar.** Tiga tugas dikerjakan dan di-commit sebelum baseline
selesai, demi kecepatan. Diperbaiki dengan mengembalikan berkas ke commit pra-perubahan
(`git checkout <commit> -- <berkas>`), menjalankan uji, lalu memulihkannya — pola yang
sama dipakai untuk membuktikan satu kegagalan bersifat pra-existing. Pola itu bekerja dan
layak dipakai lagi bila baseline terlanjur terlewat, **tetapi mengambil baseline lebih
dulu tetap lebih murah.**

**Hasil verifikasi:** 13 berkas dijalankan, 12 lulus, 1 gagal
(`SopBuilderSaringanPaketTest > sumbu tak dikenal ditolak`) — **terbukti pra-existing**,
hasilnya identik pada kode pra-perubahan.

---

## Task 1: Ambil baseline suite sebelum menyentuh apa pun

`SopReconciliationService` dan `SopDokumenFanoutService` dipakai banyak pemanggil. Riwayat repo ini mencatat satu penjaga baru mematahkan 9 uji lama di 4 berkas dan baru ketahuan berhari-hari kemudian. Tanpa baseline, kegagalan lama dan baru tidak dapat dipisahkan — dan diketahui sudah ada sekitar 19 kegagalan non-SOP yang memang sudah berdiri sebelum pekerjaan ini.

**Files:**
- Create: `docs/superpowers/plans/baseline/2026-09-01-baseline-suite.txt`

**Interfaces:**
- Consumes: —
- Produces: berkas baseline yang dibaca Task 11 untuk membandingkan hasil akhir.

- [ ] **Step 1: Minta izin menjalankan suite**

Berhenti di sini. Sampaikan ke pemilik project: "Baseline harus diambil sebelum satu baris pun diubah. Ini menjalankan suite penuh terhadap DB dev (perkiraan 2-4 menit) dan akan memperlambat UI selama itu. Boleh dijalankan?" Jangan lanjut tanpa jawaban ya.

- [ ] **Step 2: Jalankan suite penuh dan simpan keluarannya**

```bash
php artisan test --testsuite=Feature > docs/superpowers/plans/baseline/2026-09-01-baseline-suite.txt 2>&1; echo "exit=$?"
```

Keluaran diharapkan: sejumlah kegagalan yang **sudah ada sebelumnya**. Ini bukan kesalahan; ini yang sedang direkam.

- [ ] **Step 3: Ringkas jumlahnya di kepala berkas**

Tambahkan tiga baris di paling atas berkas baseline: tanggal, commit HEAD (`git rev-parse --short HEAD`), dan jumlah lulus/gagal. Tanpa ini berkasnya tak bisa dipakai membandingkan apa pun.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/baseline/2026-09-01-baseline-suite.txt
git commit -m "test(sop): rekam baseline suite sebelum Fase 1

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Penjaga rollback pada `\Error`

Ini penjaga inti fase ini. Ia harus **terlihat MERAH pada kode lama** sebelum satu titik pun dikonversi — penjaga yang lahir langsung hijau tidak membuktikan apa-apa.

**Files:**
- Create: `tests/Feature/LMS/SOP/SopDisiplinTransaksiTest.php`

**Interfaces:**
- Consumes: `SopApqcService::create(array $data, int $userId): int` — terverifikasi di `app/Services/LMS/SOP/SopApqcService.php:128`. Kelasnya tidak `final`, sehingga dapat diperluas kelas anonim. Tanda tangan override **wajib sama persis**, kalau tidak PHP melempar galat fatal saat uji dimuat.
- Endpoint: `POST /api/v1/sop/admin/apqc/store`, digerbangi middleware `check.jabatan:menuSopApqcPage` (`routes/LMS/SOP/Governance/SopGovernance.php:54-62`).
- Validasi berjalan **sebelum** transaksi: `Kode_Proses` wajib, `max:10`, hanya `[A-Za-z0-9._-]`; `Nama_Proses` wajib, `max:200`. Payload yang melanggar berhenti di 422 dan tidak pernah mencapai service — ujinya lalu lulus tanpa menguji apa pun.
- Produces: `SopDisiplinTransaksiTest` — dijalankan ulang setelah Task 3 untuk membuktikan hijau.

- [ ] **Step 1: Tulis uji yang gagal**

Uji ini menukar `SopApqcService` di container dengan ganda yang melempar `\TypeError` — sebuah `\Error`, **bukan** `\Exception`, sehingga `catch (\Exception)` pada kode lama tidak menangkapnya. Yang ditegaskan adalah **efeknya**: level transaksi kembali ke baseline, dan tak ada baris tersisa.

```php
<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopApqcService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Disiplin transaksi modul SOP — Fase 1.
 *
 * Menguji EFEK, bukan teks `catch`-nya. `\Error` (TypeError, ArgumentCountError,
 * DivisionByZeroError) BUKAN turunan `\Exception`, sehingga `catch (\Exception)`
 * melewatkannya: `DB::rollBack()` tak pernah dipanggil dan transaksi tetap
 * terbuka selama sisa request — termasuk selagi Laravel merender exception dan
 * menulis log. Pada basis data tanpa RCSI, transaksi yang menggantung seperti
 * itu memblokir pembaca lain.
 *
 * CATATAN PENTING: DatabaseTransactions membuka satu transaksi sebelum tiap
 * uji, jadi transactionLevel() bernilai 1 di sini, bukan 0. Baseline karena itu
 * DITANGKAP, tidak diasumsikan.
 */
class SopDisiplinTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    private function adminApqc(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopApqcPage'));
    }

    public function test_error_di_tengah_mutasi_tidak_meninggalkan_transaksi_menggantung(): void
    {
        $admin = $this->adminApqc();
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopApqcPage di data dev.');
        }

        $this->app->bind(SopApqcService::class, fn () => new class extends SopApqcService
        {
            public function create(array $data, int $userId): int
            {
                throw new \TypeError('galat tipe yang disengaja untuk uji');
            }
        });

        $levelSebelum = DB::transactionLevel();
        $jumlahSebelum = DB::table('N_SOP_Master_APQC')->count();

        // Kode_Proses dibatasi max:10 — payload lebih panjang berhenti di 422
        // dan tak pernah mencapai service, sehingga ujinya lulus tanpa menguji apa pun.
        $res = $this->actingAs($admin)->postJson('/api/v1/sop/admin/apqc/store', [
            'Kode_Proses' => 'UJI-DIS-1',
            'Nama_Proses' => 'Uji disiplin transaksi',
            'Urutan' => 999,
        ]);

        /* Penjaga kerja-nyata: buktikan permintaannya BENAR-BENAR sampai ke
           service. Bila validasi menolaknya (422) atau izinnya kurang (403),
           penegasan di bawah akan lulus tanpa satu pun transaksi pernah dibuka
           — hijau palsu yang paling mudah masuk ke uji semacam ini.
           Kode lama (TypeError lolos ke handler Laravel) dan kode baru
           (ditangkap \Throwable) sama-sama menghasilkan 500. */
        $res->assertStatus(500);

        $this->assertSame(
            $levelSebelum,
            DB::transactionLevel(),
            'Transaksi menggantung setelah \\Error: rollBack() tak pernah dipanggil. '
            .'Inilah yang terjadi ketika sebuah titik hanya menangkap \\Exception.'
        );

        $this->assertSame(
            $jumlahSebelum,
            DB::table('N_SOP_Master_APQC')->count(),
            'Ada baris tersisa setelah \\Error — mutasinya tidak di-rollback.'
        );
    }
}
```

- [ ] **Step 2: Jalankan uji untuk memastikan ia GAGAL**

Minta izin lebih dulu bila belum diberikan di Task 1 untuk sesi ini.

Jalankan: `php artisan test --filter=test_error_di_tengah_mutasi_tidak_meninggalkan_transaksi_menggantung`

Diharapkan: **FAIL** pada penegasan pertama, dengan level transaksi lebih tinggi dari baseline. Bila uji ini justru LULUS pada kode lama, **berhenti dan laporkan** — berarti asumsi tentang titik ini keliru dan sisa rencana perlu ditinjau, bukan diteruskan.

- [ ] **Step 3: Commit ujinya dalam keadaan merah**

Uji merah di-commit lebih dulu supaya merahnya terekam di riwayat, bukan hanya diklaim.

```bash
git add tests/Feature/LMS/SOP/SopDisiplinTransaksiTest.php
git commit -m "test(sop): penjaga rollback pada \\Error — merah pada kode lama

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Konversi `SopApqcController` (3 titik)

Berkas terkecil, dipakai membuktikan polanya sebelum diterapkan ke berkas besar. Task 2 menjadi hijau di akhir tugas ini.

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopApqc/SopApqcController.php` — metode `store` (baris 82), `update` (baris 114), `destroy` (baris 144)
- Test: `tests/Feature/LMS/SOP/SopDisiplinTransaksiTest.php`, `tests/Feature/LMS/SOP/SopApqcTest.php`

**Interfaces:**
- Consumes: pola sasaran dari Global Constraints.
- Produces: tidak ada antarmuka baru; tanda tangan metode tidak berubah.

- [ ] **Step 1: Konversi ketiga metode ke pola sasaran**

Terapkan pola dari Global Constraints. Pertahankan setiap cabang `\DomainException` yang sudah ada beserta kode status dan pesannya. Pindahkan kerja apa pun yang berada setelah `DB::commit()` ke luar blok `try`.

Konteks yang dipakai di `Log::error`: `'SOP apqc store gagal'`, `'SOP apqc update gagal'`, `'SOP apqc destroy gagal'`.

- [ ] **Step 2: Jalankan penjaga untuk memastikan ia kini HIJAU**

Jalankan: `php artisan test --filter=test_error_di_tengah_mutasi_tidak_meninggalkan_transaksi_menggantung`
Diharapkan: **PASS**. Inilah pembuktian bahwa penjaganya benar-benar mengukur perubahan ini.

- [ ] **Step 3: Jalankan uji APQC yang sudah ada untuk memastikan tak ada regresi**

Jalankan: `php artisan test --filter=SopApqcTest`
Diharapkan: hasil identik dengan baseline Task 1 untuk berkas ini.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopApqc/SopApqcController.php
git commit -m "refactor(sop): DB::transaction() gantikan begin/commit manual di APQC

Menutup celah \\Error pada 3 titik: catch (\\Exception) melewatkan
TypeError sehingga rollBack() tak pernah jalan.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Konversi `SopJenisDokumenController` (4 titik)

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php` — `store` (146), `update` (179), `reorder` (221), `destroy` (243)

**Interfaces:**
- Consumes: pola sasaran dari Global Constraints.
- Produces: tanda tangan tidak berubah.

- [ ] **Step 1: Konversi keempat metode**

Pola sama persis dengan Task 3. Konteks log: `'SOP jenis-dokumen store gagal'`, `'... update gagal'`, `'... reorder gagal'`, `'... destroy gagal'`.

Perhatikan `reorder` (221) dan `destroy` (243): keduanya hanya menangkap `\Exception` tanpa cabang `\DomainException`. Tambahkan cabang `\DomainException` → 422 hanya bila layanannya memang melempar `\DomainException`; jangan menambah cabang yang tak pernah tercapai. Periksa `SopJenisDokumenService` lebih dulu.

- [ ] **Step 2: Jalankan uji terkait**

Jalankan: `php artisan test --filter=JenisDokumen`
Diharapkan: identik dengan baseline.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php
git commit -m "refactor(sop): DB::transaction() gantikan begin/commit manual di Jenis Dokumen

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Konversi `SopAdminDokumenController` (4 titik) dan buka dua `catch` senyap

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php` — `storeVersi` (1244), `updateVersi` (1412), `pindahTahapVersi` (2158), `simpanReviewVersi` (2243); serta `catch` senyap di baris 1696 dan 1708

**Interfaces:**
- Consumes: pola sasaran dari Global Constraints.
- Produces: tanda tangan tidak berubah.

- [ ] **Step 1: Konversi keempat titik transaksi**

Konteks log: `'SOP dokumen storeVersi gagal'`, `'... updateVersi gagal'`, `'... pindahTahapVersi gagal'`, `'... simpanReviewVersi gagal'`.

`updateVersi` (1412) memiliki `rollBack()` pada baris 1416 sebagai **gerbang awal**, bukan penanganan galat — di sana kode memeriksa prasyarat lalu membatalkan. Pada pola baru, gerbang itu menjadi `throw new \DomainException(...)` di dalam closure, yang otomatis me-rollback dan ditangkap cabang 422. Jangan menyalin `rollBack()` manualnya.

- [ ] **Step 2: Beri konteks pada dua `catch` senyap**

Baris 1696 dan 1708 saat ini berisi `// ignore`. Perilakunya **tetap** (field bernilai null, halaman tetap tampil), tetapi kegagalannya berhenti tak terlihat:

```php
} catch (\Throwable $e) {
    /* Viewer konten opsional: kegagalannya membuat blok berkas kosong, bukan
       menggagalkan halaman. Tetap dicatat — kegagalan yang tak terlihat adalah
       kegagalan yang tak pernah diperbaiki. */
    Log::warning('SOP detail versi: konten tak dapat dimuat', [
        'id_versi' => $decoded,
        'exception' => $e,
    ]);
}
```

Untuk baris 1708 gunakan pesan `'SOP detail versi: langkah tahap tak dapat dimuat'` dengan kunci yang sama.

- [ ] **Step 3: Jalankan uji terkait**

Jalankan: `php artisan test --filter=SopDetailVersiPayloadTest`
Diharapkan: identik dengan baseline. Uji ini menyentuh payload yang sama dengan dua `catch` di atas, jadi ia yang paling mungkin menangkap kekeliruan.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php
git commit -m "refactor(sop): DB::transaction() + buka catch senyap di Admin Dokumen

4 titik transaksi dikonversi. Dua catch '// ignore' kini mencatat
Log::warning berkonteks; perilakunya tetap, kegagalannya tak lagi
tak terlihat.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Konversi `SopAdminBuilderController` (18 titik)

Berkas terbesar dan paling banyak titiknya. Dikerjakan sebagai satu tugas karena satu berkas, tetapi diperiksa per metode.

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php`

**Interfaces:**
- Consumes: pola sasaran dari Global Constraints.
- Produces: tanda tangan tidak berubah.

Delapan belas titik beserta metode pemiliknya:

| Metode | Baris | Catatan |
|---|---|---|
| `storePaket` | 378 | — |
| `updatePaket` | 423 | Berisi hapus GCS setelah commit — ditangani Task 7, **jangan** disentuh di sini |
| `updateStatusPaket` | 538 | Sudah punya penanda `$committed`; hapus penanda itu, closure menggantikannya. Fase 2 akan menghapus transaksinya sama sekali |
| `destroyPaket` | 731 | — |
| `storeItem` | 1006 | Dua `DB::commit()` pada dua cabang; keduanya menjadi satu closure yang mengembalikan nilai |
| `updateItem` | 1209 | Titik pertama dari tiga di metode ini |
| `updateItem` | 1267 | Titik kedua |
| `updateItem` | 1373 | Titik ketiga |
| `clonePaket` | 1399 | — |
| `cloneSection` | 1434 | Berisi query setelah commit — ditangani Task 7 |
| `cloneItem` | 1473 | Berisi query setelah commit — ditangani Task 7 |
| `copyItemToPaket` | 1810 | — |
| `reorderItem` | 1885 | — |
| `destroyItem` | 1922 | — |
| `storeSection` | 1958 | — |
| `updateSection` | 2010 | — |
| `reorderSection` | 2066 | — |
| `destroySection` | 2102 | — |

- [ ] **Step 1: Konversi kedelapan belas titik**

Terapkan pola sasaran. Konteks log memakai bentuk `'SOP builder <namaMetode> gagal'`.

Untuk `storeItem` (1006) yang punya dua `DB::commit()` pada dua cabang, closure mengembalikan nilai yang dibutuhkan kedua cabang, dan pemilihan bentuk respons terjadi **setelah** closure:

```php
try {
    $hasil = DB::transaction(function () use (...) {
        if ($published) {
            $newId = $this->paketDraft->createItem(...);
            if (! $newId) {
                throw new \RuntimeException('Item draft tidak dapat dibuat.');
            }
            return ['draft' => true, 'id' => $newId];
        }

        $newId = $this->item->create($decodedPaket, $data, Auth::user()->Id_Users);
        if (SopTipeItem::isVersioned($data['Tipe_Item'])) {
            $this->item->tautkanDokumen($newId, (int) $data['Id_SOP_Dokumen'], Auth::user()->Id_Users);
        }
        return ['draft' => false, 'id' => $newId];
    });
} catch (\DomainException $e) {
    return ResponseHelper::error($e->getMessage(), 422);
} catch (\Throwable $e) {
    Log::error('SOP builder storeItem gagal', ['exception' => $e]);
    return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
}

$token = $hasil['draft']
    ? 'di_'.Hashids::connection('custom')->encode($hasil['id'])
    : Hashids::connection('custom')->encode($hasil['id']);

$this->jejak('ITEM_BUAT', "Item \"{$data['Judul_Item']}\" ditambahkan.", $decodedPaket,
    $hasil['draft'] ? null : $hasil['id'],
    ['tipe' => $data['Tipe_Item']] + ($hasil['draft'] ? ['id_draft_item' => $hasil['id']] : []));

return ResponseHelper::success(['Id_SOP_Paket_Item' => $token], 'Item berhasil ditambahkan!', 201);
```

Untuk `updateStatusPaket` (538), hapus variabel `$committed` dan kedua pemeriksaan `if (! $committed)`. Closure sudah menjamin rollback, dan menahan penanda itu berarti mempertahankan mekanisme yang kini tak punya tugas.

- [ ] **Step 2: Pastikan tak ada sisa transaksi manual di modul SOP**

```bash
grep -rn "DB::beginTransaction\|DB::commit\|DB::rollBack" app --include=*.php | grep -i sop
```

Diharapkan: **tanpa keluaran**. Satu baris pun yang tersisa berarti ada titik yang terlewat.

- [ ] **Step 3: Jalankan uji Builder yang sudah ada**

Jalankan: `php artisan test --filter=SopBuilder`
Diharapkan: identik dengan baseline. Ini kumpulan uji terbesar yang menyentuh berkas ini; setiap selisih terhadap baseline wajib dijelaskan sebelum lanjut.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php
git commit -m "refactor(sop): DB::transaction() gantikan begin/commit manual di Builder

18 titik dikonversi; penanda \$committed pada updateStatusPaket dihapus
karena closure sudah menjamin rollback. Modul SOP kini tak menyisakan
satu pun DB::beginTransaction() manual.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Keluarkan kerja setelah commit dari blok `try`

Cakupan tugas ini **empat titik**, bukan sembilan belas. `SopLog::catat()` sudah membungkus insert-nya sendiri dengan `catch (\Throwable)` (`SopLog.php:56`), sehingga `jejak()` tidak dapat melempar dan tak dapat mengubah operasi yang berhasil menjadi 500. Lihat §1.3 spec.

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` — baris 433 (`updatePaket`), 1444 (`cloneSection`), 1483 (`cloneItem`)

**Interfaces:**
- Consumes: hasil Task 6 (berkas sudah memakai pola closure).
- Produces: tanda tangan tidak berubah.

Titik keempat, `reconcileSetelahPublish()` pada baris 549, **sengaja tidak disentuh di sini**: ia hilang dengan sendirinya di Fase 2 ketika berubah menjadi intent di dalam transaksi. Menyentuhnya sekarang berarti menulis kode yang akan dihapus minggu depan.

- [ ] **Step 1: Jadikan hapus GCS best-effort (baris 433)**

Panggilan `Storage::disk('gcs')->delete($oldCover)` berjalan setelah commit. GCS pada project ini pernah menolak seluruh operasi objek dengan 403 karena tagihan; ketika itu terjadi, pembaruan paket yang **sudah tersimpan** dilaporkan ke admin sebagai "Terjadi Kesalahan Sistem".

Gunakan pola yang **sudah ada** di `SopBerkasService.php:72-76`, jangan mengarang pola baru:

```php
// setelah closure transaksi, DI LUAR try
if (! $published && $request->has('Cover') && $oldCover && $oldCover !== $coverBaru) {
    try {
        Storage::disk('gcs')->delete($oldCover);
    } catch (\Throwable $e) {
        /* best-effort: berkas cover lama yang tertinggal tidak merusak apa pun,
           sedangkan menggagalkan respons atas pembaruan yang SUDAH commit
           mendorong admin mengulang dan membuat duplikat. */
        Log::warning('SOP cover lama gagal dihapus dari GCS', [
            'path' => $oldCover,
            'exception' => $e,
        ]);
    }
}
```

- [ ] **Step 2: Keluarkan query setelah commit (baris 1444 dan 1483)**

`paketDariSection()` dan `paketDariItem()` menjalankan query setelah commit di dalam `try`. Pindahkan keduanya ke luar `try` bersama pemanggilan `jejak()` yang memakainya. Tidak perlu pembungkus tambahan: keduanya hanya dipakai untuk mengisi jejak, dan `jejak()` sendiri sudah tak dapat melempar.

- [ ] **Step 3: Pastikan tak ada `commit` yang masih diikuti kerja di dalam `try`**

```bash
grep -n "DB::commit" app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php
```

Diharapkan: **tanpa keluaran** (Task 6 sudah menghapus semuanya). Bila ada, Task 6 belum tuntas.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php
git commit -m "fix(sop): kerja setelah commit keluar dari try

Hapus cover GCS jadi best-effort: saat GCS menolak (pernah terjadi, 403
karena tagihan), pembaruan yang SUDAH commit dilaporkan sebagai galat 500
sehingga admin mengulang dan membuat duplikat.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Batas masukan pada endpoint bulk

**KOREKSI 2026-09-01.** Rencana semula menunjuk `SopAdminDokumenController`. Itu **salah**:
`$ids` di berkas itu semuanya hasil `pluck()` dari baris berpaginasi yang sudah dibaca
sendiri, bukan masukan pengguna, dan tiga array yang memang datang dari request di sana
(`Pengesah`, `Review`, `Review.*.Lampiran`) **sudah** berbatas. Nol perubahan di berkas
itu.

Array dari request yang benar-benar tak berbatas ada di dua berkas lain:

**Files:**
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php:198`
- Modify: `app/Http/Controllers/LMS/SOP/Governance/SopAdminPenjadwalanController.php:313, 413, 664, 764`

**Interfaces:**
- Consumes: —
- Produces: —

- [ ] **Step 1: Batasi `ids` pada reorder jenis dokumen**

```php
'ids' => 'required|array|min:1|max:500',
```

Populasinya jenis dokumen — puluhan, bukan ribuan. 500 jauh di atas kebutuhan nyata dan
jauh di bawah plafon 2100 parameter SQL Server.

- [ ] **Step 2: Batasi daftar karyawan — TAPI JANGAN 500**

`kode_karyawan` (baris 313, 413, 664) dan `kode_karyawan_list` (baris 764) adalah daftar
sasaran penugasan massal. **Menerapkan `max:500` di sini akan merusak fitur**: ada
**958 karyawan aktif** (diukur 2026-09-01), sehingga menugaskan seluruh organisasi
sekaligus adalah pemakaian yang sah. Repo ini juga sudah pernah memperlakukan plafon
senyap pada jalur target penjadwalan sebagai **cacat yang diperbaiki**, bukan fitur —
mengembalikannya berarti mengulang bug yang sudah pernah dibayar.

Yang dipasang adalah pagar terhadap payload absurd, bukan plafon fungsional:

```php
'kode_karyawan' => 'required|array|min:1|max:5000',
'kode_karyawan.*' => 'string|max:50',
```

5.000 memberi ruang lebih dari lima kali headcount saat ini. Batas ini **tidak boleh**
menjadi pemotong senyap: bila terlampaui, Laravel menolak dengan pesan validasi yang
terbaca admin. Bila suatu saat headcount mendekati angka ini, yang benar adalah menaikkan
batasnya bersama pengukuran ulang — bukan memotong diam-diam.

Catatan: batas ini **tidak** melindungi dari plafon 2100 parameter, dan memang tidak
perlu — `assignKeHeader()` sudah memotong `whereIn` per 500 kode, dan `ProsesBulkAssignSop`
memproses per 100. Yang dicegah di sini adalah permintaan raksasa yang menghabiskan memori
sebelum sampai ke pemotongan itu.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/LMS/SOP/Governance/SopAdminDokumenController.php
git commit -m "fix(sop): batasi array ids pada endpoint bulk ke 500

Tanpa batas, masukan besar menembus plafon 2100 parameter SQL Server
dan muncul sebagai galat basis data, bukan pesan validasi.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Persempit `catch` yang berbohong pada `apiFavorit`

**Files:**
- Modify: `app/Services/LMS/SOP/SopRepositoryReadService.php:573-581`

**Interfaces:**
- Consumes: pola `catch (QueryException)` dari `SopCompletionService.php:115-120`.
- Produces: `toggleFavorit()` tetap mengembalikan `array{favorit: bool}`. (Nama metodenya `toggleFavorit`, bukan `favorit` — tidak ada metode bernama `favorit()` di berkas itu.) Rantai pemanggil: `routes/LMS/SOP/Employee/SopEmployee.php:52` → `SopRepositoryController::apiFavorit():367` → `toggleFavorit():380`. Controller sudah membungkusnya dengan `catch (\Exception)`, dan `QueryException` turunan `\Exception`, sehingga galat yang kini dilempar menjadi respons 500 yang tertangani — bukan exception yang bocor.

- [ ] **Step 1: Ganti tangkapan menyeluruh dengan tangkapan pelanggaran unik**

Kode sekarang menangkap **semua** `\Throwable` pada insert favorit lalu tetap menjawab `favorit: true`. Bila kegagalannya bukan pelanggaran unik — koneksi putus, kolom meluap — pengguna diberi tahu bahwa penandaannya berhasil padahal tidak.

Ikuti pola yang sudah dipakai `SopCompletionService.php:114`:

```php
try {
    DB::table('N_SOP_Favorit')->insert([
        'Kode_Karyawan' => $kode,
        'Id_SOP_Paket' => $idPaket,
        'Created_At' => now(),
    ]);
} catch (\Illuminate\Database\QueryException $e) {
    // 2627 = pelanggaran UNIQUE/PK, 2601 = pelanggaran index unik.
    // Keduanya berarti "sudah tersemat" — hasil akhirnya memang yang diminta.
    // Galat lain DILEMPAR: menjawab "tersemat" atas insert yang gagal
    // adalah berbohong kepada pengguna.
    if (! in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
        throw $e;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/LMS/SOP/SopRepositoryReadService.php
git commit -m "fix(sop): favorit tak lagi melaporkan sukses atas insert yang gagal

catch (\\Throwable) menyeluruh diganti tangkapan pelanggaran unik saja,
mengikuti pola SopCompletionService.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Sertakan exception pada seluruh `Log::error` modul SOP

Terukur pada 2026-09-01: **178 titik** menulis `Log::error($e->getMessage())` sementara hanya 6 menyertakan objek exception. Yang sampai ke log produksi karena itu hanya satu kalimat tanpa baris asalnya — inilah sebabnya galat di modul ini sulit dilacak.

Dikerjakan sebagai commit tersendiri supaya diff-nya dapat ditinjau tanpa bercampur perubahan logika.

**Files:**
- Modify: seluruh berkas di bawah `app/**` yang path-nya mengandung `SOP`

**Interfaces:**
- Consumes: —
- Produces: —

- [ ] **Step 1: Daftarkan seluruh titiknya**

```bash
{ grep -rn "Log::error(\$e->getMessage()" app --include=*.php | grep -i sop
  grep -rn "Log::error(.*\.\$e->getMessage()" app --include=*.php | grep -i sop; } | sort -u | tee /dev/stderr | wc -l
```

Jangan menulis daftarnya ke `/tmp`: pekerjaan latar lain di mesin ini berbagi
direktori itu dan saling menimpa berkas. Baca keluarannya langsung, atau simpan
ke direktori scratch milik sesi bila memang perlu disimpan.

- [ ] **Step 2: Ubah setiap titik menjadi bentuk berkonteks**

```php
// SEBELUM
Log::error($e->getMessage());
Log::error('SOP simpanHalaman: '.$e->getMessage());

// SESUDAH
Log::error('SOP <modul> <namaMetode> gagal', ['exception' => $e]);
Log::error('SOP simpanHalaman gagal', ['exception' => $e]);
```

Konteksnya wajib menyebut metode atau operasinya. `Log::error('gagal', ['exception' => $e])` tidak lebih baik dari keadaan sekarang — yang menjadikannya berguna adalah bisa dicari.

Jangan ubah `Log::warning` yang sudah sengaja best-effort, dan jangan ubah tingkat log mana pun.

- [ ] **Step 3: Pastikan tak ada yang tersisa**

```bash
grep -rn "Log::error(\$e->getMessage()" app --include=*.php | grep -i sop
```

Diharapkan: **tanpa keluaran**.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/LMS/SOP app/Services/LMS/SOP app/Jobs/LMS/SOP app/Console/Commands/LMS/SOP
git commit -m "chore(sop): sertakan exception pada Log::error agar stack trace terekam

178 titik hanya menulis getMessage(), sehingga log produksi berisi satu
kalimat tanpa baris asalnya. Tak ada perubahan perilaku.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: Verifikasi akhir terhadap baseline

**Files:**
- Create: `docs/superpowers/plans/baseline/2026-09-01-sesudah-fase1.txt`

**Interfaces:**
- Consumes: `docs/superpowers/plans/baseline/2026-09-01-baseline-suite.txt` dari Task 1.
- Produces: —

- [ ] **Step 1: Minta izin menjalankan suite penuh**

Berhenti dan minta izin, sama seperti Task 1.

- [ ] **Step 2: Jalankan suite penuh**

```bash
php artisan test --testsuite=Feature > docs/superpowers/plans/baseline/2026-09-01-sesudah-fase1.txt 2>&1; echo "exit=$?"
```

- [ ] **Step 3: Bandingkan terhadap baseline dan jelaskan setiap selisih**

```bash
diff <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-01-baseline-suite.txt | sort) \
     <(grep -E "^\s*(FAIL|✕)" docs/superpowers/plans/baseline/2026-09-01-sesudah-fase1.txt | sort)
```

Diharapkan: kegagalan yang **sama persis** dengan baseline, ditambah **nol** kegagalan baru. `SopDisiplinTransaksiTest` harus muncul sebagai lulus.

Setiap kegagalan baru wajib dijelaskan sebelum fase ini dinyatakan selesai. Jangan menaikkan ambang, jangan menandai uji sebagai skipped, dan jangan menyatakan selesai dengan selisih yang belum dipahami.

- [ ] **Step 4: Commit hasil verifikasi**

```bash
git add docs/superpowers/plans/baseline/2026-09-01-sesudah-fase1.txt
git commit -m "test(sop): verifikasi Fase 1 terhadap baseline

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Kriteria selesai Fase 1

1. `grep -rn "DB::beginTransaction" app --include=*.php | grep -i sop` tidak mengeluarkan apa pun.
2. `SopDisiplinTransaksiTest` pernah terlihat MERAH pada kode lama (terekam di riwayat commit Task 2) dan HIJAU sesudah Task 3.
3. Empat titik kerja-setelah-commit berada di luar `try`; hapus GCS bersifat best-effort.
4. Tak ada `Log::error($e->getMessage())` tersisa di modul SOP.
5. Suite penuh dijalankan dan dibandingkan terhadap baseline; nol kegagalan baru, setiap selisih dijelaskan.
6. Tidak ada perubahan skema, tidak ada perubahan perilaku yang terlihat pengguna kecuali pada jalur galat.
