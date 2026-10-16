<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Overtime\Request\LemburRequestService;
use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LemburSubmitSmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected ?User $actor = null;

    protected ?string $actorKodeKaryawan = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable permission gate agar smoke test tetap jalan di environment dev/qa.
        config()->set('services.hcis.lembur_request.enforce_permission', false);

        $actor = User::query()
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->select('KPI_Users.*', 'k.Kode_Karyawan')
            ->where('k.Kode_Perusahaan', '001')
            ->where('k.Aktif', 'Y')
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('N_HRIS_Role_User as ru')
                    ->whereColumn('ru.UserId_Web', 'KPI_Users.Id_Users')
                    ->where('ru.Flag_Aktif', 'Y');
            })
            ->first();

        if (! $actor) {
            $this->markTestSkipped('Tidak ada user + karyawan aktif yang punya functional role.');
        }

        $this->actor = User::find($actor->Id_Users);
        $this->actorKodeKaryawan = (string) $actor->Kode_Karyawan;
        Auth::login($this->actor);

        session(['access_mode' => 'admin']);
        app(AccessContext::class)->set('admin');
    }

    public static function submitTypeProvider(): array
    {
        return [
            'lembur_hk' => ['LEMBUR_HK', 'LEMBUR', '20:00:00', '22:00:00'],
            'lembur_awal' => ['LEMBUR_AWAL', 'LEMBUR_AWAL', '05:00:00', null],
            'lembur_libur' => ['LEMBUR_LIBUR', 'LEMBUR_LIBUR', '08:00:00', '12:00:00'],
        ];
    }

    /**
     * @dataProvider submitTypeProvider
     */
    public function test_submit_smoke_per_type_without_migration(
        string $requestType,
        string $expectedJenis,
        string $startTime,
        ?string $endTime
    ): void {
        /** @var LemburRequestService $service */
        $service = app(LemburRequestService::class);
        $candidate = $this->findActiveCandidate($service, $requestType, $startTime, $endTime);

        if (! $candidate) {
            $this->markTestSkipped("Tidak ditemukan kandidat aktif untuk tipe {$requestType} pada data real.");
        }

        $row = $this->buildRowFromCandidate($candidate);

        $context = [
            'kode_perusahaan' => '001',
            'date' => $candidate['date'],
            'type' => $requestType,
            'start_time' => $startTime,
            'end_time' => $endTime ?? $startTime,
        ];

        $submit = $service->submit([$row], $context, true);

        $this->assertSame(1, (int) ($submit['inserted'] ?? 0), 'Submit harus insert 1 row.');
        $this->assertNotEmpty($submit['no_transaksi'] ?? null, 'No transaksi wajib terisi.');

        $noTransaksi = (string) $submit['no_transaksi'];
        $header = DB::table('Transaksi_Lembur')
            ->where('Kode_Perusahaan', '001')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        $this->assertNotNull($header, 'Header transaksi lembur tidak ditemukan.');
        $this->assertSame($this->actorKodeKaryawan, (string) ($header->UserID ?? ''));
        $this->assertSame('Y', (string) ($header->Flag_Admin ?? ''));
        /* 'Y', bukan 'T'. LemburRequestService menulis Flag_Selesai = 'Y' TETAP
           di dalam insert headernya — tak ada jalur submit yang pernah
           menghasilkan 'T', sehingga harapan lama tak mungkin terpenuhi sejak
           bentuk kode ini ada. Yang dijaga di sini keadaan yang benar-benar
           dijanjikan submit; kalau kolom ini kelak harus mewakili status
           persetujuan, perubahannya di service, bukan di sini. */
        $this->assertSame('Y', (string) ($header->Flag_Selesai ?? ''));
        $this->assertNotSame('', trim((string) ($header->Updated_By ?? '')));

        $detail = DB::table('Transaksi_Lembur_Detail')
            ->where('Kode_Perusahaan', '001')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        $this->assertNotNull($detail, 'Detail transaksi lembur tidak ditemukan.');
        $this->assertSame($expectedJenis, (string) ($detail->Jenis ?? ''));
    }

    protected function findActiveCandidate(
        LemburRequestService $service,
        string $type,
        string $startTime,
        ?string $endTime
    ): ?array {
        $dates = $service->getTanggalOptions(true);

        foreach ($dates as $date) {
            $request = new Request([
                'date' => $date,
                'type' => $type,
                'startTime' => $startTime,
                'endTime' => $endTime ?? $startTime,
                'time' => $endTime ?? $startTime,
            ]);

            $users = $service->getActiveUsers($request, true);
            $active = collect($users)->first(function ($u) use ($type) {
                $isActive = (bool) ($u->isActive ?? false);
                if (! $isActive) {
                    return false;
                }

                if ($type === 'LEMBUR_LIBUR') {
                    $status = strtoupper((string) ($u->StatusHari ?? ''));

                    return str_starts_with($status, 'OFF');
                }

                return true;
            });

            if ($active) {
                return [
                    'date' => $date,
                    'type' => $type,
                    'start' => $startTime,
                    'end' => $endTime,
                    'user' => $active,
                ];
            }
        }

        return null;
    }

    protected function buildRowFromCandidate(array $candidate): array
    {
        $u = $candidate['user'];

        return [
            'id' => (string) ($u->userId ?? ''),
            'nama' => (string) ($u->name ?? ''),
            'divisi' => (string) ($u->Divisi ?? ''),
            'shift' => (string) ($u->Nama_Shift ?? ''),
            'idShift' => $u->ID_Shift ?? null,
            'jamMasuk' => $u->Jam_Masuk ?? null,
            'jamKeluar' => $u->Jam_Keluar ?? null,
            'snapshot_shift_id' => $u->ID_Shift ?? null,
            'snapshot_status_hari' => $u->StatusHari ?? null,
            'snapshot_jam_masuk' => $u->Jam_Masuk ?? null,
            'snapshot_jam_keluar' => $u->Jam_Keluar ?? null,
            'snapshot_checked_at' => $u->eligibility_checked_at ?? null,
            'tanggal' => $candidate['date'],
            'effectiveWorkDate' => $candidate['date'],
            'type' => $candidate['type'],
            'startTime' => $candidate['start'],
            'endTime' => $candidate['end'],
            'reason' => 'AUTO TEST SUBMIT LEMBUR',
        ];
    }
}
