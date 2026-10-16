<?php

return [
    'aliases' => [
        'pages' => [
            'superadminmanajemenperanpage' => 'superAdminManajemenPeranPage',
            'systemauthmanagerpage' => 'systemAuthManagerPage',
            'system-manager' => 'systemAuthManagerPage',
            'role-manager' => 'superAdminManajemenPeranPage',
        ],
        'resources' => [
            'system-manager' => 'systemAuthManagerPage',
            'role-manager' => 'superAdminManajemenPeranPage',
        ],
        'actions' => [
            'view' => 'VIEW',
            'create' => 'CREATE',
            'insert' => 'CREATE',
            'edit' => 'EDIT',
            'update' => 'EDIT',
            'delete' => 'DELETE',
            'assign' => 'ASSIGN',
            'delegate' => 'DELEGATE',
            'approve' => 'APPROVE',
        ],
    ],

    // Only pages listed here use strict resource/action registration.
    // Legacy pages continue using their existing compatibility path.
    'governed_pages' => [
        'systemAuthManagerPage',
        'menuApprovalMatrixPage',
    ],

    'resource_access' => [
        'default_context' => 'admin',
        'default_page' => 'systemAuthManagerPage',
        'default_tab_codes' => ['dashboard', 'users', 'master', 'governance', 'distribution', 'matrix', 'monitoring'],
    ],

    'resource_registry' => [
        'definitions' => [
            'systemAuthManagerPage' => [
                'tab' => [
                    'dashboard' => 'Dashboard',
                    'users' => 'User & Roles',
                    'master' => 'Master Data',
                    'governance' => 'Module Governance',
                    'monitoring' => 'Monitoring',
                    'distribution' => 'Access Distribution',
                    'matrix' => 'Permission Matrix',
                ],
            ],
            'menuApprovalMatrixPage' => [
                'tab' => [
                    'menu' => 'Tab Menu',
                    'approval-matrix' => 'Tab Approval Matrix',
                    'membership' => 'Tab Keanggotaan',
                ],
                'capability' => [
                    'MENU_ACCESS.ASSIGN' => 'Assign Menu',
                    'APPROVAL_MATRIX.ASSIGN' => 'Assign Approval',
                    'MEMBERSHIP.ASSIGN' => 'Assign Keanggotaan',
                    'MEMBERSHIP.CONFIGURE' => 'Konfigurasi Keanggotaan',
                ],
            ],
        ],
    ],

    /*
     * RBAC Fase 0 (spec 2026-10-09-rbac-akses-peran-design.md): halaman yang
     * memberi/mengatur akses orang lain. Untuk halaman ini, hibah role
     * (N_HRIS_Grup_Fitur_Map.Flag_Hibah_Role) hanya berlaku bagi role admin
     * sah di bawah. Grant langsung (HRIS_Page_Access) dan System Admin tidak
     * terpengaruh. Tambah halaman/role di sini tanpa mengubah kode.
     */
    'tata_kelola' => [
        'halaman' => [
            'adminAllPagesAccess',
            'superAdminManajemenPeranPage',
            'systemAuthManagerPage',
            'menuApprovalMatrixPage',
            'menuApprovalMatrixEssPage',
            'menuApprovalMatrixKpiPage',
            'menuApprovalMatrixLmsPage',
            'menuApprovalMatrixVisitorPage',
            'ManajemenAksesDivisi',
            'ManajemenAksesLevel',
            'ActivityMonitor',
        ],
        'peran_admin' => [
            'ESS_MENU_ADMINISTRATOR',
            'ESS_APPROVAL_ADMINISTRATOR',
            'ESS_MEMBERSHIP_ADMINISTRATOR',
            'ESS_MENU_APPROVAL_ADMINISTRATOR',
            'ESS_MENU_ADMINISTRATOR_PHL',
            'ESS_APPROVAL_ADMINISTRATOR_PHL',
            'KPI_SUPER_ADMIN',
            'KPI_ADMIN_PHL',
            'KPI_MENU_ADMINISTRATOR',
            'KPI_APPROVAL_ADMINISTRATOR',
            'KPI_MEMBERSHIP_ADMINISTRATOR',
            'KPI_MENU_APPROVAL_ADMINISTRATOR',
            'LMS_MENU_ADMINISTRATOR',
            'LMS_APPROVAL_ADMINISTRATOR',
            'LMS_MENU_APPROVAL_ADMINISTRATOR',
            'VISITOR_MENU_ADMINISTRATOR',
            'VISITOR_APPROVAL_ADMINISTRATOR',
            'VISITOR_MENU_APPROVAL_ADMINISTRATOR',
            // Dibuat langsung berjenis ADMIN: DML-2026-180, DML-2026-182, DML-2026-185, DML-2026-187
            'DMS_ADMIN',
            'VISITOR_ADMIN',
            'LMS_ADMIN',
            'KPI_ADMIN',
            'ESS_ADMIN',
            'ESS_SUPER_ADMIN',
            'LMS_SUPER_ADMIN',
            'VISITOR_SUPER_ADMIN',
            'DMS_SUPER_ADMIN',
        ],
    ],

    // RBAC Fase 4: semua jalur keputusan halaman lewat KeputusanAkses, jadi
    // mode BARU dibuka. false = rem darurat: modul BARU kembali diputuskan aturan
    // lama (berlaku sebagai BAYANGAN) dan System Admin tak bisa memilih BARU.
    'mode_baru_diizinkan' => env('RBAC_MODE_BARU_DIIZINKAN', true),

    'cache' => [
        'cross_request_enabled' => true,
        'ttl_seconds' => 60,
    ],

    'audit' => [
        'enabled' => true,
        'db_enabled' => false,
        'channel' => 'stack',
    ],
];
