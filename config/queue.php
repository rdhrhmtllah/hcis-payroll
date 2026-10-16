<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
        'cloudtasks' => [
            'driver' => 'cloudtasks',
            'project' => env('CLOUD_TASKS_PROJECT', ''),
            'location' => env('CLOUD_TASKS_LOCATION', ''),
            'queue' => env('CLOUD_TASKS_QUEUE', 'default'),
            'queue_kpi_notif' => env('CLOUD_TASKS_QUEUE_KPI_NOTIF', 'general-notif'),
            'service_account_email' => env('CLOUD_TASKS_SERVICE_EMAIL', ''),
            'handler' => env('CLOUD_TASKS_HANDLER_URL', 'https://hcis.evonusabersaudara.co.id/handle-task'),
            // Semua job yang di-dispatch DI DALAM transaksi DB otomatis ditunda
            // sampai transaksi commit. Mencegah job "phantom" (jalan padahal
            // transaksi rollback) dan tidak menahan transaksi selama enqueue.
            'after_commit' => true,
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'N_LMS_Jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
            'after_commit' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlsrv'),
        'table' => 'N_LMS_Failed_Jobs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Queues (per-feature isolation)
    |--------------------------------------------------------------------------
    |
    | Nama queue khusus agar job berat tidak bersaing dengan job lain di queue
    | "default". Untuk Cloud Tasks, nama ini harus cocok dengan nama queue yang
    | dibuat di Cloud Tasks console (mis. `gcloud tasks queues create leader-dashboard`).
    |
    */

    'names' => [
        'leader_dashboard' => env('CLOUD_TASKS_QUEUE_LEADER_DASHBOARD', 'leader-dashboard'),
        'kpi_enforcement' => env('CLOUD_TASKS_QUEUE_KPI_ENFORCEMENT', 'kpi-enforcement'),

        // Queue terpusat untuk SEMUA export + cleanup-temp lintas modul (KPI, LMS,
        // dan modul export berikutnya). Render PDF tetap di queue tersendiri.
        'async_export' => env('CLOUD_TASKS_QUEUE_ASYNC_EXPORT', 'async-export'),

        // Queue khusus penjadwalan training LMS (bulk assign). Harus dibuat di
        // Cloud Tasks: `gcloud tasks queues create penjadwalan-queue`.
        'penjadwalan_training' => env('CLOUD_TASKS_QUEUE_PENJADWALAN', 'penjadwalans-queue'),

        // Outbox LMS (ajukan ulang ujian, sinkron penjadwalan ke HC Learn). Dulu tertulis
        // 'lms' di controller, padahal queue itu tidak pernah dibuat (NOT_FOUND, 14 Okt 2026);
        // bawaannya kini queue penjadwalan yang memang ada.
        'lms_outbox' => env('CLOUD_TASKS_QUEUE_LMS_OUTBOX', env('CLOUD_TASKS_QUEUE_PENJADWALAN', 'penjadwalans-queue')),

        // Deprecated: disisakan untuk kompatibilitas; job export kini pakai
        // 'async_export'. Hapus setelah dipastikan tak ada task tertinggal.
        'kpi_export' => env('CLOUD_TASKS_QUEUE_KPI_EXPORT', 'kpi-export'),
        'lms_export' => env('CLOUD_TASKS_QUEUE_LMS_EXPORT', 'lms-export'),
    ],
];
