<?php

namespace App\Http\Controllers\KPI\MasterData\Unit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class SatuanKpiController extends Controller
{
    public function index()
    {
        return inertia('KPI/MasterData/Unit/satuan');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('KPI_Master_Satuan');

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Satuan', 'LIKE', "%{$search}%")
                        ->orWhere('Simbol_Satuan', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Id_Master_Satuan', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Satuan = Hashids::connection('custom')->encode($item->Id_Master_Satuan);
                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                }

                return $item;
            });

            return ResponseHelper::successWithPagination($encodedData, $page, $limit, $total, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('satuanKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

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
        // dd($request);
        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Nama_Satuan' => 'required',
            'Simbol_Satuan' => 'required',
        ]);

        $cekDuplikat = DB::table('KPI_Master_Satuan')
            ->where('Nama_Satuan', $request->Nama_Satuan)
            ->exists();

        if ($cekDuplikat) {
            return ResponseHelper::error('Data kategori sudah ada', 409);
        }

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Satuan' => $request->Nama_Satuan,
                'Simbol_Satuan' => $request->Simbol_Satuan,
                'Flag_Aktif' => 'Y',
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];
            DB::table('KPI_Master_Satuan')->insert($payload);
            DB::commit();

            return ResponseHelper::success($payload, 'Berhasil Menyimpan Data', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('satuanKpiController')->error('Error STORE: '.$e->getMessage());

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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            // decode Id_Master_Penjadwalan dari hashids
            $Id_Master_Satuan = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Tidak Valid.',
            ], 400);
        }

        $request->validate([
            'Nama_Satuan' => 'required',
            'Simbol_Satuan' => 'required',
        ]);

        // $cekDuplikat = DB::table('KPI_Master_Bsc_Kategori')
        // ->where('Nama_Satuan', $request->Nama_Satuan)
        // ->exists();

        // if ($cekDuplikat) {
        //     return ResponseHelper::error("Data kategori sudah ada", 409);
        // }

        $pengguna = Auth::user()->Id_Users;

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Satuan' => $request->Nama_Satuan,
                'Simbol_Satuan' => $request->Simbol_Satuan,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];

            DB::table('KPI_Master_Satuan')
                ->where('Id_Master_Satuan', $Id_Master_Satuan)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success('Data Berhasi Diperbaharui', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('KPI_Master_Satuan')->error('Error UPDATE: '.$e->getMessage());

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
