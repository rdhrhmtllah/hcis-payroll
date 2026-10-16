<?php

return [
    /*
    | Kill-switch. Saat true, RequestDiagnostics memasang DB::listen (murah) &
    | HANYA menulis catatan bila request lambat/5xx. Set false utk matikan total
    | tanpa deploy (env REQUEST_DIAGNOSTICS_ENABLED=false).
    */
    'enabled' => (bool) env('REQUEST_DIAGNOSTICS_ENABLED', true),

    /*
    | Ambang "request menarik". Catatan hanya ditulis bila Http_Status >= 500
    | ATAU total_ms >= slow_ms. Request normal cepat = 0 tulis.
    */
    'slow_ms' => (int) env('REQUEST_DIAGNOSTICS_SLOW_MS', 3000),

    /*
    | Sink 1: channel log (stderr = tahan saat DB down, tampil di Cloud Logging).
    | Boleh >1 channel.
    */
    'channels' => ['stderr'],

    /*
    | Sink 2: tabel N_HRIS_Request_Diagnostics (queryable utk tren). Tulis tabel
    | selalu di-try/catch; bila DB down, sink stderr tetap dapat.
    */
    'table' => (bool) env('REQUEST_DIAGNOSTICS_TABLE', true),

    /*
    | Simpan SQL query terlambat (parameterized, TANPA bindings → tanpa PII).
    */
    'capture_sql' => (bool) env('REQUEST_DIAGNOSTICS_CAPTURE_SQL', true),

    /*
    | Masa simpan baris diagnostik (hari), dipakai hris:diagnostik-prune. Tabel
    | ini hanya bertambah sampai command itu ada: di production ia sudah 71.000
    | baris. Nol pemangkasan berarti tiap agregat di /admin/activity memindai
    | tabel yang makin besar — pemantau yang ikut jadi beban yang dipantau.
    */
    'retention_days' => (int) env('REQUEST_DIAGNOSTICS_RETENTION_DAYS', 90),

    /*
    | Layer 2: tag sesi DB dgn Trace_Id via SET CONTEXT_INFO utk atribusi lock.
    | DEFAULT OFF karena menambah 1 round-trip DB per request. Nyalakan HANYA saat
    | sedang berburu lock aktif (env REQUEST_DIAGNOSTICS_TAG_SESSION=true).
    */
    'tag_session' => (bool) env('REQUEST_DIAGNOSTICS_TAG_SESSION', false),

    /*
    | Layer 3: command db:health-snapshot (blocking chain + wait DMV, out-of-band).
    | Tak menyentuh request user. Hanya menulis saat ADA blocking / request aktif
    | >= active_threshold.
    */
    'snapshot' => [
        'enabled' => (bool) env('DB_HEALTH_SNAPSHOT_ENABLED', true),
        'active_threshold' => (int) env('DB_HEALTH_SNAPSHOT_ACTIVE_THRESHOLD', 20),
        'table' => (bool) env('DB_HEALTH_SNAPSHOT_TABLE', true),
        'channels' => ['stderr'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layer 4: pemantauan lock REALTIME — HANYA untuk pengembangan.
    |--------------------------------------------------------------------------
    | Membaca DMV (sys.dm_exec_requests / dm_os_waiting_tasks / dm_tran_locks)
    | berulang-ulang. Di dev itu murah dan tak terasa. Di PRODUKSI ia merusak:
    | tiap sampel memakai satu koneksi dari pool yang sama yang sedang habis,
    | jadi ia menambah beban tepat pada saat sistemnya paling rapuh — persis
    | keadaan yang sedang ia coba jelaskan.
    |
    | Karena itu gerbangnya TIGA KUNCI dan ketiganya harus setuju
    | (lihat GerbangDiagnostikRealtime):
    |
    |   1. app.env  ada di dalam 'lingkungan' di bawah
    |   2. 'enabled' bernilai true — DEFAULT MATI, harus dinyalakan sengaja
    |   3. host DB TIDAK cocok satu pun pola di 'host_terlarang'
    |
    | Kunci ketiga ada karena dua yang pertama sama-sama env var: satu salah
    | set saat deploy sudah cukup meruntuhkan keduanya sekaligus. Host DB
    | datang dari sumber yang berbeda, jadi ia penahan yang benar-benar lain.
    */
    'realtime' => [
        'enabled' => (bool) env('DB_REALTIME_WATCH_ENABLED', false),

        'lingkungan' => ['local', 'development', 'dev', 'testing'],

        // Dicocokkan sebagai substring, huruf besar-kecil diabaikan.
        'host_terlarang' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'DB_REALTIME_WATCH_HOST_TERLARANG',
                'prod,production,cloudsql,/cloudsql/',
            )),
        ))),

        // Jarak antar-sampel pemantau berkelanjutan, dalam detik.
        'interval_detik' => (int) env('DB_REALTIME_WATCH_INTERVAL', 2),

        // Plafon baris per pembacaan, supaya satu sampel tak pernah membengkak.
        'maks_baris' => (int) env('DB_REALTIME_WATCH_MAX_ROWS', 200),
    ],
];
