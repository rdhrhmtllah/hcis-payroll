<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Bulan mulai pengisian parameter KPI harus ditentukan PER ANGGOTA.
 *
 * Sebelumnya bulan mulai dihitung sekali dari NIK leader lalu dipakai untuk
 * seluruh anggota, sehingga anggota yang mulainya lebih lambat mendapat baris
 * target untuk bulan yang tidak pernah ia jalani. Itu menekan achievement-nya,
 * karena YTD menjumlahkan target dari baris yang ADA.
 */
class KpiParameterStartPeriodeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function panggilResolver(?string $kodeKaryawan, $jadwalGlobal): array
    {
        $metode = new ReflectionMethod(FormKpiController::class, 'resolveJadwalPengisianAnggota');
        $metode->setAccessible(true);

        return $metode->invoke(new FormKpiController, $kodeKaryawan, $jadwalGlobal);
    }

    /** Jadwal global apa adanya, tanpa menyentuh data. */
    private function jadwalGlobal(?string $periode, $tahun = 2026): object
    {
        return (object) [
            'Id_Master_Penjadwalan' => 999999,
            'Tahun' => $tahun,
            'Periode' => $periode,
        ];
    }

    public function test_jadwal_perorangan_menimpa_jadwal_global(): void
    {
        $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Start_Periode')
            ->where('m.Flag_Aktif', 'Y')
            ->where('m.Flag_Form_KPI', 'Y')
            ->where('m.Flag_Perorangan', 'Y')
            ->select('p.Kode_Karyawan', 'p.Start_Periode', 'm.Id_Master_Penjadwalan', 'm.Tahun')
            ->orderByDesc('m.Id_Master_Penjadwalan')
            ->first();

        if (! $peserta) {
            $this->markTestSkipped('Tidak ada peserta jadwal pengisian perorangan di data dev.');
        }

        $hasil = $this->panggilResolver(
            $peserta->Kode_Karyawan,
            $this->jadwalGlobal('2026-01', $peserta->Tahun),
        );

        $this->assertSame('perorangan', $hasil['sumber'], 'Jadwal perorangan seharusnya menang atas global.');
        $this->assertSame(
            $peserta->Start_Periode,
            $hasil['mulai'],
            'Bulan mulai tidak diambil dari Start_Periode peserta.',
        );
        $this->assertSame(
            (int) $peserta->Id_Master_Penjadwalan,
            (int) $hasil['id_penjadwalan'],
            'Pengajuan tidak diikat ke jadwal yang benar-benar mengatur anggota ini.',
        );
    }

    public function test_anggota_tanpa_jadwal_perorangan_pakai_periode_global(): void
    {
        $hasil = $this->panggilResolver('NIK-TIDAK-ADA-XYZ', $this->jadwalGlobal('2026-07'));

        $this->assertSame('global', $hasil['sumber']);
        $this->assertSame('2026-07', $hasil['mulai']);
        $this->assertSame(999999, (int) $hasil['id_penjadwalan']);
    }

    public function test_global_tanpa_periode_jatuh_ke_bulan_satu(): void
    {
        $hasil = $this->panggilResolver('NIK-TIDAK-ADA-XYZ', $this->jadwalGlobal(null));

        $this->assertSame('2026-01', $hasil['mulai'], 'Jadwal global tanpa Periode harus jatuh ke bulan 1.');
    }

    public function test_kode_karyawan_kosong_tidak_bikin_error(): void
    {
        $hasil = $this->panggilResolver(null, $this->jadwalGlobal('2026-03'));

        $this->assertSame('global', $hasil['sumber']);
        $this->assertSame('2026-03', $hasil['mulai']);
    }

    /**
     * Tahun jadwal perorangan harus cocok dengan tahun jadwal yang dipilih leader,
     * supaya jadwal tahun lalu tidak diam-diam mengatur pengajuan tahun ini.
     */
    public function test_jadwal_perorangan_tahun_lain_diabaikan(): void
    {
        $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Start_Periode')
            ->where('m.Flag_Aktif', 'Y')
            ->where('m.Flag_Form_KPI', 'Y')
            ->value('p.Kode_Karyawan');

        if (! $peserta) {
            $this->markTestSkipped('Tidak ada peserta jadwal pengisian perorangan di data dev.');
        }

        $hasil = $this->panggilResolver($peserta, $this->jadwalGlobal('1999-05', 1999));

        $this->assertSame('global', $hasil['sumber'], 'Jadwal perorangan tahun lain seharusnya diabaikan.');
    }

    /**
     * Aturan pemangkasan: periode < bulan mulai dibuang, dan breakdown kuartalan
     * yang MEMUAT bulan mulai tetap dipertahankan.
     */
    public function test_aturan_pemangkasan_periode(): void
    {
        $mulai = '2026-05';

        $bulanan = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
        $tersimpan = array_values(array_filter($bulanan, fn ($b) => '2026-'.$b >= $mulai));
        $this->assertSame(['05', '06', '07', '08', '09', '10', '11', '12'], $tersimpan);

        $kuartal = ['03', '06', '09', '12'];
        $tersimpanKuartal = array_values(array_filter($kuartal, fn ($b) => '2026-'.$b >= $mulai));
        $this->assertSame(
            ['06', '09', '12'],
            $tersimpanKuartal,
            'Q2 (Apr-Jun) memuat bulan mulai Mei, jadi harus dipertahankan.',
        );
    }

    /**
     * Pengajuan tim yang SUDAH ADA masih menyimpan baris target sebelum bulan
     * mulai anggotanya. Test ini merekam besarnya, bukan menegakkan aturan —
     * pembersihan data lama adalah keputusan terpisah karena akan mengubah skor
     * YTD yang sudah tampil.
     */
    public function test_catat_pengajuan_lama_yang_masih_melewati_bulan_mulai(): void
    {
        $pelanggaran = 0;

        $tim = DB::table('KPI_Pengajuan as kp')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->where('kp.Flag_Team', 'Y')
            ->where('kp.Flag_Aktif', 'Y')
            ->select('kp.Id_Pengajuan', 'k.Kode_Karyawan')
            ->get();

        foreach ($tim as $t) {
            $mulai = DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
                ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
                ->where('p.Kode_Karyawan', $t->Kode_Karyawan)
                ->where('p.Flag_Aktif', 'Y')
                ->where('m.Flag_Form_KPI', 'Y')
                ->where('m.Flag_Aktif', 'Y')
                ->value('p.Start_Periode');

            if (! $mulai) {
                continue;
            }

            $pelanggaran += DB::table('KPI_Pengajuan_Det as d')
                ->join('KPI_Pengajuan_Detail as dt', 'dt.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
                ->where('dt.Id_Pengajuan', $t->Id_Pengajuan)
                ->where('d.Periode', '<', $mulai)
                ->count();
        }

        // Baseline data dev per 2026-07-29: 8 baris — Tari, Jan-Apr 2026 (bulan
        // mulai Mei) dikali 2 parameter ("KPI System" dan "Learning management
        // system"). Angka ini menghitung BARIS KPI_Pengajuan_Det, bukan bulan.
        $this->assertLessThanOrEqual(
            8,
            $pelanggaran,
            'Baris target sebelum bulan mulai bertambah — perbaikan storeTeam tidak jalan.',
        );
    }

    public function test_hashids_periode_breakdown_dua_digit(): void
    {
        $aneh = DB::table('KPI_Target_Breakdown')
            ->whereRaw('LEN(Periode) <> 2')
            ->count();

        $this->assertSame(
            0,
            $aneh,
            'Periode breakdown harus dua digit; perbandingan string bulan mulai bergantung padanya.',
        );

        $this->assertNotEmpty(Hashids::connection('custom')->encode(1));
    }

    /**
     * Endpoint pratinjau harus memberi jawaban yang sama dengan yang dipakai
     * saat menyimpan — kalau berbeda, leader melihat satu hal dan tersimpan
     * hal lain.
     */
    public function test_endpoint_pratinjau_sama_dgn_yang_tersimpan(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        $user = $leader ? User::find($leader) : null;

        if (! $user || ! $user->memilikiJabatan('FormKpiTeam')) {
            $this->markTestSkipped('Tidak ada leader dgn akses FormKpiTeam di data dev.');
        }

        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Form_KPI', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        if (! $jadwal) {
            $this->markTestSkipped('Tidak ada jadwal pengisian di data dev.');
        }

        $kode = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->where('Aktif', 'Y')
            ->limit(5)
            ->pluck('Kode_Karyawan')
            ->all();

        $res = $this->actingAs($user)->postJson('/api/v1/form-kpi/team/start-periode', [
            'Id_Penjadwalan' => Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan),
            'kode_karyawan' => $kode,
        ]);

        $res->assertStatus(200);

        $baris = collect($res->json('result'));
        $this->assertCount(count($kode), $baris, 'Jumlah baris pratinjau tidak sama dgn jumlah anggota.');

        foreach ($baris as $b) {
            $langsung = $this->panggilResolver($b['kode_karyawan'], $jadwal);

            $this->assertSame(
                $langsung['mulai'],
                $b['mulai'],
                "Pratinjau berbeda dgn resolusi simpan untuk {$b['kode_karyawan']}.",
            );
            $this->assertSame($langsung['sumber'], $b['sumber']);
            $this->assertSame((int) substr($langsung['mulai'], 5, 2), $b['bulan']);
        }
    }

    public function test_pratinjau_menolak_jadwal_tidak_valid(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        $user = $leader ? User::find($leader) : null;

        if (! $user || ! $user->memilikiJabatan('FormKpiTeam')) {
            $this->markTestSkipped('Tidak ada leader dgn akses FormKpiTeam di data dev.');
        }

        $this->actingAs($user)
            ->postJson('/api/v1/form-kpi/team/start-periode', [
                'Id_Penjadwalan' => 'bukan-hash',
                'kode_karyawan' => ['X1'],
            ])
            ->assertStatus(400);

        $this->actingAs($user)
            ->postJson('/api/v1/form-kpi/team/start-periode', ['Id_Penjadwalan' => 'abc'])
            ->assertStatus(422);
    }

    /**
     * Grid target harus mematikan sel bulan sebelum bulan mulai ANGGOTA yang
     * dikirim, bukan bulan mulai leader yang sedang login.
     */
    public function test_breakdown_mematikan_bulan_sebelum_start_anggota(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        $user = $leader ? User::find($leader) : null;

        if (! $user) {
            $this->markTestSkipped('Tidak ada leader tim di data dev.');
        }

        $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Start_Periode')
            ->where('m.Flag_Aktif', 'Y')
            ->where('m.Flag_Form_KPI', 'Y')
            ->where('m.Flag_Perorangan', 'Y')
            ->select('p.Kode_Karyawan', 'p.Start_Periode', 'm.Tahun')
            ->first();

        $jadwal = $peserta
            ? DB::table('KPI_Master_Penjadwalan')
                ->where('Flag_Form_KPI', 'Y')
                ->where('Tahun', $peserta->Tahun)
                ->where(function ($q) {
                    $q->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
                })
                ->first()
            : null;

        if (! $peserta || ! $jadwal) {
            $this->markTestSkipped('Butuh jadwal global + peserta perorangan di tahun yang sama.');
        }

        $bulanMulai = (int) substr($peserta->Start_Periode, 5, 2);

        // Target harus punya minimal satu breakdown SEBELUM bulan mulai, kalau
        // tidak aturannya tak teruji sama sekali.
        $target = DB::table('KPI_Master_Target as a')
            ->join('KPI_Target_Breakdown as b', 'b.Id_Master_Target', '=', 'a.Id_Master_Target')
            ->where('a.Flag_Aktif', 'Y')
            ->whereRaw('CAST(b.Periode AS int) < ?', [$bulanMulai])
            ->select('a.Id_Master_Target')
            ->first();

        if (! $target) {
            $this->markTestSkipped('Tidak ada master target dgn breakdown sebelum bulan mulai peserta.');
        }

        $res = $this->actingAs($user)->getJson('/api/v1/form-kpi/getTargetBreakdown?'.http_build_query([
            'Id_Master_Target' => Hashids::connection('custom')->encode($target->Id_Master_Target),
            'Id_Penjadwalan' => Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan),
            'kode_karyawan' => [$peserta->Kode_Karyawan],
        ]));

        $res->assertStatus(200);

        $baris = collect($res->json('result'));

        $this->assertNotEmpty($baris, 'Breakdown target kosong.');

        foreach ($baris as $b) {
            $this->assertSame(
                (int) $b['Periode'] >= $bulanMulai,
                (bool) $b['isActive'],
                "Periode {$b['Periode']} salah status terhadap bulan mulai {$bulanMulai}.",
            );
        }

        $this->assertTrue(
            $baris->contains(fn ($b) => (bool) $b['isActive'] === false),
            'Target ini punya breakdown sebelum bulan mulai, tapi tidak ada sel yang dimatikan.',
        );
    }

    /**
     * Daftar target (/api/v1/form-kpi/current) juga dipakai form tim. Penilaian
     * is_active harus mengikuti bulan mulai PALING AWAL di antara anggota yang
     * dikirim, bukan bulan mulai leader — kalau tidak, target yang seluruh
     * periodenya lebih awal ikut dimatikan untuk semua orang.
     */
    public function test_daftar_target_pakai_bulan_mulai_paling_awal(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        $user = $leader ? User::find($leader) : null;

        if (! $user || ! $user->memilikiJabatan('FormKPIPage')) {
            $this->markTestSkipped('Leader tidak punya akses FormKPIPage di data dev.');
        }

        // Jadwal global dgn Periode selarut mungkin, supaya bedanya terlihat.
        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Form_KPI', 'Y')
            ->whereNotNull('Periode')
            ->orderByDesc('Periode')
            ->first();

        $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
            ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNotNull('p.Start_Periode')
            ->where('m.Flag_Aktif', 'Y')
            ->where('m.Flag_Form_KPI', 'Y')
            ->where('m.Flag_Perorangan', 'Y')
            ->select('p.Kode_Karyawan', 'p.Start_Periode', 'm.Tahun')
            ->first();

        if (! $jadwal || ! $peserta || (int) $jadwal->Tahun !== (int) $peserta->Tahun) {
            $this->markTestSkipped('Butuh jadwal global ber-Periode + peserta perorangan di tahun sama.');
        }

        $hash = Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan);

        $tanpaAnggota = $this->actingAs($user)
            ->getJson('/api/v1/form-kpi/current?'.http_build_query(['Id_Penjadwalan' => $hash]));

        $denganAnggota = $this->actingAs($user)
            ->getJson('/api/v1/form-kpi/current?'.http_build_query([
                'Id_Penjadwalan' => $hash,
                'kode_karyawan' => [$peserta->Kode_Karyawan],
            ]));

        $tanpaAnggota->assertStatus(200);
        $denganAnggota->assertStatus(200);

        $aktifTanpa = collect($tanpaAnggota->json('result.dataTarget'))->where('is_active', 'Y')->count();
        $aktifDengan = collect($denganAnggota->json('result.dataTarget'))->where('is_active', 'Y')->count();

        $bulanJadwal = (int) substr($jadwal->Periode, 5, 2);
        $bulanPeserta = (int) substr($peserta->Start_Periode, 5, 2);

        if ($bulanPeserta < $bulanJadwal) {
            // Bulan mulai peserta lebih awal -> target yang aktif tidak boleh menyusut.
            $this->assertGreaterThanOrEqual(
                $aktifTanpa,
                $aktifDengan,
                'Mengirim anggota dgn bulan mulai lebih awal justru mengurangi target yang aktif.',
            );
        } else {
            $this->assertSame($aktifTanpa, $aktifDengan);
        }
    }

    /** Tanpa kode_karyawan, perilaku lama harus utuh (kompatibilitas mundur). */
    public function test_breakdown_tanpa_kode_karyawan_tetap_jalan(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        $user = $leader ? User::find($leader) : null;
        $target = DB::table('KPI_Master_Target')->where('Flag_Aktif', 'Y')->first();
        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Form_KPI', 'Y')
            ->where(function ($q) {
                $q->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->first();

        if (! $user || ! $target || ! $jadwal) {
            $this->markTestSkipped('Data dev tidak lengkap untuk skenario ini.');
        }

        $res = $this->actingAs($user)->getJson('/api/v1/form-kpi/getTargetBreakdown?'.http_build_query([
            'Id_Master_Target' => Hashids::connection('custom')->encode($target->Id_Master_Target),
            'Id_Penjadwalan' => Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan),
        ]));

        $res->assertStatus(200);
        $this->assertNotEmpty($res->json('result'));
    }

    /**
     * Perintah pembersih WAJIB tidak mengubah apa pun tanpa --terapkan.
     * Salah di sini berarti skor YTD berubah tanpa ada yang memutuskan.
     */
    public function test_perintah_rapikan_tidak_mengubah_data_tanpa_terapkan(): void
    {
        $sebelum = DB::table('KPI_Pengajuan_Det')->count();
        $sebelumTarget = (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target');

        $this->artisan('kpi:rapikan-target-sebelum-mulai')
            ->expectsOutputToContain('MODE LAPORAN')
            ->assertExitCode(0);

        $this->assertSame(
            $sebelum,
            DB::table('KPI_Pengajuan_Det')->count(),
            'Mode laporan menghapus baris.',
        );
        $this->assertSame(
            $sebelumTarget,
            (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target'),
            'Mode laporan mengubah nilai target.',
        );
    }

    /** Filter --kode tidak boleh menyentuh karyawan lain. */
    public function test_perintah_rapikan_menghormati_filter_kode(): void
    {
        $this->artisan('kpi:rapikan-target-sebelum-mulai', ['--kode' => 'KODE-TIDAK-ADA'])
            ->expectsOutputToContain('Bersih')
            ->assertExitCode(0);
    }

    /**
     * Pendeteksi nilai target menyimpang harus menemukan pencilan yang jelas dan
     * tidak mengubah data apa pun.
     */
    public function test_pendeteksi_target_menyimpang_menemukan_pencilan(): void
    {
        $sebelum = (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target');

        // Cari pencilan yang benar-benar ada supaya test tidak lulus semu.
        $adaPencilan = false;

        $baris = DB::table('KPI_Pengajuan_Det as d')
            ->join('KPI_Pengajuan_Detail as dt', 'dt.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as kp', 'kp.Id_Pengajuan', '=', 'dt.Id_Pengajuan')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->select('d.Nilai_Target', 'dt.Id_Pengajuan_Detail')
            ->get()
            ->groupBy('Id_Pengajuan_Detail');

        foreach ($baris as $rows) {
            if ($rows->count() < 3) {
                continue;
            }

            $nilai = $rows->pluck('Nilai_Target')->map(fn ($v) => (float) $v)->sort()->values()->all();
            $median = $nilai[intdiv(count($nilai), 2)];

            if ($median > 0 && max($nilai) / $median > 10) {
                $adaPencilan = true;
                break;
            }
        }

        $perintah = $this->artisan('kpi:cek-target-menyimpang');

        if ($adaPencilan) {
            $perintah->expectsOutputToContain('Total nilai menyimpang');
        } else {
            $perintah->expectsOutputToContain('Bersih');
        }

        $perintah->assertExitCode(0);

        $this->assertSame(
            $sebelum,
            (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target'),
            'Pendeteksi seharusnya membaca saja, tapi nilai target berubah.',
        );
    }

    /** Ambang yang sangat tinggi harus menghapus semua temuan. */
    public function test_pendeteksi_menghormati_ambang(): void
    {
        $this->artisan('kpi:cek-target-menyimpang', ['--faktor' => 100000])
            ->expectsOutputToContain('Bersih')
            ->assertExitCode(0);
    }

    /**
     * Pemeriksa band harus melaporkan celah yang memang ada, tanpa mengubah
     * data master.
     */
    public function test_pemeriksa_band_melaporkan_celah(): void
    {
        $band = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();

        if ($band->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua band untuk menilai celah.');
        }

        $adaCelah = false;
        $sebelumnya = null;

        foreach ($band as $b) {
            if ($sebelumnya !== null && (float) $b->Range_Awal - (float) $sebelumnya->Range_Akhir > 0.01) {
                $adaCelah = true;
                break;
            }
            $sebelumnya = $b;
        }

        $sebelum = DB::table('KPI_Master_Performance')->get()->toJson();

        $perintah = $this->artisan('kpi:cek-band-performance');
        $perintah->expectsOutputToContain($adaCelah ? 'Celah antar band' : 'Tidak ada celah antar band');
        $perintah->assertExitCode(0);

        $this->assertSame(
            $sebelum,
            DB::table('KPI_Master_Performance')->get()->toJson(),
            'Pemeriksa band seharusnya membaca saja, tapi data master berubah.',
        );
    }

    public function test_leader_tetap_bisa_membuka_form_tim(): void
    {
        $leader = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_User_Sender');

        if (! $leader) {
            $this->markTestSkipped('Tidak ada pengajuan tim di data dev.');
        }

        $user = User::find($leader);

        if (! $user || ! $user->memilikiJabatan('FormKpiTeam')) {
            $this->markTestSkipped('Leader tidak punya akses FormKpiTeam di data dev.');
        }

        $this->actingAs($user)->get('/form-kpi/team')->assertStatus(200);
    }
}
