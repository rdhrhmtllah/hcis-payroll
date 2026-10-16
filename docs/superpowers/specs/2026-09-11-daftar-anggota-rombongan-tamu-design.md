# Desain: Daftar Anggota Rombongan pada Registrasi Tamu

Tanggal: 2026-09-11
Modul: `VISITOR`
Change ID DDL: `DDL-2026-100-visitor-daftar-anggota-tamu`

## Konteks & Masalah

Registrasi tamu hari ini mencatat **satu nama** (`Nama_Lengkap_Tamu`) dan **sebuah angka** (`Jumlah_Tamu`). Selebihnya tidak ada.

Ukurannya bukan kasus pinggiran. Dari 83 registrasi di `Web_HRIS` development:

| Jumlah orang | Registrasi |
|---|---|
| 1 | 21 |
| 2 | 18 |
| 3 | 16 |
| 4 | 12 |
| 5 | 16 |

**62 dari 83 (75%) membawa lebih dari satu orang.** Artinya mayoritas orang yang melewati gerbang saat ini tidak tercatat namanya di mana pun — satpam tahu "lima orang dari PT Anu", tetapi tidak tahu siapa saja. Kalau terjadi sesuatu di dalam area, daftar yang bisa dibuka tidak ada.

Fitur ini menutup lubang itu: mencatat siapa saja yang datang.

## Keputusan Desain

Disepakati bersama user; **bukan** untuk ditawar ulang saat implementasi.

| # | Keputusan | Alasan |
|---|-----------|--------|
| 1 | **Kolom JSON, bukan tabel anak.** | Di gerbang rombongan diperlakukan sebagai **satu kesatuan**: daftarnya manifes untuk dicocokkan mata, bukan catatan yang berubah per orang. Tidak ada keadaan per-orang (jam masuk/keluar sendiri-sendiri), dan justru keadaan per-orang itulah satu-satunya hal yang membuat JSON menyakitkan — memperbarui satu elemen berarti menulis ulang seluruh kolom, dan dua penulis bersamaan saling menimpa. Tanpa itu, tabel anak hanya menambah join dan satu tabel lagi yang harus ditarik aplikasi gerbang. |
| 2 | Per orang: **`nama` wajib, identitas opsional.** | Menuntut KTP seluruh rombongan saat mendaftar sering tidak mungkin — anggotanya baru diketahui di hari H. Kolom yang dipaksa terisi berakhir diisi asal, dan kolom yang diisi asal lebih buruk daripada kolom yang tidak ada. |
| 3 | Daftar memuat **semua orang, termasuk tamu utama.** | Satu tempat baca. Ada satu nama yang tertulis dua kali, tetapi itu jauh lebih murah daripada setiap pembaca (gerbang, papan harian, laporan, export) harus ingat menggabungkan `Nama_Tamu` dengan daftarnya — yang lupa tidak mendapat galat, ia hanya menampilkan satu orang lebih sedikit, dan justru orang yang paling bertanggung jawab. |
| 4 | **Wajib diisi** untuk registrasi baru. | Inilah yang membuat lubang 75% itu benar-benar tertutup, sekaligus membuat `Jumlah_Tamu` bisa diturunkan sehingga tidak pernah ada dua kebenaran. Bebannya nyata dan diterima sadar. |
| 5 | `Jumlah_Tamu` **diturunkan** dari panjang daftar, bukan diketik. | Angka yang diketik terpisah dari daftar pasti menyimpang. Kolomnya tetap **ditulis** supaya papan harian, dashboard, laporan, dan aplikasi gerbang yang membacanya tidak perlu berubah. |
| 6 | Data lama **tidak di-backfill**; `NULL` berarti "daftar tidak pernah dikumpulkan". | 83 baris lama punya `Jumlah_Tamu` sampai 5 tetapi hanya satu nama yang pernah tercatat. Mengarang daftar satu elemen membuat daftar dan jumlah saling bertentangan secara permanen — dan bertentangan diam-diam, karena tidak ada yang memeriksa. |
| 7 | Proyeksi **permit coklat tidak ikut membawa daftar.** | Permit adalah catatan izin akses/kendaraan; orangnya ada di baris tamu, dan keduanya sudah tertaut lewat `No_Transaksi`. Menyalin ke dua tempat hanya menambah yang bisa menyimpang. |

## Lingkup

**Termasuk:** kolom JSON di transaksi dan proyeksi tamu, validasi dan batas, penurunan `Jumlah_Tamu`, isian daftar di formulir registrasi, dan penampilannya di detail riwayat tamu serta modal review verifikator.

**Tidak termasuk:** API B2B permit mitra — mitra mengajukan **permit**, bukan registrasi tamu, dan permit tetap mencatat satu pemegang. Rombongan lewat API adalah permintaan terpisah bila kelak muncul. Juga tidak termasuk: pencatatan masuk/keluar per orang, dan dimensi export untuk daftar ini.

## Model Data

Satu change: `database/ddl/DDL-2026-100-visitor-daftar-anggota-tamu/{forward,drop}.sql`, entri di `database/ddl/status.json`. Tidak ada DML — tidak ada backfill (keputusan #6).

Mengikuti konvensi JSON yang sudah ada di repo ini (`N_AMS_Field_Privacy_Rules.*_Json`, `Report_Template.DefinisiJson`): kolom bernama `*_Json`, bertipe `nvarchar(max)`, dijaga CHECK `ISJSON` bernama `CK_<tabel>_<kolom>`.

| Tabel | Kolom baru | Tipe |
|---|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | `Daftar_Anggota_Json` | `nvarchar(max) NULL` + `CK_..._Daftar_Anggota_Json` |
| `N_VISITOR_Tamu_Efektif` | `Daftar_Anggota_Json` | `nvarchar(max) NULL` + `CK_..._Daftar_Anggota_Json` |

Bentuk CHECK-nya: `(Daftar_Anggota_Json IS NULL OR ISJSON(Daftar_Anggota_Json) = 1)`. Ia menjaga agar kolomnya tidak pernah berisi teks yang bukan JSON — penjaga murah yang mencegah pembaca meledak jauh di hilir.

### Bentuk isinya

```json
[
  { "nama": "Rudi Hartono", "jenis_identitas": "KTP", "no_identitas": "3201234567890001" },
  { "nama": "Siti Aminah" }
]
```

- `nama` — wajib, maksimal 150 karakter, tidak boleh hanya spasi.
- `jenis_identitas`, `no_identitas` — opsional; boleh tidak ada sama sekali, bukan `null` kosong yang menyesatkan.
- Elemen pertama adalah tamu utama, sama dengan `Nama_Lengkap_Tamu`.

### Kolom yang berubah maknanya

`Jumlah_Tamu` berhenti menjadi isian pengguna dan menjadi nilai turunan `count(daftar)`. Tipe dan pembacanya tidak berubah sama sekali.

`Nama_Lengkap_Tamu` / `Nama_Tamu` tetap berarti **penanggung jawab atau kontak rombongan**, dan namanya ikut muncul sebagai elemen pertama daftar.

## Aturan dan Batas

Hidup di satu service, bukan tersebar sebagai aturan validasi lepas:

- `MAKS_ANGGOTA_ROMBONGAN = 50`. Data nyata maksimal 5, jadi 50 longgar — tetapi tanpa batas, satu permintaan bisa menempelkan ribuan nama ke kolom yang harus di-parse aplikasi gerbang pada setiap penarikan.
- Minimal satu anggota. Registrasi tanpa orang tidak berarti apa-apa.
- `nama` di-trim sebelum disimpan; entri yang namanya kosong sesudah di-trim ditolak, bukan dibuang diam-diam — pengguna berhak tahu barisnya tidak terpakai.

## Alur

1. **Formulir registrasi** mengirim `daftar_anggota` sebagai array. "Nama tamu" yang sudah ada tetap diisi sebagai penanggung jawab; backend menyusun daftar lengkap `[utama, …lainnya]`.
2. **`GuestRegistrationController::store()`** memvalidasi, menyusun daftarnya, menulis `Daftar_Anggota_Json`, dan menulis `Jumlah_Tamu` dari panjang daftar itu — bukan dari input.
3. **`GuestRegistrationController::update()`** memperlakukan perubahan daftar sama seperti perubahan jendela: menulis ulang daftar, menurunkan ulang jumlah, lalu menyinkronkan proyeksi.
4. **`VisitorEfektifService::syncTamuEfektif()`** menyalin `Daftar_Anggota_Json` apa adanya ke proyeksi.
5. **`baseTamuQuery()`** membawa kolomnya supaya pembaca internal tidak perlu kueri kedua.

## Tampilan

- **Formulir** (`TamuForm.vue`): daftar anggota yang bisa ditambah dan dikurangi, tiap baris berisi nama plus dua isian identitas opsional. Kolom "Jumlah orang" berubah dari isian menjadi **angka turunan yang hanya ditampilkan**.
- **Detail riwayat tamu** (`TamuHistory.vue`): daftar nama, supaya PIC dapat memeriksa kembali siapa yang ia daftarkan.
- **Modal review verifikator** (`TamuReviewModal.vue`): daftar nama. Approver perlu tahu **siapa** yang akan masuk sebelum menyetujui, bukan hanya berapa orang.
- **Papan harian dan dashboard**: tidak berubah; keduanya membaca `Jumlah_Tamu` yang nilainya tetap benar.

## Pengujian

Yang diuji bukan "kolomnya terisi", melainkan hal yang kalau salah membuat orang tidak tercatat atau angka berbohong.

| # | Uji | Membuktikan |
|---|---|---|
| 1 | Daftar tersimpan apa adanya, termasuk entri tanpa identitas | bentuk JSON dan sifat opsionalnya |
| 2 | `Jumlah_Tamu` sama dengan panjang daftar, walau formulir mengirim angka lain | keputusan #5 — daftar yang menang, bukan isian |
| 3 | Elemen pertama adalah tamu utama | keputusan #3 |
| 4 | Daftar kosong, nama kosong/spasi, dan melebihi 50 ditolak `422` | aturan dan batas |
| 5 | Daftar sampai ke proyeksi tamu lewat `syncTamuEfektif()` | gerbang benar-benar menerimanya |
| 6 | `baseTamuQuery()` membawa kolomnya | pembaca internal tidak perlu kueri kedua |
| 7 | Mengubah daftar lewat `update()` memperbarui proyeksi **dan** jumlahnya | lubang yang sama dengan jendela jam dulu |
| 8 | Registrasi lama (`Daftar_Anggota_Json` `NULL`) tetap terbaca, jumlahnya tidak berubah | keputusan #6 — data lama tidak rusak |
| 9 | Teks yang bukan JSON ditolak database | CHECK `ISJSON` benar-benar menjaga |

Penjaga baru menyentuh `baseTamuQuery()` dan `syncTamuEfektif()` yang dipakai banyak layar, jadi verifikasi akhir menjalankan suite modul Visitor penuh.

## Risiko yang Perlu Keputusan Manusia

- **Aplikasi gerbang harus mem-parse JSON** untuk menampilkan manifesnya. Kolomnya aditif dan nullable, jadi gerbang yang belum diperbarui tetap berjalan seperti sekarang — tetapi manifesnya tidak akan terlihat sampai mereka mengadopsinya. Perlu dikabarkan, satu paket dengan kolom-kolom permit yang sudah menunggu konfirmasi mereka.
- **Beban pengisian bertambah nyata.** PIC yang biasa mengetik "5" sekarang mengetik lima nama. Kalau di lapangan ini ditolak pengguna, keputusan #4 yang perlu ditinjau ulang — bukan aturannya diakali dengan membiarkan nama kosong lolos.
