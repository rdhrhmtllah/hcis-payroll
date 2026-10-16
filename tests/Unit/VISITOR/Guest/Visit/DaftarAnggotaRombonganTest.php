<?php

namespace Tests\Unit\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Penyusun daftar rombongan tamu.
 *
 * Daftar ini adalah satu-satunya sumber Jumlah_Tamu. Kalau ia boleh berisi nama
 * kosong, melewatkan tamu utama, atau melampaui batas, angka yang dibaca papan
 * harian, laporan, dan gerbang ikut salah tanpa satu pun galat.
 */
class DaftarAnggotaRombonganTest extends TestCase
{
    private DaftarAnggotaRombongan $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DaftarAnggotaRombongan;
    }

    public function test_tamu_utama_selalu_jadi_elemen_pertama(): void
    {
        $daftar = $this->service->susun('Rudi Hartono', [
            ['nama' => 'Siti Aminah'],
            ['nama' => 'Budi'],
        ]);

        $this->assertCount(3, $daftar);
        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
    }

    public function test_identitas_opsional_tidak_ditulis_sebagai_kunci_kosong(): void
    {
        $daftar = $this->service->susun('Rudi', [['nama' => 'Siti']]);

        $this->assertArrayNotHasKey('no_identitas', $daftar[1]);
        $this->assertArrayNotHasKey('jenis_identitas', $daftar[1]);
    }

    public function test_identitas_ikut_bila_diisi(): void
    {
        $daftar = $this->service->susun('Rudi', [[
            'nama' => 'Siti',
            'jenis_identitas' => 'KTP',
            'no_identitas' => '3201234567890001',
        ]]);

        $this->assertSame('KTP', $daftar[1]['jenis_identitas']);
        $this->assertSame('3201234567890001', $daftar[1]['no_identitas']);
    }

    public function test_nama_dirapikan_dari_spasi_berlebih(): void
    {
        $daftar = $this->service->susun('  Rudi Hartono  ', [['nama' => " Siti \n"]]);

        $this->assertSame('Rudi Hartono', $daftar[0]['nama']);
        $this->assertSame('Siti', $daftar[1]['nama']);
    }

    public function test_nama_kosong_ditolak_bukan_dibuang_diam_diam(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('Rudi', [['nama' => '   ']]);
    }

    public function test_tamu_utama_tanpa_nama_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('  ', []);
    }

    public function test_melebihi_batas_ditolak(): void
    {
        $anggotaLain = array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN, ['nama' => 'Orang']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('Rudi', $anggotaLain);
    }

    public function test_tepat_di_batas_masih_diterima(): void
    {
        $anggotaLain = array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN - 1, ['nama' => 'Orang']);

        $this->assertCount(
            DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN,
            $this->service->susun('Rudi', $anggotaLain),
        );
    }

    public function test_nama_kepanjangan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('Rudi', [['nama' => str_repeat('a', 151)]]);
    }

    public function test_jenis_identitas_kepanjangan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('Rudi', [[
            'nama' => 'Siti',
            'jenis_identitas' => str_repeat('J', 21),
        ]]);
    }

    public function test_nomor_identitas_kepanjangan_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->susun('Rudi', [[
            'nama' => 'Siti',
            'no_identitas' => str_repeat('1', 101),
        ]]);
    }

    public function test_bolak_balik_json_tidak_mengubah_isi(): void
    {
        $daftar = $this->service->susun('Rudi', [[
            'nama' => 'Siti',
            'jenis_identitas' => 'KTP',
            'no_identitas' => '32',
        ]]);

        $this->assertSame($daftar, $this->service->dariJson($this->service->keJson($daftar)));
    }

    public function test_json_kosong_atau_rusak_dibaca_sebagai_daftar_kosong(): void
    {
        $this->assertSame([], $this->service->dariJson(null));
        $this->assertSame([], $this->service->dariJson(''));
        $this->assertSame([], $this->service->dariJson('bukan json'));
        $this->assertSame([], $this->service->dariJson('{"nama":"bukan array"}'));
    }

    public function test_json_array_kosong_tetap_dibaca_sebagai_array(): void
    {
        $this->assertSame([], $this->service->dariJson('[]'));
    }

    public function test_jumlah_sama_dengan_panjang_daftar(): void
    {
        $daftar = $this->service->susun('Rudi', [['nama' => 'Siti'], ['nama' => 'Budi']]);

        $this->assertSame(3, $this->service->jumlah($daftar));
    }
}
