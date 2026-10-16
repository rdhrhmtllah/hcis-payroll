<?php

namespace Tests\Integration\KPI\PerformanceAppraisal\Export;

use App\Services\KPI\PerformanceAppraisal\Export\PaSystemTemplates;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\ReportCompiler;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rantai baca Tahap 2: katalog -> template resmi -> compiler/runner -> SQL Server.
 *
 * Seluruh uji ini BACA-SAJA. Endpoint HTTP sengaja tidak dipakai: login admin menulis
 * state pengguna ke database development, sedangkan controller/rute/auth sudah dijaga
 * terpisah di `PaExportStudioAksesTest`. Yang belum pernah dibuktikan Tahap 1 adalah SQL
 * nyata dari dua dataset baru dan dua template resminya.
 */
class PaExportStudioTahapDuaTest extends TestCase
{
    private function dataset(string $kunci)
    {
        return app(DatasetRegistry::class)->ambil('pa', $kunci);
    }

    private function lingkupAdmin(): Lingkup
    {
        return new Lingkup('AD1', true, [], true);
    }

    public static function templateTahapDua(): array
    {
        return [
            'kompetensi' => ['kompetensi', 'pa-gap-kompetensi'],
            'peserta' => ['peserta', 'pa-audit-cakupan'],
        ];
    }

    /**
     * @dataProvider templateTahapDua
     */
    public function test_template_resmi_tahap_dua_lolos_validator_dan_berjalan(
        string $datasetKunci,
        string $templateKode,
    ): void {
        $template = PaSystemTemplates::semua()[$templateKode] ?? null;
        $this->assertNotNull($template, "Template sistem {$templateKode} tidak ada.");
        $this->assertSame($datasetKunci, $template['dataset']);

        $dataset = $this->dataset($datasetKunci);
        $definisi = DefinisiLaporan::dariArray($template['definisi']);
        $galat = app(DefinisiValidator::class)->periksa($definisi, $dataset);
        $this->assertSame([], $galat, "Template {$templateKode} tidak sah: ".json_encode($galat));

        $hasil = app(ReportRunner::class)->jalankan(
            $definisi,
            $dataset,
            $this->lingkupAdmin(),
            'UJI TAHAP DUA',
            5,
        );

        foreach (['title', 'generated_at', 'columns', 'rows', 'catatan'] as $kunci) {
            $this->assertArrayHasKey($kunci, $hasil);
        }

        $this->assertNotEmpty($hasil['columns']);
        $this->assertIsArray($hasil['rows']);
    }

    public function test_dataset_peserta_memuat_orang_dikecualikan_tanpa_hasil(): void
    {
        $dataset = $this->dataset('peserta');
        $jumlahSumber = DB::table('N_PA_Participant')
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Peserta', 'DIKECUALIKAN')
            ->count();
        $this->assertGreaterThan(0, $jumlahSumber, 'Tidak ada peserta dikecualikan; uji ini tak membuktikan apa pun.');

        $definisi = DefinisiLaporan::dariArray([
            'dataset' => 'peserta',
            'mode' => 'rinci',
            'kolom' => ['kode_cycle', 'kode_karyawan', 'status_peserta', 'alasan_dikecualikan'],
            'saringan' => [[
                'field' => 'status_peserta',
                'op' => 'sama_dengan',
                'nilai' => 'DIKECUALIKAN',
            ]],
        ]);

        $baris = app(ReportCompiler::class)
            ->susun($definisi, $dataset, $this->lingkupAdmin())
            ->limit(5)
            ->get();

        $this->assertNotEmpty($baris);
        foreach ($baris as $r) {
            $this->assertSame('DIKECUALIKAN', $r->status_peserta);
        }
    }

    /**
     * Spec mewajibkan tujuh saringan umum pada SETIAP dataset, bukan hanya yang baru.
     * Matriks ini menjaga seluruh permukaan sekaligus: `saringan()` menentukan field yang
     * boleh dipakai definisi, `opsiSaringan()` menentukan dropdown yang benar-benar bisa
     * dipilih admin. Salah satu hilang menghasilkan field yang tampak ada tetapi tidak bisa
     * dipilih, atau dropdown yang ditolak validator.
     */
    public function test_keempat_dataset_menyediakan_saringan_dan_opsi_organisasi_lengkap(): void
    {
        $wajib = ['kode_cycle', 'tahun', 'divisi', 'sub_divisi', 'level', 'jabatan', 'lokasi'];
        $wajibBeropsi = ['kode_cycle', 'divisi', 'sub_divisi', 'level', 'jabatan', 'lokasi'];

        foreach (['hasil', 'penugasan', 'kompetensi', 'peserta'] as $kunci) {
            $dataset = $this->dataset($kunci);
            $saringan = array_map(fn ($s) => $s->key, $dataset->saringan());
            $opsi = $dataset->opsiSaringan();

            foreach ($wajib as $field) {
                $this->assertContains($field, $saringan, "Dataset {$kunci} kehilangan saringan umum {$field}.");
            }

            foreach ($wajibBeropsi as $field) {
                $this->assertArrayHasKey($field, $opsi, "Dataset {$kunci} kehilangan opsi saringan {$field}.");
                $this->assertIsArray($opsi[$field]);
            }
        }
    }

    public function test_dataset_kompetensi_hanya_memuat_baris_agregat_lintas_kategori(): void
    {
        $dataset = $this->dataset('kompetensi');
        $jumlahAgregat = DB::table('N_PA_Result_Competency')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('PA_Rater_Category_Id')
            ->count();
        $this->assertGreaterThan(0, $jumlahAgregat, 'Tidak ada kompetensi agregat; uji ini tak membuktikan apa pun.');

        /* Jumlah grain dataset harus PERSIS baris agregat aktif yang tetap punya result,
           participant, dan master kompetensi aktif. Kalau whereNull dicabut, angka ini
           melonjak karena baris per kategori penilai ikut terbaca. */
        $jumlahDataset = $dataset->baseQuery()->count();
        $jumlahAcuan = DB::table('N_PA_Result_Competency as rc')
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.Id_PA_Result', '=', 'rc.PA_Result_Id')->where('r.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Participant as p', function ($j) {
                $j->on('p.Id_PA_Participant', '=', 'r.PA_Participant_Id')->where('p.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Competency as k', function ($j) {
                $j->on('k.Id_PA_Competency', '=', 'rc.PA_Competency_Id')->where('k.Flag_Aktif', 'Y');
            })
            ->where('rc.Flag_Aktif', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id')
            ->count();

        $this->assertSame($jumlahAcuan, $jumlahDataset);
    }
}
