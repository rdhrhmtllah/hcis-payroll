/* Change ID: DML-2026-167-pa-draf-deskripsi-perilaku-kompetensi
   Target: Web_HRIS development.
   Scope: tabel N_PA_Competency, kolom Deskripsi dan Expected_Behaviour SAJA, untuk 21
          kompetensi aktif yang dicocokkan lewat Kode_Competency.

   ── KENAPA ──────────────────────────────────────────────────────────────────────
   Sesudah DDL-2026-166 seluruh layar membaca arti dan perilaku kompetensi dari master,
   tetapi master di development masih kosong (0 dari 21). Berkas ini mengisi DRAF supaya
   layar bisa diuji dan HC punya titik awal untuk direvisi lewat Master Kompetensi.
   Deskripsi disusun dari kalimat yang sebelumnya ditebak di frontend
   (getCompetencyDescription), yang kini dihapus.

   ── AMAN DIULANG, TIDAK MENIMPA ──────────────────────────────────────────────────
   Setiap kolom hanya diisi bila masih NULL. Isian yang sudah ditulis HC tidak tersentuh.
   Kompetensi kualitatif (QL01-QL04) berupa esai, jadi hanya Deskripsi yang diisi.
*/

SET NOCOUNT ON;

DECLARE @draf TABLE (Kode varchar(40) NOT NULL PRIMARY KEY, Deskripsi nvarchar(500) NOT NULL, Perilaku nvarchar(2000) NULL);

INSERT INTO @draf (Kode, Deskripsi, Perilaku)
VALUES
    (N'K01', N'Kemampuan mengidentifikasi akar masalah, menganalisis data secara terstruktur, dan menarik kesimpulan yang logis.', N'Memeriksa data dan fakta sebelum menyimpulkan
Memecah masalah besar menjadi bagian yang bisa dianalisis
Membedakan gejala dari akar penyebab
Menyajikan kesimpulan beserta dasar datanya'),
    (N'K02', N'Kemampuan merumuskan beberapa opsi pemecahan masalah dan menjalankan solusi sampai tuntas.', N'Menawarkan lebih dari satu alternatif solusi
Menimbang untung-rugi tiap opsi sebelum memilih
Menindaklanjuti solusi sampai masalah benar-benar selesai
Mencegah masalah yang sama terulang'),
    (N'K03', N'Ketepatan dan ketegasan mengambil keputusan berdasarkan data dan pertimbangan risiko.', N'Mengambil keputusan tepat waktu, tidak menunda tanpa alasan
Mempertimbangkan dampak dan risiko keputusan
Menjelaskan alasan di balik keputusan yang diambil
Bertanggung jawab atas akibat keputusannya'),
    (N'K04', N'Kemampuan menyusun rencana kerja, mengatur prioritas, dan mengelola sumber daya untuk mencapai target.', N'Menyusun rencana kerja dengan target dan tenggat yang jelas
Menentukan prioritas berdasarkan urgensi dan dampak
Memantau kemajuan dan menyesuaikan rencana bila perlu
Memanfaatkan waktu dan sumber daya secara efisien'),
    (N'K05', N'Dorongan untuk mencapai hasil terbaik dan melampaui target yang ditetapkan.', N'Menetapkan target yang menantang namun realistis
Tetap gigih ketika menghadapi hambatan
Mengukur hasil kerja sendiri terhadap target
Mencari cara untuk meningkatkan hasil berikutnya'),
    (N'K06', N'Komitmen memahami dan memenuhi kebutuhan pelanggan internal maupun eksternal.', N'Menggali kebutuhan pelanggan sebelum memberi solusi
Merespons permintaan dan keluhan dengan cepat
Menjaga janji layanan yang sudah disepakati
Meminta umpan balik untuk perbaikan layanan'),
    (N'K07', N'Kemampuan menyampaikan gagasan dan umpan balik secara jelas, lugas, dan berdampak.', N'Menyampaikan pesan secara runtut dan mudah dipahami
Menyesuaikan cara bicara dengan audiens
Mendengarkan aktif sebelum menanggapi
Memberi umpan balik yang spesifik dan membangun'),
    (N'K08', N'Kemampuan beradaptasi terhadap perubahan dan tetap efektif di situasi baru.', N'Menerima perubahan dengan sikap terbuka
Cepat mempelajari cara kerja atau sistem baru
Tetap produktif saat prioritas berubah
Membantu rekan menyesuaikan diri dengan perubahan'),
    (N'K09', N'Keterampilan bekerja sama lintas fungsi untuk mencapai hasil yang lebih solid.', N'Berbagi informasi yang dibutuhkan rekan kerja
Menawarkan bantuan tanpa harus diminta
Menghargai pendapat yang berbeda
Mengutamakan tujuan tim di atas kepentingan pribadi'),
    (N'K10', N'Pemahaman tentang bisnis perusahaan dan dampak pekerjaan terhadap kinerja usaha.', N'Memahami bagaimana pekerjaannya memengaruhi hasil bisnis
Mempertimbangkan biaya dan manfaat dalam bekerja
Mengikuti perkembangan pasar dan pesaing
Mengusulkan ide yang memberi nilai tambah bagi bisnis'),
    (N'K11', N'Kemampuan memotivasi, mengarahkan, dan mengembangkan potensi tim menuju sasaran bersama.', N'Menetapkan arah dan sasaran tim dengan jelas
Mendelegasikan tugas sesuai kemampuan anggota
Memberi pengakuan atas kontribusi anggota tim
Membimbing anggota tim untuk berkembang'),
    (N'CV01', N'Mengutamakan kepuasan pelanggan dalam setiap pekerjaan sebagai nilai inti perusahaan.', N'Mendahulukan kebutuhan pelanggan dalam bekerja
Menjaga kualitas produk dan layanan
Menangani keluhan pelanggan sampai tuntas'),
    (N'CV02', N'Kecepatan beradaptasi terhadap perubahan dan inisiatif melakukan perbaikan berkelanjutan.', N'Mengusulkan perbaikan cara kerja
Cepat menyesuaikan diri dengan perubahan
Belajar dari kesalahan dan tidak mengulanginya'),
    (N'CV03', N'Semangat bertumbuh bersama: saling mendukung, berbagi ilmu, dan maju bersama.', N'Berbagi pengetahuan dan pengalaman dengan rekan
Mendukung keberhasilan rekan kerja
Terbuka menerima masukan untuk berkembang'),
    (N'BH01', N'Kepatuhan pada aturan, kejujuran, dan konsistensi antara ucapan dan tindakan.', N'Hadir dan menyelesaikan pekerjaan tepat waktu
Mematuhi aturan dan prosedur perusahaan
Bertindak jujur dan melaporkan apa adanya'),
    (N'BH02', N'Ketekunan, keandalan, dan tanggung jawab penuh dalam menuntaskan pekerjaan.', N'Menyelesaikan pekerjaan sampai tuntas
Dapat diandalkan tanpa perlu diawasi terus
Teliti dan menjaga kualitas hasil kerja'),
    (N'BH03', N'Kepekaan terhadap kebutuhan orang lain dan fokus melayani pelanggan.', N'Peka terhadap kebutuhan rekan dan pelanggan
Bersikap ramah dan sopan dalam melayani
Berusaha membantu menyelesaikan masalah orang lain'),
    (N'QL01', N'Kekuatan utama karyawan yang menonjol selama periode penilaian, ditulis penilai sebagai uraian.', NULL),
    (N'QL02', N'Area yang perlu dikembangkan karyawan agar kinerjanya meningkat, ditulis penilai sebagai uraian.', NULL),
    (N'QL03', N'Inisiatif yang pernah diambil karyawan di luar tugas rutinnya selama periode penilaian.', NULL),
    (N'QL04', N'Rekomendasi penilai untuk pengembangan karier atau penugasan karyawan berikutnya.', NULL);

UPDATE c
SET c.Deskripsi = d.Deskripsi,
    c.Updated_At = SYSDATETIME()
FROM N_PA_Competency c
JOIN @draf d ON d.Kode = c.Kode_Competency
WHERE c.Flag_Aktif = 'Y'
  AND c.Deskripsi IS NULL;

SELECT @@ROWCOUNT AS Deskripsi_Diisi;

UPDATE c
SET c.Expected_Behaviour = d.Perilaku,
    c.Updated_At = SYSDATETIME()
FROM N_PA_Competency c
JOIN @draf d ON d.Kode = c.Kode_Competency
WHERE c.Flag_Aktif = 'Y'
  AND c.Expected_Behaviour IS NULL
  AND d.Perilaku IS NOT NULL;

SELECT @@ROWCOUNT AS Perilaku_Diisi;
