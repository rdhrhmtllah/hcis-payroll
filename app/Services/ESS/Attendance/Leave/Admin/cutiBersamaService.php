<?php

namespace App\Services\ESS\Attendance\Leave\Admin;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class cutiBersamaService
{
    public function getDataCutiByKode_Karyawan(string|array $kodeKaryawan): array
    {
        $requestedKodes = $this->normalizeKodeKaryawanInput($kodeKaryawan);

        if (empty($requestedKodes)) {
            return [
                'requested_kode_karyawan' => [],
                'total_requested' => 0,
                'total_found_karyawan' => 0,
                'data' => [],
            ];
        }

        $profiles = $this->getKaryawanProfiles($requestedKodes);
        $resultByKode = $this->buildEmptyResult($requestedKodes, $profiles);

        $candidates = $this->getEffectiveCutiBersamaCandidates($requestedKodes);

        if ($candidates->isEmpty()) {
            return $this->formatResult($requestedKodes, $profiles, $resultByKode);
        }

        $cutiBersamaIds = $candidates->pluck('Id_Cuti_Bersama')->filter()->values()->all();
        $latestTasks = $this->getLatestExecutionTasksByCutiBersamaIds($cutiBersamaIds);
        $details = $this->getActiveDetailsByCutiBersamaAndKode($cutiBersamaIds, $requestedKodes);
        $exclusions = $this->getExclusionsByCutiBersama($cutiBersamaIds, $requestedKodes);

        foreach ($candidates as $cutiBersama) {
            $idCutiBersama = (int) $cutiBersama->Id_Cuti_Bersama;
            $latestTask = $latestTasks[$idCutiBersama] ?? null;
            $selectedDates = $this->extractSelectedDatesFromRecord($cutiBersama);

            foreach ($requestedKodes as $kode) {
                $detailKey = $this->buildCutiKaryawanKey($idCutiBersama, $kode);
                $detail = $details[$detailKey] ?? null;

                if ($detail) {
                    $this->appendCutiBersamaItem(
                        $resultByKode[$kode],
                        $this->buildCutiBersamaItem($cutiBersama, $selectedDates, 'executed', $latestTask, $detail),
                    );

                    continue;
                }

                $taskStatus = strtoupper(trim((string) ($latestTask->Status ?? '')));
                if (! in_array($taskStatus, ['DISPATCHED', 'PROCESSING'], true)) {
                    continue;
                }

                if (isset($exclusions[$idCutiBersama][$kode])) {
                    continue;
                }

                $profile = $profiles[$kode] ?? null;
                if (! $this->isKaryawanTargetedForQueuedCutiBersama($cutiBersama, $profile, $kode)) {
                    continue;
                }

                $this->appendCutiBersamaItem(
                    $resultByKode[$kode],
                    $this->buildCutiBersamaItem(
                        $cutiBersama,
                        $selectedDates,
                        $this->resolveQueuedStatus($latestTask),
                        $latestTask,
                        null,
                    ),
                );
            }
        }

        return $this->formatResult($requestedKodes, $profiles, $resultByKode);
    }

    private function normalizeKodeKaryawanInput(string|array $kodeKaryawan): array
    {
        $values = is_array($kodeKaryawan) ? $kodeKaryawan : [$kodeKaryawan];

        return collect($values)
            ->filter(fn ($kode) => is_scalar($kode))
            ->map(fn ($kode) => trim((string) $kode))
            ->filter(fn ($kode) => $kode !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function getKaryawanProfiles(array $requestedKodes)
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->whereIn('k.Kode_Karyawan', $requestedKodes)
            ->select(
                'k.Kode_Karyawan',
                'k.Nama',
                'k.Cabang_Untuk_Gaji',
                'k.Aktif',
                'k.Tanggal_Resign',
                'vd.ID_Divisi',
                'vd.ID_Sub_Divisi',
                'vd.nama_divisi',
                'vd.nama_sub_divisi',
            )
            ->get()
            ->keyBy('Kode_Karyawan');
    }

    private function buildEmptyResult(array $requestedKodes, $profiles): array
    {
        $result = [];

        foreach ($requestedKodes as $kode) {
            $profile = $profiles[$kode] ?? null;
            $result[$kode] = [
                'kode_karyawan' => $kode,
                'nama_karyawan' => $profile->Nama ?? null,
                'found' => $profile !== null,
                'total_cuti_bersama' => 0,
                'dates' => [],
                'items' => [],
            ];
        }

        return $result;
    }

    private function getEffectiveCutiBersamaCandidates(array $requestedKodes)
    {
        $query = DB::table('N_HRIS_Cuti_Bersama')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) use ($requestedKodes) {
                $query
                    ->where('Flag_Executed', 'Y')
                    ->orWhereExists(function ($subQuery) use ($requestedKodes) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('N_HRIS_Cuti_Bersama_Detail as cbd')
                            ->whereColumn('cbd.Id_Cuti_Bersama', 'N_HRIS_Cuti_Bersama.Id_Cuti_Bersama')
                            ->whereIn('cbd.Kode_Karyawan', $requestedKodes)
                            ->whereIn(DB::raw("UPPER(LTRIM(RTRIM(ISNULL(cbd.Status, ''))))"), ['SUCCESS', 'COMPLETED']);
                    })
                    ->orWhereExists(function ($subQuery) {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('N_HRIS_Cloud_Task_Log as ctl')
                            ->whereColumn('ctl.Reference_Id', 'N_HRIS_Cuti_Bersama.Id_Cuti_Bersama')
                            ->where('ctl.Task_Type', 'CUTI_BERSAMA_EXECUTE')
                            ->whereIn('ctl.Status', ['DISPATCHED', 'PROCESSING']);
                    });
            })
            ->orderBy('Tanggal_Mulai')
            ->orderBy('Id_Cuti_Bersama');

        $selects = [
            'Id_Cuti_Bersama',
            'Nama_Cuti_Bersama',
            'Tanggal_Mulai',
            'Tanggal_Selesai',
            'Jumlah_Hari',
            'Potong_Ke_Tipe_Cuti',
            'Flag_Executed',
            'Executed_At',
            'Filter_Cabang',
            'Filter_Divisi',
            'Filter_Sub_Divisi',
            'Filter_Kode_Karyawan',
        ];

        if (SkemaTersedia::kolom('N_HRIS_Cuti_Bersama', 'Selected_Dates')) {
            $selects[] = 'Selected_Dates';
        }

        return $query->select($selects)->get();
    }

    private function getLatestExecutionTasksByCutiBersamaIds(array $cutiBersamaIds): array
    {
        if (empty($cutiBersamaIds)) {
            return [];
        }

        $tasks = DB::table('N_HRIS_Cloud_Task_Log')
            ->whereIn('Reference_Id', $cutiBersamaIds)
            ->where('Task_Type', 'CUTI_BERSAMA_EXECUTE')
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Log')
            ->get();

        $latest = [];
        foreach ($tasks as $task) {
            $id = (int) $task->Reference_Id;
            if (! isset($latest[$id])) {
                $latest[$id] = $task;
            }
        }

        return $latest;
    }

    private function getActiveDetailsByCutiBersamaAndKode(array $cutiBersamaIds, array $requestedKodes): array
    {
        if (empty($cutiBersamaIds) || empty($requestedKodes)) {
            return [];
        }

        $details = DB::table('N_HRIS_Cuti_Bersama_Detail')
            ->whereIn('Id_Cuti_Bersama', $cutiBersamaIds)
            ->whereIn('Kode_Karyawan', $requestedKodes)
            ->whereIn(DB::raw("UPPER(LTRIM(RTRIM(ISNULL(Status, ''))))"), ['SUCCESS', 'COMPLETED'])
            ->orderByDesc('Created_At')
            ->orderByDesc('Id_Detail')
            ->get();

        $keyed = [];
        foreach ($details as $detail) {
            $key = $this->buildCutiKaryawanKey((int) $detail->Id_Cuti_Bersama, (string) $detail->Kode_Karyawan);
            if (! isset($keyed[$key])) {
                $keyed[$key] = $detail;
            }
        }

        return $keyed;
    }

    private function getExclusionsByCutiBersama(array $cutiBersamaIds, array $requestedKodes): array
    {
        if (empty($cutiBersamaIds) || empty($requestedKodes)) {
            return [];
        }

        $rows = DB::table('N_HRIS_Cuti_Bersama_Exclusion')
            ->whereIn('Id_Cuti_Bersama', $cutiBersamaIds)
            ->whereIn('Kode_Karyawan', $requestedKodes)
            ->select('Id_Cuti_Bersama', 'Kode_Karyawan')
            ->get();

        $exclusions = [];
        foreach ($rows as $row) {
            $exclusions[(int) $row->Id_Cuti_Bersama][(string) $row->Kode_Karyawan] = true;
        }

        return $exclusions;
    }

    private function isKaryawanTargetedForQueuedCutiBersama(object $cutiBersama, ?object $profile, string $kode): bool
    {
        if (! $profile || ($profile->Aktif ?? null) !== 'Y' || ! empty($profile->Tanggal_Resign)) {
            return false;
        }

        $filterKode = $this->decodeArrayValue($cutiBersama->Filter_Kode_Karyawan ?? null);
        $filterCabang = $this->decodeArrayValue($cutiBersama->Filter_Cabang ?? null);
        $filterDivisi = $this->decodeArrayValue($cutiBersama->Filter_Divisi ?? null);
        $filterSubDivisi = $this->decodeArrayValue($cutiBersama->Filter_Sub_Divisi ?? null);

        $hasKodeFilter = ! empty($filterKode);
        $hasUnitFilter = ! empty($filterCabang) || ! empty($filterDivisi) || ! empty($filterSubDivisi);

        $inKode = in_array($kode, $filterKode, true);
        $inCabang = empty($filterCabang) || in_array((string) ($profile->Cabang_Untuk_Gaji ?? ''), $filterCabang, true);
        $inDivisi = empty($filterDivisi) || in_array((string) ($profile->ID_Divisi ?? ''), $filterDivisi, true);
        $inSubDivisi =
            empty($filterSubDivisi) || in_array((string) ($profile->ID_Sub_Divisi ?? ''), $filterSubDivisi, true);

        $inUnit = $hasUnitFilter && $inCabang && $inDivisi && $inSubDivisi;

        return $inKode || $inUnit || (! $hasKodeFilter && ! $hasUnitFilter);
    }

    private function decodeArrayValue($value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->filter(fn ($item) => $item !== null && trim((string) $item) !== '')
                ->map(fn ($item) => trim((string) $item))
                ->values()
                ->all();
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($item) => $item !== null && trim((string) $item) !== '')
            ->map(fn ($item) => trim((string) $item))
            ->values()
            ->all();
    }

    private function extractSelectedDatesFromRecord(object $record): array
    {
        if (! empty($record->Selected_Dates)) {
            $selectedDates = $this->decodeArrayValue($record->Selected_Dates);
            if (! empty($selectedDates)) {
                return $this->normalizeDates($selectedDates);
            }
        }

        return $this->buildDateRange($record->Tanggal_Mulai ?? null, $record->Tanggal_Selesai ?? null);
    }

    private function normalizeDates(array $dates): array
    {
        return collect($dates)
            ->map(function ($date) {
                try {
                    return Carbon::parse($date)->toDateString();
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function buildDateRange(?string $tanggalMulai, ?string $tanggalSelesai): array
    {
        if (! $tanggalMulai) {
            return [];
        }

        try {
            $start = Carbon::parse($tanggalMulai)->startOfDay();
            $end = $tanggalSelesai ? Carbon::parse($tanggalSelesai)->startOfDay() : $start->copy();
        } catch (\Throwable $e) {
            return [];
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->toDateString();
        }

        return $dates;
    }

    private function resolveQueuedStatus(?object $task): string
    {
        $taskStatus = strtoupper(trim((string) ($task->Status ?? '')));

        if ($taskStatus === 'PROCESSING') {
            return 'processing';
        }

        return ! empty($task->Scheduled_At) ? 'scheduled' : 'queued';
    }

    private function buildCutiBersamaItem(
        object $cutiBersama,
        array $selectedDates,
        string $status,
        ?object $latestTask,
        ?object $detail,
    ): array {
        return [
            'id_cuti_bersama' => (int) $cutiBersama->Id_Cuti_Bersama,
            'nama_cuti_bersama' => $cutiBersama->Nama_Cuti_Bersama,
            'status' => $status,
            'tanggal_mulai' => $this->formatDateValue($cutiBersama->Tanggal_Mulai ?? null),
            'tanggal_selesai' => $this->formatDateValue($cutiBersama->Tanggal_Selesai ?? null),
            'selected_dates' => $selectedDates,
            'jumlah_hari' => (float) ($cutiBersama->Jumlah_Hari ?? count($selectedDates)),
            'potong_ke_tipe_cuti' => $cutiBersama->Potong_Ke_Tipe_Cuti,
            'execution' => [
                'flag_executed' => $cutiBersama->Flag_Executed ?? null,
                'task_status' => $latestTask->Status ?? null,
                'scheduled_at' => $latestTask->Scheduled_At ?? null,
                'executed_at' => $cutiBersama->Executed_At ?? ($latestTask->Completed_At ?? null),
            ],
            'detail' => $detail
                ? [
                    'id_detail' => (int) $detail->Id_Detail,
                    'status' => $detail->Status,
                    'jumlah_hari_dipotong' => (float) ($detail->Jumlah_Hari_Dipotong ?? 0),
                    'saldo_sebelum' => isset($detail->Saldo_Sebelum) ? (float) $detail->Saldo_Sebelum : null,
                    'saldo_sesudah' => isset($detail->Saldo_Sesudah) ? (float) $detail->Saldo_Sesudah : null,
                ]
                : null,
        ];
    }

    private function appendCutiBersamaItem(array &$karyawanResult, array $item): void
    {
        $karyawanResult['items'][] = $item;
        $karyawanResult['dates'] = array_values(
            array_unique(array_merge($karyawanResult['dates'], $item['selected_dates'])),
        );
        sort($karyawanResult['dates']);
        $karyawanResult['total_cuti_bersama'] = count($karyawanResult['items']);
    }

    private function formatResult(array $requestedKodes, $profiles, array $resultByKode): array
    {
        return [
            'requested_kode_karyawan' => $requestedKodes,
            'total_requested' => count($requestedKodes),
            'total_found_karyawan' => $profiles->count(),
            'data' => array_values($resultByKode),
        ];
    }

    private function buildCutiKaryawanKey(int $idCutiBersama, string $kodeKaryawan): string
    {
        return $idCutiBersama.'|'.$kodeKaryawan;
    }

    private function formatDateValue($date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return (string) $date;
        }
    }
}
