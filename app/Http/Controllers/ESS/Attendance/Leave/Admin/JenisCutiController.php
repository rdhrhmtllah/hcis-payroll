<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class JenisCutiController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = strip_tags($validated['search'] ?? '');
        $limit = 2;
        $page = $validated['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        $searchColumns = [
            'Nama_Jenis_Cuti',
            'Deskripsi',
        ];

        $baseQuery = DB::table('N_HRIS_Jenis_Cuti')
            ->leftJoin('Karyawan', function ($join) {
                $join->whereRaw("N_HRIS_Jenis_Cuti.Created_By NOT LIKE '%[^0-9]%'")
                    ->on(DB::raw('Karyawan.UserID_Web'), '=', DB::raw('TRY_CAST(N_HRIS_Jenis_Cuti.Created_By AS INT)'));
            })
            ->selectRaw("
                CASE 
                    WHEN N_HRIS_Jenis_Cuti.Created_By NOT LIKE '%[^0-9]%'
                        THEN Karyawan.Nama
                    ELSE N_HRIS_Jenis_Cuti.Created_By
                END as Nama_Pembuat,
                N_HRIS_Jenis_Cuti.*
            ")
            ->where('Flag_Aktif', 'Y');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $baseQuery->where(function ($query) use ($searchColumns, $search) {
                foreach ($searchColumns as $col) {
                    $query->orWhere($col, 'LIKE', "%{$search}%");
                }
            });
        }

        $countQuery = clone $baseQuery;
        $total = $countQuery->count();
        $jenisCutiRaw = $baseQuery
            ->offset($offset)
            ->limit($limit)
            ->orderBy('Id_Jenis_Cuti', 'desc')
            ->get();

        $encodedData = collect($jenisCutiRaw)->map(function ($e) {
            $approver = DB::table('N_HRIS_Cuti_Approval as ca')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ca.Id_Jenis_Approval')
                        // ->select('ja.Nama_Approval','ca.Request_Order_Flow', 'ja.Auto_Approve', 'ja.Keterangan')
                ->where('ca.Flag_Aktif', 'Y')
                ->where('ca.Id_Jenis_Cuti', $e->Id_Jenis_Cuti)
                ->get();

            $e->Approver = [];
            if (! $approver->isEmpty()) {
                $e->Approver = $approver;
            }
            $e->Id_Jenis_Cuti = Hashids::connection('custom')->encode($e->Id_Jenis_Cuti);
            $e->Created_At = FormatTanggalHelper::format($e->Created_At, true);

            return $e;
        });

        return ResponseHelper::successWithPaginationV2($encodedData, $page, $limit, $total, 'Data Ditemukan', 200);
    }

    public function roleOptions()
    {
        $roles = DB::table('N_HRIS_Role_Functional as rf')
            ->leftJoin('N_HRIS_Role_User as ru', function ($join) {
                $join->on('ru.Id_Role', '=', 'rf.Id_Role')
                    ->where('ru.Flag_Aktif', 'Y');
            })
            ->where('rf.Flag_Aktif', 'Y')
            ->select(
                'rf.Id_Role',
                'rf.Role_Code',
                'rf.Role_Name',
                DB::raw('COUNT(DISTINCT ru.UserId_Web) as user_count'),
            )
            ->groupBy('rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name')
            ->orderBy('rf.Role_Name')
            ->get();

        return ResponseHelper::success($roles, 'Role approval berhasil diambil.', 200);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        try {

            $Id_User = Auth::user()->Id_Users;
            DB::BeginTransaction();
            $validated = $request->validate([
                'Nama_Jenis_Cuti' => 'required|string|max:255',
                'Deskripsi' => 'nullable|string',
                // 'Kuota_Default' => 'required|integer|min:0',
                'Masa_Berlaku_Bulan' => 'nullable|integer',
                // 'Dapat_Diakumulasi' => 'boolean',
                'Gender_Spesifik' => 'nullable | string',
                'Flag_Bisa_Hutang_Cuti' => 'required|string|max:1|in:T,Y',
                'Maks_Hutang_Cuti' => 'nullable|integer|min:0',
                'Hutang_Cuti_Mode' => 'nullable|string|in:OUTSTANDING,PERIODIK',
                'Hutang_Cuti_Basis_Periode' => 'nullable|string|in:KALENDER,JOIN_DATE',
                'Hutang_Periodik_Perhitungan' => 'nullable|string|in:GROSS,NETTO',
                'Batas_Hari_Pengajuan' => 'required',
                'Maksimum_Pengajuan' => 'required|integer|min:0',
                'Perlu_Persetujuan_Khusus' => 'required|string|max:1|in:T,Y',
                'Minimal_Masa_Kerja_Bulan' => 'nullable|integer|min:0',
                'Approver' => ' nullable | Array',
                'Approver.*.Source_Type' => 'nullable|string|in:TABLE,GROUP_TABLE,DIRECT_EMPLOYEE,ROLE',
                'Approver.*.Source_Reference' => 'nullable|string|max:200',
                'Approver.*.Nama_Approval' => 'nullable|string|max:100',
                'Approver.*.Auto_Approve' => 'nullable|string|in:T,Y',
                'Approver.*.Keterangan' => 'nullable|string|max:255',
                'Approver.*.Order_Approver_Limit' => 'nullable|integer|min:1',
                'Dokumen_Pendukung' => 'required|string|max:1|in:T,Y',
                'Memotong_Saldo_Cuti' => 'required|string|max:1|in:T,Y',
                'mode_tanggal' => 'required|string|max:10',
            ]);

            $payload = $validated;
            // dd($validated);
            $approvers = [];
            if (isset($payload['Approver'])) {
                $approvers = $payload['Approver'];
                unset($payload['Approver']);
                unset($payload['mode_tanggal']);
            }
            $payload['Created_By'] = $Id_User;
            $payload['Flag_Aktif'] = 'Y';
            $validated['Minimal_Masa_Kerja_Bulan'] = $validated['Minimal_Masa_Kerja_Bulan'] ?? 0;
            $payload['Minimal_Masa_Kerja_Bulan'] = $validated['Minimal_Masa_Kerja_Bulan'] ?? 0;
            $validated['Masa_Berlaku_Bulan'] = $validated['Masa_Berlaku_Bulan'] ?? 12;
            $payload['Masa_Berlaku_Bulan'] = $validated['Masa_Berlaku_Bulan'] ?? 12;
            $validated['Format_Tanggal_Select'] = $validated['mode_tanggal'] ?? 'MULTIPLE';
            $payload['Format_Tanggal_Select'] = $validated['mode_tanggal'] ?? 'MULTIPLE';

            // dd($validated);
            $jenis = DB::table('N_HRIS_Jenis_Cuti')->insertGetId($payload);

            // dd($approvers);
            if (! empty($approvers) && $jenis) {
                foreach ($approvers as $index => $approver) {
                    $approver = $this->normalizeApproverPayload($approver, $index);
                    // dd($approver['Source_Reference']);
                    $adaDirect = DB::table('N_HRIS_Jenis_Approval')
                        ->where('Nama_Approval', $approver['Nama_Approval'])
                        ->where('Source_Type', $approver['Source_Type'])
                        ->where('Source_Reference', $approver['Source_Reference'])
                        ->where('Auto_Approve', $approver['Auto_Approve'])
                        ->where('Flag_Aktif', 'Y')->first();

                    if (! $adaDirect) {
                        $approver['Id_Jenis_Approval'] = DB::table('N_HRIS_Jenis_Approval')->insertGetId([
                            'Nama_Approval' => $approver['Nama_Approval'],
                            'Auto_Approve' => $approver['Auto_Approve'],
                            'Source_Type' => $approver['Source_Type'],
                            'Source_Reference' => $approver['Source_Reference'],
                            'Keterangan' => $approver['Keterangan'],
                            'Flag_Aktif' => 'Y',
                            'Created_At' => now(),
                            'Created_By' => $Id_User,
                        ]);
                    } else {
                        $approver['Id_Jenis_Approval'] = $adaDirect->Id_Jenis_Approval;
                    }

                    DB::table('N_HRIS_Cuti_Approval')->insert([
                        'Id_Jenis_Cuti' => $jenis,
                        'Id_Jenis_Approval' => $approver['Id_Jenis_Approval'],
                        'Order_Flow' => $index + 1,
                        'Order_Approver_Limit' => $approver['Order_Approver_Limit'] ?? null,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Created_By' => $Id_User,
                    ]);
                }
            }

            DB::commit();

            return ResponseHelper::success($payload, 'Berhasil Menyimpan Data', 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            $all_messages = [];
            foreach ($e->errors() as $field_errors) {
                $all_messages = array_merge($all_messages, $field_errors);
            }
            $error_string = implode(', ', $all_messages);
            Log::channel('JenisCutiController')->error('Input Tidak Valid: '.json_encode($e->errors()));

            return ResponseHelper::error("Input tidak valid $error_string", 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('JenisCutiController')->error('Gagal menyimpan data Karyawan Team: '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data'], 500);

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

    public function update(Request $request, $id)
    {
        $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', Hashids::connection('custom')->decode($id)[0])->first();

        $validated = $request->validate([
            'Nama_Jenis_Cuti' => 'required|string|max:255',
            'Deskripsi' => 'nullable|string',
            // 'Kuota_Default' => 'required|integer|min:0',
            'Masa_Berlaku_Bulan' => 'nullable|integer',
            // 'Dapat_Diakumulasi' => 'boolean',
            'Gender_Spesifik' => 'nullable | string',
            'Flag_Bisa_Hutang_Cuti' => 'required|string|max:1|in:T,Y',
            'Maks_Hutang_Cuti' => 'nullable|integer|min:0',
            'Hutang_Cuti_Mode' => 'nullable|string|in:OUTSTANDING,PERIODIK',
            'Hutang_Cuti_Basis_Periode' => 'nullable|string|in:KALENDER,JOIN_DATE',
            'Hutang_Periodik_Perhitungan' => 'nullable|string|in:GROSS,NETTO',
            'Batas_Hari_Pengajuan' => 'required',
            'Maksimum_Pengajuan' => 'required|integer|min:0',
            'Perlu_Persetujuan_Khusus' => 'required|string|max:1|in:T,Y',
            'Minimal_Masa_Kerja_Bulan' => 'nullable|integer|min:0',
            'Approver' => ' nullable | Array',
            'Approver.*.Source_Type' => 'nullable|string|in:TABLE,GROUP_TABLE,DIRECT_EMPLOYEE,ROLE',
            'Approver.*.Source_Reference' => 'nullable|string|max:200',
            'Approver.*.Nama_Approval' => 'nullable|string|max:100',
            'Approver.*.Auto_Approve' => 'nullable|string|in:T,Y',
            'Approver.*.Keterangan' => 'nullable|string|max:255',
            'Approver.*.Order_Approver_Limit' => 'nullable|integer|min:1',
            'Dokumen_Pendukung' => 'required|string|max:1|in:T,Y',
            'Memotong_Saldo_Cuti' => 'required|string|max:1|in:T,Y',
            'Format_Tanggal_Select' => 'required|string|max:10',
            'mode_tanggal' => 'required|string|max:10',
        ]);
        try {
            $payload = $validated;
            $approvers = [];
            if (isset($payload['Approver'])) {
                $approvers = $payload['Approver'];
                unset($payload['Approver']);
            }
            unset($payload['mode_tanggal']);
            $payload['Updated_By'] = Auth::user()->Id_Users;
            $payload['Updated_At'] = now();
            $payload['Minimal_Masa_Kerja_Bulan'] = $validated['Minimal_Masa_Kerja_Bulan'] ?? 0;
            $payload['Masa_Berlaku_Bulan'] = $validated['Masa_Berlaku_Bulan'] ?? 12;
            $payload['Flag_Aktif'] = 'Y';
            $validated['Minimal_Masa_Kerja_Bulan'] = $validated['Minimal_Masa_Kerja_Bulan'] ?? 0;
            $payload['Minimal_Masa_Kerja_Bulan'] = $validated['Minimal_Masa_Kerja_Bulan'] ?? 0;
            $validated['Masa_Berlaku_Bulan'] = $validated['Masa_Berlaku_Bulan'] ?? 12;
            $payload['Masa_Berlaku_Bulan'] = $validated['Masa_Berlaku_Bulan'] ?? 12;
            $validated['Format_Tanggal_Select'] = $validated['mode_tanggal'] ?? 'MULTIPLE';
            $payload['Format_Tanggal_Select'] = $validated['mode_tanggal'] ?? 'MULTIPLE';

            // dd($payload, $validated);
            DB::beginTransaction();

            // dd($payload);

            DB::table('N_HRIS_Jenis_Cuti')
                ->where('Id_Jenis_Cuti', $jenisCuti->Id_Jenis_Cuti)
                ->update($payload);

            // Hapus semua approver lama untuk jenis cuti ini
            DB::table('N_HRIS_Cuti_Approval')
                ->where('Id_Jenis_Cuti', $jenisCuti->Id_Jenis_Cuti)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => Auth::user()->Id_Users,
                ]);

            // Tambahkan approver baru
            if (! empty($approvers)) {
                foreach ($approvers as $index => $approver) {
                    $approver = $this->normalizeApproverPayload($approver, $index);

                    $adaDirect = DB::table('N_HRIS_Jenis_Approval')
                        ->where('Nama_Approval', $approver['Nama_Approval'])
                        ->where('Source_Type', $approver['Source_Type'])
                        ->where('Source_Reference', $approver['Source_Reference'])
                        ->where('Auto_Approve', $approver['Auto_Approve'])
                        ->where('Flag_Aktif', 'Y')->first();

                    if (! $adaDirect) {
                        $approver['Id_Jenis_Approval'] = DB::table('N_HRIS_Jenis_Approval')->insertGetId([
                            'Nama_Approval' => $approver['Nama_Approval'],
                            'Auto_Approve' => $approver['Auto_Approve'],
                            'Source_Type' => $approver['Source_Type'],
                            'Source_Reference' => $approver['Source_Reference'],
                            'Keterangan' => $approver['Keterangan'],
                            'Flag_Aktif' => 'Y',
                            'Created_At' => now(),
                            'Created_By' => Auth::user()->Id_Users,
                        ]);
                    } else {
                        $approver['Id_Jenis_Approval'] = $adaDirect->Id_Jenis_Approval;
                    }

                    DB::table('N_HRIS_Cuti_Approval')->insert([
                        'Id_Jenis_Cuti' => $jenisCuti->Id_Jenis_Cuti,
                        'Id_Jenis_Approval' => $approver['Id_Jenis_Approval'],
                        'Order_Flow' => $index + 1,
                        'Order_Approver_Limit' => $approver['Order_Approver_Limit'] ?? null,
                        'Flag_Aktif' => 'Y',
                        'Created_At' => now(),
                        'Created_By' => Auth::user()->Id_Users,
                    ]);
                }
            }

            DB::commit();

            return ResponseHelper::success(null, 'Jenis Cuti berhasil diperbarui.');

        } catch (ValidationException $e) {
            DB::rollBack();
            $all_messages = [];
            foreach ($e->errors() as $field_errors) {
                $all_messages = array_merge($all_messages, $field_errors);
            }
            $error_string = implode(', ', $all_messages);
            Log::channel('JenisCutiController')->error('Input Tidak Valid: '.json_encode($e->errors()));

            return ResponseHelper::error("Input tidak valid $error_string", 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('JenisCutiController')->error('Gagal memperbarui data Jenis Cuti: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat memperbarui data', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }

    }

    public function destroy($id)
    {
        try {
            $decodedId = Hashids::connection('custom')->decode($id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Jenis Cuti tidak valid.', 400);
            }
            $idJenisCuti = $decodedId[0];

            DB::beginTransaction();

            $jenisCuti = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenisCuti)->first();

            if (! $jenisCuti) {
                DB::rollBack();

                return ResponseHelper::error('Jenis Cuti tidak ditemukan.', 404);
            }

            DB::table('N_HRIS_Jenis_Cuti')
                ->where('Id_Jenis_Cuti', $idJenisCuti)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_By' => Auth::user()->Id_Users,
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                ]);

            DB::commit();

            return ResponseHelper::success(null, 'Jenis Cuti berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting Jenis Cuti: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus Jenis Cuti.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }

    }

    private function normalizeApproverPayload(array $approver, int $index): array
    {
        $approver['Source_Type'] = strtoupper(trim($approver['Source_Type'] ?? 'TABLE'));
        $approver['Source_Reference'] =
            $approver['Source_Type'] == 'DIRECT_EMPLOYEE'
                ? Crypt::decryptString($approver['Source_Reference'])
                : trim((string) ($approver['Source_Reference'] ?? ''));

        if ($approver['Source_Type'] === 'ROLE') {
            $role = DB::table('N_HRIS_Role_Functional')
                ->where('Role_Code', $approver['Source_Reference'])
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $role) {
                throw ValidationException::withMessages([
                    'Approver' => 'Role approval step '.($index + 1).' tidak ditemukan atau tidak aktif: '.$approver['Source_Reference'],
                ]);
            }

        }

        // Order_Approver_Limit hanya bermakna untuk source rantai (TABLE/KPI_TABLE).
        // Untuk ROLE/GROUP_TABLE/DIRECT_EMPLOYEE resolver hanya menghasilkan 1 approver,
        // sehingga limit > 1 akan menggagalkan pengajuan di ApprovalFlowService.
        $limit = $approver['Order_Approver_Limit'] ?? null;
        if (in_array($approver['Source_Type'], ['TABLE', 'KPI_TABLE'], true) && $limit !== null && (int) $limit >= 1) {
            $approver['Order_Approver_Limit'] = (int) $limit;
        } else {
            $approver['Order_Approver_Limit'] = null;
        }

        return $approver;
    }
}
