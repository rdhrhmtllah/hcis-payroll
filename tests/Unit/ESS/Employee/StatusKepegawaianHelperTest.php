<?php

namespace Tests\Unit\ESS\Employee;

use App\Helpers\StatusKepegawaianHelper;
use Tests\TestCase;

/**
 * Label "Status Kepegawaian" di halaman profil.
 *
 * Semua uji di sini murni: petanya disuntikkan, jadi tidak ada satu pun kueri
 * ke database. Yang diuji adalah aturan penerjemahannya, bukan isi master.
 */
class StatusKepegawaianHelperTest extends TestCase
{
    /** Bentuk master yang sesungguhnya ada di N_HRIS_Aturan_Kelayakan_Cuti. */
    private function peta(): array
    {
        return StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 1,
                'Status_Kontrak' => 'PKWT',
                'Status_Karyawan' => 'Kontrak',
                'Keterangan' => 'KONTRAK',
            ],
            (object) [
                'Id_Aturan_Cuti' => 2,
                'Status_Kontrak' => 'PKWTT',
                'Status_Karyawan' => 'Tetap',
                'Keterangan' => 'TETAP',
            ],
        ]);
    }

    public function test_pkwt_menjadi_kontrak(): void
    {
        $this->assertSame('KONTRAK', StatusKepegawaianHelper::labelDariPeta('PKWT', $this->peta()));
    }

    public function test_pkwtt_menjadi_tetap_bukan_probation(): void
    {
        // Ini regresi yang sesungguhnya: userController lama memetakan PKWTT ke
        // 'Probation', padahal PKWTT adalah karyawan tetap menurut master.
        $label = StatusKepegawaianHelper::labelDariPeta('PKWTT', $this->peta());

        $this->assertSame('TETAP', $label);
        $this->assertNotSame('Probation', $label);
    }

    public function test_status_kontrak_null_tidak_ditebak(): void
    {
        // 950 dari 958 karyawan aktif ada di kondisi ini. Menebak = melabeli
        // mayoritas orang dengan status yang salah.
        $this->assertSame(
            StatusKepegawaianHelper::TIDAK_DIKETAHUI,
            StatusKepegawaianHelper::labelDariPeta(null, $this->peta()),
        );
    }

    public function test_string_kosong_dan_spasi_tidak_ditebak(): void
    {
        $peta = $this->peta();

        $this->assertSame(StatusKepegawaianHelper::TIDAK_DIKETAHUI, StatusKepegawaianHelper::labelDariPeta('', $peta));
        $this->assertSame(StatusKepegawaianHelper::TIDAK_DIKETAHUI, StatusKepegawaianHelper::labelDariPeta('   ', $peta));
    }

    public function test_nilai_asing_tidak_jatuh_ke_label_apa_pun(): void
    {
        $this->assertSame(
            StatusKepegawaianHelper::TIDAK_DIKETAHUI,
            StatusKepegawaianHelper::labelDariPeta('MAGANG', $this->peta()),
        );
    }

    public function test_pencocokan_abai_huruf_besar_kecil_dan_spasi_tepi(): void
    {
        $peta = $this->peta();

        $this->assertSame('KONTRAK', StatusKepegawaianHelper::labelDariPeta('pkwt', $peta));
        $this->assertSame('KONTRAK', StatusKepegawaianHelper::labelDariPeta('  PKWT  ', $peta));
        $this->assertSame('TETAP', StatusKepegawaianHelper::labelDariPeta('PkwtT', $peta));
    }

    public function test_nilai_baru_cukup_ditambahkan_di_master(): void
    {
        // Menambah baris PROBATION di master harus langsung membuat labelnya
        // hidup, tanpa menyentuh kode sama sekali.
        $peta = StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 3,
                'Status_Kontrak' => 'PROBATION',
                'Status_Karyawan' => 'Probation',
                'Keterangan' => 'PROBATION',
            ],
        ]);

        $this->assertSame('PROBATION', StatusKepegawaianHelper::labelDariPeta('PROBATION', $peta));
    }

    public function test_keterangan_kosong_jatuh_ke_status_karyawan(): void
    {
        $peta = StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 1,
                'Status_Kontrak' => 'PKWT',
                'Status_Karyawan' => 'Kontrak',
                'Keterangan' => null,
            ],
            (object) [
                'Id_Aturan_Cuti' => 2,
                'Status_Kontrak' => 'PKWTT',
                'Status_Karyawan' => 'Tetap',
                'Keterangan' => '   ',
            ],
        ]);

        $this->assertSame('Kontrak', StatusKepegawaianHelper::labelDariPeta('PKWT', $peta));
        $this->assertSame('Tetap', StatusKepegawaianHelper::labelDariPeta('PKWTT', $peta));
    }

    public function test_baris_tanpa_label_sama_sekali_diabaikan(): void
    {
        $peta = StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 9,
                'Status_Kontrak' => 'PKWT',
                'Status_Karyawan' => '',
                'Keterangan' => null,
            ],
        ]);

        $this->assertSame([], $peta);
        $this->assertSame(
            StatusKepegawaianHelper::TIDAK_DIKETAHUI,
            StatusKepegawaianHelper::labelDariPeta('PKWT', $peta),
        );
    }

    public function test_status_kontrak_kembar_dimenangkan_baris_terakhir(): void
    {
        // Kuerinya mengurutkan berdasar Id_Aturan_Cuti, jadi "terakhir" berarti
        // Id terbesar — bukan urutan acak yang dikembalikan server.
        $peta = StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 1,
                'Status_Kontrak' => 'PKWT',
                'Status_Karyawan' => 'Kontrak',
                'Keterangan' => 'LAMA',
            ],
            (object) [
                'Id_Aturan_Cuti' => 7,
                'Status_Kontrak' => 'pkwt',
                'Status_Karyawan' => 'Kontrak',
                'Keterangan' => 'BARU',
            ],
        ]);

        $this->assertSame(['PKWT' => 'BARU'], $peta);
    }

    public function test_baris_tanpa_status_kontrak_dilewati(): void
    {
        $peta = StatusKepegawaianHelper::petaDariBaris([
            (object) [
                'Id_Aturan_Cuti' => 1,
                'Status_Kontrak' => null,
                'Status_Karyawan' => 'Kontrak',
                'Keterangan' => 'KONTRAK',
            ],
            (object) [
                'Id_Aturan_Cuti' => 2,
                'Status_Kontrak' => '  ',
                'Status_Karyawan' => 'Tetap',
                'Keterangan' => 'TETAP',
            ],
        ]);

        $this->assertSame([], $peta);
    }
}
