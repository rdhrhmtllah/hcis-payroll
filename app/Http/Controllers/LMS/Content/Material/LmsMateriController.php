<?php

namespace App\Http\Controllers\LMS\Content\Material;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\LmsSecurePdfService;
use App\Services\LMS\SecurePdfPageRenderService;
use App\Services\LMS\Training\Scoring\PenilaiEkspresiNumerik;
use App\Services\LMS\TrainingScheduleQueryService;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vinkla\Hashids\Facades\Hashids;

class LmsMateriController extends Controller
{
    private const SECURE_PDF_HANDSHAKE_TTL_SECONDS = 20;

    private const SECURE_PDF_VIEWER_SESSION_TTL_SECONDS = 300;

    private const SECURE_PDF_PAGE_NONCE_TTL_SECONDS = 20;

    private const SECURE_PDF_SIGNED_URL_TTL_SECONDS = 120;

    private const SECURE_PDF_STREAM_CHUNK_BYTES = 8192;

    private const SECURE_PDF_GRANT_RATE_LIMIT = 80;

    private const SECURE_PDF_PAGE_RATE_LIMIT = 120;

    private const SECURE_PDF_RATE_LIMIT_WINDOW_SECONDS = 60;

    public function __construct(
        private TrainingScheduleQueryService $trainingScheduleQueryService,
        private SecurePdfPageRenderService $securePdfPageRenderService,
    ) {}

    /**
     * Get the full training material structure for a given Materi ID.
     */
    public function show(Request $request, $id)
    {
        $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

        // 1. Fetch header info
        $materi = DB::table('N_LMS_Materi')->where('Id_Materi', $realId)->where('Flag_Aktif', 'Y')->first();

        if (! $materi) {
            return ResponseHelper::error('Materi tidak ditemukan', 404);
        }

        // Check if there is an active Penjadwalan for this user and materi
        $penjadwalan = $this->resolvePenjadwalanForMateri((int) $realId, $request->query('penjadwalan_id'));

        if (! $penjadwalan) {
            return ResponseHelper::error('Penjadwalan tidak ditemukan', 404);
        }

        $this->ensurePenjadwalanStructure((int) $penjadwalan->Id_Penjadwalan, (int) $realId);

        // dd($penjadwalan);

        // 2. Fetch sub-sections ordered by Urutan_Sub_Training
        if ($penjadwalan->Id_Penjadwalan || empty($penjadwalan->Id_Penjadwalan)) {
            $sections = DB::table('N_LMS_Penjadwalan_Detail as pd')
                ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
                ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
                ->where('md.Flag_Aktif', 'Y')
                ->where('md.Id_Master_Training', $realId)
                ->orderBy('md.Urutan_Sub_Training', 'asc')
                ->select('md.Id_Materi_Detail', 'md.Nama_Sub', 'md.Urutan_Sub_Training', 'pd.Id_Penjadwalan_Detail')
                ->get();
        }

        // dd($sections);
        // dd($sections->toSql(), $sections->getBindings());

        // else {
        //     $sections = DB::table('N_LMS_Materi_Detail')
        //         ->where('Id_Master_Training', $realId)
        //         ->where('Flag_Aktif', 'Y')
        //         ->orderBy('Urutan_Sub_Training', 'asc')
        //         ->get();
        // }

        if (empty($sections)) {
            return ResponseHelper::error('Detil materi tidak ditemukan', 404);
            Log::warning(
                "Materi ID {$realId} memiliki header tetapi tidak ada detil aktif. Mungkin ada masalah dengan data atau penjadwalan.",
            );
        }
        // dd($sections);

        $prasyaratMap = $this->getMateriDetPrerequisiteMap((int) $realId, $penjadwalan->Id_Penjadwalan ?? null);
        // dd($prasyaratMap);
        $prasyaratLabelMap = $this->getMateriDetPrerequisiteLabelMap((int) $realId, $prasyaratMap);

        // 3. For each section, fetch items with joined master data
        $sectionsWithItems = $sections->map(function ($section) use (
            $realId,
            $penjadwalan,
            $prasyaratMap,
            $prasyaratLabelMap,
        ) {
            $query = DB::table('N_LMS_Materi_Det as det')
                ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
                ->leftJoin('N_LMS_Master_Konten as kon', 'det.Id_Master_Konten', '=', 'kon.Id_Master_Konten')
                ->leftJoin(
                    'N_LMS_Master_Konten_Berkas as berkas',
                    'kon.Id_Master_Konten',
                    '=',
                    'berkas.Id_Master_Konten',
                )
                ->leftJoin('N_LMS_Master_Konten_Media as media', 'kon.Id_Master_Konten', '=', 'media.Id_Master_Konten');

            if ($penjadwalan && isset($section->Id_Penjadwalan_Detail)) {
                $query->join('N_LMS_Penjadwalan_Det as pdet', function ($join) use ($section) {
                    $join
                        ->on('det.Id_Materi_Det', '=', 'pdet.Id_Materi_Det')
                        ->where('pdet.Id_Penjadwalan_Detail', '=', $section->Id_Penjadwalan_Detail);
                });
                $query->leftJoin(
                    'N_LMS_Penjadwalan_Integrasi as pint',
                    'pdet.Id_Penjadwalan_Det',
                    '=',
                    'pint.Id_Penjadwalan_Det',
                );

                $query->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                    $join
                        ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                        ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                        ->on('trx_detail.Id_Materi_Det', '=', 'det.Id_Materi_Det');
                });
                $query->leftJoin(
                    'N_LMS_Transaksi_Training_Det as trx_det',
                    'trx_det.Id_Transaksi_Training_Detail',
                    '=',
                    'trx_detail.Id_Transaksi_Training_Detail',
                );

                $query->addSelect([
                    'pdet.Id_Penjadwalan_Det',
                    'pdet.Flag_Selesai as Det_Flag_Selesai',
                    'pdet.Flag_Checkin as Det_Flag_Checkin',
                    'pdet.Waktu_Mulai_Akses',
                    'pdet.Waktu_Batas_Akses',
                    'pint.Id_Penjadwalan_Integrasi',
                    'pint.Waktu_Mulai as Integrasi_Waktu_Mulai',
                    'pint.Waktu_Akhir as Integrasi_Waktu_Akhir',
                    'pint.Payload_Json as Integrasi_Payload_Json',
                    'pint.Id_Master_Ujian as Integrasi_Id_Master_Ujian',
                    'trx_det.Skor_Akhir',
                ]);
            }

            $query
                ->where('det.Id_Materi', $realId)
                ->where('det.Id_Materi_Detail', $section->Id_Materi_Detail)
                ->where('det.Flag_Aktif', 'Y')
                ->orderBy('det.Urutan_Materi', 'asc')
                ->addSelect([
                    'det.Id_Materi_Det',
                    'det.Id_Materi',
                    'det.Id_Materi_Detail',
                    'det.Id_Master_Aktivitas',
                    'det.Id_Master_Konten',
                    'det.Id_Master_Rumus',
                    'det.Keterangan_Level',
                    'det.Urutan_Materi',
                    'det.Durasi',
                    'det.Flag_Download',
                    'akt.Kode_Aktivitas',
                    'akt.Nama_Aktivitas',
                    'akt.Flag_Third_Party as Aktivitas_Third_Party',
                    'kon.Id_Master_Konten as Konten_Id',
                    'kon.Judul_Konten',
                    'kon.Tipe_Konten',
                    'kon.Flag_Third_Party as Konten_Third_Party',
                    'berkas.Id_Master_Konten_Berkas',
                    'media.Provider_Media_Id',
                ]);

            // dd($query->toSql(), $query->getBindings());

            $items = $query
                ->get()
                ->map(function ($item) use ($penjadwalan, $section, $prasyaratMap, $prasyaratLabelMap) {
                    $konten = null;
                    if ($item->Konten_Id) {
                        $konten = [
                            'id' => Hashids::connection('custom')->encode($item->Konten_Id),
                            'judul' => $item->Judul_Konten,
                            'tipe' => $item->Tipe_Konten,
                            'flag_third_party' => $item->Konten_Third_Party,
                        ];

                        if (($item->Flag_Download ?? 'T') === 'Y' && ! empty($item->Id_Master_Konten_Berkas)) {
                            $encodedBerkasId = Hashids::connection('custom')->encode($item->Id_Master_Konten_Berkas);
                            $konten['download_url'] = url("/api/v1/lms/materi/download-file/{$encodedBerkasId}");
                        }

                        if (
                            ($item->Flag_Download ?? 'T') === 'Y' &&
                            ($item->Konten_Third_Party ?? 'T') === 'Y' &&
                            ! empty($item->Provider_Media_Id)
                        ) {
                            $konten['download_url'] = $this->resolveBunnyVideoDownloadUrl(
                                (string) $item->Provider_Media_Id,
                            );
                        }
                    }

                    $integrasiPayload = $this->decodeIntegrasiPayload($item->Integrasi_Payload_Json ?? null);

                    $status = 'not-started';
                    if (isset($item->Det_Flag_Selesai) && $item->Det_Flag_Selesai === 'Y') {
                        $status = 'done';
                    } elseif (
                        ($item->Kode_Aktivitas ?? null) === 'ABS' &&
                        isset($item->Det_Flag_Checkin) &&
                        $item->Det_Flag_Checkin === 'Y'
                    ) {
                        $status = 'done';
                    } elseif (isset($item->Det_Flag_Checkin) && $item->Det_Flag_Checkin === 'Y') {
                        $status = 'in-progress';
                    }

                    $mapped = [
                        'id_materi_det' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
                        'id_materi_det_syarat' => isset($prasyaratMap[$item->Id_Materi_Det])
                            ? Hashids::connection('custom')->encode($prasyaratMap[$item->Id_Materi_Det])
                            : null,
                        'label_materi_det_syarat' => $prasyaratLabelMap[$item->Id_Materi_Det] ?? null,
                        'id_materi' => Hashids::connection('custom')->encode($item->Id_Materi),
                        'id_materi_detail' => Hashids::connection('custom')->encode($item->Id_Materi_Detail),
                        'id_penjadwalan' => $penjadwalan
                            ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                            : null,
                        'id_penjadwalan_detail' => isset($section->Id_Penjadwalan_Detail)
                            ? Hashids::connection('custom')->encode($section->Id_Penjadwalan_Detail)
                            : null,
                        'id_master_aktivitas' => $item->Id_Master_Aktivitas
                            ? Hashids::connection('custom')->encode($item->Id_Master_Aktivitas)
                            : null,
                        'id_master_rumus' => $item->Id_Master_Rumus
                            ? Hashids::connection('custom')->encode($item->Id_Master_Rumus)
                            : null,
                        'kode_aktivitas' => $item->Kode_Aktivitas,
                        'nama_aktivitas' => $item->Nama_Aktivitas,
                        'aktivitas_third_party' => $item->Aktivitas_Third_Party,
                        'konten' => $konten,
                        'keterangan_level' => $item->Keterangan_Level,
                        'urutan_materi' => $item->Urutan_Materi,
                        'durasi' => $item->Durasi,
                        'flag_download' => $item->Flag_Download,
                    ];

                    if (isset($item->Id_Penjadwalan_Det)) {
                        $mapped['id_penjadwalan_det'] = Hashids::connection('custom')->encode(
                            $item->Id_Penjadwalan_Det,
                        );
                        $mapped['status_penjadwalan'] = $status;
                        $mapped['waktu_mulai_akses'] = $item->Waktu_Mulai_Akses;
                        $mapped['waktu_batas_akses'] = $item->Waktu_Batas_Akses;
                        if (($item->Aktivitas_Third_Party ?? 'T') === 'Y') {
                            $mapped['jadwal_mulai'] = $item->Integrasi_Waktu_Mulai ?? $item->Waktu_Mulai_Akses;
                            $mapped['jadwal_akhir'] = $item->Integrasi_Waktu_Akhir ?? $item->Waktu_Batas_Akses;
                            /* ?? null — WAJIB. decodeIntegrasiPayload() memang
                               mengembalikan array KOSONG untuk baris yang belum
                               punya sesi ujian, dan di data ini 102 dari 163
                               baris integrasi berpayload NULL: aktivitas
                               pihak-ketiga yang sudah dijadwalkan tapi tokennya
                               belum dicetak. Membacanya tanpa penjaga membuat
                               SELURUH halaman detail materi 500, bukan cuma
                               kehilangan satu tombol. */
                            $mapped['link_ujian'] = $this->applyAdditionalIntegrasiData(
                                $integrasiPayload['Link_Ujian'] ?? null,
                                $penjadwalan->Id_Penjadwalan ?? null,
                            );
                            $mapped['id_penjadwalan_integrasi'] = isset($item->Id_Penjadwalan_Integrasi)
                                ? Hashids::connection('custom')->encode($item->Id_Penjadwalan_Integrasi)
                                : null;
                            $mapped['id_master_ujian_integrasi'] = isset($item->Integrasi_Id_Master_Ujian)
                                ? Hashids::connection('custom')->encode($item->Integrasi_Id_Master_Ujian)
                                : null;
                            $mapped['skor_akhir'] = $item->Skor_Akhir ?? null;
                        }
                        $mapped['flag_selesai'] = $item->Det_Flag_Selesai;
                        $mapped['flag_checkin'] = $item->Det_Flag_Checkin;
                    }

                    return $mapped;
                });

            return [
                'id_materi_detail' => Hashids::connection('custom')->encode($section->Id_Materi_Detail),
                'nama_sub' => $section->Nama_Sub ?? $section->Urutan_Sub_Training,
                'urutan' => $section->Urutan_Sub_Training ?? $section->Urutan,
                'items' => $items->values()->toArray(),
            ];
        });

        $finalScoreSummary = $this->buildFinalScoreSummary($penjadwalan, (int) $realId);
        $trainingResultSummary = $this->buildTrainingResultSummary($penjadwalan, (int) $realId);

        return ResponseHelper::success([
            'materi' => [
                'id' => Hashids::connection('custom')->encode($materi->Id_Materi),
                'penjadwalan_id' => $penjadwalan
                    ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                    : null,
                'id_training_kategori' => $materi->Id_Training_Kategori
                    ? Hashids::connection('custom')->encode($materi->Id_Training_Kategori)
                    : null,
                'id_training_tipe' => $materi->Id_Training_Tipe
                    ? Hashids::connection('custom')->encode($materi->Id_Training_Tipe)
                    : null,
                'judul' => $materi->Judul_Training,
                'deskripsi' => $materi->Deskripsi,
                'outline_session' => $this->decodeMetaList($materi->Outline_Session ?? null),
                'metode' => $this->decodeMetaList($materi->Metode ?? null),
                'durasi' => $this->decodeMetaList($materi->Durasi ?? null),
                'total_durasi' => $materi->Total_Durasi ?? null,
                'sasaran_peserta' => $this->decodeMetaList($materi->Sasaran_Peserta ?? null),
                'standar_kelulusan' => $materi->Standar_Kelulusan ?? null,
                'tanggal' => $materi->Tanggal,
                'jam' => $materi->Jam,
                'thumbnail' => $this->resolveThumbnailUrl($materi->Thumbnail_Training),
            ],
            'sections' => $sectionsWithItems->values()->toArray(),
            'final_score_summary' => $finalScoreSummary,
            'training_result' => $trainingResultSummary,
        ]);
    }

    /**
     * Display the Netflix-style viewer page.
     */
    public function viewer(string $materiId, string $materiDetId)
    {
        return inertia('LMS/Training/Catalog/Detail/detail/trainingViewer', [
            'materiId' => $materiId,
            'materiDetId' => $materiDetId,
            'penjadwalanId' => request()->query('penjadwalan_id'),
        ]);
    }

    /**
     * Get content data for the viewer.
     */
    // public function getKonten(Request $request, string $materiId, string $materiDetId)
    // {
    //     $realMateriId = Hashids::connection('custom')->decode($materiId)[0] ?? (int) $materiId;
    //     $realMateriDetId = Hashids::connection('custom')->decode($materiDetId)[0] ?? (int) $materiDetId;

    //     // Get the requested item details
    //     $item = DB::table('N_LMS_Materi_Det as det')
    //         ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
    //         ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
    //         ->where('det.Id_Materi_Det', $realMateriDetId)
    //         ->where('det.Id_Materi', $realMateriId)
    //         ->select([
    //             'det.*',
    //             'act.Kode_Aktivitas',
    //             'act.Nama_Aktivitas',
    //             'k.Id_Master_Konten',
    //             'k.Judul_Konten',
    //             'k.Tipe_Konten',
    //             'k.Flag_Third_Party',
    //         ])
    //         ->first();

    //     if (!$item) {
    //         return ResponseHelper::error('Item tidak ditemukan', 404);
    //     }

    //     $penjadwalan = $this->resolvePenjadwalanForMateri(
    //         (int) $realMateriId,
    //         $request->query('penjadwalan_id'),
    //         $request->query('target_kode_karyawan'),
    //     );

    //     if (!$penjadwalan) {
    //         return ResponseHelper::error('Penjadwalan tidak ditemukan', 404);
    //     }

    //     $currentItemProgress = null;

    //     $this->ensurePenjadwalanStructure((int) $penjadwalan->Id_Penjadwalan, (int) $realMateriId);

    //     $currentItemProgress = DB::table('N_LMS_Penjadwalan_Detail as pd')
    //         ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
    //         ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
    //         ->where('pdet.Id_Materi_Det', $realMateriDetId)
    //         ->select([
    //             'pd.Id_Penjadwalan_Detail',
    //             'pdet.Id_Penjadwalan_Det',
    //             'pdet.Flag_Checkin',
    //             'pdet.Flag_Selesai',
    //             'pdet.Waktu_Mulai_Akses',
    //             'pdet.Waktu_Selesai',
    //         ])
    //         ->first();

    //     if (!$currentItemProgress) {
    //         return ResponseHelper::error('Item tidak tersedia pada penjadwalan ini', 404);
    //     }

    //     $currentScheduledSection = null;
    //     if ($penjadwalan) {
    //         $currentScheduledSection = DB::table('N_LMS_Penjadwalan_Detail as pd')
    //             ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
    //             ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
    //             ->where('pdet.Id_Materi_Det', $realMateriDetId)
    //             ->select(['pd.Id_Penjadwalan_Detail'])
    //             ->first();
    //     }

    //     // Sidebar must follow the penjadwalan snapshot when it exists.
    //     // The master materi table is only the syllabus source and can change later.
    //     if ($penjadwalan && $currentScheduledSection && $currentScheduledSection->Id_Penjadwalan_Detail) {
    //         $sectionItems = DB::table('N_LMS_Penjadwalan_Det as pdet')
    //             ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
    //             ->leftJoin('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
    //             ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
    //             ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
    //             ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
    //             ->where('pdet.Id_Penjadwalan_Detail', $currentScheduledSection->Id_Penjadwalan_Detail)
    //             ->where(function ($query) {
    //                 $query->whereNull('det.Id_Materi_Det')->orWhere('det.Flag_Aktif', 'Y');
    //             })
    //             ->select([
    //                 'pdet.Id_Materi_Det',
    //                 'pdet.Id_Penjadwalan_Det',
    //                 'det.Urutan_Materi',
    //                 'det.Keterangan_Level',
    //                 'det.Durasi',
    //                 'act.Kode_Aktivitas',
    //                 'act.Nama_Aktivitas',
    //                 'k.Judul_Konten',
    //                 'k.Tipe_Konten',
    //                 'k.Flag_Third_Party',
    //             ])
    //             ->orderBy('pdet.Id_Penjadwalan_Det')
    //             ->get();
    //     } else {
    //         $sectionItems = DB::table('N_LMS_Materi_Det as det')
    //             ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
    //             ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
    //             ->where('det.Id_Materi_Detail', $item->Id_Materi_Detail)
    //             ->where('det.Flag_Aktif', 'Y')
    //             ->select([
    //                 'det.Id_Materi_Det',
    //                 'det.Urutan_Materi',
    //                 'det.Keterangan_Level',
    //                 'det.Durasi',
    //                 'act.Kode_Aktivitas',
    //                 'act.Nama_Aktivitas',
    //                 'k.Judul_Konten',
    //                 'k.Tipe_Konten',
    //                 'k.Flag_Third_Party',
    //             ])
    //             ->orderBy('det.Urutan_Materi')
    //             ->get();
    //     }

    //     $sidebar = $sectionItems->map(function ($si) {
    //         $type = $si->Kode_Aktivitas;
    //         if ($si->Tipe_Konten === 'video' && !in_array($type, ['PRT', 'PTT', 'QIZ', 'ELS', 'ABS'])) {
    //             $type = 'VIDEO';
    //         }
    //         return [
    //             'id' => Hashids::connection('custom')->encode($si->Id_Materi_Det),
    //             'order' => $si->Urutan_Materi,
    //             'title' => $si->Keterangan_Level ?: ($si->Judul_Konten ?: $si->Nama_Aktivitas),
    //             'type' => $type,
    //             'duration' => $si->Durasi,
    //         ];
    //     });

    //     // Resolve Content URL/Data
    //     $contentData = null;

    //     if ($item->Id_Master_Konten) {
    //         if ($item->Flag_Third_Party === 'Y') {
    //             // Video (Bunny)
    //             $media = DB::table('N_LMS_Master_Konten_Media')
    //                 ->where('Id_Master_Konten', $item->Id_Master_Konten)
    //                 ->first();

    //             if ($media && $media->Provider_Media_Id) {
    //                 $libraryId = config('services.lms.bunny.library_id');
    //                 $videoId = $media->Provider_Media_Id;

    //                 $contentData = [
    //                     'type' => 'video',
    //                     'embed_url' => "https://iframe.mediadelivery.net/embed/{$libraryId}/{$videoId}?autoplay=true&loop=false&muted=false&preload=true&responsive=true",
    //                 ];
    //             }
    //         } else {
    //             // Berkas (GCS / Local)
    //             $berkas = DB::table('N_LMS_Master_Konten_Berkas')
    //                 ->where('Id_Master_Konten', $item->Id_Master_Konten)
    //                 ->first();

    //             if ($berkas && $berkas->Id_Master_Konten_Berkas) {
    //                 $contentData = [
    //                     'type' => 'secure-pdf',
    //                     'berkas_id' => Hashids::connection('custom')->encode($berkas->Id_Master_Konten_Berkas),
    //                 ];
    //             }
    //         }
    //     }

    //     return ResponseHelper::success([
    //         'item' => [
    //             'id' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
    //             'id_materi' => $materiId,
    //             'id_materi_det' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
    //             'id_penjadwalan' => $penjadwalan
    //                 ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
    //                 : null,
    //             'id_penjadwalan_detail' =>
    //                 $currentItemProgress && $currentItemProgress->Id_Penjadwalan_Detail
    //                     ? Hashids::connection('custom')->encode($currentItemProgress->Id_Penjadwalan_Detail)
    //                     : null,
    //             'id_penjadwalan_det' =>
    //                 $currentItemProgress && $currentItemProgress->Id_Penjadwalan_Det
    //                     ? Hashids::connection('custom')->encode($currentItemProgress->Id_Penjadwalan_Det)
    //                     : null,
    //             'urutan_materi' => $item->Urutan_Materi,
    //             'aktivitas_third_party' => $item->Flag_Third_Party,
    //             'flag_checkin' => $currentItemProgress->Flag_Checkin ?? null,
    //             'flag_selesai' => $currentItemProgress->Flag_Selesai ?? null,
    //             'title' => $item->Keterangan_Level ?: $item->Judul_Konten,
    //             'type' => $item->Tipe_Konten,
    //             'description' => '',
    //         ],
    //         'content' => $contentData,
    //         'sidebar' => $sidebar,
    //     ]);
    // }

    public function getKonten(Request $request, string $materiId, string $materiDetId)
    {
        $realMateriId = Hashids::connection('custom')->decode($materiId)[0] ?? (int) $materiId;
        $realMateriDetId = Hashids::connection('custom')->decode($materiDetId)[0] ?? (int) $materiDetId;

        // Get the requested item details
        $item = DB::table('N_LMS_Materi_Det as det')
            ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            ->where('det.Id_Materi_Det', $realMateriDetId)
            ->where('det.Id_Materi', $realMateriId)
            ->select([
                'det.*',
                'act.Kode_Aktivitas',
                'act.Nama_Aktivitas',
                'k.Id_Master_Konten',
                'k.Judul_Konten',
                'k.Tipe_Konten',
                'k.Flag_Third_Party',
            ])
            ->first();

        if (! $item) {
            return ResponseHelper::error('Item tidak ditemukan', 404);
        }

        $penjadwalan = $this->resolvePenjadwalanForMateri(
            (int) $realMateriId,
            $request->query('penjadwalan_id'),
            $request->query('target_kode_karyawan'),
        );

        if (! $penjadwalan) {
            return ResponseHelper::error('Penjadwalan tidak ditemukan', 404);
        }

        $currentItemProgress = null;

        $this->ensurePenjadwalanStructure((int) $penjadwalan->Id_Penjadwalan, (int) $realMateriId);

        $currentItemProgress = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('pdet.Id_Materi_Det', $realMateriDetId)
            ->select([
                'pd.Id_Penjadwalan_Detail',
                'pdet.Id_Penjadwalan_Det',
                'pdet.Flag_Checkin',
                'pdet.Flag_Selesai',
                'pdet.Waktu_Mulai_Akses',
                'pdet.Waktu_Selesai',
            ])
            ->first();

        if (! $currentItemProgress) {
            return ResponseHelper::error('Item tidak tersedia pada penjadwalan ini', 404);
        }

        $currentScheduledSection = null;
        if ($penjadwalan) {
            $currentScheduledSection = DB::table('N_LMS_Penjadwalan_Detail as pd')
                ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
                ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
                ->where('pdet.Id_Materi_Det', $realMateriDetId)
                ->select(['pd.Id_Penjadwalan_Detail'])
                ->first();
        }

        if ($penjadwalan && $currentScheduledSection && $currentScheduledSection->Id_Penjadwalan_Detail) {
            $sectionItems = DB::table('N_LMS_Penjadwalan_Det as pdet')
                ->join('N_LMS_Penjadwalan_Detail as pd', 'pdet.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                ->leftJoin('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
                ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
                ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
                ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
                ->where('pdet.Id_Penjadwalan_Detail', $currentScheduledSection->Id_Penjadwalan_Detail)
                ->where(function ($query) {
                    $query->whereNull('det.Id_Materi_Det')->orWhere('det.Flag_Aktif', 'Y');
                })
                ->select([
                    'pdet.Id_Materi_Det',
                    'pdet.Id_Penjadwalan_Det',
                    'det.Urutan_Materi',
                    'det.Keterangan_Level',
                    'det.Durasi',
                    'act.Kode_Aktivitas',
                    'act.Nama_Aktivitas',
                    'k.Judul_Konten',
                    'k.Tipe_Konten',
                    'k.Flag_Third_Party',
                ])
                ->orderBy('pdet.Id_Penjadwalan_Det')
                ->get();
        } else {
            $sectionItems = DB::table('N_LMS_Materi_Det as det')
                ->leftJoin('N_LMS_Master_Aktivitas as act', 'det.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
                ->leftJoin('N_LMS_Master_Konten as k', 'det.Id_Master_Konten', '=', 'k.Id_Master_Konten')
                ->where('det.Id_Materi_Detail', $item->Id_Materi_Detail)
                ->where('det.Flag_Aktif', 'Y')
                ->select([
                    'det.Id_Materi_Det',
                    'det.Urutan_Materi',
                    'det.Keterangan_Level',
                    'det.Durasi',
                    'act.Kode_Aktivitas',
                    'act.Nama_Aktivitas',
                    'k.Judul_Konten',
                    'k.Tipe_Konten',
                    'k.Flag_Third_Party',
                ])
                ->orderBy('det.Urutan_Materi')
                ->get();
        }

        $sidebar = $sectionItems->map(function ($si) {
            $type = $si->Kode_Aktivitas;
            if ($si->Tipe_Konten === 'video' && ! in_array($type, ['PRT', 'PTT', 'QIZ', 'ELS', 'ABS'])) {
                $type = 'VIDEO';
            }

            return [
                'id' => Hashids::connection('custom')->encode($si->Id_Materi_Det),
                'order' => $si->Urutan_Materi,
                'title' => $si->Keterangan_Level ?: ($si->Judul_Konten ?: $si->Nama_Aktivitas),
                'type' => $type,
                'duration' => $si->Durasi,
            ];
        });

        // Resolve Content URL/Data
        $contentData = null;

        if ($item->Id_Master_Konten) {
            if ($item->Flag_Third_Party === 'Y') {
                // Video (Bunny)
                $media = DB::table('N_LMS_Master_Konten_Media')
                    ->where('Id_Master_Konten', $item->Id_Master_Konten)
                    ->first();

                if ($media && $media->Provider_Media_Id) {
                    $libraryId = config('services.lms.bunny.library_id');
                    $videoId = $media->Provider_Media_Id;

                    $contentData = [
                        'type' => 'video',
                        'embed_url' => "https://iframe.mediadelivery.net/embed/{$libraryId}/{$videoId}?autoplay=true&loop=false&muted=false&preload=true&responsive=true",
                    ];
                }
            } else {
                // Berkas (GCS / Local)
                $berkas = DB::table('N_LMS_Master_Konten_Berkas')
                    ->where('Id_Master_Konten', $item->Id_Master_Konten)
                    ->first();

                if ($berkas && $berkas->Id_Master_Konten_Berkas) {
                    $isPdf = str_contains(strtolower($berkas->Type_File ?? ''), 'pdf');

                    if ($isPdf) {
                        $statusRender = $berkas->Status_Render ?? 'DONE'; // Fallback aman

                        if ($statusRender === 'DONE') {
                            $contentData = [
                                'type' => 'secure-pdf',
                                'berkas_id' => Hashids::connection('custom')->encode($berkas->Id_Master_Konten_Berkas),
                                'total_pages' => (int) ($berkas->Total_Halaman ?? 0),
                                'status' => 'ready',
                            ];
                        } else {
                            // PENDING → dispatch segera
                            // FAILED → allow retry dengan cooldown 5 menit (user klik "Coba Lagi")
                            if ($statusRender === 'PENDING' || $statusRender === 'FAILED') {
                                $this->maybeDispatchPdfConversionJob($berkas, $statusRender === 'FAILED');
                            }

                            $contentData = [
                                'type' => 'secure-pdf',
                                'status' => strtolower($statusRender),
                                'message' => $statusRender === 'FAILED'
                                    ? 'Gagal memproses dokumen. Klik "Coba Lagi" untuk memproses ulang.'
                                    : 'Dokumen sedang disiapkan untuk pengamanan, silakan kembali beberapa saat lagi.',
                            ];
                        }
                    } else {
                        // Jika bukan PDF (misal PPT/Excel), gunakan standard file
                        $contentData = [
                            'type' => 'standard-file',
                            'berkas_id' => Hashids::connection('custom')->encode($berkas->Id_Master_Konten_Berkas),
                        ];
                    }
                }
            }
        }

        return ResponseHelper::success([
            'item' => [
                'id' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
                'id_materi' => $materiId,
                'id_materi_det' => Hashids::connection('custom')->encode($item->Id_Materi_Det),
                'id_penjadwalan' => $penjadwalan
                    ? Hashids::connection('custom')->encode($penjadwalan->Id_Penjadwalan)
                    : null,
                'id_penjadwalan_detail' => $currentItemProgress && $currentItemProgress->Id_Penjadwalan_Detail
                        ? Hashids::connection('custom')->encode($currentItemProgress->Id_Penjadwalan_Detail)
                        : null,
                'id_penjadwalan_det' => $currentItemProgress && $currentItemProgress->Id_Penjadwalan_Det
                        ? Hashids::connection('custom')->encode($currentItemProgress->Id_Penjadwalan_Det)
                        : null,
                'urutan_materi' => $item->Urutan_Materi,
                'aktivitas_third_party' => $item->Flag_Third_Party,
                'flag_checkin' => $currentItemProgress->Flag_Checkin ?? null,
                'flag_selesai' => $currentItemProgress->Flag_Selesai ?? null,
                'title' => $item->Keterangan_Level ?: $item->Judul_Konten,
                'type' => $item->Tipe_Konten,
                'description' => '',
            ],
            'content' => $contentData,
            'sidebar' => $sidebar,
        ]);
    }

    public function getSecurePdfPages(Request $request, string $berkasId)
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $user = Auth::user();

        if (! $user || $request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Akses Ditolak', 403);
        }

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();

        if (! $berkas || ($berkas->Status_Render ?? 'DONE') !== 'DONE' || ! $berkas->Total_Halaman) {
            return ResponseHelper::error('Dokumen belum siap', 404);
        }

        $gcsDisk = Storage::disk('gcs');
        $baseFolder = "lms/konten/rendered/{$realId}";
        $pages = [];

        // Generate Signed URL untuk setiap halaman (valid selama 2 jam)
        for ($i = 1; $i <= $berkas->Total_Halaman; $i++) {
            $imagePath = "{$baseFolder}/page-{$i}.jpg";
            $pages[] = $gcsDisk->temporaryUrl($imagePath, now()->addHours(2));
        }

        // Data untuk watermark di sisi Client (Vue)
        $clientIp = $this->resolveSecurePdfClientIp($request);
        $watermark = [
            'user' => 'User : '.$this->resolveSecurePdfWatermarkUser($user),
            'ip' => $this->maskIpAddress($clientIp),
            'date' => now()->format('Y-m-d'),
            'property' => 'Property of Evo Group',
        ];

        return ResponseHelper::success([
            'pages' => $pages,
            'watermark' => $watermark,
            'total_pages' => $berkas->Total_Halaman,
        ]);
    }

    /**
     * Proxy PDF streaming.
     */
    public function streamPdf(string $berkasId)
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();

        if (! $berkas || ! $berkas->File_Path) {
            abort(404, 'File tidak ditemukan');
        }

        $service = new \App\Services\LMS\GCS\GcsService;
        $metadata = $service->getFileMetadata($berkas->File_Path);

        if (! $metadata['exists']) {
            abort(404, 'File fisik tidak ditemukan');
        }

        $disk = Storage::disk($metadata['disk']);
        $mimeType = $metadata['mime_type'] ?: 'application/pdf';

        return response()->stream(
            function () use ($disk, $berkas) {
                $stream = $disk->readStream($berkas->File_Path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.$metadata['file_name'].'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ],
        );
    }

    /**
     * STEP 1: Generate a one-time token for secure PDF streaming.
     */
    public function generatePdfToken(string $berkasId)
    {
        $request = request();
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }

        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $accessContext = $this->resolveSecurePdfAccessContext($request, $realId);

        if (! $accessContext) {
            return ResponseHelper::error('Akses materi tidak valid', 403);
        }

        $berkas = $accessContext->berkas;
        if (! $berkas || ! $berkas->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $service = new \App\Services\LMS\GCS\GcsService;
        // Hanya butuh cek keberadaan — fileExists() = 1 round-trip (vs getFileMetadata 4-5).
        $metadata = $service->fileExists($berkas->File_Path);

        if (! $metadata['exists']) {
            return ResponseHelper::error('File fisik tidak ditemukan', 404);
        }

        $tokenRaw = Str::random(40);
        $encryptedSignature = LmsSecurePdfService::generate($tokenRaw);
        $cacheKey = $this->securePdfTokenCacheKey((int) $user->Id_Users, (int) $realId, $tokenRaw);
        $this->pdfCache()->put(
            $cacheKey,
            [
                'user_id' => (int) $user->Id_Users,
                'berkas_id' => (int) $realId,
                'materi_id' => (int) $accessContext->materi_id,
                'materi_det_id' => (int) $accessContext->materi_det_id,
                'penjadwalan_id' => (int) $accessContext->penjadwalan_id,
                'watermark' => $this->buildSecurePdfWatermark($request, $user),
            ],
            self::SECURE_PDF_HANDSHAKE_TTL_SECONDS,
        );

        $manifestUrl =
            url("/api/v1/lms/materi/secure-pdf/pages/{$berkasId}/manifest").
            '?'.
            http_build_query([
                'x_signature' => $encryptedSignature,
                'materi_id' => $request->query('materi_id'),
                'materi_det_id' => $request->query('materi_det_id'),
                'penjadwalan_id' => $request->query('penjadwalan_id'),
                'target_kode_karyawan' => $request->query('target_kode_karyawan'),
            ]);

        return ResponseHelper::success([
            'signature' => $encryptedSignature,
            'expires_in' => self::SECURE_PDF_HANDSHAKE_TTL_SECONDS,
            'page_manifest_url' => $manifestUrl,
            'watermark' => $this->buildSecurePdfWatermark($request, $user),
        ]);
    }

    public function securePdfPageManifest(Request $request, string $berkasId)
    {
        $handshake = $this->consumeSecurePdfHandshake($request, $berkasId);
        if ($handshake instanceof \Illuminate\Http\JsonResponse) {
            return $handshake;
        }

        $berkas = $this->resolveBerkasRecord($berkasId);
        if (! $berkas || ! $berkas->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $manifest = $this->securePdfPageRenderService->getManifest((int) $handshake['berkas_id'], $berkas->File_Path);
        if (($manifest['total_pages'] ?? 0) < 1) {
            return ResponseHelper::error('Dokumen tidak memiliki halaman yang dapat ditampilkan', 422);
        }

        $this->securePdfPageRenderService->ensurePageRendered(
            (int) $handshake['berkas_id'],
            $berkas->File_Path,
            1,
            $manifest,
        );

        $viewerTokenRaw = Str::random(48);
        $viewerSignature = LmsSecurePdfService::generate($viewerTokenRaw);
        $this->pdfCache()->put(
            $this->securePdfViewerCacheKey((int) $handshake['user_id'], (int) $handshake['berkas_id'], $viewerTokenRaw),
            array_merge($handshake, [
                'fingerprint' => $this->buildSecurePdfViewerFingerprint($request),
            ]),
            self::SECURE_PDF_VIEWER_SESSION_TTL_SECONDS,
        );

        $pageBaseUrl = url("/api/v1/lms/materi/secure-pdf/pages/{$berkasId}");

        return ResponseHelper::success([
            'viewer_signature' => $viewerSignature,
            'expires_in' => self::SECURE_PDF_VIEWER_SESSION_TTL_SECONDS,
            'total_pages' => (int) $manifest['total_pages'],
            'pages' => $manifest['pages'] ?? [],
            'page_url_base' => $pageBaseUrl,
            'watermark' => $handshake['watermark'] ?? [],
        ]);
    }

    public function securePdfPageGrant(Request $request, string $berkasId, int $page)
    {
        $viewerPayload = $this->resolveSecurePdfViewerSession($request, $berkasId);
        if ($viewerPayload instanceof \Illuminate\Http\JsonResponse) {
            return $viewerPayload;
        }

        if ($page < 1) {
            return ResponseHelper::error('Nomor halaman tidak valid', 422);
        }

        $rateLimited = $this->ensureSecurePdfRateLimit(
            'grant',
            (int) ($viewerPayload['user_id'] ?? 0),
            (int) ($viewerPayload['berkas_id'] ?? 0),
            $page,
            self::SECURE_PDF_GRANT_RATE_LIMIT,
        );
        if ($rateLimited instanceof \Illuminate\Http\JsonResponse) {
            return $rateLimited;
        }

        $berkas = $this->resolveBerkasRecord($berkasId);
        if (! $berkas || ! $berkas->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $manifest = $this->securePdfPageRenderService->getManifest(
            (int) $viewerPayload['berkas_id'],
            $berkas->File_Path,
        );
        if ($page > (int) ($manifest['total_pages'] ?? 0)) {
            return ResponseHelper::error('Halaman tidak ditemukan', 404);
        }

        $nonceRaw = Str::random(64);
        $pageNonce = LmsSecurePdfService::generate($nonceRaw);
        $this->pdfCache()->put(
            $this->securePdfPageNonceCacheKey(
                (int) $viewerPayload['user_id'],
                (int) $viewerPayload['berkas_id'],
                $page,
                $nonceRaw,
            ),
            [
                'status' => 'issued',
                'user_id' => (int) $viewerPayload['user_id'],
                'berkas_id' => (int) $viewerPayload['berkas_id'],
                'page' => $page,
                'materi_id' => (int) ($viewerPayload['materi_id'] ?? 0),
                'materi_det_id' => (int) ($viewerPayload['materi_det_id'] ?? 0),
                'penjadwalan_id' => (int) ($viewerPayload['penjadwalan_id'] ?? 0),
                'watermark' => $viewerPayload['watermark'] ?? [],
                'fingerprint' => $viewerPayload['fingerprint'] ?? [],
            ],
            self::SECURE_PDF_PAGE_NONCE_TTL_SECONDS,
        );

        return ResponseHelper::success([
            'page_nonce' => $pageNonce,
            'expires_in' => self::SECURE_PDF_PAGE_NONCE_TTL_SECONDS,
        ]);
    }

    public function securePdfPageImage(Request $request, string $berkasId, int $page)
    {
        $noncePayload = $this->consumeSecurePdfPageNonce($request, $berkasId, $page);
        if ($noncePayload instanceof \Illuminate\Http\JsonResponse) {
            return $noncePayload;
        }

        if ($page < 1) {
            return ResponseHelper::error('Nomor halaman tidak valid', 422);
        }

        $rateLimited = $this->ensureSecurePdfRateLimit(
            'page',
            (int) ($noncePayload['user_id'] ?? 0),
            (int) ($noncePayload['berkas_id'] ?? 0),
            $page,
            self::SECURE_PDF_PAGE_RATE_LIMIT,
        );
        if ($rateLimited instanceof \Illuminate\Http\JsonResponse) {
            return $rateLimited;
        }

        $berkas = $this->resolveBerkasRecord($berkasId);
        if (! $berkas || ! $berkas->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $manifest = $this->securePdfPageRenderService->getManifest(
            (int) $noncePayload['berkas_id'],
            $berkas->File_Path,
        );
        if ($page > (int) ($manifest['total_pages'] ?? 0)) {
            return ResponseHelper::error('Halaman tidak ditemukan', 404);
        }

        $rendered = $this->securePdfPageRenderService->ensurePageRendered(
            (int) $noncePayload['berkas_id'],
            $berkas->File_Path,
            $page,
            $manifest,
        );

        // Watermark diputuskan dari Flag_Download aktivitas (N_LMS_Materi_Det) yang sedang
        // dibuka — sumber yang sama dengan tombol "Download" di halaman detail. Aktivitas yang
        // boleh di-download (Flag_Download='Y') tampil bersih tanpa watermark karena user memang
        // sudah bisa mengunduh file aslinya. Fallback ke 'T' (watermark) bila konteks tak lengkap.
        $activityFlagDownload = $this->resolveActivityFlagDownload(
            (int) ($noncePayload['materi_id'] ?? 0),
            (int) ($noncePayload['materi_det_id'] ?? 0),
        );

        $responseBlob = $this->shouldApplySecurePdfWatermark($activityFlagDownload)
            ? $this->securePdfPageRenderService->createWatermarkedPageBlob($rendered, $noncePayload['watermark'] ?? [])
            : $this->securePdfPageRenderService->readRenderedPageBlob($rendered);

        return response($responseBlob, 200, [
            'Content-Type' => $rendered['content_type'] ?? 'image/jpeg',
            'Content-Disposition' => 'inline; filename="page-'.$page.'.jpg"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * STEP 2: Stream PDF securely.
     */
    public function streamPdfSecure(Request $request, string $berkasId)
    {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }

        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;

        // Validate source header
        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $signature = $request->input('x_signature') ?: $request->query('x_signature');
        if (! $signature) {
            return ResponseHelper::error('Access Denied: Signature Required', 403);
        }

        // Decrypt signature
        $decrypted = LmsSecurePdfService::decrypt($signature);
        if ($decrypted['success'] === false) {
            return ResponseHelper::error('Access Denied: Invalid Token Format', 403);
        }

        $tokenFromClient = $decrypted['value'];
        $cacheKey = $this->securePdfTokenCacheKey((int) $user->Id_Users, (int) $realId, $tokenFromClient);

        if (! $this->pdfCache()->has($cacheKey)) {
            return ResponseHelper::error('Link Expired or Replay Attack Detected. Please reload.', 403);
        }

        // Resolve file
        $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();

        if (! $berkas || ! $berkas->File_Path) {
            return ResponseHelper::error('File tidak ditemukan', 404);
        }

        $service = new \App\Services\LMS\GCS\GcsService;
        $metadata = $service->getFileMetadata($berkas->File_Path);

        if (! $metadata['exists']) {
            return ResponseHelper::error('File fisik tidak ditemukan', 404);
        }

        $disk = Storage::disk($metadata['disk']);
        $mimeType = $metadata['mime_type'] ?: 'application/pdf';
        $fileSize = $metadata['size_bytes'];

        if (! is_numeric($fileSize)) {
            try {
                $fileSize = $disk->size($berkas->File_Path);
            } catch (\Throwable) {
                $fileSize = null;
            }
        }

        $fileSize = is_numeric($fileSize) ? (int) $fileSize : null;

        if (! $fileSize || $fileSize < 1) {
            return ResponseHelper::error('Ukuran file tidak valid', 500);
        }

        $baseHeaders = $this->securePdfStreamHeaders($mimeType);
        $range = $this->parseSecurePdfRange($request->header('Range'), $fileSize);

        if ($range === false) {
            return response(
                '',
                416,
                array_merge($baseHeaders, [
                    'Content-Range' => 'bytes */'.$fileSize,
                    'Content-Length' => '0',
                ]),
            );
        }

        // return response()->stream(
        //     function () use ($disk, $berkas) {
        //         $stream = $disk->readStream($berkas->File_Path);
        //         fpassthru($stream);
        //         if (is_resource($stream)) {
        //             fclose($stream);
        //         }
        //     },
        //     200,
        //     [
        //         'Content-Type' => $mimeType,
        //         'Content-Disposition' => 'inline; filename="document.pdf"',
        //         'Cache-Control' => 'no-store, no-cache, must-revalidate',
        //         'Pragma' => 'no-cache',
        //         'Expires' => '0',
        //         'X-Content-Type-Options' => 'nosniff',
        //         'Accept-Ranges' => 'bytes',
        //     ],
        // );

        if (is_array($range)) {
            [$start, $end] = $range;
            $length = $end - $start + 1;

            return new StreamedResponse(
                function () use ($disk, $berkas, $start, $length) {
                    $stream = $disk->readStream($berkas->File_Path);

                    try {
                        $this->streamStoredFileBytes($stream, $start, $length);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                },
                206,
                array_merge($baseHeaders, [
                    'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
                    'Content-Length' => (string) $length,
                ]),
            );
        }

        return new StreamedResponse(
            function () use ($disk, $berkas, $fileSize) {
                $stream = $disk->readStream($berkas->File_Path);

                try {
                    $this->streamStoredFileBytes($stream, 0, $fileSize);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            },
            200,
            array_merge($baseHeaders, [
                'Content-Length' => (string) $fileSize,
            ]),
        );
    }

    /**
     * @return array{0:int,1:int}|false|null false = invalid range, null = no range
     */
    private function parseSecurePdfRange(?string $rangeHeader, int $fileSize): array|false|null
    {
        if (! is_string($rangeHeader) || trim($rangeHeader) === '') {
            return null;
        }

        if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return false;
        }

        $startRaw = $matches[1];
        $endRaw = $matches[2];

        if ($startRaw === '' && $endRaw === '') {
            return false;
        }

        if ($startRaw === '') {
            $suffixLength = (int) $endRaw;
            if ($suffixLength <= 0) {
                return false;
            }

            $suffixLength = min($suffixLength, $fileSize);

            return [$fileSize - $suffixLength, $fileSize - 1];
        }

        $start = (int) $startRaw;
        $end = $endRaw === '' ? $fileSize - 1 : (int) $endRaw;

        if ($start >= $fileSize || $end < $start) {
            return false;
        }

        return [$start, min($end, $fileSize - 1)];
    }

    /**
     * Stream a bounded byte range while tolerating non-seekable cloud streams.
     *
     * @param  resource|false  $stream
     */
    private function streamStoredFileBytes($stream, int $start, int $length): void
    {
        if (! is_resource($stream) || $length <= 0) {
            return;
        }

        $meta = stream_get_meta_data($stream);
        $isSeekable = (bool) ($meta['seekable'] ?? false);

        if ($start > 0) {
            if ($isSeekable) {
                fseek($stream, $start);
            } else {
                $this->discardStreamBytes($stream, $start);
            }
        }

        $remaining = $length;

        while ($remaining > 0 && ! feof($stream)) {
            $chunkSize = min(self::SECURE_PDF_STREAM_CHUNK_BYTES, $remaining);
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);

            if (function_exists('flush')) {
                flush();
            }
        }
    }

    /**
     * @param  resource  $stream
     */
    private function discardStreamBytes($stream, int $bytes): void
    {
        $remaining = $bytes;

        while ($remaining > 0 && ! feof($stream)) {
            $chunkSize = min(self::SECURE_PDF_STREAM_CHUNK_BYTES, $remaining);
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $remaining -= strlen($chunk);
        }
    }

    private function securePdfStreamHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ];
    }

    /**
     * @return array{url:string,transport:string,expires_in:int}
     */
    private function resolveSecurePdfStreamUrl(
        array $metadata,
        string $berkasId,
        string $filePath,
        string $encryptedSignature,
        ?string $preferredTransport = null,
    ): array {
        $forceAppStream = $preferredTransport === 'app-stream';

        if (($metadata['disk'] ?? null) === 'gcs' && ! $forceAppStream) {
            try {
                return [
                    'url' => Storage::disk('gcs')->temporaryUrl(
                        $filePath,
                        now()->addSeconds(self::SECURE_PDF_SIGNED_URL_TTL_SECONDS),
                        [
                            'ResponseContentDisposition' => 'inline',
                            'ResponseContentType' => 'application/pdf',
                        ],
                    ),
                    'transport' => 'signed-url',
                    'expires_in' => self::SECURE_PDF_SIGNED_URL_TTL_SECONDS,
                ];
            } catch (\Throwable) {
                // Fall back to the app-stream endpoint when signed URL generation is unavailable.
            }
        }

        return [
            'url' => url("/api/v1/lms/materi/secure-pdf/stream/{$berkasId}").
                '?x_signature='.
                rawurlencode($encryptedSignature),
            'transport' => 'app-stream',
            'expires_in' => self::SECURE_PDF_SIGNED_URL_TTL_SECONDS,
        ];
    }

    private function securePdfTokenCacheKey(int $userId, int $berkasId, string $tokenRaw): string
    {
        return 'lms_pdf_token_'.$userId.'_'.$berkasId.'_'.hash('sha256', $tokenRaw);
    }

    private function securePdfViewerCacheKey(int $userId, int $berkasId, string $tokenRaw): string
    {
        return 'lms_pdf_viewer_'.$userId.'_'.$berkasId.'_'.hash('sha256', $tokenRaw);
    }

    private function securePdfPageNonceCacheKey(int $userId, int $berkasId, int $page, string $tokenRaw): string
    {
        return 'lms_pdf_page_nonce_'.$userId.'_'.$berkasId.'_'.$page.'_'.hash('sha256', $tokenRaw);
    }

    private function securePdfPageNonceConsumedCacheKey(int $userId, int $berkasId, int $page, string $tokenRaw): string
    {
        return 'lms_pdf_page_nonce_consumed_'.
            $userId.
            '_'.
            $berkasId.
            '_'.
            $page.
            '_'.
            hash('sha256', $tokenRaw);
    }

    private function securePdfRateLimitCacheKey(
        string $action,
        int $userId,
        int $berkasId,
        ?int $page = null,
        ?string $fingerprintHash = null,
    ): string {
        return 'lms_pdf_rate_'.
            $action.
            '_'.
            $userId.
            '_'.
            $berkasId.
            '_'.
            ($page ?? 0).
            '_'.
            ($fingerprintHash ?: 'none');
    }

    private function consumeSecurePdfHandshake(Request $request, string $berkasId): array|\Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }

        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $signature = $request->query('x_signature') ?: $request->input('x_signature');
        if (! $signature) {
            return ResponseHelper::error('Signature handshake tidak ditemukan', 403);
        }

        $decrypted = LmsSecurePdfService::decrypt($signature);
        if (($decrypted['success'] ?? false) !== true) {
            return ResponseHelper::error('Signature handshake tidak valid', 403);
        }

        $cacheKey = $this->securePdfTokenCacheKey((int) $user->Id_Users, (int) $realId, (string) $decrypted['value']);
        $payload = $this->pdfCache()->pull($cacheKey);
        if (! is_array($payload)) {
            return ResponseHelper::error('Handshake kedaluwarsa. Silakan muat ulang dokumen.', 403);
        }

        return $payload;
    }

    private function resolveSecurePdfViewerSession(
        Request $request,
        string $berkasId,
    ): array|\Illuminate\Http\JsonResponse {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }

        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $signature = $request->query('viewer_signature') ?: $request->input('viewer_signature');
        if (! $signature) {
            return ResponseHelper::error('Viewer session tidak ditemukan', 403);
        }

        $decrypted = LmsSecurePdfService::decrypt($signature);
        if (($decrypted['success'] ?? false) !== true) {
            return ResponseHelper::error('Viewer session tidak valid', 403);
        }

        $cacheKey = $this->securePdfViewerCacheKey((int) $user->Id_Users, (int) $realId, (string) $decrypted['value']);
        $payload = $this->pdfCache()->get($cacheKey);
        if (! is_array($payload)) {
            return ResponseHelper::error('Akses viewer kedaluwarsa. Silakan muat ulang dokumen.', 403);
        }

        $fingerprintValidation = $this->validateSecurePdfFingerprint($payload['fingerprint'] ?? null, $request);
        if ($fingerprintValidation !== true) {
            return ResponseHelper::error($fingerprintValidation, 403);
        }

        return $payload;
    }

    private function consumeSecurePdfPageNonce(
        Request $request,
        string $berkasId,
        int $page,
    ): array|\Illuminate\Http\JsonResponse {
        $user = Auth::user();
        if (! $user) {
            return ResponseHelper::error('Unauthorized', 401);
        }

        if ($request->header('X-App-Source') !== 'vue-client') {
            return ResponseHelper::error('Access Denied: Invalid Source', 403);
        }

        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $pageNonce = $request->input('page_nonce');
        if (! filled($pageNonce)) {
            return ResponseHelper::error('Page grant tidak ditemukan', 403);
        }

        $decrypted = LmsSecurePdfService::decrypt($pageNonce);
        if (($decrypted['success'] ?? false) !== true) {
            return ResponseHelper::error('Page grant tidak valid', 403);
        }

        $cacheKey = $this->securePdfPageNonceCacheKey(
            (int) $user->Id_Users,
            (int) $realId,
            $page,
            (string) $decrypted['value'],
        );
        $consumedCacheKey = $this->securePdfPageNonceConsumedCacheKey(
            (int) $user->Id_Users,
            (int) $realId,
            $page,
            (string) $decrypted['value'],
        );

        $payload = $this->pdfCache()->pull($cacheKey);
        if (! is_array($payload)) {
            if ($this->pdfCache()->has($consumedCacheKey)) {
                return ResponseHelper::error('Page grant sudah digunakan. Silakan muat ulang halaman ini.', 403);
            }

            return ResponseHelper::error('Page grant kedaluwarsa atau tidak valid.', 403);
        }

        if ((int) ($payload['page'] ?? 0) !== $page) {
            return ResponseHelper::error('Page grant tidak cocok dengan halaman yang diminta.', 403);
        }

        $fingerprintValidation = $this->validateSecurePdfFingerprint($payload['fingerprint'] ?? null, $request);
        if ($fingerprintValidation !== true) {
            return ResponseHelper::error($fingerprintValidation, 403);
        }

        $this->pdfCache()->put($consumedCacheKey, ['status' => 'consumed'], self::SECURE_PDF_PAGE_NONCE_TTL_SECONDS);

        return $payload;
    }

    private function ensureSecurePdfRateLimit(
        string $action,
        int $userId,
        int $berkasId,
        ?int $page,
        int $maxAttempts,
    ): ?\Illuminate\Http\JsonResponse {
        $fingerprint = $this->buildSecurePdfViewerFingerprint(request());
        $fingerprintHash = data_get($fingerprint, 'fingerprint_hash');
        $cacheKey = $this->securePdfRateLimitCacheKey($action, $userId, $berkasId, $page, $fingerprintHash);

        if (! $this->pdfCache()->has($cacheKey)) {
            $this->pdfCache()->put($cacheKey, 1, self::SECURE_PDF_RATE_LIMIT_WINDOW_SECONDS);

            return null;
        }

        $attempts = (int) $this->pdfCache()->increment($cacheKey);
        if ($attempts > $maxAttempts) {
            return ResponseHelper::error('Terlalu banyak permintaan dokumen. Silakan tunggu sebentar.', 429);
        }

        return null;
    }

    private function buildSecurePdfViewerFingerprint(Request $request): array
    {
        $clientIp = $this->resolveSecurePdfClientIp($request);
        $laravelSession = (string) ($request->cookie(config('session.cookie')) ?: '');
        $userAgent = (string) $request->userAgent();
        $sessionHash = hash('sha256', $laravelSession);
        $userAgentHash = hash('sha256', $userAgent);
        $ipFingerprint = $this->buildSecurePdfIpFingerprint($clientIp);

        return [
            'session_hash' => $sessionHash,
            'user_agent_hash' => $userAgentHash,
            'ip_fingerprint' => $ipFingerprint,
            'fingerprint_hash' => hash('sha256', $sessionHash.'|'.$userAgentHash.'|'.$ipFingerprint),
        ];
    }

    private function validateSecurePdfFingerprint(?array $storedFingerprint, Request $request): bool|string
    {
        if (! is_array($storedFingerprint)) {
            return 'Fingerprint viewer tidak valid.';
        }

        $currentFingerprint = $this->buildSecurePdfViewerFingerprint($request);

        if (($storedFingerprint['session_hash'] ?? null) !== ($currentFingerprint['session_hash'] ?? null)) {
            return 'Sesi browser berubah. Silakan muat ulang dokumen.';
        }

        if (($storedFingerprint['user_agent_hash'] ?? null) !== ($currentFingerprint['user_agent_hash'] ?? null)) {
            return 'Browser viewer berubah. Silakan muat ulang dokumen.';
        }

        if (($storedFingerprint['ip_fingerprint'] ?? null) !== ($currentFingerprint['ip_fingerprint'] ?? null)) {
            return 'Jaringan viewer berubah. Silakan muat ulang dokumen.';
        }

        return true;
    }

    private function buildSecurePdfIpFingerprint(?string $ipAddress): string
    {
        if (! filled($ipAddress)) {
            return 'unknown';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ipAddress);

            return implode('.', array_slice($segments, 0, 3)).'.*';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $segments = explode(':', strtolower($ipAddress));

            return implode(':', array_slice($segments, 0, 4)).':*';
        }

        return 'unknown';
    }

    /**
     * Cache store untuk handshake/nonce/rate-limit/dispatch-lock PDF viewer.
     * Pakai database driver agar shared antar Cloud Run instance.
     * Tabel: `cache` (sudah dibuat manual).
     */
    private function pdfCache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store('database');
    }

    private function resolveBerkasRecord(string $berkasId): ?object
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;

        return DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();
    }

    private function shouldApplySecurePdfWatermark(?string $flagDownload): bool
    {
        // Watermark diterapkan KECUALI aktivitas boleh di-download (Flag_Download='Y').
        // Default 'T' (watermark) saat flag tidak diketahui → fail-safe.
        return strtoupper((string) ($flagDownload ?? 'T')) !== 'Y';
    }

    /**
     * Ambil Flag_Download aktivitas (N_LMS_Materi_Det) yang sedang dibuka. Ini sumber yang sama
     * dengan tombol "Download" di halaman detail training, sehingga tampilan viewer konsisten
     * dengan ketersediaan unduhan. Return 'T' bila konteks tidak lengkap / aktivitas tak ditemukan.
     */
    private function resolveActivityFlagDownload(int $materiId, int $materiDetId): string
    {
        if ($materiId <= 0 || $materiDetId <= 0) {
            return 'T';
        }

        $flag = DB::table('N_LMS_Materi_Det')
            ->where('Id_Materi', $materiId)
            ->where('Id_Materi_Det', $materiDetId)
            ->where('Flag_Aktif', 'Y')
            ->value('Flag_Download');

        return strtoupper((string) ($flag ?? 'T'));
    }

    /**
     * Dispatch Cloud Task job konversi PDF jika belum ada job yang sedang berjalan.
     *
     * @param  bool  $isRetry  True = dipanggil dari kondisi FAILED (user klik Coba Lagi).
     *                         Lock TTL lebih lama (5 menit) untuk mencegah spam retry.
     */
    private function maybeDispatchPdfConversionJob(object $berkas, bool $isRetry = false): void
    {
        $berkasId = (int) $berkas->Id_Master_Konten_Berkas;
        $lockKey = 'lms_pdf_converting_'.$berkasId;
        $lockTtl = $isRetry ? 300 : 600; // retry: 5 menit cooldown, normal: 10 menit

        /*
         * PINTU KEEMPAT untuk kunci yang sama ('lms_pdf_converting_'), dan yang
         * terakhir ditemukan -- oleh penjaga kelengkapan, bukan oleh mata.
         *
         * add() atomik: di SQL Server Laravel menjalankan INSERT ke tabel cache
         * lalu menangkap pelanggaran primary key, jadi yang kalah balapan
         * mendapat false. has()+put() bukan kunci melainkan cek-lalu-tindak, dan
         * di sini jendelanya bahkan lebih lebar daripada pintu lain: kuncinya
         * baru dipasang setelah dispatch DAN setelah satu UPDATE.
         */
        if (! $this->pdfCache()->add($lockKey, true, $lockTtl)) {
            return;
        }

        try {
            $cloudTask = app(CloudTaskService::class);
            $queue = config('services.project_config.gcp.queue_pdf', 'default');
            $webhookUrl = url('/api/v1/tasks/lms/process-pdf');

            $cloudTask->dispatch(
                url: $webhookUrl,
                queue: $queue,
                payload: ['berkas_id' => $berkasId],
                taskType: $isRetry ? 'LMS_PDF_RENDER_RETRY' : 'LMS_PDF_RENDER_ON_DEMAND',
                referenceId: $berkasId,
                referenceNo: ($isRetry ? 'RETRY-' : 'ON-DEMAND-').$berkasId,
                dispatchedBy: 'SYSTEM_ON_DEMAND',
            );

            // Reset status → PROCESSING agar frontend polling tahu job sudah jalan
            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update([
                    'Status_Render' => 'PROCESSING',
                    'Error_Message' => null,
                    'Updated_At' => now(),
                ]);

        } catch (\Throwable $e) {
            /* Dilepas supaya dispatch yang gagal tidak memblokir percobaan
               berikutnya sampai TTL habis -- perilaku lama, karena dulu
               kunci memang tak pernah terpasang kalau dispatch melempar. */
            $this->pdfCache()->forget($lockKey);

            \Illuminate\Support\Facades\Log::warning('lms.pdf.on_demand_dispatch_failed', [
                'berkas_id' => $berkasId,
                'is_retry' => $isRetry,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveSecurePdfAccessContext(Request $request, int $berkasId): ?object
    {
        $materiIdParam = $request->query('materi_id');
        $materiDetIdParam = $request->query('materi_det_id');
        $materiId = filled($materiIdParam)
            ? Hashids::connection('custom')->decode($materiIdParam)[0] ?? (int) $materiIdParam
            : 0;
        $materiDetId = filled($materiDetIdParam)
            ? Hashids::connection('custom')->decode($materiDetIdParam)[0] ?? (int) $materiDetIdParam
            : 0;

        if ($materiId <= 0 || $materiDetId <= 0) {
            return null;
        }

        $item = DB::table('N_LMS_Materi_Det as det')
            ->leftJoin('N_LMS_Master_Konten as konten', 'det.Id_Master_Konten', '=', 'konten.Id_Master_Konten')
            ->where('det.Id_Materi', $materiId)
            ->where('det.Id_Materi_Det', $materiDetId)
            ->where('det.Flag_Aktif', 'Y')
            ->select([
                'det.Id_Materi',
                'det.Id_Materi_Det',
                'det.Id_Materi_Detail',
                'det.Id_Master_Konten',
                'konten.Tipe_Konten',
                'konten.Flag_Third_Party',
            ])
            ->first();

        if (! $item || empty($item->Id_Master_Konten) || strtoupper((string) ($item->Flag_Third_Party ?? 'T')) === 'Y') {
            return null;
        }

        $penjadwalan = $this->resolvePenjadwalanForMateri(
            (int) $item->Id_Materi,
            $request->query('penjadwalan_id'),
            $request->query('target_kode_karyawan'),
        );

        if (! $penjadwalan) {
            return null;
        }

        $this->ensurePenjadwalanStructure((int) $penjadwalan->Id_Penjadwalan, (int) $item->Id_Materi);

        $hasScheduledItem = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('pdet.Id_Materi_Det', $item->Id_Materi_Det)
            ->exists();

        if (! $hasScheduledItem) {
            return null;
        }

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $berkasId)
            ->where('Id_Master_Konten', $item->Id_Master_Konten)
            ->first();

        if (! $berkas || empty($berkas->File_Path)) {
            return null;
        }

        return (object) [
            'berkas' => $berkas,
            'materi_id' => (int) $item->Id_Materi,
            'materi_det_id' => (int) $item->Id_Materi_Det,
            'penjadwalan_id' => (int) $penjadwalan->Id_Penjadwalan,
        ];
    }

    private function buildSecurePdfWatermark(Request $request, object $user): array
    {
        $clientIp = $this->resolveSecurePdfClientIp($request);

        return [
            'user' => 'User : '.$this->resolveSecurePdfWatermarkUser($user),
            'ip' => $this->maskIpAddress($clientIp),
            'date' => now()->format('Y-m-d'),
            'Property' => 'Property of Evo Group',
        ];
    }

    private function resolveSecurePdfWatermarkUser(object $user): string
    {
        $candidates = [
            data_get($user, 'Nickname'),
            data_get($user, 'Nama_User'),
            data_get($user, 'Username'),
            data_get($user, 'name'),
        ];

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return trim((string) $candidate);
            }
        }

        return 'Unknown User';
    }

    private function maskIpAddress(?string $ipAddress): string
    {
        if (! filled($ipAddress)) {
            return '-';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $segments = explode('.', $ipAddress);

            return "{$segments[0]}.{$segments[1]}.xxx.xxx";
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $segments = explode(':', $ipAddress);

            return implode(':', array_slice($segments, 0, 3)).':xxxx:xxxx';
        }

        return $ipAddress;
    }

    private function resolveSecurePdfClientIp(Request $request): ?string
    {
        $headerCandidates = [
            $request->header('CF-Connecting-IP'),
            $request->header('True-Client-IP'),
            $request->header('X-Real-IP'),
        ];

        foreach ($headerCandidates as $candidate) {
            if ($this->isUsableClientIp($candidate)) {
                return trim((string) $candidate);
            }
        }

        $forwardedFor = $request->header('X-Forwarded-For');
        if (filled($forwardedFor)) {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = trim($candidate);
                if ($this->isUsableClientIp($candidate)) {
                    return $candidate;
                }
            }
        }

        $fallbacks = [$request->ip(), $request->server('REMOTE_ADDR')];

        foreach ($fallbacks as $candidate) {
            if ($this->isUsableClientIp($candidate)) {
                return trim((string) $candidate);
            }
        }

        return filled($request->ip()) ? (string) $request->ip() : null;
    }

    private function isUsableClientIp(?string $candidate): bool
    {
        if (! filled($candidate)) {
            return false;
        }

        $candidate = trim((string) $candidate);

        return (bool) filter_var($candidate, FILTER_VALIDATE_IP);
    }

    public function downloadFile(string $berkasId)
    {
        $realId = Hashids::connection('custom')->decode($berkasId)[0] ?? (int) $berkasId;
        $berkas = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten_Berkas', $realId)->first();

        if (! $berkas || ! $berkas->File_Path) {
            abort(404, 'File tidak ditemukan');
        }

        $service = new \App\Services\LMS\GCS\GcsService;
        $metadata = $service->getFileMetadata($berkas->File_Path);

        if (! $metadata['exists']) {
            abort(404, 'File fisik tidak ditemukan');
        }

        $disk = Storage::disk($metadata['disk']);
        $mimeType = $metadata['mime_type'] ?: 'application/octet-stream';
        $fileName = $metadata['file_name'] ?: basename($berkas->File_Path);

        return response()->streamDownload(
            function () use ($disk, $berkas) {
                $stream = $disk->readStream($berkas->File_Path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            $fileName,
            [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function buildFinalScoreSummary(?object $penjadwalan, int $materiId): ?array
    {
        if (! $penjadwalan || empty($penjadwalan->Id_Master_Rumus)) {
            return null;
        }

        $formula = DB::table('N_LMS_Master_Rumus')->where('Id_Master_Rumus', $penjadwalan->Id_Master_Rumus)->first();

        if (! $formula) {
            return null;
        }

        preg_match_all('/\[(.*?)\]/', (string) $formula->Rumus, $matches);
        $tokens = collect($matches[1] ?? [])
            ->map(fn ($token) => strtoupper(trim((string) $token)))
            ->unique()
            ->values();

        $activityRows = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->leftJoin('N_LMS_Penjadwalan_Integrasi as pint', 'pdet.Id_Penjadwalan_Det', '=', 'pint.Id_Penjadwalan_Det')
            ->leftJoin('N_LMS_Transaksi_Training as trx', function ($join) {
                $join->on('trx.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')->where('trx.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_LMS_Transaksi_Training_Detail as trx_detail', function ($join) {
                $join
                    ->on('trx_detail.Id_Transaksi_Training', '=', 'trx.Id_Transaksi_Training')
                    ->on('trx_detail.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                    ->on('trx_detail.Id_Materi', '=', 'det.Id_Materi')
                    ->on('trx_detail.Id_Materi_Det', '=', 'det.Id_Materi_Det');
            })
            ->leftJoin(
                'N_LMS_Transaksi_Training_Det as trx_det',
                'trx_det.Id_Transaksi_Training_Detail',
                '=',
                'trx_detail.Id_Transaksi_Training_Detail',
            )
            ->where('pd.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('det.Id_Materi', $materiId)
            ->select([
                'det.Id_Materi_Det',
                'akt.Kode_Aktivitas',
                'akt.Nama_Aktivitas',
                'akt.Flag_Third_Party as Aktivitas_Third_Party',
                'pdet.Flag_Selesai',
                'pdet.Waktu_Batas_Akses',
                'pint.Waktu_Akhir as Integrasi_Waktu_Akhir',
                'trx_det.Skor_Akhir',
            ])
            ->get();

        $labelOverrides = [
            'ABS' => 'Absensi',
            'PRT' => 'Pre-Test',
            'PTT' => 'Post-Test',
            'QIZ' => 'Quiz',
            'ELS' => 'Evaluasi',
            'EVL' => 'Evaluasi',
            'MDL' => 'Modul',
        ];

        $activityNames = DB::table('N_LMS_Master_Aktivitas')
            ->whereIn('Kode_Aktivitas', $tokens->all())
            ->pluck('Nama_Aktivitas', 'Kode_Aktivitas')
            ->mapWithKeys(fn ($name, $code) => [strtoupper((string) $code) => $name])
            ->toArray();

        $components = $tokens
            ->map(function ($token) use ($activityRows, $labelOverrides, $activityNames) {
                $rows = $activityRows
                    ->filter(fn ($row) => strtoupper((string) $row->Kode_Aktivitas) === $token)
                    ->values();
                $label = $labelOverrides[$token] ?? ($activityNames[$token] ?? $token);

                if ($rows->isEmpty()) {
                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => null,
                        'display_value' => '-',
                        'ready' => false,
                        'source_type' => 'missing',
                        'tooltip' => "{$label}: komponen ini tidak ditemukan pada training.",
                    ];
                }

                if (in_array($token, ['ABS', 'MDL'], true)) {
                    $total = $rows->count();
                    $completed = $rows->where('Flag_Selesai', 'Y')->count();
                    $ratio = $total > 0 ? round($completed / $total, 4) : null;

                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => $ratio,
                        'display_value' => $ratio === null ? '-' : number_format($ratio, 2),
                        'ready' => $total > 0,
                        'source_type' => 'completion-ratio',
                        'tooltip' => "{$label}: {$completed}/{$total} aktivitas selesai, nilai komponen ".
                            ($ratio === null ? '-' : number_format($ratio, 2)),
                    ];
                }

                $total = $rows->count();
                $scores = $rows->map(function ($row) {
                    return $row->Skor_Akhir !== null ? (float) $row->Skor_Akhir : null;
                });
                $scoredCount = $scores->filter(fn ($score) => $score !== null)->count();

                if ($scoredCount > 0) {
                    $normalizedScores = $scores->map(fn ($score) => $score !== null ? (float) $score : 0.0);
                    $averageScore = $total > 0 ? round($normalizedScores->sum() / $total, 2) : null;

                    return [
                        'token' => $token,
                        'name' => $label,
                        'value' => $averageScore,
                        'display_value' => $averageScore === null ? '-' : number_format($averageScore, 2),
                        'ready' => $scoredCount === $total,
                        'source_type' => 'score-average',
                        'tooltip' => "{$label}: {$scoredCount}/{$total} skor tersedia, rata-rata komponen ".
                            ($averageScore === null ? '-' : number_format($averageScore, 2)),
                    ];
                }

                $completed = $rows->where('Flag_Selesai', 'Y')->count();
                $ratio = $total > 0 ? round($completed / $total, 4) : null;

                return [
                    'token' => $token,
                    'name' => $label,
                    'value' => $ratio,
                    'display_value' => $ratio === null ? '-' : number_format($ratio, 2),
                    'ready' => $total > 0 && $completed === $total,
                    'source_type' => 'completion-fallback',
                    'tooltip' => "{$label}: belum ada skor akhir, memakai rasio penyelesaian {$completed}/{$total} dengan nilai ".
                        ($ratio === null ? '-' : number_format($ratio, 2)),
                ];
            })
            ->values();

        $allComponentsReady = $components->every(fn ($component) => $component['ready'] === true);
        $finalScore = null;
        $calculationStatus = 'pending';

        if ($allComponentsReady) {
            $resolvedFormula = (string) $formula->Rumus;

            foreach ($components as $component) {
                $resolvedFormula = str_replace(
                    '['.$component['token'].']',
                    (string) ($component['value'] ?? 0),
                    $resolvedFormula,
                );
            }

            $finalScore = PenilaiEkspresiNumerik::nilai($resolvedFormula);
            $calculationStatus = 'ready';
        }

        $translatedFormula = preg_replace_callback(
            '/\[(.*?)\]/',
            function ($matches) use ($components) {
                $token = strtoupper(trim((string) ($matches[1] ?? '')));
                $component = $components->firstWhere('token', $token);

                return $component['name'] ?? $token;
            },
            (string) $formula->Rumus,
        );

        return [
            'id_master_rumus' => Hashids::connection('custom')->encode($formula->Id_Master_Rumus),
            'kode_rumus' => $formula->Kode_Rumus,
            'nama_formula' => $formula->Nama_Formula,
            'deskripsi' => $formula->Deskripsi,
            'raw_formula' => $formula->Rumus,
            'translated_formula' => $translatedFormula,
            'components' => $components->all(),
            'final_score' => $finalScore !== null ? round((float) $finalScore, 2) : null,
            'final_score_display' => $finalScore !== null ? number_format((float) $finalScore, 2) : '-',
            'status' => $calculationStatus,
            'status_label' => $allComponentsReady ? 'Nilai akhir tersedia' : 'Menunggu komponen lengkap',
            'all_components_ready' => $allComponentsReady,
        ];
    }

    /**
     * Tempelkan id_penjadwalan ke link ujian, supaya HC Learn tahu ke mana
     * peserta dikembalikan.
     *
     * $penjadwalanId DIBOLEHKAN null: pemanggilnya membaca
     * `$penjadwalan->Id_Penjadwalan ?? null`, sementara tanda tangannya dulu
     * menuntut int. PHP tanpa strict_types memaksanya jadi 0 disertai
     * deprecation — dan 0 itu falsy, sehingga jatuh ke cabang di bawah.
     *
     * Yang lebih berbahaya: cabang itu dulu mengembalikan NULL, membuang link
     * yang sudah ada hanya karena penjadwalannya tak terbaca. Tombol ujiannya
     * hilang tanpa satu pun galat — peserta menyimpulkan ujiannya belum siap,
     * padahal sesinya sudah dicetak dan menunggu. Sekarang linknya dikembalikan
     * apa adanya: tanpa id_penjadwalan ia memang kurang lengkap, tapi masih
     * mengantar peserta ke ujiannya.
     */
    private function applyAdditionalIntegrasiData(?string $linkUjian, ?int $penjadwalanId): ?string
    {
        if (! $linkUjian) {
            return null;
        }

        if (! $penjadwalanId) {
            return $linkUjian;
        }

        $encodePenjadwalan = Hashids::connection('custom')->encode($penjadwalanId);

        /* Disisipkan SEBELUM fragment: query yang ditempel sesudah '#' bukan
           query sama sekali — ia jadi bagian dari fragment dan tak pernah
           terbaca server mana pun. */
        [$dasar, $fragment] = array_pad(explode('#', $linkUjian, 2), 2, null);
        $dasar .= (Str::contains($dasar, '?') ? '&' : '?').'id_penjadwalan='.$encodePenjadwalan;

        return $fragment === null ? $dasar : $dasar.'#'.$fragment;
    }

    private function buildTrainingResultSummary(?object $penjadwalan, int $materiId): ?array
    {
        if (! $penjadwalan) {
            return null;
        }

        $transaction = DB::table('N_LMS_Transaksi_Training as trx')
            ->leftJoin(
                'N_LMS_Master_Keterangan_Kelulusan as kel',
                'trx.Id_Keterangan_Kelulusan',
                '=',
                'kel.Id_Keterangan_Kelulusan',
            )
            ->where('trx.Id_Penjadwalan', $penjadwalan->Id_Penjadwalan)
            ->where('trx.Flag_Aktif', 'Y')
            ->select([
                'trx.Id_Transaksi_Training',
                'trx.Total_Score',
                'trx.Score_Learning_Gain',
                'trx.Id_Keterangan_Kelulusan',
                'kel.Kode_Keterangan',
                'kel.Nama_Keterangan',
            ])
            ->first();

        if (! $transaction || $transaction->Total_Score === null) {
            return null;
        }

        $komposisi = app(\App\Services\LMS\TrainingExecutionService::class)
            ->komposisiTes((int) $penjadwalan->Id_Penjadwalan, $materiId);

        $standarKelulusan = DB::table('N_LMS_Materi')->where('Id_Materi', $materiId)->value('Standar_Kelulusan');

        $totalScore = (float) $transaction->Total_Score;
        $standarKelulusanValue = $standarKelulusan !== null ? (float) $standarKelulusan : null;
        $kelulusanStatus = 'unknown';

        if (
            (int) $transaction->Kode_Keterangan === 1 ||
            strtolower((string) $transaction->Nama_Keterangan) === 'lulus'
        ) {
            $kelulusanStatus = 'passed';
        } elseif (
            (int) $transaction->Kode_Keterangan === 2 ||
            strtolower((string) $transaction->Nama_Keterangan) === 'tidak lulus'
        ) {
            $kelulusanStatus = 'failed';
        } elseif ($standarKelulusanValue !== null) {
            $kelulusanStatus = $totalScore >= $standarKelulusanValue ? 'passed' : 'failed';
        }

        return [
            'id_transaksi_training' => $transaction->Id_Transaksi_Training,
            'total_score' => round($totalScore, 2),
            'total_score_display' => number_format($totalScore, 2),
            'score_learning_gain' => $transaction->Score_Learning_Gain !== null ? round((float) $transaction->Score_Learning_Gain, 2) : null,
            'score_learning_gain_display' => $transaction->Score_Learning_Gain !== null
                    ? number_format((float) $transaction->Score_Learning_Gain, 2)
                    : null,
            'id_keterangan_kelulusan' => $transaction->Id_Keterangan_Kelulusan,
            'kode_keterangan' => $transaction->Kode_Keterangan,
            'keterangan_kelulusan' => $transaction->Nama_Keterangan,
            'kelulusan_status' => $kelulusanStatus,
            'standar_kelulusan' => $standarKelulusanValue,
            // Dibaca dari aktivitas yang benar-benar dijadwalkan. Dulu dipaku true,
            // sehingga baris "Keterangan Kelulusan" ikut tampil pada training yang
            // tidak punya post-test sama sekali.
            'has_post_test' => $komposisi['ada_post_test'],
            'has_pre_test' => $komposisi['ada_pre_test'],
            // Nilai akhir boleh berasal dari pre-test bila post-test-nya tidak ada;
            // layar perlu tahu supaya tidak menyebutnya "nilai post-test".
            'sumber_nilai' => $komposisi['sumber_nilai'],
        ];
    }

    private function resolveThumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (! Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
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

    private function resolvePenjadwalanForMateri(
        int $materiId,
        ?string $penjadwalanId = null,
        ?string $targetKodeKaryawan = null,
    ): ?object {
        $kodeKaryawan = null;

        if (filled($targetKodeKaryawan)) {
            try {
                $kodeKaryawan = Crypt::decryptString($targetKodeKaryawan);
            } catch (\Throwable $e) {
                return null;
            }
        }

        $kodeKaryawan = $kodeKaryawan ?: $this->resolveCurrentKodeKaryawan();
        if (! $kodeKaryawan) {
            return null;
        }

        if (filled($penjadwalanId)) {
            $realPenjadwalanId = Hashids::connection('custom')->decode($penjadwalanId)[0] ?? (int) $penjadwalanId;
            $penjadwalan = $this->trainingScheduleQueryService->resolvePenjadwalanByIdAndEmployee(
                (int) $realPenjadwalanId,
                $kodeKaryawan,
            );

            if (
                ! $penjadwalan ||
                ! $this->trainingScheduleQueryService->penjadwalanContainsMateri(
                    (int) $penjadwalan->Id_Penjadwalan,
                    $materiId,
                )
            ) {
                return null;
            }

            return $penjadwalan;
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

    private function getMateriDetPrerequisiteMap(int $materiId, ?int $penjadwalanId = null): array
    {
        $itemIds = DB::table('N_LMS_Materi_Det')
            ->where('Id_Materi', $materiId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Materi_Det')
            ->toArray();

        if (empty($itemIds)) {
            return [];
        }

        $validItemIds = array_fill_keys($itemIds, true);

        if ($penjadwalanId && SkemaTersedia::tabel('N_LMS_Penjadwalan_Syarat')) {
            $snapshotMap = DB::table('N_LMS_Penjadwalan_Syarat as ps')
                ->join('N_LMS_Penjadwalan_Detail as pd', 'ps.Id_Penjadwalan_Detail', '=', 'pd.Id_Penjadwalan_Detail')
                ->where('pd.Id_Penjadwalan', $penjadwalanId)
                ->whereIn('ps.Id_Materi_Det', $itemIds)
                ->when(SkemaTersedia::kolom('N_LMS_Penjadwalan_Syarat', 'Flag_Aktif'), function ($query) {
                    $query->where('ps.Flag_Aktif', 'Y');
                })
                ->pluck('ps.Id_Materi_Det_Syarat', 'ps.Id_Materi_Det')
                ->map(fn ($value) => filled($value) ? (int) $value : null)
                ->filter(fn ($value) => $value === null || isset($validItemIds[$value]))
                ->toArray();

            if (! empty($snapshotMap)) {
                return $snapshotMap;
            }
        }

        if (SkemaTersedia::tabel('N_LMS_Materi_Det_Syarat')) {
            return DB::table('N_LMS_Materi_Det_Syarat')
                ->whereIn('Id_Materi_Det', $itemIds)
                ->pluck('Id_Materi_Det_Syarat', 'Id_Materi_Det')
                ->map(fn ($value) => filled($value) ? (int) $value : null)
                ->filter(fn ($value) => $value === null || isset($validItemIds[$value]))
                ->toArray();
        }

        if (! SkemaTersedia::tabel('N_LMS_Prasyarat')) {
            return [];
        }

        if (
            ! SkemaTersedia::kolom('N_LMS_Prasyarat', 'Id_Materi_Det') ||
            ! SkemaTersedia::kolom('N_LMS_Prasyarat', 'Id_Materi_Det_Syarat')
        ) {
            return [];
        }

        return DB::table('N_LMS_Prasyarat')
            ->when(SkemaTersedia::kolom('N_LMS_Prasyarat', 'Flag_Aktif'), function ($query) {
                $query->where('Flag_Aktif', 'Y');
            })
            ->whereIn('Id_Materi_Det', $itemIds)
            ->pluck('Id_Materi_Det_Syarat', 'Id_Materi_Det')
            ->map(fn ($value) => filled($value) ? (int) $value : null)
            ->filter(fn ($value) => $value === null || isset($validItemIds[$value]))
            ->toArray();
    }

    private function getMateriDetPrerequisiteLabelMap(int $materiId, array $prasyaratMap): array
    {
        $prerequisiteIds = array_values(array_filter($prasyaratMap));

        if (empty($prerequisiteIds)) {
            return [];
        }

        $labelsByPrerequisiteId = DB::table('N_LMS_Materi_Det as det')
            ->leftJoin('N_LMS_Master_Konten as kon', 'det.Id_Master_Konten', '=', 'kon.Id_Master_Konten')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->where('det.Id_Materi', $materiId)
            ->whereIn('det.Id_Materi_Det', $prerequisiteIds)
            ->select(['det.Id_Materi_Det', 'det.Keterangan_Level', 'kon.Judul_Konten', 'akt.Nama_Aktivitas'])
            ->get()
            ->mapWithKeys(function ($item) {
                return [
                    $item->Id_Materi_Det => $item->Keterangan_Level ?: ($item->Judul_Konten ?: $item->Nama_Aktivitas),
                ];
            })
            ->toArray();

        $labelMap = [];

        foreach ($prasyaratMap as $targetId => $prerequisiteId) {
            $labelMap[$targetId] = $prerequisiteId ? $labelsByPrerequisiteId[$prerequisiteId] ?? null : null;
        }

        return $labelMap;
    }

    private function resolveBunnyVideoDownloadUrl(string $videoId): ?string
    {
        $videoId = trim($videoId);
        $cdnHost = trim((string) config('services.lms.bunny.cdn_host', ''));

        if ($videoId === '' || $cdnHost === '') {
            return null;
        }

        return "https://{$cdnHost}/{$videoId}/play_720p.mp4";
    }

    private function decodeIntegrasiPayload(?string $payloadJson): array
    {
        if (! filled($payloadJson)) {
            return [];
        }

        $decoded = json_decode($payloadJson, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeMetaList(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return collect($decoded)->map(fn ($entry) => trim((string) $entry))->filter()->values()->all();
        }

        return collect(preg_split('/\r?\n|;/', (string) $value) ?: [])
            ->map(fn ($entry) => trim((string) $entry))
            ->filter()
            ->values()
            ->all();
    }
}
