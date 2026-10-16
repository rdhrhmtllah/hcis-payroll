# PA Saya — Wizard Tugas Penilaian dan Kalibrasi Penilai

- Tanggal: 18 September 2026
- Status: RANCANGAN, menunggu tinjauan pemilik proyek
- Modul: **KPI** → submodul **Performance Appraisal** → fitur **PA Saya / Tugas Penilaian**
- Halaman: `/performance-appraisal/saya` (`PerformanceAppraisalSayaPage`)

## 1. Ringkasan

Tab "Tugas Penilaian" di halaman PA Saya diubah dari daftar berhalaman menjadi **wizard
yang berjalan per kategori penilai**, dengan urutan yang ditentukan master jenis penilai.
Di setiap transisi antar kategori, penilai melihat **pratinjau skor yang ia berikan** kepada
setiap orang yang ia nilai. Pada kategori yang diizinkan, pratinjau itu menjadi
**Kalibrasi Penilai**: penilai boleh mengubah skornya per kompetensi sebelum mengirim.
Nilai asli dari HC Learn disimpan utuh untuk audit, dan perhitungan memakai nilai terbaru.

Pengisian lembar tetap terjadi di HC Learn. Wizard di HCIS adalah pemandu: ia menunjukkan
siapa yang harus dinilai lebih dulu, membuka lembarnya, dan menampilkan hasilnya kembali.

## 2. Keputusan yang sudah diambil

Diputuskan pemilik proyek pada 18 September 2026. Jangan dibuka ulang tanpa alasan baru.

| # | Keputusan | Catatan |
|---|---|---|
| W1 | Urutan antar kategori **dikunci**: langkah berikutnya terbuka setelah semua lembar langkah sebelumnya dikerjakan. | Tanpa pengecualian khusus untuk lembar macet; jalan keluarnya tindakan admin yang sudah ada (§5.3). |
| W2 | **Kirim tetap satu kali di akhir**, lewat `kirim-semua` yang sudah ada. | `PaRaterSubmitService` tidak berubah. |
| W3 | Pratinjau transisi adalah **Kalibrasi Penilai**: penilai boleh mengubah skor per kompetensi per orang yang ia nilai. | Hanya pada kategori ber-`Flag_Boleh_Kalibrasi = 'Y'`. |
| W4 | **Nilai asli disimpan untuk audit; nilai terbaru yang dipakai** perhitungan. | `N_PA_Assignment_Score` tidak pernah ditimpa. |
| W5 | Urutan dan penanda boleh-kalibrasi adalah **kamus hidup**: perubahan master langsung berlaku, termasuk pada siklus yang sedang berjalan. | **Menyimpang dari K26** ("aturan mendapat salinan siklus"). Disengaja oleh pemilik proyek; jangan "diperbaiki" menjadi salinan siklus. |
| W6 | **Alasan wajib, satu per orang yang dinilai** setiap kali ada kompetensi yang diubah. | Bukan per kompetensi. |
| W7 | Istilahnya **Kalibrasi Penilai**. Kalibrasi grade oleh forum admin disebut **Kalibrasi Forum** di layar. | Tabel, service, dan kode galat memakai nama yang berbeda dari kalibrasi hasil. |
| W8 | Penyimpanan memakai **tabel kalibrasi terpisah + pembaca skor efektif satu pintu** (pendekatan B). | Pendekatan A (menimpa `Skor`) ditolak karena revisi hilang saat pemetaan ulang. |

## 3. Dasar registry

Diverifikasi pada 18 September 2026 terhadap `Web_HRIS` (development):

| Hal | Nilai |
|---|---|
| Grup fitur | `Id_Grup_Fitur = 2`, `Kode_Grup_Fitur = KPI` |
| Pemetaan halaman | `N_HRIS_Grup_Fitur_Map.Id_Grup_Map = 245`, `Jenis_page = PerformanceAppraisalSayaPage`, `Flag_Aktif = Y` |
| Middleware halaman | `auth` + `check.jabatan:PerformanceAppraisalSayaPage` |

Tidak ada halaman baru. Seluruh endpoint baru masuk grup rute yang sama, sehingga tidak
perlu baris baru di `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`, maupun `HRIS_Page_Access`.

## 4. Data

### 4.1 `N_PA_Rater_Category` — dua kolom baru (DDL-2026-119)

| Kolom | Tipe | Null | Bawaan | Arti |
|---|---|---|---|---|
| `Urutan_Pengisian` | `smallint` | NULL | — | Posisi kategori di wizard. Kecil lebih dulu. NULL = belum diurutkan, ditaruh paling akhir (lalu menurut `Id_PA_Rater_Category`). |
| `Flag_Boleh_Kalibrasi` | `char(1)` | NOT NULL | `'T'` (`DF_PA_Rater_Category_Boleh_Kalibrasi`) | `Y` = penilai kategori ini boleh mengubah skornya di pratinjau. `T` = pratinjau hanya-baca. |

Indeks unik tersaring `UX_PA_Rater_Category_Urutan` pada `Urutan_Pengisian`
`WHERE Flag_Aktif = 'Y' AND Urutan_Pengisian IS NOT NULL`. Saringan `IS NOT NULL` wajib:
SQL Server memperlakukan NULL sebagai sama di indeks unik, dan tanpa saringan hanya satu
kategori yang boleh belum berurutan.

Kolomnya boleh NULL karena nilai urutan adalah data bisnis yang masuk lewat DML. Kolom
NOT NULL akan menuntut rantai DDL → DML → DDL untuk satu keputusan.

Tidak ada `CHECK` untuk `Flag_Boleh_Kalibrasi`; nilai `Y`/`T` divalidasi backend, sesuai
konvensi proyek.

### 4.2 Nilai awal urutan (DML-2026-124)

Diusulkan: `SELF = 1`, `ATASAN = 2`, `PEER = 3`, `BAWAHAN = 4`. Menilai diri sendiri lebih
dulu memberi penilai titik acuan sebelum menilai orang lain.

**Nilai ini dikonfirmasi pemilik proyek sebelum DML dijalankan.** `Flag_Boleh_Kalibrasi`
tidak diubah oleh DML ini: seluruh kategori tetap `T` sampai admin menyalakannya lewat
layar master.

### 4.3 Tabel kalibrasi (DDL-2026-119)

**`N_PA_Kalibrasi_Penilai`** — satu baris per tindakan simpan penilai atas satu orang.

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_PA_Kalibrasi_Penilai` | `int IDENTITY` | NOT NULL | PK `PK_PA_Kalibrasi_Penilai` |
| `PA_Assignment_Id` | `int` | NOT NULL | FK `FK_PA_Kalibrasi_Penilai_Assignment` → `N_PA_Assignment` |
| `Alasan` | `nvarchar(500)` | NOT NULL | Alasan penilai (W6) |
| `Created_At` | `datetime2` | NOT NULL | Bawaan `sysdatetime()` |
| `Created_By` | `int` | NULL | `KPI_Users.Id_Users` dari **sesi login**, bukan dari parameter |

Indeks `IX_PA_Kalibrasi_Penilai_Assignment (PA_Assignment_Id, Id_PA_Kalibrasi_Penilai DESC)`.

**`N_PA_Kalibrasi_Penilai_Detail`** — satu baris per kompetensi yang diubah pada tindakan itu.

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_PA_Kalibrasi_Penilai_Detail` | `int IDENTITY` | NOT NULL | PK `PK_PA_Kalibrasi_Penilai_Detail` |
| `PA_Kalibrasi_Penilai_Id` | `int` | NOT NULL | FK `FK_PA_Kalibrasi_Penilai_Detail_Kepala` |
| `PA_Competency_Id` | `int` | NOT NULL | FK `FK_PA_Kalibrasi_Penilai_Detail_Competency` → `N_PA_Competency` |
| `Skor_Sebelum` | `decimal(6,2)` | NOT NULL | Skor efektif saat tindakan dibuat |
| `Skor_Sesudah` | `decimal(6,2)` | NOT NULL | Skor baru |
| `Skor_Maksimal` | `decimal(6,2)` | NOT NULL | Skala butir saat itu, disimpan supaya riwayat terbaca tanpa bergantung pada payload |

Unik `UX_PA_Kalibrasi_Penilai_Detail_Kompetensi (PA_Kalibrasi_Penilai_Id, PA_Competency_Id)`:
satu tindakan tidak boleh mengubah kompetensi yang sama dua kali.

Kedua tabel **hanya ditambah, tidak pernah diubah atau dihapus**. Tidak ada `Flag_Aktif`,
`Updated_*`, maupun `Cancelled_*`: revisi yang salah diperbaiki dengan revisi berikutnya,
dan rantainya tetap terbaca utuh.

### 4.4 Kunci dan skor efektif

Kuncinya **(penugasan, kompetensi)**, bukan id baris skor. Alasannya terukur:

- `PaKirimanTinjauService::petakanUlang()` menonaktifkan baris skor lama dan menyisipkan
  baris baru ber-`Mapper_Version` lebih tinggi. Revisi yang menempel ke id baris skor akan
  hilang tanpa jejak.
- `Kode_Soal_Sumber` tidak bisa jadi kunci: sejak DDL-2026-090 ia boleh kosong dan berulang
  lintas alat tes.
- `PaBijeksiLabel` menjamin satu bagian ↔ satu kompetensi dalam satu paket, dan perhitungan
  memang mengelompokkan skor per kompetensi.

**Skor efektif** (penugasan P, kompetensi K) = `Skor_Sesudah` dari rincian terbaru untuk
(P, K) bila ada; bila tidak, `Skor` dari `N_PA_Assignment_Score` payload terakhir P yang
aktif. Revisi berulang membentuk rantai asli → revisi 1 → revisi 2, karena `Skor_Sebelum`
setiap tindakan adalah skor efektif saat itu.

Skor ber-`PA_Competency_Id` NULL (tidak terpetakan) dan jawaban naratif **tidak bisa
dikalibrasi**.

## 5. Aturan dan gerbang

### 5.1 Siapa

Hanya penilai pemilik penugasan. `assignment_id` dari layar diperiksa kepemilikannya
terhadap `Kode_Perusahaan_Penilai` + `Kode_Karyawan_Penilai` milik user yang login. Tidak
ada id peserta yang diterima dari pemanggil (aturan kelas `PaPesertaService`). Penugasan
milik orang lain dijawab **404**, bukan 403, supaya keberadaannya tidak bocor.

### 5.2 Kapan

Semua syarat diperiksa **di dalam satu transaksi dengan `lockForUpdate` pada baris
`N_PA_Assignment`**, karena penilai bisa menekan kirim atau admin melepas paksa tepat saat
kalibrasi disimpan:

1. Operasi gerbang baru `PaCycleGate::KALIBRASI_PENILAI => ['OPEN']`.
2. `Status_Penugasan = TERKUMPUL`. `SELESAI` sudah beku (sesudah kirim, lepas paksa, atau
   penutupan siklus); `MENUNGGU_VERIFIKASI` belum lengkap petanya.
3. `Flag_Boleh_Kalibrasi = 'Y'` pada kategori penugasan, dibaca saat menyimpan (W5).
4. Setiap skor baru berada di `0 … Skor_Maksimal` butirnya dan **berbeda** dari skor
   efektifnya sekarang.
5. `Alasan` terisi, paling panjang 500 karakter.

### 5.3 Kunci urutan wizard

- Kategori tanpa penugasan aktif untuk penilai itu **dilompati**.
- Langkah ke-k **terbuka** bila seluruh penugasan aktif pada langkah sebelumnya berstatus
  `TERKUMPUL`, `SELESAI`, atau `MENUNGGU_VERIFIKASI`. `DIBATALKAN` diabaikan.
- Untuk langkah yang terkunci, API **tidak mengirim** `url_ujian`, `short_token`, maupun
  `akses_otp`.

**Batas yang diketahui:** penguncian berlaku di HCIS. Orang yang sudah memegang tautan
HC Learn tetap bisa mengisi lebih dulu. Kiriman di luar urutan **tetap diterima**, karena
menolaknya berarti membuang pekerjaan sungguhan.

Lembar yang macet (pintu gagal atau antre) ikut mengunci langkah berikutnya (W1). Jalan
keluarnya tindakan admin yang sudah ada: cetak ulang pintu, atau batalkan penugasan.

### 5.4 Satu pintu skor efektif

Service baru **`PaSkorEfektif`**, satu method publik yang menerima daftar penugasan dan
mengembalikan, per (penugasan, kompetensi): `skor_asli`, `skor_efektif`, `skor_maksimal`,
`terkalibrasi`. Pembacaannya dipotong per 500 penugasan (batas 2.100 parameter SQL Server).

Pemakai:

| Berkas | Perlakuan |
|---|---|
| `PaResultCalculationService` (pemuat skor berskor, sekitar baris 1460) | **Beralih** ke `PaSkorEfektif`. Satu-satunya titik baca `Skor` di perhitungan; `simpanCatatanDariPenilai` hanya membaca jawaban naratif. |
| `PaParticipantReadService` | Menampilkan asli **dan** efektif di detail admin. |
| `PaAdminDashboardService`, `PaVerifyCommand` | Diaudit saat implementasi: yang menghitung atau menampilkan **nilai** beralih; yang hanya mencacah baris masuk daftar izin dengan komentar alasan. |
| `PaPayloadMapperService` | Daftar izin: ia penulisnya. |
| `PaKirimanTinjauService` | Daftar izin: diagnosis pemetaan memang membaca apa yang dikirim HC Learn. |

Uji pemindai **`SkorEfektifSatuPintuTest`** menolak berkas di `app/` yang membaca kolom
`Skor` dari `N_PA_Assignment_Score` di luar daftar izin. Pola dan tempatnya mengikuti
`ResolverSatuPintuTest`. Uji ini wajib terlihat **merah** pada pelanggaran buatan sebelum
dianggap menjaga.

**Tidak ada hasil yang bisa basi:** kalibrasi hanya boleh sebelum lembar `SELESAI`,
sedangkan hasil hanya dihitung setelah seluruh lembar `SELESAI`. Hitung ulang di kemudian
hari otomatis memakai skor efektif.

## 6. API

Seluruh rute baru masuk grup yang sudah ada di
`routes/KPI/PerformanceAppraisal/Peserta/PerformanceAppraisalPeserta.php`. Amplop respons
memakai `result`. Kontraknya ditulis lebih dulu di
`docs/superpowers/specs/2026-09-01-kontrak-api-performance-appraisal.md`, bersama fixture
di `resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures/` dan `KontrakResponsTest`.

| Metode | Alamat | Isi |
|---|---|---|
| GET | `/api/v1/performance-appraisal/saya/wizard?cycle_id=` | `langkah[]` berurutan: `urutan`, `kode_kategori`, `nama_kategori`, `boleh_kalibrasi`, `status` (`TERKUNCI`/`AKTIF`/`TUNTAS`), `jumlah_lembar`, `jumlah_terisi`, `jumlah_terkalibrasi`, `sebab_terkunci`. Ditambah **`langkah_aktif` yang ditentukan server** dan `boleh_kirim`. |
| GET | `.../saya/wizard/langkah/{kode_kategori}/lembar` | Orang yang dinilai pada kategori itu: identitas saat siklus, status, tenggat/terlambat, `url_ujian`, `short_token`, `akses_otp` (ketiganya `null` bila langkah terkunci), `diri_sendiri`. Tanpa halaman, berplafon, dengan penanda `dipangkas`. |
| GET | `.../saya/wizard/langkah/{kode_kategori}/pratinjau` | Matriks orang × kompetensi. Per orang: `assignment_id`, identitas, status, `boleh_diubah`, `kalibrasi_terakhir {alasan, pada}`. Per sel: `competency_id`, kode, nama, jenis, `skor_asli`, `skor_efektif`, `skor_maksimal`, `terkalibrasi`. Per kategori: rata-rata skor efektif yang diberikan penilai per kompetensi. Jawaban naratif ikut, hanya-baca. |
| POST | `.../saya/kalibrasi` | Badan: `{assignment_id, perubahan: [{competency_id, skor}], alasan}`. Mengembalikan baris pratinjau orang itu yang sudah diperbarui. |

Kode galat `POST /kalibrasi`, selalu bersama `request_id`:

| HTTP | `error_code` | Sebab |
|---|---|---|
| 404 | `PA_PENUGASAN_TIDAK_DITEMUKAN` | Tidak ada, atau bukan milik penilai yang login |
| 409 | `PA_SIKLUS_TIDAK_TERBUKA` | Gerbang `KALIBRASI_PENILAI` menolak |
| 409 | `PA_LEMBAR_BEKU` | Status bukan `TERKUMPUL` |
| 403 | `PA_KALIBRASI_DITUTUP` | Kategori ber-`Flag_Boleh_Kalibrasi = 'T'` |
| 422 | `PA_SKOR_DI_LUAR_RENTANG` | Di luar `0 … Skor_Maksimal` |
| 422 | `PA_ALASAN_WAJIB` | Alasan kosong atau lebih dari 500 karakter |
| 422 | `PA_TIDAK_ADA_PERUBAHAN` | Semua nilai sama dengan skor efektif. Ini juga yang membuat tekan-ganda aman. |

**Orang dengan level berbeda dalam satu kategori.** Seorang atasan bisa menilai bawahan
Staff (17 kompetensi) sekaligus Assistant Manager (21 kompetensi), dan skala butirnya bisa
berbeda. Karena itu:

- Kolom matriks adalah **gabungan** kompetensi seluruh orang pada kategori itu. Sel untuk
  kompetensi yang tidak dimiliki seseorang dikirim `null` dan tampil "—", bukan nol.
- Rata-rata per kompetensi dihitung pada **skala ternormalisasi 0-100**
  (`skor_efektif / skor_maksimal × 100`), dan hanya atas orang yang memiliki kompetensi
  itu. Rata-rata skor mentah dari skala berbeda tidak bermakna.
- Masukan skor di layar tetap pada **skala asli butirnya**; normalisasi hanya untuk
  pembanding.

**Tidak berubah:** `/kemajuan-pengiriman` dan `/kirim-semua`. Endpoint lama `/assignment`
tetap hidup; pencabutannya tugas terpisah setelah dipastikan tak ada pemanggil.

### 6.1 Sisi admin

- **Master jenis penilai.** `PaMasterWriteService` (jalur `kategori-penilai`) kini juga
  menulis `Flag_Boleh_Kalibrasi`. Urutan ditata lewat endpoint `PUT` yang menerima
  **daftar urutan lengkap** dan menuliskannya dalam **satu pernyataan `UPDATE ... CASE`**.
  Menukar dua posisi satu per satu akan menabrak `UX_PA_Rater_Category_Urutan` di tengah
  jalan; SQL Server memeriksa indeks unik per pernyataan.
- **Detail hasil admin** (`PaResultDialog`). Skor asli ditampilkan di samping skor efektif,
  plus riwayat Kalibrasi Penilai (siapa, kapan, dari berapa ke berapa, alasan).
- **Peserta tidak pernah melihat satu pun dari ini.** Aturan kelas `PaPesertaService` tetap
  berlaku.

## 7. Halaman

Tab "Tugas Penilaian" di `saya.vue` berhenti memakai `PaSayaAssignmentPanel.vue`
(2.348 baris). Berkas baru di `resources/js/Pages/KPI/PerformanceAppraisal/Peserta/`:

| Berkas | Tugas |
|---|---|
| `composables/usePaWizardPenilaian.js` | Seluruh keadaan: memuat `/wizard`, lembar dan pratinjau per langkah, menyimpan kalibrasi, memuat ulang. **Memuat ulang saat tab browser aktif kembali**, karena penilai mengisi di tab HC Learn dan lembarnya kembali lewat callback. |
| `components/PaWizardStepper.vue` | Langkah: terkunci/aktif/tuntas. Langkah terkunci menyebut sebabnya. Ringkas di ponsel. |
| `components/PaWizardLembarList.vue` | Kartu per orang: status, tenggat, **Mulai Menilai** (tab baru lewat `urlAman`), kode akses dan OTP. Setelah semua terisi: **Lanjut ke Kalibrasi Penilai**. |
| `components/PaWizardPratinjauKalibrasi.vue` | Matriks orang × kompetensi dengan kolom nama menempel dan gulir di dalam wadahnya sendiri, plus baris rata-rata. Di ponsel berubah menjadi kartu per orang, sehingga halamannya tidak pernah bergulir menyamping. Sel terkalibrasi menampilkan nilai asli dicoret di samping nilai baru. Kategori ber-`T` tampil hanya-baca. Simpan membuka isian alasan wajib. |
| `components/PaWizardRingkasanKirim.vue` | Ringkasan seluruh kategori dan jumlah yang dikalibrasi, lalu **Kirim** lewat `kirim-semua` dengan konfirmasi. |

Aturan tampilan:

- Keadaan wajib: skeleton saat memuat, kosong (tidak ada tugas di siklus ini), galat
  dengan `error_code` + `request_id`, terkunci, basi/coba lagi.
- Langkah yang sudah tuntas bisa dibuka lagi dan dikalibrasi ulang **sampai kirim**.
- Meninggalkan langkah dengan perubahan yang belum disimpan meminta konfirmasi. Form
  ditandai bersih **sebelum** pesan sukses tampil.
- Dialog memakai `useModalFocus` dan `useScrollLock`; gerak mengikuti `[data-hcis-motion]`;
  warna dari token lapis peran.
- Sel masukan berlabel aksesibel "Skor {kompetensi} untuk {nama}".

## 8. Pengujian

Ditulis lengkap, **dijalankan setelah pemilik proyek ACC**.

**Backend** (`tests/Feature/KPI/PerformanceAppraisal/`):

- Kepemilikan: penugasan orang lain → 404.
- Hanya `TERKUMPUL` yang bisa dikalibrasi; `SELESAI` dan `MENUNGGU_VERIFIKASI` ditolak.
- Penanda kategori, rentang skor, alasan, dan "tidak ada perubahan" ditegakkan.
- Balapan: kirim-semua dan kalibrasi berebut baris yang sama; revisi tidak mendarat pada
  lembar `SELESAI`.
- **Revisi selamat melewati `petakanUlang()`**: kalibrasi, petakan ulang, skor efektif
  tetap nilai revisi. Ini uji terpenting.
- Perhitungan memakai skor efektif, dan nilai asli di `N_PA_Assignment_Score` tidak berubah.
- Rantai revisi: dua tindakan berturut-turut, `Skor_Sebelum` tindakan kedua = `Skor_Sesudah`
  tindakan pertama.
- Kunci urutan: langkah terkunci tidak membawa akses HC Learn; kategori kosong dilompati.
- `SkorEfektifSatuPintuTest` terlihat merah pada pelanggaran buatan.
- Penataan urutan atomik tidak menabrak indeks unik.

**Kontrak:** fixture baru dan `KontrakResponsTest`.

**Frontend** (Vitest): composable (langkah aktif dari server, muat ulang saat tab aktif,
perubahan belum tersimpan) dan tiap komponen. Selektor uji menunjuk markup stub, mengikuti
pola uji halaman PA yang ada.

**Browser:** verifikasi lewat vhost Laragon, bukan `artisan serve`: desktop dan ponsel,
matriks tanpa gulir menyamping di level halaman.

## 9. Artefak database

| Change | Isi | Catatan |
|---|---|---|
| DDL-2026-119 | Dua kolom `N_PA_Rater_Category`, indeks unik tersaring, dua tabel kalibrasi | `forward.sql` + `drop.sql`, dokumen tabel di `docs/database/tables/kpi/` |
| DML-2026-124 | Nilai awal `Urutan_Pengisian` | Menunggu konfirmasi §4.2 |

Mengikuti alur status di `AGENTS.md`: DRAFT → REVIEW_REQUIRED → APPROVED → dijalankan di
development → TRACKER_PENDING. Agent tidak menjalankan apa pun di production.

Nomor diambil pada 18 September 2026 dari nomor terbesar yang sudah ada di
`database/ddl/status.json` (DDL 118, DML 123). Periksa ulang saat implementasi dimulai:
branch lain bisa lebih dulu memakainya.

## 10. Di luar cakupan

- Mengubah jawaban naratif lewat kalibrasi.
- Kirim per kategori.
- Salinan siklus untuk urutan dan penanda (ditolak oleh W5).
- Mencabut endpoint `/assignment` dan `PaSayaAssignmentPanel.vue`.
- Menyemai data contoh kalibrasi di `pa:seed-presentasi`.
- Menolak kiriman HC Learn yang datang di luar urutan.

## 11. Risiko yang diketahui

- **Kamus hidup (W5).** Mematikan penanda di tengah siklus membuat sebagian penilai sudah
  mengkalibrasi dan sisanya tidak bisa. Jejaknya tetap utuh: keberadaan baris kalibrasi
  membuktikan penandanya menyala saat itu.
- **Penguncian urutan tidak mengikat HC Learn** (§5.3).
- **Sebaran beban dari data dev tidak mewakili production.** Terukur di siklus berjalan
  dev: 133 dari 206 penilai hanya punya satu kategori. Relasi organisasi dev mayoritas
  dummy, jadi bentuk wizard harus diuji juga untuk penilai empat kategori.

## 12. Revisi r2 — 18 September 2026

Koreksi yang ditemukan saat implementasi. Bagian di atas tetap sebagai catatan rancangan;
yang berlaku adalah bagian ini bila keduanya berbeda.

- **(a) Penulis penanda dan urutan hanya `PaJenisPenilaiMatriksService`** (layar
  `jenis-penilai.vue`, alamat `PUT /master/jenis-penilai/urutan-kategori` dan
  `PUT /master/jenis-penilai/kategori/{id}/kalibrasi`), bukan `PaMasterWriteService`.
  Form kategori di `master.vue` tidak pernah tampil lewat alamat `kategori-penilai` —
  alamat itu merender matriks jenis penilai. Keduanya tetap diperiksa dengan hak master
  `kategori-penilai`.
- **(b) Dua kode galat tambahan:** `PA_KOMPETENSI_TIDAK_DI_LEMBAR` (422, `competency_id`
  tidak berskor di lembar itu) dan `PA_LANGKAH_TIDAK_DIKENAL` (404, `{kode}` bukan kategori
  tugas milik yang login, atau tidak ada siklus terbuka).
- **(c) `POST /saya/kalibrasi` mengembalikan pratinjau kategori UTUH** di samping hasil
  simpannya, karena rata-rata per kompetensi ikut berubah. Layar tidak menambal barisnya
  sendiri.
- **(d) Perhitungan hanya punya SATU titik baca skor**, bukan dua:
  `PaResultCalculationService::penugasanDenganNilai()` membaca lewat
  `PaSkorEfektif::butirBerskor()`. Satu-satunya bacaan `Skor` mentah yang tersisa di sana
  (`jenisTerjawabTanpaAngka`) hanya memeriksa ada-tidaknya angka dan bertanda
  `// skor-mentah:`.
- **(e) `PaVerifyCommand` menerapkan kalibrasi secara independen** — kueri sendiri, bukan
  memanggil `PaSkorEfektif`. Pemverifikasi yang memakai pintu yang sama dengan yang
  diverifikasinya tidak akan pernah menemukan selisih.
- **(f) `PaPesertaService::urutanKategori()` membaca `Urutan_Pengisian`** lebih dulu, jadi
  panel progres dan wizard memakai urutan yang sama.
- **(g) `PaApiError` kini membawa `error_code` dan `request_id`.** Sebelumnya keduanya tidak
  pernah diisi, sehingga kode galat PA tidak pernah sampai ke layar mana pun walau komposabel
  sudah membacanya.
- **(h) Pratinjau tidak dimuat ulang saat tab kembali aktif selagi ada perubahan belum
  tersimpan**, dan jawaban isi langkah yang terlambat tiba tidak menimpa langkah yang sedang
  dilihat.
- **(i) Esai ikut bisa dikalibrasi** (keputusan pemilik proyek, 18 Sep 2026). Tabel
  `N_PA_Kalibrasi_Penilai_Jawaban` ditambahkan ke DDL-2026-119 sebagai revisi **r2**
  (diamandemen di tempat karena belum `TRACKER_RECORDED`). Aturannya: esai tidak boleh
  dikosongkan (butir kosong terbaca "tidak dijawab"), ikut `Flag_Boleh_Kalibrasi` dan satu
  alasan per orang yang sama dengan skor, berkunci (penugasan, `Kode_Soal_Sumber`), dan versi
  terbaru dipakai di catatan hasil, detail admin, dan pratinjau — teks asli tetap di
  `N_PA_Assignment_Score` dan terbaca di riwayat. Pembacanya satu pintu:
  `PaSkorEfektif::jawabanNaratif()` / `timpaJawaban()`, dijaga pemindai `jawaban-mentah:`.
- **(j) Esai revisi berupa teks kaya** (keputusan pemilik proyek, 18 Sep 2026). Editor
  `HcisRichText` dengan toolbar terbatas; server menyaring lewat `PaTeksKaya` (daftar izin tag
  tanpa atribut, `DOMDocument`, tanpa paket baru) dan menyimpan HTML. Format DIKETAHUI DARI
  SUMBER, tidak ditebak: asli HC Learn selalu teks polos (di-escape per baris saat dijadikan
  HTML), revisi selalu HTML. Catatan hasil kini disimpan sebagai HTML aman, seragam dengan
  catatan admin. Temuan: layar hasil karyawan dan leader menggambar catatan dengan `v-html`
  tanpa penyaring — kini lewat `htmlAman()` (DOMPurify).
