<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jadwal seminggu self-shift: dua kueri per hari, bukan dua kueri per rentang.
 *
 * `getWeekSchedule()` menembak dua kueri untuk tiap hari di dalam `map()`:
 * status hari lewat fungsi skalar `N_HRIS_FN_GetStatusHari`, dan jadwal shift
 * lewat `HRIS_Shift_Kerja`. Seminggu berarti **14 kueri** yang tak satu pun
 * perlu berdiri sendiri.
 *
 * Sesudah keduanya diambil untuk seluruh rentang sekaligus:
 * `self-shift/schedule` **20 → 7** kueri, halaman `self-shift` **34 → 22**.
 *
 * Penggantinya bukan kueri baru. `N_HRIS_FN_GetStatusHariRange` sudah lama ada
 * di basis data dan sudah dipakai di berkas yang sama — ia hanya belum dipakai
 * di sini. Bahwa ia menjawab sama persis dengan bentuk skalarnya diperiksa
 * lebih dulu pada 25 karyawan x 4 rentang x 7 hari: 700 cocok, nol beda.
 * Uji di bawah ini mengadu keduanya lagi setiap kali dijalankan, supaya
 * kesetaraan itu tidak berhenti menjadi benar diam-diam.
 */
class JadwalSelfShiftKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTE = '/self-shift/schedule';

    private string $kodeKaryawan;

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first();

        if (! $aktor || ! $aktor->karyawan?->Kode_Karyawan) {
            $this->markTestSkipped('User uji tanpa data karyawan di data dev.');
        }

        $this->kodeKaryawan = (string) $aktor->karyawan->Kode_Karyawan;

        $this->withoutMiddleware();
        Auth::login($aktor);
    }

    public function test_jadwal_tidak_menembak_dua_kueri_per_hari(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $hari = count($res->json('data.schedule') ?? []);
        if ($hari < 3) {
            $this->markTestSkipped("Hanya {$hari} hari terbentuk; terlalu sedikit untuk membedakan sekali dari per-hari.");
        }

        $skalar = 0;
        $shift = 0;
        foreach ($kueri as $sql) {
            /* Bentuk skalar dan bentuk rentang punya nama yang beririsan, jadi
               yang dihitung hanya pemanggilan skalarnya. */
            if (stripos($sql, 'N_HRIS_FN_GetStatusHari(') !== false) {
                $skalar++;
            }
            if (stripos($sql, 'HRIS_Shift_Kerja') !== false) {
                $shift++;
            }
        }

        $this->assertLessThan(
            $hari,
            $skalar,
            "Fungsi skalar N_HRIS_FN_GetStatusHari dipanggil {$skalar}x untuk {$hari} hari — biayanya ikut "
            .'tumbuh per hari. Statusnya harus diambil sekali lewat N_HRIS_FN_GetStatusHariRange.'
        );

        $this->assertLessThan(
            $hari,
            $shift,
            "Kueri ke HRIS_Shift_Kerja berjalan {$shift}x untuk {$hari} hari — biayanya ikut tumbuh per hari. "
            .'Jadwal shift harus diambil sekali untuk seluruh pasangan (shift, hari) yang mungkin dipakai.'
        );
    }

    /**
     * Status tiap hari harus sama dengan yang dijawab fungsi skalarnya.
     *
     * Inilah yang menjaga penggantian itu sendiri: kalau suatu saat bentuk
     * rentang dan bentuk skalar tak lagi sepakat — misalnya karena salah satu
     * diubah di basis data — uji ini yang memberi tahu, bukan pengguna.
     */
    public function test_status_hari_sama_dengan_bentuk_skalar(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $jadwal = $res->json('data.schedule') ?? [];
        if ($jadwal === []) {
            $this->markTestSkipped('Jadwal kosong di data dev.');
        }

        $statusBerbeda = [];

        foreach ($jadwal as $hari) {
            $this->assertArrayHasKey('status_hari', $hari, "Baris jadwal kehilangan medan 'status_hari'.");

            $skalar = DB::selectOne('SELECT dbo.N_HRIS_FN_GetStatusHari(?, ?, ?) as status_hari', [
                '001',
                $this->kodeKaryawan,
                $hari['date'],
            ])->status_hari ?? 'WORK';

            $this->assertSame(
                (string) $skalar,
                (string) $hari['status_hari'],
                "Tanggal {$hari['date']} dilaporkan '{$hari['status_hari']}' padahal fungsi skalarnya menjawab "
                ."'{$skalar}'. Peta rentang kemungkinan meleset tanggalnya."
            );

            $statusBerbeda[(string) $skalar] = true;
        }

        /* Premisnya diperiksa: kalau seluruh minggu berstatus sama, pemetaan
           yang meleset satu hari pun tetap cocok. */
        $this->assertGreaterThan(
            1,
            count($statusBerbeda),
            'Seluruh hari berstatus sama ('.implode(', ', array_keys($statusBerbeda)).'), jadi peta yang '
            .'bergeser tanggalnya tetap akan cocok. Pilih rentang yang memuat hari kerja dan hari libur.'
        );
    }

    /**
     * Shift yang menempel pada satu hari harus benar-benar jadwal shift itu
     * untuk HARI ITU.
     *
     * Peta massal dikunci oleh pasangan (ID_Shift, Hari). Kalau kuncinya
     * bergeser, jam masuk hari Senin bisa menempel pada hari Rabu — tak ada
     * yang gagal, tak ada galat, dan uji jumlah kueri pun tetap hijau.
     */
    public function test_jam_shift_milik_hari_yang_benar(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $jadwal = $res->json('data.schedule') ?? [];
        $diperiksa = 0;

        foreach ($jadwal as $hari) {
            $shift = $hari['shift'] ?? null;
            if (! $shift) {
                continue;
            }

            $iso = Carbon::parse($hari['date'])->isoWeekday();
            $hariHRIS = $iso == 7 ? 1 : $iso + 1;

            $acuan = DB::table('HRIS_Shift_Kerja as sk')
                ->join('HRIS_Shift_Kerja_Detail as sd', 'sk.ID_Shift', '=', 'sd.ID_Shift')
                ->join('HRIS_Waktu_Kerja as w', 'sd.ID_Waktu_Kerja', '=', 'w.ID_Waktu_Kerja')
                ->where('sk.ID_Shift', $shift['id'])
                ->where('sd.Hari', $hariHRIS)
                ->select('sk.Nama', 'w.Jam_Masuk', 'w.Jam_Keluar')
                ->first();

            $this->assertNotNull(
                $acuan,
                "Tanggal {$hari['date']} melaporkan shift {$shift['id']} padahal shift itu tidak punya jadwal "
                ."untuk hari ke-{$hariHRIS}. Kunci peta (shift, hari) kemungkinan bergeser."
            );

            $this->assertSame((string) $acuan->Nama, (string) $shift['nama'], "Nama shift {$hari['date']} menyimpang.");
            $this->assertSame(
                substr((string) $acuan->Jam_Masuk, 0, 5),
                (string) $shift['jam_masuk'],
                "Jam masuk {$hari['date']} menyimpang — jam milik hari lain kemungkinan tertempel di sini."
            );
            $this->assertSame(
                substr((string) $acuan->Jam_Keluar, 0, 5),
                (string) $shift['jam_keluar'],
                "Jam keluar {$hari['date']} menyimpang."
            );

            /* `source` diturunkan di PHP, bukan kolom tabel; barisnya disalin
               sebelum ditempeli supaya satu baris peta tak bisa membawa penanda
               milik hari lain. */
            $this->assertContains(
                $hari['source'] ?? null,
                ['PERMANEN', 'SEMENTARA'],
                "Tanggal {$hari['date']} kehilangan penanda source-nya."
            );

            $diperiksa++;
        }

        $this->assertGreaterThan(
            0,
            $diperiksa,
            'Tak satu pun hari punya shift di data dev, jadi pemetaan (shift, hari) tidak teruji sama sekali.'
        );
    }
}
