<?php

use App\Http\Controllers\LMS\Content\ContentManagementController;
use App\Http\Controllers\LMS\SOP\Employee\SopSecurePdfController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminBuilderController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminDokumenController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminDokumenImporController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminKebijakanController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminMonitoringController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminMonitoringPeopleController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminPenjadwalanController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminPrioritasController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminSetupSystemController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminVersiWizardController;
use App\Http\Controllers\LMS\SOP\Governance\SopApqc\SopApqcController;
use App\Http\Controllers\LMS\SOP\Governance\SopBerkasController;
use App\Http\Controllers\LMS\SOP\Governance\SopJenisDokumen\SopJenisDokumenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SOP — Suite Admin (LEAD)
|--------------------------------------------------------------------------
| Tiap fitur di-gate check.jabatan dengan page code masing-masing
| (menuSop*Page). Akses diberikan bila role user memiliki Grup Fitur LMS
| (SOP page dipetakan ke sana) ATAU grant HRIS_Page_Access ATAU menu global.
| Halaman + API digate bersama per fitur.
*/

/* ============ MASTER JENIS DOKUMEN (HIRARKI MUTU) ============
| Kebijakan -> Prosedur -> Instruksi Kerja -> Formulir. Halaman SENDIRI, bukan
| panel di Master Dokumen: yang diurus di sini adalah daftar tingkat yang
| berlaku bagi SELURUH dokumen, sementara Master Dokumen mengurus satu dokumen
| pada satu waktu. Menggabungkannya membuat perubahan master terasa seperti
| perubahan pada dokumen yang kebetulan sedang dibuka.
*/
Route::middleware(['auth', 'check.jabatan:menuSopJenisDokumenPage'])->group(function () {
    Route::get('sop/admin/jenis-dokumen', [SopJenisDokumenController::class, 'index'])->name('sop.admin.jenis-dokumen');

    Route::prefix('api/v1/sop/admin/jenis-dokumen')->group(function () {
        Route::get('/current', [SopJenisDokumenController::class, 'getDataCurrent']);
        Route::post('/store', [SopJenisDokumenController::class, 'store']);
        Route::post('/reorder', [SopJenisDokumenController::class, 'reorder']);
        Route::put('/update/{id}', [SopJenisDokumenController::class, 'update']);
        Route::delete('/delete/{id}', [SopJenisDokumenController::class, 'destroy']);
    });
});

/* ============ MASTER APQC (KLASIFIKASI PROSES BISNIS) ============
| Sumbu KEEMPAT pada identitas dokumen, dan sengaja terpisah dari tiga yang
| lain: Tipe menjawab "berkasnya apa", Jenis Dokumen "tingkatnya apa",
| Kategori SOP "topiknya apa" (dan itu melekat di paket). APQC menjawab
| "memetakan proses bisnis yang mana".
*/
Route::middleware(['auth', 'check.jabatan:menuSopApqcPage'])->group(function () {
    Route::get('sop/admin/apqc', [SopApqcController::class, 'index'])->name('sop.admin.apqc');

    Route::prefix('api/v1/sop/admin/apqc')->group(function () {
        Route::get('/current', [SopApqcController::class, 'getDataCurrent']);
        Route::post('/store', [SopApqcController::class, 'store']);
        Route::put('/update/{id}', [SopApqcController::class, 'update']);
        Route::delete('/delete/{id}', [SopApqcController::class, 'destroy']);
    });
});

/* ============ OPSI MASTER DATA BERSAMA ============
| Dipakai Master Dokumen DAN Builder, jadi gerbangnya ganda dan
| implementasinya satu -- mengikuti preseden route token secure-PDF.
| Menduplikasi endpoint per halaman berarti dua daftar jenis yang cepat atau
| lambat tidak lagi sama.
*/
Route::middleware(['auth', 'check.jabatan:menuSopDokumenPage,menuSopBuilderPage'])
    ->prefix('api/v1/sop/admin/master-data')->group(function () {
        Route::get('/jenis-dokumen', [SopJenisDokumenController::class, 'opsi']);
        Route::get('/unit-kerja', [SopJenisDokumenController::class, 'opsiUnitKerja']);
        Route::get('/entitas', [SopJenisDokumenController::class, 'opsiEntitas']);
        Route::get('/apqc', [SopApqcController::class, 'opsi']);
        Route::get('/departemen', [SopJenisDokumenController::class, 'opsiDepartemen']);
    });

/* ============ MASTER PRIORITAS DOKUMEN ============
| Halaman SENDIRI. Master yang jarang disentuh tapi menentukan urutan papan
| risiko tak boleh jadi panel yang terdorong jauh ke bawah oleh daftar lain.
|
| SATU KUNCI. Sampai 28 Agu 2026 rute ini juga menerima menuSopKategoriPage
| sebagai TONGKAT: halamannya sudah jadi, tapi tiga baris pendaftarannya di
| database tak pernah ditanam, sehingga tanpa tongkat itu ia menolak semua
| orang. DML-2026-057 menanam ketiganya, jadi tongkatnya dilepas.
|
| Jangan pasang kembali kunci Kategori di sini. Kategori sudah dicabut dari
| modul; menambahkannya lagi hanya akan membuat halaman ini menolak semua orang
| dengan cara yang sulit ditelusuri.
*/
Route::middleware(['auth', 'check.jabatan:menuSopPrioritasPage'])->group(function () {
    Route::get('sop/admin/prioritas', [SopAdminPrioritasController::class, 'halaman'])->name('sop.admin.prioritas');

    Route::prefix('api/v1/sop/admin/prioritas')->group(function () {
        Route::get('/current', [SopAdminPrioritasController::class, 'index']);
        Route::post('/store', [SopAdminPrioritasController::class, 'store']);
        Route::put('/update/{id}', [SopAdminPrioritasController::class, 'update']);
        Route::delete('/delete/{id}', [SopAdminPrioritasController::class, 'destroy']);
    });
});

/* ============ MASTER DOKUMEN SOP ============
| Titik awal alur: admin mendeklarasikan SOP di sini lebih dulu, lalu
| memakainya di banyak Paket SOP lewat pemilih Dokumen di Builder.
|
| Digate menuSopDokumenPage. Kunci ini BARU — sampai barisnya ada di tabel
| page-access, halaman ini tak akan muncul di sidebar dan aksesnya ditolak.
*/
Route::middleware(['auth', 'check.jabatan:menuSopDokumenPage'])->group(function () {
    Route::get('sop/admin/dokumen', [SopAdminDokumenController::class, 'index'])->name('sop.admin.dokumen');

    /* Wizard versi. Berada di grup yang SAMA dengan Master Dokumen supaya
       haknya satu benda: siapa pun yang boleh membuka daftar dokumen boleh
       mendefinisikan versinya. Kunci akses baru di sini berarti tiga baris
       page-access yang harus ditanam manual, dan sampai itu terjadi halamannya
       menolak semua orang — termasuk yang sudah memegang Master Dokumen.

       {versi} opsional: tanpa itu halaman membuka formulir versi BARU. */
    Route::get('sop/admin/dokumen/{dokumen}/versi/{versi?}',
        [SopAdminVersiWizardController::class, 'halaman'])->name('sop.admin.dokumen.versi');

    Route::prefix('api/v1/sop/admin/dokumen')->group(function () {
        Route::get('/current', [SopAdminDokumenController::class, 'getDataCurrent']);
        Route::get('/saran-nomor', [SopAdminDokumenController::class, 'saranNomor']);
        /* Template empat langkah untuk form versi yang belum punya id. Harus
           berada sebelum /{id}/detail agar kata "langkah" tak dibaca sebagai id. */
        Route::get('/langkah', [SopAdminDokumenController::class, 'templateLangkahVersi']);
        Route::get('/{id}/detail', [SopAdminDokumenController::class, 'detail']);
        /* Jejak audit dokumen. Tiga segmen seperti /detail, jadi tak tertelan
           pola /{id} mana pun. */
        Route::get('/{id}/riwayat', [SopAdminDokumenController::class, 'riwayat']);
        /* Riwayat versi dokumen dengan paginasi database teroptimasi. */
        Route::get('/{id}/versi', [SopAdminDokumenController::class, 'getRiwayatVersi']);
        /* Sebaran sebelum penonaktifan. Tiga segmen seperti /detail, jadi tak
           tertelan pola /{id} mana pun. WAJIB mendahului penonaktifan: sejak
           DDL-2026-055 dokumen yang sedang dipakai boleh dinonaktifkan, dan
           angka inilah satu-satunya yang menahan admin dari keputusan yang tak
           ia pahami. */
        Route::get('/{id}/sebaran-nonaktif', [SopAdminDokumenController::class, 'sebaranNonaktif']);
        Route::post('/store', [SopAdminDokumenController::class, 'store']);
        // Impor register Excel: identitas + versi, tanpa berkas. mode=dry_run|jalankan.
        Route::post('/impor-register', [SopAdminDokumenImporController::class, 'imporRegister']);
        Route::put('/update/{id}', [SopAdminDokumenController::class, 'update']);
        Route::delete('/delete/{id}', [SopAdminDokumenController::class, 'destroy']);
        // Versi & pengesahan diurus DI SINI (Master SOP), bukan di Builder.
        Route::post('/{id}/versi', [SopAdminDokumenController::class, 'storeVersi']);
        /* Draft boleh disunting dan dihapus; yang sudah terbit tidak.
           Versi terbit adalah catatan yang sudah dibaca karyawan, dan yang
           obsolete adalah riwayatnya — koreksinya lewat revisi minor. */
        Route::put('/versi/{idVersi}', [SopAdminDokumenController::class, 'updateVersi']);
        Route::delete('/versi/{idVersi}', [SopAdminDokumenController::class, 'destroyVersi']);

        /* Detail utuh & mendalam sebuah versi dokumen untuk modal audit / viewer. */
        Route::get('/versi/{idVersi}/detail', [SopAdminDokumenController::class, 'detailVersiLengkap']);

        /* Isi sebuah versi, termasuk versi LAMA — arsip yang tak bisa dibuka
           bukan arsip. ?unduh=1 memaksa bentuk unduhan (dipakai tipe FORM). */
        Route::get('/versi/{idVersi}/konten', [SopAdminDokumenController::class, 'kontenVersi']);

        // Pratinjau dampak WAJIB mendahului penerbitan — lihat dampakVersi().
        Route::get('/versi/{idVersi}/dampak', [SopAdminDokumenController::class, 'dampakVersi']);
        /* Tahap proses versi. Daftar tujuan dibaca terpisah dari pemindahannya
           supaya layar bisa menawarkan langkah yang memang sah tanpa menebak. */
        Route::get('/versi/{idVersi}/tahap', [SopAdminDokumenController::class, 'tahapVersi']);
        Route::put('/versi/{idVersi}/tahap', [SopAdminDokumenController::class, 'pindahTahapVersi']);

        /* Catatan review per versi — bisa berkali-kali, masing-masing dengan
           tanggal, hasil ber-HTML tersanitasi, dan lampiran gambar opsional. */
        Route::get('/versi/{idVersi}/review', [SopAdminDokumenController::class, 'reviewVersi']);
        Route::put('/versi/{idVersi}/review', [SopAdminDokumenController::class, 'simpanReviewVersi']);

        /* Alur berlangkah: satu panggilan membawa langkah, tahap, tujuan, dan
           syarat beserta status terpenuhinya — layar tak menyusun aturannya. */
        Route::get('/versi/{idVersi}/langkah', [SopAdminDokumenController::class, 'langkahVersi']);

        /* Membatalkan versi yang SUDAH TERBIT — hanya selama belum beredar.
           Pratinjaunya dipanggil lebih dulu supaya admin melihat penggantinya
           sebelum menekan apa pun. */
        Route::get('/versi/{idVersi}/batal-terbit', [SopAdminDokumenController::class, 'pratinjauBatalTerbit']);
        Route::post('/versi/{idVersi}/batal-terbit', [SopAdminDokumenController::class, 'batalkanVersiTerbit']);
        Route::post('/versi/{idVersi}/terbitkan', [SopAdminDokumenController::class, 'terbitkanVersi']);
        /* Rollback punya rutenya SENDIRI, bukan menumpang terbitkan:
           keduanya menggeser pointer tapi maknanya berlawanan, dan jejak
           auditnya harus bisa dibedakan. */
        Route::post('/versi/{idVersi}/kembalikan', [SopAdminDokumenController::class, 'kembalikanVersi']);

        /* Daftar ujian HC Learn untuk versi KUIS. Menunjuk METODE YANG SAMA
           dengan Builder alih-alih menyalinnya: isinya panggilan HTTP ke HC
           Learn berikut penanganan galatnya, dan dua salinan pasti menyimpang.
           Yang berbeda hanya gerbangnya — user Master tak punya
           menuSopBuilderPage, sehingga rute Builder membalas 403. */
        Route::get('/options/ujian', [SopAdminBuilderController::class, 'optionsUjian']);

        /* Siapa saja yang sudah memakai sebuah berkas. Dipasang di KEDUA
           gerbang (Master & Builder) menunjuk metode yang sama: berkas
           dilampirkan dari kedua layar, dan pemberitahuan yang cuma ada di
           satu pintu tak mencegah apa pun di pintu lainnya. */
        Route::get('/konten/{id}/pemakai', [SopAdminDokumenController::class, 'pemakaiKonten']);

        /* Pratinjau berkas yang BARU DIPILIH di wizard, sebelum tersimpan ke
           versi. Dikunci konten, bukan versi: di langkah Pemberkasan berkasnya
           belum menempel ke versi mana pun, dan /versi/{id}/konten akan
           menampilkan berkas lama sambil mengaku menampilkan yang baru. */
        Route::get('/konten/{id}/berkas', [SopAdminDokumenController::class, 'berkasKonten']);

        // Karyawan untuk mengisi pengesah — nama DAN jabatannya sekaligus.
        Route::get('/options/pengesah', [SopAdminDokumenController::class, 'optionsPengesah']);
    });
});

/* ============ PRATINJAU BERKAS ADMIN ============
   Handshake secure-PDF untuk pratinjau di layar admin. Terpisah dari rute
   karyawan karena otorisasinya berbeda: admin membuka DRAFT (belum terbit) dan
   OBSOLETE (sudah dicabut) — dua keadaan yang memang ditolak lensa karyawan,
   sehingga memakai rute karyawan berarti pratinjau admin selalu 403.

   SATU pendaftaran dengan DUA kunci (OR), bukan dua pendaftaran di dua grup:
   URI yang sama didaftar dua kali akan saling menimpa, dan yang tersisa hanya
   gerbang terakhir — pemegang kunci Master justru tertolak. */
Route::middleware(['auth', 'check.jabatan:menuSopDokumenPage,menuSopBuilderPage'])->group(function () {
    Route::post('api/v1/sop/admin/secure-pdf/token/{berkasId}', [SopSecurePdfController::class, 'tokenAdmin']);
});

/* ============ BUILDER ============ */
Route::redirect('sop/admin/builders', '/sop/admin/builder', 301);
Route::middleware(['auth', 'check.jabatan:menuSopBuilderPage'])->group(function () {
    Route::get('sop/admin/builder', [SopAdminBuilderController::class, 'index'])->name('sop.admin.builder');

    Route::prefix('api/v1/sop/admin/builder')->group(function () {
        Route::get('/options/prioritas', [SopAdminBuilderController::class, 'optionsPrioritas']);
        Route::get('/options/ujian', [SopAdminBuilderController::class, 'optionsUjian']);
        Route::get('/paket/current', [SopAdminBuilderController::class, 'listPaket']);
        Route::get('/paket/counts', [SopAdminBuilderController::class, 'countsPaket']);
        // Riwayat spesifik wajib sebelum /paket/{id} agar segmen statik tak tertelan.
        Route::get('/paket/{id}/riwayat', [SopAdminBuilderController::class, 'riwayatPaket']);
        /* Dampak publish paket, TERMASUK paket lain yang berbagi dokumennya.
           Wajib sebelum /paket/{id} agar segmen statiknya tak tertelan. */
        Route::get('/paket/{id}/dampak-publish', [SopAdminBuilderController::class, 'dampakPublishPaket']);
        Route::get('/paket/{id}/section-options', [SopAdminBuilderController::class, 'sectionOptionsPaket']);
        Route::get('/paket/{id}', [SopAdminBuilderController::class, 'showPaket']);
        Route::post('/paket/store', [SopAdminBuilderController::class, 'storePaket']);
        Route::put('/paket/update/{id}', [SopAdminBuilderController::class, 'updatePaket']);
        Route::patch('/paket/status/{id}', [SopAdminBuilderController::class, 'updateStatusPaket']);
        Route::delete('/paket/{id}/draft', [SopAdminBuilderController::class, 'discardDraft']);
        /* Pasangannya untuk paket DRAFT, yang tak punya salinan kerja untuk
           dibuang. Dipisah rutenya, bukan digabung ke atas dengan percabangan
           status: dua tindakan ini berbeda akibatnya (satu mengembalikan, satu
           mengosongkan) dan menyatukannya membuat satu tombol berperilaku dua
           macam tergantung keadaan yang tak terlihat pemanggilnya. */
        Route::delete('/paket/{id}/isi', [SopAdminBuilderController::class, 'kosongkanPaket']);
        Route::post('/paket/{id}/reconcile', [SopAdminBuilderController::class, 'reconcile']);
        Route::delete('/paket/delete/{id}', [SopAdminBuilderController::class, 'destroyPaket']);
        Route::post('/paket/{id}/clone', [SopAdminBuilderController::class, 'clonePaket']);
        Route::post('/section/{id}/clone', [SopAdminBuilderController::class, 'cloneSection']);
        /* Dampak hapus item — dipanggil tepat sebelum konfirmasinya muncul.
           Wajib SEBELUM /item/{id}/clone dst? Tidak: segmennya tiga dan tak
           tertelan pola mana pun. */
        Route::get('/item/{id}/dampak', [SopAdminBuilderController::class, 'itemDampak']);
        Route::post('/item/{id}/clone', [SopAdminBuilderController::class, 'cloneItem']);
        Route::post('/item/{id}/copy-to-paket', [SopAdminBuilderController::class, 'copyItemToPaket']);
        // Pratinjau dampak fan-out sebelum publish/rollback (Fase 3 Master Dokumen)
        Route::get('/versi/{id}/dampak', [SopAdminBuilderController::class, 'versiDampak']);
        // Pemilih Dokumen di Builder (Fase 6)
        Route::get('/dokumen/options', [SopAdminBuilderController::class, 'dokumenOptions']);
        /* /konten/{id}/pemakai dan /options/pengesah DICABUT 27 Agu 2026.
           Keduanya ada semata untuk jalur "buat dokumen baru" di Builder —
           peringatan berkas kembar dan blok penanda tangan. Builder tak lagi
           melahirkan dokumen, jadi tak ada satu pun layar yang memanggilnya.
           Metodenya sendiri tetap hidup di grup Master, tempatnya memang. */
        Route::post('/paket/{idPaket}/section/store', [SopAdminBuilderController::class, 'storeSection']);
        Route::put('/section/update/{id}', [SopAdminBuilderController::class, 'updateSection']);
        Route::post('/section/reorder', [SopAdminBuilderController::class, 'reorderSection']);
        Route::delete('/section/delete/{id}', [SopAdminBuilderController::class, 'destroySection']);
        Route::post('/paket/{idPaket}/item/store', [SopAdminBuilderController::class, 'storeItem']);
        Route::put('/item/update/{id}', [SopAdminBuilderController::class, 'updateItem']);
        Route::post('/item/reorder', [SopAdminBuilderController::class, 'reorderItem']);
        Route::delete('/item/delete/{id}', [SopAdminBuilderController::class, 'destroyItem']);
    });
});

/* ============ VERSI ITEM — BACA SAJA ============
   Siklus versi (buat, siapkan rilis, terbitkan, hapus) DICABUT dari Builder pada
   27 Agu 2026 dan sepenuhnya milik Master Dokumen: /sop/admin/dokumen/{dokumen}/versi
   dengan lima langkahnya (Pengajuan, Review, Pengesahan, Pemberkasan, Tinjauan Akhir).

   Rute publish di sini dulu sengaja dibuka untuk memecah jalan buntu rilis pertama:
   publish paket menolak selama ada versi DRAFT, sementara satu-satunya penerbitnya
   ada di panel Builder. Jalan buntu itu tak bisa terjadi lagi — item hanya boleh
   menunjuk dokumen yang SUDAH punya versi berlaku (pastikanDokumenSiap), jadi paket
   tak pernah lagi menggendong versi draft milik dokumen yang belum sah. Yang tersisa
   setelah pencabutan hanyalah membaca: panel versi di Builder menampilkan keadaan,
   bukan mengubahnya. */
Route::middleware(['auth', 'check.jabatan:menuSopBuilderPage'])->group(function () {
    Route::prefix('api/v1/sop/admin/berkas')->group(function () {
        Route::get('/item/{idItem}/versi', [SopBerkasController::class, 'listVersi']);
        Route::get('/versi/{id}/konten', [SopBerkasController::class, 'viewKonten']);
    });
});

/* ============ KONTEN (library bersama, scope SOP) ============
   Reuse ContentManagementController + komponen FE LMS; dibedakan hanya ->defaults('scope','SOP').
   Konten SOP terisolasi dari LMS (controller memfilter kolom Scope). */
Route::middleware(['auth', 'check.jabatan:menuSopKontenPage'])
    ->prefix('sop/admin/konten')
    ->group(function () {
        Route::get('/', [ContentManagementController::class, 'index'])->defaults('scope', 'SOP')->name('sop.admin.konten');
        Route::get('/kategori', [ContentManagementController::class, 'getKategori'])->defaults('scope', 'SOP');
        Route::get('/konten/{id}', [ContentManagementController::class, 'getKontenByKategori'])->defaults('scope', 'SOP');
        Route::get('/berkas/{id}/metadata', [ContentManagementController::class, 'getKontenBerkasMetadata'])->defaults('scope', 'SOP');
        Route::post('/berkas/{id}/render-ulang', [ContentManagementController::class, 'renderUlangBerkas'])->defaults('scope', 'SOP');
        Route::post('/berkas/{id}/verifikasi-ulang', [ContentManagementController::class, 'verifikasiUlangBerkas'])->defaults('scope', 'SOP');
        Route::get('/berkas/{id}/unduh', [ContentManagementController::class, 'unduhBerkas'])->defaults('scope', 'SOP');
        Route::get('/bunny-thumbnail/{videoId}/{fileName}', [ContentManagementController::class, 'getBunnyThumbnail'])->where('fileName', '.*')->defaults('scope', 'SOP');
        Route::post('/create-kategori', [ContentManagementController::class, 'createKategori'])->defaults('scope', 'SOP');
        Route::post('/rename', [ContentManagementController::class, 'rename'])->defaults('scope', 'SOP');
        Route::post('/upload-konten', [ContentManagementController::class, 'uploadKonten'])->defaults('scope', 'SOP');
        Route::post('/get-upload-ticket', [ContentManagementController::class, 'getUploadTicket'])->defaults('scope', 'SOP');
        Route::post('/upload-task/start', [ContentManagementController::class, 'startUploadTask'])->defaults('scope', 'SOP');
        Route::post('/upload-task/{exportId}/progress', [ContentManagementController::class, 'progressUploadTask'])->defaults('scope', 'SOP');
        Route::post('/upload-task/{exportId}/fail', [ContentManagementController::class, 'failUploadTask'])->defaults('scope', 'SOP');
        Route::delete('/delete-upload-ticket', [ContentManagementController::class, 'deleteUploadTicket'])->defaults('scope', 'SOP');
        Route::post('/delete-konten', [ContentManagementController::class, 'deleteKonten'])->defaults('scope', 'SOP');
        Route::post('/delete-kategori', [ContentManagementController::class, 'deleteKategori'])->defaults('scope', 'SOP');
    });

/* ============ PENJADWALAN ============ */
Route::middleware(['auth', 'check.jabatan:menuSopPenjadwalanPage'])->group(function () {
    Route::get('sop/admin/penjadwalan', [SopAdminPenjadwalanController::class, 'index'])->name('sop.admin.penjadwalan');

    Route::prefix('api/v1/sop/admin/penjadwalan')->group(function () {
        Route::get('/options/paket', [SopAdminPenjadwalanController::class, 'optionsPaket']);
        Route::get('/options/filter', [SopAdminPenjadwalanController::class, 'optionsFilter']);
        Route::get('/options/kandidat', [SopAdminPenjadwalanController::class, 'optionsKandidat']);
        Route::get('/options/kandidat-ringkas', [SopAdminPenjadwalanController::class, 'kandidatRingkas']);
        Route::post('/options/validasi-tempel', [SopAdminPenjadwalanController::class, 'validasiTempel']);
        Route::get('/preflight', [SopAdminPenjadwalanController::class, 'preflight']);
        Route::get('/ringkas', [SopAdminPenjadwalanController::class, 'ringkas']);
        // statik dulu, baru dinamis
        Route::get('/batch/{id}/karyawan-kodes', [SopAdminPenjadwalanController::class, 'batchKaryawanKodes']);
        Route::get('/batch/{id}/karyawan', [SopAdminPenjadwalanController::class, 'batchKaryawan']);
        Route::get('/batch/{id}', [SopAdminPenjadwalanController::class, 'batch']);
        Route::get('/batch/{id}/riwayat', [SopAdminPenjadwalanController::class, 'riwayat']);
        Route::get('/batch/{id}/export', [SopAdminPenjadwalanController::class, 'exportBatch']);
        Route::post('/batch/{id}/ingatkan', [SopAdminPenjadwalanController::class, 'ingatkanBatch']);
        Route::post('/batch/{id}/detail', [SopAdminPenjadwalanController::class, 'ubahDetail']);
        Route::post('/batch/{id}/karyawan', [SopAdminPenjadwalanController::class, 'tambahKaryawan']);
        Route::post('/batch/{id}/bulk-cabut', [SopAdminPenjadwalanController::class, 'bulkCabut']);
        Route::get('/karyawan', [SopAdminPenjadwalanController::class, 'searchKaryawan']);
        Route::get('/list', [SopAdminPenjadwalanController::class, 'list']);
        Route::post('/assign', [SopAdminPenjadwalanController::class, 'assign']);
        Route::post('/cabut', [SopAdminPenjadwalanController::class, 'cabut']);
    });
});

/* ============ MONITORING ============ */
Route::middleware(['auth', 'check.jabatan:menuSopMonitoringPage'])->group(function () {
    Route::get('sop/admin/monitoring', [SopAdminMonitoringController::class, 'index'])->name('sop.admin.monitoring');

    /* Halaman turunan monitoring (deep-dive per karyawan).

       Parameternya TOKEN, bukan Kode_Karyawan. Alamat halaman ikut terbawa ke
       riwayat peramban, tangkapan layar, dan log akses, jadi kode polos di
       sana membuat /karyawan/A4 → /karyawan/A5 bisa ditebak berurutan.
       Endpoint API di bawah tetap memakai kode polos: pemanggilnya adalah
       halaman ini, yang memang sudah memegang kodenya. */
    Route::get('sop/admin/monitoring/karyawan/{token}', [SopAdminMonitoringPeopleController::class, 'karyawanPage'])
        ->name('sop.admin.monitoring.karyawan');
    Route::get('sop/admin/monitoring/karyawan/{token}/paket/{id}', [SopAdminMonitoringPeopleController::class, 'karyawanPaketPage'])
        ->name('sop.admin.monitoring.karyawan.paket');

    /* Halaman detail/monitoring paket SOP */
    Route::get('sop/admin/paket/{id}', [SopAdminMonitoringController::class, 'paketDetailPage'])
        ->name('sop.admin.paket.detail');
    Route::get('sop/admin/monitoring/paket/{id}', [SopAdminMonitoringController::class, 'paketDetailPage'])
        ->name('sop.admin.monitoring.paket.detail');

    Route::prefix('api/v1/sop/admin/monitoring')->group(function () {
        Route::get('/summary', [SopAdminMonitoringController::class, 'summary']);
        Route::get('/coverage', [SopAdminMonitoringController::class, 'coverage']);

        /* ---- SEMUA SEGMEN STATIK DI ATAS YANG DINAMIS ----
           /karyawan harus mendahului /karyawan/{kode}; /paket/{id}/section &
           /funnel harus mendahului /paket/{id}; /dokumen/ringkasan mendahului
           pola /dokumen lain. Salah urut = route tertelan parameter. */

        Route::get('/filter-options', [SopAdminMonitoringPeopleController::class, 'filterOptions']);

        /* Palet perintah memanggil alamat ini sejak lama; rutenya tak pernah
           ada, dan pemanggilnya menelan 404-nya sendiri. Segmen statik, jadi
           tak bertabrakan dengan /paket/{id} di bawah. */
        Route::get('/paket-options', [SopAdminMonitoringController::class, 'paketOptions']);

        // sudut pandang karyawan
        Route::get('/karyawan', [SopAdminMonitoringPeopleController::class, 'karyawanList']);
        Route::get('/karyawan/{kode}/ringkas', [SopAdminMonitoringPeopleController::class, 'karyawanRingkas']);
        Route::get('/karyawan/{kode}/paket/{id}', [SopAdminMonitoringPeopleController::class, 'karyawanPaketDetail']);
        Route::get('/karyawan/{kode}/paket', [SopAdminMonitoringPeopleController::class, 'karyawanPaket']);
        Route::get('/karyawan/{kode}/riwayat', [SopAdminMonitoringPeopleController::class, 'karyawanRiwayat']);
        Route::get('/karyawan/{kode}/rekam-baca', [SopAdminMonitoringPeopleController::class, 'karyawanRekamBaca']);
        Route::get('/karyawan/{kode}/atestasi', [SopAdminMonitoringPeopleController::class, 'karyawanAtestasi']);
        Route::get('/karyawan/{kode}/absensi', [SopAdminMonitoringPeopleController::class, 'karyawanAbsensi']);
        Route::get('/karyawan/{kode}', [SopAdminMonitoringPeopleController::class, 'karyawanDetail']);

        // kontrol dokumen
        Route::get('/dokumen/ringkasan', [SopAdminMonitoringController::class, 'dokumenRingkasan']);
        /* Lapis dokumen dashboard. Tiga segmen, jadi tak tertelan pola
           /dokumen maupun /dokumen/{id}/detail. Berbutir DOKUMEN, berbeda dari
           /dokumen/ringkasan yang berbutir baris paket. */
        Route::get('/dokumen/kesehatan', [SopAdminMonitoringController::class, 'dokumenKesehatan']);
        Route::get('/dokumen/jangkauan-publik', [SopAdminMonitoringController::class, 'dokumenJangkauanPublik']);
        // 3 segmen, jadi tak tertelan /dokumen maupun /dokumen/ringkasan.
        Route::get('/dokumen/{id}/detail', [SopAdminMonitoringController::class, 'dokumenDetail']);
        Route::get('/dokumen', [SopAdminMonitoringController::class, 'dokumen']);

        // analitik
        Route::get('/risiko', [SopAdminMonitoringController::class, 'risiko']);
        Route::get('/pulse', [SopAdminMonitoringController::class, 'pulse']);
        Route::get('/analitik/tren', [SopAdminMonitoringController::class, 'tren']);
        Route::get('/analitik/waktu-selesai', [SopAdminMonitoringController::class, 'waktuSelesai']);
        Route::get('/analitik/aging', [SopAdminMonitoringController::class, 'aging']);
        Route::get('/analitik/heatmap-sumbu', [SopAdminMonitoringController::class, 'heatmapSumbu']);
        Route::get('/analitik/heatmap', [SopAdminMonitoringController::class, 'heatmap']);

        // agregat tab Ringkasan (bab Momentum / Titik Macet / Kualitas)
        Route::get('/analitik/momentum', [SopAdminMonitoringController::class, 'momentum']);
        Route::get('/analitik/peringkat-unit', [SopAdminMonitoringController::class, 'peringkatUnit']);
        Route::get('/analitik/konsentrasi', [SopAdminMonitoringController::class, 'konsentrasi']);
        Route::get('/analitik/titik-henti', [SopAdminMonitoringController::class, 'titikHenti']);
        Route::get('/analitik/pemahaman', [SopAdminMonitoringController::class, 'pemahaman']);
        Route::get('/kepatuhan/kilat', [SopAdminMonitoringController::class, 'kilat']);
        Route::get('/kepatuhan/mandek', [SopAdminMonitoringController::class, 'mandek']);

        // kuis
        Route::get('/kuis-tertahan', [SopAdminMonitoringController::class, 'kuisTertahan']);
        Route::post('/kuis/{id}/reset', [SopAdminMonitoringController::class, 'resetKuis']);

        // aksi
        Route::post('/ingatkan', [SopAdminMonitoringPeopleController::class, 'ingatkan']);

        // export v2 (berbasis saringan) — /export lama tetap ada, tak diubah
        Route::get('/export/estimasi', [SopAdminMonitoringPeopleController::class, 'exportEstimasi']);
        Route::get('/export/karyawan/{kode}/paket', [SopAdminMonitoringPeopleController::class, 'exportPaketKaryawan']);
        // rbac_aksi=VIEW: ekspor hanya membaca (RBAC Fase 5 — bukan aksi tulis).
        Route::post('/export/request', [SopAdminMonitoringPeopleController::class, 'exportRequest'])->defaults('rbac_aksi', 'VIEW');
        Route::post('/export', [SopAdminMonitoringController::class, 'export'])->defaults('rbac_aksi', 'VIEW');

        // drill paket — statik dulu, baru {id}
        Route::get('/paket/{id}/section', [SopAdminMonitoringController::class, 'sectionCoverage']);
        Route::get('/paket/{id}/funnel', [SopAdminMonitoringController::class, 'funnel']);
        Route::get('/paket/{id}', [SopAdminMonitoringController::class, 'detail']);
    });
});

/* ============ OTOMASI SOP (ATURAN PENUGASAN & RIWAYAT EKSEKUSI) ============
   Dulu satu halaman "Kebijakan & Otomasi" berisi empat tab. Dua tab yang
   menyentuh mesin — Kebijakan Global dan Otomasi Terjadwal — pindah ke halaman
   Set Up System di bawah. URL dan page code halaman ini sengaja TIDAK diubah:
   keduanya kunci akses yang sudah dipegang, dan /sop/admin/kebijakan masih
   ditautkan dari contentDashboard.vue. */
Route::middleware(['auth', 'check.jabatan:menuSopKebijakanPage'])->group(function () {
    Route::get('sop/admin/kebijakan', [SopAdminKebijakanController::class, 'index'])->name('sop.admin.kebijakan');

    Route::prefix('api/v1/sop/admin/kebijakan')->group(function () {
        Route::get('/summary', [SopAdminKebijakanController::class, 'summary']);
        Route::get('/options', [SopAdminKebijakanController::class, 'options']);
        Route::get('/runs', [SopAdminKebijakanController::class, 'history']);
        Route::get('/rules', [SopAdminKebijakanController::class, 'listRules']);
        Route::post('/rules', [SopAdminKebijakanController::class, 'storeRule']);
        Route::get('/rules/{id}', [SopAdminKebijakanController::class, 'showRule']);
        Route::put('/rules/{id}', [SopAdminKebijakanController::class, 'updateRule']);
        Route::post('/rules/{id}/preview', [SopAdminKebijakanController::class, 'previewRule'])->defaults('rbac_aksi', 'VIEW');
        Route::post('/rules/{id}/activate', [SopAdminKebijakanController::class, 'activateRule']);
        Route::post('/rules/{id}/deactivate', [SopAdminKebijakanController::class, 'deactivateRule']);
    });
});

/* ============ SET UP SYSTEM (KEBIJAKAN GLOBAL & OTOMASI TERJADWAL) ============
   Tetap milik modul SOP — Id_Grup_Fitur 3 (LMS) — hanya duduk di header sidebar
   "Set Up System" sendiri. Pemisahannya per-halaman karena gerbang otorisasi di
   aplikasi ini memang per-Jenis_page; tidak ada konsep izin di bawah halaman,
   sehingga menyembunyikan tab di Vue tidak akan menutup satu endpoint pun. */
Route::middleware(['auth', 'check.jabatan:menuSopSetupSystemPage'])->group(function () {
    Route::get('sop/admin/setup-system', [SopAdminSetupSystemController::class, 'index'])->name('sop.admin.setup-system');

    Route::prefix('api/v1/sop/admin/setup-system')->group(function () {
        Route::get('/settings', [SopAdminSetupSystemController::class, 'settings']);
        Route::put('/settings', [SopAdminSetupSystemController::class, 'updateSettings']);
    });
});

/* Pemicu manual otomasi sengaja bergerbang DUA kunci. Tombolnya hidup di kartu
   job halaman Set Up System, tetapi menjalankan evaluasi tepat setelah membuat
   aturan adalah kebutuhan admin modul — bukan sysadmin. Pola OR ini sama dengan
   gerbang Master Dokumen/Builder di berkas ini. */
Route::middleware(['auth', 'check.jabatan:menuSopKebijakanPage,menuSopSetupSystemPage'])->group(function () {
    Route::post('api/v1/sop/admin/kebijakan/runs/{type}', [SopAdminKebijakanController::class, 'runNow']);
});
