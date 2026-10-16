# DDL-2026-162-sinkron-prod-tabel-kolom — Tabel & kolom yang kurang di production

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-162-sinkron-prod-tabel-kolom` |
| Jenis | DDL (sinkronisasi skema) |
| Target | production `emi_db_web_hr`; development `Web_HRIS` sudah punya objeknya |
| Artifact | `database/ddl/DDL-2026-162-sinkron-prod-tabel-kolom/forward.sql` (sha256 `541477edf9239255a5c29001286bde77d7da4d7de7d8f09d54cec5464d4dd3fd`) |
| Drop manual | `database/ddl/DDL-2026-162-sinkron-prod-tabel-kolom/drop.sql` — jangan dijalankan agen |
| Urutan | jalankan SEBELUM `DDL-2026-163-sinkron-prod-indeks` |
| Status | `REVIEW_REQUIRED` |

## Metode (2026-10-06, baca saja di kedua database)

1. Katalog dev (819 tabel) dan prod (330 tabel terlihat) diekstrak.
2. 501 tabel dev yang tak terlihat di prod diuji ulang lewat pesan galat
   (`SELECT TOP 0`): **493 memang tidak ada**, 8 ada tetapi tanpa izin bagi akun audit.
3. Tiap selisih dicocokkan dengan kode aplikasi (`app`, `routes`, `config`,
   `resources/js`, `resources/views`) dan artifact `database/`.
4. `forward.sql` dan `drop.sql` dikompilasi di dev dengan `SET NOEXEC ON` — lolos,
   tidak ada yang dieksekusi.

## Isi

**1 tabel baru**: `N_SOP_Item_Versi_Lampiran` (lampiran form kosong per versi SOP,
dipakai `app/Services/LMS/SOP/SopVersiLampiranService.php`). Definisinya identik dengan
`DDL-2026-158-sop-versi-lampiran-form`, yang masih `PENDING` di production. Keduanya
idempoten — cukup jalankan salah satu; bila 158 sudah dijalankan, berkas ini tidak
mengubah apa pun.

**0 kolom baru**: tidak ada kolom yang dipakai kode dan hilang di production.

Objek `DDL-2026-154-sinkron-prod-tabel-kolom` dan `DDL-2026-155-sinkron-prod-indeks`
semuanya sudah ada di production per 2026-10-06 (6 tabel, 8 kolom, 19 index) — status
production keduanya di ledger belum diperbarui karena butuh konfirmasi user.

## Sengaja TIDAK dimasukkan

- **Tabel**: 472 tidak dirujuk kode (tabel aplikasi lain yang berbagi database dev:
  kandidat, careers, desktop, mesin absensi, dll.); `HRIS_KANDIDAT_*` dan
  `N_HRIS_KANDIDAT_*` hanya dirujuk `CheckOfficialApiKey`/`ApiCredentialValidator` yang tidak
  terdaftar di Kernel maupun rute; `N_HRIS_Master_Lokasi` hanya disebut di komentar;
  `devices`/`jobs`/`migrations` cocok kata umum, bukan kueri; 8 tabel yang hanya disebut
  DDL-2026-089 / DML uji.
- **Kolom tanpa pemakai kode**: `N_HCIS_Tabel_Absen.Koefisien_OT_Gross`,
  `N_HRIS_Approval_Request.(id, Kode_Karyawan, Flow_Id, angka_wa, update_at)`,
  `HRIS_KANDIDAT_Users.*` (10 kolom).
- **Tabel tanpa izin baca** (ada di prod, kolom tak bisa diverifikasi): `CHECKINOUT_Lain`,
  `N_EMI_Binding_NFC`, `N_EMI_Master_NFC`, `N_HRIS_CHECKINOUT_ANOMALI`, `N_HRIS_Mesin_Absensi`,
  `N_HRIS_Mesin_Absensi_Status`, `N_WEB_CAREERS_Mpp_Sla_Perpanjangan`, `init`.
- **Constraint** (bukan tabel/kolom; menambahkannya ke tabel berisi bisa gagal bila ada data
  yatim/di luar daftar, jadi butuh keputusan terpisah):
  - 18 FK ada di dev, tidak di prod — tabel `N_SOP_Automation_Run`, `N_SOP_Penjadwalan`,
    `N_SOP_Paket_Draft*`, `N_SOP_Assignment_Rule_Paket`, `N_SOP_Progress`,
    `N_VISITOR_Inspeksi_*`, `N_HRIS_Menu_Capabilities`.
  - 17 CHECK yang namanya tidak ada di prod (5 di antaranya bernama otomatis `CK__…`,
    bisa jadi ada di prod dengan nama lain; teks CHECK prod tak terbaca akun audit).
- **Beda tipe/panjang/nullable** (36 kolom) — dilaporkan di
  `DDL-2026-154-sinkron-prod-tabel-kolom`; yang baru sejak itu:
  `N_VISITOR_Permit_Efektif.Waktu_Mulai_Berlaku` dan `.Waktu_Berakhir`
  (dev `datetime`, prod `datetime2(0)`).

## Tugas user

1. Tinjau `forward.sql`; setujui.
2. Catat di SQL Tracker persis isi berkas (cocokkan checksum), jalankan di production
   (atau jalankan DDL-2026-158 saja), lalu DDL-2026-163.
3. Verifikasi: blok VERIFIKASI di kaki `forward.sql` menghasilkan nol baris.
