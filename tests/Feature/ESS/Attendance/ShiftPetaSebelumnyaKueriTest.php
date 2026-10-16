<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Peta "shift sebelumnya" harus berbiaya TETAP, bukan per (anggota x tanggal).
 *
 * ── Skalanya, terukur ────────────────────────────────────────────────────────
 *
 * buildPreviousShiftMap() dulu memanggil getUserShiftForDate() sekali untuk
 * TIAP pasangan anggota-tanggal. Satu panggilan itu berharga tiga kueri: satu
 * `SELECT *` di Karyawan, dan satu eksekusi
 * N_HRIS_SP_GET_ABSEN_WEB_WRAPPER — prosedur yang tercatat 1.783 logical reads
 * sekali jalan.
 *
 * Seorang leader yang menetapkan shift untuk 40 anggotanya sepanjang 30 hari
 * membangkitkan 1.200 panggilan dan 3.600 kueri. Terukur 0,36 detik per
 * pasangan: sekitar tujuh menit, untuk satu layar.
 *
 * ── Kenapa ia tak pernah tertangkap ──────────────────────────────────────────
 *
 * Perulangannya TIDAK memuat kueri; yang dipanggilnya ada di berkas lain.
 * Pemindai N+1 berbasis "akar kueri di dalam foreach" melihat nol di sini, dan
 * kelas ini tak punya satu pun uji sehingga perekam kueri per-uji pun tak
 * pernah melewatinya. Penjaga inilah yang menutup keduanya.
 *
 * ── Ambangnya ────────────────────────────────────────────────────────────────
 *
 * Nyatanya 2 kueri (satu daftar UserID_Absen, satu eksekusi SP) berapa pun
 * jumlah pasangannya. Ambang di bawah memberi ruang tumbuh yang wajar sambil
 * tetap menangkap kembalinya bentuk per-pasangan seketika: satu pasangan saja
 * yang kembali per-panggilan sudah melewatinya pada populasi uji ini.
 */
class ShiftPetaSebelumnyaKueriTest extends TestCase
{
    use DatabaseTransactions;

    /** Nyatanya 2. Lihat catatan kelas. */
    private const AMBANG_KUERI = 8;

    public function test_biaya_peta_shift_tidak_tumbuh_per_pasangan(): void
    {
        $aktor = DB::table('KPI_Users')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->where('k.Kode_Perusahaan', '001')
            ->where('k.Aktif', 'Y')
            ->value('KPI_Users.Id_Users');

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user + karyawan aktif di data dev.');
        }

        Auth::login(User::find($aktor));

        $kode = DB::table('Karyawan')
            ->where('Kode_Perusahaan', '001')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Absen')
            ->orderBy('Kode_Karyawan')
            ->limit(6)
            ->pluck('Kode_Karyawan')
            ->all();

        if (count($kode) < 3) {
            $this->markTestSkipped('Karyawan ber-UserID_Absen terlalu sedikit untuk membuktikan pertumbuhannya.');
        }

        $tanggal = [];
        for ($i = 1; $i <= 5; $i++) {
            $tanggal[] = Carbon::yesterday()->subDays($i)->format('Y-m-d');
        }

        $pasangan = count($kode) * count($tanggal);

        /** @var ShiftRequestService $svc */
        $svc = app(ShiftRequestService::class);

        $cacah = 0;
        DB::listen(function () use (&$cacah) {
            $cacah++;
        });

        $peta = $svc->getUserShiftsForDates($kode, $tanggal);

        $this->assertLessThanOrEqual(
            self::AMBANG_KUERI,
            $cacah,
            sprintf(
                'Peta shift memakai %d kueri untuk %d pasangan (%.1f per pasangan). '.
                'Bentuk per-pasangan sudah kembali: tiap pasangan memanggil '.
                'N_HRIS_SP_GET_ABSEN_WEB_WRAPPER sendiri.',
                $cacah,
                $pasangan,
                $pasangan > 0 ? $cacah / $pasangan : 0,
            ),
        );

        /* Murah saja tidak cukup — petanya harus MENJAWAB setiap pasangan yang
           ditanyakan. Peta yang kosong juga murah. */
        foreach ($kode as $k) {
            $this->assertArrayHasKey($k, $peta, "Peta tak memuat karyawan {$k}.");
            foreach ($tanggal as $t) {
                $this->assertArrayHasKey(
                    $t,
                    $peta[$k],
                    "Peta tak menjawab pasangan {$k} / {$t} — pemanggil membedakan ".
                    '"tak ada shift" dari "tak ditanya".',
                );
            }
        }
    }

    /** Jawaban berhimpun harus sama dengan jawaban satu-satu. */
    public function test_hasil_berhimpun_sama_dengan_satu_per_satu(): void
    {
        $aktor = DB::table('KPI_Users')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->where('k.Kode_Perusahaan', '001')
            ->where('k.Aktif', 'Y')
            ->value('KPI_Users.Id_Users');

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user + karyawan aktif di data dev.');
        }

        Auth::login(User::find($aktor));

        $kode = DB::table('Karyawan')
            ->where('Kode_Perusahaan', '001')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Absen')
            ->orderBy('Kode_Karyawan')
            ->limit(3)
            ->pluck('Kode_Karyawan')
            ->all();

        if (count($kode) < 2) {
            $this->markTestSkipped('Karyawan ber-UserID_Absen terlalu sedikit.');
        }

        $tanggal = [
            Carbon::yesterday()->subDays(2)->format('Y-m-d'),
            Carbon::yesterday()->subDay()->format('Y-m-d'),
        ];

        /** @var ShiftRequestService $svc */
        $svc = app(ShiftRequestService::class);

        $berhimpun = $svc->getUserShiftsForDates($kode, $tanggal);

        foreach ($kode as $k) {
            foreach ($tanggal as $t) {
                $satu = $svc->getUserShiftForDate($k, Carbon::parse($t));

                $this->assertSame(
                    $satu === null ? null : (string) $satu,
                    $berhimpun[$k][$t] === null ? null : (string) $berhimpun[$k][$t],
                    "Jawaban berbeda untuk {$k} / {$t}: bentuk berhimpun tidak boleh ".
                    'mengubah hasil, hanya jumlah perjalanannya.',
                );
            }
        }
    }

    /**
     * Cek konflik permanen juga harus berbiaya TETAP.
     *
     * Bentuk lama: satu COUNT ke HRIS_Shift_Sementara per (anggota, tanggal).
     * Layar "cek konflik" memanggilnya untuk seluruh anggota dan seluruh
     * tanggal sekaligus, jadi 40 anggota x 30 tanggal = 1.200 COUNT.
     */
    public function test_biaya_cek_konflik_tidak_tumbuh_per_pasangan(): void
    {
        $this->masukSebagaiKaryawanAktif();

        $kode = $this->kodeKaryawanUji(6);
        $tanggal = [];
        for ($i = 1; $i <= 5; $i++) {
            $tanggal[] = Carbon::yesterday()->subDays($i)->format('Y-m-d');
        }

        $pasangan = count($kode) * count($tanggal);

        /** @var ShiftRequestService $svc */
        $svc = app(ShiftRequestService::class);

        $cacah = 0;
        DB::listen(function () use (&$cacah) {
            $cacah++;
        });

        $hasil = $svc->checkPermanentConflicts($kode, $tanggal);

        $this->assertLessThanOrEqual(
            self::AMBANG_KUERI,
            $cacah,
            sprintf(
                'Cek konflik memakai %d kueri untuk %d pasangan (%.1f per pasangan).',
                $cacah,
                $pasangan,
                $pasangan > 0 ? $cacah / $pasangan : 0,
            ),
        );

        /* Sama seperti peta shift: murah saja tidak cukup, angkanya harus BENAR. */
        foreach ($kode as $k) {
            foreach ($tanggal as $t) {
                $this->assertSame(
                    $svc->checkPermanentConflict($k, $t),
                    $hasil[$k][$t] ?? null,
                    "Jumlah konflik berbeda untuk {$k} / {$t}: bentuk berhimpun tidak boleh ".
                    'mengubah hasil, hanya jumlah perjalanannya.',
                );
            }
        }
    }

    private function masukSebagaiKaryawanAktif(): void
    {
        $aktor = DB::table('KPI_Users')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->where('k.Kode_Perusahaan', '001')
            ->where('k.Aktif', 'Y')
            ->value('KPI_Users.Id_Users');

        if (! $aktor) {
            $this->markTestSkipped('Tidak ada user + karyawan aktif di data dev.');
        }

        Auth::login(User::find($aktor));
    }

    /** @return list<string> */
    private function kodeKaryawanUji(int $jumlah): array
    {
        $kode = DB::table('Karyawan')
            ->where('Kode_Perusahaan', '001')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Absen')
            ->orderBy('Kode_Karyawan')
            ->limit($jumlah)
            ->pluck('Kode_Karyawan')
            ->all();

        if (count($kode) < 2) {
            $this->markTestSkipped('Karyawan ber-UserID_Absen terlalu sedikit.');
        }

        return $kode;
    }
}
