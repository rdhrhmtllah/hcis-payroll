# Kesiapan UAT Modul SOP — 14 August 2026

Dihasilkan `php artisan sop:uat-laporan` dari hasil uji otomatis.
**Dokumen UAT tidak ditimpa** — kolom Hasil Aktual dan Status di sana tetap
diisi penguji saat sesi berlangsung. Berkas ini dibaca SEBELUM sesi, untuk
mengetahui skenario mana yang sudah terbukti dan mana yang masih harus
dipercaya begitu saja.

| Status | Jumlah | Arti |
|---|---:|---|
| PASS | 46 | terbukti otomatis |
| FAIL | 0 | terbukti gagal — perbaiki sebelum mengundang orang |
| MANUAL | 2 | ada bagian yang tak bisa diotomatiskan; wajib dijalankan manusia |
| BELUM | 0 | ujinya belum ditulis |

Laporan ini **tidak akan pernah 48/48**, dan itu disengaja. Dua skenario
menyentuh sesuatu di luar jangkauan suite: **034** mengerjakan kuis di sistem
ujian HC Learn, dan **036** menulis foto check-in ke penyimpanan awan yang tak
bisa dibereskan pembersih. Langkahnya ada di `plans/sop/uat-panduan-manual.md`.

## Seluruh skenario

| UAT-ID | Modul | Lapis | Kontrol | Status | Uji |
|---|---|---|:-:|:-:|--:|
| UAT-SOP-001 | Hak Akses & Menu SOP | backend |  | **PASS** | 1 |
| UAT-SOP-002 | Hak Akses & Menu SOP | backend | ✓ | **PASS** | 2 |
| UAT-SOP-003 | Master Kategori SOP | backend |  | **PASS** | 1 |
| UAT-SOP-004 | Master Kategori SOP | backend | ✓ | **PASS** | 2 |
| UAT-SOP-005 | Master Konten (Berkas) | backend |  | **PASS** | 2 |
| UAT-SOP-006 | Master Konten (Berkas) | backend | ✓ | **PASS** | 5 |
| UAT-SOP-007 | Master Dokumen Terkendali | backend |  | **PASS** | 1 |
| UAT-SOP-008 | Master Dokumen Terkendali | peramban |  | **PASS** | 2 |
| UAT-SOP-009 | Master Dokumen Terkendali | peramban | ✓ | **PASS** | 1 |
| UAT-SOP-010 | Master Dokumen Terkendali | backend |  | **PASS** | 3 |
| UAT-SOP-011 | Master Dokumen Terkendali | backend |  | **PASS** | 1 |
| UAT-SOP-012 | Master Dokumen Terkendali | backend |  | **PASS** | 1 |
| UAT-SOP-013 | Master Dokumen Terkendali | backend | ✓ | **PASS** | 5 |
| UAT-SOP-014 | Master Dokumen Terkendali | backend |  | **PASS** | 2 |
| UAT-SOP-015 | Builder Paket SOP | backend |  | **PASS** | 3 |
| UAT-SOP-016 | Builder Paket SOP | peramban |  | **PASS** | 2 |
| UAT-SOP-017 | Builder Paket SOP | backend |  | **PASS** | 1 |
| UAT-SOP-018 | Builder Paket SOP | backend |  | **PASS** | 1 |
| UAT-SOP-019 | Builder Paket SOP | peramban |  | **PASS** | 2 |
| UAT-SOP-020 | Penjadwalan & Penugasan | backend |  | **PASS** | 1 |
| UAT-SOP-021 | Penjadwalan & Penugasan | backend |  | **PASS** | 1 |
| UAT-SOP-022 | Penjadwalan & Penugasan | backend | ✓ | **PASS** | 4 |
| UAT-SOP-023 | Penjadwalan & Penugasan | backend |  | **PASS** | 1 |
| UAT-SOP-024 | Kebijakan & Otomasi | backend |  | **PASS** | 2 |
| UAT-SOP-025 | Kebijakan & Otomasi | backend |  | **PASS** | 2 |
| UAT-SOP-026 | Repository SOP | backend |  | **PASS** | 2 |
| UAT-SOP-027 | Repository SOP | backend | ✓ | **PASS** | 2 |
| UAT-SOP-028 | SOP Saya | backend |  | **PASS** | 2 |
| UAT-SOP-029 | SOP Saya | backend |  | **PASS** | 1 |
| UAT-SOP-030 | Membaca & Konfirmasi Dokumen | backend |  | **PASS** | 1 |
| UAT-SOP-031 | Membaca & Konfirmasi Dokumen | peramban |  | **PASS** | 2 |
| UAT-SOP-032 | Membaca & Konfirmasi Dokumen | peramban | ✓ | **PASS** | 1 |
| UAT-SOP-033 | Membaca & Konfirmasi Dokumen | backend | ✓ | **PASS** | 4 |
| UAT-SOP-034 | Kuis / Checkpoint | campuran |  | **MANUAL** | 0 |
| UAT-SOP-035 | Kuis / Checkpoint | backend | ✓ | **PASS** | 4 |
| UAT-SOP-036 | Absensi Check-in | campuran |  | **MANUAL** | 0 |
| UAT-SOP-037 | Absensi Check-in | backend | ✓ | **PASS** | 2 |
| UAT-SOP-038 | Kepatuhan Tim (Leader) | backend |  | **PASS** | 1 |
| UAT-SOP-039 | Kepatuhan Tim (Leader) | backend | ✓ | **PASS** | 2 |
| UAT-SOP-040 | Monitoring & Laporan | backend |  | **PASS** | 2 |
| UAT-SOP-041 | Monitoring & Laporan | backend |  | **PASS** | 2 |
| UAT-SOP-042 | Monitoring & Laporan | backend |  | **PASS** | 1 |
| UAT-SOP-043 | Monitoring & Laporan | backend | ✓ | **PASS** | 1 |
| UAT-SOP-044 | Siklus Hidup Dokumen | backend |  | **PASS** | 1 |
| UAT-SOP-045 | Siklus Hidup Dokumen | backend |  | **PASS** | 2 |
| UAT-SOP-046 | Siklus Hidup Dokumen | backend | ✓ | **PASS** | 3 |
| UAT-SOP-047 | Notifikasi & Rekertifikasi | backend |  | **PASS** | 3 |
| UAT-SOP-048 | Notifikasi & Rekertifikasi | backend |  | **PASS** | 1 |

## Skenario kontrol yang belum terbukti

Seluruh skenario kontrol sudah terbukti otomatis.
