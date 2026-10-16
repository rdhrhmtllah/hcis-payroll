<?php

namespace App\Exceptions;

use App\Http\Middleware\CatatGalatServer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Session\TokenMismatchException;
use Inertia\Inertia;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        });

        $this->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('login'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 419);
            }

            return redirect()->guest(route('login'));
        });

        /*
         * Tautan spesifikasi kontrak yang tidak sah.
         *
         * Sengaja DIBATASI pada /api/contracts/*: pembacanya developer vendor
         * di luar organisasi, dan halaman 403 umum tidak memberi tahu apa pun
         * yang bisa mereka lakukan. Yang mereka butuhkan cuma satu kalimat —
         * mintalah tautan baru ke tim HCIS.
         *
         * Tidak menyebut alasan teknisnya (kedaluwarsa vs tanda tangan salah):
         * itu tidak mengubah tindakan mereka, dan membedakannya justru memberi
         * umpan balik gratis kepada siapa pun yang sedang menebak-nebak.
         */
        $this->renderable(function (InvalidSignatureException $e, Request $request) {
            if (! $request->is('api/contracts/*')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tautan spesifikasi tidak sah atau sudah tidak berlaku. Mintalah tautan baru kepada tim HCIS.',
                ], 403);
            }

            return response()->view('SYSTEM.Integration.outbound-contract-tautan-tidak-sah', [], 403);
        });

        /*
         * Penanda, bukan pelaporan.
         *
         * Galat yang sampai ke sini SUDAH dicatat lengkap dengan stack trace oleh
         * Laravel. Middleware CatatGalatServer mencatat setiap tanggapan 5xx yang
         * TIDAK punya laporan exception — dan tanpa penanda ini, setiap galat yang
         * jujur akan tercatat dua kali.
         *
         * Memulangkan null (bukan false) supaya pelaporan bawaan tetap berjalan;
         * hanya `false` yang menghentikannya (ReportableHandler::__invoke).
         */
        $this->reportable(function (Throwable $e) {
            $request = request();

            if ($request instanceof Request) {
                $request->attributes->set(CatatGalatServer::PENANDA_SUDAH_DICATAT, true);
            }

            return null;
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * Saat APP_DEBUG=false (produksi/staging), semua HttpException (403/404/500/503,
     * dll.) yang sebelumnya jatuh ke Blade `errors/*` dialihkan ke komponen Vue
     * `Pages/Error.vue` lewat Inertia, agar tampilan error konsisten dengan aplikasi.
     *
     * Saat APP_DEBUG=true (development) kita TIDAK mengintervensi: biarkan Ignition /
     * Blade default tampil penuh agar developer tetap dapat stacktrace.
     */
    public function render($request, Throwable $e)
    {
        // Biarkan renderable() (Authentication 401 & TokenMismatch 419) menang lebih
        // dulu — keduanya sudah jadi redirect ke login sebelum sampai sini.
        $response = parent::render($request, $e);

        // Dev: jangan sentuh Ignition/stacktrace.
        if (config('app.debug') === true) {
            return $response;
        }

        // Jangan Vue-kan response file (download) atau streamed.
        if ($response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
            || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse) {
            return $response;
        }

        // Request API/JSON tetap dapat response default (JSON), bukan halaman Vue.
        if ($request->expectsJson() || $request->is('api/*')) {
            return $response;
        }

        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        // Hanya alihkan error 4xx/5xx. Selain itu (redirect 3xx, 200) biarkan apa adanya.
        if ($status < 400) {
            return $response;
        }

        try {
            return Inertia::render('Error', [
                'status' => $status,
                'message' => $this->errorMessageFor($status),
            ])->toResponse($request)->setStatusCode($status);
        } catch (Throwable $inertiaError) {
            // Bila render Inertia gagal (mis. share() butuh DB yang sedang down),
            // jangan biarkan layar kosong — kembalikan response asli (Blade fallback).
            return $response;
        }
    }

    /**
     * Pesan generik (Bahasa Indonesia) per status. TIDAK memakai $e->getMessage()
     * agar detail/stacktrace tidak bocor ke pengguna di produksi.
     */
    private function errorMessageFor(int $status): string
    {
        return match ($status) {
            403 => 'Anda tidak memiliki izin untuk mengakses halaman ini.',
            404 => 'Maaf, halaman yang Anda cari tidak dapat ditemukan.',
            419 => 'Sesi Anda telah berakhir. Silakan muat ulang atau masuk kembali.',
            429 => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
            500 => 'Terjadi kesalahan pada server. Tim kami sedang menanganinya.',
            503 => 'Sistem sedang dalam perbaikan. Silakan kembali beberapa saat lagi.',
            default => 'Terjadi kesalahan. Silakan coba lagi.',
        };
    }
}
