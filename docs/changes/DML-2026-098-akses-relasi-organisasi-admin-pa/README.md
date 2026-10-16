# DML-2026-098 — Admin PA boleh menyetel relasi organisasi

| | |
|---|---|
| Change ID | `DML-2026-098-akses-relasi-organisasi-admin-pa` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-098-akses-relasi-organisasi-admin-pa/data-change.sql` |
| Bergantung pada | DML-2026-097 (menu + pemetaan `systemRelasiOrganisasiPage`) |
| Perubahan kode penyerta | `EnsureSystemAdminOrPage`, alias `system.admin.atau`, `routes/SYSTEM/Organization/RelasiOrganisasi.php` |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Halaman relasi organisasi digerbangi `system.admin`, dan pemegang bendera itu **satu orang**.
Sementara yang membutuhkannya untuk pekerjaannya justru admin Performance Appraisal:
penugasan penilai diturunkan dari tabel relasi ini, dan selama isinya kosong penugasan selain
penilaian diri tidak bisa terbentuk sama sekali.

Jadi orang yang menyiapkan siklus tidak bisa memperbaiki hal yang menghalangi siklusnya.

## Perubahan kodenya: gerbang ATAU, bukan tumpukan

Middleware Laravel bersifat **DAN**. Menumpuk `system.admin` dan `check.jabatan` menghasilkan
"harus System Admin **dan** punya halamannya" — kebalikan dari yang dimaksud, dan kebalikan
yang tidak menampakkan diri: satu-satunya System Admin yang ada memang biasanya punya semua
halaman, jadi layarnya tetap terbuka untuknya dan salahnya baru terlihat pada orang kedua.

Mengganti begitu saja `system.admin` dengan `check.jabatan` juga salah: `CheckJabatan` tidak
menyinggung bendera `Is_System_Admin` sama sekali, jadi System Admin akan **kehilangan** akses
atas halaman yang selama ini memang miliknya.

`EnsureSystemAdminOrPage` menyatukan keduanya secara eksplisit.

## Penerimanya diturunkan, bukan didaftar

Id pengguna di dev dan produksi **tidak sama**. Skrip yang menyebut `UserID_Web = 622` akan
memberi akses kepada orang yang sama sekali lain di produksi — dan kesalahan itu tidak
menampakkan diri: barisnya tersimpan, halamannya terbuka, hanya untuk orang yang salah.

Kriterianya: siapa pun yang **sudah** memegang `PerformanceAppraisalPage` secara eksplisit.
Itu definisi operasional "admin PA" di basis data ini.

Kolom `Kode_Perusahaan`, `ID_Level`, `ID_Divisi` disalin dari baris `PerformanceAppraisalPage`
milik orang yang sama, bukan diisi nilai baku — ketiganya tidak boleh NULL dan ikut dibaca
jalur legacy, sehingga tebakan menghasilkan baris yang tersimpan rapi tetapi tidak pernah
cocok saat dibaca: hak yang terlihat ada dan tidak berfungsi.

## Yang semula sengaja tidak dilakukan — lalu diputuskan sebaliknya

> **Dibalik oleh DML-2026-099.** Bagian di bawah ini adalah pertimbangan saat 098 ditulis, dan
> tetap dipertahankan karena alasannya masih benar. Yang berubah keputusannya: pemilik produk
> memilih tetap membuat butir menu di modul KPI, dengan konsekuensi akses yang disebutkan di
> sini diterima. Butir barunya `PaMasterRelasiOrganisasiPage`, dan gerbang rutenya kini
> menerima dua kode halaman.

**Memetakan halaman ini ke grup fitur KPI.** Di sistem ini role memberi **seluruh** halaman
grupnya tanpa granularitas — grup KPI mencakup 38 halaman — sehingga pemetaan itu akan
menyerahkan master "siapa menilai siapa" kepada setiap pemegang role KPI, sekarang maupun yang
ditambahkan kelak, termasuk role yang namanya menjanjikan cakupan lain seperti "KPI Menu
Administrator". Diputuskan bersama: pemberian per orang.

Penambahan orang berikutnya **tidak perlu DML** — layar Page Access sudah bisa melakukannya.
Sesudah DML-099, pemegang role KPI mendapatkannya lewat rolenya dan tidak perlu baris apa pun.

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`, lalu diverifikasi lewat **efek** pada gerbangnya — bukan lewat isi
tabel, karena verifikasi yang hanya memeriksa kelolosan tidak membedakan "hak diberikan" dari
"gerbangnya rusak":

| Pengguna | Jalur | Hasil |
|---|---|---|
| NOIR (#4) | bendera System Admin | LOLOS |
| aji (#622) | hak halaman dari DML ini | LOLOS |
| tari (#642) | role KPI saja | 403 |
| Haris (#644) | role KPI saja | 403 |
| iqbal (#648) | role KPI saja | 403 |
| karyawan tanpa hak apa pun | — | 403 |

Sebelum DML ini dijalankan, gerbangnya juga sudah diuji dalam transaksi yang digulung balik:
System Admin lolos **tanpa** baris halaman, admin PA ditolak tanpa baris, lolos setelah
barisnya diberikan, dan ditolak lagi setelah barisnya dinonaktifkan — sehingga terbukti yang
meloloskan memang barisnya.
