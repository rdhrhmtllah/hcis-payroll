# Daftar dokumen bersama: Public Document mengikuti Functional Document

**Tanggal:** 29 Agustus 2026
**Modul:** LMS · submodul SOP (DMS) · fitur Public Document & Functional Document

## Masalah

Public Document (`SopRepository`) dan Functional Document (`SopSaya`) menampilkan
benda yang sama — paket dokumen beserta isinya — dengan dua cara yang sama
sekali berbeda. Functional Document memakai tata letak master–detail: rail
"Kelompokkan per" di kiri, panel dengan tiga bentuk tampilan (Kartu · Tabel ·
Dokumen) di kanan. Public Document memakai grid kartu datar dengan chip lensa
dan paginasi.

Permintaannya bukan sekadar menyeragamkan rupa, melainkan menutup celah yang
melahirkan perbedaan itu: selama kedua halaman memegang markup daftarnya
masing-masing, perbaikan pada satu tak pernah sampai ke yang lain, dan tak ada
satu pun uji yang memerah ketika keduanya menyimpang.

## Keputusan

Satu komponen tampilan, dua penyedia data.

`Shared/components/listing/SopListingDokumen.vue` menjadi satu-satunya tempat
daftar digambar. Ia **tidak memanggil axios, tidak mengelompokkan, dan tidak
tahu ada server**. Itu bukan kebersihan arsitektur demi dirinya sendiri
melainkan syarat teknisnya: kedua halaman memperoleh isinya dengan cara yang
sangat berbeda, dan hanya dengan mengusir perbedaan itu ke pemanggil, satu
tampilan bisa melayani keduanya.

| | Functional Document | Public Document |
|---|---|---|
| Pengelompokan | di memori (`bangunGrup`) | di server (`GET …/rekap`) |
| Daftar | seluruhnya di memori | dipotong per halaman di server |
| Prop `paginasi` | `null` | `{halaman, halamanTotal, total}` |
| Prop `rujukan` | `false` | `true` |

Kuncinya: endpoint `rekap` mengembalikan **bentuk yang identik dengan keluaran
`bangunGrup()`** — `{ grup: [{nama, total, kosong}], tugas, penempatan }`. Dari
sudut pandang komponen tak ada "mode server"; yang ada hanya prop `grup` yang
sudah terisi.

### Keputusan turunan

1. **Satu bentuk kartu.** Muatan paket Public Document diseragamkan ke bentuk
   PascalCase milik Functional Document (`Nama_Paket`, `Departemen[]`,
   `Persen`, …). Penyeragaman dikerjakan di service, sekali, bukan di layar
   berkali-kali. `KartuSop.vue` dicabut; `SopTugasCard` mendapat prop
   `rujukan`.

2. **`rujukan` sebagai prop tersendiri, bukan nilai ketiga pada `mode`.**
   `mode` sudah dipakai memilih gaya tombol admin; menumpuk dua sumbu makna
   pada satu prop membuat kombinasi "admin + rujukan" mustahil dinyatakan.

3. **`Total_Item` dan `Item_Tugas` dipisahkan.** Yang pertama isi paket, yang
   kedua berapa di antaranya ditugaskan kepada orang ini. Hanya yang kedua
   berpasangan dengan `Item_Selesai`. Menukarnya memberitahu penjelajah biasa
   bahwa ia "0 dari 5 selesai" atas pekerjaan yang tak pernah dibebankan
   kepadanya.

4. **Favorit & "intip" lewat slot, bukan prop.** Keduanya aksi milik Public
   Document. Sebagai prop, setiap pemanggil lain (Functional Document,
   Monitoring Karyawan) ikut memikirkan tombol yang tak pernah mereka pasang.

5. **Lensa rak jadi saringan server.** "Baru" dan "Rak Saya" dulu dua larik
   berisi maksimal delapan Id yang disaring di klien. Begitu keduanya memberi
   makan daftar berpaginasi dengan rail, larik itu membuat rail menghitung satu
   himpunan sementara daftar menampilkan himpunan lain.

6. **`LensaDokumen.vue` dicabut.** Tampilan "Per Dokumen" jadi salah satu dari
   tiga bentuk di dalam komponen bersama, bukan lensa terpisah.

## Yang dipindahkan

`RailSumbu`, `BarisTugasSaya`→`BarisPaket`, `BarisDokumenSaya`→`BarisDokumen`,
dan `sumbuTugas.js`→`sumbu.js` pindah dari `Employee/Saya/` ke
`Shared/components/listing/`. Ketiganya sudah dipakai lintas halaman (rail
bahkan dipakai SopBuilder); membiarkannya di folder salah satu halaman membuat
`Shared` bergantung pada `Employee` — arah ketergantungan yang terbalik.

`SopIdentitasDokumenService` lahir untuk menampung satu-satunya definisi
"departemen · jenis · APQC per paket", yang sebelumnya privat di
`SopProgressReadService` dan nyaris disalin ke `SopRepositoryReadService`.

## Cacat lama yang ikut tertutup

Tiga hal ini sudah rusak sebelum pekerjaan ini dan tak pernah menimbulkan galat:

- `KartuSop.vue` membaca lima medan yang API-nya tak pernah kirim
  (`cover_url`, `estimasi_menit`, `tipe_konten`, `progress_persen`, `nomor`),
  sehingga kartu Public Document selalu bersampul gradien, berkode "SOP-REF",
  bertipe "Dokumen", dan berprogres 0%.
- Frontend membaca `r.data.total_data` yang tak ada di respons paginasi,
  sehingga `daftarTotal` selalu jatuh ke panjang halaman dan **bilah paginasi
  tak pernah muncul**.
- Angka kartu metrik "Baru" dan "Rak Saya" diambil dari panjang larik yang
  dibatasi delapan — rak berisi dua puluh favorit tertulis "8".

## Anggaran query

Beranda perpustakaan punya pagar `≤ 12 query` (`SopAwarenessUpdateTest`).
Penambahan identitas dan tiga angka metrik sempat membawanya ke 14. Pagarnya
**tidak dinaikkan**; ketiga hitungan dilebur jadi satu agregat lewat tabel
turunan `LEFT JOIN` — SQL Server menolak subkueri di dalam fungsi agregat.
Predikat "baru" karena itu hidup dua kali (builder dan SQL mentah), dan
kesepakatan keduanya dijaga uji tersendiri.

## Cakupan yang TIDAK dikerjakan

- `apiCari` / `SopRepositoryReadService::cari()` dibiarkan hidup meski halaman
  tak lagi memanggilnya; pencarian kini mengalir lewat `daftarPaket` dengan
  predikat yang sama persis. Pencabutannya perubahan kontrak tersendiri.
- Bentuk "Dokumen" di Public Document tetap mengelompokkan di memori:
  katalognya memang tak berpaginasi.

## Bukti

- `SopRepositoryRekapSumbuTest` — 9 uji; penjaga utamanya (rekap dihitung atas
  himpunan, bukan halaman) sudah **dilihat MERAH** dengan sengaja.
- `SopListingDokumen.test.js` — 15 uji; penjaga "angka toolbar memakai total
  server" dan "chip tenggat tak bocor ke rak rujukan" juga sudah dilihat merah.
- `AwarenessUpdate.test.js` pindah dari `KartuSop` ke `SopTugasCard`; perilaku
  lencana "Baru N / Diperbarui N" ikut pindah, bukan ikut hilang.
