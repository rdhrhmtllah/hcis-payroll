<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusPengumpulanServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const KODE = 'UJI-PD-001';

    private function service(): StatusPengumpulanService
    {
        return app(StatusPengumpulanService::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', self::KODE)->delete();
        $this->service()->bersihkanCache(self::KODE);
    }

    public function test_karyawan_baru_berstatus_belum_dengan_jatah_penuh(): void
    {
        $status = $this->service()->untuk(self::KODE);

        $this->assertSame('BELUM', $status['status']);
        $this->assertSame(0, $status['jumlah_tunda']);
        $this->assertSame(3, $status['sisa_tunda']);
        $this->assertFalse($status['terkunci']);
    }

    public function test_penundaan_ketiga_mengunci_dan_penundaan_berikutnya_tidak_menambah(): void
    {
        $this->service()->catatTunda(self::KODE);
        $this->service()->catatTunda(self::KODE);
        $akhir = $this->service()->catatTunda(self::KODE);

        $this->assertSame('TERKUNCI', $akhir['status']);
        $this->assertSame(0, $akhir['sisa_tunda']);

        $lagi = $this->service()->catatTunda(self::KODE);

        $this->assertSame(3, $lagi['jumlah_tunda'], 'Jatah tidak boleh melewati maksimum.');
        $this->assertSame('TERKUNCI', $lagi['status']);
    }

    public function test_draf_tersimpan_dan_terbaca_kembali(): void
    {
        $this->service()->simpanDraft(self::KODE, ['email' => 'a@b.co'], 2);

        $draf = $this->service()->ambilDraft(self::KODE);

        $this->assertSame(['email' => 'a@b.co'], $draf['data']);
        $this->assertSame(2, $draf['langkah']);
    }

    public function test_selesai_menutup_status_selamanya(): void
    {
        $this->service()->catatTunda(self::KODE);
        $this->service()->tandaiSelesai(self::KODE);

        $status = $this->service()->untuk(self::KODE);

        $this->assertSame('SELESAI', $status['status']);
        $this->assertFalse($status['terkunci']);
    }

    public function test_karyawan_dikecualikan_ditandai(): void
    {
        $this->service()->untuk(self::KODE);
        $this->service()->simpanDraft(self::KODE, [], 0); // pastikan baris ada

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', self::KODE)
            ->update(['Is_Dikecualikan' => 'Y']);
        $this->service()->bersihkanCache(self::KODE);

        $this->assertTrue($this->service()->untuk(self::KODE)['is_dikecualikan']);
    }
}
