<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jaring pengaman: setiap tanggapan 5xx meninggalkan jejak, siapa pun yang
 * membuatnya.
 *
 * ── Cacat yang ditutup ──────────────────────────────────────────────────────
 *
 * Diukur di repo ini pada 16 September 2026: 649 blok `catch` menangkap
 * exception lalu memulangkan 500 sendiri, sehingga exception-nya tidak pernah
 * sampai ke App\Exceptions\Handler dan tidak pernah dicatat otomatis. Dari
 * jumlah itu 123 blok tidak mencatat apa pun sama sekali — SYSTEM 82, ESS 27,
 * LMS 13, VISITOR 1 — dan 248 lainnya mencatat ke kanal bernama, yang berarti
 * masuk berkas modulnya sendiri dan bukan `laravel.log`.
 *
 * Akibatnya persis seperti yang dilaporkan dari production: layar menerima 500,
 * log tidak memuat apa pun, dan penyebab sebenarnya — misalnya sebuah kolom yang
 * belum ada di production sehingga SQL Server melempar "Invalid column name" —
 * tersembunyi di balik kalimat "Gagal mengambil data".
 *
 * ── Kenapa di middleware, bukan di tiap blok catch ──────────────────────────
 *
 * Memperbaiki 123 blok satu per satu menutup yang ada hari ini dan tidak
 * menutup blok ke-124 yang ditulis minggu depan. Di sini penjaganya satu, di
 * jalur yang HARUS dilewati setiap tanggapan, sehingga "lupa mencatat" berhenti
 * menjadi kemungkinan. Pencatatan per blok tetap berguna karena ia punya objek
 * exception-nya; keduanya saling melengkapi, bukan menggantikan.
 *
 * ── Kenapa tidak berisik ────────────────────────────────────────────────────
 *
 * Exception yang lolos ke Handler SUDAH dicatat lengkap dengan stack trace.
 * Handler menandai request-nya (`hcis.galat_dilaporkan`), dan middleware ini
 * melewatinya — tanpa itu setiap 500 yang jujur akan tercatat dua kali dan
 * membuat log yang justru lebih sulit dibaca.
 */
class CatatGalatServer
{
    /** Penanda yang dipasang App\Exceptions\Handler saat sebuah galat sudah dilaporkan. */
    public const PENANDA_SUDAH_DICATAT = 'hcis.galat_dilaporkan';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Dijalankan setelah tanggapan dikirim ke pengguna, jadi pencatatan ini
     * tidak menambah waktu tunggu siapa pun.
     */
    public function terminate(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();

        if ($status < 500) {
            return;
        }

        if ($request->attributes->get(self::PENANDA_SUDAH_DICATAT) === true) {
            return;
        }

        Log::error('HTTP 5xx tanpa laporan exception', [
            'status' => $status,
            'method' => $request->getMethod(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => optional($request->route())->getName(),
            'user_id' => optional($request->user())->Id_Users,
            'ip' => $request->ip(),
            // Pesan yang benar-benar dilihat pengguna. Inilah jembatan antara
            // keluhan "muncul Gagal mengambil data" dan baris log ini.
            'pesan_ke_pengguna' => $this->pesanTanggapan($response),
            'petunjuk' => 'Tanggapan ini dibuat kode aplikasi, bukan oleh Handler. '
                .'Exception aslinya tertelan blok catch di jalur ini — cari catch terdekat '
                .'pada rute di atas dan catat $e di sana.',
        ]);
    }

    /**
     * Ambil kalimat yang dikirim ke pengguna, sependek mungkin.
     *
     * Badan tanggapan tidak selalu JSON dan tidak selalu kecil: halaman HTML
     * error bisa puluhan kilobyte, dan menyalinnya ke log menukar satu masalah
     * dengan masalah lain. Hanya bentuk JSON yang dibaca, itu pun dipotong.
     */
    private function pesanTanggapan(Response $response): ?string
    {
        $tipe = (string) $response->headers->get('Content-Type');

        if (! str_contains($tipe, 'json')) {
            return null;
        }

        $isi = $response->getContent();

        if (! is_string($isi) || $isi === '' || strlen($isi) > 20000) {
            return null;
        }

        $data = json_decode($isi, true);

        if (! is_array($data)) {
            return null;
        }

        $pesan = $data['message'] ?? $data['pesan'] ?? null;

        return is_string($pesan) ? mb_substr($pesan, 0, 300) : null;
    }
}
