<?php

namespace Tests\Feature\SYSTEM;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemangkas baris cache kedaluwarsa.
 *
 * Yang dijaga di sini bukan "ada yang terhapus" melainkan BATASNYA: tabel
 * `cache` dipakai seluruh aplikasi, jadi command yang salah predikat akan
 * mengosongkan cache setiap modul sekaligus. Satuan kolom `expiration` adalah
 * Unix timestamp detik, bukan datetime — membandingkannya dengan now() apa
 * adanya menghapus semuanya, dan itulah kekeliruan yang uji ini tangkap.
 */
class CachePruneTest extends TestCase
{
    use DatabaseTransactions;

    private function tabel(): string
    {
        return (string) config('cache.stores.database.table', 'cache');
    }

    public function test_hanya_baris_kedaluwarsa_yang_dihapus(): void
    {
        $t = $this->tabel();

        DB::table($t)->insert([
            ['key' => 'uji_prune_mati', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()],
            ['key' => 'uji_prune_hidup', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('hris:cache-prune')->assertExitCode(0);

        $this->assertDatabaseMissing($t, ['key' => 'uji_prune_mati']);
        $this->assertDatabaseHas($t, ['key' => 'uji_prune_hidup']);
    }

    public function test_dry_run_tidak_menghapus_apa_pun(): void
    {
        $t = $this->tabel();

        DB::table($t)->insert([
            ['key' => 'uji_prune_dryrun', 'value' => 'x', 'expiration' => now()->subHour()->getTimestamp()],
        ]);

        $this->artisan('hris:cache-prune', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas($t, ['key' => 'uji_prune_dryrun']);
    }
}
