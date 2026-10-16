-- database/ddl/DDL-2026-014-riwayat-kepatuhan-sebab-aktor/forward.sql
-- Target: Web_HRIS (development). Jalankan manual di production lewat SQL Tracker.
-- Dokumentasi: docs/changes/DDL-2026-014-riwayat-kepatuhan-sebab-aktor/README.md
--
-- Seluruh pernyataan di berkas ini bersifat MENAMBAH. Tidak ada kolom, baris,
-- indeks, atau constraint yang dihapus atau ditimpa.

-- 1) Sebab kejadian, versi yang boleh berkembang.
--    Kolom lama `Alasan` terkunci CHECK CK_N_SOP_Rwt_Alasan ke lima nilai yang
--    semuanya menjelaskan "kenapa penugasan dibuka lagi" — tak satu pun bisa
--    menjelaskan "kenapa akses dicabut". Kolom ini sengaja TANPA CHECK, mengikuti
--    docs/database/table-design-rules.md: daftar nilai bisnis yang dapat
--    berkembang divalidasi di backend, bukan dikunci di skema.
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Sebab')
ALTER TABLE N_SOP_Kepatuhan_Riwayat ADD Sebab varchar(40) NULL;
GO

-- 2) Penjelasan bebas dari admin. Tanpa ini, "SALAH_TARGET" tak pernah bisa
--    menyebut target mana yang salah.
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Catatan')
ALTER TABLE N_SOP_Kepatuhan_Riwayat ADD Catatan nvarchar(500) NULL;
GO

-- 3) Pelaku. Sebelum ini tabel riwayat tidak punya kolom aktor sama sekali,
--    sehingga "dicabut oleh siapa" mustahil dijawab. Menumpang
--    N_SOP_Penjadwalan_Detail.Updated_By tidak bisa menggantikannya: baris detail
--    ikut terhapus bersama penjadwalannya — pada data development hanya 2 dari
--    135 baris DICABUT yang detailnya masih ada.
--
--    Aktor_Id merujuk KPI_Users.Id_Users (int) — identifier yang sama dengan
--    yang sudah ditulis ke N_SOP_Penjadwalan_Detail.Updated_By. TANPA foreign key:
--    baris audit harus tetap terbaca meski akun pelakunya kelak dihapus.
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Aktor_Id')
ALTER TABLE N_SOP_Kepatuhan_Riwayat ADD Aktor_Id int NULL;
GO

-- 4) Nama pelaku disalin saat kejadian, bukan di-join saat dibaca.
--    Akun bisa berganti nama atau dinonaktifkan; catatan audit tidak boleh ikut
--    berubah setiap kali master user disunting. NULL berarti bukan tindakan
--    manusia (mesin aturan otomatis, rekonsiliasi terbit).
IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat') AND name = 'Aktor_Nama')
ALTER TABLE N_SOP_Kepatuhan_Riwayat ADD Aktor_Nama varchar(100) NULL;
GO

-- 5) Penelusuran "apa saja yang dilakukan admin X" dan "semua pencabutan
--    bersebab Y" adalah dua pertanyaan audit yang paling sering diajukan.
IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Rwt_Aktor' AND object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat'))
CREATE INDEX IX_SOP_Rwt_Aktor
    ON N_SOP_Kepatuhan_Riwayat (Aktor_Id, Waktu_Peristiwa DESC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE name = 'IX_SOP_Rwt_Sebab' AND object_id = OBJECT_ID('N_SOP_Kepatuhan_Riwayat'))
CREATE INDEX IX_SOP_Rwt_Sebab
    ON N_SOP_Kepatuhan_Riwayat (Peristiwa, Sebab);
GO
