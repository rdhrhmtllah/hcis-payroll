# Koreksi target KPI dari halaman Enforcement

| Bidang | Nilai |
|---|---|
| Tanggal | 2026-09-03 |
| Modul | `KPI` |
| Submodul | Monitoring / Enforcement |
| Status | Menunggu tinjauan pengguna |
| Change ID database | `DDL-2026-064-kpi-koreksi-target` (belum dibuat) |

## Masalah

Nilai target KPI hanya bisa ditulis satu kali, saat parameter diajukan
(`FormKpiController`). Setelah itu tidak ada jalur pengubahan sama sekali.
Salah ketik karena itu permanen.

Kesalahan seperti itu bukan hipotesis. `kpi:cek-target-menyimpang` dibuat
setelah ditemukan satu parameter dengan target bulanan **100 di sebelas bulan
dan 100100 di satu bulan**. Angka itu ikut masuk penyebut YTD, jadi satu digit
kelebihan menjatuhkan achievement satu tahun penuh tanpa gejala apa pun di
layar.

Perintah itu sengaja **membaca saja**. Docblock-nya menyatakan alasannya: nilai
yang benar hanya diketahui pemilik prosesnya, sehingga memperbaikinya lewat
skrip justru berbahaya. Deteksinya sudah ada; yang belum ada adalah pintu bagi
manusia untuk menetapkan nilai yang benar.

Rancangan ini membuka pintu itu, dan hanya pintu itu.

## Keputusan yang sudah diambil

Diputuskan bersama pengguna sebelum rancangan ini ditulis:

1. **Untuk koreksi kesalahan input, bukan penyetelan ulang target.** Karena itu
   tidak ada alur persetujuan ulang. Kalau kelak target perlu berubah karena
   keputusan bisnis, itu fitur lain dengan kebutuhan lain.
2. **Draf memblokir koreksi.** Bila orangnya sudah mengisi tapi belum submit,
   koreksi ditolak. Alasannya di bagian 2, "Kenapa draf memblokir".
   Pekerjaan orang lain tidak diubah tanpa ia minta.
3. **Satu simpan boleh menyentuh beberapa periode**, dengan kelayakan dinilai
   per periode dan penolakan dilaporkan per periode.
4. **`Id_Target_Breakdown` dibiarkan tertaut** setelah koreksi, dan fakta bahwa
   angkanya diubah manusia disimpan di tabel jejak. Ini rekomendasi yang diambil
   tanpa pilihan eksplisit pengguna — silakan ditolak saat tinjauan; alternatifnya
   mengosongkan tautan itu, yang jujur bahwa nilainya kini manual tetapi
   menghapus asal-usulnya.

## Kosakata

| Istilah | Arti di dokumen ini |
|---|---|
| **Target** | satu baris `KPI_Pengajuan_Det`: satu parameter, satu periode |
| **Sel** | satu target di layar — perpotongan parameter dan bulan |
| **Final** | baris `KPI_Update_Performance_Detail` |
| **Draf** | baris `KPI_Update_Performance_Detail_Sementara` |
| **Koreksi** | satu perubahan `Nilai_Target` beserta sebab dan pelakunya |

Granularitasnya **per (parameter × periode)**, bukan per orang dan bukan per
bulan. Seseorang bisa punya Parameter A bulan Mei yang sudah dinilai sementara
Parameter B bulan Mei belum — B masih boleh dikoreksi, A terkunci.

## Rancangan

### 1. Gerbang kelayakan

Satu pemilik aturan: `App\Services\KPI\KpiTargetKoreksiService`.

```
periksaKelayakan(array $idPengajuanDet): array
    // [Id_Pengajuan_Det => ['boleh' => bool, 'kode' => string, 'keterangan' => string]]
```

Boleh dikoreksi bila **ketiganya** terpenuhi:

| # | Syarat | Kode penolakan bila gagal |
|---|---|---|
| 1 | Tidak ada baris final untuk `Id_Pengajuan_Det` itu | `SUDAH_FINAL` |
| 2 | Tidak ada baris draf untuk `Id_Pengajuan_Det` itu | `ADA_DRAF` |
| 3 | `KPI_Pengajuan` induknya `Flag_Aktif='Y'`, `Flag_Approval='Y'`, `Status` NULL | `PENGAJUAN_TIDAK_HIDUP` |

Penolakan `ADA_DRAF` **wajib menyertakan nama pemegang drafnya**. Tanpa itu
admin hanya melihat tombol yang mati dan tidak tahu siapa yang harus dikejar —
dan karena draf memblokir, koreksi bisa tertahan lama oleh draf yang menganggur.

Aturan ini **tidak boleh disalin** ke controller maupun ke layar. Pemeriksa
kelayakan (yang mematikan sel) dan jalur simpan memanggil fungsi yang sama.
Cacat yang paling sering berulang di proyek ini adalah aturan yang terpasang di
sebagian pintu saja.

### 2. Kenapa draf memblokir

Finalisasi **menyalin** angka dari draf, tidak menghitung ulang:

```php
// UpdatePerformanceController::createFinalPerformanceDetails()
'Nilai_Actual' => $item->Nilai_Actual,
'Score'        => $item->Score,
'Achievement'  => $item->Achievement,
```

Jadi bila target diubah sementara draf hidup, draf itu tetap menyimpan
achievement yang dihitung terhadap target **lama**, dan angka lama itulah yang
mendarat di record final saat orangnya submit. Tanpa error, tanpa gejala.

Di data dev saat rancangan ini ditulis ada 368 baris draf — populasinya nyata.

### 3. Skema — `DDL-2026-064-kpi-koreksi-target`

Tabel baru `N_KPI_Target_Koreksi`. `KPI_Pengajuan_Det` **tidak disentuh
strukturnya**.

| Kolom | Tipe | Isi |
|---|---|---|
| `Id_Target_Koreksi` | int identity PK | |
| `No_Batch` | varchar(40) | mengikat satu simpan multi-periode; nilai buram, cukup unik per simpan |
| `Id_Pengajuan_Det` | int | target yang dikoreksi |
| `Nilai_Target_Lama` | float | nilai sebelum koreksi |
| `Nilai_Target_Baru` | float | nilai sesudah koreksi |
| `Sebab` | nvarchar(500) | wajib, bebas teks; satu sebab per simpan, disalin ke tiap baris batch agar satu baris jejak bisa dibaca sendirian |
| `Id_User_Aktor` | int | pelaku |
| `Waktu` | datetime | |

**Kenapa tabel, bukan kolom pada `KPI_Pengajuan_Det`.** Koreksi bisa terjadi
lebih dari sekali. Kolom hanya mengingat yang terakhir, dan itu justru menghapus
riwayat yang jadi alasan fitur ini ada.

Tabel ini satu-satunya tempat nilai target asli tersimpan setelah koreksi.
Tanpa baris di sini, angka yang disepakati hilang tanpa bekas.

### 4. Izin

Kunci izin baru `kpiKoreksiTarget`, dalam grup rute sendiri.

**Bukan** menumpang `kpiEnforcementMonitoring`. Itu peran **pemantau**;
menambahkan mutasi ke situ memberi kuasa mengubah angka penilaian kepada semua
pemegang peran pemantau.

**Dan kunci ini TIDAK BOLEH didaftarkan di `N_HRIS_Grup_Fitur_Map`.**
`memilikiJabatan()` memeriksa grup fitur lebih dulu, dan sebuah role memberi
**seluruh** halaman grup fiturnya sekaligus — granularitasnya nol. Menaruh
`kpiKoreksiTarget` di grup KPI berarti memberikannya kepada setiap pemegang
role KPI mana pun, yang persis memusnahkan pemisahan yang jadi tujuannya.

Pemberiannya lewat jalur eksplisit per orang: baris `HRIS_Page_Access`
(`UserID_Web` + `Jenis_Page='kpiKoreksiTarget'` + `Flag_Aktif='Y'`), yang
dibaca `memilikiJabatanLegacy()` sebagai fallback. Itu satu-satunya jalur di
sistem ini yang bisa memberi izin kepada orang tertentu saja.

Tidak ada menu sidebar baru: layarnya menumpang halaman `/kpi-enforcement`
yang sudah ada, jadi tidak perlu `Sort_Order` maupun `flushMenuCache`.

### 5. Endpoint

```
GET  /api/v1/kpi-enforcement/target/kelayakan?ids[]=...
     -> status per target, untuk mematikan sel dan menampilkan alasannya

POST /api/v1/kpi-enforcement/target/koreksi
     body: { sebab, perubahan: [{ id_pengajuan_det, nilai_baru }] }
```

Simpan **dua langkah, gagal-tertutup**:

| Keadaan | Balasan |
|---|---|
| Semua lolos | terapkan, 200 |
| Ada yang ditolak | **422 + daftar penolakan, tidak ada yang diterapkan** |
| Ada yang ditolak, `?sebagian=1` | terapkan yang lolos, laporkan yang ditolak |

Hasil separuh harus jadi keputusan sadar, bukan efek samping. Pola ini
mengikuti `kpi:rapikan-target-sebelum-mulai`, yang melapor dulu dan menghapus
hanya bila `--terapkan` diberikan.

Penerapan berjalan dalam satu transaksi: penulisan `Nilai_Target` dan baris
jejaknya tidak boleh terpisah.

### 6. Peringatan menyimpang

Logika `CekTargetMenyimpang` dipakai ulang untuk memperingatkan bila **nilai
baru** justru menyimpang jauh dari saudara sebarisnya, supaya koreksi tidak
melahirkan salah ketik berikutnya.

Memperingatkan, **tidak memblokir**. Nilai yang benar tetap milik pemilik
prosesnya — prinsip yang sama yang membuat `CekTargetMenyimpang` tidak pernah
memperbaiki apa pun sendiri.

### 7. Layar

Di halaman `/kpi-enforcement`, pada monitoring KPI Parameter: sel target bisa
disunting bila layak, mati bila tidak, dengan alasan penolakan terbaca saat
disorot. Yang mati adalah **sel per sel** — bukan baris, bukan kolom.

Satu kotak sebab untuk satu simpan. Hasilnya ditampilkan apa adanya: berapa
diterapkan, berapa ditolak, dan kenapa.

## Yang tidak berubah

- Rumus, penjepitan, dan seluruh perhitungan skor.
- `KPI_Pengajuan_Det` sebagai bentuk tabel.
- Alur persetujuan parameter.
- Skor dan YTD yang sudah terbit. Penyebut YTD hanya menghitung periode yang
  punya update performance disetujui + terverifikasi, sehingga gerbang di
  bagian 1 menjamin tidak ada angka terbit yang bergeser.

## Pengujian

Semuanya berbasis **efek**, dan tiap penjaga wajib pernah dilihat MERAH.

| Penjaga | Cara memerahkannya |
|---|---|
| Menolak saat draf ada | cabut syarat 2 dari gerbang |
| Menolak saat final ada | cabut syarat 1 |
| Menolak saat pengajuan tak hidup | cabut syarat 3 |
| Baris jejak tercatat dengan nilai LAMA | tulis nilai baru ke kedua kolom |
| Penolakan `ADA_DRAF` menyebut pemegangnya | kosongkan namanya |
| Gagal-tertutup: ada ditolak → nol diterapkan | terapkan yang lolos tanpa `sebagian=1` |
| Peran pemantau saja tidak bisa memanggil | pasang jabatan lama di rute |

Fikstur **membuat keadaannya sendiri** (draf, final, pengajuan mati) di dalam
transaksi, tidak menunggu data dev kebetulan berbentuk begitu. Pelajaran dari
`CapPerParameterTersimpanTest`, yang selama ini melewati dirinya sendiri karena
jendela jadwal di dev sudah lewat — hijau di laporan, nol yang dibuktikan.

## Urutan pengerjaan

1. DDL-2026-064 + artefak dan dokumentasinya.
2. `KpiTargetKoreksiService`: gerbang + penerapan + penulisan jejak.
3. Penjaga gerbang (merah dulu).
4. Endpoint kelayakan dan koreksi + kunci izin `kpiKoreksiTarget`.
5. Penjaga endpoint, termasuk gagal-tertutup dan izin.
6. Layar.
7. Jalankan suite penuh KPI.

## Risiko

| Risiko | Penanganan |
|---|---|
| Draf basi membawa skor lama ke final | draf memblokir koreksi (bagian 2) |
| Target asli hilang tanpa bekas | `N_KPI_Target_Koreksi` (bagian 3) |
| Pemantau ikut dapat kuasa mengubah | kunci izin terpisah, diberikan per orang lewat `HRIS_Page_Access` dan sengaja TIDAK didaftarkan di grup fitur (bagian 4) |
| Koreksi melahirkan salah ketik baru | peringatan menyimpang (bagian 6) |
| Hasil separuh tak disadari | gagal-tertutup (bagian 5) |
| Koreksi tertahan draf menganggur | pemegang draf ditampilkan (bagian 1) |
| Aturan tersalin dan menyimpang | satu service, dipakai kedua jalur (bagian 1) |

## Basis verifikasi database

Diverifikasi pada `Web_HRIS` (development), 2026-09-03:

- `KPI_Pengajuan_Det` berkolom `Id_Pengajuan_Det, Id_Pengajuan_Detail,
  Nama_Target, Nilai_Target, Periode, Id_Target_Breakdown` — **tidak ada kolom
  jejak sama sekali**.
- Tidak ada jalur `update` ke `Nilai_Target` di seluruh `app/`; satu-satunya
  penulis adalah dua `insert` di `FormKpiController`.
- `memilikiJabatan()` memeriksa `N_HRIS_Grup_Fitur_Map` lebih dulu lalu jatuh ke
  `HRIS_Page_Access` per `UserID_Web` — dasar keputusan bagian 4.
- 14 berkas membaca `Nilai_Target`.
- 368 baris draf dan 305 baris detail final.
- Nomor bebas berikutnya pada `database/ddl/status.json` adalah 064.
