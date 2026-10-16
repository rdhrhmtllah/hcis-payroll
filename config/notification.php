<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy source fallback (Meta_JSON -> JSON_VALUE)
    |--------------------------------------------------------------------------
    | Saat true, filter "source" pada query notifikasi akan menyertakan cabang
    | JSON_VALUE(Meta_JSON, '$.source') untuk membaca notifikasi lama yang kolom
    | `source`-nya masih NULL/kosong.
    |
    | JSON_VALUE di klausa WHERE TIDAK bisa memakai index -> full scan tiap
    | request (query ini jalan di shell pada SETIAP halaman). Setelah kolom
    | `source` di-backfill (lihat problem/fix-notification-perf.sql), biarkan
    | false agar query memakai index IX_Notification_Unread_Shell.
    |
    | Nyalakan SEMENTARA (true) hanya saat masa transisi sebelum backfill selesai.
    */
    'legacy_source_fallback' => (bool) env('NOTIFICATION_LEGACY_SOURCE_FALLBACK', false),
];
