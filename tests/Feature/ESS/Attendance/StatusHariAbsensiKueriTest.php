<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Status hari pada jadwal absensi seminggu diambil sekali, bukan per hari.
 *
 * `getUserShift()` memanggil `getStatusHariInfo()` di dalam `map()`: satu
 * pemanggilan fungsi skalar `N_HRIS_FN_GetStatusHari` per hari, ditambah satu
 * pencarian `HRIS_Hari_Libur` untuk tiap hari yang berstatus OFF. Seminggu
 * berarti sampai **14 kueri** untuk keterangan yang seluruhnya bisa diambil
 * dalam dua.
 *
 * Sesudahnya: **16 → 10** kueri.
 *
 * YANG TIDAK BERUBAH, DAN ITU DISENGAJA.
 * Tujuh `exec sp_getShiftAbsen` tetap tujuh. Prosedurnya berkontrak satu
 * tanggal (`@Tanggal DATE`), jadi membuatnya melayani rentang berarti mengubah
 * DDL — perubahan tersendiri dengan alur persetujuannya sendiri, bukan bagian
 * dari pas performa ini.
 *
 * DUA CARA KUNCI BISA RUSAK, DAN MASING-MASING PUNYA PENJAGANYA.
 * Pemakaian petanya berpasangan dengan cadangan ke bentuk tunggal, dan itu
 * hanya menolong kalau kuncinya HILANG: jawabannya tetap benar, yang naik
 * jumlah kuerinya — tertangkap uji pertama. Kunci yang BERGESER lain
 * ceritanya: tanggal+1 masih ada di peta, jadi cadangannya tak pernah
 * jalan dan yang keluar adalah status milik hari sebelahnya — tertangkap uji
 * kedua. Keduanya sudah dibuktikan dengan sabotase, bukan diduga.
 */
class StatusHariAbsensiKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTE = '/absensi/getUserShift';

    private string $kodeKaryawan;

    /** Minggu yang diminta ke rute, dipilih supaya memuat hari libur. */
    private string $tanggalUji;

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first();

        if (! $aktor || ! $aktor->karyawan?->Kode_Karyawan) {
            $this->markTestSkipped('User uji tanpa data karyawan di data dev.');
        }

        $this->kodeKaryawan = (string) $aktor->karyawan->Kode_Karyawan;

        /* Minggunya dipilih dari tabel hari libur, BUKAN dari hari ini. Uji
           yang bersandar pada minggu berjalan akan berubah warna sendiri begitu
           kalender bergeser — merah karena tanggal, bukan karena kode, dan
           penjaga yang begitu cepat diabaikan. */
        $libur = DB::table('HRIS_Hari_Libur')
            ->where('Kode_Perusahaan', '001')
            ->orderByDesc('Tanggal')
            ->value('Tanggal');

        if (! $libur) {
            $this->markTestSkipped('Tabel HRIS_Hari_Libur kosong; tak ada minggu berhari-libur untuk diuji.');
        }

        $this->tanggalUji = Carbon::parse($libur)->format('Y-m-d');

        $this->withoutMiddleware();
        Auth::login($aktor);
    }

    private function rute(): string
    {
        return self::RUTE.'?tanggal='.$this->tanggalUji;
    }

    public function test_status_hari_tidak_diambil_per_hari(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson($this->rute());
        $res->assertOk();

        $hari = count($res->json('data') ?? []);
        if ($hari < 3) {
            $this->markTestSkipped("Hanya {$hari} hari terbentuk; terlalu sedikit untuk membedakan sekali dari per-hari.");
        }

        $skalar = 0;
        $libur = 0;
        foreach ($kueri as $sql) {
            /* Nama bentuk skalar dan bentuk rentang beririsan, jadi kurung
               buka ikut dijadikan penanda. */
            if (stripos($sql, 'N_HRIS_FN_GetStatusHari(') !== false) {
                $skalar++;
            }
            if (stripos($sql, 'HRIS_Hari_Libur') !== false) {
                $libur++;
            }
        }

        $this->assertLessThan(
            $hari,
            $skalar,
            "Fungsi skalar N_HRIS_FN_GetStatusHari dipanggil {$skalar}x untuk {$hari} hari — biayanya ikut "
            .'tumbuh per hari. Statusnya harus diambil sekali lewat getStatusHariInfoRentang().'
        );

        $this->assertLessThan(
            $hari,
            $libur,
            "Kueri ke HRIS_Hari_Libur berjalan {$libur}x untuk {$hari} hari — nama hari liburnya harus "
            .'diambil sekali untuk seluruh rentang.'
        );
    }

    /**
     * Ketiga medan status harus sama dengan yang dijawab bentuk tunggalnya.
     *
     * Bentuk lamanya dijalankan ulang di sini — itulah yang diadu.
     */
    public function test_ketiga_medan_status_sama_dengan_bentuk_tunggal(): void
    {
        $res = $this->getJson($this->rute());
        $res->assertOk();

        $baris = $res->json('data') ?? [];
        if ($baris === []) {
            $this->markTestSkipped('Jadwal absensi kosong di data dev.');
        }

        $statusBerbeda = [];
        $adaLabel = 0;

        foreach ($baris as $hari) {
            $tanggal = $hari['Tanggal'] ?? null;
            if (! $tanggal) {
                continue;
            }

            foreach (['status_hari', 'status_hari_label', 'is_day_off'] as $medan) {
                $this->assertArrayHasKey($medan, $hari, "Baris {$tanggal} kehilangan medan '{$medan}'.");
            }

            $statusHari = DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari', [
                '001',
                $this->kodeKaryawan,
                $tanggal,
            ])->status_hari ?? 'WORK';

            $isDayOff = str_starts_with($statusHari, 'OFF');

            $label = null;
            if ($isDayOff) {
                $label = DB::table('HRIS_Hari_Libur')
                    ->where('Kode_Perusahaan', '001')
                    ->whereDate('Tanggal', $tanggal)
                    ->value('Nama_Hari_Libur');
            }

            $this->assertSame((string) $statusHari, (string) $hari['status_hari'], "status_hari {$tanggal} menyimpang.");
            $this->assertSame($isDayOff, (bool) $hari['is_day_off'], "is_day_off {$tanggal} menyimpang.");
            $this->assertSame(
                $label,
                $hari['status_hari_label'],
                "status_hari_label {$tanggal} menyimpang — nama hari libur milik tanggal lain kemungkinan "
                .'tertempel di sini, atau labelnya hilang.'
            );

            $statusBerbeda[(string) $statusHari] = true;
            if ($label !== null) {
                $adaLabel++;
            }
        }

        /* Dua premis. Kalau seluruh minggu berstatus sama, peta yang bergeser
           tanggalnya tetap cocok. Kalau tak satu hari pun punya nama hari
           libur, cabang pencarian labelnya tak pernah terlewati. */
        $this->assertGreaterThan(
            1,
            count($statusBerbeda),
            'Seluruh hari berstatus sama ('.implode(', ', array_keys($statusBerbeda)).'), jadi peta yang '
            .'bergeser tanggalnya tetap akan cocok.'
        );

        $this->assertGreaterThan(
            0,
            $adaLabel,
            'Tak satu hari pun membawa nama hari libur, jadi pencarian labelnya tidak teruji sama sekali. '
            .'Pilih minggu yang memuat hari libur nasional.'
        );
    }
}
