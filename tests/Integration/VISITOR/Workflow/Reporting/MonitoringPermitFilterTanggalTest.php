<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Rentang tanggal di Monitoring Permit punya DUA makna yang sering tertukar.
 *
 * "Tanggal approve" menjawab kapan keputusannya diambil; "masa berlaku"
 * menjawab kapan kartunya sah dipakai. Keduanya hampir selalu berbeda, dan
 * permit yang disetujui Maret untuk masa berlaku Oktober adalah bukti
 * hidupnya — itulah sebabnya modenya dipilih pemakai, bukan ditebak sistem.
 *
 * Yang dijaga di sini adalah PERBEDAANNYA. Uji yang hanya memeriksa "filter
 * mengembalikan lebih sedikit baris" akan tetap hijau walau kedua mode
 * diam-diam memfilter kolom yang sama — persis bentuk kegagalan yang tidak
 * menimbulkan galat dan hanya terbaca sebagai angka laporan yang meleset.
 *
 * Mode masa berlaku memakai IRISAN, bukan pengurungan: permit setahun yang
 * sedang berlaku pada minggu yang ditanyakan tetap harus muncul meski
 * rentangnya jauh lebih panjang dari pertanyaannya.
 *
 * SELURUH fixture di sini sengaja bermasa berlaku yang BELUM lewat. Daftar
 * Monitoring Permit hanya memuat status APPROVED dan REVOKED, jadi permit
 * yang sudah kedaluwarsa tak akan pernah muncul — apa pun filternya. Fixture
 * kedaluwarsa membuat assertNotContains lulus tanpa menyentuh filter sama
 * sekali, dan penjaganya berubah jadi hiasan. Itulah sebabnya setiap uji
 * "tidak ikut" di bawah ini disertai KENDALI POSITIF: baris yang sama harus
 * terbukti bisa muncul pada rentang yang memang mengenainya.
 */
class MonitoringPermitFilterTanggalTest extends TestCase
{
    use DatabaseTransactions;

    private const KUNCI = 'visitorMonitoringPermitPage';

    private const URL = '/laporanvisitor-permit';

    /** Penanda unik agar hasilnya bersih dari data development. */
    private string $tanda;

    private static int $urutanPengguna = 0;

    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
        $this->tanda = 'FT'.Str::upper(Str::random(8));
    }

    public function test_mode_approve_dan_mode_berlaku_menyaring_sumbu_yang_berbeda(): void
    {
        // Inti uji ini. Permit yang disetujui Maret tetapi baru berlaku
        // Oktober: ia HARUS muncul di mode approve untuk rentang Maret, dan
        // HARUS hilang di mode berlaku untuk rentang yang sama.
        $silang = $this->buatPermit(
            disetujui: '2026-03-15',
            mulai: '2026-10-01',
            akhir: '2026-10-31',
        );

        $this->assertContains(
            $silang,
            $this->nomorHasil(['mode_tanggal' => 'approve', 'tanggal_dari' => '2026-03-01', 'tanggal_sampai' => '2026-03-31']),
            'Mode approve tidak melihat tanggal persetujuan.',
        );

        $this->assertNotContains(
            $silang,
            $this->nomorHasil(['mode_tanggal' => 'berlaku', 'tanggal_dari' => '2026-03-01', 'tanggal_sampai' => '2026-03-31']),
            'Mode berlaku ikut menyaring tanggal persetujuan; kedua mode menyaring kolom yang sama.',
        );
    }

    public function test_mode_berlaku_memakai_irisan_bukan_pengurungan(): void
    {
        // Permit setahun ditanya per minggu. Kalau yang dipakai "seluruhnya
        // masuk rentang", laporan mingguan akan selalu kosong.
        $setahun = $this->buatPermit(
            disetujui: '2026-01-02',
            mulai: '2026-01-01',
            akhir: '2026-12-31',
        );

        $this->assertContains(
            $setahun,
            $this->nomorHasil(['mode_tanggal' => 'berlaku', 'tanggal_dari' => '2026-06-01', 'tanggal_sampai' => '2026-06-07']),
            'Permit yang sedang berlaku pada rentang itu justru tersaring keluar.',
        );
    }

    public function test_masa_berlaku_di_luar_rentang_tidak_ikut(): void
    {
        $november = $this->buatPermit(
            disetujui: '2026-07-20',
            mulai: '2026-11-01',
            akhir: '2026-11-30',
        );

        // Kendali positif LEBIH DULU: buktikan barisnya memang bisa tampil.
        // Tanpa ini, "tidak ikut" bisa berarti "tak pernah ada".
        $this->assertContains(
            $november,
            $this->nomorHasil(['mode_tanggal' => 'berlaku', 'tanggal_dari' => '2026-11-01', 'tanggal_sampai' => '2026-11-30']),
            'Prasyarat gagal: barisnya tidak muncul bahkan pada rentangnya sendiri.',
        );

        $this->assertNotContains(
            $november,
            $this->nomorHasil(['mode_tanggal' => 'berlaku', 'tanggal_dari' => '2026-03-01', 'tanggal_sampai' => '2026-03-31']),
        );
    }

    public function test_mode_yang_tidak_dikenal_jatuh_ke_masa_berlaku(): void
    {
        // Bawaan harus pasti. Mode yang salah ketik tidak boleh diam-diam
        // berubah makna menjadi tanggal approve.
        $silang = $this->buatPermit(
            disetujui: '2026-03-15',
            mulai: '2026-10-01',
            akhir: '2026-10-31',
        );

        // Kendali positif: pada rentang Maret, mode approve MEMUAT barisnya.
        // Jadi hilangnya ia di mode 'ngawur' benar-benar karena modenya jatuh
        // ke masa berlaku, bukan karena barisnya tak pernah terlihat.
        $this->assertContains(
            $silang,
            $this->nomorHasil(['mode_tanggal' => 'approve', 'tanggal_dari' => '2026-03-01', 'tanggal_sampai' => '2026-03-31']),
            'Prasyarat gagal: barisnya tidak tampil bahkan di mode approve.',
        );

        $this->assertNotContains(
            $silang,
            $this->nomorHasil(['mode_tanggal' => 'ngawur', 'tanggal_dari' => '2026-03-01', 'tanggal_sampai' => '2026-03-31']),
            'Mode tak dikenal tidak jatuh ke masa berlaku.',
        );
    }

    public function test_tanggal_yang_tidak_terbaca_berarti_tidak_menyaring(): void
    {
        // Salah ketik di kotak tanggal harus berarti "tidak menyaring", bukan
        // galat 500 di layar monitoring maupun hasil kosong yang menyesatkan.
        $november = $this->buatPermit(
            disetujui: '2026-07-20',
            mulai: '2026-11-01',
            akhir: '2026-11-30',
        );

        $hasil = $this->nomorHasil(['mode_tanggal' => 'berlaku', 'tanggal_dari' => 'bukan-tanggal', 'tanggal_sampai' => '']);

        $this->assertContains($november, $hasil, 'Tanggal yang tak terbaca justru ikut menyaring.');
    }

    public function test_tanpa_rentang_seluruh_permit_uji_tampil(): void
    {
        // Prasyarat bagi seluruh uji di atas: tanpa filter, ketiganya memang
        // terlihat. Tanpa ini, assertNotContains bisa hijau karena fixture-nya
        // tidak pernah tampil sama sekali.
        $satu = $this->buatPermit('2026-03-15', '2026-10-01', '2026-10-31');
        $dua = $this->buatPermit('2026-07-20', '2026-11-01', '2026-11-30');

        $hasil = $this->nomorHasil([]);

        $this->assertContains($satu, $hasil);
        $this->assertContains($dua, $hasil);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  array<string, string>  $filter
     * @return array<int, string>
     */
    public function test_permit_yang_sudah_kedaluwarsa_tampil_di_monitoring_dan_saringannya(): void
    {
        // Monitoring dulu hanya memuat APPROVED & REVOKED, sehingga filter dan
        // kartu "Sudah kedaluwarsa" selalu kosong (audit 27 Sep 2026, M7).
        $habis = $this->buatPermit('2026-01-05', '2026-01-10', now()->subDays(3)->toDateString());
        $berlaku = $this->buatPermit('2026-01-05', '2026-01-10', now()->addMonths(2)->toDateString());

        $this->assertContains($habis, $this->nomorHasil([]));
        $this->assertSame([$habis], $this->nomorHasil(['status' => 'kadaluarsa']));
        $this->assertNotContains($habis, $this->nomorHasil(['status' => 'aktif']));
        $this->assertContains($berlaku, $this->nomorHasil(['status' => 'aktif']));
    }

    public function test_pengajuan_yang_approvalnya_kedaluwarsa_tidak_masuk_monitoring_izin(): void
    {
        // EXPIRED milik pengajuan yang TAK PERNAH disetujui bukan izin yang pernah hidup.
        $nomor = $this->buatPermit('2026-01-05', '2026-01-10', now()->addMonths(2)->toDateString());
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $nomor)->update(['Flag_Approval' => null]);
        DB::table('HRIS_Approval_Request')->insert([
            'No_Transaksi' => $nomor, 'Kode_Karyawan' => 'A1', 'Request_Order_Flow' => 1,
            'Approver_Karyawan_Kode' => 'A1', 'Flag_Approval' => null,
            'Expired_At' => now()->subDay(), 'created_at' => now()->subDays(3), 'update_at' => now()->subDays(3),
        ]);

        $this->assertNotContains($nomor, $this->nomorHasil([]));
    }

    private function nomorHasil(array $filter): array
    {
        $response = $this->actingAs($this->pengguna())
            ->getJson(self::URL.'?'.http_build_query($filter + ['cari' => $this->tanda, 'limit' => 100]));

        $response->assertOk();

        return array_map(
            static fn ($baris) => (string) $baris['No_Transaksi'],
            $response->json('result') ?? [],
        );
    }

    /**
     * Pengguna uji diambil dari UJUNG daftar, bukan dari awalnya.
     *
     * Beberapa berkas uji lain memilih "pengguna ke-N tanpa role" dengan
     * mengurut menaik. Mengambil dari awal membuat dua berkas berebut orang
     * yang sama, dan kunci halaman yang diberikan di sini ikut terbawa ke
     * harapan berkas sebelah — gejalanya uji yang hijau sendirian lalu merah
     * saat dijalankan serombongan, bentuk kegagalan yang paling membingungkan
     * untuk dilacak.
     */
    private function pengguna(): User
    {
        $user = User::query()
            ->whereNotIn('Id_Users', fn ($q) => $q->select('UserId_Web')->from('N_HRIS_Role_User')->where('Flag_Aktif', 'Y'))
            ->orderByDesc('Id_Users')
            ->skip(self::$urutanPengguna++)
            ->first();

        $this->assertNotNull($user, 'Tidak ada user tanpa role untuk menguji laporan.');

        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001',
            'ID_Level' => 5,
            'ID_Divisi' => 22,
            'Jenis_page' => self::KUNCI,
            'UserID_Web' => $user->Id_Users,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);

        return $user->fresh();
    }

    private function buatPermit(string $disetujui, string $mulai, string $akhir): string
    {
        $nomor = $this->tanda.'-'.Str::upper(Str::random(6));
        $pemohon = (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $pemohon,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => $disetujui.' 10:00:00',
            'Created_At' => $disetujui.' 08:00:00',
            'Created_By' => $pemohon,
            'Idempotency_Key' => 'FT-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'FT',
            'Tanggal_Mulai_Berlaku' => $mulai,
            'Tanggal_Kadaluarsa' => $akhir,
        ]);

        return $nomor;
    }
}
