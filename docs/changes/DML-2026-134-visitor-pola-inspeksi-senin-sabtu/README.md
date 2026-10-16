# DML-2026-134-visitor-pola-inspeksi-senin-sabtu

## Ringkas

Pola jadwal inspeksi kendaraan untuk **Senin sampai Sabtu**, empat baris per
hari. Minggu sengaja kosong.

| | |
|---|---|
| Change ID | `DML-2026-134-visitor-pola-inspeksi-senin-sabtu` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Bergantung pada | `DDL-2026-123` (master pos/shift/pola), `DML-2026-133` (pembersihan pola buntu) |
| Status | `DEV_EXECUTED` |

## Isi jadwal

| Shift | Jam kerja | Pos | Jendela | Sesi |
|---|---|---|---|---|
| Shift 1 | 07:00–15:00 | Area Parkir Motor/Mobil | 10:00–11:00 | 4 × 15 mnt |
| Shift 1 | 07:00–15:00 | Klinik SHE | 14:00–15:00 | 4 × 15 mnt |
| Shift 2 | 15:00–23:00 | Klinik SHE | 19:00–20:00 | 4 × 15 mnt |
| Shift 3 | 23:00–07:00 | Klinik SHE | 01:00–02:00 | 4 × 15 mnt |

Kuota 1 kendaraan per sesi → **16 kendaraan per hari**, 96 per minggu.

Baris Shift 3 **menyeberang tengah malam**: 01:00–02:00 berada di dalam shift
23:00–07:00 yang dimulai hari sebelumnya, dan slotnya dimiliki hari yang
memulainya — bukan tanggal jamnya.

## Yang dijaga artefaknya

- **Pos dan shift dicari lewat namanya, bukan Id yang ditempel.** Id identity
  berbeda antar lingkungan; menempelkannya membuat artefak ini menulis jadwal
  ke pos yang salah di production tanpa satu pun galat. Bila salah satunya
  tidak ditemukan atau tidak aktif, artefak berhenti dengan `THROW` — bukan
  menulis jadwal setengah jadi.
- **Idempoten** lewat `NOT EXISTS` pada (pos, shift, hari, jam mulai).

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `data-change.sql` | OK — 24 baris (4 × 6 hari). Slot terbukti terbentuk: 16 slot/hari Senin–Sabtu, 0 pada Minggu, 96 per minggu. |
| 2026-09-22 | `data-change.sql` (ulang) | OK — tetap 24 baris. Idempoten terbukti. |

## Pekerjaan user

- Rekam di SQL Tracker **hanya bila** jadwal yang sama memang dikehendaki di
  production. Ini data konfigurasi operasional, bukan struktur: SHE di
  production biasanya menyusunnya sendiri lewat `/agenda-inspeksi`.
