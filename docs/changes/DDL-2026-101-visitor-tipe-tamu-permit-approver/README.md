# DDL-2026-101-visitor-tipe-tamu-permit-approver

> **Sebagian digantikan `DDL-2026-103`.** Permit COKLAT Tamu B2B kini diajukan
> sebagai transaksi permit dan menunggu persetujuan verifikator; masa berlakunya
> dipaku satu hari kalender pada tanggal kunjungan, tidak lagi ditentukan
> approver saat menyetujui.

## Ringkas

Menambahkan kolom `Tipe_Tamu` (`varchar(20) NULL`) ke tabel transaksi detail tamu dan proyeksi tamu efektif untuk membedakan **Tamu REGULAR** dan **Tamu B2B**.

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | + `Tipe_Tamu` (`varchar(20) NULL`) + `CK_..._Tipe_Tamu` (`REGULAR`, `B2B`) |
| `N_VISITOR_Tamu_Efektif` | + `Tipe_Tamu` (`varchar(20) NULL`) + `CK_..._Tipe_Tamu` (`REGULAR`, `B2B`) |

## Keputusan desain

1. Tamu Regular hanya memerlukan tanggal kunjungan. Permit COKLAT tidak diterbitkan.
2. Tamu B2B memerlukan permit COKLAT. Jendela jam berlaku permit COKLAT ditentukan oleh Approver/Verifikator saat menyetujui pengajuan.
3. Data lama di-backfill menjadi `'REGULAR'`.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-101-visitor-tipe-tamu-permit-approver/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-101-visitor-tipe-tamu-permit-approver/drop.sql` |
| DML | `database/dml/DDL-2026-101-visitor-tipe-tamu-permit-approver/data-change.sql` |
| Rencana | `docs/superpowers/plans/2026-09-11-tipe-tamu-dan-permit-coklat-approver.md` |

## Status

Development: **DEV_EXECUTED** (2026-09-11, `Web_HRIS`).

Checksum forward DDL (SHA-256): `0b45fa6a110db478fc40343a36bab9f40f730e0b60c101a678dccb28dd4d7a95`

Verifikasi: 2 kolom `Tipe_Tamu varchar(20)` terbentuk; DML backfill selesai. PHPUnit targeted 10 tes/26 assertion hijau; Vitest 14 tes hijau; Pint dan seluruh structural/database/release guard hijau.

## Pekerjaan user

- Rekam DDL dan DML ini di SQL Tracker, jalankan ke production, lalu verifikasi.
- Rilis kode backend dan frontend setelah DDL/DML production selesai.
