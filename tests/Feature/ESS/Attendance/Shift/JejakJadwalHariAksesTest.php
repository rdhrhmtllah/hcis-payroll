<?php

namespace Tests\Feature\ESS\Attendance\Shift;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Panel "Kenapa jadwal saya begini?" menampilkan siapa mengubah jadwal siapa,
 * jadi pintu Leader Shift WAJIB menolak karyawan di luar tim. Endpoint lama
 * di grup yang sama (override-for-date) tidak memeriksanya — jangan disalin.
 *
 * Middleware rute TIDAK dimatikan: yang diuji justru gerbang lengkapnya.
 */
class JejakJadwalHariAksesTest extends TestCase
{
    use DatabaseTransactions;

    private const TANGGAL = '2026-10-13';

    private ?User $leader = null;

    private string $anggota = '';

    private string $luarTim = '';

    protected function setUp(): void
    {
        parent::setUp();

        $pasangan = DB::table('HRIS_Karyawan_Team as kt')
            ->join('Karyawan as l', 'l.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->join('Karyawan as m', 'm.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
            ->whereNotNull('l.UserID_Web')->where('m.Aktif', 'Y')
            ->select('kt.Kode_Karyawan_Team as leader', 'kt.Kode_Karyawan as anggota', 'l.UserID_Web as uid')
            ->get();

        foreach ($pasangan->groupBy('uid') as $uid => $tim) {
            $user = User::find($uid);
            if (! $user || ! $user->memilikiJabatan('ShiftManagement')) {
                continue;
            }
            $this->leader = $user;
            $this->anggota = (string) $tim->first()->anggota;
            $this->luarTim = (string) DB::table('Karyawan')->where('Aktif', 'Y')
                ->whereNotIn('Kode_Karyawan', $tim->pluck('anggota')->push($tim->first()->leader)->all())
                ->value('Kode_Karyawan');
            break;
        }

        if (! $this->leader || $this->luarTim === '') {
            $this->markTestSkipped('Data dev tidak punya leader ber-tim yang memegang Leader Shift.');
        }
    }

    public function test_leader_melihat_jejak_anggota_timnya(): void
    {
        $res = $this->actingAs($this->leader)->getJson('/leader-shift/jejak-hari?kode='.$this->anggota.'&tanggal='.self::TANGGAL);

        $res->assertOk();
        $this->assertSame($this->anggota, $res->json('result.karyawan.kode'));
        $this->assertContains($res->json('result.hasil.kategori'), ['MASUK', 'LIBUR']);
        $this->assertIsArray($res->json('result.linimasa'));
    }

    public function test_leader_ditolak_untuk_karyawan_di_luar_timnya(): void
    {
        $res = $this->actingAs($this->leader)->getJson('/leader-shift/jejak-hari?kode='.$this->luarTim.'&tanggal='.self::TANGGAL);

        $res->assertStatus(403);
        $this->assertNull($res->json('result.linimasa'));
    }

    public function test_pintu_pribadi_hanya_menjawab_diri_sendiri(): void
    {
        // Parameter `kode` diabaikan: tidak ada cara mengintip orang lain dari sini.
        $res = $this->actingAs($this->leader)->getJson('/self-shift/jejak-hari?kode='.$this->luarTim.'&tanggal='.self::TANGGAL);

        if ($res->status() === 403 && ! $this->leader->memilikiJabatan('selfShift')) {
            $this->markTestSkipped('Leader contoh tidak memegang halaman Self Shift.');
        }
        $res->assertOk();
        $this->assertSame($this->leader->karyawan->Kode_Karyawan, $res->json('result.karyawan.kode'));
    }
}
