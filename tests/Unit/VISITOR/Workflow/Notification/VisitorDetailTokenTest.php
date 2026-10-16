<?php

namespace Tests\Unit\VISITOR\Workflow\Notification;

use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Tests\TestCase;

class VisitorDetailTokenTest extends TestCase
{
    public function test_token_pulang_pergi_mengembalikan_id_semula(): void
    {
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, 49);

        $this->assertNotSame('49', $token);
        $this->assertStringNotContainsString('49', $token);
        $this->assertSame(49, VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $token));
    }

    public function test_token_selalu_aman_dipakai_di_path_url(): void
    {
        // Ciphertext Crypt adalah base64 biasa: '+', '/', dan '=' MUNCUL pada
        // sebagian besar panjang payload. Satu '/' saja memecah path URL dan
        // permintaannya nyasar. Panjang payload sengaja divariasikan.
        foreach ([1, 7, 42, 999, 123456, 9876543210] as $id) {
            for ($i = 0; $i < 40; $i++) {
                $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, $id + $i);

                $this->assertMatchesRegularExpression(
                    '/^[A-Za-z0-9_-]+$/',
                    $token,
                    "Token tidak aman untuk path URL: {$token}",
                );
                $this->assertSame(
                    $id + $i,
                    VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_PERMIT, $token),
                );
            }
        }
    }

    public function test_token_permit_tidak_bisa_dipakai_sebagai_token_tamu(): void
    {
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, 412);

        $this->assertSame(412, VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_PERMIT, $token));
        $this->assertNull(
            VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $token),
            'Domain ikut dikunci di dalam token; tanpa itu token permit membuka baris tamu ber-Id sama.',
        );
    }

    public function test_token_yang_dirusak_atau_sampah_ditolak(): void
    {
        $token = VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, 49);
        $rusak = substr($token, 0, -4).'AAAA';

        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $rusak));
        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, 'abc'));
        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, ''));
        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, '../../etc/passwd'));
    }

    public function test_angka_polos_tetap_diterima_demi_notifikasi_lama_dan_klik_tabel(): void
    {
        // Notifikasi yang sudah tersimpan memakai ?detail=49, dan klik dari tabel
        // mengirim Id numerik dari data yang sudah ada di layar.
        $this->assertSame(49, VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, '49'));
        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, '0'));
        $this->assertNull(VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, '-5'));
    }
}
