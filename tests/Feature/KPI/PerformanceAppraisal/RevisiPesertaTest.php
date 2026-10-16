<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Jobs\KPI\PerformanceAppraisal\PenjadwalanHcLearnPaJob;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantEditService;
use App\Services\KPI\PerformanceAppraisal\PaRevisiPesertaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Revisi peserta SESUDAH siklus dikunci — terapkan Level PA terbaru, ikutkan kembali, susulan.
 *
 * ── Apa yang dijaga ───────────────────────────────────────────────────────────
 *
 *  1. Hanya untuk LOCKED/OPEN; sebelum dikunci jalurnya Langkah 4/5.
 *  2. Penilai alami TERBENTUK LAGI sesudah pembatalan oleh sistem — pembatalan karena
 *     pengecualian/revisi tidak dihitung "pernah dicabut admin" (13 Okt 2026: tanpa ini
 *     peserta yang diikutkan kembali berakhir tanpa penilai).
 *  3. Koreksi level ditolak bila sudah ada penilai yang mengisi.
 *  4. Yang terbentuk dijadwalkan ke HC Learn sebagai tugas latar.
 */
class RevisiPesertaTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private PaRevisiPesertaService $revisi;

    /** Kode yang dijawab HC Learn BELUM_TERDAFTAR / SUDAH_RESIGN; selain itu siap. */
    private array $hclBelum = [];

    private array $hclResign = [];

    /** Muatan yang dikirim ke add-new. */
    private array $hclDidaftarkan = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::whereNotNull('User_Key')->firstOrFail());

        DB::table('N_PA_Level_Exclusion')->where('Flag_Aktif', 'Y')->whereIn('ID_Level', [2])->update(['Flag_Aktif' => 'T']);
        PaLevelDikecualikan::lupakan();

        $kode = 'UJI-RVS-'.substr(bin2hex(random_bytes(4)), 0, 8);
        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => $kode,
            'Nama_Cycle' => 'Siklus uji revisi peserta',
            'Tahun' => 2026,
            'Status_Cycle' => 'DRAFT',
            'Periode_Dinilai_Dari' => '2026-01-01',
            'Periode_Dinilai_Sampai' => '2026-06-30',
            'Pengisian_Dari' => now()->subDay()->format('Y-m-d H:i:s'),
            'Pengisian_Sampai' => now()->addDays(20)->format('Y-m-d H:i:s'),
        ]);
        $this->cycleId = (int) DB::table('N_PA_Cycle')->where('Kode_Cycle', $kode)->value('Id_PA_Cycle');

        $this->gate()->lupakanMemo();
        app(PaCyclePreparationService::class)->siapkan($this->cycleId, 4);
        $this->gate()->lupakanMemo();

        /* Paket soal per level — penjadwalan menuntutnya; revisi menolak level tanpa paket. */
        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->distinct()->pluck('ID_Level_Saat_Cycle') as $lvl) {
            if ($lvl !== null) {
                DB::table('N_PA_Cycle_Question_Package')->insert([
                    'PA_Cycle_Id' => $this->cycleId, 'ID_Level' => (int) $lvl, 'PA_Rater_Category_Id' => null,
                    'Id_Master_Ujian' => '100160', 'Nama_Ujian_Sumber' => 'Ujian uji', 'Sumber_Kontrak' => 'HCLEARN', 'Flag_Aktif' => 'Y',
                ]);
            }
        }

        Queue::fake();
        /* HC Learn dipalsukan untuk SEMUA uji: revisi memeriksa penilai baru ke sana sebelum
           menjadwalkan, dan uji tidak boleh bergantung pada server luar. */
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/pa/add-new/karyawan')) {
                foreach ((array) ($request->data()['data'] ?? []) as $k) {
                    $this->hclDidaftarkan[] = (string) ($k['kode_karyawan'] ?? '');
                }
                $this->hclBelum = array_values(array_diff($this->hclBelum, $this->hclDidaftarkan));

                return Http::response(['success' => true, 'status' => 200, 'result' => [
                    'ringkasan' => ['diminta' => count($this->hclDidaftarkan), 'tersimpan' => count($this->hclDidaftarkan), 'sudah_ada' => 0, 'gagal' => 0],
                    'tersimpan' => [], 'sudah_ada' => [], 'gagal' => []]]);
            }
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $data = array_map(fn ($k) => in_array($k, $this->hclBelum, true)
                ? ['dicari' => $k, 'ada' => false, 'siap_dipakai' => false, 'alasan' => 'BELUM_TERDAFTAR', 'keterangan' => 'Karyawan belum ada di HCLearn.']
                : (in_array($k, $this->hclResign, true)
                    ? ['dicari' => $k, 'ada' => true, 'siap_dipakai' => false, 'alasan' => 'SUDAH_RESIGN', 'keterangan' => 'Karyawan sudah resign.']
                    : ['dicari' => $k, 'ada' => true, 'siap_dipakai' => true, 'alasan' => 'SIAP', 'keterangan' => 'Siap.']),
                array_values(array_filter(explode(',', (string) ($q['kode_karyawan'] ?? '')))));

            return Http::response(['success' => true, 'status' => 200, 'result' => ['data' => $data]]);
        });
        $this->revisi = app(PaRevisiPesertaService::class);
    }

    public function test_sebelum_dikunci_tidak_ada_tindakan_yang_boleh(): void
    {
        $p = $this->pesertaBerpenilai();
        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);

        foreach ($pr['tindakan'] as $t) {
            $this->assertFalse($t['boleh']);
            $this->assertStringContainsString('sudah dikunci', $t['sebab']);
        }
    }

    public function test_ikutkan_kembali_membentuk_lagi_penilai_alaminya_dan_menjadwalkan(): void
    {
        $p = $this->pesertaBerpenilai();
        $awal = $this->penugasanAktif($p->Id_PA_Participant);
        $this->assertGreaterThan(0, $awal, 'Peserta uji tanpa penilai; uji ini tidak menguji apa pun.');

        app(PaParticipantEditService::class)->ubahStatus((int) $p->Id_PA_Participant, 'DIKECUALIKAN', 'Cuti panjang (uji).', 4);
        $this->assertSame(0, $this->penugasanAktif($p->Id_PA_Participant));
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertTrue($pr['tindakan']['IKUTKAN_KEMBALI']['boleh']);
        $this->assertFalse($pr['tindakan']['TAMBAH_SUSULAN']['boleh']);

        $hasil = $this->revisi->jalankan('IKUTKAN_KEMBALI', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Batal cuti (uji).', 4);

        $this->assertSame('DIIKUTKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->value('Status_Peserta'));
        /* Pembatalan karena pengecualian TIDAK dihitung "pernah dicabut" — penilai yang sama terbentuk lagi. */
        $this->assertSame($awal, $hasil['dibentuk']);
        $this->assertSame($awal, $this->penugasanAktif($p->Id_PA_Participant));
        $this->assertNotNull($hasil['penjadwalan']);
        Queue::assertPushed(PenjadwalanHcLearnPaJob::class);
    }

    public function test_terapkan_level_terbaru_membatalkan_dan_membentuk_ulang(): void
    {
        $p = $this->pesertaBerpenilai();
        $awal = $this->penugasanAktif($p->Id_PA_Participant);
        $levelAsli = (int) $p->ID_Level_Saat_Cycle;
        $levelLain = (int) DB::table('HRIS_Level')->where('ID_Level', '<>', $levelAsli)->value('ID_Level');

        /* Baris peserta "tertinggal" di level lain — terapkan level terbaru mengembalikannya ke
           Level PA menurut HRIS + koreksi. */
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->update(['ID_Level_Saat_Cycle' => $levelLain]);
        $this->kunci();

        $hasil = $this->revisi->jalankan('TERAPKAN_LEVEL', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Koreksi level HR (uji).', 4);

        $this->assertSame($levelAsli, (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->value('ID_Level_Saat_Cycle'));
        $this->assertSame($awal, $hasil['dibatalkan']);
        $this->assertSame($awal, $hasil['dibentuk']);
        $this->assertTrue(DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)
            ->where('Cancel_Reason', 'like', 'Revisi peserta:%')->exists(), 'Alasan revisi tidak tercatat di Cancel_Reason.');
    }

    public function test_terapkan_level_ditolak_bila_sudah_ada_yang_mengisi(): void
    {
        $p = $this->pesertaBerpenilai();
        DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')
            ->limit(1)->update(['Status_Penugasan' => 'SELESAI']);
        $levelLain = (int) DB::table('HRIS_Level')->where('ID_Level', '<>', (int) $p->ID_Level_Saat_Cycle)->value('ID_Level');
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->update(['ID_Level_Saat_Cycle' => $levelLain]);
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertFalse($pr['tindakan']['TERAPKAN_LEVEL']['boleh']);
        $this->assertStringContainsString('sudah mengisi', $pr['tindakan']['TERAPKAN_LEVEL']['sebab']);

        $this->expectException(\RuntimeException::class);
        $this->revisi->jalankan('TERAPKAN_LEVEL', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Koreksi level (uji).', 4);
    }

    /* Kecualikan dari dialog yang sama — juga untuk karyawan yang sudah tidak aktif (resign
       sesudah dikunci, kasus paling umum). */
    public function test_kecualikan_dari_revisi_juga_untuk_karyawan_tidak_aktif(): void
    {
        $p = $this->pesertaBerpenilai();
        $awal = $this->penugasanAktif($p->Id_PA_Participant);
        DB::table('Karyawan')->where('Kode_Perusahaan', $p->Kode_Perusahaan)->where('Kode_Karyawan', $p->Kode_Karyawan)->update(['Aktif' => 'T']);
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertTrue($pr['tindakan']['KECUALIKAN']['boleh'], (string) $pr['tindakan']['KECUALIKAN']['sebab']);
        $this->assertFalse($pr['tindakan']['TERAPKAN_LEVEL']['boleh']);

        $hasil = $this->revisi->jalankan('KECUALIKAN', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Resign sesudah dikunci (uji).', 4);

        $this->assertSame($awal, $hasil['dibatalkan']);
        $this->assertSame('DIKECUALIKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->value('Status_Peserta'));
        $this->assertSame(0, $this->penugasanAktif($p->Id_PA_Participant));
        $this->assertTrue(DB::table('N_PA_Cycle_Pre_Exclusion')->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $p->Kode_Karyawan)->where('Flag_Aktif', 'Y')->exists());
    }

    public function test_peserta_susulan_dibuat_dibentuk_dan_dijadwalkan(): void
    {
        $p = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')->first();
        /* Meniru karyawan yang BELUM pernah menjadi peserta (uji ini dibungkus transaksi). */
        foreach (DB::select("SELECT OBJECT_NAME(f.parent_object_id) t, COL_NAME(fc.parent_object_id, fc.parent_column_id) c
            FROM sys.foreign_keys f JOIN sys.foreign_key_columns fc ON fc.constraint_object_id = f.object_id
            WHERE f.referenced_object_id = OBJECT_ID('N_PA_Participant')") as $fk) {
            DB::table($fk->t)->where($fk->c, $p->Id_PA_Participant)->delete();
        }
        DB::table('N_PA_Participant')->where('Id_PA_Participant', $p->Id_PA_Participant)->delete();
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertNull($pr['peserta']);
        $this->assertTrue($pr['tindakan']['TAMBAH_SUSULAN']['boleh'], (string) $pr['tindakan']['TAMBAH_SUSULAN']['sebab']);

        $hasil = $this->revisi->jalankan('TAMBAH_SUSULAN', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Karyawan baru masuk (uji).', 4);

        $baru = DB::table('N_PA_Participant')->where('Id_PA_Participant', $hasil['participant_id'])->first();
        $this->assertSame('DIIKUTKAN', $baru->Status_Peserta);
        $this->assertNotNull($baru->PA_Cycle_Rater_Policy_Snapshot_Id);
        $this->assertGreaterThan(0, $hasil['dibentuk']);
    }

    /* Level orangnya tidak dinilai di siklus ini: jalan keluarnya Koreksi Level, dan itu yang
       disebut — bukan sekadar "tidak bisa". */
    public function test_level_tidak_dinilai_mengarahkan_ke_koreksi_level(): void
    {
        $p = $this->pesertaBerpenilai();
        app(PaParticipantEditService::class)->ubahStatus((int) $p->Id_PA_Participant, 'DIKECUALIKAN', 'Cuti (uji).', 4);
        $this->kunci();

        DB::table('N_PA_Level_Exclusion')->insert(['ID_Level' => (int) $p->ID_Level_Saat_Cycle, 'Alasan' => 'Uji level tidak dinilai', 'Flag_Aktif' => 'Y']);
        PaLevelDikecualikan::lupakan();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $ikut = $pr['tindakan']['IKUTKAN_KEMBALI'];
        $this->assertFalse($ikut['boleh']);
        $this->assertTrue($ikut['perlu_koreksi_level']);
        $this->assertStringContainsString('koreksi Level PA-nya dulu', $ikut['sebab']);
        $this->assertSame('/performance-appraisal/master/level-pa', $pr['alamat_koreksi_level']);

        $massal = $this->revisi->pratinjauBanyak($this->cycleId, 'IKUTKAN_KEMBALI', [['kode_perusahaan' => $p->Kode_Perusahaan, 'kode_karyawan' => $p->Kode_Karyawan]]);
        $this->assertSame(1, $massal['ringkasan']['perlu_koreksi_level']);
        $this->assertTrue($massal['baris'][0]['perlu_koreksi_level']);
    }

    public function test_cari_berfilter_untuk_centang_semua(): void
    {
        $semua = $this->revisi->cari($this->cycleId, 'peserta', []);
        $diikutkan = $this->revisi->cari($this->cycleId, 'peserta', ['status' => 'DIIKUTKAN']);
        $this->assertNotEmpty($diikutkan);
        $this->assertTrue(collect($diikutkan)->every(fn ($r) => $r['status'] === 'DIIKUTKAN'));
        $this->assertLessThanOrEqual(count($semua), count($diikutkan));

        $lvl = $diikutkan[0]['id_level'];
        $perLevel = $this->revisi->cari($this->cycleId, 'peserta', ['id_level' => [$lvl]]);
        $this->assertTrue(collect($perLevel)->every(fn ($r) => (int) $r['id_level'] === (int) $lvl));

        /* Sumber karyawan TIDAK memuat yang sudah peserta. */
        $kodePeserta = collect($semua)->map(fn ($r) => $r['kode_perusahaan'].'|'.$r['kode_karyawan'])->flip();
        foreach ($this->revisi->cari($this->cycleId, 'karyawan', [], 200) as $r) {
            $this->assertFalse(isset($kodePeserta[$r['kode_perusahaan'].'|'.$r['kode_karyawan']]));
        }
    }

    /* Banyak orang: dipratinjau sekaligus, dijalankan latar, penjadwalan SEKALI di akhir. */
    public function test_revisi_massal_berjalan_latar_dan_menjadwalkan_sekali(): void
    {
        $a = $this->pesertaBerpenilai();
        app(PaParticipantEditService::class)->ubahStatus((int) $a->Id_PA_Participant, 'DIKECUALIKAN', 'Cuti (uji).', 4);
        $diikutkan = DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')->first();
        $this->kunci();

        $orang = [
            ['kode_perusahaan' => $a->Kode_Perusahaan, 'kode_karyawan' => $a->Kode_Karyawan],
            ['kode_perusahaan' => $diikutkan->Kode_Perusahaan, 'kode_karyawan' => $diikutkan->Kode_Karyawan],
        ];

        $pr = $this->revisi->pratinjauBanyak($this->cycleId, 'IKUTKAN_KEMBALI', $orang);
        $this->assertSame(['total' => 2, 'bisa' => 1, 'tidak' => 1], array_intersect_key($pr['ringkasan'], ['total' => 1, 'bisa' => 1, 'tidak' => 1]));

        $mulai = $this->postJson("/api/v1/performance-appraisal/cycles/{$this->cycleId}/revisi-peserta/massal", [
            'tindakan' => 'IKUTKAN_KEMBALI', 'orang' => $orang, 'alasan' => 'Batal cuti massal (uji).',
        ])->assertOk()->json('result');
        $this->assertSame(1, $mulai['diproses']);
        $this->assertSame(1, $mulai['dilewati']);
        Queue::assertPushed(\App\Jobs\KPI\PerformanceAppraisal\RevisiPesertaPaJob::class);

        app()->call([new \App\Jobs\KPI\PerformanceAppraisal\RevisiPesertaPaJob($mulai['tugas']['id']), 'handle']);

        $layar = app(\App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::class)->bentukLayar(
            app(\App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::class)->baris($mulai['tugas']['id']));
        $this->assertSame('ready', $layar['status']);
        $this->assertTrue($layar['hasil'][0]['ok']);
        $this->assertGreaterThan(0, $layar['hasil'][0]['dibentuk']);
        $this->assertNotNull($layar['penjadwalan']);
        Queue::assertPushed(PenjadwalanHcLearnPaJob::class, 1);
        $this->assertSame('DIIKUTKAN', DB::table('N_PA_Participant')->where('Id_PA_Participant', $a->Id_PA_Participant)->value('Status_Peserta'));
    }

    /* Pindah atasan / mutasi sesudah dikunci: penilai USULAN yang belum mengisi dan tidak lagi
       ada di relasi diganti calon dari relasi terbaru. Pilihan admin (MANUAL) tidak disentuh. */
    public function test_selaraskan_relasi_mengganti_penilai_yang_tidak_lagi_berelasi(): void
    {
        $p = $this->pesertaBerpenilai();
        $tugas = DB::table('N_PA_Assignment as a')->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.PA_Participant_Id', $p->Id_PA_Participant)->where('a.Flag_Aktif', 'Y')->where('c.Kode_Kategori', '<>', 'SELF')
            ->orderBy('a.Id_PA_Assignment')->get(['a.Id_PA_Assignment', 'a.Kode_Karyawan_Penilai']);
        $this->assertNotEmpty($tugas, 'Peserta uji tanpa penilai selain diri sendiri.');
        $lama = $tugas[0];

        /* Penilainya "berganti": diganti orang yang tidak berelasi dengan peserta. */
        $asing = DB::table('Karyawan')->where('Aktif', 'Y')->where('Kode_Perusahaan', $p->Kode_Perusahaan)
            ->whereNotIn('Kode_Karyawan', DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->pluck('Kode_Karyawan_Penilai')->push($p->Kode_Karyawan)->all())
            ->orderBy('Kode_Karyawan')->first(['Kode_Karyawan', 'Nama']);
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $lama->Id_PA_Assignment)
            ->update(['Kode_Karyawan_Penilai' => $asing->Kode_Karyawan, 'Nama_Penilai_Saat_Cycle' => $asing->Nama]);
        $manual = $tugas[1] ?? null;
        if ($manual) {
            DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $manual->Id_PA_Assignment)->update(['Sumber_Penugasan' => 'MANUAL']);
        }
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertTrue($pr['tindakan']['SELARASKAN_RELASI']['boleh'], (string) $pr['tindakan']['SELARASKAN_RELASI']['sebab']);
        $this->assertSame([(int) $lama->Id_PA_Assignment], array_column($pr['rencana_selaras']['keluar'], 'assignment_id'));
        $this->assertContains(trim($lama->Kode_Karyawan_Penilai), array_column($pr['rencana_selaras']['masuk'], 'kode_karyawan'));

        $hasil = $this->revisi->jalankan('SELARASKAN_RELASI', $this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan, 'Pindah atasan (uji).', 4);

        $this->assertSame(1, $hasil['dibatalkan']);
        $this->assertGreaterThanOrEqual(1, $hasil['dibentuk']);
        $this->assertSame('T', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $lama->Id_PA_Assignment)->value('Flag_Aktif'));
        $this->assertTrue(DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')
            ->where('Kode_Karyawan_Penilai', $lama->Kode_Karyawan_Penilai)->exists(), 'Penilai dari relasi tidak terbentuk lagi.');
        if ($manual) {
            $this->assertSame('Y', DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $manual->Id_PA_Assignment)->value('Flag_Aktif'));
        }

        /* Sudah selaras: tidak ada lagi yang diganti. */
        $this->revisi->lupakanMemo();
        app(PaAssignmentBuilderService::class)->lupakanMemo();
        $lagi = $this->revisi->pratinjau($this->cycleId, $p->Kode_Perusahaan, $p->Kode_Karyawan);
        $this->assertFalse($lagi['tindakan']['SELARASKAN_RELASI']['boleh']);
        $this->assertStringContainsString('sudah sesuai relasi', $lagi['tindakan']['SELARASKAN_RELASI']['sebab']);
    }

    /* Penilai resign sesudah dikunci (bukan peserta pun): tugas menilainya dibatalkan, ia tidak
       dipilih lagi, dan peserta yang ditinggal dibentukkan pengganti atau disebut namanya. */
    public function test_lepas_penilai_tidak_aktif_membatalkan_tugas_menilainya(): void
    {
        $p = $this->pesertaBerpenilai();
        $penilai = DB::table('N_PA_Assignment as a')->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.PA_Participant_Id', $p->Id_PA_Participant)->where('a.Flag_Aktif', 'Y')->where('c.Kode_Kategori', '<>', 'SELF')
            ->first(['a.Kode_Perusahaan_Penilai as kp', 'a.Kode_Karyawan_Penilai as kk']);
        DB::table('Karyawan')->where('Kode_Perusahaan', $penilai->kp)->where('Kode_Karyawan', $penilai->kk)->update(['Aktif' => 'T']);
        $this->kunci();

        $tidakAktif = $this->revisi->cari($this->cycleId, 'penilai', ['status' => 'TIDAK_AKTIF']);
        $this->assertContains(trim($penilai->kk), array_map('trim', array_column($tidakAktif, 'kode_karyawan')));

        $pr = $this->revisi->pratinjauBanyak($this->cycleId, 'LEPAS_PENILAI', [['kode_perusahaan' => $penilai->kp, 'kode_karyawan' => $penilai->kk]]);
        $this->assertTrue($pr['baris'][0]['boleh'], (string) $pr['baris'][0]['sebab']);
        $menilai = $pr['baris'][0]['dampak']['menilai'];
        $this->assertGreaterThan(0, $menilai);

        $hasil = $this->revisi->jalankan('LEPAS_PENILAI', $this->cycleId, $penilai->kp, $penilai->kk, 'Resign 1 Okt (uji).', 4);

        $this->assertSame($menilai, $hasil['dibatalkan']);
        $sisa = DB::table('N_PA_Assignment as a')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $this->cycleId)->where('a.Flag_Aktif', 'Y')
            ->where('a.Kode_Perusahaan_Penilai', $penilai->kp)->where('a.Kode_Karyawan_Penilai', $penilai->kk)
            ->whereColumn('a.Kode_Karyawan_Penilai', '<>', 'p.Kode_Karyawan')->count();
        $this->assertSame(0, $sisa, 'Penilai yang dilepas masih (atau lagi) memegang tugas menilai.');
        $this->assertTrue(DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)
            ->where('Cancel_Reason', 'like', 'Penilai dilepas dari siklus:%')->exists());
        $this->assertIsArray($hasil['tanpa_pengganti']);
    }

    /* Dikecualikan sebagai peserta TIDAK otomatis berhenti menilai — kecuali admin memilih
       "lepas juga sebagai penilai". */
    public function test_kecualikan_dengan_lepas_penilai(): void
    {
        $pembentuk = app(PaAssignmentBuilderService::class);
        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')->limit(40)->pluck('Id_PA_Participant') as $id) {
            $pembentuk->bentukUntukPeserta((int) $id, 4);
        }
        $pembentuk->lupakanMemo();

        /* Peserta yang juga menilai peserta lain. */
        $x = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as dinilai', 'dinilai.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Participant as x', function ($j) {
                $j->on('x.Kode_Perusahaan', '=', 'a.Kode_Perusahaan_Penilai')->on('x.Kode_Karyawan', '=', 'a.Kode_Karyawan_Penilai')
                    ->on('x.PA_Cycle_Id', '=', 'dinilai.PA_Cycle_Id');
            })
            ->where('dinilai.PA_Cycle_Id', $this->cycleId)->where('a.Flag_Aktif', 'Y')->where('x.Status_Peserta', 'DIIKUTKAN')
            ->whereColumn('x.Id_PA_Participant', '<>', 'dinilai.Id_PA_Participant')
            ->first(['x.Kode_Perusahaan', 'x.Kode_Karyawan']);
        $this->assertNotNull($x, 'Tidak ada peserta yang juga menilai — uji ini tidak menguji apa pun.');
        $this->kunci();

        $pr = $this->revisi->pratinjau($this->cycleId, $x->Kode_Perusahaan, $x->Kode_Karyawan);
        $menilai = $pr['dampak']['menilai'];
        $this->assertGreaterThan(0, $menilai);

        $hasil = $this->revisi->jalankan('KECUALIKAN', $this->cycleId, $x->Kode_Perusahaan, $x->Kode_Karyawan, 'Resign (uji).', 4, false, ['lepas_penilai' => true]);

        $this->assertGreaterThanOrEqual($menilai, $hasil['dibatalkan']);
        $this->revisi->lupakanMemo();
        $this->assertSame(0, $this->revisi->pratinjau($this->cycleId, $x->Kode_Perusahaan, $x->Kode_Karyawan)['dampak']['menilai']);
    }

    /* Di bawah ambang pengisi, jawaban yang ada pun dibuang — pratinjau menyebutnya SEBELUM
       penilai dilepas, termasuk bila pengganti dari relasi tidak cukup. */
    public function test_pratinjau_lepas_menyebut_kategori_yang_tidak_akan_dihitung(): void
    {
        $pembentuk = app(PaAssignmentBuilderService::class);
        $kasus = null;
        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')->limit(40)->get() as $p) {
            $pembentuk->bentukUntukPeserta((int) $p->Id_PA_Participant, 4);
            $pembentuk->lupakanMemo();
            foreach ($pembentuk->usulkan((int) $p->Id_PA_Participant) as $kode => $d) {
                $sisaCalon = count(array_filter($d['calon'], fn ($c) => empty($c['sudah_ditugaskan']) && empty($c['pernah_dicabut']) && empty($c['di_luar_relasi'])));
                if ($kode !== 'SELF' && $d['terpasang'] >= 1 && $d['terpasang'] <= $d['min_rater_selesai'] && $sisaCalon === 0) {
                    $kasus = [$p, $d];
                    break 2;
                }
            }
        }
        $this->assertNotNull($kasus, 'Tidak ada kategori tanpa calon pengganti di data uji — uji ini tidak menguji apa pun.');
        [$p, $d] = $kasus;
        $penilai = DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')
            ->where('PA_Rater_Category_Id', $d['kategori_id'])->first(['Kode_Perusahaan_Penilai as kp', 'Kode_Karyawan_Penilai as kk']);
        $this->kunci();

        $pr = $this->revisi->pratinjauBanyak($this->cycleId, 'LEPAS_PENILAI', [['kode_perusahaan' => $penilai->kp, 'kode_karyawan' => $penilai->kk]]);

        $this->assertGreaterThan(0, $pr['ringkasan']['peringatan']);
        $this->assertTrue(collect($pr['baris'][0]['peringatan'])->contains(
            fn ($w) => str_contains($w, $p->Nama_Karyawan_Saat_Cycle.': kategori '.$d['nama_kategori'].' tidak akan dihitung')
        ), json_encode($pr['baris'][0]['peringatan']));
    }

    /* Penilai baru memakai jendela pengisian siklus: hampir habis / lewat disebut di muka. */
    public function test_keadaan_jendela_pengisian_untuk_penilai_baru(): void
    {
        $atur = fn (string $sampai) => DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Pengisian_Dari' => now()->subDays(20)->format('Y-m-d H:i:s'), 'Pengisian_Sampai' => $sampai]);

        $atur(now()->addDays(10)->format('Y-m-d H:i:s'));
        $this->assertSame('AMAN', $this->revisi->keadaanJendela($this->cycleId)['status']);

        $atur(now()->addHours(30)->format('Y-m-d H:i:s'));
        $j = $this->revisi->keadaanJendela($this->cycleId);
        $this->assertSame('HAMPIR', $j['status']);
        $this->assertStringContainsString('hanya punya sekitar 1 hari', $j['pesan']);

        $atur(now()->subDays(2)->format('Y-m-d H:i:s'));
        $j = $this->revisi->keadaanJendela($this->cycleId);
        $this->assertSame('LEWAT', $j['status']);
        $this->assertStringContainsString('perpanjang jendela pengisian', $j['pesan']);
    }

    /* Penilai baru dipastikan dikenal HC Learn sebelum dijadwalkan: yang belum terdaftar
       didaftarkan, yang resign di sisi HC Learn disebut namanya. */
    public function test_penilai_baru_didaftarkan_atau_disebut_sebelum_dijadwalkan(): void
    {
        $this->pesertaBerpenilai();
        $penilai = DB::table('N_PA_Assignment as a')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $this->cycleId)->where('a.Flag_Aktif', 'Y')
            ->distinct()->orderBy('a.Kode_Karyawan_Penilai')->limit(2)->pluck('a.Kode_Karyawan_Penilai')->map(fn ($k) => trim($k))->all();
        $this->assertCount(2, $penilai, 'Siklus uji butuh dua penilai.');
        [$this->hclBelum, $this->hclResign] = [[$penilai[0]], [$penilai[1]]];

        $hasil = $this->revisi->pastikanPenilaiBaruTerdaftar($this->cycleId);

        $this->assertSame([$penilai[0]], $this->hclDidaftarkan, 'add-new hanya untuk yang belum terdaftar.');
        $this->assertSame(1, $hasil['didaftarkan']);
        $this->assertSame([$penilai[1]], array_column($hasil['tidak_siap'], 'kode_karyawan'));
        $this->assertStringContainsString('1 penilai baru ditolak HC Learn', PaRevisiPesertaService::kalimatTidakSiap($hasil));
    }

    /* Bobot khusus admin dibagi ulang begitu susunan penilainya berubah — disebut di muka. */
    public function test_pratinjau_lepas_menyebut_bobot_khusus_yang_dibagi_ulang(): void
    {
        $p = $this->pesertaBerpenilai();
        $tugas = DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')
            ->where('Kode_Karyawan_Penilai', '<>', $p->Kode_Karyawan)->orderBy('Id_PA_Assignment')->first();
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $tugas->Id_PA_Assignment)
            ->update(['Bobot_Kustom' => 'Y', 'Bobot_Diatur_Pada' => now()]);
        $this->kunci();

        $pr = $this->revisi->pratinjauBanyak($this->cycleId, 'LEPAS_PENILAI',
            [['kode_perusahaan' => $tugas->Kode_Perusahaan_Penilai, 'kode_karyawan' => $tugas->Kode_Karyawan_Penilai]]);

        $this->assertTrue(collect($pr['baris'][0]['peringatan'])->contains(
            fn ($w) => str_contains($w, $p->Nama_Karyawan_Saat_Cycle.': bobot khusus penilai kategori')
        ), json_encode($pr['baris'][0]['peringatan']));
    }

    /* Penilai yang ditambah lewat Kelola penilai sesudah dikunci langsung dijadwalkan —
       bukan menunggu admin ingat menekan "Jadwalkan sisanya". Sebelum dikunci: tidak. */
    public function test_kelola_penilai_sesudah_dikunci_langsung_menjadwalkan(): void
    {
        $p = $this->pesertaBerpenilai();
        $tugas = DB::table('N_PA_Assignment')->where('PA_Participant_Id', $p->Id_PA_Participant)->where('Flag_Aktif', 'Y')
            ->where('Kode_Karyawan_Penilai', '<>', $p->Kode_Karyawan)->orderBy('Id_PA_Assignment')->first();
        $kode = DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $tugas->PA_Rater_Category_Id)->value('Kode_Kategori');
        app(PaAssignmentBuilderService::class)->batalkan((int) $tugas->Id_PA_Assignment, 'Uji kelola penilai.', 4);
        $kirim = fn () => $this->postJson("/api/v1/performance-appraisal/participants/{$p->Id_PA_Participant}/penugasan", ['penilai' => [[
            'kode_kategori' => $kode, 'kode_perusahaan' => $tugas->Kode_Perusahaan_Penilai, 'kode_karyawan' => trim($tugas->Kode_Karyawan_Penilai),
            'sumber' => 'MANUAL', 'alasan_manual' => 'Uji kelola penilai sesudah dikunci.',
        ]]]);

        $this->kunci();
        $r = $kirim()->assertOk()->json('result');

        $this->assertSame(1, $r['dibuat']);
        $this->assertNotNull($r['jadwal_hclearn']['penjadwalan'] ?? null, json_encode($r));
        Queue::assertPushed(PenjadwalanHcLearnPaJob::class);
    }

    /* ------------------------------------------------------------------ */

    private function gate(): PaCycleGate
    {
        return app(PaCycleGate::class);
    }

    private function kunci(): void
    {
        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)->update(['Status_Cycle' => 'LOCKED']);
        $this->gate()->lupakanMemo();
    }

    /** Peserta diikutkan yang punya calon penilai — penugasannya dibentuk saat PREPARED. */
    private function pesertaBerpenilai(): object
    {
        $pembentuk = app(PaAssignmentBuilderService::class);

        foreach (DB::table('N_PA_Participant')->where('PA_Cycle_Id', $this->cycleId)->where('Status_Peserta', 'DIIKUTKAN')
            ->orderBy('Id_PA_Participant')->limit(30)->get() as $p) {
            if ($pembentuk->bentukUntukPeserta((int) $p->Id_PA_Participant, 4)['dibuat'] > 1) {
                $pembentuk->lupakanMemo();

                return $p;
            }
        }

        $this->fail('Tidak ada peserta dengan calon penilai di data uji.');
    }

    private function penugasanAktif(int|string $pid): int
    {
        return DB::table('N_PA_Assignment')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->count();
    }
}
