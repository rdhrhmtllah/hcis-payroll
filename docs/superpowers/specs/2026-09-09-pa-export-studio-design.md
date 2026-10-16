# Export Studio Performance Appraisal — rancangan

Tanggal: 9 September 2026
Status: menunggu review

## Ringkasan

Menambahkan Export Studio untuk modul Performance Appraisal, memakai mesin
laporan generik yang sudah ada di `app/Services/SYSTEM/Application/Reports/`.
PA menjadi penghuni **ketiga** sesudah SOP dan VISITOR; tidak ada mesin baru yang
dibangun.

Yang ditulis hanya bagian yang mustahil generik: empat kelas dataset, satu
service export beserta job-nya, satu controller tipis, satu halaman Vue
pembungkus, dan baris-baris hak akses.

## Keputusan yang sudah diambil

| Pertanyaan | Keputusan |
|---|---|
| Dataset apa saja | Empat: `hasil`, `penugasan`, `kompetensi`, `peserta` |
| Siapa yang boleh | Admin PA saja, **tanpa penyempitan** — tidak ada `Lingkup` |
| Hasil belum terbit | **Boleh** ikut, tetapi `Status_Hasil` wajib jadi kolom yang selalu ada |
| Batas siklus | Boleh lintas siklus; siklus jadi dimensi dan saringan biasa |
| Template sistem | Empat preset, tersedia sejak hari pertama |
| Urutan kerja | Spec mencakup empat dataset; implementasi tahap 1 = `hasil` + `penugasan` |

## Kenapa empat dataset, bukan satu yang lebar

Butiran baris yang berbeda adalah dataset yang berbeda, bukan tampilan lain atas
data yang sama. Satu dataset lebar yang mencampur peserta dan penugasan
menghasilkan `COUNT(*)` yang benar secara aritmetika dan menyesatkan secara
bisnis — dan kesalahannya tidak pernah memunculkan galat.

| Dataset | Satu baris = | `COUNT(*)` menghitung |
|---|---|---|
| `hasil` | satu peserta pada satu siklus | peserta |
| `penugasan` | satu penugasan penilai | penugasan |
| `kompetensi` | peserta × kompetensi | pasangan peserta-kompetensi |
| `peserta` | satu orang terdaftar di satu siklus | orang |

## Tiga temuan dari kode yang membentuk rancangan ini

### Snapshot organisasi menyelamatkan seluruh dataset

`N_PA_Participant` menyimpan identitas organisasi **saat siklus**:
`Nama_Karyawan_Saat_Cycle`, `Nama_Level_Saat_Cycle`, `Nama_Divisi_Saat_Cycle`,
`Nama_Sub_Divisi_Saat_Cycle`, `Nama_Jabatan_Saat_Cycle`, `Lokasi_Saat_Cycle`.

Keempat dataset membaca identitas dari sana, **bukan** dari master karyawan
hidup. Dua akibat yang disengaja: export siklus tahun lalu tetap memperlihatkan
departemen orang itu tahun lalu, dan tidak ada join ke `View_Divisi_Sub_Divisi` —
view yang berbaris satu per pasangan divisi × sub-divisi, sehingga orang yang
bernaung di dua divisi akan tergandakan.

### Pra-kecuali tidak butuh dataset kelima

Orang yang dikecualikan **tetap dibuatkan baris** di `N_PA_Participant` dengan
`Status_Peserta='DIKECUALIKAN'` dan `Alasan_Dikecualikan` terisi — itu disengaja,
catatannya ada di `PaCyclePreparationService`. Jadi "audit cakupan siklus" adalah
dataset peserta biasa, bukan UNION.

Ini berbeda dari jebakan di SOP, tempat dokumen yang belum masuk paket mana pun
tidak punya baris sama sekali sehingga butuh dataset tersendiri.

### `N_PA_Result_Competency` menyimpan DUA butiran dalam satu tabel

Ini yang paling berbahaya, dan wajib dikunci.

`PaResultCalculationService::nilaiKompetensi()` menulis, untuk setiap kompetensi:

- satu baris **per kategori penilai** (`PA_Rater_Category_Id` terisi), dan
- satu baris **agregat lintas kategori** dengan `PA_Rater_Category_Id IS NULL` —
  inilah yang digambar radar.

`AVG(Nilai)` polos di atas tabel itu mencampur dua butiran: nilai agregat ikut
dirata-ratakan bersama komponennya sendiri. Hasilnya angka yang tidak berarti
apa-apa, tanpa satu pun galat.

**Keputusan:** dataset `kompetensi` mengunci basisnya ke baris agregat
(`rc.PA_Rater_Category_Id IS NULL`). Rincian per kategori penilai sengaja tidak
masuk versi pertama; kalau kelak dibutuhkan, ia dataset tersendiri, bukan
saringan.

## Berkas yang dibuat

Seluruhnya di bawah kepemilikan modul `KPI` → submodul `PerformanceAppraisal` →
fitur `Export`.

| Berkas | Isinya |
|---|---|
| `app/Services/KPI/PerformanceAppraisal/Export/PaHasilDataset.php` | dataset `hasil` |
| `…/Export/PaPenugasanDataset.php` | dataset `penugasan` |
| `…/Export/PaKompetensiDataset.php` | dataset `kompetensi` |
| `…/Export/PaPesertaDataset.php` | dataset `peserta` |
| `…/Export/PaReportServiceProvider.php` | mendaftarkan keempatnya ke `DatasetRegistry` |
| `…/Export/PaReportExportService.php` | turunan `ReportExportService`; `moduleKey() = 'pa-report'` |
| `…/Export/PaSystemTemplates.php` | empat template sistem |
| `app/Jobs/KPI/PerformanceAppraisal/Export/GeneratePaReportExportJob.php` | job antrean |
| `app/Http/Controllers/KPI/PerformanceAppraisal/Export/PaExportStudioController.php` | controller tipis |
| `app/Http/Requests/KPI/PerformanceAppraisal/Export/RunPaReportRequest.php` | validasi jalankan |
| `app/Http/Requests/KPI/PerformanceAppraisal/Export/SavePaReportTemplateRequest.php` | validasi simpan template |
| `routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php` | rute |
| `resources/js/Pages/KPI/PerformanceAppraisal/Export/PaExportStudioPage.vue` | halaman pembungkus |

Yang **tidak** dibuat: mesin laporan, komponen studio di
`resources/js/components/SYSTEM/ReportStudio/`, tabel antrean async. Semuanya
sudah ada dan dipakai apa adanya.

Perubahan pada berkas bersama, masing-masing satu baris: `config/app.php`
(provider), `config/exports.php` (registri modul + plafon baris), `routes/web.php`
(require berkas rute).

## Alamat

```
Halaman : /performance-appraisal/export-studio
API     : api/v1/performance-appraisal/export-studio/{studioDataset}/...
Katalog : api/v1/performance-appraisal/export-studio/katalog/{dataset}
```

`{studioDataset}` dibatasi di route. `{dataset}` pada `/katalog/{dataset}`
**sengaja tidak dibatasi**, supaya nama yang tak dikenal tetap kembali sebagai
`REPORT_DATASET_UNKNOWN` — kode galat yang sudah menjadi kontrak dengan layar.

Halaman menempelkan datasetnya sendiri ke `baseUrl`, dan `:key="datasetAktif"`
melahirkan ulang perakit sehingga pilihan dimensi dari dataset lama tidak bocor
ke katalog dataset baru.

## Isi tiap dataset

### `hasil`

Basis `N_PA_Result` INNER ke `N_PA_Participant` (hasil selalu punya peserta),
LEFT ke `N_PA_Cycle`, `N_PA_Hav_Quadrant`, dan `N_PA_Score_Range`.

Dimensi: siklus (kode, nama, tahun, periode dinilai), identitas snapshot (NIK,
nama, level, divisi, sub-divisi, jabatan, lokasi), lalu `Status_Hasil`,
`Alasan_Tertahan`, `Kode_Grade_Sistem`, `Kode_Grade_Final`, `Flag_Dikalibrasi`,
kuadran HAV (kode, nama, nomor box), `Calculated_At`, `Published_At`.

Ukuran: `Nilai_KPI`, `Nilai_KPI_Sebelum_Cap`, `Nilai_Kompetensi`,
`Bobot_KPI_Terpakai`, `Bobot_Kompetensi_Terpakai`, `Final_Score`,
`Jumlah_Rater_Diharapkan`, `Jumlah_Rater_Mengisi`, jumlah peserta.

**Dua jebakan yang wajib ditutup:**

1. `Flag_Aktif='Y'` wajib disaring. Perhitungan ulang meninggalkan baris versi
   lama; tanpa saringan itu satu peserta muncul berkali-kali dengan angka yang
   berbeda-beda.
2. Join ke `N_PA_Score_Range` **tidak boleh** hanya lewat `Kode_Range`. Tabel itu
   berkunci `Score_Type` + `Score_Usage` juga, sehingga kode grade yang sama pada
   dua peruntukan akan menggandakan baris peserta tanpa satu pun galat.

### `penugasan`

Basis `N_PA_Assignment` → peserta → siklus, LEFT ke `N_PA_Rater_Category`.

Dimensi: seluruh identitas peserta, ditambah penilai (`Kode_Karyawan_Penilai`,
`Nama_Penilai_Saat_Cycle`), kategori penilai, `Sumber_Penugasan`, `Alasan_Manual`,
`Bobot_Kustom`, `Status_Penugasan`, `Due_At`, `Started_At`, `Finished_At`, dan
kolom turunan "terlambat".

Ukuran: `Bobot_Dalam_Kategori`, jumlah penugasan, jumlah sudah terisi, jumlah
belum, persentase kemajuan.

**Keputusan yang paling mudah salah di seluruh spec ini.** `Status_Penugasan`
punya enam nilai, dan **`TERKUMPUL` bukan selesai** — lembarnya terisi tetapi
penilainya belum menekan kirim untuk seluruh tugasnya; sedangkan
`MENUNGGU_VERIFIKASI` menunggu pengesah di HC Learn. Definisi "sudah terisi" dan
"terlambat" karena itu **tidak boleh diketik ulang di dataset ini**; keduanya
dipinjam dari konstanta yang sama yang dipakai `PaAdminDashboardService`. Kalau
tidak, Export Studio dan halaman pemantauan akan melaporkan dua angka kemajuan
yang berbeda untuk siklus yang sama, dan yang membacanya tidak punya cara
mengetahui mana yang benar.

### `kompetensi`

Basis `N_PA_Result_Competency` **dikunci ke `PA_Rater_Category_Id IS NULL`**,
join ke `N_PA_Result` → peserta → siklus, dan ke `N_PA_Competency` +
`N_PA_Competency_Type`.

Dimensi: identitas peserta, jenis kompetensi, kode & nama kompetensi.
Ukuran: `Nilai`, `Jumlah_Penilai`, rata-rata nilai, jumlah peserta dinilai.

`grain()` menuliskan terang bahwa peserta memang muncul berulang, supaya siapa
pun yang membuka berkasnya tidak menyimpulkan datanya rangkap.

### `peserta`

Basis `N_PA_Participant` saja, tanpa hasil. Inilah dataset yang menjawab "siapa
yang tidak dinilai, dan kenapa".

Dimensi: siklus, identitas snapshot, `Status_Peserta` (`DIIKUTKAN` /
`DIKECUALIKAN` / `TERTUNDA`), `Alasan_Dikecualikan`, `Snapshot_At`.
Ukuran: jumlah orang, jumlah penugasan terpasang.

## Template sistem

Empat, di `PaSystemTemplates` mengikuti pola `VisitorSystemTemplates`: muncul di
galeri tanpa pemilik, tidak bisa dihapus, bisa diduplikat menjadi milik sendiri.

| Nama | Dataset | Mode |
|---|---|---|
| Rekap Nilai Akhir Siklus | `hasil` | rinci |
| Papan Kemajuan Pengisian | `penugasan` | ringkasan per divisi × kategori penilai |
| Gap Kompetensi per Departemen | `kompetensi` | ringkasan per divisi |
| Audit Cakupan Siklus | `peserta` | rinci |

Aturan GRAND TOTAL yang sudah berlaku tetap dipatuhi: hanya `COUNT(*)` dan
`SUM()` yang boleh ditotal. Persentase kemajuan dan rata-rata nilai ditandai
`Ukuran::TOTAL_ABAIKAN` sehingga tercetak `-`, bukan angka yang menjumlahkan
rata-rata.

## Hak akses

Satu halaman, satu jenis halaman baru: **`PaExportStudioPage`**.

Sesuai keputusan "admin tanpa penyempitan", `terapkanLingkup()` mengembalikan
kueri apa adanya bila `$lingkup->adminPenuh`, dan `1 = 0` untuk selain itu. Hak
halaman karena itu menjadi satu-satunya gerbang, dan tidak ada jalan diam-diam ke
data orang lain.

Satu berkas DML memasang tiga baris yang memang dituntut setiap halaman baru:

1. `N_HRIS_Menus`
2. `N_HRIS_Grup_Fitur_Map` — **tanpa baris ini menunya tidak pernah muncul**
   walaupun hak aksesnya sudah benar
3. `HRIS_Page_Access` untuk peran yang berhak

Grup fiturnya **diverifikasi ke `N_HRIS_Master_Grup_Fitur` di basis data**, bukan
diambil dari daftar bertanggal di `CLAUDE.md`. Ditutup dengan `flushMenuCache()`
dan `php artisan route:clear`.

## Saringan dan opsinya

Semua dataset menyaring `Flag_Aktif='Y'` dan menyediakan saringan siklus, tahun,
divisi, sub-divisi, level, jabatan, dan lokasi, ditambah saringan status khas
masing-masing.

Daftar opsi isian datang dari **katalog dataset** lewat kontrak
`PenyediaOpsiSaringan`, bukan dari endpoint monitoring — itu menutup tiga hal
sekaligus: komponen SYSTEM tidak menyebut alamat modul, beda hak halaman tidak
menghasilkan kebocoran, dan saringan tidak pernah menyaring kolom ID sambil
diberi daftar nama.

**Label pada `CASE` dan daftar opsinya wajib dirender dari konstanta yang sama.**
Ditulis terpisah, memilih sebuah opsi akan membandingkan teks yang tak pernah
dihasilkan kueri, dan hasilnya nol baris **tanpa galat** — kegagalan yang paling
sulit dilacak karena tidak terlihat seperti kegagalan.

Kalau opsi saringan di-cache, kunci cache-nya dinaikkan setiap kali daftarnya
berubah bentuk.

## Plafon baris

`config/exports.php` mendapat `pa_report_max_rows` per format. Dataset
`kompetensi` berbutir peserta × kompetensi: satu siklus 900 peserta × 12
kompetensi sudah 10.800 baris sebelum saringan apa pun.

Pemotongan tidak pernah senyap — `ReportRunner` menuliskannya di `catatan`, yang
ikut masuk ke berkas maupun ke layar pratinjau.

## Rencana uji

Setiap penjaga di bawah harus **pernah terlihat merah** sebelum dihijaukan.

1. **Kontrak tiap dataset** — butiran, `Flag_Aktif`, join `Score_Range` yang tidak
   menggandakan, kunci `PA_Rater_Category_Id IS NULL`.
2. **Penjaga "TERKUMPUL bukan selesai"** — membandingkan angka kemajuan Studio
   dengan angka `PaAdminDashboardService` untuk siklus yang sama. Ini uji yang
   paling berharga di seluruh daftar: ia menangkap dua sumber kebenaran yang mulai
   berselisih.
3. **Mode ringkasan untuk tiap dataset**, bukan hanya mode rinci. SQL Server
   menolak sub-kueri di `GROUP BY`, dan kegagalan itu hanya muncul setelah
   dikelompokkan — dimensi yang jalan sempurna di mode rinci bisa gagal total di
   mode ringkasan.
4. **Hak halaman** — bukan admin mendapat nol baris, bukan galat.
5. **Pendaftaran `config/exports.php`** — lupa mendaftarkannya tidak menggagalkan
   apa pun sampai klik unduh; berkasnya tetap dibuat dan ditandai siap, lalu
   unduhannya gagal.
6. **Siklus penuh**: definisi → pratinjau → jalankan → berkas.

Fixture menyemai siklusnya **lewat jalur aplikasi**, bukan INSERT mentah, supaya
snapshot aturan penilai dan rentang skornya benar-benar terbentuk. INSERT mentah
menghasilkan nol snapshot, dan ujinya lalu menguji keadaan yang tidak pernah ada
di kenyataan.

## Urutan implementasi

- **Tahap 1** — mesin terpasang, dataset `hasil` dan `penugasan`, dua preset
  pertama, halaman, hak akses, uji. Sudah bisa dipakai.
- **Tahap 2** — dataset `kompetensi` dan `peserta` beserta dua preset sisanya.

Spec ini mencakup keduanya supaya tahap 2 tidak memaksa membongkar bentuk yang
sudah jadi.

## Di luar cakupan

- Rincian kompetensi per kategori penilai.
- Penyempitan untuk atasan (leader) — sengaja tidak ada `Lingkup`.
- Penjadwalan export otomatis.
- Ekspor lampiran atau berkas selain baris data.
