# DDL-2026-005 — Create SYSTEM Task Outbox

## Status

- Development: `DONE`
- SQL Tracker: `PENDING`
- Production: `PENDING`
- Verification: `DEV_DONE`

## Ownership

- Module: `SYSTEM`
- Submodule: `TaskProcessing`
- Feature: `TaskOutbox`

## Purpose

`N_SYSTEM_Task_Outbox` menyimpan durable task intent untuk pekerjaan asynchronous yang tidak boleh hilang setelah transaction bisnis berhasil commit. Tabel ini bukan pengganti `N_HRIS_Cloud_Task_Log`.

## Separation of responsibility

- `N_SYSTEM_Task_Outbox`: intent yang harus dikirim, idempotency, retry scheduling, dan recovery dispatch.
- `N_HRIS_Cloud_Task_Log`: operational log task yang sudah dikirim/diproses, hasil, durasi, dan error handler.

## Lifecycle

```text
QUEUED -> DISPATCHING -> DISPATCHED -> PROCESSING -> COMPLETED
                         |                         |
                         +-> RETRY_PENDING <-------+
                         +-> FAILED
```

Status divalidasi di application/service layer; tidak memakai rigid database CHECK agar dapat berkembang.

## Column contract

- `Id_Task_Outbox`: identity primary key.
- `Task_Type`: tipe use case/task.
- `Aggregate_Type`, `Aggregate_Id`: referensi entity bisnis tanpa foreign key polymorphic.
- `Idempotency_Key`: kunci unik untuk mencegah intent duplicate.
- `Queue_Name`, `Task_Url`, `Payload`: data dispatch. Payload tidak boleh berisi credential atau secret.
- `Status`: lifecycle outbox.
- `Attempt_Count`, `Max_Attempts`, `Available_At`: retry scheduling.
- `Locked_At`, `Locked_By`: claim/lease dispatcher.
- `Dispatched_At`, `Cloud_Task_Name`: hasil dispatch.
- `Last_Error`: error terakhir yang sudah disanitasi.
- `Created_*`, `Updated_*`: audit actor dan waktu UTC dari database.

## Rules

1. Outbox row dibuat dalam transaction yang sama dengan perubahan bisnis inti.
2. Dispatcher hanya boleh claim row yang `QUEUED` atau `RETRY_PENDING` dan `Available_At` sudah jatuh tempo.
3. Claim harus singkat dan tidak boleh menahan lock saat memanggil Google Cloud Tasks.
4. API Cloud Tasks dipanggil setelah claim transaction commit.
5. Dispatch berhasil mengubah outbox menjadi `DISPATCHED`; gagal menjadi `RETRY_PENDING` atau `FAILED` sesuai attempt policy.
6. Handler tetap wajib idempotent; outbox tidak menjamin exactly-once execution.
7. Tidak ada production execution oleh agent.

## SQL artifacts

- Forward: `database/ddl/DDL-2026-005-create-system-task-outbox/forward.sql`
- Manual cleanup only: `database/ddl/DDL-2026-005-create-system-task-outbox/drop.sql`
- Documentation: `docs/changes/DDL-2026-005-create-system-task-outbox/README.md`

## Development execution

- Target: `Web_HRIS`
- Result: table created and verified
- Columns: `21`
- Initial rows: `0`
- Indexes: `4`
- SQL Tracker: `PENDING`
- Production: `PENDING`
