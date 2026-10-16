<?php

namespace App\Http\Controllers\LMS\Training\Scheduling;

use App\Helpers\ResponseHelper;
use App\Helpers\SignatureHelper;
use App\Http\Controllers\Controller;
use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use App\Services\LMS\TrainingScheduleQueryService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxDispatcher;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class TrainingSchedulingController extends Controller
{
    private $domain;

    public function __construct(
        private TrainingScheduleQueryService $trainingScheduleQueryService,
        private TaskOutboxService $taskOutboxService,
        private TaskOutboxDispatcher $taskOutboxDispatcher,
    ) {
        $this->domain = rtrim(config('services.frans.hclearn_api.domain'), '/');
    }

    public function index()
    {
        return inertia('LMS/Training/Scheduling/TrainingSchedulingPage');
    }

    public function getDataKaryawan(Request $request)
    {
        try {
            $filter = $request->get('filter', 'Semua');

            // 1. Tangkap array hash dari frontend
            $filterLevelsHash = $request->input('filter_levels', []);

            // 2. Lakukan perulangan untuk men-decode setiap elemen array
            $decodedLevels = [];
            if (is_array($filterLevelsHash) && ! empty($filterLevelsHash)) {
                foreach ($filterLevelsHash as $hash) {
                    $decoded = Hashids::connection('custom')->decode($hash);
                    if (! empty($decoded)) {
                        $decodedLevels[] = $decoded[0]; // Ambil nilai integer aslinya
                    }
                }
            }

            $idMasterPaketHash = $request->get('id_master_paket');
            $idMasterPaket = null;
            if ($idMasterPaketHash) {
                $decoded = Hashids::connection('custom')->decode($idMasterPaketHash);
                $idMasterPaket = ! empty($decoded) ? $decoded[0] : 0;
            }

            // Filter lokasi kerja (Cabang_Untuk_Gaji), bisa multi-pilih.
            $filterLokasi = $request->input('filter_lokasi', []);
            $filterLokasi = is_array($filterLokasi)
                ? collect($filterLokasi)->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all()
                : [];

            $getData = DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vgsglj.ID_Level_Jabatan',
                )
                ->leftJoin('N_LMS_Penjadwalan as lp', function ($join) use ($idMasterPaket) {
                    $join
                        ->on('k.Kode_Karyawan', '=', 'lp.Kode_Karyawan')
                        ->where('lp.Id_Master_Paket', '=', $idMasterPaket);
                })
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'k.HP',
                    'k.Cabang_Untuk_Gaji as Lokasi',
                    'vdsd.nama_divisi as Divisi',
                    'vdsd.nama_sub_divisi as Departemen',
                    'vgsglj.nama_level as Level',
                    'vgsglj.nama_jabatan as Jabatan',
                    DB::raw('COUNT(lp.Id_Penjadwalan) as Total_Assign'),
                )
                ->where('k.Aktif', 'Y')
                ->when($filter === 'Karyawan', function ($query) {
                    return $query->where('vgsglj.Id_Level', '!=', '21');
                })
                ->when($filter === 'Phl', function ($query) {
                    return $query->where('vgsglj.Id_Level', '=', '21');
                })
                // 3. Gunakan array yang sudah berhasil di-decode ke dalam whereIn
                ->when(! empty($decodedLevels), function ($query) use ($decodedLevels) {
                    return $query->whereIn('vgsglj.Id_Level', $decodedLevels);
                })
                ->when(! empty($filterLokasi), function ($query) use ($filterLokasi) {
                    return $query->whereIn('k.Cabang_Untuk_Gaji', $filterLokasi);
                })
                ->groupBy(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'k.HP',
                    'k.Cabang_Untuk_Gaji',
                    'vdsd.nama_divisi',
                    'vdsd.nama_sub_divisi',
                    'vgsglj.nama_level',
                    'vgsglj.nama_jabatan',
                )
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan !', 404);
            }

            return ResponseHelper::success($getData, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    /**
     * Daftar lokasi kerja (Cabang_Untuk_Gaji) yang benar-benar punya penjadwalan,
     * dipakai sebagai opsi filter "lokasi kerja" di halaman penjadwalan.
     */
    public function getLokasiKerja()
    {
        try {
            $getData = DB::table('N_LMS_Penjadwalan as p')
                ->join('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->whereNotNull('k.Cabang_Untuk_Gaji')
                ->where('k.Cabang_Untuk_Gaji', '!=', '')
                ->select('k.Cabang_Untuk_Gaji as Lokasi')
                ->distinct()
                ->orderBy('k.Cabang_Untuk_Gaji', 'asc')
                ->get();

            return ResponseHelper::success($getData, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error('Error getLokasiKerja: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    /**
     * Daftar lokasi kerja (Cabang_Untuk_Gaji) dari seluruh karyawan aktif, dipakai
     * sebagai opsi filter lokasi di modal "Tambah Penjadwalan" (memilih peserta).
     * Berbeda dari getLokasiKerja yang hanya lokasi yang sudah punya penjadwalan.
     */
    public function getLokasiKaryawan()
    {
        try {
            $getData = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->whereNotNull('Cabang_Untuk_Gaji')
                ->where('Cabang_Untuk_Gaji', '!=', '')
                ->select('Cabang_Untuk_Gaji as Lokasi')
                ->distinct()
                ->orderBy('Cabang_Untuk_Gaji', 'asc')
                ->get();

            return ResponseHelper::success($getData, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error('Error getLokasiKaryawan: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    public function getDataKategoriUjian(Request $request)
    {
        $appId = Auth::user()->User_Key;
        $kodeKaryawan = Auth::user()->Kode_Users;
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $endpoint = 'api/v1/penjadwalan/training/options';
        $url = rtrim($this->domain, '/').'/'.ltrim($endpoint, '/');

        try {
            $auth = SignatureHelper::get($endpoint, $appId, $kodeKaryawan);
            $response = Http::timeout((int) config('services.lms_third_party.timeout', 8))
                ->connectTimeout((int) config('services.lms_third_party.connect_timeout', 5))
                ->withHeaders($auth['headers'])->get($url);

            // dd($response, $auth, $url);
            if (! $response->successful()) {
                $statusCode = $response->status();
                $contentType = $response->header('Content-Type');

                if (str_contains($contentType, 'application/json')) {
                    $apiData = $response->json();
                    $messageFromApi =
                        $apiData['message'] ?? ($apiData['result'] ?? 'Gagal mengambil data dari API tujuan.');

                    return ResponseHelper::error($messageFromApi, $statusCode);
                }

                return response($response->body(), $statusCode)->header('Content-Type', 'text/html');
            }
            $apiData = $response->json();
            $data = $apiData['result'] ?? ($apiData['data'] ?? $apiData);

            if (! empty($search) && is_array($data)) {
                $data = array_values(array_filter($data, function ($item) use ($search) {
                    $namaUjian = is_array($item) ? ($item['Nama_Ujian'] ?? '') : ($item->Nama_Ujian ?? '');

                    return stripos($namaUjian, $search) !== false;
                }));
            }

            return ResponseHelper::success($data, 'Data Ditemukan !');
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    public function getDataMasterRumus()
    {
        try {
            $rumusData = DB::table('N_LMS_Master_Rumus')->where('Flag_Aktif', 'Y')->get();

            if ($rumusData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan !', 404);
            }
            $aktivitas = DB::table('N_LMS_Master_Aktivitas')->pluck('Nama_Aktivitas', 'Kode_Aktivitas');
            $formattedData = $rumusData->map(function ($item) use ($aktivitas) {
                preg_match_all('/\[(.*?)\]/', $item->Rumus, $matches);
                $kodeAktifDalamRumus = $matches[1] ?? [];
                $rumusHumanReadable = $item->Rumus;
                foreach ($kodeAktifDalamRumus as $kode) {
                    if (isset($aktivitas[$kode])) {
                        $rumusHumanReadable = str_replace("[$kode]", $aktivitas[$kode], $rumusHumanReadable);
                    }
                }

                return [
                    'Id_Master_Rumus' => Hashids::connection('custom')->encode($item->Id_Master_Rumus),
                    'Kode_Rumus' => $item->Kode_Rumus,
                    'Nama_Formula' => $item->Nama_Formula,
                    'RumusTampil' => $rumusHumanReadable,
                    'Deskripsi' => $item->Deskripsi,
                    'DaftarKodeDibutuhkan' => $kodeAktifDalamRumus,
                ];
            });

            return ResponseHelper::success($formattedData, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error('Error getMasterRumus: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    public function getDataMasterLevel()
    {
        try {
            $levelData = DB::table('HRIS_Level')->orderBy('Level_Hierarchy', 'desc')->get();

            if ($levelData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan !', 404);
            }

            $formattedLevelData = $levelData->map(function ($level) {
                return [
                    'Id_Level' => Hashids::connection('custom')->encode($level->ID_Level),
                    'Keterangan' => $level->Keterangan,
                ];
            });

            return ResponseHelper::success($formattedLevelData, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error('Error getDataMasterLevel: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    public function getMasterTraining(Request $request)
    {
        try {
            $search = trim((string) $request->query('search', $request->query('q', '')));
            $page = max(1, (int) $request->query('page', 1));
            $limit = max(1, min(100, (int) $request->query('limit', 20)));
            $isPaginated = $request->has('page') || $request->has('search') || $request->has('limit');

            $query = DB::table('N_LMS_Master_Paket')->where('Flag_Aktif', 'Y');

            if ($search !== '') {
                $query->where('Nama_Paket', 'LIKE', '%'.$search.'%');
            }

            $total = (clone $query)->count();

            if ($isPaginated) {
                $paketList = $query->orderBy('Nama_Paket', 'asc')
                    ->skip(($page - 1) * $limit)
                    ->take($limit)
                    ->get();
            } else {
                $paketList = $query->orderBy('Nama_Paket', 'asc')->get();
            }

            // Jika ada selected_id yang diminta (misal saat edit / reload awal), pastikan row tersebut ada di list
            $selectedIdRaw = $request->query('selected_id');
            if (! empty($selectedIdRaw)) {
                $decoded = Hashids::connection('custom')->decode($selectedIdRaw);
                $selectedId = $decoded[0] ?? null;
                if ($selectedId && ! $paketList->contains('Id_Master_Paket', $selectedId)) {
                    $selectedRow = DB::table('N_LMS_Master_Paket')
                        ->where('Id_Master_Paket', $selectedId)
                        ->first();
                    if ($selectedRow) {
                        $paketList->prepend($selectedRow);
                    }
                }
            }

            $paketIds = $paketList->pluck('Id_Master_Paket')->filter()->toArray();

            $paketDetailList = empty($paketIds) ? collect() : DB::table('N_LMS_Master_Paket_Detail as pd')
                ->join('N_LMS_Materi as m', 'pd.Id_Master_Training', '=', 'm.Id_Materi')
                ->select('pd.Id_Master_Paket_Detail', 'pd.Id_Master_Paket', 'm.Id_Materi', 'm.Judul_Training')
                ->whereIn('pd.Id_Master_Paket', $paketIds)
                ->where('m.Flag_Aktif', 'Y')
                ->orderBy('pd.Urutan_Paket_Detail', 'asc')
                ->get();

            $materiIds = $paketDetailList->pluck('Id_Materi')->filter()->unique()->toArray();

            $materiDetList = empty($materiIds) ? collect() : DB::table('N_LMS_Materi_Det as det')
                ->join('N_LMS_Materi_Detail as sub', 'det.Id_Materi_Detail', '=', 'sub.Id_Materi_Detail')
                ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
                ->leftJoin('N_LMS_Master_Konten as kon', 'det.Id_Master_Konten', '=', 'kon.Id_Master_Konten')
                ->leftJoin('N_LMS_Kategori_Konten as kat', 'kon.Id_Kategori_Konten', '=', 'kat.Id_Kategori_Konten')
                ->select(
                    'det.Id_Materi',
                    'sub.Id_Materi_Detail',
                    'det.Id_Materi_Det',
                    'sub.Nama_Sub',
                    'det.Id_Master_Aktivitas',
                    'det.Keterangan_Level as Nama_Aktivitas',
                    'act.Kode_Aktivitas',
                    'act.Flag_Third_Party',
                    'det.Id_Master_Konten',
                    'kon.Judul_Konten',
                    'kon.Tipe_Konten',
                    'kat.Nama_Kategori',
                )
                ->whereIn('det.Id_Materi', $materiIds)
                ->where('det.Flag_Aktif', 'Y')
                ->where('sub.Flag_Aktif', 'Y')
                ->orderBy('sub.Urutan_Sub_Training', 'asc')
                ->orderBy('det.Urutan_Materi', 'asc')
                ->get();

            $activitiesMap = [];
            foreach ($materiDetList as $det) {
                if (! isset($activitiesMap[$det->Id_Materi][$det->Id_Materi_Detail])) {
                    $activitiesMap[$det->Id_Materi][$det->Id_Materi_Detail] = [
                        'Id_Materi_Detail' => Hashids::connection('custom')->encode($det->Id_Materi_Detail),
                        'Nama_Sub' => $det->Nama_Sub,
                        'Aktivitas' => [],
                    ];
                }

                $kodeAktivitas = $det->Kode_Aktivitas ?? strtoupper(substr($det->Nama_Aktivitas, 0, 3));

                $aktivitasItem = [
                    'Id_Master_Aktivitas' => $det->Id_Master_Aktivitas
                        ? Hashids::connection('custom')->encode($det->Id_Master_Aktivitas)
                        : null,
                    'Id_Materi_Det' => Hashids::connection('custom')->encode($det->Id_Materi_Det),
                    'Kode_Aktivitas' => $kodeAktivitas,
                    'Nama_Aktivitas' => $det->Nama_Aktivitas,
                    'Flag_Third_Party' => $det->Flag_Third_Party ?? 'T',
                ];

                if ($kodeAktivitas === 'MDL') {
                    if ($det->Id_Master_Konten) {
                        $aktivitasItem['Details'] = [
                            [
                                'Id_Materi_Det' => Hashids::connection('custom')->encode($det->Id_Materi_Det),
                                'Nama_Kategori' => $det->Nama_Kategori,
                                'Judul_Konten' => $det->Judul_Konten,
                                'Tipe_Konten' => $det->Tipe_Konten,
                            ],
                        ];
                    } else {
                        $aktivitasItem['Details'] = [];
                    }
                }

                $activitiesMap[$det->Id_Materi][$det->Id_Materi_Detail]['Aktivitas'][] = $aktivitasItem;
            }

            $materiMap = [];
            foreach ($paketDetailList as $pd) {
                if (! isset($materiMap[$pd->Id_Master_Paket])) {
                    $materiMap[$pd->Id_Master_Paket] = [];
                }

                $materiMap[$pd->Id_Master_Paket][] = [
                    'Id_Master_Paket_Detail' => Hashids::connection('custom')->encode($pd->Id_Master_Paket_Detail),
                    'Id_Materi' => Hashids::connection('custom')->encode($pd->Id_Materi),
                    'Judul_Training' => $pd->Judul_Training,
                    'Details' => isset($activitiesMap[$pd->Id_Materi])
                        ? array_values($activitiesMap[$pd->Id_Materi])
                        : [],
                ];
            }

            $formattedPaket = [];
            foreach ($paketList as $paket) {
                $formattedPaket[] = [
                    'Id_Master_Paket' => Hashids::connection('custom')->encode($paket->Id_Master_Paket),
                    'Nama_Paket' => $paket->Nama_Paket,
                    'Deskripsi' => $paket->Deskripsi,
                    'Trainings' => $materiMap[$paket->Id_Master_Paket] ?? [],
                ];
            }

            if ($isPaginated) {
                return ResponseHelper::success([
                    'items' => $formattedPaket,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'has_more' => ($page * $limit) < $total,
                ], 'Data Ditemukan !', 200);
            }

            return ResponseHelper::success($formattedPaket, 'Data Ditemukan !', 200);
        } catch (\Exception $e) {
            Log::error('Error getMasterTraining: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Pada Sistem', 500);
        }
    }

    /**
     * Terapkan filter daftar penjadwalan (pencarian, status akses, lokasi kerja, paket,
     * module/materi, rentang tanggal) ke query builder. Dipakai bersama oleh
     * getDataCurrent dan getAllIds agar tetap sinkron.
     * Query builder harus sudah punya join ke N_LMS_Master_Paket (mp) dan Karyawan (k1).
     */
    private function applyPenjadwalanFilters($query, Request $request): void
    {
        $search = $request->query('q', '');
        $statusFilter = $request->query('status', 'all');
        $materiFilter = $request->query('materi', '');
        $lokasiFilter = $request->query('lokasi', '');
        $moduleFilter = $request->query('module', '');
        $dateMode = $request->query('date_mode', 'enroll');
        $dateStart = $request->query('date_start', '');
        $dateEnd = $request->query('date_end', '');

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('k1.Nama', 'LIKE', '%'.$search.'%')
                    ->orWhere('p.Kode_Karyawan', 'LIKE', '%'.$search.'%')
                    ->orWhere('mp.Nama_Paket', 'LIKE', '%'.$search.'%')
                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('N_LMS_Master_Paket_Detail as mpd')
                            ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
                            ->whereColumn('mpd.Id_Master_Paket', 'p.Id_Master_Paket')
                            ->where('m.Judul_Training', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        if ($statusFilter === 'Y') {
            $query->where('p.Flag_Cabut_Akses', 'Y');
        } elseif ($statusFilter === 'T') {
            $query->where(function ($q) {
                $q->where('p.Flag_Cabut_Akses', 'T')->orWhereNull('p.Flag_Cabut_Akses');
            });
        }

        if (! empty($materiFilter)) {
            $materiArray = explode(',', $materiFilter);

            $decodedMateri = collect($materiArray)
                ->map(function ($hash) {
                    return Hashids::connection('custom')->decode($hash)[0] ?? null;
                })
                ->filter()
                ->values()
                ->toArray();

            if (! empty($decodedMateri)) {
                $query->whereIn('p.Id_Master_Paket', $decodedMateri);
            }
        }

        // Lokasi kerja difilter langsung lewat predikat pada tabel Karyawan (k1) yang
        // memang sudah di-join di getDataCurrent maupun getAllIds. Tidak di-resolve jadi
        // daftar Kode_Karyawan supaya tidak menghasilkan IN list ribuan nilai saat
        // karyawan banyak; predikat pada tabel dimensi kecil jauh lebih murah.
        if (! empty($lokasiFilter)) {
            $lokasiList = collect(explode(',', $lokasiFilter))
                ->map(function ($lokasi) {
                    return trim($lokasi);
                })
                ->filter()
                ->values()
                ->toArray();

            if (! empty($lokasiList)) {
                $query->whereIn('k1.Cabang_Untuk_Gaji', $lokasiList);
            }
        }

        // Module di-resolve dulu menjadi daftar paket lewat satu query ke tabel master,
        // supaya query listing tetap datar (query ini dijalankan 2x: count + ambil data).
        // Sengaja bukan join: satu paket punya banyak materi, join akan menduplikasi
        // baris penjadwalan sehingga total dan paginasi ikut rusak.
        if (! empty($moduleFilter)) {
            $decodedModule = collect(explode(',', $moduleFilter))
                ->map(function ($hash) {
                    return Hashids::connection('custom')->decode($hash)[0] ?? null;
                })
                ->filter()
                ->values()
                ->toArray();

            if (! empty($decodedModule)) {
                $paketIds = DB::table('N_LMS_Master_Paket_Detail')
                    ->whereIn('Id_Master_Training', $decodedModule)
                    ->distinct()
                    ->pluck('Id_Master_Paket')
                    ->toArray();

                // whereIn dengan array kosong akan mengabaikan filter dan menampilkan
                // semua data, sedangkan yang benar adalah hasil kosong.
                $query->whereIn('p.Id_Master_Paket', ! empty($paketIds) ? $paketIds : [-1]);
            }
        }

        if (! empty($dateStart) && ! empty($dateEnd)) {
            $dateColumn = $dateMode === 'created' ? 'p.Created_At' : 'p.Waktu_Enroll';
            $query->whereDate($dateColumn, '>=', $dateStart)
                ->whereDate($dateColumn, '<=', $dateEnd);
        }
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;

            $masterAktivitas = DB::table('N_LMS_Master_Aktivitas')
                ->pluck('Nama_Aktivitas', 'Kode_Aktivitas')
                ->toArray();

            $query = DB::table('N_LMS_Penjadwalan as p')
                ->leftJoin('N_LMS_Master_Paket as mp', 'p.Id_Master_Paket', '=', 'mp.Id_Master_Paket')
                ->leftJoin('N_LMS_Master_Rumus as mr', 'p.Id_Master_Rumus', '=', 'mr.Id_Master_Rumus')
                ->leftJoin('Karyawan as k1', 'p.Kode_Karyawan', '=', 'k1.Kode_Karyawan')
                ->leftJoin('Karyawan as k2', 'p.Asign_By', '=', 'k2.UserID_Web');

            $this->applyPenjadwalanFilters($query, $request);

            $total = $query->count();

            $getData = $query
                ->select(
                    'p.Id_Penjadwalan',
                    'p.Kode_Karyawan',
                    'k1.Nama as Nama_Karyawan',
                    'p.Waktu_Enroll',
                    'p.Flag_Selesai',
                    'p.Flag_Cabut_Akses',
                    'p.Asign_By',
                    'k2.Nama as Nama_Assign_By',
                    'p.Created_At',
                    'p.Id_Master_Paket',
                    'p.Id_Master_Rumus',
                    'mp.Nama_Paket',
                    'mr.Nama_Formula',
                    'mr.Rumus',
                    'mr.Deskripsi as Deskripsi_Formula',
                )
                ->orderBy('p.Id_Penjadwalan', 'DESC')
                ->offset($offset)
                ->limit($limit)
                ->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $idPenjadwalans = $getData->pluck('Id_Penjadwalan')->toArray();
            $idPakets = $getData->pluck('Id_Master_Paket')->filter()->unique()->toArray();

            $trainingList = DB::table('N_LMS_Master_Paket_Detail as mpd')
                ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
                ->select(
                    'mpd.Id_Master_Paket_Detail',
                    'mpd.Id_Master_Paket',
                    'm.Id_Materi',
                    'm.Judul_Training',
                    'mpd.Urutan_Paket_Detail',
                )
                ->whereIn('mpd.Id_Master_Paket', $idPakets)
                ->orderBy('mpd.Urutan_Paket_Detail', 'ASC')
                ->get()
                ->groupBy('Id_Master_Paket');

            $details = DB::table('N_LMS_Penjadwalan_Detail as pd')
                ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
                ->select(
                    'pd.Id_Penjadwalan',
                    'pd.Id_Penjadwalan_Detail',
                    'pd.Id_Master_Paket_Detail',
                    'md.Id_Materi_Detail',
                    'md.Id_Master_Training',
                    'md.Nama_Sub',
                    'md.Urutan_Sub_Training',
                )
                ->whereIn('pd.Id_Penjadwalan', $idPenjadwalans)
                ->get();

            $idPenjadwalanDetails = $details->pluck('Id_Penjadwalan_Detail')->toArray();

            $subDetailsQuery = DB::table('N_LMS_Penjadwalan_Det as pdet')
                ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                ->leftJoin('N_LMS_Materi_Det as mdet', 'pdet.Id_Materi_Det', '=', 'mdet.Id_Materi_Det')
                ->leftJoin('N_LMS_Master_Aktivitas as ma', 'mdet.Id_Master_Aktivitas', '=', 'ma.Id_Master_Aktivitas')
                ->leftJoin('N_LMS_Penjadwalan_Integrasi as pi', 'pdet.Id_Penjadwalan_Det', '=', 'pi.Id_Penjadwalan_Det')
                ->leftJoin('N_LMS_Master_Konten as mk', 'mdet.Id_Master_Konten', '=', 'mk.Id_Master_Konten')
                ->select(
                    'pdet.Id_Penjadwalan_Detail',
                    'pdet.Id_Penjadwalan_Det',
                    'pdet.Id_Materi_Det',
                    'pdet.Waktu_Mulai_Akses',
                    'pdet.Flag_Third_Party',
                    'ma.Nama_Aktivitas',
                    'ma.Kode_Aktivitas',
                    'mk.Judul_Konten as Nama_Modul',
                    'mk.Tipe_Konten',
                    'pi.Waktu_Mulai as Third_Waktu_Mulai',
                    'pi.Waktu_Akhir as Third_Waktu_Akhir',
                    'pi.Payload_Json',
                    'pi.Id_Master_Ujian',
                    'pi.Id_Ujian_Token', // <-- [DITAMBAHKAN] Field Id Ujian Token
                )
                ->whereIn('pdet.Id_Penjadwalan_Detail', $idPenjadwalanDetails)
                ->get();

            $subDetails = collect();
            foreach ($subDetailsQuery as $row) {
                if (! $subDetails->has($row->Id_Penjadwalan_Detail)) {
                    $subDetails->put($row->Id_Penjadwalan_Detail, collect());
                }

                $existingAct = $subDetails
                    ->get($row->Id_Penjadwalan_Detail)
                    ->firstWhere('Id_Penjadwalan_Det', $row->Id_Penjadwalan_Det);

                if (! $existingAct) {
                    $newAct = (object) [
                        'Id_Penjadwalan_Det' => $row->Id_Penjadwalan_Det,
                        'Id_Materi_Det' => Hashids::connection('custom')->encode($row->Id_Materi_Det),
                        'Nama_Aktivitas' => $row->Nama_Aktivitas,
                        'Kode_Aktivitas' => $row->Kode_Aktivitas,
                        'Waktu_Mulai_Akses' => $row->Waktu_Mulai_Akses,
                        'Flag_Third_Party' => $row->Flag_Third_Party,
                        'Third_Waktu_Mulai' => $row->Third_Waktu_Mulai,
                        'Third_Waktu_Akhir' => $row->Third_Waktu_Akhir,
                        'Payload_Json' => $row->Payload_Json,
                        'Id_Master_Ujian' => $row->Id_Master_Ujian,
                        'Id_Ujian_Token' => $row->Id_Ujian_Token, // <-- [DITAMBAHKAN]
                        'Details' => [],
                    ];

                    if ($row->Kode_Aktivitas === 'MDL' && $row->Nama_Modul) {
                        $newAct->Details[] = [
                            'Id_Materi_Det' => Hashids::connection('custom')->encode($row->Id_Materi_Det),
                            'Nama_Modul' => $row->Nama_Modul,
                            'Tipe_Konten' => $row->Tipe_Konten,
                        ];
                    }
                    $subDetails->get($row->Id_Penjadwalan_Detail)->push($newAct);
                } else {
                    if ($row->Kode_Aktivitas === 'MDL' && $row->Nama_Modul) {
                        $existingAct->Details[] = [
                            'Id_Materi_Det' => Hashids::connection('custom')->encode($row->Id_Materi_Det),
                            'Nama_Modul' => $row->Nama_Modul,
                            'Tipe_Konten' => $row->Tipe_Konten,
                        ];
                    }
                }
            }

            $encodedData = $getData->map(function ($item) use ($trainingList, $details, $subDetails, $masterAktivitas) {
                $rumusTerjemahan = $item->Rumus ?? '-';
                if ($rumusTerjemahan !== '-') {
                    foreach ($masterAktivitas as $kode => $nama) {
                        $rumusTerjemahan = str_replace('['.$kode.']', $nama, $rumusTerjemahan);
                    }
                }

                $currentTraining = $trainingList
                    ->get($item->Id_Master_Paket, collect())
                    ->map(function ($tr) use ($item, $details, $subDetails) {
                        $materiDetail = $details
                            ->where('Id_Penjadwalan', $item->Id_Penjadwalan)
                            ->where('Id_Master_Paket_Detail', $tr->Id_Master_Paket_Detail)
                            ->map(function ($det) use ($subDetails) {
                                $materiDet = $subDetails
                                    ->get($det->Id_Penjadwalan_Detail, collect())
                                    ->map(function ($sub) {
                                        $thirdPartyData = null;
                                        if ($sub->Flag_Third_Party === 'Y' && ! empty($sub->Payload_Json)) {
                                            $payload = json_decode($sub->Payload_Json, true);
                                            $thirdPartyData = [
                                                'Id_Master_Ujian' => $sub->Id_Master_Ujian ?? null,
                                                'Id_Ujian_Token' => $sub->Id_Ujian_Token ?? null, // <-- [DITAMBAHKAN]
                                                'Token' => $payload['token'] ?? null,
                                                'Otp' => $payload['otp'] ?? null,
                                                'Waktu_Mulai' => $sub->Third_Waktu_Mulai,
                                                'Waktu_Akhir' => $sub->Third_Waktu_Akhir,
                                                'Link' => $payload['Link_Ujian'] ?? null,
                                            ];
                                        }

                                        return [
                                            'Id_Penjadwalan_Det' => Hashids::connection('custom')->encode(
                                                $sub->Id_Penjadwalan_Det,
                                            ),
                                            'Id_Materi_Det' => $sub->Id_Materi_Det,
                                            'Nama_Aktivitas' => $sub->Nama_Aktivitas,
                                            'Kode_Aktivitas' => $sub->Kode_Aktivitas,
                                            'Details' => $sub->Details,
                                            'Waktu_Mulai_Akses' => $sub->Waktu_Mulai_Akses,
                                            'Flag_Third_Party' => $sub->Flag_Third_Party,
                                            'third_party_info' => $thirdPartyData,
                                        ];
                                    });

                                return [
                                    'Nama_Sub' => $det->Nama_Sub,
                                    'Urutan' => $det->Urutan_Sub_Training,
                                    'materi_det' => $materiDet->values()->toArray(),
                                ];
                            });

                        return [
                            'Id_Master_Paket_Detail' => Hashids::connection('custom')->encode(
                                $tr->Id_Master_Paket_Detail,
                            ),
                            'Id_Materi' => Hashids::connection('custom')->encode($tr->Id_Materi),
                            'Judul_Training' => $tr->Judul_Training,
                            'materi_detail' => $materiDetail->values()->toArray(),
                        ];
                    });

                return [
                    'Id_Penjadwalan' => Hashids::connection('custom')->encode($item->Id_Penjadwalan),
                    'Id_Master_Paket' => Hashids::connection('custom')->encode($item->Id_Master_Paket),
                    'Id_Master_Rumus' => $item->Id_Master_Rumus
                        ? Hashids::connection('custom')->encode($item->Id_Master_Rumus)
                        : null,
                    'Kode_Karyawan' => $item->Kode_Karyawan,
                    'Nama_Karyawan' => $item->Nama_Karyawan ?? '-',
                    'Nama_Paket' => $item->Nama_Paket ?? '-',
                    'Nama_Formula' => $item->Nama_Formula ?? '-',
                    'Rumus' => $rumusTerjemahan,
                    'Deskripsi_Formula' => $item->Deskripsi_Formula ?? '-',
                    'Waktu_Enroll' => $item->Waktu_Enroll,
                    'Flag_Selesai' => $item->Flag_Selesai,
                    'Flag_Cabut_Akses' => $item->Flag_Cabut_Akses,
                    'Created_At' => $item->Created_At,
                    'Asign_By' => $item->Nama_Assign_By ?? '-',
                    'Training_List' => $currentTraining->values()->toArray(),
                ];
            });

            return ResponseHelper::successWithPaginationV2(
                $encodedData,
                $page,
                $limit,
                $total,
                'Data Ditemukan !',
                200,
            );
        } catch (\Exception $e) {
            Log::channel('PenjadwalanController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan: '.$e->getMessage(), 500);
        }
    }

    public function getAllIds(Request $request)
    {
        try {
            $query = DB::table('N_LMS_Penjadwalan as p')
                ->leftJoin('N_LMS_Master_Paket as mp', 'p.Id_Master_Paket', '=', 'mp.Id_Master_Paket')
                ->leftJoin('Karyawan as k1', 'p.Kode_Karyawan', '=', 'k1.Kode_Karyawan');

            $this->applyPenjadwalanFilters($query, $request);

            $ids = $query
                ->pluck('p.Id_Penjadwalan')
                ->map(function ($id) {
                    return Hashids::connection('custom')->encode($id);
                })
                ->toArray();

            return response()->json([
                'success' => true,
                'result' => $ids,
            ]);
        } catch (\Exception $e) {
            Log::channel('PenjadwalanController')->error('ERROR GETALLIDS: '.$e->getMessage());

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Terjadi Kesalahan: '.$e->getMessage(),
                ],
                500,
            );
        }
    }

    public function store(Request $request)
    {
        $appId = Auth::user()->User_Key;
        $kodeKaryawanAuth = Auth::user()->Kode_Users ?? 'SYSTEM';
        $idUsersAuth = Auth::user()->Id_Users ?? null;
        $userAgent = $request->User_Agent;
        $domain = $this->domain;

        $validated = $request->validate([
            'karyawan' => 'required|array',
            'id_training' => 'required|string',
            'waktu_mulai' => 'required|date',
            'waktu_akhir' => 'nullable|date',
            'id_rumus_penilaian' => 'nullable|string',
            'sub_materi' => 'required|array',
            'Jenis_User' => 'nullable|string',
            'Flag_Learning_Gain' => 'nullable',
        ]);

        $processId = (string) Str::uuid();

        DB::table('N_LMS_Job_Payloads')->insert([
            'Process_Id' => $processId,
            'Payload_Json' => json_encode($validated),
        ]);

        Cache::put(
            "progress_penjadwalan_{$processId}",
            [
                'status' => 'processing',
                'progress' => 0,
            ],
            7200,
        );

        ProcessBulkPenjadwalan::dispatch($processId, $appId, $kodeKaryawanAuth, $idUsersAuth, $userAgent, $domain);

        return ResponseHelper::success(
            ['process_id' => $processId], // Kirim ini ke Vue
            'Data penjadwalan sedang diproses di latar belakang.',
            202,
        );
    }

    public function checkProgress($processId)
    {
        $data = Cache::get("progress_penjadwalan_{$processId}");

        if (! $data) {
            return response()->json(['status' => 'not_found', 'progress' => 0], 404);
        }

        return response()->json($data);
    }

    public function update(Request $request)
    {
        $appId = Auth::user()->User_Key ?? config('app.key');
        $kodeKaryawanAuth = Auth::user()->Kode_Users ?? 'SYSTEM';

        Log::info('--- START UPDATE PENJADWALAN TRAINING ---');

        $validated = $request->validate([
            'id_penjadwalan' => 'required|string',
            'karyawan' => 'required|array',
            'id_training' => 'required|string',
            'waktu_mulai' => 'required|date',
            'waktu_akhir' => 'nullable|date',
            'id_rumus_penilaian' => 'nullable|string',
            'sub_materi' => 'required|array',
            'Jenis_User' => 'nullable|string',
            'Flag_Camera' => 'nullable|string',
            'User_Agent' => 'nullable',
            'Flag_Learning_Gain' => 'nullable',
        ]);

        DB::beginTransaction();

        try {
            $idPenjadwalanArr = Hashids::connection('custom')->decode($validated['id_penjadwalan']);
            if (empty($idPenjadwalanArr)) {
                Log::warning('Decode id_penjadwalan gagal.');

                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }
            $idPenjadwalan = $idPenjadwalanArr[0];
            Log::info("Memproses Id_Penjadwalan: {$idPenjadwalan}");

            $idMasterPaketArr = Hashids::connection('custom')->decode($validated['id_training']);
            $idMasterPaket = ! empty($idMasterPaketArr) ? $idMasterPaketArr[0] : null;

            $idMasterRumus = null;
            if (! empty($validated['id_rumus_penilaian'])) {
                $idMasterRumusArr = Hashids::connection('custom')->decode($validated['id_rumus_penilaian']);
                $idMasterRumus = ! empty($idMasterRumusArr) ? $idMasterRumusArr[0] : null;
            }

            $valLGN = $validated['Flag_Learning_Gain'] ?? 'T';
            $flagLearningGain = $valLGN === true || $valLGN === 'true' || $valLGN === 'Y' ? 'Y' : 'T';

            $kodeKar = $validated['karyawan'][0];
            $karyawanLMS = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKar)->first();
            if (! $karyawanLMS) {
                Log::warning("Karyawan dengan kode {$kodeKar} tidak ditemukan.");

                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }

            $karyawanSpecificDetail = [
                [
                    'Kode_Karyawan' => $karyawanLMS->Kode_Karyawan,
                    'Nama' => $karyawanLMS->Nama ?? '-',
                    'No_Hp' => $karyawanLMS->HP ?? '-',
                    'UserID_Web' => $karyawanLMS->UserID_Web ?? '-',
                    'UserID_Absen' => $karyawanLMS->UserID_Absen ?? '-',
                    'Tgl_Lahir' => $karyawanLMS->Tgl_Lahir ?? null,
                    'ID_Level_Jabatan' => $karyawanLMS->ID_Level_Jabatan ?? null,
                    'ID_Divisi_Sub_Divisi' => $karyawanLMS->ID_Divisi_Sub_Divisi ?? null,
                ],
            ];

            DB::table('N_LMS_Penjadwalan')
                ->where('Id_Penjadwalan', $idPenjadwalan)
                ->update([
                    'Id_Master_Paket' => $idMasterPaket,
                    'Id_Master_Rumus' => $idMasterRumus,
                    'Flag_Learning_Gain' => $flagLearningGain,
                    'Waktu_Enroll' => $validated['waktu_mulai'],
                    'Updated_At' => now(),
                ]);

            $incomingMateriDetIds_ForDelete = [];
            $flaggedT_ForDelete = [];
            foreach ($validated['sub_materi'] as $sub) {
                if (isset($sub['Aktivitas']) && is_array($sub['Aktivitas'])) {
                    foreach ($sub['Aktivitas'] as $akt) {
                        $idMateriDetArr = isset($akt['id_materi_det'])
                            ? Hashids::connection('custom')->decode($akt['id_materi_det'])
                            : [];
                        $idMateriDet = ! empty($idMateriDetArr) ? $idMateriDetArr[0] : null;
                        if ($idMateriDet) {
                            $incomingMateriDetIds_ForDelete[] = $idMateriDet;
                            if (($akt['flag_third_party'] ?? 'T') === 'T') {
                                $flaggedT_ForDelete[] = $idMateriDet;
                            }
                        }
                    }
                }
            }

            $existingDetails_ForDelete = DB::table('N_LMS_Penjadwalan_Detail')
                ->where('Id_Penjadwalan', $idPenjadwalan)
                ->pluck('Id_Penjadwalan_Detail')
                ->toArray();

            $integrasiDetIdsToDelete_Initial = [];
            if (! empty($existingDetails_ForDelete)) {
                $abandonedDets = DB::table('N_LMS_Penjadwalan_Det')
                    ->whereIn('Id_Penjadwalan_Detail', $existingDetails_ForDelete)
                    ->whereNotIn('Id_Materi_Det', $incomingMateriDetIds_ForDelete)
                    ->pluck('Id_Penjadwalan_Det')
                    ->toArray();

                $changedToT = [];
                if (! empty($flaggedT_ForDelete)) {
                    $changedToT = DB::table('N_LMS_Penjadwalan_Det')
                        ->whereIn('Id_Penjadwalan_Detail', $existingDetails_ForDelete)
                        ->whereIn('Id_Materi_Det', $flaggedT_ForDelete)
                        ->pluck('Id_Penjadwalan_Det')
                        ->toArray();
                }

                $integrasiDetIdsToDelete_Initial = array_unique(array_merge($abandonedDets, $changedToT));
            }

            /* Pekerjaan sinkron ke HC Learn dikumpulkan di sini, lalu dititipkan
               ke outbox SEKALI di bawah — bukan dipanggil di tengah perulangan
               selagi transaksi memegang kunci. */
            $sinkronTertunda = [];

            $tokensToDelete = [];
            if (! empty($integrasiDetIdsToDelete_Initial)) {
                $tokensToDelete = DB::table('N_LMS_Penjadwalan_Integrasi')
                    ->whereIn('Id_Penjadwalan_Det', $integrasiDetIdsToDelete_Initial)
                    ->whereNotNull('Id_Ujian_Token')
                    ->pluck('Id_Ujian_Token')
                    ->toArray();
            }

            $incomingMateriDetailIds = [];
            $incomingMateriDetIdsPerDetail = [];
            $integrasiDetIdsToDelete = [];

            foreach ($validated['sub_materi'] as $sub) {
                $idMateriDetailArr = Hashids::connection('custom')->decode($sub['Id_Materi_Detail']);
                $idMateriDetail = ! empty($idMateriDetailArr) ? $idMateriDetailArr[0] : null;

                $idMasterPaketDetailArr = isset($sub['Id_Master_Paket_Detail'])
                    ? Hashids::connection('custom')->decode($sub['Id_Master_Paket_Detail'])
                    : [];
                $idMasterPaketDetail = ! empty($idMasterPaketDetailArr) ? $idMasterPaketDetailArr[0] : null;

                if (! $idMateriDetail || ! $idMasterPaketDetail) {
                    continue;
                }

                $incomingMateriDetailIds[] = $idMateriDetail;

                $existingDetail = DB::table('N_LMS_Penjadwalan_Detail')
                    ->where('Id_Penjadwalan', $idPenjadwalan)
                    ->where('Id_Materi_Detail', $idMateriDetail)
                    ->first();

                if ($existingDetail) {
                    $idPenjadwalanDetail = $existingDetail->Id_Penjadwalan_Detail;
                    DB::table('N_LMS_Penjadwalan_Detail')
                        ->where('Id_Penjadwalan_Detail', $idPenjadwalanDetail)
                        ->update(['Urutan' => $sub['Urutan'] ?? 1]);
                } else {
                    $idPenjadwalanDetail = DB::table('N_LMS_Penjadwalan_Detail')->insertGetId([
                        'Id_Penjadwalan' => $idPenjadwalan,
                        'Id_Master_Paket_Detail' => $idMasterPaketDetail,
                        'Id_Materi_Detail' => $idMateriDetail,
                        'Urutan' => $sub['Urutan'] ?? 1,
                        'Flag_Aktif' => 'Y',
                    ]);
                }

                $incomingMateriDetIdsPerDetail[$idPenjadwalanDetail] = [];

                if (isset($sub['Aktivitas']) && is_array($sub['Aktivitas'])) {
                    foreach ($sub['Aktivitas'] as $akt) {
                        $idMateriDetArr = isset($akt['id_materi_det'])
                            ? Hashids::connection('custom')->decode($akt['id_materi_det'])
                            : [];
                        $idMateriDet = ! empty($idMateriDetArr) ? $idMateriDetArr[0] : null;

                        if (! $idMateriDet) {
                            continue;
                        }

                        $incomingMateriDetIdsPerDetail[$idPenjadwalanDetail][] = $idMateriDet;

                        $flagThirdParty = $akt['flag_third_party'] == 'Y' ? 'Y' : 'T';
                        $punyaBatasAkhir = isset($validated['waktu_akhir']) ? 'Y' : 'T';
                        $waktuMulaiAkses = $akt['waktu_mulai_thirdparty'] ?? $validated['waktu_mulai'];
                        $waktuAkhirAkses = $akt['waktu_akhir_thirdparty'] ?? $validated['waktu_akhir'];

                        $existingDet = DB::table('N_LMS_Penjadwalan_Det')
                            ->where('Id_Penjadwalan_Detail', $idPenjadwalanDetail)
                            ->where('Id_Materi_Det', $idMateriDet)
                            ->first();

                        if ($existingDet) {
                            $idPenjadwalanDet = $existingDet->Id_Penjadwalan_Det;
                            DB::table('N_LMS_Penjadwalan_Det')
                                ->where('Id_Penjadwalan_Det', $idPenjadwalanDet)
                                ->update([
                                    'Waktu_Mulai_Akses' => $waktuMulaiAkses,
                                    'Waktu_Batas_Akses' => $waktuAkhirAkses,
                                    'Flag_Third_Party' => $flagThirdParty,
                                    'Flag_Mempunyai_Akhir_Waktu' => $punyaBatasAkhir,
                                ]);

                            DB::table('N_LMS_Penjadwalan_Syarat')->updateOrInsert(
                                ['Id_Penjadwalan_Det' => $idPenjadwalanDet],
                                [
                                    'Id_Penjadwalan_Detail' => $idPenjadwalanDetail,
                                    'Id_Materi_Det' => $idMateriDet,
                                    'Id_Materi_Det_Syarat' => null,
                                    'Flag_Aktif' => 'Y',
                                ],
                            );
                        } else {
                            $idPenjadwalanDet = DB::table('N_LMS_Penjadwalan_Det')->insertGetId([
                                'Id_Penjadwalan_Detail' => $idPenjadwalanDetail,
                                'Id_Materi_Det' => $idMateriDet,
                                'Waktu_Mulai_Akses' => $waktuMulaiAkses,
                                'Waktu_Batas_Akses' => $waktuAkhirAkses,
                                'Flag_Checkin' => 'T',
                                'Flag_Third_Party' => $flagThirdParty,
                                'Flag_Selesai' => 'T',
                                'Flag_Mempunyai_Akhir_Waktu' => $punyaBatasAkhir,
                                'Status_Download' => 'T',
                            ]);

                            DB::table('N_LMS_Penjadwalan_Syarat')->insert([
                                'Id_Penjadwalan_Detail' => $idPenjadwalanDetail,
                                'Id_Penjadwalan_Det' => $idPenjadwalanDet,
                                'Id_Materi_Det' => $idMateriDet,
                                'Id_Materi_Det_Syarat' => null,
                                'Flag_Aktif' => 'Y',
                            ]);
                        }

                        if ($flagThirdParty === 'Y' && ! empty($akt['id_paket_soal'])) {
                            $integrasiLama = DB::table('N_LMS_Penjadwalan_Integrasi')
                                ->where('Id_Penjadwalan_Det', $idPenjadwalanDet)
                                ->first();

                            $userAgentPayload = is_array($request->User_Agent)
                                ? json_encode($request->User_Agent)
                                : $request->User_Agent ?? '';

                            /* HC Learn TIDAK dipanggil dari sini.
                               Dulu panggilannya dilakukan tepat di titik ini —
                               di dalam perulangan bersarang, di dalam transaksi
                               yang terbuka sejak awal metode. Selama panggilan
                               berlangsung seluruh kunci transaksi tetap
                               dipegang, dan lamanya ditentukan pihak lain:
                               timeout 8 detik dikali jumlah aktivitas. Itu
                               temuan #2 di register audit.

                               Sekarang pekerjaannya DITITIPKAN ke outbox: satu
                               baris, di dalam transaksi yang sama dengan
                               perubahan datanya, sehingga keduanya jadi atau
                               batal bersama-sama dan tak pernah ada task yatim.
                               Panggilan keluarnya dikerjakan
                               LmsPenjadwalanSyncThirdPartyHandler sesudah
                               commit, tanpa satu kunci pun tertahan, dan
                               dicoba ulang bila gagal sementara. */
                            if ($integrasiLama && ! empty($integrasiLama->Id_Ujian_Token)) {
                                DB::table('N_LMS_Penjadwalan_Integrasi')
                                    ->where('Id_Penjadwalan_Det', $idPenjadwalanDet)
                                    ->update([
                                        'Waktu_Mulai' => $waktuMulaiAkses,
                                        'Waktu_Akhir' => $waktuAkhirAkses,
                                        'Id_Master_Ujian' => $akt['id_paket_soal'],
                                    ]);

                                $sinkronTertunda[] = [
                                    'mode' => 'update',
                                    'id_integrasi' => $integrasiLama->Id_Penjadwalan_Integrasi,
                                    'token_lama' => $integrasiLama->Id_Ujian_Token,
                                    'id_master_ujian' => $akt['id_paket_soal'],
                                    'waktu_mulai' => $waktuMulaiAkses,
                                    'waktu_akhir' => $waktuAkhirAkses,
                                    'flag_camera' => isset($akt['flag_camera']) && $akt['flag_camera'] === 'Y',
                                    'user_agent' => $userAgentPayload,
                                    'deleted_tokens' => $tokensToDelete,
                                    'kode_karyawan' => $kodeKar,
                                    'app_id' => $appId,
                                    'kode_karyawan_auth' => $kodeKaryawanAuth,
                                ];
                            } else {
                                $idIntegrasiBaru = DB::table('N_LMS_Penjadwalan_Integrasi')->insertGetId([
                                    'Id_Penjadwalan_Det' => $idPenjadwalanDet,
                                    'Waktu_Mulai' => $waktuMulaiAkses,
                                    'Waktu_Akhir' => $waktuAkhirAkses,
                                    'Id_Master_Ujian' => $akt['id_paket_soal'],
                                ]);

                                $sinkronTertunda[] = [
                                    'mode' => 'store',
                                    'id_integrasi' => $idIntegrasiBaru,
                                    'id_master_ujian' => $akt['id_paket_soal'],
                                    'waktu_mulai' => $waktuMulaiAkses,
                                    'waktu_akhir' => $waktuAkhirAkses,
                                    'flag_camera' => isset($akt['flag_camera']) && $akt['flag_camera'] === 'Y',
                                    'user_agent' => $userAgentPayload,
                                    'deleted_tokens' => $tokensToDelete,
                                    'kode_karyawan' => $kodeKar,
                                    'data_karyawan' => $karyawanSpecificDetail,
                                    'app_id' => $appId,
                                    'kode_karyawan_auth' => $kodeKaryawanAuth,
                                ];
                            }
                        } else {
                            $integrasiDetIdsToDelete[] = $idPenjadwalanDet;
                        }
                    }
                }
            }

            $detailsToDelete = DB::table('N_LMS_Penjadwalan_Detail')
                ->where('Id_Penjadwalan', $idPenjadwalan)
                ->whereNotIn('Id_Materi_Detail', $incomingMateriDetailIds)
                ->pluck('Id_Penjadwalan_Detail');

            $detsToDelete = collect();
            if ($detailsToDelete->isNotEmpty()) {
                $detsToDelete = DB::table('N_LMS_Penjadwalan_Det')
                    ->whereIn('Id_Penjadwalan_Detail', $detailsToDelete)
                    ->pluck('Id_Penjadwalan_Det');

                if ($detsToDelete->isNotEmpty()) {
                    $integrasiDetIdsToDelete = array_merge($integrasiDetIdsToDelete, $detsToDelete->toArray());
                }
            }

            $abandonedDetsAll = collect();
            foreach ($incomingMateriDetIdsPerDetail as $idPD => $keptDetIds) {
                $abandonedDets = DB::table('N_LMS_Penjadwalan_Det')
                    ->where('Id_Penjadwalan_Detail', $idPD)
                    ->whereNotIn('Id_Materi_Det', $keptDetIds)
                    ->pluck('Id_Penjadwalan_Det');

                if ($abandonedDets->isNotEmpty()) {
                    $abandonedDetsAll = $abandonedDetsAll->merge($abandonedDets);
                    $integrasiDetIdsToDelete = array_merge($integrasiDetIdsToDelete, $abandonedDets->toArray());
                }
            }

            Log::info('=== MULAI PROSES HAPUS THIRD PARTY (LOCAL DB ONLY) ===');

            if (! empty($integrasiDetIdsToDelete)) {
                $integrasiDetIdsToDelete = array_unique($integrasiDetIdsToDelete);

                DB::table('N_LMS_Penjadwalan_Integrasi')
                    ->whereIn('Id_Penjadwalan_Det', $integrasiDetIdsToDelete)
                    ->delete();
            }

            Log::info('Membersihkan sisa tabel Det, Syarat, dan Detail lokal.');
            if ($detsToDelete->isNotEmpty()) {
                DB::table('N_LMS_Penjadwalan_Syarat')->whereIn('Id_Penjadwalan_Det', $detsToDelete)->delete();
                DB::table('N_LMS_Penjadwalan_Det')->whereIn('Id_Penjadwalan_Det', $detsToDelete)->delete();
            }
            if ($detailsToDelete->isNotEmpty()) {
                DB::table('N_LMS_Penjadwalan_Detail')->whereIn('Id_Penjadwalan_Detail', $detailsToDelete)->delete();
            }
            if ($abandonedDetsAll->isNotEmpty()) {
                DB::table('N_LMS_Penjadwalan_Syarat')->whereIn('Id_Penjadwalan_Det', $abandonedDetsAll)->delete();
                DB::table('N_LMS_Penjadwalan_Det')->whereIn('Id_Penjadwalan_Det', $abandonedDetsAll)->delete();
            }

            /* Outbox ditulis DI DALAM transaksi ini: kalau suntingannya batal,
               titipannya ikut batal, sehingga tak pernah ada task yang
               menyinkronkan keadaan yang tak jadi tersimpan. */
            $idOutbox = [];
            foreach ($sinkronTertunda as $tugas) {
                $kunci = 'LMS:PENJADWALAN_SYNC:'.$tugas['mode'].':'.$tugas['id_integrasi']
                    .':'.sha1(json_encode($tugas));

                $outbox = $this->taskOutboxService->create([
                    'Task_Type' => 'LMS_PENJADWALAN_SYNC_THIRD_PARTY',
                    'Aggregate_Type' => 'N_LMS_Penjadwalan_Integrasi',
                    'Aggregate_Id' => $tugas['id_integrasi'],
                    'Idempotency_Key' => $kunci,
                    'Queue_Name' => config('queue.names.lms_outbox'),
                    // absoluteUrl(): APP_URL di lingkungan ini boleh tanpa skema,
                    // dan URL tanpa skema ditolak Cloud Tasks secara SENYAP.
                    'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
                    'Payload' => $tugas,
                    'Max_Attempts' => 3,
                    'Created_By' => $kodeKaryawanAuth,
                    'Source_Module' => 'LMS',
                    'Source_Feature' => 'PenjadwalanTrainingUpdate',
                    // Pemilik baris di panel Tugas & Aktivitas (yang menyaring per Kode_Karyawan).
                    'Sender_Employee_Code' => optional(Auth::user()?->karyawan)->Kode_Karyawan,
                ]);

                $idOutbox[] = $outbox->Id_Task_Outbox;
            }

            DB::commit();
            Log::info('--- UPDATE PENJADWALAN TRAINING BERHASIL ---');

            /* Dispatch SESUDAH commit, dan kegagalannya tidak menggagalkan
               permintaan: barisnya tetap QUEUED dan akan terambil putaran
               berikutnya. Yang tak boleh terjadi adalah task terkirim untuk
               transaksi yang ternyata batal. */
            $kirim = $this->kirimOutbox($idOutbox, 'lms-penjadwalan-sync');

            /* Suntingannya SUDAH tersimpan, jadi tetap 200 — tetapi sinkron yang belum
               terkirim disebut terang-terangan alih-alih ditelan log. */
            $pesan = $kirim['belum'] === []
                ? 'Berhasil memperbarui data penjadwalan.'
                : 'Data penjadwalan tersimpan, tetapi '.count($kirim['belum'])
                    .' sinkron ke HC Learn belum terkirim: '.$kirim['sebab'].' Hubungi admin sistem.';

            return ResponseHelper::success(['sinkron_belum_terkirim' => count($kirim['belum'])], $pesan, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Update Training: '.$e->getMessage().' Trace: '.$e->getTraceAsString());
            Log::channel('LmsPenjadwalanTrainingController')->error('Error Update Training: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan sistem: '.$e->getMessage(), 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function toggleAktif(Request $request)
    {
        $request->validate([
            'id_penjadwalan' => 'required|string',
        ]);

        try {
            $idPenjadwalanArr = Hashids::connection('custom')->decode($request->id_penjadwalan);

            if (empty($idPenjadwalanArr)) {
                Log::warning('Decode id_penjadwalan gagal: '.$request->id_penjadwalan);

                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }

            $idPenjadwalan = $idPenjadwalanArr[0];
        } catch (\Exception $e) {
            Log::error('Hashids Error: '.$e->getMessage());

            return ResponseHelper::error('Format ID tidak valid.', 400);
        }

        DB::beginTransaction();
        try {
            $jadwal = DB::table('N_LMS_Penjadwalan')->where('Id_Penjadwalan', $idPenjadwalan)->first();
            if (! $jadwal) {
                DB::rollBack();

                return ResponseHelper::error('Data Penjadwalan tidak ditemukan.', 404);
            }
            $newFlag = $jadwal->Flag_Cabut_Akses === 'Y' ? 'T' : 'Y';
            $messageAction = $newFlag === 'Y' ? 'dicabut' : 'dibuka kembali';

            DB::table('N_LMS_Penjadwalan')
                ->where('Id_Penjadwalan', $idPenjadwalan)
                ->update([
                    'Flag_Cabut_Akses' => $newFlag,
                    'Updated_At' => now(),
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => "Akses training berhasil $messageAction!",
                'data' => [
                    'Id_Penjadwalan' => $request->id_penjadwalan,
                    'Flag_Cabut_Akses' => $newFlag,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Gagal toggle akses: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Internal Server.'.$e->getMessage(), 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function bulkToggleAktif(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'action' => 'required|in:Y,T',
        ]);

        DB::beginTransaction();
        try {
            $decodedIds = [];
            foreach ($request->ids as $hashId) {
                $idArr = Hashids::connection('custom')->decode($hashId);
                if (! empty($idArr)) {
                    $decodedIds[] = $idArr[0];
                }
            }

            if (empty($decodedIds)) {
                DB::rollBack();

                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }

            DB::table('N_LMS_Penjadwalan')
                ->whereIn('Id_Penjadwalan', $decodedIds)
                ->update([
                    'Flag_Cabut_Akses' => $request->action,
                    'Updated_At' => now(),
                ]);

            DB::commit();

            $messageAction = $request->action === 'Y' ? 'dicabut' : 'dibuka kembali';

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => count($decodedIds)." Akses training berhasil $messageAction!",
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Gagal bulk toggle akses: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Internal Server.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function reassignThirdParty(Request $request)
    {
        $appId = Auth::user()->User_Key ?? config('app.key');
        $kodeKaryawanAuth = Auth::user()->Kode_Users ?? 'SYSTEM';

        $validated = $request->validate([
            'id_penjadwalan_det' => 'required|string',
            'kode_karyawan' => 'required|string',
            'id_master_ujian' => 'required|string',
            'waktu_mulai' => 'required|date',
            'waktu_akhir' => 'nullable|date',
            'old_token' => 'required|string',
            'User_Agent' => 'nullable',
            'flag_camera' => 'nullable|boolean',
        ]);

        try {
            $outbox = DB::transaction(function () use ($validated, $appId, $kodeKaryawanAuth, $request) {
                $idDetArr = Hashids::connection('custom')->decode($validated['id_penjadwalan_det']);
                if (empty($idDetArr)) {
                    throw new \InvalidArgumentException('ID Aktivitas tidak valid.');
                }
                $idPenjadwalanDet = $idDetArr[0];

                $integrasiLama = DB::table('N_LMS_Penjadwalan_Integrasi')
                    ->where('Id_Penjadwalan_Det', $idPenjadwalanDet)
                    ->where('Id_Ujian_Token', $validated['old_token'])
                    ->first();
                if (! $integrasiLama) {
                    throw new \RuntimeException('Data integrasi sebelumnya tidak ditemukan atau token sudah berubah.');
                }

                $karyawanLMS = DB::table('Karyawan')->where('Kode_Karyawan', $validated['kode_karyawan'])->first();
                if (! $karyawanLMS) {
                    throw new \RuntimeException('Karyawan tidak ditemukan');
                }

                $userAgentPayload = is_array($request->User_Agent)
                    ? json_encode($request->User_Agent)
                    : ($request->User_Agent ?? '');
                $payload = [
                    'integration_id' => $integrasiLama->Id_Penjadwalan_Integrasi,
                    'id_penjadwalan_det' => $idPenjadwalanDet,
                    'kode_karyawan' => $validated['kode_karyawan'],
                    'id_master_ujian' => $validated['id_master_ujian'],
                    'waktu_mulai' => $validated['waktu_mulai'],
                    'waktu_akhir' => $validated['waktu_akhir'] ?? null,
                    'flag_camera' => ! empty($validated['flag_camera']),
                    'old_token' => $validated['old_token'],
                    'app_id' => $appId,
                    'kode_karyawan_auth' => $kodeKaryawanAuth,
                    'user_agent' => $userAgentPayload,
                    'data_karyawan' => [[
                        'Kode_Karyawan' => $karyawanLMS->Kode_Karyawan,
                        'Nama' => $karyawanLMS->Nama ?? '-',
                        'No_Hp' => $karyawanLMS->HP ?? '-',
                        'UserID_Web' => $karyawanLMS->UserID_Web ?? '-',
                        'UserID_Absen' => $karyawanLMS->UserID_Absen ?? '-',
                        'Tgl_Lahir' => $karyawanLMS->Tgl_Lahir ?? null,
                        'ID_Level_Jabatan' => $karyawanLMS->ID_Level_Jabatan ?? null,
                        'ID_Divisi_Sub_Divisi' => $karyawanLMS->ID_Divisi_Sub_Divisi ?? null,
                    ]],
                ];

                $key = 'LMS:REASSIGN:'.$integrasiLama->Id_Penjadwalan_Integrasi.':'.$validated['old_token'].':'.sha1(json_encode($payload));

                /* Kunci yang sama mengembalikan baris lama (klik ganda tidak menggandakan
                   permintaan). Tetapi baris yang sudah FINAL gagal/dibatalkan tidak boleh
                   menahan pengajuan berikutnya dengan isian yang sama — itu permintaan baru. */
                $statusLama = DB::table('N_SYSTEM_Task_Outbox')->where('Idempotency_Key', $key)->value('Status');
                if (in_array($statusLama, ['FAILED', 'CANCELLED'], true)) {
                    $key .= ':ulang:'.Str::uuid();
                }

                return $this->taskOutboxService->create([
                    'Task_Type' => 'LMS_REASSIGN_THIRD_PARTY',
                    'Aggregate_Type' => 'N_LMS_Penjadwalan_Integrasi',
                    'Aggregate_Id' => $integrasiLama->Id_Penjadwalan_Integrasi,
                    'Idempotency_Key' => $key,
                    'Queue_Name' => config('queue.names.lms_outbox'),
                    // Lewat absoluteUrl(): APP_URL boleh ditulis tanpa skema, dan
                    // URL tanpa skema membuat Cloud Tasks menolak diam-diam.
                    'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
                    'Payload' => $payload,
                    'Max_Attempts' => 3,
                    'Created_By' => $kodeKaryawanAuth,
                    'Source_Module' => 'LMS',
                    'Source_Feature' => 'PenjadwalanTrainingReassign',
                    'Sender_Employee_Code' => optional(Auth::user()?->karyawan)->Kode_Karyawan,
                ]);
            });

            /* Dikirim SESUDAH commit, dan yang dikirim baris MILIK permintaan ini. Dulu
               `dispatchBatch(1)` memungut baris tertua di antrean — begitu ada tunggakan,
               permintaan ini tak pernah terkirim padahal layar sudah berkata "sedang
               diproses". Sekarang jawabannya mengikuti nasib baris ini sendiri. */
            $kirim = $this->kirimOutbox([(int) $outbox->Id_Task_Outbox], 'lms-reassign');

            if ($kirim['belum'] !== []) {
                return ResponseHelper::error(
                    'Permintaan pengajuan ulang tersimpan, tetapi belum bisa dikirim ke HC Learn: '
                    .$kirim['sebab'].' Hubungi admin sistem.',
                    503,
                );
            }

            return ResponseHelper::success([
                'process_id' => $outbox->Id_Task_Outbox,
                'status' => 'DISPATCHED',
            ], 'Permintaan pengajuan ulang ujian sedang diproses.', 202);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Log::error('Gagal membuat task reassign LMS: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengajukan ulang ujian.', 500);
        }
    }

    /**
     * Mengirim baris Outbox milik permintaan ini dan melaporkan yang belum terkirim.
     *
     * Baris yang tidak ikut terklaim (sudah dikirim permintaan kembar, atau masih menunggu
     * jeda percobaan ulang) dinilai dari status terkininya: yang sudah DISPATCHED/PROCESSING/
     * COMPLETED dianggap terkirim, sisanya belum — dengan galat terakhirnya sebagai sebab.
     *
     * @param  list<int>  $ids
     * @return array{belum: list<int>, sebab: ?string}
     */
    private function kirimOutbox(array $ids, string $awalanWorker): array
    {
        if ($ids === []) {
            return ['belum' => [], 'sebab' => null];
        }

        $galat = [];
        try {
            $hasil = $this->taskOutboxDispatcher->dispatchIds($ids, $awalanWorker.':'.Str::uuid());
            $galat = $hasil['galat'];
        } catch (\Throwable $exception) {
            Log::error('Gagal dispatch Outbox LMS sesudah commit.', ['outbox_id' => $ids, 'error' => $exception->getMessage()]);
            $galat = array_fill_keys($ids, $exception->getMessage());
        }

        $baris = DB::table('N_SYSTEM_Task_Outbox')->whereIn('Id_Task_Outbox', $ids)->get(['Id_Task_Outbox', 'Status', 'Last_Error']);
        $belum = $baris
            ->reject(fn ($r) => in_array($r->Status, ['DISPATCHED', 'PROCESSING', 'COMPLETED'], true))
            ->pluck('Id_Task_Outbox')->map(fn ($id) => (int) $id)->values()->all();

        if ($belum === []) {
            return ['belum' => [], 'sebab' => null];
        }

        $sebab = $galat[$belum[0]] ?? $baris->firstWhere('Id_Task_Outbox', $belum[0])?->Last_Error;
        Log::warning('Outbox LMS belum terkirim.', ['outbox_id' => $belum, 'sebab' => $sebab]);

        return ['belum' => $belum, 'sebab' => $sebab ?: 'antrean belum memprosesnya.'];
    }
}
