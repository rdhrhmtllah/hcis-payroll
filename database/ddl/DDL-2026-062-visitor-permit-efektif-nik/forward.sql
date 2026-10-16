-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-062-visitor-permit-efektif-nik
--
-- Kolom N_VISITOR_Permit_Efektif.NIK sudah ada di development tetapi tidak
-- pernah tercatat sebagai change apa pun: berkas pembuat tabelnya,
-- database/visitor/2026_07_30_visitor_efektif_projection.sql, tidak
-- menyebutkannya sama sekali. Kolomnya menempel di urutan paling akhir
-- (sesudah Synced_At), tanda bahwa ia ditambahkan di luar alur DDL.
--
-- Berkas ini mencatatnya secara retroaktif supaya ada jalur resmi ke
-- production. Tanpa ini, kode yang mulai mengisi NIK akan gagal di production
-- dengan "Invalid column name 'NIK'" pada setiap penyetujuan permit.
--
-- Isi kolom: nomor induk karyawan pemohon, disalin dari
-- Karyawan.No_Induk_Karyawan. BUKAN NIK KTP.
--
-- Ukuran: sumbernya varchar(30), kolom ini varchar(20). Nilai terpanjang yang
-- ada di Karyawan pada 2026-09-01 adalah 13 karakter, jadi tidak ada
-- pemotongan. Panjang 20 dipertahankan agar identik dengan development;
-- melebarkannya akan membuat dev dan production berbeda lagi.
--
-- Nullable disengaja: 63 dari 965 baris Karyawan punya No_Induk_Karyawan
-- kosong, sehingga NOT NULL akan menolak permit yang sah.
--
-- Tidak ada perubahan data di berkas ini. Pengisian baris lama ada di
-- database/dml/DDL-2026-062-visitor-permit-efektif-nik/data-change.sql dan
-- dijalankan terpisah. Pernyataan di bawah idempoten.

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'NIK') IS NULL
ALTER TABLE N_VISITOR_Permit_Efektif ADD NIK varchar(20) NULL;
GO
