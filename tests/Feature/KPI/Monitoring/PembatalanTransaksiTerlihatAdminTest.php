<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\KpiCancellationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Apa yang MEMBLOKIR pengisian harus bisa dilihat — dan dibatalkan — admin.
 *
 * ── Jalan buntu yang dijaga ─────────────────────────────────────────────────
 *
 * Penjaga finalisasi (assertBelumAdaRealisasiHidup) menolak pengajuan baru
 * berdasarkan (Id_User, Periode) — ia tidak peduli baris lama itu bernaung di
 * penjadwalan mana. Monitoring Performance Tracking dulu hanya melihat baris
 * yang Id_Penjadwalan-nya ada dalam scope penjadwalan penilaian aktif. Begitu
 * penjadwalannya ditutup — atau ternyata bukan jadwal penilaian sama sekali —
 * barisnya HILANG dari layar admin tetapi TETAP memblokir pengisian.
 *
 * Hasilnya keadaan yang tidak punya jalan keluar: pengguna ditolak
 * "Batalkan yang lama terlebih dahulu", sementara tidak ada seorang pun yang
 * bisa membatalkannya karena tidak ada satu layar pun yang menampilkannya.
 *
 * Diukur di Web_HRIS 10 September 2026: 60 dari 64 realisasi hidup berada dalam
 * keadaan itu. Contoh nyatanya AKPI-06-2026-0032 (A4, periode 2026-05) yang
 * bernaung di penjadwalan #17 — periode 2026-07, Flag_Penilaian_KPI NULL, dan
 * sudah tidak aktif.
 *
 * Yang dijaga: baris yang memblokir sebuah periode HARUS muncul di monitoring
 * periode itu, membawa Id_Update_Performance (tanpa itu checkbox dan tombol
 * batalnya mati), dan benar-benar diterima jalur pembatalan.
 */
class PembatalanTransaksiTerlihatAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const PERIODE = '2031-04';

    public function test_baris_yang_memblokir_terlihat_dan_bisa_dibatalkan_admin(): void
    {
        $karyawan = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->where('Aktif', 'Y')
            ->first(['UserID_Web', 'Kode_Karyawan', 'Nama']);

        if (! $karyawan) {
            $this->markTestSkipped('Tidak ada karyawan aktif berakun di data ini.');
        }

        // Jadwal penilaian yang membentuk SCOPE monitoring untuk periode uji.
        DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji scope monitoring',
            'Tahun' => (int) substr(self::PERIODE, 0, 4),
            'Periode' => self::PERIODE,
            'Flag_Penilaian_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(3),
            'Jadwal_Akhir' => now()->addDays(3),
        ]);

        // Penjadwalan ASING: bukan jadwal penilaian, sudah mati, dan periodenya
        // berbeda — persis bentuk penjadwalan #17 yang menaungi baris pemblokir.
        $jadwalAsing = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji jadwal asing',
            'Tahun' => (int) substr(self::PERIODE, 0, 4),
            'Periode' => '2031-09',
            'Flag_Penilaian_KPI' => null,
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'T',
            'Jadwal_Mulai' => now()->subYear(),
            'Jadwal_Akhir' => now()->subMonths(6),
        ]);

        $noTransaksi = 'UJI-BLOK-'.substr(bin2hex(random_bytes(6)), 0, 10);

        DB::table('KPI_Update_Performance')->insert([
            'No_Transaksi' => $noTransaksi,
            'Id_Pengajuan' => DB::table('KPI_Pengajuan')->value('Id_Pengajuan'),
            'Id_Penjadwalan' => $jadwalAsing,
            'Id_User' => (int) $karyawan->UserID_Web,
            'Periode' => self::PERIODE,
            'Status_Pengajuan' => 'disetujui',
            'Flag_Approval' => 'Y',
            'Flag_Aktif' => 'Y',
            'Status' => null,
            'Tanggal' => now(),
        ]);

        $id = (int) DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Update_Performance');

        // 1. Baris itu memang memblokir periode tersebut.
        $this->assertTrue(
            DB::table('KPI_Update_Performance')
                ->where('Id_User', $karyawan->UserID_Web)
                ->where('Periode', 'like', self::PERIODE.'%')
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Status')
                ->exists(),
            'Fixture tidak valid: barisnya tidak memblokir apa pun.',
        );

        // 2. Admin harus bisa MELIHATNYA di monitoring periode itu.
        $baris = $this->tracking(self::PERIODE, $karyawan->Kode_Karyawan);

        $this->assertNotNull(
            $baris,
            'Karyawan tidak muncul sama sekali di Performance Tracking periode '.self::PERIODE.'.',
        );
        $this->assertSame(
            $id,
            (int) ($baris['id_update_performance'] ?? 0),
            'Baris pemblokir tidak terbawa ke monitoring. Tanpa Id_Update_Performance, checkbox '.
            'dan tombol batal di layar admin mati, dan tak seorang pun bisa membuka jalan buntunya.',
        );
        $this->assertNotTrue(
            $baris['is_draft'] ?? false,
            'Baris final terbaca sebagai draft, sehingga tetap tidak bisa dipilih untuk dibatalkan.',
        );

        // 3. Dan jalur pembatalannya harus benar-benar menerimanya.
        $preview = app(KpiCancellationService::class)->buildPreview('performance', [$id]);
        $item = collect($preview['items'] ?? [])->firstWhere('id', $id);

        $this->assertNotNull($item, 'Preview pembatalan tidak mengenali transaksi itu.');
        $this->assertFalse(
            $item['blocked'] ?? true,
            'Transaksi pemblokir ditolak jalur pembatalan: '.($item['reason'] ?? '-'),
        );
    }

    /** @return array<string,mixed>|null */
    private function tracking(string $periode, string $kodeKaryawan): ?array
    {
        $admin = User::first();

        if (! $admin) {
            $this->markTestSkipped('Tidak ada akun untuk berperan sebagai admin.');
        }

        Auth::setUser($admin);

        $req = Request::create('/x', 'GET', [
            'periode' => $periode,
            'search' => $kodeKaryawan,
            'per_page' => 100,
            'page' => 1,
        ]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);

        $isi = json_decode(
            app(KpiEnforcementController::class)->getPerformanceTracking($req)->getContent(),
            true,
        );

        $rows = $isi['result']['rows'] ?? $isi['result'] ?? [];

        return collect($rows)->firstWhere('kode_karyawan', $kodeKaryawan);
    }
}
