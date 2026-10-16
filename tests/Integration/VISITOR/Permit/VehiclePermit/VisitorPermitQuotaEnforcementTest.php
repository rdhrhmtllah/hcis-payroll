<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MemesanSlotInspeksi;
use Tests\TestCase;

/**
 * Penegakan kuota di pintu pengajuan.
 *
 * Batas sengaja dihitung relatif terhadap isi DB, bukan angka mutlak: data dev
 * sudah memuat permit milik orang yang sama, dan fixture berangka mati akan
 * lulus atau gagal tergantung isi database hari itu.
 */
class VisitorPermitQuotaEnforcementTest extends TestCase
{
    use DatabaseTransactions;
    use MemesanSlotInspeksi;

    public function test_pengajuan_ditolak_saat_jatah_sudah_habis(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        $this->kunciBatasTepatDiJatahTerpakai($kode);

        $sebelum = $this->jumlahPermit($kode);
        $response = app(VehiclePermitController::class)->store($this->permintaan($user));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'VISITOR_PERMIT_LIMIT_REACHED',
            json_decode($response->getContent(), true)['error_code'] ?? null,
        );
        $this->assertSame($sebelum, $this->jumlahPermit($kode), 'Pengajuan yang ditolak tidak boleh menyisakan baris.');
    }

    public function test_pesan_penolakan_menyebut_angka_batasnya(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        $batas = $this->kunciBatasTepatDiJatahTerpakai($kode);

        $response = app(VehiclePermitController::class)->store($this->permintaan($user));

        // Ditolak tanpa menyebut angkanya membuat orang menebak-nebak berapa
        // yang boleh dan kapan jatahnya kembali.
        $this->assertStringContainsString(
            (string) $batas,
            (string) (json_decode($response->getContent(), true)['message'] ?? ''),
        );
    }

    public function test_replay_idempoten_tetap_dilayani_meski_jatah_habis(): void
    {
        [$kode, $user] = $this->pemohon();
        $kunci = 'QUOTA-REPLAY-'.Str::upper(Str::random(12));
        $noTransaksi = $this->buatPermitPending($kode, $kunci);
        $this->kunciBatasTepatDiJatahTerpakai($kode);

        $response = app(VehiclePermitController::class)->store($this->permintaan($user, $kunci));

        // Penjaga kuota yang ditaruh SEBELUM pemeriksaan replay akan menolak
        // pengiriman ulang yang sah — padahal ia tidak menambah apa pun.
        $this->assertSame(200, $response->getStatusCode());
        $isi = json_decode($response->getContent(), true);
        $this->assertTrue($isi['result']['idempotent_replay'] ?? false);
        $this->assertSame($noTransaksi, $isi['result']['no_transaksi'] ?? null);
    }

    public function test_jatah_yang_masih_tersisa_lolos_penjaga_kuota(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        config([
            'services.visitor.permit.max_pengajuan_aktif' => $this->terpakai($kode) + 1,
        ]);

        $response = app(VehiclePermitController::class)->store($this->permintaan($user));

        // Lolos kuota lalu berhenti di gerbang berikutnya membuktikan eksekusi
        // benar-benar melewati penjaga ini, bukan sekadar "tidak error kuota".
        $this->assertSame(
            'VISITOR_PERMIT_MASTER_INACTIVE',
            json_decode($response->getContent(), true)['error_code'] ?? null,
        );
    }

    public function test_batas_nol_tidak_membatasi_siapa_pun(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        $this->buatPermitPending($kode);
        $this->buatPermitPending($kode);
        config(['services.visitor.permit.max_pengajuan_aktif' => 0]);

        $response = app(VehiclePermitController::class)->store($this->permintaan($user));

        $this->assertSame(
            'VISITOR_PERMIT_MASTER_INACTIVE',
            json_decode($response->getContent(), true)['error_code'] ?? null,
        );
    }

    public function test_stats_membawa_kuota_dari_sumber_yang_sama_dengan_penjaga(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        config(['services.visitor.permit.max_pengajuan_aktif' => 7]);

        $response = app(VehiclePermitController::class)->getStats($this->permintaanStats($user));
        $isi = json_decode($response->getContent(), true)['result'] ?? [];

        // Dua definisi yang berbeda tipis membuat layar berkata "2/2 terpakai"
        // sementara penjaganya menghitung 1.
        $this->assertSame($this->terpakai($kode), $isi['kuota_terpakai'] ?? null);
        $this->assertSame(7, $isi['kuota_maks'] ?? null);
    }

    /**
     * Ketiga batas wajib ikut ke layar, bukan hanya jatah permit.
     *
     * Batas pending dan batas akses inspeksi harian dulu hanya menggigit
     * SESUDAH kirim: pemohon melewati tiga langkah - foto kendaraan, STNK, SIM
     * - lalu memesan slot inspeksi, untuk mendapat penolakan yang sudah bisa
     * dipastikan sejak layarnya dibuka. Formulir hanya bisa memblokir di depan
     * kalau angkanya sampai ke sana, dan angkanya wajib lahir dari service yang
     * sama dengan penjaganya - bukan dihitung ulang di controller.
     */
    public function test_stats_membawa_ketiga_batas_yang_bisa_menolak_pengajuan(): void
    {
        [$kode, $user] = $this->pemohon();
        $this->buatPermitPending($kode);
        config([
            'services.visitor.permit.max_pengajuan_pending' => 3,
            'services.visitor.permit.max_akses_inspeksi_harian' => 5,
        ]);

        $response = app(VehiclePermitController::class)->getStats($this->permintaanStats($user));
        $isi = json_decode($response->getContent(), true)['result'] ?? [];
        $kuota = app(VisitorPermitQuotaService::class);

        $this->assertSame($kuota->pendingTerpakai($kode), $isi['pending_terpakai'] ?? null);
        $this->assertSame(3, $isi['pending_maks'] ?? null);
        $this->assertSame($kuota->aksesInspeksiHariIni($kode), $isi['akses_terpakai'] ?? null);
        $this->assertSame(5, $isi['akses_maks'] ?? null);
    }

    public function test_kuota_di_stats_tidak_menghitung_permit_kedaluwarsa(): void
    {
        [$kode, $user] = $this->pemohon();
        $sebelum = $this->terpakai($kode);
        $this->buatPermitKedaluwarsa($kode);

        $response = app(VehiclePermitController::class)->getStats($this->permintaanStats($user));
        $isi = json_decode($response->getContent(), true)['result'] ?? [];

        $this->assertSame($sebelum, $isi['kuota_terpakai'] ?? null);
    }

    // ---------------------------------------------------------------- helpers

    private function permintaanStats(User $user): Request
    {
        $request = Request::create('/transaksipermit-stats', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function buatPermitKedaluwarsa(string $kode): void
    {
        $noTransaksi = 'PMT-QX-'.Str::upper(Str::random(10));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $kode,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'QX-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
            'Tanggal_Mulai_Berlaku' => now()->subMonths(2)->toDateString(),
            'Tanggal_Kadaluarsa' => now()->subDay()->toDateString(),
        ]);
    }

    private function permintaan(User $user, ?string $idempotencyKey = null): Request
    {
        $request = Request::create(
            '/transaksipermit',
            'POST',
            [
                'plat_prefix' => 'ZZ',
                'plat_angka' => '9999',
                'plat_suffix' => 'ZZZ',
                'id_vehicle_type' => 999999,
                'id_vehicle_model' => 999999,
                'id_vehicle_color' => 999999,
                'tahun_kendaraan' => 2020,
                'id_kategori_permit' => 999999,
            ] + $this->slotInspeksiUji(),
            [],
            [
                'foto_kendaraan' => UploadedFile::fake()->image('kendaraan.jpg'),
                'foto_stnk' => UploadedFile::fake()->image('stnk.jpg'),
                'foto_sim' => UploadedFile::fake()->image('sim.jpg'),
            ],
            ['HTTP_IDEMPOTENCY_KEY' => $idempotencyKey ?? 'QUOTA-'.Str::upper(Str::random(16))],
        );
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Set batas tepat di jumlah jatah yang sudah terpakai — jadi pengajuan
     * berikutnya pasti yang pertama melampaui.
     */
    private function kunciBatasTepatDiJatahTerpakai(string $kode): int
    {
        $batas = $this->terpakai($kode);
        $this->assertGreaterThan(0, $batas, 'Jatah terpakai harus > 0 agar batasnya mengikat.');
        config(['services.visitor.permit.max_pengajuan_aktif' => $batas]);

        return $batas;
    }

    private function terpakai(string $kode): int
    {
        return app(VisitorPermitQuotaService::class)->terpakai($kode);
    }

    private function jumlahPermit(string $kode): int
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Kode_Karyawan_Pemohon', $kode)
            ->count();
    }

    private function buatPermitPending(string $kode, ?string $idempotencyKey = null): string
    {
        $noTransaksi = 'PMT-QE-'.Str::upper(Str::random(10));

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $kode,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => $idempotencyKey ?? 'QE-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $noTransaksi,
        ]);

        return $noTransaksi;
    }

    /**
     * @return array{0:string,1:User}
     */
    private function pemohon(): array
    {
        $karyawan = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            // Belum resign: permit karyawan yang lewat hari terakhirnya terbaca EXPIRED.
            ->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'UserID_Web']);

        $this->assertNotNull($karyawan, 'Tidak ada karyawan ber-UserID_Web.');

        $user = User::find($karyawan->UserID_Web);
        $this->assertNotNull($user, 'User pemohon tidak ditemukan.');

        return [trim((string) $karyawan->Kode_Karyawan), $user];
    }
}
