<?php

namespace Tests\Feature\SYSTEM\Observability;

use App\Helpers\ResponseHelper;
use App\Http\Middleware\CatatGalatServer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * Setiap tanggapan 5xx harus meninggalkan jejak di log.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * Dilaporkan dari production: sebuah kolom belum ada di sana, layar menerima
 * 500, dan log tidak memuat apa pun. Penelusuran 16 September 2026 menemukan
 * sebabnya bukan di framework — 649 blok `catch` di repo ini menangkap
 * exception lalu memulangkan 500 SENDIRI, sehingga exception-nya tidak pernah
 * sampai ke App\Exceptions\Handler dan tidak pernah dicatat otomatis. Dari
 * jumlah itu 123 blok tidak mencatat apa pun (SYSTEM 82, ESS 27, LMS 13,
 * VISITOR 1), dan 248 lainnya mencatat ke kanal bernama sehingga masuk berkas
 * modulnya sendiri, bukan `laravel.log`.
 *
 * Yang membuatnya mahal bukan galatnya, melainkan diamnya: "Gagal mengambil
 * data" di layar dan halaman log kosong tidak memberi tahu siapa pun bahwa
 * yang sebenarnya terjadi adalah `Invalid column name`.
 *
 * ── Yang ditegaskan ─────────────────────────────────────────────────────────
 *
 * Bukan "middleware-nya terpasang", melainkan EFEKNYA pada tiga keadaan yang
 * membedakan perbaikan ini dari yang sebelumnya: 500 yang bisu tetap dicatat,
 * galat yang sudah dilaporkan Handler TIDAK dicatat dua kali, dan tanggapan di
 * bawah 500 tidak ikut mengotori log.
 */
class SetiapGalatServerBerjejakTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_lima_ratus_yang_bisu_tetap_meninggalkan_jejak(): void
    {
        Route::get('/uji/galat-bisu', fn () => ResponseHelper::error('Gagal mengambil data', 500));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $pesan, array $konteks) {
                return str_contains($pesan, '5xx')
                    && $konteks['status'] === 500
                    && $konteks['path'] === '/uji/galat-bisu'
                    && $konteks['pesan_ke_pengguna'] === 'Gagal mengambil data';
            });

        $this->get('/uji/galat-bisu')->assertStatus(500);
    }

    public function test_pesan_yang_dilihat_pengguna_ikut_tercatat(): void
    {
        // Jembatan antara keluhan ("muncul Gagal mengambil options") dan baris
        // lognya. Tanpa kalimat itu, admin melaporkan sesuatu yang tidak bisa
        // dicari di log.
        Route::get('/uji/galat-berpesan', fn () => ResponseHelper::error('Gagal mengambil options', 500));

        $tertangkap = [];
        Log::shouldReceive('error')->andReturnUsing(function ($pesan, $konteks) use (&$tertangkap) {
            $tertangkap[] = $konteks;
        });

        $this->get('/uji/galat-berpesan')->assertStatus(500);

        $this->assertNotEmpty($tertangkap, 'Tanggapan 500 tidak menghasilkan satu pun baris log.');
        $this->assertSame('Gagal mengambil options', $tertangkap[0]['pesan_ke_pengguna']);
    }

    public function test_galat_yang_sudah_dilaporkan_tidak_dicatat_dua_kali(): void
    {
        // Exception yang lolos ke Handler sudah dicatat lengkap dengan stack
        // trace. Kalau middleware ikut mencatat, setiap galat yang jujur muncul
        // dua kali dan log justru jadi lebih sulit dibaca.
        Route::get('/uji/galat-lolos', function () {
            throw new RuntimeException('meledak dengan jujur');
        });

        $this->app['request']->attributes->set(CatatGalatServer::PENANDA_SUDAH_DICATAT, true);

        $middleware = new CatatGalatServer;

        Log::shouldReceive('error')->never();

        $middleware->terminate(
            $this->app['request'],
            response()->json(['message' => 'x'], 500),
        );
    }

    public function test_tanggapan_di_bawah_lima_ratus_tidak_mengotori_log(): void
    {
        Route::get('/uji/tidak-ditemukan', fn () => ResponseHelper::error('Tidak ditemukan', 404));
        Route::get('/uji/aman', fn () => ResponseHelper::success(['a' => 1]));

        Log::shouldReceive('error')->never();

        $this->get('/uji/tidak-ditemukan')->assertStatus(404);
        $this->get('/uji/aman')->assertStatus(200);
    }

    public function test_galat_server_mencatat_kelas_berkas_dan_sql(): void
    {
        // Middleware hanya melihat TANGGAPAN. Yang memegang kelas exception,
        // berkas, baris, dan SQL-nya cuma blok catch itu sendiri — dan itulah
        // selisih antara "ada 500 di halaman ini" dan "kolom X tidak ada".
        $galat = new QueryException(
            'sqlsrv',
            'select [Kolom_Yang_Belum_Ada] from [Karyawan]',
            [],
            new PDOException("Invalid column name 'Kolom_Yang_Belum_Ada'."),
        );

        $tertangkap = [];
        Log::shouldReceive('error')->andReturnUsing(function ($pesan, $konteks) use (&$tertangkap) {
            $tertangkap[] = [$pesan, $konteks];
        });

        $respons = ResponseHelper::galatServer($galat, 'Gagal mengambil data');

        $this->assertSame(500, $respons->getStatusCode());
        $this->assertNotEmpty($tertangkap, 'galatServer() tidak mencatat apa pun.');

        [$pesan, $konteks] = $tertangkap[0];

        $this->assertStringContainsString('Gagal mengambil data', $pesan);
        $this->assertSame(QueryException::class, $konteks['kelas']);
        $this->assertStringContainsString('Invalid column name', $konteks['pesan']);
        $this->assertArrayHasKey('berkas', $konteks);
        $this->assertStringContainsString(
            'Kolom_Yang_Belum_Ada',
            $konteks['sql'],
            'SQL-nya tidak ikut tercatat. Tanpa itu "Invalid column name" tidak memberi tahu '.
            'kolom mana pada kueri mana — satu-satunya hal yang ingin diketahui pembaca log.',
        );
    }

    public function test_kanal_bawaan_bisa_diarahkan_ke_stderr_lewat_env(): void
    {
        // Berkas log di Cloud Run hidup DI DALAM container yang menulisnya:
        // maxScale 3, minScale 0, dan .dockerignore membuang *.log dari image.
        // Baris yang ditulis instance A tidak terlihat dari instance B dan ikut
        // musnah saat A didaur ulang. Selama daftar kanal 'stack' dipaku ke
        // ['single'], tidak ada cara mengalirkan log yang sama ke Cloud Logging
        // tanpa mengubah kode — dan itu berarti tidak ada salinan yang awet.
        $bawaan = require base_path('config/logging.php');

        $this->assertSame(
            ['single'],
            $bawaan['channels']['stack']['channels'],
            'Bawaan lokal berubah. Pengembangan tidak boleh ikut terseret perubahan ini.',
        );

        putenv('LOG_STACK=single,stderr');
        $_ENV['LOG_STACK'] = 'single,stderr';
        $_SERVER['LOG_STACK'] = 'single,stderr';

        try {
            $dikonfigurasi = require base_path('config/logging.php');

            $this->assertSame(
                ['single', 'stderr'],
                $dikonfigurasi['channels']['stack']['channels'],
                "Daftar kanal 'stack' tidak membaca LOG_STACK, jadi log production tetap hanya "
                .'berupa berkas per-instance yang fana.',
            );
            $this->assertArrayHasKey(
                'stderr',
                $dikonfigurasi['channels'],
                "Kanal 'stderr' tidak ada, sehingga LOG_STACK=single,stderr akan meledak saat boot.",
            );
        } finally {
            putenv('LOG_STACK');
            unset($_ENV['LOG_STACK'], $_SERVER['LOG_STACK']);
        }
    }

    public function test_galat_server_tidak_membocorkan_rincian_teknis_ke_layar(): void
    {
        $galat = new RuntimeException('koneksi ke 10.0.0.5 gagal dengan sandi rahasia');

        Log::shouldReceive('error')->andReturnNull();

        $respons = ResponseHelper::galatServer($galat, 'Terjadi kesalahan pada server');
        $isi = json_decode($respons->getContent(), true);

        $this->assertSame('Terjadi kesalahan pada server', $isi['message']);
        $this->assertStringNotContainsString(
            '10.0.0.5',
            $respons->getContent(),
            'Rincian teknis bocor ke layar. Detail milik log, bukan pengguna.',
        );
    }
}
