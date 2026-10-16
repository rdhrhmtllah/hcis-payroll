<?php

namespace App\Http\Controllers\KPI\Performance\Dashboard\Concerns;

use App\Helpers\ResponseHelper;
use App\Services\KPI\KpiDetailPeriodeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Ganti periode di dalam modal "Detail Employee Performance".
 *
 * Isi detailnya tetap dirakit recentKpiUserDetail() milik controller pemakai,
 * sehingga angkanya sama persis dengan detail yang dibuka dari daftar. Trait
 * ini hanya memilih transaksi rujukan untuk periode yang diminta dan menambah
 * strip navigasi 12 bulan. Siapa karyawannya — dan hak melihatnya — ditentukan
 * controller pemakai SEBELUM memanggil trait ini.
 */
trait DetailPerPeriode
{
    /**
     * @param  string  $periode  '01'..'12' | 'ytd' | 'full'
     */
    protected function susunDetailPerPeriode(int $idUser, int $tahun, string $periode)
    {
        $svc = app(KpiDetailPeriodeService::class);
        $periode = strtolower($periode);
        $mode = in_array($periode, ['ytd', 'full'], true) ? $periode : 'bulan';
        $bulan = $mode === 'bulan' ? (int) $periode : null;

        $label = match ($mode) {
            'ytd' => 'YTD '.$tahun,
            'full' => 'Full Year '.$tahun,
            default => Carbon::create($tahun, $bulan, 1)->locale('id')->translatedFormat('F Y'),
        };

        $navigasi = $svc->navigasi($idUser, $tahun);
        $infoPeriode = ['tahun' => $tahun, 'nilai' => $periode, 'mode' => $mode, 'label' => $label];

        $rujukan = $svc->rujukan($idUser, $tahun, $mode, $bulan);

        if (! $rujukan) {
            // Bukan galat: periode ini memang belum punya isi. Status bulannya
            // (belum diisi / tanpa target / menunggu / mendatang) diambil dari
            // strip yang sama supaya kalimat dan warna di layar konsisten.
            $statusBulan = $mode === 'bulan' ? ($navigasi[$bulan - 1]['status'] ?? null) : null;

            return ResponseHelper::success([
                'kosong' => true,
                'status_kosong' => $statusBulan ?? 'belum_ada_data',
                'periode' => $infoPeriode,
                'navigasi' => $navigasi,
                'header' => null,
                'details' => [],
            ], 'Belum ada data pada periode ini', 200);
        }

        $hash = fn (int $id) => Hashids::connection('custom')->encode($id);
        $isyear = match ($mode) {
            'ytd' => 'ytd',
            'full' => 'true',
            default => 'false',
        };

        $respons = $this->recentKpiUserDetail($hash($rujukan['id_pengajuan']), $hash($rujukan['id_update']), $isyear);
        $isi = $respons->getData(true);

        if (($isi['status'] ?? $respons->getStatusCode()) >= 400 || ! isset($isi['result'])) {
            return $respons;
        }

        return ResponseHelper::success($isi['result'] + [
            'kosong' => false,
            'periode' => $infoPeriode,
            'navigasi' => $navigasi,
            // Rujukan baru, dipakai modal untuk perpindahan berikutnya.
            'rujukan' => ['id_pengajuan' => $hash($rujukan['id_pengajuan']), 'id_update' => $hash($rujukan['id_update'])],
        ], $isi['message'] ?? 'Data KPI Detail Ditemukan!', 200);
    }

    /** Validasi bersama parameter periode. */
    protected function validasiPeriodeDetail(Request $request): array
    {
        return $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periode' => ['required', 'regex:/^(0[1-9]|1[0-2]|ytd|YTD|full|FULL)$/'],
        ]);
    }
}
