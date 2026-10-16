<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutoAppendTracking
{
    protected int $uidLength = 16;

    protected string $appVersion = '2.0.0';

    protected string $defaultTimezone = 'Asia/Jakarta';

    protected array $excludedPaths = [
        'api/',
        'logout',
        'register',
        'password',
        'storage',
        'broadcasting',
    ];

    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $currentPath = ltrim($request->path(), '/');

        foreach ($this->excludedPaths as $excluded) {
            if (Str::startsWith($currentPath, $excluded)) {
                return $next($request);
            }
        }

        // 📌 Deteksi browser dan sesuaikan panjang UID secara otomatis
        $userAgent = $request->header('User-Agent', 'Unknown-UA');
        $this->uidLength = $this->resolveUidLength($userAgent);

        if (! $request->has('uid')) {
            $acceptLang = $request->header('Accept-Language', 'en');
            $ipAddress = $request->ip();
            $host = $request->getHost();
            $scheme = $request->getScheme();
            $method = $request->method();
            $referer = $request->header('Referer', 'direct');

            // ✅ UID disesuaikan otomatis
            $uid = $this->generateAdaptiveUid($this->uidLength);
            $rid = Str::uuid()->toString();
            $sess = substr(hash('sha1', session()->getId()), 0, 12);
            $fp = substr(hash('sha1', $userAgent.$ipAddress), 0, 16);
            $visitorId = substr(hash('sha1', $ipAddress.$userAgent), 0, 20);
            $deviceId = substr(hash('sha256', $userAgent.$ipAddress.$sess), 0, 32);
            $traceId = 'trace-'.Str::uuid().'-'.Str::random(6);
            $spanId = 'span-'.bin2hex(random_bytes(4));
            $txnId = strtoupper(Str::random(14));
            $nonce = Str::random(20);
            $entropy = bin2hex(random_bytes(32));
            $secretKey = 'Kera3428Aja';

            $buildSeed = $this->appVersion.'|'.date('YmdHis');
            $buildHash = substr(hash('sha256', $buildSeed.$secretKey), 0, 8);
            $buildNumber = sprintf('build-%s-%s', date('Ymd'), strtoupper($buildHash));
            $timestamp = now()->timestamp;
            $signature = hash_hmac('sha256', "{$uid}|{$rid}|{$timestamp}|{$entropy}", $secretKey);
            $hashFinal = substr(hash('sha256', $uid.$rid.$entropy.$sess.$timestamp), 0, 40);
            $replayCode = substr(hash('sha256', $nonce.$timestamp.$secretKey), 0, 24);

            $trackingData = [
                'uid' => $uid,
                'rid' => $rid,
                'trace_id' => $traceId,
                'span_id' => $spanId,
                'txn_id' => $txnId,
                'sess' => $sess,
                'device_id' => $deviceId,
                'visitor_id' => $visitorId,
                'fp' => $fp,
                'browser' => $this->detectBrowser($userAgent),
                'engine' => $this->detectEngine($userAgent),
                'os' => $this->detectOS($userAgent),
                'arch' => php_uname('m') ?: 'unknown',
                'src' => $this->isMobile($userAgent) ? 'mobile' : 'desktop',
                'ua' => base64_encode($userAgent),
                'lang' => substr($acceptLang, 0, 2) ?: 'en',
                'country' => strtoupper(substr($acceptLang, 3, 2) ?: 'ID'),
                'client' => str_contains(strtolower($userAgent), 'bot') ? 'bot' : 'browser',
                'ip' => $ipAddress,
                'host' => $host,
                'proto' => $scheme,
                'method' => $method,
                'referer' => $referer,
                'net' => $request->header('Save-Data') ? 'low' : 'high',
                'v' => $this->appVersion,
                'tz' => $this->defaultTimezone,
                'platform' => $request->header('X-Platform', 'web'),
                'build' => $buildNumber,
                'flow' => 'entry',
                'rand' => random_int(10000, 999999),
                'nonce' => $nonce,
                'entropy' => substr($entropy, 0, 24),
                'signature' => $signature,
                'hash' => $hashFinal,
                'replay_code' => $replayCode,
                'ts' => $timestamp,
                'ts_ms' => round(microtime(true) * 1000),
                'uptime' => round(microtime(true) - $startTime, 6),
            ];

            return redirect()->to($request->url().'?'.http_build_query($trackingData));
        }

        return $next($request);
    }

    /**
     * 🔧 Menyesuaikan panjang UID berdasarkan browser
     */
    protected function resolveUidLength(string $agent): int
    {
        $agent = strtolower($agent);

        return match (true) {
            // Safari kadang error jika UID terlalu panjang
            str_contains($agent, 'safari') && ! str_contains($agent, 'chrome') => 24,
            // Edge lebih aman di bawah 48
            str_contains($agent, 'edg') => 32,
            // Browser mobile biasanya lebih ketat
            str_contains($agent, 'mobile') || str_contains($agent, 'android') || str_contains($agent, 'iphone') => 20,
            // Default Chrome, Firefox, dll.
            default => 64,
        };
    }

    /**
     * 🧠 Membuat UID adaptif, menjaga informasi meskipun dipendekkan
     */
    protected function generateAdaptiveUid(int $length): string
    {
        $uidFull = bin2hex(random_bytes(32)); // 64 chars
        if (strlen($uidFull) <= $length) {
            return $uidFull;
        }

        // Ambil setengah dari awal dan akhir agar tetap punya entropi unik
        $half = (int) floor($length / 2);

        return substr($uidFull, 0, $half).substr($uidFull, -$half);
    }

    protected function detectBrowser(string $agent): string
    {
        return match (true) {
            preg_match('/Chrome\/([\d.]+)/', $agent, $m) => 'Chrome_'.$m[1],
            preg_match('/Firefox\/([\d.]+)/', $agent, $m) => 'Firefox_'.$m[1],
            preg_match('/Edg\/([\d.]+)/', $agent, $m) => 'Edge_'.$m[1],
            preg_match('/Safari\/([\d.]+)/', $agent, $m) && ! str_contains($agent, 'Chrome') => 'Safari_'.($m[1] ?? 'unknown'),
            default => 'Unknown',
        };
    }

    protected function detectEngine(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'AppleWebKit') => 'WebKit/Blink',
            str_contains($agent, 'Gecko') && ! str_contains($agent, 'like Gecko') => 'Gecko',
            str_contains($agent, 'Trident') => 'Trident',
            default => 'Unknown',
        };
    }

    protected function detectOS(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Macintosh') => 'MacOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') => 'iOS',
            str_contains($agent, 'iPad') => 'iPadOS',
            default => 'Other',
        };
    }

    protected function isMobile(string $agent): bool
    {
        return (bool) preg_match('/(iPhone|Android|webOS|BlackBerry|IEMobile|Opera Mini)/i', $agent);
    }
}
