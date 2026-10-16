<?php

namespace App\Http\Controllers\LMS\Training\Catalog;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\TrainingScheduleQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class TrainingCatalogApi extends Controller
{
    public function __construct(private TrainingScheduleQueryService $trainingScheduleQueryService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

        $materi = DB::table('N_LMS_Materi')->where('Id_Materi', $realId)->where('Flag_Aktif', 'Y')->first();
        if (! $materi) {
            return response()->json(['success' => false, 'message' => 'Training tidak ditemukan'], 404);
        }

        $penjadwalan = $this->resolvePenjadwalanForMateri((int) $realId);

        // Thumbnail url resolver (optional, sesuaikan jika ada helper)
        $materi->thumbnail_url = $materi->Thumbnail_Training ? url('storage/'.$materi->Thumbnail_Training) : null;

        if (! $penjadwalan) {
            return ResponseHelper::error('Penjadwalan tidak ditemukan');
        }
        // if ($penjadwalan) {
        $sections = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Master_Konten as kon', 'det.Id_Master_Konten', '=', 'kon.Id_Master_Konten')
            ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('det.Id_Materi', $realId)
            ->where('det.Flag_Aktif', 'Y')
            ->orderBy('pd.Urutan')
            ->orderBy('det.Urutan_Materi')
            ->select([
                'pd.Id_Penjadwalan_Detail',
                'pdet.Id_Penjadwalan_Det',
                'det.Id_Materi_Detail',
                'det.Id_Materi_Det',
                'md.Nama_Sub',
                'det.Urutan_Materi',
                'det.Keterangan_Level',
                'det.Durasi',
                'act.Kode_Aktivitas',
                'act.Nama_Aktivitas',
                'kon.Judul_Konten',
                'kon.Tipe_Konten',
                'kon.Flag_Third_Party',
                'pd.Urutan as Urutan_Sub_Training',
            ])
            ->get()
            ->groupBy('Id_Materi_Detail')
            ->map(function ($items, $sectionId) {
                $first = $items->first();

                return [
                    'id' => $sectionId,
                    'nama_sub' => $first->Nama_Sub ?? '-',
                    'urutan' => $first->Urutan_Sub_Training ?? null,
                    'flag_aktif' => 'Y',
                    'items' => $items->values()->map(function ($item) {
                        return [
                            'id_materi_det' => $item->Id_Materi_Det,
                            'id_penjadwalan_detail' => $item->Id_Penjadwalan_Detail,
                            'id_penjadwalan_det' => $item->Id_Penjadwalan_Det,
                            'urutan_materi' => $item->Urutan_Materi,
                            'keterangan_level' => $item->Keterangan_Level,
                            'durasi' => $item->Durasi,
                            'kode_aktivitas' => $item->Kode_Aktivitas,
                            'nama_aktivitas' => $item->Nama_Aktivitas,
                            'konten' => [
                                'judul' => $item->Judul_Konten,
                                'tipe' => $item->Tipe_Konten,
                                'flag_third_party' => $item->Flag_Third_Party,
                            ],
                        ];
                    }),
                ];
            })
            ->values();
        // }
        // else {
        //     $sections = DB::table('N_LMS_Materi_Detail')
        //         ->where('Id_Master_Training', $realId)
        //         ->orderBy('Urutan_Sub_Training')
        //         ->get()
        //         ->map(function ($sec) use ($realId) {
        //             $items = DB::table('N_LMS_Materi_Det as d')
        //                 ->where('d.Id_Materi_Detail', $sec->Id_Materi_Detail)
        //                 ->where('d.Id_Materi', $realId)
        //                 ->orderBy('d.Urutan_Materi')
        //                 ->get();

        //             return [
        //                 'id' => $sec->Id_Materi_Detail,
        //                 'nama_sub' => $sec->Nama_Sub,
        //                 'urutan' => $sec->Urutan_Sub_Training,
        //                 'flag_aktif' => $sec->Flag_Aktif ?? 'Y',
        //                 'items' => $items,
        //             ];
        //         });
        // }

        return response()->json([
            'success' => true,
            'materi' => $materi,
            'penjadwalan_id' => $penjadwalan?->Id_Penjadwalan
                ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                : null,
            'sections' => $sections,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
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

    private function resolvePenjadwalanForMateri(int $materiId): ?object
    {
        $kodeKaryawan = $this->resolveCurrentKodeKaryawan();
        if (! $kodeKaryawan) {
            return null;
        }

        return $this->trainingScheduleQueryService->resolvePenjadwalanByMateriAndEmployee($materiId, $kodeKaryawan);
    }
}
