# DML-2026-010 — Daftarkan DDL-2026-009 ke Database Change Dashboard

Satu baris `INSERT` ke `N_SYSTEM_Database_Change` supaya
`DDL-2026-009-create-report-template-tables` terlihat di dashboard SYSTEM.

## Kenapa perlu langkah terpisah

Repo ini punya **dua** catatan perubahan database, dan keduanya tidak saling
mengisi:

| Catatan | Dibaca oleh |
|---|---|
| `database/ddl/status.json` | manusia dan agent, lewat repo |
| `N_SYSTEM_Database_Change` | Database Change Dashboard, lewat aplikasi |

Menulis DDL dan mengisi `status.json` **tidak** membuat perubahan muncul di
dashboard. Pendaftarannya adalah DML tersendiri — pola yang sama dipakai
`DML-2026-002-register-system-dashboard-change`.

## Eksekusi

Dijalankan di `Web_HRIS` (development) pada 2026-08-18. Baris sebelum: 1,
sesudah: 2, delta 1 — sesuai perkiraan. `INSERT` dijaga `IF NOT EXISTS` pada
`Kode_Change`, jadi aman dijalankan ulang.

## Yang TIDAK dikerjakan di sini

Delapan perubahan lain di `status.json` juga belum terdaftar di dashboard:
`DDL-2026-001`, `DML-2026-003`, `DML-2026-004`, `DDL-2026-005`, `DDL-2026-006`,
`DDL-2026-007`, `DML-2026-008`. Selisih itu sudah ada sebelum pekerjaan ini dan
di luar cakupannya; memperbaikinya adalah perubahan data massal yang butuh
instruksi eksplisit tersendiri.
