# DML-2026-054 — Subtitle Menu Functional Document Ikut Kosakata DMS

**Target development:** `Web_HRIS` @ team311 · **Status:** PENDING · SQL Tracker PENDING · Production PENDING

## Alasan Perubahan

`DML-2026-044` memindahkan modul SOP menjadi **DMS** dan menulis ulang seluruh
label menu ke kosakata baru. Tiga belas `Nama_Menu` dan dua belas `Menu_Subtitle`
sudah memakai kosakata itu.

Satu subtitle tertinggal:

| `Jenis_page` | Nama Menu | Subtitle lama | Subtitle baru |
|---|---|---|---|
| `menuSopSayaPage` | Functional Document | Dokumen fungsional & **SOP** penugasan saya | Dokumen fungsional & penugasan saya |

Akibatnya satu baris sidebar menyebut dua kosakata sekaligus — namanya sudah
"Functional Document", keterangannya masih "SOP". Ini ketahuan saat penyelarasan
kosakata di seluruh halaman DMS pada 2026-08-27.

`DML-2026-044` berstatus `DEV_DONE` dan checksum forward-nya terkunci, jadi
pembetulan ditempuh lewat perubahan terpisah, bukan dengan menyunting artefak
yang sudah dieksekusi.

---

## Lingkup

- Tabel: `N_HRIS_Menus`
- Predikat: `Jenis_page = 'menuSopSayaPage'`
- Baris terdampak yang diharapkan: **tepat 1**
- Skrip menolak dan membatalkan transaksi bila `@@ROWCOUNT <> 1`, sehingga
  baris ganda maupun menu yang hilang tidak lolos diam-diam.

## Kaitan

Kosakata yang menjadi acuan didokumentasikan di
`docs/architecture/glosarium-dms.md`.

---

## Eksekusi & Verifikasi Development

Belum dijalankan. Menunggu persetujuan user.

Sesudah dijalankan, `InertiaShellService::flushMenuCache()` wajib dipanggil —
baris menu di-cache, sehingga tanpa flush subtitle lama masih tampil.

## Tugas User Selanjutnya

1. Review isi `database/dml/DML-2026-054-subtitle-functional-document/data-change.sql`.
2. Setujui eksekusi ke development (`Web_HRIS`), lalu flush cache menu.
3. Daftarkan dan jalankan forward DML ke staging/production via SQL Tracker.
4. Verifikasi subtitle menu Functional Document di sidebar.
