<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        /*
         * Kanal bawaan seluruh aplikasi.
         *
         * Daftar isinya dibaca dari LOG_STACK, bukan dipaku ke ['single'].
         * Sebabnya lingkungan, bukan selera: production berjalan di Cloud Run
         * dengan maxScale 3 dan minScale 0, dan berkas log hidup DI DALAM
         * container yang menulisnya. Baris yang ditulis instance A tidak
         * terlihat dari instance B, dan ikut musnah begitu A didaur ulang -
         * yang tersisa di layar log viewer hanyalah berkas milik instance yang
         * kebetulan melayani permintaan itu.
         *
         * Dengan LOG_STACK=single,stderr, baris yang sama juga keluar ke stderr
         * dan ditangkap Cloud Logging: awet, bisa dicari, dan tidak bergantung
         * pada instance mana yang menjawab. Berkasnya tetap ditulis sehingga
         * log viewer tidak berubah fungsinya.
         *
         * Bawaannya tetap 'single' supaya pengembangan lokal tidak berubah.
         */
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'worker_schedular' => [
            'driver' => 'daily',
            'path' => storage_path('logs/worker_schedular.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ticket_log' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ticket_log.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'attendance_queue_worker' => [
            'driver' => 'daily',
            'path' => storage_path('logs/attendance_queue_worker.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'KonversiLemburController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KonversiLemburController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ShiftApprovalController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ShiftApprovalController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ApprovalDetailService' => [
            'driver' => 'daily',
            'path' => storage_path('logs/approval-detail-service.log'),
            'level' => 'info',
            'days' => 14,
        ],
        'ShiftRequestController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ShiftRequestController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'LeaderShiftController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/LeaderShiftController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'izinAtasanApprovalLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/izinAtasanApprovalLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'RecentKpiDetail' => [
            'driver' => 'daily',
            'path' => storage_path('logs/RecentKpiDetail.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        // 'cutiController' => [
        //     'driver' => 'daily',
        //     'path' => storage_path('logs/cutiController.log'),
        //     'level' => env('LOG_LEVEL', 'debug'),
        //     'days' => 30,
        // ],
        'JenisCutiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/JenisCutiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'cutiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cutiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'dashboardStaffController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/dashboardStaffController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'kpiNotifUpdate' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kpiNotifUpdate.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'WhatsappHelperLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/WhatsappHelperLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'TransactionService' => [
            'driver' => 'daily',
            'path' => storage_path('logs/TransactionService.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'cutiTutupBuku' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cutiTutupBuku.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'checkMatured' => [
            'driver' => 'daily',
            'path' => storage_path('logs/checkMatured.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'kpiApprovalController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kpiApprovalController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'ApprovalPerformanceController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ApprovalPerformanceController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'formKPIController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/formKPIController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'bscKategoriController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/bscKategoriController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FlagCheckinout' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FlagCheckinout.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'PenjadwalanKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PenjadwalanKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'PerformanceKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PerformanceKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'TargetKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/TargetKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'SatuanFormulaKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/SatuanFormulaKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'FormulaKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/FormulaKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'DashboardCeoController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/DashboardCeoController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'DashboardStaffController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/DashboardStaffController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UserAksesDivisiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UserAksesDivisiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UserAksesLevelController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UserAksesLevelController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'UpdatePerformanceController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/UpdatePerformanceController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waAddTeam' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waAddTeam.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'addTeam' => [
            'driver' => 'daily',
            'path' => storage_path('logs/addTeam.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'WaCreateUserLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/WaCreateUserLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'KontenKategoriController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KontenKategoriController.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],
        'LmsBunnyService' => [
            'driver' => 'daily',
            'path' => storage_path('logs/LmsBunnyService.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],
        'LmsGcsService' => [
            'driver' => 'daily',
            'path' => storage_path('logs/LmsGcsService.log'),
            'level' => 'debug',
            'days' => 30,
            'replace_placeholders' => true,
        ],
        'CreateUserLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CreateUserLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'KaryawanTeamLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KaryawanTeamLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'loginLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/loginLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waUlangLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waUlangLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'BatasCutiAkhirTahunLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/BatasCutiAkhirTahunLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'cutiHangusHarian' => [
            'driver' => 'daily',
            'path' => storage_path('logs/cutiHangusHarian.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'expiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/expiredIzin.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'promotedEmployees' => [
            'driver' => 'daily',
            'path' => storage_path('logs/promotedEmployees.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'HakCutiTambahan' => [
            'driver' => 'daily',
            'path' => storage_path('logs/HakCutiTambahan.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'kuotaAwalCuti' => [
            'driver' => 'daily',
            'path' => storage_path('logs/kuotaAwalCuti.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'tambahKuotaCutiBulanan' => [
            'driver' => 'daily',
            'path' => storage_path('logs/tambahKuotaCutiBulanan.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],
        'waUDashLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waUDashLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'uDashLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/uDashLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'homepageLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/homepageLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waIzinLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waIzinLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'izinLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/izinLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waAbsensiLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waAbsensiLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'absensiLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/absensiLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waShiftLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waShiftLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'shiftLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shiftLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waLemburLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waLemburLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'lemburLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lemburLog.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waConfirmIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waConfirmIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waIzinPage' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waIzinPage.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waCommandExpiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waCommandExpiredIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'CommandExpiredIzin' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CommandExpiredIzin.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'waCommandWaUlang' => [
            'driver' => 'daily',
            'path' => storage_path('logs/waCommandWaUlang.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'CommandBeriNotifUlang' => [
            'driver' => 'daily',
            'path' => storage_path('logs/CommandBeriNotifUlang.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'whatsapp_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/whatsapp_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'absensi_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/absensi_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'Wa_izin_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_izin_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'izin_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/izin_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'Wa_absensi_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_absensi_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'shift_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/shift_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'wa_shift_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_shift_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'lembur_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lembur_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'wa_lembur_error' => [
            'driver' => 'daily',
            'path' => storage_path('logs/Wa_lembur_error.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 60,
        ],
        'openaiError' => [
            'driver' => 'daily',
            'path' => storage_path('logs/openaiError.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],
        'review_log' => [
            'driver' => 'daily',
            'path' => storage_path('logs/review_log.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        /*
         * Tujuh kanal di bawah ini dirujuk Log::channel() di app/ tetapi tidak
         * pernah didefinisikan di sini. LogManager melempar
         * InvalidArgumentException untuk kanal yang tak dikenal, dan seluruh
         * pemanggilnya berada di jalur galat -- sebelas di dalam blok catch.
         * Akibatnya penanganan galat aslinya digantikan lemparan tentang
         * logging, dan sebab sesungguhnya tak pernah tercatat.
         *
         * 'ubahApproval' sebelumnya hanya ada di config/logging-salah.php, yang
         * dimuat Laravel sebagai config('logging-salah') -- bukan sebagai kanal.
         */
        'KPI_Master_Satuan' => [
            'driver' => 'daily',
            'path' => storage_path('logs/KPI_Master_Satuan.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'LmsPenjadwalanTrainingController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/LmsPenjadwalanTrainingController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'PenjadwalanController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/PenjadwalanController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'absenLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/absenLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'lmsLog' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lmsLog.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'satuanKpiController' => [
            'driver' => 'daily',
            'path' => storage_path('logs/satuanKpiController.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'ubahApproval' => [
            'driver' => 'daily',
            'path' => storage_path('logs/ubahApproval.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 30,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
