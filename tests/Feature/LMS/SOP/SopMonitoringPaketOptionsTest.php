<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Daftar paket untuk palet perintah layar Monitoring.
 *
 * KENAPA BERKAS INI ADA. SopMonitoring.vue memanggil
 * /api/v1/sop/admin/monitoring/paket-options sejak lama, rutenya tak pernah
 * ada, dan pemanggilnya menelan 404-nya sendiri lewat catch kosong bertanda
 * `// Non-fatal`. Akibatnya pencarian paket di palet SELALU memulangkan hasil
 * kosong — tanpa galat di layar, tanpa baris di log, tanpa satu pun tanda
 * bahwa ada yang rusak. Kegagalan yang paling lama bertahan memang yang tak
 * pernah berbunyi.
 *
 * Uji ini menuntut rutenya ada DAN bentuknya sesuai yang dibaca paletnya.
 */
class SopMonitoringPaketOptionsTest extends TestCase
{
    use DatabaseTransactions;

    private const ALAMAT = '/api/v1/sop/admin/monitoring/paket-options';

    private function adminMonitoring(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Jenis_page', 'menuSopMonitoringPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopMonitoringPage di database ini.');
        }

        return $u;
    }

    /** @return array<int, array<string, mixed>> */
    private function baris(): array
    {
        $r = $this->actingAs($this->adminMonitoring())->getJson(self::ALAMAT);

        $this->assertSame(200, $r->status(),
            'Rute paket-options tak terjawab. Selama ia 404, palet perintah gagal diam-diam.');

        return $r->json('result') ?? [];
    }

    public function test_membalas_daftar_paket_terbit(): void
    {
        $baris = $this->baris();

        $this->assertNotEmpty($baris,
            'Tak ada paket terbit di basis data ini, sehingga uji ini tak membuktikan apa pun.');

        foreach ($baris as $p) {
            $this->assertArrayHasKey('Id_SOP_Paket', $p);
            $this->assertArrayHasKey('Nama_Paket', $p);
        }
    }

    /**
     * Id keluar dalam bentuk hashid, bukan id mentah.
     *
     * Palet berpindah dengan nilai ini apa adanya, dan saringan monitoring
     * men-decode-nya di terapkanSaringanPaketRingkas(). Id mentah akan lolos
     * pemeriksaan bentuk tetapi membocorkan urutan kunci basis data.
     */
    public function test_id_paket_terenkode_bukan_id_mentah(): void
    {
        foreach ($this->baris() as $p) {
            $id = (string) $p['Id_SOP_Paket'];

            $this->assertFalse(ctype_digit($id),
                "Id paket '{$id}' terkirim mentah; seluruh id yang keluar modul ini wajib ter-hashid.");
            $this->assertNotEmpty(Hashids::connection('custom')->decode($id),
                "Id paket '{$id}' tak bisa di-decode kembali, jadi paletnya akan menyaring dengan nilai yang tak dikenal server.");
        }
    }

    /**
     * Hanya paket yang benar-benar beredar yang ditawarkan.
     *
     * Palet ini alat berpindah; menawarkan paket draft atau yang sudah
     * dinonaktifkan mengirim admin ke layar yang tak punya isi.
     */
    public function test_hanya_paket_aktif_dan_terbit(): void
    {
        $seharusnya = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Status', SopStatus::PAKET_PUBLISHED)
            ->whereNotNull('Nama_Paket')
            ->count();

        $this->assertSame($seharusnya, count($this->baris()),
            'Jumlah paket yang ditawarkan tak sama dengan paket aktif & terbit di master.');
    }

    public function test_menolak_tanpa_hak_monitoring(): void
    {
        $r = $this->getJson(self::ALAMAT);

        $this->assertContains($r->status(), [401, 403, 302],
            'Daftar paket terbuka untuk yang belum masuk.');
    }
}
