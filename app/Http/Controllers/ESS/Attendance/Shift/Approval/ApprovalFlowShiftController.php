<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Approval;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Shift\Approval\ApprovalFlowShiftService;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ApprovalFlowShiftController extends Controller
{
    protected ApprovalFlowShiftService $service;

    public function __construct(ApprovalFlowShiftService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the main page
     */
    public function index()
    {
        return Inertia::render('ESS/Attendance/Shift/Approval/ApprovalFlowShift', [
            'initialStats' => $this->service->getStats(),
            'filterOptions' => $this->service->getFilterOptions(),
        ]);
    }

    /**
     * Get dashboard stats
     */
    public function getStats()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->getStats(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil stats: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of approvers for dropdown
     */
    public function getApprovers(Request $request)
    {
        try {
            $filters = [
                'search' => $request->search,
                'divisi' => $request->divisi,
            ];

            return response()->json([
                'success' => true,
                'data' => $this->service->getApprovers($filters),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil approvers: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of members for assignment
     */
    public function getMembers(Request $request)
    {
        try {
            $filters = [
                'search' => $request->search,
                'divisi' => $request->divisi,
                'jabatan' => $request->jabatan,
                'status' => $request->status,
            ];

            return response()->json([
                'success' => true,
                'data' => $this->service->getMembers($filters),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil members: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all assignments with pagination
     */
    public function getAssignments(Request $request)
    {
        try {
            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 15,
                'filters' => [
                    'search' => $request->search,
                    'approver' => $request->approver,
                    'divisi' => $request->divisi,
                ],
            ];

            $result = $this->service->getAssignments($params);

            return ResponseHelper::successWithPaginationV2(
                ['items' => $result['data']],
                $result['page'],
                $result['limit'],
                $result['total'],
                'Data ditemukan'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk assign members to an approver
     */
    public function bulkAssign(Request $request)
    {
        $request->validate([
            'approver_kode' => 'required|string',
            'member_kodes' => 'required|array|min:1',
            'member_kodes.*' => 'required|string',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->service->bulkAssign(
                $request->approver_kode,
                $request->member_kodes,
                $request->catatan
            );

            return response()->json([
                'success' => true,
                'message' => "Berhasil assign {$result['total_success']} anggota ke approver.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update single assignment
     */
    public function updateAssignment(Request $request, $id)
    {
        $request->validate([
            'approver_kode' => 'required|string',
        ]);

        try {
            $existing = DB::table('N_HRIS_Approval_Flow_Shift')
                ->where('id', $id)
                ->whereNull('Status')
                ->first();

            if (! $existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Assignment tidak ditemukan.',
                ], 404);
            }

            $result = $this->service->bulkAssign(
                $request->approver_kode,
                [$existing->Kode_Karyawan_Requester],
                'Update assignment via edit'
            );

            return response()->json([
                'success' => true,
                'message' => 'Assignment berhasil diupdate.',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal update: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove assignment (soft delete)
     */
    public function removeAssignment($id)
    {
        try {
            $this->service->removeAssignment((int) $id);

            return response()->json([
                'success' => true,
                'message' => 'Assignment berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal hapus: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transfer all members from one approver to another
     */
    public function transferApprover(Request $request)
    {
        $request->validate([
            'old_approver_kode' => 'required|string',
            'new_approver_kode' => 'required|string|different:old_approver_kode',
            'catatan' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->service->transferApprover(
                $request->old_approver_kode,
                $request->new_approver_kode,
                $request->catatan
            );

            return response()->json([
                'success' => true,
                'message' => "Berhasil transfer {$result['total_transferred']} anggota ke approver baru.",
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal transfer: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get transaction history
     */
    public function getTransactionHistory(Request $request)
    {
        try {
            $params = [
                'page' => $request->page ?? 1,
                'limit' => $request->limit ?? 10,
            ];

            $result = $this->service->getTransactionHistory($params);

            return ResponseHelper::successWithPaginationV2(
                ['items' => $result['data']],
                $result['page'],
                $result['limit'],
                $result['total'],
                'Data ditemukan'
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export data in same format as import template
     * Supports filtering by approver, divisi, or custom member selection
     */
    public function exportData(Request $request)
    {
        try {
            // Base query: Start from Active Karyawan to include everyone (even those without approvers)
            $query = DB::table('Karyawan as ka')
                ->leftJoin('N_HRIS_Approval_Flow_Shift as af', function ($join) {
                    $join->on('ka.Kode_Karyawan', '=', 'af.Kode_Karyawan_Requester')
                        ->whereNull('af.Status'); // Only active assignments
                })
                ->leftJoin('Karyawan as kp', 'kp.Kode_Karyawan', '=', 'af.Kode_Karyawan') // Get Approver details
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'ka.Id_Divisi_Sub_Divisi')
                ->where('ka.Aktif', 'Y') // Only active employees
                ->select(
                    'ka.NIK as nik_anggota',
                    'ka.Nama as nama_anggota',
                    'kp.NIK as nik_approver',
                    'kp.Nama as nama_approver',
                    'ka.Kode_Karyawan as Kode_Karyawan_Requester',
                    'vd.ID_DIVISI'
                );

            // Filter specific member selection (Custom)
            if ($request->has('member_kodes') && is_array($request->member_kodes) && count($request->member_kodes) > 0) {
                $query->whereIn('ka.Kode_Karyawan', $request->member_kodes);
            }

            // Filter by approver (if specific approver requested)
            // Note: If type is 'approver', we ONLY want members assigned to that approver.
            if ($request->filled('approver_kode')) {
                $query->where('af.Kode_Karyawan', $request->approver_kode);
            }

            // Filter by divisi
            if ($request->filled('divisi_id')) {
                $query->where('vd.ID_DIVISI', $request->divisi_id);
            }

            $data = $query->orderBy('ka.Nama')->get();

            if ($data->count() === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data untuk diexport',
                ], 400);
            }

            // Create Excel file
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Header Instructions (same as template)
            $sheet->setCellValue('A1', 'EXPORT DATA APPROVAL FLOW SHIFT');
            $sheet->setCellValue('A2', 'File ini dapat diimport kembali ke sistem. Pastikan NIK tidak diubah.');
            $sheet->setCellValue('A3', 'Isi/Update NIK Approver untuk anggota yang belum memiliki approver.');
            $sheet->mergeCells('A1:D1');
            $sheet->mergeCells('A2:D2');
            $sheet->mergeCells('A3:D3');

            // Style for title
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle('A2:A3')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10],
            ]);

            // Headers (same as template)
            $headers = ['NIK_Anggota', 'Nama_Anggota (Opsional)', 'NIK_Approver', 'Nama_Approver (Opsional)'];
            $columns = ['A', 'B', 'C', 'D'];
            foreach ($headers as $i => $header) {
                $sheet->setCellValue($columns[$i].'4', $header);
            }

            // Header styling (same as template)
            $sheet->getStyle('A4:D4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Data rows
            $rowNum = 5;
            foreach ($data as $row) {
                $sheet->setCellValue('A'.$rowNum, $row->nik_anggota);
                $sheet->setCellValue('B'.$rowNum, $row->nama_anggota);

                // If approver is null, cell will be empty, prompting user to fill it
                $sheet->setCellValue('C'.$rowNum, $row->nik_approver);
                $sheet->setCellValue('D'.$rowNum, $row->nama_approver);

                $rowNum++;
            }

            // Column widths
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(30);

            // Generate file
            $filename = 'export_approval_flow_shift_'.date('Y-m-d_His').'.xlsx';
            $writer = new Xlsx($spreadsheet);

            // Save to temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            $writer->save($tempFile);

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal export data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download Excel template using PhpSpreadsheet directly
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            // Header Instructions
            $sheet->setCellValue('A1', 'TEMPLATE IMPORT APPROVAL FLOW SHIFT');
            $sheet->setCellValue('A2', 'Petunjuk: Isi NIK_Anggota dan NIK_Approver. Kolom Nama opsional (untuk referensi saja).');
            $sheet->setCellValue('A3', 'NIK harus sesuai dengan data di sistem. Data yang sudah ada akan di-overwrite.');
            $sheet->mergeCells('A1:D1');
            $sheet->mergeCells('A2:D2');
            $sheet->mergeCells('A3:D3');

            // Style for title
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $sheet->getStyle('A2:A3')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10],
            ]);

            // Headers
            $headers = ['NIK_Anggota', 'Nama_Anggota (Opsional)', 'NIK_Approver', 'Nama_Approver (Opsional)'];
            $columns = ['A', 'B', 'C', 'D'];
            foreach ($headers as $i => $header) {
                $sheet->setCellValue($columns[$i].'4', $header);
            }

            // Header styling
            $sheet->getStyle('A4:D4')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Sample data
            $sampleData = [
                ['1001', 'Ahmad Fauzi', '2001', 'John Manager'],
                ['1002', 'Budi Santoso', '2001', 'John Manager'],
                ['1003', 'Citra Dewi', '2002', 'Jane Supervisor'],
            ];
            foreach ($sampleData as $rowIndex => $rowData) {
                foreach ($rowData as $colIndex => $value) {
                    $sheet->setCellValue($columns[$colIndex].($rowIndex + 5), $value);
                }
            }

            // Column widths
            $sheet->getColumnDimension('A')->setWidth(15);
            $sheet->getColumnDimension('B')->setWidth(25);
            $sheet->getColumnDimension('C')->setWidth(15);
            $sheet->getColumnDimension('D')->setWidth(25);

            // Generate file - use temp file approach for compatibility
            $filename = 'template_approval_flow_shift.xlsx';
            $writer = new Xlsx($spreadsheet);

            // Save to temp file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            $writer->save($tempFile);

            // Return file download response
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate import before processing
     * Returns all rows with validation status for preview/editing
     */
    public function validateImport(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $validRows = 0;
            $invalidRows = 0;
            $previewData = [];

            // Skip first 4 rows (3 instruction + 1 header)
            for ($i = 4; $i < count($rows); $i++) {
                $row = $rows[$i];
                $rowNumber = $i + 1;

                $nikAnggota = trim($row[0] ?? '');
                $namaAnggotaInput = trim($row[1] ?? '');
                $nikApprover = trim($row[2] ?? '');
                $namaApproverInput = trim($row[3] ?? '');

                // Skip empty rows
                if (empty($nikAnggota) && empty($nikApprover)) {
                    continue;
                }

                $rowData = [
                    'id' => uniqid('import_'),
                    'row' => $rowNumber,
                    'nik_anggota' => $nikAnggota,
                    'nama_anggota' => $namaAnggotaInput,
                    'nik_approver' => $nikApprover,
                    'nama_approver' => $namaApproverInput,
                    'kode_anggota' => null,
                    'kode_approver' => null,
                    'is_valid' => true,
                    'errors' => [],
                ];

                // Validate NIK Anggota
                $anggota = DB::table('Karyawan')
                    ->where('NIK', $nikAnggota)
                    ->where('Aktif', 'Y')
                    ->first();

                if (! $anggota) {
                    $rowData['is_valid'] = false;
                    $rowData['errors'][] = 'NIK Anggota tidak ditemukan atau tidak aktif';
                } else {
                    $rowData['nama_anggota'] = $anggota->Nama;
                    $rowData['kode_anggota'] = $anggota->Kode_Karyawan;
                }

                // Validate NIK Approver
                $approver = DB::table('Karyawan')
                    ->where('NIK', $nikApprover)
                    ->where('Aktif', 'Y')
                    ->first();

                if (! $approver) {
                    $rowData['is_valid'] = false;
                    $rowData['errors'][] = 'NIK Approver tidak ditemukan atau tidak aktif';
                } else {
                    $rowData['nama_approver'] = $approver->Nama;
                    $rowData['kode_approver'] = $approver->Kode_Karyawan;
                }

                if ($rowData['is_valid']) {
                    $validRows++;
                } else {
                    $invalidRows++;
                }

                $previewData[] = $rowData;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_rows' => count($previewData),
                    'valid_rows' => $validRows,
                    'invalid_rows' => $invalidRows,
                    'preview' => $previewData,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal validasi file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import from Excel using PhpSpreadsheet directly
     */
    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $userId = Auth::id() ?? 'system';
            $noTransaksi = $this->generateNoTransaksi('IMP');

            $successCount = 0;
            $errorCount = 0;
            $details = [];

            /*
             * NIK TIDAK unik di tabel Karyawan, dan itu bukan teori: pada basis
             * pengembangan per 9 September 2026 ada 958 karyawan aktif dengan
             * hanya 911 NIK berbeda, satu di antaranya -- NIK '2' -- dipakai 47
             * orang sekaligus (Frans Bachtiar, Diva Mona Lisa, Hendry, ...).
             * Skema pun tidak menjaminnya: tak ada indeks unik pada NIK.
             *
             * Sebelumnya tiap baris memakai ->first() pada NIK, jadi untuk NIK
             * yang dipakai bersama impor ini memasang alur approval ke SALAH SATU
             * dari mereka secara sembarang -- tanpa galat, tanpa jejak. Sekarang
             * NIK yang cocok ke lebih dari satu karyawan aktif dihitung sebagai
             * baris GAGAL, sama seperti NIK yang tidak ditemukan. Menolak lebih
             * jujur daripada menebak.
             *
             * Sekalian: ketiga kueri Karyawan per baris (anggota, approver, dan
             * divisi/level approver) diambil sekali di depan. Impor ini berjalan
             * di dalam transaksi, jadi tiap kueri per baris memperpanjang waktu
             * kunci ditahan untuk SELURUH berkas.
             */
            $semuaNik = [];
            for ($i = 4; $i < count($rows); $i++) {
                foreach ([$rows[$i][0] ?? '', $rows[$i][2] ?? ''] as $nik) {
                    $nik = trim((string) $nik);
                    if ($nik !== '') {
                        $semuaNik[$nik] = true;
                    }
                }
            }

            $karyawanPerNik = [];
            $nikAmbigu = [];
            foreach (array_chunk(array_keys($semuaNik), PenyisipanBerbatasParameter::BATAS_AMAN) as $potongan) {
                foreach (DB::table('Karyawan')->whereIn('NIK', $potongan)->where('Aktif', 'Y')->get() as $k) {
                    if (isset($karyawanPerNik[$k->NIK])) {
                        $nikAmbigu[$k->NIK] = true;

                        continue;
                    }
                    $karyawanPerNik[$k->NIK] = $k;
                }
            }

            $infoPerKode = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
                ->whereIn('k.Kode_Karyawan', array_map(fn ($k) => $k->Kode_Karyawan, $karyawanPerNik))
                ->select('k.Kode_Karyawan', 'vd.ID_DIVISI', 'vg.Id_Level')
                ->get()
                ->keyBy('Kode_Karyawan');

            DB::beginTransaction();

            // Skip first 4 rows (3 instruction + 1 header)
            for ($i = 4; $i < count($rows); $i++) {
                $row = $rows[$i];
                $nikAnggota = trim($row[0] ?? '');
                $nikApprover = trim($row[2] ?? '');

                if (empty($nikAnggota) || empty($nikApprover)) {
                    continue;
                }

                if (isset($nikAmbigu[$nikAnggota]) || isset($nikAmbigu[$nikApprover])) {
                    /* Dihitung sebagai baris gagal, sama seperti NIK yang tidak
                       ditemukan. TIDAK ditambahkan ke $details -- larik itu
                       disisipkan apa adanya ke N_HRIS_Transaksi_Approver_Shift_Detail,
                       jadi bentuk baris yang berbeda akan merusak sisipannya. */
                    $errorCount++;

                    continue;
                }

                $anggota = $karyawanPerNik[$nikAnggota] ?? null;
                $approver = $karyawanPerNik[$nikApprover] ?? null;

                if (! $anggota || ! $approver) {
                    $errorCount++;

                    continue;
                }

                $approverInfo = $infoPerKode[$approver->Kode_Karyawan] ?? null;

                $divisionId = $approverInfo->ID_DIVISI ?? 0;
                $levelId = $approverInfo->Id_Level ?? 0;

                // Check if already exists
                $existing = DB::table('N_HRIS_Approval_Flow_Shift')
                    ->where('Kode_Karyawan_Requester', $anggota->Kode_Karyawan)
                    ->whereNull('Status')
                    ->first();

                $action = $existing ? 'UPDATE' : 'INSERT';
                $oldApprover = $existing->Kode_Karyawan ?? null;

                if ($existing) {
                    DB::table('N_HRIS_Approval_Flow_Shift')
                        ->where('id', $existing->id)
                        ->update([
                            'Kode_Karyawan' => $approver->Kode_Karyawan,
                            'Division_Id' => $divisionId,
                            'level_Id' => $levelId,
                        ]);
                } else {
                    DB::table('N_HRIS_Approval_Flow_Shift')->insert([
                        'Kode_Karyawan' => $approver->Kode_Karyawan,
                        'Kode_Karyawan_Requester' => $anggota->Kode_Karyawan,
                        'Division_Id' => $divisionId,
                        'level_Id' => $levelId,
                        'order_flow' => 1,
                        'Status' => null,
                    ]);
                }

                $details[] = [
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan_Requester' => $anggota->Kode_Karyawan,
                    'Kode_Approver_Lama' => $oldApprover,
                    'Kode_Approver_Baru' => $approver->Kode_Karyawan,
                    'Action' => $action,
                    'Status' => null,
                    'Created_At' => now(),
                ];
                $successCount++;
            }

            // Log transaction if any success
            if ($successCount > 0) {
                DB::table('N_HRIS_Transaksi_Approver_Shift')->insert([
                    'No_Transaksi' => $noTransaksi,
                    'Jenis_Transaksi' => 'IMPORT',
                    'Total_Affected' => $successCount,
                    'Import_File_Name' => $file->getClientOriginalName(),
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ]);

                DB::table('N_HRIS_Transaksi_Approver_Shift_Detail')->insert($details);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import berhasil. {$successCount} data diproses, {$errorCount} error.",
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'no_transaksi' => $noTransaksi,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal import: '.$e->getMessage(),
            ], 500);
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
     * Import from verified preview data (after user verification)
     * Accepts JSON data instead of re-parsing Excel
     */
    public function importFromPreview(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.kode_anggota' => 'required|string',
            'items.*.kode_approver' => 'required|string',
        ]);

        try {
            $items = $request->items;
            $userId = Auth::id() ?? 'system';
            $noTransaksi = $this->generateNoTransaksi('IMP');

            $successCount = 0;
            $errorCount = 0;
            $details = [];

            /*
             * Divisi/level approver dan nama anggota diambil SEKALI, sebelum
             * transaksi dibuka. Keduanya membaca tabel Karyawan dan view yang
             * tidak pernah ditulis oleh perulangan ini, jadi pra-ambil tidak
             * bisa basi. Impor ini berjalan di dalam transaksi: tiap kueri per
             * baris memperpanjang waktu kunci ditahan untuk SELURUH berkas.
             *
             * $existing dan $hasPending TIDAK ikut dipra-ambil: keduanya membaca
             * tabel yang perulangan ini juga tulisi, jadi peta bisa basi begitu
             * satu baris memengaruhi baris berikutnya.
             */
            $kodeApproverSemua = array_values(array_unique(array_filter(array_column($items, 'kode_approver'))));
            $kodeAnggotaSemua = array_values(array_unique(array_filter(array_column($items, 'kode_anggota'))));

            $infoPerApprover = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
                ->whereIn('k.Kode_Karyawan', $kodeApproverSemua)
                ->select('k.Kode_Karyawan', 'vd.ID_DIVISI', 'vg.Id_Level')
                ->get()
                ->keyBy('Kode_Karyawan');

            $namaPerAnggota = DB::table('Karyawan')
                ->whereIn('Kode_Karyawan', $kodeAnggotaSemua)
                ->pluck('Nama', 'Kode_Karyawan');

            DB::beginTransaction();

            foreach ($items as $item) {
                $kodeAnggota = $item['kode_anggota'];
                $kodeApprover = $item['kode_approver'];

                // Get approver's division & level
                $approverInfo = $infoPerApprover[$kodeApprover] ?? null;

                if (! $approverInfo) {
                    $errorCount++;

                    continue;
                }

                $divisionId = $approverInfo->ID_DIVISI ?? 0;
                $levelId = $approverInfo->Id_Level ?? 0;

                // Check if already exists
                $existing = DB::table('N_HRIS_Approval_Flow_Shift')
                    ->where('Kode_Karyawan_Requester', $kodeAnggota)
                    ->whereNull('Status')
                    ->first();

                if ($existing) {
                    // VALIDATION CHECK FOR PENDING REQUESTS
                    if ($existing->Kode_Karyawan) {
                        $hasPending = DB::table('HRIS_Approval_Request as ar')
                            ->join('N_HRIS_Transaksi_Shift as h', 'ar.No_Transaksi', '=', 'h.No_Transaksi')
                            ->where('ar.Approver_Karyawan_Kode', $existing->Kode_Karyawan)
                            ->whereNull('ar.Flag_Approval')
                            ->where('h.Kode_Karyawan', $kodeAnggota)
                            ->whereNull('h.Status')
                            ->exists();

                        if ($hasPending) {
                            $memberName = $namaPerAnggota[$kodeAnggota] ?? null;
                            throw new \Exception("Import dibatalkan: Anggota '$memberName' ($kodeAnggota) masih memiliki pengajuan shift pending dengan approver saat ini. Harap selesaikan approval terlebih dahulu.");
                        }
                    }
                }

                $action = $existing ? 'UPDATE' : 'INSERT';
                $oldApprover = $existing->Kode_Karyawan ?? null;

                if ($existing) {
                    DB::table('N_HRIS_Approval_Flow_Shift')
                        ->where('id', $existing->id)
                        ->update([
                            'Kode_Karyawan' => $kodeApprover,
                            'Division_Id' => $divisionId,
                            'level_Id' => $levelId,
                        ]);
                } else {
                    DB::table('N_HRIS_Approval_Flow_Shift')->insert([
                        'Kode_Karyawan' => $kodeApprover,
                        'Kode_Karyawan_Requester' => $kodeAnggota,
                        'Division_Id' => $divisionId,
                        'level_Id' => $levelId,
                        'order_flow' => 1,
                        'Status' => null,
                    ]);
                }

                $details[] = [
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan_Requester' => $kodeAnggota,
                    'Kode_Approver_Lama' => $oldApprover,
                    'Kode_Approver_Baru' => $kodeApprover,
                    'Action' => $action,
                    'Status' => null,
                    'Created_At' => now(),
                ];
                $successCount++;
            }

            // Log transaction if any success
            if ($successCount > 0) {
                DB::table('N_HRIS_Transaksi_Approver_Shift')->insert([
                    'No_Transaksi' => $noTransaksi,
                    'Jenis_Transaksi' => 'IMPORT',
                    'Total_Affected' => $successCount,
                    'Catatan' => 'Import dari preview dengan verifikasi',
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ]);

                DB::table('N_HRIS_Transaksi_Approver_Shift_Detail')->insert($details);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import berhasil. {$successCount} data diproses.",
                'data' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'no_transaksi' => $noTransaksi,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal import: '.$e->getMessage(),
            ], 500);
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
     * Get filter options
     */
    public function getFilterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->getFilterOptions(),
        ]);
    }

    /**
     * Generate transaction number
     */
    protected function generateNoTransaksi(string $prefix = 'IMP'): string
    {
        // Counter atomik per-prefix (sargable, anti-duplikat), konsisten dengan
        // ApprovalFlowShiftService::generateNoTransaksi pada tabel yang sama.
        $fullPrefix = sprintf('%s%s-%s-', $prefix, date('m'), date('y'));

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $fullPrefix,
            function () use ($fullPrefix) {
                $last = DB::table('N_HRIS_Transaksi_Approver_Shift')
                    ->where('No_Transaksi', 'like', $fullPrefix.'%')
                    ->orderBy('Id', 'desc')
                    ->value('No_Transaksi');
                if (! $last) {
                    return 0;
                }
                $parts = explode('-', $last);

                return intval(end($parts));
            },
            4,
        );
    }
}
