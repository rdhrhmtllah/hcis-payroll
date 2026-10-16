<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Import\SopImporRegisterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Impor register dokumen dari Excel (identitas + versi, tanpa berkas).
 *
 * Yang dijaga: dry run tak menulis apa pun; jalankan menempuh gerbang tahap
 * sampai Pengesahan, bukan menulis Tahap_Id langsung; tanggal Excel menang atas
 * cap otomatis "hari ini"; revisi N melahirkan versi 00..N; dan mengulang berkas
 * yang sama tidak menggandakan dokumen.
 */
class SopImporRegisterTest extends TestCase
{
    use DatabaseTransactions;

    private const JUDUL = [
        'No. ', 'Jenis Dokumen', 'EMI', 'Bagian Pemilik Dokumen', 'Ruang Lingkup Dokumen', 'No. Process APQC',
        'No. Dokumen', 'Revisi', 'No. Dokumen Lengkap', 'Judul Dokumen', 'PIC', 'Status', 'Tanggal Permintaan',
        'Tanggal Respon ', 'Tanggal Akhir Approval', 'Tanggal Berlaku', 'Tanggal Filing Dokumen Softcopy',
        'Tanggal Filing Dokumen Fisik', 'Tanggal Sosialisasi', 'Cara Sosialisasi', 'Tanggal Obsolate',
    ];

    private array $berkas = [];

    protected function tearDown(): void
    {
        foreach ($this->berkas as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function svc(): SopImporRegisterService
    {
        return app(SopImporRegisterService::class);
    }

    /** No. Dokumen yang pasti belum dipakai pada kombinasi uji — dihitung, bukan diacak. */
    private function nomorBebas(): int
    {
        $maks = (int) DB::table('N_SOP_Dokumen')->max('No_Dokumen');

        return min(990, max(900, $maks + 1));
    }

    private function prasyarat(): void
    {
        foreach ([
            ['N_HRIS_Master_Entitas', 'Kode_Entitas', 'EMI'],
            ['N_HRIS_Master_Departemen', 'Kode_Departemen', 'SHE'],
            ['N_SOP_Master_APQC', 'Kode_Proses', '11'],
            ['N_SOP_Master_Jenis_Dokumen', 'Kode_Jenis_Dokumen', 'SOP'],
        ] as [$tabel, $kolom, $kode]) {
            if (! DB::table($tabel)->where($kolom, $kode)->where('Flag_Aktif', 'Y')->exists()) {
                $this->markTestSkipped("Master {$tabel}.{$kode} tidak aktif di basis data uji.");
            }
        }
    }

    /** @param  array<int, array<int, mixed>>  $baris */
    private function xlsx(array $baris): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(array_merge([self::JUDUL], $baris), null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'sopimp').'.xlsx';
        (new Xlsx($book))->save($path);

        return $this->berkas[] = $path;
    }

    private function baris(string $jenis, string $dep, int $nomor, string $revisi, array $tgl = []): array
    {
        $no = str_pad((string) $nomor, 3, '0', STR_PAD_LEFT);

        return [
            1, $jenis, 'EMI', $dep, 'EMI', '11', $no, $revisi, "{$jenis}/EMI/{$dep}/EMI/11/{$no}/{$revisi}",
            'UJI IMPOR '.$jenis.' '.$nomor, 'MIC', 'Filing and Registered',
            $tgl[0] ?? '18-Okt-2025', $tgl[1] ?? null, $tgl[2] ?? '30/10/2025', $tgl[3] ?? '01/11/2025',
            '01/11/2025', null, null, null, null,
        ];
    }

    private function jumlahDokumen(): int
    {
        return DB::table('N_SOP_Dokumen')->count();
    }

    public function test_dry_run_tidak_menulis_dan_menandai_galat(): void
    {
        $this->prasyarat();
        $n = $this->nomorBebas();
        $sebelum = $this->jumlahDokumen();

        $hasil = $this->svc()->dryRun($this->xlsx([
            $this->baris('SOP', 'SHE', $n, '00'),
            $this->baris('SOP', 'TIDAKADA', $n, '00'),
            $this->baris('SOP', 'SHE', $n, '00'),
            $this->baris('SOP', 'SHE', $n + 1, '00', ['tanggal ngawur']),
        ]));

        $this->assertSame($sebelum, $this->jumlahDokumen(), 'Dry run tidak boleh menulis dokumen.');
        $status = array_column($hasil['baris'], 'status', 'baris');
        $this->assertSame('SIAP', $status[2]);
        $this->assertSame('GALAT', $status[3], 'Departemen yang tak ada di master harus galat.');
        $this->assertSame('GALAT', $status[4], 'Identitas kembar di dalam berkas harus galat.');
        $this->assertSame('GALAT', $status[5], 'Tanggal yang tak terbaca harus galat, bukan dikosongkan diam-diam.');
        $this->assertArrayNotHasKey('_id', $hasil['baris'][0], 'Id master mentah tak ikut ke respons.');
        $this->assertSame(1, $hasil['ringkasan']['siap']);
        $this->assertArrayHasKey('PIC', $hasil['ringkasan']['kolom_tak_disimpan']);
    }

    public function test_jalankan_menempuh_tahap_sampai_pengesahan_dengan_tanggal_excel(): void
    {
        $this->prasyarat();
        $n = $this->nomorBebas();

        $hasil = $this->svc()->jalankan($this->xlsx([
            $this->baris('SOP', 'SHE', $n, '01', ['18-Okt-2025', null, '30/10/2025', '01/11/2025']),
        ]), 'uji.xlsx', 1);

        $this->assertSame(1, $hasil['ringkasan']['dibuat'], json_encode($hasil['baris'][0]['galat'] ?? []));

        $dok = DB::table('N_SOP_Dokumen')->where('Judul', 'UJI IMPOR SOP '.$n)->first();
        $this->assertNotNull($dok);
        $this->assertSame($n, (int) $dok->No_Dokumen);

        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 'v.Tahap_Id')
            ->where('v.Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->orderBy('v.Versi_Ke')
            ->get(['v.Versi_Ke', 't.Kode_Tahap', 'v.Tanggal_Permintaan', 'v.Tanggal_Akhir_Approval', 'v.Tanggal_Berlaku']);

        $this->assertSame([0, 1], $versi->pluck('Versi_Ke')->map(fn ($v) => (int) $v)->all(),
            'Revisi 01 melahirkan versi riwayat 00 dan versi 01.');
        $this->assertSame(['APPROVAL', 'APPROVAL'], $versi->pluck('Kode_Tahap')->all());

        $riwayat = $versi[0];
        $this->assertNull($riwayat->Tanggal_Permintaan, 'Cap "hari ini" tak boleh tertinggal pada versi riwayat.');
        $this->assertNull($riwayat->Tanggal_Akhir_Approval);

        $kini = $versi[1];
        $this->assertSame('2025-10-18', substr((string) $kini->Tanggal_Permintaan, 0, 10));
        $this->assertSame('2025-10-30', substr((string) $kini->Tanggal_Akhir_Approval, 0, 10));
        $this->assertSame('2025-11-01', substr((string) $kini->Tanggal_Berlaku, 0, 10));

        $this->assertTrue(DB::table('N_SOP_Log')->where('Aksi', 'DOK_IMPOR')
            ->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)->exists(),
            'Impor meninggalkan jejak DOK_IMPOR pada dokumennya.');
    }

    public function test_berkas_yang_sama_diulang_tidak_menggandakan(): void
    {
        $this->prasyarat();
        $n = $this->nomorBebas();
        $path = $this->xlsx([$this->baris('SOP', 'SHE', $n, '00')]);

        $this->svc()->jalankan($path, 'uji.xlsx', 1);
        $sesudahPertama = $this->jumlahDokumen();

        $ulang = $this->svc()->jalankan($path, 'uji.xlsx', 1);

        $this->assertSame($sesudahPertama, $this->jumlahDokumen());
        $this->assertSame('SUDAH_ADA', $ulang['baris'][0]['status']);
        $this->assertSame(0, $ulang['ringkasan']['dibuat']);
    }

    public function test_jalankan_bertahap_per_jendela_menulis_semua_tanpa_ganda(): void
    {
        $this->prasyarat();
        $n = $this->nomorBebas();
        $path = $this->xlsx([
            $this->baris('SOP', 'SHE', $n, '00'),
            $this->baris('SOP', 'SHE', $n + 1, '00'),
            $this->baris('SOP', 'SHE', $n + 2, '00'),
        ]);
        $sebelum = $this->jumlahDokumen();

        $a = $this->svc()->jalankan($path, 'uji.xlsx', 1, 0, 2);
        $this->assertSame(['mulai' => 0, 'sampai' => 2, 'total' => 3, 'berikutnya' => 2], $a['batch']);
        $this->assertCount(2, $a['baris'], 'Hanya baris di jendela yang diproses dan dilaporkan.');
        $this->assertSame(2, $a['ringkasan']['dibuat']);
        $this->assertSame($sebelum + 2, $this->jumlahDokumen());

        $b = $this->svc()->jalankan($path, 'uji.xlsx', 1, $a['batch']['berikutnya'], 2);
        $this->assertNull($b['batch']['berikutnya']);
        $this->assertSame(1, $b['ringkasan']['dibuat']);
        $this->assertSame($sebelum + 3, $this->jumlahDokumen());

        // Jendela pertama diulang (mis. permintaan terputus lalu dicoba lagi) tidak menggandakan.
        $ulang = $this->svc()->jalankan($path, 'uji.xlsx', 1, 0, 2);
        $this->assertSame(0, $ulang['ringkasan']['dibuat']);
        $this->assertSame(['SUDAH_ADA', 'SUDAH_ADA'], array_column($ulang['baris'], 'status'));
        $this->assertSame($sebelum + 3, $this->jumlahDokumen());
    }

    public function test_endpoint_dry_run_lewat_unggahan_dan_menolak_masukan_salah(): void
    {
        $this->prasyarat();
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')->whereNotNull('UserID_Web')->value('UserID_Web');
        if (! $uid) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }
        $user = \App\Models\ESS\Employee\Models\User::find($uid);
        $url = '/api/v1/sop/admin/dokumen/impor-register';
        $n = $this->nomorBebas();
        $sebelum = $this->jumlahDokumen();

        $xlsx = fn () => new \Illuminate\Http\UploadedFile(
            $this->xlsx([$this->baris('SOP', 'SHE', $n, '00')]), 'register.xlsx', null, null, true);

        $res = $this->actingAs($user)->post($url, ['berkas' => $xlsx(), 'mode' => 'dry_run'], ['Accept' => 'application/json']);
        $res->assertStatus(200);
        $this->assertSame(1, $res->json('result.ringkasan.siap'));
        $this->assertSame($sebelum, $this->jumlahDokumen(), 'Dry run lewat HTTP tidak menulis.');

        $this->actingAs($user)->post($url, ['berkas' => $xlsx(), 'mode' => 'hapus'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('mode');

        $csv = \Illuminate\Http\UploadedFile::fake()->createWithContent('register.csv', "a,b\n1,2\n");
        $this->actingAs($user)->post($url, ['berkas' => $csv, 'mode' => 'dry_run'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('berkas');
    }

    public function test_format_register_holding_tanpa_kolom_opsional_dan_kode_cetak_pemilik(): void
    {
        $this->prasyarat();
        $holding = DB::table('N_HRIS_Master_Entitas')->where('Flag_Aktif', 'Y')
            ->whereNotNull('Kode_Sebagai_Pemilik')->where('Kode_Sebagai_Pemilik', '<>', '')
            ->first(['Kode_Entitas', 'Kode_Sebagai_Pemilik']);
        if (! $holding) {
            $this->markTestSkipped('Tak ada entitas dengan kode cetak pemilik.');
        }
        $cetak = $holding->Kode_Sebagai_Pemilik;
        $n = $this->nomorBebas();
        $no = str_pad((string) $n, 3, '0', STR_PAD_LEFT);

        // Register Holding-Group: judul kolom entitas berbeda, TANPA Tanggal Respon & Tanggal Obsolate.
        $judul = ['No. ', 'Jenis Dokumen', 'PT Pemilik Dokumen', 'Bagian Pemilik Dokumen', 'Ruang Lingkup Dokumen',
            'No. Process APQC', 'No. Dokumen', 'Revisi', 'No. Dokumen Lengkap', 'Judul Dokumen', 'PIC', 'Status',
            'Tanggal Permintaan ', 'Tanggal Akhir Approval', 'Tanggal Berlaku', '@dropdown'];
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$judul, [
            1, 'SOP', $cetak, 'SHE', 'EMI', '11', $no, '00', "SOP/{$cetak}/SHE/EMI/11/{$no}/00",
            'UJI HOLDING', 'MIC', 'Filing and Registered', '17/07/2025', '01/08/2025', '25/07/2025', null,
        ]], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'sopimp').'.xlsx';
        (new Xlsx($book))->save($path);
        $this->berkas[] = $path;

        $hasil = $this->svc()->dryRun($path);
        $b = $hasil['baris'][0];

        $this->assertSame('SIAP', $b['status'], json_encode($b['galat']));
        $this->assertSame("SOP/{$cetak}/SHE/EMI/11/{$no}/00", $b['nomor_sistem'],
            'Pemilik dicocokkan lewat kode cetak, dan nomor terakit persis seperti di register.');
        $this->assertSame(['tanggal respon', 'tanggal obsolate'], $hasil['ringkasan']['kolom_tidak_ada']);
        $this->assertNull($b['versi'][0]['tanggal']['Tanggal_Respon']);
        $this->assertSame('2025-08-01', $b['versi'][0]['tanggal']['Tanggal_Akhir_Approval']);
    }

    public function test_api_bearer_gagal_tertutup_dan_dry_run_dengan_token_sah(): void
    {
        $this->prasyarat();
        $url = '/api/v1/sop/dokumen/impor-register';
        $pelaku = (int) DB::table('KPI_Users')->value('Id_Users');
        $n = $this->nomorBebas();
        $sebelum = $this->jumlahDokumen();
        $xlsx = fn () => new \Illuminate\Http\UploadedFile(
            $this->xlsx([$this->baris('SOP', 'SHE', $n, '00')]), 'register.xlsx', null, null, true);
        $kirim = fn (array $header, string $mode = 'dry_run') => $this->withHeaders($header + ['Accept' => 'application/json'])
            ->post($url, ['berkas' => $xlsx(), 'mode' => $mode]);

        // Token belum dikonfigurasi: permintaan tanpa Authorization pun tak boleh lolos (null === null).
        config(['services.hcis.sop_impor_register.token' => '', 'services.hcis.sop_impor_register.user_id' => $pelaku]);
        $kirim([])->assertStatus(401);

        config(['services.hcis.sop_impor_register.token' => 'token-uji-rahasia']);
        $kirim([])->assertStatus(401);
        $kirim(['Authorization' => 'Bearer salah'])->assertStatus(401);

        $ok = $kirim(['Authorization' => 'Bearer token-uji-rahasia']);
        $ok->assertStatus(200);
        $this->assertSame(1, $ok->json('result.ringkasan.siap'));
        $this->assertSame($sebelum, $this->jumlahDokumen(), 'Dry run lewat API tidak menulis.');

        // Pelaku tak dikonfigurasi: menolak, bukan menulis dokumen tanpa pelaku.
        config(['services.hcis.sop_impor_register.user_id' => '']);
        $kirim(['Authorization' => 'Bearer token-uji-rahasia'], 'jalankan')->assertStatus(503);
        $this->assertSame($sebelum, $this->jumlahDokumen());
    }
}
