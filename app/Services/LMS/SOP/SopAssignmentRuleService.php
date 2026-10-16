<?php

namespace App\Services\LMS\SOP;

use App\Jobs\LMS\SOP\Automation\ApplySopAssignmentRule;
use App\Services\LMS\SOP\Support\SopAssignmentRuleConfig;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopSetting;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

class SopAssignmentRuleService
{
    /** Sama dengan ProsesBulkAssignSop::CHUNK — batas aman satu transaksi assign. */
    private const CHUNK_ASSIGN = 100;

    public function __construct(
        private SopPenjadwalanTargetService $targets,
        private SopPenjadwalanService $schedules,
        private SopAutomationRunService $runs,
        private SopAturanPaketService $paket,
    ) {}

    public function list(): array
    {
        $rows = DB::table('N_SOP_Assignment_Rule as r')
            ->where('r.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('r.Nama_Aturan')
            ->get([
                'r.Id_SOP_Assignment_Rule', 'r.Nama_Aturan', 'r.Status_Aturan',
                'r.Draft_Hash', 'r.Active_Hash', 'r.Last_Apply_Status', 'r.Last_Apply_Error',
                'r.Last_Applied_At', 'r.Last_Scanned_At', 'r.Updated_At',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        // Satu kueri untuk seluruh aturan, bukan satu per aturan: daftar ini
        // dimuat setiap kali halaman dibuka.
        $paket = DB::table('N_SOP_Assignment_Rule_Paket as rp')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'rp.Id_SOP_Paket')
            ->whereIn('rp.Id_SOP_Assignment_Rule', $rows->pluck('Id_SOP_Assignment_Rule'))
            ->where('rp.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('rp.Urutan')
            ->orderBy('rp.Id_SOP_Assignment_Rule_Paket')
            ->get([
                'rp.Id_SOP_Assignment_Rule', 'rp.Id_SOP_Paket', 'rp.Last_Apply_Status as Paket_Apply_Status',
                'p.Nama_Paket', 'p.Mode_Distribusi',
            ])
            ->groupBy('Id_SOP_Assignment_Rule');

        return $rows->map(function ($row) use ($paket) {
            $milik = $paket->get($row->Id_SOP_Assignment_Rule, collect());
            $row->Jumlah_Paket = $milik->count();
            $row->Paket = $milik->map(fn ($p) => [
                'Id_SOP_Paket' => Hashids::connection('custom')->encode($p->Id_SOP_Paket),
                'Nama_Paket' => $p->Nama_Paket,
                'Mode_Distribusi' => $p->Mode_Distribusi ?: 'PUBLIK',
                'Last_Apply_Status' => $p->Paket_Apply_Status,
            ])->values()->all();

            return $this->mapRow($row);
        })->all();
    }

    public function find(int $ruleId): ?array
    {
        $row = DB::table('N_SOP_Assignment_Rule')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();

        if (! $row) {
            return null;
        }

        $paket = $this->paket->daftar($ruleId);
        $row->Jumlah_Paket = count($paket);
        $row->Paket = array_map(fn ($p) => [
            'Id_SOP_Paket' => Hashids::connection('custom')->encode($p->Id_SOP_Paket),
            'Nama_Paket' => $p->Nama_Paket,
            'Mode_Distribusi' => $p->Mode_Distribusi ?: 'PUBLIK',
            'Urutan' => (int) $p->Urutan,
            'override' => $p->Flag_Override_Jadwal === 'Y',
            'schedule' => $p->Flag_Override_Jadwal === 'Y' ? [
                'deadline_mode' => $p->Deadline_Mode,
                'deadline_days' => $p->Deadline_Hari !== null ? (int) $p->Deadline_Hari : null,
                'deadline_date' => $p->Tanggal_Deadline,
                'reminder_enabled' => $p->Flag_Reminder === 'Y',
                'reminder_interval_days' => $p->Irama_Reminder_Hari !== null ? (int) $p->Irama_Reminder_Hari : null,
            ] : null,
            'Last_Apply_Status' => $p->Last_Apply_Status,
            'Last_Apply_Error' => $p->Last_Apply_Error,
        ], $paket);

        $mapped = (array) $this->mapRow($row);
        $mapped['config'] = json_decode($row->Draft_Config_JSON, true) ?: [];

        return $mapped;
    }

    /**
     * Simpan draft aturan berikut daftar paketnya.
     *
     * Parameter $idPaket tunggal yang dulu ada di posisi pertama sudah dibuang:
     * sejak DDL-2026-053 relasi aturan ke paket tinggal di tabel jembatan, dan
     * satu aturan boleh menampung banyak paket.
     *
     * @param  array  $paket  [['id_paket'=>int,'urutan'=>int,'override'=>bool,'schedule'=>?array], …]
     */
    public function saveDraft(string $name, array $input, array $paket, ?int $userId, ?int $ruleId = null): int
    {
        $config = SopAssignmentRuleConfig::normalize($input);
        $json = SopAssignmentRuleConfig::encode($config);
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['nama_aturan' => 'Nama aturan wajib diisi.']);
        }
        if (! $paket) {
            throw ValidationException::withMessages(['paket' => 'Aturan harus memuat minimal satu paket dokumen.']);
        }

        return DB::transaction(function () use ($ruleId, $name, $json, $config, $paket, $userId) {
            $baru = ! $ruleId;

            if ($ruleId) {
                $ada = DB::table('N_SOP_Assignment_Rule')
                    ->where('Id_SOP_Assignment_Rule', $ruleId)
                    ->where('Flag_Aktif', SopStatus::AKTIF)
                    ->exists();
                if (! $ada) {
                    throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
                }
            } else {
                $ruleId = (int) DB::table('N_SOP_Assignment_Rule')->insertGetId([
                    'Nama_Aturan' => mb_substr($name, 0, 150),
                    'Status_Aturan' => 'DRAFT',
                    'Draft_Config_JSON' => $json,
                    'Draft_Hash' => SopAssignmentRuleConfig::hash($config),
                    'Last_Apply_Status' => 'IDLE',
                    'Created_By' => $userId,
                    'Created_At' => now(),
                    'Flag_Aktif' => SopStatus::AKTIF,
                ], 'Id_SOP_Assignment_Rule');
            }

            // Daftar paket disinkronkan LEBIH DULU: hash draft harus dihitung
            // dari daftar yang sudah tersimpan, kalau tidak ia mengukur keadaan
            // yang sudah basi dan lencana "draft berubah" ikut salah.
            $this->paket->sinkron($ruleId, $paket, $userId);

            $hash = SopAssignmentRuleConfig::hash(
                $config,
                SopAssignmentRuleConfig::snapshotPaket($this->paket->barisMentah($ruleId)),
            );

            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Nama_Aturan' => mb_substr($name, 0, 150),
                'Draft_Config_JSON' => $json,
                'Draft_Hash' => $hash,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            SopLog::catat(
                'KEBIJAKAN_DRAFT',
                $baru ? 'Draft aturan kebijakan dokumen dibuat.' : 'Draft aturan kebijakan dokumen diperbarui.',
                null, null, null, null,
                [
                    'id_assignment_rule' => $ruleId,
                    'nama_aturan' => mb_substr($name, 0, 150),
                    'jumlah_paket' => count($paket),
                    'draft_hash' => $hash,
                    'oleh_id_users' => $userId,
                ],
            );

            return $ruleId;
        });
    }

    /**
     * Dampak aturan, dirinci per paket berikut baris TOTAL.
     *
     * Bentuk nilai baliknya berubah sejak multi-paket: dulu satu himpunan angka
     * karena satu aturan hanya punya satu paket.
     *
     * @return array{paket:array,total:array,auto_release:bool}
     */
    public function preview(int $ruleId, ?array $override = null): array
    {
        $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
        }
        $config = $override
            ? SopAssignmentRuleConfig::normalize($override)
            : SopAssignmentRuleConfig::normalize(json_decode($rule->Draft_Config_JSON, true) ?: []);

        $baris = [];
        $total = ['matched' => 0, 'new' => 0, 'kept' => 0, 'covered' => 0, 'release' => 0, 'withheld' => 0, 'excluded' => 0];

        // Sekali untuk seluruh paket: cohort tidak bergantung paket.
        $cohort = $this->resolveCohort($config);

        foreach ($this->paket->daftar($ruleId) as $paket) {
            $jadwal = $this->paket->jadwalEfektif($paket, $config['schedule']);
            $dampak = $this->impact($ruleId, (int) $paket->Id_SOP_Paket, $cohort, false);
            $dampak['Nama_Paket'] = $paket->Nama_Paket;
            $dampak['Id_SOP_Paket'] = Hashids::connection('custom')->encode($paket->Id_SOP_Paket);
            // Paket yang tertahan gerbang tenggat absolut harus terlihat SEBELUM
            // admin menekan Aktifkan, bukan ditemukan sesudahnya di riwayat.
            $dampak['tertahan_tenggat'] = $jadwal['deadline_mode'] === 'ABSOLUTE'
                && Carbon::parse($jadwal['deadline_date'])->lt(Carbon::today());

            /*
             * 'matched' dan 'excluded' SENGAJA tidak dijumlahkan.
             *
             * Keduanya angka cohort — sama persis di setiap baris paket, karena
             * cohort tidak bergantung paket. Menjumlahkannya atas lima paket
             * memberi lima kali headcount dan terbaca seolah aturan menjangkau
             * lima kali lebih banyak orang. Sisanya memang angka PENUGASAN, dan
             * penjumlahannya benar.
             */
            foreach (['new', 'kept', 'covered', 'release', 'withheld'] as $k) {
                $total[$k] += (int) ($dampak[$k] ?? 0);
            }
            $baris[] = $dampak;
        }

        $total['matched'] = count($cohort['matched']);
        $total['excluded'] = $cohort['excluded'];

        return [
            'paket' => $baris,
            'total' => $total,
            'auto_release' => SopSetting::bool('mover_auto_release', false),
        ];
    }

    public function queueActivation(int $ruleId, ?int $userId): int
    {
        return $this->queueApply($ruleId, 'RULE_APPLY', 'ACTIVATION', true, $userId);
    }

    public function queueApply(int $ruleId, string $type, string $trigger, bool $useDraft, ?int $userId = null): int
    {
        $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
        }
        if (! $useDraft && ($rule->Status_Aturan !== 'ACTIVE' || ! $rule->Active_Config_JSON)) {
            throw ValidationException::withMessages(['rule' => 'Aturan belum aktif.']);
        }
        if ($this->runs->hasActiveForRule($ruleId)) {
            throw ValidationException::withMessages(['rule' => 'Aturan ini masih memiliki proses aktif.']);
        }

        $config = json_decode($useDraft ? $rule->Draft_Config_JSON : $rule->Active_Config_JSON, true) ?: [];
        $hash = $useDraft ? $rule->Draft_Hash : $rule->Active_Hash;
        $runId = $this->runs->queue($type, $trigger, $ruleId, $hash, $userId, [
            'config' => $config,
            'activate' => $useDraft,
        ]);

        DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
            'Last_Apply_Status' => 'QUEUED',
            'Last_Apply_Error' => null,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        SopLog::catat('KEBIJAKAN_AKTIVASI', 'Apply aturan kebijakan dokumen masuk antrean.', null, null, null, null, [
            'id_assignment_rule' => $ruleId,
            'id_automation_run' => $runId,
            'tipe_run' => $type,
            'trigger_run' => $trigger,
            'use_draft' => $useDraft,
            'config_hash' => $hash,
            'oleh_id_users' => $userId,
        ]);

        ApplySopAssignmentRule::dispatch($runId, $ruleId, $useDraft, $userId);

        return $runId;
    }

    public function applyQueued(int $runId, int $ruleId, bool $activate, ?int $userId): array
    {
        if (! $this->runs->claim($runId)) {
            return ['skipped' => 1, 'message' => 'Run sudah diproses.'];
        }

        try {
            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Last_Apply_Status' => 'RUNNING', 'Last_Apply_Error' => null,
            ]);
            $payload = $this->runs->payload($runId);
            $config = SopAssignmentRuleConfig::normalize((array) ($payload['config'] ?? []));
            $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
            if (! $rule) {
                throw new \RuntimeException('Aturan tidak ditemukan.');
            }

            /*
             * Satu paket = satu satuan kegagalan.
             *
             * Paket yang sudah tersemai TIDAK dianulir karena paket berikutnya
             * gagal. Itu bukan kebijakan baru — jalur chunk di dalam
             * terapkanSatuPaket() sudah lama memegang janji yang sama; di sini
             * ia hanya dinaikkan satu tingkat, dari antar-chunk menjadi
             * antar-paket.
             */
            $ringkas = [];
            $adaMasalah = false;

            // Sekali untuk seluruh paket: cohort tidak bergantung paket, dan
            // resolveTargets() bukan kueri murah untuk diulang lima kali.
            $cohort = $this->resolveCohort($config);

            foreach ($this->paket->daftar($ruleId) as $paketBaris) {
                $idPaket = (int) $paketBaris->Id_SOP_Paket;
                $rpId = (int) $paketBaris->Id_SOP_Assignment_Rule_Paket;
                $runAnak = $this->runs->queueAnak(
                    $runId, $idPaket, 'RULE_APPLY', 'ACTIVATION', $ruleId, $rule->Draft_Hash, $userId,
                );

                try {
                    $hasil = $this->terapkanSatuPaket($rule, $ruleId, $paketBaris, $config, $cohort, $userId);

                    if (! empty($hasil['withheld_gate'])) {
                        $adaMasalah = true;
                        $this->paket->tandaiStatus($rpId, 'NEEDS_ATTENTION', $hasil['message']);
                        $this->runs->skip($runAnak, $hasil['message'], $hasil);
                    } else {
                        $this->paket->tandaiStatus($rpId, 'SUCCESS');
                        $this->runs->success($runAnak, $hasil);
                    }

                    $ringkas[] = ['paket' => $paketBaris->Nama_Paket] + $hasil;
                } catch (\Throwable $e) {
                    $adaMasalah = true;
                    Log::error('SOP aturan penugasan applyQueued gagal', ['id_aturan' => $ruleId, 'id_paket' => $idPaket, 'exception' => $e]);
                    $this->paket->tandaiStatus($rpId, 'FAILED', $e->getMessage());
                    $this->runs->fail($runAnak, $e->getMessage());
                    $ringkas[] = [
                        'paket' => $paketBaris->Nama_Paket,
                        'gagal' => mb_substr($e->getMessage(), 0, 200),
                    ];
                }
            }

            $total = $this->totalkan($ringkas, $cohort);
            $total['rincian'] = $ringkas;

            DB::transaction(function () use ($ruleId, $config, $userId, $activate, $adaMasalah) {
                $updates = [
                    // NEEDS_ATTENTION, bukan status PARTIAL baru: status ini
                    // sudah dihitung kartu "Perhatian" di ringkasan dan sudah
                    // punya warna serta label di layar.
                    'Last_Apply_Status' => $adaMasalah ? 'NEEDS_ATTENTION' : 'SUCCESS',
                    'Last_Apply_Error' => $adaMasalah
                        ? 'Sebagian paket tidak selesai. Periksa rincian per paket.' : null,
                    'Last_Applied_At' => now(),
                    'Last_Scanned_At' => now(),
                    'Updated_By' => $userId,
                    'Updated_At' => now(),
                ];
                if ($activate) {
                    $snapshot = SopAssignmentRuleConfig::snapshotPaket($this->paket->barisMentah($ruleId));
                    $updates += [
                        'Active_Config_JSON' => SopAssignmentRuleConfig::encode($config),
                        'Active_Hash' => SopAssignmentRuleConfig::hash($config, $snapshot),
                        'Status_Aturan' => 'ACTIVE',
                        'Activated_At' => now(),
                        'Deactivated_At' => null,
                    ];
                }
                DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update($updates);
            });

            $this->runs->success($runId, $total);
            SopLog::catat('KEBIJAKAN_APPLY_OK', 'Apply aturan kebijakan dokumen selesai.', null, null, null, null, [
                'id_assignment_rule' => $ruleId,
                'id_automation_run' => $runId,
                'activate' => $activate,
                'result' => $total,
                'oleh_id_users' => $userId,
            ]);

            return $total;
        } catch (\Throwable $e) {
            $this->runs->fail($runId, $e->getMessage());
            // Paket yang belum sempat menutup dirinya harus ikut ditandai:
            // aturan NEEDS_ATTENTION tanpa satu paket pun yang menjelaskan
            // sebabnya tak memberi admin cara tahu mana yang berhenti.
            $this->paket->tandaiGantung($ruleId, $e->getMessage());
            DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
                'Last_Apply_Status' => 'FAILED',
                'Last_Apply_Error' => mb_substr($e->getMessage(), 0, 1000),
                'Updated_At' => now(),
            ]);
            SopLog::catat('KEBIJAKAN_APPLY_FAIL', 'Apply aturan kebijakan dokumen gagal.', null, null, null, null, [
                'id_assignment_rule' => $ruleId,
                'id_automation_run' => $runId,
                'activate' => $activate,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'oleh_id_users' => $userId,
            ]);
            throw $e;
        }
    }

    /**
     * Badan apply untuk SATU paket.
     *
     * Isinya jalur lama yang dulu berlaku untuk satu-satunya paket sebuah
     * aturan, termasuk pemecahan chunk 100 per transaksi. Yang berubah hanya
     * sumber jadwalnya: setelan induk, atau override milik paket ini.
     *
     * @return array angka dampak paket ini; berkunci 'withheld_gate' bila
     *               tertahan gerbang tenggat absolut
     */
    private function terapkanSatuPaket(object $rule, int $ruleId, object $paketBaris, array $config, array $cohort, ?int $userId): array
    {
        $idPaket = (int) $paketBaris->Id_SOP_Paket;
        $schedule = $this->paket->jadwalEfektif($paketBaris, $config['schedule']);

        if ($schedule['deadline_mode'] === 'ABSOLUTE'
            && Carbon::parse($schedule['deadline_date'])->lt(Carbon::today())) {
            return [
                'withheld_gate' => 1,
                'withheld' => 1,
                'message' => 'Tenggat absolut sudah lewat. Penugasan baru ditahan sampai draft diperbaiki.',
                'reason' => 'ABSOLUTE_DEADLINE_PASSED',
            ];
        }

        $impact = $this->impact($ruleId, $idPaket, $cohort, true);
        $assignedTargets = [];
        $released = 0;

        /*
         * Penugasan dipecah per chunk, masing-masing dalam transaksi sendiri.
         *
         * assignKeHeader() melakukan INSERT per karyawan ditambah INSERT progress
         * per item wajib. Untuk aturan yang menyasar ratusan orang, satu transaksi
         * raksasa menahan lock sangat lama dan berisiko timeout — jalur assign
         * manual sudah lama dilindungi dengan memecah ke ProsesBulkAssignSop
         * berukuran chunk 100.
         *
         * Header dibuat sekali di luar loop supaya semua chunk bergabung ke batch
         * yang sama. Chunk yang gagal dicatat tanpa membatalkan yang sudah commit:
         * penugasan yang sudah sah tak dianulir hanya karena chunk berikutnya
         * bermasalah.
         */
        $chunkGagal = [];
        if ($impact['_new_codes']) {
            $opts = $this->scheduleOptions($schedule, $ruleId, $rule->Nama_Aturan, (string) $paketBaris->Nama_Paket);
            $headerId = DB::transaction(fn () => $this->schedules->buatHeader($idPaket, $opts, $userId));

            foreach (array_chunk($impact['_new_codes'], self::CHUNK_ASSIGN) as $i => $chunk) {
                try {
                    $hasil = DB::transaction(fn () => $this->schedules->assignKeHeader(
                        $headerId, $idPaket, $chunk, $userId, $ruleId,
                    ));
                    $assignedTargets = array_merge($assignedTargets, $hasil['targets']);
                } catch (\Throwable $e) {
                    Log::error('SOP aturan penugasan terapkanSatuPaket gagal', ['id_paket' => $idPaket, 'chunk' => $i, 'exception' => $e]);
                    $chunkGagal[] = [
                        'chunk' => $i + 1,
                        'jumlah' => count($chunk),
                        'pesan' => mb_substr($e->getMessage(), 0, 200),
                    ];
                }
            }
        }

        DB::transaction(function () use ($ruleId, $idPaket, $impact, $userId, &$released) {
            if (SopSetting::bool('mover_auto_release', false) && $impact['_release_codes']) {
                $released = $this->schedules->cabutMilikRule($ruleId, $idPaket, $impact['_release_codes'], $userId);
            }
        });

        if ($assignedTargets) {
            app(SopNotificationService::class)->notifPenugasan(
                $assignedTargets, $idPaket, $paketBaris->Nama_Paket ?: 'Paket Dokumen',
            );
        }

        unset($impact['_new_codes'], $impact['_release_codes']);
        $impact['assigned'] = count($assignedTargets);
        $impact['released'] = $released;
        // Kegagalan chunk harus terlihat, bukan ditelan diam-diam: sebagian
        // penugasan sudah commit sementara sisanya tidak.
        if ($chunkGagal) {
            $impact['gagal'] = $chunkGagal;
        }

        return $impact;
    }

    /**
     * @param  array<int,array>  $ringkas  hasil per paket
     * @param  array  $cohort  hasil resolveCohort(), untuk angka yang bukan per paket
     */
    private function totalkan(array $ringkas, array $cohort): array
    {
        $total = [
            'new' => 0, 'assigned' => 0, 'kept' => 0,
            'covered' => 0, 'released' => 0, 'withheld' => 0,
        ];

        foreach ($ringkas as $baris) {
            foreach (array_keys($total) as $k) {
                $total[$k] += (int) ($baris[$k] ?? 0);
            }
        }

        // 'matched' adalah jumlah ORANG dalam cohort — sama di setiap paket.
        // Menjumlahkannya lintas paket menuliskan lima kali headcount ke
        // Total_Ditemukan pada riwayat, dan angka itu terbaca sebagai jangkauan.
        $total['matched'] = count($cohort['matched']);
        $total['excluded'] = $cohort['excluded'];
        $total['paket_diproses'] = count($ringkas);

        return $total;
    }

    public function deactivate(int $ruleId, ?int $userId): void
    {
        $rule = DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->first();
        if (! $rule) {
            throw ValidationException::withMessages(['rule' => 'Aturan tidak ditemukan.']);
        }

        DB::table('N_SOP_Assignment_Rule')->where('Id_SOP_Assignment_Rule', $ruleId)->update([
            'Status_Aturan' => 'INACTIVE',
            'Deactivated_At' => now(),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        SopLog::catat('KEBIJAKAN_NONAKTIF', 'Aturan kebijakan dokumen dinonaktifkan.', null, null, null, null, [
            'id_assignment_rule' => $ruleId,
            'nama_aturan' => $rule->Nama_Aturan,
            'oleh_id_users' => $userId,
        ]);
    }

    public function scanActive(string $trigger = 'SCHEDULED', ?int $userId = null): array
    {
        if (! SopSetting::bool('mover_auto_assign', true)) {
            return ['queued' => 0, 'disabled' => true];
        }
        $ids = DB::table('N_SOP_Assignment_Rule')->where('Status_Aturan', 'ACTIVE')
            ->where('Flag_Aktif', SopStatus::AKTIF)->pluck('Id_SOP_Assignment_Rule');
        $queued = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            try {
                $this->queueApply((int) $id, 'RULE_SCAN', $trigger, false, $userId);
                $queued++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Dampak sebuah config terhadap SATU paket.
     *
     * Dulu menerima baris aturan karena paketnya menempel di sana; sekarang
     * paket diterima eksplisit, dan metode ini dipanggil sekali per paket.
     */
    /**
     * Siapa saja yang dijangkau config ini — TANPA melihat paket sama sekali.
     *
     * Dihitung SEKALI per apply/pratinjau, bukan sekali per paket. Cohort tidak
     * bergantung pada paket, jadi menghitungnya di dalam loop berarti menembak
     * resolveTargets() lima kali untuk jawaban yang identik.
     *
     * @return array{matched:array,matchedIndex:array,excluded:int}
     */
    private function resolveCohort(array $config): array
    {
        $groupCodes = [];
        foreach ($config['groups'] as $group) {
            $groupCodes = array_merge($groupCodes, $this->targets->resolveTargets($group));
        }
        $groupCodes = array_values(array_unique($groupCodes));
        $included = $this->resolvePeople($config['include_karyawan']);

        /*
         * Pengecualian datang dari DUA jalur yang digabung: daftar NIK, dan
         * grup kriteria. Keduanya diperlakukan sama persis — "kecuali level
         * Manager" tak boleh lebih lemah daripada mengetik nama-namanya satu
         * per satu.
         */
        $excluded = $this->resolvePeople($config['exclude_karyawan']);
        foreach (($config['exclude_groups'] ?? []) as $group) {
            $excluded = array_merge($excluded, $this->targets->resolveTargets($group));
        }
        $excluded = array_values(array_unique($excluded));

        $beforeExclude = array_values(array_unique(array_merge($groupCodes, $included)));
        $matched = array_values(array_diff($beforeExclude, $excluded));

        return [
            'matched' => $matched,
            // Lookup O(1). Sebelumnya in_array() dipanggil di dalam filter atas
            // SELURUH penugasan aktif paket — biayanya perkalian, bukan
            // penjumlahan, dan dengan lima paket biaya itu dikali lima.
            'matchedIndex' => array_flip($matched),
            'excluded' => count(array_intersect($beforeExclude, $excluded)),
        ];
    }

    /**
     * Dampak sebuah cohort terhadap SATU paket.
     *
     * Menerima cohort yang sudah diresolusi, bukan config: yang benar-benar
     * berbeda antar paket hanyalah penugasan yang sudah ada.
     */
    private function impact(int $ruleId, int $idPaket, array $cohort, bool $internal): array
    {
        $matched = $cohort['matched'];
        $matchedIndex = $cohort['matchedIndex'];

        $current = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->get(['d.Kode_Karyawan', 'd.Id_SOP_Assignment_Rule', 'pj.Sumber'])
            ->keyBy('Kode_Karyawan');

        $new = [];
        $kept = 0;
        $covered = 0;
        foreach ($matched as $code) {
            $assignment = $current->get($code);
            if (! $assignment) {
                $new[] = $code;
            } elseif ((int) ($assignment->Id_SOP_Assignment_Rule ?? 0) === $ruleId
                && $assignment->Sumber === 'AUTO_RULE') {
                $kept++;
            } else {
                $covered++;
            }
        }

        $release = $current->filter(fn ($assignment, $code) => (int) ($assignment->Id_SOP_Assignment_Rule ?? 0) === $ruleId
            && $assignment->Sumber === 'AUTO_RULE'
            && ! isset($matchedIndex[$code])
        )->keys()->values()->all();
        $autoRelease = SopSetting::bool('mover_auto_release', false);

        $result = [
            'matched' => count($matched),
            'new' => count($new),
            'kept' => $kept,
            'covered' => $covered,
            'release' => $autoRelease ? count($release) : 0,
            'withheld' => $autoRelease ? 0 : count($release),
            'excluded' => $cohort['excluded'],
            'auto_release' => $autoRelease,
        ];
        if ($internal) {
            $result['_new_codes'] = $new;
            $result['_release_codes'] = $release;
        }

        return $result;
    }

    private function resolvePeople(array $inputs): array
    {
        if (! $inputs) {
            return [];
        }
        $rows = collect();
        foreach (array_chunk($inputs, 350) as $chunk) {
            $rows = $rows->merge(DB::table('Karyawan')->where('Aktif', 'Y')
                ->where(fn ($q) => $q->whereIn('Kode_Karyawan', $chunk)->orWhereIn('No_Induk_Karyawan', $chunk))
                ->pluck('Kode_Karyawan'));
        }

        return $rows->unique()->values()->all();
    }

    private function scheduleOptions(array $schedule, int $ruleId, string $ruleName, string $namaPaket = ''): array
    {
        $mode = $schedule['deadline_mode'];
        $deadline = null;
        if ($mode === 'RELATIVE') {
            $deadline = now()->addDays($schedule['deadline_days'])->endOfDay();
        }
        if ($mode === 'ABSOLUTE') {
            $deadline = Carbon::parse($schedule['deadline_date'])->endOfDay();
        }

        return [
            'id_assignment_rule' => $ruleId,
            // Nama paket ikut disebut: satu aturan kini melahirkan beberapa
            // batch sekaligus, dan tanpa itu kelimanya bernama sama persis.
            'nama_batch' => mb_substr('Otomasi - '.$ruleName.($namaPaket ? ' - '.$namaPaket : '').' - '.now()->format('d M Y'), 0, 150),
            'keterangan' => 'Cohort otomatis dari Otomasi Dokumen.',
            'sumber' => 'AUTO_RULE',
            'tipe_tenggat' => $mode === 'NONE' ? 'SAMPAI_DICABUT' : 'DEADLINE',
            'tanggal_deadline' => $deadline,
            'flag_reminder' => $schedule['reminder_enabled'],
            'irama_reminder_hari' => $schedule['reminder_interval_days'],
        ];
    }

    private function mapRow(object $row): object
    {
        $row->Id_SOP_Assignment_Rule = Hashids::connection('custom')->encode($row->Id_SOP_Assignment_Rule);
        $row->Draft_Berubah = ! empty($row->Active_Hash) && $row->Draft_Hash !== $row->Active_Hash;

        return $row;
    }
}
