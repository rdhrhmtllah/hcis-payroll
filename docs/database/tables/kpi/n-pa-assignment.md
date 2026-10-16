# N_PA_Assignment

Satu penilai ditugaskan menilai satu peserta. Inilah baris yang menentukan siapa
mengisi form untuk siapa, dan dengan bobot berapa di dalam kategorinya.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-075-performance-appraisal-assignment |
| Forward SQL | `database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Participant_Id` | `int` | T | | Yang **dinilai** |
| `Kode_Perusahaan_Penilai` | `varchar(3)` | T | | Yang **menilai**, bagian pertama |
| `Kode_Karyawan_Penilai` | `varchar(255)` | T | | Bagian kedua; bersama-sama FK komposit ke `Karyawan` |
| `Nama_Penilai_Saat_Cycle` | `varchar(255)` | T | | Beku, supaya laporan lama tetap terbaca |
| `PA_Rater_Category_Id` | `int` | T | | `SELF` / `ATASAN` / `BAWAHAN` / `PEER` |
| `Bobot_Dalam_Kategori` | `decimal(5,2)` | T | | Bobot penilai ini relatif terhadap penilai lain **di dalam** kategori yang sama |
| `Sumber_Penugasan` | `varchar(20)` | T | `'USULAN'` | FK ke `N_PA_Assignment_Source.Kode_Sumber` |
| `Organization_Relationship_Id` | `int` | Y | | Jejak baris relasi yang jadi dasar usulan; kosong saat manual |
| `Alasan_Manual` | `nvarchar(500)` | Y | | Wajib diisi aplikasi saat sumbernya `MANUAL` |
| `Status_Penugasan` | `varchar(20)` | T | `'BELUM_MULAI'` | FK ke `N_PA_Assignment_Status.Kode_Status` |
| `Due_At` | `date` | Y | | |
| `Started_At` | `datetime2(0)` | Y | | Saat penilai membuka form (`penilaian.Waktu_Mulai_Akses` dari HC Learn) |
| `Finished_At` | `datetime2(0)` | Y | | Saat lembar selesai diisi (`penilaian.Waktu_Selesai_Akses`); jam pemetaan hanya cadangan |
| `Submitted_At` | `datetime2(0)` | Y | | Saat lembar dilepas menjadi `SELESAI` (DDL-2026-169). Berbeda dari `Finished_At` (selesai diisi di HC Learn) |
| `Submitted_By` | `int` | Y | | `KPI_Users.Id_Users` yang melepas: penilai, admin, atau pelaku penutupan |
| `Submitted_Via` | `varchar(20)` | Y | | `PENILAI` / `ADMIN` / `PENUTUPAN`; divalidasi aplikasi. NULL untuk lembar yang selesai sebelum DDL-2026-169 |
| `Cancel_Reason` | `nvarchar(500)` | Y | | Alasan penugasan dicabut; tidak boleh hanya spasi |
| `Cancelled_At` | `datetime2(0)` | Y | | Waktu pembatalan |
| `Cancelled_By` | `int` | Y | | `KPI_Users.Id_Users` pelaku pembatalan |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Token ujian tidak di sini

`Id_Ujian_Token` ada di `N_PA_Assignment_Integration`, bukan di tabel ini. Bentuk itu
mengikuti dua modul yang sudah berjalan — `N_LMS_Penjadwalan_Det` dan
`N_SOP_Penjadwalan_Detail` sama-sama **tidak punya kolom token sama sekali**;
tokennya hidup di `N_LMS_Penjadwalan_Integrasi` dan `N_SOP_Penjadwalan_Integrasi`.
Alasannya bukan gaya: satu penugasan bisa punya lebih dari satu baris integrasi ketika
paket soalnya diganti, dan kolom tunggal tidak bisa menampung itu.

## Dua bobot yang mudah tertukar

| Kolom | Di tabel | Bobot antara apa |
|---|---|---|
| `Bobot_Persen` | `N_PA_Cycle_Rater_Policy_Detail_Snapshot` | **Antar kategori**, berlaku selevel |
| `Bobot_Dalam_Kategori` | tabel ini | **Di dalam** satu kategori, berlaku seorang |

Keduanya dikalikan berurutan. Atasan-1 dan Atasan-2 bukan kategori terpisah — keduanya
baris `ATASAN` pada peserta yang sama, dan yang membedakannya kolom ini (K18). Bakunya
bagi rata: satu atasan 100, dua atasan 50/50; admin boleh menimpanya jadi 70/30. Ia
tidak bisa ditaruh di master karena jumlah atasan berbeda-beda per orang dan hanya admin
yang tahu atasan mana yang lebih berbobot.

## Penilaian diri baris biasa

Peserta menilai dirinya sendiri lewat baris di tabel ini juga, kategori `SELF`, penilai
sama dengan peserta. Tidak ada kolom terpisah dan tidak ada tabel terpisah — kalau
dipisah, seluruh kode agregasi harus menangani dua bentuk data untuk hal yang sama.

## Satu orang boleh menilai satu peserta di dua kategori

Keunikannya `(peserta, penilai, kategori)` dan hanya berlaku pada baris aktif. Seorang
rekan yang juga bawahan bisa muncul dua kali dengan kategori berbeda — sudah diuji dan
diterima. Penugasan yang dicabut tidak menghalangi penggantinya.

## Siklus tidak diduplikasi

Sketsa awal membawa `Id_Cycle` di sini. Dibuang: siklusnya sudah melekat pada peserta,
dan menyimpannya dua kali membuka peluang keduanya bertentangan.

## Menunjuk kategori, bukan baris salinan siklus

Sejak K32, bobot antar kategori yang berlaku bagi seseorang bisa datang dari override.
Jadi penugasan cukup menyebut kategorinya, dan bukti "dihitung memakai bobot berapa"
direkam saat perhitungan di `N_PA_Result_Rater_Category` — bukan dibekukan di sini
sebagai angka yang bisa basi.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment` | Primary key | |
| `FK_PA_Assignment_Participant` | Foreign key | → `N_PA_Participant` |
| `FK_PA_Assignment_Penilai` | Foreign key komposit | `(Kode_Perusahaan_Penilai, Kode_Karyawan_Penilai)` → `Karyawan` |
| `FK_PA_Assignment_Category` | Foreign key | → `N_PA_Rater_Category` |
| `FK_PA_Assignment_Sumber` | Foreign key | → `N_PA_Assignment_Source.Kode_Sumber`, menunjuk kode supaya `DEFAULT` tetap bekerja |
| `FK_PA_Assignment_Status_Master` | Foreign key | → `N_PA_Assignment_Status.Kode_Status`, alasan sama |
| `FK_PA_Assignment_Org_Relationship` | Foreign key | → `N_HRIS_Organization_Relationship`, nullable |
| `UX_PA_Assignment_Peserta_Penilai` | Unique index tersaring | `(peserta, perusahaan, karyawan, kategori)` saat aktif |
| `IX_PA_Assignment_Peserta_Status` | Index | Jalur agregasi per peserta, `INCLUDE` kategori, bobot, waktu selesai |
| `IX_PA_Assignment_Penilai_Status` | Index | Jalur "tugas menilai saya", `INCLUDE` peserta dan tenggat |
| `CK_PA_Assignment_Bobot` | Check | 0–100 |
| `CK_PA_Assignment_Rentang_Waktu` | Check | Selesai tidak boleh mendahului mulai |
| `CK_PA_Assignment_Cancel_Reason` | Check | Alasan yang terisi tidak boleh hanya spasi |
| `CK_PA_Assignment_Cancel_Lengkap` | Check | Waktu pembatalan tidak boleh ada tanpa alasan |

## Yang sengaja tidak dijaga database

`Alasan_Manual` wajib saat sumbernya `MANUAL`. Aturan ini dijaga oleh
`PaAssignmentBuilderService`; CHECK-nya hanya bisa ditulis dengan
mengeraskan literal `'MANUAL'` ke dalam skema — persis yang dilarang aturan proyek untuk
daftar nilai bisnis, dan persis yang ingin dihindari dengan membuat masternya.

Σ `Bobot_Dalam_Kategori` per (peserta, kategori) harus 100; melibatkan banyak baris,
jadi divalidasi saat siklus dikunci (G10). Menormalkan ulang sisanya setelah satu
penilai dibatalkan juga pekerjaan aplikasi.

## Batasan yang diketahui

`N_HRIS_Organization_Relationship` masih 0 baris, jadi jalur `USULAN` belum bisa diuji
ujung ke ujung sampai endpoint konverter (K21) ada. FK-nya sudah terpasang dan teruji;
yang belum ada hanya data rekomendasinya.

## Kedudukan

Lapisan pelaksanaan, pintu masuknya.

## Pemakaian aplikasi

`PaAssignmentBuilderService` membuat penugasan, memvalidasi sumber manual/usulan,
menormalkan bobot penilai per kategori, dan membatalkan penugasan tanpa menghapusnya.
`PaPayloadMapperService` memperbarui status saat kontrak jawaban lengkap, sedangkan
`PaResultCalculationService` membaca penugasan selesai untuk perhitungan hasil.

## Keadaan data

Kosong.
