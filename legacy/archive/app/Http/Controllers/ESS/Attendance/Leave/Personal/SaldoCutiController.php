<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Personal;

use App\Http\Controllers\Controller;
use App\Models\SaldoCuti;

class SaldoCutiController extends Controller
{
    public function getSaldoCuti()
    {
        $saldo = SaldoCuti::with('jenisCuti')
            ->where('karyawan_id', auth()->user()->karyawan_id)
            ->get()
            ->map(function ($item) {
                return [
                    'jenis_cuti_id' => $item->jenis_cuti_id,
                    'nama_jenis' => $item->jenisCuti->nama,
                    'sisa' => $item->sisa,
                    'satuan' => $item->jenisCuti->satuan,
                ];
            });

        return response()->json($saldo);
    }

    public function getDetailSaldo()
    {
        $saldoDetail = SaldoCuti::with('jenisCuti')
            ->where('karyawan_id', auth()->user()->karyawan_id)
            ->get()
            ->map(function ($item) {
                $persentase = $item->saldo_awal > 0 ? (($item->saldo_awal - $item->sisa) / $item->saldo_awal) * 100 : 0;

                return [
                    'id' => $item->id,
                    'nama_jenis' => $item->jenisCuti->nama,
                    'kuota' => $item->saldo_awal,
                    'digunakan' => $item->saldo_awal - $item->sisa,
                    'sisa' => $item->sisa,
                    'satuan' => $item->jenisCuti->satuan,
                    'persentase' => round($persentase, 2),
                    'expired_date' => $this->calculateExpiryDate($item),
                ];
            });

        return response()->json($saldoDetail);
    }

    private function calculateExpiryDate($saldoCuti)
    {
        if ($saldoCuti->jenisCuti->masa_berlaku_bulan) {
            return now()->addMonths($saldoCuti->jenisCuti->masa_berlaku_bulan)->endOfYear()->format('Y-m-d');
        }

        return now()->endOfYear()->format('Y-m-d');
    }
}
