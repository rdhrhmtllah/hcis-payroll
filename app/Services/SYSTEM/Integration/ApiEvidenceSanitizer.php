<?php

namespace App\Services\SYSTEM\Integration;

/**
 * Sanitizer payload untuk jejak bukti API pihak ketiga.
 *
 * Nilai rahasia diredaksi berdasarkan NAMA KUNCI secara rekursif, bukan regex
 * teks. Dengan begitu string, angka, boolean, array, dan object sama-sama aman;
 * nested JSON juga tidak lolos. Bila body bukan JSON, isinya tidak disalin ke
 * log—Payload_Hash tetap cukup untuk membuktikan dua pihak membahas byte yang
 * sama tanpa membuka muatannya.
 */
class ApiEvidenceSanitizer
{
    private const POLA_RAHASIA = '/(?:secret|password|token|signature|api_?key)/i';

    /**
     * Data pribadi yang tidak perlu ada di log bukti: nomor identitas/KTP/NIK dan
     * nomor telepon. Dicocokkan per SEGMEN kunci (dipisah '_') supaya kunci lain
     * yang kebetulan memuat hurufnya tidak ikut terhapus.
     */
    private const POLA_PII = '/(?:^|_)(?:identitas|ktp|nik|hp|handphone|telepon|telp|phone)(?:_|$)/i';

    public function ringkasBody(?string $body, int $batas): ?string
    {
        $body = trim((string) $body);
        if ($body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            return '[NON_JSON_REDACTED]';
        }

        $encoded = json_encode(
            $this->redaksiRekursif($decoded),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return $this->ringkas(is_string($encoded) ? $encoded : null, $batas);
    }

    public function ringkasTeks(?string $teks, int $batas): ?string
    {
        return $this->ringkas($teks, $batas);
    }

    private function redaksiRekursif(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            if (preg_match(self::POLA_RAHASIA, (string) $kunci)) {
                $data[$kunci] = '[REDACTED]';
            } elseif (is_string($kunci) && preg_match(self::POLA_PII, $kunci) && ! is_array($nilai)) {
                $data[$kunci] = '[PII_REDACTED]';
            } elseif (is_array($nilai)) {
                $data[$kunci] = $this->redaksiRekursif($nilai);
            }
        }

        return $data;
    }

    private function ringkas(?string $teks, int $batas): ?string
    {
        $teks = trim((string) $teks);
        if ($teks === '') {
            return null;
        }

        return mb_strlen($teks) > $batas ? mb_substr($teks, 0, $batas - 3).'...' : $teks;
    }
}
