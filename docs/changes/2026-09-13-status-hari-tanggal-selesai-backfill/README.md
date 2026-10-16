# Backfill tanggal selesai status hari karyawan

## Ownership dan basis verifikasi

- Modul: `ESS` (`N_HRIS_Master_Grup_Fitur.Id_Grup_Fitur=1`, kode `ESS`, aktif).
- Submodul: `Attendance`.
- Fitur: `Shift`.
- Layer: internal operation API, Form Request, controller, service, middleware, dan feature test.
- Page mapping terkait: `ShiftManagement`, `ShiftManagementAdmin`, `shiftApproval`, dan `selfShift` pada `N_HRIS_Grup_Fitur_Map` aktif.
- Endpoint ini tidak menambah page/menu baru. Route internal: `POST /api/v1/internal/ess/attendance/shift/status-hari/backfill-tanggal-selesai`.
- Basis live diverifikasi read-only pada `Web_HRIS` development di host `team311.dyndns.info` tanggal 14 September 2026.

## Tujuan dan batas

Operasi ini memperbaiki baris aktif `N_HRIS_Status_Hari_Karyawan` dengan
`Tgl_Selesai < Tgl_Mulai`. Dry-run adalah mode default dan tidak menulis data.
Apply endpoint hanya boleh hidup pada development/staging; production tetap
wajib memakai DML yang direview dan dicatat melalui SQL Tracker.

Endpoint tidak menebak semua baris. Hanya kandidat dengan bukti deterministik
yang diberi `auto_fixable=true`:

| Klasifikasi | Nilai usulan |
|---|---|
| `WORK_AUTO_ONE_DAY` | `Tgl_Selesai = Tgl_Mulai` |
| `STATUS_SEMENTARA_ONE_DAY` | `Tgl_Selesai = Tgl_Mulai` |
| `STATUS_PERMANEN_WORK_RESTORE_OPEN` | `Tgl_Selesai = NULL` |
| `STATUS_PERMANEN_OFF_CLOSE_AT_CAUSAL_SHIFT` | sehari sebelum shift penyebab, tetapi tidak boleh sebelum `Tgl_Mulai` |
| `STATUS_PERMANEN_OFF_RESTORE_OPEN` | `NULL`, karena OFF baru dimulai setelah shift penyebab |
| `OFF_CLOSE_AT_CAUSAL_SHIFT` | sehari sebelum shift penyebab, tetapi tidak boleh sebelum `Tgl_Mulai` |

Baris tanpa bukti transaksi sumber maupun shift penyebab tetap
`MANUAL_REVIEW` dan tidak disentuh oleh apply.

## Strategi koreksi dan kontrak sinkronisasi

Apply memakai strategi `DEACTIVATE_AND_INSERT`, bukan mengubah nilai bisnis
row lama secara langsung:

1. row lama diubah hanya pada `Flag_Aktif='T'` serta audit `Updated_At` dan
   `Updated_By`;
2. row pengganti di-INSERT dengan `Flag_Aktif='Y'` dan nilai
   `Tgl_Selesai` hasil klasifikasi;
3. kedua operasi berada dalam satu transaction per batch;
4. response dan structured log mencatat pasangan `old_id` dan `new_id`.

Kolom bisnis selain `Tgl_Selesai` disalin persis, termasuk `Status_Hari`,
`Hari`, `No_Transaksi_Reference`, dan `Keterangan`. Nilai `Keterangan` tidak
boleh diberi suffix koreksi karena klasifikasi WORK overlay memakai nilai exact.
Row pengganti mempertahankan `Created_At` dan `Created_By` row lama agar
tie-breaker `N_HRIS_FN_GetStatusHari` tidak berubah. Waktu apply dan actor System
Admin dicatat pada `Updated_At` dan `Updated_By` row pengganti.

Strategi ini dipilih karena consumer pihak ketiga menangkap INSERT serta
perubahan `Flag_Aktif`, tetapi tidak menangkap perubahan kolom bisnis biasa.
Consumer wajib menerapkan perubahan flag berdasarkan `Id` sumber. Jika event
datang tidak berurutan, penonaktifan `old_id` tidak boleh menonaktifkan
`new_id` berdasarkan business key.

## Gerbang keselamatan

1. Bearer token wajib dan tidak mempunyai nilai default.
2. `target_database` harus sama dengan hasil live `DB_NAME()`.
3. Bila environment deployment dikonfigurasi, request harus sama dengannya.
4. Apply hanya menerima `development` atau `staging`.
5. Apply default mati dan baru hidup jika environment, database, dan sakelar
   apply dikonfigurasi eksplisit.
6. Apply membutuhkan `plan_hash`, jumlah kandidat batch, actor System Admin
   aktif, dan frasa konfirmasi dari hasil dry-run.
7. Plan hash dan setiap penonaktifan mengikat seluruh nilai row yang disalin,
   termasuk audit actor. Perubahan setelah dry-run membuat seluruh batch—termasuk
   INSERT pengganti—rollback sebagai stale plan.
8. Batch maksimum 500 dan kandidat ambigu tidak masuk batch.
9. Sesudah commit, rentang attendance terkait ditandai dirty. Kegagalan dirty
   mark dilaporkan sebagai `APPLIED_DIRTY_MARK_WARNING`, bukan disamarkan.
10. Mode production ditolak oleh endpoint walaupun seluruh payload lengkap.
11. Dirty-mark dideduplicasi per rentang kandidat dan tidak menggabungkan dua
    tanggal berjauhan milik karyawan menjadi satu rentang besar. Dry-run
    menampilkan `dirty_plan.range_count`, `dirty_plan.user_date_count`, dan
    daftar rentang yang akan dikirim ke antrean recalculation.
12. `mutation_plan.ordering_policy` wajib bernilai
    `PRESERVE_ORIGINAL_CREATED_AT`. Hash dry-run yang dibuat sebelum kebijakan
    ini tersedia tidak boleh digunakan untuk apply.

## Konfigurasi deployment

```dotenv
HCIS_STATUS_HARI_BACKFILL_TOKEN=
HCIS_STATUS_HARI_BACKFILL_APPLY_ENABLED=false
HCIS_STATUS_HARI_BACKFILL_ENVIRONMENT=
HCIS_STATUS_HARI_BACKFILL_DATABASE=
```

Jangan menyimpan nilai token di repository. Setelah operasi selesai, matikan
kembali `HCIS_STATUS_HARI_BACKFILL_APPLY_ENABLED` dan rotasi/hapus tokennya.

## Request dry-run

```json
{
  "target_database": "Web_HRIS",
  "target_environment": "development",
  "dry_run": true,
  "kode_perusahaan": "001",
  "batch_size": 200
}
```

Kirim melalui header `Authorization: Bearer <token>`. Filter opsional:
`tanggal_mulai_dari` dan `tanggal_mulai_sampai` dalam format `YYYY-MM-DD`.

Catat dari response: `summary`, `plan_hash`, `dirty_plan`, `candidates`, dan
`manual_review_sample`. `mutation_plan.strategy` harus bernilai
`DEACTIVATE_AND_INSERT`, dengan jumlah `deactivate_rows` dan `insert_rows` yang
sama. `mutation_plan.ordering_policy` harus bernilai
`PRESERVE_ORIGINAL_CREATED_AT`. Dry-run ulang tepat sebelum apply.

## Request apply development/staging

Payload harus memakai scope yang sama dengan dry-run:

```json
{
  "target_database": "Web_HRIS",
  "target_environment": "development",
  "dry_run": false,
  "kode_perusahaan": "001",
  "batch_size": 200,
  "plan_hash": "<plan_hash dari dry-run terakhir>",
  "expected_auto_fixable_count": 200,
  "actor_id": 123,
  "confirmation": "APPLY_STATUS_HARI_BACKFILL"
}
```

`actor_id` harus merupakan `KPI_Users.Id_Users` yang aktif dan memiliki
`Is_System_Admin='Y'`. Ulangi dry-run dan apply per batch sampai
`auto_fixable_total=0`. Jangan menerapkan kandidat `MANUAL_REVIEW` tanpa audit
sumber tambahan.

Apply yang berhasil mengembalikan:

- `applied.deactivated_rows`;
- `applied.inserted_rows`;
- `applied.replacements[]` berisi mapping `old_id` ke `new_id`;
- `applied.remaining_invalid_in_scope`;
- hasil dirty mark untuk kalkulasi ulang attendance.

Plan hash mengikat strategi mutasi dan kebijakan urutan resolver. Hash dari
versi direct-UPDATE maupun dry-run sebelum kebijakan pelestarian `Created_At`
tidak valid dan wajib diganti dengan hasil dry-run terbaru.

## Pencegahan untuk transaksi baru

Jalur penerapan shift permanen dan aksi `Akhiri Override` memakai versioning
yang sama: versi lama dinonaktifkan, versi pengganti diinsert, dan
`Created_At`/`Created_By` dipertahankan. Mode selain `SEMENTARA` atau `PERMANEN`
ditolak sebelum penulisan. Bila perhitungan batas minggu menghasilkan tanggal
sebelum `Tgl_Mulai`, tanggal akhir dicap ke `Tgl_Mulai` agar invariant rentang
tidak pernah dilanggar.

## Hasil development read-only saat implementasi

Dry-run langsung terhadap `Web_HRIS` development, diverifikasi ulang pada
14 September 2026:

- invalid aktif: 74;
- otomatis: 16 (`WORK_AUTO_ONE_DAY` 14, `OFF_CLOSE_AT_CAUSAL_SHIFT` 2);
- manual review: 58;
- tidak ada UPDATE/INSERT/DELETE yang dijalankan terhadap data bisnis development.

Angka 609 yang dilaporkan pengguna berasal dari `emi_db_web_hr` pada host
`34.143.236.80`. Response menyebut konfigurasi `development`, tetapi target itu
belum diverifikasi independen sebagai development menurut gerbang keselamatan
repository. Karena itu apply tidak boleh dilakukan hanya berdasarkan label
response. Hasil dry-run baru pada deployment final—setelah verifikasi target—
menjadi basis exact predicate, expected count, dan plan hash operasi.

## Verifikasi setelah apply

```sql
SELECT COUNT(Id) AS Invalid_Aktif
FROM N_HRIS_Status_Hari_Karyawan
WHERE Flag_Aktif = 'Y'
  AND Tgl_Selesai IS NOT NULL
  AND Tgl_Selesai < Tgl_Mulai;
```

Target akhirnya nol hanya setelah kandidat `MANUAL_REVIEW` diselesaikan dengan
bukti yang cukup. Selain invariant tabel, periksa hasil dirty queue dan resolver
status hari pada sampel tanggal sebelum/saat/setelah perubahan.

## Status produksi

- Endpoint/code: siap direview dan diuji pada branch fix.
- DML SQL Tracker: belum dibuat final karena target 609 belum terverifikasi.
- Production: belum dijalankan.
