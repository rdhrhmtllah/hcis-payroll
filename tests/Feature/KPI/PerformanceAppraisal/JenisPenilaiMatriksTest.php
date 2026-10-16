<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use App\Services\KPI\PerformanceAppraisal\PaJenisPenilaiMatriksService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Matriks level × jenis penilai, dan pengecualian level yang tinggal di dalamnya.
 *
 * ── Tiga sifat yang dijaga ────────────────────────────────────────────────────
 *
 *  1. Bobot dan kuota DIBACA dari kebijakan penilai, tidak dihitung ulang di sini.
 *     Dua penghitung untuk satu angka cepat atau lambat berselisih, dan yang terlihat
 *     admin adalah dua layar yang saling membantah tanpa ada yang salah sendiri.
 *  2. Alasan pengecualian WAJIB. Pengecualian tanpa sebab menghasilkan level yang hilang
 *     dari penilaian tanpa satu orang pun bisa menjawab kenapa.
 *  3. Pengecualian TIDAK menyentuh siklus yang sedang berjalan. Peserta membawa levelnya
 *     sebagai salinan; mencabut mereka di tengah jalan membuang penilaian yang sudah masuk.
 *
 * ── Cara membuatnya merah ─────────────────────────────────────────────────────
 *
 * Hitung ulang bobot di dalam service ini alih-alih membacanya; longgarkan penjaga alasan;
 * atau tambahkan penghapusan peserta pada `kecualikan()`.
 */
class JenisPenilaiMatriksTest extends TestCase
{
    use DatabaseTransactions;

    private PaJenisPenilaiMatriksService $service;

    /** @var array<int, array<string, mixed>> */
    private array $exclusionsAwal = [];

    /** @var array<int, array<string, mixed>> */
    private array $detailsAwal = [];

    protected function setUp(): void
    {
        parent::setUp();

        PaLevelDikecualikan::lupakan();
        $this->service = app(PaJenisPenilaiMatriksService::class);
        $this->exclusionsAwal = DB::table('N_PA_Level_Exclusion')->get()->map(fn ($r) => (array) $r)->all();
        $this->detailsAwal = DB::table('N_PA_Rater_Policy_Detail')->get()->map(fn ($r) => (array) $r)->all();
    }

    protected function tearDown(): void
    {
        PaLevelDikecualikan::lupakan();
        $this->pulihkanDatabase();

        parent::tearDown();
    }

    public function test_matriks_membaca_bobot_dan_kuota_dari_kebijakan_penilai(): void
    {
        $m = $this->service->matriks();

        $this->assertNotEmpty($m['kategori'], 'Kategori penilai kosong.');
        $this->assertNotEmpty($m['level'], 'Level kosong.');

        /* Diambil dari kebijakan yang benar-benar ada, bukan dari level yang dipilih
           sembarang: level tanpa kebijakan tidak membuktikan bahwa pembacaannya benar. */
        $detail = DB::table('N_PA_Rater_Policy as p')
            ->join('N_PA_Rater_Policy_Detail as d', 'd.PA_Rater_Policy_Id', '=', 'p.Id_PA_Rater_Policy')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('p.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->first(['p.ID_Level', 'c.Kode_Kategori', 'd.Bobot_Persen', 'd.Min_Rater', 'd.Maks_Rater']);

        if ($detail === null) {
            $this->markTestSkipped('Belum ada kebijakan penilai aktif pada basis data ini.');
        }

        $baris = collect($m['level'])->firstWhere('id_level', (int) $detail->ID_Level);

        $this->assertNotNull($baris, "Level {$detail->ID_Level} tidak ada di matriks.");

        $sel = $baris['sel'][$detail->Kode_Kategori] ?? null;

        $this->assertNotNull($sel, "Sel {$detail->Kode_Kategori} kosong padahal kebijakannya ada.");
        $this->assertSame((float) $detail->Bobot_Persen, $sel['bobot'], 'Bobot tidak sama dengan kebijakannya.');
        $this->assertSame((int) $detail->Min_Rater, $sel['min']);
        $this->assertSame((int) $detail->Maks_Rater, $sel['maks']);
    }

    public function test_level_yang_dikecualikan_tidak_ikut_dituduh_perlu_perhatian(): void
    {
        $m = $this->service->matriks();

        $dikecualikan = array_column(
            array_filter($m['level'], fn ($l) => $l['dikecualikan_pa']),
            'id_level'
        );

        $this->assertNotEmpty($dikecualikan, 'Seed DDL-2026-091 hilang: tidak ada level yang dikecualikan.');

        /* Nol kategori pada level yang memang tidak dinilai BUKAN kekeliruan, dan penjaga
           yang berteriak atas keadaan yang disengaja berhenti dibaca — lebih buruk daripada
           tidak ada penjaga sama sekali. */
        foreach (array_column($m['perlu_perhatian'], 'id_level') as $id) {
            $this->assertNotContains($id, $dikecualikan,
                "Level {$id} dikecualikan tetapi masih dituduh perlu perhatian.");
        }
    }

    public function test_alasan_wajib_dan_pengecualian_ganda_ditolak(): void
    {
        $level = $this->levelBelumDikecualikan();

        try {
            $this->service->kecualikan($level, '   ');
            $this->fail('Pengecualian tanpa alasan seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('alasan', mb_strtolower($e->getMessage()));
        }

        $this->service->kecualikan($level, 'Alasan uji otomatis.');

        try {
            $this->service->kecualikan($level, 'Alasan kedua.');
            $this->fail('Level yang sudah dikecualikan seharusnya tidak bisa dikecualikan lagi.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('sudah dikecualikan', mb_strtolower($e->getMessage()));
        }
    }

    public function test_pengecualian_tidak_menyentuh_peserta_siklus_berjalan(): void
    {
        $level = $this->levelBelumDikecualikan();

        $sebelum = DB::table('N_PA_Participant')->where('ID_Level_Saat_Cycle', $level)->count();

        $hasil = $this->service->kecualikan($level, 'Alasan uji otomatis.');

        $sesudah = DB::table('N_PA_Participant')->where('ID_Level_Saat_Cycle', $level)->count();

        $this->assertSame($sebelum, $sesudah,
            'Pengecualian level MENGHAPUS peserta. Mereka membawa levelnya sebagai salinan; '
            .'mencabutnya di tengah jalan membuang penilaian yang sudah masuk.');

        $this->assertArrayHasKey('peserta_siklus_berjalan', $hasil,
            'Angka peserta siklus berjalan harus dikembalikan supaya layar bisa menyebutnya '
            .'apa adanya, bukan membiarkan admin menebak.');
    }

    public function test_cabut_menonaktifkan_bukan_menghapus(): void
    {
        $level = $this->levelBelumDikecualikan();

        $this->service->kecualikan($level, 'Alasan uji otomatis.');
        $this->service->cabut($level);

        $this->assertFalse(PaLevelDikecualikan::apakah($level));

        /* Barisnya masih ada dengan Flag_Aktif='T'. Baris yang dihapus membuat "kenapa level
           itu tidak dinilai pada siklus lalu padahal sekarang dinilai" mustahil dijawab. */
        $this->assertDatabaseHas('N_PA_Level_Exclusion', [
            'ID_Level' => $level,
            'Flag_Aktif' => 'T',
        ]);
    }

    public function test_sel_baru_lahir_berlaku_tanpa_bobot(): void
    {
        /* INI INTI ALUR DUA LANGKAHNYA. Sel yang baru ditandai harus BERLAKU tetapi BELUM
           BERBOBOT — bukan berbobot nol. Nol berarti "kategori ini menilai tetapi tidak
           menyumbang skor", pernyataan yang berbeda dan akan ikut tersalin ke siklus. */
        $level = $this->levelBelumDikecualikan();
        $kategori = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->value('Id_PA_Rater_Category');

        $awal = $this->selDari($level, $kategori);

        if ($awal !== null) {
            $this->service->setelSel($level, $kategori, false);
        }

        $hasil = $this->service->setelSel($level, $kategori, true);

        $this->assertTrue($hasil['berlaku']);
        $this->assertFalse($hasil['berbobot'], 'Sel baru seharusnya belum berbobot.');

        $baris = $this->selDari($level, $kategori);

        $this->assertNotNull($baris, 'Barisnya tidak terbentuk.');
        $this->assertNull($baris->Bobot_Persen, 'Bobot seharusnya NULL, bukan 0.');
        $this->assertNull($baris->Min_Rater, 'Kuota seharusnya NULL sampai ditetapkan.');
    }

    public function test_sel_baru_mendapat_jenis_relasi_sesuai_kategori_dan_self_tanpa_relasi(): void
    {
        $level = $this->levelBelumDikecualikan();
        $harapan = ['ATASAN' => 'ATASAN', 'PEER' => 'PEER', 'BAWAHAN' => 'BAWAHAN', 'SELF' => null];

        foreach ($harapan as $kodeKategori => $kodeRelasi) {
            $kategori = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', $kodeKategori)->value('Id_PA_Rater_Category');
            if ($kategori === 0) {
                continue;
            }
            if ($this->selDari($level, $kategori) !== null) {
                $this->service->setelSel($level, $kategori, false);
            }
            $this->service->setelSel($level, $kategori, true);

            $relasi = DB::table('N_PA_Rater_Policy_Detail as d')
                ->join('N_PA_Rater_Policy as p', 'p.Id_PA_Rater_Policy', '=', 'd.PA_Rater_Policy_Id')
                ->leftJoin('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'd.Organization_Relationship_Type_Id')
                ->where('p.ID_Level', $level)->where('p.Flag_Aktif', 'Y')
                ->where('d.PA_Rater_Category_Id', $kategori)->where('d.Flag_Aktif', 'Y')
                ->value('t.Kode_Relasi');

            $this->assertSame($kodeRelasi, $relasi, "Kategori {$kodeKategori} harus lahir dengan jenis relasi ".($kodeRelasi ?? 'kosong').'.');
        }
    }

    public function test_matriks_membedakan_berlaku_dari_berbobot(): void
    {
        $level = $this->levelBelumDikecualikan();
        $kategori = DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Rater_Category', 'Kode_Kategori']);

        if ($this->selDari($level, (int) $kategori->Id_PA_Rater_Category) !== null) {
            $this->service->setelSel($level, (int) $kategori->Id_PA_Rater_Category, false);
        }

        $this->service->setelSel($level, (int) $kategori->Id_PA_Rater_Category, true);

        $baris = collect($this->service->matriks()['level'])->firstWhere('id_level', $level);
        $sel = $baris['sel'][$kategori->Kode_Kategori];

        $this->assertTrue($sel['berlaku']);
        $this->assertFalse($sel['berbobot']);
        $this->assertNull($sel['bobot'], 'Bobot sel yang menunggu tidak boleh dilaporkan sebagai angka.');
        $this->assertGreaterThan(0, $baris['jumlah_belum_berbobot']);

        /* Sel yang menunggu TIDAK ikut dijumlahkan: totalnya harus mencerminkan yang sudah
           berbobot saja, kalau tidak angkanya berbohong dua kali. */
        $this->assertSame(
            round(array_sum(array_map(
                fn ($x) => $x === null || ! $x['berbobot'] ? 0 : $x['bobot'],
                $baris['sel'],
            )), 2),
            $baris['total_bobot'],
        );
    }

    public function test_sel_dicabut_dengan_menonaktifkan(): void
    {
        $level = $this->levelBelumDikecualikan();
        $kategori = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->value('Id_PA_Rater_Category');

        if ($this->selDari($level, $kategori) === null) {
            $this->service->setelSel($level, $kategori, true);
        }

        $this->service->setelSel($level, $kategori, false);

        $this->assertNull($this->selDari($level, $kategori), 'Sel masih aktif sesudah dicabut.');

        $this->assertTrue(
            DB::table('N_PA_Rater_Policy_Detail as d')
                ->join('N_PA_Rater_Policy as p', 'p.Id_PA_Rater_Policy', '=', 'd.PA_Rater_Policy_Id')
                ->where('p.ID_Level', $level)
                ->where('d.PA_Rater_Category_Id', $kategori)
                ->where('d.Flag_Aktif', 'T')
                ->exists(),
            'Barisnya DIHAPUS, bukan dinonaktifkan; riwayatnya hilang.'
        );
    }

    public function test_level_yang_dikecualikan_tidak_bisa_diatur_penilainya(): void
    {
        $level = $this->levelBelumDikecualikan();
        $kategori = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->value('Id_PA_Rater_Category');

        $this->service->kecualikan($level, 'Alasan uji otomatis.');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/dikecualikan/i');

        $this->service->setelSel($level, $kategori, true);
    }

    private function selDari(int $levelId, int $kategoriId): ?object
    {
        return DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Policy as p', 'p.Id_PA_Rater_Policy', '=', 'd.PA_Rater_Policy_Id')
            ->where('p.ID_Level', $levelId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('d.PA_Rater_Category_Id', $kategoriId)
            ->where('d.Flag_Aktif', 'Y')
            ->first(['d.Id_PA_Rater_Policy_Detail', 'd.Bobot_Persen', 'd.Min_Rater']);
    }

    /**
     * Rutenya dijaga izin halaman, bukan sekadar `auth`.
     *
     * Menegaskan 401 saja tidak cukup: middleware `auth` sendirian sudah menghasilkannya,
     * sehingga uji semacam itu tetap hijau meski penjaga izinnya hilang. Yang ditegaskan di
     * sini keberadaan middleware izinnya — dan pengecualian level adalah tulisan, bukan
     * bacaan, jadi ia harus dijaga sama ketatnya dengan master mana pun.
     *
     * @dataProvider ruteJenisPenilai
     */
    public function test_rute_jenis_penilai_dijaga_izin_halaman(string $metode, string $uri): void
    {
        $cocok = null;

        foreach (\Illuminate\Support\Facades\Route::getRoutes()->getRoutes() as $r) {
            if (in_array($metode, $r->methods(), true) && $r->uri() === $uri) {
                $cocok = $r;
                break;
            }
        }

        $this->assertNotNull($cocok, "Rute {$metode} {$uri} tidak terdaftar.");

        $middleware = $cocok->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertTrue(
            (bool) count(array_filter($middleware, fn ($m) => str_starts_with((string) $m, 'check.jabatan:'))),
            "Rute {$metode} {$uri} tidak memakai check.jabatan.",
        );
    }

    public static function ruteJenisPenilai(): array
    {
        $p = 'api/v1/performance-appraisal/master/jenis-penilai';

        return [
            'matriks' => ['GET', $p.'/matriks'],
            'kecualikan' => ['POST', $p.'/{levelId}/kecualikan'],
            'bulk_kecualikan' => ['POST', $p.'/bulk/kecualikan'],
            'bulk_cabut' => ['POST', $p.'/bulk/cabut-pengecualian'],
            'sel' => ['POST', $p.'/{levelId}/sel'],
            'cabut' => ['POST', $p.'/{levelId}/cabut-pengecualian'],
            'komposisi' => ['PUT', $p.'/{levelId}/komposisi'],
            'urutan_kategori' => ['PUT', $p.'/urutan-kategori'],
            'boleh_kalibrasi' => ['PUT', $p.'/kategori/{kategoriId}/kalibrasi'],
            'halaman' => ['GET', 'performance-appraisal/master/kategori-penilai'],
        ];
    }

    public function test_kecualikan_banyak_level_sekaligus(): void
    {
        $levels = DB::table('HRIS_Level')
            ->whereNotIn('ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->orderBy('ID_Level')
            ->limit(2)
            ->pluck('ID_Level')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($levels) < 2) {
            $this->markTestSkipped('Kurang dari 2 level yang belum dikecualikan untuk uji massal.');
        }

        $hasil = $this->service->kecualikanBanyak($levels, 'Alasan uji massal.');

        $this->assertSame(2, $hasil['jumlah_level']);
        $this->assertCount(2, $hasil['level']);
        $this->assertSame('Alasan uji massal.', $hasil['alasan']);

        foreach ($levels as $id) {
            $this->assertTrue(PaLevelDikecualikan::apakah($id), "Level {$id} seharusnya berstatus dikecualikan.");
            $this->assertDatabaseHas('N_PA_Level_Exclusion', [
                'ID_Level' => $id,
                'Alasan' => 'Alasan uji massal.',
                'Flag_Aktif' => 'Y',
            ]);
        }
    }

    public function test_cabut_banyak_pengecualian_level_sekaligus(): void
    {
        $levels = DB::table('HRIS_Level')
            ->whereNotIn('ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->orderBy('ID_Level')
            ->limit(2)
            ->pluck('ID_Level')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($levels) < 2) {
            $this->markTestSkipped('Kurang dari 2 level yang belum dikecualikan untuk uji massal.');
        }

        $this->service->kecualikanBanyak($levels, 'Alasan uji massal.');
        $hasil = $this->service->cabutBanyak($levels);

        $this->assertSame(2, $hasil['jumlah_level']);

        foreach ($levels as $id) {
            $this->assertFalse(PaLevelDikecualikan::apakah($id), "Level {$id} seharusnya tidak lagi dikecualikan.");
            $this->assertDatabaseHas('N_PA_Level_Exclusion', [
                'ID_Level' => $id,
                'Flag_Aktif' => 'T',
            ]);
        }
    }

    public function test_simpan_komposisi_level_menetapkan_bobot_dan_kuota(): void
    {
        $level = $this->levelBelumDikecualikan();
        $kategori1 = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->min('Id_PA_Rater_Category');
        $kategori2 = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->where('Id_PA_Rater_Category', '>', $kategori1)->min('Id_PA_Rater_Category');

        $rincian = [
            [
                'pa_rater_category_id' => $kategori1,
                'bobot_persen' => 60,
                'min_rater' => 1,
                'maks_rater' => 2,
                'min_rater_selesai' => 1,
                'flag_izinkan_manual' => 'Y',
            ],
            [
                'pa_rater_category_id' => $kategori2,
                'bobot_persen' => 40,
                'min_rater' => 1,
                'maks_rater' => 1,
                'min_rater_selesai' => 1,
                'flag_izinkan_manual' => 'T',
            ],
        ];

        $hasil = $this->service->simpanKomposisi($level, $rincian);
        $this->assertNotEmpty($hasil);

        $m = $this->service->matriks();
        $baris = collect($m['level'])->firstWhere('id_level', $level);
        $this->assertNotNull($baris);
        $this->assertEquals(100.0, (float) $baris['total_bobot']);
    }

    private function levelBelumDikecualikan(): int
    {
        $id = DB::table('HRIS_Level')
            ->whereNotIn('ID_Level', PaLevelDikecualikan::daftar() ?: [0])
            ->orderBy('ID_Level')
            ->value('ID_Level');

        $this->assertNotNull($id, 'Semua level sudah dikecualikan; uji ini tidak bermakna.');

        return (int) $id;
    }

    private function pulihkanDatabase(): void
    {
        if (empty($this->detailsAwal)) {
            return;
        }

        $idAwal = array_column($this->detailsAwal, 'Id_PA_Rater_Policy_Detail');
        DB::table('N_PA_Rater_Policy_Detail')
            ->whereNotIn('Id_PA_Rater_Policy_Detail', $idAwal)
            ->delete();

        foreach ($this->detailsAwal as $d) {
            DB::table('N_PA_Rater_Policy_Detail')
                ->where('Id_PA_Rater_Policy_Detail', $d['Id_PA_Rater_Policy_Detail'])
                ->update([
                    'Flag_Aktif' => $d['Flag_Aktif'],
                    'Bobot_Persen' => $d['Bobot_Persen'],
                    'Min_Rater' => $d['Min_Rater'],
                    'Maks_Rater' => $d['Maks_Rater'],
                    'Min_Rater_Selesai' => $d['Min_Rater_Selesai'],
                    'Flag_Izinkan_Manual' => $d['Flag_Izinkan_Manual'],
                ]);
        }

        $idExclAwal = array_column($this->exclusionsAwal, 'Id_PA_Level_Exclusion');
        DB::table('N_PA_Level_Exclusion')
            ->whereNotIn('Id_PA_Level_Exclusion', $idExclAwal)
            ->delete();

        foreach ($this->exclusionsAwal as $e) {
            DB::table('N_PA_Level_Exclusion')
                ->where('Id_PA_Level_Exclusion', $e['Id_PA_Level_Exclusion'])
                ->update(['Flag_Aktif' => $e['Flag_Aktif']]);
        }
    }
}
