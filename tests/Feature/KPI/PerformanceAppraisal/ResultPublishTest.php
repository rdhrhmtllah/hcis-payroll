<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\HitungHasilPesertaJob;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaKategoriGugurException;
use App\Services\KPI\PerformanceAppraisal\PaParticipantEditService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Penerbitan hasil — langkah yang membuat modul ini ada gunanya bagi karyawan.
 *
 * Sebelum fitur ini ada, hasil bisa dihitung tetapi `Published_At` tidak pernah ditulis
 * siapa pun. Gerbang `TERBITKAN` berdiri tanpa satu baris jalur kode.
 *
 * Uji ini tidak memakai penyiapan siklus penuh: barisnya dibentuk langsung supaya yang
 * diuji adalah ATURAN PENERBITAN, bukan lagi-lagi penyiapan peserta yang sudah punya
 * ujinya sendiri. Setiap uji penolakan menuntut penolakannya benar-benar terjadi, dan
 * memeriksa bahwa tidak ada jejak yang tertinggal.
 */
class ResultPublishTest extends TestCase
{
    use DatabaseTransactions;

    private PaResultPublishService $terbit;

    private PaCycleGate $gate;

    private int $cycleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gate = new PaCycleGate;

        /* Gate-nya DIIKAT ke container, bukan sekadar dipegang uji ini. Dua alasan.
           Pertama, memo `PaCycleGate` bersifat per-objek dan gate-nya tidak terdaftar
           singleton — tanpa ikatan ini, service akan memakai gate LAIN, sehingga
           `$this->gate->lupakanMemo()` tidak berpengaruh apa pun pada yang diuji.
           Kedua, membangun service lewat container membuat uji ini tidak lagi patah
           setiap kali konstruktornya bertambah dependensi; ia patah persis begitu
           `PaNotificationService` ditambahkan, dan `ArgumentCountError`-nya tidak
           mengatakan apa-apa tentang aturan penerbitan yang sebenarnya diuji. */
        $this->app->instance(PaCycleGate::class, $this->gate);
        $this->terbit = $this->app->make(PaResultPublishService::class);
        $this->cycleId = $this->siklus('CLOSED');
    }

    public function test_menerbitkan_hasil_yang_dihitung_dan_mengisi_jejaknya(): void
    {
        $a = $this->peserta('DIHITUNG');
        $b = $this->peserta('DIHITUNG');

        $h = $this->terbit->terbitkan($this->cycleId, 99);

        $this->assertSame(2, $h['diterbitkan']);

        foreach ([$a, $b] as $participantId) {
            $r = DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)
                ->first(['Status_Hasil', 'Published_At', 'Published_By']);

            $this->assertSame('TERBIT', $r->Status_Hasil);
            // Tanpa jejak ini, "sejak kapan karyawan bisa melihatnya" hanya bisa ditebak.
            $this->assertNotNull($r->Published_At);
            $this->assertSame(99, (int) $r->Published_By);
        }
    }

    /*
     | DUA ADMIN / KLIK GANDA (14 Okt 2026): proses lain menerbitkan hasil yang sama tepat sesudah
     | panggilan ini memilih calonnya. Dulu UPDATE-nya tanpa syarat status: Published_At ditimpa
     | dan setiap orang menerima pemberitahuan "hasil terbit" dua kali.
     */
    public function test_terbit_bersamaan_tidak_menimpa_dan_tidak_mengabari_dua_kali(): void
    {
        $a = $this->peserta('DIHITUNG');
        $b = $this->peserta('DIHITUNG');
        $jamLain = '2026-01-01 08:00:00';

        $sekali = false;
        DB::listen(function ($q) use (&$sekali, $a) {
            if ($sekali || ! str_contains($q->sql, '[r].[Jumlah_KPI_Belum_Disetujui]')) {
                return;
            }
            $sekali = true;
            DB::table('N_PA_Result')->where('PA_Participant_Id', $a)
                ->update(['Status_Hasil' => 'TERBIT', 'Published_At' => '2026-01-01 08:00:00', 'Published_By' => 7]);
        });

        $notif = fn () => DB::table('N_HRIS_Notification')->where('Type', \App\Services\KPI\PerformanceAppraisal\PaNotificationService::TYPE_HASIL_TERBIT)->count();
        $sebelum = $notif();

        $h = $this->terbit->terbitkan($this->cycleId, 99);

        $this->assertSame(1, $h['diterbitkan'], 'Hanya B yang diterbitkan panggilan ini.');
        $this->assertSame($jamLain, substr((string) DB::table('N_PA_Result')->where('PA_Participant_Id', $a)->value('Published_At'), 0, 19));
        $this->assertSame(7, (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $a)->value('Published_By'));
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $b)->value('Status_Hasil'));
        $this->assertLessThanOrEqual(1, $notif() - $sebelum, 'A tidak boleh dikabari oleh panggilan ini.');
    }

    public function test_yang_tertahan_tidak_ikut_terbit(): void
    {
        $this->peserta('DIHITUNG');
        $tertahan = $this->peserta('TERTAHAN', 'Tidak ada KPI disetujui.');

        $h = $this->terbit->terbitkan($this->cycleId, 99);

        $this->assertSame(1, $h['diterbitkan']);
        $this->assertSame(1, $h['tertahan']);

        // TERTAHAN berarti sistem MENOLAK memberi angka. Menerbitkannya berarti
        // menunjukkan kepada karyawan sesuatu yang sistemnya sendiri bilang tidak sah.
        $this->assertSame(
            'TERTAHAN',
            DB::table('N_PA_Result')->where('PA_Participant_Id', $tertahan)->value('Status_Hasil')
        );
    }

    public function test_menerbitkan_saat_tak_ada_yang_dihitung_ditolak(): void
    {
        $this->peserta('TERTAHAN', 'uji');

        try {
            $this->terbit->terbitkan($this->cycleId, 99);
            $this->fail('Penerbitan tanpa satu pun hasil DIHITUNG seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            // Pesannya wajib menyebut ANGKA-nya; "tidak ada yang bisa diterbitkan" tanpa
            // rincian membuat orang menebak apa yang salah.
            $this->assertStringContainsString('DIHITUNG', $e->getMessage());
            $this->assertStringContainsString('Tertahan: 1', $e->getMessage());
        }
    }

    public function test_menerbitkan_peserta_tertahan_ditolak_dan_menyebut_sebabnya(): void
    {
        $tertahan = $this->peserta('TERTAHAN', 'Total bobot KPI berjumlah 50, bukan 100.');

        try {
            $this->terbit->terbitkanPeserta($tertahan, 99);
            $this->fail('Peserta TERTAHAN seharusnya tidak bisa diterbitkan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('TERTAHAN', $e->getMessage());
            // Sebab tertahannya ikut disebut, supaya yang membaca tahu apa yang harus
            // diperbaiki tanpa membuka layar lain.
            $this->assertStringContainsString('bobot KPI', $e->getMessage());
        }
    }

    public function test_menerbitkan_ulang_peserta_yang_sudah_terbit_ditolak(): void
    {
        $p = $this->peserta('DIHITUNG');
        $this->terbit->terbitkanPeserta($p, 99);

        $this->expectException(RuntimeException::class);
        $this->terbit->terbitkanPeserta($p, 99);
    }

    public function test_menutup_penerbitan_ditolak_selama_masih_ada_yang_tersisa(): void
    {
        $this->peserta('DIHITUNG');
        $this->peserta('TERTAHAN', 'uji');

        $this->terbit->terbitkan($this->cycleId, 99);

        try {
            $this->terbit->tutupPenerbitan($this->cycleId, 99);
            $this->fail('Penutupan seharusnya ditolak selama masih ada peserta tertahan.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tertahan', $e->getMessage());
        }

        // Dan statusnya TIDAK boleh bergeser diam-diam — PUBLISHED adalah pintu satu arah.
        $this->assertSame('CLOSED', $this->statusSiklus());
    }

    public function test_menutup_penerbitan_berhasil_ketika_semua_sudah_terbit(): void
    {
        $this->peserta('DIHITUNG');
        $this->peserta('DIHITUNG');

        $this->terbit->terbitkan($this->cycleId, 99);
        $this->gate->lupakanMemo();

        $h = $this->terbit->tutupPenerbitan($this->cycleId, 99);

        $this->assertSame('PUBLISHED', $h['status']);
        $this->assertSame('PUBLISHED', $this->statusSiklus());
        $this->assertNotNull(
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->value('Published_At')
        );
    }

    public function test_menutup_penerbitan_tanpa_satu_pun_hasil_terbit_ditolak(): void
    {
        $this->peserta('DIHITUNG');

        // Siklus tanpa hasil terbit bukan siklus yang selesai; menutupnya menghasilkan
        // siklus yang tampak beres tetapi tak pernah menghasilkan apa pun.
        $this->expectException(RuntimeException::class);
        $this->terbit->tutupPenerbitan($this->cycleId, 99);
    }

    public function test_penerbitan_boleh_saat_masih_terbuka_tetapi_tidak_sebelum_ada_yang_bisa_dihitung(): void
    {
        /* DIUBAH DENGAN SENGAJA, dan uji ini yang memegang alasannya.

           Semula penerbitan menuntut siklus DITUTUP: menerbitkan saat pengisian berjalan
           dianggap membuat karyawan melihat angka yang masih bisa berubah. Kekhawatiran
           itu tidak terbukti pada modul ini — nilai seseorang tidak bergantung pada orang
           lain sama sekali. Grade dan kuadran lahir dari pita ABSOLUT, bukan dari
           peringkat, jadi tidak ada satu angka pun yang bergeser ketika peserta berikutnya
           menyusul.

           Yang menjaga agar tak ada angka setengah jadi yang terbit BUKAN status siklusnya
           melainkan status hasilnya: hanya yang DIHITUNG yang ikut, dan perhitungan
           sendiri menolak peserta yang penilainya belum lengkap selama jendela terbuka.
           Dua penjaga itu punya ujinya masing-masing di berkas ini.

           Biaya aturan lama nyata: seorang peserta yang seluruh penilainya sudah selesai
           harus menunggu orang terakhir di seluruh siklus — kadang berminggu-minggu —
           untuk sesuatu yang angkanya sudah final sejak lama. */
        $siklusTerbuka = $this->siklus('OPEN');
        $this->peserta('DIHITUNG', null, $siklusTerbuka);
        $this->gate->lupakanMemo();

        $hasil = $this->terbit->terbitkan($siklusTerbuka, 99);

        $this->assertSame(1, (int) $hasil['diterbitkan'],
            'Peserta yang sudah dihitung harus bisa terbit tanpa menunggu siklus ditutup.');

        /* ARAH SEBALIKNYA tetap dijaga. Sebelum pengisian pernah dibuka memang belum ada
           apa pun untuk diterbitkan, dan tanpa penegasan ini ujinya akan tetap hijau
           seandainya gerbangnya mengizinkan penerbitan di SEGALA status. */
        $siklusTerkunci = $this->siklus('LOCKED');
        $this->peserta('DIHITUNG', null, $siklusTerkunci);
        $this->gate->lupakanMemo();

        $this->expectException(RuntimeException::class);
        $this->terbit->terbitkan($siklusTerkunci, 99);
    }

    public function test_ringkasan_menghitung_hanya_peserta_yang_diikutkan(): void
    {
        $this->peserta('DIHITUNG');
        $this->peserta('TERTAHAN', 'uji');
        $dikecualikan = $this->peserta('BELUM_DIHITUNG');

        DB::table('N_PA_Participant')->where('Id_PA_Participant', $dikecualikan)
            ->update(['Status_Peserta' => 'DIKECUALIKAN', 'Alasan_Dikecualikan' => 'uji']);

        $r = $this->terbit->ringkasan($this->cycleId);

        // Yang dikecualikan tidak pernah dinilai, jadi ia bukan bagian dari cacah
        // penerbitan — memasukkannya membuat siklus tampak tak pernah selesai.
        $this->assertSame(2, $r['total']);
        $this->assertSame(1, $r['dihitung']);
        $this->assertSame(1, $r['tertahan']);
        $this->assertSame(0, $r['belum_dihitung']);
    }

    /**
     * Penerbitan MASSAL memegang penahanan "KPI belum lengkap" yang sama dengan jalur per orang
     * (koreksi 8 Okt 2026). Dulu hanya `terbitkanPeserta()` yang menahannya, sehingga tombol
     * massal mengunci selamanya angka yang ditolak jalur per orang.
     */
    public function test_penerbitan_massal_melewati_hasil_ber_kpi_belum_lengkap_dan_menyebutnya(): void
    {
        $lengkap = $this->peserta('DIHITUNG');
        $belum = $this->peserta('DIHITUNG');
        DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->update(['Jumlah_KPI_Belum_Disetujui' => 2]);

        $h = $this->terbit->terbitkan($this->cycleId, 99);

        $this->assertSame(1, $h['diterbitkan']);
        $this->assertSame(1, $h['dilewati_kpi_belum_lengkap']);
        $this->assertSame($belum, $h['peserta_kpi_belum_lengkap'][0]['participant_id']);
        $this->assertSame(2, $h['peserta_kpi_belum_lengkap'][0]['kpi_belum_disetujui']);
        $this->assertNotSame('', $h['peserta_kpi_belum_lengkap'][0]['nama']);
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $lengkap)->value('Status_Hasil'));
        $this->assertSame('DIHITUNG', DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->value('Status_Hasil'));
        $this->assertNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->value('Published_At'));
    }

    public function test_penerbitan_massal_menyertakan_kpi_belum_lengkap_bila_diminta_dengan_sadar(): void
    {
        $belum = $this->peserta('DIHITUNG');
        DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->update(['Jumlah_KPI_Belum_Disetujui' => 1]);

        $h = $this->terbit->terbitkan($this->cycleId, 99, null, true);

        $this->assertSame(1, $h['diterbitkan']);
        $this->assertSame(0, $h['dilewati_kpi_belum_lengkap']);
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->value('Status_Hasil'));
    }

    public function test_penerbitan_massal_yang_seluruhnya_ber_kpi_belum_lengkap_ditolak_dengan_nama(): void
    {
        $belum = $this->peserta('DIHITUNG');
        DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->update(['Jumlah_KPI_Belum_Disetujui' => 3]);
        $nama = (string) DB::table('N_PA_Participant')->where('Id_PA_Participant', $belum)->value('Nama_Karyawan_Saat_Cycle');

        try {
            $this->terbit->terbitkan($this->cycleId, 99, [$belum]);
            $this->fail('Penerbitan yang seluruhnya ber-KPI belum lengkap seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('belum disetujui', $e->getMessage());
            $this->assertStringContainsString($nama, $e->getMessage());
        }

        $this->assertSame('DIHITUNG', DB::table('N_PA_Result')->where('PA_Participant_Id', $belum)->value('Status_Hasil'));
    }

    /**
     * Hasil yang sudah DIRILIS KE LEADER tidak dihitung ulang — sama dengan yang TERBIT
     * (koreksi 8 Okt 2026). Jalur otomatis (job dari callback HC Learn) mencatat penolakannya,
     * bukan jatuh.
     */
    public function test_hasil_yang_dirilis_ke_leader_menolak_dihitung_ulang_dan_job_tidak_jatuh(): void
    {
        $pid = $this->peserta('DIHITUNG');
        $this->terbit->rilisKeLeaderPeserta($pid, 99);
        $sebelum = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Result', 'Calculation_Version', 'Final_Score']);

        try {
            $this->app->make(PaResultCalculationService::class)->hitung($pid, 99);
            $this->fail('Hasil yang sudah dirilis ke leader seharusnya menolak dihitung ulang.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('dirilis ke leader', $e->getMessage());
            $this->assertStringContainsString('Cabut rilis leader', $e->getMessage());
        }

        // Jalur otomatis: penolakan aturan dicatat, tidak dilempar ke callback.
        (new HitungHasilPesertaJob($pid, 99))->handle($this->app->make(PaResultCalculationService::class));

        $sesudah = DB::table('N_PA_Result')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Result', 'Calculation_Version', 'Final_Score']);
        $this->assertEquals($sebelum, $sesudah, 'Hasil yang dibaca leader tidak boleh berubah sedikit pun.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * Kategori penilai GUGUR (pengisi di bawah minimal) menahan penerbitan seperti KPI belum
     * lengkap (UAT 13 Okt 2026): massal melewati dan menyebutnya, per orang menolak dengan
     * kode stabil, dan keduanya bisa ditimpa dengan sadar.
     */
    public function test_penerbitan_massal_melewati_hasil_berkategori_gugur_dan_menyebutnya(): void
    {
        $utuh = $this->peserta('DIHITUNG');
        $gugur = $this->peserta('DIHITUNG');
        $this->gugurkanKategori($gugur);

        $h = $this->terbit->terbitkan($this->cycleId, 99);

        $this->assertSame(1, $h['diterbitkan']);
        $this->assertSame(1, $h['dilewati_kategori_gugur']);
        $this->assertSame($gugur, $h['peserta_kategori_gugur'][0]['participant_id']);
        $this->assertStringContainsString('1/2 mengisi', $h['peserta_kategori_gugur'][0]['kategori'][0]);
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $utuh)->value('Status_Hasil'));
        $this->assertSame('DIHITUNG', DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Status_Hasil'));

        $h2 = $this->terbit->terbitkan($this->cycleId, 99, null, false, true);
        $this->assertSame(1, $h2['diterbitkan']);
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Status_Hasil'));
    }

    public function test_terbitkan_peserta_berkategori_gugur_ditolak_kecuali_disadari(): void
    {
        $gugur = $this->peserta('DIHITUNG');
        $this->gugurkanKategori($gugur);

        try {
            $this->terbit->terbitkanPeserta($gugur, 99);
            $this->fail('Hasil berkategori gugur seharusnya ditahan.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (PaKategoriGugurException $e) {
            $this->assertStringContainsString('tidak dihitung', $e->getMessage());
        }
        $this->assertSame('DIHITUNG', DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Status_Hasil'));

        $this->terbit->terbitkanPeserta($gugur, 99, false, true);
        $this->assertSame('TERBIT', DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Status_Hasil'));
    }

    /* 14 Okt 2026: rilis ke leader juga membekukan hasil, jadi penahanannya sama dengan terbit. */
    public function test_rilis_leader_massal_melewati_kategori_gugur_dan_kpi_belum_lengkap(): void
    {
        $utuh = $this->peserta('DIHITUNG');
        $gugur = $this->peserta('DIHITUNG');
        $kpi = $this->peserta('DIHITUNG');
        $this->gugurkanKategori($gugur);
        DB::table('N_PA_Result')->where('PA_Participant_Id', $kpi)->update(['Jumlah_KPI_Belum_Disetujui' => 1]);

        $h = $this->terbit->rilisKeLeader($this->cycleId, 99);

        $this->assertSame(1, $h['dirilis']);
        $this->assertSame($gugur, $h['peserta_kategori_gugur'][0]['participant_id']);
        $this->assertSame($kpi, $h['peserta_kpi_belum_lengkap'][0]['participant_id']);
        $this->assertNotNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $utuh)->value('Published_Leader_At'));
        $this->assertNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Published_Leader_At'));

        $this->assertSame(2, $this->terbit->rilisKeLeader($this->cycleId, 99, null, true, true)['dirilis']);
    }

    public function test_rilis_leader_per_orang_berkategori_gugur_ditolak_kecuali_disadari(): void
    {
        $gugur = $this->peserta('DIHITUNG');
        $this->gugurkanKategori($gugur);

        try {
            $this->terbit->rilisKeLeaderPeserta($gugur, 99);
            $this->fail('Rilis hasil berkategori gugur seharusnya ditahan.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (PaKategoriGugurException $e) {
            $this->assertStringContainsString('tidak dihitung', $e->getMessage());
        }

        $this->terbit->rilisKeLeaderPeserta($gugur, 99, false, true);
        $this->assertNotNull(DB::table('N_PA_Result')->where('PA_Participant_Id', $gugur)->value('Published_Leader_At'));
    }

    /* 14 Okt 2026: sesudah CLOSED, hanya yang tak punya angka boleh dikecualikan — supaya
       peserta tertahan permanen tidak membuat siklus mustahil ditutup. */
    public function test_sesudah_ditutup_hanya_hasil_tanpa_angka_yang_boleh_dikecualikan(): void
    {
        $tertahan = $this->peserta('TERTAHAN', 'Karyawan tidak punya akun KPI (uji).');
        $dihitung = $this->peserta('DIHITUNG');
        $edit = $this->app->make(PaParticipantEditService::class);

        $edit->ubahStatus($tertahan, 'DIKECUALIKAN', 'Tanpa akun KPI (uji).', 99);
        $this->assertSame('DIKECUALIKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $tertahan)->value('Status_Peserta'));

        try {
            $edit->ubahStatus($dihitung, 'DIKECUALIKAN', 'Uji.', 99);
            $this->fail('Hasil yang sudah dihitung tidak boleh dikecualikan sesudah ditutup.');
        } catch (AssertionFailedError $gagal) {
            throw $gagal;
        } catch (RuntimeException $e) {
            $this->assertSame('DIIKUTKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $dihitung)->value('Status_Peserta'));
        }
    }

    private function gugurkanKategori(int $participantId): void
    {
        DB::table('N_PA_Result_Rater_Category')->insert([
            'PA_Result_Id' => (int) DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)->value('Id_PA_Result'),
            'PA_Rater_Category_Id' => (int) DB::table('N_PA_Rater_Category')->orderBy('Id_PA_Rater_Category')->value('Id_PA_Rater_Category'),
            'Nilai' => null,
            'Jumlah_Rater_Diharapkan' => 2,
            'Jumlah_Rater_Mengisi' => 1,
            'Bobot_Terpakai' => null,
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function statusSiklus(): string
    {
        return (string) DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $this->cycleId)->value('Status_Cycle');
    }

    private function siklus(string $status): int
    {
        return (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => 'UJI-TERBIT-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => 'Siklus Uji Penerbitan',
            'Tahun' => 2026,
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Status_Cycle' => $status,
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle');
    }

    /**
     * Satu peserta beserta baris hasilnya, langsung pada status yang diinginkan.
     */
    private function peserta(string $statusHasil, ?string $alasan = null, ?int $cycleId = null): int
    {
        $k = DB::table('Karyawan')->where('Aktif', 'Y')
            ->inRandomOrder()->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        $participantId = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $cycleId ?? $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => 1,
            'ID_Level_Saat_Cycle' => 2,
            'Status_Peserta' => 'DIIKUTKAN',
            'Snapshot_At' => now(),
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Participant');

        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $participantId,
            'Status_Hasil' => $statusHasil,
            'Alasan_Tertahan' => $alasan,
            'Kode_Grade_Sistem' => $statusHasil === 'DIHITUNG' ? 'B' : null,
            'Kode_Grade_Final' => $statusHasil === 'DIHITUNG' ? 'B' : null,
            'Final_Score' => $statusHasil === 'DIHITUNG' ? 75.5 : null,
            'Flag_Dikalibrasi' => 'T',
            'Calculation_Version' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        return $participantId;
    }
}
