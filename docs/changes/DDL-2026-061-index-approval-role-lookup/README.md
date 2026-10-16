# DDL-2026-061-index-approval-role-lookup

## Ringkas

Mencatat dua index pencarian yang **sudah ada di development** tetapi tidak pernah
terdaftar sebagai change apa pun, sehingga tidak pernah punya jalur resmi ke
production.

| Tabel | Index | Kolom kunci |
|---|---|---|
| `HRIS_Approval_Request` | `IX_Approval_Request_Trans_Order` | `No_Transaksi`, `Request_Order_Flow` |
| `N_HRIS_Role_User` | `IX_RoleUser_UserId` | `UserId_Web` |

## Kenapa muncul sekarang

Ditemukan saat audit skema modul VISITOR antara `Web_HRIS` (development) dan
`emi_db_web_hr` (production) pada 2026-09-01. Audit menelusuri objek yang dipakai
133 berkas fitur Visitor, lalu membandingkan keduanya.

Pencarian di `database/` tidak menemukan satu pun berkas DDL yang membuat kedua
index ini — jadi keduanya adalah drift development yang tidak tercatat, bukan
sisa change yang gagal jalan.

## Dampak

Bukan penyebab error. Kolomnya sudah ada di production, sehingga kueri tetap
berjalan; yang hilang hanya jalur index sehingga pembacaan lebih lambat.

Pemakaian di modul Visitor:

- `Request_Order_Flow` dipakai 3 berkas
- `UserId_Web` dipakai 1 berkas

Keduanya juga dipakai luas di luar modul Visitor.

## Catatan eksekusi

- Development: kedua index **sudah ada**. `forward.sql` bersifat idempoten,
  sehingga menjalankannya di development adalah no-op yang terverifikasi.
- Production berjalan di **Express Edition**, yang tidak mendukung
  `WITH (ONLINE = ON)`. Kedua index sengaja dibangun offline.
- `HRIS_Approval_Request` berisi 35.968 baris di production per 2026-09-01 —
  build offline berlangsung singkat, tetapi tabel terkunci selama proses.
  Jalankan di luar jam sibuk bila memungkinkan.
- `N_HRIS_Role_User` berisi 25 baris; seketika.

## Berkas

- `database/ddl/DDL-2026-061-index-approval-role-lookup/forward.sql`
- `database/ddl/DDL-2026-061-index-approval-role-lookup/drop.sql` — bantuan reset manual, tidak dijalankan agent
- Tidak ada DML.

## Dijalankan bersama

Change ini digabung ke dalam satu runbook eksekusi production bersama
`DDL-2026-020` dan `DDL-2026-023`:

`database/runbook/2026-09-01-visitor-production-catchup.sql`

Runbook itu hanya alat bantu eksekusi. Pencatatan SQL Tracker tetap per change ID.
