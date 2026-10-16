# DML-2026-197 — Pembersihan Grant Duplikat, Yatim, dan Bypass Tata Kelola (HRIS_Page_Access)

Menonaktifkan secara aman (soft-delete, `Flag_Aktif = 'T'`) baris grant langsung di `HRIS_Page_Access` yang redundan atau tidak valid:

1. **27 baris Halaman Yatim (Orphan):** Halaman yang sudah dihapus/tidak ada di `N_HRIS_Menus`.
2. **196 baris Grant Duplikat:** Pengguna sudah memiliki peran aktif yang memuat halaman tersebut di `N_HRIS_Role_Hak`.
3. **27 baris Bypass Tata Kelola:** Halaman administratif/tata kelola yang dialihkan ke peran admin modul resmi (`[MODUL]_ADMIN` / `[MODUL]_SUPER_ADMIN`).

Total baris dinonaktifkan: **237 baris unik**.

## Sifat Perubahan

- **100% Soft-Delete:** Tidak ada data yang dihapus fisik (`DELETE`), hanya diubah `Flag_Aktif = 'T'` dengan riwayat alasan tercatat.
- **Backward-Compatible:** Pengguna tidak kehilangan akses karena haknya sudah dijamin oleh peran aktif mereka.

## Production

Jalankan via SQL Tracker setelah DML-2026-196.
