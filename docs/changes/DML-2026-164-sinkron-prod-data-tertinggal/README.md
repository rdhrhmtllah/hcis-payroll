# DML-2026-164-sinkron-prod-data-tertinggal — Data DML yang belum ada di production

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-164-sinkron-prod-data-tertinggal` |
| Jenis | DML (satu berkas, satu transaksi, idempoten) |
| Target | production `emi_db_web_hr` |
| Artifact | `database/dml/DML-2026-164-sinkron-prod-data-tertinggal/data-change.sql` (sha256 `c327b9bb53684c119f0384d571087988ac61c85921879b7c40c2c62926d11587`) |
| Prasyarat | tidak ada (semua tabel/kolom sasaran sudah ada di production) |
| Status | `REVIEW_REQUIRED` |

## Metode (2026-10-06, baca saja)

Setiap DML berstatus production `PENDING` di ledger, ditambah bagian data dari change DDL
(`database/dml/DDL-*`), diperiksa efeknya di production: isi tabel konfigurasi dibandingkan
dengan dev per kunci alami, dan predikat `WHERE` tiap UPDATE dihitung sisanya. Hasil:
sebagian besar sudah ada di production (ledger saja yang tertinggal); berkas ini hanya memuat
yang terbukti belum ada. Dikompilasi di dev dengan `SET NOEXEC ON` — lolos, tidak dieksekusi.

## Isi dan perkiraan baris di production

| Bagian | Asal | Sasaran | Baris |
|---|---|---|---|
| 1 | DML-075 bag. 1–3, DDL-079, DDL-102, DML-080, DML-143 | `N_HRIS_Api_Endpoint` / `_Aplikasi` / `_Akses_Endpoint` (ketiganya kosong di production) | 9 / s.d. 4 / s.d. 9 |
| 2 | DDL-2026-093 bagian data | `KPI_Update_Performance.Metode_Cap` NULL | 75 `PARAMETER` + 198 `TOTAL` |
| 3 | DML-2026-147 | peta `systemPanduanPage` menunjuk Id grup 5 (Id dev, tidak ada di production) → grup SYSTEM | 1 |
| 4 | DML-2026-057, 058 | peta `menuSopPrioritasPage` (baru), peta & hak akses `menuSopKategoriPage` dicabut | 1 + 1 + 1 |
| 5 | DML-2026-046 | `Sort_Menu` Administrator DMS | 5 |
| 6 | temuan | `menuSopKebijakanPage.Url_Menu` `/sop/admin/setup-system` → `/sop/admin/kebijakan` | 1 |

**Kunci API** diisi saat eksekusi di BAGIAN 0, tidak pernah ditulis ke repo. Aplikasi yang
kuncinya dibiarkan placeholder dilewati dengan `PRINT`; bagian lain tetap jalan dan berkas bisa
dijalankan ulang setelah kuncinya ada. HC Learn tidak butuh kunci baru — disalin dari
`N_LMS_Api_Aplikasi` "HUMAN CAPITAL LEARNING" (di dev pasangan kuncinya identik). Semua grup
dicari lewat `Kode_Grup_Fitur`, bukan Id.

## Sengaja TIDAK dimasukkan

- **DML-2026-130** — 2 baris `N_VISITOR_Permit_Efektif` `EXPIRED` + `Flag_Batal='Y'`
  (PMT1026-0001, PMT1026-0003) adalah tulisan sah `VisitorEfektifService::akhiriAksesInspeksi()`
  (akses inspeksi diakhiri = batal bagi palang). Menjalankan DML-130 akan merusaknya.
- **DML-2026-013** — 1 versi FORM PUBLISHED (Id 214) masih punya 2 baris pengesah. Pembersihan ini
  menghapus data dan dulu diputuskan untuk dev; butuh keputusan tersendiri untuk production.
- **DDL-2026-127** — 3 permit tanpa `Kode_Kartu` semuanya dibuat 6 Okt dan belum di-approve; bukan sisa.
- **DML-2026-098, 099, 134, 139** — tidak berlaku: konfigurasi scheduler production berbeda,
  perintahnya tidak ada, pola inspeksi disusun SHE sendiri, permit sasaran tidak ada.
- **DML-2026-002, 010, 011** — pencatatan `N_SYSTEM_Database_Change` saja.
- `POST /api/v1/pa/third-party/penilaian/complete` — milik Performance Appraisal yang belum rilis.

## Tugas user

1. Tinjau berkas; isi kunci di BAGIAN 0 (atau biarkan placeholder untuk melewati aplikasinya).
2. Catat di SQL Tracker persis isi berkas, jalankan di production.
3. Periksa hasil blok VERIFIKASI: `Metode_Cap belum terisi` = 0; `systemPanduanPage` di grup SYSTEM;
   `menuSopPrioritasPage` aktif di DMS; urutan menu Administrator DMS 1–11.
4. Serahkan pasangan kunci Web Careers / mitra / cek kartu ke pihaknya lewat jalur rahasia.
