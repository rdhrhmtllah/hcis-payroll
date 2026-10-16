# DML-2026-156 — Data Visitor (VMS) yang belum ada di production

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-156-visitor-data-tertunda-prod` |
| Jenis | DML |
| Target | production `emi_db_web_hr` (berkas menolak database lain) |
| Artifact | `database/dml/DML-2026-156-visitor-data-tertunda-prod/data-change.sql` (sha256 `28d15b877d2e20a47fc4a44d183bd8a8e5146b616be640f3830b2c7dab7ba1fb`), 194 baris |
| Bergantung pada | `DDL-2026-154` (hanya bagian 11, kontak bantuan) |
| Status | `REVIEW_REQUIRED` |

## Dasar (production, baca saja, 2026-09-30)

Setiap artifact dipecah menjadi efek-efeknya lalu dicek satu per satu. Hanya
efek yang **belum ada** yang ditulis ulang di berkas ini.

**Sudah beres — tidak diulang:** hibah halaman admin Visitor sudah dicabut
(bagian 2 DML-2026-124, manual 18 Sep); header menu verifikator (DML-2026-025);
pola inspeksi tak ada (DML-2026-133). Transaksi tamu, detail tamu, dan kedua
proyeksi Efektif **kosong** di production, jadi semua backfill/migrasi status
(DDL-077, 121, 122, 132) tidak diperlukan.

## Isi

| # | Asal | Efek |
|---|---|---|
| 1 | DML-2026-124 | kode grup `VMS` → `VISITOR` |
| 2 | DDL-2026-077 / 104 | kategori COKLAT; `Flag_Muncul_Pengajuan` 4 kategori (COKLAT = T) |
| 3 | DDL-2026-121 / 122 | role ADMIN_TAMU_B2B + 6 role departemen; anggota ADMIN_TAMU disalin ke B2B |
| 4 | DDL-2026-121 | jenis approval + config TAMU_B2B |
| 5 | DDL-121 / 122 / 123 + DML-137 | 5 menu (B2B ×3, Jadwal Tamu tersembunyi, Agenda Inspeksi), peta grup, kunci halaman diturunkan dari pemegang Verifikator Tamu / Permit |
| 6 | DML-2026-137 | Monitoring Tamu dihibahkan lewat role (data tetap disaring matriks) |
| 7 | DDL-2026-122 + DML-2026-136 | 11 baris matriks tamu |
| 8 | DDL-2026-123 | master Shift 1/2/3 |
| 9 | DML-2026-078 | **INSERT** penjadwal `visitor:expire-efektif` tiap 5 menit (baris belum pernah ada) |
| 10 | registrasi | Database Change Dashboard |
| 11 | DML-2026-150 | kontak PIC modul — dilewati bila DDL-2026-154 belum jalan |

## Belum dimasukkan

- **DML-2026-134** (pola inspeksi Senin–Sabtu): butuh pos "Area Parkir Motor/Mobil" dan "Klinik SHE" yang disusun SHE di layar — jalankan artifact aslinya setelah pos dibuat.
- **DML-2026-109 / 131**: UserID_Web di dalamnya milik orang lain di production — butuh daftar Kode_Karyawan dari user.
- **DML-2026-143** (API cek kartu) dan **DML-2026-080** (API mitra): butuh kunci API production.
- **DML-2026-141 / 145 / 151**: data dummy development.

## Validasi (development `Web_HRIS`)

1. Kompilasi `SET NOEXEC ON`: lolos.
2. Simulasi di satu transaksi yang di-**rollback**: dev dibentuk seperti production
   (kode `VMS`, role/menu/config/matriks/shift/penjadwal dihapus, flag kategori
   dikosongkan) → berkas dijalankan → hasil **identik dengan dev** untuk kode grup,
   kategori, role, config, menu, peta grup + hibah, matriks, shift, penjadwal.
   Kunci halaman B2B 3 vs dev 4 (satu kunci dev diberi manual belakangan) — sesuai
   aturan penurunan DDL-2026-121. Jalan ulang: tidak mengubah apa pun.

## Tugas user

1. Tinjau & setujui; catat di SQL Tracker persis isi berkas (cocokkan checksum).
2. Jalankan di production. Bagian 11 butuh DDL-2026-154 lebih dulu (bila belum, ia
   mencetak "LEWATI" — jalankan ulang setelah 154).
3. Verifikasi dengan 4 SELECT di kaki berkas.
