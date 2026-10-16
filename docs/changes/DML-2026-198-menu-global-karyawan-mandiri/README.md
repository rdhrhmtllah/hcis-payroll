# DML-2026-198 — Standarisasi Menu Global Karyawan Mandiri & Pembersihan Grant

Mengubah menu layanan mandiri karyawan (*Self-Service*) menjadi **Menu Global (`Is_Global_Menu = 'Y'`)** dan menonaktifkan 1.979 baris grant perorangan di `HRIS_Page_Access`.

## Latar Belakang

Pada sistem lama, 900+ karyawan diberikan akses langsung ke halaman `FormKPIPage`, `UpdatePerformancePage`, `cutiPage`, `IzinPage`, dll. melalui `HRIS_Page_Access` (1.900+ baris) karena sistem belum memiliki peran umum karyawan.

Sama seperti menu *Appraisal Saya* (`PerformanceAppraisalSayaPage`) dan *Dokumen Saya* (`menuSopSayaPage`) yang sudah sukses berjalan sebagai Menu Global, menu layanan mandiri karyawan kini dijadikan Menu Global resmi.

## Objek Terdampak

- `dbo.N_HRIS_Menus` (UPDATE `Is_Global_Menu = 'Y'` untuk 8 menu)
- `dbo.HRIS_Page_Access` (UPDATE `Flag_Aktif = 'T'` untuk 1.979 baris yang kini di-cover Menu Global)

## Menu yang Distandarkan:
1. `FormKPIPage` (Input Target KPI Pribadi)
2. `UpdatePerformancePage` (Update Capaian KPI Pribadi)
3. `dashboardStaff` (Dashboard KPI Individu)
4. `cutiPage` (Cuti Pribadi)
5. `IzinPage` (Izin Pribadi)
6. `absensiPage` (Absensi Pribadi)
7. `selfShift` (Shift Pribadi)
8. `konversiLembur` (Konversi Lembur Pribadi)

## Production

Jalankan via SQL Tracker setelah DML-2026-197.
Bersihkan cache menu shell setelah eksekusi:
```bash
php artisan cache:forget shell.menu-rows.v2:001
```
