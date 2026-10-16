<?php

use App\Http\Controllers\KPI\PerformanceAppraisal\MasterData\PaMasterDataController;
use Illuminate\Support\Facades\Route;

/*
 * Admin Performance Appraisal — master data.
 *
 * Kunci halaman TERPISAH dari halaman siklus (`PerformanceAppraisalPage`). Yang
 * mengelola siklus belum tentu boleh mengubah kompetensi, bobot pilar, dan pita grade —
 * yang terakhir mengubah arti nilai semua orang di siklus berikutnya. Menyatukannya ke
 * satu kunci berarti memberi keduanya kepada siapa pun yang butuh salah satunya.
 *
 * `{master}` adalah bagian alamat, bukan delapan alamat yang hampir sama. Nama yang
 * tidak dikenal ditolak service dengan menyebut daftar yang tersedia.
 */
/*
 * Gerbang rute menerima kunci halaman master MANA PUN — cukup untuk membuka layar admin.
 * Master mana yang boleh dibuka dijaga terpisah di controller, per master. Dua lapis ini
 * berbeda maksudnya: yang pertama menjawab "boleh masuk ruangannya", yang kedua "boleh
 * membuka lemari yang mana".
 */
Route::middleware(['auth', 'check.jabatan:PaMasterKompetensiPage,PaMasterJenisKompetensiPage,'
    .'PaMasterKategoriPenilaiPage,PaMasterAturanPenilaiPage,PaMasterBobotPilarPage,'
    .'PaMasterPitaNilaiPage,PaMasterKuadranHavPage,PaMasterPaketSoalPage,PaMasterGrupKurvaPage'])->group(function () {
        Route::get('/performance-appraisal/master', [PaMasterDataController::class, 'index'])
            ->name('performance-appraisal.master');

        // Alamat per master supaya tiap layar punya URL sendiri: bisa ditautkan, bisa
        // di-bookmark, dan bisa jadi baris menu yang nyata. Halamannya sama; nama masternya
        // diteruskan sebagai prop.
        /* Di ATAS rute berpola `{master}`: ditaruh di bawah, 'syarat-kompetensi' akan
           tertangkap sebagai nama master dan dijawab "master tidak dikenal". */
        Route::get('/performance-appraisal/master/syarat-kompetensi', [PaMasterDataController::class, 'halamanSyaratKompetensi'])
            ->name('performance-appraisal.master.syarat-kompetensi');

        Route::get('/performance-appraisal/master/ikatan-paket-soal', [PaMasterDataController::class, 'halamanIkatanBaku'])
            ->name('performance-appraisal.master.ikatan-paket-soal');

        /* Halaman berbentuk MATRIKS level x jenis penilai, bukan daftar empat baris seperti
           master lain — karena itulah bentuk yang menjawab pertanyaan yang sebenarnya
           ditanyakan tentangnya. Sama seperti dua di atas, harus di ATAS rute berpola. */
        Route::get('/performance-appraisal/master/kategori-penilai', [PaMasterDataController::class, 'halamanJenisPenilai'])
            ->name('performance-appraisal.master.kategori-penilai');

        // Master Level PA (DDL-2026-183): level HRIS + level khusus PA. Di ATAS rute berpola `{master}`.
        Route::get('/performance-appraisal/master/level', [PaMasterDataController::class, 'halamanLevelMaster'])
            ->name('performance-appraisal.master.level');

        // Koreksi Level PA per karyawan (DDL-2026-177): halaman sendiri, di ATAS rute berpola `{master}`.
        Route::get('/performance-appraisal/master/level-pa', [PaMasterDataController::class, 'halamanLevelPa'])
            ->name('performance-appraisal.master.level-pa');

        // Grup Level Kurva: halaman sendiri, di ATAS rute berpola `{master}`.
        Route::get('/performance-appraisal/master/grup-kurva', [PaMasterDataController::class, 'halamanGrupKurva'])
            ->name('performance-appraisal.master.grup-kurva');

        Route::get('/performance-appraisal/master/{master}', [PaMasterDataController::class, 'index'])
            ->name('performance-appraisal.master.satu');

        Route::prefix('api/v1/performance-appraisal/master')->group(function () {
            // Seluruh daftar acuan untuk dropdown dalam SATU panggilan. Delapan layar yang
            // masing-masing mengambil daftar levelnya sendiri berarti delapan kali
            // menanyakan hal yang sama.
            Route::get('/referensi', [PaMasterDataController::class, 'referensi']);

            /* Matriks dimensi penilaian x level. Alamatnya di ATAS `/{master}` karena rute berpola
               menangkap apa pun; ditaruh di bawah, 'syarat-kompetensi' akan dibaca sebagai nama
               master dan dijawab "master tidak dikenal". */
            Route::get('/syarat-kompetensi/matriks', [PaMasterDataController::class, 'matriksSyaratKompetensi']);

            // Koreksi Level PA per karyawan (DDL-2026-177).
            Route::get('/level-pa', [PaMasterDataController::class, 'daftarLevelPa']);
            Route::get('/level-pa/karyawan', [PaMasterDataController::class, 'cariKaryawanLevelPa']);
            Route::get('/level-pa/calon', [PaMasterDataController::class, 'calonLevelPa']);
            Route::get('/level-pa/ekspor', [PaMasterDataController::class, 'eksporLevelPa']);
            Route::post('/level-pa/impor', [PaMasterDataController::class, 'imporLevelPa']);
            Route::post('/level-pa', [PaMasterDataController::class, 'simpanLevelPa']);
            Route::put('/level-pa/{id}', [PaMasterDataController::class, 'ubahLevelPa'])->whereNumber('id');
            Route::post('/level-pa/{id}/cabut', [PaMasterDataController::class, 'cabutLevelPa'])->whereNumber('id');

            // Master Level PA (DDL-2026-183): level HRIS + level khusus PA (mis. Officer Specialist).
            Route::get('/level-master', [PaMasterDataController::class, 'daftarLevelMaster']);
            Route::get('/level-master/periksa', [PaMasterDataController::class, 'periksaLevelMaster']);
            Route::post('/level-master/sinkron', [PaMasterDataController::class, 'sinkronLevelMaster']);
            Route::post('/level-master', [PaMasterDataController::class, 'tambahLevelMaster']);
            Route::put('/level-master/{id}', [PaMasterDataController::class, 'ubahLevelMaster'])->whereNumber('id');
            Route::post('/level-master/{id}/aktif', [PaMasterDataController::class, 'aturAktifLevelMaster'])->whereNumber('id');

            // Matriks Kompetensi per KOMPETENSI dan tingkatnya (DDL-2026-175).
            Route::get('/matriks-kompetensi', [PaMasterDataController::class, 'matriksKompetensi']);
            Route::put('/matriks-kompetensi/{levelId}', [PaMasterDataController::class, 'simpanMatriksKompetensi'])
                ->whereNumber('levelId');
            Route::get('/kompetensi/{id}/tingkat', [PaMasterDataController::class, 'tingkatKompetensi'])
                ->whereNumber('id');
            Route::put('/kompetensi/{id}/tingkat', [PaMasterDataController::class, 'simpanTingkatKompetensi'])
                ->whereNumber('id');
            Route::get('/ikatan-paket-soal/matriks', [PaMasterDataController::class, 'matriksIkatanBaku']);
            Route::get('/jenis-penilai/matriks', [PaMasterDataController::class, 'matriksJenisPenilai']);
            // Urutan wizard dan penanda Kalibrasi Penilai: kamus hidup, berlaku seketika.
            Route::put('/jenis-penilai/urutan-kategori', [PaMasterDataController::class, 'tataUrutanKategoriPenilai']);
            Route::put('/jenis-penilai/kategori/{kategoriId}/kalibrasi', [PaMasterDataController::class, 'setelBolehKalibrasi'])
                ->whereNumber('kategoriId');
            // Minimal masa kerja peserta (DDL-2026-191), di layar yang sama dengan pengecualian level.
            Route::get('/jenis-penilai/kelayakan-masa-kerja', [PaMasterDataController::class, 'kelayakanMasaKerja']);
            Route::put('/jenis-penilai/kelayakan-masa-kerja', [PaMasterDataController::class, 'simpanKelayakanMasaKerja']);
            Route::post('/jenis-penilai/bulk/kecualikan', [PaMasterDataController::class, 'kecualikanBanyakLevel']);
            Route::post('/jenis-penilai/bulk/cabut-pengecualian', [PaMasterDataController::class, 'cabutBanyakPengecualianLevel']);

            /* Pengecualian level tinggal di sini, bukan di master mana pun: "level ini tidak
               dinilai" adalah pernyataan tentang satu BARIS matriks — ia mengosongkan seluruh
               selnya sekaligus, bukan satu kategori penilai saja. */
            Route::post('/jenis-penilai/{levelId}/sel', [PaMasterDataController::class, 'setelSelJenisPenilai'])
                ->whereNumber('levelId');
            Route::post('/jenis-penilai/{levelId}/kecualikan', [PaMasterDataController::class, 'kecualikanLevel'])
                ->whereNumber('levelId');
            Route::post('/jenis-penilai/{levelId}/cabut-pengecualian', [PaMasterDataController::class, 'cabutPengecualianLevel'])
                ->whereNumber('levelId');
            Route::put('/jenis-penilai/{levelId}/komposisi', [PaMasterDataController::class, 'simpanKomposisiLevel'])
                ->whereNumber('levelId');

            /* '/bagian' didaftarkan SEBELUM '/{id}/...' supaya ia tidak tertangkap sebagai {id}.
               Ruas statis yang ditaruh di bawah ruas berparameter akan dijawab "ikatan bagian tidak
               ditemukan" — galat yang menuduh datanya, bukan urutan rutenya. */
            Route::get('/ikatan-paket-soal/bagian', [PaMasterDataController::class, 'bagianUjian']);
            Route::get('/katalog-paket-soal', [PaMasterDataController::class, 'katalogPaketSoalMaster']);
            Route::post('/ikatan-paket-soal', [PaMasterDataController::class, 'simpanIkatanBaku']);
            Route::post('/ikatan-paket-soal/verifikasi', [PaMasterDataController::class, 'verifikasiIkatanBaku']);
            Route::get('/ikatan-paket-soal/{id}/label', [PaMasterDataController::class, 'labelIkatan'])
                ->whereNumber('id');
            Route::post('/ikatan-paket-soal/{id}/periksa', [PaMasterDataController::class, 'periksaSelIkatan'])
                ->whereNumber('id');
            Route::post('/ikatan-paket-soal/{id}/cabut', [PaMasterDataController::class, 'cabutIkatanBaku'])
                ->whereNumber('id');
            Route::put('/syarat-kompetensi/{levelId}', [PaMasterDataController::class, 'simpanSyaratKompetensi'])
                ->whereNumber('levelId');

            Route::get('/grup-kurva', [PaMasterDataController::class, 'daftarGrupKurva']);
            Route::put('/grup-kurva', [PaMasterDataController::class, 'simpanGrupKurva']);

            Route::get('/{master}', [PaMasterDataController::class, 'daftar']);
            Route::post('/{master}', [PaMasterDataController::class, 'simpan']);
            Route::get('/{master}/{id}', [PaMasterDataController::class, 'detail'])->whereNumber('id');
            Route::put('/{master}/{id}', [PaMasterDataController::class, 'perbarui'])->whereNumber('id');

            // POST, bukan DELETE: barisnya tidak dihapus, hanya dinonaktifkan. Memakai DELETE
            // akan menjanjikan hal yang tidak dilakukan.
            Route::post('/{master}/{id}/nonaktif', [PaMasterDataController::class, 'nonaktifkan'])
                ->whereNumber('id');
            Route::post('/{master}/{id}/aktif', [PaMasterDataController::class, 'aktifkan'])
                ->whereNumber('id');
        });

        // Rincian aturan penilai disimpan SEKALIGUS, bukan per baris: bobot antar kategori
        // wajib berjumlah 100, dan itu hanya bisa diperiksa saat seluruh anggotanya diketahui.
        Route::prefix('api/v1/performance-appraisal/aturan-penilai')->group(function () {
            Route::put('/{policyId}/rincian', [PaMasterDataController::class, 'simpanRincianAturan'])
                ->whereNumber('policyId');
            Route::post('/{policyId}/aktifkan', [PaMasterDataController::class, 'aktifkanAturan'])
                ->whereNumber('policyId');
        });
    });
