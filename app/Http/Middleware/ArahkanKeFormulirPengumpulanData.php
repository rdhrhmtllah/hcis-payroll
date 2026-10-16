<?php

namespace App\Http\Middleware;

use App\Services\ESS\Employee\DataCollection\CakupanKampanye;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Selama terkunci, aplikasinya adalah formulir.
 *
 * Dulu keadaan ini dijaga sebuah MODAL: orang yang jatah tundanya habis tetap
 * dibawa ke halaman mana pun yang ia minta, lalu sebuah lapisan tanpa tombol
 * tutup, tanpa Esc, dan tanpa klik-luar menutupinya. Satu-satunya tombolnya
 * menuju halaman yang sedang ia buka, jadi begitu lapisan itu ikut tampil di
 * atas formulirnya sendiri, orangnya terkurung: tidak bisa menutup, tidak bisa
 * mengisi.
 *
 * Gerbangnya sekarang perpindahan halaman biasa. Tidak ada lapisan yang bisa
 * gagal menutup, karena tidak ada lapisan — dan keputusannya diambil server,
 * tempat statusnya memang hidup.
 *
 * Yang TIDAK dialihkan, dan alasannya:
 *   - formulir & endpoint-nya sendiri  → kalau ikut, orangnya berputar selamanya
 *   - layar admin pengumpulan data     → HR tetap harus bisa mengurus kampanye,
 *                                        termasuk membuka kunci orang lain
 *   - permintaan yang menunggu JSON    → penerimanya kode, bukan peramban yang
 *                                        berpindah; pengalihan mengirim HTML ke
 *                                        pemanggil yang menunggu data
 *   - selain GET                       → keluar (logout) dan kiriman formulir
 *                                        tidak boleh dibelokkan
 *   - lokasi di luar cakupan kampanye  → formulirnya memang belum dibuka
 *                                        untuknya; mengalihkannya ke sana
 *                                        berarti mengurungnya di layar 403
 */
class ArahkanKeFormulirPengumpulanData
{
    public function __construct(
        private KonfigurasiKampanye $konfigurasi,
        private StatusPengumpulanService $status,
        private CakupanKampanye $cakupan,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $this->terkunci($request)) {
            return $next($request);
        }

        return redirect()->route('pengumpulanData.index');
    }

    private function terkunci(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->expectsJson()) {
            return false;
        }

        if ($request->routeIs('pengumpulanData.*', 'adminPengumpulanData.*') || $request->is('api/*')) {
            return false;
        }

        if (! Auth::check() || ! $this->konfigurasi->aktif()) {
            return false;
        }

        $kode = (string) (Auth::user()?->karyawan?->Kode_Karyawan ?? '');

        if ($kode === '') {
            return false;
        }

        // Lokasi yang kampanyenya belum dibuka tidak boleh dikurung di dalam
        // formulir yang bahkan tidak terbuka untuknya.
        if (! $this->cakupan->mencakup($kode)) {
            return false;
        }

        $status = $this->status->untuk($kode);

        return $status['terkunci'] && ! $status['is_dikecualikan'];
    }
}
