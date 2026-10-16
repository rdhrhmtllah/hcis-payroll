<?php

namespace App\Http\Controllers\KPI\Access\Level;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class UserAksesLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/Access/Level/masterAksesLevel');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $totalUsersQuery = DB::table('KPI_User_Akses_Level as al')
                ->leftJoin('KPI_Users as u', 'al.Id_User', '=', 'u.Id_Users');

            if (! empty($search)) {
                $totalUsersQuery->where(function ($q) use ($search) {
                    $q->where('u.Nama', 'like', "%{$search}%")
                        ->orWhere('u.Username', 'like', "%{$search}%");
                });
            }

            $totalUsers = $totalUsersQuery->distinct('al.Id_User')->count('al.Id_User');

            $usersQuery = DB::table('KPI_User_Akses_Level as al')
                ->select('al.Id_User', 'u.Username', 'u.Nama as Nama_Lengkap')
                ->leftJoin('KPI_Users as u', 'al.Id_User', '=', 'u.Id_Users');

            if (! empty($search)) {
                $usersQuery->where(function ($q) use ($search) {
                    $q->where('u.Nama', 'like', "%{$search}%")
                        ->orWhere('u.Username', 'like', "%{$search}%");
                });
            }

            $users = $usersQuery
                ->groupBy('al.Id_User', 'u.Username', 'u.Nama')
                ->orderBy('u.Nama', 'ASC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($users->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $userIds = $users->pluck('Id_User');

            $aksesLevels = DB::table('KPI_User_Akses_Level as al')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'al.Id_Level', '=', 'v.ID_Level')
                ->select(
                    'al.Id_User_Akses_Level',
                    'al.Id_User',
                    'al.Id_Level',
                    'v.nama_level'
                )
                ->whereIn('al.Id_User', $userIds)
                ->groupBy('al.Id_User', 'al.Id_Level', 'v.nama_level', 'al.Id_User_Akses_Level')
                ->orderBy('v.nama_level', 'asc')
                ->get();

            $aksesLevels = $aksesLevels->map(function ($item) {
                $item->Id_User_Akses_Level = Hashids::connection('custom')->encode($item->Id_User_Akses_Level);
                $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                $item->Id_Level = Hashids::connection('custom')->encode($item->Id_Level);

                return $item;
            });

            $grouped = [];
            foreach ($users as $user) {
                $encodedUserId = Hashids::connection('custom')->encode($user->Id_User);
                $grouped[$encodedUserId] = [
                    'Nama_Lengkap' => $user->Nama_Lengkap,
                    'Username' => $user->Username,
                    'satuan' => $aksesLevels
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
            Log::channel('UserAksesLevelController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataOptionLevel()
    {
        try {
            $getData = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
                ->select('ID_Level', 'nama_level')
                ->groupBy('ID_Level', 'nama_level')
                ->orderBy('ID_Level', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->ID_Level = Hashids::connection('custom')->encode($item->ID_Level);

                return $item;
            });

            return ResponseHelper::success($encodedData, 200);
        } catch (\Exception $e) {
            Log::channel('UserAksesLevelController')->error('ERROR getDataOptionLevel: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'Id_User' => 'required|string',
            'Id_Level' => 'required|array',
            'Id_Level.*' => 'string',
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
        foreach ($request->Id_Level as $encoded) {
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
                $exists = DB::table('KPI_User_Akses_Level')
                    ->where('Id_User', $Id_User)
                    ->where('Id_Level', $divisiId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = [
                    'Id_User' => $Id_User,
                    'Id_Level' => $divisiId,
                ];

                DB::table('KPI_User_Akses_Level')->insert($payload);
                $inserted[] = $payload;
            }

            DB::commit();

            if (empty($inserted)) {
                return ResponseHelper::error('Semua data sudah ada, tidak ada yang disimpan.', 409);
            }

            return ResponseHelper::success($inserted, 'Data berhasil disimpan', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('UserAksesLevelController')->error('Error STORE: '.$e->getMessage());

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
