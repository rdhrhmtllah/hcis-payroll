<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Employee target scope rollout
    |--------------------------------------------------------------------------
    | Tetap false sampai seluruh role admin lama lolos endpoint readiness.
    | Role yang sudah memiliki scope selalu dibatasi walaupun flag masih false.
    */
    'enforce_target_scope' => env('MENU_MATRIX_ENFORCE_TARGET_SCOPE', false),
    /*
    |--------------------------------------------------------------------------
    | Whitelist source table assignment approver
    |--------------------------------------------------------------------------
    | Hanya tabel di daftar ini yang boleh diedit dari halaman Approval Matrix
    | (assignment requester -> approver). Nama tabel TIDAK PERNAH diterima dari
    | client; service me-resolve dari N_HRIS_Jenis_Approval.Source_Reference
    | lalu memvalidasi terhadap whitelist ini + cek kolom via Schema.
    |
    | Dialek kolom mengikuti ApproverResolverService:
    | - TABLE     : approver = Kode_Karyawan,          pk = id
    | - KPI_TABLE : approver = Kode_Karyawan_Approver, pk = Id_Approval_Flow
    | Keduanya: Kode_Karyawan_Requester, order_flow, Status (aktif = Status IS NULL).
    */
    'editable_source_tables' => [
        'HRIS_Approval_Flow',
        'HRIS_HC_Approval',
        'N_HRIS_Approval_Flow_Shift',
        'KPI_Approval_Flow_Parameter',
        'KPI_Approval_Flow_Performance',
    ],

    // Source_Type yang assignment-nya bisa dikelola dari UI matrix.
    'editable_source_types' => ['TABLE', 'KPI_TABLE'],

    // Maksimal level approver per requester dalam satu step.
    'max_order_flow' => 10,
];
