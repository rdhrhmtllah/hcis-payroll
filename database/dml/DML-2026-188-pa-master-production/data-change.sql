/* Change ID: DML-2026-188-pa-master-production
   Target: PRODUCTION (emi_db_web_hr) melalui SQL Tracker. Bergantung pada DDL-2026-187-pa-skema-lengkap-production.
   Scope: data MASTER Performance Appraisal dalam keadaan bersih (14 Okt 2026). Tidak memuat data
          transaksi (siklus, peserta, penugasan, hasil), relasi organisasi per karyawan, koreksi Level PA
          per karyawan, maupun Matriks Soal (ikatan ujian HC Learn production dipetakan lewat layar).
   Keputusan pemilik proyek (14 Okt 2026): tipe relasi hanya ATASAN/BAWAHAN/PEER; level dikecualikan hanya
          FREELANCE, NOT SET, BELUM, ADVISOR; grup kurva STAFF / OFFICER / MANAJERIAL; Expected Behaviour
          contoh dikosongkan (menunggu dokumen resmi HC).
   N_PA_Level diisi dari HRIS_Level PRODUCTION saat dijalankan (aturan sama dengan
          PaLevelMasterService::sinkronHris), ditambah level khusus PA OFFICER SPECIALIST.
   ID master dibawa dari development (tabel production masih kosong); kolom pelaku (*_By) NULL.
   Satu batch, satu transaksi: seluruhnya masuk atau tidak sama sekali.
   Dokumentasi: docs/changes/DML-2026-188-pa-master-production/README.md
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

/* Prasyarat 1: skema PA (DDL-2026-187) sudah dibuat. */
IF OBJECT_ID('N_PA_Rater_Category', 'U') IS NULL OR OBJECT_ID('N_PA_Level', 'U') IS NULL OR OBJECT_ID('N_HRIS_Organization_Relationship_Type', 'U') IS NULL
    THROW 50188, 'DML-2026-188-pa-master-production: skema PA belum ada. Jalankan DDL-2026-187-pa-skema-lengkap-production lebih dulu.', 1;

/* Prasyarat 2: master masih kosong — mencegah dijalankan dua kali. */
IF EXISTS (SELECT 1 FROM N_PA_Rater_Category) OR EXISTS (SELECT 1 FROM N_PA_Level) OR EXISTS (SELECT 1 FROM N_PA_Competency)
    THROW 50188, 'DML-2026-188-pa-master-production: master PA sudah berisi; DML ini tidak dijalankan ulang.', 1;

/* Prasyarat 3: setiap level yang dipakai konfigurasi ada di HRIS_Level production dengan NAMA YANG SAMA.
   ID level development dan production terbukti sama pada 14 Okt 2026; penjaga ini menolak bila berubah. */
IF EXISTS (
    SELECT 1 FROM (VALUES (1, N'NON CLERK/PRAMUKARYA'), (2, N'STAFF'), (3, N'SENIOR STAFF'), (4, N'OFFICER'), (5, N'SENIOR OFFICER'), (6, N'ASSISTEN MANAGER'), (7, N'SENIOR ASSISTEN MANAGER'), (8, N'MANAGER'), (9, N'SENIOR MANAGER'), (10, N'GENERAL MANAGER'), (11, N'SENIOR GENERAL MANAGER'), (12, N'DIREKTUR'), (13, N'DIREKTUR UTAMA'), (14, N'NOT SET'), (15, N'BELUM'), (21, N'FREELANCE')) v(ID_Level, Nama)
    WHERE NOT EXISTS (SELECT 1 FROM HRIS_Level h WHERE h.ID_Level = v.ID_Level AND UPPER(LTRIM(RTRIM(h.Keterangan))) = v.Nama)
)
    THROW 50188, 'DML-2026-188-pa-master-production: ID/nama HRIS_Level production tidak sama dengan yang dipetakan. Dihentikan.', 1;

BEGIN TRANSACTION;

    /* 1. Status & lookup */
    /* N_HRIS_Organization_Relationship_Type — 3 baris */
    SET IDENTITY_INSERT N_HRIS_Organization_Relationship_Type ON;
    INSERT INTO N_HRIS_Organization_Relationship_Type (Id_Organization_Relationship_Type, Kode_Relasi, Nama_Relasi, Deskripsi, Urutan, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By, Flag_Relasi_Setara, Flag_Pembentuk_Struktur_Organisasi) VALUES
        (6, 'PEER', N'Rekan Kerja', N'Subject dan Related Employee mempunyai kedudukan setara sebagai peer.', 30, 'Y', SYSDATETIME(), NULL, NULL, NULL, 'Y', 'T'),
        (9, 'ATASAN', N'Atasan', N'Related Employee adalah atasan Subject Employee.', 10, 'Y', SYSDATETIME(), NULL, NULL, NULL, 'T', 'Y'),
        (1010, 'BAWAHAN', N'Bawahan', N'Related Employee adalah bawahan Subject Employee.', 20, 'Y', SYSDATETIME(), NULL, NULL, NULL, 'T', 'Y');
    SET IDENTITY_INSERT N_HRIS_Organization_Relationship_Type OFF;

    /* N_PA_Assignment_Source — 2 baris */
    SET IDENTITY_INSERT N_PA_Assignment_Source ON;
    INSERT INTO N_PA_Assignment_Source (Id_PA_Assignment_Source, Kode_Sumber, Nama_Sumber, Deskripsi, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'USULAN', N'Usulan Sistem', N'Lahir dari resolver relasi organisasi lalu disetujui admin. Barisnya menyimpan jejak relasi asalnya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'MANUAL', N'Ditunjuk Admin', N'Admin menunjuk penilainya sendiri tanpa dasar relasi organisasi. Alasannya wajib ditulis aplikasi.', 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Assignment_Source OFF;

    /* N_PA_Assignment_Status — 6 baris */
    SET IDENTITY_INSERT N_PA_Assignment_Status ON;
    INSERT INTO N_PA_Assignment_Status (Id_PA_Assignment_Status, Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'BELUM_MULAI', N'Belum Mulai', N'Penugasan sudah dibentuk tetapi penilainya belum membuka form asesmen.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'BERJALAN', N'Berjalan', N'Penilai sudah membuka form di HC Learn tetapi belum menyetorkan hasilnya.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'SELESAI', N'Selesai', N'Hasil sudah diterima lewat callback dan tersimpan sebagai payload mentah.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 'DIBATALKAN', N'Dibatalkan', N'Penugasan dicabut sebelum atau sesudah pengisian. Jawaban yang telanjur masuk tetap tersimpan tetapi berhenti ikut dihitung, dan bobot penilai lain dalam kategori yang sama wajib dinormalkan ulang.', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 'MENUNGGU_VERIFIKASI', N'Menunggu Verifikasi', N'Penilai sudah menyetorkan hasilnya, tetapi angkanya belum disahkan admin HC Learn. Nilainya BELUM ikut dihitung dan peserta belum terhitung lengkap; yang berhenti hanyalah pengingat, karena orangnya memang sudah mengerjakan bagiannya. Status ini berganti menjadi SELESAI saat kiriman FINAL datang, atau kembali ke BELUM_MULAI bila verifikasinya menolak.', 5, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 'TERKUMPUL', N'Terisi, Belum Dikirim', N'Penilai sudah mengisi lembar ini sampai penuh dan jawabannya tersimpan utuh, tetapi ia belum mengirim seluruh tugasnya di siklus ini. Nilainya BELUM ikut dihitung dan pesertanya masih terbaca belum lengkap; pengingat pengisian berhenti karena pekerjaannya memang sudah dilakukan. Dilepas menjadi SELESAI oleh PaRaterSubmitService - lewat tombol kirim penilai, pelepasan paksa admin, atau penutupan pengisian siklus.', 6, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Assignment_Status OFF;

    /* N_PA_Cycle_Status — 7 baris */
    SET IDENTITY_INSERT N_PA_Cycle_Status ON;
    INSERT INTO N_PA_Cycle_Status (Id_PA_Cycle_Status, Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'DRAFT', N'Draft', N'Baru dibuat. Tanggal boleh masih kosong dan belum ada yang dapat dijalankan.', 1, 'DRAFT', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'PREPARED', N'Disiapkan', N'Peserta dan seluruh salinan sudah dibentuk dan masih boleh diubah. Penetapan penilai berjalan di sini.', 2, 'ACTIVE', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'LOCKED', N'Dikunci', N'Salinan aturan penilai dibekukan dan penilai tidak dapat diubah lagi. Keranjang kasus tertahan wajib bersih sebelum masuk keadaan ini.', 3, 'ACTIVE', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 'OPEN', N'Pengisian Dibuka', N'Jendela pengisian terbuka dan penilai boleh mengisi. Berbeda dari Dikunci walaupun sering berdekatan waktunya.', 4, 'ACTIVE', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 'CLOSED', N'Pengisian Ditutup', N'Tidak ada nilai baru yang masuk. Perhitungan, kalibrasi, dan review berjalan di sini; kemajuannya dibaca dari status hasil dan mesin approval, bukan dari status siklus.', 5, 'CLOSED', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 'PUBLISHED', N'Terbit', N'Hasil sudah diterbitkan dan karyawan boleh melihat kesimpulan akhirnya.', 6, 'CLOSED', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (7, 'ARCHIVED', N'Diarsipkan', N'Siklus selesai dan disimpan sebagai riwayat. Hanya-baca dan hilang dari daftar aktif.', 7, 'ARCHIVED', 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Cycle_Status OFF;

    /* N_PA_Participant_Status — 3 baris */
    SET IDENTITY_INSERT N_PA_Participant_Status ON;
    INSERT INTO N_PA_Participant_Status (Id_PA_Participant_Status, Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'TERTUNDA', N'Tertunda', N'Baris peserta sudah ada tetapi level atau aturan penilainya belum teresolusi. Inilah isi keranjang yang memblokir penguncian siklus.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'DIIKUTKAN', N'Diikutkan', N'Kondisi organisasi dan aturan penilai sudah teresolusi; peserta ikut dinilai pada siklus ini.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'DIKECUALIKAN', N'Dikecualikan', N'Sengaja tidak dinilai pada siklus ini. Alasannya wajib ditulis oleh aplikasi.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Participant_Status OFF;

    /* N_PA_Rater_Policy_Status — 3 baris */
    SET IDENTITY_INSERT N_PA_Rater_Policy_Status ON;
    INSERT INTO N_PA_Rater_Policy_Status (Id_PA_Rater_Policy_Status, Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'DRAFT', N'Draft', N'Sedang disusun. Bobot antar kategori belum wajib berjumlah 100 dan policy belum boleh dipakai siklus.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'ACTIVE', N'Berlaku', N'Sudah diaktifkan dan boleh disalin ke siklus. Total bobot kategori aktif wajib 100 sebelum masuk keadaan ini.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'INACTIVE', N'Tidak Berlaku', N'Digantikan versi yang lebih baru atau dihentikan pemakaiannya. Siklus lama yang sudah menyalinnya tidak terpengaruh.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Rater_Policy_Status OFF;

    /* N_PA_Result_Status — 4 baris */
    SET IDENTITY_INSERT N_PA_Result_Status ON;
    INSERT INTO N_PA_Result_Status (Id_PA_Result_Status, Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'BELUM_DIHITUNG', N'Belum Dihitung', N'Baris hasil sudah ada bersamaan dengan pesertanya, tetapi perhitungan belum pernah dijalankan. Keadaan inilah yang membuat ketiadaan angka punya sebab yang terbaca.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'DIHITUNG', N'Dihitung', N'Nilai akhir dan grade sudah terbentuk, tetapi belum diterbitkan kepada karyawan.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'TERBIT', N'Terbit', N'Hasil sudah dapat dilihat karyawan yang bersangkutan.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 'TERTAHAN', N'Tertahan', N'Cabang, bukan tahap. Penilainya kurang dari ambang minimum, sehingga hasilnya sengaja tidak diberi angka yang tampak wajar padahal datanya bolong. Alasannya wajib ditulis.', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Result_Status OFF;

    /* N_PA_Result_Note_Type — 2 baris */
    SET IDENTITY_INSERT N_PA_Result_Note_Type ON;
    INSERT INTO N_PA_Result_Note_Type (Id_PA_Result_Note_Type, Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'STRENGTH', N'Kekuatan', N'Hal yang menonjol dan layak dipertahankan.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'DEVELOPMENT_AREA', N'Area Pengembangan', N'Hal yang perlu ditingkatkan pada periode berikutnya.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Result_Note_Type OFF;

    /* N_PA_Score_Type — 3 baris */
    SET IDENTITY_INSERT N_PA_Score_Type ON;
    INSERT INTO N_PA_Score_Type (Id_PA_Score_Sumbu, Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'KPI', N'Nilai KPI', N'Sumbu hasil kerja. Berjalan pada rentang 50 sampai 125 di modul KPI.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'KOMPETENSI', N'Nilai Kompetensi', N'Sumbu hasil penilaian 360 derajat.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'FINAL', N'Nilai Akhir', N'Gabungan kedua pilar sesuai bobot pilar per level.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Score_Type OFF;

    /* N_PA_Score_Usage — 3 baris */
    SET IDENTITY_INSERT N_PA_Score_Usage ON;
    INSERT INTO N_PA_Score_Usage (Id_PA_Score_Kegunaan, Kode_Usage, Nama_Usage, Deskripsi, Flag_Pakai_Nilai_Hasil, Flag_Pakai_Target, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'HAVMAP', N'Pita HAV Map', N'Tiga tingkat Tinggi, Sedang, dan Rendah yang menentukan kotak seseorang pada 9-box.', 'T', 'T', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'GRADE', N'Pita Grade', N'Batas grade akhir beserta target distribusinya untuk kalibrasi.', 'T', 'Y', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'KONVERSI', N'Konversi Skala', N'Pemetaan nilai dari satu skala ke skala lain; hasilnya di kolom Nilai_Hasil.', 'Y', 'T', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Score_Usage OFF;

    /* N_PA_Rater_Category — 4 baris */
    SET IDENTITY_INSERT N_PA_Rater_Category ON;
    INSERT INTO N_PA_Rater_Category (Id_PA_Rater_Category, Kode_Kategori, Nama_Kategori, Deskripsi, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By, Urutan_Pengisian, Flag_Boleh_Kalibrasi) VALUES
        (1, 'ATASAN', N'Atasan', N'Rater menilai sebagai atasan participant.', 'Y', SYSDATETIME(), NULL, NULL, NULL, 2, 'Y'),
        (2, 'BAWAHAN', N'Bawahan', N'Rater menilai sebagai bawahan participant.', 'Y', SYSDATETIME(), NULL, NULL, NULL, 4, 'Y'),
        (3, 'PEER', N'Rekan Sejawat', N'Rater menilai sebagai rekan sejawat participant.', 'Y', SYSDATETIME(), NULL, NULL, NULL, 3, 'Y'),
        (4, 'SELF', N'Diri Sendiri', N'Participant menilai dirinya sendiri.', 'Y', SYSDATETIME(), NULL, NULL, NULL, 1, 'T');
    SET IDENTITY_INSERT N_PA_Rater_Category OFF;

    /* N_PA_Hav_Quadrant — 9 baris */
    SET IDENTITY_INSERT N_PA_Hav_Quadrant ON;
    INSERT INTO N_PA_Hav_Quadrant (Id_PA_Hav_Quadrant, Kode_Kuadran, Nama_Kuadran, Nomor_Box, Tingkat_Performa, Tingkat_Kompetensi, Deskripsi, Warna, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'STRIVING_STAR', N'Striving Star', 1, 'TINGGI', 'TINGGI', N'Performa dan kompetensi sama-sama tinggi.', NULL, 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'FUTURE_STAR_A', N'Future Star', 2, 'TINGGI', 'SEDANG', N'Performa tinggi dengan kompetensi sedang.', NULL, 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'FUTURE_STAR_B', N'Future Star', 3, 'SEDANG', 'TINGGI', N'Kompetensi tinggi dengan performa sedang.', NULL, 3, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 'POTENTIAL_CANDIDATE', N'Potential Candidate', 4, 'SEDANG', 'SEDANG', N'Performa dan kompetensi sama-sama sedang.', NULL, 4, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 'TOP_PERFORMER', N'Top Performer', 5, 'TINGGI', 'RENDAH', N'Performa tinggi tetapi kompetensi rendah.', NULL, 5, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 'CONTRIBUTOR', N'Contributor', 6, 'SEDANG', 'RENDAH', N'Performa sedang dengan kompetensi rendah.', NULL, 6, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (7, 'RAW_DIAMOND', N'Raw Diamond', 7, 'RENDAH', 'TINGGI', N'Kompetensi tinggi tetapi performa rendah.', NULL, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (8, 'PROBLEM_EMPLOYEE', N'Problem Employee', 8, 'RENDAH', 'SEDANG', N'Performa rendah dengan kompetensi sedang.', NULL, 8, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (9, 'DEADWOOD', N'Deadwood', 9, 'RENDAH', 'RENDAH', N'Performa dan kompetensi sama-sama rendah.', NULL, 9, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Hav_Quadrant OFF;

    /* N_PA_Score_Range — 12 baris */
    SET IDENTITY_INSERT N_PA_Score_Range ON;
    INSERT INTO N_PA_Score_Range (Id_PA_Score_Range, Score_Type, Score_Usage, Kode_Range, Nama_Range, Nilai_Dari, Nilai_Sampai, Nilai_Hasil, Target_Persen, Warna, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By, Batas_Z) VALUES
        (7, 'KPI', 'HAVMAP', 'RENDAH', N'Performa Rendah', .00, 70.00, NULL, NULL, '#EF4444', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (8, 'KPI', 'HAVMAP', 'SEDANG', N'Performa Sedang', 70.00, 85.00, NULL, NULL, '#F59E0B', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (9, 'KPI', 'HAVMAP', 'TINGGI', N'Performa Tinggi', 85.00, 999.99, NULL, NULL, '#22C55E', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (10, 'KOMPETENSI', 'HAVMAP', 'RENDAH', N'Kompetensi Rendah', .00, 70.00, NULL, NULL, '#EF4444', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (11, 'KOMPETENSI', 'HAVMAP', 'SEDANG', N'Kompetensi Sedang', 70.00, 85.00, NULL, NULL, '#F59E0B', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (12, 'KOMPETENSI', 'HAVMAP', 'TINGGI', N'Kompetensi Tinggi', 85.00, 999.99, NULL, NULL, '#22C55E', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL),
        (13, 'FINAL', 'GRADE', 'A', N'Istimewa', 90.00, 999.99, NULL, 10.00, '#16A34A', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, 2.00),
        (14, 'FINAL', 'GRADE', 'B_PLUS', N'Sangat Baik', 80.00, 90.00, NULL, 20.00, '#4ADE80', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, 1.00),
        (15, 'FINAL', 'GRADE', 'B', N'Baik', 70.00, 80.00, NULL, 40.00, '#A3E635', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, .00),
        (16, 'FINAL', 'GRADE', 'C_PLUS', N'Cukup', 60.00, 70.00, NULL, 20.00, '#FACC15', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL, -1.00),
        (17, 'FINAL', 'GRADE', 'C', N'Kurang', 50.00, 60.00, NULL, 8.00, '#FB923C', 5, 'Y', SYSDATETIME(), NULL, NULL, NULL, -2.00),
        (18, 'FINAL', 'GRADE', 'D', N'Sangat Kurang', .00, 50.00, NULL, 2.00, '#EF4444', 6, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Score_Range OFF;

    /* 2. Kompetensi (jenis & kompetensi uji CONTOH1/FAMILY/CTH01 tidak dibawa) */
    /* N_PA_Competency_Type — 4 baris */
    SET IDENTITY_INSERT N_PA_Competency_Type ON;
    INSERT INTO N_PA_Competency_Type (Id_PA_Competency_Type, Kode_Type, Nama_Type, Deskripsi, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 'MANAJERIAL', N'Kompetensi Manajerial', N'Kompetensi manajerial berskor, sumber utama nilai kompetensi.', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 'CORE_VALUE', N'Core Value', N'Nilai inti perusahaan, dinilai berangka.', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 'BEHAVIOR', N'Behavior (Core Value)', N'Perilaku turunan core value untuk band Pramukarya.', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 'KUALITATIF', N'Qualitative Question', N'Butir bertekstual tanpa angka; tidak ikut perhitungan.', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Competency_Type OFF;

    /* N_PA_Competency — 24 baris */
    SET IDENTITY_INSERT N_PA_Competency ON;
    INSERT INTO N_PA_Competency (Id_PA_Competency, PA_Competency_Type_Id, Kode_Competency, Nama_Competency, Deskripsi, Flag_Berskor, Urutan_Tampil, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By, Nilai_Ambang, Flag_Radar, Expected_Behaviour) VALUES
        (1, 1, 'K01', N'Analytical Thinking', N'Kemampuan mengidentifikasi akar masalah, menganalisis data secara terstruktur, dan menarik kesimpulan yang logis.', 'Y', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Memeriksa data dan fakta sebelum menyimpulkan
Memecah masalah besar menjadi bagian yang bisa dianalisis
Membedakan gejala dari akar penyebab
Menyajikan kesimpulan beserta dasar datanya'),
        (2, 1, 'K02', N'Problem Solving', N'Kemampuan merumuskan beberapa opsi pemecahan masalah dan menjalankan solusi sampai tuntas.', 'Y', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menawarkan lebih dari satu alternatif solusi
Menimbang untung-rugi tiap opsi sebelum memilih
Menindaklanjuti solusi sampai masalah benar-benar selesai
Mencegah masalah yang sama terulang'),
        (3, 1, 'K03', N'Decision Making', N'Ketepatan dan ketegasan mengambil keputusan berdasarkan data dan pertimbangan risiko.', 'Y', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Mengambil keputusan tepat waktu, tidak menunda tanpa alasan
Mempertimbangkan dampak dan risiko keputusan
Menjelaskan alasan di balik keputusan yang diambil
Bertanggung jawab atas akibat keputusannya'),
        (4, 1, 'K04', N'Planning and Organizing', N'Kemampuan menyusun rencana kerja, mengatur prioritas, dan mengelola sumber daya untuk mencapai target.', 'Y', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menyusun rencana kerja dengan target dan tenggat yang jelas
Menentukan prioritas berdasarkan urgensi dan dampak
Memantau kemajuan dan menyesuaikan rencana bila perlu
Memanfaatkan waktu dan sumber daya secara efisien'),
        (5, 1, 'K05', N'Achievement Orientation', N'Dorongan untuk mencapai hasil terbaik dan melampaui target yang ditetapkan.', 'Y', 5, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menetapkan target yang menantang namun realistis
Tetap gigih ketika menghadapi hambatan
Mengukur hasil kerja sendiri terhadap target
Mencari cara untuk meningkatkan hasil berikutnya'),
        (6, 1, 'K06', N'Customer Focus', N'Komitmen memahami dan memenuhi kebutuhan pelanggan internal maupun eksternal.', 'Y', 6, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menggali kebutuhan pelanggan sebelum memberi solusi
Merespons permintaan dan keluhan dengan cepat
Menjaga janji layanan yang sudah disepakati
Meminta umpan balik untuk perbaikan layanan'),
        (7, 1, 'K07', N'Impactful Communication', N'Kemampuan menyampaikan gagasan dan umpan balik secara jelas, lugas, dan berdampak.', 'Y', 7, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menyampaikan pesan secara runtut dan mudah dipahami
Menyesuaikan cara bicara dengan audiens
Mendengarkan aktif sebelum menanggapi
Memberi umpan balik yang spesifik dan membangun'),
        (8, 1, 'K08', N'Change & Adaptability', N'Kemampuan beradaptasi terhadap perubahan dan tetap efektif di situasi baru.', 'Y', 8, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menerima perubahan dengan sikap terbuka
Cepat mempelajari cara kerja atau sistem baru
Tetap produktif saat prioritas berubah
Membantu rekan menyesuaikan diri dengan perubahan'),
        (9, 1, 'K09', N'Teamwork & Collaboration', N'Keterampilan bekerja sama lintas fungsi untuk mencapai hasil yang lebih solid.', 'Y', 9, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Berbagi informasi yang dibutuhkan rekan kerja
Menawarkan bantuan tanpa harus diminta
Menghargai pendapat yang berbeda
Mengutamakan tujuan tim di atas kepentingan pribadi'),
        (10, 1, 'K10', N'Business Sense', N'Pemahaman tentang bisnis perusahaan dan dampak pekerjaan terhadap kinerja usaha.', 'Y', 10, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Memahami bagaimana pekerjaannya memengaruhi hasil bisnis
Mempertimbangkan biaya dan manfaat dalam bekerja
Mengikuti perkembangan pasar dan pesaing
Mengusulkan ide yang memberi nilai tambah bagi bisnis'),
        (11, 1, 'K11', N'Team Leadership', N'Kemampuan memotivasi, mengarahkan, dan mengembangkan potensi tim menuju sasaran bersama.', 'Y', 11, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menetapkan arah dan sasaran tim dengan jelas
Mendelegasikan tugas sesuai kemampuan anggota
Memberi pengakuan atas kontribusi anggota tim
Membimbing anggota tim untuk berkembang'),
        (12, 2, 'CV01', N'Focus on Customer', N'Mengutamakan kepuasan pelanggan dalam setiap pekerjaan sebagai nilai inti perusahaan.', 'Y', 1, 'T', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Mendahulukan kebutuhan pelanggan dalam bekerja
Menjaga kualitas produk dan layanan
Menangani keluhan pelanggan sampai tuntas'),
        (13, 2, 'CV02', N'Agility & Improvement', N'Kecepatan beradaptasi terhadap perubahan dan inisiatif melakukan perbaikan berkelanjutan.', 'Y', 2, 'T', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Mengusulkan perbaikan cara kerja
Cepat menyesuaikan diri dengan perubahan
Belajar dari kesalahan dan tidak mengulanginya'),
        (14, 2, 'CV03', N'Mutual Growth', N'Semangat bertumbuh bersama: saling mendukung, berbagi ilmu, dan maju bersama.', 'Y', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Berbagi pengetahuan dan pengalaman dengan rekan
Mendukung keberhasilan rekan kerja
Terbuka menerima masukan untuk berkembang'),
        (15, 2, 'CV04', N'Integrity', NULL, 'Y', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', NULL),
        (16, 2, 'CV05', N'Leadership', NULL, 'Y', 5, 'T', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', NULL),
        (17, 2, 'CV06', N'Yes, We Can Spirit', NULL, 'Y', 6, 'T', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', NULL),
        (18, 3, 'BH01', N'Discipline/Integrity', N'Kepatuhan pada aturan, kejujuran, dan konsistensi antara ucapan dan tindakan.', 'Y', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Hadir dan menyelesaikan pekerjaan tepat waktu
Mematuhi aturan dan prosedur perusahaan
Bertindak jujur dan melaporkan apa adanya'),
        (19, 3, 'BH02', N'Diligent/YWC', N'Ketekunan, keandalan, dan tanggung jawab penuh dalam menuntaskan pekerjaan.', 'Y', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Menyelesaikan pekerjaan sampai tuntas
Dapat diandalkan tanpa perlu diawasi terus
Teliti dan menjaga kualitas hasil kerja'),
        (20, 3, 'BH03', N'Empathy/Focus on Customer', N'Kepekaan terhadap kebutuhan orang lain dan fokus melayani pelanggan.', 'Y', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, 80.00, 'Y', N'Peka terhadap kebutuhan rekan dan pelanggan
Bersikap ramah dan sopan dalam melayani
Berusaha membantu menyelesaikan masalah orang lain'),
        (21, 4, 'QL01', N'Strength', N'Kekuatan utama karyawan yang menonjol selama periode penilaian, ditulis penilai sebagai uraian.', 'T', 1, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL, 'T', NULL),
        (22, 4, 'QL02', N'Development Area', N'Area yang perlu dikembangkan karyawan agar kinerjanya meningkat, ditulis penilai sebagai uraian.', 'T', 2, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL, 'T', NULL),
        (23, 4, 'QL03', N'Initiative', N'Inisiatif yang pernah diambil karyawan di luar tugas rutinnya selama periode penilaian.', 'T', 3, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL, 'T', NULL),
        (24, 4, 'QL04', N'Recommendation', N'Rekomendasi penilai untuk pengembangan karier atau penugasan karyawan berikutnya.', 'T', 4, 'Y', SYSDATETIME(), NULL, NULL, NULL, NULL, 'T', NULL);
    SET IDENTITY_INSERT N_PA_Competency OFF;

    /* N_PA_Competency_Proficiency — 83 baris */
    SET IDENTITY_INSERT N_PA_Competency_Proficiency ON;
    INSERT INTO N_PA_Competency_Proficiency (Id_PA_Competency_Proficiency, PA_Competency_Id, Tingkat, Nama_Tingkat, Expected_Behaviour, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (5, 15, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 15, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (7, 15, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (8, 16, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (9, 16, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (10, 16, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (11, 17, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (12, 17, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (13, 17, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (14, 12, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (15, 12, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (16, 12, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (17, 13, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (18, 13, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (19, 13, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (20, 14, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (21, 14, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (22, 14, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (23, 1, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (24, 1, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (25, 1, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (26, 1, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (27, 2, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (28, 2, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (29, 2, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (30, 2, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (31, 3, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (32, 3, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (33, 3, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (34, 3, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (35, 4, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (36, 4, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (37, 4, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (38, 4, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (39, 5, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (40, 5, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (41, 5, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (42, 5, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (43, 6, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (44, 6, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (45, 6, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (46, 6, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (47, 7, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (48, 7, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (49, 7, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (50, 7, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (51, 8, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (52, 8, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (53, 8, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (54, 8, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (55, 9, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (56, 9, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (57, 9, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (58, 9, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (59, 10, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (60, 10, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (61, 10, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (62, 10, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (63, 11, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (64, 11, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (65, 11, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (66, 11, 4, N'Expert', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (67, 18, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (68, 18, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (69, 18, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (70, 19, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (71, 19, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (72, 19, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (73, 20, 1, N'Foundational', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (74, 20, 2, N'Proficient', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (75, 20, 3, N'Role Model', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (76, 21, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (77, 21, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (78, 21, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (79, 22, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (80, 22, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (81, 22, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (82, 23, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (83, 23, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (84, 23, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (85, 24, 1, N'Basic', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (86, 24, 2, N'Intermediate', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (87, 24, 3, N'Advanced', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Competency_Proficiency OFF;

    /* 3. Level PA: seluruh HRIS_Level production (sama dengan sinkronHris) + level khusus PA */
    INSERT INTO N_PA_Level (ID_Level, Nama_Level, Sumber, ID_Level_HRIS_Dasar, Urutan, Flag_Aktif, Created_At)
    SELECT h.ID_Level, LTRIM(RTRIM(h.Keterangan)), 'HRIS', h.ID_Level, ISNULL(h.Level_Hierarchy, 0), 'Y', SYSDATETIME() FROM HRIS_Level h;
    /* N_PA_Level — 1 baris */
    INSERT INTO N_PA_Level (ID_Level, Nama_Level, Sumber, ID_Level_HRIS_Dasar, Urutan, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1000000, N'OFFICER SPECIALIST', 'PA', 4, 4.50, 'Y', SYSDATETIME(), NULL, NULL, NULL);

    /* 4. Kebutuhan kompetensi per level */
    /* N_PA_Competency_Requirement — 35 baris */
    SET IDENTITY_INSERT N_PA_Competency_Requirement ON;
    INSERT INTO N_PA_Competency_Requirement (Id_PA_Competency_Requirement, ID_Level, PA_Competency_Type_Id, Flag_Wajib, Catatan, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 2, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 3, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 4, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 5, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 6, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 7, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (7, 8, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (8, 9, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (9, 10, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (10, 11, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (11, 12, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (12, 13, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (13, 2, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (14, 3, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (15, 4, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (16, 5, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (18, 7, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (19, 8, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (20, 9, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (21, 10, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (22, 11, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (23, 12, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (24, 13, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (25, 1, 3, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (26, 6, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (27, 7, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (28, 8, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (29, 9, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (30, 10, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (31, 11, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (32, 12, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (33, 13, 4, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (37, 6, 3, 'Y', NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (59, 1000000, 1, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (60, 1000000, 2, 'Y', N'Deck PIC slide 9. Batas kelompok Leaders diambil mulai Assisten Manager; deck tidak menyatakannya.', 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Competency_Requirement OFF;

    /* N_PA_Competency_Level_Requirement — 205 baris */
    SET IDENTITY_INSERT N_PA_Competency_Level_Requirement ON;
    INSERT INTO N_PA_Competency_Level_Requirement (Id_PA_Competency_Level_Requirement, ID_Level, PA_Competency_Id, PA_Competency_Proficiency_Id, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (1, 2, 1, 23, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (2, 3, 1, 23, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 4, 1, 24, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 5, 1, 24, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 6, 1, 25, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 7, 1, 25, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (7, 8, 1, 25, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (8, 9, 1, 26, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (9, 10, 1, 26, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (10, 11, 1, 26, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (11, 12, 1, 26, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (12, 13, 1, 26, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (13, 2, 2, 27, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (14, 3, 2, 27, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (15, 4, 2, 28, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (16, 5, 2, 28, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (17, 6, 2, 29, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (18, 7, 2, 29, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (19, 8, 2, 29, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (20, 9, 2, 30, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (21, 10, 2, 30, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (22, 11, 2, 30, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (23, 12, 2, 30, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (24, 13, 2, 30, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (25, 2, 3, 31, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (26, 3, 3, 31, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (27, 4, 3, 32, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (28, 5, 3, 32, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (29, 6, 3, 33, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (30, 7, 3, 33, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (31, 8, 3, 33, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (32, 9, 3, 34, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (33, 10, 3, 34, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (34, 11, 3, 34, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (35, 12, 3, 34, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (36, 13, 3, 34, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (37, 2, 4, 35, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (38, 3, 4, 35, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (39, 4, 4, 36, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (40, 5, 4, 36, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (41, 6, 4, 37, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (42, 7, 4, 37, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (43, 8, 4, 37, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (44, 9, 4, 38, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (45, 10, 4, 38, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (46, 11, 4, 38, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (47, 12, 4, 38, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (48, 13, 4, 38, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (49, 2, 5, 39, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (50, 3, 5, 39, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (51, 4, 5, 40, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (52, 5, 5, 40, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (53, 6, 5, 41, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (54, 7, 5, 41, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (55, 8, 5, 41, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (56, 9, 5, 42, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (57, 10, 5, 42, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (58, 11, 5, 42, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (59, 12, 5, 42, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (60, 13, 5, 42, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (61, 2, 6, 43, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (62, 3, 6, 43, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (63, 4, 6, 44, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (64, 5, 6, 44, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (65, 6, 6, 45, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (66, 7, 6, 45, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (67, 8, 6, 45, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (68, 9, 6, 46, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (69, 10, 6, 46, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (70, 11, 6, 46, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (71, 12, 6, 46, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (72, 13, 6, 46, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (73, 2, 7, 47, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (74, 3, 7, 47, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (75, 4, 7, 48, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (76, 5, 7, 48, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (77, 6, 7, 49, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (78, 7, 7, 49, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (79, 8, 7, 49, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (80, 9, 7, 50, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (81, 10, 7, 50, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (82, 11, 7, 50, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (83, 12, 7, 50, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (84, 13, 7, 50, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (85, 2, 8, 51, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (86, 3, 8, 51, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (87, 4, 8, 52, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (88, 5, 8, 52, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (89, 6, 8, 53, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (90, 7, 8, 53, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (91, 8, 8, 53, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (92, 9, 8, 54, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (93, 10, 8, 54, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (94, 11, 8, 54, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (95, 12, 8, 54, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (96, 13, 8, 54, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (97, 2, 9, 55, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (98, 3, 9, 55, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (99, 4, 9, 56, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (100, 5, 9, 56, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (101, 6, 9, 57, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (102, 7, 9, 57, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (103, 8, 9, 57, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (104, 9, 9, 58, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (105, 10, 9, 58, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (106, 11, 9, 58, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (107, 12, 9, 58, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (108, 13, 9, 58, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (109, 2, 10, 59, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (110, 3, 10, 59, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (111, 4, 10, 60, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (112, 5, 10, 60, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (113, 6, 10, 61, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (114, 7, 10, 61, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (115, 8, 10, 61, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (116, 9, 10, 62, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (117, 10, 10, 62, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (118, 11, 10, 62, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (119, 12, 10, 62, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (120, 13, 10, 62, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (121, 2, 11, 63, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (122, 3, 11, 63, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (123, 4, 11, 64, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (124, 5, 11, 64, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (125, 6, 11, 65, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (126, 7, 11, 65, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (127, 8, 11, 65, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (128, 9, 11, 66, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (129, 10, 11, 66, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (130, 11, 11, 66, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (131, 12, 11, 66, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (132, 13, 11, 66, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (155, 2, 14, 20, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (156, 3, 14, 20, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (157, 4, 14, 21, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (159, 7, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (160, 8, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (161, 9, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (162, 10, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (163, 11, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (164, 12, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (165, 13, 14, 22, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (166, 1, 18, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (167, 6, 18, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (168, 1, 19, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (169, 6, 19, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (170, 1, 20, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (171, 6, 20, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (172, 6, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (173, 7, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (174, 8, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (175, 9, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (176, 10, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (177, 11, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (178, 12, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (179, 13, 21, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (180, 6, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (181, 7, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (182, 8, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (183, 9, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (184, 10, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (185, 11, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (186, 12, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (187, 13, 22, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (188, 6, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (189, 7, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (190, 8, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (191, 9, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (192, 10, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (193, 11, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (194, 12, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (195, 13, 23, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (196, 6, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (197, 7, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (198, 8, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (199, 9, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (200, 10, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (201, 11, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (202, 12, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (203, 13, 24, NULL, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (207, 5, 14, 21, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (348, 1000000, 1, 24, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (349, 1000000, 2, 28, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (350, 1000000, 3, 32, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (351, 1000000, 4, 36, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (352, 1000000, 5, 40, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (353, 1000000, 6, 44, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (354, 1000000, 7, 48, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (355, 1000000, 8, 52, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (356, 1000000, 9, 56, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (357, 1000000, 10, 60, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (358, 1000000, 11, 64, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (361, 1000000, 14, 21, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (444, 2, 15, 5, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (445, 3, 15, 5, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (446, 4, 15, 6, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (447, 7, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (448, 8, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (449, 9, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (450, 10, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    INSERT INTO N_PA_Competency_Level_Requirement (Id_PA_Competency_Level_Requirement, ID_Level, PA_Competency_Id, PA_Competency_Proficiency_Id, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (451, 11, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (452, 12, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (453, 13, 15, 7, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (454, 5, 15, 6, 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (455, 1000000, 15, 6, 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Competency_Level_Requirement OFF;

    /* 5. Bobot pilar & aturan penilai */
    /* N_PA_Pillar_Weight — 14 baris */
    SET IDENTITY_INSERT N_PA_Pillar_Weight ON;
    INSERT INTO N_PA_Pillar_Weight (Id_PA_Pillar_Weight, ID_Level, Bobot_KPI, Bobot_Kompetensi, Catatan, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (9, 1, .00, 100.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (10, 2, 80.00, 20.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (11, 3, 80.00, 20.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (12, 4, 75.00, 25.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (13, 5, 75.00, 25.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (14, 6, 70.00, 30.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (15, 7, 70.00, 30.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (16, 8, 65.00, 35.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (17, 9, 55.00, 45.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (18, 10, 55.00, 45.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (19, 11, 55.00, 45.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (20, 12, 50.00, 50.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (21, 13, 50.00, 50.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL),
        (40, 1000000, 75.00, 25.00, N'SEMENTARA. Deck hanya memberi empat pita dan salah satu namanya tidak ada di HRIS_Level; pemetaan sebenarnya diatur lewat halaman master.', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Pillar_Weight OFF;

    /* N_PA_Rater_Policy — 14 baris */
    SET IDENTITY_INSERT N_PA_Rater_Policy ON;
    INSERT INTO N_PA_Rater_Policy (Id_PA_Rater_Policy, Kode_Policy, Nama_Policy, Deskripsi, ID_Level, Versi, PA_Rater_Policy_Sumber_Id, Status_Policy, Berlaku_Dari, Berlaku_Sampai, Catatan_Versi, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By, Activated_At, Activated_By) VALUES
        (14, 'PA-RTR-PRAMUKARYA', N'Aturan Penilai — Non Clerk / Pramukarya', NULL, 1, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (15, 'PA-RTR-STAFF', N'Aturan Penilai — Staff', NULL, 2, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (16, 'PA-RTR-SRSTAFF', N'Aturan Penilai — Senior Staff', NULL, 3, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (17, 'PA-RTR-OFFICER', N'Aturan Penilai — Officer', NULL, 4, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (18, 'PA-RTR-SROFFICER', N'Aturan Penilai — Senior Officer', NULL, 5, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (19, 'PA-RTR-ASMAN', N'Aturan Penilai — Assisten Manager', NULL, 6, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (20, 'PA-RTR-SRASMAN', N'Aturan Penilai — Senior Assisten Manager', NULL, 7, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (21, 'PA-RTR-MANAGER', N'Aturan Penilai — Manager', NULL, 8, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (22, 'PA-RTR-SRMANAGER', N'Aturan Penilai — Senior Manager', NULL, 9, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (23, 'PA-RTR-GM', N'Aturan Penilai — General Manager', NULL, 10, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (24, 'PA-RTR-SRGM', N'Aturan Penilai — Senior General Manager', NULL, 11, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (25, 'PA-RTR-DIREKTUR', N'Aturan Penilai — Direktur', NULL, 12, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (26, 'PA-RTR-DIRUT', N'Aturan Penilai — Direktur Utama', NULL, 13, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-08-31 15:18:35', NULL),
        (1714, 'PA-RTR-L1000000', N'Aturan Penilai — OFFICER SPECIALIST', NULL, 1000000, 1, NULL, 'ACTIVE', '2026-01-01', NULL, N'Disalin dari deck PIC slide 4. Min/maks penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL, '2026-10-13 19:51:41', NULL);
    SET IDENTITY_INSERT N_PA_Rater_Policy OFF;

    /* N_PA_Rater_Policy_Detail — 47 baris */
    SET IDENTITY_INSERT N_PA_Rater_Policy_Detail ON;
    INSERT INTO N_PA_Rater_Policy_Detail (Id_PA_Rater_Policy_Detail, PA_Rater_Policy_Id, PA_Rater_Category_Id, Organization_Relationship_Type_Id, Bobot_Persen, Min_Rater, Maks_Rater, Min_Rater_Selesai, Flag_Izinkan_Manual, Urutan_Tampil, Catatan_Aturan, Flag_Aktif, Created_At, Created_By, Updated_At, Updated_By) VALUES
        (2, 14, 1, 9, 100.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (3, 15, 1, 9, 80.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (4, 16, 1, 9, 80.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (5, 17, 1, 9, 55.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (6, 18, 1, 9, 55.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (9, 21, 1, 9, 45.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (10, 22, 1, 9, 45.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (11, 23, 1, 9, 40.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (12, 24, 1, 9, 40.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (13, 25, 1, 9, 40.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (14, 26, 1, 9, 40.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (15, 17, 2, 1010, 25.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (16, 18, 2, 1010, 25.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (19, 21, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (20, 22, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (21, 23, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (22, 24, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (23, 25, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (24, 26, 2, 1010, 30.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (25, 17, 3, 6, 10.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (26, 18, 3, 6, 10.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (29, 21, 3, 6, 15.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (30, 22, 3, 6, 15.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (31, 23, 3, 6, 20.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (32, 24, 3, 6, 20.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (33, 25, 3, 6, 20.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (34, 26, 3, 6, 20.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (35, 15, 4, NULL, 20.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (36, 16, 4, NULL, 20.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (37, 17, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (38, 18, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (41, 21, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (42, 22, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (43, 23, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (44, 24, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (45, 25, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (46, 26, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1681, 19, 4, NULL, 30.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1682, 19, 1, 9, 20.00, 1, NULL, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1683, 19, 3, 6, 25.00, 2, NULL, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1684, 19, 2, 1010, 25.00, 2, 8, 2, 'Y', 4, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1716, 20, 4, NULL, 20.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1717, 20, 1, 9, 50.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1718, 20, 2, 1010, 30.00, 2, 8, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1964, 1714, 4, NULL, 10.00, 1, 1, 1, 'Y', 1, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1965, 1714, 1, 9, 55.00, 1, 2, 1, 'Y', 2, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL),
        (1966, 1714, 3, 6, 35.00, 2, 5, 2, 'Y', 3, N'Bobot dari deck PIC slide 4; batas jumlah penilai belum dari deck.', 'Y', SYSDATETIME(), NULL, NULL, NULL);
    SET IDENTITY_INSERT N_PA_Rater_Policy_Detail OFF;

    /* 6. Level dikecualikan: hanya non-level (keputusan 14 Okt 2026) */
    INSERT INTO N_PA_Level_Exclusion (ID_Level, Alasan, Flag_Aktif, Created_At) VALUES
        (21, N'Level FREELANCE dikecualikan dari Performance Appraisal.', 'Y', SYSDATETIME()),
        (14, N'NOT SET bukan level jabatan sebenarnya; dikecualikan dari Performance Appraisal.', 'Y', SYSDATETIME()),
        (15, N'BELUM bukan level jabatan sebenarnya; dikecualikan dari Performance Appraisal.', 'Y', SYSDATETIME()),
        (24, N'ADVISOR tidak termasuk Performance Appraisal (keputusan 14 Okt 2026).', 'Y', SYSDATETIME());

    /* 7. Grup kurva: STAFF / OFFICER / MANAJERIAL (keputusan 14 Okt 2026) */
    SET IDENTITY_INSERT N_PA_Curve_Group ON;
    INSERT INTO N_PA_Curve_Group (Id_PA_Curve_Group, Kode_Grup, Nama_Grup, Urutan_Tampil, Flag_Aktif, Created_At) VALUES
        (72, 'STAFF', N'Staff', 1, 'Y', SYSDATETIME()),
        (73, 'OFFICER', N'Officer & Senior Officer', 2, 'Y', SYSDATETIME()),
        (74, 'MANAJERIAL', N'Manajerial', 3, 'Y', SYSDATETIME());
    SET IDENTITY_INSERT N_PA_Curve_Group OFF;
    INSERT INTO N_PA_Curve_Group_Level (PA_Curve_Group_Id, ID_Level, Flag_Aktif, Created_At) VALUES
        (72, 2, 'Y', SYSDATETIME()),
        (72, 3, 'Y', SYSDATETIME()),
        (73, 4, 'Y', SYSDATETIME()),
        (73, 5, 'Y', SYSDATETIME()),
        (73, 1000000, 'Y', SYSDATETIME()),
        (74, 6, 'Y', SYSDATETIME()),
        (74, 7, 'Y', SYSDATETIME()),
        (74, 8, 'Y', SYSDATETIME()),
        (74, 9, 'Y', SYSDATETIME()),
        (74, 10, 'Y', SYSDATETIME()),
        (74, 11, 'Y', SYSDATETIME()),
        (74, 12, 'Y', SYSDATETIME()),
        (74, 13, 'Y', SYSDATETIME());

    /* 8. Catatan di ledger production */
    IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-188')
        INSERT INTO N_SYSTEM_Database_Change (
            Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
            Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By
        ) VALUES (
            'DML-2026-188', 'DML', 'Data master Performance Appraisal untuk production',
            'Master PA bersih: status, kategori penilai, pita nilai, kuadran HAV, tipe relasi, kompetensi & tingkat, kebutuhan per level, bobot pilar, aturan penilai, Level PA, level dikecualikan, grup kurva.',
            'database/dml/DML-2026-188-pa-master-production/data-change.sql', 'docs/changes/DML-2026-188-pa-master-production/README.md',
            'Web_HRIS', 'NOT_APPLICABLE', 'PENDING', 'PENDING', 'PENDING',
            'KPI/Performance Appraisal. Khusus production; bergantung DDL-2026-187.', NULL
        );

COMMIT TRANSACTION;
