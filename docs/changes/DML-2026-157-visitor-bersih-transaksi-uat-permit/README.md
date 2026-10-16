# DML-2026-157 — Bersihkan transaksi Visitor untuk UAT VMS Permit

- **Target:** development `Web_HRIS` saja (berkas menolak database lain)
- **Status:** DEV_EXECUTED (2026-09-30)
- **Artifact:** `database/dml/DML-2026-157-visitor-bersih-transaksi-uat-permit/data-change.sql`
- **Acuan:** `docs/uat/IT_BA_UAT VMS-PERMIT.docx` (UAT 1 Oktober 2026)

## Yang diubah

| Tabel | Aksi | Baris |
|---|---|---|
| HRIS_Approval_Request (No_Transaksi permit/tamu) | DELETE | 101 |
| N_HRIS_Notification (Module = VISITOR) | DELETE | 195 |
| N_EMI_VISITOR_Transaksi_Permit_Checklist / _Dokumen / _Koreksi / _Detail | DELETE | 1325 / 228 / 1 / 97 |
| N_VISITOR_Inspeksi_Booking | DELETE | 49 |
| N_VISITOR_Permit_Efektif | DELETE | 74 |
| N_EMI_VISITOR_Transaksi_Permit | DELETE | 99 |
| N_EMI_VISITOR_Transaksi_Tamu_Detail / N_VISITOR_Tamu_Efektif / N_EMI_VISITOR_Transaksi_Tamu | DELETE | 50 / 48 / 50 |
| N_VISITOR_Master_Pos_Inspeksi, N_VISITOR_Master_Shift_Inspeksi (Created_By = UJI) | Flag_Aktif = T | 1 + 1 |
| N_EMI_VISITOR_Master_Approval_Question #15 (teks uji) | Flag_Aktif = T | 1 |

Tidak disentuh: master data, kartu NFC & binding, pos/shift/pola asli, role, akses halaman.
Berkas unggahan dokumen permit di storage tidak dihapus (hanya barisnya).

## Cadangan

Seluruh baris yang dihapus/diubah disimpan sebagai JSON di
`storage/app/cadangan/DML-2026-157/` (di-ignore git) tepat sebelum eksekusi.

Dijalankan ulang 30 Sep sore setelah uji tim selesai (6 permit, 7 tamu,
6 approval request, 15 notifikasi); cadangannya di
`storage/app/cadangan/DML-2026-157-ulang-20260930/`. Berkas aman dijalankan
ulang: bagian UPDATE master tidak mengubah apa pun pada eksekusi kedua.

## Kesiapan UAT sesudah eksekusi

| Prasyarat | Keadaan dev |
|---|---|
| Pemohon NOIR (H45) & tari (Y62) | Kartu TERDAFTAR, tanpa permit |
| Pemohon tanpa ID Card | anita (B8) — BELUM_PAIRING |
| Verifikator/SHE | andre (B6), arya (C9): Verifikator Permit + Agenda Inspeksi |
| Admin | NOIR: Monitoring, Master Data, Export Studio Permit |
| Master data | HIJAU & MERAH tampil, COKLAT tersembunyi; durasi 1/3/6 aktif; pertanyaan umum + khusus MERAH (#17) |
| Jadwal inspeksi | Klinik SHE & Area Parkir, 28–36 slot/hari 1–8 Okt (Minggu tutup) |
| Penjadwal | visitor:expire-efektif `*/5 * * * *` aktif |

## Selisih setelan vs dokumen UAT

- `VISITOR_PERMIT_JEDA_MINIMUM_MENIT` = 30 (dokumen: 0 menit).
- `VISITOR_PERMIT_MAX_PENGAJUAN_AKTIF` = 2 (dokumen: belum ada pembatasan).
