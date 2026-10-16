# Register SYSTEM Feature Group

- Change ID: `20260816_175007_add-system-module`
- Type: DML master data
- Target table: `N_HRIS_Master_Grup_Fitur`
- Intended record: `Kode_Grup_Fitur = SYSTEM`, `Nama_Grup_Fitur = System`
- Expected effect: insert one row if the code does not exist; otherwise no change
- DML artifact: `database/dml/20260816_175007_add-system-module/data-change.sql`
- Overall status: `TRACKER_PENDING`
- Development execution: completed on confirmed development database `Web_HRIS`
- Production execution: `PENDING` — user task through SQL Tracker only
- SQL Tracker status: `PENDING`
- SQL Tracker reference: not recorded yet
- Production status: `PENDING`
- Verification status: `DEV_DONE`
- Rollback: no automatic rollback is executed; if removal is explicitly approved later, handle it as a separately reviewed, scoped DML operation

## Next action

Masukkan isi file `database/dml/20260816_175007_add-system-module/data-change.sql` ke SQL Tracker. Setelah tercatat, simpan reference/ID SQL Tracker di dokumen ini dan ubah status SQL Tracker menjadi `RECORDED`.

## Discovery

The read-only inspection found the active SQL Server database name `Web_HRIS`. The user confirmed this target as development before execution. Production execution remains pending through SQL Tracker.

## Development execution result

- Executed: yes
- Database verified before execution: `Web_HRIS`
- `SYSTEM` count before: `0`
- `SYSTEM` count after: `1`
- Inserted/verified row: `Id_Grup_Fitur = 5`, `Kode_Grup_Fitur = SYSTEM`, `Nama_Grup_Fitur = System`
- Production execution: not performed
