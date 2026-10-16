<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminKebijakanController;
use App\Http\Controllers\LMS\SOP\Governance\SopAdminPenjadwalanController;
use App\Services\LMS\SOP\SopPenjadwalanService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopPenjadwalanServiceTest extends TestCase
{
    public function test_published_package_options_match_the_live_table_without_extra_queries(): void
    {
        $expected = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status', 'PUBLISHED')
            ->orderBy('p.Nama_Paket')
            ->get(['p.Nama_Paket', 'p.Prioritas']);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $actual = app(SopPenjadwalanService::class)->paketPublished();

        $this->assertLessThanOrEqual(2, $queries);
        $this->assertNotEmpty($expected, 'Tak ada paket terbit — uji ini tak menguji apa pun.');
        $this->assertCount($expected->count(), $actual['items']);
        $this->assertSame(
            $expected->pluck('Prioritas', 'Nama_Paket')->all(),
            $actual['items']->pluck('Prioritas', 'Nama_Paket')->all(),
        );
    }

    public function test_package_options_stop_at_the_safety_cap_and_report_truncation(): void
    {
        $tersedia = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', 'Y')
            ->where('Status', 'PUBLISHED')
            ->count();

        $this->assertGreaterThan(1, $tersedia, 'Butuh minimal 2 paket published untuk menguji pemotongan.');

        $batas = $tersedia - 1;

        $svc = app(SopPenjadwalanService::class);

        /* DIUKUR SEBAGAI SELISIH, BUKAN SEBAGAI TOTAL.
           Klaim yang dijaga adalah "mendeteksi pemotongan tidak menambah
           query" — ditempuh dengan mengambil satu baris lebih dari batas,
           bukan dengan COUNT terpisah. Versi sebelumnya menuntut TOTAL query
           <= 1, dan itu patah ketika komposisi item ditambahkan: query kedua
           itu milik fitur lain, bukan biaya deteksinya. Uji yang mengukur
           besaran yang salah akan memerahkan setiap fitur berikutnya di jalur
           ini, sampai akhirnya seseorang melonggarkan angkanya dan klaim
           aslinya hilang tanpa pernah dilanggar. */
        $hitung = function (int $b) use ($svc): array {
            $n = 0;
            DB::listen(function () use (&$n): void {
                $n++;
            });
            $hasil = $svc->paketPublished($b);

            return [$n, $hasil];
        };

        [$queryTerpotong, $actual] = $hitung($batas);
        [$queryUtuh] = $hitung($tersedia);

        $this->assertCount($batas, $actual['items']);
        $this->assertTrue($actual['terpotong']);
        $this->assertSame($batas, $actual['batas']);
        $this->assertSame($queryUtuh, $queryTerpotong,
            'Deteksi pemotongan tidak boleh menambah query: jumlahnya harus sama '
            .'baik daftarnya terpotong maupun utuh.');
    }

    public function test_package_options_are_not_flagged_when_everything_fits(): void
    {
        $tersedia = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', 'Y')
            ->where('Status', 'PUBLISHED')
            ->count();

        $actual = app(SopPenjadwalanService::class)->paketPublished($tersedia);

        $this->assertCount($tersedia, $actual['items']);
        $this->assertFalse($actual['terpotong']);
    }

    public function test_package_options_endpoint_keeps_result_a_flat_list_for_existing_consumers(): void
    {
        $payload = json_decode(
            app(SopAdminPenjadwalanController::class)->optionsPaket()->getContent(),
            true,
        );

        $this->assertTrue($payload['success']);
        $this->assertArrayHasKey('result', $payload);
        $this->assertIsList($payload['result'], 'SopPenjadwalan.vue & SopMonitoring.vue membaca result sebagai array.');
        $this->assertArrayHasKey('Nama_Paket', $payload['result'][0]);
    }

    public function test_package_options_endpoint_exposes_truncation_flag_alongside_result(): void
    {
        $payload = json_decode(
            app(SopAdminPenjadwalanController::class)->optionsPaket()->getContent(),
            true,
        );

        $this->assertArrayHasKey('terpotong', $payload);
        $this->assertFalse($payload['terpotong'], 'Data dev jauh di bawah batas, seharusnya tidak terpotong.');
        $this->assertSame(SopPenjadwalanService::BATAS_OPSI_PAKET, $payload['batas']);
    }

    public function test_policy_options_endpoint_still_exposes_packages_as_a_flat_list(): void
    {
        $payload = json_decode(
            app(SopAdminKebijakanController::class)->options()->getContent(),
            true,
        );

        $this->assertTrue($payload['success'], 'Endpoint opsi kebijakan tidak boleh error.');
        $this->assertIsList($payload['result']['packages']);
        $this->assertArrayHasKey('Nama_Paket', $payload['result']['packages'][0]);
    }

    public function test_draft_status_is_loaded_only_for_the_selected_package(): void
    {
        $idPaket = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', 'Y')
            ->where('Status', 'PUBLISHED')
            ->value('Id_SOP_Paket');

        $this->assertNotNull($idPaket);

        $expected = DB::table('N_SOP_Paket_Draft')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Status', 'DRAFT')
            ->exists();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $actual = app(SopPenjadwalanService::class)->paketMemilikiDraft((int) $idPaket);

        $this->assertSame($expected, $actual);
        $this->assertLessThanOrEqual(1, $queries);
    }
}
