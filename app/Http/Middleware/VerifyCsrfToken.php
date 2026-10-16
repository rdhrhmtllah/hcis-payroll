<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/handle-task',
        'scheduler-T4sk-RuNn3r-sEcr3t',
        // Backfill cap KPI di-hit dari Postman (tanpa session/XSRF cookie); diamankan
        // secret token di controller. Dikecualikan dari CSRF agar tidak 419.
        'api/v1/kpi-enforcement/backfill-cap',
        'api/v1/kpi-enforcement/backfill-cap-parameter',
        'api/v1/kpi-enforcement/backfill-cap-parameter/*',
    ];

    /**
     * Catat SEBAB setiap 419, bukan hanya kenyataan bahwa ia terjadi.
     *
     * 419 adalah kegagalan yang paling sulit ditelusuri di aplikasi ini: klien
     * menyegarkan token lalu mengulang sekali, dan bila masih gagal ia melempar
     * orangnya ke /login — yang karena sudah masuk memantul ke homepage. Yang
     * dilihat pemakainya cuma "tiba-tiba kembali ke beranda", tanpa satu pun
     * jejak yang menjelaskan apakah sesinya mati, cookienya hilang, atau
     * tokennya yang tertinggal.
     *
     * Yang dicatat sengaja hanya KEBERADAAN dan KECOCOKAN, tidak pernah nilai
     * tokennya: catatan yang memuat token yang sah sama saja dengan menyimpan
     * kunci di dalam log.
     */
    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            $sesi = $request->hasSession() ? $request->session() : null;

            Log::warning('CSRF_419', [
                'path' => $request->path(),
                'method' => $request->method(),
                // Sesi yang BARU dibuat pada permintaan ini adalah tanda paling
                // kuat bahwa sesi lamanya sudah mati — bukan tokennya yang keliru.
                'sesi_ada' => $sesi !== null,
                'sesi_baru' => $sesi !== null && ! $sesi->has('_token'),
                'sudah_login' => $request->user() !== null,
                'kirim_header_csrf' => $request->hasHeader('X-CSRF-TOKEN'),
                'kirim_header_xsrf' => $request->hasHeader('X-XSRF-TOKEN'),
                'kirim_token_body' => $request->input('_token') !== null,
                'punya_cookie_xsrf' => $request->cookie('XSRF-TOKEN') !== null,
                'punya_cookie_sesi' => $request->cookie(config('session.cookie')) !== null,
                // NAMA cookie mentah, sebelum EncryptCookies membuang yang gagal
                // didekripsi. Tanpa ini, "tidak punya cookie XSRF" bermakna ganda:
                // browsernya memang tidak mengirim, ATAU ia mengirim tetapi
                // nilainya ditolak dekripsi lalu dibuang diam-diam — dua sebab
                // yang perbaikannya bertolak belakang. Hanya nama, tidak pernah
                // nilainya.
                'nama_cookie_mentah' => array_keys($request->cookies->all()),
                // Permintaan yang tidak berasal dari axios/fetch aplikasi tidak
                // akan pernah membawa header XSRF, dan itu bukan kegagalan token.
                'ajax' => $request->ajax(),
                'minta_json' => $request->expectsJson(),
                'umur_sesi_menit' => config('session.lifetime'),
                'referer' => $request->headers->get('referer'),
            ]);

            throw $e;
        }
    }
}
