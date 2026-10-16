<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Http\Middleware\HrisApiGuard;
use App\Http\Middleware\LmsApiGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Nonce kembar harus dijawab 401 "Duplicate request", bukan 500.
 *
 * ── Kenapa ──────────────────────────────────────────────────────────────────
 *
 * Gerbang memeriksa "nonce ini sudah pernah dipakai?" lalu menyimpannya dengan
 * pernyataan TERPISAH. Dua permintaan bernonce sama yang datang bersamaan
 * karena itu sama-sama lolos pemeriksaan — anti-replay-nya hanya berlaku untuk
 * pengulangan yang berjarak. Yang benar-benar menutup celah adalah indeks
 * UNIQUE di database:
 *
 *   N_HRIS_Api_Nonce   UQ_N_HRIS_Api_Nonce (Nonce)   — sudah ada sejak awal
 *   N_LMS_Api_Nonces   UQ_N_LMS_Api_Nonces_Nonce     — DDL-2026-095
 *
 * Tapi indeks itu hanya berguna kalau pelanggarannya diterjemahkan jadi jawaban
 * yang benar. Sebelum perbaikan ini, INSERT yang ditolak database melempar
 * QueryException, ditangkap catch(\Throwable) di handle(), lalu keluar sebagai
 * 500 "Security System Failure" — pihak seberang akan menyimpulkan HCIS rusak
 * dan mencoba lagi, padahal itu penolakan anti-replay yang wajar.
 *
 * ── Yang dijaga ─────────────────────────────────────────────────────────────
 *
 * Diuji lewat EFEK, bukan lewat bentuk kode: nonce disimpan sungguhan ke tabel
 * nyata, lalu disimpan lagi dengan nilai yang sama. Kalau indeks UNIQUE-nya
 * belum ada di lingkungan ini, uji ditandai skip dan berteriak — bukan diam-diam
 * hijau karena tak ada yang menolak.
 */
class GerbangApiNonceKembarTest extends TestCase
{
    use DatabaseTransactions;

    /** @dataProvider registry */
    public function test_nonce_kembar_dijawab_401(string $kelas, string $tabel, string $indeks): void
    {
        $this->pastikanUnik($tabel, $indeks);

        $gerbang = new $kelas;
        $nonce = 'UJI-'.bin2hex(random_bytes(12));
        $aplikasi = $this->idAplikasiApaPun($tabel);

        $pertama = $gerbang->simpanNonce($aplikasi, $nonce);
        $this->assertNull($pertama, 'Nonce pertama seharusnya diterima tanpa penolakan.');

        $kedua = $gerbang->simpanNonce($aplikasi, $nonce);

        $this->assertInstanceOf(
            Response::class,
            $kedua,
            'Nonce kembar diteruskan sebagai exception — pemanggil akan menerima 500, bukan penolakan replay.',
        );
        $this->assertSame(401, $kedua->getStatusCode());
        $this->assertStringContainsString('Duplicate request', (string) $kedua->getContent());

        $this->assertSame(
            1,
            DB::table($tabel)->where('Nonce', $nonce)->count(),
            'Nonce yang sama tersimpan lebih dari sekali — anti-replay bocor.',
        );
    }

    /** @dataProvider registry */
    public function test_galat_lain_tidak_disamarkan_jadi_duplicate(string $kelas): void
    {
        $gerbang = new $kelas;

        // Galat non-unik (mis. kolom tak dikenal, koneksi putus) tidak boleh ikut
        // diterjemahkan menjadi "Duplicate request": itu menyembunyikan kerusakan
        // nyata di balik penolakan yang terdengar wajar.
        $palsu = new \Illuminate\Database\QueryException('sqlsrv', 'select 1', [], new \RuntimeException('x'));
        $palsu->errorInfo = ['42S22', 207, 'Invalid column name'];

        $this->assertFalse($gerbang->tabrakanUnik($palsu));
    }

    public static function registry(): array
    {
        return [
            'LMS' => [LmsApiGuardTerbuka::class, 'N_LMS_Api_Nonces', 'UQ_N_LMS_Api_Nonces_Nonce'],
            'HRIS' => [HrisApiGuardTerbuka::class, 'N_HRIS_Api_Nonce', 'UQ_N_HRIS_Api_Nonce'],
        ];
    }

    private function pastikanUnik(string $tabel, string $indeks): void
    {
        $ada = DB::selectOne(
            'SELECT 1 AS ada FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?) AND is_unique = 1',
            [$indeks, $tabel],
        );

        if (! $ada) {
            $this->markTestSkipped(
                "{$indeks} belum ada di lingkungan ini — jalankan DDL-2026-095 lebih dulu. ".
                'Tanpa indeks itu anti-replay hanya berlaku untuk pengulangan yang berjarak.',
            );
        }
    }

    private function idAplikasiApaPun(string $tabel): int
    {
        $kolom = $tabel === 'N_LMS_Api_Nonces' ? 'N_LMS_Api_Aplikasi' : 'N_HRIS_Api_Aplikasi';
        $id = DB::table($kolom)->value('Id_Api_Aplikasi');

        if (! $id) {
            $this->markTestSkipped("Tidak ada baris di {$kolom}.");
        }

        return (int) $id;
    }
}

/** Membuka simpanNonce()/tabrakanUnik() untuk diuji lewat efek nyata. */
class LmsApiGuardTerbuka extends LmsApiGuard
{
    public function simpanNonce(int $idAplikasi, string $nonce): ?Response
    {
        return parent::simpanNonce($idAplikasi, $nonce);
    }

    public function tabrakanUnik(\Illuminate\Database\QueryException $e): bool
    {
        return parent::tabrakanUnik($e);
    }
}

class HrisApiGuardTerbuka extends HrisApiGuard
{
    public function simpanNonce(int $idAplikasi, string $nonce): ?Response
    {
        return parent::simpanNonce($idAplikasi, $nonce);
    }

    public function tabrakanUnik(\Illuminate\Database\QueryException $e): bool
    {
        return parent::tabrakanUnik($e);
    }
}
