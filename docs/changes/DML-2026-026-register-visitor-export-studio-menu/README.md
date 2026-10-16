# DML-2026-026 — Register Visitor Export Studio as its own page

| Bidang | Isi |
|---|---|
| Change ID | `DML-2026-026-register-visitor-export-studio-menu` |
| Jenis | DML (tanpa perubahan skema) |
| Target development | `Web_HRIS` @ team311 |
| Artefak DML | `database/dml/DML-2026-026-register-visitor-export-studio-menu/data-change.sql` |
| Dokumentasi | `docs/changes/DML-2026-026-register-visitor-export-studio-menu/README.md` |
| Desain | `docs/superpowers/specs/2026-08-25-pisah-monitoring-export-studio-design.md` |
| Status | `DEV_EXECUTED` — SQL Tracker & produksi masih `PENDING` |

## Deskripsi

Export Studio Visitor dipisah dari halaman `/laporanvisitor` menjadi halaman
sendiri di `/visitor/export-studio`, supaya halaman Monitoring punya ruang tumbuh
tanpa menumpuk dengan perakitan laporan.

Halaman Monitoring **mewarisi** URL `/laporanvisitor` dan kunci
`visitorLaporanPage`. Changeset ini karena itu **murni penambahan** — tidak ada
UPDATE dan tidak ada DELETE, sehingga tidak seorang pun dapat kehilangan akses.

## Objek terdampak

| Tabel | Operasi | Baris (development) |
|---|---|---|
| `N_HRIS_Menus` | INSERT | 1 — `visitorExportStudioPage` |
| `N_HRIS_Grup_Fitur_Map` | INSERT | 1 — `Id_Grup_Fitur = 4` (VISITOR) |
| `HRIS_Page_Access` | INSERT…SELECT | 2 — user 624 dan 4 |
| `N_SYSTEM_Database_Change` | INSERT | 1 — pendaftaran changeset |

Hak akses diturunkan **dari data** (pemegang aktif `visitorLaporanPage`), bukan
dari daftar user yang ditulis tangan, supaya tetap benar bila audiensnya berubah
sebelum changeset ini dijalankan ke produksi.

## Verifikasi pada development

Dijalankan 2026-08-25 terhadap `Web_HRIS`; `SELECT DB_NAME()` diverifikasi lebih
dulu. Hasil sama persis dengan yang diperkirakan sebelum eksekusi:

```
N_HRIS_Menus          : 1
N_HRIS_Grup_Fitur_Map : 1
HRIS_Page_Access      : user=4 level=5 divisi=22 aktif=Y
                        user=624 level=10 divisi=20 aktif=Y
visitorLaporanPage    : 2 baris (utuh, tidak disentuh)
```

`InertiaShellService::flushMenuCache()` dijalankan sesudahnya; tanpa itu menu
baru tidak muncul sampai cache kedaluwarsa.

Uji `tests/Integration/VISITOR/Workflow/Reporting/VisitorReportingPageSplitTest.php`
menegaskan ketiga baris ini ada, gerbangnya menolak pemegang kunci lama, dan
kunci lama tidak dicabut.

## Batas yang harus diketahui

Pemisahan izinnya **tidak seutuh namanya**. Baris `N_HRIS_Grup_Fitur_Map` wajib
ada supaya menunya muncul, dan baris itu memberi akses kepada siapa pun pemegang
role grup VISITOR — granularitas role di aplikasi ini memang sebatas grup fitur.
Pemisahan yang benar-benar mengikat hanya ada di jalur `HRIS_Page_Access`
eksplisit. Pada development hari ini tak berdampak (yang lewat role hanya user
624), tapi jangan berasumsi kunci terpisah berarti akses terpisah bagi pemegang
role.

## Sisa pekerjaan pengguna

1. Review changeset ini.
2. Jalankan DML ke SQL Tracker, lalu ke produksi.
3. Verifikasi di produksi: ketiga baris ada, menu Export Studio muncul bagi
   pemegang haknya, dan `/laporanvisitor` tetap terbuka bagi pemegang lama.
4. Jalankan `flushMenuCache()` di produksi setelah DML.
