<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

class PayloadSanitizer
{
    public function __construct(protected array $piiKeys = []) {}

    public function sanitize(array $data): array
    {
        $redact = array_map('strtolower', $this->piiKeys);

        return $this->walk($data, $redact);
    }

    public function hash(array $data): string
    {
        $sanitized = $this->sanitize($data);
        ksort($sanitized);

        return sha1(json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function walk(array $data, array $redact): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $lower = is_string($k) ? strtolower($k) : '';
            if (in_array($lower, $redact, true)) {
                $out[$k] = '***';

                continue;
            }
            if (is_array($v)) {
                $out[$k] = $this->walk($v, $redact);

                continue;
            }
            if (is_string($v) && mb_strlen($v) > 500) {
                $out[$k] = mb_substr($v, 0, 500).'…';

                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
