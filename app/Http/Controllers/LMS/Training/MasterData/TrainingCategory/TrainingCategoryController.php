<?php

namespace App\Http\Controllers\LMS\Training\MasterData\TrainingCategory;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class TrainingCategoryController extends Controller
{
    public function store(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        $validateData = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.Kode_Kategori' => 'required|string|max:20',
            'data.*.Nama_Kategori' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');

            $insertData = [];
            $kodeList = [];

            foreach ($validateData['data'] as $item) {
                if (in_array($item['Kode_Kategori'], $kodeList)) {
                    throw new \Exception(
                        'Kode kategori duplicate dalam request: '.
                            $item['Kode_Kategori'],
                    );
                }
                $kodeList[] = $item['Kode_Kategori'];
                $exists = DB::table('N_LMS_Training_Kategori')
                    ->where('Kode_Kategori', $item['Kode_Kategori'])
                    ->exists();

                if ($exists) {
                    throw new \Exception(
                        'Kode kategori sudah ada: '.$item['Kode_Kategori'],
                    );
                }
                $insertData[] = [
                    'Kode_Kategori' => $item['Kode_Kategori'],
                    'Nama_Kategori' => $item['Nama_Kategori'],
                    'Tanggal' => $tanggal,
                    'Jam' => $jam,
                    'Id_User' => $pengguna,
                ];
            }
            DB::table('N_LMS_Training_Kategori')->insert($insertData);
            DB::commit();

            return ResponseHelper::success(
                $insertData,
                'Data berhasil disimpan !',
                201,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function update(Request $request, $id_training_kategori)
    {
        $decode = Hashids::connection('custom')->decode($id_training_kategori);

        if (! $decode) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $decodeId_Training_Kategori = $decode[0];

        $validateData = $request->validate([
            'Kode_Kategori' => 'required',
            'Nama_Kategori' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $payload = [
                'Kode_Kategori' => $validateData['Kode_Kategori'],
                'Nama_Kategori' => $validateData['Nama_Kategori'],
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];

            DB::table('N_LMS_Training_Kategori')
                ->where('Id_Training_Kategori', $decodeId_Training_Kategori)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success(
                $payload,
                'Data berhasil diupdate!',
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan sistem', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            $query = DB::table('N_LMS_Training_Kategori');

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('Nama_Kategori', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Id_Training_Kategori', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Training_Kategori = Hashids::connection(
                    'custom',
                )->encode($item->Id_Training_Kategori);
                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode(
                        $item->Id_User,
                    );
                }

                return $item;
            });

            return ResponseHelper::successWithPagination(
                $encodedData,
                $page,
                $limit,
                $total,
                'Data Ditemukan',
                200,
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function index()
    {
        return inertia('LMS/Training/MasterData/TrainingCategory/TrainingCategoryPage');
    }
}
