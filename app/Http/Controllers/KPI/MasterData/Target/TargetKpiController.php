<?php

namespace App\Http\Controllers\KPI\MasterData\Target;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class TargetKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/MasterData/Target/masterTarget');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('KPI_Master_Target');

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Target', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Id_Master_Target', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Target = Hashids::connection('custom')->encode($item->Id_Master_Target);
                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                }

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
            Log::channel('TargetKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

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

        $request->validate([
            '*.Kode_Target' => ['required', 'string', 'size:1', 'regex:/^[A-Za-z]$/'],
            '*.Nama_Target' => 'required|string|max:255',
            '*.Deskripsi' => 'nullable|string',
        ]);

        $forms = $request->all();

        DB::beginTransaction();

        try {
            $dataToInsert = [];

            foreach ($forms as $form) {
                $kodeTarget = strtoupper($form['Kode_Target']);
                $namaTarget = trim($form['Nama_Target']);

                $cekDuplikat = DB::table('KPI_Master_Target')
                    ->where('Nama_Target', $namaTarget)
                    ->exists();

                if ($cekDuplikat) {
                    DB::rollBack();

                    return ResponseHelper::error("Data sudah ada untuk kegiatan: {$namaTarget}", 409);
                }

                $dataToInsert[] = [
                    'Kode_Target' => $kodeTarget,
                    'Nama_Target' => $namaTarget,
                    'Deskripsi' => $form['Deskripsi'] ?? null,
                    'Id_User' => $pengguna,
                    'Jam' => date('H:i:s'),
                    'Flag_Aktif' => 'Y',
                    'Tanggal' => date('Y-m-d'),
                ];
            }

            DB::table('KPI_Master_Target')->insert($dataToInsert);
            DB::commit();

            return ResponseHelper::success($dataToInsert, 'Data berhasil disimpan', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('TargetKpiController')->error('Error STORE MULTIPLE: '.$e->getMessage());

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
    public function update(Request $request, $Id_Master_Target)
    {
        try {
            $Id_Master_Target = Hashids::connection('custom')->decode($Id_Master_Target)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Tidak Valid.',
            ], 400);
        }

        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Nama_Target' => 'required|string|max:255',
            'Deskripsi' => 'nullable|string',
        ]);

        $cekDuplikat = DB::table('KPI_Master_Target')
            ->where('Nama_Target ', $request->Nama_Target)
            ->where('Id_Master_Target', '!=', $Id_Master_Target)
            ->exists();

        if ($cekDuplikat) {
            return ResponseHelper::error('Data sudah ada untuk kegiatan ini', 409);
        }

        DB::beginTransaction();

        try {

            $payload = [
                'Nama_Target' => $request->Nama_Target,
                'Deskripsi' => $request->Deskripsi,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];

            DB::table('KPI_Master_Target')
                ->where('Id_Master_Target', $Id_Master_Target)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success($payload, 'Data berhasil Diperbaharui', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('TargetKpiController')->error('Error UPDATE: '.$e->getMessage());

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
