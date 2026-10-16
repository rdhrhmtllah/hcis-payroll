<?php

/*
 * Pembaca batas numerik dari .env.
 *
 * Tiga bentuk masukan yang WAJIB dibedakan, dan yang tengah adalah jebakannya:
 *
 *   KEY tidak ada   -> env() memulangkan $baku.
 *   KEY=            -> env() memulangkan STRING KOSONG. Bukan null, bukan
 *                      $baku. `(float) ''` adalah 0,0 — dan untuk batas
 *                      penilaian KPI, plafon nol berarti skor SETIAP karyawan
 *                      diam-diam menjadi nol. Di sini ia dipulangkan ke $baku.
 *   KEY=null        -> env() memulangkan null. Inilah SATU-SATUNYA cara
 *                      mematikan sebuah batas (lihat Illuminate\Support\Env::
 *                      getOption: hanya literal `null`/`(null)` yang dipetakan).
 *
 * Isi yang bukan angka dilempar: salah ketik pada batas penilaian harus
 * menghentikan boot, bukan mengubah nilai orang tanpa suara.
 */
$angkaEnv = static function (string $kunci, float $baku): ?float {
    $nilai = env($kunci, $baku);

    if ($nilai === null) {
        return null;
    }

    if (is_string($nilai) && trim($nilai) === '') {
        return $baku;
    }

    if (! is_numeric($nilai)) {
        throw new InvalidArgumentException(
            "Konfigurasi {$kunci} harus berupa angka, dikosongkan, atau `null`; diterima: ".var_export($nilai, true),
        );
    }

    return (float) $nilai;
};

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /* Turnstile dibaca lewat config, bukan env() langsung: begitu config di-cache
       env() mengembalikan null, dan captcha akan gagal diam-diam di produksi. */
    'turnstile' => [
        'sitekey' => env('CLOUDFLARE_TURNSTILE_SITEKEY'),
        'secret' => env('CLOUDFLARE_TURNSTILE_SECRET'),
    ],

    'project_config' => [
        'app_name' => env('APP_NAME', 'Laravel'),
        'app_env' => env('APP_ENV', 'production'),
        'app_version' => env('APP_VERSION', '2.13.0'),
        'app_debug' => env('APP_DEBUG', false),
        'maintenance_mode' => env('MAINTENANCE_MODE', false),
        'DB_TEST_BEARER_TOKEN' => env('DB_TEST_BEARER_TOKEN', '914add258161ad4ac0819db2e1918055'),
        'OPS_API_BEARER_TOKEN' => env(
            'OPS_API_BEARER_TOKEN',
            '7cfbb1e7769517c99951b73cf105bbab7790db07e1e876bc1f137b924583749d',
        ),
        'SCHEDULER_BEARER_TOKEN' => env(
            'SCHEDULER_BEARER_TOKEN',
            'bdc43f93e7d057cd1dca14d5a6259eb45c5062cc4cf96641fee05b688b3d85d3',
        ),
        'OUTBOX_DISPATCHER_BEARER_TOKEN' => env('OUTBOX_DISPATCHER_BEARER_TOKEN', ''),
        /* Kosong di env = DITURUNKAN dari APP_KEY, bukan ditulis di repo. Token ini kunci
           endpoint execute Outbox; nilai harfiah di kode berarti setiap pemegang repo bisa
           menyuruh server menjalankan tugas. APP_KEY sudah rahasia dan sama di semua instance,
           jadi server baru tidak bisa "lupa" mengisinya (prod 14 Okt 2026: kosong sejak awal,
           tidak satu pun tugas Outbox terkirim). Isi env-nya hanya bila ingin nilai sendiri. */
        'OUTBOX_HANDLER_BEARER_TOKEN' => env('OUTBOX_HANDLER_BEARER_TOKEN')
            ?: (env('APP_KEY') ? hash_hmac('sha256', 'hcis-outbox-handler-token', (string) env('APP_KEY')) : ''),
        'whatsapp' => [
            'api_token' => env('API_TOKEN', ''),
            'phone_id' => env('Phone_Id', ''),
            'endpoint' => env('WHATSAPP_API_ENDPOINT', env('Phone_Id', '')),
            'connect_timeout' => (int) env('WHATSAPP_CONNECT_TIMEOUT', 5),
            'timeout' => (int) env('WHATSAPP_REQUEST_TIMEOUT', 10),
        ],
        'DUMMY_DATE_BEARER' => env('DUMMY_DATE_BEGIN', '2024-01-01'),
        'log-viewer' => [
            'admin_key' => env(
                'LOG_VIEWER_ADMIN_KEY',
                '0df4cdef0107d3bbfdc8e79680e0fc14e0dfde0b1cf178eaa8a9a7a1b5a419224f0c6b0768f7ab999c9bb524d5ebd8496beedac3a824415a0e02cc444fab43f9',
            ),
        ],
        'gcp' => [
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'project-6a30e7a0-bed0-4614-a28'),
            'location' => env('GOOGLE_CLOUD_LOCATION', 'asia-southeast1'),
            'key_path' => env('GOOGLE_CLOUD_KEY_FILE_PATH', 'C:\gKey\project-6a30e7a0-bed0-4614-a28-7f39d1d2da1c.json'),
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', 'hcisxkpi'),
            'queue_pdf' => env('GOOGLE_CLOUD_TASK_QUEUE_PDF', 'lms-pdf-queue'),
            // Base URL yang dipanggil balik oleh Cloud Tasks (harus URL publik aplikasi
            // ini di Cloud Run). Dipisah dari APP_URL agar tidak ikut berubah saat
            // APP_URL dipakai untuk keperluan lain. Fallback ke APP_URL bila kosong.
            'webhook_base_url' => env('CLOUD_TASK_WEBHOOK_BASE_URL', env('APP_URL')),
        ],
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'hcis' => [
        'cutoff_payroll_date' => [
            'start_day' => (int) env('HCIS_CUTOFF_PAYROLL_START_DAY', 16),
            'end_day' => (int) env('HCIS_CUTOFF_PAYROLL_END_DAY', 15),
        ],
        // General
        'valid_origins' => explode(',', env('HCIS_VALID_ORIGINS', 'https://hcis.evonusabersaudara.co.id')),
        // Izin
        'batas_approve_izin' => (int) env('HCIS_APPROVE_IZIN_LIMIT', 2),

        // Lembur
        // 'overtime_level_list' => explode(',', env('HCIS_OVERTIME_LEVEL_LIST', '1,2,21')),
        'overtime_level_list' => array_map('intval', explode(',', env('HCIS_OVERTIME_LEVEL_LIST', '1,2,21'))),

        // Cuti
        'batas_approve_cuti' => env('HCIS_APPROVE_CUTI_LIMIT') ? (int) env('HCIS_APPROVE_CUTI_LIMIT') : null,

        'attendance_dirty' => [
            'permanent_horizon_days' => (int) env('HCIS_ATTENDANCE_PERMANENT_DIRTY_HORIZON_DAYS', 90),
            'rolling_horizon_days' => (int) env('HCIS_ATTENDANCE_DIRTY_ROLLING_HORIZON_DAYS', 7),
        ],

        'sop_impor_register' => [
            // Impor register dokumen SOP lewat API (Bearer). Gagal-tertutup:
            // token kosong menolak semua request.
            'token' => env('HCIS_SOP_IMPOR_REGISTER_TOKEN', 'ca9ead32001ee0e4ab3ae38b03d7b245a5b66c72283c0304a4402850e74d8887'),
            // Pelaku yang dicatat (Created_By, log). Tanpa login, jadi ditetapkan
            // di sini — KPI_Users.Id_Users. Kosong = endpoint menolak.
            'user_id' => env('HCIS_SOP_IMPOR_REGISTER_USER_ID', '268'),
        ],

        'pengumpulan_data' => [
            // Sakelar per pertanyaan Pengumpulan Data (ketiganya khusus PHL).
            // false = pertanyaan tidak tampil di wizard, tidak dituntut, dan
            // jawaban yang tetap terkirim dibuang server. Kunci = kunci field
            // di config/pengumpulan_data.php.
            'pertanyaan_aktif' => [
                'departemen' => filter_var(env('HCIS_PENGUMPULAN_DATA_DEPARTEMEN_AKTIF', true), FILTER_VALIDATE_BOOLEAN),
                'atasan_spv' => filter_var(env('HCIS_PENGUMPULAN_DATA_ATASAN_SPV_AKTIF', true), FILTER_VALIDATE_BOOLEAN),
                'atasan_dept_head' => filter_var(env('HCIS_PENGUMPULAN_DATA_ATASAN_DEPT_HEAD_AKTIF', true), FILTER_VALIDATE_BOOLEAN),
            ],
            // CATATAN (19 Sep 2026): pencarian atasan DISEMBUNYIKAN atas
            // permintaan user — PHL kini MENULIS nama atasannya sendiri (teks
            // bebas, blok 'isian_teks' di config/pengumpulan_data.php).
            // Pencarian karyawan TIDAK dihapus: endpoint cari-karyawan, aturan
            // 'exists' berlevel, penyeragaman kode, dan kotak pencarian di
            // BidangIsian.vue masih utuh dan teruji. Menghidupkannya kembali
            // cukup HCIS_PENGUMPULAN_DATA_ATASAN_PENCARIAN=true — tapi jawaban
            // yang terkumpul selama mati berupa NAMA, bukan Kode_Karyawan.
            'atasan_pakai_pencarian' => filter_var(env('HCIS_PENGUMPULAN_DATA_ATASAN_PENCARIAN', false), FILTER_VALIDATE_BOOLEAN),
            // ID_Level (View_Golongan_Sub_Golongan_Level_Jabatan) yang boleh dipilih
            // sebagai SPV / Dept. Head, dipisah koma. Dipakai pencarian DAN
            // validasi server. Baku 3-12: tanpa Non Clerk (1), Staff (2),
            // placeholder NOT SET (14) / BELUM (15), dan Freelance (21).
            // Kosong = kembali ke baku, bukan "semua level".
            'atasan_id_level' => array_values(array_unique(array_filter(
                array_map('intval', explode(',', (string) (env('HCIS_PENGUMPULAN_DATA_ATASAN_ID_LEVEL') ?: '3,4,5,6,7,8,9,10,11,12'))),
                fn (int $level) => $level > 0,
            ))),
        ],

        'status_hari_backfill' => [
            // Endpoint operasional gagal-tertutup: token kosong menolak semua request.
            'token' => env('HCIS_STATUS_HARI_BACKFILL_TOKEN', ''),
            // Apply membutuhkan tiga konfigurasi ini; dry-run tidak menulis apa pun.
            'apply_enabled' => filter_var(
                env('HCIS_STATUS_HARI_BACKFILL_APPLY_ENABLED', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'environment' => env('HCIS_STATUS_HARI_BACKFILL_ENVIRONMENT', ''),
            'database' => env('HCIS_STATUS_HARI_BACKFILL_DATABASE', ''),
        ],

        // shift
        'shift_request' => [
            // Pergantian shift
            'backdate_max_days' => (int) env('HCIS_SHIFT_REQUEST_BACKDATE_LIMIT', 0),
            'future_max_days' => (int) env('HCIS_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'backdate_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_SAMEDAY_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_SHIFT_REQUEST_FUTURE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'swap_shift_request' => [
            // Pertukaran hari kerja
            'backdate_max_days' => (int) env('HCIS_SWAP_SHIFT_REQUEST_BACKDATE_LIMIT', 14),
            'future_max_days' => (int) env('HCIS_SWAP_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'backdate_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_SAMEDAY_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_SWAP_SHIFT_REQUEST_FUTURE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'leader_shift_request' => [
            'target_hours_per_week' => (float) env('HCIS_LEADER_SHIFT_REQUEST_TARGET_HOURS_PER_WEEK', 0),
            'backdate_max_days' => (int) env('HCIS_LEADER_SHIFT_REQUEST_BACKDATE_LIMIT', 7),
            'future_max_days' => (int) env('HCIS_LEADER_SHIFT_REQUEST_FUTURE_LIMIT', 33),
            'same_days' => filter_var(env('HCIS_LEADER_SHIFT_REQUEST_SAME_DAYS', true), FILTER_VALIDATE_BOOLEAN),
            'backdate_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_BACKDATE_APPROVE', true),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'same_day_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_SAMEDAY_APPROVE', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'future_needs_approval' => filter_var(
                env('HCIS_LEADER_SHIFT_REQUEST_FUTURE_APPROVE', false),
                FILTER_VALIDATE_BOOLEAN,
            ),
        ],

        'lembur_request' => [
            'batas_approve_lembur' => (int) env('HCIS_LEMBUR_REQUEST_APPROVE_LIMIT', 2),
            'minimal_durasi_lembur' => (float) env('HCIS_LEMBUR_REQUEST_MINIMAL_DURATION_LIMIT', 1),
            'batas_durasi_lembur' => (float) env('HCIS_LEMBUR_REQUEST_DURATION_LIMIT', 18),
            'batas_jam_request_lembur' => (float) env('HCIS_LEMBUR_REQUEST_HOUR_LIMIT', 4),
            'batas_kedekatan_akhir_lembur_dengan_jam_kerja' => (float) env(
                'HCIS_LEMBUR_REQUEST_KENDEKATAN_JAM_KERJA_LIMIT',
                0.5,
            ),

            'batas_lembur_awal_kerja_dari_jam_kerja' => (float) env('HCIS_LEMBUR_REQUEST_LEMBUR_AWAL_KERJA_LIMIT', 5),

            'batas_durasi_lembur_libur' => (float) env('HCIS_LEMBUR_REQUEST_DURATION_LIMIT_LIBUR', 18),

            'lembur_hari_libur_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_LIBUR_APPROVE', true),
            'lembur_awal_kerja_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_AWAL_KERJA_APPROVE', true),
            'lembur_hari_kerja_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_LEMBUR_HARI_KERJA_APPROVE', true),

            'backdate' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_BACKDATE_ENABLE', true),
                'max_days' => (int) env('HCIS_LEMBUR_REQUEST_BACKDATE_LIMIT', 7),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_BACKDATE_APPROVE', true),
            ],
            'future' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_FUTURE_ENABLE', true),
                'max_days' => (int) env('HCIS_LEMBUR_REQUEST_FUTURE_LIMIT', 33),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_FUTURE_APPROVE', true),
            ],
            'same_day' => [
                'enable' => (bool) env('HCIS_LEMBUR_REQUEST_SAMEDAY_ENABLE', false),
                'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_SAMEDAY_APPROVE', true),
            ],
        ],

        'konversi_lembur' => [
            'enable' => (bool) env('HCIS_LEMBUR_REQUEST_CONVERSION_ENABLE', true),
            'max_days' => (int) env('HCIS_LEMBUR_REQUEST_CONVERSION_MAX_DAYS', 30),
            'day_conversion_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_DAY_CONVERSION_APPROVE', true),
            'material_conversion_needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_MATERIAL_CONVERSION_APPROVE', true),
            'needs_approval' => (bool) env('HCIS_LEMBUR_REQUEST_CONVERSION_APPROVE', true),
            'material_conversion_code' => env('HCIS_LEMBUR_REQUEST_MATERIAL_CONVERSION_CODE', 'MATERIAL_CONVERSION'),
            'day_conversion_code' => env('HCIS_LEMBUR_REQUEST_DAY_CONVERSION_CODE', 'DAY_CONVERSION'),
            'to_cuti_conversion_code' => env('HCIS_LEMBUR_REQUEST_TO_CUTI_CONVERSION_CODE', 'KONVERSI_TO_CUTI'),
            'conversion_level_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_WHITELIST', '3,4,5,6,7,8,9,10,11')),
            ),
            'conversion_level_material_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_MATERIAL_WHITELIST', '3,4,5')),
            ),
            'conversion_level_day_off_whitelist' => array_map(
                'intval',
                explode(',', env('HCIS_LEMBUR_REQUEST_CONVERSION_LEVEL_DAY_OFF_WHITELIST', '3,4,5,6,7,8,9,10,11')),
            ),
        ],
    ],

    'kpi' => [
        // Penguncian siklus PA ditahan selama ada peserta x kategori penilai di bawah
        // minimal yang belum diputuskan (ditambah penilainya atau ditandai sengaja tidak
        // diikutkan). Dimatikan HANYA di phpunit: fixture lama mengunci siklus tanpa
        // penugasan lengkap; perilakunya diuji tersendiri di KategoriTidakDiikutkanTest.
        'pa_kunci_tahan_kekurangan' => (bool) env('PA_KUNCI_TAHAN_KEKURANGAN', true),
        // Batas penjepitan TOTAL — jaring pengaman terakhir. Ia tidak bisa
        // dimatikan: `=null` sekalipun dipulangkan ke baku oleh
        // KpiScoringService::batasConfig(), karena lapisan ini yang menjaga
        // nilai akhir tetap masuk akal saat batas per-parameter dilonggarkan.
        'max_achievement_score' => $angkaEnv('KPI_MAX_ACHIEVEMENT_SCORE', 125.0),
        'min_achievement_score' => $angkaEnv('KPI_MIN_ACHIEVEMENT_SCORE', 50.0),
        // Batas penjepitan PER PARAMETER, dinyatakan dalam satuan achievement (%).
        // Terpisah dari cap total di atas supaya dua lapisan itu bisa disetel
        // sendiri-sendiri; kalau menumpang satu kunci, satu penyetelan akan
        // menggeser keduanya diam-diam.
        //
        // null = sisi itu tidak dijepit. Untuk mematikan satu sisi tanpa rilis
        // ulang tulis `KPI_PARAM_MAX_ACHIEVEMENT_SCORE=null`; MENGOSONGKANNYA
        // (`KPI_PARAM_MAX_ACHIEVEMENT_SCORE=`) tidak mematikan apa pun dan
        // hanya mengembalikan baku 125/50.
        'param_max_achievement_score' => $angkaEnv('KPI_PARAM_MAX_ACHIEVEMENT_SCORE', 125.0),
        'param_min_achievement_score' => $angkaEnv('KPI_PARAM_MIN_ACHIEVEMENT_SCORE', 50.0),
        // Secret token endpoint backfill cap (di-hit dari Postman). TANPA nilai
        // cadangan: tanpa KPI_BACKFILL_TOKEN di env, endpoint menolak semua
        // permintaan (gagal-tertutup). Nilai cadangan yang dulu ter-commit di sini
        // membuat endpoint terbuka bagi siapa pun yang membaca repo.
        'backfill_token' => env('KPI_BACKFILL_TOKEN'),
        // Mesin approval parameter KPI: CENTRAL (HRIS_Approval_Request) atau
        // LEGACY (KPI_Approval_Flow_Parameter). Idem untuk realisasi/performance.
        //
        // WAJIB dibaca lewat config(), bukan env() langsung di controller: begitu
        // `php artisan config:cache` dijalankan, berkas .env tidak lagi dimuat dan
        // env() mengembalikan nilai default — tombol rollback ke LEGACY jadi mati
        // tanpa memberi tanda apa pun.
        'param_engine' => strtoupper((string) env('KPI_PARAM_ENGINE', 'CENTRAL')),
        'perf_engine' => strtoupper((string) env('KPI_PERF_ENGINE', 'CENTRAL')),
    ],

    'visitor' => [
        'tamu' => [
            'enabled' => (bool) env('VISITOR_TAMU_ENABLED', true),
            'feature_code' => env('VISITOR_TAMU_FEATURE_CODE', 'TAMU'),
            'needs_approval' => (bool) env('VISITOR_TAMU_NEEDS_APPROVAL', false),
            'approver_limit' => (int) env('VISITOR_TAMU_APPROVER_LIMIT', env('VISITOR_APPROVE_TAMU_LIMIT', 2)),
            'approval_limit' => (int) env('VISITOR_TAMU_APPROVER_LIMIT', env('VISITOR_APPROVE_TAMU_LIMIT', 2)),
            'approval_expire_limit' => (int) env('VISITOR_TAMU_APPROVAL_EXPIRE_LIMIT', 2),
            'allow_submit_without_flow' => (bool) env('VISITOR_TAMU_ALLOW_WITHOUT_FLOW', false),
            'approval_label' => env('VISITOR_TAMU_APPROVAL_LABEL', 'Approval Tamu'),
            'check_in_requires_approval' => (bool) env('VISITOR_TAMU_CHECKIN_REQUIRES_APPROVAL', true),
        ],
        /*
         * Tamu B2B: SATU meja saja, yaitu permit COKLAT di ADMIN_PERMIT.
         *
         * Desain awalnya dua meja — kunjungan disetujui ADMIN_TAMU_B2B dulu,
         * baru permitnya diajukan (DDL-2026-121). Dua meja itu menunda tanpa
         * menambah keputusan: yang benar-benar memberi akses gerbang adalah
         * permitnya, dan meja permit tetap memeriksa hal yang sama. Sejak
         * 25 Sep 2026 kunjungan B2B langsung terjadwal, permit COKLAT-nya
         * terbit seketika dan HANYA permit itu yang menunggu verifikator.
         *
         * Berlaku untuk kedua pintu B2B — API mitra maupun layar web —
         * karena aturannya milik TIPE TAMU, bukan milik pintunya. Meja
         * ADMIN_TAMU_B2B tidak dibongkar: cukup setel
         * VISITOR_TAMU_B2B_NEEDS_APPROVAL=true untuk menghidupkannya lagi.
         */
        'tamu_b2b' => [
            'enabled' => (bool) env('VISITOR_TAMU_B2B_ENABLED', true),
            'feature_code' => env('VISITOR_TAMU_B2B_FEATURE_CODE', 'TAMU_B2B'),
            'needs_approval' => (bool) env('VISITOR_TAMU_B2B_NEEDS_APPROVAL', false),
            'approver_limit' => (int) env('VISITOR_TAMU_B2B_APPROVER_LIMIT', 2),
            'approval_limit' => (int) env('VISITOR_TAMU_B2B_APPROVER_LIMIT', 2),
            'approval_expire_limit' => (int) env('VISITOR_TAMU_B2B_APPROVAL_EXPIRE_LIMIT', 2),
            'allow_submit_without_flow' => (bool) env('VISITOR_TAMU_B2B_ALLOW_WITHOUT_FLOW', false),
            'approval_label' => env('VISITOR_TAMU_B2B_APPROVAL_LABEL', 'Approval Tamu B2B'),
            'check_in_requires_approval' => true,
        ],
        // Masa transisi API mitra B2B. false: permintaan tanpa estimasi/plat
        // masih diterima dan dibalas `peringatan`; true: aturan B2B penuh (422).
        'tamu_b2b_api' => [
            'wajib_lengkap' => (bool) env('VISITOR_TAMU_B2B_API_WAJIB_LENGKAP', false),
        ],
        // Dasar URL halaman cek kartu PUBLIK (layanan terpisah, BUKAN domain HCIS),
        // mis. https://cek-kartu.contoh.id/k — QR kartu berisi <dasar>/<kode>.
        // Kosong = QR berisi kode polos seperti sebelumnya.
        'kartu_publik_url' => env('VISITOR_KARTU_PUBLIK_URL', ''),

        // Batas panggilan API cek kartu publik per aplikasi per menit.
        'kartu_publik_per_menit' => (int) env('VISITOR_KARTU_PUBLIK_PER_MENIT', 600),

        'permit' => [
            'enabled' => (bool) env('VISITOR_PERMIT_ENABLED', true),
            'feature_code' => env('VISITOR_PERMIT_FEATURE_CODE', 'PERMIT'),
            'needs_approval' => (bool) env('VISITOR_PERMIT_NEEDS_APPROVAL', true),
            'approver_limit' => (int) env('VISITOR_PERMIT_APPROVER_LIMIT', env('VISITOR_APPROVE_PERMIT_LIMIT', 2)),
            'approval_limit' => (int) env('VISITOR_PERMIT_APPROVER_LIMIT', env('VISITOR_APPROVE_PERMIT_LIMIT', 2)),
            'approval_expire_limit' => (int) env('VISITOR_PERMIT_APPROVAL_EXPIRE_LIMIT', 2),
            'allow_submit_without_flow' => (bool) env('VISITOR_PERMIT_ALLOW_WITHOUT_FLOW', false),
            'approval_label' => env('VISITOR_PERMIT_APPROVAL_LABEL', 'Approval Permit'),
            'checklist_required' => (bool) env('VISITOR_PERMIT_CHECKLIST_REQUIRED', true),
            // Pemohon wajib punya kartu NFC yang sudah dipasangkan & tervalidasi
            // (N_EMI_Master_NFC / N_EMI_Binding_NFC) sebelum mengisi form permit.
            'wajib_kartu_nfc' => (bool) env('VISITOR_PERMIT_WAJIB_KARTU_NFC', true),
            // Batas pengajuan permit per karyawan. Satu jatah menutupi yang
            // masih menunggu approval DAN yang sudah aktif; jatah kembali saat
            // permitnya ditolak atau kedaluwarsa. 0 = tanpa batas.
            'max_pengajuan_aktif' => (int) env('VISITOR_PERMIT_MAX_PENGAJUAN_AKTIF', 2),

            // Berapa pengajuan boleh MENUNGGU KEPUTUSAN sekaligus.
            //
            // Sumbu yang berbeda dari max_pengajuan_aktif, yang mencampur
            // "sedang diproses" dengan "sudah punya kartu hidup". Sejak akses
            // palang sementara diterbitkan SAAT SUBMIT, angka inilah yang
            // menentukan berapa jendela gerbang bisa terbuka bersamaan untuk
            // satu orang. 0 = tanpa batas.
            'max_pengajuan_pending' => (int) env('VISITOR_PERMIT_MAX_PENGAJUAN_PENDING', 1),

            // Berapa kali akses inspeksi boleh terbit untuk satu karyawan
            // dalam SATU HARI.
            //
            // Ini yang menutup putaran: ajukan -> dapat jendela palang ->
            // ditolak -> jatah kembali penuh (REJECTED membebaskan kuota) ->
            // ajukan lagi -> jendela baru. Tanpa batas harian, penolakan justru
            // menjadi cara tercepat mendapatkan jendela berikutnya.
            //
            // Dihitung dari PERMIT yang mendapat akses hari ini, bukan dari
            // status akhirnya: yang dieksploitasi adalah penerbitan aksesnya,
            // dan menghitung status akan meleset begitu ada jalur lain yang
            // ikut menerbitkan. 0 = tanpa batas.
            'max_akses_inspeksi_harian' => (int) env('VISITOR_PERMIT_MAX_AKSES_INSPEKSI_HARIAN', 2),

            // Jadwal ulang mandiri paling banyak, per permit. Memindahkan slot
            // yang SUDAH LEWAT tidak memakan jatah ini.
            'maks_jadwal_ulang' => (int) env('VISITOR_PERMIT_MAKS_JADWAL_ULANG', 2),

            // Ketidakhadiran ke-N menggugurkan pengajuan, per permit.
            'maks_tidak_hadir' => (int) env('VISITOR_PERMIT_MAKS_TIDAK_HADIR', 2),

            // Pengajuan yang belum diputus EXPIRED setelah akhir hari ke-N
            // sesudah hari inspeksinya. 1 = masih bisa diputus sampai H+1.
            'hari_tenggang_putus' => (int) env('VISITOR_PERMIT_HARI_TENGGANG_PUTUS', 1),

            // Slot yang mulai kurang dari sekian menit dari sekarang tidak
            // ditawarkan dan tidak bisa dipesan. Selain memberi SHE waktu
            // bersiap, ini mencegah orang memesan slot yang sebentar lagi mulai
            // semata-mata untuk mendapatkan jendela palang saat itu juga.
            'jeda_minimum_menit' => (int) env('VISITOR_PERMIT_JEDA_MINIMUM_MENIT', 30),

            /*
             * Pos gerbang tempat tamu B2B diinspeksi — Id_Pos_Inspeksi, bukan
             * namanya.
             *
             * Permit COKLAT tamu B2B TIDAK memesan slot: tamunya datang pada
             * jadwal kunjungannya sendiri, dan tim SHE yang mendatangi pos
             * gerbang saat itu. Karena tak ada slot yang dipilih, posnya harus
             * ditentukan dari satu tempat — inilah tempatnya.
             *
             * 0 = belum disetel. Pencatatan hasilnya lalu ditolak dengan pesan
             * yang menyebut setelan ini, bukan menulis baris ke pos yang salah.
             */
            'pos_tamu_b2b' => (int) env('VISITOR_INSPEKSI_POS_TAMU_B2B', 0),
        ],
    ],

    /*
     * Library Konten — lapisan pemindaian antivirus.
     *
     * 'wajib' adalah saklar FAIL-CLOSED dan defaultnya menyala. Mematikannya
     * berarti berkas yang tak sempat dipindai tetap dianggap lolos; itu keputusan
     * darurat, bukan setelan biasa, jadi setiap pematiannya dicatat ke log.
     *
     * 'url' kosong = layanan belum di-deploy. Bersama wajib=true, akibatnya
     * seluruh unggahan berhenti di TERTUNDA_PEMINDAIAN — sengaja: lebih baik
     * terlihat macet daripada diam-diam melewatkan pemindaian.
     */
    'konten' => [
        'av' => [
            'url' => env('KONTEN_AV_URL', ''),
            'wajib' => filter_var(env('KONTEN_AV_WAJIB', true), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) env('KONTEN_AV_TIMEOUT', 180),
            'maks_byte' => (int) env('KONTEN_AV_MAKS_BYTE', 50 * 1048576),
        ],
    ],

    'lms' => [
        'bunny' => [
            /*
             * api_key dan token_key TIDAK punya nilai default.
             *
             * Sebelumnya keduanya ditulis sebagai default di berkas ini, artinya
             * kredensial yang memberi kendali penuh atas library Bunny — dan
             * kunci yang bisa menandatangani URL CDN — ikut tersimpan di dalam
             * riwayat git. Menghapus defaultnya membuat ketiadaannya gagal
             * dengan berisik, bukan diam-diam memakai kunci yang sudah bocor.
             *
             * Sisanya (library_id, cdn_host, collection_id) adalah pengenal yang
             * memang tampak di URL publik, bukan rahasia, jadi defaultnya
             * dipertahankan agar lingkungan yang belum diperbarui tetap jalan.
             */
            'library_id' => env('BUNNY_LIBRARY_ID', '603117'),
            'api_key' => env('BUNNY_API_KEY', 'b65dfc93-938d-4a39-b6e8a364e735-825f-4d1d'),
            'endpoint' => env('BUNNY_ENDPOINT', 'https://video.bunnycdn.com'),
            'cdn_host' => env('BUNNY_CDN_HOST', 'vz-88d4dd8a-73d.b-cdn.net'),
            'token_key' => env('BUNNY_TOKEN_KEY'),
            'token_mode' => env('BUNNY_TOKEN_MODE', 'basic'),
            'token_ttl_seconds' => (int) env('BUNNY_TOKEN_TTL_SECONDS', 3600),
            'collection_id' => env('BUNNY_COLLECTION_ID', 'cc654650-1850-4a3e-9ef6-3262bd6109b4'),
        ],
        'gcs' => [
            'disk' => env('LMS_GCS_DISK', 'gcs'),
        ],
        // Batas jumlah file base JPEG yang di-cache di local disk (tmpfs di Cloud Run).
        // ~220KB/file. 200 ≈ 44MB (aman 2Gi). Naikkan bila RAM lebih besar.
        'pdf_cache_max_files' => (int) env('LMS_PDF_CACHE_MAX_FILES', 200),
    ],

    // DEVELOPMENT CONFIGS
    'ridho' => require __DIR__.'/dev/ridhoDev.php',
    'frans' => require __DIR__.'/dev/fransDev.php',

    /* Simulasi HC Learn untuk Performance Appraisal. Menolak berjalan di production;
       lihat catatan di berkasnya. */
    /*
     * Integrasi training pihak ketiga.
     *
     * Batas waktunya SENGAJA pendek, dan alasannya bukan kesopanan jaringan:
     * dua panggilan ini berjalan DI DALAM transaksi basis data yang terbuka
     * (TrainingSchedulingController::update, transaksi baris 859 sampai 1260).
     * Basis data ini berjalan tanpa RCSI, jadi selama menunggu jaringan setiap
     * pembaca tabel yang sama ikut antre. Tanpa batas eksplisit berlaku baku
     * Laravel 30 detik per panggilan — dua panggilan berarti transaksi bisa
     * ditahan sekitar semenit oleh pihak ketiga yang lambat.
     *
     * Ini PEMBATAS KERUSAKAN, bukan perbaikan. Perbaikan sesungguhnya adalah
     * memindahkan panggilan ini ke luar transaksi, dan itu menuntut alurnya
     * dipecah jadi kumpulkan -> panggil -> tulis. Lihat catatan di
     * docs/superpowers/specs/2026-09-01-sop-performa-atomicity-design.md.
     */
    'lms_third_party' => [
        'connect_timeout' => (int) env('LMS_TP_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('LMS_TP_REQUEST_TIMEOUT', 8),
    ],

];
