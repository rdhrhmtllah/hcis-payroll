<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Services\SYSTEM\Integration\ApiEvidenceSanitizer;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard inbound generik lapisan N_HRIS_Api_* (HMAC-SHA512 + nonce anti-replay +
 * whitelist endpoint).
 *
 * Setiap permintaan meninggalkan tepat satu baris bukti di N_HRIS_Api_Log,
 * dicatat SETELAH responsnya diketahui. Status_Code karena itu adalah status
 * yang benar-benar diterima pemanggil, bukan sekadar tanda bahwa permintaan
 * lolos gerbang keamanan.
 */
class HrisApiGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);
        $correlationId = (string) Str::uuid();
        $request->attributes->set('api_correlation_id', $correlationId);

        // NULL berarti belum bisa dinilai. Jangan menganggap signature sah
        // sebelum public key dikenal dan hash_equals() benar-benar lolos.
        $jejak = [
            'app' => null,
            'grup' => null,
            // Kontrak tabel hanya mengizinkan IN/OUT (varchar(3)).
            'arah' => 'IN',
            'signature_valid' => null,
            // Kategori resmi CK_N_HRIS_Api_Log_Src. Nilainya diperinci
            // sepanjang pemeriksaan dan diganti CLIENT/SERVER setelah lolos
            // gerbang lalu controller membalas galat.
            'sumber' => 'AUTH',
            'lolos_gerbang' => false,
            'error' => null,
        ];

        try {
            $tolakan = $this->periksa($request, $jejak);

            if ($tolakan !== null) {
                return $this->catat($request, $tolakan, $jejak, $mulai, $correlationId);
            }

            return $this->catat($request, $next($request), $jejak, $mulai, $correlationId);
        } catch (\Throwable $e) {
            return $this->catat(
                $request,
                $this->tanganiGalat($request, $e, $jejak, $correlationId),
                $jejak,
                $mulai,
                $correlationId,
            );
        }
    }

    /**
     * Ubah exception menjadi respons LEWAT penangan galat Laravel.
     *
     * Alasannya sama dengan kembarannya di LmsApiGuard: menangkap \Throwable di
     * middleware membuang jenis respons yang sebenarnya (422 validasi berubah
     * jadi 500 generik) sekaligus laporannya (stack trace tak pernah masuk kanal
     * galat biasa). Aturan yang sama wajib berlaku di kedua pintu — di proyek
     * ini sudah terbukti aturan yang hanya dipasang di satu pintu akan
     * menyimpang di pintu lainnya.
     */
    protected function tanganiGalat(
        Request $request,
        \Throwable $e,
        array &$jejak,
        string $correlationId,
    ): Response {
        try {
            $penangan = app(ExceptionHandler::class);
            $penangan->report($e);
            $respons = $penangan->render($request, $e);
        } catch (\Throwable $gagal) {
            Log::error('HRIS_API_GUARD_ERROR', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
                'line' => $e->getLine(),
                'penangan_gagal' => $gagal::class,
            ]);

            $jejak['error'] = 'Security System Failure';

            return ResponseHelper::error('Security System Failure', 500);
        }

        if ($respons->getStatusCode() >= 500) {
            Log::error('HRIS_API_GUARD_ERROR', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
                'line' => $e->getLine(),
                'status' => $respons->getStatusCode(),
            ]);
        }

        return $respons;
    }

    /**
     * Jalankan seluruh pemeriksaan gerbang.
     *
     * Respons berarti permintaan ditolak di gerbang. NULL berarti seluruh
     * pemeriksaan lolos dan controller boleh dijalankan.
     */
    protected function periksa(Request $request, array &$jejak): ?Response
    {
        if (config('app.env') === 'production' && ! $request->secure()) {
            return ResponseHelper::error('HTTPS Required', 403);
        }

        $publicKey = $request->header('X-HC-Public');
        $signature = $request->header('X-HC-Signature');
        $timestamp = $request->header('X-HC-Timestamp');
        $nonce = $request->header('X-HC-Nonce');
        $bodyHash = $request->header('X-HC-Body-Hash');

        if (! $publicKey || ! $signature || ! $timestamp || ! $nonce || ! $bodyHash) {
            return ResponseHelper::error('Security headers incomplete', 401);
        }

        if (abs(time() - strtotime($timestamp)) > 60) {
            return ResponseHelper::error('Request expired', 401);
        }

        $app = DB::table('N_HRIS_Api_Aplikasi')
            ->where('Api_Public', $publicKey)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $app) {
            return ResponseHelper::error('Unauthorized application', 401);
        }

        // Seluruh nilai diambil dari registry pada database INI; tidak ada Id
        // identity dari lingkungan lain yang disalin.
        $jejak['app'] = (int) $app->Id_Api_Aplikasi;
        $jejak['grup'] = (int) $app->Id_Grup_Fitur;
        $jejak['arah'] = 'IN';

        if (DB::table('N_HRIS_Api_Nonce')->where('Nonce', $nonce)->exists()) {
            $jejak['sumber'] = 'NONCE';

            return ResponseHelper::error('Duplicate request', 401);
        }

        $method = strtoupper($request->method());
        ['pola' => $polaRute, 'tandaTangan' => $path] = $this->jalurPermintaan($request);
        $query = $request->getQueryString() ?? '';
        $body = $request->getContent() ?? '';

        if (hash('sha256', $body) !== $bodyHash) {
            return response()->json(['message' => 'Body hash invalid'], 401);
        }

        $payload = $method."\n".$path."\n".$query."\n".$timestamp."\n".$nonce."\n".$bodyHash."\n".$publicKey;
        $serverSignature = hash_hmac('sha512', $payload, $app->Api_Secret);

        if (! hash_equals($serverSignature, $signature)) {
            $jejak['signature_valid'] = 'T';
            $jejak['sumber'] = 'SIGNATURE';

            return ResponseHelper::error('Invalid signature', 401);
        }

        $jejak['signature_valid'] = 'Y';

        $whitelisted = DB::table('N_HRIS_Api_Akses_Endpoint as ae')
            ->join('N_HRIS_Api_Endpoint as ep', 'ep.Id_Api_Endpoint', '=', 'ae.Id_Api_Endpoint')
            ->where('ae.Id_Api_Aplikasi', $app->Id_Api_Aplikasi)
            ->where('ep.Url_Route', $polaRute)
            ->where('ep.Method', $method)
            ->where('ep.Flag_Aktif', 'Y')
            ->where('ae.Flag_Aktif', 'Y')
            ->exists();

        if (! $whitelisted) {
            return ResponseHelper::error('Endpoint not authorized for this application', 403);
        }

        // Nonce valid tetap dikonsumsi SEBELUM controller. Respons bisnis 4xx
        // tidak boleh membuat payload yang sama dapat dimainkan ulang.
        if ($tabrakan = $this->simpanNonce((int) $app->Id_Api_Aplikasi, $nonce)) {
            $jejak['sumber'] = 'NONCE';

            return $tabrakan;
        }
        $request->attributes->set('hris_api_app_id', $app->Id_Api_Aplikasi);
        $jejak['lolos_gerbang'] = true;

        return null;
    }

    /**
     * Konsumsi nonce, dan perlakukan tabrakan UNIQUE sebagai replay.
     *
     * Pemeriksaan "sudah pernah dipakai?" dan penyimpanannya adalah dua
     * pernyataan terpisah, jadi dua permintaan bernonce sama yang datang
     * bersamaan sama-sama bisa lolos pemeriksaan. Yang menutup celah itu adalah
     * indeks UNIQUE di database (DDL-2026-095) — tetapi indeks itu hanya
     * bermanfaat kalau pelanggarannya diterjemahkan menjadi jawaban yang benar.
     * Tanpa terjemahan ini, pemenang kedua menerima 500 "Security System
     * Failure" untuk sesuatu yang sebenarnya penolakan anti-replay biasa: pihak
     * seberang akan mengira HCIS rusak, lalu mencoba lagi.
     */
    protected function simpanNonce(int $idAplikasi, string $nonce): ?Response
    {
        try {
            DB::table('N_HRIS_Api_Nonce')->insert([
                'Nonce' => $nonce,
                'Id_Api_Aplikasi' => $idAplikasi,
                'Expired_At' => now()->addMinutes(5),
                'Created_At' => now(),
            ]);
        } catch (QueryException $e) {
            if (! $this->tabrakanUnik($e)) {
                throw $e;
            }

            return ResponseHelper::error('Duplicate request', 401);
        }

        return null;
    }

    /**
     * 2601/2627 = pelanggaran indeks/constraint unik di SQL Server. Dicocokkan
     * lewat kode galat driver, bukan lewat teks pesannya: teks itu berubah
     * mengikuti bahasa dan versi server.
     */
    protected function tabrakanUnik(QueryException $e): bool
    {
        return in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true);
    }

    /**
     * Jalur permintaan punya DUA peran yang tidak boleh dicampur:
     *
     * - `pola`        — pola rute (mis. ".../tahun/{tahun}"), dipakai mencocokkan
     *                   whitelist. Satu baris registry cukup untuk semua tahun.
     * - `tandaTangan` — jalur yang benar-benar dipanggil (mis. ".../tahun/2026"),
     *                   dipakai di payload HMAC dan di log. Inilah yang dilihat
     *                   dan ditandatangani klien; ia tidak tahu-menahu soal pola
     *                   rute internal kita.
     *
     * Untuk rute statis keduanya identik. Jangan disatukan lagi: menyatukannya
     * membuat setiap rute berparameter ditolak 401 tanpa petunjuk penyebabnya.
     *
     * @return array{pola:string, tandaTangan:string}
     */
    private function jalurPermintaan(Request $request): array
    {
        $route = $request->route();

        return [
            'pola' => $route ? '/'.ltrim($route->uri(), '/') : $request->getPathInfo(),
            'tandaTangan' => $request->getPathInfo(),
        ];
    }

    /**
     * Catat respons sebenarnya dan kembalikan id yang sama ke pemanggil.
     *
     * Kegagalan menulis jejak tidak boleh menggagalkan permintaan bisnis yang
     * sudah diproses. Production belum punya tabel N_HRIS_Api_Log sampai bagian
     * registry DDL-2026-092 dijalankan; pada keadaan itu callback tetap berjalan
     * dan kegagalan pencatatan jatuh ke log berkas.
     */
    private function catat(
        Request $request,
        Response $response,
        array $jejak,
        float $mulai,
        string $correlationId,
    ): Response {
        $status = $response->getStatusCode();
        $error = $jejak['error'] ?? $this->pesanGalatRespons($response, $status);
        // 5xx selalu SERVER, termasuk ketika galatnya meletus di dalam gerbang
        // sendiri; sebelumnya kasus itu tersimpan sebagai 'AUTH' dan membuat
        // kegagalan kami terlihat seperti kesalahan pemanggil.
        $sumber = $status < 400
            ? 'NONE'
            : ($status >= 500
                ? 'SERVER'
                : ($jejak['lolos_gerbang'] ? 'CLIENT' : $jejak['sumber']));

        try {
            DB::table('N_HRIS_Api_Log')->insert([
                'Id_Api_Aplikasi' => $jejak['app'],
                'Id_Grup_Fitur' => $jejak['grup'],
                'Arah' => $jejak['arah'],
                'Sumber_Error' => $sumber,
                'Endpoint' => $request->getPathInfo(),
                'Http_Method' => strtoupper($request->method()),
                'Status_Code' => $status,
                'Signature_Valid' => $jejak['signature_valid'],
                'Correlation_Id' => $correlationId,
                'Durasi_Ms' => (int) round((microtime(true) - $mulai) * 1000),
                'Payload_Hash' => hash('sha256', (string) $request->getContent()),
                'Request_Ringkas' => app(ApiEvidenceSanitizer::class)
                    ->ringkasBody($request->getContent(), 1000),
                'Response_Ringkas' => app(ApiEvidenceSanitizer::class)
                    ->ringkasBody($response->getContent(), 2000),
                'Error_Message' => $error,
                'Created_At' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('HRIS_API_LOG_GAGAL', [
                'correlation_id' => $correlationId,
                // Jangan log QueryException::getMessage(): ia menyertakan SQL
                // dan binding, termasuk request ringkas yang bisa membawa token.
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
            ]);
        }

        $response->headers->set('X-HC-Correlation-Id', $correlationId);

        return $response;
    }

    private function pesanGalatRespons(Response $response, int $status): ?string
    {
        if ($status < 400) {
            return null;
        }

        $data = json_decode((string) $response->getContent(), true);
        $pesan = is_array($data) ? ($data['message'] ?? null) : null;

        if (is_array($pesan)) {
            $pesan = collect($pesan)->flatten()->filter()->implode(' ');
        }

        return app(ApiEvidenceSanitizer::class)
            ->ringkasTeks(is_scalar($pesan) ? (string) $pesan : null, 1000);
    }
}
