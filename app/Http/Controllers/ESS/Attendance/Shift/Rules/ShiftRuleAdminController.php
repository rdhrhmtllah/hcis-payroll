<?php

namespace App\Http\Controllers\ESS\Attendance\Shift\Rules;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ShiftRuleAdminController extends Controller
{
    /**
     * Sumbu pengelompokan yang sah.
     *
     * Isinya WAJIB sama dengan kunci peta di getGroupConfig() ditambah
     * 'kode_karyawan' yang ditangani terpisah — dijaga oleh
     * ShiftRuleGroupDetailTest supaya keduanya tak bisa berpisah diam-diam.
     */
    private const VIEW_BY_SAH = [
        'id_divisi',
        'id_sub_divisi',
        'id_level',
        'id_workgroup',
        'cabang_untuk_gaji',
        'kode_karyawan',
    ];

    public function index()
    {
        $now = FormatTanggalHelper::getCurrentTime('leader-shift');

        return Inertia::render('ESS/Attendance/Shift/Rules/ShiftRuleAdmin', [
            'serverTime' => $now->toISOString(),
            'initialStats' => $this->getStats(),
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    public function stats()
    {
        return ResponseHelper::success($this->getStats(), 'Statistik shift rule berhasil dimuat');
    }

    public function groups(Request $request)
    {
        $viewBy = (string) $request->input('view_by', 'id_divisi');
        $page = max((int) $request->input('page', 1), 1);
        $limit = max((int) $request->input('limit', 10), 1);
        $search = trim((string) $request->input('search', ''));
        $flagAktif = $request->input('flag_aktif');

        $allowed = [
            'id_divisi',
            'id_sub_divisi',
            'id_level',
            'id_workgroup',
            'cabang_untuk_gaji',
            'kode_karyawan',
        ];

        if (! in_array($viewBy, $allowed, true)) {
            return ResponseHelper::error('view_by tidak valid', 422);
        }

        if ($viewBy === 'kode_karyawan') {
            $all = $this->buildPerKaryawanGroups($search, $flagAktif);
            $total = count($all);
            $items = array_slice($all, ($page - 1) * $limit, $limit);

            return ResponseHelper::successWithPaginationV2(
                $items,
                $page,
                $limit,
                $total,
                'Data group per karyawan berhasil dimuat'
            );
        }

        $config = $this->getGroupConfig($viewBy);
        $query = DB::table('N_HRIS_Shift_Rule as r')
            ->leftJoin($config['label_table'].' as l', "r.{$config['column']}", '=', "l.{$config['label_key']}")
            ->selectRaw("r.{$config['column']} as group_value")
            ->selectRaw("MAX(l.{$config['label_name']}) as group_label")
            ->selectRaw('COUNT(*) as total_rules')
            ->selectRaw('COUNT(DISTINCT r.ID_Shift) as total_shifts')
            ->selectRaw("SUM(CASE WHEN r.Flag_Aktif = 'Y' THEN 1 ELSE 0 END) as active_rules")
            ->groupBy("r.{$config['column']}");

        if (in_array($flagAktif, ['Y', 'N'], true)) {
            $query->where('r.Flag_Aktif', $flagAktif);
        }

        if ($search !== '') {
            $query->havingRaw("CAST(r.{$config['column']} as varchar(200)) LIKE ? OR MAX(l.{$config['label_name']}) LIKE ?", [
                "%{$search}%",
                "%{$search}%",
            ]);
        }

        $rows = $query
            ->orderByRaw("CASE WHEN r.{$config['column']} IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw("MAX(l.{$config['label_name']})")
            ->orderByRaw("r.{$config['column']}")
            ->get()
            ->map(function ($row) use ($viewBy) {
                $groupValue = $row->group_value;
                $groupLabel = $row->group_label ?: ($groupValue ?? 'Tanpa Filter (Global)');
                if ($viewBy === 'id_workgroup' && $groupValue !== null && $groupLabel !== 'Tanpa Filter (Global)') {
                    $groupLabel = "{$groupLabel} (#{$groupValue})";
                }

                return [
                    '_key' => md5($viewBy.'|'.(string) $groupValue.'|'.(string) $groupLabel),
                    'view_by' => $viewBy,
                    'group_value' => $groupValue,
                    'group_label' => $groupLabel,
                    'total_rules' => (int) ($row->total_rules ?? 0),
                    'total_shifts' => (int) ($row->total_shifts ?? 0),
                    'active_rules' => (int) ($row->active_rules ?? 0),
                ];
            })
            ->values()
            ->toArray();

        $total = count($rows);
        $items = array_slice($rows, ($page - 1) * $limit, $limit);

        return ResponseHelper::successWithPaginationV2(
            $items,
            $page,
            $limit,
            $total,
            'Data group shift rule berhasil dimuat'
        );
    }

    public function groupDetail(Request $request)
    {
        $viewBy = (string) $request->input('view_by');
        $groupValue = $request->input('group_value');
        $flagAktif = $request->input('flag_aktif');

        /* view_by yang tak dikenal dijawab 422, bukan 500.
           getGroupConfig() melempar InvalidArgumentException untuk nilai di luar
           petanya, dan tak ada yang menangkapnya — permintaan tanpa view_by
           (atau dengan nilai asal) berakhir sebagai "Server Error", padahal itu
           kesalahan PEMANGGIL. Metode ini sudah tahu cara menjawab 422; di sini
           gayanya tinggal diikuti. */
        if (! in_array($viewBy, self::VIEW_BY_SAH, true)) {
            return ResponseHelper::error('Parameter view_by tidak valid', 422);
        }

        if ($viewBy === 'kode_karyawan') {
            if (! $groupValue) {
                return ResponseHelper::error('Kode karyawan wajib diisi', 422);
            }

            return ResponseHelper::success(
                $this->getKaryawanShiftDetail((string) $groupValue, $flagAktif),
                'Detail shift per karyawan berhasil dimuat'
            );
        }

        $config = $this->getGroupConfig($viewBy);
        $query = DB::table('N_HRIS_Shift_Rule as r')
            ->leftJoin('HRIS_Shift_Kerja as s', 'r.ID_Shift', '=', 's.ID_Shift')
            ->select(
                'r.ID_Shift',
                's.Nama as Nama_Shift'
            )
            ->selectRaw('COUNT(*) as total_rules')
            ->selectRaw("SUM(CASE WHEN r.Flag_Aktif = 'Y' THEN 1 ELSE 0 END) as active_rules")
            ->groupBy('r.ID_Shift', 's.Nama')
            ->orderBy('s.Nama');

        if (in_array($flagAktif, ['Y', 'N'], true)) {
            $query->where('r.Flag_Aktif', $flagAktif);
        }

        if ($groupValue === null || $groupValue === '' || $groupValue === '__NULL__') {
            $query->whereNull("r.{$config['column']}");
        } else {
            $query->where("r.{$config['column']}", $groupValue);
        }

        $items = $query->get()->map(function ($row) {
            return [
                'id_shift' => (int) $row->ID_Shift,
                'nama_shift' => $row->Nama_Shift ?: ('Shift #'.$row->ID_Shift),
                'total_rules' => (int) ($row->total_rules ?? 0),
                'active_rules' => (int) ($row->active_rules ?? 0),
            ];
        })->values()->toArray();

        return ResponseHelper::success($items, 'Detail shift group berhasil dimuat');
    }

    public function deactivateGroupShift(Request $request)
    {
        $validated = $request->validate([
            'view_by' => 'required|string',
            'group_value' => 'nullable',
            'id_shift' => 'required|integer',
        ]);

        $viewBy = (string) $validated['view_by'];
        $idShift = (int) $validated['id_shift'];

        if ($viewBy === 'kode_karyawan') {
            return ResponseHelper::error('Untuk mode per karyawan, gunakan endpoint nonaktifkan source rule', 422);
        }

        $config = $this->getGroupConfig($viewBy);
        $query = DB::table('N_HRIS_Shift_Rule')
            ->where('ID_Shift', $idShift)
            ->where('Flag_Aktif', 'Y');

        $groupValue = $validated['group_value'] ?? null;
        if ($groupValue === null || $groupValue === '' || $groupValue === '__NULL__') {
            $query->whereNull($config['column']);
        } else {
            $query->where($config['column'], $groupValue);
        }

        $affected = $query->update(['Flag_Aktif' => 'N']);

        return ResponseHelper::success(
            ['affected' => $affected],
            "Rule aktif yang dinonaktifkan: {$affected}"
        );
    }

    public function deactivateRuleSource(Request $request)
    {
        $validated = $request->validate([
            'kode_perusahaan' => 'required|string',
            'id_shift' => 'required|integer',
            'id_divisi' => 'nullable|integer',
            'id_sub_divisi' => 'nullable|integer',
            'id_level' => 'nullable|integer',
            'kode_karyawan' => 'nullable|string',
            'id_workgroup' => 'nullable|integer',
            'cabang_untuk_gaji' => 'nullable|string',
        ]);

        $query = DB::table('N_HRIS_Shift_Rule')
            ->where('Kode_Perusahaan', $validated['kode_perusahaan'])
            ->where('ID_Shift', $validated['id_shift'])
            ->where('Flag_Aktif', 'Y');

        $this->whereNullableEquals($query, 'Id_Divisi', $validated['id_divisi'] ?? null);
        $this->whereNullableEquals($query, 'Id_Sub_Divisi', $validated['id_sub_divisi'] ?? null);
        $this->whereNullableEquals($query, 'Id_Level', $validated['id_level'] ?? null);
        $this->whereNullableEquals($query, 'Kode_Karyawan', $validated['kode_karyawan'] ?? null);
        $this->whereNullableEquals($query, 'Id_Workgroup', $validated['id_workgroup'] ?? null);
        $this->whereNullableEquals($query, 'Cabang_Untuk_Gaji', $validated['cabang_untuk_gaji'] ?? null);

        $affected = $query->update(['Flag_Aktif' => 'N']);

        return ResponseHelper::success(
            ['affected' => $affected],
            "Source rule yang dinonaktifkan: {$affected}"
        );
    }

    public function list(Request $request)
    {
        $page = max((int) $request->input('page', 1), 1);
        $limit = max((int) $request->input('limit', 10), 1);

        $query = DB::table('N_HRIS_Shift_Rule as r')
            ->leftJoin('HRIS_Shift_Kerja as s', 'r.ID_Shift', '=', 's.ID_Shift')
            ->select(
                'r.Kode_Perusahaan',
                'r.ID_Shift',
                's.Nama as Nama_Shift',
                'r.Id_Divisi',
                'r.Id_Sub_Divisi',
                'r.Id_Level',
                'r.Kode_Karyawan',
                'r.Id_Workgroup',
                'r.Cabang_Untuk_Gaji',
                'r.Flag_Aktif'
            );

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('r.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('s.Nama', 'like', "%{$search}%")
                    ->orWhere('r.Cabang_Untuk_Gaji', 'like', "%{$search}%");
            });
        }

        $this->applyOptionalFilter($query, 'r.Kode_Perusahaan', $request->input('kode_perusahaan'));
        $this->applyOptionalFilter($query, 'r.ID_Shift', $request->input('id_shift'));
        $this->applyOptionalFilter($query, 'r.Id_Divisi', $request->input('id_divisi'));
        $this->applyOptionalFilter($query, 'r.Id_Sub_Divisi', $request->input('id_sub_divisi'));
        $this->applyOptionalFilter($query, 'r.Id_Level', $request->input('id_level'));
        $this->applyOptionalFilter($query, 'r.Id_Workgroup', $request->input('id_workgroup'));
        $this->applyOptionalFilter($query, 'r.Kode_Karyawan', $request->input('kode_karyawan'));
        $this->applyOptionalFilter($query, 'r.Cabang_Untuk_Gaji', $request->input('cabang_untuk_gaji'));

        if ($request->filled('flag_aktif')) {
            $query->where('r.Flag_Aktif', $request->input('flag_aktif'));
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderByDesc('r.Flag_Aktif')
            ->orderBy('r.Kode_Perusahaan')
            ->orderBy('r.ID_Shift')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $arr = (array) $row;
                $arr['_key'] = md5(json_encode($arr));

                return $arr;
            })
            ->values()
            ->toArray();

        return ResponseHelper::successWithPaginationV2(
            $items,
            $page,
            $limit,
            $total,
            'Data shift rule berhasil dimuat'
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_perusahaan' => 'required|string|max:10',
            'id_shift' => 'required',
            'id_divisi' => 'nullable',
            'id_sub_divisi' => 'nullable',
            'id_level' => 'nullable',
            'kode_karyawan' => 'nullable',
            'id_workgroup' => 'nullable',
            'cabang_untuk_gaji' => 'nullable',
            'flag_aktif' => 'nullable|in:Y,N',
        ]);

        $shiftIds = $this->normalizeIntValues($validated['id_shift'] ?? null);
        if (empty($shiftIds)) {
            return ResponseHelper::error('Shift wajib dipilih minimal 1', 422);
        }

        $divisiIds = $this->normalizeIntValues($validated['id_divisi'] ?? null, true);
        $subDivisiIds = $this->normalizeIntValues($validated['id_sub_divisi'] ?? null, true);
        $levelIds = $this->normalizeIntValues($validated['id_level'] ?? null, true);
        $workgroupIds = $this->normalizeIntValues($validated['id_workgroup'] ?? null, true);
        $cabangValues = $this->normalizeStringValues($validated['cabang_untuk_gaji'] ?? null, true);
        $karyawanValues = $this->normalizeKaryawanValues($validated['kode_karyawan'] ?? null, true);

        $totalCombinations = count($shiftIds)
            * count($divisiIds)
            * count($subDivisiIds)
            * count($levelIds)
            * count($workgroupIds)
            * count($cabangValues)
            * count($karyawanValues);

        if ($totalCombinations > 5000) {
            return ResponseHelper::error(
                'Kombinasi rule terlalu besar (>5000). Kurangi pilihan filter agar proses aman.',
                422
            );
        }

        $created = 0;
        $skipped = 0;
        $kodePerusahaan = $validated['kode_perusahaan'];
        $flagAktif = $validated['flag_aktif'] ?? 'Y';

        DB::transaction(function () use (
            $shiftIds,
            $divisiIds,
            $subDivisiIds,
            $levelIds,
            $workgroupIds,
            $cabangValues,
            $karyawanValues,
            $kodePerusahaan,
            $flagAktif,
            &$created,
            &$skipped
        ) {
            foreach ($shiftIds as $shiftId) {
                foreach ($divisiIds as $divisiId) {
                    foreach ($subDivisiIds as $subDivisiId) {
                        foreach ($levelIds as $levelId) {
                            foreach ($workgroupIds as $workgroupId) {
                                foreach ($cabangValues as $cabang) {
                                    foreach ($karyawanValues as $kodeKaryawan) {
                                        $payload = [
                                            'Kode_Perusahaan' => $kodePerusahaan,
                                            'ID_Shift' => $shiftId,
                                            'Id_Divisi' => $divisiId,
                                            'Id_Sub_Divisi' => $subDivisiId,
                                            'Id_Level' => $levelId,
                                            'Kode_Karyawan' => $kodeKaryawan,
                                            'Id_Workgroup' => $workgroupId,
                                            'Cabang_Untuk_Gaji' => $cabang,
                                            'Flag_Aktif' => $flagAktif,
                                        ];

                                        $duplicate = DB::table('N_HRIS_Shift_Rule')
                                            ->where('Kode_Perusahaan', $payload['Kode_Perusahaan'])
                                            ->where('ID_Shift', $payload['ID_Shift'])
                                            ->where(function ($q) use ($payload) {
                                                $this->whereNullableEquals($q, 'Id_Divisi', $payload['Id_Divisi']);
                                                $this->whereNullableEquals($q, 'Id_Sub_Divisi', $payload['Id_Sub_Divisi']);
                                                $this->whereNullableEquals($q, 'Id_Level', $payload['Id_Level']);
                                                $this->whereNullableEquals($q, 'Kode_Karyawan', $payload['Kode_Karyawan']);
                                                $this->whereNullableEquals($q, 'Id_Workgroup', $payload['Id_Workgroup']);
                                                $this->whereNullableEquals($q, 'Cabang_Untuk_Gaji', $payload['Cabang_Untuk_Gaji']);
                                            })
                                            ->exists();

                                        if ($duplicate) {
                                            $skipped++;

                                            continue;
                                        }

                                        DB::table('N_HRIS_Shift_Rule')->insert($payload);
                                        $created++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });

        return ResponseHelper::success([
            'created' => $created,
            'skipped' => $skipped,
            'total' => $created + $skipped,
        ], "Shift rule diproses. Berhasil: {$created}, duplikat dilewati: {$skipped}");
    }

    private function getStats(): array
    {
        $base = DB::table('N_HRIS_Shift_Rule');

        return [
            'total' => (clone $base)->count(),
            'aktif' => (clone $base)->where('Flag_Aktif', 'Y')->count(),
            'non_aktif' => (clone $base)->where('Flag_Aktif', 'N')->count(),
            'spesifik_karyawan' => (clone $base)->whereNotNull('Kode_Karyawan')->count(),
        ];
    }

    private function getFilterOptions(): array
    {
        $shiftOptions = DB::table('HRIS_Shift_Kerja')
            ->where('Non_Shift', 'T')
            ->orderBy('Nama')
            ->select('ID_Shift as value', 'Nama as label')
            ->get();

        $divisiOptions = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_DIVISI as value', 'nama_divisi as label')
            ->whereNotNull('ID_DIVISI')
            ->whereNotNull('nama_divisi')
            ->groupBy('ID_DIVISI', 'nama_divisi')
            ->orderBy('nama_divisi')
            ->get();

        $subDivisiOptions = DB::table('View_Divisi_Sub_Divisi')
            ->select('ID_DIVISI_SUB_DIVISI as value', 'nama_sub_divisi as label')
            ->whereNotNull('ID_DIVISI_SUB_DIVISI')
            ->whereNotNull('nama_sub_divisi')
            ->groupBy('ID_DIVISI_SUB_DIVISI', 'nama_sub_divisi')
            ->orderBy('nama_sub_divisi')
            ->get();

        $levelOptions = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
            ->select('ID_Level as value', 'nama_level as label')
            ->whereNotNull('ID_Level')
            ->whereNotNull('nama_level')
            ->groupBy('ID_Level', 'nama_level')
            ->orderBy('nama_level')
            ->get();

        $workgroupOptions = DB::table('N_HRIS_Workgroup')
            ->select('Id as value', 'Nama_Workgroup as label')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nama_Workgroup')
            ->get();

        // $workgroupOptions = [];
        $cabangOptions = DB::table('Karyawan')
            ->select('Cabang_Untuk_Gaji as value', 'Cabang_Untuk_Gaji as label')
            ->whereNotNull('Cabang_Untuk_Gaji')
            ->where('Cabang_Untuk_Gaji', '!=', '')
            ->groupBy('Cabang_Untuk_Gaji')
            ->orderBy('Cabang_Untuk_Gaji')
            ->get();

        $kodeKaryawanOptions = DB::table('Karyawan')
            ->select('Kode_Karyawan as value', 'Kode_Karyawan as label')
            ->where('Aktif', 'Y')
            ->whereNotNull('Kode_Karyawan')
            ->groupBy('Kode_Karyawan')
            ->orderBy('Kode_Karyawan')
            ->limit(2000)
            ->get();

        return [
            'kodePerusahaan' => [
                ['value' => '001', 'label' => '001'],
            ],
            'shift' => $shiftOptions,
            'divisi' => $divisiOptions,
            'subDivisi' => $subDivisiOptions,
            'level' => $levelOptions,
            'workgroup' => $workgroupOptions,
            'cabang' => $cabangOptions,
            'kodeKaryawan' => $kodeKaryawanOptions,
            'flagAktif' => [
                ['value' => 'Y', 'label' => 'Aktif'],
                ['value' => 'N', 'label' => 'Non Aktif'],
            ],
        ];
    }

    private function applyOptionalFilter($query, string $column, $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->where($column, $value);
    }

    private function whereNullableEquals($query, string $column, $value): void
    {
        if ($value === null || $value === '') {
            $query->whereNull($column);

            return;
        }

        $query->where($column, $value);
    }

    private function getGroupConfig(string $viewBy): array
    {
        $map = [
            'id_divisi' => [
                'column' => 'Id_Divisi',
                'label_table' => 'View_Divisi_Sub_Divisi',
                'label_key' => 'ID_DIVISI',
                'label_name' => 'nama_divisi',
            ],
            'id_sub_divisi' => [
                'column' => 'Id_Sub_Divisi',
                'label_table' => 'View_Divisi_Sub_Divisi',
                'label_key' => 'ID_DIVISI_SUB_DIVISI',
                'label_name' => 'nama_sub_divisi',
            ],
            'id_level' => [
                'column' => 'Id_Level',
                'label_table' => 'View_Golongan_Sub_Golongan_Level_Jabatan',
                'label_key' => 'Id_Level',
                'label_name' => 'nama_level',
            ],
            'id_workgroup' => [
                'column' => 'Id_Workgroup',
                'label_table' => 'N_HRIS_Workgroup',
                'label_key' => 'Id',
                'label_name' => 'Nama_Workgroup',
            ],
            'cabang_untuk_gaji' => [
                'column' => 'Cabang_Untuk_Gaji',
                'label_table' => 'Karyawan',
                'label_key' => 'Cabang_Untuk_Gaji',
                'label_name' => 'Cabang_Untuk_Gaji',
            ],
        ];

        if (! array_key_exists($viewBy, $map)) {
            throw new \InvalidArgumentException('view_by tidak valid');
        }

        return $map[$viewBy];
    }

    private function buildPerKaryawanGroups(string $search = '', $flagAktif = null): array
    {
        $employeeQuery = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.Id_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vj', 'k.Id_Level_Jabatan', '=', 'vj.ID_Level_Jabatan')
            ->leftJoin('N_HRIS_Workgroup_Member as wgm', function ($join) {
                $join->on('k.Kode_Karyawan', '=', 'wgm.Kode_Karyawan')
                    ->where('wgm.Flag_Aktif', '=', 'Y');
            })
            ->where('k.Aktif', 'Y')
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'wgm.Id_Workgroup',
                'k.Cabang_Untuk_Gaji',
                'k.Id_Level_Jabatan',
                'vd.nama_sub_divisi',
                'vd.ID_DIVISI as Id_Divisi',
                'vd.ID_DIVISI_SUB_DIVISI as Id_Sub_Divisi',
                'vj.ID_Level',
                'vj.nama_level'
            );

        if ($search !== '') {
            $employeeQuery->where(function ($q) use ($search) {
                $q->where('k.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('k.Nama', 'like', "%{$search}%");
            });
        }

        $employees = $employeeQuery->orderBy('k.Nama')->get();

        $rulesQuery = DB::table('N_HRIS_Shift_Rule as r');
        if (in_array($flagAktif, ['Y', 'N'], true)) {
            $rulesQuery->where('r.Flag_Aktif', $flagAktif);
        } else {
            $rulesQuery->where('r.Flag_Aktif', 'Y');
        }

        $rules = $rulesQuery
            ->get([
                'r.Kode_Perusahaan',
                'r.ID_Shift',
                'r.Id_Divisi',
                'r.Id_Sub_Divisi',
                'r.Id_Level',
                'r.Kode_Karyawan',
                'r.Id_Workgroup',
                'r.Cabang_Untuk_Gaji',
            ]);

        $result = [];
        foreach ($employees as $emp) {
            $matched = $rules->filter(function ($rule) use ($emp) {
                return
                    ($rule->Id_Divisi === null || (string) $rule->Id_Divisi === (string) $emp->Id_Divisi) &&
                    ($rule->Id_Sub_Divisi === null || (string) $rule->Id_Sub_Divisi === (string) $emp->Id_Sub_Divisi) &&
                    ($rule->Id_Level === null || (string) $rule->Id_Level === (string) $emp->ID_Level) &&
                    ($rule->Kode_Karyawan === null || (string) $rule->Kode_Karyawan === (string) $emp->Kode_Karyawan) &&
                    ($rule->Id_Workgroup === null || (string) $rule->Id_Workgroup === (string) $emp->Id_Workgroup) &&
                    ($rule->Cabang_Untuk_Gaji === null || (string) $rule->Cabang_Untuk_Gaji === (string) $emp->Cabang_Untuk_Gaji);
            });

            $shiftCount = $matched->pluck('ID_Shift')->unique()->count();
            if ($shiftCount === 0) {
                continue;
            }

            $result[] = [
                '_key' => md5('kode_karyawan|'.$emp->Kode_Karyawan),
                'view_by' => 'kode_karyawan',
                'group_value' => $emp->Kode_Karyawan,
                'group_label' => "{$emp->Nama} ({$emp->Kode_Karyawan})",
                'total_rules' => $matched->count(),
                'total_shifts' => $shiftCount,
                'active_rules' => $matched->count(),
            ];
        }

        return $result;
    }

    private function getKaryawanShiftDetail(string $kodeKaryawan, $flagAktif = null): array
    {
        $emp = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.Id_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('N_HRIS_Workgroup_Member as wgm', function ($join) {
                $join->on('k.Kode_Karyawan', '=', 'wgm.Kode_Karyawan')
                    ->where('wgm.Flag_Aktif', '=', 'Y');
            })
            ->where('k.Kode_Karyawan', $kodeKaryawan)
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'wgm.Id_Workgroup',
                'k.Cabang_Untuk_Gaji',
                'k.Id_Level_Jabatan',
                'vd.nama_sub_divisi',
                'vd.ID_DIVISI as Id_Divisi',
                'vd.ID_DIVISI_SUB_DIVISI as Id_Sub_Divisi'
            )
            ->first();

        if (! $emp) {
            return [];
        }

        $query = DB::table('N_HRIS_Shift_Rule as r')
            ->leftJoin('HRIS_Shift_Kerja as s', 'r.ID_Shift', '=', 's.ID_Shift')
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Id_Divisi')->orWhere('r.Id_Divisi', $emp->Id_Divisi);
            })
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Id_Sub_Divisi')->orWhere('r.Id_Sub_Divisi', $emp->Id_Sub_Divisi);
            })
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Id_Level')->orWhere('r.Id_Level', $emp->Id_Level_Jabatan);
            })
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Kode_Karyawan')->orWhere('r.Kode_Karyawan', $emp->Kode_Karyawan);
            })
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Id_Workgroup')->orWhere('r.Id_Workgroup', $emp->Id_Workgroup);
            })
            ->where(function ($q) use ($emp) {
                $q->whereNull('r.Cabang_Untuk_Gaji')->orWhere('r.Cabang_Untuk_Gaji', $emp->Cabang_Untuk_Gaji);
            });

        if (in_array($flagAktif, ['Y', 'N'], true)) {
            $query->where('r.Flag_Aktif', $flagAktif);
        } else {
            $query->where('r.Flag_Aktif', 'Y');
        }

        $rows = $query->get([
            'r.Kode_Perusahaan',
            'r.ID_Shift',
            's.Nama as Nama_Shift',
            'r.Id_Divisi',
            'r.Id_Sub_Divisi',
            'r.Id_Level',
            'r.Kode_Karyawan',
            'r.Id_Workgroup',
            'r.Cabang_Untuk_Gaji',
        ]);

        $wgMap = [];
        if ($rows->pluck('Id_Workgroup')->filter()->unique()->values()->isNotEmpty()) {
            $wgMap = DB::table('N_HRIS_Workgroup')
                ->whereIn('Id', $rows->pluck('Id_Workgroup')->filter()->unique()->values())
                ->pluck('Nama_Workgroup', 'Id');
        }

        $grouped = $rows->groupBy('ID_Shift')->map(function ($shiftRows) use ($wgMap) {
            $first = $shiftRows->first();
            $sources = $shiftRows->map(function ($r) use ($wgMap) {
                $tags = [];
                if ($r->Id_Divisi !== null) {
                    $tags[] = "Divisi:{$r->Id_Divisi}";
                }
                if ($r->Id_Sub_Divisi !== null) {
                    $tags[] = "SubDiv:{$r->Id_Sub_Divisi}";
                }
                if ($r->Id_Level !== null) {
                    $tags[] = "Level:{$r->Id_Level}";
                }
                if ($r->Kode_Karyawan !== null) {
                    $tags[] = "Karyawan:{$r->Kode_Karyawan}";
                }
                if ($r->Id_Workgroup !== null) {
                    $wgName = $wgMap[$r->Id_Workgroup] ?? null;
                    $tags[] = $wgName ? "WG:{$wgName} (#{$r->Id_Workgroup})" : "WG:{$r->Id_Workgroup}";
                }
                if ($r->Cabang_Untuk_Gaji !== null) {
                    $tags[] = "Cabang:{$r->Cabang_Untuk_Gaji}";
                }
                if (empty($tags)) {
                    $tags[] = 'Global';
                }

                return [
                    'source_label' => implode(' | ', $tags),
                    'rule' => [
                        'kode_perusahaan' => $r->Kode_Perusahaan,
                        'id_shift' => (int) $r->ID_Shift,
                        'id_divisi' => $r->Id_Divisi,
                        'id_sub_divisi' => $r->Id_Sub_Divisi,
                        'id_level' => $r->Id_Level,
                        'kode_karyawan' => $r->Kode_Karyawan,
                        'id_workgroup' => $r->Id_Workgroup,
                        'cabang_untuk_gaji' => $r->Cabang_Untuk_Gaji,
                    ],
                ];
            })->values()->toArray();

            return [
                'id_shift' => (int) $first->ID_Shift,
                'nama_shift' => $first->Nama_Shift ?: ('Shift #'.$first->ID_Shift),
                'total_rules' => count($sources),
                'active_rules' => count($sources),
                'sources' => $sources,
            ];
        })->values()->toArray();

        return $grouped;
    }

    private function normalizeIntValues($input, bool $allowNull = false): array
    {
        $raw = Arr::wrap($input);
        $out = [];

        foreach ($raw as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_numeric($value)) {
                $out[] = (int) $value;
            }
        }

        $out = array_values(array_unique($out));
        if (empty($out)) {
            return $allowNull ? [null] : [];
        }

        return $out;
    }

    private function normalizeStringValues($input, bool $allowNull = false): array
    {
        $raw = Arr::wrap($input);
        $out = [];

        foreach ($raw as $value) {
            if ($value === null) {
                continue;
            }
            $v = trim((string) $value);
            if ($v === '') {
                continue;
            }
            $out[] = $v;
        }

        $out = array_values(array_unique($out));
        if (empty($out)) {
            return $allowNull ? [null] : [];
        }

        return $out;
    }

    private function normalizeKaryawanValues($input, bool $allowNull = false): array
    {
        if (is_string($input)) {
            $input = preg_split('/[\s,;]+/', $input) ?: [];
        }

        return $this->normalizeStringValues($input, $allowNull);
    }
}
