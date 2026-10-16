<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SignatureHelper
{
    private static function buildUrl($endpoint, $params = [])
    {
        $domain = rtrim(config('services.frans.hclearn_api.domain'), '/');
        // Pastikan tidak ada double slash
        $endpoint = ltrim($endpoint, '/');
        $url = $domain.'/'.$endpoint;

        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $url;
    }

    private static function sign($url, $method, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $publicKey = config('services.frans.hclearn_api.public');
        $secretKey = config('services.frans.hclearn_api.secret');

        $timestamp = date('Y-m-d H:i:s');
        $nonce = Str::random(32);

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY) ?? '';

        $bodyJson = empty($body) ? '' : json_encode($body);
        $bodyHash = hash('sha256', $bodyJson);

        $payloadParts = [strtoupper($method), $path, $query, $timestamp, $nonce, $bodyHash, $publicKey];

        $payload = implode("\n", $payloadParts);
        $signature = hash_hmac('sha512', $payload, $secretKey);

        $headers = [
            'X-HC-Public' => $publicKey,
            'X-HC-Timestamp' => $timestamp,
            'X-HC-Nonce' => $nonce,
            'X-HC-Body-Hash' => $bodyHash,
            'X-HC-Signature' => $signature,
        ];

        if ($appId) {
            $headers['X-App-ID'] = $appId;
        }

        if ($kodeKaryawan) {
            $headers['X-Kode-Karyawan'] = $kodeKaryawan;
        }

        return [
            'headers' => $headers,
            'body' => $bodyJson,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GET LIST TANPA PARAMETER
    |--------------------------------------------------------------------------
    */
    // Tambahkan parameter $endpoint di awal
    public static function get($endpoint, $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | GET LIST + SEARCH
    |--------------------------------------------------------------------------
    */
    public static function getWithParams($endpoint, $params = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint, $params);

        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | GET DETAIL
    |--------------------------------------------------------------------------
    */
    public static function getDetail($endpoint, $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'GET', [], $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public static function store($endpoint, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'POST', $body, $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE (POST)
    |--------------------------------------------------------------------------
    */
    public static function update($endpoint, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'POST', $body, $appId, $kodeKaryawan);
    }

    /*
    |--------------------------------------------------------------------------
    | PUT dan DELETE
    |--------------------------------------------------------------------------
    |
    | METODENYA IKUT DITANDATANGANI. Muatan tanda tangan memuat metode HTTP pada baris
    | pertama, jadi menandatangani sebagai POST lalu mengirimnya sebagai PUT menghasilkan
    | 401 "Invalid Signature" — galat yang menuduh kuncinya, bukan metodenya, dan karena itu
    | paling lama dicari.
    |
    | Ditambahkan 10 September 2026 untuk kontrak penjadwalan PA: `update/penjadwalan`
    | menerima PUT maupun POST, tetapi `extend-time/penjadwalan` hanya PUT dan
    | `delete/penjadwalan` hanya DELETE.
    */
    public static function put($endpoint, $body = [], $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'PUT', $body, $appId, $kodeKaryawan);
    }

    public static function destroy($endpoint, $appId = null, $kodeKaryawan = null)
    {
        $url = self::buildUrl($endpoint);

        return self::sign($url, 'DELETE', [], $appId, $kodeKaryawan);
    }
}
