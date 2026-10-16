# DDL-2026-120-pa-grade-kurva

## Metadata

- **Change ID**: `DDL-2026-120-pa-grade-kurva`
- **Target Database**: `Web_HRIS`
- **Type**: DDL (+ DML pasangan `DML-2026-125-batas-z-grade-dan-menu-grup-kurva`)
- **Revision**: `r1`
- **Forward Checksum (SHA-256)**: `398894190ee75312bf2833e5da5bd6ed963bc749b770761f198b3991ffa7127c`
- **DML Checksum (SHA-256)**: `11d9209101d89db251b76cba0e912032762538c92d0b1a36498f225e15abbddd`
- **Affected Objects**: `N_PA_Score_Range (Batas_Z)`, `N_PA_Cycle_Score_Range_Snapshot (Batas_Z)`,
  `N_PA_Cycle (Metode_Grade)`, `N_PA_Result (PA_Cycle_Curve_Id)`, `N_PA_Curve_Group`,
  `N_PA_Curve_Group_Level`, `N_PA_Cycle_Curve_Group`, `N_PA_Cycle_Curve_Group_Level`,
  `N_PA_Cycle_Curve`, `N_PA_Cycle_Curve_Band`, `N_SYSTEM_Database_Change`
- **Status**: `TRACKER_PENDING`
- **Rancangan**: `docs/superpowers/specs/2026-09-18-pa-grade-kurva-normal-design.md`
- **Rencana**: `docs/superpowers/plans/2026-09-18-pa-grade-kurva-normal.md`

## Deskripsi

Grade sistem Performance Appraisal tidak lagi lahir dari pita nilai tetap, melainkan dari
**z-score per grup level** yang dikunci per siklus. Master pita grade menyimpan batas bawah z
(`Batas_Z`); master baru **Grup Level Kurva** menentukan level mana yang dibandingkan dalam
satu kurva. Keduanya disalin per siklus. Saat admin mengunci kurva, rata-rata (μ), simpangan
baku sampel (σ), dan pita absolut (μ + z·σ) tiap grup dibekukan di `N_PA_Cycle_Curve(_Band)`.

## Keputusan fisik

- **`Metode_Grade` DEFAULT `'PITA'`** — menyimpang sadar dari spec §4.2 (yang menyebut DEFAULT
  `'KURVA'` + DML). DEFAULT `PITA` mengisi seluruh siklus lama saat kolom ditambahkan, tanpa DML
  dan tanpa jeda di mana aplikasi baru melihat siklus lama sebagai KURVA. Siklus baru diset
  `KURVA` oleh `PaCycleSetupService`. Dijaga `CK_PA_Cycle_Metode_Grade`.
- **Kurva hanya ditambah dan dinonaktifkan.** Membuka kunci menonaktifkan baris dan mencatat
  waktu, pelaku, alasan (`CK_PA_Cycle_Curve_Buka` menuntut keduanya); mengunci ulang membuat
  baris baru. Riwayat penguncian utuh.
- **Satu kurva aktif per grup** (`UX_PA_Cycle_Curve_Grup_Aktif`, tersaring `Flag_Aktif = 'Y'`).
- **Satu level satu grup aktif** di master dan di salinan siklus (indeks unik tersaring).
- **Batas pita 4 desimal** (`decimal(9,4)`): nilai akhir disimpan 2 desimal, dan membulatkan
  batas ke 2 desimal bisa menjatuhkan nilai yang tepat di tepi ke grade sebelah.
- **`ID_Level bigint` ber-FK ke `HRIS_Level (PK_Level)`.**

## Artefak

- `database/ddl/DDL-2026-120-pa-grade-kurva/forward.sql`
- `database/ddl/DDL-2026-120-pa-grade-kurva/drop.sql` (manual; tidak dijalankan agen)
- `database/dml/DML-2026-125-batas-z-grade-dan-menu-grup-kurva/data-change.sql`

## Isi awal (DML-2026-125)

Keputusan pemilik proyek 18 September 2026 (G8): enam grade selebar satu σ, μ = batas bawah B —
A +2 · B_PLUS +1 · B 0 · C_PLUS −1 · C −2 · D tanpa batas. Hanya mengisi yang masih NULL.
Halaman `PaMasterGrupKurvaPage` (`/performance-appraisal/master/grup-kurva`) di grup menu
`pa_master` Sort_Menu 10, grup fitur KPI, akses dua user pengembang (pola DML-2026-085).
**Grup level awal tidak diisi** — keputusan bisnis, diatur admin lewat layar.

## Verifikasi development

**Web_HRIS @ instance-20240809-031237 — 18 September 2026**, sasaran diverifikasi lewat
`SELECT DB_NAME()` + `@@SERVERNAME` sebelum batch pertama.

- **Sebelum:** 0 dari 6 tabel kurva, 0 dari 4 kolom baru, ledger `DDL-2026-120` kosong.
- **`forward.sql`:** 19 batch OK → 6 tabel, 4 kolom, ledger 1. **Putaran kedua:** no-op.
- **DML:** Batas_Z A 2, B_PLUS 1, B 0, C_PLUS −1, C −2, D NULL; menu/map/akses 1/1/2.
  **Putaran kedua:** tidak berubah. `InertiaShellService::flushMenuCache()` dipanggil.
- Seluruh siklus yang ada `Metode_Grade = 'PITA'` (0 selain itu).

## Sisa pekerjaan user

- Meninjau dan menyetujui `forward.sql` dan `data-change.sql`.
- Mencatat keduanya ke SQL Tracker dengan checksum di atas.
- Menjalankan ke production lewat SQL Tracker, lalu memanggil pembersih cache menu.
- Menyusun grup level kurva di layar master sebelum siklus kurva pertama dikunci.
