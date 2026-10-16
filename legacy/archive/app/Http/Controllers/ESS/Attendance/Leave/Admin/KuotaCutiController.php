<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\SaldoCuti;
use Illuminate\Http\Request;

class KuotaCutiController extends Controller
{
    public function index(Request $request)
    {
        $query = SaldoCuti::with(['karyawan', 'jenisCuti']);

        if ($request->search) {
            $query->whereHas('karyawan', function ($q) use ($request) {
                $q->where('nama', 'like', '%'.$request->search.'%')
                    ->orWhere('kode_karyawan', 'like', '%'.$request->search.'%');
            });
        }

        $kuota = $query->get()->groupBy('karyawan_id');

        $result = [];
        foreach ($kuota as $karyawanId => $saldoList) {
            $karyawan = $saldoList->first()->karyawan;
            $result[] = [
                'kode_karyawan' => $karyawan->kode_karyawan,
                'nama_karyawan' => $karyawan->nama,
                'sisa_cuti_tahunan' => $saldoList->where('jenisCuti.nama', 'Cuti Tahunan')->first()->sisa ?? 0,
                'sisa_cuti_lainnya' => $saldoList->where('jenisCuti.nama', '!=', 'Cuti Tahunan')->sum('sisa'),
                'detail' => $saldoList,
            ];
        }

        return response()->json($result);
    }

    public function adjustKuota(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => 'required|exists:karyawan,kode_karyawan',
            'tipe_cuti' => 'required|exists:jenis_cuti,id',
            'jumlah' => 'required|integer',
            'note' => 'required|string|max:500',
        ]);

        $karyawan = Karyawan::where('kode_karyawan', $validated['kode_karyawan'])->first();

        $saldo = SaldoCuti::where('karyawan_id', $karyawan->id)
            ->where('jenis_cuti_id', $validated['tipe_cuti'])
            ->first();

        if (! $saldo) {
            // Create new saldo record if doesn't exist
            $saldo = SaldoCuti::create([
                'karyawan_id' => $karyawan->id,
                'jenis_cuti_id' => $validated['tipe_cuti'],
                'saldo_awal' => $validated['jumlah'],
                'sisa' => $validated['jumlah'],
                'tahun' => now()->year,
            ]);
        } else {
            // Adjust existing saldo
            $saldo->increment('sisa', $validated['jumlah']);
            $saldo->increment('saldo_awal', $validated['jumlah']);
        }

        // Log the adjustment
        \App\Models\AdjustmentCuti::create([
            'karyawan_id' => $karyawan->id,
            'jenis_cuti_id' => $validated['tipe_cuti'],
            'jumlah' => $validated['jumlah'],
            'keterangan' => $validated['note'],
            'adjusted_by' => auth()->id(),
            'adjusted_at' => now(),
        ]);

        return response()->json(['message' => 'Kuota cuti berhasil disesuaikan']);
    }

    public function searchKaryawan(Request $request)
    {
        $query = Karyawan::select('id', 'kode_karyawan', 'nama');

        if ($request->q) {
            $query->where('nama', 'like', '%'.$request->q.'%')
                ->orWhere('kode_karyawan', 'like', '%'.$request->q.'%');
        }

        $karyawan = $query->limit(10)->get();

        return response()->json($karyawan);
    }
}
