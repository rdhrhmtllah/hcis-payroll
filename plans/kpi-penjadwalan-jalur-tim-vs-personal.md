# Penjadwalan KPI — Pemisahan Jalur Tim vs Personal

Status: **Lapis 1 sudah diterapkan** — commit `69683631`. Lapis 2 (kolom
`Flag_Team` pada master penjadwalan) belum, dan mungkin tidak perlu; lihat
bagian 5.
Tanggal: 2026-07-29. Diukur terhadap DB dev (`emi_db_web_hr`) setelah reseed.

Koreksi lingkup Lapis 1 saat dikerjakan: bagian 3a semula menyebut "sesuai jalur
yang dipilih admin", padahal pilihan jalur baru ada di Lapis 2. Yang diterapkan
adalah menampilkan status **kedua** jalur sekaligus dan mengunci hanya bila tidak
ada jalur yang menyisakan pekerjaan — tidak butuh selector maupun kolom baru.

Satu perilaku yang sengaja dipertahankan: karyawan yang pengajuannya ditolak
**tetap** terkunci bila jadwal penilaian peroranganya untuk periode itu masih
terbuka, supaya tidak dibuatkan jadwal ganda. Yang berubah adalah alasannya —
"Sudah Dijadwalkan" dengan rincian "Tim: Perlu Perbaikan", bukan lagi "Selesai"
yang membuat admin mengira tidak ada pekerjaan.

## 1. Masalah

Penjadwalan KPI hanya mengenal pasangan **(orang, periode)**. Padahal proses KPI
punya dua jalur independen untuk orang dan periode yang sama:

| Jalur | Parameter dibuat oleh | Realisasi diisi oleh |
|---|---|---|
| Personal | karyawan sendiri | karyawan sendiri |
| Tim | leader, untuk anggotanya | leader (atau anggota lewat halaman individu) |

Karena jadwal tidak punya dimensi jalur, admin tidak bisa membuka jendela untuk
salah satu jalur saja, dan layar pemilihan peserta mengunci orang berdasarkan
jalur yang keliru.

Bukti struktur (kolom tabel per 2026-07-29):

```
KPI_Master_Penjadwalan:
  Id_Master_Penjadwalan, Nama_Kegiatan, Deskripsi, Jadwal_Mulai, Jadwal_Akhir,
  Tahun, Jam, Tanggal, Id_User, Flag_Form_KPI, Flag_Update_KPI,
  Flag_Penilaian_KPI, Flag_Batas_Verifikasi, Flag_Aktif, Periode, Flag_Perorangan
  -> penanda tim/personal: TIDAK ADA

N_KPI_Master_Penjadwalan_Perorangan:
  Id_Master_Penjadwalan_Perorangan, Id_Master_Penjadwalan, Kode_Karyawan,
  Keterangan, Start_Periode, Flag_Aktif
  -> penanda tim/personal: TIDAK ADA
```

Gejala yang sudah terjadi di data dev (periode 2026-06):

```
TERKUNCI: Aisyah  (realisasi disetujui)
TERKUNCI: Ridho   (realisasi disetujui)
TERKUNCI: Tari    (realisasi DITOLAK)   <-- salah
```

Tari ditolak dan justru butuh jendela dibuka lagi, tetapi layar penjadwalan
menandainya "Selesai" dan menguncinya.

## 2. Prinsip yang dipegang

**Jalur adalah properti PENGAJUAN, bukan properti REALISASI.**

Alasannya keputusan yang sudah diambil sebelumnya: anggota boleh mengisi
parameter jalur tim lewat halaman individu **tanpa** dicap `Flag_Team`. Jadi
`KPI_Update_Performance.Flag_Team` tidak dapat dipercaya sebagai penanda jalur.

Data mendukung — dari 18 baris realisasi hidup, `Flag_Team` bernilai tiga macam:

```
Flag_Team=Y    : 1
Flag_Team=T    : 13
Flag_Team=NULL : 4
```

Tri-state ini juga berarti `Flag_Team <> 'Y'` dan `Flag_Team = 'T'` memberi hasil
berbeda untuk baris NULL — jangan pakai bergantian.

Patokan yang benar: **`KPI_Pengajuan.Flag_Team`**. Siapa pun yang mengisi,
jalurnya ditentukan oleh set parameter yang diisi.

`buildToUpdatePairs` (`UpdatePerformanceController.php:1527`) sudah memakai
patokan ini lewat `whereIn('Id_Pengajuan', $teamPengajuanIds)`. Jadi daftar tim
leader sudah benar; **yang tertinggal hanya layar penjadwalan.**

## 3. Lapis 1 — perbaiki definisi "selesai" (tanpa ubah skema)

Semua di `PenjadwalanKpiController::getDataKaryawan`.

### 3a. Ikat realisasi ke pengajuan jalur yang sedang dijadwalkan

Sekarang (baris 147-157) join menyambung ke **sembarang** pengajuan hidup milik
orang itu, tanpa memandang jalur dan tanpa memandang tahun:

```php
->join('KPI_Pengajuan as kp', function ($join) {
    $join->on('k.UserID_Web', '=', 'kp.Id_User')
        ->where('kp.Flag_Aktif', 'Y');
})
```

Yang diinginkan: batasi ke pengajuan dengan `Flag_Team` sesuai jalur yang dipilih
admin, dan ke tahun jadwal yang sedang disusun. Ikuti pola `buildToUpdatePairs`:
join `KPI_Master_Penjadwalan` lewat `kp.Id_Penjadwalan` lalu saring `mp.Tahun`.

Catatan NULL: untuk jalur personal, syaratnya
`(kp.Flag_Team IS NULL OR kp.Flag_Team <> 'Y')` — bukan `= 'T'` — karena 4 baris
realisasi dan sebagian pengajuan lama bernilai NULL.

### 3b. Hormati `Status_Pengajuan`

Sekarang leftJoin realisasi hanya menyaring `Flag_Aktif` dan `Status`
(baris 151-157), sehingga baris **ditolak** ikut terhitung "sudah isi".

Status jadi empat, bukan tiga:

| Kondisi | Label | Dikunci? |
|---|---|---|
| realisasi hidup, `disetujui` | Selesai | ya |
| realisasi hidup, `menunggu` | Sedang diproses | ya |
| realisasi hidup, `ditolak` | Perlu perbaikan | **tidak** |
| ada jadwal perorangan aktif | Sudah Dijadwalkan | ya |
| selain itu | Belum Dijadwalkan | tidak |

Frontend: `isKaryawanLocked` (`masterPenjadwalan.vue:1857`) sekarang berbunyi
`is_filled || is_scheduled_admin`. Ganti agar memakai flag baru dari backend
(mis. `is_locked`) supaya aturan kunci hidup di satu tempat saja, bukan
diduplikasi di dua sisi.

### 3c. Batasi tahun pada join pengajuan

Tanpa filter tahun, pengajuan 2025 ikut terjaring dan hasil dedup
`groupBy('kode_karyawan')` (baris 268-274) bisa memilih baris tahun yang salah —
`no_kpi` dan `status_approval` yang tampil jadi milik tahun lain.

### Dampak Lapis 1

Menyelesaikan keluhan utama: orang yang sudah mengisi jalur personal tidak lagi
terkunci dari penugasan jalur tim, dan yang ditolak bisa dibukakan lagi.
Tidak ada perubahan skema, tidak ada migrasi data.

## 4. Lapis 2 — kolom penanda jalur di master penjadwalan

### DDL

```sql
ALTER TABLE KPI_Master_Penjadwalan
    ADD Flag_Team CHAR(1) NULL;

-- Sengaja TANPA backfill: NULL = berlaku untuk dua jalur, sehingga
-- seluruh baris jadwal lama berperilaku persis seperti sekarang.
```

Semantik:

| Nilai | Arti |
|---|---|
| `NULL` | berlaku untuk dua jalur (default, kompatibel mundur) |
| `'T'` | jadwal untuk jalur personal saja |
| `'Y'` | jadwal untuk jalur tim saja |

### Kenapa di tabel master, bukan di tabel peserta

Jadwal global (`Flag_Perorangan = 'T'`) tidak punya baris peserta sama sekali.
Kalau penandanya ditaruh di `N_KPI_Master_Penjadwalan_Perorangan`, jadwal global
tetap tidak bisa dibedakan jalurnya. Kalau kelak butuh granularitas per peserta,
kolom tabel peserta bisa ditambahkan belakangan sebagai penimpa (override) —
master tetap jadi default.

### Titik ubah

| Berkas | Lokasi | Perubahan |
|---|---|---|
| `PenjadwalanKpiController.php` | `store()` ~b.533 | tulis `Flag_Team` dari request |
| `PenjadwalanKpiController.php` | `update()` ~b.658 | idem |
| `PenjadwalanKpiController.php` | `getDataCurrent()` | kirim `Flag_Team` ke daftar |
| `PenjadwalanKpiController.php` | `getDataKaryawan()` | status peserta mengikuti jalur terpilih |
| `UpdatePerformanceController.php` | `buildToUpdatePairs()` b.1485 & b.1500 | saring jadwal: `Flag_Team IS NULL OR Flag_Team = 'Y'` |
| `UpdatePerformanceController.php` | `getUserActiveSchedules()` b.135 | saring: `Flag_Team IS NULL OR Flag_Team <> 'Y'` |
| `masterPenjadwalan.vue` | form | pilihan "Berlaku untuk: Semua / Personal / Tim" |
| `masterPenjadwalan.vue` | `buildPayloads` ~b.2186 | sertakan `Flag_Team` di payload |

Validasi: `'Flag_Team' => 'nullable|in:Y,T'` di `store()` dan `update()`.

## 5. Urutan yang disarankan

1. Lapis 1 — langsung menghilangkan gejala, risiko rendah, tanpa migrasi.
2. Amati sebentar di pemakaian nyata; kalau admin ternyata tidak butuh kendali
   eksplisit per jalur, Lapis 2 boleh tidak dikerjakan sama sekali.
3. Lapis 2 kalau memang perlu memisahkan jendela waktu tim dan personal.

## 6. Yang perlu diputuskan sebelum Lapis 2 dikerjakan

- Bila jadwal tim dan jadwal personal untuk periode yang sama punya rentang
  tanggal berbeda, mana yang berlaku bagi anggota yang mengisi parameter tim
  lewat halaman individu? Rancangan ini mengasumsikan **jalur mengikuti
  pengajuan**, jadi yang berlaku adalah jadwal tim.
- Apakah admin boleh membuat dua jadwal penilaian aktif untuk periode yang sama
  (satu tim, satu personal)? Pengecekan duplikat sekarang
  (`store()` baris 507-521) memakai kunci Nama_Kegiatan + Tahun + Periode, jadi
  nama kegiatannya harus dibedakan — atau `Flag_Team` ikut masuk kunci duplikat.

## 7. Temuan lain di modul yang sama (di luar lingkup dokumen ini)

Sudah diperbaiki:

- SQL injection di `getDataKaryawan` — `$periodeDb` disisipkan ke string SQL pada
  dua subquery. Kini diikat sebagai parameter, ditambah validasi
  `periode: integer|between:1,12`, `tahun: digits:4`.
- Validasi dipindah ke luar `try` di `getDataKaryawan` dan
  `getDataKaryawanBelumAdaKpi` — `ValidationException` sebelumnya tertangkap
  `catch` generik dan dilaporkan sebagai 500, bukan 422.

Juga sudah diperbaiki (commit `f1ff8870`):

- `update()` kini meng-upsert peserta, tidak lagi menghapus lalu menyisipkan
  ulang, sehingga hasil `toggleSwitchPeserta` tidak hilang saat jadwal disunting.
- `update()` memvalidasi `Jenis_Kegiatan` dengan daftar yang sama seperti
  `store()`.
- `store()` dan `update()` membatasi `Periode` ke 1-12 dan menolak `Periode`
  kosong pada jalur yang membutuhkannya (dulu menghasilkan bulan `'00'`).
- Tujuh berkas halaman Vue mati dihapus.

Belum diperbaiki:

- `Flag_Update_KPI` dan `Flag_Batas_Verifikasi` ditulis tapi tidak dibaca kode
  mana pun (4 kemunculan, semuanya penulisan). Jenis kegiatan "update" dan
  "verifikasi" tidak berefek di modul mana pun. Untuk jadwal perorangan berjenis
  ini, `Periode` master dan `Start_Periode` peserta sama-sama dikosongkan
  (baris 503 dan 569) sehingga jadwal tidak punya periode sama sekali.
- `update()` menghapus lalu menyisipkan ulang seluruh peserta (baris 679-714):
  `Flag_Aktif` peserta selalu kembali `'Y'`, jadi hasil `toggleSwitchPeserta`
  hilang setiap kali jadwal diedit.
- `update()` tidak memvalidasi `Jenis_Kegiatan` dengan `in:` (baris 613),
  berbeda dari `store()`. Jenis di luar daftar membuat keempat flag NULL.
- `store()` menerima `Periode` kosong -> `str_pad(null, 2, '0')` -> `'2026-00'`;
  juga tidak dibatasi 1-12.
- `getDataCurrent` membalas 404 saat halaman kosong (baris 52), sehingga
  frontend masuk cabang error alih-alih menampilkan daftar kosong.
- Dua berkas mati, `masterPenjadwalan copy.vue` dan `copy 2.vue`, ikut terbundel
  karena `app.js:127` memakai `import.meta.glob('./Pages/**/*.vue')`.
