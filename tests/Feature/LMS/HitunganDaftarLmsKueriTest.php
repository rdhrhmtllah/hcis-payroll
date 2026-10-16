<?php

namespace Tests\Feature\LMS;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Angka pendamping pada dua daftar LMS diambil sekali, bukan per baris.
 *
 * Keduanya memanggil `count()` di dalam `map()`:
 *
 * - `/lms/admin/training/list` — dua hitungan per materi (section dan item),
 *   jadi halaman 8 materi berarti **16 kueri hitung**. Ukurannya bisa diminta
 *   lewat `per_page`, sehingga biayanya naik lurus mengikuti permintaan.
 * - `/lms/konten-kategori/kategori` — satu hitungan per kategori.
 *
 * Sesudah dihitung berkelompok: **21 → 7** dan **8 → 2** kueri.
 *
 * Dua sisi dijaga di sini. Uji jumlah kueri saja tidak cukup: kalau kunci
 * pemetaan hasil kelompok meleset, semua angka jatuh ke 0 dan jumlah kuerinya
 * justru terlihat makin bagus. Karena itu tiap angka yang dilaporkan diadu
 * dengan hitungan langsung ke tabelnya.
 */
class HitunganDaftarLmsKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTE_TRAINING = '/lms/admin/training/list';

    private const RUTE_KATEGORI = '/lms/konten-kategori/kategori';

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

    public function test_hitungan_training_tidak_diambil_per_materi(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson(self::RUTE_TRAINING);
        $res->assertOk();

        $baris = count($res->json('result') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} materi; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        /* 'N_LMS_Materi_Det' sengaja dipakai sebagai satu jangkar: ia awalan
           dari 'N_LMS_Materi_Detail', jadi satu hitungan ini menampung kedua
           tabel sekaligus. Bentuk per-baris menghasilkan 2 x jumlah materi;
           bentuk massal menghasilkan 2, berapa pun isi halamannya. */
        $n = 0;
        foreach ($kueri as $sql) {
            if (stripos($sql, 'N_LMS_Materi_Det') !== false) {
                $n++;
            }
        }

        $this->assertLessThan(
            $baris,
            $n,
            "Kueri ke tabel detail materi berjalan {$n}x untuk {$baris} materi — biayanya ikut tumbuh "
            .'per baris. Hitungan section dan item harus diambil berkelompok lewat hitungPerMateri(), '
            .'bukan count() di dalam map().'
        );
    }

    public function test_hitungan_kategori_tidak_diambil_per_kategori(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson(self::RUTE_KATEGORI);
        $res->assertOk();

        $baris = count($res->json('result.data') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} kategori; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        $n = 0;
        foreach ($kueri as $sql) {
            if (stripos($sql, 'N_LMS_Master_Konten') !== false) {
                $n++;
            }
        }

        $this->assertLessThan(
            $baris,
            $n,
            "Kueri ke N_LMS_Master_Konten berjalan {$n}x untuk {$baris} kategori — biayanya ikut tumbuh "
            .'per baris. contentCount harus diambil berkelompok, bukan count() di dalam map().'
        );
    }

    /**
     * Sisi kedua untuk training: angkanya harus tetap benar, bukan sekadar
     * sedikit kuerinya.
     */
    public function test_angka_training_sama_dengan_hitungan_langsung(): void
    {
        $res = $this->getJson(self::RUTE_TRAINING);
        $res->assertOk();

        $rows = $res->json('result') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Daftar training kosong di data dev.');
        }

        $adaAngka = 0;
        foreach ($rows as $baris) {
            foreach (['id', 'section_count', 'item_count'] as $medan) {
                $this->assertArrayHasKey($medan, $baris, "Baris training kehilangan medan '{$medan}'.");
            }

            $idMateri = Hashids::connection('custom')->decode($baris['id'])[0] ?? null;
            $this->assertNotNull($idMateri, "Id materi '{$baris['id']}' tidak bisa dibuka kembali.");

            $section = DB::table('N_LMS_Materi_Detail')
                ->where('Id_Master_Training', $idMateri)
                ->where('Flag_Aktif', 'Y')
                ->count();

            $item = DB::table('N_LMS_Materi_Det')
                ->where('Id_Materi', $idMateri)
                ->where('Flag_Aktif', 'Y')
                ->count();

            $this->assertSame(
                $section,
                (int) $baris['section_count'],
                "Materi {$idMateri} melaporkan {$baris['section_count']} section padahal tabelnya berisi "
                ."{$section}. Kunci pemetaan hasil kelompok kemungkinan meleset."
            );

            $this->assertSame(
                $item,
                (int) $baris['item_count'],
                "Materi {$idMateri} melaporkan {$baris['item_count']} item padahal tabelnya berisi "
                ."{$item}. Kunci pemetaan hasil kelompok kemungkinan meleset."
            );

            if ($section > 0 || $item > 0) {
                $adaAngka++;
            }
        }

        /* Premisnya diperiksa: kalau seluruh materi memang berangka nol, dua
           penegasan di atas cocok tanpa membuktikan apa pun. */
        $this->assertGreaterThan(
            0,
            $adaAngka,
            'Seluruh '.count($rows).' materi berangka nol, jadi perbandingan di atas tidak membuktikan '
            .'pemetaannya benar. Pilih data dev yang punya materi berisi.'
        );
    }

    /**
     * Sisi kedua untuk kategori.
     */
    public function test_angka_kategori_sama_dengan_hitungan_langsung(): void
    {
        $res = $this->getJson(self::RUTE_KATEGORI);
        $res->assertOk();

        $rows = $res->json('result.data') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Daftar kategori konten kosong di data dev.');
        }

        $adaAngka = 0;
        foreach ($rows as $baris) {
            foreach (['id', 'contentCount'] as $medan) {
                $this->assertArrayHasKey($medan, $baris, "Baris kategori kehilangan medan '{$medan}'.");
            }

            $idKategori = Hashids::connection('custom')->decode($baris['id'])[0] ?? null;
            $this->assertNotNull($idKategori, "Id kategori '{$baris['id']}' tidak bisa dibuka kembali.");

            $konten = DB::table('N_LMS_Master_Konten')
                ->where('Id_Kategori_Konten', $idKategori)
                ->where('Flag_Aktif', 'Y')
                ->count();

            $this->assertSame(
                $konten,
                (int) $baris['contentCount'],
                "Kategori {$idKategori} melaporkan {$baris['contentCount']} konten padahal tabelnya berisi "
                ."{$konten}. Kunci pemetaan hasil kelompok kemungkinan meleset."
            );

            if ($konten > 0) {
                $adaAngka++;
            }
        }

        $this->assertGreaterThan(
            0,
            $adaAngka,
            'Seluruh '.count($rows).' kategori berangka nol, jadi perbandingan di atas tidak membuktikan '
            .'pemetaannya benar. Pilih data dev yang punya kategori berisi.'
        );
    }
}
