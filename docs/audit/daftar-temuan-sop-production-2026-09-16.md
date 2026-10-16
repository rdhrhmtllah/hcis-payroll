# Daftar temuan skema production — 2026-09-16 (terverifikasi)

Database: `emi_db_web_hr` @ `34.143.236.80`. Login audit: `srv_team_web`
(**berbeda** dari login aplikasi production). Seluruh pemeriksaan **READ ONLY** —
tidak ada satu pun pernyataan tulis yang dijalankan.

Latar belakang, mekanisme oracle pesan galat, dan dampak per layar ada di
`izin-tabel-sop-production-2026-09-16.md`.

---

## Cakupan dan metode

Kandidat dikumpulkan dari **tiga sumber independen** lalu digabung:

1. Seluruh berkas PHP di `app/` yang menyinggung SOP/DMS — pola `DB::table()`,
   `->from()`, `->join*()`, `$table = '...'`, SQL mentah `FROM/JOIN/INTO/UPDATE/MERGE`
   (termasuk yang membentang beberapa baris), dan `EXEC`.
2. Katalog `Web_HRIS` (development): semua tabel **dan view** yang namanya memuat
   `SOP` atau `DMS`.
3. Seluruh `CREATE TABLE` di `DDL-2026-092-sinkronisasi-skema-production/forward.sql`
   (44 tabel).

Tiap kandidat diprobe dengan `SELECT TOP 0 * FROM dbo.[X]` dan hasilnya dibaca dari
*jenis* galatnya. Untuk tabel yang tak terbaca, tiap kolom dev diprobe satu per satu
dengan `SELECT TOP 0 [Kolom] FROM dbo.[Tabel]`, dan **setiap tabel diberi kontrol**
berupa nama kolom acak yang harus dijawab `Invalid column name` — 23 dari 23 kontrol
lolos.

| | Jumlah |
|---|---:|
| Kandidat diprobe | 124 |
| Bisa dibaca | 60 |
| Ada tetapi tanpa izin baca | 23 |
| **Tabel yang benar-benar tidak ada** | **0** |
| **Kolom yang benar-benar belum ada** | **12** (10 SOP + 2 non-SOP) |
| Hasil yang tidak terklasifikasi | 0 |

Probe melaporkan 41 nama "tidak ada", tetapi seluruh 41 itu adalah kata Indonesia
dari komentar kode yang tertangkap regex (`yang`, `sudah`, `versi`, `rollup`, …).
Diverifikasi: nol dari 41 nama itu merupakan objek nyata di development. **Jadi
benar-benar tidak ada tabel yang hilang.**

Skrip probe-nya ada di `storage/app/scan/probe.php` (sekali pakai, boleh dihapus).

---

## DAFTAR 1 — Tabel ADA tapi TANPA IZIN BACA untuk `srv_team_web` (23)

Semuanya terbukti ada: menjawab `The SELECT permission was denied on the object`,
bukan `Invalid object name`.

### 1a. Modul SOP/DMS (13)

| # | Tabel | Kolom (dev) | Kelengkapan kolom di prod |
|---|---|---:|---|
| 1 | `N_SOP_Master_Paket` | 23 | **kurang 2** |
| 2 | `N_SOP_Progress` | 22 | **kurang 2** |
| 3 | `N_SOP_Penjadwalan` | 17 | **kurang 3** |
| 4 | `N_SOP_Absensi` | 13 | **kurang 3** |
| 5 | `N_SOP_Paket_Section` | 10 | lengkap |
| 6 | `N_SOP_Penjadwalan_Integrasi` | 13 | lengkap |
| 7 | `N_SOP_Item_Versi_Berkas` | 15 | lengkap |
| 8 | `N_SOP_Kuis_Integrasi` | 12 | lengkap |
| 9 | `N_SOP_Aktivitas_Log` | 10 | lengkap |
| 10 | `N_SOP_Coverage_Rollup` | 8 | lengkap |
| 11 | `N_SOP_Kategori` | 8 | lengkap |
| 12 | `N_SOP_Setting` | 6 | lengkap |
| 13 | `N_SOP_Favorit` | 4 | lengkap |

### 1b. Di luar SOP — ikut ketahuan saat cakupan diperluas (10)

| # | Tabel | Kelengkapan kolom di prod |
|---|---|---|
| 14 | `N_HRIS_Api_Aplikasi` | **kurang 1** (`Kode_Karyawan_PIC`) |
| 15 | `N_HRIS_Api_Endpoint` | lengkap |
| 16 | `N_HRIS_Api_Akses_Endpoint` | lengkap |
| 17 | `N_HRIS_Api_Log` | lengkap |
| 18 | `N_HRIS_Api_Nonce` | lengkap |
| 19 | `N_HRIS_Pengumpulan_Data_Config` | **kurang 1** (`Cakupan_Lokasi`) |
| 20 | `N_HRIS_Pengumpulan_Data_Submission` | lengkap |
| 21 | `N_HRIS_Pengumpulan_Data_Status` | lengkap |
| 22 | `N_HRIS_Pengumpulan_Data_Audit` | lengkap |
| 23 | `N_HRIS_Bypass_Cuti_Tutup_Buku` | lengkap |

### 1c. Izin tulis

Pada **26 tabel `N_SOP_*` yang bisa dibaca**, `srv_team_web` punya `SELECT` tetapi
**tidak punya `INSERT`/`UPDATE`/`DELETE` sama sekali** (diperiksa lewat
`HAS_PERMS_BY_NAME`). Login ini tidak tergabung di database role mana pun; seluruh
izinnya eksplisit per objek: SELECT 319 objek, INSERT 139, UPDATE 126, DELETE 124,
EXECUTE 17.

> Bagian 1 ini adalah **batas akun audit**, bukan bukti kerusakan production.
> Login aplikasi production berbeda dan tidak diperiksa di audit ini.

---

## DAFTAR 2 — Kolom yang BELUM ADA di production (12)

Tipe diambil dari `Web_HRIS`. Hasil sudah diuji dua kali dengan keluaran identik.

### 2a. Modul SOP — 10 kolom, 4 tabel

| # | Tabel | Kolom | Tipe (dev) |
|---|---|---|---|
| 1 | `N_SOP_Master_Paket` | `Versi_Paket_Aktif` | `int NULL` |
| 2 | `N_SOP_Master_Paket` | `Id_SOP_Paket_Versi_Aktif` | `bigint NULL` |
| 3 | `N_SOP_Penjadwalan` | `Nama_Batch` | `nvarchar(150) NULL` |
| 4 | `N_SOP_Penjadwalan` | `Keterangan` | `nvarchar(500) NULL` |
| 5 | `N_SOP_Penjadwalan` | `Id_SOP_Assignment_Rule` | `int NULL` |
| 6 | `N_SOP_Progress` | `Id_Ujian_Token_Hasil` | `varchar(100) NULL` |
| 7 | `N_SOP_Progress` | `Id_Versi_Asal` | `int NULL` |
| 8 | `N_SOP_Absensi` | `Akurasi_Meter` | `decimal(9,2) NULL` |
| 9 | `N_SOP_Absensi` | `User_Agent` | `varchar(255) NULL` |
| 10 | `N_SOP_Absensi` | `IP_Address` | `varchar(45) NULL` |

Semuanya `NULL` tanpa default, jadi `ALTER TABLE … ADD` aman dan tidak mengunci
tabel lama.

### 2b. Di luar SOP — 2 kolom, keduanya dari DDL yang memang masih PENDING

| # | Tabel | Kolom | Tipe (dev) | Asal |
|---|---|---|---|---|
| 11 | `N_HRIS_Api_Aplikasi` | `Kode_Karyawan_PIC` | `varchar(20) NULL` | `DDL-2026-079-visitor-permit-api-b2b` (prod=PENDING) |
| 12 | `N_HRIS_Pengumpulan_Data_Config` | `Cakupan_Lokasi` | `varchar(2000) NULL` | `DDL-2026-110-cakupan-lokasi-pengumpulan-data` (prod=PENDING, masih dikerjakan) |

Kedua ini **bukan kejutan** — status tracker-nya memang `PENDING`, jadi wajar belum
ada di production.

### 2c. Temuan PALSU yang sudah dibuang

`Karyawan.max_Cuti` sempat terbaca "hilang" karena dev mencatatnya huruf kecil dan
production `Max_Cuti`. Collation database `SQL_Latin1_General_CP1_CI_AS`
(case-insensitive) — `SELECT TOP 0 [max_Cuti] FROM dbo.Karyawan` **berhasil** di
production. Kolom yang sama, bukan selisih.

---

## DAFTAR 3 — Kenapa DDL-2026-092 tidak bisa dipakai memperbaikinya

Kesepuluh kolom SOP hanya muncul di
`database/ddl/DDL-2026-092-sinkronisasi-skema-production/forward.sql` **Bagian 5**,
di dalam `CREATE TABLE`, bukan sebagai `ALTER TABLE … ADD`:

```sql
IF OBJECT_ID('dbo.[N_SOP_Master_Paket]', 'U') IS NULL   -- sekarang TIDAK NULL
BEGIN
    CREATE TABLE dbo.[N_SOP_Master_Paket] (
        ...
        [Versi_Paket_Aktif]        int NULL,
        [Id_SOP_Paket_Versi_Aktif] bigint NULL,
    );
END;
```

Probe membuktikan **seluruh 44 tabel** yang hendak dibuat Bagian 5 sudah ada di
production (21 terbaca + 23 tanpa izin = 44). Artinya **setiap blok `CREATE TABLE`
di Bagian 5 akan dilewati**, dan kolom-kolom pelengkapnya tidak akan pernah
terpasang. Skrip itu disusun dari perbandingan 10 September; tabelnya dibuat 11–12
September lewat jalur lain dengan definisi lebih lama.

**Yang dibutuhkan: DDL baru berisi `ALTER TABLE … ADD` untuk 10 kolom di Daftar 2a.**

---

## DAFTAR 4 — Selisih lain pada tabel yang terbaca

| Tabel | Selisih | Penilaian |
|---|---|---|
| `Karyawan` | prod punya 14 kolom ekstra (`Sisa_Cuti`, `Id_Level_Lama`, `ID_Jabatan_Baru`, …) | drift warisan, sudah lama, bukan SOP |
| `Karyawan` | `Kode_Karyawan` dev `varchar(255)` / prod `varchar(15)`; `Nama` dev `varchar(255)` / prod `varchar(50)` | prod lebih ketat; sudah tercatat di audit 2026-09-10 |
| `KPI_Users` | `Kode_Users` dev `varchar(max)` / prod `varchar(30)` | sama, drift lama |
| `N_HRIS_Notification` | `Channel` dev `varchar(20)` / prod `varchar(100)` | prod lebih longgar |

Untuk **seluruh tabel `N_SOP_*` yang terbaca (26)**: nol kolom hilang, nol kolom
ekstra, nol tipe berbeda.

---

## DAFTAR 5 — Data master

| Tabel | Dev | Prod | Catatan |
|---|---:|---:|---|
| `N_SOP_Master_APQC` | 14 | 14 | sama |
| `N_SOP_Master_Jenis_Dokumen` | 8 | 8 | sama |
| `N_SOP_Master_Tahap_Versi` | 8 | 8 | sama |
| `N_SOP_Master_Tahap_Versi_Syarat` | 7 | 7 | sama |
| `N_SOP_Master_Tahap_Versi_Transisi` | 22 | 22 | sama |
| `N_SOP_Master_APQC_Tipe_Item` | 40 | **52** | prod lebih banyak |
| `N_SOP_Master_Jenis_Dokumen_Tipe_Item` | 9 | **28** | prod lebih banyak |
| `N_SOP_Prioritas` | 3 | **0** | prod kosong |

`N_SOP_Prioritas` kosong tidak menyebabkan 500 (`SopPrioritasService::untukLayar()`
tetap memberi tiga tingkat bawaan KRITIS/TINGGI/NORMAL), tapi prioritas buatan admin
tidak ada di production sampai diseed.

Menu & registri lengkap: grup `DMS` terdaftar (`Id_Grup_Fitur=13`, landing
`menuSopSayaPage`) dengan 13 halaman di `N_HRIS_Grup_Fitur_Map`, termasuk
`menuSopBuilderPage`.

---

## DAFTAR 6 — Batas audit ini (harus ikut ditulis di report Anda)

1. **Satu arah saja.** Metode probe per kolom hanya menemukan kolom yang ada di dev
   tapi hilang di production. Kebalikannya — kolom yang hanya ada di production —
   tidak bisa ditemukan pada 23 tabel tanpa izin, karena namanya harus ditebak lebih
   dulu. Untuk 60 tabel yang terbaca, dua arah sudah tercakup.
2. **Hanya keberadaan, bukan bentuk.** Pada 23 tabel tanpa izin, tipe data,
   nullability, default, indeks, dan foreign key **tidak terperiksa**. Bisa saja
   kolomnya ada tapi tipenya berbeda.
3. **Login aplikasi production tidak diperiksa.** Seluruh temuan izin di Daftar 1
   berlaku untuk `srv_team_web` saja.
4. **Rutin tidak terperiksa.** Tidak ada `EXEC` prosedur/fungsi yang terdeteksi di
   modul SOP, jadi tidak ada yang bisa diuji. Trigger dan constraint juga di luar
   cakupan.

Kalau report Anda perlu tanpa batasan 1–2, minta DBA `GRANT VIEW DEFINITION TO
srv_team_web` (tetap read-only, tidak membuka akses data) lalu audit ini bisa
diulang lewat `sys.columns` seperti untuk 60 tabel yang lain.

---

## Tindak lanjut

1. **Buat DDL baru** `ALTER TABLE … ADD` untuk 10 kolom di Daftar 2a — **jangan**
   mengandalkan DDL-2026-092.
2. Tinjau ulang DDL-2026-092 secara keseluruhan: Bagian 5 kini mati total karena
   ke-44 tabelnya sudah ada; kolom pelengkap 44 tabel itu perlu diperiksa dengan
   metode probe yang sama, bukan diasumsikan terpasang.
3. Pasang `SkemaTersedia::kolom()` pada titik sentuh yang belum berpenjaga, atau
   putuskan kolomnya wajib ada dan biarkan gagal keras setelah DDL dijalankan.
4. Seed `N_SOP_Prioritas` di production.
5. Cocokkan arah `N_SOP_Master_APQC_Tipe_Item` dan
   `N_SOP_Master_Jenis_Dokumen_Tipe_Item`.
6. Isi atau hapus folder kosong `DDL-2026-096-perbaikan-eksekusi-sop-production/`.
7. Untuk audit berikutnya: `GRANT SELECT` + `VIEW DEFINITION` pada 23 tabel Daftar 1.
