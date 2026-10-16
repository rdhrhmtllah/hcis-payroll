<?php

namespace App\Http\Controllers\KPI\MasterData\Scoring\Unit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class SatuanFormulaKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return inertia('KPI/MasterData/Scoring/Unit/masterSatuanFormula');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            // --- OPTIMASI 1: TENTUKAN QUERY SECARA BERSYARAT ---

            $totalQuery = null;
            $paginatedIdsQuery = null;

            if (! empty($search)) {
                // JIKA ADA SEARCH: Kita gunakan query join yang kompleks (seperti sebelumnya)
                // Query dasar HANYA dibuat jika diperlukan
                $baseQueryWithJoins = DB::table('KPI_Master_Satuan_Formula as sf')
                    ->leftJoin('KPI_Master_Formula as f', 'sf.Id_Master_Formula', '=', 'f.Id_Master_Formula')
                    ->leftJoin('KPI_Master_Satuan as s', 'sf.Id_Master_Satuan', '=', 's.Id_Master_Satuan')
                    ->where(function ($q) use ($search) { // 'when' tidak diperlukan, 'where' langsung saja
                        $q->where('f.Nama_Formula', 'like', '%'.$search.'%')
                            ->orWhere('s.Nama_Satuan', 'like', '%'.$search.'%')
                            ->orWhere('s.Simbol_Satuan', 'like', '%'.$search.'%');
                    });

                // 1.A Query Total (dengan Join)
                $totalQuery = (clone $baseQueryWithJoins)
                    ->distinct('sf.Id_Master_Formula');

                // 2.A Query Paginasi ID (dengan Join)
                $paginatedIdsQuery = (clone $baseQueryWithJoins)
                    ->select('sf.Id_Master_Formula')
                    ->orderByRaw('MAX(sf.Id_Master_Satuan_Formula) DESC')
                    ->groupBy('sf.Id_Master_Formula');

            } else {
                // JIKA TIDAK ADA SEARCH: Kita gunakan query super cepat di tabel utama

                // 1.B Query Total (Tanpa Join)
                $totalQuery = DB::table('KPI_Master_Satuan_Formula')
                    ->distinct('Id_Master_Formula');

                // 2.B Query Paginasi ID (Tanpa Join)
                $paginatedIdsQuery = DB::table('KPI_Master_Satuan_Formula')
                    ->select('Id_Master_Formula')
                    ->orderByRaw('MAX(Id_Master_Satuan_Formula) DESC')
                    ->groupBy('Id_Master_Formula');
            }

            // --- OPTIMASI 2: JALANKAN QUERY ---
            // Kita gabungkan eksekusi di sini

            // 1. Eksekusi Query Total
            $total = $totalQuery->count('Id_Master_Formula'); // `count` dieksekusi di sini

            // 2. Eksekusi Query Paginasi ID
            $paginatedFormulaIds = $paginatedIdsQuery
                ->offset($offset)
                ->limit($limit)
                ->pluck('Id_Master_Formula'); // `pluck` dieksekusi di sini

            if ($paginatedFormulaIds->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            // --- QUERY PENGAMBILAN DATA (INI TETAP SAMA) ---
            // 3. Query ini sudah optimal karena HANYA mengambil data untuk ID yang
            //    sudah dipaginasi (misal: 10 ID)
            $getData = DB::table('KPI_Master_Satuan_Formula')
                ->select(
                    'KPI_Master_Satuan_Formula.Id_Master_Satuan_Formula',
                    'KPI_Master_Satuan_Formula.Id_Master_Formula',
                    'KPI_Master_Satuan_Formula.Id_Master_Satuan',
                    'KPI_Master_Satuan_Formula.Flag_Aktif',
                    'KPI_Master_Satuan_Formula.Tanggal',
                    'KPI_Master_Satuan_Formula.Jam',
                    'KPI_Master_Satuan_Formula.Id_User',
                    'KPI_Master_Formula.Nama_Formula',
                    'KPI_Master_Formula.Rumus',
                    'KPI_Master_Satuan.Nama_Satuan',
                    'KPI_Master_Satuan.Simbol_Satuan'
                )
                ->leftJoin('KPI_Master_Formula', 'KPI_Master_Satuan_Formula.Id_Master_Formula', '=', 'KPI_Master_Formula.Id_Master_Formula')
                ->leftJoin('KPI_Master_Satuan', 'KPI_Master_Satuan_Formula.Id_Master_Satuan', '=', 'KPI_Master_Satuan.Id_Master_Satuan')
                ->whereIn('KPI_Master_Satuan_Formula.Id_Master_Formula', $paginatedFormulaIds)
                ->orderBy('KPI_Master_Satuan_Formula.Id_Master_Satuan_Formula', 'DESC')
                ->get();

            // --- PROSES GROUPING (Sama seperti sebelumnya) ---
            // Kode Anda di sini sudah OK.
            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Satuan_Formula = Hashids::connection('custom')->encode($item->Id_Master_Satuan_Formula);
                $item->Id_Master_Formula = Hashids::connection('custom')->encode($item->Id_Master_Formula);
                $item->Id_Master_Satuan = Hashids::connection('custom')->encode($item->Id_Master_Satuan);
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

                return $item;
            });

            $grouped = [];
            foreach ($encodedData as $row) {
                $formulaId = $row->Id_Master_Formula;

                if (! isset($grouped[$formulaId])) {
                    $grouped[$formulaId] = [
                        'Nama_Formula' => $row->Nama_Formula,
                        'Rumus' => $row->Rumus,
                        'Tanggal' => $row->Tanggal,
                        'Jam' => $row->Jam,
                        'satuan' => [],
                    ];
                }

                $grouped[$formulaId]['satuan'][] = [
                    'Id_Master_Satuan_Formula' => $row->Id_Master_Satuan_Formula,
                    'Id_Master_Formula' => $row->Id_Master_Formula,
                    'Id_Master_Satuan' => $row->Id_Master_Satuan,
                    'Nama_Satuan' => $row->Nama_Satuan,
                    'Simbol_Satuan' => $row->Simbol_Satuan,
                    'Flag_Aktif' => $row->Flag_Aktif,
                ];
            }

            // Mengurutkan hasil grup berdasarkan urutan ID yang dipaginasi
            $result = [];
            $encodedIds = $paginatedFormulaIds->map(function ($id) {
                return Hashids::connection('custom')->encode($id);
            });

            foreach ($encodedIds as $encodedId) {
                if (isset($grouped[$encodedId])) {
                    $result[] = $grouped[$encodedId];
                }
            }

            return ResponseHelper::successWithPagination($result, $page, $limit, $total, 'Data Ditemukan', 200);

        } catch (\Exception $e) {
            Log::channel('SatuanFormulaKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataOptionSatuan()
    {
        try {
            $getData = DB::table('KPI_Master_Satuan')
                ->select('Id_Master_Satuan', 'Nama_Satuan', 'Simbol_Satuan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Master_Satuan', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Satuan = Hashids::connection('custom')->encode($item->Id_Master_Satuan);

                return $item;
            });

            return ResponseHelper::success($encodedData, 200);
        } catch (\Exception $e) {
            Log::channel('SatuanFormulaKpiController')->error('ERROR getDataOptionSatuan: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataOptionFormula()
    {
        try {
            $getData = DB::table('KPI_Master_Formula')
                ->select('Id_Master_Formula', 'Nama_Formula', 'Rumus')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Master_Formula', 'DESC')
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Master_Formula = Hashids::connection('custom')->encode($item->Id_Master_Formula);
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

                return $item;
            });

            return ResponseHelper::success($encodedData, 200);
        } catch (\Exception $e) {
            Log::channel('SatuanFormulaKpiController')->error('ERROR getDataOptionFormula: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function store(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Id_Master_Formula' => 'required|string',
            'Id_Master_Satuan' => 'required|array',
            'Id_Master_Satuan.*' => 'string',
        ]);

        try {
            $Id_Master_Formula = Hashids::connection('custom')->decode($request->Id_Master_Formula)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula Tidak Valid.', 400);
        }

        $cekFormula = DB::table('KPI_Master_Formula')
            ->where('Id_Master_Formula', $Id_Master_Formula)
            ->exists();

        if (! $cekFormula) {
            return ResponseHelper::error('Formula tidak ditemukan.', 404);
        }

        $decodedSatuan = [];
        foreach ($request->Id_Master_Satuan as $encoded) {
            try {
                $decoded = Hashids::connection('custom')->decode($encoded)[0] ?? null;
                if ($decoded) {
                    $decodedSatuan[] = $decoded;
                }
            } catch (\Exception $e) {
                return ResponseHelper::error('Format Kunci Satuan Tidak Valid.', 400);
            }
        }

        if (empty($decodedSatuan)) {
            return ResponseHelper::error('Tidak ada satuan valid.', 400);
        }

        $validSatuan = DB::table('KPI_Master_Satuan')
            ->whereIn('Id_Master_Satuan', $decodedSatuan)
            ->pluck('Id_Master_Satuan')
            ->toArray();

        if (count($validSatuan) !== count($decodedSatuan)) {
            return ResponseHelper::error('Terdapat satuan yang tidak valid.', 404);
        }

        DB::beginTransaction();
        try {
            $inserted = [];

            foreach ($validSatuan as $satuanId) {
                $exists = DB::table('KPI_Master_Satuan_Formula')
                    ->where('Id_Master_Formula', $Id_Master_Formula)
                    ->where('Id_Master_Satuan', $satuanId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = [
                    'Id_Master_Formula' => $Id_Master_Formula,
                    'Id_Master_Satuan' => $satuanId,
                    'Id_User' => $pengguna,
                    'Jam' => date('H:i:s'),
                    'Tanggal' => date('Y-m-d'),
                    'Flag_Aktif' => 'Y',
                ];

                DB::table('KPI_Master_Satuan_Formula')->insert($payload);
                $inserted[] = $payload;
            }

            DB::commit();

            if (empty($inserted)) {
                return ResponseHelper::error('Semua data sudah ada, tidak ada yang disimpan.', 409);
            }

            return ResponseHelper::success($inserted, 'Data berhasil disimpan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('SatuanFormulaKpiController')->error('Error STORE: '.$e->getMessage());

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

    public function update(Request $request, $Id_Master_Satuan_Formula)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($Id_Master_Satuan_Formula)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Relasi Tidak Valid.', 400);
        }

        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            'Id_Master_Formula' => 'required|string',
            'Id_Master_Satuan' => 'required|string',
        ]);

        try {
            $Id_Master_Formula = Hashids::connection('custom')->decode($request->Id_Master_Formula)[0];
            $Id_Master_Satuan = Hashids::connection('custom')->decode($request->Id_Master_Satuan)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $formulaExists = DB::table('KPI_Master_Formula')->where('Id_Master_Formula', $Id_Master_Formula)->exists();
        $satuanExists = DB::table('KPI_Master_Satuan')->where('Id_Master_Satuan', $Id_Master_Satuan)->exists();

        if (! $formulaExists) {
            return ResponseHelper::error('Formula tidak ditemukan.', 404);
        }
        if (! $satuanExists) {
            return ResponseHelper::error('Satuan tidak ditemukan.', 404);
        }
        $duplicateExists = DB::table('KPI_Master_Satuan_Formula')
            ->where('Id_Master_Formula', $Id_Master_Formula)
            ->where('Id_Master_Satuan', $Id_Master_Satuan)
            ->where('Id_Master_Satuan_Formula', '!=', $decodedId)
            ->exists();

        if ($duplicateExists) {
            return ResponseHelper::error('Kombinasi Formula dan Satuan ini sudah ada.', 409);
        }

        DB::beginTransaction();
        try {
            $payload = [
                'Id_Master_Formula' => $Id_Master_Formula,
                'Id_Master_Satuan' => $Id_Master_Satuan,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
            ];

            DB::table('KPI_Master_Satuan_Formula')
                ->where('Id_Master_Satuan_Formula', $decodedId)
                ->update($payload);

            DB::commit();

            return ResponseHelper::success($payload, 'Data berhasil diperbarui', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('SatuanFormulaKpiController')->error('Error UPDATE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Saat Memperbarui Data', 500);
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
            $jadwal = DB::table('KPI_Master_Satuan_Formula')
                ->where('Id_Master_Satuan_Formula', $id)
                ->first();

            if (! $jadwal) {
                return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
            }

            $newStatus = ($jadwal->Flag_Aktif === 'Y') ? 'T' : 'Y';

            // Update database
            DB::table('KPI_Master_Satuan_Formula')
                ->where('Id_Master_Satuan_Formula', $id)
                ->update(['Flag_Aktif' => $newStatus]);

            return ResponseHelper::success(
                ['new_status' => $newStatus],
                'Status formula berhasil diubah.'
            );

        } catch (\Exception $e) {
            Log::channel('SatuanFormulaKpiController')->error('ERROR TOGGLESWITCH: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengubah status.', 500);
        }
    }
}
