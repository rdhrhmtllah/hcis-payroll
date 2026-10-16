# DML-2026-139-visitor-nonaktifkan-permit-tanpa-proyeksi

## Ringkas

Menonaktifkan **satu** permit COKLAT mitra, `PMT0926-0012`. Permit ini disetujui oleh C9 pada 14 Sep 2026 pukul 09:50 **tanpa jendela berlaku**, di hari yang sama sebelum commit `ec786d63` mewajibkan inspeksi dan jendela sebelum approve. Karena tidak pernah punya baris `N_VISITOR_Permit_Efektif`, tidak ada akses gerbang yang bisa dicabut. Celah #7 di `plans/visitor/2026-09-26-vms-celah-alur-sisa.md`, dengan keputusan user opsi a.

| | |
|---|---|
| Change ID | `DML-2026-139-visitor-nonaktifkan-permit-tanpa-proyeksi` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Status | `DEV_EXECUTED` |
| Checksum `data-change.sql` (sha256) | `c1d821078426f4f6dcd605d7253c98f83db658e94a9124a2ed288a19c7e1a1d7` |

## Objek

| Objek | Perubahan | Baris (dev) |
|---|---|---|
| `N_EMI_VISITOR_Transaksi_Permit` | `Flag_Aktif` 'Y' → 'T', `Updated_By` = 'DML-2026-139' | 1 |

Predikatnya dikunci ke nomor permit **dan** keadaannya (disetujui, aktif, sumber API, tanpa proyeksi). Di database yang tidak memiliki baris ini, perubahan menyentuh 0 baris. DML ini idempoten.

## Agent completed

- DML ditulis dan dijalankan di `Web_HRIS` development pada 2026-09-26, dengan 1 baris terdampak.
- Hasil verifikasi: permit tidak lagi muncul di `basePermitQuery`, dan jumlah permit "APPROVED tanpa proyeksi" yang aktif menjadi 0.

## User tasks

1. Review `data-change.sql`.
2. Di production, jalankan dulu kueri verifikasi yang ada di berkas itu untuk melihat apakah barisnya ada. Kalau baris tidak ada, DML tetap aman dijalankan (0 baris).
3. Catat perubahan ini ke SQL Tracker, lalu eksekusi di production.
