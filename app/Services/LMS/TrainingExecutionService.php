<?php

namespace App\Services\LMS;

use App\Services\LMS\Training\Scoring\PenilaiEkspresiNumerik;
use App\Services\SYSTEM\Application\Database\GalatDeadlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class TrainingExecutionService
{
    public function getExecutionSummariesByPenjadwalanIds(array $penjadwalanIds)
    {
        $normalizedIds = collect($penjadwalanIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return collect();
        }

        $rows = DB::table('N_LMS_Transaksi_Training as trx')
            ->whereIn('trx.Id_Penjadwalan', $normalizedIds->all())
            ->where('trx.Flag_Aktif', 'Y')
            ->select([
                'trx.Id_Penjadwalan',
                'trx.Id_Transaksi_Training',
                'trx.Total_Score',
                'trx.Score_Learning_Gain',
                'trx.Id_Keterangan_Kelulusan',
                'trx.Created_At',
                'trx.Updated_At',
            ])
            ->orderByDesc('trx.Updated_At')
            ->orderByDesc('trx.Id_Transaksi_Training')
            ->get();

        return $rows
            ->groupBy('Id_Penjadwalan')
            ->map(fn ($group) => $group->first())
            ->values()
            ->keyBy('Id_Penjadwalan');
    }

    public function bootstrapAttendance(array $payload): array
    {
        $now = Carbon::now();

        return $this->withDeadlockRetry(function () use ($payload, $now) {
            return DB::transaction(function () use ($payload, $now) {
                $attendanceId = DB::table('N_LMS_CHECKINOUT')->insertGetId([
                    'Id_Penjadwalan' => $payload['Id_Penjadwalan'],
                    'Id_Penjadwalan_Detail' => $payload['Id_Penjadwalan_Detail'] ?? null,
                    'Checktime' => $now,
                    'Output' => $payload['Output'],
                    'File_Path' => $payload['File_Path'] ?? null,
                    'Latitude' => $payload['Latitude'] ?? null,
                    'Longitude' => $payload['Longitude'] ?? null,
                ]);

                $transaksi = $this->ensureTransaksiTraining((int) $payload['Id_Penjadwalan']);
                $transaksiDetail = null;
                $transaksiDet = null;
                $progress = null;

                if (
                    ! empty($payload['Id_Penjadwalan_Detail']) &&
                    ! empty($payload['Id_Materi']) &&
                    ! empty($payload['Id_Materi_Det'])
                ) {
                    $transaksiDetail = $this->ensureTransaksiTrainingDetail(
                        (int) $transaksi->Id_Transaksi_Training,
                        (int) $payload['Id_Penjadwalan_Detail'],
                        (int) $payload['Id_Materi'],
                        (int) $payload['Id_Materi_Det'],
                        $payload['Flag_Third_Party'] ?? 'T',
                    );

                    $transaksiDet = $this->ensureTransaksiTrainingDet(
                        (int) $transaksiDetail->Id_Transaksi_Training_Detail,
                    );
                    $progress = $this->ensureTrainingProgress(
                        (int) $transaksi->Id_Transaksi_Training,
                        (int) $transaksiDetail->Id_Transaksi_Training_Detail,
                        (int) ($payload['Urutan'] ?? 0),
                        'SELESAI',
                    );

                    $this->markPenjadwalanDetCompleted($payload, $now);
                    DB::table('N_LMS_Transaksi_Training_Detail')
                        ->where('Id_Transaksi_Training_Detail', $transaksiDetail->Id_Transaksi_Training_Detail)
                        ->update(['Flag_Selesai' => 'Y']);
                    $this->syncCompletionState(
                        (int) $payload['Id_Penjadwalan'],
                        (int) $payload['Id_Penjadwalan_Detail'],
                        (int) $transaksi->Id_Transaksi_Training,
                    );

                    $this->recalculateFinalScoreIfReady(
                        (int) $payload['Id_Penjadwalan'],
                        (int) $payload['Id_Materi'],
                    );
                }

                return [
                    'attendance_id' => $attendanceId,
                    'checktime' => $now,
                    'transaksi_training_id' => $transaksi->Id_Transaksi_Training,
                    'transaksi_training_detail_id' => $transaksiDetail->Id_Transaksi_Training_Detail ?? null,
                    'transaksi_training_det_id' => $transaksiDet->Id_Transaksi_Training_Det ?? null,
                    'training_progress_id' => $progress->Id_Training_Progress ?? null,
                ];
            });
        });
    }

    public function recordActivityProgress(array $payload): array
    {
        return $this->withDeadlockRetry(function () use ($payload) {
            return DB::transaction(fn () => $this->recordActivityProgressInTransaction($payload));
        });
    }

    public function completeActivity(array $payload): array
    {
        return $this->withDeadlockRetry(function () use ($payload) {
            return DB::transaction(function () use ($payload) {
                $completedPayload = [
                    ...$payload,
                    'Status_Progress' => 'SELESAI',
                    'MarkStarted' => true,
                    'MarkCompleted' => true,
                ];
                $result = $this->recordActivityProgressInTransaction($completedPayload);

                if (! empty($payload['Score']) || ! empty($payload['Fallback_Referensi'])) {
                    $updates = [];
                    if (array_key_exists('Score', $payload)) {
                        $updates['Skor_Akhir'] = $payload['Score'] ?? 0;
                    }
                    if (array_key_exists('Fallback_Referensi', $payload)) {
                        $updates['Id_Fallback_Referensi'] = $payload['Fallback_Referensi'];
                    }

                    if ($updates !== []) {
                        DB::table('N_LMS_Transaksi_Training_Det')
                            ->where('Id_Transaksi_Training_Det', $result['transaksi_training_det_id'])
                            ->update($updates);
                    }
                }

                if (! empty($payload['Id_Materi'])) {
                    $this->recalculateFinalScoreIfReady((int) $payload['Id_Penjadwalan'], (int) $payload['Id_Materi']);
                }

                return $result;
            });
        });
    }

    private function recordActivityProgressInTransaction(array $payload): array
    {
        $transaksi = $this->ensureTransaksiTraining((int) $payload['Id_Penjadwalan']);
        $transaksiDetail = $this->ensureTransaksiTrainingDetail(
            (int) $transaksi->Id_Transaksi_Training,
            (int) $payload['Id_Penjadwalan_Detail'],
            (int) $payload['Id_Materi'],
            (int) $payload['Id_Materi_Det'],
            $payload['Flag_Third_Party'] ?? 'T',
        );
        $transaksiDet = $this->ensureTransaksiTrainingDet((int) $transaksiDetail->Id_Transaksi_Training_Detail);

        $statusProgress = $payload['Status_Progress'] ?? 'PROGRESS';
        $progress = $this->ensureTrainingProgress(
            (int) $transaksi->Id_Transaksi_Training,
            (int) $transaksiDetail->Id_Transaksi_Training_Detail,
            (int) ($payload['Urutan'] ?? 0),
            $statusProgress,
        );

        if (! empty($payload['Id_Penjadwalan_Det'])) {
            $updates = [];
            if (($payload['MarkStarted'] ?? true) === true) {
                $updates['Flag_Checkin'] = 'Y';
                $updates['Waktu_Mulai_Akses'] = DB::raw('COALESCE(Waktu_Mulai_Akses, GETDATE())');
            }
            if (($payload['MarkCompleted'] ?? false) === true) {
                $updates['Flag_Selesai'] = 'Y';
                $updates['Waktu_Selesai'] = now();
            }

            if ($updates !== []) {
                DB::table('N_LMS_Penjadwalan_Det')
                    ->where('Id_Penjadwalan_Det', $payload['Id_Penjadwalan_Det'])
                    ->update($updates);
            }
        }

        if (($payload['MarkCompleted'] ?? false) === true) {
            DB::table('N_LMS_Transaksi_Training_Detail')
                ->where('Id_Transaksi_Training_Detail', $transaksiDetail->Id_Transaksi_Training_Detail)
                ->update(['Flag_Selesai' => 'Y']);
        }

        $this->syncCompletionState(
            (int) $payload['Id_Penjadwalan'],
            (int) $payload['Id_Penjadwalan_Detail'],
            (int) $transaksi->Id_Transaksi_Training,
        );

        if (($payload['MarkCompleted'] ?? false) === true && ! empty($payload['Id_Materi'])) {
            $this->recalculateFinalScoreIfReady((int) $payload['Id_Penjadwalan'], (int) $payload['Id_Materi']);
        }

        return [
            'transaksi_training_id' => $transaksi->Id_Transaksi_Training,
            'transaksi_training_detail_id' => $transaksiDetail->Id_Transaksi_Training_Detail,
            'transaksi_training_det_id' => $transaksiDet->Id_Transaksi_Training_Det,
            'training_progress_id' => $progress->Id_Training_Progress,
        ];
    }

    private function withDeadlockRetry(callable $callback, int $attempts = 3)
    {
        $attempts = max(1, $attempts);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $callback();
            } catch (Throwable $exception) {
                if (! GalatDeadlock::adalah($exception) || $attempt >= $attempts) {
                    throw $exception;
                }

                usleep(100000 * $attempt);
            }
        }
    }

    private function ensureTransaksiTraining(int $penjadwalanId): object
    {
        $transaksi = DB::table('N_LMS_Transaksi_Training')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($transaksi) {
            return $transaksi;
        }

        $id = DB::table('N_LMS_Transaksi_Training')->insertGetId([
            'Id_Penjadwalan' => $penjadwalanId,
            'Id_User' => Auth::id(),
            'Total_Score' => 0,
            'Score_Learning_Gain' => 0,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Updated_At' => now(),
        ]);

        return DB::table('N_LMS_Transaksi_Training')->where('Id_Transaksi_Training', $id)->first();
    }

    private function ensureTransaksiTrainingDetail(
        int $transaksiTrainingId,
        int $penjadwalanDetailId,
        int $materiId,
        int $materiDetId,
        string $flagThirdParty = 'T',
    ): object {
        $existing = DB::table('N_LMS_Transaksi_Training_Detail')
            ->where('Id_Transaksi_Training', $transaksiTrainingId)
            ->where('Id_Penjadwalan_Detail', $penjadwalanDetailId)
            ->where('Id_Materi', $materiId)
            ->where('Id_Materi_Det', $materiDetId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $id = DB::table('N_LMS_Transaksi_Training_Detail')->insertGetId([
            'Id_Transaksi_Training' => $transaksiTrainingId,
            'Id_Penjadwalan_Detail' => $penjadwalanDetailId,
            'Id_Materi' => $materiId,
            'Id_Materi_Det' => $materiDetId,
            'Flag_Selesai' => 'T',
            'Flag_Third_Party' => $flagThirdParty,
        ]);

        return DB::table('N_LMS_Transaksi_Training_Detail')->where('Id_Transaksi_Training_Detail', $id)->first();
    }

    protected function ensureNeedsLearningGain(int $penjadwalanId): bool
    {
        $exists = DB::table('N_LMS_Penjadwalan')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->where('Flag_Learning_Gain', 'Y')
            ->exists();

        if (! $exists) {
            return false;
        }

        return true;
    }

    private function ensureTransaksiTrainingDet(int $transaksiTrainingDetailId): object
    {
        $existing = DB::table('N_LMS_Transaksi_Training_Det')
            ->where('Id_Transaksi_Training_Detail', $transaksiTrainingDetailId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $id = DB::table('N_LMS_Transaksi_Training_Det')->insertGetId([
            'Id_Transaksi_Training_Detail' => $transaksiTrainingDetailId,
            'Id_Fallback_Referensi' => null,
            'Skor_Akhir' => 0,
        ]);

        return DB::table('N_LMS_Transaksi_Training_Det')->where('Id_Transaksi_Training_Det', $id)->first();
    }

    private function ensureTrainingProgress(
        int $transaksiTrainingId,
        int $transaksiTrainingDetailId,
        int $urutan,
        string $statusProgress = 'PROGRESS',
    ): object {
        $statusProgress = strtoupper(trim($statusProgress));
        if (! in_array($statusProgress, ['MENUNGGU', 'PROGRESS', 'SELESAI'], true)) {
            $statusProgress = 'PROGRESS';
        }

        $existing = DB::table('N_LMS_Training_Progress')
            ->where('Id_Transaksi_Training', $transaksiTrainingId)
            ->where('Id_Transaksi_Training_Detail', $transaksiTrainingDetailId)
            ->first();

        if ($existing) {
            DB::table('N_LMS_Training_Progress')
                ->where('Id_Training_Progress', $existing->Id_Training_Progress)
                ->update([
                    'Status_Progress' => $statusProgress,
                    'Urutan' => $urutan,
                ]);

            return DB::table('N_LMS_Training_Progress')
                ->where('Id_Training_Progress', $existing->Id_Training_Progress)
                ->first();
        }

        $id = DB::table('N_LMS_Training_Progress')->insertGetId([
            'Id_Transaksi_Training' => $transaksiTrainingId,
            'Id_Transaksi_Training_Detail' => $transaksiTrainingDetailId,
            'Id_User' => Auth::id(),
            'Urutan' => $urutan,
            'Status_Progress' => $statusProgress,
        ]);

        return DB::table('N_LMS_Training_Progress')->where('Id_Training_Progress', $id)->first();
    }

    private function markPenjadwalanDetStarted(array $payload, Carbon $startedAt): void
    {
        if (empty($payload['Id_Penjadwalan_Det'])) {
            return;
        }

        DB::table('N_LMS_Penjadwalan_Det')
            ->where('Id_Penjadwalan_Det', $payload['Id_Penjadwalan_Det'])
            ->update([
                'Flag_Checkin' => 'Y',
                'Waktu_Mulai_Akses' => $startedAt,
            ]);
    }

    private function markPenjadwalanDetCompleted(array $payload, Carbon $completedAt): void
    {
        if (empty($payload['Id_Penjadwalan_Det'])) {
            return;
        }

        DB::table('N_LMS_Penjadwalan_Det')
            ->where('Id_Penjadwalan_Det', $payload['Id_Penjadwalan_Det'])
            ->update([
                'Flag_Checkin' => 'Y',
                'Flag_Selesai' => 'Y',
                'Waktu_Mulai_Akses' => DB::raw('COALESCE(Waktu_Mulai_Akses, GETDATE())'),
                'Waktu_Selesai' => $completedAt,
            ]);
    }

    private function syncCompletionState(int $penjadwalanId, int $penjadwalanDetailId, int $transaksiTrainingId): void
    {
        $remainingOnDetail = DB::table('N_LMS_Penjadwalan_Det')
            ->where('Id_Penjadwalan_Detail', $penjadwalanDetailId)
            ->where(function ($query) {
                $query->whereNull('Flag_Selesai')->orWhere('Flag_Selesai', '!=', 'Y');
            })
            ->exists();

        if (! $remainingOnDetail) {
            DB::table('N_LMS_Transaksi_Training_Detail')
                ->where('Id_Transaksi_Training', $transaksiTrainingId)
                ->where('Id_Penjadwalan_Detail', $penjadwalanDetailId)
                ->update(['Flag_Selesai' => 'Y']);
        }

        $remainingOnTraining = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where(function ($query) {
                $query->whereNull('pdet.Flag_Selesai')->orWhere('pdet.Flag_Selesai', '!=', 'Y');
            })
            ->exists();

        if (! $remainingOnTraining) {
            DB::table('N_LMS_Penjadwalan')
                ->where('Id_Penjadwalan', $penjadwalanId)
                ->update(['Flag_Selesai' => 'Y']);

            DB::table('N_LMS_Transaksi_Training')
                ->where('Id_Transaksi_Training', $transaksiTrainingId)
                ->update(['Updated_At' => now()]);
        }
    }

    private function recalculateFinalScoreIfReady(int $penjadwalanId, int $materiId): ?float
    {
        $updates = $this->computeFinalScoreUpdates($penjadwalanId, $materiId);

        if ($updates === null) {
            return null;
        }

        $learningGain = $updates['__learning_gain'] ?? null;
        unset($updates['__learning_gain']);

        DB::table('N_LMS_Transaksi_Training')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->where('Flag_Aktif', 'Y')
            ->update($updates);

        return $learningGain;
    }

    /**
     * Tulis ulang skor header N_LMS_Transaksi_Training untuk satu penjadwalan+materi
     * (dipakai oleh command backfill). Menghitung memakai logika yang sama dengan
     * runtime, lalu menulis kolom skor saja.
     *
     * @return array|null Daftar kolom yang (akan) ditulis, atau null bila tidak ada
     *                    yang perlu di-backfill. Pada $dryRun=true tidak menyentuh DB.
     */
    public function applyFinalScoreBackfill(int $penjadwalanId, int $materiId, bool $dryRun = false): ?array
    {
        // Backfill memberlakukan syarat ketat: komponen bernilai harus Flag_Selesai='Y'.
        $updates = $this->computeFinalScoreUpdates($penjadwalanId, $materiId, true);

        if ($updates === null) {
            return null;
        }

        unset($updates['__learning_gain']);

        if (! $dryRun) {
            DB::table('N_LMS_Transaksi_Training')
                ->where('Id_Penjadwalan', $penjadwalanId)
                ->where('Flag_Aktif', 'Y')
                ->update($updates);
        }

        return $updates;
    }

    /**
     * Hitung kolom-kolom header N_LMS_Transaksi_Training yang harus di-update
     * untuk sebuah penjadwalan+materi, TANPA menyentuh database.
     *
     * Mengembalikan array kolom => nilai (Total_Score, Id_Keterangan_Kelulusan,
     * Score_Learning_Gain jika perlu, Updated_At) bila semua komponen rumus sudah
     * siap, atau null bila belum siap / tidak ada yang perlu dihitung.
     *
     * Dipakai oleh recalculateFinalScoreIfReady (runtime) dan command backfill,
     * sehingga perilakunya selalu identik.
     */
    public function computeFinalScoreUpdates(
        int $penjadwalanId,
        int $materiId,
        bool $requireCompleted = false,
    ): ?array {
        if ($penjadwalanId <= 0 || $materiId <= 0) {
            return null;
        }

        $penjadwalan = DB::table('N_LMS_Penjadwalan')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->select('Id_Penjadwalan', 'Id_Master_Rumus')
            ->first();

        if (! $penjadwalan) {
            return null;
        }

        // Rumus BOLEH kosong. Ia milik Learning Gain saja; Total_Score tidak
        // pernah memakainya. Dulu ketiadaan rumus memulangkan null di sini dan
        // ikut membatalkan Total_Score — 283 penjadwalan tanpa rumus karena itu
        // tak pernah punya nilai akhir sekalipun post-test-nya sudah dinilai.
        $formula = $this->ambilRumus($penjadwalan->Id_Master_Rumus ?? null);

        $activityRows = $this->barisAktivitas($penjadwalanId, $materiId);

        if ($activityRows->isEmpty()) {
            return null;
        }

        $materi = DB::table('N_LMS_Materi')
            ->where('Id_Materi', $materiId)
            ->select('Id_Materi', 'Standar_Kelulusan')
            ->first();

        $totalScore = $this->resolveTotalScore($activityRows, $requireCompleted);
        $learningGainScore = $this->hitungLearningGain($formula, $activityRows, $requireCompleted);

        $standarKelulusan = $materi?->Standar_Kelulusan !== null ? (float) $materi->Standar_Kelulusan : null;
        $kelulusan = $this->resolveKelulusanBinding($totalScore, $standarKelulusan);

        $updates = [
            'Updated_At' => now(),
        ];

        if ($totalScore !== null) {
            $updates['Total_Score'] = $totalScore;
        }

        if ($kelulusan !== null && $kelulusan['id'] !== null) {
            $updates['Id_Keterangan_Kelulusan'] = (int) $kelulusan['id'];
        }

        // Learning Gain hanya disimpan bila penjadwalan memang menandai butuh
        // perhitungan tsb DAN rumusnya benar-benar menghasilkan angka. Rumus yang
        // tak bisa dihitung (mis. pre-test 0 pada ([PTT]-[PRT])/[PRT]*100) kini
        // hanya membuat kolom ini dilewati — bukan membuang Total_Score yang sah.
        if ($learningGainScore !== null && $this->ensureNeedsLearningGain($penjadwalanId)) {
            $updates['Score_Learning_Gain'] = $learningGainScore;
        }

        // Kalau tidak ada kolom skor yang berubah (hanya Updated_At), anggap tidak ada yang perlu di-update.
        if (
            ! array_key_exists('Total_Score', $updates) &&
            ! array_key_exists('Id_Keterangan_Kelulusan', $updates) &&
            ! array_key_exists('Score_Learning_Gain', $updates)
        ) {
            return null;
        }

        // Nilai bantu untuk pemanggil runtime; dibuang sebelum ditulis ke DB.
        $updates['__learning_gain'] = $learningGainScore;

        return $updates;
    }

    /** Rumus Learning Gain milik sebuah penjadwalan, atau null bila tak diatur. */
    protected function ambilRumus(?int $idMasterRumus): ?object
    {
        if (! $idMasterRumus) {
            return null;
        }

        return DB::table('N_LMS_Master_Rumus')->where('Id_Master_Rumus', $idMasterRumus)->first();
    }

    /**
     * Apakah penjadwalan ini punya pre-test / post-test, dan dari mana Total_Score
     * seharusnya diambil.
     *
     * Satu-satunya penjawab pertanyaan itu. Dua halaman menampilkan ringkasan hasil
     * training (detail peserta dan monitoring admin) dan keduanya dulu menebak
     * sendiri: yang satu memaku has_post_test menjadi true, yang lain menyimpulkannya
     * dari "Total_Score terisi". Tebakan kedua kini pasti salah, sebab training
     * ber-pre-test-saja juga punya Total_Score.
     *
     * @return array{ada_pre_test:bool, ada_post_test:bool, sumber_nilai:?string}
     */
    public function komposisiTes(int $penjadwalanId, ?int $materiId = null): array
    {
        $kode = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->when($materiId !== null && $materiId > 0, fn ($q) => $q->where('det.Id_Materi', $materiId))
            ->distinct()
            ->pluck('akt.Kode_Aktivitas')
            ->map(fn ($k) => $this->normalizeToken($k));

        $adaPost = $kode->intersect(['POST_TEST', 'POSTTEST', 'PTT'])->isNotEmpty();
        $adaPre = $kode->intersect(['PRE_TEST', 'PRETEST', 'PRT'])->isNotEmpty();

        return [
            'ada_pre_test' => $adaPre,
            'ada_post_test' => $adaPost,
            // Urutannya sama persis dengan resolveTotalScore(): post-test menang,
            // pre-test dipakai hanya bila post-test-nya memang tidak ada.
            'sumber_nilai' => $adaPost ? 'POST_TEST' : ($adaPre ? 'PRE_TEST' : null),
        ];
    }

    /**
     * Seluruh aktivitas satu penjadwalan+materi beserta skor dan status selesainya.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function barisAktivitas(int $penjadwalanId, int $materiId)
    {
        return DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->join('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
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
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where('det.Id_Materi', $materiId)
            ->select(['akt.Kode_Aktivitas', 'pdet.Flag_Selesai', 'trx_det.Skor_Akhir'])
            ->get();
    }

    /**
     * Learning Gain — dan HANYA Learning Gain — berasal dari rumus penjadwalan.
     *
     * Mengembalikan null bila rumusnya tidak ada, salah satu tokennya tidak punya
     * aktivitas, komponennya belum dinilai, atau ekspresinya tak bisa dihitung.
     * Ketiadaan Learning Gain tidak boleh menjatuhkan Total_Score.
     */
    protected function hitungLearningGain(?object $formula, $activityRows, bool $requireCompleted): ?float
    {
        if (! $formula) {
            return null;
        }

        preg_match_all('/\[(.*?)\]/', (string) $formula->Rumus, $matches);
        $tokens = collect($matches[1] ?? [])
            ->map(fn ($token) => strtoupper(trim((string) $token)))
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return null;
        }

        $components = $tokens
            ->map(function ($token) use ($activityRows, $requireCompleted) {
                $rows = $activityRows
                    ->filter(fn ($row) => strtoupper((string) $row->Kode_Aktivitas) === $token)
                    ->values();

                if ($rows->isEmpty()) {
                    return ['token' => $token, 'ready' => false, 'value' => null];
                }

                if (in_array($token, ['ABS', 'MDL'], true)) {
                    $total = $rows->count();
                    $completed = $rows->where('Flag_Selesai', 'Y')->count();

                    // Mode ketat (backfill): semua baris harus benar-benar selesai.
                    if ($requireCompleted && $completed !== $total) {
                        return ['token' => $token, 'ready' => false, 'value' => null];
                    }

                    return [
                        'token' => $token,
                        'ready' => $total > 0,
                        'value' => $total > 0 ? round($completed / $total, 4) : null,
                    ];
                }

                $nilai = $this->rataSkorAktivitas($rows, $requireCompleted);

                return [
                    'token' => $token,
                    'ready' => $nilai !== null,
                    'value' => $nilai,
                ];
            })
            ->values();

        if (! $components->every(fn ($component) => $component['ready'] === true)) {
            return null;
        }

        $resolvedFormula = (string) $formula->Rumus;
        foreach ($components as $component) {
            $resolvedFormula = str_replace(
                '['.$component['token'].']',
                (string) ($component['value'] ?? 0),
                $resolvedFormula,
            );
        }

        $nilai = PenilaiEkspresiNumerik::nilai($resolvedFormula);

        return $nilai === null ? null : round((float) $nilai, 2);
    }

    /**
     * Nilai akhir training.
     *
     * Aturannya ditetapkan pemilik proses: Total_Score adalah skor POST-TEST bila
     * training punya post-test, dan skor PRE-TEST bila post-test-nya memang tidak
     * ada. Learning Gain TIDAK pernah menjadi Total_Score — ia punya kolomnya
     * sendiri.
     *
     * Kalau post-test ada tetapi belum dinilai, hasilnya null (belum siap) dan
     * BUKAN jatuh ke pre-test: nilai akhir yang muncul lalu berubah sendiri lebih
     * menyesatkan daripada nilai yang belum muncul.
     */
    protected function resolveTotalScore($activityRows, bool $requireCompleted): ?float
    {
        foreach ([['POST_TEST', 'POSTTEST', 'PTT'], ['PRE_TEST', 'PRETEST', 'PRT']] as $kelompok) {
            $rows = collect($activityRows)
                ->filter(fn ($row) => in_array($this->normalizeToken($row->Kode_Aktivitas ?? null), $kelompok, true))
                ->values();

            if ($rows->isEmpty()) {
                continue;
            }

            return $this->rataSkorAktivitas($rows, $requireCompleted);
        }

        return null;
    }

    /**
     * Rata-rata Skor_Akhir sekumpulan baris aktivitas, atau null bila belum semua
     * baris punya skor (atau, pada mode ketat, belum semua Flag_Selesai='Y').
     */
    private function rataSkorAktivitas($rows, bool $requireCompleted): ?float
    {
        $rows = collect($rows);
        $total = $rows->count();

        if ($total <= 0) {
            return null;
        }

        // Mode ketat (backfill): aktivitas bernilai harus Flag_Selesai='Y', bukan
        // sekadar punya Skor_Akhir. Mencegah peserta yang belum mengerjakan ikut terhitung.
        if ($requireCompleted && $rows->where('Flag_Selesai', 'Y')->count() !== $total) {
            return null;
        }

        $skor = $rows->map(fn ($row) => $row->Skor_Akhir !== null ? (float) $row->Skor_Akhir : null);

        if ($skor->filter(fn ($n) => $n !== null)->count() !== $total) {
            return null;
        }

        return round($skor->sum() / $total, 2);
    }

    private function resolveKelulusanBinding(?float $totalScore, ?float $standarKelulusan): ?array
    {
        if ($totalScore === null || $standarKelulusan === null) {
            return null;
        }

        $isPassed = $totalScore >= $standarKelulusan;
        $code = $isPassed ? 1 : 2;
        $masterBinding = DB::table('N_LMS_Master_Keterangan_Kelulusan')
            ->where('Kode_Keterangan', $code)
            ->where(function ($query) {
                $query->whereNull('Flag_Aktif')->orWhere('Flag_Aktif', 'Y');
            })
            ->select('Id_Keterangan_Kelulusan', 'Kode_Keterangan', 'Nama_Keterangan')
            ->first();

        if (! $masterBinding) {
            $fallbackLabel = $isPassed ? 'lulus' : 'tidak lulus';
            $masterBinding = DB::table('N_LMS_Master_Keterangan_Kelulusan')
                ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Keterangan))) = ?', [$fallbackLabel])
                ->where(function ($query) {
                    $query->whereNull('Flag_Aktif')->orWhere('Flag_Aktif', 'Y');
                })
                ->select('Id_Keterangan_Kelulusan', 'Kode_Keterangan', 'Nama_Keterangan')
                ->first();
        }

        return [
            'id' => $masterBinding->Id_Keterangan_Kelulusan ?? null,
            'code' => $masterBinding->Kode_Keterangan ?? $code,
            'label' => $masterBinding->Nama_Keterangan ?? ($isPassed ? 'LULUS' : 'TIDAK LULUS'),
        ];
    }

    public function normalizeToken($token): string
    {
        $raw = strtoupper(trim((string) $token));

        return preg_replace('/[^A-Z0-9]+/', '_', $raw) ?: '';
    }
}
