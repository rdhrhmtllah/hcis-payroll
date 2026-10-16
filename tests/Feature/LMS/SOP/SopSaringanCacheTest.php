<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SaringanMonitoring;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pemakai trait sekadar untuk uji.
 *
 * sidikSaringan() dan mungkinCache() sengaja TIDAK public — keduanya detail
 * kunci cache, bukan kontrak trait — jadi ujinya masuk lewat refleksi. Menaikkan
 * visibilitasnya demi uji akan membuat pemanggil di luar modul bisa mengarang
 * kunci cache sendiri.
 *
 * Kelas ini tak menyetel $people: mungkinCache dan sidikSaringan tak
 * membutuhkannya, dan closure agregatnya diganti closure uji.
 */
class PemakaiSaringanMonitoringUji
{
    use SaringanMonitoring;
}

/**
 * Cache untuk hasil monitoring yang TERSARING.
 *
 * Sebelumnya mungkinCache() melewati cache sama sekali begitu ada saringan
 * non-waktu, sehingga admin yang menyaring per divisi memicu agregat penuh pada
 * SETIAP interaksi, di ~25 endpoint monitoring.
 *
 * Yang menjaga perbaikan itu tidak menukar satu masalah dengan masalah lain
 * adalah NORMALISASI saringan. Tabel `cache` dipakai bersama seluruh modul dan
 * tumbuh mengikuti kardinalitas kunci, bukan jumlah kunci yang hidup; bentuk
 * saringan yang ekuivalen karena itu WAJIB berbagi satu kunci.
 */
class SopSaringanCacheTest extends TestCase
{
    use DatabaseTransactions;

    private function subjek(): PemakaiSaringanMonitoringUji
    {
        return new PemakaiSaringanMonitoringUji;
    }

    private function sidik(array $f): string
    {
        $m = new \ReflectionMethod(PemakaiSaringanMonitoringUji::class, 'sidikSaringan');
        $m->setAccessible(true);

        return (string) $m->invoke($this->subjek(), $f);
    }

    private function mungkinCache(string $kunci, int $detik, array $f, \Closure $fn)
    {
        $m = new \ReflectionMethod(PemakaiSaringanMonitoringUji::class, 'mungkinCache');
        $m->setAccessible(true);

        return $m->invoke($this->subjek(), $kunci, $detik, $f, $fn);
    }

    /**
     * Saringan yang menyaring himpunan yang sama harus bersidik sama.
     *
     * Urutan pilihan berbeda, nilai duplikat, dan kunci bernilai kosong adalah
     * tiga bentuk yang datang apa adanya dari layar; ketiganya tidak mengubah
     * hasil satu baris pun.
     */
    public function test_saringan_ekuivalen_berbagi_satu_sidik(): void
    {
        $a = $this->sidik(['dept' => ['B', 'A']]);
        $b = $this->sidik(['dept' => ['A', 'B', 'A'], 'kosong' => '']);

        $this->assertSame($a, $b,
            "Sidik berbeda ({$a} vs {$b}) untuk saringan yang menyaring himpunan yang "
            .'persis sama. Kardinalitas kunci cache akan meledak: satu hasil ditulis '
            .'berulang kali di bawah kunci yang berbeda-beda, pada tabel `cache` yang '
            .'dipakai bersama seluruh modul.');
    }

    /** Bentuk kosong yang lain — null dan array kosong — juga tak boleh membentuk kunci sendiri. */
    public function test_nilai_kosong_tidak_membentuk_sidik_sendiri(): void
    {
        $dasar = $this->sidik(['divisi' => ['IT']]);

        $this->assertSame($dasar, $this->sidik(['divisi' => ['IT'], 'dept' => []]));
        $this->assertSame($dasar, $this->sidik(['divisi' => ['IT'], 'level' => null]));
        $this->assertSame($dasar, $this->sidik(['level' => '', 'divisi' => ['IT']]),
            'Urutan kunci pun tak boleh mengubah sidik — ksort() hilang.');
    }

    /**
     * PENJAGA ARAH SEBALIKNYA.
     *
     * Normalisasi yang terlalu rakus sama berbahayanya: dua saringan yang
     * BERBEDA berbagi satu kunci berarti admin divisi A disajikan angka
     * divisi B.
     */
    public function test_saringan_berbeda_tidak_boleh_berbagi_sidik(): void
    {
        $sidik = [
            $this->sidik(['dept' => ['A']]),
            $this->sidik(['dept' => ['B']]),
            $this->sidik(['dept' => ['A', 'B']]),
            $this->sidik(['divisi' => ['A']]),
            $this->sidik(['dept' => ['A'], 'date_start' => '2026-01-01']),
            $this->sidik(['dept' => ['A'], 'date_start' => '2026-02-01']),
            $this->sidik(['dept' => ['A'], 'sumber' => ['MANDIRI']]),
        ];

        $this->assertCount(count($sidik), array_unique($sidik),
            'Dua saringan yang berbeda memulangkan sidik yang sama. Rentang tanggal dan '
            .'sumber IKUT menentukan hasil, jadi keduanya wajib ikut masuk sidik.');
    }

    /**
     * Efeknya, bukan bentuknya: jalur tersaring kini benar-benar melewati cache.
     */
    public function test_hasil_tersaring_kini_diambil_dari_cache(): void
    {
        $kunci = 'uji_sop_saringan_'.bin2hex(random_bytes(6));
        $f = ['dept' => ['B', 'A']];

        $jalan = 0;
        $hitung = function () use (&$jalan) {
            $jalan++;

            return ['nilai' => 42];
        };

        $pertama = $this->mungkinCache($kunci, 900, $f, $hitung);

        $this->assertSame(1, $jalan,
            'PENJAGA KERJA-NYATA: agregatnya bahkan tak pernah dijalankan sekali pun.');
        $this->assertSame(['nilai' => 42], $pertama);

        // Bentuk ekuivalen, bukan array yang sama — harus mengenai kunci yang sama.
        $kedua = $this->mungkinCache($kunci, 900, ['dept' => ['A', 'B', 'A'], 'kosong' => ''], $hitung);

        $this->assertSame(1, $jalan,
            'Agregat dijalankan lagi untuk saringan yang sama. mungkinCache() masih '
            .'melewati cache begitu ada saringan non-waktu — persis keluhan aslinya: '
            .'admin yang menyaring per divisi memicu agregat penuh tiap interaksi.');
        $this->assertSame(['nilai' => 42], $kedua);

        // Saringan yang berbeda tetap harus dihitung ulang.
        $this->mungkinCache($kunci, 900, ['dept' => ['C']], $hitung);
        $this->assertSame(2, $jalan, 'Saringan berbeda mengenai kunci yang sama — hasilnya salah, bukan cuma basi.');
    }
}
