<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Tests\TestCase;

/** Pemohon diberi tahu saat petugas memindah jadwal inspeksinya. */
class NotifikasiJadwalDipindahTest extends TestCase
{
    public function test_payload_menyebut_jadwal_baru_dan_alasannya(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'A1',
            1,
            VisitorNotificationService::DOMAIN_PERMIT,
            'PMT0926-0001',
            VisitorNotificationService::KEPUTUSAN_JADWAL_DIPINDAH,
            ['jadwal_baru' => 'Selasa, 22 Sep 2026 08:00 di Klinik SHE', 'alasan' => 'Pos penuh'],
        );

        $this->assertSame('Permit Kendaraan — Jadwal Inspeksi Dipindah', $payload['title']);
        $this->assertStringContainsString('Selasa, 22 Sep 2026 08:00 di Klinik SHE', $payload['message']);
        $this->assertStringContainsString('Alasan: Pos penuh', $payload['message']);
        $this->assertSame('info', $payload['severity']);
        $this->assertStringContainsString(':JADWAL_DIPINDAH:', $payload['notification_key']);
    }

    public function test_setiap_pemindahan_adalah_notifikasi_baru(): void
    {
        // Kunci yang sama membuat pemindahan kedua tak terlihat bila notifikasi
        // pertama sudah dihapus pemohon (upsertByKey tidak menghidupkannya).
        $svc = app(VisitorNotificationService::class);
        $kunci = fn (int $idBooking) => $svc->bangunPayloadKeputusan(
            'A1', 1, VisitorNotificationService::DOMAIN_PERMIT, 'PMT0926-0001',
            VisitorNotificationService::KEPUTUSAN_JADWAL_DIPINDAH, ['id_booking' => $idBooking],
        )['notification_key'];

        $this->assertNotSame($kunci(10), $kunci(11));
    }

    public function test_payload_tamu_tidak_hadir_tidak_berbunyi_ditolak(): void
    {
        $payload = app(VisitorNotificationService::class)->bangunPayloadKeputusan(
            'A1', 1, VisitorNotificationService::DOMAIN_TAMU_B2B, 'VSR0926-0001',
            VisitorNotificationService::KEPUTUSAN_TAMU_TIDAK_HADIR,
        );

        $this->assertSame('Kunjungan Tamu B2B — Tamu Tidak Hadir', $payload['title']);
        $this->assertStringContainsString('dicatat tidak hadir', $payload['message']);
        $this->assertStringNotContainsString('ditolak', $payload['message']);
        $this->assertSame('warning', $payload['severity']);
    }
}
