<?php

namespace Tests\Feature\VISITOR\Governance\MasterData;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Gerbang dan penjaga pintu import master data permit.
 *
 * Import menulis ke tujuh master sekaligus. Menggerbangi halamannya saja hanya
 * menyembunyikan tombol: endpointnya tetap bisa dipanggil langsung, jadi
 * kuncinya harus sama persis dengan kunci halamannya — dan pintu eksekusi
 * harus menolak berkas yang tidak pernah melewati pratinjau.
 */
class ImportMasterDataPermitRouteTest extends TestCase
{
    use DatabaseTransactions;

    private const KUNCI = 'visitorMasterDataPermitPage';

    private const PRATINJAU = 'http://localhost/api/v1/visitor/master-data/import/permit/pratinjau';

    private const JALANKAN = 'http://localhost/api/v1/visitor/master-data/import/permit';

    private array $berkasSementara = [];

    protected function tearDown(): void
    {
        foreach ($this->berkasSementara as $berkas) {
            @unlink($berkas);
        }

        parent::tearDown();
    }

    public static function jalurImport(): array
    {
        return [
            'pratinjau' => ['/api/v1/visitor/master-data/import/permit/pratinjau'],
            'jalankan' => ['/api/v1/visitor/master-data/import/permit'],
        ];
    }

    /**
     * @dataProvider jalurImport
     */
    public function test_kedua_pintu_dijaga_kunci_halaman_permit(string $jalur): void
    {
        $rute = Route::getRoutes()->match(Request::create($jalur, 'POST'));
        $middleware = $rute->gatherMiddleware();

        $this->assertContains('auth', $middleware);
        $this->assertContains('check.jabatan:'.self::KUNCI, $middleware);
        $this->assertSame('permit', $rute->defaults['domain'] ?? null);
    }

    public function test_tanpa_login_ditolak(): void
    {
        $this->postJson(self::PRATINJAU, ['berkas' => $this->unggahan()])->assertStatus(401);
    }

    public function test_sidik_yang_tidak_cocok_membatalkan_eksekusi(): void
    {
        // Tanpa penjaga ini, dry run cuma ritual: yang ditulis bisa berkas lain
        // yang laporannya tidak pernah dilihat siapa pun.
        $sebelum = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->count();

        $this->actingAs($this->adminPermit())
            ->post(self::JALANKAN, [
                'berkas' => $this->unggahan(),
                'sidik' => str_repeat('a', 64),
            ], ['Accept' => 'application/json'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'VISITOR_MASTER_IMPORT_STALE');

        $this->assertSame($sebelum, DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->count());
    }

    public function test_pratinjau_membalas_sidik_yang_dipakai_pintu_eksekusi(): void
    {
        $respons = $this->actingAs($this->adminPermit())
            ->post(self::PRATINJAU, ['berkas' => $this->unggahan()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('result.dijalankan', false);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $respons->json('result.sidik'));
    }

    public function test_berkas_selain_xlsx_ditolak_sebelum_dibaca(): void
    {
        $this->actingAs($this->adminPermit())
            ->post(self::PRATINJAU, [
                'berkas' => UploadedFile::fake()->createWithContent('master.csv', 'Warna Kendaraan,Status'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('berkas');
    }

    private function adminPermit(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', self::KUNCI)
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->markTestSkipped('Tak ada user aktif dengan '.self::KUNCI.' di database development.');
        }

        return $user;
    }

    private function unggahan(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Warna Kendaraan');
        $spreadsheet->getActiveSheet()->fromArray([
            ['Warna Kendaraan', 'Status'],
            ['UJI WARNA '.strtoupper(bin2hex(random_bytes(4))), 'Aktif'],
        ], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'visitor-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->berkasSementara[] = $path;

        return new UploadedFile($path, 'master-data-permit.xlsx', null, null, true);
    }
}
