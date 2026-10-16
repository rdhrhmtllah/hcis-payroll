<?php

namespace App\Http\Controllers\KPI\Performance\Dashboard\Executive;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\KpiScheduleHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\KPI\Performance\Dashboard\Concerns\DetailPerPeriode;
use App\Services\KPI\KpiAkumulasiYtdService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class DashboardCeoController extends Controller
{
    /** Band performance dibaca sekali per permintaan; lihat bandPerformance(). */
    private $bandCache = null;

    use DetailPerPeriode;

    /**
     * Ganti periode di modal detail dashboard Tim.
     *
     * Karyawannya diturunkan dari transaksi yang SEDANG dibuka di modal
     * (`ref_update`), sama seperti endpoint detail yang sudah ada: siapa pun yang
     * boleh membuka detail orang itu dari daftar boleh melihat periode lainnya.
     */
    public function detailPerPeriode(Request $request)
    {
        $validated = $this->validasiPeriodeDetail($request);
        $request->validate(['ref_update' => ['required', 'string']]);

        $idUpdate = Hashids::connection('custom')->decode(trim($request->input('ref_update')))[0] ?? null;
        $idUser = $idUpdate
            ? DB::table('KPI_Update_Performance')->where('Id_Update_Performance', $idUpdate)->value('Id_User')
            : null;

        if (! $idUser) {
            return ResponseHelper::error('Data performance KPI referensi tidak ditemukan.', 404);
        }

        return $this->susunDetailPerPeriode((int) $idUser, (int) $validated['tahun'], (string) $validated['periode']);
    }

    /**
     * Display a listing of the resource.
     */
    /**
     * Satu karyawan, satu baris per periode -- yang terbaru.
     *
     * Seluruh angka dashboard ini (widget, daftar, grafik batang, heatmap,
     * peringkat) diturunkan dari satu kueri dasar, jadi baris kembar merusak
     * semuanya sekaligus: karyawan yang sama muncul dua batang, dan widget
     * berselisih dengan dirinya sendiri karena "Total Karyawan" menghitung ORANG
     * sementara "Mencapai/Di Bawah Target" menghitung BARIS.
     *
     * Kembarnya bukan sisa revisi: keduanya Flag_Aktif='Y', Status NULL,
     * disetujui, dan terverifikasi, jadi tidak ada satu pun saringan yang
     * membedakannya. Diverifikasi di Web_HRIS (19 Sep 2026): 12 pasangan
     * (karyawan, periode) punya lebih dari satu baris hidup -- mis. Aji (A4)
     * periode 2026-05 dengan skor 95,25 DAN 102.
     *
     * Penjaga ini memilih Id_Update_Performance terbesar, yaitu kiriman
     * terakhir. Ia TIDAK memperbaiki datanya; ia hanya menolak menghitung satu
     * orang dua kali.
     */
    protected function satuUpdatePerPeriode($query): void
    {
        $query->whereRaw(
            'KPI_Update_Performance.Id_Update_Performance = ('
            .' SELECT MAX(kembar.Id_Update_Performance) FROM KPI_Update_Performance AS kembar'
            .' WHERE kembar.Id_User = KPI_Update_Performance.Id_User'
            .' AND kembar.Periode = KPI_Update_Performance.Periode'
            .' AND kembar.Flag_Aktif = ? AND kembar.Status IS NULL'
            .' AND kembar.Status_Pengajuan = ? AND kembar.Flag_Approval = ? AND kembar.Flag_Verifikasi = ?)',
            ['Y', 'disetujui', 'Y', 'Y'],
        );
    }

    /**
     * Band performance dari SKOR, bukan dari pointer yang tersimpan.
     *
     * KPI_Update_Performance.Id_Performance menunjuk KPI_Master_Performance,
     * dan pointer itu bisa basi: di Web_HRIS (19 Sep 2026) 535 dari 552 update
     * tahun 2026 menunjuk id yang sudah tidak ada di master (master hanya 9-12).
     * Karena jalur bulanan dulu meng-INNER JOIN master, setiap orang berpointer
     * basi hilang dari daftar, peringkat, dan grafik -- tetapi tetap terhitung
     * di widget, yang tidak ikut join. Itulah sebabnya "Total Karyawan" bisa
     * menyebut dua orang sementara daftarnya hanya berisi satu.
     *
     * Rentangnya sendiri yang menjadi sumber: band = baris master yang memuat
     * skornya. Jalur tahunan sudah lama memakai cara ini; kini keduanya sama.
     *
     * @return array<string, object> band per kode, plus perhitungan batas NP
     */
    protected function bandPerformance()
    {
        if ($this->bandCache === null) {
            $this->bandCache = DB::table('KPI_Master_Performance')
                ->orderBy('Range_Awal')
                ->get(['Id_Master_Performance', 'Kode', 'Keterangan', 'Range_Awal', 'Range_Akhir', 'Warna']);
        }

        return $this->bandCache;
    }

    /** Band yang memuat satu skor; band terakhir dipakai bila skor melampaui semua rentang. */
    protected function bandDariSkor($skor)
    {
        $band = $this->bandPerformance();
        $nilai = (float) ($skor ?? 0);

        foreach ($band as $b) {
            if ($nilai >= (float) $b->Range_Awal && $nilai <= (float) $b->Range_Akhir) {
                return $b;
            }
        }

        return $band->last();
    }

    public function index()
    {
        return inertia('KPI/Performance/Dashboard/ceo/dashboardCeo');
    }

    public function getDataListDivisi()
    {
        $pengguna = Auth::user();
        $leaderId = $pengguna->karyawan->Kode_Karyawan;

        try {
            $team = DB::table('KPI_Karyawan_Team as kt')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
                ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->where('Kode_Karyawan_Leader', $leaderId);

            $teamMemberIds = $team->pluck('Kode_Karyawan_Team');

            $getData = DB::table('KPI_User_Akses_Divisi')
                ->select('KPI_User_Akses_Divisi.Id_Divisi', 'View_Divisi_Sub_Divisi.nama_divisi as Nama_Divisi')
                ->groupBy('KPI_User_Akses_Divisi.Id_Divisi', 'View_Divisi_Sub_Divisi.nama_divisi')
                ->join(
                    'View_Divisi_Sub_Divisi',
                    'KPI_User_Akses_Divisi.Id_Divisi',
                    '=',
                    'View_Divisi_Sub_Divisi.ID_Divisi',
                )
                ->where('KPI_User_Akses_Divisi.Id_User', $pengguna->Id_Users)
                ->get();

            if ($getData->isEmpty() && $teamMemberIds->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            if ($teamMemberIds->isNotEmpty()) {
                $getData = $team
                    ->select('nama_divisi as Nama_Divisi', 'ID_Divisi as Id_Divisi')
                    ->groupBy('nama_divisi', 'Id_Divisi')
                    ->get();
            }

            // dd($teamMemberGrouped);

            $encodedData = $getData->map(function ($item) {
                $item->Id_Divisi = Hashids::connection('custom')->encode($item->Id_Divisi);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListDivisiAndDepartemenOption: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataListDepartemen($divisionIds)
    {
        $pengguna = Auth::user();
        $leaderId = $pengguna->karyawan->Kode_Karyawan;

        try {
            $team = DB::table('KPI_Karyawan_Team as kt')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
                ->join('View_Divisi_Sub_Divisi as b', 'b.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->where('Kode_Karyawan_Leader', $leaderId);
            $teamMemberIds = $team->pluck('Kode_Karyawan_Team');

            if ($teamMemberIds->isNotEmpty()) {
                $query = $team->select('ID_Sub_Divisi', 'nama_sub_divisi');
            } else {
                $query = DB::table('KPI_User_Akses_Divisi as a')
                    ->join('View_Divisi_Sub_Divisi as b', 'a.Id_Divisi', '=', 'b.ID_Divisi')
                    ->select('b.ID_Sub_Divisi', 'b.nama_sub_divisi')
                    ->where('a.Id_User', $pengguna->Id_Users);
            }
            // dd($query->get());

            if ($divisionIds !== 'ALL') {
                $decodedIds = [];
                try {
                    $hashedIds = explode(',', $divisionIds);
                    foreach ($hashedIds as $hashedId) {
                        $decoded = Hashids::connection('custom')->decode($hashedId);
                        if (! empty($decoded)) {
                            $decodedIds[] = $decoded[0];
                        }
                    }

                    if (empty($decodedIds)) {
                        return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
                    }
                } catch (\Exception $e) {
                    return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
                }
                $query->whereIn('b.ID_Divisi', $decodedIds);
            }

            $getData = $query->distinct()->get();
            $encodedData = $getData->map(function ($item) {
                $item->ID_Sub_Divisi = Hashids::connection('custom')->encode($item->ID_Sub_Divisi);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListDepartemen: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataListLevelOption()
    {
        $pengguna = Auth::user();
        $leaderId = $pengguna->karyawan->Kode_Karyawan;

        try {
            $team = DB::table('KPI_Karyawan_Team as kt')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'vg.ID_Level_Jabatan',
                    '=',
                    'k.ID_Level_Jabatan',
                )
                ->where('Kode_Karyawan_Leader', $leaderId);

            $teamMemberIds = $team->pluck('Kode_Karyawan_Team');

            $getData = DB::table('KPI_User_Akses_Level')
                ->select('KPI_User_Akses_Level.Id_Level', 'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan',
                    'KPI_User_Akses_Level.Id_Level',
                    '=',
                    'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level',
                )
                ->join('HRIS_Level', 'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level', '=', 'HRIS_Level.ID_Level')
                ->where('KPI_User_Akses_Level.Id_User', $pengguna->Id_Users)
                ->groupBy(
                    'KPI_User_Akses_Level.Id_Level',
                    'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                    'HRIS_Level.Level_Hierarchy',
                )
                ->orderBy('HRIS_Level.Level_Hierarchy', 'desc')
                ->get();

            if ($getData->isEmpty() && $teamMemberIds->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            if ($teamMemberIds->isNotEmpty()) {
                $getData = $team
                    ->select('nama_level', 'ID_Level as Id_Level')
                    ->groupBy('nama_level', 'ID_Level')
                    ->get();
            }

            $encodedData = $getData->map(function ($item) {
                $item->Id_Level = Hashids::connection('custom')->encode($item->Id_Level);

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error('getDataListLevelOption: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataInformasi(Request $request)
    {
        return ResponseHelper::success(
            null,
            'Hi Dev EVO PET, Hubungi IT agar mengetahui route yang terbaru, Terimakasih',
        );

        $pengguna = Auth::user();

        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 5);
            $search = $request->input('q', null);

            $divisiIdsInput = $request->input('divisi');
            $departemenIdsInput = $request->input('departemen');
            $levelIdsInput = $request->input('level');
            $bulan = $request->input('bulan');
            $tahun = $request->input('tahun');
            $levelIdsTopInput = $request->input('levelTop');
            $levelIdsUnderInput = $request->input('levelUnder');
            $sortStatus = $request->input('sort_status');
            $heatmapPage = max(1, (int) $request->input('heatmap_page', 1));
            $heatmapLimit = max(1, (int) $request->input('heatmap_limit', 20));
            $topLimit = min(50, max(1, (int) $request->input('top_limit', 5)));
            $leaderId = $pengguna->karyawan->Kode_Karyawan;

            $userAccessDivisi = DB::table('KPI_User_Akses_Divisi')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Divisi');

            $userAccessLevel = DB::table('KPI_User_Akses_Level')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Level');

            $teamMemberIds = DB::table('KPI_Karyawan_Team')
                ->where('Kode_Karyawan_Leader', $leaderId)
                ->pluck('Kode_Karyawan_Team');

            // dd($teamMemberIds->isEmpty());

            if ($teamMemberIds->isEmpty() && $userAccessDivisi->isEmpty() && $userAccessLevel->isEmpty()) {
                return ResponseHelper::success(
                    $this->getEmptyResultStructure(),
                    'Data Tidak Ditemukan (Tidak Ada Akses)',
                    200,
                );
            }

            $decodeHashids = fn ($s) => $s
                ? collect(explode(',', $s))
                    ->map(fn ($h) => Hashids::connection('custom')->decode(trim($h))[0] ?? null)
                    ->filter()
                : collect();

            $divisiIds = $divisiIdsInput && $divisiIdsInput !== 'ALL' ? $decodeHashids($divisiIdsInput) : collect();
            $departemenIds =
                $departemenIdsInput && $departemenIdsInput !== 'ALL' ? $decodeHashids($departemenIdsInput) : collect();
            $levelIds = $levelIdsInput && $levelIdsInput !== 'ALL' ? $decodeHashids($levelIdsInput) : collect();
            $levelIdsTop =
                $levelIdsTopInput && $levelIdsTopInput !== 'ALL' ? $decodeHashids($levelIdsTopInput) : collect();
            $levelIdsUnder =
                $levelIdsUnderInput && $levelIdsUnderInput !== 'ALL' ? $decodeHashids($levelIdsUnderInput) : collect();

            $baseQuery = DB::table('KPI_Update_Performance')
                ->join('Karyawan as k', 'KPI_Update_Performance.Id_User', '=', 'k.UserID_Web')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vgsglj.ID_Level_Jabatan',
                )
                ->whereNull('KPI_Update_Performance.Status')
                ->where('KPI_Update_Performance.Status_Pengajuan', 'disetujui')
                ->where('KPI_Update_Performance.Flag_Approval', 'Y')
                ->where('k.Aktif', 'Y')
                // Tanpa Flag_Aktif, header lama yang sudah digantikan revisi ikut terhitung
                // (penggantian hanya menyetel Flag_Aktif='T', Status tetap NULL).
                ->where('KPI_Update_Performance.Flag_Aktif', 'Y')
                ->where('KPI_Update_Performance.Flag_Verifikasi', 'Y');

            $this->satuUpdatePerPeriode($baseQuery);
            // dd($baseQuery->get());

            if ($teamMemberIds->isNotEmpty()) {
                $baseQuery->whereIn('k.Kode_Karyawan', $teamMemberIds);
                // dd($baseQuery->get());
            } else {
                $baseQuery->whereIn('vdsd.Id_Divisi', $userAccessDivisi)->whereIn('vgsglj.ID_Level', $userAccessLevel);
            }

            // dd($baseQuery->get());
            if ($divisiIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.Id_Divisi', $divisiIds);
            }
            if ($departemenIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.ID_Sub_Divisi', $departemenIds);
            }
            if ($levelIds->isNotEmpty()) {
                $baseQuery->whereIn('vgsglj.ID_Level', $levelIds);
            }

            if ($tahun) {
                // SARGABLE: LEFT(Periode,4)=year → prefix 'year-%' (Periode 'YYYY-MM').
                $baseQuery->where('KPI_Update_Performance.Periode', 'like', $tahun.'-%');
            }

            $formatScore = fn ($score) => fmod(round($score, 1), 1) === 0.0 ? (int) round($score, 1) : round($score, 1);
            $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

            $countEmployee = 0;
            $onTarget = 0;
            $underTarget = 0;
            $resultRecent = [];
            $topPerformers = collect();
            $underPerformers = collect();
            $chartBar = [];
            $chartHeatmap = [];

            if ($bulan && $bulan !== 'ALL') {
                $baseQuery->whereRaw('RIGHT(KPI_Update_Performance.Periode, 2) = ?', [$bulan]);

                $widgetCounts = (clone $baseQuery)
                    ->selectRaw('COUNT(DISTINCT KPI_Update_Performance.Id_User) as countEmployee')
                    // DISTINCT Id_User: satuannya orang, sama dengan countEmployee di atas.
                    ->selectRaw('COUNT(DISTINCT CASE WHEN KPI_Update_Performance.Total_Score >= 100 THEN KPI_Update_Performance.Id_User END) as onTarget')
                    ->selectRaw('COUNT(DISTINCT CASE WHEN KPI_Update_Performance.Total_Score < 100 THEN KPI_Update_Performance.Id_User END) as underTarget')
                    ->first();

                $countEmployee = $widgetCounts->countEmployee ?? 0;
                $onTarget = $widgetCounts->onTarget ?? 0;
                $underTarget = $widgetCounts->underTarget ?? 0;

                $allDataQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'KPI_Update_Performance.Id_Update_Performance',
                        'k.Nama as name',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'KPI_Update_Performance.Id_Pengajuan',
                        'mp.Keterangan as status',
                        'mp.Kode',
                        'KPI_Update_Performance.Tanggal',
                        'vgsglj.ID_Level',
                        'vgsglj.nama_jabatan as Nama_Jabatan',
                    );

                if (! empty($search)) {
                    $allDataQuery->where(
                        fn ($q) => $q
                            ->where('k.Nama', 'LIKE', "%$search%")
                            ->orWhere('vdsd.nama_divisi', 'LIKE', "%$search%"),
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    // Band itu rentang skor, jadi urutan skor = urutan band, dan
                    // tetap benar untuk baris yang pointer band-nya basi.
                    $allDataQuery->orderByRaw(
                        'KPI_Update_Performance.Total_Score '.($sortStatus === 'asc' ? 'ASC' : 'DESC'),
                    );
                }

                $allDataQuery
                    ->orderByDesc('KPI_Update_Performance.Tanggal')
                    ->orderByDesc('KPI_Update_Performance.Id_Update_Performance');

                $totalData = (clone $allDataQuery)->count();
                $paginatedItems = (clone $allDataQuery)->forPage($page, $limit)->get();

                $resultRecent = [
                    'data' => $paginatedItems
                        ->values()
                        ->map(
                            fn ($item) => [
                                'id' => bin2hex(random_bytes(16)).Str::random(16),
                                'Id_Update_Performance' => Hashids::connection('custom')->encode(
                                    $item->Id_Update_Performance,
                                ),
                                'Id_Pengajuan' => Hashids::connection('custom')->encode($item->Id_Pengajuan),
                                'name' => $item->name,
                                'division' => $item->division,
                                'subdivision' => $item->subdivision,
                                'achievement' => round($item->achievement, 1),
                                'status' => $item->status ?? $this->bandDariSkor($item->achievement)->Keterangan,
                            ],
                        )
                        ->all(),
                    'page' => $page,
                    'total_page' => ceil($totalData / $limit),
                    'total_data' => $totalData,
                    'is_full_year' => false,
                ];

                // Batas atas band NOT PERFORM: di atasnya "berprestasi", di bawahnya
                // "perlu perhatian". Diambil dari master, bukan angka yang ditulis ulang.
                $batasBawahNp = (float) ($this->bandPerformance()->firstWhere('Kode', 'NP')->Range_Akhir ?? 74);

                $topPerformersBaseQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        // Rujukan transaksi ikut supaya baris peringkat bisa dibuka
                        // detailnya. Tanpa ini modal terbuka tanpa rujukan: namanya
                        // muncul di judul, tetapi profil dan parameternya kosong.
                        'KPI_Update_Performance.Id_Update_Performance',
                        'KPI_Update_Performance.Id_Pengajuan',
                    )
                    ->where('KPI_Update_Performance.Total_Score', '>', $batasBawahNp)
                    ->orderByDesc('KPI_Update_Performance.Total_Score')
                    ->take($topLimit);

                if ($levelIdsTop->isNotEmpty()) {
                    $topPerformersBaseQuery->whereIn('vgsglj.ID_Level', $levelIdsTop);
                }

                $topPerformers = $topPerformersBaseQuery->get()->map(
                    fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                        'Id_Update_Performance' => $item->Id_Update_Performance,
                        'Id_Pengajuan' => $item->Id_Pengajuan,
                    ],
                );

                $underPerformersBaseQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        // Rujukan transaksi ikut supaya baris peringkat bisa dibuka
                        // detailnya. Tanpa ini modal terbuka tanpa rujukan: namanya
                        // muncul di judul, tetapi profil dan parameternya kosong.
                        'KPI_Update_Performance.Id_Update_Performance',
                        'KPI_Update_Performance.Id_Pengajuan',
                    )
                    ->where('KPI_Update_Performance.Total_Score', '<=', $batasBawahNp)
                    ->orderBy('KPI_Update_Performance.Total_Score')
                    ->take($topLimit);

                if ($levelIdsUnder->isNotEmpty()) {
                    $underPerformersBaseQuery->whereIn('vgsglj.ID_Level', $levelIdsUnder);
                }

                $underPerformers = $underPerformersBaseQuery->get()->map(
                    fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                        'Id_Update_Performance' => $item->Id_Update_Performance,
                        'Id_Pengajuan' => $item->Id_Pengajuan,
                    ],
                );

                $chartBarData = (clone $baseQuery)
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_sub_divisi as subdivision',
                        'vgsglj.nama_jabatan as Nama_Jabatan',
                    )
                    ->orderByDesc('KPI_Update_Performance.Total_Score')
                    ->get();

                $chartBar = [
                    'labels' => $chartBarData->pluck('name'),
                    'series' => $chartBarData->pluck('achievement')->map($formatScore),
                    'details' => $chartBarData->map(
                        fn ($item) => [
                            'departemen' => $item->subdivision,
                            'jabatan' => $item->Nama_Jabatan,
                            'nama' => $item->name,
                        ],
                    ),
                ];
            } else {
                $performanceRanges = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();
                $performanceMasterMap = $performanceRanges->pluck('Keterangan', 'Kode');
                $formulas = DB::table('KPI_Master_Formula')
                    ->where('Flag_Aktif', 'Y')
                    ->pluck('Rumus', 'Id_Master_Formula')
                    ->all();

                $latestUpdatesSubquery = (clone $baseQuery)->select(
                    'Id_User',
                    'Id_Update_Performance',
                    'Id_Pengajuan',
                    DB::raw('ROW_NUMBER() OVER (
                            PARTITION BY KPI_Update_Performance.Id_User
                            ORDER BY KPI_Update_Performance.Tanggal DESC, KPI_Update_Performance.Id_Update_Performance DESC
                        ) as rn'),
                );

                $latestUpdatesMap = DB::table(DB::raw("({$latestUpdatesSubquery->toSql()}) as sub"))
                    ->mergeBindings($latestUpdatesSubquery)
                    ->where('rn', 1)
                    ->get()
                    ->keyBy('Id_User');

                $aggregatedData = (clone $baseQuery)
                    ->join(
                        'KPI_Update_Performance_Detail as kupd',
                        'KPI_Update_Performance.Id_Update_Performance',
                        '=',
                        'kupd.Id_Update_Performance',
                    )
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->join(
                        'KPI_Pengajuan_Detail as kpi_detail',
                        'kpd.Id_Pengajuan_Detail',
                        '=',
                        'kpi_detail.Id_Pengajuan_Detail',
                    )
                    ->select(
                        'k.UserID_Web',
                        'k.Nama',
                        'vdsd.nama_divisi',
                        'vdsd.nama_sub_divisi',
                        'vgsglj.nama_jabatan',
                        'vgsglj.ID_Level',
                        'kpi_detail.Id_Pengajuan_Detail',
                        'kpi_detail.Bobot',
                        'kpi_detail.Id_Master_Formula',
                        DB::raw('SUM(kpd.Nilai_Target) as total_target'),
                        DB::raw('SUM(kupd.Nilai_Actual) as total_actual'),
                    )
                    ->groupBy(
                        'k.UserID_Web',
                        'k.Nama',
                        'vdsd.nama_divisi',
                        'vdsd.nama_sub_divisi',
                        'vgsglj.nama_jabatan',
                        'vgsglj.ID_Level',
                        'kpi_detail.Id_Pengajuan_Detail',
                        'kpi_detail.Bobot',
                        'kpi_detail.Id_Master_Formula',
                    )
                    ->get();

                $yearlyPerformance = $aggregatedData
                    ->groupBy('UserID_Web')
                    ->map(function ($kpiRecords, $userId) use ($latestUpdatesMap, $performanceRanges, $formulas) {
                        $totalYearlyScore = 0;
                        $karyawanData = $kpiRecords->first();

                        foreach ($kpiRecords as $kpi) {
                            $calculation = $this->calculateFormulaStream(
                                $kpi->total_actual,
                                $kpi->total_target,
                                $kpi->Bobot,
                                $kpi->Id_Master_Formula,
                                $formulas,
                            );
                            $totalYearlyScore += $calculation['score'];
                        }

                        $performanceCode = 'NP';
                        foreach ($performanceRanges as $range) {
                            if ($totalYearlyScore >= $range->Range_Awal && $totalYearlyScore <= $range->Range_Akhir) {
                                $performanceCode = $range->Kode;
                                break;
                            }
                        }

                        $latestUpdate = $latestUpdatesMap->get($userId);

                        return [
                            'Id_Update_Performance' => $latestUpdate->Id_Update_Performance ?? null,
                            'Id_Pengajuan' => $latestUpdate->Id_Pengajuan ?? null,
                            'Nama_Lengkap' => $karyawanData->Nama,
                            'Total_Score' => $totalYearlyScore,
                            'Nama_Divisi' => $karyawanData->nama_divisi,
                            'Nama_Departemen' => $karyawanData->nama_sub_divisi,
                            'Nama_Jabatan' => $karyawanData->nama_jabatan,
                            'ID_Level' => $karyawanData->ID_Level,
                            'Kode' => $performanceCode,
                        ];
                    });

                $yearlyPerformanceCollection = $yearlyPerformance->values();
                $countEmployee = $yearlyPerformanceCollection->count();
                $onTarget = $yearlyPerformanceCollection->where('Total_Score', '>=', 100)->count();
                $underTarget = $yearlyPerformanceCollection->where('Total_Score', '<', 100)->count();

                $topPerformersFiltered = $levelIdsTop->isNotEmpty()
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsTop)
                    : $yearlyPerformanceCollection;
                $topPerformers = $topPerformersFiltered
                    ->whereIn('Kode', ['ET', 'GD', 'SY'])
                    ->sortByDesc('Total_Score')
                    ->take($topLimit)
                    ->values()
                    ->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $underPerformersFiltered = $levelIdsUnder->isNotEmpty()
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsUnder)
                    : $yearlyPerformanceCollection;
                $underPerformers = $underPerformersFiltered
                    ->where('Kode', 'NP')
                    ->sortBy('Total_Score')
                    ->take($topLimit)
                    ->values()
                    ->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $recentKpiCollection = $yearlyPerformanceCollection;
                if (! empty($search)) {
                    $recentKpiCollection = $recentKpiCollection->filter(
                        fn ($item) => stripos($item['Nama_Lengkap'], $search) !== false ||
                            stripos($item['Nama_Divisi'], $search) !== false,
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    $ascOrder = ['NP' => 1, 'SY' => 2, 'GD' => 3, 'ET' => 4, 'DEFAULT' => 5];
                    $descOrder = ['ET' => 1, 'GD' => 2, 'SY' => 3, 'NP' => 4, 'DEFAULT' => 5];
                    $orderMap = $sortStatus === 'asc' ? $ascOrder : $descOrder;

                    $recentKpiCollection = $recentKpiCollection
                        ->sortBy(fn ($item) => $orderMap[$item['Kode']] ?? $orderMap['DEFAULT'])
                        ->values();
                } else {
                    $recentKpiCollection = $recentKpiCollection->sortByDesc('Total_Score')->values();
                }

                $totalData = $recentKpiCollection->count();
                $totalPage = (int) ceil($totalData / $limit);
                if ($totalPage < 1) {
                    $totalPage = 1;
                }
                $paginatedItems = $recentKpiCollection->forPage($page, $limit)->values();

                $formattedData = $paginatedItems->map(function ($item) use ($performanceMasterMap) {
                    return [
                        'id' => bin2hex(random_bytes(16)).Str::random(16),
                        'Id_Update_Performance' => $item['Id_Update_Performance']
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => $item['Id_Pengajuan']
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                        'name' => $item['Nama_Lengkap'],
                        'division' => $item['Nama_Divisi'],
                        'subdivision' => $item['Nama_Departemen'],
                        'achievement' => round($item['Total_Score'], 1),
                        'status' => $performanceMasterMap[$item['Kode']] ?? $item['Kode'],
                    ];
                });

                $resultRecent = [
                    'data' => $formattedData,
                    'page' => $page,
                    'total_page' => $totalPage,
                    'total_data' => $totalData,
                    'is_full_year' => true,
                ];

                $chartBarData = $yearlyPerformanceCollection->sortByDesc('Total_Score');
                $chartBar = [
                    'labels' => $chartBarData->pluck('Nama_Lengkap'),
                    'series' => $chartBarData->pluck('Total_Score')->map($formatScore),
                    'details' => $chartBarData->map(
                        fn ($item) => [
                            'departemen' => $item['Nama_Departemen'],
                            'jabatan' => $item['Nama_Jabatan'],
                            'nama' => $item['Nama_Lengkap'],
                        ],
                    ),
                ];
            }

            $heatmapRawData = (clone $baseQuery)
                ->select(
                    'k.UserID_Web',
                    'k.Nama as Nama_Lengkap',
                    'vgsglj.nama_jabatan as Nama_Jabatan',
                    'vdsd.nama_divisi as Nama_Divisi',
                    'vdsd.nama_sub_divisi as Nama_Departemen',
                    'KPI_Update_Performance.Periode',
                    'KPI_Update_Performance.Total_Score',
                )
                ->whereNotNull('k.UserID_Web')
                ->orderBy('k.Nama', 'asc')
                ->get();

            $monthCount = $bulan && $bulan !== 'ALL' ? 1 : 12;
            $heatmapCategories = $bulan && $bulan !== 'ALL' ? [$allMonths[(int) $bulan - 1]] : $allMonths;

            $groupedData = $heatmapRawData->groupBy('UserID_Web');
            $heatmapSeries = [];
            $heatmapDetails = [];

            foreach ($groupedData as $userId => $records) {
                $monthlyScores = array_fill(0, $monthCount, null);

                foreach ($records as $record) {
                    if ($record->Periode) {
                        $monthIndex = (int) substr($record->Periode, 5, 2) - 1;

                        if ($monthCount === 1 && $monthIndex === (int) $bulan - 1) {
                            $monthlyScores[0] = $formatScore($record->Total_Score ?? 0);
                        } elseif ($monthCount === 12 && $monthIndex >= 0 && $monthIndex < 12) {
                            $monthlyScores[$monthIndex] = $formatScore($record->Total_Score ?? 0);
                        }
                    }
                }

                $firstRecord = $records->first();
                if ($firstRecord) {
                    $heatmapSeries[] = ['name' => $firstRecord->Nama_Lengkap, 'data' => $monthlyScores];
                    $heatmapDetails[] = [
                        'departemen' => $firstRecord->Nama_Departemen,
                        'divisi' => $firstRecord->Nama_Divisi,
                        'jabatan' => $firstRecord->Nama_Jabatan,
                    ];
                }
            }

            $chartHeatmap = [
                'series' => $heatmapSeries,
                'details' => $heatmapDetails,
                'categories' => $heatmapCategories,
            ];

            $resultAkhir = [
                'Widget' => [
                    'Employee' => $countEmployee,
                    'On_Target' => $onTarget,
                    'Under_Target' => $underTarget,
                ],
                'Recent_Kpi_Update' => $resultRecent,
                'Top_3_Performers' => $topPerformers->map(
                    fn ($item) => (object) [
                        'rank' => $item['rank'],
                        'Nama_Lengkap' => $item['Nama_Lengkap'],
                        'Total_Score' => $formatScore($item['Total_Score']),
                        'Nama_Divisi' => $item['Nama_Divisi'],
                        'Nama_Departemen' => $item['Nama_Departemen'],
                        /* Rujukan transaksi, di-hash sama seperti daftar update.
                           null berarti orangnya memang tak punya update hidup di
                           periode ini; layar memakainya untuk TIDAK menawarkan
                           detail yang pasti kosong. */
                        'Id_Update_Performance' => ($item['Id_Update_Performance'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => ($item['Id_Pengajuan'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                    ],
                ),
                'Under_3_Performers' => $underPerformers->map(
                    fn ($item) => (object) [
                        'rank' => $item['rank'],
                        'Nama_Lengkap' => $item['Nama_Lengkap'],
                        'Total_Score' => $formatScore($item['Total_Score']),
                        'Nama_Divisi' => $item['Nama_Divisi'],
                        'Nama_Departemen' => $item['Nama_Departemen'],
                        /* Rujukan transaksi, di-hash sama seperti daftar update.
                           null berarti orangnya memang tak punya update hidup di
                           periode ini; layar memakainya untuk TIDAK menawarkan
                           detail yang pasti kosong. */
                        'Id_Update_Performance' => ($item['Id_Update_Performance'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => ($item['Id_Pengajuan'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                    ],
                ),
                'Chart_Bar' => $chartBar,
                'Chart_Heatmap' => $chartHeatmap,
            ];

            return ResponseHelper::success($resultAkhir, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error(
                'getDataInformasi: '.$e->getMessage().' on line '.$e->getLine().' in file '.$e->getFile(),
            );

            return ResponseHelper::error('Terjadi Kesalahan Pada Server', 500);
        }
    }

    public function getDataInformasiV2(Request $request)
    {
        $pengguna = Auth::user();

        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 5);
            $search = $request->input('q', null);
            $heatmapPage = max(1, (int) $request->input('heatmap_page', 1));
            $heatmapLimit = max(1, (int) $request->input('heatmap_limit', 20));
            $barPage = max(1, (int) $request->input('bar_page', 1));
            $barLimit = max(1, (int) $request->input('bar_limit', 20));
            $topLimit = min(50, max(1, (int) $request->input('top_limit', 5)));

            $divisiIdsInput = $request->input('divisi');
            $departemenIdsInput = $request->input('departemen');
            $levelIdsInput = $request->input('level');
            $bulan = $request->input('bulan');
            $tahun = $request->input('tahun');
            $levelIdsTopInput = $request->input('levelTop');
            $levelIdsUnderInput = $request->input('levelUnder');
            $sortStatus = $request->input('sort_status');
            $leaderId = $pengguna->karyawan->Kode_Karyawan;

            // -----------------------------------------------------------
            // 1. CEK AKSES & TEAM
            // -----------------------------------------------------------
            $userAccessDivisi = DB::table('KPI_User_Akses_Divisi')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Divisi');
            $userAccessLevel = DB::table('KPI_User_Akses_Level')
                ->where('Id_User', $pengguna->Id_Users)
                ->pluck('Id_Level');
            $teamMemberIds = DB::table('KPI_Karyawan_Team')
                ->where('Kode_Karyawan_Leader', $leaderId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Kode_Karyawan_Team');
            // dd($teamMemberIds, $userAccessDivisi, $userAccessLevel);

            if ($teamMemberIds->isEmpty() && $userAccessDivisi->isEmpty() && $userAccessLevel->isEmpty()) {
                return ResponseHelper::success(
                    $this->getEmptyResultStructure(),
                    'Data Tidak Ditemukan (Tidak Ada Akses)',
                    200,
                );
            }

            $decodeHashids = fn ($s) => $s
                ? collect(explode(',', $s))
                    ->map(fn ($h) => Hashids::connection('custom')->decode(trim($h))[0] ?? null)
                    ->filter()
                : collect();
            $divisiIds = $divisiIdsInput && $divisiIdsInput !== 'ALL' ? $decodeHashids($divisiIdsInput) : collect();
            $departemenIds =
                $departemenIdsInput && $departemenIdsInput !== 'ALL' ? $decodeHashids($departemenIdsInput) : collect();
            $levelIds = $levelIdsInput && $levelIdsInput !== 'ALL' ? $decodeHashids($levelIdsInput) : collect();
            $levelIdsTop =
                $levelIdsTopInput && $levelIdsTopInput !== 'ALL' ? $decodeHashids($levelIdsTopInput) : collect();
            $levelIdsUnder =
                $levelIdsUnderInput && $levelIdsUnderInput !== 'ALL' ? $decodeHashids($levelIdsUnderInput) : collect();

            // -----------------------------------------------------------
            // 2. BASE QUERY (Digunakan untuk Filter User & Data Bulanan)
            // -----------------------------------------------------------
            $baseQuery = DB::table('KPI_Update_Performance')
                ->join('Karyawan as k', 'KPI_Update_Performance.Id_User', '=', 'k.UserID_Web')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vgsglj.ID_Level_Jabatan',
                )
                ->whereNull('KPI_Update_Performance.Status')
                ->where('KPI_Update_Performance.Status_Pengajuan', 'disetujui')
                ->where('KPI_Update_Performance.Flag_Approval', 'Y')
                ->where('k.Aktif', 'Y')
                // Tanpa Flag_Aktif, header lama yang sudah digantikan revisi ikut terhitung
                // (penggantian hanya menyetel Flag_Aktif='T', Status tetap NULL).
                ->where('KPI_Update_Performance.Flag_Aktif', 'Y')
                ->where('KPI_Update_Performance.Flag_Verifikasi', 'Y');

            $this->satuUpdatePerPeriode($baseQuery);

            if ($teamMemberIds->isNotEmpty()) {
                $baseQuery->whereIn('k.Kode_Karyawan', $teamMemberIds);
            } else {
                $baseQuery->whereIn('vdsd.Id_Divisi', $userAccessDivisi)->whereIn('vgsglj.ID_Level', $userAccessLevel);
            }

            if ($divisiIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.Id_Divisi', $divisiIds);
            }
            if ($departemenIds->isNotEmpty()) {
                $baseQuery->whereIn('vdsd.ID_Sub_Divisi', $departemenIds);
            }
            if ($levelIds->isNotEmpty()) {
                $baseQuery->whereIn('vgsglj.ID_Level', $levelIds);
            }

            if ($tahun) {
                // SARGABLE: LEFT(Periode,4)=year → prefix 'year-%' (Periode 'YYYY-MM').
                $baseQuery->where('KPI_Update_Performance.Periode', 'like', $tahun.'-%');
            }

            // [PENTING] Clone query SEBELUM filter bulan agar Heatmap tetap Full Year
            $queryForHeatmap = clone $baseQuery;

            $formatScore = fn ($score) => fmod(round($score, 1), 1) === 0.0 ? (int) round($score, 1) : round($score, 1);
            $allMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

            // -----------------------------------------------------------
            // YTD: tentukan batas bulan (maxMonth) untuk agregasi
            // - Full-Year (ALL)  -> 12 bulan penuh
            // - YTD              -> Jan..(bulan berjalan - 1), tahun lampau = 12
            // -----------------------------------------------------------
            $selectedYearInt = (int) ($tahun ?? date('Y'));
            // Batas bulan YTD mengikuti PENJADWALAN pengisian update performance
            // (Flag_Penilaian_KPI). Agregat lintas karyawan → pakai jadwal global.
            // 0 = belum ada periode terjadwal → YTD kosong.
            $ytdEndMonth = KpiScheduleHelper::resolveYtdCapMonth($selectedYearInt);
            $maxMonth = $bulan === 'YTD' ? $ytdEndMonth : 12;

            // Init Variables
            $countEmployee = 0;
            $onTarget = 0;
            $underTarget = 0;
            $resultRecent = [];
            $topPerformers = collect();
            $underPerformers = collect();
            $chartBar = [];
            $chartHeatmap = [];

            // -----------------------------------------------------------
            // 3. LOGIC DATA WIDGET & LIST
            // -----------------------------------------------------------
            if ($bulan && $bulan !== 'ALL' && $bulan !== 'YTD') {
                // ==========================
                // CASE 1: FILTER BULANAN
                // ==========================
                $baseQuery->whereRaw('RIGHT(KPI_Update_Performance.Periode, 2) = ?', [$bulan]);

                $widgetCounts = (clone $baseQuery)
                    ->selectRaw('COUNT(DISTINCT KPI_Update_Performance.Id_User) as countEmployee')
                    // DISTINCT Id_User: satuannya orang, sama dengan countEmployee di atas.
                    ->selectRaw('COUNT(DISTINCT CASE WHEN KPI_Update_Performance.Total_Score >= 100 THEN KPI_Update_Performance.Id_User END) as onTarget')
                    ->selectRaw('COUNT(DISTINCT CASE WHEN KPI_Update_Performance.Total_Score < 100 THEN KPI_Update_Performance.Id_User END) as underTarget')
                    ->first();

                $countEmployee = $widgetCounts->countEmployee ?? 0;
                $onTarget = $widgetCounts->onTarget ?? 0;
                $underTarget = $widgetCounts->underTarget ?? 0;

                $allDataQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'KPI_Update_Performance.Id_Update_Performance',
                        'k.Nama as name',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'KPI_Update_Performance.Id_Pengajuan',
                        'mp.Keterangan as status',
                        'mp.Kode',
                        'KPI_Update_Performance.Tanggal',
                        'vgsglj.ID_Level',
                        'vgsglj.nama_jabatan as Nama_Jabatan',
                    );

                if (! empty($search)) {
                    $allDataQuery->where(
                        fn ($q) => $q
                            ->where('k.Nama', 'LIKE', "%$search%")
                            ->orWhere('vdsd.nama_divisi', 'LIKE', "%$search%"),
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    // Band itu rentang skor, jadi urutan skor = urutan band, dan
                    // tetap benar untuk baris yang pointer band-nya basi.
                    $allDataQuery->orderByRaw(
                        'KPI_Update_Performance.Total_Score '.($sortStatus === 'asc' ? 'ASC' : 'DESC'),
                    );
                }

                $allDataQuery
                    ->orderByDesc('KPI_Update_Performance.Tanggal')
                    ->orderByDesc('KPI_Update_Performance.Id_Update_Performance');
                // dd($allDataQuery->get());

                $totalData = (clone $allDataQuery)->count();
                $paginatedItems = (clone $allDataQuery)->forPage($page, $limit)->get();

                $resultRecent = [
                    'data' => $paginatedItems
                        ->values()
                        ->map(
                            fn ($item) => [
                                'id' => bin2hex(random_bytes(16)).Str::random(16),
                                'Id_Update_Performance' => Hashids::connection('custom')->encode(
                                    $item->Id_Update_Performance,
                                ),
                                'Id_Pengajuan' => Hashids::connection('custom')->encode($item->Id_Pengajuan),
                                'name' => $item->name,
                                'division' => $item->division,
                                'subdivision' => $item->subdivision,
                                'achievement' => round($item->achievement, 1),
                                'status' => $item->status ?? $this->bandDariSkor($item->achievement)->Keterangan,
                            ],
                        )
                        ->all(),
                    'page' => $page,
                    'total_page' => ceil($totalData / $limit),
                    'total_data' => $totalData,
                    'is_full_year' => false,
                ];

                // Batas atas band NOT PERFORM: di atasnya "berprestasi", di bawahnya
                // "perlu perhatian". Diambil dari master, bukan angka yang ditulis ulang.
                $batasBawahNp = (float) ($this->bandPerformance()->firstWhere('Kode', 'NP')->Range_Akhir ?? 74);

                $topPerformersBaseQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        // Rujukan transaksi ikut supaya baris peringkat bisa dibuka
                        // detailnya. Tanpa ini modal terbuka tanpa rujukan: namanya
                        // muncul di judul, tetapi profil dan parameternya kosong.
                        'KPI_Update_Performance.Id_Update_Performance',
                        'KPI_Update_Performance.Id_Pengajuan',
                    )
                    ->where('KPI_Update_Performance.Total_Score', '>', $batasBawahNp)
                    ->orderByDesc('KPI_Update_Performance.Total_Score')
                    ->take($topLimit);
                if ($levelIdsTop->isNotEmpty()) {
                    $topPerformersBaseQuery->whereIn('vgsglj.ID_Level', $levelIdsTop);
                }
                $topPerformers = $topPerformersBaseQuery->get()->map(
                    fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                        'Id_Update_Performance' => $item->Id_Update_Performance,
                        'Id_Pengajuan' => $item->Id_Pengajuan,
                    ],
                );

                $underPerformersBaseQuery = (clone $baseQuery)
                    // LEFT: pointer basi tidak boleh menghapus orangnya dari layar;
                    // statusnya diturunkan dari skor lewat bandDariSkor().
                    ->leftJoin(
                        'KPI_Master_Performance as mp',
                        'KPI_Update_Performance.Id_Performance',
                        '=',
                        'mp.Id_Master_Performance',
                    )
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_divisi as division',
                        'vdsd.nama_sub_divisi as subdivision',
                        // Rujukan transaksi ikut supaya baris peringkat bisa dibuka
                        // detailnya. Tanpa ini modal terbuka tanpa rujukan: namanya
                        // muncul di judul, tetapi profil dan parameternya kosong.
                        'KPI_Update_Performance.Id_Update_Performance',
                        'KPI_Update_Performance.Id_Pengajuan',
                    )
                    ->where('KPI_Update_Performance.Total_Score', '<=', $batasBawahNp)
                    ->orderBy('KPI_Update_Performance.Total_Score')
                    ->take($topLimit);
                if ($levelIdsUnder->isNotEmpty()) {
                    $underPerformersBaseQuery->whereIn('vgsglj.ID_Level', $levelIdsUnder);
                }
                $underPerformers = $underPerformersBaseQuery->get()->map(
                    fn ($item, $key) => [
                        'rank' => $key + 1,
                        'Nama_Lengkap' => $item->name,
                        'Total_Score' => $item->achievement,
                        'Nama_Divisi' => $item->division,
                        'Nama_Departemen' => $item->subdivision,
                        'Id_Update_Performance' => $item->Id_Update_Performance,
                        'Id_Pengajuan' => $item->Id_Pengajuan,
                    ],
                );

                $chartBarQuery = (clone $baseQuery)
                    ->select(
                        'k.Nama as name',
                        'KPI_Update_Performance.Total_Score as achievement',
                        'vdsd.nama_sub_divisi as subdivision',
                        'vgsglj.nama_jabatan as Nama_Jabatan',
                    )
                    ->orderByDesc('KPI_Update_Performance.Total_Score');

                $barTotalData = (clone $chartBarQuery)->count();
                $barTotalPage = $barTotalData > 0 ? (int) ceil($barTotalData / $barLimit) : 0;
                if ($barTotalPage > 0 && $barPage > $barTotalPage) {
                    $barPage = $barTotalPage;
                }
                $barOffset = ($barPage - 1) * $barLimit;

                $chartBarData = (clone $chartBarQuery)
                    ->skip($barOffset)
                    ->take($barLimit)
                    ->get();

                $chartBar = [
                    'labels' => $chartBarData->pluck('name'),
                    'series' => $chartBarData->pluck('achievement')->map($formatScore),
                    'details' => $chartBarData->map(
                        fn ($item) => [
                            'departemen' => $item->subdivision,
                            'jabatan' => $item->Nama_Jabatan,
                            'nama' => $item->name,
                        ],
                    ),
                    'page' => $barPage,
                    'limit' => $barLimit,
                    'total_page' => $barTotalPage,
                    'total_data' => $barTotalData,
                ];
            } else {
                // ==========================
                // CASE 2: FULL YEAR (PERBAIKAN FLAG AKTIF)
                // ==========================

                $performanceRanges = DB::table('KPI_Master_Performance')->orderBy('Range_Awal')->get();
                $performanceMasterMap = $performanceRanges->pluck('Keterangan', 'Kode');

                // 1. Ambil List User yang Valid sesuai Filter
                $filteredUserIds = (clone $baseQuery)->pluck('KPI_Update_Performance.Id_User')->unique();

                // 2. Ambil Parameter KPI (WAJIB FILTER FLAG_AKTIF = 'Y')
                $kpiParams = DB::table('KPI_Pengajuan as kp')
                    ->join('Karyawan as k', 'kp.Id_User', '=', 'k.UserID_Web')
                    ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                        'k.Id_Level_Jabatan',
                        '=',
                        'vgsglj.ID_Level_Jabatan',
                    )
                    ->join('KPI_Pengajuan_Detail as kpi_detail', 'kp.Id_Pengajuan', '=', 'kpi_detail.Id_Pengajuan')
                    ->whereIn('kp.Id_User', $filteredUserIds)
                    ->where('kp.Flag_Aktif', 'Y') // <<--- INI FIX UTAMANYA!
                    ->where('k.Aktif', 'Y')
                    ->join('KPI_Pengajuan_Det as kpd_check', function ($join) use ($tahun) {
                        $join
                            ->on('kpi_detail.Id_Pengajuan_Detail', '=', 'kpd_check.Id_Pengajuan_Detail')
                            ->where('kpd_check.Periode', 'like', $tahun.'-%');
                    })
                    ->select(
                        'k.UserID_Web',
                        'k.Nama',
                        'vdsd.nama_divisi',
                        'vdsd.nama_sub_divisi',
                        'vgsglj.nama_jabatan',
                        'vgsglj.ID_Level',
                        'kp.Id_Pengajuan',
                        'kpi_detail.Id_Pengajuan_Detail',
                        'kpi_detail.Bobot',
                        'kpi_detail.Id_Master_Formula',
                    )
                    ->distinct()
                    ->get();

                // 3–4. Skor akumulasi dari SATU sumber: KpiAkumulasiYtdService — sama
                // dengan Monitoring, homepage, export, dashboard staff, dan
                // `kpi:verify-ytd`. Dulu dihitung di sini sendiri dan menjepit di TOTAL,
                // padahal sejak 28 Agt 2026 jepitan 50–125 berlaku PER PARAMETER.
                // Karyawan tanpa realisasi terverifikasi di rentang ini tidak punya
                // skor (bukan 50 hasil menjepit nol) dan tidak ikut dihitung.
                $ytdSvc = app(KpiAkumulasiYtdService::class);
                $skorYtd = $maxMonth > 0
                    ? $ytdSvc->skorPerUser(
                        $kpiParams->pluck('UserID_Web')->unique()->values()->all(),
                        (int) $tahun,
                        $ytdSvc->periodeSampai((int) $tahun, $maxMonth),
                    )
                    : [];

                // 5. Ambil ID Update Terakhir
                $latestUpdatesSubquery = (clone $baseQuery)->select(
                    'Id_User',
                    'Id_Update_Performance',
                    'Id_Pengajuan',
                    DB::raw(
                        'ROW_NUMBER() OVER (PARTITION BY KPI_Update_Performance.Id_User ORDER BY KPI_Update_Performance.Tanggal DESC, KPI_Update_Performance.Id_Update_Performance DESC) as rn',
                    ),
                );
                $latestUpdatesMap = DB::table(DB::raw("({$latestUpdatesSubquery->toSql()}) as sub"))
                    ->mergeBindings($latestUpdatesSubquery)
                    ->where('rn', 1)
                    ->get()
                    ->keyBy('Id_User');

                // 6. Mapping & Kalkulasi
                $yearlyPerformance = $kpiParams
                    ->groupBy('UserID_Web')
                    ->filter(fn ($userParams, $userId) => array_key_exists((string) $userId, $skorYtd))
                    ->map(function ($userParams, $userId) use (
                        $skorYtd,
                        $latestUpdatesMap,
                        $performanceRanges,
                    ) {
                        $karyawanData = $userParams->first();

                        $totalYearlyScore = (float) $skorYtd[(string) $userId];

                        // $performanceCode = 'NP';
                        // foreach ($performanceRanges as $range) {
                        //     if ($totalYearlyScore >= $range->Range_Awal && $totalYearlyScore <= $range->Range_Akhir) {
                        //         $performanceCode = $range->Kode; break;
                        //     }
                        // }

                        $performanceCode = 'NP';

                        // foreach ($performanceRanges as $range) {
                        //     if (
                        //         $totalYearlyScore >= $range->Range_Awal &&
                        //         $totalYearlyScore < ($range->Range_Akhir + 1)
                        //     ) {
                        //         $performanceCode = $range->Kode;
                        //         break;
                        //     }
                        // }
                        $score = floor($totalYearlyScore);

                        foreach ($performanceRanges as $range) {
                            if ($score >= $range->Range_Awal && $score <= $range->Range_Akhir) {
                                $performanceCode = $range->Kode;
                                break;
                            }
                        }

                        $latestUpdate = $latestUpdatesMap->get($userId);

                        return [
                            'Id_Update_Performance' => $latestUpdate->Id_Update_Performance ?? null,
                            'Id_Pengajuan' => $latestUpdate->Id_Pengajuan ?? $karyawanData->Id_Pengajuan,
                            'Nama_Lengkap' => $karyawanData->Nama,
                            'Total_Score' => $totalYearlyScore,
                            'Nama_Divisi' => $karyawanData->nama_divisi,
                            'Nama_Departemen' => $karyawanData->nama_sub_divisi,
                            'Nama_Jabatan' => $karyawanData->nama_jabatan,
                            'ID_Level' => $karyawanData->ID_Level,
                            'Kode' => $performanceCode,
                        ];
                    });

                // ... (Sisa logic sorting dan pagination sama) ...
                $yearlyPerformanceCollection = $yearlyPerformance->values();
                // dd($yearlyPerformanceCollection);
                $countEmployee = $yearlyPerformanceCollection->count();
                $onTarget = $yearlyPerformanceCollection->where('Total_Score', '>=', 100)->count();
                $underTarget = $yearlyPerformanceCollection->where('Total_Score', '<', 100)->count();

                $topPerformersFiltered = $levelIdsTop->isNotEmpty()
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsTop)
                    : $yearlyPerformanceCollection;
                $topPerformers = $topPerformersFiltered
                    ->whereIn('Kode', ['ET', 'GD', 'SY'])
                    ->sortByDesc('Total_Score')
                    ->take($topLimit)
                    ->values()
                    ->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $underPerformersFiltered = $levelIdsUnder->isNotEmpty()
                    ? $yearlyPerformanceCollection->whereIn('ID_Level', $levelIdsUnder)
                    : $yearlyPerformanceCollection;
                $underPerformers = $underPerformersFiltered
                    ->where('Kode', 'NP')
                    ->sortBy('Total_Score')
                    ->take($topLimit)
                    ->values()
                    ->map(fn ($item, $key) => ['rank' => $key + 1] + $item);

                $recentKpiCollection = $yearlyPerformanceCollection;
                if (! empty($search)) {
                    $recentKpiCollection = $recentKpiCollection->filter(
                        fn ($item) => stripos($item['Nama_Lengkap'], $search) !== false ||
                            stripos($item['Nama_Divisi'], $search) !== false,
                    );
                }

                if ($sortStatus && in_array($sortStatus, ['asc', 'desc'])) {
                    $ascOrder = ['NP' => 1, 'SY' => 2, 'GD' => 3, 'ET' => 4, 'DEFAULT' => 5];
                    $descOrder = ['ET' => 1, 'GD' => 2, 'SY' => 3, 'NP' => 4, 'DEFAULT' => 5];
                    $orderMap = $sortStatus === 'asc' ? $ascOrder : $descOrder;
                    $recentKpiCollection = $recentKpiCollection
                        ->sortBy(fn ($item) => $orderMap[$item['Kode']] ?? $orderMap['DEFAULT'])
                        ->values();
                } else {
                    $recentKpiCollection = $recentKpiCollection->sortByDesc('Total_Score')->values();
                }

                $totalData = $recentKpiCollection->count();
                $totalPage = (int) ceil($totalData / $limit);
                if ($totalPage < 1) {
                    $totalPage = 1;
                }
                $paginatedItems = $recentKpiCollection->forPage($page, $limit)->values();

                $formattedData = $paginatedItems->map(function ($item) use ($performanceMasterMap) {
                    return [
                        'id' => bin2hex(random_bytes(16)).Str::random(16),
                        'Id_Update_Performance' => $item['Id_Update_Performance']
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => $item['Id_Pengajuan']
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                        'name' => $item['Nama_Lengkap'],
                        'division' => $item['Nama_Divisi'],
                        'subdivision' => $item['Nama_Departemen'],
                        'achievement' => round($item['Total_Score'], 1),
                        'status' => $performanceMasterMap[$item['Kode']] ?? $item['Kode'],
                    ];
                });

                $resultRecent = [
                    'data' => $formattedData,
                    'page' => $page,
                    'total_page' => $totalPage,
                    'total_data' => $totalData,
                    'is_full_year' => true,
                ];
                $chartBarFullData = $yearlyPerformanceCollection->sortByDesc('Total_Score')->values();
                $barTotalData = $chartBarFullData->count();
                $barTotalPage = $barTotalData > 0 ? (int) ceil($barTotalData / $barLimit) : 0;
                if ($barTotalPage > 0 && $barPage > $barTotalPage) {
                    $barPage = $barTotalPage;
                }
                $chartBarData = $chartBarFullData->forPage($barPage, $barLimit)->values();
                $chartBar = [
                    'labels' => $chartBarData->pluck('Nama_Lengkap'),
                    'series' => $chartBarData->pluck('Total_Score')->map($formatScore),
                    'details' => $chartBarData->map(
                        fn ($item) => [
                            'departemen' => $item['Nama_Departemen'],
                            'jabatan' => $item['Nama_Jabatan'],
                            'nama' => $item['Nama_Lengkap'],
                        ],
                    ),
                    'page' => $barPage,
                    'limit' => $barLimit,
                    'total_page' => $barTotalPage,
                    'total_data' => $barTotalData,
                ];
            }

            // -----------------------------------------------------------
            // 4. LOGIC HEATMAP (STATIC FULL YEAR)
            // -----------------------------------------------------------
            $heatmapRawData = (clone $queryForHeatmap)
                ->select(
                    'k.UserID_Web',
                    'k.Nama as Nama_Lengkap',
                    'vgsglj.nama_jabatan as Nama_Jabatan',
                    'vdsd.nama_divisi as Nama_Divisi',
                    'vdsd.nama_sub_divisi as Nama_Departemen',
                    'KPI_Update_Performance.Periode',
                    'KPI_Update_Performance.Total_Score',
                )
                ->whereNotNull('k.UserID_Web')
                ->orderBy('k.Nama', 'asc')
                ->get();

            $monthCount = 12;
            $heatmapCategories = $allMonths;
            $groupedData = $heatmapRawData->groupBy('UserID_Web');
            $heatmapSeries = [];
            $heatmapDetails = [];

            foreach ($groupedData as $userId => $records) {
                $monthlyScores = array_fill(0, 12, null);
                foreach ($records as $record) {
                    if ($record->Periode) {
                        $monthIndex = (int) substr($record->Periode, 5, 2) - 1;
                        if ($monthIndex >= 0 && $monthIndex < 12) {
                            $monthlyScores[$monthIndex] = $formatScore($record->Total_Score ?? 0);
                        }
                    }
                }
                $firstRecord = $records->first();
                if ($firstRecord) {
                    $heatmapSeries[] = ['name' => $firstRecord->Nama_Lengkap, 'data' => $monthlyScores];
                    $heatmapDetails[] = [
                        'departemen' => $firstRecord->Nama_Departemen,
                        'divisi' => $firstRecord->Nama_Divisi,
                        'jabatan' => $firstRecord->Nama_Jabatan,
                    ];
                }
            }
            $chartHeatmap = [
                'series' => $heatmapSeries,
                'details' => $heatmapDetails,
                'categories' => $heatmapCategories,
            ];

            $heatmapTotalData = count($chartHeatmap['series']);
            $heatmapTotalPage = $heatmapTotalData > 0 ? (int) ceil($heatmapTotalData / $heatmapLimit) : 0;

            if ($heatmapTotalPage > 0 && $heatmapPage > $heatmapTotalPage) {
                $heatmapPage = $heatmapTotalPage;
            }

            $heatmapOffset = ($heatmapPage - 1) * $heatmapLimit;
            $chartHeatmap['series'] = array_slice($chartHeatmap['series'], $heatmapOffset, $heatmapLimit);
            $chartHeatmap['details'] = array_slice($chartHeatmap['details'], $heatmapOffset, $heatmapLimit);
            $chartHeatmap['page'] = $heatmapPage;
            $chartHeatmap['limit'] = $heatmapLimit;
            $chartHeatmap['total_page'] = $heatmapTotalPage;
            $chartHeatmap['total_data'] = $heatmapTotalData;

            $resultAkhir = [
                'Widget' => ['Employee' => $countEmployee, 'On_Target' => $onTarget, 'Under_Target' => $underTarget],
                'Recent_Kpi_Update' => $resultRecent,
                'Top_3_Performers' => $topPerformers->map(
                    fn ($item) => (object) [
                        'rank' => $item['rank'],
                        'Nama_Lengkap' => $item['Nama_Lengkap'],
                        'Total_Score' => $formatScore($item['Total_Score']),
                        'Nama_Divisi' => $item['Nama_Divisi'],
                        'Nama_Departemen' => $item['Nama_Departemen'],
                        /* Rujukan transaksi, di-hash sama seperti daftar update.
                           null berarti orangnya memang tak punya update hidup di
                           periode ini; layar memakainya untuk TIDAK menawarkan
                           detail yang pasti kosong. */
                        'Id_Update_Performance' => ($item['Id_Update_Performance'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => ($item['Id_Pengajuan'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                    ],
                ),
                'Under_3_Performers' => $underPerformers->map(
                    fn ($item) => (object) [
                        'rank' => $item['rank'],
                        'Nama_Lengkap' => $item['Nama_Lengkap'],
                        'Total_Score' => $formatScore($item['Total_Score']),
                        'Nama_Divisi' => $item['Nama_Divisi'],
                        'Nama_Departemen' => $item['Nama_Departemen'],
                        /* Rujukan transaksi, di-hash sama seperti daftar update.
                           null berarti orangnya memang tak punya update hidup di
                           periode ini; layar memakainya untuk TIDAK menawarkan
                           detail yang pasti kosong. */
                        'Id_Update_Performance' => ($item['Id_Update_Performance'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Update_Performance'])
                            : null,
                        'Id_Pengajuan' => ($item['Id_Pengajuan'] ?? null)
                            ? Hashids::connection('custom')->encode($item['Id_Pengajuan'])
                            : null,
                    ],
                ),
                'Chart_Bar' => $chartBar,
                'Chart_Heatmap' => $chartHeatmap,
            ];

            return ResponseHelper::success($resultAkhir, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('DashboardCeoController')->error(
                'getDataInformasiV2: '.$e->getMessage().' on line '.$e->getLine().' in file '.$e->getFile(),
            );

            return ResponseHelper::error('Terjadi Kesalahan Pada Server', 500);
        }
    }

    private function getEmptyResultStructure()
    {
        $emptyPaginated = [
            'data' => [],
            'page' => 1,
            'total_page' => 1,
            'total_data' => 0,
            'is_full_year' => false,
        ];

        return [
            'Widget' => ['Employee' => 0, 'On_Target' => 0, 'Under_Target' => 0],
            'Recent_Kpi_Update' => $emptyPaginated,
            'Top_3_Performers' => [],
            'Under_3_Performers' => [],
            'Chart_Bar' => [
                'labels' => [],
                'series' => [],
                'details' => [],
                'page' => 1,
                'limit' => 20,
                'total_page' => 0,
                'total_data' => 0,
            ],
            'Chart_Heatmap' => [
                'series' => [],
                'details' => [],
                'categories' => [],
                'page' => 1,
                'limit' => 20,
                'total_page' => 0,
                'total_data' => 0,
            ],
        ];
    }

    private function calculateFormulaStream($actual, $target, $weight, $idMasterFormula, $formulas)
    {
        // Terpusat di KpiScoringService (acuan kanonik). Perilaku identik.
        return app(\App\Services\KPI\KpiScoringService::class)
            ->scoreParameterById($actual, $target, $weight, $idMasterFormula, $formulas);
    }

    public function recentKpiUserDetail($id_pengajuan, $id_update_performance, $isyear)
    {
        // dd($id_pengajuan, $id_update_performance, $isyear);
        try {
            $isYtd = is_string($isyear) && strtolower($isyear) === 'ytd';
            $isYearBool = $isYtd ? true : filter_var($isyear, FILTER_VALIDATE_BOOLEAN);
            // dd($isYearBool);
            $decodedPengajuanId = Hashids::connection('custom')->decode(trim($id_pengajuan));
            $decodedUpdatePerformanceId = Hashids::connection('custom')->decode(trim($id_update_performance));

            if (empty($decodedPengajuanId) || empty($decodedUpdatePerformanceId)) {
                return ResponseHelper::error('ID Pengajuan atau ID Update Performance tidak valid.', 400);
            }

            $pengajuanId = $decodedPengajuanId[0];
            $updatePerformanceId = $decodedUpdatePerformanceId[0];

            // Ambil formula SEKALI, di luar if/else
            $formulas = DB::table('KPI_Master_Formula')
                ->where('Flag_Aktif', 'Y')
                ->pluck('Rumus', 'Id_Master_Formula')
                ->all();

            // Optimasi query header referensi
            $performanceHeaderRef = DB::table('KPI_Update_Performance as kup')
                ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                ->where('kup.Id_Update_Performance', $updatePerformanceId)
                ->where('kup.Id_Pengajuan', $pengajuanId)
                ->select('kup.Id_User', 'k.Nama as Nama_Lengkap', 'kup.Periode')
                ->first();

            if (! $performanceHeaderRef) {
                return ResponseHelper::error('Data performance KPI referensi tidak ditemukan.', 404);
            }
            $userId = $performanceHeaderRef->Id_User;
            $periodeLengkap = $performanceHeaderRef->Periode;
            $periodeTahun = substr($periodeLengkap, 0, 4);

            // Batas akhir periode agregasi: YTD mengikuti PENJADWALAN pengisian update
            // performance (Flag_Penilaian_KPI) karyawan ybs; selain itu full year (12).
            $detailYtdEndMonth = 12;
            if ($isYtd) {
                $kodeKaryawanDetail = DB::table('Karyawan')
                    ->where('UserID_Web', $userId)
                    ->value('Kode_Karyawan');
                $detailYtdEndMonth = KpiScheduleHelper::resolveYtdCapMonth((int) $periodeTahun, $kodeKaryawanDetail);
            }
            $periodeAkhir = $periodeTahun.'-'.str_pad((string) $detailYtdEndMonth, 2, '0', STR_PAD_LEFT);

            // $performanceRanges = DB::table('KPI_Master_Performance')
            //     ->orderBy('Range_Awal', 'DESC')
            //     ->get();

            // $getPerformanceLevel = function ($scoreValue) use ($performanceRanges) {

            //     if (is_null($scoreValue)) return (object)['Keterangan' => null, 'Warna' => null, 'Kode' => null];
            //     foreach ($performanceRanges as $range) {
            //         if ($scoreValue >= $range->Range_Awal && $scoreValue <= $range->Range_Akhir) {
            //             return (object)['Keterangan' => $range->Keterangan, 'Warna' => $range->Warna, 'Kode' => $range->Kode];
            //         }
            //     }
            //     $lastRange = $performanceRanges->last();
            //     return $lastRange ? (object)['Keterangan' => $lastRange->Keterangan, 'Warna' => $lastRange->Warna, 'Kode' => $lastRange->Kode] : (object)['Keterangan' => 'N/A', 'Warna' => '#808080', 'Kode' => 'NA'];
            // };

            $performanceRanges = DB::table('KPI_Master_Performance')->orderBy('Range_Awal', 'DESC')->get();

            $getPerformanceLevel = function ($scoreValue) use ($performanceRanges) {
                // null safety
                if ($scoreValue === null) {
                    return (object) [
                        'Keterangan' => null,
                        'Warna' => null,
                        'Kode' => null,
                    ];
                }

                foreach ($performanceRanges as $range) {
                    if ($scoreValue >= (float) $range->Range_Awal) {
                        return (object) [
                            'Keterangan' => $range->Keterangan,
                            'Warna' => $range->Warna,
                            'Kode' => $range->Kode,
                        ];
                    }
                }

                // fallback terakhir (harusnya tidak pernah kena)
                return (object) [
                    'Keterangan' => 'N/A',
                    'Warna' => '#808080',
                    'Kode' => 'NA',
                ];
            };

            // dd($getPerformanceLevel(85));

            if ($isYearBool !== true) {
                $performanceHeaderMonthly = DB::table('KPI_Update_Performance as kup')
                    ->join('KPI_Master_Performance as kmp', 'kup.Id_Performance', '=', 'kmp.Id_Master_Performance')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                        'k.Id_Level_Jabatan',
                        '=',
                        'vgsglj.Id_Level_Jabatan',
                    )
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->select(
                        'kup.*',
                        'kmp.Keterangan as Status_Performance',
                        'kmp.Warna',
                        'k.Nama as Nama_Lengkap',
                        'k.Email',
                        'k.HP as Kontak',
                        'vgsglj.nama_jabatan',
                        'vdss.nama_sub_divisi',
                    )
                    ->where('kup.Id_Update_Performance', $updatePerformanceId)
                    ->where('kup.Id_Pengajuan', $pengajuanId)
                    ->first();

                if (! $performanceHeaderMonthly) {
                    return ResponseHelper::error('Data performance KPI bulanan tidak ditemukan.', 404);
                }

                $allDetailsMonthly = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->leftJoin('KPI_Pengajuan_Det as kpd', function ($join) use ($periodeLengkap) {
                        $join
                            ->on('kpi_main.Id_Pengajuan_Detail', '=', 'kpd.Id_Pengajuan_Detail')
                            ->where('kpd.Periode', $periodeLengkap);
                    })
                    ->leftJoin('KPI_Update_Performance_Detail as kupd', function ($join) use ($updatePerformanceId) {
                        $join
                            ->on('kpd.Id_Pengajuan_Det', '=', 'kupd.Id_Pengajuan_Det')
                            ->where('kupd.Id_Update_Performance', $updatePerformanceId);
                    })
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select(
                        'kpi_main.Id_Pengajuan_Detail',
                        'kpi_main.Nama_Parameter',
                        'kpi_main.Deskripsi',
                        'kpi_main.Bobot',
                        'kpi_main.Id_Master_Formula',
                        'kms.Simbol_Satuan',
                        'kpd.Id_Pengajuan_Det',
                        'kpd.Nilai_Target',
                        'kupd.Id_Update_Performance_Detail',
                        'kupd.Nilai_Actual',
                        'kupd.File_Berkas',
                        'kupd.Alasan_Reject',
                        'kupd.Flag_Pica',
                    )
                    ->get();

                $picaDetailIds = $allDetailsMonthly
                    ->where('Flag_Pica', 'Y')
                    ->whereNotNull('Id_Update_Performance_Detail')
                    ->pluck('Id_Update_Performance_Detail')
                    ->unique();
                $allPicasMonthly = collect();
                if ($picaDetailIds->isNotEmpty()) {
                    $allPicasMonthly = DB::table('KPI_Update_Performance_Det')
                        ->whereIn('Id_Update_Performance_Detail', $picaDetailIds)
                        ->get()
                        ->keyBy('Id_Update_Performance_Detail');
                }

                $transformedDetailsMonthly = $allDetailsMonthly->map(function ($item) use (
                    $getPerformanceLevel,
                    $allPicasMonthly,
                    $formulas,
                ) {
                    $newItem = new \stdClass;
                    $isInputPeriod = ! is_null($item->Id_Pengajuan_Det);
                    $wasSubmitted = ! is_null($item->Id_Update_Performance_Detail);

                    $newItem->id = $wasSubmitted
                        ? Hashids::connection('custom')->encode($item->Id_Update_Performance_Detail)
                        : ($isInputPeriod
                            ? Hashids::connection('custom')->encode($item->Id_Pengajuan_Det)
                            : Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail));
                    $newItem->parameter = $item->Nama_Parameter;
                    // Deskripsi parameter: dipakai kartu detail, dipotong dua baris di UI.
                    $newItem->deskripsi = trim((string) ($item->Deskripsi ?? '')) ?: null;
                    $newItem->Bobot = (float) $item->Bobot;

                    $targetMonthly = $isInputPeriod ? (float) $item->Nilai_Target : null;
                    $actualMonthly = $wasSubmitted ? (float) $item->Nilai_Actual : null;

                    $calculation = $this->calculateFormulaStream(
                        $actualMonthly,
                        $targetMonthly,
                        $newItem->Bobot,
                        $item->Id_Master_Formula,
                        $formulas,
                    );

                    // dd( $calculation );
                    $achievementMonthly = $wasSubmitted ? $calculation['achievement'] : null;
                    $scoreMonthly = $wasSubmitted ? $calculation['score'] : null;

                    $newItem->targetValue = $targetMonthly;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = $actualMonthly;
                    $newItem->achievement = $achievementMonthly;
                    $newItem->score = $scoreMonthly;
                    // dd($getPerformanceLevel($newItem->achievement));
                    $performanceMonthly = $getPerformanceLevel($newItem->achievement);

                    $newItem->statusKeterangan = $performanceMonthly->Keterangan;
                    $newItem->statusWarna = $performanceMonthly->Warna;

                    $newItem->is_input_period = $isInputPeriod;
                    $newItem->dataSourceFile =
                        $wasSubmitted && ! empty($item->File_Berkas) ? (object) ['name' => $item->File_Berkas] : null;
                    $newItem->File_Berkas_Url =
                        $wasSubmitted && ! empty($item->File_Berkas)
                            ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                            : null;
                    $newItem->isRejected = $wasSubmitted && ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $wasSubmitted ? $item->Alasan_Reject : null;
                    $newItem->pica = null;

                    if ($wasSubmitted && $item->Flag_Pica === 'Y') {
                        $picaDataMonthly = $allPicasMonthly->get($item->Id_Update_Performance_Detail);
                        if ($picaDataMonthly) {
                            $newItem->pica = (object) [
                                'problem' => $picaDataMonthly->Identifikasi,
                                'action' => $picaDataMonthly->Koreksi_Aksi,
                                'targetDate' => FormatTanggalHelper::format(
                                    $picaDataMonthly->Tanggal_Pelaksanaan,
                                    true,
                                ),
                            ];
                        }
                    }

                    return $newItem;
                });

                $periodeMonthly = Carbon::parse($performanceHeaderMonthly->Periode);
                $periodeFormattedMonthly = 'Periode '.$periodeMonthly->format('M Y');
                $formattedHeaderMonthly = (object) [
                    'Nama_Lengkap' => $performanceHeaderMonthly->Nama_Lengkap,
                    'Nama_Jabatan' => $performanceHeaderMonthly->nama_jabatan,
                    'Nama_Departemen' => $performanceHeaderMonthly->nama_sub_divisi,
                    'Status_Pengajuan' => $performanceHeaderMonthly->Status_Pengajuan,
                    'Achievement' => (float) round($performanceHeaderMonthly->Total_Score, 1),
                    'Status' => $performanceHeaderMonthly->Status_Performance,
                    'Warna' => $performanceHeaderMonthly->Warna,
                    'Email' => $performanceHeaderMonthly->Email,
                    'Kontak' => $performanceHeaderMonthly->Kontak,
                    'Jam' => $performanceHeaderMonthly->Jam,
                    'Tanggal' => $performanceHeaderMonthly->Tanggal,
                    'Flag_Aktif' => $performanceHeaderMonthly->Flag_Aktif,
                    'Periode' => $periodeFormattedMonthly,
                    'Flag_Pindah' => 'Y',
                    'is_year' => false,
                ];

                return ResponseHelper::success(
                    ['header' => $formattedHeaderMonthly, 'details' => $transformedDetailsMonthly],
                    'Data KPI Detail Bulanan Ditemukan!',
                    200,
                );
            } else {
                $kpiParams = DB::table('KPI_Pengajuan_Detail as kpi_main')
                    ->join('KPI_Master_Satuan as kms', 'kpi_main.Id_Master_Satuan', '=', 'kms.Id_Master_Satuan')
                    ->where('kpi_main.Id_Pengajuan', $pengajuanId)
                    ->select(
                        'kpi_main.Id_Pengajuan_Detail',
                        'kpi_main.Nama_Parameter',
                        'kpi_main.Deskripsi',
                        'kpi_main.Bobot',
                        'kms.Simbol_Satuan',
                        'kpi_main.Id_Master_Formula',
                    )
                    ->get();

                $paramIds = $kpiParams->pluck('Id_Pengajuan_Detail');

                $allTargets = DB::table('KPI_Pengajuan_Det')
                    ->whereIn('Id_Pengajuan_Detail', $paramIds)
                    ->whereBetween('Periode', [$periodeTahun.'-01', $periodeAkhir])
                    ->groupBy('Id_Pengajuan_Detail')
                    ->select('Id_Pengajuan_Detail', DB::raw('SUM(ISNULL(Nilai_Target, 0)) as Total_Target'))
                    ->pluck('Total_Target', 'Id_Pengajuan_Detail');

                // Angka akumulasi dari SATU sumber (KpiAkumulasiYtdService) — sama
                // dengan daftar dashboard ini, Monitoring, homepage, dashboard staff,
                // dan `kpi:verify-ytd`. Parameter tanpa realisasi tidak ada di
                // rinciannya: tetap ditampilkan, tidak ikut total.
                $ytdSvc = app(KpiAkumulasiYtdService::class);
                // Batas 0 = belum ada bulan terjadwal → YTD kosong (periodeSampai
                // sendiri akan menjepit 0 menjadi Januari).
                $rincianYtd = $detailYtdEndMonth > 0
                    ? ($ytdSvc->rincianPerUser(
                        [$userId],
                        (int) $periodeTahun,
                        $ytdSvc->periodeSampai((int) $periodeTahun, $detailYtdEndMonth),
                    )[(string) $userId] ?? null)
                    : null;
                $rincianPerParam = collect($rincianYtd['parameter'] ?? [])->keyBy(fn ($p) => (string) $p['id']);

                $allPicas = DB::table('KPI_Update_Performance_Det as pica_det')
                    ->join(
                        'KPI_Update_Performance_Detail as kupd',
                        'pica_det.Id_Update_Performance_Detail',
                        '=',
                        'kupd.Id_Update_Performance_Detail',
                    )
                    ->join(
                        'KPI_Update_Performance as kup',
                        'kupd.Id_Update_Performance',
                        '=',
                        'kup.Id_Update_Performance',
                    )
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->where('kup.Id_User', $userId)
                    ->where('kupd.Flag_Pica', 'Y')
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeAkhir])
                    ->select(
                        'kpd.Id_Pengajuan_Detail',
                        'pica_det.Identifikasi',
                        'pica_det.Koreksi_Aksi',
                        'pica_det.Tanggal_Pelaksanaan',
                        'kup.Periode',
                    )
                    ->orderBy('kup.Periode')
                    ->get();

                $groupedPicas = $allPicas->groupBy('Id_Pengajuan_Detail');

                $allDataSources = DB::table('KPI_Update_Performance_Detail as kupd')
                    ->join(
                        'KPI_Update_Performance as kup',
                        'kupd.Id_Update_Performance',
                        '=',
                        'kup.Id_Update_Performance',
                    )
                    ->join('KPI_Pengajuan_Det as kpd', 'kupd.Id_Pengajuan_Det', '=', 'kpd.Id_Pengajuan_Det')
                    ->whereIn('kpd.Id_Pengajuan_Detail', $paramIds)
                    ->where('kup.Id_User', $userId)
                    ->where('kup.Status_Pengajuan', 'disetujui')
                    ->where('kup.Flag_Approval', 'Y')
                    ->where('kup.Flag_Verifikasi', 'Y')
                    ->whereNull('kup.Status')
                    // WAJIB. Tanpa ini header lama yang sudah digantikan revisi
                    // ikut terjumlah — penggantian hanya menyetel Flag_Aktif='T'
                    // sementara Status tetap NULL, jadi saringan Status saja
                    // melewatkannya. Jebakan yang sama sudah didokumentasikan di
                    // tiga tempat lain pada berkas ini.
                    ->where('kup.Flag_Aktif', 'Y')
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeAkhir])
                    ->whereNotNull('kupd.File_Berkas')
                    ->where('kupd.File_Berkas', '!=', '')
                    ->select('kpd.Id_Pengajuan_Detail', 'kupd.File_Berkas', 'kup.Periode')
                    ->orderBy('kup.Periode')
                    ->get();

                $groupedDataSources = $allDataSources->groupBy('Id_Pengajuan_Detail');

                $transformedDetailsYearly = $kpiParams->map(function ($item) use (
                    $allTargets,
                    $rincianPerParam,
                    $groupedPicas,
                    $groupedDataSources,
                    $getPerformanceLevel,
                    $formulas,
                ) {
                    $newItem = new \stdClass;
                    $paramId = $item->Id_Pengajuan_Detail;
                    $rincian = $rincianPerParam->get((string) $paramId);

                    if ($rincian) {
                        $target = $rincian['sum_target'];
                        $actual = $rincian['sum_actual'];
                        $achievementKPI = $rincian['achievement'];
                        $score = $rincian['score'];
                    } else {
                        // Tanpa realisasi di rentang ini: hanya untuk ditampilkan.
                        $target = $allTargets->get($paramId, 0);
                        $actual = 0;
                        $calculation = $this->calculateFormulaStream(
                            $actual,
                            $target,
                            $item->Bobot,
                            $item->Id_Master_Formula,
                            $formulas,
                        );
                        $achievementKPI = $calculation['achievement'];
                        $score = $calculation['score'];
                    }
                    $newItem->id = Hashids::connection('custom')->encode($item->Id_Pengajuan_Detail);
                    $newItem->parameter = $item->Nama_Parameter;
                    // Deskripsi parameter: dipakai kartu detail, dipotong dua baris di UI.
                    $newItem->deskripsi = trim((string) ($item->Deskripsi ?? '')) ?: null;
                    $newItem->Bobot = (float) $item->Bobot;
                    $newItem->targetValue = (float) $target;
                    $newItem->targetUnit = $item->Simbol_Satuan;
                    $newItem->actual = (float) $actual;
                    $newItem->achievement = (float) $achievementKPI;
                    $newItem->score = (float) $score;
                    $performanceYearlyParam = $getPerformanceLevel($newItem->achievement);
                    $newItem->statusKeterangan = $performanceYearlyParam->Keterangan;
                    $newItem->statusWarna = $performanceYearlyParam->Warna;
                    $newItem->is_input_period = true;
                    $sourcesForThisParam = $groupedDataSources->get($paramId);
                    if ($sourcesForThisParam && $sourcesForThisParam->isNotEmpty()) {
                        $newItem->dataSourceFile = $sourcesForThisParam
                            ->map(
                                fn ($s) => (object) [
                                    'name' => $s->File_Berkas,
                                    'month' => Carbon::parse($s->Periode)->format('M'),
                                    'periode' => Carbon::parse($s->Periode)->format('M Y'),
                                ],
                            )
                            ->all();
                        $newItem->File_Berkas_Url = $sourcesForThisParam
                            ->map(
                                fn ($s) => (object) [
                                    'url' => Storage::disk('gcs')->temporaryUrl($s->File_Berkas, now()->addMinutes(20)),
                                    'month' => Carbon::parse($s->Periode)->format('M'),
                                    'periode' => Carbon::parse($s->Periode)->format('M Y'),
                                ],
                            )
                            ->filter()
                            ->all();
                    } else {
                        $newItem->dataSourceFile = [];
                        $newItem->File_Berkas_Url = [];
                    }
                    $newItem->isRejected = false;
                    $newItem->alasan_penolakan = null;
                    $picasForThisParam = $groupedPicas->get($paramId);
                    if ($picasForThisParam && $picasForThisParam->isNotEmpty()) {
                        $newItem->pica = $picasForThisParam
                            ->map(
                                fn ($p) => (object) [
                                    'problem' => $p->Identifikasi,
                                    'action' => $p->Koreksi_Aksi,
                                    'targetDate' => FormatTanggalHelper::format($p->Tanggal_Pelaksanaan, true),
                                    'periode' => Carbon::parse($p->Periode)->format('M Y'),
                                ],
                            )
                            ->all();
                    } else {
                        $newItem->pica = null;
                    }

                    return $newItem;
                });

                // Total dari sumber yang sama dengan rinciannya. Tanpa realisasi sama
                // sekali: 0, bukan 50 hasil menjepit nol.
                $roundedTotalScore = (float) round((float) ($rincianYtd['skor'] ?? 0), 1);
                $yearlyPerformanceOverall = $getPerformanceLevel($roundedTotalScore);

                $lastMonthlyHeader = DB::table('KPI_Update_Performance as kup')
                    ->join('Karyawan as k', 'kup.Id_User', '=', 'k.UserID_Web')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                        'k.Id_Level_Jabatan',
                        '=',
                        'vgsglj.Id_Level_Jabatan',
                    )
                    ->join('View_Divisi_Sub_Divisi as vdss', 'k.ID_Divisi_Sub_Divisi', '=', 'vdss.ID_Divisi_Sub_Divisi')
                    ->where('kup.Id_User', $userId)
                    ->whereBetween('kup.Periode', [$periodeTahun.'-01', $periodeAkhir])
                    ->orderBy('kup.Periode', 'desc')
                    ->select(
                        'vgsglj.nama_jabatan',
                        'vdss.nama_sub_divisi',
                        'k.Email',
                        'k.HP as Kontak',
                        'kup.Jam',
                        'kup.Tanggal',
                        'kup.Flag_Aktif',
                    )
                    ->first();

                $formattedHeaderYearly = (object) [
                    'Nama_Lengkap' => $performanceHeaderRef->Nama_Lengkap,
                    'Nama_Jabatan' => $lastMonthlyHeader->nama_jabatan ?? 'N/A',
                    'Nama_Departemen' => $lastMonthlyHeader->nama_sub_divisi ?? 'N/A',
                    'Status_Pengajuan' => 'disetujui',
                    'Achievement' => $roundedTotalScore,
                    'Status' => $yearlyPerformanceOverall->Keterangan,
                    'Warna' => $yearlyPerformanceOverall->Warna,
                    'Email' => $lastMonthlyHeader->Email ?? 'N/A',
                    'Kontak' => $lastMonthlyHeader->Kontak ?? 'N/A',
                    'Jam' => $lastMonthlyHeader->Jam ?? null,
                    'Tanggal' => $lastMonthlyHeader->Tanggal ?? null,
                    'Flag_Aktif' => $lastMonthlyHeader->Flag_Aktif ?? 'Y',
                    'Periode' => ($isYtd ? 'YTD ' : 'Full Year ').$periodeTahun,
                    'Flag_Pindah' => 'Y',
                    'is_year' => true,
                    'is_ytd' => $isYtd,
                ];

                return ResponseHelper::success(
                    ['header' => $formattedHeaderYearly, 'details' => $transformedDetailsYearly],
                    'Data KPI Detail Tahunan Ditemukan!',
                    200,
                );
            }
        } catch (\Exception $e) {
            Log::channel('RecentKpiDetail')->error('Error in recentKpiUserDetail: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'id_pengajuan' => $id_pengajuan,
                'id_update' => $id_update_performance,
                'isyear' => $isyear,
            ]);

            return ResponseHelper::error('Terjadi Kesalahan Pada Server Saat Mengambil Detail KPI.', 500);
        }
    }
}
