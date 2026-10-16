<?php

namespace Tests\Unit\VISITOR\Workflow\Notification;

use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Tests\TestCase;

class VisitorNotificationServiceTest extends TestCase
{
    public function test_payload_pengajuan_tamu_membawa_kunci_modul_dan_tautan_verifikator(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayload(
            'C9',
            77,
            VisitorNotificationService::DOMAIN_TAMU,
            'VSR-2608-001',
            [
                'nama_pemohon' => 'Ridho',
                'nama_tamu' => 'Budi Santoso',
                'instansi' => 'PT Contoh',
                'tanggal_kunjungan' => '2026-08-25',
            ],
        );

        $this->assertSame('C9', $payload['kode_karyawan']);
        $this->assertSame(
            'visitor_pengajuan_baru:TAMU:VSR-2608-001:C9',
            $payload['notification_key'],
        );
        $this->assertSame(VisitorNotificationService::MODULE, $payload['module']);
        $this->assertSame(NotificationService::DELIVERY_IN_APP, $payload['channel']);
        $this->assertTautanDetail(
            $payload['action_url'],
            // Halaman diambil dari peta milik LAYANAN, bukan ditulis ulang di
            // sini: meja verifikator sudah dipecah per domain, dan alamat yang
            // disalin ke uji akan menyimpang diam-diam pada pemecahan berikutnya.
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU],
            VisitorNotificationService::DOMAIN_TAMU, 77, '&jenis=tamu');
        $this->assertSame('VSR-2608-001', $payload['reference_id']);
        $this->assertSame(VisitorNotificationService::TYPE_PENGAJUAN_BARU, $payload['type']);

        $this->assertStringContainsString('Tamu', $payload['title']);
        $this->assertStringContainsString('Ridho', $payload['message']);
        $this->assertStringContainsString('Budi Santoso', $payload['message']);
        $this->assertStringContainsString('PT Contoh', $payload['message']);
        $this->assertSame('TAMU', $payload['meta']['domain']);
    }

    public function test_payload_pengajuan_permit_menyebut_kendaraan_bukan_tamu(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayload(
            'C9',
            412,
            VisitorNotificationService::DOMAIN_PERMIT,
            'PMT-2608-007',
            [
                'nama_pemohon' => 'Ridho',
                'kategori' => 'Permit Bulanan',
                'plat_nomor' => 'B 1234 XYZ',
            ],
        );

        $this->assertSame(
            'visitor_pengajuan_baru:PERMIT:PMT-2608-007:C9',
            $payload['notification_key'],
        );
        $this->assertSame('PERMIT', $payload['meta']['domain']);
        $this->assertTautanDetail(
            $payload['action_url'],
            // Halaman diambil dari peta milik LAYANAN, bukan ditulis ulang di
            // sini: meja verifikator sudah dipecah per domain, dan alamat yang
            // disalin ke uji akan menyimpang diam-diam pada pemecahan berikutnya.
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_PERMIT],
            VisitorNotificationService::DOMAIN_PERMIT, 412, '&jenis=permit');

        $this->assertStringContainsString('Permit', $payload['title']);
        $this->assertStringNotContainsString('Tamu', $payload['title']);

        $this->assertStringContainsString('Ridho', $payload['message']);
        $this->assertStringContainsString('B 1234 XYZ', $payload['message']);
        $this->assertStringContainsString('Permit Bulanan', $payload['message']);
        $this->assertStringNotContainsString('kunjungan tamu', $payload['message']);
    }

    public function test_payload_permit_disetujui_menautkan_detail_transaksi_pemohon(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'Y62',
            412,
            VisitorNotificationService::DOMAIN_PERMIT,
            'PMT0826-0006',
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
            ['plat_nomor' => 'BA 123 DAS'],
        );

        $this->assertSame('Y62', $payload['kode_karyawan']);
        $this->assertTautanDetail($payload['action_url'], '/transaksipermit', VisitorNotificationService::DOMAIN_PERMIT, 412);
        $this->assertSame('success', $payload['severity']);
        $this->assertSame(
            'visitor_keputusan:PERMIT:PMT0826-0006:DISETUJUI:Y62',
            $payload['notification_key'],
        );
        $this->assertSame(VisitorNotificationService::TYPE_KEPUTUSAN, $payload['type']);
        $this->assertSame('MAIN_NOTIF', $payload['meta']['source']);
        $this->assertStringContainsString('Disetujui', $payload['title']);
        $this->assertStringContainsString('BA 123 DAS', $payload['message']);
    }

    public function test_payload_tamu_ditolak_membawa_alasan_dan_nada_bahaya(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'K32',
            77,
            VisitorNotificationService::DOMAIN_TAMU,
            'VSR0826-0003',
            VisitorNotificationService::KEPUTUSAN_DITOLAK,
            ['alasan' => 'Dokumen identitas tidak lengkap'],
        );

        $this->assertTautanDetail($payload['action_url'], '/transaksitamu', VisitorNotificationService::DOMAIN_TAMU, 77);
        $this->assertSame('danger', $payload['severity']);
        $this->assertStringContainsString('Ditolak', $payload['title']);
        $this->assertStringContainsString('Dokumen identitas tidak lengkap', $payload['message']);
    }

    public function test_payload_pengajuan_tamu_b2b_menautkan_meja_b2b_dengan_token_tamu(): void
    {
        // DDL-2026-121: domain TAMU_B2B punya meja sendiri, tetapi barisnya di
        // tabel tamu yang sama — token detailnya bertanda domain TAMU.
        $payload = app(VisitorNotificationService::class)->bangunPayload(
            'C9',
            77,
            VisitorNotificationService::DOMAIN_TAMU_B2B,
            'VSR-2609-001',
            ['nama_pemohon' => 'Ridho', 'nama_tamu' => 'Budi Santoso'],
        );

        $this->assertSame('/verifikator-visitor/b2b', VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU_B2B]);
        $this->assertTautanDetail(
            $payload['action_url'],
            VisitorNotificationService::ACTION_URL_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU_B2B],
            VisitorNotificationService::DOMAIN_TAMU, 77, '&jenis=tamu');
        $this->assertSame('Pengajuan Tamu B2B Baru', $payload['title']);
        $this->assertSame(
            'visitor_pengajuan_baru:TAMU_B2B:VSR-2609-001:C9',
            $payload['notification_key'],
        );
        $this->assertSame('TAMU_B2B', $payload['meta']['domain']);
    }

    public function test_payload_keputusan_tamu_b2b_menautkan_halaman_pengajuan_b2b(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'K32',
            77,
            VisitorNotificationService::DOMAIN_TAMU_B2B,
            'VSR0926-0003',
            VisitorNotificationService::KEPUTUSAN_DISETUJUI,
        );

        $this->assertTautanDetail($payload['action_url'], '/transaksi-tamu-b2b', VisitorNotificationService::DOMAIN_TAMU, 77);
        $this->assertStringContainsString('Tamu B2B', $payload['title']);
        $this->assertSame('success', $payload['severity']);
    }

    public function test_role_pool_dan_token_per_domain(): void
    {
        $this->assertSame('ADMIN_TAMU_B2B', VisitorNotificationService::ROLE_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU_B2B]);
        $this->assertSame('ADMIN_TAMU', VisitorNotificationService::ROLE_PER_DOMAIN[VisitorNotificationService::DOMAIN_TAMU]);
        $this->assertSame(VisitorNotificationService::DOMAIN_TAMU, VisitorNotificationService::domainToken(VisitorNotificationService::DOMAIN_TAMU_B2B));
        $this->assertSame(VisitorNotificationService::DOMAIN_PERMIT, VisitorNotificationService::domainToken(VisitorNotificationService::DOMAIN_PERMIT));
    }

    public function test_payload_dibatalkan_bernada_peringatan(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'Y62',
            412,
            VisitorNotificationService::DOMAIN_PERMIT,
            'PMT0826-0006',
            VisitorNotificationService::KEPUTUSAN_DIBATALKAN,
            ['alasan' => 'Kendaraan sudah tidak dipakai'],
        );

        $this->assertSame('warning', $payload['severity']);
        $this->assertStringContainsString('Dibatalkan', $payload['title']);
        $this->assertStringContainsString('Kendaraan sudah tidak dipakai', $payload['message']);
    }

    /**
     * Tautan harus membawa token, bukan Id berurutan — tapi token itu wajib
     * benar-benar menunjuk transaksi yang dimaksud.
     */
    private function assertTautanDetail(
        string $actionUrl,
        string $halaman,
        string $domain,
        int $idHarapan,
        string $ekor = '',
    ): void {
        $this->assertStringStartsWith($halaman.'?detail=', $actionUrl);
        if ($ekor !== '') {
            $this->assertStringEndsWith($ekor, $actionUrl);
        }
        $this->assertStringNotContainsString('detail='.$idHarapan, $actionUrl);

        $token = substr($actionUrl, strlen($halaman.'?detail='));
        if ($ekor !== '') {
            $token = substr($token, 0, -strlen($ekor));
        }

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        $this->assertSame($idHarapan, VisitorDetailToken::bacaId($domain, $token));
    }
}
