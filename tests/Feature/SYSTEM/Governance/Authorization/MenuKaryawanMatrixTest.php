<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixExportService;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Matrix menu x karyawan di halaman Menu, Approval & Keanggotaan.
 *
 * "Sudah terima" = assign manual (HRIS_Page_Access aktif) saja, menu global
 * tidak ikut dinilai, dan layar serta berkas ekspor harus sepakat.
 */
class MenuKaryawanMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private int $grupId;

    /** @var array<int, string> */
    private array $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        $grupId = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'KPI')->value('Id_Grup_Fitur');

        if (! $admin || ! $grupId) {
            $this->markTestSkipped('Data dev tidak punya admin sistem atau grup KPI.');
        }

        $this->admin = $admin;
        $this->grupId = $grupId;
        $this->pages = $this->svc()->menuGrup($grupId)->pluck('Jenis_page')->all();

        if (count($this->pages) < 2) {
            $this->markTestSkipped('Grup KPI butuh minimal dua menu non-global.');
        }
    }

    private function svc(): MenuKaryawanMatrixService
    {
        return app(MenuKaryawanMatrixService::class);
    }

    /** Karyawan aktif berakun yang BELUM punya satu pun menu grup ini. */
    private function karyawanKosong(): object
    {
        $k = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.UserID_Web')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('HRIS_Page_Access as pa')
                ->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $this->pages))
            ->orderByDesc('k.UserID_Web')
            ->first(['k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama']);

        if (! $k) {
            $this->markTestSkipped('Tidak ada karyawan tanpa menu KPI di data dev.');
        }

        return $k;
    }

    private function beri(object $k, array $pages): void
    {
        // ID_Divisi/ID_Level wajib; diambil dari organisasi karyawannya
        // seperti snapshot yang ditulis jalur assign sungguhan.
        $org = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
            ->where('k.UserID_Web', $k->UserID_Web)
            ->first(['d.ID_Divisi', 'l.ID_Level']);

        foreach ($pages as $page) {
            DB::table('HRIS_Page_Access')->insert([
                'UserID_Web' => $k->UserID_Web,
                'Jenis_page' => $page,
                'ID_Divisi' => $org->ID_Divisi ?? 0,
                'ID_Level' => $org->ID_Level ?? 0,
                'Kode_Perusahaan' => '001',
                'Created_At' => now(),
                'Updated_At' => now(),
                'Flag_Aktif' => 'Y',
            ]);
        }
    }

    private function halamanUntuk(object $k, array $filter = []): array
    {
        return $this->svc()->halaman($this->admin, $this->grupId, ['search' => $k->Kode_Karyawan] + $filter, 1, 25);
    }

    private function barisUntuk(array $hasil, object $k): ?array
    {
        return collect($hasil['rows'])->firstWhere('UserID_Web', (int) $k->UserID_Web);
    }

    public function test_menu_global_tidak_ikut_dinilai(): void
    {
        $global = DB::table('N_HRIS_Menus')->whereIn('Jenis_page', $this->pages)->where('Is_Global_Menu', 'Y')->count();

        $this->assertSame(0, $global, 'Menu global dimiliki semua orang tanpa assign; kolomnya hanya jadi "belum" palsu.');
    }

    public function test_sel_mencerminkan_assign_manual_dan_jumlah_belum_benar(): void
    {
        $k = $this->karyawanKosong();
        $this->beri($k, [$this->pages[0]]);

        $baris = $this->barisUntuk($this->halamanUntuk($k), $k);

        $this->assertNotNull($baris, 'Karyawan aktif dalam cakupan admin sistem harus muncul.');
        $this->assertSame([$this->pages[0]], $baris['pages']);
        $this->assertSame(count($this->pages) - 1, $baris['jumlah_belum']);
    }

    public function test_akses_nonaktif_tidak_dihitung_terima(): void
    {
        $k = $this->karyawanKosong();
        $this->beri($k, [$this->pages[0]]);
        DB::table('HRIS_Page_Access')->where('UserID_Web', $k->UserID_Web)->where('Jenis_page', $this->pages[0])->update(['Flag_Aktif' => 'T']);

        $baris = $this->barisUntuk($this->halamanUntuk($k), $k);

        $this->assertSame([], $baris['pages']);
        $this->assertSame(count($this->pages), $baris['jumlah_belum']);
    }

    public function test_hanya_belum_menyembunyikan_yang_sudah_lengkap(): void
    {
        $k = $this->karyawanKosong();
        $this->beri($k, $this->pages);

        $this->assertNotNull($this->barisUntuk($this->halamanUntuk($k), $k), 'Tanpa saringan, orang lengkap tetap tampil.');
        $this->assertNull(
            $this->barisUntuk($this->halamanUntuk($k, ['hanya_belum' => true]), $k),
            'Saringan "hanya yang belum lengkap" harus menyembunyikan orang yang sudah lengkap.',
        );
    }

    /**
     * Rekap per menu menghitung seluruh populasi tersaring, bukan hanya
     * halaman yang sedang tampil — dan tidak ikut menyempit oleh hanya_belum.
     */
    public function test_rekap_menu_sepakat_dengan_baris(): void
    {
        $k = $this->karyawanKosong();

        // Diukur relatif: pencarian LIKE bisa ikut menjaring karyawan lain.
        $sebelum = collect($this->halamanUntuk($k)['menus'])->keyBy('Jenis_page');
        $this->beri($k, [$this->pages[0]]);

        foreach ([false, true] as $hanyaBelum) {
            $hasil = $this->halamanUntuk($k, ['hanya_belum' => $hanyaBelum]);
            $sesudah = collect($hasil['menus'])->keyBy('Jenis_page');

            $this->assertSame(
                $sebelum[$this->pages[0]]['jumlah_terima'] + 1,
                $sesudah[$this->pages[0]]['jumlah_terima'],
                'Satu assign baru harus menambah tepat satu penerima pada menunya (hanya_belum='.json_encode($hanyaBelum).').',
            );
            $this->assertSame($sebelum[$this->pages[1]]['jumlah_terima'], $sesudah[$this->pages[1]]['jumlah_terima']);

            foreach ($sesudah as $m) {
                $this->assertSame(
                    $sebelum[$m['Jenis_page']]['jumlah_terima'] + $sebelum[$m['Jenis_page']]['jumlah_belum'],
                    $m['jumlah_terima'] + $m['jumlah_belum'],
                    'Terima + belum per menu = seluruh populasi tersaring, tak menyempit oleh hanya_belum.',
                );
            }
        }
    }

    public function test_endpoint_menerima_hanya_belum_dari_query_string(): void
    {
        $k = $this->karyawanKosong();

        $res = $this->actingAs($this->admin)->getJson(
            "/api/v1/menu-approval-matrix/menu-binding/{$this->grupId}/matrix-karyawan?search={$k->Kode_Karyawan}&hanya_belum=false",
        );

        $res->assertOk();
        $this->assertContains((int) $k->UserID_Web, array_column($res->json('rows'), 'UserID_Web'));
    }

    public function test_ekspor_tiga_sheet_sepakat_dengan_layar(): void
    {
        Storage::fake(MenuKaryawanMatrixExportService::DISK);
        Queue::fake();

        $k = $this->karyawanKosong();
        $this->beri($k, [$this->pages[0]]);

        $ekspor = app(MenuKaryawanMatrixExportService::class);
        $id = $ekspor->makeExportId();
        $ekspor->startJob($id, 'xlsx', [
            'grup_id' => $this->grupId,
            'grup_kode' => 'KPI',
            'id_users' => (int) $this->admin->Id_Users,
            'filter' => ['search' => $k->Kode_Karyawan],
        ], 1, 'UJI');
        $ekspor->process($id);

        $job = DB::table(MenuKaryawanMatrixExportService::TABLE)->where('Export_Id', $id)->first();
        $this->assertSame(MenuKaryawanMatrixExportService::STATUS_READY, $job->Status, (string) $job->Error_Message);

        $tmp = tempnam(sys_get_temp_dir(), 'mkmuji');
        file_put_contents($tmp, Storage::disk(MenuKaryawanMatrixExportService::DISK)->get($job->File_Path));
        $buku = IOFactory::load($tmp);

        $this->assertSame(['Matrix', 'Belum Terima', 'Rekap Menu'], $buku->getSheetNames());

        $cari = fn (array $sheet) => collect($sheet)->first(fn ($r) => $r[0] === $k->Kode_Karyawan);

        $matrix = $cari($buku->getSheetByName('Matrix')->toArray());
        $this->assertNotNull($matrix, 'Karyawan uji harus ada di sheet Matrix.');
        $this->assertSame(count($this->pages) - 1, (int) $matrix[7], 'Kolom Jumlah Belum berkas harus sama dengan layar.');
        $this->assertSame('✓', $matrix[8], 'Menu pertama sudah di-assign.');
        $this->assertSame('—', $matrix[9]);

        $belum = $cari($buku->getSheetByName('Belum Terima')->toArray());
        $this->assertNotNull($belum);
        $this->assertSame(count($this->pages) - 1, (int) $belum[7]);
        $this->assertCount(count($this->pages) - 1, explode(', ', (string) $belum[8]));

        $this->assertCount(count($this->pages) + 1, $buku->getSheetByName('Rekap Menu')->toArray());

        $buku->disconnectWorksheets();
        @unlink($tmp);
    }

    public function test_ekspor_ditolak_bila_melebihi_plafon_sel(): void
    {
        $ekspor = app(MenuKaryawanMatrixExportService::class);

        $this->assertFalse($ekspor->melebihiPlafon(1000, 42), 'Ukuran modul terbesar di dev harus muat.');
        $this->assertTrue($ekspor->melebihiPlafon(5000, 42));
    }
}
