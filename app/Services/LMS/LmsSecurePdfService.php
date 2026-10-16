<?php

namespace App\Services\LMS;

/**
 * LmsSecurePdfService
 *
 * Handles AES-256-CBC encryption/decryption for one-time PDF streaming tokens.
 * Used to create secure, time-limited signatures that prevent replay attacks
 * and direct URL access to PDF documents.
 */
class LmsSecurePdfService
{
    private static string $saltFront = 'LMS_SEC';

    private static string $saltBack = 'PDF_GUARD';

    private static string $aesKey = 'hcis-lms-pdf-secure-key-2026-evo';

    /**
     * Encrypt a plain token value into a secure signature.
     *
     * Format: saltFront.value.saltBack_random10_timestamp
     * Then AES-256-CBC encrypted and base64url encoded.
     */
    public static function generate(string $value): string
    {
        $random10 = substr(bin2hex(random_bytes(10)), 0, 10);
        $timestamp = time();

        $plain = sprintf('%s.%s.%s|%s|%d', self::$saltFront, $value, self::$saltBack, $random10, $timestamp);
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            hash('sha256', self::$aesKey, true),
            OPENSSL_RAW_DATA,
            $iv
        );

        return rtrim(strtr(base64_encode($iv.$encrypted), '+/', '-_'), '=');
    }

    /**
     * Decrypt a signature back to the original token value.
     * Validates salt integrity to detect tampering.
     *
     * @return array{success: bool, value?: string, random?: string, timestamp?: int, message?: string}
     */
    public static function decrypt(string $token): array
    {
        try {
            $raw = base64_decode(strtr($token, '-_', '+/'));
            if (! $raw || strlen($raw) <= 16) {
                throw new \Exception('Token invalid');
            }

            $iv = substr($raw, 0, 16);
            $encrypted = substr($raw, 16);

            $plain = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                hash('sha256', self::$aesKey, true),
                OPENSSL_RAW_DATA,
                $iv
            );

            if (! $plain) {
                throw new \Exception('Decrypt gagal');
            }

            [$saltFront, $value, $backPart] = explode('.', $plain, 3);
            [$saltBack, $random, $timestamp] = explode('|', $backPart, 3);

            if ($saltFront !== self::$saltFront || $saltBack !== self::$saltBack) {
                throw new \Exception('Salt tidak valid');
            }

            return [
                'success' => true,
                'value' => $value,
                'random' => $random,
                'timestamp' => (int) $timestamp,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
