# DML-2026-185 — Admin VISITOR tunggal (RBAC Tahap B)

Langkah pertama penggabungan peran kembar di VISITOR. `VISITOR_MENU_ADMINISTRATOR` dan `VISITOR_APPROVAL_ADMINISTRATOR` punya hak halaman identik; perbedaannya (menu vs approval) kini cukup diwakili satu Admin modul plus cakupan.

- Membuat `VISITOR_ADMIN` (Jenis `ADMIN`, tingkat `ADMIN`, modul VISITOR) dengan hak salinan `VISITOR_MENU_ADMINISTRATOR`.
- `VISITOR_ADMIN` didaftarkan di `config/authorization.php` (`tata_kelola.peran_admin`) agar halaman tata kelola VISITOR tetap terhibah.
- **Tidak** memindah pemegang dan **tidak** menonaktifkan peran lama — itu change berikutnya, setelah mode VISITOR BAYANGAN menunjukkan selisih nol.
- Peran pool approval VISITOR (`ADMIN_TAMU`, `ADMIN_PERMIT`, `ADMIN_TAMU_B2B`, `SHE_ADMIN`, `TAMU_DEPT_*`) tidak disentuh: identitasnya alur persetujuan & `N_VISITOR_Matriks_Tamu`.

Idempoten. Production: jalankan lewat SQL Tracker setelah DDL-2026-165 (dan idealnya DDL-2026-181).
