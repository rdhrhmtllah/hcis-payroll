<?php

namespace App\Http\Controllers\KPI\MasterData\Scoring\Formula;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class FormulaKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/MasterData/Scoring/Formula/FormulaListPage');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('KPI_Master_Formula');

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Formula', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Id_Master_Formula', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Formula = Hashids::connection('custom')->encode($item->Id_Master_Formula);
                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                }

                // Simpan raw formula sebelum ditransform untuk display
                $item->Rumus_Raw = $item->Rumus;

                if (! empty($item->Rumus)) {
                    $rumus = preg_replace('/\[(.*?)\]/', '$1', $item->Rumus);
                    $mapping = [
                        'targetKpiEvo' => 'Target',
                        'actualKpiEvo' => 'Actual',
                    ];
                    foreach ($mapping as $key => $val) {
                        $rumus = str_replace($key, $val, $rumus);
                    }
                    $item->Rumus = $rumus;
                }

                // Deteksi apakah formula membutuhkan range (ada MIN/MAX/IF)
                $rawUpper = strtoupper($item->Rumus_Raw ?? '');
                $item->Has_Range = (
                    str_contains($rawUpper, 'MIN(') ||
                    str_contains($rawUpper, 'MAX(') ||
                    str_contains($rawUpper, 'IF(')
                ) ? true : false;

                $item->Min_Range = isset($item->Min_Range) ? (float) $item->Min_Range : null;
                $item->Max_Range = isset($item->Max_Range) ? (float) $item->Max_Range : null;

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
            Log::channel('FormulaKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getFormulaById(string $hashId)
    {
        try {
            $id = Hashids::connection('custom')->decode($hashId);
            if (empty($id)) {
                return ResponseHelper::error('ID Formula tidak valid', 400);
            }

            $formula = DB::table('KPI_Master_Formula')
                ->where('Id_Master_Formula', $id[0])
                ->first();

            if (! $formula) {
                return ResponseHelper::error('Data Formula tidak ditemukan', 404);
            }

            $formula->Id_Master_Formula = $hashId;
            if (! empty($formula->Id_User)) {
                $formula->Id_User = Hashids::connection('custom')->encode($formula->Id_User);
            }

            if (! empty($formula->Rumus)) {
                // Simpan format raw untuk keperluan edit di frontend
                $formula->Rumus_Raw = $formula->Rumus;

                // Buat versi display yang lebih mudah dibaca
                $rumus = preg_replace('/\[(.*?)\]/', '$1', $formula->Rumus);
                $mapping = [
                    'targetKpiEvo' => 'Target',
                    'actualKpiEvo' => 'Actual',
                ];
                $rumus = str_replace(array_keys($mapping), array_values($mapping), $rumus);
                $formula->Rumus = $rumus;
            }

            // Deteksi kebutuhan range dari raw formula
            $rawUpper = strtoupper($formula->Rumus_Raw ?? '');
            $formula->Has_Range = (
                str_contains($rawUpper, 'MIN(') ||
                str_contains($rawUpper, 'MAX(') ||
                str_contains($rawUpper, 'IF(')
            ) ? true : false;

            $formula->Min_Range = isset($formula->Min_Range) ? (float) $formula->Min_Range : null;
            $formula->Max_Range = isset($formula->Max_Range) ? (float) $formula->Max_Range : null;

            return ResponseHelper::success($formula, 'Data Formula berhasil ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('FormulaKpiController')->error('ERROR getFormulaById: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan pada server', 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return inertia('KPI/MasterData/Scoring/Formula/FormulaCreatePage');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Nama_Formula' => 'required|string|max:255',
            'Deskripsi' => 'required',
            'Rumus' => 'required|string',
            'Min_Range' => 'nullable|numeric',
            'Max_Range' => 'nullable|numeric',
        ]);

        $cekDuplikat = DB::table('KPI_Master_Formula')
            ->where('Nama_Formula', $request->Nama_Formula)
            ->whereRaw('CAST(Rumus AS NVARCHAR(MAX)) = ?', [$request->Rumus])
            ->exists();

        if ($cekDuplikat) {
            return ResponseHelper::error('Data sudah ada', 409);
        }

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Formula' => $request->Nama_Formula,
                'Deskripsi' => $request->Deskripsi,
                'Rumus' => $request->Rumus,
                'Min_Range' => $request->filled('Min_Range') ? (float) $request->Min_Range : null,
                'Max_Range' => $request->filled('Max_Range') ? (float) $request->Max_Range : null,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
                'Flag_Aktif' => 'Y',
            ];
            DB::table('KPI_Master_Formula')->insert($payload);
            DB::commit();

            return ResponseHelper::success($payload, 'Data berhasil disimpan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FormulaKpiController')->error('Error STORE: '.$e->getMessage());

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
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return inertia('KPI/MasterData/Scoring/Formula/FormulaEditPage', [
            'Id_Master_Formula' => $id,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $Id_Master_Formula)
    {
        try {
            $Id_Master_Formula = Hashids::connection('custom')->decode($Id_Master_Formula)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Tidak Valid.',
            ], 400);
        }

        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Nama_Formula' => 'required|string|max:255',
            'Deskripsi' => 'nullable|string',
            'Rumus' => 'required|string',
            'Min_Range' => 'nullable|numeric',
            'Max_Range' => 'nullable|numeric',
        ]);

        $cekDuplikat = DB::table('KPI_Master_Formula')
            ->where('Nama_Formula', $request->Nama_Formula)
            ->whereRaw('CAST(Rumus AS NVARCHAR(MAX)) = ?', [$request->Rumus])
            ->where('Id_Master_Formula', '!=', $Id_Master_Formula)
            ->exists();

        if ($cekDuplikat) {
            return ResponseHelper::error('Data sudah ada', 409);
        }

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Formula' => $request->Nama_Formula,
                'Deskripsi' => $request->Deskripsi,
                'Rumus' => $request->Rumus,
                'Min_Range' => $request->filled('Min_Range') ? (float) $request->Min_Range : null,
                'Max_Range' => $request->filled('Max_Range') ? (float) $request->Max_Range : null,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];

            DB::table('KPI_Master_Formula')
                ->where('Id_Master_Formula', $Id_Master_Formula)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success($payload, 'Data Berhasi Diperbaharui', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('FormulaKpiController')->error('Error UPDATE: '.$e->getMessage());

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

    public function toggleSwitch(Request $request)
    {
        $request->validate(['id' => 'required']);

        try {
            // Decode ID yang di-hash
            $decodedId = Hashids::connection('custom')->decode($request->id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID tidak valid.', 400);
            }
            $id = $decodedId[0];

            // Cari data penjadwalan
            $jadwal = DB::table('KPI_Master_Formula')
                ->where('Id_Master_Formula', $id)
                ->first();

            if (! $jadwal) {
                return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
            }

            $newStatus = ($jadwal->Flag_Aktif === 'Y') ? 'T' : 'Y';

            // Update database
            DB::table('KPI_Master_Formula')
                ->where('Id_Master_Formula', $id)
                ->update(['Flag_Aktif' => $newStatus]);

            return ResponseHelper::success(
                ['new_status' => $newStatus],
                'Status formula berhasil diubah.'
            );

        } catch (\Exception $e) {
            Log::channel('FormulaKpiController')->error('ERROR TOGGLESWITCH: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengubah status.', 500);
        }
    }
}
