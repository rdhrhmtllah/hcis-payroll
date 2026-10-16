# DDL-2026-009 — Tabel template laporan lintas modul

Dua tabel baru untuk Export Studio. Tidak ada tabel lama yang diubah, ditambah
kolom, atau disentuh.

| Tabel | Isi |
|---|---|
| `N_HRIS_Report_Template` | Definisi laporan buatan pengguna, sebagai JSON kunci katalog |
| `N_HRIS_Report_Template_Akses` | Sasaran berbagi, hanya untuk `Tingkat_Bagi = 'DIBAGIKAN'` |

## Kenapa `N_HRIS_`, bukan `N_SOP_`

Fitur ini dikembangkan untuk SOP lebih dulu, tapi mesinnya lintas modul sejak
awal. Seluruh infrastruktur lintas modul di repo ini memakai `N_HRIS_`:
`N_HRIS_Async_Tasks` (yang juga dipakai job Studio, dan sudah dibagi tiga
modul), `N_HRIS_Cloud_Task_Log`, `N_HRIS_Authorization_Audit`,
`N_HRIS_Master_Grup_Fitur`. Prefix `N_SOP_`, `N_LMS_`, dan `N_KPI_` menandai
kepemilikan satu domain — nama yang akan berbohong begitu modul kedua menulis
barisnya ke sini.

Kolom `Module` yang membedakan pemilik baris, dan **nilainya selalu diisi dari
route, tak pernah dari badan permintaan**. Bila modul boleh dikirim pengguna,
satu query yang lupa menyaring langsung membocorkan daftar template modul lain.

## Kenapa tanpa CHECK constraint

`Mode`, `Tingkat_Bagi`, dan `Dataset_Key` adalah daftar nilai bisnis yang akan
berkembang seiring dataset dan modul bertambah.
`docs/database/table-design-rules.md` melarang mengunci daftar semacam itu di
database; validasinya ada di backend (`DefinisiValidator`, `DatasetRegistry`).

`ISJSON(Definisi_Json)` tetap dipasang karena itu integritas struktur, bukan
daftar nilai bisnis.

## Template sistem tidak menempati baris

Tujuh laporan resmi SOP didefinisikan di kode, bukan di-seed sebagai baris.
Alasannya: tidak ada drift antara development dan production, isinya ikut code
review, dan "Duplikat & ubah" jadi sekadar salin kode ke baris milik pengguna.

## Status

Kedua tabel diverifikasi ada di `Web_HRIS` (development) pada 2026-08-18 lewat
`sys.columns` dan `sys.indexes`: `N_HRIS_Report_Template` 17 kolom / 4 indeks,
`N_HRIS_Report_Template_Akses` 7 kolom / 3 indeks, keduanya 0 baris. DDL-nya
idempoten (`IF NOT EXISTS`), aman dijalankan ulang.

Belum masuk SQL Tracker dan belum dijalankan di production.

Dokumentasi tabel: `docs/database/tables/hris/n-hris-report-template.md` dan
`docs/database/tables/hris/n-hris-report-template-akses.md`
Desain lengkap: `docs/superpowers/specs/2026-08-18-export-studio-design.md`
