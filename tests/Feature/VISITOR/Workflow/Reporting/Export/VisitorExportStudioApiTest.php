<?php

namespace Tests\Feature\VISITOR\Workflow\Reporting\Export;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Read-only HTTP contract checks safe for the verified development database. */
class VisitorExportStudioApiTest extends TestCase
{
    private const BASE = 'http://localhost/api/v1/visitor/export-studio';

    private function reporter(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'visitorLaporanPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->markTestSkipped('Tak ada user aktif dengan visitorLaporanPage di database development.');
        }

        return $user;
    }

    public function test_unauthenticated_catalog_is_rejected(): void
    {
        $this->getJson(self::BASE.'/tamu/katalog/tamu')->assertStatus(401);
    }

    public function test_guest_catalog_has_stable_contract_and_sensitive_markers(): void
    {
        $response = $this->actingAs($this->reporter())
            ->getJson(self::BASE.'/tamu/katalog/tamu')
            ->assertOk()
            ->assertJsonPath('result.kunci', 'tamu')
            ->assertJsonStructure(['result' => ['label', 'grain', 'dimensi', 'ukuran', 'saringan', 'opsi_saringan']]);

        $dimensions = collect($response->json('result.dimensi'))->keyBy('key');
        $this->assertTrue($dimensions['no_hp']['sensitif']);
        $this->assertTrue($dimensions['no_identitas']['sensitif']);
    }

    public function test_permit_catalog_is_separate(): void
    {
        $this->actingAs($this->reporter())
            ->getJson(self::BASE.'/permit/katalog/permit')
            ->assertOk()
            ->assertJsonPath('result.kunci', 'permit');
    }

    public function test_route_and_definition_dataset_mismatch_is_rejected(): void
    {
        $this->actingAs($this->reporter())
            ->getJson(self::BASE.'/tamu/katalog/permit')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'REPORT_DATASET_UNKNOWN');
    }

    public function test_gallery_is_filtered_per_dataset(): void
    {
        $guest = $this->actingAs($this->reporter())
            ->getJson(self::BASE.'/tamu/template')
            ->assertOk()
            ->json('result.resmi');

        $this->assertCount(2, $guest);
        $this->assertSame(['tamu'], array_values(array_unique(array_column($guest, 'dataset'))));

        $permit = $this->getJson(self::BASE.'/permit/template')
            ->assertOk()
            ->json('result.resmi');

        $this->assertCount(2, $permit);
        $this->assertSame(['permit'], array_values(array_unique(array_column($permit, 'dataset'))));
    }

    public function test_official_guest_template_can_be_previewed_read_only(): void
    {
        $this->actingAs($this->reporter())
            ->postJson(self::BASE.'/tamu/pratinjau', [
                'template_kode' => 'visitor-tamu-rinci',
                'per_page' => 5,
                'hitung_total' => false,
            ])
            ->assertOk()
            ->assertJsonStructure(['result' => ['title', 'columns', 'rows', 'catatan']]);
    }

    public function test_official_permit_template_can_be_previewed_read_only(): void
    {
        $this->actingAs($this->reporter())
            ->postJson(self::BASE.'/permit/pratinjau', [
                'template_kode' => 'visitor-permit-rinci',
                'per_page' => 5,
                'hitung_total' => false,
            ])
            ->assertOk()
            ->assertJsonStructure(['result' => ['title', 'columns', 'rows', 'catatan']]);
    }
}
