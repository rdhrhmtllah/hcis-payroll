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

/**
 * "Cari orang" lembur harus berbiaya TETAP, bukan sebanding jumlah karyawan.
 *
 * Layar ini meresolusi seluruh angkatan kerja aktif sekaligus. Selama biayanya
 * per orang, ia tumbuh diam-diam mengikuti pertumbuhan perusahaan: tidak ada
 * yang rusak, tidak ada error, hanya makin lama setiap bulan.
 *
 * Saat penjaga ini ditulis: 958 orang menghasilkan 2.029 kueri — dua per orang.
 * Ambang di bawah sengaja jauh di atas kebutuhan wajar (belasan kueri) dan jauh
 * di bawah bentuk per-orang, supaya ia menangkap KEMBALINYA pola itu, bukan
 * mengunci angka pastinya.
 *
 * 5 Sep 2026 — ambangnya diketatkan dari 50 jadi 15.
 *
 * Penjaga ini sempat MERAH di master: 54 kueri. Penyebabnya bukan pola dua-per-
 * orang yang lama, melainkan 48 kueri yang seluruhnya sia-sia —
 * buildActiveUsersEligibilityCache hanya mengisi next_shift_start bila besok
 * WORK, sehingga tiap karyawan yang besok LIBUR tidak punya kunci itu, dan
 * ketiga resolver membaca ketiadaan kunci sebagai "belum tahu" lalu bertanya ke
 * basis data. Jawabannya selalu "bukan WORK" — yaitu hal yang sudah dipegang
 * cache. Sesudah diperbaiki: 6 kueri untuk 958 orang.
 *
 * Ambang 50 dibiarkan berarti regresi 44 kueri bisa masuk tanpa suara. 15 masih
 * memberi ruang tumbuh yang wajar (penulis pertama menyebut "belasan kueri
 * sudah cukup") sambil tetap menangkap kembalinya bentuk per-orang seketika.
 */
class LemburCariOrangKueriTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Nyatanya 6. 15 memberi ruang tumbuh yang wajar tanpa menyembunyikan
     * kembalinya kueri per-orang — lihat catatan kelas di atas.
     */
    private const AMBANG_KUERI = 15;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.hcis.lembur_request.enforce_permission', false);

        $actor = User::query()
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'KPI_Users.Id_Users')
            ->select('KPI_Users.Id_Users')
            ->where('k.Kode_Perusahaan', '001')
            ->where('k.Aktif', 'Y')
            ->first();

        if (! $actor) {
            $this->markTestSkipped('Tidak ada user + karyawan aktif di data dev.');
        }

        Auth::login(User::find($actor->Id_Users));
        session(['access_mode' => 'admin']);
        app(AccessContext::class)->set('admin');
    }

    public function test_biaya_cari_orang_tidak_tumbuh_per_karyawan(): void
    {
        /** @var LemburRequestService $service */
        $service = app(LemburRequestService::class);

        $tanggal = $service->getTanggalOptions(true);

        if (empty($tanggal)) {
            $this->markTestSkipped('Tidak ada opsi tanggal lembur.');
        }

        $cacah = 0;
        DB::listen(function () use (&$cacah) {
            $cacah++;
        });

        $orang = $service->getActiveUsers(new Request([
            'date' => $tanggal[0],
            'type' => 'LEMBUR_HK',
            'startTime' => '20:00:00',
            'endTime' => '22:00:00',
            'time' => '22:00:00',
        ]), true);

        $jumlahOrang = is_countable($orang) ? count($orang) : 0;

        // Premis penjaga: kalau populasinya kecil, ambang kueri tak membuktikan
        // apa pun tentang pertumbuhan per orang.
        $this->assertGreaterThan(
            100,
            $jumlahOrang,
            'Populasi dev terlalu kecil untuk membuktikan biayanya tidak per-orang.',
        );

        $this->assertLessThanOrEqual(
            self::AMBANG_KUERI,
            $cacah,
            sprintf(
                'getActiveUsers memakai %d kueri untuk %d orang (%.1f per orang). '
                .'Biayanya harus tetap, bukan sebanding jumlah karyawan.',
                $cacah,
                $jumlahOrang,
                $jumlahOrang > 0 ? $cacah / $jumlahOrang : 0,
            ),
        );
    }
}
