<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorNotificationPublishTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pengajuan_tamu_menerbitkan_satu_notifikasi_per_admin(): void
    {
        $domain = VisitorNotificationService::DOMAIN_TAMU;
        $service = app(VisitorNotificationService::class);
        $penerima = $service->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role verifikator aktif untuk domain itu.');

        $noTransaksi = 'VSR-TEST-'.Str::upper(Str::random(8));

        $terkirim = $service->notifikasiPengajuanBaru(
            VisitorNotificationService::DOMAIN_TAMU,
            $noTransaksi,
            ['nama_pemohon' => 'Ridho', 'nama_tamu' => 'Budi Santoso', 'instansi' => 'PT Contoh'],
        );

        $this->assertSame(count($penerima), $terkirim);

        $rows = DB::table('N_HRIS_Notification')
            ->where('Reference_Id', $noTransaksi)
            ->get();

        $this->assertCount(count($penerima), $rows);
        $this->assertEqualsCanonicalizing(
            $penerima,
            $rows->pluck('Kode_Karyawan')->map(fn ($k) => trim((string) $k))->all(),
        );

        $row = $rows->first();
        $this->assertSame(VisitorNotificationService::MODULE, $row->Module);
        $this->assertSame(NotificationService::DELIVERY_IN_APP, $row->Channel);
        // No_Transaksi sintetis ini tidak punya baris transaksi, jadi tautannya
        // mundur ke halaman verifikator polos — bukan `?detail=0` yang pasti gagal.
        $this->assertSame(VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU], $row->Action_Url);
        $this->assertSame(VisitorNotificationService::TYPE_PENGAJUAN_BARU, $row->Type);
        // Konvensi tabel notifikasi: belum dibaca = 'T' (lihat NotificationService::activeUnreadQuery).
        $this->assertSame('T', $row->Is_Read);
        $this->assertStringContainsString('Budi Santoso', (string) $row->Message);
    }

    public function test_pengajuan_tamu_b2b_menerbitkan_notifikasi_ke_pool_b2b(): void
    {
        // DDL-2026-121: tamu B2B punya pool verifikator sendiri (ADMIN_TAMU_B2B)
        // dan halaman verifikator sendiri.
        $domain = VisitorNotificationService::DOMAIN_TAMU_B2B;
        $service = app(VisitorNotificationService::class);
        $penerima = $service->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role ADMIN_TAMU_B2B aktif; jalankan DML DDL-2026-121 lebih dulu.');

        $noTransaksi = 'VSR-TEST-'.Str::upper(Str::random(8));

        $terkirim = $service->notifikasiPengajuanBaru(
            $domain,
            $noTransaksi,
            ['nama_pemohon' => 'Ridho', 'nama_tamu' => 'Budi Santoso', 'instansi' => 'PT Contoh'],
        );

        $this->assertSame(count($penerima), $terkirim);

        $rows = DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->get();

        $this->assertEqualsCanonicalizing(
            $penerima,
            $rows->pluck('Kode_Karyawan')->map(fn ($k) => trim((string) $k))->all(),
        );
        $this->assertSame('/verifikator-visitor/b2b', $rows->first()->Action_Url);
        $this->assertSame('Pengajuan Tamu B2B Baru', $rows->first()->Title);
    }

    public function test_penerbitan_ulang_transaksi_yang_sama_tidak_menambah_baris(): void
    {
        $domain = VisitorNotificationService::DOMAIN_PERMIT;
        $service = app(VisitorNotificationService::class);
        $penerima = $service->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role verifikator aktif untuk domain itu.');

        $noTransaksi = 'PMT-TEST-'.Str::upper(Str::random(8));
        $ctx = ['nama_pemohon' => 'Ridho', 'kategori' => 'Permit Bulanan', 'plat_nomor' => 'B 1234 XYZ'];

        $service->notifikasiPengajuanBaru(VisitorNotificationService::DOMAIN_PERMIT, $noTransaksi, $ctx);
        $service->notifikasiPengajuanBaru(VisitorNotificationService::DOMAIN_PERMIT, $noTransaksi, $ctx);

        $this->assertSame(
            count($penerima),
            DB::table('N_HRIS_Notification')->where('Reference_Id', $noTransaksi)->count(),
            'Submit ulang / idempotent replay tidak boleh melahirkan notifikasi kedua.',
        );
    }

    public function test_notifikasi_terlihat_lewat_jalur_baca_lonceng_topbar(): void
    {
        $domain = VisitorNotificationService::DOMAIN_TAMU;
        $service = app(VisitorNotificationService::class);
        $penerima = $service->penerimaAdmin($domain);
        $this->assertNotEmpty($penerima, 'Tidak ada pemegang role verifikator aktif untuk domain itu.');

        $noTransaksi = 'VSR-TEST-'.Str::upper(Str::random(8));
        $service->notifikasiPengajuanBaru(
            VisitorNotificationService::DOMAIN_TAMU,
            $noTransaksi,
            ['nama_pemohon' => 'Ridho', 'nama_tamu' => 'Budi Santoso'],
        );

        // AppTopbar.vue dan AppNotificationModal.vue keduanya MENYARING source='MAIN_NOTIF'.
        // Baris yang ada di tabel tapi bersumber lain tidak pernah sampai ke pengguna.
        $terlihat = collect(app(NotificationService::class)->listUnread($penerima[0], [
            'limit' => 20,
            'source' => 'MAIN_NOTIF',
        ]))->pluck('reference_id')->all();

        $this->assertContains(
            $noTransaksi,
            $terlihat,
            'Notifikasi tidak lolos saringan source=MAIN_NOTIF, jadi tak akan muncul di lonceng maupun Pusat Notifikasi.',
        );
    }

    public function test_domain_tak_dikenal_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(VisitorNotificationService::class)->notifikasiPengajuanBaru(
            'KUNJUNGAN_HANTU',
            'VSR-TEST-DOMAIN',
            [],
        );
    }
}
