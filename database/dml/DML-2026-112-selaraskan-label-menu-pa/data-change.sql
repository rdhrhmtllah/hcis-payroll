-- DML-2026-112-selaraskan-label-menu-pa (Revision r5)
-- Penyelarasan label Master Data ("Master · [Nama Menu]"), urutan alur kerja administrasi PA
-- dari konfigurasi fondasi Master Data (1..7) -> Siklus/Assessment Cycle (8) -> Monitoring (9) ->
-- Hasil (10) -> Kalibrasi (11) -> 9-Box HAV (12) -> Distribusi (13) -> Riwayat (14) -> Dashboard PA (15).

-- 1. Master Data Foundations (Sort 1..7)
UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Jenis Kompetensi',
    [Menu_Subtitle] = 'Master dimensi dan kategori tipe kompetensi (Core Values, Manajerial, dsb.)',
    [Sort_Menu] = '1',
    [Icon_Menu] = 'bi-diagram-2-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/jenis-kompetensi';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Kompetensi',
    [Menu_Subtitle] = 'Katalog butir kompetensi master dan indikator perilaku',
    [Sort_Menu] = '2',
    [Icon_Menu] = 'bi-bullseye',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/kompetensi';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Matriks Kompetensi',
    [Menu_Subtitle] = 'Matriks pemetaan dimensi kompetensi wajib per level jabatan',
    [Sort_Menu] = '3',
    [Icon_Menu] = 'bi-grid-3x3-gap-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/syarat-kompetensi';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Paket Soal Default',
    [Menu_Subtitle] = 'Pemetaan paket soal default per level jabatan dan kategori penilai',
    [Sort_Menu] = '4',
    [Icon_Menu] = 'bi-diagram-3-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/ikatan-paket-soal';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Komposisi Penilai',
    [Menu_Subtitle] = 'Kebijakan komposisi rater 360 multi-rater dan alokasi bobot',
    [Sort_Menu] = '5',
    [Icon_Menu] = 'bi-people-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/aturan-penilai';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Bobot Penilaian',
    [Menu_Subtitle] = 'Proporsi bobot pilar capaian KPI vs evaluasi Kompetensi',
    [Sort_Menu] = '6',
    [Icon_Menu] = 'bi-pie-chart-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/bobot-pilar';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Master · Pita Nilai & Grade',
    [Menu_Subtitle] = 'Interval rentang skor, skala predikat, dan batasan grade',
    [Sort_Menu] = '7',
    [Icon_Menu] = 'bi-bar-chart-steps',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/master/pita-nilai';

-- 2. Operational Cycle, Monitoring, Results & Calibration Flow (Sort 8..14)
UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Assessment Cycle',
    [Menu_Subtitle] = 'Kelola fase persiapan, rater assignment, pengisian, dan status cycle',
    [Sort_Menu] = '8',
    [Icon_Menu] = 'bi-grid-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Monitoring & Verifikasi',
    [Menu_Subtitle] = 'Progres pengisian evaluasi rater dan antrean verifikasi hasil',
    [Sort_Menu] = '9',
    [Icon_Menu] = 'bi-hourglass-split',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/pemantauan';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Hasil & Penerbitan',
    [Menu_Subtitle] = 'Kalkulasi nilai akhir peserta dan penerbitan hasil evaluasi',
    [Sort_Menu] = '10',
    [Icon_Menu] = 'bi-calculator-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/hasil';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Kalibrasi Grade',
    [Menu_Subtitle] = 'Forum penyeimbangan grade dan penyesuaian kuadran performa',
    [Sort_Menu] = '11',
    [Icon_Menu] = 'bi-sliders',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/kalibrasi';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = '9-Box HAV Map',
    [Menu_Subtitle] = 'Matriks pemetaan talenta 3×3 (Kinerja KPI vs Kompetensi)',
    [Sort_Menu] = '12',
    [Icon_Menu] = 'bi-grid-3x3',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/hav-map';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Distribusi Performance',
    [Menu_Subtitle] = 'Analisis kurva normal per level jabatan dan sebaran hasil organisasi',
    [Sort_Menu] = '13',
    [Icon_Menu] = 'bi-bar-chart-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/distribusi';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Riwayat Penilaian',
    [Menu_Subtitle] = 'Rekam jejak penilaian multi-tahun, tren kinerja, dan log audit',
    [Sort_Menu] = '14',
    [Icon_Menu] = 'bi-clock-history',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/riwayat';

-- 3. Executive Control Tower Dashboard (Sort 15)
UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Dashboard PA',
    [Menu_Subtitle] = 'Pusat komando dan ringkasan eksekutif seluruh siklus',
    [Sort_Menu] = '15',
    [Icon_Menu] = 'bi-speedometer2',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/dashboard';

-- 4. Portals Karyawan & Leader
UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Penilaian Saya',
    [Menu_Subtitle] = 'Lembar evaluasi diri, tugas penilaian 360°, dan hasil performa pribadi',
    [Icon_Menu] = 'bi-clipboard2-check-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/saya';

UPDATE [N_HRIS_Menus]
SET [Nama_Menu] = 'Penilaian Tim',
    [Menu_Subtitle] = 'Pantau progres penilaian dan hasil evaluasi anggota tim di bawah supervisi',
    [Icon_Menu] = 'bi-people-fill',
    [Is_Show_Sidebar] = 'Y'
WHERE [Url_Menu] = '/performance-appraisal/tim';