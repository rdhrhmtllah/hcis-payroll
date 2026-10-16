# DDL-2026-138-visitor-koreksi-permit

## Ringkas

Verifikator yang memegang giliran approval dapat **mengoreksi data permit karyawan** yang masih PENDING — jenis permit (antar kategori karyawan), plat, model, warna, tahun, keterangan — tanpa menolak dan menunggu pengajuan ulang. Setiap field yang berubah dicatat beserta alasan dan pelakunya.

| | |
|---|---|
| Change ID | `DDL-2026-138-visitor-koreksi-permit` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Status | `DEV_EXECUTED` |
| Checksum `forward.sql` (sha256) | `18b32fba5652d334d3b3a76b1d3ef06bcc1b55dbe8f6810d83d34b9c9319a2b1` |

## Objek

| Objek | Perubahan |
|---|---|
| `dbo.N_EMI_VISITOR_Transaksi_Permit_Koreksi` | tabel baru |
| `IX_VISITOR_Permit_Koreksi_No_Transaksi` | indeks baru |

Aditif; tidak ada kolom atau data lama yang disentuh. Dokumentasi tabel: `docs/database/tables/visitor/n-emi-visitor-transaksi-permit-koreksi.md`. Desain: `docs/superpowers/specs/2026-09-23-visitor-koreksi-permit-review-design.md`.

## Berkas

- `database/ddl/DDL-2026-138-visitor-koreksi-permit/forward.sql`
- `database/ddl/DDL-2026-138-visitor-koreksi-permit/drop.sql` (alat bantu manual, tidak dijalankan agen)

## Agent completed

- DDL ditulis dan, atas persetujuan user, dijalankan di `Web_HRIS` development pada 2026-09-23. Verifikasi `sys.columns`: 9 kolom sesuai `forward.sql`; indeks ada.
- Belum dicatat ke SQL Tracker (tugas user).

## User tasks

1. Review `forward.sql`.
2. Catat `forward.sql` yang sama persis (checksum di atas) ke SQL Tracker.
3. Eksekusi ke production dari SQL Tracker **sebelum** kode fitur ini naik production — endpoint `showPermit` membaca tabel ini.
4. Verifikasi di production: tabel dan indeks ada.
