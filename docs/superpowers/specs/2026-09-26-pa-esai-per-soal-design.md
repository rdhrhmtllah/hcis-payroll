# PA — Esai per soal dari callback HC Learn

- Tanggal: 26 September 2026
- Status: RANCANGAN, menunggu tinjauan pemilik proyek
- Modul: **KPI** → submodul **Performance Appraisal** → fitur **Integrasi HC Learn** dan
  **Kalibrasi Penilai** (halaman `PerformanceAppraisalSayaPage`)
- Melanjutkan: `2026-09-18-pa-wizard-kalibrasi-penilai-design.md` §12 (i)–(j) dan
  `docs/KPI/performance-appraisal/permintaan-kontrak-hclearn-butir-soal.md`

## 1. Ringkasan

HC Learn memperbarui callback hasil (`POST /api/v1/pa/third-party/penilaian/complete`):
setiap bagian esai kini membawa `Esai[]`, satu entri per soal, lengkap dengan teks
pertanyaan dan jawaban penilai. Layar Pratinjau Kalibrasi akan menampilkan **teks
pertanyaan di atas setiap jawaban**, dan kalibrasi esai berpindah dari per bagian ke per
soal.

## 2. Keputusan

| # | Keputusan | Catatan |
|---|---|---|
| E1 | **Skor bagian esai diabaikan.** Esai tetap kualitatif dan tidak memengaruhi Nilai Kompetensi. | Diputuskan pemilik proyek 26 Sep 2026. Skor HC Learn untuk esai adalah kemiripan teks terhadap kunci, tidak bermakna untuk esai 360. Angka mentahnya tetap ada di `Payload_Json`. |
| E2 | Jangkar kalibrasi esai = **(penugasan, `Kode_Soal_Sumber`, `Id_Master_Soal`)**. | `Id_Master_Soal` NULL = jawaban format lama (satu teks per bagian). |
| E3 | Kiriman **format lama tetap terbaca** apa adanya. | Data sebelum HC Learn berubah hanya punya `Jawaban_Teks` per bagian. |
| E4 | Butir esai disimpan di **tabel anak baru**, bukan dipecah menjadi baris skor. | Baris skor tetap satu per bagian, sehingga pemetaan kompetensi dan pemeriksaan `butir_hilang` tidak berubah. |

## 3. Bukti kontrak baru

Diperiksa langsung ke dokumentasi HC Learn staging (`/docs/api`, bundel `ApiDocs`), 26 Sep 2026.

Field baru pada tiap `Detail[]`:

| Field | Arti |
|---|---|
| `Flag_Essai` | `true` bila bagian ini esai |
| `Jumlah_Esai` | Sama dengan panjang `Esai[]`; 0 bila bukan esai |
| `Esai[]` | Selalu larik; kosong bila bukan esai |
| `Esai[].Id_Master_Soal` | Id soal di bank soal HC Learn |
| `Esai[].Pertanyaan` | Teks soal tanpa markup; `null` bila soal sudah terhapus dari bank |
| `Esai[].Jawaban_Peserta` | Jawaban penilai, utuh |
| `Esai[].Waktu_Dijawab` | `Y-m-d H:i:s` |
| `Esai[].Nilai`, `Rekomendasi_Nilai_Sistem`, `Alasan_Sistem`, `Sudah_Dikoreksi`, `Catatan_Koreksi` | Penilaian mesin/admin HC Learn; tidak dipakai (E1) |

Perubahan perilaku: bagian esai kini **berskor** (contoh: 85/100), dan `Jawaban_Teks` pada
bagian esai **`null`**.

Yang belum dipenuhi HC Learn:

- Katalog `/api/v1/pa/nama-tes` masih berhenti di bagian; tidak ada daftar soal. Soal yang
  belum dijawab siapa pun tidak bisa ditampilkan.
- Kestabilan `Id_Master_Soal` bila soal disunting belum dikonfirmasi.
- Belum ada kiriman sungguhan format baru di development (terakhir 19 Sep 2026, format lama).

## 4. Dampak bila tidak diubah

- **Esai hilang** dari Pratinjau Kalibrasi, catatan hasil, dan detail admin, karena
  seluruh pembaca menyaring `Jawaban_Teks IS NOT NULL`.
- **Skor mesin masuk ke Nilai Kompetensi** untuk bagian esai yang terpetakan.
- Jenis kompetensi kualitatif wajib dianggap **tidak tercakup** bila skornya dinolkan tanpa
  pembaca baru, sehingga hasil peserta bisa **TERTAHAN**.

## 5. Data

### 5.1 Tabel baru `N_PA_Assignment_Score_Esai` (DDL-2026-140)

Satu baris per soal esai pada satu baris skor.

| Kolom | Tipe | Null | Arti |
|---|---|---|---|
| `Id_PA_Assignment_Score_Esai` | `int IDENTITY` | NOT NULL | PK `PK_PA_Assignment_Score_Esai` |
| `PA_Assignment_Score_Id` | `int` | NOT NULL | FK `FK_PA_Assignment_Score_Esai_Score` → `N_PA_Assignment_Score` |
| `Id_Master_Soal` | `int` | NULL | Id soal HC Learn |
| `Urutan_Tampil` | `smallint` | NOT NULL | Urutan di `Esai[]`, mulai 1 |
| `Pertanyaan` | `nvarchar(max)` | NULL | NULL bila HC Learn mengirim null |
| `Jawaban_Teks` | `nvarchar(max)` | NULL | `Jawaban_Peserta` apa adanya |
| `Waktu_Dijawab` | `datetime2(0)` | NULL | |
| `Created_At` | `datetime2(0)` | NOT NULL | Bawaan `SYSDATETIME()` |

- Unik `UX_PA_Assignment_Score_Esai_Urutan (PA_Assignment_Score_Id, Urutan_Tampil)`.
- Tanpa `Flag_Aktif`: baris anak mengikuti `Flag_Aktif` baris skornya. Pemetaan ulang
  menonaktifkan baris skor lama dan menyisipkan anak baru untuk baris skor baru.

### 5.2 Amandemen DDL-2026-119 r3 — `N_PA_Kalibrasi_Penilai_Jawaban`

- Kolom baru `Id_Master_Soal int NULL`.
- `UX_PA_Kalibrasi_Penilai_Jawaban_Soal` menjadi
  `(PA_Kalibrasi_Penilai_Id, Kode_Soal_Sumber, Id_Master_Soal)`.

Syarat amandemen di tempat, diperiksa 26 Sep 2026: status `TRACKER_PENDING`,
`sql_tracker` PENDING, `production` PENDING, hanya pernah dijalankan di `Web_HRIS`
development, dan ketiga tabel kalibrasi berisi **0 baris**.

Penyelarasan development: kolom ditambah dengan `ALTER TABLE … ADD`, indeks diganti dengan
`CREATE UNIQUE INDEX … WITH (DROP_EXISTING = ON)`. Tidak ada `DROP` yang dijalankan.
**Perlu persetujuan**: `DROP_EXISTING` membangun ulang indeks di tempat tanpa menghapus data,
tetapi tetap mengganti definisi objek yang sudah ada.

## 6. Aturan pemetaan (`PaPayloadMapperService`)

Untuk tiap `Detail[]` dengan `Flag_Essai = true`:

1. `Skor` dan `Skor_Maksimal` disimpan **NULL** (E1). Validasi `Skor ≤ Skor_Maksimal` tetap
   dijalankan atas angka kiriman, supaya kiriman cacat tetap ditolak.
2. Tiap `Esai[]` disisipkan ke `N_PA_Assignment_Score_Esai`.
3. `Jawaban_Teks` bagian tetap disimpan bila HC Learn mengisinya.
4. Ditolak 422 bila `Jumlah_Esai` ≠ panjang `Esai[]`. Bagian esai tanpa jawaban sama sekali
   **tidak** ditolak: HC Learn memperlakukan 422 sebagai fatal, dan esai yang belum dijawab
   adalah keadaan sah (revisi implementasi 26 Sep 2026).

Bagian tanpa `Flag_Essai` diproses seperti sekarang.

## 7. Pembaca

Satu pintu tetap `PaSkorEfektif`. Satu "jawaban" kini berarti satu soal esai (format baru)
atau satu bagian (format lama).

| Pembaca | Perubahan |
|---|---|
| `PaSkorEfektif::jawabanNaratif()` | Mengembalikan satu entri per soal, dengan `pertanyaan` dan `id_master_soal`. |
| `PaSkorEfektif::timpaJawaban()` | Kunci revisi ditambah `Id_Master_Soal`. |
| `PaKalibrasiPenilaiService` | Menerima dan memvalidasi revisi per soal. |
| `PaResultCalculationService::jenisTerjawabTanpaAngka()` | Jenis kualitatif tercakup bila ada jawaban di baris skor **atau** di anak esainya. |
| `PaResultCalculationService` catatan hasil (±1992) | Satu catatan per soal: pertanyaan tebal di atas jawabannya (HTML aman). Soal yang pertanyaannya null hanya berisi jawaban. (Revisi implementasi: semula satu catatan per bagian; per soal lebih sederhana dan tetap urut.) |
| `PaParticipantReadService::jawabanNaratif()` | Detail admin menampilkan pertanyaan per jawaban (kunci baru `Pertanyaan`). |
| `PaAdminDashboardService` (±545, ±631) | Pemeriksaan "ada jawaban" ikut membaca anak esai. |
| Pemeriksaan ada-tidaknya jawaban | Satu potongan SQL `PaSkorEfektif::sqlAdaJawaban()`, dipakai keempat pembaca di atas. |

## 8. Halaman

`PaWizardPratinjauKalibrasi.vue`: setiap jawaban menampilkan **teks pertanyaan** sebagai
judulnya, dengan nama kompetensi sebagai keterangan kecil. Pertanyaan `null` tampil sebagai
"Pertanyaan tidak tersedia (soal sudah dihapus di HC Learn)". Format lama tetap berlabel nama
kompetensi seperti sekarang. Kunci draf esai di layar menjadi `kode_soal` + `id_master_soal`.

## 9. Pengujian

- Pemeta: payload contoh dokumentasi HC Learn (tanpa esai, beresai 4 soal, multi alat tes)
  dijadikan fixture. Skor bagian esai tersimpan NULL, empat anak esai tersimpan berurutan.
- Pemeta: tolak `Jumlah_Esai` tidak cocok.
- Perhitungan: jenis kualitatif tercakup lewat anak esai, dan hasilnya tidak TERTAHAN.
- Perhitungan: skor mesin bagian esai tidak mengubah Nilai Kompetensi.
- Kalibrasi: revisi dua soal pada satu bagian tersimpan terpisah dan terbaca terpisah.
- Format lama: seluruh uji kalibrasi esai yang ada tetap hijau.
- Layar: pertanyaan tampil di atas jawaban; pertanyaan null memakai teks pengganti.

## 10. Di luar cakupan

- Menampilkan soal yang belum dijawab (butuh katalog HC Learn per soal).
- Menyimpan penilaian mesin dan koreksi admin HC Learn.
- Mengubah simulator dan perintah seed, kecuali yang diperlukan agar uji berjalan.

## 11. Yang masih perlu dari HC Learn

1. Konfirmasi `Id_Master_Soal` tidak berubah saat soal disunting.
2. Satu kiriman sungguhan format baru ke development, untuk memastikan bentuknya sama dengan
   dokumentasi.
3. Daftar soal pada katalog, bila soal yang belum dijawab perlu ditampilkan.

## 12. Revisi 28 September 2026 — isi esai berupa HTML

HC Learn mengubah kontrak: `Esai[].Pertanyaan` dan `Esai[].Jawaban_Peserta` kini HTML editor
Quill **apa adanya, tidak dibersihkan** (daftar, penebalan, paragraf adalah bagian jawaban).

- Penyimpanan tidak berubah: teks mentah tetap disimpan utuh di `N_PA_Assignment_Score_Esai`
  dan `Payload_Json`.
- Pembersihan saat dibaca, satu pintu di `PaSkorEfektif`: `PaTeksKaya::dariEsaiHcLearn()`
  membersihkan isi ber-HTML dengan daftar izin tag yang sama dengan revisi penilai, dan
  meng-escape teks polos (kiriman lama). Pengenalan HTML hanya lewat nama tag yang dikenal,
  sehingga "2 < 3" atau "<kekuatan>" tetap teks.
- `jawaban_html` = HTML aman (layar teks kaya, editor kalibrasi, catatan hasil); `jawaban`,
  `jawaban_asli`, dan `pertanyaan` = teks polos berbaris (layar berteks biasa).
- `Jawaban_Teks` bagian (format lama) tetap diperlakukan sebagai teks polos.
