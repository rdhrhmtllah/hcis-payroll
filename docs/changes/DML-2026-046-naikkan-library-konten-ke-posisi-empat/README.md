# DML-2026-046 — Penyesuaian Posisi Menu Library Konten Sebelum Master Dokumen & Builder

**Target development:** `Web_HRIS` @ team311 · **Status:** DEV_DONE · SQL Tracker PENDING · Production PENDING

## Alasan Perubahan

Dalam alur kerja penyusunan dokumen, materi/blok konten, aset lampiran, dan template standar yang disimpan di **Library Konten** perlu disiapkan terlebih dahulu sebelum admin melakukan registrasi **Master Dokumen** dan perakitan pada **Document Builder**.

Oleh karena itu, posisi menu `Library Konten` dinaikkan ke urutan ke-4 (tepat setelah Master APQC).

---

## Urutan Menu Administrator DMS Terbaru

| Sort | `Jenis_page` | URL | Nama Menu | Keterangan Alur Kerja |
|---|---|---|---|---|
| 1 | `menuSopJenisDokumenPage` | `/sop/admin/jenis-dokumen` | **Jenis Dokumen** | Setup hirarki mutu dokumen (Kebijakan s/d Formulir) |
| 2 | `menuSopKategoriPage` | `/sop/admin/kategori` | **Kategori Dokumen** | Setup kategori & taksonomi |
| 3 | `menuSopApqcPage` | `/sop/admin/apqc` | **Master APQC** | Setup klasifikasi proses bisnis standar APQC |
| 4 | `menuSopKontenPage` | `/sop/admin/konten` | **Library Konten** | **Setup bahan/aset isi, lampiran, & template materi** |
| 5 | `menuSopDokumenPage` | `/sop/admin/dokumen` | **Master Dokumen** | Registrasi identitas master & nomor dokumen |
| 6 | `menuSopBuilderPage` | `/sop/admin/builder` | **Document Builder** | Perancangan versi dokumen menggunakan konten library |
| 7 | `menuSopPenjadwalanPage` | `/sop/admin/penjadwalan` | **Penjadwalan Dokumen** | Penjadwalan penugasan & sosialisasi |
| 8 | `menuSopKebijakanPage` | `/sop/admin/kebijakan` | **Otomasi Dokumen** | Aturan penugasan otomatis sistem |
| 9 | `menuSopMonitoringPage` | `/sop/admin/monitoring` | **Monitoring Dokumen** | Pemantauan kepatuhan & status pembacaan |
| 10 | `menuSopExportStudioPage` | `/sop/admin/export-studio` | **Export Studio** | Studio pembuatan laporan kustom & ekspor data audit |
| 11 | `menuSopSetupSystemPage` | `/sop/admin/setup-system` | **Kebijakan Global Dokumen** | Pengaturan guardrail toleransi & cron scheduler |

---

## Eksekusi & Verifikasi Development

- Target: `Web_HRIS`
- Eksekusi DML via SQL Server Transaction (ACID compliant).
- `InertiaShellService::flushMenuCache()` dijalankan.
- Verifikasi pembacaan menu di browser membuktikan urutan menu dari atas ke bawah 100% konsisten.

## Tugas User Selanjutnya

1. Review urutan menu di antarmuka (HMR aktif).
2. Daftarkan dan jalankan forward DML (`database/dml/DML-2026-046-naikkan-library-konten-ke-posisi-empat/data-change.sql`) ke staging/production via SQL Tracker.
