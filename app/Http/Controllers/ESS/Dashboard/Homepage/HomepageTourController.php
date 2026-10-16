<?php

namespace App\Http\Controllers\ESS\Dashboard\Homepage;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic tour progress endpoints.
 *
 * Storage schema (KPI_Users.tour_progress, nvarchar(max)):
 * {
 *   "version": 1,
 *   "tours": {
 *     "homepage": {
 *       "status": "not_started|in_progress|completed|skipped",
 *       "current_step": "key",
 *       "completed_steps": ["key1","key2"],
 *       "skipped_at_step": null,
 *       "started_at": "...",
 *       "completed_at": "...",
 *       "updated_at": "...",
 *       "step_version": 1
 *     },
 *     "leader-dashboard": { ... }
 *   }
 * }
 *
 * Legacy data (flat shape with top-level `status`) is auto-migrated on first read
 * by wrapping it under `tours.homepage`.
 */
class HomepageTourController extends Controller
{
    private const SCHEMA_VERSION = 1;

    private const STATUS_NOT_STARTED = 'not_started';

    private const STATUS_IN_PROGRESS = 'in_progress';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_SKIPPED = 'skipped';

    /**
     * Registry of known tours and their allowed step keys.
     * Add new tours here when you ship a tour on another page.
     */
    private const TOURS = [
        'homepage' => [
            'step_version' => 3,
            'steps' => [
                'welcome',
                // Sidebar drill-down
                'sidebar',
                'sidebar-brand',
                'sidebar-nav',
                'sidebar-profile-button',
                'sidebar-profile-menu',
                // Topbar — notifikasi (3 sub-step)
                'topbar-notifications',
                'notif-tabs',
                'notif-list',
                'notif-footer',
                // Topbar — profil pill
                'topbar-profile',
                'breadcrumb-popover',
                // Topbar — help drill-down (3 sub-step)
                'topbar-help',
                'help-tour-cta',
                'help-contacts',
                // Konten halaman (top → bottom)
                'hero',
                'awareness',
                'module-hcis',
                'module-kpi',
                'module-lms',
                'module-overtime',         // staff only
                'calendar',
                'leader-approvals',        // leader only
                'quick-actions',
                'attendance-composition',
                'submissions',
                'farewell',
            ],
        ],
        'leader-dashboard' => [
            'step_version' => 1,
            'steps' => [
                'welcome',
                'hero',
                'hero-action-focus',
                'insight-hcis',
                'insight-kpi',
                'insight-lms',
                'team-members',
                'team-members-filters',
                'team-members-content',
                'team-member-card',
                'team-member-identity',
                'team-member-metrics',
                'team-member-status',
                'team-member-detail',
                'team-member-detail-summary',
                'team-member-priority-reasons',
                'team-member-next-actions',
                'team-member-hcis-detail',
                'team-member-weekly-calendar',
                'team-member-issue-list',
                'team-member-kpi-detail',
                'team-member-lms-detail',
                'team-member-approval-detail',
                'team-calendar',
                'action-center',
                'approval-queue',
                'issue-heatmap',
                'training-pending',
                'farewell',
            ],
        ],
    ];

    public function updateProgress(Request $request)
    {
        $user = Auth::user();
        if (! $user || empty($user->Id_Users)) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'tour' => ['required', 'string', 'max:50'],
            'step' => ['required', 'string', 'max:50'],
            'status' => ['nullable', 'in:in_progress,completed,skipped'],
        ]);

        $tourKey = $validated['tour'];
        if (! $this->isKnownTour($tourKey) || ! $this->isKnownStep($tourKey, $validated['step'])) {
            return response()->json(['ok' => false, 'message' => 'Unknown tour or step'], 422);
        }

        $envelope = $this->loadEnvelope($user->Id_Users);
        $tour = $this->ensureTourSlice($envelope, $tourKey);
        $now = Carbon::now()->toIso8601String();

        if (empty($tour['started_at'])) {
            $tour['started_at'] = $now;
        }
        $tour['current_step'] = $validated['step'];
        if (! in_array($validated['step'], $tour['completed_steps'], true)) {
            $tour['completed_steps'][] = $validated['step'];
        }

        $status = $validated['status'] ?? self::STATUS_IN_PROGRESS;
        if ($status === self::STATUS_COMPLETED) {
            $tour['status'] = self::STATUS_COMPLETED;
            $tour['completed_at'] = $now;
        } elseif ($status === self::STATUS_SKIPPED) {
            $tour['status'] = self::STATUS_SKIPPED;
            $tour['skipped_at_step'] = $validated['step'];
            $tour['completed_at'] = $now;
        } else {
            $tour['status'] = self::STATUS_IN_PROGRESS;
        }
        $tour['updated_at'] = $now;
        $tour['step_version'] = self::TOURS[$tourKey]['step_version'];

        $envelope['tours'][$tourKey] = $tour;
        $this->saveEnvelope($user->Id_Users, $envelope);

        return response()->json(['ok' => true, 'progress' => $tour]);
    }

    public function skip(Request $request)
    {
        $user = Auth::user();
        if (! $user || empty($user->Id_Users)) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'tour' => ['required', 'string', 'max:50'],
            'at_step' => ['nullable', 'string', 'max:50'],
        ]);

        $tourKey = $validated['tour'];
        if (! $this->isKnownTour($tourKey)) {
            return response()->json(['ok' => false, 'message' => 'Unknown tour'], 422);
        }

        $envelope = $this->loadEnvelope($user->Id_Users);
        $tour = $this->ensureTourSlice($envelope, $tourKey);
        $now = Carbon::now()->toIso8601String();

        if (empty($tour['started_at'])) {
            $tour['started_at'] = $now;
        }
        $tour['status'] = self::STATUS_SKIPPED;
        $tour['skipped_at_step'] = $validated['at_step'] ?? $tour['current_step'] ?? null;
        $tour['updated_at'] = $now;
        $tour['completed_at'] = $now;
        $tour['step_version'] = self::TOURS[$tourKey]['step_version'];

        $envelope['tours'][$tourKey] = $tour;
        $this->saveEnvelope($user->Id_Users, $envelope);

        return response()->json(['ok' => true, 'progress' => $tour]);
    }

    public function reset(Request $request)
    {
        $user = Auth::user();
        if (! $user || empty($user->Id_Users)) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'tour' => ['required', 'string', 'max:50'],
        ]);

        $tourKey = $validated['tour'];
        if (! $this->isKnownTour($tourKey)) {
            return response()->json(['ok' => false, 'message' => 'Unknown tour'], 422);
        }

        $envelope = $this->loadEnvelope($user->Id_Users);
        $tour = self::defaultTour();
        $tour['updated_at'] = Carbon::now()->toIso8601String();
        $tour['step_version'] = self::TOURS[$tourKey]['step_version'];

        $envelope['tours'][$tourKey] = $tour;
        $this->saveEnvelope($user->Id_Users, $envelope);

        return response()->json(['ok' => true, 'progress' => $tour]);
    }

    // ─────────────────────────────────────────────────────────────
    //  Internal helpers
    // ─────────────────────────────────────────────────────────────

    private function isKnownTour(string $key): bool
    {
        return array_key_exists($key, self::TOURS);
    }

    private function isKnownStep(string $tourKey, string $step): bool
    {
        return in_array($step, self::TOURS[$tourKey]['steps'] ?? [], true);
    }

    private static function defaultTour(): array
    {
        return [
            'status' => self::STATUS_NOT_STARTED,
            'current_step' => null,
            'completed_steps' => [],
            'skipped_at_step' => null,
            'started_at' => null,
            'completed_at' => null,
            'updated_at' => null,
            'step_version' => self::SCHEMA_VERSION,
        ];
    }

    private static function defaultEnvelope(): array
    {
        return [
            'version' => self::SCHEMA_VERSION,
            'tours' => [],
        ];
    }

    private function loadEnvelope($userId): array
    {
        try {
            $raw = DB::table('KPI_Users')->where('Id_Users', $userId)->value('tour_progress');
        } catch (\Throwable $e) {
            Log::warning('Failed to load tour_progress envelope: '.$e->getMessage(), ['user_id' => $userId]);

            return self::defaultEnvelope();
        }

        if (empty($raw)) {
            return self::defaultEnvelope();
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return self::defaultEnvelope();
        }

        // ── Legacy migration: flat shape (had top-level "status") ──
        // becomes { version: 1, tours: { homepage: <flat> } } automatically.
        if (isset($decoded['status']) && ! isset($decoded['tours'])) {
            $legacy = array_intersect_key($decoded, self::defaultTour());
            $legacy = array_merge(self::defaultTour(), $legacy);

            return [
                'version' => self::SCHEMA_VERSION,
                'tours' => ['homepage' => $legacy],
            ];
        }

        return [
            'version' => $decoded['version'] ?? self::SCHEMA_VERSION,
            'tours' => is_array($decoded['tours'] ?? null) ? $decoded['tours'] : [],
        ];
    }

    /**
     * Get (or create) the slice for a tour from the envelope, normalized.
     */
    private function ensureTourSlice(array &$envelope, string $tourKey): array
    {
        $existing = $envelope['tours'][$tourKey] ?? [];

        return array_merge(self::defaultTour(), is_array($existing) ? $existing : []);
    }

    private function saveEnvelope($userId, array $envelope): void
    {
        try {
            DB::table('KPI_Users')
                ->where('Id_Users', $userId)
                ->update(['tour_progress' => json_encode($envelope, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            Log::error('Failed to save tour_progress envelope: '.$e->getMessage(), ['user_id' => $userId]);
        }
    }
}
