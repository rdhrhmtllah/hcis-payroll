<?php

namespace Tests\Feature\ESS\Attendance\Shift;

use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class StatusHariTanggalSelesaiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shift_permanen_tidak_membuat_tanggal_selesai_sebelum_tanggal_mulai(): void
    {
        $user = User::query()->first();
        $idShift = DB::table('HRIS_Shift_Kerja')->value('ID_Shift');

        if (! $user || ! $idShift) {
            $this->markTestSkipped('User atau master shift untuk regression test tidak tersedia.');
        }

        $this->actingAs($user);

        $kodeKaryawan = 'UJIS'.Str::upper(Str::random(8));
        $referensi = 'UJI-SH-'.Str::upper(Str::random(8));
        $tanggal = Carbon::parse('2099-01-01')->next(Carbon::WEDNESDAY)->format('Y-m-d');
        $sehariSebelumnya = Carbon::parse($tanggal)->subDay()->format('Y-m-d');
        $duaMingguSebelumnya = Carbon::parse($tanggal)->subWeeks(2)->format('Y-m-d');

        $offSatuHari = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => Carbon::parse($tanggal)->dayOfWeek + 1,
            'Tgl_Mulai' => $tanggal,
            'Tgl_Selesai' => $tanggal,
            'No_Transaksi_Reference' => 'TSH-UJI-SEMENTARA',
        ]);

        $offPermanenLama = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => Carbon::parse($tanggal)->dayOfWeek + 1,
            'Tgl_Mulai' => $duaMingguSebelumnya,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'TSH-UJI-PERMANEN-LAMA',
        ]);

        $offPermanenMulaiHariIni = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => Carbon::parse($tanggal)->dayOfWeek + 1,
            'Tgl_Mulai' => $tanggal,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'TSH-UJI-PERMANEN-HARI-INI',
        ]);

        $workLama = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'WORK',
            'Hari' => null,
            'Tgl_Mulai' => $duaMingguSebelumnya,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'SH-UJI-WORK-LAMA',
        ]);

        $tukarHari = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => Carbon::parse($tanggal)->dayOfWeek + 1,
            'Tgl_Mulai' => $duaMingguSebelumnya,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'TH-UJI-TETAP',
        ]);

        $tanpaReferensi = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => Carbon::parse($tanggal)->dayOfWeek + 1,
            'Tgl_Mulai' => $duaMingguSebelumnya,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => null,
        ]);

        $jumlahUpdateStatusHari = 0;
        DB::listen(function ($query) use (&$jumlahUpdateStatusHari) {
            $sql = str_replace(['[', ']'], '', strtolower(preg_replace('/\s+/', ' ', $query->sql)));
            if (str_starts_with($sql, 'update n_hris_status_hari_karyawan')) {
                $jumlahUpdateStatusHari++;
            }
        });

        $this->panggilApplyShiftDirect(
            $kodeKaryawan,
            $tanggal,
            (int) $idShift,
            'PERMANEN',
            $referensi,
            'OFF',
        );

        $this->assertSame($tanggal, $this->tanggalSelesai($offSatuHari));
        $this->assertSame('Y', $this->flagAktif($offSatuHari));
        $this->assertNull($this->tanggalSelesai($offPermanenLama));
        $this->assertSame('T', $this->flagAktif($offPermanenLama));
        $this->assertNull($this->tanggalSelesai($offPermanenMulaiHariIni));
        $this->assertSame('T', $this->flagAktif($offPermanenMulaiHariIni));
        $this->assertNull($this->tanggalSelesai($workLama));
        $this->assertNull($this->tanggalSelesai($tukarHari));
        $this->assertNull($this->tanggalSelesai($tanpaReferensi));
        $this->assertSame(1, $jumlahUpdateStatusHari, 'Penutupan OFF harus tetap satu perjalanan database.');

        $penggantiOffLama = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('No_Transaksi_Reference', 'TSH-UJI-PERMANEN-LAMA')
            ->where('Flag_Aktif', 'Y')
            ->sole();
        $penggantiOffHariIni = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('No_Transaksi_Reference', 'TSH-UJI-PERMANEN-HARI-INI')
            ->where('Flag_Aktif', 'Y')
            ->sole();

        $this->assertSame($sehariSebelumnya, Carbon::parse($penggantiOffLama->Tgl_Selesai)->format('Y-m-d'));
        $this->assertSame($tanggal, Carbon::parse($penggantiOffHariIni->Tgl_Selesai)->format('Y-m-d'));
        $this->assertSame('2000-01-01 00:00:00.000', Carbon::parse($penggantiOffLama->Created_At)->format('Y-m-d H:i:s.v'));
        $this->assertSame('2000-01-01 00:00:00.000', Carbon::parse($penggantiOffHariIni->Created_At)->format('Y-m-d H:i:s.v'));

        $overlay = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('No_Transaksi_Reference', $referensi)
            ->where('Status_Hari', 'WORK')
            ->sole();

        $this->assertSame($tanggal, Carbon::parse($overlay->Tgl_Mulai)->format('Y-m-d'));
        $this->assertSame($tanggal, Carbon::parse($overlay->Tgl_Selesai)->format('Y-m-d'));

        $statusHari = DB::selectOne(
            'SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) AS Status_Hari',
            ['001', $kodeKaryawan, $tanggal],
        )->Status_Hari ?? null;

        $this->assertSame('WORK', $statusHari);

        $jumlahInvalid = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereNotNull('Tgl_Selesai')
            ->whereColumn('Tgl_Selesai', '<', 'Tgl_Mulai')
            ->count();

        $this->assertSame(0, $jumlahInvalid);
    }

    public function test_target_shift_tidak_dikenal_ditolak_sebelum_penulisan(): void
    {
        $user = User::query()->first();
        $idShift = DB::table('HRIS_Shift_Kerja')->value('ID_Shift');

        if (! $user || ! $idShift) {
            $this->markTestSkipped('User atau master shift untuk regression test tidak tersedia.');
        }

        $this->actingAs($user);

        $kodeKaryawan = 'UJIT'.Str::upper(Str::random(8));
        $referensi = 'UJI-TARGET-'.Str::upper(Str::random(8));
        $sebelum = DB::table('HRIS_Shift_Per_Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->count();

        try {
            $this->panggilApplyShiftDirect(
                $kodeKaryawan,
                '2099-01-07',
                (int) $idShift,
                'LEADER_SHIFT',
                $referensi,
                'WORK',
            );
            $this->fail('Target di luar SEMENTARA/PERMANEN tidak ditolak.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Target shift harus SEMENTARA atau PERMANEN.', $e->getMessage());
        }

        $sesudah = DB::table('HRIS_Shift_Per_Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->count();

        $this->assertSame($sebelum, $sesudah);
        $this->assertFalse(
            DB::table('N_HRIS_Status_Hari_Karyawan')
                ->where('No_Transaksi_Reference', $referensi)
                ->exists(),
        );
    }

    public function test_stop_override_menonaktifkan_versi_lama_dan_insert_versi_baru_tanpa_mengubah_urutan(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('User untuk regression test tidak tersedia.');
        }

        $this->actingAs($user);
        $kodeKaryawan = 'UJIO'.Str::upper(Str::random(8));
        $id = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => 2,
            'Tgl_Mulai' => '2099-01-01',
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'TSH-UJI-STOP',
        ]);

        app(ShiftRequestService::class)->stopActiveOverride($id, '2099-01-31');

        $lama = DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $id)->first();
        $baru = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('No_Transaksi_Reference', 'TSH-UJI-STOP')
            ->where('Flag_Aktif', 'Y')
            ->sole();

        $this->assertSame('T', $lama->Flag_Aktif);
        $this->assertNull($lama->Tgl_Selesai);
        $this->assertNotSame((int) $lama->Id, (int) $baru->Id);
        $this->assertSame('2099-01-31', Carbon::parse($baru->Tgl_Selesai)->toDateString());
        $this->assertSame(
            Carbon::parse($lama->Created_At)->format('Y-m-d H:i:s.v'),
            Carbon::parse($baru->Created_At)->format('Y-m-d H:i:s.v'),
        );
        $this->assertSame((string) $lama->Created_By, (string) $baru->Created_By);
        $this->assertSame((string) $user->getAuthIdentifier(), (string) $lama->Updated_By);
        $this->assertSame((string) $user->getAuthIdentifier(), (string) $baru->Updated_By);
    }

    public function test_status_hari_permanen_mencap_tanggal_akhir_agar_tidak_sebelum_tanggal_mulai(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->markTestSkipped('User untuk regression test tidak tersedia.');
        }

        $this->actingAs($user);
        $dirty = Mockery::mock(AttendanceDirtyResolverService::class);
        $dirty->shouldReceive('markPermanentEffectByKodeKaryawan')->once();
        $this->app->instance(AttendanceDirtyResolverService::class, $dirty);

        $kodeKaryawan = 'UJIP'.Str::upper(Str::random(8));
        $tanggalMulai = '2099-01-05';
        $tanggalEfektif = '2099-01-07';
        $idLama = $this->buatStatusHari([
            'Kode_Karyawan' => $kodeKaryawan,
            'Status_Hari' => 'OFF',
            'Hari' => 2,
            'Tgl_Mulai' => $tanggalMulai,
            'Tgl_Selesai' => null,
            'No_Transaksi_Reference' => 'TSH-UJI-PERM-CAP',
        ]);

        app(ShiftRequestService::class)->applyStatusHariDirectForExistingTransaction(
            'TSH-UJI-PERM-BARU',
            $kodeKaryawan,
            $tanggalEfektif,
            'WORK',
            null,
            'Regression test status permanen',
            'PERMANEN',
        );

        $pengganti = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('No_Transaksi_Reference', 'TSH-UJI-PERM-CAP')
            ->where('Flag_Aktif', 'Y')
            ->sole();

        $this->assertSame('T', $this->flagAktif($idLama));
        $this->assertSame($tanggalMulai, Carbon::parse($pengganti->Tgl_Selesai)->toDateString());
        $this->assertSame(0, DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereNotNull('Tgl_Selesai')
            ->whereColumn('Tgl_Selesai', '<', 'Tgl_Mulai')
            ->count());
    }

    private function buatStatusHari(array $nilai): int
    {
        return DB::table('N_HRIS_Status_Hari_Karyawan')->insertGetId(array_merge([
            'Kode_Perusahaan' => '001',
            'Flag_Aktif' => 'Y',
            'Keterangan' => 'Regression test tanggal selesai status hari',
            'Created_At' => '2000-01-01 00:00:00.000',
            'Created_By' => 'TEST',
        ], $nilai));
    }

    private function flagAktif(int $id): string
    {
        return (string) DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $id)->value('Flag_Aktif');
    }

    private function tanggalSelesai(int $id): ?string
    {
        $nilai = DB::table('N_HRIS_Status_Hari_Karyawan')->where('Id', $id)->value('Tgl_Selesai');

        return $nilai === null ? null : Carbon::parse($nilai)->format('Y-m-d');
    }

    private function panggilApplyShiftDirect(
        string $kodeKaryawan,
        string $tanggal,
        int $idShift,
        string $target,
        string $referensi,
        string $statusHari,
    ): void {
        $metode = new ReflectionMethod(ShiftRequestService::class, 'applyShiftDirect');
        $metode->setAccessible(true);
        $metode->invoke(
            app(ShiftRequestService::class),
            $kodeKaryawan,
            ['tanggal' => $tanggal, 'id_shift' => $idShift],
            $target,
            $referensi,
            true,
            $statusHari,
            true,
        );
    }
}
