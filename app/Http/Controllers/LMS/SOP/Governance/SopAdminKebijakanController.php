<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\LMS\SOP\Automation\RunSopAutomation;
use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopAutomationRunService;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\SopPenjadwalanTargetService;
use App\Services\LMS\SOP\Support\SopLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Otomasi SOP — aturan penugasan dan riwayat eksekusinya.
 *
 * Kebijakan global dan status scheduler terjadwal TIDAK lagi di sini; keduanya
 * pindah ke SopAdminSetupSystemController (halaman "Set Up System"). Yang masih
 * dibagi dua halaman hanyalah runNow(): tombol pemicunya hidup di kartu job
 * halaman Set Up System, tetapi memicu evaluasi tepat setelah membuat aturan
 * adalah kebutuhan admin modul — karena itu rutenya bergerbang dua kunci.
 */
class SopAdminKebijakanController extends Controller
{
    /**
     * Satu aturan validasi untuk store dan update: keduanya menerima bentuk
     * kiriman yang sama persis, dan menyalinnya dua kali adalah cara paling
     * gampang membuat salah satunya tertinggal saat berubah.
     */
    private const ATURAN_RULES = [
        'nama_aturan' => 'required|string|max:150',
        'config' => 'required|array',
        'paket' => 'required|array|min:1',
        'paket.*.id_paket' => 'required|string',
        'paket.*.urutan' => 'nullable|integer|min:1',
        'paket.*.override' => 'nullable|boolean',
        'paket.*.schedule' => 'nullable|array',
    ];

    public function __construct(
        private SopAssignmentRuleService $rules,
        private SopAutomationRunService $runs,
        private SopPenjadwalanService $schedules,
        private SopPenjadwalanTargetService $targets,
    ) {}

    public function index()
    {
        return inertia('LMS/SOP/Governance/Admin/SopKebijakan');
    }

    public function summary()
    {
        try {
            $rows = $this->rules->list();

            return ResponseHelper::success([
                'total_rules' => count($rows),
                'active_rules' => collect($rows)->where('Status_Aturan', 'ACTIVE')->count(),
                // RUNNING ikut dihitung: aturan yang penerapannya mati di tengah
                // jalan tersangkut pada status itu dan tak bisa di-apply lagi.
                // Tanpa dimasukkan ke sini, kondisi tersebut tak terlihat sama
                // sekali di kartu ringkasan.
                'attention_rules' => collect($rows)->whereIn('Last_Apply_Status', ['FAILED', 'NEEDS_ATTENTION', 'RUNNING'])->count(),
                // Baris induk saja. Baris anak per paket berumur pendek dan
                // ditutup segera; ikut menghitungnya membuat kartu "sedang
                // berjalan" menyebut angka yang jauh lebih besar dari jumlah
                // otomasi yang sebenarnya berjalan.
                'active_runs' => DB::table('N_SOP_Automation_Run')
                    ->whereNull('Id_Run_Induk')
                    ->whereIn('Status_Run', ['QUEUED', 'RUNNING'])->count(),
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('summary', $e);
        }
    }

    public function options()
    {
        try {
            // Daftar orang ikut di sini, bukan endpoint sendiri: layar butuh
            // keduanya bersamaan, dan keduanya sama-sama ter-cache 30 menit.
            $orang = $this->targets->daftarOrang();

            return ResponseHelper::success([
                'packages' => $this->schedules->paketPublished()['items'],
                'filters' => $this->targets->filterOptions(),
                'karyawan' => $orang['items'],
                'karyawan_total' => $orang['total'],
            ]);
        } catch (\Throwable $e) {
            return $this->serverError('options', $e);
        }
    }

    public function listRules()
    {
        try {
            return ResponseHelper::success($this->rules->list());
        } catch (\Throwable $e) {
            return $this->serverError('rules', $e);
        }
    }

    public function showRule(string $id)
    {
        try {
            $rule = $this->rules->find($this->decode($id));

            return $rule ? ResponseHelper::success($rule) : ResponseHelper::error('Aturan tidak ditemukan', 404);
        } catch (\Throwable $e) {
            return $this->serverError('show rule', $e);
        }
    }

    public function storeRule(Request $request)
    {
        try {
            $request->validate(self::ATURAN_RULES);
            $id = $this->rules->saveDraft(
                $request->input('nama_aturan'),
                $request->input('config'),
                $this->paketMasukan($request->input('paket')),
                Auth::id(),
            );

            return ResponseHelper::success(['id' => $this->encode($id)], 'Draft aturan tersimpan', 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('store rule', $e);
        }
    }

    public function updateRule(string $id, Request $request)
    {
        try {
            $request->validate(self::ATURAN_RULES);
            $ruleId = $this->decode($id);
            $this->rules->saveDraft(
                $request->input('nama_aturan'),
                $request->input('config'),
                $this->paketMasukan($request->input('paket')),
                Auth::id(),
                $ruleId,
            );

            return ResponseHelper::success(['id' => $this->encode($ruleId)], 'Draft aturan diperbarui');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('update rule', $e);
        }
    }

    public function previewRule(string $id, Request $request)
    {
        try {
            return ResponseHelper::success($this->rules->preview(
                $this->decode($id),
                $request->filled('config') ? $request->input('config') : null,
            ));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('preview rule', $e);
        }
    }

    public function activateRule(string $id)
    {
        try {
            $runId = $this->rules->queueActivation($this->decode($id), Auth::id());

            return ResponseHelper::success(['run_id' => $this->encode($runId)], 'Aktivasi masuk antrean', 202);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->serverError('activate rule', $e);
        }
    }

    public function deactivateRule(string $id)
    {
        try {
            $this->rules->deactivate($this->decode($id), Auth::id());

            return ResponseHelper::success(null, 'Aturan dinonaktifkan. Penugasan yang ada tetap tercatat.');
        } catch (\Throwable $e) {
            return $this->serverError('deactivate rule', $e);
        }
    }

    public function history(Request $request)
    {
        try {
            // Plafon diselaraskan dengan service (100). Sebelumnya controller
            // mengizinkan 200 sementara service diam-diam menjepitnya ke 100,
            // sehingga permintaan 200 balas 100 tanpa memberi tahu siapa pun.
            return ResponseHelper::success(
                $this->runs->history(min(100, max(5, (int) $request->query('limit', 50)))),
            );
        } catch (\Throwable $e) {
            return $this->serverError('run history', $e);
        }
    }

    public function runNow(string $type)
    {
        try {
            $map = ['rule-scan' => 'RULE_SCAN', 'reminder' => 'REMINDER'];
            $runType = $map[$type] ?? null;
            if (! $runType) {
                return ResponseHelper::error('Tipe otomasi tidak didukung.', 422);
            }
            if ($this->runs->hasActive($runType)) {
                return ResponseHelper::error('Otomasi ini masih berjalan.', 409);
            }

            $runId = $this->runs->queue($runType, 'MANUAL', null, null, Auth::id());
            RunSopAutomation::dispatch($runId, $runType, Auth::id());
            SopLog::catat('KEBIJAKAN_RUN', 'Otomasi Dokumen dijalankan manual.', null, null, null, null, [
                'id_automation_run' => $runId,
                'tipe_run' => $runType,
                'oleh_id_users' => Auth::id(),
            ]);

            return ResponseHelper::success(['run_id' => $this->encode($runId)], 'Otomasi masuk antrean', 202);
        } catch (\Throwable $e) {
            return $this->serverError('run now', $e);
        }
    }

    /**
     * Terjemahkan daftar paket dari layar: hashid → id nyata.
     *
     * Dilakukan di controller, bukan di service, supaya service tak perlu tahu
     * apa pun tentang hashid — sama seperti seluruh endpoint lain di berkas ini.
     *
     * @param  array<int,array>  $masukan
     */
    private function paketMasukan(array $masukan): array
    {
        $hasil = [];
        foreach (array_values($masukan) as $i => $baris) {
            $hasil[] = [
                'id_paket' => $this->decode((string) $baris['id_paket']),
                'urutan' => (int) ($baris['urutan'] ?? $i + 1),
                'override' => (bool) ($baris['override'] ?? false),
                'schedule' => $baris['schedule'] ?? null,
            ];
        }

        return $hasil;
    }

    private function decode(string $hash): int
    {
        $decoded = Hashids::connection('custom')->decode($hash);
        if (empty($decoded[0])) {
            throw ValidationException::withMessages(['id' => 'ID tidak valid.']);
        }

        return (int) $decoded[0];
    }

    private function encode(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function serverError(string $action, \Throwable $e)
    {
        Log::error("SOP kebijakan {$action}: {$e->getMessage()}", ['exception' => $e]);

        return ResponseHelper::error('Terjadi kesalahan sistem.', 500);
    }
}
