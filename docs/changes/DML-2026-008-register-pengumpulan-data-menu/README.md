# DML-2026-008 — Pendaftaran menu Pengumpulan Data

Mendaftarkan dua halaman ke menu HCIS dan memetakannya ke grup fitur **ESS**.

| Halaman | URL | `Jenis_page` | `Is_Global_Menu` |
|---|---|---|---|
| Pembaruan Data | `/pengumpulan-data` | `pengumpulanDataPage` | `Y` |
| Pembaruan Data Admin | `/admin/pengumpulan-data` | `adminPengumpulanDataPage` | `T` |

Halaman karyawan memakai `Is_Global_Menu = 'Y'` karena seluruh karyawan aktif
memang wajib mengisi; memakai `'T'` berarti harus memberi akses satu per satu ke
ratusan orang lewat `HRIS_Page_Access`.

Halaman admin memakai `'T'` karena menampilkan NIK KTP seluruh karyawan.
Aksesnya diberikan sadar lewat `/page-access`.

## Kaitannya dengan struktur modul

Kedua baris `N_HRIS_Grup_Fitur_Map` memakai `Id_Grup_Fitur = 1`, yang pada
`N_HRIS_Master_Grup_Fitur` adalah **ESS**. Inilah bukti yang menentukan
penempatan seluruh berkas fitur ini di bawah `ESS/Employee/DataCollection`,
bukan hasil terkaan dari nama fitur.

## Status

Diverifikasi **sudah terdaftar** di `Web_HRIS` (development) pada 2026-08-18:
`Id_Menu` 83 dan 84 ada di `N_HRIS_Menus`, dan kedua `Jenis_Page` terpetakan ke
grup 1 di `N_HRIS_Grup_Fitur_Map`.

Catatan: berkas SQL-nya membagi diri menjadi BLOK A (halaman karyawan, "jalankan
sekarang") dan BLOK B (halaman admin, "menunggu fase 4"). Pembacaan database
menunjukkan **keduanya sudah dijalankan** di development.

Belum masuk SQL Tracker dan belum dijalankan di production.

## Asal berkas

Dikembangkan di cabang `feat/pengumpulan-data-user` sebagai
`database/pengumpulan_data/2026_08_14_pengumpulan_data_menu_dml.sql`, dipindah ke
tata letak ledger pada 2026-08-18 tanpa perubahan isi SQL.
