# DML-2026-141-visitor-dummy-kartu-nfc

- **Target:** Web_HRIS development saja (kartu fiktif). Tidak untuk production.
- **Artefak:** `database/dml/DML-2026-141-visitor-dummy-kartu-nfc/data-change.sql` (sha256 `181e3d04c3f0ebbc945c038272039cbc983e20ad10db342a0b911c39098f3624`)
- **Objek:** `N_EMI_Master_NFC` (13 kartu), `N_EMI_Binding_NFC` (12 pairing), semua `created_by = 'DUMMY-DML-2026-141'`
- **Status:** lihat `database/ddl/status.json`

## Studi kasus

| # | Pemegang | Kartu (hex / InBio) | Keadaan data | Harapan saat ajukan permit |
|---|---|---|---|---|
| 1 | H45 Ridho | 04A1B2C3 / 77705923 | kartu aktif, tervalidasi, pairing aktif | **Boleh** mengisi form |
| 2 | B6 Andre | 04A1B2CF / 77705935 | sama dengan #1 | **Boleh** |
| 3 | K32 Iqbal | 04A1B2C5 → 04A1B2C6 | kartu lama hilang (kartu & pairing nonaktif), kartu pengganti aktif | **Boleh** (yang dipakai kartu pengganti) |
| 4 | Y62 Tari | 04A1B2C4 / 77705924 | pairing aktif, kartu **belum divalidasi sistem** | Ditolak / menunggu validasi *(aturan perlu diputuskan)* |
| 5 | C9 Arya | 04A1B2C8 / 77705928 | pairing aktif, kartu **diblokir** (master nonaktif) | Ditolak → hubungi IT support |
| 6 | E14 Dhea | 04A1B2C7 / 77705927 | pairing **dicabut** (binding nonaktif), kartu masih aktif | Ditolak → hubungi IT support |
| 7 | B8 Anita | — | **belum pernah pairing** | Ditolak → hubungi IT support |
| 8 | J31 Imam & F19 Erlangga | 04A1B2C9 / 77705929 | **satu kartu dipakai dua karyawan** aktif | Anomali *(aturan perlu diputuskan)* |
| 9 | W30 Sandi (resign) | 04A1B2CA / 77705930 | pairing masih aktif padahal karyawan nonaktif | Tidak bisa login; kartu perlu ditarik IT |
| 10 | Mitra MTR-2026-0001 | 04A1B2CB / 77705931 | pairing mitra aktif, tanpa kode karyawan | Di luar permit karyawan |
| 11 | Mitra MTR-2026-0002 | 04A1B2CC / 77705932 | pairing mitra nonaktif (kontrak selesai) | Di luar permit karyawan |
| 12 | — (stok IT) | 04A1B2CD / 77705933 | kartu tervalidasi tanpa pairing | Siap dipasangkan |
| 13 | — (stok IT) | 04A1B2CE / 77705934 | kartu belum divalidasi tanpa pairing | Belum boleh dipasangkan |

Selain yang tercantum, seluruh karyawan lain tidak punya pairing (sama dengan kasus #7).

## Catatan untuk fitur

- Di dev seluruh karyawan aktif ber-NIK sama (`EMI2025120001`); pencocokan pemohon wajib lewat `kode_karyawan`.
- Kedua tabel dibuat manual di dev dan **belum** punya berkas DDL di repo; production membutuhkannya lewat SQL Tracker.

## Pembersihan

Blok `DELETE` berkomentar di akhir `data-change.sql` (berdasarkan `created_by`), dijalankan manual bila perlu.
