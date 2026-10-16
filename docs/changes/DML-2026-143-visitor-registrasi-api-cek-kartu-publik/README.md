# DML-2026-143-visitor-registrasi-api-cek-kartu-publik

- **Target:** Web_HRIS development dan production
- **Artefak:** `database/dml/DML-2026-143-visitor-registrasi-api-cek-kartu-publik/data-change.sql` (sha256 `68f6afe04d2bacb919306a05996ec131b0c67b2f61113fe38931a1f15c67fe18`)
- **Objek:** `N_HRIS_Api_Aplikasi` (1 aplikasi "Cek Kartu Permit Publik"), `N_HRIS_Api_Endpoint` (`GET /api/v1/hcis/visitor/kartu-permit/{kode}`), `N_HRIS_Api_Akses_Endpoint` (1 hak akses)
- **Desain:** `docs/superpowers/specs/2026-09-28-cek-kartu-permit-publik-design.md`

## Cara menjalankan

1. Bangkitkan pasangan kunci baru (berbeda per lingkungan), mis.
   `php -r 'echo "pub-".bin2hex(random_bytes(16)).PHP_EOL."sec-".bin2hex(random_bytes(32)).PHP_EOL;'`.
2. Isi `@ApiPublic` dan `@ApiSecret` di salinan lokal berkas (jangan di-commit), jalankan.
3. Simpan pasangan kunci ke Secret Manager layanan publik (`HCIS_API_PUBLIC`, `HCIS_API_SECRET`).

## Darurat

Kunci bocor: `UPDATE N_HRIS_Api_Aplikasi SET Flag_Aktif='T' WHERE Nama_Aplikasi=N'Cek Kartu Permit Publik'`, lalu terbitkan kunci baru.
