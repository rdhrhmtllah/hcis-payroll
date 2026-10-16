# DDL-2026-092 — Sinkronisasi Skema Production

| | |
| --- | --- |
| Change ID | `DDL-2026-092-sinkronisasi-skema-production` |
| Target | production (`emi_db_web_hr`) — **dijalankan manual oleh user** |
| Sumber | `docs/audit/perbandingan-skema-production-2026-09-10.md` |
| Status | `REVIEW_REQUIRED` |
| Forward | `database/ddl/DDL-2026-092-sinkronisasi-skema-production/forward.sql` |
| Drop | `database/ddl/DDL-2026-092-sinkronisasi-skema-production/drop.sql` |
| DML | `database/dml/DDL-2026-092-sinkronisasi-skema-production/data-change.sql` |
| Checksum forward | `096bcc6cff95ce253c49eb4fbf0cbab2ddd138b6f432df4e4213e2bdcac962b8` |

## Tujuan

Menutup selisih **tabel, kolom, dan indeks** antara development dan production
yang ditemukan pada perbandingan READ ONLY 10 September 2026, ditambah trigger,
default, dan foreign key yang ikut ketahuan pada sapuan yang sama.

## Isi forward.sql

| Bagian | Isi | Jumlah |
| ---: | --- | ---: |
| 1 | Kolom kritis — halaman production error tanpa ini | 13 kolom pada 5 tabel |
| 2 | Kolom pelengkap — belum dipakai kode | 29 kolom |
| 3 | Primary key `HRIS_Approval_Flow` + indeks pada tabel yang sudah ada | 1 PK + 33 indeks |
| 4 | Default constraint & check constraint | 7 default + 1 check |
| 5 | Tabel baru milik modul yang belum dirilis | 44 tabel |
| 6 | Trigger `*_trg_UpdateTimestamp` | 3 |
| 7 | Foreign key | 7 |

Bagian 1 adalah satu-satunya bagian yang memperbaiki kerusakan yang sudah
terjadi hari ini:

| Tabel | Kolom | Akibat sekarang |
| --- | --- | --- |
| `KPI_Update_Performance_Detail` (+ `_Sementara`) | `Score_Raw`, `Achievement_Raw`, `Flag_Capped` | Layar approval & verifikasi KPI 500 |
| `HRIS_Hak_Cuti_Tambahan` | `Status`, `Created_At`, `Created_By`, `Updated_At`, `Updated_By` | Tambah/ubah ketentuan hak cuti gagal |
| `Karyawan` | `Tanggal_Akhir_Probation` | Dua widget dashboard cuti error |
| `N_HRIS_Cuti_Bersama` | `Flag_Cancelled` | Kalender operasional gagal |

`Karyawan.Tanggal_Akhir_Probation` juga ada di DDL-2026-091 dan `Score_Raw`
sekeluarga ada di DDL-2026-045. Keduanya sengaja diulang di sini supaya berkas
ini berdiri sendiri; karena idempoten, menjalankan keduanya tidak bertabrakan.

## Isi data-change.sql

| Bagian | Isi | Jumlah |
| ---: | --- | ---: |
| 1 | `HRIS_Klasifikasi_Aksi` (prasyarat kapabilitas menu) | 18 baris |
| 2 | `N_HRIS_Menus` — hanya halaman yang kodenya ada di `master` | 10 |
| 3 | `N_HRIS_Grup_Fitur_Map` | 11 |
| 4 | `N_HRIS_Menu_Capabilities` | 185 |
| 5 | `N_HRIS_Role_Functional` | 8 |
| 6 | `N_HRIS_Role_Scope` | 17 pernyataan berpenjaga |
| 7 | `N_HRIS_Role_Resource_Access` | 48 pernyataan berpenjaga |
| 8 | Master pendukung SOP | 86 baris |

## Keputusan penting yang perlu ditinjau

**1. Id identity tidak pernah disalin.** Terbukti berbeda antar server:
`Kode_Grup_Fitur = 'SYSTEM'` ber-`Id_Grup_Fitur` 5 di development tapi 11 di
production. Seluruh relasi di DML diselesaikan lewat pencarian kunci bisnis
(`Kode_Grup_Fitur`, `Role_Code`, `Jenis_page`, `Kode_Departemen`, …).
Pengecualiannya hanya `HRIS_Klasifikasi_Aksi`, yang tabelnya baru dibuat dan
masih kosong sehingga Id-nya boleh dipaksa agar cocok dengan `Id_Aksi` di
`N_HRIS_Menu_Capabilities`.

**2. Bagian 5 dan 7 adalah keputusan rilis, bukan perbaikan.** 44 tabel itu
milik SOP/DMS, registry API, Pengumpulan Data, dan RBAC generasi kedua. Dibuat
kosong, tidak mengubah perilaku aplikasi yang berjalan, tapi memasangnya berarti
menyiapkan modul yang belum dirilis. Bagian 1–4 berdiri sendiri kalau bagian 5
dan 7 dilewati.

**3. Bagian 8 DML hanya berguna bersama rilis SOP.** Ditelusuri lewat
`sys.foreign_keys`, `N_HRIS_Master_Departemen` dan `N_HRIS_Master_Entitas` di
production hanya dikonsumsi `N_SOP_Dokumen` (0 baris), dan `Karyawan` di
production tidak punya kolom departemen sama sekali.

**4. Yang sengaja TIDAK dimasukkan.** 43 tabel `N_PA_*` (kodenya tidak ada di
cabang `master`), 8 tabel `HRIS_KANDIDAT_*` (aplikasi rekrutmen),
`N_HRIS_Approval_Request` (production memakai nama kolom berbeda dan tabelnya
tidak dirujuk kode), 13 rutin warisan yang tak dipanggil kode, dan penyamaan
tipe data (butuh keputusan arah — lihat laporan §2e).

**5. `Sub_Divisi_Id` dan `Induk_Entitas_Id` dilepas** dari DML master karena
menunjuk Id yang berbeda antar server. Di development pun hanya 17 dari 52
departemen yang mengisinya. Harus diisi manual setelah data masuk.

## Sifat berkas

- **Idempoten.** Setiap pernyataan berpenjaga (`IF NOT EXISTS`, `COL_LENGTH`,
  `OBJECT_ID`). Menjalankan dua kali tidak menambah apa pun dan tidak melempar
  galat.
- **Menambah saja.** Tidak ada `DROP`, `TRUNCATE`, `DELETE`, `UPDATE`, atau
  `MERGE`. Tidak ada kolom yang diubah tipenya. Baris yang sudah ada di
  production tidak disentuh.
- **Foreign key aman gagal.** Bagian 7 melewati constraint yang masih punya
  baris yatim alih-alih melempar galat.

## Prasyarat yang sudah diverifikasi READ ONLY

- `HRIS_Approval_Flow.[id]`: `int NOT NULL`, 4.310 baris, 4.310 nilai unik, 0
  NULL → aman dijadikan primary key clustered.
- `KPI_Update_Performance_Detail` (6.254 baris) dan `_Sementara` (6.531 baris):
  `Flag_Capped` `NOT NULL` ditambahkan dengan `DEFAULT 'T'` supaya tidak gagal
  pada baris yang sudah ada.
- `N_HRIS_Konversi_Lembur_Efektif` dan `N_LMS_Master_Keterangan_Kelulusan`: 0
  grup duplikat, jadi indeks `UNIQUE`-nya tidak akan gagal.

## Verifikasi yang sudah dijalankan

| Pemeriksaan | Hasil |
| --- | --- |
| `SET PARSEONLY ON` per batch terhadap development | `forward.sql` 217 batch, `data-change.sql` 13 batch — **0 gagal parse** |
| Validasi semantik statis (objek & kolom yang disentuh akan ada di production) | 383 INSERT, 111 CREATE INDEX, 42 ALTER ADD, 7 FK — **0 masalah** |

`PARSEONLY` hanya mengurai; tidak ada objek dibuat dan tidak ada baris disentuh
di development maupun production.

## Urutan menjalankan

1. Ambil backup / snapshot database production.
2. Jalankan `forward.sql` **sampai selesai** dalam satu sesi SSMS atau sqlcmd
   (berkas memakai pemisah batch `GO`).
3. Jalankan `data-change.sql`.
4. Jalankan **ulang** `forward.sql` sekali lagi — foreign key yang tadi
   dilewati karena baris yatim akan ikut terpasang setelah datanya ada.
5. Bersihkan cache menu aplikasi (`flushMenuCache`) agar menu baru terlihat.
6. Verifikasi (bagian berikutnya).

## Verifikasi sesudah eksekusi

```sql
-- kolom kritis harus terjawab tanpa galat
SELECT TOP 0 Score_Raw, Achievement_Raw, Flag_Capped FROM KPI_Update_Performance_Detail;
SELECT TOP 0 Status, Created_At, Created_By, Updated_At, Updated_By FROM HRIS_Hak_Cuti_Tambahan;
SELECT TOP 0 Tanggal_Akhir_Probation FROM Karyawan;
SELECT TOP 0 Flag_Cancelled FROM N_HRIS_Cuti_Bersama;

-- primary key HRIS_Approval_Flow harus ada
SELECT name, type_desc FROM sys.indexes WHERE object_id = OBJECT_ID('HRIS_Approval_Flow');

-- menu baru harus 10 baris
SELECT COUNT(*) FROM N_HRIS_Menus
 WHERE Jenis_page IN ('superAdminManajemenPeranPage','MyActivity','ActivityMonitor',
   'menuSopPrioritasPage','visitorApprovalPage','approvalFlowShift','adminPengumpulanDataPage',
   'adminKPIKaryawanTeamPage','adminHCApprovalKPIPage','adminAllPagesAccess');
```

Lalu buka halaman approval KPI, verifikasi KPI, ketentuan hak cuti tambahan, dan
kalender operasional — keempatnya yang sekarang error.

## Pekerjaan agent vs pekerjaan user

**Sudah dikerjakan agent**

- Perbandingan skema READ ONLY dev vs production (tidak ada perintah tulis).
- Pembangkitan `forward.sql`, `drop.sql`, `data-change.sql` dari katalog
  development.
- Verifikasi sintaks (`PARSEONLY`) dan validasi semantik statis.
- Pencatatan di `database/ddl/status.json` dengan status `REVIEW_REQUIRED`.

**Belum dikerjakan — milik user**

- Meninjau dan menyetujui isi berkas, khususnya keputusan 2, 3, dan 5 di atas.
- Menjalankan di development lebih dulu bila ingin uji nyata (agent tidak
  menjalankannya karena belum disetujui).
- Merekam forward DDL di SQL Tracker.
- Menjalankan ke production secara manual dan memverifikasi hasilnya.
- Menaikkan status di `status.json` mengikuti alur wajib
  (`APPROVED` → … → `PRODUCTION_EXECUTED` → `COMPLETED`).

`drop.sql` adalah alat bantu reset manual. Berkas itu berisi `DROP` dan tidak
boleh dijalankan sebagai bagian rilis.
