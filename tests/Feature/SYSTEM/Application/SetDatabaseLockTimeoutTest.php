<?php

namespace Tests\Feature\SYSTEM\Application;

use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Batas lock & durasi kueri untuk request HTTP interaktif.
 *
 * Middleware ini adalah jaring pengaman terhadap kaskade saturasi koneksi yang
 * pernah menjatuhkan situs, jadi ia punya DUA sifat yang harus sama-sama benar
 * dan yang gampang saling menukar:
 *
 *  1. Request yang MENYENTUH database harus sudah berada di bawah batas itu
 *     sebelum kueri pertamanya jalan. Ini yang tak boleh melemah.
 *  2. Request yang TIDAK menyentuh database sama sekali tidak boleh membuka
 *     koneksi. Versi lama menjalankan SET LOCK_TIMEOUT di setiap request,
 *     sehingga halaman publik statis mengambil satu slot koneksi hanya untuk
 *     melindungi kueri yang tak pernah ada — penguat termurah bagi persis
 *     kaskade yang middleware ini cegah.
 *
 * Butuh DB dev: yang diuji adalah keadaan sesi SQL Server yang sungguhan.
 * Digerbangi env seperti uji dev lain di repo ini.
 *   RUN_DB_LOCK_TIMEOUT_TEST=true php artisan test --filter SetDatabaseLockTimeout
 */
class SetDatabaseLockTimeoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_DB_LOCK_TIMEOUT_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_DB_LOCK_TIMEOUT_TEST=true untuk menjalankan (butuh DB dev).');
        }

        // Tiap uji harus mulai dari koneksi yang belum terbuka, kalau tidak
        // "belum terhubung" hanyalah sisa keadaan uji sebelumnya.
        DB::purge();
    }

    private function sudahTerhubung(): bool
    {
        // getRawPdo() masih berupa Closure berarti penyelesai PDO belum pernah
        // dijalankan. Membandingkannya dengan null saja menghasilkan hijau palsu:
        // Closure bukan null, sehingga setiap uji akan lolos.
        return DB::connection()->getRawPdo() instanceof PDO;
    }

    /** @test */
    public function request_tanpa_kueri_tidak_membuka_koneksi(): void
    {
        // Halaman viewer kontrak murni statis: datanya array keras di dalam kode.
        // Tautannya bertanda tangan — pembuatannya pun tidak menyentuh DB, jadi
        // uji ini tetap mengukur apa yang dimaksudkannya.
        $tautan = app(\App\Services\SYSTEM\Integration\OutboundContractService::class)
            ->tautanViewer('web-careers-candidate-status');

        $this->get($tautan)->assertStatus(200);

        $this->assertFalse(
            $this->sudahTerhubung(),
            'Request yang tidak menjalankan satu kueri pun tetap membuka koneksi DB. '
            .'Itu satu slot pool per permintaan, pada endpoint publik tanpa auth.',
        );
    }

    /** @test */
    public function halaman_dokumentasi_juga_tidak_membuka_koneksi(): void
    {
        $this->get('/api/docs')->assertStatus(200);

        $this->assertFalse($this->sudahTerhubung());
    }

    /** @test */
    public function batas_lock_terpasang_sebelum_kueri_pertama(): void
    {
        // Sifat yang TIDAK boleh melemah. Jalur ini menyentuh registry, jadi ia
        // benar-benar berkueri.
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $this->getJson('/api/docs/openapi.json', ['X-HC-Public' => 'KUNCI_TIDAK_DIKENAL'])
            ->assertStatus(401);

        $this->assertNotEmpty($kueri, 'Jalur ini seharusnya berkueri; kalau tidak, uji ini tak menguji apa pun.');
        $this->assertStringStartsWith(
            'SET LOCK_TIMEOUT',
            $kueri[0],
            'Batasnya harus terpasang SEBELUM kueri pertama, bukan sesudahnya.',
        );
    }

    /** @test */
    public function nilai_lock_timeout_benar_benar_berlaku_di_sesi(): void
    {
        // Diuji lewat EFEK, bukan lewat teks SQL yang dikirim: @@LOCK_TIMEOUT
        // adalah keadaan sesi yang sungguhan. SET lewat prepared statement
        // mengirim teks yang sama persis tetapi TIDAK mempersist, dan hanya
        // pemeriksaan seperti inilah yang membedakannya.
        $this->getJson('/api/docs/openapi.json', ['X-HC-Public' => 'KUNCI_TIDAK_DIKENAL'])
            ->assertStatus(401);

        $this->assertSame(
            (int) env('DB_WEB_LOCK_TIMEOUT_MS', 10000),
            (int) DB::selectOne('SELECT @@LOCK_TIMEOUT AS nilai')->nilai,
        );
    }

    /** @test */
    public function penyetelan_hanya_terjadi_sekali_per_sesi(): void
    {
        // Menggantungkannya pada beforeExecuting berarti ia berpeluang jalan di
        // SETIAP kueri. Kalau penjagaannya lepas, satu halaman berisi 30 kueri
        // akan menjalankan 30 SET — mahal dan senyap.
        $set = 0;
        DB::listen(function ($q) use (&$set) {
            if (str_starts_with($q->sql, 'SET LOCK_TIMEOUT')) {
                $set++;
            }
        });

        $this->getJson('/api/docs/openapi.json', ['X-HC-Public' => 'KUNCI_TIDAK_DIKENAL'])->assertStatus(401);

        // Beberapa kueri lanjutan pada koneksi yang sama tidak boleh memicunya lagi.
        DB::select('SELECT 1 AS a');
        DB::select('SELECT 2 AS a');

        $this->assertSame(1, $set, 'SET LOCK_TIMEOUT dijalankan lebih dari sekali pada satu sesi.');
    }
}
