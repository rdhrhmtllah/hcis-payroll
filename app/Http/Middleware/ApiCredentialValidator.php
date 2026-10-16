<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiCredentialValidator
{
    public function handle(Request $request, Closure $next): Response
    {
        try {

            if (config('app.env') === 'production' && ! $request->secure()) {
                return response()->json([
                    'message' => 'HTTPS Required',
                ], 403);
            }

            $publicKey = $request->header('X-HC-Public');
            $signature = $request->header('X-HC-Signature');
            $timestamp = $request->header('X-HC-Timestamp');
            $nonce = $request->header('X-HC-Nonce');
            $bodyHash = $request->header('X-HC-Body-Hash');

            if (! $publicKey || ! $signature || ! $timestamp || ! $nonce || ! $bodyHash) {
                return ResponseHelper::error('Security Incomplete', 401);
            }

            if (abs(time() - strtotime($timestamp)) > 60) {
                return ResponseHelper::error('Request Expired', 401);
            }

            $app = DB::table('N_HRIS_KANDIDAT_Api_Aplikasi')
                ->where('Api_Public', $publicKey)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $app) {
                return ResponseHelper::error('Non Official', 401);
            }

            $nonceExists = DB::table('N_HRIS_KANDIDAT_Api_Nonces')
                ->where('Nonce', $nonce)
                ->exists();

            if ($nonceExists) {
                return ResponseHelper::error('Duplicate Request', 401);
            }

            $method = strtoupper($request->method());

            $path = '/'.$request->path();

            $query = $request->getQueryString() ?? '';

            $body = $request->getContent() ?? '';

            $serverBodyHash = hash('sha256', $body);

            if ($serverBodyHash !== $bodyHash) {
                return response()->json(['message' => 'Body Hash Invalid'], 401);
            }

            $payload =
                $method."\n".
                $path."\n".
                $query."\n".
                $timestamp."\n".
                $nonce."\n".
                $bodyHash."\n".
                $publicKey;

            $serverSignature = hash_hmac('sha512', $payload, $app->Api_Secret);

            Log::info('API_SIGNATURE_VALIDATION', [
                'payload' => $payload,
                'serverSignature' => $serverSignature,
                'clientSignature' => $signature,
            ]);

            if (! hash_equals($serverSignature, $signature)) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 401, 'T');

                return ResponseHelper::error('Invalid Signature', 401);
            }

            DB::table('N_HRIS_KANDIDAT_Api_Nonces')->insert([
                'Id_Api_Aplikasi' => $app->Id_Api_Aplikasi,
                'Nonce' => $nonce,
                'Expired_At' => now()->addMinutes(5),
            ]);
            $this->logAttempt($app->Id_Api_Aplikasi, $path, 200, 'Y');

            return $next($request);
        } catch (\Throwable $e) {
            Log::error('SECURITY_ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ResponseHelper::error('Security System Failure', 500);
        }
    }

    private function logAttempt($appId, $path, $code, $valid)
    {
        DB::table('N_HRIS_KANDIDAT_Api_Logs')->insert([
            'Id_Api_Aplikasi' => $appId,
            'Endpoint' => $path,
            'Status_Code' => $code,
            'Signature_Valid' => $valid,
            'Created_At' => now(),
        ]);
    }
}
