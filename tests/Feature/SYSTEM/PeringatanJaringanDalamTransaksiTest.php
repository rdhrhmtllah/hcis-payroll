<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Application\Diagnostics\PeringatanJaringanDalamTransaksi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Penjaga runtime: panggilan jaringan di dalam transaksi harus TERLIHAT.
 *
 * Pemindai statis terbukti alat yang salah untuk cacat ini. Penyisiran atas 70
 * titik `lockForUpdate` menyisakan 3 kandidat setelah batas metode diperbaiki,
 * dan KETIGANYA positif palsu — sementara satu-satunya cacat yang nyata
 * (temuan #18, `createTask()` ke Cloud Tasks sambil memegang kunci) tidak
 * muncul sama sekali, karena panggilannya bersarang dua lapis ke dalam.
 *
 * Runtime tahu apa yang pemindai tak bisa tahu.
 */
class PeringatanJaringanDalamTransaksiTest extends TestCase
{
    public function test_diam_saja_ketika_tidak_ada_transaksi(): void
    {
        Log::spy();

        $hasil = PeringatanJaringanDalamTransaksi::periksa('uji tanpa transaksi');

        $this->assertFalse($hasil, 'Melaporkan ada transaksi padahal tidak ada.');
        Log::shouldNotHaveReceived('warning');
    }

    public function test_memperingatkan_ketika_di_dalam_transaksi(): void
    {
        Log::spy();

        DB::beginTransaction();
        try {
            $hasil = PeringatanJaringanDalamTransaksi::periksa('uji di dalam transaksi', ['id' => 7]);
        } finally {
            DB::rollBack();
        }

        $this->assertTrue($hasil, 'Tidak mengenali bahwa sedang di dalam transaksi.');
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Sedalam apa pun bersarangnya, tetap terlihat.
     *
     * Inilah alasan penjaga ini ada: temuan #18 tersembunyi di balik dua lapis
     * pemanggilan, dan pemindai statis melewatkannya. Bila uji ini bisa lolos
     * dengan pemeriksaan yang hanya melihat satu tingkat, penjaganya tak lebih
     * baik daripada pemindai yang digantikannya.
     */
    public function test_terlihat_walau_bersarang_beberapa_lapis(): void
    {
        Log::spy();

        $lapisTerdalam = fn () => PeringatanJaringanDalamTransaksi::periksa('bersarang');
        $lapisTengah = fn () => $lapisTerdalam();
        $lapisLuar = fn () => $lapisTengah();

        DB::beginTransaction();
        try {
            $hasil = $lapisLuar();
        } finally {
            DB::rollBack();
        }

        $this->assertTrue($hasil, 'Panggilan bersarang tidak terdeteksi — penjaga ini kehilangan alasan keberadaannya.');
        Log::shouldHaveReceived('warning')->once();
    }

    /**
     * Log harus menunjuk PEMANGGILNYA, bukan kelas penjaga itu sendiri.
     *
     * Tanpa ini, peringatannya benar tapi tak menolong: ia hanya memberi tahu
     * bahwa sesuatu terjadi, tanpa memberi tahu transaksi mana yang bersalah.
     */
    public function test_log_menunjuk_berkas_pemanggil(): void
    {
        $tercatat = [];
        Log::listen(function ($pesan) use (&$tercatat) {
            $tercatat[] = $pesan;
        });

        DB::beginTransaction();
        try {
            PeringatanJaringanDalamTransaksi::periksa('uji pemanggil');
        } finally {
            DB::rollBack();
        }

        $peringatan = array_values(array_filter(
            $tercatat,
            fn ($p) => $p->level === 'warning' && str_contains($p->message, 'DI DALAM transaksi')
        ));

        $this->assertNotEmpty($peringatan, 'Peringatannya tidak tercatat sama sekali.');

        $pemanggil = (string) ($peringatan[0]->context['pemanggil'] ?? '');
        $this->assertStringContainsString(
            'PeringatanJaringanDalamTransaksiTest',
            $pemanggil,
            "Log menunjuk '{$pemanggil}', bukan berkas pemanggilnya. Peringatan yang tak menunjuk "
            .'siapa pun tidak menolong siapa pun.'
        );
    }
}
