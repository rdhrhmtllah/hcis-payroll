# DML-2026-090 — Cabut menu master struktural dari Administrator PA

| | |
|---|---|
| Change ID | `DML-2026-090-cabut-menu-master-struktural-pa` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-090-cabut-menu-master-struktural-pa/data-change.sql` |
| Bergantung pada | DML-2026-085 (kedelapan halaman master PA sudah terdaftar) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang dilakukan

Mencabut **dua** halaman dari menu Administrator PA:

- `PaMasterKuadranHavPage`
- `PaMasterKategoriPenilaiPage`

Yang dihapus hanya baris `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`, dan `HRIS_Page_Access`.
**Isi masternya tidak disentuh sama sekali.**

## Kenapa dua ini, bukan delapan-delapannya

Jalur frontend membuat halaman untuk seluruh master PA. Dua di antaranya seharusnya tidak
pernah disetel admin PA — dan itu bukan pendapat, melainkan sesuatu yang **kodenya sendiri
sudah nyatakan**: `PaMasterWriteService::alasanTertutup()` sudah menolak menambah maupun
menonaktifkan keduanya sejak awal.

| Master | Tambah | Nonaktif | Yang tersisa bagi admin |
|---|---|---|---|
| Kuadran HAV | ditolak | ditolak | menyunting nama & deskripsi |
| Kategori Penilai | ditolak | ditolak | menyunting nama & deskripsi |

Halaman setelan yang satu-satunya gunanya mengubah label bukan halaman setelan; ia hanya
tempat orang salah mengira sesuatu bisa diubah.

**Kuadran HAV** — matriks tiga kali tiga dan sudah penuh: sembilan kotak untuk sembilan
kombinasi `(RENDAH|SEDANG|TINGGI) × (RENDAH|SEDANG|TINGGI)`. Kotak kesepuluh tidak punya
tempat berdiri; menonaktifkan satu kotak membuat orang yang jatuh ke sel itu tidak masuk
kotak mana pun.

**Kategori Penilai** — empat baris, dan `SELF` ditulis langsung di dua berkas service.
Kategori baru tanpa aturan di setiap level tidak akan pernah muncul; menonaktifkan yang
ada memutus aturan siklus yang sedang berjalan.

## Enam yang TETAP, dan kenapa

`jenis-kompetensi`, `kompetensi`, `aturan-penilai`, `bobot-pilar`, `pita-nilai`,
`paket-soal` — semuanya keputusan bisnis yang sah diubah HC. Kompetensi bertambah,
aturan penilai berubah per level, bobot pilar digeser, grade didefinisikan ulang. Semua itu
memang pekerjaan admin PA.

## Isi masternya tetap sampai ke layar — jalurnya diperiksa, bukan diasumsikan

Ini yang paling perlu diperiksa ulang bila kelak ada yang meragukan perubahan ini.

| Tabel | Bagaimana datanya sampai ke layar |
|---|---|
| `N_PA_Rater_Category` | ikut di `/master/referensi`, kunci `kategori_penilai` |
| `N_PA_Hav_Quadrant` | **tidak** ada di `/master/referensi` — dibaca **langsung** oleh enam service |

Keenam service itu: kalibrasi, distribusi, leader, peserta, baca peserta, dan perhitungan.
Jadi nama kuadran tetap tampil di layar hasil, HAV Map, dan detail peserta lewat jalurnya
masing-masing.

Catatan jujur: draf pertama artefak ini menulis bahwa `/master/referensi` memulangkan
keduanya. Itu keliru — diperiksa dan dikoreksi sebelum dicatat.

## Kalau kelak memang perlu diubah

Lewat DML bernomor, sehingga terekam di SQL Tracker. Dan itu memang bentuk yang benar:
mengubah nama kuadran atau kategori penilai mengubah arti **seluruh riwayat penilaian**
yang sudah tersimpan, bukan hanya siklus berikutnya.

## Sesudah dijalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

## Verifikasi lewat EFEK, bukan isi tabel

1. Halaman Administrator PA tersisa **tepat 6** — diperiksa di dalam skrip.
2. `N_PA_Hav_Quadrant` tetap 9 baris, `N_PA_Rater_Category` tetap ≥4 — diperiksa di dalam
   skrip; kalau berkurang, transaksinya dibatalkan.
3. Gerbang H45 (user 4) untuk kedua halaman harus **ditolak**, sementara
   `PaMasterPitaNilaiPage` dan `PaMasterKompetensiPage` tetap **lolos** sebagai kontrol
   positif — tanpa kontrol itu, "ditolak" bisa berarti gerbangnya rusak untuk semua
   halaman.
4. HAV Map tetap menampilkan sembilan kotak berikut namanya.

Ketiga yang pertama sudah dijalankan pada dev 3 September 2026 dan lolos.

## Sisi frontend

**Tidak ada berkas Vue yang perlu dihapus.** Kedelapan master dilayani SATU halaman,
`MasterData/master.vue`, yang berganti isi menurut slug — jadi mencabut dua master tidak
menyisakan berkas yatim.

### Koreksi terhadap draf pertama README ini

Draf pertama menulis "rutenya akan menolak dengan 403 karena gerbangnya sudah dicabut".
**Itu keliru, dan diperiksa sebelum dicatat.** Gerbang rutenya menyebut delapan kunci
halaman sekaligus, dan `CheckJabatan` memakai logika **salah-satu**: memegang satu master
saja sudah cukup untuk membuka alamat master mana pun. Rutenya TIDAK 403.

Yang benar-benar menahan adalah `PaMasterDataController::pastikanBolehMaster()` di lapisan
data — jadi **datanya memang tidak pernah bocor**, tetapi halamannya tetap terbuka lalu
gagal memuat.

Karena itu, pada 3 September 2026 `index()` diubah menyaring `daftar_master` menurut hak
orang yang membuka. Yang bocor sebelumnya bukan data melainkan **harapan**: menawarkan
pintu yang pasti tertutup membuat orang mengira sistemnya rusak, bukan mengira dirinya
tidak berhak.
