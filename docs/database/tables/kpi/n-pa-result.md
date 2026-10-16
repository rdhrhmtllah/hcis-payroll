# N_PA_Result

Satu baris hasil untuk satu peserta. Lahir bersamaan dengan pesertanya berstatus
`BELUM_DIHITUNG`, bukan setelah perhitungan berhasil.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-080-performance-appraisal-result |
| Forward SQL | `database/ddl/DDL-2026-080-performance-appraisal-result/forward.sql` |
| Konsep | `plans/kpi/2026-09-01-lapisan-hasil-pa-rancangan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Participant_Id` | `int` | T | | Peserta yang hasilnya dicatat |
| `Status_Hasil` | `varchar(20)` | T | `'BELUM_DIHITUNG'` | FK ke `N_PA_Result_Status.Kode_Status` |
| `Nilai_KPI` | `decimal(6,2)` | Y | | `final_pct` dari resolver KPI, **sudah** di-cap |
| `Nilai_KPI_Sebelum_Cap` | `decimal(6,2)` | Y | | `raw_pct`, sebelum cap |
| `KPI_Periode_Dari` / `_Sampai` | `date` | Y | | Rentang bulan yang diakumulasi |
| `Jumlah_KPI_Belum_Disetujui` | `int` | Y | | Jejak gerbang; lebih dari 0 berarti dihitung saat masih ada pengajuan menggantung |
| `Nilai_Kompetensi` | `decimal(6,2)` | Y | | Hasil agregasi 360° |
| `Jumlah_Rater_Diharapkan` / `_Mengisi` | `smallint` | Y | | Ringkasan kelengkapan penilai |
| `Bobot_KPI_Terpakai` / `Bobot_Kompetensi_Terpakai` | `decimal(5,2)` | Y | | Bukti bobot pilar saat dihitung |
| `Final_Score` | `decimal(6,2)` | Y | | **Tanpa plafon 100** |
| `Kode_Grade_Sistem` | `varchar(40)` | Y | | Grade hasil pencarian pita |
| `Kode_Grade_Final` | `varchar(40)` | Y | | Grade setelah kalibrasi |
| `Flag_Dikalibrasi` | `char(1)` | T | `'T'` | |
| `PA_Hav_Quadrant_Id` | `int` | Y | | FK ke kamus sembilan kotak |
| `Alasan_Tertahan` | `nvarchar(500)` | Y | | Wajib lewat aplikasi saat status `TERTAHAN` |
| `Calculation_Version` | `int` | T | `1` | Naik tiap hitung ulang |
| `Calculated_At` / `_By` | `datetime2(0)` / `int` | Y | | |
| `Published_At` / `_By` | `datetime2(0)` / `int` | Y | | Publikasi KE KARYAWAN ada di sini |
| `Published_Leader_At` / `_By` | `datetime2(0)` / `int` | Y | | Publikasi KE LEADER — pintu terpisah (DDL-2026-130) |
| `Unpublished_Leader_At` / `_By` | `datetime2(0)` / `int` | Y | | Pencabutan akses leader |
| `Alasan_Cabut_Leader` | `nvarchar(500)` | Y | | Wajib lewat aplikasi saat rilis leader dicabut |
| `Flag_Aktif` + audit | | | | |

## Dua pintu publikasi, dan kenapa bukan satu status

`Status_Hasil = 'TERBIT'` menjawab satu pertanyaan saja: **karyawan** boleh melihat
hasilnya. Siapa yang boleh melihat **di halaman PA Tim** dijawab `Published_Leader_At`.

Keduanya dipisah sebagai kolom, bukan sebagai status baru di antara `DIHITUNG` dan
`TERBIT`, karena nilai `'TERBIT'` dibaca 35 tempat di 19 berkas — kurva, `pa:verify`,
distribusi, kalibrasi, penerbitan. Menyisipkan status di tengahnya menuntut seluruh
pembaca itu ditinjau ulang demi satu pertanyaan tambahan.

Aturan baca `PaLeaderService`: **`Published_Leader_At IS NOT NULL` ATAU
`Status_Hasil = 'TERBIT'`**. Bentuk "atau" itulah yang membuat seluruh siklus lama tetap
terlihat leader tanpa satu baris DML pun.

## Dua kolom nilai KPI, dan kenapa keduanya perlu

`KpiScoringService::accumulatedScore()` mengembalikan `raw_pct` dan `final_pct`.
Keduanya disimpan.

Jaraknya nyata dan besar: cap KPI adalah `[50, 125]`, dan data development memuat
capaian mentah **177,75** yang menjadi **125** setelah di-cap. Tanpa nilai mentahnya,
pertanyaan "kenapa dia 125 padahal capaiannya 177" hanya bisa dijawab dengan menghitung
ulang — dan menghitung ulang justru yang tidak lagi mungkin begitu data sumbernya
bergerak.

## Nilai KPI diambil YTD, mengikuti periode siklus

Ditetapkan user 1 September 2026. PA **tidak** membaca kolom nilai bulanan mana pun; ia
memanggil `accumulatedScore()` dengan rentang periode siklusnya.

Metode itu menjumlahkan target dan actual per parameter sepanjang rentang, baru
mengevaluasi rumusnya, lalu menormalkan `Σweighted ÷ Σbobot × 100` dan men-cap.
Perhatikan urutannya: **menjumlahkan dulu baru menghitung**, bukan merata-ratakan skor
bulanan. Untuk orang yang capaiannya naik-turun, dua cara itu memberi angka berbeda.

Hanya pengajuan berstatus `disetujui` yang ikut. KPI yang masih `menunggu` bukan angka
yang disepakati siapa pun; kalau ikut dihitung, hasil PA seseorang bisa bergeser sendiri
saat atasannya menyetujui minggu depan.

## `Final_Score` sengaja tanpa plafon

Tidak ada CHECK yang membatasinya di 100, dan itu keputusan sadar. Staff dengan bobot
pilar 80/20 sah mencapai `0,8 × 125 + 0,2 × 100 = 120`, dan pita grade sudah terbuka ke
atas sejak DML-2026-079.

Menaruh plafon di sini akan menciptakan sumber kebenaran kedua tentang skala, yang
bertengkar dengan pita grade begitu salah satunya berubah. Yang dijaga hanya
non-negatif.

## Kolom yang dirancang lalu dibatalkan

Draf sebelumnya membawa `PA_Metode_Nilai_Id` beserta masternya, dengan alasan K31. User
membatalkannya, dan benar: K31 berlaku untuk kolom yang nilainya **berbeda-beda** dari
daftar tetap. Kolom itu akan berisi `AKUMULASI_YTD` di setiap baris selamanya, karena
untuk KPI memang hanya ada satu metode. Kolom yang selamanya berisi satu nilai tidak
menyimpan informasi apa pun.

## Publikasi ada di sini, bukan di peserta

Yang diterbitkan adalah **hasilnya**, dan sebagian peserta bisa tertahan sementara yang
lain terbit. Jadi keadaan itu milik baris yang diterbitkan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result` | Primary key | |
| `FK_PA_Result_Participant` | Foreign key | → `N_PA_Participant` |
| `FK_PA_Result_Status_Master` | Foreign key | → master status, menunjuk kode supaya `DEFAULT` bekerja |
| `FK_PA_Result_Hav_Quadrant` | Foreign key | → `N_PA_Hav_Quadrant`, nullable |
| `UX_PA_Result_Participant` | Unique index tersaring | Satu hasil aktif per peserta |
| `IX_PA_Result_Status` | Index | Jalur monitoring, `INCLUDE` nilai akhir, grade, kuadran |
| `IX_PA_Result_Kuadran` | Index | Hitungan sembilan kotak |
| `CK_PA_Result_Calc_Version` | Check | Minimal 1 |
| `CK_PA_Result_Nilai_Non_Negatif` | Check | Keempat kolom nilai tidak boleh negatif |
| `CK_PA_Result_Bobot` | Check | Dua-duanya kosong, atau dua-duanya terisi dan berjumlah 100 |
| `CK_PA_Result_Periode` | Check | Akhir tidak mendahului awal |
| `CK_PA_Result_Rater` | Check | Mengisi tidak melebihi diharapkan |
| `CK_PA_Result_KPI_Menggantung` | Check | Tidak negatif |

## Yang sengaja tidak dijaga database

`Alasan_Tertahan` wajib saat status `TERTAHAN` — CHECK-nya harus mengeraskan literal
bisnis `'TERTAHAN'`, dilarang aturan proyek. Begitu pula aturan bahwa `Kode_Grade_Final`
boleh berbeda dari `Kode_Grade_Sistem` hanya saat sudah dikalibrasi, dan urutan status.

## Kedudukan

Puncak lapisan hasil. Induk dari empat tabel di DDL-2026-081.

## Pemakaian aplikasi

Belum ada per 1 September 2026.

## Keadaan data

Kosong.
