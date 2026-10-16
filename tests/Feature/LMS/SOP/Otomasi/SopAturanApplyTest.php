<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAturanPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Penerapan aturan otomasi yang memuat banyak paket.
 *
 * Yang dijaga di sini adalah sifat yang paling mudah hilang saat satu aturan
 * berubah dari satu paket menjadi banyak: kegagalan satu paket TIDAK boleh
 * menganulir paket yang sudah tersemai. Itu janji yang sudah dipegang jalur
 * chunk sejak lama; multi-paket hanya menaikkannya satu tingkat.
 */
class SopAturanApplyTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Aturan')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    private function baris(int $idPaket, int $urutan, bool $override = false, ?array $schedule = null): array
    {
        return ['id_paket' => $idPaket, 'urutan' => $urutan, 'override' => $override, 'schedule' => $schedule];
    }

    public function test_satu_aturan_menyimpan_tiga_paket_sekaligus(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket B');
        $d->terbitkanPaketLain($b['paket']);
        $c = $d->paketTambahan('Paket C');
        $d->terbitkanPaketLain($c['paket']);

        $ruleId = app(SopAssignmentRuleService::class)->saveDraft(
            $d->nama('Aturan Finance'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                $this->baris($d->idPaket, 1),
                $this->baris($b['paket'], 2),
                $this->baris($c['paket'], 3),
            ],
            $d->userIdUji(),
        );

        $this->assertCount(3, app(SopAturanPaketService::class)->daftar($ruleId),
            'Aturan tidak menyimpan ketiga paket.');
    }

    public function test_aturan_tanpa_paket_ditolak(): void
    {
        $d = $this->dunia();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(SopAssignmentRuleService::class)->saveDraft(
            $d->nama('Aturan Kosong'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [],
            $d->userIdUji(),
        );
    }

    public function test_pratinjau_memberi_angka_per_paket_dan_total(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket B');
        $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Pratinjau'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1), $this->baris($b['paket'], 2)],
            $d->userIdUji(),
        );

        $pratinjau = $svc->preview($ruleId);

        $this->assertArrayHasKey('paket', $pratinjau, 'Pratinjau tidak merinci per paket.');
        $this->assertArrayHasKey('total', $pratinjau, 'Pratinjau tidak memberi baris TOTAL.');
        $this->assertCount(2, $pratinjau['paket']);

        // Angka PENUGASAN dijumlahkan lintas paket...
        $this->assertSame(
            array_sum(array_column($pratinjau['paket'], 'new')),
            $pratinjau['total']['new'],
            'Baris TOTAL tidak menjumlahkan angka penugasan per paket.',
        );

        // ...tetapi angka ORANG tidak. 'matched' sama di setiap baris karena
        // cohort tidak bergantung paket; menjumlahkannya memberi kelipatan
        // headcount yang terbaca sebagai jangkauan aturan.
        $this->assertSame(
            (int) $pratinjau['paket'][0]['matched'],
            (int) $pratinjau['total']['matched'],
            'Baris TOTAL mengalikan jumlah orang dengan jumlah paket.',
        );
    }

    /**
     * Paket yang tenggat absolutnya sudah lewat harus terlihat TERTAHAN di
     * pratinjau — sebelum admin menekan Aktifkan, bukan ditemukan sesudahnya.
     */
    public function test_pratinjau_menandai_paket_yang_tertahan_tenggat_lewat(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Lewat');
        $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Tenggat Lewat'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                $this->baris($d->idPaket, 1),
                $this->baris($b['paket'], 2, true, [
                    'deadline_mode' => 'ABSOLUTE',
                    'deadline_date' => now()->subDays(30)->format('Y-m-d'),
                ]),
            ],
            $d->userIdUji(),
        );

        $pratinjau = collect($svc->preview($ruleId)['paket'])->keyBy('Nama_Paket');

        $tertahan = $pratinjau->firstWhere('tertahan_tenggat', true);
        $this->assertNotNull($tertahan,
            'Paket bertenggat lewat tidak ditandai; admin baru tahu setelah menekan Aktifkan.');
    }

    /**
     * PENJAGA. Kegagalan disimulasikan dengan melepas pointer versi aktif paket
     * kedua, sehingga penyemaian progress atasnya gagal.
     */
    public function test_paket_gagal_tidak_menyeret_paket_yang_sudah_tersemai(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Rusak');
        $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);
        $paketSvc = app(SopAturanPaketService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Sebagian'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1), $this->baris($b['paket'], 2)],
            $d->userIdUji(),
        );

        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $b['paket'])
            ->update(['Id_Item_Versi_Aktif' => null]);

        $runId = $svc->queueActivation($ruleId, $d->userIdUji());
        $svc->applyQueued($runId, $ruleId, true, $d->userIdUji());

        $status = collect($paketSvc->daftar($ruleId))->keyBy('Id_SOP_Paket');

        $this->assertSame('SUCCESS', $status[$d->idPaket]->Last_Apply_Status,
            'Paket pertama tidak berhasil, atau ikut dianulir oleh kegagalan paket berikutnya.');

        $anak = DB::table('N_SOP_Automation_Run')->where('Id_Run_Induk', $runId)->count();
        $this->assertSame(2, $anak,
            'Riwayat tidak memuat satu baris anak per paket, sehingga paket mana yang gagal tak terlihat.');
    }

    /**
     * Override tenggat harus benar-benar sampai ke header penjadwalan. Setelan
     * yang tersimpan tapi tak dipakai adalah setelan yang berbohong.
     */
    public function test_override_tenggat_melahirkan_header_bertanggal_berbeda(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Override');
        $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Override'),
            [
                'groups' => [['tipe' => 'Karyawan']],
                'schedule' => ['deadline_mode' => 'RELATIVE', 'deadline_days' => 7],
            ],
            [
                $this->baris($d->idPaket, 1),
                $this->baris($b['paket'], 2, true, ['deadline_mode' => 'RELATIVE', 'deadline_days' => 30]),
            ],
            $d->userIdUji(),
        );

        $runId = $svc->queueActivation($ruleId, $d->userIdUji());
        $svc->applyQueued($runId, $ruleId, true, $d->userIdUji());

        $tenggat = DB::table('N_SOP_Penjadwalan')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->pluck('Tanggal_Deadline', 'Id_SOP_Paket')->all();

        $this->assertArrayHasKey($d->idPaket, $tenggat, 'Header paket induk tidak terbentuk.');
        $this->assertArrayHasKey($b['paket'], $tenggat, 'Header paket ber-override tidak terbentuk.');
        $this->assertNotEquals(
            (string) $tenggat[$d->idPaket],
            (string) $tenggat[$b['paket']],
            'Kedua paket berakhir dengan tenggat sama; override tidak dipakai.',
        );
    }

    /**
     * Aktivasi menuliskan Active_Hash yang ikut menyerap daftar paket, sehingga
     * menambah paket sesudahnya membuat draft terbaca "berubah".
     */
    public function test_menambah_paket_setelah_aktif_membuat_draft_terbaca_berubah(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Susulan');
        $d->terbitkanPaketLain($b['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Hash'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1)],
            $d->userIdUji(),
        );
        $runId = $svc->queueActivation($ruleId, $d->userIdUji());
        $svc->applyQueued($runId, $ruleId, true, $d->userIdUji());

        $svc->saveDraft(
            $d->nama('Aturan Hash'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1), $this->baris($b['paket'], 2)],
            $d->userIdUji(),
            $ruleId,
        );

        $aturan = collect($svc->list())->firstWhere('Id_SOP_Assignment_Rule',
            \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($ruleId));

        $this->assertNotNull($aturan, 'Aturan tidak muncul di daftar.');
        $this->assertTrue((bool) $aturan->Draft_Berubah,
            'Menambah paket tidak membuat draft terbaca berubah; hash tidak menyerap daftar paket.');
    }

    /**
     * PENJAGA. Pengecualian berbasis kriteria harus benar-benar mengurangi
     * cohort, bukan sekadar tersimpan di config.
     *
     * Pengecualiannya sengaja dibuat identik dengan kriteria targetnya, supaya
     * hasilnya tak bergantung pada isi basis data dev: apa pun yang cocok, ia
     * juga dikecualikan, jadi yang tersisa wajib nol.
     */
    public function test_pengecualian_berbasis_kriteria_mengurangi_cohort(): void
    {
        $d = $this->dunia();
        $svc = app(SopAssignmentRuleService::class);

        $tanpa = $svc->saveDraft(
            $d->nama('Aturan Tanpa Kecuali'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1)],
            $d->userIdUji(),
        );
        $cocokTanpa = $svc->preview($tanpa)['total']['matched'];

        $this->assertGreaterThan(0, $cocokTanpa,
            'Tidak ada karyawan bertipe Karyawan di basis data uji; uji ini tak bisa membuktikan apa pun.');

        $b = $d->paketTambahan('Paket Kecuali');
        $d->terbitkanPaketLain($b['paket']);

        $dengan = $svc->saveDraft(
            $d->nama('Aturan Dengan Kecuali'),
            [
                'groups' => [['tipe' => 'Karyawan']],
                'exclude_groups' => [['tipe' => 'Karyawan']],
            ],
            [$this->baris($b['paket'], 1)],
            $d->userIdUji(),
        );
        $hasil = $svc->preview($dengan)['total'];

        $this->assertSame(0, $hasil['matched'],
            'Grup pengecualian tidak mengurangi cohort sama sekali.');
        $this->assertSame($cocokTanpa, $hasil['excluded'],
            'Jumlah yang dikecualikan tidak dilaporkan, sehingga admin tak tahu berapa yang tersaring.');
    }

    /**
     * Pengecualian yang tak menyentuh siapa pun tidak boleh mengubah apa pun —
     * penjaga terhadap pengecualian yang diam-diam menyapu lebih luas dari
     * kriterianya.
     */
    public function test_pengecualian_yang_tak_cocok_siapa_pun_tidak_mengubah_cohort(): void
    {
        $d = $this->dunia();
        $svc = app(SopAssignmentRuleService::class);

        $tanpa = $svc->saveDraft(
            $d->nama('Aturan Acuan'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [$this->baris($d->idPaket, 1)],
            $d->userIdUji(),
        );
        $acuan = $svc->preview($tanpa)['total']['matched'];

        $b = $d->paketTambahan('Paket Acuan');
        $d->terbitkanPaketLain($b['paket']);

        $dengan = $svc->saveDraft(
            $d->nama('Aturan Kecuali Fiktif'),
            [
                'groups' => [['tipe' => 'Karyawan']],
                'exclude_groups' => [['lokasi' => ['LOKASI-YANG-TIDAK-PERNAH-ADA-XYZ']]],
            ],
            [$this->baris($b['paket'], 1)],
            $d->userIdUji(),
        );

        $this->assertSame($acuan, $svc->preview($dengan)['total']['matched'],
            'Pengecualian yang tak cocok siapa pun ikut mengurangi cohort.');
    }

    /**
     * matched dan excluded adalah angka ORANG, sama di setiap paket. Baris TOTAL
     * yang menjumlahkannya menuliskan lima kali headcount dan terbaca sebagai
     * jangkauan aturan.
     */
    public function test_total_tidak_mengalikan_jumlah_orang_dengan_jumlah_paket(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Kedua');
        $d->terbitkanPaketLain($b['paket']);
        $c = $d->paketTambahan('Paket Ketiga');
        $d->terbitkanPaketLain($c['paket']);
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Tiga Paket'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [
                $this->baris($d->idPaket, 1),
                $this->baris($b['paket'], 2),
                $this->baris($c['paket'], 3),
            ],
            $d->userIdUji(),
        );

        $pratinjau = $svc->preview($ruleId);

        $this->assertCount(3, $pratinjau['paket']);
        $this->assertSame(
            (int) $pratinjau['paket'][0]['matched'],
            (int) $pratinjau['total']['matched'],
            'Baris TOTAL mengalikan jumlah orang dengan jumlah paket.',
        );
    }
}
