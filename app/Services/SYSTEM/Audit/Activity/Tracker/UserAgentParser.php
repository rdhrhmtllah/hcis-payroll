<?php

namespace App\Services\SYSTEM\Audit\Activity\Tracker;

class UserAgentParser
{
    public function parse(?string $agent): array
    {
        $agent = (string) ($agent ?? '');

        return [
            'raw' => mb_substr($agent, 0, 1000),
            'browser' => $this->detectBrowser($agent),
            'browser_version' => $this->detectBrowserVersion($agent),
            'engine' => $this->detectEngine($agent),
            'os' => $this->detectOS($agent),
            'os_version' => $this->detectOSVersion($agent),
            'device_type' => $this->detectDeviceType($agent),
            'is_mobile' => $this->isMobile($agent),
            'is_bot' => $this->isBot($agent),
        ];
    }

    public function detectBrowser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            str_contains($agent, 'MSIE') || str_contains($agent, 'Trident/') => 'IE',
            default => 'Unknown',
        };
    }

    public function detectBrowserVersion(string $agent): ?string
    {
        $patterns = [
            'Edge' => '/Edg\/([0-9\.]+)/',
            'Opera' => '/(?:OPR|Opera)\/([0-9\.]+)/',
            'Chrome' => '/Chrome\/([0-9\.]+)/',
            'Firefox' => '/Firefox\/([0-9\.]+)/',
            'Safari' => '/Version\/([0-9\.]+).*Safari/',
            'IE' => '/(?:MSIE |rv:)([0-9\.]+)/',
        ];

        $browser = $this->detectBrowser($agent);
        $pattern = $patterns[$browser] ?? null;
        if ($pattern && preg_match($pattern, $agent, $m)) {
            return mb_substr($m[1], 0, 40);
        }

        return null;
    }

    public function detectEngine(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'AppleWebKit') => 'WebKit/Blink',
            str_contains($agent, 'Gecko') && ! str_contains($agent, 'like Gecko') => 'Gecko',
            str_contains($agent, 'Trident') => 'Trident',
            default => 'Unknown',
        };
    }

    public function detectOS(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Windows NT') => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'MacOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iOS',
            str_contains($agent, 'iPad') => 'iPadOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Other',
        };
    }

    public function detectOSVersion(string $agent): ?string
    {
        if (preg_match('/Windows NT ([0-9\.]+)/', $agent, $m)) {
            return $this->translateWindowsNt($m[1]);
        }
        if (preg_match('/Mac OS X ([0-9_\.]+)/', $agent, $m)) {
            return str_replace('_', '.', $m[1]);
        }
        if (preg_match('/Android ([0-9\.]+)/', $agent, $m)) {
            return $m[1];
        }
        if (preg_match('/(?:iPhone|iPad).+?OS ([0-9_]+)/', $agent, $m)) {
            return str_replace('_', '.', $m[1]);
        }

        return null;
    }

    private function translateWindowsNt(string $nt): string
    {
        return match ($nt) {
            '10.0' => '10/11',
            '6.3' => '8.1',
            '6.2' => '8',
            '6.1' => '7',
            default => $nt,
        };
    }

    public function detectDeviceType(string $agent): string
    {
        if ($this->isBot($agent)) {
            return 'bot';
        }
        if (preg_match('/iPad|Tablet/i', $agent)) {
            return 'tablet';
        }
        if ($this->isMobile($agent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public function isMobile(string $agent): bool
    {
        return (bool) preg_match('/(iPhone|Android|webOS|BlackBerry|IEMobile|Opera Mini)/i', $agent);
    }

    public function isBot(string $agent): bool
    {
        return (bool) preg_match('/(bot|crawler|spider|slurp|facebookexternalhit|googlebot|bingbot)/i', $agent);
    }
}
