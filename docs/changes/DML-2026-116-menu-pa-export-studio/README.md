# DML-2026-116-menu-pa-export-studio

## Metadata

- **Change ID**: `DML-2026-116-menu-pa-export-studio`
- **Target Database**: `Web_HRIS`
- **Type**: DML
- **Revision**: `r1`
- **DML Checksum (SHA-256)**: `f55ef67a2f7f1149072aebefb45562e255b2e3bcb3463abed14d64db92f7ee4e`
- **Forward Checksum (SHA-256)**: `f55ef67a2f7f1149072aebefb45562e255b2e3bcb3463abed14d64db92f7ee4e`
- **Affected Objects**: `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map`, `HRIS_Page_Access`
- **Status**: `DRAFT` (belum dijalankan ke Web_HRIS mana pun)

## Deskripsi

Mendaftarkan menu sidebar dan hak akses untuk halaman **Export Studio Performance
Appraisal** (`/performance-appraisal/export-studio`, `Jenis_page = 'PaExportStudioPage'`,
route `pa.export-studio`). Controller (`PaExportStudioController`), route, dan halaman Vue
(`PaExportStudioPage.vue`) sudah ada di worktree ini dari tahapan sebelumnya, tetapi
halamannya belum bisa dicapai siapa pun: `check.jabatan:PaExportStudioPage` menolak setiap
permintaan karena kuncinya nol baris di ketiga tabel berikut.

| Tabel                     | Sebelum | Sesudah |
|---------------------------|---------|---------|
| `N_HRIS_Menus`            | 0       | 1       |
| `N_HRIS_Grup_Fitur_Map`   | 0       | 1       |
| `HRIS_Page_Access`        | 0       | 2 (mengikuti jumlah pemegang aktif `PaHasilPage` pada development) |

### Kenapa tiga baris, dan kenapa Grup_Fitur_Map yang paling sering terlupa

1. `N_HRIS_Menus` -- supaya menunya ada di sidebar Administrator PA.
2. `N_HRIS_Grup_Fitur_Map` -- **tanpa baris ini menunya tidak pernah muncul walaupun
   haknya sudah benar.** Resolusi menu di `InertiaShellService` menyaring lewat pemetaan
   grup fitur lebih dulu, sebelum hak per halaman diperiksa sama sekali.
3. `HRIS_Page_Access` -- supaya peran admin PA benar-benar berhak membukanya. Halaman ini
   **tidak** ditandai global (`Is_Global_Menu = 'T'`), sama seperti seluruh halaman lain
   pada header `administrator_pa`.

### Nilai yang diturunkan dari data, bukan ditulis tangan

- **Id_Grup_Fitur**: diambil dari pemetaan `PaHasilPage` yang sudah aktif (terverifikasi
  `2` = "Key Performance Indicator" di development), bukan ditulis angka. Id grup bisa
  berbeda antar basis data; menyalinnya dari baris acuan membuat skrip ini tetap benar di
  mana pun dijalankan.
- **Penerima `HRIS_Page_Access`**: diturunkan dari siapa pun yang hari ini sudah memegang
  `PaHasilPage` secara aktif dan eksplisit -- pemilik hasil penilaian, yang juga wajib bisa
  mengekspornya. Pada development per 2026-09-09 itu dua orang: `UserID_Web` 622 dan 4.
  Kolom penyerta (`Kode_Perusahaan`, `ID_Level`, `ID_Divisi`) disalin dari baris sumber
  masing-masing, bukan ditebak.
- **Header, Sort_Header, dan Sort_Menu**: header (`administrator_pa` / "Administrator PA"
  / `bi bi-gear-fill` / Sort_Header 11) disalin dari baris `PaHasilPage`; `Sort_Menu`
  dihitung `MAX(Sort_Menu) + 1` pada header yang sama (menghasilkan 15 pada development
  saat skrip ini ditulis).

### Nilai yang ditetapkan langsung

- `Nama_Menu`: **Export Studio** -- disamakan dengan penamaan Export Studio SOP
  (`menuSopExportStudioPage`) dan Visitor (`visitorExportStudioPage`), bukan istilah baru.
- `Icon_Menu`: **`bi-sliders`** -- ikon yang sama dipakai kedua Export Studio lain di
  HCIS sebagai bahasa "rakit laporan sendiri" lintas modul. Catatan: ikon ini kebetulan
  sudah dipakai `PaKalibrasiPage` ("Kalibrasi Grade") pada header yang sama; labelnya
  berbeda sehingga tidak membingungkan, tetapi disebutkan di sini sebagai keputusan sadar,
  bukan kealpaan.
- `Route_Name`: **`pa.export-studio`**, diverifikasi dari
  `routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php`.
- `Menu_Subtitle`: "Rakit dan unduh laporan Performance Appraisal sendiri".
- `Target`: `self`; `Is_Show_Sidebar` (menu): `Y`; `Is_Global_Menu`: `T` (tidak global).

## Perbaikan dari review (sebelum eksekusi apa pun)

Review menemukan satu temuan **Important** pada revisi pertama skrip, sebelum skrip ini
dijalankan ke lingkungan mana pun:

**Masalah**: penjaga idempotensi pada `INSERT INTO HRIS_Page_Access` menyaring
`NOT EXISTS (... AND ada.Flag_Aktif = 'Y' ...)`. Aplikasi mencabut akses dengan **mengubah**
`Flag_Aktif` menjadi `'T'` (`PageAccessController`), bukan menghapus barisnya. Akibatnya,
menjalankan ulang skrip ini (replay lewat SQL Tracker, atau ke lingkungan lain) setelah
seorang admin mencabut akses seseorang akan membuat `NOT EXISTS` tidak menemukan baris
**aktif**, lalu menyisipkan baris baru ber-`Flag_Aktif='Y'` -- pencabutan yang disengaja
hidup kembali tanpa galat maupun jejak yang terlihat. Penjaga akhir skrip (`THROW 50590`)
juga tidak menangkapnya karena ia hanya menghitung duplikat di antara baris aktif, dan
dalam skenario itu cuma ada satu baris aktif.

**Perbaikan**:

1. `NOT EXISTS` pada langkah 3 (`HRIS_Page_Access`) tidak lagi menyaring `Flag_Aktif` --
   ia memeriksa keberadaan barisnya, aktif atau tidak, mengikuti pola
   `DML-2026-026-register-visitor-export-studio-menu`. Idempoten sekarang berarti
   "beri sekali"; pencabutan adalah keputusan admin dan bertahan terhadap eksekusi ulang.
   Mengaktifkan kembali baris yang dinonaktifkan sengaja TIDAK dilakukan implisit lewat
   `INSERT` -- itu harus berupa `UPDATE ... SET Flag_Aktif='Y'` eksplisit oleh admin.
2. Penjaga `THROW 50586` (sebelumnya membandingkan jumlah pemegang **aktif** Export Studio
   dengan jumlah pemegang aktif `PaHasilPage`) diubah menjadi memeriksa "apakah setiap
   pemegang aktif `PaHasilPage` PERNAH tercatat di `HRIS_Page_Access` untuk
   `PaExportStudioPage`" (aktif atau tidak). Guard versi lama akan salah meledak kalau
   skrip dijalankan ulang setelah admin mencabut akses seseorang yang PaHasilPage-nya
   masih aktif -- padahal itu bukan kesalahan, melainkan keputusan admin yang harus
   dihormati.
3. Penjaga `THROW 50590` (duplikat hak akses) **tetap** hanya menghitung baris **aktif**
   per `UserID_Web`, tidak diubah menjadi seluruh riwayat baris. Alasannya: riwayat "satu
   baris lama ber-`Flag_Aktif='T'` plus satu baris baru ber-`Flag_Aktif='Y'` untuk
   kombinasi Level/Divisi berbeda" itu wajar (mis. orang pindah level/divisi) dan bukan
   cacat -- menghitungnya sebagai kembar akan menolak keadaan yang sah. Yang benar-benar
   berbahaya (dan tetap dijaga) adalah DUA baris **aktif sekaligus** untuk satu orang,
   karena mencabut salah satunya akan terlihat seperti pencabutan yang tidak bekerja.
4. Checksum dihitung ulang dan `database/ddl/status.json` diperbarui:
   `dml_checksum_sha256` dan `forward_checksum_sha256` sekarang
   `f55ef67a2f7f1149072aebefb45562e255b2e3bcb3463abed14d64db92f7ee4e` (sebelumnya
   `375615aa860c347ce316c20cb9a3f10097328f6bb50e4fabdc518bd2f7d3d1a1`).

Perbaikan ini diverifikasi ulang lewat `SELECT` read-only yang mensimulasikan kondisi
`INSERT` dan kedua penjaga terhadap data development saat ini (lihat bagian Verifikasi di
bawah) -- tidak ada perubahan hasil untuk keadaan hari ini (2 baris tetap tersisip untuk
UserID_Web 622 dan 4), karena belum ada riwayat pencabutan pada `PaExportStudioPage` yang
memang belum pernah ada barisnya sama sekali. Tidak ada eksekusi INSERT/UPDATE/DELETE yang
dijalankan terhadap `Web_HRIS`.

## Bukti penomoran (dua sumber, sesuai instruksi tugas)

- `database/ddl/status.json`: entri `change_id` tertinggi sebelum perubahan ini adalah
  `DML-2026-115-label-menu-bobot-kpi-kompetensi` (DDL tertinggi: `DDL-2026-089-kalibrasi-boleh-dilepas`).
  Nomor tertinggi keseluruhan (DDL dan DML berbagi satu urutan): **115**.
- `database/dml/` (nama direktori): entri tertinggi adalah
  `DML-2026-115-label-menu-bobot-kpi-kompetensi` (`database/dml/` juga memuat
  `DDL-2026-089-kalibrasi-boleh-dilepas` yang lebih rendah). Nomor tertinggi: **115**.
- Dicek pula `master`/`origin/master`: nomor tertinggi di sana hanya `DML-2026-099`, jauh
  di bawah 115 pada branch ini -- tidak ada risiko tabrakan pada `116`.
- **Nomor yang dipakai: 116.**

## Verifikasi yang dilakukan (SELECT saja, tidak ada eksekusi DML)

Seluruh verifikasi dijalankan lewat `php artisan tinker` (read-only) terhadap
`Web_HRIS` development, bukan lewat migrasi atau perubahan basis data:

1. **Grup fitur aktif** (`N_HRIS_Master_Grup_Fitur`, `Flag_Aktif = 'Y'`): 7 baris, id 1-7
   (ESS, **KPI=2**, LMS, VISITOR, System, Module Builder, Document Management System).
   Mengonfirmasi ulang bahwa `Id_Grup_Fitur = 2` untuk KPI masih berlaku hari ini.
2. **Baris menu header `administrator_pa`**: 16 baris terbaca (`Sort_Menu` 1-14 dengan
   beberapa baris tersembunyi berbagi angka lama seperti 8 dan 11 -- pola yang sama
   didokumentasikan di `DML-2026-110` dan `DML-2026-114`). `MAX(Sort_Menu)+1` = 15.
3. **`HRIS_Page_Access` untuk `PaHasilPage`** (`Flag_Aktif = 'Y'`): 2 baris --
   `UserID_Web` 622 (`Kode_Perusahaan` 001, `ID_Level` 4, `ID_Divisi` 22) dan `UserID_Web`
   4 (001, 5, 22). Pola yang sama diperiksa juga untuk `PerformanceAppraisalPage`,
   `PaPemantauanPage`, `PaRiwayatPage`, `PaDashboardPage`, `PaMasterBobotPilarPage`: semua
   persis 2 pemegang aktif, mengonfirmasi bahwa dua orang inilah "admin PA" operasional
   di database ini, bukan seluruh 39 pemegang role grup KPI (`Id_Grup_Fitur = 2`).
4. **`N_HRIS_Grup_Fitur_Map` untuk `PaHasilPage`**: `Id_Grup_Map` 248, `Id_Grup_Fitur` 2,
   `Is_Default_Landing` 'T', `Is_Show_Sidebar` 'Y', `Flag_Aktif` 'Y' -- pola yang disalin
   persis oleh skrip ini.
5. **Route dan middleware**: `routes/KPI/PerformanceAppraisal/Export/PerformanceAppraisalExport.php`
   mendaftarkan `pa.export-studio` di balik `check.jabatan:PaExportStudioPage`, dan
   `App\Http\Middleware\CheckJabatan` memanggil `User::memilikiJabatan()`, yang meloloskan
   halaman lewat tiga jalur: pemetaan role->grup fitur, `HRIS_Page_Access` eksplisit, atau
   `Is_Global_Menu = 'Y'` -- mengonfirmasi bahwa tanpa baris `HRIS_Page_Access` di sini,
   dua admin PA yang ada pun tetap akan ditolak 403.
6. **Simulasi read-only** dari ketiga pernyataan `SELECT` sumber yang dipakai skrip
   (menu, grup fitur map, page access) dijalankan langsung terhadap database untuk
   memastikan hasilnya cocok dengan yang didokumentasikan di atas sebelum ditulis ke
   dalam skrip DML.

Tidak ada INSERT/UPDATE/DELETE/DDL yang dijalankan terhadap `Web_HRIS` sebagai bagian dari
pekerjaan ini.

## Artefak Perubahan

- `database/dml/DML-2026-116-menu-pa-export-studio/data-change.sql`

## Eksekusi development

Bagian ini menggantikan daftar "yang belum dilakukan" yang berlaku saat artefak ini
ditulis. Artefaknya sendiri tidak berubah.

**Lokal (Web_HRIS @ 127.0.0.1:14330, Docker) — 11 September 2026.** Barisnya sudah ada
saat diperiksa pada 14 September 2026: satu baris menu, satu baris grup fitur, dan satu
baris `HRIS_Page_Access` (UserID_Web 4, `Created_At` 2026-09-11 13:52). Satu baris, bukan
dua, karena saat itu baru satu orang memegang `PaHasilPage` secara aktif. Entri ledger
untuk perubahan ini masih tertulis `PENDING` dan dikoreksi menjadi `DONE` pada 14
September 2026.

**Remote (Web_HRIS @ team311.dyndns.info:1433) — 14 September 2026.** Dijalankan dalam
rangka menyamakan basis data pengembangan remote dengan lokal; sasaran diverifikasi lewat
`SELECT DB_NAME()` + `@@SERVERNAME` lebih dulu. Sebelum: nol baris pada ketiga tabel.
Sesudah: 1 baris `N_HRIS_Menus`, 1 baris `N_HRIS_Grup_Fitur_Map`, 2 baris
`HRIS_Page_Access` — dua, karena penerimanya diturunkan dari pemegang aktif `PaHasilPage`
dan di remote ada dua. Skrip dijalankan ulang dan berakhir no-op.

## Yang masih harus dilakukan pemilik proyek

1. Membersihkan cache menu pada lingkungan yang memakai basis data ini:
   `php artisan tinker --execute="app(\App\Services\SYSTEM\Application\Shell\InertiaShellService::class)->flushMenuCache();"`
2. `php artisan route:clear`.
3. Meninjau, mencatat ke SQL Tracker, lalu menjalankan ke production melalui SQL Tracker.

## Keraguan / hal yang perlu ditinjau pemilik proyek

- **Ikon `bi-sliders` dipakai dua kali di header `administrator_pa`** (Export Studio dan
  Kalibrasi Grade). Dipilih demi konsistensi lintas modul (SOP dan Visitor Export Studio
  memakai ikon yang sama), tetapi bisa diganti bila pemilik proyek menginginkan ikon yang
  unik per baris dalam satu header.
- **`Route_Name = 'pa.export-studio'`** ditulis eksplisit sesuai instruksi tugas, walaupun
  seluruh baris menu `administrator_pa` lain menyimpan `Route_Name = NULL` (kolom ini
  tampak tidak dibaca oleh `InertiaShellService` maupun `MenuBindingService` saat ini).
  Tidak menimbulkan risiko fungsional, tetapi disebutkan sebagai penyimpangan pola sengaja.
- Penerima `HRIS_Page_Access` (622 dan 4) adalah nilai pada **development** hari ini;
  skrip menurunkannya dari `PaHasilPage` secara dinamis, jadi jika susunan admin PA di
  production berbeda, hasil di production akan mengikuti data production saat skrip
  dijalankan di sana -- bukan angka 622/4 yang ditulis di komentar.
