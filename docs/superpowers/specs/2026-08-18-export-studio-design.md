# Export Studio — Desain

**Tanggal:** 2026-08-18
**Cabang:** `feat/sop/export-expert`
**Status:** desain, menunggu review. Belum ada kode, belum ada DDL dijalankan.
**Modul pertama:** `LMS/SOP/Export` — mesinnya lintas modul sejak awal.

## 1. Masalah

Export SOP hari ini adalah tujuh jenis laporan yang dipatri di `match` pada
`SopComplianceExportService::buildDataset()`. Setiap pertanyaan baru dari
pengguna berarti satu cabang baru, satu deploy, dan satu antrean ke tim
pengembang. Padahal pertanyaannya hampir selalu variasi dari data yang sama.

Yang diminta: pengguna bisa menyusun laporannya sendiri dari data mentah —
seperti ProIn dan Mekari — sementara tim tetap menyediakan template resmi yang
sudah teragregasi.

## 2. Keputusan cakupan

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Kedalaman | dataset → kolom → agregasi/pivot | cukup untuk pertanyaan nyata tanpa membuka SQL bebas |
| Dataset per template | satu | join lintas dataset adalah fitur tersendiri, bukan v1 |
| Audiens | Admin SOP + atasan/leader tim | leader hanya melihat timnya, ditegakkan server |
| Jenis lama | jadi template sistem di mesin baru | satu mesin, dan "duplikat & ubah" jadi mungkin |
| Dataset v1 | kelimanya | kepatuhan, dokumen, katalog, absensi, audit |
| Masuk v1 | pratinjau langsung, berbagi & duplikat, bagan | |
| Ditunda | jadwal + pengiriman (WA/email) | jalannya disiapkan, kodenya tidak ditulis |

Cakupan implementasi v1 tetap **SOP saja**. Yang dibuat global adalah mesin dan
tabelnya, bukan jumlah modul yang didaftarkan.

## 3. Arsitektur

Tiga lapis, dengan satu ukuran keberhasilan: **modul baru bisa bergabung dengan
menulis satu berkas katalog, satu berkas route, dan satu skrip menu — tanpa
menyentuh mesin.** Bila modul kedua memaksa perubahan mesin, generalisasinya
gagal.

```text
app/Services/SYSTEM/Application/Reports/    mesin — kontrak, validator, compiler,
                                            template, pratinjau, export, bagan
app/Services/LMS/SOP/Export/                katalog dataset SOP
app/Http/Controllers/LMS/SOP/Export/
app/Jobs/LMS/SOP/Export/                    sudah ada
routes/LMS/SOP/Export/SopExportStudio.php
resources/js/components/SYSTEM/ReportStudio/    kerangka builder, lintas modul
resources/js/Pages/LMS/SOP/Export/SopExportStudioPage.vue   halaman tipis
resources/views/exports/report-pdf.blade.php                blade PDF generik
tests/{Unit,Feature}/LMS/SOP/Export/
tests/Unit/SYSTEM/Application/Reports/
```

Penempatan mesin di `SYSTEM/Application/` mengikuti preseden yang sudah ada:
`AbstractExportService` tinggal di `SYSTEM/Application/Exports/`.
`docs/architecture/module-structure-inventory.md` menyebut `SOP/Export` sebagai
kandidat pilot struktur baru untuk cabang ini.

### 3.1 Infrastruktur yang dipakai ulang, bukan dibangun

`AbstractExportService` sudah menyediakan lifecycle job, progress polling,
Activity Center, guard pembatalan, guard idempotensi retry Cloud Tasks,
penyimpanan GCS, dan `export:cleanup`. Mesin laporan hanya mengisi
`buildDataset()` dan `renderFile()`. Tidak ada infrastruktur async baru.

Bagan: `dompdf/php-svg-lib` sudah ada di vendor, jadi satu generator SVG
melayani pratinjau layar dan PDF sekaligus. `PhpSpreadsheet\Chart` menghasilkan
bagan native XLSX. Tidak ada dependency baru.

### 3.2 Kontrak dataset

```php
interface DatasetLaporan
{
    public function kunci(): string;    // 'kepatuhan'
    public function label(): string;
    public function grain(): string;    // satu kalimat, tampil di UI
    public function dimensi(): array;   // Dimensi[]
    public function ukuran(): array;    // Ukuran[]
    public function saringan(): array;  // Saringan[]
    public function baseQuery(): Builder;
    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder;
}
```

`Dimensi` = `key, label, select, tipe, grup, sensitif`.
`Ukuran` = `key, label, agregat, format, sensitif`.
`Saringan` = `key, label, kolom, tipe, operator[], sumberPilihan, wajib_saat_jalan`.

Dua aturan yang menentukan apakah mesin ini jujur:

**`select` dan `agregat` adalah ekspresi SQL utuh yang kami tulis, bukan
`agregasi × field` yang dirakit pengguna.** Grain dataset kepatuhan adalah
item; `COUNT(*)` di sana menghitung item, bukan orang. Bila pengguna boleh
memilih "COUNT" lalu "Karyawan", angkanya salah dan tampak benar. Karena itu
`jml_karyawan` **adalah** `COUNT(DISTINCT d.Kode_Karyawan)`, tak bisa dirakit
lain.

**Satu dataset = satu query yang bisa di-`GROUP BY`.** Ini yang membuat
agregasi turun ke SQL Server alih-alih menarik puluhan ribu baris ke PHP.
Modul yang butuh prefetch harus menyatakannya sebagai subquery/CTE di dalam
`baseQuery()`, bukan sebagai rangkaian query yang dijahit di PHP.

### 3.3 Definisi template

Seluruh nilainya adalah kunci katalog. Tidak ada SQL dari pengguna, sehingga
injeksi mustahil secara konstruksi — dan hal itu dijaga test, bukan disiplin.

```json
{
  "versi": 1,
  "dataset": "kepatuhan",
  "mode": "ringkasan",
  "dimensi": ["divisi", "paket"],
  "ukuran": ["jml_karyawan", "persen_selesai", "jml_terlambat"],
  "kolom": ["nama", "divisi", "paket", "status_item", "waktu_konfirmasi"],
  "saringan": [
    { "field": "periode",  "op": "antara",     "nilai": ["2026-07-01", "2026-09-30"] },
    { "field": "kategori", "op": "salah_satu", "nilai": [3, 7] },
    { "field": "kode_karyawan", "op": "sama_dengan", "wajib_saat_jalan": true }
  ],
  "urut":  [{ "key": "persen_selesai", "arah": "asc" }],
  "opsi":  { "subtotal": true, "grand_total": true, "batas": null },
  "bagan": { "tipe": "batang", "dimensi": "divisi", "ukuran": ["persen_selesai"] },
  "format_bawaan": "xlsx"
}
```

`wajib_saat_jalan` menandai saringan yang **tidak** menyimpan nilai; ia
ditanyakan saat dijalankan. Tanpa ini, template "Kartu Kepatuhan" hanya berguna
untuk satu orang. Dengan ini, satu template "Kepatuhan Divisi" melayani semua
manajer.

`DefinisiValidator` berjalan **dua kali**: saat simpan dan saat jalan. Template
bisa menjadi tak sah tanpa disentuh — field dihapus dari katalog, atau hak
pengguna berubah. Galat saat jalan wajib menyebut field mana yang bermasalah;
"template rusak" tanpa nama field memaksa pengguna membangun ulang dari nol.

### 3.4 Endpoint, berkas render, dan penomoran kode

Route per modul — wajib, karena otorisasi menempel pada halaman lewat
`N_HRIS_Grup_Fitur_Map`. `Module` disuntik dari route, tak pernah dari badan
permintaan.

| Method | URI (relatif `/sop/export-studio`) | Guna |
|---|---|---|
| GET | `/` | halaman Studio (Inertia) |
| GET | `/katalog/{dataset}` | dimensi, ukuran, saringan untuk perakit |
| GET | `/template` | galeri: resmi + milik saya + dibagikan |
| POST | `/template` | simpan template baru |
| PUT | `/template/{kode}` | ubah |
| POST | `/template/{kode}/duplikat` | duplikat, termasuk dari template sistem |
| DELETE | `/template/{kode}` | nonaktifkan (`Flag_Aktif = 'T'`, bukan hapus baris) |
| POST | `/pratinjau` | jalankan definisi dengan `TOP n`, tanpa berkas |
| POST | `/estimasi` | perkiraan jumlah baris/grup sebelum dijalankan |
| POST | `/jalankan` | kirim job export; balas `task_id` |

Menghapus template menonaktifkan barisnya, tidak menghapusnya —
`Kode_Template_Asal` pada template turunannya harus tetap bisa ditelusuri.

Render PDF memakai blade generik baru
`resources/views/exports/report-pdf.blade.php`, sejajar dengan
`sop-compliance-pdf.blade.php` yang sudah melayani ketujuh jenis lama. Blade
lama tidak diubah selama masa dua jalur.

`Kode` template dibangkitkan server: `<module>-<slug nama>-<4 hex>`, unik dalam
`(Module, Kode)`. Pengguna tidak memilihnya. Duplikat dari template sistem
mendapat kode baru dan mengisi `Kode_Template_Asal` dengan kode sistemnya —
kode sistem hidup di kode program dan tak pernah menempati baris tabel, jadi
tidak ada risiko tabrakan.

### 3.5 Lingkup baris

Lingkup **tidak pernah disimpan di template**. Ia diterapkan saat run oleh
`terapkanLingkup()`. Efeknya satu template melayani setiap leader dengan baris
timnya masing-masing — itulah yang membuat berbagi template aman.

Sumber tim: `HRIS_Karyawan_Team` (`Kode_Karyawan_Team` = leader).

## 4. Skema database

Dua tabel baru. Lintas modul sejak awal, dengan kolom `Module` sebagai pembeda —
pola yang sama dengan `N_HRIS_Async_Tasks` yang sudah dibagi tiga modul.

Prefix `N_HRIS_` dipilih karena seluruh infrastruktur lintas modul di repo ini
memakainya: `N_HRIS_Async_Tasks`, `N_HRIS_Cloud_Task_Log`,
`N_HRIS_Authorization_Audit`, `N_HRIS_Master_Grup_Fitur`. Tiga prefix lain
(`N_SOP_`, `N_LMS_`, `N_KPI_`) menandai kepemilikan satu domain.

```text
N_HRIS_Report_Template
  Id_Report_Template      int IDENTITY(1,1)  PK
  Module                  varchar(30)   NOT NULL   -- 'sop' | 'lms' | 'kpi'
  Kode                    varchar(60)   NOT NULL
  Nama                    nvarchar(150) NOT NULL
  Deskripsi               nvarchar(500) NULL
  Dataset_Key             varchar(40)   NOT NULL
  Mode                    varchar(20)   NOT NULL   -- RINCI | RINGKASAN
  Definisi_Json           nvarchar(max) NOT NULL
  Versi_Definisi          int           NOT NULL DEFAULT 1
  Tingkat_Bagi            varchar(20)   NOT NULL   -- PRIBADI | DIBAGIKAN | ORGANISASI
  Kode_Karyawan_Pemilik   varchar(50)   NOT NULL
  Kode_Template_Asal      varchar(60)   NULL       -- jejak duplikat
  Flag_Aktif              char(1)       NOT NULL DEFAULT 'Y'
  Created_At / Created_By / Updated_At / Updated_By
  UX_Report_Template_Kode      (Module, Kode)
  IX_Report_Template_Pemilik   (Module, Kode_Karyawan_Pemilik, Flag_Aktif)
  IX_Report_Template_Bagi      (Module, Tingkat_Bagi, Flag_Aktif) INCLUDE (Dataset_Key)

N_HRIS_Report_Template_Akses     -- hanya saat Tingkat_Bagi = 'DIBAGIKAN'
  Id_Report_Template_Akses  int IDENTITY(1,1)  PK
  Report_Template_Id        int          NOT NULL
  Tipe_Sasaran              varchar(20)  NOT NULL   -- KARYAWAN|DIVISI|DEPARTEMEN|LOKASI
  Nilai_Sasaran             varchar(100) NOT NULL
  Flag_Aktif                char(1)      NOT NULL DEFAULT 'Y'
  Created_At / Created_By
  UX_Report_Template_Akses_Sasaran (Report_Template_Id, Tipe_Sasaran, Nilai_Sasaran)
  IX_Report_Template_Akses_Cari    (Tipe_Sasaran, Nilai_Sasaran, Flag_Aktif)
```

Mengikuti `docs/database/table-design-rules.md`: `int` bukan `bigint`,
`datetime2`, panjang eksplisit, `Flag_*` char(1) Y/T, **tanpa CHECK constraint**
untuk daftar nilai bisnis yang berkembang (`Mode`, `Tingkat_Bagi`,
`Dataset_Key` divalidasi di backend). `ISJSON(Definisi_Json)` tetap dipakai
karena itu integritas struktur, bukan daftar nilai bisnis. Tanpa FOREIGN KEY,
mengikuti pola tabel `N_HRIS_*` lain.

Aktor audit: `Kode_Karyawan` dari `HRIS_Karyawan`, mengikuti `Requested_By` di
`N_HRIS_Async_Tasks` yang diisi
`optional(Auth::user()->karyawan)->Kode_Karyawan`.

### 4.1 Aturan keamanan yang lahir dari kolom `Module`

`Module` **selalu** berasal dari route, tak pernah dari badan permintaan.
Controller menyuntikkannya ke service lewat konstruktor, sehingga setiap query
terikat modul sejak lahir. Bila modul boleh dikirim pengguna, satu query yang
lupa menyaring langsung membocorkan daftar template modul lain.

Job Studio masuk `N_HRIS_Async_Tasks` dengan `Module = 'sop-studio'` (bukan
`'sop-compliance'`), supaya Activity Center bisa membedakannya dari export lama
selama masa transisi.

### 4.2 Yang sengaja tidak diberi tabel

| | Sebabnya |
|---|---|
| Riwayat jalan | `N_HRIS_Async_Tasks` sudah merekamnya |
| Riwayat definisi | cuplikan di payload job + `Versi_Definisi` |
| Favorit | YAGNI |
| Jadwal & penerima | ditunda; penambahannya murni aditif |

Template sistem didefinisikan di **kode**, bukan baris seed — menghindari drift
dev/prod, tetap masuk code review, dan membuat "duplikat & ubah" jadi salin
kode→baris.

## 5. Katalog dataset SOP

### 5.1 `kepatuhan` — inti

Baris = karyawan × penugasan paket × item. Dari `N_SOP_Penjadwalan_Detail` →
`N_SOP_Penjadwalan` → `N_SOP_Master_Paket` → `N_SOP_Paket_Item`, **LEFT JOIN**
`N_SOP_Progress`.

Tiga aturan bisnis dipatri di `baseQuery()` dan tak bisa dimatikan template:
`d.Flag_Aktif = 'Y'`, `d.Flag_Cabut_Akses = 'T'`, dan join progress yang LEFT.
Alasan yang ketiga sudah tertulis di docblock `SopExportRinciService`: item yang
belum pernah dibuka tidak punya baris progress, jadi inner join menghapusnya —
tunggakan lenyap dan kepatuhan terbaca lebih baik daripada kenyataannya.

- **Dimensi** — identitas: `kode`, `nama`, `no_induk`, `lokasi`, `divisi`,
  `departemen`, `jabatan`; paket: `paket`, `kode_paket`, `kategori`,
  `prioritas`, `status_paket`; item: `section`, `item`, `nomor_sop`,
  `tipe_item`, `wajib`; versi: `versi_aktif`, `versi_dibaca`, `status_item`,
  `perlu_ulang`; waktu: `bulan_assign`, `bulan_tenggat`, `bulan_konfirmasi`.
- **Ukuran** — `jml_karyawan` = `COUNT(DISTINCT d.Kode_Karyawan)`,
  `jml_penugasan`, `jml_item` = `COUNT(*)`, `jml_selesai`, `persen_selesai`,
  `jml_terlambat`, `telat_maks_hari`, `jml_perlu_ulang`, `durasi_baca_rata`,
  `skor_kuis_rata`.
- **Saringan** — `periode`, `divisi`/`departemen`/`lokasi`/`jabatan`/`paket`/
  `kategori`, `prioritas`, `status_paket`, `status_item`, `wajib`, `terlambat`.

Dimensinya mengambil ulang 38 kolom di `SopExportRinciService::columns()` —
katalog itu sudah ada dan sudah dipakai; menulis daftar kedua berarti membuat
dua daftar yang bisa berbeda.

### 5.2 `dokumen` — register kontrol

Baris = item dokumen × versi.

- **Dimensi** — `paket`, `dokumen`, `nomor_sop_resmi`, `versi_ke`,
  `status_versi`, `tanggal_berlaku`, `bulan_berlaku`, `disahkan_oleh`,
  `nomor_pengesahan`, `kategori`, `temuan`.
- **Ukuran** — `jml_dokumen`, `jml_versi`, `terdampak_target`,
  `terdampak_konfirmasi`, `persen_atestasi`, `umur_versi_hari`.

Lingkupnya tidak lurus dan keputusannya eksplisit: **dimensi register bersifat
organisasi**, karena dokumen SOP bukan data pribadi siapa pun — leader berhak
tahu SOP apa yang berlaku. Tapi **ukuran dampaknya** menghitung orang, jadi
untuk leader dipersempit ke timnya. Leader melihat register lengkap dengan
angka atestasi tim sendiri.

### 5.3 `katalog` — struktur paket & item

Baris = paket × section × item. Tanpa orang.

- **Dimensi** — `paket`, `kode_paket`, `kategori`, `prioritas`, `status_paket`,
  `versi_paket`, `section`, `urutan`, `item`, `tipe_item`, `wajib`,
  `punya_kuis`, `punya_berkas`.
- **Ukuran** — `jml_paket`, `jml_section`, `jml_item`, `jml_item_wajib`,
  `rata_item_per_paket`, `jml_versi_terbit`.

Satu-satunya dataset yang `terapkanLingkup()`-nya mengembalikan query apa
adanya, dan aman karena **tak ada orang di dalam barisnya**. Disebut eksplisit
supaya polanya tidak disalin ke dataset lain.

### 5.4 `absensi` — sesi baca

Baris = **peserta yang ditargetkan × sesi**, dari `N_SOP_Penjadwalan_Detail`
**LEFT JOIN** `N_SOP_Absensi`. Bila dibangun dari `N_SOP_Absensi`, yang terbaca
hanya orang yang hadir dan "siapa belum presensi" jadi mustahil dinyatakan —
padahal itu pertanyaan yang paling sering diajukan. Prinsip yang sama dengan
`kepatuhan`: yang belum terjadi tetap punya baris.

- **Dimensi** — `paket`, `sesi`, `tanggal_sesi`, `bulan_sesi`, `karyawan`,
  `divisi`, `departemen`, `lokasi`, `status_hadir`, `ada_foto`.
- **Ukuran** — `jml_target`, `jml_hadir`, `jml_belum_hadir`, `persen_hadir`,
  `jml_sesi`.

### 5.5 `audit` — ledger kepatuhan

Baris = satu kejadian di `N_SOP_Kepatuhan_Riwayat`. Docblock
`SopMonitoringAnalyticsService` menyebutnya satu-satunya time-series milik SOP,
jadi ini sumber alami bagan tren.

- **Dimensi** — `peristiwa` (SELESAI / DIBUKA_LAGI / DICABUT / KADALUARSA),
  `tanggal`, `minggu`, `bulan`, `paket`, `kategori`, `karyawan`, `divisi`,
  `alasan`.
- **Ukuran** — `jml_peristiwa`, `jml_karyawan_terdampak`, `persen_rata`,
  `jml_dibuka_lagi`, `jml_dicabut`, `jml_kadaluarsa`.

`Snapshot_JSON` tidak dibuka sebagai dimensi: isinya payload internal berbentuk
bebas, dan mengeksposnya mengunci bentuk internal itu jadi kontrak laporan
selamanya.

### 5.6 Field sensitif

| Tingkat | Contoh |
|---|---|
| `publik` | mayoritas dimensi |
| `terbatas` | `ip`, `perangkat`, `alasan` (audit), `foto` (absensi) |
| `dilarang` | `Snapshot_JSON` — tak pernah masuk katalog |

Ditegakkan **saat run, bukan saat simpan**. Bila template berbagi memuat field
`terbatas` dan penjalankannya tak berhak: **kolomnya tetap muncul, isinya
di-mask `—`**, dengan catatan kaki "N kolom disembunyikan sesuai hak akses".

Menghapus kolomnya diam-diam mengubah bentuk hasil, sehingga pivot dan rumus
Excel yang dibangun di atas template itu rusak untuk sebagian orang saja — mode
gagal yang paling sulit dilacak. Menolak menjalankan membuat fitur berbaginya
tak berguna: satu field sensitif membunuh seluruh template.

**Ukuran turunan field sensitif mewarisi tingkatnya.**
`COUNT(DISTINCT ip)` membocorkan persis apa yang di-mask di kolomnya. Dijaga
test integritas katalog.

Kelayakan diikat ke hak halaman monitoring admin SOP yang sudah ada — tidak
membuat sistem izin baru. Pemetaan persisnya wajib diverifikasi ke
`AuthorizationScopeResolver` dan `N_HRIS_Grup_Fitur_Map` saat implementasi.

### 5.7 Uji kertas kontrak

Kontrak dicocokkan ke tiga service export yang sudah ada, tanpa menulis kode.

| Service | Hasil |
|---|---|
| `PengumpulanDataExportService` | **Cocok, dan mempertajam kontrak.** Jawaban ada di `Data_Json`, jadi hanya muat karena `select` berupa ekspresi SQL (`JSON_VALUE(...)`). Bila `select` didefinisikan sebagai nama kolom, modul ini sudah tidak muat sekarang juga. |
| `LmsTrainingExportService` | **Cocok dengan syarat.** `computeAvgScores()` menghitung di PHP setelah `prefetchTrainingLookups()`. Mesin mendorongnya ke `GROUP BY`, tapi prefetch harus dinyatakan sebagai subquery/CTE di `baseQuery()`. |
| `KpiExportService` | **Temuan.** `filterRowsByStatus()` menyaring baris setelah query karena statusnya label hasil perhitungan. Mesin menuntut setiap saringan bisa dinyatakan di SQL. KPI perlu menurunkan derivasi status itu ke ekspresi SQL sebelum bisa bergabung. |

Kesimpulan: kontraknya benar dan harus tetap ketat — saringan yang berjalan di
PHP mustahil digabung dengan agregasi di SQL, dan melonggarkannya berarti
menarik seluruh baris ke memori worker. Yang berubah hanya ekspektasi:
onboarding KPI tidak gratis. Lebih murah diketahui sekarang.

## 6. Layar Studio

Bukan wizard. Pratinjau langsung masuk v1, dan wizard membunuhnya — orang harus
menyelesaikan seluruh langkah sebelum melihat apa pun, padahal menyusun laporan
itu iteratif. Satu layar terbagi dua (`HcisSplitter`): perakit di kiri, hasil di
kanan. Pengecualiannya pilihan dataset, yang tetap gerbang di depan karena
mengubahnya membuang seluruh dimensi, ukuran, dan saringan.

Komponen dari design system HCIS yang sudah ada: `HcisTransfer` (dimensi &
ukuran), `HcisSplitter`, `HcisDataTable`, `HcisFilterBar`/`HcisFilterField`,
`HcisSwitch`, `HcisTabs`, `HcisSkeleton`, `HcisEmptyState`, `HcisResultState`,
`HcisCard`, `HcisDialog`.

`components/SYSTEM/ReportStudio/` **bukan** design system — ia komponen fitur
milik modul SYSTEM yang *memakai* `Hcis*`, tidak *menjadi* `Hcis*`. Tidak
didaftarkan ke `registry.js`.

**Urutan dimensi bermakna**: urutan dimensi adalah urutan `GROUP BY` dan
menentukan bersarangnya subtotal, jadi panel kanan harus bisa diurut ulang.
Bila `HcisTransfer` belum mendukungnya, penambahannya ada di pembungkus kami —
bukan dengan menambal komponen DS.

**Berpindah mode tidak membuang pilihan mode satunya.** Ringkasan dan rinci
sama-sama hidup di definisi, jadi bolak-balik gratis.

**Pratinjau menjalankan compiler yang sama persis dengan export** — ia adalah
export-nya, hanya dengan `TOP n` dan tanpa menulis berkas. Tidak ada jalur kedua
yang bisa berbeda pendapat dengan berkas hasilnya. Debounce 400 ms, request
berjalan di-abort saat definisi berubah lagi.

**Galeri** menjadi pintu masuk: Resmi / Milik Saya / Dibagikan. Tujuh template
resmi berlencana dan tak bisa diubah; yang bisa dilakukan adalah menjalankan
atau **Duplikat & ubah**. Itu jalur belajar terpenting di seluruh fitur —
halaman kosong dengan 38 dimensi membuat orang menyerah; "ambil laporan resmi
yang sudah kamu kenal, ganti satu kolom" tidak.

**Status layar wajib**: memuat (`HcisSkeleton`, hanya di panel pratinjau),
kosong (dibedakan: "pilih minimal satu ukuran" ≠ "saringanmu menghasilkan 0
baris"), galat (`HcisResultState` + coba lagi), dan definisi tak sah — yang
muncul **inline di panel penyebabnya**, bukan sebagai toast. "Mode ringkasan
butuh minimal satu ukuran" tak berguna melayang di pojok layar.

**Ponsel**: Studio desktop-first. Di ponsel tampil galeri, jalankan, dan unduh;
perakitnya turun jadi ringkasan definisi yang bisa dibaca, tidak bisa diubah.
Responsif berarti tetap berguna, bukan tetap identik.

**Tidak ada**: kotak SQL bebas, perakit join, penjadwalan, editor hak per-field.
Untuk kemajuan export tidak ada UI baru — Activity Center dan progress polling
sudah berjalan.

## 7. Migrasi tujuh jenis lama

`buildDataset()` bercabang ke **tujuh**, bukan enam: `kartu`, `coverage`,
`karyawan`, `rinci`, `atestasi`, `dokumen`, `risiko`. Docblock di atasnya hanya
mendaftar enam — `rinci` tak tertulis di sana padahal metodenya dipakai jalur
export v2.

Ketujuhnya sudah mengembalikan bentuk yang identik
(`title`, `generated_at`, `columns[]`, `rows[]`), itulah sebabnya satu blade PDF
melayani semuanya. Mesin baru tidak perlu menciptakan kontrak keluaran.

### 7.1 Lima dari tujuh tidak mengandung query sendiri

`coverage` → `SopMonitoringService::coveragePaket()`; `karyawan` →
`SopMonitoringPeopleService::employees()`; `rinci` → `SopExportRinciService`;
`dokumen` → `SopDocumentControlService::register()`; `risiko` →
`SopMonitoringAnalyticsService::papanRisiko()`.

Kelimanya berbagi service dengan layar monitoring, **disengaja**. Docblock
`datasetCoverage` mencatat sebabnya: query-nya dulu disalin ke sana, salinannya
menyimpang, dan kolom `Terlambat` yang tampil di layar tak pernah ikut
terekspor.

Menulis ulang kelimanya secara deklaratif **menghidupkan kembali duplikasi yang
sengaja dihapus itu**. Memigrasi layar monitoring sekalian bukan jawabannya —
itu memperluas scope aktif, yang dilarang `AGENTS.md`.

Sikapnya: **terima duplikasinya, tapi jangan biarkan ia diam.** Harness
pembanding di §7.4 tinggal permanen di CI, bukan dibuang setelah migrasi.
Selama ia hijau, kedua implementasi setuju berdasarkan bukti. Duplikasinya
nyata dan tidak dikecilkan; yang ditolak adalah duplikasi yang tak terdeteksi.

### 7.2 Klasifikasi

| jenis | dataset | mode | catatan |
|---|---|---|---|
| `rinci` | kepatuhan | rinci | paling langsung — katalognya sudah jadi |
| `karyawan` | kepatuhan | ringkasan | dimensi = identitas karyawan |
| `coverage` | kepatuhan | ringkasan | dimensi = paket |
| `risiko` | kepatuhan | ringkasan | butuh `opsi.batas` (hari ini keras di 200) |
| `dokumen` | dokumen | rinci | cocok |
| `kartu` | kepatuhan | ringkasan | butuh `wajib_saat_jalan` |
| `atestasi` | kepatuhan | rinci | `wajib_saat_jalan` + field `terbatas` |

`risiko` memotong di 200 baris teratas dan itu memang maksudnya — papan risiko
adalah peringkat, bukan sensus. Bila `opsi.batas` aktif, jumlah yang terpotong
ditulis di kaki laporan.

### 7.3 Perubahan perilaku yang disengaja

`atestasi` memuat `ip` dan `perangkat`, yang di §5.6 ditandai `terbatas`.
Diterapkan mentah, migrasi ini **memperketat** perilaku.

Supaya tak ada yang kehilangan akses: template sistem "Bukti Atestasi" tetap
membawa field penuh untuk siapa pun yang hari ini sudah bisa memanggil endpoint
lamanya — orangnya sama, haknya sama. Pembatasan `terbatas` hanya berlaku pada
template buatan pengguna yang dibagikan. Yang diperketat jalur baru, bukan
jalur yang sudah berjalan.

### 7.4 Harness pembanding

Jalur lama tidak dipensiunkan karena kodenya sudah ditulis, tapi karena
berkasnya terbukti sama.

| Format | Cara banding | Alasan |
|---|---|---|
| CSV | byte identik | deterministik penuh setelah waktu dipatok |
| XLSX | sel demi sel: nilai, tipe, label header, lebar kolom | zip XLSX menyimpan timestamp |
| PDF | teks hasil ekstraksi, baris demi baris | dompdf menulis `CreationDate` |

`Carbon::setTestNow()` mematok `generated_at` dan judul.

**Matriks**: 7 jenis × 3 format × 3 skenario saringan (kosong, sempit, luas) =
63 kasus di suite reguler. Skenario nol baris dan skenario melewati
`MAKS_BARIS` dijalankan sekali per jenis yang bisa mengalaminya (`rinci`,
`karyawan`, `coverage`, `dokumen`) di suite lambat.

Skenario `MAKS_BARIS` wajib ada. Docblock `datasetRinci` menegaskan pemotongan
ditulis di judul berkas, dengan alasan yang tepat: berkas yang diam-diam
terpotong lebih berbahaya daripada berkas yang gagal, karena angkanya tetap
terbaca masuk akal. Kehilangan perilaku itu berarti menukar bug keras jadi bug
sunyi.

**Fixture**: `tests/Feature/LMS/SOP/Uat/Support/UatDunia.php` sudah ada —
pembangun dunia SOP untuk test backend, hidup di dalam transaksi dan lenyap
saat rollback.

### 7.5 Syarat pensiun, berurutan

1. Ketujuh template sistem berjalan di mesin baru; seluruh 63 + 8 kasus hijau.
2. Endpoint lama tetap hidup tapi diarahkan ke mesin baru — satu rilis, dua
   jalur, keluaran sama.
3. Setelah satu siklus pemakaian nyata tanpa keluhan,
   `SopComplianceExportService` disusutkan jadi pembungkus tipis.
4. Penghapusan kelasnya adalah task tersendiri dengan review sendiri, **bukan
   bagian fitur ini**.

Tidak dipensiunkan: `SopMonitoringService`, `SopMonitoringPeopleService`,
`SopExportRinciService`, `SopDocumentControlService`,
`SopMonitoringAnalyticsService` — kelimanya melayani layar, bukan cuma export.

## 8. Penjaga performa

Untuk lima dari tujuh jenis lama, mesin baru **lebih ringan**. Jalur lama
menarik seluruh baris ke PHP lalu memformatnya (`datasetKaryawan` dalam loop
halaman 500 baris); mode ringkasan mendorong agregasinya ke `GROUP BY` dan hanya
menerima baris hasil. Mode rinci setara.

Risiko yang tak dimiliki jalur lama: **tujuh query tetap yang bisa ditala
berganti jadi query yang disusun pengguna.**

```text
dimensi  ≤ 6      ukuran   ≤ 12
kolom    ≤ 60     saringan ≤ 20
```

Enam dimensi bukan angka sembarangan — subtotal bersarang di kedalaman tujuh
sudah tak terbaca manusia.

Penjaga terbaik untuk query lambat bukan timeout melainkan **saringan periode**.
Untuk `audit` dan `absensi` — tabel kejadian yang tumbuh selamanya — periode
**wajib**. Untuk `kepatuhan` tidak wajib ("seluruh penugasan aktif" itu
pertanyaan sah), tapi ketiadaannya memunculkan peringatan beserta estimasi
baris.

| | Pratinjau | Export |
|---|---|---|
| Dijalankan di | request web | worker Cloud Tasks |
| Batas | 200 grup / 100 baris | `MAKS_BARIS` = 50000 |
| Timeout | ~5 detik | batas Cloud Run |
| Saat lewat batas | turun anggun | tulis pemotongan di judul |

Turun anggun: pratinjau yang terlalu berat **tidak** memblokir export. Layar
menampilkan "terlalu berat untuk dipratinjau — masih bisa dijalankan sebagai
export". Kehilangan pratinjau bukan alasan mencabut fiturnya.

Kaki tabel pratinjau selalu menyebut "200 dari 418 grup", tak pernah diam.

## 9. Penanganan galat

Diwarisi dari `AbstractExportService` tanpa ditulis ulang: guard idempotensi
(`ready` + `File_Path` → langsung kembali), klaim job lewat update bersyarat
(`$claimed === 0` → berhenti), `ABORT_CHECK_EVERY = 25`,
`ExportAbortedException` dengan pemeriksaan sebelum dan sesudah render, dan
`Error_Message` yang selalu aman dibaca pengguna.

Tiga mode gagal baru: definisi jadi tak sah tanpa disentuh (validator dua kali);
template diubah/dihapus saat job antre (payload membawa cuplikan definisi
terkompilasi — pola yang sama dengan "simpan saringan, bukan daftar id"); dan
field sensitif pada penjalankan tak berhak (bukan galat — mask + catatan kaki).

| `error_code` | HTTP | Kapan |
|---|---|---|
| `REPORT_DEFINITION_INVALID` | 422 | validator gagal; `errors` per field |
| `REPORT_DATASET_UNKNOWN` | 404 | `Dataset_Key` tak ada di registry modul |
| `REPORT_TEMPLATE_FORBIDDEN` | 403 | template milik modul atau orang lain |
| `REPORT_PREVIEW_TOO_HEAVY` | 422 | layar memperlakukannya sebagai turun anggun |

Response memakai `App\Helpers\ResponseHelper`. Log memakai channel yang sudah
ada dengan konteks `export_id`, `module`, `dataset`, kode template, dan **hash
definisi** — bukan definisinya, dan tanpa secret.

## 10. Strategi test

Menyentuh authorization, database, response bersama, dan Cloud Tasks →
**High risk** menurut `testing-and-quality-standard.md` §7. Gerbangnya penuh:
test-first, unit, component, feature/API, integration/contract, browser smoke,
impact regression, build/static check, manual acceptance.

**Unit** — `tests/Unit/SYSTEM/Application/Reports/`, `tests/Unit/LMS/SOP/Export/`

- Validator: satu test per aturan penolakan.
- Compiler: `GROUP BY` benar-benar terbentuk.
- **Kunci bermusuhan ditolak, bukan di-escape.** Klaim "injeksi mustahil secara
  konstruksi" hanya benar selama tak ada jalur yang menerima string bebas; itu
  dijaga test, bukan disiplin.
- **Integritas katalog**: setiap ukuran turunan field sensitif ikut bertanda
  sensitif. Murah, dan menutup seluruh kelas kebocoran.

**Feature** — `tests/Feature/LMS/SOP/Export/`

- 63 + 8 kasus pembanding (§7.4).
- **Leader menjalankan template yang dibuat admin dan dibagikan → hanya baris
  timnya.** Test terpenting di seluruh fitur: membuktikan lingkup diterapkan
  saat run, bukan tersimpan di template.
- Leader menjalankan template bermuatan field atestasi → ter-mask + catatan kaki.
- Endpoint SOP tak bisa membaca/menulis template dengan `Module = 'kpi'`.
- `process()` dipanggil dua kali → yang kedua kembali tanpa menimpa berkas.
- Abort di tengah proses → status bersih, bukan `failed` generik.

**Component** (Vitest): panel terbangun dari fixture katalog; berpindah mode
tidak membuang pilihan; request pratinjau dibatalkan saat definisi berubah;
keempat status layar.

**Integration**: pratinjau dan export menghasilkan baris identik untuk definisi
yang sama — jaminan §6 diuji, bukan dijanjikan.

**Browser smoke**: buka Studio → duplikat template resmi → ganti satu ukuran →
jalankan → unduh.

**Gerbang struktural**: kelima skrip di `scripts/`. Baseline sudah diambil
sebelum implementasi:

```text
plans/sop/baseline/export-studio-sebelum-route.json     1173 route
plans/sop/baseline/export-studio-sebelum-console.json    170 command
```

Sengaja **tidak** diuji: rendering piksel PDF dan penampilan bagan Excel. Yang
dibandingkan datanya, bukan gambarnya — test penjaga piksel gagal setiap kali
dompdf naik versi, lalu orang berhenti mempercayainya.

## 11. Artefak perubahan database

```text
database/ddl/DDL-2026-009-create-report-template-tables/forward.sql
database/ddl/DDL-2026-009-create-report-template-tables/drop.sql
database/dml/DML-2026-010-register-sop-export-studio-menu/data-change.sql
docs/changes/DDL-2026-009-create-report-template-tables/README.md
docs/changes/DML-2026-010-register-sop-export-studio-menu/README.md
docs/database/tables/hris/n-hris-report-template.md
docs/database/tables/hris/n-hris-report-template-akses.md
+ entri di database/ddl/status.json
```

Nomor 001–008 sudah terpakai. `drop.sql` adalah alat bantu manual dan tidak
dijalankan agent.

Menu DML mengikuti `DML-2026-008-register-pengumpulan-data-menu`: baris menu
plus pemetaan ke `N_HRIS_Grup_Fitur_Map`. Milik kita memetakan ke grup **LMS**,
bukan ESS. Tanpa langkah pemetaan itu menu tak pernah muncul dan tidak ada galat
apa pun.

## 12. Basis verifikasi database

`AGENTS.md` mewajibkan basis verifikasi dicatat, dan snapshot dokumentasi bukan
otoritas.

| | |
|---|---|
| Environment | `Web_HRIS` (development), host team311 |
| Grup fitur | `LMS`, `Id_Grup_Fitur = 3` |
| Mapping page | `N_HRIS_Grup_Fitur_Map` — `Jenis_page` untuk halaman Studio, belum dibuat |
| Status | **BELUM DIVERIFIKASI ULANG pada sesi ini** |

Angka `Id_Grup_Fitur = 3` berasal dari snapshot bertanggal 2026-08-18 di
`docs/architecture/module-registry.md`. Wajib dibaca ulang dari
`N_HRIS_Master_Grup_Fitur` dan `N_HRIS_Grup_Fitur_Map` sebelum DDL/DML
dijalankan, bukan disalin dari sini.

## 13. Yang ditunda, dan jalannya

Jadwal + pengiriman (WA/email). Infrastrukturnya sudah ada — scheduler berbasis
DB di `app/Console/Kernel.php`, `SendWhatsappJob`, service notifikasi.
Penambahannya murni aditif: dua tabel jadwal/penerima, satu command, satu panel
di galeri yang tempatnya sudah disiapkan sebagai kolom kosong — bukan kode mati.

## 14. Keputusan terbuka

1. Aktor audit `Created_By`/`Updated_By` = `Kode_Karyawan`, `varchar(100)` —
   diusulkan, menunggu konfirmasi eksplisit sebelum DDL (diwajibkan
   `docs/database/table-design-rules.md`).
2. Penempatan `resources/js/components/SYSTEM/` — konvensinya
   `components/<MODUL>/` dan pemilik mesin memang SYSTEM, tapi belum ada
   preseden `components/SYSTEM/` di repo. Dicocokkan ke registry saat
   implementasi.
3. Pemetaan tingkat `terbatas` ke hak halaman monitoring admin SOP — wajib
   diverifikasi ke `AuthorizationScopeResolver` dan `N_HRIS_Grup_Fitur_Map`.
