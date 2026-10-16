<?php

namespace Tests\Feature\LMS;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ringkasan training pada export karyawan diambil sekali, bukan per orang.
 *
 * `exportEmployeeTrainingSummary()` menembak satu kueri per karyawan, dan
 * kuerinya **non-sargable** — `UPPER(LTRIM(RTRIM(Kode_Karyawan)))` membungkus
 * kolomnya, sehingga tiap panggilan memaksa pemindaian. Halaman 15 orang berarti
 * **15 pemindaian**.
 *
 * Sesudah dilayani bentuk jamak yang sudah lama ada di service yang sama
 * (`byKodeKaryawansQuery()`, hanya belum dipakai di sini): **20 → 6 kueri**.
 *
 * YANG TIDAK BERUBAH, DAN ITU DISENGAJA.
 * Kuerinya tetap non-sargable: `IN` atas kolom yang dibungkus fungsi tetap tak
 * bisa memakai index. Yang hilang adalah 14 pemindaian dari 15, bukan
 * pemindaiannya. Membuatnya sargable berarti menyentuh bagaimana Kode_Karyawan
 * dinormalkan di seluruh sistem — perubahan tersendiri yang harus diuji sendiri.
 */
class MonitoringExportKaryawanKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTE = '/lms/admin/monitoring/export/employees';

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

    public function test_ringkasan_training_tidak_diambil_per_karyawan(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $baris = count($res->json('result') ?? []);
        if ($baris < 3) {
            $this->markTestSkipped("Hanya {$baris} baris; terlalu sedikit untuk membedakan sekali dari per-orang.");
        }

        $n = 0;
        foreach ($kueri as $sql) {
            if (stripos($sql, 'N_LMS_Penjadwalan') !== false) {
                $n++;
            }
        }

        /* Ambangnya RELATIF: yang dijaga "tidak tumbuh mengikuti orang", bukan
           angka mati yang akan basi begitu isi halaman berubah. */
        $this->assertLessThan(
            $baris,
            $n,
            "Kueri ke N_LMS_Penjadwalan berjalan {$n}x untuk {$baris} karyawan — biayanya ikut "
            .'tumbuh per orang. Ringkasan training harus diambil massal lewat '
            .'byKodeKaryawansQuery(), bukan satu kueri per karyawan. Kuerinya non-sargable, '
            .'jadi tiap satu di antaranya memaksa pemindaian.'
        );
    }

    /**
     * Sisi kedua: memangkas kueri tak berguna kalau angkanya ikut hilang.
     *
     * Kalau pemetaan kunci meleset, berlangsung/selesai jatuh ke 0 dan `ids`
     * jadi kosong untuk semua orang — dan jumlah kuerinya justru terlihat makin
     * bagus.
     */
    public function test_ringkasan_tetap_membawa_angka_dan_id(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $rows = $res->json('result') ?? [];
        if ($rows === []) {
            $this->markTestSkipped('Daftar export karyawan kosong di data dev.');
        }

        $adaAngka = 0;
        foreach ($rows as $baris) {
            foreach (['training_berlangsung', 'training_selesai', 'penjadwalan_ids'] as $medan) {
                $this->assertArrayHasKey($medan, $baris, "Baris export kehilangan medan '{$medan}'.");
            }

            if ((int) $baris['training_berlangsung'] > 0
                || (int) $baris['training_selesai'] > 0
                || $baris['penjadwalan_ids'] !== []) {
                $adaAngka++;
            }
        }

        /* Premisnya diperiksa lebih dulu: rute ini hanya memuat karyawan yang
           PUNYA penjadwalan aktif, jadi seluruhnya nol berarti pemetaannya
           rusak — bukan datanya yang kosong. */
        $this->assertGreaterThan(
            0,
            $adaAngka,
            'Seluruh '.count($rows).' karyawan berangka nol dan tanpa id, padahal rute ini hanya '
            .'memuat karyawan yang punya penjadwalan aktif. Kunci pemetaan kemungkinan meleset — '
            .'gejala yang justru membuat jumlah kueri tampak membaik.'
        );
    }

    /**
     * Id yang dikirim harus benar-benar bisa dibuka, dan menunjuk penjadwalan
     * milik karyawan yang bersangkutan.
     *
     * Pengambilan massal mengelompokkan baris dari banyak orang dalam satu
     * hasil. Kalau pengelompokannya keliru, id milik orang lain bisa menempel
     * pada baris yang salah — dan tak ada uji jumlah kueri yang akan
     * menyadarinya.
     */
    public function test_id_penjadwalan_milik_karyawan_yang_benar(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $rows = $res->json('result') ?? [];
        $diperiksa = 0;

        foreach ($rows as $baris) {
            foreach (array_slice($baris['penjadwalan_ids'] ?? [], 0, 3) as $terenkripsi) {
                $idPenjadwalan = (int) Crypt::decryptString($terenkripsi);

                $pemilik = DB::table('N_LMS_Penjadwalan')
                    ->where('Id_Penjadwalan', $idPenjadwalan)
                    ->value('Kode_Karyawan');

                $this->assertSame(
                    strtoupper(trim((string) $baris['kode_karyawan'])),
                    strtoupper(trim((string) $pemilik)),
                    "Penjadwalan {$idPenjadwalan} menempel pada karyawan {$baris['kode_karyawan']} "
                    .'padahal miliknya '.$pemilik.'. Pengelompokan hasil massal keliru.'
                );

                $diperiksa++;
            }
        }

        if ($diperiksa === 0) {
            $this->markTestSkipped('Tak ada id penjadwalan untuk diperiksa di data dev.');
        }
    }
}
