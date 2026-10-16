# DDL-2026-006-add-provider-result-to-task-outbox

## Ownership

- Module: `SYSTEM`
- Submodule: `TaskProcessing`
- Feature: `TaskOutbox`
- Layer: Database DDL

## Purpose

Menambahkan penyimpanan durable untuk hasil provider eksternal dan metadata asal notifikasi, terpisah dari metadata transport Cloud Tasks. Ini diperlukan sebelum pilot WhatsApp agar provider message ID tidak disimpan di `Cloud_Task_Name` dan data dapat diagregasi berdasarkan pengirim, module, feature, serta template.

## Status

- Development: `DONE`
- SQL Tracker: `PENDING`
- Production: `PENDING`
- Verification: `DEV_DONE`

## Execution rule

`forward.sql` belum dijalankan. `drop.sql` hanya dokumentasi rollback manual dan tidak boleh dijalankan agent.

## Metadata aggregation

- `Sender_Employee_Code`: kode karyawan pengirim/pemilik notifikasi, bukan actor teknis request;
- `Source_Module`: module bisnis asal;
- `Source_Feature`: feature/use case asal;
- `Message_Template`: template provider atau template pesan yang dipakai.

Kolom metadata ini nullable agar task non-notifikasi tetap dapat memakai Outbox. Nilai harus diisi oleh feature producer ketika konteksnya tersedia.

## Development execution

- Target: `Web_HRIS`
- Result: `9` columns and `2` indexes created
- Initial rows: `0`
- SQL Tracker: `PENDING`
- Production: `PENDING`
