# Lapisan hasil Performance Appraisal — rancangan untuk ditinjau

Menggantikan draf 30 Agustus, disesuaikan dengan temuan sumber nilai KPI (1 September)
dan keputusan user hari yang sama. Belum dieksekusi.

## Keputusan yang sudah mengikat rancangan ini

| # | Keputusan | Sumber |
|---|---|---|
| 1 | Nilai KPI diambil **YTD**, mengikuti rentang periode siklus, lewat `KpiScoringService::accumulatedScore()` | user, 1 Sep |
| 2 | Hanya KPI berstatus **`disetujui`** yang dihitung | user, 1 Sep |
| 3 | Orang tanpa KPI **tidak** otomatis diberi bobot 0/100 — sistem menandai, admin memutuskan | user, 1 Sep |
| 4 | Nilai di atas 100 **tidak dipotong**; pita teratas terbuka ke atas | user, 1 Sep (DML-079) |
| 5 | `Total_Bobot` KPI selalu 100 | user, 1 Sep |

## Kenapa tujuh tabel

Registry merencanakan enam; K31 menambah satu master (`N_PA_Result_Note_Type`).

**`N_PA_Metode_Nilai` dibatalkan.** Sempat diusulkan untuk merekam bahwa nilai KPI
dihitung dengan akumulasi YTD, lalu dijatuhkan oleh keberatan user: kolom yang isinya
selalu sama tidak menyimpan informasi apa pun. K31 berlaku untuk kolom yang nilainya
**berbeda-beda** dari daftar tetap; kalau tidak pernah berbeda, tidak ada daftarnya.

Alasan cadangan saya juga tidak tahan diperiksa. Seandainya metodenya berubah kelak,
seluruh hasil sebelum tanggal perubahan memang memakai metode lama — dan itu sudah
terjawab `Calculated_At` plus catatan change-nya. Kolom metode baru berguna kalau dua
metode hidup berdampingan, misalnya berbeda per level atau ada masa transisi. Untuk KPI
itu tidak terjadi: **YTD satu-satunya**, ditegaskan user 1 September 2026.

---

## 1. `N_PA_Result_Status` — master

| Kolom | Tipe | Catatan |
|---|---|---|
| `Id_PA_Result_Status` | `int IDENTITY` | PK |
| `Kode_Status` | `varchar(20)` | `UNIQUE`, sasaran FK |
| `Nama_Status` | `nvarchar(100)` | |
| `Deskripsi` | `nvarchar(500)` | |
| `Urutan_Alur` | `smallint` | |
| `Flag_Aktif` + audit | | |

Isi: `BELUM_DIHITUNG` (1), `DIHITUNG` (2), `TERBIT` (3), `TERTAHAN` (4).

`TERTAHAN` adalah cabang, bukan tahap — orang yang penilainya kurang dari ambang
`Min_Rater` berhenti di sana alih-alih diberi angka yang tampak wajar.

## 2. `N_PA_Result` — satu baris per peserta

| Kolom | Tipe | Null | Arti |
|---|---|:---:|---|
| `Id_PA_Result` | `int IDENTITY` | T | PK |
| `PA_Participant_Id` | `int` | T | Unik — satu peserta satu hasil |
| `Status_Hasil` | `varchar(20)` | T | Baku `BELUM_DIHITUNG` |
| **Sisi KPI** | | | |
| `Nilai_KPI` | `decimal(6,2)` | Y | `final_pct` dari resolver KPI, sudah di-cap |
| `Nilai_KPI_Sebelum_Cap` | `decimal(6,2)` | Y | `raw_pct`, sebelum cap |
| `KPI_Periode_Dari` / `_Sampai` | `date` | Y | Rentang yang diakumulasi |
| `Jumlah_KPI_Belum_Disetujui` | `int` | Y | Jejak gerbang; > 0 berarti hasil dihitung saat masih ada pengajuan menggantung |
| **Sisi kompetensi** | | | |
| `Nilai_Kompetensi` | `decimal(6,2)` | Y | Hasil agregasi 360° |
| `Jumlah_Rater_Diharapkan` / `_Mengisi` | `smallint` | Y | Ringkasan kelengkapan |
| **Gabungan** | | | |
| `Bobot_KPI_Terpakai` / `Bobot_Kompetensi_Terpakai` | `decimal(5,2)` | Y | Bukti bobot saat dihitung |
| `Final_Score` | `decimal(6,2)` | Y | **Tidak dibatasi 100** |
| `Kode_Grade_Sistem` | `varchar(40)` | Y | Hasil pencarian pita |
| `Kode_Grade_Final` | `varchar(40)` | Y | Setelah kalibrasi |
| `Flag_Dikalibrasi` | `char(1)` | T | Baku `T` |
| `PA_Hav_Quadrant_Id` | `int` | Y | FK kamus kuadran |
| **Keadaan** | | | |
| `Alasan_Tertahan` | `nvarchar(500)` | Y | Wajib lewat aplikasi saat `TERTAHAN` |
| `Calculation_Version` | `int` | T | Baku 1, naik tiap hitung ulang |
| `Calculated_At` / `_By` | | Y | |
| `Published_At` / `_By` | | Y | Publikasi ada di sini, bukan di peserta |
| `Flag_Aktif` + audit | | | |

**Dua kolom nilai KPI, bukan satu.** `accumulatedScore()` mengembalikan `raw_pct` dan
`final_pct`. Menyimpan keduanya menjawab "kenapa dia 125 padahal capaiannya 177" tanpa
menghitung ulang — dan tanpa itu, pertanyaan seperti ini hanya bisa dijawab dengan
menebak.

**`Final_Score` sengaja tidak dibatasi.** Nilai 120 adalah hasil sah dari pencapaian di
atas target, dan pita grade sudah terbuka ke atas sejak DML-079.

CHECK yang bisa ditegakkan baris: `Calculation_Version >= 1`; kedua bobot berjumlah 100
bila keduanya terisi; `Final_Score >= 0`.

## 3. `N_PA_Result_Rater_Category` — jejak redistribusi bobot

Satu baris per (hasil, kategori penilai): `Nilai`, `Jumlah_Rater_Diharapkan`,
`Jumlah_Rater_Mengisi`, `Bobot_Terpakai`, `Calculation_Version`, `Flag_Aktif`.

Inilah yang menjawab "kenapa nilai kompetensinya segitu". Bobot antar kategori
dinormalkan ulang bila satu kategori kosong — tanpa jejak ini, redistribusinya tidak
bisa diaudit.

Bobot **di dalam** kategori tidak diringkas di sini; jejaknya tetap di
`N_PA_Assignment.Bobot_Dalam_Kategori` masing-masing.

## 4. `N_PA_Result_Competency` — nilai per kompetensi

Satu baris per (hasil, kompetensi, kategori penilai). `PA_Rater_Category_Id` **boleh
kosong**, dan kosong berarti **agregat lintas kategori** — itu yang dipakai radar chart.

Kolom: `Nilai`, `Jumlah_Penilai`, `Calculation_Version`, `Flag_Aktif`.

## 5. `N_PA_Calibration_History` — hanya sisip

`PA_Result_Id`, `Kode_Grade_Sebelum`, `Kode_Grade_Sesudah`,
`PA_Hav_Quadrant_Sebelum_Id`, `PA_Hav_Quadrant_Sesudah_Id`, `Alasan` **NOT NULL**,
`Performed_By`, `Performed_At`.

Tanpa `Flag_Aktif` dan tanpa `Updated_At`: riwayat yang bisa dinonaktifkan bukan
riwayat. `Alasan` wajib karena baris ini hanya lahir ketika seseorang menimpa hasil
mesin.

## 6. `N_PA_Result_Note_Type` — master

Isi: `STRENGTH`, `DEVELOPMENT_AREA`.

## 7. `N_PA_Result_Note`

`PA_Result_Id`, `PA_Result_Note_Type_Id`, `Isi` `nvarchar(max)`,
`PA_Assignment_Id` (kosong bila ditulis admin, terisi bila berasal dari jawaban
kualitatif seorang penilai), `Urutan_Tampil`, `Flag_Aktif`, audit.

Menyimpan asal-usulnya penting: catatan yang datang dari jawaban penilai dan catatan
yang diketik admin punya bobot yang berbeda saat dibaca karyawan.

---

## Menghitung ulang: pola yang sama dengan pemeta skor

`Calculation_Version` ada di induk **dan** ketiga anaknya. Menghitung ulang tidak
menghapus apa pun: baris anak yang lama diberi `Flag_Aktif = 'T'`, baris baru
disisipkan dengan versi berikutnya, dan induknya diperbarui.

Ini pola yang sama persis dengan pemetaan ulang di `N_PA_Assignment_Score`, dan alasannya
sama: hasil lama harus tetap bisa dijelaskan setelah metodenya berubah.

## Yang tidak dijaga database

- Bahwa `Alasan_Tertahan` terisi saat status `TERTAHAN` — CHECK-nya akan mengeraskan
  literal bisnis, dilarang aturan proyek.
- Bahwa `Kode_Grade_Final` berbeda dari `Kode_Grade_Sistem` hanya bila
  `Flag_Dikalibrasi = 'Y'` — lintas kolom, tetapi bergantung pada riwayat kalibrasi.
- Urutan status. `TERBIT` sebelum `DIHITUNG` tidak masuk akal, tetapi menegakkannya di
  baris berarti mengeraskan alur ke skema.

## Urutan eksekusi bila disetujui

1. DDL: `N_PA_Result_Status`, `N_PA_Result_Note_Type` + seed, lalu `N_PA_Result`.
2. DDL: `N_PA_Result`.
3. DDL: `N_PA_Result_Rater_Category`, `N_PA_Result_Competency`,
   `N_PA_Calibration_History`, `N_PA_Result_Note`.

Bisa digabung jadi satu change karena saling bergantung dan tidak ada yang berdiri
sendiri, tetapi dipisah dua akan membuat kegagalan lebih mudah dilokalisasi.
