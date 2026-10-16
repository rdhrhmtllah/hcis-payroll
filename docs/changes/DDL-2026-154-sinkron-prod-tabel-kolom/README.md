# DDL-2026-154-sinkron-prod-tabel-kolom — Tabel & kolom yang kurang di production

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-154-sinkron-prod-tabel-kolom` |
| Jenis | DDL (sinkronisasi skema) |
| Target | production `emi_db_web_hr`; development `Web_HRIS` sudah punya semua objeknya |
| Artifact | `database/ddl/DDL-2026-154-sinkron-prod-tabel-kolom/forward.sql` (sha256 `e090b80f13cb50d63961380feb807ab5aa84cb9dbc592ab039f6ea9818031a1c`) |
| Drop manual | `database/ddl/DDL-2026-154-sinkron-prod-tabel-kolom/drop.sql` — jangan dijalankan agen |
| Urutan | jalankan SEBELUM `DDL-2026-155-sinkron-prod-indeks` |
| Status | `REVIEW_REQUIRED` |

## Metode (2026-09-29, baca saja di kedua database)

1. Katalog dev (780 tabel) dan prod (324 tabel terlihat) diekstrak.
2. Setiap tabel dev yang tak terlihat di prod diuji ulang lewat pesan galat —
   akun audit `srv_team_web` tidak melihat tabel tanpa GRANT:
   **460 memang tidak ada**, 8 ada tetapi tanpa izin (kolomnya diuji satu per satu
   dengan `SELECT TOP 0 [kolom]`).
3. Tiap selisih dicocokkan dengan kode aplikasi (`app`, `routes`, `config`,
   `resources/js`) dan artifact `database/`.
4. Kompilasi forward/drop di dev dengan `SET NOEXEC ON` — lolos, tidak ada yang dieksekusi.

## Isi

**6 tabel baru** (definisi dirakit dari katalog dev: kolom, identity, default bernama, PK, CHECK, FK):
`N_HRIS_Panduan`, `N_HRIS_Panduan_Versi`, `N_HRIS_Panduan_Halaman`, `N_HRIS_Panduan_Role`
(DDL-2026-146/148/149), `N_HRIS_Master_Kontak_Bantuan` (DDL-2026-144),
`N_EMI_VISITOR_Transaksi_Permit_Koreksi` (DDL-2026-138). FK Panduan ↔ Panduan_Versi
saling merujuk, jadi dipasang setelah semua tabel ada.

**8 kolom baru di tabel yang sudah ada**:

| Kolom | Asal | Catatan |
|---|---|---|
| `HRIS_Hak_Cuti_Tambahan.Status` | DDL-2026-092 | dipakai modul cuti |
| `N_EMI_VISITOR_Transaksi_Tamu_Detail.Nama_Penanggung_Jawab` | DDL-2026-135 | |
| `N_VISITOR_Permit_Efektif.Jenis_Akses` | DDL-2026-132 | NOT NULL DEFAULT 'PERMIT' — aman untuk tabel berisi |
| `N_VISITOR_Permit_Efektif.NFC_Inbio` | DDL-2026-153 | |
| `N_VISITOR_Tamu_Efektif.Flag_ByPass_CheckIn/Out`, `User_Bypass_CheckIn/Out` | **TANPA artifact** | dipakai `VisitorKehadiranQueryService` sejak `476693b3` (23 Sep 2026) — kueri kehadiran tamu **gagal di production** sampai kolom ini ada. Default dev bernama acak (`DF__N_VISITOR__Flag___…`) diganti nama bermakna. |

## Sengaja TIDAK dimasukkan

- **Tabel**: 420 tidak dirujuk kode maupun artifact (tabel aplikasi lain
  yang berbagi database dev: kandidat, careers, desktop, mesin absensi, AMS, dll.),
  14 cadangan/uji, dan 8 yang hanya disebut DDL-2026-089 (pembersihan index
  kembar dev) / DML uji. Dua middleware yang merujuk tabel `HRIS_KANDIDAT_*` adalah kode
  mati (tidak terdaftar di Kernel maupun rute).
- **Kolom tanpa pemakai kode**: `Karyawan.max_Cuti`, `N_HCIS_Tabel_Absen.Koefisien_OT_Gross`,
  `N_HRIS_Approval_Request.(id, Kode_Karyawan, Flow_Id, angka_wa, created_at, update_at)`,
  `HRIS_KANDIDAT_Users.*`.
- **Beda tipe/panjang/nullable** pada kolom yang ada di keduanya — mengubahnya butuh
  keputusan per kolom (bisa memotong data). Dilaporkan saja:

| Kolom | Dev | Prod |
|---|---|---|
| `Absen_SN.Output` | varchar(10,0,0) null=1 | varchar(5,0,0) null=1 |
| `CHECKINOUT.Hasil` | varchar(10,0,0) null=1 | varchar(50,0,0) null=1 |
| `HRIS_Approval_Flow.Kode_Karyawan_Requester` | varchar(100,0,0) null=1 | varchar(200,0,0) null=1 |
| `HRIS_Approval_Request.created_at` | datetime(8,23,3) null=1 | datetime2(8,27,7) null=1 |
| `HRIS_Approval_Request.Expired_by` | varchar(50,0,0) null=1 | varchar(100,0,0) null=1 |
| `HRIS_Buku_Cuti.Activated_At` | datetime2(8,27,7) null=1 | datetime(8,23,3) null=1 |
| `HRIS_Buku_Cuti.jumlah_terpakai` | decimal(5,5,2) null=1 | float(8,53,0) null=1 |
| `HRIS_Page_Access.UserID_Web` | int(4,10,0) null=1 | int(4,10,0) null=0 |
| `HRIS_Shift_Kerja_Detail.Flag_Default_Off` | char(1,0,0) null=0 | char(1,0,0) null=1 |
| `Karyawan.Kode_Karyawan` | varchar(255,0,0) null=0 | varchar(15,0,0) null=0 |
| `Karyawan.Nama` | varchar(255,0,0) null=1 | varchar(50,0,0) null=1 |
| `KPI_Master_Penjadwalan.Flag_Arsip` | char(1,0,0) null=0 | char(1,0,0) null=1 |
| `KPI_Pengajuan.Alasan_Reject` | varchar(500,0,0) null=1 | varchar(300,0,0) null=1 |
| `KPI_Users.Kode_Users` | varchar(-1,0,0) null=1 | varchar(30,0,0) null=1 |
| `N_HCIS_Tabel_Absen.Checktime3Hari` | nvarchar(-1,0,0) null=1 | varchar(300,0,0) null=1 |
| `N_HCIS_Tabel_Absen.Jenis_Lembur` | varchar(30,0,0) null=1 | varchar(50,0,0) null=1 |
| `N_HCIS_Tabel_Absen.No_Transaksi_Lembur_Awal` | varchar(200,0,0) null=1 | varchar(30,0,0) null=1 |
| `N_HCIS_Tabel_Absen.alasan_Lembur_Awal` | varchar(200,0,0) null=1 | varchar(300,0,0) null=1 |
| `N_HRIS_KPI_Notif_Skip.Kode_Karyawan` | varchar(20,0,0) null=0 | varchar(50,0,0) null=1 |
| `N_HRIS_Log_Buku_Cuti.Keterangan` | text(16,0,0) null=1 | nvarchar(-1,0,0) null=1 |
| `N_HRIS_Notification.Channel` | varchar(20,0,0) null=1 | varchar(100,0,0) null=1 |
| `N_HRIS_Shift_Whitelist.Kode_Karyawan` | varchar(10,0,0) null=0 | varchar(50,0,0) null=0 |
| `N_SOP_Absensi.Id_SOP_Paket_Item` | int(4,10,0) null=1 | int(4,10,0) null=0 |
| `N_SOP_Penjadwalan_Integrasi.Id_Master_Ujian` | varchar(200,0,0) null=0 | varchar(50,0,0) null=0 |
| `N_VISITOR_Tamu_Efektif.Kode_Karyawan_PIC` | varchar(20,0,0) null=1 | varchar(20,0,0) null=0 |
| `Transaksi_Absensi_Detail.lang` | varchar(100,0,0) null=1 | varchar(200,0,0) null=1 |
| `Transaksi_Absensi_Detail.lat` | varchar(100,0,0) null=1 | varchar(200,0,0) null=1 |
| `Transaksi_Absensi_Detail.accuracy` | varchar(100,0,0) null=1 | varchar(200,0,0) null=1 |
| `Transaksi_Cuti_Detail.Jenis` | varchar(20,0,0) null=0 | varchar(100,0,0) null=1 |
| `Transaksi_Cuti_Detail.Alasan` | varchar(100,0,0) null=1 | varchar(350,0,0) null=1 |
| `Transaksi_Lembur.UserID` | varchar(15,0,0) null=1 | varchar(50,0,0) null=1 |
| `Transaksi_Sakit_Izin_Detail.Jenis` | varchar(10,0,0) null=0 | varchar(20,0,0) null=0 |
| `Transaksi_Sakit_Izin_Detail.Alasan` | varchar(50,0,0) null=1 | varchar(300,0,0) null=1 |
| `Transaksi_Terlambat_Pulang_Cepat_Detail.Alasan` | varchar(50,0,0) null=1 | varchar(300,0,0) null=1 |

## Tugas user

1. Tinjau `forward.sql`; setujui.
2. Catat di SQL Tracker persis isi berkas (cocokkan checksum), jalankan di production,
   lalu jalankan `DDL-2026-155-sinkron-prod-indeks`.
3. Verifikasi: tabel/kolom di atas ada (`OBJECT_ID`, `COL_LENGTH`), dan layar kehadiran
   tamu Visitor tidak lagi galat.
