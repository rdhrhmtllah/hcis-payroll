# Plan: Full-async WA (jalur ber-callback) + Uji Alur Bisnis per-Endpoint

## Context
Perbaikan lock "Login timeout expired" sudah masuk & teruji (WA keluar transaksi via
`SendWhatsappJob` untuk jalur tanpa-callback, `DB::afterCommit` untuk jalur ber-callback).
Sisa: (1) jadikan jalur **ber-callback** (approval flow dgn update `angka_wa`) **juga
non-blocking** lewat job; (2) uji alur bisnis nyata per-endpoint di DB dev.

---

# BAGIAN 1 — Full-async jalur ber-callback

## Temuan yang menentukan pendekatan
`angka_wa` (kolom `HRIS_Approval_Request`) **write-only di seluruh repo**: semua kemunculan =
`UPDATE ... angka_wa = angka_wa + 1`; satu-satunya pembaca (`->where('angka_wa','>',2)` di
`app/Console/Commands/ExpiredIzin.php:56`) **sudah di-comment**. Tak ada referensi di
`resources/`, `routes/`, `database/`. Callback `onSuccess/onFail` pada `sendAndLog` isinya
hanya: (a) increment `angka_wa`, (b) log ke channel, (c) insert baris FAILED ke `Whatsapp_Log`
(redundan — `performSend` sudah insert). **Tak satu pun bagian keputusan approval.**

## GATE (wajib sebelum eksekusi)
Konfirmasi ke user / cek di luar repo: **adakah report/BI/DB view/aplikasi lain yang MEMBACA
`HRIS_Approval_Request.angka_wa`?** Repo menunjukkan tidak. 
- **Jika TIDAK** → Approach A (drop). 
- **Jika YA** → Approach B (job memelihara `angka_wa`).

## Approach A — REKOMENDASI (angka_wa write-only → hentikan pemeliharaannya)
Metrik "berapa kali WA dikirim ke approver" tetap **bisa diturunkan** dari `Whatsapp_Log`
kapan pun dibutuhkan: `COUNT(*) WHERE No_Transaksi=? AND Kode_Karyawan_Destination=? AND
Status='SUCCESS'`. Jadi kolom `angka_wa` tak perlu dipelihara.

Langkah:
1. **`WhatsappLogHelper::sendAndLog`**: hapus cabang `afterCommit`-sync; **semua** panggilan
   (dengan/tanpa callback) → `SendWhatsappJob::dispatch(...)`. Callback closure diabaikan.
   Untuk mempertahankan jejak log per-modul, tambah param opsional `?string $logChannel` /
   `array $logContext` yang di-serialize ke job (bukan closure), lalu job menulis log itu.
2. **Job `SendWhatsappJob`**: sudah memanggil `performSend` (kirim + `Whatsapp_Log`). Tambah
   penulisan log channel opsional bila `logContext` diberikan.
3. **Situs inline angka_wa + WA** (bukan via `sendAndLog`): ganti dispatch job & **hapus**
   update `angka_wa`. Lokasi: `app/Services/ApprovalService.php:424`,
   `app/Services/ShiftRequestService.php` (443/750/3543/4079/4398/7970),
   `app/Http/Controllers/LeaderShiftController.php:1382`, `app/Services/KonversiLemburService.php:379`,
   `app/Http/Controllers/HCAbsensi/.../IzinAtasanApprovalController.php:2313`,
   `app/Http/Controllers/HCAbsensi/cuti/karyawan/CutiController.php:2200`,
   `app/Http/Controllers/HCAbsensi/cuti/admin/AdminCutiController.php:1088`, dan situs Absensi
   yang sudah dibungkus afterCommit (639/6131/6374 → sederhanakan jadi dispatch job, buang angka_wa).
4. **`app/Console/Commands/waUlang.php:135`** & **`ExpiredIzin.php`**: bila resend WA dulu
   dibatasi `angka_wa`, ganti logika cap ke hitungan dari `Whatsapp_Log` (atau biarkan — sudah
   di-comment). Verifikasi command resend masih benar.

## Approach B — bila angka_wa ternyata dibaca (job memelihara angka_wa)
Tambah param serializable ke `SendWhatsappJob` (BUKAN closure):
```php
new SendWhatsappJob($kode,$nama,$hp,$noTransaksi,$pesan, angkaWaTarget: [
    'no_transaksi' => $noTransaksi,
    'kode_karyawan' => $approverKode,     // approver tujuan
    'order_flow' => $orderFlow ?? null,   // opsional
    'group_id' => $groupId ?? null,       // opsional (flow GROUP)
]);
```
`handle()` setelah kirim SUKSES → jalankan UPDATE `angka_wa` yang **dinormalkan** (satu bentuk
kanonik keyed `No_Transaksi + approver + flow/group`), menggantikan aneka WHERE/JOIN yang
sekarang berbeda-beda antar situs. Perlu memetakan tiap situs ke bentuk kanonik ini.

## DDL `Whatsapp_Log` (dipakai di sini) — EKSEKUSI MANUAL user, di dev dulu
Full-async = kirim di worker + retry Cloud Tasks → butuh audit siklus:
```sql
ALTER TABLE dbo.Whatsapp_Log ADD
    Attempt_Count   int          NULL CONSTRAINT DF_WA_Log_Attempt DEFAULT (0),
    Queued_At       datetime2(0) NULL,
    Sent_At         datetime2(0) NULL,
    Idempotency_Key varchar(200) NULL,
    Task_Name       varchar(500) NULL;
GO
CREATE UNIQUE INDEX UX_WA_Log_Idempotency ON dbo.Whatsapp_Log (Idempotency_Key) WHERE Idempotency_Key IS NOT NULL;
GO
CREATE INDEX IX_WA_Log_Status_Created ON dbo.Whatsapp_Log (Status, Created_At);
GO
```
Pemakaian di kode:
- **Dispatch**: `sendAndLog` insert baris `Status='QUEUED'`, `Queued_At=now`,
  `Idempotency_Key = sha1(No_Transaksi|hp|type|step)`; simpan `Id`.
- **Job handle()**: cek idempotency — bila sudah ada baris `SUCCESS` dgn key sama → skip
  (anti dobel-kirim saat Cloud Tasks retry). Setelah kirim → UPDATE baris (by Id/key) jadi
  `SUCCESS`/`FAILED`, set `Sent_At`, `Attempt_Count` dari header `X-CloudTasks-TaskRetryCount`.
- Ubah `performSend`/`insertLog` agar bisa **update baris** (bukan hanya insert) + terima
  idempotency key.

## Verifikasi Bagian 1 (di dev, tanpa migrate, Http::fake + Queue::fake)
- Semua `sendAndLog` (dengan & tanpa callback) → `SendWhatsappJob` dipush (Queue::assertPushed).
- Approval flow submit/approve tidak lagi menahan koneksi (tak ada afterCommit-sync WA).
- Idempotency: dispatch 2x key sama → job kedua skip (tak kirim 2x).
- `angka_wa`: (A) tak ada UPDATE angka_wa lagi; (B) job meng-update baris yang benar.
- Regресi cek: approve/reject KPI, Cuti, Izin, Lembur, Shift tetap commit & status benar.

---

# BAGIAN 2 — Uji alur bisnis per-endpoint (DB dev)

## Prinsip
Drive **controller action asli** di dev dengan `Http::fake` (tak ada WA nyata) + `Queue::fake`
(job tak benar-benar jalan), **tanpa migrate**. Membuktikan: transaksi commit, data bisnis
tertulis benar, WA di-defer/queue (bukan saat transaksi), tak ada error variabel/closure.

## Cara dapат skenario valid (pilih)
- **Opsi 1 (cepat):** saya query dev (READ-ONLY) untuk record yang cocok: 1 pengajuan pending
  per modul + approver-nya, 1 karyawan valid utk submit. Lalu drive alurnya.
- **Opsi 2:** user beri No_Transaksi + user login spesifik per skenario.

## Target endpoint (per modul yang tersentuh perbaikan)
| Modul | Aksi | Yang dibuktikan |
|---|---|---|
| Lembur | `OvertimeController::submit` (loop WA) | commit; WA per-user di-defer; no error |
| Absensi Izin | `AbsensiController::confirmIzin` (approve/reject) | commit; WA + angka_wa setelah commit |
| Absensi Izin/Cuti/Sakit/Terlambat | submit (5771–6408) | commit; 3 cabang WA ter-defer |
| Shift | `ShiftController::submit` & `update` | commit; WA per-user di-defer |
| KPI | `FormKpiController::store`/`storeTeam` | commit; WA via job/afterCommit |
| Approval terpusat | `ApprovalService::approve/reject` (Cuti/Izin/TP/Lembur) | commit; WA setelah commit |

## Harness uji (pola sama seperti verify sebelumnya)
Script boot Laravel → `Auth::login($userValid)` → `Http::fake()` + `Queue::fake()` →
bangun `Request` valid (dari record dev) → panggil `app(Controller::class)->action($request)`
→ assert: tak throw, `DB::transactionLevel()==0` di akhir, baris bisnis dibuat (query balik),
`Queue::assertPushed(SendWhatsappJob)` / tak ada WA saat transaksi. **Bersihkan** record test
yang dibuat (delete by label/Id) setelah selesai — dev boleh ditulis, tapi dirapikan.

## Batasan (tetap berlaku)
- **JANGAN** `php artisan test` / `migrate` / `RefreshDatabase` → menghapus data dev
  (LemburSubmitSmokeTest pakai RefreshDatabase).
- Produksi tetap READ-ONLY; DDL & perubahan skema dieksekusi manual oleh user.

---

## Urutan eksekusi disarankan
1. GATE: pastikan `angka_wa` tak dibaca eksternal → pilih Approach A/B.
2. (Bila Approach dgn audit) user jalankan DDL `Whatsapp_Log` di dev.
3. Implement Bagian 1 (job jadi satu-satunya jalur WA; idempotency; angka_wa).
4. Verifikasi Bagian 1 (Http::fake + Queue::fake).
5. Bagian 2: drive endpoint per modul di dev, assert, bersihkan.
6. Deploy bertahap: perbaikan lock (sudah) → full-async → pantau `Conn_Acquire_Ms` &
   hilangnya `Login timeout expired`.
