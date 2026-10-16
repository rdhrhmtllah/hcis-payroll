<?php

namespace Tests\Integration\VISITOR\Governance\MasterData;

use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Master data menampilkan yang SEDANG BERLAKU, bukan yang pernah ada.
 *
 * Baris nonaktif yang ikut tampil membuat daftar memanjang oleh pilihan yang
 * justru sudah dihentikan, dan admin harus memeriksa lencana status setiap
 * kali sebelum percaya pada barisnya.
 *
 * Tetapi menyembunyikan tanpa jalan kembali akan MENGUBUR barisnya — itu
 * penghapusan yang menyamar, dan yang dinonaktifkan karena salah ketik tidak
 * akan pernah bisa dihidupkan lagi. Karena itu dua hal diuji bersama di sini:
 * bawaannya menyembunyikan, dan saringannya tetap bisa memunculkan.
 */
class MasterDataSembunyikanNonaktifTest extends TestCase
{
    use DatabaseTransactions;

    private const MASTER = 'purpose';

    private const TABEL = 'N_EMI_VISITOR_Master_Purpose';

    public function test_bawaan_tidak_memunculkan_baris_nonaktif(): void
    {
        [$aktif, $nonaktif] = $this->duaBaris();

        $nama = $this->namaHasil([]);

        $this->assertContains($aktif, $nama, 'Baris aktif justru ikut hilang.');
        $this->assertNotContains($nonaktif, $nama);
    }

    public function test_saringan_nonaktif_tetap_bisa_memunculkannya(): void
    {
        // Tanpa jalan kembali ini, menonaktifkan sama dengan menghapus.
        [$aktif, $nonaktif] = $this->duaBaris();

        $nama = $this->namaHasil(['Flag_Aktif' => 'T']);

        $this->assertContains($nonaktif, $nama, 'Baris nonaktif tidak bisa ditemukan lagi; ia terkubur.');
        $this->assertNotContains($aktif, $nama);
    }

    public function test_saringan_semua_memuat_keduanya(): void
    {
        [$aktif, $nonaktif] = $this->duaBaris();

        $nama = $this->namaHasil(['Flag_Aktif' => 'ALL']);

        $this->assertContains($aktif, $nama);
        $this->assertContains($nonaktif, $nama);
    }

    public function test_export_mengikuti_saringan_yang_sama(): void
    {
        // Berkas yang terunduh tidak boleh berisi baris yang barusan disaring
        // hilang dari tabel di layar; tidak ada galat, hanya angka yang tidak
        // cocok dan kepercayaan yang hilang pelan-pelan.
        [$aktif, $nonaktif] = $this->duaBaris();

        $nama = collect($this->service()->rowsForExport(self::MASTER, []))
            ->pluck('Nama_Keperluan')
            ->all();

        $this->assertContains($aktif, $nama);
        $this->assertNotContains($nonaktif, $nama);
    }

    // ---------------------------------------------------------------- helpers

    private function service(): VisitorMasterDataService
    {
        return app(VisitorMasterDataService::class);
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function namaHasil(array $filter): array
    {
        return collect($this->service()->list(self::MASTER, $filter + ['limit' => 500])['items'])
            ->pluck('Nama_Keperluan')
            ->all();
    }

    /** @return array{0: string, 1: string} [nama aktif, nama nonaktif] */
    private function duaBaris(): array
    {
        $aktif = 'Uji Aktif '.Str::upper(Str::random(8));
        $nonaktif = 'Uji Nonaktif '.Str::upper(Str::random(8));

        DB::table(self::TABEL)->insert([
            ['Nama_Keperluan' => $aktif, 'Flag_Aktif' => 'Y'],
            ['Nama_Keperluan' => $nonaktif, 'Flag_Aktif' => 'T'],
        ]);

        return [$aktif, $nonaktif];
    }
}
