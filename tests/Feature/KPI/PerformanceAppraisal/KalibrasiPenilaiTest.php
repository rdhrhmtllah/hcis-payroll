<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiException;
use App\Services\KPI\PerformanceAppraisal\PaKalibrasiPenilaiService;
use App\Services\KPI\PerformanceAppraisal\PaKirimanTinjauService;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaSkorEfektif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\KPI\PerformanceAppraisal\Support\MenyiapkanLembarPenilaian;
use Tests\TestCase;

/**
 * Menyimpan Kalibrasi Penilai (spec §5.1-5.2) dan ketahanannya terhadap pemetaan ulang.
 */
class KalibrasiPenilaiTest extends TestCase
{
    use DatabaseTransactions;
    use MenyiapkanLembarPenilaian;

    private function layanan(): PaKalibrasiPenilaiService
    {
        return app(PaKalibrasiPenilaiService::class);
    }

    /** Menjalankan simpan dan mengembalikan kode galatnya, atau null bila berhasil. */
    private function kodeGalat(callable $kerja): ?string
    {
        try {
            $kerja();

            return null;
        } catch (PaKalibrasiPenilaiException $e) {
            return $e->errorCode;
        }
    }

    public function test_menyimpan_revisi_tanpa_menyentuh_skor_asli(): void
    {
        $l = $this->lembarTerisi(['skor' => [70, 85], 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];

        $hasil = $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
            [['competency_id' => $k, 'skor' => 60]], 'Diselaraskan dengan rekan setim', 99);

        $this->assertSame(1, $hasil['diubah']);
        $this->assertSame('ATASAN', $hasil['kategori']);
        $this->assertSame($l['cycle_id'], $hasil['cycle_id']);

        $detail = DB::table('N_PA_Kalibrasi_Penilai_Detail')
            ->where('PA_Kalibrasi_Penilai_Id', $hasil['kalibrasi_id'])->first();
        $this->assertEquals(70, (float) $detail->Skor_Sebelum);
        $this->assertEquals(60, (float) $detail->Skor_Sesudah);
        $this->assertEquals(99, (int) DB::table('N_PA_Kalibrasi_Penilai')
            ->where('Id_PA_Kalibrasi_Penilai', $hasil['kalibrasi_id'])->value('Created_By'));

        $this->assertEquals(70, (float) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $l['payload_id'])->where('PA_Competency_Id', $k)->value('Skor'));
    }

    public function test_rantai_revisi_membawa_skor_sebelum_dari_revisi_sebelumnya(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];

        $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [['competency_id' => $k, 'skor' => 60]], 'Pertama', null);
        $kedua = $this->layanan()->simpan($l['penilai'], $l['assignment_id'], [['competency_id' => $k, 'skor' => 65]], 'Kedua', null);

        $this->assertEquals(60, (float) DB::table('N_PA_Kalibrasi_Penilai_Detail')
            ->where('PA_Kalibrasi_Penilai_Id', $kedua['kalibrasi_id'])->value('Skor_Sebelum'));
    }

    public function test_penugasan_milik_orang_lain_dijawab_tidak_ditemukan(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'Y']);
        $orangLain = (object) ['Kode_Perusahaan' => $l['penilai']->Kode_Perusahaan, 'Kode_Karyawan' => 'BUKAN-PENILAI'];

        $this->assertSame(PaKalibrasiPenilaiException::PENUGASAN_TIDAK_DITEMUKAN, $this->kodeGalat(
            fn () => $this->layanan()->simpan($orangLain, $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_hanya_lembar_terkumpul_yang_bisa_dikalibrasi(): void
    {
        foreach (['BELUM_MULAI', 'SELESAI', 'MENUNGGU_VERIFIKASI'] as $status) {
            $l = $this->lembarTerisi(['status_penugasan' => $status, 'boleh_kalibrasi' => 'Y']);

            $this->assertSame(PaKalibrasiPenilaiException::LEMBAR_BEKU, $this->kodeGalat(
                fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                    [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)), $status);
        }
    }

    public function test_kategori_yang_tidak_boleh_dikalibrasi_ditolak(): void
    {
        $l = $this->lembarTerisi(['boleh_kalibrasi' => 'T']);

        $this->assertSame(PaKalibrasiPenilaiException::KALIBRASI_DITUTUP, $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_siklus_yang_tidak_terbuka_ditolak(): void
    {
        $l = $this->lembarTerisi(['status_siklus' => 'CLOSED', 'boleh_kalibrasi' => 'Y']);

        $this->assertSame(PaKalibrasiPenilaiException::SIKLUS_TIDAK_TERBUKA, $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
                [['competency_id' => $l['kompetensi'][0], 'skor' => 10]], 'x', null)));
    }

    public function test_rentang_alasan_dan_perubahan_ditegakkan(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'skor_maksimal' => 100, 'boleh_kalibrasi' => 'Y']);
        $k = $l['kompetensi'][0];
        $simpan = fn (array $p, ?string $alasan = 'x') => $this->kodeGalat(
            fn () => $this->layanan()->simpan($l['penilai'], $l['assignment_id'], $p, $alasan, null));

        $this->assertSame(PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG, $simpan([['competency_id' => $k, 'skor' => 101]]));
        $this->assertSame(PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG, $simpan([['competency_id' => $k, 'skor' => -1]]));
        $this->assertSame(PaKalibrasiPenilaiException::KOMPETENSI_TIDAK_DI_LEMBAR, $simpan([['competency_id' => 999999, 'skor' => 10]]));
        $this->assertSame(PaKalibrasiPenilaiException::ALASAN_WAJIB, $simpan([['competency_id' => $k, 'skor' => 10]], '   '));
        $this->assertSame(PaKalibrasiPenilaiException::ALASAN_WAJIB, $simpan([['competency_id' => $k, 'skor' => 10]], str_repeat('a', 501)));
        $this->assertSame(PaKalibrasiPenilaiException::TIDAK_ADA_PERUBAHAN, $simpan([['competency_id' => $k, 'skor' => 70]]));

        $this->assertSame(0, DB::table('N_PA_Kalibrasi_Penilai')->where('PA_Assignment_Id', $l['assignment_id'])->count(),
            'Penolakan tidak boleh meninggalkan baris kepala.');
    }

    /**
     * UJI TERPENTING FITUR INI. Pemetaan ulang menonaktifkan baris skor lama dan menulis yang
     * baru; kalibrasi yang menempel ke id baris akan hilang tanpa jejak. Payloadnya dikirim
     * lewat PEMETA SUNGGUHAN supaya yang diuji adalah jalur produksi, bukan tiruannya.
     */
    public function test_kalibrasi_selamat_melewati_petakan_ulang(): void
    {
        $siap = $this->penugasanBerpintuUntukPemeta();

        $muatan = json_decode((string) file_get_contents(
            base_path('docs/KPI/performance-appraisal/contoh-callback-hclearn.json')), true)['final_tanpa_esai'];
        unset($muatan['_kapan']);
        $muatan['Api_Reference'] = $siap['api_ref'];
        $muatan['Id_Integrasi'] = $siap['integration_id'];
        $muatan['Kode_Karyawan'] = $siap['penilai']->Kode_Karyawan;

        app(PaPayloadMapperService::class)->terimaDanPetakan($siap['api_ref'], $muatan);

        $this->assertSame('TERKUMPUL', DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $siap['assignment_id'])->value('Status_Penugasan'),
            'Prasyarat: kiriman FINAL harus berhenti di TERKUMPUL.');

        $k = $siap['kompetensi'][0]; // butir BE, skor asli 21 dari 100
        $this->layanan()->simpan($siap['penilai'], $siap['assignment_id'],
            [['competency_id' => $k, 'skor' => 60]], 'Diselaraskan', null);

        $payloadId = (int) DB::table('N_PA_Assignment_Payload')
            ->where('PA_Assignment_Integration_Id', $siap['integration_id'])->value('Id_PA_Assignment_Payload');
        app(PaKirimanTinjauService::class)->petakanUlang($siap['cycle_id'], $payloadId, null);

        $this->assertGreaterThan(1, (int) DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $payloadId)->max('Mapper_Version'),
            'Prasyarat: pemetaan ulang harus benar-benar menulis versi baru.');

        $butir = app(PaSkorEfektif::class)->butirBerskor([$siap['assignment_id']])[$siap['assignment_id']]
            ->keyBy('PA_Competency_Id');

        $this->assertSame(60.0, $butir[$k]->Skor, 'Kalibrasi hilang sesudah pemetaan ulang.');
        $this->assertSame(21.0, $butir[$k]->Skor_Asli);
    }

    public function test_riwayat_peserta_memuat_rantai_perubahan(): void
    {
        $l = $this->lembarTerisi(['skor' => [70], 'boleh_kalibrasi' => 'Y']);
        $this->layanan()->simpan($l['penilai'], $l['assignment_id'],
            [['competency_id' => $l['kompetensi'][0], 'skor' => 60]], 'Pertama', null);

        $riwayat = $this->layanan()->riwayatPeserta($l['participant_id']);

        $this->assertCount(1, $riwayat);
        $this->assertSame('Pertama', $riwayat[0]['alasan']);
        $this->assertSame('ATASAN', $riwayat[0]['kategori']['kode']);
        $this->assertEquals(70.0, $riwayat[0]['perubahan'][0]['skor_sebelum']);
        $this->assertEquals(60.0, $riwayat[0]['perubahan'][0]['skor_sesudah']);
    }

    /**
     * Paket dua butir (BE, KWN) persis contoh HC Learn, dengan user uji sebagai penilai.
     * Bentuknya meniru CallbackKontrakHcLearnTest::siapkanPenugasanBerpintu().
     *
     * @return array{cycle_id:int, assignment_id:int, integration_id:int, api_ref:string, kompetensi:list<int>, penilai:object}
     */
    private function penugasanBerpintuUntukPemeta(): array
    {
        $user = $this->penilaiUji();
        $penilai = DB::table('Karyawan')->where('UserID_Web', $user->Id_Users)->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);
        $dinilai = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('ID_Level_Jabatan')
            ->where('Kode_Karyawan', '<>', $penilai->Kode_Karyawan)
            ->orderBy('Kode_Karyawan')->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan']);

        $cycleId = $this->siklusLembar('OPEN');
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');
        $kategoriId = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'ATASAN')->value('Id_PA_Rater_Category');
        DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $kategoriId)->update(['Flag_Boleh_Kalibrasi' => 'Y']);

        $paketId = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'PA_Rater_Category_Id' => $kategoriId,
            'Id_Master_Ujian' => '900900',
            'Nama_Ujian_Sumber' => 'Employee Assessment 360 - STAFF',
            'Sumber_Kontrak' => 'HCLEARN',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Cycle_Question_Package');

        $kompetensi = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->where('Flag_Berskor', 'Y')
            ->orderBy('Id_PA_Competency')->limit(2)->pluck('Id_PA_Competency')->map(fn ($x) => (int) $x)->all();
        $this->assertCount(2, $kompetensi, 'Master kompetensi berskor kurang dari dua baris.');

        foreach ([['BE', 'Business Ethic'], ['KWN', 'Kewarganegaraan']] as $i => [$kode, $nama]) {
            DB::table('N_PA_Cycle_Question_Package_Item')->insert([
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Jenis_Indikator' => '900910',
                'Id_Jenis_Soal' => (string) (900920 + $i),
                'Kode_Soal_Sumber' => $kode,
                'Nama_Alat_Tes' => 'Employee Assessment 360 - STAFF',
                'Nama_Bagian_Sumber' => $nama,
                'PA_Competency_Id' => $kompetensi[$i],
                'Urutan_Tampil' => $i + 1,
                'Flag_Aktif' => 'Y',
            ]);
        }

        $participantId = (int) DB::table('N_PA_Participant')->insertGetId([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $dinilai->Kode_Perusahaan,
            'Kode_Karyawan' => $dinilai->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $dinilai->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $dinilai->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ], 'Id_PA_Participant');

        $assignmentId = (int) DB::table('N_PA_Assignment')->insertGetId([
            'PA_Participant_Id' => $participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ], 'Id_PA_Assignment');

        $apiRef = bin2hex(random_bytes(16));
        $integrationId = (int) DB::table('N_PA_Assignment_Integration')->insertGetId([
            'PA_Assignment_Id' => $assignmentId,
            'PA_Cycle_Question_Package_Id' => $paketId,
            'Api_Ref' => $apiRef,
            'Short_Token' => 'UJIKL'.substr(bin2hex(random_bytes(2)), 0, 3),
            'Status_Integrasi' => 'BERHASIL',
            'Flag_Aktif' => 'Y',
        ], 'Id_PA_Assignment_Integration');

        return [
            'cycle_id' => $cycleId,
            'assignment_id' => $assignmentId,
            'integration_id' => $integrationId,
            'api_ref' => $apiRef,
            'kompetensi' => $kompetensi,
            'penilai' => $penilai,
        ];
    }
}
