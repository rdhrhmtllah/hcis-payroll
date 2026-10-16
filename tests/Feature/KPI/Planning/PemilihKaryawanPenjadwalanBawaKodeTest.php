<?php

namespace Tests\Feature\KPI\Planning;

use App\Http\Controllers\KPI\Planning\Scheduling\PenjadwalanKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pemilih karyawan di penjadwalan KPI harus membawa kode karyawan.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Kartu pemilih menampilkan nama, level, dan divisi. Ketika ketiganya kebetulan
 * sama — dan di daftar sepanjang 900-an orang itu bukan hal aneh — admin tak
 * punya apa pun untuk membedakan dua kandidat, sementara yang dicentang
 * menentukan siapa yang masuk penjadwalan. Salahnya tidak melempar galat; ia
 * hanya menjadwalkan orang yang keliru.
 *
 * Kodenya sebenarnya SUDAH ada di data yang dikirim ke halaman — dipakai untuk
 * :key dan untuk membuang duplikat — hanya tak pernah ditampilkan.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * KEDUA sumber pemilih memulangkan kode_karyawan yang terisi. Dijaga bersama
 * karena keduanya mengisi kartu yang sama: satu untuk penjadwalan biasa, satu
 * untuk kasus "belum ada KPI". Kalau hanya satu yang dijaga, yang lain bebas
 * menyimpang tanpa ketahuan.
 */
class PemilihKaryawanPenjadwalanBawaKodeTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> */
    private function baris(string $metode, array $query): array
    {
        $respons = app(PenjadwalanKpiController::class)->{$metode}(
            Request::create('/api/v1/penjadwalan-kpi', 'GET', $query),
        );

        $isi = json_decode($respons->getContent(), true);

        return $isi['result'] ?? [];
    }

    private function tegaskanBerkode(array $baris, string $asal): void
    {
        if ($baris === []) {
            $this->markTestSkipped("Sumber {$asal} kosong pada data ini.");
        }

        foreach (array_slice($baris, 0, 20) as $b) {
            $this->assertArrayHasKey(
                'kode_karyawan',
                $b,
                "Sumber {$asal} tidak membawa kode_karyawan; dua kandidat dengan nama, level, ".
                'dan divisi yang sama tak bisa dibedakan di kartu pemilih.',
            );
            $this->assertNotEmpty(
                trim((string) $b['kode_karyawan']),
                "Sumber {$asal} membawa kode_karyawan kosong — sama saja tak ada pembeda.",
            );
        }
    }

    public function test_pemilih_penjadwalan_biasa_membawa_kode(): void
    {
        $this->tegaskanBerkode(
            $this->baris('getDataKaryawan', ['periode' => (int) date('n'), 'tahun' => date('Y')]),
            'getDataKaryawan',
        );
    }

    public function test_pemilih_belum_ada_kpi_membawa_kode(): void
    {
        $this->tegaskanBerkode(
            $this->baris('getDataKaryawanBelumAdaKpi', ['tahun' => date('Y')]),
            'getDataKaryawanBelumAdaKpi',
        );
    }
}
