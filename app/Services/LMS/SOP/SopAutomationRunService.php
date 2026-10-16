<?php

namespace App\Services\LMS\SOP;

use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class SopAutomationRunService
{
    public function queue(
        string $type,
        string $trigger,
        ?int $ruleId = null,
        ?string $configHash = null,
        ?int $requestedBy = null,
        array $payload = [],
    ): int {
        return DB::table('N_SOP_Automation_Run')->insertGetId([
            'Id_SOP_Assignment_Rule' => $ruleId,
            'Tipe_Run' => $type,
            'Trigger_Run' => $trigger,
            'Status_Run' => 'QUEUED',
            'Config_Hash' => $configHash,
            'Hasil_JSON' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'Requested_By' => $requestedBy,
            'Queued_At' => now(),
            'Created_At' => now(),
        ], 'Id_SOP_Automation_Run');
    }

    /**
     * Baris anak: hasil SATU paket di dalam satu eksekusi.
     *
     * Penjaga konkurensi TIDAK dipindahkan ke sini. claim(), hasActive(),
     * hasActiveForRule(), reapStale(), dan hook failed() tetap bekerja pada
     * baris INDUK — satu baris, persis seperti sebelum multi-paket. Baris anak
     * murni catatan hasil, sehingga tak ada pengaman lama yang perlu diubah
     * bentuknya.
     */
    public function queueAnak(
        int $indukId,
        int $idPaket,
        string $type,
        string $trigger,
        ?int $ruleId = null,
        ?string $configHash = null,
        ?int $requestedBy = null,
    ): int {
        return DB::table('N_SOP_Automation_Run')->insertGetId([
            'Id_Run_Induk' => $indukId,
            'Id_SOP_Paket' => $idPaket,
            'Id_SOP_Assignment_Rule' => $ruleId,
            'Tipe_Run' => $type,
            'Trigger_Run' => $trigger,
            'Status_Run' => 'RUNNING',
            'Config_Hash' => $configHash,
            'Requested_By' => $requestedBy,
            'Queued_At' => now(),
            'Started_At' => now(),
            'Created_At' => now(),
        ], 'Id_SOP_Automation_Run');
    }

    public function hasActive(string $type, ?int $ruleId = null): bool
    {
        return DB::table('N_SOP_Automation_Run')
            // Hanya baris induk. Baris anak berumur pendek dan tak pernah jadi
            // dasar penjaga konkurensi; ikut menghitungnya membuat otomasi
            // tampak "masih berjalan" setelah induknya selesai.
            ->whereNull('Id_Run_Induk')
            ->where('Tipe_Run', $type)
            ->when($ruleId, fn ($q) => $q->where('Id_SOP_Assignment_Rule', $ruleId))
            ->when(! $ruleId, fn ($q) => $q->whereNull('Id_SOP_Assignment_Rule'))
            ->whereIn('Status_Run', ['QUEUED', 'RUNNING'])
            ->exists();
    }

    public function hasActiveForRule(int $ruleId): bool
    {
        return DB::table('N_SOP_Automation_Run')
            ->whereNull('Id_Run_Induk')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->whereIn('Status_Run', ['QUEUED', 'RUNNING'])
            ->exists();
    }

    /**
     * Ambil alih run untuk dikerjakan.
     *
     * RUNNING dan FAILED ikut diterima, bukan hanya QUEUED. Tanpa itu, worker
     * yang mati setelah menulis RUNNING membuat run tersangkut selamanya: retry
     * job gagal claim lalu berhenti diam-diam, hasActive()/hasActiveForRule()
     * bernilai true permanen, sehingga aturan tak bisa di-apply lagi, tombol
     * "Jalankan sekarang" balas 409 terus, dan scan terjadwal melewatinya tiap
     * hari — tanpa satu pun gejala di kartu ringkasan.
     *
     * Pola ini menyalin SopBulkAssignTaskService::klaim() yang memang sudah
     * bisa pulih sendiri; run yang sudah SUCCESS/SKIPPED tetap tidak diambil.
     */
    public function claim(int $runId): bool
    {
        return DB::table('N_SOP_Automation_Run')
            ->where('Id_SOP_Automation_Run', $runId)
            ->whereIn('Status_Run', ['QUEUED', 'RUNNING', 'FAILED'])
            ->update(['Status_Run' => 'RUNNING', 'Started_At' => now()]) > 0;
    }

    /**
     * Tandai gagal semua run yang menggantung melebihi batas waktu.
     *
     * Jaring pengaman terakhir untuk proses yang mati tanpa sempat menulis
     * status akhir (worker di-restart, deploy, OOM). Tanpa ini satu run
     * menggantung cukup untuk membekukan otomasi paketnya selamanya.
     *
     * Ambang default 2 jam = dua kali $timeout job (3600 dtk), jadi run yang
     * masih berjalan wajar tidak ikut disapu.
     *
     * @return int jumlah run yang disapu
     */
    public function reapStale(int $timeoutMinutes = 120): int
    {
        $batas = now()->subMinutes(max(1, $timeoutMinutes));

        return DB::table('N_SOP_Automation_Run')
            ->whereNull('Id_Run_Induk')
            ->whereIn('Status_Run', ['QUEUED', 'RUNNING'])
            // Queued_At selalu terisi; Started_At baru ada setelah claim().
            ->where(DB::raw('COALESCE(Started_At, Queued_At)'), '<', $batas)
            ->update([
                'Status_Run' => 'FAILED',
                'Error_Message' => 'Run dihentikan otomatis: melebihi batas waktu '.$timeoutMinutes.' menit tanpa penyelesaian.',
                'Completed_At' => now(),
            ]);
    }

    public function success(int $runId, array $result = []): void
    {
        DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $runId)->update([
            'Status_Run' => 'SUCCESS',
            'Total_Ditemukan' => (int) ($result['matched'] ?? $result['found'] ?? 0),
            'Total_Ditambahkan' => (int) ($result['assigned'] ?? 0),
            'Total_Dipertahankan' => (int) ($result['kept'] ?? 0),
            'Total_Dicabut' => (int) ($result['released'] ?? 0),
            'Total_Dilewati' => (int) ($result['skipped'] ?? $result['withheld'] ?? 0),
            'Hasil_JSON' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Error_Message' => null,
            'Completed_At' => now(),
        ]);
    }

    public function skip(int $runId, string $message, array $result = []): void
    {
        DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $runId)->update([
            'Status_Run' => 'SKIPPED',
            'Hasil_JSON' => $result ? json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'Error_Message' => mb_substr($message, 0, 2000),
            'Completed_At' => now(),
        ]);
    }

    public function fail(int $runId, string $message): void
    {
        DB::table('N_SOP_Automation_Run')->where('Id_SOP_Automation_Run', $runId)->update([
            'Status_Run' => 'FAILED',
            'Error_Message' => mb_substr($message, 0, 2000),
            'Completed_At' => now(),
        ]);
    }

    public function payload(int $runId): array
    {
        $json = DB::table('N_SOP_Automation_Run')
            ->where('Id_SOP_Automation_Run', $runId)
            ->value('Hasil_JSON');

        return json_decode($json ?: '{}', true) ?: [];
    }

    /**
     * Riwayat: baris induk terbaru berikut seluruh baris anaknya.
     *
     * Plafon dihitung atas baris INDUK; baris anak ikut serta tanpa memakan
     * jatah. Kalau tidak, satu aktivasi berisi lima paket akan memakan
     * sepersepuluh jendela riwayat sendirian, dan jejak audit hari-hari
     * sebelumnya hilang jauh lebih cepat dari yang siapa pun sadari.
     *
     * Nilai baliknya menyertakan total sebenarnya supaya layar bisa menyatakan
     * pemotongannya. Konvensi repo ini: setiap pembatasan wajib dilaporkan.
     *
     * @return array{rows:array,ditampilkan:int,total_induk:int}
     */
    public function history(int $limit = 50): array
    {
        $limit = min(100, max(1, $limit));

        $indukIds = DB::table('N_SOP_Automation_Run')
            ->whereNull('Id_Run_Induk')
            ->orderByDesc('Id_SOP_Automation_Run')
            ->limit($limit)
            ->pluck('Id_SOP_Automation_Run');

        $totalInduk = (int) DB::table('N_SOP_Automation_Run')->whereNull('Id_Run_Induk')->count();

        if ($indukIds->isEmpty()) {
            return ['rows' => [], 'ditampilkan' => 0, 'total_induk' => $totalInduk];
        }

        $rows = DB::table('N_SOP_Automation_Run as r')
            ->leftJoin('N_SOP_Assignment_Rule as ar', 'ar.Id_SOP_Assignment_Rule', '=', 'r.Id_SOP_Assignment_Rule')
            // Nama paket diambil dari kolom run LANGSUNG. Join lewat aturan
            // putus begitu satu aturan tak lagi menunjuk satu paket.
            ->leftJoin('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'r.Id_SOP_Paket')
            ->where(fn ($q) => $q->whereIn('r.Id_SOP_Automation_Run', $indukIds)
                ->orWhereIn('r.Id_Run_Induk', $indukIds))
            // Anak menempel tepat di bawah induknya: kelompokkan menurut id
            // induk, lalu induk lebih dulu karena idnya selalu terkecil.
            ->orderByDesc(DB::raw('COALESCE(r.Id_Run_Induk, r.Id_SOP_Automation_Run)'))
            ->orderBy('r.Id_SOP_Automation_Run')
            ->get([
                'r.Id_SOP_Automation_Run', 'r.Id_Run_Induk', 'r.Id_SOP_Assignment_Rule', 'r.Id_SOP_Paket',
                'r.Tipe_Run', 'r.Trigger_Run', 'r.Status_Run', 'r.Total_Ditemukan', 'r.Total_Ditambahkan',
                'r.Total_Dipertahankan', 'r.Total_Dicabut', 'r.Total_Dilewati', 'r.Error_Message',
                'r.Queued_At', 'r.Started_At', 'r.Completed_At', 'p.Nama_Paket', 'ar.Nama_Aturan',
            ])->map(function ($row) {
                $row->Is_Anak = $row->Id_Run_Induk !== null;
                $row->Id_SOP_Automation_Run = Hashids::connection('custom')->encode($row->Id_SOP_Automation_Run);
                $row->Id_Run_Induk = $row->Id_Run_Induk
                    ? Hashids::connection('custom')->encode($row->Id_Run_Induk) : null;
                $row->Id_SOP_Assignment_Rule = $row->Id_SOP_Assignment_Rule
                    ? Hashids::connection('custom')->encode($row->Id_SOP_Assignment_Rule) : null;
                $row->Id_SOP_Paket = $row->Id_SOP_Paket
                    ? Hashids::connection('custom')->encode($row->Id_SOP_Paket) : null;

                return $row;
            })->all();

        return ['rows' => $rows, 'ditampilkan' => $indukIds->count(), 'total_induk' => $totalInduk];
    }

    /** Hashid satu id run — dipakai uji untuk mencocokkan baris hasil. */
    public function encodeUji(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }
}
