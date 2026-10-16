<?php

namespace App\Http\Controllers\SYSTEM\MasterData\KontakBantuan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use Illuminate\Http\Request;

/**
 * Kontak bantuan untuk Pusat Bantuan (topbar) — cukup `auth`: kontak resmi
 * yang sama untuk semua pengguna, hanya urutannya mengikuti halaman aktif.
 */
class KontakBantuanController extends Controller
{
    public function __construct(private KontakBantuanService $kontak) {}

    /** GET /bantuan/kontak?halaman=<Jenis_page> */
    public function pusatBantuan(Request $request)
    {
        $halaman = $request->query('halaman');
        $halaman = is_string($halaman) && preg_match('/^[A-Za-z0-9_]{1,100}$/', $halaman) ? $halaman : null;

        return ResponseHelper::success($this->kontak->kelompokPusatBantuan($halaman));
    }
}
