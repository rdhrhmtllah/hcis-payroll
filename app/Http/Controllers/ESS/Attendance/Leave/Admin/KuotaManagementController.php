<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ESS\Attendance\Leave\Admin\RecoveryPreviewRequest;
use App\Services\ESS\Attendance\Leave\Dashboard\CutiDashboardService;
use App\Services\ESS\Attendance\Leave\Processing\CutiProcessBatchService;
use App\Services\ESS\Attendance\Leave\Shared\AturanAkrualCuti;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KuotaManagementController extends Controller
{
    protected $transactionService;

    protected $batchService;

    const ID_CUTI_AKUMULASI = [1]; // ID Jenis Cuti Tahunan dan Loyalty

    private const ID_CUTI_TAHUNAN = 1;

    private const ID_CUTI_LOYALTY = 2;

    private function cutoffDate(): Carbon
    {
        return Carbon::parse(CutiProcessBatchService::accrualCutoffDate())->startOfDay();
    }

    public function __construct(
        TransactionService $transactionService,
        CutiProcessBatchService $batchService,
        private readonly AttendanceDirtyResolverService $attendanceDirtyResolver,
        private readonly CutiDashboardService $dashboardService,
    ) {
        $this->transactionService = $transactionService;
        $this->batchService = $batchService;
    }

    /**
     * Render halaman Vue
     */
    public function index()
    {
        return inertia('ESS/Attendance/Leave/Cuti/Admin/KuotaManagement');
    }

    // =========================================================================
    // TAB KUOTA KARYAWAN
    // =========================================================================

    /**
     * Get statistics for quick stat cards
     */
    public function getStats()
    {
        try {
            $stats = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan')
                ->selectRaw(
                    '
                    COUNT(*) as total_karyawan,
                    SUM(CASE WHEN Sisa_Cuti_Tampil > 0 AND Hutang_Cuti_Tampil = 0 THEN 1 ELSE 0 END) as karyawan_normal,
                    SUM(Sedang_Diajukan) as total_pending,
                    SUM(CASE WHEN Hutang_Cuti_Tampil > 0 THEN 1 ELSE 0 END) as karyawan_hutang,
                    AVG(Sisa_Cuti_Tampil) as avg_sisa_cuti
                ',
                )
                ->first();

            return ResponseHelper::success(
                [
                    'total_karyawan' => (int) $stats->total_karyawan,
                    'karyawan_normal' => (int) $stats->karyawan_normal,
                    'total_pending' => (int) $stats->total_pending,
                    'karyawan_hutang' => (int) $stats->karyawan_hutang,
                    'avg_sisa_cuti' => round($stats->avg_sisa_cuti, 1),
                ],
                'Stats retrieved',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getStats error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil statistik', 500);
        }
    }

    /**
     * Get all karyawan with kuota summary (paginated)
     */
    public function getAllKaryawan(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable', 'string'],
            'status_kuota' => ['nullable', 'string'], // normal, pending, hutang
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        try {
            $search = strip_tags($validated['search'] ?? '');
            $divisi = $validated['divisi'] ?? '';
            $statusKuota = $validated['status_kuota'] ?? '';
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // dd($divisi);

            $query = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan as v')
                ->join('Karyawan as k', 'v.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                )
                ->select(
                    'v.Kode_Karyawan',
                    'v.Nama_Karyawan',
                    'v.Saldo_Awal_DB',
                    'v.Sedang_Diajukan',
                    'v.Potongan_Cuti_Bersama',
                    'v.Sisa_Cuti_Tampil',
                    'v.Hutang_Cuti_Tampil',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                    'k.UserId_Absen',
                )
                ->where('k.Aktif', 'Y');

            // Search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('v.Nama_Karyawan', 'like', "%{$search}%")->orWhere(
                        'v.Kode_Karyawan',
                        'like',
                        "%{$search}%",
                    );
                });
            }

            // Divisi filter
            if ($divisi) {
                $query->where('vd.ID_Divisi', $divisi);
            }

            // Status kuota filter
            if ($statusKuota === 'normal') {
                $query->where('v.Sisa_Cuti_Tampil', '>', 0)->where('v.Hutang_Cuti_Tampil', '=', 0);
            } elseif ($statusKuota === 'pending') {
                $query->where('v.Sedang_Diajukan', '>', 0);
            } elseif ($statusKuota === 'hutang') {
                $query->where('v.Hutang_Cuti_Tampil', '>', 0);
            }

            $total = $query->count();
            $offset = ($page - 1) * $perPage;

            $data = $query->orderBy('v.Nama_Karyawan', 'asc')->offset($offset)->limit($perPage)->get();

            // Encrypt Kode_Karyawan for security
            $data = $data->map(function ($item) {
                $item->Kode_Karyawan_Encrypted = Crypt::encryptString($item->Kode_Karyawan);
                unset($item->Kode_Karyawan);

                return $item;
            });

            // Get nearest expiry for each karyawan
            $kodeList = $data
                ->pluck('Kode_Karyawan_Encrypted')
                ->map(function ($enc) {
                    return Crypt::decryptString($enc);
                })
                ->toArray();

            $expiryData = DB::table('HRIS_Buku_Cuti')
                ->whereIn('Kode_Karyawan', $kodeList)
                ->where('Flag_Aktif', 'Y')
                ->where('sisa_cuti', '>', 0)
                ->where('tanggal_expired', '>=', now())
                ->select('Kode_Karyawan', DB::raw('MIN(tanggal_expired) as nearest_expiry'))
                ->groupBy('Kode_Karyawan')
                ->get()
                ->keyBy('Kode_Karyawan');

            $data = $data->map(function ($item) use ($expiryData) {
                $kode = Crypt::decryptString($item->Kode_Karyawan_Encrypted);
                $item->nearest_expiry = $expiryData[$kode]->nearest_expiry ?? null;

                return $item;
            });

            return ResponseHelper::successWithPaginationV2(
                $data,
                $page,
                $perPage,
                $total,
                'Data karyawan berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getAllKaryawan error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data karyawan', 500);
        }
    }

    /**
     * Get detail kuota per karyawan
     */
    public function getKaryawanDetail(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);

            // Get summary from view
            $summary = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();

            // Get karyawan info
            $karyawan = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                )
                ->where('k.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'k.Nama',
                    'k.Kode_Karyawan',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_jabatan',
                    'vg.nama_level',
                )
                ->first();

            // Get breakdown per jenis cuti (active wallets OR hutang wallets)
            $breakdown = DB::table('HRIS_Buku_Cuti as bc')
                ->leftJoin('N_HRIS_Jenis_Cuti as jc', 'bc.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
                ->where('bc.Kode_Karyawan', $kodeKaryawan)
                ->where('bc.Flag_Aktif', 'Y')
                ->where(function ($query) {
                    // Active wallets: activated and not expired
                    $query
                        ->where(function ($q) {
                            $q
                                // ->where('bc.Activated_At', '<=', now())
                                ->where('bc.Tanggal_Expired', '>=', now());
                        })
                        // OR hutang wallets: negative balance regardless of expired
                        ->orWhere('bc.sisa_cuti', '<', 0);
                })
                ->select(
                    'jc.Nama_Jenis_Cuti',
                    'bc.Id_Jenis_Cuti',
                    'bc.jumlah_awal_cuti',
                    'bc.jumlah_terpakai',
                    'bc.sisa_cuti',
                    'bc.tanggal_diberikan as activated_at',
                    'bc.activated_at as activated_date',
                    'bc.tanggal_expired',
                    'bc.Periode',
                    'bc.Id_Buku_Cuti',
                    'bc.notes',
                )
                ->orderBy('bc.sisa_cuti', 'desc') // Show positive balances first, then hutang
                ->orderBy('bc.tanggal_expired', 'asc')
                ->get();
            // dd($breakdown);

            return ResponseHelper::success(
                [
                    'karyawan' => $karyawan,
                    'summary' => $summary,
                    'breakdown' => $breakdown,
                ],
                'Detail kuota berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getKaryawanDetail error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil detail kuota', 500);
        }
    }

    /**
     * Get riwayat cuti karyawan (approved transactions)
     */
    public function getRiwayatCuti(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);
            $page = $validated['page'] ?? 1;
            $perPage = 10;

            $query = DB::table('Transaksi_Cuti_Detail as tcd')
                ->join('Transaksi_Cuti as tc', 'tcd.No_Transaksi', '=', 'tc.No_Transaksi')
                ->leftJoin('N_HRIS_Jenis_Cuti as jc', 'tcd.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
                ->where('tcd.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'tcd.No_Transaksi',
                    'jc.Nama_Jenis_Cuti',
                    'tcd.Tanggal_Cuti_Dari',
                    'tcd.Tanggal_Cuti_Sampai',
                    'tcd.Alasan',
                    'tcd.Flag_Approval',
                    'tc.Tanggal as tanggal_pengajuan',
                    'tc.Status',
                )
                ->orderBy('tc.Tanggal', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;

            $data = $query->offset($offset)->limit($perPage)->get();

            // Get jumlah hari for each transaction
            $data = $data->map(function ($item) {
                $tanggalDet = DB::table('Transaksi_Cuti_Det')
                    ->where('No_Transaksi', $item->No_Transaksi)
                    ->pluck('Tanggal_Cuti')
                    ->toArray();

                $item->jumlah_hari = count($tanggalDet);
                $item->tanggal_detail = FormatTanggalHelper::formatTanggalMultiple($tanggalDet);
                $item->status_text = $this->parseApprovalStatus($item->Flag_Approval, $item->Status);

                return $item;
            });

            return ResponseHelper::successWithPaginationV2(
                $data,
                $page,
                $perPage,
                $total,
                'Riwayat cuti berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getRiwayatCuti error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil riwayat cuti', 500);
        }
    }

    /**
     * Get log mutasi kuota dari N_HRIS_Log_Buku_Cuti
     */
    public function getLogMutasi(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);
            $page = $validated['page'] ?? 1;
            $perPage = 15;

            // Get all Id_Buku_Cuti for this karyawan
            $bukuCutiIds = DB::table('HRIS_Buku_Cuti')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->pluck('Id_Buku_Cuti')
                ->toArray();

            $query = DB::table('N_HRIS_Log_Buku_Cuti as log')
                ->leftJoin('HRIS_Buku_Cuti as bc', 'log.Id_Buku_Cuti', '=', 'bc.Id_Buku_Cuti')
                ->leftJoin('N_HRIS_Jenis_Cuti as jc', 'bc.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
                ->whereIn('log.Id_Buku_Cuti', $bukuCutiIds)
                ->select(
                    'log.Id_Log',
                    'log.Jenis_Mutasi',
                    'log.Kategori_Transaksi',
                    'log.Jumlah',
                    'log.Saldo_Sebelum',
                    'log.Saldo_Sesudah',
                    'log.Keterangan',
                    'log.No_Transaksi_Ref',
                    'log.Created_At',
                    'log.Created_By',
                    'jc.Nama_Jenis_Cuti',
                )
                ->orderBy('log.Created_At', 'desc');

            $total = $query->count();
            $offset = ($page - 1) * $perPage;

            $data = $query->offset($offset)->limit($perPage)->get();

            return ResponseHelper::successWithPaginationV2(
                $data,
                $page,
                $perPage,
                $total,
                'Log mutasi berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getLogMutasi error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil log mutasi', 500);
        }
    }

    /**
     * Adjust kuota karyawan (tambah/kurangi)
     */
    public function adjustKuota(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
            'id_jenis_cuti' => ['required', 'integer'],
            'tipe' => ['required', 'in:ADD,SUBTRACT'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'tanggal_berlaku' => ['nullable', 'date'],
            'id_buku_cuti' => ['nullable', 'integer'], // Optional: target wallet for ADD
            'keterangan' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);
        $adminKode = Auth::user()->karyawan->Kode_Karyawan;
        $tanggalBerlaku = $validated['tanggal_berlaku'] ?? Carbon::now()->toDateString();
        $idBukuCuti = $validated['id_buku_cuti'] ?? null;

        $batchId = $this->batchService->startBatch('MANUAL_ADJUSTMENT', [
            'period_start' => $tanggalBerlaku,
            'period_end' => $tanggalBerlaku,
            'is_dry_run' => false,
            'triggered_from' => 'ADMIN',
            'requested_by' => $adminKode,
            'options' => [
                'kode_karyawan' => $kodeKaryawan,
                'id_jenis_cuti' => $validated['id_jenis_cuti'],
                'tipe' => $validated['tipe'],
                'jumlah' => $validated['jumlah'],
                'tanggal_berlaku' => $tanggalBerlaku,
                'id_buku_cuti' => $idBukuCuti,
            ],
        ]);

        try {
            // Use TransactionService for both ADD and SUBTRACT
            // ADD: If id_buku_cuti provided, add to that specific wallet
            // SUBTRACT: FIFO deduction, creates debt if not enough
            $this->transactionService->manualAdjustment(
                $kodeKaryawan,
                $validated['id_jenis_cuti'],
                $validated['tipe'],
                $validated['jumlah'],
                $validated['keterangan'],
                $adminKode,
                $tanggalBerlaku,
                $idBukuCuti,
            );

            $this->batchService->recordDetail($batchId, [
                'source_type' => 'MANUAL_ADJUSTMENT',
                'kode_karyawan' => $kodeKaryawan,
                'id_jenis_cuti' => $validated['id_jenis_cuti'],
                'period_key' => Carbon::parse($tanggalBerlaku)->format('Y-m'),
                'due_date' => $tanggalBerlaku,
                'effective_date' => $tanggalBerlaku,
                'jumlah' => $validated['tipe'] === 'ADD' ? $validated['jumlah'] : -abs((int) $validated['jumlah']),
                'status' => 'SUCCESS',
                'message' => $validated['keterangan'],
                'metadata' => [
                    'tipe' => $validated['tipe'],
                    'id_buku_cuti' => $idBukuCuti,
                ],
            ]);
            $this->batchService->completeBatch($batchId, [
                'checked' => 1,
                'eligible' => 1,
                'success' => 1,
                'dry_run' => 0,
                'failed' => 0,
            ]);

            return ResponseHelper::success(null, 'Kuota berhasil di-adjust', 200);
        } catch (\Exception $e) {
            $this->batchService->recordDetail($batchId, [
                'source_type' => 'MANUAL_ADJUSTMENT',
                'kode_karyawan' => $kodeKaryawan,
                'id_jenis_cuti' => $validated['id_jenis_cuti'],
                'period_key' => Carbon::parse($tanggalBerlaku)->format('Y-m'),
                'due_date' => $tanggalBerlaku,
                'effective_date' => $tanggalBerlaku,
                'jumlah' => $validated['tipe'] === 'ADD' ? $validated['jumlah'] : -abs((int) $validated['jumlah']),
                'status' => 'FAILED',
                'reason_code' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ]);
            $this->batchService->completeBatch(
                $batchId,
                [
                    'checked' => 1,
                    'eligible' => 1,
                    'success' => 0,
                    'dry_run' => 0,
                    'failed' => 1,
                ],
                'COMPLETED_WITH_ERRORS',
            );
            Log::error('KuotaManagement adjustKuota error: '.$e->getMessage());

            return ResponseHelper::error('Gagal meng-adjust kuota: '.$e->getMessage(), 500);
        }
    }

    // =========================================================================
    // TAB CUTI BERSAMA
    // =========================================================================

    /**
     * Get all cuti bersama with calendar data
     */
    public function getCutiBersama(Request $request)
    {
        try {
            $year = $request->input('year', Carbon::now()->year);

            $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')
                ->where('Flag_Aktif', 'Y')
                /* Rentang setengah-terbuka: YEAR(Tanggal_Mulai) mematikan indeks. */
                ->where('Tanggal_Mulai', '>=', Carbon::create((int) $year, 1, 1)->startOfDay())
                ->where('Tanggal_Mulai', '<', Carbon::create((int) $year + 1, 1, 1)->startOfDay())
                ->orderBy('Tanggal_Mulai', 'asc')
                ->get();

            // Format for calendar
            $calendarData = $cutiBersama->map(function ($item) {
                $dates = [];
                $start = Carbon::parse($item->Tanggal_Mulai);
                $end = Carbon::parse($item->Tanggal_Selesai);

                while ($start <= $end) {
                    $dates[] = $start->format('Y-m-d');
                    $start->addDay();
                }

                return [
                    'id' => $item->Id_Cuti_Bersama,
                    'nama' => $item->Nama_Cuti_Bersama,
                    'dates' => $dates,
                    'tanggal_mulai' => $item->Tanggal_Mulai,
                    'tanggal_selesai' => $item->Tanggal_Selesai,
                    'jumlah_hari' => $item->Jumlah_Hari,
                    'potong_ke' => $item->Potong_Ke_Tipe_Cuti,
                ];
            });

            return ResponseHelper::success(
                [
                    'list' => $cutiBersama,
                    'calendar' => $calendarData,
                    'year' => $year,
                ],
                'Data cuti bersama berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement getCutiBersama error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data cuti bersama', 500);
        }
    }

    /**
     * Store cuti bersama baru
     */
    public function storeCutiBersama(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'potong_ke_tipe_cuti' => ['nullable', 'string'],
        ]);

        try {
            $jumlahHari =
                Carbon::parse($validated['tanggal_mulai'])->diffInDays(Carbon::parse($validated['tanggal_selesai'])) +
                1;

            DB::table('N_HRIS_Cuti_Bersama')->insert([
                'Nama_Cuti_Bersama' => $validated['nama'],
                'Tanggal_Mulai' => $validated['tanggal_mulai'],
                'Tanggal_Selesai' => $validated['tanggal_selesai'],
                'Jumlah_Hari' => $jumlahHari,
                'Potong_Ke_Tipe_Cuti' => $validated['potong_ke_tipe_cuti'] ?? 'Tahunan',
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => Auth::user()->Id_Users,
            ]);

            $this->markCutiBersamaDirty(
                $validated['tanggal_mulai'],
                $validated['tanggal_selesai'],
                'CUTI_BERSAMA_LEGACY_CHANGED',
            );

            return ResponseHelper::success(null, 'Cuti bersama berhasil ditambahkan', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement storeCutiBersama error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menambahkan cuti bersama', 500);
        }
    }

    /**
     * Update cuti bersama
     */
    public function updateCutiBersama(Request $request, $id)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'potong_ke_tipe_cuti' => ['nullable', 'string'],
        ]);

        try {
            $jumlahHari =
                Carbon::parse($validated['tanggal_mulai'])->diffInDays(Carbon::parse($validated['tanggal_selesai'])) +
                1;

            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Nama_Cuti_Bersama' => $validated['nama'],
                    'Tanggal_Mulai' => $validated['tanggal_mulai'],
                    'Tanggal_Selesai' => $validated['tanggal_selesai'],
                    'Jumlah_Hari' => $jumlahHari,
                    'Potong_Ke_Tipe_Cuti' => $validated['potong_ke_tipe_cuti'],
                    'Updated_At' => now(),
                    'Updated_By' => Auth::user()->Id_Users,
                ]);

            $this->markCutiBersamaDirty(
                $validated['tanggal_mulai'],
                $validated['tanggal_selesai'],
                'CUTI_BERSAMA_LEGACY_CHANGED',
            );

            return ResponseHelper::success(null, 'Cuti bersama berhasil diperbarui', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement updateCutiBersama error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui cuti bersama', 500);
        }
    }

    /**
     * Delete (soft) cuti bersama
     */
    public function deleteCutiBersama($id)
    {
        try {
            DB::table('N_HRIS_Cuti_Bersama')
                ->where('Id_Cuti_Bersama', $id)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => Auth::user()->Id_Users,
                ]);

            $record = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();
            if ($record) {
                $this->markCutiBersamaDirty(
                    $record->Tanggal_Mulai,
                    $record->Tanggal_Selesai,
                    'CUTI_BERSAMA_LEGACY_CHANGED',
                );
            }

            return ResponseHelper::success(null, 'Cuti bersama berhasil dihapus', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement deleteCutiBersama error: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus cuti bersama', 500);
        }
    }

    /**
     * Preview dampak apply cuti bersama ke semua karyawan
     */
    public function previewApply($id)
    {
        try {
            $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cutiBersama) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }

            $jumlahHari = $cutiBersama->Jumlah_Hari;

            // Get all active employees with their saldo
            $karyawanSaldo = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan')
                ->select('Kode_Karyawan', 'Nama_Karyawan', 'Sisa_Cuti_Tampil', 'Hutang_Cuti_Tampil')
                ->get();

            $hasilPreview = [
                'cukup' => 0,
                'kurang' => 0,
                'sudah_hutang' => 0,
                'detail_kurang' => [],
            ];

            foreach ($karyawanSaldo as $k) {
                if ($k->Hutang_Cuti_Tampil > 0) {
                    $hasilPreview['sudah_hutang']++;
                    $hasilPreview['detail_kurang'][] = [
                        'nama' => $k->Nama_Karyawan,
                        'saldo' => $k->Sisa_Cuti_Tampil,
                        'hutang_tambahan' => $jumlahHari,
                        'total_hutang' => $k->Hutang_Cuti_Tampil + $jumlahHari,
                    ];
                } elseif ($k->Sisa_Cuti_Tampil < $jumlahHari) {
                    $hasilPreview['kurang']++;
                    $hasilPreview['detail_kurang'][] = [
                        'nama' => $k->Nama_Karyawan,
                        'saldo' => $k->Sisa_Cuti_Tampil,
                        'hutang_tambahan' => $jumlahHari - $k->Sisa_Cuti_Tampil,
                        'total_hutang' => $jumlahHari - $k->Sisa_Cuti_Tampil,
                    ];
                } else {
                    $hasilPreview['cukup']++;
                }
            }

            return ResponseHelper::success(
                [
                    'cuti_bersama' => $cutiBersama,
                    'preview' => $hasilPreview,
                    'total_karyawan' => count($karyawanSaldo),
                ],
                'Preview berhasil',
                200,
            );
        } catch (\Exception $e) {
            Log::error('KuotaManagement previewApply error: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat preview', 500);
        }
    }

    /**
     * Execute apply cuti bersama ke semua karyawan
     */
    public function executeApply(Request $request, $id)
    {
        $validated = $request->validate([
            'id_jenis_cuti' => ['required', 'integer'],
        ]);

        DB::beginTransaction();
        try {
            $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();

            if (! $cutiBersama) {
                return ResponseHelper::error('Cuti bersama tidak ditemukan', 404);
            }

            $jumlahHari = $cutiBersama->Jumlah_Hari;
            $adminKode = Auth::user()->karyawan->Kode_Karyawan;

            // Get all active employees
            $karyawanList = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->whereNull('Tanggal_Resign')
                ->pluck('Kode_Karyawan');

            $successCount = 0;
            $errorCount = 0;

            foreach ($karyawanList as $kodeKaryawan) {
                try {
                    $this->transactionService->manualAdjustment(
                        $kodeKaryawan,
                        $validated['id_jenis_cuti'],
                        'SUB',
                        $jumlahHari,
                        "Pemotongan Cuti Bersama: {$cutiBersama->Nama_Cuti_Bersama}",
                        $adminKode,
                    );
                    $successCount++;
                    $dirtyTargets[] = [
                        'kode_karyawan' => $kodeKaryawan,
                        'tgl_awal' => $cutiBersama->Tanggal_Mulai,
                        'tgl_akhir' => $cutiBersama->Tanggal_Selesai,
                    ];
                } catch (\Exception $e) {
                    Log::warning("Execute apply failed for {$kodeKaryawan}: ".$e->getMessage());
                    $errorCount++;
                }
            }

            DB::commit();
            $this->attendanceDirtyResolver->markMany(
                $dirtyTargets ?? [],
                'CUTI_BERSAMA_EXECUTED',
                'KUOTA_MANAGEMENT_CUTI_BERSAMA',
                4,
            );

            return ResponseHelper::success(
                [
                    'success' => $successCount,
                    'error' => $errorCount,
                ],
                "Eksekusi selesai. Berhasil: {$successCount}, Gagal: {$errorCount}",
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('KuotaManagement executeApply error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengeksekusi pemotongan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================

    private function parseApprovalStatus($flagApproval, $status)
    {
        // Transaksi_Cuti.Status: NULL = aktif, 'Y' = dibatalkan
        if (strtoupper(trim((string) $status)) === 'Y') {
            return 'cancelled';
        }
        if ($flagApproval === 'Y') {
            return 'approved';
        }
        if ($flagApproval === 'T') {
            return 'rejected';
        }

        return 'pending';
    }

    private function markCutiBersamaDirty(string $tglAwal, string $tglAkhir, string $reason): void
    {
        $targets = DB::table('Karyawan')->where('Aktif', 'Y')->whereNull('Tanggal_Resign')->pluck('Kode_Karyawan')->map(
            fn ($kode) => [
                'kode_karyawan' => $kode,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
            ],
        );

        $this->attendanceDirtyResolver->markMany($targets, $reason, 'KUOTA_MANAGEMENT_CUTI_BERSAMA', 4);
    }

    /**
     * Get jenis cuti options for dropdown
     */
    public function getJenisCutiOptions()
    {
        try {
            $options = DB::table('N_HRIS_Jenis_Cuti')
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Id_Jenis_Cuti', self::ID_CUTI_AKUMULASI)
                ->select('Id_Jenis_Cuti', 'Nama_Jenis_Cuti')
                ->orderBy('Nama_Jenis_Cuti')
                ->get();

            return ResponseHelper::success($options, 'Options berhasil diambil', 200);
        } catch (\Exception $e) {
            return ResponseHelper::galatServer($e, 'Gagal mengambil options');
        }
    }

    /**
     * Get divisi options for filter
     */
    public function getDivisiOptions()
    {
        try {
            $options = DB::table('View_Divisi_Sub_Divisi')
                ->select('ID_Divisi', 'nama_divisi')
                ->orderBy('nama_divisi')
                ->groupBy('ID_Divisi', 'nama_divisi')
                ->get();

            return ResponseHelper::success($options, 'Options berhasil diambil', 200);
        } catch (\Exception $e) {
            return ResponseHelper::galatServer($e, 'Gagal mengambil options');
        }
    }

    /**
     * Get karyawan options for searchable recovery multiselect
     */
    public function getKaryawanOptions(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        try {
            $search = trim(strip_tags($validated['search'] ?? ''));
            $limit = $validated['limit'] ?? 20;

            $query = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                )
                ->where('k.Aktif', 'Y')
                ->whereNull('k.Tanggal_Resign')
                ->select('k.Kode_Karyawan', 'k.Nama as nama_karyawan', 'vd.nama_divisi', 'vg.nama_jabatan');

            if ($search !== '') {
                $query->where(function ($builder) use ($search) {
                    $builder
                        ->where('k.Kode_Karyawan', 'like', "%{$search}%")
                        ->orWhere('k.Nama', 'like', "%{$search}%")
                        ->orWhere('vd.nama_divisi', 'like', "%{$search}%")
                        ->orWhere('vg.nama_jabatan', 'like', "%{$search}%");
                });
            }

            $options = $query
                ->orderBy('k.Nama', 'asc')
                ->limit($limit)
                ->get()
                ->map(function ($item) {
                    $labelParts = array_filter([
                        $item->Kode_Karyawan ?? null,
                        $item->nama_karyawan ?? null,
                        $item->nama_divisi ? "({$item->nama_divisi})" : null,
                    ]);

                    return [
                        'kode_karyawan' => $item->Kode_Karyawan,
                        'label' => $item->nama_karyawan,
                        'nama_karyawan' => $item->nama_karyawan,
                        'nama_divisi' => $item->nama_divisi,
                        'nama_jabatan' => $item->nama_jabatan,
                    ];
                });

            return ResponseHelper::success($options, 'Options berhasil diambil', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement getKaryawanOptions error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil options karyawan', 500);
        }
    }

    /**
     * Get available wallets for a karyawan and jenis cuti (for dropdown selection)
     */
    public function getAvailableWallets(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string'],
            'id_jenis_cuti' => ['required', 'integer'],
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);
            $currentYear = Carbon::now()->year;

            // Get all active wallets for this karyawan and jenis cuti
            $wallets = DB::table('HRIS_Buku_Cuti as bc')
                ->leftJoin('N_HRIS_Jenis_Cuti as jc', 'bc.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
                ->where('bc.Kode_Karyawan', $kodeKaryawan)
                ->where('bc.Id_Jenis_Cuti', $validated['id_jenis_cuti'])
                ->where('bc.Flag_Aktif', 'Y')
                ->where('bc.Tanggal_Expired', '>=', now())
                ->select(
                    'bc.Id_Buku_Cuti',
                    'bc.Periode',
                    'bc.Activated_At',
                    'bc.tanggal_expired',
                    'bc.sisa_cuti',
                    'bc.notes',
                    'jc.Nama_Jenis_Cuti',
                    DB::raw("CASE WHEN bc.Periode < {$currentYear} THEN 1 ELSE 0 END as is_carry_over"),
                )
                ->orderBy('bc.Periode', 'desc')
                ->orderBy('bc.tanggal_expired', 'desc')
                ->get();

            // Format for dropdown display
            $formatted = $wallets->map(function ($w) use ($currentYear) {
                $noteValue = strtolower(trim((string) ($w->notes ?? '')));
                $walletTypeLabel = null;
                if (str_contains($noteValue, 'hutang') && str_contains($noteValue, 'cuti bersama')) {
                    $walletTypeLabel = 'Dompet Hutang Cuti Bersama';
                } elseif ($noteValue === 'hutang_cuti') {
                    $walletTypeLabel = 'Dompet Hutang Cuti';
                } elseif ($noteValue === 'saldo_cuti') {
                    $walletTypeLabel = 'Dompet Saldo Cuti';
                }

                $label = "Periode {$w->Periode}";
                if ($w->Periode < $currentYear) {
                    $label .= ' (Carry Over)';
                }
                if ($w->Activated_At > now()) {
                    $label .= ' - Akti pada '.Carbon::parse($w->Activated_At)->format('d/m/Y').' (Belum Aktif)';
                }
                $label .= ' - Exp: '.Carbon::parse($w->tanggal_expired)->format('d/m/Y');
                $label .= " | Sisa: {$w->sisa_cuti}";
                if ($walletTypeLabel) {
                    $label .= " | {$walletTypeLabel}";
                }

                return [
                    'id' => $w->Id_Buku_Cuti,
                    'label' => $label,
                    'periode' => $w->Periode,
                    'expired' => $w->tanggal_expired,
                    'activated_at' => $w->Activated_At,
                    'sisa' => $w->sisa_cuti,
                    'wallet_type_label' => $walletTypeLabel,
                    'is_carry_over' => $w->is_carry_over == 1,
                ];
            });

            return ResponseHelper::success($formatted, 'Wallets berhasil diambil', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement getAvailableWallets error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil wallets: '.$e->getMessage(), 500);
        }
    }

    public function getProcessDashboard()
    {
        if (! $this->batchService->isAvailable()) {
            return ResponseHelper::success(
                [
                    'installed' => false,
                    'message' => 'Table process batch belum tersedia. Jalankan SQL database/sql/cuti_process_batch.sql.',
                ],
                'Process batch belum tersedia',
                200,
            );
        }

        try {
            $ttl = max(1, CutiProcessBatchService::DASHBOARD_CACHE_TTL_MINUTES);
            $payload = Cache::remember(
                CutiProcessBatchService::DASHBOARD_CACHE_KEY,
                now()->addMinutes($ttl),
                function () {
                    $today = Carbon::now()->startOfDay();
                    $periodKey = $today->format('Y-m');

                    $summary = DB::table('N_HRIS_Cuti_Process_Batch')
                        ->where('Is_Dry_Run', 0)
                        ->selectRaw(
                            "
                        COUNT(*) as total_batch,
                        SUM(CASE WHEN Status = 'RUNNING' THEN 1 ELSE 0 END) as running_batch,
                        SUM(CASE WHEN Status = 'COMPLETED_WITH_ERRORS' THEN 1 ELSE 0 END) as warning_batch,
                        SUM(CASE WHEN Status = 'FAILED' THEN 1 ELSE 0 END) as failed_batch,
                        SUM(Total_Success) as total_success,
                        SUM(Total_Failed) as total_failed
                    ",
                        )
                        ->first();

                    $latestBatches = DB::table('N_HRIS_Cuti_Process_Batch')
                        ->where('Is_Dry_Run', 0)
                        ->orderByDesc('Created_At')
                        ->limit(8)
                        ->get();

                    $failedDetails = DB::table('N_HRIS_Cuti_Process_Batch_Detail as d')
                        ->join('N_HRIS_Cuti_Process_Batch as b', 'd.Id_Batch', '=', 'b.Id_Batch')
                        ->where('b.Is_Dry_Run', 0)
                        ->where('d.Status', CutiProcessBatchService::DETAIL_FAILED)
                        ->select('d.*', 'b.Batch_Key', 'b.Status as Batch_Status')
                        ->orderByDesc('d.Created_At')
                        ->limit(10)
                        ->get();

                    $hutangCount = DB::table('HRIS_Buku_Cuti')
                        ->where('Flag_Aktif', 'Y')
                        ->where('sisa_cuti', '<', 0)
                        ->distinct()
                        ->count('Kode_Karyawan');

                    $activeNoAnnualBalance = DB::table('Karyawan as k')
                        ->where('k.Aktif', 'Y')
                        ->whereNull('k.Tanggal_Resign')
                        ->whereNotExists(function ($q) use ($today) {
                            $q->select(DB::raw(1))
                                ->from('HRIS_Buku_Cuti as bc')
                                ->whereColumn('bc.Kode_Karyawan', 'k.Kode_Karyawan')
                                ->where('bc.Id_Jenis_Cuti', self::ID_CUTI_TAHUNAN)
                                ->where('bc.Flag_Aktif', 'Y')
                                ->where('bc.sisa_cuti', '>', 0)
                                ->whereDate('bc.tanggal_expired', '>=', $today->toDateString());
                        })
                        ->count();

                    $missingMonthly = $this->countMissingMonthlyAccrual($today);

                    return [
                        'installed' => true,
                        'summary' => [
                            'total_batch' => (int) ($summary->total_batch ?? 0),
                            'running_batch' => (int) ($summary->running_batch ?? 0),
                            'warning_batch' => (int) ($summary->warning_batch ?? 0),
                            'failed_batch' => (int) ($summary->failed_batch ?? 0),
                            'total_success' => (int) ($summary->total_success ?? 0),
                            'total_failed' => (int) ($summary->total_failed ?? 0),
                            'karyawan_hutang' => (int) $hutangCount,
                            'aktif_tanpa_saldo_tahunan' => (int) $activeNoAnnualBalance,
                            'missing_monthly_accrual' => (int) $missingMonthly,
                            'period_key' => $periodKey,
                        ],
                        'latest_batches' => $latestBatches,
                        'failed_details' => $failedDetails,
                        'cached_at' => Carbon::now()->toIso8601String(),
                    ];
                },
            );

            return ResponseHelper::success($payload, 'Dashboard process batch berhasil diambil', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement getProcessDashboard error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil dashboard process batch: '.$e->getMessage(), 500);
        }
    }

    /**
     * Manual cache flush endpoint untuk dashboard process batch.
     */
    public function flushProcessDashboardCache()
    {
        try {
            $this->forgetDashboardCache();

            return ResponseHelper::success(['flushed' => true], 'Cache dashboard process batch berhasil di-flush', 200);
        } catch (\Exception $e) {
            return ResponseHelper::galatServer($e, 'Gagal flush cache dashboard: '.$e->getMessage());
        }
    }

    private function forgetDashboardCache(): void
    {
        CutiProcessBatchService::flushAllDashboardCaches();
    }

    // =========================================================================
    // DASHBOARD WIDGETS
    // 4 widget utama untuk monitoring akrual cuti + 3 detail drill-down.
    // Setiap widget di-cache 5 menit. Cache di-flush saat scheduler/recovery jalan.
    // =========================================================================

    public function widgetHeatmapAkrual(Request $request)
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'in:ALL,MONTHLY_ACCRUAL,LOYALTY_ACCRUAL'],
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $source = ($validated['source'] ?? 'ALL') === 'ALL' ? null : $validated['source'];
        $days = (int) ($validated['days'] ?? 30);
        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;
        $cacheSuffix =
            ($validated['source'] ?? 'ALL').".{$days}.".($month ?: 'current').'.'.($year ?: 'current');

        return $this->cachedDashboardWidget(
            "heatmap-akrual.{$cacheSuffix}",
            fn () => $this->dashboardService->heatmapAkrual($days, $source, $month, $year),
        );
    }

    public function widgetTrenAkrualBulanan(Request $request)
    {
        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'end_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $months = (int) ($validated['months'] ?? 6);
        $endMonth = $validated['end_month'] ?? null;
        $endYear = $validated['end_year'] ?? null;
        $cacheSuffix = $months.'.'.($endMonth ?: 'current').'.'.($endYear ?: 'current');

        return $this->cachedDashboardWidget(
            "tren-akrual-bulanan.{$cacheSuffix}",
            fn () => $this->dashboardService->trenAkrualBulanan($months, $endMonth, $endYear),
        );
    }

    public function widgetTrenAkrualLoyalty(Request $request)
    {
        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'end_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'end_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $months = (int) ($validated['months'] ?? 6);
        $endMonth = $validated['end_month'] ?? null;
        $endYear = $validated['end_year'] ?? null;
        $cacheSuffix = $months.'.'.($endMonth ?: 'current').'.'.($endYear ?: 'current');

        return $this->cachedDashboardWidget(
            "tren-akrual-loyalty.{$cacheSuffix}",
            fn () => $this->dashboardService->trenAkrualLoyalty($months, $endMonth, $endYear),
        );
    }

    public function widgetAnniversaryBulanIni(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;
        $cacheSuffix = ($month ?: 'current').'.'.($year ?: 'current');

        return $this->cachedDashboardWidget(
            "anniversary-bulan-ini.v2.{$cacheSuffix}",
            fn () => $this->dashboardService->anniversaryBulanIni($month, $year),
        );
    }

    public function widgetMonthlyAnniversaryCuti(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $month = $validated['month'] ?? null;
        $year = $validated['year'] ?? null;
        $cacheSuffix = ($month ?: 'current').'.'.($year ?: 'current');

        return $this->cachedDashboardWidget(
            "monthly-anniversary-cuti.v2.{$cacheSuffix}",
            fn () => $this->dashboardService->monthlyAnniversaryCuti($month, $year),
        );
    }

    public function widgetSaldoAkanAktif(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $days = (int) ($validated['days'] ?? 30);

        return $this->cachedDashboardWidget(
            "saldo-akan-aktif.{$days}",
            fn () => $this->dashboardService->saldoAkanAktif($days),
        );
    }

    public function widgetKaryawanBerpotensiKonflik()
    {
        return $this->cachedDashboardWidget(
            'karyawan-berpotensi-konflik',
            fn () => $this->dashboardService->karyawanBerpotensiKonflik(),
        );
    }

    public function widgetHeatmapAkrualDetail(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'source' => ['nullable', 'string', 'in:ALL,MONTHLY_ACCRUAL,LOYALTY_ACCRUAL'],
        ]);

        $source = ($validated['source'] ?? 'ALL') === 'ALL' ? null : $validated['source'];

        return ResponseHelper::success(
            $this->dashboardService->heatmapDetail($validated['date'], $source),
            'Detail heatmap akrual berhasil diambil',
            200,
        );
    }

    public function widgetTrenAkrualDetail(Request $request)
    {
        $validated = $request->validate([
            'period_key' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'source_type' => ['required', 'string', 'in:MONTHLY_ACCRUAL,LOYALTY_ACCRUAL'],
        ]);

        return ResponseHelper::success(
            $this->dashboardService->trendDetail($validated['period_key'], $validated['source_type']),
            'Detail trend akrual berhasil diambil',
            200,
        );
    }

    public function widgetAnniversaryDetail(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => ['required', 'string', 'max:50'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        return ResponseHelper::success(
            $this->dashboardService->anniversaryDetail($validated['kode_karyawan'], $validated['year'] ?? null),
            'Detail anniversary berhasil diambil',
            200,
        );
    }

    /**
     * Helper cache untuk widget utama. Kegagalan query tetap dikemas oleh
     * CutiDashboardService::safeRun(), sehingga dashboard tidak ikut tumbang.
     */
    private function cachedDashboardWidget(string $widgetKey, \Closure $callback)
    {
        try {
            $ttl = max(1, CutiProcessBatchService::DASHBOARD_CACHE_TTL_MINUTES);
            $cacheKey = "cuti.dashboard.widget.{$widgetKey}.v4";
            $payload = Cache::remember($cacheKey, now()->addMinutes($ttl), $callback);

            return ResponseHelper::success($payload, "Widget {$widgetKey} berhasil diambil", 200);
        } catch (\Throwable $e) {
            Log::error("Widget {$widgetKey} controller error", [
                'widget' => $widgetKey,
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            return ResponseHelper::error("Widget {$widgetKey} gagal: ".$e->getMessage(), 500);
        }
    }

    public function getProcessBatches(Request $request)
    {
        if (! $this->batchService->isAvailable()) {
            return ResponseHelper::successWithPaginationV2([], 1, 10, 0, 'Process batch belum tersedia', 200);
        }

        $validated = $request->validate([
            'source_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:40'],
            'origin' => ['nullable', 'string', 'max:40'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 10;

        $query = DB::table('N_HRIS_Cuti_Process_Batch as b')
            ->leftJoin('Karyawan as admin', 'b.Requested_By', '=', 'admin.Kode_Karyawan')
            ->where('b.Is_Dry_Run', 0);
        if (! empty($validated['source_type'])) {
            $sourceType = $validated['source_type'];
            $query->where(function ($q) use ($sourceType) {
                $q->where('b.Source_Type', $sourceType)->orWhereRaw(
                    "JSON_VALUE(CASE WHEN ISJSON(b.Options_Json) = 1 THEN b.Options_Json ELSE NULL END, '$.source_type') = ?",
                    [$sourceType],
                );
            });
        }
        if (! empty($validated['status'])) {
            $query->where('b.Status', $validated['status']);
        }
        if (! empty($validated['origin'])) {
            $origin = strtoupper($validated['origin']);
            if ($origin === 'ADMIN_RECOVERY') {
                $query->where('b.Source_Type', CutiProcessBatchService::SOURCE_MANUAL_RECOVERY);
            } elseif ($origin === 'SCHEDULER_SYSTEM') {
                $query
                    ->where('b.Source_Type', '!=', CutiProcessBatchService::SOURCE_MANUAL_RECOVERY)
                    ->where('b.Triggered_From', 'COMMAND')
                    ->whereIn('b.Requested_By', ['SYSTEM_SCHEDULER', 'SYSTEM_LOYALTY']);
            } elseif ($origin === 'OTHER') {
                $query
                    ->where(function ($q) {
                        $q->where('b.Source_Type', CutiProcessBatchService::SOURCE_MANUAL_RECOVERY)
                            ->orWhere('b.Triggered_From', '!=', 'COMMAND')
                            ->orWhereNotIn('b.Requested_By', ['SYSTEM_SCHEDULER', 'SYSTEM_LOYALTY'])
                            ->orWhereNull('b.Triggered_From')
                            ->orWhereNull('b.Requested_By');
                    })
                    ->where('b.Source_Type', '!=', CutiProcessBatchService::SOURCE_MANUAL_RECOVERY);
            }
        }
        if (! empty($validated['created_from'])) {
            $query->whereDate('b.Created_At', '>=', $validated['created_from']);
        }
        if (! empty($validated['created_to'])) {
            $query->whereDate('b.Created_At', '<=', $validated['created_to']);
        }

        $total = (clone $query)->count('b.Id_Batch');
        $rows = $query
            ->select(
                'b.*',
                'admin.Nama as Requested_By_Name',
                DB::raw(
                    "JSON_VALUE(CASE WHEN ISJSON(b.Options_Json) = 1 THEN b.Options_Json ELSE NULL END, '$.source_type') as Recovery_Source_Type",
                ),
            )
            ->orderByDesc('b.Created_At')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return ResponseHelper::successWithPaginationV2($rows, $page, $perPage, $total, 'Batch berhasil diambil', 200);
    }

    public function getProcessBatchDetails(Request $request, $id)
    {
        if (! $this->batchService->isAvailable()) {
            return ResponseHelper::successWithPaginationV2([], 1, 25, 0, 'Process batch belum tersedia', 200);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 25;

        $query = DB::table('N_HRIS_Cuti_Process_Batch_Detail')->where('Id_Batch', $id);
        if (! empty($validated['status'])) {
            $query->where('Status', $validated['status']);
        }

        $total = $query->count();
        $rows = $query
            ->orderByDesc('Created_At')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return ResponseHelper::successWithPaginationV2(
            $rows,
            $page,
            $perPage,
            $total,
            'Detail batch berhasil diambil',
            200,
        );
    }

    public function previewRecovery(RecoveryPreviewRequest $request)
    {
        $validated = $request->validatedRecovery();

        try {
            $result = $this->processRecovery($validated, true);
            $result['preview_token'] = $this->makePreviewToken($result['items']);

            return ResponseHelper::success($result, 'Preview recovery berhasil dibuat', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement previewRecovery error', [
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            return ResponseHelper::error('Gagal membuat preview recovery: '.$e->getMessage(), 500);
        }
    }

    public function executeRecovery(RecoveryPreviewRequest $request)
    {
        try {
            Gate::authorize('manageKuotaRecovery');
        } catch (AuthorizationException $e) {
            return ResponseHelper::error('Anda tidak memiliki izin untuk eksekusi recovery.', 403);
        }

        $validated = $request->validatedRecovery();
        $providedToken = (string) $request->input('preview_token', '');

        try {
            $previewResult = $this->processRecovery($validated, true);
            $expectedToken = $this->makePreviewToken($previewResult['items']);

            if (! hash_equals($expectedToken, $providedToken)) {
                return ResponseHelper::error(
                    'Data preview sudah berubah sejak Preview terakhir. Jalankan Preview lagi sebelum Execute.',
                    409,
                );
            }

            $result = $this->processRecovery($validated, false);
            $this->forgetDashboardCache();

            return ResponseHelper::success($result, 'Recovery berhasil dieksekusi', 200);
        } catch (\Exception $e) {
            Log::error('KuotaManagement executeRecovery error', [
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 500),
            ]);

            return ResponseHelper::error('Gagal eksekusi recovery: '.$e->getMessage(), 500);
        }
    }

    /**
     * Hash deterministic dari items preview. Sort by Kode_Karyawan + Period_Key agar
     * stabil walaupun query ordering berubah, lalu hash JSON.
     */
    private function makePreviewToken(array $items): string
    {
        $normalized = collect($items)
            ->map(
                fn (array $i) => [
                    'kode' => $i['kode_karyawan'] ?? null,
                    'period' => $i['period_key'] ?? null,
                    'source' => $i['source_type'] ?? null,
                    'status' => $i['status'] ?? null,
                    'due_date' => $i['due_date'] ?? null,
                    'cuti_key' => $i['cuti_key'] ?? null,
                    'jumlah' => $i['jumlah'] ?? null,
                ],
            )
            ->sortBy(fn (array $i) => ($i['kode'] ?? '').'|'.($i['period'] ?? ''))
            ->values()
            ->all();

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function processRecovery(array $validated, bool $dryRun): array
    {
        $adminKode = Auth::user()->karyawan->Kode_Karyawan ?? 'ADMIN';
        $items = $this->buildRecoveryItems($validated);
        $summary = [
            'checked' => count($items),
            'eligible' => 0,
            'success' => 0,
            'dry_run' => 0,
            'skipped_existing_key' => 0,
            'skipped_rule' => 0,
            'skipped_not_due' => 0,
            'legacy_before_cutoff' => 0,
            'failed' => 0,
        ];

        $targetDueDates = collect($validated['due_dates'] ?? [])
            ->map(fn ($date) => Carbon::createFromFormat('Y-m-d', $date)->startOfDay())
            ->values();
        $firstDueDate = $targetDueDates->first();
        $lastDueDate = $targetDueDates->last();

        $periodStarts = collect($validated['months'])
            ->map(fn ($month) => Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth())
            ->sortBy(fn (Carbon $date) => $date->toDateString())
            ->values();
        $firstPeriod = $firstDueDate ?: $periodStarts->first();
        $lastPeriod = $lastDueDate ?: $periodStarts->last();
        $batchId = $this->batchService->startBatch(CutiProcessBatchService::SOURCE_MANUAL_RECOVERY, [
            'period_start' => $firstPeriod?->toDateString(),
            'period_end' => $lastDueDate
                ? $lastDueDate->toDateString()
                : $lastPeriod?->copy()->endOfMonth()->toDateString(),
            'is_dry_run' => $dryRun,
            'triggered_from' => 'ADMIN',
            'requested_by' => $adminKode,
            'status' => $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : CutiProcessBatchService::STATUS_RUNNING,
            'options' => [
                'origin' => 'KUOTA_MANAGEMENT_RECOVERY_PAGE',
                'origin_label' => 'Admin manual dari page Recover',
                'source_type' => $validated['source_type'],
                'employee_scope' => $validated['employee_scope'] ?? 'selected',
                'months' => $validated['months'],
                'due_dates' => $validated['due_dates'] ?? [],
                'due_date' => $validated['due_date'] ?? null,
                'kode_karyawan' => $validated['kode_karyawan'],
                'keterangan' => $validated['keterangan'] ?? null,
            ],
        ]);

        foreach ($items as &$item) {
            if ($item['status'] !== 'ELIGIBLE') {
                $this->applyRecoverySummaryStatus($summary, $item['status']);
                $this->recordRecoveryDetail($batchId, $item);

                continue;
            }

            $summary['eligible']++;

            if ($dryRun) {
                $item['status'] = CutiProcessBatchService::DETAIL_DRY_RUN;
                $item['message'] = 'Preview recovery, saldo belum diubah.';
                $summary['dry_run']++;
                $this->recordRecoveryDetail($batchId, $item);

                continue;
            }

            $this->executeRecoveryItem($item, $adminKode, $batchId, $summary, $validated['source_type']);
        }
        unset($item);

        $this->batchService->completeBatch(
            $batchId,
            $summary,
            $dryRun ? CutiProcessBatchService::STATUS_DRY_RUN : null,
        );

        return [
            'batch_id' => $batchId,
            'summary' => $summary,
            'items' => $items,
        ];
    }

    /**
     * Eksekusi 1 item recovery dalam DB::transaction sendiri.
     * Per-item transaction memastikan: bila satu karyawan gagal, sukses sebelumnya
     * tetap commit. Catch QueryException unique violation → SKIPPED_EXISTING_KEY
     * (race-safe: dua admin/scheduler concurrent tidak akan double-credit).
     */
    private function executeRecoveryItem(
        array &$item,
        string $adminKode,
        ?int $batchId,
        array &$summary,
        string $sourceType,
    ): void {
        $bulanTahun = Carbon::parse($item['due_date'])->format('m-y');
        $refPrefix = $sourceType === CutiProcessBatchService::SOURCE_LOYALTY_ACCRUAL ? 'RECLOYALTY' : 'RECCUTI';
        $refNo = $this->transactionService->getNoTransaction(
            'N_HRIS_Log_Buku_Cuti',
            $refPrefix,
            'No_Transaksi_Ref',
            $bulanTahun,
        );

        try {
            DB::transaction(function () use (&$item, $refNo, $adminKode, $batchId) {
                $processed = $this->transactionService->creditBalance(
                    $item['kode_karyawan'],
                    $item['id_jenis_cuti'],
                    $item['jumlah'],
                    $refNo,
                    $item['kategori_transaksi'],
                    $item['reason'],
                    $adminKode,
                    $item['activated_at'],
                    $item['expired_date'],
                    $item['periode'],
                    $item['cuti_key'],
                    $item['due_date'],
                );

                if ($processed === false) {
                    $item['status'] = CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY;
                    $item['reason_code'] = 'CUTI_KEY_EXISTS_DB_GUARD';
                    $item['message'] = 'Cuti key sudah ada saat final guard.';
                } else {
                    $item['status'] = CutiProcessBatchService::DETAIL_SUCCESS;
                    $item['message'] = 'Recovery berhasil dieksekusi.';
                    $item['no_transaksi_ref'] = $refNo;
                    $item['id_log'] = $this->batchService->findLogIdsByCutiKey($item['cuti_key'])[0] ?? null;
                }

                $this->recordRecoveryDetail($batchId, $item);
            });

            if ($item['status'] === CutiProcessBatchService::DETAIL_SUCCESS) {
                $summary['success']++;
            } elseif ($item['status'] === CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY) {
                $summary['skipped_existing_key']++;
            }
        } catch (QueryException $e) {
            if ($this->batchService->isCutiKeyUniqueViolation($e)) {
                $item['status'] = CutiProcessBatchService::DETAIL_SKIPPED_EXISTING_KEY;
                $item['reason_code'] = 'CUTI_KEY_UNIQUE_VIOLATION';
                $item['message'] = 'Cuti key duplicate dideteksi di DB level.';
                $item['no_transaksi_ref'] = $refNo;
                $summary['skipped_existing_key']++;
                $this->recordRecoveryDetail($batchId, $item);

                return;
            }
            $this->recordRecoveryFailure($e, $item, $refNo, $batchId, $summary);
        } catch (\Throwable $e) {
            $this->recordRecoveryFailure($e, $item, $refNo, $batchId, $summary);
        }
    }

    private function recordRecoveryFailure(
        \Throwable $e,
        array &$item,
        ?string $refNo,
        ?int $batchId,
        array &$summary,
    ): void {
        $item['status'] = CutiProcessBatchService::DETAIL_FAILED;
        $item['reason_code'] = 'EXCEPTION';
        $item['message'] = Str::limit($e->getMessage(), 950);
        $item['no_transaksi_ref'] = $refNo;
        $summary['failed']++;
        $this->recordRecoveryDetail($batchId, $item);

        Log::channel('daily')->error('Recovery item failed', [
            'kode_karyawan' => $item['kode_karyawan'] ?? null,
            'cuti_key' => $item['cuti_key'] ?? null,
            'batch_id' => $batchId,
            'error_class' => $e::class,
            'error_message' => Str::limit($e->getMessage(), 500),
        ]);
    }

    private function buildRecoveryItems(array $validated): array
    {
        $targetDueDates = collect($validated['due_dates'] ?? [])
            ->map(fn ($date) => (string) $date)
            ->filter()
            ->values()
            ->all();
        $karyawans = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
            ->whereIn('k.Kode_Karyawan', $validated['kode_karyawan'])
            ->select('k.*', 'g.ID_Golongan', 'g.nama_golongan')
            ->get()
            ->keyBy('Kode_Karyawan');

        $items = [];
        foreach ($validated['months'] as $month) {
            foreach ($validated['kode_karyawan'] as $kode) {
                $karyawan = $karyawans[$kode] ?? null;
                if (! $karyawan) {
                    if (! empty($targetDueDates)) {
                        continue;
                    }

                    $items[] = [
                        'source_type' => $validated['source_type'],
                        'kode_karyawan' => $kode,
                        'period_key' => $month,
                        'status' => 'SKIPPED_NOT_ELIGIBLE',
                        'reason_code' => 'KARYAWAN_NOT_FOUND',
                        'message' => 'Kode karyawan tidak ditemukan.',
                    ];

                    continue;
                }

                $item =
                    $validated['source_type'] === 'LOYALTY_ACCRUAL'
                        ? $this->buildLoyaltyRecoveryItem($karyawan, $month, $validated['keterangan'] ?? null)
                        : $this->buildMonthlyRecoveryItem($karyawan, $month, $validated['keterangan'] ?? null);

                if ($targetDueDates && ! in_array((string) ($item['due_date'] ?? ''), $targetDueDates, true)) {
                    continue;
                }

                $items[] = $item;
            }
        }

        return $items;
    }

    private function buildMonthlyRecoveryItem(object $karyawan, string $month, ?string $keterangan): array
    {
        $periodStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $periodEnd = $periodStart->isSameMonth(Carbon::now())
            ? Carbon::now()->startOfDay()
            : $periodStart->copy()->endOfMonth()->startOfDay();

        $base = [
            'source_type' => 'MONTHLY_ACCRUAL',
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'nama_karyawan' => $karyawan->Nama,
            'id_jenis_cuti' => self::ID_CUTI_TAHUNAN,
            'period_key' => $month,
        ];

        $allRules = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get();
        $rule = $allRules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);
        if (! $rule) {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'RULE_NOT_FOUND',
                'message' => 'Tidak ada aturan kelayakan cuti.',
            ];
        }

        $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
        if (empty($karyawan->$kolomAcuan)) {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'REF_DATE_EMPTY',
                'message' => "Kolom acuan {$kolomAcuan} kosong.",
            ];
        }

        $tglStart = Carbon::parse($karyawan->$kolomAcuan);
        $dueDate = AturanAkrualCuti::jatuhTempoBulanan($tglStart, $periodStart);
        $base['due_date'] = $dueDate->toDateString();
        $base['before_cutoff'] = $dueDate->lt($this->cutoffDate());

        if ($dueDate->lt($periodStart) || $dueDate->gt($periodEnd)) {
            return $base + [
                'status' => 'SKIPPED_NOT_DUE',
                'reason_code' => 'DUE_DATE_OUTSIDE_PERIOD',
                'message' => 'Due date tidak masuk periode target.',
            ];
        }

        $cutiKey = "CUTI_MONTHLY:{$month}:{$karyawan->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
        $base['cuti_key'] = $cutiKey;
        if (DB::table('N_HRIS_Log_Buku_Cuti')->where('Cuti_Key', $cutiKey)->exists()) {
            return $base + [
                'status' => 'SKIPPED_EXISTING_KEY',
                'reason_code' => 'CUTI_KEY_EXISTS',
                'message' => 'Cuti key sudah pernah tercatat.',
            ];
        }

        $masaKerjaBulan = $tglStart->diffInMonths($dueDate);
        if ($masaKerjaBulan < $rule->Bulan_Minimum) {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'MINIMUM_TENURE_NOT_MET',
                'message' => "Masa kerja {$masaKerjaBulan} bulan kurang dari minimum {$rule->Bulan_Minimum} bulan.",
            ];
        }

        $cycleInfo = AturanAkrualCuti::siklusTahunKerja(Carbon::parse($karyawan->Tgl_Masuk), $dueDate);
        /*
         * Aturan "berapa hari" tinggal di rumah bersamanya. Berkas ini adalah
         * SALINAN KETIGA-nya, yang terlewat saat dua salinan pertama dipusatkan;
         * ia ketahuan lewat sapuan menyeluruh, bukan lewat pemindai pasangan.
         */
        $jumlah = AturanAkrualCuti::jumlahBulanan(
            $karyawan->ID_Golongan ?? null,
            $karyawan->status_kontrak ?? null,
            $cycleInfo,
        );
        $jumlahNote = $jumlah === 2
            ? 'golongan 6 PKWTT pada paruh kedua tahun kerja, jatah 2 hari'
            : 'jatah standar 1 hari';

        $activatedAt = $dueDate->copy();
        if ($masaKerjaBulan < $rule->Delay_Aktivasi) {
            $activatedAt = $tglStart->copy()->addMonths($rule->Delay_Aktivasi);
        }

        return $base + [
            'status' => 'ELIGIBLE',
            'jumlah' => $jumlah,
            'activated_at' => $activatedAt->toDateString(),
            'expired_date' => $dueDate->copy()->addYear()->endOfYear()->toDateString(),
            'periode' => $dueDate->year,
            'kategori_transaksi' => 'AKRUAL_BULANAN',
            'reason' => $keterangan ?: 'Recovery Jatah Bulanan '.$dueDate->format('F Y'),
            'message' => "Eligible recovery akrual bulanan ({$jumlahNote}).",
            'rule_name' => 'Aturan kelayakan cuti '.($rule->Status_Kontrak ?? $karyawan->status_kontrak),
            'rule_source' => 'N_HRIS_Aturan_Kelayakan_Cuti',
            'rule_summary' => sprintf(
                'Status kontrak %s, acuan %s, minimum masa kerja %s bulan, delay aktivasi %s bulan.',
                $rule->Status_Kontrak ?? '-',
                $kolomAcuan,
                $rule->Bulan_Minimum ?? 0,
                $rule->Delay_Aktivasi ?? 0,
            ),
            'calculation_note' => sprintf(
                'Masa kerja %s bulan pada due %s memenuhi minimum %s bulan; %s.',
                $masaKerjaBulan,
                $dueDate->format('d M Y'),
                $rule->Bulan_Minimum ?? 0,
                $jumlahNote,
            ),
            'metadata' => [
                'masa_kerja_bulan' => $masaKerjaBulan,
                'rule' => 'Aturan kelayakan cuti '.($rule->Status_Kontrak ?? $karyawan->status_kontrak),
                'rule_source' => 'N_HRIS_Aturan_Kelayakan_Cuti',
                'rule_summary' => sprintf(
                    'Status kontrak %s, acuan %s, minimum masa kerja %s bulan, delay aktivasi %s bulan.',
                    $rule->Status_Kontrak ?? '-',
                    $kolomAcuan,
                    $rule->Bulan_Minimum ?? 0,
                    $rule->Delay_Aktivasi ?? 0,
                ),
                'calculation_note' => sprintf(
                    'Masa kerja %s bulan pada due %s memenuhi minimum %s bulan; %s.',
                    $masaKerjaBulan,
                    $dueDate->format('d M Y'),
                    $rule->Bulan_Minimum ?? 0,
                    $jumlahNote,
                ),
                'before_cutoff' => $base['before_cutoff'],
            ],
        ];
    }

    private function buildLoyaltyRecoveryItem(object $karyawan, string $month, ?string $keterangan): array
    {
        $periodStart = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth();
        $periodEnd = $periodStart->isSameMonth(Carbon::now())
            ? Carbon::now()->startOfDay()
            : $periodStart->copy()->endOfMonth()->startOfDay();

        $base = [
            'source_type' => 'LOYALTY_ACCRUAL',
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'nama_karyawan' => $karyawan->Nama,
            'id_jenis_cuti' => self::ID_CUTI_LOYALTY,
            'period_key' => $month,
        ];

        if (empty($karyawan->Tgl_Masuk)) {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'JOIN_DATE_EMPTY',
                'message' => 'Tanggal masuk kosong.',
            ];
        }

        $tglMasuk = Carbon::parse($karyawan->Tgl_Masuk);
        $dueDate = $this->resolveAnniversaryDueDate($tglMasuk, $periodStart->year);
        $base['due_date'] = $dueDate->toDateString();
        $base['before_cutoff'] = $dueDate->lt($this->cutoffDate());

        if ($dueDate->lt($periodStart) || $dueDate->gt($periodEnd)) {
            return $base + [
                'status' => 'SKIPPED_NOT_DUE',
                'reason_code' => 'DUE_DATE_OUTSIDE_PERIOD',
                'message' => 'Anniversary tidak masuk periode target.',
            ];
        }

        if ($karyawan->status_kontrak !== 'PKWTT') {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'NOT_PKWTT',
                'message' => 'Loyalty hanya untuk status kontrak PKWTT.',
            ];
        }

        $masaKerjaTahun = $tglMasuk->diffInYears($dueDate);
        $rule = DB::table('HRIS_Hak_Cuti_Tambahan')
            ->whereNull('Status')
            ->where('tahun_minimal', '<=', $masaKerjaTahun)
            ->where('golongan_min_id', '<=', $karyawan->ID_Golongan)
            ->where('golongan_max_id', '>=', $karyawan->ID_Golongan)
            ->orderByDesc('tahun_minimal')
            ->first();

        if (! $rule) {
            return $base + [
                'status' => 'SKIPPED_NOT_ELIGIBLE',
                'reason_code' => 'RULE_NOT_FOUND',
                'message' => "Tidak ada rule loyalty untuk masa kerja {$masaKerjaTahun} tahun.",
                'rule_source' => 'HRIS_Hak_Cuti_Tambahan',
                'calculation_note' => "Masa kerja {$masaKerjaTahun} tahun dan golongan {$karyawan->ID_Golongan} tidak menemukan rule loyalty aktif.",
            ];
        }

        $cutiKey =
            "CUTI_LOYALTY:{$dueDate->year}:{$karyawan->Kode_Karyawan}:YEAR-{$masaKerjaTahun}:JENIS-".
            self::ID_CUTI_LOYALTY;
        $base['cuti_key'] = $cutiKey;
        if (DB::table('N_HRIS_Log_Buku_Cuti')->where('Cuti_Key', $cutiKey)->exists()) {
            return $base + [
                'status' => 'SKIPPED_EXISTING_KEY',
                'reason_code' => 'CUTI_KEY_EXISTS',
                'message' => 'Cuti key sudah pernah tercatat.',
            ];
        }

        return $base + [
            'status' => 'ELIGIBLE',
            'jumlah' => (float) $rule->tambahan_hari,
            'activated_at' => $dueDate->toDateString(),
            'expired_date' => $dueDate->copy()->addMonths($rule->masa_berlaku_cuti)->toDateString(),
            'periode' => $dueDate->year,
            'kategori_transaksi' => 'REWARD_LOYALTY',
            'reason' => $keterangan ?: "Recovery Reward Masa Kerja {$masaKerjaTahun} Tahun ({$rule->nama_rule})",
            'message' => "Eligible recovery loyalty sesuai {$rule->nama_rule}.",
            'rule_name' => $rule->nama_rule,
            'rule_source' => 'HRIS_Hak_Cuti_Tambahan',
            'rule_summary' => sprintf(
                'Minimal masa kerja %s tahun, golongan %s-%s, masa berlaku %s bulan.',
                $rule->tahun_minimal ?? 0,
                $rule->golongan_min_id ?? '-',
                $rule->golongan_max_id ?? '-',
                $rule->masa_berlaku_cuti ?? '-',
            ),
            'calculation_note' => sprintf(
                'Masa kerja %s tahun dan golongan %s masuk rule "%s", sehingga mendapat %s hari.',
                $masaKerjaTahun,
                $karyawan->ID_Golongan ?? '-',
                $rule->nama_rule,
                $rule->tambahan_hari,
            ),
            'metadata' => [
                'masa_kerja_tahun' => $masaKerjaTahun,
                'rule' => $rule->nama_rule,
                'rule_source' => 'HRIS_Hak_Cuti_Tambahan',
                'rule_summary' => sprintf(
                    'Minimal masa kerja %s tahun, golongan %s-%s, masa berlaku %s bulan.',
                    $rule->tahun_minimal ?? 0,
                    $rule->golongan_min_id ?? '-',
                    $rule->golongan_max_id ?? '-',
                    $rule->masa_berlaku_cuti ?? '-',
                ),
                'calculation_note' => sprintf(
                    'Masa kerja %s tahun dan golongan %s masuk rule "%s", sehingga mendapat %s hari.',
                    $masaKerjaTahun,
                    $karyawan->ID_Golongan ?? '-',
                    $rule->nama_rule,
                    $rule->tambahan_hari,
                ),
                'before_cutoff' => $base['before_cutoff'],
            ],
        ];
    }

    private function recordRecoveryDetail(?int $batchId, array $item): void
    {
        $metadata = $item['metadata'] ?? [];
        $metadata['before_cutoff'] = $item['before_cutoff'] ?? ($metadata['before_cutoff'] ?? false);

        $this->batchService->recordDetail($batchId, [
            'source_type' => $item['source_type'] ?? 'MANUAL_RECOVERY',
            'kode_karyawan' => $item['kode_karyawan'] ?? null,
            'nama_karyawan' => $item['nama_karyawan'] ?? null,
            'id_jenis_cuti' => $item['id_jenis_cuti'] ?? null,
            'period_key' => $item['period_key'] ?? null,
            'due_date' => $item['due_date'] ?? null,
            'effective_date' => $item['activated_at'] ?? ($item['due_date'] ?? null),
            'jumlah' => $item['jumlah'] ?? null,
            'cuti_key' => $item['cuti_key'] ?? null,
            'no_transaksi_ref' => $item['no_transaksi_ref'] ?? null,
            'id_log' => $item['id_log'] ?? null,
            'status' => $item['status'] ?? 'FAILED',
            'reason_code' => $item['reason_code'] ?? null,
            'message' => $item['message'] ?? null,
            'metadata' => $metadata,
        ]);
    }

    private function applyRecoverySummaryStatus(array &$summary, string $status): void
    {
        if ($status === 'SKIPPED_EXISTING_KEY') {
            $summary['skipped_existing_key']++;
        } elseif ($status === 'SKIPPED_NOT_DUE') {
            $summary['skipped_not_due']++;
        } elseif ($status === 'SKIPPED_BEFORE_CUTOFF') {
            $summary['legacy_before_cutoff']++;
        } elseif ($status === 'FAILED') {
            $summary['failed']++;
        } else {
            $summary['skipped_rule']++;
        }
    }

    private function normalizeKaryawanCodes($value): array
    {
        $items = is_array($value) ? $value : preg_split('/[,\s]+/', (string) $value);

        return collect($items)->map(fn ($item) => strtoupper(trim((string) $item)))->filter()->unique()->values()->all();
    }

    private function countMissingMonthlyAccrual(Carbon $today): int
    {
        $rules = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->get();
        if ($rules->isEmpty()) {
            return 0;
        }

        $periodStart = $today->copy()->startOfMonth();
        $periodKey = $periodStart->format('Y-m');
        $existingKeys = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Cuti_Key', 'like', "CUTI_MONTHLY:{$periodKey}:%")
            ->pluck('Cuti_Key')
            ->flip()
            ->all();
        $missing = 0;

        DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->select('k.*')
            ->orderBy('k.Kode_Karyawan')
            ->chunk(500, function ($karyawans) use (
                $rules,
                $periodStart,
                $today,
                $periodKey,
                $existingKeys,
                &$missing,
            ) {
                foreach ($karyawans as $karyawan) {
                    $rule = $rules->first(fn ($r) => $r->Status_Kontrak == $karyawan->status_kontrak);
                    if (! $rule) {
                        continue;
                    }

                    $kolomAcuan = $rule->Ref_Date_Source ?? 'Tgl_Masuk';
                    if (empty($karyawan->$kolomAcuan)) {
                        continue;
                    }

                    $tglStart = Carbon::parse($karyawan->$kolomAcuan);
                    $dueDate = AturanAkrualCuti::jatuhTempoBulanan($tglStart, $periodStart);
                    if ($dueDate->gt($today)) {
                        continue;
                    }

                    if ($tglStart->diffInMonths($dueDate) < $rule->Bulan_Minimum) {
                        continue;
                    }

                    $cutiKey = "CUTI_MONTHLY:{$periodKey}:{$karyawan->Kode_Karyawan}:JENIS-".self::ID_CUTI_TAHUNAN;
                    if (! isset($existingKeys[$cutiKey])) {
                        $missing++;
                    }
                }
            });

        return $missing;
    }

    private function resolveAnniversaryDueDate(Carbon $joinDate, int $targetYear): Carbon
    {
        $base = Carbon::create($targetYear, $joinDate->month, 1)->startOfDay();
        $day = min($joinDate->day, $base->daysInMonth);

        return Carbon::create($targetYear, $joinDate->month, $day)->startOfDay();
    }

    // =========================================================================
    // EXPORT FUNCTIONS
    // =========================================================================

    /**
     * Export Kuota Karyawan to Excel
     */
    public function exportKuotaKaryawan(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => 'nullable|array',
            'kode_karyawan.*' => 'string',
            'filters' => 'nullable|array',
            'filters.divisi' => 'nullable|string',
            'filters.status_kuota' => 'nullable|string',
            'filters.search' => 'nullable|string',
        ]);

        try {
            $query = DB::table('N_HRIS_VW_Saldo_Cuti_Karyawan as v')
                ->join('Karyawan as k', 'v.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                );

            // Scenario 1: Export Specific IDs
            if (! empty($validated['kode_karyawan'])) {
                $kodeKaryawanList = array_map(function ($encrypted) {
                    try {
                        return Crypt::decryptString($encrypted);
                    } catch (\Exception $e) {
                        return null;
                    }
                }, $validated['kode_karyawan']);

                // Filter invalid decrypts
                $kodeKaryawanList = array_filter($kodeKaryawanList);

                if (count($kodeKaryawanList) > 0) {
                    $query->whereIn('v.Kode_Karyawan', $kodeKaryawanList);
                } else {
                    return ResponseHelper::error('Tidak ada data karyawan yang valid', 400);
                }
            }
            // Scenario 2: Export All with Filters
            elseif (! empty($validated['filters'])) {
                $filters = $validated['filters'];

                // 1. Division Filter
                if (! empty($filters['divisi'])) {
                    $query->where('vd.ID_Divisi', $filters['divisi']);
                }

                // 2. Search Filter (Name or ID)
                if (! empty($filters['search'])) {
                    $searchTerm = $filters['search'];
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('k.Nama', 'LIKE', "%{$searchTerm}%")->orWhere(
                            'k.Kode_Karyawan',
                            'LIKE',
                            "%{$searchTerm}%",
                        );
                    });
                }

                // 3. Status Kuota Filter
                if (! empty($filters['status_kuota'])) {
                    switch ($filters['status_kuota']) {
                        case 'normal':
                            $query->where('v.Hutang_Cuti', 0)->where('v.Sedang_Diajukan', 0);
                            break;
                        case 'pending':
                            $query->where('v.Sedang_Diajukan', '>', 0);
                            break;
                        case 'hutang':
                            $query->where('v.Hutang_Cuti', '>', 0);
                            break;
                    }
                }
            } else {
                return ResponseHelper::error('Parameter export tidak valid', 400);
            }

            // Execute Query
            $data = $query
                ->select(
                    'v.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                    'v.Sisa_Cuti_Tampil as Total_Kuota_Tampil',
                    'v.Sedang_Diajukan',
                    'v.Hutang_Cuti_Tampil',
                    'v.Sisa_Cuti_Tampil',
                )
                ->orderBy('k.Nama')
                ->get();

            // Create Excel
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Kuota Karyawan');

            // Title
            $sheet->setCellValue('A1', 'LAPORAN KUOTA CUTI KARYAWAN');
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Generated date
            $sheet->setCellValue('A2', 'Tanggal Export: '.Carbon::now()->format('d/m/Y H:i'));
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2')->getFont()->setItalic(true);

            // Headers
            $headers = [
                'No',
                'Nama Karyawan',
                'Kode Karyawan',
                'Divisi',
                'Sub Divisi',
                'Level',
                'Jabatan',
                'Jatah Cuti',
                'Pending',
                'Hutang',
                'Sisa Cuti',
            ];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.'4', $header);
                $col++;
            }

            // Header style
            $headerRange = 'A4:K4';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4A90D9');
            $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Data
            $row = 5;
            $no = 1;
            foreach ($data as $d) {
                $sheet->setCellValue('A'.$row, $no++);
                $sheet->setCellValue('B'.$row, $d->Nama_Karyawan);
                $sheet->setCellValue('C'.$row, $d->Kode_Karyawan);
                $sheet->setCellValue('D'.$row, $d->nama_divisi ?? '-');
                $sheet->setCellValue('E'.$row, $d->nama_sub_divisi ?? '-');
                $sheet->setCellValue('F'.$row, $d->nama_level ?? '-');
                $sheet->setCellValue('G'.$row, $d->nama_jabatan ?? '-');
                $sheet->setCellValue('H'.$row, $d->Total_Kuota_Tampil);
                $sheet->setCellValue('I'.$row, $d->Sedang_Diajukan);
                $sheet->setCellValue('J'.$row, $d->Hutang_Cuti_Tampil);
                $sheet->setCellValue('K'.$row, $d->Sisa_Cuti_Tampil);
                $row++;
            }

            // Auto-fit columns
            foreach (range('A', 'K') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Border
            $dataRange = 'A4:K'.($row - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Download
            $filename = 'Kuota_Cuti_Karyawan_'.Carbon::now()->format('Ymd_His').'.xlsx';
            $writer = new Xlsx($spreadsheet);

            $tempFile = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';
            $writer->save($tempFile);

            $content = file_get_contents($tempFile);
            unlink($tempFile);

            return Response::make($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Export Kuota error: '.$e->getMessage());

            return ResponseHelper::error('Gagal export: '.$e->getMessage(), 500);
        }
    }

    /**
     * Export Riwayat Cuti to Excel
     */
    public function exportRiwayatCuti(Request $request)
    {
        /* =====================================================
         * 1. VALIDATION
         * ===================================================== */
        $validated = $request->validate([
            'kode_karyawan' => 'required|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);

            /* =====================================================
             * 2. DATA KARYAWAN
             * ===================================================== */
            $karyawan = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                )
                ->where('k.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'k.Nama as Nama_Karyawan',
                    'k.Kode_Karyawan',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                )
                ->first();

            if (! $karyawan) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }

            /* =====================================================
             * 3. SUBQUERY AGGREGASI CUTI (SUDAH TERFILTER)
             * ===================================================== */
            $cutiAgg = DB::table('Transaksi_Cuti_Det as tcdt')
                ->join('Transaksi_Cuti as tc', 'tc.No_Transaksi', '=', 'tcdt.No_Transaksi')
                ->join('Transaksi_Cuti_Detail as tcd', 'tcd.No_Transaksi', '=', 'tc.No_Transaksi')
                ->where('tcd.Kode_Karyawan', $kodeKaryawan)
                ->whereNull('tc.Status')
                ->select(
                    'tcdt.No_Transaksi',
                    DB::raw('COUNT(*) as Jumlah_Hari'),
                    DB::raw(
                        "STRING_AGG(CONVERT(varchar, FORMAT(tcdt.Tanggal_Cuti, 'dd/MM/yyyy')), ', ') as Tanggal_Cuti",
                    ),
                )
                ->groupBy('tcdt.No_Transaksi');

            /* =====================================================
             * 4. QUERY UTAMA RIWAYAT CUTI
             * ===================================================== */
            $query = DB::table('Transaksi_Cuti as tc')
                ->join('Transaksi_Cuti_Detail as tcd', 'tc.No_Transaksi', '=', 'tcd.No_Transaksi')
                ->join('N_HRIS_Jenis_Cuti as jc', 'tcd.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
                ->leftJoinSub($cutiAgg, 'agg', function ($join) {
                    $join->on('tc.No_Transaksi', '=', 'agg.No_Transaksi');
                })
                ->where('tcd.Kode_Karyawan', $kodeKaryawan)
                ->whereNull('tc.Status')
                ->select(
                    'tc.No_Transaksi',
                    'jc.Nama_Jenis_Cuti',
                    'tcd.Alasan as Keterangan',
                    'tc.Tanggal as Created_At',
                    'agg.Jumlah_Hari',
                    'agg.Tanggal_Cuti',
                    DB::raw(
                        "CASE WHEN tcd.Flag_Approval = 'Y' THEN 'Disetujui' WHEN tcd.Flag_Approval = 'T' THEN 'Ditolak' ELSE 'Pending' END as Status",
                    ),
                );

            /* =====================================================
             * 5. FILTER PERIODE (BERDASARKAN TANGGAL CUTI REAL)
             * ===================================================== */
            if (! empty($validated['date_start']) || ! empty($validated['date_end'])) {
                $query->whereExists(function ($q) use ($validated) {
                    $q->select(DB::raw(1))
                        ->from('Transaksi_Cuti_Det as d')
                        ->whereColumn('d.No_Transaksi', 'tc.No_Transaksi');

                    if (! empty($validated['date_start'])) {
                        $q->whereDate('d.Tanggal_Cuti', '>=', $validated['date_start']);
                    }
                    if (! empty($validated['date_end'])) {
                        $q->whereDate('d.Tanggal_Cuti', '<=', $validated['date_end']);
                    }
                });
            }

            $data = $query->orderBy('tc.Tanggal', 'desc')->get();

            /* =====================================================
             * 6. CREATE EXCEL
             * ===================================================== */
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Riwayat Cuti');

            // Title
            $sheet->setCellValue('A1', 'RIWAYAT PENGAJUAN CUTI');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            // Info Karyawan
            $sheet->setCellValue('A3', 'Nama Karyawan');
            $sheet->setCellValue('B3', ': '.$karyawan->Nama_Karyawan);
            $sheet->setCellValue('A4', 'Kode Karyawan');
            $sheet->setCellValue('B4', ': '.$karyawan->Kode_Karyawan);
            $sheet->setCellValue('A5', 'Divisi');
            $sheet->setCellValue('B5', ': '.($karyawan->nama_divisi ?? '-'));
            $sheet->setCellValue('A6', 'Sub Divisi');
            $sheet->setCellValue('B6', ': '.($karyawan->nama_sub_divisi ?? '-'));
            $sheet->setCellValue('A7', 'Level');
            $sheet->setCellValue('B7', ': '.($karyawan->nama_level ?? '-'));
            $sheet->setCellValue('A8', 'Jabatan');
            $sheet->setCellValue('B8', ': '.($karyawan->nama_jabatan ?? '-'));

            // Periode
            $periode = 'Semua Data';
            if (! empty($validated['date_start']) && ! empty($validated['date_end'])) {
                $periode =
                    Carbon::parse($validated['date_start'])->format('d/m/Y').
                    ' - '.
                    Carbon::parse($validated['date_end'])->format('d/m/Y');
            }
            $sheet->setCellValue('A9', 'Periode');
            $sheet->setCellValue('B9', ': '.$periode);

            // Header
            $headers = ['No', 'No Transaksi', 'Jenis Cuti', 'Tanggal Cuti', 'Jumlah Hari', 'Status', 'Keterangan'];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.'11', $header);
                $col++;
            }

            $sheet->getStyle('A11:H11')->getFont()->setBold(true);
            $sheet->getStyle('A11:H11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4A90D9');
            $sheet->getStyle('A11:H11')->getFont()->getColor()->setRGB('FFFFFF');

            // Data
            $row = 12;
            $no = 1;
            foreach ($data as $d) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValue("B{$row}", $d->No_Transaksi);
                $sheet->setCellValue("C{$row}", $d->Nama_Jenis_Cuti);
                $sheet->setCellValue("D{$row}", $d->Tanggal_Cuti);
                $sheet->setCellValue("E{$row}", $d->Jumlah_Hari);
                $sheet->setCellValue("F{$row}", ucfirst($d->Status));
                $sheet->setCellValue("G{$row}", $d->Keterangan ?? '-');

                $row++;
            }

            foreach (range('A', 'H') as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $sheet
                ->getStyle('A11:H'.($row - 1))
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            /* =====================================================
             * 7. RESPONSE DOWNLOAD
             * ===================================================== */
            $filename =
                'Riwayat_Cuti_'.
                str_replace(' ', '_', $karyawan->Nama_Karyawan).
                '_'.
                now()->format('Ymd_His').
                '.xlsx';
            $temp = tempnam(sys_get_temp_dir(), 'cuti_').'.xlsx';

            // new Xlsx($spreadsheet)->save($temp);
            $writer = new Xlsx($spreadsheet);
            $writer->save($temp);
            $content = file_get_contents($temp);
            unlink($temp);

            return Response::make($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Export Riwayat Cuti Error: '.$e->getMessage());

            return ResponseHelper::error('Gagal export data cuti', 500);
        }
    }

    /**
     * Export Log Mutasi to Excel
     */
    public function exportLogMutasi(Request $request)
    {
        $validated = $request->validate([
            'kode_karyawan' => 'required|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date',
        ]);

        try {
            $kodeKaryawan = Crypt::decryptString($validated['kode_karyawan']);

            // Get employee info
            $karyawan = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vg.Id_Level_Jabatan',
                )
                ->where('k.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'k.Nama as Nama_Karyawan',
                    'k.Kode_Karyawan',
                    'vd.nama_divisi',
                    'vd.nama_sub_divisi',
                    'vg.nama_level',
                    'vg.nama_jabatan',
                )
                ->first();
            // dd($karyawan);
            // Get logs
            $query = DB::table('N_HRIS_Log_Buku_Cuti as l')
                ->join('HRIS_Buku_Cuti as bc', 'l.Id_Buku_Cuti', '=', 'bc.Id_Buku_Cuti')
                ->where('bc.Kode_Karyawan', $kodeKaryawan)
                ->select(
                    'l.Created_At',
                    'l.Kategori_Transaksi',
                    'l.Jenis_Mutasi',
                    'l.Jumlah',
                    'l.Saldo_Sebelum',
                    'l.Saldo_Sesudah',
                    'l.Keterangan',
                );

            if (! empty($validated['date_start'])) {
                $query->whereDate('l.Created_At', '>=', $validated['date_start']);
            }
            if (! empty($validated['date_end'])) {
                $query->whereDate('l.Created_At', '<=', $validated['date_end']);
            }

            $data = $query->orderBy('l.Created_At', 'desc')->get();

            // Create Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Log Mutasi');

            // Title
            $sheet->setCellValue('A1', 'LOG PERUBAHAN KUOTA CUTI');
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            // Employee Info
            $sheet->setCellValue('A3', 'Nama Karyawan');
            $sheet->setCellValue('B3', ': '.($karyawan->Nama_Karyawan ?? '-'));
            $sheet->setCellValue('A4', 'Kode Karyawan');
            $sheet->setCellValue('B4', ': '.($karyawan->Kode_Karyawan ?? '-'));
            $sheet->setCellValue('A5', 'Divisi');
            $sheet->setCellValue('B5', ': '.($karyawan->nama_divisi ?? '-'));
            $sheet->setCellValue('A6', 'Sub Divisi');
            $sheet->setCellValue('B6', ': '.($karyawan->nama_sub_divisi ?? '-'));
            $sheet->setCellValue('A7', 'Level');
            $sheet->setCellValue('B7', ': '.($karyawan->nama_level ?? '-'));
            $sheet->setCellValue('A8', 'Jabatan');
            $sheet->setCellValue('B8', ': '.($karyawan->nama_jabatan ?? '-'));

            // Headers
            $headers = [
                'No',
                'Tanggal',
                'Kategori',
                'Jenis Mutasi',
                'Jumlah',
                'Saldo Sebelum',
                'Saldo Sesudah',
                'Keterangan',
            ];
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col.'10', $header);
                $col++;
            }

            $headerRange = 'A10:H10';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet
                ->getStyle($headerRange)
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('4A90D9');
            $sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');

            // Data with mutasi coloring
            $row = 11;
            $no = 1;
            foreach ($data as $d) {
                $sheet->setCellValue('A'.$row, $no++);
                $sheet->setCellValue('B'.$row, Carbon::parse($d->Created_At)->format('d/m/Y H:i'));
                $sheet->setCellValue('C'.$row, $d->Kategori_Transaksi);
                $sheet->setCellValue('D'.$row, $d->Jenis_Mutasi);
                $sheet->setCellValue('E'.$row, $d->Jumlah);
                $sheet->setCellValue('F'.$row, $d->Saldo_Sebelum);
                $sheet->setCellValue('G'.$row, $d->Saldo_Sesudah);
                $sheet->setCellValue('H'.$row, $d->Keterangan ?? '-');

                // Row coloring based on mutasi type
                $rowColor = $d->Jenis_Mutasi === 'CREDIT' ? 'C6EFCE' : 'FFC7CE';
                $sheet
                    ->getStyle('A'.$row.':H'.$row)
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($rowColor);

                $row++;
            }

            // Auto-fit & borders
            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $sheet
                ->getStyle('A10:H'.($row - 1))
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

            $filename =
                'Log_Mutasi_'.
                str_replace(' ', '_', $karyawan->Nama_Karyawan ?? 'Export').
                '_'.
                Carbon::now()->format('Ymd_His').
                '.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $tempFile = tempnam(sys_get_temp_dir(), 'export_').'.xlsx';
            $writer->save($tempFile);

            $content = file_get_contents($tempFile);
            unlink($tempFile);

            return Response::make($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Exception $e) {
            Log::error('Export Log error: '.$e->getMessage());

            return ResponseHelper::error('Gagal export: '.$e->getMessage(), 500);
        }
    }
}
