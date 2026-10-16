# DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi

## Metadata

- **Change ID**: `DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi`
- **Target Database**: `Web_HRIS`
- **Type**: DDL (+ DML pasangan `DML-2026-121`)
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `4132d216617e34cead9653a81edd68c248aeea30d5f41850ce89b948fb49be20`
- **DML Checksum (SHA-256)**: `2c400e9db20ac6a63945aa5778bdf37bc96cb269be43133541a9ed491daafd10`
- **Affected Objects**: `N_PA_Competency (Nilai_Ambang, Flag_Radar)`, `CK_PA_Competency_Ambang`, `DF_PA_Competency_Radar`, `N_SYSTEM_Database_Change`
- **Status**: `REVIEW_REQUIRED`

## Deskripsi

Dua kolom pada kamus kompetensi PA:

| Kolom | Tipe | Arti |
| --- | --- | --- |
| `Nilai_Ambang` | `decimal(5,2) NULL` | Ambang nilai (KKM) kompetensi, skala 0–100. `NULL` = belum ditetapkan. |
| `Flag_Radar` | `char(1) NOT NULL DEFAULT 'Y'` | `Y` = kompetensi ini menjadi sumbu radar; `T` = tidak. |

## Latar belakang

Radar kompetensi selama ini menggambar satu jaring saja: nilai orangnya. Jaring itu
menjawab "berapa", tidak pernah menjawab "cukup atau belum" — pembacanya membawa sendiri
standar yang ada di kepalanya, dan dua pembaca membawa standar yang berbeda.
`Nilai_Ambang` memberi radar jaring acuan kedua, sehingga jarak antara capaian dan
standar terbaca langsung dari bentuknya.

`Flag_Radar` menjawab masalah lain. Radar dengan belasan sumbu berubah menjadi lingkaran
bergerigi yang tidak terbaca, dan menambah satu kompetensi ke kamus tidak seharusnya
diam-diam merusak setiap radar yang sudah ada. Pilihan sumbu kini eksplisit dan diatur HC
dari `/performance-appraisal/master/kompetensi`.

## Skalanya 0–100, bukan 1–5 dan bukan 125

Nilai kompetensi dinormalkan sebelum dirata-ratakan:
`PaResultCalculationService::normal()` menghitung `Skor / Skor_Maksimal * 100`. Jadi
apa pun skala pengisian di HC Learn, angka yang tersimpan di `N_PA_Result_Competency`
berada di 0–100 dan ambang harus memakai skala yang sama. Angka `max: 125` yang terlihat
pada radar layar admin (`PaResultDialog.vue`) adalah kelonggaran gambar, bukan skala
nilai.

## Kamus, bukan snapshot per siklus — dan batas keputusannya

K26 (README `DDL-2026-067`) membagi tabel PA menjadi **kamus** dan **aturan**. Kamus
menyatakan ARTI dan dikoreksi global; aturan MENGUBAH HASIL orang dan wajib punya salinan
per siklus (`N_PA_Competency_Requirement`, `N_PA_Score_Range`). `N_PA_Competency` adalah
kamus.

Kedua kolom ini sengaja ditaruh di kamus. Keputusan diambil 11 September 2026 bersama
pemilik fitur, dengan alasan berikut:

- `Flag_Radar` murni penyajian — sekelas `Urutan_Tampil`, yang juga sudah tinggal di sini.
- `Nilai_Ambang` lebih dekat ke perbatasan, tetapi pada perubahan ini ia **tidak dipakai
  menghitung apa pun**: tidak masuk `N_PA_Result`, tidak menentukan grade, tidak
  menentukan kuadran HAV. Ia garis acuan yang digambar.

**Batasnya, dicatat di sini supaya tidak dilewati diam-diam:** begitu ambang dipakai untuk
memutuskan sesuatu — lulus/tidak, syarat promosi, penyaring kalibrasi, angka yang ikut
diekspor sebagai kesimpulan — ia berubah menjadi *aturan* menurut K26 dan **harus** pindah
ke snapshot per siklus lewat perubahan baru. Tanpa itu, mengubah KKM tahun depan diam-diam
menulis ulang kesimpulan siklus yang sudah ditutup.

## Keputusan fisik

- **`Nilai_Ambang` NULL-able, bukan default 0.** `NULL` berarti "belum ditetapkan", dan
  sumbu itu dilewati saat menggambar jaring KKM. Default `0` akan membuat jaring KKM
  penyok ke pusat pada sumbu yang sebenarnya belum diputuskan — terbaca sebagai standar
  yang sangat rendah, yaitu kekeliruan yang tidak akan pernah memunculkan galat.
- **`CHECK` 0–100 boleh ada di sini.** Aturan proyek melarang `CHECK` untuk membatasi
  *daftar nilai bisnis yang berkembang*; ini rentang angka tetap yang berasal dari rumus
  normalisasi, sekelas `CK_PA_Competency_Urutan` yang sudah ada pada tabel yang sama.
- **`Flag_Radar` berdefault `'Y'`.** Default itu mengenai seluruh baris lama, sehingga
  tidak ada radar yang berubah bentuk hanya karena kolomnya lahir. Pengecualian butir
  kualitatif dinyatakan terpisah di `DML-2026-121` — sebagai pernyataan tentang data,
  bukan kecerdasan yang disembunyikan di dalam default kolom.
- **Tanpa indeks.** `N_PA_Competency` adalah kamus berisi puluhan baris, selalu dibaca
  utuh lalu disaring di memori. Indeks pada `Flag_Radar` hanya menambah objek yang harus
  dirawat tanpa satu pun kueri yang memanfaatkannya.

## Aturan turunan (ditegakkan aplikasi, bukan database)

Kompetensi tak berskor (`Flag_Berskor = 'T'`) **tidak boleh** masuk radar dan ambangnya
wajib kosong. Ditegakkan di `PaMasterWriteService::simpanKompetensi()`, bukan sebagai
`CHECK` lintas kolom, supaya pesan galatnya bisa menyebutkan sebabnya kepada HC dan supaya
aturan ini bisa berkembang tanpa DDL baru.

Dibiarkan lolos, akibatnya adalah sumbu radar yang nilainya selamanya kosong — persis
kegagalan yang dulu melahirkan `Flag_Berskor`.

## Penyaringan dikerjakan layar, bukan kueri

Empat jalur baca radar (`PaPesertaService`, `PaParticipantReadService::nilaiKompetensi()`,
`PaLeaderService::radar()`, `PaResultCalculationService`) hanya **menambahkan**
`Nilai_Ambang` dan `Flag_Radar` ke `SELECT`-nya; tidak satu pun menyaring
`Flag_Radar = 'Y'`.

Alasannya: baris yang sama dipakai radar **dan** daftar rincian per kompetensi. Kalau
server yang menyaring, kompetensi non-radar ikut lenyap dari rincian — padahal ia tetap
dinilai dan nilainya tetap harus terbaca.

## Artefak

- `database/ddl/DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi/forward.sql`
- `database/ddl/DDL-2026-096-pa-ambang-dan-sumbu-radar-kompetensi/drop.sql` (manual; tidak dijalankan agen)
- `database/dml/DML-2026-121-kompetensi-kualitatif-keluar-dari-radar/data-change.sql`
- `database/dml/DML-2026-122-ambang-kompetensi-baku-80/data-change.sql`

## Nilai ambang yang berlaku

`DML-2026-122` menetapkan **80** untuk seluruh kompetensi berskor — keputusan HC
11 September 2026: satu ambang yang sama, bukan ambang per kompetensi. Rata bukan berarti
sementara; kolomnya memang per kompetensi, jadi membedakannya kelak cukup mengubah data,
tanpa DDL dan tanpa perubahan kode.

Butir kualitatif sengaja dilewati, dan baris nonaktif ikut diisi supaya ia kembali dengan
ambang yang sama seperti tetangganya bila diaktifkan lagi. Penyaring `Nilai_Ambang IS NULL`
membuat skrip itu aman diulang sekaligus menjaga agar menjalankannya ulang kelak tidak
menghapus penyetelan per kompetensi yang sudah dilakukan HC.

## Verifikasi development

Sudah dijalankan ke **dua** basis data pengembangan. Sasaran keduanya diverifikasi lewat
`SELECT DB_NAME()` + `@@SERVERNAME` sebelum satu pun batch dijalankan.

**Lokal (Web_HRIS @ 127.0.0.1:14330, Docker) — 11 September 2026.** Sebelum: 12 kolom,
24 baris (4 kualitatif). Sesudah: 14 kolom, `CK_PA_Competency_Ambang` dan
`DF_PA_Competency_Radar` terpasang. `forward.sql` dijalankan ulang dan berakhir no-op.

**Remote (Web_HRIS @ team311.dyndns.info:1433) — 14 September 2026.** Dijalankan dalam
rangka menyamakan skema sebelum konfigurasi aplikasi dipindah kembali ke sana. Sebelum:
`Nilai_Ambang` dan `Flag_Radar` tidak ada, ledger `DDL-2026-096` kosong. Sesudah: kedua
kolom ada, check constraint terpasang, ledger terisi satu baris. DML-2026-121 menyentuh
4 baris (`kualitatif_di_radar = 0`, `sumbu_radar = 20`) dan DML-2026-122 menyentuh 20
baris (`berskor_tanpa_ambang = 0`, `kualitatif_berambang = 0`, `berambang_80 = 20`,
`total = 24`). Ketiga artefak dijalankan ulang dan seluruhnya berakhir no-op.

Perbandingan skema penuh sesudahnya memulangkan **nol** selisih untuk 44 tabel `N_PA_`:
631 kolom, 123 indeks, 81 foreign key, dan 96 check constraint identik di kedua sisi.

Satu-satunya selisih data yang tersisa pada `N_PA_Competency` adalah `Flag_Aktif`:
delapan kompetensi (CV02, CV03, CV04, CV06, BH02, BH03, QL03, QL04) dinonaktifkan di
lokal lewat layar master dan masih aktif di remote. Itu keputusan admin, bukan efek
artefak ini, sehingga tidak disamakan sepihak — dan itulah sebab `sumbu_radar` terbaca
14 di lokal tetapi 20 di remote.

## Sisa pekerjaan user

- Meninjau dan menyetujui `forward.sql` serta `data-change.sql`.
- Mencatat keduanya ke SQL Tracker.
- Menjalankan ke production melalui SQL Tracker dan mengonfirmasi hasilnya.
- Mengisi `Nilai_Ambang` per kompetensi dari layar master; sampai diisi, jaring KKM tidak
  digambar pada sumbu tersebut.
