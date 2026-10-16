# Change: DML-2026-112-selaraskan-label-menu-pa

## Deskripsi
Penyelarasan nama menu (`Nama_Menu`), subtitle deskriptif (`Menu_Subtitle`), icon (`Icon_Menu`), urutan tampilan (`Sort_Menu`), dan visibilitas sidebar (`Is_Show_Sidebar`) untuk seluruh menu modul Performance Appraisal pada tabel `N_HRIS_Menus`.

Penyelarasan ini memastikan:
1. Label Master Data tetap mempertahankan prefix `Master · [Nama Menu]`.
2. Urutan menu mengikuti alur logis administrasi PA dari awal hingga akhir:
   - Fondasi Master Data (1..7)
   - Pelaksanaan Siklus (`Assessment Cycle`) (8)
   - Pemantauan & Verifikasi (`Pemantauan & Verifikasi`) (9)
   - Perhitungan & Penerbitan Hasil (`Hasil & Penerbitan`) (10)
   - Kalibrasi Komite (`Kalibrasi Grade`) (11)
   - Analisis Talenta (`9-Box HAV Map`) (12)
   - Analisis Sebaran Korporat (`Distribusi Performance`) (13)
   - Audit & Jejak Multi-tahun (`Riwayat Penilaian`) (14)
   - Pusat Komando Eksekutif (`Dashboard PA`) (15)

## Riwayat Revisi
| Revisi | Tanggal | Pemicu / Catatan Perubahan | Checksum SHA-256 |
|---|---|---|---|
| r1 | 2026-09-07 | Draf awal penyelarasan label dan switcher | `c30f4ee3fae0a7f14b6279f0fc31c8ad480d1964ee718bfcb54d762086e3f2ff` |
| r2 | 2026-09-07 | Mempertahankan prefix `Master · ` pada master data dan menyusun urutan alur kerja dari Master -> Cycle -> Monitoring -> Hasil -> Kalibrasi -> 9-Box -> Distribusi -> Riwayat -> Dashboard | `1db2dc026eb6e52534460c43ad2646d3727f28b28688dcd02844b1873a99f065` |
| r3 | 2026-09-07 | Penyesuaian label menu `Master · Syarat per Level` menjadi `Master · Matriks Kompetensi` agar lebih baku dan intuitif sesuai terminologi standar HR | `c7f5d425dc99719a2803ced24a7759601354cc21d5327326f9264ac46a27df4f` |
| r4 | 2026-09-07 | Penyesuaian label menu `Master · Aturan Penilai` menjadi `Master · Komposisi Penilai` agar tidak tertukar dengan bobot pilar dan lebih representatif terhadap kebijakan rater 360° | `7816226511bb60920618f18b441d463602cede54e4f78d6f1809ded88e5e99c8` |
| r5 | 2026-09-07 | Penyesuaian label menu `Pemantauan & Verifikasi` menjadi `Monitoring & Verifikasi` | `64f79ee5d15ead6b768dcbee14d9d7d3b3818dbfceaa4d4c3ed6703ff4986d0c` |

## Target Database
- Environment: `development`
- Database: `Web_HRIS`
- Tabel Terdampak: `N_HRIS_Menus`

## Artifacts
- DML: `database/dml/DML-2026-112-selaraskan-label-menu-pa/data-change.sql`
- Checksum SHA-256 (r5): `64f79ee5d15ead6b768dcbee14d9d7d3b3818dbfceaa4d4c3ed6703ff4986d0c`

## Status Workflow
- Development: `DONE`
- SQL Tracker: `PENDING`
- Production: `PENDING`
- Verification: `DEV_DONE`