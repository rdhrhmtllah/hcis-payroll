<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaBobotGuard;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;
use Tests\TestCase;

/**
 * Penjaga "Σ harus 100".
 *
 * Empat aturan yang sebelumnya hanya berupa kalimat di README bermuara ke satu tempat
 * ini. Kegagalannya diam: bobot yang berjumlah 70 menghasilkan nilai 30 persen lebih
 * rendah tanpa satu pun error, dan angkanya tetap tampak wajar.
 *
 * Tidak menyentuh database — murni aritmetika.
 */
class BobotGuardTest extends TestCase
{
    private PaBobotGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new PaBobotGuard;
    }

    public function test_menerima_bobot_yang_berjumlah_seratus(): void
    {
        $this->guard->pastikanSeratus(['ATASAN' => 45, 'SELF' => 10, 'PEER' => 15, 'BAWAHAN' => 30], 'uji');
        $this->assertTrue($this->guard->berjumlahSeratus(['A' => 80, 'B' => 20]));
    }

    public function test_menolak_bobot_yang_kurang_dan_menyebut_jumlahnya(): void
    {
        try {
            $this->guard->pastikanSeratus(['ATASAN' => 45, 'SELF' => 10, 'BAWAHAN' => 15], 'aturan level Manager');
            $this->fail('Bobot 70 seharusnya ditolak.');
        } catch (AssertionFailedError $gagal) {
            // `AssertionFailedError` turunan `RuntimeException`, jadi `fail()` di dalam
            // `try` ditangkap `catch` di bawahnya — dan bila pesannya kebetulan memuat
            // frasa yang ditegaskan, ujinya HIJAU justru ketika penjaganya tidak ada.
            throw $gagal;
        } catch (RuntimeException $e) {
            // Pesannya harus menyebut jumlah sebenarnya dan rinciannya, karena yang
            // membaca error ini sedang mencari kategori mana yang salah.
            $this->assertStringContainsString('70', $e->getMessage());
            $this->assertStringContainsString('aturan level Manager', $e->getMessage());
            $this->assertStringContainsString('ATASAN=45', $e->getMessage());
        }
    }

    public function test_menolak_bobot_yang_lebih(): void
    {
        $this->expectException(RuntimeException::class);
        $this->guard->pastikanSeratus(['A' => 70, 'B' => 40], 'uji');
    }

    public function test_menolak_daftar_kosong(): void
    {
        // Nol bukan 100. Daftar kosong yang lolos akan membuat peserta tanpa kategori
        // apa pun tampak sah.
        $this->expectException(RuntimeException::class);
        $this->guard->pastikanSeratus([], 'uji');
    }

    public function test_toleransi_menyerap_pembulatan_tapi_bukan_kesalahan(): void
    {
        // 33,33 tiga kali = 99,99. Ini pembagian yang BENAR dan harus lolos.
        $this->assertTrue($this->guard->berjumlahSeratus([33.33, 33.33, 33.33]));

        // 99,9 bukan pembulatan, itu kekurangan 0,1 — harus ditolak.
        $this->assertFalse($this->guard->berjumlahSeratus([33.30, 33.30, 33.30]));
    }

    public function test_redistribusi_membagi_bobot_kategori_yang_kosong(): void
    {
        // Aturan Manager: Atasan 45, Self 10, Rekan 15, Bawahan 30.
        // Tidak ada rekan yang mengisi, jadi 15 miliknya dibagi ulang.
        $hasil = $this->guard->redistribusi(
            ['ATASAN' => 45, 'SELF' => 10, 'PEER' => 15, 'BAWAHAN' => 30],
            ['ATASAN', 'SELF', 'BAWAHAN']
        );

        $this->assertSame(['ATASAN', 'SELF', 'BAWAHAN'], array_keys($hasil));
        $this->assertTrue($this->guard->berjumlahSeratus($hasil));

        // Perbandingan antar kategori harus tetap: 45 : 10 : 30 menjadi
        // 52,94 : 11,76 : 35,29 — Atasan tetap terbesar, Self tetap terkecil.
        $this->assertGreaterThan($hasil['BAWAHAN'], $hasil['ATASAN']);
        $this->assertLessThan($hasil['BAWAHAN'], $hasil['SELF']);

        // Ketiganya berjumlah 99,99 sebelum penyesuaian, jadi sisa 0,01 jatuh ke
        // bagian TERBESAR. Angka pastinya 52,95, bukan 52,94 — dan itu memang
        // aturannya, bukan pembulatan yang lolos.
        $this->assertSame(52.95, $hasil['ATASAN']);
        $this->assertSame(11.76, $hasil['SELF']);
        $this->assertSame(35.29, $hasil['BAWAHAN']);
    }

    public function test_redistribusi_selalu_menghasilkan_tepat_seratus(): void
    {
        // Kasus yang paling mudah meleset: pembagian yang tidak habis dibagi.
        foreach ([[1, 1, 1], [1, 1, 1, 1, 1, 1, 1], [7, 11, 13], [1, 2, 97]] as $awal) {
            $hasil = $this->guard->redistribusi($awal, array_keys($awal));
            $this->assertSame(100.0, round(array_sum($hasil), 2),
                'Pembagian ulang '.implode('/', $awal).' tidak berjumlah tepat 100.');
        }
    }

    public function test_sisa_pembulatan_tidak_bergantung_urutan_masukan(): void
    {
        // Dua panggilan dengan isi sama tetapi urutan berbeda wajib memberi angka yang
        // sama untuk kategori yang sama. Kalau sisanya diberikan ke elemen PERTAMA,
        // uji ini gagal — dan hasil yang bergantung urutan mustahil dijelaskan saat
        // diaudit.
        $a = $this->guard->redistribusi(['X' => 1, 'Y' => 1, 'Z' => 1], ['X', 'Y', 'Z']);
        $b = $this->guard->redistribusi(['Z' => 1, 'Y' => 1, 'X' => 1], ['Z', 'Y', 'X']);

        $this->assertSame($a['X'], $b['X']);
        $this->assertSame($a['Y'], $b['Y']);
        $this->assertSame($a['Z'], $b['Z']);
    }

    public function test_menolak_pembagian_ulang_saat_tidak_ada_kategori_terisi(): void
    {
        // Peserta seperti ini harus TERTAHAN, bukan diberi angka hasil pembagian nol.
        $this->expectException(RuntimeException::class);
        $this->guard->redistribusi(['ATASAN' => 45, 'SELF' => 10], []);
    }

    public function test_menolak_kategori_terisi_yang_tidak_ada_di_bobot_awal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->guard->redistribusi(['ATASAN' => 100], ['BAWAHAN']);
    }

    public function test_menolak_saat_seluruh_kategori_terisi_berbobot_nol(): void
    {
        // Tanpa penjaga ini, pembagiannya membagi dengan nol.
        $this->expectException(RuntimeException::class);
        $this->guard->redistribusi(['ATASAN' => 0, 'SELF' => 0], ['ATASAN', 'SELF']);
    }

    public function test_bagi_rata_untuk_bobot_di_dalam_kategori(): void
    {
        $this->assertSame([100.0], $this->guard->bagiRata(1));
        $this->assertSame([50.0, 50.0], $this->guard->bagiRata(2));

        // Tiga penilai: 33,33 tiga kali berjumlah 99,99, jadi satu sen sisanya
        // jatuh ke penilai pertama. Yang penting jumlahnya tepat 100 dan siapa yang
        // menerima sisanya tidak berubah-ubah.
        $this->assertSame([33.34, 33.33, 33.33], $this->guard->bagiRata(3));
        $this->assertSame(100.0, round(array_sum($this->guard->bagiRata(3)), 2));
    }

    public function test_bagi_rata_menolak_jumlah_nol(): void
    {
        $this->expectException(RuntimeException::class);
        $this->guard->bagiRata(0);
    }
}
