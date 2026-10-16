# Kontrak HC Learn untuk Performance Appraisal — usulan HCIS

> ## DIGANTIKAN — JANGAN DIKIRIM
>
> Status per 3 September 2026: **rancangan awal, sudah digantikan.** Berkas ini ditahan
> karena memuat pembacaan baseline dari kode yang masih berguna sebagai rujukan, bukan
> karena masih berlaku.
>
> - **Yang dikirim ke HC Learn:** `2026-09-03-permintaan-kontrak-hc-learn-pa.md`
> - **Catatan kerja + riwayat keputusan:** `2026-09-01-kontrak-hc-learn-pa-siap-kirim.md`
>
> Dokumen pengganti memuat dua permintaan yang belum ada di sini — pemberitahuan saat
> penilai membuka formulir, dan pencabutan token — serta tujuh pertanyaan yang perlu
> dipastikan. Mengirim berkas ini akan meminta lebih sedikit daripada yang sebenarnya
> dibutuhkan, dan kekurangannya baru ketahuan setelah HC Learn selesai membangun.

Rancangan ini menyusun kontrak yang dibutuhkan PA, berjangkar pada dua bentuk yang sudah
berjalan. HC Learn menyatakan bentuk responsnya boleh dikustom sesuai kebutuhan.

---

## 1. Baseline yang sudah ada — dibaca dari kode, bukan diingat

### 1a. Pengiriman HCIS → HC Learn

`POST api/v1/penjadwalan/training/store`, dipakai Training dan SOP Kuis
(`app/Jobs/LMS/Training/Scheduling/ProcessBulkPenjadwalan.php:331`):

```json
{
  "Id_Master_Ujian": "<paket soal>",
  "Started_At": "2026-09-01 08:00:00",
  "Finished_At": "2026-09-30 17:00:00",
  "Jenis_User": "internal",
  "Flag_Camera": null,
  "User_Agent": "...",
  "Id_Karyawan": ["A1", "A2"],
  "Data_Karyawan": [ { } ],
  "Id_Integrasi": [ { "Kode_Karyawan": "A1", "Id_Integrasi": 123 } ]
}
```

Balasannya berisi `result[]` per karyawan, dari situ HCIS menyimpan `Id_Ujian_Token`
dan mengekstrak `Link_Ujian`.

### 1b. Balasan HC Learn → HCIS

`POST` callback pihak ketiga, tervalidasi di
`LmsThirdPartyCallbackController::completeExam` baris 25–30. **Hanya empat field:**

```json
{
  "Kode_Karyawan": "A1",
  "Id_Penjadwalan_Integrasi": 123,
  "Id_Ujian_Token": "<token>",
  "Score": 82
}
```

Satu angka agregat, 0–100.

### 1c. Contoh respons rekrutmen yang pernah diberikan

Bentuk ini yang sudah mendekati kebutuhan PA, karena rinci per butir: memuat
`Kode_Soal`, nilai per butir, `Bobot_Penilaian`, `Skor_Maksimal`, dan `Total_Nilai`.
Pada contohnya `(41×20 + 60×30 + 80×50) ÷ 100 = 66,2`, tepat sama dengan `Total_Nilai`
yang dilaporkan.

---

## 2. Kenapa baseline 1b tidak bisa dipakai PA apa adanya

Dua hal, dan yang pertama jauh lebih mendasar daripada yang kelihatan.

**Satu angka tidak cukup.** PA menghitung nilai per kompetensi, bukan satu nilai ujian.
Tanpa rincian per butir, tidak ada yang bisa dipetakan ke `N_PA_Competency`, HAV Map
tidak punya sumbu kompetensi, dan laporan per kompetensi mustahil dibuat.

**Kontrak lama hanya mengenal SATU orang.** `Kode_Karyawan` di sana adalah orang yang
mengerjakan ujian, dan nilainya milik orang itu juga. Di PA keduanya **berbeda**: yang
mengisi form adalah **penilai**, sedangkan nilainya milik **peserta yang dinilai**.
Kontrak lama tidak punya tempat untuk menyatakan "A menilai B".

### Keputusan rancangan: HC Learn tidak perlu tahu soal itu

Kami **tidak** meminta HC Learn menambahkan konsep peserta-dan-penilai. Token di HCIS
sudah dicetak per **penugasan**, dan satu penugasan sudah memuat pasangan
(peserta, penilai, kategori penilai) di `N_PA_Assignment`. Jadi cukup token itu
dikembalikan apa adanya, dan HCIS yang tahu artinya.

Konsekuensinya menguntungkan kedua pihak: HC Learn tetap menjadi tuan rumah form dan
soal, tanpa perlu memahami aturan penilaian 360°, dan perubahan aturan di HCIS tidak
pernah menuntut perubahan di sisi mereka.

---

## 3. Yang kami butuhkan

### 3a. Pengiriman — perubahan minimal dari 1a

Cukup satu tambahan: penanda bahwa satu penugasan berdiri sendiri, karena di PA
**satu orang bisa menerima banyak form sekaligus** (menilai lima bawahan berarti lima
form). Kontrak lama mengasumsikan satu karyawan satu ujian per paket.

```json
{
  "Id_Master_Ujian": "<paket soal PA per level>",
  "Started_At": "2026-09-01 08:00:00",
  "Finished_At": "2026-09-30 17:00:00",
  "Jenis_User": "internal",
  "Id_Penugasan": [
    { "Kode_Karyawan": "A1", "Id_Integrasi": 9001, "Label_Subjek": "Budi (Staff, IT)" },
    { "Kode_Karyawan": "A1", "Id_Integrasi": 9002, "Label_Subjek": "Sari (Staff, IT)" }
  ]
}
```

- `Kode_Karyawan` = **penilai**, yaitu orang yang login dan mengisi.
- `Id_Integrasi` = pengenal penugasan milik HCIS; **satu token per baris ini**, bukan
  per karyawan.
- `Label_Subjek` = teks bebas, hanya untuk ditampilkan di layar HC Learn supaya penilai
  tahu sedang menilai siapa. HC Learn tidak perlu mengartikannya, tidak perlu
  menyimpannya sebagai relasi, dan tidak perlu memvalidasinya.

Balasannya kami harapkan tetap seperti sekarang, tetapi **per `Id_Integrasi`**, bukan
per karyawan:

```json
{
  "result": [
    { "Id_Integrasi": 9001, "Id_Ujian_Token": "tok-aaa", "Link_Ujian": "https://..." },
    { "Id_Integrasi": 9002, "Id_Ujian_Token": "tok-bbb", "Link_Ujian": "https://..." }
  ]
}
```

### 3b. Balasan hasil — inilah bagian yang benar-benar baru

```json
{
  "Kode_Karyawan": "A1",
  "Id_Penjadwalan_Integrasi": 9001,
  "Id_Ujian_Token": "tok-aaa",
  "Id_Ujian_Nilai_Akhir": "hasil-7f3c9a",
  "Api_Version": "pa-v1",
  "Finished_At": "2026-09-12 10:45:00",
  "Total_Nilai": 66.20,
  "Detail": [
    {
      "Kode_Soal": "K01",
      "Nama_Soal": "Analytical Thinking",
      "Jenis_Soal": "skala",
      "Skor": 4,
      "Skor_Maksimal": 5,
      "Bobot_Penilaian": 20
    },
    {
      "Kode_Soal": "QL01",
      "Nama_Soal": "Kekuatan utama yang bersangkutan",
      "Jenis_Soal": "teks",
      "Skor": null,
      "Skor_Maksimal": null,
      "Bobot_Penilaian": null,
      "Jawaban_Teks": "Konsisten menutup temuan audit sebelum tenggat."
    }
  ]
}
```

**Kenapa tiap field diminta:**

| Field | Alasan |
|---|---|
| `Id_Ujian_Token` | Kunci sambung utama. HCIS menemukan penugasan, peserta, dan penilainya dari sini. |
| `Id_Penjadwalan_Integrasi` | Cocok-silang, bukan penentu. Kalau berbeda dengan token, token yang menang. |
| `Id_Ujian_Nilai_Akhir` | **Kunci idempotensi.** Kiriman ulang dengan nilai yang sama tidak boleh menghasilkan proyeksi ganda. Wajib unik per hasil, bukan per token. |
| `Api_Version` | Supaya perubahan bentuk di masa depan tidak memaksa menebak. |
| `Detail[].Kode_Soal` | Diterjemahkan HCIS menjadi kompetensi. Kode yang belum dikenali tetap disimpan, tidak dibuang. |
| `Detail[].Skor` + `Skor_Maksimal` | **Dikirim berpasangan.** Lihat catatan skala di bawah. |
| `Detail[].Bobot_Penilaian` | Dipakai HCIS membuktikan perhitungannya sama dengan HC Learn. |
| `Detail[].Jawaban_Teks` | Butir kualitatif tidak berangka; jawabannya tetap harus sampai. |
| `Total_Nilai` | Bukan sumber kebenaran, melainkan pembanding: HCIS menghitung ulang dari `Detail` dan membandingkannya. Selisih berarti ada yang perlu diperiksa. |

### 3c. Soal skala — pertanyaan yang hilang dengan sendirinya

Deck memakai dua skala berbeda untuk hal yang sama: 1–5 di sebagian slide dan persen di
slide lain. Selama ini itu jadi pertanyaan terbuka yang menahan pekerjaan.

**Kami tidak perlu HC Learn memilih salah satu.** Selama `Skor` selalu dikirim bersama
`Skor_Maksimal` milik butir itu, HCIS menormalkannya sendiri, dan satu paket boleh
memuat butir berskala 5 bersama butir berskala 100 — itu sudah diuji dan diterima di
`N_PA_Assignment_Score`.

Satu-satunya syarat: **`Skor_Maksimal` tidak boleh nol atau kosong untuk butir
berangka**, karena normalisasi akan membagi dengan nol. Butir teks memang mengirim
keduanya kosong, dan itu benar.

### 3d. Daftar kode soal

Kami butuh satu endpoint atau satu berkas berisi daftar `Kode_Soal` per paket, supaya
HCIS bisa memetakannya ke kompetensi **sebelum** siklus dijalankan, bukan setelah hasil
pertama datang. Bentuk paling sederhana sudah cukup:

```json
{ "Id_Master_Ujian": "PA-STAFF-2027",
  "Detail": [ { "Kode_Soal": "K01", "Nama_Soal": "...", "Jenis_Soal": "skala",
                "Skor_Maksimal": 5, "Bobot_Penilaian": 20 } ] }
```

Endpoint `api/v1/penjadwalan/training/options` yang sudah ada boleh dipakai kalau
isinya bisa memuat `Detail` seperti di atas.

---

## 4. Yang perlu dipastikan ke HC Learn

1. Bisakah satu karyawan menerima **beberapa token aktif** pada paket yang sama, satu
   per penugasan? Ini syarat mutlak PA — seorang manajer menilai banyak bawahan.
2. Bisakah `Label_Subjek` ditampilkan di layar pengerjaan tanpa perlu dimaknai?
3. Apakah `Id_Ujian_Nilai_Akhir` bisa dijamin unik per hasil, dan tetap sama bila
   kiriman diulang?
4. Bisakah `Detail` memuat butir teks dengan skor kosong?
5. Kalau paket disunting setelah dikirim, apakah token lama tetap mengembalikan susunan
   soal yang lama?

---

## 5. Akibatnya pada pekerjaan HCIS

Bila kontrak ini disepakati, tiga hal yang selama ini tertahan langsung terbuka:

- **T05 tidak lagi menahan `N_PA_Result`** — skala kanonik tidak diperlukan untuk
  menyimpan, hanya untuk pita grade, dan itu keputusan internal kita.
- **`N_PA_Competency_Kode_Soal` bisa diisi** begitu daftar kode datang.
- **Pemeta payload bisa ditulis**, karena bentuk masukannya sudah pasti.

Tidak ada perubahan skema yang dibutuhkan di sisi HCIS: `N_PA_Assignment_Payload` dan
`N_PA_Assignment_Score` sudah dirancang untuk bentuk ini, termasuk kolom `Api_Version`,
`Id_Ujian_Nilai_Akhir`, `Jawaban_Teks`, dan pasangan `Skor`/`Skor_Maksimal`.
