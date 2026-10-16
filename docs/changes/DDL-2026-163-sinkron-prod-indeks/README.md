# DDL-2026-163-sinkron-prod-indeks — Index yang kurang di production

| Bidang | Nilai |
|---|---|
| Change ID | `DDL-2026-163-sinkron-prod-indeks` |
| Jenis | DDL (sinkronisasi index) |
| Target | production `emi_db_web_hr` |
| Artifact | `database/ddl/DDL-2026-163-sinkron-prod-indeks/forward.sql` (sha256 `bbfa5dacec9f8f1af80d9049bf14055bf82a248327cbaa67f127f0b14c2d8c38`) |
| Drop manual | `database/ddl/DDL-2026-163-sinkron-prod-indeks/drop.sql` — jangan dijalankan agen |
| Bergantung pada | `DDL-2026-162-sinkron-prod-tabel-kolom` (atau DDL-2026-158) |
| Status | `REVIEW_REQUIRED` |

## Metode (2026-10-06, baca saja)

Dibandingkan berdasarkan **definisi** (kolom kunci + urutan/DESC, INCLUDE, UNIQUE,
filter), bukan nama. Akun audit production tidak bisa membaca teks filter
(`filter_definition` NULL, padahal `has_filter = 1` pada 49 index), jadi index berfilter
dianggap sama bila kunci, INCLUDE, dan UNIQUE-nya sama. Dikompilasi di dev dengan
`SET NOEXEC ON` — lolos.

## Isi — 1 index

| Tabel | Index | Keterangan |
|---|---|---|
| `N_SOP_Item_Versi_Lampiran` | `IX_SOP_Versi_Lampiran_Versi` (`Id_SOP_Item_Versi, Flag_Aktif, Urutan`) | tabel baru DDL-2026-162; sama dengan index di DDL-2026-158 |

**Tidak ada index yang kurang pada tabel production yang sudah ada.** Ke-19 index
DDL-2026-155 sudah ada di production.

## Tidak dimasukkan

- `HRIS_Page_Access.UQ__HRIS_Pag__4D27FF41FECDF62E` (UNIQUE `Id_Page_Access`) — prod sudah
  punya `PK_HRIS_Page_Access` CLUSTERED pada kolom yang sama; keunikan sudah terjaga.
- Index pada kolom yang tidak disinkronkan: `HRIS_KANDIDAT_Users.IX_Users_KodeRole`,
  PK `N_HRIS_Approval_Request.[id]`.
- Index pada 8 tabel tanpa izin baca akun audit (tak bisa diverifikasi), termasuk
  `N_EMI_Binding_NFC` dan `N_EMI_Master_NFC` yang dipakai `KartuNfcPemohon`.
- **Bernama sama, definisi beda** — mengubahnya butuh DROP + CREATE, di luar cakupan
  "yang kurang":

| Index | Dev | Prod |
|---|---|---|
| `N_SOP_Automation_Run.IX_N_SOP_Run_History` | `Created_At DESC, Tipe_Run, Status_Run` | tanpa DESC |
| `N_KPI_Target_Koreksi.IX_N_KPI_Target_Koreksi_Det` | `Id_Pengajuan_Det, Waktu DESC` | tanpa DESC |
| `Transaksi_Lembur_Det.IX_Transaksi_Lembur_Det_NoTrans_Kode` | `No_Transaksi, Kode_Karyawan, Tanggal_Lembur DESC` | tanpa DESC |
| `N_SOP_Paket_Draft.IX_N_SOP_Paket_Draft_Paket_Status` | `Id_SOP_Paket, Status, Updated_At DESC` | tanpa DESC |
| `N_SOP_Paket_Versi.IX_N_SOP_Paket_Versi_Paket_Tanggal` | `Id_SOP_Paket, Tanggal_Publish DESC` | tanpa DESC |
| `N_SOP_Paket_Draft_Item_Versi.IX_N_SOP_Paket_Draft_Item_Versi_Status` | `Id_SOP_Draft_Item, Status, Versi_Ke DESC` | tanpa DESC |
| `Karyawan.IX_Karyawan_UserID_Web` | `UserID_Web` | `UserID_Web` + INCLUDE 7 kolom (prod lebih lengkap) |

  Beda urutan DESC tidak membuat index tak terpakai (SQL Server bisa memindai mundur);
  dampaknya kecil.

## Tugas user

1. Tinjau `forward.sql`; setujui.
2. Catat di SQL Tracker persis isi berkas (cocokkan checksum), jalankan SETELAH DDL-2026-162.
3. Verifikasi: blok VERIFIKASI di kaki `forward.sql` menghasilkan nol baris.
