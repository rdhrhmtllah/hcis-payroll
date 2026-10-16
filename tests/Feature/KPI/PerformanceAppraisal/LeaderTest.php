<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaLeaderService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MembandingkanBentukRespons;
use Tests\TestCase;

/**
 * Sisi leader (L01 dan L02).
 *
 * TIM DIAMBIL DARI DATA SUNGGUHAN, TIDAK DIKARANG.
 * `N_HRIS_Organization_Relationship` berada di luar awalan `N_PA_`, dan berkas ini sengaja
 * tidak menulis satu baris pun ke sana — bukan karena rumit, melainkan karena batas modul
 * itu memang aturan proyek ini. Konsekuensinya uji ini bergantung pada adanya leader
 * ber-tim di basis data pengembangan; kalau tidak ada, ia BERHENTI dengan keterangan,
 * bukan lolos diam-diam.
 *
 * Yang diuji di sini sebagian besar adalah hal yang TIDAK BOLEH muncul. Uji semacam itu
 * gampang hijau tanpa menguji apa pun — kalau kueri penyiapannya salah dan hasilnya
 * kosong, "tidak ada nilai per penilai" tentu saja terpenuhi. Karena itu hampir setiap
 * uji di sini lebih dulu menegaskan bahwa yang seharusnya ADA memang ada.
 */
class LeaderTest extends TestCase
{
    use DatabaseTransactions;
    use MembandingkanBentukRespons;

    private int $userIdLeader;

    private string $kodeLeader;

    /** @var list<string> */
    private array $anggota;

    private int $cycleId;

    private int $levelId;

    private int $snapshotId;

    private int $kategoriSelfId;

    private int $kategoriLainId;

    /** Penghitung agar tiap penugasan uji memakai penilai yang berbeda. */
    private int $penilaiKe = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pilihLeader();
        $this->siapkanSiklus();
    }

    /* ------------------------------------------------------------------ *
     * L01 — daftar tim                                                    *
     * ------------------------------------------------------------------ */

    /**
     * Pendaftaran halamannya di basis data — DML-2026-105.
     *
     * Halaman ini punya controller, service, rute, dan halaman Vue sejak awal, tetapi
     * NOL baris pendaftaran. Akibatnya bukan "menunya tidak ada" melainkan `check.jabatan`
     * menolak siapa pun yang mengetik alamatnya, termasuk para atasan yang justru dituju.
     *
     * Diuji sebagai keadaan basis data, bukan lewat permintaan HTTP: yang menentukan bukan
     * kode mana pun melainkan tiga baris data, dan permintaan HTTP dari akun uji tidak
     * membedakan "lolos karena global" dari "lolos karena kebetulan punya role KPI".
     */
    public function test_halaman_leader_terdaftar_dan_dapat_dicapai(): void
    {
        $menu = DB::table('N_HRIS_Menus')
            ->where('Jenis_page', 'PerformanceAppraisalLeaderPage')
            ->first(['Url_Menu', 'Is_Global_Menu', 'Header_Code']);

        $this->assertNotNull($menu, 'Baris menu PerformanceAppraisalLeaderPage belum ditanam (DML-2026-105).');
        $this->assertSame('/performance-appraisal/tim', $menu->Url_Menu);

        /* Tanpa penanda global halamannya tetap ditolak: tidak ada satu pun baris
           HRIS_Page_Access maupun role yang memegang kuncinya, dan "leader" memang bukan
           role melainkan keadaan — siapa saja yang kebetulan punya bawahan. */
        $this->assertSame('Y', $menu->Is_Global_Menu,
            'Halaman Leader tidak global; check.jabatan akan menolak setiap atasan.');

        $peta = DB::table('N_HRIS_Grup_Fitur_Map')
            ->where('Jenis_page', 'PerformanceAppraisalLeaderPage')
            ->first(['Flag_Aktif', 'Id_Grup_Fitur']);

        $this->assertNotNull($peta, 'Pemetaan grup fitur belum ada — menunya tak akan muncul di sidebar.');
        $this->assertSame('Y', $peta->Flag_Aktif);

        // Grupnya harus sama dengan "Penilaian Saya": keduanya halaman karyawan pada modul
        // yang sama, dan grup yang berbeda menaruh salah satunya di modul lain.
        $this->assertSame(
            (int) DB::table('N_HRIS_Grup_Fitur_Map')
                ->where('Jenis_page', 'PerformanceAppraisalSayaPage')
                ->where('Flag_Aktif', 'Y')
                ->value('Id_Grup_Fitur'),
            (int) $peta->Id_Grup_Fitur,
        );
    }

    public function test_tim_hanya_berisi_anggota_leader_itu(): void
    {
        $penyusup = $this->pesertaBukanAnggota();

        $hasil = $this->service()->tim($this->userIdLeader, $this->cycleId);

        $this->assertNotEmpty($hasil['anggota'], 'Tim uji kosong; penyiapannya gagal.');

        $ids = array_column($hasil['anggota'], 'participant_id');

        $this->assertNotContains(
            $penyusup,
            $ids,
            'Peserta yang bukan anggota tim ikut terbawa. Ini kebocoran paling langsung '
            .'di layar ini: satu baris orang lain di daftar yang seharusnya berisi tim '
            .'sendiri, dan tidak ada apa pun di layar yang menandainya sebagai keliru.'
        );

        foreach ($hasil['anggota'] as $a) {
            $this->assertContains($a['kode_karyawan'], $this->anggota);
        }
    }

    public function test_bukan_leader_mendapat_tanpa_tim_bukan_galat(): void
    {
        $bukanLeader = DB::table('Karyawan as k')
            ->leftJoin('HRIS_Karyawan_Team as t', 't.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.UserID_Web')
            ->whereNull('t.Kode_Karyawan_Team')
            ->orderBy('k.Kode_Karyawan')
            ->value('k.UserID_Web');

        if ($bukanLeader === null) {
            $this->markTestSkipped('Tidak ada karyawan aktif ber-akun yang bukan leader.');
        }

        $hasil = $this->service()->tim((int) $bukanLeader);

        /* Bukan pengecualian, dan bukan senarai kosong tanpa keterangan. Layar yang cuma
           menerima senarai kosong tidak bisa membedakan "Anda bukan leader" dari "sedang
           memuat" atau "gagal", dan ketiganya butuh kalimat yang berbeda. */
        $this->assertTrue($hasil['tanpa_tim']);
        $this->assertSame([], $hasil['anggota']);
        $this->assertNull($hasil['cycle']);
    }

    public function test_nilai_tidak_tampak_sebelum_terbit(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_DIHITUNG, 88.5, 'A');

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);

        /* Angkanya SUDAH ADA di basis data — itu yang membuat uji ini berarti. Kalau
           barisnya belum dihitung, null-nya akan muncul dengan sendirinya dan penjaganya
           tidak menjaga apa pun. */
        $this->assertSame(
            '88.50',
            (string) DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)
                ->value('Final_Score'),
            'Prasyarat: nilainya harus benar-benar tersimpan sebelum diuji tersembunyi.'
        );

        $this->assertFalse($baris['terbit']);
        $this->assertNull($baris['nilai_akhir']);
        $this->assertNull($baris['kode_grade']);
        $this->assertNull($baris['kode_kuadran']);
    }

    public function test_nilai_tampak_sesudah_terbit(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);

        $this->assertTrue($baris['terbit']);
        $this->assertSame(88.5, $baris['nilai_akhir']);
        $this->assertSame('A', $baris['kode_grade']);
    }

    public function test_tiga_alamat_rilis_leader_terpasang(): void
    {
        /* Layanannya bisa benar sementara layarnya tetap buntu: rute yang tidak terdaftar
           menjawab 404 tanpa satu pun uji layanan berubah warna. */
        $terdaftar = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->methods()[0].' '.$r->uri())
            ->values()
            ->all();

        foreach ([
            'POST api/v1/performance-appraisal/cycles/{cycleId}/rilis-leader',
            'POST api/v1/performance-appraisal/participants/{participantId}/rilis-leader',
            'POST api/v1/performance-appraisal/participants/{participantId}/cabut-rilis-leader',
        ] as $alamat) {
            $this->assertContains($alamat, $terdaftar, "Rute {$alamat} tidak terdaftar.");
        }
    }

    public function test_rilis_ke_leader_membuka_angka_untuk_leader_tanpa_menerbitkan_ke_karyawan(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_DIHITUNG, 88.5, 'A');

        $sebelum = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);
        $this->assertNull($sebelum['nilai_akhir'], 'Prasyarat: sebelum dirilis, angkanya harus tersembunyi.');

        app(PaResultPublishService::class)->rilisKeLeaderPeserta($participantId, 1);

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);

        $this->assertSame(88.5, $baris['nilai_akhir']);
        $this->assertSame('A', $baris['kode_grade']);
        $this->assertTrue($baris['dirilis_ke_leader']);

        /* Yang membuat seluruh perubahan ini ada: leader melihat, karyawannya belum.
           `terbit` tetap false, dan status hasilnya tetap DIHITUNG. */
        $this->assertFalse($baris['terbit']);
        $this->assertSame(
            PaResultCalculationService::STATUS_DIHITUNG,
            DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)->value('Status_Hasil'),
            'Rilis ke leader tidak boleh menyentuh Status_Hasil — itu penanda milik karyawan.'
        );
    }

    /**
     * Rilis PESERTA TERPILIH (centang di tahap Rilis & Terbit). Sampai 3 Oktober 2026 jalur
     * ini selalu jatuh dengan TypeError: `array_intersect()` menerima Collection.
     */
    public function test_rilis_ke_leader_hanya_untuk_peserta_terpilih(): void
    {
        $ids = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->whereIn('Kode_Karyawan', $this->anggota)
            ->orderBy('Id_PA_Participant')
            ->limit(2)
            ->pluck('Id_PA_Participant')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('Butuh minimal dua anggota tim di siklus uji.');
        }

        [$dipilih, $tidakDipilih] = $ids;
        $this->hasil($dipilih, PaResultCalculationService::STATUS_DIHITUNG, 80.0, 'B');
        $this->hasil($tidakDipilih, PaResultCalculationService::STATUS_DIHITUNG, 75.0, 'B');

        $r = app(PaResultPublishService::class)->rilisKeLeader($this->cycleId, 1, [$dipilih]);

        $this->assertSame(1, $r['dirilis']);
        $dirilis = fn (int $id) => DB::table('N_PA_Result')->where('PA_Participant_Id', $id)
            ->where('Flag_Aktif', 'Y')->whereNotNull('Published_Leader_At')->exists();
        $this->assertTrue($dirilis($dipilih));
        $this->assertFalse($dirilis($tidakDipilih), 'Peserta yang tidak dicentang tidak boleh ikut dirilis.');
    }

    public function test_detail_anggota_ikut_terbuka_oleh_rilis_leader(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_DIHITUNG, 88.5, 'A');

        $tertutup = $this->service()->detailAnggota($this->userIdLeader, $participantId);
        $this->assertNull($tertutup['nilai_akhir']);
        $this->assertFalse($tertutup['dirilis_ke_leader']);

        app(PaResultPublishService::class)->rilisKeLeaderPeserta($participantId, 1);
        $terbuka = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $this->assertSame(88.5, $terbuka['nilai_akhir']);
        $this->assertTrue($terbuka['dirilis_ke_leader']);
        $this->assertFalse($terbuka['terbit']);
    }

    public function test_hasil_tertahan_tidak_bisa_dirilis_ke_leader(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_TERTAHAN, null, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TERTAHAN');
        app(PaResultPublishService::class)->rilisKeLeaderPeserta($participantId, 1);
    }

    public function test_cabut_rilis_leader_wajib_bersebab_dan_menutup_kembali_angkanya(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_DIHITUNG, 88.5, 'A');
        $penerbitan = app(PaResultPublishService::class);
        $penerbitan->rilisKeLeaderPeserta($participantId, 1);

        try {
            $penerbitan->cabutRilisLeader($participantId, 'ok', 1);
            $this->fail('Alasan terlalu pendek seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('5 sampai 500', $e->getMessage());
        }

        $penerbitan->cabutRilisLeader($participantId, 'Grade masih ditinjau forum', 7);

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);
        $this->assertNull($baris['nilai_akhir']);
        $this->assertFalse($baris['dirilis_ke_leader']);

        $hasil = DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)
            ->first(['Published_Leader_At', 'Unpublished_Leader_By', 'Alasan_Cabut_Leader']);
        $this->assertNull($hasil->Published_Leader_At);
        $this->assertSame(7, (int) $hasil->Unpublished_Leader_By);
        $this->assertSame('Grade masih ditinjau forum', $hasil->Alasan_Cabut_Leader);
    }

    public function test_hasil_yang_sudah_terbit_tetap_terlihat_leader_dan_tidak_bisa_dicabut(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');

        /* Siklus lama: tidak satu pun barisnya punya penanda leader, dan justru itu sebabnya
           aturannya berbentuk "atau". Kalau uji ini merah, siklus yang sudah terbit mendadak
           menghilang dari layar setiap leader. */
        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);
        $this->assertSame(88.5, $baris['nilai_akhir']);
        $this->assertFalse($baris['dirilis_ke_leader']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sudah terbit');
        app(PaResultPublishService::class)->cabutRilisLeader($participantId, 'Ingin ditutup dulu', 1);
    }

    public function test_sebab_tertahan_tetap_terlihat_meski_belum_terbit(): void
    {
        $participantId = $this->pesertaPertama();
        $this->hasil($participantId, PaResultCalculationService::STATUS_TERTAHAN, null, null, 'Paket soal belum lengkap.');

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);

        /* Sebab tertahan bukan nilai. Menyembunyikannya bersama angka akan membuat leader
           melihat anggota yang "kosong" tanpa tahu ada pekerjaan yang menunggu — dan
           leader justru orang yang paling mungkin menindaklanjutinya. */
        $this->assertSame('Paket soal belum lengkap.', $baris['alasan_tertahan']);
        $this->assertNull($baris['nilai_akhir']);
    }

    public function test_progres_dan_keterlambatan_dihitung_server(): void
    {
        $participantId = $this->pesertaPertama();

        $this->penugasan($participantId, 'SELESAI', '2020-01-01');
        $this->penugasan($participantId, 'BELUM_MULAI', '2020-01-01');
        // Tenggat HARI INI. `Due_At` bertipe DATE, dan tenggat tanggal berarti sampai
        // akhir hari itu — orang ini belum terlambat.
        $this->penugasan($participantId, 'BELUM_MULAI', date('Y-m-d'));

        $baris = $this->anggotaDari($this->service()->tim($this->userIdLeader, $this->cycleId), $participantId);

        $this->assertSame(3, $baris['penugasan_total']);
        $this->assertSame(1, $baris['penugasan_selesai']);
        $this->assertSame(
            1,
            $baris['penugasan_terlambat'],
            'Yang tenggatnya hari ini tidak boleh dihitung terlambat sejak tengah malam.'
        );
        $this->assertSame(33, $baris['progres_persen']);
    }

    public function test_siklus_default_memilih_yang_memuat_tim(): void
    {
        /* Siklus yang LEBIH BARU tetapi tidak memuat seorang pun anggota tim ini. Memilih
           "siklus terbaru" begitu saja akan menyajikan layar kosong yang terbaca seperti
           kesalahan, padahal siklus yang relevan ada. */
        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-LEAD-KOSONG-'.bin2hex(random_bytes(4)),
            'Nama_Cycle' => 'Siklus Tanpa Tim Ini',
            'Tahun' => 2099,
            'Status_Cycle' => 'DRAFT',
        ]);

        /* Siklus tim ini dijadikan yang periodenya paling akhir di antara siklus yang MEMUAT
           anggotanya (urutan kini menurut akhir periode dinilai), supaya uji ini tidak
           bergantung pada siklus lain di database yang kebetulan memuat orang yang sama. */
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Periode_Dinilai_Sampai' => '2098-12-31']);

        $hasil = $this->service()->tim($this->userIdLeader);

        $this->assertNotNull($hasil['cycle']);
        $this->assertSame($this->cycleId, $hasil['cycle']['cycle_id']);
    }

    /* ------------------------------------------------------------------ *
     * L02 — detail anggota                                                *
     * ------------------------------------------------------------------ */

    public function test_detail_anggota_orang_lain_ditolak(): void
    {
        $penyusup = $this->pesertaBukanAnggota();

        try {
            $this->service()->detailAnggota($this->userIdLeader, $penyusup);
            $this->fail('Detail peserta di luar tim seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('bukan anggota tim Anda', $e->getMessage());
        }
    }

    public function test_detail_tanpa_terbit_tidak_memuat_satu_angka_pun(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_DIHITUNG, 88.5, 'A');
        $this->kategoriHasil($resultId, $this->kategoriLainId, 80.0, 5);

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $this->assertFalse($detail['terbit']);
        $this->assertNull($detail['nilai_akhir']);
        $this->assertSame([], $detail['kategori']);
        $this->assertSame(
            [],
            $detail['kompetensi'],
            'Radar kompetensi pun tidak boleh keluar sebelum terbit — ia membocorkan '
            .'hasilnya sepotong-sepotong.'
        );
    }

    public function test_kompetensi_di_luar_radar_tetap_ada_di_rincian(): void
    {
        /* DDL-2026-096. `Flag_Radar` memilih SUMBU RADAR, bukan menyaring data.

           Godaannya jelas: menambahkan `where('c.Flag_Radar', 'Y')` ke kueri ini terasa
           seperti membereskan pekerjaan di tempat yang paling murah. Tetapi baris yang sama
           melayani dua hal — radar DAN daftar rincian per kompetensi — sehingga penyaringan
           di kueri melenyapkan kompetensi non-radar dari rincian juga. Ia tetap dinilai,
           nilainya tetap tersimpan, dan tiba-tiba tidak ada di mana pun. Yang memilih sumbu
           adalah layar. */
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');

        $duaKompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')
            ->limit(2)
            ->pluck('Id_PA_Competency')
            ->all();

        if (count($duaKompetensi) < 2) {
            $this->markTestSkipped('Butuh dua kompetensi berskor untuk membedakan sumbu radar dari rincian.');
        }

        [$diRadar, $diLuarRadar] = $duaKompetensi;

        DB::table('N_PA_Competency')->where('Id_PA_Competency', $diRadar)
            ->update(['Flag_Radar' => 'Y', 'Nilai_Ambang' => 75]);
        DB::table('N_PA_Competency')->where('Id_PA_Competency', $diLuarRadar)
            ->update(['Flag_Radar' => 'T', 'Nilai_Ambang' => null]);

        foreach ([$diRadar => 88.0, $diLuarRadar => 64.0] as $competencyId => $nilai) {
            DB::table('N_PA_Result_Competency')->insert([
                'PA_Result_Id' => $resultId,
                'PA_Competency_Id' => $competencyId,
                'PA_Rater_Category_Id' => null,
                'Nilai' => $nilai,
                'Jumlah_Penilai' => 5,
                'Calculation_Version' => 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $perId = [];
        foreach ($detail['kompetensi'] as $baris) {
            $kode = DB::table('N_PA_Competency')->where('Kode_Competency', $baris['kode_kompetensi'])
                ->value('Id_PA_Competency');
            $perId[(int) $kode] = $baris;
        }

        $this->assertArrayHasKey($diRadar, $perId);
        $this->assertArrayHasKey(
            $diLuarRadar,
            $perId,
            'Kompetensi di luar radar HILANG dari rincian. Penyaringan Flag_Radar bocor '
            .'dari layar ke kueri.'
        );

        $this->assertSame('Y', $perId[$diRadar]['flag_radar']);
        $this->assertSame('T', $perId[$diLuarRadar]['flag_radar']);
        $this->assertSame(75.0, $perId[$diRadar]['nilai_ambang']);

        /* NULL, bukan 0.0. `angka()` di service ini mengubah null menjadi 0.0, dan kalau
           ambang ikut melewatinya, "belum ditetapkan" berubah menjadi "standarnya nol" —
           jaring KKM lalu penyok ke pusat tanpa satu pun galat. */
        $this->assertNull(
            $perId[$diLuarRadar]['nilai_ambang'],
            'Ambang yang belum ditetapkan harus tetap null sampai ke layar, bukan jadi 0.'
        );
    }

    public function test_kategori_berpengisi_sedikit_disembunyikan_dan_sebabnya_disebut(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');

        $sedikit = $this->kategoriHasil($resultId, $this->kategoriLainId, 91.0, 1);
        $this->assertGreaterThan(0, $sedikit, 'Prasyarat: barisnya harus benar-benar tersimpan.');

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $baris = $this->kategoriDari($detail, $this->kategoriLainId);

        $this->assertTrue($baris['disembunyikan']);
        $this->assertNull($baris['nilai'], 'Nilainya harus benar-benar hilang, bukan sekadar ditandai.');
        $this->assertNotNull($baris['alasan_disembunyikan']);
        /* Jumlah pengisinya tetap disebut. Itu bukan kebocoran — justru itu yang membuat
           penyembunyiannya masuk akal bagi yang membacanya, dan yang memberitahu leader
           bahwa kategori ini kurang pengisi. */
        $this->assertSame(1, $baris['jumlah_penilai_mengisi']);
    }

    public function test_kategori_diri_selalu_tampak_meski_satu_pengisi(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');

        $this->kategoriHasil($resultId, $this->kategoriSelfId, 77.0, 1);

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);
        $baris = $this->kategoriDari($detail, $this->kategoriSelfId);

        /* Penilaian diri selalu berpengisi satu, dan orang itu adalah anggota yang sedang
           dilihat — bukan pihak ketiga yang perlu dilindungi. Menyembunyikannya tidak
           melindungi siapa pun dan membuang bahan percakapan yang paling berguna. */
        $this->assertFalse($baris['disembunyikan']);
        $this->assertSame(77.0, $baris['nilai']);
    }

    public function test_balasan_tidak_pernah_memuat_nilai_per_penilai(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');
        $this->kategoriHasil($resultId, $this->kategoriLainId, 80.0, 5);
        $this->penugasan($participantId, 'SELESAI', '2026-01-01');

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $this->assertNotEmpty(
            $detail['kategori'],
            'Prasyarat: harus ADA rincian kategori, kalau tidak penjaga di bawah ini '
            .'hijau hanya karena balasannya kosong.'
        );

        $terlarang = ['penilai', 'nama_penilai', 'kode_karyawan_penilai', 'assignment_id'];

        $this->assertSame(
            [],
            $this->kunciTerlarang($detail, $terlarang),
            'Balasan leader tidak boleh memuat identitas penilai dalam bentuk apa pun. '
            .'Pada tim kecil, satu baris per penilai meniadakan alasan anonimitas itu ada.'
        );
    }

    /**
     * Catatan kualitatif sampai ke leader hanya sebagai KESIMPULAN. Observasi penilai — dulu
     * dikirim lengkap dengan nama penulisnya dan hanya disembunyikan layar — tidak boleh
     * keluar dari server sama sekali.
     */
    public function test_detail_hanya_membawa_kesimpulan_tanpa_tulisan_dan_nama_penilai(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');
        $this->penugasan($participantId, 'SELESAI', '2026-01-01');
        $assignmentId = (int) DB::table('N_PA_Assignment')->where('PA_Participant_Id', $participantId)
            ->orderByDesc('Id_PA_Assignment')->value('Id_PA_Assignment');
        $ql01 = (int) DB::table('N_PA_Competency')->where('Kode_Competency', 'QL01')->value('Id_PA_Competency');
        $this->assertGreaterThan(0, $ql01, 'Uji ini butuh kompetensi QL01 di master.');

        foreach ([[null, 'Kesimpulan resmi HC'], [$assignmentId, 'RAHASIA-TULISAN-PENILAI']] as $i => [$a, $isi]) {
            DB::table('N_PA_Result_Note')->insert([
                'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $ql01, 'PA_Assignment_Id' => $a,
                'Isi' => $isi, 'Urutan_Tampil' => $i + 1, 'Flag_Aktif' => 'Y',
            ]);
        }

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);
        $json = json_encode($detail['catatan']);

        $this->assertStringContainsString('Kesimpulan resmi HC', $json);
        $this->assertStringNotContainsString('RAHASIA-TULISAN-PENILAI', $json);
        $this->assertStringNotContainsString('Nama_Penilai', $json);
    }

    public function test_batas_dinyatakan_dalam_balasan(): void
    {
        $hasil = $this->service()->tim($this->userIdLeader, $this->cycleId);

        /* Layar yang tidak tahu batasnya akan menyajikan ketiadaan sebagai ketiadaan
           data, dan menulis ambangnya sebagai angka tetap di kode layar. */
        $this->assertSame(
            PaLeaderService::MIN_PENILAI_UNTUK_TAMPIL,
            $hasil['batas']['min_penilai_untuk_tampil']
        );
        $this->assertTrue($hasil['batas']['nilai_hanya_setelah_terbit']);
        $this->assertTrue($hasil['batas']['tanpa_nilai_per_penilai']);
    }

    /* ------------------------------------------------------------------ *
     * Bentuk yang dibaca frontend                                         *
     * ------------------------------------------------------------------ */

    /**
     * Tren di tab Ringkasan: nilai siklus sebelumnya hanya untuk hasil yang boleh dibaca
     * leader (terbit atau dirilis), dan rata-ratanya hanya dari yang terlihat itu.
     */
    public function test_tren_hanya_memakai_hasil_siklus_sebelumnya_yang_terlihat(): void
    {
        $kode = 'UJI-LEAD-SBL-'.bin2hex(random_bytes(3));
        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode, 'Nama_Cycle' => 'Siklus Sebelumnya', 'Tahun' => 2025, 'Status_Cycle' => 'PUBLISHED',
            'Periode_Dinilai_Dari' => '2025-07-01', 'Periode_Dinilai_Sampai' => '2025-12-31',
        ]);
        $sebelumId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');
        $karyawan = DB::table('Karyawan')->whereIn('Kode_Karyawan', $this->anggota)->orderBy('Kode_Karyawan')
            ->limit(2)->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        if ($karyawan->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua anggota tim.');
        }

        $pid = [];
        foreach ($karyawan as $k) {
            $pid[$k->Kode_Karyawan] = (int) DB::table('N_PA_Participant')->insertGetId([
                'PA_Cycle_Id' => $sebelumId, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                'ID_Level_Saat_Cycle' => $this->levelId,
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->snapshotId, 'Status_Peserta' => 'DIIKUTKAN',
            ], 'Id_PA_Participant');
        }
        [$terlihat, $tersembunyi] = $karyawan->pluck('Kode_Karyawan')->all();
        $this->hasil($pid[$terlihat], PaResultCalculationService::STATUS_TERBIT, 70.0, 'C');
        $this->hasil($pid[$tersembunyi], PaResultCalculationService::STATUS_DIHITUNG, 95.0, 'A');

        $daftar = [
            ['cycle_id' => $this->cycleId, 'kode_cycle' => 'X', 'nama_cycle' => 'Sekarang'],
            ['cycle_id' => $sebelumId, 'kode_cycle' => $kode, 'nama_cycle' => 'Siklus Sebelumnya'],
        ];
        $m = new \ReflectionMethod(PaLeaderService::class, 'trenSebelumnya');
        $tren = $m->invoke($this->service(), $this->cycleId, $daftar, $this->anggota);

        $this->assertSame($sebelumId, $tren['cycle']['cycle_id']);
        $this->assertSame(70.0, $tren['per_anggota'][$terlihat]['nilai']);
        $this->assertArrayNotHasKey($tersembunyi, $tren['per_anggota'], 'Hasil yang belum dirilis tidak boleh bocor lewat tren.');
        $this->assertSame(70.0, $tren['rata_rata']);

        // Siklus paling lama tidak punya pembanding.
        $this->assertNull($m->invoke($this->service(), $sebelumId, $daftar, $this->anggota)['cycle']);
    }

    public function test_siklus_terhapus_tidak_muncul_di_pemilih_periode(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update(['Flag_Aktif' => 'T']);

        $hasil = $this->service()->tim($this->userIdLeader);

        $this->assertNotContains($this->cycleId, array_column($hasil['daftar_cycle'], 'cycle_id'));
        $this->assertNotSame($this->cycleId, $hasil['cycle']['cycle_id'] ?? null);
    }

    public function test_tim_sebentuk_dengan_fixture(): void
    {
        /* Satu hasil terlihat beserta nilai kompetensi berskor, supaya `kompetensi_tim`
           berisi dan bentuk butirnya ikut dibandingkan dengan fixture. */
        $resultId = $this->hasil($this->pesertaPertama(), PaResultCalculationService::STATUS_TERBIT, 85.0, 'B');
        /* Kompetensi berskor yang PUNYA tingkat di level uji, supaya `perilaku_per_level` berisi
           dan bentuk butirnya ikut dibandingkan. Tingkatnya dipasang di sini (ikut di-rollback),
           bukan diandalkan dari Matriks Kompetensi dev: kompetensi tanpa tingkat di level itu
           memang tidak punya perilaku yang dituntut, dan barisnya disaring keluar. */
        $tingkat = DB::table('N_PA_Competency_Proficiency as p')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'p.PA_Competency_Id')
            ->where('p.Flag_Aktif', 'Y')->where('c.Flag_Aktif', 'Y')->where('c.Flag_Berskor', 'Y')
            ->orderBy('c.Id_PA_Competency')->orderBy('p.Tingkat')
            ->first(['c.Id_PA_Competency', 'p.Id_PA_Competency_Proficiency']);
        $this->assertNotNull($tingkat, 'Tidak ada kompetensi berskor yang bertingkat.');
        $berskor = (int) $tingkat->Id_PA_Competency;
        DB::table('N_PA_Competency_Level_Requirement')
            ->where('ID_Level', $this->levelId)->where('PA_Competency_Id', $berskor)->update(['Flag_Aktif' => 'T']);
        DB::table('N_PA_Competency_Level_Requirement')->insert([
            'ID_Level' => $this->levelId, 'PA_Competency_Id' => $berskor,
            'PA_Competency_Proficiency_Id' => $tingkat->Id_PA_Competency_Proficiency, 'Flag_Aktif' => 'Y',
        ]);
        DB::table('N_PA_Result_Competency')->insert([
            'PA_Result_Id' => $resultId, 'PA_Competency_Id' => $berskor, 'PA_Rater_Category_Id' => null,
            'Nilai' => 82.5, 'Jumlah_Penilai' => 5, 'Calculation_Version' => 1, 'Flag_Aktif' => 'Y',
        ]);

        /* Siklus sebelumnya yang sudah terbit, supaya `tren.cycle` berisi dan bentuknya ikut
           dibandingkan. Disiapkan di sini, bukan diandalkan dari data dev (dikosongkan untuk
           UAT, 8 Okt 2026). */
        $kode = 'UJI-LEAD-TRN-'.bin2hex(random_bytes(3));
        $sebelumId = (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => $kode, 'Nama_Cycle' => 'Siklus Sebelumnya', 'Tahun' => 2001, 'Status_Cycle' => 'PUBLISHED',
            'Periode_Dinilai_Dari' => '2001-01-01', 'Periode_Dinilai_Sampai' => '2001-12-31',
        ], 'Id_PA_Cycle');
        $k = DB::table('Karyawan')->whereIn('Kode_Karyawan', $this->anggota)->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);
        $pidSebelum = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $sebelumId, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->snapshotId, 'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');
        $this->hasil($pidSebelum, PaResultCalculationService::STATUS_TERBIT, 70.0, 'C');

        $hasil = $this->service()->tim($this->userIdLeader, $this->cycleId);
        $this->assertNotNull($hasil['tren']['cycle'], 'Prasyarat: tren harus menunjuk siklus sebelumnya.');
        $this->assertNotEmpty($hasil['kompetensi_tim'], 'Prasyarat: kompetensi tim harus berisi.');

        $this->assertNotEmpty($hasil['anggota'], 'Prasyarat: tim harus berisi.');

        $this->samakanBentuk('leader-tim.json', $this->amplop($hasil));
    }

    public function test_detail_anggota_sebentuk_dengan_fixture(): void
    {
        $participantId = $this->pesertaPertama();
        $resultId = $this->hasil($participantId, PaResultCalculationService::STATUS_TERBIT, 88.5, 'A');
        /* SELF berpengisi satu tetapi SELALU tampak, dan kategori lain berpengisi satu
           sehingga DISEMBUNYIKAN. Dua baris ini sengaja sama-sama berpengisi satu: yang
           membedakan nasibnya bukan jumlah pengisinya melainkan kategorinya, dan itulah
           aturan yang hendak dikunci di sini. */
        $this->kategoriHasil($resultId, $this->kategoriSelfId, 77.0, 1);
        $this->kategoriHasil($resultId, $this->kategoriLainId, 84.0, 1);
        $this->kompetensiHasil($resultId);

        $detail = $this->service()->detailAnggota($this->userIdLeader, $participantId);

        $this->assertNotEmpty(
            $detail['kompetensi'],
            'Prasyarat: radar harus terisi, kalau tidak seluruh kunci kompetensi di '
            .'fixture tak pernah terbandingkan.'
        );

        /* Prasyarat berlapis. Fixture-nya memuat cabang "disembunyikan" DAN cabang
           "tampil"; kalau uji ini hanya menghasilkan salah satunya, salah satu cabang
           tak pernah terbandingkan dan penjaganya hijau tanpa menjaganya. */
        $this->assertCount(2, $detail['kategori']);
        $this->assertContains(
            true,
            array_column($detail['kategori'], 'disembunyikan'),
            'Fixture memuat cabang disembunyikan; uji ini harus menghasilkannya juga.'
        );
        $this->assertContains(false, array_column($detail['kategori'], 'disembunyikan'));

        $this->samakanBentuk('leader-anggota.json', $this->amplop($detail));
    }

    /**
     * Amplop yang persis sama dengan yang dibangun `ResponseHelper::success()`.
     *
     * Fixture menyimpan amplop utuh karena itulah yang benar-benar dibaca frontend;
     * membandingkan `result` saja akan membiarkan perubahan pada amplopnya lolos.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function amplop(array $result): array
    {
        return ['success' => true, 'status' => 200, 'message' => 'Berhasil', 'result' => $result];
    }

    /* ------------------------------------------------------------------ *
     * Penyiapan                                                           *
     * ------------------------------------------------------------------ */

    /**
     * Leader diambil dari `N_HRIS_Organization_Relationship` — sumber yang SAMA dengan
     * yang dibaca service, dan yang sama pula dengan yang dipakai mesin penugasan.
     *
     * Semula dari `HRIS_Karyawan_Team`. Diganti 6 September 2026 bersama servicenya: dua
     * tabel yang menjawab "siapa bawahan saya" membuat uji ini bisa hijau di atas data
     * yang tidak pernah dilihat halaman sungguhannya.
     *
     * Yang dibaca tetap DATA SUNGGUHAN dan tidak satu baris pun ditulis ke luar awalan
     * `N_PA_`. Kalau basis datanya tidak punya leader yang memenuhi syarat, uji ini
     * BERHENTI dengan keterangan — bukan lolos diam-diam.
     */
    private function pilihLeader(): void
    {
        $tipeBawahan = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', 'BAWAHAN')
            ->value('Id_Organization_Relationship_Type');

        if ($tipeBawahan === null) {
            $this->markTestSkipped('Master relasi organisasi tidak memuat jenis BAWAHAN.');
        }

        $baris = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('Karyawan as m', function ($j) {
                $j->on('m.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                    ->on('m.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
            })
            ->join('Karyawan as l', function ($j) {
                $j->on('l.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Subjek')
                    ->on('l.Kode_Karyawan', '=', 'r.Kode_Karyawan_Subjek');
            })
            ->where('r.Id_Organization_Relationship_Type', $tipeBawahan)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->whereColumn('r.Kode_Karyawan_Subjek', '<>', 'r.Kode_Karyawan_Terkait')
            ->where('m.Aktif', 'Y')
            ->where('l.Aktif', 'Y')
            ->whereNotNull('l.UserID_Web')
            ->groupBy('r.Kode_Karyawan_Subjek', 'l.UserID_Web')
            ->havingRaw('COUNT(DISTINCT r.Kode_Karyawan_Terkait) >= 2')
            // Tim TERKECIL yang memenuhi syarat: uji ini menyiapkan satu baris peserta
            // per anggota, dan tim besar memperlambatnya tanpa menambah apa pun yang
            // diuji. Urutannya disebut eksplisit — `first()` tanpa ORDER BY tidak punya
            // urutan yang dijamin di SQL Server.
            ->orderByRaw('COUNT(DISTINCT r.Kode_Karyawan_Terkait) ASC')
            ->orderBy('r.Kode_Karyawan_Subjek')
            ->selectRaw('r.Kode_Karyawan_Subjek as leader, l.UserID_Web as uid')
            ->first();

        if ($baris === null) {
            $this->markTestSkipped(
                'Basis data pengembangan tidak punya satu pun leader ber-akun dengan '
                .'minimal dua bawahan aktif di N_HRIS_Organization_Relationship.'
            );
        }

        $this->kodeLeader = (string) $baris->leader;
        $this->userIdLeader = (int) $baris->uid;

        $this->anggota = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                    ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
            })
            ->where('r.Kode_Karyawan_Subjek', $this->kodeLeader)
            ->where('r.Id_Organization_Relationship_Type', $tipeBawahan)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->whereColumn('r.Kode_Karyawan_Terkait', '<>', 'r.Kode_Karyawan_Subjek')
            ->where('k.Aktif', 'Y')
            ->distinct()
            ->orderBy('r.Kode_Karyawan_Terkait')
            ->pluck('r.Kode_Karyawan_Terkait')
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    private function siapkanSiklus(): void
    {
        $kode = 'UJI-LEAD-'.bin2hex(random_bytes(4));

        $this->levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $this->kategoriSelfId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');
        $this->kategoriLainId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', '<>', 'SELF')->orderBy('Id_PA_Rater_Category')
            ->value('Id_PA_Rater_Category');

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus Uji Leader',
            'Tahun' => 2026,
            'Status_Cycle' => 'CLOSED',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
        ]);
        $this->cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)
            ->value('Id_PA_Cycle');

        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Policy Uji Leader',
            'ID_Level' => $this->levelId,
            'Berlaku_Dari' => '2026-01-01',
        ]);
        $policyId = (int) DB::table('N_PA_Rater_Policy')->where('Kode_Policy', $kode)
            ->value('Id_PA_Rater_Policy');

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'ID_Level' => $this->levelId,
            'PA_Rater_Policy_Id' => $policyId,
            'Kode_Policy_Salinan' => $kode,
            'Nama_Policy_Salinan' => 'Policy Uji Leader',
            'Versi_Policy_Salinan' => 1,
        ]);
        $this->snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        $karyawan = DB::table('Karyawan')
            ->whereIn('Kode_Karyawan', $this->anggota)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        foreach ($karyawan as $k) {
            DB::table('N_PA_Participant')->insert([
                'PA_Cycle_Id' => $this->cycleId,
                'Kode_Perusahaan' => $k->Kode_Perusahaan,
                'Kode_Karyawan' => $k->Kode_Karyawan,
                'Nama_Karyawan_Saat_Cycle' => $k->Nama,
                'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                'ID_Level_Saat_Cycle' => $this->levelId,
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->snapshotId,
                'Status_Peserta' => 'DIIKUTKAN',
            ]);
        }
    }

    /**
     * Satu peserta pada siklus uji yang BUKAN anggota tim leader ini.
     */
    private function pesertaBukanAnggota(): int
    {
        $orang = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotIn('Kode_Karyawan', array_merge($this->anggota, [$this->kodeLeader]))
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        $this->assertNotNull($orang, 'Butuh satu karyawan aktif di luar tim untuk uji kebocoran.');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $orang->Kode_Perusahaan,
            'Kode_Karyawan' => $orang->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $orang->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $orang->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $this->levelId,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $this->snapshotId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $orang->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function pesertaPertama(): int
    {
        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->whereIn('Kode_Karyawan', $this->anggota)
            ->orderBy('Id_PA_Participant')
            ->value('Id_PA_Participant');
    }

    private function hasil(
        int $participantId,
        string $status,
        ?float $nilai,
        ?string $grade,
        ?string $alasan = null,
    ): int {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Status_Hasil' => $status,
            'Final_Score' => $nilai,
            'Kode_Grade_Final' => $grade,
            'Alasan_Tertahan' => $alasan,
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
            'Published_At' => $status === PaResultCalculationService::STATUS_TERBIT
                ? '2026-07-01 00:00:00'
                : null,
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_PA_Result');
    }

    private function kategoriHasil(int $resultId, int $kategoriId, float $nilai, int $mengisi): int
    {
        DB::table('N_PA_Result_Rater_Category')->insert([
            'PA_Result_Id' => $resultId,
            'PA_Rater_Category_Id' => $kategoriId,
            'Nilai' => $nilai,
            'Jumlah_Rater_Diharapkan' => $mengisi,
            'Jumlah_Rater_Mengisi' => $mengisi,
            'Bobot_Terpakai' => 100,
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        return (int) DB::table('N_PA_Result_Rater_Category')
            ->where('PA_Result_Id', $resultId)
            ->where('PA_Rater_Category_Id', $kategoriId)
            ->value('Id_PA_Result_Rater_Category');
    }

    /**
     * Satu penugasan dengan penilai yang BERBEDA setiap kali dipanggil.
     *
     * `UX_PA_Assignment_Peserta_Penilai` melarang pasangan (peserta, penilai) berulang,
     * jadi tiga penugasan untuk satu orang wajib datang dari tiga penilai. Memakai
     * penilai yang sama bukan sekadar melanggar indeks — ia menggambarkan keadaan yang
     * memang tidak boleh ada.
     */
    /**
     * Satu baris radar AGREGAT — `PA_Rater_Category_Id` sengaja NULL.
     *
     * Baris per kategori memang ada di tabel yang sama, dan justru itulah yang tidak
     * boleh keluar ke layar leader: "Integritas menurut Bawahan = 60" pada kategori
     * berpengisi satu adalah nilai satu orang yang diberi nama lain.
     */
    private function kompetensiHasil(int $resultId): void
    {
        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Competency')->value('Id_PA_Competency');

        $this->assertNotNull($kompetensi, 'Master kompetensi kosong.');

        DB::table('N_PA_Result_Competency')->insert([
            'PA_Result_Id' => $resultId,
            'PA_Competency_Id' => $kompetensi,
            'PA_Rater_Category_Id' => null,
            'Nilai' => 88.0,
            'Jumlah_Penilai' => 5,
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function penugasan(int $participantId, string $status, string $dueAt): void
    {
        $this->penilaiKe++;

        $penilai = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->offset($this->penilaiKe - 1)
            ->limit(1)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        $this->assertNotNull($penilai, 'Karyawan aktif habis untuk dijadikan penilai uji.');

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriSelfId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $status,
            'Due_At' => $dueAt,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Alat bantu                                                          *
     * ------------------------------------------------------------------ */

    private function service(): PaLeaderService
    {
        return $this->app->make(PaLeaderService::class);
    }

    /** @param array<string, mixed> $hasil */
    private function anggotaDari(array $hasil, int $participantId): array
    {
        foreach ($hasil['anggota'] as $a) {
            if ($a['participant_id'] === $participantId) {
                return $a;
            }
        }

        $this->fail("Peserta {$participantId} tidak ada di daftar tim.");
    }

    /** @param array<string, mixed> $detail */
    private function kategoriDari(array $detail, int $kategoriId): array
    {
        $kode = (string) DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $kategoriId)
            ->value('Kode_Kategori');

        foreach ($detail['kategori'] as $k) {
            if ($k['kode_kategori'] === $kode) {
                return $k;
            }
        }

        $this->fail("Kategori {$kode} tidak ada di balasan detail.");
    }

    /**
     * Menelusuri seluruh balasan dan mengembalikan kunci terlarang yang ditemukan.
     *
     * Menelusuri REKURSIF, bukan memeriksa tingkat pertama saja: kebocoran seperti ini
     * hampir selalu muncul terselip di dalam senarai anak, bukan di akar.
     *
     * @param  list<string>  $terlarang
     * @return list<string>
     */
    private function kunciTerlarang(mixed $simpul, array $terlarang, string $jalur = ''): array
    {
        if (! is_array($simpul)) {
            return [];
        }

        $temuan = [];

        foreach ($simpul as $kunci => $nilai) {
            $anak = $jalur === '' ? (string) $kunci : $jalur.'.'.$kunci;

            if (is_string($kunci) && in_array($kunci, $terlarang, true)) {
                $temuan[] = $anak;
            }

            $temuan = array_merge($temuan, $this->kunciTerlarang($nilai, $terlarang, $anak));
        }

        return $temuan;
    }
}
