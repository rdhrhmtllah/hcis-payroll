<?php

namespace App\Http\Controllers\LMS\Training\Catalog;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\TrainingProgressReadService;
use App\Services\LMS\TrainingScheduleQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class TrainingCatalogController extends Controller
{
    public function __construct(
        private TrainingProgressReadService $trainingProgressReadService,
        private TrainingScheduleQueryService $trainingScheduleQueryService,
    ) {}

    /**
     * Get available filter options for catalog
     */
    public function getFilterOptions()
    {
        $kodeKaryawan = $this->resolveCurrentKodeKaryawan();

        if (! $kodeKaryawan) {
            return ResponseHelper::success(
                [
                    'kategoris' => [],
                    'statuses' => [
                        ['label' => 'Belum Dimulai', 'value' => 'not-started'],
                        ['label' => 'Kelas Berlangsung', 'value' => 'in-progress'],
                        ['label' => 'Kelas Selesai', 'value' => 'completed'],
                    ],
                ],
                'Filter options',
            );
        }

        $normalizedKodeKaryawan = strtoupper(trim($kodeKaryawan));

        // Get available kategoris from user's enrolled training
        $kategoris = $this->trainingScheduleQueryService
            ->byKodeKaryawanQuery($normalizedKodeKaryawan, 'p')
            ->join('N_LMS_Master_Paket as mp', 'p.Id_Master_Paket', '=', 'mp.Id_Master_Paket')
            ->join('N_LMS_Master_Paket_Detail as mpd', 'mp.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
            ->leftJoin('N_LMS_Training_Kategori as k', 'm.Id_Training_Kategori', '=', 'k.Id_Training_Kategori')
            ->where('m.Flag_Aktif', 'Y')
            ->selectRaw("COALESCE(k.Nama_Kategori, 'General') as Nama_Kategori")
            ->distinct()
            ->orderBy('Nama_Kategori')
            ->pluck('Nama_Kategori')
            ->filter(fn ($k) => ! empty($k))
            ->values()
            ->map(fn ($label) => ['label' => $label, 'value' => $label])
            ->toArray();

        return ResponseHelper::success(
            [
                'kategoris' => $kategoris,
                'statuses' => [
                    ['label' => 'Belum Dimulai', 'value' => 'not-started'],
                    ['label' => 'Kelas Berlangsung', 'value' => 'in-progress'],
                    ['label' => 'Kelas Selesai', 'value' => 'completed'],
                ],
            ],
            'Filter options',
        );
    }

    /**
     * Display the training catalog listing.
     */
    public function index(Request $request)
    {
        $kodeKaryawan = $this->resolveCurrentKodeKaryawan();
        $limit = (int) $request->query('limit', 12);
        $page = (int) $request->query('page', 1);
        $search = trim((string) $request->query('q', ''));
        $kategori = trim((string) $request->query('kategori', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $sort = trim((string) $request->query('sort', 'default'));

        if (! $kodeKaryawan) {
            return inertia('LMS/Training/Catalog/TrainingCatalogPage', [
                'trainingCatalog' => [],
                'initialPagination' => [
                    'total' => 0,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'total_pages' => 1,
                ],
            ]);
        }

        $normalizedKodeKaryawan = strtoupper(trim($kodeKaryawan));

        $query = $this->trainingScheduleQueryService->byKodeKaryawanWithPaketQuery($normalizedKodeKaryawan, 'p');

        $totalData = $query->count();
        $penjadwalans = $query
            ->select([
                'p.Id_Penjadwalan',
                'p.Id_Master_Paket',
                'p.Flag_Selesai',
                'p.Waktu_Enroll',
                'p.Created_At',
                'mp.Nama_Paket',
                'mp.Deskripsi as Deskripsi_Paket',
            ])
            ->orderBy('p.Created_At', 'desc')
            ->orderBy('p.Id_Penjadwalan', 'desc')
            ->get();

        /* Ringkasan progres & status diambil SEKALI untuk seluruh halaman.
           Sebelumnya computeProgress() dan computeStatus() dipanggil di dalam
           map(), dan masing-masing berbiaya 4–6 kueri; sembilan baris
           menghasilkan 61 kueri, 58 di antaranya per-baris. */
        $idPenjadwalan = $penjadwalans->pluck('Id_Penjadwalan')->map(fn ($v) => (int) $v)->all();
        $ringkasan = $this->trainingProgressReadService->ringkasanMassal($idPenjadwalan);
        $sisaAktivitas = $this->penjadwalanYangMasihPunyaAktivitas($idPenjadwalan);

        $catalog = $penjadwalans
            ->map(function ($penjadwalan) use ($search, $kategori, $ringkasan, $sisaAktivitas) {
                $materi = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
                    (int) $penjadwalan->Id_Master_Paket,
                    $search,
                    $kategori,
                );

                if (! $materi) {
                    return null;
                }

                $idP = (int) $penjadwalan->Id_Penjadwalan;
                $r = $ringkasan[$idP] ?? ['progress_percent' => 0, 'has_started' => false];

                $progress = (int) ($r['progress_percent'] ?? 0);
                $computedStatus = $this->computeStatus($penjadwalan, $sisaAktivitas, (bool) ($r['has_started'] ?? false));
                if ($computedStatus === 'not-started') {
                    $progress = 0;
                }

                return [
                    'id' => isset($materi->Id_Materi)
                        ? Hashids::connection('custom')->encode($materi->Id_Materi)
                        : Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan),
                    'penjadwalan_id' => Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan),
                    'id_penjadwalan_raw' => (int) $penjadwalan->Id_Penjadwalan,
                    'created_at' => $penjadwalan->Created_At ?? null,
                    'title' => $materi->Judul_Training ?? ($penjadwalan->Nama_Paket ?? 'Untitled Training'),
                    'category' => $materi->Nama_Kategori ?? 'General',
                    'provider' => 'Internal Training',
                    'date' => $penjadwalan->Created_At ?? ($penjadwalan->Waktu_Enroll ?? ($materi->Tanggal ?? null)),
                    'start_at' => $penjadwalan->Waktu_Enroll,
                    'duration' => '-',
                    'level' => 'General',
                    'progress' => $progress,
                    'status' => $computedStatus,
                    'image' => $this->resolveThumbnailUrl($materi->Thumbnail_Training ?? null),
                    'trainer' => 'Learning Team',
                    'description' => $materi->Deskripsi ?? ($penjadwalan->Deskripsi_Paket ?? ''),
                    'tags' => [],
                ];
            })
            ->filter(function ($item) {
                // Filter out items where materiData is null (tidak ada materi sesuai filter)
                return $item !== null;
            })
            ->values();

        // Apply status filter if provided
        if (! empty($statusFilter) && in_array($statusFilter, ['not-started', 'in-progress', 'completed'], true)) {
            $catalog = $catalog->filter(fn ($item) => $item['status'] === $statusFilter);
        }

        // Apply sorting to catalog collection
        $catalog = $this->sortCatalog($catalog, $sort);

        // Recalculate total and pagination after filtering & sorting
        $filteredTotal = $catalog->count();
        $filteredCatalog = $catalog
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->values();

        if ($request->wantsJson()) {
            return ResponseHelper::successWithPaginationV2(
                $filteredCatalog,
                $page,
                $limit,
                $filteredTotal,
                'Data catalog berhasil dimuat',
            );
        }

        return inertia('LMS/Training/Catalog/TrainingCatalogPage', [
            'trainingCatalog' => $filteredCatalog->toArray(),
            'initialPagination' => [
                'total' => $filteredTotal,
                'per_page' => $limit,
                'current_page' => $page,
                'total_pages' => ceil($filteredTotal / $limit),
            ],
        ]);
    }

    /**
     * Sort the catalog collection according to the specified sort criteria.
     */
    private function sortCatalog($catalog, string $sort)
    {
        return $catalog->sort(function ($left, $right) use ($sort) {
            $leftCreatedAt = ! empty($left['created_at']) ? strtotime((string) $left['created_at']) : 0;
            $rightCreatedAt = ! empty($right['created_at']) ? strtotime((string) $right['created_at']) : 0;
            $leftDate = ! empty($left['start_at']) ? strtotime((string) $left['start_at']) : (! empty($left['date']) ? strtotime((string) $left['date']) : 0);
            $rightDate = ! empty($right['start_at']) ? strtotime((string) $right['start_at']) : (! empty($right['date']) ? strtotime((string) $right['date']) : 0);
            $leftId = (int) ($left['id_penjadwalan_raw'] ?? 0);
            $rightId = (int) ($right['id_penjadwalan_raw'] ?? 0);
            $leftProgress = (int) ($left['progress'] ?? 0);
            $rightProgress = (int) ($right['progress'] ?? 0);
            $leftTitle = (string) ($left['title'] ?? '');
            $rightTitle = (string) ($right['title'] ?? '');

            // Timestamp jam penjadwalan / assignment diberikan ke user
            $leftScheduleTime = $leftCreatedAt > 0 ? $leftCreatedAt : $leftDate;
            $rightScheduleTime = $rightCreatedAt > 0 ? $rightCreatedAt : $rightDate;

            switch ($sort) {
                case 'newest':
                    if ($leftScheduleTime !== $rightScheduleTime) {
                        return $rightScheduleTime <=> $leftScheduleTime;
                    }
                    if ($leftId !== $rightId) {
                        return $rightId <=> $leftId;
                    }

                    return strcasecmp($leftTitle, $rightTitle);

                case 'progress-desc':
                    if ($leftProgress !== $rightProgress) {
                        return $rightProgress <=> $leftProgress;
                    }

                    return $rightScheduleTime <=> $leftScheduleTime;

                case 'progress-asc':
                    if ($leftProgress !== $rightProgress) {
                        return $leftProgress <=> $rightProgress;
                    }

                    return $rightScheduleTime <=> $leftScheduleTime;

                case 'title-asc':
                    return strcasecmp($leftTitle, $rightTitle);

                case 'title-desc':
                    return strcasecmp($rightTitle, $leftTitle);

                case 'completed-first':
                    $leftRank = $left['status'] === 'completed' ? 1 : ($left['status'] === 'in-progress' ? 2 : 3);
                    $rightRank = $right['status'] === 'completed' ? 1 : ($right['status'] === 'in-progress' ? 2 : 3);
                    if ($leftRank !== $rightRank) {
                        return $leftRank <=> $rightRank;
                    }

                    return $rightScheduleTime <=> $leftScheduleTime;

                case 'in-progress-first':
                    $leftRank = ($left['status'] === 'in-progress' && $leftProgress > 0) ? 1 : (($left['status'] === 'in-progress') ? 2 : ($left['status'] === 'not-started' ? 3 : 4));
                    $rightRank = ($right['status'] === 'in-progress' && $rightProgress > 0) ? 1 : (($right['status'] === 'in-progress') ? 2 : ($right['status'] === 'not-started' ? 3 : 4));
                    if ($leftRank !== $rightRank) {
                        return $leftRank <=> $rightRank;
                    }

                    return $rightScheduleTime <=> $leftScheduleTime;

                case 'default':
                case 'featured':
                default:
                    // 1: Baru Dijadwalkan ke User (progress == 0 / belum mulai) -> jam penjadwalan terbaru paling depan (Created_At desc / Id_Penjadwalan desc)
                    // 2: Sedang Progress (progress > 0 && progress < 100)
                    // 3: Selesai (completed / progress >= 100)
                    $getRank = function ($item) {
                        $status = $item['status'] ?? 'not-started';
                        $prog = (int) ($item['progress'] ?? 0);
                        if ($status === 'completed' || $prog >= 100) {
                            return 3;
                        }
                        if ($prog > 0) {
                            return 2;
                        }

                        return 1;
                    };

                    $rankL = $getRank($left);
                    $rankR = $getRank($right);

                    if ($rankL !== $rankR) {
                        return $rankL <=> $rankR;
                    }

                    // Di dalam masing-masing kelompok (Baru, Sedang Progress, Selesai),
                    // selalu urutkan dari yang terakhir/paling baru ditugaskan ke yang lama:
                    if ($leftScheduleTime !== $rightScheduleTime) {
                        return $rightScheduleTime <=> $leftScheduleTime;
                    }

                    if ($leftId !== $rightId) {
                        return $rightId <=> $leftId;
                    }

                    if ($leftDate !== $rightDate) {
                        return $rightDate <=> $leftDate;
                    }

                    return strcasecmp($leftTitle, $rightTitle);
            }
        })->values();
    }

    /**
     * Display the specified training detail.
     */
    public function show(string $id)
    {
        $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
        $gateData = $this->resolveTrainingGateData($realId);

        if (($gateData['requires_checkin'] ?? false) === true) {
            return inertia('LMS/Training/Catalog/Detail/detail/trainingCheckInGate', [
                'courseId' => $id,
                'penjadwalanId' => null,
                'gateData' => $gateData,
            ]);
        }

        return inertia('LMS/Training/Catalog/Detail/detail/trainingDetail', [
            'courseId' => $id,
            'penjadwalanId' => null,
        ]);
    }

    public function showBySchedule(string $penjadwalanId)
    {
        $realPenjadwalanId = Hashids::connection('custom')->decode($penjadwalanId)[0] ?? (int) $penjadwalanId;
        $kodeKaryawan = $this->resolveCurrentKodeKaryawan();

        if (! $kodeKaryawan) {
            abort(403, 'Akun karyawan tidak ditemukan.');
        }

        $penjadwalan = $this->trainingScheduleQueryService->resolvePenjadwalanByIdAndEmployee(
            (int) $realPenjadwalanId,
            $kodeKaryawan,
        );

        if (! $penjadwalan) {
            abort(404, 'Penjadwalan training tidak ditemukan.');
        }

        $materi = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
            (int) $penjadwalan->Id_Master_Paket,
        );

        if (! $materi || empty($materi->Id_Materi)) {
            abort(404, 'Materi training untuk penjadwalan ini tidak ditemukan.');
        }

        $courseId = Hashids::connection('custom')->encode((int) $materi->Id_Materi);
        $gateData = $this->resolveTrainingGateData((int) $materi->Id_Materi, $penjadwalan);

        if (($gateData['requires_checkin'] ?? false) === true) {
            return inertia('LMS/Training/Catalog/Detail/detail/trainingCheckInGate', [
                'courseId' => $courseId,
                'penjadwalanId' => $penjadwalanId,
                'gateData' => $gateData,
            ]);
        }

        return inertia('LMS/Training/Catalog/Detail/detail/trainingDetail', [
            'courseId' => $courseId,
            'penjadwalanId' => $penjadwalanId,
        ]);
    }

    private function getScheduledTrainings(string $kodeKaryawan)
    {
        $normalizedKodeKaryawan = strtoupper(trim($kodeKaryawan));

        $penjadwalans = $this->trainingScheduleQueryService
            ->byKodeKaryawanWithPaketQuery($normalizedKodeKaryawan, 'p')
            ->select([
                'p.Id_Penjadwalan',
                'p.Id_Master_Paket',
                'p.Flag_Selesai',
                'p.Waktu_Enroll',
                'mp.Nama_Paket',
                'mp.Deskripsi as Deskripsi_Paket',
            ])
            ->orderBy('p.Waktu_Enroll', 'desc')
            ->get();

        if ($penjadwalans->isEmpty()) {
            return collect();
        }

        // Sama seperti di index(): ringkasan diambil sekali untuk seluruh daftar.
        $idPenjadwalan = $penjadwalans->pluck('Id_Penjadwalan')->map(fn ($v) => (int) $v)->all();
        $ringkasan = $this->trainingProgressReadService->ringkasanMassal($idPenjadwalan);
        $sisaAktivitas = $this->penjadwalanYangMasihPunyaAktivitas($idPenjadwalan);

        return $penjadwalans->map(function ($penjadwalan) use ($ringkasan, $sisaAktivitas) {
            $materiData = $this->trainingScheduleQueryService->getFirstActiveMateriInPackageWithMetadata(
                (int) $penjadwalan->Id_Master_Paket,
            );

            $r = $ringkasan[(int) $penjadwalan->Id_Penjadwalan] ?? ['progress_percent' => 0, 'has_started' => false];

            $progress = (int) ($r['progress_percent'] ?? 0);
            $status = $this->computeStatus($penjadwalan, $sisaAktivitas, (bool) ($r['has_started'] ?? false));
            if ($status === 'not-started') {
                $progress = 0;
            }

            return [
                'id' => isset($materiData->Id_Materi)
                    ? Hashids::connection('custom')->encode($materiData->Id_Materi)
                    : Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan),
                'penjadwalan_id' => Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan),
                'title' => $materiData->Judul_Training ?? ($penjadwalan->Nama_Paket ?? 'Untitled Training'),
                'category' => $materiData->Nama_Kategori ?? 'General',
                'provider' => 'Internal Training',
                'date' => $penjadwalan->Waktu_Enroll ?? ($materiData->Tanggal ?? null),
                'start_at' => $penjadwalan->Waktu_Enroll,
                'duration' => '-',
                'level' => 'General',
                'progress' => $progress,
                'status' => $status,
                'image' => $this->resolveThumbnailUrl($materiData->Thumbnail_Training ?? null),
                'trainer' => 'Learning Team',
                'description' => $materiData->Deskripsi ?? ($penjadwalan->Deskripsi_Paket ?? ''),
                'tags' => [],
            ];
        });
    }

    /**
     * Penjadwalan mana yang MASIH punya aktivitas belum selesai.
     *
     * Menggantikan satu `exists()` per baris dengan satu kueri untuk seluruh
     * halaman. Yang dikembalikan adalah himpunan id — ketiadaan sebuah id
     * berarti "tak ada sisa aktivitas", jawaban yang sama dengan `exists()`
     * yang mengembalikan false.
     *
     * @param  array<int,int>  $ids
     * @return array<int,bool> berkunci Id_Penjadwalan
     */
    private function penjadwalanYangMasihPunyaAktivitas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $baris = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->whereIn('pd.Id_Penjadwalan', $ids)
            ->where(function ($query) {
                $query->whereNull('pdet.Flag_Selesai')->orWhere('pdet.Flag_Selesai', '!=', 'Y');
            })
            ->distinct()
            ->pluck('pd.Id_Penjadwalan')
            ->all();

        return array_fill_keys(array_map('intval', $baris), true);
    }

    /**
     * @param  array<int,bool>  $sisaAktivitas  hasil penjadwalanYangMasihPunyaAktivitas()
     * @param  bool  $sudahMulai  dari ringkasanMassal(); menggantikan hasStartedActivity() per baris
     */
    private function computeStatus($penjadwalan, array $sisaAktivitas, bool $sudahMulai): string
    {
        if ($penjadwalan->Flag_Selesai === 'Y') {
            return 'completed';
        }

        $hasRemainingActivities = isset($sisaAktivitas[(int) $penjadwalan->Id_Penjadwalan]);

        if (! $hasRemainingActivities) {
            DB::table('N_LMS_Penjadwalan')
                ->where('Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
                ->update(['Flag_Selesai' => 'Y']);

            return 'completed';
        }

        if (! $sudahMulai) {
            return 'not-started';
        }

        return 'in-progress';
    }

    /* computeProgress() DIHAPUS 5 Sep 2026.
       Ia hanya membungkus computeProgressSnapshot() per baris, dan kedua
       pemanggilnya kini membaca dari ringkasanMassal(). Membiarkannya berarti
       menyisakan jalan pintas yang mengembalikan pola per-baris tanpa disadari
       — persis cara cacat ini lahir pertama kali. */

    private function resolveThumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (! Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            try {
                return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
            } catch (\Throwable $e) {
                Log::warning('Gagal membuat temporary URL thumbnail Training Catalog', [
                    'path' => $path,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $path;
    }

    private function resolveCurrentKodeKaryawan(): ?string
    {
        if (! Auth::check()) {
            return null;
        }

        $user = Auth::user();

        $candidates = [
            data_get($user, 'karyawan.Kode_Karyawan'),
            DB::table('Karyawan')->where('UserID_Web', $user->Id_Users)->value('Kode_Karyawan'),
            $user->Username ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    private function resolveTrainingGateData(int $materiId, ?object $penjadwalan = null): array
    {
        $materi = DB::table('N_LMS_Materi')->where('Id_Materi', $materiId)->where('Flag_Aktif', 'Y')->first();

        $default = [
            'requires_checkin' => false,
            'penjadwalan_id' => $penjadwalan?->Id_Penjadwalan
                ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                : null,
            'materi' => [
                'id' => Hashids::connection('custom')->encode($materiId),
                'judul' => $materi->Judul_Training ?? 'Training',
                'deskripsi' => $materi->Deskripsi ?? '',
                'thumbnail' => $this->resolveThumbnailUrl($materi->Thumbnail_Training ?? null),
            ],
            'message' => '',
            'activity_item' => null,
        ];

        $penjadwalan = $penjadwalan ?: $this->resolvePenjadwalanForMateri($materiId);

        if (! $penjadwalan) {
            return [
                ...$default,
                'requires_checkin' => true,
                'message' => 'Data penjadwalan training untuk akun ini belum tersedia. Silakan hubungi admin training bila training sudah ditugaskan.',
            ];
        }

        $default['penjadwalan_id'] = Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan);

        $this->ensurePenjadwalanStructure((int) $penjadwalan->Id_Penjadwalan, $materiId);

        $checkInDone = DB::table('N_LMS_CHECKINOUT')
            ->where('Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('Output', 'In')
            ->exists();

        $absensiItem = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
            ->join('N_LMS_Materi_Det as det', 'md.Id_Materi_Detail', '=', 'det.Id_Materi_Detail')
            ->join('N_LMS_Penjadwalan_Det as pdet', function ($join) {
                $join
                    ->on('pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                    ->on('det.Id_Materi_Det', '=', 'pdet.Id_Materi_Det');
            })
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('md.Id_Master_Training', $materiId)
            ->where('det.Flag_Aktif', 'Y')
            ->where('akt.Kode_Aktivitas', 'ABS')
            ->orderBy('md.Urutan_Sub_Training')
            ->orderBy('det.Urutan_Materi')
            ->select([
                'pd.Id_Penjadwalan_Detail',
                'pdet.Id_Penjadwalan_Det',
                'det.Id_Materi_Det',
                'det.Id_Materi',
                'det.Urutan_Materi',
                'det.Keterangan_Level',
                'akt.Nama_Aktivitas',
                'akt.Flag_Third_Party',
            ])
            ->first();

        if (! $absensiItem) {
            return $default;
        }

        if ($checkInDone) {
            return $default;
        }

        return [
            ...$default,
            'requires_checkin' => true,
            'message' => 'Training ini memerlukan check-in terlebih dahulu. Setelah absensi berhasil, detail training akan terbuka otomatis.',
            'activity_item' => [
                'id_penjadwalan' => Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan),
                'id_penjadwalan_detail' => Hashids::connection('custom')->encode($absensiItem->Id_Penjadwalan_Detail),
                'id_penjadwalan_det' => Hashids::connection('custom')->encode($absensiItem->Id_Penjadwalan_Det),
                'id_materi' => Hashids::connection('custom')->encode($absensiItem->Id_Materi),
                'id_materi_det' => Hashids::connection('custom')->encode($absensiItem->Id_Materi_Det),
                'urutan_materi' => (int) ($absensiItem->Urutan_Materi ?? 0),
                'aktivitas_third_party' => $absensiItem->Flag_Third_Party ?? 'T',
                'nama_aktivitas' => $absensiItem->Nama_Aktivitas ?? 'Check-In Training',
                'keterangan_level' => $absensiItem->Keterangan_Level,
            ],
        ];
    }

    private function resolvePenjadwalanForMateri(int $materiId): ?object
    {
        $kodeKaryawan = $this->resolveCurrentKodeKaryawan();
        if (! $kodeKaryawan) {
            return null;
        }

        return $this->trainingScheduleQueryService->resolvePenjadwalanByMateriAndEmployee($materiId, $kodeKaryawan);
    }

    /**
     * Diteruskan ke PenjadwalanStrukturService.
     *
     * Badannya dulu 76 baris IDENTIK BYTE-PER-BYTE dengan salinan di controller
     * satunya. Pembungkus tipis ini dipertahankan supaya pemanggil di dalam
     * controller tak perlu berubah, dan supaya jelas bahwa logikanya kini
     * bertempat tinggal di satu tempat saja.
     */
    private function ensurePenjadwalanStructure(int $penjadwalanId, int $materiId): void
    {
        app(\App\Services\LMS\Training\Scheduling\PenjadwalanStrukturService::class)->pastikan($penjadwalanId, $materiId);
    }
}
