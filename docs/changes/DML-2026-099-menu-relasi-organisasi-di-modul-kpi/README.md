# DML-2026-099 — Menu Relasi Organisasi di modul KPI

| | |
|---|---|
| Change ID | `DML-2026-099-menu-relasi-organisasi-di-modul-kpi` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-099-menu-relasi-organisasi-di-modul-kpi/data-change.sql` |
| Bergantung pada | DML-2026-097, DML-2026-098 |
| Perubahan kode penyerta | `routes/SYSTEM/Organization/RelasiOrganisasi.php` (gerbang menerima dua kode halaman) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang diminta

Butir menu untuk admin PA di header modul **KPI**, supaya ia tidak perlu turun ke modul
SYSTEM. Sebelumnya butirnya hanya ada di SYSTEM › Master Data — dan bagi admin PA modul itu
berisi **satu** butir saja, di tempat yang tidak ia kunjungi.

## Akibat aksesnya: diputuskan, bukan terselip

Akses role diturunkan dari `N_HRIS_Grup_Fitur_Map` — tabel yang sama yang menentukan menu.
Memetakan halaman ke grup KPI karena itu **memberikan aksesnya kepada setiap pemegang role
KPI**, sekarang lima orang dan siapa pun yang ditambahkan kelak.

Itu diuji, bukan diduga: satu baris pemetaan mengubah pemegang role KPI dari 403 menjadi
lolos seketika. Tidak ada cara memisahkan "tampil di menu KPI" dari "diberikan ke role KPI"
lewat tabel ini. Konsekuensinya disampaikan dan diterima.

**Jebakan yang menyertainya:** `Flag_Aktif = 'T'` pada baris pemetaan menyembunyikan menunya
tetapi **tidak** mencabut aksesnya — kueri akses tidak menyaring kolom itu. Yang mencabut
adalah **menghapus** barisnya.

## Kenapa butir baru, bukan pemetaan kedua atas butir yang ada

Sidebar mengelompokkan butir menurut **kode** header, bukan labelnya. Butir SYSTEM ber-kode
`master_data`, sedangkan header Master Data milik KPI ber-kode `pa_master`. Memetakan butir
SYSTEM ke grup KPI karena itu melahirkan grup "Master Data" **kedua** di dalam modul KPI —
label sama, isi berbeda. Sudah terlihat saat diuji.

Butir baru ber-`Header_Code = 'pa_master'` masuk ke grup Master Data yang sudah ada, sebagai
butir kesembilan sesudah Paket Soal Baku.

`Jenis_page` unik di `N_HRIS_Menus`, dan join menu→pemetaan memakai kolom itu, jadi butir baru
wajib punya kode halaman sendiri: `PaMasterRelasiOrganisasiPage`.

## Alamatnya sama, dan itu disengaja

Kedua butir menunjuk `/system/organisasi/relasi`. Satu halaman, satu sumber kebenaran.
Konsekuensinya System Admin melihatnya **dua kali** — SYSTEM › Master Data dan KPI › Master
Data. Diterima; kalau kelak diputuskan satu saja, yang dicabut butir SYSTEM-nya.

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`, dan **wajib** dipasangkan dengan
perubahan kode gerbangnya. Menjalankan DML ini tanpa perubahan kode menghasilkan menu yang
tampil lalu menolak dengan 403.

## Verifikasi di dev

Menu dibangun lewat pembangun yang sama dengan yang dipakai halaman:

| Pengguna | KPI › Master Data | SYSTEM › Master Data | Gerbang |
|---|---|---|---|
| NOIR (sysadmin) | 9 butir, termasuk Relasi Organisasi | 3 butir | LOLOS |
| aji (admin PA) | 9 butir, termasuk Relasi Organisasi | 1 butir | LOLOS |
| tari / Haris / iqbal (role KPI) | 9 butir, termasuk Relasi Organisasi | — | LOLOS |
| karyawan tanpa hak | — | — | 403 |

Grup "Master Data" di dalam modul KPI tetap **satu** (bukan dua) — yang membedakannya dari
percobaan pemetaan ulang yang sengaja tidak dipakai.
