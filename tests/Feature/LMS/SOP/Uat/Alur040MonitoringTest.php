<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopMonitoringPeopleService;
use App\Services\LMS\SOP\SopMonitoringService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Monitoring & laporan kepatuhan — jalur normal.
 *
 * UAT-SOP-040 memantau kepatuhan sebuah paket · 041 menelusuri seorang karyawan
 * lintas paket berikut rekam bacanya.
 *
 * Yang dijaga di sini adalah KESESUAIAN ANGKA dengan kenyataan yang dibentuk
 * uji ini sendiri — bukan sekadar "halamannya memuat sesuatu". Monitoring yang
 * menampilkan angka salah lebih berbahaya daripada monitoring yang kosong:
 * yang kosong membuat orang bertanya, yang salah membuat orang berhenti
 * bertanya.
 */
class Alur040MonitoringTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode;

    private function dunia(int $jumlahItem = 2): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $karyawan = UatAkun::karyawanBiasa();
        $kode = $karyawan ? UatAkun::kodeKaryawan($karyawan) : null;
        if (! $kode) {
            $this->markTestSkipped('Tidak ada akun karyawan yang tertaut data karyawan.');
        }
        $this->kode = $kode;

        try {
            $d = UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket();

            for ($i = 1; $i <= $jumlahItem; $i++) {
                $d->tambahItem("Item {$i}", wajib: true);
            }

            return $d->terbitkanPaket()->tugaskanKe($kode);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[Group('UAT-SOP-040')]
    public function test_monitoring_paket_menghitung_penugasan_yang_baru_dibuat(): void
    {
        $d = $this->dunia();

        $detail = app(SopMonitoringService::class)->detailPaket($d->idPaket);
        $datar = json_encode($detail, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString($this->kode, $datar,
            'Karyawan yang baru ditugaskan tidak muncul pada monitoring paket. Admin '
            .'tak akan pernah tahu siapa yang belum membaca.');
    }

    /**
     * Corong kepatuhan: target, sudah dibuka, sudah dikonfirmasi.
     *
     * Angkanya dituntut PERSIS, bukan sekadar "berubah". Bentuk kueri sebelumnya
     * memakai EXISTS di dalam SUM, yang ditolak SQL Server — endpoint
     * /paket/{id}/funnel tak pernah sekali pun berhasil dijalankan. Setelah
     * diperbaiki dengan LEFT JOIN, bahaya berikutnya adalah penggandaan baris:
     * satu penugasan punya banyak baris progres, dan join tanpa DISTINCT membuat
     * target dilaporkan lebih besar daripada jumlah orang yang ditugaskan.
     * Karena itu jumlah target diperiksa terhadap angka yang diketahui: satu.
     */
    #[Group('UAT-SOP-040')]
    public function test_corong_kepatuhan_menghitung_target_dan_yang_sudah_membuka(): void
    {
        $d = $this->dunia(2);
        $svc = app(SopMonitoringService::class);

        $awal = $svc->funnelPaket($d->idPaket);

        $this->assertSame(1, $awal['target'],
            'Target corong bukan 1 padahal paket ini ditugaskan ke tepat satu orang — '
            .'baris kemungkinan tergandakan oleh join ke progres.');
        $this->assertSame(0, $awal['dibuka'],
            'Karyawan yang belum membuka apa pun sudah terhitung membuka.');

        // Karyawan membuka salah satu itemnya.
        DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $d->detailId())
            ->where('Id_SOP_Paket_Item', $d->idItem[0])
            ->update(['Waktu_Buka' => now()]);

        $sesudah = $svc->funnelPaket($d->idPaket);

        $this->assertSame(1, $sesudah['target'],
            'Target berubah hanya karena satu item dibuka; barisnya tergandakan.');
        $this->assertSame(1, $sesudah['dibuka']);
        $this->assertSame(0, $sesudah['dikonfirmasi'],
            'Membuka bukan mengkonfirmasi — keduanya tak boleh tertukar.');
    }

    #[Group('UAT-SOP-041')]
    public function test_penelusuran_karyawan_menampilkan_paket_yang_ditugaskan(): void
    {
        $d = $this->dunia();
        $svc = app(SopMonitoringPeopleService::class);

        $ringkas = $svc->employeeRingkas($this->kode);

        $this->assertNotNull($ringkas,
            'Karyawan yang punya penugasan aktif justru tak bisa ditelusuri.');

        $paket = json_encode($svc->paketTersaring($this->kode, []), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString($d->nama('Paket'), $paket,
            'Paket yang ditugaskan tidak muncul saat karyawannya ditelusuri — pertanyaan '
            .'audit tentang satu orang tak bisa dijawab dari layar ini.');
    }

    /**
     * Rekam baca adalah bukti kepatuhan per orang: kapan ia membuka, kapan ia
     * mengkonfirmasi. Tanpa itu, "sudah membaca" hanya klaim tanpa jejak.
     */
    #[Group('UAT-SOP-041')]
    public function test_rekam_baca_mencatat_item_yang_sudah_dikonfirmasi(): void
    {
        $d = $this->dunia();
        $d->tandaiSelesai($d->idItem[0]);

        $rekam = json_encode(
            app(SopMonitoringPeopleService::class)->rekamBaca($this->kode),
            JSON_UNESCAPED_UNICODE
        );

        $this->assertStringContainsString($d->nama('Item 1'), $rekam,
            'Item yang sudah dikonfirmasi tidak muncul pada rekam baca karyawan.');
    }
}
