<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MemesanSlotInspeksi;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

class VisitorSubmissionNotifiesAdminTest extends TestCase
{
    use DatabaseTransactions;
    use MemesanSlotInspeksi;
    use MenegaskanTautanDetail;
    use PermintaanRegistrasiTamu;

    public function test_pengajuan_tamu_yang_butuh_approval_memberi_notif_ke_admin(): void
    {
        $domain = VisitorNotificationService::DOMAIN_TAMU;
        config([
            'services.visitor.tamu.needs_approval' => true,
            'services.visitor.tamu.allow_submit_without_flow' => true,
        ]);

        $penerima = app(VisitorNotificationService::class)->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role verifikator aktif untuk domain itu.');

        $noTransaksi = $this->ajukanTamu();

        $notif = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();

        $this->assertCount(count($penerima), $notif);
        $this->assertEqualsCanonicalizing(
            $penerima,
            $notif->pluck('Kode_Karyawan')->map(fn ($k) => trim((string) $k))->all(),
        );
        $this->assertSame(VisitorNotificationService::MODULE, $notif->first()->Module);
        $this->assertStringContainsString('Tamu', (string) $notif->first()->Title);

        $idTamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Transaksi_Tamu');
        // Klik notifikasi harus membuka detail transaksinya, bukan sekadar
        // halaman verifikator.
        $this->assertTautanDetail(
            $notif->first()->Action_Url,
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU],
            VisitorNotificationService::DOMAIN_TAMU,
            (int) $idTamu,
            '&jenis=tamu',
        );
    }

    public function test_pengajuan_tamu_b2b_memberi_notif_ke_pool_b2b_bukan_pool_regular(): void
    {
        // DDL-2026-121: pengajuan dari halaman B2B memakai modul approval
        // tamu_b2b dan memberitahu ADMIN_TAMU_B2B, dengan tautan ke meja B2B.
        config([
            'services.visitor.tamu_b2b.needs_approval' => true,
            'services.visitor.tamu_b2b.allow_submit_without_flow' => true,
        ]);

        $service = app(VisitorNotificationService::class);
        $penerima = $service->penerimaAdmin(VisitorNotificationService::DOMAIN_TAMU_B2B);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role ADMIN_TAMU_B2B aktif; jalankan DML DDL-2026-121 lebih dulu.');

        $noTransaksi = $this->ajukanTamu(b2b: true);

        $notif = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();

        $this->assertEqualsCanonicalizing(
            $penerima,
            $notif->pluck('Kode_Karyawan')->map(fn ($k) => trim((string) $k))->all(),
        );
        $this->assertSame('Pengajuan Tamu B2B Baru', (string) $notif->first()->Title);

        $idTamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Transaksi_Tamu');
        // Token detail tetap bertanda domain TAMU; halamannya meja B2B.
        $this->assertTautanDetail(
            $notif->first()->Action_Url,
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU_B2B],
            VisitorNotificationService::DOMAIN_TAMU,
            (int) $idTamu,
            '&jenis=tamu',
        );
    }

    public function test_pengajuan_tamu_tanpa_approval_tidak_memberi_notif(): void
    {
        config([
            'services.visitor.tamu.needs_approval' => false,
            'services.visitor.tamu.allow_submit_without_flow' => true,
        ]);

        $noTransaksi = $this->ajukanTamu();

        $this->assertSame(
            0,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Kunjungan yang langsung terjadwal tidak menyisakan apa pun untuk diverifikasi.',
        );
    }

    public function test_pengajuan_permit_yang_butuh_approval_memberi_notif_ke_admin(): void
    {
        $domain = VisitorNotificationService::DOMAIN_PERMIT;
        config([
            'services.visitor.permit.needs_approval' => true,
            'services.visitor.permit.allow_submit_without_flow' => true,
        ]);

        $penerima = app(VisitorNotificationService::class)->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role verifikator aktif untuk domain itu.');

        $noTransaksi = $this->ajukanPermit();

        $notif = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();

        $this->assertCount(count($penerima), $notif);
        $this->assertSame(VisitorNotificationService::MODULE, $notif->first()->Module);
        $this->assertStringContainsString('Permit', (string) $notif->first()->Title);
        $this->assertStringContainsString('B 1234 XYZ', (string) $notif->first()->Message);

        $idPermit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Id_Transaksi_Permit');
        // Klik notifikasi harus membuka detail transaksinya, bukan sekadar
        // halaman verifikator.
        $this->assertTautanDetail(
            $notif->first()->Action_Url,
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_PERMIT],
            VisitorNotificationService::DOMAIN_PERMIT,
            (int) $idPermit,
            '&jenis=permit',
        );
    }

    public function test_permit_yang_disetujui_otomatis_tidak_memberi_notif(): void
    {
        config([
            'services.visitor.permit.needs_approval' => false,
            'services.visitor.permit.allow_submit_without_flow' => true,
        ]);

        $noTransaksi = $this->ajukanPermit();

        $this->assertSame(
            0,
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Permit yang auto-approve tidak menyisakan apa pun untuk diverifikasi.',
        );
    }

    private function ajukanPermit(): string
    {
        Storage::fake('gcs');

        $master = $this->masterPermit();
        $user = $this->pemohon();

        $request = Request::create('/transaksipermit', 'POST', [
            'plat_prefix' => $master['plat'],
            'plat_angka' => '1234',
            'plat_suffix' => 'XYZ',
            'id_vehicle_type' => $master['id_tipe'],
            'id_vehicle_model' => $master['id_model'],
            'id_vehicle_color' => $master['id_warna'],
            'tahun_kendaraan' => 2020,
            'id_kategori_permit' => $master['id_kategori'],
            'keterangan_pengajuan' => 'Uji notifikasi',
        ] + $this->slotInspeksiUji(), [], [
            'foto_kendaraan' => UploadedFile::fake()->image('kendaraan.jpg'),
            'foto_stnk' => UploadedFile::fake()->image('stnk.jpg'),
            'foto_sim' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $request->headers->set('Idempotency-Key', 'TEST-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => $user);

        $response = app(VehiclePermitController::class)->store($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(
            201,
            $response->getStatusCode(),
            'Pengajuan permit gagal: '.($payload['message'] ?? $response->getContent()),
        );

        return $payload['result']['no_transaksi'];
    }

    /**
     * @return array<string,mixed>
     */
    private function masterPermit(): array
    {
        $plat = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->first(['Kode_Plat']);
        $model = DB::table('N_EMI_VISITOR_Master_Vehicle_Model as m')
            ->join('N_EMI_VISITOR_Master_Vehicle_Type as t', 't.Id_Vehicle_Type', '=', 'm.Id_Master_Tipe')
            ->where('m.Flag_Aktif', 'Y')
            ->where('t.Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('t.Akses')->orWhereIn('t.Akses', ['PERMIT', 'ALL']);
            })
            ->first(['m.Id_Vehicle_Model', 'm.Id_Master_Tipe']);
        $warna = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')->where('Flag_Aktif', 'Y')->first(['Id_Master_Color']);
        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->where('Flag_Aktif', 'Y')->first(['Id_Kategori_Permit']);

        $this->assertNotNull($plat, 'Master plat aktif tidak tersedia.');
        $this->assertNotNull($model, 'Master model/tipe kendaraan aktif untuk permit tidak tersedia.');
        $this->assertNotNull($warna, 'Master warna kendaraan aktif tidak tersedia.');
        $this->assertNotNull($kategori, 'Master kategori permit aktif tidak tersedia.');

        return [
            'plat' => $plat->Kode_Plat,
            'id_model' => (int) $model->Id_Vehicle_Model,
            'id_tipe' => (int) $model->Id_Master_Tipe,
            'id_warna' => (int) $warna->Id_Master_Color,
            'id_kategori' => (int) $kategori->Id_Kategori_Permit,
        ];
    }

    private function ajukanTamu(bool $b2b = false): string
    {
        $user = $this->pemohon();

        $payload = [
            'nama_tamu' => 'Budi Santoso',
            'instansi_asal' => 'PT Contoh',
            'keperluan' => 'Uji Notifikasi '.Str::upper(Str::random(6)),
            'pic_tujuan' => 'Bagian SHE',
            // Estimasi Masuk/Keluar wajib (DDL-2026-121); Tanggal_Kunjungan
            // diturunkan dari Estimasi Masuk.
            'waktu_mulai_kunjungan' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => now()->addDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
            'jumlah_tamu' => 1,
            'daftar_anggota' => [],
        ];

        if ($b2b) {
            // B2B wajib berkendaraan + plat (AturanFormTamu).
            $payload += $this->kendaraanTamuB2B();
        }

        $request = $this->permintaanRegistrasiTamu(
            $payload,
            $user,
            'TEST-'.Str::upper(Str::random(20)),
            $b2b ? TipeTamu::B2B : null,
        );

        $response = app(GuestRegistrationController::class)->store($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertSame(
            201,
            $response->getStatusCode(),
            'Pengajuan tamu gagal: '.($payload['message'] ?? $response->getContent()),
        );

        return $payload['result']['no_transaksi'];
    }

    /**
     * @return array{jenis_kendaraan:string, plat_prefix:string, plat_angka:string, plat_suffix:string}
     */
    private function kendaraanTamuB2B(): array
    {
        $jenis = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']);
            })
            ->orderBy('Nama_Tipe_Kendaraan')
            ->pluck('Nama_Tipe_Kendaraan')
            ->first(fn ($nama) => ! AturanFormTamu::tanpaKendaraan($nama));
        $plat = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->value('Kode_Plat');

        $this->assertNotNull($jenis, 'Master tipe kendaraan aktif untuk tamu tidak tersedia.');
        $this->assertNotNull($plat, 'Master plat aktif tidak tersedia.');

        return [
            'jenis_kendaraan' => (string) $jenis,
            'plat_prefix' => (string) $plat,
            'plat_angka' => (string) random_int(1000, 9999),
            'plat_suffix' => 'NT',
        ];
    }

    private function pemohon(): User
    {
        $karyawan = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->first(['UserID_Web']);

        $this->assertNotNull($karyawan, 'Tidak ada karyawan ber-UserID_Web untuk dijadikan pemohon.');

        $user = User::find($karyawan->UserID_Web);
        $this->assertNotNull($user, 'User pemohon tidak ditemukan.');

        return $user;
    }
}
