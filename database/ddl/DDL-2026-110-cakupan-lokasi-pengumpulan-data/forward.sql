-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-110-cakupan-lokasi-pengumpulan-data
--
-- Membuka kampanye Pembaruan Data Karyawan PER LOKASI KERJA
-- (Karyawan.Cabang_Untuk_Gaji), bukan serentak untuk seluruh karyawan aktif.
--
-- Kolom ini adalah CAKUPAN kampanye, bukan saringan tampilan. Ia menentukan
-- siapa yang wajib mengisi: siapa yang dihitung di ringkasan, siapa yang kena
-- modal dan pengalihan halaman, siapa yang dikirimi pengingat, dan siapa yang
-- ikut terekspor. Saringan lokasi di tabel admin adalah hal terpisah dan tidak
-- menyentuh kolom ini.
--
-- Sengaja BUKAN penanda per baris di N_HRIS_Pengumpulan_Data_Status. Menandai
-- orang di luar cakupan satu per satu berarti menulis ribuan baris setiap kali
-- cakupan digeser, membuat pergeseran cakupan mahal, dan mengotori jejak audit
-- pengecualian individual — padahal 'dikecualikan' punya arti lain: keputusan
-- admin atas SATU orang, bukan gelombang pembukaan.
--
-- Isinya array JSON berisi nilai Cabang_Untuk_Gaji yang sudah dinormalkan
-- (huruf besar, tanpa spasi pinggir), contoh: ["EMI","JKT"].
--
-- NULL atau array kosong DIBACA SEBAGAI 'seluruh lokasi'. Itu perilaku hari ini,
-- jadi menaikkan perubahan ini tanpa mengisi apa pun tidak mengubah satu pun
-- keputusan yang sudah berjalan.
--
-- VARCHAR, bukan NVARCHAR: nilai Cabang_Untuk_Gaji di tabel induk adalah
-- varchar, dan menyimpan salinannya sebagai nvarchar hanya mengundang konversi
-- implisit saat keduanya dibandingkan.

IF COL_LENGTH('N_HRIS_Pengumpulan_Data_Config', 'Cakupan_Lokasi') IS NULL
BEGIN
    ALTER TABLE N_HRIS_Pengumpulan_Data_Config ADD Cakupan_Lokasi VARCHAR(2000) NULL;
END
GO
