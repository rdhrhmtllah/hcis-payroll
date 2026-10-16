<?php

namespace Tests\Unit\VISITOR\Permit\Inspection;

use PHPUnit\Framework\TestCase;

/**
 * Aksi yang ada di kode tetapi tak terjangkau dari layar.
 *
 * `ubahStatusPos()` dan `ubahStatusShift()` hidup berbulan-bulan tanpa satu pun
 * pemanggil di template. Endpointnya ada, penanganannya ada, pesan suksesnya
 * ada — hanya tombolnya yang tidak pernah dipasang. Akibatnya pos inspeksi yang
 * salah ketik bisa diubah namanya tetapi TIDAK PERNAH bisa dipensiunkan, dan
 * tidak ada yang menyalakan alarm: tak ada galat, tak ada uji merah, dan
 * kodenya terbaca lengkap saat dibaca sepintas.
 *
 * Penjaga ini menagih hal yang tidak bisa dilihat dari satu sisi saja: setiap
 * aksi pembuka dialog dan pengubah status pada halaman ini harus benar-benar
 * dirujuk dari template-nya.
 */
class AgendaInspeksiAksiTerjangkauTest extends TestCase
{
    private const HALAMAN = 'resources/js/Pages/VISITOR/Permit/Inspection/AgendaInspeksiPage.vue';

    /** Aksi yang wajib punya pemicu; awalan ini menandai niat "dipakai user". */
    private const AWALAN_AKSI = ['bukaTambah', 'bukaEdit', 'ubahStatus', 'hapus', 'catat'];

    public function test_setiap_aksi_master_punya_pemicu_di_template(): void
    {
        [$template, $skrip] = $this->belah();

        $tanpaPemicu = [];

        foreach ($this->metodeAksi($skrip) as $metode) {
            if (! preg_match('/\b'.preg_quote($metode, '/').'\s*[(\"\']/', $template)
                && ! str_contains($template, $metode)) {
                $tanpaPemicu[] = $metode;
            }
        }

        $this->assertSame(
            [],
            $tanpaPemicu,
            'Aksi berikut ada di skrip tetapi tidak dirujuk template mana pun, '
                ."sehingga user tidak punya cara memanggilnya:\n".implode("\n", $tanpaPemicu),
        );
    }

    public function test_penjaga_ini_benar_benar_menemukan_aksinya(): void
    {
        // Tanpa ini, penjaga di atas tetap hijau seandainya pemindainya berhenti
        // menemukan metode apa pun — bentuk uji yang paling sia-sia.
        [, $skrip] = $this->belah();

        $aksi = $this->metodeAksi($skrip);

        $this->assertGreaterThanOrEqual(4, count($aksi), 'Pemindai tidak menemukan aksi master; polanya perlu diperiksa.');
        $this->assertContains('ubahStatusPos', $aksi);
        $this->assertContains('ubahStatusShift', $aksi);
    }

    /**
     * @return array{0: string, 1: string} [template, skrip]
     */
    private function belah(): array
    {
        // base_path() tidak tersedia: uji ini sengaja TIDAK memuat aplikasi
        // Laravel — ia hanya membaca berkas, dan memuat kernel hanya untuk itu
        // membuatnya lambat tanpa menambah apa pun yang dijaga.
        $penuh = dirname(__DIR__, 5).'/'.self::HALAMAN;
        $this->assertFileExists($penuh);

        $isi = (string) file_get_contents($penuh);
        $batas = strpos($isi, '<script>');

        $this->assertNotFalse($batas, 'Halaman tidak punya blok <script>; pembelahannya tidak sah.');

        return [substr($isi, 0, $batas), substr($isi, $batas)];
    }

    /** @return array<int, string> */
    private function metodeAksi(string $skrip): array
    {
        // Alternasinya WAJIB dikurung sendiri: tanpa itu [A-Za-z0-9_]* hanya
        // menempel pada awalan terakhir, dan pemindainya melewatkan sebagian
        // besar aksi sambil tetap terlihat bekerja.
        $pola = '/^\s{8}(?:async\s+)?((?:'.implode('|', self::AWALAN_AKSI).')[A-Za-z0-9_]*)\s*\(/m';
        preg_match_all($pola, $skrip, $cocok);

        return array_values(array_unique($cocok[1]));
    }
}
