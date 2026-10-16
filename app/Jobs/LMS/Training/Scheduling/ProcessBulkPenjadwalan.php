<?php

namespace App\Jobs\LMS\Training\Scheduling;

use App\Helpers\SignatureHelper;
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

    public $timeout = 3600;

    public $tries = 2;

    protected $processId;

    protected $appId;

    protected $kodeKaryawanAuth;

    protected $idUsersAuth;

    protected $userAgent;

    protected $domain;

    public function __construct($processId, $appId, $kodeKaryawanAuth, $idUsersAuth, $userAgent, $domain)
    {
        $this->processId = $processId;
        $this->appId = $appId;
        $this->kodeKaryawanAuth = $kodeKaryawanAuth;
        $this->idUsersAuth = $idUsersAuth;
        $this->userAgent = $userAgent;
        $this->domain = $domain;

        $this->onQueue((string) config('queue.names.penjadwalan_training'));
    }

    public function handle()
    {
        $payloadData = DB::table('N_LMS_Job_Payloads')->where('Process_Id', $this->processId)->first();

        if (! $payloadData) {
            return;
        }

        $validated = json_decode($payloadData->Payload_Json, true);

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

            // Nama paket untuk isi notifikasi (in-app + WA). Di-lookup sekali saja.
            $namaPaket = DB::table('N_LMS_Master_Paket')
                ->where('Id_Master_Paket', $idMasterPaket)
                ->value('Nama_Paket') ?? 'Training';

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

                    /*
                     * INSERT ... OUTPUT — id-nya DIKEMBALIKAN, bukan dibaca ulang.
                     *
                     * ══ KENAPA PEMBACAAN ULANGNYA DIBUANG ═════════════════════
                     *
                     * Sebelumnya baris hasil INSERT dibaca kembali dengan
                     *
                     *     WHERE Id_Master_Paket = ? AND Waktu_Enroll = ?
                     *       AND Kode_Karyawan IN (...)
                     *
                     * hanya untuk mengambil Id_Penjadwalan. Pembacaan itu berada DI
                     * DALAM transaksi yang barusan menulis, dan di situlah letak
                     * masalahnya: penjadwalan massal berjalan sebagai beberapa job
                     * sekaligus, dan seluruh job memakai Id_Master_Paket serta
                     * Waktu_Enroll YANG SAMA. Artinya setiap job membaca rentang
                     * kunci yang persis sama dengan yang sedang ditulis job lain
                     * dan belum di-commit.
                     *
                     * Terukur pada uji 900 karyawan: empat sesi saling menunggu
                     * LCK_M_S di N_LMS_Penjadwalan, tunggu terlama 6,8 detik —
                     * lalu 9,4 detik setelah indeks dipasang, karena indeks justru
                     * memusatkan rentang kuncinya.
                     *
                     * Indeks memang mempercepat pembacaannya (2 pemindaian jadi
                     * seek, 7 baca jadi 2), tapi tidak bisa menolong sama sekali di
                     * sini: baris yang dicari memang baris yang sedang dikunci job
                     * lain. Seek secepat apa pun tetap harus menunggu kuncinya
                     * lepas. Yang menyelesaikan hanya MENGHAPUS pembacaannya.
                     *
                     * OUTPUT mengembalikan baris yang baru saja disisipkan langsung
                     * dari operasi INSERT-nya. Tidak ada pencarian, tidak ada kunci
                     * baca, dan tidak ada titik temu dengan job lain.
                     *
                     * Bukan scope_identity(): ia hanya mengembalikan SATU id,
                     * sedangkan yang disisipkan di sini dua puluh baris sekaligus.
                     *
                     * Aman tanpa OUTPUT ... INTO karena tabel ini tidak punya
                     * trigger. Kalau suatu saat trigger ditambahkan, SQL Server
                     * akan menolak bentuk ini — dan penolakannya tegas, bukan
                     * diam-diam salah.
                     */
                    $insertedPenjadwalan = $this->sisipkanDenganOutput(
                        'N_LMS_Penjadwalan',
                        $batchPenjadwalan,
                        ['Id_Penjadwalan', 'Kode_Karyawan'],
                    )->keyBy('Kode_Karyawan');

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

                    // Sama seperti induknya: id-nya dikembalikan INSERT, tidak dibaca
                    // ulang. Pembacaan ini tidak bentrok antar-job (id induknya sudah
                    // milik job ini sendiri), tapi tetap satu pembacaan penuh di dalam
                    // transaksi — dan lamanya transaksi itulah yang menahan kunci.
                    $insertedDetail = $this->sisipkanDenganOutput(
                        'N_LMS_Penjadwalan_Detail',
                        $batchDetail,
                        ['Id_Penjadwalan_Detail', 'Id_Penjadwalan', 'Id_Materi_Detail'],
                    )->keyBy(function ($item) {
                        return $item->Id_Penjadwalan.'_'.$item->Id_Materi_Detail;
                    });

                    $batchDet = [];
                    $batchSyarat = [];

                    /* Baris integrasi dikumpulkan dulu, disisipkan sekali di
                       bawah. Bentuk lamanya satu insertGetId per aktivitas per
                       karyawan — jumlahnya persis karyawan x aktivitas. */
                    $barisIntegrasi = [];
                    $tujuanIntegrasi = [];

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

                    $insertedDet = $this->sisipkanDenganOutput(
                        'N_LMS_Penjadwalan_Det',
                        $batchDet,
                        ['Id_Penjadwalan_Det', 'Id_Penjadwalan_Detail', 'Id_Materi_Det', 'Flag_Third_Party'],
                    )->keyBy(function ($item) {
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

                                    $barisIntegrasi[] = [
                                        'Id_Penjadwalan_Det' => $idPenjadwalanDet,
                                        'Waktu_Mulai' => $wMulaiThird,
                                        'Waktu_Akhir' => $wAkhirThird,
                                        'Id_Master_Ujian' => $akt['id_paket_soal'],
                                    ];

                                    $idPaketSoal = $akt['id_paket_soal'];

                                    // PERUBAHAN: Gunakan Key spesifik per materi agar pre-test & post-test terpisah
                                    $keyApiGroup = $idPaketSoal.'_'.$akt['Decoded_Id_Materi_Det'];

                                    if (! isset($apiBulkRequests[$keyApiGroup])) {
                                        $apiBulkRequests[$keyApiGroup] = [
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

                                    if (! in_array($kodeKar, $apiBulkRequests[$keyApiGroup]['Id_Karyawan'])) {
                                        $apiBulkRequests[$keyApiGroup]['Id_Karyawan'][] = $kodeKar;
                                        $apiBulkRequests[$keyApiGroup]['Data_Karyawan'][] =
                                            $dataDetailKaryawan[$kodeKar];
                                    }

                                    /* Id integrasinya belum ada saat ini — barisnya baru
                                       disisipkan sesudah perulangan selesai. Yang dicatat di
                                       sini tujuannya; Id_Penjadwalan_Det dipakai sebagai
                                       jodoh karena ia unik per (karyawan, sub, aktivitas)
                                       dan sudah diketahui sebelum penyisipan. */
                                    $tujuanIntegrasi[$idPenjadwalanDet] = [$keyApiGroup, $kodeKar];
                                }
                            }
                        }
                    }

                    if (! empty($barisIntegrasi)) {
                        $hasilIntegrasi = $this->sisipkanDenganOutput(
                            'N_LMS_Penjadwalan_Integrasi',
                            $barisIntegrasi,
                            ['Id_Penjadwalan_Integrasi', 'Id_Penjadwalan_Det'],
                        );

                        foreach ($hasilIntegrasi as $rowIntegrasi) {
                            $tujuan = $tujuanIntegrasi[$rowIntegrasi->Id_Penjadwalan_Det] ?? null;
                            if (! $tujuan) {
                                continue;
                            }

                            [$keyTujuan, $kodeTujuan] = $tujuan;
                            $apiBulkRequests[$keyTujuan]['Mapping_Integrasi'][$kodeTujuan] =
                                $rowIntegrasi->Id_Penjadwalan_Integrasi;
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
                } catch (\Throwable $e) {
                    /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                       jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                       supaya handler global tetap mencatat stack trace-nya dan responsnya
                       tak berubah sedikit pun. */
                    DB::rollBack();

                    throw $e;
                }

                // SETELAH commit (di luar transaksi): kirim notifikasi in-app + WhatsApp
                // untuk peserta chunk ini. Data nama/HP sudah ada di memori (tanpa query).
                // Fan-out dipindah ke job khusus agar worker bulk tetap ringan (1 dispatch
                // per chunk) dan HTTP WA tak pernah menahan lock transaksi.
                $recipients = [];
                foreach ($insertedPenjadwalan as $kodeKar => $rowPenjadwalan) {
                    $detail = $dataDetailKaryawan[$kodeKar] ?? null;
                    if (! $detail) {
                        continue;
                    }
                    $recipients[] = [
                        'kode' => $kodeKar,
                        'nama' => $detail['Nama'] ?? '-',
                        'hp' => $detail['No_Hp'] ?? '',
                        'id_penjadwalan' => $rowPenjadwalan->Id_Penjadwalan,
                    ];
                }
                if (! empty($recipients)) {
                    SendPenjadwalanNotificationJob::dispatch($recipients, $namaPaket);
                }

                $endpoint = 'api/v1/penjadwalan/training/store';
                $url = rtrim($this->domain, '/').'/'.ltrim($endpoint, '/');

                // PERUBAHAN: Loop berdasar key kombinasi materi tadi
                foreach ($apiBulkRequests as $keyApiGroup => $payload) {
                    $mappingIntegrasi = $payload['Mapping_Integrasi'];
                    $payload['Id_Integrasi'] = [];

                    foreach ($payload['Id_Karyawan'] as $kodeKar) {
                        if (isset($mappingIntegrasi[$kodeKar]) && ! empty($mappingIntegrasi[$kodeKar])) {
                            $payload['Id_Integrasi'][] = [
                                'Kode_Karyawan' => $kodeKar,
                                'Id_Integrasi' => $mappingIntegrasi[$kodeKar], // Sudah bukan array index ke-[0] lagi
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

                            /* Token dikumpulkan dulu, ditulis sekali per kelompok.
                               Bentuk lamanya satu UPDATE per butir jawaban —
                               jumlahnya persis sebanyak karyawan di kelompok itu.
                               Batasnya tetap per kelompok, sama seperti sebelumnya,
                               supaya kegagalan satu kelompok tidak ikut membatalkan
                               kelompok lain yang sudah berhasil. */
                            $pembaruanToken = [];
                            foreach ($responseDataApi as $itemApi) {
                                $kodeKarApi = $itemApi['Kode_Karyawan'] ?? null;

                                if (
                                    $kodeKarApi &&
                                    isset($mappingIntegrasi[$kodeKarApi]) &&
                                    isset($itemApi['Id_Ujian_Token'])
                                ) {
                                    $pembaruanToken[] = [
                                        'id' => $mappingIntegrasi[$kodeKarApi],
                                        'token' => $itemApi['Id_Ujian_Token'],
                                        'payload' => is_array($itemApi['Payload_Json'] ?? null)
                                            ? json_encode($itemApi['Payload_Json'])
                                            : $itemApi['Payload_Json'] ?? null,
                                    ];
                                }
                            }

                            $this->perbaruiTokenMassal($pembaruanToken);
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

            DB::table('N_LMS_Job_Payloads')->where('Process_Id', $this->processId)->delete();
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

    /**
     * Tulis token ujian untuk sederet baris integrasi dalam satu pernyataan.
     *
     * Menggantikan satu UPDATE per butir jawaban API. Bentuk `UPDATE ... FROM
     * (VALUES ...)` dipakai karena tiap baris menerima NILAI YANG BERBEDA —
     * `whereIn` tidak bisa melakukannya, dan `CASE` panjang jauh lebih sulit
     * dibaca.
     *
     * Payload_Json bertipe `text`. Menugaskan nvarchar ke text diperbolehkan
     * SQL Server (yang dilarang adalah MEMBANDINGKANNYA), jadi nilainya
     * di-cast eksplisit supaya niatnya terbaca dan tidak bergantung pada
     * konversi diam-diam.
     *
     * @param  array<int,array{id:int|string,token:string,payload:?string}>  $pembaruan
     */
    private function perbaruiTokenMassal(array $pembaruan): void
    {
        if ($pembaruan === []) {
            return;
        }

        /* Tiga parameter per baris; dipotong 500 supaya 1.500 parameter tetap
           jauh di bawah batas 2.100 milik SQL Server. */
        foreach (array_chunk($pembaruan, 500) as $potongan) {
            $nilai = [];
            $ikatan = [];

            foreach ($potongan as $satu) {
                $nilai[] = '(?, ?, ?)';
                $ikatan[] = $satu['id'];
                $ikatan[] = $satu['token'];
                $ikatan[] = $satu['payload'];
            }

            DB::update(
                'UPDATE t SET t.Id_Ujian_Token = v.token, t.Payload_Json = CAST(v.payload AS TEXT) '
                .'FROM N_LMS_Penjadwalan_Integrasi t '
                .'INNER JOIN (VALUES '.implode(', ', $nilai).') AS v(id, token, payload) '
                .'ON t.Id_Penjadwalan_Integrasi = v.id',
                $ikatan,
            );
        }
    }

    /**
     * Sisipkan sekumpulan baris dan KEMBALIKAN barisnya berikut id barunya —
     * tanpa membaca ulang tabelnya.
     *
     * ══ KENAPA INI ADA ════════════════════════════════════════════════════
     *
     * Ketiga penulisan di penjadwalan massal dulu mengambil id hasil INSERT
     * dengan cara membaca kembali tabel yang barusan ditulis, di dalam
     * transaksi yang sama. Yang paling merugikan pembacaan pada
     * N_LMS_Penjadwalan: penjadwalan berjalan sebagai beberapa job sekaligus,
     * dan seluruh job memakai Id_Master_Paket serta Waktu_Enroll yang sama —
     * jadi masing-masing membaca rentang kunci yang sedang ditulis job lain
     * dan belum di-commit.
     *
     * Terukur pada uji 900 karyawan: empat sesi saling menunggu LCK_M_S,
     * tunggu terlama 6,8 detik; naik jadi 9,4 detik setelah indeks dipasang,
     * karena indeks justru merapatkan rentang kuncinya. Indeks memang membuat
     * pembacaannya jadi seek (7 baca turun ke 2), tapi tidak menolong sama
     * sekali — baris yang dicari memang baris yang sedang dikunci job lain.
     *
     * OUTPUT menghapus pembacaannya sama sekali.
     *
     * ══ BATASAN YANG PERLU DIINGAT ════════════════════════════════════════
     *
     * Bentuk `OUTPUT ... VALUES` tanpa `INTO` hanya sah pada tabel TANPA
     * trigger. Ketiga tabel di sini tidak punya trigger (sudah diperiksa).
     * Bila suatu saat trigger ditambahkan, SQL Server menolak pernyataannya
     * secara tegas — bukan diam-diam mengembalikan hasil yang salah.
     *
     * @param  string  $tabel  nama tabel tujuan
     * @param  array  $baris  larik asosiatif; SEMUA baris wajib berkolom sama
     * @param  array  $kembali  kolom yang ingin dikembalikan
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function sisipkanDenganOutput(string $tabel, array $baris, array $kembali)
    {
        $hasil = collect();

        if (empty($baris)) {
            return $hasil;
        }

        // Dipotong 100 baris supaya jumlah parameter tetap jauh di bawah batas
        // 2.100 milik SQL Server. Sebelas kolom x 100 baris = 1.100 — aman,
        // dan potongan yang lebih besar akan gagal dengan galat yang menunjuk
        // ke jumlah parameter, bukan ke ukuran potongannya.
        foreach (array_chunk($baris, 100) as $potongan) {
            $kolom = array_keys($potongan[0]);
            $satuBaris = '('.implode(', ', array_fill(0, count($kolom), '?')).')';

            $ikatan = [];
            foreach ($potongan as $satu) {
                foreach ($kolom as $namaKolom) {
                    $ikatan[] = $satu[$namaKolom];
                }
            }

            $sql = 'INSERT INTO '.$tabel.' ('.implode(', ', $kolom).') '
                .'OUTPUT '.implode(', ', array_map(fn ($k) => 'INSERTED.'.$k, $kembali)).' '
                .'VALUES '.implode(', ', array_fill(0, count($potongan), $satuBaris));

            // selectFromWriteConnection, bukan select(): pada penyiapan baca/tulis
            // terpisah, select() boleh diarahkan ke replika — dan replika tidak
            // akan pernah melihat baris yang baru disisipkan transaksi ini.
            $hasil = $hasil->concat(DB::selectFromWriteConnection($sql, $ikatan));
        }

        return $hasil;
    }
}
