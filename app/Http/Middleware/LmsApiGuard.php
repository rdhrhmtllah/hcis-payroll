<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Integration\ApiEvidenceSanitizer;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LmsApiGuard
{
    /**
     * Setiap permintaan meninggalkan TEPAT SATU baris di N_LMS_Api_Logs, dicatat
     * SESUDAH responsnya diketahui.
     *
     * Sebelumnya pencatatan tersebar di sepuluh tempat di dalam pemeriksaan, dan
     * jalur suksesnya menulis angka 200 yang dipaku sebelum `$next($request)`
     * dipanggil. Angka itu berarti "lolos gerbang", bukan "HCIS membalas 200":
     * kegagalan di dalam controller tak pernah bisa muncul di log. Terlihat di
     * production — 1.639 baris, nol di antaranya 5xx.
     *
     * Enam penolakan paling awal (HTTPS, header tak lengkap, timestamp
     * kedaluwarsa, aplikasi tak dikenal, nonce berulang, body hash tak cocok)
     * bahkan tidak menulis baris sama sekali. Untuk pihak luar, "ditolak" dan
     * "tidak pernah sampai" jadi tak bisa dibedakan — persis ruang saling tebak
     * yang membuat sengketa tak bisa diselesaikan. Sekarang keduanya tercatat.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mulai = microtime(true);
        $correlationId = (string) Str::uuid();
        $request->attributes->set('api_correlation_id', $correlationId);

        // NULL berarti belum bisa dinilai (mis. header tidak lengkap atau public
        // key tidak dikenal). Jangan menulis 'Y' sebelum HMAC benar-benar lolos.
        $jejak = [
            'app' => null,
            'signature_valid' => null,
            'sumber' => 'GERBANG',
            'modul' => null,
            'error' => null,
        ];

        try {
            $tolakan = $this->periksa($request, $jejak);

            if ($tolakan !== null) {
                return $this->catat($request, $tolakan, $jejak, $mulai, $correlationId);
            }

            $jejak['sumber'] = 'APLIKASI';

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
     * Menangkap \Throwable di middleware berarti exception tidak pernah sampai
     * ke penangan galat framework. Dua hal hilang sekaligus: jenis responsnya
     * (ValidationException seharusnya 422 berisi field yang salah, bukan 500
     * "Security System Failure") dan laporannya (stack trace tak pernah masuk
     * kanal galat biasa, jadi bug controller hanya menyisakan satu baris tanpa
     * jejak). Untuk perbatasan yang justru dibangun agar kedua pihak berhenti
     * saling tebak, mengganti galat sebenarnya dengan kalimat generik adalah
     * kemunduran.
     *
     * report() aman dipanggil untuk semua jenis: penangan Laravel sendiri yang
     * memutuskan mana yang tak perlu dilaporkan (validasi, HTTP, autentikasi).
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
            // Penangan galat pun gagal. Di titik ini tidak ada lagi yang bisa
            // dipercaya selain menutup permintaan dengan aman.
            Log::error('LMS_API_GUARD_ERROR', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'penangan_gagal' => $gagal::class,
            ]);

            // Kolom Error_Message menyimpan pesan aman (pesan respons), bukan
            // pesan exception mentah yang bisa memuat SQL + binding.
            $jejak['error'] = 'Security System Failure';

            return ResponseHelper::error('Security System Failure', 500);
        }

        if ($respons->getStatusCode() >= 500) {
            // 4xx dari validasi/aturan bisnis bukan insiden; hanya 5xx yang
            // perlu jangkar tambahan di log berkas.
            Log::error('LMS_API_GUARD_ERROR', [
                'correlation_id' => $correlationId,
                'exception' => $e::class,
                'code' => (string) $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'status' => $respons->getStatusCode(),
            ]);
        }

        return $respons;
    }

    protected function periksa(Request $request, array &$jejak): ?Response
    {
        // 1. Cek HTTPS (hanya berlaku di environment production)
        if (config('app.env') === 'production' && ! $request->secure()) {
            return ResponseHelper::error('HTTPS Required', 403);
        }

        // 2. Ambil dan cek kelengkapan header yang diperlukan
        $publicKey = $request->header('X-HC-Public');
        $signature = $request->header('X-HC-Signature');
        $timestamp = $request->header('X-HC-Timestamp');
        $nonce = $request->header('X-HC-Nonce');
        $bodyHash = $request->header('X-HC-Body-Hash');

        if (! $publicKey || ! $signature || ! $timestamp || ! $nonce || ! $bodyHash) {
            return ResponseHelper::error('Security headers incomplete', 401);
        }

        // 3. Validasi Timestamp (Request dilarang apabila selisih melebihi 60 detik / expired)
        if (abs(time() - strtotime($timestamp)) > 60) {
            return ResponseHelper::error('Request expired', 401);
        }

        // 4. Aplikasi/Public Key Lookup
        $app = DB::table('N_LMS_Api_Aplikasi')->where('Api_Public', $publicKey)->where('Flag_Aktif', 'Y')->first();

        if (! $app) {
            return ResponseHelper::error('Unauthorized application', 401);
        }

        // Sejak titik ini baris bukti bisa diatribusikan ke konsumen yang
        // benar. Id identity ini milik database yang sama — bukan Id dari
        // server lain — jadi aman disimpan langsung.
        $jejak['app'] = (int) $app->Id_Api_Aplikasi;

        // 5. Pencegahan Replay Attack dengan Nonce Lookup
        $nonceExists = DB::table('N_LMS_Api_Nonces')->where('Nonce', $nonce)->exists();

        if ($nonceExists) {
            return ResponseHelper::error('Duplicate request', 401);
        }

        $method = strtoupper($request->method());
        $path = '/'.ltrim($request->route()->uri(), '/');
        $query = $request->getQueryString() ?? '';
        $body = $request->getContent() ?? '';

        // 6. Validasi Body Hash (memastikan body content tidak dimanipulasi di tengah jalan)
        $serverBodyHash = hash('sha256', $body);
        if ($serverBodyHash !== $bodyHash) {
            return response()->json(['message' => 'Body hash invalid'], 401);
        }

        // 7. HMAC-SHA512 Signature Validation
        $payload =
            $method.
            "\n".
            $path.
            "\n".
            $query.
            "\n".
            $timestamp.
            "\n".
            $nonce.
            "\n".
            $bodyHash.
            "\n".
            $publicKey;

        $serverSignature = hash_hmac('sha512', $payload, $app->Api_Secret);

        if (! hash_equals($serverSignature, $signature)) {
            $jejak['signature_valid'] = 'T';

            return ResponseHelper::error('Invalid signature', 401);
        }

        // Hanya sesudah hash_equals() lolos tanda tangannya sah. Semua
        // penolakan setelah titik ini tetap menyimpan Y karena permintaannya
        // autentik, walaupun ditolak whitelist atau aturan bisnis.
        $jejak['signature_valid'] = 'Y';

        // 8. Whitelist Endpoint Check
        $isWhitelisted = DB::table('N_LMS_Akses_Endpoint as ae')
            ->join('N_LMS_Endpoint_Api as ep', 'ep.Id_Endpoint_Api', '=', 'ae.Id_Endpoint_Api')
            ->where('ae.Id_Api_Aplikasi', $app->Id_Api_Aplikasi)
            ->where('ep.Url_Route', $path)
            ->where('ep.Method', $method)
            ->where('ep.Flag_Aktif', 'Y')
            ->where('ae.Flag_Aktif', 'Y')
            ->exists();

        if (! $isWhitelisted) {
            return ResponseHelper::error('Endpoint not authorized for this application', 403);
        }

        // 9. Validasi payload identitas ujian dan kepemilikannya
        $bodyData = json_decode($body, true);
        $kodeKaryawanRaw = isset($bodyData['Kode_Karyawan'])
            ? strtoupper(trim((string) $bodyData['Kode_Karyawan']))
            : null;
        $kodeKaryawan = $kodeKaryawanRaw ?? null;
        $idPenjadwalanIntegrasi = $bodyData['Id_Penjadwalan_Integrasi'] ?? null;
        $idUjianToken = $bodyData['Id_Ujian_Token'] ?? null;

        if (! $kodeKaryawan) {
            return ResponseHelper::error('Kode_Karyawan is required in body', 422);
        }

        if (! $idPenjadwalanIntegrasi) {
            return ResponseHelper::error('Id_Penjadwalan_Integrasi is required in body', 422);
        }

        if (! $idUjianToken) {
            return ResponseHelper::error('Id_Ujian_Token is required in body', 422);
        }

        /*
         * ── Penentuan modul pemilik token ──────────────────────────────
         * HC Learn memakai SATU URL callback untuk semua modul, jadi asal
         * hasil ujian (LMS atau SOP) tidak bisa dibaca dari endpoint-nya.
         * Yang membedakan adalah TOKEN: ia dicetak HC Learn per sesi ujian
         * dan tersimpan di tepat satu tabel integrasi.
         *
         * Sebelumnya penentuan ini bersifat menyimpulkan: "query LMS gagal
         * → anggap saja SOP". Itu rapuh, karena query LMS ikut menyaring
         * Id_Penjadwalan_Integrasi, Kode_Karyawan, dan 3 join. Callback LMS
         * yang gagal karena SALAH KODE KARYAWAN pun ikut jatuh ke cabang SOP,
         * lalu berakhir 404 dengan pesan yang menyesatkan — padahal masalah
         * sebenarnya soal kepemilikan.
         *
         * Sekarang modulnya ditentukan lebih dulu, dari token saja.
         */
        $sopIntegrasi = DB::table('N_SOP_Penjadwalan_Integrasi')
            ->where('Id_Ujian_Token', $idUjianToken)
            ->orderByDesc('Id_SOP_Penjadwalan_Integrasi')
            ->first();
        $tokenMilikLms = DB::table('N_LMS_Penjadwalan_Integrasi')
            ->where('Id_Ujian_Token', $idUjianToken)
            ->exists();

        /*
         * Performance Appraisal TIDAK lewat sini. Ia sempat menumpang, dengan alasan
         * yang waktu itu benar: HC Learn hanya punya satu URL callback. Alasan itu gugur
         * begitu mereka bersedia menyimpan alamat per modul, dan biaya menumpangnya
         * terlihat justru dari kode ini — setiap kiriman menembak satu tabel lagi hanya
         * untuk MENEBAK asalnya, ditambah satu cabang untuk token yang cocok di lebih
         * dari satu modul.
         *
         * Pintunya kini `pa.api.guard` di /api/v1/pa/third-party/penilaian/complete.
         * Token PA yang nyasar ke sini dijawab 404 — dan itu memang yang benar: alamat
         * ini bukan miliknya.
         */
        if ($sopIntegrasi && $tokenMilikLms) {
            // Tak seharusnya terjadi (token unik lintas modul). LMS dimenangkan
            // demi kompatibilitas, tapi wajib berteriak supaya ketahuan.
            Log::warning('CALLBACK_TOKEN_AMBIGU', [
                'token_hash' => hash('sha256', (string) $idUjianToken),
                'correlation_id' => $request->attributes->get('api_correlation_id'),
            ]);
        }

        if (! $sopIntegrasi && ! $tokenMilikLms) {
            return ResponseHelper::error('Id_Ujian_Token tidak dikenali di LMS maupun SOP', 404);
        }

        if ($sopIntegrasi && ! $tokenMilikLms) {
            $jejak['modul'] = 'SOP';

            // Milik SOP. Validasi kepemilikan & penulisan hasil dikerjakan
            // SopKuisCallbackService lewat controller — aturannya satu tempat.
            $request->attributes->set('sop_integrasi', $sopIntegrasi);
            // konsumsi nonce (anti-replay) + log — pakai registry N_LMS yang sama
            if ($tabrakan = $this->simpanNonce((int) $app->Id_Api_Aplikasi, $nonce)) {
                return $tabrakan;
            }
            Log::info('CALLBACK_MODUL', [
                'modul' => 'SOP',
                'token_hash' => hash('sha256', (string) $idUjianToken),
                'correlation_id' => $request->attributes->get('api_correlation_id'),
            ]);

            return null;
        }

        // ── Milik LMS: validasi penuh seperti semula ──
        $jejak['modul'] = 'LMS';

        $integrasi = DB::table('N_LMS_Penjadwalan_Integrasi as pi')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pi.Id_Penjadwalan_Det', '=', 'pdet.Id_Penjadwalan_Det')
            ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
            ->join('N_LMS_Penjadwalan as p', 'pd.Id_Penjadwalan', '=', 'p.Id_Penjadwalan')
            ->where('pi.Id_Penjadwalan_Integrasi', $idPenjadwalanIntegrasi)
            ->where('pi.Id_Ujian_Token', $idUjianToken)
            ->whereRaw('UPPER(LTRIM(RTRIM(p.Kode_Karyawan))) = ?', [$kodeKaryawan])
            ->select([
                'pi.*',
                'p.Id_Penjadwalan',
                'p.Kode_Karyawan',
                'pdet.Id_Materi_Det',
                'pd.Id_Penjadwalan_Detail',
            ])
            ->first();

        if (! $integrasi) {
            // Token JELAS milik LMS, tapi tak lolos penyaringan lain — pesannya
            // kini menyebut sebabnya, bukan lagi "kombinasi tidak valid" yang
            // membuat orang mengira tokennya salah.
            return ResponseHelper::error(
                'Token ujian LMS ini tidak cocok dengan Id_Penjadwalan_Integrasi / Kode_Karyawan yang dikirim',
                403,
            );
        }

        $karyawan = DB::table('Karyawan')
            ->whereRaw('UPPER(LTRIM(RTRIM(Kode_Karyawan))) = ?', [$kodeKaryawan])
            ->first();

        if (! $karyawan || empty($karyawan->UserID_Web)) {
            return ResponseHelper::error('Karyawan tidak memiliki akun web yang valid', 422);
        }

        $user = User::find($karyawan->UserID_Web);
        if (! $user) {
            return ResponseHelper::error('User web untuk karyawan tidak ditemukan', 404);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Menginjek data integrasi yang sudah didapatkan agar bisa dimanfaatkan langsung oleh Controller
        $request->attributes->set('lms_integrasi', $integrasi);
        $request->attributes->set('lms_kode_karyawan', $kodeKaryawan);
        $request->attributes->set('lms_user_id', $user->getAuthIdentifier());

        // 10. Terakhir, Simpan Nonce dan Log request tersebut karena sudah valid
        if ($tabrakan = $this->simpanNonce((int) $app->Id_Api_Aplikasi, $nonce)) {
            return $tabrakan;
        }

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
            DB::table('N_LMS_Api_Nonces')->insert([
                'Id_Api_Aplikasi' => $idAplikasi,
                'Nonce' => $nonce,
                'Expired_At' => now()->addMinutes(5),
            ]);
        } catch (QueryException $e) {
            if (! $this->tabrakanUnik($e)) {
                throw $e;
            }

            // Jawaban yang sama persis dengan pemeriksaan di langkah 5, supaya
            // pemanggil melihat satu perilaku — bukan dua, tergantung siapa yang
            // kebetulan menang balapan.
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
     * Satu baris bukti, lalu id-nya dikembalikan lewat header respons supaya
     * kedua pihak bisa menyebut baris yang sama saat berselisih.
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

        try {
            DB::table('N_LMS_Api_Logs')->insert([
                'Id_Api_Aplikasi' => $jejak['app'],
                'Endpoint' => $request->route()
                    ? '/'.ltrim($request->route()->uri(), '/')
                    : $request->getPathInfo(),
                'Http_Method' => strtoupper($request->method()),
                'Status_Code' => $status,
                'Signature_Valid' => $jejak['signature_valid'],
                'Correlation_Id' => $correlationId,
                // Endpoint-nya sama untuk LMS dan SOP. Asal hasil baru bisa
                // diketahui setelah token ditemukan di registry integrasi.
                'Modul' => $jejak['modul'],
                'Durasi_Ms' => (int) round((microtime(true) - $mulai) * 1000),
                // SHA-256 body mentah: kedua pihak bisa membandingkan hash tanpa
                // mempertukarkan payload yang mungkin membawa token.
                'Payload_Hash' => hash('sha256', (string) $request->getContent()),
                // Ditolak gerbang, atau sudah masuk aplikasi lalu gagal di sana.
                // Tanpa pembeda ini, 4xx dari pemeriksaan keamanan dan 4xx dari
                // logika bisnis terlihat sama persis.
                'Sumber_Error' => $status >= 400 ? $jejak['sumber'] : null,
                'Request_Ringkas' => app(ApiEvidenceSanitizer::class)
                    ->ringkasBody($request->getContent(), 1000),
                'Response_Ringkas' => app(ApiEvidenceSanitizer::class)
                    ->ringkasBody($response->getContent(), 2000),
                'Error_Message' => $error,
                'Created_At' => now(),
            ]);
        } catch (\Throwable $e) {
            // Kegagalan mencatat TIDAK boleh menggagalkan permintaan yang sudah
            // diproses. Ia jatuh ke log berkas supaya tetap ada jejaknya.
            Log::error('LMS_API_LOG_GAGAL', [
                'correlation_id' => $correlationId,
                // QueryException::getMessage() membawa SQL + binding; binding
                // itu termasuk body ringkas dan bisa memuat token. File log
                // cukup menyimpan kelas + kode error — correlation ID menjadi
                // jangkar investigasinya.
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
