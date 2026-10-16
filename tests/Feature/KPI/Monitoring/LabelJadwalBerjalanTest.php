<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiJadwalBerjalan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Label "Belum Update" vs "Tidak Update" dihitung PER ORANG.
 *
 * ── Cacat yang dijaga ───────────────────────────────────────────────────────
 *
 * Labelnya dulu satu boolean se-scope: `isScopeExpired()` menuntut SELURUH
 * penjadwalan periode itu sudah lewat, sehingga satu jadwal yang masih terbuka
 * menyeret label semua orang pada periode yang sama.
 *
 * Terukur di produksi 16 Sep 2026, periode Juli 2026: dari 90 baris
 * "belum/tidak update", hanya 3 orang yang benar-benar peserta jadwal #32
 * (perorangan, berakhir 15 Sep 21:00). 87 sisanya cuma dibebani jadwal #26 yang
 * sudah tutup 31 Agustus — tetapi sepanjang 1–15 September mereka tetap
 * berlabel "Belum Update". Tiga orang menyeret label delapan puluh tujuh.
 *
 * Lapis kedua: menonaktifkan penjadwalan tidak berefek apa pun. Ke-37 peserta
 * jadwal #32 sudah `Flag_Aktif='T'`, jadwalnya sendiri juga, dan tak satu pun
 * dari tiga lapis (jadwal, peserta, label) membacanya. Admin praktis tidak
 * punya tuas untuk menutup sebuah periode selain menunggu jam dinding lewat.
 *
 * ── Yang ditegaskan ─────────────────────────────────────────────────────────
 *
 * Fixture-nya dibuat supaya satu-satunya pembeda adalah SIAPA yang dibebani
 * jadwal yang masih berjalan. Kalau labelnya kembali se-scope, ketiga karyawan
 * di bawah akan berlabel sama — dan uji ini merah.
 *
 * Universe SENGAJA tidak diikutkan dalam perubahan: penjadwalan nonaktif tetap
 * menampilkan orangnya (riwayat tidak boleh lenyap, lihat catatan di
 * KpiEnforcementController::fetchYears). Yang berubah hanya labelnya.
 */
class LabelJadwalBerjalanTest extends TestCase
{
    use DatabaseTransactions;

    /** Tahun jauh ke depan supaya universe uji hanya berisi karyawan di sini. */
    private const TAHUN = 2035;

    private const PERIODE = '2035-07';

    /** @var array<string,string> label -> Kode_Karyawan */
    private array $karyawan = [];

    private int $jadwalTutup;

    private int $jadwalBerjalan;

    private int $jadwalForm;

    protected function setUp(): void
    {
        parent::setUp();

        $perusahaan = DB::table('Karyawan')->value('Kode_Perusahaan');
        $idTarget = DB::table('KPI_Master_Target')->min('Id_Master_Target');
        $userIdDasar = (int) DB::table('Karyawan')->max('UserID_Web') + 2000;

        if (! $perusahaan || ! $idTarget) {
            $this->markTestSkipped('Butuh minimal satu Karyawan dan satu KPI_Master_Target sebagai acuan.');
        }

        // Dua penjadwalan penilaian pada periode yang SAMA: satu sudah tutup,
        // satu masih berjalan. Keduanya perorangan supaya universe uji kecil dan
        // keanggotaannya bisa dikendalikan per orang.
        $this->jadwalTutup = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji label — jadwal sudah tutup',
            'Tahun' => self::TAHUN,
            'Periode' => self::PERIODE,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-08-01 00:00:00',
            'Jadwal_Akhir' => now()->subDays(3),
        ], 'Id_Master_Penjadwalan');

        $this->jadwalBerjalan = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji label — jadwal masih berjalan',
            'Tahun' => self::TAHUN,
            'Periode' => self::PERIODE,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3),
            'Jadwal_Akhir' => now()->addDays(10),
        ], 'Id_Master_Penjadwalan');

        $this->jadwalForm = (int) DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji label — form',
            'Tahun' => self::TAHUN,
            'Periode' => self::TAHUN.'-01',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => self::TAHUN.'-01-01 00:00:00',
            'Jadwal_Akhir' => self::TAHUN.'-01-31 23:59:59',
        ], 'Id_Master_Penjadwalan');

        // hanya_jadwal_tutup  -> tenggatnya benar-benar habis
        // peserta_berjalan    -> masih punya waktu
        // peserta_dicabut     -> terdaftar di jadwal berjalan tapi sudah dinonaktifkan
        $peserta = [
            'hanya_jadwal_tutup' => [$this->jadwalTutup, 'Y'],
            'peserta_berjalan' => [$this->jadwalBerjalan, 'Y'],
            'peserta_dicabut' => [$this->jadwalBerjalan, 'T'],
        ];

        $i = 0;
        foreach ($peserta as $label => [$idJadwal, $flagPeserta]) {
            $kode = 'ZL'.Str::upper(Str::random(6));
            $idUser = $userIdDasar + (++$i);
            $this->karyawan[$label] = $kode;

            DB::table('Karyawan')->insert([
                'Kode_Karyawan' => $kode,
                'Kode_Perusahaan' => $perusahaan,
                'Nama' => 'Uji label '.$label,
                'UserID_Web' => $idUser,
                'Aktif' => 'Y',
            ]);

            DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
                'Id_Master_Penjadwalan' => $idJadwal,
                'Kode_Karyawan' => $kode,
                'Keterangan' => 'fixture uji label jadwal berjalan',
                'Flag_Aktif' => $flagPeserta,
            ]);

            $idPengajuan = (int) DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => 'UJI-LJB-'.$idUser,
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
                'Nama_Parameter' => 'Parameter uji label',
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

    /** @return array<string,string> kode_karyawan -> status */
    private function statusPerKaryawan(): array
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        app()->instance('request', Request::create('/x', 'GET'));
        Auth::setUser($admin);

        $peta = [];

        for ($halaman = 1; $halaman <= 10; $halaman++) {
            $req = Request::create('/x', 'GET', [
                'periode' => self::PERIODE,
                'mode' => 'bulan',
                'per_page' => 100,
                'page' => $halaman,
            ]);
            $req->setUserResolver(fn () => $admin);
            app()->instance('request', $req);

            $res = app(KpiEnforcementController::class)->getPerformanceTracking($req);

            $this->assertSame(200, $res->getStatusCode(), 'Monitoring gagal: '.$res->getContent());

            $isi = json_decode($res->getContent(), true);
            $baris = $isi['result']['rows'] ?? $isi['result'] ?? [];

            foreach ($baris as $b) {
                $peta[$b['kode_karyawan']] = $b['status'];
            }

            if (count($peta) >= (int) ($isi['total_data'] ?? 0) || $baris === []) {
                break;
            }
        }

        return $peta;
    }

    public function test_jadwal_orang_lain_tidak_menyeret_label(): void
    {
        $status = $this->statusPerKaryawan();
        $kode = $this->karyawan['hanya_jadwal_tutup'];

        $this->assertArrayHasKey($kode, $status, 'Karyawan uji tidak muncul di monitoring sama sekali.');
        $this->assertSame(
            'tidak_submit',
            $status[$kode],
            'Tenggat orang ini sudah habis, tetapi ia masih berlabel "Belum Update" karena '.
            'ADA jadwal lain pada periode yang sama yang belum tutup — padahal jadwal itu '.
            'tidak membebaninya. Labelnya kembali dihitung se-scope, bukan per orang.',
        );
    }

    public function test_yang_masih_punya_waktu_tetap_belum_update(): void
    {
        $status = $this->statusPerKaryawan();
        $kode = $this->karyawan['peserta_berjalan'];

        $this->assertSame(
            'belum_submit',
            $status[$kode] ?? null,
            'Orang ini masih terdaftar aktif di penjadwalan yang belum tutup, jadi ia belum '.
            'boleh dinyatakan "Tidak Update" — itu menuduh tenggat yang sebenarnya masih ada.',
        );
    }

    public function test_peserta_yang_dicabut_tidak_lagi_dibebani(): void
    {
        $status = $this->statusPerKaryawan();
        $kode = $this->karyawan['peserta_dicabut'];

        $this->assertArrayHasKey(
            $kode,
            $status,
            'Peserta yang dicabut ikut hilang dari layar. Universe sengaja TIDAK menyaring '.
            'Flag_Aktif supaya riwayat periode tidak lenyap; yang boleh berubah hanya labelnya.',
        );
        $this->assertSame(
            'tidak_submit',
            $status[$kode],
            'Pencabutan peserta (N_KPI_Master_Penjadwalan_Perorangan.Flag_Aktif=T) tidak '.
            'terbaca: orangnya masih dianggap punya tenggat berjalan.',
        );
    }

    public function test_menonaktifkan_jadwal_menutup_tenggatnya(): void
    {
        $kode = $this->karyawan['peserta_berjalan'];

        $this->assertSame('belum_submit', $this->statusPerKaryawan()[$kode] ?? null, 'Prasyarat fixture gagal.');

        DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $this->jadwalBerjalan)
            ->update(['Flag_Aktif' => 'T']);

        $this->assertSame(
            'tidak_submit',
            $this->statusPerKaryawan()[$kode] ?? null,
            'Penjadwalan sudah dinonaktifkan tetapi labelnya tidak berubah. Tanpa ini admin '.
            'tidak punya satu pun tuas untuk menutup sebuah periode selain menunggu '.
            'Jadwal_Akhir lewat dengan sendirinya.',
        );
    }

    public function test_penanda_untuk_export_bolak_balik_utuh(): void
    {
        // Berkas export dibangun belakangan oleh job dari payload, bukan dari
        // objek yang hidup di request. Kalau penanda ini tidak utuh bolak-balik,
        // berkasnya menyebut status yang berbeda dari layar yang barusan dilihat.
        $schedules = collect([
            (object) [
                'Id_Master_Penjadwalan' => $this->jadwalBerjalan,
                'Flag_Perorangan' => 'Y',
                'Flag_Aktif' => 'Y',
                'Jadwal_Akhir' => now()->addDays(10),
            ],
            (object) [
                'Id_Master_Penjadwalan' => $this->jadwalTutup,
                'Flag_Perorangan' => 'Y',
                'Flag_Aktif' => 'Y',
                'Jadwal_Akhir' => now()->subDays(3),
            ],
        ]);

        $asli = KpiJadwalBerjalan::dariJadwal($schedules);
        $lewatMeta = KpiJadwalBerjalan::dariMeta($asli->untukMeta());

        $this->assertNotNull($lewatMeta, 'Penanda hilang saat dititipkan ke payload job.');

        foreach (['peserta_berjalan', 'peserta_dicabut', 'hanya_jadwal_tutup'] as $label) {
            $this->assertSame(
                $asli->membebani($this->karyawan[$label]),
                $lewatMeta->membebani($this->karyawan[$label]),
                "Berkas export dan layar akan berbeda untuk '{$label}'.",
            );
        }
    }

    public function test_meta_tanpa_penanda_baru_memulangkan_null(): void
    {
        // Job yang sudah terlanjur antre sebelum rilis ini tidak punya penanda
        // baru. Ia harus jatuh ke cadangan lamanya, bukan diam-diam menganggap
        // tidak ada jadwal berjalan.
        $this->assertNull(
            KpiJadwalBerjalan::dariMeta(['schedule_ids' => [1], 'expired' => true]),
            'Payload job lama tidak terdeteksi sebagai payload lama, sehingga cadangan '.
            "'expired' tidak pernah dipakai dan seluruh barisnya salah label.",
        );
    }
}
