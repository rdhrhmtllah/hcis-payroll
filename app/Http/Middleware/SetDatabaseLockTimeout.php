<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Batasi lama tunggu LOCK dan durasi QUERY untuk request HTTP interaktif.
 *
 * Tanpa ini, query yang ter-blok oleh lock akan menunggu hingga batas Cloud Run
 * (~120 dtk) sambil MENAHAN koneksi DB. Di bawah beban, koneksi yang ter-blok
 * menumpuk → pool koneksi SQL Server habis → koneksi baru gagal dibuka →
 * "SQLSTATE[HYT00] Login timeout expired" → situs down.
 *
 * `SET LOCK_TIMEOUT <ms>` membuat query yang menunggu lock lebih dari N ms GAGAL
 * CEPAT (error 1222) lalu MELEPAS koneksi — mengubah kaskade saturasi menjadi
 * kegagalan terisolasi yang bisa di-retry. Catatan penting:
 *  - Hanya memengaruhi query yang MENUNGGU lock, BUKAN query lambat biasa
 *    (report/scan non-lock) maupun transaksi yang MEMEGANG lock lama. Jadi aman
 *    untuk endpoint report yang panjang selama tidak ter-blok lock.
 *  - Hanya untuk request HTTP interaktif. Console command & queue worker tidak
 *    melewati middleware ini. Handler Cloud Tasks dikecualikan eksplisit agar
 *    job/batch panjang tidak ikut dibatasi.
 *
 * Nilai diatur via env DB_WEB_LOCK_TIMEOUT_MS (default 10000 ms) dan
 * DB_WEB_QUERY_TIMEOUT_S (default 60 dtk). Set 0/negatif untuk menonaktifkan.
 *
 * ── Kenapa dipasang MALAS ────────────────────────────────────────────────────
 *
 * Versi sebelumnya menjalankan `SET LOCK_TIMEOUT` langsung di handle(), yaitu
 * pada SETIAP request. Akibatnya request yang sama sekali tidak menyentuh
 * database tetap membuka koneksi — hanya untuk melindungi query yang tidak
 * pernah ada. Terukur pada 4 Sep 2026: /api/docs, /api/contracts/{slug}, dan
 * /api/docs/openapi.json jalur 401 masing-masing menjalankan TEPAT SATU query
 * per request, dan query itu adalah `SET LOCK_TIMEOUT` itu sendiri.
 *
 * Itu bukan sekadar boros: endpoint publik tanpa auth yang mengambil satu slot
 * koneksi per permintaan adalah penguat termurah bagi persis kaskade saturasi
 * yang middleware ini ada untuk mencegahnya.
 *
 * Sekarang penyetelannya digantung pada beforeExecuting() — dijalankan tepat
 * sebelum query PERTAMA yang sungguhan. Tidak ada query berarti tidak ada
 * koneksi; ada query berarti batasnya sudah terpasang sebelum query itu jalan.
 * Cakupannya identik dengan versi lama karena `Connection::run()` memanggil
 * beforeExecuting paling awal, dan seluruh jalur query aplikasi ini melewati
 * `run()` (tidak ada pemakaian PDO mentah).
 *
 * Bonus yang tidak dimiliki versi lama: penyetelan dilacak per objek PDO,
 * sehingga bila koneksi putus dan Laravel menyambung ulang di tengah request,
 * batasnya DIPASANG LAGI. LOCK_TIMEOUT bersifat per-sesi — versi lama
 * kehilangannya secara diam-diam setelah reconnect.
 */
class SetDatabaseLockTimeout
{
    public function handle(Request $request, Closure $next)
    {
        // Kecualikan handler Cloud Tasks (job/batch panjang) — jaring pengaman
        // bila suatu saat route-nya melewati grup ini.
        if ($request->is('handle-task', 'handle-task/*')) {
            return $next($request);
        }

        // 1) Batas tunggu LOCK: query yang MENUNGGU lock > N ms gagal cepat
        //    (error 1222) & lepas koneksi.
        $ms = (int) env('DB_WEB_LOCK_TIMEOUT_MS', 10000);

        // 2) Batas DURASI QUERY: menangkap query LAMBAT non-lock (scan/komputasi
        //    berat / runaway) yang TIDAK tertangkap LOCK_TIMEOUT. Query > N detik
        //    gagal (HYT00 "Query timeout expired") & MELEPAS koneksi, sebelum
        //    menahannya hingga batas Cloud Run (~120s) & menyaturasi pool.
        //    Nilai moderat (default 60s, di bawah 120s) → report normal aman;
        //    query nyasar dibunuh. Query yang MEMANG perlu lama sebaiknya
        //    dipindah ke job async.
        $detik = (int) env('DB_WEB_QUERY_TIMEOUT_S', 60);

        if ($ms > 0 || $detik > 0) {
            // DB::connection() hanya membuat objek Connection; PDO-nya masih
            // berupa closure yang belum dijalankan, jadi baris ini TIDAK membuka
            // koneksi.
            $this->pasangSaatKueriPertama(DB::connection(), $ms, $detik);
        }

        return $next($request);
    }

    /**
     * Gantungkan penyetelan pada query pertama yang sungguhan.
     */
    private function pasangSaatKueriPertama(Connection $koneksi, int $ms, int $detik): void
    {
        $terpasangPada = null;   // spl_object_id PDO yang sudah disetel
        $sedangMemasang = false;

        $koneksi->beforeExecuting(function () use ($koneksi, $ms, $detik, &$terpasangPada, &$sedangMemasang) {
            // unprepared() di bawah masuk lagi ke sini lewat Connection::run().
            if ($sedangMemasang) {
                return;
            }

            try {
                $pdo = $koneksi->getPdo();
            } catch (\Throwable $e) {
                // Koneksi gagal dibuka. Diam di sini: query yang memanggil kita
                // akan gagal sebentar lagi dengan pesan yang jauh lebih berguna.
                return;
            }

            // Objek PDO yang sama = sesi yang sama = penyetelan masih berlaku.
            // Berubah berarti Laravel baru menyambung ulang, dan LOCK_TIMEOUT
            // yang bersifat per-sesi ikut hilang bersama sesi lamanya.
            $idPdo = spl_object_id($pdo);
            if ($idPdo === $terpasangPada) {
                return;
            }

            $sedangMemasang = true;

            try {
                if ($detik > 0 && defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
                    $pdo->setAttribute(\PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $detik);
                }

                if ($ms > 0) {
                    // WAJIB unprepared (PDO::exec): SET LOCK_TIMEOUT lewat prepared
                    // statement (DB::statement) TIDAK mempersist ke query berikutnya
                    // di sesi ini (@@LOCK_TIMEOUT tetap -1). Nilai di-cast int (aman
                    // injeksi); SET tak menerima binding.
                    $koneksi->unprepared('SET LOCK_TIMEOUT '.$ms);
                }

                $terpasangPada = $idPdo;
            } catch (\Throwable $e) {
                // Jangan gagalkan request hanya karena SET gagal.
                Log::warning('Gagal memasang batas lock/kueri: '.$e->getMessage());
            } finally {
                $sedangMemasang = false;
            }
        });
    }
}
