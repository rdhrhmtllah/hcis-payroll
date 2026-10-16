# DDL-2026-187-pa-skema-lengkap-production

| | |
|---|---|
| Change ID | `DDL-2026-187-pa-skema-lengkap-production` |
| Jenis | DDL |
| Target | **Production** `emi_db_web_hr`, hanya melalui SQL Tracker |
| Development | Tidak dijalankan (seluruh tabel sudah ada di `Web_HRIS`; lihat Verifikasi) |
| Forward | `database/ddl/DDL-2026-187-pa-skema-lengkap-production/forward.sql` |
| Drop (bantuan manual) | `database/ddl/DDL-2026-187-pa-skema-lengkap-production/drop.sql` |
| Checksum forward (sha256) | `86f6af48296c72b058f4914bfa3b468b449cb4976401dc044474e66abee8c26f` |
| Status | REVIEW_REQUIRED |

## Untuk apa

Production belum punya satu pun tabel Performance Appraisal. Di development skemanya terbentuk
bertahap lewat 46 DDL (064–185), banyak di antaranya `ALTER` atas tabel yang dibuat DDL
sebelumnya. Menjalankan rangkaian itu di production berarti mengulang seluruh sejarah perbaikannya.

Change ini membuat **skema akhir** sekaligus: 69 tabel (67 `N_PA_*` + 2 tabel relasi
organisasi HRIS yang dirujuk PA), masing-masing langsung dalam bentuk akhirnya — kolom, default,
identity, PK, UNIQUE, CHECK, FK, dan indeks. **Tanpa ALTER.** FK ditulis di dalam `CREATE TABLE`
dan tabel diurutkan mengikuti ketergantungannya (tidak ada FK melingkar).

## Cara dibangkitkan

Langsung dari katalog `Web_HRIS` development (bukan dari berkas forward lama), lalu dibandingkan
dengan katalog production (baca saja, akun "READ ONLY"):

- Production: 0 tabel `N_PA_*`; `Karyawan`, `HRIS_Level`, dan `N_SYSTEM_Database_Change` sudah ada
  (kolom ledger sama persis dengan development).
- `N_HRIS_Organization_Relationship` dan `N_HRIS_Organization_Relationship_Type` **belum ada** di
  production dan dirujuk tiga tabel PA — ikut dibuat di sini.
- **Tipe kolom FK ke `Karyawan` disamakan dengan production**: `Karyawan.Kode_Karyawan` di production
  `varchar(15)`, di development `varchar(255)`. SQL Server menuntut panjang yang sama untuk FK
  (galat 1753), jadi kolom berikut ditulis `varchar(15)`:
  `N_PA_Participant.Kode_Karyawan`, `N_PA_Assignment.Kode_Karyawan_Penilai`,
  `N_HRIS_Organization_Relationship.Kode_Karyawan_Subjek` dan `.Kode_Karyawan_Terkait`.
  Collation development dan production sama (`SQL_Latin1_General_CP1_CI_AS`).

## Verifikasi (agent, 14 Okt 2026)

1. **Kompilasi tanpa eksekusi** (`SET NOEXEC ON`) di development: 133 batch, 0 galat; ledger
   development tidak berubah.
2. **Uji bangun penuh dalam satu transaksi yang di-ROLLBACK** di development, nama tabel &
   constraint diberi awalan `Z187_` (tipe kolom FK `Karyawan` disesuaikan ke development hanya untuk
   uji ini): terbangun 69 tabel, 124 FK, 116 CHECK, 183 indeks (termasuk PK/UNIQUE), 154 DEFAULT —
   **sama persis** dengan skema PA development. Perbandingan per kolom (tipe, panjang, presisi,
   null, identity, default, urutan): 917 dari 917 identik. Sesudah rollback tidak ada objek tersisa.
3. Ke-46 DDL PA lama hanya menyentuh 69 tabel ini (tidak ada tabel lain yang diubah),
   jadi change ini mencakup seluruhnya untuk production.

## DDL lama yang DIGANTIKAN untuk production

Untuk production, **jalankan change ini saja — jangan jalankan DDL berikut**. Mereka tetap menjadi
catatan sejarah development:

- `DDL-2026-061-hris-organization-relationship`
- `DDL-2026-062-organization-relationship-simple-type`
- `DDL-2026-063-organization-relationship-explicit-direction-types`
- `DDL-2026-064-performance-appraisal-policy-foundation`
- `DDL-2026-065-performance-appraisal-pillar-weight`
- `DDL-2026-066-performance-appraisal-cycle`
- `DDL-2026-067-performance-appraisal-competency-and-score-range`
- `DDL-2026-068-performance-appraisal-hav-quadrant`
- `DDL-2026-069-performance-appraisal-cycle-status`
- `DDL-2026-070-performance-appraisal-status-and-axis-masters`
- `DDL-2026-071-performance-appraisal-cycle-rule-snapshots`
- `DDL-2026-072-performance-appraisal-participant`
- `DDL-2026-073-performance-appraisal-cycle-question-package`
- `DDL-2026-074-performance-appraisal-participant-overrides`
- `DDL-2026-075-performance-appraisal-assignment`
- `DDL-2026-076-performance-appraisal-payload-and-score`
- `DDL-2026-080-performance-appraisal-result`
- `DDL-2026-081-performance-appraisal-result-detail`
- `DDL-2026-082-performance-appraisal-assignment-cancellation`
- `DDL-2026-083-performance-appraisal-question-competency-map`
- `DDL-2026-084-performance-appraisal-pre-exclusion`
- `DDL-2026-085-performance-appraisal-cycle-setup-step`
- `DDL-2026-086-performance-appraisal-package-per-rater`
- `DDL-2026-087-performance-appraisal-question-binding-master`
- `DDL-2026-088-catatan-hasil-berpangkal-kompetensi`
- `DDL-2026-089-kalibrasi-boleh-dilepas`
- `DDL-2026-090-pa-ikatan-paket-soal-bagian`
- `DDL-2026-091-pa-level-dikecualikan`
- `DDL-2026-092-pa-sel-penilai-belum-berbobot`
- `DDL-2026-093-pa-pintu-hclearn-nyata`
- `DDL-2026-094-pa-callback-hclearn-lengkap`
- `DDL-2026-095-pa-kode-soal-butir-siklus`
- `DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi`
- `DDL-2026-119-pa-kalibrasi-penilai`
- `DDL-2026-120-pa-grade-kurva`
- `DDL-2026-130-pa-rilis-hasil-ke-leader`
- `DDL-2026-140-pa-esai-per-soal`
- `DDL-2026-165-pa-balancing`
- `DDL-2026-166-pa-expected-behaviour-kompetensi`
- `DDL-2026-168-pa-riwayat-hasil`
- `DDL-2026-169-pa-waktu-kirim-penilai`
- `DDL-2026-170-pa-salinan-kompetensi-siklus`
- `DDL-2026-175-pa-tingkat-kompetensi`
- `DDL-2026-177-pa-level-pa-karyawan`
- `DDL-2026-183-pa-master-level`
- `DDL-2026-185-pa-fk-level-ke-master-pa`

## Urutan production

1. Jalankan `forward.sql` ini lewat SQL Tracker (satu kali; setiap blok dijaga `IF OBJECT_ID ... IS NULL`).
2. Verifikasi: 69 tabel, 124 FK, 116 CHECK, 183 indeks, 154 DEFAULT pada objek `N_PA_*` dan dua
   tabel relasi organisasi.
3. Data (master PA, menu, relasi organisasi, Level PA) menyusul lewat DML terpisah.

## Agent completed

- Forward, drop, README, dan entri ledger dibuat; verifikasi di atas.

## User tasks

- Review dan setujui artefak ini.
- Catat di SQL Tracker dengan checksum di atas, jalankan di production, lalu konfirmasi hasilnya.
- Tandai DDL lama di atas sebagai "digantikan" di SQL Tracker bila perlu.
