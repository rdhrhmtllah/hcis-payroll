# Development ke Production

1. Agent membuat DDL.
2. Developer melakukan review.
3. Setelah disetujui, agent menjalankan DDL di development.
4. Agent melakukan verifikasi.
5. Agent mencatat DDL byte-for-byte yang sama ke SQL Tracker.
6. Status menjadi `PRODUCTION_PENDING`.
7. Developer menjalankan DDL dari SQL Tracker ke production.
8. Developer mengonfirmasi eksekusi.
9. Production diverifikasi.

`DEV_EXECUTED` tidak berarti `PRODUCTION_EXECUTED`.
