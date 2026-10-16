<?php

namespace Tests\Feature\KPI\Approval;

use App\Services\KPI\Approval\KpiApprovalService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * getApproverFlowBatch() harus SETARA dengan getApproverFlow() satu per satu.
 *
 * Jalur batch ada murni demi jumlah query: grid tim memanggil resolusi approver
 * sekali per anggota, dan tiap panggilan membaca ulang config approval yang
 * sama. Karena ia menggandakan logika penyusunan approver, satu-satunya
 * penjaga yang berarti adalah membandingkan hasilnya dengan jalur tunggal —
 * kalau keduanya berbeda, yang batch-lah yang salah.
 */
class ApproverFlowBatchSetaraTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int, string> */
    private function kodeKaryawanUji(int $jumlah = 20): array
    {
        return DB::table('KPI_Karyawan_Team')
            ->where('Flag_Aktif', 'Y')
            ->limit($jumlah)
            ->pluck('Kode_Karyawan_Team')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function test_batch_menghasilkan_approver_yang_sama_dengan_jalur_tunggal(): void
    {
        $kode = $this->kodeKaryawanUji();

        if (count($kode) < 2) {
            $this->markTestSkipped('Data dev tidak punya cukup anggota tim.');
        }

        $service = app(ApprovalFlowService::class);
        $batch = $service->getApproverFlowBatch($kode, KpiApprovalService::CONFIG_PARAM);

        $adaYangBerisi = 0;

        foreach ($kode as $k) {
            $tunggal = collect($service->getApproverFlow($k, KpiApprovalService::CONFIG_PARAM))
                ->map(fn ($a) => (array) $a)
                ->values()
                ->all();

            $dariBatch = collect($batch[$k] ?? [])
                ->map(fn ($a) => (array) $a)
                ->values()
                ->all();

            $this->assertSame(
                $tunggal,
                $dariBatch,
                "Approver karyawan {$k} berbeda antara jalur tunggal dan batch.",
            );

            if (! empty($tunggal)) {
                $adaYangBerisi++;
            }
        }

        // Tanpa penjaga ini tes bisa lolos secara hampa: kalau SEMUA karyawan
        // kebetulan tidak punya approver, membandingkan kosong dengan kosong
        // tidak membuktikan apa pun.
        $this->assertGreaterThan(
            0,
            $adaYangBerisi,
            'Tidak ada satu pun karyawan uji yang punya approver, '
                .'jadi perbandingan ini tidak membuktikan kesetaraan.',
        );
    }

    public function test_daftar_kosong_tidak_menyentuh_database(): void
    {
        $jumlah = 0;
        DB::listen(function () use (&$jumlah) {
            $jumlah++;
        });

        $hasil = app(ApprovalFlowService::class)
            ->getApproverFlowBatch([], KpiApprovalService::CONFIG_PARAM);

        $this->assertSame([], $hasil);
        $this->assertSame(0, $jumlah, 'Daftar kosong seharusnya pulang tanpa query apa pun.');
    }

    public function test_kode_duplikat_tidak_menggandakan_hasil(): void
    {
        $kode = $this->kodeKaryawanUji(3);

        if (empty($kode)) {
            $this->markTestSkipped('Data dev tidak punya anggota tim.');
        }

        $satu = $kode[0];

        $hasil = app(ApprovalFlowService::class)->getApproverFlowBatch(
            [$satu, $satu, $satu],
            KpiApprovalService::CONFIG_PARAM,
        );

        $this->assertCount(1, $hasil, 'Kode yang sama dikirim tiga kali seharusnya menghasilkan satu entri.');

        $tunggal = collect(app(ApprovalFlowService::class)->getApproverFlow($satu, KpiApprovalService::CONFIG_PARAM))
            ->map(fn ($a) => (array) $a)
            ->values()
            ->all();

        $this->assertSame(
            $tunggal,
            collect($hasil[$satu])->map(fn ($a) => (array) $a)->values()->all(),
        );
    }
}
