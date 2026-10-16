# N_VISITOR_Tamu_Efektif

Proyeksi operasional kunjungan tamu yang telah efektif. Aplikasi gate eksternal menjadi sumber perubahan status operasional dan mengisi `Waktu_Aktual_Masuk`/`Waktu_Aktual_Keluar`; HCIS membaca dan menampilkannya.

- Status lifecycle: `AKTIF`, `CHECKED_IN`, `CHECKED_OUT`, `DIBATALKAN`, `EXPIRED`.
- Aktor teknis default integrasi eksternal: `GATE_EXTERNAL` sampai identitas aktor disepakati.
- Relasi bisnis memakai `No_Transaksi` ke header/detail tamu.
- Kolom waktu aktual nullable karena belum ada event gate saat proyeksi dibuat.
- Rentang & jenis kunjungan (`DDL-2026-126`): `Waktu_Estimasi_Masuk` / `Waktu_Estimasi_Keluar` (rentang rencana; pasangan dari `Waktu_Aktual_Masuk/Keluar` yang ditulis palang; batas berlaku kunjungan = `Waktu_Estimasi_Keluar`), `Tamu_Kategori` (`VISITOR`/`MITRA`, MITRA boleh keluar-masuk), `B2B_Unit` (`PROCUREMENT`/`LOGISTIC`), `Path_Foto_Kendaraan`. Ditulis `VisitorEfektifService::syncTamuEfektif` dari Detail kunjungan; baris lama tanpa estimasi bernilai `NULL` (cadangan: `Tanggal_Kunjungan` s.d. +1 hari).
- Bypass palang: `Flag_ByPass_CheckIn` / `User_Bypass_CheckIn` dan pasangan `CheckOut`-nya ditulis aplikasi gate ketika kartu gagal terbaca dan petugas membuka palang manual. Penandanya `'Y'` atau `NULL` — **bukan** `'T'` — dan kolom `User_*` adalah teks bebas nama petugas, bukan `Kode_Karyawan`; jangan di-join ke `Karyawan`. Nama petugas tidak dikosongkan saat bypass tidak lagi berlaku, sehingga hanya boleh dibaca bila penandanya `'Y'` (lihat `VisitorKehadiranQueryService::bypassMasukOlehSql`).
- Perubahan: `DDL-2026-020-visitor-lifecycle-idempotency`, `DDL-2026-126-visitor-tamu-efektif-lengkap`.
- SQL: `database/ddl/DDL-2026-020-visitor-lifecycle-idempotency/forward.sql`.
- Pemakai aktual: `VisitorEfektifService`, controller registrasi/verifikator/laporan Visitor dan aplikasi gate eksternal (di luar repository).
