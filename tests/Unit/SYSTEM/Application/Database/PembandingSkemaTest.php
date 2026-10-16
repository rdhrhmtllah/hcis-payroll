<?php

namespace Tests\Unit\SYSTEM\Application\Database;

use App\Services\SYSTEM\Application\Database\PembandingSkema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PembandingSkemaTest extends TestCase
{
    private PembandingSkema $pembanding;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pembanding = new PembandingSkema;
    }

    /**
     * @return array<string, mixed>
     */
    private function kolom(string $tabel, string $nama, string $tipe = 'varchar', int $panjang = 50, bool $nullable = false, int $presisi = 0, int $skala = 0): array
    {
        return [
            'tabel' => $tabel,
            'kolom' => $nama,
            'tipe' => $tipe,
            'panjang' => $panjang,
            'presisi' => $presisi,
            'skala' => $skala,
            'nullable' => $nullable,
        ];
    }

    public function test_dua_inventaris_identik_dinyatakan_sama(): void
    {
        $inv = [$this->kolom('N_PA_Cycle', 'Kode_Cycle'), $this->kolom('N_PA_Cycle', 'Nama_Cycle')];

        $hasil = $this->pembanding->banding($inv, $inv);

        $this->assertTrue($hasil['sama']);
        $this->assertSame([], $hasil['tabel_hilang']);
        $this->assertSame([], $hasil['kolom_beda']);
    }

    public function test_tabel_yang_belum_ada_di_staging_dilaporkan(): void
    {
        $dari = [$this->kolom('N_PA_Cycle', 'Kode_Cycle'), $this->kolom('N_PA_Result', 'Final_Score')];
        $ke = [$this->kolom('N_PA_Cycle', 'Kode_Cycle')];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertFalse($hasil['sama']);
        $this->assertSame(['N_PA_Result'], $hasil['tabel_hilang']);
    }

    public function test_tabel_yang_hilang_tidak_diurai_per_kolom(): void
    {
        /*
         * Satu rilis fitur bisa membawa puluhan tabel. Kalau tiap kolomnya ikut
         * dilaporkan, perbedaan yang justru halus dan berbahaya — kolom yang
         * diam-diam berubah tipe — tenggelam di bawah ratusan baris yang sudah
         * terwakili satu nama tabel, dan laporannya berhenti dibaca orang.
         */
        $dari = [
            $this->kolom('N_PA_Result', 'Id_PA_Result'),
            $this->kolom('N_PA_Result', 'Final_Score'),
            $this->kolom('N_PA_Result', 'Kode_Grade_Final'),
        ];

        $hasil = $this->pembanding->banding($dari, []);

        $this->assertSame(['N_PA_Result'], $hasil['tabel_hilang']);
        $this->assertSame([], $hasil['kolom_hilang']);
    }

    public function test_kolom_yang_hilang_pada_tabel_yang_ada_dilaporkan(): void
    {
        $dari = [$this->kolom('N_PA_Cycle', 'Kode_Cycle'), $this->kolom('N_PA_Cycle', 'Tahun', 'smallint', 2)];
        $ke = [$this->kolom('N_PA_Cycle', 'Kode_Cycle')];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertSame(['N_PA_Cycle.Tahun'], $hasil['kolom_hilang']);
        $this->assertSame([], $hasil['tabel_hilang']);
    }

    public function test_kolom_yang_hanya_ada_di_staging_dilaporkan_sebagai_asing(): void
    {
        // Ini terjadi kalau ada yang menjalankan DDL langsung di staging. Diam-diam
        // dibiarkan, ia akan hilang pada refresh berikutnya dan yang membuatnya
        // menyangka refresh-nya yang rusak.
        $dari = [$this->kolom('N_PA_Cycle', 'Kode_Cycle')];
        $ke = [$this->kolom('N_PA_Cycle', 'Kode_Cycle'), $this->kolom('N_PA_Cycle', 'Coba_Coba')];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertSame(['N_PA_Cycle.Coba_Coba'], $hasil['kolom_asing']);
        $this->assertFalse($hasil['sama']);
    }

    public function test_perubahan_panjang_kolom_tertangkap(): void
    {
        // varchar(50) menjadi varchar(255) tidak pernah menggagalkan apa pun sampai
        // ada nilai yang terpotong. Inilah drift yang paling tidak mengumumkan diri.
        $dari = [$this->kolom('N_PA_Cycle', 'Nama_Cycle', 'varchar', 50)];
        $ke = [$this->kolom('N_PA_Cycle', 'Nama_Cycle', 'varchar', 255)];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertCount(1, $hasil['kolom_beda']);
        $this->assertSame('N_PA_Cycle.Nama_Cycle', $hasil['kolom_beda'][0]['kolom']);
        $this->assertSame('varchar(50) NOT NULL', $hasil['kolom_beda'][0]['dari']);
        $this->assertSame('varchar(255) NOT NULL', $hasil['kolom_beda'][0]['ke']);
    }

    public function test_perubahan_nullability_tertangkap(): void
    {
        $dari = [$this->kolom('N_PA_Result', 'Final_Score', 'decimal', 5, false, 6, 2)];
        $ke = [$this->kolom('N_PA_Result', 'Final_Score', 'decimal', 5, true, 6, 2)];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertCount(1, $hasil['kolom_beda']);
        $this->assertSame('decimal(6,2) NOT NULL', $hasil['kolom_beda'][0]['dari']);
        $this->assertSame('decimal(6,2) NULL', $hasil['kolom_beda'][0]['ke']);
    }

    public function test_nullable_bentuk_string_tidak_terbaca_sebagai_beda(): void
    {
        // Driver SQL Server mengembalikan bit kadang sebagai 1, kadang sebagai "1".
        // Tanpa normalisasi, SETIAP kolom akan terlaporkan berbeda dan laporannya
        // jadi tak berarti apa-apa.
        $dari = [array_merge($this->kolom('N_PA_Cycle', 'Deskripsi'), ['nullable' => 1])];
        $ke = [array_merge($this->kolom('N_PA_Cycle', 'Deskripsi'), ['nullable' => '1'])];

        $hasil = $this->pembanding->banding($dari, $ke);

        $this->assertTrue($hasil['sama']);
    }

    public function test_nama_database_yang_tidak_sah_ditolak(): void
    {
        // Nama basis data dirangkai ke dalam SQL karena SQL Server tidak menerima
        // parameter untuk nama objek. Tanpa pemeriksaan ini, satu argumen perintah
        // bisa menjadi SQL apa pun.
        $this->expectException(InvalidArgumentException::class);
        PembandingSkema::pastikanNamaDatabaseAman('Web_HRIS]; DROP TABLE x--');
    }

    public function test_nama_database_wajar_diterima(): void
    {
        $this->assertSame('Web_HRIS_Cut_Off', PembandingSkema::pastikanNamaDatabaseAman('Web_HRIS_Cut_Off'));
    }

    public function test_kueri_inventaris_menyebut_database_dan_menyaring_tabel_bawaan(): void
    {
        $sql = PembandingSkema::kueriInventaris('Web_HRIS_Cut_Off');

        $this->assertStringContainsString('[Web_HRIS_Cut_Off].sys.columns', $sql);
        $this->assertStringContainsString('is_ms_shipped = 0', $sql);
        $this->assertStringContainsString("s.name = 'dbo'", $sql);
    }
}
