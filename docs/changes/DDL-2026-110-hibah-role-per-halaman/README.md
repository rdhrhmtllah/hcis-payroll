# DDL-2026-110-hibah-role-per-halaman

## Ringkas

Memisahkan dua tugas yang selama ini ditumpuk pada `N_HRIS_Grup_Fitur_Map`,
lalu memakai pemisahan itu untuk membuat pemisahan menu Visitor benar-benar
mengikat.

| Tugas | Sebelum | Sesudah |
|---|---|---|
| **Struktur** — menu ini milik modul mana | seluruh baris peta | **tidak berubah**, tetap seluruh baris |
| **Hibah** — role membuka halaman apa | seluruh baris peta | hanya baris `Flag_Hibah_Role = 'Y'` |

## Kenapa muncul sekarang

Permintaan user 2026-09-16: "kan disini ada 2 role yang berbeda yaitu tamu dan
permit, nah disini saya ingin benaran di pisah saja."

Pemisahannya sudah dibuat berlapis-lapis — halaman dipecah (`DML-2026-105`),
role dipecah (`DML-2026-108`), keanggotaan dan kunci halaman diberikan per orang
(`DML-2026-109`) — tetapi **tidak satu pun mengikat**. `ADMIN_PERMIT` dan
`ADMIN_TAMU` sama-sama menunjuk grup fitur `VISITOR`, dan grup itu memetakan
seluruh 16 halaman Visitor. `User::memilikiAksesBaru()` meluluskan lewat jalur
role→grup lebih dulu, sehingga memegang salah satu role membuka semuanya. Kunci
per orang hanya MENAMBAH; tak ada yang bisa membatasi.

## Kenapa tidak cukup DML

Rencana awal — menghapus baris peta Visitor agar kunci per orang jadi otoritas —
**tidak bisa dijalankan**, dan itu baru ketahuan setelah menelusuri pemakainya.
`InertiaShellService::fetchMappedMenuRows()` mem-JOIN tabel yang sama untuk
menyusun sidebar: menu tanpa baris peta tidak dapat muncul sama sekali, bahkan
bagi orang yang punya kunci halamannya. Menghapus barisnya bukan mencabut
hibahnya, melainkan melenyapkan seluruh menu Visitor untuk semua orang.

Satu tabel, dua maksud. Kolom penanda memisahkannya tanpa menyentuh strukturnya.

## Keputusan desain

**Bawaannya `'Y'`.** Seluruh 113 baris yang sudah ada — ESS, KPI, LMS, DMS,
SYSTEM, BUILDER — berperilaku persis seperti sebelumnya. Perubahan yang terasa
di modul yang tidak diminta berubah adalah kerusakan, bukan efek samping.

**CHECK constraint `IN ('Y','T')`.** Nilai ketiga yang masuk lewat jalur lain
akan diperlakukan kueri hibah sebagai "bukan Y" — yaitu mencabut akses diam-diam,
kegagalan yang tidak menyalakan galat apa pun.

**Penyaringnya dipasang di DUA tempat sekaligus.** `User::memilikiAksesBaru()`
menentukan hak, `InertiaShellService` menentukan apa yang tampil. Keduanya
menjawab pertanyaan yang sama dan bentuk kueriny identik; yang berbeda di antara
keduanya menghasilkan menu yang dibalas 403, atau hak atas menu yang tak pernah
tampil. Dijaga `HibahRoleSatuPenyaringTest`.

> **Koreksi 2026-09-18.** Pernyataan di atas keliru: sidebar tidak membaca
> `InertiaShellService::getAccessiblePages()` (pemanggilnya sudah dikomentari),
> melainkan `AuthorizationScopeResolver::getUsablePageTypes()` lewat
> `menuIsVisible()` → `canAccessPage()`. Resolver itu tertinggal tanpa penyaring,
> sehingga di production pemegang ADMIN_PERMIT tetap melihat seluruh menu Visitor
> walau datanya sudah benar. Penyaringnya ditambahkan di branch
> `fix/system/sidebar-hibah-role`, dan uji penjaganya kini juga memeriksa resolver.
>
> Catatan production: kode grup Visitor di sana `VMS`, bukan `VISITOR`, sehingga
> langkah 2 `data-change.sql` mengubah 0 baris. Hibahnya dicabut manual dengan
> `UPDATE ... WHERE Id_Grup_Fitur = 4` (13 baris) dan belum dicatat sebagai
> change tersendiri.

**Kunci dilengkapi SEBELUM hibah dicabut.** Andre dan Imam hari itu hanya punya
satu kunci eksplisit — verifikator domainnya — dan sisanya datang dari hibah
grup. Urutan terbalik membuat keduanya kehilangan seluruh menu Visitor di antara
dua langkah.

**`visitorPermitPage` dan `visitorTamuPage` tidak disentuh.** Keduanya menu
GLOBAL (`N_HRIS_Menus.Is_Global_Menu = 'Y'`), jadi pengajuan permit dan tamu
tetap terbuka untuk seluruh karyawan tanpa bergantung pada grup maupun kunci.

**Halaman warisan ikut dicabut hibahnya** — `visitorVerifikatorPage`,
`visitorLaporanPage`, `visitorExportStudioPage`, `visitorMasterDataPage`,
`visitorApprovalPage`. Semuanya pengalihan ke halaman domain; membiarkannya
memberi hibah berarti pintu belakang ke endpoint lama bagi orang yang domainnya
bukan itu.

**Menu "Approval Permit (Lama)" disembunyikan, bukan dihapus.** Seluruh
mutasi `visitorApprovalPage` sudah dipensiunkan (410) dan halamannya hanya
mengalihkan ke meja verifikator, jadi yang tersisa cuma kekusutan di sidebar.
Barisnya tetap ada: menghapusnya memutus baris `N_HRIS_Grup_Fitur_Map` dan
`HRIS_Page_Access` yang menunjuknya, dan bookmark lama berhenti mengalihkan —
padahal pengalihan itulah satu-satunya gunanya sekarang.

## Tab Keanggotaan pada Menu & Matrix Visitor

Tidak satu pun role Visitor diberi `tab/membership` di
`N_HRIS_Role_Resource_Access` — tabnya memang tidak berlaku di sana. Yang membuat
ia tetap terlihat adalah pintasan System Admin pada
`ApprovalMatrixService::buildMatrixCapabilities()`:
`$isSystemAdmin || $check(...)` melewati seluruh pemeriksaan.

Ditambahkan daftar `TAB_TAK_BERLAKU` per grup yang dihormati **bahkan oleh
pintasan itu**. Bedanya penting: pintasan System Admin menjawab "berhak melihat
segalanya", bukan "melihat hal yang tidak ada". Keanggotaan role Visitor
diberikan lewat change basis data yang tercatat, bukan lewat layar — tab yang
tetap dirender hanya mengundang orang mengubahnya dari tempat yang tidak
meninggalkan jejak.

## Status

Development: **DONE** (2026-09-16, `Web_HRIS`).

Diverifikasi dari **menu yang benar-benar terlihat**, bukan dari isi tabel:

| Orang | Role | Menu Visitor yang tampil |
|---|---|---|
| Andre | `ADMIN_PERMIT` | Permit Kendaraan, Kunjungan Tamu, **Verifikator/Monitoring/Export Studio/Master Data — Permit** |
| Imam | `ADMIN_TAMU` | Permit Kendaraan, Kunjungan Tamu, **Verifikator/Monitoring/Export Studio/Master Data — Tamu** |
| Ridho | keduanya | kedua domain |
| Arya | semua | kedua domain + Menu & Matrix |

Menu "Approval Permit (Lama)" tidak lagi tampil pada siapa pun; jumlah menu Arya
turun 12 → 11 dan Ridho 11 → 10.

Tab Keanggotaan pada grup VISITOR: `canView = false`.

Uji sudah ditulis tetapi **belum dijalankan** atas permintaan user.

## Berkas

| Peran | Path |
|---|---|
| DDL | `database/ddl/DDL-2026-110-hibah-role-per-halaman/forward.sql` |
| DROP | `database/ddl/DDL-2026-110-hibah-role-per-halaman/drop.sql` |
| DML | `database/dml/DDL-2026-110-hibah-role-per-halaman/data-change.sql` |
| Kode | `User::memilikiAksesBaru()`, `AuthorizationScopeResolver::getUsablePageTypes()`, `InertiaShellService::mappedPagesForUser()`, `ApprovalMatrixService::buildMatrixCapabilities()` |

## Pekerjaan user

- Merekam change ini di SQL Tracker bersama `DML-2026-105`…`109`, lalu
  mengeksekusi ke production. **Urutannya wajib**: `forward.sql` dulu, baru
  `data-change.sql` — DML-nya menulis kolom yang dibuat DDL-nya.
- **Periksa `UserID_Web` di production sebelum menjalankan DML.** Penjaga di
  dalam skrip menolak `Jenis_page` yang tidak terdaftar, tetapi tidak bisa
  mendeteksi id orang yang kebetulan valid namun milik orang lain.
