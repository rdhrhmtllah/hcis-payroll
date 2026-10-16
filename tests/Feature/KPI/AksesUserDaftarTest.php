<?php

namespace Tests\Feature\KPI;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Daftar akses user KPI harus benar-benar menjawab, bukan 500.
 *
 * `akses-user/current` dan `akses-user/level/current` **selalu** menjawab 500 —
 * entah sejak kapan. Sebabnya `ceil()` (float) dikirim ke parameter ke-7
 * `successWithPagination()` yang bertipe `array $extra`, sehingga PHP melempar
 * TypeError.
 *
 * Cacat seperti ini tak gagal saat boot dan tak memberi peringatan saat deploy;
 * ia diam sampai seseorang membuka halamannya. Ditemukan hanya karena sapuan
 * rute Fase 7 menjalankannya.
 *
 * Nilai yang dikirim itu pun mubazir: helper sudah menghitung `total_page`
 * dengan rumus yang sama persis, jadi membuangnya tidak mengubah bentuk
 * jawaban sedikit pun.
 */
class AksesUserDaftarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first()
            ?? User::query()->orderBy('Id_Users')->first();

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        $this->withoutMiddleware();
        Auth::login($aktor);
    }

    public static function ruteDaftar(): array
    {
        return [
            'akses divisi' => ['/akses-user/current'],
            'akses level' => ['/akses-user/level/current'],
        ];
    }

    /**
     * @dataProvider ruteDaftar
     */
    public function test_menjawab_200_bukan_500(string $uri): void
    {
        $res = $this->getJson($uri);

        $this->assertSame(
            200,
            $res->getStatusCode(),
            "{$uri} menjawab {$res->getStatusCode()}.\n\nBadan: "
            .substr((string) $res->getContent(), 0, 300)
            ."\n\nPeriksa tipe argumen yang dikirim ke successWithPagination(): parameter "
            .'ke-7 bertipe array, dan mengirim angka ke sana melempar TypeError yang hanya '
            .'muncul saat barisnya benar-benar dijalankan.'
        );
    }

    /**
     * Sisi kedua: 200 yang kosong bentuknya bukan jawaban yang bekerja.
     *
     * Tanpa ini, mengganti isinya dengan `return response()->json([])` akan
     * lolos uji di atas.
     */
    public function test_membawa_bentuk_paginasi_yang_utuh(): void
    {
        $res = $this->getJson('/akses-user/current');
        $res->assertOk();

        foreach (['success', 'status', 'message', 'result', 'page', 'total_page', 'total_data'] as $medan) {
            $this->assertNotNull($res->json($medan), "Jawaban kehilangan medan '{$medan}'.");
        }

        $this->assertIsArray($res->json('result'));

        /* total_page harus konsisten dengan total_data & halaman yang diminta.
           Inilah nilai yang dulu dikirim salah tempat oleh pemanggilnya; kalau
           helper berhenti menghitungnya, buangnya jadi kehilangan data. */
        $totalData = (int) $res->json('total_data');
        $totalPage = (int) $res->json('total_page');

        if ($totalData > 0) {
            $this->assertGreaterThanOrEqual(
                1,
                $totalPage,
                'total_data terisi tetapi total_page nol — halamannya tak bisa dinavigasi.'
            );
        }
    }
}
