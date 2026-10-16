# DML-2026-105 — Menu "Penilaian Tim" untuk halaman Leader

| | |
|---|---|
| Change ID | `DML-2026-105-menu-penilaian-tim-leader` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-105-menu-penilaian-tim-leader/data-change.sql` |
| Bergantung pada | `DML-2026-093` **dan** `DML-2026-101` — urutannya mengikat, lihat di bawah |
| Perubahan kode penyerta | `PaLeaderService::kodeAnggota()` — sumber tim pindah ke `N_HRIS_Organization_Relationship`; boleh berangkat terpisah, keduanya tidak saling menuntut |
| Status | `TRACKER_PENDING` — dev **DONE**, production `PENDING` |

## Yang diselesaikan

`PerformanceAppraisalLeaderPage` (`/performance-appraisal/tim`) tidak bisa dicapai siapa
pun. Bukan sekadar tak punya butir menu: `check.jabatan` menolak siapa saja yang mengetik
alamatnya, karena kuncinya nol baris di ketiga tempat — `N_HRIS_Menus`,
`N_HRIS_Grup_Fitur_Map`, dan `HRIS_Page_Access`.

### Ini pemulihan, bukan pendaftaran yang terlupa

Barisnya pernah ada. `DML-2026-089` memasangnya 2 September 2026, lalu `DML-2026-093`
**mencabutnya** 3 September 2026 — sengaja, atas keputusan pemilik proyek, karena mockup
yang dipakai hanya mengenal dua peran: Peserta dan Administrator. Halaman dan endpointnya
tidak pernah dihapus; yang dicabut hanya barisnya dari sidebar.

Perubahan ini membalikkannya, juga atas keputusan pemilik proyek (6 September 2026),
sesudah halaman Leader disepakati sebagai **dashboard yang hanya menampilkan hasil yang
sudah diterbitkan** — peran ketiga yang tidak ada di mockup lama.

**Urutannya karena itu mengikat.** `DML-2026-093` menghapus tanpa syarat, jadi menjalankan
`DML-2026-105` lebih dulu membuat pekerjaannya lenyap tanpa satu pun galat. Di SQL Tracker,
093 harus tercatat lebih dulu.

Halamannya sendiri sudah jadi dan sudah berpenjaga. `PaLeaderService` hanya menampilkan
angka untuk hasil ber-status `TERBIT`, tidak pernah menyingkap nilai per penilai, dan
menyembunyikan rincian per kategori bila pengisinya kurang dari tiga orang
(`MIN_PENILAI_UNTUK_TAMPIL`). Yang kurang hanya pintunya.

## Kenapa global, bukan diberikan satu per satu

Akses di HCIS bergranularitas nol: role menunjuk GRUP FITUR, dan grup fitur menjangkau
seluruh halaman di dalamnya. "Leader" bukan sebuah role melainkan keadaan — siapa saja yang
kebetulan punya bawahan. Memetakannya ke role KPI justru memberikannya kepada admin HC,
bukan kepada para atasan.

Sisa pilihannya dua: menandainya global, atau menuliskan pemiliknya satu per satu di
`HRIS_Page_Access`. Yang kedua terdengar lebih rapi dan justru lebih berbahaya — daftarnya
menua diam-diam. Orang yang baru diangkat menjadi atasan tidak akan mengeluh halamannya
hilang; ia hanya tidak akan pernah membukanya, dan tidak ada yang tahu. Itu persis alasan
`PerformanceAppraisalSayaPage` ditandai global.

Yang ditanggung global: karyawan tanpa bawahan melihat satu butir menu tambahan, dan
halamannya menyambut dengan keterangan yang sudah ada di layar ("Anda belum memiliki scope
tim") — bukan galat, bukan layar kosong. Data orang lain tetap tidak terlihat; penjaganya
pemeriksaan keanggotaan di `PaLeaderService::detailAnggota()`, bukan gerbang rutenya.

## Header diambil, bukan ditulis

Header dan `Sort_Header` disalin dari baris `PerformanceAppraisalSayaPage`, begitu pula
`Id_Grup_Fitur` dari pemetaannya. Menebak angkanya membelah satu `Header_Code` pada dua
`Sort_Header`, dan grupnya tampil DUA KALI di sidebar dengan nama sama dan isi berbeda tanpa
satu pun galat — kekeliruan yang pernah terjadi pada menu PA. Id grup pun berbeda antara dev
dan production, sehingga angka yang ditulis tangan bisa menaruh halaman ini di modul lain.
Penjaga di akhir skrip menolak keduanya bila terulang.

## Verifikasi

Dijalankan di development, idempotensinya dibuktikan dengan menjalankan dua kali:

```
SEBELUM menus=0 map=0   →  SESUDAH menus=1 map=1
SEBELUM menus=1 map=1   →  SESUDAH menus=1 map=1   (idempoten terbukti)

PerformanceAppraisalSayaPage    Penilaian Saya   sort_header=6  sort_menu=1  global=Y
PerformanceAppraisalLeaderPage  Penilaian Tim    sort_header=6  sort_menu=2  global=Y
```

Sesudahnya `InertiaShellService::flushMenuCache()` dijalankan.

## Sumber "siapa bawahan saya" ikut disamakan

Menu bukan satu-satunya syarat, dan syarat keduanya diselesaikan bersamaan.
`PaLeaderService::kodeAnggota()` membaca `HRIS_Karyawan_Team`, sedangkan mesin penugasan PA
membaca `N_HRIS_Organization_Relationship`. Dua tabel menjawab satu pertanyaan, dan
akibatnya terukur di development:

| | penugasan kategori ATASAN | penilainya TIDAK melihat orangnya di halaman tim |
|---|---|---|
| Sumber lama (`HRIS_Karyawan_Team`) | 1.721 | **1.717** |
| Sumber baru (`N_HRIS_Organization_Relationship`) | 1.721 | **0** |

Artinya: hampir setiap atasan yang ditugaskan menilai bawahannya tetap disambut "Anda belum
memiliki scope tim" di halaman ini. Menu yang terdaftar tidak akan menolong apa pun selama
itu berlaku.

Servicenya kini membaca tabel yang SAMA dengan mesin penugasan, dua arah sekaligus
(`BAWAHAN` dari sisi subjek dan cerminnya `ATASAN` dari sisi terkait), dengan penyaring
keberlakuan yang disalin dari `PaAssignmentBuilderService::hangatkanCalon()`. Dibuktikan
terhadap **467 leader**: hasilnya sama persis dengan kueri SQL yang ditulis terpisah.

Satu hal yang perlu diketahui pemilik proses: **6.972 dari 7.008 baris relasi di development
ber-`Sumber_Data = 'DUMMY_DEV'`**. Angka 467 karena itu bukan gambaran production. Yang
dibuktikan di sini adalah KESEPADANAN kedua sisi, bukan besaran datanya — dan kesepadanan itu
berlaku berapa pun barisnya.

## Cara membatalkan

```sql
DELETE FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';
DELETE FROM N_HRIS_Menus          WHERE Jenis_page = 'PerformanceAppraisalLeaderPage';
```

Aman: tidak ada baris lain yang menunjuk kunci ini, dan halamannya kembali ke keadaan
semula — ada tetapi tak tercapai. Jalankan `flushMenuCache()` sesudahnya.
