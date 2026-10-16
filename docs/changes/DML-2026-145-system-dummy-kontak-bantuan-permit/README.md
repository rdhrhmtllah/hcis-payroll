# DML-2026-145-system-dummy-kontak-bantuan-permit

- **Target:** Web_HRIS development saja (nomor & email fiktif). Tidak untuk production.
- **Artefak:** `database/dml/DML-2026-145-system-dummy-kontak-bantuan-permit/data-change.sql` (sha256 `46888f64cbc3d51c29d981812e6d7e7de277e5eb3fb000a07edba9c1abd76a9a`)
- **Objek:** `N_HRIS_Master_Kontak_Bantuan` — 4 baris VISITOR/PERMIT, `Created_By = 'DUMMY-DML-2026-145'`
- **Bergantung pada:** DDL-2026-144-system-master-kontak-bantuan

| # | Kontak | Isi | Harapan di halaman permit |
|---|---|---|---|
| 1 | [DUMMY] Admin Permit SHE | HP + email + jam | Tampil pertama; tombol WhatsApp, Telepon, Email |
| 2 | [DUMMY] Helpdesk GA | email saja | Tampil; hanya tombol Email |
| 3 | [DUMMY] Pos Keamanan Utama | HP saja | Tampil; WhatsApp & Telepon |
| 4 | [DUMMY] Kontak Lama | nonaktif | Tidak tampil |
