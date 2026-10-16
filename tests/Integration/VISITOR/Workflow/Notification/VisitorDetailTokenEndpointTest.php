<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorDetailTokenEndpointTest extends TestCase
{
    use DatabaseTransactions;

    public function test_show_permit_pemohon_menerima_token(): void
    {
        [$id, $user] = $this->buatPermit();
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, $id);

        $response = app(VehiclePermitController::class)->show($this->requestSebagai($user), $token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            $id,
            (int) json_decode($response->getContent(), true)['result']['permit']['Id_Transaksi_Permit'],
        );
    }

    public function test_show_tamu_pemohon_menerima_token(): void
    {
        [$id, $user] = $this->buatTamu();
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, $id);

        $response = app(GuestRegistrationController::class)->show($this->requestSebagai($user), $token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            $id,
            (int) json_decode($response->getContent(), true)['result']['Id_Transaksi_Tamu'],
        );
    }

    public function test_show_verifikator_menerima_token_permit_dan_tamu(): void
    {
        [$idPermit] = $this->buatPermit();
        [$idTamu] = $this->buatTamu();
        $controller = app(VerifikatorVisitorController::class);

        $permit = $controller->showPermit(
            VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, $idPermit),
        );
        // showTamu() kini menerima Request: tipe rute menyaring barisnya
        // (DDL-2026-121). Tanpa atribut tipe = meja Regular.
        $tamu = $controller->showTamu(
            Request::create('/verifikator-tamu/x', 'GET'),
            VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, $idTamu),
        );

        $this->assertSame(200, $permit->getStatusCode());
        $this->assertSame(200, $tamu->getStatusCode());
    }

    public function test_token_tamu_b2b_terbuka_di_meja_b2b_dan_404_di_meja_regular(): void
    {
        // Notifikasi TAMU_B2B membawa token ber-domain TAMU; yang memisahkan
        // kedua meja adalah penyaring tipe rute, bukan tokennya.
        [$idTamu] = $this->buatTamu(TipeTamu::B2B);
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, $idTamu);
        $controller = app(VerifikatorVisitorController::class);

        $b2b = $controller->showTamu($this->requestBertipe('/verifikator-b2b/x', TipeTamu::B2B), $token);
        $regular = $controller->showTamu(Request::create('/verifikator-tamu/x', 'GET'), $token);

        $this->assertSame(200, $b2b->getStatusCode());
        $this->assertSame($idTamu, (int) json_decode($b2b->getContent(), true)['result']['Id_Transaksi_Tamu']);
        $this->assertSame(404, $regular->getStatusCode(), 'Meja Regular membuka detail kunjungan B2B.');
    }

    public function test_pemohon_b2b_membuka_token_di_halaman_b2b_saja(): void
    {
        [$id, $user] = $this->buatTamu(TipeTamu::B2B);
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, $id);

        $b2b = $this->requestSebagai($user);
        $b2b->attributes->set(TipeTamu::ATRIBUT, TipeTamu::B2B);

        $halamanB2b = app(GuestRegistrationController::class)->show($b2b, $token);
        $halamanRegular = app(GuestRegistrationController::class)->show($this->requestSebagai($user), $token);

        $this->assertSame(200, $halamanB2b->getStatusCode());
        $this->assertSame(404, $halamanRegular->getStatusCode());
    }

    public function test_token_sampah_dibalas_404_bukan_500(): void
    {
        [, $user] = $this->buatPermit();

        // Nilai non-numerik yang dialirkan ke kolom int SQL Server melempar
        // QueryException -> 500. Setelah token dikenali, ia harus berhenti di
        // pintu dengan 404.
        foreach (['abc', 'eyJpdiI6InJ1c2FrIn0', '../../etc/passwd'] as $sampah) {
            $permit = app(VehiclePermitController::class)->show($this->requestSebagai($user), $sampah);
            $tamu = app(GuestRegistrationController::class)->show($this->requestSebagai($user), $sampah);

            $this->assertSame(404, $permit->getStatusCode(), "Permit tidak 404 untuk: {$sampah}");
            $this->assertSame(404, $tamu->getStatusCode(), "Tamu tidak 404 untuk: {$sampah}");
        }
    }

    public function test_token_permit_tidak_membuka_detail_tamu(): void
    {
        [$idPermit] = $this->buatPermit();
        [, $user] = $this->buatTamu();

        $tokenPermit = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, $idPermit);

        $response = app(GuestRegistrationController::class)->show($this->requestSebagai($user), $tokenPermit);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_id_polos_tetap_bekerja_untuk_notifikasi_lama(): void
    {
        [$id, $user] = $this->buatPermit();

        $response = app(VehiclePermitController::class)->show($this->requestSebagai($user), (string) $id);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ---------------------------------------------------------------- helpers

    public function test_akun_tanpa_karyawan_tidak_bisa_membaca_detail_permit_siapa_pun(): void
    {
        // Dulu penyaring pemilik dilewati bila akun tak tertaut ke Karyawan,
        // sehingga detail permit mana pun terbaca lewat Id biasa (audit M1).
        [$id] = $this->buatPermit();
        $tanpaKaryawan = User::query()
            ->whereNotIn('Id_Users', fn ($q) => $q->select('UserID_Web')->from('Karyawan')->whereNotNull('UserID_Web'))
            ->first();
        $this->assertNotNull($tanpaKaryawan, 'Tidak ada akun tanpa Karyawan di dev untuk diuji.');

        $response = app(VehiclePermitController::class)->show($this->requestSebagai($tanpaKaryawan), (string) $id);

        $this->assertSame(403, $response->getStatusCode());
    }

    private function requestBertipe(string $url, string $tipe): Request
    {
        $request = Request::create($url, 'GET');
        $request->attributes->set(TipeTamu::ATRIBUT, $tipe);

        return $request;
    }

    private function requestSebagai(User $user): Request
    {
        $request = Request::create('/transaksipermit', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @return array{0:int,1:User}
     */
    private function buatPermit(): array
    {
        [$kode, $user] = $this->pemohon();

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => 'PMT-TEST-'.Str::upper(Str::random(8)),
            'Kode_Karyawan_Pemohon' => $kode,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        return [(int) $id, $user];
    }

    /**
     * @return array{0:int,1:User}
     */
    private function buatTamu(string $tipe = TipeTamu::REGULAR): array
    {
        [$kode, $user] = $this->pemohon();
        $noTransaksi = 'VSR-TEST-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_PIC' => $kode,
            'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL,
            'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $kode,
            'Idempotency_Key' => 'TEST-'.Str::upper(Str::random(20)),
        ]);

        // Tipe dibaca dari detail; setiap endpoint tamu menyaringnya.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $noTransaksi,
            'Nama_Lengkap_Tamu' => 'Tamu Token',
            'Tipe_Tamu' => $tipe,
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
        ]);

        return [(int) $id, $user];
    }

    /**
     * @return array{0:string,1:User}
     */
    private function pemohon(): array
    {
        $karyawan = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'UserID_Web']);

        $this->assertNotNull($karyawan, 'Tidak ada karyawan ber-UserID_Web.');

        $user = User::find($karyawan->UserID_Web);
        $this->assertNotNull($user, 'User pemohon tidak ditemukan.');

        return [trim((string) $karyawan->Kode_Karyawan), $user];
    }
}
