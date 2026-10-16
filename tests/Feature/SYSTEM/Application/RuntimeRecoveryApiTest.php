<?php

namespace Tests\Feature\SYSTEM\Application;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Endpoint pemulihan runtime untuk operasional.
 *
 * DB DITIRU PARSIAL, bukan penuh. DB::shouldReceive() menukar seluruh
 * DatabaseManager, sehingga metode apa pun yang tak disebutkan meledak — dan
 * setiap permintaan HTTP melewati middleware RequestDiagnostics yang memanggil
 * DB::listen(). Akibatnya ketiga uji ini menjawab 500 dengan
 * BadMethodCallException, bukan status yang diperiksa, sejak middleware itu
 * dipasang.
 *
 * Yang dipakai TETAP tiruan penuh, ditambah satu harapan longgar untuk listen().
 * partialMock() terlihat lebih rapi tapi tidak bisa: ia membuat tiruan tanpa
 * argumen konstruktor, sehingga $app di dalam DatabaseManager null dan setiap
 * metode yang diteruskan meledak pada "array offset on null" — jauh dari
 * penyebabnya.
 */
class RuntimeRecoveryApiTest extends TestCase
{
    private const OPS_TOKEN = 'ops-token-for-test';

    /**
     * Izinkan DB::listen() dipanggil tanpa memeriksa berapa kali.
     *
     * Middleware RequestDiagnostics memanggilnya pada SETIAP permintaan untuk
     * mencatat lama kueri. Tanpa harapan ini, tiruan penuh menolaknya dengan
     * BadMethodCallException dan jawabannya menjadi 500 — bukan status yang
     * sedang diperiksa uji ini.
     */
    private function diamkanPendengarKueri(): void
    {
        DB::shouldReceive('listen')->zeroOrMoreTimes();
    }

    /**
     * Izinkan middleware SetDatabaseLockTimeout memasang batasnya.
     *
     * Middleware itu memanggil `DB::connection()` TANPA ARGUMEN lalu
     * `beforeExecuting()` pada hasilnya. Uji di bawah hanya berharap
     * `connection('sqlsrv')`, jadi panggilan tanpa argumen itu tak cocok dengan
     * harapan mana pun dan tiruan penuh menolaknya — jawabannya jadi 500, bukan
     * status yang sedang diperiksa.
     *
     * Ini alasan yang sama persis dengan diamkanPendengarKueri(), hanya
     * middleware yang berbeda: keduanya lahir belakangan dan mematahkan uji yang
     * sudah ada tanpa menyentuh endpointnya sama sekali. Endpointnya sendiri
     * sehat — dipanggil lewat kernel HTTP sungguhan ia menjawab 200.
     *
     * Callback beforeExecuting() tak pernah berjalan di sini karena tak ada kueri
     * sungguhan yang dieksekusi; cukup diterima, tak perlu ditiru isinya.
     */
    private function diamkanBatasLockKoneksi(): void
    {
        /* Tiruannya WAJIB bertipe Connection: pasangSaatKueriPertama()
           men-type-hint kelas itu, jadi tiruan polos ditolak TypeError sebelum
           sempat menerima beforeExecuting(). */
        $koneksiMiddleware = Mockery::mock(\Illuminate\Database\Connection::class);
        $koneksiMiddleware->shouldReceive('beforeExecuting')->zeroOrMoreTimes();

        DB::shouldReceive('connection')
            ->withNoArgs()
            ->zeroOrMoreTimes()
            ->andReturn($koneksiMiddleware);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.project_config.OPS_API_BEARER_TOKEN', self::OPS_TOKEN);
    }

    public function test_db_health_returns_403_without_bearer_token(): void
    {
        $response = $this->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(403)->assertJson([
            'status' => 'forbidden',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_db_health_returns_403_with_invalid_bearer_token(): void
    {
        $response = $this
            ->withHeaders($this->authHeaders('invalid-token'))
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(403)->assertJson([
            'status' => 'forbidden',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_db_health_returns_ok_for_healthy_connection(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andReturn(new \stdClass);

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        $this->diamkanPendengarKueri();
        $this->diamkanBatasLockKoneksi();
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_db_health_returns_503_when_connection_fails(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andThrow(new \RuntimeException('Login timeout expired'));

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        $this->diamkanPendengarKueri();
        $this->diamkanBatasLockKoneksi();
        DB::shouldReceive('connection')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->getJson('/api/ops/runtime/db-health');

        $response->assertStatus(503)->assertJson([
            'status' => 'error',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_db_reconnect_returns_ok_after_purge_and_reconnect(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getPdo')->once()->andReturn(new \stdClass);

        DB::shouldReceive('getDefaultConnection')->once()->andReturn('sqlsrv');
        $this->diamkanPendengarKueri();
        $this->diamkanBatasLockKoneksi();
        DB::shouldReceive('purge')->once()->with('sqlsrv');
        DB::shouldReceive('reconnect')->once()->with('sqlsrv')->andReturn($connection);

        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/ops/runtime/db-reconnect');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'connection' => 'sqlsrv',
        ]);
    }

    public function test_laravel_refresh_calls_optimize_clear(): void
    {
        Artisan::shouldReceive('call')->once()->with('optimize:clear')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('Caches cleared successfully.');

        $response = $this
            ->withHeaders($this->authHeaders())
            ->postJson('/api/ops/runtime/laravel-refresh');

        $response->assertStatus(200)->assertJson([
            'status' => 'ok',
            'command' => 'optimize:clear',
        ]);
    }

    private function authHeaders(?string $token = null): array
    {
        return [
            'Authorization' => 'Bearer '.($token ?? self::OPS_TOKEN),
        ];
    }
}
