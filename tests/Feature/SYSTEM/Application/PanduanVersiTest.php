<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Panduan\PanduanService;
use App\Services\SYSTEM\Application\Panduan\PanduanVersiService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Versioning berkas panduan (DDL-2026-149): riwayat tersimpan, draf hanya
 * untuk admin sampai terbit, rollback, dan tanda "Diperbarui" bagi pengguna.
 */
class PanduanVersiTest extends TestCase
{
    use DatabaseTransactions;

    private string $halaman;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(PanduanService::DISK);
        $this->halaman = (string) DB::table('N_HRIS_Menus')->orderBy('Jenis_page')->value('Jenis_page');
    }

    private function panduan(): PanduanService
    {
        return app(PanduanService::class);
    }

    private function versi(): PanduanVersiService
    {
        return app(PanduanVersiService::class);
    }

    private function pdf(string $nama = 'manual.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 12, 'application/pdf');
    }

    /** Turunan User: keputusan halaman lewat KeputusanAkses (RBAC Fase 4). */
    private function pengguna(): User
    {
        $u = new class extends User
        {
            public string $halamanTiruan = '';

            public function memilikiJabatan($page): bool
            {
                return $page === $this->halamanTiruan;
            }
        };
        $u->halamanTiruan = $this->halaman;

        return $u;
    }

    private function buat(): array
    {
        return $this->panduan()->buat(['judul' => 'Manual Versi', 'versi' => 'v1 - Sep'], $this->pdf('v1.pdf'), [['jenis_page' => $this->halaman]]);
    }

    private function barisPanduan(int $id): object
    {
        return DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $id)->first();
    }

    private function tampil(int $id): ?array
    {
        return collect($this->panduan()->untukHalaman($this->pengguna(), $this->halaman))->firstWhere('id', $id);
    }

    public function test_skema_versi_ada(): void
    {
        $kolom = fn (string $t) => array_map(fn ($r) => $r->COLUMN_NAME, DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$t]));

        $versi = $kolom(PanduanVersiService::T_VERSI);
        $this->assertNotEmpty($versi, 'Tabel N_HRIS_Panduan_Versi belum ada — jalankan database/ddl/DDL-2026-149-system-panduan-versi/forward.sql');
        foreach (['Id_Versi', 'Id_Panduan', 'No_Versi', 'Label_Versi', 'Catatan_Perubahan', 'Path_File', 'Status', 'Diterbitkan_At'] as $k) {
            $this->assertContains($k, $versi);
        }
        foreach (['Id_Versi_Aktif', 'Versi_Aktif_Sejak'] as $k) {
            $this->assertContains($k, $kolom(PanduanService::T_PANDUAN));
        }
    }

    public function test_panduan_baru_langsung_punya_versi_satu_yang_aktif(): void
    {
        $baru = $this->buat();
        $daftar = $this->versi()->daftar($baru['id'])['versi'];

        $this->assertCount(1, $daftar);
        $this->assertSame(1, $daftar[0]['no']);
        $this->assertSame('TERBIT', $daftar[0]['status']);
        $this->assertTrue($daftar[0]['aktif']);
        $this->assertSame('v1 - Sep', $daftar[0]['label']);
        $this->assertSame(1, $baru['no_versi']);

        // Panduan baru bukan "diperbarui".
        $this->assertFalse($this->tampil($baru['id'])['baru']);
    }

    public function test_draf_tidak_mengubah_yang_dilihat_pengguna(): void
    {
        $baru = $this->buat();
        $pathV1 = $this->barisPanduan($baru['id'])->Path_File;

        $daftar = $this->versi()->unggahVersi($baru['id'], $this->pdf('v2.pdf'), 'v2', 'Bab baru', false)['versi'];

        $this->assertSame(2, $daftar[0]['no']);
        $this->assertSame('DRAF', $daftar[0]['status']);
        $this->assertFalse($daftar[0]['aktif']);
        $this->assertSame($pathV1, $this->barisPanduan($baru['id'])->Path_File, 'Draf menggantikan berkas yang dilihat pengguna.');
        $this->assertFalse($this->tampil($baru['id'])['baru']);
        $this->assertSame(1, collect($this->panduan()->daftar())->firstWhere('id', $baru['id'])['jumlah_draf']);

        // Admin tetap bisa melihat berkas draf.
        $this->assertNotNull($this->versi()->berkas($baru['id'], $daftar[0]['id']));
    }

    public function test_terbitkan_draf_lalu_kembalikan_versi_lama(): void
    {
        $baru = $this->buat();
        $pathV1 = $this->barisPanduan($baru['id'])->Path_File;
        $idV1 = $this->versi()->daftar($baru['id'])['versi'][0]['id'];
        $idV2 = $this->versi()->unggahVersi($baru['id'], $this->pdf('v2.pdf'), 'v2', 'Bab persetujuan ditulis ulang', false)['versi'][0]['id'];

        $this->versi()->aktifkan($baru['id'], $idV2);
        $p = $this->barisPanduan($baru['id']);
        $this->assertSame($idV2, (int) $p->Id_Versi_Aktif);
        $this->assertSame('v2', $p->Versi);
        $this->assertNotSame($pathV1, $p->Path_File);
        Storage::disk(PanduanService::DISK)->assertExists($pathV1); // riwayat: berkas lama tidak dihapus

        $tampil = $this->tampil($baru['id']);
        $this->assertTrue($tampil['baru']);
        $this->assertSame('Bab persetujuan ditulis ulang', $tampil['catatan']);
        $this->assertNotNull($tampil['diperbarui']);

        // Rollback ke v1.
        $this->versi()->aktifkan($baru['id'], $idV1);
        $p = $this->barisPanduan($baru['id']);
        $this->assertSame($idV1, (int) $p->Id_Versi_Aktif);
        $this->assertSame($pathV1, $p->Path_File);
        $this->assertSame('v1 - Sep', $p->Versi);
        $this->assertTrue($this->tampil($baru['id'])['baru'], 'Rollback juga perubahan yang perlu diketahui pengguna.');

        $this->expectException(DomainException::class);
        $this->versi()->aktifkan($baru['id'], $idV1);
    }

    public function test_unggah_langsung_terbit_dan_nomor_berurutan(): void
    {
        $baru = $this->buat();
        $this->versi()->unggahVersi($baru['id'], $this->pdf('v2.pdf'), null, null, false);
        $daftar = $this->versi()->unggahVersi($baru['id'], $this->pdf('v3.pdf'), 'v3', null, true)['versi'];

        $this->assertSame([3, 2, 1], array_column($daftar, 'no'));
        $this->assertTrue($daftar[0]['aktif']);
        $this->assertSame('TERBIT', $daftar[0]['status']);
        $this->assertSame('DRAF', $daftar[1]['status']);
    }

    public function test_hanya_draf_yang_bisa_dihapus(): void
    {
        $baru = $this->buat();
        $draf = $this->versi()->unggahVersi($baru['id'], $this->pdf('v2.pdf'), null, null, false)['versi'][0];
        $pathDraf = DB::table(PanduanVersiService::T_VERSI)->where('Id_Versi', $draf['id'])->value('Path_File');

        $daftar = $this->versi()->hapusDraf($baru['id'], $draf['id'])['versi'];
        $this->assertSame([1], array_column($daftar, 'no'));
        Storage::disk(PanduanService::DISK)->assertMissing($pathDraf);

        $this->expectException(DomainException::class);
        $this->versi()->hapusDraf($baru['id'], $daftar[0]['id']);
    }

    public function test_panduan_lama_tanpa_riwayat_dicatat_sebagai_versi_satu(): void
    {
        $id = (int) DB::table(PanduanService::T_PANDUAN)->insertGetId([
            'Judul' => 'Panduan lama', 'Versi' => 'lama', 'Path_File' => 'panduan/uji/lama.pdf',
            'Nama_File_Asli' => 'lama.pdf', 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
            'Created_At' => now()->subMonths(2),
        ], 'Id_Panduan');
        DB::table(PanduanService::T_HALAMAN)->insert(['Id_Panduan' => $id, 'Jenis_page' => $this->halaman, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI']);

        $daftar = $this->versi()->daftar($id)['versi'];
        $this->assertCount(1, $daftar);
        $this->assertSame('lama', $daftar[0]['label']);
        $this->assertTrue($daftar[0]['aktif']);
        $this->assertSame('panduan/uji/lama.pdf', $this->barisPanduan($id)->Path_File);

        // Dipanggil ulang tidak menggandakan.
        $this->assertCount(1, $this->versi()->daftar($id)['versi']);

        $this->panduan()->lupakanCache();
        $this->assertFalse($this->tampil($id)['baru']);
    }

    public function test_tanda_diperbarui_berakhir_setelah_batas_hari(): void
    {
        $baru = $this->buat();
        $this->versi()->unggahVersi($baru['id'], $this->pdf('v2.pdf'), 'v2', 'x', true);
        $this->assertTrue($this->tampil($baru['id'])['baru']);

        DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $baru['id'])
            ->update(['Versi_Aktif_Sejak' => now()->subDays(PanduanVersiService::HARI_TANDA_BARU + 1)]);
        $this->panduan()->lupakanCache();

        $this->assertFalse($this->tampil($baru['id'])['baru']);
    }

    public function test_http_versi_khusus_system_admin_dan_alurnya(): void
    {
        $baru = $this->buat();

        $bukanAdmin = User::query()->where('Flag_Active', 'Y')->where(fn ($q) => $q->whereNull('Is_System_Admin')->orWhere('Is_System_Admin', '<>', 'Y'))->first();
        if ($bukanAdmin) {
            $this->actingAs($bukanAdmin)->getJson("/system/panduan/{$baru['id']}/versi")->assertForbidden();
        }

        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada System Admin aktif.');
        }

        $res = $this->actingAs($admin)->post("/system/panduan/{$baru['id']}/versi", [
            'berkas' => $this->pdf('v2.pdf'), 'label' => 'v2', 'catatan' => 'Ringkas', 'terbitkan' => '0',
        ], ['Accept' => 'application/json'])->assertCreated();
        $idDraf = $res->json('result.versi.0.id');
        $this->assertSame('DRAF', $res->json('result.versi.0.status'));

        Storage::disk(PanduanService::DISK)->put(
            DB::table(PanduanVersiService::T_VERSI)->where('Id_Versi', $idDraf)->value('Path_File'),
            '%PDF-1.4 draf'
        );
        $this->actingAs($admin)->get("/system/panduan/{$baru['id']}/versi/{$idDraf}/berkas")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs($admin)->postJson("/system/panduan/{$baru['id']}/versi/{$idDraf}/aktifkan")
            ->assertOk()->assertJsonPath('result.versi.0.aktif', true);

        // Berkas bukan PDF ditolak.
        $this->actingAs($admin)->post("/system/panduan/{$baru['id']}/versi", [
            'berkas' => UploadedFile::fake()->create('x.png', 5, 'image/png'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }
}
