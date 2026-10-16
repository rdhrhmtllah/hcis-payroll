<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Perekam diagnostik per-request. Tujuan: membedakan request lambat karena
 * "query lambat / ke-lock" vs "gagal mendapatkan koneksi DB" (Login Timeout).
 *
 * Overhead: DB::listen = closure murah per query; conn-acquire diukur via event
 * query PERTAMA (TANPA memaksa koneksi baru). Catatan HANYA ditulis saat request
 * lambat (>= slow_ms) atau 5xx → jalur normal ~nol. Semua di terminate() memakai
 * data IN-MEMORY (tak menyentuh DB) agar tetap tercatat walau DB sedang down.
 *
 * Pasang paling awal di grup 'web'/'api' agar DB::listen menangkap query pertama
 * (mis. baca session) sebagai penanda selesainya akuisisi koneksi.
 */
class RequestDiagnostics
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('request-diagnostics.enabled', false)) {
            return $next($request);
        }

        $state = new \stdClass;
        $state->t0 = microtime(true);
        $state->endedAt = null;
        $state->firstQueryAt = null;
        $state->queryCount = 0;
        $state->queryMs = 0.0;
        $state->slowestMs = 0.0;
        $state->slowestSql = null;

        $captureSql = (bool) config('request-diagnostics.capture_sql', true);

        // Perekaman per-rute (dev): ringkasan disusun di MEMORI selagi
        // permintaan berjalan, lalu ditulis sekali di terminate(). Menulis per
        // kueri akan menghasilkan puluhan tulisan untuk satu klik — dan
        // pemantau yang seberisik itu ikut mengubah yang diukurnya.
        $state->rekam = app(PerekamKueriPerRute::class)->sedangMerekam();
        $state->perSql = [];

        DB::listen(function ($q) use ($state, $captureSql) {
            if ($state->firstQueryAt === null) {
                $state->firstQueryAt = microtime(true);
            }
            $state->queryCount++;
            $t = (float) $q->time; // ms
            $state->queryMs += $t;
            if ($t > $state->slowestMs) {
                $state->slowestMs = $t;
                // $q->sql = SQL parameterized (bindings TERPISAH) → tanpa PII.
                $state->slowestSql = $captureSql ? $q->sql : null;
            }

            if ($state->rekam && ! $this->kueriMilikPemantau($q->sql)) {
                // Dikelompokkan menurut SQL berparameter, jadi 50 kali kueri
                // yang sama menyatu jadi satu baris ber-n=50 — bentuk N+1
                // terbaca sekali lihat.
                $kunci = substr($q->sql, 0, 400);
                $state->perSql[$kunci] ??= ['n' => 0, 'ms' => 0.0];
                $state->perSql[$kunci]['n']++;
                $state->perSql[$kunci]['ms'] += $t;
            }
        });

        // Layer 2 (OFF default): tandai sesi DB dgn trace utk atribusi lock.
        if ((bool) config('request-diagnostics.tag_session', false)) {
            $this->tagSession($request);
        }

        $request->attributes->set('_reqdiag', $state);

        $response = $next($request);
        $state->endedAt = microtime(true);

        return $response;
    }

    /**
     * terminate() jalan SETELAH response dikirim → tak menambah latency user.
     * Hanya memakai data in-memory (tak query DB) supaya insiden saat DB down
     * tetap tercatat ke stderr.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! (bool) config('request-diagnostics.enabled', false)) {
                return;
            }

            $state = $request->attributes->get('_reqdiag');
            if (! $state instanceof \stdClass) {
                return;
            }

            $status = $response->getStatusCode();
            $end = $state->endedAt ?? microtime(true);
            $totalMs = (int) round(($end - $state->t0) * 1000);
            $slowMs = (int) config('request-diagnostics.slow_ms', 3000);

            // Perekaman sesi uji ditulis SEBELUM gerbang "request menarik" di
            // bawah. Gerbang itu benar untuk tabel diagnostik — di produksi
            // hanya insiden yang layak disimpan — tetapi salah total untuk
            // sesi uji: yang sedang dicari justru permintaan NORMAL, dan
            // hampir tak ada di antaranya yang lebih lambat dari 3 detik.
            if (($state->rekam ?? false) && $state->perSql !== []) {
                app(PerekamKueriPerRute::class)->catat([
                    'rute' => $request->method().' '.'/'.ltrim(substr($request->path(), 0, 200), '/'),
                    'kueri' => $state->queryCount,
                    'ms' => round($state->queryMs, 1),
                    'total_ms' => $totalMs,
                    'status' => $status,
                    'sql' => $state->perSql,
                ]);
            }

            // GATE: hanya rekam request "menarik" (lambat / error server).
            if ($status < 500 && $totalMs < $slowMs) {
                return;
            }

            // null = tak ada query DB sama sekali (indikasi kuat: gagal koneksi
            // / hang sebelum sempat query). Bila ada, ini ≈ waktu akuisisi koneksi.
            $connAcquireMs = $state->firstQueryAt !== null
                ? (int) round(($state->firstQueryAt - $state->t0) * 1000)
                : null;

            $row = [
                'Trace_Id' => $this->traceId($request),
                'Kode_Karyawan' => $this->userCode(),
                'Path' => substr($request->path(), 0, 400),
                'Method' => $request->method(),
                'Http_Status' => $status,
                'Total_Ms' => $totalMs,
                'Conn_Acquire_Ms' => $connAcquireMs,
                'Db_Query_Count' => $state->queryCount,
                'Db_Query_Ms' => (int) round($state->queryMs),
                'Slowest_Query_Ms' => (int) round($state->slowestMs),
                'Slowest_Query_Sql' => $state->slowestSql !== null ? substr($state->slowestSql, 0, 2000) : null,
                'Created_At' => now()->format('Y-m-d H:i:s'),
            ];

            // Sink 1: stderr (selalu jalan, selamat saat DB down).
            foreach ((array) config('request-diagnostics.channels', ['stderr']) as $channel) {
                Log::channel($channel)->warning('REQ_DIAG', $row);
            }

            // Sink 2: tabel SQL (di-try/catch; bila DB down, stderr sudah dapat).
            if ((bool) config('request-diagnostics.table', true)) {
                try {
                    DB::table('N_HRIS_Request_Diagnostics')->insert($row);
                } catch (\Throwable $e) {
                    // Sengaja diam: gagal tulis diag tak boleh menjatuhkan apa pun.
                }
            }
        } catch (\Throwable $e) {
            // Never block business flow.
        }
    }

    private function traceId(Request $request): string
    {
        $raw = (string) ($request->headers->get('X-Cloud-Trace-Context') ?? '');
        if ($raw !== '') {
            return substr((string) strtok($raw, '/'), 0, 64);
        }

        // Di luar Cloud Run header itu tidak ada, dan sebelum 4 Sep 2026 artinya
        // trace SELALU kosong — sehingga tagSession() keluar lebih awal dan
        // Layer 2 tidak pernah menandai apa pun di dev, meski sakelarnya menyala.
        // Kegagalannya diam: tidak ada galat, hanya atribusi yang tak pernah ada.
        //
        // Id dibuat sekali per permintaan dan dititipkan pada objek Request-nya
        // supaya tanda pada sesi DB dan baris di tabel memakai id yang SAMA.
        if (! $request->attributes->has('diagnostik_trace')) {
            $request->attributes->set('diagnostik_trace', 'dev-'.bin2hex(random_bytes(8)));
        }

        return (string) $request->attributes->get('diagnostik_trace');
    }

    /**
     * Kode user dari data IN-MEMORY saja (kolom user yg sudah ter-load) — JANGAN
     * akses relasi ->karyawan (memicu query; bisa gagal & menghapus catatan saat
     * DB down).
     */
    private function userCode(): ?string
    {
        if (! Auth::check()) {
            return null;
        }
        $user = Auth::user();

        return (string) ($user->Kode_Users ?? $user->Username ?? $user->Id_Users ?? Auth::id() ?? '') ?: null;
    }

    /**
     * Kueri yang dijalankan oleh alat diagnostik ITU SENDIRI.
     *
     * SET CONTEXT_INFO dari Layer 2 berjalan pada SETIAP permintaan, jadi tanpa
     * penyaring ini ia memuncaki tiap rekaman — dan yang paling merugikan,
     * biayanya nyata: satu round-trip penuh, terukur 166-276 ms ke basis data
     * dev yang remote. Pemantau yang memuncaki daftarnya sendiri hanya membuat
     * orang mengejar bayangannya.
     */
    private function kueriMilikPemantau(string $sql): bool
    {
        return str_starts_with(ltrim($sql), 'SET CONTEXT_INFO')
            || str_contains($sql, 'dm_exec_')
            || str_contains($sql, 'dm_tran_locks')
            || str_contains($sql, 'dm_os_waiting');
    }

    private function tagSession(Request $request): void
    {
        try {
            $trace = $this->traceId($request);
            if ($trace === '') {
                return;
            }
            // context_info varbinary(128). Yang disimpan BUKAN hanya trace-nya,
            // melainkan trace + rutenya sekaligus.
            //
            // Alasannya soal WAKTU. Baris N_HRIS_Request_Diagnostics baru ditulis
            // di terminate(), yaitu SESUDAH respons terkirim. Padahal saat sebuah
            // permintaan sedang menahan lock, ia belum selesai — jadi pemantau
            // yang mencocokkan trace ke tabel itu tidak akan menemukan apa pun,
            // tepat pada permintaan yang paling ingin dikenali. Menitipkan
            // rutenya di dalam tanda membuat atribusi bekerja SELAGI permintaan
            // masih berjalan, tanpa kueri tambahan sama sekali.
            $rute = strtoupper($request->getMethod()).' '.$request->getPathInfo();
            $muatan = substr($trace, 0, 24).'|'.substr($rute, 0, 100);
            DB::statement('SET CONTEXT_INFO 0x'.bin2hex(substr($muatan, 0, 128)));
        } catch (\Throwable $e) {
            // best-effort; abaikan.
        }
    }
}
