# Perbandingan Skema Production vs Development — 10 September 2026

Laporan ini menjawab satu pertanyaan: **apa yang seharusnya ada di production tapi
tidak ada di sana** — tabel, kolom, indeks, dan isi master — sebagai bahan
penyusunan DDL/DML susulan.

## Cara pengambilan data

Seluruh pengambilan bersifat **READ ONLY**: hanya `SELECT` dan katalog `sys.*`.
Tidak ada satu pun perintah tulis (DDL/DML) dijalankan terhadap production.

| Sisi | Database | Tabel | View |
| --- | --- | ---: | ---: |
| Development | `Web_HRIS` | 750 | 29 |
| Production | `emi_db_web_hr` | 273 | 22 |

Selisih mentah 477 tabel **tidak berarti apa-apa**. Server development menampung
aplikasi lain (`HRIS_KANDIDAT_*`, `N_WEB_CAREERS_*`, `N_AMS_*`, `N_ESIGN_*`,
`finger_log`, `Loadtest_*`) dan juga skema dari cabang yang belum dirilis, karena
satu database development dipakai bersama oleh semua worktree.

Karena itu perbandingan disaring dua kali:

1. **Dirujuk kode.** Nama tabel/view diekstrak dari `app/`, `routes/`,
   `database/`, `config/`, dan `resources/` — 1.787 berkas, 280 nama cocok dengan
   objek dev. Hanya yang benar-benar dirujuk yang dihitung sebagai kekurangan.
2. **Ada di cabang `master`.** Untuk objek dan menu yang hilang, dicek apakah
   kodenya memang ada di worktree ini. Yang tidak ada kodenya berasal dari cabang
   yang belum dirilis dan **bukan** kekurangan production.

### Batas yang harus diketahui pembaca

Login production yang dipakai **tidak punya izin `VIEW DEFINITION`**
(`HAS_PERMS_BY_NAME(...) = 0`, bukan `db_owner`/`db_ddladmin`). Akibatnya setiap
kolom `definition` di katalog terbaca NULL: 35/35 check constraint, 367/367
default constraint, 27/27 filter indeks, dan 47/47 badan rutin.

Karena itu untuk check, default, filter indeks, definisi view, dan isi
prosedur/fungsi yang bisa dibandingkan **hanya keberadaannya**, bukan isinya.
Percobaan pertama laporan ini membandingkan teks definisi dan menghasilkan 157
"default hilang", 16 "check hilang", serta 21 "indeks kehilangan filter" — semua
palsu, murni artefak izin. Angka di bawah sudah memakai metode yang tahan
terhadap batasan ini.

Arahnya juga tidak satu arah. Production justru punya **95 indeks** dan kolom
pada **25 objek** yang tidak ada di development. Production bukan sekadar
tertinggal dari dev; keduanya sudah menyimpang dua arah.

---

## 1. Tabel yang dipakai kode tapi tidak ada di production — 49

### 1a. Modul SOP/DMS — 25 dirujuk kode, 28 total (paling besar)

SOP terpasang **separuh** di production: 11 tabel ada, 28 tabel tidak ada.

Yang sudah ada (11): `N_SOP_Dokumen`, `N_SOP_Item_Versi_Pengesah`,
`N_SOP_Item_Versi_Review`, `N_SOP_Item_Versi_Review_Lampiran`,
`N_SOP_Master_APQC`, `N_SOP_Master_APQC_Tipe_Item`, `N_SOP_Master_Jenis_Dokumen`,
`N_SOP_Master_Jenis_Dokumen_Tipe_Item`, `N_SOP_Master_Tahap_Versi`,
`N_SOP_Master_Tahap_Versi_Syarat`, `N_SOP_Master_Tahap_Versi_Transisi`.

Yang tidak ada (28): `N_SOP_Absensi`, `N_SOP_Assignment_Rule`,
`N_SOP_Assignment_Rule_Paket`, `N_SOP_Automation_Run`, `N_SOP_Coverage_Rollup`,
`N_SOP_Favorit`, `N_SOP_Item_Versi`, `N_SOP_Item_Versi_Berkas`,
`N_SOP_Kepatuhan_Riwayat`, `N_SOP_Log`, `N_SOP_Master_Paket`,
`N_SOP_Paket_Draft`, `N_SOP_Paket_Draft_Item`, `N_SOP_Paket_Draft_Item_Versi`,
`N_SOP_Paket_Draft_Section`, `N_SOP_Paket_Item`, `N_SOP_Paket_Section`,
`N_SOP_Paket_Versi`, `N_SOP_Paket_Versi_Item`, `N_SOP_Penjadwalan`,
`N_SOP_Penjadwalan_Detail`, `N_SOP_Penjadwalan_Integrasi`, `N_SOP_Progress`,
`N_SOP_Setting`, `N_SOP_Kategori` — 25 di antaranya terdeteksi dirujuk kode,
ditambah 3 yang tidak tertangkap pemindai nama tapi jelas milik modul yang sama:
`N_SOP_Aktivitas_Log`, `N_SOP_Kuis_Integrasi`, `N_SOP_Prioritas`.

`N_SOP_Item_Versi` adalah inti siklus versi — tanpa itu, dari 11 tabel yang sudah
terpasang pun hampir tidak ada yang bisa dipakai. Modul ini praktis **belum
dirilis**, bukan sekadar kurang beberapa tabel.

### 1b. Registry API & Pengumpulan Data — 9 tabel

`N_HRIS_Api_Aplikasi`, `N_HRIS_Api_Endpoint`, `N_HRIS_Api_Akses_Endpoint`,
`N_HRIS_Api_Log`, `N_HRIS_Api_Nonce`, `N_HRIS_Pengumpulan_Data_Audit`,
`N_HRIS_Pengumpulan_Data_Config`, `N_HRIS_Pengumpulan_Data_Status`,
`N_HRIS_Pengumpulan_Data_Submission`.

### 1c. RBAC generasi kedua — 4 tabel

`HRIS_Role_Menu_Access`, `HRIS_Role_Konten_Access`, `HRIS_Page_Access_2`,
`HRIS_Klasifikasi_Aksi`.

### 1d. Lain-lain — 3 tabel

`N_HRIS_Bypass_Cuti_Tutup_Buku`, `N_HRIS_Master_Konversi_Lembur`,
`N_KPI_Target_Koreksi`.

### 1e. Bukan kekurangan — 8 tabel

`HRIS_KANDIDAT_Jenis_Indikator`, `HRIS_KANDIDAT_Jenis_Soal`,
`HRIS_KANDIDAT_Menus`, `N_HRIS_KANDIDAT_Akses_Endpoint`,
`N_HRIS_KANDIDAT_Api_Aplikasi`, `N_HRIS_KANDIDAT_Api_Logs`,
`N_HRIS_KANDIDAT_Api_Nonces`, `N_HRIS_KANDIDAT_Endpoint_Api` — milik aplikasi
rekrutmen, memang tidak berjalan di production HCIS.

### 1f. Bukan kekurangan — 43 tabel `N_PA_*`

Seluruh modul Performance Appraisal ada di development tapi **kodenya tidak ada
di cabang `master`** — `grep -r "N_PA_"` di `app/`, `routes/`, dan `database/`
mengembalikan 0 hasil, dan tidak ada satu pun berkas di bawah `app/` yang
namanya memuat `Appraisal` atau berada di folder `PA/`. Skema itu milik cabang
yang belum dirilis. Jangan diikutkan ke DDL production.

---

## 2. Kolom yang hilang di production

21 objek punya kolom yang ada di dev tapi tidak di production. Yang berdampak
diverifikasi langsung ke production dengan `SELECT TOP 0 <kolom> FROM <tabel>` —
seluruhnya menjawab `Invalid column name`.

### 2a. Terbukti merusak halaman produksi — WAJIB

| Tabel | Kolom hilang | Dampak |
| --- | --- | --- |
| `KPI_Update_Performance_Detail` | `Score_Raw` float, `Achievement_Raw` float, `Flag_Capped` char(1) NOT NULL | `PerformanceApprovalController.php:1196` dan `KpiVerifikasiController.php:401` **membaca** kolom ini; `PerformanceApprovalController.php:2304` **menulisnya**. Layar approval KPI, layar verifikasi, dan edit nilai aktual semuanya 500. |
| `KPI_Update_Performance_Detail_Sementara` | `Score_Raw`, `Achievement_Raw`, `Flag_Capped` | Jalur sementara dari fitur yang sama. |
| `HRIS_Hak_Cuti_Tambahan` | `Status` char(1), `Created_At`, `Created_By`, `Updated_At`, `Updated_By` | `KetentuanCutiController::store()` menulis kelimanya; `HakCutiTambahan` command memfilter `whereNull('Status')`. Tambah/ubah ketentuan hak cuti tambahan gagal. |
| `Karyawan` | `Tanggal_Akhir_Probation` datetime | Sudah tercatat sebagai **DDL-2026-091** (belum dijalankan). Dua widget dashboard cuti error. |
| `N_HRIS_Cuti_Bersama` | `Flag_Cancelled` char(1) | `KalenderOperasionalService.php:124` memfilter dan menyeleksinya. Kalender operasional gagal. |

### 2b. Ada di dev, tidak dipakai kode — tidak mendesak

`Karyawan.max_Cuti`, `Karyawan.Timezone`, `Karyawan.Flag_Probation`,
`HRIS_Level.Flag_Harian`, `KPI_Target_Breakdown.Flag_Aktif`,
`Transaksi_Cuti_Det.Jenis_Tanggal_Pengajuan`,
`N_HRIS_Absen_Snapshot.Koefisien_OT_Gross`, `Whatsapp_Log.Kode_Karyawan_Sender`,
`View_Golongan_Sub_Golongan_Level_Jabatan.Flag_Harian`,
`View_Golongan_Sub_Golongan_Level_Jabatan.flag_extra_meal`.

Sudah dicek satu per satu: tidak ada kueri di `app/` yang menyentuhnya
(`Timezone` yang muncul di grep milik tabel activity/konfigurasi, bukan
`Karyawan`; `Flag_Harian` hanya muncul di docblock).

### 2c. Kolom pada modul yang memang belum rilis

`N_EMI_VISITOR_Transaksi_Permit` (`Sumber_Pengajuan`, `Id_Api_Aplikasi`,
`Referensi_Mitra`), `N_EMI_VISITOR_Transaksi_Permit_Detail` (6 kolom),
`N_EMI_VISITOR_Transaksi_Tamu_Detail` (2 kolom), `N_VISITOR_Permit_Efektif`
(5 kolom), `N_HRIS_Master_Entitas` (`Logo_Path`, `Kode_Sebagai_Pemilik`),
`N_SOP_Master_Jenis_Dokumen` (`Masa_Berlaku_Bulan`, `Siklus_Review_Bulan`).

Kolom Visitor bergantung pada registry API (§1b) yang tabelnya belum ada, jadi
harus menyusul bersama modulnya, bukan sendirian.

### 2d. Tabel kembar dengan penamaan berbeda — bukan kekurangan

`N_HRIS_Approval_Request` ada di kedua sisi dengan **jumlah kolom yang sama (16)
tapi enam nama berbeda**:

| Development | Production |
| --- | --- |
| `id` | `Id_Approval_Request` |
| `Kode_Karyawan` | `Kode_Karyawan_Requester` |
| `Flow_Id` | `Approval_Flow_Id` |
| `angka_wa` | `Notif_Counter` |
| `created_at` | `Created_At` |
| `update_at` | `Updated_At` |

Sapuan otomatis melaporkan enam "kolom hilang" dan satu "PK hilang" di sini —
semuanya salah baca. Tabel ini juga tidak dirujuk kode mana pun (dev 12 baris,
prod 0 baris); yang dipakai aplikasi adalah `HRIS_Approval_Request` tanpa awalan
`N_`. **Jangan dibuatkan DDL.**

Dua objek lain masuk hitungan 21 tapi tidak dipakai kode:
`N_HCIS_Tabel_Absen.Koefisien_OT_Gross` dan
`N_HRIS_VW_Src_Tukar_Hari.Created_At`.

### 2e. Perbedaan tipe data — 26 objek

Sebagian besar production justru **lebih lebar** (aman). Yang perlu diperiksa
arahnya sebelum disamakan:

| Objek | Kolom | Dev | Production |
| --- | --- | --- | --- |
| `Karyawan` | `Kode_Karyawan` | varchar(255) | varchar(15) |
| `Karyawan` | `Nama` | varchar(255) | varchar(50) |
| `KPI_Users` | `Kode_Users` | varchar(max) | varchar(30) |
| `KPI_Pengajuan` | `Alasan_Reject` | varchar(500) | varchar(300) |
| `Absen_SN` | `Output` | varchar(10) | varchar(5) |

Pelebaran di dev pada `Karyawan.Kode_Karyawan`/`Nama` kemungkinan besar artefak
development, bukan kebutuhan production — jangan disamakan tanpa alasan. Yang
lebih layak ditindak adalah `KPI_Pengajuan.Alasan_Reject`: validasi aplikasi
mengizinkan teks lebih panjang daripada yang muat di production.

Sembilan **view** (`N_HRIS_VW_All_Transactions`, `..._Admin`,
`N_HRIS_VW_Approval_Request`, `..._V2`, `N_HRIS_VW_Approval_Tracking_Generic`,
`N_HRIS_VW_Saldo_Cuti_Karyawan`) juga berbeda tipe kolomnya. Ini berarti
**definisi view-nya berbeda**, bukan sekadar lebar kolom. Yang paling mencolok:
`N_HRIS_VW_Approval_Tracking_Generic.Deskripsi` int di dev tapi nvarchar(max) di
production. Perlu `CREATE OR ALTER VIEW`, bukan `ALTER TABLE`.

---

## 3. Indeks yang hilang di production — 35 indeks pada 29 tabel

Perbandingan memakai **daftar kolom kunci**, bukan nama indeks, karena nama
otomatis (`PK__xxx__hash`) selalu berbeda antar server.

Sebelum daftar ini dianggap final, setiap kandidat diuji sekali lagi terhadap
**prefiks kiri**: indeks production yang kuncinya lebih panjang tetap melayani
kueri atas kunci yang lebih pendek. Enam kandidat gugur karena itu dan **bukan**
kekurangan:

| Indeks dev | Sudah dilayani indeks production |
| --- | --- |
| `IX_ApprovalDetail_Request (Id_Approval_Request)` | `UQ_Approval_Detail_Unique (Id_Approval_Request, Source_Row_Key)` |
| `IX_TSH_NoTransaksi (No_Transaksi)` | `IX_..._NoTransaksi_Status (No_Transaksi, Status)` |
| `IX_LMS_Penjadwalan_Karyawan (Kode_Karyawan)` | `IX_..._Karyawan_Aktif_Paket_Enroll (Kode_Karyawan, …)` |
| `IX_LMS_Penjadwalan_Det_Induk (Id_Penjadwalan_Detail)` | `IX_..._Detail_Selesai (Id_Penjadwalan_Detail, Flag_Selesai)` |
| `IX_LMS_Penjadwalan_Detail_Induk (Id_Penjadwalan)` | `IX_..._Penjadwalan_Detail (Id_Penjadwalan, Id_Penjadwalan_Detail)` |
| `IX_TerlambatPulang_Kode_Jenis_Status_Date` | `IX_TPC_Detail_Karyawan_Jenis_Approval_TglHariKerja (… + Tanggal_Masuk_Pulang)` |

Yang tersisa, diurutkan menurut jumlah baris production:

| Tabel | Baris di prod | Indeks hilang |
| --- | ---: | --- |
| `N_HRIS_Transaksi_Shift_Detail` | 52.931 | `IX_N_HRIS_ShiftDetail_NoTrans_Karyawan (No_Transaksi, Kode_Karyawan)` |
| `N_HRIS_Status_Hari_Karyawan` | 49.237 | `IX_StatusHari_Lookup (Kode_Perusahaan, Kode_Karyawan, Tgl_Mulai, Tgl_Selesai, Hari, Flag_Aktif)` |
| `N_LMS_Penjadwalan_Syarat` | 40.802 | `IX_LMS_Penjadwalan_Syarat_Induk (Id_Penjadwalan_Det)` |
| `Transaksi_Lembur_Det` | 32.342 | `IX_Transaksi_Lembur_Det_NoTrans_Kode (No_Transaksi, Kode_Karyawan, Tanggal_Lembur)` |
| `Transaksi_Lembur_Detail` | 32.315 | `IX_Transaksi_Lembur_Detail_Kode_Tgl (Kode_Karyawan, Tanggal_Lembur_Dari, Tanggal_Lembur_Sampai)` |
| `HRIS_Approval_Request_Detail` | 27.871 | **3 indeks**: `_Source (Source_View, Source_Row_Key)`, `_ApproverFilter (Processed_By_Kode, Processed_At)`, `_KaryawanTanggal (Kode_Karyawan, Tanggal_Item)` |
| `N_HRIS_Transaksi_Shift` | 26.955 | `_Created (Created_By, Created_At)`, `_Status (Status, Flag_Selesai)` |
| `N_HRIS_Transaksi_Status_Hari_Detail` | 22.738 | `IX_TSHD_NoTransaksi`, `IX_TSHD_KodeKaryawan` |
| `N_LMS_Penjadwalan` | 9.097 | `IX_LMS_Penjadwalan_Paket_Enroll_Karyawan (Id_Master_Paket, Waktu_Enroll, Kode_Karyawan)` |
| `KPI_Update_Performance_Detail` | 6.254 | `IX_KPI_Update_Perf_Det_Join_Pica (Id_Update_Performance, Id_Pengajuan_Det, Flag_Pica)` |
| `HRIS_Approval_Flow` | 4.310 | **seluruhnya** — lihat catatan di bawah |
| `KPI_Update_Performance` | 1.417 | `_Covering_Filter`, `_User_Periode` |
| `N_HRIS_Async_Tasks` | 242 | `IX_N_HRIS_Async_Tasks_Reference (Task_Type, Reference_Id, Status)` |

Sisanya (16 tabel, ≤ 409 baris) berdampak kecil: `N_HRIS_Konversi_Lembur_Efektif`,
`N_HRIS_Transaksi_Approver_Shift(_Detail)`, `N_LMS_Master_Konten_Berkas`,
`KPI_User_Akses_Divisi`, `KPI_User_Akses_Level`, `KPI_Master_Satuan(_Formula)`,
`KPI_Master_Formula`, `N_HRIS_Role_Permission`,
`N_LMS_Master_Keterangan_Kelulusan`, plus tabel Visitor/KANDIDAT yang kosong.

**`HRIS_Approval_Flow` di production adalah HEAP tanpa satu pun indeks.** Bukan
sekadar kehilangan primary key clustered: `sys.indexes` untuk tabel itu hanya
mengembalikan satu baris `HEAP`, sementara dev punya PK clustered pada `id`.
4.310 baris, dibaca setiap kali rantai approval dievaluasi. Ini butir indeks
yang paling layak didahulukan.

Berikutnya tiga indeks `HRIS_Approval_Request_Detail` (27.871 baris) — tabel itu
dipakai `ApprovalDetailService::itemsForApprover()` di setiap pembukaan inbox
approval.

---

## 3b. Rutin, trigger, dan constraint

### Prosedur & fungsi — 14 hilang di production

Dev punya 30 rutin (16 SP, 8 inline TVF, 5 scalar, 1 TVF); production punya 20
(15 SP, 3 inline TVF, 2 scalar).

Yang hilang: `fn_NWC_Tambah_Hari_Kerja`, `n_GetStatusHari`,
`N_HRIS_FN_GetStatusHari_Inline`, `fn_GetShiftAbsen`, `fn_GetJadwalHarian_Range`,
`fn_GetShiftLengkap_Range`, `fn_GetStatusHariRange`,
`HRIS_SP_GET_ABSEN_NEW_V2_Trial2`, `HRIS_SP_GET_ABSEN_NEW_V2_Trial3`,
`HRIS_SP_GET_ABSEN_NEW_FIX_WIT_WITA`, `HRIS_SP_GET_ABSEN_NEW_V2_OvertimeShift`,
`N_HRIS_SP_MAINTAIN_ABSEN_QUEUE`, `TVF_HRIS_HolidayByKaryawanRange`, dan satu
fungsi bernama `n`.

Seluruh rutin yang **benar-benar dipanggil kode** sudah dicek satu per satu dan
**semuanya ada** di production: `N_HRIS_FN_GetStatusHari`,
`N_HRIS_FN_GetStatusHariRange`, `N_HRIS_FN_GetAvailableShift`,
`N_HRIS_Cuti_Process_Batch(_Detail)`, `HRIS_SP_GET_ABSEN_NEW_V3`,
`N_HRIS_SP_MARK_ABSEN_DIRTY`, `N_HRIS_SP_ABSEN_MAINTENANCE`,
`N_HRIS_SP_GET_ABSEN_WEB_WRAPPER`, `N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE`,
`N_HRIS_SP_PROCESS_ABSEN_RECALC_QUEUE`.

Satu pengecualian perlu dicatat, dan ternyata **bukan** kerusakan produksi:
`OvertimeController::userActive()` (baris 1187) dan `userActiveAll()` (baris
1370) memanggil `dbo.fn_GetShiftLengkap_Range(...)` yang tidak ada di
production — tapi kedua metode itu **tidak punya rute** (`routes/` hanya
menunjuk `index2`, `getData`, `getDataAdmin`, `getDetails`, `getExport`,
`getExportAdmin`, `getShift`, `getWaktuShift`, `destroy`, `destroyUser`) dan
tidak dipanggil dari mana pun. Jalur lembur yang hidup lewat
`LemburRequestService`, yang memakai `N_HRIS_FN_GetStatusHari(Range)` — keduanya
ada di production. Jadi ini **kode mati**, bukan bug produksi; layak dibersihkan
dari repo, bukan dibuatkan DDL.

Isi rutin tidak bisa dibandingkan (`sys.sql_modules.definition` NULL, lihat batas
izin di atas). Yang dijamin laporan ini hanya keberadaannya.

### Trigger — 3 hilang

| Tabel | Trigger |
| --- | --- |
| `KPI_Approval_Request_Performance` | `KPI_Approval_Request_Performance_trg_UpdateTimestamp` |
| `KPI_Approval_Request_Parameter` | `KPI_Approval_Request_trg_UpdateTimestamp` |
| `KPI_Karyawan_Team` | `KPI_Karyawan_Team_trg_UpdateTimestamp` |

Ketiganya pemelihara kolom timestamp. Tanpa trigger, kolom `Updated_At` pada
tabel-tabel itu tidak terisi otomatis di production.

### Foreign key — 8 hilang pada 6 tabel

| Tabel | FK |
| --- | --- |
| `HRIS_Approval_Request_Detail` | `Id_Approval_Request → HRIS_Approval_Request`, `Id_Jenis_Approval → N_HRIS_Jenis_Approval` |
| `N_HRIS_Menu_Capabilities` | `Jenis_page → N_HRIS_Menus`, `Id_Aksi → HRIS_Klasifikasi_Aksi` |
| `N_SOP_Dokumen` | `Id_Versi_Aktif → N_SOP_Item_Versi` |
| `N_SOP_Item_Versi_Pengesah` | `Id_SOP_Item_Versi → N_SOP_Item_Versi` |
| `N_SOP_Item_Versi_Review` | `Id_SOP_Item_Versi → N_SOP_Item_Versi` |
| `HRIS_KANDIDAT_Users` | `Kode_Role → N_HRIS_KANDIDAT_User_Role` |

Lima di antaranya menunjuk tabel yang memang belum ada di production
(`N_SOP_Item_Versi`, `HRIS_Klasifikasi_Aksi`) — akan ikut sendiri saat modulnya
dirilis. Yang berdiri sendiri hanya dua FK `HRIS_Approval_Request_Detail`.

### Default constraint — 19 pada 12 tabel; check constraint — 1

Yang perlu diperhatikan karena kolomnya sudah ada di production:

- `Karyawan`: `Aktif`, `Email`, `Email_Backup`, `Tgl_Masuk`
- `HRIS_Approval_Request_Detail`: `Created_At`
- `HRIS_Shift_Kerja_Detail`: `Flag_Default_Off`
- `HRIS_Page_Access`: `Flag_Aktif`
- `N_LMS_Master_Konten_Berkas`: satu check constraint pada `Status_Verifikasi`

Sisanya menempel pada kolom yang memang belum ada di production (§2a/§2b) dan
harus dibuat bersama kolomnya, bukan terpisah.

Sekali lagi: hanya **keberadaan** default/check yang dibandingkan. Isi
ekspresinya tidak terbaca dari login ini, jadi default yang ada di kedua sisi
tetapi berbeda nilainya **tidak akan tertangkap laporan ini**.

---

## 4. Isi master yang kurang di production

Perbandingan memakai **kunci bisnis**, bukan PK identity — Id identity berbeda
antar server sehingga selisih Id bukan selisih isi.

### 4a. Master kosong sama sekali di production

| Tabel | Dev | Prod | Catatan |
| --- | ---: | ---: | --- |
| `N_HRIS_Master_Departemen` | 52 | **0** | Halaman `systemMasterDepartemenPage` tidak punya data sama sekali. Kode: PPIC, IA, FIN, ACC, TAX, PROC, IT, LGL, CQM, SAL, DIS, MT, GT, EXT, COPL, MDEV, MCOM, TDEV, FOR, RAD, QAL, KEA, MTP, ECO, MTA, HC, HCS, TPM, TLD, GA, IER, PROD, WFP, DFP, WH, RWH, PFWH, ENM, SHE, CI, … (52) |
| `N_HRIS_Master_Entitas` | 4 | **0** | EHG, ENB, EMI, GMN |
| `N_SOP_Master_Jenis_Dokumen` | 8 | **0** | CNTH, KEBIJAKAN, MANUAL, SURAT_KEPUTUSAN, PROSEDUR, INSTRUKSI_KERJA, STANDAR, FORMULIR |
| `N_SOP_Master_Tahap_Versi` | 8 | **0** | HOLD, DRAFTING, REVIEW, APPROVAL, CANCEL, APPROVED, FILING_REGISTERED, OBSOLETE |
| `N_SOP_Master_APQC` | 14 | **0** | Kode proses 01–14 |
| `N_HRIS_Role_Konten_Access` | 56 | **0** | |

Tiga master SOP itu tabelnya **sudah ada** di production tapi isinya kosong —
tanpa DML, mesin siklus versi SOP tidak punya satu pun tahap yang sah.

**Kelimanya sebenarnya satu perkara, bukan lima.** Ditelusuri lewat
`sys.foreign_keys` di production, seluruh master ini hanya dikonsumsi modul SOP:

| Master | Dirujuk FK oleh |
| --- | --- |
| `N_HRIS_Master_Departemen` | `N_SOP_Dokumen` |
| `N_HRIS_Master_Entitas` | `N_SOP_Dokumen` |
| `N_SOP_Master_Jenis_Dokumen` | `N_SOP_Dokumen`, `N_SOP_Master_Jenis_Dokumen_Tipe_Item` |
| `N_SOP_Master_APQC` | `N_SOP_Dokumen`, `N_SOP_Master_APQC_Tipe_Item` |
| `N_SOP_Master_Tahap_Versi` | `N_SOP_Master_Tahap_Versi_Syarat`, `..._Transisi` |

`N_SOP_Dokumen` di production **0 baris**. `Karyawan` di production tidak punya
kolom departemen sama sekali, dan `N_HRIS_Master_Entitas` hanya disentuh berkas
di bawah `app/**/SOP/`. Jadi menjalankan DML master ini **sebelum** modul SOP
dirilis tidak memperbaiki apa pun — ia bagian dari paket rilis SOP, bukan
perbaikan berdiri sendiri. Ini mengoreksi kesan urgensi di versi awal laporan.

### 4b. Navigasi & RBAC

Kolom "baris" adalah `COUNT(*)` mentah; kolom "kunci kurang" memakai kunci
bisnis yang unik, sehingga bisa lebih kecil dari selisih baris kalau ada duplikat
(mis. `N_HRIS_Role_Scope` 17 baris tapi 15 pasangan unik).

| Tabel | Baris dev | Baris prod | Kunci kurang | Keterangan |
| --- | ---: | ---: | ---: | --- |
| `N_HRIS_Menus` | 103 | 72 | 33 | **10 punya kode di `master`** (lihat bawah), 23 milik cabang belum rilis |
| `N_HRIS_Grup_Fitur_Map` | 103 | 75 | 43 | Didominasi SOP (14 entri) dan PA (13 entri, belum rilis) |
| `N_HRIS_Menu_Capabilities` | 189 | **3** | 185 | Production praktis tidak punya definisi kapabilitas menu |
| `N_HRIS_Role_Functional` | 27 | 19 | 8 | ADMIN_HC_CUTI_VERIFIKATOR, ESS_Approval_Administrator_PHL, ESS_Menu_Administrator_PHL, ESS_MENU_APPROVAL_ADMINISTRATOR, KPI_ADMIN_PHL, KPI_MENU_APPROVAL_ADMINISTRATOR, LMS_MENU_APPROVAL_ADMINISTRATOR, VISITOR_MENU_APPROVAL_ADMINISTRATOR |
| `N_HRIS_Role_Scope` | 17 | 11 | 12 | Mengikuti role di atas |
| `N_HRIS_Role_Resource_Access` | 48 | 20 | 32 | Mengikuti role di atas |
| `N_HRIS_Role_Menu_Access` | 2.005 | 4 | — | Lihat catatan |

**Menu yang hilang DAN kodenya ada di `master`** (10) — ini yang benar-benar
perlu di-seed:
`superAdminManajemenPeranPage`, `MyActivity`, `ActivityMonitor`,
`menuSopPrioritasPage`, `visitorApprovalPage`, `approvalFlowShift`,
`adminPengumpulanDataPage`, `adminKPIKaryawanTeamPage`, `adminHCApprovalKPIPage`,
`adminAllPagesAccess`.

Sisanya (23) — seluruh `Pa*`/`PerformanceAppraisal*`, `builderPerancangPage`,
`systemRelasiOrganisasiPage`, `globalDashboardKehadiran`, `PaDistribusiPage` —
tidak punya kode di cabang ini. **Jangan di-seed.**

Catatan `N_HRIS_Role_Menu_Access`: perbandingan kunci di sini tidak valid, karena
`Id_Page_Access` merujuk `HRIS_Page_Access` yang di production justru punya
**11.540** baris melawan 3.026 di dev. Yang bisa dipastikan hanya bahwa dev punya
2.005 baris dan production punya 4. Konsisten dengan catatan arsitektur bahwa
role di HCIS bekerja sebagai keanggotaan grup dan akses per-menu granular belum
dipakai — **perlu dikonfirmasi ke pemilik modul sebelum dibuatkan DML**, bukan
langsung disalin.

### 4c. Divergensi isi, bukan kekurangan

`HRIS_Sub_Divisi` (dev 32 / prod 38), `N_HRIS_Jenis_Cuti` (10/9),
`N_HRIS_Jenis_Approval` (10/6), `KPI_Master_Satuan` (15/21),
`N_EMI_VISITOR_Master_Kategori_Permit` (5/4), `KPI_Master_Formula` (3/3).

Pada semua tabel ini **kedua sisi punya baris yang tidak ada di sisi lain**.
Production menyimpan data operasional nyata; dev menyimpan hasil coba-coba
(mis. "Cuti Healing", "Cuti Wisuda", satuan "Skala"/"YEN"). Menyalin dev ke
production akan menambah sampah, bukan menutup kekurangan. **Tidak
direkomendasikan tanpa keputusan pemilik data.**

---

## 4d. Silang dengan tracker DDL proyek


`database/ddl/status.json` mencatat **88 perubahan**. Status sisi production:

| Status production | Jumlah |
| ---: | --- |
| `PENDING` | 84 |
| `CANCELLED` | 4 |
| `DONE` | **0** |

**Belum satu pun perubahan dari alur DDL proyek pernah dijalankan di
production.** Ini penjelasan struktural untuk hampir seluruh isi laporan: yang
kita sebut "kekurangan" sebagian besar adalah tumpukan 84 tiket yang tidak
pernah sampai ke sana, bukan kerusakan yang muncul sendiri.

Tapi silang itu juga membuka masalah kedua, yang justru lebih penting untuk
diketahui sebelum menulis DDL susulan:

| Perkara | Sudah punya artefak DDL? |
| --- | --- |
| `Score_Raw`, `Achievement_Raw`, `Flag_Capped` | Ya — `DDL-2026-045-kpi-cap-per-parameter` |
| `Karyawan.Tanggal_Akhir_Probation` | Ya — `DDL-2026-091` |
| Tabel SOP (`N_SOP_Item_Versi`, `N_SOP_Master_Paket`, `N_SOP_Penjadwalan`, …) | Ya — DDL-2026-032/053/059/014 dan lainnya |
| `N_HRIS_Master_Departemen` (tabel) | Ya — `DDL-2026-027` (tabel saja, **tanpa `INSERT`**) |
| `N_HRIS_Cuti_Bersama.Flag_Cancelled` | **Tidak ada** |
| 5 kolom `HRIS_Hak_Cuti_Tambahan` | **Tidak ada** |
| **Ke-35 indeks di §3** | **Tidak ada satu pun** |
| 3 trigger, 8 FK, 19 default | **Tidak ada** |

Artinya ke-35 indeks itu dipasang langsung di development tanpa melewati alur
DDL, sehingga production memang tidak pernah punya kesempatan menerimanya. DDL
untuk kelompok-kelompok bertanda "tidak ada" harus **ditulis baru**, bukan
tinggal dijalankan.

Catatan untuk `N_HRIS_Master_Departemen`: `DDL-2026-027/forward.sql` hanya
`CREATE TABLE` — nol pernyataan `INSERT`. Isi 52 departemen di development
dimasukkan di luar alur, jadi tidak ada artefak DML yang bisa dipakai ulang.

---

## 5. Ringkasan prioritas

| # | Perkara | Jenis | Urgensi |
| --- | --- | --- | --- |
| 1 | 3 kolom `Score_Raw`/`Achievement_Raw`/`Flag_Capped` di `KPI_Update_Performance_Detail` + `_Sementara` | DDL | **Kritis** — approval & verifikasi KPI 500 |
| 2 | 5 kolom audit + `Status` di `HRIS_Hak_Cuti_Tambahan` | DDL | **Kritis** — CRUD ketentuan cuti gagal |
| 3 | `Karyawan.Tanggal_Akhir_Probation` (DDL-2026-091 sudah dibuat) | DDL | **Kritis** — dashboard cuti |
| 4 | `N_HRIS_Cuti_Bersama.Flag_Cancelled` | DDL | **Kritis** — kalender operasional |
| 5 | `HRIS_Approval_Flow` HEAP tanpa indeks apa pun (4.310 baris) | DDL | Tinggi |
| 6 | 3 indeks `HRIS_Approval_Request_Detail` (27.871 baris) | DDL | Tinggi — inbox approval |
| 7 | 8 indeks lain pada tabel > 20.000 baris | DDL | Tinggi |
| 8 | 3 trigger `*_trg_UpdateTimestamp` di tabel KPI | DDL | Sedang — `Updated_At` tak terisi |
| 9 | DML `N_HRIS_Master_Departemen` (52) + `N_HRIS_Master_Entitas` (4) | DML | **Ikut rilis SOP**, bukan berdiri sendiri (§4a) |
| 10 | 10 menu + peta grup fitur yang kodenya sudah rilis | DML | Sedang |
| 11 | 8 role fungsional + scope + resource access | DML | Sedang |
| 12 | 2 FK `HRIS_Approval_Request_Detail` + 8 default constraint pada kolom yang sudah ada | DDL | Sedang |
| 13 | 28 tabel SOP + DML 5 master pendukungnya (§4a) | DDL+DML | Rilis modul tersendiri |
| 14 | 9 tabel registry API + kolom Visitor yang bergantung padanya | DDL | Rilis modul tersendiri |
| 15 | 4 tabel RBAC generasi kedua | DDL | Perlu keputusan pemilik modul |
| 16 | Definisi 6 view `N_HRIS_VW_*` yang berbeda tipe kolomnya | DDL (view) | Perlu penelusuran |
| 17 | `KPI_Pengajuan.Alasan_Reject` 300 → 500 | DDL | Rendah |
| 18 | Buang `OvertimeController::userActive()`/`userActiveAll()` — kode mati pemanggil `fn_GetShiftLengkap_Range` | Kode | Rendah |

**Tidak boleh masuk DDL production:** 43 tabel `N_PA_*`, 8 tabel `HRIS_KANDIDAT_*`,
23 menu cabang belum rilis, tabel `N_HRIS_Approval_Request` (§2d), 13 rutin
warisan yang tak dipanggil kode, dan seluruh isi §4c.

---

## Catatan metode

- Semua kolom pada §2a dibuktikan langsung ke production lewat `SELECT TOP 0`,
  dan seluruhnya menjawab `Invalid column name`.
- Perbandingan indeks memakai **daftar kolom kunci**, bukan nama indeks (nama
  otomatis `PK__xxx__hash` selalu berbeda antar server), lalu setiap kandidat
  diuji ulang terhadap **prefiks kiri** indeks production yang lebih panjang —
  enam kandidat gugur di tahap ini.
- Rutin, trigger, dan foreign key dibandingkan lewat keberadaan di `sys.objects`,
  `sys.triggers`, dan `sys.foreign_keys`. Untuk rutin, keberadaan itu dicek dua
  arah: 14 hilang dari production, tapi **12 rutin yang benar-benar dipanggil
  kode semuanya ada**.
- Jumlah baris production diambil dengan `COUNT(*)`, bukan `sys.partitions`, agar
  tidak terkena penggandaan oleh allocation unit LOB.

### Yang laporan ini TIDAK bisa jamin

**"Hilang" tidak otomatis berarti "dibutuhkan".** Laporan ini membuktikan apa
yang ada di development tapi tidak ada di production. Yang menjadikannya
kebutuhan adalah tiga hal berbeda, dan hanya dua yang bisa dibuktikan dari sini:

1. *Kodenya di `master` menyentuhnya* — ini dibuktikan (grep + verifikasi rute).
2. *Skema production menolak kueri kode itu* — ini dibuktikan untuk §2a lewat
   `SELECT TOP 0` yang menjawab `Invalid column name`.
3. *Bisnis memang mau fitur itu hidup di production sekarang* — **ini tidak bisa
   dibuktikan dari database dan bukan keputusan laporan ini.** SOP, registry API,
   dan RBAC generasi kedua masuk kategori ini: kodenya ada dan skemanya kurang,
   tapi apakah modulnya memang mau dirilis sekarang adalah keputusan pemilik
   modul.

Empat butir §2a adalah satu-satunya kelompok yang ketiga syaratnya jelas
terpenuhi: fiturnya sudah dipakai di production hari ini, dan kolomnya tidak
ada.

Juga tidak diverifikasi dengan menjalankan halaman production sungguhan.
Kesimpulan "layar approval KPI 500" berasal dari membaca kueri di kode dan
membuktikan kolomnya tidak ada — bukan dari membuka halamannya.

Karena login production tanpa `VIEW DEFINITION`:

- **Isi** check constraint, default constraint, filter indeks, definisi view, dan
  badan prosedur/fungsi tidak terbaca. Objek yang ada di kedua sisi tetapi
  **berbeda isinya** tidak akan muncul di laporan ini.
- Untuk memastikannya perlu satu kali pembacaan dengan login ber-`VIEW
  DEFINITION` (cukup `GRANT VIEW DEFINITION TO <login>` — tetap read-only, tidak
  mengubah data maupun struktur), atau skrip skema yang di-generate dari sisi
  DBA.

**Collation sudah diperiksa dan bersih.** Collation database identik di kedua
sisi (`SQL_Latin1_General_CP1_CI_AS`), dan dari 51 kolom dev yang memakai
collation non-standar (`Latin1_General_BIN`, `Latin1_General_CI_AS_KS_WS`,
`SQL_Latin1_General_CP437_CS_AS`) tidak satu pun berada di tabel yang juga ada di
production — **0 selisih collation pada objek bersama**.

Belum diperiksa sama sekali: statistik, partisi, kompresi, dan pengaturan level
database (termasuk RCSI, yang sudah tercatat terpisah sebagai DDL-2026-067).
