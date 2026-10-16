# DDL Change Ledger

Folder ini menyimpan perubahan struktur database SQL Server yang dibuat untuk project.

## Environment gate

CRUD dan eksekusi perubahan database hanya boleh dilakukan otomatis pada target yang sudah diverifikasi sebagai `development` atau `staging`. Host `team311.dyndns.info:1433` tidak otomatis berarti semua database di dalamnya aman; nama database dan environment harus dicocokkan dengan koneksi aktif. Target lain wajib dikonfirmasi user sebelum operasi.

## Aturan

- Folder `<change-id>/forward.sql` adalah forward DDL yang direview dan diuji di development.
- Folder `<change-id>/drop.sql` hanya untuk reset manual oleh developer; agent tidak boleh menjalankannya.
- DML disimpan terpisah di `database/dml/<change-id>/data-change.sql`.
- Forward DDL yang masuk ke SQL Tracker harus sama persis dengan file yang diuji di development.
- Setiap perubahan harus memiliki status workflow dan checksum.
- Status PRODUCTION_EXECUTED hanya boleh diberikan setelah developer mengonfirmasi eksekusi dari SQL Tracker.

## Checklist perubahan

- [ ] DDL dibuat
- [ ] DROP script dibuat sebagai dokumentasi manual
- [ ] Review user selesai
- [ ] DDL dijalankan di development
- [ ] Development diverifikasi
- [ ] DDL yang sama dicatat ke SQL Tracker
- [ ] User menjalankan DDL dari SQL Tracker ke production
- [ ] Production diverifikasi

## Status yang dipakai

DRAFT, REVIEW_REQUIRED, APPROVED, DEV_EXECUTION_PENDING, DEV_EXECUTED, DEV_FAILED, TRACKER_PENDING, TRACKER_RECORDED, PRODUCTION_PENDING, PRODUCTION_EXECUTED, VERIFICATION_PENDING, COMPLETED.
