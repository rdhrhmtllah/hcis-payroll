# Adendum — cek skema production pasca-merge master, 2026-09-16 (final)

Dijalankan setelah `feat/hcis/pengumpulan-date-parsial` di-merge dengan
`origin/master` (`20b02ea3`). Melengkapi
`daftar-temuan-sop-production-2026-09-16.md`, yang cakupannya hanya modul SOP.

Seluruhnya **READ ONLY**. Target: `emi_db_web_hr` @ `34.143.236.80`,
login audit `srv_team_web` (berbeda dari login aplikasi production).

## Cakupan

Diperluas ke **seluruh `app/` dan `routes/`**, bukan lagi SOP saja, dan menutup
tiga celah probe sebelumnya:

1. Kandidat yang **tidak ada di dev pun tetap diprobe** ke production. Probe
   sebelumnya membuangnya sebagai derau, padahal bisa saja itu objek yang belum
   dibuat di mana-mana.
2. **Rutin** (stored procedure dan function) ikut diperiksa, bukan hanya tabel.
3. **VIEW** ikut dibandingkan kolomnya, bukan hanya `USER_TABLE`.

| | Jumlah |
|---|---:|
| Kandidat diprobe ke production | 484 |
| Bisa dibaca | 256 |
| **Ada tetapi tanpa izin baca** | **23** |
| **Tidak ada di production (nyata)** | **4** |
| Derau komentar (bukan objek) | 198 |
| Rutin diperiksa | 15 |
| Kontrol oracle gagal | 0 |

---

## A. Objek yang BELUM ADA di production (4)

| # | Objek | Jenis di dev | Dirujuk di | DDL di repo |
|---|---|---|---|---|
| 1 | `N_HRIS_KANDIDAT_Api_Aplikasi` | tabel | `ApiCredentialValidator.php`, `CheckOfficialApiKey.php` | **tidak ada** |
| 2 | `N_HRIS_KANDIDAT_Api_Logs` | tabel | idem | **tidak ada** |
| 3 | `N_HRIS_KANDIDAT_Api_Nonces` | tabel | idem | **tidak ada** |
| 4 | `fn_GetShiftLengkap_Range` | inline table-valued function | `OvertimeController.php:1184, 1366` | **tidak ada** |

**Keempatnya hanya dipakai kode yang tidak pernah dijalankan.**

- `ApiCredentialValidator` dan `CheckOfficialApiKey` **tidak terdaftar di mana
  pun** — tidak di `bootstrap/app.php`, `config/`, maupun `routes/`. Middleware
  yatim.
- `OvertimeController::userActive()` dan `userActiveAll()` — dua satu-satunya
  pemakai TVF itu — **tidak punya rute**. Rute `overtime.userActive` dan
  `overtime.userActiveAdmin` menunjuk `LemburController::getUserActive` /
  `getUserActiveAll`, dan `LemburController` sama sekali tidak menyentuh
  `fn_GetShiftLengkap_Range`. Ini sisa dari penggantian TVF-per-baris oleh join
  `Absen_Snapshot`; metode lamanya tertinggal.

Bukti `fn_GetShiftLengkap_Range` benar-benar tidak ada (bukan sekadar tanpa izin),
dengan kontrol pembanding pada fungsi sejenis:

```
SELECT TOP 0 * FROM dbo.fn_GetShiftLengkap_Range            -> Invalid object name   (TIDAK ADA)
SELECT TOP 0 * FROM dbo.fn_GetShiftLengkap_Range(NULL,NULL,NULL) -> Invalid object name   (TIDAK ADA)
SELECT TOP 0 * FROM dbo.N_HRIS_FN_GetAvailableShift(NULL,NULL,NULL) -> galat jumlah argumen (ADA & terlihat)
```

**Tindakan:** hapus kode yatimnya, atau lengkapi skemanya dan daftarkan. Yang
berbahaya adalah membiarkannya — keempat objek ini akan 500 begitu seseorang
mendaftarkan rute atau middleware-nya tanpa memeriksa skema production.

---

## B. Tabel yang ADA tetapi login audit tidak punya akses (23)

**Tidak bertambah satu pun dari merge.** Daftarnya identik sebelum dan sesudah.

| Kelompok | Tabel |
|---|---|
| SOP (13) | `N_SOP_Absensi`, `N_SOP_Aktivitas_Log`, `N_SOP_Coverage_Rollup`, `N_SOP_Favorit`, `N_SOP_Item_Versi_Berkas`, `N_SOP_Kategori`, `N_SOP_Kuis_Integrasi`, `N_SOP_Master_Paket`, `N_SOP_Paket_Section`, `N_SOP_Penjadwalan`, `N_SOP_Penjadwalan_Integrasi`, `N_SOP_Progress`, `N_SOP_Setting` |
| Registry API (5) | `N_HRIS_Api_Aplikasi`, `N_HRIS_Api_Endpoint`, `N_HRIS_Api_Akses_Endpoint`, `N_HRIS_Api_Log`, `N_HRIS_Api_Nonce` |
| Pengumpulan Data (4) | `N_HRIS_Pengumpulan_Data_Config`, `N_HRIS_Pengumpulan_Data_Submission`, `N_HRIS_Pengumpulan_Data_Status`, `N_HRIS_Pengumpulan_Data_Audit` |
| Lain (1) | `N_HRIS_Bypass_Cuti_Tutup_Buku` |

Tambahan: pada 26 tabel `N_SOP_*` yang **bisa** dibaca, login ini punya `SELECT`
tetapi **tidak punya `INSERT`/`UPDATE`/`DELETE`**. Login tidak tergabung di
database role mana pun; izinnya eksplisit per objek.

> Ini batas akun audit, bukan bukti kerusakan production. Login aplikasi
> production berbeda dan tidak diperiksa di sini.

---

## C. Kolom yang BELUM ADA di production

### C1. Belum punya DDL sama sekali — 10 kolom SOP

Sudah dijawab `DDL-2026-114-sop-kolom-tertinggal-production` (dibuat hari ini).
Rinciannya di `daftar-temuan-sop-production-2026-09-16.md` Daftar 2a.

| Tabel | Kolom |
|---|---|
| `N_SOP_Master_Paket` | `Versi_Paket_Aktif`, `Id_SOP_Paket_Versi_Aktif` |
| `N_SOP_Penjadwalan` | `Nama_Batch`, `Keterangan`, `Id_SOP_Assignment_Rule` |
| `N_SOP_Progress` | `Id_Ujian_Token_Hasil`, `Id_Versi_Asal` |
| `N_SOP_Absensi` | `Akurasi_Meter`, `User_Agent`, `IP_Address` |

### C2. Sudah punya DDL, tinggal dijalankan — 13 kolom

| Tabel | Kolom | DDL | Status prod |
|---|---|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `Daftar_Anggota_Json` | DDL-2026-100 | PENDING |
| `N_VISITOR_Tamu_Efektif` | `Daftar_Anggota_Json` | DDL-2026-100 | PENDING |
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `Tipe_Tamu` | DDL-2026-101 | PENDING |
| `N_VISITOR_Tamu_Efektif` | `Tipe_Tamu` | DDL-2026-101 | PENDING |
| `N_EMI_VISITOR_Transaksi_Tamu` | `Sumber_Pengajuan`, `Id_Api_Aplikasi`, `Referensi_Mitra` | DDL-2026-102 | PENDING |
| `N_EMI_VISITOR_Transaksi_Permit` | `No_Transaksi_Tamu` | DDL-2026-103 | PENDING |
| `N_EMI_VISITOR_Transaksi_Permit_Detail` | `Jenis_Kendaraan_Tamu` | DDL-2026-103 | PENDING |
| `N_EMI_VISITOR_Master_Kategori_Permit` | `Flag_Muncul_Pengajuan` | DDL-2026-104 | PENDING |
| `N_HRIS_Api_Aplikasi` | `Kode_Karyawan_PIC` | DDL-2026-079 | PENDING |
| `N_HRIS_Grup_Fitur_Map` | `Flag_Hibah_Role` | DDL-2026-110-hibah-role-per-halaman | PENDING |
| `N_HRIS_Pengumpulan_Data_Config` | `Cakupan_Lokasi` | DDL-2026-110-cakupan-lokasi-pengumpulan-data | PENDING |

`Flag_Hibah_Role` dibawa master lewat commit `Separate Visitor role grants per
page`. Selama DDL-nya belum dijalankan, jalur RBAC yang membacanya akan gagal di
production.

### C3. Tanpa DDL, tetapi tidak disentuh kode hidup

| Objek | Kolom hilang | Catatan |
|---|---|---|
| `HRIS_KANDIDAT_Users` | `avatar_url`, `avatar_object`, `avatar_updated_at`, `avatar`, `kode_role`, `flag_notifikasi`, `flag_pertama_login`, `flag_informasi`, `flag_informasi_storage`, `flag_login_mobile` | Milik sistem rekrutmen terpisah. DDL-2026-092 menyatakan 8 tabel `HRIS_KANDIDAT_*` sengaja di luar cakupan. Hanya dirujuk middleware yatim. |
| `View_Golongan_Sub_Golongan_Level_Jabatan` (VIEW) | `Flag_Extra_Meal`, `Flag_Harian` | Kode hidup (`StatusKaryawanResolver`) hanya mengambil `lvl.ID_Level`, jadi tidak terdampak. `HRIS_Level.Flag_Harian` sendiri ditambahkan DDL-2026-092 yang masih PENDING — **view ini perlu dibuat ulang setelah DDL-2026-092 dijalankan**, kalau tidak kolomnya tetap tak muncul walau kolom dasarnya sudah ada. |

---

## D. Rutin (procedure / function) yang dipanggil kode

15 diperiksa, **14 ADA**, 1 tidak ada.

| Rutin | Keadaan di production |
|---|---|
| `HRIS_SP_GET_ABSEN_NEW_V3` | ADA |
| `N_HRIS_SP_GET_ABSEN_WEB_WRAPPER` | ADA |
| `N_HRIS_SP_ABSEN_MAINTENANCE` | ADA |
| `N_HRIS_SP_PROCESS_ABSEN_RECALC_QUEUE` | ADA |
| `N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE` | ADA |
| `N_HRIS_SP_MARK_ABSEN_DIRTY` | ADA |
| `sp_getShiftAbsen` | ADA |
| `N_HRIS_FN_GetStatusHariRange` | ADA |
| `N_HRIS_FN_GetAvailableShift` | ADA |
| `N_HRIS_VW_Approval_Request_V2` | ADA |
| **`fn_GetShiftLengkap_Range`** | **TIDAK ADA** (lihat bagian A) |

---

## E. Selisih tipe data (bukan temuan baru, tidak berubah karena merge)

21 kolom bertipe berbeda antara dev dan production — `Karyawan.Kode_Karyawan`
(`varchar(255)` vs `varchar(15)`), `CHECKINOUT.Hasil`, `KPI_Users.Kode_Users`,
`HRIS_Buku_Cuti.Jumlah_Terpakai` (`decimal(5,2)` vs `float`),
`HRIS_Approval_Request.Created_At` (`datetime` vs `datetime2`), dan seterusnya.
Seluruhnya drift warisan yang sudah tercatat di
`perbandingan-skema-production-2026-09-10.md`; DDL-2026-092 secara eksplisit
menyatakan penyamaan tipe data di luar cakupannya.

Catatan metode: `Karyawan.max_Cuti` yang sempat tercatat "hilang" di laporan
pertama adalah temuan palsu — murni beda kapitalisasi terhadap `Max_Cuti`. Probe
ini membandingkan nama kolom case-insensitive mengikuti collation
`SQL_Latin1_General_CP1_CI_AS`, jadi tidak muncul lagi.

---

## F. Batas audit ini

1. **Satu arah untuk 23 tabel tanpa izin.** Probe per kolom hanya menemukan kolom
   yang ada di dev tetapi hilang di production. Kebalikannya tidak bisa ditemukan
   karena namanya harus ditebak lebih dulu. Untuk 256 objek yang terbaca, dua arah
   sudah tercakup.
2. **Hanya keberadaan, bukan bentuk,** pada 23 tabel tanpa izin: tipe data,
   nullability, default, indeks, dan foreign key tidak terperiksa.
3. **Trigger, constraint, dan permission aplikasi production tidak diperiksa.**

Untuk menghilangkan batas 1–2, minta DBA `GRANT VIEW DEFINITION TO srv_team_web`
(tetap read-only, tidak membuka akses data).

---

## Ringkasan tindakan

| Prioritas | Tindakan |
|---|---|
| 1 | Jalankan `DDL-2026-114` — 10 kolom SOP, satu-satunya yang belum punya DDL. |
| 2 | Jalankan DDL-2026-110-hibah-role-per-halaman — `Flag_Hibah_Role` dipakai jalur RBAC hidup dari master. |
| 3 | Jalankan DDL-2026-100 s.d. 104 dan 079 — kolom Visitor yang sudah tercatat PENDING. |
| 4 | Jalankan `DML-2026-115` — seed `N_SOP_Prioritas`. |
| 5 | Setelah DDL-2026-092: buat ulang `View_Golongan_Sub_Golongan_Level_Jabatan` agar `Flag_Harian` muncul. |
| 6 | Putuskan nasib kode yatim: `ApiCredentialValidator`, `CheckOfficialApiKey`, `OvertimeController::userActive/userActiveAll`. |
| 7 | Selesaikan tabrakan nomor `DDL-2026-110` (dua change berbeda memakai nomor yang sama). |
