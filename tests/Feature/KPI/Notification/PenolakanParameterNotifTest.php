<?php

namespace Tests\Feature\KPI\Notification;

use App\Services\KPI\Notification\KpiNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Notifikasi penolakan KPI harus benar-benar SAMPAI.
 *
 * Latar belakangnya: KpiNotificationService memanggil resolveRejectionDomain()
 * di dua tempat, tapi metode itu tidak pernah ada. Setiap penolakan melempar
 * Error: Call to undefined method, lalu ditelan catch (\Throwable) di tiga
 * lapisan berturut-turut dan hanya menjadi baris warning di log. Tidak satu pun
 * notifikasi penolakan pernah terkirim — parameter maupun update performance,
 * tim maupun individu — dan tidak ada yang menyadarinya selama berbulan-bulan.
 *
 * Karena setiap lapisan sengaja memaafkan kegagalan (notifikasi tidak boleh
 * menggagalkan transaksi approval), tidak ada exception yang bisa dijadikan
 * penjaga. Test inilah penjaganya: ia memeriksa BARIS notifikasi benar-benar
 * lahir, bukan sekadar memeriksa method-nya tidak melempar.
 */
class PenolakanParameterNotifTest extends TestCase
{
    use DatabaseTransactions;

    private function domainDari(string $noTransaksi): array
    {
        $service = app(KpiNotificationService::class);
        $metode = new ReflectionMethod($service, 'resolveRejectionDomain');
        $metode->setAccessible(true);

        return $metode->invoke($service, $noTransaksi);
    }

    public function test_domain_dikenali_dari_tabel_bukan_dari_prefiks(): void
    {
        $param = DB::table('KPI_Pengajuan')->orderByDesc('Id_Pengajuan')->value('No_Transaksi');
        $perf = DB::table('KPI_Update_Performance')->orderByDesc('Id_Update_Performance')->value('No_Transaksi');

        if (! $param || ! $perf) {
            $this->markTestSkipped('Data dev tidak punya contoh kedua domain.');
        }

        [$domainParam, $barisParam] = $this->domainDari($param);
        $this->assertSame('parameter', $domainParam);
        $this->assertNotNull($barisParam);

        [$domainPerf, $barisPerf] = $this->domainDari($perf);
        $this->assertSame('performance', $domainPerf);
        $this->assertNotNull($barisPerf);
    }

    public function test_nomor_asing_tidak_dianggap_domain_mana_pun(): void
    {
        [$domain, $baris] = $this->domainDari('NOMOR-YANG-TIDAK-ADA-12345');

        $this->assertNull($domain);
        $this->assertNull($baris);
    }

    public function test_nomor_kosong_pulang_tanpa_menebak(): void
    {
        [$domain, $baris] = $this->domainDari('   ');

        $this->assertNull($domain);
        $this->assertNull($baris);
    }

    /**
     * Inti dari perbaikan ini: penolakan parameter TIM harus melahirkan
     * notifikasi untuk LEADER — bukan untuk anggota yang KPI-nya ditolak,
     * karena hanya leader yang bisa merevisi.
     */
    public function test_penolakan_parameter_tim_menotifikasi_leader(): void
    {
        $pengajuan = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->whereNotNull('Id_User_Sender')
            ->whereColumn('Id_User_Sender', '!=', 'Id_User')
            ->orderByDesc('Id_Pengajuan')
            ->first();

        if (! $pengajuan) {
            $this->markTestSkipped('Data dev tidak punya pengajuan parameter tim.');
        }

        $leader = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User_Sender)->first(['Kode_Karyawan', 'Nama']);
        $anggota = DB::table('Karyawan')->where('UserID_Web', $pengajuan->Id_User)->first(['Kode_Karyawan', 'Nama']);

        if (! $leader) {
            $this->markTestSkipped('Leader pengajuan tidak punya baris Karyawan.');
        }

        DB::table('N_HRIS_Notification')
            ->where('Reference_Id', $pengajuan->No_Transaksi)
            ->delete();

        $jumlah = app(KpiNotificationService::class)->notifyRequesterRejected(
            $pengajuan->No_Transaksi,
            'Target kuartal dua belum realistis',
            ['Bobot belum berjumlah 100'],
        );

        $this->assertSame(1, $jumlah, 'Notifikasi penolakan tidak terbuat sama sekali.');

        $notif = DB::table('N_HRIS_Notification')
            ->where('Reference_Id', $pengajuan->No_Transaksi)
            ->where('Kode_Karyawan', $leader->Kode_Karyawan)
            ->first();

        $this->assertNotNull($notif, 'Leader tidak menerima notifikasi penolakan.');

        // Judul & tautan harus milik domain parameter, bukan warisan
        // Update Performance yang dulu dipatok mati.
        $this->assertSame('Pengajuan Parameter KPI Ditolak', $notif->Title);
        $this->assertSame('/form-kpi/team', $notif->Action_Url);

        // Alasan global harus terbawa, kalau tidak leader tak tahu apa yang salah.
        $this->assertStringContainsString('Target kuartal dua belum realistis', $notif->Message);

        // "Periode: " kosong dulu terjadi karena $periode tidak pernah
        // didefinisikan. Parameter berlaku setahun, jadi yang benar adalah tahun.
        $this->assertStringNotContainsString('Periode: '."\n", $notif->Message);
        $this->assertMatchesRegularExpression('/Tahun: \d{4}/', $notif->Message);

        // Konteks tim: leader harus tahu ini KPI milik siapa.
        if ($anggota) {
            $this->assertStringContainsString($anggota->Nama, $notif->Message);
        }

        // Anggota TIDAK ikut dinotifikasi — ia tak punya tombol untuk memperbaikinya.
        if ($anggota && $anggota->Kode_Karyawan !== $leader->Kode_Karyawan) {
            $this->assertDatabaseMissing('N_HRIS_Notification', [
                'Reference_Id' => $pengajuan->No_Transaksi,
                'Kode_Karyawan' => $anggota->Kode_Karyawan,
            ]);
        }
    }
}
