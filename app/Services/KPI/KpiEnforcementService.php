<?php

namespace App\Services\KPI;

use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * KpiEnforcementService
 *
 * Logika headless (tanpa request context / Auth) untuk:
 *   1. Mencari karyawan yang BELUM mengisi Update Performance pada periode tertentu
 *      padahal sudah punya jadwal aktif + parameter KPI (KPI_Pengajuan).
 *   2. Mengeksekusi "auto-cap enforcement": tulis KPI_Update_Performance dengan
 *      semua Achievement = 0, status langsung Approved (bypass approval normal),
 *      dan skor akhir tetap melewati cap min/max (config services.kpi.*).
 *   3. Mengelola counter batch di N_HRIS_KPI_Execution_Logs untuk progress polling.
 *
 * Perhitungan score & cap mengikuti UpdatePerformanceController (calculateScores +
 * applyAchievementCap + createFinalPerformanceHeaderByPass) agar konsisten.
 */
class KpiEnforcementService
{
    /**
     * Batas keamanan jumlah karyawan per sekali eksekusi auto-cap.
     *
     * Dinaikkan dari 500 ke 5.000 setelah dispatching dipindahkan ke Job Induk
     * (DispatchEnforceKpiBatchJob). Batas parameter SQL Server tetap aman karena
     * unfilledTerpilih() memecah kueri per 500 parameter.
     */
    public const MAX_ENFORCE_ITEMS = 5000;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    /**
     * Batch dibatalkan admin. Job anak yang masih antre di Cloud Tasks tetap
     * akan dikirim ulang (akun aplikasi tak berhak menghapus task), jadi job
     * itulah yang wajib berhenti sendiri saat melihat status ini — kalau tidak,
     * transaksi yang sudah dibatalkan dibuat ulang (terjadi 12 Okt 2026).
     */
    public const STATUS_CANCELLED = 'cancelled';

    public const LOG_TABLE = 'N_HRIS_KPI_Execution_Logs';

    public const LOG_DETAIL_TABLE = 'N_HRIS_KPI_Execution_Logs_Detail';

    public const ASYNC_TASK_TYPE = 'kpi-auto-cap';

    public const ASYNC_MODULE = 'kpi-enforcement';

    /**
     * Bangun batch_id unik untuk satu eksekusi enforcement.
     */
    public function makeBatchId(): string
    {
        return 'kpi-enf-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
    }

    /**
     * Buat header batch (status queued). Mengembalikan Id_Log.
     */
    public function startBatch(string $batchId, ?string $periode, int $totalRecords, ?string $requestedBy, array $filter = []): int
    {
        $now = now();

        $idLog = (int) DB::table(self::LOG_TABLE)->insertGetId([
            'Batch_Id' => $batchId,
            'Periode' => $periode ? Carbon::parse($periode)->toDateString() : null,
            'Total_Records' => $totalRecords,
            'Processed_Records' => 0,
            'Total_Success' => 0,
            'Total_Failed' => 0,
            'Status' => self::STATUS_QUEUED,
            'Filter_Json' => json_encode($filter),
            'Requested_By' => $requestedBy,
            'Started_At' => $now,
            'Created_At' => $now,
            'Updated_At' => $now,
        ], 'Id_Log');

        // Catat juga ke antrean tugas async sentral agar tampil di panel topbar ("Tugas & Aktivitas").
        DB::table('N_HRIS_Async_Tasks')->insert([
            'Export_Id' => $batchId,
            'Task_Type' => self::ASYNC_TASK_TYPE,
            'Module' => self::ASYNC_MODULE,
            'Format' => 'auto-cap',
            'Payload_Json' => json_encode([
                'periode' => $periode ? Carbon::parse($periode)->format('Y-m') : null,
                'total_records' => $totalRecords,
            ]),
            'Status' => 'queued',
            'Total_Records' => max(1, $totalRecords),
            'Processed_Records' => 0,
            'Percent' => 0,
            'Requested_By' => $requestedBy,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return $idLog;
    }

    /**
     * Tandai batch mulai diproses (dipanggil job pertama yang jalan).
     */
    public function markProcessing(string $batchId): void
    {
        DB::table(self::LOG_TABLE)
            ->where('Batch_Id', $batchId)
            ->where('Status', self::STATUS_QUEUED)
            ->update([
                'Status' => self::STATUS_PROCESSING,
                'Updated_At' => now(),
            ]);

        DB::table('N_HRIS_Async_Tasks')
            ->where('Export_Id', $batchId)
            ->where('Task_Type', self::ASYNC_TASK_TYPE)
            ->where('Status', 'queued')
            ->update([
                'Status' => 'processing',
                'Started_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Batalkan batch yang masih antre/berjalan (tombol Batalkan di panel Tugas &
     * Aktivitas). Hanya MENGHENTIKAN karyawan yang belum diproses — job sisa
     * keluar sendiri lewat batchDibatalkan(). Transaksi yang sudah terbentuk
     * tidak ikut dibatalkan; itu jalur pembatalan transaksi di dashboard KPI.
     *
     * @return bool false bila batch tak ditemukan, bukan milik peminta, atau sudah selesai
     */
    public function batalkanBatch(string $batchId, ?string $requestedBy): bool
    {
        $kena = DB::table(self::LOG_TABLE)
            ->where('Batch_Id', $batchId)
            ->where('Requested_By', $requestedBy)
            ->whereIn('Status', [self::STATUS_QUEUED, self::STATUS_PROCESSING])
            ->update([
                'Status' => self::STATUS_CANCELLED,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);

        if ($kena === 0) {
            return false;
        }

        DB::table('N_HRIS_Async_Tasks')
            ->where('Export_Id', $batchId)
            ->where('Task_Type', self::ASYNC_TASK_TYPE)
            ->update([
                'Status' => self::STATUS_CANCELLED,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);

        return true;
    }

    /** Batch sudah dibatalkan admin? Job yang melihat ini harus keluar tanpa memproses. */
    public function batchDibatalkan(string $batchId): bool
    {
        return DB::table(self::LOG_TABLE)
            ->where('Batch_Id', $batchId)
            ->where('Status', self::STATUS_CANCELLED)
            ->exists();
    }

    /**
     * Tandai batch gagal total (misalnya job induk gagal dispatch).
     */
    public function markFailed(string $batchId): void
    {
        DB::table(self::LOG_TABLE)
            ->where('Batch_Id', $batchId)
            ->update([
                'Status' => self::STATUS_FAILED,
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);

        DB::table('N_HRIS_Async_Tasks')
            ->where('Export_Id', $batchId)
            ->where('Task_Type', self::ASYNC_TASK_TYPE)
            ->update([
                'Status' => 'failed',
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Proses enforcement satu karyawan untuk satu periode, lalu catat hasil ke
     * detail batch + increment counter header. Aman idempoten secukupnya: bila
     * sudah ada KPI_Update_Performance aktif untuk user+periode, dianggap sukses
     * (skip) tanpa menulis ganda.
     *
     * @return string SUCCESS | FAILED
     */
    public function enforceForEmployee(string $batchId, int $idUser, string $periode, ?int $idPenjadwalanPenilaian = null): string
    {
        $status = 'SUCCESS';
        $message = null;
        $noTransaksi = null;
        $kodeKaryawan = null;

        /* Nomor diambil SEBELUM transaksi: counter-nya mengunci satu baris
           selama satu UPDATE autocommit. Dipanggil di dalam transaksi, kunci itu
           tertahan sampai commit — dan penegakan massal ini akan memblokir
           finalisasi interaktif yang memakai seri yang sama.

           Harganya: karyawan yang dilewati atau gagal ikut memakan satu nomor.
           Nomor "bolong" memang sudah jadi norma sistem sejak seluruh seri lain
           memakai counter yang sama. */
        $noTransaksiDisiapkan = $this->generateNewTransactionNumber();

        try {
            $result = DB::transaction(function () use ($idUser, $periode, $idPenjadwalanPenilaian, $noTransaksiDisiapkan) {
                $karyawan = DB::table('Karyawan')->where('UserID_Web', $idUser)->first();
                if (! $karyawan) {
                    throw new \Exception('Data karyawan tidak ditemukan.');
                }

                $targetDate = Carbon::parse($periode);

                // Kebijakan: SATU periode = SATU performance (union lintas penjadwalan).
                // Bila user sudah punya performance aktif di periode ini (penjadwalan
                // manapun), skip (idempoten) — cegah duplikasi/double-count.
                $existing = DB::table('KPI_Update_Performance')
                    ->where('Id_User', $idUser)
                    ->where('Flag_Aktif', 'Y')
                    // Record dibatalkan (Status='Y') tidak dianggap "sudah mengisi" -> hanya
                    // performance aktif (Status NULL) yang membuat enforce di-skip (idempoten).
                    ->whereNull('Status')
                    ->where('Periode', $targetDate->format('Y-m'))
                    ->exists();
                if ($existing) {
                    return ['kode' => $karyawan->Kode_Karyawan, 'no_transaksi' => null, 'skipped' => true];
                }

                // Resolve penjadwalan PENILAIAN target untuk user ini: override bila
                // diberikan, selain itu AUTO = penjadwalan terbaru yang dimiliki user
                // pada periode ini (perorangan diutamakan; fallback global).
                $idPenjadwalanTarget = $this->resolveTargetSchedule($karyawan->Kode_Karyawan, $targetDate, $idPenjadwalanPenilaian);

                $detailSet = $this->buildZeroedDetailSet($idUser, $targetDate);
                // Harus ada minimal 1 parameter EXACT-MATCH (Flag_Aktif='Y') untuk
                // periode ini — yang punya nilai target & boleh dinilai. Bila semua
                // hanya cocok via range (Q/S/Y belum waktunya), tidak ada yang dinilai.
                $hasActive = $detailSet['details']->contains(fn ($d) => ($d->Flag_Aktif ?? 'Y') === 'Y');
                if (! $hasActive) {
                    throw new \Exception('Tidak ada parameter KPI dengan target untuk periode ini.');
                }

                // Semua Achievement = 0 -> score 0, lalu cap (akan floor ke cap_min).
                $scores = $this->calculateScores($detailSet['details']);
                $scores = $this->applyAchievementCap($scores);

                $scoreForChecking = floor($scores['final_score_percentage']);
                $performanceLevel = $this->getPerformanceLevel($scoreForChecking);
                $noTransaksi = $noTransaksiDisiapkan;

                $idHeaderFinal = $this->createFinalPerformanceHeader(
                    $detailSet['pengajuan'],
                    $detailSet['periodeDate'],
                    $scores,
                    $performanceLevel,
                    $noTransaksi,
                    $idUser,
                    $idPenjadwalanTarget,
                );

                $this->createFinalPerformanceDetails($idHeaderFinal, $detailSet['details'], $karyawan->Kode_Karyawan);

                return [
                    'kode' => $karyawan->Kode_Karyawan,
                    'no_transaksi' => $noTransaksi,
                    'skipped' => false,
                ];
            });

            $kodeKaryawan = $result['kode'] ?? null;
            $noTransaksi = $result['no_transaksi'] ?? null;
            if (! empty($result['skipped'])) {
                $message = 'Sudah memiliki performance aktif (skip).';
            }
        } catch (\Throwable $e) {
            $status = 'FAILED';
            $message = $e->getMessage();
            Log::channel('UpdatePerformanceController')->error(
                'KPI_ENFORCE_ERROR: '.$e->getMessage(),
                ['id_user' => $idUser, 'periode' => $periode, 'batch_id' => $batchId],
            );
        }

        $this->recordDetailAndIncrement($batchId, $idUser, $kodeKaryawan, $noTransaksi, $status, $message);

        return $status;
    }

    /**
     * Resolve penjadwalan PENILAIAN target untuk satu karyawan pada satu periode.
     *
     *   $override != null -> pakai itu (admin custom satu penjadwalan untuk semua).
     *   AUTO -> penjadwalan penilaian aktif untuk periode ini yang DIMILIKI user:
     *           perorangan (terdaftar di N_KPI_Master_Penjadwalan_Perorangan)
     *           diutamakan & ambil terbaru; bila tidak ada, fallback global
     *           (Flag_Perorangan='T') terbaru pada periode tsb.
     *
     * @return int|null Id_Master_Penjadwalan, atau null bila tak ada (header
     *                  akan fallback ke Id_Penjadwalan pengajuan).
     */
    private function resolveTargetSchedule(string $kodeKaryawan, Carbon $targetDate, ?int $override): ?int
    {
        if ($override) {
            return $override;
        }

        $periode = $targetDate->format('Y-m');

        // Penjadwalan PERORANGAN milik user pada periode ini (paling baru).
        $perorangan = DB::table('KPI_Master_Penjadwalan as j')
            ->join('N_KPI_Master_Penjadwalan_Perorangan as pp', 'pp.Id_Master_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('j.Flag_Penilaian_KPI', 'Y')
            ->where('j.Flag_Aktif', 'Y')
            ->where('j.Flag_Perorangan', 'Y')
            ->where('j.Periode', $periode)
            ->where('pp.Kode_Karyawan', $kodeKaryawan)
            ->where('pp.Flag_Aktif', 'Y')
            ->orderByDesc('j.Id_Master_Penjadwalan')
            ->value('j.Id_Master_Penjadwalan');

        if ($perorangan) {
            return (int) $perorangan;
        }

        // Fallback: penjadwalan GLOBAL penilaian periode ini (paling baru).
        $global = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Perorangan', 'T')
            ->where('Periode', $periode)
            ->orderByDesc('Id_Master_Penjadwalan')
            ->value('Id_Master_Penjadwalan');

        return $global ? (int) $global : null;
    }

    /**
     * Simulasi hasil enforcement untuk SATU karyawan TANPA menulis ke DB.
     *
     * $withDetails = false (default) -> HEADER saja (total score & achievement)
     *   untuk list preview, ringan. Tidak mengirim array detail parameter.
     * $withDetails = true -> sertakan detail tiap parameter KPI (semua 0) untuk
     *   accordion, dipanggil hanya saat baris di-klik.
     *
     * Jika karyawan sudah punya performance aktif untuk periode -> kembalikan
     * hasil REAL dari DB (status 'enforced').
     *
     * @return array payload siap-JSON (status: simulated|enforced|error)
     */
    public function simulateForEmployee(int $idUser, string $periode, bool $withDetails = false, ?int $idPenjadwalanPenilaian = null): array
    {
        $targetDate = Carbon::parse($periode);

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $idUser)->first();
        if (! $karyawan) {
            return ['id_user' => $idUser, 'status' => 'error', 'message' => 'Karyawan tidak ditemukan.'];
        }

        $base = [
            'id_user' => $idUser,
            'kode_karyawan' => $karyawan->Kode_Karyawan,
            'nama' => $karyawan->Nama,
        ];

        // Bila sudah ada performance aktif di periode ini (penjadwalan MANAPUN) ->
        // tampilkan hasil real (union per-periode, konsisten dgn kebijakan enforce).
        $real = $this->getEnforcedResult($idUser, $targetDate, $withDetails);
        if ($real) {
            return array_merge($base, ['status' => 'enforced'], $real);
        }

        try {
            $detailSet = $this->buildZeroedDetailSet($idUser, $targetDate);
        } catch (\Throwable $e) {
            return array_merge($base, ['status' => 'error', 'message' => $e->getMessage()]);
        }

        if ($detailSet['details']->isEmpty()) {
            return array_merge($base, [
                'status' => 'error',
                'message' => 'Tidak ada parameter KPI relevan untuk periode ini.',
            ]);
        }

        $scores = $this->applyAchievementCap($this->calculateScores($detailSet['details']));

        $payload = array_merge($base, [
            'status' => 'simulated',
            // total_score = persentase akhir SUDAH di-cap (konsisten dgn hasil simpan).
            // Nilai mentah sebelum cap tetap ditampilkan di total_score_raw.
            'total_score' => (float) $scores['final_score_percentage'],
            'total_score_achievement' => (float) $scores['sum_of_scores'],
            'total_score_raw' => (float) ($scores['raw_final_score_percentage'] ?? 0),
            'total_bobot' => (float) $scores['sum_of_active_bobot'],
            'is_capped' => ! empty($scores['is_capped']),
            'cap_min' => (float) ($scores['cap_min_applied'] ?? 0),
            'cap_max' => (float) ($scores['cap_max_applied'] ?? 0),
        ]);

        if ($withDetails) {
            // Hanya parameter yang benar-benar dinilai (exact-match periode) yang
            // ditampilkan — sama dengan yang disimpan & dihitung ke total. Parameter
            // Q/S/Y yang belum waktunya tidak ditampilkan agar tidak membingungkan.
            $payload['details'] = $detailSet['details']
                ->filter(fn ($d) => ($d->Flag_Aktif ?? 'Y') === 'Y')
                ->map(fn ($d) => [
                    'nama_parameter' => $d->Nama_Parameter,
                    'tipe_target' => $d->Nama_Target,
                    'kode_target' => $d->Kode_Target,
                    'periode_target' => $d->Periode_Target,
                    'bobot' => (float) $d->Bobot,
                    'nilai_actual' => (float) $d->Nilai_Actual,
                    'score' => (float) $d->Score,
                    'achievement' => (float) $d->Achievement,
                    'score_raw' => isset($d->Score_Raw) ? (float) $d->Score_Raw : null,
                    'achievement_raw' => isset($d->Achievement_Raw) ? (float) $d->Achievement_Raw : null,
                    'is_capped' => ($d->Flag_Capped ?? 'T') === 'Y',
                ])->values()->all();
        }

        return $payload;
    }

    /**
     * Ambil hasil REAL enforcement (record KPI_Update_Performance aktif) untuk
     * karyawan+periode. Mengembalikan null bila belum ada.
     *
     * $withDetails = false -> header saja (query detail di-skip, ringan).
     */
    public function getEnforcedResult(int $idUser, Carbon $targetDate, bool $withDetails = false, ?int $idPenjadwalanPenilaian = null): ?array
    {
        $header = DB::table('KPI_Update_Performance')
            ->where('Id_User', $idUser)
            ->where('Flag_Aktif', 'Y')
            ->where('Periode', $targetDate->format('Y-m'))
            // Bila penjadwalan penilaian diberikan, cek hasil enforce spesifik ke
            // penjadwalan tsb agar tidak salah ambil dari penjadwalan lain.
            ->when($idPenjadwalanPenilaian, fn ($q) => $q->where('Id_Penjadwalan', $idPenjadwalanPenilaian))
            ->orderByDesc('Tanggal')
            ->first();

        if (! $header) {
            return null;
        }

        $payload = [
            'no_transaksi' => $header->No_Transaksi,
            'total_score' => (float) $header->Total_Score,
            'total_score_achievement' => (float) $header->Total_Score_Achievement,
            'total_score_raw' => isset($header->Total_Score_Raw) ? (float) $header->Total_Score_Raw : (float) $header->Total_Score,
            'total_bobot' => (float) $header->Total_Bobot,
            'is_capped' => ($header->Flag_Capped ?? 'T') === 'Y',
            'cap_min' => isset($header->Cap_Min_Applied) ? (float) $header->Cap_Min_Applied : 0.0,
            'cap_max' => isset($header->Cap_Max_Applied) ? (float) $header->Cap_Max_Applied : 0.0,
        ];

        if ($withDetails) {
            $details = DB::table('KPI_Update_Performance_Detail as upd')
                ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                ->where('upd.Id_Update_Performance', $header->Id_Update_Performance)
                ->get([
                    'pd.Nama_Parameter',
                    'pd.Bobot',
                    'pdet.Periode as Periode_Target',
                    'upd.Nilai_Actual',
                    'upd.Score',
                    'upd.Achievement',
                    'upd.Score_Raw',
                    'upd.Achievement_Raw',
                    'upd.Flag_Capped',
                ]);

            $payload['details'] = $details->map(fn ($d) => [
                'nama_parameter' => $d->Nama_Parameter,
                'tipe_target' => null,
                'kode_target' => null,
                'periode_target' => $d->Periode_Target,
                'bobot' => (float) $d->Bobot,
                'nilai_actual' => (float) $d->Nilai_Actual,
                'score' => (float) $d->Score,
                'achievement' => (float) $d->Achievement,
                'score_raw' => isset($d->Score_Raw) ? (float) $d->Score_Raw : null,
                'achievement_raw' => isset($d->Achievement_Raw) ? (float) $d->Achievement_Raw : null,
                'is_capped' => ($d->Flag_Capped ?? 'T') === 'Y',
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * Klasifikasi tiap Id_User (dari $idUsers) terhadap ketersediaan parameter & target
     * untuk $targetDate. Dipakai untuk membedakan karyawan yang benar-benar "belum
     * mengisi" dari yang memang tak ada yang bisa diisi pada periode:
     *   - 'has'          : punya >= 1 parameter dengan target relevan periode ini.
     *   - 'no_target'    : punya parameter, TAPI tak ada target yang relevan periode ini.
     *   - 'no_parameter' : tak punya pengajuan disetujui / tak punya parameter sama sekali.
     *
     * Versi BATCH (3 query) dari logika buildZeroedDetailSet + resolveRelevantPeriodDetail
     * agar bebas N+1 saat dipanggil dari monitoring/export.
     *
     * @param  array<int>  $idUsers
     * @return array<int,string> Peta Id_User => 'has'|'no_target'|'no_parameter'.
     */
    public function classifyParameterTargetForPeriod(array $idUsers, Carbon $targetDate): array
    {
        $idUsers = array_values(array_unique(array_filter($idUsers, fn ($v) => $v !== null)));
        if (empty($idUsers)) {
            return [];
        }

        // Default: semua kandidat dianggap tak punya parameter dulu.
        $result = [];
        foreach ($idUsers as $u) {
            $result[(int) $u] = 'no_parameter';
        }

        // 1. Pengajuan disetujui terbaru per user untuk tahun target (mirror buildZeroedDetailSet).
        $pengajuanRows = collect();
        foreach (array_chunk($idUsers, 2000) as $chunk) {
            $batch = DB::table('KPI_Pengajuan as p')
                ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
                ->whereIn('p.Id_User', $chunk)
                ->where('j.Tahun', $targetDate->year)
                ->whereNull('p.Status')
                ->where('p.Flag_Aktif', 'Y')
                ->where('p.Flag_Approval', 'Y')
                ->select('p.Id_Pengajuan', 'p.Id_User')
                ->orderBy(DB::raw('CAST(p.Tanggal + p.Jam AS DATETIME)'), 'DESC')
                ->get();
            $pengajuanRows = $pengajuanRows->merge($batch);
        }

        $pengajuanByUser = $pengajuanRows
            ->groupBy('Id_User')
            ->map(fn ($g) => $g->first()); // sudah terurut DESC -> ambil terbaru

        $idPengajuanList = $pengajuanByUser->pluck('Id_Pengajuan')->all();
        if (empty($idPengajuanList)) {
            return $result; // semua tetap 'no_parameter'
        }

        // Peta Id_Pengajuan -> Id_User untuk merangkai balik parameter ke user.
        $userByPengajuan = $pengajuanByUser->mapWithKeys(fn ($r) => [$r->Id_Pengajuan => (int) $r->Id_User]);

        // 2. Parameter (detail) + kode target untuk pengajuan-pengajuan tsb.
        $mainKpis = DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Master_Target as t', 'pd.Id_Master_Target', '=', 't.Id_Master_Target')
            ->whereIn('pd.Id_Pengajuan', $idPengajuanList)
            ->select('pd.Id_Pengajuan_Detail', 'pd.Id_Pengajuan', 't.Kode_Target')
            ->get();

        if ($mainKpis->isEmpty()) {
            return $result; // ada pengajuan tapi tanpa parameter -> 'no_parameter'
        }

        // Punya parameter -> minimal naik ke 'no_target' (sampai terbukti ada target relevan).
        $kpisByUser = [];
        foreach ($mainKpis as $kpi) {
            $idUser = $userByPengajuan[$kpi->Id_Pengajuan] ?? null;
            if ($idUser === null) {
                continue;
            }
            $kpisByUser[$idUser][] = $kpi;
            if (($result[$idUser] ?? null) === 'no_parameter') {
                $result[$idUser] = 'no_target';
            }
        }

        // 3. Semua target periode untuk parameter-parameter tsb (chunked untuk hindari 2100 param limit).
        $allPeriodDetails = collect();
        $detailIdChunks = array_chunk($mainKpis->pluck('Id_Pengajuan_Detail')->values()->unique()->toArray(), 2000);
        foreach ($detailIdChunks as $chunk) {
            $batch = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Detail', $chunk)
                ->get()
                ->groupBy('Id_Pengajuan_Detail');
            foreach ($batch as $key => $val) {
                $allPeriodDetails[$key] = $val;
            }
        }

        // 4. Naikkan ke 'has' HANYA bila ada >= 1 parameter dengan target EXACT-MATCH
        // periode (Periode Y-m == periode). Konsisten dengan command notif & enforcement
        // ($hasActive di buildZeroedDetailSet): target Q/S/Y yang baru cocok via range
        // (belum jatuh tempo) TIDAK dianggap "ada target untuk diisi" -> tetap 'no_target'.
        $targetYm = $targetDate->format('Y-m');
        foreach ($kpisByUser as $idUser => $kpis) {
            foreach ($kpis as $kpi) {
                $detailsForThisKpi = $allPeriodDetails->get($kpi->Id_Pengajuan_Detail);
                if (! $detailsForThisKpi) {
                    continue;
                }
                $hasExact = $detailsForThisKpi->contains(
                    fn ($d) => Carbon::parse($d->Periode)->format('Y-m') === $targetYm,
                );
                if ($hasExact) {
                    $result[$idUser] = 'has';
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Bulan-bulan dalam satu tahun yang PUNYA target jatuh tempo bagi satu orang.
     *
     * Aturan yang sama persis dengan classifyParameterTargetForPeriod() — pengajuan
     * disetujui terbaru tahun itu, target TEPAT di bulan itu — tetapi untuk 12 bulan
     * sekaligus dalam 2 kueri. Dipanggil per bulan, classify butuh hingga 3 kueri
     * per bulan; strip navigasi 12 bulan jadi puluhan kueri.
     *
     * @return array<int> nomor bulan (1..12) yang punya target
     */
    public function bulanBertargetSetahun(int $idUser, int $tahun): array
    {
        $idPengajuan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $idUser)
            ->where('j.Tahun', $tahun)
            ->whereNull('p.Status')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->orderBy(DB::raw('CAST(p.Tanggal + p.Jam AS DATETIME)'), 'DESC')
            ->value('p.Id_Pengajuan');

        if (! $idPengajuan) {
            return [];
        }

        return DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Master_Target as t', 'pd.Id_Master_Target', '=', 't.Id_Master_Target')
            ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->where('pd.Id_Pengajuan', $idPengajuan)
            ->where('det.Periode', 'like', $tahun.'-%')
            ->distinct()
            ->pluck('det.Periode')
            ->map(fn ($p) => (int) Carbon::parse($p)->format('n'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bangun himpunan detail (Achievement = 0) dari parameter KPI pengajuan
     * karyawan untuk periode target. Mirror logika pemilihan periode di
     * UpdatePerformanceController::getDataKpiItems (kode target M/Q/S/Y).
     *
     * @return array{pengajuan: object, periodeDate: \Carbon\Carbon, details: \Illuminate\Support\Collection}
     */
    private function buildZeroedDetailSet(int $idUser, Carbon $targetDate)
    {
        $pengajuan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $idUser)
            ->where('j.Tahun', $targetDate->year)
            ->whereNull('p.Status')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->select('p.*')
            ->orderBy(DB::raw('CAST(p.Tanggal + p.Jam AS DATETIME)'), 'DESC')
            ->first();

        if (! $pengajuan) {
            throw new \Exception('Data pengajuan KPI tidak ditemukan untuk periode ini.');
        }

        $mainKpis = DB::table('KPI_Pengajuan_Detail as pd')
            ->join('KPI_Master_Target as t', 'pd.Id_Master_Target', '=', 't.Id_Master_Target')
            ->where('pd.Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->select('pd.Id_Pengajuan_Detail', 'pd.Nama_Parameter', 'pd.Bobot', 't.Kode_Target', 't.Nama_Target')
            ->get();

        $allPeriodDetails = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $mainKpis->pluck('Id_Pengajuan_Detail'))
            ->get()
            ->groupBy('Id_Pengajuan_Detail');

        $details = collect();

        foreach ($mainKpis as $kpi) {
            $detailsForThisKpi = $allPeriodDetails->get($kpi->Id_Pengajuan_Detail);
            if (! $detailsForThisKpi) {
                continue;
            }

            $relevantDetail = $this->resolveRelevantPeriodDetail($detailsForThisKpi, $kpi->Kode_Target, $targetDate);
            if (! $relevantDetail) {
                continue;
            }

            // PENTING (identik dgn submit normal): hanya parameter dgn periode target
            // EXACT-MATCH periode yang dinilai yang Flag_Aktif='Y' (dihitung ke total).
            // Parameter Q/S/Y yang hanya cocok via range -> Flag_Aktif='T' (disimpan
            // sbg jejak, TAPI TIDAK ikut total bobot/score) karena belum waktunya
            // dinilai (mis. Quarterly yg quarter-nya belum berakhir).
            $isExact = Carbon::parse($relevantDetail->Periode)->format('Y-m') === $targetDate->format('Y-m');

            // Rasio nol yang sudah melewati penjepitan per parameter.
            $nolTerjepit = app(KpiScoringService::class)->scoreFromRatio(0.0, (float) $kpi->Bobot);

            // Bentuk objek detail "enforce": rasio 0 untuk semua.
            // Nama_Parameter/Kode_Target/Periode disertakan untuk keperluan UI accordion.
            $details->push((object) [
                'Id_Pengajuan_Det' => $relevantDetail->Id_Pengajuan_Det,
                'Nama_Parameter' => $kpi->Nama_Parameter,
                'Kode_Target' => $kpi->Kode_Target,
                'Nama_Target' => $kpi->Nama_Target,
                'Periode_Target' => $relevantDetail->Periode,
                'Bobot' => (float) $kpi->Bobot,
                'Nilai_Actual' => 0,
                // Rasio nol dilewatkan penjepitan yang sama dengan submit normal,
                // supaya total tetap sama dengan rata-rata baris yang ditampilkan.
                // Nol yang sebenarnya tidak hilang: ia pindah ke kolom mentah, dan
                // Flag_Capped menandai bahwa baris ini terangkat ke lantai cap —
                // bukti orangnya tidak menyerahkan apa pun, bukan mencapai separuh
                // target. Rumus TIDAK dievaluasi dengan aktual nol: rumus "semakin
                // kecil semakin baik" justru mengembalikan rasio penuh di sana.
                'Score' => $nolTerjepit['achievement'],
                'Achievement' => $nolTerjepit['score'],
                'Score_Raw' => $nolTerjepit['achievement_raw'],
                'Achievement_Raw' => $nolTerjepit['score_raw'],
                'Flag_Capped' => $nolTerjepit['is_capped'] ? 'Y' : 'T',
                'File_Berkas' => null,
                'Flag_Pica' => 'T',
                'Flag_Aktif' => $isExact ? 'Y' : 'T',
            ]);
        }

        return [
            'pengajuan' => $pengajuan,
            'periodeDate' => $targetDate,
            'details' => $details,
        ];
    }

    /**
     * Pilih KPI_Pengajuan_Det yang relevan untuk targetDate berdasarkan kode target.
     * Sama persis dgn logika UpdatePerformanceController (exact month -> M/Q/S/Y).
     */
    private function resolveRelevantPeriodDetail($detailsForThisKpi, ?string $kodeTarget, Carbon $targetDate)
    {
        foreach ($detailsForThisKpi as $detail) {
            $periodeDate = Carbon::parse($detail->Periode);
            if ($periodeDate->format('Y-m') === $targetDate->format('Y-m')) {
                return $detail;
            }
        }

        foreach ($detailsForThisKpi as $detail) {
            $periodeDate = Carbon::parse($detail->Periode);
            $isCurrent = false;

            if ($kodeTarget === 'M') {
                $isCurrent = $targetDate->isSameMonth($periodeDate);
            } elseif ($kodeTarget === 'Y') {
                $isCurrent = $targetDate->isSameYear($periodeDate);
            } elseif ($kodeTarget === 'Q') {
                $startDate = $periodeDate->copy()->subMonths(2)->startOfMonth();
                $endDate = $periodeDate->copy()->endOfMonth();
                $isCurrent = $targetDate->between($startDate, $endDate);
            } elseif ($kodeTarget === 'S') {
                $startDate = $periodeDate->copy()->subMonths(5)->startOfMonth();
                $endDate = $periodeDate->copy()->endOfMonth();
                $isCurrent = $targetDate->between($startDate, $endDate);
            }

            if ($isCurrent) {
                return $detail;
            }
        }

        return null;
    }

    /**
     * Hitung agregat score. Mirror UpdatePerformanceController::calculateScores.
     */
    private function calculateScores($detailsData): array
    {
        // Hanya parameter Flag_Aktif='Y' (exact-match periode) yang dihitung ke total
        // bobot/score — identik dgn submit normal. Parameter Q/S/Y yang belum waktunya
        // dinilai (Flag_Aktif='T') tidak boleh memengaruhi total.
        $activeDetails = $detailsData->filter(fn ($item) => ($item->Flag_Aktif ?? 'Y') === 'Y');

        return app(KpiScoringService::class)->aggregate($activeDetails);
    }

    /**
     * Terpusat di KpiScoringService. Salinan lokal sebelumnya memakai batas baku
     * 150/0 yang menyimpang dari batas yang sebenarnya berlaku — tak terlihat
     * selama config terisi, dan menampilkan angka salah begitu tidak.
     */
    private function applyAchievementCap(array $scores): array
    {
        return app(KpiScoringService::class)->applyCap($scores);
    }

    /**
     * Terpusat di KpiScoringService: skor di bawah seluruh band jatuh ke band
     * TERENDAH alih-alih melempar. Salinan lokal sebelumnya melempar, sehingga
     * satu record berkinerja sangat buruk bisa menggagalkan seluruh job.
     */
    private function getPerformanceLevel(float $finalScore)
    {
        return app(KpiScoringService::class)->resolveLevel($finalScore);
    }

    /**
     * Nomor AKPI lewat COUNTER ATOMIK — harus SAMA MEKANISMENYA dengan
     * UpdatePerformanceController::generateNewTransactionNumber().
     *
     * Ini bukan sekadar soal kecepatan. Seri AKPI punya DUA penulis: jalur
     * finalisasi di controller, dan penegakan massal di sini. Selama keduanya
     * memakai `MAX(...) + lockForUpdate`, keduanya saling mengunci sehingga
     * tak pernah bertabrakan. Begitu salah satunya pindah ke counter dan yang
     * lain tidak, keduanya berhenti berkoordinasi — counter membagikan 0006
     * sementara MAX+1 juga menghitung 0006, dan lahirlah nomor dokumen kembar.
     * Karena itu kedua penulis dipindahkan bersama-sama, bukan bergiliran.
     */
    private function generateNewTransactionNumber(): string
    {
        $now = Carbon::now();
        $prefix = 'AKPI-'.$now->format('m').'-'.$now->format('Y').'-';

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            fn () => DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', 'like', $prefix.'%')
                ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
        );
    }

    /**
     * Tulis header KPI_Update_Performance dgn status Approved (bypass).
     * Mirror UpdatePerformanceController::createFinalPerformanceHeaderByPass.
     */
    private function createFinalPerformanceHeader(
        $pengajuan,
        Carbon $periodeDate,
        array $scores,
        $performanceLevel,
        string $noTransaksi,
        int $idUser,
        ?int $idPenjadwalanPenilaian = null,
    ): int {
        return (int) DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'No_Transaksi_Ref' => null,
            'Id_Pengajuan' => $pengajuan->Id_Pengajuan,
            // Id_Penjadwalan = penjadwalan PENILAIAN terpilih (agar Performance
            // Tracking menemukannya); fallback ke penjadwalan pengajuan bila null.
            'Id_Penjadwalan' => $idPenjadwalanPenilaian ?? $pengajuan->Id_Penjadwalan,
            'Periode' => $periodeDate->format('Y-m'),
            // IDENTIK dgn finalisasi normal (UpdatePerformanceController):
            //   Total_Score = persentase akhir yang SUDAH di-cap (mis. di-floor ke min).
            //   Total_Score_Achievement = sum_of_scores faktual (tidak di-cap).
            //   Nilai raw (sebelum cap) disimpan terpisah di kolom _Raw untuk audit.
            'Total_Score' => (float) $scores['final_score_percentage'],
            'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
            'Total_Bobot' => (float) $scores['sum_of_active_bobot'],
            'Total_Score_Raw' => (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']),
            'Total_Score_Achievement_Raw' => (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']),
            'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
            // Detailnya dijepit per parameter, sama seperti finalisasi normal.
            // Tanpa label ini record auto-cap menyamar sebagai record lama ber-cap TOTAL.
            'Metode_Cap' => \App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController::METODE_CAP_PARAMETER,
            'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
            'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
            'Id_Performance' => $performanceLevel->Id_Master_Performance,
            'Id_User' => $idUser,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status_Pengajuan' => 'disetujui',
            'Flag_Team' => 'T',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
        ]);
    }

    /**
     * Tulis detail KPI_Update_Performance_Detail (semua Achievement = 0).
     */
    private function createFinalPerformanceDetails(int $idHeaderFinal, $detailsData, string $kodeKaryawan): void
    {
        // IDENTIK submit normal: detail final HANYA berisi parameter exact-match
        // (Flag_Aktif='Y'). Parameter Q/S/Y yang belum waktunya dinilai tidak ditulis
        // ke detail (tidak ada nilai target untuk periode ini), sehingga tidak ikut total.
        $batchDetails = $detailsData
            ->filter(fn ($item) => ($item->Flag_Aktif ?? 'Y') === 'Y')
            ->map(fn ($item) => [
                'Id_Update_Performance' => $idHeaderFinal,
                'Id_Pengajuan_Det' => $item->Id_Pengajuan_Det,
                'Kode_Karyawan' => $kodeKaryawan,
                'Nilai_Actual' => $item->Nilai_Actual,
                'Score' => $item->Score,
                'Achievement' => $item->Achievement,
                'Score_Raw' => $item->Score_Raw ?? $item->Score,
                'Achievement_Raw' => $item->Achievement_Raw ?? $item->Achievement,
                'Flag_Capped' => $item->Flag_Capped ?? 'T',
                'File_Berkas' => $item->File_Berkas,
                'Flag_Pica' => $item->Flag_Pica,
            ])
            ->values()
            ->all();

        if (! empty($batchDetails)) {
            DB::table('KPI_Update_Performance_Detail')->insert($batchDetails);
        }
    }

    /**
     * Catat detail batch + increment counter header secara atomic. Bila ini record
     * terakhir (Processed_Records mencapai Total_Records) -> finalisasi status batch.
     */
    private function recordDetailAndIncrement(
        string $batchId,
        int $idUser,
        ?string $kodeKaryawan,
        ?string $noTransaksi,
        string $status,
        ?string $message,
    ): void {
        $log = DB::table(self::LOG_TABLE)->where('Batch_Id', $batchId)->first();
        if (! $log) {
            return;
        }

        /* Satu karyawan dihitung SEKALI per batch. Cloud Tasks bisa mengirim job
           yang sama lebih dari sekali (percobaan ulang setelah timeout di sisi
           pengirim padahal job-nya sudah selesai). Kiriman kedua masuk jalur
           "skip" dan dulu ikut dihitung: 12 Okt 2026 batch 588 orang tercatat
           872 diproses, dan ditandai selesai sebelum semua karyawan diproses. */
        if (DB::table(self::LOG_DETAIL_TABLE)->where('Batch_Id', $batchId)->where('Id_User', $idUser)->exists()) {
            return;
        }

        DB::table(self::LOG_DETAIL_TABLE)->insert([
            'Id_Log' => $log->Id_Log,
            'Batch_Id' => $batchId,
            'Kode_Karyawan' => $kodeKaryawan,
            'Id_User' => $idUser,
            'No_Transaksi' => $noTransaksi,
            'Status' => $status,
            'Message' => $message,
            'Created_At' => now(),
        ]);

        $successInc = $status === 'SUCCESS' ? 1 : 0;
        $failedInc = $status === 'FAILED' ? 1 : 0;

        // Status 'cancelled' tak boleh ditimpa job yang kebetulan sedang jalan
        // saat admin membatalkan — penanda itulah yang menghentikan sisanya.
        DB::table(self::LOG_TABLE)
            ->where('Batch_Id', $batchId)
            ->where('Status', '<>', self::STATUS_CANCELLED)
            ->update([
                'Processed_Records' => DB::raw('Processed_Records + 1'),
                'Total_Success' => DB::raw('Total_Success + '.$successInc),
                'Total_Failed' => DB::raw('Total_Failed + '.$failedInc),
                'Status' => self::STATUS_PROCESSING,
                'Updated_At' => now(),
            ]);

        $fresh = DB::table(self::LOG_TABLE)->where('Batch_Id', $batchId)->first();
        if ($fresh && $fresh->Status !== self::STATUS_CANCELLED) {
            $total = (int) $fresh->Total_Records;
            $processed = (int) $fresh->Processed_Records;
            $percent = $total > 0 ? (int) round(($processed / $total) * 100) : 0;
            $isComplete = $processed >= $total;
            $finalStatus = $fresh->Total_Failed > 0
                ? ($fresh->Total_Failed >= $total ? self::STATUS_FAILED : self::STATUS_COMPLETED_WITH_ERRORS)
                : self::STATUS_COMPLETED;

            if ($isComplete) {
                DB::table(self::LOG_TABLE)
                    ->where('Batch_Id', $batchId)
                    ->update([
                        'Status' => $finalStatus,
                        'Completed_At' => now(),
                        'Updated_At' => now(),
                    ]);
            }

            DB::table('N_HRIS_Async_Tasks')
                ->where('Export_Id', $batchId)
                ->where('Task_Type', self::ASYNC_TASK_TYPE)
                ->update([
                    'Processed_Records' => $processed,
                    'Percent' => min(100, $percent),
                    'Status' => $isComplete ? ($finalStatus === self::STATUS_FAILED ? 'failed' : 'ready') : 'processing',
                    'Completed_At' => $isComplete ? now() : null,
                    'Updated_At' => now(),
                ]);
        }
    }

    /**
     * Ambil progress batch untuk polling. Mengembalikan payload siap-JSON.
     */
    public function getBatchProgress(string $batchId): ?array
    {
        $log = DB::table(self::LOG_TABLE)->where('Batch_Id', $batchId)->first();
        if (! $log) {
            return null;
        }

        $total = (int) $log->Total_Records;
        $processed = (int) $log->Processed_Records;
        $percent = $total > 0 ? (int) round(($processed / $total) * 100) : 0;

        return [
            'batch_id' => $log->Batch_Id,
            'status' => $log->Status,
            'total_records' => $total,
            'processed_records' => $processed,
            'success' => (int) $log->Total_Success,
            'failed' => (int) $log->Total_Failed,
            'percent' => $percent,
            'periode' => $log->Periode,
            'started_at' => $log->Started_At,
            'completed_at' => $log->Completed_At,
        ];
    }
}
