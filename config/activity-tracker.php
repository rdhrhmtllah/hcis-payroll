<?php

return [
    'enabled' => env('ACTIVITY_TRACKER_ENABLED', true),

    'queue' => [
        'connection' => env('ACTIVITY_TRACKER_QUEUE_CONN', 'cloudtasks'),
        'name' => env('ACTIVITY_TRACKER_QUEUE', 'hcis-activity-queue'),
    ],

    'db_connection' => env('ACTIVITY_TRACKER_DB', config('database.default')),

    'capture' => [
        'pageviews' => true,
        'api_calls' => true,
        'downloads' => true,
        'failed_auth' => true,
    ],

    /*
     | Aturan apa yang dianggap "aktivitas user" dan layak di-track.
     | Prinsip: rekam INTENT user, bukan request otomatis di balik layar.
     */
    'track' => [
        // GET navigasi halaman (Inertia visit / full page load / refresh)
        'page_navigations' => true,
        // Mutation (POST/PUT/PATCH/DELETE) = aksi user (submit, approve, mark-read, hapus)
        'mutations' => true,
        // GET XHR/data-fetch/polling di balik layar → default JANGAN di-track
        'background_get' => false,
    ],

    'sampling' => [
        'pageview_rate' => (float) env('ACTIVITY_PAGEVIEW_RATE', 1.0),
        'api_call_rate' => (float) env('ACTIVITY_API_RATE', 1.0),
        'dedup_seconds' => 2,
    ],

    'exclude_paths' => [
        '_debugbar/*',
        'vite/*',
        'build/*',
        'storage/*',
        'health',
        'ping',
        'broadcasting/*',
        'livewire/*',
        'api/v1/activity/handshake',
        'log-viewer/*',
        // Endpoint polling/otomatis yang sering hit sendiri — jangan di-track
        '*/notifications/unread',
        '*/notifications/count',
        '*/notifications/poll',
        '*/heartbeat',
        '*/keep-alive',
        // Data-fetch internal milik Activity Tracker sendiri
        'my/activity/timeline',
        'my/activity/sessions',
        'my/activity/sessions/*',
        'my/activity/devices',
        'my/activity/security',
        'admin/activity/kpis',
        'admin/activity/heatmap',
        'admin/activity/top-modules',
        'admin/activity/users',
        'admin/activity/users/*',
        'admin/activity/anomalies',
    ],

    'retention_days' => [
        'event' => 365,
        'session' => 365,
        'daily' => 365 * 3,
    ],

    'pii_redact' => [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'nik',
        'ktp',
        'email',
        'phone',
        'no_hp',
        'card_number',
        '_token',
    ],

    'ip_mask_for_users' => true,

    'idle_session_minutes' => (int) env('ACTIVITY_IDLE_MINUTES', 30),

    'session_id_cookie' => '_act_sid',

    'route_map_cache_ttl' => 3600,

    'flush_batch_size' => 200,

    'geo' => [
        'driver' => env('ACTIVITY_GEO_DRIVER', 'ipapi'),
        'cache_days' => 30,
        'timeout_sec' => 3,
    ],

    'risk' => [
        'new_device_score' => 30,
        'new_geo_score' => 25,
        'unusual_hour_score' => 15,
        'velocity_score' => 30,
        'unusual_hours' => [22, 23, 0, 1, 2, 3, 4, 5],
    ],

    'admin_access' => [
        'page_key' => env('ACTIVITY_ADMIN_PAGE_KEY', 'ActivityMonitor'),
    ],
];
