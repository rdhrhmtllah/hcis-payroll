<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

class IpMasker
{
    public function mask(?string $ip, bool $isAdmin = false): ?string
    {
        if (! $ip) {
            return null;
        }
        if ($isAdmin) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                $parts[3] = '***';

                return implode('.', $parts);
            }

            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $keep = array_slice($parts, 0, 4);

            return implode(':', $keep).':****:****:****:****';
        }

        return $ip;
    }
}
