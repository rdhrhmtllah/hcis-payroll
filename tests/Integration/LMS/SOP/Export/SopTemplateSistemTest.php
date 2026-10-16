<?php

namespace Tests\Integration\LMS\SOP\Export;

use App\Services\LMS\SOP\Export\SopDatasetDokumen;
use App\Services\LMS\SOP\Export\SopDatasetKepatuhan;
use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Tests\TestCase;

/**
 * Template resmi ditulis di kode, jadi salah ketik satu kunci tidak ketahuan
 * sampai ada orang yang mengkliknya. Berkas ini yang menahannya.
 */
class SopTemplateSistemTest extends TestCase
{
    public static function templateSistem(): array
    {
        return array_map(fn ($t) => [$t], SopTemplateSistem::semua());
    }

    /**
     * Dataset yang DIDEKLARASIKAN template itu sendiri.
     *
     * Uji ini dulu memaksa seluruh template ke SopDatasetKepatuhan. Selama
     * hanya ada satu dataset, itu tak keliatan; begitu Export Studio menambah
     * dataset DOKUMEN, keenam template dokumen divalidasi terhadap katalog yang
     * salah dan seluruh kunci dimensinya dilaporkan "tidak dikenal" — kegagalan
     * yang terbaca seolah templatenya rusak, padahal ujinya yang salah alamat.
     */
    private function dataset(array $tpl): object
    {
        $kunci = $tpl['dataset'] ?? $tpl['definisi']['dataset'] ?? null;

        return match ($kunci) {
            'kepatuhan' => app(SopDatasetKepatuhan::class),
            'dokumen' => app(SopDatasetDokumen::class),
            default => $this->fail("Template '{$tpl['kode']}' menyebut dataset tak dikenal: ".var_export($kunci, true)),
        };
    }

    /**
     * @dataProvider templateSistem
     */
    public function test_setiap_template_resmi_lolos_validator(array $tpl): void
    {
        $galat = app(DefinisiValidator::class)->periksa(
            DefinisiLaporan::dariArray($tpl['definisi']),
            $this->dataset($tpl),
        );

        $this->assertSame([], $galat, "Template '{$tpl['kode']}' tidak sah: ".json_encode($galat, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Lolos validator berarti kuncinya dikenal — bukan berarti SQL-nya jalan.
     * Ekspresi katalog yang keliru baru meledak saat dieksekusi.
     *
     * @dataProvider templateSistem
     */
    public function test_setiap_template_resmi_benar_benar_jalan(array $tpl): void
    {
        $hasil = app(ReportRunner::class)->jalankan(
            DefinisiLaporan::dariArray($tpl['definisi']),
            $this->dataset($tpl),
            new Lingkup('AD1', true, [], true),
            'UJI',
            5,
        );

        $this->assertNotEmpty($hasil['columns'], "Template '{$tpl['kode']}' tidak menghasilkan kolom.");
        $this->assertIsArray($hasil['rows']);
    }

    /**
     * Tanpa kedua kolom ini, satu berkas memuat kewajiban yang ditugaskan dan
     * bacaan sukarela tanpa cara membedakannya — dan pembaca berkas tidak punya
     * petunjuk apa pun bahwa keduanya tercampur.
     *
     * HANYA BERLAKU PADA DATASET KEPATUHAN. Di sana satu baris adalah satu
     * PENUGASAN, jadi percampuran itu nyata dan wajib dibedakan. Pada dataset
     * DOKUMEN satu baris adalah satu dokumen — tak ada penugasan di dalamnya,
     * dan 'distribusi'/'sumber' bahkan bukan dimensi yang dikenal katalognya.
     * Menuntutnya di sana bukan menjaga apa pun; ia hanya melarang dataset itu
     * punya template rinci sama sekali.
     *
     * @dataProvider templateSistem
     */
    public function test_template_rinci_kepatuhan_selalu_membawa_distribusi_dan_sumber(array $tpl): void
    {
        $kepatuhan = ($tpl['dataset'] ?? $tpl['definisi']['dataset'] ?? null) === 'kepatuhan';

        if (($tpl['mode'] ?? '') !== 'rinci' || ! $kepatuhan) {
            $this->assertTrue(true);

            return;
        }

        $kolom = $tpl['definisi']['kolom'] ?? [];

        $this->assertContains('distribusi', $kolom, "Template rinci '{$tpl['kode']}' tidak menyebut Tipe Distribusi.");
        $this->assertContains('sumber', $kolom, "Template rinci '{$tpl['kode']}' tidak menyebut Sumber Penugasan.");
    }

    public function test_kode_template_unik_dan_cocok_dengan_kuncinya(): void
    {
        foreach (SopTemplateSistem::semua() as $kunci => $tpl) {
            $this->assertSame(
                $kunci,
                $tpl['kode'],
                "Kunci array dan field 'kode' harus sama — galeri memakai salah satunya bergantian.",
            );
        }
    }

    /** Ada template khusus untuk masing-masing tipe distribusi, tersaring benar. */
    public function test_tersedia_template_terpisah_per_tipe_distribusi(): void
    {
        $semua = SopTemplateSistem::semua();

        $saringan = fn (string $kode) => $semua[$kode]['definisi']['saringan'][0] ?? [];

        $this->assertSame('TERBATAS', $saringan('sop-sistem-rinci-tertutup')['nilai'] ?? null);
        $this->assertSame('PUBLIK', $saringan('sop-sistem-rinci-publik')['nilai'] ?? null);
        $this->assertSame('distribusi', $saringan('sop-sistem-rinci-tertutup')['field'] ?? null);
    }
}
