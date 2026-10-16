# DDL-2026-118 — Rekap satu berkas untuk seluruh selisih skema production

| | |
|---|---|
| Change ID | `DDL-2026-118-rekap-skema-production` |
| Tipe | DDL |
| Target | `emi_db_web_hr` (production) |
| Development | `Web_HRIS` — no-op, semuanya sudah ada |
| Status | `REVIEW_REQUIRED` |
| Forward | `database/ddl/DDL-2026-118-rekap-skema-production/forward.sql` |
| Drop | `database/ddl/DDL-2026-118-rekap-skema-production/drop.sql` |
| DML | `database/dml/DDL-2026-118-rekap-skema-production/data-change.sql` (sengaja kosong) |
| Checksum forward (sha256) | `0727d4e852816e7df94198651305216021b6c8111541d6b4dbf54caf3b5c814a` |
| Sumber temuan | `docs/audit/daftar-temuan-sop-production-2026-09-16.md`, `docs/audit/adendum-pasca-merge-master-2026-09-16.md` |

## Isi

Satu kali eksekusi menutup:

| | Jumlah |
|---|---:|
| Kolom | **23** (10 SOP + 13 Visitor/RBAC/Pengumpulan Data) |
| Foreign key | 1 (`FK_N_SOP_Pjd_Rule`) |
| Constraint DEFAULT/CHECK | 8 |
| Indeks | 6 (3 belum ada, 3 definisinya kedaluwarsa) |

## Menggantikan eksekusi sembilan DDL

Setelah berkas ini dijalankan, kesembilan DDL berikut **tidak perlu dijalankan
lagi** — isinya sudah tercakup seluruhnya:

`DDL-2026-079-visitor-permit-api-b2b`, `DDL-2026-100-visitor-daftar-anggota-tamu`,
`DDL-2026-101-visitor-tipe-tamu-permit-approver`, `DDL-2026-102-visitor-tamu-api-b2b`,
`DDL-2026-103-visitor-permit-coklat-approval`, `DDL-2026-104-visitor-kategori-permit-akses`,
`DDL-2026-110-hibah-role-per-halaman`, `DDL-2026-110-cakupan-lokasi-pengumpulan-data`,
dan `DDL-2026-114-sop-kolom-tertinggal-production`.

`DDL-2026-114` dan `DDL-2026-117` yang sempat dibuat sebagai langkah antara sudah
**dicabut** (artefak dan entri ledger-nya dihapus) supaya tidak ada dua jalur
untuk pekerjaan yang sama. Saat merekam di SQL Tracker, tandai kedelapan DDL
sisanya sebagai dieksekusi melalui DDL-2026-118 dan rujuk silang nomornya.

## PERHATIAN: berkas ini memuat tiga `DROP INDEX`

Bagian 11 membuang lalu membuat ulang tiga indeks yang definisinya di production
sudah kedaluwarsa — **dua di antaranya indeks UNIK**:

| Indeks | Definisi di production | Yang seharusnya |
|---|---|---|
| `UX_VISITOR_Permit_Requester_Idempotency` | `(Kode_Karyawan_Pemohon, Idempotency_Key)`, tanpa filter | `(Kode_Karyawan_Pemohon, Id_Api_Aplikasi, Idempotency_Key)` `WHERE Idempotency_Key IS NOT NULL` |
| `UX_VISITOR_Tamu_Requester_Idempotency` | `(Kode_Karyawan_PIC, Idempotency_Key)`, tanpa filter | `(Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)` `WHERE Idempotency_Key IS NOT NULL` |
| `IX_VISITOR_Permit_Api_Aplikasi` | tanpa filter | `WHERE Id_Api_Aplikasi IS NOT NULL` |

Tanpa `Id_Api_Aplikasi` di kunci, dua aplikasi mitra yang mengirim
`Idempotency_Key` yang sama akan bertabrakan — persis masalah yang DDL-2026-079
dan 102 hendak selesaikan.

Tiga pengaman dipasang:

1. **Tabelnya kosong.** `N_EMI_VISITOR_Transaksi_Permit` dan
   `N_EMI_VISITOR_Transaksi_Tamu` berisi **0 baris** di production per
   2026-09-16, jadi pembuatan ulang berlangsung seketika dan tidak mungkin gagal
   karena nilai kembar.
2. **`DROP` berpenjaga ciri-versi,** bukan penjaga "kalau ada". Untuk kedua UX,
   `DROP` hanya berjalan bila `Id_Api_Aplikasi` memang belum ada di kunci; untuk
   `IX`, hanya bila `filter_definition IS NULL`. Menjalankan berkas ini berulang
   tidak akan membongkar indeks yang sudah benar.
3. **Penjaga akhir.** Bagian 12 melempar `RAISERROR` bila ada satu pun dari 23
   kolom, 6 indeks, atau foreign key yang gagal terbentuk — sehingga indeks yang
   terbuang tetapi gagal dibuat ulang tidak akan lolos tanpa disadari.

## Kenapa urutannya seperti itu

1. **Seluruh kolom (Bagian 1–10) mendahului seluruh indeks (Bagian 11).** Empat
   dari enam indeks menyebut kolom yang baru lahir di berkas ini
   (`Id_Api_Aplikasi`, `No_Transaksi_Tamu`); membangunnya lebih dulu pasti gagal.
2. **Bagian 4 (DDL-100) wajib mendahului Bagian 5 (DDL-101).** Dua tabel menerima
   kolom dari keduanya — `N_EMI_VISITOR_Transaksi_Tamu_Detail` dan
   `N_VISITOR_Tamu_Efektif` — dan SQL Server memberi `column_id` sesuai urutan
   penambahan. Membalik urutannya membuat ordinal production berbeda dari
   development.
3. **Foreign key dan CHECK selalu di batch terpisah setelah kolomnya.** SQL Server
   mengompilasi satu batch utuh sebelum menjalankannya, jadi merujuk kolom yang
   baru dibuat di batch yang sama gagal dengan `Invalid column name`.
4. **DEFAULT dipasang inline pada `ALTER … ADD`.** Untuk kolom `NOT NULL` itu
   wajib: tanpa default, `ALTER` ditolak pada tabel yang sudah berisi baris.
5. **Satu `ALTER` per kolom, masing-masing berpenjaga.** Menggabungkan beberapa
   kolom dalam satu `ALTER` membuat penjaganya salah begitu sebagian kolom sudah
   ada.

## Dampak

- 22 dari 23 kolom nullable tanpa default → operasi metadata saja: tanpa penulisan
  ulang tabel, tanpa kunci panjang, baris lama tidak tersentuh.
- Satu kolom `NOT NULL` berdefault: `N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role`.
  Tabelnya berisi **75 baris**, seluruhnya otomatis `'Y'` — perilaku hibah role
  yang berlaku sekarang dipertahankan apa adanya; pengecualian ditandai `'T'`
  belakangan lewat perubahan data tersendiri.
- Tidak ada `DROP TABLE`, `DROP COLUMN`, `TRUNCATE`, `UPDATE`, `DELETE`, atau
  `MERGE`. Satu-satunya `DROP` adalah tiga `DROP INDEX` di Bagian 11, masing-masing
  langsung diikuti `CREATE` penggantinya.
- Nama DEFAULT, CHECK, dan INDEX disalin persis dari DDL asal supaya tidak lahir
  objek kembar bernama beda.

## Di luar cakupan

| Dilewati | Alasan |
|---|---|
| `DDL-2026-092-sinkronisasi-skema-production` | Sampel Bagian 1–2 (10 kolom) terbukti **sudah ada** di production meski ledger menandainya PENDING, dan Bagian 5-nya sudah mati total karena ke-44 tabelnya sudah ada. Bagian 3 (indeks), 4 (default), 6 (trigger), 7 (foreign key) **belum diperiksa**. |
| `N_HRIS_KANDIDAT_Api_*`, 10 kolom `HRIS_KANDIDAT_Users` | Sistem rekrutmen terpisah; hanya dipakai middleware yatim yang tidak terdaftar di mana pun. Keputusan user. |
| `fn_GetShiftLengkap_Range` | Hanya dipanggil `OvertimeController::userActive/userActiveAll` yang tidak punya rute. Keputusan user. |
| `View_Golongan_Sub_Golongan_Level_Jabatan` (`Flag_Extra_Meal`, `Flag_Harian`) | Keputusan user. |
| Seed data | Terpisah di `DML-2026-115-seed-sop-prioritas`. |

## Verifikasi

`forward.sql` menutup dengan:

- **Bagian 12 — penjaga akhir.** `RAISERROR` bila ada dari 23 kolom, 6 indeks,
  atau FK yang belum terbentuk. Kalau berkas selesai tanpa galat, artinya semuanya
  lengkap.
- **Bagian 13 — laporan.** Tiga tabel hasil: 23 kolom beserta tipe harapan dan
  keadaannya, 9 constraint (1 FK + 8 DEFAULT/CHECK), dan definisi sebenarnya
  keenam indeks (unik, filter, kolom kunci) untuk dicocokkan dengan tabel di atas.

Sudah divalidasi `SET PARSEONLY ON` terhadap `Web_HRIS`: `forward.sql` **54 batch
lolos**, `drop.sql` **43 batch lolos**, 0 gagal.

## Pekerjaan yang sudah dilakukan agent

- Menyusun `forward.sql`, `drop.sql`, `data-change.sql`, dan dokumentasi ini.
- Inventarisasi seluruh pernyataan eksekutabel di kedelapan DDL asal (33
  pernyataan) supaya tidak ada yang tertinggal.
- Verifikasi read-only ke production: keadaan 23 kolom, 8 nama constraint, dan
  definisi lengkap 6 indeks (kunci, INCLUDE, filter, unik); jumlah baris setiap
  tabel terdampak.
- Validasi sintaks `SET PARSEONLY ON`.
- Mencabut artefak `DDL-2026-114` dan `DDL-2026-117` beserta entri ledger-nya.
- Agent **tidak** menjalankan pernyataan pengubah skema apa pun, baik ke
  production maupun development.

## Pekerjaan user

1. Tinjau dan setujui `forward.sql`, khususnya Bagian 11 (tiga `DROP INDEX`).
2. Rekam di SQL Tracker — isi harus identik dan checksum harus cocok. Tandai
   kedelapan DDL asal sebagai dieksekusi melalui DDL-2026-118.
3. Jalankan ke production sekali jalan. Jalankan saat trafik sepi.
4. Baca keluaran `PRINT` dan ketiga tabel Bagian 13. Kalau ada `RAISERROR`,
   **jangan** dianggap selesai.
5. Jalankan `DML-2026-115-seed-sop-prioritas`.
6. Bersihkan cache menu aplikasi (`flushMenuCache`) setelah `Flag_Hibah_Role` ada.
7. Naikkan status mengikuti alur wajib sampai `COMPLETED`.
