<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Approval\Performance\PerformanceApprovalController;
use App\Http\Controllers\KPI\Approval\Verification\KpiVerifikasiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Approver/verifikator yang mengubah nilai aktual harus melewati penjepitan
 * per parameter yang SAMA dengan jalur pengisian.
 *
 * Jalur ini sebelumnya tidak punya satu uji pun. Ia berbahaya justru karena
 * diam: bila approver menulis lewat rumus mentah sementara karyawan menulis
 * lewat rumus terjepit, satu persetujuan sudah cukup membuat detail dan header
 * berselisih — tanpa error, dan tanpa kolom mana pun yang tampak salah.
 */
class ApprovalEditCapPerParameterTest extends TestCase
{
    use DatabaseTransactions;

    private function ctl(): PerformanceApprovalController
    {
        return app(PerformanceApprovalController::class);
    }

    private function panggil(string $nama, array $args)
    {
        $m = new ReflectionMethod(PerformanceApprovalController::class, $nama);
        $m->setAccessible(true);

        return $m->invoke($this->ctl(), ...$args);
    }

    /**
     * Satu detail final yang rumusnya rasio lurus actual/target dan targetnya
     * positif, sehingga aktual bisa disetel untuk menembus plafon maupun jatuh
     * di bawah lantai secara terkendali.
     */
    private function detailRasioLurus()
    {
        return DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Pengajuan_Det as a', 'a.Id_Pengajuan_Det', '=', 'upd.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as b', 'b.Id_Pengajuan_Detail', '=', 'a.Id_Pengajuan_Detail')
            ->join('KPI_Master_Formula as c', 'c.Id_Master_Formula', '=', 'b.Id_Master_Formula')
            ->where('a.Nilai_Target', '>', 0)
            ->where('b.Bobot', '>', 0)
            ->whereRaw("REPLACE(CAST(c.Rumus AS VARCHAR(MAX)), ' ', '') = '[actualKpiEvo]/[targetKpiEvo]'")
            ->orderByDesc('upd.Id_Update_Performance_Detail')
            ->select(
                'upd.Id_Update_Performance_Detail',
                'upd.Id_Update_Performance',
                'a.Nilai_Target',
                'b.Bobot',
                'b.Id_Pengajuan_Detail',
            )
            ->first();
    }

    private function baris(int $id)
    {
        return DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance_Detail', $id)
            ->first();
    }

    public function test_edit_approver_menembus_plafon_disimpan_terjepit_dengan_jejak_mentah(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        // Rasio 3 -> achievement mentah 300%, jauh di atas plafon 125.
        $this->panggil('saveEditedActual', [
            (int) $d->Id_Update_Performance,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target * 3,
        ]);

        $baris = $this->baris((int) $d->Id_Update_Performance_Detail);

        $this->assertSame(125.0, (float) $baris->Score, 'Achievement tersimpan harus terjepit di plafon.');
        $this->assertSame(300.0, (float) $baris->Score_Raw, 'Angka mentah harus tetap terekam apa adanya.');
        $this->assertSame('Y', $baris->Flag_Capped);

        // Skor berbobot WAJIB ikut turun. Menjepit achievement sendirian sambil
        // membiarkan skor dihitung dari rasio mentah membuat total berhenti sama
        // dengan rata-rata tertimbang baris yang ditampilkan.
        $this->assertSame(
            round((float) $d->Bobot * 1.25, 2),
            (float) $baris->Achievement,
            'Skor berbobot harus diturunkan dari rasio yang sudah dijepit.',
        );
        $this->assertSame(round((float) $d->Bobot * 3, 2), (float) $baris->Achievement_Raw);
    }

    public function test_edit_approver_di_bawah_lantai_diangkat_dengan_jejak_mentah(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        // Rasio 0,2 -> achievement mentah 20%, di bawah lantai 50.
        $this->panggil('saveEditedActual', [
            (int) $d->Id_Update_Performance,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target * 0.2,
        ]);

        $baris = $this->baris((int) $d->Id_Update_Performance_Detail);

        $this->assertSame(50.0, (float) $baris->Score);
        $this->assertSame(20.0, (float) $baris->Score_Raw);
        $this->assertSame('Y', $baris->Flag_Capped);
        $this->assertSame(round((float) $d->Bobot * 0.5, 2), (float) $baris->Achievement);
    }

    public function test_edit_di_dalam_rentang_tidak_ditandai_terjepit(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        $this->panggil('saveEditedActual', [
            (int) $d->Id_Update_Performance,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target,
        ]);

        $baris = $this->baris((int) $d->Id_Update_Performance_Detail);

        $this->assertSame(100.0, (float) $baris->Score);
        $this->assertSame(100.0, (float) $baris->Score_Raw);
        $this->assertSame('T', $baris->Flag_Capped);
    }

    /**
     * Header hasil recalc harus berdiri di atas detail yang SUDAH dijepit, dan
     * kolom mentahnya di atas jumlah yang BELUM dijepit. Dua sumbu itu tidak
     * boleh tertukar: kalau Total_Score_Achievement_Raw menyalin nilai terjepit,
     * jejak auditnya berbohong tanpa ada yang tampak rusak.
     */
    public function test_recalc_header_memisahkan_total_terjepit_dan_total_mentah(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        $idUp = (int) $d->Id_Update_Performance;

        $this->panggil('saveEditedActual', [
            $idUp,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target * 3,
        ]);

        $this->panggil('recalcPerformanceTotals', [$idUp]);

        $header = DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $idUp)->first();

        $detail = DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->where('upd.Id_Update_Performance', $idUp)
            ->get(['upd.Achievement', 'upd.Achievement_Raw', 'pd.Bobot']);

        $sumBobot = $detail->sum(fn ($r) => (float) $r->Bobot);

        if ($sumBobot <= 0) {
            $this->markTestSkipped('Header ini tidak punya bobot aktif.');
        }

        $sumJepit = $detail->sum(fn ($r) => (float) $r->Achievement);
        $sumMentah = $detail->sum(fn ($r) => (float) ($r->Achievement_Raw ?? $r->Achievement));

        $this->assertEqualsWithDelta(
            round($sumJepit / $sumBobot * 100, 2),
            (float) $header->Total_Score,
            0.01,
            'Total_Score harus rata-rata tertimbang detail yang SUDAH dijepit.',
        );

        $this->assertEqualsWithDelta(
            round($sumMentah, 2),
            (float) $header->Total_Score_Achievement_Raw,
            0.01,
            'Total_Score_Achievement_Raw harus jumlah skor MENTAH, bukan salinan yang terjepit.',
        );

        $this->assertSame('Y', $header->Flag_Capped, 'Ada parameter terjepit, header wajib menandainya.');

        // Batas yang tercatat harus batas per-parameter yang benar-benar berlaku.
        $this->assertSame(125.0, (float) $header->Cap_Max_Applied);
        $this->assertSame(50.0, (float) $header->Cap_Min_Applied);
    }

    /**
     * Proyeksi payload approval harus IKUT membawa jejak penjepitan.
     *
     * Tanpa dua kolom itu layar approval memperlihatkan angka yang sudah
     * dipangkas plafon tanpa satu pun tanda bahwa pemangkasan terjadi, dan
     * approver justru orang yang paling perlu melihatnya.
     *
     * Penjaga ini juga menutup kegagalan senyap: getApprovalDetail() menelan
     * seluruh exception dan memulangkan 500 "Terjadi Kesalahan", jadi subkueri
     * yang salah ketik tidak akan pernah menampakkan dirinya sebagai error SQL.
     */
    public function test_payload_approval_membawa_jejak_penjepitan_per_parameter(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        $header = DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', (int) $d->Id_Update_Performance)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $header) {
            $this->markTestSkipped('Header realisasinya tidak aktif; subkueri payload menyaring Flag_Aktif=Y.');
        }

        $idPengajuan = DB::table('KPI_Pengajuan_Detail')
            ->where('Id_Pengajuan_Detail', $d->Id_Pengajuan_Detail)
            ->value('Id_Pengajuan');

        // Paksa satu parameter menembus plafon supaya jejaknya punya isi yang
        // bisa dibedakan dari nilai tersimpannya.
        $this->panggil('saveEditedActual', [
            (int) $d->Id_Update_Performance,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target * 3,
        ]);

        $res = $this->ctl()->getApprovalDetail(new Request, $idPengajuan, $header->Periode);

        $this->assertSame(200, $res->getStatusCode(), 'getApprovalDetail menelan exception jadi 500; cek subkuerinya.');

        $isi = json_decode($res->getContent(), true);
        $baris = collect($isi['result']['kpiDetails'] ?? []);

        $this->assertNotEmpty($baris, 'Payload tidak memuat satu pun parameter.');
        $this->assertTrue($baris->every(fn ($b) => array_key_exists('score_raw', $b)), 'Kolom score_raw tidak ada di payload.');
        $this->assertTrue($baris->every(fn ($b) => array_key_exists('flag_capped', $b)), 'Kolom flag_capped tidak ada di payload.');

        $terjepit = $baris->firstWhere('Id_Pengajuan_Detail', $d->Id_Pengajuan_Detail)
            ?? $baris->firstWhere('flag_capped', 'Y');

        $this->assertNotNull($terjepit, 'Parameter yang baru saja dijepit tidak muncul di payload.');
        $this->assertSame('Y', $terjepit['flag_capped']);
        $this->assertSame(125.0, (float) $terjepit['score'], 'Payload harus membawa nilai TERJEPIT di kolom score.');
        $this->assertSame(300.0, (float) $terjepit['score_raw'], 'Payload harus membawa nilai MENTAH di kolom score_raw.');
    }

    /**
     * PINTU KEDUA. Jejak penjepitan harus ikut keluar dari proyeksi milik
     * KpiVerifikasiController juga, bukan hanya dari PerformanceApprovalController.
     *
     * Keduanya membangun kpiDetails-nya sendiri-sendiri dan sama-sama
     * dirutekan. Melengkapi salah satunya saja membuat verifikator melihat
     * angka yang sudah dipangkas plafon tanpa tanda apa pun, sementara approver
     * di halaman sebelah melihat tandanya — persis bentuk cacat yang berulang
     * di proyek ini: aturan dipasang di satu dari dua jalur setara.
     */
    public function test_proyeksi_verifikasi_juga_membawa_jejak_penjepitan(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        $noTransaksi = DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', (int) $d->Id_Update_Performance)
            ->value('No_Transaksi');

        if (! $noTransaksi) {
            $this->markTestSkipped('Realisasi ini tidak punya No_Transaksi.');
        }

        $this->panggil('saveEditedActual', [
            (int) $d->Id_Update_Performance,
            (int) $d->Id_Update_Performance_Detail,
            (float) $d->Nilai_Target * 3,
        ]);

        $res = app(KpiVerifikasiController::class)->getApprovalDetail(new Request, $noTransaksi);
        $isi = json_decode($res->getContent(), true);
        $baris = collect($isi['result']['kpiDetails'] ?? []);

        $this->assertNotEmpty($baris, 'Proyeksi verifikasi tidak memuat satu pun parameter.');
        $this->assertTrue($baris->every(fn ($b) => array_key_exists('score_raw', $b)), 'Kolom score_raw tidak ada di proyeksi verifikasi.');
        $this->assertTrue($baris->every(fn ($b) => array_key_exists('flag_capped', $b)), 'Kolom flag_capped tidak ada di proyeksi verifikasi.');

        $terjepit = $baris->firstWhere('id', (int) $d->Id_Update_Performance_Detail);

        $this->assertNotNull($terjepit, 'Parameter yang baru dijepit tidak muncul di proyeksi verifikasi.');
        $this->assertSame('Y', $terjepit['flag_capped']);
        $this->assertSame(125.0, (float) $terjepit['score']);
        $this->assertSame(300.0, (float) $terjepit['score_raw']);
    }

    /**
     * Uji lewat EFEK, bukan lewat pemanggilan.
     *
     * Uji-uji di atas memanggil saveEditedActual() langsung: itu membuktikan
     * daunnya benar, bukan bahwa jalur verifikator benar-benar sampai ke sana.
     * Verifikator menyimpan lewat edit-approve, yang rantainya:
     *
     *   KpiVerifikasiController::editAndApprovePerformance (delegasi)
     *     -> PerformanceApprovalController::editAndApprovePerformance
     *       -> applyPerformanceEdits()  -> saveEditedActual()
     *
     * Yang diuji di sini adalah bentuk muatan yang DIKIRIM layar verifikator
     * (actual_values berkunci Id_Update_Performance_Detail), supaya rantainya
     * tidak bisa diam-diam dialihkan ke penulis lain tanpa penjepitan.
     */
    public function test_muatan_edit_verifikator_mendarat_terjepit(): void
    {
        $d = $this->detailRasioLurus();

        if (! $d) {
            $this->markTestSkipped('Data dev tidak punya detail berumus rasio lurus dengan target positif.');
        }

        $idDetail = (int) $d->Id_Update_Performance_Detail;

        $this->panggil('applyPerformanceEdits', [
            new Request(['actual_values' => [$idDetail => (float) $d->Nilai_Target * 3]]),
            (int) $d->Id_Update_Performance,
        ]);

        $baris = $this->baris($idDetail);

        $this->assertSame(125.0, (float) $baris->Score, 'Muatan verifikator harus melewati penjepitan yang sama.');
        $this->assertSame(300.0, (float) $baris->Score_Raw);
        $this->assertSame('Y', $baris->Flag_Capped);
    }
}
