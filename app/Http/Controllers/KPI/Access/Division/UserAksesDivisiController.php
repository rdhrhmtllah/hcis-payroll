<?php

namespace App\Http\Controllers\KPI\Access\Division;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class UserAksesDivisiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/Access/Division/masterAksesDivisi');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            // Base query user
            $userQuery = DB::table('KPI_User_Akses_Divisi as uad')
                ->leftJoin('KPI_Users as u', 'uad.Id_User', '=', 'u.Id_Users')
                ->leftJoin('View_Divisi_Sub_Divisi as v', 'uad.Id_Divisi', '=', 'v.ID_Divisi')
                ->select('uad.Id_User', 'u.Nama', 'u.Username')
                ->groupBy('uad.Id_User', 'u.Nama', 'u.Username');

            if (! empty($search)) {
                $userQuery->where(function ($q) use ($search) {
                    $q->where('u.Nama', 'like', "%{$search}%")
                        ->orWhere('u.Username', 'like', "%{$search}%")
                        ->orWhere('v.nama_divisi', 'like', "%{$search}%");
                });
            }

            $totalUsers = DB::table('KPI_User_Akses_Divisi as uad')
                ->leftJoin('KPI_Users as u', 'uad.Id_User', '=', 'u.Id_Users')
                ->leftJoin('View_Divisi_Sub_Divisi as v', 'uad.Id_Divisi', '=', 'v.ID_Divisi');

            if (! empty($search)) {
                $totalUsers = $totalUsers->where(function ($q) use ($search) {
                    $q->where('u.Nama', 'like', "%{$search}%")
                        ->orWhere('u.Username', 'like', "%{$search}%")
                        ->orWhere('v.nama_divisi', 'like', "%{$search}%");
                });
            }

            $totalUsers = $totalUsers->distinct('uad.Id_User')->count('uad.Id_User');
            $users = $userQuery->offset($offset)->limit($limit)->get();

            if ($users->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $userIds = $users->pluck('Id_User');

            $aksesDivisi = DB::table('KPI_User_Akses_Divisi as uad')
                ->leftJoin('View_Divisi_Sub_Divisi as v', 'uad.Id_Divisi', '=', 'v.ID_Divisi')
                ->select(
                    DB::raw('MIN(uad.Id_User_Akses_Divisi) as Id_User_Akses_Divisi'),
                    'uad.Id_User',
                    'uad.Id_Divisi',
                    'v.nama_divisi'
                )
                ->whereIn('uad.Id_User', $userIds)
                ->groupBy('uad.Id_User', 'uad.Id_Divisi', 'v.nama_divisi')
                ->orderBy('v.nama_divisi', 'asc')
                ->get();

            $aksesDivisi = $aksesDivisi->map(function ($item) {
                $item->Id_User_Akses_Divisi = Hashids::connection('custom')->encode($item->Id_User_Akses_Divisi);
                $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                $item->Id_Divisi = Hashids::connection('custom')->encode($item->Id_Divisi);

                return $item;
            });

            $grouped = [];
            foreach ($users as $user) {
                $encodedUserId = Hashids::connection('custom')->encode($user->Id_User);
                $grouped[$encodedUserId] = [
                    'Nama_Lengkap' => $user->Nama,
                    'Username' => $user->Username,
                    'satuan' => $aksesDivisi
                        ->where('Id_User', $encodedUserId)
                        ->values(),
                ];
            }

            $result = array_values($grouped);

            /* $totalPage DIBUANG, bukan dibungkus array.
               Parameter ke-7 successWithPagination() bertipe `array $extra`,
               sehingga mengirim float ke sana melempar TypeError dan rute ini
               SELALU menjawab 500 — entah sejak kapan, karena TypeError baru
               terjadi saat barisnya dijalankan.

               Nilainya pun mubazir: helper sudah menghitung sendiri
               'total_page' => ceil($total / $limit), rumus yang sama persis.
               Jadi bentuk jawabannya tidak berubah sedikit pun. */
            return ResponseHelper::successWithPagination(
                $result,
                $page,
                $limit,
                $totalUsers,
                'Data Ditemukan',
                200
            );

        } catch (\Exception $e) {
            Log::channel('UserAksesDivisiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataOptionDivisi()
    {
        try {
            $getData = DB::table('View_Divisi_Sub_Divisi')
                ->select('ID_Divisi', 'nama_divisi')
                ->groupBy('ID_Divisi', 'nama_divisi')
                ->orderBy('nama_divisi', 'ASC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->ID_Divisi = Hashids::connection('custom')->encode($item->ID_Divisi);

                return $item;
            });

            return ResponseHelper::success($encodedData, 200);

        } catch (\Exception $e) {
            Log::channel('UserAksesDivisiController')->error('ERROR getDataOptionDivisi: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataOptionUser()
    {
        try {
            $getData = DB::table('KPI_Users')
                ->select('Id_Users', 'Username', 'Nama')
                ->orderBy('Id_Users', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Users = Hashids::connection('custom')->encode($item->Id_Users);

                return $item;
            });

            return ResponseHelper::success($encodedData, 200);
        } catch (\Exception $e) {
            Log::channel('UserAksesDivisiController')->error('ERROR getDataOptionSatuan: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function create() {}

    public function store(Request $request)
    {
        $request->validate([
            'Id_User' => 'required|string',
            'Id_Divisi' => 'required|array',
            'Id_Divisi.*' => 'string',
        ]);

        try {
            $Id_User = Hashids::connection('custom')->decode($request->Id_User)[0] ?? null;

            if (! $Id_User) {
                return ResponseHelper::error('Format ID User tidak valid.', 400);
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Format ID User tidak valid.', 400);
        }

        $decodedDivisi = [];
        foreach ($request->Id_Divisi as $encoded) {
            $decoded = Hashids::connection('custom')->decode($encoded);
            if (! empty($decoded)) {
                $decodedDivisi[] = $decoded[0];
            }
        }

        if (empty($decodedDivisi)) {
            return ResponseHelper::error('Tidak ada divisi valid.', 400);
        }

        DB::beginTransaction();
        try {
            $inserted = [];

            foreach ($decodedDivisi as $divisiId) {
                $exists = DB::table('KPI_User_Akses_Divisi')
                    ->where('Id_User', $Id_User)
                    ->where('Id_Divisi', $divisiId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = [
                    'Id_User' => $Id_User,
                    'Id_Divisi' => $divisiId,
                ];

                DB::table('KPI_User_Akses_Divisi')->insert($payload);
                $inserted[] = $payload;
            }

            DB::commit();

            if (empty($inserted)) {
                return ResponseHelper::error('Semua data sudah ada, tidak ada yang disimpan.', 409);
            }

            return ResponseHelper::success($inserted, 'Data berhasil disimpan', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UserAksesDivisiController')->error('Error STORE: '.$e->getMessage());

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

    public function show(string $id) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id) {}

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
