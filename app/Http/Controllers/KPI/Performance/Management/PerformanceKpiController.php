<?php

namespace App\Http\Controllers\KPI\Performance\Management;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class PerformanceKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/Performance/Management/masterPerformance');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('KPI_Master_Performance');

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Range_Awal ', 'LIKE', "%{$search}%")
                        ->orWhere('Range_Akhir ', 'LIKE', "%{$search}%")
                        ->orWhere('Keterangan', 'LIKE', "%{$search}%")
                        ->orWhere('Warna', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Range_Awal', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Performance = Hashids::connection('custom')->encode($item->Id_Master_Performance);
                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                }
                $item->Range_Awal = (float) $item->Range_Awal;
                $item->Range_Akhir = (float) $item->Range_Akhir;

                return $item;
            });

            return ResponseHelper::successWithPagination(
                $encodedData,
                $page,
                $limit,
                $total,
                'Data Ditemukan',
                200
            );
        } catch (\Exception $e) {
            Log::channel('PerformanceKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        // Pastikan request berisi array forms
        $request->validate([
            '*.Range_Awal' => 'required|numeric',
            '*.Range_Akhir' => 'required|numeric|gte:*.Range_Awal',
            '*.Keterangan' => 'required|string',
            '*.Warna' => 'required|string',
            '*.Kode' => 'required|string',
        ], [
            '*.Range_Akhir.gte' => 'Range Akhir tidak boleh lebih kecil dari Range Awal.',
        ]);

        $forms = $request->all();

        DB::beginTransaction();
        try {
            $dataToInsert = [];

            foreach ($forms as $form) {
                $rangeAwal = $form['Range_Awal'];
                $rangeAkhir = $form['Range_Akhir'];

                // Cek overlap setiap item
                $cekOverlap = DB::table('KPI_Master_Performance')
                    ->where(function ($query) use ($rangeAwal, $rangeAkhir) {
                        $query->where('Range_Awal', '<=', $rangeAkhir)
                            ->where('Range_Akhir', '>=', $rangeAwal);
                    })
                    ->exists();

                if ($cekOverlap) {
                    DB::rollBack();

                    return ResponseHelper::error(
                        "Rentang nilai ($rangeAwal - $rangeAkhir) tumpang tindih dengan data yang sudah ada.",
                        409
                    );
                }

                $dataToInsert[] = [
                    'Kode' => $form['Kode'],
                    'Range_Awal' => $form['Range_Awal'],
                    'Range_Akhir' => $form['Range_Akhir'],
                    'Keterangan' => $form['Keterangan'],
                    'Warna' => $form['Warna'],
                    'Id_User' => $pengguna,
                    'Jam' => date('H:i:s'),
                    'Tanggal' => date('Y-m-d'),
                ];
            }

            // Simpan sekaligus
            DB::table('KPI_Master_Performance')->insert($dataToInsert);
            DB::commit();

            return ResponseHelper::success($dataToInsert, 'Data berhasil disimpan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PerformanceKpiController')->error('Error STORE MULTIPLE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat menyimpan data', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function update(Request $request, $Id_Master_Performance)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($Id_Master_Performance)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
        }

        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Range_Awal' => 'required|numeric',
            'Range_Akhir' => 'required|numeric|gte:Range_Awal',
            'Keterangan' => 'required|string',
            'Warna' => 'required|string',
        ], [
            'Range_Akhir.gte' => 'Range Akhir tidak boleh lebih kecil dari Range Awal.',
        ]);

        $rangeAwal = $request->Range_Awal;
        $rangeAkhir = $request->Range_Akhir;

        $cekOverlap = DB::table('KPI_Master_Performance')
            ->where(function ($query) use ($rangeAwal, $rangeAkhir) {
                $query->where('Range_Awal', '<=', $rangeAkhir)
                    ->where('Range_Akhir', '>=', $rangeAwal);
            })
            ->where('Id_Master_Performance', '!=', $decodedId)
            ->exists();

        if ($cekOverlap) {
            return ResponseHelper::error('Rentang nilai yang dimasukkan tumpang tindih dengan data yang sudah ada.', 409);
        }

        DB::beginTransaction();

        try {
            $payload = [
                'Range_Awal' => $request->Range_Awal,
                'Range_Akhir' => $request->Range_Akhir,
                'Keterangan' => $request->Keterangan,
                'Warna' => $request->Warna,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];
            DB::table('KPI_Master_Performance')
                ->where('Id_Master_Performance', $decodedId)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success($payload, 'Data berhasil Diperbaharui', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PerformanceKpiController')->error('Error UPDATE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
