# KPI Parameter Tim — Bulan Mulai Per Anggota

Status: **sudah diterapkan** — commit `72eae42a` (backend + ringkasan konfirmasi)
dan `a963d8ed` (bulan mulai per anggota di grid target). Yang belum: pembersihan
data lama, lihat bagian 8.
Tanggal: 2026-07-29. Diukur terhadap DB dev (`emi_db_web_hr`).

Perbedaan dari rancangan awal, keduanya karena temuan saat mengerjakan:

1. Mekanisme mematikan sel bulan **sudah ada** — `getTargetBreakdown` mengembalikan
   `isActive` per breakdown (baris 1382) dan frontend memakainya untuk
   `disabledField`. Yang salah hanya sumber bulan mulainya. Jadi butir 5a tidak
   perlu grid baru, cukup memperbaiki asal `$bln`.
2. Parameter `kode_karyawan` dibuat berupa **daftar**, bukan satu NIK, supaya satu
   endpoint melayani dua keadaan: tab satu anggota (satu NIK, tepat untuk dia) dan
   template bersama (semua yang dicentang, dipakai yang paling awal agar tidak ada
   bulan yang mestinya bisa diisi malah tak pernah dirender).

Dokumen saudara: [`kpi-penjadwalan-jalur-tim-vs-personal.md`](kpi-penjadwalan-jalur-tim-vs-personal.md).

## 1. Ini soal kebenaran skor, bukan kenyamanan

YTD dan Full Year menjumlahkan target dari **baris yang ada**, bukan dari 12 bulan
tetap (`DashboardCeoController.php:1116-1121`):

```php
$allTargets = DB::table('KPI_Pengajuan_Det')
    ->whereIn('Id_Pengajuan_Detail', $paramIds)
    ->whereBetween('Periode', [$tahun.'-01', $tahun.'-'.$maxMonthPad])
    ->groupBy('Id_Pengajuan_Detail')
    ->select('Id_Pengajuan_Detail', DB::raw('SUM(Nilai_Target) as total'));
```

Actual dijumlahkan atas periode yang sama, hasilnya masuk rumus parameter,
dikali bobot, dibagi Σbobot. **Jumlah bulan tidak pernah masuk penyebut.**

Konsekuensinya: kalau seorang anggota dibuatkan baris target untuk bulan yang
tidak pernah ia jalani, `total_target`-nya membengkak sementara `total_actual`
tetap, dan achievement-nya jatuh tanpa sebab yang terlihat di layar mana pun.

Karena itu pemangkasan bulan per anggota **wajib**, bukan pilihan. Sekaligus ini
menjawab bahwa **tidak perlu prorata nilai target** — desain skoring sudah
menanganinya lewat Σbobot.

## 2. Masalahnya sekarang

Bulan mulai dihitung **sekali** dari NIK orang yang sedang login — yaitu leader —
lalu dipakai untuk seluruh anggota dalam form itu:

| Lokasi | Isi |
|---|---|
| `FormKpiController.php:1189` | `$Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan` |
| `FormKpiController.php:1201-1222` | `$startPeriode` dari `Periode` master, ditimpa `Start_Periode` milik NIK di atas |
| `FormKpiController.php:1240` | `getListTarget($startPeriode)` — satu rentang bulan untuk semua |
| `FormKpiController.php:1299-1339` | `getTargetBreakdown`, pola sama |
| `FormKpiController.php:1950-1954` | saat simpan hanya melewati nilai kosong — **tidak ada penjagaan sama sekali** |

Lebih jauh, `index()` (`FormKpiController.php:37-48`) hanya menampilkan jadwal
tempat **leader sendiri** terdaftar:

```php
->where('p.Kode_Karyawan', $kodeKaryawan)   // NIK yang login
->where('m.Flag_Form_KPI', 'Y')
```

sehingga jadwal perorangan yang mendaftarkan **anggota** tidak pernah muncul di
layar leader.

### Bukti di data dev

```
Jadwal 47 'PENGISIAN KPI Param' (AKTIF, Perorangan=Y)
   Y62 Tari     Start_Periode=2026-05
   A38 Aisyah   Start_Periode=2026-05
   F19 Erlangga Start_Periode=2026-05
   -> Andre (leader Tari) tidak terdaftar

Pengajuan tim yang terjadi:
   PKPI06-26-0010  anggota=Tari  leader=Andre  jadwal=17 (GLOBAL)

Baris target Tari:
   2026-01  200.0      <- sebelum bulan mulai
   2026-02  200.0      <- sebelum bulan mulai
   2026-03  200.0      <- sebelum bulan mulai
   2026-04  100200.0   <- sebelum bulan mulai
   2026-05..12  200.0 masing-masing
```

600 dari 2.200 target (±27%) duduk di bulan sebelum Tari mulai. Baris April
bernilai `100200` sementara bulan lain `200` — hampir pasti salah ketik data
seed; angka 27% sudah mengecualikannya. Data dev hanya punya satu pengajuan tim,
jadi ini contoh, bukan statistik.

## 3. Aturan resolusi bulan mulai, per anggota

Dievaluasi untuk setiap anggota yang dicentang, bukan sekali untuk seluruh form:

1. Ada jadwal **pengisian perorangan** untuk anggota itu
   (`m.Flag_Form_KPI='Y'`, `m.Flag_Aktif='Y'`, `p.Flag_Aktif='Y'`,
   `m.Tahun` = tahun jadwal yang dipilih leader) → menang, pakai
   `p.Start_Periode`.
2. Tidak ada → pakai jadwal **global** yang dipilih leader, ambil bulan dari
   `m.Periode`.
3. Global pun tanpa `Periode` → bulan 1.

Perorangan menimpa global. Ini persis model yang sudah berlaku di jalur
penilaian.

### Yang sengaja TIDAK diubah

Dropdown jadwal leader tetap seperti sekarang — leader hanya memilih jadwal
global. Jadwal perorangan **tidak** perlu muncul di layar leader; backend yang
menerapkannya per anggota. Ini menghindari kebingungan "kenapa saya harus
memilih jadwal milik orang lain".

### Pendaftaran hanya menimpa bulan mulai, bukan gerbang keanggotaan

Anggota yang tidak terdaftar di jadwal perorangan mana pun tetap boleh ikut
pengajuan tim; ia jatuh ke aturan 2 lalu 3. Kalau pendaftaran dijadikan syarat
keikutsertaan, seluruh pengajuan tim yang berjalan sekarang (memakai jadwal
global tanpa peserta) langsung patah.

## 4. Titik ubah backend

Semua di `FormKpiController.php`.

| Lokasi | Sekarang | Jadi |
|---|---|---|
| `:1908` (`store` tim) | `$ambilJadwal` diresolusi sekali untuk semua anggota; `Id_Penjadwalan` sama untuk semua | diresolusi **per anggota** sesuai aturan bagian 3 |
| `:1950-1954` | hanya melewati nilai kosong | buang periode `< bulan mulai anggota tsb` sebelum `insert` |
| `:1201-1222` (`getDataCurrent`) | satu `$startPeriode` dari NIK leader | kembalikan peta `{kode_karyawan: 'YYYY-MM'}` untuk seluruh anggota tim |
| `:1299-1339` (`getTargetBreakdown`) | pola sama | idem |

Struktur data sudah mendukung tanpa perubahan skema: `KPI_Pengajuan` dibuat
**di dalam loop per anggota** (`:1906`), begitu pula `KPI_Pengajuan_Detail`
(`:1935`) dan `KPI_Pengajuan_Det` (`:1979`). Sekarang isinya kebetulan identik
karena ditulis dari satu grid, bukan karena strukturnya memaksa.

### `Id_Penjadwalan` anggota

Diikat ke jadwal yang **benar-benar mengatur** anggota itu — jadwal perorangan
bila menang, jadwal global bila tidak. Satu-satunya pemakaian hilir adalah
`mp.Tahun` (`UpdatePerformanceController.php:1415`), dan tahunnya sama pada
kedua kemungkinan, jadi aman.

### Jadwal perorangan yang jendelanya lewat

Tetap menang selama `Flag_Aktif='Y'` di master maupun baris peserta dan tahunnya
cocok. Jendela tanggal **tidak** ikut dihitung — kalau ikut, seorang anggota bisa
diam-diam jatuh kembali ke bulan 1 hanya karena tanggal terlewat, dan itu sulit
dilacak.

## 5. Perubahan tampilan

Form tim sudah bekerja per anggota — ada tab per orang dan toggle
"KPI Custom" / "Gunakan Template" (`FormKPITeam.vue:1079-1094`, `:4654`). Yang
ditambahkan:

**a. Sel bulan sebelum bulan mulai dimatikan** di grid tab anggota tersebut.
Leader tidak bisa mengetik sesuatu yang akan dibuang — pencegahan, bukan
peringatan.

**b. Keterangan di tab anggota yang memakai template bersama:**

> *Template diterapkan mulai **Mei 2026**. Empat bulan pertama tidak dipakai
> untuk anggota ini.*

**c. Pengelompokan berdasarkan bulan mulai.** Dengan 80 anggota, tab per orang
tidak berguna. Tampilkan ringkasan kelompok:

```
Mulai Maret    23 orang   Mar-Des   [lihat daftar]
Mulai Juni     31 orang   Jun-Des   [lihat daftar]
Mulai Agustus  18 orang   Ags-Des   [lihat daftar]
Mulai Oktober   8 orang   Okt-Des   [lihat daftar]
```

**d. Ringkasan yang sama di dialog konfirmasi.** Sekarang dialog itu
(`FormKPITeam.vue:4634`) hanya berbunyi "Apakah Anda yakin ingin mengajukan KPI
untuk N anggota?".

Tetap **satu template** — parameter, bobot, formula, satuan, target master sama
untuk semua. Yang bercabang hanya jendela bulannya. Kalau satu kelompok butuh
nilai target yang benar-benar berbeda, mekanismenya sudah ada lewat toggle
"KPI Custom" per anggota; jangan bangun "custom per kelompok" sebelum terbukti
perlu.

## 6. Penjagaan sisi server

Tampilan mencegah kekeliruan, server mencegah data salah masuk. Pemangkasan di
`:1950-1954` tetap wajib walau tampilan sudah benar — bundle frontend lama yang
masih ter-cache tidak boleh bisa menulis periode sebelum bulan mulai.

## 7. Yang sudah diputuskan

- Potong ekor, **tanpa prorata** nilai target. Dasarnya cara YTD dihitung
  (bagian 1) — dikonfirmasi pemilik proses.
- Perorangan menimpa global.
- Pendaftaran hanya menimpa bulan mulai, bukan gerbang keanggotaan.
- Dropdown jadwal leader tidak diubah.
- Tetap satu template, bercabang hanya pada jendela bulan.

## 8. Yang masih terbuka

- Apakah leader boleh **menimpa** bulan mulai hasil resolusi untuk anggota
  tertentu? Saran: tidak. Bulan mulai adalah keputusan admin; kalau leader boleh
  menimpanya, `Start_Periode` kehilangan makna.
- Bagaimana memperlakukan pengajuan tim yang **sudah terlanjur** punya baris
  target sebelum bulan mulai anggota (kasus Tari)? Perlu keputusan terpisah:
  dibiarkan, atau dibersihkan lewat perintah artisan sekali jalan. Membersihkan
  akan mengubah skor YTD yang sudah tampil — jadi harus disengaja, bukan efek
  samping.

## 9. Cara menguji

1. Anggota A tanpa jadwal perorangan, anggota B dengan `Start_Periode` Mei.
   Leader memilih jadwal global Periode Januari, mengisi satu template Jan-Des,
   mengirim untuk keduanya.
   → A dapat `KPI_Pengajuan_Det` Jan-Des; B hanya Mei-Des.
2. `Id_Penjadwalan` milik B menunjuk jadwal perorangannya, milik A menunjuk
   jadwal global.
3. Kirim payload langsung ke `/api/v1/form-kpi/team/store` berisi periode
   Januari untuk B → periode itu tidak tersimpan.
4. YTD B dihitung atas 8 bulan, tanpa beban target Jan-Apr.
5. Anggota tanpa jadwal perorangan dan jadwal global tanpa `Periode` → mulai
   bulan 1, perilaku lama tetap.
6. Jadwal perorangan B dinonaktifkan (`Flag_Aktif='T'`) → B jatuh ke jadwal
   global.

## 10. Catatan kebersihan

Tujuh halaman Vue mati ikut terbundel karena `app.js:127` memakai
`import.meta.glob('./Pages/**/*.vue')`:

```
formKPI/formKPI Benar dengan logika form.vue
formKPI/formKPI dengan sidbar history.vue
formKPI/formKPI copy.vue
formKPI/team/FormKPITeam Benar dengan logika form.vue
formKPI/team/FormKPITeam 2.vue
penjadwalan/masterPenjadwalan copy.vue
penjadwalan/masterPenjadwalan copy 2.vue
```

Selain memberatkan build, nama seperti "Benar dengan logika form" membuat orang
ragu berkas mana yang sebenarnya dipakai — berbahaya saat menyunting modul ini.
