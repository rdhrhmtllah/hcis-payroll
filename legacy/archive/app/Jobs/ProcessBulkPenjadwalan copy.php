<?php

namespace App\Jobs;

use App\Helpers\SignatureHelper;
use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class ProcessBulkPenjadwalan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    public $tries = 2;

    protected $validated;

    protected $appId;

    protected $kodeKaryawanAuth;

    protected $idUsersAuth;

    protected $userAgent;

    protected $domain;

    protected $processId;

    public function __construct(
        $validated,
        $appId,
        $kodeKaryawanAuth,
        $idUsersAuth,
        $userAgent,
        $domain,
        $processId = null,
    ) {
        $this->validated = $validated;
        $this->appId = $appId;
        $this->kodeKaryawanAuth = $kodeKaryawanAuth;
        $this->idUsersAuth = $idUsersAuth;
        $this->userAgent = $userAgent;
        $this->domain = $domain;
        $this->processId = $processId;
    }

    public function handle()
    {
        $validated = $this->validated;

        try {
            $idMasterPaket = Hashids::connection('custom')->decode($validated['id_training'])[0] ?? null;
            if (! $idMasterPaket) {
                throw new \Exception('ID Paket tidak valid.');
            }

            $idMasterRumus = null;
            if (! empty($validated['id_rumus_penilaian'])) {
                $idMasterRumus = Hashids::connection('custom')->decode($validated['id_rumus_penilaian'])[0] ?? null;
            }

            $valLGN = $validated['Flag_Learning_Gain'] ?? 'T';
            $flagLearningGain = in_array($valLGN, [true, 'true', 'Y'], true) ? 'Y' : 'T';

            $karyawanMurni = array_unique($validated['karyawan']);

            $dataKaryawanLMS = DB::table('Karyawan')->whereIn('Kode_Karyawan', $karyawanMurni)->get();
            $karyawanDitemukan = $dataKaryawanLMS->pluck('Kode_Karyawan')->toArray();

            if (count($karyawanDitemukan) !== count($karyawanMurni)) {
                $karyawanTidakValid = array_diff($karyawanMurni, $karyawanDitemukan);
                throw new \Exception('Kode Karyawan tidak valid: '.implode(', ', $karyawanTidakValid));
            }

            $dataDetailKaryawan = $dataKaryawanLMS
                ->mapWithKeys(function ($item) {
                    return [
                        $item->Kode_Karyawan => [
                            'Kode_Karyawan' => $item->Kode_Karyawan,
                            'Nama' => $item->Nama ?? '-',
                            'No_Hp' => $item->HP ?? '-',
                            'UserID_Web' => $item->UserID_Web ?? '-',
                            'UserID_Absen' => $item->UserID_Absen ?? '-',
                            'Tgl_Lahir' => $item->Tgl_Lahir ?? null,
                            'ID_Level_Jabatan' => $item->ID_Level_Jabatan ?? null,
                            'ID_Divisi_Sub_Divisi' => $item->ID_Divisi_Sub_Divisi ?? null,
                        ],
                    ];
                })
                ->toArray();

            $asignBy = $this->idUsersAuth;
            $waktuEnroll = $validated['waktu_mulai'];
            $waktuBatas = $validated['waktu_akhir'] ?? null;
            $now = now();

            $decodedSubMateri = [];
            foreach ($validated['sub_materi'] as $sub) {
                $idMateriDetail = Hashids::connection('custom')->decode($sub['Id_Materi_Detail'])[0] ?? null;
                $idMasterPaketDetail = isset($sub['Id_Master_Paket_Detail'])
                    ? Hashids::connection('custom')->decode($sub['Id_Master_Paket_Detail'])[0] ?? null
                    : null;

                if (! $idMateriDetail || ! $idMasterPaketDetail) {
                    continue;
                }

                $decodedAktivitas = [];
                if (isset($sub['Aktivitas']) && is_array($sub['Aktivitas'])) {
                    foreach ($sub['Aktivitas'] as $akt) {
                        $idMateriDet = isset($akt['id_materi_det'])
                            ? Hashids::connection('custom')->decode($akt['id_materi_det'])[0] ?? null
                            : null;
                        if ($idMateriDet) {
                            $akt['Decoded_Id_Materi_Det'] = $idMateriDet;
                            $decodedAktivitas[] = $akt;
                        }
                    }
                }

                $decodedSubMateri[] = [
                    'Urutan' => $sub['Urutan'] ?? 1,
                    'Id_Materi_Detail' => $idMateriDetail,
                    'Id_Master_Paket_Detail' => $idMasterPaketDetail,
                    'Aktivitas' => $decodedAktivitas,
                ];
            }

            $karyawanChunks = array_chunk($karyawanMurni, 20);
            $totalChunks = count($karyawanChunks);
            $processedChunks = 0;

            foreach ($karyawanChunks as $chunkKaryawan) {
                DB::beginTransaction();

                $apiBulkRequests = [];

                try {
                    $batchPenjadwalan = [];
                    foreach ($chunkKaryawan as $kodeKar) {
                        $batchPenjadwalan[] = [
                            'Kode_Karyawan' => $kodeKar,
                            'Id_Master_Paket' => $idMasterPaket,
                            'Id_Master_Rumus' => $idMasterRumus,
                            'Waktu_Enroll' => $waktuEnroll,
                            'Asign_By' => $asignBy,
                            'Flag_Selesai' => 'T',
                            'Flag_Learning_Gain' => $flagLearningGain,
                            'Flag_Cabut_Akses' => 'T',
                            'Created_At' => $now,
                            'Updated_At' => $now,
                        ];
                    }

                    foreach (array_chunk($batchPenjadwalan, 100) as $chunk) {
                        DB::table('N_LMS_Penjadwalan')->insert($chunk);
                    }

                    $insertedPenjadwalan = DB::table('N_LMS_Penjadwalan')
                        ->where('Id_Master_Paket', $idMasterPaket)
                        ->where('Waktu_Enroll', $waktuEnroll)
                        ->whereIn('Kode_Karyawan', $chunkKaryawan)
                        ->get(['Id_Penjadwalan', 'Kode_Karyawan'])
                        ->keyBy('Kode_Karyawan');

                    $batchDetail = [];
                    foreach ($chunkKaryawan as $kodeKar) {
                        $idPenjadwalan = $insertedPenjadwalan[$kodeKar]->Id_Penjadwalan ?? null;
                        if (! $idPenjadwalan) {
                            continue;
                        }

                        foreach ($decodedSubMateri as $sub) {
                            $batchDetail[] = [
                                'Id_Penjadwalan' => $idPenjadwalan,
                                'Id_Master_Paket_Detail' => $sub['Id_Master_Paket_Detail'],
                                'Id_Materi_Detail' => $sub['Id_Materi_Detail'],
                                'Urutan' => $sub['Urutan'],
                                'Flag_Aktif' => 'Y',
                            ];
                        }
                    }

                    foreach (array_chunk($batchDetail, 100) as $chunk) {
                        DB::table('N_LMS_Penjadwalan_Detail')->insert($chunk);
                    }

                    $insertedDetail = DB::table('N_LMS_Penjadwalan_Detail')
                        ->whereIn('Id_Penjadwalan', $insertedPenjadwalan->pluck('Id_Penjadwalan'))
                        ->get(['Id_Penjadwalan_Detail', 'Id_Penjadwalan', 'Id_Materi_Detail'])
                        ->keyBy(function ($item) {
                            return $item->Id_Penjadwalan.'_'.$item->Id_Materi_Detail;
                        });

                    $batchDet = [];
                    $batchSyarat = [];

                    foreach ($chunkKaryawan as $kodeKar) {
                        $idPenjadwalan = $insertedPenjadwalan[$kodeKar]->Id_Penjadwalan ?? null;
                        if (! $idPenjadwalan) {
                            continue;
                        }

                        foreach ($decodedSubMateri as $sub) {
                            $keyDetail = $idPenjadwalan.'_'.$sub['Id_Materi_Detail'];
                            $idPenjadwalanDetail = $insertedDetail[$keyDetail]->Id_Penjadwalan_Detail ?? null;
                            if (! $idPenjadwalanDetail) {
                                continue;
                            }

                            foreach ($sub['Aktivitas'] as $akt) {
                                $batchDet[] = [
                                    'Id_Penjadwalan_Detail' => $idPenjadwalanDetail,
                                    'Id_Materi_Det' => $akt['Decoded_Id_Materi_Det'],
                                    'Waktu_Mulai_Akses' => $waktuEnroll,
                                    'Waktu_Batas_Akses' => $waktuBatas,
                                    'Flag_Checkin' => 'T',
                                    'Flag_Third_Party' => $akt['flag_third_party'] == 'Y' ? 'Y' : 'T',
                                    'Flag_Selesai' => 'T',
                                    'Flag_Mempunyai_Akhir_Waktu' => $waktuBatas ? 'Y' : 'T',
                                    'Status_Download' => 'T',
                                ];
                            }
                        }
                    }

                    foreach (array_chunk($batchDet, 100) as $chunk) {
                        DB::table('N_LMS_Penjadwalan_Det')->insert($chunk);
                    }

                    $insertedDet = DB::table('N_LMS_Penjadwalan_Det')
                        ->whereIn('Id_Penjadwalan_Detail', $insertedDetail->pluck('Id_Penjadwalan_Detail'))
                        ->get(['Id_Penjadwalan_Det', 'Id_Penjadwalan_Detail', 'Id_Materi_Det', 'Flag_Third_Party'])
                        ->keyBy(function ($item) {
                            return $item->Id_Penjadwalan_Detail.'_'.$item->Id_Materi_Det;
                        });

                    foreach ($chunkKaryawan as $kodeKar) {
                        $idPenjadwalan = $insertedPenjadwalan[$kodeKar]->Id_Penjadwalan ?? null;
                        if (! $idPenjadwalan) {
                            continue;
                        }

                        foreach ($decodedSubMateri as $sub) {
                            $keyDetail = $idPenjadwalan.'_'.$sub['Id_Materi_Detail'];
                            $idPenjadwalanDetail = $insertedDetail[$keyDetail]->Id_Penjadwalan_Detail ?? null;
                            if (! $idPenjadwalanDetail) {
                                continue;
                            }

                            foreach ($sub['Aktivitas'] as $akt) {
                                $keyDet = $idPenjadwalanDetail.'_'.$akt['Decoded_Id_Materi_Det'];
                                $idPenjadwalanDet = $insertedDet[$keyDet]->Id_Penjadwalan_Det ?? null;
                                if (! $idPenjadwalanDet) {
                                    continue;
                                }

                                $batchSyarat[] = [
                                    'Id_Penjadwalan_Detail' => $idPenjadwalanDetail,
                                    'Id_Penjadwalan_Det' => $idPenjadwalanDet,
                                    'Id_Materi_Det' => $akt['Decoded_Id_Materi_Det'],
                                    'Id_Materi_Det_Syarat' => null,
                                    'Flag_Aktif' => 'Y',
                                ];

                                if (($akt['flag_third_party'] ?? 'T') === 'Y' && isset($akt['id_paket_soal'])) {
                                    $wMulaiThird = $akt['waktu_mulai_thirdparty'] ?? $waktuEnroll;
                                    $wAkhirThird = $akt['waktu_akhir_thirdparty'] ?? $waktuBatas;

                                    $idIntegrasi = DB::table('N_LMS_Penjadwalan_Integrasi')->insertGetId([
                                        'Id_Penjadwalan_Det' => $idPenjadwalanDet,
                                        'Waktu_Mulai' => $wMulaiThird,
                                        'Waktu_Akhir' => $wAkhirThird,
                                        'Id_Master_Ujian' => $akt['id_paket_soal'],
                                    ]);

                                    $idPaketSoal = $akt['id_paket_soal'];
                                    if (! isset($apiBulkRequests[$idPaketSoal])) {
                                        $apiBulkRequests[$idPaketSoal] = [
                                            'Id_Master_Ujian' => $idPaketSoal,
                                            'Waktu_Mulai' => $wMulaiThird,
                                            'Waktu_Akhir' => $wAkhirThird,
                                            'Jenis_User' => 'internal',
                                            'Flag_Camera' => isset($akt['flag_camera']) && $akt['flag_camera'] === 'Y' ? true : null,
                                            'User_Agent' => is_array($this->userAgent)
                                                ? json_encode($this->userAgent)
                                                : $this->userAgent ?? '',
                                            'Id_Karyawan' => [],
                                            'Data_Karyawan' => [],
                                            'Mapping_Integrasi' => [],
                                        ];
                                    }

                                    $apiBulkRequests[$idPaketSoal]['Id_Karyawan'][] = $kodeKar;
                                    $apiBulkRequests[$idPaketSoal]['Data_Karyawan'][] = $dataDetailKaryawan[$kodeKar];
                                    $apiBulkRequests[$idPaketSoal]['Mapping_Integrasi'][$kodeKar] = $idIntegrasi;
                                }
                            }
                        }
                    }

                    if (! empty($batchSyarat)) {
                        foreach (array_chunk($batchSyarat, 100) as $chunk) {
                            DB::table('N_LMS_Penjadwalan_Syarat')->insert($chunk);
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error($e->getMessage());

                    continue;
                }

                $endpoint = 'api/v1/penjadwalan/training/store';
                $url = rtrim($this->domain, '/').'/'.ltrim($endpoint, '/');

                foreach ($apiBulkRequests as $idPaketSoal => $payload) {
                    $mappingIntegrasi = $payload['Mapping_Integrasi'];
                    $payload['Id_Integrasi'] = [];

                    foreach ($payload['Id_Karyawan'] as $kodeKar) {
                        if (isset($mappingIntegrasi[$kodeKar])) {
                            $payload['Id_Integrasi'][] = [
                                'Kode_Karyawan' => $kodeKar,
                                'Id_Integrasi' => $mappingIntegrasi[$kodeKar],
                            ];
                        }
                    }

                    $auth = SignatureHelper::store($endpoint, $payload, $this->appId, $this->kodeKaryawanAuth);

                    try {
                        $response = Http::withHeaders($auth['headers'])
                            ->withBody($auth['body'], 'application/json')
                            ->post($url);

                        if ($response->successful()) {
                            $responseDataApi = $response->json()['result'] ?? ($response->json()['data'] ?? []);

                            foreach ($responseDataApi as $itemApi) {
                                $kodeKarApi = $itemApi['Kode_Karyawan'] ?? null;

                                if (
                                    $kodeKarApi &&
                                    isset($mappingIntegrasi[$kodeKarApi]) &&
                                    isset($itemApi['Id_Ujian_Token'])
                                ) {
                                    DB::table('N_LMS_Penjadwalan_Integrasi')
                                        ->where('Id_Penjadwalan_Integrasi', $mappingIntegrasi[$kodeKarApi])
                                        ->update([
                                            'Id_Ujian_Token' => $itemApi['Id_Ujian_Token'],
                                            'Payload_Json' => is_array($itemApi['Payload_Json'] ?? null)
                                                ? json_encode($itemApi['Payload_Json'])
                                                : $itemApi['Payload_Json'] ?? null,
                                        ]);
                                }
                            }
                        } else {
                            Log::error('API Error', [
                                'url' => $url,
                                'status' => $response->status(),
                            ]);
                        }
                    } catch (\Exception $apiException) {
                        Log::error($apiException->getMessage());
                    }
                }

                $processedChunks++;
                $percentage = round(($processedChunks / $totalChunks) * 100);

                if ($this->processId) {
                    Cache::put(
                        "progress_penjadwalan_{$this->processId}",
                        [
                            'status' => 'processing',
                            'progress' => $percentage,
                        ],
                        7200,
                    );
                }
            }

            if ($this->processId) {
                Cache::put(
                    "progress_penjadwalan_{$this->processId}",
                    [
                        'status' => 'completed',
                        'progress' => 100,
                    ],
                    7200,
                );
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            if ($this->processId) {
                Cache::put(
                    "progress_penjadwalan_{$this->processId}",
                    [
                        'status' => 'error',
                        'progress' => 0,
                        'message' => $e->getMessage(),
                    ],
                    7200,
                );
            }

            throw $e;
        }
    }
}
