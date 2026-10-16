<?php

namespace Tests\Integration\VISITOR\Workflow\Notification;

use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;

/**
 * Tautan notifikasi Visitor tak lagi memuat Id berurutan.
 *
 * Menyamakan `Action_Url` dengan string harfiah jadi mustahil: tokennya berbeda
 * tiap kali dibuat. Yang diuji karena itu dua hal sekaligus — Id-nya benar-benar
 * hilang dari alamat, dan tokennya tetap menunjuk transaksi yang dimaksud.
 */
trait MenegaskanTautanDetail
{
    private function assertTautanDetail(
        ?string $actionUrl,
        string $halaman,
        string $domain,
        int $idHarapan,
        string $ekor = '',
    ): void {
        $this->assertNotNull($actionUrl, 'Notifikasi tidak membawa Action_Url.');
        $this->assertStringStartsWith($halaman.'?detail=', $actionUrl);

        if ($ekor !== '') {
            $this->assertStringEndsWith($ekor, $actionUrl);
        }

        $this->assertStringNotContainsString(
            'detail='.$idHarapan,
            $actionUrl,
            'Id berurutan masih terbaca di alamat.',
        );

        $token = substr($actionUrl, strlen($halaman.'?detail='));
        if ($ekor !== '') {
            $token = substr($token, 0, -strlen($ekor));
        }

        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]+$/',
            $token,
            "Token tidak aman dipakai di path URL: {$token}",
        );
        $this->assertSame(
            $idHarapan,
            VisitorDetailToken::bacaId($domain, $token),
            'Token tidak menunjuk transaksi yang dimaksud.',
        );
    }
}
