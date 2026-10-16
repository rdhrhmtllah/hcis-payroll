# DML-2026-044 — Register active Visitor menus

## Ringkasan

| Item | Nilai |
| --- | --- |
| Change ID | `DML-2026-044-register-visitor-active-menus` |
| Jenis | DML (murni penambahan) |
| Tujuan | Mendaftarkan 7 menu Visitor aktif di database yang belum memilikinya (target: produksi) |
| Artifact | `database/dml/DML-2026-044-register-visitor-active-menus/data-change.sql` |
| Dokumentasi | `docs/changes/DML-2026-044-register-visitor-active-menus/README.md` |
| Target development | `Web_HRIS` @ team311 (sumber kebenaran isi menu) |
| Status | DRAFT — belum dieksekusi di mana pun |

## Basis verifikasi database

Isi skrip diturunkan dari pembacaan langsung `Web_HRIS` (development) pada
**2026-08-28**, bukan dari snapshot dokumentasi:

- `N_HRIS_Master_Grup_Fitur`: grup `VISITOR` aktif, `Id_Grup_Fitur = 4` di dev.
- `N_HRIS_Menus` × `N_HRIS_Grup_Fitur_Map`: 8 halaman terpetakan ke grup VISITOR
  dengan `Flag_Aktif = 'Y'`; 7 di antaranya didaftarkan oleh skrip ini.

## Objek yang disentuh

| Tabel | Operasi | Ekspektasi baris |
| --- | --- | --- |
| `N_HRIS_Menus` | INSERT | 7 (0 bila sudah ada) |
| `N_HRIS_Grup_Fitur_Map` | INSERT | 7 (0 bila sudah ada) |
| `N_SYSTEM_Database_Change` | INSERT | 1 (dilewati bila tabel tidak ada) |

## Menu yang didaftarkan

| Header | Sort | Menu | `Jenis_page` | URL |
| --- | --- | --- | --- | --- |
| Visitor (4) | 10 | Permit Kendaraan | `visitorPermitPage` | `/transaksipermit` |
| Visitor (4) | 20 | Kunjungan Tamu | `visitorTamuPage` | `/transaksitamu` |
| Administrator Visitor (10) | 10 | Master Data Visitor | `visitorMasterDataPage` | `/admin/visitor/master-data` |
| Administrator Visitor (10) | 20 | Verifikator Visitor | `visitorVerifikatorPage` | `/verifikator-visitor` |
| Administrator Visitor (10) | 30 | Laporan Visitor | `visitorLaporanPage` | `/laporanvisitor` |
| Administrator Visitor (10) | 40 | Export Studio Visitor | `visitorExportStudioPage` | `/visitor/export-studio` |
| Administrator Visitor (10) | 90 | Menu & Matrix | `menuApprovalMatrixVisitorPage` | `/admin/visitor/menu-approval-matrix` |

## Yang sengaja tidak disertakan

- **`visitorApprovalPage`** (`/approvalvisitor`, "Approval Permit (Lama)").
  Masih `Flag_Aktif = 'Y'` di dev, tetapi subtitle-nya sendiri menyatakan
  deprecated dan halamannya mengarah ke Verifikator Visitor. Keputusan
  pengguna pada 2026-08-28: tidak didaftarkan.
- **`HRIS_Page_Access`**. `UserID_Web` produksi berbeda dengan dev, jadi hak
  akses per user/level/divisi diatur setelah deploy melalui halaman
  **Menu & Matrix**. Tanpa baris `HRIS_Page_Access`, menu tetap terlihat oleh
  pemegang role grup VISITOR — pembatasan yang benar-benar mengikat hanya ada
  di jalur `HRIS_Page_Access`.

## Keputusan teknis

- `Id_Menu` adalah kolom IDENTITY dan tidak diisi manual. Nomor di produksi akan
  berbeda dengan dev dan itu tidak masalah: aplikasi memakai `Jenis_page`.
- `Id_Grup_Fitur` **tidak** di-hardcode `4`. Skrip meresolusinya dari
  `Kode_Grup_Fitur = 'VISITOR'` dan gagal dengan `THROW 50044` bila grup itu
  belum ada, supaya menu tidak pernah nyasar ke modul lain.
- `Kode_Perusahaan` diisi dari variabel `@Kode_Perusahaan` (default `'001'`).
  Ubah nilainya bila kode perusahaan di target berbeda.
- Idempoten: setiap INSERT dijaga `NOT EXISTS`, jadi menjalankan ulang tidak
  membuat duplikat.

## Setelah eksekusi

Menu di-cache oleh `InertiaShellService`. Setelah baris masuk, jalankan
`flushMenuCache` (atau bersihkan cache aplikasi) di target agar menu baru
muncul tanpa menunggu kedaluwarsa cache.

Kueri verifikasi ada di bagian akhir skrip: harus mengembalikan 7 baris dengan
`Map_Aktif = 'Y'`.

## Sisa pekerjaan pengguna

1. Review dan approve isi `data-change.sql`.
2. Jalankan melalui SQL Tracker ke produksi.
3. Bersihkan cache menu di produksi.
4. Atur hak akses per user melalui halaman Menu & Matrix.
5. Verifikasi hasil dan perbarui status di `database/ddl/status.json`.
