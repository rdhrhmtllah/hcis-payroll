# DML-2026-012 — Menu SOP Export Studio

Mendaftarkan halaman Export Studio agar muncul di sidebar dan punya izin
tersendiri.

## Basis verifikasi database

Dibaca langsung dari `Web_HRIS` (development) pada 2026-08-18, bukan disalin dari
snapshot dokumentasi:

| Yang diverifikasi | Hasil |
|---|---|
| `Id_Grup_Fitur` LMS | **3** |
| Menu SOP yang dicontoh | `menuSopMonitoringPage`, header `administrator_sop`, `Sort_Header` 11 |
| `Sort_Menu` tertinggi di header itu | 8 → menu baru memakai 9 |
| Pemegang `menuSopMonitoringPage` aktif | 2 |

## Empat blok

1. **Baris `N_HRIS_Menus`.** `Id_Menu` IDENTITY, jadi tidak masuk daftar kolom.
   Header disalin persis dari menu SOP lain supaya masuk kelompok sidebar yang
   sama.
2. **Pemetaan `N_HRIS_Grup_Fitur_Map` ke grup LMS.** Ini langkah yang paling
   sering terlewat: tanpa baris ini menu **tidak pernah muncul** dan tidak ada
   galat apa pun yang menjelaskan kenapa.
3. **Hak akses.** Setiap pemegang `menuSopMonitoringPage` yang aktif ikut
   mendapat halaman baru — orang yang hari ini sudah boleh melihat data
   kepatuhan tidak kehilangan apa pun pada hari rilis.
4. **Pendaftaran ke Database Change Dashboard.** Mengisi `status.json` saja tidak
   membuat perubahan terlihat di sana; keduanya catatan terpisah (lihat
   `DML-2026-011`).

## Gate route

`routes/LMS/SOP/Export/SopExportStudio.php` memakai pola OR yang sudah dipakai
`SopAdminPrioritasController`:
`check.jabatan:menuSopExportStudioPage,menuSopMonitoringPage`. Kunci sebenarnya
yang pertama; yang kedua menjaga admin monitoring existing tetap bisa masuk
sebelum hak barunya disebar lebih luas.

## Eksekusi

`Web_HRIS`, 2026-08-18. Baris terpengaruh **4**, persis perkiraan:

| Tabel | Delta |
|---|---:|
| `N_HRIS_Menus` | 1 |
| `N_HRIS_Grup_Fitur_Map` | 1 |
| `HRIS_Page_Access` | 2 |

Seluruh blok dijaga `IF NOT EXISTS`; jalan kedua hanya menyisipkan blok
pendaftaran dashboard, membuktikan idempotensinya.

Belum masuk SQL Tracker dan belum dijalankan di production.
