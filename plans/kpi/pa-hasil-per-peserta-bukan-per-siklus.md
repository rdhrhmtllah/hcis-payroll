# PA — hasil per peserta, bukan per siklus

Rancangan perubahan gerbang siklus Performance Appraisal, ditulis 5 September 2026.
**Belum dikerjakan.** Tujuan berkas ini: memperlihatkan dampaknya sebelum kodenya disentuh.

---

## 1. Masalahnya

Peserta yang penilaiannya sudah lengkap tetap tidak bisa dihitung, dikalibrasi, maupun
diterbitkan sampai jendela pengisian ditutup untuk **semua orang**. Akibatnya dua arah:

- Aji selesai 6/6 pada minggu pertama, tetapi menunggu sampai 30 September;
- lima orang yang telat menahan 854 orang yang sudah beres.

Yang mengikat mereka **bukan matematikanya**. Diperiksa langsung di kode:

| Bagian | Sumber | Bergantung peserta lain? |
|---|---|---|
| Nilai kompetensi | rata-rata tertimbang penilai → bobot kategori | tidak |
| Grade | `cariPita(FINAL, GRADE)` — pita **absolut** `Nilai_Dari ≤ n < Nilai_Sampai` | tidak |
| Kuadran HAV | dua pita absolut (sumbu KPI, sumbu Kompetensi) → matriks kuadran | tidak |
| Kalibrasi | per satu hasil, bersebab dan berjejak | tidak |

Tidak ada distribusi paksa, peringkat, maupun kurva. Nilai Aji 77 tetap 77 apa pun yang
terjadi pada 858 orang lainnya.

Yang mengikat adalah **gerbangnya**:

```php
self::HITUNG_HASIL => ['CLOSED'],
self::KALIBRASI    => ['CLOSED'],
self::TERBITKAN    => ['CLOSED'],
```

Itu gerbang proses, bukan gerbang kebenaran.

---

## 2. Yang diusulkan

Pindahkan syarat dari **status siklus** ke **kelengkapan peserta**, untuk tiga operasi itu
saja. Sisa matriksnya tidak berubah.

### 2.1 Definisi "peserta siap dihitung"

Peserta siap ketika seluruh penugasan **aktif** miliknya berstatus `SELESAI`:

```
tidak ada N_PA_Assignment WHERE PA_Participant_Id = X
                            AND Flag_Aktif = 'Y'
                            AND Status_Penugasan NOT IN ('SELESAI', 'DIBATALKAN')
```

`DIBATALKAN` dikecualikan karena penugasan yang dicabut memang bukan pekerjaan siapa pun
lagi — itu sudah jadi aturan di `PaPesertaService::kueriPenugasan()`, dan definisinya tidak
boleh ditulis ulang di tempat kedua.

Kelengkapan penilai **bukan** jaminan hasilnya berangka. Aji lengkap 6/6 tetapi `TERTAHAN`
karena KPI-nya belum disetujui — dan itu benar. Siap dihitung ≠ siap diterbitkan.

### 2.2 Matriks gerbang sesudahnya

| Operasi | Sekarang | Usulan |
|---|---|---|
| `HITUNG_HASIL` | `CLOSED` | `OPEN`, `CLOSED` + peserta lengkap |
| `KALIBRASI` | `CLOSED` | `OPEN`, `CLOSED` + hasil sudah bergrade |
| `TERBITKAN` | `CLOSED` | `OPEN`, `CLOSED` + hasil `DIHITUNG` |
| `SUNTING_BOBOT_PILAR` | `DRAFT…CLOSED` | **beku sejak terbitan pertama** — lihat §3.1 |
| lainnya | — | tidak berubah |

Gerbang tingkat siklus (`hitungSiklus`, `terbitkan` massal) tetap `CLOSED`: operasi massal
memang pekerjaan penutupan, dan melonggarkannya hanya menambah cara menembak kaki sendiri.

---

## 3. Syarat yang WAJIB ikut

### 3.1 Bobot pilar harus beku sejak terbitan pertama

`SUNTING_BOBOT_PILAR` kini berlaku sampai `CLOSED`, dan `Bobot_KPI_Terpakai` disimpan
**per baris hasil**. Kalau Aji terbit pada 75/25 lalu admin menggeser ke 65/35, satu siklus
akan memuat orang-orang yang dinilai dengan aturan berbeda — dan tidak ada satu kolom pun
yang mengatakannya. Ini kerusakan yang baru terlihat setahun kemudian, saat seseorang
membandingkan dua orang dan angkanya tidak masuk akal.

Karena itu: begitu ada **satu** hasil berstatus `TERBIT` di siklus, bobot pilar ditolak
perubahannya, dengan pesan yang menyebut berapa orang sudah terbit.

### 3.2 Payload susulan atas hasil terbit harus BERSUARA

`TERIMA_PAYLOAD` menerima pada `OPEN` dan `CLOSED` — sengaja, supaya kiriman HC Learn yang
datang tepat sebelum penutupan tidak hilang. `hitung()` menolak menghitung ulang hasil
`TERBIT` — juga benar.

Tetapi keduanya bersama menghasilkan lubang: payload untuk orang yang sudah terbit tersimpan
rapi dan **tidak pernah berpengaruh**, tanpa seorang pun tahu. Dengan penerbitan per orang,
lubang ini berubah dari langka menjadi rutin.

Yang harus ada: penanda pada payload tersebut, dan barisnya muncul di pemantauan sebagai
"kiriman sesudah terbit" supaya admin memutuskan — bukan hilang diam-diam.

### 3.3 Pencabutan penilai — SUDAH aman, tidak perlu apa-apa

Sempat dikhawatirkan: mencabut penilai dari peserta yang hasilnya sudah terbit akan mengubah
penyebut di belakang angka yang sudah dibaca orang. Diperiksa, dan ternyata sudah tertutup
dua lapis di `PaAssignmentBuilderService::batalkan()`:

1. gerbangnya `BUAT_PENUGASAN` — hanya `DRAFT` dan `PREPARED`, jadi pencabutan mustahil sejak
   siklus dikunci, apalagi saat `OPEN`;
2. penugasan berstatus `SELESAI` ditolak terpisah, dengan sebab yang sudah tepat:
   *"batalkan akan membuat hasil yang sudah dihitung tak bisa direproduksi."*

Tidak ada pekerjaan tambahan di sini.

---

## 4. Dampak ke kode

### 4.1 Berkas yang berubah

| Berkas | Perubahan |
|---|---|
| `PaCycleGate.php` | matriks `IZIN`; tambah pemeriksaan berbasis peserta |
| `PaResultCalculationService.php` | `hitung()` — gerbang per peserta (2 tempat: baris 128 siklus, 196 peserta) |
| `PaCalibrationService.php` | gerbang baris 85 |
| `PaResultPublishService.php` | `terbitkanPeserta()` baris 128; `terbitkan()`/`tutupPenerbitan()` tetap siklus |
| `PaCyclePillarWeightService.php` | penjaga baru §3.1 (baris 99 `boleh_sunting`, 113 `pastikanBoleh`) |
| `PaCycleStepService.php` | langkah `HASIL` tidak lagi menunggu `CLOSED` |
| `PaAdminDashboardService.php` | kolom "siap dihitung" + bucket verifikasi di pemantauan |
| `PaAuditCommand.php` | 4 pemeriksaan gerbang menyebut status lama |

### 4.2 Yang TIDAK berubah

- Rumus perhitungan — tidak satu baris pun. Ini murni soal kapan boleh dijalankan.
- `TERIMA_PAYLOAD`, `BUAT_PENUGASAN`, `KUNCI_SIKLUS`, `BUKA/TUTUP_PENGISIAN`.
- Operasi massal tingkat siklus.

### 4.3 Uji yang pasti merah

Setiap uji yang menegaskan "operasi X ditolak saat siklus OPEN" akan berubah maknanya.
Berdasarkan pola modul ini, penjaga di `PaAuditCommand` dan uji gerbang adalah yang pertama
terkena. **Suite penuh wajib dijalankan** — penjaga di service bersama menyentuh setiap
pemanggil, dan pelajaran celah 013 di modul SOP persis tentang ini.

---

## 5. Pemicu otomatis

Setelah gerbangnya per peserta, "hitung otomatis sesudah rater terakhir" baru masuk akal —
sebelumnya tidak, karena hasilnya tidak bisa ditindaklanjuti.

Tempat pemicunya: sesudah `PaPayloadMapperService::terimaDanPetakan()` berhasil, periksa
apakah peserta itu kini lengkap; bila ya, antrekan perhitungannya.

**Wajib lewat antrean, bukan di dalam permintaan HTTP callback.** Satu perhitungan memakan
sekitar 1,2 detik pada latensi sekarang; menambahkannya ke jalur callback berarti HC Learn
menunggu perhitungan kita, dan callback yang lambat akan diulang pengirimnya — menghasilkan
perhitungan berulang atas kiriman yang sama.

Idempotensi sudah aman: kiriman ulang beridentik dijawab tanpa membuat payload kedua, dan
`hitung()` menolak hasil yang sudah terbit.

---

## 6. Alur sesudah perubahan

```
LOCKED ──buka──> OPEN
                  │
                  ├─ penilai mengisi
                  ├─ peserta lengkap ──> hitung (otomatis, lewat antrean)
                  │                        │
                  │                        ├─ TERTAHAN ──> bucket "perlu perhatian"
                  │                        └─ DIHITUNG ──> bucket verifikasi admin
                  │                                          ├─ kalibrasi (opsional)
                  │                                          └─ terbit per orang
                  │                                               └─ bobot pilar BEKU
                  └──tutup──> CLOSED
                               └─ hitung massal untuk sisa yang belum lengkap
```

Yang telat tidak menahan siapa pun. Yang lengkap jalan terus.

---

## 7. Keputusan yang masih menunggu

Dua dari tiga pertanyaan awal sudah terjawab sendiri saat diperiksa:

- ~~Pencabutan penilai sesudah terbit~~ — sudah aman, lihat §3.3.
- ~~Peserta yang tak pernah lengkap~~ — aturannya sudah ada: kategori yang pengisinya di bawah
  `Min_Rater_Selesai` dikeluarkan dari perhitungan, dan bila tak satu kategori pun memenuhi
  ambang, hasilnya `TERTAHAN` dengan sebab tertulis. Tidak perlu aturan baru; yang berlaku saat
  penutupan massal sama persis dengan yang berlaku per orang.

Tersisa satu yang benar-benar keputusan Anda:

1. **Bucket verifikasi** — halaman baru, atau tab di `/performance-appraisal/pemantauan` yang
   sudah ada? Pemantauan sekarang sudah punya ringkasan, rekap kategori, dan daftar penuh
   berpaginasi, jadi menambah tab di sana jauh lebih murah daripada halaman baru — tetapi
   pemantauan menjawab "bagaimana pengisian berjalan", sedangkan bucket menjawab "apa yang
   menunggu keputusan saya". Dua pertanyaan berbeda di satu layar bisa membuat keduanya
   tumpul.
