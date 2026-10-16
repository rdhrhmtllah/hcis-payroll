# DML-2026-089 — Halaman leader Performance Appraisal

| | |
|---|---|
| Change ID | `DML-2026-089-menu-performance-appraisal-tim` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-089-menu-performance-appraisal-tim/data-change.sql` |
| Bergantung pada | DML-2026-084 (halaman siklus PA sudah terdaftar) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang dilakukan

Satu baris `N_HRIS_Menus` untuk `PerformanceAppraisalLeaderPage`
(`/performance-appraisal/tim`), dipasang **`Is_Global_Menu = 'Y'`**, plus satu baris
`N_HRIS_Grup_Fitur_Map` ke grup `KPI`. Nol baris `HRIS_Page_Access`.

## Kenapa global padahal penggunanya hanya 14 orang

Ini bukan pengulangan pola DML-2026-086, dan penting untuk tidak dibaca begitu.

DML-2026-086 global karena halamannya **memang** dipakai seluruh karyawan. Halaman ini
tidak: per 2 September 2026 hanya **14 orang** yang tercatat sebagai leader di
`HRIS_Karyawan_Team`, dari 958 karyawan aktif. Jadi di sini global menu adalah pertukaran
yang disengaja.

Yang dipertukarkan:

| | Akses eksplisit | Global menu |
|---|---|---|
| Yang melihat menu | 14 | 958 |
| Pemeliharaan | satu baris tiap keanggotaan tim berubah | nol |
| Leader baru | **tidak jalan** sampai seseorang mendaftarkannya | langsung jalan |
| Biaya bila salah | leader tidak pernah melihat timnya | 944 orang melihat menu yang kosong bagi mereka |

Yang menentukan pilihannya adalah **kapan kesalahannya ketahuan**. Keanggotaan tim berubah,
dan tidak ada satu pun mekanisme yang menyambungkan perubahan itu ke tabel akses. Leader
baru yang terlewat tidak akan mengeluh — ia hanya tidak pernah melihat timnya, dan itu
baru ketahuan ketika siklusnya sudah berjalan dan tenggat pengisiannya lewat. Sebaliknya,
944 orang yang membuka menu kosong akan mendapat kalimat yang jelas dan berhenti di situ.

Kekosongan yang dijelaskan lebih murah daripada leader yang diam-diam tidak pernah melihat
timnya.

Halamannya menjawab:

> Anda belum tercatat sebagai leader dari siapa pun, jadi halaman ini kosong.
> Bila ini keliru, hubungi HC untuk memeriksa keanggotaan tim Anda.

Bukan layar galat, dan bukan daftar kosong tanpa keterangan — respons `GET /leader/tim`
membawa `tanpa_tim: true` supaya layar bisa membedakannya dari "sedang memuat" dan "gagal".

## Menu terbuka bukan berarti data terbuka

Ini yang paling penting untuk diperiksa ulang bila kelak ada yang meragukan perubahan ini.

`Is_Global_Menu='Y'` hanya membuka **gerbang halaman**. Lingkup datanya dijaga terpisah,
di service, dan tidak lewat menu sama sekali:

- `PaLeaderService::tim()` mengambil anggota dari `HRIS_Karyawan_Team` untuk
  `Auth::id()` — identitas leader tidak pernah diterima dari permintaan.
- `PaLeaderService::detailAnggota()` menolak peserta yang bukan anggota tim, dengan
  memeriksa **keanggotaan**, bukan siklus. Memeriksa siklus akan meloloskan seluruh
  peserta siklus itu.
- Pemeriksaannya berada di service, bukan controller, supaya jalur lain — perintah
  artisan, ekspor, pekerjaan antrean — tidak melewatinya tanpa ada yang menyadari.

Dijaga `LeaderTest`, termasuk satu uji yang menelusuri balasan secara rekursif dan
menggagalkannya bila memuat kunci identitas penilai pada kedalaman mana pun.

## Sesudah dijalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

Tanpa ini menunya tidak muncul sampai cache kedaluwarsa sendiri, dan gejalanya menyamar
jadi "DML-nya tidak jalan".

## Verifikasi yang harus dilakukan lewat EFEK

Bukan dengan membaca isi tabel — baris yang ada tidak membuktikan gerbangnya terbuka:

1. Karyawan acak yang **bukan** leader harus **lolos** gerbang
   `PerformanceAppraisalLeaderPage` dan menerima `tanpa_tim: true`.
2. Karyawan yang sama harus tetap **ditolak** pada `PaMasterPitaNilaiPage` sebagai kontrol
   negatif — tanpa ini, "lolos" bisa berarti gerbangnya rusak untuk semua halaman.
3. Seorang leader harus melihat **hanya** anggotanya sendiri.
