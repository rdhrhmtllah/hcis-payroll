<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Kontrak HTTP halaman Otomasi SOP sesudah multi-paket.
 *
 * Yang dijaga: kiriman tanpa paket ditolak di pintu, dan riwayat mengembalikan
 * angka pemotongannya. Endpoint yang menerima aturan tanpa paket melahirkan
 * aturan yang tak pernah menugaskan apa pun dan tak pernah mengeluh.
 */
class SopKebijakanEndpointTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Akun uji dicari dari HRIS_Page_Access, mengikuti pola
     * SopExportStudioApiTest — tidak mengarang user, dan di-skip dengan pesan
     * jelas bila basis data dev tak punya akun yang cocok.
     */
    private function sebagaiAdmin(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopKebijakanPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $user = $uid ? User::find($uid) : null;
        if (! $user) {
            $this->markTestSkipped('Tak ada user dengan menuSopKebijakanPage di basis data ini.');
        }

        return $user;
    }

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Aturan')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    public function test_menyimpan_aturan_tanpa_paket_ditolak(): void
    {
        $r = $this->actingAs($this->sebagaiAdmin())
            ->postJson('/api/v1/sop/admin/kebijakan/rules', [
                'nama_aturan' => 'Aturan Tanpa Paket',
                'config' => ['groups' => [['tipe' => 'Karyawan']]],
                'paket' => [],
            ]);

        $r->assertStatus(422);
    }

    public function test_menyimpan_aturan_tanpa_kunci_paket_sama_sekali_ditolak(): void
    {
        $r = $this->actingAs($this->sebagaiAdmin())
            ->postJson('/api/v1/sop/admin/kebijakan/rules', [
                'nama_aturan' => 'Aturan Lupa Paket',
                'config' => ['groups' => [['tipe' => 'Karyawan']]],
            ]);

        $r->assertStatus(422);
    }

    public function test_menyimpan_aturan_dengan_dua_paket_berhasil(): void
    {
        $d = $this->dunia();
        $b = $d->paketTambahan('Paket Endpoint');
        $d->terbitkanPaketLain($b['paket']);

        $hash = fn (int $id) => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($id);

        $r = $this->actingAs($this->sebagaiAdmin())
            ->postJson('/api/v1/sop/admin/kebijakan/rules', [
                'nama_aturan' => $d->nama('Aturan Endpoint'),
                'config' => ['groups' => [['tipe' => 'Karyawan']]],
                'paket' => [
                    ['id_paket' => $hash($d->idPaket), 'urutan' => 1, 'override' => false],
                    ['id_paket' => $hash($b['paket']), 'urutan' => 2, 'override' => false],
                ],
            ]);

        $r->assertStatus(201)->assertJsonStructure(['result' => ['id']]);
    }

    public function test_riwayat_mengembalikan_plafon_dan_total(): void
    {
        $r = $this->actingAs($this->sebagaiAdmin())
            ->getJson('/api/v1/sop/admin/kebijakan/runs');

        $r->assertOk()
            ->assertJsonStructure(['result' => ['rows', 'ditampilkan', 'total_induk']]);
    }

    public function test_daftar_aturan_membawa_jumlah_paket(): void
    {
        $r = $this->actingAs($this->sebagaiAdmin())
            ->getJson('/api/v1/sop/admin/kebijakan/rules');

        $r->assertOk();

        $hasil = $r->json('result');
        if ($hasil) {
            $this->assertArrayHasKey('Jumlah_Paket', $hasil[0],
                'Daftar aturan tak membawa jumlah paket; layar tak punya apa pun untuk ditampilkan.');
            $this->assertArrayHasKey('Paket', $hasil[0]);
        }
    }
}
