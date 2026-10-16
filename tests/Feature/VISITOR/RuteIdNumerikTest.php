<?php

namespace Tests\Feature\VISITOR;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `{id}` yang dimasukkan ke kolom int wajib numerik di tingkat rute: SQL Server
 * menolak konversi 'abc' ke int, dan permintaan itu berakhir 500 alih-alih 404
 * (audit ulang 28 Sep).
 */
class RuteIdNumerikTest extends TestCase
{
    public function test_rute_ber_id_int_hanya_menerima_angka(): void
    {
        foreach ([
            'transaksipermit/{id}/cancel',
            'transaksipermit/{id}/pindah-inspeksi',
            'transaksipermit/{id}/kartu-pdf',
            'transaksitamu/cancel/{id}',
            'transaksi-tamu-b2b/cancel/{id}',
        ] as $uri) {
            $rute = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri);
            $this->assertNotNull($rute, "Rute {$uri} tidak ditemukan.");
            $this->assertSame('[0-9]+', $rute->wheres['id'] ?? null, "Rute {$uri} menerima id non-angka.");
        }
    }
}
