<?php

namespace App\Http\Controllers\KPI\Monitoring\Enforcement;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dua pintu koreksi nilai target.
 *
 * TANPA aturan bisnis: seluruh keputusan kelayakan dan penerapan tinggal di
 * KpiTargetKoreksiService, supaya layar (yang mematikan sel) dan simpan (yang
 * menolak) tidak pernah bisa berselisih.
 */
class KpiTargetKoreksiController extends Controller
{
    public function __construct(private KpiTargetKoreksiService $svc) {}

    public function kelayakan(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|max:500',
            'ids.*' => 'required|integer',
        ]);

        return ResponseHelper::success(
            $this->svc->periksaKelayakan($data['ids']),
            'Kelayakan dinilai',
            200,
        );
    }

    public function koreksi(Request $request)
    {
        $data = $request->validate([
            'sebab' => 'required|string|max:500',
            'perubahan' => 'required|array|min:1|max:500',
            'perubahan.*.id_pengajuan_det' => 'required|integer',
            'perubahan.*.nilai_baru' => 'required|numeric',
        ]);

        $bolehSebagian = $request->boolean('sebagian');

        // Peringatan menyimpang diperiksa SEBELUM menerapkan, dan menuntut
        // pengakuan sekali. Ia tidak menolak nilainya — nilai yang benar tetap
        // milik pemilik prosesnya — tetapi peringatan yang hanya muncul SESUDAH
        // koreksi terpasang tidak menolong siapa pun, dan itulah yang terjadi
        // selama peringatan ini belum tersambung ke mana-mana.
        if (! $request->boolean('abaikan_peringatan')) {
            // Sekaligus, bukan satu per satu: muatan boleh memuat 500 perubahan,
            // dan bentuk per-target mengeluarkan 1.000 kueri.
            $nilaiBaru = collect($data['perubahan'])
                ->mapWithKeys(fn ($p) => [(int) $p['id_pengajuan_det'] => (float) $p['nilai_baru']]);

            $peringatan = collect($this->svc->periksaMenyimpangBanyak($data['perubahan']))
                ->map(fn ($cek, $id) => [
                    'id_pengajuan_det' => (int) $id,
                    'nilai_baru' => $nilaiBaru->get((int) $id),
                    'median' => round($cek['median'], 2),
                    'rasio' => round($cek['rasio'], 2),
                ])
                ->values()
                ->all();

            if ($peringatan !== []) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Nilai baru menyimpang jauh dari periode lain pada parameter yang sama',
                    'result' => ['peringatan' => $peringatan, 'diterapkan' => [], 'ditolak' => []],
                ], 422);
            }
        }

        $hasil = $this->svc->terapkan(
            $data['perubahan'],
            $data['sebab'],
            (int) Auth::id(),
            $bolehSebagian,
        );

        // Ada yang ditolak sementara pemanggil tidak meminta hasil separuh:
        // laporkan sebagai penolakan, jangan diam-diam menerapkan sebagian.
        //
        // response()->json langsung, bukan ResponseHelper::error(): balasan ini
        // HARUS membawa daftar penolakannya, dan error() hanya memulangkan
        // success/status/message tanpa tempat untuk hasil.
        if ($hasil['ditolak'] !== [] && ! $bolehSebagian) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Sebagian target tidak layak dikoreksi',
                'result' => $hasil,
            ], 422);
        }

        return ResponseHelper::success($hasil, 'Koreksi diterapkan', 200);
    }
}
