<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\Cycle\PaCycleAdminController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Cycle\PaHitungLatarController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Cycle\PaPembentukanLatarController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Cycle\PaPenjadwalanLatarController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Cycle\PaRevisiPesertaController;
use App\Http\Controllers\KPI\PerformanceAppraisal\Report\PaReportController;
use Illuminate\Support\Facades\Route;

/*
 * Performance Appraisal — pengelolaan siklus, dipecah menjadi TIGA FASE.
 *
 * KENAPA DIPECAH, DAN KENAPA BARU SEKARANG
 *
 * Sampai 3 September 2026 seluruh berkas ini memakai satu kunci halaman, dengan alasan
 * yang ditulis di sini: "memecahnya menjadi beberapa kunci akan melahirkan kombinasi hak
 * yang tak seorang pun berniat memberikannya — misalnya orang yang boleh menghitung hasil
 * tetapi tak boleh melihat peserta yang dihitungnya."
 *
 * Kekhawatiran itu SAH, dan tetap dihormati. Yang berubah adalah cara mendamaikannya:
 *
 *   - Yang berbahaya adalah memecah hak MEMBACA. Karena itu seluruh endpoint BACA
 *     digerbangi ketiga kunci sekaligus (logika salah-satu): siapa pun yang memegang
 *     salah satu fase bisa melihat seluruh keadaan siklus. Kombinasi "boleh menghitung
 *     tetapi tak boleh melihat peserta" tetap mustahil.
 *
 *   - Yang berguna adalah memecah hak MENULIS. Menyiapkan siklus, memantau pengisian,
 *     dan memutuskan angka dikerjakan pada waktu berbeda dan sering oleh orang berbeda.
 *     Kalibrasi dan penerbitan mengubah angka yang sampai ke karyawan; menaruhnya satu
 *     klik dari "siapkan peserta" bukan penataan yang baik.
 *
 * PEMBAGIAN FASENYA BUKAN KARANGAN. Ia mengikuti pengelompokan yang sudah ada di
 * `PaCycleGate::IZIN` — operasi apa yang diizinkan pada status apa:
 *
 *   Fase           Status                Operasi gerbang
 *   Penyiapan      DRAFT, PREPARED       SUNTING_*, BUAT_PENUGASAN, KUNCI_SIKLUS
 *   Pengisian      LOCKED, OPEN          BUKA_PENGISIAN, TERIMA_PAYLOAD, TUTUP_PENGISIAN
 *   Hasil          CLOSED, PUBLISHED     HITUNG_HASIL, KALIBRASI, TERBITKAN
 *
 * `SUNTING_BOBOT_PILAR` sengaja melintasi ketiganya (K23) dan ditaruh di fase Penyiapan,
 * karena ia menyunting salinan — bukan memutuskan angka akhir siapa pun.
 *
 * Callback hasil pengisian dari HC Learn TIDAK ada di sini. HC Learn memakai satu URL
 * callback tetap milik LMS; LmsApiGuard yang mengenali token PA lalu mendelegasikannya.
 */

/* Ketiga kunci fase untuk endpoint BACA ditulis SEBARIS di tempat pemakaiannya, bukan
   sebagai `const` berkas.

   `const` di berkas rute dideklarasikan ULANG setiap kali aplikasi boot, dan itu fatal:
   Laravel mem-boot aplikasi baru untuk TIAP uji, sehingga uji pertama lulus lalu semua
   sesudahnya mati dengan "Constant already defined". Gejalanya menyesatkan — 38 uji merah
   sekaligus, termasuk uji yang tidak menyentuh rute sama sekali. */

/* ====================================================================== *
 * HALAMAN — tiap fase punya alamatnya sendiri                             *
 * ====================================================================== */

Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalPage'])->group(function () {
    Route::get('/performance-appraisal', [PaCycleAdminController::class, 'index'])
        ->name('performance-appraisal.index');
});

/* Halaman penyiapan berlangkah. Memakai kunci halaman siklus, bukan kunci sendiri:
   menyiapkan siklus adalah pekerjaan orang yang sama yang membuatnya, dan kunci terpisah
   akan menghasilkan admin yang bisa membuat siklus tetapi tidak bisa menyiapkannya. */
Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalPage'])->group(function () {
    Route::get('/performance-appraisal/siapkan/{cycleId}', [PaCycleAdminController::class, 'halamanPenyiapan'])
        ->whereNumber('cycleId')
        ->name('performance-appraisal.penyiapan');
});

Route::middleware(['auth', 'check.jabatan:PaPemantauanPage'])->group(function () {
    Route::get('/performance-appraisal/pemantauan', [PaCycleAdminController::class, 'pemantauan'])
        ->name('performance-appraisal.pemantauan');
});

Route::middleware(['auth', 'check.jabatan:PaHasilPage'])->group(function () {
    Route::get('/performance-appraisal/hasil', [PaCycleAdminController::class, 'hasil'])
        ->name('performance-appraisal.hasil');
});

/* ---------------------------------------------------------------------- *
 * Enam halaman yang mengikuti alur mockup                                 *
 *                                                                         *
 * Sampai 3 September 2026 keenamnya adalah dialog di dalam satu layar.    *
 * Mockup merancangnya sebagai halaman terpisah dalam tiga grup, dan itu   *
 * bentuk yang benar untuk pekerjaan yang dijalankan sekali-dua kali       *
 * setahun. Endpoint datanya sudah ada sejak lama.                         *
 *                                                                         *
 * Gerbangnya mengikuti SIFAT halamannya, bukan seragam: yang hanya        *
 * membaca dibuka untuk ketiga fase, yang menulis mengikuti fasenya.       *
 * ---------------------------------------------------------------------- */

// Hanya membaca — dibuka untuk pemegang fase mana pun.
Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalPage,PaPemantauanPage,PaHasilPage'])
    ->group(function () {
        Route::get('/performance-appraisal/dashboard', [PaCycleAdminController::class, 'dashboardAdmin'])
            ->name('performance-appraisal.dashboard');
        Route::get('/performance-appraisal/hav-map', [PaCycleAdminController::class, 'halamanHavMap'])
            ->name('performance-appraisal.hav-map');
        Route::get('/performance-appraisal/distribusi', [PaCycleAdminController::class, 'halamanDistribusi'])
            ->name('performance-appraisal.distribusi');
        Route::get('/performance-appraisal/riwayat', [PaCycleAdminController::class, 'halamanRiwayat'])
            ->name('performance-appraisal.riwayat');
    });

/* HALAMAN PENUGASAN PENILAI DIBUBARKAN.
 |
 | Dulu `/performance-appraisal/penilai` adalah layar tersendiri. Pekerjaannya kini Tahap 5 di
 | wizard penyiapan (`/performance-appraisal/siapkan/{cycleId}`), tab "Peserta": tiap baris
 | punya tombol "Kelola Penilai" yang membuka penyunting yang SAMA di tempat.
 |
 | Sebabnya perjalanan adminnya: dulu ia melihat "peserta ini kurang penilai" di Tahap 5, lalu
 | dilempar ke halaman lain, harus menemukan orangnya lagi di daftar sana, menyuntingnya, lalu
 | kembali — kehilangan tab, saringan level, dan posisi gulirnya. Satu pekerjaan, dua layar.
 |
 | Endpoint API-nya TIDAK dibubarkan (usulan-penilai, penugasan, override): yang dibubarkan
 | halamannya, bukan kemampuannya. Penyunting di Tahap 5 memakai endpoint yang sama.
 |
 | Butir menunya dicabut lewat DML-2026-100.
 */

/* Kalibrasi mengubah GRADE YANG SAMPAI KE KARYAWAN, jadi ia milik fase Hasil —
   bukan halaman yang boleh dibuka siapa pun yang kebetulan memegang salah satu fase. */
Route::middleware(['auth', 'check.jabatan:PaHasilPage'])->group(function () {
    Route::get('/performance-appraisal/kalibrasi', [PaCycleAdminController::class, 'halamanKalibrasi'])
        ->name('performance-appraisal.kalibrasi');
});

/* ====================================================================== *
 * BACA — terbuka untuk ketiga fase                                        *
 * ====================================================================== */

Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalPage,PaPemantauanPage,PaHasilPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        Route::get('/cycles', [PaCycleAdminController::class, 'daftarSiklus']);
        Route::get('/cycles/{cycleId}', [PaCycleAdminController::class, 'detailSiklus'])
            ->whereNumber('cycleId');
        /* Alur berlangkah — dibaca setiap kali layar penyiapan dibuka. Ditaruh di blok
           BACA karena ia hanya melaporkan keadaan; tidak ada satu pun yang berubah. */
        Route::get('/cycles/{cycleId}/langkah', [PaCycleAdminController::class, 'alurLangkah']);
        // Alur penutupan (5 tahap sesudah pengisian) — hanya membaca, karena itu di blok BACA.
        Route::get('/cycles/{cycleId}/penutupan', [PaCycleAdminController::class, 'alurPenutupan'])->whereNumber('cycleId');

        /* Pratinjau penyiapan. GET dan di blok BACA meski namanya terdengar seperti aksi:
           ia tidak menulis satu baris pun, dan menaruhnya di blok TULIS akan membuat orang
           yang hanya boleh memantau kehilangan kemampuan MELIHAT apa yang akan terjadi. */
        Route::get('/cycles/{cycleId}/pratinjau-penyiapan', [PaCycleAdminController::class, 'pratinjauPenyiapan']);
        Route::get('/cycles/{cycleId}/perubahan-peserta', [PaCycleAdminController::class, 'perubahanPeserta']);
        Route::get('/cycles/{cycleId}/pra-kecuali', [PaCycleAdminController::class, 'daftarPraKecuali']);
        Route::get('/cycles/{cycleId}/kelayakan-masa-kerja', [PaCycleAdminController::class, 'kelayakanMasaKerja'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/tertunda', [PaCycleAdminController::class, 'keranjangTertunda']);
        Route::get('/cycles/{cycleId}/participants', [PaCycleAdminController::class, 'daftarPeserta']);
        Route::get('/cycles/{cycleId}/hav-map', [PaCycleAdminController::class, 'havMap']);
        Route::get('/cycles/{cycleId}/penerbitan', [PaCycleAdminController::class, 'ringkasanPenerbitan']);
        Route::get('/cycles/{cycleId}/bobot-pilar', [PaCycleAdminController::class, 'daftarBobotPilar']);

        /* Alur penyiapan berlangkah. Keadaan seluruh langkah dibaca sekali; layar wizard
           menggambar penunjuk posisinya sekaligus, bukan sepotong per langkah. */
        Route::get('/cycles/{cycleId}/setup', [PaCycleAdminController::class, 'keadaanSetup']);
        Route::get('/cycles/{cycleId}/aturan-penilai', [PaCycleAdminController::class, 'daftarAturanPenilai']);
        Route::get('/cycles/{cycleId}/level', [PaCycleAdminController::class, 'levelSiklus']);
        Route::get('/cycles/{cycleId}/katalog-paket-soal', [PaCycleAdminController::class, 'katalogPaketSoal']);
        Route::get('/cycles/{cycleId}/matriks-paket-soal', [PaCycleAdminController::class, 'matriksPaketSoal']);
        Route::get('/cycles/{cycleId}/kesehatan-penugasan', [PaCycleAdminController::class, 'kesehatanPenugasan']);
        Route::get('/cycles/{cycleId}/penugasan-bermasalah', [PaCycleAdminController::class, 'pesertaPenugasanBermasalah']);
        Route::get('/cycles/{cycleId}/cari-karyawan', [PaCycleAdminController::class, 'cariKaryawanPraKecuali']);
        Route::get('/cycles/{cycleId}/opsi-filter-karyawan', [PaCycleAdminController::class, 'opsiFilterKaryawanPraKecuali']);
        Route::get('/participants/{participantId}/usulan-penilai', [PaCycleAdminController::class, 'usulanPenilai']);
        Route::get('/participants/{participantId}/hasil', [PaCycleAdminController::class, 'detailHasil']);
        Route::get('/participants/{participantId}/hasil/riwayat', [PaCycleAdminController::class, 'riwayatHasil'])->whereNumber('participantId');
        // Tahap Kesimpulan Kualitatif: hanya membaca; simpannya lewat PUT /participants/{id}/catatan.
        Route::get('/cycles/{cycleId}/kesimpulan-kualitatif', [PaCycleAdminController::class, 'daftarKesimpulanKualitatif'])->whereNumber('cycleId');
        Route::get('/participants/{participantId}/kesimpulan-kualitatif', [PaCycleAdminController::class, 'ruangKerjaKualitatif'])->whereNumber('participantId');
        // POST karena daftar id terpilih bisa ratusan; tidak menulis apa pun.
        Route::post('/cycles/{cycleId}/kesimpulan-kualitatif/periksa', [PaCycleAdminController::class, 'periksaKesimpulanSebelumTerbit'])->whereNumber('cycleId');

        /* Laporan admin — seluruhnya BACA, tidak satu pun mengubah keadaan siklus. */
        Route::get('/cycles/{cycleId}/ringkasan', [PaReportController::class, 'ringkasanSiklus']);
        Route::get('/cycles/{cycleId}/monitoring', [PaReportController::class, 'monitoring']);
        Route::get('/cycles/{cycleId}/monitoring/penilai', [PaReportController::class, 'monitoringPenilai']);
        Route::get('/cycles/{cycleId}/monitoring/penilai/rincian', [PaReportController::class, 'rincianPenilai']);

        /* Kiriman HC Learn yang MASUK tetapi tidak terpetakan penuh. Berdiri di blok BACA:
           ia hanya menghitung ulang dari yang tersimpan, tidak mengubah apa pun. */
        Route::get('/cycles/{cycleId}/kiriman-perlu-ditinjau', [PaReportController::class, 'kirimanPerluDitinjau'])
            ->whereNumber('cycleId');

        /* Keranjang keputusan admin — pertanyaan yang berbeda dari pemantauan pengisian.
           Yang itu "bagaimana pengisian berjalan", yang ini "apa yang menunggu keputusan
           saya". Satu halaman, dua daftar; digabung, admin menyaring dua kali untuk satu
           pekerjaan. */
        Route::get('/cycles/{cycleId}/keranjang', [PaReportController::class, 'keranjang']);
        Route::get('/cycles/{cycleId}/distribusi', [PaReportController::class, 'distribusi']);
        // Kurva grade (siklus KURVA): pratinjau sebelum kunci, pita beku sesudahnya.
        Route::get('/cycles/{cycleId}/kurva', [PaReportController::class, 'kurva'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/kurva/grup/{grupId}', [PaReportController::class, 'kurvaGrup'])->whereNumber(['cycleId', 'grupId']);
        Route::get('/cycles/{cycleId}/distribusi/export-pdf', [PaReportController::class, 'exportDistribusiPdf']);

        /* Lini masa audit. Tanpa tabel riwayat baru: modul ini menonaktifkan alih-alih
           menghapus, jadi riwayatnya sudah tersimpan sebagai baris yang masih ada. */
        Route::get('/cycles/{cycleId}/audit', [PaReportController::class, 'auditSiklus']);
        Route::get('/riwayat', [PaReportController::class, 'riwayat']);
    });

/* ====================================================================== *
 * TULIS — fase PENYIAPAN                                                  *
 * ====================================================================== */

Route::middleware(['auth', 'check.jabatan:PerformanceAppraisalPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        /* Penyiapan siklus. `POST /cycles` tidak menerima status: siklus selalu lahir
           DRAFT, karena yang lahir terkunci melewati penyiapan dan pesertanya tak pernah
           terbentuk. */
        Route::post('/cycles', [PaCycleAdminController::class, 'buatSiklus']);
        Route::put('/cycles/{cycleId}', [PaCycleAdminController::class, 'perbaruiSiklus'])
            ->whereNumber('cycleId');
        /* Daftar pengecualian awal. Digerbangi fase PENYIAPAN karena ia hanya bermakna
           sebelum peserta terbentuk — sesudahnya jalurnya `PUT /participants/{id}/status`,
           yang bekerja pada baris peserta yang sudah ada. */
        // Minimal masa kerja siklus (DDL-2026-191): hanya sebelum peserta dibentuk.
        Route::put('/cycles/{cycleId}/kelayakan-masa-kerja', [PaCycleAdminController::class, 'simpanKelayakanMasaKerja'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/pra-kecuali', [PaCycleAdminController::class, 'tambahPraKecuali']);
        Route::post('/cycles/{cycleId}/pra-kecuali/cabut-massal', [PaCycleAdminController::class, 'cabutPraKecualiBanyak']);
        Route::post('/cycles/{cycleId}/pra-kecuali/{id}/cabut', [PaCycleAdminController::class, 'cabutPraKecuali'])
            ->whereNumber('id');
        /* Perpindahan posisi dan penandaan draft. Digerbangi fase PENYIAPAN karena alur
           berlangkah hanya bermakna sebelum siklus dikunci — sesudahnya setelannya sudah
           dipakai membentuk peserta, dan berpindah langkah tidak mengubah apa pun. */
        Route::post('/cycles/{cycleId}/setup/pindah', [PaCycleAdminController::class, 'pindahLangkahSetup']);
        Route::post('/cycles/{cycleId}/setup/draft', [PaCycleAdminController::class, 'draftLangkahSetup']);

        /* Aturan penilai MILIK SIKLUS. `salin` membentuk salinannya dari master; `simpan`
           menyuntingnya per level, sekaligus seluruh kategori karena bobotnya wajib 100. */
        /* Pelabelan bagian→kompetensi LANGSUNG dari Langkah 2, tanpa berpindah ke halaman
           master. Alamatnya kembar dengan yang di master dan kembarnya disengaja: izin
           halamannya berbeda. Admin siklus memegang `PerformanceAppraisalPage`, bukan
           `PaMasterPaketSoalPage`; memakai alamat master dari layar siklus berarti 403
           untuk orang yang justru sedang menjalankan pekerjaannya. Yang ditulis tetap
           MASTER — layar menyebutnya apa adanya. */
        Route::get('/cycles/{cycleId}/paket-soal/bagian', [PaCycleAdminController::class, 'bagianPaketSoal']);
        Route::post('/cycles/{cycleId}/paket-soal/label', [PaCycleAdminController::class, 'labelPaketSoal']);

        Route::post('/cycles/{cycleId}/paket-soal/impor', [PaCycleAdminController::class, 'imporPaketSoal']);
        Route::post('/cycles/{cycleId}/paket-soal/ikat', [PaCycleAdminController::class, 'ikatPaketSoal']);
        Route::post('/cycles/{cycleId}/paket-soal/salin-master', [PaCycleAdminController::class, 'salinPaketSoalDariMaster']);
        Route::post('/cycles/{cycleId}/paket-soal/terima-usulan', [PaCycleAdminController::class, 'terimaUsulanPaketSoal']);
        Route::post('/cycles/{cycleId}/aturan-penilai/salin', [PaCycleAdminController::class, 'salinAturanPenilai']);
        Route::put('/cycles/{cycleId}/aturan-penilai/{levelId}', [PaCycleAdminController::class, 'simpanAturanPenilai'])
            ->whereNumber('levelId');

        /* Kesiapan karyawan di HC Learn — tahap Langkah 4, sebelum penjadwalan.
           Keduanya di blok TULIS meski yang pertama hanya membaca: keduanya menghubungi
           HC Learn dengan menandatangani atas nama pemanggilnya, dan itu bukan kemampuan
           yang pantas dibuka untuk pemegang izin pemantauan atau hasil. */
        Route::get('/cycles/{cycleId}/hclearn-karyawan', [PaCycleAdminController::class, 'kesiapanKaryawanHcLearn']);
        Route::post('/cycles/{cycleId}/hclearn-karyawan/sinkronkan', [PaCycleAdminController::class, 'sinkronkanKaryawanHcLearn']);

        /* Memetakan ULANG satu kiriman yang sudah tersimpan — dipakai sesudah ikatan paket
           soalnya dibetulkan. Tidak meminta apa pun ke HC Learn: bukti mentahnya sudah ada
           di sini, yang salah hanya penerjemahannya. */
        Route::post('/cycles/{cycleId}/kiriman/{payloadId}/petakan-ulang', [PaCycleAdminController::class, 'petakanUlangKiriman'])
            ->whereNumber('cycleId')
            ->whereNumber('payloadId');

        Route::post('/cycles/{cycleId}/siapkan', [PaCycleAdminController::class, 'siapkan']);
        Route::post('/cycles/{cycleId}/segarkan-level-tertahan', [PaCycleAdminController::class, 'segarkanLevelTertahan']);
        Route::get('/cycles/{cycleId}/pra-kunci', [PaCycleAdminController::class, 'ringkasanPraKunci']);
        Route::post('/cycles/{cycleId}/kunci', [PaCycleAdminController::class, 'kunci']);

        /* Penugasan penilai — siapa menilai siapa. */
        Route::post('/participants/{participantId}/penugasan', [PaCycleAdminController::class, 'tugaskanPenilai']);
        Route::post('/assignments/{assignmentId}/batalkan', [PaCycleAdminController::class, 'batalkanPenugasan']);

        /* Pembagian bobot antar penilai dalam kategori. PUT, bukan POST: ia mengganti
           seluruh pembagian kategori yang disentuh, bukan menambah satu baris. */
        Route::put('/participants/{participantId}/bobot-penilai', [PaCycleAdminController::class, 'simpanBobotPenilai']);
        Route::put('/participants/{participantId}/bobot-penilai/rata', [PaCycleAdminController::class, 'kembalikanBobotRata']);

        /* Menambah penilai SEKALIGUS membentuk relasi organisasinya. POST: ia
           melahirkan dua hal baru, relasi dan penugasan. */
        /* Pencarian calon penilai: jawabannya bergantung pada PESERTA, karena relasi yang
           sudah ada di antara mereka menentukan boleh-tidaknya. */
        Route::get('/participants/{participantId}/cari-calon-penilai', [PaCycleAdminController::class, 'cariCalonPenilai']);

        Route::post('/participants/{participantId}/penilai-baru', [PaCycleAdminController::class, 'tambahPenilaiDenganRelasi']);

        /* Penyimpangan per peserta (K32). PUT, bukan POST: yang dikirim adalah SELURUH
           susunan aturan orang itu sekaligus, karena bobotnya wajib berjumlah 100, dan
           itu hanya bisa diperiksa ketika semuanya diketahui sekaligus. */
        Route::put('/participants/{participantId}/override/penilai', [PaCycleAdminController::class, 'simpanOverridePenilai']);
        Route::put('/participants/{participantId}/override/pilar', [PaCycleAdminController::class, 'simpanOverridePilar']);
        Route::put('/participants/{participantId}/override/syarat', [PaCycleAdminController::class, 'simpanOverrideSyarat']);
        Route::post('/participants/{participantId}/override/cabut', [PaCycleAdminController::class, 'cabutOverride']);

        /* Kekurangan penilai yang SENGAJA tidak diisi: kategori dicabut bagi satu peserta,
           beralasan. Menahan penguncian sampai setiap kekurangan diputuskan (13 Okt 2026). */
        Route::post('/participants/{participantId}/kategori-tidak-diikutkan', [PaCycleAdminController::class, 'tandaiKategoriTidakDiikutkan']);
        Route::delete('/participants/{participantId}/kategori-tidak-diikutkan/{kategoriId}', [PaCycleAdminController::class, 'batalkanKategoriTidakDiikutkan'])->whereNumber('kategoriId');

        /* Catatan Kekuatan / Area Pengembangan yang ditulis admin sendiri. PUT karena yang
           dikirim seluruh daftarnya sekaligus — lihat catatan di controllernya. */
        Route::put('/participants/{participantId}/catatan', [PaCycleAdminController::class, 'simpanCatatanHasil'])
            ->whereNumber('participantId');

        /* Penyuntingan keikutsertaan. PUT untuk satu orang, POST untuk banyak: yang
           pertama satu keputusan, yang kedua sekumpulan pekerjaan yang hasilnya
           dilaporkan per baris. */
        Route::put('/participants/{participantId}/status', [PaCycleAdminController::class, 'ubahStatusPeserta']);
        Route::post('/participants/status-massal', [PaCycleAdminController::class, 'ubahStatusPesertaBanyak']);

        /* Bobot pilar per siklus (K23) — satu-satunya salinan yang masih boleh disunting
           sesudah siklus dikunci, karena ia tidak menyentuh siapa menilai siapa. Ditaruh
           di fase penyiapan meski berlaku lintas fase: ia menyunting SALINAN, bukan
           memutuskan angka akhir siapa pun. */
        Route::put('/cycles/{cycleId}/bobot-pilar/{levelId}', [PaCycleAdminController::class, 'simpanBobotPilar'])
            ->whereNumber('levelId');
    });

/* ====================================================================== *
 * TULIS — fase PENGISIAN                                                  *
 * ====================================================================== */

Route::middleware(['auth', 'check.jabatan:PaPemantauanPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        /* Dua alamat yang menutup rantai status. Sampai 2 September 2026 `OPEN` dan
           `CLOSED` tidak punya penulis di kode produksi sama sekali, sehingga siklus
           mentok di `LOCKED` dan empat operasi inti tak pernah terjangkau.

           `buka-pengisian` menerima siklus `CLOSED` juga: menutup terlalu cepat adalah
           kekeliruan yang wajar, dan aturan tanpa jalan kembali hanya memindahkan
           perbaikannya ke SQL manual. */
        Route::get('/cycles/{cycleId}/penugasan-progres', [PaCycleAdminController::class, 'progresPenugasan']);
        Route::post('/cycles/{cycleId}/penugasan-massal', [PaCycleAdminController::class, 'bentukPenugasanMassal']);
        /* Pembentukan sebagai TUGAS LATAR (Langkah 5 bagian 2): server yang mengerjakan potongan
           demi potongan; layar hanya memulai dan membaca, tugasnya tampil di Tugas & Aktivitas. */
        Route::post('/cycles/{cycleId}/penugasan-massal/latar', [PaPembentukanLatarController::class, 'mulai'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/penugasan-massal/latar', [PaPembentukanLatarController::class, 'status'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/penugasan-massal/latar/ulangi', [PaPembentukanLatarController::class, 'ulangi'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/penugasan-massal/latar/hentikan', [PaPembentukanLatarController::class, 'hentikan'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/penjadwalan', [PaCycleAdminController::class, 'ringkasanPenjadwalan']);
        Route::post('/cycles/{cycleId}/penjadwalan', [PaCycleAdminController::class, 'jadwalkanPenilaian']);

        /* Menarik hasil penjadwalan yang masih ANTRE. TERPISAH dari penjadwalannya karena
           `store` milik HC Learn menjawab 202 lalu menggarap antreannya belakangan: tokennya
           belum ada saat penjadwalan selesai. Boleh dipanggil berkali-kali. */
        Route::post('/cycles/{cycleId}/penjadwalan/tarik', [PaCycleAdminController::class, 'tarikStatusPenjadwalan']);

        /* Kunci & jadwalkan sebagai TUGAS LATAR (langkah 5). Servernya yang mengirim batch demi
           batch dan menunggu HC Learn; layar hanya memulai dan membaca, dan tugasnya tampil di
           panel Tugas & Aktivitas. */
        Route::post('/cycles/{cycleId}/penjadwalan/latar', [PaPenjadwalanLatarController::class, 'mulai'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/penjadwalan/latar', [PaPenjadwalanLatarController::class, 'status'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/penjadwalan/latar/ulangi', [PaPenjadwalanLatarController::class, 'ulangi'])->whereNumber('cycleId');
        /* Revisi peserta SESUDAH dikunci: terapkan Level PA terbaru, ikutkan kembali, susulan.
           Pratinjau dampak dulu, lalu satu tindakan beralasan. */
        Route::get('/cycles/{cycleId}/revisi-peserta/pratinjau', [PaRevisiPesertaController::class, 'pratinjau'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/revisi-peserta', [PaRevisiPesertaController::class, 'jalankanRevisi'])->whereNumber('cycleId');
        // Banyak orang sekaligus: cari berfilter → pratinjau → tugas latar.
        Route::get('/cycles/{cycleId}/revisi-peserta/cari', [PaRevisiPesertaController::class, 'cari'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/revisi-peserta/pratinjau-massal', [PaRevisiPesertaController::class, 'pratinjauMassal'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/revisi-peserta/massal', [PaRevisiPesertaController::class, 'mulaiMassal'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/revisi-peserta/massal', [PaRevisiPesertaController::class, 'statusMassal'])->whereNumber('cycleId');

        // Jadwal HC Learn milik penugasan yang dibatalkan: dihapus (lagi) di antrean.
        Route::post('/cycles/{cycleId}/penjadwalan/hapus-dibatalkan', [PaPenjadwalanLatarController::class, 'hapusDibatalkan'])->whereNumber('cycleId');

        /* Cetak ULANG pintu satu penugasan — pemicu `pa/reassign` dalam kontrak HC Learn.
           Beralamat pada PENUGASAN, bukan pada siklus, karena yang diterbitkan ulang memang
           satu pintu: paket sel yang cacat disalin ulang, atau tokennya sendiri rusak.
           Siklus pemiliknya dibaca dari penugasannya di dalam service lalu digerbangi
           `CETAK_ULANG_PINTU`; id dari siklus lain berhenti di sana. */
        Route::post('/penugasan/{assignmentId}/cetak-ulang-pintu', [PaCycleAdminController::class, 'cetakUlangPintu'])
            ->whereNumber('assignmentId');
        /* Hapus jadwal di HC Learn lalu jadwalkan baru — gerbang yang sama (CETAK_ULANG_PINTU). */
        Route::post('/penugasan/{assignmentId}/jadwal-ulang-pintu', [PaCycleAdminController::class, 'jadwalkanUlangPintu'])
            ->whereNumber('assignmentId');
        Route::post('/cycles/{cycleId}/buka-pengisian', [PaCycleAdminController::class, 'bukaPengisian']);
        Route::post('/cycles/{cycleId}/tutup-pengisian', [PaCycleAdminController::class, 'tutupPengisian']);
    });

/* ====================================================================== *
 * TULIS — fase HASIL                                                      *
 * ====================================================================== */

Route::middleware(['auth', 'check.jabatan:PaHasilPage'])
    ->prefix('api/v1/performance-appraisal')
    ->group(function () {
        Route::post('/cycles/{cycleId}/hitung', [PaCycleAdminController::class, 'hitungSiklus']);

        /* Hitung sebagai TUGAS LATAR (14 Okt 2026): Pemantauan memulai lalu membaca kemajuan;
           admin boleh menutup halaman. Lihat PaHitungLatarController. */
        Route::post('/cycles/{cycleId}/hitung/latar', [PaHitungLatarController::class, 'mulai'])->whereNumber('cycleId');
        Route::get('/cycles/{cycleId}/hitung/latar', [PaHitungLatarController::class, 'status'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/hitung/latar/ulangi', [PaHitungLatarController::class, 'ulangi'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/hitung/latar/hentikan', [PaHitungLatarController::class, 'hentikan'])->whereNumber('cycleId');

        /* Pratinjau tombol Hitung. POST di grup TULIS meski ia hanya membaca - izinnya
           memang izin menulis yang sedang ditanyakan, dan menaruhnya di grup BACA akan
           memperlihatkan angkanya kepada orang yang tidak boleh menekan tombolnya. */
        Route::post('/cycles/{cycleId}/hitung/pratinjau', [PaCycleAdminController::class, 'pratinjauHitungSiklus']);

        /* Menghitung ulang yang TERTAHAN. Sebabnya hampir selalu di luar modul ini — KPI
           belum disetujui, akun KPI belum ada — dan PA tidak punya cara tahu kapan sebab itu
           hilang selain bertanya ulang. */
        Route::post('/cycles/{cycleId}/sapu-tertahan', [PaCycleAdminController::class, 'sapuTertahan']);
        Route::post('/participants/{participantId}/hitung', [PaCycleAdminController::class, 'hitungPeserta']);

        /* Pintu darurat aturan satu-pintu pengiriman penilai. Di grup HASIL, bukan
           PENGISIAN: akibatnya angka yang lahir, dan yang berhak menekannya orang yang
           bertanggung jawab atas angka itu. */
        Route::post('/cycles/{cycleId}/lepas-paksa-penilai', [PaCycleAdminController::class, 'lepasPaksaPenilai']);
        Route::post('/results/{resultId}/kalibrasi', [PaCycleAdminController::class, 'kalibrasiHasil']);

        /* Dua alamat penerbitan, bukan satu, dan itu menghindari jebakan: menerbitkan
           seluruh siklus sekaligus dengan menutupnya dalam satu tombol berarti satu
           peserta yang tertahan terkunci selamanya begitu tombol ditekan. */
        Route::post('/cycles/{cycleId}/terbitkan', [PaCycleAdminController::class, 'terbitkan']);

        // Kurva grade: kunci membekukan rata-rata/simpangan baku per grup; buka wajib alasan
        // dan ditolak bila sudah ada hasil yang terbit.
        Route::post('/cycles/{cycleId}/kurva/kunci', [PaCycleAdminController::class, 'kunciKurva'])->whereNumber('cycleId');
        Route::post('/cycles/{cycleId}/kurva/buka', [PaCycleAdminController::class, 'bukaKurva'])->whereNumber('cycleId');
        Route::put('/cycles/{cycleId}/kurva/metode', [PaCycleAdminController::class, 'ubahMetodeGrade'])->whereNumber('cycleId');
        Route::put('/cycles/{cycleId}/kurva/grup', [PaCycleAdminController::class, 'aturGrupKurva'])->whereNumber('cycleId');
        Route::post('/participants/{participantId}/terbitkan', [PaCycleAdminController::class, 'terbitkanPeserta']);

        /* Rilis ke LEADER, pintu yang berbeda dari penerbitan ke karyawan (DDL-2026-130).
           Yang dibuka adalah halaman PA Tim; orang yang dinilai tetap tidak melihat apa pun
           sampai hasilnya benar-benar diterbitkan. Pencabutannya wajib bersebab. */
        Route::post('/cycles/{cycleId}/rilis-leader', [PaCycleAdminController::class, 'rilisLeader'])->whereNumber('cycleId');
        Route::post('/participants/{participantId}/rilis-leader', [PaCycleAdminController::class, 'rilisLeaderPeserta'])->whereNumber('participantId');
        Route::post('/participants/{participantId}/cabut-rilis-leader', [PaCycleAdminController::class, 'cabutRilisLeader'])->whereNumber('participantId');
        Route::post('/cycles/{cycleId}/tutup-penerbitan', [PaCycleAdminController::class, 'tutupPenerbitan']);
    });
