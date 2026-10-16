# DDL-2026-007 — Tabel Pengumpulan Data Karyawan

Empat tabel baru untuk fitur pembaruan data karyawan (`ESS/Employee/DataCollection`).
Tidak ada tabel lama yang diubah, ditambah kolom, atau disentuh.

| Tabel | Isi |
|---|---|
| `N_HRIS_Pengumpulan_Data_Submission` | Jawaban karyawan, disimpan sebagai JSON |
| `N_HRIS_Pengumpulan_Data_Status` | Status pengisian per karyawan, untuk filter admin |
| `N_HRIS_Pengumpulan_Data_Config` | Sakelar kampanye dan konfigurasi |
| `N_HRIS_Pengumpulan_Data_Audit` | Jejak audit perubahan |

Semua terikat ke `Karyawan` lewat `Kode_Karyawan` tanpa `FOREIGN KEY`, mengikuti
pola tabel `N_HRIS_*` lain di repo ini.

Jawaban disimpan sebagai JSON dengan kolom `Versi_Skema`. Menambah field di
kemudian hari tidak memerlukan `ALTER TABLE` — cukup naikkan `Versi_Skema` di
`config/pengumpulan_data.php`.

## Asal berkas

Dikembangkan di cabang `feat/pengumpulan-data-user` sebagai
`database/pengumpulan_data/2026_08_14_pengumpulan_data_ddl.sql`. Saat cabang itu
disesuaikan ke struktur modul baru (2026-08-18), berkasnya dipindah ke tata letak
ledger. Isi SQL-nya tidak berubah sama sekali; hanya baris komentar pertama yang
menunjuk path dirinya sendiri yang diperbarui.

## Status

Keempat tabel diverifikasi **sudah ada** di `Web_HRIS` (development) pada
2026-08-18 lewat `sys.tables`. DDL-nya idempoten (`IF NOT EXISTS`), jadi aman
dijalankan ulang.

Belum masuk SQL Tracker dan belum dijalankan di production.

Desain lengkap: `docs/superpowers/specs/2026-08-14-pengumpulan-data-karyawan-design.md`
