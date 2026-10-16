<?php

namespace App\Http\Controllers\SYSTEM\Application\Panduan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Panduan\PanduanService;
use App\Services\SYSTEM\Application\Panduan\PanduanVersiService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Versi berkas panduan (DDL-2026-149) — khusus System Admin.
 */
class PanduanVersiController extends Controller
{
    public function __construct(
        private PanduanVersiService $versi,
        private PanduanService $panduan,
    ) {}

    public function index(int $id)
    {
        return $this->jalankan(fn () => $this->versi->daftar($id));
    }

    public function store(Request $request, int $id)
    {
        $data = $request->validate([
            'berkas' => ['required', 'file', 'mimes:pdf', 'max:'.PanduanService::MAKS_KB],
            'label' => 'nullable|string|max:30',
            'catatan' => 'nullable|string|max:1000',
            'terbitkan' => 'nullable|boolean',
        ], [], ['berkas' => 'berkas PDF', 'label' => 'label versi', 'catatan' => 'catatan perubahan']);

        $terbitkan = $request->boolean('terbitkan');

        return $this->jalankan(
            fn () => $this->versi->unggahVersi($id, $request->file('berkas'), $data['label'] ?? null, $data['catatan'] ?? null, $terbitkan),
            $terbitkan ? 'Versi baru diterbitkan.' : 'Draf versi disimpan.',
            201,
        );
    }

    /** Terbitkan draf atau kembalikan versi lama. */
    public function aktifkan(int $id, int $idVersi)
    {
        return $this->jalankan(fn () => $this->versi->aktifkan($id, $idVersi), 'Versi aktif diperbarui.');
    }

    public function destroy(int $id, int $idVersi)
    {
        return $this->jalankan(fn () => $this->versi->hapusDraf($id, $idVersi), 'Draf dihapus.');
    }

    /** Pratinjau berkas satu versi (termasuk draf). */
    public function berkas(int $id, int $idVersi)
    {
        $berkas = $this->versi->berkas($id, $idVersi);
        if (! $berkas) {
            return ResponseHelper::errorWithCode('Versi tidak ditemukan.', 'SYSTEM_PANDUAN_NOT_FOUND', 404);
        }

        return $this->panduan->sajikanPdf($berkas);
    }

    private function jalankan(callable $kerja, string $pesan = 'Berhasil', int $status = 200)
    {
        try {
            return ResponseHelper::success($kerja(), $pesan, $status);
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'SYSTEM_PANDUAN_INVALID', 422);
        } catch (\Throwable $e) {
            Log::error('Versi panduan gagal', ['error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Terjadi kesalahan saat memproses versi panduan.', 'SYSTEM_PANDUAN_FAILED', 500);
        }
    }
}
