<?php

namespace App\Http\Controllers\SYSTEM\Application\Panduan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Panduan\PanduanService;
use Illuminate\Http\Request;

/**
 * Panduan untuk pengguna yang sedang membuka sebuah halaman.
 *
 * Hanya `auth`: hak lihatnya diturunkan dari hak membuka HALAMAN yang dituju
 * (dan role pembatas panduan bila diatur), diperiksa di PanduanService per permintaan.
 */
class PanduanPenggunaController extends Controller
{
    public function __construct(private PanduanService $panduan) {}

    /** GET /panduan/halaman/{jenisPage} */
    public function untukHalaman(Request $request, string $jenisPage)
    {
        return ResponseHelper::success($this->panduan->untukHalaman($request->user(), $jenisPage));
    }

    /** GET /panduan/{id}/berkas — PDF disajikan inline, bergerbang per permintaan. */
    public function berkas(Request $request, int $id)
    {
        if (! $this->panduan->bolehBukaBerkas($request->user(), $id)) {
            return ResponseHelper::errorWithCode('Panduan tidak tersedia untuk Anda.', 'SYSTEM_PANDUAN_FORBIDDEN', 403);
        }

        $berkas = $this->panduan->berkas($id);
        if (! $berkas) {
            return ResponseHelper::errorWithCode('Panduan tidak ditemukan.', 'SYSTEM_PANDUAN_NOT_FOUND', 404);
        }

        return $this->panduan->sajikanPdf($berkas);
    }
}
