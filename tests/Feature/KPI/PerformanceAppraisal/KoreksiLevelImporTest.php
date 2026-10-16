<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKoreksiLevelPaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Templat impor Koreksi Level PA tidak lagi menanyakan Kode Perusahaan: kode karyawan sudah
 * menunjuk satu orang, perusahaannya dicari dari data karyawan. Berkas lama yang masih
 * berkolom Kode Perusahaan tetap diterima supaya templat yang sudah beredar tidak tertolak.
 */
class KoreksiLevelImporTest extends TestCase
{
    use DatabaseTransactions;

    private function layanan(): PaKoreksiLevelPaService
    {
        return app(PaKoreksiLevelPaService::class);
    }

    /** @return array{0:object, 1:string} karyawan aktif tanpa koreksi + nama level PA yang berbeda dari level HRIS-nya */
    private function sasaran(): array
    {
        $k = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotExists(fn ($q) => $q->from('N_PA_Karyawan_Level_PA as lp')
                ->whereColumn('lp.Kode_Karyawan', 'k.Kode_Karyawan')->where('lp.Flag_Aktif', 'Y'))
            ->orderBy('k.Kode_Karyawan')
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'v.ID_Level']);
        if ($k === null) {
            $this->markTestSkipped('Tidak ada karyawan aktif tanpa koreksi di basis data ini.');
        }
        $lain = collect($this->layanan()->opsiLevel())->first(fn ($l) => $l['id'] !== (int) $k->ID_Level);

        return [$k, $lain['nama']];
    }

    private function berkas(array $baris): string
    {
        $buku = new Spreadsheet;
        $buku->getActiveSheet()->fromArray($baris, null, 'A1', true);
        $jalur = tempnam(sys_get_temp_dir(), 'lpa').'.xlsx';
        IOFactory::createWriter($buku, 'Xlsx')->save($jalur);

        return $jalur;
    }

    public function test_templat_tanpa_kolom_kode_perusahaan(): void
    {
        $jalur = tempnam(sys_get_temp_dir(), 'lpa').'.xlsx';
        file_put_contents($jalur, $this->layanan()->ekspor(true));
        $buku = IOFactory::load($jalur);

        $kepala = $buku->getSheet(0)->rangeToArray('A1:F1')[0];
        $this->assertSame(['Kode Karyawan', 'Level PA', 'Alasan', 'Berlaku Dari', 'Berlaku Sampai', null], $kepala);
        $teksPetunjuk = collect($buku->getSheetByName('Petunjuk')->toArray())->flatten()->filter()->implode(' ');
        $this->assertStringNotContainsStringIgnoringCase('Kode Perusahaan', $teksPetunjuk);
    }

    public function test_impor_mencari_perusahaan_dari_kode_karyawan(): void
    {
        [$k, $level] = $this->sasaran();

        $hasil = $this->layanan()->impor($this->berkas([
            ['Kode Karyawan', 'Level PA', 'Alasan', 'Berlaku Dari', 'Berlaku Sampai'],
            [$k->Kode_Karyawan, $level, 'Uji impor tanpa kode perusahaan', '', ''],
        ]));

        $this->assertSame(['dibuat' => 1, 'diperbarui' => 0], $hasil);
        $this->assertSame(
            trim((string) $k->Kode_Perusahaan),
            trim((string) DB::table('N_PA_Karyawan_Level_PA')->where('Kode_Karyawan', $k->Kode_Karyawan)->where('Flag_Aktif', 'Y')->value('Kode_Perusahaan')),
        );
    }

    public function test_berkas_lama_berkolom_kode_perusahaan_tetap_diterima(): void
    {
        [$k, $level] = $this->sasaran();

        $hasil = $this->layanan()->impor($this->berkas([
            ['Kode Karyawan', 'Kode Perusahaan', 'Level PA', 'Alasan', 'Berlaku Dari', 'Berlaku Sampai'],
            [$k->Kode_Karyawan, '', $level, 'Uji impor templat lama', '', ''],
        ]));

        $this->assertSame(['dibuat' => 1, 'diperbarui' => 0], $hasil);
    }

    public function test_kode_tak_dikenal_ditolak_dengan_nomor_baris(): void
    {
        $this->expectExceptionMessage('Baris 2 (TIDAKADA999)');

        $this->layanan()->impor($this->berkas([
            ['Kode Karyawan', 'Level PA', 'Alasan', 'Berlaku Dari', 'Berlaku Sampai'],
            ['TIDAKADA999', 'OFFICER', 'Uji kode tak dikenal', '', ''],
        ]));
    }
}
