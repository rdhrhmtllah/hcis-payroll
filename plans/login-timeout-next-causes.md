# Penyebab "Login timeout expired" — Tahap Berikutnya (setelah fix WhatsApp)

## Context
Penyebab utama (HTTP WhatsApp sinkron di dalam transaksi) sudah diperbaiki & teruji.
Investigasi lanjutan (2 agen) menemukan penyebab TERSISA dari 2 kelas yang sama-sama
menahan koneksi/lock DB → berkontribusi ke saturasi pool → "Login timeout expired".

## Kelas A — I/O eksternal SINKRON di dalam transaksi (sama seperti WhatsApp)
Fix pola: pindahkan I/O ke SEBELUM `beginTransaction`, atau setelah `commit`, atau job async.

| # | Situs | I/O | Transaksi | Prioritas |
|---|-------|-----|-----------|-----------|
| A1 | `UpdatePerformanceController.php` upload KPI | **Lock issue fixed**: semua upload dipindah sebelum transaction, orphan cleanup saat rollback; wajib manual staging regression | sebelumnya tx 1993-2127 / 2191-2326 / 2392-2526 | **TERTINGGI** |
| A2 | `LmsPenjadwalanTrainingController.php` update/reassign training | **REASSIGN fixed in phase 1**: intent Outbox commit singkat, Third Party dipanggil handler di luar transaction, hasil token disimpan conditional; `update()` masih requires separate redesign | update legacy tx 853-1259; reassign async | TINGGI |
| A3 | `TransaksiPermitController.php` upload permit | **FIXED**: upload GCS dipindah sebelum transaction, metadata disimpan setelah transaction dimulai, dan file baru dibersihkan saat upload/DB gagal; fallback public dihapus | `store()` pre-upload + short tx; `uploadDokumen()` cleanup on insert failure | MED-TINGGI |
| A4 | `CutiController.php` upload lampiran | **UPLOAD ISSUE FIXED**: file GCS di-upload sebelum transaction aktif dan dihapus saat rollback; notifikasi sebelum commit dicatat sebagai audit terpisah, bukan bagian fix storage | `store()` upload sekitar 1667-1681; tx mulai 1687 | MED |
| A5 | `AbsensiController.php` submitIzin lampiran | **FIXED**: nomor transaksi dan lampiran GCS disiapkan sebelum transaction; satu path dilacak dan dibersihkan saat gagal; WA sudah memakai `afterCommit()` pada tiga cabang; submitAbsensi foto juga sudah fixed | submitIzin short tx setelah pre-upload; upload 5600/5788/5997 memakai path pre-upload | MED |
| A6 | `KontenKategoriController.php` dispatch verifikasi konten | **Lock issue fixed**: Cloud Tasks API dipanggil setelah `DB::commit()`; durable Outbox/recovery masih deferred | sebelumnya tx 847-926 | MED |

Aman (sudah di luar transaksi): submit absen (upload sebelum begin 7445), userController, LmsAdminTraining, AuthController, dll.

## Kelas B — Lock contention (non-sargable + lockForUpdate / transaksi lebar)
| # | Situs | Masalah | Status |
|---|-------|---------|--------|
| **B1** | `UpdatePerformanceController.php:4416` `generateNewTransactionNumber` | `whereYear/whereMonth` non-sargable + `MAX(CAST(RIGHT))` + `lockForUpdate` → full-scan setahun under UPDLOCK | **SUDAH DIPERBAIKI** → prefix `LIKE` sargable; diverifikasi identik di dev (Juni 33=33) |
| B2 | `CutiBersamaAdvancedController.php:1406-1606` | transaksi lebar menyelimuti loop SEMUA karyawan; "sub-transaction" 1522 hanya savepoint → header(1408)+wallet(2725/2786) lock ditahan sepanjang batch | belum |
| B3 | `TransactionService.php:1099-1124` `getNoTransaction` | sargable tapi lock ditahan sampai commit caller bila di-nest | belum (rendah) |
| B4 | Generator nomor transaksi legacy | **PARTIALLY FIXED**: `LEFT()` lama sudah diganti counter atomik + prefix `LIKE` pada Overtime/Form KPI/Absensi; residual: Overtime dan Form KPI memanggil counter setelah transaction dimulai, perlu dipindah sebelum transaction | Overtime 1478/1598; Form KPI 1590/2177; Absensi duplicate generator removed in A5 follow-up | belum selesai |
| B5 | `AuditAdminTransaction.php:520` `sp_getapplock` Exclusive | applock per-bulan | sudah dimitigasi (jalan di terminate()) |

## Rekomendasi urutan
1. **B1** — SELESAI (kemenangan tunggal terbesar utk lock, risiko minimal).
2. **A6 KontenKategori** — pindah `createTask` ke setelah commit (atau pakai queue after_commit). Kecil, jelas.
3. **A1 UpdatePerformance GCS-in-loop** — restrukturisasi: upload GCS SEBELUM `beginTransaction` (kumpulkan path→row), transaksi hanya tulis DB. Dampak tertinggi kelas A, perlu hati-hati + test.
4. **A2 LmsPenjadwalan HTTP-in-loop** — pindah `Http::post` keluar transaksi.
5. **A3/A4/A5** — upload GCS keluar transaksi (pola sama seperti submit absen 7445 yang sudah benar).
6. **B4** — samakan generator ke prefix sargable + counter atomik; keluarkan dari transaksi.
7. **B2 CutiBersama** — pecah jadi commit NYATA per-karyawan; lepas lock header cepat; keluarkan kerja non-DB.
8. **Global (paling fundamental):** ganti seluruh penomoran ke **counter atomik/SEQUENCE** (`HRIS_Counter(prefix,next_val)` `UPDATE ... OUTPUT`) → hapus pola scan-lalu-lock sepenuhnya; panggil sebelum transaksi bisnis.

## Verifikasi & batasan
- Uji tiap fix di DB dev (`team311/Web_HRIS`) dengan pola rollback-safe / Http+Queue fake; JANGAN `migrate`/`RefreshDatabase` (hapus data). Produksi READ-ONLY.
- Untuk fix upload/HTTP-keluar-transaksi: pastikan bila upload gagal, transaksi tidak commit data yang mereferensikan file yang gagal (atau sebaliknya bersihkan file bila commit gagal).

## Manual staging follow-up

A1 dan A6 sudah memiliki perbaikan kode di branch ini, tetapi validasi staging tetap wajib dijalankan menggunakan: [`docs/architecture/staging-regression-checklist.md`](../docs/architecture/staging-regression-checklist.md).

A1 minimal mencakup submit satu file, banyak file, tanpa file, replace/update, simulasi upload gagal, rollback cleanup, approval/reject, dan pemeriksaan duplicate file/task/notification.

## A2 design finding

A2 tidak boleh diperbaiki dengan sekadar memindahkan `Http::post()` ke `DB::afterCommit()`, karena response Third Party menghasilkan `Id_Ujian_Token` dan `Payload_Json` yang dibutuhkan oleh row lokal sebelum workflow dianggap selesai. Selain masalah lock, ada risiko distributed inconsistency: Third Party sukses tetapi transaction lokal rollback, atau transaction lokal commit tetapi operasi Third Party belum berhasil.

Rekomendasi desain: pecah menjadi workflow dua fase dengan intent/status lokal yang commit singkat, lalu proses Third Party di luar transaction melalui Outbox/handler. Handler menyimpan token/result secara conditional dan retryable. Rollout harus memisahkan jalur `update()` dan `reassign()` serta memakai idempotency key per operasi/detail/token lama. Fase 1 untuk `reassign()` sudah diimplementasikan; jalur `update()` tetap belum diubah.

## A2 implementation record

Phase 1 hanya mengubah `reassignThirdParty()`. Endpoint sekarang membuat `LMS_REASSIGN_THIRD_PARTY` Outbox intent dan mengembalikan HTTP `202`; dispatcher dipanggil setelah commit. Handler memanggil Third Party di luar transaction, lalu menyimpan token/payload dan waktu akses dalam transaction lokal yang pendek dengan guard `old_token`. Retryable HTTP errors dikembalikan ke mekanisme retry Outbox; response tanpa token dan konflik token menjadi permanent failure.

Alur `update()` yang masih memiliki banyak detail dan STORE/UPDATE pihak ketiga tidak diubah pada fase ini.

## A3 design finding

Perbaikan storage pada commit lama `8107566d` hanya mengubah target utama menjadi GCS dan menambahkan fallback public; masalah transaction belum terselesaikan. Desain yang disetujui adalah GCS wajib tanpa fallback public, pre-upload sebelum transaction, short transaction untuk metadata, dan cleanup seluruh path baru bila upload/database gagal. Desain ini sudah diimplementasikan pada fase A3.

## A3 implementation record

A3 sudah diperbaiki dengan GCS sebagai storage wajib. `store()` melakukan validasi/master lookup dan upload seluruh dokumen lebih dulu, kemudian membuka transaction singkat untuk insert header, detail, path dokumen, checklist, dan approval flow. Bila upload berikutnya gagal, atau transaction/database gagal, seluruh file baru dalam request dibersihkan dari GCS. Endpoint `uploadDokumen()` juga membersihkan file jika insert metadata gagal. Fallback ke disk `public` tidak lagi digunakan agar path dan cleanup tidak ambigu.

## A4 implementation record

Jalur aktif `CutiController::store()` sudah memindahkan upload GCS sebelum `DB::beginTransaction()`, menggunakan nomor transaksi yang sudah dibuat, dan melacak `$uploadedGcsPath`. Bila validasi approval, exception, atau database gagal, file tersebut dihapus melalui `deleteUploadedCutiFile()`. Karena perbaikan ini sudah ada sejak commit `cfcd8446`, tidak ada perubahan runtime baru pada fase A4.

Catatan terpisah: `notifyFirstApprover()` masih dipanggil sebelum `DB::commit()`. Ini perlu audit notifikasi/side-effect tersendiri dan tidak boleh dianggap terselesaikan hanya karena masalah upload sudah fixed.

## A5 design finding

A5 dipisahkan antara `submitAbsensi()` dan `submitIzin()`. Upload foto absensi pada jalur sekitar 7200 sudah berada sebelum transaction dan memiliki cleanup saat DB gagal. `submitIzin()` kini membuat nomor transaksi dan meng-upload satu lampiran opsional sebelum membuka transaction, memakai path pre-upload pada tiga cabang data, dan cleanup bila approval/insert/database gagal. WA pada tiga cabang sudah memakai `DB::afterCommit()`; perubahan A5 mempertahankan kontrak tersebut.

## A5 implementation record

`submitIzin()` sekarang memanggil `uploadIzinAttachment()` sebelum `DB::beginTransaction()`. Nomor transaksi dibuat lebih dahulu melalui counter atomik, sehingga nama/path dapat disiapkan tanpa menahan transaction bisnis. Transaction hanya membungkus insert izin/cuti dan approval data. Bila proses gagal, transaction di-rollback dan path GCS baru dihapus. Tiga callback WhatsApp yang sudah menggunakan `DB::afterCommit()` dipertahankan; tidak ada HTTP provider di dalam transaction pada jalur tersebut.

## B4 audit record

Audit terbaru menemukan bahwa beberapa perubahan sebelumnya sudah mengganti pola non-sargable `LEFT(No_Transaksi)`/scan lama dengan `TransactionNumberService` dan prefix `LIKE`. Namun penggunaan counter atomik tetap idealnya dilakukan sebelum transaction bisnis. `OvertimeController::submitAdmin()` dan `submit()` masih membuka transaction sebelum memanggil generator. `FormKpiController` juga membuka transaction sebelum generator pada submit individual dan team. `AbsensiController::submitIzin()` kini hanya membuat nomor di awal sebelum transaction; generator ganda pada cabang `cuti/cutiHutang` telah dihapus sebagai follow-up A5.

B4 belum dianggap selesai sampai residual nested counter di Overtime dan Form KPI dipindahkan, diuji, dan diverifikasi tanpa mengubah format nomor atau urutan bisnis.
