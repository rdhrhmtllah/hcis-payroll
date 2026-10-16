# Dashboard Admin Visitor — Desain

Tanggal: 2026-08-24
Modul: `VISITOR` · Submodul: `Workflow` · Fitur: `Reporting`
Halaman induk: `laporanvisitor` (Laporan & Dashboard Visitor)

## 1. Masalah

Halaman Laporan & Dashboard Visitor punya dua tab: **Aktivitas harian** (tabel
mentah) dan **Export Studio** (perakit laporan). Keduanya menjawab pertanyaan
"apa yang terjadi", tidak satu pun menjawab "apa yang harus saya kerjakan
sekarang". Admin yang memantau pos jaga harus membuka beberapa halaman dan
menyaring sendiri untuk tahu siapa masih di dalam area, siapa lupa check-out,
dan pengajuan mana yang menyumbat.

Tab baru **Dashboard** mengisi celah itu.

## 2. Keputusan yang sudah diambil

| Keputusan | Pilihan |
|---|---|
| Fokus utama | Operasional harian — keadaan sekarang di atas, tren jadi pendukung |
| Sifat | Pantau + jalan pintas. **Tidak ada endpoint tulis baru**; tiap kartu menautkan ke halaman yang sudah berwenang |
| Kesegaran | Cache pendek + tombol segarkan manual, dengan penanda "data per HH:MM" |
| Definisi overdue | Sudah check-in, belum check-out, dan `Tanggal_Kunjungan < hari ini` |

Keputusan overdue diambil karena `Waktu_Estimasi_Kunjungan` tidak bisa dipakai —
lihat §3.

## 3. Temuan sumber data yang membentuk desain

Tiga temuan ini diverifikasi langsung ke DB dev (`Web_HRIS`) pada 2026-08-24 dan
masing-masing membatalkan satu asumsi yang wajar.

### 3.1 Tabel `*_Efektif` bukan view, dan basi untuk lifecycle

`N_VISITOR_Tamu_Efektif` dan `N_VISITOR_Permit_Efektif` adalah `BASE TABLE`
(dicek lewat `INFORMATION_SCHEMA.TABLES`; `sys.sql_modules` tidak punya
definisinya). Isinya ditulis sekali oleh `VisitorEfektifService::syncTamuEfektif()`
saat approval selesai.

Check-in dan check-out (`GuestRegistrationController::checkIn/checkOut`,
baris 467-495) menulis:

- `Status_Kunjungan` → tabel **header** `N_EMI_VISITOR_Transaksi_Tamu`
- `Waktu_Aktual_Masuk` / `Waktu_Aktual_Keluar` → tabel **detail**
  `N_EMI_VISITOR_Transaksi_Tamu_Detail`

dan **tidak pernah** menyentuh `N_VISITOR_Tamu_Efektif`. Keadaan DB dev
mengonfirmasi: kedua baris efektif berstatus `AKTIF` dengan waktu aktual `NULL`.

Konsekuensi:

- Blok operasional **wajib** membaca header + detail. Membacanya dari tabel
  efektif menghasilkan nol selamanya, bukan error — kegagalan senyap.
- Bug yang sudah ada: `LaporanVisitorController::getLaporanTamu()` menghitung
  `stats.sudah_keluar` dan `stats.expired` dari
  `N_VISITOR_Tamu_Efektif.Status_Efektif`, sehingga **kedua angka itu selalu 0**.
  Diperbaiki sebagai bagian pekerjaan ini (§7.6) karena satu tab di halaman yang
  sama akan menampilkan angka yang bertentangan kalau dibiarkan.
- Dataset Export Studio tamu juga hanya membaca tabel efektif, jadi kolom status
  dan waktu aktual di sana punya cacat yang sama. **Di luar lingkup** pekerjaan
  ini, dicatat di §10.

### 3.2 `Waktu_Estimasi_Kunjungan` teks bebas

Tipe kolomnya `varchar(50)`. Nilai nyata di dev: `"1 - 2 Jam"` dan `"2-4 jam"` —
beda format, dan maknanya *durasi*, bukan jam pulang. Kolom ini tidak boleh
dijadikan jam jatuh tempo. Definisi overdue memakai `Tanggal_Kunjungan` (`date`)
dan `Waktu_Aktual_Keluar` (`datetime2`) saja.

### 3.3 Permit tidak punya check-in/check-out

`N_VISITOR_Permit_Efektif` tidak punya kolom `Waktu_Aktual_*` sama sekali, dan
tabel transaksi permit juga tidak. Permit adalah izin masa berlaku, bukan
kunjungan.

Konsekuensi: headcount "di dalam area" **murni tamu**. Permit tidak boleh ikut
dijumlahkan ke angka itu; permit punya bloknya sendiri (masa berlaku).

### 3.4 Rombongan itu nyata

`Jumlah_Tamu` ada di tabel detail dan sudah terisi > 1 di dev (`VSR0826-0003`
berisi 5 orang dari 5 transaksi). Detail bersifat 1:1 terhadap header
(diverifikasi: semua transaksi punya tepat satu baris detail).

Konsekuensi: **jumlah orang ≠ jumlah transaksi**. Kartu headcount menampilkan
orang sebagai angka utama dan transaksi sebagai angka pendamping. Keduanya wajib
ada; menampilkan salah satu saja membuat admin salah baca kapasitas.

### 3.5 Approver visitor adalah kolam peran, bukan orang

Di `HRIS_Approval_Request`, semua baris visitor punya `Approver_Karyawan_Kode`
`NULL`, `Flag_Group = 'Y'`, dan `Group_Reference_Id` berisi `Id_Role`
(`N_HRIS_Role_Functional`). Nama approver untuk antrian pending harus diambil
lewat `LEFT JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ar.Group_Reference_Id`
— pola yang sudah dipakai `VisitorPermitQueryService::getApprovals()`.

Setelah diputus, `Approved_By_Kode` dan `Tanggal_Approval` baru terisi.

### 3.6 Volume data dev praktis nol

5 transaksi tamu (3 `MENUNGGU_APPROVAL`, 2 `TERJADWAL`), 9 transaksi permit,
2 baris tamu efektif, 4 baris permit efektif. Rentang tanggal 31 Jul – 24 Agu 2026.

Konsekuensi: **keadaan kosong adalah tampilan yang paling sering terlihat selama
pengembangan**, dan kepadatan visual tidak bisa dinilai dari dev. Keadaan kosong
dirancang per kartu, bukan disisakan sebagai grafik bolong (§8).

## 4. Sumber kebenaran per angka

| Angka | Sumber | Alasan |
|---|---|---|
| Di dalam area, overdue, belum datang | header `N_EMI_VISITOR_Transaksi_Tamu` + detail | satu-satunya tempat check-in/out ditulis (§3.1) |
| Menunggu approval (tamu) | header `Status_Kunjungan` + `HRIS_Approval_Request` | pending tidak ada di tabel efektif |
| Menunggu approval (permit) | `VisitorPermitQueryService::permitStatusSql()` | kosakata status permit sudah lengkap di sana, jangan ditulis ulang |
| Tren kunjungan, jam sibuk | header + detail | butuh waktu aktual yang benar |
| Permit aktif & akan kadaluarsa | `N_VISITOR_Permit_Efektif` | tanggal berlaku memang ditulis ke sini dan tidak berubah setelahnya |
| Kesehatan data | header + detail | mengukur keterisian kolom sumber, bukan salinannya |

Aturan tetap: **logika status tidak ditulis ulang.** Status permit memakai
`permitStatusSql()`; status approval memakai kosakata yang sama dengan
`VisitorApprovalPolicyService::approvalStatus()`
(`PENDING` / `APPROVED` / `REJECTED` / `EXPIRED` / `NO_FLOW`).

## 5. Isi dashboard

Tab baru berkunci `dashboard`, **ditempatkan sebagai tab pertama**, sebelum
`harian` dan `studio`.

### Baris 1 — Keadaan sekarang (4 kartu, semua bisa diklik)

| Kartu | Angka utama | Pendamping | Definisi | Menuju |
|---|---|---|---|---|
| Di dalam area | jumlah **orang** | jumlah transaksi | `Status_Kunjungan='CHECKED_IN'` DAN `d.Waktu_Aktual_Keluar IS NULL` | Verifikator |
| Perlu ditutup | jumlah transaksi | tertua sejak kapan | seperti di atas DAN `h.Tanggal_Kunjungan < CAST(GETDATE() AS date)` | Verifikator |
| Terjadwal belum datang | jumlah transaksi | jumlah orang | `Status_Kunjungan='TERJADWAL'` DAN `h.Tanggal_Kunjungan = hari ini` DAN `d.Waktu_Aktual_Masuk IS NULL` | Aktivitas harian |
| Menunggu approval | total pending (tamu + permit) | pecahan umur `<1j / 1-4j / >4j` | lihat §5.1 | Approval |

Kartu "Menunggu approval" membawa keterangan tambahan bila ada pengajuan yang
lewat `Expired_At` tanpa keputusan — itu bukan pending, itu proses macet, dan
dihitung terpisah supaya tidak menyamar jadi antrian.

### 5.1 Definisi antrian approval

Sebuah transaksi berstatus **PENDING** bila ada baris `HRIS_Approval_Request`
dengan `Flag_Approval IS NULL` yang belum lewat `Expired_At`, dan tidak ada baris
`Flag_Approval='T'`. Ini persis aturan `VisitorApprovalPolicyService::approvalStatus()`
dan `permitStatusSql()`.

- Umur antrian = selisih menit dari `MIN(created_at)` per `No_Transaksi` sampai
  sekarang — yaitu sejak pengajuan masuk antrian, bukan sejak langkah terakhir.
- Approver ditampilkan = nama peran dari `N_HRIS_Role_Functional` bila
  `Flag_Group='Y'`; nama karyawan bila `Approver_Karyawan_Kode` terisi; `-` bila
  keduanya kosong. Tidak pernah menebak.
- **KADALUARSA** = `Flag_Approval IS NULL` DAN `Expired_At < sekarang`. Dihitung
  terpisah, tidak masuk pending.

### Baris 2 — Dua daftar tindakan berdampingan

Bukan angka, tapi daftar pendek yang bisa langsung dikerjakan. Masing-masing
maksimal 8 baris, dengan tautan "lihat semua" ke halaman berwenang.

- **Perlu ditutup** — nama tamu, instansi, PIC, masuk sejak jam berapa, sudah
  berapa lama di dalam. Urut dari yang paling lama.
- **Menunggu keputusan** — nomor transaksi, jenis (Tamu/Permit), pemohon, umur
  antrian, approver. Urut dari yang paling tua.

Bila daftar dipotong, jumlah yang tidak ditampilkan **disebutkan** ("8 dari 23").
Pembatasan yang tidak dilaporkan terbaca sebagai "cuma segini".

### Baris 3 — Irama hari & minggu (ikut rentang)

- **Tren kunjungan** — garis, dua seri terpisah: jumlah transaksi dan jumlah
  orang. Dua garis, bukan satu, karena rombongan membuat keduanya jauh berbeda.
- **Sebaran jam kedatangan** — heatmap jam × hari dari `Waktu_Aktual_Masuk`,
  untuk menentukan jam sibuk pos jaga.

### Baris 4 — Permit & masa berlaku (ikut rentang)

- **Permit aktif per kategori** — batang mendatar dari `N_VISITOR_Permit_Efektif`
  yang `Status_Efektif='AKTIF'` dan belum lewat `Tanggal_Kadaluarsa`.
- **Akan kadaluarsa ≤30 hari** — daftar pendek: plat, kategori, pemohon,
  departemen, sisa hari. Ini daftar perpanjangan, bukan angka mati.

### Baris 5 — Kesehatan data (satu kartu tipis, ikut rentang)

Persentase transaksi tamu pada rentang terpilih yang: tanpa `Waktu_Aktual_Keluar`
padahal sudah check-in, tanpa `No_Identitas`, tanpa `Nomor_Handphone`.

Kartu ini juga jadi rem: bila "tanpa waktu keluar" tinggi, baris 3 membawa
penanda bahwa angkanya belum layak dipercaya (§8).

## 6. Arsitektur

### 6.1 Dua endpoint, dipisah menurut sifat kesegaran

```
GET /api/v1/visitor/dashboard/sekarang            → baris 1 & 2
GET /api/v1/visitor/dashboard/analitik?dari=&sampai=  → baris 3, 4, 5
```

- `sekarang` ringan, cache **60 detik**, satu kunci global. Inilah yang ditarik
  ulang tombol segarkan.
- `analitik` berat, cache **10 menit** berkunci rentang. Data kemarin tidak
  berubah tiap menit; menghitungnya ulang tiap 60 detik adalah pemborosan yang
  pernah membuat CPU database mentok.

Dua permintaan per muat halaman. Menyegarkan "sekarang" tidak menyentuh analitik.

Balasan membawa `dihitung_pada` (ISO 8601) supaya UI bisa menampilkan
"data per HH:MM" — angka tanpa stempel waktu mengundang salah baca.

### 6.2 Wewenang

Mengikuti pintu yang sudah ada: middleware `['auth', 'check.jabatan:visitorLaporanPage']`,
sama persis dengan Export Studio dan Laporan. **Tidak ada lapisan izin baru dan
tidak ada baris menu baru** — ini tab di halaman yang sudah terdaftar, bukan
halaman baru.

Catatan: `VisitorExportStudioController::lingkup()` memberi `adminPenuh = true`
kepada siapa pun yang lolos pintu itu. Dashboard bersikap sama demi konsistensi;
menambahkan penyaringan per divisi di sini saja akan membuat dua tab pada satu
halaman menampilkan populasi berbeda.

### 6.3 Berkas

```
routes/VISITOR/Workflow/Reporting/VisitorDashboardApi.php
app/Http/Controllers/VISITOR/Workflow/Reporting/VisitorDashboardController.php
app/Services/VISITOR/Workflow/Reporting/Dashboard/
    VisitorDashboardOperasionalService.php    ← baris 1-2, cache 60 dtk
    VisitorDashboardAnalitikService.php       ← baris 3-5, cache 10 mnt
resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/
    api/visitorDashboardApi.js
    components/VisitorDashboardPanel.vue      ← tata letak, rentang, penanda waktu
    components/dashboard/
        DashboardKartuKeadaan.vue
        DashboardDaftarTindakan.vue
        DashboardBlokTren.vue
        DashboardBlokPermit.vue
        DashboardKesehatanData.vue
```

Dua layanan dipisah karena umur cache-nya beda dan supaya tidak ada satu berkas
yang memuat semua kueri.

Berkas yang disunting:

- `resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/laporanVisitor.vue`
  — tambah tab + panel.
- `routes/web.php` — `require` berkas rute baru.
- `app/Http/Controllers/VISITOR/Workflow/Reporting/LaporanVisitorController.php`
  — perbaikan `stats` (§7.6).

### 6.4 Komponen UI

Memakai kit `@/components/ui`: `HcisStatCard` / `HcisStatGroup` (kartu keadaan),
`HcisChart` (`line`, `bar-horizontal`, `heatmap` — semuanya sudah didukung),
`HcisCard`, `HcisEmptyState`, `HcisSkeleton`, `HcisBadge`, `HcisButton`,
`HcisDateRangePicker`. **Tidak ada pustaka grafik baru** — `apexcharts` dan
`echarts` sudah terpasang dan dibungkus `HcisChart`.

Gaya mengikuti tab tetangga di halaman yang sama supaya satu halaman tidak terasa
dijahit dari dua bahasa visual.

## 7. Rincian kueri

Semua kueri **sargable**: tidak ada `CAST()` atau fungsi pada kolom tanggal di
sisi kiri perbandingan. Perbandingan ke hari ini memakai `CAST(GETDATE() AS date)`
di sisi kanan.

### 7.1 Di dalam area

```sql
SELECT COUNT(*) AS transaksi, SUM(ISNULL(d.Jumlah_Tamu, 1)) AS orang
FROM N_EMI_VISITOR_Transaksi_Tamu h
JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail d ON d.No_Transaksi = h.No_Transaksi
WHERE h.Flag_Aktif = 'Y'
  AND h.Status_Kunjungan = 'CHECKED_IN'
  AND d.Waktu_Aktual_Keluar IS NULL
```

### 7.2 Perlu ditutup

Sama dengan 7.1 ditambah `AND h.Tanggal_Kunjungan < CAST(GETDATE() AS date)`.

### 7.3 Terjadwal belum datang

```sql
WHERE h.Flag_Aktif = 'Y'
  AND h.Status_Kunjungan = 'TERJADWAL'
  AND h.Tanggal_Kunjungan = CAST(GETDATE() AS date)
  AND d.Waktu_Aktual_Masuk IS NULL
```

### 7.4 Antrian approval

Satu kueri berbasis himpunan atas `HRIS_Approval_Request` yang dibatasi ke
`No_Transaksi` milik visitor (awalan `VSR`/`PMT` **tidak** dipakai sebagai
penyaring — penyaringan lewat `EXISTS` ke tabel transaksi tamu/permit, karena
awalan nomor adalah kesepakatan penamaan yang bisa berubah, bukan kunci relasi).

Agregasi per `No_Transaksi`: `MIN(created_at)` untuk umur, keberadaan `'T'` untuk
ditolak, keberadaan `NULL` belum kedaluwarsa untuk pending.

### 7.5 Tren & heatmap

`GROUP BY` atas `h.Tanggal_Kunjungan` (tren) dan
`DATEPART(hour, d.Waktu_Aktual_Masuk)` × `DATEPART(weekday, ...)` (heatmap).
Satu kueri per blok — tidak ada perulangan per tanggal.

### 7.6 Perbaikan `getLaporanTamu().stats`

`sudah_keluar` dan `expired` dipindah dari `N_VISITOR_Tamu_Efektif.Status_Efektif`
ke header + detail, mengikuti §4. Tanpa ini, tab Dashboard dan tab lama akan
menampilkan angka yang saling bertentangan pada halaman yang sama.

## 8. Keadaan kosong dan penanda data tipis

- Setiap kartu angka punya bentuk kosongnya sendiri: angka `0` dengan keterangan
  yang menjelaskan artinya ("tidak ada tamu di dalam area"), bukan strip kosong.
- Setiap daftar tindakan memakai `HcisEmptyState` dengan kalimat yang menyatakan
  keadaan baik ("semua tamu sudah check-out"), bukan "tidak ada data".
- Setiap grafik memakai skeleton peniru bentuk saat memuat, dan `HcisEmptyState`
  saat rentangnya memang kosong.
- Bila kesehatan data menunjukkan "tanpa waktu keluar" melebihi 30% pada rentang
  terpilih, blok tren dan heatmap membawa lencana peringatan bahwa angkanya
  belum layak dipercaya. **Kartunya tetap ditampilkan** — menyembunyikannya
  membuat masalah datanya ikut tersembunyi.

## 9. Pengujian

**Uji fitur (PHP)** — `tests/Feature/VISITOR/Workflow/Reporting/VisitorDashboardTest.php`

1. Tanpa autentikasi → dialihkan / 401.
2. Autentikasi tanpa hak `visitorLaporanPage` → ditolak.
3. `sekarang` mengembalikan 200 dengan seluruh kunci blok walau DB kosong.
4. `analitik` dengan rentang tanpa data → 200, blok kosong, bukan error.
5. **Rombongan**: satu transaksi `Jumlah_Tamu = 5` yang check-in →
   `orang = 5`, `transaksi = 1`. Penjaga ini wajib pernah terlihat MERAH.
6. **Overdue**: transaksi check-in bertanggal kemarin masuk hitungan; yang
   bertanggal hari ini tidak.
7. **Sumber lifecycle**: transaksi yang sudah check-in tetapi baris efektifnya
   masih `AKTIF` dengan waktu aktual `NULL` **tetap** terhitung "di dalam area".
   Inilah penjaga langsung atas §3.1 — kalau nanti ada yang memindahkan kueri ke
   tabel efektif, uji ini merah.
8. **Approval kedaluwarsa** tidak terhitung pending, terhitung di angka macet.
9. `dihitung_pada` ada di kedua balasan.

**Uji unit (JS)** — komponen panel: keadaan memuat, keadaan kosong, penanda data
tipis muncul saat ambang terlampaui, dan pemotongan daftar melaporkan sisanya.

Sebelum selesai: jalankan **suite penuh**, bukan hanya berkas baru. Perbaikan
`getLaporanTamu()` menyentuh kode yang sudah ada dan bisa mematahkan uji lama.

## 10. Di luar lingkup

- **Tidak** ada aksi tulis dari dashboard (check-out paksa, ingatkan PIC,
  batalkan). Semua tautan menuju halaman yang sudah berwenang.
- **Tidak** mengubah `Waktu_Estimasi_Kunjungan` jadi pilihan terkontrol. Itu
  perbaikan sumber data yang layak dikerjakan sendiri, dan tidak menolong data
  lama.
- **Tidak** memperbaiki dataset Export Studio tamu yang membaca kolom lifecycle
  dari tabel efektif (§3.1). Dicatat sebagai utang; memperbaikinya berarti
  mengubah kontrak dataset dan template yang sudah tersimpan.
- **Tidak** menyalakan penyegaran otomatis. Tombol segarkan manual dulu; kalau
  nanti terbukti perlu, cache bersama sudah siap menanggungnya.
