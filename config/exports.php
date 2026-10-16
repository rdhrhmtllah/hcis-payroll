<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registry Modul Export (Pusat Aktivitas)
    |--------------------------------------------------------------------------
    |
    | Memetakan kolom `Module` di tabel N_HRIS_Async_Tasks ke service export-nya.
    | ActivityCenterController memakai ini untuk meresolusi service (download,
    | retry, dismiss) tanpa hardcode per modul.
    |
    | Menambah modul export baru:
    |   1. Buat service `extends AbstractExportService` + implement dispatchJob().
    |   2. Buat Job-nya dengan onQueue(config('queue.names.async_export')).
    |   3. Daftarkan 1 baris di sini: '<module>' => <Service>::class.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Plafon Baris Export Studio
    |--------------------------------------------------------------------------
    |
    | Berkas laporan Studio bergrain karyawan x paket x item, dan template resmi
    | 'rinci' memilih 27 kolom tanpa saringan wajib. Tanpa plafon, satu klik bisa
    | menarik seluruh tabel ke memori worker (timeout 300 dtk, tries 1) dan XLSX
    | sendiri mentok di 1.048.576 baris.
    |
    | Pemotongan TIDAK pernah senyap: ReportRunner menuliskannya di 'catatan',
    | yang ikut masuk ke berkas CSV/XLSX maupun ke layar pratinjau.
    |
    */

    'report_max_rows' => (int) env('REPORT_MAX_ROWS', 200000),

    // PhpSpreadsheet menyimpan setiap sel sebagai object. Plafon jumlah sel
    // menjaga worker 512 MB dari OOM; jumlah baris efektif dihitung otomatis
    // dari banyaknya kolom template. CSV tidak memakai plafon ini karena ditulis
    // streaming langsung dari cursor.
    'report_xlsx_max_cells' => (int) env('REPORT_XLSX_MAX_CELLS', 150000),

    // Worker export memiliki timeout 5 menit. Lease dua kali timeout memberi
    // ruang untuk shutdown/latency sebelum task processing boleh direbut retry.
    'processing_lease_minutes' => (int) env('EXPORT_PROCESSING_LEASE_MINUTES', 10),

    // Visitor punya dataset lebar dan dijalankan pada worker Cloud Run yang
    // sama dengan request aplikasi. Batas per format mencegah PhpSpreadsheet
    // menggandakan ratusan ribu baris di memori; CSV tetap lebih longgar.
    'visitor_report_max_rows' => [
        'csv' => (int) env('VISITOR_REPORT_MAX_ROWS_CSV', 50000),
        'xlsx' => (int) env('VISITOR_REPORT_MAX_ROWS_XLSX', 20000),
        'pdf' => (int) env('VISITOR_REPORT_MAX_ROWS_PDF', 200),
    ],

    // Dataset penugasan berbutir peserta x penilai: satu siklus 4.790 peserta
    // menghasilkan 7.308 penugasan sebelum saringan apa pun.
    'pa_report_max_rows' => [
        'csv' => (int) env('PA_REPORT_MAX_ROWS_CSV', 50000),
        'xlsx' => (int) env('PA_REPORT_MAX_ROWS_XLSX', 20000),
        'pdf' => (int) env('PA_REPORT_MAX_ROWS_PDF', 200),
    ],

    /*
    | Kuncinya WAJIB sama persis dengan moduleKey() milik service-nya: kolom
    | Module dicocokkan apa adanya. Lupa mendaftar tidak menggagalkan apa pun
    | sampai klik unduh — berkasnya tetap dibuat, ditandai 'ready', lalu
    | ActivityCenterController gagal meresolusi service-nya dan menjawab 404
    | "Export tidak ditemukan." Dijaga ExportModuleRegistryTest.
    */
    'modules' => [
        'kpi-export' => \App\Services\KPI\KpiExportService::class,
        'lms-training' => \App\Services\LMS\LmsTrainingExportService::class,
        'menu-karyawan-matrix' => \App\Services\SYSTEM\Governance\Authorization\Menu\MenuKaryawanMatrixExportService::class,
        'pengumpulan-data-export' => \App\Services\ESS\Employee\DataCollection\PengumpulanDataExportService::class,
        'sop-compliance' => \App\Services\LMS\SOP\SopComplianceExportService::class,
        'sop-studio' => \App\Services\LMS\SOP\Export\SopReportExportService::class,
        'visitor-studio' => \App\Services\VISITOR\Workflow\Reporting\Export\VisitorReportExportService::class,
        'pa-studio' => \App\Services\KPI\PerformanceAppraisal\Export\PaReportExportService::class,
        'relasi-organisasi-export' => \App\Services\SYSTEM\Organization\OrganizationRelationAsyncExportService::class,
        'monitoring-kehadiran-rekap' => \App\Services\ESS\Attendance\Monitoring\RekapKehadiranExportService::class,
    ],

];
