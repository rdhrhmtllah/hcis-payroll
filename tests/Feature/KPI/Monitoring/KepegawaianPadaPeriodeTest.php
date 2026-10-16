<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Console\Commands\KPI\Operations\KpiNotifUpdatePerformance;
use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Siapa yang wajib dinilai KPI pada sebuah periode.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * Pertanyaan itu dulu dijawab di tiga tempat dengan tiga hasil berbeda:
 * universe monitoring memakai `k.Aktif='Y'` (status HARI INI), kandidat Auto Cap
 * tidak menyaring kepegawaian sama sekali, dan sasaran notifikasi juga tidak.
 *
 * Diukur di produksi untuk Juli 2026 (16 Sep 2026): Auto Cap memulangkan 121
 * orang, monitoring 90, dan SELURUH selisihnya karyawan non-aktif — 16 sudah
 * resign sebelum Juli, 9 resign di tengah Juli, 10 resign baru Agustus.
 * Dua kerusakan yang berlawanan arah dari akar yang sama:
 *
 *   - Auto Cap terlalu longgar. Query yang sama dipakai previewEnforcement dan
 *     penjaga validId di enforceCap, jadi satu klik menulis realisasi KPI atas
 *     nama 16 orang yang sudah keluar.
 *   - Monitoring terlalu ketat, dan ketatnya BERGERAK. Sepuluh orang yang
 *     bekerja penuh sepanjang Juli hilang dari laporan Juli hanya karena mereka
 *     resign di Agustus — artinya angka Juli menyusut sendiri berbulan-bulan
 *     setelah Juli ditutup, tanpa satu pun jejak.
 *
 * ── Yang ditegaskan di sini ─────────────────────────────────────────────────
 *
 * Bukan "ada penyaringnya", melainkan efeknya pada tiga permukaan sekaligus,
 * dengan lima karyawan yang hanya dibedakan oleh tanggal resign. Setiap
 * assertion di bawah HARUS bisa merah kalau salah satu permukaan dilewati:
 * itulah cara ketiganya berhenti boleh berselisih.
 *
 * Keputusan pemilik proses (16 Sep 2026): seseorang ditampilkan untuk sebuah
 * periode hanya bila ia menjalani periode itu PENUH. Yang resign 15 Juli tidak
 * perlu lagi muncul di Juli; yang resign tepat 31 Juli tetap muncul, karena
 * bulannya ia jalani sampai habis. Batas "penuh atau tidak" itulah yang diuji
 * di sini lewat dua tanggal yang hanya berbeda enam belas hari.
 */
class KepegawaianPadaPeriodeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tahun jauh ke depan supaya universe uji hanya berisi karyawan yang
     * ditanam di sini. Data dev punya penjadwalan di hampir setiap bulan tahun
     * berjalan; menumpang di sana berarti assertion "tidak muncul" bisa hijau
     * karena tertimbun ribuan baris lain, bukan karena penyaringnya bekerja.
     */
    private const TAHUN = 2034;

    private const PERIODE = '2034-07';

    /** @var array<string,string> label -> Kode_Karyawan */
    private array $karyawan = [];

    private int $jadwalPenilaian;

    private int $jadwalForm;

    protected function setUp(): void
    {
        parent::setUp();

        $perusahaan = DB::table('Karyawan')->value('Kode_Perusahaan');
        $idTarget = DB::table('KPI_Master_Target')->min('Id_Master_Target');
        $userIdDasar = (int) DB::table('Karyawan')->max('UserID_Web') + 1000;

        if (! $perusahaan || ! $idTarget) {
            $this->markTestSkipped('Butuh minimal satu Karyawan dan satu KPI_Master_Target sebagai acuan.');
        }

        // Lima orang yang HANYA dibedakan tanggal resign-nya.
        $skenario = [
            'aktif' => null,
            'resign_sebelum' => self::TAHUN.'-06-30',
            'resign_tengah' => self::TAHUN.'-07-15',
            'resign_hari_terakhir' => self::TAHUN.'-07-31',
            'resign_sesudah' => self::TAHUN.'-08-25',
        ];

        $this->jadwalPenilaian = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji kepegawaian pada periode (penilaian)',
            'Tahun' => self::TAHUN,
            'Periode' => self::PERIODE,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-08-01 00:00:00',
            'Jadwal_Akhir' => self::TAHUN.'-08-31 23:59:59',
        ], 'Id_Master_Penjadwalan');

        $this->jadwalForm = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji kepegawaian pada periode (form)',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-01-01 00:00:00',
            'Jadwal_Akhir' => self::TAHUN.'-01-31 23:59:59',
        ], 'Id_Master_Penjadwalan');

        $i = 0;
        foreach ($skenario as $label => $tanggalResign) {
            $kode = 'ZK'.Str::upper(Str::random(6));
            $idUser = $userIdDasar + (++$i);
            $this->karyawan[$label] = $kode;

            DB::table('Karyawan')->insert([
                'Kode_Karyawan' => $kode,
                'Kode_Perusahaan' => $perusahaan,
                'Nama' => 'Uji '.$label,
                'UserID_Web' => $idUser,
                'HP' => '628000000'.$i,
                'Aktif' => $tanggalResign === null ? 'Y' : 'T',
                'Tanggal_Resign' => $tanggalResign,
            ]);

            DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
                'Id_Master_Penjadwalan' => $this->jadwalPenilaian,
                'Kode_Karyawan' => $kode,
                'Keterangan' => 'fixture uji kepegawaian pada periode',
                'Flag_Aktif' => 'Y',
            ]);

            // Parameter KPI yang sudah disetujui, dengan target PERSIS di bulan uji.
            // Tanpa target exact-month, orangnya berstatus "Tidak Ada Target" dan
            // memang bukan kandidat enforcement — bukan itu yang sedang diuji.
            $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => 'UJI-KPP-'.$idUser,
                'Id_Penjadwalan' => $this->jadwalForm,
                'Id_User' => $idUser,
                'Tanggal' => self::TAHUN.'-01-10 00:00:00',
                'Jam' => '09:00:00',
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => 'Y',
                'Status' => null,
            ], 'Id_Pengajuan');

            $idDetail = (int) DB::table('KPI_Pengajuan_Detail')->insertGetId([
                'Id_Pengajuan' => $idPengajuan,
                'Nama_Parameter' => 'Parameter uji kepegawaian',
                'Id_Master_Target' => $idTarget,
                'Bobot' => 100,
                'Kode_Karyawan' => $kode,
            ], 'Id_Pengajuan_Detail');

            DB::table('KPI_Pengajuan_Det')->insert([
                'Id_Pengajuan_Detail' => $idDetail,
                'Nama_Target' => 'Target '.self::PERIODE,
                'Nilai_Target' => 100,
                'Periode' => self::PERIODE,
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function barisMonitoring(): array
    {
        return $this->kumpulkan('getPerformanceTracking', [
            'periode' => self::PERIODE,
            'mode' => 'bulan',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function barisAutoCap(): array
    {
        return $this->kumpulkan('getUnfilledEmployees', ['periode' => self::PERIODE]);
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<int,array<string,mixed>>
     */
    private function kumpulkan(string $metode, array $params): array
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        // Guard 'web' menuntut request terikat sebelum setUser.
        app()->instance('request', Request::create('/x', 'GET'));
        Auth::setUser($admin);

        $semua = [];

        for ($halaman = 1; $halaman <= 20; $halaman++) {
            $req = Request::create('/x', 'GET', $params + ['per_page' => 100, 'page' => $halaman]);
            $req->setUserResolver(fn () => $admin);
            app()->instance('request', $req);

            $res = app(KpiEnforcementController::class)->{$metode}($req);

            $this->assertSame(
                200,
                $res->getStatusCode(),
                "{$metode} gagal untuk periode uji: ".$res->getContent(),
            );

            $isi = json_decode($res->getContent(), true);
            $baris = $isi['result']['rows'] ?? $isi['result'] ?? [];
            $semua = array_merge($semua, $baris);

            if (count($semua) >= (int) ($isi['total_data'] ?? 0) || $baris === []) {
                break;
            }
        }

        return $semua;
    }

    /**
     * @param  array<int,array<string,mixed>>  $baris
     * @return array<string,array<string,mixed>>
     */
    private function perKode(array $baris): array
    {
        $peta = [];
        foreach ($baris as $b) {
            $peta[$b['kode_karyawan']] = $b;
        }

        return $peta;
    }

    public function test_resign_sebelum_periode_tidak_bisa_di_auto_cap(): void
    {
        $peta = $this->perKode($this->barisAutoCap());

        $this->assertArrayNotHasKey(
            $this->karyawan['resign_sebelum'],
            $peta,
            'Karyawan yang resign SEBELUM periode masih jadi kandidat Auto Cap. Query ini '.
            'juga dipakai previewEnforcement dan penjaga validId di enforceCap, jadi satu '.
            'klik akan menulis realisasi KPI atas nama orang yang sudah keluar.',
        );
    }

    public function test_masih_bekerja_pada_periode_tetap_jadi_kandidat_auto_cap(): void
    {
        $peta = $this->perKode($this->barisAutoCap());

        foreach (['aktif', 'resign_hari_terakhir', 'resign_sesudah'] as $label) {
            $this->assertArrayHasKey(
                $this->karyawan[$label],
                $peta,
                "Karyawan '{$label}' menjalani periode uji sampai habis tetapi hilang dari ".
                'Auto Cap. Penyaring kepegawaian terlalu ketat: KPI bulan itu jadi tak bisa '.
                'dilengkapi admin sama sekali.',
            );
        }

        foreach (['resign_sebelum', 'resign_tengah'] as $label) {
            $this->assertArrayNotHasKey(
                $this->karyawan[$label],
                $peta,
                "Karyawan '{$label}' tidak menjalani periode uji sampai habis tetapi masih ".
                'jadi kandidat Auto Cap.',
            );
        }
    }

    public function test_resign_sesudah_periode_tetap_terlihat_di_monitoring(): void
    {
        $peta = $this->perKode($this->barisMonitoring());
        $kode = $this->karyawan['resign_sesudah'];

        $this->assertArrayHasKey(
            $kode,
            $peta,
            'Karyawan yang bekerja PENUH sepanjang periode lalu resign sesudahnya hilang dari '.
            'laporan periode itu. Artinya angka sebuah bulan menyusut sendiri berbulan-bulan '.
            'setelah bulan itu ditutup — laporan historis yang berubah surut tanpa jejak.',
        );

        $this->assertContains(
            $peta[$kode]['status'],
            ['belum_submit', 'tidak_submit'],
            'Orangnya muncul tetapi statusnya bukan belum/tidak update, padahal ia tidak punya '.
            'realisasi untuk periode uji.',
        );
    }

    public function test_resign_tengah_periode_tidak_muncul_di_monitoring(): void
    {
        $peta = $this->perKode($this->barisMonitoring());

        $this->assertArrayNotHasKey(
            $this->karyawan['resign_tengah'],
            $peta,
            'Karyawan yang resign di tengah periode (15 Juli pada fixture) masih dihitung '.
            'sebagai belum update untuk bulan itu, padahal separuh bulannya tidak ia jalani. '.
            'Batasnya kemungkinan mundur ke AWAL periode, bukan hari terakhirnya.',
        );

        $this->assertArrayHasKey(
            $this->karyawan['resign_hari_terakhir'],
            $peta,
            'Karyawan yang resign tepat di hari terakhir periode ikut terbuang. Ia bekerja '.
            'penuh sepanjang bulan itu, jadi angkanya tetap dibutuhkan — batasnya kelewat '.
            'satu hari.',
        );
    }

    public function test_resign_sebelum_periode_tidak_muncul_di_monitoring(): void
    {
        $peta = $this->perKode($this->barisMonitoring());

        $this->assertArrayNotHasKey(
            $this->karyawan['resign_sebelum'],
            $peta,
            'Karyawan yang sudah resign sebelum periode dimulai ikut dihitung sebagai '.
            '"belum update" — memperbesar angka yang dipakai menilai kepatuhan divisi.',
        );
    }

    public function test_kedua_layar_menyebut_orang_yang_sama(): void
    {
        $monitoring = collect($this->barisMonitoring())
            ->whereIn('status', ['belum_submit', 'tidak_submit', 'draft'])
            ->pluck('kode_karyawan')
            ->intersect(array_values($this->karyawan))
            ->sort()
            ->values()
            ->all();

        $autoCap = collect($this->barisAutoCap())
            ->pluck('kode_karyawan')
            ->intersect(array_values($this->karyawan))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $autoCap,
            $monitoring,
            'Monitoring dan Auto Cap menyebut himpunan orang yang berbeda untuk periode yang '.
            'sama. Selisih seperti inilah yang membuat admin tidak bisa mempercayai angka '.
            'mana pun di halaman ini.',
        );
    }

    public function test_pengingat_tidak_dikirim_ke_mantan_karyawan(): void
    {
        $sasaran = KpiNotifUpdatePerformance::sasaranPengingat(self::PERIODE)
            ->pluck('Kode_Karyawan')
            ->all();

        $this->assertContains(
            $this->karyawan['aktif'],
            $sasaran,
            'Prasyarat fixture gagal: karyawan aktif yang belum mengisi seharusnya jadi sasaran.',
        );

        foreach (['resign_sebelum', 'resign_tengah', 'resign_hari_terakhir', 'resign_sesudah'] as $label) {
            $this->assertNotContains(
                $this->karyawan[$label],
                $sasaran,
                "Pengingat masih menyasar '{$label}'. Sebuah pengingat adalah tindakan kepada ".
                'orang SEKARANG: mengirim WhatsApp ke mantan karyawan tidak menjadi benar hanya '.
                'karena periodenya benar. Terukur di produksi: 35 nomor milik karyawan resign '.
                'ikut jadi sasaran pengingat Juli 2026.',
            );
        }
    }
}
