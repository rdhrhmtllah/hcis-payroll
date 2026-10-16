<?php

namespace App\Http\Controllers\KPI\Monitoring\Enforcement;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KPI\Approval\Verification\KpiVerifikasiController;
use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Jobs\KPI\Export\GenerateKpiExportJob;
use App\Jobs\KPI\Monitoring\Enforcement\DispatchEnforceKpiBatchJob;
use App\Jobs\KPI\Monitoring\Enforcement\EnforceKpiCapJob;
use App\Services\KPI\Approval\KpiApprovalService;
use App\Services\KPI\KpiAkumulasiYtdService;
use App\Services\KPI\KpiCancellationService;
use App\Services\KPI\KpiEnforcementService;
use App\Services\KPI\KpiExportService;
use App\Services\KPI\KpiFilterTglMasuk;
use App\Services\KPI\KpiJadwalBerjalan;
use App\Services\KPI\KpiKepegawaianPeriode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * KpiEnforcementController
 *
 * Backend untuk halaman admin "KPI Enforcement & Monitoring Dashboard".
 *   TAB 1  Monitoring  : status pengisian KPI (submission) + tracking performance.
 *   TAB 2  Enforcement : list karyawan belum mengisi + eksekusi auto-cap massal.
 *
 * Eksekusi massal di-dispatch ke Cloud Tasks (EnforceKpiCapJob) dan progress
 * dilacak di N_HRIS_KPI_Execution_Logs untuk dibaca via polling.
 */
class KpiEnforcementController extends Controller
{
    public function __construct(
        protected KpiEnforcementService $service,
        protected KpiApprovalService $kpiApproval,
        protected KpiExportService $exportService,
        protected KpiCancellationService $cancelService,
    ) {}

    public function index()
    {
        return inertia('KPI/Monitoring/Enforcement/kpiEnforcementDashboard', [
            // Monitoring: filter induk = PERIODE (bukan penjadwalan), agar status
            // "sudah/belum mengisi" dinilai per periode lintas penjadwalan
            // (global + perorangan) -> tidak ambigu.
            //   Submission (parameter) -> per TAHUN.
            //   Performance (penilaian) -> per BULAN (YYYY-MM).
            'submissionYears' => $this->fetchYears('submission'),
            'penilaianPeriods' => $this->fetchMonthlyPeriods('penilaian'),
            // Enforcement tetap pakai penjadwalan penilaian (menulis ke jadwal spesifik).
            'penilaianSchedules' => $this->fetchSchedules('penilaian'),
        ]);
    }

    /**
     * Daftar penjadwalan untuk dropdown. Difilter per jenis:
     *   submission -> Flag_Form_KPI = 'Y'   (penjadwalan pengajuan parameter)
     *   penilaian  -> Flag_Penilaian_KPI = 'Y' (penjadwalan update performance)
     */
    public function getSchedules(Request $request)
    {
        $jenis = $request->input('jenis', 'penilaian');

        return ResponseHelper::success($this->fetchSchedules($jenis), 'Daftar penjadwalan berhasil diambil');
    }

    /**
     * Daftar TAHUN untuk filter induk submission/parameter.
     *
     * ── Kenapa Flag_Aktif TIDAK disaring di sini ─────────────────────────────
     *
     * Monitoring memandang ke BELAKANG: pertanyaannya "tahun itu ada pengajuan
     * atau tidak", bukan "tahun itu masih dibuka atau tidak". Menyaring
     * Flag_Aktif membuat sebuah tahun LENYAP dari pemilihnya begitu penjadwalan
     * terakhirnya ditutup — dan bersamanya seluruh riwayat pengajuan tahun itu
     * jadi tak bisa dilihat sama sekali, padahal datanya utuh di basis data.
     *
     * Terukur 10 September 2026 di Web_HRIS: dari 11 penjadwalan Form KPI tahun
     * 2026, DELAPAN sudah Flag_Aktif='T'. Tahun 2026 masih muncul semata karena
     * tiga sisanya kebetulan belum ditutup; begitu ketiganya ditutup, layar
     * monitoring submission kehilangan satu-satunya tahun yang bisa dipilih
     * sementara 913 pengajuan tetap ada.
     *
     * Tiga saudara terdekat metode ini — schedulesInScope(),
     * fetchMonthlyPeriods(), dan getSchedulesInScope() — sudah lebih dulu
     * melepas syarat Flag_Aktif (filternya masih terlihat sebagai komentar di
     * sana). Metode inilah satu-satunya yang tertinggal, jadi separuh layar
     * bersedia menampilkan penjadwalan nonaktif sementara pemilih tahunnya
     * menolak memberi jalan ke sana.
     */
    private function fetchYears(string $jenis): array
    {
        $flagColumn = $jenis === 'submission' ? 'Flag_Form_KPI' : 'Flag_Penilaian_KPI';

        return DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            // Flag_Aktif SENGAJA tidak disaring — lihat catatan di atas.
            ->whereNotNull('Tahun')
            ->select('Tahun')
            ->distinct()
            ->orderByDesc('Tahun')
            ->pluck('Tahun')
            ->map(fn ($t) => ['value' => (string) $t, 'label' => (string) $t])
            ->values()
            ->all();
    }

    /**
     * Daftar PERIODE bulanan (YYYY-MM) untuk filter induk performance. Diambil dari
     * penjadwalan penilaian yang ada.
     */
    private function fetchMonthlyPeriods(string $jenis): array
    {
        $flagColumn = $jenis === 'submission' ? 'Flag_Form_KPI' : 'Flag_Penilaian_KPI';

        return DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            // ->where('Flag_Aktif', 'Y')
            ->whereNotNull('Periode')
            ->where('Periode', '!=', '')
            ->select('Periode')
            ->distinct()
            ->orderByDesc('Periode')
            ->pluck('Periode')
            ->map(
                fn ($p) => [
                    'value' => $p, // 'YYYY-MM'
                    'label' => Carbon::parse($p)->isoFormat('MMMM YYYY'),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Penjadwalan DI DALAM scope (tahun untuk submission, periode bulan untuk
     * performance). Untuk dropdown "filter dalam" (opsional, default Semua).
     */
    public function getSchedulesInScope(Request $request)
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:submission,penilaian'],
            'tahun' => ['nullable', 'string'],
            'periode' => ['nullable', 'string'],
        ]);

        $flagColumn = $validated['jenis'] === 'submission' ? 'Flag_Form_KPI' : 'Flag_Penilaian_KPI';

        $rows = DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            // ->where('Flag_Aktif', 'Y')
            ->when(
                $validated['jenis'] === 'submission' && ! empty($validated['tahun']),
                fn ($q) => $q->where('Tahun', $validated['tahun']),
            )
            ->when(
                $validated['jenis'] === 'penilaian' && ! empty($validated['periode']),
                fn ($q) => $q->where('Periode', $validated['periode']),
            )
            ->orderByDesc('Id_Master_Penjadwalan')
            ->get(['Id_Master_Penjadwalan', 'Nama_Kegiatan', 'Flag_Perorangan']);

        $data = $rows
            ->map(
                fn ($r) => [
                    'value' => (int) $r->Id_Master_Penjadwalan,
                    'label' => (trim($r->Nama_Kegiatan ?? '') ?: 'Penjadwalan').
                        ' ('.
                        (($r->Flag_Perorangan ?? 'T') === 'Y' ? 'Perorangan' : 'Global').
                        ')',
                    'tipe' => ($r->Flag_Perorangan ?? 'T') === 'Y' ? 'Perorangan' : 'Global',
                ],
            )
            ->values()
            ->all();

        return ResponseHelper::success($data, 'Daftar penjadwalan dalam periode berhasil diambil');
    }

    private function fetchSchedules(string $jenis): array
    {
        $flagColumn = $jenis === 'submission' ? 'Flag_Form_KPI' : 'Flag_Penilaian_KPI';

        // Tampilkan semua penjadwalan (aktif & nonaktif). Penjadwalan aktif
        // diurutkan lebih dulu, lalu beri keterangan status di label.
        return DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            ->whereNotNull('Periode')
            ->orderByRaw("CASE WHEN Flag_Aktif = 'Y' THEN 0 ELSE 1 END")
            ->orderByDesc('Periode')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->get([
                'Id_Master_Penjadwalan',
                'Periode',
                'Tahun',
                'Nama_Kegiatan',
                'Flag_Perorangan',
                'Flag_Aktif',
                'Jadwal_Mulai',
                'Jadwal_Akhir',
            ])
            ->map(function ($row) {
                $periode = Carbon::parse($row->Periode);
                $tipe = ($row->Flag_Perorangan ?? 'T') === 'Y' ? 'Perorangan' : 'Global';
                $nama = trim($row->Nama_Kegiatan ?? '') ?: 'Penjadwalan';
                $isAktif = ($row->Flag_Aktif ?? 'T') === 'Y';
                $statusLabel = $isAktif ? 'Aktif' : 'Nonaktif';

                return [
                    // value = Id penjadwalan (bukan periode) agar presisi.
                    'value' => (int) $row->Id_Master_Penjadwalan,
                    'label' => $periode->isoFormat('MMMM YYYY').' — '.$nama.' ('.$tipe.') · '.$statusLabel,
                    'periode' => $periode->toDateString(),
                    'tahun' => $row->Tahun,
                    'tipe' => $tipe,
                    'is_aktif' => $isAktif,
                    'jadwal_mulai' => $row->Jadwal_Mulai,
                    'jadwal_akhir' => $row->Jadwal_Akhir,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve periode (Carbon) dari Id_Penjadwalan. Dipakai semua endpoint yang
     * menerima id_penjadwalan agar query tetap berbasis periode varchar 'YYYY-MM'.
     */
    private function resolvePeriodeFromSchedule(int $idPenjadwalan): ?Carbon
    {
        $row = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $idPenjadwalan)->value('Periode');

        return $row ? Carbon::parse($row) : null;
    }

    /**
     * Id penjadwalan dalam SCOPE monitoring:
     *   submission -> Flag_Form_KPI='Y' + Tahun = $scope
     *   penilaian  -> Flag_Penilaian_KPI='Y' + Periode = $scope (YYYY-MM)
     * Bila $idPenjadwalan diberikan (filter dalam), dibatasi ke satu itu saja.
     *
     * @return \Illuminate\Support\Collection<int,object> {Id, Flag_Perorangan, Jadwal_Akhir, Nama_Kegiatan}
     */
    /**
     * @param  string|array<int,string>  $scope  Satu nilai, atau daftar periode
     *                                           untuk scope YTD (Jan..bulan).
     */
    private function schedulesInScope(string $jenis, $scope, ?int $idPenjadwalan = null)
    {
        $flagColumn = $jenis === 'submission' ? 'Flag_Form_KPI' : 'Flag_Penilaian_KPI';
        $scopeColumn = $jenis === 'submission' ? 'Tahun' : 'Periode';

        return DB::table('KPI_Master_Penjadwalan')
            ->where($flagColumn, 'Y')
            // ->where('Flag_Aktif', 'Y')
            ->when(
                is_array($scope),
                fn ($q) => $q->whereIn($scopeColumn, $scope),
                fn ($q) => $q->where($scopeColumn, $scope),
            )
            ->when($idPenjadwalan, fn ($q) => $q->where('Id_Master_Penjadwalan', $idPenjadwalan))
            ->get(['Id_Master_Penjadwalan', 'Flag_Perorangan', 'Flag_Aktif', 'Jadwal_Akhir', 'Nama_Kegiatan']);
    }

    /**
     * Universe karyawan terjadwal untuk SCOPE (union semua penjadwalan dalam scope):
     * gabungan karyawan dari penjadwalan global (semua karyawan aktif pada periode)
     * + perorangan (yang terdaftar). Tiap karyawan muncul sekali. Mengembalikan null
     * bila tidak ada penjadwalan dalam scope.
     *
     * $batasKepegawaian WAJIB: penyaring kepegawaian memakai status PADA PERIODE, bukan
     * status hari ini. Lihat KpiKepegawaianPeriode untuk sebabnya — dengan
     * `k.Aktif='Y'` polos, laporan sebuah bulan menyusut sendiri setiap kali ada
     * karyawan resign, berbulan-bulan setelah bulan itu ditutup.
     */
    private function scheduledEmployeesInScope($schedules, Carbon $batasKepegawaian): ?\Illuminate\Database\Query\Builder
    {
        if ($schedules->isEmpty()) {
            return null;
        }

        $hasGlobal = $schedules->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y');
        $peroranganIds = $schedules
            ->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
            ->pluck('Id_Master_Penjadwalan')
            ->all();

        $q = KpiKepegawaianPeriode::terapkan(
            DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vg.ID_Level_Jabatan',
                ),
            $batasKepegawaian,
        );

        if ($hasGlobal) {
            // Global -> semua karyawan aktif pada periode (perorangan otomatis tercakup).
            return $q;
        }

        // Hanya perorangan -> batasi ke karyawan yang terdaftar di salah satu jadwal.
        return $q->whereIn('k.Kode_Karyawan', function ($sub) use ($peroranganIds) {
            $sub->from('N_KPI_Master_Penjadwalan_Perorangan')
                ->whereIn('Id_Master_Penjadwalan', $peroranganIds)
                // ->where('Flag_Aktif', 'Y')
                ->select('Kode_Karyawan');
        });
    }

    /**
     * Apakah SEMUA penjadwalan dalam scope sudah lewat tanggal pengisiannya.
     * "Belum" (masih ada jadwal berjalan) vs "Tidak" (semua sudah lewat).
     */
    private function isScopeExpired($schedules): bool
    {
        if ($schedules->isEmpty()) {
            return false;
        }

        return $schedules->every(fn ($s) => $s->Jadwal_Akhir && Carbon::parse($s->Jadwal_Akhir)->isPast());
    }

    /**
     * Terapkan filter ISI monitoring (divisi/departemen/level/jabatan/lokasi) ke
     * builder universe. Filter penjadwalan = scope/induk (terpisah).
     */
    private function applyMonitorFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('lokasi'), fn ($q) => $q->whereIn('k.Lokasi', (array) $request->input('lokasi')))
            ->when(
                $request->filled('divisi'),
                fn ($q) => $q->whereIn('vd.nama_divisi', (array) $request->input('divisi')),
            )
            ->when(
                $request->filled('departemen'),
                fn ($q) => $q->whereIn('vd.nama_sub_divisi', (array) $request->input('departemen')),
            )
            ->when($request->filled('level'), fn ($q) => $q->whereIn('vg.nama_level', (array) $request->input('level')))
            ->when(
                $request->filled('jabatan'),
                fn ($q) => $q->whereIn('vg.nama_jabatan', (array) $request->input('jabatan')),
            )
            ->tap(fn ($q) => KpiFilterTglMasuk::terapkan($q, KpiFilterTglMasuk::rentang($request->input('tgl_masuk'))));
    }

    // =====================================================================
    // TAB 1a — KPI Submission (status parameter / approval)
    // =====================================================================
    public function getSubmissionStatus(Request $request)
    {
        $validated = $request->validate([
            'tahun' => ['required', 'string'], // scope induk = TAHUN
            'id_penjadwalan' => ['nullable', 'integer'], // filter dalam (opsional)
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $tahun = $validated['tahun'];
        $idPenjadwalan = $validated['id_penjadwalan'] ?? null;
        $search = strip_tags($validated['search'] ?? '');
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 15;
        $statusFilter = array_filter((array) $request->input('status', []));

        // Penjadwalan form dalam scope tahun (semua, atau satu bila difilter).
        $schedules = $this->schedulesInScope('submission', $tahun, $idPenjadwalan);
        $universe = $this->scheduledEmployeesInScope(
            $schedules,
            KpiKepegawaianPeriode::batasDariScope($tahun),
        );
        if (! $universe) {
            return ResponseHelper::error('Tidak ada penjadwalan untuk tahun ini.', 404);
        }

        $berjalan = KpiJadwalBerjalan::dariJadwal($schedules);
        $scheduleIds = $schedules->pluck('Id_Master_Penjadwalan')->all();

        // Universe karyawan terjadwal + LEFT JOIN pengajuan (lintas penjadwalan dalam
        // scope). Nama kegiatan penjadwalan disertakan utk keterangan "via jadwal X".
        $rows = $this->applyMonitorFilters($universe, $request)
            ->leftJoin('KPI_Pengajuan as p', function ($join) use ($scheduleIds) {
                $join
                    ->on('p.Id_User', '=', 'k.UserID_Web')
                    ->whereIn('p.Id_Penjadwalan', $scheduleIds)
                    ->where('p.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->when($search, function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($w) => $w->where('k.Nama', 'like', $like)->orWhere('k.Kode_Karyawan', 'like', $like));
            })
            ->orderBy('k.Nama')
            ->get([
                'k.UserID_Web',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.Lokasi',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
                'p.Id_Pengajuan',
                'p.No_Transaksi',
                'p.Status as Pengajuan_Status',
                'p.Flag_Approval',
                'j.Nama_Kegiatan as Jadwal_Nama',
                'j.Flag_Perorangan as Jadwal_Peror',
            ]);

        // Satu karyawan bisa muncul >1 baris bila punya pengajuan di >1 penjadwalan.
        // Ambil pengajuan terbaru per karyawan (Id_Pengajuan terbesar).
        $rows = $rows
            ->groupBy('Kode_Karyawan')
            ->map(function ($group) {
                return $group->sortByDesc(fn ($r) => $r->Id_Pengajuan ?? 0)->first();
            })
            ->values();

        // Status approval seluruh baris diturunkan SEKALI dalam query konstan (bukan
        // ~3 query/baris) untuk menghindari N+1 saat banyak pengajuan berstatus menunggu.
        $statusMap = $this->cancelService->deriveApprovalStatusMap($rows, 'parameter');

        $mapped = $rows->map(function ($row) use ($berjalan, $statusMap) {
            if (! $row->Id_Pengajuan) {
                // Label per ORANG: hanya jadwal yang benar-benar membebani dia yang
                // boleh menahannya di "Belum". Lihat KpiJadwalBerjalan.
                return $this->makeMonitorRow(
                    $row,
                    $berjalan->membebani($row->Kode_Karyawan ?? null)
                        ? ['status' => 'belum_submit', 'label' => 'Belum Submit', 'stage' => null]
                        : ['status' => 'tidak_submit', 'label' => 'Tidak Submit', 'stage' => null],
                );
            }

            $derived = ($row->Flag_Approval ?? null) === 'Y'
                ? ['status' => 'approved', 'label' => 'Disetujui', 'stage' => null]
                : ($statusMap[$row->No_Transaksi] ?? ['status' => 'waiting', 'label' => 'Menunggu Approval', 'stage' => null]);

            return $this->makeMonitorRow($row, $derived, [
                'id_pengajuan' => (int) $row->Id_Pengajuan,
                'no_transaksi' => $row->No_Transaksi,
                'jadwal_nama' => $this->scheduleLabel($row->Jadwal_Nama, $row->Jadwal_Peror),
            ]);
        });

        $mapped = $this->attachCancellationTrace($mapped, $scheduleIds, 'parameter');

        if (! empty($statusFilter)) {
            $mapped = $mapped->whereIn('status', $statusFilter)->values();
        }
        $mapped = $this->applyRiwayatFilter($mapped, $request);

        if ($request->boolean('ids_only')) {
            return ResponseHelper::success($this->selectableIds($mapped, 'id_pengajuan'), 'Id transaksi berhasil diambil');
        }

        $total = $mapped->count();
        $data = $mapped->forPage($page, $perPage)->values();

        return ResponseHelper::successWithPagination(
            $data,
            $page,
            $perPage,
            $total,
            'Status submission KPI berhasil diambil',
        );
    }

    /**
     * Id transaksi SELURUH baris yang cocok scope+filter (lintas halaman), untuk
     * "centang semua" di sub-tab monitoring. Hanya baris yang benar-benar punya
     * transaksi -- baris "Belum Submit"/draft tidak punya apa pun untuk dibatalkan.
     */
    private function selectableIds($mapped, string $idKey): array
    {
        $rows = $mapped->filter(fn ($r) => ! empty($r[$idKey]) && empty($r['is_draft']))->values();

        return [
            // Status ikut dikirim: baris terpilih bisa berada di halaman lain, sehingga
            // FE tak bisa membaca statusnya dari tabel untuk menghitung "sudah disetujui".
            'items' => $rows
                ->map(fn ($r) => [
                    'id' => (int) $r[$idKey],
                    'nama' => $r['nama'],
                    'status' => $r['status'],
                ])
                ->all(),
            'total' => $rows->count(),
            'max' => KpiCancellationService::MAX_ITEMS,
        ];
    }

    /**
     * Tempelkan jejak pembatalan ke baris monitoring.
     *
     * Transaksi yang dibatalkan sengaja tidak lagi muncul sebagai transaksi aktif
     * (karyawan kembali "Belum Submit"), tapi admin tetap harus bisa melihat bahwa
     * kekosongan itu BUKAN karena karyawan tak mengisi -- melainkan karena
     * transaksinya dibatalkan. Jejak ini yang dipakai badge & modal detail di FE.
     */
    private function attachCancellationTrace($mapped, array $scheduleIds, string $domain)
    {
        $trace = $this->cancelService->cancellationTraceFor($scheduleIds, $domain);

        if ($trace->isEmpty()) {
            return $mapped;
        }

        return $mapped->map(function ($row) use ($trace) {
            $row['cancellation'] = $trace->get($row['id_user']);

            return $row;
        });
    }

    /**
     * Filter "Riwayat" (post-mapping, sama pola dengan filter status): saring baris
     * yang transaksinya pernah dibatalkan.
     */
    private function applyRiwayatFilter($mapped, Request $request)
    {
        $riwayat = array_filter((array) $request->input('riwayat', []));

        if (! in_array('pernah_dibatalkan', $riwayat, true)) {
            return $mapped;
        }

        return $mapped->filter(fn ($row) => ! empty($row['cancellation']))->values();
    }

    /**
     * Label kecil penjadwalan untuk keterangan "submit via jadwal X".
     */
    private function scheduleLabel(?string $nama, ?string $flagPeror): ?string
    {
        if (! $nama) {
            return null;
        }
        $tipe = ($flagPeror ?? 'T') === 'Y' ? 'Perorangan' : 'Global';

        return trim($nama).' ('.$tipe.')';
    }

    /**
     * Bentuk satu baris monitoring standar (identitas karyawan + status + extra).
     */
    private function makeMonitorRow($row, array $derived, array $extra = []): array
    {
        return array_merge(
            [
                'id_user' => isset($row->UserID_Web) ? (int) $row->UserID_Web : null,
                'kode_karyawan' => $row->Kode_Karyawan,
                'nama' => $row->Nama,
                // ISO saja; format tampilannya diputuskan frontend (utils/tanggal).
                'tgl_masuk' => ! empty($row->Tgl_Masuk) ? Carbon::parse($row->Tgl_Masuk)->format('Y-m-d') : null,
                'lokasi' => $row->Lokasi ?? null,
                'divisi' => $row->Divisi,
                'departemen' => $row->Departemen,
                'level' => $row->Level,
                'jabatan' => $row->Jabatan,
                'no_transaksi' => null,
                'jadwal_nama' => null,
                'status' => $derived['status'],
                'status_label' => $derived['label'],
                'current_stage' => $derived['stage'] ?? null,
                // Diisi attachCancellationTrace() bila karyawan ini pernah transaksinya
                // dibatalkan pada scope penjadwalan yang sedang dilihat.
                'cancellation' => null,
            ],
            $extra,
        );
    }

    /**
     * Turunkan status approval (parameter/performance) dari header + alur approval.
     *   Approved : header Flag_Approval = 'Y' atau semua step approved
     *   Rejected : ada step ditolak
     *   Waiting  : sudah diajukan tapi belum selesai (gabungan "menunggu" & "sedang
     *              berjalan"; level berjalan tetap ditampilkan sebagai keterangan)
     *
     * CATATAN: TIDAK ada status "Draft" di sini. Pengajuan/performance final selalu
     * dibuat bersama alur approval dalam satu transaksi DB, jadi tanpa No_Transaksi
     * atau flow kosong = anomali yang diperlakukan sebagai "Menunggu Approval"
     * (bukan Draft). Draft asli hanya untuk Update Performance yang belum difinalisasi
     * (tabel _Sementara) dan ditangani terpisah di getPerformanceTracking().
     */
    private function deriveApprovalStatus($row, string $domain = 'parameter'): array
    {
        return $this->cancelService->deriveApprovalStatus($row, $domain);
    }

    // =====================================================================
    // TAB 1b — Performance Tracking
    // =====================================================================
    public function getPerformanceTracking(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'], // scope induk = BULAN (YYYY-MM)
            // 'ytd' = akumulasi Januari s/d bulan pada 'periode'. Bulanannya tetap
            // bawaan supaya pemanggil lama tidak berubah perilakunya.
            'mode' => ['nullable', 'in:bulan,ytd'],
            'id_penjadwalan' => ['nullable', 'integer'], // filter dalam (opsional)
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $periode = $validated['periode'];
        $modeYtd = ($validated['mode'] ?? 'bulan') === 'ytd';
        $idPenjadwalan = $validated['id_penjadwalan'] ?? null;
        $search = strip_tags($validated['search'] ?? '');
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 15;
        $statusFilter = array_filter((array) $request->input('status', []));

        // Scope penjadwalan mengikuti mode. Pada YTD, universe-nya adalah siapa pun
        // yang terjadwal di SALAH SATU bulan Jan..N — bukan hanya bulan N — karena
        // angka YTD memang mengakumulasi seluruh rentang itu. Kalau universe tetap
        // satu bulan, orang yang dinilai Februari tapi tidak Agustus akan hilang
        // dari laporan YTD Agustus, padahal nilainya ikut terhitung.
        $ytdSvc = app(\App\Services\KPI\KpiAkumulasiYtdService::class);
        $tahunScope = (int) substr($periode, 0, 4);
        $bulanScope = (int) substr($periode, 5, 2);
        $periodeYtd = $ytdSvc->periodeSampai($tahunScope, $bulanScope);

        $schedules = $this->schedulesInScope(
            'penilaian',
            $modeYtd ? $periodeYtd : $periode,
            $idPenjadwalan,
        );
        $universe = $this->scheduledEmployeesInScope(
            $schedules,
            KpiKepegawaianPeriode::batasDariScope($modeYtd ? $periodeYtd : $periode),
        );
        if (! $universe) {
            return ResponseHelper::error('Tidak ada penjadwalan untuk periode ini.', 404);
        }

        $berjalan = KpiJadwalBerjalan::dariJadwal($schedules);
        $scheduleIds = $schedules->pluck('Id_Master_Penjadwalan')->all();

        // Universe karyawan terjadwal + LEFT JOIN performance.
        //
        // Cakupannya SENGAJA memakai dua jalan: penjadwalan yang ada dalam scope,
        // ATAU kolom Periode baris itu sendiri.
        //
        // Alasannya: penjaga finalisasi (assertBelumAdaRealisasiHidup) menolak
        // pengajuan baru berdasarkan (Id_User, Periode) — ia tidak peduli baris
        // lama itu bernaung di penjadwalan mana. Monitoring dulu hanya melihat
        // Id_Penjadwalan yang sedang dalam scope, jadi begitu penjadwalannya
        // ditutup atau ternyata bukan jadwal penilaian, barisnya HILANG dari layar
        // admin tetapi TETAP memblokir pengisian — dan tidak ada seorang pun yang
        // bisa membatalkannya karena tidak ada yang bisa melihatnya.
        //
        // Diukur di Web_HRIS 10 Sep 2026: 60 dari 64 realisasi hidup berada dalam
        // keadaan itu. Contohnya AKPI-06-2026-0032 (A4, periode 2026-05) yang
        // bernaung di penjadwalan #17 — periode 2026-07, Flag_Penilaian_KPI NULL,
        // dan sudah tidak aktif.
        //
        // Baris kembar dari dua jalan itu tidak jadi masalah: pengelompokan di
        // bawah sudah mengambil yang terbaru per karyawan.
        $rows = $this->applyMonitorFilters($universe, $request)
            ->leftJoin('KPI_Update_Performance as up', function ($join) use ($scheduleIds, $periode) {
                $join
                    ->on('up.Id_User', '=', 'k.UserID_Web')
                    ->where('up.Flag_Aktif', '=', 'Y')
                    // Record dibatalkan (Status='Y') dianggap belum mengisi -> hanya yang
                    // benar-benar aktif (Status NULL) yang dihitung "sudah mengisi".
                    ->whereNull('up.Status')
                    ->where(function ($q) use ($scheduleIds, $periode) {
                        // Wildcard hanya di belakang supaya tetap sargable, dan
                        // cocok baik untuk 'YYYY-MM' maupun 'YYYY-MM-DD'.
                        $q->whereIn('up.Id_Penjadwalan', $scheduleIds)
                            ->orWhere('up.Periode', 'like', $periode.'%');
                    });
            })
            ->leftJoin('KPI_Master_Penjadwalan as j', 'up.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->when($search, function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where(fn ($w) => $w->where('k.Nama', 'like', $like)->orWhere('k.Kode_Karyawan', 'like', $like));
            })
            ->orderBy('k.Nama')
            ->get([
                'k.UserID_Web',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Tgl_Masuk',
                'k.Lokasi',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
                'up.Id_Update_Performance',
                'up.Id_Pengajuan',
                'up.No_Transaksi',
                'up.Periode',
                'up.Total_Score',
                'up.Total_Score_Achievement',
                'up.Total_Score_Raw',
                'up.Flag_Capped',
                'up.Cap_Min_Applied',
                'up.Cap_Max_Applied',
                'up.Status_Pengajuan',
                'up.Flag_Approval',
                'j.Nama_Kegiatan as Jadwal_Nama',
                'j.Flag_Perorangan as Jadwal_Peror',
            ]);

        // Satu karyawan bisa punya performance di >1 penjadwalan -> ambil terbaru.
        $rows = $rows
            ->groupBy('Kode_Karyawan')
            ->map(function ($group) {
                return $group->sortByDesc(fn ($r) => $r->Id_Update_Performance ?? 0)->first();
            })
            ->values();

        // Draft hidup (sudah diisi user tapi BELUM difinalisasi) tersimpan di tabel
        // sementara, bukan tabel final. Ambil draft terbaru per user dalam scope:
        // Flag_Aktif='Y' DAN belum pindah (Flag_Pindah <> 'Y'). Dipakai untuk
        // menandai status "Draft" pada user yang belum punya record final.
        $draftByUser = DB::table('KPI_Update_Performance_Sementara')
            ->whereIn('Id_Penjadwalan', $scheduleIds)
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y'))
            ->get([
                'Id_User', 'Id_Update_Performance_Sementara',
                'Total_Score', 'Total_Score_Achievement', 'Total_Bobot',
            ])
            ->groupBy('Id_User')
            ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance_Sementara')->first());

        // Karyawan tanpa performance final & tanpa draft: bedakan yang benar-benar
        // belum mengisi dari yang memang tak punya parameter+target untuk periode ini.
        // Dihitung sekali (batch) agar bebas N+1.
        $targetDate = Carbon::parse($periode);
        $candidateIds = $rows
            ->filter(fn ($r) => ! $r->Id_Update_Performance && ! $draftByUser->get($r->UserID_Web))
            ->pluck('UserID_Web')
            ->map(fn ($v) => (int) $v)
            ->all();
        $paramTargetClass = $this->service->classifyParameterTargetForPeriod($candidateIds, $targetDate);

        // Status approval seluruh baris sekali (query konstan) — hindari N+1 per baris.
        $statusMap = $this->cancelService->deriveApprovalStatusMap($rows, 'performance');
        // current_approver (label "Menunggu X") hanya untuk tampilan tabel; saat
        // ids_only (centang semua) payload baris dibuang, jadi lewati derivasi mahalnya.
        $idsOnly = $request->boolean('ids_only');

        $mapped = $rows->map(function ($row) use ($berjalan, $draftByUser, $paramTargetClass, $statusMap, $idsOnly) {
            // Belum ada record performance final.
            if (! $row->Id_Update_Performance) {
                // Ada draft hidup -> status Draft (sudah diisi, belum difinalisasi).
                $draft = $draftByUser->get($row->UserID_Web);
                if ($draft) {
                    return $this->makeMonitorRow(
                        $row,
                        ['status' => 'draft', 'label' => 'Draft', 'stage' => null],
                        [
                            'is_draft' => true,
                            'id_update_performance_sementara' => (int) $draft->Id_Update_Performance_Sementara,
                            'total_score' => (float) $draft->Total_Score,
                            'total_score_achievement' => (float) $draft->Total_Score_Achievement,
                            'total_score_raw' => (float) $draft->Total_Score,
                            'is_capped' => false,
                            'approved' => false,
                        ],
                    );
                }

                // Bukan "belum mengisi", melainkan memang tak ada yang bisa diisi/dinilai:
                // dibedakan antara tak punya parameter sama sekali vs punya parameter tapi
                // tak ada target untuk periode ini.
                $cls = $paramTargetClass[(int) $row->UserID_Web] ?? 'no_parameter';
                if ($cls === 'no_parameter') {
                    return $this->makeMonitorRow(
                        $row,
                        ['status' => 'tanpa_parameter', 'label' => 'Tidak Ada Parameter', 'stage' => null],
                    );
                }
                if ($cls === 'no_target') {
                    return $this->makeMonitorRow(
                        $row,
                        ['status' => 'tanpa_target', 'label' => 'Tidak Ada Target', 'stage' => null],
                    );
                }

                // Belum diisi sama sekali -> belum/tidak update. Label per ORANG:
                // hanya jadwal yang benar-benar membebani dia yang boleh menahannya
                // di "Belum". Lihat KpiJadwalBerjalan.
                return $this->makeMonitorRow(
                    $row,
                    $berjalan->membebani($row->Kode_Karyawan ?? null)
                        ? ['status' => 'belum_submit', 'label' => 'Belum Update']
                        : ['status' => 'tidak_submit', 'label' => 'Tidak Update'],
                );
            }

            $approved = ($row->Flag_Approval ?? null) === 'Y';
            $derived = $approved
                ? ['status' => 'approved', 'label' => 'Disetujui', 'stage' => null]
                : ($statusMap[$row->No_Transaksi] ?? ['status' => 'waiting', 'label' => 'Menunggu Approval', 'stage' => null]);
            $currentApprover = ($approved || $idsOnly) ? null : $this->deriveCurrentApprover($row->No_Transaksi);

            return $this->makeMonitorRow($row, $derived, [
                'id_update_performance' => (int) $row->Id_Update_Performance,
                'id_pengajuan' => (int) $row->Id_Pengajuan,
                'periode' => $row->Periode,
                'no_transaksi' => $row->No_Transaksi,
                'jadwal_nama' => $this->scheduleLabel($row->Jadwal_Nama, $row->Jadwal_Peror),
                'total_score' => (float) $row->Total_Score,
                'total_score_achievement' => (float) $row->Total_Score_Achievement,
                'total_score_raw' => (float) $row->Total_Score_Raw,
                'is_capped' => ($row->Flag_Capped ?? 'T') === 'Y',
                'cap_min' => $row->Cap_Min_Applied !== null ? (float) $row->Cap_Min_Applied : null,
                'cap_max' => $row->Cap_Max_Applied !== null ? (float) $row->Cap_Max_Applied : null,
                'status_pengajuan' => $row->Status_Pengajuan,
                'approved' => $approved,
                'current_approver' => $currentApprover,
            ]);
        });

        $mapped = $this->attachCancellationTrace($mapped, $scheduleIds, 'performance');

        if ($modeYtd) {
            $mapped = $this->terapkanSkorYtd($mapped, $ytdSvc, $tahunScope, $periodeYtd, $periode);
        }

        if (! empty($statusFilter)) {
            $mapped = $mapped->whereIn('status', $statusFilter)->values();
        }
        $mapped = $this->applyRiwayatFilter($mapped, $request);

        if ($request->boolean('ids_only')) {
            return ResponseHelper::success(
                $this->selectableIds($mapped, 'id_update_performance'),
                'Id transaksi berhasil diambil',
            );
        }

        $total = $mapped->count();
        $data = $mapped->forPage($page, $perPage)->values();

        return ResponseHelper::successWithPagination(
            $data,
            $page,
            $perPage,
            $total,
            'Performance tracking berhasil diambil',
        );
    }

    /**
     * Timpa kolom skor baris monitoring dengan angka YTD AKUMULASI.
     *
     * Angkanya diambil dari KpiAkumulasiYtdService — sumber yang sama dengan
     * dashboard pribadi, dashboard tim, dan `kpi:verify-ytd`. Ini bukan
     * kenyamanan: kalau monitoring menghitung sendiri, admin akan menarik laporan
     * yang berselisih dengan layar yang dilihat karyawan, dan tak seorang pun bisa
     * menjelaskan mana yang benar.
     *
     * Baris YTD SENGAJA kehilangan id_update_performance dan no_transaksi. Ia
     * agregat sepanjang Jan..N, bukan satu transaksi — dan tanpa itu tombol batal
     * di layar admin akan menjanjikan sesuatu yang maknanya kabur: yang tampil
     * gabungan, yang terbatalkan satu baris.
     *
     * @param  \Illuminate\Support\Collection  $mapped
     * @param  array<int,string>  $periodeYtd
     * @return \Illuminate\Support\Collection
     */
    private function terapkanSkorYtd(
        $mapped,
        \App\Services\KPI\KpiAkumulasiYtdService $ytdSvc,
        int $tahun,
        array $periodeYtd,
        string $periodeAkhir,
    ) {
        $userIds = $mapped->pluck('id_user')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
        $skor = $ytdSvc->skorPerUser($userIds, $tahun, $periodeYtd);

        $label = 'YTD '.Carbon::parse($tahun.'-01-01')->translatedFormat('M').'–'
            .Carbon::parse($periodeAkhir.'-01')->translatedFormat('M Y');

        return $mapped->map(function ($row) use ($skor, $label, $periodeAkhir) {
            $kunci = (string) ($row['id_user'] ?? '');
            $adaSkor = $kunci !== '' && array_key_exists($kunci, $skor);

            $row['is_ytd'] = true;
            $row['periode'] = $periodeAkhir;
            $row['periode_label'] = $label;

            // Pembatalan tidak berlaku pada agregat — layar membaca dua kunci ini
            // untuk mematikan centang dan tombolnya.
            $row['id_update_performance'] = null;
            $row['no_transaksi'] = null;
            $row['cancel_disabled_reason'] = 'Mode YTD menampilkan akumulasi Jan–'
                .Carbon::parse($periodeAkhir.'-01')->translatedFormat('M').
                '. Pembatalan transaksi dilakukan di mode Bulanan.';

            if ($adaSkor) {
                $row['total_score'] = round((float) $skor[$kunci], 2);
                $row['total_score_achievement'] = null;
                $row['total_score_raw'] = null;
                $row['has_ytd_score'] = true;
            } else {
                // Tidak punya realisasi terverifikasi sepanjang rentang. Dikatakan
                // apa adanya, bukan diberi angka lantai cap — kekeliruan yang persis
                // pernah membuat `kpi:verify-ytd` melaporkan 50 untuk orang tanpa data.
                $row['total_score'] = null;
                $row['total_score_achievement'] = null;
                $row['total_score_raw'] = null;
                $row['has_ytd_score'] = false;
            }

            return $row;
        })->values();
    }

    /**
     * Opsi filter ISI monitoring (divisi/departemen/level/jabatan/lokasi) — distinct
     * dari universe karyawan terjadwal pada penjadwalan terpilih.
     */
    public function getMonitorFilterOptions(Request $request)
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:submission,penilaian'],
            'tahun' => ['nullable', 'string'],
            'periode' => ['nullable', 'string'],
            'id_penjadwalan' => ['nullable', 'integer'],
        ]);

        $scope = $validated['jenis'] === 'submission' ? $validated['tahun'] ?? '' : $validated['periode'] ?? '';

        $schedules = $this->schedulesInScope($validated['jenis'], $scope, $validated['id_penjadwalan'] ?? null);
        $universe = $this->scheduledEmployeesInScope(
            $schedules,
            KpiKepegawaianPeriode::batasDariScope($scope),
        );
        if (! $universe) {
            return ResponseHelper::error('Tidak ada penjadwalan untuk scope ini.', 404);
        }

        $pluck = fn ($column) => (clone $universe)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();

        return ResponseHelper::success(
            [
                'lokasi' => $pluck('k.Lokasi'),
                'divisi' => $pluck('vd.nama_divisi'),
                'departemen' => $pluck('vd.nama_sub_divisi'),
                'level' => $pluck('vg.nama_level'),
                'jabatan' => $pluck('vg.nama_jabatan'),
            ],
            'Opsi filter monitoring berhasil diambil',
        );
    }

    // =====================================================================
    // Detail modal — isi transaksi (parameter) + alur approval
    // =====================================================================

    /**
     * Detail SUBMISSION (parameter KPI yang diajukan) untuk satu pengajuan.
     * Menampilkan semua parameter beserta target & atributnya (BSC, formula,
     * satuan, bobot, deskripsi). Pola mengikuti FormKpiController/PerformanceApproval.
     */
    public function getSubmissionDetail(Request $request)
    {
        $validated = $request->validate([
            'id_pengajuan' => ['required', 'integer'],
        ]);

        $idPengajuan = (int) $validated['id_pengajuan'];

        $header = DB::table('KPI_Pengajuan as p')
            ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'k.ID_Level_Jabatan',
                '=',
                'vg.ID_Level_Jabatan',
            )
            ->where('p.Id_Pengajuan', $idPengajuan)
            ->first([
                'p.Id_Pengajuan',
                'p.No_Transaksi',
                'p.Tanggal',
                'p.Jam',
                'p.Flag_Approval',
                'p.Status',
                'p.Cancel_Reason',
                'p.Cancelled_By',
                'p.Cancelled_By_Nama',
                'p.Cancelled_At',
                'p.Cancel_Batch_Id',
                'k.Kode_Karyawan',
                'k.Nama',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
            ]);

        if (! $header) {
            return ResponseHelper::error('Pengajuan tidak ditemukan.', 404);
        }

        // Parameter + atribut master.
        $params = DB::table('KPI_Pengajuan_Detail as pd')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->leftJoin('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'pd.Id_Master_Target')
            ->where('pd.Id_Pengajuan', $idPengajuan)
            ->get([
                'pd.Id_Pengajuan_Detail',
                'pd.Nama_Parameter',
                'pd.Deskripsi',
                'pd.Bobot',
                'bc.Nama_Kategori as Bsc_Kategori',
                'mf.Rumus',
                'ms.Simbol_Satuan',
                'mt.Nama_Target as Tipe_Target',
                'mt.Kode_Target',
            ]);

        // Target per periode (KPI_Pengajuan_Det) per parameter.
        $targets = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $params->pluck('Id_Pengajuan_Detail'))
            ->get(['Id_Pengajuan_Det', 'Id_Pengajuan_Detail', 'Nama_Target', 'Nilai_Target', 'Periode'])
            ->groupBy('Id_Pengajuan_Detail');

        $parameters = $params
            ->map(function ($p) use ($targets) {
                $rows = $targets->get($p->Id_Pengajuan_Detail, collect());

                return [
                    'nama_parameter' => $p->Nama_Parameter,
                    'deskripsi' => $p->Deskripsi,
                    'bobot' => (float) $p->Bobot,
                    'bsc_kategori' => $p->Bsc_Kategori,
                    'rumus' => $this->humanizeRumus($p->Rumus),
                    'satuan' => $p->Simbol_Satuan,
                    'tipe_target' => $p->Tipe_Target,
                    'kode_target' => $p->Kode_Target,
                    'targets' => $rows
                        ->map(
                            fn ($t) => [
                                // Alamat satu target: satu parameter, satu periode.
                                // Tanpa id ini layar punya angkanya tapi tidak punya
                                // cara menunjuk baris mana yang hendak dikoreksi.
                                'id_pengajuan_det' => (int) $t->Id_Pengajuan_Det,
                                'nama_target' => $t->Nama_Target,
                                'nilai_target' => $t->Nilai_Target !== null ? (float) $t->Nilai_Target : null,
                                'periode' => $t->Periode,
                            ],
                        )
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return ResponseHelper::success(
            [
                'header' => [
                    'no_transaksi' => $header->No_Transaksi,
                    'nama' => $header->Nama,
                    'kode_karyawan' => $header->Kode_Karyawan,
                    'divisi' => $header->Divisi,
                    'departemen' => $header->Departemen,
                    'level' => $header->Level,
                    'jabatan' => $header->Jabatan,
                    'tanggal' => $header->Tanggal,
                    'jam' => $header->Jam,
                    'approved' => ($header->Flag_Approval ?? null) === 'Y',
                    'total_bobot' => array_sum(array_column($parameters, 'bobot')),
                    // Non-null hanya bila transaksi ini dibatalkan -> FE menampilkan
                    // banner jejak pembatalan di atas detail.
                    'cancellation' => $this->cancelService->cancellationMetaFor($header, 'parameter'),
                ],
                'parameters' => $parameters,
            ],
            'Detail submission berhasil diambil',
        );
    }

    /**
     * Detail PERFORMANCE (parameter yang sudah diisi/di-update user) untuk satu
     * transaksi Update Performance. Menampilkan tiap parameter dengan nilai actual,
     * score, achievement, target, bobot, dan PICA bila ada.
     */
    public function getPerformanceDetail(Request $request)
    {
        $validated = $request->validate([
            'id_update_performance' => ['required', 'integer'],
        ]);

        $idUp = (int) $validated['id_update_performance'];

        $header = DB::table('KPI_Update_Performance as up')
            ->join('Karyawan as k', 'up.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'k.ID_Level_Jabatan',
                '=',
                'vg.ID_Level_Jabatan',
            )
            ->where('up.Id_Update_Performance', $idUp)
            ->first([
                'up.No_Transaksi',
                'up.Periode',
                'up.Tanggal',
                'up.Jam',
                'up.Total_Score',
                'up.Total_Score_Achievement',
                'up.Total_Bobot',
                'up.Total_Score_Raw',
                'up.Flag_Capped',
                'up.Cap_Min_Applied',
                'up.Cap_Max_Applied',
                'up.Status_Pengajuan',
                'up.Flag_Approval',
                'up.Status',
                'up.Id_Pengajuan',
                'up.Cancel_Reason',
                'up.Cancelled_By',
                'up.Cancelled_By_Nama',
                'up.Cancelled_At',
                'up.Cancel_Batch_Id',
                'k.Kode_Karyawan',
                'k.Nama',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
            ]);

        if (! $header) {
            return ResponseHelper::error('Data performance tidak ditemukan.', 404);
        }

        // Detail performance per parameter + atribut master + target periode.
        $details = DB::table('KPI_Update_Performance_Detail as upd')
            ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->where('upd.Id_Update_Performance', $idUp)
            ->get([
                'upd.Id_Update_Performance_Detail',
                'pd.Nama_Parameter',
                'pd.Deskripsi',
                'pd.Bobot',
                'bc.Nama_Kategori as Bsc_Kategori',
                'mf.Rumus',
                'ms.Simbol_Satuan',
                'pdet.Nilai_Target',
                'pdet.Periode as Periode_Target',
                'upd.Nilai_Actual',
                'upd.Score',
                'upd.Achievement',
                'upd.Flag_Pica',
                'upd.File_Berkas',
                'upd.Alasan_Reject',
            ]);

        // PICA per detail (bila ada).
        $picaByDetail = DB::table('KPI_Update_Performance_Det')
            ->whereIn('Id_Update_Performance_Detail', $details->pluck('Id_Update_Performance_Detail'))
            ->get(['Id_Update_Performance_Detail', 'Identifikasi', 'Koreksi_Aksi', 'Tanggal_Pelaksanaan'])
            ->groupBy('Id_Update_Performance_Detail');

        $parameters = $details
            ->map(function ($d) use ($picaByDetail) {
                $pica = $picaByDetail->get($d->Id_Update_Performance_Detail, collect());

                return [
                    'nama_parameter' => $d->Nama_Parameter,
                    'deskripsi' => $d->Deskripsi,
                    'bobot' => $d->Bobot !== null ? (float) $d->Bobot : null,
                    'bsc_kategori' => $d->Bsc_Kategori,
                    'rumus' => $this->humanizeRumus($d->Rumus),
                    'satuan' => $d->Simbol_Satuan,
                    'periode_target' => $d->Periode_Target,
                    'nilai_target' => $d->Nilai_Target !== null ? (float) $d->Nilai_Target : null,
                    'nilai_actual' => $d->Nilai_Actual !== null ? (float) $d->Nilai_Actual : null,
                    'score' => $d->Score !== null ? (float) $d->Score : null,
                    'achievement' => $d->Achievement !== null ? (float) $d->Achievement : null,
                    'has_pica' => ($d->Flag_Pica ?? 'T') === 'Y',
                    'file_berkas' => $d->File_Berkas,
                    'alasan_reject' => $d->Alasan_Reject,
                    'pica' => $pica
                        ->map(
                            fn ($p) => [
                                'identifikasi' => $p->Identifikasi,
                                'koreksi_aksi' => $p->Koreksi_Aksi,
                                'tanggal_pelaksanaan' => $p->Tanggal_Pelaksanaan,
                            ],
                        )
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return ResponseHelper::success(
            [
                'header' => [
                    'no_transaksi' => $header->No_Transaksi,
                    'nama' => $header->Nama,
                    'kode_karyawan' => $header->Kode_Karyawan,
                    'divisi' => $header->Divisi,
                    'departemen' => $header->Departemen,
                    'level' => $header->Level,
                    'jabatan' => $header->Jabatan,
                    'periode' => $header->Periode,
                    'tanggal' => $header->Tanggal,
                    'jam' => $header->Jam,
                    'total_score' => (float) $header->Total_Score,
                    'total_score_achievement' => (float) $header->Total_Score_Achievement,
                    'total_score_raw' => $header->Total_Score_Raw !== null ? (float) $header->Total_Score_Raw : null,
                    'total_bobot' => (float) $header->Total_Bobot,
                    'is_capped' => ($header->Flag_Capped ?? 'T') === 'Y',
                    'cap_min' => $header->Cap_Min_Applied !== null ? (float) $header->Cap_Min_Applied : null,
                    'cap_max' => $header->Cap_Max_Applied !== null ? (float) $header->Cap_Max_Applied : null,
                    'approved' => ($header->Flag_Approval ?? null) === 'Y',
                    'status_pengajuan' => $header->Status_Pengajuan,
                    'cancellation' => $this->cancelService->cancellationMetaFor($header, 'performance'),
                ],
                'parameters' => $parameters,
            ],
            'Detail performance berhasil diambil',
        );
    }

    /**
     * Rincian perhitungan YTD satu karyawan untuk mode YTD Performance Tracking.
     *
     * Baris YTD adalah agregat Jan..bulan terpilih, bukan satu transaksi, jadi ia
     * tidak punya id_update_performance dan detail bulanan tidak bisa dipakai.
     * Angkanya diambil dari KpiAkumulasiYtdService::rincianPerUser — sumber yang
     * sama dengan skor di tabel (terapkanSkorYtd), dashboard, dan export —
     * sehingga rincian ini PASTI menjumlah ke angka yang tampil di baris.
     *
     * "Bulan terhitung" memakai saringan yang sama dengan layanan (realisasi
     * hidup, disetujui, terverifikasi, pengajuan induk hidup & disetujui).
     */
    public function getPerformanceDetailYtd(Request $request)
    {
        $validated = $request->validate([
            'id_user' => ['required', 'integer'],
            'periode' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $idUser = (int) $validated['id_user'];
        $tahun = (int) substr($validated['periode'], 0, 4);
        $bulan = (int) substr($validated['periode'], 5, 2);

        $karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->where('k.UserID_Web', $idUser)
            ->first([
                'k.Nama',
                'k.Kode_Karyawan',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
            ]);

        if (! $karyawan) {
            return ResponseHelper::error('Karyawan tidak ditemukan.', 404);
        }

        $ytdSvc = app(KpiAkumulasiYtdService::class);
        $periodeYtd = $ytdSvc->periodeSampai($tahun, $bulan);
        $rincian = $ytdSvc->rincianPerUser([$idUser], $tahun, $periodeYtd)[(string) $idUser] ?? null;

        $bulanTerhitung = $rincian
            ? DB::table('KPI_Update_Performance as up')
                ->join('KPI_Pengajuan as p', function ($j) {
                    $j->on('p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')
                        ->where('p.Flag_Approval', 'Y')
                        ->whereNull('p.Status');
                })
                ->where('up.Id_User', $idUser)
                ->where('up.Flag_Aktif', 'Y')
                ->where('up.Flag_Approval', 'Y')
                ->where('up.Flag_Verifikasi', 'Y')
                ->whereNull('up.Status')
                ->whereIn('up.Periode', $periodeYtd)
                ->distinct()
                ->orderBy('up.Periode')
                ->pluck('up.Periode')
                ->values()
                ->all()
            : [];

        $skor = $rincian ? (float) $rincian['skor'] : null;
        $band = $skor === null ? null : DB::table('KPI_Master_Performance')
            ->where('Range_Awal', '<=', floor($skor))
            ->where('Range_Akhir', '>=', floor($skor))
            ->first(['Keterangan', 'Warna']);

        [$capParamMax, $capParamMin] = app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds();

        return ResponseHelper::success(
            [
                'header' => [
                    'nama' => $karyawan->Nama,
                    'kode_karyawan' => $karyawan->Kode_Karyawan,
                    'divisi' => $karyawan->Divisi,
                    'departemen' => $karyawan->Departemen,
                    'level' => $karyawan->Level,
                    'jabatan' => $karyawan->Jabatan,
                    'periode_label' => 'YTD '.Carbon::create($tahun, 1, 1)->translatedFormat('M').'–'
                        .Carbon::create($tahun, $bulan, 1)->translatedFormat('M Y'),
                    'ada_data' => $rincian !== null,
                    'skor' => $skor,
                    'keterangan' => $band->Keterangan ?? null,
                    'warna' => $band->Warna ?? null,
                    'sum_weighted' => $rincian ? (float) $rincian['sum_weighted'] : null,
                    'total_bobot' => $rincian ? (float) $rincian['total_bobot'] : null,
                    'bulan_terhitung' => $bulanTerhitung,
                    'cap_param_min' => $capParamMin,
                    'cap_param_max' => $capParamMax,
                ],
                'parameters' => collect($rincian['parameter'] ?? [])
                    ->map(fn ($p) => ['rumus' => \App\Helpers\KpiFormulaHelper::toDisplayText((string) ($p['rumus'] ?? ''))] + $p)
                    ->values()
                    ->all(),
            ],
            'Rincian YTD berhasil diambil',
        );
    }

    /**
     * Alur approval lengkap (semua step + status + catatan) untuk satu No_Transaksi.
     * Dual-engine: CENTRAL (N_HRIS_VW_Approval_Request_V2) atau LEGACY
     * (KPI_Approval_Request_Parameter / _Performance sesuai domain).
     *
     * @param  string  $domain  'parameter' | 'performance' (untuk pemilihan tabel legacy)
     */
    public function getApprovalFlow(Request $request)
    {
        $validated = $request->validate([
            'no_transaksi' => ['required', 'string'],
            'domain' => ['nullable', 'in:parameter,performance'],
        ]);

        $noTransaksi = $validated['no_transaksi'];
        $domain = $validated['domain'] ?? 'performance';

        $steps = $this->kpiApproval->isCentral($noTransaksi)
            ? $this->approvalFlowCentral($noTransaksi)
            : $this->approvalFlowLegacy($noTransaksi, $domain);

        return ResponseHelper::success(['steps' => $steps], 'Alur approval berhasil diambil');
    }

    /**
     * Semua step approval via engine terpusat (N_HRIS_VW_Approval_Request_V2).
     */
    private function approvalFlowCentral(string $noTransaksi): array
    {
        return DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->orderBy('Request_Order_Flow', 'asc')
            ->get([
                'Request_Order_Flow',
                'Current_Approver',
                'Current_Approver_Name',
                'Role_Name',
                'Flag_Approval',
                'Approved_By_Nama',
                'Tanggal_Approval',
                'Catatan',
                'Flow_State',
            ])
            ->map(
                fn ($s) => [
                    'order' => (int) $s->Request_Order_Flow,
                    'approver_nama' => $s->Current_Approver_Name ?: ($s->Role_Name ?: $s->Current_Approver),
                    'role_name' => $s->Role_Name,
                    // Normalisasi: Y disetujui, T ditolak, null menunggu.
                    'flag_approval' => $s->Flag_Approval,
                    'approved_by' => $s->Approved_By_Nama,
                    'tanggal_approval' => $s->Tanggal_Approval,
                    'catatan' => $s->Catatan,
                    'is_current' => $s->Flow_State === 'CURRENT',
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Semua step approval via engine LEGACY (sesuai domain parameter/performance).
     */
    private function approvalFlowLegacy(string $noTransaksi, string $domain): array
    {
        $requestTable = $domain === 'parameter' ? 'KPI_Approval_Request_Parameter' : 'KPI_Approval_Request_Performance';
        $flowTable = $domain === 'parameter' ? 'KPI_Approval_Flow_Parameter' : 'KPI_Approval_Flow_Performance';

        $rows = DB::table($requestTable.' as r')
            ->join($flowTable.' as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
            ->leftJoin('Karyawan as k', 'f.Kode_Karyawan_Approver', '=', 'k.Kode_Karyawan')
            ->where('r.No_Transaksi', $noTransaksi)
            ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
            ->get([
                'f.order_flow',
                'f.Kode_Karyawan_Approver',
                'k.Nama as Approver_Nama',
                'r.Flag_Approval',
                'r.update_at',
                'r.Alasan',
            ]);

        // Step berjalan = step pertama yang belum disetujui.
        $currentOrder = optional($rows->first(fn ($r) => ($r->Flag_Approval ?? null) !== 'Y'))->order_flow;

        return $rows
            ->map(
                fn ($r) => [
                    'order' => (int) $r->order_flow,
                    'approver_nama' => $r->Approver_Nama ?: $r->Kode_Karyawan_Approver,
                    'role_name' => null,
                    'flag_approval' => $r->Flag_Approval,
                    'approved_by' => ($r->Flag_Approval ?? null) === 'Y' ? ($r->Approver_Nama ?: $r->Kode_Karyawan_Approver) : null,
                    'tanggal_approval' => $r->update_at,
                    'catatan' => $r->Alasan,
                    'is_current' => $r->order_flow === $currentOrder,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * Ubah rumus mentah jadi lebih terbaca (mirror Update/Performance controller).
     */
    private function humanizeRumus(?string $rumus): ?string
    {
        if (! $rumus) {
            return null;
        }

        $rumus = preg_replace('/\[(.*?)\]/', '$1', $rumus);

        return str_replace(['targetKpiEvo', 'actualKpiEvo'], ['Target', 'Actual'], $rumus);
    }

    /**
     * Resolve approver yang sedang menangani approval Update Performance saat ini.
     * Mengcover DUA engine (sesuai cara penulisan di UpdatePerformanceController &
     * pembacaan di KpiVerifikasiController):
     *
     *   CENTRAL (default) — flow ditulis ke HRIS_Approval_Request via
     *     KpiApprovalService::createPerfFlow. Current approver dibaca dari
     *     N_HRIS_VW_Approval_Request_V2 (Flow_State='CURRENT').
     *
     *   LEGACY (KPI_PERF_ENGINE=LEGACY / transaksi lama) — flow di
     *
     *   CENTRAL (default) — flow ditulis ke HRIS_Approval_Request via
     *     KpiApprovalService::createPerfFlow. Current approver dibaca dari
     *     N_HRIS_VW_Approval_Request_V2 (Flow_State='CURRENT').
     *
     *   LEGACY (KPI_PERF_ENGINE=LEGACY / transaksi lama) — flow di
     *     KPI_Approval_Request_Performance JOIN KPI_Approval_Flow_Performance.
     *     (Domain performance => tabel _Performance, bukan _Parameter.)
     *
     * @return array{nama: string, kode: string, level: int, total: int, label: string}|null
     */
    private function deriveCurrentApprover(?string $noTransaksi): ?array
    {
        if (! $noTransaksi) {
            return null;
        }

        return $this->kpiApproval->isCentral($noTransaksi)
            ? $this->currentApproverCentral($noTransaksi)
            : $this->currentApproverLegacyPerformance($noTransaksi);
    }

    /**
     * Current approver via engine terpusat (N_HRIS_VW_Approval_Request_V2).
     */
    private function currentApproverCentral(string $noTransaksi): ?array
    {
        // Step yang sedang berjalan (CURRENT). Bisa lebih dari satu bila paralel/group.
        $current = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', 'CURRENT')
            ->orderBy('Request_Order_Flow', 'asc')
            ->first(['Current_Approver', 'Current_Approver_Name', 'Role_Name', 'Request_Order_Flow']);

        if (! $current) {
            return null;
        }

        // Total langkah flow transaksi ini (untuk teks "Level x/total").
        $totalSteps = (int) DB::table('N_HRIS_VW_Approval_Request_V2')->where('No_Transaksi', $noTransaksi)->count();

        $level = (int) $current->Request_Order_Flow;
        // Approver bisa berupa orang (nama) atau pool role (Role_Name) bila group.
        $nama = $current->Current_Approver_Name ?: ($current->Role_Name ?: ($current->Current_Approver ?: '-'));

        return [
            'nama' => $nama,
            'kode' => $current->Current_Approver,
            'level' => $level,
            'total' => $totalSteps,
            'label' => 'Menunggu '.$nama." (Level {$level}/{$totalSteps})",
        ];
    }

    /**
     * Current approver via engine LEGACY domain PERFORMANCE.
     */
    private function currentApproverLegacyPerformance(string $noTransaksi): ?array
    {
        $steps = DB::table('KPI_Approval_Request_Performance as r')
            ->join('KPI_Approval_Flow_Performance as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
            ->where('r.No_Transaksi', $noTransaksi)
            ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
            ->get(['f.order_flow', 'f.Kode_Karyawan_Approver', 'r.Flag_Approval as Step_Approval']);

        if ($steps->isEmpty()) {
            return null;
        }

        $totalSteps = $steps->count();
        $current = $steps->first(fn ($s) => ($s->Step_Approval ?? null) !== 'Y');

        // Semua step sudah approved -> tidak ada approver berjalan.
        if (! $current) {
            return null;
        }

        $level = (int) $current->order_flow;
        $namaApprover = DB::table('Karyawan')->where('Kode_Karyawan', $current->Kode_Karyawan_Approver)->value('Nama');

        return [
            'nama' => $namaApprover ?: $current->Kode_Karyawan_Approver,
            'kode' => $current->Kode_Karyawan_Approver,
            'level' => $level,
            'total' => $totalSteps,
            'label' => 'Menunggu '.($namaApprover ?: $current->Kode_Karyawan_Approver)." (Level {$level}/{$totalSteps})",
        ];
    }

    // =====================================================================
    // Pembatalan transaksi (single & bulk)
    // =====================================================================

    /**
     * Preview dampak pembatalan — WAJIB dipanggil sebelum eksekusi.
     *
     * Mengembalikan rekap per transaksi (identitas karyawan, status approval saat
     * ini, transaksi turunan yang ikut batal, item yang tak bisa dibatalkan) plus
     * fingerprint state yang harus dikirim balik saat eksekusi.
     */
    public function previewCancellation(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['required', 'in:parameter,performance,draft'],
            'ids' => ['required', 'array', 'min:1', 'max:'.KpiCancellationService::MAX_ITEMS],
            'ids.*' => ['required', 'integer'],
        ]);

        return ResponseHelper::success(
            $this->cancelService->buildPreview($validated['domain'], $validated['ids']),
            'Preview pembatalan berhasil dibuat',
        );
    }

    /**
     * Eksekusi pembatalan. All-or-nothing: bila ada satu saja transaksi yang
     * state-nya berubah sejak preview (mis. baru disetujui approver), SELURUH aksi
     * ditolak dengan 409 — tidak ada yang terbatalkan separuh jalan.
     */
    public function executeCancellation(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['required', 'in:parameter,performance,draft'],
            'ids' => ['required', 'array', 'min:1', 'max:'.KpiCancellationService::MAX_ITEMS],
            'ids.*' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:'.KpiCancellationService::MIN_REASON, 'max:500'],
            'fingerprints' => ['required', 'array'],
        ], [
            'ids.max' => 'Maksimal '.KpiCancellationService::MAX_ITEMS.' transaksi per pembatalan. Persempit pilihan Anda.',
            'reason.min' => 'Alasan pembatalan minimal '.KpiCancellationService::MIN_REASON.' karakter.',
        ]);

        $adminKode = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        $outcome = $this->cancelService->execute(
            $validated['domain'],
            $validated['ids'],
            trim(strip_tags($validated['reason'])),
            $validated['fingerprints'],
            $adminKode,
        );

        if (! $outcome['ok']) {
            return response()->json([
                'success' => false,
                'message' => 'Pembatalan dibatalkan: status sebagian transaksi sudah berubah. Muat ulang dampak lalu coba lagi.',
                'conflicts' => $outcome['conflicts'],
            ], 409);
        }

        return ResponseHelper::success($outcome['result'], 'Transaksi berhasil dibatalkan');
    }

    /**
     * Riwayat pembatalan (buku besar audit) — sub-tab Monitoring.
     */
    public function getCancellationHistory(Request $request)
    {
        $validated = $request->validate([
            'domain' => ['nullable', 'array'],
            'source' => ['nullable', 'array'],
            'batch_id' => ['nullable', 'string', 'max:50'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $validated['search'] = strip_tags($validated['search'] ?? '');

        $history = $this->cancelService->getHistory($validated);

        return ResponseHelper::successWithPagination(
            $history['rows'],
            $history['page'],
            $history['per_page'],
            $history['total'],
            'Riwayat pembatalan berhasil diambil',
        );
    }

    // =====================================================================
    // TAB 2 — Karyawan belum mengisi + filter
    // =====================================================================

    /**
     * Builder dasar "belum mengisi": punya pengajuan KPI aktif+approved utk tahun
     * periode, TAPI belum ada KPI_Update_Performance aktif utk periode tsb.
     */
    private function unfilledBaseQuery(Carbon $periode)
    {
        // "Belum mengisi" = punya PENGAJUAN PARAMETER approved (KPI_Pengajuan, yang
        // terhubung penjadwalan FORM/pengajuan) untuk tahun periode, TAPI belum ada
        // KPI_Update_Performance aktif untuk periode (bulan) tsb — lintas penjadwalan.
        //
        // Catatan: jangan syaratkan j.Flag_Penilaian_KPI='Y'. Pengajuan parameter
        // terhubung penjadwalan Form KPI (Flag_Form_KPI), bukan penilaian. Menyaring
        // ke penilaian membuat kandidat yang valid (mis. pengajuan di jadwal Form)
        // tidak muncul walau belum mengisi performance.
        //
        // Kepegawaian disaring PADA PERIODE (KpiKepegawaianPeriode), bukan dibiarkan
        // terbuka seperti sebelumnya. Diukur di produksi untuk Juli 2026: dari 121
        // kandidat, 16 di antaranya sudah resign SEBELUM Juli — dan karena query ini
        // juga dipakai previewEnforcement serta penjaga validId di enforceCap, satu
        // klik Auto Cap akan menulis realisasi KPI atas nama orang yang sudah keluar.
        return KpiKepegawaianPeriode::terapkan(
            DB::table('KPI_Pengajuan as p')
                ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
                ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vg.ID_Level_Jabatan',
                ),
            $periode->copy()->endOfMonth()->startOfDay(),
        )
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->where('j.Tahun', $periode->year)
            // Hanya yang PUNYA target EXACT-MONTH untuk periode ini (konsisten dgn
            // notif & classifyParameterTargetForPeriod). Yang cuma punya target Q/S/Y
            // belum jatuh tempo ("Tidak Ada Target") TIDAK bisa di-enforce —
            // enforceForEmployee menolaknya (tak ada parameter target periode ini).
            ->whereExists(function ($q) use ($periode) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan_Detail as pd')
                    ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                    ->whereColumn('pd.Id_Pengajuan', 'p.Id_Pengajuan')
                    ->where('pdet.Periode', 'like', $periode->format('Y-m').'%');
            })
            ->whereNotExists(function ($q) use ($periode) {
                $q->select(DB::raw(1))
                    ->from('KPI_Update_Performance as up')
                    ->whereColumn('up.Id_User', 'p.Id_User')
                    ->where('up.Flag_Aktif', 'Y')
                    // Record dibatalkan (Status='Y') tidak dianggap "sudah mengisi";
                    // draft (KPI_Update_Performance_Sementara) sengaja TIDAK dicek di sini
                    // -> tetap dihitung "belum mengisi" (boleh di-enforce).
                    ->whereNull('up.Status')
                    ->where('up.Periode', $periode->format('Y-m'));
            });
    }

    private function applyEmployeeFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('lokasi'), fn ($q) => $q->whereIn('k.Lokasi', (array) $request->input('lokasi')))
            ->when(
                $request->filled('divisi'),
                fn ($q) => $q->whereIn('vd.nama_divisi', (array) $request->input('divisi')),
            )
            ->when(
                $request->filled('departemen'),
                fn ($q) => $q->whereIn('vd.nama_sub_divisi', (array) $request->input('departemen')),
            )
            ->when($request->filled('level'), fn ($q) => $q->whereIn('vg.nama_level', (array) $request->input('level')))
            ->when(
                $request->filled('jabatan'),
                fn ($q) => $q->whereIn('vg.nama_jabatan', (array) $request->input('jabatan')),
            );
    }

    /**
     * Ambil baris "belum mengisi" untuk sekumpulan Id_User, dipecah agar tidak
     * menabrak batas 2.100 parameter SQL Server. Sejak "centang semua" bekerja
     * lintas halaman, daftar id yang masuk bisa jauh lebih panjang dari satu
     * halaman — dan kegagalannya menyamar jadi galat basis data biasa.
     *
     * @param  int[]  $ids
     * @param  string[]  $kolom
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function unfilledTerpilih(Carbon $periode, array $ids, array $kolom)
    {
        $hasil = collect();

        // SELECT DISTINCT di SQL Server menolak ORDER BY atas kolom yang tidak
        // ikut di-SELECT. enforceCap hanya meminta p.Id_User, jadi urut nama
        // dipasang hanya bila k.Nama memang diminta.
        $urutNama = in_array('k.Nama', $kolom, true);

        foreach (array_chunk(array_values(array_unique($ids)), 500) as $bagian) {
            $hasil = $hasil->merge(
                $this->unfilledBaseQuery($periode)
                    ->whereIn('p.Id_User', $bagian)
                    ->distinct()
                    ->when($urutNama, fn ($q) => $q->orderBy('k.Nama'))
                    ->get($kolom),
            );
        }

        return $hasil;
    }

    public function getUnfilledEmployees(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'], // induk = periode bulan (YYYY-MM)
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $periode = Carbon::parse($validated['periode']);
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 15;

        $base = $this->applyEmployeeFilters($this->unfilledBaseQuery($periode), $request);

        $total = (clone $base)->distinct()->count('p.Id_User');

        // "Centang semua" berarti SELURUH karyawan yang cocok scope + filter saat
        // ini, bukan yang kebetulan tampil di halaman aktif. Id-nya diambil dari
        // server dengan filter yang sama persis dengan tabelnya — pola yang sudah
        // dipakai monitoring (selectableIds), supaya kedua tab berperilaku sama.
        if ($request->boolean('ids_only')) {
            $semua = (clone $base)
                ->distinct()
                ->orderBy('k.Nama')
                ->get(['p.Id_User', 'k.Kode_Karyawan', 'k.Nama', 'vd.nama_divisi as Divisi']);

            return ResponseHelper::success(
                [
                    'items' => $semua
                        ->map(fn ($r) => [
                            'id' => (int) $r->Id_User,
                            'kode_karyawan' => $r->Kode_Karyawan,
                            'nama' => $r->Nama,
                            'divisi' => $r->Divisi,
                        ])
                        ->all(),
                    'total' => $semua->count(),
                    'max' => KpiEnforcementService::MAX_ENFORCE_ITEMS,
                ],
                'Id karyawan berhasil diambil',
            );
        }

        $rows = (clone $base)
            ->select(
                'p.Id_User',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Lokasi',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_level as Level',
                'vg.nama_jabatan as Jabatan',
            )
            ->distinct()
            ->orderBy('k.Nama')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $data = $rows->map(
            fn ($row) => [
                'id_user' => (int) $row->Id_User,
                'kode_karyawan' => $row->Kode_Karyawan,
                'nama' => $row->Nama,
                'lokasi' => $row->Lokasi,
                'divisi' => $row->Divisi,
                'departemen' => $row->Departemen,
                'level' => $row->Level,
                'jabatan' => $row->Jabatan,
            ],
        );

        return ResponseHelper::successWithPagination(
            $data,
            $page,
            $perPage,
            $total,
            'Karyawan belum mengisi berhasil diambil',
        );
    }

    public function getFilterOptions(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'],
        ]);

        $periode = Carbon::parse($validated['periode']);
        $base = $this->unfilledBaseQuery($periode);

        $pluckDistinct = fn ($column) => (clone $base)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->filter()
            ->values()
            ->all();

        return ResponseHelper::success(
            [
                'lokasi' => $pluckDistinct('k.Lokasi'),
                'divisi' => $pluckDistinct('vd.nama_divisi'),
                'departemen' => $pluckDistinct('vd.nama_sub_divisi'),
                'level' => $pluckDistinct('vg.nama_level'),
                'jabatan' => $pluckDistinct('vg.nama_jabatan'),
            ],
            'Opsi filter berhasil diambil',
        );
    }

    // =====================================================================
    // Enforcement — preview + execute + polling
    // =====================================================================
    /**
     * Preview enforcement: kembalikan LIST karyawan terpilih (yang valid "belum
     * mengisi") untuk ditampilkan di modal. Simulasi total score/achievement per
     * karyawan dimuat lazy via previewEmployeeDetail (saat accordion dibuka).
     */
    public function previewEnforcement(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'],
            'id_users' => ['required', 'array', 'min:1', 'max:'.KpiEnforcementService::MAX_ENFORCE_ITEMS],
            'id_users.*' => ['integer'],
        ], [
            'id_users.max' => 'Maksimal '.KpiEnforcementService::MAX_ENFORCE_ITEMS
                .' karyawan per sekali eksekusi. Persempit filter Anda.',
        ]);

        $periode = Carbon::parse($validated['periode']);
        $ids = array_values(array_unique($validated['id_users']));

        $employees = $this->unfilledTerpilih($periode, $ids, [
            'p.Id_User',
            'k.Kode_Karyawan',
            'k.Nama',
            'vd.nama_divisi as Divisi',
            'vd.nama_sub_divisi as Departemen',
            'vg.nama_jabatan as Jabatan',
        ])
            ->unique('Id_User')
            ->map(
                fn ($row) => [
                    'id_user' => (int) $row->Id_User,
                    'kode_karyawan' => $row->Kode_Karyawan,
                    'nama' => $row->Nama,
                    'divisi' => $row->Divisi,
                    'departemen' => $row->Departemen,
                    'jabatan' => $row->Jabatan,
                ],
            )
            ->values();

        $batasParameter = app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds();

        return ResponseHelper::success(
            [
                'affected_count' => $employees->count(),
                'periode' => $periode->toDateString(),
                'periode_label' => $periode->isoFormat('MMMM YYYY'),
                'employees' => $employees,
                'simulation' => [
                    'target_value' => 0,
                    'achievement' => 0,
                    'status_result' => 'Approved (bypass approval normal)',
                    // Batas dari sumber kanonik. Baku lokal sebelumnya 0/150 —
                    // menyimpang dari yang sebenarnya berlaku, dan halaman inilah
                    // yang paling awal menampilkan angka salah begitu config kosong.
                    'cap_min' => $batasParameter[1] ?? (float) config('services.kpi.min_achievement_score', 50),
                    'cap_max' => $batasParameter[0] ?? (float) config('services.kpi.max_achievement_score', 125),
                ],
            ],
            'Preview enforcement berhasil dibuat',
        );
    }

    /**
     * Simulasi enforcement SATU karyawan.
     *   with_details = false (default) -> HEADER saja (total score/achievement),
     *     ringan, dipakai prefetch stat list.
     *   with_details = true -> sertakan detail tiap parameter (semua 0), dipanggil
     *     saat accordion dibuka.
     * Bila sudah ter-enforce, kembalikan nilai real.
     */
    public function previewEmployeeDetail(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'],
            'id_user' => ['required', 'integer'],
            'with_details' => ['nullable', 'boolean'],
        ]);

        $periode = Carbon::parse($validated['periode']);
        $result = $this->service->simulateForEmployee(
            (int) $validated['id_user'],
            $periode->toDateString(),
            (bool) ($validated['with_details'] ?? false),
        );

        return ResponseHelper::success($result, 'Simulasi berhasil diambil');
    }

    public function enforceCap(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['required', 'string'], // induk = periode bulan
            'id_penjadwalan' => ['nullable', 'integer'], // override target (opsional)
            'id_users' => ['required', 'array', 'min:1', 'max:'.KpiEnforcementService::MAX_ENFORCE_ITEMS],
            'id_users.*' => ['integer'],
        ], [
            'id_users.max' => 'Maksimal '.KpiEnforcementService::MAX_ENFORCE_ITEMS
                .' karyawan per sekali eksekusi. Persempit filter Anda.',
        ]);

        $periode = Carbon::parse($validated['periode']);
        $override = $validated['id_penjadwalan'] ?? null; // null = AUTO per-user

        // Validasi ulang server-side: hanya proses yang benar2 "belum mengisi"
        // (union per-periode — sudah ada di unfilledBaseQuery).
        $validIds = $this->unfilledTerpilih($periode, $validated['id_users'], ['p.Id_User'])
            ->pluck('Id_User')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($validIds)) {
            return ResponseHelper::error('Tidak ada karyawan valid untuk dieksekusi (mungkin sudah terisi).', 422);
        }

        $batchId = $this->service->makeBatchId();
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        $this->service->startBatch($batchId, $periode->toDateString(), count($validIds), $requestedBy, [
            'periode' => $periode->toDateString(),
            'id_penjadwalan' => $override,
            'id_users' => $validIds,
        ]);

        // Job induk (DispatchEnforceKpiBatchJob) memecah dan mendispatch
        // EnforceKpiCapJob per karyawan di background. Controller hanya
        // mendispatch 1 job sehingga respons langsung 202 tanpa risiko timeout.
        DispatchEnforceKpiBatchJob::dispatch($batchId, $validIds, $periode->toDateString(), $override);

        Log::channel('UpdatePerformanceController')->info('KPI_ENFORCE_DISPATCH', [
            'batch_id' => $batchId,
            'total' => count($validIds),
            'periode' => $periode->toDateString(),
            'id_penjadwalan_override' => $override,
            'requested_by' => $requestedBy,
        ]);

        return ResponseHelper::success(
            [
                'batch_id' => $batchId,
                'total_records' => count($validIds),
            ],
            'Eksekusi auto-cap dijadwalkan',
            202,
        );
    }

    public function getBatchStatus(string $batchId)
    {
        $progress = $this->service->getBatchProgress($batchId);

        if (! $progress) {
            return ResponseHelper::error('Batch tidak ditemukan', 404);
        }

        return ResponseHelper::success($progress, 'Progress batch berhasil diambil');
    }

    // =====================================================================
    // SUB-TAB Export — universe + request (queue) + status + download
    // =====================================================================

    /**
     * Universe karyawan terjadwal untuk panel Export (sama pola monitoring):
     *   parameter   -> scope TAHUN, performance -> scope PERIODE bulan.
     * Disertai status & no_transaksi agar admin bisa memilih siapa yg di-export.
     */
    public function getExportUniverse(Request $request)
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:parameter,performance'],
            'tahun' => ['nullable', 'string'],
            'periode' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:bulan,ytd'],
        ]);

        // Map jenis export -> jenis penjadwalan monitoring.
        if ($validated['jenis'] === 'parameter') {
            $request->merge(['id_penjadwalan' => null]);
            $proxy = Request::create(
                '',
                'GET',
                [
                    'tahun' => $validated['tahun'] ?? '',
                    'per_page' => min((int) $request->integer('per_page', 15), 100),
                    'page' => max(1, $request->integer('page', 1)),
                ] + $request->only(['lokasi', 'divisi', 'departemen', 'level', 'jabatan', 'status', 'search', 'tgl_masuk']),
            );

            return $this->getSubmissionStatus($proxy);
        }

        // 'mode' diteruskan apa adanya supaya daftar yang dipilih admin di panel
        // Export sama persis dengan yang dilihatnya di tabel monitoring. Kalau
        // tidak, ia memilih dari YTD lalu mengekspor daftar bulanan.
        $proxy = Request::create(
            '',
            'GET',
            [
                'periode' => $validated['periode'] ?? '',
                'mode' => $validated['mode'] ?? 'bulan',
                'per_page' => min((int) $request->integer('per_page', 15), 100),
                'page' => max(1, $request->integer('page', 1)),
            ] + $request->only(['lokasi', 'divisi', 'departemen', 'level', 'jabatan', 'status', 'search', 'tgl_masuk']),
        );

        return $this->getPerformanceTracking($proxy);
    }

    /**
     * Buat job export (Excel/PDF) untuk karyawan terpilih -> dispatch ke queue.
     * Mengembalikan export_id untuk polling.
     */
    public function requestExport(Request $request)
    {
        $validated = $request->validate([
            'jenis' => ['required', 'in:parameter,performance'],
            'format' => ['required', 'in:excel,pdf'],
            'scope_value' => ['required', 'string'], // tahun (parameter) / periode YYYY-MM (performance)
            'select_all' => ['nullable', 'boolean'],
            'id_users' => ['required_without:select_all', 'array'],
            'id_users.*' => ['integer'],
            'filters' => ['nullable', 'array'],
            'expected_total' => ['nullable', 'integer', 'min:0'], // total terfilter dari tabel universe (mode "semua")
            'nama' => ['nullable', 'string', 'max:120'], // label opsional dari user
            // Mengikuti sakelar di layar. Tanpa ini admin yang sedang melihat YTD
            // menerima berkas berisi SATU bulan tanpa satu pun tanda bahwa isinya
            // berbeda dari yang barusan dilihatnya.
            'mode' => ['nullable', 'in:bulan,ytd'],
        ]);

        $selectAll = (bool) ($validated['select_all'] ?? false);

        // Label opsional yang ditampilkan di panel aktivitas (mudah dikenali user).
        $nama = trim(strip_tags((string) ($validated['nama'] ?? '')));

        // Filter monitoring (divisi/dept/level/jabatan/lokasi) hanya relevan untuk
        // mode "semua" — worker yang memakainya saat resolve himpunan karyawan.
        $filters = $selectAll ? $this->sanitizeExportFilters($request->input('filters', [])) : [];
        $ids = $selectAll ? [] : array_values(array_unique(array_map('intval', $validated['id_users'] ?? [])));

        if (! $selectAll && empty($ids)) {
            return ResponseHelper::error('Tidak ada karyawan yang dipilih untuk export.', 422);
        }

        // Penjadwalan dalam scope (sama dgn monitoring): parameter pakai Flag_Form_KPI,
        // performance pakai Flag_Penilaian_KPI. Filter export memakai Id_Penjadwalan
        // ini — BUKAN kolom Periode/Tahun mentah — agar himpunan persis sama dgn
        // monitoring (mencegah record "nyasar" yg periode-nya tak sinkron jadwal).
        // schedule_meta juga membawa has_global + perorangan_ids agar worker bisa
        // membangun ulang UNIVERSE karyawan terjadwal (termasuk yg belum mengisi).
        // Pada YTD scope-nya melebar ke Jan..N, persis seperti monitoring: orang
        // yang dinilai Februari tetapi tidak Agustus tetap harus ada di laporan
        // YTD Agustus, karena nilainya memang ikut terakumulasi.
        $modeYtd = $validated['jenis'] === 'performance' && ($validated['mode'] ?? 'bulan') === 'ytd';
        $periodeYtd = [];
        if ($modeYtd) {
            $periodeYtd = app(KpiAkumulasiYtdService::class)->periodeSampai(
                (int) substr($validated['scope_value'], 0, 4),
                (int) substr($validated['scope_value'], 5, 2),
            );
        }

        $scheduleMeta = $this->exportScheduleMeta(
            $validated['jenis'],
            $modeYtd ? $periodeYtd : $validated['scope_value'],
        );
        $scheduleIds = $scheduleMeta['schedule_ids'];

        // Total record: mode manual = jumlah id; mode "semua" = pakai total terfilter
        // yang sudah diketahui tabel universe (sudah termasuk filter STATUS yang tak
        // bisa dihitung SQL). Tanpa ini label panel "59 -> 5" saat selesai. Fallback ke
        // COUNT universe (tanpa status) bila client tak mengirim expected_total.
        $expectedTotal = $request->integer('expected_total', -1);
        $total = $selectAll
            ? ($expectedTotal >= 0 ? $expectedTotal : $this->countExportUniverse($scheduleMeta, $filters))
            : count($ids);

        $payload = [
            'jenis' => $validated['jenis'],
            'scope_value' => $validated['scope_value'],
            'schedule_ids' => $scheduleIds,
            'schedule_meta' => $scheduleMeta,
        ];
        if ($modeYtd) {
            $payload['mode'] = 'ytd';
            $payload['periods'] = $periodeYtd;
        }
        if ($nama !== '') {
            $payload['nama'] = $nama;
        }
        if ($selectAll) {
            $payload['select_all'] = true;
            $payload['filters'] = $filters;
        } else {
            $payload['id_users'] = $ids;
        }

        $exportId = $this->exportService->makeExportId();
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        $this->exportService->startJob($exportId, $validated['format'], $payload, $total, $requestedBy);

        GenerateKpiExportJob::dispatch($exportId);

        Log::channel('UpdatePerformanceController')->info('KPI_EXPORT_REQUEST', [
            'export_id' => $exportId,
            'jenis' => $validated['jenis'],
            'format' => $validated['format'],
            'select_all' => $selectAll,
            'total' => $total,
            'requested_by' => $requestedBy,
        ]);

        return ResponseHelper::success(
            [
                'export_id' => $exportId,
                'total_records' => $total,
            ],
            'Export dijadwalkan',
            202,
        );
    }

    /**
     * Normalisasi filter export ke whitelist key + nilai array string bersih.
     */
    private function sanitizeExportFilters($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $keys = ['lokasi', 'divisi', 'departemen', 'level', 'jabatan'];
        $out = [];
        foreach ($keys as $k) {
            $vals = array_values(array_filter(
                array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) ($raw[$k] ?? [])),
                fn ($v) => $v !== '',
            ));
            if (! empty($vals)) {
                $out[$k] = $vals;
            }
        }

        // Filter STATUS (nilai = key status derived, mis. 'approved','belum_submit',
        // 'tanpa_target'). Status dihitung di worker (bukan kolom DB) -> di-whitelist
        // di sini & diterapkan KpiExportService saat memfilter baris hasil build.
        $allowedStatus = [
            'approved', 'rejected', 'waiting', 'draft',
            'belum_submit', 'tidak_submit', 'tanpa_parameter', 'tanpa_target',
        ];
        $status = array_values(array_intersect(
            array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) ($raw['status'] ?? [])),
            $allowedStatus,
        ));
        if (! empty($status)) {
            $out['status'] = $status;
        }

        // Pencarian nama / kode karyawan (string tunggal).
        $search = strip_tags(trim((string) ($raw['search'] ?? '')));
        if ($search !== '') {
            $out['search'] = $search;
        }

        // Rentang Tgl Masuk [dari, sampai]; yang tak terurai dibuang diam-diam.
        $tglMasuk = KpiFilterTglMasuk::rentang($raw['tgl_masuk'] ?? null);
        if ($tglMasuk !== null) {
            $out['tgl_masuk'] = $tglMasuk;
        }

        return $out;
    }

    /**
     * Meta penjadwalan dalam scope export, identik dengan universe monitoring:
     *   - parameter   : Flag_Form_KPI='Y' + Tahun = scope
     *   - performance : Flag_Penilaian_KPI='Y' + Periode = scope
     *
     * Mengembalikan schedule_ids + has_global (ada jadwal global) + perorangan_ids,
     * cukup untuk membangun ulang UNIVERSE karyawan terjadwal di worker.
     *
     * @return array{schedule_ids:int[], has_global:bool, perorangan_ids:int[]}
     */
    private function exportScheduleMeta(string $jenis, string|array $scope): array
    {
        $jadwalJenis = $jenis === 'performance' ? 'penilaian' : 'submission';
        $schedules = $this->schedulesInScope($jadwalJenis, $scope);

        return [
            'schedule_ids' => $schedules->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            'has_global' => $schedules->contains(fn ($s) => ($s->Flag_Perorangan ?? 'T') !== 'Y'),
            // Batas kepegawaian ikut dititipkan ke meta supaya berkas export memakai
            // universe yang sama persis dengan layar. Job export berjalan belakangan
            // dan bisa saja mengeksekusi payload yang dibuat kemarin — menurunkan
            // ulang batas ini di sisi job berarti dua tempat yang boleh berselisih.
            'batas_kepegawaian' => KpiKepegawaianPeriode::batasDariScope($scope)->toDateString(),
            'perorangan_ids' => $schedules
                ->filter(fn ($s) => ($s->Flag_Perorangan ?? 'T') === 'Y')
                ->pluck('Id_Master_Penjadwalan')->map(fn ($v) => (int) $v)->all(),
            // Penanda lama (boolean se-scope). Dipertahankan hanya sebagai cadangan
            // untuk job yang sudah terlanjur antre sebelum rilis ini.
            'expired' => $this->isScopeExpired($schedules),
            // Penanda sebenarnya: label dihitung per orang, sama seperti di layar.
            ...KpiJadwalBerjalan::dariJadwal($schedules)->untukMeta(),
        ];
    }

    /**
     * Hitung jumlah karyawan yang akan di-export untuk mode "semua" = SELURUH
     * karyawan terjadwal dalam scope (yang mengisi MAUPUN belum), persis universe
     * monitoring. Bukan hanya yang punya transaksi.
     *
     * @param  array{schedule_ids:int[], has_global:bool, perorangan_ids:int[]}  $meta
     */
    private function countExportUniverse(array $meta, array $filters): int
    {
        if (empty($meta['schedule_ids'])) {
            return 0;
        }

        $query = $this->scheduledUniverseQuery(
            $meta['has_global'],
            $meta['perorangan_ids'],
            KpiKepegawaianPeriode::batasDariScope($meta['batas_kepegawaian'] ?? ''),
        );

        $whereInIf = function ($q, string $col, $values) {
            $values = array_values(array_filter((array) $values, fn ($v) => $v !== null && $v !== ''));

            return ! empty($values) ? $q->whereIn($col, $values) : $q;
        };
        $query = $whereInIf($query, 'k.Lokasi', $filters['lokasi'] ?? []);
        $query = $whereInIf($query, 'vd.nama_divisi', $filters['divisi'] ?? []);
        $query = $whereInIf($query, 'vd.nama_sub_divisi', $filters['departemen'] ?? []);
        $query = $whereInIf($query, 'vg.nama_level', $filters['level'] ?? []);
        $query = $whereInIf($query, 'vg.nama_jabatan', $filters['jabatan'] ?? []);
        $query = KpiFilterTglMasuk::terapkan($query, KpiFilterTglMasuk::rentang($filters['tgl_masuk'] ?? null));

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($w) => $w->where('k.Nama', 'like', $like)->orWhere('k.Kode_Karyawan', 'like', $like));
        }

        return (int) $query->distinct()->count('k.Kode_Karyawan');
    }

    /**
     * Builder UNIVERSE karyawan terjadwal (tanpa join transaksi). Global -> semua
     * karyawan aktif PADA PERIODE; perorangan -> hanya yang terdaftar. Sama logika
     * dengan scheduledEmployeesInScope namun menerima meta yang sudah diturunkan.
     *
     * @param  int[]  $peroranganIds
     */
    private function scheduledUniverseQuery(
        bool $hasGlobal,
        array $peroranganIds,
        Carbon $batasKepegawaian,
    ): \Illuminate\Database\Query\Builder {
        $q = KpiKepegawaianPeriode::terapkan(
            DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan'),
            $batasKepegawaian,
        );

        if ($hasGlobal) {
            return $q;
        }

        return $q->whereIn('k.Kode_Karyawan', function ($sub) use ($peroranganIds) {
            $sub->from('N_KPI_Master_Penjadwalan_Perorangan')
                ->whereIn('Id_Master_Penjadwalan', $peroranganIds)
                ->select('Kode_Karyawan');
        });
    }

    public function getExportStatus(string $exportId)
    {
        $progress = $this->exportService->getProgress($exportId);

        if (! $progress) {
            return ResponseHelper::error('Export tidak ditemukan', 404);
        }

        return ResponseHelper::success($progress, 'Status export berhasil diambil');
    }

    /**
     * Export TERAKHIR milik admin (yang masih relevan) untuk resume panel saat
     * kembali ke halaman — tidak bergantung localStorage. Mengabaikan yang sudah
     * di-dismiss (Deleted_At terisi) agar tidak muncul lagi.
     */
    public function getLatestExport()
    {
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        $latest = $this->exportService->getLatest($requestedBy);

        return ResponseHelper::success(
            $latest,
            $latest ? 'Export terakhir berhasil diambil' : 'Tidak ada export aktif',
        );
    }

    /**
     * Daftar beberapa export terakhir milik admin (panel list) dengan label jelas
     * agar admin tahu file mana yang mana saat export beberapa kali.
     */
    public function getExportList()
    {
        $requestedBy = optional(optional(Auth::user())->karyawan)->Kode_Karyawan;

        return ResponseHelper::success(
            $this->exportService->getList($requestedBy, 10),
            'Daftar export berhasil diambil',
        );
    }

    /**
     * Coba lagi export yang gagal — HAPUS job gagal lalu buat & dispatch job BARU
     * (export_id baru) dengan payload yang sama. Tidak memakai retry Cloud Tasks.
     */
    public function retryExport(string $exportId)
    {
        $newId = $this->exportService->retryAsNew($exportId);
        if (! $newId) {
            return ResponseHelper::error('Export tidak ditemukan atau sudah selesai.', 422);
        }

        GenerateKpiExportJob::dispatch($newId);

        return ResponseHelper::success(['export_id' => $newId], 'Export dijadwalkan ulang', 202);
    }

    /**
     * Download file export yang sudah ready. Saat pertama diunduh, dispatch
     * penghapusan file ~1 jam kemudian (hemat storage).
     */
    public function downloadExport(string $exportId)
    {
        $job = $this->exportService->markDownloaded($exportId);

        if (! $job || $job->Status !== KpiExportService::STATUS_READY || ! $job->File_Path) {
            return ResponseHelper::error('File export belum siap atau sudah dihapus.', 404);
        }

        if (! Storage::disk(KpiExportService::DISK)->exists($job->File_Path)) {
            return ResponseHelper::error('File export sudah tidak tersedia.', 410);
        }

        return Storage::disk(KpiExportService::DISK)->download($job->File_Path, $job->File_Name);
    }

    /**
     * Dismiss export dari panel: tandai Deleted_At agar tidak muncul lagi via
     * getLatestExport. Tidak menghapus record (audit) — hanya menyembunyikan.
     */
    public function dismissExport(string $exportId)
    {
        $this->exportService->dismiss($exportId);

        return ResponseHelper::success(null, 'Export disembunyikan dari panel');
    }
}
